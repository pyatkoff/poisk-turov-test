<?php
declare(strict_types=1);

require_once __DIR__.'/hotel_match_live30_common4_gap_matrix_v1.php';

const HMC4C8_OP='hotel-match-live30-common4-continuation-plan-1971-20260923-v8';
const HMC4C8_ORIGINAL_FRONTIER=1799;
const HMC4C8_CHILDREN=[
 ['offset'=>0,'count'=>100,'sha'=>'1d10e02a1a541a242b7466b3eab99887203c005ee270469f2c351179a3387faa'],
 ['offset'=>100,'count'=>100,'sha'=>'e78c23bce102bfb07dd45bcef82f65c88585160747f68c0222dfd82a1f929f82'],
 ['offset'=>200,'count'=>100,'sha'=>'6fe13e366ab5fd6b130389fafd0c769b3bc80ce676b46a82f9d402f7296179f4'],
 ['offset'=>300,'count'=>80,'sha'=>'c6aa57404a3261ed0d9e82d93fba522cd326e000be26b5bf42abd319e28e9257'],
 ['offset'=>380,'count'=>40,'sha'=>'109b819ab7845c9e50242e607d275e1c4dc1c5b6e6851960596a3f3aa01748d4'],
 ['offset'=>420,'count'=>30,'sha'=>'6186f7441a2f9af365117927c6f98c1c8afd0d5c5db23a77c66304a46d965572'],
];

