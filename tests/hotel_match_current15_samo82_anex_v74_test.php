<?php
declare(strict_types=1);
require_once __DIR__.'/../scripts/diagnostics/hotel_match_current15_samo82_anex_v74.php';
$count=0;
function check(mixed $got,mixed $want,string $name): void {global $count;$count++;if($got!==$want)throw new RuntimeException($name.':'.json_encode($got));}
function fixture(): array {
    return ['hotels'=>[42=>['id'=>42,'name'=>'Test hotel','country_name'=>'Турция','is_active'=>1]],'live'=>[42=>true],'samo_source'=>[],'samo_target'=>[],'mapping_source'=>[],'mapping_target'=>[],'manual_source'=>[],'manual_target'=>[],'excluded_source'=>[],'op5_source'=>[],'effective'=>['by_native'=>[100=>42],'by_local'=>[42=>[100=>true]]]];
}
function source(?int $id,string $decision='pending',bool $valid=true): array {return ['external_hotel_id'=>'99','local_hotel_id'=>$id,'decision_status'=>$decision,'evidence_valid'=>$valid];}
$c=fixture();check(a74_samo(42,'99',$c)['status'],'source_missing_needs_identity_proof','empty_not_ready');
$c['samo_source']['99']=[source(null)];check(a74_samo(42,'99',$c)['status'],'pending_unassigned_needs_identity_review','NULL_not_other_hotel');
check(a74_samo(42,'99',$c)['safe_to_write_now'],false,'pending_not_write_ready');
$c['samo_source']['99']=[source(42)];check(a74_samo(42,'99',$c)['status'],'pending_same_needs_identity_review','same_pending_not_accepted');
$c['samo_source']['99']=[source(42,'accepted')];check(a74_samo(42,'99',$c)['status'],'already_accepted_same','accepted_same');
$c['samo_source']['99']=[source(88,'accepted')];check(a74_samo(42,'99',$c)['status'],'hold','occupied_source');
check(in_array('source_other_target',a74_samo(42,'99',$c)['reasons'],true),true,'occupied_reason');
foreach(['conflict','rejected','unknown',''] as $state){$c['samo_source']['99']=[source(null,$state)];check(a74_samo(42,'99',$c)['status'],'hold','protected_'.$state);}
$c['samo_source']['99']=[source(null,'pending',false)];check(a74_samo(42,'99',$c)['status'],'hold','invalid_evidence');
$c['samo_source']['99']=[source(0)];check(a74_samo(42,'99',$c)['status'],'hold','zero_is_not_NULL');
$c['samo_source']['99']=[source(null),source(null)];check(a74_samo(42,'99',$c)['status'],'hold','duplicate_source');
$c=fixture();$c['samo_target'][42]=[['external_hotel_id'=>'1000']];check(a74_samo(42,'99',$c)['status'],'hold','other_target_catalog');
$c=fixture();$c['effective']['by_local']=[];check(a74_samo(42,'99',$c)['status'],'hold','missing_ANEX_anchor');
$c=fixture();$c['hotels'][42]['is_active']=0;check(a74_samo(42,'99',$c)['status'],'hold','inactive');
$c=fixture();$c['hotels'][42]['country_name']='Россия';check(a74_samo(42,'99',$c)['status'],'hold','Russia_excluded');
$c=fixture();$c['live']=[];check(a74_samo(42,'99',$c)['status'],'hold','stale_live30');
$c=fixture();$c['manual_target'][42]=[1];check(a74_samo(42,'99',$c)['status'],'hold','manual_target');
$c=fixture();check(a74_anex(42,'100',$c)['status'],'already_effective_same','canonical_accepted');
$c['effective']['by_native'][100]=999;check(a74_anex(42,'100',$c)['status'],'hold','canonical_other_target');
$c=fixture();unset($c['effective']['by_native'][100]);check(a74_anex(42,'100',$c)['status'],'source_missing_needs_identity_proof','free_slot_not_ready');
$c['op5_source']['100']=[source(42,'accepted')];check(a74_anex(42,'100',$c)['status'],'source_missing_needs_identity_proof','op5_not_direct_ANEX');
$c['op5_source']['100']=[source(999,'accepted')];check(a74_anex(42,'100',$c)['status'],'hold','op5_other_target');
$c=fixture();unset($c['effective']['by_native'][100]);$c['mapping_source']['100']=[['enabled'=>0]];check(a74_anex(42,'100',$c)['status'],'hold','disabled_mapping_protected');
$c=fixture();$c['manual_source']['100']=[1];check(a74_anex(42,'100',$c)['status'],'hold','manual_source_protected');
$c=fixture();$c['excluded_source']['100']=[1];check(a74_anex(42,'100',$c)['status'],'hold','exclusion');
$c=fixture();$c['mapping_target'][42]=[['anex_hotel_id'=>'101']];check(a74_anex(42,'100',$c)['status'],'hold','target_occupied');
check(count(A74_S15),15,'exact_SAMO_scope');check(A74_S15[1772],'37255','collision_retained');
$c=fixture();$c['hotels'][1772]=$c['hotels'][42];$c['live'][1772]=true;$c['effective']['by_local'][1772]=[101=>true];check(in_array('known_marhaba_target_collision',a74_samo(1772,'37255',$c)['reasons'],true),true,'MARHABA_not_promoted');
$raw=file_get_contents(__DIR__.'/../reports/hotel-match-retained-anex82-v74.json');$pairs=a74_pairs($raw);check(count($pairs),82,'exact_ANEX_scope');check(count(array_unique($pairs)),82,'native_unique');check($pairs[8928],'14355','first_pair');check($pairs[162479],'45123','last_pair');
try {a74_pairs($raw.' ');throw new Exception('hash_not_checked');}catch(RuntimeException $e){check($e->getMessage(),'manifest_hash','manifest_drift');}
$text=file_get_contents(__DIR__.'/../scripts/diagnostics/hotel_match_current15_samo82_anex_v74.php');
check(str_contains($text,'AnyTourMatchAnexEffectiveCoverage::fromPdo($db)'),true,'canonical_resolver_called');
check(str_contains($text,'catalog_hotel_id,decision_status FROM anex_hotel_search_mappings'),false,'wrong_schema_removed');
check(preg_match('/[\x27\x22](?:INSERT|UPDATE|DELETE|REPLACE|ALTER|DROP)\s/i',$text),0,'no_mutation_SQL');
check(str_contains($text,'START TRANSACTION READ ONLY'),true,'readonly_transaction');
check(str_contains($text,"'identity_proof_revalidated'=>false"),true,'honest_proof_boundary');
// Recording remains exclusive: a completed or partially started operation is not overwritten.
$dir=sys_get_temp_dir().'/a74-test-'.bin2hex(random_bytes(6));mkdir($dir);
$hash=a74_save($dir.'/result.json',['state'=>'test']);check(hash_file('sha256',$dir.'/result.json'),$hash,'durable_hash');
try{a74_save($dir.'/result.json',['state'=>'overwrite']);throw new Exception('replayed');}catch(RuntimeException $e){check($e->getMessage(),'exclusive_record','no_overwrite');}
unlink($dir.'/result.json');rmdir($dir);
echo "MATCH_CURRENT97_TESTS_PASS $count\n";
