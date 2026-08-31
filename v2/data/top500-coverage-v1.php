<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/db-v1.php';
require_once __DIR__ . '/top-hotels-v1.php';

$pdo=v2_data_db();
$ids=array_values(array_unique(array_map('intval',v2_priority_hotel_ids())));
$set=array_fill_keys($ids,true);
$csv=implode(',',$ids);
$catalogRows=$pdo->query("SELECT id,is_active,country_id,country_name,name FROM catalog_hotels WHERE id IN (".$csv.") ORDER BY country_name,name")->fetchAll(PDO::FETCH_ASSOC)?:[];
$present=[];$active=[];$countries=[];
foreach($catalogRows as $r){
    $id=(int)$r['id'];$present[$id]=true;if((int)$r['is_active']===1)$active[$id]=true;
    $country=(string)($r['country_name']?:$r['country_id']);if(!isset($countries[$country]))$countries[$country]=['present'=>0,'active'=>0];$countries[$country]['present']++;if((int)$r['is_active']===1)$countries[$country]['active']++;
}
$searched=[];$attempts=$pdo->query("SELECT hotel_ids_json FROM tour_matrix_collection_attempts WHERE criterion='hotel_batch' AND status IN ('success','empty') AND hotel_ids_json IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN)?:[];
foreach($attempts as $json){$batch=json_decode((string)$json,true);if(!is_array($batch))continue;foreach($batch as $id){$id=(int)$id;if(isset($set[$id]))$searched[$id]=true;}}
$observed=[];$rows=$pdo->query("SELECT DISTINCT hotel_id FROM tour_price_observations WHERE source='scheduled_monitor' AND hotel_id IN (".$csv.")")->fetchAll(PDO::FETCH_COLUMN)?:[];foreach($rows as $id)$observed[(int)$id]=true;
$missing=array_values(array_diff($ids,array_keys($present)));$unsearched=array_values(array_diff($ids,array_keys($searched)));$unobserved=array_values(array_diff($ids,array_keys($observed)));
echo 'TOP500_COVERAGE total='.count($ids).' catalog_present='.count($present).' active='.count($active).' searched='.count($searched).' observed='.count($observed).' missing='.count($missing).' unsearched='.count($unsearched).' unobserved='.count($unobserved)."\n";
ksort($countries);foreach($countries as $name=>$c)echo 'TOP500_COUNTRY country='.str_replace(' ','_',trim($name)).' present='.$c['present'].' active='.$c['active']."\n";
echo 'TOP500_MISSING_IDS '.($missing?implode(',',$missing):'-')."\n";
echo 'TOP500_UNSEARCHED_IDS '.($unsearched?implode(',',$unsearched):'-')."\n";
