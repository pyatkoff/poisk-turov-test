<?php
declare(strict_types=1);
if (!defined('FC_LIBRARY_ONLY')) define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/hotel_match_live_priority_review.php';

const AALP_OPERATION = 'hotel-match-anex-andromeda-live-pair-current-review-1971-20260911-v1';
const AALP_MANIFEST = 'hotel_match_anex_andromeda_live_pair_current_review_v1.json';

function aalp_norm(string $value, bool $dropGeneric = false): string {
    $value = str_replace(['Ё','ё'],['Е','е'],$value);
    $value = function_exists('mb_strtolower') ? mb_strtolower($value,'UTF-8') : strtolower($value);
    $value = (string)(preg_replace('/\b(?:ex|ех)\.?\s*/iu',' ',$value) ?? $value);
    $value = (string)(preg_replace('/[^\p{L}\p{N}]+/u',' ',$value) ?? $value);
    $tokens = array_values(array_filter(preg_split('/\s+/u',trim($value)) ?: [],static fn($x)=>$x!==''));
    if ($dropGeneric) $tokens = array_values(array_filter($tokens,static fn($x)=>!in_array($x,['hotel','hotels','resort','resorts','spa'],true)));
    return implode(' ',$tokens);
}

function aalp_keys(array $names): array {
    $out=[];
    foreach($names as $name){
        $name=trim((string)$name); if($name==='')continue;
        foreach([false,true] as $drop){$key=aalp_norm($name,$drop);if($key!=='')$out[$key]=true;}
    }
    return $out;
}

function aalp_alias_ok(array $manifestKeys,array $sourceNames,array $targetNames): bool {
    $manifest=array_fill_keys(array_values(array_filter(array_map('strval',$manifestKeys),static fn($v)=>$v!=='')),true);
    if(!$manifest)return false;
    $source=aalp_keys($sourceNames);$target=aalp_keys($targetNames);
    return (bool)array_intersect_key($manifest,$source) && (bool)array_intersect_key($manifest,$target);
}

function aalp_decide(array $anex,array $andromeda,bool $countryOk,bool $aliasOk,bool $geoOk,bool $coordinateConflict): array {
    if(!$countryOk)return ['bucket'=>'hard_conflict','reason'=>'country_mismatch'];
    if($coordinateConflict)return ['bucket'=>'hard_conflict','reason'=>'coordinate_conflict_gt_5km'];
    if(($anex['state']??'')==='protected'||($andromeda['state']??'')==='protected')return ['bucket'=>'protected','reason'=>'manual_conflict_or_existing_nonbridge'];
    if(($anex['state']??'')==='accepted'&&($andromeda['state']??'')==='accepted'){
        return ((int)($anex['local_id']??0)===(int)($andromeda['local_id']??0))
            ? ['bucket'=>'already_resolved','reason'=>'both_providers_already_bridge']
            : ['bucket'=>'hard_conflict','reason'=>'providers_map_to_different_locals'];
    }
    $aUn=($anex['state']??'')==='unresolved';$dUn=($andromeda['state']??'')==='unresolved';
    if($aUn===$dUn)return ['bucket'=>'deferred','reason'=>'requires_exactly_one_current_unresolved_provider'];
    $bridge=$aUn?$andromeda:$anex;
    if(($bridge['state']??'')!=='accepted'||(int)($bridge['local_id']??0)<=0)return ['bucket'=>'deferred','reason'=>'cross_provider_bridge_missing'];
    if(!$aliasOk&&!$geoOk)return ['bucket'=>'deferred','reason'=>'live_pair_needs_current_alias_or_geo_corroboration'];
    return ['bucket'=>'safe','provider'=>$aUn?'anex':'andromeda','reason'=>$aliasOk?'live_pair_unique_alias_plus_current_cross_provider_bridge':'live_pair_current_geo_plus_cross_provider_bridge','target_local_hotel_id'=>(int)$bridge['local_id']];
}

