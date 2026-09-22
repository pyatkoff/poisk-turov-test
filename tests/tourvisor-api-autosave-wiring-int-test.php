<?php
declare(strict_types=1);

function check_true(bool $value, string $message): void
{
    if (!$value) throw new RuntimeException($message);
}

$source = file_get_contents(__DIR__ . '/../v2/api-v2.php');
check_true(is_string($source) && $source !== '', 'gateway source missing');

// The same checked gateway can live under /v2 in source or at the shared site root.
check_true(str_contains($source, "__DIR__ . '/app/integrations/tourvisor-anytour-offer-autosave.php'"), 'root helper candidate missing');
check_true(str_contains($source, "dirname(__DIR__) . '/app/integrations/tourvisor-anytour-offer-autosave.php'"), 'v2 helper candidate missing');
check_true(str_contains($source, "class_exists('AnyTourTourvisorOfferAutosaveV1', false)"), 'missing-helper no-op guard missing');

// The gateway only hands existing supplier responses to the existing INT owner.
// Listing TTL and price/fuel arithmetic must not move into this boundary.
foreach (['86400', 'finalPrice', 'fuelCharge', 'replaceCompleteSnapshot', 'party_surcharge'] as $forbidden) {
    check_true(!str_contains($source, $forbidden), 'protected arithmetic/TTL leaked into gateway: ' . $forbidden);
}
check_true(str_contains($source, "https://api.tourvisor.ru/search/api/v1"), 'supplier endpoint changed');

$cases = [
    'search_start' => ['next' => 'search_continue', 'fetch' => "$data = tv_get('/tours/search', $searchParams);", 'hook' => 'tourvisor_autosave_start($searchParams, $data);'],
    'search_status' => ['next' => 'search_results', 'fetch' => "$data = tv_get('/tours/search/' . $id . '/status', ['operatorStatus' => false]);", 'hook' => 'tourvisor_autosave_status($id, $data);'],
    'search_results' => ['next' => 'tour', 'fetch' => "$data = tv_get('/tours/search/' . $id, ['limit' => $limit]);", 'hook' => 'tourvisor_autosave_results($id, $limit, $data);'],
];
foreach ($cases as $name => $spec) {
    $start = strpos($source, "case '" . $name . "':");
    $end = strpos($source, "case '" . $spec['next'] . "':", $start === false ? 0 : $start + 1);
    check_true($start !== false && $end !== false && $end > $start, 'case boundary missing: ' . $name);
    $body = substr($source, $start, $end - $start);
    $fetch = strpos($body, $spec['fetch']);
    $hook = strpos($body, $spec['hook']);
    $out = strpos($body, 'out($data);');
    check_true($fetch !== false && $hook !== false && $out !== false && $fetch < $hook && $hook < $out, 'response-transparent hook order wrong: ' . $name);
}

$continueStart = strpos($source, "case 'search_continue':");
$continueEnd = strpos($source, "case 'search_status':", $continueStart === false ? 0 : $continueStart + 1);
check_true($continueStart !== false && $continueEnd !== false, 'continue case missing');
$continueBody = substr($source, $continueStart, $continueEnd - $continueStart);
check_true(!str_contains($continueBody, 'tourvisor_autosave_'), 'search_continue must not invent persistence authority');

// Execute the ACTUAL wrapper definitions in isolation. With no helper class they are
// strict no-ops; with the existing class present each handoff happens exactly once.
$wrapperStart = strpos($source, 'function tourvisor_autosave_start');
$wrapperEnd = strpos($source, '$action =', $wrapperStart === false ? 0 : $wrapperStart + 1);
check_true($wrapperStart !== false && $wrapperEnd !== false && $wrapperEnd > $wrapperStart, 'wrapper source missing');
$wrapperSource = substr($source, $wrapperStart, $wrapperEnd - $wrapperStart);
eval($wrapperSource);

tourvisor_autosave_start(['departureId' => 1], ['searchId' => 11]);
tourvisor_autosave_status(11, ['progress' => 100]);
tourvisor_autosave_results(11, 100, [['id' => 'offer']]);

final class AnyTourTourvisorOfferAutosaveV1
{
    public static array $calls = [];
    public static bool $throw = false;

    private static function record(string $method, array $args): void
    {
        if (self::$throw) throw new RuntimeException('fixture persistence failure');
        self::$calls[] = [$method, $args];
    }

    public static function captureSearchStart(array $scope, array $response, DateTimeImmutable $now): void
    {
        self::record(__FUNCTION__, [$scope, $response, $now]);
    }

    public static function captureSearchStatus(int $searchId, array $response, DateTimeImmutable $now): void
    {
        self::record(__FUNCTION__, [$searchId, $response, $now]);
    }

    public static function autosaveSearchResults(int $searchId, int $limit, array $response, DateTimeImmutable $now): void
    {
        self::record(__FUNCTION__, [$searchId, $limit, $response, $now]);
    }
}

$scope = ['departureId' => 1, 'countryId' => 4, 'nightsFrom' => 7, 'nightsTo' => 14];
$startResponse = ['searchId' => 91, 'opaque' => ['preserve' => true]];
$statusResponse = ['progress' => 100, 'opaque' => 'status'];
$resultResponse = [['hotel' => 'fixture', 'opaque' => ['price' => 'unchanged']]];
tourvisor_autosave_start($scope, $startResponse);
tourvisor_autosave_status(91, $statusResponse);
tourvisor_autosave_results(91, 100, $resultResponse);
check_true(count(AnyTourTourvisorOfferAutosaveV1::$calls) === 3, 'each helper handoff must happen exactly once');

[$method1, $args1] = AnyTourTourvisorOfferAutosaveV1::$calls[0];
[$method2, $args2] = AnyTourTourvisorOfferAutosaveV1::$calls[1];
[$method3, $args3] = AnyTourTourvisorOfferAutosaveV1::$calls[2];
check_true($method1 === 'captureSearchStart' && $args1[0] === $scope && $args1[1] === $startResponse, 'start handoff mutated data');
check_true($method2 === 'captureSearchStatus' && $args2[0] === 91 && $args2[1] === $statusResponse, 'status handoff mutated data');
check_true($method3 === 'autosaveSearchResults' && $args3[0] === 91 && $args3[1] === 100 && $args3[2] === $resultResponse, 'results handoff mutated data');
foreach ([$args1[2], $args2[2], $args3[3]] as $at) {
    check_true($at instanceof DateTimeImmutable && $at->getTimezone()->getName() === 'UTC', 'handoff clock must be UTC');
}

// Persistence failures are deliberately swallowed and cannot replace the upstream response.
AnyTourTourvisorOfferAutosaveV1::$throw = true;
tourvisor_autosave_start($scope, $startResponse);
tourvisor_autosave_status(91, $statusResponse);
tourvisor_autosave_results(91, 100, $resultResponse);
check_true(count(AnyTourTourvisorOfferAutosaveV1::$calls) === 3, 'failed persistence must not create duplicate accepted calls');

echo "TOURVISOR_API_AUTOSAVE_WIRING_INT_OK no_helper=1 helper_calls=3 response_transparent=1 supplier_http=0 db=0 ttl_arithmetic=0\n";
