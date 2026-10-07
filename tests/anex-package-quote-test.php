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
        'checkin' => '2026-10-10', 'checkout' => '2026-10-17', 'nights' => 7, 'adults' => 2, 'children' => 0, 'infants' => 0,
        'meal' => 'AI', 'room' => 'STANDARD', 'external_room_id' => '10', 'hotel_place' => 'DBL',
        'price' => ['amount' => '100000', 'currency' => 'RUB'], 'converted_price' => null,
        'availability' => [], 'supplier_booking_flag' => true, 'final_price_verified' => false];
    $known = ['offer_key' => $key, 'kind' => 'concrete', 'hotel_external_id' => '469', 'supplier_offer_id' => 'private-catclaim'];
    $entry = ['offer' => $offer, 'observed_at' => 1001, 'supplier_currency_id' => '1'];
    $state = ['generation' => 7, 'params' => ['countryId' => 4], 'gateway' => [
        'saved_offers' => ['search_ref' => $ref, 'created_at' => 1000, 'expires_at' => 1900, 'offers' => [$key => $entry]],
        'search' => ['context' => ['currency_id' => 3], 'offers' => [$known]]]];
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
            if ($mode === 'http-' . $stage) return ['status' => 503, 'body' => 'PRIVATE_HTTP_BODY'];
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
            if ($mode === 'infant-counter') $doc['infant'] = '1';
            if ($mode === 'infant-counter-invalid') $doc['infant'] = 'unknown';
            if ($mode === 'infant-counter-array') $doc['infant'] = ['PRIVATE_INFANT_VALUE'];
            if ($mode === 'infant-counter-null') $doc['infant'] = null;
            if ($mode === 'infant-counter-bool') $doc['infant'] = false;
            if ($mode === 'infant-counter-negative') $doc['infant'] = -1;
            if ($mode === 'infant-final-counter' && $stage === 'calcfull') $doc['infant'] = '1';
            if ($mode === 'infant-final-human' && $stage === 'calcfull') $doc['peoples']['people'][] = ['key' => '3', 'human' => 'INF'];
            if ($mode === 'infant-zero-string') $doc['infant'] = '0';
            if ($mode === 'infant-zero-int') $doc['infant'] = 0;
            if (in_array($mode, ['infant-typed-age', 'infant-typed-human', 'infant-conflicting-counters'], true)) {
                $infant = ['key' => '3'];
                $infant[$mode === 'infant-typed-age' ? 'age' : 'human'] = 'INF';
                $doc['peoples']['people'] = [['key' => '1', 'age' => 'ADL'], ['key' => '2', 'human' => 'ADL'],
                    $infant];
                if ($mode === 'infant-conflicting-counters') $doc['infant'] = '0';
            }
            if ($mode === 'identity-detail') {
                $doc['datebeg'] = '20261010'; $doc['dateend'] = '20261017';
                unset($doc['child']);
                $doc['hotels']['hotel'][0]['meal'] = 'PRIVATE_DIFFERENT_MEAL';
            }
            if (str_starts_with($mode, 'party-')) {
                unset($doc['adult'], $doc['child']);
                $doc['peoples']['people'] = [['key' => '1', 'age' => 'ADL'], ['key' => '2', 'human' => 'ADL']];
                if ($mode === 'party-single-counter') $doc['adult'] = '2';
                if ($mode === 'party-conflict') $doc['adult'] = '3';
                if ($mode === 'party-counter-versus-people') {
                    $doc['adult'] = '2'; $doc['peoples']['people'][] = ['key' => '3', 'age' => 'ADL'];
                }
                if ($mode === 'party-wrong-count') array_pop($doc['peoples']['people']);
                if ($mode === 'party-unknown') $doc['peoples']['people'][1] = ['key' => '2', 'age' => 'UNKNOWN'];
                if ($mode === 'party-infant') $doc['peoples']['people'][1]['human'] = 'INF';
                if ($mode === 'party-conflicting-types') $doc['peoples']['people'][1]['age'] = 'CHD';
                if ($mode === 'party-duplicate') $doc['peoples']['people'][1]['key'] = '1';
                if ($mode === 'party-absent') unset($doc['peoples']);
                if ($mode === 'party-final-mismatch' && $stage === 'calcfull') $doc['peoples']['people'][1]['human'] = 'CHD';
            }
            if ($mode === 'room') $doc['hotels']['hotel'][0]['room'] = 'OTHER';
            if ($mode === 'meal') $doc['hotels']['hotel'][0]['meal'] = 'BB';
            if ($stage === 'calcfull' && $mode === 'changed-flight') $doc['transports']['transport'][0]['uid'] = 'different';
            if ($mode === 'net-only') unset($doc['moneys']['money'][1]['price']);
            if ($mode === 'ambiguous') $doc['moneys']['money'][] = $doc['moneys']['money'][1];
            $body = ['bron' => ['claim' => ['claimDocument' => $doc,
                'variants' => ['transports' => ['transport' => [$leg(0, 'private-alt-out'), $leg(1, 'private-alt-back')]]]]]];
            if ($mode === 'supplier') $body['error'] = 1108;
            if ($mode === 'code-' . $stage || ($mode === 'code-string' && $stage === 'calcfull')) {
                // A failure envelope may still contain the prior package document.
                $body['code'] = $mode === 'code-string' ? '-1' : -1;
                $body['message'] = 'PRIVATE_SUPPLIER_REJECTION';
            }
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
foreach (['hotel', 'party', 'room', 'meal', '401', 'supplier', 'echo', 'start', 'transports', 'SetTransport', 'calcfull', 'net-only', 'ambiguous', 'changed-flight',
    'party-conflict', 'party-counter-versus-people', 'party-wrong-count', 'party-unknown', 'party-infant', 'party-conflicting-types', 'party-duplicate', 'party-absent', 'party-final-mismatch',
    'infant-counter', 'infant-counter-invalid', 'infant-counter-array', 'infant-counter-null', 'infant-counter-bool', 'infant-counter-negative', 'infant-final-counter', 'infant-final-human',
    'infant-typed-age', 'infant-typed-human', 'infant-conflicting-counters'] as $mode) {
    [$s, $r, $o, $k, $e, $f, $fac, $cp] = qp_fixture($mode);
    $failed = qp_run($r, $s, $fac, $cp);
    if ($failed['status'] === 'quote_choices') {
        $r = array_replace($r, ['action' => 'quote_calculate', 'choice_ref' => $failed['choices'][0]['choice_ref']]);
        $failed = qp_run($r, $s, $fac, $cp);
    }
    qp_assert($failed['status'] === 'quote_failed' && !$failed['final_price_verified'] && !isset($failed['price']), 'failure cannot promote price: ' . $mode);
    if (str_starts_with($mode, 'infant-')) {
        $classification = in_array($mode, ['infant-counter-invalid', 'infant-counter-array', 'infant-counter-null', 'infant-counter-bool', 'infant-counter-negative'], true)
            ? 'format' : 'mismatch';
        qp_assert($failed['reason'] === 'ANEX_QUOTE_IDENTITY_UNCONFIRMED'
            && $failed['identity_mismatches'] === ['infants' => $classification]
            && $failed['failure_stage'] === (in_array($mode, ['infant-final-counter', 'infant-final-human'], true) ? 'calcfull' : 'start'),
            'explicit infant cannot be hidden by matching adult/child counters: ' . $mode);
    }
    $count = count($f->calls); $s = unserialize(serialize($s));
    qp_assert(qp_run($r, $s, $fac, $cp) === $failed && count($f->calls) === $count, 'terminal failure not replayed: ' . $mode);
    qp_assert(strpos(json_encode($failed), 'private') === false, 'failure redacted');
    if ($mode === 'infant-counter-array') {
        qp_assert(strpos(json_encode([$failed, $s]), 'PRIVATE_INFANT_VALUE') === false, 'malformed infant value never retained or public');
    }
}
foreach (['party-participants', 'party-single-counter', 'infant-zero-string', 'infant-zero-int'] as $mode) {
    [$s, $r, $o, $k, $e, $f, $fac, $cp] = qp_fixture($mode);
    $choices = qp_run($r, $s, $fac, $cp);
    qp_assert($choices['status'] === 'quote_choices' && !$choices['final_price_verified'], 'compatible party retains choices: ' . $mode);
    $r = array_replace($r, ['action' => 'quote_calculate', 'choice_ref' => $choices['choices'][0]['choice_ref']]);
    $verified = qp_run($r, $s, $fac, $cp);
    qp_assert($verified['status'] === 'quote_verified' && $verified['price']['amount'] === '123456.78'
        && $f->calls === ['start', 'transports', 'SetTransport', 'calcfull'], 'party remains bound through supplier calc: ' . $mode);
}
qp_assert(anytour_anex_quote_participant_counts(['peoples' => ['people' => [['age' => 'ADL'], ['age' => 'CHD']]]]) === ['adults' => 1, 'children' => 1], 'explicit child is not counted as an adult');
qp_assert(anytour_anex_quote_participant_counts(['peoples' => ['people' => ['human' => 'ADL']]]) === ['adults' => 1, 'children' => 0], 'single participant object is supported');
foreach (['start', 'transports', 'SetTransport', 'calcfull', 'string'] as $rejectedStage) {
    [$s, $r, $o, $k, $e, $f, $fac, $cp] = qp_fixture('code-' . $rejectedStage);
    $failed = qp_run($r, $s, $fac, $cp);
    if ($failed['status'] === 'quote_choices') {
        $r = array_replace($r, ['action' => 'quote_calculate', 'choice_ref' => $failed['choices'][0]['choice_ref']]);
        $failed = qp_run($r, $s, $fac, $cp);
    }
    qp_assert($failed['status'] === 'quote_failed' && $failed['reason'] === 'ANEX_QUOTE_SUPPLIER_REJECTED'
        && !$failed['final_price_verified'] && !isset($failed['price']) && !isset($failed['choices']),
        'supplier code -1 overrides an otherwise valid package: ' . $rejectedStage);
    $stages = ['start', 'transports', 'SetTransport', 'calcfull'];
    $last = $rejectedStage === 'string' ? 'calcfull' : $rejectedStage;
    qp_assert($f->calls === array_slice($stages, 0, array_search($last, $stages, true) + 1),
        'no supplier stage after rejection: ' . $rejectedStage);
    $calls = $f->calls; $s = unserialize(serialize($s));
    qp_assert(qp_run($r, $s, $fac, $cp) === $failed && $f->calls === $calls, 'failure remains terminal after restore');
    qp_assert(strpos(json_encode([$failed, $s]), 'PRIVATE_SUPPLIER_REJECTION') === false, 'rejection text not retained or public');
}
foreach (['start', 'transports', 'SetTransport', 'calcfull'] as $failedStage) {
    [$s, $r, $o, $k, $e, $f, $fac, $cp] = qp_fixture('http-' . $failedStage);
    $failed = qp_run($r, $s, $fac, $cp);
    if ($failed['status'] === 'quote_choices') {
        $r = array_replace($r, ['action' => 'quote_calculate', 'choice_ref' => $failed['choices'][0]['choice_ref']]);
        $failed = qp_run($r, $s, $fac, $cp);
    }
    qp_assert($failed['reason'] === 'ANEX_QUOTE_HTTP_ERROR' && $failed['failure_stage'] === $failedStage
        && $failed['supplier_http_status'] === 503 && !$failed['final_price_verified'] && !isset($failed['price']),
        'ordinary failure distinguishes its exact HTTP stage: ' . $failedStage);
    $calls = $f->calls;
    qp_assert(end($calls) === $failedStage && qp_run($r, $s, $fac, $cp) === $failed && $f->calls === $calls,
        'HTTP failure remains terminal without a new diagnostic request');
    qp_assert(strpos(json_encode([$failed, $s]), 'PRIVATE_HTTP_BODY') === false, 'no HTTP body retained or projected');
}
[$s, $r, $o, $k, $e, $f, $fac, $cp] = qp_fixture('checkpoint');
[$detailState, $detailRequest, , , , $detailFacts, $detailFactory, $detailPersist] = qp_fixture('identity-detail');
$detailFailure = qp_run($detailRequest, $detailState, $detailFactory, $detailPersist);
qp_assert($detailFailure['status'] === 'quote_failed' && $detailFailure['reason'] === 'ANEX_QUOTE_IDENTITY_UNCONFIRMED'
    && $detailFailure['failure_stage'] === 'start'
    && $detailFailure['identity_mismatches'] === ['checkin' => 'format', 'checkout' => 'format', 'children' => 'missing', 'meal' => 'mismatch']
    && !$detailFailure['final_price_verified'], 'fixed field classifications do not relax identity or expose values');
