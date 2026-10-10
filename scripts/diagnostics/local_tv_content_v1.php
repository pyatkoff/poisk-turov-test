<?php
/** One bounded new-target fill. Old legacy edges and known absent sources are excluded. */
declare(strict_types=1);

final class LocalTvContentV1
{
    public const CAP=200;

    public static function plan(PDO $db,array $scope,string $directory,string $now): array
    {
        LocalTvSchemaV1::need(!$db->inTransaction(),'content_nested_transaction');
        $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('SET TRANSACTION READ ONLY');$db->beginTransaction();
        try{
            $inventory=LocalTvSeedV1::inventory($db);$read=LocalTvSeedV1::readback($db);
            $row=$db->prepare('SELECT * FROM local_tv_hotels WHERE id=?');
            $edge=$db->prepare("SELECT 1 FROM anytour_hotel_sources WHERE namespace='legacy_catalog' AND CAST(external_key AS UNSIGNED)=? LIMIT 1");
            $cache=$db->prepare('SELECT * FROM catalog_hotel_details WHERE hotel_id=?');
            $eligible=[];$excluded=[];$statuses=[];$seen=[];$cacheHashes=[];
            $retryCutoff=(new DateTimeImmutable($now,new DateTimeZone('UTC')))->modify('-1 day')->format('Y-m-d H:i:s');
            foreach($scope['pending'] as $p){
                $id=$p['id'];LocalTvSchemaV1::need(is_int($id) && $id>0 && !isset($seen[$id]),'content_scope_ids');$seen[$id]=true;
                $row->execute([$id]);$r=$row->fetch(PDO::FETCH_ASSOC);$edge->execute([$id]);
                $cache->execute([$id]);$d=$cache->fetch(PDO::FETCH_ASSOC);
                $status=$d['status']??'missing';$statuses[$status][]=$id;
                $reason=null;
                if(!$r || $r['state']==='ready' || (int)$r['revision']!==$p['revision'])$reason='target_changed';
                elseif($edge->fetchColumn() || $p['old_local_ids']!==[])$reason='old_legacy_edge';
                elseif(!in_array($p['retained_reason'],['cache_missing','raw_missing'],true))$reason='not_an_admitted_missing_source';
                elseif(($d['source_hash']??null)!==$p['retained_sha256'] || ($d['fetched_at']??null)!==$p['retained_fetched_at'])$reason='cache_changed';
                elseif($status==='not_found' && is_string($d['fetched_at']??null) && $r['last_seen_at']<=$d['fetched_at'])$reason='known_not_found_without_new_observation';
                elseif($status==='failure' && is_string($d['fetched_at']??null) && $d['fetched_at']>$retryCutoff)$reason='recent_failure';
                if($reason!==null){$excluded[$reason][]=$id;continue;}
                $eligible[]=$r;$cacheHashes[$id]=hash('sha256',LocalTvCatalogV1::json($d?:null));LocalTvSchemaV1::need(count($eligible)<=self::CAP,'content_review_bound');
            }
            ksort($excluded);ksort($statuses);
            $plan=['eligible'=>$eligible,'cache_sha256'=>$cacheHashes,'excluded'=>$excluded,'source_status_ids'=>$statuses,'database_sha256'=>$inventory['schema']['database_sha256'],'protected_snapshots'=>$inventory['images'],'before'=>$read];
            LocalTvSchemaV1::save($directory,'content-plan.json',$plan);$db->commit();return $plan;
        }finally{if($db->inTransaction())$db->rollBack();}
    }

    public static function summary(array $plan): array
    {
        return ['eligible'=>count($plan['eligible']),'eligible_ids'=>array_map(static fn($r)=>(int)$r['id'],$plan['eligible']),
            'excluded'=>$plan['excluded'],'source_status_ids'=>$plan['source_status_ids'],'database_sha256'=>$plan['database_sha256'],'protected_snapshots'=>$plan['protected_snapshots'],'before'=>$plan['before']];
    }

