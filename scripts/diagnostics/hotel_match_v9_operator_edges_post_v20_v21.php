<?php
declare(strict_types=1);
require_once __DIR__.'/hotel_match_v9_operator_edges_current_v16.php';

const HM21_OP='hotel-match-v9-operator-edges-post-v20-1971-20260922-v21';

function hm21_remaining(array $r):array{
    $out=[];
    foreach(($r['rows']??[]) as $x){
        if(!is_array($x)||($x['status']??'')!=='current_missing_edge')continue;
        $out[]=[
            'supplier_namespace'=>(string)($x['supplier_namespace']??''),
            'external_hotel_id'=>(string)($x['external_hotel_id']??''),
            'tv_hotel_id'=>(int)($x['tv_hotel_id']??0),
            'operator'=>(string)($x['operator']??''),
            'operator_id'=>(int)($x['operator_id']??0),
            'operator_link_sha256'=>(string)($x['operator_link_sha256']??''),
            'canonical_anchor_count'=>(int)($x['canonical_anchor_count']??-1),
            'catalog_hotel'=>$x['catalog_hotel']??null,
        ];
    }
    usort($out,fn($a,$b)=>[$a['supplier_namespace'],$a['external_hotel_id'],$a['tv_hotel_id']]<=>[$b['supplier_namespace'],$b['external_hotel_id'],$b['tv_hotel_id']]);
    return $out;
}

if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    $mode=$argv[1]??'';
    if($mode==='--self-test'){
        $x=hm21_remaining(['rows'=>[
            ['status'=>'current_missing_edge','supplier_namespace'=>'bgoperator','external_hotel_id'=>'1','tv_hotel_id'=>2,'operator_link_sha256'=>str_repeat('a',64),'canonical_anchor_count'=>0],
            ['status'=>'resolved_same','supplier_namespace'=>'bgoperator','external_hotel_id'=>'2','tv_hotel_id'=>3],
        ]]);
        hm16_need(count($x)===1&&$x[0]['canonical_anchor_count']===0,'remaining');
        echo "MATCH_V9_OPERATOR_EDGES_POST_V20_V21_SELFTEST_OK\n";exit;
    }
    hm16_need($mode==='--execute','disabled');
    $root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$input=(string)getenv('MATCH_INPUT_FILE');$sha=(string)getenv('MATCH_SOURCE_SHA');
    hm16_need(is_dir($root)&&basename($root)==='anytoour.ru'&&is_dir($dir)&&basename($dir)===HM21_OP&&is_file($input)&&preg_match('/^[0-9a-f]{40}$/D',$sha),'runtime_scope');
    $res=hm16_load($dir.'/reservation.json');hm16_need(($res['operation']??'')===HM21_OP&&($res['state']??'')==='reserved_before_db_read','reservation');
    hm16_need(hash_file('sha256',$input)===HM16_INPUT_SHA,'input_hash');$norm=hm16_normalize(hm16_load($input));
    require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
    try{
        $base=hm16_execute(v2_data_db(),$norm,$sha);$remaining=hm21_remaining($base);
        $result=$base;$result['operation']=HM21_OP;$result['state']='completed_read_only_post_v20_reconcile';
        $result['predecessors']=['v17_run'=>35782031843,'v20_run'=>35783561787];
        $result['remaining_current_missing']=$remaining;
        $h=hm16_save($dir.'/result.json',$result);hm16_save($dir.'/receipt.json',['operation'=>HM21_OP,'state'=>$result['state'],'result_sha256'=>$h,'readback_verified'=>hash_file('sha256',$dir.'/result.json')===$h,'provider_accessed'=>false,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0]);
        echo hm16_json(['state'=>$result['state'],'status_counts'=>$result['status_counts'],'writer_ready'=>$result['writer_ready_count'],'remaining'=>$remaining])."\n";
    }catch(Throwable $e){
        $f=['operation'=>HM21_OP,'state'=>'failed_read_only_post_v20_reconcile','reason'=>preg_replace('/[^A-Za-z0-9_.:-]+/','_',mb_substr($e->getMessage(),0,100,'UTF-8')),'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0];
        $h=hm16_save($dir.'/result.json',$f);hm16_save($dir.'/receipt.json',['operation'=>HM21_OP,'state'=>$f['state'],'result_sha256'=>$h,'readback_verified'=>true,'provider_accessed'=>false,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0]);fwrite(STDERR,$f['reason']."\n");exit(2);
    }
}
