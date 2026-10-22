<?php
declare(strict_types=1);
// Standalone endpoint import must load its GET receipt dependencies itself.
require_once __DIR__ . '/../v2/api-anex-search3-preview.php';

// Synthetic contract fixtures, NOT retained supplier/live acceptance evidence.
$checks = 0;
function qp_assert(bool $ok, string $message): void { global $checks; ++$checks; if (!$ok) throw new RuntimeException($message); }
qp_assert(function_exists('anytour_anex_normalizer_context'), 'standalone receipt import loads the normalizer without a POST bootstrap');
function qp_fixture(string $mode = '', ?array $familyAges = null): array
{
    $key = 'anex_online:' . str_repeat('b', 64); $ref = str_repeat('a', 32);
    $offer = ['offer_key' => $key, 'provider' => 'anex', 'supplier_namespace' => 'anex_online', 'kind' => 'concrete',
        'hotel' => ['external_id' => '469', 'local_id' => 245, 'mapping_status' => 'resolved', 'name' => 'Fixture hotel', 'star' => '4'],
        'checkin' => '2026-10-10', 'checkout' => '2026-10-17', 'nights' => 7, 'adults' => 2, 'children' => 0, 'infants' => 0,
        'meal' => 'AI', 'room' => 'STANDARD', 'external_room_id' => '10', 'hotel_place' => 'DBL',
        'price' => ['amount' => '100000', 'currency' => 'RUB'], 'converted_price' => null,
        'availability' => [], 'supplier_booking_flag' => true, 'final_price_verified' => false];
    if ($familyAges !== null) {
        $offer['children'] = count($familyAges);
        $offer['infants'] = count(array_filter($familyAges, static fn($age) => $age < 2));
    }
    $known = ['offer_key' => $key, 'kind' => 'concrete', 'hotel_external_id' => '469', 'supplier_offer_id' => 'private-catclaim'];
    $entry = ['offer' => $offer, 'observed_at' => 1001, 'supplier_currency_id' => '1'];
    $state = ['generation' => 7, 'params' => ['countryId' => 4], 'gateway' => [
        'saved_offers' => ['search_ref' => $ref, 'created_at' => 1000, 'expires_at' => 1900, 'offers' => [$key => $entry]],
        'search' => ['context' => ['currency_id' => 3], 'offers' => [$known]]]];
    if ($familyAges !== null) $state['gateway']['search']['context']['child_ages'] = $familyAges;
    $request = ['action' => 'quote_start', 'generation' => 7, 'search_ref' => $ref, 'offer_ref' => $key, 'local_hotel_id' => 245];
    $facts = (object) ['calls' => [], 'persisted' => [], 'snapshots' => [], 'checkpoints' => 0, 'factory_calls' => 0,
        'selection' => ['0' => 'private-out', '1' => 'private-back'], 'id' => null, 'mode' => $mode,
        'multiple' => $mode === 'multiple', 'before_checkpoint' => null, 'after_checkpoint' => null, 'after_http' => null,
        'family_ages' => $familyAges, 'transform_doc' => null];
    $checkpoint = static function (array &$s) use ($facts): void {
        ++$facts->checkpoints;
        if ($facts->mode === 'checkpoint') throw new RuntimeException('checkpoint_failed');
        if (is_callable($facts->before_checkpoint)) ($facts->before_checkpoint)($s);
        $facts->persisted = unserialize(serialize($s));
        $facts->snapshots[] = $facts->persisted;
        if (is_callable($facts->after_checkpoint)) ($facts->after_checkpoint)($s);
    };
    $factory = static function () use ($facts, $key): AnyTourAnexPackageQuoteClient {
        ++$facts->factory_calls;
        return new AnyTourAnexPackageQuoteClient('fixture-bearer-secret', static function ($url, $fields, $headers) use ($facts, $key): array {
            $mode = $facts->mode;
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
            if ($facts->family_ages !== null) {
                $infants = count(array_filter($facts->family_ages, static fn($age) => $age < 2));
                $doc['child'] = (string) (count($facts->family_ages) - $infants);
                $doc['infant'] = (string) $infants;
                $people = [['key' => '1', 'age' => 'ADL'], ['key' => '2', 'age' => 'ADL']];
                foreach ($facts->family_ages as $age) {
                    $people[] = ['key' => (string) (count($people) + 1), 'age' => $age < 2 ? 'INF' : 'CHD',
                        'name' => 'PRIVATE_INFANT_NAME', 'born' => 'PRIVATE_INFANT_BIRTH'];
                }
                $doc['peoples']['people'] = $people;
            }
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
            if ($mode === 'non-rub') $doc['moneys']['money'][1]['currency'] = 'USD';
            if ($mode === 'ambiguous') $doc['moneys']['money'][] = $doc['moneys']['money'][1];
            $variants = [$leg(0, 'private-alt-out'), $leg(1, 'private-alt-back')];
            if ($facts->multiple) {
                $variants = array_merge($variants, [$leg(0, 'private-third-out'), $leg(1, 'private-third-back'),
                    $leg(0, 'private-fourth-out'), $leg(1, 'private-fourth-back')]);
                $totals = ['private-out' => '123456.78', 'private-alt-out' => '234567.89',
                    'private-third-out' => '345678.90', 'private-fourth-out' => '456789.01'];
                $doc['moneys']['money'][1]['price'] = $totals[$facts->selection[0]];
            }
            if (is_callable($facts->transform_doc)) $doc = ($facts->transform_doc)($doc, $stage);
            $body = ['bron' => ['claim' => ['claimDocument' => $doc,
                'variants' => ['transports' => ['transport' => $variants]]]]];
            if ($mode === 'supplier') $body['error'] = 1108;
            if ($mode === 'code-' . $stage || ($mode === 'code-string' && $stage === 'calcfull')) {
                // A failure envelope may still contain the prior package document.
                $body['code'] = $mode === 'code-string' ? '-1' : -1;
                $body['message'] = 'PRIVATE_SUPPLIER_REJECTION';
            }
            if ($mode === 'echo') $body['echo'] = 'fixture-bearer-secret';
            if (is_callable($facts->after_http)) ($facts->after_http)($stage);
            return ['status' => 200, 'body' => json_encode($body, JSON_THROW_ON_ERROR)];
        });
    };
    return [$state, $request, $offer, $known, $entry, $facts, $factory, $checkpoint];
}
function qp_run(array $request, array &$state, callable $factory, callable $checkpoint, int $now = 1100, ?callable $clock = null): array
{
    return anytour_anex_search3_followup($request, $state,
        static fn($ns, $id) => $ns === 'anex_online' && $id === '469' ? 245 : null,
        static function () { throw new RuntimeException('parser must not run'); },
        static fn($offers) => [245 => ['id' => 245, 'name' => 'Fixture hotel', 'country_id' => 4, 'country_name' => 'Turkey', 'category' => 4]],
        $clock ?? static fn() => $now, $checkpoint, null, false, $factory);
}
function qp_tuple(array $quote): array
{
    unset($quote['repricing'], $quote['choices']);
    return $quote;
}
function qp_no_work(array $request, array &$state, int $now = 1100): array
{
    return qp_run($request, $state,
        static function () { throw new RuntimeException('read must not construct a quote client'); },
        static function () { throw new RuntimeException('read must not checkpoint'); }, $now);
}
function qp_unverified(array $result, string $message): void
{
    qp_assert(($result['final_price_verified'] ?? false) === false && !isset($result['price']), $message);
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
$otherQuote = qp_run($other, $state, $factory, $persist);
qp_assert($otherQuote['status'] === 'quote_verified' && count($facts->calls) === 6,
    'new versioned attempt can calculate a second retained pair');
qp_assert($otherQuote['repricing'] === ['enabled' => true, 'max_pairs' => 3, 'used_pairs' => 2, 'remaining_pairs' => 1],
    'second pair consumes one of three distinct pair decisions');
qp_assert(qp_run($select, $state, $factory, $persist, 1800)['status'] === 'quote_expired', 'quote ttl expires before search ttl');
foreach (['private-catclaim', 'private-alt-out', 'fixture-bearer-secret', 'PRIVATE_NAME', 'PRIVATE_PASSPORT'] as $private) {
    qp_assert(strpos(json_encode($quote), $private) === false, 'no private fields in public quote');
}
qp_assert(strpos(json_encode($state), 'PRIVATE_NAME') === false && strpos(json_encode($state), 'PRIVATE_PASSPORT') === false, 'personal fields never persisted');

// Different exact supplier totals make a stale or misbound A/B/A cache observable.
[$multiState, $multiRequest, , , , $multiFacts, $multiFactory, $multiPersist] = qp_fixture('multiple');
$inventory = qp_run($multiRequest, $multiState, $multiFactory, $multiPersist);
$multiKey = $multiRequest['offer_ref'];
qp_assert(count($inventory['choices']) === 4 && $inventory['repricing'] === [
    'enabled' => true, 'max_pairs' => 3, 'used_pairs' => 0, 'remaining_pairs' => 3], 'all retained GDS pairs begin unpriced');
qp_assert($multiState['package_quotes'][$multiKey]['version'] === 2
    && $multiState['package_quotes'][$multiKey]['reserved_calls'] === 2, 'new context records its version and initial two calls');
$pairRequests = [];
foreach ($inventory['choices'] as $choice) $pairRequests[] = array_replace($multiRequest,
    ['action' => 'quote_calculate', 'choice_ref' => $choice['choice_ref']]);
$quoteA = qp_run($pairRequests[0], $multiState, $multiFactory, $multiPersist, 1101);
$quoteB = qp_run($pairRequests[1], $multiState, $multiFactory, $multiPersist, 1110);
qp_assert($quoteA['price'] === ['amount' => '123456.78', 'currency' => 'RUB', 'basis' => 'supplier_gross_package']
    && $quoteB['price'] === ['amount' => '234567.89', 'currency' => 'RUB', 'basis' => 'supplier_gross_package'],
    'each calculated pair retains its exact gross RUB total');
qp_assert($quoteA['choice']['choice_ref'] === $pairRequests[0]['choice_ref']
    && $quoteB['choice']['choice_ref'] === $pairRequests[1]['choice_ref']
    && $quoteA['offer_ref'] === $multiRequest['offer_ref'] && $quoteA['search_ref'] === $multiRequest['search_ref']
    && $quoteA['generation'] === 7, 'verified money binds pair, offer, search and generation');
$multiBeforeCache = serialize($multiState);
$cachedA = qp_no_work($pairRequests[0], $multiState, 1120);
qp_assert(qp_tuple($cachedA) === qp_tuple($quoteA) && $cachedA['repricing'] === [
    'enabled' => true, 'max_pairs' => 3, 'used_pairs' => 2, 'remaining_pairs' => 1],
    'A/B/A returns immutable A evidence with current capability counts');
qp_assert(serialize($multiState) === $multiBeforeCache && $multiFacts->calls === [
    'start', 'transports', 'SetTransport', 'calcfull', 'SetTransport', 'calcfull']
    && $multiFacts->factory_calls === 3 && $multiState['package_quotes'][$multiKey]['reserved_calls'] === 6,
    'A/B/A spends two pair calculations and no cache factory, checkpoint, stage or budget write');
$head = qp_no_work($multiRequest, $multiState, 1121);
qp_assert(qp_tuple($head) === qp_tuple($quoteB) && $head['choices'] === $inventory['choices']
    && serialize($multiState) === $multiBeforeCache, 'cached A leaves healthy quote_start on supplier head B');
$quoteC = qp_run($pairRequests[2], $multiState, $multiFactory, $multiPersist, 1130);
qp_assert($quoteC['price']['amount'] === '345678.90' && $quoteC['choice']['choice_ref'] === $pairRequests[2]['choice_ref']
    && $quoteC['repricing'] === ['enabled' => true, 'max_pairs' => 3, 'used_pairs' => 3, 'remaining_pairs' => 0]
    && $multiState['package_quotes'][$multiKey]['reserved_calls'] === 8 && count($multiFacts->calls) === 8,
    'A/B/A/C reaches three distinct decisions and the eight-call ceiling');
$multiAtCap = serialize($multiState);
$lockedD = qp_no_work($pairRequests[3], $multiState, 1140);
qp_assert($lockedD['status'] === 'quote_selection_locked' && $lockedD['repricing']['remaining_pairs'] === 0,
    'uncached fourth pair is disabled at the retained ceiling');
qp_unverified($lockedD, 'cap response cannot promote an uncalculated pair');
$cachedAtCap = qp_no_work($pairRequests[0], $multiState, 1141);
qp_assert(qp_tuple($cachedAtCap) === qp_tuple($quoteA) && $cachedAtCap['repricing']['used_pairs'] === 3
    && serialize($multiState) === $multiAtCap && count($multiFacts->calls) === 8,
    'cap leaves earlier verified caches readable without resetting the budget or supplier head');
$expiredCache = qp_no_work($pairRequests[0], $multiState, $quoteA['expires_at']);
qp_assert($expiredCache['status'] === 'quote_expired' && $expiredCache['repricing']['remaining_pairs'] === 0
    && serialize($multiState) === $multiAtCap, 'cached quote expires at the original context boundary');
qp_unverified($expiredCache, 'expired cache cannot return a verified price');

// Changes beneath the same retained identifiers cannot reuse a previously verified tuple.
foreach (['currency', 'party', 'child-ages', 'supplier-claim'] as $bindingChange) {
    $changed = unserialize($multiBeforeCache);
    if ($bindingChange === 'currency') $changed['gateway']['search']['context']['currency_id'] = 4;
    if ($bindingChange === 'party') $changed['gateway']['saved_offers']['offers'][$multiKey]['offer']['adults'] = 3;
    if ($bindingChange === 'child-ages') $changed['gateway']['search']['context']['child_ages'] = [5];
    if ($bindingChange === 'supplier-claim') $changed['gateway']['search']['offers'][0]['supplier_offer_id'] = 'private-other-claim';
    $beforeChangeRead = serialize($changed);
    foreach ([$multiRequest, $pairRequests[0], $pairRequests[2]] as $changedRequest) {
        $changedResult = qp_no_work($changedRequest, $changed);
        qp_assert($changedResult['status'] === 'quote_unknown' && $changedResult['repricing']['remaining_pairs'] === 0,
            'context binding blocks changed ' . $bindingChange);
        qp_unverified($changedResult, 'changed binding cannot promote retained money: ' . $bindingChange);
    }
    qp_assert(serialize($changed) === $beforeChangeRead, 'binding mismatch reads do not rewrite retained state: ' . $bindingChange);
}

// UNKNOWN is global, even when an older pair still contains a complete public quote.
foreach (['start', 'transports', 'SetTransport', 'calcfull'] as $unknownStage) {
    $unknown = unserialize($multiBeforeCache);
    $unknown['package_quotes'][$multiKey]['stages'][$unknownStage] = 'unknown';
    foreach ([$multiRequest, $pairRequests[0], $pairRequests[2]] as $unknownRequest) {
        $unknownResult = qp_no_work($unknownRequest, $unknown);
        qp_assert($unknownResult['status'] === 'quote_unknown' && $unknownResult['repricing']['remaining_pairs'] === 0,
            'retained top-level UNKNOWN seals every path: ' . $unknownStage);
        qp_unverified($unknownResult, 'UNKNOWN cannot promote older cached evidence: ' . $unknownStage);
    }
}
$unknownSnapshots = 0; $betweenStages = null;
foreach ($multiFacts->snapshots as $snapshot) {
    $retained = $snapshot['package_quotes'][$multiKey];
    if (($retained['active_pair'] ?? null) === $pairRequests[0]['choice_ref']
        && ($retained['pairs'][$pairRequests[0]['choice_ref']]['stages']['SetTransport'] ?? null) === 'complete'
        && !isset($retained['pairs'][$pairRequests[0]['choice_ref']]['stages']['calcfull'])) $betweenStages = $snapshot;
    if (($retained['active_pair'] ?? null) === null && !in_array('unknown', $retained['stages'], true)) continue;
    ++$unknownSnapshots;
    $restored = unserialize(serialize($snapshot));
    $beforeRestoreRead = serialize($restored);
    $restoreResult = qp_no_work($multiRequest, $restored);
    qp_assert($restoreResult['status'] === 'quote_unknown' && serialize($restored) === $beforeRestoreRead,
        'each durable pending checkpoint restores as a read-only sealed context');
    qp_unverified($restoreResult, 'restored pending context cannot promote price');
    if (isset($retained['choices'])) {
        foreach ([$pairRequests[0], $pairRequests[3]] as $restoreRequest) {
            qp_unverified(qp_no_work($restoreRequest, $restored), 'restored pending context cannot resume or switch pair');
        }
    }
}
qp_assert($unknownSnapshots >= 8 && is_array($betweenStages), 'fixtures captured every HTTP reservation and between-stage active marker');
$partial = $betweenStages;
$partial['package_quotes'][$multiKey]['active_pair'] = null;
foreach ([$multiRequest, $pairRequests[0], $pairRequests[1]] as $partialRequest) {
    $partialResult = qp_no_work($partialRequest, $partial);
    qp_assert($partialResult['status'] === 'quote_unknown', 'partial pair remains sealed even without an active marker');
    qp_unverified($partialResult, 'partial pair cannot manufacture completion from a cleared marker');
}
$sealed = unserialize($multiBeforeCache);
$sealed['package_quotes'][$multiKey]['sealed'] = true;
qp_unverified(qp_no_work($pairRequests[0], $sealed), 'explicit seal blocks an otherwise complete older cache');

// Unversioned sessions retain the previous one-pair contract without acquiring new capacity.
[$legacyState, $legacyRequest, , , , $legacyFacts, $legacyFactory, $legacyPersist] = qp_fixture();
$legacyChoices = qp_run($legacyRequest, $legacyState, $legacyFactory, $legacyPersist);
$legacyUnchosen = unserialize(serialize($legacyState));
$legacySelect = array_replace($legacyRequest, ['action' => 'quote_calculate', 'choice_ref' => $legacyChoices['choices'][0]['choice_ref']]);
$legacyQuote = qp_run($legacySelect, $legacyState, $legacyFactory, $legacyPersist);
$legacyKey = $legacyRequest['offer_ref'];
foreach (['version', 'context_digest', 'reserved_calls', 'pairs', 'active_pair', 'sealed'] as $field) {
    unset($legacyState['package_quotes'][$legacyKey][$field]);
}
$legacyState['package_quotes'][$legacyKey]['chosen'] = $legacySelect['choice_ref'];
$legacyBefore = serialize($legacyState);
$legacyCached = qp_no_work($legacySelect, $legacyState);
qp_assert(qp_tuple($legacyCached) === qp_tuple($legacyQuote) && !isset($legacyCached['repricing'])
    && serialize($legacyState) === $legacyBefore, 'legacy completed quote remains readable without version promotion');
$legacyOther = array_replace($legacySelect, ['choice_ref' => $legacyChoices['choices'][1]['choice_ref']]);
qp_assert(qp_no_work($legacyOther, $legacyState)['status'] === 'quote_selection_locked'
    && serialize($legacyState) === $legacyBefore, 'legacy chosen pair still locks all other pairs');
foreach (['version', 'context_digest', 'reserved_calls', 'pairs', 'active_pair', 'sealed'] as $field) {
    unset($legacyUnchosen['package_quotes'][$legacyKey][$field]);
}
$legacyUnchosenBefore = serialize($legacyUnchosen);
$legacyRetainedChoices = qp_no_work($legacyRequest, $legacyUnchosen);
qp_assert($legacyRetainedChoices['status'] === 'quote_choices' && !isset($legacyRetainedChoices['repricing'])
    && serialize($legacyUnchosen) === $legacyUnchosenBefore, 'legacy unchosen inventory remains readable without version promotion');
$legacyCallsBefore = count($legacyFacts->calls);
$legacyFirst = qp_run($legacySelect, $legacyUnchosen, $legacyFactory, $legacyPersist);
qp_assert(qp_tuple($legacyFirst) === qp_tuple($legacyQuote) && count($legacyFacts->calls) === $legacyCallsBefore + 2
    && !array_key_exists('version', $legacyUnchosen['package_quotes'][$legacyKey])
    && !isset($legacyFirst['repricing']), 'legacy unchosen attempt retains its one first calculation without acquiring v2 capacity');
$legacyFirstBefore = serialize($legacyUnchosen);
qp_assert(qp_no_work($legacyOther, $legacyUnchosen)['status'] === 'quote_selection_locked'
    && serialize($legacyUnchosen) === $legacyFirstBefore, 'legacy first calculation locks a second pair without replay or migration');
$legacyUnknown = $legacyState;
unset($legacyUnknown['package_quotes'][$legacyKey]['public']);
$legacyUnknown['package_quotes'][$legacyKey]['stages']['calcfull'] = 'unknown';
qp_assert(qp_no_work($legacySelect, $legacyUnknown)['status'] === 'quote_unknown', 'legacy unknown chosen attempt never replays');
foreach ([1, '2', null, []] as $badVersion) {
    $badVersionState = unserialize($multiBeforeCache);
    $badVersionState['package_quotes'][$multiKey]['version'] = $badVersion;
    $badVersionResult = qp_no_work($pairRequests[0], $badVersionState);
    qp_assert($badVersionResult['status'] === 'quote_unknown', 'explicit invalid version cannot fall back to legacy or v2 semantics');
    qp_unverified($badVersionResult, 'invalid version cannot promote a cached quote');
}

foreach (['hotel', 'party', 'room', 'meal', '401', 'supplier', 'echo', 'start', 'transports', 'SetTransport', 'calcfull', 'net-only', 'non-rub', 'ambiguous', 'changed-flight',
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
    if ($mode === 'non-rub') {
        qp_assert($failed['reason'] === 'ANEX_QUOTE_PRICE_UNCONFIRMED' && $failed['failure_stage'] === 'calcfull'
            && $f->calls === ['start', 'transports', 'SetTransport', 'calcfull'],
            'foreign-currency gross rows never become a verified RUB package total');
    }
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
catch (RuntimeException $error) { qp_assert($error->getMessage() === 'checkpoint_failed' && $f->calls === []
    && $f->checkpoints === 1 && $f->factory_calls === 0, 'failed persistence prevents transport and a second checkpoint'); }
[$s, $r, $o, $k, $e, $f, $fac, $cp] = qp_fixture();
$s['package_quotes'][$r['offer_ref']] = ['id' => '1234567890123', 'expires_at' => 1600, 'stages' => ['start' => 'unknown']];
qp_assert(qp_run($r, $s, $fac, $cp)['status'] === 'quote_unknown' && $f->calls === [], 'crash reservation cannot be replayed');
$r['generation'] = 8;
qp_assert(qp_run($r, $s, $fac, $cp)['status'] === 'mismatch' && $f->calls === [], 'search generation binding precedes supplier');

// A nested reader cannot take ownership of the outer operation's mutable context.
[$nestedState, $nestedRequest, , , , $nestedFacts, $nestedFactory, $nestedPersist] = qp_fixture('multiple');
$nestedChoices = qp_run($nestedRequest, $nestedState, $nestedFactory, $nestedPersist);
$nestedPairs = [];
foreach ($nestedChoices['choices'] as $choice) $nestedPairs[] = array_replace($nestedRequest,
    ['action' => 'quote_calculate', 'choice_ref' => $choice['choice_ref']]);
qp_run($nestedPairs[0], $nestedState, $nestedFactory, $nestedPersist);
$nestedResults = []; $nestedReadOnly = [];
$nestedFacts->after_http = static function ($stage) use (&$nestedState, $nestedRequest, $nestedPairs,
    &$nestedResults, &$nestedReadOnly): void {
    if ($stage !== 'SetTransport') return;
    foreach ([$nestedRequest, $nestedPairs[0], $nestedPairs[2]] as $readRequest) {
        $beforeRead = serialize($nestedState);
        $nestedResults[] = qp_no_work($readRequest, $nestedState);
        $nestedReadOnly[] = serialize($nestedState) === $beforeRead;
    }
};
$nestedB = qp_run($nestedPairs[1], $nestedState, $nestedFactory, $nestedPersist);
qp_assert(count($nestedResults) === 3 && !in_array(false, $nestedReadOnly, true)
    && $nestedB['status'] === 'quote_verified' && count($nestedFacts->calls) === 6,
    'nested reads spend no budget and leave the outer second-pair operation able to finish');
foreach ($nestedResults as $nestedResult) {
    qp_assert($nestedResult['status'] === 'quote_unknown' && $nestedResult['repricing']['remaining_pairs'] === 0,
        'in-flight nested readers receive a disabled unknown response');
    qp_unverified($nestedResult, 'nested reader cannot promote a historical cache during mutation');
}

// A rejected session CAS escapes without retrying persistence or replacing its newer state.
foreach (['initial-reservation', 'after-SetTransport'] as $casBoundary) {
    [$casState, $casRequest, , , , $casFacts, $casFactory, $casPersist] = qp_fixture();
    $casKey = $casRequest['offer_ref'];
    if ($casBoundary === 'after-SetTransport') {
        $casChoices = qp_run($casRequest, $casState, $casFactory, $casPersist);
        $casRequest = array_replace($casRequest,
            ['action' => 'quote_calculate', 'choice_ref' => $casChoices['choices'][0]['choice_ref']]);
    }
    $casExpected = null;
    $casFacts->before_checkpoint = static function (array &$changing) use ($casBoundary, $casKey, &$casExpected): void {
        if ($casBoundary === 'after-SetTransport'
            && ($changing['package_quotes'][$casKey]['stages']['SetTransport'] ?? null) !== 'complete') return;
        $changing['package_quotes'][$casKey]['public'] = ['status' => 'EXTERNAL_OWNER'];
        $changing['generation'] = 8;
        $casExpected = serialize($changing);
        throw new RuntimeException('ANEX_SESSION_CHANGED');
    };
    $casCallsBefore = count($casFacts->calls); $casCheckpointsBefore = $casFacts->checkpoints;
    try { qp_run($casRequest, $casState, $casFactory, $casPersist); throw new RuntimeException('missing CAS failure'); }
    catch (RuntimeException $error) {
        qp_assert($error->getMessage() === 'ANEX_SESSION_CHANGED' && serialize($casState) === $casExpected,
            'CAS rejection preserves the externally changed state: ' . $casBoundary);
    }
    qp_assert(count($casFacts->calls) === $casCallsBefore + ($casBoundary === 'after-SetTransport' ? 1 : 0)
        && $casFacts->checkpoints === $casCheckpointsBefore + ($casBoundary === 'after-SetTransport' ? 2 : 1),
        'CAS rejection performs no retry checkpoint or following supplier stage: ' . $casBoundary);
}

// A failed final write must not leak in-memory verification through the later session close.
[$finalState, $finalRequest, , , , $finalFacts, $finalFactory, $finalPersist] = qp_fixture('multiple');
$finalChoices = qp_run($finalRequest, $finalState, $finalFactory, $finalPersist);
$finalA = array_replace($finalRequest, ['action' => 'quote_calculate', 'choice_ref' => $finalChoices['choices'][0]['choice_ref']]);
$finalB = array_replace($finalA, ['choice_ref' => $finalChoices['choices'][1]['choice_ref']]);
qp_run($finalA, $finalState, $finalFactory, $finalPersist);
$finalKey = $finalRequest['offer_ref']; $finalRef = $finalB['choice_ref'];
$finalFacts->before_checkpoint = static function (array &$pending) use ($finalKey, $finalRef): void {
    if (($pending['package_quotes'][$finalKey]['pairs'][$finalRef]['public']['status'] ?? null) === 'quote_verified') {
        throw new RuntimeException('final_checkpoint_failed');
    }
};
$finalCPBefore = $finalFacts->checkpoints; $finalHTTPBefore = count($finalFacts->calls);
try { qp_run($finalB, $finalState, $finalFactory, $finalPersist); throw new RuntimeException('missing final checkpoint failure'); }
catch (RuntimeException $error) { qp_assert($error->getMessage() === 'final_checkpoint_failed', 'final checkpoint failure escapes without success'); }
$finalAttempt = $finalState['package_quotes'][$finalKey];
qp_assert($finalFacts->checkpoints === $finalCPBefore + 4 && count($finalFacts->calls) === $finalHTTPBefore + 2
    && $finalAttempt['sealed'] === true && $finalAttempt['active_pair'] === $finalRef
    && $finalAttempt['pairs'][$finalRef]['stages']['calcfull'] === 'unknown'
    && ($finalAttempt['public']['final_price_verified'] ?? false) === false
    && ($finalAttempt['pairs'][$finalRef]['public']['final_price_verified'] ?? false) === false,
    'failed final write restores the pending reservation and seals the in-memory context without a second write');
foreach ([$finalState, $finalFacts->persisted] as $finalRestoreSource) {
    $finalRestored = unserialize(serialize($finalRestoreSource));
    foreach ([$finalRequest, $finalA, $finalB] as $finalReadRequest) {
        qp_unverified(qp_no_work($finalReadRequest, $finalRestored), 'both session-close and last durable restore block every price after final write failure');
    }
}

// Failure on a later pair also seals the earlier verified cache.
[$laterState, $laterRequest, , , , $laterFacts, $laterFactory, $laterPersist] = qp_fixture('multiple');
$laterChoices = qp_run($laterRequest, $laterState, $laterFactory, $laterPersist);
$laterA = array_replace($laterRequest, ['action' => 'quote_calculate', 'choice_ref' => $laterChoices['choices'][0]['choice_ref']]);
$laterB = array_replace($laterA, ['choice_ref' => $laterChoices['choices'][1]['choice_ref']]);
qp_run($laterA, $laterState, $laterFactory, $laterPersist);
$laterFacts->mode = 'calcfull';
$laterFailure = qp_run($laterB, $laterState, $laterFactory, $laterPersist);
qp_assert($laterFailure['status'] === 'quote_failed' && $laterFailure['failure_stage'] === 'calcfull'
    && $laterFailure['repricing']['remaining_pairs'] === 0 && count($laterFacts->calls) === 6,
    'later calcfull timeout permanently disables the context');
$laterRestored = unserialize(serialize($laterState));
$laterBeforeRead = serialize($laterRestored);
foreach ([$laterRequest, $laterA, $laterB] as $laterReadRequest) {
    qp_unverified(qp_no_work($laterReadRequest, $laterRestored), 'later unknown cannot expose an earlier verified cache');
}
qp_assert(serialize($laterRestored) === $laterBeforeRead, 'sealed failure reads never reset stage or pair counters');

// The clock advances in the supplier callback and after persistence, not just at request entry.
foreach (['start', 'SetTransport', 'calcfull', 'reservation-checkpoint', 'final-checkpoint'] as $expiryBoundary) {
    [$expiryState, $expiryRequest, , , , $expiryFacts, $expiryFactory, $expiryPersist] = qp_fixture();
    $expiryTime = 1100;
    $expiryClock = static function () use (&$expiryTime): int { return $expiryTime; };
    $expiryKey = $expiryRequest['offer_ref'];
    if ($expiryBoundary !== 'start') {
        $expiryChoices = qp_run($expiryRequest, $expiryState, $expiryFactory, $expiryPersist, 1100, $expiryClock);
        $expiryRequest = array_replace($expiryRequest,
            ['action' => 'quote_calculate', 'choice_ref' => $expiryChoices['choices'][0]['choice_ref']]);
    }
    if ($expiryBoundary === 'reservation-checkpoint' || $expiryBoundary === 'final-checkpoint') {
        $expiryFacts->after_checkpoint = static function (array &$pending) use ($expiryBoundary, $expiryKey, &$expiryTime): void {
            $attempt = $pending['package_quotes'][$expiryKey];
            $reached = $expiryBoundary === 'reservation-checkpoint'
                ? ($attempt['stages']['SetTransport'] ?? null) === 'unknown'
                : ($attempt['public']['status'] ?? null) === 'quote_verified';
            if ($reached) $expiryTime = $attempt['expires_at'];
        };
    } else {
        $expiryFacts->after_http = static function ($stage) use ($expiryBoundary, &$expiryTime): void {
            if ($stage === $expiryBoundary) $expiryTime = 1700;
        };
    }
    $expired = qp_run($expiryRequest, $expiryState, $expiryFactory, $expiryPersist, 1100, $expiryClock);
    $expectedHTTP = ['start' => 1, 'SetTransport' => 3, 'calcfull' => 4,
        'reservation-checkpoint' => 2, 'final-checkpoint' => 4][$expiryBoundary];
    qp_assert($expired['status'] === 'quote_expired' && $expired['repricing']['remaining_pairs'] === 0
        && count($expiryFacts->calls) === $expectedHTTP, 'TTL boundary prevents further supplier calls: ' . $expiryBoundary);
    qp_unverified($expired, 'TTL boundary cannot send final verification: ' . $expiryBoundary);
    $expiryRestored = unserialize(serialize($expiryFacts->persisted));
    qp_unverified(qp_no_work($expiryRequest, $expiryRestored, $expiryTime),
        'expired retained state cannot regain verification after restore: ' . $expiryBoundary);
}

[$s, $r, $o, $k, $e, $f, $fac, $cp] = qp_fixture('401');
qp_run($r, $s, $fac, $cp);
$session = ['offer_context' => $s, 'unrelated_secret' => 'PRIVATE_SESSION_DATA'];
$session['offer_context']['package_quotes'][$r['offer_ref']]['diagnostics']['start']['raw'] = 'PRIVATE_SUPPLIER_BODY';
$before = serialize($session); $calls = count($f->calls);
$receipt = anytour_anex_search3_quote_receipt($session);
qp_assert($receipt === ['retained_only' => true, 'supplier_calls' => 0, 'records' => [[
    'status' => 'quote_failed', 'reason' => 'ANEX_QUOTE_HTTP_ERROR',
    'stages' => [['stage' => 'start', 'state' => 'unknown', 'http_status' => 401, 'response_bytes' => 31]],
    'version' => 2, 'reserved_calls' => 1, 'sealed' => true,
]]], 'versioned retained receipt proves reserved budget, seal, original stage and HTTP without raw response');
qp_assert(anytour_anex_search3_quote_receipt($session) === $receipt && serialize($session) === $before
    && count($f->calls) === $calls, 'repeat receipt read neither changes session nor invokes supplier');
foreach (['PRIVATE', 'private-catclaim', $r['offer_ref'], $s['package_quotes'][$r['offer_ref']]['id']] as $private) {
    qp_assert(strpos(json_encode($receipt), $private) === false, 'retained receipt redacts private context');
}
$legacyReceiptSession = $session;
foreach (['version', 'context_digest', 'reserved_calls', 'pairs', 'active_pair', 'sealed'] as $field) {
    unset($legacyReceiptSession['offer_context']['package_quotes'][$r['offer_ref']][$field]);
}
qp_assert(anytour_anex_search3_quote_receipt($legacyReceiptSession) === [
    'retained_only' => true, 'supplier_calls' => 0, 'records' => [[
        'status' => 'quote_failed', 'reason' => 'ANEX_QUOTE_HTTP_ERROR',
        'stages' => [['stage' => 'start', 'state' => 'unknown', 'http_status' => 401, 'response_bytes' => 31]],
    ]]], 'legacy receipt retains its exact previous shape');
$multiReceiptSession = ['offer_context' => unserialize($multiAtCap)];
$multiReceiptSession['offer_context']['package_quotes'][$multiKey]['pairs'][$pairRequests[0]['choice_ref']]['diagnostics']['calcfull']['raw'] = 'PRIVATE_PAIR_BODY';
$multiReceiptBefore = serialize($multiReceiptSession);
$multiReceipt = anytour_anex_search3_quote_receipt($multiReceiptSession);
$multiRecord = $multiReceipt['records'][0];
qp_assert($multiRecord['status'] === 'quote_verified' && $multiRecord['version'] === 2
    && $multiRecord['reserved_calls'] === 8 && $multiRecord['sealed'] === false
    && array_column($multiRecord['stages'], 'stage') === ['start', 'transports',
        'SetTransport', 'calcfull', 'SetTransport', 'calcfull', 'SetTransport', 'calcfull']
    && array_column(array_slice($multiRecord['stages'], 2), 'pair_index') === [1, 1, 2, 2, 3, 3],
    'versioned receipt flattens two initial stages and six pair calls in their durable order');
qp_assert(!isset($multiRecord['stages'][0]['pair_index']) && !isset($multiRecord['stages'][1]['pair_index'])
    && serialize($multiReceiptSession) === $multiReceiptBefore
    && anytour_anex_search3_quote_receipt($multiReceiptSession) === $multiReceipt,
    'receipt initial stages have no pair index and repeated receipt reads are pure');
foreach (array_merge(['PRIVATE', 'private-', $multiKey, $multiState['package_quotes'][$multiKey]['id']],
    array_column($inventory['choices'], 'choice_ref')) as $private) {
    qp_assert(strpos(json_encode($multiReceipt), $private) === false, 'flattened receipt never projects pair refs or supplier context');
}
$overfullReceiptState = unserialize($multiAtCap);
$overfullReceiptState['package_quotes'][$multiKey]['reserved_calls'] = 999;
for ($index = 0; $index < 20; ++$index) {
    $overfullReceiptState['package_quotes'][$multiKey]['pairs']['PRIVATE_PAIR_' . $index] =
        $overfullReceiptState['package_quotes'][$multiKey]['pairs'][$pairRequests[0]['choice_ref']];
}
$boundedReceipt = anytour_anex_search3_quote_receipt(['offer_context' => $overfullReceiptState]);
qp_assert(count($boundedReceipt['records'][0]['stages']) <= 8
    && $boundedReceipt['records'][0]['reserved_calls'] === null
    && $boundedReceipt['records'][0]['sealed'] === true
    && $boundedReceipt['records'][0]['status'] === 'quote_unknown'
    && strpos(json_encode($boundedReceipt), 'PRIVATE_PAIR_') === false,
    'malformed overfull versioned state cannot expand the receipt or expose its keys');
$bad = ['public' => ['status' => 'PRIVATE_STATUS', 'reason' => 'PRIVATE_REASON'],
    'stages' => ['start' => 'complete', 'private-stage' => 'complete'],
    'diagnostics' => ['start' => ['http_status' => 'PRIVATE', 'response_bytes' => -1]]];
$safe = anytour_anex_search3_quote_receipt(['offer_context' => ['package_quotes' => array_fill(0, 50, $bad)]]);
qp_assert(count($safe['records']) === 40 && $safe['records'][0] === ['status' => 'quote_unknown', 'reason' => null,
    'stages' => [['stage' => 'start', 'state' => 'complete']]], 'bounded allowlist handles malformed retained values');
qp_assert(anytour_anex_search3_quote_receipt([])['records'] === [], 'missing session never triggers a fresh quote');
// Read existing raw-page digests, never replay a search to distinguish empty/rejected.
$pageContext = ['checkin_begin' => '2027-01-31', 'checkin_end' => '2027-02-06',
    'nights_from' => 7, 'nights_till' => 7, 'adults' => 2, 'children' => 1, 'child_ages' => [0]];
$pageSession = ['offer_context' => ['generation' => 7, 'gateway' => [
    'search' => ['schema_version' => 1, 'context' => $pageContext, 'offers' => [],
        'pagination' => ['pages_read' => 1, 'state' => 'exhausted', 'page_digests' => [hash('sha256', '[]')]]],
    'saved_offers' => ['search_ref' => str_repeat('a', 32), 'search' => $pageContext]]],
    'PRIVATE_BODY' => 'PRIVATE_TOKEN'];
foreach (['empty', 'nonempty'] as $rawPage) {
    $retained = $pageSession;
    if ($rawPage === 'nonempty') $retained['offer_context']['gateway']['search']['pagination']['page_digests'][0] = hash('sha256', '["PRIVATE_RAW_ROW"]');
    $beforePageRead = serialize($retained);
    $pageReceipt = anytour_anex_search3_quote_receipt($retained);
    qp_assert($pageReceipt['retained_first_page'] === ['raw_rows' => $rawPage, 'retained_offers' => 0,
        'interpretation' => $rawPage === 'empty' ? 'empty_supplier_page' : 'rows_rejected_or_filtered'], 'retained page distinguishes supplier empty from rejected/filtered rows');
    qp_assert($pageReceipt['records'] === [] && serialize($retained) === $beforePageRead
        && anytour_anex_search3_quote_receipt($retained) === $pageReceipt, 'page receipt is pure and never creates a quote/search');
    qp_assert(!str_contains(json_encode($pageReceipt), 'PRIVATE')
        && !str_contains(json_encode($pageReceipt), $retained['offer_context']['gateway']['search']['pagination']['page_digests'][0])
        && !str_contains(json_encode($pageReceipt), str_repeat('a', 32)), 'raw digest, IDs and unrelated session values remain private');
}
$withOffers = $retained;
$withOffers['offer_context']['gateway']['search']['offers'] = [['supplier_offer_id' => 'PRIVATE_CATCLAIM']];
qp_assert(anytour_anex_search3_quote_receipt($withOffers)['retained_first_page'] === [
    'raw_rows' => 'nonempty', 'retained_offers' => 1, 'interpretation' => 'offers_retained'], 'nonempty retained offers are not called rejected');
foreach (['missing', 'continued', 'blocked', 'bad-digest', 'array-digest', 'extra-digest', 'empty-with-offers',
    'empty-available', 'bad-schema', 'bad-context', 'foreign-context', 'bad-ref', 'bad-generation', 'overfull', 'non-list'] as $badPage) {
    $badSession = $pageSession;
    $snap = &$badSession['offer_context']['gateway']['search'];
    if ($badPage === 'missing') unset($snap['pagination']);
    elseif ($badPage === 'continued') $snap['pagination']['pages_read'] = 2;
    elseif ($badPage === 'blocked') $snap['pagination']['state'] = 'blocked';
    elseif ($badPage === 'bad-digest') $snap['pagination']['page_digests'] = ['PRIVATE'];
    elseif ($badPage === 'array-digest') $snap['pagination']['page_digests'] = [[]];
    elseif ($badPage === 'extra-digest') $snap['pagination']['page_digests'][] = hash('sha256', '[]');
    elseif ($badPage === 'empty-with-offers') $snap['offers'] = [['supplier_offer_id' => 'PRIVATE']];
    elseif ($badPage === 'empty-available') $snap['pagination']['state'] = 'available';
    elseif ($badPage === 'bad-schema') $snap['schema_version'] = 2;
    elseif ($badPage === 'bad-context') $snap['context'] = [];
    elseif ($badPage === 'foreign-context') $snap['context']['child_ages'] = [1];
    elseif ($badPage === 'bad-ref') $badSession['offer_context']['gateway']['saved_offers']['search_ref'] = 'PRIVATE';
    elseif ($badPage === 'bad-generation') $badSession['offer_context']['generation'] = '7';
    elseif ($badPage === 'overfull') $snap['offers'] = array_fill(0, 301, []);
    elseif ($badPage === 'non-list') $snap['offers'] = [1 => []];
    unset($snap);
    qp_assert(!isset(anytour_anex_search3_quote_receipt($badSession)['retained_first_page']), 'unproved page remains unknown: ' . $badPage);
}
// Infant party fixtures exercise the inferred source contract, NOT live acceptance.
foreach ([[0], [1], [0, 8], [0, 0], [1, 1, 9], [2]] as $ages) {
    [$s, $r, , , , $f, $make, $save] = qp_fixture('', $ages);
    $inventory = qp_run($r, $s, $make, $save);
    qp_assert($inventory['status'] === 'quote_choices', 'full family gets unpriced choices');
    $pair = array_replace($r, ['action' => 'quote_calculate', 'choice_ref' => $inventory['choices'][0]['choice_ref']]);
    $priced = qp_run($pair, $s, $make, $save);
    qp_assert($priced['status'] === 'quote_verified' && $priced['price'] === [
        'amount' => '123456.78', 'currency' => 'RUB', 'basis' => 'supplier_gross_package'],
        'only supplier gross verifies the whole family, no infant/fuel estimate added');
    qp_assert($f->calls === ['start', 'transports', 'SetTransport', 'calcfull'], 'one family uses four reserved stages');
    $before = serialize($s);
    qp_assert(qp_tuple(qp_no_work($pair, $s, 1110)) === qp_tuple($priced)
        && serialize($s) === $before, 'family cached quote keeps exact money and expiry without work');
    qp_unverified(qp_no_work($pair, $s, $priced['expires_at']), 'family expires on original deadline');
    $publicAndRetained = json_encode([$s, $priced], JSON_THROW_ON_ERROR);
    qp_assert(strpos($publicAndRetained, 'PRIVATE_INFANT_NAME') === false
        && strpos($publicAndRetained, 'PRIVATE_INFANT_BIRTH') === false, 'no personal infant fields retained or projected');
    if ($ages === [0]) {
        foreach ([[1], [], [8]] as $differentAges) {
            $changed = unserialize($before);
            $changed['gateway']['search']['context']['child_ages'] = $differentAges;
            qp_unverified(qp_no_work($pair, $changed), 'age0 binding cannot reuse price for another age or missing infant');
        }
    }
}
foreach (['without-summaries', 'human-infant'] as $variant) {
    [$s, $r, , , , $f, $make, $save] = qp_fixture('', [0]);
    $f->transform_doc = static function (array $doc) use ($variant): array {
        if ($variant === 'without-summaries') unset($doc['adult'], $doc['child'], $doc['infant']);
        else {
            unset($doc['peoples']['people'][2]['age']);
            $doc['peoples']['people'][2]['human'] = 'INF';
        }
        return $doc;
    };
    $inventory = qp_run($r, $s, $make, $save);
    $pair = array_replace($r, ['action' => 'quote_calculate', 'choice_ref' => $inventory['choices'][0]['choice_ref']]);
    qp_assert(qp_run($pair, $s, $make, $save)['status'] === 'quote_verified', 'explicit typed family handles ' . $variant);
}
$familyStages = ['start', 'transports', 'SetTransport', 'calcfull'];
foreach ($familyStages as $stageIndex => $badStage) {
    foreach (['missing-people', 'missing-infant', 'duplicate-key', 'missing-key', 'null-key', 'all-null-keys', 'unknown-class',
        'conflicting-class', 'wrong-adult', 'child-includes-infant', 'wrong-infant',
        'null-infant', 'bool-infant', 'array-infant'] as $variant) {
        [$s, $r, , , , $f, $make, $save] = qp_fixture('', [0]);
        $f->transform_doc = static function (array $doc, string $stage) use ($variant, $badStage): array {
            if ($stage !== $badStage) return $doc;
            if ($variant === 'missing-people') unset($doc['peoples']);
            elseif ($variant === 'missing-infant') array_pop($doc['peoples']['people']);
            elseif ($variant === 'duplicate-key') $doc['peoples']['people'][2]['key'] = '2';
            elseif ($variant === 'missing-key') unset($doc['peoples']['people'][2]['key']);
            elseif ($variant === 'null-key') $doc['peoples']['people'][2]['key'] = null;
            elseif ($variant === 'all-null-keys') {
                foreach ($doc['peoples']['people'] as &$person) $person['key'] = null;
                unset($person);
            }
            elseif ($variant === 'unknown-class') $doc['peoples']['people'][2]['age'] = 'UNKNOWN';
            elseif ($variant === 'conflicting-class') $doc['peoples']['people'][2]['human'] = 'CHD';
            elseif ($variant === 'wrong-adult') $doc['adult'] = '3';
            elseif ($variant === 'child-includes-infant') $doc['child'] = '1';
            elseif ($variant === 'wrong-infant') $doc['infant'] = '2';
            elseif ($variant === 'null-infant') $doc['infant'] = null;
            elseif ($variant === 'bool-infant') $doc['infant'] = true;
            elseif ($variant === 'array-infant') $doc['infant'] = ['PRIVATE_INFANT_COUNTER'];
            return $doc;
        };
        $failed = qp_run($r, $s, $make, $save);
        $attemptedRequest = $r;
        if ($stageIndex > 1) {
            $attemptedRequest = array_replace($r, ['action' => 'quote_calculate',
                'choice_ref' => $failed['choices'][0]['choice_ref']]);
            $failed = qp_run($attemptedRequest, $s, $make, $save);
        }
        qp_unverified($failed, 'bad family never verifies: ' . $badStage . '/' . $variant);
        qp_assert($failed['status'] === 'quote_failed' && $f->calls === array_slice($familyStages, 0, $stageIndex + 1),
            'family identity failure stops exact stage and further spend: ' . $badStage . '/' . $variant);
        $before = count($f->calls);
        $f->transform_doc = null;
        qp_unverified(qp_run($attemptedRequest, $s, $make, $save), 'sealed family cannot replay restored response');
        qp_assert(count($f->calls) === $before, 'restored response grants no family retry');
    }
}
foreach ([null, 0, 2, '1', false] as $badInfants) {
    [$s, $r, , , , $f, $make, $save] = qp_fixture('', [0]);
    $s['gateway']['saved_offers']['offers'][$r['offer_ref']]['offer']['infants'] = $badInfants;
    // Old retained zero/missing infant counts must not bypass full age0/1 binding.
    $reply = qp_run($r, $s, $make, $save);
    qp_unverified($reply, 'foreign infant binding cannot verify');
    qp_assert($f->calls === [] && $f->factory_calls === 0, 'invalid or legacy infant binding rejected before client creation');
}
// Hotel identity/metadata work may cross expiry after the gateway's entry-time check.
foreach ([false, true] as $delayedLegacy) {
    [$delayedState, $delayedRequest, , , , $delayedFacts, $delayedFactory, $delayedPersist] = qp_fixture();
    $delayedChoices = qp_run($delayedRequest, $delayedState, $delayedFactory, $delayedPersist);
    $delayedUnpriced = $delayedState;
    $delayedPair = array_replace($delayedRequest,
        ['action' => 'quote_calculate', 'choice_ref' => $delayedChoices['choices'][0]['choice_ref']]);
    qp_run($delayedPair, $delayedState, $delayedFactory, $delayedPersist);
    $delayedKey = $delayedRequest['offer_ref'];
    if ($delayedLegacy) {
        foreach (['version', 'context_digest', 'reserved_calls', 'pairs', 'active_pair', 'sealed'] as $field) {
            unset($delayedState['package_quotes'][$delayedKey][$field], $delayedUnpriced['package_quotes'][$delayedKey][$field]);
        }
        $delayedState['package_quotes'][$delayedKey]['chosen'] = $delayedPair['choice_ref'];
    }
    foreach ([[$delayedUnpriced, $delayedRequest], [$delayedState, $delayedRequest], [$delayedState, $delayedPair]] as [$delayedSaved, $delayedRead]) {
        $deadline = $delayedSaved['package_quotes'][$delayedKey]['expires_at'];
        $beforeDelay = qp_no_work($delayedRead, $delayedSaved, $deadline - 2);
        foreach ([$deadline - 1, $deadline, $deadline + 1, 0, (string) $deadline] as $afterMetadata) {
            $delayedRestore = $delayedSaved; $delayedBefore = serialize($delayedRestore); $delayedTime = $deadline - 2;
            $delayedReply = anytour_anex_search3_followup($delayedRead, $delayedRestore,
                static fn($ns, $id) => 245,
                static function () { throw new RuntimeException('delayed cached read must not construct a parser'); },
                static function ($offers) use (&$delayedTime, $afterMetadata): array {
                    $delayedTime = $afterMetadata;
                    return [245 => ['id' => 245, 'name' => 'Fixture hotel', 'country_id' => 4, 'country_name' => 'Turkey', 'category' => 4]];
                },
                static function () use (&$delayedTime) { return $delayedTime; },
                static function () { throw new RuntimeException('delayed cached read must not checkpoint'); }, null, false,
                static function () { throw new RuntimeException('delayed cached read must not construct a quote client'); });
            qp_assert(serialize($delayedRestore) === $delayedBefore, 'metadata-delay reads never renew or mutate retained state');
            if ($afterMetadata === $deadline - 1) {
                qp_assert($delayedReply === $beforeDelay, 'before-expiry read preserves exact price/choices/times and capacity');
            } else {
                $delayedStatus = is_int($afterMetadata) && $afterMetadata > 0 ? 'quote_expired' : 'quote_unknown';
                qp_assert($delayedReply['status'] === $delayedStatus && !isset($delayedReply['choices']),
                    'current time after metadata gates both cached prices and retained choices');
                qp_unverified($delayedReply, 'metadata-delay boundary cannot promote a retained price');
                qp_assert($delayedLegacy ? !isset($delayedReply['repricing']) : $delayedReply['repricing']['remaining_pairs'] === 0,
                    'expired or invalid clock cannot grant pair capacity or upgrade legacy state');
            }
        }
    }
}
echo "ANEX package quote: {$checks} checks passed (offline contract fixtures; no live supplier calls)\n";