    public static function fill(PDO $db,array $plan,string $directory,string $now,callable $fetch): array
    {
        $lock=(int)$db->query("SELECT GET_LOCK('anytour-local-tv-daily',0)")->fetchColumn();LocalTvSchemaV1::need($lock===1,'collector_active');
        $started=false;$result=['selected'=>count($plan['eligible']),'attempted'=>0,'filled'=>0,'state_updates'=>0,'errors'=>[],'stopped'=>false,'samples'=>[]];
        try{
            LocalTvSchemaV1::need(count($plan['eligible'])<=self::CAP,'content_review_bound');
            LocalTvSchemaV1::need(LocalTvSeedV1::readback($db)===$plan['before'],'content_catalog_changed');
            $inventory=LocalTvSeedV1::inventory($db);
            LocalTvSchemaV1::need($inventory['schema']['database_sha256']===$plan['database_sha256'],'content_database_changed');
            LocalTvSchemaV1::need($inventory['images']===$plan['protected_snapshots'],'content_protected_source_changed');
            LocalTvSchemaV1::save($directory,'content-before.json',$plan);
            $check=$db->prepare('SELECT * FROM local_tv_hotels WHERE id=?');
            $edge=$db->prepare("SELECT 1 FROM anytour_hotel_sources WHERE namespace='legacy_catalog' AND CAST(external_key AS UNSIGNED)=? LIMIT 1");
            $cache=$db->prepare('SELECT * FROM catalog_hotel_details WHERE hotel_id=?');
            $c=new LocalTvCatalogV1($db);$consecutive=0;
            foreach($plan['eligible'] as $before){
                $id=(int)$before['id'];$check->execute([$id]);LocalTvSchemaV1::need($check->fetch(PDO::FETCH_ASSOC)===$before,'content_target_changed');
                $edge->execute([$id]);LocalTvSchemaV1::need(!$edge->fetchColumn(),'content_legacy_edge_appeared');
                $cache->execute([$id]);LocalTvSchemaV1::need(hash('sha256',LocalTvCatalogV1::json($cache->fetch(PDO::FETCH_ASSOC)?:null))===$plan['cache_sha256'][$id],'content_cache_changed');
                LocalTvSchemaV1::save($directory,'hotel-'.$id.'-started.json',['id'=>$id,'revision'=>(int)$before['revision'],'max_attempts'=>1]);
                $started=true;$result['attempted']++;
                try{
                    $payload=$fetch($id);LocalTvSchemaV1::need(is_array($payload),'invalid_source_response');
                    LocalTvSchemaV1::save($directory,'hotel-'.$id.'-source.json',$payload);
                    LocalTvCatalogV1::normalize($payload,$id);$saved=$c->saveSource($id,$payload,$now);
                    $check->execute([$id]);$after=$check->fetch(PDO::FETCH_ASSOC);
                    LocalTvSchemaV1::need($after['manual_json']===$before['manual_json'],'content_manual_changed');
                    LocalTvSchemaV1::save($directory,'hotel-'.$id.'-result.json',$saved);
                    $result['filled']++;$consecutive=0;
                    if(count($result['samples'])<3)$result['samples'][]=['id'=>$id,'revision'=>$saved['revision'],'photos'=>count($c->read([$id])['items'][0]['images']??[])];
                }catch(Throwable $e){
                    if(in_array($e->getMessage(),['source_commit_unknown','post_commit_readback_unknown','content_manual_changed'],true))throw $e;
                    $message=$e->getMessage();$code=preg_match('/Tourvisor HTTP ([0-9]{3}) /',$message,$m)?'http_'.$m[1]:'source_error';
                    LocalTvSchemaV1::save($directory,'hotel-'.$id.'-error.json',['id'=>$id,'class'=>get_class($e),'error_sha256'=>hash('sha256',$message),'code'=>$code]);
                    // No old cache/profile writer. An unknown state UPDATE also stops the batch.
                    $c->fail($id,$code,$now);$result['state_updates']++;$result['errors'][(string)$id]=$code;$consecutive++;
                    if(in_array($code,['http_401','http_403','http_429'],true) || $consecutive>=3){$result['stopped']=true;break;}
                }
            }
            $after=LocalTvSeedV1::inventory($db);LocalTvSchemaV1::need($after['images']===$plan['protected_snapshots'],'content_old_bytes_changed');
            $result['readback']=LocalTvSeedV1::readback($db);$result['deferred']=$result['selected']-$result['attempted'];
            LocalTvSchemaV1::need($result['readback']['links_sha256']===$plan['before']['links_sha256'] && $result['readback']['manual_fields']===$plan['before']['manual_fields'],'content_bridge_or_manual_changed');
            LocalTvSchemaV1::save($directory,'content-after.json',$result);return $result;
        }catch(Throwable $e){if($started)throw new RuntimeException('content_unknown_no_replay',0,$e);throw $e;}
        finally{$db->query("SELECT RELEASE_LOCK('anytour-local-tv-daily')");}
    }
}

