<?php
declare(strict_types=1);
require_once __DIR__ . '/../scripts/diagnostics/hotel_match_anex_tourvisor_sold_date_details_review.php';
function hmatsd_t(bool $ok,string $message): void { if(!$ok) throw new RuntimeException($message); }

$sources=[
 ['anex_hotel_id'=>10,'country_id'=>1,'probe_dates'=>['2026-09-25','2026-10-09']],
 ['anex_hotel_id'=>11,'country_id'=>1,'probe_dates'=>['2026-10-09']],
 ['anex_hotel_id'=>12,'country_id'=>4,'probe_dates'=>['2026-09-25']],
];
$slices=hmatsd_slices($sources);
hmatsd_t(count($slices)===3,'slice dedupe failed');
hmatsd_t($slices[0]['date']==='2026-09-25'&&$slices[0]['country_id']===1&&$slices[0]['source_count']===1,'first slice wrong');
hmatsd_t($slices[1]['date']==='2026-09-25'&&$slices[1]['country_id']===4,'country/date slice wrong');
hmatsd_t($slices[2]['date']==='2026-10-09'&&$slices[2]['source_count']===2,'shared sold-date slice missing');

$source=['country_id'=>1,'probe_dates'=>['2026-09-25'],'detail'=>['name'=>'Pickalbatros White Beach Resort','region'=>'Хургада','town'=>'Хургада','latitude'=>'27.1900','longitude'=>'33.8300']];
$row=['id'=>100,'name'=>'PICKALBATROS WHITE BEACH RESORT','country'=>['id'=>1],'region'=>['name'=>'Хургада'],'subRegion'=>['name'=>'Хургада'],'latitude'=>'27.1901','longitude'=>'33.8301'];
$catalog=['id'=>100,'country_id'=>1,'name'=>'PICKALBATROS WHITE BEACH RESORT','region_name'=>'Хургада','subregion_name'=>'Хургада','latitude'=>'27.1901','longitude'=>'33.8301'];
$c=hmatsd_candidate_for_slice($source,'2026-09-25',$row,$catalog,[],false);
hmatsd_t(is_array($c)&&$c['strict_name']===true&&$c['sold_date']==='2026-09-25','same sold-date strict candidate missing');
hmatsd_t(hmatv_safe($c,1.0),'same sold-date strict candidate should be safe');
hmatsd_t(hmatsd_candidate_for_slice($source,'2026-10-09',$row,$catalog,[],false)===null,'non-sold date leaked into evidence');

$far=$catalog;$far['latitude']='35.0000';
$c=hmatsd_candidate_for_slice($source,'2026-09-25',$row,$far,[],false);
hmatsd_t(is_array($c)&&$c['blocked']==='coordinate_conflict','>5km conflict not blocked');

$qSource=$source;$qSource['detail']=['name'=>'Sunrise Garden Beach','region'=>'Хургада','town'=>'Хургада','latitude'=>null,'longitude'=>null];
$qRow=$row;$qRow['name']='SUNRISE GARDEN';$qRow['latitude']=null;$qRow['longitude']=null;
$qCat=$catalog;$qCat['name']='SUNRISE GARDEN';$qCat['latitude']=null;$qCat['longitude']=null;
hmatsd_t(hmatsd_candidate_for_slice($qSource,'2026-09-25',$qRow,$qCat,[],false)===null,'BEACH qualifier loss accepted');

$exSource=['country_id'=>4,'probe_dates'=>['2026-10-09'],'detail'=>['name'=>'Novotel Beach','region'=>'Шарм Эль Шейх','town'=>'Наама Бей','latitude'=>null,'longitude'=>null]];
$exRow=['id'=>338,'name'=>'NOVOTEL SHARM EL SHEIKH','country'=>['id'=>4],'region'=>['name'=>'Шарм Эль Шейх'],'subRegion'=>['name'=>'Наама Бей'],'latitude'=>null,'longitude'=>null];
$exCat=['id'=>338,'country_id'=>4,'name'=>'NOVOTEL SHARM EL SHEIKH (EX. NOVOTEL BEACH)','region_name'=>'Шарм Эль Шейх','subregion_name'=>'Наама Бей','latitude'=>null,'longitude'=>null];
$c=hmatsd_candidate_for_slice($exSource,'2026-10-09',$exRow,$exCat,[],true);
hmatsd_t(is_array($c)&&$c['strict_name']===true,'former-name identity not preserved');
hmatsd_t(hmatv_safe($c,0.2),'former-name same-place candidate should pass');

echo "hotel-match-anex-tourvisor-sold-date-details-review-test: OK\n";