function aalp_anex_state(PDO $db,int $id): array {
    $q=$db->prepare('SELECT catalog_hotel_id,decision_status FROM anex_hotel_decisions WHERE anex_hotel_id=? LIMIT 2');$q->execute([$id]);$dec=$q->fetchAll(PDO::FETCH_ASSOC);
    if($dec){$r=$dec[0];if(count($dec)===1&&$r['decision_status']==='accepted'&&(int)$r['catalog_hotel_id']>0){$target=(int)$r['catalog_hotel_id'];$x=$db->prepare('SELECT 1 FROM anex_review_pair_exclusions WHERE anex_hotel_id=? AND catalog_hotel_id=? LIMIT 1');$x->execute([$id,$target]);if(!$x->fetchColumn())return ['state'=>'accepted','local_id'=>$target,'source'=>'manual_accepted'];}return ['state'=>'protected','reason'=>'anex_decision_present'];}
    $q=$db->prepare('SELECT catalog_hotel_id,enabled,scope,approval_policy FROM anex_hotel_search_mappings WHERE anex_hotel_id=? ORDER BY catalog_hotel_id');$q->execute([$id]);$rows=$q->fetchAll(PDO::FETCH_ASSOC);
    if(!$rows)return ['state'=>'unresolved'];
    $valid=[];foreach($rows as$r){$target=(int)$r['catalog_hotel_id'];if((int)$r['enabled']!==1||$r['scope']!=='preview'||$r['approval_policy']!==MBR_POLICY)continue;$x=$db->prepare('SELECT 1 FROM anex_review_pair_exclusions WHERE anex_hotel_id=? AND catalog_hotel_id=? LIMIT 1');$x->execute([$id,$target]);if(!$x->fetchColumn())$valid[$target]=true;}
    if(count($rows)===1&&count($valid)===1)return ['state'=>'accepted','local_id'=>(int)array_key_first($valid),'source'=>'search_mapping'];
    return ['state'=>'protected','reason'=>'anex_existing_mapping_nonbridge_or_ambiguous'];
}

function aalp_andromeda_state(PDO $db,string $id): array {
    $q=$db->prepare("SELECT local_hotel_id,decision_status,evidence_json,catalog_sha256 FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=? LIMIT 2");$q->execute([$id]);$rows=$q->fetchAll(PDO::FETCH_ASSOC);
    if(!$rows)return ['state'=>'unresolved','row'=>null];
    if(count($rows)!==1)return ['state'=>'protected','reason'=>'andromeda_identity_ambiguous'];
    $r=$rows[0];
    if($r['decision_status']==='pending'&&$r['local_hotel_id']===null)return ['state'=>'unresolved','row'=>$r];
    if($r['decision_status']==='accepted'&&(int)$r['local_hotel_id']>0)return ['state'=>'accepted','local_id'=>(int)$r['local_hotel_id'],'row'=>$r];
    return ['state'=>'protected','reason'=>'andromeda_conflict_or_decision','row'=>$r];
}

function aalp_anex_context(PDO $db,int $id): array {
    $names=[];$places=[];$coord=[];$country=0;
    $q=$db->prepare('SELECT * FROM anex_hotels WHERE anex_hotel_id=? LIMIT 1');$q->execute([$id]);$s=$q->fetch(PDO::FETCH_ASSOC)?:[];
    foreach(['api_name','xml_name','xml_alternate_name']as$k)if(trim((string)($s[$k]??''))!=='')$names[]=(string)$s[$k];
    foreach(['api_region','api_town']as$k)if(trim((string)($s[$k]??''))!=='')$places[]=(string)$s[$k];
    $country=fc_country($s['api_country']??'')??0;$coord=$s;
    $q=$db->prepare('SELECT * FROM anex_search_hotel_observations WHERE anex_hotel_id=? ORDER BY last_seen_utc DESC LIMIT 1');$q->execute([$id]);$o=$q->fetch(PDO::FETCH_ASSOC)?:[];
    if(trim((string)($o['hotel_name']??''))!=='')$names[]=(string)$o['hotel_name'];if((int)($o['country_id']??0)>0)$country=(int)$o['country_id'];
    return ['names'=>array_values(array_unique($names)),'places'=>array_values(array_unique($places)),'coord'=>$coord,'country_id'=>$country,'live_observation'=>(bool)$o];
}

function aalp_andromeda_context(PDO $db,string $id,?array $identity): array {
    $prior=fc_evidence($identity['evidence_json']??'');$source=$prior['source']??[];if(!is_array($source))$source=[];$geo=$prior['geography']??[];if(!is_array($geo))$geo=[];
    $names=array_values(array_unique(array_filter([(string)($source['name']??''),(string)($source['lName']??'')],static fn($v)=>trim($v)!=='')));
    $places=array_values(array_unique(array_filter([(string)($source['town']??''),(string)($geo['town']??''),(string)($geo['parent']??'')],static fn($v)=>trim($v)!=='')));
    $country=0;$coord=$source;
    $q=$db->prepare("SELECT * FROM andromeda_search_hotel_observations WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=? ORDER BY observed_at_utc DESC LIMIT 1");$q->execute([$id]);$o=$q->fetch(PDO::FETCH_ASSOC)?:[];
    if(trim((string)($o['hotel_name']??''))!=='')$names[]=(string)$o['hotel_name'];if(trim((string)($o['region_name']??''))!=='')$places[]=(string)$o['region_name'];if((int)($o['country_id']??0)>0)$country=(int)$o['country_id'];$coord=$coord+$o;
    if(!$country&&$identity)$country=(int)(fc_sha_countries($db)[$identity['catalog_sha256']??'']??0);
    return ['names'=>array_values(array_unique($names)),'places'=>array_values(array_unique($places)),'coord'=>$coord,'country_id'=>$country,'live_observation'=>(bool)$o];
}

