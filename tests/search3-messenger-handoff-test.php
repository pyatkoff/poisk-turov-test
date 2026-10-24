<?php
declare(strict_types=1);
if (($argv[1] ?? '') === '--curl-fixture') {
    if (function_exists('curl_init')) throw new RuntimeException('run curl fixture with php -n');
    foreach (['CURLOPT_POST', 'CURLOPT_POSTFIELDS', 'CURLOPT_HTTPHEADER', 'CURLOPT_CONNECTTIMEOUT', 'CURLOPT_TIMEOUT',
        'CURLOPT_FOLLOWLOCATION', 'CURLOPT_MAXREDIRS', 'CURLOPT_SSL_VERIFYPEER', 'CURLOPT_SSL_VERIFYHOST', 'CURLOPT_PROTOCOLS',
        'CURLOPT_WRITEFUNCTION', 'CURLPROTO_HTTPS', 'CURLINFO_RESPONSE_CODE'] as $i => $constant) define($constant, $i + 1);
    function curl_init($url) { $GLOBALS['mh_curl_url'] = $url; return new stdClass(); }
    function curl_setopt_array($ch, $options) { $ch->options = $options; $GLOBALS['mh_curl_options'] = $options; return true; }
    function curl_exec($ch) { return ($ch->options[CURLOPT_WRITEFUNCTION])($ch, str_repeat('x', 16385)) !== 0; }
    function curl_getinfo($ch, $option) { return 200; }
    function curl_close($ch) { $GLOBALS['mh_curl_closed'] = true; }
    require_once __DIR__ . '/../app/integrations/search3-messenger-handoff.php';
    try { AnyTourSearch3MessengerHandoff::httpTransport(AnyTourSearch3MessengerHandoff::RECEIVER, '{}', []); }
    catch (RuntimeException $e) { $failure = $e->getMessage(); }
    $o = $GLOBALS['mh_curl_options'];
    echo json_encode(['fixed' => $GLOBALS['mh_curl_url'] === AnyTourSearch3MessengerHandoff::RECEIVER,
        'timeout' => $o[CURLOPT_TIMEOUT], 'connect' => $o[CURLOPT_CONNECTTIMEOUT], 'redirects' => $o[CURLOPT_FOLLOWLOCATION],
        'tls' => $o[CURLOPT_SSL_VERIFYPEER] && $o[CURLOPT_SSL_VERIFYHOST] === 2 && $o[CURLOPT_PROTOCOLS] === CURLPROTO_HTTPS,
        'oversize' => ($failure ?? null) === 'receiver_unknown', 'closed' => $GLOBALS['mh_curl_closed'] ?? false]);
    exit;
}
require_once __DIR__ . '/../v2/api-anex-search3-preview.php';
require_once __DIR__ . '/../app/integrations/anex-preview-gateway.php';

function mh_check(bool $condition, string $label): void
{
    $GLOBALS['mh_checks'] = ($GLOBALS['mh_checks'] ?? 0) + 1;
    if (!$condition) throw new RuntimeException('FAIL ' . $label);
}
function mh_reject(callable $run, string $field): void
{
    try { $run(); } catch (AnyTourSearch3HandoffMissing $e) { mh_check($e->field === $field, $field . ' got ' . $e->field); return; }
    throw new RuntimeException('expected missing ' . $field);
}
function mh_error(callable $run, string $code): void
{
    try { $run(); } catch (Throwable $e) { mh_check($e->getMessage() === $code, $code . ' got ' . $e->getMessage()); return; }
    throw new RuntimeException('expected error ' . $code);
}

