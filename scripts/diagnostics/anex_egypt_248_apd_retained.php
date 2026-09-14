<?php
declare(strict_types=1);

const ANEX_EGYPT_248_APD_OPERATION = 'anex-egypt-248-apd-retained-20260914-v1';
const ANEX_EGYPT_248_APD_LOCAL_HOTEL = 248;
const ANEX_EGYPT_248_APD_EXTERNAL_HOTEL = '10449';
const ANEX_EGYPT_248_APD_DATE = '2026-12-07';
const ANEX_EGYPT_248_APD_NIGHTS = 10;
const ANEX_EGYPT_248_APD_ADULTS = 3;
const ANEX_EGYPT_248_APD_ROOM = 'standard room with garden view';
const ANEX_EGYPT_248_APD_SEARCH_PRICE = '169970';
const ANEX_EGYPT_248_TV_DISPLAY_PRICE = '191505';
const ANEX_EGYPT_248_TV_FUEL = '21535';

function anex_egypt_248_apd_norm($value): string
{
    if (!is_string($value)) return '';
    $value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    $value = str_replace(['ё', 'Ё'], 'е', $value);
    return trim((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value));
}

function anex_egypt_248_apd_ai($value): bool
{
    $value = anex_egypt_248_apd_norm($value);
    return in_array($value, ['ai','all','all inclusive','uai','ultra all inclusive','ai without alcohol',
        'все включено','ультра все включено','все включено без алкоголя'], true);
}

function anex_egypt_248_apd_money_units($value): ?int
{
    if (is_int($value) || (is_float($value) && is_finite($value))) $value = (string) $value;
    if (!is_string($value) || !preg_match('/\A(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,4})?\z/D', $value)) return null;
    $parts = explode('.', $value, 2);
    if (strlen($parts[0]) > 11) return null;
    $fraction = str_pad($parts[1] ?? '', 4, '0');
    if (strlen($fraction) !== 4) return null;
    return ((int) $parts[0] * 10000) + (int) $fraction;
}

function anex_egypt_248_apd_money_string(int $units): string
{
    if ($units < 0) throw new InvalidArgumentException('ANEX_EGYPT_APD_MONEY');
    $whole = intdiv($units, 10000);
    $fraction = $units % 10000;
    return $fraction === 0 ? (string) $whole
        : $whole . '.' . rtrim(str_pad((string) $fraction, 4, '0', STR_PAD_LEFT), '0');
}

function anex_egypt_248_apd_offer_price(array $offer): ?string
{
    $price = ($offer['price']['currency'] ?? null) === 'RUB' ? $offer['price'] : ($offer['converted_price'] ?? null);
    return is_array($price) && ($price['currency'] ?? null) === 'RUB'
        && anex_egypt_248_apd_money_units($price['amount'] ?? null) !== null ? (string) $price['amount'] : null;
}

function anex_egypt_248_apd_target_offer(array $offer): bool
{
    return ($offer['hotel']['external_id'] ?? null) === ANEX_EGYPT_248_APD_EXTERNAL_HOTEL
        && ($offer['hotel']['local_id'] ?? null) === ANEX_EGYPT_248_APD_LOCAL_HOTEL
        && ($offer['checkin'] ?? null) === ANEX_EGYPT_248_APD_DATE
        && ($offer['nights'] ?? null) === ANEX_EGYPT_248_APD_NIGHTS
        && ($offer['adults'] ?? null) === ANEX_EGYPT_248_APD_ADULTS
        && ($offer['children'] ?? null) === 0
        && anex_egypt_248_apd_ai($offer['meal'] ?? null)
        && anex_egypt_248_apd_norm($offer['room'] ?? null) === ANEX_EGYPT_248_APD_ROOM;
}

/** SearchTour facts remain useful for exact offer evidence, but never identify the B2B APD tour namespace. */
function anex_egypt_248_apd_select_concrete(array $offers): array
{
    $candidates = [];
    foreach ($offers as $offer) {
        if (!is_array($offer) || ($offer['kind'] ?? null) !== 'concrete' || !anex_egypt_248_apd_target_offer($offer)) continue;
        $program = $offer['supplier_tour_program_id'] ?? null;
        $currency = $offer['supplier_currency_id'] ?? null;
        $price = anex_egypt_248_apd_offer_price($offer);
        if (!is_string($program) || !preg_match('/\A[1-9][0-9]{0,17}\z/D', $program)
            || !is_string($currency) || !preg_match('/\A[1-9][0-9]{0,17}\z/D', $currency)
            || $price === null) continue;
        $units = anex_egypt_248_apd_money_units($price);
        $candidates[] = ['offer' => $offer, 'program' => $program, 'currency' => $currency, 'price' => $price, 'units' => $units];
    }
    if ($candidates === []) throw new RuntimeException('ANEX_EGYPT_APD_CONCRETE_MISSING');
    usort($candidates, static function (array $a, array $b): int { return $a['units'] <=> $b['units']; });
    $minimum = $candidates[0]['units'];
    $contexts = [];
    foreach ($candidates as $candidate) {
        if ($candidate['units'] !== $minimum) break;
        $key = $candidate['program'] . "\0" . $candidate['currency'];
        $contexts[$key] = $candidate;
    }
    if (count($contexts) !== 1) throw new RuntimeException('ANEX_EGYPT_APD_CONCRETE_AMBIGUOUS');
    return array_values($contexts)[0];
}

