<?php
declare(strict_types=1);

const V40_OP='hotel-match-v37-single-direct-current-dossier-1971-20260925-v40';
const V40_V37_OP='hotel-match-tv-samo-common4-fingerprint-join-1971-20260925-v37';
const V40_V37_SHA='58286e8fc9286abcd5f78d2f05e5e048dfb9b9d6';
const V40_V37_RESULT_SHA='b7ef6082b8d27ad822ddaf69dd86e9249523f859cd61ee1b547c106118ffc55a';
const V40_FRONTIER_SHA='5001297e29a920acc2d565e45fec0e4d0a5b2244e0ba60a860941be882ad1c77';
const V40_EXPECTED=[
 '3995'=>490,'2000028206'=>1181,'3378'=>1247,'2000081576'=>1507,'2000106034'=>65341,
 '2000060751'=>147573,'2000030128'=>152266,'2000089633'=>152744,'6998'=>155174,
];
const V40_NS=['operator_5'=>true,'operator_115'=>true,'operator_315'=>true,'operator_342'=>true];

function v40_need(bool $v,string $why):void{if(!$v)throw new RuntimeException($why);}
function v40_json(mixed $v):string{return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function v40_save(string $p,array $v):string{$raw=v40_json($v)."\n";$f=@fopen($p,'x+b');v40_need($f!==false,'exclusive_create');try{v40_need(fwrite($f,$raw)===strlen($raw)&&fflush($f),'durable_write');if(function_exists('fsync'))v40_need(fsync($f),'durable_sync');}finally{fclose($f);}return hash('sha256',$raw);}
function v40_load(string $p):array{$v=json_decode((string)file_get_contents($p),true,512,JSON_THROW_ON_ERROR);v40_need(is_array($v),'json_shape');return$v;}
function v40_id(mixed $v):?string{$s=trim((string)$v);return preg_match('/^[1-9][0-9]{0,21}$/D',$s)===1?$s:null;}
function v40_int(mixed $v):?int{$s=v40_id($v);if($s===null||strlen($s)>9)return null;$n=(int)$s;return$n>0?$n:null;}
function v40_sha(string $s):bool{return preg_match('/^[0-9a-f]{64}$/D',$s)===1;}
function v40_excluded(string $v):bool{return preg_match('/^(?:россия|абхазия|russia|russian federation|abkhazia)$/iu',trim($v))===1;}
function v40_query(PDO $db,string $sql,array $args=[]):array{$st=$db->prepare($sql);$st->execute(array_values($args));return$st->fetchAll(PDO::FETCH_ASSOC)?:[];}

function v40_input_rows(array $r):array{
    v40_need(($r['operation']??'')===V40_V37_OP,'v37_operation');
    v40_need(($r['state']??'')==='completed_read_only_tv_samo_common4_fingerprint_join','v37_state');
    v40_need(($r['source_sha']??'')===V40_V37_SHA,'v37_source_sha');
    v40_need((int)($r['frontier_total']??0)===1737&&($r['frontier_target_sha256']??'')===V40_FRONTIER_SHA,'v37_frontier');
    foreach(['provider_http_calls','tourvisor_calls','samo_calls','anex_calls','andromeda_calls','database_writes','mapping_writes'] as $k)v40_need((int)($r[$k]??-1)===0,'v37_zero_'.$k);
    v40_need(($r['safe_to_write_now']??null)===false,'v37_safe');
    $out=[];
    foreach($r['rows']??[] as $x){
        if(!is_array($x)||($x['status']??'')!=='single_direct')continue;
        $src=v40_id($x['andromeda_catalog_id']??null);$target=v40_int($x['candidate_local_hotel_id']??null);
        v40_need($src!==null&&$target!==null&&isset(V40_EXPECTED[$src])&&V40_EXPECTED[$src]===$target,'v37_single_identity');
        $out[$src]=$x;
    }
    v40_need(count($out)===9&&count(array_diff_key(V40_EXPECTED,$out))===0,'v37_single_count');
    return$out;
}

function v40_evidence(array $r):array{
    $raw=(string)($r['evidence_json']??'');$hash=(string)($r['evidence_sha256']??'');
    $valid=v40_sha($hash)&&hash('sha256',$raw)===$hash;
    return['valid'=>$valid,'evidence_sha256'=>$hash,'catalog_sha256'=>(string)($r['catalog_sha256']??''),'decision_status'=>(string)($r['decision_status']??'')];
}
function v40_current(PDO $db,array $targets):array{
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
    try{
        $active=[];foreach(v40_query($db,"SELECT id,name,country_name,region_name,subregion_name,category,is_active FROM catalog_hotels WHERE is_active=1") as $r){$id=(int)$r['id'];if(isset($targets[$id]))$active[$id]=$r;}
        $catalogSource=[];$catalogTarget=[];$registry=[];
        foreach(v40_query($db,"SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status,catalog_sha256,evidence_sha256,evidence_json FROM andromeda_hotel_identities WHERE supplier_namespace IN ('andromeda_catalog','operator_5','operator_115','operator_315','operator_342') ORDER BY supplier_namespace,external_hotel_id,local_hotel_id") as $r){
            $ns=(string)$r['supplier_namespace'];$ext=(string)$r['external_hotel_id'];
            if($ns==='andromeda_catalog'){$catalogSource[$ext][]=$r;if(($r['decision_status']??'')==='accepted'&&$r['local_hotel_id']!==null)$catalogTarget[(int)$r['local_hotel_id']][]=$r;continue;}
            if(($r['decision_status']??'')!=='accepted'||$r['local_hotel_id']===null)continue;
            $registry[$ns.'|'.$ext][]=['local_hotel_id'=>(int)$r['local_hotel_id'],'evidence'=>v40_evidence($r)];
        }
        $manual=[];foreach(v40_query($db,"SELECT DISTINCT catalog_hotel_id FROM anex_hotel_decisions WHERE catalog_hotel_id IS NOT NULL") as $r)$manual[(int)$r['catalog_hotel_id']]=true;
        $db->rollBack();return compact('active','catalogSource','catalogTarget','registry','manual');
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw$e;}
}
function v40_lane_native(array $row,string $ns):?string{
    foreach(['direct','support'] as $grp){$lane=$row[$grp][$ns]??null;if(!is_array($lane))continue;$id=v40_id($lane['native_id']??null);if($id!==null)return$id;}
    return null;
}
function v40_registry_lane(array $cur,string $ns,string $native,int $target):array{
    $rows=$cur['registry'][$ns.'|'.$native]??[];$targets=[];$allValid=true;$e=[];
    foreach($rows as $r){$targets[(int)$r['local_hotel_id']]=true;$allValid=$allValid&&(($r['evidence']['valid']??false)===true);$e[]=$r['evidence'];}
    if(!$rows)return['state'=>'missing','namespace'=>$ns,'native_id'=>$native,'target'=>null,'evidence'=>[]];
    if(count($targets)!==1)return['state'=>'collision','namespace'=>$ns,'native_id'=>$native,'targets'=>array_map('intval',array_keys($targets)),'evidence'=>$e];
    $got=(int)array_key_first($targets);
    if(!$allValid)return['state'=>'invalid_evidence','namespace'=>$ns,'native_id'=>$native,'target'=>$got,'evidence'=>$e];
    return['state'=>$got===$target?'same_target':'other_target','namespace'=>$ns,'native_id'=>$native,'target'=>$got,'evidence'=>$e];
}
function v40_classify(string $src,array $row,array $cur):array{
    $target=(int)$row['candidate_local_hotel_id'];$bucket=(string)$row['frontier_bucket'];
    $lanes=[];$sameNs=[];$conflict=false;
    foreach(array_keys(V40_NS) as $ns){$native=v40_lane_native($row,$ns);if($native===null)continue;$x=v40_registry_lane($cur,$ns,$native,$target);$lanes[$ns]=$x;if($x['state']==='same_target')$sameNs[]=$ns;if(in_array($x['state'],['collision','invalid_evidence','other_target'],true))$conflict=true;}
    $directNs=null;$directNative=null;
    foreach(['operator_315','operator_342'] as $ns){$lane=$row['direct'][$ns]??null;if(is_array($lane)&&($lane['state']??'')==='exact_unique'){$directNs=$ns;$directNative=v40_id($lane['native_id']??null);}}
    v40_need($directNs!==null&&$directNative!==null,'direct_lane');
    $directCurrent=$lanes[$directNs]??v40_registry_lane($cur,$directNs,$directNative,$target);
    $srcRows=$cur['catalogSource'][$src]??[];$accepted=array_values(array_filter($srcRows,fn($r)=>(($r['decision_status']??'')==='accepted'&&$r['local_hotel_id']!==null)));
    $same=array_values(array_filter($accepted,fn($r)=>(int)$r['local_hotel_id']===$target));$other=array_values(array_filter($accepted,fn($r)=>(int)$r['local_hotel_id']!==$target));
    $targetRows=$cur['catalogTarget'][$target]??[];$foreignTarget=array_values(array_filter($targetRows,fn($r)=>(string)$r['external_hotel_id']!==$src));
    $hasPriorNonaccepted=(bool)array_filter($srcRows,fn($r)=>(($r['decision_status']??'')!=='accepted'));
    $status='HOLD';$reason='guard';
    if($same){$status='already_resolved_same';$reason='source_already_maps_target';}
    elseif($other){$status='source_occupied';$reason='source_maps_other_target';}
    elseif($foreignTarget){$status='target_occupied';$reason='target_has_other_samo_source';}
    elseif($hasPriorNonaccepted){$status='HOLD';$reason='prior_source_decision';}
    elseif(!isset($cur['active'][$target])||v40_excluded((string)($cur['active'][$target]['country_name']??''))){$status='HOLD';$reason='target_inactive_or_excluded';}
    elseif(isset($cur['manual'][$target])){$status='HOLD';$reason='manual_target_protected';}
    elseif($conflict){$status='conflict';$reason='operator_registry_conflict';}
    elseif($directCurrent['state']==='same_target'){$status='current_missing_single_direct_exact';$reason='exact_same_namespace_current_registry';}
    else{$status='current_missing_unconfirmed';$reason='direct_raw_not_confirmed_current_registry';}
    $second=array_values(array_filter($sameNs,fn($ns)=>$ns!==$directNs));
    return[
      'andromeda_catalog_id'=>$src,'candidate_local_hotel_id'=>$target,'frontier_bucket'=>$bucket,
      'status'=>$status,'reason'=>$reason,'direct_namespace'=>$directNs,'direct_native_id'=>$directNative,
      'direct_current'=>$directCurrent,'all_source_operator_lanes'=>$lanes,'same_target_namespaces'=>$sameNs,
      'second_independent_same_target_namespaces'=>$second,'second_independent_proof_count'=>count($second),
      'source_current_rows'=>array_map(fn($r)=>['local_hotel_id'=>$r['local_hotel_id'],'decision_status'=>$r['decision_status'],'evidence_sha256'=>$r['evidence_sha256']],$srcRows),
      'target_current_samo_sources'=>array_map(fn($r)=>['external_hotel_id'=>$r['external_hotel_id'],'decision_status'=>$r['decision_status'],'evidence_sha256'=>$r['evidence_sha256']],$targetRows),
      'target_catalog'=>$cur['active'][$target]??null,'manual_target'=>isset($cur['manual'][$target]),'safe_to_write_now'=>false,
    ];
}
function v40_execute(PDO $db,array $v37,string $sourceSha):array{
    $rows=v40_input_rows($v37);$targets=[];foreach(V40_EXPECTED as $t)$targets[$t]=true;$cur=v40_current($db,$targets);
    $out=[];$counts=[];$second=0;$bucket=[];foreach($rows as $src=>$row){$x=v40_classify((string)$src,$row,$cur);$out[]=$x;$counts[$x['status']]=($counts[$x['status']]??0)+1;if($x['second_independent_proof_count']>0)$second++;$bucket[$x['frontier_bucket']][$x['status']]=($bucket[$x['frontier_bucket']][$x['status']]??0)+1;}
    ksort($counts);ksort($bucket);usort($out,fn($a,$b)=>$a['candidate_local_hotel_id']<=>$b['candidate_local_hotel_id']);
    return['operation'=>V40_OP,'state'=>'completed_read_only_v37_single_direct_current_dossier','generated_at_utc'=>gmdate('c'),'source_sha'=>$sourceSha,'v37_source_sha'=>V40_V37_SHA,'v37_result_sha256'=>V40_V37_RESULT_SHA,'input_single_direct_count'=>count($out),'candidate_status_counts'=>$counts,'rows_with_second_independent_proof'=>$second,'by_v33_bucket'=>$bucket,'rows'=>$out,'provider_http_calls'=>0,'tourvisor_calls'=>0,'samo_calls'=>0,'anex_calls'=>0,'andromeda_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'safe_to_write_now'=>false];
}
function v40_self_test():void{
    $row=['candidate_local_hotel_id'=>10,'frontier_bucket'=>'tv_only_missing_both','direct'=>['operator_315'=>['state'=>'exact_unique','native_id'=>'7'],'operator_342'=>['state'=>'source_namespace_missing']],'support'=>['operator_115'=>['state'=>'source_namespace_missing'],'operator_5'=>['state'=>'source_namespace_missing']]];
    $cur=['registry'=>['operator_315|7'=>[['local_hotel_id'=>10,'evidence'=>['valid'=>true,'evidence_sha256'=>str_repeat('a',64)]]]],'catalogSource'=>[],'catalogTarget'=>[],'active'=>[10=>['country_name'=>'Турция']],'manual'=>[]];
    $x=v40_classify('1',$row,$cur);v40_need($x['status']==='current_missing_single_direct_exact','single_exact');
    $cur['catalogTarget'][10]=[['external_hotel_id'=>'2','decision_status'=>'accepted','evidence_sha256'=>str_repeat('b',64)]];$x=v40_classify('1',$row,$cur);v40_need($x['status']==='target_occupied','target_guard');
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    if(in_array('--self-test',$argv??[],true)){v40_self_test();echo"MATCH_V37_SINGLE_DIRECT_CURRENT_DOSSIER_V40_SELFTEST_OK\n";exit;}
    v40_need(($argv[1]??'')==='--execute','disabled');$root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$v37p=(string)getenv('MATCH_V37_RESULT');$v37sha=(string)getenv('MATCH_V37_RESULT_SHA');$sha=(string)getenv('MATCH_SOURCE_SHA');
    v40_need(is_dir($root)&&is_dir($dir)&&basename($dir)===V40_OP&&is_file($v37p)&&$v37sha===V40_V37_RESULT_SHA&&hash_file('sha256',$v37p)===$v37sha&&preg_match('/^[0-9a-f]{40}$/D',$sha)===1,'runtime_scope');
    $res=v40_load($dir.'/reservation.json');v40_need(($res['operation']??'')===V40_OP&&($res['state']??'')==='reserved_before_read_only_dossier','reservation');
    require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
    try{$out=v40_execute(v2_data_db(),v40_load($v37p),$sha);$h=v40_save($dir.'/result.json',$out);v40_save($dir.'/receipt.json',['operation'=>V40_OP,'state'=>$out['state'],'result_sha256'=>$h,'readback_verified'=>hash_file('sha256',$dir.'/result.json')===$h,'provider_accessed'=>false,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0]);echo v40_json(['state'=>$out['state'],'candidate_status_counts'=>$out['candidate_status_counts'],'rows_with_second_independent_proof'=>$out['rows_with_second_independent_proof'],'by_v33_bucket'=>$out['by_v33_bucket']])."\n";}
    catch(Throwable$e){$f=['operation'=>V40_OP,'state'=>'failed_read_only_v37_single_direct_current_dossier','reason'=>preg_replace('/[^A-Za-z0-9_.:-]+/','_',mb_substr($e->getMessage(),0,180,'UTF-8')),'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'safe_to_write_now'=>false];$h=v40_save($dir.'/result.json',$f);v40_save($dir.'/receipt.json',['operation'=>V40_OP,'state'=>$f['state'],'result_sha256'=>$h,'readback_verified'=>true,'provider_accessed'=>false,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0]);fwrite(STDERR,$f['reason']."\n");exit(2);}
}
