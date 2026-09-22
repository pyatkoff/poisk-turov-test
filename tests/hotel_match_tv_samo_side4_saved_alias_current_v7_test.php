<?php
declare(strict_types=1);
require_once __DIR__.'/../scripts/diagnostics/hotel_match_tv_samo_side4_saved_alias_current_v7.php';
function a7(bool $ok,string $m):void{if(!$ok)throw new RuntimeException($m);}
$row=fn($id,$name,$family)=>['hotel_id'=>(string)$id,'hotel_name'=>$name,'operator_family'=>$family];
$tv=[$row(1,'SIDE YESILOZ HOTEL','funsun'),$row(2,'BLUE BEACH RESORT','anex'),$row(3,'SELENIUM HOTEL','biblio'),$row(4,'MEDITERRANEO HOTEL','intourist')];
$sa=[$row(11,'Side Yesiloz','funsun'),$row(12,'Blue Resort','anex'),$row(13,'Selenium Hotel','biblio'),$row(14,'Mediteraneo Hotel','intourist')];
$r=hma7_resolve($tv,$sa);a7($r['strong_count']===2,'strong_count');
$pairs=array_map(fn($x)=>$x['tv_hotel_id'].'|'.$x['samo_hotel_id'],$r['strong_common3']);sort($pairs);
a7($pairs===['1|11','4|14'],'strong_pairs');
a7(count(array_filter($r['strong_common3'],fn($x)=>$x['tv_hotel_id']==='2'))===0,'qualifier_hold');
a7(count(array_filter($r['strong_common3'],fn($x)=>$x['tv_hotel_id']==='3'))===0,'biblio_only_hold');
$q=hma7_name_score('ROYAL FAMILY RESORT','ROYAL RESORT');a7($q['qualifier_conflict']&&$q['score']<0.90,'meaningful_qualifier');
$source=(string)file_get_contents(__DIR__.'/../scripts/diagnostics/hotel_match_tv_samo_side4_saved_alias_current_v7.php');
a7(!str_contains($source,'hmc_tv_call(')&&!str_contains($source,'->price('),'no_provider_calls');
echo "MATCH_TV_SAMO_SIDE4_SAVED_ALIAS_CURRENT_V7_TEST_OK\n";
