<?php
declare(strict_types=1);
putenv('MATCH_PROVIDER_GEO_NAME_DECOMP_TEST_LIBRARY=1');
require_once __DIR__.'/../scripts/diagnostics/hotel_match_provider_geo_name_decomposition_review.php';
function tn(bool $ok,string $msg):void{if(!$ok)throw new RuntimeException($msg);}
$aug=mpgnd_augmented_names(['Aroma Hanoi'],[['source_field'=>'townLName','source_raw'=>'Hanoi']]);tn(isset($aug['derived']['aroma'])&&$aug['derived']['aroma']['side']==='suffix','strip safe geo suffix');
$aug2=mpgnd_augmented_names(['Hanoi Aroma'],[['source_field'=>'townLName','source_raw'=>'Hanoi']]);tn(isset($aug2['derived']['aroma'])&&$aug2['derived']['aroma']['side']==='prefix','strip safe geo prefix');
$aug3=mpgnd_augmented_names(['Sunrise Beach'],[['source_field'=>'townLName','source_raw'=>'Beach']]);tn(!$aug3['derived'],'do not strip meaningful BEACH qualifier');
$aug4=mpgnd_augmented_names(['Grand Emin'],[['source_field'=>'townLName','source_raw'=>'Grand Emin']]);tn(!$aug4['derived'],'whole hotel name cannot disappear');
$hotels=[10=>['id'=>10,'name'=>'AROMA','category'=>'3','latitude'=>null,'longitude'=>null,'country_id'=>16,'region_name'=>'Ханой','subregion_name'=>'Ханой']];$forms=[10=>['AROMA']];$e=['source'=>['name'=>'Aroma Hanoi']];$geo=['status'=>'ok','ids'=>[10],'anchors'=>[['source_field'=>'townLName','source_raw'=>'Hanoi']]];$sel=mpgnd_select($e,16,$geo,$hotels,$forms);tn(($sel['route']??'')==='auto_accept_candidate'&&($sel['target']??0)===10&&($sel['reason']??'')==='provider_geo_name_decomposition_unique_exact','derived exact selection');
$hotels2=$hotels+[11=>['id'=>11,'name'=>'AROMA HANOI PALACE','category'=>'3','latitude'=>null,'longitude'=>null,'country_id'=>16,'region_name'=>'Ханой','subregion_name'=>'Ханой']];$forms2=$forms+[11=>['AROMA HANOI PALACE']];$geo2=$geo;$geo2['ids']=[10,11];$sel2=mpgnd_select($e,16,$geo2,$hotels2,$forms2);tn(($sel2['route']??'')!=='hard_conflict','decomposition never bypasses guards');
echo "MATCH_PROVIDER_GEO_NAME_DECOMP_TEST_OK\n";
