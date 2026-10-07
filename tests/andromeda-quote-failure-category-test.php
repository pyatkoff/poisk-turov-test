<?php
declare(strict_types=1);

$sourcePath = __DIR__ . '/../v2/api-andromeda-quote-preview.php';
$source = file_get_contents($sourcePath);
if (!is_string($source)) throw new RuntimeException('FAILURE_SOURCE_READ');

$extractFunction = static function (string $source, string $name): string {
    $needle = 'function ' . $name;
    $start = strpos($source, $needle);
    if ($start === false) throw new RuntimeException('FAILURE_FUNCTION_MISSING_' . $name);
    $open = strpos($source, '{', $start);
    if ($open === false) throw new RuntimeException('FAILURE_FUNCTION_OPEN_' . $name);
    $depth = 0;
    $length = strlen($source);
    for ($i = $open; $i < $length; ++$i) {
        if ($source[$i] === '{') ++$depth;
        elseif ($source[$i] === '}' && --$depth === 0) return substr($source, $start, $i - $start + 1);
    }
    throw new RuntimeException('FAILURE_FUNCTION_CLOSE_' . $name);
};

eval($extractFunction($source, 'anytour_andromeda_quote_failure_category'));
eval($extractFunction($source, 'anytour_andromeda_quote_supplier_failure'));

$cases = [
    'ANDROMEDA_TRANSPORT_ERROR' => 'supplier_transport',
    'ANDROMEDA_NETWORK_TRANSPORT_FAILURE' => 'supplier_transport',
    'ANDROMEDA_CURL_REQUIRED' => 'supplier_transport',
    'ANDROMEDA_HTTP_ERROR' => 'supplier_http',
    'ANDROMEDA_SUPPLIER_ERROR' => 'supplier_rejected',
    'ANDROMEDA_INVALID_RESPONSE' => 'supplier_response',
    'ANDROMEDA_INVALID_CLAIM_RESPONSE' => 'supplier_response',
    'ANDROMEDA_INVALID_PACKAGE_RESPONSE' => 'supplier_response',
    'ANDROMEDA_RESPONSE_TOO_LARGE' => 'supplier_response',
    'ANDROMEDA_SECRET_ECHO' => 'supplier_response',
    'ANDROMEDA_LOGIN_REQUIRED' => 'supplier_auth',
    'ANDROMEDA_CREDENTIALS_REQUIRED' => 'supplier_auth',
    'ANDROMEDA_CLAIM_SESSION_INVALID' => 'supplier_auth',
    'ANDROMEDA_OPERATOR_CREDENTIALS_PAIR_REQUIRED' => 'supplier_auth',
    'ANDROMEDA_OPERATOR_CREDENTIALS_INVALID' => 'supplier_auth',
    'ANDROMEDA_QUOTE_CONTEXT_MISMATCH' => 'quote_state',
    'ANDROMEDA_SELECTION_CONTEXT_MISMATCH' => 'quote_state',
    'ANDROMEDA_SELECTION_MAPPING_UNAVAILABLE' => 'quote_state',
    'ANDROMEDA_QUOTE_ATTEMPT_INVALID' => 'quote_state',
    'ANDROMEDA_QUOTE_RESULT_INVALID' => 'quote_state',
    'ANDROMEDA_QUOTE_MONEY_INVALID' => 'quote_state',
    'ANDROMEDA_QUOTE_PRIVATE_STATE' => 'quote_state',
    'ANDROMEDA_QUOTE_PROVENANCE_INVALID' => 'quote_state',
    'ANDROMEDA_QUOTE_CHECKPOINT_INVALID' => 'quote_state',
    'ANDROMEDA_QUOTE_CHECKPOINT_CHANGED' => 'quote_state',
    'ANDROMEDA_QUOTE_CHECKPOINT_FAILED' => 'quote_state',
    'ANDROMEDA_QUOTE_LOCK_FAILED' => 'quote_state',
    'ANDROMEDA_QUOTE_NOT_OFFER' => 'quote_state',
    'ANDROMEDA_FLIGHT_STATE_CHANGED' => 'quote_state',
    'ANDROMEDA_FLIGHT_STATE_FAILED' => 'quote_state',
    'ANDROMEDA_FLIGHT_STATE_INVALID' => 'quote_state',
    'ANDROMEDA_FLIGHT_SELECTION_INVALID' => 'quote_state',
    'ANDROMEDA_FLIGHT_UID_INVALID' => 'quote_state',
    'ANDROMEDA_FLIGHT_OPTIONS_INVALID' => 'quote_state',
    'ANDROMEDA_FLIGHT_REF_INVALID' => 'quote_state',
    'ANDROMEDA_FLIGHT_REFS_INVALID' => 'quote_state',
    'ANDROMEDA_FLIGHT_CONTEXT_INVALID' => 'quote_state',
    'ANDROMEDA_FLIGHT_ALREADY_SELECTED' => 'quote_state',
    'ANDROMEDA_SELECTED_FLIGHTS_INVALID' => 'quote_state',
    'ANDROMEDA_FINAL_PRICE_MISSING' => 'quote_state',
    'ANDROMEDA_CLAIM_SHAPE_INVALID' => 'quote_state',
    'ANDROMEDA_CLAIM_TOO_LARGE' => 'quote_state',
    'ANDROMEDA_CLAIM_REQUEST_BUDGET' => 'quote_state',
    'ANDROMEDA_CLAIM_ACTION_NOT_ALLOWED' => 'quote_state',
    'ANDROMEDA_PACKAGE_DISABLED' => 'quote_state',
    'ANDROMEDA_PACKAGE_REPLAY_REFUSED' => 'quote_state',
    'ANDROMEDA_INVALID_PACKAGE_ID' => 'quote_state',
];

