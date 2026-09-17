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
$result=anytour_andromeda_search3_run_pages(['generation'=>7,'page'=>1,'params'=>['countryId'=>1]],$runner);
pagination_need(array_column($calls,'page')===[1,2,3],'sequential_pages');
pagination_need(count($result['hotels'])===2,'hotel_merge');
pagination_need(count($result['hotels'][0]['tours'])===2,'tour_merge_and_dedupe');
pagination_need(($result['hotels'][0]['andromeda_content']['image_url']??null)==='x','content_hydration');
pagination_need($result['page']===3&&$result['pages_count']===3,'complete_page_range');
pagination_need($result['status']==='complete','aggregate_uses_last_page_status');
pagination_need($result['received_offers']===3&&$result['mapped_offers']===3,'counts_sum');
pagination_need($result['external_search_pending']===false,'not_pending_after_complete_drain');
pagination_need($result['selection_enabled']===false,'selection_unchanged');

$calls=[];
$single=anytour_andromeda_search3_run_pages(['generation'=>7,'page'=>2,'params'=>[]],static function(array $request)use(&$calls):array{$calls[]=$request;return pagination_page(2,2,[]);});
pagination_need(array_column($calls,'page')===[2]&&$single['page']===2,'explicit_continuation_preserved');

$calls=[];
$detail=anytour_andromeda_search3_run_pages(['generation'=>7,'action'=>'offer_detail','page'=>1,'params'=>[]],static function(array $request)use(&$calls):array{$calls[]=$request;return ['provider'=>'andromeda','detail'=>true];});
pagination_need(count($calls)===1&&($detail['detail']??false)===true,'detail_not_paginated');

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

$failed=false;
try{
    anytour_andromeda_search3_run_pages(['generation'=>7,'params'=>[]],static function(array $request):array{
        if($request['page']===2)throw new RuntimeException('supplier_unavailable');
        return pagination_page(1,2,[]);
    });
}catch(RuntimeException $e){$failed=$e->getMessage()==='supplier_unavailable';}
pagination_need($failed,'later_page_error_propagates_fail_closed');

fwrite(STDOUT,"Andromeda Search3 page drain: {$checks} checks passed; supplier/DB/money/booking=0\n");
