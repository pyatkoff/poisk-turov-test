<?php
declare(strict_types=1);

require_once __DIR__.'/../scripts/diagnostics/search3_destination_valid_seed_apply_v1.php';

function t(bool $value,string $message):void{if(!$value)throw new RuntimeException($message);}
function norm_sql(string $sql):string{
    $sql=preg_replace('/^\s*--.*$/m','',$sql)??$sql;
    $sql=preg_replace('/\s+/',' ',trim($sql))??trim($sql);
    return rtrim($sql,'; ');
}

$migration=file_get_contents(__DIR__.'/../v2/data/migrations/20260921-anytour-destination-identities-v1.sql');
t($migration!==false,'migration missing');
$canonical=array_values(array_filter(array_map('trim',preg_split('/;\s*(?:\r?\n|$)/',(string)$migration)?:[]),fn($s)=>$s!==''));
$writer=destination_schema_statements();
t(count($canonical)===2 && count($writer)===2,'schema statement count');
for($i=0;$i<2;$i++)t(norm_sql($canonical[$i])===norm_sql($writer[$i]),'writer DDL differs from canonical migration '.$i);

$dsn=(string)getenv('SEARCH3_DESTINATION_APPLY_TEST_DSN');
if($dsn===''){echo "SEARCH3_DESTINATION_APPLY_V1_STATIC_OK\n";exit(0);}
$db=new PDO(
    $dsn,
    (string)getenv('SEARCH3_DESTINATION_APPLY_TEST_USER'),
    (string)getenv('SEARCH3_DESTINATION_APPLY_TEST_PASSWORD'),
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]
);

$db->exec("CREATE TABLE catalog_countries (id BIGINT UNSIGNED PRIMARY KEY,name VARCHAR(255) NOT NULL,is_active TINYINT UNSIGNED NOT NULL)");
$db->exec("CREATE TABLE catalog_regions (id BIGINT UNSIGNED PRIMARY KEY,country_id BIGINT UNSIGNED NOT NULL,name VARCHAR(255) NOT NULL,is_active TINYINT UNSIGNED NOT NULL)");
$db->exec("CREATE TABLE catalog_subregions (id BIGINT UNSIGNED PRIMARY KEY,region_id BIGINT UNSIGNED NOT NULL,name VARCHAR(255) NOT NULL,is_active TINYINT UNSIGNED NOT NULL)");
$db->exec("INSERT INTO catalog_countries(id,name,is_active) VALUES(4,'Турция',1)");
$db->exec("INSERT INTO catalog_regions(id,country_id,name,is_active) VALUES(21,4,'Белек',1),(287,999,'Orphan Region',1)");
$db->exec("INSERT INTO catalog_subregions(id,region_id,name,is_active) VALUES(10,21,'Кадрие',1),(99,287,'Orphan Child',1)");

$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
$db->exec('SET TRANSACTION READ ONLY');
$db->beginTransaction();
$plan=destination_build_manifest_current($db);
$db->rollBack();

t($plan['plannedRows']===3,'fixture planned rows');
t($plan['plannedBridges']===3,'fixture planned bridges');
t($plan['validHierarchyCounts']===['countries'=>1,'regions'=>1,'subregions'=>1],'fixture valid hierarchy');
t($plan['excluded']['orphanRegionsMissingOrInactiveCountry']===1,'fixture orphan region');
t($plan['excluded']['subregionsUnderOrphanCountry']===1,'fixture orphan child');

$result=search3_destination_valid_seed_apply(
    $db,
    $plan['manifestSha256'],
    3,
    'fixture://search3-destination-plan',
    'fixture-owner'
);
t($result['state']==='committed_verified','fixture writer state');
t($result['schemaTablesCreated']===2,'fixture schema count');
t($result['destinationWrites']===3 && $result['mappingWrites']===3,'fixture write counts');
t($result['readbackVerified']===true,'fixture readback');
t($result['readback']['kindCounts']===['country'=>1,'region'=>1,'subregion'=>1],'fixture kind counts');
t($result['readback']['turkey']['regions']===1 && $result['readback']['turkey']['subregions']===1,'fixture turkey counts');

$accepted=(int)$db->query("SELECT COUNT(*) FROM anytour_destination_sources_v1 WHERE provider='tourvisor' AND state='accepted'")->fetchColumn();
t($accepted===3,'fixture accepted count');
$orphans=(int)$db->query("SELECT COUNT(*) FROM anytour_destination_sources_v1 WHERE external_id IN ('287','99')")->fetchColumn();
t($orphans===0,'orphan identities must not seed');
$copied=(int)$db->query("SELECT COUNT(*) FROM anytour_destination_sources_v1 WHERE CAST(external_id AS UNSIGNED)=anytour_destination_id")->fetchColumn();
t($copied===0,'source ids must not be assigned as local ids');

$blocked=false;
try{
    search3_destination_valid_seed_apply($db,$plan['manifestSha256'],3,'fixture://again','fixture-owner');
}catch(Throwable $e){
    $blocked=$e->getMessage()==='DESTINATION_TABLES_ALREADY_PRESENT';
}
t($blocked,'second apply must fail closed');

echo "SEARCH3_DESTINATION_APPLY_V1_MYSQL_OK\n";
