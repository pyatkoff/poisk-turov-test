<?php
declare(strict_types=1);
require_once __DIR__.'/hotel_match_pending8_transition_v76.php';

const TP30_OP='int-andromeda-match-live30-target-preflight-20261001-v1';
const TP30_BATCH='tv-live30-target-preflight-20261001';
const TP30_TABLES=[
    'catalog_hotels'=>['id','name','country_id','country_name','region_name','subregion_name','category','is_active','latitude','longitude'],
    'tour_operator_identity_observations'=>['hotel_id','last_seen_at'],
    'andromeda_hotel_identities'=>['supplier_namespace','external_hotel_id','local_hotel_id','decision_status'],
    'anex_hotel_decisions'=>['catalog_hotel_id'],
    'anex_review_pair_exclusions'=>['catalog_hotel_id'],
];

function tp30_scalar(PDO $db,string $sql): array {
    try {
        $value=$db->query($sql)->fetchColumn();
        return ['ok'=>true,'value'=>max(0,(int)$value)];
    } catch (Throwable) {
        return ['ok'=>false,'value'=>null];
    }
}

/** Aggregate-only preflight. It never calls the consumed catalog projector. */
function tp30_current(PDO $db): array {
    w76_need(!$db->inTransaction(),'target_preflight_transaction');
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $db->exec('START TRANSACTION READ ONLY');
    try {
        $schema=[];$missing=[];
        $rows=w76_q($db,"SELECT TABLE_NAME,COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('catalog_hotels','tour_operator_identity_observations','andromeda_hotel_identities','anex_hotel_decisions','anex_review_pair_exclusions') ORDER BY TABLE_NAME,COLUMN_NAME");
        foreach($rows as $row)$schema[(string)$row['TABLE_NAME']][(string)$row['COLUMN_NAME']]=true;
        foreach(TP30_TABLES as $table=>$columns){$missing[$table]=[];foreach($columns as $column)if(!isset($schema[$table][$column]))$missing[$table][]=$column;}
        $schemaOk=!array_filter($missing);
        $queries=[];
        $queries['cohort']=$schemaOk?tp30_scalar($db,"SELECT COUNT(DISTINCT h.id) FROM catalog_hotels h JOIN tour_operator_identity_observations o ON o.hotel_id=h.id WHERE h.is_active=1 AND o.last_seen_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 30 DAY) AND h.country_name NOT IN ('Россия','Абхазия','Russia','Abkhazia','Russian Federation')"):['ok'=>false,'value'=>null];
        $queries['invalid_coordinates']=$schemaOk?tp30_scalar($db,"SELECT COUNT(*) FROM (SELECT DISTINCT h.id,h.latitude,h.longitude FROM catalog_hotels h JOIN tour_operator_identity_observations o ON o.hotel_id=h.id WHERE h.is_active=1 AND o.last_seen_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 30 DAY) AND h.country_name NOT IN ('Россия','Абхазия','Russia','Abkhazia','Russian Federation')) q WHERE (q.latitude IS NOT NULL AND (q.latitude<-90 OR q.latitude>90)) OR (q.longitude IS NOT NULL AND (q.longitude<-180 OR q.longitude>180))"):['ok'=>false,'value'=>null];
        $queries['invalid_text']=$schemaOk?tp30_scalar($db,"SELECT COUNT(*) FROM (SELECT DISTINCT h.id,h.name,h.country_id,h.country_name,h.region_name,h.subregion_name,h.category FROM catalog_hotels h JOIN tour_operator_identity_observations o ON o.hotel_id=h.id WHERE h.is_active=1 AND o.last_seen_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 30 DAY) AND h.country_name NOT IN ('Россия','Абхазия','Russia','Abkhazia','Russian Federation')) q WHERE q.id<=0 OR q.name IS NULL OR q.name='' OR CHAR_LENGTH(q.name)>512 OR LOCATE('http://',LOWER(q.name))>0 OR LOCATE('https://',LOWER(q.name))>0 OR CHAR_LENGTH(COALESCE(q.country_name,''))>512 OR CHAR_LENGTH(COALESCE(q.region_name,''))>512 OR CHAR_LENGTH(COALESCE(q.subregion_name,''))>512"):['ok'=>false,'value'=>null];
        $queries['invalid_accepted_native']=$schemaOk?tp30_scalar($db,"SELECT COUNT(*) FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IS NOT NULL AND external_hotel_id NOT REGEXP '^[1-9][0-9]{0,31}$'"):['ok'=>false,'value'=>null];
        $queries['max_accepted_aliases']=$schemaOk?tp30_scalar($db,"SELECT COALESCE(MAX(x.c),0) FROM (SELECT local_hotel_id,COUNT(DISTINCT external_hotel_id) c FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IS NOT NULL GROUP BY local_hotel_id) x"):['ok'=>false,'value'=>null];
        $queries['manual_targets']=$schemaOk?tp30_scalar($db,'SELECT COUNT(DISTINCT catalog_hotel_id) FROM anex_hotel_decisions WHERE catalog_hotel_id IS NOT NULL'):['ok'=>false,'value'=>null];
        $queries['excluded_targets']=$schemaOk?tp30_scalar($db,'SELECT COUNT(DISTINCT catalog_hotel_id) FROM anex_review_pair_exclusions WHERE catalog_hotel_id IS NOT NULL'):['ok'=>false,'value'=>null];
        w76_need($db->rollBack(),'target_preflight_rollback');
        $status=[];$metrics=[];foreach($queries as $name=>$result){$status[$name]=$result['ok'];$metrics[$name]=$result['value'];}
        return ['schema_complete'=>$schemaOk,'missing_columns'=>$missing,'query_status'=>$status,'metrics'=>$metrics];
    } catch (Throwable $e) {
        if($db->inTransaction())$db->rollBack();
        throw $e;
    }
}

