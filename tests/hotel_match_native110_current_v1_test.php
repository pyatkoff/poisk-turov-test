<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/scripts/diagnostics/hotel_match_native110_current_v1.php';

$checks=0;
function check(bool $ok,string $name): void {global $checks; if(!$ok)throw new RuntimeException('test:'.$name);++$checks;}
function rejects(callable $f,string $name): void {try{$f();}catch(RuntimeException $e){check(true,$name);return;}throw new RuntimeException('test:'.$name);}
$path=dirname(__DIR__).'/scripts/diagnostics/fixtures/hotel_match_native110_current_v1.json';
check(hash_file('sha256',$path)===NC110_MANIFEST_SHA,'immutable_manifest');
$m=json_decode(file_get_contents($path),true,64,JSON_THROW_ON_ERROR);
$s=nc110_scope($m);
check(count($s['catalog_ids'])===109&&!in_array(NC110_PROTECTED,$s['catalog_ids'],true),'protected_removed');
check(!in_array(144804,$s['target_ids'],true)&&in_array(56551,$s['target_ids'],true),'protected_target_and_independent_anchor');
foreach(['count','duplicate','namespace','target_native','protected'] as $case){
    $bad=$m;
    if($case==='count')array_pop($bad['rows']);
    if($case==='duplicate')$bad['rows'][1]['catalog_id']=$bad['rows'][0]['catalog_id'];
    if($case==='namespace')$bad['rows'][0]['native_by_operator']['tv_catalog']=['123'];
    if($case==='target_native')foreach($bad['rows'] as &$r)if($r['tv_candidates']){$r['tv_candidates'][0]['native_id']='999999999';break;}unset($r);
    if($case==='protected')foreach($bad['rows'] as &$r)if($r['catalog_id']===NC110_PROTECTED){$r['catalog_id']='999999999';break;}unset($r);
    rejects(fn()=>nc110_scope($bad),'scope_'.$case);
}

$raw=['hotelKey'=>3126,'operatorKey'=>315,'original'=>['hotelKey'=>849821,'operatorKey'=>315]];
check(nc110_fact($raw,'3126','operator_315','849821'),'original_native_fact');
foreach(['hotelKey','native','operator','original_operator','catalog_as_native','flag'] as $case){
    $bad=$raw;
    if($case==='hotelKey')$bad['hotelKey']=849821;
    if($case==='native')$bad['original']['hotelKey']=123;
    if($case==='operator')$bad['operatorKey']=342;
    if($case==='original_operator')$bad['original']['operatorKey']=342;
    if($case==='catalog_as_native')$bad['original']['hotelKey']=3126;
    if($case==='flag')$bad['isOperatorHotelKey']='true';
    check(!nc110_fact($bad,'3126','operator_315','849821'),'fact_'.$case);
}
$bg=['hotelKey'=>'13293','operatorKey'=>115,'original'=>['hotelKey'=>'123','operatorKey'=>115]];
check(nc110_fact($bg,'13293','operator_115','123'),'bg_raw_is_only_source_fact');
check(!nc110_fact($raw,'3126','operator_13','849821'),'tv_operator_not_samo_namespace');

$tmp=sys_get_temp_dir().'/native110-test-'.bin2hex(random_bytes(8));
mkdir($tmp,0700);mkdir($tmp.'/hotel-match-fixture',0700);mkdir($tmp.'/hotel-match-fixture/evidence-private',0700);
$relative='hotel-match-fixture/evidence-private/page.json';
$bytes=json_encode(['PRICES'=>[$raw,$bg]],JSON_THROW_ON_ERROR);file_put_contents($tmp.'/'.$relative,$bytes);
$sha=hash('sha256',$bytes);
$f=['catalog_id'=>'3126','supplier_namespace'=>'operator_315','native_id'=>'849821','evidence'=>[
    ['source_file'=>'operations/'.$relative,'sha256'=>$sha,'json_pointer'=>'/PRICES/0']]];
try{
    $b=$f;$b['catalog_id']='13293';$b['supplier_namespace']='operator_115';$b['native_id']='123';$b['evidence'][0]['json_pointer']='/PRICES/1';
    $a=nc110_raw($tmp,[$f,$b]);
    check($a['files_read']===1&&$a['bytes_read']===strlen($bytes)&&$a['facts'][0]['raw_verified']&&$a['facts'][1]['raw_verified'],'indexed_single_file_read');
    foreach(['hash','pointer','identity','traversal','missing'] as $case){
        $bad=$f;
        if($case==='hash')$bad['evidence'][0]['sha256']=str_repeat('a',64);
        if($case==='pointer')$bad['evidence'][0]['json_pointer']='/PRICES/99';
        if($case==='identity')$bad['native_id']='123';
        if($case==='traversal')$bad['evidence'][0]['source_file']='operations/../page.json';
        if($case==='missing')$bad['evidence'][0]['source_file']='operations/hotel-match-absent/page.json';
        $a=nc110_raw($tmp,[$bad,$b]);
        check(!$a['facts'][0]['raw_verified']&&$a['facts'][1]['raw_verified'],'raw_hold_does_not_block_'.$case);
    }
    $link=$tmp.'/hotel-match-fixture/evidence-private/link.json';symlink($tmp.'/'.$relative,$link);
    $bad=$f;$bad['evidence'][0]['source_file']='operations/hotel-match-fixture/evidence-private/link.json';
    check(!nc110_raw($tmp,[$bad])['facts'][0]['raw_verified'],'raw_symlink_rejected');unlink($link);
}finally{unlink($tmp.'/'.$relative);rmdir($tmp.'/hotel-match-fixture/evidence-private');rmdir($tmp.'/hotel-match-fixture');rmdir($tmp);}

