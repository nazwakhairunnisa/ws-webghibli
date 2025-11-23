import requests
from bs4 import BeautifulSoup
import json
import re
import time
from urllib.parse import urljoin

class GhibliScraper:
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
        self.movies = []
        self.characters = []
        self.directors = {}

    def get_soup(self, url):
        try:
            resp = self.session.get(url, timeout=12)
            resp.raise_for_status()
            return BeautifulSoup(resp.content, 'html.parser')
        except Exception as e:
            print(f"[ERROR] fetching {url}: {e}")
            return None

    def candidate_list_pages(self):
        return [
            "/wiki/Category:Films",
            "/wiki/List_of_Studio_Ghibli_films",
            "/wiki/Studio_Ghibli_films",
            "/wiki/Studio_Ghibli",
            "/wiki/Category:Studio_Ghibli_films",
        ]

    def scrape_movie_list(self):
        print("[*] Scraping movie list...")
        movie_links = []

        for path in self.candidate_list_pages():
            url = urljoin(self.base_url, path)
            soup = self.get_soup(url)
            if not soup:
                continue

            cat_links = soup.select('a.category-page__member-link')
            if cat_links:
                for a in cat_links:
                    href = a.get('href')
                    title = a.get_text(strip=True)
                    if href and '/wiki/' in href and ':' not in href:
                        movie_links.append({'title': title, 'url': urljoin(self.base_url, href)})
                if movie_links:
                    break

            content = soup.find('div', {'class': 'mw-parser-output'})
            if content:
                for a in content.find_all('a', href=True):
                    href = a['href']
                    title = a.get_text(strip=True)
                    if not title:
                        continue
                    if href.startswith('/wiki/') and ':' not in href and len(title) > 2:
                        low = title.lower()
                        if any(skip in low for skip in ['category', 'file', 'special', 'help']):
                            continue
                        movie_links.append({'title': title, 'url': urljoin(self.base_url, href)})

            if movie_links:
                break

            time.sleep(0.5)

        seen = set()
        unique = []
        for item in movie_links:
            if item['url'] not in seen:
                seen.add(item['url'])
                unique.append(item)

        print(f"[i] Found {len(unique)} candidate movie links.")
        return unique[:30]

    def extract_description(self, soup):
        """Extract first paragraph after main heading for description"""
        content = soup.find('div', class_='mw-parser-output')
        if not content:
            return None
        
        for p in content.find_all('p', recursive=False):
            text = p.get_text(" ", strip=True)
            if len(text) > 100:
                return text[:800]
        
        return None

    def extract_synopsis(self, soup):
        """Extract plot/synopsis from Plot section with subsections"""
        content = soup.find('div', class_='mw-parser-output')
        if not content:
            return None

        plot_heading = None
        for h2 in content.find_all('h2'):
            span = h2.find('span', class_='mw-headline')
            if span and span.get('id') in ['Plot', 'Synopsis', 'Story']:
                plot_heading = h2
                break

        if not plot_heading:
            return None

        synopsis_parts = []
        current = plot_heading.find_next_sibling()
        
        while current:
            if current.name in ['h2', 'h1']:
                break
            
            if current.name == 'p':
                text = current.get_text(" ", strip=True)
                if len(text) > 40:
                    synopsis_parts.append(text)
            
            current = current.find_next_sibling()

        if synopsis_parts:
            full_synopsis = " ".join(synopsis_parts)
            return full_synopsis[:2000]
        
        return None

    def extract_poster(self, soup):
        infobox = soup.find('aside', {'class': 'portable-infobox'})
        if not infobox:
            return None
        img = infobox.find('img')
        return img['src'] if img and img.get('src') else None

    def extract_director(self, soup):
        """Extract director with multiple fallback methods"""
        infobox = soup.find('aside', {'class': 'portable-infobox'})
        if infobox:
            for item in infobox.select('.pi-item'):
                label = item.find(class_='pi-data-label')
                val = item.find(class_='pi-data-value')
                if label and val:
                    label_text = label.get_text(strip=True).lower()
                    if 'director' in label_text or 'directed' in label_text:
                        director_link = val.find('a')
                        if director_link:
                            return director_link.get_text(strip=True)
                        return val.get_text(" ", strip=True)
        
        content = soup.find('div', {'class': 'mw-parser-output'})
        if content:
            for p in content.find_all('p', recursive=False)[:3]:
                text = p.get_text(" ", strip=True)
                match = re.search(r'directed by ([A-Z][a-zA-Z\s]+?)(?:\.|,|\sand\s)', text, re.IGNORECASE)
                if match:
                    return match.group(1).strip()
                
                match = re.search(r'(?:a |the )?film by ([A-Z][a-zA-Z\s]+?)(?:\.|,|\sand\s)', text, re.IGNORECASE)
                if match:
                    return match.group(1).strip()
        
        return None

    def extract_release_year(self, soup):
        infobox = soup.find('aside', {'class': 'portable-infobox'})
        if not infobox:
            return None
        for item in infobox.select('.pi-item'):
            label = item.find(class_='pi-data-label')
            val = item.find(class_='pi-data-value')
            if label and val:
                txt = val.get_text(" ", strip=True)
                year = re.search(r'(19|20)\d{2}', txt)
                if year:
                    return int(year.group())
        return None

    def extract_duration(self, soup):
        infobox = soup.find('aside', {'class': 'portable-infobox'})
        if not infobox:
            return None
        for item in infobox.select('.pi-item'):
            label = item.find(class_='pi-data-label')
            val = item.find(class_='pi-data-value')
            if label and val and 'running time' in label.get_text(strip=True).lower():
                return val.get_text(" ", strip=True)
        return None

    def extract_genres(self, soup):
        """Extract genres from categories and content with fallback"""
        genres = []
        
        infobox = soup.find('aside', {'class': 'portable-infobox'})
        if infobox:
            for item in infobox.select('.pi-item'):
                label = item.find(class_='pi-data-label')
                val = item.find(class_='pi-data-value')
                if label and val and 'genre' in label.get_text(strip=True).lower():
                    genre_text = val.get_text(" ", strip=True)
                    genres_split = re.split(r'[,/;]', genre_text)
                    genres.extend([g.strip() for g in genres_split if g.strip()])
        
        if not genres:
            categories = soup.find('div', {'id': 'mw-normal-catlinks'})
            if categories:
                for link in categories.find_all('a'):
                    cat_text = link.get_text(strip=True)
                    if any(keyword in cat_text.lower() for keyword in ['adventure', 'fantasy', 'drama', 'romance', 'anime']):
                        if 'films' not in cat_text.lower() and 'movies' not in cat_text.lower():
                            genres.append(cat_text)
        
        if not genres:
            content = soup.find('div', {'class': 'mw-parser-output'})
            if content:
                text = content.get_text(" ", strip=True).lower()
                default_genres = []
                if 'adventure' in text or 'journey' in text:
                    default_genres.append('Adventure')
                if 'fantasy' in text or 'magic' in text or 'spirit' in text:
                    default_genres.append('Fantasy')
                if 'romance' in text or 'love' in text:
                    default_genres.append('Romance')
                if 'war' in text or 'battle' in text:
                    default_genres.append('Drama')
                
                if 'anime' not in [g.lower() for g in default_genres]:
                    default_genres.append('Animation')
                
                genres = default_genres[:3]
        
        return genres if genres else ['Animation', 'Fantasy']

    def scrape_movie_detail(self, movie_url, movie_title):
        print(f"[+] Scraping movie: {movie_title}")
        soup = self.get_soup(movie_url)
        if not soup:
            return None

        movie_data = {
            'title': movie_title,
            'url': movie_url,
            'release_year': self.extract_release_year(soup),
            'director': self.extract_director(soup),
            'duration': self.extract_duration(soup),
            'description': self.extract_description(soup),
            'synopsis': self.extract_synopsis(soup),
            'poster_url': self.extract_poster(soup),
            'genres': self.extract_genres(soup),
            'characters': []
        }

        chars = self.scrape_characters_from_movie(soup, movie_title)
        movie_data['characters'] = chars

        return movie_data

    # ====================================================
    # FIXED: Extract deskripsi dari halaman karakter langsung
    # ====================================================
    def get_character_description_from_page(self, char_url):
        """Fetch character description directly from character page"""
        soup = self.get_soup(char_url)
        if not soup:
            return None
        
        content = soup.find('div', {'class': 'mw-parser-output'})
        if not content:
            return None
        
        # Ambil semua paragraf
        paragraphs = content.find_all('p', recursive=False)
        
        for p in paragraphs:
            text = p.get_text(" ", strip=True)
            
            # Skip paragraf pendek
            if len(text) < 50:
                continue
            
            # Skip jika hanya berisi voice actor (banyak tanda kurung dan comma)
            # Pattern: "Name1 (Language), Name2 (Language), Name3 (Language)"
            parentheses_count = text.count('(')
            comma_count = text.count(',')
            
            # Jika lebih dari 2 tanda kurung dan banyak comma dalam 200 karakter pertama -> skip
            if parentheses_count >= 2 and comma_count >= 2 and len(text) < 200:
                continue
            
            # Jika mengandung kata kunci voice actor -> skip
            voice_keywords = ['(Japanese)', '(English)', '(Disney)', '(Streamline)', 'voiced by', 'voice actor']
            if any(keyword in text for keyword in voice_keywords) and len(text) < 200:
                continue
            
            # Ini kemungkinan besar deskripsi karakter yang benar
            return text[:600]
        
        return None

    # ====================================================
    # FIXED: Scrape characters - ambil nama dulu, fetch deskripsi kemudian
    # ====================================================
    def scrape_characters_from_movie(self, soup, movie_title):
        """Extract characters from Characters section, fetch descriptions separately"""
        results = []
        content = soup.find('div', {'class': 'mw-parser-output'})
        if not content:
            return results

        # Cari heading "Characters"
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
        
        # Step 1: Kumpulkan semua character URLs
        while current and char_count < 20:
            if current.name in ['h2', 'h1']:
                break
            
            # Dari definition list
            if current.name == 'dl':
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
            
            # Dari list items
            elif current.name == 'ul':
                for li in current.find_all('li', recursive=False):
                    link = li.find('a', href=True)
                    if link:
                        href = link['href']
                        name = link.get_text(strip=True)
                        
                        skip_words = ['film', 'movie', 'studio', 'category', 
                                     'ghibli', 'miyazaki', 'list', 'main']
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
            
            # Dari paragraf dengan bold
            elif current.name == 'p':
                for bold in current.find_all(['b', 'strong']):
                    char_name = bold.get_text(strip=True)
                    link = bold.find('a', href=True) or bold.find_next('a', href=True)
                    
                    if link and len(char_name) > 1:
                        href = link['href']
                        
                        if (href.startswith('/wiki/') and 
                            ':' not in href):
                            
                            char_url = urljoin(self.base_url, href)
                            
                            if char_url not in [c['url'] for c in char_urls_found]:
                                char_urls_found.append({
                                    'name': char_name,
                                    'url': char_url
                                })
                                char_count += 1
                                
                                if char_count >= 20:
                                    break
            
            current = current.find_next_sibling()

        # Step 2: Fetch deskripsi untuk setiap karakter dari halaman mereka
        print(f"    -> Found {len(char_urls_found)} characters, fetching descriptions...")
        
        for char_info in char_urls_found:
            print(f"       - Fetching description for: {char_info['name']}")
            
            # Fetch deskripsi dari halaman karakter
            description = self.get_character_description_from_page(char_info['url'])
            
            results.append({
                'name': char_info['name'],
                'url': char_info['url'],
                'description': description,
                'appears_in': [movie_title]
            })
            
            # Rate limiting
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
                        if movie_title not in existing['appears_in']:
                            existing['appears_in'].append(movie_title)
                        # Update deskripsi jika lebih baik
                        if char.get('description') and (
                            not existing.get('description') or 
                            len(char['description']) > len(existing.get('description', ''))
                        ):
                            existing['description'] = char['description']

        return results

    def scrape_character_detail(self, char_data):
        """Scrape additional character details (image, age, gender)"""
        print(f"    - Character detail: {char_data['name']}")
        soup = self.get_soup(char_data['url'])
        if not soup:
            return char_data

        infobox = soup.find('aside', {'class': 'portable-infobox'})
        if infobox:
            img = infobox.find('img')
            if img and img.get('src'):
                char_data['image_url'] = img['src']
            
            for div in infobox.select('.pi-item'):
                label = div.find(class_='pi-data-label')
                val = div.find(class_='pi-data-value')
                if label and val:
                    lt = label.get_text(strip=True).lower()
                    vt = val.get_text(" ", strip=True)
                    if 'age' in lt:
                        char_data['age'] = vt
                    elif 'gender' in lt:
                        char_data['gender'] = vt

        return char_data

    def scrape_all(self, scrape_char_details=False):
        print("[*] Starting scraping...")
        movie_links = self.scrape_movie_list()
        print(f"[i] Candidate movie links: {len(movie_links)}")

        for m in movie_links:
            time.sleep(0.8)
            movie = self.scrape_movie_detail(m['url'], m['title'])
            if movie:
                self.movies.append(movie)

        print(f"[i] Movies scraped: {len(self.movies)}")
        print(f"[i] Characters found: {len(self.characters)}")

        if scrape_char_details:
            print("[*] Scraping additional character details (images, age, gender)...")
            for c in self.characters:
                time.sleep(0.5)
                self.scrape_character_detail(c)

        self.extract_directors()
        print("[*] Scraping complete")
        return {
            'movies': self.movies, 
            'characters': self.characters, 
            'directors': list(self.directors.values())
        }

    def extract_directors(self):
        for m in self.movies:
            d = m.get('director')
            if d:
                if d not in self.directors:
                    self.directors[d] = {'name': d, 'notable_works': []}
                self.directors[d]['notable_works'].append(m['title'])

    def save_to_json(self, filename='ghibli_data.json'):
        data = {
            'movies': self.movies,
            'characters': self.characters,
            'directors': list(self.directors.values()),
        }
        with open(filename, 'w', encoding='utf-8') as f:
            json.dump(data, f, ensure_ascii=False, indent=2)
        print(f"[+] Saved JSON => {filename}")
        return filename

    def print_summary(self):
        print("="*40)
        print("SCRAPER SUMMARY")
        print("="*40)
        print(f"Movies: {len(self.movies)}")
        print(f"Characters: {len(self.characters)}")
        print(f"Directors: {len(self.directors)}")
        print("\nSample movies:")
        for m in self.movies[:3]:
            print(f"\n - Title: {m['title']}")
            print(f"   Year: {m.get('release_year')}")
            print(f"   Director: {m.get('director', 'N/A')}")
            print(f"   Genres: {', '.join(m.get('genres', []))}")
            print(f"   Description: {m.get('description', '')[:100]}...")
            print(f"   Synopsis: {m.get('synopsis', '')[:150]}...")
            print(f"   Characters: {len(m.get('characters', []))} found")
            if m.get('characters'):
                first_char = m['characters'][0]
                desc = first_char.get('description', 'N/A')
                if desc and desc != 'N/A':
                    print(f"   First character: {first_char.get('name')}")
                    print(f"   Description: {desc[:120]}...")
        
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
    s = GhibliScraper()
    data = s.scrape_all(scrape_char_details=True)
    s.save_to_json('../data/films.json')
    s.print_summary()