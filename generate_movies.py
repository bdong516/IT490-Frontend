import requests
import json
import time

TMDB_API_KEY = "df14c5d3032038a72528de3f82906e18"

def fetch_list(endpoint, pages=200):
    print(f"Fetching from {endpoint} ...")
    entries = {}

    for page in range(1, pages + 1):
        url = f"https://api.themoviedb.org/3/{endpoint}?api_key={TMDB_API_KEY}&page={page}"
        res = requests.get(url).json()

        if "results" not in res:
            break

        for movie in res["results"]:
            # English only
            if movie.get("original_language") != "en":
                continue

            movie_id = movie.get("id")
            title = movie.get("title") or movie.get("name")

            if movie_id and title:
                entries[movie_id] = title.strip()

        print(f"Page {page} processed ({len(entries)} total)...")
        time.sleep(0.25)  # avoid TMDB rate limits

    return entries


all_movies = {}

# Popular movies
all_movies.update(fetch_list("movie/popular", pages=200))

# Top rated movies
all_movies.update(fetch_list("movie/top_rated", pages=200))

# Trending daily + weekly
all_movies.update(fetch_list("trending/movie/day", pages=50))
all_movies.update(fetch_list("trending/movie/week", pages=50))

# Convert to list of objects { id, title }
movie_list = [{"id": mid, "title": title} for mid, title in all_movies.items()]

# Sort alphabetically
movie_list = sorted(movie_list, key=lambda x: x["title"])

print(f"\n Total English-only unique movies: {len(movie_list)}")
print("Writing movies.json ...")

with open("movies.json", "w", encoding="utf-8") as f:
    json.dump(movie_list, f, ensure_ascii=False, indent=2)

print("Done! movies.json generated.")
