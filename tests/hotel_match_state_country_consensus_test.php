<?php
declare(strict_types=1);
putenv('MATCH_TEST_LIBRARY=1');
require __DIR__.'/../scripts/diagnostics/hotel_match_state_country_consensus.php';
function t(bool $ok,string $m):void{if(!$ok){fwrite(STDERR,"FAIL $m\n");exit(1);}}
t(msc_norm('The Blue SPA Hotel (EX. Old)')==='blue','generic/ex normalization');
t(msc_qualifier_ok('Green Beach Hotel','Green Beach Resort'),'qualifier same');
t(!msc_qualifier_ok('Green Beach','Green Garden'),'qualifier conflict');
$hotels=[];$forms=[];$exact=[];$core=[8=>'Maldives',4=>'Turkey'];
for($i=1;$i<=6;$i++){$hotels[$i]=['id'=>$i,'country_id'=>8,'name'=>'Island '.$i,'latitude'=>null,'longitude'=>null];$forms[$i]=['Island '.$i];$exact[8]['island '.$i]=[$i];}
$hotels[20]=['id'=>20,'country_id'=>4,'name'=>'Island X','latitude'=>null,'longitude'=>null];$forms[20]=['Island X'];$exact[4]['island x']=[20];
$pending=[];for($i=1;$i<=5;$i++)$pending[]=['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>(string)(100+$i),'evidence_json'=>json_encode(['source'=>['stateKey'=>73,'name'=>'Island '.$i]])];
$pending[]=['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>'999','evidence_json'=>json_encode(['source'=>['stateKey'=>88,'name'=>'Island X']])];
$inf=msc_infer($pending,$hotels,$forms,$exact,$core);t(isset($inf['73'])&&$inf['73']['country_id']===8&&$inf['73']['winner']===5&&abs($inf['73']['share']-1.0)<0.0001,'five exact anchors infer country');t(!isset($inf['88']),'single anchor insufficient');
$u=msc_unique_exact(['Island 1'],[],$hotels,$forms,$exact,8);t(($u['ok']??false)&&($u['target']??0)===1,'country-scoped unique exact');
$hotels[30]=['id'=>30,'country_id'=>8,'name'=>'Blue Beach','latitude'=>null,'longitude'=>null];$forms[30]=['Blue Beach'];$exact[8]['blue beach']=[30];$u=msc_unique_exact(['Blue Garden'],[],$hotels,$forms,$exact,8);t(!($u['ok']??false),'meaningful qualifier mismatch not accepted');
$hotels[40]=['id'=>40,'country_id'=>8,'name'=>'Far One','latitude'=>4.0,'longitude'=>73.0];$forms[40]=['Far One'];$exact[8]['far one']=[40];$u=msc_unique_exact(['Far One'],[['latitude'=>5.0,'longitude'=>73.0]],$hotels,$forms,$exact,8);t(!($u['ok']??false),'gt5km exact blocked');
echo "hotel_match_state_country_consensus_test: OK\n";
