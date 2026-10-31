<?php

declare(strict_types=1);

// Front controller. Run locally with:  php -S localhost:8000 -t public public/index.php

require __DIR__ . '/../src/RadioRepository.php';
require __DIR__ . '/../src/Api.php';

use NepaliRadio\Api;
use NepaliRadio\RadioRepository;

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

// Let PHP's built-in server hand out real files (logos, css) itself.
if (PHP_SAPI === 'cli-server' && $path !== '/' && is_file(__DIR__ . $path)) {
    return false;
}

$https = ($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off'
    || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
$baseUrl = ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

try {
    $radios = new RadioRepository(__DIR__ . '/../data/radios.json');
} catch (RuntimeException $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'The radio list is unavailable.']);

    return;
}

if (str_starts_with($path, '/api')) {
    header('Access-Control-Allow-Origin: *');
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: public, max-age=300');

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
        header('Access-Control-Allow-Methods: GET, OPTIONS');
        http_response_code(204);

        return;
    }
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Only GET is supported.']);

        return;
    }

    if ($path === '/api/radios.csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="nepali-radios.csv"');
        echo (new Api($radios, $baseUrl))->csv();

        return;
    }

    [$status, $body] = (new Api($radios, $baseUrl))->handle($path, $_GET);
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

    return;
}

if ($path === '/') {
    $stations = $radios->all(['online' => true]);
    $provinces = $radios->provinces();
    $updated = $radios->updated();
    require __DIR__ . '/../views/home.php';

    return;
}

http_response_code(404);
echo 'Not found';
