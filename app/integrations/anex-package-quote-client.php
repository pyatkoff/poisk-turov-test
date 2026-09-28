<?php
declare(strict_types=1);

/** ANEX's temporary package calculation session. No save, tourists, payment or booking API. */
final class AnyTourAnexPackageQuoteClient
{
    private const BASE = 'https://api.anextour.ru';
    private const LIMIT = 2097152;
    private string $token;
    private $transport;
    private array $used = [];
    private array $last = [];

    public function __construct(string $token, ?callable $transport = null)
    {
        $token = trim($token);
        if ($token === '' || strlen($token) > 8192 || preg_match('/[\x00-\x20\x7f]/', $token)) {
            throw new RuntimeException('ANEX_QUOTE_TOKEN_REQUIRED');
        }
        $this->token = $token;
        $this->transport = $transport;
    }

    public function __debugInfo(): array { return ['token' => '[redacted]', 'requests' => count($this->used)]; }
    public function lastRequestDiagnostics(): array { return $this->last; }

    public function start(string $claim, string $id, string $currency): array
    {
        if ($claim === '' || strlen($claim) > 8192 || preg_match('/[\x00-\x20\x7f]/', $claim)
            || !preg_match('/\A[1-9][0-9]{0,8}\z/D', $currency)) {
            throw new InvalidArgumentException('ANEX_QUOTE_INVALID_CONTEXT');
        }
        return $this->request('start', $id, ['cat_claim' => $claim, 'currency' => $currency]);
    }

    public function transports(string $id): array { return $this->request('transports', $id); }

    /** Directions and opaque UIDs must come from this session's retained supplier options. */
    public function select(string $id, array $selection): array
    {
        if (count($selection) !== 2) throw new InvalidArgumentException('ANEX_QUOTE_INVALID_SELECTION');
        $fields = ['recalculate' => 'false'];
        foreach ($selection as $route => $uid) {
            if (!preg_match('/\A[0-9]{1,3}\z/D', (string) $route) || !is_string($uid)
                || $uid === '' || strlen($uid) > 2048 || preg_match('/[\x00-\x20\x7f]/', $uid)) {
                throw new InvalidArgumentException('ANEX_QUOTE_INVALID_SELECTION');
            }
            $fields['transport[' . $route . ']'] = $uid;
        }
        return $this->request('SetTransport', $id, $fields);
    }

    public function calculate(string $id): array { return $this->request('calcfull', $id); }