$allowed = [
    'supplier_transport', 'supplier_http', 'supplier_rejected', 'supplier_response',
    'supplier_auth', 'quote_state', 'internal',
];

foreach ($cases as $message => $expected) {
    $actual = anytour_andromeda_quote_failure_category(new RuntimeException($message));
    if ($actual !== $expected || !in_array($actual, $allowed, true)) {
        throw new RuntimeException('FAILURE_CATEGORY_' . $message);
    }
    $public = anytour_andromeda_quote_supplier_failure(new RuntimeException($message));
    if (in_array($expected, ['quote_state', 'supplier_response'], true)) {
        if (($public['failure_reason'] ?? null) !== $message) {
            throw new RuntimeException('FAILURE_REASON_LOST_' . $message);
        }
    } elseif (array_key_exists('failure_reason', $public)) {
        throw new RuntimeException('NON_STATE_REASON_EXPOSED');
    }
}

foreach (['', 'unexpected', 'ANDROMEDA_UNKNOWN', 'secret sid=abc url=https://gateway.samo.ru/api/'] as $message) {
    if (anytour_andromeda_quote_failure_category(new RuntimeException($message)) !== 'internal') {
        throw new RuntimeException('FAILURE_CATEGORY_FALLBACK');
    }
    if (array_key_exists('failure_reason', anytour_andromeda_quote_supplier_failure(new RuntimeException($message)))) {
        throw new RuntimeException('UNKNOWN_REASON_EXPOSED');
    }
}

// A familiar prefix or suffix cannot turn raw details into a public reason.
foreach (['ANDROMEDA_SELECTED_FLIGHTS_INVALID secret=private',
    'ANDROMEDA_TOKEN_PRIVATE', "ANDROMEDA_QUOTE_CHECKPOINT_INVALID\n/private/path"] as $message) {
    $public = anytour_andromeda_quote_supplier_failure(new RuntimeException($message));
    if ($public['failure_category'] !== 'internal' || array_key_exists('failure_reason', $public)) {
        throw new RuntimeException('UNLISTED_REASON_EXPOSED');
    }
}

