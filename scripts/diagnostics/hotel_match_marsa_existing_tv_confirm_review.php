<?php
declare(strict_types=1);
if (!defined('FC_LIBRARY_ONLY')) define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/hotel_match_anex_andromeda_mutual_graph_review.php';

const HMETV_OPERATION='hotel-match-marsa-existing-tv-confirm-review-1971-20260912-v1';
const HMETV_SEARCH_ID=13591467617;
const HMETV_COUNTRY_ID=1;
const HMETV_COORD_BLOCK_M=5000.0;

function hmetv_marsa(array $places):bool{
    foreach($places as$v){$n=fc_norm($v);$n=str_replace(['марса эль алам','marsa el alam'],['марса алам','marsa alam'],$n);if(in_array($n,['марса алам','marsa alam'],true))return true;}
    return false;
}
function hmetv_rows(array $data):array{
    if(array_is_list($data))return array_values(array_filter($data,'is_array'));
    foreach(['hotels','items','results','data']as$k)if(is_array($data[$k]??null)){ $r=hmetv_rows($data[$k]);if($r)return$r; }
    return[];
}
function hmetv_tv_hotels(array $payload):array{
    $out=[];foreach(hmetv_rows($payload)as$r){$id=filter_var($r['id']??null,FILTER_VALIDATE_INT);$name=trim((string)($r['name']??''));if($id===false||(int)$id<=0||$name==='')continue;$out[(int)$id]=['id'=>(int)$id,'name'=>$name];}return$out;
}
function hmetv_candidates(PDO $db):array{
    $an=hmamgr_anex_rows($db);$and=hmamgr_andromeda_rows($db);$ai=hmamgr_index($an);$di=hmamgr_index($and);$al=hmadcrh_locality_common($an);$dl=hmadcrh_locality_common($and);$out=[];
    foreach($an as$aid=>$a){if((int)$a['country_id']!==HMETV_COUNTRY_ID||!hmetv_marsa($a['places']))continue;$f=hmamgr_choose($a,$and,$di,$dl);if(($f['bucket']??'')!=='candidate')continue;$bid=(string)$f['target_id'];$b=$and[$bid]??null;if(!$b||!hmetv_marsa($b['places']))continue;$rev=hmamgr_choose($b,$an,$ai,$al);if(($rev['bucket']??'')!=='candidate'||(string)($rev['target_id']??'')!==(string)$aid)continue;$v=hmamgr_validation($a['local_ids'],$b['local_ids']);if(!in_array($v,['anex_only','andromeda_only','neither_side'],true))continue;$out[]=['anex_hotel_id'=>(int)$aid,'andromeda_external_id'=>$bid,'validation'=>$v,'route'=>(string)$f['reason'],'anex_names'=>$a['names'],'andromeda_names'=>$b['names'],'anex_places'=>$a['places'],'andromeda_places'=>$b['places'],'anex_latitude'=>$a['latitude'],'anex_longitude'=>$a['longitude'],'andromeda_latitude'=>$b['latitude'],'andromeda_longitude'=>$b['longitude'],'anex_live'=>(bool)$a['live'],'andromeda_live'=>(bool)$b['live'],'anex_observation_count'=>(int)$a['observation_count'],'andromeda_observation_count'=>(int)$b['observation_count'],'known_local_ids'=>array_values(array_unique(array_merge($a['local_ids'],$b['local_ids'])))];}
    return$out;
}
function hmetv_local(PDO $db,array $ids):array{
    $ids=array_values(array_unique(array_map('intval',$ids)));if(!$ids)return[];$marks=implode(',',array_fill(0,count($ids),'?'));
    $q=$db->prepare("SELECT h.id,h.name,h.region_name,h.subregion_name,COALESCE(d.latitude,h.latitude) latitude,COALESCE(d.longitude,h.longitude) longitude FROM catalog_hotels h LEFT JOIN catalog_hotel_details d ON d.hotel_id=h.id WHERE h.country_id=1 AND h.is_active=1 AND h.id IN ($marks)");$q->execute($ids);$out=[];foreach($q->fetchAll(PDO::FETCH_ASSOC)as$r){$id=(int)$r['id'];$out[$id]=['id'=>$id,'names'=>[(string)$r['name']],'places'=>[(string)$r['region_name'],(string)$r['subregion_name']],'latitude'=>fc_num($r['latitude']??null),'longitude'=>fc_num($r['longitude']??null)];}
    $q=$db->prepare("SELECT hotel_id,alias,normalized_alias FROM hotel_aliases WHERE hotel_id IN ($marks) ORDER BY hotel_id,id");$q->execute($ids);foreach($q->fetchAll(PDO::FETCH_ASSOC)as$r){$id=(int)$r['hotel_id'];if(!isset($out[$id]))continue;foreach(['alias','normalized_alias']as$k)if(trim((string)($r[$k]??''))!=='')$out[$id]['names'][]=(string)$r[$k];}
    foreach($out as&$r){$r['names']=hmadcr_expand_names(array_values(array_unique($r['names'])));$r['places']=array_values(array_unique(array_filter($r['places'],static fn($v)=>trim($v)!=='')));}unset($r);return$out;
}
function hmetv_pair_local_ok(array $p,array $local):array{
    if(!hmetv_marsa($local['places']))return['ok'=>false,'reason'=>'local_not_marsa'];
    $a=hmadcr_best_pair($p['anex_names'],$local['names'],$p['anex_places'],$local['places']);$b=hmadcr_best_pair($p['andromeda_names'],$local['names'],$p['andromeda_places'],$local['places']);
    foreach([$a,$b]as$x)if(!($x['critical_ok']??false)||!($x['exact_bag']??false)||(int)($x['shared']??0)<2)return['ok'=>false,'reason'=>'supplier_local_name_not_exact'];
    $da=fc_dist($p['anex_latitude'],$p['anex_longitude'],$local['latitude'],$local['longitude']);$db=fc_dist($p['andromeda_latitude'],$p['andromeda_longitude'],$local['latitude'],$local['longitude']);if(($da!==null&&$da>HMETV_COORD_BLOCK_M)||($db!==null&&$db>HMETV_COORD_BLOCK_M))return['ok'=>false,'reason'=>'coordinate_conflict','anex_distance_m'=>$da,'andromeda_distance_m'=>$db];
    return['ok'=>true,'anex_distance_m'=>$da===null?null:round($da,2),'andromeda_distance_m'=>$db===null?null:round($db,2),'anex_pair'=>$a,'andromeda_pair'=>$b];
}
function hmetv_review(PDO $db,string $operation=HMETV_OPERATION):array{
    if($operation!==HMETV_OPERATION)throw new RuntimeException('operation_scope');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
    try{$coverage=fc_coverage($db);$pairs=hmetv_candidates($db);$db->commit();}catch(Throwable$e){if($db->inTransaction())$db->rollBack();throw$e;}
    $base=['operation_id'=>$operation,'mode'=>'reuse_saturated_tourvisor_search_read_only','search_id'=>HMETV_SEARCH_ID,'search_criteria'=>['country'=>'Египет','date'=>'2026-10-17','nights'=>7,'operator'=>'ANEX'],'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'anex_calls'=>0,'andromeda_calls'=>0,'tv_search_slices'=>0,'tv_http_calls'=>0,'historical_operations_replayed'=>false,'coverage'=>$coverage,'current_marsa_supplier_pairs'=>count($pairs)];
    if(!function_exists('v2_data_tv_get'))return$base+['status'=>'blocked','reason'=>'tourvisor_client_unavailable'];
    try{$payload=v2_data_tv_get('/tours/search/'.HMETV_SEARCH_ID,['limit'=>10000]);$base['tv_http_calls']=1;}catch(Throwable$e){return$base+['status'=>'blocked','reason'=>'cached_tourvisor_search_unavailable','tv_http_calls'=>1,'message'=>mb_substr($e->getMessage(),0,180)];}
    $tv=hmetv_tv_hotels($payload);$ids=array_keys($tv);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');try{$locals=hmetv_local($db,$ids);$db->commit();}catch(Throwable$e){if($db->inTransaction())$db->rollBack();throw$e;}
    $raw=[];$blocked=[];foreach($pairs as$i=>$p){$matches=[];foreach($locals as$id=>$local){$x=hmetv_pair_local_ok($p,$local);if($x['ok'])$matches[(int)$id]=$x;}if(count($matches)===1){$id=(int)array_key_first($matches);$raw[]=['pair_index'=>$i,'local_hotel_id'=>$id,'tourvisor_name'=>$tv[$id]['name']??'','pair'=>$p,'proof'=>$matches[$id]];}elseif(count($matches)>1)$blocked[]=['pair_index'=>$i,'reason'=>'multiple_tourvisor_targets','target_ids'=>array_keys($matches),'pair'=>$p];}
    $byLocal=[];foreach($raw as$i=>$r)$byLocal[$r['local_hotel_id']][]=$i;$prepared=[];foreach($raw as$i=>$r){if(count($byLocal[$r['local_hotel_id']])!==1){$blocked[]=['pair_index'=>$r['pair_index'],'reason'=>'one_to_one_local_collision','local_hotel_id'=>$r['local_hotel_id'],'pair'=>$r['pair']];continue;}$prepared[]=$r;}
    return$base+['status'=>'completed','tv_http_calls'=>1,'tourvisor_cached_hotels'=>count($tv),'tourvisor_catalog_hotels'=>count($locals),'prepared'=>count($prepared),'prepared_rows'=>$prepared,'blocked'=>$blocked,'guards'=>['no_new_tourvisor_search'=>true,'no_continue'=>true,'mutual_unique_supplier_pair'=>true,'supplier_to_local_exact_bag_min_shared'=>2,'marsa_locality_required'=>true,'coordinate_conflict_block_m'=>HMETV_COORD_BLOCK_M,'one_to_one_local_required'=>true,'critical_qualifiers_preserved'=>['ANNEX','BEACH','GARDEN','NORTH','SOUTH'],'generic_hotel_resort_spa_non_identity'=>true,'stars_not_identity'=>true]];
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){fwrite(STDERR,"library_only: use guarded cached-TV workflow\n");exit(64);}