    private function request(string $stage, string $id, array $fields = []): array
    {
        if (!preg_match('/\A[0-9]{13,17}\z/D', $id)) throw new InvalidArgumentException('ANEX_QUOTE_INVALID_CONTEXT');
        if (isset($this->used[$stage])) throw new RuntimeException('ANEX_QUOTE_REQUEST_LIMIT');
        $this->used[$stage] = true;
        $this->last = ['action' => $stage];
        $fields += ['id' => $id, 'lang' => 'ru'];
        $headers = ['Accept: application/json', 'User-Agent: TourismPlus', 'Authorization: Bearer ' . $this->token];
        if ($this->transport === null) $this->rateSlot();
        try {
            $response = $this->transport !== null
                ? ($this->transport)(self::BASE . '/bron/' . $stage, $fields, $headers)
                : $this->curlRequest(self::BASE . '/bron/' . $stage, $fields, $headers);
        } catch (Throwable $ignored) { throw new RuntimeException('ANEX_QUOTE_TRANSPORT_ERROR'); }
        if (!is_array($response) || !is_int($response['status'] ?? null) || !is_string($response['body'] ?? null)) {
            throw new RuntimeException('ANEX_QUOTE_INVALID_RESPONSE');
        }
        $this->last += ['http_status' => $response['status'], 'response_bytes' => strlen($response['body'])];
        if ($this->transport === null && $response['status'] === 429) $this->rateSlot(true);
        if ($response['status'] !== 200) throw new RuntimeException('ANEX_QUOTE_HTTP_ERROR');
        if (strlen($response['body']) > self::LIMIT) throw new RuntimeException('ANEX_QUOTE_INVALID_RESPONSE');
        try {
            $value = json_decode($response['body'], true, 64, JSON_THROW_ON_ERROR);
            // The official client also accepts a JSON-encoded response string.
            if (is_string($value)) $value = json_decode($value, true, 64, JSON_THROW_ON_ERROR);
        } catch (Throwable $ignored) { throw new RuntimeException('ANEX_QUOTE_INVALID_RESPONSE'); }
        if (!is_array($value)) throw new RuntimeException('ANEX_QUOTE_INVALID_RESPONSE');
        // The official bron client treats code=-1 as rejection, even if a prior
        // claimDocument accompanies it. That document cannot confirm this step.
        if (in_array($value['code'] ?? null, [-1, '-1'], true)) {
            throw new RuntimeException('ANEX_QUOTE_SUPPLIER_REJECTED');
        }
        foreach ([$value, $value['bron'] ?? []] as $node) {
            foreach (['error', 'errors', 'errorCode'] as $field) {
                if (!empty($node[$field])) throw new RuntimeException('ANEX_QUOTE_SUPPLIER_REJECTED');
            }
        }
        if (!is_array($value['bron']['claim']['claimDocument'] ?? null)) {
            throw new RuntimeException('ANEX_QUOTE_INVALID_RESPONSE');
        }
        // Never retain or project a response echoing the credential, including escaped echoes.
        $text = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        for ($i = 0; $i < 8; ++$i) {
            if (strpos($text, $this->token) !== false) throw new RuntimeException('ANEX_QUOTE_INVALID_RESPONSE');
            $decoded = rawurldecode(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($decoded === $text) break;
            $text = $decoded;
        }
        return $value;
    }

    /** Same token-wide pacing file as AdditionalPricesDaily; no retry after a throttle. */
    private function rateSlot(bool $throttled = false): void
    {
        $path = sys_get_temp_dir() . '/anytour-anex-b2b-rate-' . hash('sha256', $this->token) . '.json';
        if (is_link($path)) throw new RuntimeException('ANEX_QUOTE_RATE_LIMIT');
        $file = @fopen($path, 'c+');
        if ($file === false) throw new RuntimeException('ANEX_QUOTE_RATE_LIMIT');
        @chmod($path, 0600);
        $deadline = microtime(true) + 3;
        try {
            do {
                if (!flock($file, LOCK_EX)) throw new RuntimeException('ANEX_QUOTE_RATE_LIMIT');
                rewind($file); $raw = stream_get_contents($file, 2048);
                $state = $raw === '' ? ['next_at' => 0.0, 'blocked_until' => 0.0] : json_decode($raw, true);
                if (!is_array($state) || !isset($state['next_at'], $state['blocked_until'])) throw new RuntimeException('ANEX_QUOTE_RATE_LIMIT');
                $now = microtime(true);
                if ($throttled) $state['blocked_until'] = max((float) $state['blocked_until'], $now + 60);
                elseif ((float) $state['blocked_until'] > $now) throw new RuntimeException('ANEX_QUOTE_RATE_LIMIT');
                elseif ((float) $state['next_at'] > $now) {
                    flock($file, LOCK_UN);
                    if ($now >= $deadline) throw new RuntimeException('ANEX_QUOTE_RATE_LIMIT');
                    usleep((int) ceil(min(1.05, (float) $state['next_at'] - $now) * 1000000));
                    continue;
                } else $state['next_at'] = $now + 1.05;
                $json = json_encode($state, JSON_THROW_ON_ERROR); rewind($file);
                if (!ftruncate($file, 0) || fwrite($file, $json) !== strlen($json) || !fflush($file)) throw new RuntimeException('ANEX_QUOTE_RATE_LIMIT');
                return;
            } while (true);
        } finally { @flock($file, LOCK_UN); fclose($file); }
    }

    private function curlRequest(string $url, array $fields, array $headers): array
    {
        if (!function_exists('curl_init')) throw new RuntimeException('ANEX_QUOTE_TRANSPORT_ERROR');
        $curl = curl_init($url); $body = '';
        if ($curl === false) throw new RuntimeException('ANEX_QUOTE_TRANSPORT_ERROR');
        curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $fields,
            CURLOPT_HTTPHEADER => $headers, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 25,
            CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body): int {
                if (strlen($body) + strlen($chunk) > self::LIMIT) return 0;
                $body .= $chunk; return strlen($chunk);
            }]);
        try {
            $ok = curl_exec($curl); $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            if ($ok === false) throw new RuntimeException('ANEX_QUOTE_TRANSPORT_ERROR');
            return ['status' => $status, 'body' => $body];
        } finally { curl_close($curl); }
    }
}
