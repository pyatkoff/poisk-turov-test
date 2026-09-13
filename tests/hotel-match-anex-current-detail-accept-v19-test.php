<?php
declare(strict_types=1);
require_once __DIR__ . '/../scripts/diagnostics/hotel_match_anex_current_detail_accept_v19.php';

function v19_assert(bool $ok,string $message): void { if(!$ok) throw new RuntimeException($message); }
function v19_throws(callable $fn,string $needle): void {
    try{$fn();}catch(Throwable $e){v19_assert(str_contains($e->getMessage(),$needle),'wrong exception '.$e->getMessage());return;}
    throw new RuntimeException('expected exception '.$needle);
}
$manifestPath=__DIR__.'/../.github/match-anex-current-detail-accept-1971-v19.manifest.json';
$m=json_decode((string)file_get_contents($manifestPath),true,64,JSON_THROW_ON_ERROR);
hmacav19_validate_manifest($m);
v19_assert(count($m['intents'])===22,'intent count');
$ids=array_map(static fn($x)=>(int)$x['anex_hotel_id'],$m['intents']);
$targets=array_map(static fn($x)=>(int)$x['target_local_hotel_id'],$m['intents']);
v19_assert(count(array_unique($ids))===22,'unique ids');
v19_assert(count(array_unique($targets))===22,'unique targets');
v19_assert(!in_array(28450,$ids,true),'unsafe Fortuna intent must stay excluded');
v19_assert(($m['explicitly_excluded'][0]['anex_hotel_id']??null)===28450,'Fortuna exclusion missing');
v19_assert(($m['explicitly_excluded'][0]['target_local_hotel_id']??null)===63682,'Fortuna target exclusion missing');

$bad=$m;$bad['intents'][1]['anex_hotel_id']=$bad['intents'][0]['anex_hotel_id'];
v19_throws(static fn()=>hmacav19_validate_manifest($bad),'manifest_intent_contract');
$bad=$m;$bad['intents'][1]['target_local_hotel_id']=$bad['intents'][0]['target_local_hotel_id'];
v19_throws(static fn()=>hmacav19_validate_manifest($bad),'manifest_intent_contract');
$bad=$m;$bad['explicitly_excluded']=[];
v19_throws(static fn()=>hmacav19_validate_manifest($bad),'manifest_exclusion_missing');

function v19_synthetic_lanes(array $m): array {
    $s=[];$c=[];foreach($m['intents'] as $i){$row=['anex_hotel_id'=>(int)$i['anex_hotel_id'],'target_local_hotel_id'=>(int)$i['target_local_hotel_id']];if($i['strong_rule']!==null)$s[]=$row+['rule'=>$i['strong_rule']];if($i['coordinate_rule']!==null)$c[]=$row+['rule'=>$i['coordinate_rule']];}return [['prepared'=>$s],['prepared'=>$c]];
}
[$allStrong,$allCoord]=v19_synthetic_lanes($m);$gate=hmacav19_review_gate($m,$allStrong,$allCoord);
v19_assert(count($gate)===22,'gate count');foreach($gate as $g)v19_assert($g['status']==='eligible','expected eligible');

$oneStrong=$allStrong;foreach($oneStrong['prepared'] as $k=>$r)if((int)$r['anex_hotel_id']===32880){unset($oneStrong['prepared'][$k]);break;}$oneStrong['prepared']=array_values($oneStrong['prepared']);
$gate=hmacav19_review_gate($m,$oneStrong,$allCoord);v19_assert($gate[32880]['status']==='withheld'&&$gate[32880]['reason']==='strong_evidence_missing','missing strong');
$oneCoord=$allCoord;foreach($oneCoord['prepared'] as $k=>$r)if((int)$r['anex_hotel_id']===23894){unset($oneCoord['prepared'][$k]);break;}$oneCoord['prepared']=array_values($oneCoord['prepared']);
$gate=hmacav19_review_gate($m,$allStrong,$oneCoord);v19_assert($gate[23894]['status']==='withheld'&&$gate[23894]['reason']==='coordinate_evidence_missing','missing coord');
$drift=$allStrong;foreach($drift['prepared'] as &$r)if((int)$r['anex_hotel_id']===32880){$r['target_local_hotel_id']=999999;break;}unset($r);
$gate=hmacav19_review_gate($m,$drift,$allCoord);v19_assert($gate[32880]['reason']==='strong_target_drift','strong drift');
// 16691 has both strong and coordinate lanes; make only the coordinate lane disagree.
$drift=$allCoord;foreach($drift['prepared'] as &$r)if((int)$r['anex_hotel_id']===16691){$r['target_local_hotel_id']=999999;break;}unset($r);
$gate=hmacav19_review_gate($m,$allStrong,$drift);v19_assert($gate[16691]['reason']==='lane_conflict','lane conflict');

$source=(string)file_get_contents(__DIR__.'/../scripts/diagnostics/hotel_match_anex_current_detail_accept_v19.php');
foreach(['REPEATABLE READ','FOR UPDATE','current_state_changed_after_review','manual_or_conflict_protected','existing_mapping_protected','pair_exclusion_protected','same_provider_target_occupied','andromeda_bridge_drift','live_observation_drift','post_commit_readback_failed','unknown_after_commit','INSERT INTO anex_hotel_search_mappings'] as $required)v19_assert(str_contains($source,$required),'missing static guard '.$required);
foreach(['curl_init','curl_exec','AnyTourAnexClient','v2_data_tv_get','UPDATE anex_hotel','DELETE FROM anex_hotel','REPLACE INTO anex_hotel'] as $forbidden)v19_assert(!str_contains($source,$forbidden),'forbidden source '.$forbidden);
v19_assert(($m['source_artifacts']['v16']['artifact_id']??0)===10311246616,'v16 artifact');
v19_assert(($m['source_artifacts']['v17']['artifact_id']??0)===10310629840,'v17 artifact');
v19_assert(($m['source_artifacts']['v18']['artifact_id']??0)===10311830210,'v18 artifact');
v19_assert(($m['source_artifacts']['v16']['result_sha256']??'')==='52818f55e75a599802d058408c9e43267d1c1928eaa1b04a36f398dcf9f8c45d','v16 result digest');
v19_assert(($m['source_artifacts']['v17']['result_sha256']??'')==='b2ee3e3f7c10341facc234391ebd80288847f774c79a249e3d41b637af371378','v17 result digest');
v19_assert(($m['source_artifacts']['v18']['result_sha256']??'')==='6552f9c70ce68ff866f4a594d1eece5e94cf8aa215bcbdb043341aa767e23a8a','v18 result digest');
echo "hotel-match-anex-current-detail-accept-v19-test: PASS\n";
