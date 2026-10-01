<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/scripts/diagnostics/hotel_match_shams_geography_saved_v1.php';
$checks=0;
function sgcheck(bool $ok,string $name): void {global $checks;++$checks;if(!$ok)throw new RuntimeException('shams_test:'.$name);}
function sgreject(callable $fn,string $name): void {try{$fn();}catch(RuntimeException $e){sgcheck(true,$name);return;}throw new RuntimeException('shams_test:'.$name);}
$tmp=sys_get_temp_dir().'/shams-geo-test-'.bin2hex(random_bytes(8));mkdir($tmp,0700);mkdir($tmp.'/hotel-match-fixture',0700);
$prices=[
    ['hotelKey'=>9501,'operatorKey'=>5,'town'=>'Marsa Alam','original'=>['hotelKey'=>835,'operatorKey'=>5,'townName'=>'Marsa Alam','hotelLatitude'=>24.694,'hotelLongitude'=>35.087]],
    ['hotelKey'=>9501,'operatorKey'=>342,'townKey'=>123,'original'=>['hotelKey'=>24402,'operatorKey'=>342,'townName'=>'Марса-Алам','hotelUrl'=>'https://private.invalid/?token=NEVER_EXPORT','secret'=>'NEVER_EXPORT']],
];
$relative='hotel-match-fixture/page.json';$raw=json_encode(['PRICES'=>$prices],JSON_THROW_ON_ERROR);file_put_contents($tmp.'/'.$relative,$raw);$sha=hash('sha256',$raw);
$facts=[];foreach([['operator_5','835'],['operator_342','24402']] as $index=>[$ns,$native])$facts[]=[
    'namespace'=>$ns,'native_id'=>$native,'raw'=>['raw_verified'=>true,'references'=>[
        ['verified'=>true,'source_file'=>'operations/'.$relative,'sha256'=>$sha,'json_pointer'=>'/PRICES/'.$index]]]];
$input=['schema'=>'native110-current-review/1','operation'=>NC110_OP,'source_sha'=>SG110_INPUT_SOURCE,'batch'=>NC110_BATCH,
    'captured_at_utc'=>'2026-10-01T07:50:00Z','provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,
    'safe_to_write_now'=>false,'no_replay'=>true,'rows'=>array_fill(0,109,['catalog_id'=>'fixture_other']),
    'saved_evidence'=>['source_facts'=>['9501'=>$facts]]];
$input['rows'][]=['catalog_id'=>'9501','state'=>'current_review_observed','targets'=>[
    ['kind'=>'tv_candidate','id'=>420,'catalog_record'=>['id'=>420,'country_name'=>'Египет','region_name'=>'Марса-Алам','subregion_name'=>'','latitude'=>24.694,'longitude'=>35.087]]]];
try{
    $result=sg110_review($tmp,$input);
    sgcheck($result['raw_files_read']===1&&$result['raw_bytes_read']===strlen($raw),'deduplicated_page_read');
    sgcheck(count($result['references'])===2&&$result['references'][0]['raw_verified']&&$result['references'][1]['raw_verified'],'both_independent_original_native_facts');
    sgcheck(count($result['references'][0]['location_fields'])===4&&count($result['references'][1]['location_fields'])===2,'original_and_top_level_geography');
    sgcheck(in_array(['source_field'=>'saved_target.region_name','value'=>'Марса-Алам'],$result['saved_target_geography'],true),'saved_target_label_is_explicit');
    sgcheck(!$result['source_history_geography_exported']&&!$result['safe_to_write_now']&&$result['database_reads']===0&&$result['mapping_writes']===0,'evidence_has_no_fresh_or_write_authority');
    sgcheck(!str_contains(w76_json($result),'NEVER_EXPORT')&&!str_contains(w76_json($result),'private.invalid'),'private_values_not_exported');
    $dirty=sg110_fields(['city'=>'https://private.invalid/?auth=NEVER_EXPORT','latitude'=>INF,'country'=>'Egypt','townKey'=>123,'town'=>['token'=>'NEVER_EXPORT']],'original');
    sgcheck(count($dirty['location_fields'])===2,'reject_urls_nonfinite_and_nested_values');
    foreach(['input_source','input_operation','count','duplicate_source','wrong_target','namespace','native','unverified'] as $case){
        $bad=$input;
        if($case==='input_source')$bad['source_sha']=str_repeat('0',40);
        if($case==='input_operation')$bad['operation']='consumed-writer';
        if($case==='count')array_pop($bad['rows']);
        if($case==='duplicate_source')$bad['rows'][0]=$bad['rows'][109];
        if($case==='wrong_target')$bad['rows'][109]['targets'][0]['id']=421;
        if($case==='namespace')$bad['saved_evidence']['source_facts']['9501'][0]['namespace']='operator_115';
        if($case==='native')$bad['saved_evidence']['source_facts']['9501'][0]['native_id']='9501';
        if($case==='unverified')$bad['saved_evidence']['source_facts']['9501'][0]['raw']['raw_verified']=false;
        sgreject(fn()=>sg110_review($tmp,$bad),'binding_'.$case);
    }
    foreach(['digest','pointer','path','identity'] as $case){
        $bad=$input;$ref=&$bad['saved_evidence']['source_facts']['9501'][0]['raw']['references'][0];
        if($case==='digest')$ref['sha256']=str_repeat('a',64);
        if($case==='pointer')$ref['json_pointer']='/PRICES/99';
        if($case==='path')$ref['source_file']='operations/../page.json';
        if($case==='identity')$ref['json_pointer']='/PRICES/1';
        unset($ref);$review=sg110_review($tmp,$bad);
        sgcheck(!$review['references'][0]['raw_verified']&&$review['references'][1]['raw_verified'],'isolate_invalid_reference_'.$case);
    }
    symlink($tmp.'/'.$relative,$tmp.'/hotel-match-fixture/link.json');
    $bad=$input;$bad['saved_evidence']['source_facts']['9501'][0]['raw']['references'][0]['source_file']='operations/hotel-match-fixture/link.json';
    $review=sg110_review($tmp,$bad);sgcheck(!$review['references'][0]['raw_verified'],'reject_symlink');unlink($tmp.'/hotel-match-fixture/link.json');
    sgreject(fn()=>sg110_main(['script.php','--current']),'no_current_entrypoint');
    sgreject(fn()=>sg110_main(['script.php','--write']),'no_write_entrypoint');
    sgcheck(pm1_geography(['town'=>'Marsa Alam','latitude'=>24.694,'longitude'=>35.087],['region_name'=>'Marsa Alam','latitude'=>25.1,'longitude'=>35.087])===['coordinate_conflict_over_5km'],'existing_coordinate_conflict_preserved');
    sgcheck(pm1_geography(['town'=>'Marsa Alam'],['region_name'=>'Марса-Алам'])===['geography_requires_review'],'no_transliteration_acceptance');
}finally{unlink($tmp.'/'.$relative);rmdir($tmp.'/hotel-match-fixture');rmdir($tmp);}
echo 'SHAMS_SAVED_GEOGRAPHY_PASS '.$checks."\n";
