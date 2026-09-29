<?php
declare(strict_types=1);

const MRC25_OP='hotel-match-common4-retained-context-census-1971-20260925-v25';
const MRC25_POST_OP='hotel-match-samo-business-live30-common4-postwrite-1971-20260925-v24';
const MRC25_OPS=[5=>'operator_5',315=>'operator_315'];
const MRC25_MAX_FILES=100000;
const MRC25_MAX_FILE_BYTES=16777216;

function mrc25_need(bool $v,string $why):void{if(!$v)throw new RuntimeException($why);}
function mrc25_json(mixed $v):string{return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function mrc25_load(string $p):array{$v=json_decode((string)file_get_contents($p),true,128,JSON_THROW_ON_ERROR);mrc25_need(is_array($v),'json_shape');return$v;}
function mrc25_save(string $p,array $v):string{$raw=mrc25_json($v)."\n";$f=@fopen($p,'x+b');mrc25_need($f!==false,'exclusive_create');try{mrc25_need(fwrite($f,$raw)===strlen($raw)&&fflush($f),'durable_write');if(function_exists('fsync'))mrc25_need(fsync($f),'durable_sync');}finally{fclose($f);}return hash('sha256',$raw);}
function mrc25_pos(mixed $v):?int{$s=trim((string)$v);return preg_match('/^[1-9][0-9]{0,12}$/D',$s)===1?(int)$s:null;}
function mrc25_operator(mixed $v):?int{
    if(is_int($v)&&isset(MRC25_OPS[$v]))return$v;
    $s=trim((string)$v);if(preg_match('/^(?:operator_)?([0-9]+)$/D',$s,$m)!==1)return null;$n=(int)$m[1];return isset(MRC25_OPS[$n])?$n:null;
}
function mrc25_private_config(string $root):array{
    foreach([$root.'/_preview/search3-anex-candidate/.andromeda-private.php',$root.'/v2/.andromeda-private.php'] as $p){
        if(!is_file($p)||is_link($p))continue;$v=require$p;
        if(is_array($v)&&is_string($v['catalog_path']??null)&&$v['catalog_path']!=='')return$v;
    }
    throw new RuntimeException('andromeda_private_config_missing');
}
function mrc25_missing(array $post):array{
    mrc25_need(($post['operation']??'')===MRC25_POST_OP&&($post['state']??'')==='samo_business_live30_common4_plan_ready','post_state');
    mrc25_need((int)($post['deduped_unique_local_count']??0)===1711&&(int)($post['deduped_lane_state_conflicts']??-1)===0,'post_dedup');
    mrc25_need(($post['deduped_missing_lane_counts']??null)===['operator_5'=>1516,'operator_115'=>1218,'operator_315'=>1304,'operator_342'=>667],'post_missing_counts');
    mrc25_need(is_array($post['rows']??null)&&count($post['rows'])===1887,'post_rows');
    $sets=[5=>[],315=>[]];
    foreach($post['rows'] as $r){
        if(!is_array($r)||($r['mapping_state']??'')!=='mapped_unique'||($r['saved_catalog_state']??'')!=='saved_catalog_ready')continue;
        $local=(int)($r['local_hotel_id']??0);if($local<1)continue;$lanes=$r['operator_lanes']??[];
        foreach(MRC25_OPS as $op=>$ns)if(($lanes[$ns]['status']??'missing')==='missing')$sets[$op][$local]=true;
    }
    mrc25_need(count($sets[5])===1516&&count($sets[315])===1304,'post_missing_set_counts');
    return$sets;
}
function mrc25_criteria(array $c):array{
    $keys=['TOWNFROMINC','STATEINC','CHECKIN_BEG','CHECKIN_END','NIGHTS_FROM','NIGHTS_TILL','ADULT','CHILD','AGES','TOWNTOINC','OPERATORS','MEAL','STARS','GROUP_BY'];
    $out=[];foreach($keys as $k)if(array_key_exists($k,$c)){
        $v=$c[$k];if(is_int($v)||is_string($v))$out[$k]=$v;
    }
    ksort($out,SORT_STRING);return$out;
}
function mrc25_context_key(array $criteria):string{return hash('sha256',mrc25_json($criteria));}
function mrc25_scan(string $dir,array $missing,int $cut):array{
    mrc25_need(is_dir($dir)&&!is_link($dir),'search_dir');
    $ctx=[];$files=0;$parsed=0;$pageFiles=0;$offers=0;$operatorOffers=[5=>0,315=>0];
    foreach(new DirectoryIterator($dir) as $e){
        if($e->isDot()||$e->isLink()||!$e->isFile()||!str_ends_with($e->getFilename(),'.json'))continue;
        if(++$files>MRC25_MAX_FILES)throw new RuntimeException('file_cap');
        $n=$e->getSize();if($n<2||$n>MRC25_MAX_FILE_BYTES)continue;
        try{$x=json_decode((string)file_get_contents($e->getPathname()),true,64,JSON_THROW_ON_ERROR);}catch(Throwable){continue;}
        if(!is_array($x))continue;$store=$x['store']??null;$snap=is_array($store)?($store['snapshot']??null):null;
        $at=is_array($store)?($store['created_at']??null):null;$criteria=is_array($store)?($store['criteria']??null):null;
        if(!is_array($snap)||($snap['provider']??null)!=='andromeda'||!is_int($at)||$at<$cut||!is_array($criteria)||!is_array($snap['offers']??null))continue;
        $safe=mrc25_criteria($criteria);if($safe===[])continue;$parsed++;$pageFiles++;
        $ck=mrc25_context_key($safe);
        if(!isset($ctx[$ck]))$ctx[$ck]=['criteria_sha256'=>$ck,'criteria'=>$safe,'search_refs'=>[],'page_files'=>0,'max_created_at'=>$at,'operators'=>[]];
        $ctx[$ck]['page_files']++;$ctx[$ck]['max_created_at']=max($ctx[$ck]['max_created_at'],$at);
        $ref=(string)($store['search_ref']??'');if($ref!=='')$ctx[$ck]['search_refs'][$ref]=true;
        foreach($snap['offers'] as $o){
            if(!is_array($o))continue;$op=mrc25_operator($o['operator_ref']??($o['operatorKey']??null));if($op===null)continue;
            $local=mrc25_pos($o['local_hotel_id']??null);$offers++;$operatorOffers[$op]++;
            if(!isset($ctx[$ck]['operators'][$op]))$ctx[$ck]['operators'][$op]=['offer_rows'=>0,'locals'=>[],'missing_overlap'=>[]];
            $ctx[$ck]['operators'][$op]['offer_rows']++;
            if($local!==null){$ctx[$ck]['operators'][$op]['locals'][$local]=true;if(isset($missing[$op][$local]))$ctx[$ck]['operators'][$op]['missing_overlap'][$local]=true;}
        }
    }
    $rank=[5=>[],315=>[]];$zero=[5=>0,315=>0];
    foreach($ctx as $c){
        foreach(MRC25_OPS as $op=>$ns){
            $o=$c['operators'][$op]??['offer_rows'=>0,'locals'=>[],'missing_overlap'=>[]];
            $row=['criteria_sha256'=>$c['criteria_sha256'],'criteria'=>$c['criteria'],'search_ref_count'=>count($c['search_refs']),'page_files'=>$c['page_files'],'max_created_at'=>$c['max_created_at'],
                'offer_rows'=>(int)$o['offer_rows'],'unique_local_hotels'=>count($o['locals']),'missing_overlap_count'=>count($o['missing_overlap']),'current_missing_target_count'=>count($missing[$op])];
            if($row['missing_overlap_count']===0)$zero[$op]++;
            if($row['offer_rows']>0)$rank[$op][]=$row;
        }
    }
    foreach($rank as $op=>&$rows)usort($rows,fn($a,$b)=>[-$a['missing_overlap_count'],-$a['unique_local_hotels'],-$a['offer_rows'],-$a['max_created_at'],$a['criteria_sha256']]<=>[-$b['missing_overlap_count'],-$b['unique_local_hotels'],-$b['offer_rows'],-$b['max_created_at'],$b['criteria_sha256']]);unset($rows);
    $top=[];foreach($rank as $op=>$rows)$top[(string)$op]=array_slice($rows,0,30);
    return['files_examined'=>$files,'parseable_retained_page_files'=>$parsed,'retained_operator_offer_rows'=>['5'=>$operatorOffers[5],'315'=>$operatorOffers[315]],'contexts_with_operator_offers'=>['5'=>count($rank[5]),'315'=>count($rank[315])],
        'zero_missing_overlap_contexts'=>['5'=>$zero[5],'315'=>$zero[315]],'top_contexts_by_operator'=>$top];
}
function mrc25_self_test():void{
    $c=mrc25_criteria(['PAGE'=>2,'STATEINC'=>5,'CHECKIN_BEG'=>'20261001','NIGHTS_FROM'=>7,'ADULT'=>2,'junk'=>'x']);
    mrc25_need($c===['ADULT'=>2,'CHECKIN_BEG'=>'20261001','NIGHTS_FROM'=>7,'STATEINC'=>5],'criteria');
    mrc25_need(mrc25_operator('operator_315')===315&&mrc25_operator('5')===5&&mrc25_operator('342')===null,'operator');
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    if(in_array('--self-test',$argv??[],true)){mrc25_self_test();echo"MATCH_COMMON4_RETAINED_CONTEXT_CENSUS_V25_SELFTEST_OK\n";exit;}
    mrc25_need(($argv[1]??'')==='--execute','disabled');$root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$postPath=(string)getenv('MATCH_POST_RESULT');$postSha=(string)getenv('MATCH_POST_RESULT_SHA');$sourceSha=(string)getenv('MATCH_SOURCE_SHA');
    mrc25_need(is_dir($root)&&basename($root)==='anytoour.ru'&&is_dir($dir)&&basename($dir)===MRC25_OP&&is_file($postPath)&&preg_match('/^[a-f0-9]{64}$/D',$postSha)===1&&hash_file('sha256',$postPath)===$postSha&&preg_match('/^[a-f0-9]{40}$/D',$sourceSha)===1,'runtime_scope');
    $reservation=mrc25_load($dir.'/reservation.json');mrc25_need(($reservation['operation']??'')===MRC25_OP,'reservation');
    try{$cfg=mrc25_private_config($root);$missing=mrc25_missing(mrc25_load($postPath));$scan=mrc25_scan(dirname((string)$cfg['catalog_path']).'/searches',$missing,time()-30*86400);
        $out=['operation'=>MRC25_OP,'state'=>'completed_supplier_free_retained_context_census','source_sha'=>$sourceSha,'postwrite_operation'=>MRC25_POST_OP,'postwrite_result_sha256'=>$postSha,
            'current_missing_targets'=>['5'=>count($missing[5]),'315'=>count($missing[315])]]+$scan+['provider_http_calls'=>0,'tourvisor_calls'=>0,'samo_calls'=>0,'anex_calls'=>0,'andromeda_calls'=>0,'ssh_reads_retained_searches'=>1,'database_writes'=>0,'mapping_writes'=>0,'retained_search_mutations'=>0,'safe_to_write_now'=>false];
        $h=mrc25_save($dir.'/result.json',$out);mrc25_save($dir.'/receipt.json',['operation'=>MRC25_OP,'state'=>$out['state'],'result_sha256'=>$h,'readback_verified'=>hash_file('sha256',$dir.'/result.json')===$h,'provider_accessed'=>false,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'no_replay'=>true]);
        echo mrc25_json(['state'=>$out['state'],'current_missing_targets'=>$out['current_missing_targets'],'files_examined'=>$out['files_examined'],'parseable_retained_page_files'=>$out['parseable_retained_page_files'],'retained_operator_offer_rows'=>$out['retained_operator_offer_rows'],'contexts_with_operator_offers'=>$out['contexts_with_operator_offers'],'zero_missing_overlap_contexts'=>$out['zero_missing_overlap_contexts'],'top_contexts_by_operator'=>$out['top_contexts_by_operator']])."\n";
    }catch(Throwable$e){$f=['operation'=>MRC25_OP,'state'=>'failed_supplier_free_retained_context_census','reason'=>preg_replace('/[^A-Za-z0-9_.:-]+/','_',mb_substr($e->getMessage(),0,160)),'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0];$h=mrc25_save($dir.'/result.json',$f);mrc25_save($dir.'/receipt.json',['operation'=>MRC25_OP,'state'=>$f['state'],'result_sha256'=>$h,'readback_verified'=>true,'provider_accessed'=>false,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'no_replay'=>true]);fwrite(STDERR,$f['reason']."\n");exit(2);}
}
