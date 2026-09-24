<?php
declare(strict_types=1);
require_once __DIR__.'/../app/integrations/anex-local-offer-collector.php';

function rangeCheck(bool $ok,string $label):void
{
    if(!$ok)throw new RuntimeException('ANEX_RANGE_TEST:'.$label);
}
function rangeThrows(callable $fn,string $message,string $label):void
{
    try{$fn();}catch(Throwable $error){
        rangeCheck($error->getMessage()===$message,$label.':message');
        return;
    }
    throw new RuntimeException('ANEX_RANGE_TEST:'.$label.':not-thrown');
}

rangeCheck(AnyTourAnexLocalOfferCollectorV1::dateWindows('2026-09-29','2026-09-29')===[
    ['from'=>'2026-09-29','to'=>'2026-09-29'],
],'one-day');
$windows7=AnyTourAnexLocalOfferCollectorV1::dateWindows('2026-09-29','2026-10-05');
rangeCheck(count($windows7)===7,'seven-day-count');
rangeCheck($windows7[0]===['from'=>'2026-09-29','to'=>'2026-09-29'],'seven-day-first');
rangeCheck($windows7[6]===['from'=>'2026-10-05','to'=>'2026-10-05'],'seven-day-last');
$windows8=AnyTourAnexLocalOfferCollectorV1::dateWindows('2026-09-29','2026-10-06');
rangeCheck(count($windows8)===8,'eight-day-count');
rangeCheck($windows8[7]===['from'=>'2026-10-06','to'=>'2026-10-06'],'eight-day-last');
$windows21=AnyTourAnexLocalOfferCollectorV1::dateWindows('2026-10-30','2026-11-19');
rangeCheck(count($windows21)===21,'twenty-one-day-count');
rangeCheck($windows21[0]===['from'=>'2026-10-30','to'=>'2026-10-30'],'twenty-one-day-first');
rangeCheck($windows21[20]===['from'=>'2026-11-19','to'=>'2026-11-19'],'twenty-one-day-last');
$leap=AnyTourAnexLocalOfferCollectorV1::dateWindows('2028-02-25','2028-03-16');
rangeCheck(count($leap)===21,'leap-rollover-count');
rangeCheck($leap[4]===['from'=>'2028-02-29','to'=>'2028-02-29'],'leap-day');
rangeCheck($leap[20]===['from'=>'2028-03-16','to'=>'2028-03-16'],'leap-rollover-last');

foreach([
    ['2026-02-30','2026-03-01','invalid-date'],
    ['2026-10-02','2026-10-01','reverse'],
    ['2026-10-01','2026-10-22','over-21'],
] as [$from,$to,$label]){
    rangeThrows(
        static fn()=>AnyTourAnexLocalOfferCollectorV1::dateWindows($from,$to),
        'ANEX_LOCAL_COLLECTOR_DATE_RANGE',$label
    );
}

$baseRequest=['action'=>'search','generation'=>73,'params'=>[
    'departureId'=>'1','countryId'=>'4','dateFrom'=>'2026-10-30','dateTo'=>'2026-11-19',
]];
$seen=[];
$success=AnyTourAnexLocalOfferCollectorV1::collectRange(
    $baseRequest,'2026-10-30','2026-11-19',
    static function(array $request,int $index,array $window)use(&$seen):array{
        $seen[]=['index'=>$index,'window'=>$window,'from'=>$request['params']['dateFrom'],'to'=>$request['params']['dateTo']];
        return ['status'=>'complete','window_marker'=>$index];
    }
);
rangeCheck($success['status']==='complete'&&$success['window_count']===21&&$success['windows_completed']===21,'success-counts');
rangeCheck(count($success['windows'])===21&&count($seen)===21,'success-calls');
rangeCheck($seen[0]===['index'=>0,'window'=>['from'=>'2026-10-30','to'=>'2026-10-30'],'from'=>'2026-10-30','to'=>'2026-10-30'],'first-window-request');
rangeCheck($seen[20]===['index'=>20,'window'=>['from'=>'2026-11-19','to'=>'2026-11-19'],'from'=>'2026-11-19','to'=>'2026-11-19'],'last-window-request');
rangeCheck($baseRequest['params']['dateFrom']==='2026-10-30'&&$baseRequest['params']['dateTo']==='2026-11-19','base-request-unchanged');
rangeCheck($success['selection_authority']===false,'no-selection-authority');

$budgetSeen=[];
$budgeted=AnyTourAnexLocalOfferCollectorV1::collectRange(
    $baseRequest,'2026-10-30','2026-11-19',
    static function(array $request,int $index,array $window)use(&$budgetSeen):array{
        $budgetSeen[]=$window['from'];
        return ['status'=>'complete'];
    },
    static function(int $index,array $window,int $completed,int $attempted):bool{
        rangeCheck($completed===$attempted,'budget-completed-attempted');
        return $index<2;
    }
);
rangeCheck($budgeted['status']==='incomplete','budget-status');
rangeCheck($budgeted['window_count']===21&&$budgeted['windows_attempted']===2&&$budgeted['windows_completed']===2,'budget-counts');
rangeCheck($budgetSeen===['2026-10-30','2026-10-31'],'budget-attempts');
rangeCheck($budgeted['continuation_date']==='2026-11-01'&&$budgeted['stop_reason']==='bounded_runtime','budget-continuation');
rangeCheck(count($budgeted['windows'])===2&&$budgeted['selection_authority']===false,'budget-receipts');

$failedCalls=[];
$failed=AnyTourAnexLocalOfferCollectorV1::collectRange(
    $baseRequest,'2026-10-30','2026-11-19',
    static function(array $request,int $index,array $window)use(&$failedCalls):array{
        $failedCalls[]=$index;
        if($index===1)return ['status'=>'incomplete','autosave_failure'=>['phase'=>'finalize','receipt'=>['published'=>false]]];
        return ['status'=>'complete'];
    }
);
rangeCheck($failed['status']==='incomplete'&&$failed['window_count']===21&&$failed['windows_completed']===1,'fail-stop-counts');
rangeCheck($failedCalls===[0,1]&&count($failed['windows'])===2,'fail-stop-after-incomplete-day');
rangeCheck($failed['windows'][1]['result']['autosave_failure']['phase']==='finalize','failure-receipt-preserved');

rangeThrows(
    static fn()=>AnyTourAnexLocalOfferCollectorV1::collectRange(
        $baseRequest,'2026-10-30','2026-10-30',static fn():array=>['unexpected'=>true]
    ),
    'ANEX_LOCAL_COLLECTOR_RANGE_RESULT','malformed-callback'
);
rangeThrows(
    static fn()=>AnyTourAnexLocalOfferCollectorV1::collectRange(
        ['action'=>'other','generation'=>73,'params'=>[]],'2026-10-30','2026-10-30',static fn():array=>['status'=>'complete']
    ),
    'ANEX_LOCAL_COLLECTOR_INPUT','invalid-request'
);

echo "ANEX_LOCAL_OFFER_RANGE_OK windows21=21 bounded_runtime=1 fail_stop=1 invalid=4 supplier=0 db=0\n";
