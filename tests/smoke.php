<?php

declare(strict_types=1);

// Dependency-free checks:  php tests/smoke.php

require __DIR__ . '/../src/RadioRepository.php';
require __DIR__ . '/../src/Api.php';
require __DIR__ . '/../src/Relay.php';

use NepaliRadio\Api;
use NepaliRadio\RadioRepository;

$failures = 0;
$check = static function (string $what, bool $ok) use (&$failures): void {
    echo ($ok ? "  ok   " : "  FAIL ") . $what . PHP_EOL;
    $failures += $ok ? 0 : 1;
};

$radios = new RadioRepository(__DIR__ . '/../data/radios.json');
$api = new Api($radios, 'http://localhost');
$stations = $radios->all();

$check('data has stations', count($stations) > 100);
$check('ids are unique', count(array_unique(array_column($stations, 'id'))) === count($stations));
$check('every province is known', array_diff(array_column($stations, 'province'), RadioRepository::PROVINCES) === []);
$check('every stream url is http(s)', count(array_filter($stations, fn ($s) => ! preg_match('#^https?://\S+$#', $s['stream_url']))) === 0);
$check('every logo file exists', count(array_filter($stations, fn ($s) => $s['logo'] && ! is_file(__DIR__ . '/../public/' . $s['logo']))) === 0);

[$status, $body] = $api->handle('/api/radios', ['province' => 'bagmati', 'limit' => '5']);
$check('province filter is case-insensitive', $status === 200 && count($body['data']) === 5 && $body['data'][0]['province'] === 'Bagmati');
$check('logo_url is absolute', str_starts_with((string) $body['data'][0]['logo_url'], 'http://localhost/logos/'));

[$status, $body] = $api->handle('/api/radios', ['q' => 'kantipur']);
$check('search finds Radio Kantipur', $status === 200 && $body['meta']['total'] >= 1);

[$status, $body] = $api->handle('/api/radios/' . $stations[0]['id'], []);
$check('single station by id', $status === 200 && $body['data']['id'] === $stations[0]['id']);

[$status] = $api->handle('/api/radios/does-not-exist', []);
$check('unknown id is 404', $status === 404);

[$status, $body] = $api->handle('/api/provinces', []);
$check('provinces lists all 8', $status === 200 && count($body['data']) === 8);

$check('relay refuses private addresses', ! \NepaliRadio\Relay::inspect('http://192.168.1.5:8000/x')['ok'] && ! \NepaliRadio\Relay::inspect('http://127.0.0.1/x')['ok']);
$check('relay refuses mail/database ports', ! \NepaliRadio\Relay::inspect('http://8.8.8.8:25/')['ok'] && ! \NepaliRadio\Relay::inspect('http://8.8.8.8:3306/')['ok']);
$check('relay refuses non-http schemes', ! \NepaliRadio\Relay::inspect('file:///etc/passwd')['ok'] && ! \NepaliRadio\Relay::inspect('ftp://8.8.8.8/')['ok']);
$check('relay accepts a public address', \NepaliRadio\Relay::inspect('http://8.8.8.8:8000/stream')['ok']);

$csv = $api->csv();
$check('csv has a header and one line per station', substr_count($csv, "\n") === count($stations) + 1 && str_contains($csv, 'stream_url'));

echo $failures === 0 ? "All good.\n" : "{$failures} check(s) failed.\n";
exit($failures === 0 ? 0 : 1);
