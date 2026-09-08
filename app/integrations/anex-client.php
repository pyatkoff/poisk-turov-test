<?php
declare(strict_types=1);

/** Read-only ANEX transport. Inclusion performs no configuration or network work. */
final class AnyTourAnexClient
{
    private const ENDPOINT = 'https://parser.anextour.ru/export/default.php';
    private const BODY_LIMIT = 2097152;
    private const REQUEST_LIMIT = 12;
    private $token;
    private $transport;
    private $requests = 0;
    private $rateDirectory;
    private $lastRequest = [];

    /** Fixed action and numeric metadata only; never URL, headers or supplier text. */
    public function lastRequestDiagnostics(): array { return $this->lastRequest; }

    public function __construct(string $token, ?callable $transport = null, ?string $rateDirectory = null)
    {
        $token = trim($token);
        if ($token === '' || strlen($token) > 4096 || preg_match('/[\x00-\x20\x7f]/', $token)) {
            throw new RuntimeException('ANEX_TOKEN_REQUIRED');
        }
        $this->token = $token;
        $this->transport = $transport;
        $this->rateDirectory = $rateDirectory ?? sys_get_temp_dir();
    }

    public function __debugInfo(): array
    {
        return ['token' => '[redacted]', 'requests' => $this->requests];
    }

    public function requestsMade(): int
    {
        return $this->requests;
    }

    /** Returns the method payload, not its JSON envelope. Errors never include supplier text. */
    public function request(string $action, array $params = []): array
    {
        $params = $this->validateParams($action, $params);
        if ($this->requests >= self::REQUEST_LIMIT) {
            throw new RuntimeException('ANEX_REQUEST_LIMIT');
        }
        $url = self::ENDPOINT . '?' . http_build_query(array_merge([
            'samo_action' => 'api', 'version' => '1.0', 'type' => 'json',
            'action' => $action, 'oauth_token' => $this->token,
        ], $params), '', '&', PHP_QUERY_RFC3986);
        $options = [
            'verify_peer' => true, 'verify_host' => 2, 'follow_redirects' => false,
            'proxy' => '', 'timeout' => 20, 'connect_timeout' => 10,
            'max_response_bytes' => self::BODY_LIMIT, 'protocol' => 'https',
        ];
        $this->lastRequest = ['action' => $action];
        ++$this->requests;
        try {
            $response = $this->transport !== null
                ? ($this->transport)($url, $options)
                : $this->curlRequest($url);
        } catch (Throwable $ignored) {
            // Do not chain: transport exceptions can contain the token-bearing URL.
            throw new RuntimeException('ANEX_TRANSPORT_ERROR');
        }
        if (!is_array($response) || !isset($response['status'], $response['body'])
            || !is_int($response['status']) || !is_string($response['body'])) {
            throw new RuntimeException('ANEX_INVALID_RESPONSE');
        }
        $this->lastRequest['http_status'] = $response['status'];
        $this->lastRequest['response_bytes'] = strlen($response['body']);
        if (strlen($response['body']) > self::BODY_LIMIT) {
            throw new RuntimeException('ANEX_RESPONSE_TOO_LARGE');
        }
        if ($response['status'] !== 200) {
            throw new RuntimeException('ANEX_HTTP_ERROR');
        }
        try {
            $envelope = json_decode($response['body'], true, 64, JSON_THROW_ON_ERROR);
        } catch (Throwable $ignored) {
            throw new RuntimeException('ANEX_INVALID_RESPONSE');
        }
        if (!is_array($envelope)) {
            throw new RuntimeException('ANEX_INVALID_RESPONSE');
        }
        if (array_key_exists('error', $envelope)) {
            $this->errorDiagnostics($envelope);
            if (is_int($envelope['error']) && $envelope['error'] >= 0 && $envelope['error'] <= 99999) $this->lastRequest['supplier_code'] = $envelope['error'];
            if ($action === 'SearchTour_PRICES' && in_array($envelope['error'], [2110, '2110'], true)) {
                return ['prices' => [], 'empty_reason' => 'no_hotels_for_filters'];
            }
            throw new RuntimeException('ANEX_SUPPLIER_ERROR');
        }
        if (!array_key_exists($action, $envelope)) {
            throw new RuntimeException('ANEX_INVALID_RESPONSE');
        }
        $payload = $envelope[$action];
        if ($action === 'Hotels_DETAILS' && ($payload === null || $payload === false || $payload === '')) {
            return [];
        }
        if (!is_array($payload)) {
            throw new RuntimeException('ANEX_INVALID_RESPONSE');
        }
        if (array_key_exists('error', $payload)) {
            $this->errorDiagnostics($payload);
            if (is_int($payload['error']) && $payload['error'] >= 0 && $payload['error'] <= 99999) $this->lastRequest['supplier_code'] = $payload['error'];
            if ($action === 'SearchTour_PRICES' && in_array($payload['error'], [2110, '2110'], true)) {
                return ['prices' => [], 'empty_reason' => 'no_hotels_for_filters'];
            }
            throw new RuntimeException('ANEX_SUPPLIER_ERROR');
        }
        // The client owns the credential, so callers never need to duplicate it
        // in their own label filters to prevent a supplier echo from escaping.
        return $this->redactPayload($payload);
    }

