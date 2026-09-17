<?php
declare(strict_types=1);

/**
 * MATCH missing canonical priority v2.
 * Immutable successor to v1 live-query failure: same ranking contract, staged READ ONLY extraction.
 */
const MCP2_SCHEMA='match-missing-canonical-priority-v2';
const MCP2_MAX_ROWS=60000;

function mcp2_require(bool $ok,string $code):void{if(!$ok)throw new RuntimeException($code);}
function mcp2_pos(mixed $v,string $f):int{$s=trim((string)$v);mcp2_require($s!==''&&ctype_digit($s)&&(int)$s>0,'invalid_'.$f);return(int)$s;}
function mcp2_safe_error(Throwable $e,string $stage):string{
    $code=(string)$e->getCode();$code=preg_replace('/[^A-Za-z0-9_\-]/','',$code)??'';
    if($e instanceof PDOException)return $stage.'_pdo'.($code!==''?'_'.$code:'');
    $m=$e->getMessage();return preg_match('/^[A-Za-z0-9_\-]{2,100}$/D',$m)?$stage.'_'.$m:$stage.'_failure';
}
function mcp2_query(PDO $db,string $sql,string $stage,array $params=[],int $limit=MCP2_MAX_ROWS):array{
    try{$s=$db->prepare($sql);$s->execute(array_values($params));$r=$s->fetchAll(PDO::FETCH_ASSOC);mcp2_require(count($r)<=$limit,$stage.'_row_budget');return$r;}
    catch(Throwable $e){throw new RuntimeException(mcp2_safe_error($e,$stage),0,$e);}
}
function mcp2_load_v1():void{
    if(!defined('MCP_LIBRARY_ONLY'))define('MCP_LIBRARY_ONLY',true);
    require_once __DIR__.'/hotel_match_missing_canonical_priority_current_v1.php';
}

/** @return array<int,array{sources:array<string,bool>,providers:array<string,bool>}> */
function mcp2_provider_targets(array $andromeda,array $anex):array{
    $out=[];
    foreach($andromeda as$r){
        $tv=mcp2_pos($r['local_hotel_id']??null,'andromeda_tv');$ns=trim((string)($r['supplier_namespace']??''));$id=trim((string)($r['external_hotel_id']??''));
        mcp2_require($ns!==''&&$id!=='','andromeda_identity_shape');
        $out[$tv]??=['sources'=>[],'providers'=>[]];$out[$tv]['sources'][$ns."\x1f".$id]=true;$out[$tv]['providers'][$ns]=true;
    }
    foreach($anex as$r){
        $tv=mcp2_pos($r['catalog_hotel_id']??null,'anex_tv');$id=trim((string)($r['anex_hotel_id']??''));mcp2_require($id!==''&&ctype_digit($id)&&((int)$id)>0,'anex_identity_shape');
        $ns='operator_5';$out[$tv]??=['sources'=>[],'providers'=>[]];$out[$tv]['sources'][$ns."\x1f".$id]=true;$out[$tv]['providers'][$ns]=true;
    }
    ksort($out,SORT_NUMERIC);return$out;
}
function mcp2_canonical_set(array $rows):array{
    $out=[];foreach($rows as$r){$id=mcp2_pos($r['external_key']??null,'canonical_legacy');mcp2_require(!isset($out[$id]),'duplicate_canonical_legacy');$out[$id]=true;}return$out;
}
function mcp2_demand_map(array $rows):array{
    $out=[];foreach($rows as$r){$id=mcp2_pos($r['tv_hotel_id']??null,'demand_tv');mcp2_require(!isset($out[$id]),'duplicate_demand_tv');$out[$id]=$r;}return$out;
}
function mcp2_catalog_map(array $rows):array{
    $out=[];foreach($rows as$r){$id=mcp2_pos($r['id']??null,'catalog_tv');mcp2_require(!isset($out[$id]),'duplicate_catalog_tv');$out[$id]=$r;}return$out;
}
function mcp2_raw_rows(array $targets,array $canonical,array $demand,array $catalog):array{
    $rows=[];
    foreach($targets as$tv=>$meta){
        if(isset($canonical[$tv]))continue;
        $providers=array_keys($meta['providers']);sort($providers,SORT_STRING);$cat=$catalog[$tv]??null;$dem=$demand[$tv]??[];
        $rows[]=[
            'tv_hotel_id'=>(string)$tv,'accepted_source_count'=>(string)count($meta['sources']),'accepted_provider_count'=>(string)count($providers),'provider_namespaces'=>implode(',',$providers),
            'catalog_present'=>$cat===null?'0':'1','catalog_active'=>$cat['is_active']??null,'hotel_name'=>$cat['name']??null,'country_id'=>$cat['country_id']??null,'country_name'=>$cat['country_name']??null,
            'region_id'=>$cat['region_id']??null,'region_name'=>$cat['region_name']??null,'subregion_id'=>$cat['subregion_id']??null,'subregion_name'=>$cat['subregion_name']??null,'category'=>$cat['category']??null,
            'latitude'=>$cat['latitude']??null,'longitude'=>$cat['longitude']??null,
            'observations_24h'=>$dem['observations_24h']??'0','observations_7d'=>$dem['observations_7d']??'0','observations_total'=>$dem['observations_total']??'0',
            'searches_24h'=>$dem['searches_24h']??'0','searches_7d'=>$dem['searches_7d']??'0','searches_total'=>$dem['searches_total']??'0','last_observed_at'=>$dem['last_observed_at']??null,
        ];
    }
    return$rows;
}