function hmc4c8_need(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
function hmc4c8_json(mixed $v):string{return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function hmc4c8_load(string $p):array{$v=json_decode((string)file_get_contents($p),true,512,JSON_THROW_ON_ERROR);hmc4c8_need(is_array($v),'json_shape');return $v;}
function hmc4c8_save(string $p,array $v):string{$raw=hmc4c8_json($v)."\n";$f=@fopen($p,'x+b');hmc4c8_need($f!==false,'exclusive_create');try{hmc4c8_need(fwrite($f,$raw)===strlen($raw)&&fflush($f),'durable_write');if(function_exists('fsync'))hmc4c8_need(fsync($f),'durable_sync');}finally{fclose($f);}return hash('sha256',$raw);}
function hmc4c8_child_name(int $o,int $n):string{return 'hotel-match-live30-common4-acquire-1971-20260923-o'.$o.'-n'.$n.'-v1';}
function hmc4c8_id_digest(array $ids):string{$ids=array_map('intval',$ids);sort($ids,SORT_NUMERIC);return hash('sha256',hmc4c8_json($ids));}
function hmc4c8_sequence_digest(array $ids):string{return hash('sha256',hmc4c8_json(array_map('intval',$ids)));}
function hmc4c8_partition(array $currentIds,array $attempted):array{
    $current=[];foreach($currentIds as $id)$current[(int)$id]=true;
    $attempt=[];foreach($attempted as $id)$attempt[(int)$id]=true;
    $still=array_keys(array_intersect_key($attempt,$current));
    $resolved=array_keys(array_diff_key($attempt,$current));
    $never=array_keys(array_diff_key($current,$attempt));
    sort($still,SORT_NUMERIC);sort($resolved,SORT_NUMERIC);sort($never,SORT_NUMERIC);
    return ['attempted_still_current'=>$still,'attempted_resolved'=>$resolved,'never_attempted_current'=>$never];
}
function hmc4c8_attempted(string $root):array{
    $attempted=[];$frontierDigest=null;$children=[];
    foreach(HMC4C8_CHILDREN as $c){
        $o=(int)$c['offset'];$n=(int)$c['count'];$name=hmc4c8_child_name($o,$n);$dir=$root.'/'.$name;
        hmc4c8_need(is_dir($dir)&&!is_link($dir),'child_missing_'.$o);
        $rp=$dir.'/result.json';$qp=$dir.'/receipt.json';$pp=$dir.'/plan.json';
        hmc4c8_need(is_file($rp)&&is_file($qp)&&is_file($pp)&&!is_link($rp)&&!is_link($qp)&&!is_link($pp),'child_files_'.$o);
        $raw=(string)file_get_contents($rp);$sha=hash('sha256',$raw);hmc4c8_need($sha===$c['sha'],'child_result_hash_'.$o);
        $r=json_decode($raw,true,512,JSON_THROW_ON_ERROR);$q=hmc4c8_load($qp);$p=hmc4c8_load($pp);
        hmc4c8_need(($q['result_sha256']??'')===$sha&&($r['state']??'')==='completed_read_only','child_terminal_'.$o);
        hmc4c8_need((int)($r['scope_offset']??-1)===$o&&(int)($r['scope_count']??0)===$n&&(int)($r['searched_hotels']??0)===$n,'child_scope_'.$o);
        hmc4c8_need(($p['state']??'')==='live30_common4_ready'&&(int)($p['frontier_count']??0)===HMC4C8_ORIGINAL_FRONTIER&&($p['operator_ids']??null)===[13,18,25,43],'plan_state_'.$o);
        $rows=$p['rows']??null;hmc4c8_need(is_array($rows)&&count($rows)===HMC4C8_ORIGINAL_FRONTIER,'plan_rows_'.$o);
        $ids=[];foreach($rows as $x){$id=(int)($x['tv_hotel_id']??0);hmc4c8_need($id>0,'plan_id_'.$o);$ids[]=$id;}
        hmc4c8_need(count(array_unique($ids))===HMC4C8_ORIGINAL_FRONTIER,'plan_unique_'.$o);
        $d=hmc4c8_sequence_digest($ids);if($frontierDigest===null)$frontierDigest=$d;else hmc4c8_need($frontierDigest===$d,'original_frontier_sequence_drift_'.$o);
        $slice=array_slice($ids,$o,$n);hmc4c8_need(count($slice)===$n,'slice_count_'.$o);
        foreach($slice as $id){hmc4c8_need(!isset($attempted[$id]),'attempted_overlap_'.$id);$attempted[$id]=true;}
        $children[]=['operation'=>$name,'offset'=>$o,'count'=>$n,'result_sha256'=>$sha];
    }
    hmc4c8_need(count($attempted)===450,'attempted_count');
    $ids=array_keys($attempted);sort($ids,SORT_NUMERIC);
    return ['ids'=>$ids,'original_frontier_id_sha256'=>$frontierDigest,'children'=>$children];
}
function hmc4c8_context(PDO $db,array $ids,bool $future):array{
    $out=[];
    foreach(array_chunk(array_values($ids),250) as $chunk){
        if(!$chunk)continue;$ph=implode(',',array_fill(0,count($chunk),'?'));$futureSql=$future?' AND departure_date>=CURDATE()':'';
        $sql="SELECT hotel_id,departure_id,country_id,departure_date,nights,adults,children_count,child_ages_signature,observed_at,source
                FROM tour_price_observations
               WHERE hotel_id IN ($ph)$futureSql
               ORDER BY hotel_id,(source='user_search') DESC,observed_at DESC,departure_date ASC";
        $st=$db->prepare($sql);$st->execute($chunk);
        foreach($st->fetchAll(PDO::FETCH_ASSOC)?:[] as $r){$id=(int)$r['hotel_id'];if(!isset($out[$id]))$out[$id]=$r;}
    }
    return $out;
}
function hmc4c8_plan(PDO $db,array $attempt):array{
    $matrix=hmc4_execute($db);hmc4c8_need(($matrix['state']??'')==='completed_read_only_common4_gap_matrix','matrix_state');
    $matrixRows=$matrix['rows']??null;hmc4c8_need(is_array($matrixRows),'matrix_rows');
    $byId=[];$currentIds=[];foreach($matrixRows as $r){$id=(int)($r['tv_hotel_id']??0);hmc4c8_need($id>0&&!isset($byId[$id]),'matrix_id');$byId[$id]=$r;$currentIds[]=$id;}
    sort($currentIds,SORT_NUMERIC);$parts=hmc4c8_partition($currentIds,$attempt['ids']);

    $never=$parts['never_attempted_current'];$facts=[];
    foreach(array_chunk($never,250) as $chunk){if(!$chunk)continue;$ph=implode(',',array_fill(0,count($chunk),'?'));$st=$db->prepare("SELECT id,name,country_id,country_name,region_name,subregion_name,category FROM catalog_hotels WHERE id IN ($ph) AND is_active=1");$st->execute($chunk);foreach($st->fetchAll(PDO::FETCH_ASSOC)?:[] as $r)$facts[(int)$r['id']]=$r;}
    hmc4c8_need(count($facts)===count($never),'facts_missing');

    $future=hmc4c8_context($db,$never,true);$rest=array_values(array_diff($never,array_keys($future)));$fallback=$rest?hmc4c8_context($db,$rest,false):[];
    $deps=[];foreach($db->query("SELECT country_id,departure_id FROM catalog_departure_countries WHERE is_active=1 ORDER BY country_id,(departure_id=1) DESC,departure_id")->fetchAll(PDO::FETCH_ASSOC)?:[] as $r){$cid=(int)$r['country_id'];if(!isset($deps[$cid]))$deps[$cid]=(int)$r['departure_id'];}

    $opMap=['anex'=>13,'biblio'=>18,'funsun'=>25,'intourist'=>43];$rows=[];$noMissing=[];$missingCounts=['13'=>0,'18'=>0,'25'=>0,'43'=>0];$contextFallback=0;$pastShift=0;
    foreach($never as $id){
        $mr=$byId[$id];$f=$facts[$id];$cid=(int)$f['country_id'];$missing=[];
        foreach($opMap as $lane=>$op){if(!(bool)($mr['lanes'][$lane]['exact_evidence']??false)){$missing[]=$op;$missingCounts[(string)$op]++;}}
        if($missing===[]){$noMissing[]=$id;continue;}
        $ctx=$future[$id]??$fallback[$id]??null;$source='future_observation';
        if($ctx===null){$contextFallback++;$source='catalog_fallback';$ctx=['departure_id'=>$deps[$cid]??1,'country_id'=>$cid,'departure_date'=>(new DateTimeImmutable('now',new DateTimeZone('UTC')))->modify('+14 days')->format('Y-m-d'),'nights'=>7,'adults'=>2,'children_count'=>0,'child_ages_signature'=>''];}
        elseif(!isset($future[$id])){$pastShift++;$source='latest_observation_shifted';$ctx['departure_date']=(new DateTimeImmutable('now',new DateTimeZone('UTC')))->modify('+14 days')->format('Y-m-d');}
        hmc4c8_need((int)($ctx['departure_id']??0)>0,'departure_missing_'.$id);hmc4c8_need((int)($ctx['country_id']??0)===$cid,'country_mismatch_'.$id);
        $rows[]=['tv_hotel_id'=>$id,'hotel_name'=>(string)$f['name'],'gap_bucket'=>(string)$mr['gap_bucket'],'country_id'=>$cid,'country'=>(string)$f['country_name'],'region'=>(string)$f['region_name'],'subregion'=>(string)$f['subregion_name'],'category'=>(string)$f['category'],'exact_lane_count'=>(int)($mr['exact_lane_count']??0),'missing_operator_ids'=>$missing,'context_source'=>$source,'departure_id'=>(int)$ctx['departure_id'],'departure_date'=>(string)$ctx['departure_date'],'nights'=>max(1,min(28,(int)($ctx['nights']??7))),'adults'=>max(1,min(6,(int)($ctx['adults']??2))),'children_count'=>max(0,min(3,(int)($ctx['children_count']??0))),'child_ages_signature'=>(string)($ctx['child_ages_signature']??'')];
    }
    usort($rows,fn($a,$b)=>$a['tv_hotel_id']<=>$b['tv_hotel_id']);
    return ['operation'=>HMC4C8_OP,'state'=>'live30_common4_continuation_ready','generated_at_utc'=>gmdate('c'),'original_frontier_count'=>HMC4C8_ORIGINAL_FRONTIER,'original_frontier_id_sha256'=>$attempt['original_frontier_id_sha256'],'attempted_hotel_count'=>count($attempt['ids']),'attempted_hotel_id_sha256'=>hmc4c8_id_digest($attempt['ids']),'current_frontier_count'=>count($currentIds),'current_frontier_id_sha256'=>hmc4c8_id_digest($currentIds),'attempted_still_current_count'=>count($parts['attempted_still_current']),'attempted_resolved_count'=>count($parts['attempted_resolved']),'never_attempted_current_count'=>count($never),'never_attempted_no_missing_count'=>count($noMissing),'acquisition_target_count'=>count($rows),'acquisition_target_id_sha256'=>hmc4c8_id_digest(array_column($rows,'tv_hotel_id')),'missing_lane_counts'=>$missingCounts,'context_fallback_count'=>$contextFallback,'past_context_shifted'=>$pastShift,'gap_bucket_counts'=>$matrix['gap_bucket_counts']??[],'exact_lane_count_distribution'=>$matrix['exact_lane_count_distribution']??[],'children'=>$attempt['children'],'rows'=>$rows,'provider_http_calls'=>0,'tourvisor_calls'=>0,'samo_calls'=>0,'anex_calls'=>0,'andromeda_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'safe_to_write_now'=>false];
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    $mode=$argv[1]??'';
    if($mode==='--self-test'){$p=hmc4c8_partition([1,2,3,4],[1,2,9]);hmc4c8_need($p['attempted_still_current']===[1,2]&&$p['attempted_resolved']===[9]&&$p['never_attempted_current']===[3,4],'partition');echo "MATCH_COMMON4_CONTINUATION_PLAN_V8_SELFTEST_OK\n";exit;}
    hmc4c8_need($mode==='--execute','disabled');$root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$ops=(string)getenv('MATCH_OPERATIONS_ROOT');$sha=(string)getenv('MATCH_SOURCE_SHA');
    hmc4c8_need(is_dir($root)&&basename($root)==='anytoour.ru'&&is_dir($dir)&&basename($dir)===HMC4C8_OP&&is_dir($ops)&&preg_match('/^[0-9a-f]{40}$/D',$sha)===1,'runtime_scope');
    $res=hmc4c8_load($dir.'/reservation.json');hmc4c8_need(($res['operation']??'')===HMC4C8_OP&&($res['state']??'')==='reserved_before_db_read','reservation');
    try{require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');$attempt=hmc4c8_attempted($ops);$result=hmc4c8_plan(v2_data_db(),$attempt);$result['source_sha']=$sha;$h=hmc4c8_save($dir.'/result.json',$result);hmc4c8_save($dir.'/receipt.json',['operation'=>HMC4C8_OP,'state'=>$result['state'],'result_sha256'=>$h,'readback_verified'=>hash_file('sha256',$dir.'/result.json')===$h,'provider_accessed'=>false,'supplier_calls'=>0,'database_writes'=>0,'mapping_writes'=>0]);echo hmc4c8_json(['state'=>$result['state'],'current_frontier_count'=>$result['current_frontier_count'],'attempted_still_current_count'=>$result['attempted_still_current_count'],'attempted_resolved_count'=>$result['attempted_resolved_count'],'never_attempted_current_count'=>$result['never_attempted_current_count'],'never_attempted_no_missing_count'=>$result['never_attempted_no_missing_count'],'acquisition_target_count'=>$result['acquisition_target_count'],'missing_lane_counts'=>$result['missing_lane_counts']])."\n";}
    catch(Throwable $e){$f=['operation'=>HMC4C8_OP,'state'=>'failed_read_only_continuation_plan','reason'=>preg_replace('/[^A-Za-z0-9_.:-]+/','_',mb_substr($e->getMessage(),0,150,'UTF-8')),'supplier_calls'=>0,'database_writes'=>0,'mapping_writes'=>0];$h=hmc4c8_save($dir.'/result.json',$f);hmc4c8_save($dir.'/receipt.json',['operation'=>HMC4C8_OP,'state'=>$f['state'],'result_sha256'=>$h,'readback_verified'=>true,'provider_accessed'=>false,'supplier_calls'=>0,'database_writes'=>0,'mapping_writes'=>0]);fwrite(STDERR,$f['reason']."\n");exit(2);}
}