    private function errorDiagnostics(array $payload): void
    {
        // Read-only operational evidence, never included in public HTTP responses.
        $clean = $this->redactPayload($payload);
        $fields = [];
        foreach (array_slice($clean, 0, 12, true) as $key => $value) {
            if ($key === 'error' || !is_string($key) || !preg_match('/\A[a-zA-Z_]{1,40}\z/D', $key)) continue;
            if (is_string($value) && strlen($value) <= 600
                && !preg_match('~https?://|oauth|token|password|secret~i', $key . ' ' . $value)) {
                $fields[$key] = $value;
            }
        }
        if ($fields) $this->lastRequest['supplier_fields'] = $fields;
    }

    private function redactPayload(array $payload): array
    {
        // JSON parsing already caps nesting at 64 and the entire body at 2 MiB.
        $clean = [];
        foreach ($payload as $key => $value) {
            if (is_string($key) && $this->containsToken($key)) {
                continue;
            }
            $clean[$key] = is_array($value) ? $this->redactPayload($value)
                : (is_string($value) && $this->containsToken($value) ? null : $value);
        }
        return $clean;
    }

    private function containsToken(string $value): bool
    {
        for ($round = 0; $round < 8; ++$round) {
            if (strpos($value, $this->token) !== false) {
                return true;
            }
            $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (strpos($decoded, $this->token) !== false) {
                return true;
            }
            $decoded = rawurldecode($decoded);
            if ($decoded === $value) {
                return false;
            }
            $value = $decoded;
        }
        // Discard excessive nested encoding rather than leave an unexamined
        // representation for a downstream decoder to expose.
        return true;
    }

