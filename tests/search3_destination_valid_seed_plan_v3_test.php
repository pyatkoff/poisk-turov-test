<?php
declare(strict_types=1);

$path=__DIR__.'/../scripts/diagnostics/search3_destination_valid_seed_plan_v3.php';
$source=file_get_contents($path);
if($source===false)throw new RuntimeException('SOURCE_MISSING');
function ok(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}

ok(str_contains($source,'SET TRANSACTION ISOLATION LEVEL REPEATABLE READ'),'repeatable read missing');
ok(str_contains($source,'SET TRANSACTION READ ONLY'),'read only missing');
ok(str_contains($source,"'safeToApply' => false"),'safeToApply must stay false');
ok(str_contains($source,"'guardedApplyCandidate'"),'guarded candidate signal missing');
ok(str_contains($source,"orphanRegionsMissingOrInactiveCountry"),'orphan region exclusion missing');
ok(str_contains($source,"subregionsMissingOrInactiveRegion"),'missing-parent subregion exclusion missing');
ok(str_contains($source,"subregionsUnderOrphanCountry"),'orphan-country subregion exclusion missing');
ok(str_contains($source,"manifestSha256"),'manifest hash missing');
ok(str_contains($source,"'provider' => 'tourvisor'"),'Tourvisor bridge plan missing');
ok(str_contains($source,"'databaseWrites' => 0"),'database write receipt missing');
ok(str_contains($source,"'mappingWrites' => 0"),'mapping write receipt missing');
ok(str_contains($source,"'providerCalls' => 0"),'provider call receipt missing');

foreach(['INSERT ','UPDATE ','DELETE ','REPLACE ','ALTER ','CREATE ','DROP ','TRUNCATE '] as $token){
    ok(stripos($source,$token)===false,'mutation token present: '.$token);
}
foreach(['https://','http://','curl_'] as $token){
    ok(stripos($source,$token)===false,'provider/http token present: '.$token);
}
ok(substr_count($source,'rollBack()')>=2,'rollback guards missing');
ok(str_contains($source,"SEARCH3_DESTINATION_SEED_PLAN') !== '1'"),'explicit execution guard missing');

echo "SEARCH3_DESTINATION_VALID_SEED_PLAN_V3_CONTRACT_OK\n";
