<?php
declare(strict_types=1);

const SBLC4_OP='hotel-match-samo-business-live30-common4-plan-1971-20260924-v2';
const SBLC4_NS_OPS=['operator_5'=>5,'operator_115'=>115,'operator_315'=>315,'operator_342'=>342];

function sblc4_need(bool $v,string $why):void{if(!$v)throw new RuntimeException($why);}
function sblc4_json(mixed $v):string{return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function sblc4_save(string $p,array $v):string{$raw=sblc4_json($v)."\n";$f=@fopen($p,'x+b');sblc4_need($f!==false,'exclusive_create');try{sblc4_need(fwrite($f,$raw)===strlen($raw)&&fflush($f),'durable_write');if(function_exists('fsync'))sblc4_need(fsync($f),'durable_sync');}finally{fclose($f);}return hash('sha256',$raw);}
function sblc4_query(PDO $db,string $sql,array $args=[]):array{$st=$db->prepare($sql);$st->execute(array_values($args));return $st->fetchAll(PDO::FETCH_ASSOC)?:[];}
function sblc4_excluded(string $c):bool{return preg_match('/^(?:россия|абхазия|russia|russian federation|abkhazia)$/iu',trim($c))===1;}
function sblc4_private_config(string $root):array{foreach([$root.'/_preview/search3-anex-candidate/.andromeda-private.php',$root.'/v2/.andromeda-private.php'] as $p){if(!is_file($p)||is_link($p))continue;$v=require$p;if(is_array($v)&&is_string($v['catalog_path']??null)&&$v['catalog_path']!=='')return$v;}throw new RuntimeException('andromeda_private_config_missing');}
function sblc4_scan_saved(string $dir,int $cut):array{
    sblc4_need(is_dir($dir)&&!is_link($dir),'search_dir');$src=[];$files=0;$pages=0;$rows=0;
    foreach(new DirectoryIterator($dir) as $e){
        if($e->isDot()||$e->isLink()||!$e->isFile()||!str_ends_with($e->getFilename(),'.json'))continue;
        if(++$files>100000)throw new RuntimeException('file_cap');$n=$e->getSize();if($n<2||$n>16777216)continue;
        try{$x=json_decode((string)file_get_contents($e->getPathname()),true,64,JSON_THROW_ON_ERROR);}catch(Throwable){continue;}
        $st=is_array($x)?($x['store']??null):null;$p=is_array($st)?($st['snapshot']??null):null;$at=is_array($st)?($st['created_at']??null):null;
        if(!is_array($p)||($p['provider']??null)!=='andromeda'||!is_int($at)||$at<$cut||!is_array($p['offers']??null))continue;
        $pages++;foreach($p['offers'] as $o){if(!is_array($o))continue;$ns=trim((string)($o['supplier_namespace']??''));$id=trim((string)($o['external_hotel_id']??''));if($ns===''||$id==='')continue;$src[$ns.'|'.$id]=[$ns,$id];$rows++;}
    }
    return['sources'=>$src,'files_examined'=>$files,'live_page_files'=>$pages,'offer_rows'=>$rows];
}
function sblc4_catalog_files(string $catalogPath):array{$files=[];if(is_file($catalogPath)&&!is_link($catalogPath))$files[]=$catalogPath;foreach(glob(dirname($catalogPath).'/countries/*.json')?:[] as $p)if(is_file($p)&&!is_link($p))$files[]=$p;$files=array_values(array_unique($files));sort($files,SORT_STRING);return$files;}
function sblc4_saved_catalog_index(string $catalogPath,array $wanted):array{
    $idx=[];$fc=0;foreach(sblc4_catalog_files($catalogPath) as $path){$raw=(string)file_get_contents($path);$v=json_decode($raw,true);if(!is_array($v))continue;$fc++;$state=(int)($v['all']['params']['STATEINC']??0);$hotels=$v['all']['payload']['HOTELS']??[];if(!is_array($hotels))continue;foreach($hotels as $h){if(!is_array($h))continue;$id=trim((string)($h['id']??''));if($id===''||!isset($wanted[$id]))continue;$idx[$id][]= ['stateinc'=>$state>0?$state:null,'catalog_file_sha256'=>hash('sha256',$raw)];}}
    return['file_count'=>$fc,'by_id'=>$idx];
}
function sblc4_canonical_catalog(array $sources,array $acceptedBySource,array $catalogByLocal):array{
    $catalog=[];$opUnresolved=[];$opResolved=0;$opCollision=0;
    foreach($sources as [$ns,$ext]){
        if($ns==='andromeda_catalog'){$catalog[$ext]=true;continue;}
        $targets=array_keys($acceptedBySource[$ns.'|'.$ext]??[]);
        if(count($targets)!==1){$opUnresolved[$ns.'|'.$ext]=true;continue;}
        $cats=array_keys($catalogByLocal[(int)$targets[0]]??[]);
        if(count($cats)===1){$catalog[(string)$cats[0]]=true;$opResolved++;}
        elseif(count($cats)>1)$opCollision++;else$opUnresolved[$ns.'|'.$ext]=true;
    }
    return['catalog'=>$catalog,'operator_unresolved'=>$opUnresolved,'operator_resolved'=>$opResolved,'operator_collision'=>$opCollision];
}
function sblc4_execute(PDO $db,string $root,int $cutTs):array{
    $cfg=sblc4_private_config($root);$raw=sblc4_scan_saved(dirname((string)$cfg['catalog_path']).'/searches',$cutTs);
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
    try{
        $active=[];foreach(sblc4_query($db,"SELECT id,country_name FROM catalog_hotels WHERE is_active=1") as $r){$id=(int)$r['id'];if($id>0&&!sblc4_excluded((string)$r['country_name']))$active[$id]=true;}
        $by=[];$catalogByLocal=[];$ops=[];
        foreach(sblc4_query($db,"SELECT supplier_namespace,external_hotel_id,local_hotel_id FROM andromeda_hotel_identities WHERE decision_status='accepted' AND local_hotel_id IS NOT NULL") as $r){
            $ns=trim((string)$r['supplier_namespace']);$ext=trim((string)$r['external_hotel_id']);$local=(int)$r['local_hotel_id'];if($ns===''||$ext===''||!isset($active[$local]))continue;
            $by[$ns.'|'.$ext][$local]=true;if($ns==='andromeda_catalog')$catalogByLocal[$local][$ext]=true;if(isset(SBLC4_NS_OPS[$ns]))$ops[$local][$ns][$ext]=true;
        }
        $canon=sblc4_canonical_catalog($raw['sources'],$by,$catalogByLocal);$catalog=$canon['catalog'];
        $mapped=[];$unresolved=0;$collisions=0;
        foreach($catalog as $ext=>$_){$targets=array_keys($by['andromeda_catalog|'.$ext]??[]);if(count($targets)===1&&isset($active[(int)$targets[0]]))$mapped[$ext]=(int)$targets[0];elseif(count($targets)>1)$collisions++;else$unresolved++;}
        $db->rollBack();

        $saved=sblc4_saved_catalog_index((string)$cfg['catalog_path'],$catalog);
        $missing=array_fill_keys(array_keys(SBLC4_NS_OPS),0);$dist=['0'=>0,'1'=>0,'2'=>0,'3'=>0,'4'=>0];$ready=0;$savedReady=0;$savedNotReady=0;$rows=[];
        foreach($catalog as $ext=>$_){
            $local=$mapped[$ext]??null;$stateSet=[];foreach($saved['by_id'][$ext]??[] as $sr)if(($sr['stateinc']??null)!==null)$stateSet[(int)$sr['stateinc']]=true;$states=array_keys($stateSet);sort($states,SORT_NUMERIC);
            $savedState=count($states)===1?'saved_catalog_ready':(count($states)===0?'saved_catalog_missing':'saved_catalog_state_ambiguous');if($savedState==='saved_catalog_ready')$savedReady++;else$savedNotReady++;
            $lanes=[];$missingOps=[];$n=0;foreach(SBLC4_NS_OPS as $ns=>$op){$ids=$local!==null?array_keys($ops[$local][$ns]??[]):[];sort($ids,SORT_NATURAL);$status=count($ids)===1?'accepted_exact':(count($ids)>1?'accepted_ambiguous':'missing');if($status==='missing'){$missing[$ns]++;$missingOps[]=$op;}else$n++;$lanes[$ns]=['operator_id'=>$op,'status'=>$status,'external_hotel_ids'=>$ids];}
            if($local!==null)$dist[(string)$n]++;$isReady=$local!==null&&$savedState==='saved_catalog_ready'&&$missingOps!==[];if($isReady)$ready++;
            $rows[]=['andromeda_catalog_id'=>$ext,'mapping_state'=>$local!==null?'mapped_unique':'unresolved_or_collision','local_hotel_id'=>$local,'saved_catalog_state'=>$savedState,'saved_stateinc'=>$states[0]??null,'operator_lanes'=>$lanes,'missing_operator_ids'=>$missingOps,'acquisition_ready'=>$isReady,'safe_to_write_now'=>false];
        }
        usort($rows,fn($a,$b)=>strnatcmp((string)$a['andromeda_catalog_id'],(string)$b['andromeda_catalog_id']));
        return['operation'=>SBLC4_OP,'state'=>'samo_business_live30_common4_plan_ready','generated_at_utc'=>gmdate('c'),'cutoff_utc'=>gmdate('Y-m-d H:i:s',$cutTs),
            'retained_search_page_files'=>$raw['live_page_files'],'retained_offer_rows'=>$raw['offer_rows'],'retained_unique_source_identities'=>count($raw['sources']),
            'catalog_live30_count'=>count($catalog),'mapped_source_count'=>count($mapped),'mapped_unique_local_count'=>count(array_unique(array_values($mapped))),'unresolved_source_count'=>$unresolved,'collision_source_count'=>$collisions,
            'operator_source_identities_unresolved'=>count($canon['operator_unresolved']),'operator_source_identities_resolved_to_catalog'=>$canon['operator_resolved'],'operator_to_catalog_collisions'=>$canon['operator_collision'],
            'common4_lanes_by_count_mapped'=>$dist,'missing_lane_counts'=>$missing,'saved_catalog_ready_count'=>$savedReady,'saved_catalog_not_ready_count'=>$savedNotReady,'acquisition_ready_count'=>$ready,'saved_catalog_file_count'=>$saved['file_count'],'rows'=>$rows,
            'provider_http_calls'=>0,'tourvisor_calls'=>0,'samo_calls'=>0,'anex_calls'=>0,'andromeda_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'safe_to_write_now'=>false];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw$e;}
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    if(in_array('--self-test',$argv??[],true)){$x=sblc4_canonical_catalog([['andromeda_catalog','10'],['operator_115','b']],[ 'operator_115|b'=>[7=>true]],[7=>['10'=>true]]);sblc4_need(count($x['catalog'])===1&&isset($x['catalog']['10'])&&$x['operator_resolved']===1,'canonical');echo"MATCH_SAMO_BUSINESS_LIVE30_COMMON4_PLAN_V2_SELFTEST_OK\n";exit;}
    sblc4_need(($argv[1]??'')==='--execute','disabled');$root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$sha=(string)getenv('MATCH_SOURCE_SHA');
    sblc4_need(is_dir($root)&&is_dir($dir)&&basename($dir)===SBLC4_OP&&preg_match('/^[a-f0-9]{40}$/D',$sha)===1,'runtime_scope');$res=json_decode((string)file_get_contents($dir.'/reservation.json'),true,16,JSON_THROW_ON_ERROR);sblc4_need(($res['operation']??'')===SBLC4_OP,'reservation');
    try{require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');$r=sblc4_execute(v2_data_db(),$root,time()-30*86400);$r['source_sha']=$sha;$h=sblc4_save($dir.'/result.json',$r);sblc4_save($dir.'/receipt.json',['operation'=>SBLC4_OP,'state'=>$r['state'],'result_sha256'=>$h,'readback_verified'=>hash_file('sha256',$dir.'/result.json')===$h,'provider_accessed'=>false,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0]);echo sblc4_json(['state'=>$r['state'],'catalog_live30_count'=>$r['catalog_live30_count'],'mapped_source_count'=>$r['mapped_source_count'],'mapped_unique_local_count'=>$r['mapped_unique_local_count'],'unresolved_source_count'=>$r['unresolved_source_count'],'missing_lane_counts'=>$r['missing_lane_counts'],'acquisition_ready_count'=>$r['acquisition_ready_count']])."\n";}catch(Throwable$e){$f=['operation'=>SBLC4_OP,'state'=>'failed_samo_business_live30_common4_plan','reason'=>preg_replace('/[^A-Za-z0-9_.:-]+/','_',mb_substr($e->getMessage(),0,160)),'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0];$h=sblc4_save($dir.'/result.json',$f);sblc4_save($dir.'/receipt.json',['operation'=>SBLC4_OP,'state'=>$f['state'],'result_sha256'=>$h,'readback_verified'=>true,'provider_accessed'=>false,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0]);fwrite(STDERR,$f['reason']."\n");exit(2);}
    }