    private function validateParams(string $action, array $params): array
    {
        $party = ['TOWNFROMINC', 'STATEINC', 'ADULT', 'CHILD', 'AGES'];
        $dated = array_merge($party, ['CHECKIN_BEG', 'CHECKIN_END']);
        $priced = array_merge($dated, ['CURRENCY']);
        $allowed = [
            'SearchTour_TOWNFROMS' => [],
            'SearchTour_STATES' => ['TOWNFROMINC'],
            'SearchTour_CHECKIN' => $party,
            'SearchTour_CURRENCIES' => $dated,
            'SearchTour_NIGHTS' => $priced,
            'SearchTour_PRICES' => array_merge($priced, [
                'NIGHTS_FROM', 'NIGHTS_TILL', 'FREIGHT', 'FILTER', 'PRICEPAGE',
                'PARTITION_PRICE', 'SORT', 'DYN_SEPARATE', 'CATCLAIM', 'HOTELS',
            ]),
            'Hotels_DETAILS' => ['HOTELINC'],
            'FreightMonitor_FREIGHTSBYPACKET' => ['CATCLAIM'],
        ];
        if (!array_key_exists($action, $allowed)) {
            throw new RuntimeException('ANEX_ACTION_NOT_ALLOWED');
        }
        $ranges = [
            'TOWNFROMINC' => [1, 99999999], 'STATEINC' => [1, 99999999],
            'CURRENCY' => [1, 99999999], 'HOTELINC' => [1, 99999999],
            'ADULT' => [1, 6], 'CHILD' => [0, 6], 'NIGHTS_FROM' => [1, 60],
            'NIGHTS_TILL' => [1, 60], 'PRICEPAGE' => [1, 100],
            'FREIGHT' => [0, 1], 'FILTER' => [0, 1], 'DYN_SEPARATE' => [0, 1],
            'PARTITION_PRICE' => [0, 255],
        ];
        foreach ($params as $key => $value) {
            if (!in_array($key, $allowed[$action], true) || (!is_int($value) && !is_string($value))) {
                throw new RuntimeException('ANEX_INVALID_PARAMS');
            }
            $value = (string) $value;
            if (isset($ranges[$key])) {
                if (!preg_match('/^[0-9]{1,8}$/D', $value)
                    || (int) $value < $ranges[$key][0] || (int) $value > $ranges[$key][1]) {
                    throw new RuntimeException('ANEX_INVALID_PARAMS');
                }
                $params[$key] = (int) $value;
            } elseif ($key === 'CHECKIN_BEG' || $key === 'CHECKIN_END') {
                if (!preg_match('/^[0-9]{8}$/D', $value)
                    || !checkdate((int) substr($value, 4, 2), (int) substr($value, 6, 2), (int) substr($value, 0, 4))) {
                    throw new RuntimeException('ANEX_INVALID_PARAMS');
                }
                $params[$key] = $value;
            } elseif ($key === 'SORT') {
                if ($value !== 'ASC' && $value !== 'DESC') {
                    throw new RuntimeException('ANEX_INVALID_PARAMS');
                }
            } elseif ($key === 'CATCLAIM') {
                // Opaque supplier identifier; URL encoding keeps it a single parameter.
                if (!preg_match('/^[A-Za-z0-9_.:,;~@+\-=\/|]{1,2048}$/D', $value)) {
                    throw new RuntimeException('ANEX_INVALID_PARAMS');
                }
            } elseif ($key === 'HOTELS' || $key === 'AGES') {
                $items = explode(',', $value);
                $limit = $key === 'HOTELS' ? 30 : 6;
                if (count($items) > $limit) {
                    throw new RuntimeException('ANEX_INVALID_PARAMS');
                }
                foreach ($items as $item) {
                    if (!preg_match('/^[0-9]{1,8}$/D', $item)
                        || ($key === 'HOTELS' && ((int) $item < 1 || (int) $item > 99999999))
                        || ($key === 'AGES' && (int) $item > 17)) {
                        throw new RuntimeException('ANEX_INVALID_PARAMS');
                    }
                }
            }
        }
        if (($action === 'Hotels_DETAILS' && !isset($params['HOTELINC']))
            || ($action === 'FreightMonitor_FREIGHTSBYPACKET' && !isset($params['CATCLAIM']))
            || (isset($params['CHECKIN_BEG'], $params['CHECKIN_END']) && $params['CHECKIN_BEG'] > $params['CHECKIN_END'])
            || (isset($params['NIGHTS_FROM'], $params['NIGHTS_TILL']) && $params['NIGHTS_FROM'] > $params['NIGHTS_TILL'])
            || ((int) ($params['CHILD'] ?? 0) > 0 && !isset($params['AGES']))
            || (isset($params['AGES']) && count(explode(',', (string) $params['AGES'])) !== (int) ($params['CHILD'] ?? 0))) {
            throw new RuntimeException('ANEX_INVALID_PARAMS');
        }
        return $params;
    }

