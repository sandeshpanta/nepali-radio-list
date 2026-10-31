<?php

declare(strict_types=1);

namespace NepaliRadio;

/**
 * The JSON API:
 *   GET /api/radios            ?province=Bagmati &q=kantipur &online=all &limit=50 &offset=0
 *   GET /api/radios/{id}
 *   GET /api/provinces
 */
final class Api
{
    private const MAX_LIMIT = 500;

    public function __construct(private readonly RadioRepository $radios, private readonly string $baseUrl)
    {
    }

    /** Returns [status, body]. */
    public function handle(string $path, array $query): array
    {
        $path = '/' . trim($path, '/');

        if ($path === '/api/radios') {
            return $this->list($query);
        }
        if ($path === '/api/provinces') {
            return [200, ['data' => $this->radios->provinces()]];
        }
        if (preg_match('#^/api/radios/([a-z0-9-]+)$#', $path, $m)) {
            $station = $this->radios->find($m[1]);

            return $station === null
                ? [404, ['error' => 'Station not found.']]
                : [200, ['data' => $this->present($station)]];
        }

        return [404, ['error' => 'Unknown endpoint.', 'endpoints' => ['/api/radios', '/api/radios/{id}', '/api/provinces']]];
    }

    /** All online stations as CSV (UTF-8 with BOM so Excel shows Nepali text correctly). */
    public function csv(): string
    {
        $columns = ['id', 'name', 'province', 'frequency', 'address', 'stream_url', 'logo_url', 'checked_at'];
        $out = fopen('php://temp', 'r+');
        fputcsv($out, $columns, ',', '"', '');
        foreach ($this->radios->all(['online' => true]) as $station) {
            $row = $this->present($station);
            fputcsv($out, array_map(static fn (string $c) => $row[$c] ?? '', $columns), ',', '"', '');
        }
        rewind($out);

        return "\xEF\xBB\xBF" . stream_get_contents($out);
    }

    private function list(array $query): array
    {
        // Only stations whose stream last checked out are listed, unless ?online=0 (offline only) or ?online=all.
        $online = true;
        if (isset($query['online'])) {
            $online = $query['online'] === 'all'
                ? null
                : filter_var($query['online'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
        }

        $all = $this->radios->all([
            'province' => is_string($query['province'] ?? null) ? $query['province'] : null,
            'q' => is_string($query['q'] ?? null) ? $query['q'] : null,
            'online' => $online,
        ]);

        $limit = isset($query['limit']) ? max(1, min(self::MAX_LIMIT, (int) $query['limit'])) : self::MAX_LIMIT;
        $offset = isset($query['offset']) ? max(0, (int) $query['offset']) : 0;

        return [200, [
            'data' => array_map($this->present(...), array_slice($all, $offset, $limit)),
            'meta' => [
                'total' => count($all),
                'limit' => $limit,
                'offset' => $offset,
                'updated' => $this->radios->updated(),
            ],
        ]];
    }

    /** @param array<string,mixed> $station */
    private function present(array $station): array
    {
        $station['logo_url'] = $station['logo'] ? $this->baseUrl . '/' . ltrim((string) $station['logo'], '/') : null;
        unset($station['logo']);

        return $station;
    }
}
