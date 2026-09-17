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

    $active=[];
    foreach($db->query('SELECT id,is_active FROM anytour_hotels')->fetchAll(PDO::FETCH_ASSOC) as $row){
        $active[(int)$row['id']] = (int)$row['is_active'] === 1;
    }
    $legacy=[];$aliases=[];$direct=0;
    $sources=$db->query("SELECT namespace,CAST(external_key AS CHAR) AS external_key,anytour_hotel_id FROM anytour_hotel_sources WHERE namespace IN ('legacy_catalog','local_hotel_id','provider_ref_digest:andromeda')")->fetchAll(PDO::FETCH_ASSOC);
    foreach($sources as $row){
        $namespace=(string)$row['namespace'];
        if($namespace==='provider_ref_digest:andromeda'){++$direct;continue;}
        $key=(string)$row['external_key'];$own=(int)$row['anytour_hotel_id'];
        if(!preg_match('/\A[1-9][0-9]*\z/D',$key)||$own<1)continue;
        if($namespace==='legacy_catalog')$legacy[(int)$key]=$own;
        else $aliases[(int)$key]=$own;
    }

    $legacyWithout=0;$legacyExact=0;$legacyConflict=0;
    foreach($legacy as $local=>$own){
        if(!array_key_exists($local,$aliases)){++$legacyWithout;continue;}
        if($aliases[$local]===$own)++$legacyExact;else ++$legacyConflict;
    }

    $accepted=$db->query("SELECT local_hotel_id FROM andromeda_hotel_identities WHERE decision_status='accepted' AND local_hotel_id IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
    $acceptedAlias=0;$acceptedLegacyOnly=0;$acceptedMissing=0;
    foreach($accepted as $value){
        $local=(int)$value;if($local<1)continue;
        if(isset($aliases[$local])&&($active[$aliases[$local]]??false)){++$acceptedAlias;continue;}
        if(!array_key_exists($local,$aliases)&&isset($legacy[$local])&&($active[$legacy[$local]]??false)){++$acceptedLegacyOnly;continue;}
        if(!array_key_exists($local,$aliases)&&!isset($legacy[$local]))++$acceptedMissing;
    }

    $result=[
        'schema_version'=>1,
        'operation'=>'anytour-canonical-coverage-census',
        'mode'=>'read_only',
        'active_profiles'=>count(array_filter($active)),
        'all_profiles'=>count($active),
        'legacy_catalog_links'=>count($legacy),
        'local_alias_links'=>count($aliases),
        'direct_andromeda_links'=>$direct,
        'legacy_without_local_alias'=>$legacyWithout,
        'legacy_with_exact_local_alias'=>$legacyExact,
        'legacy_with_conflicting_local_alias'=>$legacyConflict,
        'accepted_andromeda_refs'=>count($accepted),
        'accepted_andromeda_with_local_alias'=>$acceptedAlias,
        'accepted_andromeda_legacy_only'=>$acceptedLegacyOnly,
        'accepted_andromeda_without_canonical'=>$acceptedMissing,
        'writes'=>0,'supplier_calls'=>0,'mapping_writes'=>0,
    ];
    $db->commit();
    echo json_encode($result,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n";
} catch(Throwable $e) {
    if(isset($db)&&$db instanceof PDO&&$db->inTransaction())$db->rollBack();
    fail_census('UNEXPECTED_'.preg_replace('/[^A-Z0-9_]/','_',strtoupper(get_class($e))));
}
