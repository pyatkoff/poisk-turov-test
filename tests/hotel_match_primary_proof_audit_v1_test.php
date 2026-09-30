<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/scripts/diagnostics/hotel_match_primary_proof_audit_v1.php';
$checks=0;
function ppcheck(bool $ok,string $label): void {global $checks;++$checks;if(!$ok)throw new RuntimeException($label);}
function ppfixture(int $id): array {
    $s=PM1_PAIRS[$id];
    return ['tv_hotel_id'=>$id,'operator_id'=>$s['operator'],'namespace'=>$s['namespace'],
        'state'=>'detail_identity_verified','link_state'=>'captured_single_native',
        'positive_native_candidates'=>[(int)$s['native']],
        'operator_link_host'=>$s['operator']===25?'b2b.fstravel.com':'searchtour.intourist.ru',
        'operator_link_sha256'=>str_repeat('a',64),'tour_id_sha256'=>str_repeat('b',64),
        'search_id_sha256'=>str_repeat('c',64)];
}
foreach(PM1_PAIRS as $id=>$spec){
    $edge=ppfixture($id);$run=fn($edges)=>pp1_producer(['edges'=>$edges],$id,$spec);
    ppcheck($run([$edge])['state']==='saved_tv_proof_verified','valid_'.$id);
    ppcheck(pp1_edge_failures($edge,$id,$spec)===[]&&pm1_tv_edge($edge,$id,$spec),'predicate_parity');
    foreach(['state','link_state','namespace','operator_link_host','operator_link_sha256','tour_id_sha256','search_id_sha256'] as $key){
        $bad=$edge;$bad[$key]='wrong';$r=$run([$bad]);
        ppcheck($r['state']==='proof_hold'&&in_array($key,$r['edge_checks'][0]['failed_fields'],true),'field_'.$key);
        ppcheck(!pm1_tv_edge($bad,$id,$spec),'writer_still_rejects');
    }
    ppcheck($run([])['failures']===['verified_edge_missing','source_target_not_unique','target_native_not_unique'],'empty');
    $conflict=$edge;$conflict['tv_hotel_id']=$id+1;
    ppcheck($run([$edge,$conflict])['failures']===['source_target_not_unique'],'source_conflict');
    $conflict=$edge;$conflict['positive_native_candidates']=[999999];
    ppcheck($run([$edge,$conflict])['failures']===['target_native_not_unique'],'target_conflict');
    $bad=$edge;$bad['positive_native_candidates']=[(int)$spec['native'],999999];
    ppcheck(in_array('positive_native_candidates',$run([$bad])['edge_checks'][0]['failed_fields'],true),'ambiguous');
    $bad=$edge;$bad['positive_native_candidates']=['secret'=>'fixture-secret'];
    ppcheck(!str_contains(w76_json($run([$bad])),'fixture-secret'),'invalid_native_redacted');
    $edge['operatorLink']='https://example.test/?token=fixture-secret';$edge['operator_link_host']='secret-host.invalid';
    ppcheck(!str_contains(w76_json($run([$edge])),'fixture-secret')&&!str_contains(w76_json($run([$edge])),'secret-host'),'secret_redacted');
    $many=array_fill(0,100,$edge);$many[]=ppfixture($id);$r=$run($many);
    ppcheck($r['state']==='saved_tv_proof_verified'&&$r['proof_matches']===1&&$r['edge_checks_omitted']===1,'proof_after_projection_cap');
    $conflict=ppfixture($id);$conflict['tv_hotel_id']=$id+1;$many[]=$conflict;
    ppcheck($run($many)['failures']===['source_target_not_unique'],'conflict_after_projection_cap');
}
$dir=sys_get_temp_dir().'/pp1-'.bin2hex(random_bytes(8));mkdir($dir,0700);
foreach(PM1_PAIRS as $id=>$spec){
    $detail=$id!==16944;
    $url='https://'.($spec['operator']===25?'b2b.fstravel.com':'searchtour.intourist.ru').'/search?HOTELS='.$spec['native'];
    $legacy=['tv_hotel_id'=>$id,'operator_id'=>$spec['operator'],'state'=>'detail_identity_verified','link_state'=>'captured_single_native',
        'positive_native_candidates'=>[(int)$spec['native']],'operator_link'=>$url,'operator_link_sha256'=>hash('sha256',$url),'tour_id'=>'123456'];
    if($detail)$legacy+=['target_supplier_namespace'=>$spec['namespace'],'retained_search_id'=>'789','returned_tv_hotel_id'=>$id,'returned_operator_id'=>$spec['operator'],'returned_tour_id'=>'123456'];
    else $legacy['search_id']='789';
    ppcheck(!pm1_tv_edge($legacy,$id,$spec),'legacy_originally_rejected');
    $projection=pm1_tv_projection($legacy,$id,$spec);
    ppcheck($projection!==null&&pm1_tv_edge($projection,$id,$spec),'legacy_strict_projection');
    ppcheck(array_intersect(['operator_link','tour_id','search_id'],array_keys($projection))===[],'legacy_raw_fields_removed');
    ppcheck(pp1_producer(['edges'=>[$legacy]],$id,$spec)['state']==='saved_tv_proof_verified','legacy_reader');
    foreach(['tv_hotel_id'=>$id+1,'operator_id'=>99,'state'=>'detail_identity_mismatch','link_state'=>'captured_ambiguous_native',
        'tour_id'=>'0','operator_link_sha256'=>str_repeat('d',64),'namespace'=>'wrong'] as $key=>$value){
        $bad=$legacy;$bad[$key]=$value;ppcheck(pm1_tv_projection($bad,$id,$spec)===null,'legacy_mutation_'.$key);
    }
    foreach(['https://evil.example/search?HOTELS='.$spec['native'],$url.'&HOTELS='.$spec['native'],$url.'&session=secret',$url.'#fragment'] as $badUrl){
        $bad=$legacy;$bad['operator_link']=$badUrl;$bad['operator_link_sha256']=hash('sha256',$badUrl);
        ppcheck(pm1_tv_projection($bad,$id,$spec)===null,'legacy_origin_guard');
    }
    $badSpec=$spec;$badSpec['producer_sha']=str_repeat('a',64);ppcheck(pm1_tv_projection($legacy,$id,$badSpec)===null,'legacy_wrong_pin');
    $bad=$legacy;$bad[$detail?'retained_search_id':'search_id']='0';ppcheck(pm1_tv_projection($bad,$id,$spec)===null,'legacy_bad_search');
    if($detail){$bad=$legacy;$bad['returned_tour_id']='999';ppcheck(pm1_tv_projection($bad,$id,$spec)===null,'legacy_returned_identity');}
}
$before=scandir($dir);$r=pp1_saved($dir);
ppcheck(scandir($dir)===$before,'no_files_created');
ppcheck(count($r['rows'])===3&&array_column($r['rows'],'state')===array_fill(0,3,'producer_unavailable'),'exact_missing_scope');
ppcheck($r['database_writes']===0&&$r['mapping_writes']===0&&$r['provider_http_calls']===0&&!$r['safe_to_write_now'],'read_only');
mkdir($dir.'/hotel-match-fixture');$op=$dir.'/hotel-match-fixture';
$result=['operation'=>'hotel-match-fixture','state'=>'terminal_wrapper_timeout_salvaged_no_replay','database_writes'=>0,'mapping_writes'=>0];
$bytes=w76_json($result);$sha=hash('sha256',$bytes);file_put_contents($op.'/result.json',$bytes);
$receipt=['state'=>$result['state'],'result_sha256'=>$sha];file_put_contents($op.'/receipt.json',w76_json($receipt));
$rejected=false;try{pm1_terminal($dir,'hotel-match-fixture',$sha,[$result['state']]);}catch(RuntimeException $e){$rejected=$e->getMessage()==='salvaged_terminal_required';}
ppcheck($rejected,'unsealed_salvage_rejected');
$receipt+=['salvaged_after_wrapper_timeout'=>true,'no_replay'=>true];file_put_contents($op.'/receipt.json',w76_json($receipt));
ppcheck(pm1_terminal($dir,'hotel-match-fixture',$sha,[$result['state']])===$result,'sealed_salvage');
file_put_contents($op.'/result.json',$bytes.' ');$rejected=false;
try{pm1_terminal($dir,'hotel-match-fixture',$sha,[$result['state']]);}catch(RuntimeException $e){$rejected=$e->getMessage()==='retained_digest';}
ppcheck($rejected,'tampering_rejected');
unlink($op.'/result.json');unlink($op.'/receipt.json');
$edge=ppfixture(420);$edge['operatorLink']='fixture-secret';
file_put_contents($op.'/tv-edge-420-43.json',w76_json($edge));
file_put_contents($op.'/result.json',w76_json(['edges'=>[$edge]]));
$before=scandir($op);$inventory=pp1_origins($dir);
ppcheck($inventory['state']==='completed_bounded_inventory'&&$inventory['files_read']===2,'origin_scan');
ppcheck(array_column($inventory['rows'],'tv_hotel_id')===[420,16944,42903],'origin_fixed_scope');
$refs=$inventory['rows'][0]['references'];
ppcheck(count($refs)===2&&$refs[0]['json_pointer']==='/edges/0'&&$refs[1]['json_pointer']===''&&$refs[0]['verified']&&$refs[1]['verified'],'both_origin_formats');
ppcheck($refs[1]['sha256']===hash_file('sha256',$op.'/tv-edge-420-43.json'),'origin_file_hash');
ppcheck(!str_contains(w76_json($inventory),'fixture-secret')&&scandir($op)===$before,'origin_redaction_read_only');
unlink($op.'/result.json');unlink($op.'/tv-edge-420-43.json');
file_put_contents($op.'/result.json','invalid-json');
ppcheck(pp1_origins($dir)['invalid_files']===1,'origin_invalid_json');
unlink($op.'/result.json');rmdir($op);rmdir($dir);
echo 'PP1_TEST_PASS '.$checks."\n";
