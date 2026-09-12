<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/integrations/anex-additional-prices-client.php';

$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    ++$checks;
    if (!$condition) {
        throw new RuntimeException('ASSERTION_FAILED:' . $message);
    }
};
$expect = static function (string $message, callable $fn) use ($assert): void {
    try {
        $fn();
    } catch (Throwable $e) {
        $assert($e->getMessage() === $message, 'exception:' . $message);
        return;
    }
    throw new RuntimeException('EXPECTED_EXCEPTION:' . $message);
};

$seen = [];
$transport = static function (string $url, array $headers, array $options) use (&$seen): array {
    $seen = compact('url', 'headers', 'options');
    return [
        'status' => 200,
        'body' => json_encode([
            'items' => [
                ['amount' => '1234.50', 'currency' => 3, 'note' => 'supplier-defined'],
            ],
            'page' => 1,
        ], JSON_THROW_ON_ERROR),
    ];
};

$client = new AnyTourAnexAdditionalPricesClient('secret-token', $transport);
$result = $client->additionalPricesDaily([
    'page' => 1,
    'pageSize' => 10,
    'tour' => 778,
    'dateBeg' => '2026-09-20',
    'nights' => 7,
    'currency' => 3,
]);
$assert($client->requestsMade() === 1, 'single request counted');
$assert(($result['items'][0]['amount'] ?? null) === '1234.50', 'payload preserved without money interpretation');
$assert(strpos($seen['url'] ?? '', 'https://api.anextour.ru/b2b/AdditionalPricesDaily?') === 0, 'fixed https endpoint');
$assert(strpos($seen['url'] ?? '', 'tour=778') !== false, 'tour encoded');
$assert(strpos($seen['url'] ?? '', 'dateBeg=2026-09-20') !== false, 'date encoded');
$assert(in_array('User-Agent: TourismPlus', $seen['headers'] ?? [], true), 'required user agent');
$assert(in_array('Authorization: Bearer secret-token', $seen['headers'] ?? [], true), 'bearer header');
$assert(($seen['options']['verify_peer'] ?? null) === true, 'tls peer verify');
$assert(($seen['options']['follow_redirects'] ?? null) === false, 'redirects disabled');
$assert(($client->lastRequestDiagnostics()['action'] ?? null) === 'AdditionalPricesDaily', 'sanitized diagnostics');
$assert(strpos(json_encode($client->lastRequestDiagnostics(), JSON_THROW_ON_ERROR), 'secret-token') === false, 'diagnostics omit token');
$expect('ANEX_B2B_REQUEST_LIMIT', static function () use ($client): void {
    $client->additionalPricesDaily([
        'page' => 1, 'pageSize' => 10, 'tour' => 778,
        'dateBeg' => '2026-09-21', 'nights' => 7, 'currency' => 3,
    ]);
});

$echoClient = new AnyTourAnexAdditionalPricesClient('echo-token', static function (): array {
    return [
        'status' => 200,
        'body' => '{"safe":"ok","echo":"prefix-echo-token-suffix","nested":{"echo":"echo%2Dtoken"}}',
    ];
});
$echo = $echoClient->additionalPricesDaily([
    'page' => 2, 'pageSize' => 1, 'tour' => 1,
    'dateBeg' => '2026-12-31', 'nights' => 60, 'currency' => 1,
]);
$assert(($echo['safe'] ?? null) === 'ok', 'safe supplier field retained');
$assert(array_key_exists('echo', $echo) && $echo['echo'] === null, 'plain token echo redacted');
$assert(isset($echo['nested']) && is_array($echo['nested'])
    && array_key_exists('echo', $echo['nested']) && $echo['nested']['echo'] === null,
    'encoded token echo redacted');

$expect('ANEX_B2B_INVALID_CRITERIA', static function (): void {
    (new AnyTourAnexAdditionalPricesClient('token', static function (): array { return []; }))
        ->additionalPricesDaily([
            'page' => 0, 'pageSize' => 10, 'tour' => 778,
            'dateBeg' => '2026-09-20', 'nights' => 7, 'currency' => 3,
        ]);
});
$expect('ANEX_B2B_INVALID_CRITERIA', static function (): void {
    (new AnyTourAnexAdditionalPricesClient('token', static function (): array { return []; }))
        ->additionalPricesDaily([
            'page' => 1, 'pageSize' => 101, 'tour' => 778,
            'dateBeg' => '2026-09-20', 'nights' => 7, 'currency' => 3,
        ]);
});
$expect('ANEX_B2B_INVALID_CRITERIA', static function (): void {
    (new AnyTourAnexAdditionalPricesClient('token', static function (): array { return []; }))
        ->additionalPricesDaily([
            'page' => 1, 'pageSize' => 10, 'tour' => 778,
            'dateBeg' => '2026-02-30', 'nights' => 7, 'currency' => 3,
        ]);
});
$expect('ANEX_B2B_HTTP_ERROR', static function (): void {
    $client = new AnyTourAnexAdditionalPricesClient('token', static function (): array {
        return ['status' => 401, 'body' => '{}'];
    });
    $client->additionalPricesDaily([
        'page' => 1, 'pageSize' => 10, 'tour' => 778,
        'dateBeg' => '2026-09-20', 'nights' => 7, 'currency' => 3,
    ]);
});
$expect('ANEX_B2B_INVALID_RESPONSE', static function (): void {
    $client = new AnyTourAnexAdditionalPricesClient('token', static function (): array {
        return ['status' => 200, 'body' => 'not-json'];
    });
    $client->additionalPricesDaily([
        'page' => 1, 'pageSize' => 10, 'tour' => 778,
        'dateBeg' => '2026-09-20', 'nights' => 7, 'currency' => 3,
    ]);
});

$expect('ANEX_B2B_TOKEN_REQUIRED', static function (): void {
    new AnyTourAnexAdditionalPricesClient('bad token');
});

fwrite(STDOUT, "ANEX AdditionalPricesDaily client: {$checks} checks passed; network=0.\n");
