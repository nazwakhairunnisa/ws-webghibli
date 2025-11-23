import requests
from bs4 import BeautifulSoup
import json
import re
import time
from urllib.parse import urljoin

class GhibliSeriesScraper:
    def __init__(self):
        self.base_url = "https://ghibli.fandom.com"
        self.headers = {
            'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'Accept-Language': 'en-US,en;q=0.9',
            'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Referer': 'https://google.com'
        }
        self.session = requests.Session()
        self.session.headers.update(self.headers)
        self.series = []
        self.characters = []

    def get_soup(self, url):
        try:
            resp = self.session.get(url, timeout=12)
            resp.raise_for_status()
            return BeautifulSoup(resp.content, 'html.parser')
        except Exception as e:
            print(f"[ERROR] fetching {url}: {e}")
            return None

    def get_known_series_urls(self):
        """Direct URLs for known Ghibli series"""
        return [
            {'title': "Ronja, the Robber's Daughter", 'url': "https://ghibli.fandom.com/wiki/Ronja,_the_Robber%27s_Daughter"},
            {'title': 'Sherlock Hound', 'url': 'https://ghibli.fandom.com/wiki/Sherlock_Hound'},
            {'title': 'Film Guru Guru', 'url': 'https://ghibli.fandom.com/wiki/Film_Guru_Guru'},
        ]

    def candidate_list_pages(self):
        """Pages that might list series"""
        return [
            "/wiki/Category:Television_series",
            "/wiki/Category:Series",
            "/wiki/Studio_Ghibli",
            "/wiki/List_of_Studio_Ghibli_films",
        ]

    def scrape_series_list(self):
        """Scrape list of series from various pages"""
        print("[*] Scraping series list...")
        
        # Start with known series
        series_links = self.get_known_series_urls()
        
        for path in self.candidate_list_pages():
            url = urljoin(self.base_url, path)
            soup = self.get_soup(url)
            if not soup:
                continue

            # Method 1: Category page links
            cat_links = soup.select('a.category-page__member-link')
            if cat_links:
                for a in cat_links:
                    href = a.get('href')
                    title = a.get_text(strip=True)
                    if href and '/wiki/' in href and ':' not in href:
                        series_links.append({'title': title, 'url': urljoin(self.base_url, href)})

            # Method 2: Content links - look for TV series section
            content = soup.find('div', {'class': 'mw-parser-output'})
            if content:
                for heading in content.find_all(['h2', 'h3']):
                    heading_text = heading.get_text(strip=True).lower()
                    if any(keyword in heading_text for keyword in ['television', 'tv', 'series']):
                        current = heading.find_next_sibling()
                        while current and current.name not in ['h2', 'h3']:
                            if current:
                                for a in current.find_all('a', href=True):
                                    href = a['href']
                                    title = a.get_text(strip=True)
                                    if (href.startswith('/wiki/') and 
                                        ':' not in href and 
                                        len(title) > 2):
                                        series_links.append({
                                            'title': title, 
                                            'url': urljoin(self.base_url, href)
                                        })
                            current = current.find_next_sibling()
                            if not current:
                                break

            time.sleep(0.5)

        # Remove duplicates
        seen = set()
        unique = []
        for item in series_links:
            if item['url'] not in seen:
                seen.add(item['url'])
                unique.append(item)

        print(f"[i] Found {len(unique)} candidate series links.")
        return unique

    def extract_from_infobox(self, soup, label_keywords):
        """Extract value from infobox by label keywords"""
        infobox = soup.find('aside', {'class': 'portable-infobox'})
        if not infobox:
            return None
        
        for item in infobox.select('.pi-item'):
            label = item.find(class_='pi-data-label')
            val = item.find(class_='pi-data-value')
            if label and val:
                label_text = label.get_text(strip=True).lower()
                if any(keyword in label_text for keyword in label_keywords):
                    link = val.find('a')
                    if link:
                        return link.get_text(strip=True)
                    return val.get_text(" ", strip=True)
        return None

    def extract_director(self, soup):
        """Extract director from infobox or content"""
        # Try infobox first
        director = self.extract_from_infobox(soup, ['director', 'directed'])
        if director:
            return director
        
        # Fallback: search in first paragraph
        content = soup.find('div', {'class': 'mw-parser-output'})
        if content:
            for p in content.find_all('p', recursive=False)[:3]:
                text = p.get_text(" ", strip=True)
                match = re.search(r'directed by ([A-Z][a-zA-Z\s]+?)(?:\sand\s|,|\.|$)', text, re.IGNORECASE)
                if match:
                    return match.group(1).strip()
        
        return None

    def extract_episodes(self, soup):
        """Extract number of episodes from various sources"""
        # Try infobox
        episodes = self.extract_from_infobox(soup, ['episodes', 'no. of episodes', 'episode'])
        if episodes:
            match = re.search(r'(\d+)', episodes)
            if match:
                return int(match.group(1))
        
        # Try first paragraph
        content = soup.find('div', {'class': 'mw-parser-output'})
        if content:
            for p in content.find_all('p', recursive=False)[:3]:
                text = p.get_text(" ", strip=True)
                match = re.search(r'(\d+)\s*episode', text, re.IGNORECASE)
                if match:
                    return int(match.group(1))
        
        return None

    def extract_release_info(self, soup):
        """Extract release date and year"""
        # Try infobox
        date_str = self.extract_from_infobox(soup, ['release', 'released', 'aired', 'original run', 'premiered'])
        if date_str:
            year_match = re.search(r'(19|20)\d{2}', date_str)
            return {
                'release_date': date_str,
                'release_year': int(year_match.group()) if year_match else None
            }
        
        # Fallback: search in first paragraph
        content = soup.find('div', {'class': 'mw-parser-output'})
        if content:
            for p in content.find_all('p', recursive=False)[:2]:
                text = p.get_text(" ", strip=True)
                year_match = re.search(r'(19|20)\d{2}', text)
                if year_match:
                    return {
                        'release_date': None,
                        'release_year': int(year_match.group())
                    }
        
        return {'release_date': None, 'release_year': None}

    def extract_running_time(self, soup):
        """Extract running time/duration"""
        return self.extract_from_infobox(soup, ['running time', 'runtime', 'duration', 'length'])

    def extract_studio(self, soup):
        """Extract studio from infobox"""
        return self.extract_from_infobox(soup, ['studio', 'production', 'producer', 'produced by'])

    def extract_description(self, soup):
        """Extract first meaningful paragraph as description"""
        content = soup.find('div', class_='mw-parser-output')
        if not content:
            return None
        
        for p in content.find_all('p', recursive=False):
            text = p.get_text(" ", strip=True)
            if len(text) > 100:
                return text[:800]
        
        return None

    def extract_plot_or_overview(self, soup):
        """Extract plot/synopsis/overview from various sections"""
        content = soup.find('div', class_='mw-parser-output')
        if not content:
            return None

        # Try to find Plot, Synopsis, Story, Overview, or Premise heading
        target_heading = None
        for h2 in content.find_all('h2'):
            span = h2.find('span', class_='mw-headline')
            if span:
                heading_id = span.get('id', '').lower()
                heading_text = span.get_text(strip=True).lower()
                if any(keyword in heading_id or keyword in heading_text 
                       for keyword in ['plot', 'synopsis', 'story', 'premise', 'overview']):
                    target_heading = h2
                    break

        if not target_heading:
            return None

        # Collect paragraphs under the section
        plot_parts = []
        current = target_heading.find_next_sibling()
        
        while current:
            if current.name in ['h2', 'h1']:
                break
            
            if current.name == 'p':
                text = current.get_text(" ", strip=True)
                if len(text) > 40:
                    plot_parts.append(text)
            
            current = current.find_next_sibling()

        if plot_parts:
            full_plot = " ".join(plot_parts)
            return full_plot[:2000]
        
        return None

    def extract_poster(self, soup):
        """Extract poster image from infobox"""
        infobox = soup.find('aside', {'class': 'portable-infobox'})
        if not infobox:
            return None
        img = infobox.find('img')
        return img['src'] if img and img.get('src') else None

    def get_character_description_from_page(self, char_url):
        """Fetch character description directly from character page"""
        soup = self.get_soup(char_url)
        if not soup:
            return None
        
        content = soup.find('div', {'class': 'mw-parser-output'})
        if not content:
            return None
        
        paragraphs = content.find_all('p', recursive=False)
        
        for p in paragraphs:
            text = p.get_text(" ", strip=True)
            
            if len(text) < 50:
                continue
            
            # Skip voice actor lines
            parentheses_count = text.count('(')
            comma_count = text.count(',')
            
            if parentheses_count >= 2 and comma_count >= 2 and len(text) < 200:
                continue
            
            voice_keywords = ['(Japanese)', '(English)', '(Disney)', 'voiced by', 'voice actor']
            if any(keyword in text for keyword in voice_keywords) and len(text) < 200:
                continue
            
            return text[:600]
        
        return None

    def scrape_characters_from_series(self, soup, series_title):
        """Extract characters from Characters section"""
        results = []
        content = soup.find('div', {'class': 'mw-parser-output'})
        if not content:
            return results

        # Find Characters heading
        char_heading = None
        for h2 in content.find_all('h2'):
            span = h2.find('span', class_='mw-headline')
            if span and 'character' in span.get_text(strip=True).lower():
                char_heading = h2
                break

        if not char_heading:
            return results

        current = char_heading.find_next_sibling()
        char_count = 0
        char_urls_found = []
        
        # Collect character URLs from various formats
        while current and char_count < 20:
            if current.name in ['h2', 'h1']:
                break
            
            # Skip h3/h4 headings (like "Principal cast", "Secondary cast")
            if current.name in ['h3', 'h4']:
                current = current.find_next_sibling()
                continue
            
            # From table (common in TV series pages)
            if current.name == 'table':
                for row in current.find_all('tr'):
                    cells = row.find_all(['td', 'th'])
                    if cells:
                        # First cell usually contains character name
                        first_cell = cells[0]
                        link = first_cell.find('a', href=True)
                        if link:
                            href = link['href']
                            name = link.get_text(strip=True)
                            
                            if (href.startswith('/wiki/') and 
                                ':' not in href and 
                                len(name) > 1 and
                                'List_of' not in href):
                                
                                char_url = urljoin(self.base_url, href)
                                
                                if char_url not in [c['url'] for c in char_urls_found]:
                                    char_urls_found.append({
                                        'name': name,
                                        'url': char_url
                                    })
                                    char_count += 1
                                    
                                    if char_count >= 20:
                                        break
            
            # From definition list
            elif current.name == 'dl':
                for dt in current.find_all('dt'):
                    link = dt.find('a', href=True)
                    if link:
                        href = link['href']
                        name = link.get_text(strip=True)
                        
                        if (href.startswith('/wiki/') and 
                            ':' not in href and 
                            len(name) > 1):
                            
                            char_url = urljoin(self.base_url, href)
                            
                            if char_url not in [c['url'] for c in char_urls_found]:
                                char_urls_found.append({
                                    'name': name,
                                    'url': char_url
                                })
                                char_count += 1
                                
                                if char_count >= 20:
                                    break
            
            # From list items
            elif current.name == 'ul':
                for li in current.find_all('li', recursive=False):
                    link = li.find('a', href=True)
                    if link:
                        href = link['href']
                        name = link.get_text(strip=True)
                        
                        skip_words = ['film', 'movie', 'studio', 'category', 
                                     'ghibli', 'list', 'main', 'episode']
                        if any(skip in name.lower() for skip in skip_words):
                            continue
                        
                        if (href.startswith('/wiki/') and 
                            ':' not in href and 
                            len(name) > 1):
                            
                            char_url = urljoin(self.base_url, href)
                            
                            if char_url not in [c['url'] for c in char_urls_found]:
                                char_urls_found.append({
                                    'name': name,
                                    'url': char_url
                                })
                                char_count += 1
                                
                                if char_count >= 20:
                                    break
            
            current = current.find_next_sibling()

        # Fetch descriptions for each character (if valid character pages exist)
        if char_urls_found:
            print(f"    -> Found {len(char_urls_found)} characters, fetching descriptions...")
        
        for char_info in char_urls_found:
            print(f"       - Fetching description for: {char_info['name']}")
            
            description = self.get_character_description_from_page(char_info['url'])
            
            results.append({
                'name': char_info['name'],
                'url': char_info['url'],
                'description': description,
                'appears_in': [series_title]
            })
            
            time.sleep(0.5)

        # Update global characters list
        seen_urls = {c['url'] for c in self.characters}
        for char in results:
            if char['url'] not in seen_urls:
                self.characters.append(char)
                seen_urls.add(char['url'])
            else:
                for existing in self.characters:
                    if existing['url'] == char['url']:
                        if series_title not in existing['appears_in']:
                            existing['appears_in'].append(series_title)
                        if char.get('description') and (
                            not existing.get('description') or 
                            len(char['description']) > len(existing.get('description', ''))
                        ):
                            existing['description'] = char['description']

        return results

    def scrape_series_detail(self, series_url, series_title):
        """Scrape detailed information for a series"""
        print(f"[+] Scraping series: {series_title}")
        soup = self.get_soup(series_url)
        if not soup:
            return None

        release_info = self.extract_release_info(soup)
        
        series_data = {
            'title': series_title,
            'url': series_url,
            'director': self.extract_director(soup),
            'release_date': release_info['release_date'],
            'release_year': release_info['release_year'],
            'episodes': self.extract_episodes(soup),
            'running_time': self.extract_running_time(soup),
            'studio': self.extract_studio(soup),
            'description': self.extract_description(soup),
            'plot': self.extract_plot_or_overview(soup),
            'poster_url': self.extract_poster(soup),
            'characters': []
        }

        # Scrape characters
        chars = self.scrape_characters_from_series(soup, series_title)
        series_data['characters'] = chars

        return series_data

    def scrape_all(self):
        """Scrape all series"""
        print("[*] Starting series scraping...")
        series_links = self.scrape_series_list()
        print(f"[i] Candidate series links: {len(series_links)}")

        for s in series_links:
            time.sleep(0.8)
            series = self.scrape_series_detail(s['url'], s['title'])
            if series:
                # Add to list if it has any meaningful data
                if (series.get('director') or 
                    series.get('episodes') or 
                    series.get('description') or
                    series.get('plot')):
                    self.series.append(series)

        print(f"[i] Series scraped: {len(self.series)}")
        print(f"[i] Characters found: {len(self.characters)}")
        print("[*] Scraping complete")
        return {
            'series': self.series,
            'characters': self.characters
        }

    def save_to_json(self, filename='series.json'):
        """Save series data to JSON file"""
        data = {
            'series': self.series,
            'characters': self.characters
        }
        with open(filename, 'w', encoding='utf-8') as f:
            json.dump(data, f, ensure_ascii=False, indent=2)
        print(f"[+] Saved JSON => {filename}")
        return filename

    def print_summary(self):
        """Print summary of scraped data"""
        print("="*40)
        print("SERIES SCRAPER SUMMARY")
        print("="*40)
        print(f"Total Series: {len(self.series)}")
        print(f"Total Characters: {len(self.characters)}")
        
        print("\nSeries found:")
        for s in self.series:
            print(f"\n - Title: {s['title']}")
            print(f"   Director: {s.get('director', 'N/A')}")
            print(f"   Release Date: {s.get('release_date', 'N/A')}")
            print(f"   Release Year: {s.get('release_year', 'N/A')}")
            print(f"   Episodes: {s.get('episodes', 'N/A')}")
            print(f"   Running Time: {s.get('running_time', 'N/A')}")
            print(f"   Studio: {s.get('studio', 'N/A')}")
            if s.get('description'):
                print(f"   Description: {s['description'][:100]}...")
            if s.get('plot'):
                print(f"   Plot: {s['plot'][:100]}...")
            print(f"   Characters: {len(s.get('characters', []))} found")
            if s.get('characters'):
                for idx, char in enumerate(s['characters'][:3]):
                    desc = char.get('description', 'N/A')
                    if desc and desc != 'N/A':
                        print(f"   Character {idx+1}: {char.get('name')}")
                        print(f"      Description: {desc[:80]}...")
        
        print("\nSample characters:")
        for c in self.characters[:5]:
            print(f"\n - {c['name']}")
            print(f"   Appears in: {', '.join(c.get('appears_in', []))}")
            desc = c.get('description', 'N/A')
            if desc and desc != 'N/A':
                print(f"   Description: {desc[:150]}...")
            else:
                print(f"   Description: N/A")

if __name__ == "__main__":
    scraper = GhibliSeriesScraper()
    data = scraper.scrape_all()
    scraper.save_to_json('../data/series.json')
    scraper.print_summary()