/** Real gateway/normalizer/quote owners, all supplier transports synthetic and counted. */
function mh_fixture(array $ages = [], bool $quoted = true, ?int $now = null): array
{
    $now ??= time() - 3;
    $facts = (object)['calls' => 0];
    $infants = count(array_filter($ages, static fn(int $age): bool => $age < 2));
    $criteria = ['supplier_namespace' => 'anex_online', 'departure_id' => '1', 'destination_id' => '2', 'currency_id' => '3',
        'checkin_begin' => '2027-02-10', 'checkin_end' => '2027-02-16', 'nights_from' => 7, 'nights_till' => 7,
        'adults' => 2, 'children' => count($ages), 'child_ages' => $ages];
    $row = ['id' => 'PRIVATE_CLAIM', 'hotelKey' => '469', 'hotel' => 'Synthetic hotel', 'town' => 'Synthetic resort',
        'checkIn' => '20270210', 'checkOut' => '20270217', 'nights' => 7, 'adult' => 2, 'child' => count($ages) - $infants,
        'infant' => $infants, 'grouped' => false, 'packetType' => 0, 'price' => '100000.50', 'currency' => 'RUB',
        'meal' => 'AI', 'room' => 'STANDARD', 'roomKey' => '10', 'htPlace' => 'DBL', 'currencyKey' => '99'];
    $client = new AnyTourAnexClient('SYNTHETIC_TOKEN', static function () use ($facts, $row): array {
        ++$facts->calls; return ['status' => 200, 'body' => json_encode(['SearchTour_PRICES' => ['prices' => [$row]]], JSON_THROW_ON_ERROR)];
    });
    $gateway = new AnyTourAnexPreviewGateway(static fn() => $client, static fn() => 245, [], static fn() => $now);
    $state = [];
    $result = $gateway->handle(['action' => 'search', 'criteria' => $criteria], $state);
    $key = array_key_first($state['saved_offers']['offers']);
    if (!is_string($key)) throw new RuntimeException('fixture failed to retain concrete offer');
    $ref = $result['search_ref'];
    $context = ['generation' => 7, 'params' => ['countryId' => 4, 'adults' => 2, 'childs' => $ages], 'gateway' => $state,
        'handoff_context' => ['version' => 1, 'origin' => 'Москва', 'countryUiId' => '4', 'hotels' => anytour_anex_handoff_catalog(
            [245 => ['id' => 245, 'name' => 'Synthetic catalog hotel', 'country_id' => 4, 'region_name' => 'Synthetic resort']], 4)]];
    $choice = null;
    if ($quoted) {
        $offer = $state['saved_offers']['offers'][$key]['offer'];
        $known = $state['search']['offers'][0];
        $entry = $state['saved_offers']['offers'][$key]; $entry['supplier_currency_id'] = '3';
        $factory = static fn() => new AnyTourAnexPackageQuoteClient('SYNTHETIC_QUOTE_TOKEN', static function () use ($facts, $ages, $infants): array {
            ++$facts->calls;
            $people = [['key' => '1', 'age' => 'ADL'], ['key' => '2', 'age' => 'ADL']];
            foreach ($ages as $age) $people[] = ['key' => (string)(count($people) + 1), 'age' => $age < 2 ? 'INF' : 'CHD'];
            $doc = ['datebeg' => '2027-02-10', 'dateend' => '2027-02-17', 'nights' => '7', 'adult' => '2',
                'child' => (string)(count($ages) - $infants), 'infant' => (string)$infants, 'freightExternal' => '2',
                'hotels' => ['hotel' => [['key' => '469', 'roomKey' => '10', 'room' => 'STANDARD', 'meal' => 'AI', 'htplace' => 'DBL']]],
                'transports' => ['transport' => [
                    ['routeIndex' => '0', 'uid' => 'PRIVATE_OUT', 'name' => 'Explicit outward', 'datebeg' => '2027-02-10'],
                    ['routeIndex' => '1', 'uid' => 'PRIVATE_BACK', 'name' => 'Explicit inward', 'datebeg' => '2027-02-17']]],
                'moneys' => ['money' => [['currency' => 'RUB', 'price' => '231456.78']]], 'peoples' => ['people' => $people]];
            return ['status' => 200, 'body' => json_encode(['bron' => ['claim' => ['claimDocument' => $doc]]], JSON_THROW_ON_ERROR)];
        });
        $request = ['action' => 'quote_start', 'generation' => 7, 'search_ref' => $ref, 'offer_ref' => $key];
        $checkpoint = static function (array &$unused): void {};
        $choices = anytour_anex_quote_run($request, $context, $offer, $known, $entry, $factory, $checkpoint, $now + 1);
        $choice = $choices['choices'][0]['choice_ref'] ?? null;
        if (!is_string($choice)) throw new RuntimeException('fixture quote choices missing');
        $reply = anytour_anex_quote_run(array_replace($request, ['action' => 'quote_calculate', 'choice_ref' => $choice]),
            $context, $offer, $known, $entry, $factory, $checkpoint, $now + 2);
        if (($reply['status'] ?? null) !== 'quote_verified') throw new RuntimeException('fixture quote not verified');
    }
    $session = ['anex_context_generation' => 7, 'anex_offer_contexts' => [$ref => $context], 'offer_context' => $context,
        'search3_handoff_csrf' => str_repeat('c', 64), 'dictionaries' => [hash('sha256', 'SearchTour_TOWNFROMS[]') =>
            ['expires' => $now + 100, 'rows' => [['id' => 1, 'name' => 'Moscow', 'nameAlt' => 'Москва']]]]];
    $request = ['action' => 'handoff_freeze', 'generation' => 7, 'search_ref' => $ref, 'offer_ref' => $key, 'local_hotel_id' => 245,
        'choice_ref' => $choice, 'csrf' => $session['search3_handoff_csrf']];
    return [$session, $request, $facts, $now + 3];
}

function mh_freeze(array $request, array &$session, int $now): array
{
    return AnyTourSearch3MessengerHandoff::freeze($request, $session, $now,
        static function (array &$unused): void {}, static fn(): int => $now);
}

if (defined('ANYTOUR_HANDOFF_FIXTURE_LIBRARY_ONLY')) return;
$GLOBALS['mh_checks'] = 0;
[$session, $request, $facts, $now] = mh_fixture(); $supplierCalls = $facts->calls;
$preview = mh_freeze($request, $session, $now);
$operation = $preview['operation_id']; $record = $session['search3_messenger_handoffs'][$operation];
$body = json_decode($record['raw'], true, 32, JSON_THROW_ON_ERROR);
mh_check($facts->calls === $supplierCalls && $supplierCalls === 5, 'freeze has zero supplier reads');
mh_check($preview['observation']['price']['amount'] === '231456.78' && $preview['observation']['price']['finalPriceVerified'] === false, 'exact retained quote, no promotion');
mh_check($preview['observation']['trip']['origin'] === 'Москва' && $preview['observation']['trip']['stayEndBasis'] === 'shown-stay-end', 'retained labels and stay end');
mh_check($preview['observation']['flights']['state'] === 'selected' && count($preview['observation']['flights']['outward']) === 1, 'canonical exact selected pair');
mh_check(!str_contains(json_encode($preview), 'PRIVATE_') && !isset($preview['binding']), 'preview contains no private source refs');
mh_check(mh_freeze($request, $session, $now + 1) === $preview, 'same retained revision has one operation');
$slot = $session['anex_offer_contexts'][$request['search_ref']]['package_quotes'][$request['offer_ref']]['pairs'][$request['choice_ref']]['handoff_record'];
mh_check(json_decode($slot['json'], true) === ['binding' => $body['binding'], 'observation' => $body['observation']], 'wire reuses exact canonical INT record and binding');
mh_check($slot['json_sha256'] === $record['record_sha256'], 'canonical JSON hash retained without another projection');
$reordered = array_reverse($request, true);
mh_check(mh_freeze($reordered, $session, $now + 1)['operation_id'] === $operation, 'JSON key ordering cannot mint another intent');
$restored = unserialize(serialize($session));
mh_check(mh_freeze($request, $restored, $now + 2) === $preview, 'server restart serialization preserves bytes and operation');

[$family, $familyRequest, $familyFacts, $familyNow] = mh_fixture([0, 0, 8], true);
$before = $familyFacts->calls;
$familyPreview = mh_freeze($familyRequest, $family, $familyNow);
mh_check($familyFacts->calls === $before && $before === 5, 'selected pair uses only retained completed quote');
mh_check($familyPreview['observation']['trip']['childAges'] === [0, 0, 8], 'ordered duplicate infants retained');
mh_check($familyPreview['observation']['price']['amount'] === '231456.78' && $familyPreview['observation']['price']['state'] === 'checked', 'exact selected pair amount and stage');
mh_check(str_contains($familyPreview['observation']['flights']['inward'][0]['label'], 'Explicit inward'), 'exact explicitly chosen pair');
$exp = $family;
mh_reject(static function () use ($familyRequest, &$exp, $familyNow): void { mh_freeze($familyRequest, $exp, $familyNow + 600); }, 'quote.complete_exact_pair');

