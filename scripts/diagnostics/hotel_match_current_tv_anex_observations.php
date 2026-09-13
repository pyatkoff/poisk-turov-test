<?php
declare(strict_types=1);
if (!defined('FC_LIBRARY_ONLY')) define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/hotel_full_catalog_reconcile.php';

const HMTO_CORE8 = [1=>true,2=>true,4=>true,8=>true,9=>true,10=>true,12=>true,16=>true];
const HMTO_ANEX_NAMES = ['anex'=>true,'anex tour'=>true,'anextour'=>true,'анекс'=>true,'анекс тур'=>true];
const HMTO_QUALIFIERS = ['annex'=>true,'beach'=>true,'garden'=>true,'gardens'=>true,'north'=>true,'south'=>true,'adult'=>true,'adults'=>true,'family'=>true,'club'=>true,'aqua'=>true,'aquamarine'=>true,'posh'=>true,'grand'=>true,'select'=>true];

function hmto_names(array $values): array {
    $out=[]; foreach($values as $value){$v=trim((string)$value);if($v!=='')$out[$v]=true;} return array_keys($out);
}
function hmto_tokens(string $value): array {
    $tokens=fc_tokens($value,false);
    $tokens=array_values(array_filter($tokens,static fn($x)=>!in_array($x,['resort','resorts','spa','резорт','ресорт','спа'],true)));
    return array_values(array_unique($tokens));
}
function hmto_key(string $value): string { return implode(' ',hmto_tokens($value)); }
function hmto_qualifiers(array $names): array {
    $out=[];foreach($names as $name)foreach(hmto_tokens((string)$name) as $t)if(isset(HMTO_QUALIFIERS[$t]))$out[$t]=true;
    $keys=array_keys($out);sort($keys,SORT_STRING);return $keys;
}
function hmto_operator_is_anex($name,$host): bool {
    $n=fc_norm((string)$name);$h=strtolower(trim((string)$host));
    if(isset(HMTO_ANEX_NAMES[$n]))return true;
    return $h==='anextour.ru'||$h==='www.anextour.ru'||$h==='agent.anextour.ru'||str_ends_with($h,'.anextour.ru');
}
function hmto_direct_key(array $row): array {
    $link=trim((string)($row['operator_link']??''));$host=strtolower(trim((string)($row['operator_link_host']??'')));
    $query=trim((string)($row['operator_link_query']??''));$path=trim((string)($row['operator_link_path']??''));
    if($link!==''){
        $u=parse_url($link);if(!is_array($u)||strtolower((string)($u['scheme']??''))!=='https'||isset($u['user'])||isset($u['pass']))return ['status'=>'invalid_link'];
        $lh=strtolower((string)($u['host']??''));if($lh==='')return ['status'=>'invalid_link'];
        if($host!==''&&$host!==$lh)return ['status'=>'link_metadata_conflict'];$host=$lh;
        $lq=(string)($u['query']??'');if($query!==''&&$lq!==''&&$query!==$lq)return ['status'=>'link_metadata_conflict'];if($query==='')$query=$lq;
        if($path===''&&isset($u['path']))$path=(string)$u['path'];
    }
    if(!hmto_operator_is_anex($row['operator_name']??null,$host))return ['status'=>'not_anex'];
    if($query==='')return ['status'=>'missing_direct_key','host'=>$host,'path'=>$path];
    $values=[];foreach(explode('&',$query) as $part){if($part==='')continue;[$rk,$rv]=array_pad(explode('=',$part,2),2,'');$k=strtolower(urldecode($rk));if(!in_array($k,['hotellist','hotelcode','hotel_code'],true))continue;$decoded=trim(urldecode($rv));if($decoded==='')continue;foreach(preg_split('/\s*,\s*/',$decoded)?:[] as $piece){if(preg_match('/^[1-9][0-9]{0,11}$/D',$piece))$values[(int)$piece][$k]=true;else return ['status'=>'invalid_direct_key','host'=>$host,'path'=>$path];}}
    if(!$values)return ['status'=>'missing_direct_key','host'=>$host,'path'=>$path];
    if(count($values)!==1)return ['status'=>'ambiguous_direct_key','hotel_ids'=>array_keys($values),'host'=>$host,'path'=>$path];
    $id=(int)array_key_first($values);$params=array_keys($values[$id]);sort($params,SORT_STRING);
    return ['status'=>'direct_key','anex_hotel_id'=>$id,'params'=>$params,'host'=>$host,'path'=>$path];
}
function hmto_coord(array $source): array {
    foreach([['latitude','longitude'],['lat','lng'],['lat','lon'],['api_latitude','api_longitude']] as [$a,$b]){if(!array_key_exists($a,$source)||!array_key_exists($b,$source))continue;$lat=fc_num($source[$a]);$lon=fc_num($source[$b]);if($lat!==null&&$lon!==null&&abs($lat)<=90&&abs($lon)<=180)return[$lat,$lon];}
    return[null,null];
}
function hmto_name_guard(array $sourceNames,array $targetNames): array {
    $sourceTokens=[];$targetTokens=[];$exact=false;
    $targetKeys=[];foreach($targetNames as $name){$key=hmto_key((string)$name);if($key!=='')$targetKeys[$key]=true;foreach(hmto_tokens((string)$name) as $t)$targetTokens[$t]=true;}
    foreach($sourceNames as $name){$key=hmto_key((string)$name);if($key!==''&&isset($targetKeys[$key]))$exact=true;foreach(hmto_tokens((string)$name) as $t)$sourceTokens[$t]=true;}
    $common=count(array_intersect_key($sourceTokens,$targetTokens));$sq=hmto_qualifiers($sourceNames);$tq=hmto_qualifiers($targetNames);
    $qualifierConflict=false;if($sq||$tq){$qualifierConflict=$sq!==$tq;}
    return ['source_tokens'=>count($sourceTokens),'target_tokens'=>count($targetTokens),'common_tokens'=>$common,'exact_name'=>$exact,'source_qualifiers'=>$sq,'target_qualifiers'=>$tq,'qualifier_conflict'=>$qualifierConflict];
}
function hmto_classify(array $state): string {
    if(($state['pair_excluded']??false))return 'pair_excluded';
    if(($state['manual_status']??null)!==null){if(($state['manual_status']??'')==='accepted')return ((int)($state['manual_local']??0)===(int)($state['target_local']??-1))?'already_same_local':'direct_conflict';return 'manual_hold';}
    if(($state['accepted_local']??null)!==null)return ((int)$state['accepted_local']===(int)$state['target_local'])?'already_same_local':'direct_conflict';
    if(($state['existing_mapping']??false))return 'existing_mapping_hold';
    if(($state['target_country']??0)!==($state['observed_country']??-1))return 'direct_conflict';
    if(($state['source_country']??null)===null)return 'needs_country_evidence';
    if(($state['source_country']??0)!==($state['target_country']??-1))return 'direct_conflict';
    if(($state['target_occupied']??false))return 'provider_occupancy_hold';
    if(($state['coordinate_conflict']??false))return 'direct_conflict';
    if(($state['qualifier_conflict']??false))return 'qualifier_conflict';
    if(!($state['source_name_present']??false))return 'needs_name_evidence';
    $exact=(bool)($state['exact_name']??false);$common=(int)($state['common_tokens']??0);$directGeo=(bool)($state['direct_geo']??false);
    if($exact||$common>=2||($common>=1&&$directGeo))return 'candidate_anex';
    return 'needs_name_evidence';
}
function hmto_review(PDO $db,string $operation): array {
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
    try{
        $exists=$db->query("SHOW TABLES LIKE 'tour_operator_identity_observations'");if(!$exists||$exists->fetchColumn()===false){$db->rollBack();return ['schema'=>'hotel-match-current-tv-anex-observations/1','status'=>'observation_table_missing','operation_id'=>$operation,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'no_replay'=>true];}
        $hotels=[];$targetNames=[];foreach($db->query('SELECT h.id,h.country_id,h.name,h.normalized_name,h.region_name,h.subregion_name,COALESCE(d.latitude,h.latitude) latitude,COALESCE(d.longitude,h.longitude) longitude FROM catalog_hotels h LEFT JOIN catalog_hotel_details d ON d.hotel_id=h.id WHERE h.country_id IN (1,2,4,8,9,10,12,16) AND h.is_active=1 ORDER BY h.id')->fetchAll(PDO::FETCH_ASSOC) as $r){$id=(int)$r['id'];$hotels[$id]=$r;$targetNames[$id]=hmto_names([$r['name'],$r['normalized_name']]);}
        foreach($db->query('SELECT a.hotel_id,a.alias,a.normalized_alias,h.country_id FROM hotel_aliases a JOIN catalog_hotels h ON h.id=a.hotel_id WHERE h.country_id IN (1,2,4,8,9,10,12,16) AND h.is_active=1 ORDER BY a.hotel_id,a.id')->fetchAll(PDO::FETCH_ASSOC) as $r){$id=(int)$r['hotel_id'];if(isset($hotels[$id]))$targetNames[$id]=hmto_names(array_merge($targetNames[$id],[$r['alias'],$r['normalized_alias']]));}
        $manual=[];foreach($db->query('SELECT anex_hotel_id,decision_status,catalog_hotel_id FROM anex_hotel_decisions')->fetchAll(PDO::FETCH_ASSOC) as $r)$manual[(int)$r['anex_hotel_id']]=$r;
        $mappings=[];$occupied=[];foreach($db->query('SELECT anex_hotel_id,catalog_hotel_id,enabled,scope,approval_policy FROM anex_hotel_search_mappings')->fetchAll(PDO::FETCH_ASSOC) as $r){$aid=(int)$r['anex_hotel_id'];$mappings[$aid][]=$r;if((int)$r['enabled']===1&&$r['scope']==='preview'&&$r['approval_policy']===FC_POLICY)$occupied[(int)$r['catalog_hotel_id']][$aid]=true;}
        foreach($manual as $aid=>$r)if(($r['decision_status']??'')==='accepted'&&$r['catalog_hotel_id']!==null)$occupied[(int)$r['catalog_hotel_id']][$aid]=true;
        $excluded=[];foreach($db->query('SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions')->fetchAll(PDO::FETCH_ASSOC) as $r)$excluded[(int)$r['anex_hotel_id']][(int)$r['catalog_hotel_id']]=true;
        $stage=[];foreach($db->query('SELECT * FROM anex_hotels')->fetchAll(PDO::FETCH_ASSOC) as $r)$stage[(int)$r['anex_hotel_id']]=$r;
        $obs=[];foreach($db->query('SELECT * FROM anex_search_hotel_observations ORDER BY search_count DESC,last_seen_utc DESC,anex_hotel_id')->fetchAll(PDO::FETCH_ASSOC) as $r){$aid=(int)$r['anex_hotel_id'];if(!isset($obs[$aid]))$obs[$aid]=$r;}
        $raw=$db->query('SELECT id,first_seen_at,last_seen_at,observation_count,source,search_id,country_id,region_id,subregion_id,hotel_id,hotel_name,region_name,subregion_name,latitude,longitude,operator_id,operator_name,tour_id,operator_link,operator_link_host,operator_link_path,operator_link_query FROM tour_operator_identity_observations WHERE country_id IN (1,2,4,8,9,10,12,16) ORDER BY observation_count DESC,last_seen_at DESC,id')->fetchAll(PDO::FETCH_ASSOC);
        $stats=['rows_scanned'=>count($raw),'anex_operator_rows'=>0,'direct_key_rows'=>0,'missing_direct_key'=>0,'ambiguous_direct_key'=>0,'invalid_link'=>0,'invalid_direct_key'=>0,'link_metadata_conflict'=>0,'not_anex'=>0];$pairs=[];
        foreach($raw as $r){$key=hmto_direct_key($r);$ks=(string)$key['status'];if($ks==='not_anex'){$stats['not_anex']++;continue;}$stats['anex_operator_rows']++;if($ks!=='direct_key'){$stats[$ks]=($stats[$ks]??0)+1;continue;}$stats['direct_key_rows']++;$aid=(int)$key['anex_hotel_id'];$local=(int)$r['hotel_id'];$pk=$aid.'|'.$local;if(!isset($pairs[$pk]))$pairs[$pk]=['anex_hotel_id'=>$aid,'target_local_id'=>$local,'observed_country'=>(int)$r['country_id'],'observation_count'=>0,'first_seen'=>(string)$r['first_seen_at'],'last_seen'=>(string)$r['last_seen_at'],'tour_ids'=>[],'params'=>[],'operator_names'=>[],'tv_names'=>[],'observed_coords'=>[],'hosts'=>[],'paths'=>[]];$p=&$pairs[$pk];$p['observation_count']+=(int)$r['observation_count'];if((string)$r['first_seen_at']<$p['first_seen'])$p['first_seen']=(string)$r['first_seen_at'];if((string)$r['last_seen_at']>$p['last_seen'])$p['last_seen']=(string)$r['last_seen_at'];$p['tour_ids'][(string)$r['tour_id']]=true;foreach($key['params'] as $x)$p['params'][$x]=true;$p['operator_names'][(string)$r['operator_name']]=true;$p['tv_names'][(string)$r['hotel_name']]=true;$p['hosts'][(string)($key['host']??'')]=true;$p['paths'][(string)($key['path']??'')]=true;if(fc_num($r['latitude']??null)!==null&&fc_num($r['longitude']??null)!==null)$p['observed_coords'][]=[(float)$r['latitude'],(float)$r['longitude']];unset($p);}
        $byAnex=[];$byLocal=[];foreach($pairs as $p){$byAnex[$p['anex_hotel_id']][$p['target_local_id']]=true;$byLocal[$p['target_local_id']][$p['anex_hotel_id']]=true;}
        $rows=[];$counts=[];foreach($pairs as $p){$aid=$p['anex_hotel_id'];$target=(int)$p['target_local_id'];$targetRow=$hotels[$target]??null;$st=$stage[$aid]??[];$ob=$obs[$aid]??[];$sourceNames=hmto_names([$st['api_name']??'',$st['xml_name']??'',$st['xml_alternate_name']??'',$ob['hotel_name']??'']);$guard=hmto_name_guard($sourceNames,$targetNames[$target]??[]);$sourceCountry=(int)($ob['country_id']??0);if(!$sourceCountry)$sourceCountry=fc_country($st['api_country']??'')??0;$sourceCountry=$sourceCountry?:null;[$slat,$slon]=hmto_coord($st);$distance=$targetRow?fc_dist($slat,$slon,$targetRow['latitude']??null,$targetRow['longitude']??null):null;$place=$targetRow?fc_place([$st['api_region']??'',$st['api_town']??''],[$targetRow['region_name']??'',$targetRow['subregion_name']??'']):false;$manualRow=$manual[$aid]??null;$acceptedLocal=null;$existing=false;foreach($mappings[$aid]??[] as $mr){if((int)$mr['enabled']===1&&$mr['scope']==='preview'&&$mr['approval_policy']===FC_POLICY){$acceptedLocal=(int)$mr['catalog_hotel_id'];break;}$existing=true;}
            $state=['manual_status'=>$manualRow['decision_status']??null,'manual_local'=>$manualRow['catalog_hotel_id']??null,'pair_excluded'=>isset($excluded[$aid][$target]),'accepted_local'=>$acceptedLocal,'existing_mapping'=>$existing,'target_local'=>$target,'target_country'=>$targetRow?(int)$targetRow['country_id']:0,'observed_country'=>(int)$p['observed_country'],'source_country'=>$sourceCountry,'target_occupied'=>isset($occupied[$target])&&(!isset($occupied[$target][$aid])||count($occupied[$target])>1),'coordinate_conflict'=>$distance!==null&&$distance>5000,'direct_geo'=>($distance!==null&&$distance<=1000)||$place,'qualifier_conflict'=>$guard['qualifier_conflict'],'source_name_present'=>(bool)$sourceNames,'exact_name'=>$guard['exact_name'],'common_tokens'=>$guard['common_tokens']];
            $status=count($byAnex[$aid]??[])>1?'ambiguous_observed_target':($targetRow===null?'target_missing':hmto_classify($state));if($status==='candidate_anex'&&count($byLocal[$target]??[])>1)$status='ambiguous_observed_anex_ids';$counts[$status]=($counts[$status]??0)+1;$rows[]=['anex_hotel_id'=>$aid,'target_local_id'=>$target,'status'=>$status,'observation_count'=>$p['observation_count'],'first_seen'=>$p['first_seen'],'last_seen'=>$p['last_seen'],'tour_count'=>count($p['tour_ids']),'direct_key_params'=>array_values(array_keys($p['params'])),'operator_names'=>array_values(array_filter(array_keys($p['operator_names']),'strlen')),'tv_names'=>array_values(array_filter(array_keys($p['tv_names']),'strlen')),'source_names'=>$sourceNames,'target_names'=>$targetNames[$target]??[],'source_country'=>$sourceCountry,'target_country'=>$targetRow?(int)$targetRow['country_id']:null,'distance_m'=>$distance===null?null:(int)round($distance),'place_match'=>$place,'common_tokens'=>$guard['common_tokens'],'exact_name'=>$guard['exact_name'],'source_qualifiers'=>$guard['source_qualifiers'],'target_qualifiers'=>$guard['target_qualifiers'],'target_occupied_ids'=>array_map('intval',array_keys($occupied[$target]??[])),'direct_key_hosts'=>array_values(array_filter(array_keys($p['hosts']),'strlen')),'direct_key_paths'=>array_values(array_filter(array_keys($p['paths']),'strlen'))];}
        usort($rows,static function($a,$b){$ac=$a['status']==='candidate_anex'?0:1;$bc=$b['status']==='candidate_anex'?0:1;if($ac!==$bc)return $ac<=>$bc;if($a['observation_count']!==$b['observation_count'])return $b['observation_count']<=>$a['observation_count'];return $a['anex_hotel_id']<=>$b['anex_hotel_id'];});ksort($counts,SORT_STRING);$db->rollBack();
        return ['schema'=>'hotel-match-current-tv-anex-observations/1','status'=>'completed','operation_id'=>$operation,'stats'=>$stats,'status_counts'=>$counts,'unique_direct_pairs'=>count($pairs),'candidate_count'=>$counts['candidate_anex']??0,'rows'=>$rows,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'no_replay'=>true];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
