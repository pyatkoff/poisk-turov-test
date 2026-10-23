<?php
declare(strict_types=1);
require_once __DIR__ . "/anex-package-quote-handoff.php";

/** Retained ANEX facts only. No supplier, database, browser DTO or lead dependency. */
final class AnyTourSearch3HandoffMissing extends RuntimeException
{
    public function __construct(public readonly string $field, public readonly string $owner)
    { parent::__construct('retained_fact_missing'); }
}

final class AnyTourSearch3MessengerHandoff
{
    public const RECEIVER = 'https://app.anytoour.ru/search3/tour-handoff.php';
    private const MAX_RECORDS = 8;

    private static function missing(string $field, string $owner = 'anex_session'): never
    { throw new AnyTourSearch3HandoffMissing($field, $owner); }

    private static function exact(array $value, array $keys): void
    {
        if (array_is_list($value) || array_diff(array_keys($value), $keys) || array_diff($keys, array_keys($value))) {
            throw new InvalidArgumentException('invalid_request');
        }
    }

    /** Delegate to INT's one retained pair/record owner; do not project or mint another snapshot. */
    private static function canonical(array $selector, array &$session, callable $checkpoint, int $now, callable $clock): array
    {
        $ref = $selector['search_ref'] ?? null;
        if (!is_string($ref) || !preg_match('/\A[a-f0-9]{32}\z/D', $ref)
            || ($session['anex_context_generation'] ?? null) !== ($selector['generation'] ?? null)
            || !is_array($session['anex_offer_contexts'][$ref] ?? null)) self::missing('search.current_context', 'INT');
        $context = $session['anex_offer_contexts'][$ref];
        $persist = static function (array &$state) use (&$session, $ref, $checkpoint): void {
            $session['anex_offer_contexts'][$ref] = $state;
            $session['offer_context'] = $state;
            $checkpoint($session);
            if (($session['anex_offer_contexts'][$ref] ?? null) !== $state
                || ($session['anex_context_generation'] ?? null) !== $state['generation']) throw new RuntimeException('handoff_session_changed');
        };
        $result = anytour_anex_handoff_freeze($selector, $context, $persist, $now, $clock);
        if (($result['status'] ?? null) !== 'ready') {
            $missing = $result['missing'][0] ?? ['field' => 'record.invalid_retained', 'owner' => 'INT'];
            self::missing($missing['field'], $missing['owner']);
        }
        return $result;
    }

    /** Return the canonical server preview. Same record SHA reuses the original wire operation. */
    public static function freeze(array $request, array &$session, int $now, callable $checkpoint, ?callable $clock = null): array
    {
        self::exact($request, ['action', 'generation', 'search_ref', 'offer_ref', 'local_hotel_id', 'choice_ref', 'csrf']);
        if ($request['action'] !== 'handoff_freeze') throw new InvalidArgumentException('invalid_request');
        $selector = [];
        foreach (['generation', 'search_ref', 'offer_ref', 'local_hotel_id', 'choice_ref'] as $field) $selector[$field] = $request[$field];
        $result = self::canonical($selector, $session, $checkpoint, $now, $clock ?? static fn(): int => time());
        $source = $result['record']; $digest = $result['record_sha256'];
        $records = $session['search3_messenger_handoffs'] ?? [];
        if (!is_array($records)) self::missing('handoff.session');
        foreach ($records as $operation => $record) {
            if (($record['record_sha256'] ?? null) === $digest && ($record['selector'] ?? null) === $selector) return self::preview($operation, $record);
        }
        if (count($records) >= self::MAX_RECORDS) throw new RuntimeException('handoff_limit');
        $operation = bin2hex(random_bytes(16));
        $body = ['action' => 'create', 'operation_id' => $operation] + $source;
        $raw = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $record = ['generation' => $selector['generation'], 'selector' => $selector, 'record_sha256' => $digest,
            'raw' => $raw, 'request_hash' => hash('sha256', $raw), 'state' => 'prepared', 'prepared_at' => $now,
            'provenance' => ['owner' => 'package_quotes.pairs.handoff_record',
                'observed_at' => intdiv($source['observation']['capturedAt'], 1000),
                'deadline' => $source['observation']['price']['expiresAt']]];
        $session['search3_messenger_handoffs'][$operation] = $record;
        $checkpoint($session);
        return self::preview($operation, $record);
    }

    private static function preview(string $operation, array $record): array
    {
        return ['operation_id' => $operation, 'state' => $record['state'],
            'observation' => json_decode($record['raw'], true, 32, JSON_THROW_ON_ERROR)['observation']];
    }

