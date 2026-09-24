<?php
declare(strict_types=1);
require_once __DIR__.'/../app/integrations/anex-initial-week-gate.php';
function ck(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);}
function req(int $generation,string $from,string $to,array $extra=[]):array{
    return ['generation'=>$generation,'params'=>array_replace([
        'dateFrom'=>$from,'dateTo'=>$to,'departureId'=>'1','countryId'=>'4',
        'nightsFrom'=>7,'nightsTo'=>7,'adults'=>2,'childs'=>[],
    ],$extra)];
}
$session=[];
$first=anytour_anex_initial_week_gate($session,req(101,'2026-10-01','2026-10-07'));
ck($first['action']==='allow','first window allowed');
$retry=anytour_anex_initial_week_gate($session,req(101,'2026-10-01','2026-10-07'));
ck($retry['action']==='allow','exact first window retry allowed');
$later=anytour_anex_initial_week_gate($session,req(101,'2026-10-08','2026-10-14'));
ck($later['action']==='skip','same generation later window skipped');
$skipped=anytour_anex_initial_week_skipped($later);
ck($skipped['provider']==='anex'&&$skipped['hotels']===[]&&$skipped['pages_read']===0
    &&$skipped['date_range']===['from'=>'2026-10-08','to'=>'2026-10-14']
    &&$skipped['initial_week_only']===true,'safe skipped response');
$new=anytour_anex_initial_week_gate($session,req(102,'2026-10-08','2026-10-14'));
ck($new['action']==='allow','new generation allowed');
$changed=anytour_anex_initial_week_gate($session,req(102,'2026-10-08','2026-10-14',['adults'=>3]));
ck($changed['action']==='skip','same generation changed scope cannot spend supplier quota');
$bad=false;try{anytour_anex_initial_week_gate($session,req(103,'2026-10-01','2026-10-08'));}
catch(InvalidArgumentException $e){$bad=$e->getMessage()==='ANEX_INITIAL_WEEK_GATE_RANGE';}
ck($bad,'over seven day direct request fails closed');
echo "ANEX_INITIAL_WEEK_GATE_OK first=1 retry=1 later_skipped=1 new_generation=1 scope_guard=1 range_guard=1 supplier=0 db=0\n";
