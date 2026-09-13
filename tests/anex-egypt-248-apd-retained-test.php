<?php
declare(strict_types=1);

define('ANYTOUR_ANEX_EGYPT_248_APD_LIBRARY_ONLY', true);
require_once __DIR__ . '/../scripts/diagnostics/anex_egypt_248_apd_retained.php';

$checks = 0;
$assert = static function ($condition, string $message) use (&$checks): void {
    if (!$condition) throw new RuntimeException('FAIL: ' . $message);
    ++$checks;
};

$offer = static function (string $program, string $currency, string $price, string $kind = 'concrete'): array {
    return [
        'kind' => $kind,
        'supplier_tour_program_id' => $program,
        'supplier_currency_id' => $currency,
        'hotel' => ['external_id' => '10449', 'local_id' => 248],
        'checkin' => '2026-12-07', 'nights' => 10, 'adults' => 3, 'children' => 0,
        'meal' => 'AI', 'room' => 'Standard Room With Garden View',
        'price' => ['amount' => $price, 'currency' => 'RUB'],
    ];
};

$selected = anex_egypt_248_apd_select_concrete([
    $offer('7654', '3', '171000'),
    $offer('4321', '3', '169970'),
    $offer('4321', '3', '169970'),
]);
$assert($selected['program'] === '4321', 'program comes from the minimum concrete offer');
$assert($selected['currency'] === '3', 'native currency comes from the concrete offer');
$assert($selected['price'] === '169970', 'current search price remains separate');

$failed = false;
try {
    anex_egypt_248_apd_select_concrete([$offer('4321', '3', '169970'), $offer('9999', '3', '169970')]);
} catch (RuntimeException $error) {
    $failed = $error->getMessage() === 'ANEX_EGYPT_APD_CONCRETE_AMBIGUOUS';
}
$assert($failed, 'same-price different program contexts fail closed');

$ignored = $offer('7777', '3', '160000');
$ignored['room'] = 'Standard Room With Pool View';
$selected = anex_egypt_248_apd_select_concrete([$ignored, $offer('4321', '3', '169970')]);
$assert($selected['program'] === '4321', 'different room is not allowed to select the APD context');

$application = anex_egypt_248_apd_application([
    'totalCount' => 1,
    'data' => [[
        'price_adult' => '100', 'price_chd' => '50', 'cashrate' => '1',
        'price_converted_adult' => '7178.3333', 'price_converted_chd' => '3000',
    ]],
], '169970');
$assert($application['application_state'] === 'applied', 'one exact APD row applies');
$assert($application['party_surcharge']['amount'] === '21534.9999', 'three-adult party uses exact fixed-point multiplication');
$assert($application['search_plus_additional']['amount'] === '191504.9999', 'search and APD party addition remain separately reproducible');
$assert($application['arithmetic_applied'] === true, 'party arithmetic is explicit');

$unknown = anex_egypt_248_apd_application(['totalCount' => 2, 'data' => [[], []]], '169970');
$assert($unknown['application_state'] === 'unknown' && $unknown['party_surcharge'] === null
    && $unknown['arithmetic_applied'] === false, 'ambiguous APD rows never become zero or arithmetic');

$source = file_get_contents(__DIR__ . '/../scripts/diagnostics/anex_egypt_248_apd_retained.php');
$assert(is_string($source) && strpos($source, "'tour' => (int) \$selected['program']") !== false,
    'supplier program is taken from selected concrete offer at the APD call');
$assert(strpos($source, 'ANEX_EGYPT_248_APD_EXPECTED_PROGRAM') === false,
    'no expected supplier program constant exists');
$assert(strpos($source, "'fuel_equivalence_verified' => false") !== false
    && strpos($source, "'protected_arithmetic_changed' => false") !== false,
    'APD evidence cannot silently authorize Tourvisor arithmetic');
$assert(strpos($source, '->bron(') === false && strpos($source, 'bron_ticket') === false,
    'diagnostic contains no booking call');

$workflow = file_get_contents(__DIR__ . '/../.github/workflows/anex-egypt-248-apd-retained.yml');
$assert(is_string($workflow) && strpos($workflow, "strict=\"declare(strict_types=1);\\n\"") !== false,
    'runner extracts strict_types as an explicit first statement');
$assert(strpos($workflow, 'code=strict+bootstrap+body') !== false
    && strpos($workflow, 'shlex.quote(code)') !== false,
    'runner composes and executes strict_types before bootstrap/body');
$assert(strpos($workflow, 'shlex.quote(bootstrap+body)') === false,
    'regressed bootstrap-before-strict composition is absent');
$assert(strpos($workflow, "require_once $root.'/config.php';") !== false,
    'runner loads the proven production config before the private/APD runtime');
$assert(strpos($workflow, "$preview=realpath($root.'/_preview/search3-anex-candidate');") !== false,
    'preview runtime is resolved from the validated production root');

echo "ANEX Egypt retained APD: {$checks} checks passed; network=0\n";
