<?php
declare(strict_types=1);
require_once __DIR__.'/hotel_match_tv_live30_target_catalog_v1.php';

const TC30V2_OP='int-andromeda-match-live30-target-catalog-v2-20261001-v1';
const TC30V2_BATCH='tv-live30-targets-v2-20261001';

function tc30v2_reasons(array $hotel,array $occupants): array {
    $reasons=[];
    foreach(['latitude'=>90,'longitude'=>180] as $key=>$bound){
        $v=$hotel[$key]??null;
        if($v!==null&&(!is_numeric($v)||!is_finite((float)$v)||abs((float)$v)>$bound))$reasons['invalid_coordinates']=true;
    }
    foreach(['name','country_id','country_name','region_name','subregion_name','category'] as $key){
        $v=$hotel[$key]??null;
        if($v!==null&&(!is_scalar($v)||strlen((string)$v)>512||preg_match('/[\x00-\x1f\x7f]|https?:\/\//iu',(string)$v)!==0))$reasons['invalid_text']=true;
    }
    if(($hotel['name']??null)===null||(string)$hotel['name']==='')$reasons['invalid_text']=true;
    if(count($occupants)>32)$reasons['accepted_alias_cap']=true;
    foreach($occupants as $v)if(!is_string($v)||preg_match('/^[1-9][0-9]{0,31}$/D',$v)!==1)$reasons['invalid_accepted_native']=true;
    return array_keys($reasons);
}

/** Complete CURRENT cohort: strict valid projection plus isolated ID-only holds. */
function tc30v2_current(PDO $db): array {
    w76_need(!$db->inTransaction(),'target_catalog_v2_transaction');
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $db->exec('START TRANSACTION READ ONLY');
    try{
        $hotels=w76_q($db,"SELECT DISTINCT h.id,h.name,h.country_id,h.country_name,h.region_name,h.subregion_name,h.category,h.is_active,h.latitude,h.longitude FROM catalog_hotels h JOIN tour_operator_identity_observations o ON o.hotel_id=h.id WHERE h.is_active=1 AND o.last_seen_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 30 DAY) AND h.country_name NOT IN ('Россия','Абхазия','Russia','Abkhazia','Russian Federation') ORDER BY h.id LIMIT 20001");
        w76_need(count($hotels)<=TC30_CAP,'target_catalog_v2_cap');$wanted=[];$samo=[];$manual=[];$excluded=[];
        foreach($hotels as $h){$id=(int)$h['id'];w76_need($id>0&&(string)$id===(string)$h['id'],'target_catalog_v2_id');$wanted[$id]=true;}
        foreach(w76_q($db,"SELECT local_hotel_id,external_hotel_id FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IS NOT NULL LIMIT 50001") as $r){
            $id=(int)$r['local_hotel_id'];if(isset($wanted[$id]))$samo[$id][]=(string)$r['external_hotel_id'];
        }
        foreach(w76_q($db,'SELECT DISTINCT catalog_hotel_id FROM anex_hotel_decisions WHERE catalog_hotel_id IS NOT NULL LIMIT 50001') as $r)$manual[(int)$r['catalog_hotel_id']]=true;
        foreach(w76_q($db,'SELECT DISTINCT catalog_hotel_id FROM anex_review_pair_exclusions WHERE catalog_hotel_id IS NOT NULL LIMIT 50001') as $r)$excluded[(int)$r['catalog_hotel_id']]=true;
        $rows=[];$held=[];
        foreach($hotels as $h){
            $id=(int)$h['id'];$occupants=$samo[$id]??[];$reasons=tc30v2_reasons($h,$occupants);
            if($reasons){sort($reasons,SORT_STRING);$held[]=['id'=>$id,'reasons'=>$reasons];continue;}
            $rows[]=tc30_row($h,$occupants,isset($manual[$id]),isset($excluded[$id]));
        }
        w76_need($db->rollBack(),'target_catalog_v2_rollback');
        return ['rows'=>$rows,'held'=>$held,'cohort_count'=>count($hotels)];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}

function tc30v2_main(array $args): void {
    w76_need(count($args)===2&&$args[1]==='--current-targets-v2','target_catalog_v2_disabled');
    $root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$head=(string)getenv('MATCH_SOURCE_SHA');
    w76_need(realpath($root)===$root&&basename($root)==='anytoour.ru'&&realpath($dir)===$dir&&basename($dir)===TC30V2_OP
        &&preg_match('/^[a-f0-9]{40}$/D',$head)===1,'target_catalog_v2_execution_scope');
    $path=$dir.'/reservation.json';
    w76_need(is_file($path)&&!is_link($path)&&filesize($path)<=65536,'target_catalog_v2_reservation_file');
    $reservation=json_decode((string)file_get_contents($path),true,32,JSON_THROW_ON_ERROR);
    foreach(['operation'=>TC30V2_OP,'source_sha'=>$head,'batch'=>TC30V2_BATCH,'maximum_writes'=>0,'provider_http_calls'=>0,'state'=>'reserved_before_db_read'] as $key=>$value)
        w76_need(($reservation[$key]??null)===$value,'target_catalog_v2_reservation_binding');
    foreach(['execution-started.json','result.json','receipt.json'] as $file)w76_need(!file_exists($dir.'/'.$file)&&!is_link($dir.'/'.$file),'target_catalog_v2_no_replay');
    w76_save($dir.'/execution-started.json',['operation'=>TC30V2_OP,'source_sha'=>$head,'no_replay'=>true]);
    require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
    $catalog=tc30v2_current(v2_data_db());
    $result=['state'=>'completed_tv_live30_target_catalog_v2','operation'=>TC30V2_OP,'source_sha'=>$head,'batch'=>TC30V2_BATCH,
        'captured_at_utc'=>gmdate('Y-m-d\TH:i:s\Z'),'cohort_count'=>$catalog['cohort_count'],'row_count'=>count($catalog['rows']),
        'held_count'=>count($catalog['held']),'rows'=>$catalog['rows'],'held'=>$catalog['held'],'provider_http_calls'=>0,
        'database_reads'=>1,'database_writes'=>0,'mapping_writes'=>0,'safe_to_write_now'=>false,'no_replay'=>true];
    w76_need($result['row_count']+$result['held_count']===$result['cohort_count'],'target_catalog_v2_partition');
    w76_need(strlen(w76_json($result))<=8388608,'target_catalog_v2_output_cap');
    $sha=w76_save($dir.'/result.json',$result);
    w76_save($dir.'/receipt.json',array_diff_key($result,['rows'=>true,'held'=>true])+['result_sha256'=>$sha]);
    echo w76_json($result)."\n";
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__)tc30v2_main($argv);
