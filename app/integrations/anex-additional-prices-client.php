<?php
declare(strict_types=1);

/**
 * Read-only client for the ANEX B2B AdditionalPricesDaily endpoint.
 *
 * This client deliberately does not interpret the supplier response as fuel,
 * package total, or final price. The response contract must be verified from a
 * real bounded specimen before any field is projected into canonical money facts.
 */
final class AnyTourAnexAdditionalPricesClient
{
    private const ENDPOINT = 'https://api.anextour.ru/b2b/AdditionalPricesDaily';
    private const BODY_LIMIT = 524288;

    /** @var string */
    private $token;
    /** @var callable|null */
    private $transport;
    /** @var int */
    private $requests = 0;
    /** @var array<string,int|string> */
    private $lastRequest = [];

    public function __construct(string $bearerToken, ?callable $transport = null)
    {
        $bearerToken = trim($bearerToken);
        if ($bearerToken === '' || strlen($bearerToken) > 8192 || preg_match('/[\x00-\x20\x7f]/', $bearerToken)) {
            throw new RuntimeException('ANEX_B2B_TOKEN_REQUIRED');
        }
        $this->token = $bearerToken;
        $this->transport = $transport;
    }

    public function __debugInfo(): array
    {
        return ['token' => '[redacted]', 'requests' => $this->requests];
    }

    public function requestsMade(): int
    {
        return $this->requests;
    }

    /** Fixed metadata only; never URL, headers, token, or supplier response text. */
    public function lastRequestDiagnostics(): array
    {
        return $this->lastRequest;
    }

    /**
     * Returns the sanitized decoded supplier payload without assigning money semantics.
     * Exactly one request is allowed per client instance and there are no retries.
     */
    public function additionalPricesDaily(array $criteria): array
    {
        if ($this->requests !== 0) {
            throw new RuntimeException('ANEX_B2B_REQUEST_LIMIT');
        }
        $criteria = $this->validateCriteria($criteria);
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

        $this->lastRequest = [
            'action' => 'AdditionalPricesDaily',
            'page' => $criteria['page'],
            'page_size' => $criteria['pageSize'],
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

    private function redactPayload(array $payload): array
    {
        $clean = [];
        foreach ($payload as $key => $value) {
            if (is_string($key) && $this->containsToken($key)) {
                continue;
            }
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
        return true;
    }

    private function curlRequest(string $url, array $headers): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('ANEX_B2B_TRANSPORT_ERROR');
        }
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('ANEX_B2B_TRANSPORT_ERROR');
        }
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
            if ($ok === false || $tooLarge) {
                throw new RuntimeException('ANEX_B2B_TRANSPORT_ERROR');
            }
            return ['status' => $status, 'body' => $body];
        } finally {
            curl_close($ch);
        }
    }
}
