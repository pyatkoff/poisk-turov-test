<?php
declare(strict_types=1);
require_once __DIR__.'/../v2/data/top500-monthly-plan-v1.php';
function monthly_check(bool $ok, string $label): void { if (!$ok) throw new RuntimeException($label); }
$ids=range(1,65); $hotels=[];
foreach ($ids as $id) $hotels[]=['id'=>$id,'country_id'=>$id<=45?4:8,'is_active'=>1];
$departures=[['departure_id'=>1,'country_id'=>4],['departure_id'=>3,'country_id'=>8]];
foreach (['2026-09-09','2026-12-31','2027-01-31','2028-02-01'] as $today) {
    $plan=v2_top500_monthly_plan($ids,$hotels,$departures,$today);
    monthly_check(count($plan['months'])===12,'all twelve months');
    monthly_check($plan['hotel_count']===65,'all searchable hotels');
    $seen=[]; $keys=[];
    foreach ($plan['targets'] as $target) {
        monthly_check(!isset($keys[$target['target_key']]),'unique stable key'); $keys[$target['target_key']]=true;
        $from=new DateTimeImmutable($target['date_from']); $to=new DateTimeImmutable($target['date_to']);
        monthly_check($from<=$to && $from->diff($to)->days<=6,'weekly bounds');
        monthly_check($from->format('Y-m')===$target['month'] && $to->format('Y-m')===$target['month'],'no cross-month leakage');
        monthly_check(count($target['hotel_ids'])<=30,'supplier batch cap');
        monthly_check($target['departure_id']===($target['country_id']===4?1:3),'route-safe departure');
        foreach ($target['hotel_ids'] as $id) for($d=$from;$d<=$to;$d=$d->modify('+1 day')) {
            $k=$id.':'.$d->format('Y-m-d'); monthly_check(!isset($seen[$k]),'no overlapping dates'); $seen[$k]=true;
        }
    }
    $begin=(new DateTimeImmutable($today))->modify('+1 day'); $end=new DateTimeImmutable($plan['date_to']);
    foreach ($ids as $id) for($d=$begin;$d<=$end;$d=$d->modify('+1 day')) monthly_check(isset($seen[$id.':'.$d->format('Y-m-d')]),'every hotel on every future day including month tail');
}
$plan=v2_top500_monthly_plan($ids,$hotels,$departures,'2026-09-09');
monthly_check(in_array('2026-11',array_column(array_slice($plan['targets'],0,12),'month'),true),'November not starved');
$now=200000; $targets=array_slice($plan['targets'],0,6); $attempts=[];
foreach ([['success',$now-1,1],['empty',$now-1,2],['failure',$now-1000,3],['timeout',$now-5,4],['started',0,5]] as $i=>[$status,$finished,$id]) $attempts[]=['id'=>$id,'target_key'=>$targets[$i]['target_key'],'status'=>$status,'started_epoch'=>$now-2000,'finished_epoch'=>$finished,'search_id'=>$status==='started'?0:100+$id];
$q=v2_top500_monthly_queue($targets,$attempts,$now);
monthly_check($q['fresh']===2 && $q['pending']===4 && $q['deferred']===1,'honest states');
monthly_check(in_array(104,array_column($q['targets'],'resume_search_id'),true),'timeout resumes known search');
monthly_check(in_array(103,array_column($q['targets'],'resume_search_id'),true),'failed known search resumes after cooldown');
// A retained provider search may disappear before our six-hour resume window.
// Only the exact LOCAL client formatter is terminal; never infer it from a body.
$goneTarget=$targets[2];
$goneAttempt=['id'=>42,'target_key'=>$goneTarget['target_key'],'status'=>'failure',
    'started_epoch'=>$now-2000,'finished_epoch'=>$now-901,'search_id'=>14162884988];
