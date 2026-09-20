<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/scripts/diagnostics/hotel_match_anex_effective_coverage.php';
$n=0;
function check(bool $value,string $why):void {global $n;$n++;if(!$value)throw new RuntimeException($why);}
function row(int $native,int $local,string $policy='owner_exact_and_strong_20260908',string $class='exact'):array {
    return ['anex_hotel_id'=>$native,'catalog_hotel_id'=>$local,'existing_catalog_hotel_id'=>$local,'match_class'=>$class,'approval_policy'=>$policy,'enabled'=>1,'scope'=>'preview'];
}
function project(array $maps,array $decisions=[],array $exclusions=[]):array {
    $r=AnyTourAnexSearchMappingRegistry::fromRows($maps,$decisions,$exclusions);
    $ids=array_merge(array_column($maps,'anex_hotel_id'),array_column($decisions,'anex_hotel_id'));
    return AnyTourMatchAnexEffectiveCoverage::fromRegistry($r,$ids);
}
$policies=[
 ['owner_exact_and_strong_20260908','exact'],
 ['owner_exact_and_strong_20260908','strong_candidate'],
 ['owner_exact_operator_key_20260912','exact_operator_key'],
 ['owner_exact_operator_key_20260912_v2','exact_operator_key'],
 ['owner_coordinate_name_geo_rescue_20260912_v1','coordinate_name_geo'],
 ['owner_multi_evidence_consensus_20260912_v1','multi_evidence_consensus'],
 ['owner_current_exact_cross_provider_20260912','exact_cross_provider'],
];
foreach($policies as $i=>[$policy,$class]){
    $native=100+$i;$local=200+$i;$p=project([row($native,$local,$policy,$class)]);
    check(($p['by_native'][$native]??null)===$local,'supported_policy_'.$i);
}
$live=[row(8419,1478,'owner_exact_operator_key_20260912_v2','exact_operator_key'),row(18685,28460,'owner_exact_operator_key_20260912_v2','exact_operator_key')];
$p=project($live);check($p['local_count']===2&&isset($p['by_local'][1478],$p['by_local'][28460]),'live_false_missing_regression');
foreach([['enabled',0],['scope','production'],['approval_policy','owner_exact_operator_key_20260912_v3'],['match_class','strong_candidate'],['existing_catalog_hotel_id',null]] as [$field,$value]){
    $r=$live[0];$r[$field]=$value;check(project([$r])['native_count']===0,'reject_'.$field);
}
$r=row(1,10);$d=['anex_hotel_id'=>1,'catalog_hotel_id'=>10,'existing_catalog_hotel_id'=>10,'decision_status'=>'rejected'];
check(project([$r],[$d])['native_count']===0,'manual_block');
$d['decision_status']='accepted';$d['catalog_hotel_id']=20;$d['existing_catalog_hotel_id']=20;
check(project([$r],[$d])['by_native'][1]===20,'manual_redirect');
check(project([$r],[$d],[['anex_hotel_id'=>1,'catalog_hotel_id'=>20]])['native_count']===0,'exclusion_after_manual');
check(project([$r],[$d],[['anex_hotel_id'=>1,'catalog_hotel_id'=>10]])['by_native'][1]===20,'pair_local_exclusion');
$d['existing_catalog_hotel_id']=null;check(project([$r],[$d])['native_count']===0,'manual_missing_target');
$d=['anex_hotel_id'=>2,'catalog_hotel_id'=>30,'existing_catalog_hotel_id'=>30,'decision_status'=>'accepted'];
check(project([],[$d])['by_native'][2]===30,'manual_only_enumerated');
check(project([row(1,10),row(2,10)])['local_count']===1,'unique_local_not_mapping_sum');
$reg=AnyTourAnexSearchMappingRegistry::fromRows([row(1,10)]);
foreach([[],['bad'],[0],[1.0],array_fill(0,50001,1)] as $ids){
    $thrown=false;try{AnyTourMatchAnexEffectiveCoverage::fromRegistry($reg,$ids);}catch(UnexpectedValueException $e){$thrown=true;}check($thrown,'invalid_or_incomplete_enumeration');
}
check(AnyTourMatchAnexEffectiveCoverage::fromRegistry($reg,[1,'1'])['native_count']===1,'dedup_enum');
check($reg->resolve('anex_online','1','production')===null,'production_unchanged');
if(($argv[1]??'')==='--rows-only'){echo "MATCH_ANEX_EFFECTIVE_COVERAGE_ROWS_OK {$n}\n";exit;}
// Exercise actual PDO enumeration + canonical SQL in memory, no external database.
if(!in_array('sqlite',PDO::getAvailableDrivers(),true))throw new RuntimeException('sqlite_required');
$db=new PDO('sqlite::memory:');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE catalog_hotels(id INTEGER PRIMARY KEY); CREATE TABLE anex_hotel_search_mappings(anex_hotel_id INTEGER,catalog_hotel_id INTEGER,match_class TEXT,approval_policy TEXT,enabled INTEGER,scope TEXT); CREATE TABLE anex_hotel_decisions(anex_hotel_id INTEGER,catalog_hotel_id INTEGER,decision_status TEXT); CREATE TABLE anex_review_pair_exclusions(anex_hotel_id INTEGER,catalog_hotel_id INTEGER);');
$db->exec("INSERT INTO catalog_hotels VALUES(1478),(28460),(30); INSERT INTO anex_hotel_search_mappings VALUES(8419,1478,'exact_operator_key','owner_exact_operator_key_20260912_v2',1,'preview'),(18685,28460,'exact_operator_key','owner_exact_operator_key_20260912_v2',1,'preview'); INSERT INTO anex_hotel_decisions VALUES(77,30,'accepted');");
$db->beginTransaction();$before=$db->query('SELECT COUNT(*) FROM anex_hotel_search_mappings')->fetchColumn();$p=AnyTourMatchAnexEffectiveCoverage::fromPdo($db);
check($p['native_count']===3&&$p['local_count']===3,'pdo_all_classes_plus_manual');
check($db->query('SELECT COUNT(*) FROM anex_hotel_search_mappings')->fetchColumn()===$before,'pdo_no_writes');$db->rollBack();
echo "MATCH_ANEX_EFFECTIVE_COVERAGE_OK {$n}\n";
