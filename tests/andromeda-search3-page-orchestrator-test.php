<?php
declare(strict_types=1);
$runtime=$argv[1]??dirname(__DIR__);$runtime=realpath($runtime);
if(!$runtime||!is_file($runtime.'/v2/api-andromeda-search3-preview.php'))throw new RuntimeException('runtime_missing');
require_once $runtime.'/v2/api-andromeda-search3-preview.php';

$checks=0;
function pagination_need($value,string $message): void { global $checks; ++$checks; if(!$value)throw new RuntimeException($message); }
function pagination_offer(string $hex,int $price): array {
    return ['offer_ref'=>'offer_'.str_repeat($hex,64),'price'=>['amount'=>(string)$price,'currency'=>'RUB'],'offer_context'=>['page'=>1]];
}
function pagination_page(int $number,int $count,array $hotels,string $ref='search-ref',int $generation=7,string $status='complete'): array {
    return ['provider'=>'andromeda','generation'=>$generation,'hotels'=>$hotels,'date_range'=>['from'=>'2026-10-01','to'=>'2026-10-03'],
        'grouped'=>true,'first_page_only'=>false,'page'=>$number,'pages_count'=>$count,'external_search_pending'=>false,
        'search_ref'=>$ref,'status'=>$status,'received_offers'=>count($hotels),'mapped_offers'=>count($hotels),'selection_enabled'=>false];
}

$calls=[];
$runner=static function(array $request)use(&$calls):array{
    $calls[]=$request;
    return match($request['page']){
        1=>pagination_page(1,3,[['local_id'=>10,'name'=>'A','tours'=>[pagination_offer('a',100)]]],'search-ref',7,'partial'),
        2=>pagination_page(2,3,[['local_id'=>10,'name'=>'A','andromeda_content'=>['image_url'=>'x'],'tours'=>[pagination_offer('b',110),pagination_offer('a',100)]]]),
        3=>pagination_page(3,3,[['local_id'=>11,'name'=>'B','tours'=>[pagination_offer('c',120)]]]),
    };
};

// The published browser explicitly sends page=1 and rejects a response whose
// page differs. It owns subsequent requests; do not drain them inside page one.
$explicit=['generation'=>7,'page'=>1,'params'=>['countryId'=>1]];
$singleFirst=anytour_andromeda_search3_run_pages($explicit,$runner);
pagination_need($calls===[$explicit],'explicit_first_page_must_not_drain_cohort');
pagination_need($singleFirst===pagination_page(1,3,[['local_id'=>10,'name'=>'A','tours'=>[pagination_offer('a',100)]]],'search-ref',7,'partial'),'explicit_first_page_response_preserved');
pagination_need($singleFirst['page']===1&&$singleFirst['pages_count']===3,'browser_requested_page_identity');
pagination_need($singleFirst['status']==='partial'&&$singleFirst['selection_enabled']===false,'single_page_not_complete_or_authoritative');
$calls=[];$browserPages=[];
for($page=1;$page<=3;++$page){
    $request=$explicit;$request['page']=$page;
    $result=anytour_andromeda_search3_run_pages($request,$runner);
    pagination_need($result['page']===$page,'browser_page_response_mismatch');
    $browserPages[]=$result;
}
pagination_need(array_column($calls,'page')===[1,2,3],'browser_pages_requested_once_each');
$browserMerged=anytour_andromeda_search3_merge_projected_pages($browserPages);

// Private complete-cohort collector intentionally omits page; preserve its drain.
$calls=[];
$result=anytour_andromeda_search3_run_pages(['generation'=>7,'params'=>['countryId'=>1]],$runner);
pagination_need(array_column($calls,'page')===[1,2,3],'sequential_pages');
pagination_need($result===$browserMerged,'explicit_pages_and_private_drain_keep_same_offers');
pagination_need(count($result['hotels'])===2,'hotel_merge');
pagination_need(count($result['hotels'][0]['tours'])===2,'tour_merge_and_dedupe');
pagination_need(($result['hotels'][0]['andromeda_content']['image_url']??null)==='x','content_hydration');
pagination_need($result['page']===3&&$result['pages_count']===3,'complete_page_range');
pagination_need($result['status']==='complete','aggregate_uses_last_page_status');
pagination_need($result['received_offers']===3&&$result['mapped_offers']===3,'counts_sum');
pagination_need($result['external_search_pending']===false,'not_pending_after_complete_drain');
pagination_need($result['selection_enabled']===false,'selection_unchanged');

// Real SAMO EOF shape: earlier pages advertise more pages, then an empty complete
// page resets PAGES_COUNT to 0. Stop there and expose only actual data pages.
$calls=[];
$terminal=anytour_andromeda_search3_run_pages(['generation'=>7,'params'=>[]],static function(array $request)use(&$calls):array{
    $calls[]=$request;
    return match($request['page']){
        1=>pagination_page(1,5,[['local_id'=>20,'name'=>'C','tours'=>[pagination_offer('d',130)]]],'terminal-ref',7,'partial'),
        2=>pagination_page(2,5,[['local_id'=>21,'name'=>'D','tours'=>[pagination_offer('e',140)]]],'terminal-ref',7,'partial'),
        3=>pagination_page(3,0,[],'terminal-ref',7,'complete'),
        default=>throw new RuntimeException('unexpected_supplier_page'),
    };
});
pagination_need(array_column($calls,'page')===[1,2,3],'terminal_empty_stops_future_pages');
pagination_need($terminal['page']===2&&$terminal['pages_count']===2&&$terminal['status']==='complete','terminal_empty_actual_range');
pagination_need(count($terminal['hotels'])===2&&$terminal['received_offers']===2,'terminal_empty_keeps_data_pages');