$secret = 'secret sid=abc url=https://gateway.samo.ru/api/?action=changeservice';
$payload = anytour_andromeda_quote_supplier_failure(new RuntimeException($secret));
$expectedPayload = [
    'ok' => false,
    'error' => 'supplier_unavailable',
    'failure_category' => 'internal',
];
if ($payload !== $expectedPayload) throw new RuntimeException('FAILURE_PAYLOAD_SHAPE');
$encoded = json_encode($payload, JSON_THROW_ON_ERROR);
foreach (['secret', 'sid=abc', 'gateway.samo.ru', 'RuntimeException'] as $forbidden) {
    if (str_contains($encoded, $forbidden)) throw new RuntimeException('FAILURE_PAYLOAD_LEAK');
}

// Each response guard exposes only its exact enum, even with a private cause.
// Similar messages with raw details must retain the internal fallback shape.
foreach ($cases as $reason => $category) {
    if ($category !== 'supplier_response') continue;
    $public = anytour_andromeda_quote_supplier_failure(
        new RuntimeException($reason, 0, new RuntimeException($secret)));
    $expectedResponse = [
        'ok' => false,
        'error' => 'supplier_unavailable',
        'failure_category' => 'supplier_response',
        'failure_reason' => $reason,
    ];
    if ($public !== $expectedResponse) throw new RuntimeException('RESPONSE_REASON_PAYLOAD_' . $reason);
    $encoded = json_encode($public, JSON_THROW_ON_ERROR);
    foreach (['secret', 'sid=abc', 'gateway.samo.ru', 'RuntimeException'] as $forbidden) {
        if (str_contains($encoded, $forbidden)) throw new RuntimeException('RESPONSE_REASON_PRIVATE_CAUSE_LEAK');
    }
    foreach ([$reason . ' ' . $secret, $secret . ' ' . $reason, $reason . "\n" . $secret] as $raw) {
        if (anytour_andromeda_quote_supplier_failure(new RuntimeException($raw)) !== $expectedPayload) {
            throw new RuntimeException('RESPONSE_REASON_RAW_DETAILS_EXPOSED');
        }
    }
}

// Detail codes are trusted local annotations, not messages, raw causes or supplier codes.
$flightDetails = [
    101 => 'selected_pair_missing', 102 => 'existing_direction_duplicate',
    103 => 'existing_uid_invalid', 104 => 'returned_direction_invalid',
    105 => 'selected_directions_mismatch', 106 => 'selected_uid_mismatch',
    107 => 'public_pair_invalid',
];
$flightMessage = 'ANDROMEDA_SELECTED_FLIGHTS_INVALID';
$legacyFlightFailure = anytour_andromeda_quote_supplier_failure(new RuntimeException($flightMessage));
foreach ($flightDetails as $code => $detail) {
    $error = new RuntimeException($flightMessage, $code, new RuntimeException($secret));
    $public = anytour_andromeda_quote_supplier_failure($error, 'flight_continuation');
    $expectedDetail = [
        'ok' => false, 'error' => 'supplier_unavailable', 'failure_category' => 'quote_state',
        'failure_phase' => 'flight_continuation', 'failure_reason' => $flightMessage, 'failure_detail' => $detail,
    ];
    if ($public !== $expectedDetail) throw new RuntimeException('FAILURE_FLIGHT_DETAIL_' . $code);
    $encoded = json_encode($public, JSON_THROW_ON_ERROR);
    foreach ([$secret, (string)$code, 'RuntimeException', 'gateway.samo.ru', 'sid=abc'] as $private) {
        if (str_contains($encoded, $private)) throw new RuntimeException('FAILURE_FLIGHT_DETAIL_LEAK');
    }
    $unsafePhase = anytour_andromeda_quote_supplier_failure($error, 'flight_continuation ' . $secret);
    if ($unsafePhase !== $legacyFlightFailure + ['failure_detail' => $detail]) {
        throw new RuntimeException('FAILURE_FLIGHT_DETAIL_UNSAFE_PHASE');
    }
}
foreach ([0, 100, 108, -1, PHP_INT_MAX] as $code) {
    if (anytour_andromeda_quote_supplier_failure(new RuntimeException($flightMessage, $code)) !== $legacyFlightFailure) {
        throw new RuntimeException('UNLISTED_FLIGHT_DETAIL_CODE');
    }
}
$foreignFlightError = new class($flightMessage, 101) extends RuntimeException {};
foreach ([$foreignFlightError, new DomainException($flightMessage, 101),
    new InvalidArgumentException($flightMessage, 101), new Exception($flightMessage, 101),
    new TypeError($flightMessage, 101)] as $error) {
    if (anytour_andromeda_quote_supplier_failure($error) !== $legacyFlightFailure) {
        throw new RuntimeException('FOREIGN_FLIGHT_DETAIL_TYPE');
    }
}
foreach ([$flightMessage . ' ' . $secret, $secret . ' ' . $flightMessage,
    $flightMessage . "\n/private/path", 'ANDROMEDA_QUOTE_RESULT_INVALID', ''] as $message) {
    $public = anytour_andromeda_quote_supplier_failure(new RuntimeException($message, 101,
        new RuntimeException($secret)));
    if (array_key_exists('failure_detail', $public)
        || str_contains(json_encode($public, JSON_THROW_ON_ERROR), $secret)) {
        throw new RuntimeException('UNLISTED_FLIGHT_DETAIL_MESSAGE');
    }
}

