<?php
declare(strict_types=1);

require_once dirname(__DIR__,2).'/app/integrations/anex-search-mapping-registry.php';

const HMC13_ALLOWED_NS=['bgoperator'=>18,'operator_315'=>25,'operator_342'=>43];

function hmc13_need(bool $v,string $why):void{if(!$v)throw new RuntimeException($why);}
function hmc13_sort(mixed $v):mixed{if(!is_array($v))return $v;if(array_is_list($v))return array_map('hmc13_sort',$v);ksort($v,SORT_STRING);foreach($v as $k=>$x)$v[$k]=hmc13_sort($x);return $v;}
function hmc13_json(mixed $v):string{return json_encode(hmc13_sort($v),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function hmc13_load(string $p):array{$v=json_decode((string)file_get_contents($p),true,512,JSON_THROW_ON_ERROR);hmc13_need(is_array($v),'json_shape');return $v;}
function hmc13_save(string $p,array $v):string{$raw=hmc13_json($v)."\n";$f=@fopen($p,'x+b');hmc13_need($f!==false,'exclusive_create');try{hmc13_need(fwrite($f,$raw)===strlen($raw)&&fflush($f),'durable_write');if(function_exists('fsync'))hmc13_need(fsync($f),'durable_sync');}finally{fclose($f);}return hash('sha256',$raw);}
function hmc13_query(PDO $db,string $sql,array $args=[]):array{$st=$db->prepare($sql);$st->execute(array_values($args));return $st->fetchAll(PDO::FETCH_ASSOC)?:[];}
function hmc13_excluded(string $v):bool{return preg_match('/^(?:россия|абхазия|russia|russian federation|abkhazia)$/iu',trim($v))===1;}
function hmc13_target(array $h):array{$out=[];foreach(['id','name','country_id','country_name','region_id','region_name','subregion_id','subregion_name','category','is_active'] as $k)$out[$k]=$h[$k]??null;return $out;}
function hmc13_anchor_projection(array $a):array{$out=[];foreach(['supplier_namespace','external_hotel_id','local_hotel_id','decision_status','catalog_sha256','evidence_sha256'] as $k)$out[$k]=$a[$k]??null;return $out;}
function hmc13_anchor_state(array $aa):array{
    if(!$aa)return ['state'=>'canonical_anchor_missing','catalog_sha256'=>null,'anchors'=>[]];
    $cats=[];$out=[];
    foreach($aa as $a){
        if(($a['decision_status']??'')!=='accepted'||$a['local_hotel_id']===null)return ['state'=>'canonical_anchor_invalid','catalog_sha256'=>null,'anchors'=>[]];
        $ej=(string)($a['evidence_json']??'');$eh=(string)($a['evidence_sha256']??'');$cat=(string)($a['catalog_sha256']??'');
        if(preg_match('/^[0-9a-f]{64}$/D',$eh)!==1||preg_match('/^[0-9a-f]{64}$/D',$cat)!==1||hash('sha256',$ej)!==$eh)return ['state'=>'canonical_anchor_evidence_invalid','catalog_sha256'=>null,'anchors'=>[]];
        $cats[$cat]=true;$out[]=hmc13_anchor_projection($a);
    }
    if(count($cats)!==1)return ['state'=>'canonical_anchor_catalog_conflict','catalog_sha256'=>null,'anchors'=>$out];
    usort($out,fn($a,$b)=>strcmp((string)$a['external_hotel_id'],(string)$b['external_hotel_id']));
    return ['state'=>'canonical_anchor_ok','catalog_sha256'=>array_key_first($cats),'anchors'=>$out];
}
function hmc13_specs(array $m):array{
    $children=$m['children']??null;
    hmc13_need(is_array($children)&&array_is_list($children)&&count($children)>=1&&count($children)<=20,'manifest_children');
    $out=[];$seen=[];
    foreach($children as $c){
        hmc13_need(is_array($c),'manifest_child');
        $op=(string)($c['operation']??'');$sha=(string)($c['result_sha256']??'');
        $ok=preg_match('/^hotel-match-live30-common4-continuation-acquire-1971-20260923-c[0-9]+-n[0-9]+-v1$/D',$op)===1
            ||preg_match('/^hotel-match-live30-common4-continuation-resume-1971-20260924-r[0-9]+-n[0-9]+-v1$/D',$op)===1;
        hmc13_need($ok,'manifest_child_op');
        hmc13_need(preg_match('/^[0-9a-f]{64}$/D',$sha)===1&&!isset($seen[$op]),'manifest_child_sha');
        $seen[$op]=true;$out[]=['operation'=>$op,'result_sha256'=>$sha];
    }
    return $out;
}
function hmc13_read_children(string $root,array $manifest):array{
    $specs=hmc13_specs($manifest);$rows=[];$children=[];$totalSearched=0;$edgeCap=0;
    $allowedStates=['completed_read_only'=>true,'terminal_day_changed_no_replay'=>true,'terminal_quota_stop_no_replay'=>true];
    foreach($specs as $spec){
        $dir=$root.'/'.$spec['operation'];hmc13_need(is_dir($dir)&&!is_link($dir),'child_missing');
        $rp=$dir.'/result.json';$qp=$dir.'/receipt.json';hmc13_need(is_file($rp)&&is_file($qp)&&!is_link($rp)&&!is_link($qp),'child_files');
        $raw=(string)file_get_contents($rp);$sha=hash('sha256',$raw);hmc13_need($sha===$spec['result_sha256'],'child_hash');
        $r=json_decode($raw,true,512,JSON_THROW_ON_ERROR);$q=hmc13_load($qp);
        $state=(string)($r['state']??'');hmc13_need(isset($allowedStates[$state])&&($q['state']??'')===$state&&($q['result_sha256']??'')===$sha,'child_terminal');
        hmc13_need(($r['operator_ids']??null)===[13,18,25,43]&&(int)($r['continue_calls']??-1)===0&&(int)($r['dates_calls']??-1)===0
            &&(int)($r['database_writes']??-1)===0&&(int)($r['mapping_writes']??-1)===0&&($r['safe_to_write_now']??null)===false,'child_authority');
        hmc13_need(($r['tourvisor_account']??null)==='TOURVISOR_ANEX_JWT','child_account');
        $scope=(int)($r['scope_count']??0);$searched=(int)($r['searched_hotels']??-1);
        hmc13_need($scope>0&&$scope<=5000&&$searched>=0&&$searched<=$scope,'child_scope');
        $totalSearched+=$searched;hmc13_need($totalSearched<=5000,'manifest_hotel_cap');
        foreach(($r['edges']??[]) as $e){
            if(!is_array($e)||($e['state']??'')!=='detail_identity_verified'||($e['link_state']??'')!=='captured_single_native')continue;
            $ids=$e['positive_native_candidates']??null;if(!is_array($ids)||count($ids)!==1)continue;
            $op=(int)($e['operator_id']??0);$ns=(string)($e['namespace']??'');$tv=(int)($e['tv_hotel_id']??0);$ext=(string)$ids[0];
            if($tv<1||preg_match('/^[1-9][0-9]{0,19}$/D',$ext)!==1)continue;
            if($op===13&&$ns==='anex')$kind='anex';
            elseif(isset(HMC13_ALLOWED_NS[$ns])&&HMC13_ALLOWED_NS[$ns]===$op)$kind='identity';
            else continue;
            foreach(['operator_link_sha256','search_id_sha256','tour_id_sha256'] as $f)hmc13_need(preg_match('/^[0-9a-f]{64}$/D',(string)($e[$f]??''))===1,'edge_hash');
            $rows[]=['kind'=>$kind,'supplier_namespace'=>$ns,'external_hotel_id'=>$ext,'tv_hotel_id'=>$tv,'operator_id'=>$op,
                'source_operation'=>$spec['operation'],'source_result_sha256'=>$sha,'batch'=>(int)($e['batch']??0),
                'search_id_sha256'=>(string)$e['search_id_sha256'],'tour_id_sha256'=>(string)$e['tour_id_sha256'],
                'operator_link_sha256'=>(string)$e['operator_link_sha256'],'operator_link_host'=>(string)($e['operator_link_host']??''),
                'query_keys'=>$e['query_keys']??[],'safe_to_write_now'=>false];
            $edgeCap++;hmc13_need($edgeCap<=5000,'manifest_edge_cap');
        }
        $children[]=['operation'=>$spec['operation'],'result_sha256'=>$sha,'state'=>$state,'scope_count'=>$scope,'searched_hotels'=>$searched,
            'provider_calls'=>(int)($r['provider_calls']??0),'operation_tariff_units'=>(int)($r['operation_tariff_units']??0)];
    }
    hmc13_need($rows!==[],'no_edges');
    return ['children'=>$children,'searched_hotels'=>$totalSearched,'edges'=>$rows];
}
function hmc13_input_collision(array $e,array $sourceTargets,array $targetSources):?string{
    $sk=$e['supplier_namespace'].'|'.$e['external_hotel_id'];$tk=$e['supplier_namespace'].'|'.$e['tv_hotel_id'];
    if(count($sourceTargets[$sk]??[])>1)return 'input_source_collision';
    if(count($targetSources[$tk]??[])>1)return 'input_target_namespace_collision';
    return null;
}
function hmc13_classify_identity(array $e,?array $hotel,array $byKey,array $byTargetNs,array $manual,array $anchor):array{
    $ns=$e['supplier_namespace'];$ext=$e['external_hotel_id'];$tv=(int)$e['tv_hotel_id'];$status='current_missing_edge';
    $src=$byKey[$ns.'|'.$ext]??[];
    if($src)$status=(count($src)===1&&($src[0]['decision_status']??'')==='accepted'&&(int)($src[0]['local_hotel_id']??0)===$tv)?'resolved_same':'source_occupied';
    elseif(!$hotel||(int)($hotel['is_active']??0)!==1)$status='target_missing_or_inactive';
    elseif(hmc13_excluded((string)($hotel['country_name']??'')))$status='excluded_country';
    elseif(isset($manual[$tv]))$status='manual_target_protected';
    elseif(array_filter($byTargetNs[$ns.'|'.$tv]??[],fn($r)=>(string)($r['external_hotel_id']??'')!==$ext))$status='target_namespace_occupied_other';
    $ready=$status==='current_missing_edge'&&$anchor['state']==='canonical_anchor_ok';
    return $e+['status'=>$status,'anchor_state'=>$anchor['state'],'unanimous_catalog_sha256'=>$anchor['catalog_sha256'],'anchors'=>$anchor['anchors'],'catalog_hotel'=>$hotel,'writer_ready'=>$ready,'safe_to_write_now'=>false];
}
function hmc13_classify_anex(array $e,?array $hotel,?array $mapping,?array $decision,bool $excluded,array $anchor,?int $resolved):array{
    $id=$e['external_hotel_id'];$tv=(int)$e['tv_hotel_id'];$status='current_missing_exact_key';
    if(preg_match('/^[1-9][0-9]{0,7}$/D',$id)!==1)$status='registry_id_invalid';
    elseif($decision!==null)$status=(($decision['decision_status']??'')==='accepted'&&(int)($decision['catalog_hotel_id']??0)===$tv&&$resolved===$tv)?'already_resolved_same_manual':'manual_source_protected';
    elseif($mapping!==null)$status=$resolved===$tv?'already_resolved_same_mapping':'source_mapping_occupied';
    elseif($excluded)$status='pair_excluded';
    elseif(!$hotel||(int)($hotel['is_active']??0)!==1)$status='target_missing_or_inactive';
    elseif(hmc13_excluded((string)($hotel['country_name']??'')))$status='excluded_country';
    $ready=$status==='current_missing_exact_key'&&$anchor['state']==='canonical_anchor_ok';
    return $e+['status'=>$status,'anchor_state'=>$anchor['state'],'unanimous_catalog_sha256'=>$anchor['catalog_sha256'],'anchors'=>$anchor['anchors'],'catalog_hotel'=>$hotel,'writer_ready'=>$ready,'safe_to_write_now'=>false];
}
function hmc13_execute(PDO $db,array $input,string $sourceSha):array{
    $edges=$input['edges'];$sourceTargets=[];$targetSources=[];
    foreach($edges as $e){$sk=$e['supplier_namespace'].'|'.$e['external_hotel_id'];$tk=$e['supplier_namespace'].'|'.$e['tv_hotel_id'];$sourceTargets[$sk][(int)$e['tv_hotel_id']]=true;$targetSources[$tk][(string)$e['external_hotel_id']]=true;}$ids=array_values(array_unique(array_map(fn($r)=>(int)$r['tv_hotel_id'],$edges)));sort($ids,SORT_NUMERIC);hmc13_need($ids!==[],'no_edges');$ph=implode(',',array_fill(0,count($ids),'?'));
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
    try{
        $catalog=[];foreach(hmc13_query($db,"SELECT id,name,country_id,country_name,region_id,region_name,subregion_id,subregion_name,category,is_active FROM catalog_hotels WHERE id IN ($ph) ORDER BY id",$ids) as $r)$catalog[(int)$r['id']]=$r;
        $all=hmc13_query($db,"SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status,catalog_sha256,evidence_sha256,evidence_json FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id");
        $byKey=[];$byTargetNs=[];$anchors=[];
        foreach($all as $r){$byKey[$r['supplier_namespace'].'|'.$r['external_hotel_id']][]=$r;if(($r['decision_status']??'')==='accepted'&&$r['local_hotel_id']!==null){$byTargetNs[$r['supplier_namespace'].'|'.$r['local_hotel_id']][]=$r;if($r['supplier_namespace']==='andromeda_catalog')$anchors[(int)$r['local_hotel_id']][]=$r;}}
        $manualTarget=[];foreach(hmc13_query($db,"SELECT anex_hotel_id,catalog_hotel_id,decision_status FROM anex_hotel_decisions ORDER BY anex_hotel_id") as $r)if($r['catalog_hotel_id']!==null)$manualTarget[(int)$r['catalog_hotel_id']][]=$r;
        $maps=[];foreach(hmc13_query($db,"SELECT anex_hotel_id,catalog_hotel_id,match_class,scope,approval_policy,enabled FROM anex_hotel_search_mappings ORDER BY anex_hotel_id") as $r)$maps[(string)$r['anex_hotel_id']]=$r;
        $decisions=[];foreach(hmc13_query($db,"SELECT anex_hotel_id,catalog_hotel_id,decision_status FROM anex_hotel_decisions ORDER BY anex_hotel_id") as $r)$decisions[(string)$r['anex_hotel_id']]=$r;
        $exclusions=[];foreach(hmc13_query($db,"SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions ORDER BY anex_hotel_id,catalog_hotel_id") as $r)$exclusions[(string)$r['anex_hotel_id']][(int)$r['catalog_hotel_id']]=true;
        $registry=AnyTourAnexSearchMappingRegistry::fromPdo($db);
        $out=[];$status=[];$ready=['identity'=>0,'anex'=>0];$anchorCounts=[];
        foreach($edges as $e){$tv=(int)$e['tv_hotel_id'];$anchor=hmc13_anchor_state($anchors[$tv]??[]);$collision=hmc13_input_collision($e,$sourceTargets,$targetSources);
            if($collision!==null)$r=$e+['status'=>$collision,'anchor_state'=>$anchor['state'],'unanimous_catalog_sha256'=>$anchor['catalog_sha256'],'anchors'=>$anchor['anchors'],'catalog_hotel'=>$catalog[$tv]??null,'writer_ready'=>false,'safe_to_write_now'=>false];
            elseif($e['kind']==='identity')$r=hmc13_classify_identity($e,$catalog[$tv]??null,$byKey,$byTargetNs,$manualTarget,$anchor);
            else{$id=$e['external_hotel_id'];$resolved=$registry->resolve('anex_online',$id,'preview');$r=hmc13_classify_anex($e,$catalog[$tv]??null,$maps[$id]??null,$decisions[$id]??null,isset($exclusions[$id][$tv]),$anchor,$resolved);}
            $out[]=$r;$key=$r['kind'].'|'.$r['status'];$status[$key]=($status[$key]??0)+1;$anchorCounts[$r['anchor_state']]=($anchorCounts[$r['anchor_state']]??0)+1;if($r['writer_ready'])$ready[$r['kind']]++;
        }
        $db->rollBack();ksort($status);ksort($anchorCounts);
        return ['operation'=>'hotel-match-common4-mass-current-1971-20260924-v13','state'=>'completed_read_only_mass_current',
            'source_sha'=>$sourceSha,'children'=>$input['children'],'searched_hotels'=>$input['searched_hotels'],
            'input_single_native_edges'=>count($edges),'status_counts'=>$status,'anchor_state_counts'=>$anchorCounts,'writer_ready_counts'=>$ready,'rows'=>$out,
            'supplier_calls'=>0,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'safe_to_write_now'=>false];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}

if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    if(in_array('--self-test',$argv??[],true)){
        $raw='{"x":1}';$a=['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>'7','local_hotel_id'=>100,'decision_status'=>'accepted','catalog_sha256'=>str_repeat('a',64),'evidence_sha256'=>hash('sha256',$raw),'evidence_json'=>$raw];$anchor=hmc13_anchor_state([$a]);
        hmc13_need($anchor['state']==='canonical_anchor_ok','anchor');
        $e=['kind'=>'identity','supplier_namespace'=>'operator_315','external_hotel_id'=>'77','tv_hotel_id'=>100,'safe_to_write_now'=>false];
        $h=['id'=>100,'is_active'=>1,'country_name'=>'Турция'];$r=hmc13_classify_identity($e,$h,[],[],[],$anchor);hmc13_need($r['writer_ready']===true,'identity_ready');
        $e=['kind'=>'anex','supplier_namespace'=>'anex','external_hotel_id'=>'5844','tv_hotel_id'=>100,'safe_to_write_now'=>false];$r=hmc13_classify_anex($e,$h,null,null,false,$anchor,null);hmc13_need($r['writer_ready']===true,'anex_ready');
        echo "MATCH_COMMON4_MASS_CURRENT_V13_SELFTEST_OK\n";exit;
    }
    hmc13_need(($argv[1]??'')==='--execute','disabled');$root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$ops=(string)getenv('MATCH_OPERATIONS_ROOT');$manifestPath=(string)getenv('MATCH_CHILD_MANIFEST');$sha=(string)getenv('MATCH_SOURCE_SHA');
    hmc13_need(is_dir($root)&&basename($root)==='anytoour.ru'&&is_dir($dir)&&is_dir($ops)&&is_file($manifestPath)&&preg_match('/^[0-9a-f]{40}$/D',$sha)===1,'runtime_scope');
    $res=hmc13_load($dir.'/reservation.json');hmc13_need(($res['state']??'')==='reserved_before_db_read','reservation');
    try{require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');$input=hmc13_read_children($ops,hmc13_load($manifestPath));$result=hmc13_execute(v2_data_db(),$input,$sha);$h=hmc13_save($dir.'/result.json',$result);hmc13_save($dir.'/receipt.json',['operation'=>$result['operation'],'state'=>$result['state'],'result_sha256'=>$h,'readback_verified'=>hash_file('sha256',$dir.'/result.json')===$h,'provider_accessed'=>false,'supplier_calls'=>0,'database_writes'=>0,'mapping_writes'=>0]);echo hmc13_json(['state'=>$result['state'],'searched_hotels'=>$result['searched_hotels'],'input_single_native_edges'=>$result['input_single_native_edges'],'status_counts'=>$result['status_counts'],'writer_ready_counts'=>$result['writer_ready_counts']])."\n";}
    catch(Throwable $e){$f=['operation'=>'hotel-match-common4-mass-current-1971-20260924-v13','state'=>'failed_read_only_mass_current','reason'=>preg_replace('/[^A-Za-z0-9_.:-]+/','_',mb_substr($e->getMessage(),0,140,'UTF-8')),'supplier_calls'=>0,'database_writes'=>0,'mapping_writes'=>0];$h=hmc13_save($dir.'/result.json',$f);hmc13_save($dir.'/receipt.json',['operation'=>$f['operation'],'state'=>$f['state'],'result_sha256'=>$h,'readback_verified'=>true,'provider_accessed'=>false,'supplier_calls'=>0,'database_writes'=>0,'mapping_writes'=>0]);fwrite(STDERR,$f['reason']."\n");exit(2);}
}
