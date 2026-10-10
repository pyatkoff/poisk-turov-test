<?php
/** Initial new-catalog transfer from retained data only. No provider code or old DML. */
declare(strict_types=1);

final class LocalTvSeedV1
{
    public const MAX_HOTELS = 50000;
    private const QUERIES = [
        'profiles'=>'SELECT id,profile_json,profile_sha256,revision,is_active FROM anytour_hotels ORDER BY id',
        'sources'=>'SELECT anytour_hotel_id,namespace,external_key,acquired_via,source_json,source_sha256,first_seen_at,last_seen_at FROM anytour_hotel_sources ORDER BY anytour_hotel_id,namespace,external_key',
        'retained'=>'SELECT hotel_id,raw_json,source_hash,fetched_at FROM catalog_hotel_details ORDER BY hotel_id',
    ];

    private static function timestamp(mixed $v): string
    {
        LocalTvSchemaV1::need(is_string($v), 'timestamp_type');
        $d=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$v,new DateTimeZone('UTC'));
        LocalTvSchemaV1::need($d!==false && $d->format('Y-m-d H:i:s')===$v,'timestamp_invalid');
        return $v;
    }

    /** The same surviving observation stores/filters as reviewed backfillObserved(). */
    private static function observations(PDO $db): array
    {
        $queries=["SELECT hotel_id,observed_at AS seen FROM tour_price_observations WHERE hotel_id>0 AND search_id>0 AND source IN ('user_search','scheduled_monitor','hot_tours')",
            'SELECT hotel_id,fetched_at AS seen FROM hot_tours_current WHERE hotel_id>0'];
        $optional=[
            'tour_operator_identity_observations'=>"SELECT hotel_id,first_seen_at AS seen FROM tour_operator_identity_observations WHERE hotel_id>0 AND search_id>0 AND source IN ('user_search','scheduled_monitor','hot_tours') UNION ALL SELECT hotel_id,last_seen_at AS seen FROM tour_operator_identity_observations WHERE hotel_id>0 AND search_id>0 AND source IN ('user_search','scheduled_monitor','hot_tours')",
            'anytour_offers'=>"SELECT legacy_hotel_id AS hotel_id,observed_at AS seen FROM anytour_offers WHERE provider='tourvisor' AND legacy_hotel_id>0 UNION ALL SELECT legacy_hotel_id AS hotel_id,last_seen_at AS seen FROM anytour_offers WHERE provider='tourvisor' AND legacy_hotel_id>0",
            'anytour_offer_price_observations'=>"SELECT provider_local_hotel_id AS hotel_id,observed_at AS seen FROM anytour_offer_price_observations WHERE provider='tourvisor' AND provider_local_hotel_id>0",
        ];
        foreach($optional as $table=>$sql){
            $q=$db->prepare('SELECT 1 FROM information_schema.tables WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
            $q->execute([$table]);if($q->fetchColumn())$queries[]=$sql;
        }
        $q=$db->query('SELECT hotel_id,MIN(seen) AS first_seen,MAX(seen) AS last_seen FROM ('.implode(' UNION ALL ',$queries).') observations GROUP BY hotel_id ORDER BY MIN(seen),hotel_id');
        $out=[];
        while($r=$q->fetch(PDO::FETCH_ASSOC)){
            $id=filter_var($r['hotel_id'],FILTER_VALIDATE_INT);
            if($id===false || $id<1 || $id>4294967295)continue;
            $out[]=['id'=>$id,'first_seen'=>self::timestamp($r['first_seen']),'last_seen'=>self::timestamp($r['last_seen'])];
            LocalTvSchemaV1::need(count($out)<=self::MAX_HOTELS,'observation_review_bound');
        }
        return $out;
    }

    /** Stream complete before images privately; public receipts carry counts/hashes only. */
    private static function scan(PDO $db,string $key,?string $directory,callable $visit): array
    {
        $stream=null;$digest=hash_init('sha256');$rows=0;$bytes=0;
        if($directory!==null){
            $stream=fopen($directory.'/'.$key.'.jsonl','x+b');
            LocalTvSchemaV1::need($stream!==false,'snapshot_exists_no_replay');
            LocalTvSchemaV1::need(chmod($directory.'/'.$key.'.jsonl',0600),'snapshot_mode');
        }
        try{
            $q=$db->query(self::QUERIES[$key]);
            while($r=$q->fetch(PDO::FETCH_ASSOC)){
                $line=LocalTvSchemaV1::json($r)."\n";$rows++;$bytes+=strlen($line);
                LocalTvSchemaV1::need($rows<=250000 && $bytes<=256*1024*1024,'snapshot_review_bound');
                hash_update($digest,$line);
                if($stream!==null)LocalTvSchemaV1::need(fwrite($stream,$line)===strlen($line),'snapshot_write');
                $visit($r);
            }
            if($stream!==null){
                LocalTvSchemaV1::need(fflush($stream),'snapshot_flush');
                if(function_exists('fsync'))LocalTvSchemaV1::need(fsync($stream),'snapshot_sync');
            }
            $sha=hash_final($digest);
            if($stream!==null)LocalTvSchemaV1::need(hash_file('sha256',$directory.'/'.$key.'.jsonl')===$sha,'snapshot_readback');
            return ['rows'=>$rows,'bytes'=>$bytes,'sha256'=>$sha];
        }finally{if(is_resource($stream))fclose($stream);}
    }

    public static function inventory(PDO $db,?string $directory=null): array
    {
        $schema=LocalTvSchemaV1::inspect($db);
        foreach($schema['tables'] as $t)LocalTvSchemaV1::need($t['present'] && $t['valid'],'seed_schema_required');
        $observed=self::observations($db);$ids=array_fill_keys(array_column($observed,'id'),true);
        $profiles=[];$edges=[];$retained=[];$bad=0;$images=[];
        $images['profiles']=self::scan($db,'profiles',$directory,static function(array $r)use(&$profiles):void{$profiles[(int)$r['id']]=true;});
        $images['sources']=self::scan($db,'sources',$directory,static function(array $r)use(&$edges):void{
            if($r['namespace']==='legacy_catalog')$edges[(int)$r['anytour_hotel_id']][]=$r['external_key'];
        });
        $images['retained']=self::scan($db,'retained',$directory,static function(array $r)use($ids,&$retained,&$bad):void{
            $id=(int)$r['hotel_id'];if(!isset($ids[$id]))return;
            try{
                LocalTvSchemaV1::need(is_string($r['raw_json']) && is_string($r['source_hash']) && hash_equals($r['source_hash'],hash('sha256',$r['raw_json'])),'retained_integrity');
                $raw=json_decode($r['raw_json'],true,512,JSON_THROW_ON_ERROR);
                LocalTvSchemaV1::need(is_array($raw),'retained_shape');
                LocalTvCatalogV1::normalize($raw,$id);self::timestamp($r['fetched_at']);
                $retained[]=['id'=>$id,'sha256'=>$r['source_hash'],'fetched_at'=>$r['fetched_at']];
            }catch(Throwable){$bad++;}
        });
        $bridge=0;$ambiguous=0;
        foreach($edges as $old=>$values){
            if(!isset($profiles[$old]))continue;
            $hit=false;foreach($values as $v)if(isset($ids[(int)$v]))$hit=true;
            if(!$hit)continue;
            $values=array_values(array_unique($values));
            if(count($values)!==1 || !preg_match('/^[1-9][0-9]*$/D',$values[0]) || (int)$values[0]>4294967295)$ambiguous++;
            else $bridge++;
        }
        return ['schema'=>$schema,'observations'=>$observed,'observations_sha256'=>hash('sha256',LocalTvSchemaV1::json($observed)),
            'images'=>$images,'retained'=>$retained,'retained_invalid'=>$bad,'bridge_candidates'=>$bridge,'ambiguous_bridges'=>$ambiguous];
    }

    public static function summary(array $plan): array
    {
        return ['database_sha256'=>$plan['schema']['database_sha256'],'schema'=>$plan['schema']['tables'],
            'observed'=>count($plan['observations']),'observations_sha256'=>$plan['observations_sha256'],
            'snapshots'=>$plan['images'],'retained_valid'=>count($plan['retained']),'retained_invalid'=>$plan['retained_invalid'],
            'without_retained'=>count($plan['observations'])-count($plan['retained']),
            'bridge_candidates'=>$plan['bridge_candidates'],'ambiguous_bridges'=>$plan['ambiguous_bridges']];
    }

    public static function apply(PDO $db,array $plan,string $directory,string $now): array
    {
        $lock=(int)$db->query("SELECT GET_LOCK('anytour-local-tv-daily',0)")->fetchColumn();
        LocalTvSchemaV1::need($lock===1,'collector_active');$started=false;
        try{
            $before=self::inventory($db,$directory);
            LocalTvSchemaV1::need($before===$plan,'seed_current_inventory_changed');
            foreach($before['schema']['tables'] as $t)LocalTvSchemaV1::need($t['rows']===0,'initial_target_must_be_empty');
            LocalTvSchemaV1::save($directory,'seed-before.json',self::summary($before));
            LocalTvSchemaV1::save($directory,'seed-started.json',['observations_sha256'=>$plan['observations_sha256'],'observed'=>count($plan['observations']),'provider_calls'=>0]);
            $c=new LocalTvCatalogV1($db);$started=true;$registered=0;$filled=0;
            $registered=$c->backfillObserved();
            $links=$c->migrateLinks($now);
            LocalTvSchemaV1::save($directory,'seed-legacy-result.json',$links);
            $q=$db->prepare('SELECT raw_json,source_hash,fetched_at FROM catalog_hotel_details WHERE hotel_id=?');
            foreach($plan['retained'] as $r){
                $q->execute([$r['id']]);$cache=$q->fetch(PDO::FETCH_ASSOC);
                LocalTvSchemaV1::need($cache && $cache['source_hash']===$r['sha256'] && $cache['fetched_at']===$r['fetched_at'] && hash('sha256',$cache['raw_json'])===$r['sha256'],'retained_changed_after_plan');
                $c->saveSource($r['id'],json_decode($cache['raw_json'],true,512,JSON_THROW_ON_ERROR),$r['fetched_at']);$filled++;
            }
            $counts=$c->counts();
            LocalTvSchemaV1::need($registered===count($plan['observations']) && $counts['discovered']===$registered && $counts['ready']===$filled && $filled===count($plan['retained']),'seed_count_readback');
            foreach(['profiles','sources','retained'] as $key){
                $after=self::scan($db,$key,null,static function(array $r):void{});
                LocalTvSchemaV1::need($after===$plan['images'][$key],'protected_source_changed');
            }
            $read=self::readback($db);
            LocalTvSchemaV1::save($directory,'seed-after.json',$read);
            return ['state'=>'initialized_retained','registered'=>$registered,'filled'=>$filled,'links_transferred'=>$links['transferred'],
                'link_issues'=>count($links['issues']),'protected_sources_unchanged'=>true,'readback'=>$read];
        }catch(Throwable $e){if($started)throw new RuntimeException('seed_unknown_no_replay',0,$e);throw $e;}
        finally{$db->query("SELECT RELEASE_LOCK('anytour-local-tv-daily')");}
    }

    public static function readback(PDO $db): array
    {
        $c=new LocalTvCatalogV1($db);$digest=hash_init('sha256');$manual=0;$photos=0;$missing=[];$samples=[];
        $q=$db->query('SELECT * FROM local_tv_hotels ORDER BY id');
        while($r=$q->fetch(PDO::FETCH_ASSOC)){
            hash_update($digest,LocalTvSchemaV1::json($r)."\n");
            foreach(['discovery_json','content_json','manual_json','source_absent_json'] as $k)json_decode($r[$k],true,512,JSON_THROW_ON_ERROR);
            if($r['source_json']!==null){
                LocalTvSchemaV1::need(is_string($r['source_sha256']) && hash_equals($r['source_sha256'],hash('sha256',$r['source_json'])),'new_source_integrity');
                LocalTvCatalogV1::normalize(json_decode($r['source_json'],true,512,JSON_THROW_ON_ERROR),(int)$r['id']);
            }
            $fields=json_decode($r['manual_json'],true,512,JSON_THROW_ON_ERROR);$manual+=count($fields);
            $content=json_decode($r['content_json'],true,512,JSON_THROW_ON_ERROR);$photos+=count($content['images']??[]);
            foreach(json_decode($r['source_absent_json'],true,512,JSON_THROW_ON_ERROR) as $field)$missing[$field]=($missing[$field]??0)+1;
            if($r['source_json']!==null && count($samples)<3){
                $dto=$c->read([(int)$r['id']])['items'][0];
                $samples[]=['id'=>$dto['id'],'revision'=>$dto['revision'],'photos'=>count($dto['images']??[]),'manual_fields'=>count($dto['manualFields']),'description_sections'=>count($dto['descriptionSections']??[])];
            }
        }
        $links=0;$ld=hash_init('sha256');
        foreach($db->query('SELECT * FROM local_tv_legacy_links ORDER BY old_local_id') as $r){
            LocalTvSchemaV1::need(hash_equals($r['snapshot_sha256'],hash('sha256',$r['snapshot_json'])),'link_snapshot_integrity');
            json_decode($r['snapshot_json'],true,512,JSON_THROW_ON_ERROR);$links++;hash_update($ld,LocalTvSchemaV1::json($r)."\n");
        }
        ksort($missing);
        return $c->counts()+['links'=>$links,'manual_fields'=>$manual,'stored_photos'=>$photos,'source_absent_fields'=>$missing,
            'catalog_sha256'=>hash_final($digest),'links_sha256'=>hash_final($ld),'samples'=>$samples];
    }
}

