<?php
declare(strict_types=1);
if (!defined('FC_LIBRARY_ONLY')) define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/hotel_full_catalog_reconcile.php';

const HMSB_CORE8 = [1=>true,2=>true,4=>true,8=>true,9=>true,10=>true,12=>true,16=>true];

function hmsb_keys($value): array {
    $raw=(string)$value;
    $variants=[$raw];
    if (preg_match('/\(\s*(?:ex|ех)\s*\.?\s+([^()]+)\)\s*$/iu',$raw,$m)) $variants[]=$m[1];
    $out=[];
    foreach($variants as $variant){
        $tokens=fc_tokens($variant,false);
        $tokens=array_values(array_filter($tokens,static fn($x)=>!in_array($x,['resort','resorts','spa','резорт','ресорт','спа'],true)));
        sort($tokens,SORT_STRING);
        $key=implode(' ',$tokens);
        if($key!=='')$out[$key]=true;
    }
    return array_keys($out);
}
function hmsb_names($values): array {
    $out=[];foreach($values as $v){$v=trim((string)$v);if($v!=='')$out[$v]=true;}return array_keys($out);
}
function hmsb_index_add(array &$index,int $country,int $local,array $names,string $external): void {
    foreach($names as $name)foreach(hmsb_keys($name) as $key)$index[$country][$key][$local][$external]=true;
}
function hmsb_targets(array $index,int $country,array $names): array {
    $out=[];foreach($names as $name)foreach(hmsb_keys($name) as $key)foreach(array_keys($index[$country][$key]??[]) as $local)$out[(int)$local]=true;
    $ids=array_map('intval',array_keys($out));sort($ids,SORT_NUMERIC);return $ids;
}
function hmsb_max_tokens(array $names): int {
    $max=0;foreach($names as $name)foreach(hmsb_keys($name) as $key)$max=max($max,count(array_filter(explode(' ',$key))));return $max;
}
function hmsb_coord(array $source): array {
    foreach([['latitude','longitude'],['lat','lng'],['lat','lon'],['hotelLatitude','hotelLongitude']] as $keys){
        if(!array_key_exists($keys[0],$source)||!array_key_exists($keys[1],$source))continue;
        $lat=fc_num($source[$keys[0]]);$lon=fc_num($source[$keys[1]]);
        if($lat!==null&&$lon!==null&&abs($lat)<=90&&abs($lon)<=180)return[$lat,$lon];
    }
    return[null,null];
}
function hmsb_direct_geo(array $source,array $target): array {
    [$lat,$lon]=hmsb_coord($source);$distance=fc_dist($lat,$lon,$target['latitude']??null,$target['longitude']??null);
    $place=fc_place(array_map('strval',$source['places']??[]),[(string)($target['region_name']??''),(string)($target['subregion_name']??'')]);
    return ['distance_m'=>$distance===null?null:(int)round($distance),'coordinate_conflict'=>$distance!==null&&$distance>5000,'place_match'=>$place,'direct_geo'=>(($distance!==null&&$distance<=1000)||$place)];
}
function hmsb_numeric_category(array $source): ?int {
    foreach(['category','star','stars','starName','star_name'] as $key){if(!array_key_exists($key,$source))continue;$v=trim((string)$source[$key]);if(preg_match('/^([1-5])(?:\s*(?:\*|★|stars?))?$/iu',$v,$m))return(int)$m[1];}
    return null;
}
function hmsb_candidate_status(int $tokens,array $geo): string {
    if($geo['coordinate_conflict'])return'hard_conflict';
    if($tokens>=2||$geo['direct_geo'])return'strict_supplier_bridge';
    return'needs_geo_or_independent_evidence';
}
function hmsb_collision_hold(array $rows): array {
    $groups=[];foreach($rows as $i=>$r){if(!in_array($r['status']??'',['strict_supplier_bridge','needs_geo_or_independent_evidence'],true))continue;$groups[$r['provider']][(int)$r['target_local_id']][]=$i;}
    foreach($groups as $provider=>$locals)foreach($locals as $local=>$ids){$externals=[];foreach($ids as $i)$externals[(string)$rows[$i]['external_id']]=true;if(count($externals)>1)foreach($ids as $i){$rows[$i]['status']='duplicate_provider_hold';$rows[$i]['duplicate_provider_external_ids']=array_keys($externals);sort($rows[$i]['duplicate_provider_external_ids'],SORT_STRING);}}
    return $rows;
}
function hmsb_target(array $hotel): array {return['local_hotel_id'=>(int)$hotel['id'],'name'=>$hotel['name'],'country_id'=>(int)$hotel['country_id'],'region'=>$hotel['region_name'],'subregion'=>$hotel['subregion_name'],'category'=>$hotel['category']===null?null:(int)$hotel['category']];}

