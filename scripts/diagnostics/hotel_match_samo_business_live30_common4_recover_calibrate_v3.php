<?php
declare(strict_types=1);

const SBRC3_OP='hotel-match-samo-business-live30-common4-recover-calibrate-1971-20260924-v3';
const SBRC3_RECOVERY_OP='hotel-match-samo-business-live30-common4-recover-1971-20260924-v1';
const SBRC3_SEALED_OP='hotel-match-samo-business-live30-common4-acquire-1971-20260924-v2';
const SBRC3_EXPECTED_GROUPS=502;
const SBRC3_EXPECTED_EDGES=5988;
const SBRC3_EXPECTED_COMPLETE=392;
const SBRC3_EXPECTED_PARTIAL=110;
const SBRC3_NS=[5=>'operator_5',115=>'operator_115',315=>'operator_315',342=>'operator_342'];

function sbrc3_need(bool $v,string $why):void{if(!$v)throw new RuntimeException($why);}
function sbrc3_sort(mixed $v):mixed{if(!is_array($v))return $v;if(array_is_list($v))return array_map('sbrc3_sort',$v);ksort($v,SORT_STRING);foreach($v as $k=>$x)$v[$k]=sbrc3_sort($x);return $v;}
function sbrc3_json(mixed $v):string{return json_encode(sbrc3_sort($v),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function sbrc3_load(string $p):array{$v=json_decode((string)file_get_contents($p),true,512,JSON_THROW_ON_ERROR);sbrc3_need(is_array($v),'json_shape');return $v;}
function sbrc3_save(string $p,array $v):string{$raw=sbrc3_json($v)."\n";$f=@fopen($p,'x+b');sbrc3_need($f!==false,'exclusive_create');try{sbrc3_need(fwrite($f,$raw)===strlen($raw)&&fflush($f),'durable_write');if(function_exists('fsync'))sbrc3_need(fsync($f),'durable_sync');}finally{fclose($f);}return hash('sha256',$raw);}
function sbrc3_query(PDO $db,string $sql,array $args=[]):array{$st=$db->prepare($sql);$st->execute(array_values($args));return $st->fetchAll(PDO::FETCH_ASSOC)?:[];}
function sbrc3_id(mixed $v):?string{$s=trim((string)$v);return preg_match('/^[1-9][0-9]{0,21}$/D',$s)===1?$s:null;}
function sbrc3_sha(mixed $v):bool{return is_string($v)&&preg_match('/^[0-9a-f]{64}$/D',$v)===1;}
function sbrc3_excluded(string $v):bool{return preg_match('/^(?:россия|абхазия|russia|russian federation|abkhazia)$/iu',trim($v))===1;}
function sbrc3_anchor_projection(array $a):array{$out=[];foreach(['supplier_namespace','external_hotel_id','local_hotel_id','decision_status','catalog_sha256','evidence_sha256'] as $k)$out[$k]=$a[$k]??null;return $out;}
function sbrc3_anchor_state(array $aa):array{
    if(!$aa)return ['state'=>'canonical_anchor_missing','catalog_sha256'=>null,'anchors'=>[]];
    $cats=[];$out=[];
    foreach($aa as $a){
        if(($a['supplier_namespace']??'')!=='andromeda_catalog'||($a['decision_status']??'')!=='accepted'||$a['local_hotel_id']===null)return ['state'=>'canonical_anchor_invalid','catalog_sha256'=>null,'anchors'=>[]];
        $raw=(string)($a['evidence_json']??'');$eh=(string)($a['evidence_sha256']??'');$cat=(string)($a['catalog_sha256']??'');
        if(!sbrc3_sha($eh)||!sbrc3_sha($cat)||hash('sha256',$raw)!==$eh)return ['state'=>'canonical_anchor_evidence_invalid','catalog_sha256'=>null,'anchors'=>[]];
        $cats[$cat]=true;$out[]=sbrc3_anchor_projection($a);
    }
    usort($out,fn($a,$b)=>strcmp((string)$a['external_hotel_id'],(string)$b['external_hotel_id']));
    return count($cats)===1?['state'=>'canonical_anchor_ok','catalog_sha256'=>array_key_first($cats),'anchors'=>$out]:['state'=>'canonical_anchor_catalog_conflict','catalog_sha256'=>null,'anchors'=>$out];
}
function sbrc3_bridge(array $row,int $operator,array $requested):array{
    if((int)($row['operatorKey']??0)!==$operator)return ['state'=>'other_operator'];
    $hotel=sbrc3_id($row['hotelKey']??null);$flag=(string)($row['isOperatorHotelKey']??'');
    if($hotel===null||!isset($requested[$hotel])||$flag!=='0')return ['state'=>'unbound'];
    $original=is_array($row['original']??null)?$row['original']:[];
    $native=sbrc3_id($original['hotelKey']??null);
    if($native===null)return ['state'=>'catalog_only','catalog_id'=>$hotel,'native_id'=>null];
    return ['state'=>'exact_catalog_to_native','catalog_id'=>$hotel,'native_id'=>$native];
}
function sbrc3_recovery_batches(array $r):array{
    sbrc3_need(($r['operation']??'')===SBRC3_RECOVERY_OP&&($r['state']??'')==='completed_read_only_recovery','recovery_state');
    sbrc3_need((int)($r['expected_groups']??0)===SBRC3_EXPECTED_GROUPS&&(int)($r['expected_edges']??0)===SBRC3_EXPECTED_EDGES,'recovery_scope');
    sbrc3_need((int)($r['completed_batch_count']??0)===SBRC3_EXPECTED_COMPLETE&&(int)($r['partial_batch_count']??0)===SBRC3_EXPECTED_PARTIAL&&(int)($r['untouched_batch_count']??-1)===0,'recovery_batch_counts');
    sbrc3_need(array_key_exists('continuation_start_batch',$r)&&$r['continuation_start_batch']===null,'recovery_no_continuation');
    foreach(['provider_http_calls','tourvisor_calls','samo_calls','anex_calls','andromeda_calls','database_writes','mapping_writes'] as $k)sbrc3_need((int)($r[$k]??-1)===0,'recovery_zero_'.$k);
    $batches=$r['batches']??null;$edges=$r['edges']??null;sbrc3_need(is_array($batches)&&count($batches)===SBRC3_EXPECTED_GROUPS&&is_array($edges)&&count($edges)===SBRC3_EXPECTED_EDGES,'recovery_arrays');
    $complete=[];
    foreach($batches as $b){
        $idx=(int)($b['batch']??0);sbrc3_need($idx>=1&&$idx<=SBRC3_EXPECTED_GROUPS,'batch_index');
        if(($b['state']??'')==='fully_drained'){$pc=(int)($b['pages_count']??-1);sbrc3_need($pc>=0,'complete_pages');$complete[$idx]=['pages_count'=>$pc,'catalogs'=>[],'operator_id'=>null,'namespace'=>null];}
        elseif(($b['state']??'')!=='partial_unresolved_no_replay')sbrc3_need(false,'unexpected_batch_state');
    }
    sbrc3_need(count($complete)===SBRC3_EXPECTED_COMPLETE,'complete_count');
    foreach($edges as $e){
        $idx=(int)($e['batch']??0);if(!isset($complete[$idx]))continue;
        $cid=sbrc3_id($e['catalog_id']??null);$op=(int)($e['operator_id']??0);$ns=(string)($e['namespace']??'');
        sbrc3_need($cid!==null&&isset(SBRC3_NS[$op])&&SBRC3_NS[$op]===$ns,'complete_edge_identity');
        if($complete[$idx]['operator_id']===null){$complete[$idx]['operator_id']=$op;$complete[$idx]['namespace']=$ns;}
        sbrc3_need($complete[$idx]['operator_id']===$op&&$complete[$idx]['namespace']===$ns,'batch_operator_drift');
        $complete[$idx]['catalogs'][$cid]=true;
    }
    foreach($complete as $idx=>$b)sbrc3_need($b['operator_id']!==null&&$b['catalogs']!==[],'complete_batch_empty');
    ksort($complete,SORT_NUMERIC);return $complete;
}
function sbrc3_extract_candidates(array $recovery,string $sealedDir):array{
    $complete=sbrc3_recovery_batches($recovery);$sealed=rtrim($sealedDir,'/');sbrc3_need(is_dir($sealed)&&basename($sealed)===SBRC3_SEALED_OP,'sealed_dir');
    $edges=[];$shape=['price_rows'=>0,'catalog_rows'=>0,'candidate_rows'=>0,'original_missing_hotelkey'=>0];$byOp=[];
    foreach($complete as $batch=>$meta){
        $requested=$meta['catalogs'];$operator=(int)$meta['operator_id'];$seen=[];
        $required=$meta['pages_count']===0?[1]:range(1,$meta['pages_count']);
        foreach($required as $page){
            $p=$sealed.'/evidence-private/batch-'.$batch.'-page-'.$page.'.json';sbrc3_need(is_file($p),'missing_complete_evidence_'.$batch.'_'.$page);
            $reply=sbrc3_load($p);sbrc3_need((int)($reply['PAGES_COUNT']??-1)===$meta['pages_count'],'pages_count_drift');
            foreach(($reply['PRICES']??[]) as $row){
                if(!is_array($row))continue;$shape['price_rows']++;
                $b=sbrc3_bridge($row,$operator,$requested);if(($b['state']??'')==='other_operator'||($b['state']??'')==='unbound')continue;
                $cid=(string)$b['catalog_id'];$shape['catalog_rows']++;$seen[$cid]['returned']=true;
                if(($b['state']??'')==='exact_catalog_to_native'){
                    $native=(string)$b['native_id'];$seen[$cid]['native'][$native]=true;$shape['candidate_rows']++;
                    $proj=['batch'=>$batch,'page'=>$page,'operator_id'=>$operator,'catalog_id'=>$cid,'native_id'=>$native,'top_operator_key'=>(int)($row['operatorKey']??0),'is_operator_hotel_key'=>(string)($row['isOperatorHotelKey']??''),'original_keys'=>array_values(array_keys(is_array($row['original']??null)?$row['original']:[]))];
                    $seen[$cid]['evidence_sha256'][hash('sha256',sbrc3_json($proj))]=true;
                }else $shape['original_missing_hotelkey']++;
            }
        }
        foreach(array_keys($requested) as $cid){
            $n=array_keys($seen[$cid]['native']??[]);sort($n,SORT_NATURAL);
            $st=count($n)===1?'candidate_single_native':(count($n)>1?'candidate_ambiguous_native':(!empty($seen[$cid]['returned'])?'catalog_only':'not_returned_in_complete_batch'));
            $row=['batch'=>$batch,'catalog_id'=>$cid,'operator_id'=>$operator,'supplier_namespace'=>SBRC3_NS[$operator],'state'=>$st,'native_ids'=>$n,'evidence_sha256s'=>array_keys($seen[$cid]['evidence_sha256']??[])];
            sort($row['evidence_sha256s'],SORT_STRING);$edges[]=$row;$byOp[(string)$operator][$st]=($byOp[(string)$operator][$st]??0)+1;
        }
    }
    usort($edges,fn($a,$b)=>[$a['supplier_namespace'],$a['catalog_id'],$a['batch']]<=>[$b['supplier_namespace'],$b['catalog_id'],$b['batch']]);ksort($byOp);
    return ['edges'=>$edges,'shape'=>$shape,'operator_candidate_states'=>$byOp];
}
function sbrc3_catalog_targets(array $bySource,array $ids):array{$out=[];foreach($ids as $cid){$t=[];foreach($bySource['andromeda_catalog|'.$cid]??[] as $r)if(($r['decision_status']??'')==='accepted'&&$r['local_hotel_id']!==null)$t[(int)$r['local_hotel_id']]=true;$out[$cid]=count($t)===1?(int)array_key_first($t):null;}return $out;}
function sbrc3_execute(PDO $db,array $recovery,string $recoverySha,string $sealedDir,string $sourceSha,string $opDir):array{
    $ex=sbrc3_extract_candidates($recovery,$sealedDir);$candidates=array_values(array_filter($ex['edges'],fn($e)=>$e['state']==='candidate_single_native'));
    $catalogIds=array_values(array_unique(array_map(fn($e)=>(string)$e['catalog_id'],$candidates)));sort($catalogIds,SORT_NATURAL);
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
    try{
        $ident=sbrc3_query($db,"SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status,catalog_sha256,evidence_sha256,evidence_json FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id,local_hotel_id");
        $bySource=[];$byTargetNs=[];$anchors=[];
        foreach($ident as $r){$ns=(string)$r['supplier_namespace'];$ext=(string)$r['external_hotel_id'];$bySource[$ns.'|'.$ext][]=$r;if(($r['decision_status']??'')==='accepted'&&$r['local_hotel_id']!==null){$local=(int)$r['local_hotel_id'];$byTargetNs[$ns.'|'.$local][]=$r;if($ns==='andromeda_catalog')$anchors[$local][]=$r;}}
        $targets=sbrc3_catalog_targets($bySource,$catalogIds);$locals=array_values(array_unique(array_filter(array_values($targets),fn($x)=>is_int($x)&&$x>0)));sort($locals,SORT_NUMERIC);
        $hotels=[];$manual=[];if($locals){$ph=implode(',',array_fill(0,count($locals),'?'));foreach(sbrc3_query($db,"SELECT id,name,country_name,is_active FROM catalog_hotels WHERE id IN ($ph)",$locals) as $r)$hotels[(int)$r['id']]=$r;foreach(sbrc3_query($db,"SELECT catalog_hotel_id FROM anex_hotel_decisions WHERE catalog_hotel_id IN ($ph)",$locals) as $r)$manual[(int)$r['catalog_hotel_id']]=true;}
        $projected=[];$sourceTargets=[];$targetSources=[];
        foreach($candidates as $e){$local=$targets[$e['catalog_id']]??null;$native=$e['native_ids'][0];$e['external_hotel_id']=$native;$e['local_hotel_id']=$local;$projected[]=$e;if(is_int($local)&&$local>0){$sourceTargets[$e['supplier_namespace'].'|'.$native][$local]=true;$targetSources[$e['supplier_namespace'].'|'.$local][$native]=true;}}
        $cal=[];$calIdentity=[];
        foreach($projected as $e){$op=(string)$e['operator_id'];$local=$e['local_hotel_id'];$key=$e['supplier_namespace'].'|'.$e['external_hotel_id'].'|'.(string)$local;if(isset($calIdentity[$key]))continue;$calIdentity[$key]=true;
            if(!is_int($local)||$local<1)$s='target_unresolved';else{$src=array_values(array_filter($bySource[$e['supplier_namespace'].'|'.$e['external_hotel_id']]??[],fn($r)=>($r['decision_status']??'')==='accepted'&&$r['local_hotel_id']!==null));$ts=[];foreach($src as $r)$ts[(int)$r['local_hotel_id']]=true;if(count($ts)===1&&(int)array_key_first($ts)===$local)$s='known_exact';elseif($ts)$s='conflict';else$s='previously_unknown';}
            $cal[$op][$s]=($cal[$op][$s]??0)+1;
        }
        $validated=[];foreach(SBRC3_NS as $op=>$ns){$x=$cal[(string)$op]??[];$validated[$op]=(($x['known_exact']??0)>0&&($x['conflict']??0)===0);}
        $groups=[];foreach($projected as $i=>$e){$local=$e['local_hotel_id'];if(!is_int($local)||$local<1)continue;$ik=$e['supplier_namespace'].'|'.$e['external_hotel_id'].'|'.$local;$groups[$ik][]=$i;}
        $status=[];$ready=[];$private=[];
        foreach($groups as $ik=>$idxs){
            $rep=$projected[$idxs[0]];$ns=$rep['supplier_namespace'];$native=$rep['external_hotel_id'];$local=(int)$rep['local_hotel_id'];$op=(int)$rep['operator_id'];$s='hold_unknown';
            $sk=$ns.'|'.$native;$tk=$ns.'|'.$local;$src=$bySource[$sk]??[];$foreign=array_filter($byTargetNs[$tk]??[],fn($r)=>(string)($r['external_hotel_id']??'')!==$native);$anchor=sbrc3_anchor_state($anchors[$local]??[]);
            if(count($sourceTargets[$sk]??[])>1)$s='hold_input_native_collision';
            elseif(count($targetSources[$tk]??[])>1)$s='hold_input_target_namespace_collision';
            elseif($src){$same=count($src)===1&&($src[0]['decision_status']??'')==='accepted'&&(int)($src[0]['local_hotel_id']??0)===$local;$s=$same?'resolved_same':'hold_source_occupied';}
            elseif(!isset($hotels[$local])||(int)($hotels[$local]['is_active']??0)!==1)$s='hold_target_inactive';
            elseif(sbrc3_excluded((string)($hotels[$local]['country_name']??'')))$s='hold_excluded_country';
            elseif(isset($manual[$local]))$s='hold_manual_target';
            elseif($foreign)$s='hold_target_namespace_occupied';
            elseif($anchor['state']!=='canonical_anchor_ok')$s='hold_'.$anchor['state'];
            elseif(!($validated[$op]??false))$s='hold_namespace_uncalibrated';
            else{$s='writer_ready';$ready[$ns]=($ready[$ns]??0)+1;}
            $status[$s]=($status[$s]??0)+1;
            if($s==='writer_ready'){$catalogs=[];$ev=[];foreach($idxs as $i){$catalogs[]=(string)$projected[$i]['catalog_id'];foreach($projected[$i]['evidence_sha256s'] as $h)$ev[$h]=true;}$catalogs=array_values(array_unique($catalogs));sort($catalogs,SORT_NATURAL);$evidence=array_keys($ev);sort($evidence,SORT_STRING);$private[]=['supplier_namespace'=>$ns,'external_hotel_id'=>$native,'local_hotel_id'=>$local,'operator_id'=>$op,'input_catalog_ids'=>$catalogs,'source_evidence_sha256s'=>$evidence,'anchor_catalog_sha256'=>$anchor['catalog_sha256'],'anchors'=>$anchor['anchors']];}
        }
        $db->rollBack();ksort($status);ksort($ready);ksort($cal);ksort($validated);usort($private,fn($a,$b)=>[$a['supplier_namespace'],$a['external_hotel_id'],$a['local_hotel_id']]<=>[$b['supplier_namespace'],$b['external_hotel_id'],$b['local_hotel_id']]);
        $manifest=['operation'=>SBRC3_OP,'source_recovery_operation'=>SBRC3_RECOVERY_OP,'source_recovery_sha256'=>$recoverySha,'rows'=>$private];$mh=sbrc3_save(rtrim($opDir,'/').'/writer-ready-private.json',$manifest);
        return ['operation'=>SBRC3_OP,'state'=>'completed_read_only_recovery_calibration','source_sha'=>$sourceSha,'source_recovery_operation'=>SBRC3_RECOVERY_OP,'source_recovery_sha256'=>$recoverySha,'sealed_operation'=>SBRC3_SEALED_OP,
            'fully_drained_batch_count'=>SBRC3_EXPECTED_COMPLETE,'partial_hold_batch_count'=>SBRC3_EXPECTED_PARTIAL,'candidate_shape'=>$ex['shape'],'operator_candidate_states'=>$ex['operator_candidate_states'],
            'candidate_single_native_edge_count'=>count($candidates),'calibration_counts'=>$cal,'namespace_calibrated'=>$validated,'status_counts'=>$status,'writer_ready_counts'=>$ready,'writer_ready_total'=>count($private),
            'private_writer_manifest_sha256'=>$mh,'provider_http_calls'=>0,'tourvisor_calls'=>0,'samo_calls'=>0,'anex_calls'=>0,'andromeda_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'safe_to_write_now'=>false];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function sbrc3_self_test():void{
    $req=['10'=>true];
    $x=sbrc3_bridge(['operatorKey'=>315,'hotelKey'=>'10','isOperatorHotelKey'=>0,'original'=>['hotel'=>'X','hotelKey'=>'99','tourKey'=>7]],315,$req);
    sbrc3_need($x===['state'=>'exact_catalog_to_native','catalog_id'=>'10','native_id'=>'99'],'top_level_operator_original_hotelkey');
    $x=sbrc3_bridge(['operatorKey'=>315,'hotelKey'=>'10','isOperatorHotelKey'=>0,'original'=>['hotel'=>'X','tourKey'=>7]],315,$req);sbrc3_need($x['state']==='catalog_only','missing_original_key');
    $x=sbrc3_bridge(['operatorKey'=>115,'hotelKey'=>'10','isOperatorHotelKey'=>0,'original'=>['hotelKey'=>'99']],315,$req);sbrc3_need($x['state']==='other_operator','operator_guard');
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    if(in_array('--self-test',$argv??[],true)){sbrc3_self_test();echo "MATCH_SAMO_BUSINESS_LIVE30_RECOVER_CALIBRATE_V3_SELFTEST_OK\n";exit;}
    sbrc3_need(($argv[1]??'')==='--execute','disabled');$root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$sealed=(string)getenv('MATCH_SEALED_OPERATION_DIR');$recoveryPath=(string)getenv('MATCH_RECOVERY_RESULT');$recoverySha=(string)getenv('MATCH_RECOVERY_RESULT_SHA');$sourceSha=(string)getenv('MATCH_SOURCE_SHA');
    sbrc3_need(is_dir($root)&&basename($root)==='anytoour.ru'&&is_dir($dir)&&basename($dir)===SBRC3_OP&&is_dir($sealed)&&basename($sealed)===SBRC3_SEALED_OP&&is_file($recoveryPath)&&sbrc3_sha($recoverySha)&&hash_file('sha256',$recoveryPath)===$recoverySha&&preg_match('/^[0-9a-f]{40}$/D',$sourceSha)===1,'runtime_scope');
    $reservation=sbrc3_load($dir.'/reservation.json');sbrc3_need(($reservation['operation']??'')===SBRC3_OP&&($reservation['state']??'')==='reserved_before_db_read','reservation');
    require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
    try{$out=sbrc3_execute(v2_data_db(),sbrc3_load($recoveryPath),$recoverySha,$sealed,$sourceSha,$dir);$h=sbrc3_save($dir.'/result.json',$out);sbrc3_save($dir.'/receipt.json',['operation'=>SBRC3_OP,'state'=>$out['state'],'result_sha256'=>$h,'readback_verified'=>hash_file('sha256',$dir.'/result.json')===$h,'provider_accessed'=>false,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'no_replay'=>true]);echo sbrc3_json(['state'=>$out['state'],'candidate_single_native_edge_count'=>$out['candidate_single_native_edge_count'],'calibration_counts'=>$out['calibration_counts'],'namespace_calibrated'=>$out['namespace_calibrated'],'status_counts'=>$out['status_counts'],'writer_ready_counts'=>$out['writer_ready_counts'],'writer_ready_total'=>$out['writer_ready_total'],'private_writer_manifest_sha256'=>$out['private_writer_manifest_sha256']])."\n";}
    catch(Throwable $e){$f=['operation'=>SBRC3_OP,'state'=>'failed_read_only_recovery_calibration','reason'=>preg_replace('/[^A-Za-z0-9_.:-]+/','_',mb_substr($e->getMessage(),0,180,'UTF-8')),'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0];$h=sbrc3_save($dir.'/result.json',$f);sbrc3_save($dir.'/receipt.json',['operation'=>SBRC3_OP,'state'=>$f['state'],'result_sha256'=>$h,'readback_verified'=>true,'provider_accessed'=>false,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'no_replay'=>true]);fwrite(STDERR,$f['reason']."\n");exit(2);}
}
