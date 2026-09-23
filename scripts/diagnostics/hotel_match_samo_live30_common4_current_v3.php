<?php
declare(strict_types=1);

const SLC4C3_OP='hotel-match-samo-live30-common4-current-1971-20260924-v3';
const SLC4C3_V1_OP='hotel-match-samo-live30-common4-acquire-1971-20260924-v1';
const SLC4C3_V2_OP='hotel-match-samo-live30-common4-retry-1971-20260924-v2';
const SLC4C3_NS=[5=>'operator_5',115=>'operator_115',315=>'operator_315',342=>'operator_342'];

function sc3_need(bool $v,string $why):void{if(!$v)throw new RuntimeException($why);}
function sc3_load(string $p):array{$v=json_decode((string)file_get_contents($p),true,256,JSON_THROW_ON_ERROR);sc3_need(is_array($v),'json_shape');return $v;}
function sc3_json(mixed $v):string{return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function sc3_save(string $p,array $v):string{$raw=sc3_json($v)."\n";$f=@fopen($p,'x+b');sc3_need($f!==false,'exclusive_create');try{sc3_need(fwrite($f,$raw)===strlen($raw)&&fflush($f),'durable_write');if(function_exists('fsync'))sc3_need(fsync($f),'durable_sync');}finally{fclose($f);}return hash('sha256',$raw);}
function sc3_query(PDO $db,string $sql,array $args=[]):array{$st=$db->prepare($sql);$st->execute(array_values($args));return $st->fetchAll(PDO::FETCH_ASSOC)?:[];}
function sc3_excluded(string $v):bool{return preg_match('/^(?:россия|абхазия|russia|russian federation|abkhazia)$/iu',trim($v))===1;}
function sc3_key(array $e):string{return (string)($e['catalog_id']??'').'|'.(string)(int)($e['operator_id']??0);}
function sc3_native(array $e):?string{
    if(($e['state']??'')!=='captured_single_native')return null;
    $x=$e['positive_native_candidates']??null;
    if(!is_array($x)||count($x)!==1)return null;
    $s=(string)$x[0];return preg_match('/^[1-9][0-9]{0,21}$/D',$s)===1?$s:null;
}
function sc3_combine(array $v1,array $v2):array{
    sc3_need(($v1['operation']??'')===SLC4C3_V1_OP&&($v1['state']??'')==='completed_read_only_partial','v1_state');
    sc3_need(($v2['operation']??'')===SLC4C3_V2_OP&&($v2['state']??'')==='completed_read_only','v2_state');
    sc3_need((int)($v1['queried_edge_count']??0)===1084&&(int)($v1['batch_error_count']??0)===21,'v1_counts');
    sc3_need((int)($v2['queried_edge_count']??0)===630&&(int)($v2['v1_failed_batch_count']??0)===21&&(int)($v2['batch_error_count']??-1)===0,'v2_counts');
    $all=[];
    foreach(($v1['edges']??[]) as $e){sc3_need(is_array($e),'v1_edge');$k=sc3_key($e);sc3_need($k!=='|0'&&!isset($all[$k]),'v1_edge_key');$all[$k]=$e+['source'=>'v1'];}
    sc3_need(count($all)===1084,'v1_edge_total');
    $seen=[];
    foreach(($v2['edges']??[]) as $e){sc3_need(is_array($e),'v2_edge');$k=sc3_key($e);sc3_need(isset($all[$k])&&!isset($seen[$k]),'v2_edge_key');$seen[$k]=true;$all[$k]=$e+['source'=>'v2'];}
    sc3_need(count($seen)===630&&count($all)===1084,'combined_edge_total');
    ksort($all,SORT_STRING);
    $states=[];foreach($all as $e)$states[(string)($e['state']??'unknown')]=($states[(string)($e['state']??'unknown')]??0)+1;ksort($states);
    return ['edges'=>array_values($all),'v2_override_count'=>count($seen),'final_state_counts'=>$states];
}
function sc3_anchor_state(array $rows):array{
    if(!$rows)return ['state'=>'canonical_anchor_missing','catalog_sha256'=>null];
    $cats=[];
    foreach($rows as $r){
        if(($r['decision_status']??'')!=='accepted'||$r['local_hotel_id']===null)return ['state'=>'canonical_anchor_invalid','catalog_sha256'=>null];
        $ej=(string)($r['evidence_json']??'');$eh=(string)($r['evidence_sha256']??'');$cat=(string)($r['catalog_sha256']??'');
        if(preg_match('/^[a-f0-9]{64}$/D',$eh)!==1||preg_match('/^[a-f0-9]{64}$/D',$cat)!==1||hash('sha256',$ej)!==$eh)return ['state'=>'canonical_anchor_evidence_invalid','catalog_sha256'=>null];
        $cats[$cat]=true;
    }
    return count($cats)===1?['state'=>'canonical_anchor_ok','catalog_sha256'=>array_key_first($cats)]:['state'=>'canonical_anchor_catalog_conflict','catalog_sha256'=>null];
}
function sc3_execute(PDO $db,array $combined,string $sourceSha):array{
    $edges=$combined['edges'];$catalogIds=[];foreach($edges as $e){$id=(string)($e['catalog_id']??'');sc3_need(preg_match('/^[1-9][0-9]{0,21}$/D',$id)===1,'catalog_id');$catalogIds[$id]=true;}
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
    try{
        $ident=sc3_query($db,"SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status,catalog_sha256,evidence_sha256,evidence_json FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id,local_hotel_id");
        $bySource=[];$byTargetNs=[];$anchors=[];
        foreach($ident as $r){$ns=(string)$r['supplier_namespace'];$ext=(string)$r['external_hotel_id'];$bySource[$ns.'|'.$ext][]=$r;
            if(($r['decision_status']??'')==='accepted'&&$r['local_hotel_id']!==null){$local=(int)$r['local_hotel_id'];$byTargetNs[$ns.'|'.$local][]=$r;if($ns==='andromeda_catalog')$anchors[$local][]=$r;}}
        $catalogTarget=[];
        foreach(array_keys($catalogIds) as $cid){
            $targets=[];foreach($bySource['andromeda_catalog|'.$cid]??[] as $r)if(($r['decision_status']??'')==='accepted'&&$r['local_hotel_id']!==null)$targets[(int)$r['local_hotel_id']]=true;
            $catalogTarget[$cid]=count($targets)===1?(int)array_key_first($targets):null;
        }
        $locals=array_values(array_unique(array_filter(array_values($catalogTarget),fn($x)=>is_int($x)&&$x>0)));sort($locals,SORT_NUMERIC);
        $hotels=[];if($locals){$ph=implode(',',array_fill(0,count($locals),'?'));foreach(sc3_query($db,"SELECT id,name,country_name,region_name,subregion_name,category,is_active FROM catalog_hotels WHERE id IN ($ph)",$locals) as $r)$hotels[(int)$r['id']]=$r;}
        $manual=[];foreach(sc3_query($db,"SELECT catalog_hotel_id FROM anex_hotel_decisions WHERE catalog_hotel_id IS NOT NULL") as $r)$manual[(int)$r['catalog_hotel_id']]=true;
        $nativeTargets=[];foreach($edges as $e){$n=sc3_native($e);if($n!==null)$nativeTargets[$e['namespace'].'|'.$n][(string)$e['catalog_id']]=true;}
        $rows=[];$status=[];$ready=0;$holds=[];
        foreach($edges as $e){
            $cid=(string)$e['catalog_id'];$op=(int)$e['operator_id'];$ns=(string)$e['namespace'];sc3_need((SLC4C3_NS[$op]??null)===$ns,'namespace');
            $state=(string)($e['state']??'');$local=$catalogTarget[$cid]??null;$s='hold_unknown';$native=sc3_native($e);$anchor=['state'=>'catalog_target_unresolved','catalog_sha256'=>null];
            if($local===null)$s='hold_catalog_target_unresolved';
            else{
                $anchor=sc3_anchor_state($anchors[$local]??[]);
                if($state==='catalog_only')$s='hold_catalog_only_no_operator_native';
                elseif($state==='not_returned_in_context')$s='hold_not_returned_in_context';
                elseif($state==='captured_ambiguous_native')$s='hold_ambiguous_native';
                elseif($native===null)$s='hold_no_single_native';
                else{
                    $src=$bySource[$ns.'|'.$native]??[];$foreign=array_filter($byTargetNs[$ns.'|'.$local]??[],fn($r)=>(string)$r['external_hotel_id']!==$native);
                    if(count($nativeTargets[$ns.'|'.$native]??[])>1)$s='hold_input_native_collision';
                    elseif($src){
                        $same=count($src)===1&&($src[0]['decision_status']??'')==='accepted'&&(int)($src[0]['local_hotel_id']??0)===$local;
                        $s=$same?'resolved_same':'hold_source_occupied';
                    }elseif(!$hotels[$local]||(int)$hotels[$local]['is_active']!==1)$s='hold_target_inactive';
                    elseif(sc3_excluded((string)$hotels[$local]['country_name']))$s='hold_excluded_country';
                    elseif(isset($manual[$local]))$s='hold_manual_target';
                    elseif($foreign)$s='hold_target_namespace_occupied';
                    elseif($anchor['state']!=='canonical_anchor_ok')$s='hold_'.$anchor['state'];
                    else{$s='writer_ready';$ready++;}
                }
            }
            $status[$s]=($status[$s]??0)+1;if(str_starts_with($s,'hold_'))$holds[$s]=($holds[$s]??0)+1;
            $rows[]=['catalog_id'=>$cid,'operator_id'=>$op,'namespace'=>$ns,'source'=>$e['source']??null,'state'=>$state,'native_id'=>$native,'local_hotel_id'=>$local,'status'=>$s,'anchor_state'=>$anchor['state'],'writer_ready'=>$s==='writer_ready','safe_to_write_now'=>false];
        }
        $db->rollBack();ksort($status);ksort($holds);
        return ['operation'=>SLC4C3_OP,'state'=>'completed_read_only_samo_common4_current','source_sha'=>$sourceSha,'edge_count'=>count($edges),'v2_override_count'=>$combined['v2_override_count'],'final_state_counts'=>$combined['final_state_counts'],'status_counts'=>$status,'hold_counts'=>$holds,'writer_ready_count'=>$ready,'rows'=>$rows,'provider_http_calls'=>0,'tourvisor_calls'=>0,'samo_calls'=>0,'anex_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'safe_to_write_now'=>false];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    if(in_array('--self-test',$argv??[],true)){
        $mk=fn($i,$s)=>['catalog_id'=>(string)$i,'operator_id'=>5,'namespace'=>'operator_5','state'=>$s,'positive_native_candidates'=>[]];
        $v1=['operation'=>SLC4C3_V1_OP,'state'=>'completed_read_only_partial','queried_edge_count'=>1084,'batch_error_count'=>21,'edges'=>[]];
        for($i=1;$i<=1084;$i++)$v1['edges'][]=$mk($i,'not_returned_in_context');
        $v2=['operation'=>SLC4C3_V2_OP,'state'=>'completed_read_only','queried_edge_count'=>630,'v1_failed_batch_count'=>21,'batch_error_count'=>0,'edges'=>[]];
        for($i=1;$i<=630;$i++)$v2['edges'][]=$mk($i,'catalog_only');
        $c=sc3_combine($v1,$v2);sc3_need(count($c['edges'])===1084&&$c['final_state_counts']===['catalog_only'=>630,'not_returned_in_context'=>454],'combine');
        echo "MATCH_SAMO_LIVE30_COMMON4_CURRENT_V3_SELFTEST_OK\n";exit;
    }
    sc3_need(($argv[1]??'')==='--execute','disabled');$root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$v1=(string)getenv('MATCH_V1_RESULT');$v2=(string)getenv('MATCH_V2_RESULT');$sha=(string)getenv('MATCH_SOURCE_SHA');
    sc3_need(is_dir($root)&&basename($root)==='anytoour.ru'&&is_dir($dir)&&basename($dir)===SLC4C3_OP&&is_file($v1)&&is_file($v2)&&preg_match('/^[a-f0-9]{40}$/D',$sha)===1,'runtime_scope');
    $res=sc3_load($dir.'/reservation.json');sc3_need(($res['state']??'')==='reserved_before_db_read','reservation');
    require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
    try{$combined=sc3_combine(sc3_load($v1),sc3_load($v2));$out=sc3_execute(v2_data_db(),$combined,$sha);$h=sc3_save($dir.'/result.json',$out);sc3_save($dir.'/receipt.json',['operation'=>SLC4C3_OP,'state'=>$out['state'],'result_sha256'=>$h,'readback_verified'=>hash_file('sha256',$dir.'/result.json')===$h,'provider_accessed'=>false,'database_writes'=>0,'mapping_writes'=>0]);echo sc3_json(['state'=>$out['state'],'edge_count'=>$out['edge_count'],'final_state_counts'=>$out['final_state_counts'],'status_counts'=>$out['status_counts'],'writer_ready_count'=>$out['writer_ready_count']])."\n";}
    catch(Throwable $e){$f=['operation'=>SLC4C3_OP,'state'=>'failed_read_only_samo_common4_current','reason'=>preg_replace('/[^A-Za-z0-9_.:-]+/','_',mb_substr($e->getMessage(),0,140,'UTF-8')),'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0];$h=sc3_save($dir.'/result.json',$f);sc3_save($dir.'/receipt.json',['operation'=>SLC4C3_OP,'state'=>$f['state'],'result_sha256'=>$h,'readback_verified'=>true,'provider_accessed'=>false,'database_writes'=>0,'mapping_writes'=>0]);fwrite(STDERR,$f['reason']."\n");exit(2);}
}
