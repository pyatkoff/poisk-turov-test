<?php
/** Finite SELECT-only export; no supplier client, writer, schema or profile code. */
declare(strict_types=1);

final class MatchRetainedIntakeV1
{
    public const MAX_ROWS=100000;
    public const MAX_BYTES=134217728;

    public static function columns(PDO $db,string $table): array
    {
        $q=$db->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
        $q->execute([$table]);return $q->fetchAll(PDO::FETCH_COLUMN);
    }

    public static function capture(PDO $db,callable $emit): array
    {
        if($db->inTransaction())throw new RuntimeException('nested_transaction');
        $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $db->exec('SET TRANSACTION READ ONLY');$db->beginTransaction();
        $counts=['observation'=>0,'legacy_source'=>0,'identity'=>0,'target'=>0];$schemas=[];$targetIds=[];
        try {
            foreach(['tour_operator_identity_observations','local_tv_legacy_links','andromeda_hotel_identities','catalog_hotels'] as $table){
                $schemas[$table]=self::columns($db,$table);
            }
            $required=[
                'tour_operator_identity_observations'=>['id','hotel_id','operator_id','operator_name','operator_link','country_id','first_seen_at','last_seen_at'],
                'local_tv_legacy_links'=>['old_local_id','tv_id','snapshot_json','snapshot_sha256'],
            ];
            foreach($required as $table=>$fields)if(array_diff($fields,$schemas[$table]))throw new RuntimeException('required_schema_missing');
            $emit(['kind'=>'scope','cohort'=>'common4-retained','captured_at_utc'=>gmdate('Y-m-d\TH:i:s\Z'),
                'database_snapshot'=>'repeatable-read-read-only','catalog_id_namespace'=>'tourvisor_hotel',
                'target_scope'=>'observed_and_legacy_ids','schema_columns'=>$schemas]);
            $allowed=['id','hotel_id','hotel_name','operator_id','operator_name','operator_link','country_id','region_id','subregion_id',
                'region_name','subregion_name','latitude','longitude','tour_id','search_id','source','first_seen_at','last_seen_at',
                'native_id_type','native_id_value','native_id_conflict'];
            $fields=array_values(array_intersect($allowed,$schemas['tour_operator_identity_observations']));
            $sql='SELECT '.implode(',',$fields).' FROM tour_operator_identity_observations ORDER BY id LIMIT '.(self::MAX_ROWS+1);
            foreach($db->query($sql)as $r){
                if(++$counts['observation']>self::MAX_ROWS)throw new RuntimeException('observation_bound_exceeded');
                $targetIds[(int)$r['hotel_id']]=true;
                $emit(['kind'=>'observation','row'=>$r]);
            }
            $links=0;
            foreach($db->query('SELECT old_local_id,tv_id,snapshot_json,snapshot_sha256 FROM local_tv_legacy_links ORDER BY old_local_id LIMIT '.(self::MAX_ROWS+1))as $r){
                if(++$links>self::MAX_ROWS)throw new RuntimeException('legacy_bound_exceeded');
                $targetIds[(int)$r['tv_id']]=true;
                if(!hash_equals((string)$r['snapshot_sha256'],hash('sha256',(string)$r['snapshot_json'])))throw new RuntimeException('legacy_snapshot_integrity');
                $snapshot=json_decode($r['snapshot_json'],true,128,JSON_THROW_ON_ERROR);
                if(!is_array($snapshot['sources']??null))throw new RuntimeException('legacy_sources_shape');
                foreach($snapshot['sources']as $s){
                    if(++$counts['legacy_source']>self::MAX_ROWS)throw new RuntimeException('source_bound_exceeded');
                    if(!is_string($s['source_json']??null)||!is_string($s['source_sha256']??null)
                        ||!hash_equals($s['source_sha256'],hash('sha256',$s['source_json'])))throw new RuntimeException('legacy_source_integrity');
                    $emit(['kind'=>'legacy_source','old_local_id'=>(int)$r['old_local_id'],'tv_hotel_id'=>(int)$r['tv_id'],
                        'snapshot_sha256'=>$r['snapshot_sha256'],'source'=>$s]);
                }
            }
            $identityFields=['supplier_namespace','external_hotel_id','local_hotel_id','decision_status'];
            if(!array_diff($identityFields,$schemas['andromeda_hotel_identities'])){
                $q=$db->query('SELECT '.implode(',',$identityFields).' FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id LIMIT '.(self::MAX_ROWS+1));
                foreach($q as $r){if(++$counts['identity']>self::MAX_ROWS)throw new RuntimeException('identity_bound_exceeded');$emit(['kind'=>'identity','row'=>$r]);}
            }
            $targetFields=['id','name','country_id','country_name','region_name','subregion_name','latitude','longitude','is_active'];
            if(!array_diff($targetFields,$schemas['catalog_hotels'])){
                if(count($targetIds)>self::MAX_ROWS)throw new RuntimeException('target_cohort_bound_exceeded');
                $ids=array_keys($targetIds);sort($ids,SORT_NUMERIC);
                foreach(array_chunk($ids,500)as $chunk){
                    $q=$db->prepare('SELECT '.implode(',',$targetFields).' FROM catalog_hotels WHERE id IN ('
                        .implode(',',array_fill(0,count($chunk),'?')).') ORDER BY id');$q->execute($chunk);
                    foreach($q as $r){++$counts['target'];$emit(['kind'=>'target','row'=>$r]);}
                }
            }
            $db->commit();
            return ['state'=>'captured_read_only','cohort'=>'common4-retained','rows'=>$counts,'legacy_links'=>$links,
                'target_scope'=>'observed_and_legacy_ids','queried_target_ids'=>count($targetIds),
                'supplier_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'safe_to_write_now'=>false];
        }finally{if($db->inTransaction())$db->rollBack();}
    }
}

if(!defined('MATCH_RETAINED_LIBRARY_ONLY')){
    try{
        $home=(string)getenv('HOME');$root=$home.'/www/anytoour.ru';$dir=(string)getenv('MATCH_RETAINED_DIR');
        if(PHP_SAPI!=='cli'||count($argv)!==1||realpath($root)!==$root||realpath($dir)!==$dir
            ||!str_starts_with($dir,$home.'/.anytoour-int-executor/int-andromeda-match-retained-'))throw new RuntimeException('capture_path');
        $dbPath=$root.'/data/db-v1.php';
        if(is_link($dbPath)||hash_file('sha256',$dbPath)!=='ca7f11d6ec53e0cd3d4464e0f645f15b761bb734da1323689479838868d4abc4')throw new RuntimeException('db_helper_not_reviewed');
        $_SERVER['DOCUMENT_ROOT']=$root;require $dbPath;
        $path=$dir.'/retained-raw.ndjson';$handle=fopen($path,'xb');if(!$handle)throw new RuntimeException('capture_exists_no_replay');
        chmod($path,0600);$bytes=0;$ctx=hash_init('sha256');
        $emit=static function(array $row)use($handle,&$bytes,$ctx):void{
            $raw=json_encode($row,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
            $bytes+=strlen($raw);if(strlen($raw)>4194304||$bytes>MatchRetainedIntakeV1::MAX_BYTES)throw new RuntimeException('capture_size_bound');
            if(fwrite($handle,$raw)!==strlen($raw))throw new RuntimeException('capture_short_write');hash_update($ctx,$raw);
        };
        $result=MatchRetainedIntakeV1::capture(v2_data_db(),$emit);
        fflush($handle);fsync($handle);fclose($handle);
        $result['raw_sha256']=hash_final($ctx);$result['raw_bytes']=$bytes;
        $result['config_sha256']=hash_file('sha256',$root.'/config.php');
        echo json_encode($result,JSON_THROW_ON_ERROR)."\n";
    }catch(Throwable $e){$code=get_class($e)===RuntimeException::class&&preg_match('/\A[a-z_]{1,80}\z/D',$e->getMessage())?$e->getMessage():'child_error';
        echo json_encode(['state'=>'blocked_no_replay','error_code'=>$code,'error_class'=>get_class($e),'error_sha256'=>hash('sha256',$e->getMessage())])."\n";exit(1);}
}
