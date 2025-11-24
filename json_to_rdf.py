import json
import re
from datetime import datetime
import os

class GhibliRDFConverter:
    def __init__(self, films_json=None, series_json=None, shorts_json=None):
        """
        Initialize converter with paths to JSON files
        
        Args:
            films_json: Path to films JSON file (default: ghibli_data.json)
            series_json: Path to series JSON file (default: series.json)
            shorts_json: Path to shorts JSON file (default: shorts.json)
        """
        self.films_data = {}
        self.series_data = {}
        self.shorts_data = {}
        
        # Load films data
        if films_json and os.path.exists(films_json):
            print(f"Loading films from: {films_json}")
            with open(films_json, 'r', encoding='utf-8') as f:
                self.films_data = json.load(f)
        
        # Load series data
        if series_json and os.path.exists(series_json):
            print(f"Loading series from: {series_json}")
            with open(series_json, 'r', encoding='utf-8') as f:
                self.series_data = json.load(f)
        
        # Load shorts data
        if shorts_json and os.path.exists(shorts_json):
            print(f"Loading shorts from: {shorts_json}")
            with open(shorts_json, 'r', encoding='utf-8') as f:
                self.shorts_data = json.load(f)
        
        self.namespaces = {
            'ghibli': 'http://ghibliwiki.org/ontology#',
            'movie': 'http://ghibliwiki.org/movie/',
            'series': 'http://ghibliwiki.org/series/',
            'short': 'http://ghibliwiki.org/short/',
            'char': 'http://ghibliwiki.org/character/',
            'director': 'http://ghibliwiki.org/director/',
            'genre': 'http://ghibliwiki.org/genre/',
            'studio': 'http://ghibliwiki.org/studio/',
            'rdf': 'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
            'rdfs': 'http://www.w3.org/2000/01/rdf-schema#',
            'xsd': 'http://www.w3.org/2001/XMLSchema#',
            'owl': 'http://www.w3.org/2002/07/owl#'
        }
    
    def sanitize_uri(self, text):
        """Convert text ke URI-safe format"""
        if not text:
            return "unknown"
        # Remove special characters, replace spaces with underscore
        text = re.sub(r'[^\w\s-]', '', text)
        text = re.sub(r'[\s]+', '_', text)
        return text.lower()
    
    def escape_literal(self, text):
        """Escape string untuk RDF literal"""
        if not text:
            return ""
        text = str(text)
        text = text.replace('\\', '\\\\')
        text = text.replace('"', '\\"')
        text = text.replace('\n', '\\n')
        text = text.replace('\r', '\\r')
        return text
    
    def write_prefixes(self):
        """Generate namespace prefixes"""
        lines = ["# Ghibli Wiki RDF Dataset"]
        lines.append(f"# Generated: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
        lines.append("# Source: Ghibli Fandom Wiki")
        lines.append("")
        
        for prefix, uri in self.namespaces.items():
            lines.append(f"@prefix {prefix}: <{uri}> .")
        
        lines.append("")
        return "\n".join(lines)
    
    def convert_films(self):
        """Convert films data ke RDF"""
        movies = self.films_data.get('movies', [])
        if not movies:
            return ""
        
        lines = ["# =============================="]
        lines.append("# FILMS")
        lines.append("# ==============================\n")
        
        for movie in movies:
            movie_id = self.sanitize_uri(movie['title'])
            movie_uri = f"movie:{movie_id}"
            
            lines.append(f"{movie_uri} a ghibli:Film ;")
            lines.append(f'    ghibli:title "{self.escape_literal(movie["title"])}" ;')
            
            if movie.get('release_year'):
                lines.append(f'    ghibli:releaseYear {movie["release_year"]} ;')
            
            if movie.get('director'):
                director_id = self.sanitize_uri(movie['director'])
                lines.append(f'    ghibli:hasDirector director:{director_id} ;')
            
            if movie.get('duration'):
                duration = self.escape_literal(movie['duration'])
                lines.append(f'    ghibli:duration "{duration}" ;')
            
            if movie.get('description'):
                desc = self.escape_literal(movie['description'])
                lines.append(f'    ghibli:description "{desc}" ;')
            
            if movie.get('synopsis'):
                synopsis = self.escape_literal(movie['synopsis'])
                lines.append(f'    ghibli:synopsis "{synopsis}" ;')
            
            if movie.get('poster_url'):
                lines.append(f'    ghibli:posterURL <{movie["poster_url"]}> ;')
            
            # Add genres
            if movie.get('genres'):
                for genre in movie['genres']:
                    genre_id = self.sanitize_uri(genre)
                    lines.append(f'    ghibli:hasGenre genre:{genre_id} ;')
            
            # Add characters
            if movie.get('characters'):
                for char in movie['characters']:
                    char_name = char.get('name') if isinstance(char, dict) else char
                    if char_name:
                        char_id = self.sanitize_uri(char_name)
                        lines.append(f'    ghibli:hasCharacter char:{char_id} ;')
            
            # Remove last semicolon and add period
            lines[-1] = lines[-1].rstrip(';') + ' .'
            lines.append("")
        
        return "\n".join(lines)
    
    def convert_series(self):
        """Convert series data ke RDF"""
        series_list = self.series_data.get('series', [])
        if not series_list:
            return ""
        
        lines = ["# =============================="]
        lines.append("# TV SERIES")
        lines.append("# ==============================\n")
        
        for series in series_list:
            series_id = self.sanitize_uri(series['title'])
            series_uri = f"series:{series_id}"
            
            lines.append(f"{series_uri} a ghibli:Series ;")
            lines.append(f'    ghibli:title "{self.escape_literal(series["title"])}" ;')
            
            if series.get('release_year'):
                lines.append(f'    ghibli:releaseYear {series["release_year"]} ;')
            
            if series.get('release_date'):
                date = self.escape_literal(series['release_date'])
                lines.append(f'    ghibli:releaseDate "{date}" ;')
            
            if series.get('director'):
                director_id = self.sanitize_uri(series['director'])
                lines.append(f'    ghibli:hasDirector director:{director_id} ;')
            
            if series.get('episodes'):
                lines.append(f'    ghibli:numberOfEpisodes {series["episodes"]} ;')
            
            if series.get('running_time'):
                runtime = self.escape_literal(series['running_time'])
                lines.append(f'    ghibli:runningTime "{runtime}" ;')
            
            if series.get('studio'):
                studio_id = self.sanitize_uri(series['studio'])
                lines.append(f'    ghibli:producedBy studio:{studio_id} ;')
            
            if series.get('description'):
                desc = self.escape_literal(series['description'])
                lines.append(f'    ghibli:description "{desc}" ;')
            
            if series.get('plot'):
                plot = self.escape_literal(series['plot'])
                lines.append(f'    ghibli:plot "{plot}" ;')
            
            if series.get('poster_url'):
                lines.append(f'    ghibli:posterURL <{series["poster_url"]}> ;')
            
            # Add characters
            if series.get('characters'):
                for char in series['characters']:
                    char_name = char.get('name') if isinstance(char, dict) else char
                    if char_name:
                        char_id = self.sanitize_uri(char_name)
                        lines.append(f'    ghibli:hasCharacter char:{char_id} ;')
            
            # Remove last semicolon and add period
            lines[-1] = lines[-1].rstrip(';') + ' .'
            lines.append("")
        
        return "\n".join(lines)
    
    def convert_shorts(self):
        """Convert shorts data ke RDF"""
        shorts = self.shorts_data.get('shorts', [])
        if not shorts:
            return ""
        
        lines = ["# =============================="]
        lines.append("# SHORT FILMS")
        lines.append("# ==============================\n")
        
        for short in shorts:
            short_id = self.sanitize_uri(short['title'])
            short_uri = f"short:{short_id}"
            
            lines.append(f"{short_uri} a ghibli:ShortFilm ;")
            lines.append(f'    ghibli:title "{self.escape_literal(short["title"])}" ;')
            
            if short.get('release_year'):
                lines.append(f'    ghibli:releaseYear {short["release_year"]} ;')
            
            if short.get('release_date'):
                date = self.escape_literal(short['release_date'])
                lines.append(f'    ghibli:releaseDate "{date}" ;')
            
            if short.get('director'):
                director_id = self.sanitize_uri(short['director'])
                lines.append(f'    ghibli:hasDirector director:{director_id} ;')
            
            if short.get('duration'):
                duration = self.escape_literal(short['duration'])
                lines.append(f'    ghibli:duration "{duration}" ;')
            
            if short.get('studio'):
                studio_id = self.sanitize_uri(short['studio'])
                lines.append(f'    ghibli:producedBy studio:{studio_id} ;')
            
            if short.get('description'):
                desc = self.escape_literal(short['description'])
                lines.append(f'    ghibli:description "{desc}" ;')
            
            if short.get('plot'):
                plot = self.escape_literal(short['plot'])
                lines.append(f'    ghibli:plot "{plot}" ;')
            
            if short.get('poster_url'):
                lines.append(f'    ghibli:posterURL <{short["poster_url"]}> ;')
            
            # Remove last semicolon and add period
            lines[-1] = lines[-1].rstrip(';') + ' .'
            lines.append("")
        
        return "\n".join(lines)
    
    def convert_characters(self):
        """Convert all characters data ke RDF"""
        lines = ["# =============================="]
        lines.append("# CHARACTERS")
        lines.append("# ==============================\n")
        
        # Collect all characters from all sources
        all_characters = {}
        
        # From films
        for char in self.films_data.get('characters', []):
            char_id = self.sanitize_uri(char['name'])
            if char_id not in all_characters:
                all_characters[char_id] = char
        
        # From series
        for char in self.series_data.get('characters', []):
            char_id = self.sanitize_uri(char['name'])
            if char_id not in all_characters:
                all_characters[char_id] = char
            else:
                # Merge appears_in
                existing_appears = set(all_characters[char_id].get('appears_in', []))
                new_appears = set(char.get('appears_in', []))
                all_characters[char_id]['appears_in'] = list(existing_appears | new_appears)
        
        # Generate RDF for each character
        for char_id, char in all_characters.items():
            char_uri = f"char:{char_id}"
            
            lines.append(f"{char_uri} a ghibli:Character ;")
            lines.append(f'    ghibli:name "{self.escape_literal(char["name"])}" ;')
            
            if char.get('age'):
                age = self.escape_literal(char['age'])
                lines.append(f'    ghibli:age "{age}" ;')
            
            if char.get('gender'):
                gender = self.escape_literal(char['gender'])
                lines.append(f'    ghibli:gender "{gender}" ;')
            
            if char.get('description'):
                desc = self.escape_literal(char['description'])
                lines.append(f'    ghibli:description "{desc}" ;')
            
            if char.get('image_url'):
                lines.append(f'    ghibli:imageURL <{char["image_url"]}> ;')
            
            # Add appearsIn relations
            for title in char.get('appears_in', []):
                # Determine if it's a film, series, or unknown
                title_id = self.sanitize_uri(title)
                
                # Check if it's in series
                is_series = any(s['title'] == title for s in self.series_data.get('series', []))
                # Check if it's in films
                is_film = any(m['title'] == title for m in self.films_data.get('movies', []))
                
                if is_series:
                    lines.append(f'    ghibli:appearsIn series:{title_id} ;')
                elif is_film:
                    lines.append(f'    ghibli:appearsIn movie:{title_id} ;')
                else:
                    # Default to movie namespace
                    lines.append(f'    ghibli:appearsIn movie:{title_id} ;')
            
            # Remove last semicolon and add period
            lines[-1] = lines[-1].rstrip(';') + ' .'
            lines.append("")
        
        return "\n".join(lines)
    
    def convert_directors(self):
        """Convert all directors data ke RDF"""
        lines = ["# =============================="]
        lines.append("# DIRECTORS")
        lines.append("# ==============================\n")
        
        # Collect all unique directors
        all_directors = {}
        
        # From films - with full detail data
        for director in self.films_data.get('directors', []):
            director_id = self.sanitize_uri(director['name'])
            if director_id not in all_directors:
                all_directors[director_id] = {
                    'name': director['name'],
                    'born': director.get('born'),
                    'birth_year': director.get('birth_year'),
                    'nationality': director.get('nationality'),
                    'description': director.get('description'),
                    'history': director.get('history'),
                    'url': director.get('url'),
                    'works': set()
                }
            all_directors[director_id]['works'].update(director.get('notable_works', []))
        
        # From series
        for series in self.series_data.get('series', []):
            if series.get('director'):
                director_id = self.sanitize_uri(series['director'])
                if director_id not in all_directors:
                    all_directors[director_id] = {
                        'name': series['director'],
                        'works': set()
                    }
                all_directors[director_id]['works'].add(series['title'])
        
        # From shorts
        for short in self.shorts_data.get('shorts', []):
            if short.get('director'):
                director_id = self.sanitize_uri(short['director'])
                if director_id not in all_directors:
                    all_directors[director_id] = {
                        'name': short['director'],
                        'works': set()
                    }
                all_directors[director_id]['works'].add(short['title'])
        
        # Generate RDF
        for director_id, director_info in all_directors.items():
            director_uri = f"director:{director_id}"
            
            lines.append(f"{director_uri} a ghibli:Director ;")
            lines.append(f'    ghibli:name "{self.escape_literal(director_info["name"])}" ;')
            
            # Add detailed information if available
            if director_info.get('born'):
                born = self.escape_literal(director_info['born'])
                lines.append(f'    ghibli:born "{born}" ;')
            
            if director_info.get('birth_year'):
                lines.append(f'    ghibli:birthYear {director_info["birth_year"]} ;')
            
            if director_info.get('nationality'):
                nationality = self.escape_literal(director_info['nationality'])
                lines.append(f'    ghibli:nationality "{nationality}" ;')
            
            if director_info.get('description'):
                desc = self.escape_literal(director_info['description'])
                lines.append(f'    ghibli:description "{desc}" ;')
            
            if director_info.get('history'):
                history = self.escape_literal(director_info['history'])
                lines.append(f'    ghibli:history "{history}" ;')
            
            if director_info.get('url'):
                lines.append(f'    ghibli:wikiURL <{director_info["url"]}> ;')
            
            # Add directed works
            for work in director_info['works']:
                work_id = self.sanitize_uri(work)
                
                # Determine work type
                is_series = any(s['title'] == work for s in self.series_data.get('series', []))
                is_short = any(s['title'] == work for s in self.shorts_data.get('shorts', []))
                
                if is_series:
                    lines.append(f'    ghibli:directs series:{work_id} ;')
                elif is_short:
                    lines.append(f'    ghibli:directs short:{work_id} ;')
                else:
                    lines.append(f'    ghibli:directs movie:{work_id} ;')
            
            # Remove last semicolon and add period
            lines[-1] = lines[-1].rstrip(';') + ' .'
            lines.append("")
        
        return "\n".join(lines)
    
    def convert_genres(self):
        """Convert all genres ke RDF"""
        lines = ["# =============================="]
        lines.append("# GENRES")
        lines.append("# ==============================\n")
        
        # Collect unique genres from films
        genres = set()
        for movie in self.films_data.get('movies', []):
            for genre in movie.get('genres', []):
                genres.add(genre)
        
        for genre in sorted(genres):
            genre_id = self.sanitize_uri(genre)
            genre_uri = f"genre:{genre_id}"
            
            lines.append(f"{genre_uri} a ghibli:Genre ;")
            lines.append(f'    ghibli:name "{self.escape_literal(genre)}" .')
            lines.append("")
        
        return "\n".join(lines)
    
    def convert_studios(self):
        """Convert all studios ke RDF"""
        lines = ["# =============================="]
        lines.append("# STUDIOS")
        lines.append("# ==============================\n")
        
        # Collect unique studios
        studios = set()
        
        for series in self.series_data.get('series', []):
            if series.get('studio'):
                studios.add(series['studio'])
        
        for short in self.shorts_data.get('shorts', []):
            if short.get('studio'):
                studios.add(short['studio'])
        
        for studio in sorted(studios):
            studio_id = self.sanitize_uri(studio)
            studio_uri = f"studio:{studio_id}"
            
            lines.append(f"{studio_uri} a ghibli:Studio ;")
            lines.append(f'    ghibli:name "{self.escape_literal(studio)}" .')
            lines.append("")
        
        return "\n".join(lines)
    
    def convert_all(self, output_file='ghibli-dataset.ttl'):
        """Convert semua data ke RDF Turtle"""
        print("\n Converting JSON files to RDF Turtle...")
        
        rdf_content = []
        
        # Add prefixes
        rdf_content.append(self.write_prefixes())
        
        # Add films
        films_rdf = self.convert_films()
        if films_rdf:
            rdf_content.append(films_rdf)
        
        # Add series
        series_rdf = self.convert_series()
        if series_rdf:
            rdf_content.append(series_rdf)
        
        # Add shorts
        shorts_rdf = self.convert_shorts()
        if shorts_rdf:
            rdf_content.append(shorts_rdf)
        
        # Add characters
        chars_rdf = self.convert_characters()
        if chars_rdf:
            rdf_content.append(chars_rdf)
        
        # Add directors
        directors_rdf = self.convert_directors()
        if directors_rdf:
            rdf_content.append(directors_rdf)
        
        # Add genres
        genres_rdf = self.convert_genres()
        if genres_rdf:
            rdf_content.append(genres_rdf)
        
        # Add studios
        studios_rdf = self.convert_studios()
        if studios_rdf:
            rdf_content.append(studios_rdf)
        
        # Write to file
        with open(output_file, 'w', encoding='utf-8') as f:
            f.write("\n".join(rdf_content))
        
        print(f" RDF dataset saved to {output_file}")
        
        # Print stats
        print("\n Conversion Summary:")
        print(f"  Films: {len(self.films_data.get('movies', []))}")
        print(f"  Series: {len(self.series_data.get('series', []))}")
        print(f"  Shorts: {len(self.shorts_data.get('shorts', []))}")
        print(f"  Total Characters: {len(self.films_data.get('characters', [])) + len(self.series_data.get('characters', []))}")
        print(f"  Directors: {len(self.films_data.get('directors', []))}")
        
        return output_file


# ============================================
# MAIN EXECUTION
# ============================================

if __name__ == "__main__":
    # Convert all JSON files to RDF
    converter = GhibliRDFConverter(
        films_json='data/films.json',      # Film data
        series_json='data/series.json',           # Series data
        shorts_json='data/shorts.json'            # Shorts data
    )
    
    converter.convert_all('ghibli-dataset.ttl')
    
    print("\n🎉 Conversion complete!")