if(!defined('LOCAL_TV_SEED_LIBRARY_ONLY')){
    $action=$argv[1]??'';$home=(string)getenv('HOME');$root=(string)getenv('ANYTOUR_ROOT');
    $directory=(string)getenv('LOCAL_TV_SEED_DIR');$stage=(string)getenv('LOCAL_TV_SEED_SOURCE_ROOT');
    $operation=(string)getenv('LOCAL_TV_SEED_OPERATION');$source=(string)getenv('LOCAL_TV_SEED_SOURCE_SHA');$control=(string)getenv('LOCAL_TV_SEED_CONTROL_SHA');
    $receipt=['schema_version'=>1,'operation_id'=>$operation,'source_sha'=>$source,'control_source_sha'=>$control,'action'=>$action,
        'supplier_calls'=>0,'old_profile_writes'=>0,'mapping_writes'=>0,'schema_writes'=>0,'state'=>'blocked','observed_at'=>time()];$exit=1;
    define('LOCAL_TV_SCHEMA_LIBRARY_ONLY',true);require __DIR__.'/local_tv_schema_v1.php';
    try{
        LocalTvSchemaV1::need(count($argv)===2 && in_array($action,['inventory','apply','readback'],true),'seed_action');
        LocalTvSchemaV1::need($root===$home.'/www/anytoour.ru' && realpath($root)===$root && is_file($root.'/config.php') && !is_link($root.'/config.php'),'seed_root');
        LocalTvSchemaV1::need(realpath($directory)===$directory && $directory===$home.'/.anytoour-int-executor/'.$operation && !is_link($directory),'seed_operation_root');
        LocalTvSchemaV1::need(preg_match('/^[a-f0-9]{40}$/D',$source)===1 && preg_match('/^[a-f0-9]{40}$/D',$control)===1 && realpath($stage)===$stage,'seed_identity');
        $_SERVER['DOCUMENT_ROOT']=$root;require $stage.'/v2/data/db-v1.php';require $stage.'/v2/data/local-tv-catalog-v1.php';
        v2_data_db_config();LocalTvSchemaV1::need(!(defined('ANYTOUR_LOCAL_TV_CATALOG_ENABLED') && constant('ANYTOUR_LOCAL_TV_CATALOG_ENABLED')===true),'registry_must_remain_disabled');
        $db=v2_data_db();$configSha=hash_file('sha256',$root.'/config.php');$receipt['config_sha256']=$configSha;
        if($action==='inventory'){
            $plan=LocalTvSeedV1::inventory($db,$directory);LocalTvSchemaV1::save($directory,'seed-plan.json',$plan);
            $receipt['inventory']=LocalTvSeedV1::summary($plan);$receipt['state']='inventoried_read_only';
        }elseif($action==='apply'){
            LocalTvSchemaV1::need(hash_equals((string)getenv('LOCAL_TV_SEED_EXPECTED_CONFIG_SHA256'),$configSha),'seed_config_changed');
            $parent=$home.'/.anytoour-int-executor/int-andromeda-local-tv-seed-inventory-20261010-v1/seed-plan.json';
            LocalTvSchemaV1::need(is_file($parent) && !is_link($parent) && filesize($parent)<16*1024*1024,'seed_parent');
            $plan=json_decode(file_get_contents($parent),true,512,JSON_THROW_ON_ERROR);
            LocalTvSchemaV1::need(hash_equals((string)getenv('LOCAL_TV_SEED_EXPECTED_PLAN_SHA256'),hash_file('sha256',$parent)),'seed_plan_changed');
            $receipt=array_replace($receipt,LocalTvSeedV1::apply($db,$plan,$directory,gmdate('Y-m-d H:i:s')));
        }else{
            $receipt['readback']=LocalTvSeedV1::readback($db);$receipt['state']='verified_read_only';
        }
        if($action==='inventory')$receipt['plan_sha256']=hash_file('sha256',$directory.'/seed-plan.json');
        LocalTvSchemaV1::need(hash_file('sha256',$root.'/config.php')===$configSha,'seed_config_drift');$exit=0;
    }catch(Throwable $e){
        $receipt['state']=$e->getMessage()==='seed_unknown_no_replay'?'unknown_no_replay':'blocked';
        $receipt['error_class']=get_class($e);$receipt['error_sha256']=hash('sha256',$e->getMessage());
    }
    try{LocalTvSchemaV1::save($directory,'local-tv-seed-receipt.json',$receipt);}catch(Throwable){$exit=1;}
    echo LocalTvSchemaV1::json($receipt)."\n";exit($exit);
}
