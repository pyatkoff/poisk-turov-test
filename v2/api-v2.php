<?php
/** Hardened V2-only Tourvisor gateway. */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$privateConfig = __DIR__ . '/config.php';
if (is_file($privateConfig)) require_once $privateConfig;
require_once __DIR__ . '/catalog-cache-v1.php';

/**
 * The checked source lives in /v2 while the shared deployed gateway lives at the
 * site root. Load the existing INT-owned autosave helper from either exact layout.
 * Absence stays response-transparent: supplier search must never fail just because
 * best-effort persistence is not installed in a source-only checkout.
 */
function tourvisor_autosave_bootstrap(): void
{
    foreach ([
        __DIR__ . '/app/integrations/tourvisor-anytour-offer-autosave.php',
        dirname(__DIR__) . '/app/integrations/tourvisor-anytour-offer-autosave.php',
    ] as $candidate) {
        if (!is_file($candidate) || is_link($candidate)) continue;
        require_once $candidate;
        break;
    }
}
tourvisor_autosave_bootstrap();

function tourvisor_fuel_passive_bootstrap(): void
{
    foreach ([
        __DIR__ . '/app/integrations/tourvisor-operator-fuel-passive-intake.php',
        dirname(__DIR__) . '/app/integrations/tourvisor-operator-fuel-passive-intake.php',
    ] as $candidate) {
        if (!is_file($candidate) || is_link($candidate)) continue;
        require_once $candidate;
        break;
    }
}
tourvisor_fuel_passive_bootstrap();

foreach ([__DIR__ . '/app/integrations/tourvisor-interactive-client.php', dirname(__DIR__) . '/app/integrations/tourvisor-interactive-client.php'] as $candidate) {
    if (!is_file($candidate) || is_link($candidate)) continue;
    require_once $candidate;
    break;
}

function out($data, int $status = 200)
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function jwt()
{
    $token = trim((string)getenv('TOURVISOR_JWT'));
    if ($token !== '') return $token;
    if (defined('TOURVISOR_JWT')) return trim((string)TOURVISOR_JWT);
    return '';
}

function bounded_int($value, int $min, int $max, int $fallback)
{
    $v = filter_var($value, FILTER_VALIDATE_INT);
    if ($v === false) return $fallback;
    return max($min, min($max, (int)$v));
}

function strict_int($value, int $min, int $max, string $name)
{
    $v = filter_var($value, FILTER_VALIDATE_INT);
    if ($v === false || (int)$v < $min || (int)$v > $max) {
        out(['ok' => false, 'error' => $name . ' must be between ' . $min . ' and ' . $max], 400);
    }
    return (int)$v;
}

function optional_int($value)
{
    if ($value === null || $value === '') return null;
    $v = filter_var($value, FILTER_VALIDATE_INT);
    return $v === false ? null : (int)$v;
}

function short_text($value, int $max = 200)
{
    return mb_substr(trim((string)$value), 0, $max, 'UTF-8');
}

function request_array(string $key, int $maxItems = 100, bool $unique = true)
{
    $value = $_GET[$key] ?? [];
    if ($value === '' || $value === null) return [];
    $list = is_array($value) ? $value : [$value];
    $out = [];
    foreach (array_slice($list, 0, $maxItems) as $v) {
        $v = short_text($v, 80);
        if ($v !== '') $out[] = $v;
    }
    return $unique ? array_values(array_unique($out)) : $out;
}

function child_ages()
{
    // Ages describe people, so equal ages must remain separate party members.
    $ages = request_array('childs', 3, false);
    $out = [];
    foreach ($ages as $age) {
        $v = filter_var($age, FILTER_VALIDATE_INT);
        if ($v === false || (int)$v < 0 || (int)$v > 17) {
            out(['ok' => false, 'error' => 'child age must be between 0 and 17'], 400);
        }
        $out[] = (int)$v;
    }
    return $out;
}

function search_id()
{
    $id = bounded_int($_GET['searchId'] ?? 0, 1, PHP_INT_MAX, 0);
    if ($id <= 0) out(['ok' => false, 'error' => 'searchId is required'], 400);
    return $id;
}

function query_string(array $params)
{
    return \AnyTour\TourvisorInteractiveClient::query($params);
}

function tv_get(string $path, array $params = [])
{
    try { return \AnyTour\TourvisorInteractiveClient::get($path, $params, jwt()); }
    catch (\AnyTour\TourvisorInteractiveFailure $error) { out($error->response, $error->httpStatus); }
}

function bool_param(string $key)
{
    return filter_var($_GET[$key] ?? false, FILTER_VALIDATE_BOOLEAN);
}

