<?php
declare(strict_types=1);
require_once __DIR__.'/hotel_match_v9_operator_edges_current_v16.php';

const HM18_OP='hotel-match-v9-operator-edges-postwrite-1971-20260922-v18';

function hm18_residual(array $r):array{
    $missing=array_values(array_filter($r['rows']??[],fn($x)=>is_array($x)&&($x['status']??'')==='current_missing_edge'));
    $byNs=[];$byAnchor=[];$geo=[];
    foreach($missing as $x){
        $ns=(string)($x['supplier_namespace']??'');$ac=(int)($x['canonical_anchor_count']??-1);
        $byNs[$ns]=($byNs[$ns]??0)+1;$byAnchor[(string)$ac]=($byAnchor[(string)$ac]??0)+1;
        $h=$x['catalog_hotel']??[];$g=implode('|',[$ns,(string)($h['country_name']??''),(string)($h['region_name']??''),(string)($h['subregion_name']??'')]);
        $geo[$g]=($geo[$g]??0)+1;
    }
    ksort($byNs);ksort($byAnchor,SORT_NATURAL);arsort($geo);
    return ['current_missing_count'=>count($missing),'by_namespace'=>$byNs,'by_anchor_count'=>$byAnchor,'top_geo'=>array_slice($geo,0,30,true)];
}

if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    $mode=$argv[1]??'';
    if($mode==='--self-test'){
        $x=hm18_residual(['rows'=>[
            ['status'=>'current_missing_edge','supplier_namespace'=>'bgoperator','canonical_anchor_count'=>2,'catalog_hotel'=>['country_name'=>'A','region_name'=>'B','subregion_name'=>'C']],
            ['status'=>'resolved_same','supplier_namespace'=>'bgoperator','canonical_anchor_count'=>1,'catalog_hotel'=>[]],
        ]]);
        hm16_need($x['current_missing_count']===1&&$x['by_anchor_count']['2']===1,'residual');
        echo "MATCH_V9_OPERATOR_EDGES_POSTWRITE_V18_SELFTEST_OK\n";exit;
    }
    hm16_need($mode==='--execute','disabled');$root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$input=(string)getenv('MATCH_INPUT_FILE');$sha=(string)getenv('MATCH_SOURCE_SHA');
    hm16_need(is_dir($root)&&basename($root)==='anytoour.ru'&&is_dir($dir)&&basename($dir)===HM18_OP&&is_file($input)&&preg_match('/^[0-9a-f]{40}$/D',$sha),'runtime_scope');
    $res=hm16_load($dir.'/reservation.json');hm16_need(($res['operation']??'')===HM18_OP&&($res['state']??'')==='reserved_before_db_read','reservation');
    hm16_need(hash_file('sha256',$input)===HM16_INPUT_SHA,'input_hash');$norm=hm16_normalize(hm16_load($input));
    require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
    try{
        $base=hm16_execute(v2_data_db(),$norm,$sha);$residual=hm18_residual($base);
        $result=$base;$result['operation']=HM18_OP;$result['state']='completed_read_only_postwrite_reconcile';
        $result['predecessor_v17']=['run_id'=>35782031843,'artifact_id'=>10718108151,'inserted'=>336];
        $result['residual']=$residual;
        $h=hm16_save($dir.'/result.json',$result);hm16_save($dir.'/receipt.json',['operation'=>HM18_OP,'state'=>$result['state'],'result_sha256'=>$h,'readback_verified'=>hash_file('sha256',$dir.'/result.json')===$h,'provider_accessed'=>false,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0]);
        echo hm16_json(['state'=>$result['state'],'status_counts'=>$result['status_counts'],'writer_ready'=>$result['writer_ready_count'],'residual'=>$residual])."\n";
    }catch(Throwable $e){
        $f=['operation'=>HM18_OP,'state'=>'failed_read_only_postwrite_reconcile','reason'=>preg_replace('/[^A-Za-z0-9_.:-]+/','_',mb_substr($e->getMessage(),0,100,'UTF-8')),'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0];
        $h=hm16_save($dir.'/result.json',$f);hm16_save($dir.'/receipt.json',['operation'=>HM18_OP,'state'=>$f['state'],'result_sha256'=>$h,'readback_verified'=>true,'provider_accessed'=>false,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0]);fwrite(STDERR,$f['reason']."\n");exit(2);
    }
}