$failed=false;
try{
    anytour_andromeda_search3_run_pages(['generation'=>7,'params'=>[]],static function(array $request):array{
        return $request['page']===1
            ? pagination_page(1,3,[],'bad-zero-ref',7,'partial')
            : pagination_page(2,0,[['local_id'=>22,'name'=>'E','tours'=>[pagination_offer('f',150)]]],'bad-zero-ref',7,'complete');
    });
}catch(RuntimeException $e){$failed=$e->getMessage()==='andromeda_pages_invalid';}
pagination_need($failed,'nonempty_zero_pages_count_rejected');

$calls=[];
$single=anytour_andromeda_search3_run_pages(['generation'=>7,'page'=>2,'params'=>[]],static function(array $request)use(&$calls):array{$calls[]=$request;return pagination_page(2,2,[]);});
pagination_need(array_column($calls,'page')===[2]&&$single['page']===2,'explicit_continuation_preserved');

$calls=[];
$detail=anytour_andromeda_search3_run_pages(['generation'=>7,'action'=>'offer_detail','page'=>1,'params'=>[]],static function(array $request)use(&$calls):array{$calls[]=$request;return ['provider'=>'andromeda','detail'=>true];});
pagination_need(count($calls)===1&&($detail['detail']??false)===true,'detail_not_paginated');

$calls=[];
$scoped=['generation'=>7,'page'=>1,'params'=>[],'hotel_scope'=>['local_id'=>10]];
$singleScoped=anytour_andromeda_search3_run_pages($scoped,static function(array $request)use(&$calls):array{$calls[]=$request;return pagination_page(1,9,[]);});
pagination_need($calls===[$scoped]&&$singleScoped['pages_count']===9,'hotel_scope_not_drained');

$calls=[];
$empty=anytour_andromeda_search3_run_pages(['generation'=>7,'page'=>1,'params'=>[]],static function(array $request)use(&$calls):array{$calls[]=$request;return pagination_page(1,0,[]);});
pagination_need(count($calls)===1&&$empty['page']===1&&$empty['pages_count']===0,'explicit_empty_page_preserved');

// Explicit invalid page values remain the single-page runner's validation duty;
// presence must not silently normalize null/zero/string to page one and drain.
foreach([null,0,'1',false] as $invalidPage){
    $failed=false;$calls=[];
    try{anytour_andromeda_search3_run_pages(['generation'=>7,'page'=>$invalidPage,'params'=>[]],static function(array $request)use(&$calls,$invalidPage):array{
        $calls[]=$request;pagination_need(array_key_exists('page',$request)&&$request['page']===$invalidPage,'invalid_page_was_rewritten');
        throw new InvalidArgumentException('invalid_page');
    });}catch(InvalidArgumentException $e){$failed=$e->getMessage()==='invalid_page';}
    pagination_need($failed&&count($calls)===1,'invalid_explicit_page_not_drained');
}

$failed=false;
try{
    anytour_andromeda_search3_run_pages(['generation'=>7,'params'=>[]],static function(array $request):array{
        return $request['page']===1?pagination_page(1,2,[]):pagination_page(2,2,[],'different-ref');
    });
}catch(RuntimeException $e){$failed=$e->getMessage()==='andromeda_page_context_mismatch';}
pagination_need($failed,'context_mismatch_fails_closed');

$failed=false;$calls=0;
try{
    anytour_andromeda_search3_run_pages(['generation'=>7,'params'=>[]],static function(array $request)use(&$calls):array{
        ++$calls;return pagination_page(1,1001,[]);
    });
}catch(RuntimeException $e){$failed=$e->getMessage()==='andromeda_pages_invalid';}
pagination_need($failed&&$calls===1,'page_budget_fails_before_replay');

$calls=[];
$partial=anytour_andromeda_search3_run_pages(['generation'=>7,'params'=>[]],static function(array $request)use(&$calls):array{
    $calls[]=$request;
    if($request['page']===2)throw new RuntimeException('supplier_unavailable');
    return pagination_page(1,2,[['local_id'=>30,'name'=>'Retained','tours'=>[pagination_offer('9',190)]]],'partial-ref',7,'partial');
});
pagination_need(array_column($calls,'page')===[1,2],'late_supplier_outage_attempts_next_page_once');
pagination_need($partial['status']==='partial'&&$partial['page']===1&&$partial['pages_count']===2,'late_supplier_outage_reports_partial_range');
pagination_need($partial['external_search_pending']===true&&count($partial['hotels'])===1&&count($partial['hotels'][0]['tours'])===1,'late_supplier_outage_keeps_validated_rows');

$failed=false;
try{
    anytour_andromeda_search3_run_pages(['generation'=>7,'params'=>[]],static function(array $request):array{
        throw new RuntimeException('supplier_unavailable');
    });
}catch(RuntimeException $e){$failed=$e->getMessage()==='supplier_unavailable';}
pagination_need($failed,'first_page_supplier_outage_still_fails');

$failed=false;
try{
    anytour_andromeda_search3_run_pages(['generation'=>7,'params'=>[]],static function(array $request):array{
        if($request['page']===2)throw new RuntimeException('projection_bug');
        return pagination_page(1,2,[]);
    });
}catch(RuntimeException $e){$failed=$e->getMessage()==='projection_bug';}
pagination_need($failed,'non_supplier_late_error_still_fails_closed');

fwrite(STDOUT,"Andromeda Search3 page drain: {$checks} checks passed; supplier/DB/money/booking=0\n");
