<?php
declare(strict_types=1);
putenv('MATCH_SAVED_EVIDENCE_TEST_LIBRARY=1');
require_once __DIR__.'/../scripts/diagnostics/hotel_match_saved_andromeda_evidence.php';
$n=0;
function ok(bool $v,string $name): void {global $n;++$n;if(!$v)throw new RuntimeException('test_'.$name);}
function fails(callable $fn,string $name): void {try{$fn();}catch(Throwable $e){ok(true,$name);return;}ok(false,$name);}
foreach([1,'2000000001'] as $v)ok(msa_id($v)===(string)$v,'id');
foreach([true,false,0,'0','01','1,2','1e3',1.2,[],null,'-5'] as $v)ok(msa_id($v)===null,'bad_id');
ok(msa_core('Шри-Ланка')&&msa_core('Египет'),'core_ru');ok(!msa_core('Россия')&&!msa_core('Абхазия'),'no_unsold');
$u=msa_url('https://files.anextour.ru/hotel/egypt/o417822?hotelCode=5844&utm_source=x');
ok($u['explicit_anex_hotel_id']==='5844','explicit_id');ok(!str_contains($u['url'],'utm_'),'tracking_not_exported');
ok(msa_url('https://files.anextour.ru/photo/1767.jpg')['explicit_anex_hotel_id']===null,'photo_not_hotel');
ok(msa_url('https://www.anextour.ru/hotel?HOTELLIST=8121')['explicit_anex_hotel_id']==='8121','hotellist');
foreach(['https://evil.ru/?hotelCode=5','https://anextour.ru.evil.ru/?hotelCode=5','https://user@anextour.ru/hotel','https://anextour.ru:443/hotel','http://anextour.ru/hotel','https://anextour.ru/hotel?token=SECRET','https://anextour.ru/hotel?%73id=SECRET','https://anextour.ru/hotel?hotelCode=1&HOTELLIST=2','https://anextour.ru/hotel?hotelCode=1&hotelCode=2','https://anextour.ru/hotel?hotelCode=1,2'] as $v)ok(msa_url($v)===null,'url_guard');
ok(msa_url('https://intourist.ru/hotel/5?hotelCode=5')['explicit_anex_hotel_id']===null,'host_namespace');
$ref=str_repeat('a',64);ok(msa_search_filename($ref.'-1.json'),'first_page');ok(msa_search_filename($ref.'-1789999999-2.json'),'later_page');
foreach([$ref.'-auth.json',$ref.'-1789999999-2-offer_x-package.json','../'.$ref.'-1.json','monthly-requests.json',$ref.'-0.json'] as $v)ok(!msa_search_filename($v),'not_search_page');
$row=['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>'111','decision_status'=>'pending','local_hotel_id'=>null,'evidence_sha256'=>str_repeat('b',64),'evidence_json'=>'{"source":{"name":"Royal Beach 12 (EX. Old Name)","townKey":5},"private_offer_id":"SECRET"}'];
$pending=msa_pending([$row],[]);ok(isset($pending['111']),'pending');ok(!str_contains(msa_json($pending),'SECRET'),'source_redaction');
ok(!msa_pending([$row],['111']),'foreign_claim_excluded');
foreach(['accepted','manual','rejected'] as $s){$x=$row;$x['decision_status']=$s;ok(!msa_pending([$x],[]),'decision_preserved');}
$x=$row;$x['local_hotel_id']=5;ok(!msa_pending([$x],[]),'mapped_preserved');
$x=$row;$x['supplier_namespace']='operator_5';ok(!msa_pending([$x],[]),'namespace_distinct');
$cat=['local_country_id'=>1,'all'=>['payload'=>['TOWNTO'=>[['id'=>5,'name'=>'Hurghada','parentId'=>7]],'HOTELS'=>[['id'=>111,'name'=>'Royal Beach 12 (EX. Old Name)','townKey'=>5,'hotelUrl'=>'https://anextour.ru/hotel?hotelCode=5844']]]]];
$c=msa_catalog($cat,1,$pending,str_repeat('c',64));$r=$c['hotel_rows'][0];
ok($r['typed_town_links']['townKey']['dictionary_fields']['name']==='Hurghada','typed_town');ok($r['typed_town_links']['townKey']['dictionary_fields']['parentId']==='7','parent_kept');
ok($r['hotel_fields']['name']==='Royal Beach 12 (EX. Old Name)','qualifiers_alias_numbers_kept');ok($r['safe_to_write_now']===false,'no_acceptance');ok($r['original_http_response_sha256']===null,'no_fake_http_digest');
fails(fn()=>msa_catalog($cat,4,$pending,str_repeat('c',64)),'country_conflict');
$dup=$cat;$dup['all']['payload']['TOWNTO'][]=['id'=>5,'name'=>'Different'];$dup['all']['payload']['HOTELS'][]=['id'=>111,'name'=>'Different'];$d=msa_catalog($dup,1,$pending,str_repeat('c',64));
ok($d['duplicate_town_conflicts']===1,'duplicate_town_conflict');ok($d['hotel_rows'][0]['holds']===['saved_duplicate_hotel_conflict'],'duplicate_hotel_hold');
$offer=['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>'111','hotel'=>'Royal Beach 12','offer_ref'=>'SECRET','price'=>['amount'=>'SECRET'],'operator'=>['id'=>'5','name'=>'ANEX'],'hotel_content'=>['region'=>'Hurghada','hotel_url'=>'https://anextour.ru/hotel?HOTELLIST=5844']];
$state=['status'=>'complete','criteria'=>['STATEINC'=>3],'store'=>['version'=>1,'expires_at'=>1,'raw_ids'=>['SECRET'],'snapshot'=>['offers'=>[$offer,$offer]]]];
$s=msa_search($state,$pending,str_repeat('d',64));ok(count($s)===1,'offer_dedup');ok(!str_contains(msa_json($s),'SECRET'),'private_fields_not_exported');ok($s[0]['urls']['hotel_url']['explicit_anex_hotel_id']==='5844','retained_url');
ok(count(msa_search($state,$pending,str_repeat('d',64)))===1,'expired_identity_only');$state['status']='unavailable';ok(!msa_search($state,$pending,str_repeat('d',64)),'unknown_not_consumed');
$state['status']='complete';$state['store']['snapshot']['offers'][0]['supplier_namespace']='operator_5';array_pop($state['store']['snapshot']['offers']);ok(!msa_search($state,$pending,str_repeat('d',64)),'search_namespace');
$root=sys_get_temp_dir().'/match-saved-evidence-test-'.bin2hex(random_bytes(8));mkdir($root,0700);
try {
 $hash=msa_write($root.'/test.json',['x'=>1]);[$v,$got]=msa_read($root.'/test.json');ok($hash===$got&&$v===['x'=>1],'durable_readback');
 fails(fn()=>msa_write($root.'/test.json',['x'=>2]),'no_overwrite');
 fails(fn()=>msa_read($root.'/test.json',1),'size_bound');symlink($root.'/test.json',$root.'/link.json');fails(fn()=>msa_read($root.'/link.json'),'symlink');
 link($root.'/test.json',$root.'/hard.json');fails(fn()=>msa_read($root.'/hard.json'),'hardlink');unlink($root.'/hard.json');
 fails(fn()=>msa_read($root.'/../'.basename($root).'/test.json'),'noncanonical_path');
}finally{foreach(glob($root.'/*') as $p)unlink($p);rmdir($root);}
echo "$n saved Andromeda evidence checks PASS\n";
