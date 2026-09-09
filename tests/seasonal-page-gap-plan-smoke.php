<?php
declare(strict_types=1);
require_once __DIR__.'/../v2/data/seasonal-page-gap-plan-v1.php';
function gap_check(bool $condition,string $label):void{if(!$condition)throw new RuntimeException($label);}
function gap_record(int $country,int $region,int $month,string $status='approved'):array{
 $period=sprintf('2026-%02d',$month);$path='/country/test'.($country===4?'turkey':'maldives').'/'.($region>0?'resort/':'').($month===11?'november':'october').'/';
 return ['type'=>'seasonal','status'=>$status,'path'=>$path,'publication_allowed'=>true,'route_launch_allowed'=>true,'data'=>['search_state'=>['country'=>$country]+($region>0?['region'=>$region]:[]),'seasonal_identity'=>['departure_id'=>1,'country_id'=>$country,'region_id'=>$region?:null,'year'=>2026,'month'=>$month,'page_key'=>($region>0?'resort_month:1:':'month:1:').$country.($region>0?':'.$region:'').':'.$period]]];
}
$kemer=gap_record(4,22,11);$kemer['path']='/country/turkey/kemer/november/';
$october=gap_record(4,21,10);$october['path']='/country/turkey/belek/october/';
$maldives=gap_record(8,0,11);$maldives['path']='/country/maldives/november/';
$records=[$kemer,$october,$maldives];$paths=array_column($records,'path');$routes=[['departure_id'=>1,'country_id'=>4],['departure_id'=>1,'country_id'=>8]];
$targets=v2_seasonal_page_gap_plan($records,$routes,[],$paths,'2026-09-09');
gap_check(count($targets)===15,'all weekly windows including month tails');
$kemerTargets=array_values(array_filter($targets,static fn($t)=>$t['region_id']===22));
gap_check($kemerTargets[0]['date_from']==='2026-11-01'&&end($kemerTargets)['date_to']==='2026-11-30','whole November');
gap_check($targets[0]['page_path']===$october['path']&&$targets[1]['page_path']===$kemer['path'],'nearest deficit then NovemberKemer');
foreach($targets as $target){$from=new DateTimeImmutable($target['date_from']);$to=new DateTimeImmutable($target['date_to']);gap_check($from->diff($to)->days<=6&&$from->format('Y-m')===$to->format('Y-m'),'bounded exact window');}
$key=$kemer['data']['seasonal_identity']['page_key'];
$filled=v2_seasonal_page_gap_plan($records,$routes,[$key],$paths,'2026-09-09');
gap_check(count($filled)===10&&!in_array($key,array_column($filled,'page_key'),true),'fresh page is not searched again');
$review=$kemer;$review['status']='review';gap_check(v2_seasonal_page_gap_plan([$review],$routes,[],$paths,'2026-09-09')===[],'review not published');
$blocked=$kemer;$blocked['publication_allowed']=false;gap_check(v2_seasonal_page_gap_plan([$blocked],$routes,[],$paths,'2026-09-09')===[],'publication denied');
gap_check(v2_seasonal_page_gap_plan([$kemer],$routes,[],[],'2026-09-09')===[],'wrapper must exist');
gap_check(v2_seasonal_page_gap_plan([$kemer],[['departure_id'=>2,'country_id'=>4]],[],$paths,'2026-09-09')===[],'cannot substitute departure');
$bad=$kemer;$bad['data']['search_state']['region']=23;gap_check(v2_seasonal_page_gap_plan([$bad],$routes,[],$paths,'2026-09-09')===[],'scope mismatch rejected');
$partial=v2_seasonal_page_gap_plan([$kemer],$routes,[],$paths,'2026-11-29');gap_check(count($partial)===1&&$partial[0]['date_from']==='2026-11-30','only future days');
gap_check(v2_seasonal_page_gap_plan([$kemer],$routes,[],$paths,'2026-12-01')===[],'no past months');
$hotel=['criterion'=>'hotel_batch','target_key'=>'monthly:untouched','month'=>'2026-11','departure_id'=>1,'country_id'=>4,'region_id'=>null,'hotel_ids'=>[101,102],'date_from'=>'2026-11-01','date_to'=>'2026-11-07'];
$pass=v2_seasonal_gap_pass_targets(array_fill(0,64,$hotel),$targets,64);
gap_check(count($pass)===64&&count(array_filter($pass,static fn($t)=>isset($t['page_key'])))===8,'shared64 budget with8 gap reserve');
gap_check(v2_seasonal_gap_pass_targets([$hotel],$targets,1)===[ $hotel ],'small budget never starves TOP500');
$only=v2_seasonal_gap_pass_targets([$hotel],$targets,64,8,'2026-11',4);
foreach($only as$t)gap_check($t['month']==='2026-11'&&$t['country_id']===4,'manual filters before reserve');
$params=v2_top500_target_search_params($kemerTargets[0]);gap_check($params['regionIds']===[22]&&!isset($params['hotelIds']),'region filter in initial supplier request');
gap_check(v2_top500_target_search_params($hotel)['hotelIds']===[101,102],'unchanged TOP500batch');
gap_check(v2_top500_target_accepts_hotel($kemerTargets[0],['id'=>1234,'country'=>['id'=>4],'region'=>['id'=>22]]),'right resort');
foreach([['id'=>1234,'country'=>4,'region'=>23],['id'=>1234,'country'=>1,'region'=>22],['id'=>1234,'country'=>4],['country'=>4,'region'=>22]]as$h)gap_check(!v2_top500_target_accepts_hotel($kemerTargets[0],$h),'wrong or unknown identity rejected');
gap_check(v2_top500_target_accepts_hotel($hotel,['id'=>101])&&!v2_top500_target_accepts_hotel($hotel,['id'=>103]),'TOP500 whitelist retained');
$hotel['hotel_ids']=[];try{v2_top500_target_search_params($hotel);throw new LogicException('empty hotel batch broadened');}catch(InvalidArgumentException $e){}
echo "SEASONAL_PAGE_GAP_PLAN_OK exact_scope=1 published_only=1 shared_budget=64 reserve=8 november=1 no_id_substitution=1\n";
if(in_array('--registry',$argv,true)){
    require_once __DIR__.'/../v2/seo-core-month-content-v1.php';
    require_once __DIR__.'/../v2/data/top500-monthly-plan-v1.php';
    $records=v2_seo_core_month_content_records(gmmktime(12,0,0,9,9,2026));
    $paths=[];$fresh=[];
    foreach($records as$r){if(is_file(__DIR__.'/../v2'.$r['path'].'index.php'))$paths[]=$r['path'];if($r['path']!=='/country/turkey/kemer/november/')$fresh[]=$r['data']['seasonal_identity']['page_key'];}
    $gap=v2_seasonal_page_gap_plan($records,[['departure_id'=>1,'country_id'=>4]],$fresh,$paths,'2026-09-09');
    gap_check(count($gap)===5&&$gap[0]['page_path']==='/country/turkey/kemer/november/','real approved core record and existing wrapper');
    $now=200000;
    $queue=v2_top500_monthly_queue($gap,[['id'=>123,'target_key'=>$gap[0]['target_key'],'status'=>'empty','started_epoch'=>$now-20,'finished_epoch'=>$now-1,'search_id'=>999]],$now);
    gap_check(count($queue['targets'])===4&&$queue['targets'][0]['date_from']==='2026-11-08','empty first week advances to unsearched weeks, not no-tours conclusion');
    $queue=v2_top500_monthly_queue($gap,[['id'=>123,'target_key'=>$gap[0]['target_key'],'status'=>'timeout','started_epoch'=>$now-20,'finished_epoch'=>$now-1,'search_id'=>999]],$now);
    gap_check($queue['targets'][0]['resume_search_id']===999,'gap uses existing known-search resumption');
    echo "SEASONAL_PAGE_GAP_REGISTRY_OK kemer_november=1 real_wrapper=1 empty_week_progress=1 known_search_resume=1\n";
}
