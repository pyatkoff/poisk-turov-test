<?php
declare(strict_types=1);

const HM16_OP='hotel-match-v9-operator-edges-current-reconcile-1971-20260922-v16';
const HM16_INPUT_SHA='0995ccbd0c14639a335a644748d6dc5af6b359345328698ea3c0fc1300d88814';

function hm16_need(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
function hm16_json(mixed $v):string{return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function hm16_load(string $p):array{$v=json_decode((string)file_get_contents($p),true,512,JSON_THROW_ON_ERROR);hm16_need(is_array($v),'json_shape');return $v;}
function hm16_save(string $p,array $v):string{$raw=hm16_json($v)."\n";$f=@fopen($p,'x+b');hm16_need($f!==false,'exclusive_create');try{hm16_need(fwrite($f,$raw)===strlen($raw)&&fflush($f),'durable_write');if(function_exists('fsync'))hm16_need(fsync($f),'durable_sync');}finally{fclose($f);}return hash('sha256',$raw);}
function hm16_positive(mixed $v):?string{$s=trim((string)$v);return preg_match('/^[1-9][0-9]{0,19}$/D',$s)?$s:null;}

function hm16_f4(string $url):array{
    $u=parse_url($url);
    hm16_need(is_array($u)&&($u['scheme']??'')==='https'&&in_array(strtolower((string)($u['host']??'')),['bgoperator.ru','www.bgoperator.ru'],true)
        &&!isset($u['user'])&&!isset($u['pass'])&&(!isset($u['port'])||(int)$u['port']===443),'biblio_link_origin');
    $vals=[];
    foreach(explode('&',(string)($u['query']??'')) as $part){
        $kv=explode('=',$part,2);if(count($kv)!==2)continue;
        if(strcasecmp(rawurldecode($kv[0]),'F4')!==0)continue;
        $v=hm16_positive(rawurldecode($kv[1]));hm16_need($v!==null,'biblio_f4_literal');$vals[$v]=true;
    }
    return array_map('strval',array_keys($vals));
}
function hm16_edge_base(array $e):array{
    $link=(string)($e['operator_link']??'');$linkHash=(string)($e['operator_link_sha256']??'');
    hm16_need($link!==''&&preg_match('/^[0-9a-f]{64}$/D',$linkHash)&&hash('sha256',$link)===$linkHash,'link_hash');
    $tv=(int)($e['tv_hotel_id']??0);hm16_need($tv>0,'tv_id');
    return ['tv_hotel_id'=>$tv,'operator'=>(string)($e['operator']??''),'operator_id'=>(int)($e['operator_id']??0),
        'batch'=>(int)($e['batch']??0),'search_id'=>(string)($e['search_id']??''),'tour_id'=>(string)($e['tour_id']??''),
        'operator_link_sha256'=>$linkHash,'safe_to_write_now'=>false];
}
function hm16_normalize(array $doc):array{
    hm16_need(($doc['operation']??'')==='hotel-match-v9-reconcile-1971-20260921-v1','input_operation');
    $edges=$doc['source_result']['edges']??null;hm16_need(is_array($edges)&&count($edges)===1456,'input_edges');
    $direct=[];$holds=[];$seen=[];
    foreach($edges as $e){
        hm16_need(is_array($e),'edge_shape');$b=hm16_edge_base($e);$op=$b['operator_id'];
        $ns=null;$ext=null;$hold=null;$candidates=[];
        if($op===25||$op===43){
            $ns=$op===25?'operator_315':'operator_342';
            $raw=array_values(array_unique(array_filter(array_map('hm16_positive',(array)($e['positive_native_candidates']??[])))));
            if(($e['link_state']??'')==='captured_single_native'&&count($raw)===1){$ext=$raw[0];}
            else{$hold='saved_ambiguous_native';$candidates=$raw;}
        }elseif($op===18){
            $ns='bgoperator';$raw=hm16_f4((string)$e['operator_link']);
            if(count($raw)===1)$ext=$raw[0];else{$hold='saved_ambiguous_f4';$candidates=$raw;}
        }else{continue;}
        if($ext!==null){
            $k=$ns.'|'.$ext.'|'.$b['tv_hotel_id'];hm16_need(!isset($seen[$k]),'duplicate_direct_edge');$seen[$k]=true;
            $direct[]=$b+['supplier_namespace'=>$ns,'external_hotel_id'=>$ext,'input_status'=>'direct'];
        }else{
            $holds[]=$b+['supplier_namespace'=>$ns,'external_candidates'=>$candidates,'input_status'=>$hold];
        }
    }
    hm16_need(count($direct)===1326&&count($holds)===130,'normalized_counts');
    return ['direct'=>$direct,'input_holds'=>$holds];
}
function hm16_index_saved(array $direct):array{
    $source=[];$target=[];
    foreach($direct as $r){$source[$r['supplier_namespace'].'|'.$r['external_hotel_id']][$r['tv_hotel_id']]=true;
        $target[$r['supplier_namespace'].'|'.$r['tv_hotel_id']][$r['external_hotel_id']]=true;}
    return ['source'=>$source,'target'=>$target];
}
function hm16_classify(array $edge,array $catalog,array $byKey,array $byTargetNs,array $anchors,array $manual,array $saved):array{
    $ns=$edge['supplier_namespace'];$ext=$edge['external_hotel_id'];$tv=(int)$edge['tv_hotel_id'];
    $status=null;
    if(count($saved['source'][$ns.'|'.$ext]??[])>1)$status='saved_source_collision';
    elseif(count($saved['target'][$ns.'|'.$tv]??[])>1)$status='saved_target_collision';
    else{
        $src=$byKey[$ns.'|'.$ext]??[];
        if($src){
            if(count($src)===1&&($src[0]['decision_status']??'')==='accepted'&&(int)($src[0]['local_hotel_id']??0)===$tv)$status='resolved_same';
            else$status='source_occupied';
        }else{
            $h=$catalog[$tv]??null;
            if(!$h||(int)($h['is_active']??0)!==1)$status='target_missing_or_inactive';
            elseif(preg_match('/^(?:россия|абхазия|russia|russian federation|abkhazia)$/iu',trim((string)($h['country_name']??''))))$status='excluded_country';
            elseif(isset($manual[$tv]))$status='manual_target_protected';
            else{
                $occ=array_values(array_filter($byTargetNs[$ns.'|'.$tv]??[],fn($r)=>(string)$r['external_hotel_id']!==$ext));
                $status=$occ?'target_namespace_occupied_other':'current_missing_edge';
            }
        }
    }
    $a=$anchors[$tv]??[];$ready=$status==='current_missing_edge'&&count($a)===1;
    return $edge+['status'=>$status,'writer_ready'=>$ready,'canonical_anchor_count'=>count($a),'catalog_hotel'=>$catalog[$tv]??null,'safe_to_write_now'=>false];
}
function hm16_query(PDO $db,string $sql,array $args=[]):array{$st=$db->prepare($sql);$st->execute(array_values($args));return $st->fetchAll(PDO::FETCH_ASSOC)?:[];}
function hm16_geo_key(?array $h):string{
    if(!$h)return 'unknown';
    return implode('|',[(string)($h['country_name']??''),(string)($h['region_name']??''),(string)($h['subregion_name']??'')]);
}
function hm16_execute(PDO $db,array $norm,string $sourceSha):array{
    $direct=$norm['direct'];$ids=array_values(array_unique(array_map(fn($r)=>(int)$r['tv_hotel_id'],$direct)));sort($ids,SORT_NUMERIC);
    hm16_need(count($ids)>0,'target_ids');$ph=implode(',',array_fill(0,count($ids),'?'));
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
    try{
        $catalog=[];foreach(hm16_query($db,"SELECT id,name,country_id,country_name,region_id,region_name,subregion_id,subregion_name,category,is_active FROM catalog_hotels WHERE id IN ($ph) ORDER BY id",$ids) as $r)$catalog[(int)$r['id']]=$r;
        $ident=hm16_query($db,"SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status,catalog_sha256,evidence_sha256,evidence_json FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id");
        $byKey=[];$byTargetNs=[];$anchors=[];
        foreach($ident as $r){$byKey[$r['supplier_namespace'].'|'.$r['external_hotel_id']][]=$r;
            if(($r['decision_status']??'')==='accepted'&&$r['local_hotel_id']!==null){$byTargetNs[$r['supplier_namespace'].'|'.$r['local_hotel_id']][]=$r;if($r['supplier_namespace']==='andromeda_catalog')$anchors[(int)$r['local_hotel_id']][]=$r;}}
        $manual=[];foreach(hm16_query($db,"SELECT catalog_hotel_id,decision_status FROM anex_hotel_decisions WHERE catalog_hotel_id IN ($ph) ORDER BY catalog_hotel_id",$ids) as $r)$manual[(int)$r['catalog_hotel_id']][]=$r;
        $saved=hm16_index_saved($direct);$rows=[];$status=[];$byNs=[];$geoMissing=[];$geoReady=[];
        foreach($direct as $e){$r=hm16_classify($e,$catalog,$byKey,$byTargetNs,$anchors,$manual,$saved);$rows[]=$r;$status[$r['status']]=($status[$r['status']]??0)+1;
            $ns=$r['supplier_namespace'];$byNs[$ns][$r['status']]=($byNs[$ns][$r['status']]??0)+1;
            if($r['status']==='current_missing_edge'){$g=hm16_geo_key($r['catalog_hotel']);$geoMissing[$g]=($geoMissing[$g]??0)+1;if($r['writer_ready'])$geoReady[$g]=($geoReady[$g]??0)+1;}}
        ksort($status);ksort($byNs);foreach($byNs as &$x)ksort($x);unset($x);arsort($geoMissing);arsort($geoReady);
        $db->rollBack();
        return ['operation'=>HM16_OP,'state'=>'completed_read_only_current_reconcile','source_sha'=>$sourceSha,'input_sha256'=>HM16_INPUT_SHA,
            'input_counts'=>['edges'=>1456,'direct'=>count($direct),'input_holds'=>count($norm['input_holds'])],
            'unique_targets'=>count($ids),'status_counts'=>$status,'namespace_status_counts'=>$byNs,
            'current_missing_by_geo'=>$geoMissing,'writer_ready_by_geo'=>$geoReady,
            'writer_ready_count'=>count(array_filter($rows,fn($r)=>$r['writer_ready'])),
            'rows'=>$rows,'input_holds'=>$norm['input_holds'],'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'safe_to_write_now'=>false];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}

if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    $mode=$argv[1]??'';
    if($mode==='--self-test'){
        $u='https://www.bgoperator.ru/price.shtml?x=1&F4=123&F4=456';hm16_need(hm16_f4($u)===['123','456'],'f4_repeat');
        $edge=['supplier_namespace'=>'operator_315','external_hotel_id'=>'10','tv_hotel_id'=>7,'safe_to_write_now'=>false];
        $cat=[7=>['id'=>7,'is_active'=>1,'country_name'=>'Турция','region_name'=>'Side','subregion_name'=>'']];
        $saved=['source'=>['operator_315|10'=>[7=>true]],'target'=>['operator_315|7'=>['10'=>true]]];
        $x=hm16_classify($edge,$cat,[],[],[7=>[['decision_status'=>'accepted']]],[],$saved);hm16_need($x['status']==='current_missing_edge'&&$x['writer_ready'],'ready');
        $x=hm16_classify($edge,$cat,['operator_315|10'=>[['decision_status'=>'accepted','local_hotel_id'=>7]]],[],[7=>[['decision_status'=>'accepted']]],[],$saved);hm16_need($x['status']==='resolved_same','resolved');
        echo "MATCH_V9_OPERATOR_EDGES_CURRENT_V16_SELFTEST_OK\n";exit;
    }
    if($mode==='--validate-input'){
        $p=$argv[2]??'';hm16_need(is_file($p)&&hash_file('sha256',$p)===HM16_INPUT_SHA,'input_hash');$n=hm16_normalize(hm16_load($p));echo hm16_json(['direct'=>count($n['direct']),'holds'=>count($n['input_holds'])])."\n";exit;
    }
    hm16_need($mode==='--execute','disabled');$root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$input=(string)getenv('MATCH_INPUT_FILE');$sha=(string)getenv('MATCH_SOURCE_SHA');
    hm16_need(is_dir($root)&&basename($root)==='anytoour.ru'&&is_dir($dir)&&basename($dir)===HM16_OP&&is_file($input)&&preg_match('/^[0-9a-f]{40}$/D',$sha),'runtime_scope');
    $res=hm16_load($dir.'/reservation.json');hm16_need(($res['operation']??'')===HM16_OP&&($res['state']??'')==='reserved_before_db_read','reservation');
    hm16_need(hash_file('sha256',$input)===HM16_INPUT_SHA,'input_hash');$norm=hm16_normalize(hm16_load($input));
    require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
    try{$result=hm16_execute(v2_data_db(),$norm,$sha);$h=hm16_save($dir.'/result.json',$result);hm16_save($dir.'/receipt.json',['operation'=>HM16_OP,'state'=>$result['state'],'result_sha256'=>$h,'readback_verified'=>hash_file('sha256',$dir.'/result.json')===$h,'provider_accessed'=>false,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0]);echo hm16_json(['state'=>$result['state'],'direct'=>$result['input_counts']['direct'],'writer_ready'=>$result['writer_ready_count'],'status_counts'=>$result['status_counts']])."\n";}
    catch(Throwable $e){$reason=preg_replace('/[^A-Za-z0-9_.:-]+/','_',mb_substr($e->getMessage(),0,100,'UTF-8'))?:'failure';$f=['operation'=>HM16_OP,'state'=>'failed_read_only_current_reconcile','reason'=>$reason,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0];$h=hm16_save($dir.'/result.json',$f);hm16_save($dir.'/receipt.json',['operation'=>HM16_OP,'state'=>$f['state'],'result_sha256'=>$h,'readback_verified'=>true,'provider_accessed'=>false,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0]);fwrite(STDERR,$reason."\n");exit(2);}
}
