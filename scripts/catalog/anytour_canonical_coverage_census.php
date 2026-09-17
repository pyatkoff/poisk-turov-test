<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

function fail_census(string $code): never { fwrite(STDERR,"ANYTOUR_CANONICAL_CENSUS_FAILED code={$code}\n"); exit(1); }
function one(PDO $db,string $sql,array $params=[]): int { $q=$db->prepare($sql);$q->execute($params);$v=$q->fetchColumn();if($v===false)fail_census('QUERY');return(int)$v; }

$siteRoot=rtrim((string)getenv('ANYTOUR_SITE_ROOT'),"/\\");
$helper=$siteRoot!==''?$siteRoot.'/data/db-v1.php':__DIR__.'/../../v2/data/db-v1.php';
if(!is_file($helper))fail_census('DB_HELPER_MISSING');
require_once $helper;

try {
    $db=v2_data_db();
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    if($db->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql')fail_census('MYSQL_REQUIRED');
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $db->exec('SET TRANSACTION READ ONLY');
    $db->beginTransaction();

    $tables=one($db,"SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('anytour_hotels','anytour_hotel_sources','andromeda_hotel_identities') AND ENGINE='InnoDB'");
    if($tables!==3)fail_census('SCHEMA');

    $result=[
        'schema_version'=>1,
        'operation'=>'anytour-canonical-coverage-census',
        'mode'=>'read_only',
        'active_profiles'=>one($db,'SELECT COUNT(*) FROM anytour_hotels WHERE is_active=1'),
        'all_profiles'=>one($db,'SELECT COUNT(*) FROM anytour_hotels'),
        'legacy_catalog_links'=>one($db,"SELECT COUNT(*) FROM anytour_hotel_sources WHERE namespace='legacy_catalog'"),
        'local_alias_links'=>one($db,"SELECT COUNT(*) FROM anytour_hotel_sources WHERE namespace='local_hotel_id'"),
        'direct_andromeda_links'=>one($db,"SELECT COUNT(*) FROM anytour_hotel_sources WHERE namespace='provider_ref_digest:andromeda'"),
        'legacy_without_local_alias'=>one($db,"SELECT COUNT(*) FROM anytour_hotel_sources l LEFT JOIN anytour_hotel_sources a ON a.namespace='local_hotel_id' AND a.external_key=l.external_key WHERE l.namespace='legacy_catalog' AND a.id IS NULL"),
        'legacy_with_exact_local_alias'=>one($db,"SELECT COUNT(*) FROM anytour_hotel_sources l JOIN anytour_hotel_sources a ON a.namespace='local_hotel_id' AND a.external_key=l.external_key AND a.anytour_hotel_id=l.anytour_hotel_id WHERE l.namespace='legacy_catalog'"),
        'legacy_with_conflicting_local_alias'=>one($db,"SELECT COUNT(*) FROM anytour_hotel_sources l JOIN anytour_hotel_sources a ON a.namespace='local_hotel_id' AND a.external_key=l.external_key AND a.anytour_hotel_id<>l.anytour_hotel_id WHERE l.namespace='legacy_catalog'"),
        'accepted_andromeda_refs'=>one($db,"SELECT COUNT(*) FROM andromeda_hotel_identities WHERE decision_status='accepted' AND local_hotel_id IS NOT NULL"),
        'accepted_andromeda_with_local_alias'=>one($db,"SELECT COUNT(*) FROM andromeda_hotel_identities i JOIN anytour_hotel_sources a ON a.namespace='local_hotel_id' AND CAST(a.external_key AS CHAR)=CAST(i.local_hotel_id AS CHAR) JOIN anytour_hotels h ON h.id=a.anytour_hotel_id AND h.is_active=1 WHERE i.decision_status='accepted' AND i.local_hotel_id IS NOT NULL"),
        'accepted_andromeda_legacy_only'=>one($db,"SELECT COUNT(*) FROM andromeda_hotel_identities i JOIN anytour_hotel_sources l ON l.namespace='legacy_catalog' AND CAST(l.external_key AS CHAR)=CAST(i.local_hotel_id AS CHAR) JOIN anytour_hotels h ON h.id=l.anytour_hotel_id AND h.is_active=1 LEFT JOIN anytour_hotel_sources a ON a.namespace='local_hotel_id' AND a.external_key=l.external_key WHERE i.decision_status='accepted' AND i.local_hotel_id IS NOT NULL AND a.id IS NULL"),
        'accepted_andromeda_without_canonical'=>one($db,"SELECT COUNT(*) FROM andromeda_hotel_identities i LEFT JOIN anytour_hotel_sources l ON l.namespace='legacy_catalog' AND CAST(l.external_key AS CHAR)=CAST(i.local_hotel_id AS CHAR) LEFT JOIN anytour_hotel_sources a ON a.namespace='local_hotel_id' AND CAST(a.external_key AS CHAR)=CAST(i.local_hotel_id AS CHAR) WHERE i.decision_status='accepted' AND i.local_hotel_id IS NOT NULL AND l.id IS NULL AND a.id IS NULL"),
        'writes'=>0,
        'supplier_calls'=>0,
        'mapping_writes'=>0,
    ];
    $db->commit();
    echo json_encode($result,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n";
} catch(Throwable $e) {
    if(isset($db)&&$db instanceof PDO&&$db->inTransaction())$db->rollBack();
    fail_census('UNEXPECTED_'.preg_replace('/[^A-Z0-9_]/','_',strtoupper(get_class($e))));
}
