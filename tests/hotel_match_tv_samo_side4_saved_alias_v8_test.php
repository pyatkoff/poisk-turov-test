<?php
declare(strict_types=1);
require_once __DIR__.'/../scripts/diagnostics/hotel_match_tv_samo_side4_saved_alias_v8.php';
function v8t(bool $ok,string $m):void{if(!$ok)throw new RuntimeException($m);}
$row=fn($id,$name,$family)=>['hotel_id'=>(string)$id,'hotel_name'=>$name,'operator_family'=>$family];
$tv=[$row(1,'SIDE YESILOZ HOTEL','funsun'),$row(2,'SELENIUM HOTEL','biblio'),$row(3,'ROYAL FAMILY RESORT','anex')];
$sa=[$row(11,'Side Yesiloz','funsun'),$row(12,'Selenium Hotel','biblio'),$row(13,'Royal Resort','anex')];
$r=hma8_resolve_common4($tv,$sa);v8t($r['strong_count']===2,'common4_strong');
$pairs=array_map(fn($x)=>$x['tv_hotel_id'].'|'.$x['samo_hotel_id'],$r['strong_common4']);sort($pairs);
v8t($pairs===['1|11','2|12'],'biblio_valid_match');
v8t(count(array_filter($r['strong_common4'],fn($x)=>$x['tv_hotel_id']==='3'))===0,'qualifier_hold');
$base=hma7_baseline_pairs(['hotel_candidates'=>[['tv_hotel_id'=>'1','samo_hotel_id'=>'11','tv_name'=>'A','samo_name'=>'A','name_exact'=>true,'operator_overlap'=>['operators'=>['biblio']],'hotel_evidence_class'=>'exact']]]);
$tail=hma7_unresolved_rows([$row(1,'A','biblio'),$row(2,'B','anex'),$row(2,'B','funsun')],$base);
v8t(count(hma7_hotels($tail))===1,'baseline_partition');
$source=(string)file_get_contents(__DIR__.'/../scripts/diagnostics/hotel_match_tv_samo_side4_saved_alias_v8.php');
v8t(!str_contains($source,'hmc_tv_call(')&&!str_contains($source,'->price(')&&!str_contains($source,'v2_data_db('),'offline_only');
v8t(str_contains($source,'COMMON4_ANEX_BIBLIO_FUNSUN_INTOURIST')&&str_contains($source,"'alias_unresolved_tv_hotels'=>137"),'owner_scope');
echo "MATCH_TV_SAMO_SIDE4_SAVED_ALIAS_V8_TEST_OK\n";