foreach (['generation' => 8, 'search_ref' => str_repeat('f', 32), 'local_hotel_id' => 246, 'offer_ref' => 'anex_online:' . str_repeat('e', 64)] as $field => $value) {
    $copy = $session; mh_reject(static function () use ($request, $field, $value, &$copy, $now): void {
        mh_freeze(array_replace($request, [$field => $value]), $copy, $now);
    }, in_array($field, ['generation', 'search_ref'], true) ? 'search.current_context' : 'offer.retained_identity');
}
$copy = $session; unset($copy['anex_offer_contexts'][$request['search_ref']]['handoff_context']['origin']);
mh_reject(static function () use (&$copy, $request, $now): void { mh_freeze($request, $copy, $now); }, 'trip.origin');
$copy = $session; $ref = $request['search_ref']; $key = $request['offer_ref'];
$copy['anex_offer_contexts'][$ref]['gateway']['saved_offers']['offers'][$key]['offer']['room'] = str_repeat('я', 501);
mh_reject(static function () use (&$copy, $request, $now): void { mh_freeze($request, $copy, $now); }, 'quote.complete_exact_pair');
$copy = $session; $copy['anex_offer_contexts'][$ref]['gateway']['saved_offers']['offers'][$key]['offer']['price']['amount'] = '100000.501';
mh_reject(static function () use (&$copy, $request, $now): void { mh_freeze($request, $copy, $now); }, 'quote.complete_exact_pair');
[$infants, $infantRequest, , $infantNow] = mh_fixture([0, 0], false);
mh_reject(static function () use (&$infants, $infantRequest, $infantNow): void { mh_freeze($infantRequest, $infants, $infantNow); }, 'selection.pins');
$copy = $family; $familyRef = $familyRequest['search_ref']; $familyKey = $familyRequest['offer_ref'];
$copy['anex_offer_contexts'][$familyRef]['package_quotes'][$familyKey]['active_pair'] = $familyRequest['choice_ref'];
mh_reject(static function () use (&$copy, $familyRequest, $familyNow): void { mh_freeze($familyRequest, $copy, $familyNow); }, 'quote.complete_exact_pair');
mh_error(static function () use (&$session, $request, $now): void { mh_freeze($request + ['price' => '1'], $session, $now); }, 'invalid_request');

$keypair = sodium_crypto_sign_keypair();
$signer = ['key_id' => 'ephemeral-fixture', 'secret_key' => sodium_crypto_sign_secretkey($keypair), 'not_before' => $now - 1, 'not_after' => $now + 86400];
$public = sodium_crypto_sign_publickey($keypair);
$headers = AnyTourSearch3MessengerHandoff::headers($record['raw'], $signer, $now);
$signature = base64_decode(strtr(substr($headers[3], strlen('X-Anytour-Search3-Signature: ')), '-_', '+/') . '==', true);
$message = "anytour-search3-handoff-v1\nPOST\n/search3/tour-handoff.php\n$now\n" . hash('sha256', $record['raw']);
mh_check(sodium_crypto_sign_verify_detached($signature, $message, $public), 'exact frozen bytes and newline signature');
mh_check(!sodium_crypto_sign_verify_detached($signature, $message . "\n", $public), 'no final newline substitution');
mh_error(static fn() => AnyTourSearch3MessengerHandoff::headers($record['raw'], array_replace($signer, ['not_after' => $now]), $now), 'signer_unavailable');

$persisted = []; $wires = [];
$checkpoint = static function (array &$s) use (&$persisted): void { $persisted = unserialize(serialize($s)); };
$transport = static function (string $url, string $raw, array $headers) use (&$persisted, &$wires, $operation, $record): array {
    mh_check($url === AnyTourSearch3MessengerHandoff::RECEIVER, 'fixed receiver only');
    mh_check($persisted['search3_messenger_handoffs'][$operation]['state'] !== 'prepared', 'durable reservation before create');
    $wires[] = json_decode($raw, true, 32, JSON_THROW_ON_ERROR)['action'];
    return ['status' => 200, 'body' => json_encode(['ok' => true, 'state' => 'ready', 'request_hash' => $record['request_hash'],
        'links' => ['telegram' => 'https://t.me/synthetic_bot?start=s3_' . str_repeat('a', 32), 'max' => 'https://max.ru/synthetic_bot?start=s3_' . str_repeat('b', 32)]])];
};
$tx = ['action' => 'handoff_create', 'operation_id' => $operation, 'csrf' => $request['csrf']];
$ready = AnyTourSearch3MessengerHandoff::transact($tx, $session, $now, $signer, $checkpoint, $transport);
mh_check($ready['state'] === 'ready' && isset($ready['links']['max']) && count($persisted['search3_messenger_handoffs']) === 1, 'one persisted immutable operation');
AnyTourSearch3MessengerHandoff::transact($tx, $session, $now + 1, $signer, $checkpoint, $transport);
mh_check($wires === ['create', 'status'], 'repeated create becomes status, never another create');
mh_check($persisted['search3_messenger_handoffs'][$operation]['raw'] === $record['raw'], 'original bytes retained after readback');