function tp30_main(array $args): void {
    w76_need(count($args)===2&&$args[1]==='--current-target-preflight','target_preflight_disabled');
    $root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$head=(string)getenv('MATCH_SOURCE_SHA');
    w76_need(realpath($root)===$root&&basename($root)==='anytoour.ru'&&realpath($dir)===$dir&&basename($dir)===TP30_OP
        &&preg_match('/^[a-f0-9]{40}$/D',$head)===1,'target_preflight_execution_scope');
    $path=$dir.'/reservation.json';
    w76_need(is_file($path)&&!is_link($path)&&filesize($path)<=65536,'target_preflight_reservation_file');
    $reservation=json_decode((string)file_get_contents($path),true,32,JSON_THROW_ON_ERROR);
    foreach(['operation'=>TP30_OP,'source_sha'=>$head,'batch'=>TP30_BATCH,'maximum_writes'=>0,'provider_http_calls'=>0,'state'=>'reserved_before_db_read'] as $key=>$value)
        w76_need(($reservation[$key]??null)===$value,'target_preflight_reservation_binding');
    foreach(['execution-started.json','result.json','receipt.json'] as $file)w76_need(!file_exists($dir.'/'.$file)&&!is_link($dir.'/'.$file),'target_preflight_no_replay');
    w76_save($dir.'/execution-started.json',['operation'=>TP30_OP,'source_sha'=>$head,'no_replay'=>true]);
    require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
    $preflight=tp30_current(v2_data_db());
    $result=['state'=>'completed_tv_live30_target_preflight','operation'=>TP30_OP,'source_sha'=>$head,'batch'=>TP30_BATCH,
        'captured_at_utc'=>gmdate('Y-m-d\\TH:i:s\\Z')]+$preflight+['provider_http_calls'=>0,'database_reads'=>1,
        'database_writes'=>0,'mapping_writes'=>0,'safe_to_write_now'=>false,'no_replay'=>true];
    $sha=w76_save($dir.'/result.json',$result);
    w76_save($dir.'/receipt.json',array_diff_key($result,['missing_columns'=>true,'query_status'=>true,'metrics'=>true])+['result_sha256'=>$sha]);
    echo w76_json($result)."\n";
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__)tp30_main($argv);