/**
 * No authoritative SearchTour/CATCLAIM/freight -> B2B AdditionalPricesDaily.tour binding is known.
 * Keep this null until supplier documentation/dictionary or supplier-issued evidence establishes both B2B ids.
 */
function anex_egypt_248_apd_b2b_binding(): ?array
{
    return null;
}

/** Pure retained-evidence calculator. It never establishes that an APD row belongs to this SearchTour package. */
function anex_egypt_248_apd_application(array $payload, string $searchPrice): array
{
    $rows = $payload['data'] ?? null;
    $total = $payload['totalCount'] ?? null;
    if (is_string($total) && ctype_digit($total)) $total = (int) $total;
    $out = ['application_state' => 'unknown', 'search_price' => ['amount' => $searchPrice, 'currency' => 'RUB'],
        'party_surcharge' => null, 'search_plus_additional' => null, 'arithmetic_applied' => false,
        'raw_rates' => null, 'total_count' => is_int($total) ? $total : null];
    if (!is_array($rows) || $rows === [] || array_keys($rows) !== range(0, count($rows) - 1)
        || count($rows) !== 1 || $total !== 1 || !is_array($rows[0])) return $out;
    $adult = anex_egypt_248_apd_money_units($rows[0]['price_converted_adult'] ?? null);
    $base = anex_egypt_248_apd_money_units($searchPrice);
    if ($adult === null || $base === null || $adult > intdiv(PHP_INT_MAX, ANEX_EGYPT_248_APD_ADULTS)) return $out;
    $party = $adult * ANEX_EGYPT_248_APD_ADULTS;
    if ($base > PHP_INT_MAX - $party) return $out;
    $safeRates = [];
    foreach (['price_adult','price_chd','cashrate','price_converted_adult','price_converted_chd'] as $field) {
        $value = $rows[0][$field] ?? null;
        $safeRates[$field] = anex_egypt_248_apd_money_units($value) === null ? null : (string) $value;
    }
    $out['application_state'] = 'applied';
    $out['raw_rates'] = $safeRates;
    $out['party_surcharge'] = ['amount' => anex_egypt_248_apd_money_string($party), 'currency' => 'RUB',
        'source' => 'anex_b2b_additional_prices_daily'];
    $out['search_plus_additional'] = ['amount' => anex_egypt_248_apd_money_string($base + $party), 'currency' => 'RUB',
        'formula' => 'search_price_plus_program_date_party_additional'];
    $out['arithmetic_applied'] = true;
    return $out;
}

function anex_egypt_248_apd_main(array $input): array
{
    $out = ['schema_version' => 1, 'operation_id' => ANEX_EGYPT_248_APD_OPERATION, 'status' => 'blocked',
        'automatic_retry' => false, 'supplier_replay_allowed' => false, 'supplier_calls' => 0,
        'additional_prices_calls' => 0, 'booking_calls' => 0, 'broninit_calls' => 0, 'mapping_writes' => 0,
        'tourvisor_calls' => 0, 'semantic_reservation_written' => false,
        'b2b_tour_binding' => ['state' => 'unverified', 'searchtour_program_is_b2b_tour' => false,
            'authority_required' => 'supplier_dictionary_or_supplier_issued_binding']];
    if (array_keys($input) !== ['operation_id','source_sha'] || ($input['operation_id'] ?? null) !== ANEX_EGYPT_248_APD_OPERATION
        || !is_string($input['source_sha'] ?? null) || !preg_match('/\A[a-f0-9]{40}\z/D', $input['source_sha'])) {
        $out['reason'] = 'ANEX_EGYPT_APD_INPUT';
        return $out;
    }
    $out['source_sha'] = $input['source_sha'];
    if (anex_egypt_248_apd_b2b_binding() === null) {
        $out['reason'] = 'ANEX_EGYPT_APD_B2B_TOUR_BINDING_REQUIRED';
        return $out;
    }
    $out['reason'] = 'ANEX_EGYPT_APD_BINDING_CONTRACT_UNCONFIRMED';
    return $out;
}

if (!defined('ANYTOUR_ANEX_EGYPT_248_APD_LIBRARY_ONLY')) {
    error_reporting(0); ob_start();
    try {
        $raw = file_get_contents('php://stdin', false, null, 0, 4097);
        if (!is_string($raw) || $raw === '' || strlen($raw) > 4096) throw new RuntimeException('ANEX_EGYPT_APD_INPUT');
        $input = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
        if (!is_array($input)) throw new RuntimeException('ANEX_EGYPT_APD_INPUT');
        $report = anex_egypt_248_apd_main($input);
    } catch (Throwable $error) {
        $report = ['schema_version' => 1, 'operation_id' => ANEX_EGYPT_248_APD_OPERATION, 'status' => 'blocked',
            'reason' => 'ANEX_EGYPT_APD_INPUT', 'automatic_retry' => false, 'supplier_replay_allowed' => false,
            'supplier_calls' => 0, 'additional_prices_calls' => 0, 'booking_calls' => 0, 'broninit_calls' => 0,
            'mapping_writes' => 0, 'tourvisor_calls' => 0, 'semantic_reservation_written' => false];
    }
    ob_end_clean(); echo json_encode($report, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), "\n";
    exit(($report['status'] ?? null) === 'completed' ? 0 : 1);
}
