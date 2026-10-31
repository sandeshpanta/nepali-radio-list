# Nepali Radio List

A checked list of **257 live Nepali radio stations** (stream URL, province, frequency, logo), a small
**PHP website** to browse and play them, and a free **JSON API**.

Every stream in `data/radios.json` was tested on 2026-10-31: it answered and really delivered audio.
Dead links from the original list (about 300) were removed.

## Run it

Needs PHP 8.1 or newer. No Composer, no database.

```bash
php -S localhost:8000 -t public public/index.php
```

Open <http://localhost:8000>. On Apache or shared hosting, point the document root at `public/`
(the included `.htaccess` does the routing).

## API

All endpoints are `GET`, return JSON and allow cross-origin requests.

| Endpoint | What it returns |
|---|---|
| `/api/radios` | stations; filters: `province`, `q` (name, place, frequency), `online` (`all` or `0` to include offline ones), `limit` (max 500), `offset` |
| `/api/radios/{id}` | one station |
| `/api/provinces` | the 8 provinces with their station counts |

```bash
curl "http://localhost:8000/api/radios?province=Gandaki&q=fm&limit=3"
```

```json
{
  "data": [
    {
      "id": "radio-kantipur",
      "name": "Radio Kantipur",
      "province": "Bagmati",
      "frequency": 96.1,
      "address": "Subidhanagar,Tinkune, Kathmandu, Nepal",
      "stream_url": "https://radio-broadcast.ekantipur.com/stream",
      "online": true,
      "checked_at": "2026-10-31",
      "logo_url": "http://localhost:8000/logos/radio-kantipur.jpg"
    }
  ],
  "meta": { "total": 1, "limit": 500, "offset": 0, "updated": "2026-10-31" }
}
```

Provinces: Koshi, Madhesh, Bagmati, Gandaki, Lumbini, Karnali, Sudurpashchim, and **World** for stations
outside Nepal or without a known location.

No server? `data/radios.json` is plain JSON, so you can read it straight from GitHub's raw URL.
The `logo` field in it is a path relative to `public/` (for example `logos/radio-kantipur.jpg`).

## Keeping the list fresh

Streams go offline over time. Re-check them all (needs Python 3.9+ and `curl`):

```bash
python tools/check_streams.py            # marks every station online / offline and stamps the date
python tools/check_streams.py --prune    # also removes offline ones (saved in data/offline.json)
```

Run the PHP checks with `php tests/smoke.php`.

## Layout

```
data/radios.json      the stations (edit this to add or fix one)
public/               web root: index.php, logos/
src/                  RadioRepository (reads the data) and Api
views/home.php        the website
tools/check_streams.py
tests/smoke.php
```

## Adding a station

Add an object to `data/radios.json` with a unique `id` (lowercase, digits, dashes), `name`, one of the
provinces above, `stream_url`, and optionally `frequency`, `address` and a logo under `public/logos/`.
Then run `python tools/check_streams.py` and `php tests/smoke.php`.

## Notes

- Streams that start with `http://` cannot be played inside an `https://` page by the browser. The website
  shows an "open in new tab" link for those when it is served over HTTPS.
- Station names and logos belong to their owners; they are included only to identify the stations.
- The code is MIT-licensed (see `LICENSE`).
