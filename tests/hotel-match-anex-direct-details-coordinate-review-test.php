<?php
declare(strict_types=1);
require_once __DIR__ . '/../scripts/diagnostics/hotel_match_anex_direct_details_coordinate_review.php';
function hmaddcr_t(bool $ok,string $message): void { if(!$ok) throw new RuntimeException($message); }

$target=['id'=>10,'country_id'=>1,'name'=>'PICKALBATROS WHITE BEACH RESORT','region_name'=>'Хургада','subregion_name'=>'Хургада','latitude'=>'27.1901','longitude'=>'33.8301'];
$detail=['name'=>'Pickalbatros White Beach Resort','region'=>'Хургада','town'=>'Хургада','latitude'=>'27.1900','longitude'=>'33.8300'];
$c=hmaddcr_candidate($detail,$target,[$target['name']],false,['pickalbatros'=>1,'white'=>1,'beach'=>1],1);
hmaddcr_t(is_array($c)&&$c['route']==='exact_tokens_direct_coordinate','exact coordinate identity missing');
hmaddcr_t($c['distance_m']<30,'exact coordinate distance unexpected');

$twoDetail=['name'=>'Sunny Days Mirette Family Aqua Park Resort','region'=>'Хургада','town'=>'Хургада','latitude'=>'27.1900','longitude'=>'33.8300'];
$twoTarget=$target;$twoTarget['name']='SUNNY DAYS MIRETTE FAMILY AQUAPARK';
$c=hmaddcr_candidate($twoDetail,$twoTarget,[$twoTarget['name']],false,['sunny'=>2,'days'=>3,'mirette'=>1,'family'=>5],2);
hmaddcr_t(is_array($c)&&in_array($c['route'],['exact_tokens_direct_coordinate','two_token_direct_coordinate'],true),'two-token close identity missing');

$oneDetail=['name'=>'Mirette Hotel','region'=>'Хургада','town'=>'Хургада','latitude'=>'27.1900','longitude'=>'33.8300'];
$oneTarget=$target;$oneTarget['name']='MIRETTE RESORT';
$c=hmaddcr_candidate($oneDetail,$oneTarget,[$oneTarget['name']],true,['mirette'=>4],2);
hmaddcr_t(is_array($c)&&$c['route']==='exact_tokens_direct_coordinate','generic HOTEL/RESORT should not break exact significant token identity with bridge');

$uniqueDetail=['name'=>'Rareword Hotel','region'=>'Хургада','town'=>'Хургада','latitude'=>'27.1900','longitude'=>'33.8300'];
$uniqueTarget=$target;$uniqueTarget['name']='RAREWORD RESORT';
$c=hmaddcr_candidate($uniqueDetail,$uniqueTarget,[$uniqueTarget['name']],false,['rareword'=>1],1);
hmaddcr_t(is_array($c)&&$c['country_unique_shared']===true,'country-unique one-token coordinate identity missing');
$c=hmaddcr_candidate($uniqueDetail,$uniqueTarget,[$uniqueTarget['name']],false,['rareword'=>1],2);
hmaddcr_t($c===null,'one-token co-located cluster must block');

$qDetail=['name'=>'Sunrise Garden Beach','region'=>'Хургада','town'=>'Хургада','latitude'=>'27.1900','longitude'=>'33.8300'];
$qTarget=$target;$qTarget['name']='SUNRISE GARDEN';
hmaddcr_t(hmaddcr_candidate($qDetail,$qTarget,[$qTarget['name']],true,['sunrise'=>1,'garden'=>1],1)===null,'BEACH qualifier loss accepted');

$annexDetail=['name'=>'Coral Annex','region'=>'Хургада','town'=>'Хургада','latitude'=>'27.1900','longitude'=>'33.8300'];
$annexTarget=$target;$annexTarget['name']='CORAL';
hmaddcr_t(hmaddcr_candidate($annexDetail,$annexTarget,[$annexTarget['name']],true,['coral'=>1],1)===null,'ANNEX qualifier loss accepted');

$far=$target;$far['latitude']='35.0000';
$c=hmaddcr_candidate($detail,$far,[$far['name']],true,['pickalbatros'=>1,'white'=>1,'beach'=>1],1);
hmaddcr_t(is_array($c)&&$c['blocked']==='coordinate_conflict'&&$c['distance_m']>HMADDCR_COORDINATE_CONFLICT_BLOCK_M,'>5km coordinate conflict not blocked');

$formerDetail=['name'=>'Novotel Beach','region'=>'Шарм Эль Шейх','town'=>'Наама Бей','latitude'=>'27.9100','longitude'=>'34.3300'];
$formerTarget=['id'=>20,'country_id'=>4,'name'=>'NOVOTEL SHARM EL SHEIKH (EX. NOVOTEL BEACH)','region_name'=>'Шарм Эль Шейх','subregion_name'=>'Наама Бей','latitude'=>'27.9101','longitude'=>'34.3301'];
$c=hmaddcr_candidate($formerDetail,$formerTarget,hmaddcr_target_names($formerTarget['name'],[]),true,['novotel'=>1,'beach'=>3],1);
hmaddcr_t(is_array($c)&&$c['exact_tokens']===true,'former-name variant identity missing');

echo "hotel-match-anex-direct-details-coordinate-review-test: OK\n";
