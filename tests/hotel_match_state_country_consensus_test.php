<?php
declare(strict_types=1);
putenv('MATCH_STATE_CONSENSUS_TEST_LIBRARY=1');
require __DIR__.'/../scripts/diagnostics/hotel_match_state_country_consensus.php';
function t(bool $ok,string $m):void{if(!$ok){fwrite(STDERR,"FAIL $m\n");exit(1);}}
$core=[1=>'Египет',4=>'Турция',8=>'Мальдивы',9=>'ОАЭ',10=>'Куба',12=>'Шри-Ланка',16=>'Вьетнам',2=>'Таиланд'];
$a=msc_country_aliases($core);
t(msc_explicit_countries(['Four Seasons Resort Maldives at Kuda Huraa'],$a)===[8],'maldives marker');
t(msc_explicit_countries(['Hotel in Istanbul'],$a)===[],'city is not country marker');
t(msc_explicit_countries(['Turkey Maldives'],$a)===[4,8],'multi-country marker detected');
$pending=[];for($i=1;$i<=7;$i++)$pending[]=['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>(string)$i,'evidence_json'=>json_encode(['source'=>['stateKey'=>73,'name'=>'Island '.$i.' Maldives']])];
$pending[]=['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>'9','evidence_json'=>json_encode(['source'=>['stateKey'=>88,'name'=>'One Turkey']])];
$inf=msc_marker_consensus($pending,$core);t(isset($inf['73'])&&$inf['73']['country_id']===8&&$inf['73']['winner']===7&&abs($inf['73']['share']-1.0)<0.0001,'seven marker votes infer Maldives');t(!isset($inf['88']),'one marker vote insufficient');
$pending2=$pending;$pending2[]=['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>'10','evidence_json'=>json_encode(['source'=>['stateKey'=>73,'name'=>'Turkey Maldives']])];$inf2=msc_marker_consensus($pending2,$core);t(!isset($inf2['73']),'multi-country row blocks consensus');
t(mcr_norm('The Blue SPA Hotel (EX. Old)')==='blue','shared generic/ex normalization');t(!mcr_qualifier_ok('Green Beach','Green Garden'),'shared qualifier guard');
$hotels=[1=>['id'=>1,'country_id'=>8,'name'=>'Green Beach Resort','region_name'=>null,'subregion_name'=>null,'latitude'=>4.0,'longitude'=>73.0]];$forms=[1=>['Green Beach Resort']];$exact=[8=>['green beach'=>[1]]];$tokens=[8=>['green'=>[1=>true],'beach'=>[1=>true]]];$r=mcr_select_candidate(['Green Beach Hotel'],8,[['latitude'=>4.0,'longitude'=>73.0]],$hotels,$forms,$exact,$tokens,[]);t(($r['route']??'')==='auto_accept_candidate'&&($r['target']??0)===1,'shared exact resolver');$r=mcr_select_candidate(['Green Garden'],8,[],$hotels,$forms,$exact,$tokens,[]);t(($r['route']??'')!=='auto_accept_candidate','shared qualifier mismatch held');$r=mcr_select_candidate(['Green Beach'],8,[['latitude'=>5.0,'longitude'=>73.0]],$hotels,$forms,$exact,$tokens,[]);t(($r['route']??'')==='hard_conflict','shared gt5km veto');
echo "hotel_match_state_country_consensus_test: OK\n";
