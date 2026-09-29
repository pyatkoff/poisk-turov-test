<?php
declare(strict_types=1);

$path=__DIR__.'/../scripts/diagnostics/search3_destination_identity_current_census_v2.php';
$source=file_get_contents($path);
if($source===false)throw new RuntimeException('SOURCE_MISSING');

function ok(bool $value,string $message):void{if(!$value)throw new RuntimeException($message);}

ok(str_contains($source,"SET TRANSACTION ISOLATION LEVEL REPEATABLE READ"),'repeatable read missing');
ok(str_contains($source,"SET TRANSACTION READ ONLY"),'read only missing');
ok(str_contains($source,"'safeToApply' => false"),'apply must stay false');
ok(str_contains($source,"'databaseWrites' => 0"),'database write receipt missing');
ok(str_contains($source,"'mappingWrites' => 0"),'mapping write receipt missing');
ok(str_contains($source,"'providerCalls' => 0"),'provider call receipt missing');
ok(str_contains($source,"catalog_countries"),'country source missing');
ok(str_contains($source,"catalog_regions"),'region source missing');
ok(str_contains($source,"catalog_subregions"),'subregion source missing');
ok(str_contains($source,"anytour_destinations_v1"),'local destination table missing');
ok(str_contains($source,"anytour_destination_sources_v1"),'bridge table missing');

$sqlMutations=['INSERT ','UPDATE ','DELETE ','REPLACE ','ALTER ','CREATE ','DROP ','TRUNCATE '];
foreach($sqlMutations as $token){
    ok(stripos($source,$token)===false,'mutation token present: '.$token);
}

$forbiddenHttp=['curl_','file_get_contents("http','file_get_contents(\'http','https://','http://'];
foreach($forbiddenHttp as $token){
    ok(stripos($source,$token)===false,'http token present: '.$token);
}

ok(substr_count($source,'rollBack()')>=2,'rollback guards missing');
ok(str_contains($source,"if ((string)getenv('SEARCH3_DESTINATION_CENSUS') !== '1')"),'explicit execution guard missing');

echo "SEARCH3_DESTINATION_CURRENT_CENSUS_V2_CONTRACT_OK\n";
