<?php
declare(strict_types=1);
require_once __DIR__ . '/../scripts/diagnostics/hotel_match_anex_tourvisor_existing_results_details_review.php';
function tvd_t(bool $ok,string $m): void { if(!$ok) throw new RuntimeException($m); }
$detail=['name'=>'Pickalbatros White Beach Resort','region'=>'Хургада','town'=>'Хургада','latitude'=>'27.1900','longitude'=>'33.8300'];
$row=['id'=>10,'name'=>'PICKALBATROS WHITE BEACH RESORT','country'=>['id'=>1],'region'=>['name'=>'Хургада'],'subRegion'=>['name'=>'Хургада'],'latitude'=>'27.1901','longitude'=>'33.8301','tours'=>[['operator'=>['id'=>13],'id'=>'x']]];
$cat=['id'=>10,'country_id'=>1,'name'=>'PICKALBATROS WHITE BEACH RESORT','region_name'=>'Хургада','subregion_name'=>'Хургада','latitude'=>'27.1901','longitude'=>'33.8301'];
$c=hmatv_candidate($detail,$row,$cat,[],false);tvd_t(is_array($c)&&$c['strict_name']===true,'strict candidate missing');tvd_t(hmatv_safe($c,1.0),'strict close candidate must pass');
$far=$cat;$far['latitude']='35.0000';$far['longitude']='33.8301';$c=hmatv_candidate($detail,$row,$far,[],false);tvd_t(is_array($c)&&$c['blocked']==='coordinate_conflict','>5km conflict must block');
$qDetail=['name'=>'Sunrise Garden Beach','region'=>'Хургада','town'=>'Хургада','latitude'=>null,'longitude'=>null];$qRow=$row;$qRow['name']='SUNRISE GARDEN';$qCat=$cat;$qCat['name']='SUNRISE GARDEN';$qCat['latitude']=null;$qCat['longitude']=null;$c=hmatv_candidate($qDetail,$qRow,$qCat,[],false);tvd_t(is_array($c)&&$c['name']['critical_ok']===false,'BEACH qualifier loss must conflict');
$exDetail=['name'=>'Novotel Beach','region'=>'Шарм Эль Шейх','town'=>'Наама Бей','latitude'=>null,'longitude'=>null];$exRow=$row;$exRow['name']='NOVOTEL SHARM EL SHEIKH';$exRow['region']=['name'=>'Шарм Эль Шейх'];$exRow['subRegion']=['name'=>'Наама Бей'];$exCat=$cat;$exCat['name']='NOVOTEL SHARM EL SHEIKH (EX. NOVOTEL BEACH)';$exCat['region_name']='Шарм Эль Шейх';$exCat['subregion_name']='Наама Бей';$exCat['latitude']=null;$exCat['longitude']=null;$c=hmatv_candidate($exDetail,$exRow,$exCat,[],true);tvd_t(is_array($c)&&$c['strict_name']===true,'former-name exact variant missing');tvd_t(hmatv_safe($c,0.2),'former-name+place should pass');
tvd_t(hmatv_has_operator($row,13),'operator proof missing');tvd_t(!hmatv_has_operator($row,14),'wrong operator accepted');
echo "hotel-match-anex-tourvisor-existing-results-details-review-test: OK\n";
