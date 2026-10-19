<?php
declare(strict_types=1);

const HSA2_OP='hotel-match-hotelsu1244-current-acceptance-audit-1971-20260920-v2';
const HSA2_DELTA_SHA='9dc3c7246d545915cd311a6175bcbeb04b58feebdafd0e83d54978fca4deae69';
const HSA2_EVIDENCE_SHA='26f8ab7ba5dcf9e81287a83e3d2c2e1f3971d8a0102a57103f3498a382d7bc0b';
const HSA2_SCHEMA_SHA='bfe3c0c5b3ceda32e6a85b92e9fe77affb2e981cf42dda09b4842c88f8f8f52a';
const HSA2_LOCAL=1244;
const HSA2_ANEX=15072;
const HSA2_SAMO='2000032277';
const HSA2_COUNTRY=4;
const HSA2_REGION=20;
const HSA2_OPERATOR=13;

function hsa2_need(bool $ok,string $reason):void{if(!$ok)throw new RuntimeException($reason);}
function hsa2_json(array $v):string{return json_encode($v,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";}
function hsa2_save(string $p,array $v):string{$b=hsa2_json($v);$f=fopen($p,'xb');hsa2_need(is_resource($f),'immutable_output');try{hsa2_need(fwrite($f,$b)===strlen($b)&&fflush($f),'output_write');if(function_exists('fsync'))hsa2_need(fsync($f),'output_sync');}finally{fclose($f);}hsa2_need(file_get_contents($p)===$b,'output_readback');return hash('sha256',$b);}
function hsa2_load(string $p,string $sha):array{hsa2_need(hash_file('sha256',$p)===$sha,'input_hash');$v=json_decode((string)file_get_contents($p),true,128,JSON_THROW_ON_ERROR);hsa2_need(is_array($v),'input_shape');return$v;}
function hsa2_rows(PDO $db,string $sql,array $params=[],int $cap=50000):array{$q=$db->prepare($sql);$q->execute(array_values($params));$r=$q->fetchAll(PDO::FETCH_ASSOC);hsa2_need(count($r)<=$cap,'row_cap');return$r;}
function hsa2_norm(string $s):string{$s=function_exists('mb_strtolower')?mb_strtolower(trim($s),'UTF-8'):strtolower(trim($s));$s=str_replace('ё','е',$s);return trim(preg_replace('/[^\p{L}\p{N}]+/u',' ',$s)??'');}
function hsa2_has_phrase(string $s,string $phrase):bool{$n=' '.hsa2_norm($s).' ';$p=' '.hsa2_norm($phrase).' ';return str_contains($n,$p);}
function hsa2_hash(array $v):string{return hash('sha256',json_encode($v,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));}
function hsa2_identity_conflict(array $rows,string $candidate,int $local):array{$conf=[];foreach($rows as$r){$status=(string)($r['decision_status']??'');$rid=(string)($r['external_hotel_id']??'');$lid=$r['local_hotel_id']===null?null:(int)$r['local_hotel_id'];if($status==='accepted'&&(($rid===$candidate&&$lid!==$local)||($lid===$local&&$rid!==$candidate)))$conf[]=$r;}return$conf;}
function hsa2_place_match(string $town,array $target):bool{$t=hsa2_norm($town);if($t==='')return false;foreach(['region_name','subregion_name']as$k){$v=hsa2_norm((string)($target[$k]??''));if($v!==''&&($v===$t||str_contains(' '.$v.' ',' '.$t.' ')||str_contains(' '.$t.' ',' '.$v.' ')))return true;}return false;}

if(($argv[1]??'')==='--self-test'){
    hsa2_need(hsa2_has_phrase('Sunis Hotel Su','hotel su'),'phrase_source');
    hsa2_need(hsa2_has_phrase('HOTEL SU (EX. SU & AQUALAND; HILLSIDE SU)','hotel su'),'phrase_target');
    hsa2_need(!hsa2_place_match('Коньяалты',['region_name'=>'Анталья','subregion_name'=>'Анталия-центр']),'place_conservative');
    $x=[['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>'99','decision_status'=>'accepted','local_hotel_id'=>1244]];
    hsa2_need(count(hsa2_identity_conflict($x,HSA2_SAMO,HSA2_LOCAL))===1,'occupancy_conflict');
    echo "MATCH_HOTELSU1244_CURRENT_ACCEPTANCE_AUDIT_V2_SELFTEST_OK 4\n";exit(0);
}

hsa2_need(PHP_SAPI==='cli'&&(string)getenv('MATCH_OPERATION_ID')===HSA2_OP,'operation_guard');
$dir=rtrim((string)getenv('HOME'),'/').'/.anytoour-match/operations/'.HSA2_OP;
hsa2_need(realpath((string)getenv('MATCH_OPERATION_DIR'))===$dir,'operation_directory');
$sourceSha=(string)getenv('MATCH_SOURCE_SHA');hsa2_need(preg_match('/^[0-9a-f]{40}$/D',$sourceSha)===1,'source_sha');
$res=json_decode((string)file_get_contents($dir.'/reservation.json'),true,64,JSON_THROW_ON_ERROR);
hsa2_need(($res['operation_id']??'')===HSA2_OP&&($res['source_sha']??'')===$sourceSha&&($res['state']??'')==='reserved_before_db_read','reservation_guard');
$base=['operation_id'=>HSA2_OP,'source_sha'=>$sourceSha,'local_hotel_id'=>HSA2_LOCAL,'anex_native_id'=>HSA2_ANEX,'samo_hotel_id'=>HSA2_SAMO,'supplier_calls'=>0,'provider_calls'=>0,'tourvisor_calls'=>0,'samo_calls'=>0,'andromeda_calls'=>0,'direct_anex_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'quota_mutations'=>0,'safe_to_write_now'=>false,'no_replay'=>true];$db=null;
try{
    hsa2_save($dir.'/execution-reservation.json',$base+['state'=>'started_before_db_read']);
    $delta=hsa2_load($dir.'/input/delta-result.json',HSA2_DELTA_SHA);
    $evidence=hsa2_load($dir.'/input/hotelsu-evidence.json',HSA2_EVIDENCE_SHA);
    $schema=hsa2_load($dir.'/input/schema-result.json',HSA2_SCHEMA_SHA);
    hsa2_need(($schema['state']??'')==='completed_read_only'&&($schema['root_cause']??'')==='invalid_order_by_id_columns','schema_proof_state');
    hsa2_need(($schema['id_column_present']['anex_hotel_decisions']??true)===false&&($schema['id_column_present']['anex_review_pair_exclusions']??true)===false,'schema_proof_columns');
    hsa2_need(($delta['state']??'')==='completed_read_only','delta_state');
    $dossiers=array_values(array_filter($delta['dossiers']??[],static fn($r)=>is_array($r)&&(int)($r['local_hotel_id']??0)===HSA2_LOCAL));
    hsa2_need(count($dossiers)===1,'delta_target_unique');$prior=$dossiers[0];
    hsa2_need(($prior['anex_native_ids']??[])===[HSA2_ANEX]&&($prior['state']??'')==='new_seed_missing_samo_edge_needs_evidence_dedupe','delta_target_scope');
    $cands=array_values(array_filter($evidence['all_turkey_su_token_rows']??[],static fn($r)=>is_array($r)&&(string)($r['id']??'')===HSA2_SAMO));
    hsa2_need(count($cands)>=1,'saved_candidate_missing');$candidate=$cands[0];
    foreach($cands as$c){hsa2_need((string)$c['name']===(string)$candidate['name']&&(string)$c['town']===(string)$candidate['town']&&(string)$c['star']===(string)$candidate['star'],'saved_candidate_drift');}
    hsa2_need((string)$candidate['star']==='5'&&hsa2_norm((string)$candidate['state'])===hsa2_norm('Турция'),'saved_candidate_country_star');
    $op5=$candidate['operator5']??[];hsa2_need(is_array($op5)&&count($op5)===1&&(int)($op5[0]['id']??0)===5&&hsa2_norm((string)($op5[0]['name']??''))===hsa2_norm('Anex Tour'),'saved_operator5_fact');

    require_once dirname(__DIR__,2).'/app/integrations/anex-search-mapping-registry.php';
    $root=realpath(getcwd());hsa2_need(is_string($root)&&basename($root)==='anytoour.ru','root_guard');
    require_once(is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php');
    $db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    foreach(['catalog_hotels','andromeda_hotel_identities','anex_hotel_search_mappings','anex_hotel_decisions','anex_review_pair_exclusions','tour_operator_identity_observations','tour_price_observations']as$t){$e=hsa2_rows($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$t]);hsa2_need(count($e)===1&&strtoupper((string)$e[0]['ENGINE'])==='INNODB','table_engine_'.$t);}
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
    $clock=hsa2_rows($db,'SELECT NOW() AS database_now,UTC_TIMESTAMP() AS utc_now,@@session.time_zone AS session_time_zone')[0];
    $target=hsa2_rows($db,'SELECT * FROM catalog_hotels WHERE id=?',[HSA2_LOCAL]);
    $anexRows=hsa2_rows($db,'SELECT * FROM anex_hotel_search_mappings WHERE anex_hotel_id=? OR catalog_hotel_id=? ORDER BY anex_hotel_id,catalog_hotel_id',[HSA2_ANEX,HSA2_LOCAL]);
    $manual=hsa2_rows($db,'SELECT * FROM anex_hotel_decisions WHERE anex_hotel_id=? OR catalog_hotel_id=? ORDER BY anex_hotel_id,catalog_hotel_id',[HSA2_ANEX,HSA2_LOCAL]);
    $excluded=hsa2_rows($db,'SELECT * FROM anex_review_pair_exclusions WHERE anex_hotel_id=? OR catalog_hotel_id=? ORDER BY anex_hotel_id,catalog_hotel_id',[HSA2_ANEX,HSA2_LOCAL]);
    $idRows=hsa2_rows($db,"SELECT * FROM andromeda_hotel_identities WHERE (supplier_namespace IN ('andromeda_catalog','operator_5')) AND (external_hotel_id=? OR local_hotel_id=?) ORDER BY supplier_namespace,external_hotel_id",[HSA2_SAMO,HSA2_LOCAL]);
    $sourceRows=array_values(array_filter($idRows,static fn($r)=>(string)($r['external_hotel_id']??'')===HSA2_SAMO));
    $targetRows=array_values(array_filter($idRows,static fn($r)=>$r['local_hotel_id']!==null&&(int)$r['local_hotel_id']===HSA2_LOCAL));
    $identityConflicts=hsa2_identity_conflict($idRows,HSA2_SAMO,HSA2_LOCAL);
    $obs=hsa2_rows($db,'SELECT * FROM tour_operator_identity_observations WHERE hotel_id=? AND operator_id=? ORDER BY last_seen_at DESC,id DESC LIMIT 200',[HSA2_LOCAL,HSA2_OPERATOR],200);
    $prices=hsa2_rows($db,"SELECT id,source,search_id,tour_id,hotel_id,departure_id,country_id,region_id,subregion_id,departure_date,nights,adults,children_count,child_ages_signature,operator_id,observed_at FROM tour_price_observations WHERE source='user_search' AND hotel_id=? AND operator_id=? ORDER BY observed_at DESC,id DESC LIMIT 200",[HSA2_LOCAL,HSA2_OPERATOR],200);
    $country=hsa2_rows($db,"SELECT id,country_id,region_id,region_name,subregion_id,subregion_name,name,normalized_name,category,latitude,longitude,is_active FROM catalog_hotels WHERE country_id=? AND is_active=1 AND (LOWER(name) LIKE '%hotel%su%' OR normalized_name LIKE '%hotel su%') ORDER BY id",[HSA2_COUNTRY],5000);
    $phraseTargets=array_values(array_filter($country,static fn($r)=>hsa2_has_phrase((string)($r['name']??''),'hotel su')||hsa2_has_phrase((string)($r['normalized_name']??''),'hotel su')));
    $resolver=AnyTourAnexSearchMappingRegistry::fromPdo($db);$anexResolved=$resolver->resolve('anex_online',(string)HSA2_ANEX,'preview');
    $db->rollBack();$db=null;

    $reasons=[];$t=count($target)===1?$target[0]:[];$alreadySame=false;
    foreach($sourceRows as$r)if(($r['decision_status']??'')==='accepted'&&$r['local_hotel_id']!==null&&(int)$r['local_hotel_id']===HSA2_LOCAL)$alreadySame=true;
    if(count($target)!==1||(int)($t['is_active']??0)!==1||(int)($t['country_id']??0)!==HSA2_COUNTRY||(int)($t['region_id']??0)!==HSA2_REGION)$reasons[]='target_active_country_region_conflict';
    if((int)($t['category']??0)!==5)$reasons[]='target_category_conflict';
    if($anexResolved!==HSA2_LOCAL)$reasons[]='canonical_anex_resolution_drift';
    if($manual!==[])$reasons[]='manual_decision_present';
    if($excluded!==[])$reasons[]='pair_exclusion_present';
    if($identityConflicts!==[])$reasons[]='source_or_target_identity_occupancy_conflict';
    if(count($phraseTargets)!==1||(int)($phraseTargets[0]['id']??0)!==HSA2_LOCAL)$reasons[]='hotel_su_phrase_not_unique_current_target';
    if(!hsa2_has_phrase((string)$candidate['name'],'hotel su')||!hsa2_has_phrase((string)($t['name']??''),'hotel su'))$reasons[]='saved_name_phrase_not_shared';
    $place=hsa2_place_match((string)$candidate['town'],$t);if(!$place)$reasons[]='current_direct_place_corroboration_missing';
    if((int)$candidate['star']!==(int)($t['category']??0))$reasons[]='saved_star_conflict';
    if($prices===[])$reasons[]='current_anex_user_search_observation_missing';
    $safe=!$alreadySame&&$reasons===[];
    $result=$base+['state'=>'completed_read_only','clock'=>$clock,'saved_candidate'=>$candidate,'prior_dossier_sha256'=>hsa2_hash($prior),'target_rows'=>$target,'target_state_sha256'=>count($target)===1?hsa2_hash($target[0]):null,'current_anex_resolution'=>$anexResolved,'current_anex_rows'=>$anexRows,'current_anex_rows_sha256'=>hsa2_hash($anexRows),'current_manual_rows'=>$manual,'current_exclusion_rows'=>$excluded,'current_identity_rows'=>$idRows,'source_identity_rows'=>$sourceRows,'target_identity_rows'=>$targetRows,'identity_conflicts'=>$identityConflicts,'identity_state_sha256'=>hsa2_hash($idRows),'current_operator13_identity_rows'=>$obs,'current_operator13_identity_rows_sha256'=>hsa2_hash($obs),'current_user_search_rows'=>$prices,'current_user_search_rows_sha256'=>hsa2_hash($prices),'country_hotel_su_candidates'=>$phraseTargets,'current_name_phrase_unique'=>(count($phraseTargets)===1&&(int)($phraseTargets[0]['id']??0)===HSA2_LOCAL),'current_direct_place_confirmed'=>$place,'saved_star_agreement'=>((int)$candidate['star']===(int)($t['category']??0)),'already_same'=>$alreadySame,'safe_to_accept_candidate_now'=>$safe,'acceptance_reasons'=>$reasons,'next_evidence_needed'=>$safe||$alreadySame?null:'independent_current_supplier_date_or_geo_alias_proof_for_samo_2000032277','safe_to_write_now'=>false];
}catch(Throwable$e){if($db instanceof PDO&&$db->inTransaction())$db->rollBack();$m=$e->getMessage();$result=$base+['state'=>'failed_no_replay','reason'=>preg_match('/^[a-z0-9_]+$/D',$m)?$m:'database_or_runtime_error','error_class'=>get_class($e),'safe_to_accept_candidate_now'=>false,'safe_to_write_now'=>false];}
$hash=hsa2_save($dir.'/result.json',$result);
hsa2_save($dir.'/receipt.json',['operation_id'=>HSA2_OP,'source_sha'=>$sourceSha,'state'=>$result['state'],'result_sha256'=>$hash,'readback_verified'=>hash_file('sha256',$dir.'/result.json')===$hash,'supplier_calls'=>0,'provider_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'quota_mutations'=>0,'no_replay'=>true]);
echo hsa2_json(['state'=>$result['state'],'already_same'=>$result['already_same']??false,'safe_to_accept_candidate_now'=>$result['safe_to_accept_candidate_now']??false,'acceptance_reasons'=>$result['acceptance_reasons']??null,'current_anex_resolution'=>$result['current_anex_resolution']??null,'current_direct_place_confirmed'=>$result['current_direct_place_confirmed']??null,'identity_conflict_count'=>count($result['identity_conflicts']??[]),'country_hotel_su_candidates'=>array_map(static fn($r)=>['id'=>$r['id']??null,'name'=>$r['name']??null,'region_name'=>$r['region_name']??null,'subregion_name'=>$r['subregion_name']??null,'category'=>$r['category']??null],$result['country_hotel_su_candidates']??[]),'next_evidence_needed'=>$result['next_evidence_needed']??null,'result_sha256'=>$hash]);
exit($result['state']==='completed_read_only'?0:1);