qp_assert($detailFacts->calls === ['start'] && qp_run($detailRequest, $detailState, $detailFactory, $detailPersist) === $detailFailure
    && $detailFacts->calls === ['start'], 'identity diagnostic remains terminal with no new request');
qp_assert(strpos(json_encode([$detailFailure, $detailState]), 'PRIVATE_DIFFERENT_MEAL') === false, 'mismatched supplier values never retained');
qp_assert(anytour_anex_quote_identity_date('20260230') === null && anytour_anex_quote_identity_date('2026-02-28') === '2026-02-28', 'date classification rejects invalid calendar dates');
try { qp_run($r, $s, $fac, $cp); throw new RuntimeException('missing checkpoint failure'); }
catch (RuntimeException $error) { qp_assert($error->getMessage() === 'checkpoint_failed' && $f->calls === [], 'failed persistence prevents transport'); }
[$s, $r, $o, $k, $e, $f, $fac, $cp] = qp_fixture();
$s['package_quotes'][$r['offer_ref']] = ['id' => '1234567890123', 'expires_at' => 1600, 'stages' => ['start' => 'unknown']];
qp_assert(qp_run($r, $s, $fac, $cp)['status'] === 'quote_unknown' && $f->calls === [], 'crash reservation cannot be replayed');
$r['generation'] = 8;
qp_assert(qp_run($r, $s, $fac, $cp)['status'] === 'mismatch' && $f->calls === [], 'search generation binding precedes supplier');
[$s, $r, $o, $k, $e, $f, $fac, $cp] = qp_fixture('401');
qp_run($r, $s, $fac, $cp);
$session = ['offer_context' => $s, 'unrelated_secret' => 'PRIVATE_SESSION_DATA'];
$session['offer_context']['package_quotes'][$r['offer_ref']]['diagnostics']['start']['raw'] = 'PRIVATE_SUPPLIER_BODY';
$before = serialize($session); $calls = count($f->calls);
$receipt = anytour_anex_search3_quote_receipt($session);
qp_assert($receipt === ['retained_only' => true, 'supplier_calls' => 0, 'records' => [[
    'status' => 'quote_failed', 'reason' => 'ANEX_QUOTE_HTTP_ERROR',
    'stages' => [['stage' => 'start', 'state' => 'unknown', 'http_status' => 401, 'response_bytes' => 31]],
]]], 'retained receipt proves original stage and HTTP without raw response');
qp_assert(anytour_anex_search3_quote_receipt($session) === $receipt && serialize($session) === $before
    && count($f->calls) === $calls, 'repeat receipt read neither changes session nor invokes supplier');
foreach (['PRIVATE', 'private-catclaim', $r['offer_ref'], $s['package_quotes'][$r['offer_ref']]['id']] as $private) {
    qp_assert(strpos(json_encode($receipt), $private) === false, 'retained receipt redacts private context');
}
$bad = ['public' => ['status' => 'PRIVATE_STATUS', 'reason' => 'PRIVATE_REASON'],
    'stages' => ['start' => 'complete', 'private-stage' => 'complete'],
    'diagnostics' => ['start' => ['http_status' => 'PRIVATE', 'response_bytes' => -1]]];
$safe = anytour_anex_search3_quote_receipt(['offer_context' => ['package_quotes' => array_fill(0, 50, $bad)]]);
qp_assert(count($safe['records']) === 40 && $safe['records'][0] === ['status' => 'quote_unknown', 'reason' => null,
    'stages' => [['stage' => 'start', 'state' => 'complete']]], 'bounded allowlist handles malformed retained values');
qp_assert(anytour_anex_search3_quote_receipt([])['records'] === [], 'missing session never triggers a fresh quote');
echo "ANEX package quote: {$checks} checks passed (offline contract fixtures; no live supplier calls)\n";
