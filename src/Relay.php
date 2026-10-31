<?php

declare(strict_types=1);

namespace NepaliRadio;

/**
 * An https relay for plain-http radio streams.
 *
 * Browsers refuse to play http:// audio inside an https:// page. The listener opens
 * https://<this server>/relay/{station-id} instead; this class fetches the real stream and passes the bytes on.
 *
 * Safety: only stations from data/radios.json can be relayed (never an address given in the request), the target
 * must not be on a private network, mail/database ports are refused, the number of listeners at once is capped
 * and each listener is cut after a few hours.
 */
final class Relay
{
    /** Ports that are never a radio stream (mail, databases, SSH ...). */
    public const BLOCKED_PORTS = [21, 22, 23, 25, 53, 110, 143, 445, 465, 587, 993, 995, 1433, 3306, 3389, 5432, 6379, 11211, 27017];

    private const USER_AGENT = 'VLC/3.0.20 LibVLC/3.0.20';

    public function __construct(
        private readonly int $maxListeners = 20,
        private readonly int $maxMinutes = 720,
        private readonly ?string $slotDir = null,
    ) {
    }

    /**
     * May this address be fetched? Only http(s), allowed ports, and never an address inside a private network.
     *
     * @return array{ok:bool,reason:string,host:string,port:int,ip:string}
     */
    public static function inspect(string $url): array
    {
        $fail = static fn (string $why): array => ['ok' => false, 'reason' => $why, 'host' => '', 'port' => 0, 'ip' => ''];
        $parts = parse_url(trim($url));
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = trim((string) ($parts['host'] ?? ''), '[]');

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            return $fail('Not a valid http(s) address.');
        }
        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
        if ($port < 1 || $port > 65535 || in_array($port, self::BLOCKED_PORTS, true)) {
            return $fail('That port is not allowed.');
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);
        if ($ips === []) {
            return $fail('The station server could not be found.');
        }
        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $fail('The address points inside a private network.');
            }
        }

        return ['ok' => true, 'reason' => '', 'host' => $host, 'port' => $port, 'ip' => $ips[0]];
    }

    /** Streams $url to the listener. Sends its own status line and headers; returns when the listener leaves. */
    public function stream(string $url): void
    {
        $slot = $this->takeSlot();
        if ($slot === null) {
            $this->fail(503, 'Too many listeners right now. Please try again in a minute.', ['Retry-After: 30']);

            return;
        }

        @set_time_limit(0);
        ignore_user_abort(false);
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $current = $url;
        for ($hop = 0; $hop < 4; $hop++) {
            $target = self::inspect($current);
            if (! $target['ok']) {
                $this->fail(403, $target['reason']);

                return;
            }

            $status = 0;
            $location = null;
            $type = '';
            $started = false;

            $ch = curl_init($current);
            curl_setopt_array($ch, [
                CURLOPT_FOLLOWLOCATION => false, // redirects are followed by hand so every hop is checked
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_TIMEOUT => $this->maxMinutes * 60,
                // A listener who left is only noticed when we try to write, so a stalled station must not hold a worker:
                // give up when less than 200 bytes/s arrive for 15 seconds (live audio is at least ~1000 bytes/s).
                CURLOPT_LOW_SPEED_LIMIT => 200,
                CURLOPT_LOW_SPEED_TIME => 15,
                CURLOPT_USERAGENT => self::USER_AGENT,
                CURLOPT_HTTPHEADER => ['Icy-MetaData: 0'],
                CURLOPT_RESOLVE => ["{$target['host']}:{$target['port']}:{$target['ip']}"], // connect to the address we checked
                CURLOPT_SSL_VERIFYPEER => false, // public audio only, no secrets travel over it
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_HEADERFUNCTION => static function ($c, string $line) use (&$status, &$location, &$type): int {
                    if (preg_match('#^(?:HTTP/\S+|ICY)\s+(\d{3})#i', $line, $m)) {
                        $status = (int) $m[1];
                        $location = null;
                        $type = '';
                    } elseif (stripos($line, 'location:') === 0) {
                        $location = trim(substr($line, 9));
                    } elseif (stripos($line, 'content-type:') === 0) {
                        $type = trim(substr($line, 13));
                    }

                    return strlen($line);
                },
                CURLOPT_WRITEFUNCTION => function ($c, string $data) use (&$status, &$type, &$started): int {
                    if ($status < 200 || $status >= 300 || stripos($type, 'text/html') === 0) {
                        return 0; // redirect, error or an HTML status page: not audio
                    }
                    if (! $started) {
                        http_response_code(200);
                        header('Content-Type: ' . ($type !== '' ? $type : 'audio/mpeg'));
                        header('Cache-Control: no-store');
                        header('X-Accel-Buffering: no'); // nginx: do not buffer a live stream
                        $started = true;
                    }
                    echo $data;
                    flush();

                    return connection_aborted() ? 0 : strlen($data);
                },
            ]);
            curl_exec($ch);
            curl_close($ch);

            if ($started) {
                return; // the listener listened until they left (or the time limit)
            }
            if ($status >= 300 && $status < 400 && $location) {
                $current = self::absolute($current, $location);

                continue;
            }
            $this->fail(502, 'The station is not answering with audio.');

            return;
        }

        $this->fail(502, 'Too many redirects.');
    }

    /** @return resource|null a locked slot file; the lock is released when the request ends */
    private function takeSlot()
    {
        $dir = $this->slotDir ?? (sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nepali-radio-relay');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        for ($i = 0; $i < $this->maxListeners; $i++) {
            $fh = @fopen($dir . DIRECTORY_SEPARATOR . "slot-{$i}.lock", 'c');
            if ($fh && flock($fh, LOCK_EX | LOCK_NB)) {
                return $fh;
            }
            if ($fh) {
                fclose($fh);
            }
        }

        return null;
    }

    private static function absolute(string $base, string $location): string
    {
        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }
        $p = parse_url($base);
        $origin = $p['scheme'] . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
        if (str_starts_with($location, '/')) {
            return $origin . $location;
        }

        return $origin . rtrim(dirname($p['path'] ?? '/'), '/') . '/' . $location;
    }

    /** @param list<string> $headers */
    private function fail(int $code, string $message, array $headers = []): void
    {
        if (headers_sent()) {
            return;
        }
        http_response_code($code);
        header('Content-Type: text/plain; charset=utf-8');
        foreach ($headers as $h) {
            header($h);
        }
        echo $message;
    }
}
