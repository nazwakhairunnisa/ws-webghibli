import requests
from bs4 import BeautifulSoup
import json
import re
import time
from urllib.parse import urljoin

class GhibliShortsScraper:
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
        self.shorts = []

    def get_soup(self, url):
        try:
            resp = self.session.get(url, timeout=12)
            resp.raise_for_status()
            return BeautifulSoup(resp.content, 'html.parser')
        except Exception as e:
            print(f"[ERROR] fetching {url}: {e}")
            return None

    def candidate_list_pages(self):
        """Pages that might list shorts"""
        return [
            "/wiki/Category:Short_films",
            "/wiki/Category:Shorts",
            "/wiki/Studio_Ghibli",
            "/wiki/List_of_Studio_Ghibli_films",
        ]

    def scrape_shorts_list(self):
        """Scrape list of shorts from various pages"""
        print("[*] Scraping shorts list...")
        shorts_links = []

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
                        shorts_links.append({'title': title, 'url': urljoin(self.base_url, href)})

            # Method 2: Content links - look for short films section
            content = soup.find('div', {'class': 'mw-parser-output'})
            if content:
                # Cari section "Short films" atau sejenisnya
                for heading in content.find_all(['h2', 'h3']):
                    heading_text = heading.get_text(strip=True).lower()
                    if 'short' in heading_text:
                        # Ambil links setelah heading ini
                        current = heading.find_next_sibling()
                        while current and current.name not in ['h2', 'h3']:
                            if current:
                                for a in current.find_all('a', href=True):
                                    href = a['href']
                                    title = a.get_text(strip=True)
                                    if (href.startswith('/wiki/') and 
                                        ':' not in href and 
                                        len(title) > 2):
                                        shorts_links.append({
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
        for item in shorts_links:
            if item['url'] not in seen:
                seen.add(item['url'])
                unique.append(item)

        print(f"[i] Found {len(unique)} candidate shorts links.")
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
                    # Try to get link text first, then plain text
                    link = val.find('a')
                    if link:
                        return link.get_text(strip=True)
                    return val.get_text(" ", strip=True)
        return None

    def extract_director(self, soup):
        """Extract director from infobox"""
        return self.extract_from_infobox(soup, ['director', 'directed'])

    def extract_release_date(self, soup):
        """Extract release date from infobox"""
        date_str = self.extract_from_infobox(soup, ['release', 'released', 'premiere'])
        if date_str:
            # Extract year
            year_match = re.search(r'(19|20)\d{2}', date_str)
            if year_match:
                return {
                    'full_date': date_str,
                    'year': int(year_match.group())
                }
        return None

    def extract_duration(self, soup):
        """Extract running time from infobox"""
        return self.extract_from_infobox(soup, ['running time', 'runtime', 'duration', 'length'])

    def extract_studio(self, soup):
        """Extract studio from infobox"""
        return self.extract_from_infobox(soup, ['studio', 'production'])

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

    def extract_plot(self, soup):
        """Extract plot/synopsis from Plot section"""
        content = soup.find('div', class_='mw-parser-output')
        if not content:
            return None

        # Find Plot heading
        plot_heading = None
        for h2 in content.find_all('h2'):
            span = h2.find('span', class_='mw-headline')
            if span and span.get('id') in ['Plot', 'Synopsis', 'Story']:
                plot_heading = h2
                break

        if not plot_heading:
            return None

        # Collect paragraphs under Plot section
        plot_parts = []
        current = plot_heading.find_next_sibling()
        
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

    def scrape_short_detail(self, short_url, short_title):
        """Scrape detailed information for a short film"""
        print(f"[+] Scraping short: {short_title}")
        soup = self.get_soup(short_url)
        if not soup:
            return None

        release_info = self.extract_release_date(soup)
        
        short_data = {
            'title': short_title,
            'url': short_url,
            'director': self.extract_director(soup),
            'release_date': release_info['full_date'] if release_info else None,
            'release_year': release_info['year'] if release_info else None,
            'duration': self.extract_duration(soup),
            'studio': self.extract_studio(soup),
            'description': self.extract_description(soup),
            'plot': self.extract_plot(soup),
            'poster_url': self.extract_poster(soup),
        }

        return short_data

    def scrape_all(self):
        """Scrape all shorts"""
        print("[*] Starting shorts scraping...")
        shorts_links = self.scrape_shorts_list()
        print(f"[i] Candidate shorts links: {len(shorts_links)}")

        for s in shorts_links:
            time.sleep(0.8)
            short = self.scrape_short_detail(s['url'], s['title'])
            if short:
                # Verify it's actually a short (has duration or other short-film indicators)
                if short.get('duration') or short.get('director'):
                    self.shorts.append(short)

        print(f"[i] Shorts scraped: {len(self.shorts)}")
        print("[*] Scraping complete")
        return {'shorts': self.shorts}

    def save_to_json(self, filename='shorts.json'):
        """Save shorts data to JSON file"""
        data = {'shorts': self.shorts}
        with open(filename, 'w', encoding='utf-8') as f:
            json.dump(data, f, ensure_ascii=False, indent=2)
        print(f"[+] Saved JSON => {filename}")
        return filename

    def print_summary(self):
        """Print summary of scraped data"""
        print("="*40)
        print("SHORTS SCRAPER SUMMARY")
        print("="*40)
        print(f"Total Shorts: {len(self.shorts)}")
        
        print("\nShorts found:")
        for s in self.shorts:
            print(f"\n - Title: {s['title']}")
            print(f"   Director: {s.get('director', 'N/A')}")
            print(f"   Release: {s.get('release_date', 'N/A')}")
            print(f"   Duration: {s.get('duration', 'N/A')}")
            print(f"   Studio: {s.get('studio', 'N/A')}")
            if s.get('description'):
                print(f"   Description: {s['description'][:100]}...")
            if s.get('plot'):
                print(f"   Plot: {s['plot'][:100]}...")

if __name__ == "__main__":
    scraper = GhibliShortsScraper()
    data = scraper.scrape_all()
    scraper.save_to_json('../data/shorts.json')
    scraper.print_summary()