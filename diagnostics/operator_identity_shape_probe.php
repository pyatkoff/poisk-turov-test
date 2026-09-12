<?php
/** Inspect only field names of trusted Tourvisor search rows; never print field values. */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit(2);
$searchId = filter_var($argv[1] ?? null, FILTER_VALIDATE_INT);
if ($searchId === false || (int)$searchId <= 0) exit(3);
$root = getenv('ANYTOUR_ROOT') ?: (__DIR__ . '/..');
require rtrim($root, '/') . '/data/tourvisor-client-v1.php';
require rtrim($root, '/') . '/data/observe-search-batches-v1.php';
$payload = v2_data_tv_get('/tours/search/' . (int)$searchId, ['limit'=>5]);
$rows = v2_observe_search_result_rows($payload);
echo 'SHAPE_HOTELS=' . count($rows) . "\n";
$hotel = null;
foreach ($rows as $candidate) {
    if (is_array($candidate) && is_array($candidate['tours'] ?? null) && ($candidate['tours'] ?? []) !== []) { $hotel = $candidate; break; }
}
if (!is_array($hotel)) { echo "SHAPE_NO_TOURS\n"; exit(0); }
$keys = array_keys($hotel); sort($keys, SORT_STRING);
echo 'SHAPE_HOTEL_KEYS=' . implode(',', $keys) . "\n";
$tour = $hotel['tours'][0] ?? null;
if (!is_array($tour)) { echo "SHAPE_NO_TOUR_ROW\n"; exit(0); }
$tkeys = array_keys($tour); sort($tkeys, SORT_STRING);
echo 'SHAPE_TOUR_KEYS=' . implode(',', $tkeys) . "\n";
$operator = $tour['operator'] ?? null;
if (is_array($operator)) { $okeys=array_keys($operator); sort($okeys, SORT_STRING); echo 'SHAPE_OPERATOR_KEYS=' . implode(',', $okeys) . "\n"; }
else { echo 'SHAPE_OPERATOR_TYPE=' . get_debug_type($operator) . "\n"; }
$paths=[];
$walk = function ($value, string $prefix, int $depth) use (&$walk, &$paths): void {
    if ($depth > 3 || !is_array($value)) return;
    foreach ($value as $key=>$child) {
        $key=(string)$key; $path=$prefix===''?$key:$prefix.'.'.$key;
        if (preg_match('/link|url|operator|supplier|external|hotel.?code|code/i', $key)) $paths[$path]=true;
        if (is_array($child)) $walk($child,$path,$depth+1);
    }
};
$walk($hotel,'hotel',0);
$paths=array_keys($paths); sort($paths,SORT_STRING);
echo 'SHAPE_LINKISH_PATHS=' . implode(',', $paths) . "\n";
