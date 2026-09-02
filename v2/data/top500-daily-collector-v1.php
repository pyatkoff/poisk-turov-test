<?php
/**
 * Once-daily refresh for the owner priority hotel source set.
 * Every priority hotel with a current factual country mapping is searched exactly once per run,
 * in country-safe batches of <=30. Unresolvable source IDs remain explicit in the report.
 * This is inventory collection only; it does not publish SEO pages or feeds.
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/db-v1.php';
require_once __DIR__ . '/tourvisor-client-v1.php';
require_once __DIR__ . '/price-observer-v1.php';
require_once __DIR__ . '/top-hotels-v1.php';
require_once __DIR__ . '/top500-daily-plan-v1.php';

function top500_daily_arg(array $argv, string $name, ?string $fallback = null): ?string
{
    foreach ($argv as $arg) if (str_starts_with($arg, '--'.$name.'=')) return substr($arg, strlen($name) + 3);
    return $fallback;
}
function top500_daily_int(array $argv, string $name, int $default, int $min, int $max): int
{
    $raw = top500_daily_arg($argv, $name, (string)$default);
    $n = filter_var($raw, FILTER_VALIDATE_INT);
    return $n === false ? $default : max($min, min($max, (int)$n));
}
function top500_daily_search_id(array $payload): ?int
{
    foreach (['searchId','id'] as $key) {
        $id = filter_var($payload[$key] ?? null, FILTER_VALIDATE_INT);
        if ($id !== false && (int)$id > 0) return (int)$id;
    }
    return null;
}
function top500_daily_complete(array $payload): bool
{
    return (int)($payload['progress'] ?? 0) >= 100 || strtolower(trim((string)($payload['status'] ?? ''))) === 'complete';
}
function top500_daily_rows(array $payload): array
{
    if (array_is_list($payload)) return $payload;
    foreach (['hotels','items','results'] as $key) if (is_array($payload[$key] ?? null)) return $payload[$key];
    return [];
}
function top500_daily_fetch_results(int $searchId): array
{
    $last = null;
    foreach ([10000,5000,2000,1000,500,100] as $limit) {
        try { return top500_daily_rows(v2_data_tv_get('/tours/search/'.$searchId, ['limit'=>$limit])); }
        catch (Throwable $e) { $last = $e; }
    }
    throw new RuntimeException('top500 daily results unavailable', 0, $last);
}
function top500_daily_attempt_start(PDO $pdo, array $target): int
{
    $stmt = $pdo->prepare("INSERT INTO tour_matrix_collection_attempts (criterion,target_key,departure_id,country_id,region_id,hotel_ids_json,date_from,date_to,nights_from,nights_to,status,started_at) VALUES ('hotel_batch',:target_key,:departure,:country,NULL,:hotels,:date_from,:date_to,5,14,'started',:started_at)");
    $stmt->execute([
        'target_key'=>$target['target_key'],
        'departure'=>$target['departure_id'],
        'country'=>$target['country_id'],
        'hotels'=>json_encode($target['hotel_ids'], JSON_THROW_ON_ERROR),
        'date_from'=>$target['date_from'],
        'date_to'=>$target['date_to'],
        'started_at'=>(new DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
    ]);
    return (int)$pdo->lastInsertId();
}
function top500_daily_attempt_finish(PDO $pdo, int $id, string $status, ?int $searchId, int $rows, int $written, ?string $error = null): void
{
    $stmt = $pdo->prepare("UPDATE tour_matrix_collection_attempts SET status=:status,search_id=:search_id,rows_received=:rows,observations_written=:written,error_text=:error,finished_at=:finished_at WHERE id=:id");
    $stmt->execute([
        'status'=>$status,
        'search_id'=>$searchId,
        'rows'=>max(0,$rows),
        'written'=>max(0,$written),
        'error'=>$error !== null ? mb_substr($error,0,1000) : null,
        'finished_at'=>(new DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
        'id'=>$id,
    ]);
}

$pollAttempts = top500_daily_int($argv, 'poll-attempts', 20, 1, 30);
$pollSeconds = top500_daily_int($argv, 'poll-seconds', 2, 1, 10);
$preferredDepartureId = top500_daily_int($argv, 'preferred-departure-id', 1, 1, 1000000);
$startOffset = top500_daily_int($argv, 'start-days', 1, 0, 30);
$today = new DateTimeImmutable('today');
$dateFrom = $today->modify('+'.$startOffset.' days')->format('Y-m-d');
$dateTo = $today->modify('+'.($startOffset + 20).' days')->format('Y-m-d');

$pdo = v2_data_db();
$priorityIds = array_values(v2_priority_hotel_ids());
if ($priorityIds === []) throw new RuntimeException('top500 priority list empty');
$csv = implode(',', array_map('intval', $priorityIds));
$hotelRows = $pdo->query("SELECT id,country_id,country_name,is_active FROM catalog_hotels WHERE id IN (".$csv.") ORDER BY id")->fetchAll(PDO::FETCH_ASSOC) ?: [];
$departureRows = $pdo->query("SELECT dc.departure_id,dc.country_id,dc.is_active FROM catalog_departure_countries dc JOIN catalog_departures d ON d.id=dc.departure_id AND d.is_active=1 JOIN catalog_countries c ON c.id=dc.country_id AND c.is_active=1 WHERE dc.is_active=1 ORDER BY dc.country_id,dc.departure_id")->fetchAll(PDO::FETCH_ASSOC) ?: [];
$plan = v2_top500_daily_plan($priorityIds, $hotelRows, $departureRows, $dateFrom, $dateTo, $preferredDepartureId);

if ((int)$plan['source_hotel_count'] !== count($priorityIds) || (int)$plan['hotel_count'] <= 0 || (int)$plan['max_batch_size'] > 30) throw new RuntimeException('invalid top500 daily plan');
echo 'TOP500_DAILY_PLAN source_hotels='.$plan['source_hotel_count'].' hotels='.$plan['hotel_count'].' unavailable='.$plan['unavailable_count'].' batches='.$plan['batch_count'].' max_batch='.$plan['max_batch_size'].' from='.$dateFrom.' to='.$dateTo."\n";
if ((int)$plan['unavailable_count'] > 0) echo 'TOP500_DAILY_UNAVAILABLE_IDS '.implode(',', $plan['unavailable_priority_ids'])."\n";

$success = 0; $empty = 0; $failed = 0; $searchedHotels = 0; $writtenTotal = 0;
foreach ($plan['targets'] as $target) {
    $hotelIds = array_values(array_map('intval', $target['hotel_ids']));
    $requested = array_fill_keys($hotelIds, true);
    $searchedHotels += count($hotelIds);
    $attemptId = top500_daily_attempt_start($pdo, $target);
    $searchId = null;
    echo 'TOP500_DAILY_START country='.$target['country_id'].' departure='.$target['departure_id'].' hotels='.count($hotelIds).' batch='.$target['target_key']."\n";
    try {
        $search = [
            'departureId'=>(int)$target['departure_id'],
            'countryId'=>(int)$target['country_id'],
            'dateFrom'=>$target['date_from'],
            'dateTo'=>$target['date_to'],
            'nightsFrom'=>5,
            'nightsTo'=>14,
            'adults'=>2,
            'currency'=>'RUB',
            'onlyCharter'=>false,
            'onlyDirect'=>false,
            'hotelIds'=>$hotelIds,
        ];
        $searchId = top500_daily_search_id(v2_data_tv_get('/tours/search', $search));
        if ($searchId === null) throw new RuntimeException('no searchId');
        $complete = false;
        for ($poll = 0; $poll < $pollAttempts; $poll++) {
            sleep($pollSeconds);
            if (top500_daily_complete(v2_data_tv_get('/tours/search/'.$searchId.'/status', ['operatorStatus'=>false]))) { $complete = true; break; }
        }
        if (!$complete) throw new RuntimeException('bounded poll timeout');

        $rows = top500_daily_fetch_results($searchId);
        $trusted = [];
        foreach ($rows as $hotel) {
            if (!is_array($hotel)) continue;
            $hotelId = v2_price_observer_id($hotel['id'] ?? null);
            if ($hotelId === null || !isset($requested[$hotelId])) continue;
            $countryId = v2_price_observer_id($hotel['country'] ?? null);
            if ($countryId !== null && $countryId !== (int)$target['country_id']) continue;
            $trusted[] = $hotel;
        }
        $observed = v2_data_observe_search_results($trusted, [
            'searchId'=>$searchId,
            'departureId'=>(int)$target['departure_id'],
            'countryId'=>(int)$target['country_id'],
            'adults'=>2,
            'childs'=>[],
            'currency'=>'RUB',
            'source'=>'scheduled_monitor',
            'maxHotels'=>5000,
            'maxTours'=>50000,
        ]);
        $written = (int)($observed['written'] ?? 0);
        $writtenTotal += $written;
        if ($trusted === []) {
            top500_daily_attempt_finish($pdo, $attemptId, 'empty', $searchId, 0, $written);
            $empty++;
            echo 'TOP500_DAILY_EMPTY country='.$target['country_id'].' hotels='.implode(',', $hotelIds)."\n";
        } else {
            top500_daily_attempt_finish($pdo, $attemptId, 'success', $searchId, count($trusted), $written);
            $success++;
            echo 'TOP500_DAILY_OK country='.$target['country_id'].' returned_hotels='.count($trusted).' written='.$written."\n";
        }
    } catch (Throwable $e) {
        top500_daily_attempt_finish($pdo, $attemptId, 'error', $searchId, 0, 0, $e->getMessage());
        $failed++;
        fwrite(STDERR, 'TOP500_DAILY_ERROR country='.$target['country_id'].' hotels='.implode(',', $hotelIds).' message='.str_replace(["\r","\n"],' ', $e->getMessage())."\n");
    }
}

if ($searchedHotels !== (int)$plan['hotel_count']) throw new RuntimeException('top500 daily run did not attempt every resolvable priority hotel');
echo 'TOP500_DAILY_DONE source_hotels='.$plan['source_hotel_count'].' hotels='.$searchedHotels.' unavailable='.$plan['unavailable_count'].' batches='.count($plan['targets']).' success='.$success.' empty='.$empty.' failed='.$failed.' observations_written='.$writtenTotal."\n";
if ($failed > 0) exit(2);
