#!/usr/bin/env python3
"""Download the location database photos from Google Drive and self-host them.

Needs network access to drive.google.com, drive.usercontent.google.com and
lh3.googleusercontent.com, plus Pillow (pip install pillow pillow-heif).

    python3 tools/fetch_location_photos.py

For every photo ID in locations/index.html (the "loc-data" JSON) it saves
  assets/img/locations/<location-id>/<n>.jpg      1600px wide, the photo window
  assets/img/locations/<location-id>/<n>-s.jpg     640px wide, cards and thumbnails
and, the first time, switches js/locations.js to the local files. Photos that
are already there are skipped, so a re-run fetches only new ones.
"""
import io, json, os, re, sys, time, urllib.request

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
page = open(os.path.join(ROOT, "locations/index.html"), encoding="utf-8").read()
data = json.loads(re.search(r'id="loc-data">(.*?)</script>', page, re.S).group(1).replace("<\\/", "</"))

from PIL import Image, ImageOps
try:
    import pillow_heif; pillow_heif.register_heif_opener()
except ImportError:
    pass

def fetch(fid):
    for url in (f"https://drive.google.com/thumbnail?id={fid}&sz=w2000",
                f"https://drive.usercontent.google.com/download?id={fid}&export=download&confirm=t"):
        try:
            with urllib.request.urlopen(urllib.request.Request(url, headers={"User-Agent": "Mozilla/5.0"}), timeout=60) as r:
                body = r.read()
            return Image.open(io.BytesIO(body))
        except Exception as e:
            err = e
    raise err

def save(im, path, w, q):
    im = ImageOps.exif_transpose(im).convert("RGB")
    if im.width > w:
        im = im.resize((w, round(im.height * w / im.width)), Image.LANCZOS)
    im.save(path, "JPEG", quality=q, optimize=True, progressive=True)

failed = []
for loc in data["locations"]:
    d = os.path.join(ROOT, "assets/img/locations", loc["id"])
    os.makedirs(d, exist_ok=True)
    for n, fid in enumerate(loc["photos"], 1):
        big, small = os.path.join(d, f"{n}.jpg"), os.path.join(d, f"{n}-s.jpg")
        if os.path.exists(big) and os.path.exists(small):
            continue
        try:
            im = fetch(fid)
            save(im, big, 1600, 80)
            save(im, small, 640, 76)
            print("ok ", loc["id"], n)
        except Exception as e:
            failed.append((loc["id"], n, fid, str(e)))
            print("ERR", loc["id"], n, fid, e)
        time.sleep(0.2)

if failed:
    sys.exit(f"{len(failed)} photos failed — js/locations.js left on Google Drive. Re-run to retry.")

js_path = os.path.join(ROOT, "js/locations.js")
js = open(js_path, encoding="utf-8").read()
old = 'function img(id, w) { return "https://drive.google.com/thumbnail?id=" + encodeURIComponent(id) + "&sz=w" + w; }'
new = ('var PHOTO = {};\n  LOCS.forEach(function (l) { l.photos.forEach(function (p, i) { PHOTO[p] = l.id + "/" + (i + 1); }); });\n'
       '  function img(id, w) { return "/assets/img/locations/" + PHOTO[id] + (w > 700 ? "" : "-s") + ".jpg"; }')
if old in js:
    open(js_path, "w", encoding="utf-8").write(js.replace(old, new))
    print("js/locations.js now uses the local photos")
