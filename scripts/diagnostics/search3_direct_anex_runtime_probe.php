<?php
declare(strict_types=1);

/** Installed SEARCH boundary diagnosis only; never installs code or calls a quote. */
const S3_ANEX_OPERATION = 'search3-direct-anex-runtime-probe-20260926-v1';
const S3_ANEX_SOURCE = '93ce2190a444a7b4aa2920a013f14c8b2f64e79c';
const S3_ANEX_BLOBS = [
    'api-anex-search3-preview.php' => '22710804d8ae108098e3c545efe9835ff14e0024',
    'app/integrations/anex-client.php' => 'b1e7691409bf7289ca74e9562829dddde5c76188',
    'app/integrations/anex-search.php' => '51546f5fcc5d6f471a391e580a87d6137ea2256a',
];
function s3_anex_error(Throwable $error, array $last): array {
    $known = ['ANEX_TOKEN_REQUIRED','ANEX_REQUEST_LIMIT','ANEX_TRANSPORT_ERROR','ANEX_INVALID_RESPONSE',
        'ANEX_RESPONSE_TOO_LARGE','ANEX_HTTP_ERROR','ANEX_SUPPLIER_ERROR','ANEX_INVALID_PARAMS',
        'ANEX_INVALID_PRICES','ANEX_SEARCH_PAGINATION_LIMIT','ANEX_RATE_LIMIT',
        'ANEX_FILTER_UNSUPPORTED','ANEX_DESTINATION_UNSUPPORTED','ANEX_INVALID_SEARCH',
        'project_invalid','runtime_missing','runtime_drift','config_unavailable','operation_exists_no_replay',
        'reservation_failed','scope_expired','request_budget_exceeded'];
    $class = get_class($error);
    $safe = ['exception' => in_array($class, ['PDOException','RuntimeException','InvalidArgumentException','Error','TypeError'], true) ? $class : 'other',
        'reason' => in_array($error->getMessage(), $known, true) ? $error->getMessage() : 'internal_error'];
    $actions = ['SearchTour_TOWNFROMS','SearchTour_STATES','SearchTour_CURRENCIES','SearchTour_PRICES'];
    if (in_array($last['action'] ?? null, $actions, true)) $safe['action'] = $last['action'];
    foreach (['http_status'=>599,'curl_errno'=>999,'supplier_code'=>99999,'response_bytes'=>2097153] as $key=>$max) {
        if (isset($last[$key]) && is_int($last[$key]) && $last[$key] >= 0 && $last[$key] <= $max) $safe[$key] = $last[$key];
    }
    if ($error instanceof PDOException && preg_match('/\A[A-Z0-9]{5}\z/D', (string)$error->getCode())) $safe['sql_state'] = (string)$error->getCode();
    return $safe;
}
function s3_anex_durable(string $path, array $value): void {
    $body = json_encode($value, JSON_THROW_ON_ERROR);
    $f = @fopen($path, 'x');
    if ($f === false) throw new RuntimeException('operation_exists_no_replay');
    try {
        if (!chmod($path, 0600) || fwrite($f, $body) !== strlen($body) || !fflush($f)
            || (function_exists('fsync') && !fsync($f))) throw new RuntimeException('reservation_failed');
    } finally { fclose($f); }
}
function s3_anex_verify(string $target): void {
    foreach (S3_ANEX_BLOBS as $name=>$expected) {
        $path=$target.'/'.$name;
        if (!is_file($path) || is_link($path) || filesize($path)>2097152) throw new RuntimeException('runtime_missing');
        $bytes=file_get_contents($path);
        if (!is_string($bytes) || sha1('blob '.strlen($bytes)."\0".$bytes)!==$expected) throw new RuntimeException('runtime_drift');
    }
}
function search3_direct_anex_runtime_main(): int {
    error_reporting(0); ini_set('display_errors','0'); ini_set('log_errors','0');
    $result=['operation'=>S3_ANEX_OPERATION,'source'=>S3_ANEX_SOURCE,'status'=>'blocked','stage'=>'preflight',
        'client_requests'=>0,'database_writes'=>0,'quote_calls'=>0,'replay_allowed'=>false];
    $pdo=null; $client=null; $dir=null; $stage='preflight';
    try {
        $root=realpath(getcwd());
        if (PHP_SAPI!=='cli' || !$root || basename($root)!=='anytoour.ru') throw new RuntimeException('project_invalid');
        $target=$root.'/_preview/search3-anex-candidate';
        $private=dirname($root,2).'/.anytoour-anex';
        if (!is_dir($target) || is_link($target) || !is_dir($private) || is_link($private)) throw new RuntimeException('runtime_missing');
        s3_anex_verify($target);
        if ((new DateTimeImmutable('today', new DateTimeZone('Europe/Moscow')))->format('Y-m-d')>'2026-10-10') throw new RuntimeException('scope_expired');
        $operationDir=$private.'/'.S3_ANEX_OPERATION;
        if (file_exists($operationDir) || is_link($operationDir)) throw new RuntimeException('operation_exists_no_replay');
        if (!mkdir($operationDir,0700)) throw new RuntimeException('reservation_failed');
        $dir=$operationDir;
        s3_anex_durable($dir.'/reservation.json',['operation'=>S3_ANEX_OPERATION,'source'=>S3_ANEX_SOURCE,'at'=>time(),'replay_allowed'=>false]);
        $stage='runtime_include';
        $_SERVER['DOCUMENT_ROOT']=$root;
        require_once $target.'/api-anex-search3-preview.php';
        require_once $target.'/app/integrations/anex-initial-week-gate.php';
        require_once $target.'/app/integrations/anex-preview-gateway.php';
        require_once $target.'/app/integrations/anex-search-mapping-registry.php';
        if (!is_file($target.'/.anex-private.php')) throw new RuntimeException('config_unavailable');
        require_once $target.'/.anex-private.php';
        $token=trim((string)getenv('ANEX_API_TOKEN'));
        if ($token==='' && defined('ANEX_API_TOKEN')) $token=trim((string)ANEX_API_TOKEN);
        if ($token==='') throw new RuntimeException('config_unavailable');
        $stage='database';
        $helper=is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php';
        require_once $helper;
        $pdo=v2_data_db();
        $pdo->exec('START TRANSACTION READ ONLY');
        $client=new AnyTourAnexClient($token); unset($token);
        $params=['departureId'=>'1','countryId'=>'4','dateFrom'=>'2026-10-10','dateTo'=>'2026-10-16',
            'nightsFrom'=>7,'nightsTo'=>7,'adults'=>2,'childs'=>[],'meal'=>'','hotelCategory'=>'','hotelRating'=>'',
            'hotelTypes'=>[],'hotelIds'=>[],'hotelServices'=>[],'arrivalId'=>'','regionIds'=>[],'subregionIds'=>[],
            'operatorIds'=>[],'priceFrom'=>'','priceTo'=>'','currency'=>'RUB','onlyCharter'=>false,'onlyDirect'=>false];
        $cache=[]; $diagnostics=[]; $state=[]; $stage='search_core';
        // Exactly one invocation, using the unchanged installed client (hard limit 12).
        // No HTTP handler retry, no observer, no program-observation or offer autosave.
        $data=anytour_anex_search3_run(['action'=>'search','generation'=>1,'params'=>$params],$pdo,$client,$cache,$diagnostics,null,$state,'all');
        $result['hotels']=count($data['hotels']??[]);
        $result['offers']=array_sum(array_map(static fn(array $hotel):int=>count($hotel['tours']??[]),$data['hotels']??[]));
        s3_anex_verify($target);
        $result['status']='complete';
    } catch (Throwable $error) {
        $result['status']=$stage==='search_core'?'failed':'blocked';
        $result['failure']=s3_anex_error($error,$client!==null?$client->lastRequestDiagnostics():[]);
    } finally {
        $result['stage']=$stage;
        if ($client!==null) $result['client_requests']=$client->requestsMade();
        if ($pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
        if ($dir!==null) {
            try { s3_anex_durable($dir.'/result.json',$result); }
            catch (Throwable $ignored) { $result['status']='unknown'; }
        }
    }
    echo json_encode($result,JSON_THROW_ON_ERROR),"\n";
    return $result['status']==='unknown'?1:0;
}
