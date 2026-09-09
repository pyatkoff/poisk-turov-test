<?php
declare(strict_types=1);

/** Explicitly constructed server-side transport. Inclusion never opens a connection. */
final class AnyTourAndromedaTransport
{
    private $attempts = 0;
    private $lastStarted = 0.0;

    public function __invoke(string $url, array $ignoredOptions = []): array
    {
        // Exact origin/path prefix excludes userinfo, alternate ports and lookalike hosts.
        if (strpos($url, 'https://gateway.samo.ru/api/?') !== 0
            || strlen($url) > 16384 || preg_match('/[\x00-\x20\x7f#]/', $url)) {
            throw new RuntimeException('ANDROMEDA_ENDPOINT_REJECTED');
        }
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        if (($query['version'] ?? null) !== '1.01'
            || !in_array($query['action'] ?? null, ['login', 'townfrom', 'state', 'all'], true)) {
            throw new RuntimeException('ANDROMEDA_ACTION_NOT_ALLOWED');
        }
        if ($this->attempts >= 4) throw new RuntimeException('ANDROMEDA_REQUEST_BUDGET');
        if (!function_exists('curl_init')) throw new RuntimeException('ANDROMEDA_CURL_REQUIRED');
        // Conservative pilot spacing only; not a claim about account-wide supplier limits.
        $wait = 1.05 - (microtime(true) - $this->lastStarted);
        if ($wait > 0) usleep((int) ceil($wait * 1000000));
        ++$this->attempts;
        $this->lastStarted = microtime(true);
        $handle = curl_init();
        if ($handle === false) throw new RuntimeException('ANDROMEDA_TRANSPORT_ERROR');
        $body = '';
        $oversize = false;
        try {
            $ok = curl_setopt_array($handle, [
                CURLOPT_URL => $url, CURLOPT_HTTPGET => true,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_FOLLOWLOCATION => false, CURLOPT_MAXREDIRS => 0,
                CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 20,
                CURLOPT_VERBOSE => false, CURLOPT_HEADER => false,
                CURLOPT_HTTPHEADER => ['Accept: application/json'],
                CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$body, &$oversize): int {
                    if (strlen($body) + strlen($chunk) > 2097152) {
                        $oversize = true;
                        return 0;
                    }
                    $body .= $chunk;
                    return strlen($chunk);
                },
            ]);
            if (!$ok || curl_exec($handle) === false) {
                throw new RuntimeException($oversize ? 'ANDROMEDA_RESPONSE_TOO_LARGE' : 'ANDROMEDA_TRANSPORT_ERROR');
            }
            return ['status' => (int) curl_getinfo($handle, CURLINFO_HTTP_CODE), 'body' => $body];
        } finally {
            curl_close($handle);
        }
    }
}