$unknown = $restored;
$lost = static function (): never { throw new RuntimeException('PRIVATE_NETWORK_DETAIL'); };
$result = AnyTourSearch3MessengerHandoff::transact($tx, $unknown, $now, $signer, $checkpoint, $lost);
mh_check($result['state'] === 'unknown' && $result['error'] === 'receiver_unknown', 'unknown persisted and private exception omitted');
$actions = [];
$missing = static function ($url, $raw) use (&$actions): array { $actions[] = json_decode($raw, true)['action']; return ['status' => 200, 'body' => '{"ok":true,"state":"missing"}']; };
$result = AnyTourSearch3MessengerHandoff::transact($tx, $unknown, $now + 1, $signer, $checkpoint, $missing);
mh_check($result['state'] === 'missing' && $actions === ['status'] && $unknown['search3_messenger_handoffs'][$operation]['state'] === 'unknown', 'missing status does not replay UNKNOWN');
$unknown['anex_context_generation'] = 8;
AnyTourSearch3MessengerHandoff::transact(array_replace($tx, ['action' => 'handoff_status']), $unknown, $now + 2, $signer, $checkpoint, $missing);
mh_error(static function () use ($tx, &$restored, $now, $signer, $checkpoint, $transport): void {
    $restored['anex_context_generation'] = 8; AnyTourSearch3MessengerHandoff::transact($tx, $restored, $now, $signer, $checkpoint, $transport);
}, 'handoff_stale');
$cancel = $session; $cancel['search3_messenger_handoffs'][$operation] = $record;
$cancelled = AnyTourSearch3MessengerHandoff::transact(array_replace($tx, ['action' => 'handoff_revoke']), $cancel, $now, [], $checkpoint, $lost);
mh_check($cancelled['state'] === 'revoked', 'unsent prepared operation revoked locally without transport');
$blocked = $restored; $blocked['anex_context_generation'] = 7;
mh_error(static function () use ($tx, &$blocked, $now, $checkpoint, $transport): void { AnyTourSearch3MessengerHandoff::transact($tx, $blocked, $now, [], $checkpoint, $transport); }, 'signer_unavailable');
mh_check($blocked['search3_messenger_handoffs'][$operation]['state'] === 'prepared', 'missing signer does not consume create');
$blocked = $session; $blocked['search3_messenger_handoffs'][$operation] = $record; $sent = false;
mh_error(static function () use ($tx, &$blocked, $now, $signer, &$sent): void {
    AnyTourSearch3MessengerHandoff::transact($tx, $blocked, $now, $signer,
        static function (): never { throw new RuntimeException('checkpoint_failed'); },
        static function () use (&$sent): void { $sent = true; });
}, 'checkpoint_failed');
mh_check(!$sent, 'failed durable checkpoint never invokes transport');
// The later wire-intent checkpoint can outlive a still-valid canonical freezer result.
[$wireSession, $wireRequest, $wireFacts, $wireInitial] = mh_fixture([], true, 1700000000);
$wirePreview = mh_freeze($wireRequest, $wireSession, $wireInitial);
$wireOperation = $wirePreview['operation_id']; $wireDeadline = $wirePreview['observation']['price']['expiresAt'];
$wireStart = $wireDeadline - 2; $wireSupplierCalls = $wireFacts->calls;
$wireSigner = array_replace($signer, ['not_before' => $wireStart - 1, 'not_after' => $wireDeadline + 86400]);
$wireTx = ['action' => 'handoff_create', 'operation_id' => $wireOperation, 'csrf' => $wireRequest['csrf']];
foreach ([$wireDeadline, $wireDeadline + 1, $wireStart - 1, 'invalid-clock'] as $afterSave) {
    $wireCopy = unserialize(serialize($wireSession)); $wireNow = $wireStart; $wireSent = 0;
    mh_error(static function () use ($wireTx, &$wireCopy, $wireStart, $wireSigner, &$wireNow, $afterSave, &$wireSent): void {
        AnyTourSearch3MessengerHandoff::transact($wireTx, $wireCopy, $wireStart, $wireSigner,
            static function (array &$unused) use (&$wireNow, $afterSave): void { $wireNow = $afterSave; },
            static function () use (&$wireSent): array { ++$wireSent; return ['status' => 503, 'body' => '{}']; },
            static function () use (&$wireNow) { return $wireNow; });
    }, 'handoff_stale');
    mh_check($wireSent === 0 && $wireCopy['search3_messenger_handoffs'][$wireOperation]['state'] === 'unknown',
        'expired or invalid post-save clock has no CREATE and retains UNKNOWN');
    if ($afterSave === $wireDeadline) $expiredWire = $wireCopy;
}
$wireCopy = unserialize(serialize($wireSession)); $wireNow = $wireStart + 1; $wireSent = 0;
mh_error(static function () use ($wireTx, &$wireCopy, $wireStart, $wireSigner, &$wireNow, &$wireSent): void {
    AnyTourSearch3MessengerHandoff::transact($wireTx, $wireCopy, $wireStart, $wireSigner,
        static function (array &$unused) use (&$wireNow, $wireStart): void { $wireNow = $wireStart; },
        static function () use (&$wireSent): array { ++$wireSent; return ['status' => 503, 'body' => '{}']; },
        static function () use (&$wireNow): int { return $wireNow; });
}, 'handoff_stale');
mh_check($wireSent === 0 && $wireCopy['search3_messenger_handoffs'][$wireOperation]['state'] === 'unknown',
    'post-save clock cannot roll back below a later canonical observation, even to request-entry time');
$expiredActions = [];
$expiredReply = AnyTourSearch3MessengerHandoff::transact($wireTx, $expiredWire, $wireDeadline + 2, $wireSigner,
    static function (array &$unused): void {},
    static function ($url, $raw) use (&$expiredActions): array {
        $expiredActions[] = json_decode($raw, true)['action']; return ['status' => 200, 'body' => '{"ok":true,"state":"missing"}'];
    });
mh_check($expiredActions === ['status'] && $expiredReply['state'] === 'missing'
    && $expiredWire['search3_messenger_handoffs'][$wireOperation]['state'] === 'unknown',
    'blocked post-save CREATE stays status-only after expiry, never replayed');

$wireCopy = unserialize(serialize($wireSession)); $wireNow = $wireStart; $freshHeaders = []; $freshRaw = '';
AnyTourSearch3MessengerHandoff::transact($wireTx, $wireCopy, $wireStart, $wireSigner,
    static function (array &$unused) use (&$wireNow): void { ++$wireNow; },
    static function ($url, $raw, $headers) use (&$freshHeaders, &$freshRaw): array {
        $freshHeaders = $headers; $freshRaw = $raw; return ['status' => 503, 'body' => '{}'];
    }, static function () use (&$wireNow): int { return $wireNow; });
mh_check(in_array('X-Anytour-Search3-Timestamp: ' . ($wireStart + 1), $freshHeaders, true)
    && $freshRaw === $wireSession['search3_messenger_handoffs'][$wireOperation]['raw'],
    'still-live CREATE refreshes signature timestamp after save without changing immutable bytes');
