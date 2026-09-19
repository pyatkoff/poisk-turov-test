<?php
declare(strict_types=1);
putenv('HMNF_LIBRARY_ONLY=1');
require __DIR__.'/../scripts/diagnostics/hotel_match_unseen_native_frontier.php';
$n=0;
function ck(bool $ok,string $message): void { global $n;$n++;if(!$ok)throw new RuntimeException($message); }
function row(string $id,array $extra=[]): array {
    $e=['source'=>['stateKey'=>3,'name'=>'Hotel '.$id]]+$extra;
    return ['external_hotel_id'=>$id,'evidence_json'=>hmnf_json($e),'evidence_sha256'=>hash('sha256',hmnf_json($e))];
}
$core=[1=>['name'=>'Египет','label'=>'egypt'],12=>['name'=>'Шри-Ланка','label'=>'sri_lanka']];
ck(hmnf_country(' ЕГИПЕТ ')==='egypt','unicode_country');
ck(hmnf_country('Шри-Ланка')==='sri_lanka','hyphen_country');
ck(hmnf_country('Россия')===null&&hmnf_country('Абхазия')===null,'unsold');
ck(hmnf_id('1')==='1'&&hmnf_id('0')===null&&hmnf_id('12.4')===null&&hmnf_id('001')===null,'typed_ids');
ck(hmnf_state(['source'=>['stateKey'=>3],'state_key'=>5])===null,'conflicting_states');
ck(hmnf_state(['source'=>['stateKey'=>3],'state_key'=>3])==='3','same_states');
ck(hmnf_protected(['source'=>['manual'=>true]]),'manual');
ck(hmnf_protected(['pair_exclusion'=>['local'=>45]]),'structured_exclusion');
ck(!hmnf_protected(['manual'=>false,'conflict'=>'none']),'false_marker');
ck(hmnf_frequency(['count'=>999,'source'=>['count'=>9999]])['value']===null,'generic_count_is_not_live');
ck(hmnf_frequency(['search_count'=>0])['value']===0,'explicit_zero');
ck(hmnf_frequency(['live_frequency'=>2,'observations'=>['seen_count'=>4]])['value']===4,'explicit_max_not_sum');
ck(hmnf_frequency(['frequency'=>300])['value']===null,'untyped_frequency_not_inferred');
ck(isset(hmnf_existing_relation_ids(['previous'=>['provider_bridges'=>[['andromeda_hotel_id'=>'23']]]])['23']),'retained_relation');
ck(!hmnf_existing_relation_ids(['original'=>['hotelKey'=>'23']]),'native_integer_not_catalog_identity');
$accepted=[];for($i=0;$i<5;$i++)$accepted[]=['evidence_json'=>'{"source":{"stateKey":3}}','country_id'=>1];
[$a,$support]=hmnf_authority($accepted,$core);ck($a===['3'=>1],'supported_state');
$accepted[]=['evidence_json'=>'{"source":{"stateKey":3}}','country_id'=>12];
ck(hmnf_authority($accepted,$core)[0]===[],'country_disagreement_hold');
$pending=[row('1',['live_frequency'=>4]),row('2'),row('3'),row('4',['manual'=>true]),row('5',['search_count'=>0])];
$plan=hmnf_plan($pending,['3'=>1],$core,['3'=>true],['2']);
ck(array_column($plan['queue'],'andromeda_hotel_id')===['1','5'],'queue_only_disjoint');
ck($plan['route_counts']===['existing_typed_relation'=>1,'foreign_claim'=>1,'protected_evidence'=>1,'queue'=>2],'partition');
$other=row('6');$other['evidence_json']='{"source":{"stateKey":3,"name":"Test","countryName":"Россия"}}';
ck(hmnf_plan([$other],['3'=>1],$core,[],[])['queue']===[],'direct_country_conflict');
$other=row('7');$other['evidence_json']='{"source":{"stateKey":3,"name":"FORTUNA hotel"}}';
ck(hmnf_plan([$other],['3'=>1],$core,[],[])['route_counts']===['non_hotel_product'=>1],'product_hold');
$other=row('8');$other['evidence_sha256']='';
ck(hmnf_plan([$other],['3'=>1],$core,[],[])['queue']===[],'missing_digest');
ck(hmnf_plan([row('9')],[],[],[],[])['queue']===[],'unknown_state');
$large=[];for($i=10000;$i<12000;$i++)$large[]=row((string)$i);
$x=hmnf_plan($large,['3'=>1],$core,[],[]);ck(count($x['queue'])===2000,'mass_no_truncation');
ck(count(array_filter($x['queue'],fn($v)=>$v['frequency']===null))===2000,'missing_frequency_null');
foreach([10,30] as $limit){$chunks=hmnf_chunks(array_column($x['queue'],'andromeda_hotel_id'),$limit);
    ck(count(array_merge(...$chunks))===2000,'chunk_coverage');
    ck(max(array_map('count',$chunks))<=$limit,'chunk_count');
    ck(max(array_map(fn($v)=>strlen(implode(',',$v)),$chunks))<=300,'csv_budget');
}
$long=[];for($i=10;$i<50;$i++)$long[]='123456789012345678'.$i;
$chunks=hmnf_chunks($long,30);ck(count($chunks)>1&&count(array_merge(...$chunks))===40,'long_key_chunk');
try{hmnf_chunks(['1','1'],30);ck(false,'duplicates');}catch(RuntimeException $e){ck($e->getMessage()==='chunk_identity','duplicate_refused');}
try{hmnf_chunks(['1'],31);ck(false,'limit');}catch(RuntimeException $e){ck($e->getMessage()==='chunk_limit','limit_refused');}
$dir=sys_get_temp_dir().'/hmnf-test-'.bin2hex(random_bytes(8));mkdir($dir,0700);
try{$sha=hmnf_write($dir.'/result.json',['ok'=>true]);ck($sha===hash_file('sha256',$dir.'/result.json'),'durable_readback');
    try{hmnf_write($dir.'/result.json',['ok'=>false]);ck(false,'overwrite');}catch(RuntimeException $e){ck($e->getMessage()==='exclusive_output','no_replay_output');}
}finally{unlink($dir.'/result.json');rmdir($dir);}
echo "PASS $n checks; 2000-row complete frontier; supplier/DB calls 0\n";
