<?php
declare(strict_types=1);

const SLC4P_OP='hotel-match-samo-live30-common4-plan-1971-20260924-v1';
const SLC4P_NS_OPS=['operator_5'=>5,'operator_115'=>115,'operator_315'=>315,'operator_342'=>342];

function slc4p_need(bool $v,string $why):void{if(!$v)throw new RuntimeException($why);}
function slc4p_json(mixed $v):string{return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function slc4p_save(string $p,array $v):string{
    $raw=slc4p_json($v)."\n";$f=@fopen($p,'x+b');slc4p_need($f!==false,'exclusive_create');
    try{slc4p_need(fwrite($f,$raw)===strlen($raw)&&fflush($f),'durable_write');if(function_exists('fsync'))slc4p_need(fsync($f),'durable_sync');}
    finally{fclose($f);}return hash('sha256',$raw);
}
function slc4p_query(PDO $db,string $sql,array $args=[]):array{$st=$db->prepare($sql);$st->execute(array_values($args));return $st->fetchAll(PDO::FETCH_ASSOC)?:[];}
function slc4p_cols(PDO $db,string $table):array{
    $st=$db->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION");
    $st->execute([$table]);$out=[];foreach($st->fetchAll(PDO::FETCH_COLUMN)?:[] as $c)if(is_string($c))$out[]=$c;return $out;
}
function slc4p_time_col(array $cols):?string{foreach(['observed_at_utc','last_seen_at','observed_at','last_seen_utc','created_at'] as $c)if(in_array($c,$cols,true))return $c;return null;}
function slc4p_private_config(string $root):array{
    foreach([$root.'/_preview/search3-anex-candidate/.andromeda-private.php',$root.'/v2/.andromeda-private.php'] as $p){
        if(!is_file($p)||is_link($p))continue;$v=require $p;
        if(is_array($v)&&($v['enabled']??false)===true&&is_string($v['catalog_path']??null)&&$v['catalog_path']!=='')return $v;
    }
    throw new RuntimeException('andromeda_private_config_missing');
}
function slc4p_catalog_files(string $catalogPath):array{
    $files=[];if(is_file($catalogPath)&&!is_link($catalogPath))$files[]=$catalogPath;
    foreach(glob(dirname($catalogPath).'/countries/*.json')?:[] as $p)if(is_file($p)&&!is_link($p))$files[]=$p;
    $files=array_values(array_unique($files));sort($files,SORT_STRING);return $files;
}
function slc4p_saved_catalog_index(string $catalogPath,array $wanted):array{
    $index=[];$fileCount=0;
    foreach(slc4p_catalog_files($catalogPath) as $path){
        $raw=(string)file_get_contents($path);$v=json_decode($raw,true);
        if(!is_array($v))continue;$fileCount++;
        $state=(int)($v['all']['params']['STATEINC']??0);
        $hotels=$v['all']['payload']['HOTELS']??[];
        if(!is_array($hotels))continue;
        foreach($hotels as $h){
            if(!is_array($h))continue;$id=trim((string)($h['id']??''));
            if($id===''||!isset($wanted[$id]))continue;
            $row=['stateinc'=>$state>0?$state:null,'catalog_file_sha256'=>hash('sha256',$raw)];
            foreach(['id','name','lName','stateId','stateKey','townId','townKey','starId','starKey','regionId','regionKey'] as $k){
                if(isset($h[$k])&&(is_scalar($h[$k])||$h[$k]===null))$row[$k]=$h[$k];
            }
            $index[$id][]=$row;
        }
    }
    foreach($index as &$rows){usort($rows,fn($a,$b)=>strcmp((string)($a['catalog_file_sha256']??''),(string)($b['catalog_file_sha256']??'')));}unset($rows);
    return ['file_count'=>$fileCount,'by_id'=>$index];
}
function slc4p_execute(PDO $db,string $root):array{
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
    try{
        $cols=slc4p_cols($db,'andromeda_search_hotel_observations');
        slc4p_need(in_array('supplier_namespace',$cols,true)&&in_array('external_hotel_id',$cols,true),'observation_identity_columns');
        $tc=slc4p_time_col($cols);slc4p_need($tc!==null,'observation_time_column');
        $cut=(new DateTimeImmutable('now',new DateTimeZone('UTC')))->modify('-30 days')->format('Y-m-d H:i:s');
        $catalogRows=slc4p_query($db,"SELECT external_hotel_id,MAX($tc) observed_at FROM andromeda_search_hotel_observations WHERE supplier_namespace='andromeda_catalog' AND $tc>=? GROUP BY external_hotel_id ORDER BY external_hotel_id",[$cut]);
        $catalogIds=[];$observedAt=[];
        foreach($catalogRows as $r){$id=trim((string)($r['external_hotel_id']??''));if($id==='')continue;$catalogIds[$id]=true;$observedAt[$id]=(string)($r['observed_at']??'');}
        slc4p_need($catalogIds!==[],'no_samo_live30_catalog');

        $accepted=[];$operatorByLocal=[];$catalogBySource=[];
        foreach(slc4p_query($db,"SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status,catalog_sha256,evidence_sha256 FROM andromeda_hotel_identities WHERE decision_status='accepted' AND local_hotel_id IS NOT NULL ORDER BY supplier_namespace,external_hotel_id,local_hotel_id") as $r){
            $ns=(string)$r['supplier_namespace'];$ext=(string)$r['external_hotel_id'];$local=(int)$r['local_hotel_id'];
            if($ns==='andromeda_catalog'&&isset($catalogIds[$ext]))$catalogBySource[$ext][$local]=true;
            if(isset(SLC4P_NS_OPS[$ns]))$operatorByLocal[$local][$ns][$ext]=true;
        }
        $locals=[];foreach($catalogBySource as $targets)foreach(array_keys($targets) as $id)$locals[(int)$id]=true;
        $facts=[];
        if($locals){
            $ids=array_keys($locals);$ph=implode(',',array_fill(0,count($ids),'?'));
            foreach(slc4p_query($db,"SELECT id,name,country_id,country_name,region_id,region_name,subregion_id,subregion_name,category,is_active FROM catalog_hotels WHERE id IN ($ph) ORDER BY id",$ids) as $r)$facts[(int)$r['id']]=$r;
        }
        $db->rollBack();

        $cfg=slc4p_private_config($root);$saved=slc4p_saved_catalog_index((string)$cfg['catalog_path'],$catalogIds);
        $rows=[];$mapped=0;$unresolved=0;$catalogPresent=0;$catalogMissing=0;$readyAll=0;$missingCounts=array_fill_keys(array_keys(SLC4P_NS_OPS),0);
        foreach(array_keys($catalogIds) as $source){
            $targets=array_keys($catalogBySource[$source]??[]);sort($targets,SORT_NUMERIC);
            $local=count($targets)===1?(int)$targets[0]:null;
            if($local!==null)$mapped++;else$unresolved++;
            $savedRows=$saved['by_id'][$source]??[];$stateSet=[];foreach($savedRows as $sr)if(($sr['stateinc']??null)!==null)$stateSet[(int)$sr['stateinc']]=true;
            $states=array_keys($stateSet);sort($states,SORT_NUMERIC);
            $catalogState=count($savedRows)===0?'saved_catalog_missing':(count($states)===1?'saved_catalog_ready':'saved_catalog_state_ambiguous');
            if($catalogState==='saved_catalog_ready')$catalogPresent++;else$catalogMissing++;
            $lanes=[];$missing=[];
            foreach(SLC4P_NS_OPS as $ns=>$op){
                $ids=$local!==null?array_keys($operatorByLocal[$local][$ns]??[]):[];sort($ids,SORT_NATURAL);
                $status=count($ids)===1?'accepted_exact':(count($ids)>1?'accepted_ambiguous':'missing');
                if($status==='missing'){$missing[]=$op;$missingCounts[$ns]++;}
                $lanes[$ns]=['operator_id'=>$op,'status'=>$status,'external_hotel_ids'=>$ids];
            }
            if($catalogState==='saved_catalog_ready'&&$missing!==[])$readyAll++;
            $rows[]=[
                'andromeda_catalog_id'=>$source,'observed_at'=>$observedAt[$source]??null,
                'mapping_state'=>count($targets)===1?'mapped_unique':(count($targets)>1?'mapped_collision':'unresolved'),
                'local_hotel_id'=>$local,'local_targets'=>$targets,'local_hotel'=>$local!==null?($facts[$local]??null):null,
                'saved_catalog_state'=>$catalogState,'saved_stateinc'=>$states[0]??null,
                'saved_catalog_candidates'=>$savedRows,'operator_lanes'=>$lanes,'missing_operator_ids'=>$missing,
                'acquisition_ready'=>$catalogState==='saved_catalog_ready'&&$missing!==[],'safe_to_write_now'=>false,
            ];
        }
        usort($rows,fn($a,$b)=>strnatcmp((string)$a['andromeda_catalog_id'],(string)$b['andromeda_catalog_id']));
        return [
            'operation'=>SLC4P_OP,'state'=>'samo_live30_common4_plan_ready','generated_at_utc'=>gmdate('c'),
            'cutoff_utc'=>$cut,'observation_time_column'=>$tc,'observation_columns'=>$cols,
            'catalog_live30_count'=>count($rows),'mapped_unique_count'=>$mapped,'unresolved_or_collision_count'=>$unresolved,
            'saved_catalog_ready_count'=>$catalogPresent,'saved_catalog_not_ready_count'=>$catalogMissing,
            'acquisition_ready_count'=>$readyAll,'missing_lane_counts'=>$missingCounts,'saved_catalog_file_count'=>$saved['file_count'],
            'operator_bindings'=>SLC4P_NS_OPS,'rows'=>$rows,
            'provider_http_calls'=>0,'tourvisor_calls'=>0,'samo_calls'=>0,'anex_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'safe_to_write_now'=>false,
        ];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    if(in_array('--self-test',$argv??[],true)){slc4p_need(slc4p_time_col(['x','observed_at_utc'])==='observed_at_utc','time');echo "MATCH_SAMO_LIVE30_COMMON4_PLAN_V1_SELFTEST_OK\n";exit;}
    slc4p_need(($argv[1]??'')==='--execute','disabled');
    $root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$sha=(string)getenv('MATCH_SOURCE_SHA');
    slc4p_need(is_dir($root)&&basename($root)==='anytoour.ru'&&is_dir($dir)&&basename($dir)===SLC4P_OP&&preg_match('/^[a-f0-9]{40}$/D',$sha)===1,'runtime_scope');
    $res=json_decode((string)file_get_contents($dir.'/reservation.json'),true,32,JSON_THROW_ON_ERROR);slc4p_need(($res['operation']??'')===SLC4P_OP&&($res['state']??'')==='reserved_before_db_read','reservation');
    try{
        require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
        $r=slc4p_execute(v2_data_db(),$root);$r['source_sha']=$sha;$h=slc4p_save($dir.'/result.json',$r);
        slc4p_save($dir.'/receipt.json',['operation'=>SLC4P_OP,'state'=>$r['state'],'result_sha256'=>$h,'readback_verified'=>hash_file('sha256',$dir.'/result.json')===$h,'provider_accessed'=>false,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0]);
        echo slc4p_json(['state'=>$r['state'],'catalog_live30_count'=>$r['catalog_live30_count'],'mapped_unique_count'=>$r['mapped_unique_count'],'unresolved_or_collision_count'=>$r['unresolved_or_collision_count'],'saved_catalog_ready_count'=>$r['saved_catalog_ready_count'],'acquisition_ready_count'=>$r['acquisition_ready_count'],'missing_lane_counts'=>$r['missing_lane_counts']])."\n";
    }catch(Throwable $e){
        $f=['operation'=>SLC4P_OP,'state'=>'failed_samo_live30_common4_plan','reason'=>preg_replace('/[^A-Za-z0-9_.:-]+/','_',mb_substr($e->getMessage(),0,160,'UTF-8')),'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0];
        $h=slc4p_save($dir.'/result.json',$f);slc4p_save($dir.'/receipt.json',['operation'=>SLC4P_OP,'state'=>$f['state'],'result_sha256'=>$h,'readback_verified'=>true,'provider_accessed'=>false,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0]);fwrite(STDERR,$f['reason']."\n");exit(2);
    }
}