function mcp2_extract(PDO $db):array{
    $tables=['andromeda_hotel_identities','anex_hotel_search_mappings','anytour_hotel_sources','tour_price_observations','catalog_hotels'];
    $ph=implode(',',array_fill(0,count($tables),'?'));
    $eng=mcp2_query($db,"SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ($ph)",'tables',$tables,20);
    $by=[];foreach($eng as$r)$by[(string)$r['TABLE_NAME']]=strtoupper((string)$r['ENGINE']);foreach($tables as$t)mcp2_require(($by[$t]??'')==='INNODB','table_contract_'.$t);

    $and=mcp2_query($db,"SELECT supplier_namespace,external_hotel_id,local_hotel_id FROM andromeda_hotel_identities WHERE decision_status='accepted' AND local_hotel_id IS NOT NULL AND local_hotel_id>0",'andromeda',[],30000);
    $anex=mcp2_query($db,"SELECT anex_hotel_id,catalog_hotel_id FROM anex_hotel_search_mappings WHERE enabled=1 AND catalog_hotel_id>0 AND anex_hotel_id>0",'anex',[],30000);
    $targets=mcp2_provider_targets($and,$anex);
    $canonRows=mcp2_query($db,"SELECT external_key FROM anytour_hotel_sources WHERE namespace='legacy_catalog'",'canonical',[],30000);
    $canonical=mcp2_canonical_set($canonRows);
    $missing=array_values(array_diff(array_keys($targets),array_keys($canonical)));sort($missing,SORT_NUMERIC);mcp2_require(count($missing)<=20000,'missing_row_budget');

    $demandRows=mcp2_query($db,"SELECT hotel_id AS tv_hotel_id,COUNT(*) AS observations_total,SUM(observed_at>=UTC_TIMESTAMP()-INTERVAL 24 HOUR) AS observations_24h,SUM(observed_at>=UTC_TIMESTAMP()-INTERVAL 7 DAY) AS observations_7d,COUNT(DISTINCT search_id) AS searches_total,COUNT(DISTINCT CASE WHEN observed_at>=UTC_TIMESTAMP()-INTERVAL 24 HOUR THEN search_id END) AS searches_24h,COUNT(DISTINCT CASE WHEN observed_at>=UTC_TIMESTAMP()-INTERVAL 7 DAY THEN search_id END) AS searches_7d,MAX(observed_at) AS last_observed_at FROM tour_price_observations WHERE hotel_id>0 GROUP BY hotel_id",'demand',[],20000);
    $demand=mcp2_demand_map($demandRows);

    $catalog=[];
    foreach(array_chunk($missing,500)as$i=>$ids){if(!$ids)continue;$marks=implode(',',array_fill(0,count($ids),'?'));$rows=mcp2_query($db,"SELECT id,country_id,country_name,region_id,region_name,subregion_id,subregion_name,name,category,latitude,longitude,is_active FROM catalog_hotels WHERE id IN ($marks)",'catalog_'.($i+1),$ids,600);foreach(mcp2_catalog_map($rows)as$id=>$row){mcp2_require(!isset($catalog[$id]),'catalog_cross_chunk_duplicate');$catalog[$id]=$row;}}
    return[
        'raw_rows'=>mcp2_raw_rows($targets,$canonical,$demand,$catalog),
        'input_counts'=>['accepted_andromeda_rows'=>count($and),'enabled_anex_rows'=>count($anex),'accepted_tv_targets'=>count($targets),'canonical_legacy_rows'=>count($canonical),'missing_targets'=>count($missing),'demand_hotels'=>count($demand),'catalog_rows_for_missing'=>count($catalog)],
    ];
}