function date_param(string $key)
{
    $raw = short_text($_GET[$key] ?? '', 20);
    if ($raw === '') return null;
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $raw);
    $errors = DateTimeImmutable::getLastErrors();
    if (!$d || ($errors !== false && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0))) return null;
    return $d->format('Y-m-d');
}

function tourvisor_autosave_start(array $scope, array $response): void
{
    if (!class_exists('AnyTourTourvisorOfferAutosaveV1', false)) return;
    try {
        AnyTourTourvisorOfferAutosaveV1::captureSearchStart(
            $scope,
            $response,
            new DateTimeImmutable('now', new DateTimeZone('UTC'))
        );
    } catch (Throwable $ignored) {
        // Persistence is best-effort and must never change a successful supplier response.
    }
}

function tourvisor_autosave_status(int $searchId, array $response): void
{
    if (!class_exists('AnyTourTourvisorOfferAutosaveV1', false)) return;
    try {
        AnyTourTourvisorOfferAutosaveV1::captureSearchStatus(
            $searchId,
            $response,
            new DateTimeImmutable('now', new DateTimeZone('UTC'))
        );
    } catch (Throwable $ignored) {
    }
}

function tourvisor_autosave_results(int $searchId, int $limit, array $response): void
{
    if (!class_exists('AnyTourTourvisorOfferAutosaveV1', false)) return;
    try {
        AnyTourTourvisorOfferAutosaveV1::autosaveSearchResults(
            $searchId,
            $limit,
            $response,
            new DateTimeImmutable('now', new DateTimeZone('UTC'))
        );
    } catch (Throwable $ignored) {
    }
}

/** Existing private Andromeda searches directory is the single INT evidence store. */
function tourvisor_fuel_passive_runtime(): ?array
{
    if (!class_exists('AnyTourTourvisorOperatorFuelPassiveIntakeV1', false)) return null;
    // The deployed gateway and private runtime can have different physical DB
    // helpers. Reuse a complete loaded set; never redeclare or complete a partial set.
    $helpers = ['v2_data_db_config', 'v2_data_db', 'v2_data_normalize_text', 'v2_data_slug'];
    $loaded = count(array_filter($helpers, 'function_exists'));
    if ($loaded === 0) {
        require_once __DIR__ . '/data/db-v1.php';
    } elseif ($loaded !== count($helpers)) {
        return null;
    }
    $private = __DIR__ . '/.andromeda-private.php';
    if (!is_file($private) || is_link($private)) return null;
    $config = require $private;
    $catalog = is_array($config) ? ($config['catalog_path'] ?? null) : null;
    if (!is_string($catalog) || $catalog === '') return null;
    $directory = dirname($catalog) . '/searches';
    if (!is_dir($directory) || is_link($directory)) return null;
    return ['db' => v2_data_db(), 'directory' => $directory];
}

function tourvisor_fuel_passive_tour(string $tourId, array $response): void
{
    try {
        $runtime = tourvisor_fuel_passive_runtime();
        if ($runtime === null) return;
        AnyTourTourvisorOperatorFuelPassiveIntakeV1::captureTour(
            $tourId,
            $response,
            $runtime['db'],
            $runtime['directory'],
            new DateTimeImmutable('now', new DateTimeZone('UTC'))
        );
    } catch (Throwable $ignored) {
        // Evidence intake is best-effort and cannot replace a successful supplier response.
    }
}

function tourvisor_fuel_passive_flights(string $tourId, array $response): void
{
    try {
        $runtime = tourvisor_fuel_passive_runtime();
        if ($runtime === null) return;
        AnyTourTourvisorOperatorFuelPassiveIntakeV1::captureFlights(
            $tourId,
            $response,
            $runtime['directory'],
            new DateTimeImmutable('now', new DateTimeZone('UTC'))
        );
    } catch (Throwable $ignored) {
        // Evidence intake is best-effort and cannot replace a successful supplier response.
    }
}

$action = short_text($_GET['action'] ?? $_POST['action'] ?? 'health', 60);

