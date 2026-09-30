<?php
declare(strict_types=1);
/** Read saved PM1 proofs only. No DB, provider, reservation, or writer entrypoint. */
require_once __DIR__.'/hotel_match_primary_candidate_v1.php';

function pp1_edge_failures(array $edge,int $id,array $spec): array {
    $failures=[];
    foreach(['tv_hotel_id'=>$id,'operator_id'=>$spec['operator']] as $key=>$expected){
        if((int)($edge[$key]??0)!==$expected)$failures[]=$key;
    }
    foreach(['state'=>'detail_identity_verified','link_state'=>'captured_single_native',
        'namespace'=>$spec['namespace']] as $key=>$expected){
        if(($edge[$key]??null)!==$expected)$failures[]=$key;
    }
    $ids=$edge['positive_native_candidates']??null;
    if(!is_array($ids)||count($ids)!==1||!is_scalar(reset($ids))
        ||(string)reset($ids)!==$spec['native'])$failures[]='positive_native_candidates';
    foreach(['operator_link_sha256','tour_id_sha256','search_id_sha256'] as $key){
        if(!w76_sha($edge[$key]??null))$failures[]=$key;
    }
    if(($edge['operator_link_host']??null)!==($spec['operator']===25?'b2b.fstravel.com':'searchtour.intourist.ru')){
        $failures[]='operator_link_host';
    }
    return $failures;
}

function pp1_producer(array $producer,int $id,array $spec): array {
    $targets=[];$natives=[];$checks=[];$proof=false;$proofCount=0;$relevantCount=0;$invalid=0;
    $edges=$producer['edges']??null;
    if(!is_array($edges))return ['state'=>'proof_hold','failures'=>['producer_edges_missing'],
        'proof_matches'=>0,'source_targets'=>[],'target_natives'=>[],'edge_checks'=>[]];
    foreach($edges as $index=>$edge){
        if(!is_array($edge)){++$invalid;continue;}
        if((int)($edge['operator_id']??0)!==$spec['operator'])continue;
        $other=(int)($edge['tv_hotel_id']??0);$ids=$edge['positive_native_candidates']??null;
        $native=is_array($ids)&&count($ids)===1&&isset($ids[0])&&is_scalar($ids[0])?(string)$ids[0]:null;
        $single=($edge['link_state']??null)==='captured_single_native'&&$native!==null;
        if($single&&$native===$spec['native'])$targets[$other]=true;
        if($single&&$other===$id)$natives[$native]=true;
        if($other!==$id&&$native!==$spec['native'])continue;
        ++$relevantCount;
        $reasons=pp1_edge_failures($edge,$id,$spec);
        // The original writer predicate remains the authority, not this explanatory list.
        $verified=$reasons===[]&&pm1_tv_edge($edge,$id,$spec);
        if($verified){$proof=true;++$proofCount;}
        if(count($checks)<100)$checks[]=['json_pointer'=>'/edges/'.str_replace(['~','/'],['~0','~1'],(string)$index),
            'verified'=>$verified,'failed_fields'=>$reasons];
    }
    $targetKeys=array_keys($targets);$nativeKeys=array_map('strval',array_keys($natives));
    $failures=[];
    if(!$proof)$failures[]='verified_edge_missing';
    if($targetKeys!==[$id])$failures[]='source_target_not_unique';
    if($nativeKeys!==[$spec['native']])$failures[]='target_native_not_unique';
    return ['state'=>$failures?'proof_hold':'saved_tv_proof_verified','failures'=>$failures,
        'proof_matches'=>$proofCount,'edge_checks_omitted'=>max(0,$relevantCount-count($checks)),
        'source_targets'=>$targetKeys,'target_natives'=>array_values(array_filter($nativeKeys,
            fn($n)=>preg_match('/^[1-9][0-9]{0,31}$/D',$n)===1)),
        'invalid_edge_rows'=>$invalid,'edge_checks'=>$checks];
}

function pp1_saved(string $root): array {
    $rows=[];
    foreach(PM1_PAIRS as $id=>$spec){
        $row=['tv_hotel_id'=>$id,'catalog_id'=>$spec['catalog'],'supplier_namespace'=>$spec['namespace'],
            'native_id'=>$spec['native'],'source_operation'=>$spec['producer'],
            'source_result_sha256'=>$spec['producer_sha'],'safe_to_write_now'=>false];
        try{
            $producer=pm1_terminal($root,$spec['producer'],$spec['producer_sha'],
                ['completed_read_only','terminal_wrapper_timeout_salvaged_no_replay']);
            $row+=pp1_producer($producer,$id,$spec);
        }catch(Throwable $e){
            $reason=preg_match('/^[a-z_]+$/D',$e->getMessage())?$e->getMessage():'retained_read_failed';
            $row+=['state'=>'producer_unavailable','failures'=>[$reason]];
        }
        $rows[]=$row;
    }
    return ['state'=>'completed_saved_proof_audit','batch'=>PM1_BATCH,'rows'=>$rows,
        'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'safe_to_write_now'=>false];
}

if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    if(count($argv)!==3||$argv[1]!=='--read-saved'){
        fwrite(STDERR,"Usage: php hotel_match_primary_proof_audit_v1.php --read-saved OPERATIONS_DIRECTORY\n");exit(2);
    }
    echo w76_json(pp1_saved($argv[2]));
}
