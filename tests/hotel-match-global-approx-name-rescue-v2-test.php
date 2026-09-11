<?php
declare(strict_types=1);
define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/../scripts/diagnostics/hotel_match_global_approx_name_rescue_v2.php';
function hmgan2_assert(bool $ok,string $message): void { if(!$ok) throw new RuntimeException($message); }

hmgan2_assert(count(hmgan2_allowlist())===41,'v1 artifact allowlist must stay immutable at 41');
$hotels=[];
for($i=1;$i<=6;$i++)$hotels[$i]=['country_id'=>4,'region_name'=>'Стамбул','subregion_name'=>'Фатих','name'=>($i<=5?'ISTANBUL ':'').['ALPHA','BETA','GAMMA','DELTA','OMEGA','SIGMA'][$i-1].' HOTEL'];
$locality=hmgan2_locality_common($hotels);
$key=hmgan2_locality_key($hotels[1]);
hmgan2_assert(isset($locality['common'][$key]['istanbul']),'frequent Istanbul token must be locality-common even when locality label is Стамбул');
$target=['country_id'=>4,'region_name'=>'Стамбул','subregion_name'=>'Фатих','name'=>'DREAM SUITE ISTANBUL'];
$weakPair=hmgan_pair('My Dream Istanbul Hotel','DREAM SUITE ISTANBUL',[],[]);
$weak=hmgan2_identity_pairs($weakPair,$target,$locality);
hmgan2_assert($weak['locality_common_aligned']===1,'Istanbul aligned token must be removed as locality evidence');
hmgan2_assert($weak['identity_aligned']===1,'Dream+Istanbul must leave only one identity anchor');

$antalya=[];for($i=1;$i<=6;$i++)$antalya[$i]=['country_id'=>4,'region_name'=>'Анталья','subregion_name'=>'','name'=>'ANTALYA SAMPLE '.$i];
$locality2=hmgan2_locality_common($antalya);
$target2=['country_id'=>4,'region_name'=>'Анталья','subregion_name'=>'','name'=>'RAMADA PLAZA BY WINDHAM ANTALYA'];
$strongPair=hmgan_pair('Ramada Plaza by Wyndham Antalya','RAMADA PLAZA BY WINDHAM ANTALYA',[],[]);
$strong=hmgan2_identity_pairs($strongPair,$target2,$locality2);
hmgan2_assert($strong['identity_aligned']>=3,'brand/name anchors must survive locality filtering');
hmgan2_assert(!in_array('antalya',array_column($strong['identity'],'target'),true),'Antalya must not count as identity when locality-common');

$phu=[];for($i=1;$i<=8;$i++)$phu[$i]=['country_id'=>9,'region_name'=>'Фукуок','subregion_name'=>'','name'=>'PHU QUOC SAMPLE '.$i];
$locality3=hmgan2_locality_common($phu);
$target3=['country_id'=>9,'region_name'=>'Фукуок','subregion_name'=>'','name'=>'SAILING LUXURY PHU QUOC'];
$sailing=hmgan2_identity_pairs(hmgan_pair('Sailing Hotel Phu Quoc','SAILING LUXURY PHU QUOC',[],[]),$target3,$locality3);
hmgan2_assert($sailing['identity_aligned']===1,'Phu Quoc geography must not turn single Sailing anchor into auto identity');
hmgan2_assert($sailing['locality_common_aligned']===2,'Phu and Quoc should both be locality-common');

fwrite(STDOUT,"hotel-match-global-approx-name-rescue-v2-test: ok\n");
