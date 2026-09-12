<?php
declare(strict_types=1);

/**
 * Pure evidence resolver for an already-fetched direct-ANEX offer against
 * already-fetched Tourvisor rows from the same search context.
 *
 * It performs no I/O and never authorizes production arithmetic by itself.
 */
function anytour_anex_tv_fuel_resolve(array $anex, array $tourvisorRows): array
{
    $direct = anytour_anex_tv_fuel_normalize_offer($anex, 'anex');
    if ($direct === null) {
        return anytour_anex_tv_fuel_unresolved('invalid_direct_offer');
    }

    $fuelCents = [];
    $matches = [];
    foreach (array_slice($tourvisorRows, 0, 500) as $row) {
        if (!is_array($row)) continue;
        $tv = anytour_anex_tv_fuel_normalize_offer($row, 'tourvisor');
        if ($tv === null || !anytour_anex_tv_fuel_same_context($direct, $tv)) continue;
        if (!isset($tv['fuel_cents']) || $tv['fuel_cents'] <= 0) continue;
        if ($tv['price_cents'] - $tv['fuel_cents'] !== $direct['price_cents']) continue;
        $fuelCents[$tv['fuel_cents']] = true;
        if (count($matches) < 20) {
            $matches[] = [
                'fuel_charge' => anytour_anex_tv_fuel_format($tv['fuel_cents']),
                'tourvisor_total' => anytour_anex_tv_fuel_format($tv['price_cents']),
            ];
        }
    }

    $fuels = array_keys($fuelCents);
    sort($fuels, SORT_NUMERIC);
    if (count($fuels) !== 1) {
        return [
            'status' => 'unresolved',
            'reason' => $fuels === [] ? 'no_exact_arithmetic_match' : 'ambiguous_fuel',
            'matched_rows' => count($matches),
            'distinct_fuels' => array_map('anytour_anex_tv_fuel_format', $fuels),
            'runtime_arithmetic_authorized' => false,
            'identical_supplier_package_verified' => false,
        ];
    }

    $fuel = (int)$fuels[0];
    return [
        'status' => 'resolved_evidence',
        'fuel_charge' => anytour_anex_tv_fuel_format($fuel),
        'total_price_candidate' => anytour_anex_tv_fuel_format($direct['price_cents'] + $fuel),
        'matched_rows' => count($matches),
        'distinct_fuels' => [anytour_anex_tv_fuel_format($fuel)],
        'basis' => 'same_context_and_exact_tv_total_minus_tv_fuel_equals_direct_anex_base',
        'provenance' => 'tourvisor_same_search_arithmetic_evidence',
        'runtime_arithmetic_authorized' => false,
        'identical_supplier_package_verified' => false,
        'examples' => $matches,
    ];
}

function anytour_anex_tv_fuel_unresolved(string $reason): array
{
    return [
        'status' => 'unresolved',
        'reason' => $reason,
        'matched_rows' => 0,
        'distinct_fuels' => [],
        'runtime_arithmetic_authorized' => false,
        'identical_supplier_package_verified' => false,
    ];
}

function anytour_anex_tv_fuel_normalize_offer(array $row, string $provider): ?array
{
    if (($row['provider'] ?? null) !== $provider) return null;
    $requiredInts = ['local_hotel_id', 'nights', 'adults', 'children'];
    $clean = [];
    foreach ($requiredInts as $key) {
        $value = filter_var($row[$key] ?? null, FILTER_VALIDATE_INT);
        if ($value === false || (int)$value < ($key === 'children' ? 0 : 1)) return null;
        $clean[$key] = (int)$value;
    }
    $date = (string)($row['date'] ?? '');
    if (!preg_match('/\A20[0-9]{2}-[0-9]{2}-[0-9]{2}\z/D', $date)) return null;
    $clean['date'] = $date;
    foreach (['meal_family', 'room_norm', 'placement_norm', 'currency'] as $key) {
        $value = trim((string)($row[$key] ?? ''));
        if ($value === '' || strlen($value) > 120) return null;
        $clean[$key] = $key === 'currency' ? strtoupper($value) : mb_strtolower($value, 'UTF-8');
    }
    $price = anytour_anex_tv_fuel_cents($row['price'] ?? null);
    if ($price === null || $price <= 0) return null;
    $clean['price_cents'] = $price;
    if ($provider === 'tourvisor') {
        $fuel = anytour_anex_tv_fuel_cents($row['fuel_charge'] ?? null);
        if ($fuel === null || $fuel < 0 || $fuel >= $price) return null;
        $clean['fuel_cents'] = $fuel;
    }
    return $clean;
}

function anytour_anex_tv_fuel_same_context(array $a, array $b): bool
{
    foreach (['local_hotel_id','date','nights','adults','children','meal_family','room_norm','placement_norm','currency'] as $key) {
        if (($a[$key] ?? null) !== ($b[$key] ?? null)) return false;
    }
    return true;
}

function anytour_anex_tv_fuel_cents($value): ?int
{
    if (is_int($value)) $value = (string)$value;
    elseif (is_float($value) && is_finite($value)) $value = number_format($value, 2, '.', '');
    if (!is_string($value) || !preg_match('/\A(?:0|[1-9][0-9]{0,11})(?:\.([0-9]{1,2}))?\z/D', $value, $m)) return null;
    [$whole] = explode('.', $value, 2);
    $fraction = $m[1] ?? '';
    $fraction = str_pad($fraction, 2, '0');
    if ((int)$whole > 9000000000000) return null;
    return ((int)$whole * 100) + (int)$fraction;
}

function anytour_anex_tv_fuel_format(int $cents): string
{
    $whole = intdiv($cents, 100);
    $fraction = $cents % 100;
    return $fraction === 0 ? (string)$whole : $whole . '.' . str_pad((string)$fraction, 2, '0', STR_PAD_LEFT);
}
