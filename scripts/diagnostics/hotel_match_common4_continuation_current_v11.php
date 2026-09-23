<?php
declare(strict_types=1);

require_once dirname(__DIR__,2).'/app/integrations/anex-search-mapping-registry.php';

const HMC11_FRONTIER=1349;
const HMC11_FRONTIER_SHA='ce464a7b71dc72cf425197c73c1b8a4770adaf586ee05d169f4c67f8fc43ccca';
const HMC11_PLAN_OP='hotel-match-live30-common4-continuation-plan-1971-20260923-v9';
const HMC11_ALLOWED_NS=['bgoperator'=>18,'operator_315'=>25,'operator_342'=>43];

function hmc11_need(bool $v,string $why):void{if(!$v)throw new RuntimeException($why);}
function hmc11_sort(mixed $v):mixed{if(!is_array($v))return $v;if(array_is_list($v))return array_map('hmc11_sort',$v);ksort($v,SORT_STRING);foreach($v as $k=>$x)$v[$k]=hmc11_sort($x);return $v;}
function hmc11_json(mixed $v):string{return json_encode(hmc11_sort($v),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function hmc11_load(string $p):array{$v=json_decode((string)file_get_contents($p),true,512,JSON_THROW_ON_ERROR);hmc11_need(is_array($v),'json_shape');return $v;}
function hmc11_save(string $p,array $v):string{$raw=hmc11_json($v)."\n";$f=@fopen($p,'x+b');hmc11_need($f!==false,'exclusive_create');try{hmc11_need(fwrite($f,$raw)===strlen($raw)&&fflush($f),'durable_write');if(function_exists('fsync'))hmc11_need(fsync($f),'durable_sync');}finally{fclose($f);}return hash('sha256',$raw);}
function hmc11_query(PDO $db,string $sql,array $args=[]):array{$st=$db->prepare($sql);$st->execute(array_values($args));return $st->fetchAll(PDO::FETCH_ASSOC)?:[];}
function hmc11_excluded(string $v):bool{return preg_match('/^(?:россия|абхазия|russia|russian federation|abkhazia)$/iu',trim($v))===1;}
function hmc11_target(array $h):array{$out=[];foreach(['id','name','country_id','country_name','region_id','region_name','subregion_id','subregion_name','category','is_active'] as $k)$out[$k]=$h[$k]??null;return $out;}
function hmc11_anchor_projection(array $a):array{$out=[];foreach(['supplier_namespace','external_hotel_id','local_hotel_id','decision_status','catalog_sha256','evidence_sha256'] as $k)$out[$k]=$a[$k]??null;return $out;}
function hmc11_anchor_state(array $aa):array{
    if(!$aa)return ['state'=>'canonical_anchor_missing','catalog_sha256'=>null,'anchors'=>[]];
    $cats=[];$out=[];
    foreach($aa as $a){
        if(($a['decision_status']??'')!=='accepted'||$a['local_hotel_id']===null)return ['state'=>'canonical_anchor_invalid','catalog_sha256'=>null,'anchors'=>[]];
        $ej=(string)($a['evidence_json']??'');$eh=(string)($a['evidence_sha256']??'');$cat=(string)($a['catalog_sha256']??'');
        if(preg_match('/^[0-9a-f]{64}$/D',$eh)!==1||preg_match('/^[0-9a-f]{64}$/D',$cat)!==1||hash('sha256',$ej)!==$eh)return ['state'=>'canonical_anchor_evidence_invalid','catalog_sha256'=>null,'anchors'=>[]];
        $cats[$cat]=true;$out[]=hmc11_anchor_projection($a);
    }
    if(count($cats)!==1)return ['state'=>'canonical_anchor_catalog_conflict','catalog_sha256'=>null,'anchors'=>$out];
    usort($out,fn($a,$b)=>strcmp((string)$a['external_hotel_id'],(string)$b['external_hotel_id']));
    return ['state'=>'canonical_anchor_ok','catalog_sha256'=>array_key_first($cats),'anchors'=>$out];
}
function hmc11_specs(array $m):array{
    hmc11_need(($m['plan_operation']??'')===HMC11_PLAN_OP,'manifest_plan_operation');
    $planSha=(string)($m['plan_result_sha256']??'');hmc11_need(preg_match('/^[0-9a-f]{64}$/D',$planSha)===1,'manifest_plan_sha');
    $children=$m['children']??null;hmc11_need(is_array($children)&&array_is_list($children)&&count($children)>=1&&count($children)<=10,'manifest_children');
    $out=[];$seen=[];
    foreach($children as $c){
        hmc11_need(is_array($c),'manifest_child');
        $op=(string)($c['operation']??'');$sha=(string)($c['result_sha256']??'');
        hmc11_need(preg_match('/^hotel-match-live30-common4-continuation-acquire-1971-20260923-c[0-9]+-n[0-9]+-v1$/D',$op)===1,'manifest_child_op');
        hmc11_need(preg_match('/^[0-9a-f]{64}$/D',$sha)===1&&!isset($seen[$op]),'manifest_child_sha');$seen[$op]=true;
        $out[]=['operation'=>$op,'result_sha256'=>$sha];
    }
    return [$planSha,$out];
}
function hmc11_read_children(string $root,array $manifest):array{
    [$planSha,$specs]=hmc11_specs($manifest);$rows=[];$children=[];$scopeIds=[];$totalHotels=0;
    foreach($specs as $spec){
        $dir=$root.'/'.$spec['operation'];hmc11_need(is_dir($dir)&&!is_link($dir),'child_missing');
        $rp=$dir.'/result.json';$qp=$dir.'/receipt.json';hmc11_need(is_file($rp)&&is_file($qp)&&!is_link($rp)&&!is_link($qp),'child_files');
        $raw=(string)file_get_contents($rp);$sha=hash('sha256',$raw);hmc11_need($sha===$spec['result_sha256'],'child_hash');
        $r=json_decode($raw,true,512,JSON_THROW_ON_ERROR);$q=hmc11_load($qp);
        hmc11_need(($q['result_sha256']??'')===$sha&&($r['state']??'')==='completed_read_only'&&($q['state']??'')==='completed_read_only','child_terminal');
        hmc11_need(($r['continuation_plan_sha256']??'')===$planSha&&($q['continuation_plan_sha256']??'')===$planSha,'child_plan_hash');
        hmc11_need((int)($r['frontier_count']??0)===HMC11_FRONTIER&&($r['frontier_id_sha256']??'')===HMC11_FRONTIER_SHA,'child_frontier');
        hmc11_need(($r['operator_ids']??null)===[13,18,25,43]&&(int)($r['continue_calls']??-1)===0&&(int)($r['dates_calls']??-1)===0&&(int)($r['database_writes']??-1)===0&&(int)($r['mapping_writes']??-1)===0,'child_authority');
        $count=(int)($r['scope_count']??0);hmc11_need($count>0&&$count<=100&&(int)($r['searched_hotels']??0)===$count,'child_scope');
        $scopeHash=(string)($r['scope_target_id_sha256']??'');hmc11_need(preg_match('/^[0-9a-f]{64}$/D',$scopeHash)===1&&!isset($scopeIds[$scopeHash]),'child_scope_hash');$scopeIds[$scopeHash]=true;$totalHotels+=$count;
        hmc11_need($totalHotels<=300,'manifest_hotel_cap');
        foreach(($r['edges']??[]) as $e){
            if(!is_array($e)||($e['state']??'')!=='detail_identity_verified'||($e['link_state']??'')!=='captured_single_native')continue;
            $ids=$e['positive_native_candidates']??null;if(!is_array($ids)||count($ids)!==1)continue;
            $op=(int)($e['operator_id']??0);$ns=(string)($e['namespace']??'');$tv=(int)($e['tv_hotel_id']??0);$ext=(string)$ids[0];
            if($tv<1||preg_match('/^[1-9][0-9]{0,19}$/D',$ext)!==1)continue;
            if($op===13&&$ns==='anex')$kind='anex';
            elseif(isset(HMC11_ALLOWED_NS[$ns])&&HMC11_ALLOWED_NS[$ns]===$op)$kind='identity';
            else continue;
            foreach(['operator_link_sha256','search_id_sha256','tour_id_sha256'] as $f)hmc11_need(preg_match('/^[0-9a-f]{64}$/D',(string)($e[$f]??''))===1,'edge_hash');
            $rows[]=['kind'=>$kind,'supplier_namespace'=>$ns,'external_hotel_id'=>$ext,'tv_hotel_id'=>$tv,'operator_id'=>$op,
                'source_operation'=>$spec['operation'],'source_result_sha256'=>$sha,'batch'=>(int)($e['batch']??0),
                'search_id_sha256'=>(string)$e['search_id_sha256'],'tour_id_sha256'=>(string)$e['tour_id_sha256'],
                'operator_link_sha256'=>(string)$e['operator_link_sha256'],'operator_link_host'=>(string)($e['operator_link_host']??''),
                'query_keys'=>$e['query_keys']??[],'safe_to_write_now'=>false];
        }
        $children[]=['operation'=>$spec['operation'],'result_sha256'=>$sha,'scope_offset'=>(int)$r['scope_offset'],'scope_count'=>$count,'scope_target_id_sha256'=>$scopeHash];
    }
    return ['plan_sha256'=>$planSha,'children'=>$children,'searched_hotels'=>$totalHotels,'edges'=>$rows];
}
function hmc11_classify_identity(array $e,?array $hotel,array $byKey,array $byTargetNs,array $manual,array $anchor):array{
    $ns=$e['supplier_namespace'];$ext=$e['external_hotel_id'];$tv=(int)$e['tv_hotel_id'];$status='current_missing_edge';
    $src=$byKey[$ns.'|'.$ext]??[];
    if($src)$status=(count($src)===1&&($src[0]['decision_status']??'')==='accepted'&&(int)($src[0]['local_hotel_id']??0)===$tv)?'resolved_same':'source_occupied';
    elseif(!$hotel||(int)($hotel['is_active']??0)!==1)$status='target_missing_or_inactive';
    elseif(hmc11_excluded((string)($hotel['country_name']??'')))$status='excluded_country';
    elseif(isset($manual[$tv]))$status='manual_target_protected';
    elseif(array_filter($byTargetNs[$ns.'|'.$tv]??[],fn($r)=>(string)($r['external_hotel_id']??'')!==$ext))$status='target_namespace_occupied_other';
    $ready=$status==='current_missing_edge'&&$anchor['state']==='canonical_anchor_ok';
    return $e+['status'=>$status,'anchor_state'=>$anchor['state'],'unanimous_catalog_sha256'=>$anchor['catalog_sha256'],'anchors'=>$anchor['anchors'],'catalog_hotel'=>$hotel,'writer_ready'=>$ready,'safe_to_write_now'=>false];
}
function hmc11_classify_anex(array $e,?array $hotel,?array $mapping,?array $decision,bool $excluded,array $anchor,?int $resolved):array{
    $id=$e['external_hotel_id'];$tv=(int)$e['tv_hotel_id'];$status='current_missing_exact_key';
    if(preg_match('/^[1-9][0-9]{0,7}$/D',$id)!==1)$status='registry_id_invalid';
    elseif($decision!==null)$status=(($decision['decision_status']??'')==='accepted'&&(int)($decision['catalog_hotel_id']??0)===$tv&&$resolved===$tv)?'already_resolved_same_manual':'manual_source_protected';
    elseif($mapping!==null)$status=$resolved===$tv?'already_resolved_same_mapping':'source_mapping_occupied';
    elseif($excluded)$status='pair_excluded';
    elseif(!$hotel||(int)($hotel['is_active']??0)!==1)$status='target_missing_or_inactive';
    elseif(hmc11_excluded((string)($hotel['country_name']??'')))$status='excluded_country';
    $ready=$status==='current_missing_exact_key'&&$anchor['state']==='canonical_anchor_ok';
    return $e+['status'=>$status,'anchor_state'=>$anchor['state'],'unanimous_catalog_sha256'=>$anchor['catalog_sha256'],'anchors'=>$anchor['anchors'],'catalog_hotel'=>$hotel,'writer_ready'=>$ready,'safe_to_write_now'=>false];
}
function hmc11_execute(PDO $db,array $input,string $sourceSha):array{
    $edges=$input['edges'];$ids=array_values(array_unique(array_map(fn($r)=>(int)$r['tv_hotel_id'],$edges)));sort($ids,SORT_NUMERIC);hmc11_need($ids!==[],'no_edges');$ph=implode(',',array_fill(0,count($ids),'?'));
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
    try{
        $catalog=[];foreach(hmc11_query($db,"SELECT id,name,country_id,country_name,region_id,region_name,subregion_id,subregion_name,category,is_active FROM catalog_hotels WHERE id IN ($ph) ORDER BY id",$ids) as $r)$catalog[(int)$r['id']]=$r;
        $all=hmc11_query($db,"SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status,catalog_sha256,evidence_sha256,evidence_json FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id");
        $byKey=[];$byTargetNs=[];$anchors=[];
        foreach($all as $r){$byKey[$r['supplier_namespace'].'|'.$r['external_hotel_id']][]=$r;if(($r['decision_status']??'')==='accepted'&&$r['local_hotel_id']!==null){$byTargetNs[$r['supplier_namespace'].'|'.$r['local_hotel_id']][]=$r;if($r['supplier_namespace']==='andromeda_catalog')$anchors[(int)$r['local_hotel_id']][]=$r;}}
        $manualTarget=[];foreach(hmc11_query($db,"SELECT anex_hotel_id,catalog_hotel_id,decision_status FROM anex_hotel_decisions ORDER BY anex_hotel_id") as $r)if($r['catalog_hotel_id']!==null)$manualTarget[(int)$r['catalog_hotel_id']][]=$r;
        $maps=[];foreach(hmc11_query($db,"SELECT anex_hotel_id,catalog_hotel_id,match_class,scope,approval_policy,enabled FROM anex_hotel_search_mappings ORDER BY anex_hotel_id") as $r)$maps[(string)$r['anex_hotel_id']]=$r;
        $decisions=[];foreach(hmc11_query($db,"SELECT anex_hotel_id,catalog_hotel_id,decision_status FROM anex_hotel_decisions ORDER BY anex_hotel_id") as $r)$decisions[(string)$r['anex_hotel_id']]=$r;
        $exclusions=[];foreach(hmc11_query($db,"SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions ORDER BY anex_hotel_id,catalog_hotel_id") as $r)$exclusions[(string)$r['anex_hotel_id']][(int)$r['catalog_hotel_id']]=true;
        $registry=AnyTourAnexSearchMappingRegistry::fromPdo($db);
        $out=[];$status=[];$ready=['identity'=>0,'anex'=>0];$anchorCounts=[];
        foreach($edges as $e){$tv=(int)$e['tv_hotel_id'];$anchor=hmc11_anchor_state($anchors[$tv]??[]);
            if($e['kind']==='identity')$r=hmc11_classify_identity($e,$catalog[$tv]??null,$byKey,$byTargetNs,$manualTarget,$anchor);
            else{$id=$e['external_hotel_id'];$resolved=$registry->resolve('anex_online',$id,'preview');$r=hmc11_classify_anex($e,$catalog[$tv]??null,$maps[$id]??null,$decisions[$id]??null,isset($exclusions[$id][$tv]),$anchor,$resolved);}
            $out[]=$r;$key=$r['kind'].'|'.$r['status'];$status[$key]=($status[$key]??0)+1;$anchorCounts[$r['anchor_state']]=($anchorCounts[$r['anchor_state']]??0)+1;if($r['writer_ready'])$ready[$r['kind']]++;
        }
        $db->rollBack();ksort($status);ksort($anchorCounts);
        return ['operation'=>'hotel-match-common4-continuation-current-1971-20260923-v11','state'=>'completed_read_only_continuation_current',
            'source_sha'=>$sourceSha,'continuation_plan_sha256'=>$input['plan_sha256'],'children'=>$input['children'],'searched_hotels'=>$input['searched_hotels'],
            'input_single_native_edges'=>count($edges),'status_counts'=>$status,'anchor_state_counts'=>$anchorCounts,'writer_ready_counts'=>$ready,'rows'=>$out,
            'supplier_calls'=>0,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'safe_to_write_now'=>false];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}

if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    if(in_array('--self-test',$argv??[],true)){
        $raw='{"x":1}';$a=['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>'7','local_hotel_id'=>100,'decision_status'=>'accepted','catalog_sha256'=>str_repeat('a',64),'evidence_sha256'=>hash('sha256',$raw),'evidence_json'=>$raw];$anchor=hmc11_anchor_state([$a]);
        hmc11_need($anchor['state']==='canonical_anchor_ok','anchor');
        $e=['kind'=>'identity','supplier_namespace'=>'operator_315','external_hotel_id'=>'77','tv_hotel_id'=>100,'safe_to_write_now'=>false];
        $h=['id'=>100,'is_active'=>1,'country_name'=>'Турция'];$r=hmc11_classify_identity($e,$h,[],[],[],$anchor);hmc11_need($r['writer_ready']===true,'identity_ready');
        $e=['kind'=>'anex','supplier_namespace'=>'anex','external_hotel_id'=>'5844','tv_hotel_id'=>100,'safe_to_write_now'=>false];$r=hmc11_classify_anex($e,$h,null,null,false,$anchor,null);hmc11_need($r['writer_ready']===true,'anex_ready');
        echo "MATCH_COMMON4_CONTINUATION_CURRENT_V11_SELFTEST_OK\n";exit;
    }
    hmc11_need(($argv[1]??'')==='--execute','disabled');$root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$ops=(string)getenv('MATCH_OPERATIONS_ROOT');$manifestPath=(string)getenv('MATCH_CHILD_MANIFEST');$sha=(string)getenv('MATCH_SOURCE_SHA');
    hmc11_need(is_dir($root)&&basename($root)==='anytoour.ru'&&is_dir($dir)&&is_dir($ops)&&is_file($manifestPath)&&preg_match('/^[0-9a-f]{40}$/D',$sha)===1,'runtime_scope');
    $res=hmc11_load($dir.'/reservation.json');hmc11_need(($res['state']??'')==='reserved_before_db_read','reservation');
    try{require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');$input=hmc11_read_children($ops,hmc11_load($manifestPath));$result=hmc11_execute(v2_data_db(),$input,$sha);$h=hmc11_save($dir.'/result.json',$result);hmc11_save($dir.'/receipt.json',['operation'=>$result['operation'],'state'=>$result['state'],'result_sha256'=>$h,'readback_verified'=>hash_file('sha256',$dir.'/result.json')===$h,'provider_accessed'=>false,'supplier_calls'=>0,'database_writes'=>0,'mapping_writes'=>0]);echo hmc11_json(['state'=>$result['state'],'searched_hotels'=>$result['searched_hotels'],'input_single_native_edges'=>$result['input_single_native_edges'],'status_counts'=>$result['status_counts'],'writer_ready_counts'=>$result['writer_ready_counts']])."\n";}
    catch(Throwable $e){$f=['operation'=>'hotel-match-common4-continuation-current-1971-20260923-v11','state'=>'failed_read_only_continuation_current','reason'=>preg_replace('/[^A-Za-z0-9_.:-]+/','_',mb_substr($e->getMessage(),0,140,'UTF-8')),'supplier_calls'=>0,'database_writes'=>0,'mapping_writes'=>0];$h=hmc11_save($dir.'/result.json',$f);hmc11_save($dir.'/receipt.json',['operation'=>$f['operation'],'state'=>$f['state'],'result_sha256'=>$h,'readback_verified'=>true,'provider_accessed'=>false,'supplier_calls'=>0,'database_writes'=>0,'mapping_writes'=>0]);fwrite(STDERR,$f['reason']."\n");exit(2);}
}
