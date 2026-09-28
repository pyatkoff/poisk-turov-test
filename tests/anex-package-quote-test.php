<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/integrations/anex-normalizer.php';
require_once __DIR__ . '/../v2/api-anex-search3-preview.php';

// Synthetic contract fixtures, NOT retained supplier/live acceptance evidence.
$checks = 0;
function qp_assert(bool $ok, string $message): void { global $checks; ++$checks; if (!$ok) throw new RuntimeException($message); }
function qp_fixture(string $mode = ''): array
{
    $key = 'anex_online:' . str_repeat('b', 64); $ref = str_repeat('a', 32);
    $offer = ['offer_key' => $key, 'provider' => 'anex', 'supplier_namespace' => 'anex_online', 'kind' => 'concrete',
        'hotel' => ['external_id' => '469', 'local_id' => 245, 'mapping_status' => 'resolved', 'name' => 'Fixture hotel', 'star' => '4'],
        'checkin' => '2026-10-10', 'checkout' => '2026-10-17', 'nights' => 7, 'adults' => 2, 'children' => 0,
        'meal' => 'AI', 'room' => 'STANDARD', 'external_room_id' => '10', 'hotel_place' => 'DBL',
        'price' => ['amount' => '100000', 'currency' => 'RUB'], 'converted_price' => null,
        'availability' => [], 'supplier_booking_flag' => true, 'final_price_verified' => false];
    $known = ['offer_key' => $key, 'kind' => 'concrete', 'hotel_external_id' => '469', 'supplier_offer_id' => 'private-catclaim'];
    $entry = ['offer' => $offer, 'observed_at' => 1001, 'supplier_currency_id' => '3'];
    $state = ['generation' => 7, 'params' => ['countryId' => 4], 'gateway' => [
        'saved_offers' => ['search_ref' => $ref, 'created_at' => 1000, 'expires_at' => 1900, 'offers' => [$key => $entry]],
        'search' => ['offers' => [$known]]]];
    $request = ['action' => 'quote_start', 'generation' => 7, 'search_ref' => $ref, 'offer_ref' => $key, 'local_hotel_id' => 245];
    $facts = (object) ['calls' => [], 'persisted' => [], 'selection' => ['0' => 'private-out', '1' => 'private-back'], 'id' => null];
    $checkpoint = static function (array &$s) use ($facts, $mode): void {
        if ($mode === 'checkpoint') throw new RuntimeException('checkpoint_failed');
        $facts->persisted = unserialize(serialize($s));
    };
    $factory = static function () use ($facts, $mode, $key): AnyTourAnexPackageQuoteClient {
        return new AnyTourAnexPackageQuoteClient('fixture-bearer-secret', static function ($url, $fields, $headers) use ($facts, $mode, $key): array {
            $stage = basename($url); $facts->calls[] = $stage;
            qp_assert(($facts->persisted['package_quotes'][$key]['stages'][$stage] ?? '') === 'unknown', 'reservation before each supplier call');
            qp_assert(strpos($url, 'https://api.anextour.ru/bron/') === 0 && in_array($stage, ['start', 'transports', 'SetTransport', 'calcfull'], true), 'fixed quote-only endpoints');
            qp_assert($fields['lang'] === 'ru' && preg_match('/^[0-9]{13}$/D', $fields['id']) === 1, 'session and language');
            if ($facts->id === null) $facts->id = $fields['id'];
            qp_assert($facts->id === $fields['id'], 'same temporary session');
            if ($stage === 'start') qp_assert($fields['cat_claim'] === 'private-catclaim' && $fields['currency'] === '3', 'retained exact claim and currency');
            if ($stage === 'SetTransport') {
                qp_assert($fields['recalculate'] === 'false' && count($fields) === 5, 'select without extra booking fields');
                $facts->selection = ['0' => $fields['transport[0]'], '1' => $fields['transport[1]']];
            }
            if ($mode === '401') return ['status' => 401, 'body' => '{"message":"private rejection"}'];
            if ($mode === $stage) throw new RuntimeException('private transport failure');
            $leg = static fn($route, $uid): array => ['routeIndex' => (string) $route, 'uid' => $uid,
                'name' => $route ? 'Fixture return' : 'Fixture outward', 'datebeg' => $route ? '2026-10-17' : '2026-10-10'];
            $doc = ['datebeg' => '2026-10-10', 'dateend' => '2026-10-17', 'nights' => '7', 'adult' => '2', 'child' => '0', 'freightExternal' => '2',
                'hotels' => ['hotel' => [['key' => '469', 'roomKey' => '10', 'room' => 'STANDARD', 'meal' => 'AI', 'htplace' => 'DBL']]],
                'transports' => ['transport' => [$leg(0, $facts->selection[0]), $leg(1, $facts->selection[1])]],
                'moneys' => ['money' => [['currency' => 'EUR', 'price' => '1234', 'net' => '1000'], ['currency' => 'RUB', 'price' => '123456.78', 'net' => '100000']]],
                'peoples' => ['people' => [['name' => 'PRIVATE_NAME', 'passport' => 'PRIVATE_PASSPORT']]]];
            if ($mode === 'hotel') $doc['hotels']['hotel'][0]['key'] = '470';
            if ($mode === 'party') $doc['adult'] = '3';
            if ($mode === 'room') $doc['hotels']['hotel'][0]['room'] = 'OTHER';
            if ($mode === 'meal') $doc['hotels']['hotel'][0]['meal'] = 'BB';
            if ($stage === 'calcfull' && $mode === 'changed-flight') $doc['transports']['transport'][0]['uid'] = 'different';
            if ($mode === 'net-only') unset($doc['moneys']['money'][1]['price']);
            if ($mode === 'ambiguous') $doc['moneys']['money'][] = $doc['moneys']['money'][1];
            $body = ['bron' => ['claim' => ['claimDocument' => $doc,
                'variants' => ['transports' => ['transport' => [$leg(0, 'private-alt-out'), $leg(1, 'private-alt-back')]]]]]];
            if ($mode === 'supplier') $body['error'] = 1108;
            if ($mode === 'echo') $body['echo'] = 'fixture-bearer-secret';
            return ['status' => 200, 'body' => json_encode($body, JSON_THROW_ON_ERROR)];
        });
    };
    return [$state, $request, $offer, $known, $entry, $facts, $factory, $checkpoint];
}
function qp_run(array $request, array &$state, callable $factory, callable $checkpoint, int $now = 1100): array
{
    return anytour_anex_search3_followup($request, $state,
        static fn($ns, $id) => $ns === 'anex_online' && $id === '469' ? 245 : null,
        static function () { throw new RuntimeException('parser must not run'); },
        static fn($offers) => [245 => ['id' => 245, 'name' => 'Fixture hotel', 'country_id' => 4, 'country_name' => 'Turkey', 'category' => 4]],
        static fn() => $now, $checkpoint, null, false, $factory);
}
[$state, $req, $offer, $known, $entry, $facts, $factory, $persist] = qp_fixture();
$result = qp_run($req, $state, $factory, $persist);
qp_assert($result['status'] === 'quote_choices' && !$result['final_price_verified'] && count($result['choices']) === 2, 'unpriced paired choices');
$again = qp_run($req, $state, $factory, $persist);
qp_assert($again === $result && $facts->calls === ['start', 'transports'], 'start replay reads retained result only');
$select = array_replace($req, ['action' => 'quote_calculate', 'choice_ref' => $result['choices'][1]['choice_ref']]);
$foreign = array_replace($select, ['choice_ref' => 'anex_quote:' . str_repeat('c', 64)]);
qp_assert(qp_run($foreign, $state, $factory, $persist)['status'] === 'quote_unavailable' && count($facts->calls) === 2, 'foreign pair does not spend budget');
$quote = qp_run($select, $state, $factory, $persist);
qp_assert($quote['status'] === 'quote_verified' && $quote['final_price_verified'] && $quote['price']['amount'] === '123456.78', 'gross supplier total, not net/search/APD arithmetic');
qp_assert($facts->selection === ['private-alt-out', 'private-alt-back'], 'GDS outbound and return stay a supplier pair');
qp_assert($facts->calls === ['start', 'transports', 'SetTransport', 'calcfull'], 'bounded four-call flow');
$state = unserialize(serialize($state));
qp_assert(qp_run($select, $state, $factory, $persist) === $quote && count($facts->calls) === 4, 'completed quote survives restore without replay');
$other = array_replace($select, ['choice_ref' => $result['choices'][0]['choice_ref']]);
qp_assert(qp_run($other, $state, $factory, $persist)['status'] === 'quote_selection_locked' && count($facts->calls) === 4, 'budget cannot be renewed by changing choice');
qp_assert(qp_run($select, $state, $factory, $persist, 1800)['status'] === 'quote_expired', 'quote ttl expires before search ttl');
foreach (['private-catclaim', 'private-alt-out', 'fixture-bearer-secret', 'PRIVATE_NAME', 'PRIVATE_PASSPORT'] as $private) {
    qp_assert(strpos(json_encode($quote), $private) === false, 'no private fields in public quote');
}
qp_assert(strpos(json_encode($state), 'PRIVATE_NAME') === false && strpos(json_encode($state), 'PRIVATE_PASSPORT') === false, 'personal fields never persisted');
foreach (['hotel', 'party', 'room', 'meal', '401', 'supplier', 'echo', 'start', 'transports', 'SetTransport', 'calcfull', 'net-only', 'ambiguous', 'changed-flight'] as $mode) {
    [$s, $r, $o, $k, $e, $f, $fac, $cp] = qp_fixture($mode);
    $failed = qp_run($r, $s, $fac, $cp);
    if ($failed['status'] === 'quote_choices') {
        $r = array_replace($r, ['action' => 'quote_calculate', 'choice_ref' => $failed['choices'][0]['choice_ref']]);
        $failed = qp_run($r, $s, $fac, $cp);
    }
    qp_assert($failed['status'] === 'quote_failed' && !$failed['final_price_verified'] && !isset($failed['price']), 'failure cannot promote price: ' . $mode);
    $count = count($f->calls); $s = unserialize(serialize($s));
    qp_assert(qp_run($r, $s, $fac, $cp) === $failed && count($f->calls) === $count, 'terminal failure not replayed: ' . $mode);
    qp_assert(strpos(json_encode($failed), 'private') === false, 'failure redacted');
}
[$s, $r, $o, $k, $e, $f, $fac, $cp] = qp_fixture('checkpoint');
try { qp_run($r, $s, $fac, $cp); throw new RuntimeException('missing checkpoint failure'); }
catch (RuntimeException $error) { qp_assert($error->getMessage() === 'checkpoint_failed' && $f->calls === [], 'failed persistence prevents transport'); }
[$s, $r, $o, $k, $e, $f, $fac, $cp] = qp_fixture();
$s['package_quotes'][$r['offer_ref']] = ['id' => '1234567890123', 'expires_at' => 1600, 'stages' => ['start' => 'unknown']];
qp_assert(qp_run($r, $s, $fac, $cp)['status'] === 'quote_unknown' && $f->calls === [], 'crash reservation cannot be replayed');
$r['generation'] = 8;
qp_assert(qp_run($r, $s, $fac, $cp)['status'] === 'mismatch' && $f->calls === [], 'search generation binding precedes supplier');
echo "ANEX package quote: {$checks} checks passed (offline contract fixtures; no live supplier calls)\n";