function mcp2_json(mixed$v):string{return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";}
function mcp2_write(string$p,string$b):string{$f=@fopen($p,'x+b');mcp2_require(is_resource($f),'exclusive_output');mcp2_require(fwrite($f,$b)===strlen($b)&&fflush($f),'output_write');if(function_exists('fsync'))mcp2_require(fsync($f),'output_sync');rewind($f);mcp2_require(stream_get_contents($f)===$b,'output_readback');fclose($f);return hash('sha256',$b);}
function mcp2_self_test():void{
    $t=mcp2_provider_targets([['supplier_namespace'=>'operator_315','external_hotel_id'=>'10','local_hotel_id'=>'100'],['supplier_namespace'=>'operator_315','external_hotel_id'=>'10','local_hotel_id'=>'100']],[['anex_hotel_id'=>'20','catalog_hotel_id'=>'100']]);
    mcp2_require(count($t)===1&&count($t[100]['sources'])===2&&count($t[100]['providers'])===2,'aggregate');
    $c=mcp2_canonical_set([['external_key'=>'200']]);$rows=mcp2_raw_rows($t,$c,[],[]);mcp2_require(count($rows)===1&&$rows[0]['catalog_present']==='0','raw');
}
if(in_array('--self-test',$argv??[],true)){mcp2_self_test();echo"missing canonical priority v2 self-test PASS\n";exit(0);}
if(defined('MCP2_LIBRARY_ONLY')&&MCP2_LIBRARY_ONLY===true)return;
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}

mcp2_load_v1();
$op=trim((string)getenv('MATCH_OPERATION_ID'));$sha=trim((string)getenv('MATCH_SOURCE_SHA'));
mcp2_require((bool)preg_match('/^hotel-match-missing-canonical-priority-1971-[0-9]{8}-v[0-9]+$/D',$op),'operation_id');mcp2_require((bool)preg_match('/^[a-f0-9]{40}$/D',$sha),'source_sha');
$dir=rtrim((string)getenv('HOME'),'/').'/.anytoour-match/operations/'.$op;mcp2_require(is_dir($dir),'operation_dir_missing');$res=json_decode((string)file_get_contents($dir.'/reservation.json'),true,32,JSON_THROW_ON_ERROR);mcp2_require(($res['operation_id']??'')===$op&&($res['source_sha']??'')===$sha&&($res['state']??'')==='reserved_before_db_access','reservation_contract');
$result=['operation_id'=>$op,'source_sha'=>$sha,'state'=>'failed_no_replay','no_replay'=>true,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'samo_andromeda_calls'=>0];$db=null;$stage='bootstrap';
try{
    $root=realpath(getcwd());mcp2_require(is_string($root)&&basename($root)==='anytoour.ru','runtime_root');$dbFile=is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php';require_once$dbFile;$db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');$stage='extract';$ex=mcp2_extract($db);$stage='rank';$built=mcp_build($ex['raw_rows']);$db->exec('ROLLBACK');$stage='write';
    $payload=['schema'=>MCP2_SCHEMA,'generated_at_utc'=>gmdate('c'),'input_counts'=>$ex['input_counts'],'census'=>$built['census'],'ready_rows'=>$built['ready_rows'],'held_rows'=>$built['held_rows']];$pb=mcp2_json($payload);$ps=mcp2_write($dir.'/priority-backlog.json',$pb);$result['state']='completed_read_only';$result['input_counts']=$ex['input_counts'];$result['census']=$built['census'];$result['payload_sha256']=$ps;$result['transaction']='REPEATABLE READ / READ ONLY';
}catch(Throwable$e){if($db instanceof PDO&&$db->inTransaction())$db->rollBack();$result['error_code']=mcp2_safe_error($e,$stage);}
$rb=mcp2_json($result);$rs=mcp2_write($dir.'/result.json',$rb);$receipt=['operation_id'=>$op,'source_sha'=>$sha,'state'=>$result['state'],'result_sha256'=>$rs,'readback_verified'=>hash('sha256',(string)file_get_contents($dir.'/result.json'))===$rs,'no_replay'=>true,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'samo_andromeda_calls'=>0];mcp2_write($dir.'/receipt.json',mcp2_json($receipt));echo mcp2_json(['state'=>$result['state'],'input_counts'=>$result['input_counts']??null,'census'=>$result['census']??null,'payload_sha256'=>$result['payload_sha256']??null,'error_code'=>$result['error_code']??null,'result_sha256'=>$rs]);exit($result['state']==='completed_read_only'?0:2);
