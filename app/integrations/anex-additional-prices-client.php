<?php
declare(strict_types=1);

/**
 * Read-only client for the ANEX B2B AdditionalPricesDaily endpoint.
 *
 * This client deliberately does not interpret the supplier response as fuel,
 * package total, or final price. Successful page-1 runtime reads may be shared
 * through a private same-day cache, but money semantics remain with the caller.
 */
final class AnyTourAnexAdditionalPricesClient
{
    private const ENDPOINT = 'https://api.anextour.ru/b2b/AdditionalPricesDaily';
    private const BODY_LIMIT = 524288;
    private const CACHE_VERSION = 1;
    private const CACHE_MAX_BYTES = 65536;
    private const CACHE_TIMEZONE = 'Europe/Moscow';

    /** @var string */
    private $token;
    /** @var callable|null */
    private $transport;
    /** @var string|null */
    private $cacheDir;
    /** @var callable */
    private $clock;
    /** @var bool */
    private $used = false;
    /** @var int */
    private $requests = 0;
    /** @var array<string,int|string> */
    private $lastRequest = [];

    /**
     * Custom transports default to cache-off so offline tests/diagnostics remain isolated.
     * Pass an explicit cache directory to exercise shared-cache behavior with a custom transport.
     */
    public function __construct(string $bearerToken, ?callable $transport = null, ?string $cacheDir = null, ?callable $clock = null)
    {
        $bearerToken = trim($bearerToken);
        if ($bearerToken === '' || strlen($bearerToken) > 8192 || preg_match('/[\x00-\x20\x7f]/', $bearerToken)) {
            throw new RuntimeException('ANEX_B2B_TOKEN_REQUIRED');
        }
        if ($cacheDir !== null && ($cacheDir === '' || strpos($cacheDir, "\0") !== false)) {
            throw new InvalidArgumentException('ANEX_B2B_INVALID_CACHE_DIR');
        }
        $this->token = $bearerToken;
        $this->transport = $transport;
        $this->cacheDir = $cacheDir;
        if ($cacheDir === null && $transport === null) {
            $this->cacheDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'anytour-anex-apd-v1';
        }
        $this->clock = $clock ?? static function (): int { return time(); };
    }

    public function __debugInfo(): array
    {
        return ['token' => '[redacted]', 'requests' => $this->requests];
    }

    /** Actual supplier HTTP transports only; cache hits do not increment this counter. */
    public function requestsMade(): int
    {
        return $this->requests;
    }

    /** Fixed metadata only; never URL, headers, token, supplier ids, or supplier response text. */
    public function lastRequestDiagnostics(): array
    {
        return $this->lastRequest;
    }

    /**
     * Returns the sanitized decoded supplier payload without assigning money semantics.
     * Exactly one logical read is allowed per client instance and there are no retries.
     */
    public function additionalPricesDaily(array $criteria): array
    {
        if ($this->used) {
            throw new RuntimeException('ANEX_B2B_REQUEST_LIMIT');
        }
        $this->used = true;
        $criteria = $this->validateCriteria($criteria);
        $this->lastRequest = [
            'action' => 'AdditionalPricesDaily',
            'page' => $criteria['page'],
            'page_size' => $criteria['pageSize'],
        ];

        if ($this->cacheDir !== null && $criteria['page'] === 1 && $criteria['pageSize'] === 10) {
            $cached = $this->dailyCacheRead($criteria);
            if ($cached !== null) return $cached;
        }

        $payload = $this->supplierRead($criteria);
        if ($this->cacheDir !== null && $criteria['page'] === 1 && $criteria['pageSize'] === 10) {
            $this->dailyCacheComplete($criteria, $payload);
        }
        return $payload;
    }