function hmsb_review(PDO $db,string $operation): array {
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
    try{
        $hotels=[];$q=$db->query('SELECT h.id,h.country_id,h.name,h.region_name,h.subregion_name,h.category,COALESCE(d.latitude,h.latitude) latitude,COALESCE(d.longitude,h.longitude) longitude FROM catalog_hotels h LEFT JOIN catalog_hotel_details d ON d.hotel_id=h.id WHERE h.country_id IN (1,2,4,8,9,10,12,16) AND h.is_active=1 ORDER BY h.id');foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r)$hotels[(int)$r['id']]=$r;
        $anexAccepted=[];$anexLocal=[];
        $sql="SELECT m.anex_hotel_id external_id,m.catalog_hotel_id local_id FROM anex_hotel_search_mappings m LEFT JOIN anex_hotel_decisions d ON d.anex_hotel_id=m.anex_hotel_id WHERE m.enabled=1 AND m.scope='preview' AND m.approval_policy='".FC_POLICY."' AND d.anex_hotel_id IS NULL AND NOT EXISTS(SELECT 1 FROM anex_review_pair_exclusions x WHERE x.anex_hotel_id=m.anex_hotel_id AND x.catalog_hotel_id=m.catalog_hotel_id)";
        foreach($db->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $r){$local=(int)$r['local_id'];if(isset($hotels[$local])){$anexAccepted[(int)$r['external_id']]=$local;$anexLocal[$local]=true;}}
        foreach($db->query("SELECT anex_hotel_id external_id,catalog_hotel_id local_id FROM anex_hotel_decisions d WHERE decision_status='accepted' AND catalog_hotel_id IS NOT NULL AND NOT EXISTS(SELECT 1 FROM anex_review_pair_exclusions x WHERE x.anex_hotel_id=d.anex_hotel_id AND x.catalog_hotel_id=d.catalog_hotel_id)")->fetchAll(PDO::FETCH_ASSOC) as $r){$local=(int)$r['local_id'];if(isset($hotels[$local])){$anexAccepted[(int)$r['external_id']]=$local;$anexLocal[$local]=true;}}
        $andAccepted=[];$andLocal=[];$andAcceptedRows=[];
        foreach($db->query("SELECT * FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IS NOT NULL ORDER BY external_hotel_id")->fetchAll(PDO::FETCH_ASSOC) as $r){$local=(int)$r['local_hotel_id'];if(isset($hotels[$local])){$andAccepted[(string)$r['external_hotel_id']]=$local;$andLocal[$local]=true;$andAcceptedRows[]=$r;}}
        $anexOnly=array_diff_key($anexLocal,$andLocal);$andOnly=array_diff_key($andLocal,$anexLocal);

        $anexStage=[];foreach($db->query('SELECT * FROM anex_hotels ORDER BY anex_hotel_id')->fetchAll(PDO::FETCH_ASSOC) as $r)$anexStage[(int)$r['anex_hotel_id']]=$r;
        $anexObs=[];foreach($db->query('SELECT * FROM anex_search_hotel_observations ORDER BY search_count DESC,last_seen_utc DESC,anex_hotel_id')->fetchAll(PDO::FETCH_ASSOC) as $r){$id=(int)$r['anex_hotel_id'];if(!isset($anexObs[$id]))$anexObs[$id]=$r;}
        $andObs=[];foreach($db->query("SELECT * FROM andromeda_search_hotel_observations WHERE supplier_namespace='andromeda_catalog' ORDER BY observed_at_utc DESC,external_hotel_id")->fetchAll(PDO::FETCH_ASSOC) as $r){$id=(string)$r['external_hotel_id'];if(!isset($andObs[$id]))$andObs[$id]=$r;}
        $shaCountry=fc_sha_countries($db);

        $anexIndex=[];foreach($anexAccepted as $external=>$local){if(!isset($anexOnly[$local]))continue;$s=$anexStage[$external]??[];$o=$anexObs[$external]??[];$names=hmsb_names([$s['api_name']??'',$s['xml_name']??'',$s['xml_alternate_name']??'',$o['hotel_name']??'']);hmsb_index_add($anexIndex,(int)$hotels[$local]['country_id'],$local,$names,(string)$external);}
        $andIndex=[];foreach($andAcceptedRows as $r){$external=(string)$r['external_hotel_id'];$local=(int)$r['local_hotel_id'];if(!isset($andOnly[$local]))continue;$ev=fc_evidence($r['evidence_json']??'');$src=$ev['source']??[];if(!is_array($src))$src=[];$o=$andObs[$external]??[];$names=hmsb_names([$src['name']??'',$src['lName']??'',$o['hotel_name']??'']);hmsb_index_add($andIndex,(int)$hotels[$local]['country_id'],$local,$names,$external);}

        $rows=[];$stats=['anex_unresolved_examined'=>0,'andromeda_pending_examined'=>0,'anex_only_targets'=>count($anexOnly),'andromeda_only_targets'=>count($andOnly),'anex_name_bridge_hits'=>0,'andromeda_name_bridge_hits'=>0];
        $pending=$db->query("SELECT * FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='pending' AND local_hotel_id IS NULL ORDER BY external_hotel_id")->fetchAll(PDO::FETCH_ASSOC);
        foreach($pending as $r){$external=(string)$r['external_hotel_id'];$o=$andObs[$external]??null;$country=(int)($o['country_id']??0);if(!isset(HMSB_CORE8[$country]))$country=(int)($shaCountry[$r['catalog_sha256']]??0);if(!isset(HMSB_CORE8[$country]))continue;$stats['andromeda_pending_examined']++;$ev=fc_evidence($r['evidence_json']??'');$src=$ev['source']??[];if(!is_array($src))$src=[];$geo=$ev['geography']??[];if(!is_array($geo))$geo=[];$names=hmsb_names([$src['name']??'',$src['lName']??'',$o['hotel_name']??'']);$targets=hmsb_targets($anexIndex,$country,$names);if(count($targets)!==1)continue;$local=$targets[0];if(!isset($anexOnly[$local]))continue;$stats['andromeda_name_bridge_hits']++;$source=$src;if($o)foreach($o as $k=>$v)if(!array_key_exists($k,$source))$source[$k]=$v;$source['places']=hmsb_names([$src['town']??'',$geo['town']??'',$geo['parent']??'',$o['region_name']??'']);$g=hmsb_direct_geo($source,$hotels[$local]);$tokens=hmsb_max_tokens($names);$rows[]=['provider'=>'andromeda','external_id'=>$external,'country_id'=>$country,'target_local_id'=>$local,'target'=>hmsb_target($hotels[$local]),'source_names'=>$names,'source_places'=>$source['places'],'significant_tokens'=>$tokens,'geo'=>$g,'status'=>hmsb_candidate_status($tokens,$g),'source_category'=>hmsb_numeric_category($src),'target_category'=>$hotels[$local]['category']===null?null:(int)$hotels[$local]['category'],'bridge_provider'=>'anex','not_write_authority'=>true];}

        $manual=array_fill_keys(array_map('intval',$db->query('SELECT anex_hotel_id FROM anex_hotel_decisions')->fetchAll(PDO::FETCH_COLUMN)),true);$existing=array_fill_keys(array_map('intval',$db->query('SELECT anex_hotel_id FROM anex_hotel_search_mappings')->fetchAll(PDO::FETCH_COLUMN)),true);$excluded=[];foreach($db->query('SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions')->fetchAll(PDO::FETCH_ASSOC) as $x)$excluded[(int)$x['anex_hotel_id']][(int)$x['catalog_hotel_id']]=true;
        $allAnex=$anexStage;foreach($anexObs as $id=>$o)if(!isset($allAnex[$id]))$allAnex[$id]=[];
        foreach($allAnex as $id=>$s){$id=(int)$id;if(isset($manual[$id])||isset($existing[$id]))continue;$o=$anexObs[$id]??[];$country=(int)($o['country_id']??0);if(!isset(HMSB_CORE8[$country]))$country=fc_country($s['api_country']??'')??0;if(!isset(HMSB_CORE8[$country]))continue;$stats['anex_unresolved_examined']++;$names=hmsb_names([$o['hotel_name']??'',$s['api_name']??'',$s['xml_name']??'',$s['xml_alternate_name']??'']);$targets=hmsb_targets($andIndex,$country,$names);if(count($targets)!==1)continue;$local=$targets[0];if(!isset($andOnly[$local]))continue;$stats['anex_name_bridge_hits']++;$source=$s;$source['places']=hmsb_names([$s['api_region']??'',$s['api_town']??'']);$g=hmsb_direct_geo($source,$hotels[$local]);$tokens=hmsb_max_tokens($names);$status=isset($excluded[$id][$local])?'hard_conflict':hmsb_candidate_status($tokens,$g);$rows[]=['provider'=>'anex','external_id'=>(string)$id,'country_id'=>$country,'target_local_id'=>$local,'target'=>hmsb_target($hotels[$local]),'source_names'=>$names,'source_places'=>$source['places'],'significant_tokens'=>$tokens,'geo'=>$g,'status'=>$status,'bridge_provider'=>'andromeda','pair_excluded'=>isset($excluded[$id][$local]),'observed'=>(bool)$o,'search_count'=>(int)($o['search_count']??0),'not_write_authority'=>true];}

        $rows=hmsb_collision_hold($rows);$counts=[];$provider=[];foreach($rows as $r){$counts[$r['status']]=($counts[$r['status']]??0)+1;$provider[$r['provider']][$r['status']]=($provider[$r['provider']][$r['status']]??0)+1;}ksort($counts);ksort($provider);usort($rows,static fn($a,$b)=>strcmp($a['provider'],$b['provider'])?:((int)($b['search_count']??0)<=> (int)($a['search_count']??0))?:strcmp($a['external_id'],$b['external_id']));
        $db->commit();return['schema'=>'hotel-match-current-supplier-bridge/1','status'=>'completed','operation_id'=>$operation,'mode'=>'current_db_read_only','database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'stats'=>$stats,'status_counts'=>$counts,'provider_status_counts'=>$provider,'rows'=>$rows,'guards'=>['missing_third_only'=>true,'supplier_identity_names_not_local_names'=>true,'hotel_resort_spa_ignored'=>true,'former_name_aliases'=>true,'meaningful_qualifiers_preserved'=>true,'coordinate_conflict_gt_5km_blocks'=>true,'duplicate_provider_local_groups_hold'=>true,'manual_and_pair_exclusions_preserved'=>true,'star_difference_signal_not_identity'=>true,'no_mapping_authority'=>true]];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
