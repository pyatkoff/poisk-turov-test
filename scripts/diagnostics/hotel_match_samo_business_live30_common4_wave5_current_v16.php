<?php
declare(strict_types=1);

const SBLC4C4_OP='hotel-match-samo-business-live30-common4-wave5-current-1971-20260925-v16c';
const SBLC4C4_SOURCE_OP='hotel-match-samo-business-live30-common4-wave5-recovery-acquire-1971-20260925-v15';
const SBLC4C4_PLAN_OP='hotel-match-samo-business-live30-common4-plan-1971-20260924-v2';
const SBLC4C4_EXPECTED_EDGES=1217;
const SBLC4C4_EXPECTED_CAPTURED=508;
const SBLC4C4_NS=[5=>'operator_5',115=>'operator_115',315=>'operator_315',342=>'operator_342'];

function sb4_need(bool $v,string $why):void{if(!$v)throw new RuntimeException($why);}
function sb4_sort(mixed $v):mixed{
    if(!is_array($v))return $v;
    if(array_is_list($v))return array_map('sb4_sort',$v);
    ksort($v,SORT_STRING);foreach($v as $k=>$x)$v[$k]=sb4_sort($x);return $v;
}
function sb4_json(mixed $v):string{return json_encode(sb4_sort($v),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function sb4_load(string $p):array{$v=json_decode((string)file_get_contents($p),true,512,JSON_THROW_ON_ERROR);sb4_need(is_array($v),'json_shape');return $v;}
function sb4_save(string $p,array $v):string{$raw=sb4_json($v)."\n";$f=@fopen($p,'x+b');sb4_need($f!==false,'exclusive_create');try{sb4_need(fwrite($f,$raw)===strlen($raw)&&fflush($f),'durable_write');if(function_exists('fsync'))sb4_need(fsync($f),'durable_sync');}finally{fclose($f);}return hash('sha256',$raw);}
function sb4_query(PDO $db,string $sql,array $args=[]):array{$st=$db->prepare($sql);$st->execute(array_values($args));return $st->fetchAll(PDO::FETCH_ASSOC)?:[];}
function sb4_id(mixed $v):?string{$s=trim((string)$v);return preg_match('/^[1-9][0-9]{0,21}$/D',$s)===1?$s:null;}
function sb4_sha(mixed $v):bool{return is_string($v)&&preg_match('/^[0-9a-f]{64}$/D',$v)===1;}
function sb4_excluded(string $v):bool{return preg_match('/^(?:россия|абхазия|russia|russian federation|abkhazia)$/iu',trim($v))===1;}
function sb4_target(array $h):array{$out=[];foreach(['id','name','country_id','country_name','region_id','region_name','subregion_id','subregion_name','category','is_active'] as $k)$out[$k]=$h[$k]??null;return $out;}
function sb4_anchor_projection(array $a):array{$out=[];foreach(['supplier_namespace','external_hotel_id','local_hotel_id','decision_status','catalog_sha256','evidence_sha256'] as $k)$out[$k]=$a[$k]??null;return $out;}
function sb4_anchor_state(array $aa):array{
    if(!$aa)return ['state'=>'canonical_anchor_missing','catalog_sha256'=>null,'anchors'=>[]];
    $cats=[];$out=[];
    foreach($aa as $a){
        if(($a['supplier_namespace']??'')!=='andromeda_catalog'||($a['decision_status']??'')!=='accepted'||$a['local_hotel_id']===null)return ['state'=>'canonical_anchor_invalid','catalog_sha256'=>null,'anchors'=>[]];
        $raw=(string)($a['evidence_json']??'');$eh=(string)($a['evidence_sha256']??'');$cat=(string)($a['catalog_sha256']??'');
        if(!sb4_sha($eh)||!sb4_sha($cat)||hash('sha256',$raw)!==$eh)return ['state'=>'canonical_anchor_evidence_invalid','catalog_sha256'=>null,'anchors'=>[]];
        $cats[$cat]=true;$out[]=sb4_anchor_projection($a);
    }
    usort($out,fn($a,$b)=>strcmp((string)$a['external_hotel_id'],(string)$b['external_hotel_id']));
    return count($cats)===1?['state'=>'canonical_anchor_ok','catalog_sha256'=>array_key_first($cats),'anchors'=>$out]:['state'=>'canonical_anchor_catalog_conflict','catalog_sha256'=>null,'anchors'=>$out];
}
function sb4_source_edges(array $source,string $sourceResultSha):array{
    sb4_need(($source['operation']??'')===SBLC4C4_SOURCE_OP,'source_operation');
    sb4_need(($source['state']??'')==='completed_read_only','source_state');
    sb4_need(($source['plan_operation']??'')===SBLC4C4_PLAN_OP,'source_plan');
    sb4_need((int)($source['global_group_count']??0)===502&&(int)($source['group_start']??0)===401&&(int)($source['group_end']??0)===502,'source_group_scope');
    sb4_need((int)($source['queried_edge_count']??0)===SBLC4C4_EXPECTED_EDGES,'source_edge_count');
    sb4_need((int)($source['planned_batch_count']??0)===102&&(int)($source['completed_batch_count']??0)===102&&(int)($source['batch_error_count']??-1)===0,'source_group_counts');
    sb4_need((int)($source['single_native_source_unique_count']??0)===SBLC4C4_EXPECTED_CAPTURED&&(int)($source['single_native_source_collision_count']??-1)===0,'source_native_counts');
    sb4_need((int)($source['samo_http_calls']??0)===103,'source_samo_calls');
    foreach(['tourvisor_calls','direct_anex_calls','database_writes','mapping_writes'] as $k)sb4_need((int)($source[$k]??-1)===0,'source_zero_'.$k);
    sb4_need(($source['safe_to_write_now']??null)===false,'source_safe_flag');
    $edges=$source['edges']??null;sb4_need(is_array($edges)&&count($edges)===SBLC4C4_EXPECTED_EDGES,'source_edges');
    $out=[];$allKeys=[];$captured=0;
    foreach($edges as $i=>$e){
        sb4_need(is_array($e),'edge_shape');$catalog=sb4_id($e['catalog_id']??null);$op=(int)($e['operator_id']??0);$ns=(string)($e['namespace']??'');
        sb4_need($catalog!==null&&isset(SBLC4C4_NS[$op])&&SBLC4C4_NS[$op]===$ns,'edge_identity');
        $edgeKey=$catalog.'|'.$op;sb4_need(!isset($allKeys[$edgeKey]),'edge_duplicate');$allKeys[$edgeKey]=true;
        if(($e['state']??'')!=='captured_single_native')continue;
        $captured++;
        $ids=$e['positive_native_candidates']??null;sb4_need(is_array($ids)&&count($ids)===1,'single_native_shape');$native=sb4_id($ids[0]??null);sb4_need($native!==null,'single_native_id');
        $digest=hash('sha256',sb4_json($e));
        $out[]=['catalog_id'=>$catalog,'operator_id'=>$op,'supplier_namespace'=>$ns,'external_hotel_id'=>$native,'source_edge_sha256'=>$digest,'source_edge_index'=>$i,'source_result_sha256'=>$sourceResultSha,'source_operation'=>SBLC4C4_SOURCE_OP,'safe_to_write_now'=>false];
    }
    sb4_need($captured===SBLC4C4_EXPECTED_CAPTURED&&count($out)===SBLC4C4_EXPECTED_CAPTURED,'captured_count');
    usort($out,fn($a,$b)=>[$a['supplier_namespace'],$a['external_hotel_id'],$a['catalog_id'],$a['operator_id']]<=>[$b['supplier_namespace'],$b['external_hotel_id'],$b['catalog_id'],$b['operator_id']]);
    return $out;
}
function sb4_catalog_targets(array $bySource,array $catalogIds):array{
    $out=[];
    foreach($catalogIds as $cid){
        $targets=[];foreach($bySource['andromeda_catalog|'.$cid]??[] as $r)if(($r['decision_status']??'')==='accepted'&&$r['local_hotel_id']!==null)$targets[(int)$r['local_hotel_id']]=true;
        $out[$cid]=count($targets)===1?(int)array_key_first($targets):null;
    }
    return $out;
}
function sb4_group_input(array $edges,array $targets):array{
    $sourceTargets=[];$targetSources=[];$exact=[];
    foreach($edges as $e){
        $local=$targets[$e['catalog_id']]??null;if(!is_int($local)||$local<1)continue;
        $sk=$e['supplier_namespace'].'|'.$e['external_hotel_id'];$tk=$e['supplier_namespace'].'|'.$local;$ik=$sk.'|'.$local;
        $sourceTargets[$sk][$local]=true;$targetSources[$tk][$e['external_hotel_id']]=true;$exact[$ik][]=$e;
    }
    foreach($exact as &$rows)usort($rows,fn($a,$b)=>[$a['catalog_id'],$a['source_edge_sha256']]<=>[$b['catalog_id'],$b['source_edge_sha256']]);unset($rows);
    return ['source_targets'=>$sourceTargets,'target_sources'=>$targetSources,'exact'=>$exact];
}
function sb4_execute(PDO $db,array $source,string $sourceResultSha,string $sourceSha):array{
    $edges=sb4_source_edges($source,$sourceResultSha);sb4_need($edges!==[],'no_single_native_edges');
    $catalogIds=array_values(array_unique(array_map(fn($e)=>(string)$e['catalog_id'],$edges)));sort($catalogIds,SORT_NATURAL);
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
    try{
        $ident=sb4_query($db,"SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status,catalog_sha256,evidence_sha256,evidence_json FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id,local_hotel_id");
        $bySource=[];$byTargetNs=[];$anchors=[];
        foreach($ident as $r){
            $ns=(string)$r['supplier_namespace'];$ext=(string)$r['external_hotel_id'];$bySource[$ns.'|'.$ext][]=$r;
            if(($r['decision_status']??'')==='accepted'&&$r['local_hotel_id']!==null){$local=(int)$r['local_hotel_id'];$byTargetNs[$ns.'|'.$local][]=$r;if($ns==='andromeda_catalog')$anchors[$local][]=$r;}
        }
        $targets=sb4_catalog_targets($bySource,$catalogIds);$locals=array_values(array_unique(array_filter(array_values($targets),fn($x)=>is_int($x)&&$x>0)));sort($locals,SORT_NUMERIC);
        $hotels=[];$manual=[];
        if($locals){$ph=implode(',',array_fill(0,count($locals),'?'));foreach(sb4_query($db,"SELECT id,name,country_id,country_name,region_id,region_name,subregion_id,subregion_name,category,is_active FROM catalog_hotels WHERE id IN ($ph)",$locals) as $r)$hotels[(int)$r['id']]=$r;
            foreach(sb4_query($db,"SELECT catalog_hotel_id FROM anex_hotel_decisions WHERE catalog_hotel_id IN ($ph)",$locals) as $r)$manual[(int)$r['catalog_hotel_id']]=true;}
        $input=sb4_group_input($edges,$targets);$rows=[];$status=[];$anchorCounts=[];$ready=[];$representatives=[];
        foreach($edges as $e){
            $cid=$e['catalog_id'];$ns=$e['supplier_namespace'];$native=$e['external_hotel_id'];$local=$targets[$cid]??null;$s='hold_unknown';$anchor=['state'=>'catalog_target_unresolved','catalog_sha256'=>null,'anchors'=>[]];$catalogs=[$cid];$digests=[$e['source_edge_sha256']];
            if(!is_int($local)||$local<1)$s='hold_catalog_target_unresolved';
            else{
                $anchor=sb4_anchor_state($anchors[$local]??[]);$sk=$ns.'|'.$native;$tk=$ns.'|'.$local;$ik=$sk.'|'.$local;
                $group=$input['exact'][$ik]??[];$catalogs=array_values(array_unique(array_map(fn($x)=>(string)$x['catalog_id'],$group)));sort($catalogs,SORT_NATURAL);$digests=array_values(array_unique(array_map(fn($x)=>(string)$x['source_edge_sha256'],$group)));sort($digests,SORT_STRING);
                $rep=$group[0]??$e;$isRep=$rep['catalog_id']===$cid&&$rep['source_edge_sha256']===$e['source_edge_sha256'];
                $src=$bySource[$sk]??[];$foreign=array_filter($byTargetNs[$tk]??[],fn($r)=>(string)($r['external_hotel_id']??'')!==$native);
                if(count($input['source_targets'][$sk]??[])>1)$s='hold_input_native_collision';
                elseif(count($input['target_sources'][$tk]??[])>1)$s='hold_input_target_namespace_collision';
                elseif(!$isRep)$s='input_duplicate_same_identity';
                elseif($src){$same=count($src)===1&&($src[0]['decision_status']??'')==='accepted'&&(int)($src[0]['local_hotel_id']??0)===$local;$s=$same?'resolved_same':'hold_source_occupied';}
                elseif(!isset($hotels[$local])||(int)($hotels[$local]['is_active']??0)!==1)$s='hold_target_inactive';
                elseif(sb4_excluded((string)($hotels[$local]['country_name']??'')))$s='hold_excluded_country';
                elseif(isset($manual[$local]))$s='hold_manual_target';
                elseif($foreign)$s='hold_target_namespace_occupied';
                elseif($anchor['state']!=='canonical_anchor_ok')$s='hold_'.$anchor['state'];
                else{$s='writer_ready';$ready[$ns]=($ready[$ns]??0)+1;$representatives[$ik]=true;}
            }
            $status[$s]=($status[$s]??0)+1;$anchorCounts[$anchor['state']]=($anchorCounts[$anchor['state']]??0)+1;
            $rows[]=$e+['local_hotel_id'=>$local,'status'=>$s,'anchor_state'=>$anchor['state'],'unanimous_catalog_sha256'=>$anchor['catalog_sha256'],'anchors'=>$anchor['anchors'],'catalog_hotel'=>is_int($local)?($hotels[$local]??null):null,'input_catalog_ids'=>$catalogs,'source_edge_sha256s'=>$digests,'writer_ready'=>$s==='writer_ready','safe_to_write_now'=>false];
        }
        $db->rollBack();ksort($status);ksort($anchorCounts);ksort($ready);
        return ['operation'=>SBLC4C4_OP,'state'=>'completed_read_only_samo_business_common4_current','source_sha'=>$sourceSha,'source_operation'=>SBLC4C4_SOURCE_OP,'source_result_sha256'=>$sourceResultSha,'source_state'=>$source['state'],
            'queried_edge_count'=>(int)$source['queried_edge_count'],'captured_single_native_edge_count'=>count($edges),'unique_input_identity_count'=>count($input['exact']),'deduped_same_identity_edges'=>count($edges)-count($input['exact']),
            'status_counts'=>$status,'anchor_state_counts'=>$anchorCounts,'writer_ready_counts'=>$ready,'writer_ready_total'=>array_sum($ready),'rows'=>$rows,
            'provider_http_calls'=>0,'tourvisor_calls'=>0,'samo_calls'=>0,'anex_calls'=>0,'andromeda_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'safe_to_write_now'=>false];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function sb4_self_test():void{
    $edges=[
        ['catalog_id'=>'10','operator_id'=>115,'supplier_namespace'=>'operator_115','external_hotel_id'=>'900','source_edge_sha256'=>'a','source_edge_index'=>0],
        ['catalog_id'=>'11','operator_id'=>115,'supplier_namespace'=>'operator_115','external_hotel_id'=>'900','source_edge_sha256'=>'b','source_edge_index'=>1],
        ['catalog_id'=>'12','operator_id'=>115,'supplier_namespace'=>'operator_115','external_hotel_id'=>'901','source_edge_sha256'=>'c','source_edge_index'=>2],
    ];
    $g=sb4_group_input($edges,['10'=>100,'11'=>100,'12'=>100]);
    sb4_need(count($g['exact'])===2,'self_exact_dedupe');sb4_need(count($g['source_targets']['operator_115|900'])===1,'self_source_target');sb4_need(count($g['target_sources']['operator_115|100'])===2,'self_target_collision');
    $raw='{"x":1}';$a=['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>'10','local_hotel_id'=>100,'decision_status'=>'accepted','catalog_sha256'=>str_repeat('a',64),'evidence_sha256'=>hash('sha256',$raw),'evidence_json'=>$raw];
    sb4_need(sb4_anchor_state([$a])['state']==='canonical_anchor_ok','self_anchor');
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    if(in_array('--self-test',$argv??[],true)){sb4_self_test();echo "MATCH_SAMO_BUSINESS_LIVE30_WAVE4_CURRENT_V12_SELFTEST_OK\n";exit;}
    sb4_need(($argv[1]??'')==='--execute','disabled');$root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$sourcePath=(string)getenv('MATCH_SOURCE_RESULT');$sourceResultSha=(string)getenv('MATCH_SOURCE_RESULT_SHA');$sourceSha=(string)getenv('MATCH_SOURCE_SHA');
    sb4_need(is_dir($root)&&basename($root)==='anytoour.ru'&&is_dir($dir)&&basename($dir)===SBLC4C4_OP&&is_file($sourcePath)&&sb4_sha($sourceResultSha)&&hash_file('sha256',$sourcePath)===$sourceResultSha&&preg_match('/^[0-9a-f]{40}$/D',$sourceSha)===1,'runtime_scope');
    $reservation=sb4_load($dir.'/reservation.json');sb4_need(($reservation['operation']??'')===SBLC4C4_OP&&($reservation['state']??'')==='reserved_before_db_read','reservation');
    require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
    try{$out=sb4_execute(v2_data_db(),sb4_load($sourcePath),$sourceResultSha,$sourceSha);$h=sb4_save($dir.'/result.json',$out);sb4_save($dir.'/receipt.json',['operation'=>SBLC4C4_OP,'state'=>$out['state'],'result_sha256'=>$h,'readback_verified'=>hash_file('sha256',$dir.'/result.json')===$h,'provider_accessed'=>false,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'no_replay'=>true]);echo sb4_json(['state'=>$out['state'],'captured_single_native_edge_count'=>$out['captured_single_native_edge_count'],'unique_input_identity_count'=>$out['unique_input_identity_count'],'status_counts'=>$out['status_counts'],'writer_ready_counts'=>$out['writer_ready_counts'],'writer_ready_total'=>$out['writer_ready_total']])."\n";}
    catch(Throwable $e){$f=['operation'=>SBLC4C4_OP,'state'=>'failed_read_only_samo_business_common4_current','reason'=>preg_replace('/[^A-Za-z0-9_.:-]+/','_',mb_substr($e->getMessage(),0,160,'UTF-8')),'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0];$h=sb4_save($dir.'/result.json',$f);sb4_save($dir.'/receipt.json',['operation'=>SBLC4C4_OP,'state'=>$f['state'],'result_sha256'=>$h,'readback_verified'=>true,'provider_accessed'=>false,'database_writes'=>0,'mapping_writes'=>0,'no_replay'=>true]);fwrite(STDERR,$f['reason']."\n");exit(2);}
}