function aalp_review(PDO $db,string $manifestPath,string $operation=AALP_OPERATION): array {
    if($operation!==AALP_OPERATION)throw new RuntimeException('operation_scope');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $manifest=json_decode((string)file_get_contents($manifestPath),true,64,JSON_THROW_ON_ERROR);if(!is_array($manifest)||($manifest['source_operation']??'')!=='hotel-match-anex-andromeda-core8-pair-scale-1971-20260911-v1'||(int)($manifest['country_id']??0)!==1)throw new RuntimeException('manifest_provenance');$rows=$manifest['rows']??[];if(!is_array($rows)||count($rows)!==75)throw new RuntimeException('manifest_row_count');
    [$hotels,$names]=mbr_catalog($db);$stats=['manifest_rows'=>75,'safe'=>0,'safe_anex'=>0,'safe_andromeda'=>0,'already_resolved'=>0,'deferred'=>0,'protected'=>0,'hard_conflict'=>0,'alias_corroborated'=>0,'geo_corroborated'=>0,'coordinate_conflict'=>0];$safe=[];$blocked=[];
    foreach($rows as$m){$a=(int)($m['a']??0);$d=(string)($m['d']??'');$target=(int)($m['l']??0);$keys=is_array($m['k']??null)?$m['k']:[];if($a<=0||$d===''||$target<=0||!isset($hotels[$target])){$stats['hard_conflict']++;$blocked[]=['a'=>$a,'d'=>$d,'l'=>$target,'bucket'=>'hard_conflict','reason'=>'manifest_target_missing_current'];continue;}$hotel=$hotels[$target];$aState=aalp_anex_state($db,$a);$dState=aalp_andromeda_state($db,$d);$aCtx=aalp_anex_context($db,$a);$dCtx=aalp_andromeda_context($db,$d,$dState['row']??null);$unresolved=($aState['state']??'')==='unresolved'?$aCtx:$dCtx;$countryOk=(int)$hotel['country_id']===1&&(!($unresolved['country_id']??0)||(int)$unresolved['country_id']===1);$aliasOk=aalp_alias_ok($keys,$unresolved['names']??[],$names[$target]??[$hotel['name']]);$guard=mbr_target_guard($unresolved['coord']??[],$hotel);$placeOk=fc_place(array_map('strval',$unresolved['places']??[]),[(string)$hotel['region_name'],(string)$hotel['subregion_name']]);$geoOk=$placeOk||($guard['distance_m']!==null&&$guard['distance_m']<=1000);$decision=aalp_decide($aState,$dState,$countryOk,$aliasOk,$geoOk,(bool)$guard['coordinate_conflict']);
        $bucket=$decision['bucket'];$stats[$bucket]=($stats[$bucket]??0)+1;if($aliasOk)$stats['alias_corroborated']++;if($geoOk)$stats['geo_corroborated']++;if($guard['coordinate_conflict'])$stats['coordinate_conflict']++;
        $base=['provider'=>$decision['provider']??null,'anex_hotel_id'=>$a,'andromeda_hotel_id'=>$d,'target_local_hotel_id'=>$target,'target_name'=>$hotel['name'],'target_region'=>$hotel['region_name'],'target_subregion'=>$hotel['subregion_name'],'manifest_aliases'=>$keys,'current_alias_corroborated'=>$aliasOk,'current_geo_corroborated'=>$geoOk,'distance_m'=>$guard['distance_m'],'anex_state'=>$aState['state']??null,'andromeda_state'=>$dState['state']??null,'reason'=>$decision['reason']??null];
        if($bucket==='safe'){$stats['safe_'.$decision['provider']]++;$safe[]=$base;}else$blocked[]=$base+['bucket'=>$bucket];
    }
    usort($safe,static fn($x,$y)=>strcmp((string)$x['provider'],(string)$y['provider'])?:$x['target_local_hotel_id']<=>$y['target_local_hotel_id']);
    return ['status'=>'completed','operation_id'=>$operation,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'historical_operations_replayed'=>false,'manifest'=>['source_operation'=>$manifest['source_operation'],'source_run_id'=>$manifest['source_run_id']??null,'source_artifact_id'=>$manifest['source_artifact_id']??null,'source_sha256'=>$manifest['source_sha256']??null,'rows'=>75],'coverage'=>fc_coverage($db),'stats'=>$stats,'safe_prepared_count'=>count($safe),'safe_prepared'=>$safe,'blocked'=>$blocked];
}
