<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/integrations/anex-search.php';
require_once __DIR__ . '/../app/integrations/anex-preview-gateway.php';

function bg_check(bool $ok, string $label): void {
    if (!$ok) throw new RuntimeException('FAIL_' . $label);
}

$criteria = [
    'supplier_namespace' => 'anex_online',
    'departure_id' => '1',
    'destination_id' => '4',
    'currency_id' => '1',
    'checkin_begin' => '20261001',
    'checkin_end' => '20261001',
    'nights_from' => 7,
    'nights_till' => 7,
    'adults' => 2,
    'children' => 0,
];

$clock = 1000;
$calls = 0;
$factory = static function () use (&$calls): AnyTourAnexClient {
    return new AnyTourAnexClient('background-gateway-fixture', static function (string $url) use (&$calls): array {
        ++$calls;
        parse_str((string)parse_url($url, PHP_URL_QUERY), $query);
        $action = $query['action'] ?? '';
        return ['status' => 200, 'body' => json_encode([$action => ['prices' => []]], JSON_THROW_ON_ERROR)];
    });
};
$time = static function () use (&$clock): int { return $clock; };

// Default/browser behavior is unchanged: same-second burst is still capped at 10.
$browser = new AnyTourAnexPreviewGateway($factory, null, [], $time);
$browserState = [];
for ($i = 0; $i < 10; ++$i) {
    $browser->handle(['action' => 'search', 'criteria' => $criteria], $browserState);
}
$threw = false;
try {
    $browser->handle(['action' => 'search', 'criteria' => $criteria], $browserState);
} catch (RuntimeException $error) {
    $threw = $error->getMessage() === 'ANEX_RATE_LIMIT';
}
bg_check($threw, 'browser_cap_preserved');
bg_check($calls === 10, 'browser_cap_before_transport');

// Explicit background mode bypasses only the preview/session cap.
$background = new AnyTourAnexPreviewGateway($factory, null, [], $time, false);
$backgroundState = [];
for ($i = 0; $i < 30; ++$i) {
    $background->handle(['action' => 'search', 'criteria' => $criteria], $backgroundState);
}
bg_check($calls === 40, 'background_over_preview_cap');
bg_check(($backgroundState['request_count'] ?? 0) === 0, 'background_does_not_consume_preview_counter');

$gatewaySource = file_get_contents(__DIR__ . '/../app/integrations/anex-preview-gateway.php');
$cliSource = file_get_contents(__DIR__ . '/../scripts/ops/anex_local_offer_collect.php');
$apiSource = file_get_contents(__DIR__ . '/../v2/api-anex-search3-preview.php');
bg_check(is_string($gatewaySource) && str_contains($gatewaySource, 'if ($this->enforcePreviewRateLimit) $this->consumeRequest($session);'),
    'explicit_gateway_switch');
bg_check(is_string($cliSource) && str_contains($cliSource, '$makeAdditional,false'), 'collector_explicit_background_mode');
bg_check(is_string($apiSource) && str_contains($apiSource, 'bool $enforceGatewayRateLimit = true'),
    'http_default_remains_limited');

echo "ANEX_BACKGROUND_GATEWAY_LIMIT_OK browser10=1 background30=1 supplier_pacing_owner_unchanged=1\n";
