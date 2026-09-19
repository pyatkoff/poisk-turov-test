<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/scripts/ops/andromeda_original_transport_retained_v2.php';
$checks=0;
$ok=static function(bool $v,string $label)use(&$checks):void{++$checks;if(!$v)throw new RuntimeException('FAIL '.$label);};
$ref=str_repeat('a',64);$offerRef='offer_'.str_repeat('b',64);
$item=['ref'=>$ref,'created'=>'1000','page'=>2,'offer'=>$offerRef];
$record=['context'=>['search_ref'=>$ref,'generation'=>17,'page'=>2,'offer_ref'=>$offerRef]];
$criteria=['PAGE'=>1,'CHECKIN_BEG'=>'20260919','CHECKIN_END'=>'20260922','ADULT'=>2,'CHILD'=>0,'NIGHTS_FROM'=>7,'NIGHTS_TILL'=>10,'STATEINC'=>5];
$offer=['offer_ref'=>$offerRef,'operator_ref'=>'115','local_hotel_id'=>123,'check_in'=>'2026-09-19','nights'=>7,'adults'=>2,'children'=>0];
$first=['search_ref'=>$ref,'generation'=>17,'status'=>'complete','criteria'=>$criteria,
    'store'=>['search_ref'=>$ref,'generation'=>17,'created_at'=>1000,'expires_at'=>1900,'criteria'=>$criteria,'snapshot'=>['page'=>1,'offers'=>[]]]];
$page=$first;$page['criteria']['PAGE']=2;$page['store']['criteria']['PAGE']=2;
$page['store']['snapshot']=['page'=>2,'offers'=>[$offer]];$page['store']['created_at']=1009;
$result=anytour_original_retained_bind_v2($item,$record,$first,$page);
$ok($result['offer']===$offer,'exact offer retained');
$ok($result['cohort_created_at']===1000&&$result['page_created_at']===1009,'cohort vs page clocks');
$ok($result['v1_time_equality_would_reject']===true,'old false rejection reproduced');
$equal=$page;$equal['store']['created_at']=1000;
$ok(!anytour_original_retained_bind_v2($item,$record,$first,$equal)['v1_time_equality_would_reject'],'equal clocks valid');
$one=$first;$one['store']['snapshot']['offers']=[$offer];$oneItem=$item;$oneItem['page']=1;$oneRecord=$record;$oneRecord['context']['page']=1;
$ok(anytour_original_retained_bind_v2($oneItem,$oneRecord,$one,$one)['offer']===$offer,'page one valid');
$permuted=$page;$permuted['criteria']=array_reverse($page['criteria'],true);$permuted['store']['criteria']=array_reverse($page['store']['criteria'],true);
$ok(anytour_original_retained_bind_v2($item,$record,$first,$permuted)['offer']===$offer,'key order immaterial');
$fail=static function(array $i,array $r,array $f,array $p,string $reason,string $label)use($ok):void{
    try{anytour_original_retained_bind_v2($i,$r,$f,$p);}catch(RuntimeException $e){$ok($e->getMessage()===$reason,$label);return;}
    $ok(false,$label);
};
$r=$record;$r['context']['generation']='17';$fail($item,$r,$first,$page,'PACKAGE_FILENAME_BINDING','typed generation');
foreach(['search_ref'=>'wrong','page'=>1,'offer_ref'=>'other'] as $k=>$v){$r=$record;$r['context'][$k]=$v;$fail($item,$r,$first,$page,'PACKAGE_FILENAME_BINDING','package '.$k);}
foreach(['search_ref'=>'wrong','generation'=>18,'status'=>'unavailable'] as $k=>$v){
    $p=$page;$p[$k]=$v;$fail($item,$record,$first,$p,'PAGE_IDENTITY_BINDING','page '.$k);
    $f=$first;$f[$k]=$v;$fail($item,$record,$f,$page,'PAGE_IDENTITY_BINDING','first '.$k);
}
foreach(['search_ref'=>'wrong','generation'=>18] as $k=>$v){$p=$page;$p['store'][$k]=$v;$fail($item,$record,$first,$p,'PAGE_IDENTITY_BINDING','store '.$k);}
$p=$page;$p['store']['snapshot']['page']=3;$fail($item,$record,$first,$p,'PAGE_IDENTITY_BINDING','wrong snapshot page');
$p=$page;$p['criteria']['PAGE']=3;$fail($item,$record,$first,$p,'PAGE_IDENTITY_BINDING','wrong request page');
$p=$page;$p['store']['criteria']['PAGE']=3;$fail($item,$record,$first,$p,'PAGE_IDENTITY_BINDING','wrong store page');
$f=$first;$f['store']['created_at']=1001;$fail($item,$record,$f,$page,'FIRST_COHORT_BINDING','replaced first page');
$f=$first;$f['store']['created_at']='1000';$fail($item,$record,$f,$page,'FIRST_COHORT_BINDING','typed first clock');
$p=$page;$p['store']['created_at']=999;$fail($item,$record,$first,$p,'PAGE_TIME_ORDER','page before cohort');
$p=$page;$p['store']['created_at']='1009';$fail($item,$record,$first,$p,'PAGE_TIME_ORDER','typed page clock');
$p=$page;$p['criteria']['ADULT']=3;$fail($item,$record,$first,$p,'PAGE_CRITERIA_BINDING','internal criteria mismatch');
foreach(['ADULT'=>3,'CHECKIN_BEG'=>'20260920','NIGHTS_FROM'=>8,'STATEINC'=>3]as$k=>$v){
    $p=$page;$p['criteria'][$k]=$v;$p['store']['criteria'][$k]=$v;$fail($item,$record,$first,$p,'COHORT_CRITERIA_BINDING','other criteria '.$k);
}
$p=$page;$p['store']['snapshot']['offers']=[];$fail($item,$record,$first,$p,'PRICE_OFFER_BINDING','missing exact offer');
$p=$page;$p['store']['snapshot']['offers']=[$offer,$offer];$fail($item,$record,$first,$p,'PRICE_OFFER_BINDING','duplicate exact offer');
$r=$record;$r['context']['operator_ref']='342';$fail($item,$r,$first,$page,'OPERATOR_BINDING','other operator');
$r=$record;$r['context']['local_hotel_id']=124;$fail($item,$r,$first,$page,'HOTEL_BINDING','other hotel');
$r=$record;$r['context']['local_hotel_id']=123;$r['context']['operator_ref']='115';
$ok(anytour_original_retained_bind_v2($item,$r,$first,$page)['offer']===$offer,'full matching identity');
$source=file_get_contents(dirname(__DIR__).'/scripts/ops/andromeda_original_transport_retained_v2.php');
$ok(!preg_match('/(?:getFlights|changeService|curl_exec|fsockopen|file_put_contents|v2_data_db)\s*\(/',$source),'no supplier or mutation call');
echo 'ORIGINAL_RETAINED_V2_BINDING_OK checks='.$checks.PHP_EOL;