switch ($action) {
    case 'health':
        $data = tv_get('/departures');
        out(['ok' => true, 'source' => 'tourvisor-direct', 'departuresCount' => is_array($data) ? count($data) : 0, 'gatewayVersion' => 2]);

    case 'departures':
        out(v2_catalog_get('/departures', ['departureCountryId' => bounded_int($_GET['departureCountryId'] ?? 1, 1, 9999, 1)], 3600));

    case 'countries':
        out(v2_catalog_get('/countries', [
            'departureId' => optional_int($_GET['departureId'] ?? null),
            'onlyCharter' => bool_param('onlyCharter'),
            'onlyDirect' => bool_param('onlyDirect'),
        ], 900));

    case 'arrivals':
        out(v2_catalog_get('/arrivals', [
            'departureId' => optional_int($_GET['departureId'] ?? null),
            'onlyCharter' => bool_param('onlyCharter'),
            'onlyDirect' => bool_param('onlyDirect'),
        ], 900));

    case 'regions':
        out(v2_catalog_get('/regions', ['countryId' => optional_int($_GET['countryId'] ?? null), 'arrivalId' => optional_int($_GET['arrivalId'] ?? null)], 3600));

    case 'subregions':
        out(v2_catalog_get('/subregions', ['countryId' => optional_int($_GET['countryId'] ?? null), 'regionId' => optional_int($_GET['regionId'] ?? null)], 3600));

    case 'meals':
        out(v2_catalog_get('/meals', [], 21600));

    case 'operators':
        out(v2_catalog_get('/operators', ['departureId' => optional_int($_GET['departureId'] ?? null), 'countryId' => optional_int($_GET['countryId'] ?? null)], 900));

    case 'hotel_types':
        out(v2_catalog_get('/hotel-types', ['countryId' => optional_int($_GET['countryId'] ?? null)], 21600));

    case 'hotel_services':
        out(v2_catalog_get('/hotel-group-services', ['countryId' => optional_int($_GET['countryId'] ?? null), 'regionIds' => request_array('regionIds', 50)], 21600));

    case 'hotels':
        out(tv_get('/hotels', [
            'countryId' => optional_int($_GET['countryId'] ?? null),
            'regionId' => optional_int($_GET['regionId'] ?? null),
            'category' => short_text($_GET['category'] ?? '', 20),
            'types' => request_array('types', 30),
            'rating' => short_text($_GET['rating'] ?? '', 20),
            'page' => bounded_int($_GET['page'] ?? 1, 1, 1000, 1),
            'limit' => bounded_int($_GET['limit'] ?? 100, 1, 100, 100),
        ]));

    case 'hotel_details':
        $id = bounded_int($_GET['hotelId'] ?? 0, 1, PHP_INT_MAX, 0);
        if ($id <= 0) out(['ok' => false, 'error' => 'hotelId is required'], 400);
        out(tv_get('/hotels/' . $id));

    case 'dates':
        out(tv_get('/tours/dates', [
            'departureId' => optional_int($_GET['departureId'] ?? null),
            'countryId' => optional_int($_GET['countryId'] ?? null),
            'arrivalId' => optional_int($_GET['arrivalId'] ?? null),
            'onlyCharter' => bool_param('onlyCharter'),
        ]));

    case 'search_start':
        try { $searchParams = \AnyTour\TourvisorInteractiveClient::searchParams($_GET); }
        catch (\AnyTour\TourvisorInteractiveFailure $error) { out($error->response, $error->httpStatus); }
        $data = tv_get('/tours/search', $searchParams);
        tourvisor_autosave_start($searchParams, $data);
        out($data);

    case 'search_continue':
        $id = search_id();
        out(tv_get('/tours/search/' . $id . '/continue'));

    case 'search_status':
        $id = search_id();
        $data = tv_get('/tours/search/' . $id . '/status', ['operatorStatus' => false]);
        tourvisor_autosave_status($id, $data);
        out($data);

    case 'search_results':
        $id = search_id();
        $limit = bounded_int($_GET['limit'] ?? 25, 1, 100, 25);
        $data = tv_get('/tours/search/' . $id, ['limit' => $limit]);
        tourvisor_autosave_results($id, $limit, $data);
        out($data);

    case 'tour':
        $id = short_text($_GET['tourId'] ?? '', 200);
        if ($id === '') out(['ok' => false, 'error' => 'tourId is required'], 400);
        $data = tv_get('/tours/' . rawurlencode($id), ['currency' => short_text($_GET['currency'] ?? 'RUB', 8) ?: 'RUB']);
        tourvisor_fuel_passive_tour($id, $data);
        out($data);

    case 'flights':
        $id = short_text($_GET['tourId'] ?? '', 200);
        if ($id === '') out(['ok' => false, 'error' => 'tourId is required'], 400);
        $data = tv_get('/tours/' . rawurlencode($id) . '/flights', ['currency' => short_text($_GET['currency'] ?? 'RUB', 8) ?: 'RUB']);
        tourvisor_fuel_passive_flights($id, $data);
        out($data);

    case 'rooms':
        out(tv_get('/rooms', ['ids' => request_array('ids', 30)]));

    default:
        out(['ok' => false, 'error' => 'Unknown V2 action'], 404);
}
