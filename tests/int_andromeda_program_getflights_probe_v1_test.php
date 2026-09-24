<?php
declare(strict_types=1);

putenv('INT_PROGRAM_PROBE_LIBRARY_ONLY=1');
require_once dirname(__DIR__).'/scripts/diagnostics/int_andromeda_program_getflights_probe_v1.php';

function ipft_need(bool $ok,string $label):void{if(!$ok)throw new RuntimeException('IPF_TEST:'.$label);}
function ipft_row(string $ref,int $price,bool $mapped=true):array{
    return ['mapped'=>$mapped,'price_units'=>$price,'offer'=>['offer_ref'=>$ref]];
}
function ipft_throws(callable $fn,string $reason,string $label):void{
    try{$fn();}catch(Throwable $e){ipft_need($e->getMessage()===$reason,$label.':reason');return;}
    throw new RuntimeException('IPF_TEST:'.$label.':not_thrown');
}

$spo=[
    '501'=>ipft_row('offer_'.str_repeat('a',64),200,true),
    '502'=>ipft_row('offer_'.str_repeat('b',64),100,true),
];
$hotels=[
    '10'=>ipft_row('offer_'.str_repeat('c',64),50,true),
    '11'=>ipft_row('offer_'.str_repeat('d',64),40,true),
];
$strict=['true'=>0,'false'=>4,'null'=>0];

$kept=ipf_choose_representatives($spo,$hotels,'intourist',$strict);
ipft_need($kept['basis']==='distinct_spo','spo_precedence');
ipft_need(count($kept['rows'])===2,'spo_count');
ipft_need($kept['rows'][0]['price_units']===100,'spo_sort');

$fallback=ipf_choose_representatives([],$hotels,'intourist',$strict);
ipft_need($fallback['basis']==='distinct_mapped_hotel','fallback_basis');
ipft_need(count($fallback['rows'])===2,'fallback_count');
ipft_need($fallback['rows'][0]['price_units']===40,'fallback_sort');

ipft_throws(
    static fn()=>ipf_choose_representatives([],$hotels,'funsun',$strict),
    'target_group_missing','funsun_no_fallback'
);
ipft_throws(
    static fn()=>ipf_choose_representatives([],$hotels,'intourist',['true'=>1,'false'=>3,'null'=>0]),
    'charter_group_not_strict','gds_mixed_rejected'
);
ipft_throws(
    static fn()=>ipf_choose_representatives([],['10'=>$hotels['10']],'intourist',['true'=>0,'false'=>2,'null'=>0]),
    'charter_independent_samples_missing','two_hotels_required'
);

echo "INT_PROGRAM_FUEL_PROBE_SELECTION_OK\n";