$request=null;foreach($m['rows'] as $r)if($r['catalog_id']==='3126')$request=$r;
$evidence=w76_json(['source'=>['id'=>'3126','name'=>'unrelated display name']]);
$row=['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>'3126','local_hotel_id'=>null,'decision_status'=>'pending',
    'catalog_sha256'=>$request['catalog_sha256'],'evidence_json'=>$evidence,'evidence_sha256'=>hash('sha256',$evidence)];
$request['evidence_sha256']=$row['evidence_sha256'];
$hotel=['id'=>42903,'is_active'=>1];
$observed=nc110_classify($request,['3126'=>[$row]],[],[],[],[],[42903=>$hotel],[]);
check($observed['state']==='current_review_observed'&&$observed['holds']===[]&&$observed['safe_to_write_now']===false,'observed_is_never_accepted');
check(!str_contains(w76_json($observed),'unrelated display name')&&!array_key_exists('evidence_json',$observed['source_revision']),'private_history_not_exported');
$accepted=$row;$accepted['decision_status']='accepted';$accepted['local_hotel_id']=42903;
check(in_array('current_source_not_pending_null',nc110_classify($request,['3126'=>[$accepted]],[],[],[],[],[42903=>$hotel],[])['holds'],true),'accepted_source_is_hold');
check(in_array('current_source_not_unique',nc110_classify($request,['3126'=>[$row,$row]],[],[],[],[],[42903=>$hotel],[])['holds'],true),'duplicate_current_source');
$drift=$row;$drift['catalog_sha256']=str_repeat('a',64);
check(in_array('current_source_revision_differs',nc110_classify($request,['3126'=>[$drift]],[],[],[],[],[42903=>$hotel],[])['holds'],true),'current_source_drift');
$occupied=['42903'=>[['external_hotel_id'=>'other','decision_status'=>'accepted']]];
$v=nc110_classify($request,['3126'=>[$row]],$occupied,[],[42903=>true],[],[42903=>$hotel],[]);
check(in_array('target_catalog_occupied',$v['targets'][0]['holds'],true)&&in_array('target_manual_or_exclusion',$v['targets'][0]['holds'],true),'manual_and_occupancy_separate');
$request['local_anchor_ids']=[42903];
$v=nc110_classify($request,['3126'=>[$row]],[],[],[],[],[42903=>$hotel],[]);
check(count($v['targets'])===2&&$v['targets'][0]['kind']!==$v['targets'][1]['kind'],'tv_and_local_are_distinct');
check(nc110_classify(['catalog_id'=>NC110_PROTECTED],[],[],[],[],[],[],[])['state']==='protected_not_examined','protected_short_circuit');
$review=nc110_review_rows(['rows'=>[$request,['catalog_id'=>NC110_PROTECTED]]],
    [$observed,nc110_classify(['catalog_id'=>NC110_PROTECTED],[],[],[],[],[],[],[])],
    ['source_facts'=>['3126'=>[['namespace'=>'operator_315','native_id'=>'849821',
        'unique_catalog_in_saved_union'=>true,'raw'=>['raw_verified'=>true,'failures'=>[],
            'references'=>[['raw_secret'=>'fixture-secret']]]]]],
     'tv_proofs'=>['3126|funsun|42903'=>['producers'=>[['source_operation'=>'hotel-match-fixture',
        'source_result_sha256'=>str_repeat('a',64),'audit'=>['state'=>'saved_tv_proof_verified','failures'=>[],
            'raw_secret'=>'fixture-secret']]]]]]);
check(count($review)===2&&$review[0]['native_checks'][0]['raw_verified']
    &&$review[0]['tv_checks'][0]['producers'][0]['state']==='saved_tv_proof_verified','per_source_evidence_readback');
check(!str_contains(w76_json($review),'fixture-secret')&&!str_contains(w76_json($review),'unrelated display name'),
    'review_excludes_raw_and_history');
check($review[1]['native_checks']===[]&&$review[1]['targets']===[]&&$review[1]['tv_checks']===[],
    'protected_review_has_no_evidence_or_targets');
check($review[0]['safe_to_write_now']===false&&$review[1]['safe_to_write_now']===false,'readback_never_grants_authority');
rejects(fn()=>nc110_main(['runner','--execute']),'no_write_entrypoint');
echo 'native110 checks: '.$checks."\n";
