import os
import requests
from bs4 import BeautifulSoup
from urllib.parse import urljoin
import json

BASE_URL = "https://hkfm903.live/"

def get_show_recordings(show_name):
    """
    Scrapes the website for recordings of a specific show and returns a list of metadata.
    """
    params = {"show": show_name}
    try:
        response = requests.get(BASE_URL, params=params, timeout=10)
        response.raise_for_status()
    except Exception as e:
        print(f"Failed to fetch page: {e}")
        return []

    soup = BeautifulSoup(response.text, "html.parser")
    recordings = []

    cards = soup.select("div.recording-card")
    for card in cards:
        title_tag = card.select_one("h5.card-title")
        if not title_tag:
            continue
        
        # Clean title
        title = title_tag.get_text(strip=True)
             
        # Find the source URL
        source_tag = card.select_one("audio.audio-player source")
        if source_tag and source_tag.get("src"):
            recordings.append({
                "title": title,
                "url": urljoin(BASE_URL, source_tag.get("src"))
            })

    return recordings

def update_recordings(show_names=None):
    """
    Updates the local recordings.json file and returns the recordings.
    """
    if show_names is None:
        show_names = ["Bad Girl大過佬", "在晴朗的一天出發", "聖艾粒LaLaLaLa"]
        
    all_recs = []
    for show_name in show_names:
        recs = get_show_recordings(show_name)
        if recs:
            all_recs.extend(recs)
            
    if all_recs:
        with open("recordings.json", "w", encoding="utf-8") as f:
            json.dump(all_recs, f, indent=4, ensure_ascii=False)
            
    return all_recs

if __name__ == "__main__":
    shows = ["Bad Girl大過佬", "在晴朗的一天出發", "聖艾粒LaLaLaLa"]
    print(f"Scraping remote URLs for {len(shows)} shows...")
    recs = update_recordings(shows)
    if recs:
        print(f"Successfully updated recordings.json with {len(recs)} items.")
    else:
        print("No recordings found or update failed.")