// Package rejection details identify fixed local predicates, never supplier values.
$packageMessage = 'ANDROMEDA_INVALID_PACKAGE_RESPONSE';
$packageDetails = [
    201 => 'package_document_invalid', 202 => 'package_document_layout_invalid',
    203 => 'package_document_item_invalid', 204 => 'package_catalog_key_invalid',
    205 => 'package_catalog_key_empty',
];
$legacyPackageFailure = anytour_andromeda_quote_supplier_failure(new RuntimeException($packageMessage));
foreach ($packageDetails as $code => $detail) {
    $error = new RuntimeException($packageMessage, $code, new RuntimeException($secret));
    $public = anytour_andromeda_quote_supplier_failure($error, 'quote_bootstrap');
    $expected = [
        'ok' => false, 'error' => 'supplier_unavailable', 'failure_category' => 'supplier_response',
        'failure_phase' => 'quote_bootstrap', 'failure_reason' => $packageMessage, 'failure_detail' => $detail,
    ];
    if ($public !== $expected) throw new RuntimeException('FAILURE_PACKAGE_DETAIL_' . $code);
    $encoded = json_encode($public, JSON_THROW_ON_ERROR);
    foreach ([$secret, (string)$code, 'RuntimeException', 'gateway.samo.ru', 'sid=abc'] as $private) {
        if (str_contains($encoded, $private)) throw new RuntimeException('FAILURE_PACKAGE_DETAIL_LEAK');
    }
    if (anytour_andromeda_quote_supplier_failure($error, 'quote_bootstrap ' . $secret)
        !== $legacyPackageFailure + ['failure_detail' => $detail]) {
        throw new RuntimeException('FAILURE_PACKAGE_UNSAFE_PHASE');
    }
    if (anytour_andromeda_quote_supplier_failure(new RuntimeException($flightMessage, $code))
        !== $legacyFlightFailure) throw new RuntimeException('FAILURE_PACKAGE_CODE_CROSS_MESSAGE');
}
foreach ([0, 101, 107, 200, 206, -1, PHP_INT_MAX] as $code) {
    if (anytour_andromeda_quote_supplier_failure(new RuntimeException($packageMessage, $code))
        !== $legacyPackageFailure) throw new RuntimeException('UNLISTED_PACKAGE_DETAIL_CODE');
}
$foreignPackageError = new class($packageMessage, 201) extends RuntimeException {};
if (anytour_andromeda_quote_supplier_failure($foreignPackageError) !== $legacyPackageFailure) {
    throw new RuntimeException('FOREIGN_PACKAGE_DETAIL_CLASS');
}
$nonIntegerPackageCode = new RuntimeException($packageMessage);
$packageCodeProperty = new ReflectionProperty(Exception::class, 'code');
$packageCodeProperty->setValue($nonIntegerPackageCode, '201');
if (anytour_andromeda_quote_supplier_failure($nonIntegerPackageCode) !== $legacyPackageFailure) {
    throw new RuntimeException('NON_INTEGER_PACKAGE_DETAIL_CODE');
}
foreach ([$packageMessage . ' ' . $secret, $secret . ' ' . $packageMessage,
    $packageMessage . "\n/private/path", 'ANDROMEDA_INVALID_RESPONSE', ''] as $message) {
    $public = anytour_andromeda_quote_supplier_failure(new RuntimeException($message, 201,
        new RuntimeException($secret)));
    if (array_key_exists('failure_detail', $public)
        || str_contains(json_encode($public, JSON_THROW_ON_ERROR), $secret)) {
        throw new RuntimeException('UNLISTED_PACKAGE_DETAIL_MESSAGE');
    }
}

