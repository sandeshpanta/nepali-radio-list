# Nepali Radio List

A checked list of **235 live Nepali radio stations** (stream URL, province, frequency, logo), a small
**PHP website** to browse and play them, and a free **JSON API**.

Every stream in `data/radios.json` was tested on 2026-10-31: it answered and really delivered audio.
About 300 dead links from the original list were removed; 12 that went offline on the day of the final
check are kept in `data/offline.json`.

## Listen online (no install)

Once GitHub Pages is on (Settings -> Pages -> Deploy from branch -> `main` / root), the static `index.html` is a public page:
**https://sandeshpanta.github.io/nepali-radio-list/**. It lists every station with a play button and has an *API / Data* tab
for the JSON/CSV links. Plain `http://` streams cannot play inside an `https://` page; see
[Playing http streams on an https page](#playing-http-streams-on-an-https-page-the-relay) for how to make them play too.
To open it locally use `python -m http.server` (not a double-click on the file).

## Quick start (PHP)

You need PHP 8.1 or newer (`php --version` shows it). No Composer, no database.

```bash
git clone https://github.com/sandeshpanta/nepali-radio-list.git
cd nepali-radio-list
php -S localhost:8000 -t public public/index.php
```

Open <http://localhost:8000>. On Windows you can double-click `serve.bat` instead.

The page has two tabs: **Listen** (search, province filter, play button) and **API / Data** (links,
downloads and code examples).

## API

All endpoints are `GET`, return JSON and allow cross-origin requests.

| Endpoint | What it returns |
|---|---|
| `/api/radios` | stations; filters: `province`, `q` (name, place, frequency), `limit` (max 500), `offset`, `online=all` (include offline ones) |
| `/api/radios/{id}` | one station |
| `/api/provinces` | the 8 provinces with their station counts |
| `/api/radios.csv` | the whole list as a CSV file |

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

`data/radios.json` is also plain JSON you can read straight from GitHub's raw URL. The `logo` field in it
is a path relative to `public/` (for example `logos/radio-kantipur.jpg`).

## Put it online

It is an ordinary PHP site, so any PHP host works (GitHub Pages cannot run PHP, which is why `index.html` exists).

1. Upload the whole project.
2. Make `public/` the document root. On Apache the included `.htaccess` does the routing; on nginx send
   every request that is not a real file to `public/index.php`.
3. Serve it over `https` if you can. The website then plays plain `http://` stations through its own relay
   (see below), so every station works.

## Playing http streams on an https page (the relay)

Browsers refuse to play `http://` audio inside an `https://` page, and about 60% of the stations only stream over
plain http. The PHP app therefore has a relay: `https://your-server/relay/{station-id}` fetches the real stream and
passes the audio on. Stations that already use https are redirected to their own address and never touch the relay.

* **On your own PHP site** nothing to do. When the page is served over https it uses `/relay/{id}` for http stations.
* **For the GitHub Pages page** deploy the PHP app on any https server, then open `index.html` and set the address at
  the top of its script: `var RELAY_BASE = 'https://radio.example.com';` and push. The ▶ button then works for
  every station instead of showing ↗.

How it protects the server:

* only stations listed in `data/radios.json` can be relayed (an address in the request is never fetched);
* addresses on private networks and mail/database/SSH ports are refused, redirects are re-checked at every hop;
* at most `RADIO_RELAY_MAX` listeners at once (default 20), each cut after `RADIO_RELAY_MAX_MINUTES` (default 720),
  and a station that stops sending data is dropped after 15 seconds;
* set `RADIO_RELAY=off` to switch the relay off.

Things to know: every listener keeps one PHP worker busy, so size `pm.max_children` (php-fpm) or Apache workers for
the number of listeners you expect, and expect the server's bandwidth to carry the audio (about 16 KB/s per listener).
On nginx add `fastcgi_buffering off;` for the PHP location so audio is not held back. PHP's built-in `php -S` server
(Windows) serves one request at a time, so only one listener at once is possible there; use Apache/nginx for real use.
PHP needs the `curl` extension.

## Keeping the list fresh

Streams go offline over time. Re-check them all (needs Python 3.9+ and `curl`):

```bash
python tools/check_streams.py            # marks every station online / offline and stamps the date
python tools/check_streams.py --prune    # also removes offline ones (saved in data/offline.json)
```

Run the PHP checks with `php tests/smoke.php`.

## Layout

```
index.html            static page for GitHub Pages (listen + data links)
data/radios.json      the stations (edit this to add or fix one)
data/offline.json     stations that were offline at the last check
public/               web root: index.php, logos/
src/                  RadioRepository (reads the data), Api and Relay (https relay)
views/home.php        the website (Listen + API tabs)
tools/check_streams.py
tests/smoke.php
serve.bat             Windows shortcut for the quick start
```

## Adding a station

Add an object to `data/radios.json` with a unique `id` (lowercase, digits, dashes), `name`, one of the
provinces above, `stream_url`, and optionally `frequency`, `address` and a logo under `public/logos/`.
Then run `python tools/check_streams.py` and `php tests/smoke.php`.

## Notes

- Shoutcast servers send an HTML status page to browsers when you open the bare `http://host:port` URL.
  Where that happened the stream URL ends with `/;`, which makes the server send audio to browsers too.
- Station names and logos belong to their owners; they are included only to identify the stations.
- The code is MIT-licensed (see `LICENSE`).
