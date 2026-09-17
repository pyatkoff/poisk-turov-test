<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/integrations/tourvisor-anytour-offer-autosave.php';

$checks = 0;
function persistent_state_check(bool $ok, string $label): void
{
    global $checks;
    ++$checks;
    if (!$ok) throw new RuntimeException('persistent_state_check_' . $checks . ':' . $label);
}

function persistent_state_remove_tree(string $path): void
{
    if (!is_dir($path)) return;
    $items = scandir($path);
    if (!is_array($items)) return;
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $child = $path . DIRECTORY_SEPARATOR . $item;
        if (is_dir($child) && !is_link($child)) persistent_state_remove_tree($child);
        else @unlink($child);
    }
    @rmdir($path);
}

$originalDocumentRoot = $_SERVER['DOCUMENT_ROOT'] ?? null;
$root = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
    . DIRECTORY_SEPARATOR . 'anytour-state-docroot-' . bin2hex(random_bytes(8));
if (!mkdir($root, 0770, true) && !is_dir($root)) throw new RuntimeException('fixture_root_create');
$catalogCache = $root . DIRECTORY_SEPARATOR . '.cache' . DIRECTORY_SEPARATOR . 'catalogs';
if (!mkdir($catalogCache, 0770, true) && !is_dir($catalogCache)) throw new RuntimeException('fixture_catalog_cache_create');

$scope = [
    'departureId'=>1,'countryId'=>4,'dateFrom'=>'2026-10-05','dateTo'=>'2026-10-08',
    'nightsFrom'=>7,'nightsTo'=>9,'adults'=>2,'childs'=>[],'meal'=>'','hotelCategory'=>'',
    'hotelRating'=>'','hotelTypes'=>[],'hotelIds'=>[],'hotelServices'=>[],'arrivalId'=>null,
    'regionIds'=>[],'subregionIds'=>[],'operatorIds'=>[],'priceFrom'=>null,'priceTo'=>null,
    'currency'=>'RUB','onlyCharter'=>false,'onlyDirect'=>false,
];
$now = new DateTimeImmutable('2026-09-17T09:30:00Z');
$searchId = 1234567891;

$stateDirectory = new ReflectionMethod(AnyTourTourvisorOfferAutosaveV1::class, 'stateDirectory');
$statePath = new ReflectionMethod(AnyTourTourvisorOfferAutosaveV1::class, 'statePath');
$readState = new ReflectionMethod(AnyTourTourvisorOfferAutosaveV1::class, 'readState');

try {
    $_SERVER['DOCUMENT_ROOT'] = $root;
    $expectedDir = $catalogCache . DIRECTORY_SEPARATOR . 'tourvisor-offer-autosave';
    persistent_state_check($stateDirectory->invoke(null) === $expectedDir, 'web_uses_existing_catalog_cache');
    persistent_state_check(is_dir($catalogCache) && is_writable($catalogCache), 'catalog_cache_parent_preexists_writable');

    $expectedPath = $expectedDir . DIRECTORY_SEPARATOR
        . 'anytour-tourvisor-offer-' . hash('sha256', (string)$searchId) . '.json';
    persistent_state_check($statePath->invoke(null, $searchId) === $expectedPath, 'state_path_is_persistent');
    persistent_state_check(!is_dir($expectedDir), 'state_dir_is_lazy');

    $start = AnyTourTourvisorOfferAutosaveV1::captureSearchStart($scope, ['searchId'=>$searchId], $now);
    persistent_state_check(($start['ok'] ?? null) === true, 'start_saved');
    persistent_state_check(is_dir($expectedDir) && is_writable($expectedDir), 'state_dir_created_writable');
    persistent_state_check(is_file($expectedDir . DIRECTORY_SEPARATOR . '.htaccess'), 'deny_guard_created');
    persistent_state_check(
        file_get_contents($expectedDir . DIRECTORY_SEPARATOR . '.htaccess') === "Require all denied\n",
        'deny_guard_exact'
    );
    persistent_state_check(is_file($expectedPath), 'persistent_state_file_created');

    $state = $readState->invoke(null, $searchId, $now->modify('+1 second'));
    persistent_state_check(is_array($state) && ($state['scope'] ?? null) === $scope, 'independent_read_roundtrip');
    persistent_state_check(array_key_exists('terminal_at', $state) && $state['terminal_at'] === null, 'initial_not_terminal');

    $terminal = AnyTourTourvisorOfferAutosaveV1::captureSearchStatus(
        $searchId,
        ['progress'=>100],
        $now->modify('+10 seconds')
    );
    persistent_state_check(($terminal['ok'] ?? null) === true, 'terminal_saved');
    $terminalState = $readState->invoke(null, $searchId, $now->modify('+11 seconds'));
    persistent_state_check(is_int($terminalState['terminal_at'] ?? null), 'terminal_persisted');
    persistent_state_check(is_file($expectedPath), 'state_survives_separate_calls');

    unset($_SERVER['DOCUMENT_ROOT']);
    $fallbackDir = $stateDirectory->invoke(null);
    persistent_state_check($fallbackDir === rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR), 'cli_falls_back_to_system_temp');
    $fallbackPath = $statePath->invoke(null, $searchId + 1);
    persistent_state_check(str_starts_with($fallbackPath, $fallbackDir . DIRECTORY_SEPARATOR), 'cli_state_path_under_temp');
} finally {
    if ($originalDocumentRoot === null) unset($_SERVER['DOCUMENT_ROOT']);
    else $_SERVER['DOCUMENT_ROOT'] = $originalDocumentRoot;
    persistent_state_remove_tree($root);
}

echo 'Tourvisor persistent autosave state: ' . $checks . " checks passed; supplier calls=0; DB writes=0.\n";
