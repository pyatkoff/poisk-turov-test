<?php
declare(strict_types=1);

require_once __DIR__ . '/andromeda-network-transport-failure.php';

/** Explicit server transport. Merely including this file performs no request. */
final class AnyTourAndromedaTransport
{
    private int $attempts = 0;
    private float $lastStarted = 0.0;
    private $exec;

    /**
     * The optional executor is an offline test seam. Production/default execution uses
     * curl_exec(). The writer is passed only so tests can exercise the oversize guard
     * without opening a network connection.
     */
    public function __construct(private bool $allowPrice = false, private bool $allowPackage = false,
        ?callable $exec = null)
    {
        $this->exec = $exec ?? static fn($handle, $writer) => curl_exec($handle);
    }

    public function __invoke(string $url, array $ignoredOptions = []): array
    {
        if (strpos($url, 'https://gateway.samo.ru/api/?') !== 0
            || strlen($url) > 16384 || preg_match('/[\x00-\x20\x7f#]/', $url)) {
            throw new RuntimeException('ANDROMEDA_ENDPOINT_REJECTED');
        }
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $actions = ['login', 'townfrom', 'state', 'all'];
        if ($this->allowPrice) $actions[] = 'price';
        if ($this->allowPackage) $actions[] = 'broninit';
        if (($query['version'] ?? null) !== '1.01'
            || !in_array($query['action'] ?? null, $actions, true)) {
            throw new RuntimeException('ANDROMEDA_ACTION_NOT_ALLOWED');
        }
        if ($this->attempts >= 4) throw new RuntimeException('ANDROMEDA_REQUEST_BUDGET');
        if (!function_exists('curl_init')) throw new RuntimeException('ANDROMEDA_CURL_REQUIRED');
        $wait = 1.05 - (microtime(true) - $this->lastStarted);
        if ($wait > 0) usleep((int) ceil($wait * 1000000));
        ++$this->attempts;
        $this->lastStarted = microtime(true);
        $handle = curl_init();
        if ($handle === false) throw new RuntimeException('ANDROMEDA_TRANSPORT_ERROR');
        $body = '';
        $oversize = false;
        $writer = static function ($curl, string $chunk) use (&$body, &$oversize): int {
            if (strlen($body) + strlen($chunk) > 2097152) {
                $oversize = true;
                return 0;
            }
            $body .= $chunk;
            return strlen($chunk);
        };
        try {
            $ok = curl_setopt_array($handle, [
                CURLOPT_URL => $url, CURLOPT_HTTPGET => true,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_FOLLOWLOCATION => false, CURLOPT_MAXREDIRS => 0,
                CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 20,
                CURLOPT_VERBOSE => false, CURLOPT_HEADER => false,
                CURLOPT_HTTPHEADER => ['Accept: application/json'],
                CURLOPT_WRITEFUNCTION => $writer,
            ]);
            if (!$ok) throw new RuntimeException('ANDROMEDA_TRANSPORT_ERROR');
            $executed = ($this->exec)($handle, $writer);
            if ($executed === false) {
                if ($oversize) throw new RuntimeException('ANDROMEDA_RESPONSE_TOO_LARGE');
                // Endpoint/action/setup already succeeded and no HTTP response exists.
                // This is the only local transport state eligible for network retry evidence.
                throw new AnyTourAndromedaNetworkTransportFailure('ANDROMEDA_NETWORK_TRANSPORT_FAILURE');
            }
            return ['status' => (int) curl_getinfo($handle, CURLINFO_HTTP_CODE), 'body' => $body];
        } finally {
            curl_close($handle);
        }
    }
}