foreach(range(1,4) as $httpAttempt){
    $a=$goneAttempt+['error_text'=>'Tourvisor HTTP 410 after '.$httpAttempt.' attempt(s)'];$saved=$a;
    $gone=v2_top500_monthly_queue([$goneTarget],[$a],$now);
    monthly_check($gone['fresh']===0&&$gone['pending']===1&&$gone['deferred']===0&&count($gone['targets'])===1,'HTTP410 stays due, not successful');
    $next=$gone['targets'][0];
    monthly_check($next['target_key']===$goneTarget['target_key']&&$next['resume_search_id']===null&&$next['resume_attempt_id']===null,'HTTP410 receives a new attempt on the next pass');
    monthly_check($a===$saved&&$a['status']==='failure'&&$a['search_id']===14162884988&&$a['id']===42,'HTTP410 original failure and search identity retained');
}
$a=$goneAttempt+['error_text'=>'Tourvisor HTTP 410 after 1 attempt(s)'];$a['finished_epoch']=$now-899;$saved=$a;
$cooldown=v2_top500_monthly_queue([$goneTarget],[$a],$now);
monthly_check($cooldown['targets']===[]&&$cooldown['fresh']===0&&$cooldown['pending']===1&&$cooldown['deferred']===1&&$a===$saved,'HTTP410 keeps the failure cooldown');
$a['finished_epoch']=$now-900;
$cooled=v2_top500_monthly_queue([$goneTarget],[$a],$now);
monthly_check(count($cooled['targets'])===1&&$cooled['targets'][0]['resume_search_id']===null,'HTTP410 cooldown boundary creates no retained resume');
foreach([
    'Tourvisor HTTP 500 after 1 attempt(s)',
    'Tourvisor HTTP 429 after 4 attempt(s)',
    'Tourvisor HTTP 503 after 4 attempt(s)',
    'Tourvisor connection error after 4 attempts: operation timed out',
    'response body mentions 410',
    'prefix Tourvisor HTTP 410 after 1 attempt(s)',
    'Tourvisor HTTP 410 after 1 attempt(s) response body',
    'Tourvisor HTTP 410 after 0 attempt(s)',
    'Tourvisor HTTP 410 after 5 attempt(s)',
    'Tourvisor HTTP 410 after 01 attempt(s)',
    'Tourvisor HTTP 410 after 1 attempts',
    "Tourvisor HTTP 410 after 1 attempt(s)\n",
    " Tourvisor HTTP 410 after 1 attempt(s)",
    410,null,
] as $message){
    $a=$goneAttempt+['error_text'=>$message];$saved=$a;
    $retry=v2_top500_monthly_queue([$goneTarget],[$a],$now);
    monthly_check($retry['targets'][0]['resume_search_id']===14162884988&&$retry['targets'][0]['resume_attempt_id']===42&&$a===$saved,'noncanonical/other failure retains known search');
}
foreach(['timeout','started'] as $status){
    $a=$goneAttempt+['error_text'=>'partial results saved; resume existing search'];$a['status']=$status;$saved=$a;
    $retry=v2_top500_monthly_queue([$goneTarget],[$a],$now);
    monthly_check($retry['targets'][0]['resume_search_id']===14162884988&&$retry['targets'][0]['resume_attempt_id']===42&&$a===$saved,'ordinary incomplete search still resumes');
}
$expiry=v2_top500_monthly_queue($targets,$attempts,$now+90000);
monthly_check($expiry['fresh']===0 && count($expiry['targets'])===6,'completed pairs become due again');
$changed=$hotels; $changed[0]['is_active']=0;
$degraded=v2_top500_monthly_plan($ids,$changed,$departures,'2026-09-09');
monthly_check($degraded['missing_catalog_count']===1 && $degraded['hotel_count']===64,'missing hotel explicit');
monthly_check($degraded['targets'][0]['target_key']!==$plan['targets'][0]['target_key'],'batch composition invalidates prior coverage');
$stable=v2_top500_monthly_plan($ids,$hotels,$departures,'2026-09-10');
$nov=static fn($p)=>array_column(array_values(array_filter($p['targets'],static fn($t)=>$t['month']==='2026-11')),'target_key');
monthly_check($nov($stable)===$nov($plan),'future keys stable across daily runs');
foreach (['2026-02-30','garbage'] as $invalid) { try {v2_top500_monthly_plan($ids,$hotels,$departures,$invalid); throw new LogicException('invalid date allowed');} catch(InvalidArgumentException $e){} }
// Production has many more than two countries. The former nesting exhausted
// a 64-target worker on September/October despite passing the small fixture.
$manyHotels=[]; $manyDepartures=[];
foreach(range(1,40) as $country){
    $manyHotels[]=['id'=>$country,'country_id'=>$country,'is_active'=>1];
    $manyDepartures[]=['departure_id'=>1,'country_id'=>$country];
}
$many=v2_top500_monthly_plan(range(1,40),$manyHotels,$manyDepartures,'2026-09-09');
$initial=array_slice($many['targets'],0,64);
monthly_check(count(array_unique(array_column($initial,'month')))===12,'all months in first bounded many-country pass');
monthly_check(count(array_filter($initial,static fn($t)=>$t['country_id']===4&&$t['month']==='2026-11'))>0,'Turkey November not starved by country count');
$prior=[];
foreach($initial as $i=>$target)if(in_array($target['month'],['2026-09','2026-10'],true))$prior[]=['id'=>$i+1,'target_key'=>$target['target_key'],'status'=>'success','started_epoch'=>$now-10,'finished_epoch'=>$now-1,'search_id'=>1000+$i];
$continued=v2_top500_monthly_queue($many['targets'],$prior,$now);
monthly_check(count(array_filter(array_slice($continued['targets'],0,64),static fn($t)=>$t['country_id']===4&&$t['month']==='2026-11'))>0,'prior successes retained while November advances');
require_once __DIR__.'/top500-monthly-execution-smoke.php';
echo "TOP500_MONTHLY_PLAN_OK full_calendar=1 leap_tail=1 resume=1 http410_terminal=1 no_starvation=1 many_countries=40 explicit_missing=1\n";
