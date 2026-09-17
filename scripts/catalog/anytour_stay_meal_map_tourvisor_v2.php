<?php
/** One-shot reviewed CURRENT Tourvisor meal mapping v2 apply. No supplier I/O, rooms, source or canonical overwrite. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

const ANYTOUR_STAY_MEAL_V2_OPERATION = 'local-stay-meal-map-tourvisor-2690-20260917-v2';
const ANYTOUR_STAY_MEAL_V2_SOURCE = 'b90390c1c2008e07a91b053107d68822a45dd213';
const ANYTOUR_STAY_MEAL_V2_CURRENT = 'b4a03a16cac10139760abb3b1c9c22d281cebbcb4a78bde32c5a3691a8a1c06a';
const ANYTOUR_STAY_MEAL_V2_ROWS = 247;

function anytour_stay_meal_v2_json(array $value): string
{
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}

function anytour_stay_meal_v2_checkpoint($journal, string $event, array $state): void
{
    if (!is_resource($journal)) throw new RuntimeException('Journal unavailable');
    $line = anytour_stay_meal_v2_json(['event'=>$event,'atUtc'=>gmdate('c'),'state'=>$state]) . "\n";
    if (fwrite($journal, $line) !== strlen($line) || !fflush($journal) || !fsync($journal)) {
        throw new RuntimeException('Durable journal checkpoint failed');
    }
}

function anytour_stay_meal_v2_write(string $path, array $value): string
{
    $bytes = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
    $out = fopen($path, 'x+b');
    if (!$out || fwrite($out, $bytes) !== strlen($bytes) || !fflush($out) || !fsync($out)) {
        if (is_resource($out)) fclose($out);
        throw new RuntimeException('Exclusive durable receipt write failed');
    }
    fclose($out);
    return hash('sha256', $bytes);
}

function anytour_stay_meal_v2_validate(array $manifest): array
{
    $targets = ['3'=>'breakfast','4'=>'half-board','7'=>'all-inclusive','9'=>'ultra-all-inclusive'];
    $expected = ['3'=>79,'4'=>19,'7'=>100,'9'=>49];
    if (($manifest['version'] ?? null) !== 1 || ($manifest['operation'] ?? null) !== ANYTOUR_STAY_MEAL_V2_OPERATION
        || !is_array($manifest['rows'] ?? null) || !array_is_list($manifest['rows']) || count($manifest['rows']) !== ANYTOUR_STAY_MEAL_V2_ROWS) {
        throw new RuntimeException('Wrong sealed v2 manifest');
    }
    $counts = array_fill_keys(array_keys($expected), 0); $seen = [];
    foreach ($manifest['rows'] as $row) {
        if (!is_array($row) || !is_array($row['scope'] ?? null) || !is_array($row['reference'] ?? null)
            || !is_array($row['target'] ?? null) || !is_array($row['evidence'] ?? null)) throw new RuntimeException('Malformed v2 row');
        $scope=$row['scope']; $ref=$row['reference']; $e=$row['evidence']; $id=(string)($ref['externalKey'] ?? '');
        if (($scope['namespace'] ?? null)!=='legacy_catalog' || ($ref['kind'] ?? null)!=='meal' || ($ref['keyKind'] ?? null)!=='code'
            || !isset($targets[$id]) || ($row['target']['code'] ?? null)!==$targets[$id]
            || ($e['reviewedBy'] ?? null)!=='pyatkoff'
            || !str_contains((string)($e['ref'] ?? ''), ANYTOUR_STAY_MEAL_V2_CURRENT)
            || !str_contains((string)($e['ref'] ?? ''), 'tv-meal:' . $id . '->' . $targets[$id])) {
            throw new RuntimeException('Unreviewed mapping in v2 manifest');
        }
        if (!is_int($row['hotelId'] ?? null) || $row['hotelId']<1
            || !is_string($row['sourceSha256'] ?? null) || !preg_match('/^[0-9a-f]{64}$/D',$row['sourceSha256'])
            || !is_string($e['sha256'] ?? null) || !preg_match('/^[0-9a-f]{64}$/D',$e['sha256'])) throw new RuntimeException('Invalid v2 identity/evidence');
        $key=anytour_stay_meal_v2_json([$scope,$ref]); if(isset($seen[$key]))throw new RuntimeException('Duplicate exact v2 source decision');
        $seen[$key]=true; $counts[$id]++;
    }
    if ($counts !== $expected) throw new RuntimeException('V2 meal distribution changed');
    return $counts;
}

if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) !== __FILE__) exit;
$operationDir=''; $journal=null; $phase='before_reservation'; $committed=false;
try {
    if ($argc!==1 || basename(dirname(__DIR__,2))!=='payload') throw new RuntimeException('Fixed private invocation required');
    $operationDir=dirname(__DIR__,3);
    if (basename($operationDir)!==ANYTOUR_STAY_MEAL_V2_OPERATION) throw new RuntimeException('Wrong operation directory');
    $reservation=json_decode((string)file_get_contents($operationDir.'/reservation.json'),true,32,JSON_THROW_ON_ERROR);
    if (($reservation['operation']??'')!==ANYTOUR_STAY_MEAL_V2_OPERATION || ($reservation['dataSource']??'')!==ANYTOUR_STAY_MEAL_V2_SOURCE
        || ($reservation['attempt']??0)!==1 || ($reservation['expectedRows']??0)!==ANYTOUR_STAY_MEAL_V2_ROWS
        || ($reservation['expectedNewMappings']??0)!==ANYTOUR_STAY_MEAL_V2_ROWS || ($reservation['noReplay']??null)!==true) {
        throw new RuntimeException('Wrong v2 reservation');
    }
    $manifestPath=$operationDir.'/manifest.json'; $manifestSha=hash_file('sha256',$manifestPath);
    if (!is_string($manifestSha) || !is_string($reservation['manifestSha256']??null) || !hash_equals($reservation['manifestSha256'],$manifestSha)) {
        throw new RuntimeException('V2 manifest digest mismatch');
    }
    $manifest=json_decode((string)file_get_contents($manifestPath),true,64,JSON_THROW_ON_ERROR);
    if(!is_array($manifest))throw new RuntimeException('V2 manifest object required');
    $counts=anytour_stay_meal_v2_validate($manifest);
    if(is_file($operationDir.'/journal.jsonl')||is_file($operationDir.'/result.json'))throw new RuntimeException('Existing v2 operation state; no replay');
    $journal=fopen($operationDir.'/journal.jsonl','x+b'); if(!$journal)throw new RuntimeException('Cannot reserve v2 journal');
    anytour_stay_meal_v2_checkpoint($journal,'reserved',['operation'=>ANYTOUR_STAY_MEAL_V2_OPERATION,'manifestSha256'=>$manifestSha,'rows'=>247,'counts'=>$counts,'noReplay'=>true]);

    foreach(['ANYTOUR_DATA_DSN','ANYTOUR_DATA_DB_USER','ANYTOUR_DATA_DB_PASSWORD','ANYTOUR_DATA_DB_HOST','ANYTOUR_DATA_DB_NAME','ANYTOUR_DATA_DB_PORT'] as $key){
        if(getenv($key)!==false&&getenv($key)!=='')throw new RuntimeException('Unexpected DB override');
    }
    $root=rtrim((string)getenv('HOME'),'/').'/www/anytoour.ru'; $configFile=$root.'/config.php'; $configHash=hash_file('sha256',$configFile);
    if($configHash===false)throw new RuntimeException('Project configuration missing');
    require_once $configFile;
    require_once __DIR__.'/../../v2/data/db-v1.php';
    require_once __DIR__.'/anytour_stay_import.php';
    $manifest=AnyTourStayImport::manifest($manifest); anytour_stay_meal_v2_validate($manifest);
    $config=v2_data_db_config();
    if(!str_starts_with((string)$config['dsn'],'mysql:')||trim((string)$config['user'])==='')throw new RuntimeException('Explicit project MySQL configuration required');
    $pdo=new PDO($config['dsn'],$config['user'],$config['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_TIMEOUT=>5]);
    $import=new AnyTourStayImport($pdo);

    $phase='plan'; $plan=$import->plan($manifest);
    if(($plan['status']??null)!=='prepared_read_only'||($plan['rows']??null)!==247||($plan['createRooms']??null)!==0
        ||($plan['createMappings']??null)!==247||($plan['unchangedMappings']??null)!==0||($plan['writes']??null)!==0
        ||!is_string($plan['planSha256']??null)||!preg_match('/^[0-9a-f]{64}$/D',$plan['planSha256'])) {
        throw new RuntimeException('CURRENT v2 plan is not exact 247-mapping delta');
    }
    $planReceiptSha=anytour_stay_meal_v2_write($operationDir.'/plan.json',$plan);
    anytour_stay_meal_v2_checkpoint($journal,'planned',['planSha256'=>$plan['planSha256'],'planReceiptSha256'=>$planReceiptSha,'createMappings'=>247,'createRooms'=>0,'noReplay'=>true]);

    $phase='apply';
    $receipt=$import->apply($manifest,$plan['planSha256'],static function(array $state)use($journal):void{anytour_stay_meal_v2_checkpoint($journal,'import',$state);});
    if(($receipt['status']??null)!=='committed_verified'||($receipt['createdMappings']??null)!==247||($receipt['createdRooms']??null)!==0
        ||($receipt['unchangedMappings']??null)!==0||($receipt['verifiedMappings']??null)!==247
        ||($receipt['sourceWrites']??null)!==0||($receipt['canonicalOverwrites']??null)!==0||($receipt['supplierCalls']??null)!==0) {
        throw new RuntimeException('V2 importer terminal receipt mismatch');
    }
    $committed=true; $phase='post_commit_readback';
    $after=$import->plan($manifest);
    if(($after['status']??null)!=='prepared_read_only'||($after['rows']??null)!==247||($after['createRooms']??null)!==0
        ||($after['createMappings']??null)!==0||($after['unchangedMappings']??null)!==247||($after['writes']??null)!==0) {
        throw new RuntimeException('V2 independent post-COMMIT readback mismatch');
    }
    if(!hash_equals($configHash,(string)hash_file('sha256',$configFile)))throw new RuntimeException('Project configuration changed');

    $phase='terminal_receipt';
    $result=['status'=>'meal_mappings_committed_verified','operation'=>ANYTOUR_STAY_MEAL_V2_OPERATION,'dataSource'=>ANYTOUR_STAY_MEAL_V2_SOURCE,
        'executionSource'=>(string)($reservation['executionSource']??''),'run'=>(int)($reservation['run']??0),'manifestSha256'=>$manifestSha,
        'currentEvidenceResultSha256'=>ANYTOUR_STAY_MEAL_V2_CURRENT,'mappingCounts'=>$counts,'createdMappings'=>247,'createdRooms'=>0,
        'unresolvedMealIds'=>['2','5'],'roomsRemainUnresolved'=>true,'initialPlan'=>$plan,'importReceipt'=>$receipt,'postCommitPlan'=>$after,
        'supplierCalls'=>0,'sourceWrites'=>0,'canonicalOverwrites'=>0,'publicFileWrites'=>0,'projectConfigurationUnchanged'=>true,'noReplay'=>true];
    $resultSha=anytour_stay_meal_v2_write($operationDir.'/result.json',$result);
    anytour_stay_meal_v2_checkpoint($journal,'terminal',['status'=>$result['status'],'resultSha256'=>$resultSha,'noReplay'=>true]);
    fclose($journal);$journal=null;
    echo 'ANYTOUR_STAY_MEAL_MAP_V2_VERIFIED result_sha256='.$resultSha." mappings=247 rooms=0 supplier_calls=0\n";
} catch(Throwable $e) {
    $class=get_class($e);
    $state=$class==='AnyTourStayCommitUncertain'?'commit_unknown':($class==='AnyTourStayReadbackFailed'?'committed_unverified':($committed?'committed_verified_terminal_incomplete':'failed_before_commit'));
    if(is_resource($journal)){try{anytour_stay_meal_v2_checkpoint($journal,'stopped',['status'=>$state,'phase'=>$phase,'class'=>$class,'noReplay'=>true]);}catch(Throwable){}fclose($journal);$journal=null;}
    if($operationDir!==''&&is_dir($operationDir)&&!is_file($operationDir.'/terminal.json')){try{anytour_stay_meal_v2_write($operationDir.'/terminal.json',['status'=>$state,'phase'=>$phase,'class'=>$class,'noReplay'=>true]);}catch(Throwable){}}
    fwrite(STDERR,'ANYTOUR_STAY_MEAL_MAP_V2_STOPPED state='.$state.' phase='.$phase.' class='.$class."; NO REPLAY\n"); exit(1);
}