    /** Only real cURL requests share this token-hash state across PHP workers. */
    private function rateSlot(?float $blockedUntil = null): bool
    {
        $path = rtrim($this->rateDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
            . 'anytour-anex-rate-' . hash('sha256', $this->token) . '.json';
        if (is_link($path)) throw new RuntimeException('ANEX_TRANSPORT_ERROR');
        $handle = @fopen($path, 'c+');
        if ($handle === false) throw new RuntimeException('ANEX_TRANSPORT_ERROR');
        try {
            $opened = fstat($handle);
            $named = @lstat($path);
            if (!$opened || !$named || ($named['mode'] & 0170000) !== 0100000
                || $opened['ino'] !== $named['ino'] || $opened['dev'] !== $named['dev']
                || !@chmod($path, 0600)) throw new RuntimeException('ANEX_TRANSPORT_ERROR');
            while (true) {
                if (!flock($handle, LOCK_EX)) throw new RuntimeException('ANEX_TRANSPORT_ERROR');
                rewind($handle);
                $raw = stream_get_contents($handle, 2048);
                $state = $raw === '' ? ['next_at' => 0, 'blocked_until' => 0] : json_decode($raw, true);
                if (!is_array($state) || !isset($state['next_at'], $state['blocked_until'])) {
                    throw new RuntimeException('ANEX_TRANSPORT_ERROR');
                }
                foreach (['next_at', 'blocked_until'] as $field) {
                    if ((!is_int($state[$field]) && !is_float($state[$field]))
                        || !is_finite((float) $state[$field]) || $state[$field] < 0) {
                        throw new RuntimeException('ANEX_TRANSPORT_ERROR');
                    }
                }
                $now = microtime(true);
                if ($blockedUntil !== null) {
                    $state['blocked_until'] = max($state['blocked_until'], $blockedUntil, $now + 1.05);
                } else {
                    // A supplier cooldown fails immediately; it never occupies a worker for minutes.
                    if ($state['blocked_until'] > $now) return false;
                    $wait = $state['next_at'] - $now;
                    if ($wait > 0) {
                        flock($handle, LOCK_UN);
                        usleep((int) ceil(min($wait, 1.05) * 1000000));
                        continue;
                    }
                    $state['next_at'] = $now + 1.05;
                }
                $encoded = json_encode($state);
                rewind($handle);
                if (!ftruncate($handle, 0) || fwrite($handle, $encoded) !== strlen($encoded)
                    || !fflush($handle)) throw new RuntimeException('ANEX_TRANSPORT_ERROR');
                return true;
            }
        } finally {
            fclose($handle);
        }
    }

    private function retryUntil(string $value): ?float
    {
        if (preg_match('/^[0-9]{1,9}$/D', $value)) return microtime(true) + (int) $value;
        if (strlen($value) !== 29 || preg_match('/[^\x20-\x7e]/', $value)) return null;
        $date = DateTimeImmutable::createFromFormat('!D, d M Y H:i:s \G\M\T', $value, new DateTimeZone('UTC'));
        return $date && $date->format('D, d M Y H:i:s \G\M\T') === $value
            ? (float) $date->getTimestamp() : null;
    }

    private function curlRequest(string $url): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('ANEX_TRANSPORT_UNAVAILABLE');
        }
        if (!$this->rateSlot()) return ['status' => 429, 'body' => ''];
        $handle = curl_init();
        if ($handle === false) {
            throw new RuntimeException('ANEX_TRANSPORT_UNAVAILABLE');
        }
        $body = '';
        $retryUntil = null;
        try {
            $configured = curl_setopt_array($handle, [
                CURLOPT_URL => $url, CURLOPT_HTTPGET => true,
                CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_FOLLOWLOCATION => false, CURLOPT_MAXREDIRS => 0,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_PROXY => '', CURLOPT_NOPROXY => '*',
                CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 20,
                CURLOPT_HEADER => false, CURLOPT_RETURNTRANSFER => false, CURLOPT_VERBOSE => false,
                CURLOPT_HTTPHEADER => ['Accept: application/json'],
                CURLOPT_USERAGENT => 'AnyTour-ANEX-read-client/1.0',
                CURLOPT_HEADERFUNCTION => function ($unused, string $line) use (&$retryUntil): int {
                    if (stripos($line, 'HTTP/') === 0) $retryUntil = null;
                    if (stripos($line, 'Retry-After:') === 0 && strlen($line) <= 128) {
                        $retryUntil = $this->retryUntil(trim(substr($line, 12)));
                    }
                    return strlen($line);
                },
                CURLOPT_WRITEFUNCTION => static function ($unused, string $chunk) use (&$body): int {
                    $remaining = self::BODY_LIMIT + 1 - strlen($body);
                    $body .= substr($chunk, 0, max(0, $remaining));
                    return strlen($body) > self::BODY_LIMIT ? 0 : strlen($chunk);
                },
            ]);
            if (!$configured) {
                throw new RuntimeException('ANEX_TRANSPORT_ERROR');
            }
            $completed = curl_exec($handle);
            $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            $this->lastRequest['http_status'] = $status;
            $this->lastRequest['curl_errno'] = curl_errno($handle);
            $this->lastRequest['response_bytes'] = strlen($body);
            $this->lastRequest['elapsed_ms'] = (int) round(curl_getinfo($handle, CURLINFO_TOTAL_TIME) * 1000);
            if ($status === 429) $this->rateSlot($retryUntil ?? microtime(true) + 60);
            if ($completed === false && strlen($body) <= self::BODY_LIMIT) {
                throw new RuntimeException('ANEX_TRANSPORT_ERROR');
            }
            return ['status' => $status, 'body' => $body];
        } finally {
            curl_close($handle);
        }
    }
}
