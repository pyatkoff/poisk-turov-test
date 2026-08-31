<?php
/** Report Russian Tourvisor departure cities that expose at least one country with direct flights. CLI only, no DB writes. */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/tourvisor-client-v1.php';

function direct_departure_name(array $row): string
{
    foreach (['russianName', 'fullRussianName', 'name'] as $key) {
        $value = trim((string)($row[$key] ?? ''));
        if ($value !== '') return $value;
    }
    return '';
}

$departures = v2_data_tv_get('/departures', ['departureCountryId' => 1]);
$total = 0;
$active = [];
$inactive = [];
$pairs = 0;

foreach ($departures as $departure) {
    if (!is_array($departure)) continue;
    $id = (int)($departure['id'] ?? 0);
    $name = direct_departure_name($departure);
    if ($id <= 0 || $name === '') continue;
    $total++;

    $countries = v2_data_tv_get('/countries', [
        'departureId' => $id,
        'onlyCharter' => false,
        'onlyDirect' => true,
    ]);
    $countryIds = [];
    foreach ($countries as $country) {
        if (!is_array($country)) continue;
        $countryId = (int)($country['id'] ?? 0);
        if ($countryId > 0) $countryIds[$countryId] = true;
    }
    $count = count($countryIds);
    $pairs += $count;
    $row = ['id' => $id, 'name' => $name, 'countries' => $count];
    if ($count > 0) $active[] = $row; else $inactive[] = $row;
}

usort($active, static fn(array $a, array $b): int => $b['countries'] <=> $a['countries'] ?: strcmp($a['name'], $b['name']));
usort($inactive, static fn(array $a, array $b): int => strcmp($a['name'], $b['name']));

fwrite(STDOUT, "DIRECT_DEPARTURES_OK total={$total} active=" . count($active) . " inactive=" . count($inactive) . " pairs={$pairs}\n");
foreach ($active as $row) {
    fwrite(STDOUT, "DIRECT_ACTIVE id={$row['id']} name={$row['name']} countries={$row['countries']}\n");
}
foreach ($inactive as $row) {
    fwrite(STDOUT, "DIRECT_INACTIVE id={$row['id']} name={$row['name']}\n");
}
