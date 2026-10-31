<?php

declare(strict_types=1);

namespace NepaliRadio;

/**
 * Reads data/radios.json and answers the questions the website and the API ask.
 */
final class RadioRepository
{
    public const PROVINCES = ['Koshi', 'Madhesh', 'Bagmati', 'Gandaki', 'Lumbini', 'Karnali', 'Sudurpashchim', 'World'];

    /** @var list<array<string,mixed>> */
    private array $stations;

    private string $updated;

    public function __construct(private readonly string $file)
    {
        $json = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        if (! is_array($json) || ! isset($json['stations'])) {
            throw new \RuntimeException("Radio data not found or invalid: {$file}");
        }
        $this->stations = array_values($json['stations']);
        $this->updated = (string) ($json['updated'] ?? '');
    }

    public function updated(): string
    {
        return $this->updated;
    }

    /**
     * @param array{province?:?string,q?:?string,online?:?bool} $filters
     * @return list<array<string,mixed>>
     */
    public function all(array $filters = []): array
    {
        $province = isset($filters['province']) ? mb_strtolower(trim((string) $filters['province'])) : '';
        $q = isset($filters['q']) ? mb_strtolower(trim((string) $filters['q'])) : '';
        $online = $filters['online'] ?? null;

        return array_values(array_filter($this->stations, static function (array $s) use ($province, $q, $online): bool {
            if ($province !== '' && mb_strtolower($s['province']) !== $province) {
                return false;
            }
            if ($online !== null && (bool) ($s['online'] ?? true) !== $online) {
                return false;
            }
            if ($q !== '') {
                $haystack = mb_strtolower($s['name'] . ' ' . ($s['address'] ?? '') . ' ' . ($s['frequency'] ?? ''));
                if (! str_contains($haystack, $q)) {
                    return false;
                }
            }

            return true;
        }));
    }

    /** @return array<string,mixed>|null */
    public function find(string $id): ?array
    {
        foreach ($this->stations as $s) {
            if ($s['id'] === $id) {
                return $s;
            }
        }

        return null;
    }

    /** @return list<array{name:string,count:int}> every province, in the usual order, with its station count */
    public function provinces(): array
    {
        $counts = array_count_values(array_column($this->stations, 'province'));

        return array_map(
            static fn (string $p): array => ['name' => $p, 'count' => $counts[$p] ?? 0],
            self::PROVINCES
        );
    }
}
