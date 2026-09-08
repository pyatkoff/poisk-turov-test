<?php
declare(strict_types=1);
define('ANYTOUR_ANEX_PAIRED_LIBRARY_ONLY',true);
define('ANYTOUR_ANEX_SEGMENT_LIBRARY_ONLY',true);
require_once __DIR__.'/../scripts/diagnostics/anex_search3_paired_runner.php';
require_once __DIR__.'/../scripts/diagnostics/anex_search3_segment_runner.php';
function segment_check(bool $ok,string $label): void {
    if (!$ok) throw new RuntimeException('FAIL: '.$label);
}
function segment_rejected(callable $call,string $code): void {
    try { $call(); throw new LogicException('Unexpected acceptance'); }
    catch (RuntimeException $e) { segment_check($e->getMessage()===$code,$code); }
}
$input=['experiment_id'=>'one_day_anex_segments_20260908','case_id'=>'tv_alanya','date'=>'2026-09-16','nights'=>7,'adults'=>2,'currency'=>'RUB'];
segment_check(anex_segment_input($input)===$input,'fixed input accepted');
foreach (['case_id'=>'tv_day','date'=>'2026-09-17','nights'=>'7','adults'=>3,'currency'=>'USD','experiment_id'=>'anything'] as $key=>$value) {
    segment_rejected(static function () use ($input,$key,$value) { anex_segment_input(array_replace($input,[$key=>$value])); },'SEGMENT_INVALID_INPUT');
}
segment_rejected(static function () use ($input) { anex_segment_input($input+['region_id'=>100]); },'SEGMENT_INVALID_INPUT');
$region=anex_segment_region([['id'=>123,'name'=>' Alanya ','countryId'=>4],['id'=>124,'name'=>'Side']],4);
segment_check($region['id']===123,'Alanya ID comes from scoped dictionary');
segment_check(anex_segment_region([['id'=>124,'name'=>'Аланья']],4)['id']===124,'exact Russian resort alias');
foreach ([[['id'=>123,'name'=>'Alanya Coast']], [['id'=>123,'name'=>'Alanya','countryId'=>5]],
    [['id'=>123,'name'=>'Alanya'],['id'=>125,'name'=>'Алания']]] as $rows) {
    segment_rejected(static function () use ($rows) { anex_segment_region($rows,4); },'SEGMENT_TV_REGION_NOT_UNIQUE');
}
$operator=['id'=>90,'name'=>'ANEX TOUR']; $local=['departure_id'=>1,'country_id'=>4];
foreach (['tv_alanya','tv_5star','tv_alanya_5star'] as $case) {
    $criteria=anex_segment_criteria(array_replace($input,['case_id'=>$case]),$local,$operator,$region);
    segment_check($criteria['operatorIds']===[90] && $criteria['dateFrom']===$criteria['dateTo'],'operator and exact day are in initial supplier query');
    segment_check(isset($criteria['regionIds'])===($case!=='tv_5star'),'only requested region filter');
    segment_check(isset($criteria['hotelCategory'])===($case!=='tv_alanya'),'only requested category filter');
    if ($case!=='tv_alanya') segment_check($criteria['hotelCategory']==='5','existing exact five-star form value');
}
segment_rejected(static function () use ($input,$local,$operator) { anex_segment_criteria($input,$local,$operator,null); },'SEGMENT_REGION_REQUIRED');
$tour=['date'=>'16.09.2026','nights'=>7,'price'=>'120000','operator'=>['id'=>90,'name'=>'ANEX TOUR']];
$groups=[['id'=>7,'name'=>'Hotel A','category'=>5,'country'=>['id'=>4],'region'=>['id'=>123,'name'=>'Alanya'],'tours'=>[$tour]],
    ['id'=>8,'name'=>'Hotel B','category'=>4,'country'=>['id'=>4],'region'=>['id'=>124,'name'=>'Side'],'tours'=>[]],
    ['id'=>9,'name'=>'https://secret.invalid','country'=>['id'=>4],'tours'=>[]]];
$raw=anex_segment_raw_response($groups,[]);
segment_check($raw['hotel_ids']===[7,8,9] && $raw['hotel_count']===3 && $raw['tour_count']===1,'raw hotels retained before offer projection including zero tours');
segment_check($raw['hotels'][2]['name']===null,'unsafe raw label removed');
$criteria=anex_segment_criteria(array_replace($input,['case_id'=>'tv_alanya_5star']),$local,$operator,$region);
$verification=anex_segment_filter_verification($raw,$criteria);
segment_check($verification['response_respects_requested_filters']===false && $verification['region']['mismatched_ids']===[8]
    && $verification['category']['mismatched_ids']===[8],'supplier ignoring filters is explicit');
segment_check($verification['region']['missing_ids']===[9] && !$verification['local_post_filter_applied'],'missing facets are unknown, never silently discarded');
$summary=[]; $offers=anex_paired_tv_offers($groups,$criteria,$operator,[],$summary);
segment_check(count($offers)===1 && count($raw['hotel_ids'])===3,'returned hotel coverage remains independent of valid offers');
$verified=anex_segment_filter_verification(anex_segment_raw_response([$groups[0]],[]),$criteria);
segment_check($verified['response_respects_requested_filters']===true,'matching response facets verified');
$empty=anex_segment_filter_verification(anex_segment_raw_response([],[]),$criteria);
segment_check($empty['response_respects_requested_filters']===null,'empty response cannot prove filters honored');
echo "ANEX segment runner smoke: OK\n";