$wireCopy = unserialize(serialize($wireSession)); $wireNow = $wireStart; $wireSent = 0;
mh_error(static function () use ($wireTx, &$wireCopy, $wireStart, $wireSigner, &$wireNow, &$wireSent): void {
    AnyTourSearch3MessengerHandoff::transact($wireTx, $wireCopy, $wireStart,
        array_replace($wireSigner, ['not_after' => $wireStart + 1]),
        static function (array &$unused) use (&$wireNow): void { ++$wireNow; },
        static function () use (&$wireSent): array { ++$wireSent; return ['status' => 503, 'body' => '{}']; },
        static function () use (&$wireNow): int { return $wireNow; });
}, 'signer_unavailable');
mh_check($wireSent === 0 && $wireCopy['search3_messenger_handoffs'][$wireOperation]['state'] === 'unknown',
    'key expiry during save prevents CREATE and preserves UNKNOWN');
mh_check($wireFacts->calls === $wireSupplierCalls, 'post-save boundary cases make zero additional supplier calls');
$rotated = $session;
mh_error(static function () use ($tx, &$rotated, $now, $signer, $checkpoint, $transport): void {
    AnyTourSearch3MessengerHandoff::transact($tx, $rotated, $now, array_replace($signer, ['key_id' => 'another-key']), $checkpoint, $transport);
}, 'signer_unavailable');
$revoke = $session;
$unsafeReady = AnyTourSearch3MessengerHandoff::transact(array_replace($tx, ['action' => 'handoff_revoke']), $revoke, $now, $signer, $checkpoint, $transport);
mh_check($unsafeReady['state'] === 'revoke_unknown' && !isset($unsafeReady['links']), 'lost revoke cannot expose ready links');
$read = $session;
$badLink = static fn() => ['status' => 200, 'body' => json_encode(['ok' => true, 'state' => 'ready', 'request_hash' => $record['request_hash'],
    'links' => ['telegram' => 'https://t.me@evil.test/bot', 'max' => 'https://max.ru/bot']])];
$badReply = AnyTourSearch3MessengerHandoff::transact($tx, $read, $now, $signer, $checkpoint, $badLink);
mh_check(isset($badReply['error']) && !isset($badReply['links']), 'receiver response cannot inject foreign links');
$keyDir = sys_get_temp_dir() . '/search3-signing-test-' . bin2hex(random_bytes(8));
mkdir($keyDir, 0700); mkdir($keyDir . '/web', 0700);
$keyPath = $keyDir . '/signing.json';
file_put_contents($keyPath, json_encode(['key_id' => $signer['key_id'], 'secret_key_base64' => base64_encode($signer['secret_key']),
    'not_before' => $signer['not_before'], 'not_after' => $signer['not_after']])); chmod($keyPath, 0600);
