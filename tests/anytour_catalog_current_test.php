<?php
declare(strict_types=1);
require_once __DIR__.'/../scripts/catalog/anytour_catalog_current.php';
$checksCurrent=0;
function currentCheck(bool $ok,string $label): void { global $checksCurrent; $checksCurrent++; if (!$ok) throw new RuntimeException($label); }
foreach (['mysql:host=localhost;dbname=anytour;charset=utf8mb4','mysql:dbname=anytour;port=3306'] as $dsn) currentCheck(anytour_current_database($dsn)==='anytour','configured database name');
foreach (['sqlite:file','mysql:host=localhost','mysql:dbname=a;dbname=b','mysql:dbname=','mysql:dbname=a b'] as $dsn) {
    $failed=false; try { anytour_current_database($dsn); } catch (RuntimeException) { $failed=true; }
    currentCheck($failed,'invalid/ambiguous DSN refused');
}
if (in_array('--unit-only',$argv,true)) { echo "ANYTOUR_CURRENT_UNIT_OK checks=$checksCurrent sql=NOT_RUN\n"; exit; }
// The existing real MySQL suite creates its OWN empty loopback fixture, not a live DB.
require __DIR__.'/anytour_catalog_preflight_mysql_test.php';
$beforeCurrent=legacyHash($pdo);
$catalogueBefore=$pdo->query('SELECT COUNT(*) FROM anytour_hotels')->fetchColumn();
$reportCurrent=anytour_current_collect($pdo);
currentCheck($reportCurrent['eligibleSavedProfiles']===1000,'all active named fixture hotels counted');
currentCheck($reportCurrent['preflight']['source_profiles']===1000,'complete bounded cohort');
currentCheck($reportCurrent['tables']['anytour_hotels']['rows']===1000,'actual initialized catalogue counted');
currentCheck($reportCurrent['tables']['anytour_hotel_rooms']['present']===false,'missing stay tables not invented');
currentCheck($reportCurrent['databaseWrites']===0 && !$reportCurrent['migrationAuthorized'],'inspection does not authorize migration');
currentCheck(legacyHash($pdo)===$beforeCurrent && $pdo->query('SELECT COUNT(*) FROM anytour_hotels')->fetchColumn()===$catalogueBefore,'source and canonical rows unchanged');
$serialized=AnyTourCanonicalCatalog::json($reportCurrent);
currentCheck(!str_contains($serialized,$dsn) && !str_contains($serialized,'Сохранённый отель') && !str_contains($serialized,'fixture-only-password'),'no credentials or hotel content exported');
$pdo->beginTransaction(); $nestedRejected=false;
try { anytour_current_collect($pdo); } catch (RuntimeException) { $nestedRejected=true; }
currentCheck($nestedRejected && $pdo->inTransaction(),'caller transaction not consumed'); $pdo->rollBack();
echo "ANYTOUR_CATALOG_CURRENT_TEST_OK checks=$checksCurrent real_mysql=1 live_database=0\n";