    /**
     * Returns a completed cached payload, null after reserving a fresh miss, or throws for a same-day unknown/corrupt entry.
     * A per-context exclusive lock is intentionally held only while inspecting/reserving the file. The durable unknown
     * reservation is written before transport, so another process cannot replay the same context after the lock is released.
     */
    private function dailyCacheRead(array $criteria): ?array
    {
        $paths = $this->dailyCachePaths($criteria);
        if ($paths === null) {
            $this->lastRequest['cache_status'] = 'unavailable';
            return null;
        }
        $lock = @fopen($paths['lock'], 'c+b');
        if ($lock === false) {
            $this->lastRequest['cache_status'] = 'unavailable';
            return null;
        }
        @chmod($paths['lock'], 0600);
        try {
            if (!flock($lock, LOCK_EX)) {
                $this->lastRequest['cache_status'] = 'unavailable';
                return null;
            }
            $entry = $this->readCacheEntry($paths['data']);
            if ($entry !== null) {
                if (($entry['version'] ?? null) !== self::CACHE_VERSION
                    || ($entry['day'] ?? null) !== $paths['day']
                    || ($entry['context_digest'] ?? null) !== $paths['digest']) {
                    $this->lastRequest['cache_status'] = 'unknown';
                    throw new RuntimeException('ANEX_B2B_DAILY_UNKNOWN');
                }
                if (($entry['status'] ?? null) === 'unknown') {
                    $this->lastRequest['cache_status'] = 'unknown';
                    throw new RuntimeException('ANEX_B2B_DAILY_UNKNOWN');
                }
                if (($entry['status'] ?? null) !== 'complete' || !is_array($entry['payload'] ?? null)) {
                    $this->lastRequest['cache_status'] = 'unknown';
                    throw new RuntimeException('ANEX_B2B_DAILY_UNKNOWN');
                }
                $payload = $this->hydrateCachedPayload($entry['payload'], $criteria);
                $this->validateResponseContext($payload, $criteria);
                $this->lastRequest['cache_status'] = 'hit';
                return $payload;
            }

            $reserved = [
                'version' => self::CACHE_VERSION,
                'day' => $paths['day'],
                'context_digest' => $paths['digest'],
                'status' => 'unknown',
            ];
            if (!$this->writeCacheEntry($paths['data'], $reserved)) {
                $this->lastRequest['cache_status'] = 'unavailable';
                return null;
            }
            $this->lastRequest['cache_status'] = 'miss';
            return null;
        } finally {
            @flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** Replace this call's own same-day unknown reservation with a sanitized completed payload. */
    private function dailyCacheComplete(array $criteria, array $payload): void
    {
        $paths = $this->dailyCachePaths($criteria);
        if ($paths === null) return;
        $lock = @fopen($paths['lock'], 'c+b');
        if ($lock === false) return;
        @chmod($paths['lock'], 0600);
        try {
            if (!flock($lock, LOCK_EX)) return;
            $entry = $this->readCacheEntry($paths['data']);
            if (!is_array($entry)
                || ($entry['version'] ?? null) !== self::CACHE_VERSION
                || ($entry['day'] ?? null) !== $paths['day']
                || ($entry['context_digest'] ?? null) !== $paths['digest']
                || ($entry['status'] ?? null) !== 'unknown') {
                return;
            }
            try {
                $cachePayload = $this->cachePayload($payload);
            } catch (Throwable $ignored) {
                return;
            }
            $complete = [
                'version' => self::CACHE_VERSION,
                'day' => $paths['day'],
                'context_digest' => $paths['digest'],
                'status' => 'complete',
                'payload' => $cachePayload,
            ];
            $this->writeCacheEntry($paths['data'], $complete);
        } finally {
            @flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** @return array{day:string,digest:string,data:string,lock:string}|null */
    private function dailyCachePaths(array $criteria): ?array
    {
        if ($this->cacheDir === null) return null;
        $now = ($this->clock)();
        if (!is_int($now) || $now < 1) throw new RuntimeException('ANEX_B2B_CLOCK_ERROR');
        $day = (new DateTimeImmutable('@' . $now))->setTimezone(new DateTimeZone(self::CACHE_TIMEZONE))->format('Y-m-d');
        $digest = hash('sha256', implode("\0", [
            (string) $criteria['tour'], (string) $criteria['currency'], $criteria['dateBeg'], (string) $criteria['nights'],
        ]));
        $root = rtrim($this->cacheDir, DIRECTORY_SEPARATOR);
        if (!$this->ensurePrivateDir($root)) return null;
        $dayDir = $root . DIRECTORY_SEPARATOR . $day;
        if (!$this->ensurePrivateDir($dayDir)) return null;
        return [
            'day' => $day,
            'digest' => $digest,
            'data' => $dayDir . DIRECTORY_SEPARATOR . $digest . '.json',
            'lock' => $dayDir . DIRECTORY_SEPARATOR . $digest . '.lock',
        ];
    }

    private function ensurePrivateDir(string $path): bool
    {
        if ($path === '' || is_link($path)) return false;
        if (!is_dir($path)) {
            if (!@mkdir($path, 0700, true) && !is_dir($path)) return false;
        }
        if (is_link($path)) return false;
        $owner = @fileowner($path);
        if (function_exists('posix_geteuid') && is_int($owner) && $owner !== posix_geteuid()) return false;
        @chmod($path, 0700);
        return is_readable($path) && is_writable($path);
    }

    private function readCacheEntry(string $path): ?array
    {
        if (!file_exists($path)) return null;
        if (!is_file($path) || is_link($path)) throw new RuntimeException('ANEX_B2B_DAILY_UNKNOWN');
        $size = @filesize($path);
        if (!is_int($size) || $size < 2 || $size > self::CACHE_MAX_BYTES) throw new RuntimeException('ANEX_B2B_DAILY_UNKNOWN');
        $raw = @file_get_contents($path);
        if (!is_string($raw) || strlen($raw) !== $size) throw new RuntimeException('ANEX_B2B_DAILY_UNKNOWN');
        try {
            $decoded = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        } catch (Throwable $ignored) {
            throw new RuntimeException('ANEX_B2B_DAILY_UNKNOWN');
        }
        if (!is_array($decoded)) throw new RuntimeException('ANEX_B2B_DAILY_UNKNOWN');
        return $decoded;
    }

    private function writeCacheEntry(string $path, array $entry): bool
    {
        try {
            $json = json_encode($entry, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (Throwable $ignored) {
            return false;
        }
        if (!is_string($json) || strlen($json) > self::CACHE_MAX_BYTES) return false;
        $tmp = $path . '.tmp.' . bin2hex(random_bytes(8));
        $handle = @fopen($tmp, 'xb');
        if ($handle === false) return false;
        $ok = false;
        try {
            @chmod($tmp, 0600);
            $written = fwrite($handle, $json);
            if ($written !== strlen($json)) return false;
            if (!fflush($handle)) return false;
            if (function_exists('fsync') && !fsync($handle)) return false;
            $ok = true;
        } finally {
            fclose($handle);
            if (!$ok) @unlink($tmp);
        }
        if (!@rename($tmp, $path)) {
            @unlink($tmp);
            return false;
        }
        @chmod($path, 0600);
        return true;
    }

    /** Cache only the APD fields consumed by current money evidence; supplier context is represented only by the opaque digest. */
    private function cachePayload(array $payload): array
    {
        $rows = $payload['data'] ?? null;
        $total = $payload['totalCount'] ?? null;
        if (!is_array($rows) || ($rows !== [] && array_keys($rows) !== range(0, count($rows) - 1))) {
            throw new RuntimeException('ANEX_B2B_INVALID_RESPONSE');
        }
        if (is_string($total) && preg_match('/\A[0-9]{1,9}\z/D', $total)) $total = (int) $total;
        if (!is_int($total) || $total < 0 || $total > 100000000 || $total < count($rows)) {
            throw new RuntimeException('ANEX_B2B_INVALID_RESPONSE');
        }
        $clean = [];
        foreach ($rows as $row) {
            if (!is_array($row)) throw new RuntimeException('ANEX_B2B_INVALID_RESPONSE');
            $item = [];
            foreach (['price_adult', 'price_chd', 'cashrate', 'price_converted_adult', 'price_converted_chd'] as $field) {
                if (!array_key_exists($field, $row)) continue;
                $value = $row[$field];
                if (is_int($value) || (is_float($value) && is_finite($value))) $value = (string) $value;
                if (!is_string($value) || !preg_match('/\A(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,4})?\z/D', $value)) {
                    throw new RuntimeException('ANEX_B2B_INVALID_RESPONSE');
                }
                $item[$field] = $value;
            }
            $clean[] = $item;
        }
        return ['data' => $clean, 'totalCount' => $total];
    }

    private function hydrateCachedPayload(array $payload, array $criteria): array
    {
        $rows = $payload['data'] ?? null;
        if (!is_array($rows) || ($rows !== [] && array_keys($rows) !== range(0, count($rows) - 1))) {
            throw new RuntimeException('ANEX_B2B_DAILY_UNKNOWN');
        }
        $hydrated = [];
        foreach ($rows as $row) {
            if (!is_array($row)) throw new RuntimeException('ANEX_B2B_DAILY_UNKNOWN');
            $row['tour'] = $criteria['tour'];
            $row['currency'] = $criteria['currency'];
            $row['dateBeg'] = $criteria['dateBeg'];
            $row['nights'] = $criteria['nights'];
            $hydrated[] = $row;
        }
        return ['data' => $hydrated, 'totalCount' => $payload['totalCount'] ?? null];
    }

    private function supplierRead(array $criteria): array
    {
        $url = self::ENDPOINT . '?' . http_build_query($criteria, '', '&', PHP_QUERY_RFC3986);
        $headers = [
            'Accept: application/json',
            'User-Agent: TourismPlus',
            'Authorization: Bearer ' . $this->token,
        ];
        $options = [
            'verify_peer' => true,
            'verify_host' => 2,
            'follow_redirects' => false,
            'timeout' => 20,
            'connect_timeout' => 10,
            'max_response_bytes' => self::BODY_LIMIT,
            'protocol' => 'https',
        ];

        ++$this->requests;
        try {
            $response = $this->transport !== null
                ? ($this->transport)($url, $headers, $options)
                : $this->curlRequest($url, $headers);
        } catch (Throwable $ignored) {
            throw new RuntimeException('ANEX_B2B_TRANSPORT_ERROR');
        }

        if (!is_array($response)
            || !isset($response['status'], $response['body'])
            || !is_int($response['status'])
            || !is_string($response['body'])) {
            throw new RuntimeException('ANEX_B2B_INVALID_RESPONSE');
        }
        $this->lastRequest['http_status'] = $response['status'];
        $this->lastRequest['response_bytes'] = strlen($response['body']);
        if (strlen($response['body']) > self::BODY_LIMIT) {
            throw new RuntimeException('ANEX_B2B_RESPONSE_TOO_LARGE');
        }
        if ($response['status'] !== 200) {
            throw new RuntimeException('ANEX_B2B_HTTP_ERROR');
        }

        try {
            $decoded = json_decode($response['body'], true, 64, JSON_THROW_ON_ERROR);
        } catch (Throwable $ignored) {
            throw new RuntimeException('ANEX_B2B_INVALID_RESPONSE');
        }
        if (!is_array($decoded)) {
            throw new RuntimeException('ANEX_B2B_INVALID_RESPONSE');
        }
        $this->validateResponseContext($decoded, $criteria);
        return $this->redactPayload($decoded);
    }

    private function validateCriteria(array $criteria): array
    {
        $expected = ['page', 'pageSize', 'tour', 'dateBeg', 'nights', 'currency'];
        if (count($criteria) !== count($expected)
            || array_diff($expected, array_keys($criteria)) !== []
            || array_diff(array_keys($criteria), $expected) !== []) {
            throw new InvalidArgumentException('ANEX_B2B_INVALID_CRITERIA');
        }

        foreach (['page', 'pageSize', 'tour', 'nights', 'currency'] as $key) {
            $value = $criteria[$key];
            if ((!is_int($value) && !is_string($value))
                || !preg_match('/^[0-9]{1,9}$/D', (string) $value)) {
                throw new InvalidArgumentException('ANEX_B2B_INVALID_CRITERIA');
            }
            $criteria[$key] = (int) $value;
        }

        if ($criteria['page'] < 1 || $criteria['page'] > 10000
            || $criteria['pageSize'] < 1 || $criteria['pageSize'] > 100
            || $criteria['tour'] < 1 || $criteria['tour'] > 999999999
            || $criteria['nights'] < 1 || $criteria['nights'] > 60
            || $criteria['currency'] < 1 || $criteria['currency'] > 999999999) {
            throw new InvalidArgumentException('ANEX_B2B_INVALID_CRITERIA');
        }

        if (!is_string($criteria['dateBeg'])
            || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $criteria['dateBeg'], $m)
            || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            throw new InvalidArgumentException('ANEX_B2B_INVALID_CRITERIA');
        }

        return $criteria;
    }

    /** Fail closed unless supplier rows match the exact requested B2B tour/date/night/currency context. */
    private function validateResponseContext(array $payload, array $criteria): void
    {
        $rows = $payload['data'] ?? null;
        if (!is_array($rows) || ($rows !== [] && array_keys($rows) !== range(0, count($rows) - 1))) {
            throw new RuntimeException('ANEX_B2B_INVALID_RESPONSE');
        }
        foreach ($rows as $row) {
            if (!is_array($row)) throw new RuntimeException('ANEX_B2B_INVALID_RESPONSE');
            $tour = $row['tour'] ?? null;
            $currency = $row['currency'] ?? null;
            $nights = $row['nights'] ?? null;
            if ((is_int($tour) || is_string($tour)) && preg_match('/\A[1-9][0-9]{0,8}\z/D', (string) $tour)) $tour = (int) $tour; else $tour = null;
            if ((is_int($currency) || is_string($currency)) && preg_match('/\A[1-9][0-9]{0,8}\z/D', (string) $currency)) $currency = (int) $currency; else $currency = null;
            if ((is_int($nights) || is_string($nights)) && preg_match('/\A[0-9]{1,2}\z/D', (string) $nights)) $nights = (int) $nights; else $nights = null;
            $date = $row['dateBeg'] ?? null;
            if (!is_string($date) || !preg_match('/\A(\d{4}-\d{2}-\d{2})(?:T00:00:00)?\z/D', $date, $m)) $date = null; else $date = $m[1];
            if ($tour !== $criteria['tour'] || $currency !== $criteria['currency']
                || $date !== $criteria['dateBeg'] || $nights !== $criteria['nights']) {
                throw new RuntimeException('ANEX_B2B_CONTEXT_MISMATCH');
            }
        }
    }

    private function redactPayload(array $payload): array
    {
        $clean = [];
        foreach ($payload as $key => $value) {
            if (is_string($key) && $this->containsToken($key)) continue;
            if (is_array($value)) {
                $clean[$key] = $this->redactPayload($value);
            } elseif (is_string($value) && $this->containsToken($value)) {
                $clean[$key] = null;
            } else {
                $clean[$key] = $value;
            }
        }
        return $clean;
    }

    private function containsToken(string $value): bool
    {
        for ($round = 0; $round < 8; ++$round) {
            if (strpos($value, $this->token) !== false) return true;
            $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (strpos($decoded, $this->token) !== false) return true;
            $decoded = rawurldecode($decoded);
            if ($decoded === $value) return false;
            $value = $decoded;
        }
        return true;
    }

    private function curlRequest(string $url, array $headers): array
    {
        if (!function_exists('curl_init')) throw new RuntimeException('ANEX_B2B_TRANSPORT_ERROR');
        $ch = curl_init($url);
        if ($ch === false) throw new RuntimeException('ANEX_B2B_TRANSPORT_ERROR');
        $body = '';
        $tooLarge = false;
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$body, &$tooLarge): int {
                if (strlen($body) + strlen($chunk) > self::BODY_LIMIT) {
                    $tooLarge = true;
                    return 0;
                }
                $body .= $chunk;
                return strlen($chunk);
            },
        ]);
        try {
            $ok = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            if ($ok === false || $tooLarge) throw new RuntimeException('ANEX_B2B_TRANSPORT_ERROR');
            return ['status' => $status, 'body' => $body];
        } finally {
            curl_close($ch);
        }
    }
}
