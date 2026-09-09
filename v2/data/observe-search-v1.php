<?php
/** Refetch trusted results and acknowledge only after observations are committed. */
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405); header('Allow: POST');
    echo json_encode(['ok'=>false,'error'=>'POST required']); exit;
}
$length = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($length > 32768) { http_response_code(413); echo json_encode(['ok'=>false,'error'=>'payload too large']); exit; }
$body = (string)file_get_contents('php://input', false, null, 0, 32769);
if (strlen($body) > 32768) { http_response_code(413); echo json_encode(['ok'=>false,'error'=>'payload too large']); exit; }
$payload = json_decode($body, true);
if (!is_array($payload)) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'invalid JSON']); exit; }
$positiveInt = static function ($value): ?int {
    $v = filter_var($value, FILTER_VALIDATE_INT);
    return $v !== false && (int)$v > 0 ? (int)$v : null;
};
$searchId = $positiveInt($payload['searchId'] ?? null);
$departureId = $positiveInt($payload['departureId'] ?? null);
$countryId = $positiveInt($payload['countryId'] ?? null);
$adults = $positiveInt($payload['adults'] ?? 2);
$childs = $payload['childs'] ?? [];
if ($searchId === null || $departureId === null || $countryId === null || $adults === null || $adults > 6 || !is_array($childs) || !array_is_list($childs) || count($childs) > 3) {
    http_response_code(400); echo json_encode(['ok'=>false,'error'=>'invalid search context']); exit;
}
foreach ($childs as $age) {
    $v = filter_var($age, FILTER_VALIDATE_INT);
    if ($v === false || (int)$v < 0 || (int)$v > 17) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'invalid child age']); exit; }
}
ignore_user_abort(true);
@set_time_limit(60);
try {
    require_once __DIR__.'/tourvisor-client-v1.php';
    require_once __DIR__.'/price-observer-v1.php';
    require_once __DIR__.'/observe-search-batches-v1.php';
    // Same supplier request as the public completed/continued search. Do not
    // restart the search or accept client-provided prices as authoritative.
    $rows = v2_observe_search_result_rows(v2_data_tv_get('/tours/search/' . $searchId, ['limit'=>100]));
    $pdo = v2_data_db();
    $write = static function (array $hotels, array $context) use ($pdo): array {
        $pdo->beginTransaction();
        try {
            $result = v2_data_observe_search_results($hotels, $context);
            $pdo->commit();
            return $result;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    };
    $result = v2_observe_search_batches($rows, [
        'searchId'=>$searchId,'departureId'=>$departureId,'countryId'=>$countryId,
        'adults'=>$adults,'childs'=>$childs,'currency'=>'RUB',
    ], $write);
    error_log('ANYTOUR_PRICE_OBSERVER search='.$searchId.' rows='.$result['rows'].' written='.$result['written'].' ignored='.$result['ignored'].' seen='.$result['seen'].' persisted=1');
    http_response_code(200);
    echo json_encode(['ok'=>true,'persisted'=>true,'searchId'=>$searchId]+$result, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    // Search UI stays independent. A transient observer failure is visible to
    // its caller and safely retryable because the existing inserts deduplicate.
    error_log('ANYTOUR_PRICE_OBSERVER_FAILED search='.$searchId.' '.mb_substr($e->getMessage(),0,800));
    http_response_code(503); header('Retry-After: 2');
    echo json_encode(['ok'=>false,'persisted'=>false,'searchId'=>$searchId,'error'=>'observation unavailable']);
}