    /** Reservation is checkpointed BEFORE the only create attempt; UNKNOWN is status-only. */
    public static function transact(array $request, array &$session, int $now, array $signer, callable $checkpoint, callable $transport, ?callable $clock = null): array
    {
        self::exact($request, ['action', 'operation_id', 'csrf']);
        $operation = $request['operation_id']; $action = $request['action'];
        if (!in_array($action, ['handoff_create', 'handoff_status', 'handoff_revoke'], true)
            || !is_string($operation) || !preg_match('/\A[a-f0-9]{32}\z/D', $operation)) throw new InvalidArgumentException('invalid_request');
        if (!is_array($session['search3_messenger_handoffs'][$operation] ?? null)) throw new RuntimeException('handoff_missing');
        $record =& $session['search3_messenger_handoffs'][$operation];
        $creating = $action === 'handoff_create' && $record['state'] === 'prepared';
        if ($creating && (($session['anex_context_generation'] ?? null) !== $record['generation']
            || $now - $record['prepared_at'] > 900 || $now < $record['prepared_at']
            || $now - $record['provenance']['observed_at'] > 900)) throw new RuntimeException('handoff_stale');
        if ($creating) {
            $selector = $record['selector'];
            $slot = $session['anex_offer_contexts'][$selector['search_ref']]['package_quotes'][$selector['offer_ref']]['pairs'][$selector['choice_ref']]['handoff_record'] ?? null;
            if (!is_array($slot) || ($slot['json_sha256'] ?? null) !== $record['record_sha256']) throw new RuntimeException('handoff_stale');
            $current = self::canonical($selector, $session, $checkpoint, $now, $clock ?? static fn(): int => time());
            $record =& $session['search3_messenger_handoffs'][$operation];
            if ($current['record_sha256'] !== $record['record_sha256']) throw new RuntimeException('handoff_stale');
        }
        if ($record['state'] === 'prepared' && $action === 'handoff_status') return ['operation_id' => $operation, 'state' => 'prepared'];
        if ($record['state'] === 'prepared' && $action === 'handoff_revoke') {
            $record['state'] = 'revoked'; $checkpoint($session);
            return ['operation_id' => $operation, 'state' => 'revoked'];
        }
        $wireAction = $creating ? 'create' : ($action === 'handoff_revoke' ? 'revoke' : 'status');
        $raw = $creating ? $record['raw'] : json_encode(['action' => $wireAction, 'operation_id' => $operation,
            'request_hash' => $record['request_hash']], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $headers = self::headers($raw, $signer, $now); // No reservation when signer is unavailable.
        if (isset($record['producer_key']) && $record['producer_key'] !== $signer['key_id']) throw new RuntimeException('signer_unavailable');
        $record['producer_key'] = $signer['key_id'];
        $record['state'] = $wireAction === 'revoke' ? 'revoke_unknown' : ($creating ? 'unknown' : $record['state']);
        $checkpoint($session);
        $record =& $session['search3_messenger_handoffs'][$operation];
        try { $reply = $transport(self::RECEIVER, $raw, $headers); }
        catch (Throwable $ignored) { return ['operation_id' => $operation, 'state' => $record['state'], 'error' => 'receiver_unknown']; }
        $data = is_array($reply) && ($reply['status'] ?? null) === 200 && is_string($reply['body'] ?? null)
            && strlen($reply['body']) <= 16384 ? json_decode($reply['body'], true, 16) : null;
        if (is_array($data) && $data === ['ok' => true, 'state' => 'missing'] && !$creating) {
            return ['operation_id' => $operation, 'state' => 'missing']; // Read-only; no retry of an UNKNOWN create.
        }
        if (!is_array($data) || ($data['ok'] ?? null) !== true || ($data['request_hash'] ?? null) !== $record['request_hash']
            || !in_array($data['state'] ?? null, ['ready', 'revoked', 'expired', 'unavailable', 'missing'], true)
            || (($wireAction === 'revoke' || $record['state'] === 'revoke_unknown') && $data['state'] === 'ready')) {
            return ['operation_id' => $operation, 'state' => $record['state'], 'error' => 'receiver_unknown'];
        }
        // Never return internal record IDs, raw requests, signing data or receiver diagnostics.
        $safe = ['operation_id' => $operation, 'state' => $data['state']];
        if ($data['state'] === 'ready') {
            foreach (['telegram' => 't.me', 'max' => 'max.ru'] as $platform => $host) {
                $link = $data['links'][$platform] ?? null; $url = is_string($link) ? parse_url($link) : false;
                if (!is_array($url) || ($url['scheme'] ?? null) !== 'https' || ($url['host'] ?? null) !== $host
                    || isset($url['user']) || isset($url['pass']) || isset($url['fragment']) || isset($url['port']) || strlen($link) > 2048) {
                    return ['operation_id' => $operation, 'state' => $record['state'], 'error' => 'receiver_unknown'];
                }
                $safe['links'][$platform] = $link;
            }
        }
        $record['state'] = $data['state'];
        $checkpoint($session);
        return $safe;
    }

    public static function headers(string $raw, array $signer, int $now): array
    {
        $id = $signer['key_id'] ?? null; $secret = $signer['secret_key'] ?? null;
        if (!function_exists('sodium_crypto_sign_detached') || !is_string($id) || !preg_match('/\A[a-z0-9_-]{1,48}\z/D', $id)
            || !is_string($secret) || strlen($secret) !== 64 || !is_int($signer['not_before'] ?? null)
            || !is_int($signer['not_after'] ?? null) || $now < $signer['not_before'] || $now >= $signer['not_after']
            || !preg_match('/\A[1-9][0-9]{9}\z/D', (string)$now)) throw new RuntimeException('signer_unavailable');
        $message = "anytour-search3-handoff-v1\nPOST\n/search3/tour-handoff.php\n" . $now . "\n" . hash('sha256', $raw);
        return ['Content-Type: application/json', 'X-Anytour-Search3-Key: ' . $id, 'X-Anytour-Search3-Timestamp: ' . $now,
            'X-Anytour-Search3-Signature: ' . rtrim(strtr(base64_encode(sodium_crypto_sign_detached($message, $secret)), '+/', '-_'), '=')];
    }

    public static function authorize(array $server, array $request, array $session): bool
    {
        $host = $server['HTTP_HOST'] ?? null; $csrf = $session['search3_handoff_csrf'] ?? null;
        return in_array($host, ['anytoour.ru', 'www.anytoour.ru'], true)
            && ($server['REQUEST_METHOD'] ?? null) === 'POST'
            && ($server['HTTP_ORIGIN'] ?? null) === 'https://' . $host
            && ($server['HTTP_SEC_FETCH_SITE'] ?? null) === 'same-origin'
            && is_string($csrf) && preg_match('/\A[a-f0-9]{64}\z/D', $csrf)
            && is_string($request['csrf'] ?? null) && hash_equals($csrf, $request['csrf']);
    }

    public static function checkpoint(array &$state): void
    {
        $expected = $state;
        if (session_status() !== PHP_SESSION_ACTIVE) throw new RuntimeException('handoff_session_changed');
        $_SESSION = $expected;
        if (!session_write_close() || !session_start() || $_SESSION !== $expected) {
            throw new RuntimeException('handoff_session_changed');
        }
        $state = $_SESSION;
    }

    /** Only the separately provisioned private file outside the web root, never a supplier/bot key. */
    public static function signer(?string $path, string $documentRoot): array
    {
        $root = realpath($documentRoot); $real = is_string($path) ? realpath($path) : false;
        if ($root === false || $root === DIRECTORY_SEPARATOR || $real === false || $real !== $path || !is_file($path)
            || is_link($path) || str_starts_with($real, $root . DIRECTORY_SEPARATOR)
            || (fileperms($path) & 0007) !== 0 || filesize($path) > 2048) throw new RuntimeException('signer_unavailable');
        $data = json_decode((string)file_get_contents($path), true, 8);
        if (!is_array($data) || array_diff(array_keys($data), ['key_id', 'secret_key_base64', 'not_before', 'not_after'])
            || !is_string($data['secret_key_base64'] ?? null)) throw new RuntimeException('signer_unavailable');
        $secret = base64_decode($data['secret_key_base64'], true);
        return ['key_id' => $data['key_id'] ?? null, 'secret_key' => $secret,
            'not_before' => $data['not_before'] ?? null, 'not_after' => $data['not_after'] ?? null];
    }

    public static function httpTransport(string $url, string $raw, array $headers): array
    {
        if ($url !== self::RECEIVER || !function_exists('curl_init')) throw new RuntimeException('receiver_unknown');
        $ch = curl_init(self::RECEIVER); $body = '';
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $raw, CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 8, CURLOPT_FOLLOWLOCATION => false, CURLOPT_MAXREDIRS => 0,
            CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_WRITEFUNCTION => static function ($unused, string $chunk) use (&$body): int {
                if (strlen($body) + strlen($chunk) > 16384) return 0;
                $body .= $chunk; return strlen($chunk);
            }]);
        $ok = curl_exec($ch); $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE); curl_close($ch);
        if ($ok === false) throw new RuntimeException('receiver_unknown');
        return ['status' => $status, 'body' => $body];
    }
}
