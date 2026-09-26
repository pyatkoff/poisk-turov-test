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

// Explicit continuation: exact session and expected page, no new search or date window.
$calls = []; $client = fg_client($calls); $factories = 0; $now = 1000;
$gateway = new AnyTourAnexPreviewGateway(static function () use ($client, &$factories) {
    ++$factories; return $client;
}, $resolver, [], static function () use (&$now): int { return $now; });
$session = [];
$first = $gateway->handle(['action' => 'search', 'criteria' => $criteria], $session);
fg_check($first['continuation'] === ['state' => 'available', 'pages_read' => 1, 'next_page' => 2], 'missing_explicit_cursor');
$before = $session;
$next = ['action' => 'continue', 'search_ref' => $first['search_ref'], 'page' => 2];
foreach ([array_replace($next, ['page' => 3]), array_replace($next, ['page' => 1]),
    array_replace($next, ['page' => '2']), array_replace($next, ['page' => true]),
    array_replace($next, ['search_ref' => str_repeat('0', 32)]), $next + ['criteria' => $criteria]] as $bad) {
    $expected = $bad['page'] === 3 ? 'ANEX_CONTINUATION_UNAVAILABLE'
        : (($bad['search_ref'] ?? '') === str_repeat('0', 32) ? 'ANEX_SESSION_REQUIRED' : 'ANEX_INVALID_REQUEST');
    fg_error(static function () use ($gateway, $bad, &$session): void { $gateway->handle($bad, $session); }, $expected);
}
fg_check($calls === [1] && $factories === 1, 'invalid_continue_reached_client');
$now += 2;
$second = $gateway->handle($next, $session);
fg_check($calls === [1, 2] && count($second['offers']) === 2, 'continue_not_single_page');
fg_check($second['search_ref'] === $first['search_ref'] && $second['page'] === 2
    && $second['continuation'] === ['state' => 'exhausted', 'pages_read' => 2, 'next_page' => null], 'incorrect_continue_context');
fg_check($second['search'] === $first['search'] && count($session['search']['offers']) === 302
    && count($session['saved_offers']['offers']) === 302, 'continue_lost_original_criteria_or_offers');
fg_check($session['saved_offers']['created_at'] === $before['saved_offers']['created_at']
    && $session['saved_offers']['expires_at'] === $before['saved_offers']['expires_at'], 'continue_extended_quote_lifetime');
foreach ($before['saved_offers']['offers'] as $key => $value) fg_check($session['saved_offers']['offers'][$key] === $value, 'continue_repriced_prior_offer');
fg_error(static function () use ($gateway, $next, &$session): void { $gateway->handle($next, $session); }, 'ANEX_CONTINUATION_UNAVAILABLE');
fg_check($calls === [1, 2] && $factories === 2, 'double_click_replayed_page');
fg_check(!str_contains(json_encode($second), 'supplier_offer_id') && !str_contains(json_encode($second), 'page_digests'), 'private_context_leaked');

// A later error seals only continuation, preserving the previous offers and expiry.
$calls = []; $client = fg_client($calls, 2);
$gateway = new AnyTourAnexPreviewGateway(static fn() => $client, $resolver, [], static fn(): int => 1000);
$session = []; $first = $gateway->handle(['action' => 'search', 'criteria' => $criteria], $session);
$before = $session; $next = ['action' => 'continue', 'search_ref' => $first['search_ref'], 'page' => 2];
fg_error(static function () use ($gateway, $next, &$session): void { $gateway->handle($next, $session); }, 'ANEX_SUPPLIER_ERROR');
fg_check($calls === [1, 2] && $session['search']['offers'] === $before['search']['offers']
    && $session['saved_offers'] === $before['saved_offers'] && $session['search']['pagination']['state'] === 'blocked', 'later_failure_lost_inventory');
fg_error(static function () use ($gateway, $next, &$session): void { $gateway->handle($next, $session); }, 'ANEX_CONTINUATION_UNAVAILABLE');
$restored = new AnyTourAnexSearch($client, $resolver); $restored->restore($session['search']);
fg_error(static fn() => $restored->continuePage(2), 'ANEX_CONTINUATION_UNAVAILABLE');
fg_check($calls === [1, 2], 'failed_continue_replayed_after_restore');

// Legacy snapshots can still expand/read offers but cannot guess a next-page cursor.
$legacy = $before['search']; unset($legacy['pagination']);
$restored->restore($legacy);
fg_check(count($restored->snapshot()['offers']) === 300 && $restored->continuationStatus()['state'] === 'unsupported', 'legacy_snapshot_changed');
fg_error(static fn() => $restored->continuePage(2), 'ANEX_CONTINUATION_UNAVAILABLE');
foreach ([null, [], array_replace($before['search']['pagination'], ['pages_read' => '1']),
    array_replace($before['search']['pagination'], ['state' => 'unknown']),
    array_replace($before['search']['pagination'], ['page_digests' => []]),
    array_replace($before['search']['pagination'], ['page_digests' => ['not-a-digest']]),
    array_replace($before['search']['pagination'], ['state' => 'limit'])] as $cursor) {
    $bad = $before['search']; $bad['pagination'] = $cursor;
    fg_error(static fn() => $restored->restore($bad), 'ANEX_INVALID_SESSION');
}
fg_check($calls === [1, 2], 'invalid_snapshot_reached_provider');

