<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') exit(1);

$root=rtrim((string)getenv('HOME'),'/').'/www/anytoour.ru';
if(!is_dir($root)||is_link($root)||!is_file($root.'/config.php'))throw new RuntimeException('ROOT');
require_once $root.'/config.php';
$helper=is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php';
if(!is_file($helper))throw new RuntimeException('DB_HELPER');
require_once $helper;
$db=v2_data_db();
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
if($db->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql')throw new RuntimeException('MYSQL_REQUIRED');

$db->exec('START TRANSACTION READ ONLY');
try{
    $scope=['country_id'=>4,'date'=>'2026-10-12'];
    $latestStmt=$db->prepare(
        "SELECT MAX(last_seen_utc) FROM anex_search_hotel_observations
         WHERE country_id=:country AND last_checkin_from=:date AND last_checkin_to=:date"
    );
    $latestStmt->execute(['country'=>$scope['country_id'],'date'=>$scope['date']]);
    $latest=$latestStmt->fetchColumn();
    if(!is_string($latest)||$latest==='')throw new RuntimeException('NO_COHORT');

    $rowsStmt=$db->prepare(
        "SELECT anex_hotel_id,last_catalog_hotel_id
         FROM anex_search_hotel_observations
         WHERE country_id=:country AND last_checkin_from=:date AND last_checkin_to=:date AND last_seen_utc=:latest
         ORDER BY anex_hotel_id"
    );
    $rowsStmt->execute(['country'=>$scope['country_id'],'date'=>$scope['date'],'latest'=>$latest]);
    $rows=$rowsStmt->fetchAll(PDO::FETCH_ASSOC);

    $legacy=[];
    foreach($rows as $row){
        $id=$row['last_catalog_hotel_id']??null;
        if($id!==null && preg_match('/\A[1-9][0-9]*\z/D',(string)$id))$legacy[(int)$id]=true;
    }
    $legacyIds=array_keys($legacy);

    $legacyBridge=[];
    $localAlias=[];
    foreach(array_chunk(array_map('strval',$legacyIds),500) as $chunk){
        if(!$chunk)continue;
        $slots=implode(',',array_fill(0,count($chunk),'?'));
        $q=$db->prepare(
            "SELECT s.namespace,CAST(s.external_key AS CHAR) external_key,s.anytour_hotel_id
             FROM anytour_hotel_sources s
             JOIN anytour_hotels h ON h.id=s.anytour_hotel_id AND h.is_active=1
             WHERE s.namespace IN ('legacy_catalog','anytour_local_id') AND CAST(s.external_key AS CHAR) IN ($slots)"
        );
        $q->execute($chunk);
        foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r){
            $key=(int)$r['external_key'];$own=(int)$r['anytour_hotel_id'];
            if($key<1||$own<1)continue;
            if($r['namespace']==='legacy_catalog')$legacyBridge[$key][$own]=true;
            elseif($r['namespace']==='anytour_local_id')$localAlias[$key][$own]=true;
        }
    }
    $uniqueSingle=static function(array $map):int{
        $n=0;foreach($map as $targets)if(count($targets)===1)$n++;return $n;
    };
    $both=0;
    foreach($legacyIds as $id){
        if(isset($legacyBridge[$id])&&count($legacyBridge[$id])===1&&isset($localAlias[$id])&&count($localAlias[$id])===1)$both++;
    }

    $apd=[];
    foreach($db->query(
        "SELECT apd_state,COUNT(*) n FROM anytour_anex_apd_rates GROUP BY apd_state ORDER BY apd_state"
    )->fetchAll(PDO::FETCH_ASSOC) as $r)$apd[(string)$r['apd_state']]=(int)$r['n'];

    $latestApd=$db->query(
        "SELECT supplier_program_id,date_beg,nights,supplier_currency_id,apd_state,observed_at,expires_at
         FROM anytour_anex_apd_rates ORDER BY observed_at DESC,supplier_program_id LIMIT 5"
    )->fetchAll(PDO::FETCH_ASSOC);
    $latestApdSafe=[];
    foreach($latestApd as $r)$latestApdSafe[]=[
        'program'=>(int)$r['supplier_program_id'],
        'date'=>(string)$r['date_beg'],
        'nights'=>(int)$r['nights'],
        'currency'=>(int)$r['supplier_currency_id'],
        'state'=>(string)$r['apd_state'],
        'observed_at'=>(string)$r['observed_at'],
        'expires_at'=>(string)$r['expires_at'],
    ];

    $offers=[
        'raw'=>(int)$db->query("SELECT COUNT(*) FROM anytour_offers WHERE provider='anex'")->fetchColumn(),
        'active'=>(int)$db->query("SELECT COUNT(*) FROM anytour_offers WHERE provider='anex' AND is_active=1")->fetchColumn(),
        'ready'=>(int)$db->query("SELECT COUNT(*) FROM anytour_offers WHERE provider='anex' AND final_price_ready=1")->fetchColumn(),
    ];

    $out=[
        'source'=>'direct-anex-latest-cohort-canonical-audit-v1',
        'scope'=>$scope,
        'latest_seen_utc'=>$latest,
        'cohort_unique_hotels'=>count($rows),
        'cohort_resolved_legacy_hotels'=>count($legacyIds),
        'active_unique_legacy_bridges'=>$uniqueSingle($legacyBridge),
        'active_unique_anytour_local_aliases'=>$uniqueSingle($localAlias),
        'active_both_bridge_types'=>$both,
        'apd_states'=>(object)$apd,
        'latest_apd'=>$latestApdSafe,
        'anex_offer_store'=>$offers,
        'supplier_calls'=>0,'db_writes'=>0,'booking_calls'=>0,'lead_calls'=>0,
    ];
    $db->rollBack();
    echo json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),"\n";
}catch(Throwable $e){
    if($db->inTransaction())$db->rollBack();
    throw $e;
}
