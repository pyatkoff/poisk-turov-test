<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/integrations/anex-preview-gateway.php';

function fg_check(bool $value, string $message): void {
    if (!$value) throw new RuntimeException($message);
}
function fg_error(callable $run, string $expected): void {
    try { $run(); } catch (Throwable $error) {
        fg_check($error->getMessage() === $expected, 'unexpected_error');
        return;
    }
    throw new RuntimeException('missing_expected_failure');
}
function fg_client(array &$calls, int $failPage = 0): AnyTourAnexClient {
    return new AnyTourAnexClient('foreground-fixture-secret', static function (string $url) use (&$calls, $failPage): array {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $params);
        fg_check(($params['action'] ?? '') === 'SearchTour_PRICES', 'unexpected_action');
        $page = (int) ($params['PRICEPAGE'] ?? 0); $calls[] = $page;
        fg_check($page >= 1 && $page <= 2, 'unexpected_page');
        if ($page === $failPage) return ['status' => 200, 'body' => json_encode(['error' => 3])];
        $rows = [];
        for ($i = 0; $i < ($page === 1 ? 300 : 2); ++$i) {
            $rows[] = ['id' => 'foreground-page-' . $page . '-' . $i, 'hotelKey' => 469,
                'hotel' => 'Fixture hotel', 'checkIn' => '20261010', 'checkOut' => '20261017',
                'nights' => 7, 'adult' => 2, 'child' => 0, 'packetType' => 0,
                'price' => '1234.50', 'currency' => 'EUR', 'convertedPrice' => '100 123.45 RUB',
                'grouped' => 1, 'bron' => 0];
        }
        return ['status' => 200, 'body' => json_encode(['SearchTour_PRICES' => ['prices' => $rows]])];
    });
}
$criteria = ['supplier_namespace' => 'anex_online', 'departure_id' => '1', 'destination_id' => '2',
    'currency_id' => '3', 'checkin_begin' => '20261010', 'checkin_end' => '20261016',
    'nights_from' => 7, 'nights_till' => 7, 'adults' => 2, 'children' => 0, 'child_ages' => [],
    'hotel_ids' => ['469']];
$resolver = static fn(string $provider, string $id): ?int => $provider === 'anex_online' && $id === '469' ? 245 : null;

// Same full first page followed by an application-level rejection as the observed failure.
$calls = []; $client = fg_client($calls, 2);
$gateway = new AnyTourAnexPreviewGateway(static fn() => $client, $resolver, [], static fn(): int => 1000);
$session = [];
$data = $gateway->handle(['action' => 'search', 'criteria' => $criteria], $session);
fg_check($calls === [1], 'foreground_attempted_second_price_page');
fg_check(count($data['offers']) === 300 && $data['pages_read'] === 1 && $data['first_page_only'] === true, 'initial_inventory_lost');
fg_check(count($session['search']['offers']) === 300 && count($session['saved_offers']['offers']) === 300, 'initial_context_lost');
fg_check(strlen($data['search_ref']) === 32 && strpos(json_encode($data), 'foreground-fixture-secret') === false, 'unsafe_public_context');
fg_check($data['offers'][0]['final_price_verified'] === false, 'listing_promoted_to_final');

// Explicit background mode keeps pagination and the complete retained offer set.
$calls = []; $client = fg_client($calls);
$background = new AnyTourAnexPreviewGateway(static fn() => $client, $resolver, [], static fn(): int => 1000, false);
$session = [];
$data = $background->handle(['action' => 'search', 'criteria' => $criteria], $session);
fg_check($calls === [1, 2] && count($data['offers']) === 302 && $data['pages_read'] === 2
    && $data['first_page_only'] === false && count($session['saved_offers']['offers']) === 302, 'background_pagination_changed');

// A real background continuation failure is not silently labelled complete.
$calls = []; $client = fg_client($calls, 2);
$background = new AnyTourAnexPreviewGateway(static fn() => $client, $resolver, [], static fn(): int => 1000, false);
$session = [];
fg_error(static function () use ($background, $criteria, &$session): void {
    $background->handle(['action' => 'search', 'criteria' => $criteria], $session);
}, 'ANEX_SUPPLIER_ERROR');
fg_check($calls === [1, 2] && !isset($session['search'], $session['saved_offers']), 'failed_background_retained_selectable_state');

// First-page failures still fail; no fake empty success or automatic replay.
$calls = []; $client = fg_client($calls, 1);
$gateway = new AnyTourAnexPreviewGateway(static fn() => $client, $resolver, [], static fn(): int => 1000);
$session = [];
fg_error(static function () use ($gateway, $criteria, &$session): void {
    $gateway->handle(['action' => 'search', 'criteria' => $criteria], $session);
}, 'ANEX_SUPPLIER_ERROR');
fg_check($calls === [1] && !isset($session['search'], $session['saved_offers']), 'first_page_failure_replayed');

// Invalid internal page policies are rejected before any provider access.
$calls = []; $client = fg_client($calls); $search = new AnyTourAnexSearch($client, $resolver);
foreach ([0, 2, 13] as $limit) fg_error(static function () use ($search, $criteria, $limit): void {
    $search->search($criteria, $limit);
}, 'ANEX_INVALID_PAGE_LIMIT');
fg_check($calls === [], 'invalid_page_limit_reached_supplier');
echo "ANEX_FOREGROUND_INITIAL_PAGE_OK\n";
