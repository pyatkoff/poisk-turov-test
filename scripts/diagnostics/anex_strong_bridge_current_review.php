<?php
declare(strict_types=1);
if (!defined('FC_LIBRARY_ONLY')) define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/hotel_match_live_priority_review.php';

const ASBR_OPERATION='hotel-match-anex-strong-bridge-current-review-1971-20260911-v1';
const ASBR_CORE8=[1=>true,2=>true,4=>true,8=>true,9=>true,10=>true,12=>true,16=>true];

function asbr_pair(string $source,string $target,string $region,bool $andromedaBridge): array {
    $drop=array_values(array_unique(array_merge(pcbr_region_tokens($region),['hotel','resort','spa','ex','the','adult','adults','only','16'])));
    $s=array_values(array_unique(pcbr_without_tokens(pcbr_identity_tokens($source),$drop)));$t=array_values(array_unique(pcbr_without_tokens(pcbr_identity_tokens($target),$drop)));
    $shared=array_values(array_unique(array_intersect($s,$t)));$so=array_values(array_filter($s,static fn($v)=>!in_array($v,$t,true)));$to=array_values(array_filter($t,static fn($v)=>!in_array($v,$s,true)));
    $den=count($s)+count($t);$score=$den?2.0*count($shared)/$den:0.0;$critical=mlp_critical_signature($source)===mlp_critical_signature($target);$anchor=isset($s[0],$t[0])&&$s[0]===$t[0];$diff=count($so)+count($to);$min=min(count($s),count($t));
    $safe=$critical&&$anchor&&$diff<=2&&(($andromedaBridge&&$min>=2&&count($shared)>=2&&$score>=0.78)||(!$andromedaBridge&&$min>=3&&count($shared)>=3&&$score>=0.84));
    return ['score'=>round($score,6),'shared_unique'=>count($shared),'source_tokens'=>$s,'target_tokens'=>$t,'source_only'=>$so,'target_only'=>$to,'token_diff'=>$diff,'critical_ok'=>$critical,'anchor_ok'=>$anchor,'existing_andromeda_link'=>$andromedaBridge,'safe_strong_bridge'=>$safe];
}
function asbr_best(array $sourceNames,array $targetNames,string $region,bool $bridge):array{
    $best=['score'=>0.0,'shared_unique'=>0,'safe_strong_bridge'=>false,'source'=>'','target'=>''];
    foreach($sourceNames as $s){$s=trim((string)$s);if($s==='')continue;foreach($targetNames as $t){$t=trim((string)$t);if($t==='')continue;$p=asbr_pair($s,$t,$region,$bridge);if((int)$p['safe_strong_bridge']>(int)($best['safe_strong_bridge']??false)||((bool)$p['safe_strong_bridge']===(bool)($best['safe_strong_bridge']??false)&&($p['score']>$best['score']||($p['score']===$best['score']&&$p['shared_unique']>$best['shared_unique']))))$best=$p+['source'=>$s,'target'=>$t];}}
    return $best;
}
function asbr_source(array $obs,array $stage,bool $observed):array{
    return ['observed'=>$observed,'search_count'=>(int)($obs['search_count']??0),'last_seen_utc'=>$obs['last_seen_utc']??null,'names'=>array_values(array_filter([$obs['hotel_name']??'',$stage['api_name']??'',$stage['xml_name']??'',$stage['xml_alternate_name']??''],static fn($x)=>trim((string)$x)!=='')),'places'=>array_values(array_filter([$stage['api_region']??'',$stage['api_town']??''],static fn($x)=>trim((string)$x)!=='')),'latitude'=>$stage['latitude']??null,'longitude'=>$stage['longitude']??null];
}
function asbr_review(PDO $db,string $operation=ASBR_OPERATION):array{
    if($operation!==ASBR_OPERATION)throw new RuntimeException('operation_scope');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    [$hotels,$names,$strict,$broad,$places,$scope]=mbr_catalog($db);[$anexLocal,$andromedaLocal]=mbr_local_sets($db);
    $manual=array_fill_keys(array_map('intval',$db->query('SELECT anex_hotel_id FROM anex_hotel_decisions')->fetchAll(PDO::FETCH_COLUMN)),true);$existing=array_fill_keys(array_map('intval',$db->query('SELECT anex_hotel_id FROM anex_hotel_search_mappings')->fetchAll(PDO::FETCH_COLUMN)),true);
    $excluded=[];foreach($db->query('SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions')->fetchAll(PDO::FETCH_ASSOC) as $x)$excluded[(int)$x['anex_hotel_id']][(int)$x['catalog_hotel_id']]=true;
    $staging=[];foreach($db->query('SELECT * FROM anex_hotels ORDER BY anex_hotel_id')->fetchAll(PDO::FETCH_ASSOC) as $s)$staging[(int)$s['anex_hotel_id']]=$s;
    $obs=$db->query('SELECT * FROM anex_search_hotel_observations ORDER BY search_count DESC,last_seen_utc DESC,anex_hotel_id')->fetchAll(PDO::FETCH_ASSOC);
    $stats=['observed_examined'=>0,'staging_examined'=>0,'protected'=>0,'direct_place_rows'=>0,'safe'=>0,'safe_observed'=>0,'safe_andromeda_bridge'=>0,'safe_tv_only'=>0,'coordinate_conflict'=>0,'pair_exclusion_block'=>0,'margin_block'=>0];$safe=[];$seen=[];
    $process=function(int $id,int $country,array $source)use(&$stats,&$safe,$hotels,$names,$places,$andromedaLocal,$excluded){
        $placeIds=mbr_place_pool($places,$country,$source['places']);if(!$placeIds)return;$stats['direct_place_rows']++;$rank=[];
        foreach($placeIds as $hid){$hid=(int)$hid;if(!isset($hotels[$hid]))continue;if(isset($excluded[$id][$hid])){$stats['pair_exclusion_block']++;continue;}$hotel=$hotels[$hid];$bridge=isset($andromedaLocal[$hid]);$p=asbr_best($source['names'],$names[$hid]??[$hotel['name']],(string)$hotel['region_name'],$bridge);if(!($p['critical_ok']??false)||!($p['anchor_ok']??false))continue;$guard=mbr_target_guard($source,$hotel);if($guard['coordinate_conflict']){$stats['coordinate_conflict']++;continue;}$rank[]=['id'=>$hid,'pair'=>$p,'bridge'=>$bridge,'guard'=>$guard];}
        if(!$rank)return;usort($rank,static fn($a,$b)=>(int)$b['pair']['safe_strong_bridge']<=>(int)$a['pair']['safe_strong_bridge'] ?: $b['pair']['score']<=>$a['pair']['score'] ?: $b['pair']['shared_unique']<=>$a['pair']['shared_unique'] ?: $a['id']<=>$b['id']);$best=$rank[0];$second=$rank[1]??null;$margin=$second?($best['pair']['score']-$second['pair']['score']):1.0;if(!($best['pair']['safe_strong_bridge']??false))return;if($margin<0.12){$stats['margin_block']++;return;}
        $target=$hotels[$best['id']];$item=['provider'=>'anex','external_id'=>$id,'country_id'=>$country,'observed'=>(bool)$source['observed'],'search_count'=>(int)$source['search_count'],'last_seen_utc'=>$source['last_seen_utc'],'source_names'=>$source['names'],'source_places'=>$source['places'],'target_local_hotel_id'=>$best['id'],'target_name'=>$target['name'],'target_region'=>$target['region_name'],'target_subregion'=>$target['subregion_name'],'pair'=>$best['pair'],'score_margin'=>round($margin,6),'distance_m'=>$best['guard']['distance_m']??null,'existing_andromeda_link'=>$best['bridge'],'rule'=>$best['bridge']?'strong_fuzzy_direct_geo_andromeda_bridge':'strong_fuzzy_direct_geo_tourvisor'];$safe[]=$item;$stats['safe']++;if($source['observed'])$stats['safe_observed']++;if($best['bridge'])$stats['safe_andromeda_bridge']++;else$stats['safe_tv_only']++;
    };
    foreach($obs as $o){$id=(int)$o['anex_hotel_id'];$country=(int)$o['country_id'];if(!isset(ASBR_CORE8[$country])||isset($seen[$id]))continue;$seen[$id]=true;$stats['observed_examined']++;if(isset($manual[$id])||isset($existing[$id])){$stats['protected']++;continue;}$process($id,$country,asbr_source($o,$staging[$id]??[],true));}
    foreach($staging as $id=>$s){if(isset($seen[$id])||isset($manual[$id])||isset($existing[$id]))continue;$country=fc_country($s['api_country']??'');if(!$country||!isset(ASBR_CORE8[$country]))continue;$stats['staging_examined']++;$process((int)$id,$country,asbr_source([],$s,false));}
    usort($safe,static fn($a,$b)=>(int)$b['observed']<=>(int)$a['observed'] ?: (int)$b['existing_andromeda_link']<=>(int)$a['existing_andromeda_link'] ?: $b['search_count']<=>$a['search_count'] ?: $b['pair']['score']<=>$a['pair']['score'] ?: $a['external_id']<=>$b['external_id']);
    return ['status'=>'completed','operation_id'=>$operation,'database_writes'=>0,'supplier_calls'=>0,'historical_operations_replayed'=>false,'coverage'=>fc_coverage($db),'catalog_scope'=>$scope,'stats'=>$stats,'safe_prepared_count'=>count($safe),'safe_prepared'=>$safe];
}