// A phase is a fixed execution boundary, never a message, supplier action or path.
$phases = ['request', 'database', 'catalog', 'criteria', 'quote_resolve', 'quote_reserve',
    'quote_bootstrap', 'flight_state', 'quote_validate', 'quote_checkpoint', 'flight_continuation'];
foreach ($phases as $phase) {
    foreach (['', 'ANDROMEDA_INVALID_RESPONSE', 'ANDROMEDA_QUOTE_PRIVATE_STATE', 'ANDROMEDA_HTTP_ERROR'] as $message) {
        $error = new RuntimeException($message, 0, new RuntimeException($secret));
        $legacy = anytour_andromeda_quote_supplier_failure($error);
        $public = anytour_andromeda_quote_supplier_failure($error, $phase);
        if (($public['failure_phase'] ?? null) !== $phase
            || array_diff_key($public, ['failure_phase' => true]) !== $legacy) {
            throw new RuntimeException('FAILURE_PHASE_PAYLOAD_' . $phase);
        }
        if (str_contains(json_encode($public, JSON_THROW_ON_ERROR), $secret)) {
            throw new RuntimeException('FAILURE_PHASE_PRIVATE_CAUSE');
        }
    }
}
foreach ([null, '', 'get_flights', 'quote_bootstrap ' . $secret, "flight_state\n/private/path", 'FLIGHT_STATE'] as $phase) {
    if (anytour_andromeda_quote_supplier_failure(new RuntimeException($secret), $phase) !== $expectedPayload) {
        throw new RuntimeException('UNLISTED_PHASE_EXPOSED');
    }
}
if (!str_contains($source, 'anytour_anex_search3_out(anytour_andromeda_quote_supplier_failure($e, $failurePhase),502)')) {
    throw new RuntimeException('FAILURE_HTTP_WIRING');
}
foreach ([
    "catch (OverflowException \$e) { anytour_anex_search3_out(['ok'=>false,'error'=>'monthly_quota_exhausted'],429)",
    "catch (DomainException \$e) { anytour_anex_search3_out(['ok'=>false,'error'=>'quote_not_available'],422)",
    "catch (InvalidArgumentException \$e) { anytour_anex_search3_out(['ok'=>false,'error'=>'invalid_request'],400)",
] as $typedCatch) {
    if (!str_contains($source, $typedCatch)) throw new RuntimeException('FAILURE_TYPED_HTTP_STATUS');
}
if (substr_count($extractFunction($source, 'anytour_andromeda_quote_reprice_continue'),
    "anytour_andromeda_quote_supplier_failure(\$error, 'flight_continuation')") !== 2) {
    throw new RuntimeException('FAILURE_EMBEDDED_CONTINUATION_PHASE');
}

