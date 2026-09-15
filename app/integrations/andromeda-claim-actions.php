<?php
declare(strict_types=1);

/** Fixed-message supplier rejection with bounded, non-raw diagnostic facts. */
final class AnyTourAndromedaSupplierException extends RuntimeException
{
    public function __construct(private array $diagnosticFacts)
    {
        parent::__construct('ANDROMEDA_SUPPLIER_ERROR');
    }

    public function diagnosticFacts(): array
    {
        return $this->diagnosticFacts;
    }
}

/**
 * Bounded POST-only Andromeda claim actions used after broninit.
 * No booking action exists in this class.
 */
final class AnyTourAndromedaClaimActions
{
    private int $attempts = 0;
    private float $lastStarted = 0.0;
    private $reserve;
    private $request;

    public function __construct(private string $sid, callable $reserve, ?callable $request = null)
    {
        if (!preg_match('/^[A-Za-z0-9_-]{1,256}$/D', $sid)) {
            throw new InvalidArgumentException('ANDROMEDA_CLAIM_SESSION_INVALID');
        }
        $this->reserve = $reserve;
        $this->request = $request;
    }

    public function getFlights(array $claim): array
    {
        return $this->post('get_flights', $claim, []);
    }

    public function changeService(array $claim, string $newUid, ?string $oldUid = null): array
    {
        if (!preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $newUid)
            || ($oldUid !== null && !preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $oldUid))) {
            throw new InvalidArgumentException('ANDROMEDA_SERVICE_UID_INVALID');
        }
        $params = ['NEW_UID' => $newUid];
        if ($oldUid !== null) $params['OLD_UID'] = $oldUid;
        return $this->post('changeservice', $claim, $params);
    }

    public function calc(array $claim): array
    {
        return $this->post('calc', $claim, []);
    }

    private function post(string $action, array $claim, array $params): array
    {
        if (!in_array($action, ['get_flights', 'changeservice', 'calc'], true)) {
            throw new RuntimeException('ANDROMEDA_CLAIM_ACTION_NOT_ALLOWED');
        }
        if ($this->attempts >= 4) throw new RuntimeException('ANDROMEDA_CLAIM_REQUEST_BUDGET');
        if (!isset($claim['claimDocument']) || !is_array($claim['claimDocument'])
            || array_keys($claim['claimDocument']) !== [0] || !is_array($claim['claimDocument'][0])) {
            throw new RuntimeException('ANDROMEDA_CLAIM_SHAPE_INVALID');
        }
        $json = json_encode($claim, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        if (strlen($json) > 2097152) throw new RuntimeException('ANDROMEDA_CLAIM_TOO_LARGE');

        ($this->reserve)();
        $wait = 1.05 - (microtime(true) - $this->lastStarted);
        if ($this->request === null && $this->attempts > 0 && $wait > 0) usleep((int)ceil($wait * 1000000));
        ++$this->attempts;
        $this->lastStarted = microtime(true);

        $url = 'https://gateway.samo.ru/api/?' . http_build_query(
            ['version' => '1.01', 'action' => $action, 'sid' => $this->sid] + $params,
            '', '&', PHP_QUERY_RFC3986
        );
        $post = http_build_query(['claim' => $json], '', '&', PHP_QUERY_RFC3986);
        $response = $this->request !== null
            ? ($this->request)($url, $post)
            : $this->curl($url, $post);
        if (!is_array($response) || !is_int($response['status'] ?? null) || !is_string($response['body'] ?? null)) {
            throw new RuntimeException('ANDROMEDA_INVALID_RESPONSE');
        }
        if ($response['status'] !== 200) throw new RuntimeException('ANDROMEDA_HTTP_ERROR');
        if (strlen($response['body']) > 2097152) throw new RuntimeException('ANDROMEDA_RESPONSE_TOO_LARGE');
        $reply = json_decode($response['body'], true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($reply)) throw new RuntimeException('ANDROMEDA_INVALID_RESPONSE');
        $this->rejectSessionEcho($reply);
        if (array_key_exists('error', $reply)) {
            throw new AnyTourAndromedaSupplierException(self::supplierErrorFacts($reply['error']));
        }
        if (!isset($reply['claimDocument']) || !is_array($reply['claimDocument'])
            || array_keys($reply['claimDocument']) !== [0] || !is_array($reply['claimDocument'][0])) {
            throw new RuntimeException('ANDROMEDA_INVALID_CLAIM_RESPONSE');
        }
        return $reply;
    }

    /**
     * Classify a supplier rejection without retaining raw supplier text.
     * The facts are diagnostic-only and never participate in money arithmetic.
     */
    private static function supplierErrorFacts(mixed $error): array
    {
        $encoded = json_encode($error, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $facts = [
            'source' => 'andromeda_claim_error',
            'shape' => match (true) {
                is_array($error) => 'array',
                is_string($error) => 'string',
                is_int($error) => 'integer',
                is_float($error) => 'float',
                is_bool($error) => 'boolean',
                $error === null => 'null',
                default => 'other',
            },
            'error_sha256' => hash('sha256', $encoded),
            'reason_category' => self::supplierErrorCategory($error),
        ];

        $code = null;
        $codeKey = null;
        if (is_int($error)) {
            $code = (string)$error;
            $codeKey = 'error';
        } elseif (is_string($error) && preg_match('/^[A-Za-z0-9_.:-]{1,64}$/D', $error) === 1) {
            $code = $error;
            $codeKey = 'error';
        } elseif (is_array($error)) {
            foreach (['code', 'errorCode', 'error_code', 'status', 'type'] as $key) {
                if (!array_key_exists($key, $error)) continue;
                $value = $error[$key];
                if (is_int($value)) $value = (string)$value;
                if (is_string($value) && preg_match('/^[A-Za-z0-9_.:-]{1,64}$/D', $value) === 1) {
                    $code = $value;
                    $codeKey = $key;
                    break;
                }
            }
        }
        if ($code !== null) {
            $facts['code'] = $code;
            $facts['code_field'] = $codeKey;
        }
        return $facts;
    }

    private static function supplierErrorCategory(mixed $error): string
    {
        $rules = [
            'flight_or_freight' => '/(?:flight|freight|avia|airline|airfare|рейс|перел[её]т|авиа)/iu',
            'auth_or_session' => '/(?:auth|login|credential|session|password|sid|авториз|логин|сесс)/iu',
            'claim_or_package' => '/(?:claim|package|booking|заявк|пакет|брони)/iu',
            'service' => '/(?:service|услуг)/iu',
            'price_or_fare' => '/(?:price|cost|fare|tariff|цен|тариф)/iu',
            'date_or_time' => '/(?:date|time|дата|время)/iu',
            'hotel' => '/(?:hotel|отел)/iu',
        ];
        foreach ($rules as $category => $pattern) {
            if (self::supplierErrorContains($error, $pattern, 0)) return $category;
        }
        return 'unclassified';
    }

    private static function supplierErrorContains(mixed $value, string $pattern, int $depth): bool
    {
        if ($depth > 3) return false;
        if (is_string($value)) return preg_match($pattern, $value) === 1;
        if (!is_array($value)) return false;
        $seen = 0;
        foreach ($value as $key => $item) {
            if (++$seen > 24) break;
            if (is_string($key) && preg_match($pattern, $key) === 1) return true;
            if (self::supplierErrorContains($item, $pattern, $depth + 1)) return true;
        }
        return false;
    }

    private function curl(string $url, string $post): array
    {
        if (!function_exists('curl_init')) throw new RuntimeException('ANDROMEDA_CURL_REQUIRED');
        $handle = curl_init();
        if ($handle === false) throw new RuntimeException('ANDROMEDA_TRANSPORT_ERROR');
        $body = '';
        $oversize = false;
        try {
            $ok = curl_setopt_array($handle, [
                CURLOPT_URL => $url, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $post,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_FOLLOWLOCATION => false, CURLOPT_MAXREDIRS => 0,
                CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 30,
                CURLOPT_VERBOSE => false, CURLOPT_HEADER => false,
                CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/x-www-form-urlencoded'],
                CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$body, &$oversize): int {
                    if (strlen($body) + strlen($chunk) > 2097152) { $oversize = true; return 0; }
                    $body .= $chunk; return strlen($chunk);
                },
            ]);
            if (!$ok) throw new RuntimeException('ANDROMEDA_TRANSPORT_ERROR');
            if (curl_exec($handle) === false) {
                if ($oversize) throw new RuntimeException('ANDROMEDA_RESPONSE_TOO_LARGE');
                throw new RuntimeException('ANDROMEDA_TRANSPORT_ERROR');
            }
            return ['status' => (int)curl_getinfo($handle, CURLINFO_HTTP_CODE), 'body' => $body];
        } finally { curl_close($handle); }
    }

    private function rejectSessionEcho(array $value): void
    {
        foreach ($value as $key => $item) {
            foreach ([(string)$key, is_string($item) ? $item : ''] as $text) {
                for ($i = 0; $i < 8; ++$i) {
                    if ($text !== '' && strpos($text, $this->sid) !== false) {
                        throw new RuntimeException('ANDROMEDA_SECRET_ECHO');
                    }
                    $next = rawurldecode(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                    if ($next === $text) break;
                    $text = $next;
                }
            }
            if (is_array($item)) $this->rejectSessionEcho($item);
        }
    }
}
