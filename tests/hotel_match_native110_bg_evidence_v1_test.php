<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/scripts/diagnostics/hotel_match_native110_bg_evidence_v1.php';
$checks=0;
function bgcheck(bool $ok,string $name): void {global $checks;++$checks;if(!$ok)throw new RuntimeException('bg_test:'.$name);}
$url='https://www.bgoperator.ru/price.shtml?tid=102123&tid=102456&HOTELLIST=123,-456,789&token=fixture-secret';
$link=be110_link('original.hotelUrl',$url);
bgcheck(count($link['hotel_selectors'])===3,'repeated_parameters_preserved');
bgcheck($link['hotel_selectors'][2]['positive_tokens']===['123','789']&&$link['hotel_selectors'][2]['opaque_tokens']===1,'signed_multiple_not_single');
bgcheck($link['signed_parameters_present']&&!str_contains(w76_json($link),'fixture-secret')&&!str_contains(w76_json($link),'price.shtml?'),'url_values_remain_private');
bgcheck($link['url_sha256']===hash('sha256',$url),'full_signed_url_hash');
foreach(['http://www.bgoperator.ru/a','https://www.bgoperator.ru.evil.test/a','https://user:fixture-secret@www.bgoperator.ru/a','https://other.test/a'] as $bad)bgcheck(be110_link('hotelUrl',$bad)===null,'host_credentials_or_scheme_rejected');
$fields=be110_fields(['hotelKey'=>'13293','api_token'=>'fixture-secret','town'=>'Мармарис','townKey'=>77,
    'original'=>['hotelKey'=>'123','hotelUrl'=>$url,'country'=>'Турция','latitude'=>36.8,'password'=>'fixture-secret','serviceUrl'=>'https://other.test/?token=fixture-secret']]);
bgcheck(count($fields['bg_links'])===1&&count($fields['location_fields'])===4,'known_hotel_level_fields_only');
bgcheck(!str_contains(w76_json($fields),'fixture-secret'),'field_inventory_does_not_export_values');
bgcheck(in_array('api_token',$fields['top_fields'],true),'inventory_is_keys_only');
$m=json_decode(file_get_contents(dirname(__DIR__).'/scripts/diagnostics/fixtures/hotel_match_native110_current_v1.json'),true,64,JSON_THROW_ON_ERROR);
$tmp=sys_get_temp_dir().'/bg-review-'.bin2hex(random_bytes(8));mkdir($tmp,0700);mkdir($tmp.'/hotel-match-fixture',0700);
$facts=[];$prices=[];
foreach($m['rows'] as $r)foreach($r['tv_candidates'] as $c)if($c['operator']==='bg'){
    $prices[]=['hotelKey'=>$r['catalog_id'],'operatorKey'=>115,'original'=>['hotelKey'=>$c['native_id'],'operatorKey'=>115,'hotelUrl'=>'https://www.bgoperator.ru/price.shtml?tid='.$c['tv_native_id']]];
    $facts[$r['catalog_id']][]=['namespace'=>'operator_115','native_id'=>$c['native_id'],'raw'=>['references'=>[['verified'=>true,'source_file'=>'operations/hotel-match-fixture/page.json','sha256'=>'','json_pointer'=>'/PRICES/'.(count($prices)-1)]]]];
}
$bytes=w76_json(['PRICES'=>$prices]);file_put_contents($tmp.'/hotel-match-fixture/page.json',$bytes);
foreach($facts as &$fs)foreach($fs as &$f)$f['raw']['references'][0]['sha256']=hash('sha256',$bytes);unset($fs,$f);
try{
    $out=be110_review($tmp,$m,['saved_evidence'=>['source_facts'=>$facts]]);
    bgcheck(count($out['rows'])===18&&$out['raw_files_read']===1,'whole18_single_indexed_pass');
    bgcheck(array_sum(array_map(fn($r)=>count($r['bg_links']),$out['rows']))===18,'all18_source_hotel_urls_examined');
    bgcheck(count(array_filter($out['rows'],fn($r)=>$r['safe_to_write_now']!==false))===0,'not_acceptance_or_current_replay');
    $cat=array_key_first($facts);$facts[$cat][0]['raw']['references'][0]['sha256']=str_repeat('a',64);
    $out=be110_review($tmp,$m,['saved_evidence'=>['source_facts'=>$facts]]);
    bgcheck(count(array_filter($out['rows'],fn($r)=>$r['failures']!==[]))===1,'one_drift_does_not_stop_other17');
}finally{unlink($tmp.'/hotel-match-fixture/page.json');rmdir($tmp.'/hotel-match-fixture');rmdir($tmp);}
echo 'BG_ORIGINAL_FIELDS_PASS '.$checks."\n";