// Run the actual orchestration with local seams. No transport, DB, budget or
// filesystem write is possible; each injected fault retains its original object.
final class AnyTourQuotePhasePDO extends PDO { public function __construct() {} }
function anytour_quote_phase_hook(string $point): void {
    global $phaseFixture;
    $phaseFixture['events'][] = $point;
    if ($phaseFixture['fail_at'] === $point) throw $phaseFixture['error'];
}
function anytour_andromeda_quote_resolve(array $request, PDO $pdo, array $saved, array $config,
    string $session, array $listingPrices = []): array {
    anytour_quote_phase_hook('resolve');
    return ['expires_at' => time() + 45];
}
function anytour_andromeda_quote_meta(array $resolved, array $config): array {
    global $phaseFixture;
    anytour_quote_phase_hook('meta');
    return ['prefix' => $phaseFixture['prefix'], 'lock_path' => $phaseFixture['prefix'] . '.lock',
        'context_sha256' => str_repeat('a', 64)];
}
function anytour_andromeda_quote_reserve(string $path, string $lock, string $context, string $operation): array {
    global $phaseFixture;
    anytour_quote_phase_hook('reserve');
    return ['replay' => $phaseFixture['replay'] ? ['state' => 'quote_verified'] : null, 'attempt' => []];
}
function anytour_andromeda_quote_reprice_projection(array $result, array $resolved, array $meta): array {
    anytour_quote_phase_hook('replay'); return $result;
}
function anytour_andromeda_quote_supplier(array $config): array {
    anytour_quote_phase_hook('supplier');
    return [new class {
        public function privateSession(): array {
            anytour_quote_phase_hook('private_session'); return ['sid' => 'SID_local'];
        }
    }, new stdClass];
}
final class AnyTourAndromedaSelectedQuote {
    public static function run(array $resolved, object $client, object $actions, callable $retain): array {
        global $phaseFixture;
        anytour_quote_phase_hook('bootstrap');
        if ($phaseFixture['retain']) {
            $retain(['claimDocument' => [[]]], []);
            anytour_quote_phase_hook('bootstrap_after_retain');
        }
        return ['state' => $phaseFixture['retain'] ? 'flight_selection_required' : 'quote_verified'];
    }
    public static function continueWithFlights(array $resolved, array $claim, array $selected, object $actions): array {
        global $phaseFixture;
        anytour_quote_phase_hook('continuation'); return ['flights' => $phaseFixture['public_pair']];
    }
}
final class AnyTourAndromedaFlightSelection {
    public static function buildState(array $claim, array $options, string $context): array {
        anytour_quote_phase_hook('build'); return ['state' => ['local' => true], 'refs' => []];
    }
    public static function select(array $state, string $context, array $selection): array {
        anytour_quote_phase_hook('select'); return ['claim' => [], 'selected' => []];
    }
}
final class AnyTourAndromedaQuoteAttemptState {
    public static function replay(array $state, string $context, string $operation): array {
        return ['state' => 'flight_selection_required', 'final_price_verified' => false];
    }
}
function anytour_andromeda_search3_save(string $path, array $data): void {
    global $phaseFixture;
    anytour_quote_phase_hook('save'); $phaseFixture['written'] = $data;
}
function anytour_andromeda_quote_read(string $path, int $limit): array {
    global $phaseFixture;
    anytour_quote_phase_hook('read');
    return ($phaseFixture['gateway_public_pair'] ?? false) ? ['state' => []] : $phaseFixture['written'];
}
function anytour_andromeda_quote_with_expiry(array $result, array $resolved): array {
    anytour_quote_phase_hook('validate'); return $result;
}
final class AnyTourAndromedaFlightRepricingState {
    public static function create(string $context, string $stateHash, string $sid,
        array $claim, array $result, int $expires): array {
        anytour_quote_phase_hook('create'); return ['local' => true];
    }
    public static function metadata(array $state): array {
        anytour_quote_phase_hook('metadata'); return ['enabled' => true];
    }
}
function anytour_andromeda_quote_reprice_persist(string $path, array $state, array $expected): array {
    anytour_quote_phase_hook('persist'); return $state;
}
final class AnyTourAndromedaPriceObservation {
    public static function compareServed(?array $receipt, array $result, int $now): array {
        anytour_quote_phase_hook('observation'); return ['local' => true];
    }
}
function anytour_andromeda_quote_finish(string $path, string $lock, array $attempt, array $result): void {
    anytour_quote_phase_hook('finish');
}
function anytour_andromeda_quote_unknown(string $path, string $lock, array $attempt): void {
    global $phaseFixture;
    ++$phaseFixture['cleanup']; $phaseFixture['cleanup_phase'] = $phaseFixture['phase'];
}
eval($extractFunction($source, 'anytour_andromeda_quote_run'));
eval($extractFunction($source, 'anytour_andromeda_quote_continue'));

