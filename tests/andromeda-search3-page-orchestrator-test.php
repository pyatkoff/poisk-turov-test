<?php
declare(strict_types=1);
require_once __DIR__.'/../app/integrations/andromeda-search3-page-orchestrator.php';

$checks=0;
function need($value,string $message): void { global $checks; ++$checks; if(!$value)throw new RuntimeException($message); }
function offer(string $hex,int $price): array {
    return ['offer_ref'=>'offer_'.str_repeat($hex,64),'price'=>['amount'=>(string)$price,'currency'=>'RUB'],'offer_context'=>['page'=>1]];
}
function page(int $number,int $count,array $hotels,string $ref='search-ref',int $generation=7): array {
    return ['provider'=>'andromeda','generation'=>$generation,'hotels'=>$hotels,'date_range'=>['from'=>'2026-10-01','to'=>'2026-10-03'],
        'grouped'=>true,'first_page_only'=>false,'page'=>$number,'pages_count'=>$count,'external_search_pending'=>false,
        'search_ref'=>$ref,'status'=>'complete','received_offers'=>count($hotels),'mapped_offers'=>count($hotels),'selection_enabled'=>false];
}

$calls=[];
$runner=static function(array $request)use(&$calls):array{
    $calls[]=$request;
    return match($request['page']){
        1=>page(1,3,[['local_id'=>10,'name'=>'A','tours'=>[offer('a',100)]]]),
        2=>page(2,3,[['local_id'=>10,'name'=>'A','andromeda_content'=>['image_url'=>'x'],'tours'=>[offer('b',110),offer('a',100)]]]),
        3=>page(3,3,[['local_id'=>11,'name'=>'B','tours'=>[offer('c',120)]]]),
    };
};
$result=AnyTourAndromedaSearch3PageOrchestrator::run(['generation'=>7,'page'=>1,'params'=>['countryId'=>1]],$runner);
need(array_column($calls,'page')===[1,2,3],'sequential_pages');
need(count($result['hotels'])===2,'hotel_merge');
need(count($result['hotels'][0]['tours'])===2,'tour_merge_and_dedupe');
need(($result['hotels'][0]['andromeda_content']['image_url']??null)==='x','content_hydration');
need($result['page']===3&&$result['pages_count']===3,'complete_page_range');
need($result['received_offers']===3&&$result['mapped_offers']===3,'counts_sum');
need($result['external_search_pending']===false,'not_pending_after_complete_drain');
need($result['selection_enabled']===false,'selection_unchanged');

$calls=[];
$single=AnyTourAndromedaSearch3PageOrchestrator::run(['generation'=>7,'page'=>2,'params'=>[]],static function(array $request)use(&$calls):array{$calls[]=$request;return page(2,2,[]);});
need(array_column($calls,'page')===[2]&&$single['page']===2,'explicit_continuation_preserved');

$calls=[];
$detail=AnyTourAndromedaSearch3PageOrchestrator::run(['generation'=>7,'action'=>'offer_detail','page'=>1,'params'=>[]],static function(array $request)use(&$calls):array{$calls[]=$request;return ['provider'=>'andromeda','detail'=>true];});
need(count($calls)===1&&($detail['detail']??false)===true,'detail_not_paginated');

$failed=false;
try{
    AnyTourAndromedaSearch3PageOrchestrator::run(['generation'=>7,'params'=>[]],static function(array $request):array{
        return $request['page']===1?page(1,2,[]):page(2,2,[],'different-ref');
    });
}catch(RuntimeException $e){$failed=$e->getMessage()==='andromeda_page_context_mismatch';}
need($failed,'context_mismatch_fails_closed');

$failed=false;$calls=0;
try{
    AnyTourAndromedaSearch3PageOrchestrator::run(['generation'=>7,'params'=>[]],static function(array $request)use(&$calls):array{
        ++$calls;return page(1,1001,[]);
    });
}catch(RuntimeException $e){$failed=$e->getMessage()==='andromeda_pages_invalid';}
need($failed&&$calls===1,'page_budget_fails_before_replay');

$failed=false;
try{
    AnyTourAndromedaSearch3PageOrchestrator::run(['generation'=>7,'params'=>[]],static function(array $request):array{
        if($request['page']===2)throw new RuntimeException('supplier_unavailable');
        return page(1,2,[]);
    });
}catch(RuntimeException $e){$failed=$e->getMessage()==='supplier_unavailable';}
need($failed,'later_page_error_propagates_fail_closed');

fwrite(STDOUT,"Andromeda Search3 page orchestrator: {$checks} checks passed; supplier/DB/money/booking=0\n");
