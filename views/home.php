<?php
/** @var list<array<string,mixed>> $stations */
/** @var list<array{name:string,count:int}> $provinces */
/** @var string $updated */
/** @var string $baseUrl  e.g. http://localhost:8000 */
$e = static fn (?string $s): string => htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$api = $baseUrl . '/api/radios';
$exJs = "const res = await fetch('{$api}?province=Bagmati');\nconst { data, meta } = await res.json();\nconsole.log(meta.total, data[0].name, data[0].stream_url);";
$exPy = "import requests\nr = requests.get('{$api}', params={'province': 'Gandaki', 'q': 'fm'}).json()\nfor s in r['data']:\n    print(s['name'], s['stream_url'])";
$exPhp = "\$json = json_decode(file_get_contents('{$api}?limit=5'), true);\nforeach (\$json['data'] as \$s) {\n    echo \$s['name'], ' - ', \$s['stream_url'], PHP_EOL;\n}";
$exCurl = "curl \"{$api}?province=Karnali\"";
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nepali Radio List</title>
    <meta name="description" content="Working live stream links for <?= count($stations) ?> Nepali radio stations, with a free JSON API.">
    <style>
        :root { --bg:#f6f7fb; --card:#fff; --text:#171a22; --muted:#667085; --line:#e4e7ec; --accent:#4f46e5; --accent-text:#fff; --code:#eef0f6; }
        @media (prefers-color-scheme: dark) {
            :root { --bg:#0e1015; --card:#171a22; --text:#eef0f6; --muted:#98a2b3; --line:#272b36; --accent:#818cf8; --accent-text:#0e1015; --code:#1f232d; }
        }
        * { box-sizing: border-box; }
        body { margin:0; background:var(--bg); color:var(--text); font:16px/1.5 system-ui,-apple-system,"Segoe UI",sans-serif; padding-bottom:96px; }
        a { color:var(--accent); }
        .wrap { max-width:1080px; margin:0 auto; padding:0 16px; }
        header { padding:28px 0 4px; }
        h1 { margin:0 0 4px; font-size:1.8rem; }
        header p { margin:0; color:var(--muted); }
        nav { display:flex; gap:8px; margin:18px 0; border-bottom:1px solid var(--line); }
        nav button { border:0; background:none; color:var(--muted); font:inherit; font-weight:600; padding:10px 14px; cursor:pointer; border-bottom:3px solid transparent; margin-bottom:-1px; }
        nav button[aria-selected=true] { color:var(--accent); border-color:var(--accent); }
        input[type=search] { width:100%; padding:10px 14px; border:1px solid var(--line); border-radius:10px; background:var(--card); color:var(--text); font:inherit; }
        .pills { display:flex; gap:8px; flex-wrap:wrap; margin:12px 0 20px; }
        .pill { border:1px solid var(--line); background:var(--card); color:var(--text); border-radius:999px; padding:6px 14px; font:inherit; font-size:.9rem; cursor:pointer; }
        .pill[aria-pressed=true] { background:var(--accent); color:var(--accent-text); border-color:var(--accent); }
        .pill small { opacity:.7; margin-left:4px; }
        .grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(250px,1fr)); gap:14px; list-style:none; margin:0; padding:0; }
        .card { background:var(--card); border:1px solid var(--line); border-radius:14px; padding:14px; display:flex; gap:12px; align-items:center; }
        .card.playing { border-color:var(--accent); box-shadow:0 0 0 1px var(--accent); }
        .card img, .logo-ph { width:56px; height:56px; border-radius:50%; object-fit:cover; flex:none; background:var(--line); }
        .card .meta { min-width:0; flex:1; }
        .card h2 { margin:0; font-size:1rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .card p { margin:0; color:var(--muted); font-size:.85rem; }
        .play { flex:none; width:40px; height:40px; border-radius:50%; border:0; background:var(--accent); color:var(--accent-text); font-size:16px; cursor:pointer; display:grid; place-items:center; text-decoration:none; }
        .empty { text-align:center; color:var(--muted); padding:48px 0; }
        .note { background:var(--card); border:1px solid var(--line); border-radius:10px; padding:10px 14px; color:var(--muted); font-size:.9rem; margin-bottom:16px; }
        section.panel { background:var(--card); border:1px solid var(--line); border-radius:14px; padding:4px 20px 20px; margin-bottom:16px; }
        h3 { margin:20px 0 8px; font-size:1.05rem; }
        pre { background:var(--code); border-radius:10px; padding:12px 14px; overflow:auto; font:13px/1.5 ui-monospace,Consolas,monospace; margin:8px 0; }
        code { background:var(--code); padding:2px 6px; border-radius:6px; font-size:.88em; }
        pre code { background:none; padding:0; }
        .btn { border:1px solid var(--line); background:var(--card); color:var(--text); border-radius:8px; padding:5px 12px; font:inherit; font-size:.88rem; cursor:pointer; text-decoration:none; display:inline-block; }
        .urlrow { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
        .urlrow code { flex:1; min-width:0; overflow:auto; white-space:nowrap; padding:8px 10px; }
        table { border-collapse:collapse; width:100%; font-size:.92rem; }
        td, th { text-align:left; padding:6px 10px 6px 0; border-bottom:1px solid var(--line); vertical-align:top; }
        footer { color:var(--muted); font-size:.9rem; padding:24px 0; }
        #player { position:fixed; left:0; right:0; bottom:0; background:var(--card); border-top:1px solid var(--line); padding:12px 16px; display:none; }
        #player.on { display:block; }
        #player .row { max-width:1080px; margin:0 auto; display:flex; gap:12px; align-items:center; }
        #player audio { flex:1; min-width:0; height:36px; }
        #now { font-weight:600; max-width:30%; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        #err { color:#d92d20; font-size:.85rem; }
        [hidden] { display:none !important; }
        @media (max-width:600px) { #now { display:none; } }
    </style>
</head>
<body>
<div class="wrap">
    <header>
        <h1>Nepali Radio List</h1>
        <p><?= count($stations) ?> live stations, streams checked on <?= $e($updated) ?>.</p>
    </header>

    <nav role="tablist">
        <button role="tab" id="tab-listen" aria-selected="true">Listen</button>
        <button role="tab" id="tab-api" aria-selected="false">API / Data</button>
    </nav>

    <!-- Listen -->
    <div id="view-listen">
        <input type="search" id="q" placeholder="Search by name, place or frequency" aria-label="Search stations">
        <div class="pills" id="pills">
            <button class="pill" data-p="" aria-pressed="true">All<small><?= count($stations) ?></small></button>
            <?php foreach ($provinces as $p): if ($p['count'] === 0) continue; ?>
                <button class="pill" data-p="<?= $e($p['name']) ?>" aria-pressed="false"><?= $e($p['name']) ?><small><?= $p['count'] ?></small></button>
            <?php endforeach; ?>
        </div>
        <div class="note" id="httpsNote" hidden>This page is on https, so browsers block plain <code>http://</code> streams. Those stations show a ↗ button that opens the stream in a new tab instead.</div>

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
    </div>

    <!-- API -->
    <div id="view-api" hidden>
        <section class="panel">
            <h3>Get the data</h3>
            <p>No key, no sign-up. Every endpoint is a <code>GET</code> that returns JSON and allows cross-origin requests.</p>
            <div class="urlrow"><code id="apiUrl"><?= $e($api) ?></code><button class="btn" data-copy="apiUrl">Copy</button></div>
            <p>
                <a class="btn" href="/api/radios" download="nepali-radios.json">Download JSON</a>
                <a class="btn" href="/api/radios.csv">Download CSV</a>
            </p>
            <table>
                <tr><th>Endpoint</th><th>Returns</th></tr>
                <tr><td><code>/api/radios</code></td><td>stations. Filters: <code>?province=Bagmati</code> <code>&amp;q=kantipur</code> <code>&amp;limit=50</code> <code>&amp;offset=0</code> <code>&amp;online=all</code></td></tr>
                <tr><td><code>/api/radios/{id}</code></td><td>one station, e.g. <code>/api/radios/radio-kantipur</code></td></tr>
                <tr><td><code>/api/provinces</code></td><td>the 8 provinces with their station counts</td></tr>
                <tr><td><code>/api/radios.csv</code></td><td>the whole list as a spreadsheet file</td></tr>
            </table>
            <p style="color:var(--muted);font-size:.9rem">Provinces: Koshi, Madhesh, Bagmati, Gandaki, Lumbini, Karnali, Sudurpashchim, World (outside Nepal or unknown).</p>
        </section>

        <section class="panel">
            <h3>Use it in your code</h3>
            <p>JavaScript</p><pre><code><?= $e($exJs) ?></code></pre>
            <p>Python</p><pre><code><?= $e($exPy) ?></code></pre>
            <p>PHP</p><pre><code><?= $e($exPhp) ?></code></pre>
            <p>curl</p><pre><code><?= $e($exCurl) ?></code></pre>
        </section>
    </div>

    <footer>Station names and logos belong to their owners. Streams can go offline; <code>python tools/check_streams.py</code> re-checks them all.</footer>
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
    var $ = function (id) { return document.getElementById(id); };
    var list = $('list'), cards = [].slice.call(list.children), q = $('q'), audio = $('audio'),
        province = '', playing = null;

    // tabs
    function show(tab) {
        $('view-listen').hidden = tab !== 'listen';
        $('view-api').hidden = tab !== 'api';
        $('tab-listen').setAttribute('aria-selected', tab === 'listen');
        $('tab-api').setAttribute('aria-selected', tab === 'api');
        history.replaceState(null, '', '#' + tab);
    }
    $('tab-listen').onclick = function () { show('listen'); };
    $('tab-api').onclick = function () { show('api'); };
    show(location.hash === '#api' ? 'api' : 'listen');

    document.addEventListener('click', function (ev) {
        var b = ev.target.closest('[data-copy]'); if (!b) return;
        var text = $(b.dataset.copy).textContent;
        (navigator.clipboard ? navigator.clipboard.writeText(text) : Promise.reject()).then(function () {
            b.textContent = 'Copied'; setTimeout(function () { b.textContent = 'Copy'; }, 1500);
        }, function () { window.prompt('Copy this:', text); });
    });

    // search + province filter
    function filter() {
        var term = q.value.trim().toLowerCase(), shown = 0;
        cards.forEach(function (c) {
            var ok = (!province || c.dataset.province === province) && (!term || c.dataset.search.indexOf(term) > -1);
            c.hidden = !ok;
            if (ok) shown++;
        });
        $('empty').hidden = shown > 0;
    }
    q.addEventListener('input', filter);
    $('pills').addEventListener('click', function (ev) {
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
            $('httpsNote').hidden = false;
        });
    }

    // player
    list.addEventListener('click', function (ev) {
        var b = ev.target.closest('button.play'); if (!b) return;
        $('err').textContent = '';
        $('now').textContent = b.dataset.name;
        $('player').classList.add('on');
        if (playing) playing.classList.remove('playing');
        playing = b.closest('.card'); playing.classList.add('playing');
        audio.src = b.dataset.url;
        audio.play().catch(function () { $('err').textContent = 'Could not play this stream.'; });
    });
    audio.addEventListener('error', function () { $('err').textContent = 'Could not play this stream.'; });
})();
</script>
</body>
</html>