$phaseRun = static function(?string $failAt, bool $retain = false, bool $replay = false,
    ?Throwable $error = null, int $arity = 7): array {
    global $phaseFixture;
    $phase = null;
    $phaseFixture = ['fail_at' => $failAt, 'error' => $error ?? new RuntimeException('private local fault'),
        'retain' => $retain, 'replay' => $replay, 'events' => [], 'cleanup' => 0,
        'prefix' => sys_get_temp_dir() . '/anytour-offline-phase-' . bin2hex(random_bytes(12))];
    $phaseFixture['phase'] = &$phase;
    try {
        if ($arity === 5) $result = anytour_andromeda_quote_run([], new AnyTourQuotePhasePDO, [], [], 'local');
        elseif ($arity === 6) $result = anytour_andromeda_quote_run([], new AnyTourQuotePhasePDO, [], [], 'local', []);
        else $result = anytour_andromeda_quote_run([], new AnyTourQuotePhasePDO, [], [], 'local', [], $phase);
        return ['result' => $result, 'phase' => $phase, 'fixture' => $phaseFixture];
    } catch (Throwable $caught) {
        return ['error' => $caught, 'phase' => $phase, 'fixture' => $phaseFixture];
    }
};
$phaseChecks = 0;
foreach ([
    ['resolve', 'quote_resolve', false, false],
    ['meta', 'quote_reserve', false, false],
    ['reserve', 'quote_reserve', false, false],
    ['supplier', 'quote_bootstrap', false, true],
    ['bootstrap', 'quote_bootstrap', false, true],
    ['build', 'flight_state', true, true],
    ['save', 'flight_state', true, true],
    ['read', 'flight_state', true, true],
    ['bootstrap_after_retain', 'quote_bootstrap', true, true],
    ['validate', 'quote_validate', true, true],
    ['private_session', 'flight_state', true, true],
    ['create', 'flight_state', true, true],
    ['persist', 'flight_state', true, true],
    ['metadata', 'flight_state', true, true],
    ['observation', 'quote_validate', true, true],
    ['finish', 'quote_checkpoint', true, true],
] as [$point, $expectedPhase, $retain, $sealed]) {
    $original = new RuntimeException('private local fault');
    $run = $phaseRun($point, $retain, false, $original);
    if (($run['error'] ?? null) !== $original || $run['phase'] !== $expectedPhase
        || $run['fixture']['cleanup'] !== (int)$sealed
        || ($sealed && $run['fixture']['cleanup_phase'] !== $expectedPhase)
        || (!$sealed && in_array('supplier', $run['fixture']['events'], true))) {
        throw new RuntimeException('FAILURE_NATIVE_PHASE_' . $point);
    }
    $public = anytour_andromeda_quote_supplier_failure($run['error'], $run['phase']);
    if ($public !== $expectedPayload + ['failure_phase' => $expectedPhase]) {
        throw new RuntimeException('FAILURE_NATIVE_PHASE_PRIVACY_' . $point);
    }
    ++$phaseChecks;
}
foreach ([new DomainException('quote expired'), new InvalidArgumentException('bad selection'),
    new OverflowException('monthly quota')] as $typed) {
    $run = $phaseRun('bootstrap', false, false, $typed);
    if (($run['error'] ?? null) !== $typed || $run['fixture']['cleanup'] !== 1
        || $run['phase'] !== 'quote_bootstrap') throw new RuntimeException('FAILURE_TYPED_EXCEPTION_CHANGED');
    ++$phaseChecks;
}
foreach ([false, true] as $retain) {
    $run = $phaseRun(null, $retain);
    if (isset($run['error']) || $run['phase'] !== 'quote_checkpoint' || $run['fixture']['cleanup'] !== 0
        || isset($run['result']['failure_phase'])) throw new RuntimeException('FAILURE_PHASE_SUCCESS');
    ++$phaseChecks;
}
foreach ([5, 6] as $arity) {
    $run = $phaseRun(null, false, false, null, $arity);
    if (isset($run['error']) || $run['fixture']['cleanup'] !== 0) throw new RuntimeException('FAILURE_PHASE_LEGACY_ARITY');
    ++$phaseChecks;
}
$replayed = $phaseRun(null, false, true);
if (($replayed['result']['state'] ?? null) !== 'quote_verified' || $replayed['fixture']['cleanup'] !== 0
    || in_array('supplier', $replayed['fixture']['events'], true)) throw new RuntimeException('FAILURE_PHASE_REPLAY');