if(!defined('LOCAL_TV_CONTENT_LIBRARY_ONLY')){
    $action=$argv[1]??'';$home=(string)getenv('HOME');$root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('LOCAL_TV_CONTENT_DIR');$stage=(string)getenv('LOCAL_TV_CONTENT_SOURCE_ROOT');
    $source=(string)getenv('LOCAL_TV_CONTENT_SOURCE_SHA');$control=(string)getenv('LOCAL_TV_CONTENT_CONTROL_SHA');$operation=(string)getenv('LOCAL_TV_CONTENT_OPERATION');
    $receipt=['schema_version'=>1,'operation_id'=>$operation,'source_sha'=>$source,'control_source_sha'=>$control,'action'=>$action,'state'=>'blocked',
        'supplier_calls'=>0,'old_profile_writes'=>0,'mapping_writes'=>0,'schema_writes'=>0,'database_writes'=>0,'observed_at'=>time()];$exit=1;
    define('LOCAL_TV_SCHEMA_LIBRARY_ONLY',true);define('LOCAL_TV_SEED_LIBRARY_ONLY',true);
    require __DIR__.'/local_tv_schema_v1.php';require __DIR__.'/local_tv_seed_v1.php';
    try{
        LocalTvSchemaV1::need(count($argv)===2 && in_array($action,['plan','fill','readback'],true),'content_action');
        LocalTvSchemaV1::need($root===$home.'/www/anytoour.ru' && realpath($root)===$root && !is_link($root.'/config.php'),'content_root');
        LocalTvSchemaV1::need(realpath($dir)===$dir && $dir===$home.'/.anytoour-int-executor/'.$operation && !is_link($dir),'content_operation_root');
        LocalTvSchemaV1::need(preg_match('/^[a-f0-9]{40}$/D',$source)===1 && preg_match('/^[a-f0-9]{40}$/D',$control)===1 && realpath($stage)===$stage,'content_identity');
        $_SERVER['DOCUMENT_ROOT']=$root;require $stage.'/v2/data/db-v1.php';require $stage.'/v2/data/local-tv-catalog-v1.php';v2_data_db_config();$db=v2_data_db();
        $config=hash_file('sha256',$root.'/config.php');LocalTvSchemaV1::need(hash_equals((string)getenv('LOCAL_TV_CONTENT_EXPECTED_CONFIG'),$config),'content_config_changed');$receipt['config_sha256']=$config;
        $parent=(string)getenv('LOCAL_TV_CONTENT_PARENT_FILE');$expected=(string)getenv('LOCAL_TV_CONTENT_PARENT_SHA256');
        LocalTvSchemaV1::need(is_file($parent) && !is_link($parent) && filesize($parent)<=16*1024*1024 && hash_equals($expected,hash_file('sha256',$parent)),'content_parent_changed');
        $plan=json_decode(file_get_contents($parent),true,512,JSON_THROW_ON_ERROR);
        if($action==='plan'){$plan=LocalTvContentV1::plan($db,$plan,$dir,gmdate('Y-m-d H:i:s'));$receipt['plan']=LocalTvContentV1::summary($plan);$receipt['plan_sha256']=hash_file('sha256',$dir.'/content-plan.json');$receipt['state']='planned_independent';}
        elseif($action==='fill'){
            require $stage.'/v2/data/tourvisor-client-v1.php';putenv('TOURVISOR_HTTP_MAX_ATTEMPTS=1');
            $receipt['result']=LocalTvContentV1::fill($db,$plan,$dir,gmdate('Y-m-d H:i:s'),static function(int $id):array{
                static $previous=0.0;LocalTvSchemaV1::need(v2_data_tv_http_attempt_count()<LocalTvContentV1::CAP,'content_http_budget');
                $wait=600-(microtime(true)-$previous)*1000;if($previous>0 && $wait>0)usleep((int)($wait*1000));$previous=microtime(true);
                return v2_data_tv_get('/hotels/'.$id);
            });
            $receipt['supplier_calls']=v2_data_tv_http_attempt_count();$receipt['database_writes']=$receipt['result']['filled']+$receipt['result']['state_updates'];$receipt['state']='filled_independent';
        }else{$receipt['readback']=LocalTvSeedV1::readback($db);$receipt['state']='verified_read_only';}
        LocalTvSchemaV1::need(hash_file('sha256',$root.'/config.php')===$config,'content_config_drift');$exit=0;
    }catch(Throwable $e){$receipt['state']=$e->getMessage()==='content_unknown_no_replay'?'unknown_no_replay':'blocked';$receipt['error_class']=get_class($e);$receipt['error_sha256']=hash('sha256',$e->getMessage());
        if($receipt['state']==='unknown_no_replay')$receipt['database_writes']=null;
        if(function_exists('v2_data_tv_http_attempt_count'))$receipt['supplier_calls']=v2_data_tv_http_attempt_count();}
    try{LocalTvSchemaV1::save($dir,'local-tv-content-receipt.json',$receipt);}catch(Throwable){$exit=1;}
    echo LocalTvSchemaV1::json($receipt)."\n";exit($exit);
}
