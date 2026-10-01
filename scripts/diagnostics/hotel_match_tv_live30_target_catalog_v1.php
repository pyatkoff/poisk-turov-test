<?php
declare(strict_types=1);
require_once __DIR__.'/hotel_match_pending8_transition_v76.php';
const TC30_OP='int-andromeda-match-live30-target-catalog-20261001-v1';
const TC30_BATCH='tv-live30-targets-20261001';
const TC30_CAP=20000;

/** Only hotel identity/geography fields; no supplier payload, history or URL. */
function tc30_row(array $hotel,array $occupants,bool $manual,bool $excluded): array {
    $out=['id'=>(int)$hotel['id']];
    w76_need($out['id']>0&&(string)$out['id']===(string)$hotel['id'],'target_catalog_id');
    foreach(['name','country_id','country_name','region_name','subregion_name','category'] as $key){
        $v=$hotel[$key]??null;
        w76_need($v===null||is_scalar($v),'target_catalog_text');
        $v=$v===null?null:(string)$v;
        w76_need($v===null||(strlen($v)<=512&&preg_match('/[\x00-\x1f\x7f]|https?:\/\//iu',$v)===0),'target_catalog_text');
        $out[$key]=$v;
    }
    w76_need($out['name']!==null&&$out['name']!=='','target_catalog_name');
    w76_need((string)($hotel['is_active']??'')==='1','target_catalog_active');$out['is_active']=true;
    foreach(['latitude'=>90,'longitude'=>180] as $key=>$bound){
        $v=$hotel[$key]??null;
        w76_need($v===null||(is_numeric($v)&&is_finite((float)$v)&&abs((float)$v)<=$bound),'target_catalog_coordinates');
        $out[$key]=$v===null?null:(float)$v;
    }
    w76_need(count($occupants)<=32,'target_catalog_occupant_cap');
    foreach($occupants as $v)w76_need(is_string($v)&&preg_match('/^[1-9][0-9]{0,31}$/D',$v)===1,'target_catalog_occupant');
    $occupants=array_values(array_unique($occupants));sort($occupants,SORT_STRING);
    return $out+['accepted_samo_ids'=>$occupants,'manual_hold'=>$manual,'exclusion_hold'=>$excluded];
}

/** A new target-field snapshot of the complete existing TV-live30 cohort. */
function tc30_current(PDO $db): array {
    w76_need(!$db->inTransaction(),'target_catalog_transaction');
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $db->exec('START TRANSACTION READ ONLY');
    try{
        $hotels=w76_q($db,"SELECT DISTINCT h.id,h.name,h.country_id,h.country_name,h.region_name,h.subregion_name,h.category,h.is_active,h.latitude,h.longitude FROM catalog_hotels h JOIN tour_operator_identity_observations o ON o.hotel_id=h.id WHERE h.is_active=1 AND o.last_seen_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 30 DAY) AND h.country_name NOT IN ('Россия','Абхазия','Russia','Abkhazia','Russian Federation') ORDER BY h.id LIMIT 20001");
        w76_need(count($hotels)<=TC30_CAP,'target_catalog_cap');$wanted=[];$samo=[];$manual=[];$excluded=[];
        foreach($hotels as $h)$wanted[(int)$h['id']]=true;
        foreach(w76_q($db,"SELECT local_hotel_id,external_hotel_id FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IS NOT NULL LIMIT 50001") as $r){
            $id=(int)$r['local_hotel_id'];if(isset($wanted[$id]))$samo[$id][]=(string)$r['external_hotel_id'];
        }
        foreach(w76_q($db,'SELECT DISTINCT catalog_hotel_id FROM anex_hotel_decisions WHERE catalog_hotel_id IS NOT NULL LIMIT 50001') as $r)$manual[(int)$r['catalog_hotel_id']]=true;
        foreach(w76_q($db,'SELECT DISTINCT catalog_hotel_id FROM anex_review_pair_exclusions WHERE catalog_hotel_id IS NOT NULL LIMIT 50001') as $r)$excluded[(int)$r['catalog_hotel_id']]=true;
        $rows=[];foreach($hotels as $h){$id=(int)$h['id'];$rows[]=tc30_row($h,$samo[$id]??[],isset($manual[$id]),isset($excluded[$id]));}
        w76_need($db->rollBack(),'target_catalog_rollback');return $rows;
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}

function tc30_main(array $args): void {
    w76_need(count($args)===2&&$args[1]==='--current-targets','target_catalog_disabled');
    $root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$head=(string)getenv('MATCH_SOURCE_SHA');
    w76_need(realpath($root)===$root&&basename($root)==='anytoour.ru'&&realpath($dir)===$dir&&basename($dir)===TC30_OP
        &&preg_match('/^[a-f0-9]{40}$/D',$head)===1,'target_catalog_execution_scope');
    $path=$dir.'/reservation.json';
    w76_need(is_file($path)&&!is_link($path)&&filesize($path)<=65536,'target_catalog_reservation_file');
    $reservation=json_decode(file_get_contents($path),true,32,JSON_THROW_ON_ERROR);
    foreach(['operation'=>TC30_OP,'source_sha'=>$head,'batch'=>TC30_BATCH,'maximum_writes'=>0,'provider_http_calls'=>0,'state'=>'reserved_before_db_read'] as $key=>$value)
        w76_need(($reservation[$key]??null)===$value,'target_catalog_reservation_binding');
    foreach(['execution-started.json','result.json','receipt.json'] as $file)w76_need(!file_exists($dir.'/'.$file)&&!is_link($dir.'/'.$file),'target_catalog_no_replay');
    w76_save($dir.'/execution-started.json',['operation'=>TC30_OP,'source_sha'=>$head,'no_replay'=>true]);
    require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
    $rows=tc30_current(v2_data_db());
    $result=['state'=>'completed_tv_live30_target_catalog','operation'=>TC30_OP,'source_sha'=>$head,'batch'=>TC30_BATCH,
        'captured_at_utc'=>gmdate('Y-m-d\TH:i:s\Z'),'row_count'=>count($rows),'rows'=>$rows,'provider_http_calls'=>0,
        'database_writes'=>0,'mapping_writes'=>0,'safe_to_write_now'=>false,'no_replay'=>true];
    w76_need(strlen(w76_json($result))<=8388608,'target_catalog_output_cap');
    $sha=w76_save($dir.'/result.json',$result);
    w76_save($dir.'/receipt.json',array_diff_key($result,['rows'=>true])+['result_sha256'=>$sha]);
    echo w76_json($result)."\n";
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__)tc30_main($argv);
