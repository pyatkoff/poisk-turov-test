<?php
declare(strict_types=1);
require_once __DIR__ . '/../v2/api-anex-search3-preview.php';
require_once __DIR__ . '/../app/integrations/anex-preview-gateway.php';

$checks = 0;
function hc_check(bool $ok, string $message): void {
    global $checks; ++$checks;
    if (!$ok) throw new RuntimeException($message);
}
function hc_error(callable $run, string $code): void {
    try { $run(); } catch (Throwable $error) {
        hc_check($error->getMessage() === $code, 'unexpected_error_' . $error->getMessage()); return;
    }
    throw new RuntimeException('expected_' . $code);
}
function hc_metadata(array $offers): array {
    return [245 => ['id' => 245, 'name' => 'Fixture hotel', 'country_id' => 4, 'country_name' => 'Turkey',
        'region_id' => 1, 'region_name' => 'Antalya', 'subregion_id' => 2, 'subregion_name' => 'Lara',
        'category' => 5, 'rating' => 4.5]];
}
function hc_fixture(string $mode = 'success'): array {
    $facts = (object) ['calls' => [], 'factories' => 0, 'now' => 1000, 'reserved' => false, 'persisted' => [], 'params' => null];
    $client = new AnyTourAnexClient('http-continuation-fixture', static function (string $url) use ($facts, $mode): array {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $p);
        hc_check(($p['action'] ?? null) === 'SearchTour_PRICES', 'unexpected_action');
        $page = (int) ($p['PRICEPAGE'] ?? 0); $facts->calls[] = $page;
        unset($p['PRICEPAGE']);
        if ($facts->params === null) $facts->params = $p;
        hc_check($p === $facts->params, 'supplier_criteria_changed');
        if ($page > 1) hc_check($facts->reserved, 'supplier_before_checkpoint');
        if ($page === 2 && $mode === 'supplier') return ['status' => 200, 'body' => '{"error":3}'];
        if ($page === 2 && $mode === 'transport') throw new RuntimeException('private_fixture_message');
        $n = $page === 1 ? 300 : ($mode === 'empty' ? 0 : 2);
        $rows = [];
        for ($i = 0; $i < $n; ++$i) {
            $identity = $mode === 'duplicate' && $page === 2 && $i === 0 ? '1-0' : $page . '-' . $i;
            $rows[] = ['id' => 'hc-' . $identity, 'hotelKey' => 469, 'hotel' => 'Fixture hotel',
                'checkIn' => '20261010', 'checkOut' => '20261017', 'nights' => 7, 'adult' => 2, 'child' => 0,
                'packetType' => 0, 'price' => '1234.50', 'currency' => 'EUR', 'convertedPrice' => '100 123.45 RUB',
                'grouped' => 1, 'bron' => 0];
        }
        return ['status' => 200, 'body' => json_encode(['SearchTour_PRICES' => ['prices' => $rows]], JSON_THROW_ON_ERROR)];
    });
    $factory = static function () use ($facts, $client): AnyTourAnexClient { ++$facts->factories; return $client; };
    $resolver = static fn(string $ns, string $id): ?int => $ns === 'anex_online' && $id === '469' ? 245 : null;
    $clock = static fn(): int => $facts->now;
    $criteria = ['supplier_namespace' => 'anex_online', 'departure_id' => '1', 'destination_id' => '2',
        'currency_id' => '3', 'checkin_begin' => '2026-10-10', 'checkin_end' => '2026-10-16',
        'nights_from' => 7, 'nights_till' => 7, 'adults' => 2, 'children' => 0, 'child_ages' => [], 'hotel_ids' => ['469']];
    $gateway = new AnyTourAnexPreviewGateway($factory, $resolver, [], $clock);
    $session = []; $first = $gateway->handle(['action' => 'search', 'criteria' => $criteria], $session);
    $params = ['departureId' => '1', 'countryId' => '4', 'dateFrom' => '2026-10-10', 'dateTo' => '2026-10-16',
        'nightsFrom' => 7, 'nightsTo' => 7, 'adults' => 2, 'childs' => [], 'currency' => 'RUB'];
    $state = ['generation' => 7, 'params' => $params, 'gateway' => $session, 'expansions' => [], 'additional_prices' => []];
    $request = ['action' => 'continue', 'generation' => 7, 'search_ref' => $first['search_ref'], 'page' => 2];
    $checkpoint = static function (array &$state) use ($facts): void {
        $copy = unserialize(serialize($state)); $facts->persisted[] = $copy;
        if (($copy['continuation_attempts'][2] ?? null) === 'reserved') $facts->reserved = true;
    };
    return [$state, $request, $facts, $resolver, $factory, $clock, $checkpoint];
}
foreach (['success', 'empty', 'duplicate'] as $mode) {
    [$state, $request, $facts, $resolver, $factory, $clock, $persist] = hc_fixture($mode); $before = $state;
    hc_check(anytour_anex_search3_continuation_metadata($state) === ['state' => 'available', 'pages_read' => 1, 'next_page' => 2], 'initial_metadata');
    $reply = anytour_anex_search3_continue($request, $state, $resolver, $factory, 'hc_metadata', $clock, $persist);
    hc_check($facts->calls === [1, 2] && $facts->factories === 2, 'one_request_per_click');
    hc_check($reply['page'] === 2 && $reply['pages_read'] === 1 && $reply['first_page_only'] === false, 'page_envelope');
    hc_check($reply['generation'] === 7 && $reply['search_ref'] === $request['search_ref'], 'identity');
    hc_check($reply['date_range'] === ['from' => '2026-10-10', 'to' => '2026-10-16'], 'first_week_preserved');
    hc_check($reply['continuation'] === ['state' => 'exhausted', 'pages_read' => 2, 'next_page' => null], 'completion_metadata');
    $count = array_sum(array_map(static fn(array $h): int => count($h['tours']), $reply['hotels']));
    hc_check($count === ($mode === 'empty' ? 0 : ($mode === 'duplicate' ? 1 : 2)), 'only_new_page_projected');
    hc_check($state['gateway']['saved_offers']['expires_at'] === $before['gateway']['saved_offers']['expires_at'], 'ttl_not_extended');
    foreach ($before['gateway']['saved_offers']['offers'] as $key => $entry) hc_check($state['gateway']['saved_offers']['offers'][$key] === $entry, 'old_offer_changed');
    hc_check(count($facts->persisted) === 2 && end($facts->persisted) === $state, 'success_durable_before_reply');
    hc_check(!str_contains(json_encode($reply), 'page_digests') && !str_contains(json_encode($reply), 'oauth_token'), 'no_private_cursor');
    hc_error(static function () use (&$state, $request, $resolver, $factory, $clock, $persist): void {
        anytour_anex_search3_continue($request, $state, $resolver, $factory, 'hc_metadata', $clock, $persist);
    }, 'ANEX_CONTINUATION_UNAVAILABLE');
    hc_check($facts->calls === [1, 2], 'completed_page_replayed');
}
foreach (['supplier' => 'ANEX_SUPPLIER_ERROR', 'transport' => 'ANEX_TRANSPORT_ERROR', 'projection' => 'fixture_projection_failed'] as $mode => $code) {
    [$state, $request, $facts, $resolver, $factory, $clock, $persist] = hc_fixture($mode); $before = $state;
    $reader = $mode === 'projection' ? static function (array $offers): array { throw new RuntimeException('fixture_projection_failed'); } : 'hc_metadata';
    hc_error(static function () use (&$state, $request, $resolver, $factory, $clock, $persist, $reader): void {
        anytour_anex_search3_continue($request, $state, $resolver, $factory, $reader, $clock, $persist);
    }, $code);
    hc_check($state['gateway']['saved_offers'] === $before['gateway']['saved_offers'], 'error_lost_offers');
    hc_check($state['gateway']['search']['pagination']['state'] === 'blocked', 'error_not_sealed');
    hc_check(end($facts->persisted) === $state, 'error_not_persisted');
    $state = unserialize(serialize($state));
    hc_error(static function () use (&$state, $request, $resolver, $factory, $clock, $persist): void {
        anytour_anex_search3_continue($request, $state, $resolver, $factory, 'hc_metadata', $clock, $persist);
    }, 'ANEX_CONTINUATION_UNAVAILABLE');
    hc_check($facts->calls === [1, 2], 'restored_error_replayed');
}
[$state, $request, $facts, $resolver, $factory, $clock, $persist] = hc_fixture();
$original = $state;
foreach ([['page' => '2'], ['page' => true], ['page' => 1], ['page' => 13], ['generation' => '7'], ['search_ref' => 'bad'], ['params' => []]] as $change) {
    $bad = array_replace($request, $change);
    hc_error(static function () use (&$state, $bad, $resolver, $factory, $clock, $persist): void {
        anytour_anex_search3_continue($bad, $state, $resolver, $factory, 'hc_metadata', $clock, $persist);
    }, 'ANEX_INVALID_REQUEST');
}
foreach ([['generation' => 8], ['search_ref' => str_repeat('0', 32)]] as $change) {
    $bad = array_replace($request, $change);
    hc_error(static function () use (&$state, $bad, $resolver, $factory, $clock, $persist): void {
        anytour_anex_search3_continue($bad, $state, $resolver, $factory, 'hc_metadata', $clock, $persist);
    }, 'ANEX_SESSION_REQUIRED');
}
hc_check($state === $original && $facts->calls === [1], 'invalid_request_mutated_or_requested');
hc_error(static function () use (&$state, $request, $resolver, $factory, $clock): void {
    anytour_anex_search3_continue($request, $state, $resolver, $factory, 'hc_metadata', $clock);
}, 'ANEX_RESERVATION_REQUIRED');
// Persisted reservation surviving a worker death must prevent another supplier call.
$state['continuation_attempts'][2] = 'reserved';
hc_error(static function () use (&$state, $request, $resolver, $factory, $clock, $persist): void {
    anytour_anex_search3_continue($request, $state, $resolver, $factory, 'hc_metadata', $clock, $persist);
}, 'ANEX_CONTINUATION_UNAVAILABLE');
$state = $original; unset($state['gateway']['search']['pagination']);
hc_check(anytour_anex_search3_continuation_metadata($state) === null, 'legacy_cursor_invented');
hc_error(static function () use (&$state, $request, $resolver, $factory, $clock, $persist): void {
    anytour_anex_search3_continue($request, $state, $resolver, $factory, 'hc_metadata', $clock, $persist);
}, 'ANEX_CONTINUATION_UNAVAILABLE');
hc_check($facts->calls === [1], 'legacy_or_reserved_request_replayed');
$state = $original; $facts->now = 1900;
hc_error(static function () use (&$state, $request, $resolver, $factory, $clock, $persist): void {
    anytour_anex_search3_continue($request, $state, $resolver, $factory, 'hc_metadata', $clock, $persist);
}, 'ANEX_SESSION_REQUIRED');
// Two destinations share cookies but never overwrite each other's retained reference.
[$one, $request1] = hc_fixture(); [$two, $request2] = hc_fixture();
$session = ['offer_context' => $one];
anytour_anex_search3_select_context(['action' => 'search', 'generation' => 7], $session, 1000);
$session['offer_context'] = $two; anytour_anex_search3_retain_context($session, 1000);
hc_check(count($session['anex_offer_contexts']) === 2, 'sibling_branch_lost');
anytour_anex_search3_select_context($request1, $session, 1000);
hc_check($session['offer_context'] === $one, 'wrong_branch_selected');
$session['offer_context']['continuation_attempts'][2] = 'reserved';
hc_check(($session['anex_offer_contexts'][$request1['search_ref']]['continuation_attempts'][2] ?? null) === 'reserved', 'branch_alias_not_retained');
anytour_anex_search3_select_context($request2, $session, 1000);
hc_check($session['offer_context'] === $two, 'other_branch_touched');
anytour_anex_search3_select_context(array_replace($request1, ['generation' => 8]), $session, 1000);
hc_check($session['offer_context'] === [], 'stale_generation_borrowed_context');
anytour_anex_search3_select_context(['action' => 'search', 'generation' => 8], $session, 1000);
hc_check($session['anex_offer_contexts'] === [], 'new_generation_kept_old_authority');
// Real local PHP session serialization/reopen, without any server or supplier access.
$dir = sys_get_temp_dir() . '/anex-http-session-' . bin2hex(random_bytes(6));
mkdir($dir, 0700); session_save_path($dir); session_id(bin2hex(random_bytes(16))); session_start();
$_SESSION = ['offer_context' => $one, 'anex_context_generation' => 7, 'anex_offer_contexts' => []];
anytour_anex_search3_retain_context($_SESSION, 1000);
$_SESSION['offer_context']['continuation_attempts'][2] = 'reserved';
anytour_anex_search3_checkpoint($_SESSION['offer_context']);
hc_check($_SESSION['anex_offer_contexts'][$request1['search_ref']] === $_SESSION['offer_context'], 'checkpoint_branch_mismatch');
session_write_close(); session_start(); anytour_anex_search3_select_context($request1, $_SESSION, 1000);
hc_check(($_SESSION['offer_context']['continuation_attempts'][2] ?? null) === 'reserved', 'checkpoint_reservation_lost');
session_destroy(); foreach (glob($dir . '/*') ?: [] as $file) unlink($file); rmdir($dir);
echo 'ANEX_HTTP_CONTINUATION_OK ' . $checks . " checks; real client/normalizer with fixture transport; supplier0/DB0/lead0\n";
