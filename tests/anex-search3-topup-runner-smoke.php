<?php
declare(strict_types=1);

define('ANYTOUR_ANEX_PAIRED_LIBRARY_ONLY',true);
define('ANYTOUR_ANEX_TOPUP_LIBRARY_ONLY',true);
require_once __DIR__.'/../scripts/diagnostics/anex_search3_paired_runner.php';
require_once __DIR__.'/../scripts/diagnostics/anex_search3_topup_runner.php';

$checks=0;
function topup_check(bool $condition,string $message): void
{
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}
function topup_throws(callable $run,string $code): void
{
    try { $run(); } catch (RuntimeException $error) {
        topup_check($error->getMessage()===$code,'Unexpected validation error'); return;
    }
    throw new RuntimeException('Expected validation rejection');
}

$input=['experiment_id'=>'catalog_hotel_topup_20260909',
    'preview_source_sha'=>'2545982789ada8db8c4be18be0c1159f624633bc','hotel_ids'=>[445,9365,56094]];
topup_check(anex_topup_input($input)===$input,'Fixed input accepted');
foreach ([['hotel_ids'=>[445,9365,56094,21477]],['hotel_ids'=>['445',9365,56094]],
    ['hotel_ids'=>[9365,445,56094]],['hotel_ids'=>[445,445,56094]],['preview_source_sha'=>str_repeat('a',40)],
    ['experiment_id'=>'another'],['operatorIds'=>[1]]] as $change) {
    topup_throws(static function () use ($input,$change): void { anex_topup_input(array_replace($input,$change)); },'TOPUP_INVALID_INPUT');
}
$criteria=anex_topup_criteria($input,['departure_id'=>'1','country_id'=>'1']);
topup_check($criteria['hotelIds']===[445,9365,56094] && $criteria['meal']==='7'
    && $criteria['dateFrom']==='2026-09-18' && $criteria['dateTo']==='2026-09-18'
    && $criteria['nightsFrom']===8 && $criteria['nightsTo']===8 && $criteria['adults']===2 && $criteria['childs']===[]
    && $criteria['onlyCharter']===false && $criteria['onlyDirect']===false && !isset($criteria['operatorIds']),
    'Initial supplier criteria preserve one exact day, party and all operators');
topup_throws(static function () use ($input): void { anex_topup_criteria($input,['departure_id'=>1,'country_id'=>2]); },'TOPUP_LOCAL_IDENTITY_MISMATCH');

$catalog=[]; $mappings=[];
foreach ([445,9365,56094] as $id) {
    $catalog[]=['id'=>(string)$id,'name'=>'Hotel '.$id,'country_id'=>'1','is_active'=>'1'];
    $mappings[]=['anex_hotel_id'=>$id+1,'catalog_hotel_id'=>$id];
}
$resolve=static function (string $provider,int $external): ?int { return $provider==='anex_online' ? $external-1 : null; };
topup_check(array_column(anex_topup_catalog($catalog,$mappings,$resolve,[]),'hotel_id')===[445,9365,56094],
    'Active catalogue identity and effective mapping verified');
foreach (['inactive','wrong_country','missing_name','unmapped','duplicate'] as $case) {
    $bad=$catalog; $candidate=$mappings;
    if ($case==='inactive') $bad[0]['is_active']=0;
    elseif ($case==='wrong_country') $bad[0]['country_id']=2;
    elseif ($case==='missing_name') $bad[0]['name']='';
    elseif ($case==='unmapped') array_shift($candidate);
    else $bad[1]=$bad[0];
    topup_throws(static function () use ($bad,$candidate,$resolve): void { anex_topup_catalog($bad,$candidate,$resolve,[]); },'TOPUP_CATALOG_IDENTITY_REQUIRED');
}
topup_throws(static function () use ($catalog,$mappings): void {
    anex_topup_catalog($catalog,$mappings,static function (): ?int { return null; },[]);
},'TOPUP_CATALOG_IDENTITY_REQUIRED');

$tour=['date'=>'18.09.2026','nights'=>8,'price'=>'150000.50','adults'=>2,'children'=>0,
    'meal'=>'All Inclusive','roomType'=>'Standard','operator'=>['name'=>'Интурист'],
    'tourId'=>'private-booking-claim','bookingUrl'=>'https://booking.invalid/private'];
$group=['id'=>445,'name'=>'<b>Hotel 445</b>','country'=>['id'=>1],'tours'=>[$tour]];
$result=anex_topup_response([$group],[]);
topup_check($result['filter_verification']['response_respects_hotel_ids']===true
    && $result['filter_verification']['missing_requested_hotel_ids']===[9365,56094]
    && $result['raw_response']['hotels'][0]['minimum_price_rub']==='150000.50'
    && $result['summary']['valid_tours']===1,'Valid subset retains exact minimum and absent requested IDs');
topup_check($result['raw_response']['hotels'][0]['samples'][0]['operator']==='Интурист'
    && strpos(json_encode($result),'private-booking-claim')===false
    && strpos(json_encode($result),'booking.invalid')===false,'All operators allowed; booking identifiers and URLs absent');

