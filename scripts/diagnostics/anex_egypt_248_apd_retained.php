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

/** Select one current concrete APD context from returned SearchTour facts; never guess a program id. */
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

/** Apply only the already accepted #2362 party-rate rule; APD remains separate from Tourvisor fuel. */
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

function anex_egypt_248_apd_save(string $path, array $value): void
{
    $json = json_encode($value, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    if (!is_string($json) || strlen($json) > 131072) throw new RuntimeException('ANEX_EGYPT_APD_RECEIPT_LIMIT');
    $tmp = $path . '.tmp.' . bin2hex(random_bytes(8));
    $fh = fopen($tmp, 'x');
    if ($fh === false) throw new RuntimeException('ANEX_EGYPT_APD_RECEIPT');
    chmod($tmp, 0600);
    try {
        if (fwrite($fh, $json) !== strlen($json) || !fflush($fh)) throw new RuntimeException('ANEX_EGYPT_APD_RECEIPT');
        if (function_exists('fsync') && !fsync($fh)) throw new RuntimeException('ANEX_EGYPT_APD_RECEIPT');
    } finally { fclose($fh); }
    if (!rename($tmp, $path)) { @unlink($tmp); throw new RuntimeException('ANEX_EGYPT_APD_RECEIPT'); }
}

function anex_egypt_248_apd_main(array $input): array
{
    $out = ['schema_version' => 1, 'operation_id' => ANEX_EGYPT_248_APD_OPERATION, 'status' => 'blocked',
        'automatic_retry' => false, 'supplier_replay_allowed' => false, 'booking_calls' => 0, 'broninit_calls' => 0,
        'mapping_writes' => 0, 'tourvisor_calls' => 0, 'additional_prices_calls' => 0];
    $reserved = false; $lock = null; $path = null; $state = null; $secrets = [];
    try {
        if (array_keys($input) !== ['operation_id','source_sha'] || ($input['operation_id'] ?? null) !== ANEX_EGYPT_248_APD_OPERATION
            || !is_string($input['source_sha'] ?? null) || !preg_match('/\A[a-f0-9]{40}\z/D', $input['source_sha'])) {
            throw new RuntimeException('ANEX_EGYPT_APD_INPUT');
        }
        $out['source_sha'] = $input['source_sha'];
        $home = (string) getenv('HOME');
        $root = realpath($home . '/www/anytoour.ru');
        $preview = $root ? realpath($root . '/_preview/search3-anex-candidate') : false;
        $private = realpath($home . '/.anytoour-anex');
        if (!$root || !$preview || $preview !== $root . '/_preview/search3-anex-candidate' || !$private || $private !== $home . '/.anytoour-anex'
            || !in_array(realpath((string) getcwd()), [$root, $preview], true)) throw new RuntimeException('ANEX_EGYPT_APD_RUNTIME');
        require_once $private . '/search3-preview.php';
        require_once $preview . '/app/integrations/anex-search.php';
        require_once $preview . '/app/integrations/anex-search-mapping-registry.php';
        require_once $preview . '/app/integrations/anex-additional-prices-client.php';
        if (!defined('ANEX_API_TOKEN') || !is_string(ANEX_API_TOKEN) || trim(ANEX_API_TOKEN) === '') throw new RuntimeException('ANEX_EGYPT_APD_SEARCH_TOKEN');
        if (!defined('ANEX_B2B_TOKEN') || !is_string(ANEX_B2B_TOKEN) || trim(ANEX_B2B_TOKEN) === '') throw new RuntimeException('ANEX_EGYPT_APD_B2B_TOKEN');
        $secrets = [ANEX_API_TOKEN, ANEX_B2B_TOKEN];
        $db = is_file($root . '/data/db-v1.php') ? $root . '/data/db-v1.php' : $root . '/v2/data/db-v1.php';
        require_once $db; $pdo = v2_data_db();
        if (!$pdo instanceof PDO || $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') throw new RuntimeException('ANEX_EGYPT_APD_DB');
        $q = $pdo->prepare("SELECT d.id departure_id,d.name departure_name,c.id country_id,c.name country_name,h.id hotel_id FROM catalog_departures d CROSS JOIN catalog_countries c JOIN catalog_hotels h ON h.country_id=c.id WHERE d.is_active=1 AND c.is_active=1 AND h.is_active=1 AND h.id=? AND d.name IN ('Москва','Moscow') AND c.name IN ('Египет','Egypt') LIMIT 2");
        $q->execute([ANEX_EGYPT_248_APD_LOCAL_HOTEL]); $rows = $q->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) !== 1) throw new RuntimeException('ANEX_EGYPT_APD_LOCAL_CONTEXT');
        $local = $rows[0]; $registry = AnyTourAnexSearchMappingRegistry::fromPdo($pdo);
        if ($registry->resolve('anex_online', ANEX_EGYPT_248_APD_EXTERNAL_HOTEL, 'preview') !== ANEX_EGYPT_248_APD_LOCAL_HOTEL) {
            throw new RuntimeException('ANEX_EGYPT_APD_IDENTITY_CHANGED');
        }
        $path = $private . '/' . ANEX_EGYPT_248_APD_OPERATION . '.json';
        $lock = fopen($path . '.lock', 'c');
        if ($lock === false || !flock($lock, LOCK_EX)) throw new RuntimeException('ANEX_EGYPT_APD_LOCK');
        if (is_file($path)) {
            $prior = json_decode((string) file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
            if (is_array($prior) && ($prior['status'] ?? null) === 'completed' && is_array($prior['result'] ?? null)) {
                return array_replace($prior['result'], ['reused' => true]);
            }
            throw new RuntimeException('ANEX_EGYPT_APD_NO_REPLAY');
        }
        $state = ['schema_version' => 1, 'operation_id' => ANEX_EGYPT_248_APD_OPERATION, 'source_sha' => $input['source_sha'],
            'status' => 'reserved', 'reserved_at' => gmdate('c'), 'replay_allowed' => false];
        anex_egypt_248_apd_save($path, $state); $reserved = true;

        $client = new AnyTourAnexClient(ANEX_API_TOKEN); $cache = [];
        $departure = anytour_anex_search3_dictionary_id(anytour_anex_search3_dictionary($client, 'SearchTour_TOWNFROMS', [], $cache),
            [$local['departure_name'], 'Москва', 'Moscow']);
        $country = anytour_anex_search3_dictionary_id(anytour_anex_search3_dictionary($client, 'SearchTour_STATES', ['TOWNFROMINC' => $departure], $cache),
            [$local['country_name'], 'Египет', 'Egypt']);
        $dated = ['TOWNFROMINC' => $departure, 'STATEINC' => $country, 'CHECKIN_BEG' => '20261207', 'CHECKIN_END' => '20261207', 'ADULT' => 3, 'CHILD' => 0];
        $searchCurrency = anytour_anex_search3_dictionary_id(anytour_anex_search3_dictionary($client, 'SearchTour_CURRENCIES', $dated, $cache),
            ['RUB','RUR','Рубль','Рубли','Руб']);
        $criteria = ['supplier_namespace' => 'anex_online', 'departure_id' => $departure, 'destination_id' => $country, 'currency_id' => $searchCurrency,
            'checkin_begin' => ANEX_EGYPT_248_APD_DATE, 'checkin_end' => ANEX_EGYPT_248_APD_DATE, 'nights_from' => ANEX_EGYPT_248_APD_NIGHTS,
            'nights_till' => ANEX_EGYPT_248_APD_NIGHTS, 'adults' => ANEX_EGYPT_248_APD_ADULTS, 'children' => 0, 'child_ages' => [],
            'hotel_ids' => [ANEX_EGYPT_248_APD_EXTERNAL_HOTEL]];
        $search = new AnyTourAnexSearch($client, $registry->previewResolver(), $secrets);
        $result = $search->search($criteria); $concrete = []; $groups = [];
        foreach ($result['offers'] ?? [] as $offer) {
            if (!is_array($offer) || !anex_egypt_248_apd_target_offer($offer)) continue;
            if (($offer['kind'] ?? null) === 'concrete') $concrete[] = $offer;
            elseif (($offer['kind'] ?? null) === 'group_minimum') $groups[] = $offer;
        }
        if ($concrete === []) {
            if ($groups === [] || count($groups) > 6) throw new RuntimeException('ANEX_EGYPT_APD_GROUP_CONTEXT');
            foreach ($groups as $group) {
                $expanded = $search->expand($group['offer_key']);
                foreach ($expanded['offers'] ?? [] as $offer) if (is_array($offer) && anex_egypt_248_apd_target_offer($offer)) $concrete[] = $offer;
            }
        }
        $selected = anex_egypt_248_apd_select_concrete($concrete);
        if ($selected['price'] !== ANEX_EGYPT_248_APD_SEARCH_PRICE) throw new RuntimeException('ANEX_EGYPT_APD_SEARCH_PRICE_CHANGED');
        $contextDigest = hash('sha256', implode("\0", [$selected['program'], $selected['currency'], ANEX_EGYPT_248_APD_DATE, (string) ANEX_EGYPT_248_APD_NIGHTS]));
        $additional = new AnyTourAnexAdditionalPricesClient(ANEX_B2B_TOKEN);
        $payload = $additional->additionalPricesDaily(['page' => 1, 'pageSize' => 10, 'tour' => (int) $selected['program'],
            'dateBeg' => ANEX_EGYPT_248_APD_DATE, 'nights' => ANEX_EGYPT_248_APD_NIGHTS, 'currency' => (int) $selected['currency']]);
        $application = anex_egypt_248_apd_application($payload, $selected['price']);
        $party = $application['party_surcharge']['amount'] ?? null;
        $fuelUnits = anex_egypt_248_apd_money_units(ANEX_EGYPT_248_TV_FUEL);
        $partyUnits = anex_egypt_248_apd_money_units($party);
        $out = array_replace($out, ['status' => 'completed', 'observed_at' => gmdate('c'), 'reused' => false,
            'subject' => ['local_hotel_id' => ANEX_EGYPT_248_APD_LOCAL_HOTEL, 'anex_hotel_id' => ANEX_EGYPT_248_APD_EXTERNAL_HOTEL,
                'date' => ANEX_EGYPT_248_APD_DATE, 'nights' => ANEX_EGYPT_248_APD_NIGHTS, 'adults' => ANEX_EGYPT_248_APD_ADULTS,
                'children' => 0, 'meal_family' => 'ai', 'room_norm' => ANEX_EGYPT_248_APD_ROOM],
            'direct_anex' => ['search_price' => ['amount' => $selected['price'], 'currency' => 'RUB'],
                'concrete_offer_verified' => true, 'program_derived_from_concrete_offer' => true, 'native_currency_derived_from_concrete_offer' => true,
                'context_digest' => $contextDigest, 'search_http_requests' => $client->requestsMade()],
            'additional_prices' => $application,
            'additional_prices_calls' => $additional->requestsMade(),
            'additional_prices_cache_status' => $additional->lastRequestDiagnostics()['cache_status'] ?? null,
            'historical_tourvisor_observation' => ['source_operation' => 'read-only-anex-three-source-egypt-full-pages-v2',
                'display_price' => ['amount' => ANEX_EGYPT_248_TV_DISPLAY_PRICE, 'currency' => 'RUB'],
                'fuel_charge' => ['amount' => ANEX_EGYPT_248_TV_FUEL, 'currency' => 'RUB'], 'live_rechecked' => false],
            'comparison' => ['party_additional_equals_observed_tv_fuel' => $partyUnits !== null && $fuelUnits !== null && $partyUnits === $fuelUnits,
                'fuel_equivalence_verified' => false, 'identical_supplier_package_verified' => false, 'protected_arithmetic_changed' => false],
            'money_policy' => 'search price, AdditionalPricesDaily party addition, Tourvisor fuelCharge, package and quote/final remain separate facts']);
        $state = ['schema_version' => 1, 'operation_id' => ANEX_EGYPT_248_APD_OPERATION, 'source_sha' => $input['source_sha'],
            'status' => 'completed', 'completed_at' => gmdate('c'), 'replay_allowed' => false, 'result' => $out];
        anex_egypt_248_apd_save($path, $state); $reserved = false;
    } catch (Throwable $error) {
        $code = $error->getMessage();
        $safe = preg_match('/\AANEX_(?:EGYPT_APD|B2B)_[A-Z0-9_]{1,80}\z/D', $code) ? $code : 'ANEX_EGYPT_APD_UNCONFIRMED';
        $out['reason'] = $safe;
        if ($reserved && is_string($path) && is_array($state)) {
            $unknown = ['schema_version' => 1, 'operation_id' => ANEX_EGYPT_248_APD_OPERATION, 'source_sha' => $input['source_sha'] ?? null,
                'status' => 'unknown', 'recorded_at' => gmdate('c'), 'replay_allowed' => false, 'reason' => $safe];
            try { anex_egypt_248_apd_save($path, $unknown); } catch (Throwable $ignored) {}
            $out['status'] = 'unknown';
        }
    } finally {
        if (is_resource($lock)) { flock($lock, LOCK_UN); fclose($lock); }
    }
    $encoded = json_encode($out, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    foreach ($secrets as $secret) if (is_string($secret) && $secret !== '' && strpos($encoded, $secret) !== false) {
        return ['schema_version' => 1, 'operation_id' => ANEX_EGYPT_248_APD_OPERATION, 'status' => 'unknown',
            'reason' => 'ANEX_EGYPT_APD_SECRET_OUTPUT', 'automatic_retry' => false, 'supplier_replay_allowed' => false,
            'booking_calls' => 0, 'broninit_calls' => 0, 'mapping_writes' => 0, 'tourvisor_calls' => 0];
    }
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
            'booking_calls' => 0, 'broninit_calls' => 0, 'mapping_writes' => 0, 'tourvisor_calls' => 0];
    }
    ob_end_clean(); echo json_encode($report, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), "\n";
    exit(($report['status'] ?? null) === 'completed' ? 0 : 1);
}