try {
    mh_check(AnyTourSearch3MessengerHandoff::signer($keyPath, $keyDir . '/web') === $signer, 'dedicated private external key read');
    mh_error(static fn() => AnyTourSearch3MessengerHandoff::signer($keyPath, $keyDir), 'signer_unavailable');
    chmod($keyPath, 0644); clearstatcache();
    mh_error(static fn() => AnyTourSearch3MessengerHandoff::signer($keyPath, $keyDir . '/web'), 'signer_unavailable');
} finally { unlink($keyPath); rmdir($keyDir . '/web'); rmdir($keyDir); }
$curlProcess = proc_open([PHP_BINARY, '-n', __FILE__, '--curl-fixture'], [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
$curl = json_decode(stream_get_contents($pipes[1]), true); $curlError = stream_get_contents($pipes[2]);
fclose($pipes[1]); fclose($pipes[2]);
mh_check(proc_close($curlProcess) === 0 && $curlError === '', 'native curl branch fixture completed');
mh_check($curl === ['fixed' => true, 'timeout' => 8, 'connect' => 3, 'redirects' => false, 'tls' => true, 'oversize' => true, 'closed' => true], 'fixed URL, HTTPS, bounded body/time and no redirects');

$server = ['HTTP_HOST' => 'anytoour.ru', 'REQUEST_METHOD' => 'POST', 'HTTP_ORIGIN' => 'https://anytoour.ru', 'HTTP_SEC_FETCH_SITE' => 'same-origin'];
mh_check(AnyTourSearch3MessengerHandoff::authorize($server, $request, $session), 'same origin and exact csrf');
foreach (['HTTP_HOST' => 'attacker.test', 'REQUEST_METHOD' => 'GET', 'HTTP_ORIGIN' => 'https://other.test', 'HTTP_SEC_FETCH_SITE' => 'same-site'] as $name => $value) {
    mh_check(!AnyTourSearch3MessengerHandoff::authorize(array_replace($server, [$name => $value]), $request, $session), 'reject ' . $name);
}
mh_check(!AnyTourSearch3MessengerHandoff::authorize($server, array_replace($request, ['csrf' => 'wrong']), $session), 'csrf mismatch rejected');

// TV uses the SAME wire producer after an INTERNAL locked-server capture.
// No HTTP capture action, browser DTO, supplier, PDO or receiver call is used.
function mh_tv_fixture(string $choice='manager-deferred'): array
{
    $at=time()-3; $deadline=$at+900;
    $pins=['generation'=>9,'search_ref'=>str_repeat('a',32),'offer_ref'=>'tourvisor:'.str_repeat('b',64),
        'local_hotel_id'=>245,'choice_ref'=>$choice,'provider'=>'tourvisor'];
    $selected=$choice!=='manager-deferred';
    $terms=['source'=>'tourvisor','hotel'=>['uiId'=>245,'namespace'=>'search3-ui','name'=>'Synthetic TV hotel','resort'=>'Synthetic resort'],
        'trip'=>['origin'=>'Москва','countryUiId'=>'4','departure'=>'2027-03-19','nights'=>7,'stayEnd'=>'2027-03-26',
            'stayEndBasis'=>'shown-stay-end','adults'=>2,'childAges'=>[0,0,8]],
        'stay'=>['room'=>'Premium garden','meal'=>'AI','placement'=>'DBL+3CHD','operator'=>'FUN&SUN'],
        'flights'=>['state'=>$selected?'selected':'manager-deferred',
            'outward'=>$selected?[['label'=>'Explicit TV outward TV101']]:[],
            'inward'=>$selected?[['label'=>'Explicit TV inward TV102']]:[]],
        'price'=>['amount'=>$selected?'207867.00':'200000.00','currency'=>'RUB','state'=>'checked',
            'description'=>'Observed whole-party Tourvisor response; conditions remain unverified.',
            'expiresAt'=>$deadline,'basis'=>$selected?'selected-pair':'shown-offer',
            'finalPriceVerified'=>false,'authority'=>'browser-observation-only']];
    $state=['tourvisor_context_generation'=>9,'search3_handoff_csrf'=>str_repeat('d',64)];
    return [$pins,$terms,$state,$at,$deadline];
}
function mh_tv_capture(array $pins,array $terms,array &$state,int $at,int $deadline,?callable $save=null,?callable $clock=null): array
{
    return AnyTourTourvisorSelectedTourHandoff::capture($pins,$terms,$state,$at,$deadline,$at,
        $save??static function(array &$unused):void {},$clock??static fn():int=>$at);
}
$tvBodies=[];
foreach (['manager-deferred','tourvisor_pair:'.str_repeat('e',64)] as $tvChoice) {
    [$tvPins,$tvTerms,$tvSession,$tvAt,$tvDeadline]=mh_tv_fixture($tvChoice);
    $tvCapture=mh_tv_capture($tvPins,$tvTerms,$tvSession,$tvAt,$tvDeadline);
    mh_check($tvCapture['status']==='ready','TV internal retained capture ready');
    $tvRequest=['action'=>'handoff_freeze']+$tvPins+['csrf'=>$tvSession['search3_handoff_csrf']];
    $tvPreview=mh_freeze($tvRequest,$tvSession,$tvAt);
    $tvOp=$tvPreview['operation_id']; $tvWire=$tvSession['search3_messenger_handoffs'][$tvOp];
    $tvBody=json_decode($tvWire['raw'],true,32,JSON_THROW_ON_ERROR);$tvBodies[]=$tvBody;
    mh_check(['binding'=>$tvBody['binding'],'observation'=>$tvBody['observation']]===$tvCapture['record'],
        'TV wire reuses sole canonical record exactly, no second projection');
    mh_check($tvWire['record_sha256']===$tvCapture['record_sha256']
        && $tvWire['provenance']['owner']==='tourvisor_handoff_records','TV record hash and owner preserved');
    mh_check($tvPreview['observation']['trip']['childAges']===[0,0,8]
        && $tvPreview['observation']['price']['amount']===$tvTerms['price']['amount']
        && $tvPreview['observation']['capturedAt']===$tvAt*1000
        && $tvPreview['observation']['price']['expiresAt']===$tvDeadline,'TV original ages/own whole price/time/deadline unchanged');
    mh_check($tvPreview['observation']['price']['finalPriceVerified']===false
        && $tvPreview['observation']['price']['authority']==='browser-observation-only','TV checked is never final verified');
    mh_check($tvPreview['observation']['flights']===$tvTerms['flights'],'TV explicit deferred has empty legs, selected exact legs retained');
    mh_check(mh_freeze(array_reverse($tvRequest,true),$tvSession,$tvAt+1)===$tvPreview,'TV reorder and repeat reuse original operation');
    $tvSame=mh_tv_capture(array_reverse($tvPins,true),array_reverse($tvTerms,true),$tvSession,$tvAt,$tvDeadline);
    mh_check($tvSame===$tvCapture,'TV server key ordering cannot mint a new record');
    $tvRestored=unserialize(serialize($tvSession));
    mh_check(mh_freeze($tvRequest,$tvRestored,$tvAt+2)===$tvPreview,'TV restart preserves frozen bytes');
    $tvBefore=$tvSession; $tvChanged=$tvTerms;$tvChanged['price']['amount']='999999.00';
    $tvHeld=mh_tv_capture($tvPins,$tvChanged,$tvSession,$tvAt,$tvDeadline);
    mh_check($tvHeld['status']==='held' && $tvSession===$tvBefore,'TV changed terms cannot remint or overwrite old record');
    $tvSigner=array_replace($signer,['not_before'=>$tvAt-1]);
    $tvCalls=[];$tvTx=['action'=>'handoff_create','operation_id'=>$tvOp,'csrf'=>$tvSession['search3_handoff_csrf']];
    $tvTransport=static function(string $url,string $raw,array $unused) use (&$tvCalls):array {
        $body=json_decode($raw,true);$tvCalls[]=$body['action'];
        return ['status'=>200,'body'=>json_encode(['ok'=>true,'state'=>'ready','request_hash'=>hash('sha256',$raw),
            'links'=>['telegram'=>'https://t.me/example_bot?start=synthetic','max'=>'https://max.ru/example_bot?start=synthetic']])];
    };
    $tvReady=AnyTourSearch3MessengerHandoff::transact($tvTx,$tvSession,$tvAt,$tvSigner,
        static function(array &$unused):void {},$tvTransport,static fn():int=>$tvAt);
    mh_check($tvReady['state']==='ready' && $tvCalls===['create'],'TV uses same signed transport with no ANEX dependency');
    $tvLost=$tvBefore; $tvActions=[];
    $tvUnknownTransport=static function(string $url,string $raw,array $unused) use (&$tvActions):array {
        $body=json_decode($raw,true);$tvActions[]=$body['action'];
        if($body['action']==='create')throw new RuntimeException('private receiver timeout');
        return ['status'=>200,'body'=>'{"ok":true,"state":"missing"}'];
    };
    $tvLostResult=AnyTourSearch3MessengerHandoff::transact($tvTx,$tvLost,$tvAt,$tvSigner,
        static function(array &$unused):void {},$tvUnknownTransport,static fn():int=>$tvAt);
    $tvMissingResult=AnyTourSearch3MessengerHandoff::transact($tvTx,$tvLost,$tvAt+1,$tvSigner,
        static function(array &$unused):void {},$tvUnknownTransport,static fn():int=>$tvAt+1);
    mh_check($tvLostResult['state']==='unknown' && $tvMissingResult['state']==='missing'
        && $tvActions===['create','status'],'TV UNKNOWN create is status-only, never replay');
    $tvLateWire=$tvBefore;$tvWireTimes=[$tvAt,$tvDeadline];$tvLateSent=0;
    mh_error(static function()use($tvTx,&$tvLateWire,$tvAt,$tvSigner,&$tvWireTimes,&$tvLateSent):void {
        AnyTourSearch3MessengerHandoff::transact($tvTx,$tvLateWire,$tvAt,$tvSigner,static function(array &$unused):void {},
            static function()use(&$tvLateSent):array {++$tvLateSent;return [];},
            static function()use(&$tvWireTimes):int {return array_shift($tvWireTimes);});
    },'handoff_stale');
    mh_check($tvLateSent===0 && $tvLateWire['search3_messenger_handoffs'][$tvOp]['state']==='unknown',
        'TV expiry after durable wire checkpoint preserves UNKNOWN and sends nothing');
    foreach (['missing','generation','bytes'] as $tvChange) {
        $tvStale=$tvBefore;
        if($tvChange==='missing')unset($tvStale['tourvisor_handoff_records']);
        if($tvChange==='generation')$tvStale['tourvisor_context_generation']=10;
        if($tvChange==='bytes'){ $tvKey=array_key_first($tvStale['tourvisor_handoff_records']);$tvStale['tourvisor_handoff_records'][$tvKey]['json'].=' '; }
        $tvStaleCalls=0;
        $tvRun=static function()use($tvTx,&$tvStale,$tvAt,$tvSigner,&$tvStaleCalls):void {
            AnyTourSearch3MessengerHandoff::transact($tvTx,$tvStale,$tvAt,$tvSigner,static function(array &$unused):void {},
                static function()use(&$tvStaleCalls):array {++$tvStaleCalls;return [];},static fn():int=>$tvAt);
        };
        if($tvChange==='bytes')mh_reject($tvRun,'record.integrity');
        else mh_error($tvRun,'handoff_stale');
        mh_check($tvStaleCalls===0,'TV stale/missing/tampered cannot reach transport');
    }
    mh_error(static function()use($tvRequest,$tvSession,$tvAt):void {$s=$tvSession;mh_freeze($tvRequest+['observation'=>[]],$s,$tvAt);},'invalid_request');
    $tvEmpty=['tourvisor_context_generation'=>9];
    mh_reject(static function()use($tvRequest,&$tvEmpty,$tvAt):void {mh_freeze($tvRequest,$tvEmpty,$tvAt);},'record.current_retained');
}
[$tvPins,$tvTerms,$tvBase,$tvAt,$tvDeadline]=mh_tv_fixture();
$tvNoGeneration=['search3_handoff_csrf'=>str_repeat('d',64)];$tvNoGenerationBefore=$tvNoGeneration;
$tvResult=mh_tv_capture($tvPins,$tvTerms,$tvNoGeneration,$tvAt,$tvDeadline);
mh_check($tvResult['status']==='held' && $tvNoGeneration===$tvNoGenerationBefore,'TV capture cannot establish its own current generation');
foreach ([
    static function(array &$t):void {$t['price']['state']='listing';},
    static function(array &$t):void {$t['price']['amount']=null;},
    static function(array &$t):void {$t['price']['finalPriceVerified']=true;},
    static function(array &$t):void {$t['price']['expiresAt']++;},
    static function(array &$t):void {$t['trip']['childAges']=[0,'0',8];},
    static function(array &$t):void {$t['hotel']['uiId']=246;},
    static function(array &$t):void {$t['stay']['room']='';},
    static function(array &$t):void {$t['flights']['outward']=[['label'=>'invented default']];},
    static function(array &$t):void {$t['source']='anex';},
] as $tvMutate) {
    $tvBad=$tvTerms;$tvMutate($tvBad);$tvState=$tvBase;
    $tvResult=mh_tv_capture($tvPins,$tvBad,$tvState,$tvAt,$tvDeadline);
    mh_check($tvResult['status']==='held' && $tvState===$tvBase,'invalid TV server terms held before mutation');
}
$tvClockState=$tvBase;
$tvResult=mh_tv_capture($tvPins,$tvTerms,$tvClockState,$tvAt,$tvDeadline,null,static fn():int=>$tvAt-1);
mh_check($tvResult['status']==='held' && $tvClockState===$tvBase,'TV rollback clock rejected before capture');
$tvExpired=$tvBase;
$tvResult=mh_tv_capture($tvPins,$tvTerms,$tvExpired,$tvAt,$tvDeadline,null,static fn():int=>$tvDeadline);
mh_check($tvResult['status']==='held' && $tvExpired===$tvBase,'TV exact expiry boundary cannot capture');
$tvUnknown=$tvBase;
$tvResult=mh_tv_capture($tvPins,$tvTerms,$tvUnknown,$tvAt,$tvDeadline,
    static function(array &$unused):void {throw new RuntimeException('private save failure');});
mh_check($tvResult['status']==='held' && $tvResult['missing'][0]['field']==='record.checkpoint_unknown','TV checkpoint failure seals unknown');
$tvRetry=mh_tv_capture($tvPins,$tvTerms,$tvUnknown,$tvAt,$tvDeadline);
mh_check($tvRetry['status']==='held','TV unknown capture cannot remint');
$tvCorrupt=$tvBase;
mh_tv_capture($tvPins,$tvTerms,$tvCorrupt,$tvAt,$tvDeadline);
$tvKey=array_key_first($tvCorrupt['tourvisor_handoff_records']);$tvCorrupt['tourvisor_handoff_records'][$tvKey]=null;
$tvBeforeCorrupt=$tvCorrupt;$tvResult=mh_tv_capture($tvPins,$tvTerms,$tvCorrupt,$tvAt,$tvDeadline);
mh_check($tvResult['status']==='held' && $tvCorrupt===$tvBeforeCorrupt,'TV corrupt occupied slot cannot remint');
$tvLimit=$tvBase;$tvLimit['tourvisor_handoff_records']=array_fill(0,8,['status'=>'unknown']);
$tvBeforeLimit=$tvLimit;$tvResult=mh_tv_capture($tvPins,$tvTerms,$tvLimit,$tvAt,$tvDeadline);
mh_check($tvResult['status']==='held' && $tvLimit===$tvBeforeLimit,'TV limit cannot evict UNKNOWN slots');
$tvLate=$tvBase;$tvTimes=[$tvAt,$tvDeadline];
$tvResult=mh_tv_capture($tvPins,$tvTerms,$tvLate,$tvAt,$tvDeadline,null,static function()use(&$tvTimes):int{return array_shift($tvTimes);});
mh_check($tvResult['status']==='held','TV checkpoint crossing original expiry cannot expose ready record');

$tvLargeChoice='tourvisor_pair:'.str_repeat('f',64);
[$tvLargePins,$tvLargeTerms,$tvLargeState,$tvLargeAt,$tvLargeDeadline]=mh_tv_fixture($tvLargeChoice);
$tvSlashLabel=str_repeat('😀',500).str_repeat('/',500);
$tvLargeTerms['flights']['outward']=array_fill(0,4,['label'=>$tvSlashLabel]);
$tvLargeTerms['flights']['inward']=array_fill(0,4,['label'=>$tvSlashLabel]);
$tvReceiverOversize=['binding'=>['record_id'=>'tourvisor:'.str_repeat('a',32),'revision'=>1],
    'observation'=>['schema'=>1,'kind'=>'search3_browser_observation','capturedAt'=>$tvLargeAt*1000]+$tvLargeTerms];
mh_check(strlen(json_encode($tvReceiverOversize,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))<24576
    && strlen(json_encode($tvReceiverOversize,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE))>24576,
    'mixed Unicode/slash fixture crosses the actual receiver encoding limit only');
$tvBeforeLarge=$tvLargeState;
$tvTooLarge=mh_tv_capture($tvLargePins,$tvLargeTerms,$tvLargeState,$tvLargeAt,$tvLargeDeadline);
mh_check($tvTooLarge['status']==='held' && $tvTooLarge['missing'][0]['field']==='record.receiver_size'
    && $tvLargeState===$tvBeforeLarge,'capture enforces receiver exact size before persistence');
[$tvLargePins,$tvAllowedTerms,$tvCachedState,$tvLargeAt,$tvLargeDeadline]=mh_tv_fixture($tvLargeChoice);
$tvAllowedLabel=str_repeat('😀',500).str_repeat('/',400);
$tvAllowedTerms['flights']['outward']=array_fill(0,4,['label'=>$tvAllowedLabel]);
$tvAllowedTerms['flights']['inward']=array_fill(0,4,['label'=>$tvAllowedLabel]);
$tvAllowed=mh_tv_capture($tvLargePins,$tvAllowedTerms,$tvCachedState,$tvLargeAt,$tvLargeDeadline);
mh_check($tvAllowed['status']==='ready','large valid receiver-compatible record remains usable');
$tvReceiverLargeAllowed=$tvAllowed['record'];
$tvCacheKey=array_key_first($tvCachedState['tourvisor_handoff_records']);
$tvOversizeJSON=json_encode($tvReceiverOversize,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
$tvCachedState['tourvisor_handoff_records'][$tvCacheKey]['json']=$tvOversizeJSON;
$tvCachedState['tourvisor_handoff_records'][$tvCacheKey]['json_sha256']=hash('sha256',$tvOversizeJSON);
$tvCachedHeld=AnyTourTourvisorSelectedTourHandoff::freeze($tvLargePins,$tvCachedState,$tvLargeAt,static fn():int=>$tvLargeAt);
mh_check($tvCachedHeld['status']==='held' && $tvCachedHeld['missing'][0]['field']==='record.receiver_size',
    'cached old-version record also enforces exact receiver encoding limit');

// Actual accepted receiver code, pinned by CI; no SQL, provider or bot is invoked.
$receiver = getenv('SEARCH3_RECEIVER_SOURCE');
if (is_string($receiver) && $receiver !== '') {
    require_once $receiver . '/services/Search3TourHandoffSnapshot.php';
    require_once $receiver . '/services/Search3TourHandoffTrust.php';
    mh_check(Search3TourHandoffSnapshot::normalize($tvReceiverOversize)===null,
        'actual receiver rejects the mixed Unicode/slash oversize boundary');
    mh_check(Search3TourHandoffSnapshot::normalize($tvReceiverLargeAllowed)!==null,
        'actual receiver accepts large bounded Unicode/slash record');
    foreach ($tvBodies as $tvBody) mh_check(Search3TourHandoffSnapshot::normalize(
        ['binding'=>$tvBody['binding'],'observation'=>$tvBody['observation']]) !== null,
        'actual frozen receiver accepts TV deferred and selected records unchanged');
    $trust = new ReflectionProperty(Search3TourHandoffTrust::class, 'keys');
    $trust->setValue(null, ['ephemeral-fixture' => ['producer' => 'search3', 'project' => 'anytour', 'not_before' => $now - 1,
        'not_after' => $now + 86400, 'public_key' => base64_encode($public)]]);
    mh_check(Search3TourHandoffSnapshot::normalize(['binding' => $body['binding'], 'observation' => $body['observation']]) !== null, 'actual receiver accepts canonical retained record');
    $familyBody = json_decode($family['search3_messenger_handoffs'][$familyPreview['operation_id']]['raw'], true);
    mh_check(Search3TourHandoffSnapshot::normalize(['binding' => $familyBody['binding'], 'observation' => $familyBody['observation']]) !== null, 'actual receiver accepts exact pair and duplicate infant ages');
    $server = [];
    foreach ($headers as $header) if (str_contains($header, ': ')) { [$k, $v] = explode(': ', $header, 2); $server['HTTP_' . str_replace('-', '_', strtoupper($k))] = $v; }
    mh_check(Search3TourHandoffTrust::verify($record['raw'], $server, $now)['ok'], 'actual receiver verifies producer signature');
    mh_check(!Search3TourHandoffTrust::verify($record['raw'] . ' ', $server, $now)['ok'], 'receiver rejects raw-byte tampering');
}
echo 'Search3 retained producer: ' . $GLOBALS['mh_checks'] . " checks PASS; external supplier/bot/SQL writes=0.\n";

