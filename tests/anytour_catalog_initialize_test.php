<?php
declare(strict_types=1);
require_once __DIR__.'/../scripts/catalog/anytour_catalog_initialize.php';
// Existing test enforces an empty named loopback-only MySQL fixture and seeds 1000.
require __DIR__.'/anytour_catalog_preflight_mysql_test.php';
$checksInit=0;
function initCheck(bool $ok,string $message): void {global $checksInit;$checksInit++;if(!$ok)throw new RuntimeException($message);}
function initFails(callable $fn,string $message): void {$failed=false;try{$fn();}catch(Throwable){$failed=true;}initCheck($failed,$message);}
function clearFixtureTargets(PDO $p): void {
    if ($p->query('SELECT DATABASE()')->fetchColumn()!=='anytour_preflight_fixture') throw new RuntimeException('Fixture cleanup boundary');
    foreach(array_reverse(ANYTOUR_INIT_TABLES) as $t)$p->exec("DROP TABLE IF EXISTS `$t`");
}
clearFixtureTargets($pdo);
$pdo->exec("INSERT INTO catalog_hotel_details(hotel_id,status,description,images_json)
    SELECT id,'success','Описание','[\"https://fixture.test/room.jpg\"]' FROM catalog_hotels WHERE id BETWEEN 10001 AND 11000
    ON DUPLICATE KEY UPDATE status='success',description='Описание',images_json='[\"https://fixture.test/room.jpg\"]'");
$proof=anytour_current_collect($pdo);$beforeInit=legacyHash($pdo);$eventsInit=[];
$checkpoint=static function(array $e)use(&$eventsInit):void{$eventsInit[]=$e;};
initCheck(count(anytour_init_statements())===10,'exact additive statement count');
foreach(anytour_init_statements()as$sql)initCheck(!str_contains($sql,'IF NOT EXISTS')&&!str_contains($sql,'INSERT IGNORE'),'strict create cannot adopt a raced table');
$bad=$proof;$bad['targetIdentitySha256']=str_repeat('0',64);
initFails(fn()=>anytour_initialize($pdo,$bad,$checkpoint),'different database rejected before DDL');
$bad=$proof;$bad['preflight']['source_sha256']=str_repeat('0',64);
initFails(fn()=>anytour_initialize($pdo,$bad,$checkpoint),'changed source rejected before DDL');
initCheck(count($pdo->query('SHOW TABLES')->fetchAll())===2,'failed preflight did not install anything');
initFails(fn()=>anytour_initialize($pdo,$proof,static function(array $e):void{
    if($e['status']==='ddl_verified'&&$e['statement']===3)throw new RuntimeException('fixture interrupted after actual DDL');
}),'partial DDL interruption is visible');
initCheck(count($pdo->query('SHOW TABLES')->fetchAll())===4,'partial installation is retained, not falsely rolled back');
initFails(fn()=>anytour_initialize($pdo,$proof,$checkpoint),'partial installation cannot replay');
initCheck(legacyHash($pdo)===$beforeInit,'failed DDL path never changed legacy data');
clearFixtureTargets($pdo);
$proof=anytour_current_collect($pdo);$eventsInit=[];
$res=anytour_initialize($pdo,$proof,$checkpoint);
initCheck($res['status']==='initialized_seeded_verified','actual installation and seed completed');
initCheck($res['counts']['anytour_hotels']===1000&&$res['counts']['anytour_hotel_sources']===1000,'own profiles and source links populated');
initCheck($res['counts']['anytour_meal_plans']===10&&$res['counts']['anytour_room_categories']===10,'local definitions installed');
initCheck($res['counts']['anytour_hotel_rooms']===0&&$res['counts']['anytour_stay_mappings']===0,'no real rooms/mappings fabricated');
initCheck($res['profilesWithDescription']===1000&&$res['profilesWithImages']===1000,'all selected content survives own-ID reads');
initCheck(legacyHash($pdo)===$beforeInit,'source data byte-identical after install and seed');
initCheck($res['samples'][0]['anytourId']!==$res['samples'][0]['legacyId'],'independent ID allocation, not copied legacy ID');
initCheck(count(array_filter($eventsInit,fn($e)=>$e['status']==='ddl_attempting'))===10,'all DDL attempts checkpointed');
initCheck(count(array_filter($eventsInit,fn($e)=>$e['status']==='ddl_verified'))===10,'all DDL completions checkpointed');
initFails(fn()=>anytour_initialize($pdo,$proof,$checkpoint),'completed installation cannot replay');
initCheck((int)$pdo->query('SELECT COUNT(*) FROM anytour_hotels')->fetchColumn()===1000,'repeat refusal does not duplicate profiles');
echo "ANYTOUR_INITIALIZE_TEST_OK checks=$checksInit real_mysql=1 actual_fixture_profiles=1000 live_database=0\n";
