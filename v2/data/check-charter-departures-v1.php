<?php
/** Report Russian Tourvisor departure cities that expose charter countries, plus direct+charter overlap. CLI only, no DB writes. */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/tourvisor-client-v1.php';

function charter_departure_name(array $row): string
{
    foreach (['russianName', 'fullRussianName', 'name'] as $key) {
        $value = trim((string)($row[$key] ?? ''));
        if ($value !== '') return $value;
    }
    return '';
}

function charter_country_ids(int $departureId, bool $onlyDirect): array
{
    $countries = v2_data_tv_get('/countries', [
        'departureId' => $departureId,
        'onlyCharter' => true,
        'onlyDirect' => $onlyDirect,
    ]);
    $ids = [];
    foreach ($countries as $country) {
        if (!is_array($country)) continue;
        $countryId = (int)($country['id'] ?? 0);
        if ($countryId > 0) $ids[$countryId] = true;
    }
    return $ids;
}

$departures = v2_data_tv_get('/departures', ['departureCountryId' => 1]);
$total = 0;
$active = [];
$inactive = [];
$charterPairs = 0;
$directCharterPairs = 0;
$directCharterCities = 0;

foreach ($departures as $departure) {
    if (!is_array($departure)) continue;
    $id = (int)($departure['id'] ?? 0);
    $name = charter_departure_name($departure);
    if ($id <= 0 || $name === '') continue;
    $total++;

    $charterIds = charter_country_ids($id, false);
    $directCharterIds = charter_country_ids($id, true);
    $charterCount = count($charterIds);
    $directCharterCount = count($directCharterIds);
    $charterPairs += $charterCount;
    $directCharterPairs += $directCharterCount;
    if ($directCharterCount > 0) $directCharterCities++;

    $row = [
        'id' => $id,
        'name' => $name,
        'charter' => $charterCount,
        'directCharter' => $directCharterCount,
    ];
    if ($charterCount > 0) $active[] = $row; else $inactive[] = $row;
}

usort($active, static fn(array $a, array $b): int => $b['charter'] <=> $a['charter'] ?: $b['directCharter'] <=> $a['directCharter'] ?: strcmp($a['name'], $b['name']));
usort($inactive, static fn(array $a, array $b): int => strcmp($a['name'], $b['name']));

fwrite(STDOUT, "CHARTER_DEPARTURES_OK total={$total} active=" . count($active) . " inactive=" . count($inactive) . " charter_pairs={$charterPairs} direct_charter_cities={$directCharterCities} direct_charter_pairs={$directCharterPairs}\n");
foreach ($active as $row) {
    fwrite(STDOUT, "CHARTER_ACTIVE id={$row['id']} name={$row['name']} countries={$row['charter']} direct_charter_countries={$row['directCharter']}\n");
}
foreach ($inactive as $row) {
    fwrite(STDOUT, "CHARTER_INACTIVE id={$row['id']} name={$row['name']}\n");
}
