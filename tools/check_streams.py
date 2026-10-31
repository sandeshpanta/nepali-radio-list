#!/usr/bin/env python3
"""Check every stream in data/radios.json and record the result in the file.

    python tools/check_streams.py            # check all stations, update online / checked_at
    python tools/check_streams.py --prune    # also delete stations that are offline (kept in data/offline.json)

A stream counts as working when it answers with HTTP 2xx and really delivers audio bytes (or an HLS
playlist) within a few seconds. Needs only Python 3.9+ and `curl` on the PATH.
"""
import argparse
import json
import os
import shutil
import subprocess
import tempfile
from concurrent.futures import ThreadPoolExecutor
from datetime import date

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DATA = os.path.join(ROOT, "data", "radios.json")
OFFLINE = os.path.join(ROOT, "data", "offline.json")

LISTEN_SECONDS = 10
MIN_BYTES = 8 * 1024
# Shoutcast servers answer browser-like user agents with an HTML status page, so try a player UA first.
USER_AGENTS = ["VLC/3.0.20 LibVLC/3.0.20", "Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/124.0 Safari/537.36",
               "VLC/3.0.20 LibVLC/3.0.20"]


def sniff(head: bytes) -> str:
    low = head.lstrip()[:200].lower()
    if low.startswith(b"#extm3u"):
        return "hls"
    if low.startswith((b"<!doctype", b"<html", b"<?xml", b"<head", b"<body", b"{", b"<!--")) or b"<html" in low:
        return "html"
    return "audio" if head else "empty"


def check(url: str, user_agent: str) -> bool:
    curl = shutil.which("curl") or "curl"
    fd, body = tempfile.mkstemp(prefix="stream_")
    os.close(fd)
    try:
        proc = subprocess.run(
            [curl, "-sS", "-L", "--max-redirs", "5", "-k", "--connect-timeout", "8", "-m", str(LISTEN_SECONDS),
             "-A", user_agent, "-H", "Icy-MetaData: 0", "-o", body, "-w", "%{http_code}|%{size_download}", url],
            capture_output=True, text=True, timeout=LISTEN_SECONDS + 15)
        code, _, size = proc.stdout.strip().partition("|")
        # curl exits 28 when our time limit ends a live stream, which is the normal case
        if not code.isdigit() or not 200 <= int(code) < 300:
            return False
        with open(body, "rb") as f:
            kind = sniff(f.read(512))
        if kind == "html":
            return False
        return kind == "hls" or int(float(size or 0)) >= MIN_BYTES
    except (subprocess.SubprocessError, OSError, ValueError):
        return False
    finally:
        try:
            os.remove(body)
        except OSError:
            pass


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument("--prune", action="store_true", help="remove offline stations (saved to data/offline.json)")
    parser.add_argument("--workers", type=int, default=40)
    args = parser.parse_args()

    with open(DATA, encoding="utf-8") as f:
        doc = json.load(f)
    stations = doc["stations"]
    online = {}  # stream_url -> bool
    urls = sorted({s["stream_url"] for s in stations})

    for ua in USER_AGENTS:  # later rounds retry only what failed
        todo = [u for u in urls if not online.get(u)]
        print(f"checking {len(todo)} stream(s) as {ua.split('/')[0]} ...", flush=True)
        with ThreadPoolExecutor(args.workers) as pool:
            for u, ok in zip(todo, pool.map(lambda x: check(x, ua), todo)):
                online[u] = ok

    today = date.today().isoformat()
    for s in stations:
        s["online"] = online[s["stream_url"]]
        s["checked_at"] = today
    doc["updated"] = today

    gone = [s for s in stations if not s["online"]]
    if args.prune and gone:
        doc["stations"] = [s for s in stations if s["online"]]
        with open(OFFLINE, "w", encoding="utf-8") as f:
            json.dump({"updated": today, "stations": gone}, f, ensure_ascii=False, indent=2)
            f.write("\n")

    with open(DATA, "w", encoding="utf-8") as f:
        json.dump(doc, f, ensure_ascii=False, indent=2)
        f.write("\n")
    print(f"{len(stations) - len(gone)} online, {len(gone)} offline" + (" (pruned)" if args.prune else ""))
    for s in gone:
        print("  offline:", s["name"], "-", s["stream_url"])


if __name__ == "__main__":
    main()
