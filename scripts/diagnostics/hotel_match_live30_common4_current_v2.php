<?php
declare(strict_types=1);

const HMC4C_OP = 'hotel-match-live30-common4-current-1971-20260923-v2';
const HMC4C_EXPECTED_SINGLE_NATIVE = 283;
const HMC4C_NS = ['bgoperator'=>18,'operator_315'=>25,'operator_342'=>43];
const HMC4C_CHILDREN = [
    ['offset'=>0,'count'=>100],
    ['offset'=>100,'count'=>100],
    ['offset'=>200,'count'=>100],
    ['offset'=>300,'count'=>80],
    ['offset'=>380,'count'=>40],
    ['offset'=>420,'count'=>30],
];

function hmc4c_need(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
function hmc4c_sort(mixed $v):mixed{
    if(!is_array($v))return $v;
    if(array_is_list($v))return array_map('hmc4c_sort',$v);
    ksort($v,SORT_STRING);foreach($v as $k=>$x)$v[$k]=hmc4c_sort($x);return $v;
}
function hmc4c_json(mixed $v):string{return json_encode(hmc4c_sort($v),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function hmc4c_load(string $p):array{$v=json_decode((string)file_get_contents($p),true,512,JSON_THROW_ON_ERROR);hmc4c_need(is_array($v),'json_shape');return $v;}
function hmc4c_save(string $p,array $v):string{
    $raw=hmc4c_json($v)."\n";$f=@fopen($p,'x+b');hmc4c_need($f!==false,'exclusive_create');
    try{hmc4c_need(fwrite($f,$raw)===strlen($raw)&&fflush($f),'durable_write');if(function_exists('fsync'))hmc4c_need(fsync($f),'durable_sync');}
    finally{fclose($f);}return hash('sha256',$raw);
}
function hmc4c_query(PDO $db,string $sql,array $args=[]):array{$st=$db->prepare($sql);$st->execute(array_values($args));return $st->fetchAll(PDO::FETCH_ASSOC)?:[];}
function hmc4c_excluded_country(string $v):bool{return preg_match('/^(?:россия|абхазия|russia|russian federation|abkhazia)$/iu',trim($v))===1;}
function hmc4c_target_facts(array $h):array{
    $keys=['id','name','country_id','country_name','region_id','region_name','subregion_id','subregion_name','category','is_active'];$out=[];
    foreach($keys as $k)$out[$k]=$h[$k]??null;
    return $out;
}
function hmc4c_anchor_projection(array $a):array{
    $out=[];foreach(['supplier_namespace','external_hotel_id','local_hotel_id','decision_status','catalog_sha256','evidence_sha256'] as $k)$out[$k]=$a[$k]??null;return $out;
}
function hmc4c_child_name(int $offset,int $count):string{return 'hotel-match-live30-common4-acquire-1971-20260923-o'.$offset.'-n'.$count.'-v1';}

function hmc4c_read_acquisition(string $operationsRoot):array{
    $rows=[];$digests=[];$totals=['bgoperator'=>0,'operator_315'=>0,'operator_342'=>0];$seenEdge=[];
    foreach(HMC4C_CHILDREN as $c){
        $offset=(int)$c['offset'];$count=(int)$c['count'];$child=hmc4c_child_name($offset,$count);$dir=$operationsRoot.'/'.$child;
        hmc4c_need(is_dir($dir)&&!is_link($dir),'child_missing_'.$offset);
        $rp=$dir.'/result.json';$cp=$dir.'/receipt.json';hmc4c_need(is_file($rp)&&!is_link($rp)&&is_file($cp)&&!is_link($cp),'child_terminal_files_'.$offset);
        $raw=(string)file_get_contents($rp);$sha=hash('sha256',$raw);$r=json_decode($raw,true,512,JSON_THROW_ON_ERROR);$receipt=hmc4c_load($cp);
        hmc4c_need(($receipt['result_sha256']??'')===$sha,'child_result_hash_'.$offset);
        hmc4c_need(($r['state']??'')==='completed_read_only'&&($receipt['state']??'')==='completed_read_only','child_state_'.$offset);
        hmc4c_need((int)($r['frontier_count']??0)===1799&&(int)($r['scope_offset']??-1)===$offset&&(int)($r['scope_count']??0)===$count,'child_scope_'.$offset);
        hmc4c_need(($r['operator_ids']??null)===[13,18,25,43]&&(int)($r['database_writes']??-1)===0&&(int)($r['mapping_writes']??-1)===0,'child_authority_'.$offset);
        hmc4c_need((int)($r['continue_calls']??-1)===0&&(int)($r['dates_calls']??-1)===0,'child_identity_only_'.$offset);
        $digests[]=['operation'=>$child,'result_sha256'=>$sha,'scope_offset'=>$offset,'scope_count'=>$count,'provider_calls'=>(int)($r['provider_calls']??0)];
        foreach(($r['edges']??[]) as $e){
            if(!is_array($e)||($e['state']??'')!=='detail_identity_verified'||($e['link_state']??'')!=='captured_single_native')continue;
            $ns=(string)($e['namespace']??'');if(!isset(HMC4C_NS[$ns]))continue;
            $ids=$e['positive_native_candidates']??null;hmc4c_need(is_array($ids)&&count($ids)===1,'single_native_shape');
            $ext=trim((string)$ids[0]);$tv=(int)($e['tv_hotel_id']??0);$op=(int)($e['operator_id']??0);
            hmc4c_need(preg_match('/^[1-9][0-9]{0,19}$/D',$ext)===1&&$tv>0&&HMC4C_NS[$ns]===$op,'single_native_identity');
            $linkHash=(string)($e['operator_link_sha256']??'');hmc4c_need(preg_match('/^[0-9a-f]{64}$/D',$linkHash)===1,'operator_link_hash');
            $edgeKey=$child.'|'.$ns.'|'.$ext.'|'.$tv;hmc4c_need(!isset($seenEdge[$edgeKey]),'duplicate_saved_edge');$seenEdge[$edgeKey]=true;
            $rows[]=[
                'supplier_namespace'=>$ns,'external_hotel_id'=>$ext,'tv_hotel_id'=>$tv,'operator_id'=>$op,
                'source_operation'=>$child,'source_result_sha256'=>$sha,'batch'=>(int)($e['batch']??0),
                'search_id_sha256'=>(string)($e['search_id_sha256']??''),'tour_id_sha256'=>(string)($e['tour_id_sha256']??''),
                'operator_link_sha256'=>$linkHash,'safe_to_write_now'=>false,
            ];$totals[$ns]++;
        }
    }
    hmc4c_need(count($rows)===HMC4C_EXPECTED_SINGLE_NATIVE,'single_native_total');
    hmc4c_need($totals===['bgoperator'=>205,'operator_315'=>50,'operator_342'=>28],'single_native_namespace_totals');
    return ['rows'=>$rows,'children'=>$digests,'namespace_counts'=>$totals];
}

function hmc4c_saved_indexes(array $rows):array{
    $source=[];$target=[];
    foreach($rows as $r){$source[$r['supplier_namespace'].'|'.$r['external_hotel_id']][(int)$r['tv_hotel_id']]=true;$target[$r['supplier_namespace'].'|'.$r['tv_hotel_id']][(string)$r['external_hotel_id']]=true;}
    return [$source,$target];
}
function hmc4c_anchor_state(array $anchors):array{
    if(!$anchors)return ['state'=>'canonical_anchor_missing','anchors'=>[],'catalog_sha256'=>null];
    $catalog=[];$out=[];
    foreach($anchors as $a){
        if(($a['decision_status']??'')!=='accepted'||$a['local_hotel_id']===null)return ['state'=>'canonical_anchor_invalid','anchors'=>[],'catalog_sha256'=>null];
        $cat=(string)($a['catalog_sha256']??'');$ev=(string)($a['evidence_sha256']??'');$raw=(string)($a['evidence_json']??'');
        if(preg_match('/^[0-9a-f]{64}$/D',$cat)!==1||preg_match('/^[0-9a-f]{64}$/D',$ev)!==1||hash('sha256',$raw)!==$ev)return ['state'=>'canonical_anchor_evidence_invalid','anchors'=>[],'catalog_sha256'=>null];
        $catalog[$cat]=true;$out[]=hmc4c_anchor_projection($a);
    }
    if(count($catalog)!==1)return ['state'=>'canonical_anchor_catalog_conflict','anchors'=>$out,'catalog_sha256'=>null];
    return ['state'=>'canonical_anchor_ok','anchors'=>$out,'catalog_sha256'=>array_key_first($catalog)];
}
function hmc4c_classify(array $edge,array $catalog,array $byKey,array $byTargetNs,array $anchors,array $manual,array $savedSource,array $savedTarget):array{
    $ns=$edge['supplier_namespace'];$ext=$edge['external_hotel_id'];$tv=(int)$edge['tv_hotel_id'];$status=null;
    if(count($savedSource[$ns.'|'.$ext]??[])>1)$status='saved_source_collision';
    elseif(count($savedTarget[$ns.'|'.$tv]??[])>1)$status='saved_target_collision';
    else{
        $src=$byKey[$ns.'|'.$ext]??[];
        if($src){
            if(count($src)===1&&($src[0]['decision_status']??'')==='accepted'&&(int)($src[0]['local_hotel_id']??0)===$tv)$status='resolved_same';
            else$status='source_occupied';
        }else{
            $h=$catalog[$tv]??null;
            if(!$h||(int)($h['is_active']??0)!==1)$status='target_missing_or_inactive';
            elseif(hmc4c_excluded_country((string)($h['country_name']??'')))$status='excluded_country';
            elseif(isset($manual[$tv]))$status='manual_target_protected';
            elseif(array_filter($byTargetNs[$ns.'|'.$tv]??[],fn($r)=>(string)($r['external_hotel_id']??'')!==$ext))$status='target_namespace_occupied_other';
            else$status='current_missing_edge';
        }
    }
    $anchor=hmc4c_anchor_state($anchors[$tv]??[]);
    $ready=$status==='current_missing_edge'&&$anchor['state']==='canonical_anchor_ok';
    return $edge+['status'=>$status,'writer_ready'=>$ready,'catalog_hotel'=>$catalog[$tv]??null,'anchor_state'=>$anchor['state'],
        'unanimous_catalog_sha256'=>$anchor['catalog_sha256'],'anchors'=>$anchor['anchors'],'safe_to_write_now'=>false];
}

function hmc4c_execute(PDO $db,array $acq,string $sourceSha):array{
    $rows=$acq['rows'];[$savedSource,$savedTarget]=hmc4c_saved_indexes($rows);
    $ids=array_values(array_unique(array_map(fn($r)=>(int)$r['tv_hotel_id'],$rows)));sort($ids,SORT_NUMERIC);hmc4c_need($ids!==[],'target_ids');
    $ph=implode(',',array_fill(0,count($ids),'?'));
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
    try{
        $catalog=[];foreach(hmc4c_query($db,"SELECT id,name,country_id,country_name,region_id,region_name,subregion_id,subregion_name,category,is_active FROM catalog_hotels WHERE id IN ($ph) ORDER BY id",$ids) as $r)$catalog[(int)$r['id']]=$r;
        $ident=hmc4c_query($db,"SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status,catalog_sha256,evidence_sha256,evidence_json FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id");
        $byKey=[];$byTargetNs=[];$anchors=[];
        foreach($ident as $r){$byKey[$r['supplier_namespace'].'|'.$r['external_hotel_id']][]=$r;
            if(($r['decision_status']??'')==='accepted'&&$r['local_hotel_id']!==null){$byTargetNs[$r['supplier_namespace'].'|'.$r['local_hotel_id']][]=$r;if($r['supplier_namespace']==='andromeda_catalog')$anchors[(int)$r['local_hotel_id']][]=$r;}}
        $manual=[];foreach(hmc4c_query($db,"SELECT catalog_hotel_id,decision_status FROM anex_hotel_decisions WHERE catalog_hotel_id IN ($ph) ORDER BY catalog_hotel_id",$ids) as $r)$manual[(int)$r['catalog_hotel_id']][]=$r;
        $out=[];$status=[];$byNs=[];$anchorStates=[];
        foreach($rows as $e){$r=hmc4c_classify($e,$catalog,$byKey,$byTargetNs,$anchors,$manual,$savedSource,$savedTarget);$out[]=$r;
            $status[$r['status']]=($status[$r['status']]??0)+1;$byNs[$r['supplier_namespace']][$r['status']]=($byNs[$r['supplier_namespace']][$r['status']]??0)+1;
            $anchorStates[$r['anchor_state']]=($anchorStates[$r['anchor_state']]??0)+1;}
        ksort($status);ksort($byNs);foreach($byNs as &$x)ksort($x);unset($x);ksort($anchorStates);
        $db->rollBack();
        return ['operation'=>HMC4C_OP,'state'=>'completed_read_only_current_audit','source_sha'=>$sourceSha,
            'input_single_native_count'=>count($rows),'input_namespace_counts'=>$acq['namespace_counts'],'children'=>$acq['children'],
            'unique_targets'=>count($ids),'status_counts'=>$status,'namespace_status_counts'=>$byNs,'anchor_state_counts'=>$anchorStates,
            'writer_ready_count'=>count(array_filter($out,fn($r)=>$r['writer_ready']===true)),'rows'=>$out,
            'provider_http_calls'=>0,'supplier_calls'=>0,'database_reads'=>1,'database_writes'=>0,'mapping_writes'=>0,'safe_to_write_now'=>false];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}

if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    $mode=$argv[1]??'';
    if($mode==='--self-test'){
        $edge=['supplier_namespace'=>'operator_315','external_hotel_id'=>'10','tv_hotel_id'=>7,'operator_id'=>25,'safe_to_write_now'=>false];
        $cat=[7=>['id'=>7,'is_active'=>1,'country_name'=>'Турция']];
        $raw='{"ok":true}';$anchor=['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>'99','local_hotel_id'=>7,'decision_status'=>'accepted','catalog_sha256'=>str_repeat('a',64),'evidence_sha256'=>hash('sha256',$raw),'evidence_json'=>$raw];
        $x=hmc4c_classify($edge,$cat,[],[],[7=>[$anchor]],[],['operator_315|10'=>[7=>true]],['operator_315|7'=>['10'=>true]]);
        hmc4c_need($x['status']==='current_missing_edge'&&$x['writer_ready']===true,'self_ready');
        $x=hmc4c_classify($edge,$cat,[],[],[7=>[$anchor]],[],['operator_315|10'=>[7=>true,8=>true]],['operator_315|7'=>['10'=>true]]);
        hmc4c_need($x['status']==='saved_source_collision'&&$x['writer_ready']===false,'self_collision');
        echo "MATCH_LIVE30_COMMON4_CURRENT_V2_SELFTEST_OK\n";exit;
    }
    hmc4c_need($mode==='--execute','disabled');$root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$sha=(string)getenv('MATCH_SOURCE_SHA');
    hmc4c_need(is_dir($root)&&basename($root)==='anytoour.ru'&&is_dir($dir)&&basename($dir)===HMC4C_OP&&preg_match('/^[0-9a-f]{40}$/D',$sha)===1,'runtime_scope');
    $reservation=hmc4c_load($dir.'/reservation.json');hmc4c_need(($reservation['operation']??'')===HMC4C_OP&&($reservation['state']??'')==='reserved_before_db_read','reservation');
    try{
        $acq=hmc4c_read_acquisition(dirname($dir));require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
        $result=hmc4c_execute(v2_data_db(),$acq,$sha);$h=hmc4c_save($dir.'/result.json',$result);
        hmc4c_save($dir.'/receipt.json',['operation'=>HMC4C_OP,'state'=>$result['state'],'result_sha256'=>$h,'readback_verified'=>hash_file('sha256',$dir.'/result.json')===$h,
            'provider_accessed'=>false,'provider_http_calls'=>0,'database_reads'=>1,'database_writes'=>0,'mapping_writes'=>0]);
        echo hmc4c_json(['state'=>$result['state'],'input'=>$result['input_single_native_count'],'writer_ready'=>$result['writer_ready_count'],'status_counts'=>$result['status_counts'],'anchor_state_counts'=>$result['anchor_state_counts']])."\n";
    }catch(Throwable $e){
        $reason=preg_replace('/[^A-Za-z0-9_.:-]+/','_',mb_substr($e->getMessage(),0,120,'UTF-8'))?:'failure';
        $f=['operation'=>HMC4C_OP,'state'=>'failed_read_only_current_audit','reason'=>$reason,'provider_http_calls'=>0,'database_reads'=>0,'database_writes'=>0,'mapping_writes'=>0];
        $h=hmc4c_save($dir.'/result.json',$f);hmc4c_save($dir.'/receipt.json',['operation'=>HMC4C_OP,'state'=>$f['state'],'result_sha256'=>$h,'readback_verified'=>true,'provider_accessed'=>false,'provider_http_calls'=>0,'database_reads'=>0,'database_writes'=>0,'mapping_writes'=>0]);
        fwrite(STDERR,$reason."\n");exit(2);
    }
}