++$phaseChecks;

// The canonical native producer already guarantees a valid public pair. Inject
// malformed producer tuples to reach the actual gateway's independent guard.
$publicPairChecks = 0;
foreach ([[], [['direction' => '1'], ['direction' => '0']],
    [['direction' => 0], ['direction' => '1']]] as $publicPair) {
    $phase = 'flight_continuation';
    $phaseFixture = ['fail_at' => null, 'events' => [], 'cleanup' => 0, 'replay' => false,
        'gateway_public_pair' => true, 'public_pair' => $publicPair,
        'prefix' => sys_get_temp_dir() . '/anytour-offline-public-pair-' . bin2hex(random_bytes(12))];
    $phaseFixture['phase'] = &$phase;
    try {
        anytour_andromeda_quote_continue(['flight_selection' => ['provider' => 'andromeda',
            'outbound_ref' => 'flight_' . str_repeat('a', 32), 'return_ref' => 'flight_' . str_repeat('b', 32)]],
            new AnyTourQuotePhasePDO, [], [], 'local');
        throw new RuntimeException('INVALID_PUBLIC_PAIR_ACCEPTED');
    } catch (RuntimeException $error) {
        if (get_class($error) !== RuntimeException::class
            || $error->getMessage() !== $flightMessage || $error->getCode() !== 107
            || $error->getPrevious() !== null || $phaseFixture['cleanup'] !== 1
            || $phaseFixture['cleanup_phase'] !== 'flight_continuation'
            || $phaseFixture['events'] !== ['resolve', 'meta', 'read', 'read', 'select', 'reserve', 'supplier', 'continuation']) {
            throw new RuntimeException('FAILURE_PUBLIC_PAIR_GUARD');
        }
        $public = anytour_andromeda_quote_supplier_failure($error, $phase);
        if (($public['failure_detail'] ?? null) !== 'public_pair_invalid'
            || ($public['failure_reason'] ?? null) !== $flightMessage) {
            throw new RuntimeException('FAILURE_PUBLIC_PAIR_DETAIL');
        }
        ++$publicPairChecks;
    }
}
if (substr_count($source, "throw new RuntimeException('ANDROMEDA_SELECTED_FLIGHTS_INVALID', 107);") !== 2) {
    throw new RuntimeException('FAILURE_PUBLIC_PAIR_ANNOTATIONS');
}
echo "andromeda quote failure category: OK\n";
echo "andromeda quote native failure phase checks={$phaseChecks}\n";
echo "andromeda quote public pair detail checks={$publicPairChecks}\n";
// Stock quote CI already executes this file; keep the real parser smoke on that path.
require_once __DIR__ . '/andromeda-package-bootstrap-smoke.php';
require_once __DIR__ . '/andromeda-claim-supplier-error-smoke.php';
