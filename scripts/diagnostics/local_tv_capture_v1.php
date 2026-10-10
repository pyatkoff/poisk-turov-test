<?php
/** Read-only data snapshot for the permanent LOCAL capture installer. */
declare(strict_types=1);

final class LocalTvCaptureV1
{
    public static function snapshot(PDO $db): array
    {
        LocalTvSchemaV1::need(!$db->inTransaction(),'capture_nested_transaction');
        $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $db->exec('SET TRANSACTION READ ONLY');$db->beginTransaction();
        try {
            $inventory=LocalTvSeedV1::inventory($db);$read=LocalTvSeedV1::readback($db);$rows=[];
            foreach($db->query('SELECT * FROM local_tv_hotels ORDER BY id')as $r){
                $rows[(string)$r['id']]=hash('sha256',LocalTvCatalogV1::json($r));
            }
            $data=['database_sha256'=>$inventory['schema']['database_sha256'],
                'protected_snapshots'=>$inventory['images'],'readback'=>$read,'catalog_rows'=>$rows];
            $db->commit();return $data;
        } finally {if($db->inTransaction())$db->rollBack();}
    }
}

if(!defined('LOCAL_TV_CAPTURE_LIBRARY_ONLY')){
    define('LOCAL_TV_SCHEMA_LIBRARY_ONLY',true);define('LOCAL_TV_SEED_LIBRARY_ONLY',true);
    require __DIR__.'/local_tv_schema_v1.php';require __DIR__.'/local_tv_seed_v1.php';
    try {
        $home=(string)getenv('HOME');$root=(string)getenv('ANYTOUR_ROOT');$stage=(string)getenv('LOCAL_TV_CAPTURE_SOURCE_ROOT');
        $dir=(string)getenv('LOCAL_TV_CAPTURE_DIR');$name=$argv[1]??'';
        LocalTvSchemaV1::need(count($argv)===2 && in_array($name,['capture-before.json','capture-after.json'],true),'capture_snapshot_action');
        LocalTvSchemaV1::need($root===$home.'/www/anytoour.ru' && realpath($root)===$root && !is_link($root.'/config.php'),'capture_root');
        LocalTvSchemaV1::need(realpath($dir)===$dir && str_starts_with($dir,$home.'/.anytoour-int-executor/int-andromeda-local-tv-capture-'),'capture_private_root');
        $_SERVER['DOCUMENT_ROOT']=$root;
        require $stage.'/v2/data/db-v1.php';require $stage.'/v2/data/local-tv-catalog-v1.php';
        $data=LocalTvCaptureV1::snapshot(v2_data_db());$data['config_sha256']=hash_file('sha256',$root.'/config.php');
        LocalTvSchemaV1::save($dir,$name,$data);unset($data['catalog_rows']);
        echo LocalTvSchemaV1::json($data)."\n";exit(0);
    } catch(Throwable $e){echo LocalTvSchemaV1::json(['state'=>'blocked','error_class'=>get_class($e),'error_sha256'=>hash('sha256',$e->getMessage())])."\n";exit(1);}
}
