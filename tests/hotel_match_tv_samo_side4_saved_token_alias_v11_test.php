<?php
declare(strict_types=1);
require_once __DIR__.'/../scripts/diagnostics/hotel_match_tv_samo_side4_saved_token_alias_v11.php';
function t11(bool $ok,string $m):void{if(!$ok)throw new RuntimeException($m);}
$row=fn($id,$name,$family)=>['hotel_id'=>(string)$id,'hotel_name'=>$name,'operator_family'=>$family];

$x=hmt11_token_score('ROYAL GARDEN BEACH HOTEL','Royal Garden Beach');
t11($x['exact']===true&&$x['score']===1.0&&$x['shared_tokens']===3,'token_exact');
$q=hmt11_token_score('ROYAL FAMILY RESORT','ROYAL RESORT');
t11($q['qualifier_conflict']===true&&$q['score']<0.84,'qualifier_hold');
$typo=hmt11_token_score('MEDITERRANEO HOTEL','MEDITERANEO HOTEL');
t11($typo['exact']===false&&$typo['score']<1.0,'no_edit_distance_magic');

$tv=[
    $row(1,'SIDE PALACE BEACH HOTEL','biblio'),
    $row(2,'BLUE GARDEN RESORT','anex'),
    $row(3,'ROYAL FAMILY RESORT','funsun'),
    $row(4,'SIDE','intourist'),
];
$sa=[
    $row(11,'Side Palace Beach','biblio'),
    $row(12,'Blue Garden','anex'),
    $row(13,'Royal Resort','funsun'),
    $row(14,'Side','intourist'),
];
$r=hmt11_resolve($tv,$sa);
$pairs=array_map(fn($x)=>$x['tv_hotel_id'].'|'.$x['samo_hotel_id'],$r['strong_common4']);sort($pairs);
t11(in_array('1|11',$pairs,true),'biblio_common4_valid');
t11(count(array_filter($r['strong_common4'],fn($x)=>$x['tv_hotel_id']==='3'))===0,'family_qualifier_not_dropped');
t11(count(array_filter($r['strong_common4'],fn($x)=>$x['tv_hotel_id']==='4'))===0,'single_token_not_strong');
t11($r['levenshtein_used']===false,'levenshtein_flag');

$source=(string)file_get_contents(__DIR__.'/../scripts/diagnostics/hotel_match_tv_samo_side4_saved_token_alias_v11.php');
t11(!str_contains($source,'levenshtein('),'no_levenshtein_source');
t11(!str_contains($source,'hmc_tv_call(')&&!str_contains($source,'->price(')&&!str_contains($source,'v2_data_db('),'offline_only');
t11(str_contains($source,'COMMON4_ANEX_BIBLIO_FUNSUN_INTOURIST'),'common4_policy');
echo "MATCH_SIDE4_SAVED_TOKEN_ALIAS_V11_TEST_OK\n";
