<?php
declare(strict_types=1);

/** Offline-first protocol client. No default network transport or runtime consumer. */
final class AnyTourAndromedaClient
{
    private const ENDPOINT = 'https://gateway.samo.ru/api/';
    private const BODY_LIMIT = 2097152; // Local safety budget, not a supplier limit.
    private $transport;
    private $enabled;
    private $sid = null;
    private $expires = 0;
    private $requests = 0;

    /** Transport accepts a secret-bearing URL and must never log it. */
    public function __construct(callable $transport, bool $enabled = false)
    {
        $this->transport = $transport;
        $this->enabled = $enabled;
    }

    public function __debugInfo(): array
    {
        return ['enabled' => $this->enabled, 'requests' => $this->requests];
    }

    public function __serialize(): array
    {
        throw new RuntimeException('ANDROMEDA_SERIALIZATION_DISABLED');
    }

    public function login(string $username, string $password): void
    {
        $this->sid = null;
        $this->expires = 0;
        if ($username === '' || strlen($username) > 256 || $password === '' || strlen($password) > 4096) {
            throw new RuntimeException('ANDROMEDA_CREDENTIALS_REQUIRED');
        }
        $nonce = random_bytes(32);
        $created = gmdate('Y-m-d\TH:i:s\Z');
        $digest = base64_encode(sha1($nonce . $created . md5($password), true));
        $started = time();
        $reply = $this->send('login', [
            'username' => $username, 'password' => $digest,
            'nonce' => base64_encode($nonce), 'created' => $created,
        ]);
        if (!isset($reply['sid']) || !is_string($reply['sid'])
            || !preg_match('/^[A-Za-z0-9_-]{1,256}$/D', $reply['sid'])) {
            throw new RuntimeException('ANDROMEDA_INVALID_RESPONSE');
        }
        $this->sid = $reply['sid'];
        $this->expires = $started + 3000; // Conservative local expiry; wiki says ~1h.
    }

    /** Read-only dictionaries only. Price/booking and arbitrary parameters are excluded. */
    public function catalog(string $action, array $params = []): array
    {
        $keys = ['townfrom' => [], 'state' => ['TOWNFROMINC'], 'all' => ['TOWNFROMINC', 'STATEINC']];
        if (!array_key_exists($action, $keys)) throw new RuntimeException('ANDROMEDA_ACTION_NOT_ALLOWED');
        $actual = array_keys($params);
        $expected = $keys[$action];
        sort($actual);
        sort($expected);
        if ($actual !== $expected) throw new RuntimeException('ANDROMEDA_INVALID_PARAMS');
        foreach ($params as $value) {
            if (!is_int($value) || $value <= 0) throw new RuntimeException('ANDROMEDA_INVALID_PARAMS');
        }
        if ($this->sid === null || time() >= $this->expires) {
            $this->sid = null;
            throw new RuntimeException('ANDROMEDA_LOGIN_REQUIRED');
        }
        $sid = $this->sid;
        $reply = $this->send($action, ['sid' => $sid] + $params);
        $required = ['townfrom' => ['TOWNFROM'], 'state' => ['STATE'],
            'all' => ['CHECKIN_BEG', 'TOWNTO', 'STARS', 'HOTELS', 'MEAL', 'CURRENCY', 'OPERATORS']];
        $result = [];
        foreach ($required[$action] as $key) {
            if (!isset($reply[$key]) || !is_array($reply[$key])) throw new RuntimeException('ANDROMEDA_INVALID_RESPONSE');
            $result[$key] = $reply[$key];
        }
        // Fail closed if a supplier echo contains the session, including encoded forms.
        $this->rejectSessionEcho($result, $sid);
        foreach ($result as $key => $rows) {
            if ($key !== 'CHECKIN_BEG') $this->validateDictionaryRows($rows);
        }
        return $result;
    }

    private function validateDictionaryRows(array $rows): void
    {
        $seen = [];
        $index = 0;
        foreach ($rows as $key => $row) {
            if ($key !== $index++ || !is_array($row)
                || !isset($row['id'], $row['name'])
                || (!is_int($row['id']) && !is_string($row['id']))
                || !preg_match('/^[1-9][0-9]*$/D', (string) $row['id'])
                || strlen((string) $row['id']) > 32
                || !is_string($row['name']) || trim($row['name']) === '') {
                throw new RuntimeException('ANDROMEDA_INVALID_DICTIONARY');
            }
            // Preserve opaque numeric strings; reject duplicate logical IDs.
            $id = 'id:' . (string) $row['id'];
            if (isset($seen[$id])) throw new RuntimeException('ANDROMEDA_INVALID_DICTIONARY');
            $seen[$id] = true;
        }
    }

    private function rejectSessionEcho(array $value, string $sid): void
    {
        foreach ($value as $key => $item) {
            foreach ([$key, is_string($item) ? $item : ''] as $text) {
                $text = (string) $text;
                for ($i = 0; $i < 8; ++$i) {
                    if (strpos($text, $sid) !== false) throw new RuntimeException('ANDROMEDA_SECRET_ECHO');
                    $next = rawurldecode(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                    if ($next === $text) break;
                    $text = $next;
                    if ($i === 7) throw new RuntimeException('ANDROMEDA_SECRET_ECHO');
                }
            }
            if (is_array($item)) $this->rejectSessionEcho($item, $sid);
        }
    }

    private function send(string $action, array $params): array
    {
        if (!$this->enabled) throw new RuntimeException('ANDROMEDA_DISABLED');
        if ($this->requests >= 4) throw new RuntimeException('ANDROMEDA_REQUEST_BUDGET');
        ++$this->requests;
        $url = self::ENDPOINT . '?' . http_build_query(
            ['version' => '1.01', 'action' => $action] + $params, '', '&', PHP_QUERY_RFC3986);
        try {
            $response = ($this->transport)($url, [
                'method' => 'GET', 'verify_peer' => true, 'verify_host' => 2,
                'follow_redirects' => false, 'timeout' => 20,
                'max_response_bytes' => self::BODY_LIMIT,
            ]);
        } catch (Throwable $ignored) {
            throw new RuntimeException('ANDROMEDA_TRANSPORT_ERROR');
        }
        if (!is_array($response) || !isset($response['status'], $response['body'])
            || !is_int($response['status']) || !is_string($response['body'])) {
            throw new RuntimeException('ANDROMEDA_INVALID_RESPONSE');
        }
        if ($response['status'] !== 200) throw new RuntimeException('ANDROMEDA_HTTP_ERROR');
        if (strlen($response['body']) > self::BODY_LIMIT) throw new RuntimeException('ANDROMEDA_RESPONSE_TOO_LARGE');
        try {
            $reply = json_decode($response['body'], true, 32, JSON_THROW_ON_ERROR);
        } catch (Throwable $ignored) {
            throw new RuntimeException('ANDROMEDA_INVALID_RESPONSE');
        }
        if (!is_array($reply)) throw new RuntimeException('ANDROMEDA_INVALID_RESPONSE');
        if (array_key_exists('error', $reply)) {
            $this->sid = null;
            $this->expires = 0;
            throw new RuntimeException('ANDROMEDA_SUPPLIER_ERROR');
        }
        return $reply;
    }
}