$unexpected=$group; $unexpected['id']=21477;
$unknown=$group; unset($unknown['id']);
$raw=anex_topup_response([$group,$unexpected,$unknown],[]);
topup_check($raw['raw_response']['hotel_count']===3 && $raw['raw_response']['hotel_ids']===[445,21477]
    && $raw['filter_verification']['response_respects_hotel_ids']===false
    && $raw['filter_verification']['unexpected_hotel_ids']===[21477]
    && $raw['filter_verification']['missing_identity_rows']===1
    && $raw['raw_response']['hotels'][1]['accepted_tour_count']===1
    && $raw['raw_response']['hotels'][2]['rejections']['hotel_identity']===1,
    'Supplier hotelIds mismatch stays in raw evidence, never silently postfiltered');
topup_check(anex_topup_response([],[])['filter_verification']['response_respects_hotel_ids']===null
    && anex_topup_response([$unknown],[])['filter_verification']['response_respects_hotel_ids']===null,
    'Empty and identity-less responses cannot verify supplier filtering');

$wrong=$group; $wrong['tours']=[];
foreach ([['date'=>'2026-09-19'],['nights'=>7],['adults'=>3],['children'=>1],['childs'=>[5]],
    ['adults'=>null],['nights'=>[]],['price'=>'-1'],['currency'=>'EUR']] as $change) {
    $wrong['tours'][]=array_replace($tour,$change);
}
$wrong['tours'][]=$tour;
$tested=anex_topup_response([$wrong],[])['raw_response']['hotels'][0];
topup_check($tested['raw_tour_count']===10 && $tested['accepted_tour_count']===1
    && $tested['rejected_tour_count']===9 && $tested['minimum_price_rub']==='150000.50'
    && $tested['rejections']['date_or_nights']===3 && $tested['rejections']['party']===4
    && $tested['rejections']['price_or_currency']===2,'Wrong date, nights, party and currency excluded from comparable minima');
$wrongCountry=$group; $wrongCountry['countryId']=2;
$tested=anex_topup_response([$wrongCountry],[]);
topup_check($tested['raw_response']['hotels'][0]['minimum_price_rub']===null
    && $tested['filter_verification']['country_mismatch_hotel_ids']===[445],'Explicit wrong country preserved and excluded from minima');

$unsafe=$group; $unsafe['name']='Bearer private-secret-value';
$unsafe['tours']=[array_replace($tour,['roomType'=>'private-secret-value','meal'=>'https%3A%2F%2Fexample.test',
    'operator'=>['name'=>'<script>https://example.test</script>']])];
$safe=anex_topup_response([$unsafe],['private-secret-value']);
topup_check($safe['raw_response']['hotels'][0]['name']===null
    && $safe['raw_response']['hotels'][0]['meal_unverified_samples'][0]['room']===null
    && $safe['raw_response']['hotels'][0]['meal_unverified_samples'][0]['meal']===null
    && $safe['raw_response']['hotels'][0]['meal_unverified_samples'][0]['operator']===null,
    'Secrets and encoded links cannot enter sanitized evidence');
$meals=$group; $meals['tours']=[];
foreach (['AI','UAI','Всё включено','УЛЬТРА ВСЕ ВКЛЮЧЕНО','All-Inclusive','HB','Полный пансион','Unknown',null] as $meal) {
    $meals['tours'][]=array_replace($tour,['meal'=>$meal,'price'=>100000+count($meals['tours'])]);
}
$mealResult=anex_topup_response([$meals],[]); $mealHotel=$mealResult['raw_response']['hotels'][0];
topup_check($mealHotel['accepted_tour_count']===5 && $mealHotel['rejected_tour_count']===4
    && $mealHotel['meal_filter_counts']===['matched'=>5,'mismatched'=>2,'unverified'=>2]
    && $mealResult['filter_verification']['meal']['response_respects_meal']===false
    && count($mealHotel['meal_mismatched_samples'])===2 && count($mealHotel['meal_unverified_samples'])===2,
    'Known and unknown meal mismatches retain evidence while only verified AI contributes to minima');
$cheaperWrong=$group; $cheaperWrong['tours']=[array_replace($tour,['meal'=>'HB','price'=>1]),$tour];
topup_check(anex_topup_response([$cheaperWrong],[])['raw_response']['hotels'][0]['minimum_price_rub']==='150000.50',
    'Cheaper non-AI price does not lower the requested AI minimum');
$many=$group; $many['tours']=[];
foreach ([500000,400000,300000,200000,100000] as $price) $many['tours'][]=array_replace($tour,['price'=>$price]);
$bounded=anex_topup_response([$many],[])['raw_response']['hotels'][0];
topup_check($bounded['accepted_tour_count']===5 && $bounded['minimum_price_rub']==='100000'
    && array_column($bounded['samples'],'price')===['100000','200000','300000'],
    'All received tours contribute to minimum while samples stay bounded');
topup_throws(static function () use ($group): void { anex_topup_response(['445'=>$group],[]); },'TOPUP_TV_RESULTS_SHAPE');
topup_throws(static function (): void { anex_topup_response([['id'=>445,'tours'=>'bad']],[]); },'TOPUP_TV_RESULTS_SHAPE');

echo 'anex search3 topup runner: '.$checks." checks passed\n";
