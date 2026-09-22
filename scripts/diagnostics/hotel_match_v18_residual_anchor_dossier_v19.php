<?php
declare(strict_types=1);

const HM19_OP='hotel-match-v18-residual-anchor-dossier-1971-20260922-v19';
const HM19_INPUT_SHA='d02f4e1e0664e0bc759681c70049957bf83e86e020a462e4c3fb25f7676ca475';

function hm19_need(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
function hm19_json(mixed $v):string{return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function hm19_load(string $p):array{$v=json_decode((string)file_get_contents($p),true,512,JSON_THROW_ON_ERROR);hm19_need(is_array($v),'json_shape');return $v;}
function hm19_save(string $p,array $v):string{$raw=hm19_json($v)."\n";$f=@fopen($p,'x+b');hm19_need($f!==false,'exclusive_create');try{hm19_need(fwrite($f,$raw)===strlen($raw)&&fflush($f),'durable_write');if(function_exists('fsync'))hm19_need(fsync($f),'durable_sync');}finally{fclose($f);}return hash('sha256',$raw);}
function hm19_query(PDO $db,string $sql,array $args=[]):array{$st=$db->prepare($sql);$st->execute(array_values($args));return $st->fetchAll(PDO::FETCH_ASSOC)?:[];}
function hm19_excluded(string $country):bool{return preg_match('/^(?:россия|абхазия|russia|russian federation|abkhazia)$/iu',trim($country))===1;}
function hm19_target_facts(array $h):array{
    $numeric=['id'=>true,'country_id'=>true,'region_id'=>true,'subregion_id'=>true,'is_active'=>true];
    $keys=['id','name','country_id','country_name','region_id','region_name','subregion_id','subregion_name','category','is_active'];
    $out=[];foreach($keys as $k){$v=$h[$k]??null;$out[$k]=isset($numeric[$k])&&$v!==null?(string)$v:($v===null?null:(string)$v);}return $out;
}
function hm19_source_projection(mixed $node,int $depth=0):array{
    if($depth>14||!is_array($node))return [];
    $out=[];
    if(isset($node['source'])&&is_array($node['source'])){
        $keep=['id'=>1,'name'=>1,'lName'=>1,'stateKey'=>1,'state'=>1,'stateLName'=>1,'townKey'=>1,'town'=>1,'townLName'=>1,
            'star'=>1,'starKey'=>1,'starLName'=>1,'latitude'=>1,'longitude'=>1,'hotelKey'=>1,'operatorKey'=>1,'provider'=>1,'supplier_namespace'=>1];
        $safe=array_intersect_key($node['source'],$keep);
        if($safe)$out[]=hm19_sanitize_scalars($safe);
    }
    foreach($node as $k=>$v){
        if($k==='source')continue;
        if(is_array($v))$out=array_merge($out,hm19_source_projection($v,$depth+1));
    }
    $uniq=[];foreach($out as $r)$uniq[hash('sha256',hm19_json($r))]=$r;
    return array_values($uniq);
}
function hm19_sanitize_scalars(array $a):array{
    $out=[];
    foreach($a as $k=>$v){
        if(is_scalar($v)||$v===null){$s=$v===null?null:mb_substr(trim((string)$v),0,500,'UTF-8');$out[$k]=$s;}
    }
    return $out;
}
function hm19_manifest(array $v18):array{
    hm19_need(($v18['operation']??'')==='hotel-match-v9-operator-edges-postwrite-1971-20260922-v18','input_operation');
    hm19_need(($v18['state']??'')==='completed_read_only_postwrite_reconcile','input_state');
    hm19_need(($v18['status_counts']['current_missing_edge']??null)===77,'input_missing_count');
    hm19_need(($v18['writer_ready_count']??null)===0,'input_ready_zero');
    $rows=[];$targets=[];
    foreach(($v18['rows']??[]) as $r){
        if(!is_array($r)||($r['status']??'')!=='current_missing_edge')continue;
        $tv=(int)($r['tv_hotel_id']??0);$ext=(string)($r['external_hotel_id']??'');$ns=(string)($r['supplier_namespace']??'');
        hm19_need($tv>0&&$ext!==''&&in_array($ns,['bgoperator','operator_315','operator_342'],true),'manifest_identity');
        $h=$r['catalog_hotel']??null;hm19_need(is_array($h)&&(int)($h['id']??0)===$tv,'manifest_target');
        $rows[]=[
            'supplier_namespace'=>$ns,'external_hotel_id'=>$ext,'tv_hotel_id'=>$tv,
            'expected_anchor_count'=>(int)($r['canonical_anchor_count']??-1),
            'target'=>$h,
        ];
        $targets[$tv]=true;
    }
    hm19_need(count($rows)===77&&count($targets)===64,'manifest_counts');
    return $rows;
}
function hm19_anchor_safe(array $a):array{
    return [
        'external_hotel_id'=>(string)($a['external_hotel_id']??''),
        'local_hotel_id'=>(int)($a['local_hotel_id']??0),
        'decision_status'=>(string)($a['decision_status']??''),
        'catalog_sha256'=>(string)($a['catalog_sha256']??''),
        'evidence_sha256'=>(string)($a['evidence_sha256']??''),
        'evidence_hash_valid'=>(bool)($a['evidence_hash_valid']??false),
        'source_projection'=>$a['source_projection']??[],
    ];
}
function hm19_classify(array $m,?array $currentTarget,array $anchors):array{
    $expected=(int)$m['expected_anchor_count'];$count=count($anchors);
    $targetOk=$currentTarget!==null&&(int)($currentTarget['is_active']??0)===1&&!hm19_excluded((string)($currentTarget['country_name']??''))
        &&hm19_target_facts($currentTarget)===hm19_target_facts($m['target']);
    if(!$targetOk)return ['verdict'=>'drift','reasons'=>['target_facts_or_activity_drift']];
    if($count!==$expected)return ['verdict'=>'drift','reasons'=>['anchor_count_changed'],'current_anchor_count'=>$count];
    if($count===0)return ['verdict'=>'zero_anchor','reasons'=>[],'current_anchor_count'=>0];
    if($count<2)return ['verdict'=>'drift','reasons'=>['unexpected_single_anchor'],'current_anchor_count'=>$count];
    $reasons=[];$hashes=[];
    foreach($anchors as $a){
        if(($a['decision_status']??'')!=='accepted'||(int)($a['local_hotel_id']??0)!==(int)$m['tv_hotel_id'])$reasons[]='anchor_target_or_status';
        if(!($a['evidence_hash_valid']??false))$reasons[]='anchor_evidence_hash_invalid';
        $h=(string)($a['catalog_sha256']??'');if(!preg_match('/^[0-9a-f]{64}$/D',$h))$reasons[]='anchor_catalog_hash_invalid';else$hashes[$h]=true;
    }
    if(count($hashes)!==1)$reasons[]='catalog_hash_not_unanimous';
    $reasons=array_values(array_unique($reasons));
    return ['verdict'=>$reasons?'inconsistent_multi_anchor':'consistent_multi_anchor','reasons'=>$reasons,'current_anchor_count'=>$count,'unanimous_catalog_sha256'=>count($hashes)===1?array_key_first($hashes):null];
}
function hm19_execute(PDO $db,array $manifest,string $sourceSha):array{
    $ids=array_values(array_unique(array_map(fn($r)=>(int)$r['tv_hotel_id'],$manifest)));sort($ids,SORT_NUMERIC);
    hm19_need(count($ids)===64,'target_count');$ph=implode(',',array_fill(0,count($ids),'?'));
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
    try{
        $catalog=[];foreach(hm19_query($db,"SELECT id,name,country_id,country_name,region_id,region_name,subregion_id,subregion_name,category,is_active FROM catalog_hotels WHERE id IN ($ph) ORDER BY id",$ids) as $r)$catalog[(int)$r['id']]=$r;
        $anchors=[];
        $rows=hm19_query($db,"SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status,catalog_sha256,evidence_sha256,evidence_json FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IN ($ph) ORDER BY local_hotel_id,external_hotel_id",$ids);
        foreach($rows as $a){
            $raw=(string)($a['evidence_json']??'');$decoded=json_decode($raw,true);$a['evidence_hash_valid']=hash('sha256',$raw)===(string)($a['evidence_sha256']??'');
            $a['source_projection']=is_array($decoded)?hm19_source_projection($decoded):[];unset($a['evidence_json']);$anchors[(int)$a['local_hotel_id']][]=$a;
        }
        $out=[];$counts=[];$byNs=[];$byAnchor=[];$targetsByVerdict=[];$geo=[];
        foreach($manifest as $m){
            $tv=(int)$m['tv_hotel_id'];$aa=$anchors[$tv]??[];$c=hm19_classify($m,$catalog[$tv]??null,$aa);$v=$c['verdict'];
            $counts[$v]=($counts[$v]??0)+1;$byNs[$m['supplier_namespace']][$v]=($byNs[$m['supplier_namespace']][$v]??0)+1;
            $byAnchor[(string)count($aa)][$v]=($byAnchor[(string)count($aa)][$v]??0)+1;$targetsByVerdict[$v][$tv]=true;
            $h=$catalog[$tv]??$m['target'];$g=implode('|',[$m['supplier_namespace'],(string)($h['country_name']??''),(string)($h['region_name']??''),(string)($h['subregion_name']??'')]);
            $geo[$v][$g]=($geo[$v][$g]??0)+1;
            $out[]=$m+$c+['current_target'=>$catalog[$tv]??null,'anchors'=>array_map('hm19_anchor_safe',$aa),'safe_to_write_now'=>false];
        }
        $db->rollBack();ksort($counts);ksort($byNs);ksort($byAnchor,SORT_NATURAL);
        foreach($byNs as &$x)ksort($x);unset($x);foreach($byAnchor as &$x)ksort($x);unset($x);
        foreach($geo as &$x)arsort($x);unset($x);
        $uniq=[];foreach($targetsByVerdict as $v=>$set)$uniq[$v]=count($set);ksort($uniq);
        return [
            'operation'=>HM19_OP,'state'=>'completed_read_only_anchor_dossier','source_sha'=>$sourceSha,'input_sha256'=>HM19_INPUT_SHA,
            'input_rows'=>count($manifest),'unique_targets'=>count($ids),'verdict_counts'=>$counts,'unique_targets_by_verdict'=>$uniq,
            'namespace_verdict_counts'=>$byNs,'anchor_count_verdict_counts'=>$byAnchor,'geo_verdict_counts'=>$geo,
            'rows'=>$out,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'safe_to_write_now'=>false,
        ];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}

if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    $mode=$argv[1]??'';
    if($mode==='--self-test'){
        $m=['tv_hotel_id'=>1,'expected_anchor_count'=>2,'target'=>['id'=>1,'is_active'=>1,'country_name'=>'Турция','name'=>'A','country_id'=>4,'region_id'=>1,'region_name'=>'R','subregion_id'=>null,'subregion_name'=>null,'category'=>'5']];
        $a=['local_hotel_id'=>1,'decision_status'=>'accepted','catalog_sha256'=>str_repeat('a',64),'evidence_hash_valid'=>true];
        $x=hm19_classify($m,$m['target'],[$a,$a]);hm19_need($x['verdict']==='consistent_multi_anchor','consistent_fixture');
        echo "MATCH_V18_RESIDUAL_ANCHOR_DOSSIER_V19_SELFTEST_OK\n";exit;
    }
    hm19_need($mode==='--execute','disabled');$root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$input=(string)getenv('MATCH_V18_RESULT');$sha=(string)getenv('MATCH_SOURCE_SHA');
    hm19_need(is_dir($root)&&basename($root)==='anytoour.ru'&&is_dir($dir)&&basename($dir)===HM19_OP&&is_file($input)&&preg_match('/^[0-9a-f]{40}$/D',$sha),'runtime_scope');
    hm19_need(hash_file('sha256',$input)===HM19_INPUT_SHA,'input_hash');$manifest=hm19_manifest(hm19_load($input));$res=hm19_load($dir.'/reservation.json');hm19_need(($res['operation']??'')===HM19_OP&&($res['state']??'')==='reserved_before_db_read','reservation');
    require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
    try{$result=hm19_execute(v2_data_db(),$manifest,$sha);$h=hm19_save($dir.'/result.json',$result);hm19_save($dir.'/receipt.json',['operation'=>HM19_OP,'state'=>$result['state'],'result_sha256'=>$h,'readback_verified'=>hash_file('sha256',$dir.'/result.json')===$h,'provider_accessed'=>false,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0]);echo hm19_json(['state'=>$result['state'],'verdict_counts'=>$result['verdict_counts'],'unique_targets_by_verdict'=>$result['unique_targets_by_verdict']])."\n";}
    catch(Throwable $e){$f=['operation'=>HM19_OP,'state'=>'failed_read_only_anchor_dossier','reason'=>preg_replace('/[^A-Za-z0-9_.:-]+/','_',mb_substr($e->getMessage(),0,120,'UTF-8')),'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0];$h=hm19_save($dir.'/result.json',$f);hm19_save($dir.'/receipt.json',['operation'=>HM19_OP,'state'=>$f['state'],'result_sha256'=>$h,'readback_verified'=>true,'provider_accessed'=>false,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0]);fwrite(STDERR,$f['reason']."\n");exit(2);}
}
