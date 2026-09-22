<?php
declare(strict_types=1);
require_once __DIR__.'/../scripts/diagnostics/hotel_match_tv_samo_side4_token_alias_v11.php';
$same=hmt11_score(str_repeat('LONG ',80).'SIDE YESILOZ HOTEL',str_repeat('LONG ',80).'Side Yesiloz Hotel');
if($same['score']!==1.0||!$same['exact']||$same['qualifier_conflict'])throw new RuntimeException('long_exact');
$conflict=hmt11_score('SUN HOTEL BEACH','SUN HOTEL');
if(!$conflict['qualifier_conflict']||$conflict['score']>0.79)throw new RuntimeException('qualifier');
$tv=[['hotel_id'=>'1','hotel_name'=>'SIDE STAR HOTEL','operator_family'=>'funsun'],['hotel_id'=>'2','hotel_name'=>'SIDE STAR BEACH','operator_family'=>'funsun']];
$sa=[['hotel_id'=>'11','hotel_name'=>'Side Star Hotel','operator_family'=>'funsun'],['hotel_id'=>'22','hotel_name'=>'Side Star Beach','operator_family'=>'funsun']];
$r=hmt11_resolve($tv,$sa);
if($r['strong_count']!==2||$r['review_count']!==0)throw new RuntimeException('resolve');
echo "ok\n";