// Fixture variants exercise real client/normalizer with no HTTP transport.
function fg_pages_client(array &$calls, string $mode): AnyTourAnexClient {
    $firstParams = null;
    return new AnyTourAnexClient('foreground-fixture-secret', static function (string $url) use (&$calls, &$firstParams, $mode): array {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $params);
        fg_check(($params['action'] ?? '') === 'SearchTour_PRICES', 'unexpected_continue_action');
        $page = (int) $params['PRICEPAGE']; $calls[] = $page;
        unset($params['PRICEPAGE']);
        if ($firstParams === null) $firstParams = $params;
        fg_check($params === $firstParams, 'continuation_changed_supplier_criteria');
        if ($mode === 'transport' && $page === 2) throw new RuntimeException('fixture_transport_failure');
        if ($mode === 'malformed' && $page === 2) return ['status' => 200, 'body' => json_encode(['SearchTour_PRICES' => ['prices' => 'broken']])];
        $count = $page === 1 || $mode === 'limit' || ($mode === 'three' && $page === 2) || $mode === 'repeat' ? 300 : 2;
        if ($mode === 'empty' && $page === 2) $count = 0;
        $rows = [];
        for ($i = 0; $i < $count; ++$i) {
            $identityPage = $mode === 'repeat' || ($mode === 'duplicate' && $page === 2 && $i === 0) ? 1 : $page;
            $rows[] = ['id' => 'continue-page-' . $identityPage . '-' . $i, 'hotelKey' => 469,
                'hotel' => 'Fixture hotel', 'checkIn' => '20261010', 'checkOut' => '20261017',
                'nights' => 7, 'adult' => 2, 'child' => 0, 'packetType' => 0,
                'price' => '1234.50', 'currency' => 'EUR', 'convertedPrice' => '100 123.45 RUB', 'grouped' => 1, 'bron' => 0];
        }
        return ['status' => 200, 'body' => json_encode(['SearchTour_PRICES' => ['prices' => $rows]])];
    });
}
foreach (['three', 'duplicate', 'repeat', 'empty', 'limit', 'transport', 'malformed'] as $mode) {
    $calls = []; $client = fg_pages_client($calls, $mode); $search = new AnyTourAnexSearch($client, $resolver);
    $first = $search->search($criteria, 1); $before = $search->snapshot();
    fg_check($calls === [1], 'mode_automatically_continued');
    $search = new AnyTourAnexSearch($client, $resolver); $search->restore($before);
    if (in_array($mode, ['repeat', 'transport', 'malformed'], true)) {
        $failed = false;
        try { $search->continuePage(2); } catch (Throwable $error) { $failed = true; }
        fg_check($failed && $search->snapshot()['offers'] === $before['offers']
            && $search->continuationStatus()['state'] === 'blocked', 'invalid_page_lost_previous_inventory');
        fg_error(static fn() => $search->continuePage(2), 'ANEX_CONTINUATION_UNAVAILABLE');
        fg_check($calls === [1, 2], 'invalid_page_was_retried');
        continue;
    }
    $second = $search->continuePage(2);
    fg_check($calls === [1, 2] && $second['pages_read'] === 1 && $second['first_page_only'] === false, 'page_two_auto_drained');
    if ($mode === 'three') {
        fg_check(count($second['offers']) === 300 && $second['continuation']['next_page'] === 3, 'third_page_missing_cursor');
        $saved = $search->snapshot(); $search = new AnyTourAnexSearch($client, $resolver); $search->restore($saved);
        $third = $search->continuePage(3);
        fg_check($calls === [1, 2, 3] && count($third['offers']) === 2
            && count($search->snapshot()['offers']) === 602 && $third['continuation']['state'] === 'exhausted', 'third_page_context_lost');
    }
    if ($mode === 'duplicate') fg_check(count($second['offers']) === 1 && $second['deduplicated_count'] === 1
        && count($search->snapshot()['offers']) === 301, 'cross_page_duplicate_returned');
    if ($mode === 'empty') fg_check($second['offers'] === [] && $second['continuation']['state'] === 'exhausted'
        && $search->snapshot()['offers'] === $before['offers'], 'empty_page_erased_results');
    if ($mode === 'limit') {
        for ($page = 3; $page <= 12; ++$page) $search->continuePage($page);
        fg_check($calls === range(1, 12) && count($search->snapshot()['offers']) === 3600
            && $search->continuationStatus() === ['state' => 'limit', 'pages_read' => 12, 'next_page' => null], 'manual_page_limit_not_bounded');
        fg_error(static fn() => $search->continuePage(13), 'ANEX_CONTINUATION_UNAVAILABLE');
    }
}

// Expired references, even after a replacement search, fail before client creation.
$calls = []; $client = fg_client($calls); $now = 1000; $factories = 0;
$gateway = new AnyTourAnexPreviewGateway(static function () use ($client, &$factories) { ++$factories; return $client; },
    $resolver, [], static function () use (&$now): int { return $now; });
$session = []; $first = $gateway->handle(['action' => 'search', 'criteria' => $criteria], $session);
$next = ['action' => 'continue', 'search_ref' => $first['search_ref'], 'page' => 2];
$now = 1900;
fg_error(static function () use ($gateway, $next, &$session): void { $gateway->handle($next, $session); }, 'ANEX_SESSION_REQUIRED');
fg_check($calls === [1] && $factories === 1, 'expired_continue_reached_client');
$now = 1902; $gateway->handle(['action' => 'search', 'criteria' => $criteria], $session);
fg_error(static function () use ($gateway, $next, &$session): void { $gateway->handle($next, $session); }, 'ANEX_SESSION_REQUIRED');
fg_check($calls === [1, 1] && $factories === 2, 'old_generation_continue_reached_client');
echo "ANEX_EXPLICIT_CONTINUATION_OK\n";
