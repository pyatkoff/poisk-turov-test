<?php
declare(strict_types=1);
if (!defined('FC_LIBRARY_ONLY')) define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/hotel_full_catalog_reconcile.php';

const HMOK_CORE8 = [1=>true,2=>true,4=>true,8=>true,9=>true,10=>true,12=>true,16=>true];
const HMOK_QUALIFIERS = ['annex'=>true,'beach'=>true,'garden'=>true,'gardens'=>true,'north'=>true,'south'=>true,'posh'=>true,'adult'=>true,'adults'=>true,'pool'=>true,'sea'=>true,'prestige'=>true,'aquamarine'=>true,'aqua'=>true,'family'=>true];

function hmok_names(array $values): array {
    $out=[];
    foreach($values as $value){$value=trim((string)$value);if($value!=='')$out[$value]=true;}
    return array_keys($out);
}
function hmok_tokens(string $value): array {
    $tokens=fc_tokens($value,false);
    $tokens=array_values(array_filter($tokens,static fn($x)=>!in_array($x,['resort','resorts','spa','резорт','ресорт','спа'],true)));
    return array_values(array_unique($tokens));
}
function hmok_qualifiers(array $names): array {
    $out=[];foreach($names as $name)foreach(hmok_tokens((string)$name) as $token)if(isset(HMOK_QUALIFIERS[$token]))$out[$token]=true;
    $keys=array_keys($out);sort($keys,SORT_STRING);return $keys;
}
function hmok_name_guard(array $savedNames,array $currentNames,array $targetNames=[]): array {
    $saved=[];$current=[];$target=[];
    foreach($savedNames as $n)foreach(hmok_tokens((string)$n) as $t)$saved[$t]=true;
    foreach($currentNames as $n)foreach(hmok_tokens((string)$n) as $t)$current[$t]=true;
    foreach($targetNames as $n)foreach(hmok_tokens((string)$n) as $t)$target[$t]=true;
    $currentCommon=count(array_intersect_key($saved,$current));
    $targetCommon=$target?count(array_intersect_key($saved,$target)):0;
    $sq=hmok_qualifiers($savedNames);$cq=hmok_qualifiers($currentNames);$tq=hmok_qualifiers($targetNames);
    $qualifierConflict=($sq&&$cq&&$sq!==$cq)||($sq&&$tq&&$sq!==$tq)||($cq&&$tq&&$cq!==$tq);
    return ['saved_tokens'=>count($saved),'current_common'=>$currentCommon,'target_common'=>$targetCommon,'saved_qualifiers'=>$sq,'current_qualifiers'=>$cq,'target_qualifiers'=>$tq,'qualifier_conflict'=>$qualifierConflict,'current_name_supported'=>$currentCommon>0||(!$current&&count($saved)>=2),'target_name_supported'=>!$target||$targetCommon>0];
}
function hmok_coord_from(array $source): array {
    foreach([['latitude','longitude'],['lat','lng'],['lat','lon'],['hotelLatitude','hotelLongitude']] as $keys){
        if(!array_key_exists($keys[0],$source)||!array_key_exists($keys[1],$source))continue;
        $lat=fc_num($source[$keys[0]]);$lon=fc_num($source[$keys[1]]);
        if($lat!==null&&$lon!==null&&abs($lat)<=90&&abs($lon)<=180)return[$lat,$lon];
    }
    return[null,null];
}
function hmok_geo(array $sources,array $target): array {
    $distances=[];$conflict=false;$close=false;
    foreach($sources as $label=>$source){if(!is_array($source))continue;[$lat,$lon]=hmok_coord_from($source);$d=fc_dist($lat,$lon,$target['latitude']??null,$target['longitude']??null);if($d!==null){$distances[$label]=(int)round($d);if($d>5000)$conflict=true;if($d<=1000)$close=true;}}
    $places=[];foreach($sources as $source)if(is_array($source))foreach(['api_region','api_town','town','region_name','subregion_name','place'] as $key)if(isset($source[$key])&&trim((string)$source[$key])!=='')$places[]=(string)$source[$key];
    $place=fc_place($places,[(string)($target['region_name']??''),(string)($target['subregion_name']??'')]);
    return ['distance_m'=>$distances,'coordinate_conflict'=>$conflict,'close_coordinate'=>$close,'place_match'=>$place,'direct_geo'=>$close||$place];
}
function hmok_pair_input(array $pair): array {
    $anex=filter_var($pair['anex_hotel_id']??null,FILTER_VALIDATE_INT);
    $andr=(string)($pair['andromeda_hotel_id']??'');
    $country=filter_var($pair['country_id']??null,FILTER_VALIDATE_INT);
    if($anex===false||(int)$anex<=0||!preg_match('/^[0-9]{1,20}$/D',$andr)||$country===false||!isset(HMOK_CORE8[(int)$country]))throw new InvalidArgumentException('invalid_direct_pair');
    if(($pair['operator_key']??5)!==5&&(int)($pair['operator_key']??0)!==5)throw new InvalidArgumentException('invalid_operator');
    if(!empty($pair['operator_scoped']))throw new InvalidArgumentException('operator_scoped_quarantined');
    $names=hmok_names([$pair['hotel_name']??'']);
    if(!$names)throw new InvalidArgumentException('missing_saved_name');
    $hotelUrl=(string)($pair['hotel_url']??'');$imageUrl=(string)($pair['image_url']??'');
    $u=parse_url($hotelUrl);if(!is_array($u)||strtolower((string)($u['scheme']??''))!=='https'||strtolower((string)($u['host']??''))!=='agent.anextour.ru')throw new InvalidArgumentException('invalid_hotel_url');
    $expected='/5\.'.preg_quote((string)(int)$anex,'/').'\.'.preg_quote($andr,'/').'\.jpg(?:$|[?#])/';if(!preg_match($expected,$imageUrl))throw new InvalidArgumentException('direct_key_image_mismatch');
    return ['anex_hotel_id'=>(int)$anex,'andromeda_hotel_id'=>$andr,'country_id'=>(int)$country,'hotel_name'=>$names[0],'town'=>(string)($pair['town']??''),'hotel_url'=>$hotelUrl,'image_url'=>$imageUrl];
}
function hmok_unique_local(array $index,int $country,array $names): ?int {
    $targets=[];foreach($names as $name){$key=fc_key($name,false,false);if($key==='')continue;foreach(array_keys($index[$country][$key]??[]) as $id)$targets[(int)$id]=true;}return count($targets)===1?(int)array_key_first($targets):null;
}
function hmok_classify(array $state,array $pair): array {
    $a=$state['anex']??[];$d=$state['andromeda']??[];$target=null;$status='unresolved_both';
    if(($a['manual_hold']??false))$status='manual_hold';
    elseif(($a['pair_excluded']??false))$status='pair_excluded';
    elseif(($a['accepted_local']??null)!==null&&($d['accepted_local']??null)!==null){$status=((int)$a['accepted_local']===(int)$d['accepted_local'])?'already_same_local':'direct_conflict';$target=(int)$a['accepted_local'];}
    elseif(($a['accepted_local']??null)!==null){$target=(int)$a['accepted_local'];$status=($d['can_accept']??false)?'candidate_andromeda':(($d['status']??'')==='missing'?'andromeda_identity_missing':'andromeda_hold');}
    elseif(($d['accepted_local']??null)!==null){$target=(int)$d['accepted_local'];$status=($a['can_accept']??false)?'candidate_anex':'anex_hold';}
    elseif(($state['resolved_local']??null)!==null&&($a['can_accept']??false)&&($d['can_accept']??false)){$target=(int)$state['resolved_local'];$status='candidate_both';}
    if($target!==null){
        if(($state['target_country']??null)!==(int)$pair['country_id'])$status='direct_conflict';
        elseif(($state['target_occupied_anex']??false)&&($a['accepted_local']??null)===null)$status='provider_occupancy_hold';
        elseif(($state['target_occupied_andromeda']??false)&&($d['accepted_local']??null)===null)$status='provider_occupancy_hold';
        elseif(($state['geo']['coordinate_conflict']??false))$status='direct_conflict';
        elseif(($state['name_guard']['qualifier_conflict']??false))$status='qualifier_conflict';
        elseif(!($state['name_guard']['current_name_supported']??false))$status='needs_evidence';
        elseif($status==='candidate_both'&&!($state['name_guard']['target_name_supported']??false)&&!($state['geo']['direct_geo']??false))$status='needs_evidence';
    }
    return ['status'=>$status,'target_local_id'=>$target];
}
function hmok_review(PDO $db,array $pairs,string $operation): array {
    if(count($pairs)>5000)throw new RuntimeException('pair_scope_limit');
    $pairs=array_map('hmok_pair_input',$pairs);$seen=[];foreach($pairs as $p){$k=$p['anex_hotel_id'].'|'.$p['andromeda_hotel_id'];if(isset($seen[$k]))throw new RuntimeException('duplicate_direct_pair');$seen[$k]=true;}
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
    try{
        $hotels=[];$strict=[];$targetNames=[];
        foreach($db->query('SELECT h.id,h.country_id,h.name,h.normalized_name,h.region_name,h.subregion_name,h.category,COALESCE(d.latitude,h.latitude) latitude,COALESCE(d.longitude,h.longitude) longitude FROM catalog_hotels h LEFT JOIN catalog_hotel_details d ON d.hotel_id=h.id WHERE h.country_id IN (1,2,4,8,9,10,12,16) AND h.is_active=1 ORDER BY h.id')->fetchAll(PDO::FETCH_ASSOC) as $h){$id=(int)$h['id'];$hotels[$id]=$h;$targetNames[$id]=hmok_names([$h['name'],$h['normalized_name']]);foreach($targetNames[$id] as $n){$k=fc_key($n);if($k!=='')$strict[(int)$h['country_id']][$k][$id]=true;}}
        foreach($db->query('SELECT a.hotel_id,a.alias,a.normalized_alias,h.country_id FROM hotel_aliases a JOIN catalog_hotels h ON h.id=a.hotel_id WHERE h.country_id IN (1,2,4,8,9,10,12,16) AND h.is_active=1 ORDER BY a.hotel_id,a.id')->fetchAll(PDO::FETCH_ASSOC) as $r){$id=(int)$r['hotel_id'];foreach(hmok_names([$r['alias'],$r['normalized_alias']]) as $n){$targetNames[$id][]=$n;$k=fc_key($n);if($k!=='')$strict[(int)$r['country_id']][$k][$id]=true;}}
        $manual=[];foreach($db->query('SELECT anex_hotel_id,decision_status,catalog_hotel_id FROM anex_hotel_decisions')->fetchAll(PDO::FETCH_ASSOC) as $r)$manual[(int)$r['anex_hotel_id']]=$r;
        $mappingRows=[];$anexLocalByTarget=[];foreach($db->query('SELECT anex_hotel_id,catalog_hotel_id,enabled,scope,approval_policy FROM anex_hotel_search_mappings')->fetchAll(PDO::FETCH_ASSOC) as $r){$id=(int)$r['anex_hotel_id'];$mappingRows[$id][]=$r;if((int)$r['enabled']===1&&$r['scope']==='preview'&&$r['approval_policy']===FC_POLICY)$anexLocalByTarget[(int)$r['catalog_hotel_id']][$id]=true;}
        foreach($manual as $id=>$r)if(($r['decision_status']??'')==='accepted'&&$r['catalog_hotel_id']!==null)$anexLocalByTarget[(int)$r['catalog_hotel_id']][$id]=true;
        $excluded=[];foreach($db->query('SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions')->fetchAll(PDO::FETCH_ASSOC) as $r)$excluded[(int)$r['anex_hotel_id']][(int)$r['catalog_hotel_id']]=true;
        $anexStage=[];foreach($db->query('SELECT * FROM anex_hotels')->fetchAll(PDO::FETCH_ASSOC) as $r)$anexStage[(int)$r['anex_hotel_id']]=$r;
        $anexObs=[];foreach($db->query('SELECT * FROM anex_search_hotel_observations ORDER BY search_count DESC,last_seen_utc DESC,anex_hotel_id')->fetchAll(PDO::FETCH_ASSOC) as $r){$id=(int)$r['anex_hotel_id'];if(!isset($anexObs[$id]))$anexObs[$id]=$r;}
        $andr=[];$andLocalByTarget=[];foreach($db->query("SELECT * FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' ORDER BY external_hotel_id")->fetchAll(PDO::FETCH_ASSOC) as $r){$id=(string)$r['external_hotel_id'];$andr[$id]=$r;if(($r['decision_status']??'')==='accepted'&&$r['local_hotel_id']!==null)$andLocalByTarget[(int)$r['local_hotel_id']][$id]=true;}
        $andObs=[];foreach($db->query("SELECT * FROM andromeda_search_hotel_observations WHERE supplier_namespace='andromeda_catalog' ORDER BY observed_at_utc DESC,external_hotel_id")->fetchAll(PDO::FETCH_ASSOC) as $r){$id=(string)$r['external_hotel_id'];if(!isset($andObs[$id]))$andObs[$id]=$r;}
        $rows=[];$counts=[];
        foreach($pairs as $pair){$aid=$pair['anex_hotel_id'];$did=$pair['andromeda_hotel_id'];$m=$manual[$aid]??null;$maps=$mappingRows[$aid]??[];$acceptedAnex=null;
            if($m&&($m['decision_status']??'')==='accepted'&&$m['catalog_hotel_id']!==null)$acceptedAnex=(int)$m['catalog_hotel_id'];
            elseif(!$m)foreach($maps as $mr)if((int)$mr['enabled']===1&&$mr['scope']==='preview'&&$mr['approval_policy']===FC_POLICY){$acceptedAnex=(int)$mr['catalog_hotel_id'];break;}
            $ar=$andr[$did]??null;$acceptedAnd=$ar&&($ar['decision_status']??'')==='accepted'&&$ar['local_hotel_id']!==null?(int)$ar['local_hotel_id']:null;
            $aStage=$anexStage[$aid]??[];$aObs=$anexObs[$aid]??[];$ev=$ar?fc_evidence($ar['evidence_json']??''):[];$src=$ev['source']??[];if(!is_array($src))$src=[];$dObs=$andObs[$did]??[];
            $anexNames=hmok_names([$aStage['api_name']??'',$aStage['xml_name']??'',$aStage['xml_alternate_name']??'',$aObs['hotel_name']??'']);$andNames=hmok_names([$src['name']??'',$src['lName']??'',$dObs['hotel_name']??'']);$currentNames=hmok_names(array_merge($anexNames,$andNames));$savedNames=[$pair['hotel_name']];
            $resolved=hmok_unique_local($strict,$pair['country_id'],hmok_names(array_merge($savedNames,$currentNames)));$target=$acceptedAnex??$acceptedAnd??$resolved;$targetRow=$target!==null?($hotels[$target]??null):null;
            $anexCountry=(int)($aObs['country_id']??0);if(!$anexCountry)$anexCountry=fc_country($aStage['api_country']??'')??0;
            $andCountry=(int)($dObs['country_id']??0);$state=['anex'=>['accepted_local'=>$acceptedAnex,'manual_hold'=>$m&&($m['decision_status']??'')!=='accepted','can_accept'=>$m===null&&count($maps)===0&&(bool)($aStage||$aObs),'status'=>$m?'manual':($maps?'existing_mapping':'free')],'andromeda'=>['accepted_local'=>$acceptedAnd,'can_accept'=>$ar!==null&&($ar['decision_status']??'')==='pending'&&$ar['local_hotel_id']===null,'status'=>$ar===null?'missing':(string)$ar['decision_status']],'resolved_local'=>$resolved,'target_country'=>$targetRow?(int)$targetRow['country_id']:null,'target_occupied_anex'=>$target!==null&&isset($anexLocalByTarget[$target])&&!isset($anexLocalByTarget[$target][$aid]),'target_occupied_andromeda'=>$target!==null&&isset($andLocalByTarget[$target])&&!isset($andLocalByTarget[$target][$did])];
            $state['anex']['pair_excluded']=$target!==null&&isset($excluded[$aid][$target]);$state['name_guard']=hmok_name_guard($savedNames,$currentNames,$target!==null?($targetNames[$target]??[]):[]);$sources=['anex'=>$aStage,'andromeda'=>$src,'saved'=>['town'=>$pair['town']]];$state['geo']=$targetRow?hmok_geo($sources,$targetRow):['distance_m'=>[],'coordinate_conflict'=>false,'close_coordinate'=>false,'place_match'=>false,'direct_geo'=>false];
            if(($anexCountry&&$anexCountry!==$pair['country_id'])||($andCountry&&$andCountry!==$pair['country_id'])){$classified=['status'=>'direct_conflict','target_local_id'=>$target];}else{$classified=hmok_classify($state,$pair);}
            $status=$classified['status'];$counts[$status]=($counts[$status]??0)+1;$rows[]=['anex_hotel_id'=>$aid,'andromeda_hotel_id'=>$did,'saved_hotel_name'=>$pair['hotel_name'],'saved_town'=>$pair['town'],'country_id'=>$pair['country_id'],'target_local_id'=>$classified['target_local_id'],'status'=>$status,'anex_state'=>$state['anex']['status'],'andromeda_state'=>$state['andromeda']['status'],'anex_current_names'=>$anexNames,'andromeda_current_names'=>$andNames,'name_guard'=>$state['name_guard'],'geo'=>$state['geo'],'observed'=>['anex'=>(bool)$aObs,'andromeda'=>(bool)$dObs],'not_write_authority'=>true];
        }
        ksort($counts);usort($rows,static fn($a,$b)=>strcmp($a['status'],$b['status'])?:$a['anex_hotel_id']<=>$b['anex_hotel_id']);$db->commit();return['schema'=>'hotel-match-current-direct-operator-key/1','status'=>'completed','operation_id'=>$operation,'mode'=>'current_db_read_only','source_contract'=>'saved_andromeda_anex_original_hotel_key','input_pairs'=>count($pairs),'status_counts'=>$counts,'rows'=>$rows,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'guards'=>['ordinary_andromeda_rows_only'=>true,'current_db_revalidation'=>true,'manual_decisions_preserved'=>true,'pair_exclusions_preserved'=>true,'existing_mappings_preserved'=>true,'provider_occupancy_preserved'=>true,'coordinate_conflict_gt_5km_blocks'=>true,'meaningful_qualifiers_preserved'=>true,'generic_hotel_resort_spa_ignored'=>true,'no_mapping_authority'=>true]];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
