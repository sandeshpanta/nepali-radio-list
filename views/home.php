<?php
/** @var list<array<string,mixed>> $stations */
/** @var list<array{name:string,count:int}> $provinces */
/** @var string $updated */
$e = static fn (?string $s): string => htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nepali Radio List</title>
    <meta name="description" content="Working live stream links for <?= count($stations) ?> Nepali radio stations, with a free JSON API.">
    <style>
        :root { --bg:#f6f7fb; --card:#fff; --text:#171a22; --muted:#667085; --line:#e4e7ec; --accent:#4f46e5; --accent-text:#fff; }
        @media (prefers-color-scheme: dark) {
            :root { --bg:#0e1015; --card:#171a22; --text:#eef0f6; --muted:#98a2b3; --line:#272b36; --accent:#818cf8; --accent-text:#0e1015; }
        }
        * { box-sizing: border-box; }
        body { margin:0; background:var(--bg); color:var(--text); font:16px/1.5 system-ui,-apple-system,"Segoe UI",sans-serif; padding-bottom:96px; }
        a { color:var(--accent); }
        .wrap { max-width:1080px; margin:0 auto; padding:0 16px; }
        header { padding:32px 0 8px; }
        h1 { margin:0 0 4px; font-size:1.8rem; }
        header p { margin:0; color:var(--muted); }
        .tools { display:flex; gap:12px; flex-wrap:wrap; margin:20px 0 12px; }
        input[type=search] { flex:1 1 240px; padding:10px 14px; border:1px solid var(--line); border-radius:10px; background:var(--card); color:var(--text); font:inherit; }
        .pills { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:20px; }
        .pill { border:1px solid var(--line); background:var(--card); color:var(--text); border-radius:999px; padding:6px 14px; font:inherit; font-size:.9rem; cursor:pointer; }
        .pill[aria-pressed=true] { background:var(--accent); color:var(--accent-text); border-color:var(--accent); }
        .pill small { opacity:.7; margin-left:4px; }
        .grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(250px,1fr)); gap:14px; list-style:none; margin:0; padding:0; }
        .card { background:var(--card); border:1px solid var(--line); border-radius:14px; padding:14px; display:flex; gap:12px; align-items:center; }
        .card img, .logo-ph { width:56px; height:56px; border-radius:50%; object-fit:cover; flex:none; background:var(--line); }
        .card .meta { min-width:0; flex:1; }
        .card h2 { margin:0; font-size:1rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .card p { margin:0; color:var(--muted); font-size:.85rem; }
        .play { flex:none; width:40px; height:40px; border-radius:50%; border:0; background:var(--accent); color:var(--accent-text); font-size:16px; cursor:pointer; display:grid; place-items:center; text-decoration:none; }
        .empty { text-align:center; color:var(--muted); padding:48px 0; }
        footer { color:var(--muted); font-size:.9rem; padding:32px 0; }
        code { background:var(--line); padding:2px 6px; border-radius:6px; font-size:.85em; }
        #player { position:fixed; left:0; right:0; bottom:0; background:var(--card); border-top:1px solid var(--line); padding:12px 16px; display:none; }
        #player.on { display:block; }
        #player .row { max-width:1080px; margin:0 auto; display:flex; gap:12px; align-items:center; }
        #player audio { flex:1; min-width:0; height:36px; }
        #now { font-weight:600; max-width:30%; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        #err { color:#d92d20; font-size:.85rem; }
        @media (max-width:600px) { #now { display:none; } }
    </style>
</head>
<body>
<div class="wrap">
    <header>
        <h1>Nepali Radio List</h1>
        <p><?= count($stations) ?> live stations, every stream checked on <?= $e($updated) ?>. Free JSON API: <a href="/api/radios"><code>/api/radios</code></a></p>
    </header>

    <div class="tools">
        <input type="search" id="q" placeholder="Search by name, place or frequency" aria-label="Search stations">
    </div>
    <div class="pills" id="pills">
        <button class="pill" data-p="" aria-pressed="true">All<small><?= count($stations) ?></small></button>
        <?php foreach ($provinces as $p): if ($p['count'] === 0) continue; ?>
            <button class="pill" data-p="<?= $e($p['name']) ?>" aria-pressed="false"><?= $e($p['name']) ?><small><?= $p['count'] ?></small></button>
        <?php endforeach; ?>
    </div>

    <ul class="grid" id="list">
        <?php foreach ($stations as $s): ?>
            <li class="card" data-province="<?= $e($s['province']) ?>"
                data-search="<?= $e(mb_strtolower($s['name'] . ' ' . ($s['address'] ?? '') . ' ' . ($s['frequency'] ?? ''))) ?>">
                <?php if ($s['logo']): ?>
                    <img src="/<?= $e($s['logo']) ?>" alt="" loading="lazy" width="56" height="56">
                <?php else: ?>
                    <span class="logo-ph"></span>
                <?php endif; ?>
                <div class="meta">
                    <h2 title="<?= $e($s['name']) ?>"><?= $e($s['name']) ?></h2>
                    <p><?= $e($s['province']) ?><?= $s['frequency'] ? ' · ' . $e((string) $s['frequency']) . ' MHz' : '' ?></p>
                </div>
                <button class="play" data-url="<?= $e($s['stream_url']) ?>" data-name="<?= $e($s['name']) ?>" aria-label="Play <?= $e($s['name']) ?>">▶</button>
            </li>
        <?php endforeach; ?>
    </ul>
    <p class="empty" id="empty" hidden>No station matches your search.</p>

    <footer>
        <p>API: <code>GET /api/radios?province=Bagmati&amp;q=fm</code> · <code>/api/radios/{id}</code> · <code>/api/provinces</code>.
           Station names and logos belong to their owners.</p>
    </footer>
</div>

<div id="player">
    <div class="row">
        <span id="now"></span>
        <audio id="audio" controls></audio>
        <span id="err"></span>
    </div>
</div>

<script>
(function () {
    var list = document.getElementById('list'), cards = [].slice.call(list.children),
        q = document.getElementById('q'), empty = document.getElementById('empty'),
        audio = document.getElementById('audio'), player = document.getElementById('player'),
        now = document.getElementById('now'), err = document.getElementById('err'), province = '';

    function filter() {
        var term = q.value.trim().toLowerCase(), shown = 0;
        cards.forEach(function (c) {
            var ok = (!province || c.dataset.province === province) && (!term || c.dataset.search.indexOf(term) > -1);
            c.hidden = !ok;
            if (ok) shown++;
        });
        empty.hidden = shown > 0;
    }
    q.addEventListener('input', filter);
    document.getElementById('pills').addEventListener('click', function (ev) {
        var b = ev.target.closest('.pill'); if (!b) return;
        province = b.dataset.p;
        [].forEach.call(document.querySelectorAll('.pill'), function (p) { p.setAttribute('aria-pressed', p === b); });
        filter();
    });

    // A page served over https cannot play plain http streams: turn those buttons into "open in a new tab" links.
    if (location.protocol === 'https:') {
        [].forEach.call(document.querySelectorAll('.play'), function (b) {
            if (b.dataset.url.indexOf('http://') !== 0) return;
            var a = document.createElement('a');
            a.className = 'play'; a.href = b.dataset.url; a.target = '_blank'; a.rel = 'noopener';
            a.textContent = '↗'; a.title = 'Open the stream in a new tab';
            b.replaceWith(a);
        });
    }

    list.addEventListener('click', function (ev) {
        var b = ev.target.closest('button.play'); if (!b) return;
        err.textContent = '';
        now.textContent = b.dataset.name;
        player.classList.add('on');
        audio.src = b.dataset.url;
        audio.play().catch(function () { err.textContent = 'Could not play this stream.'; });
    });
    audio.addEventListener('error', function () { err.textContent = 'Could not play this stream.'; });
})();
</script>
</body>
</html>
