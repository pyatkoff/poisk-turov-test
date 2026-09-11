<?php
declare(strict_types=1);
define('FC_LIBRARY_ONLY', true);
require_once __DIR__.'/../scripts/diagnostics/hotel_match_anex_andromeda_cluster_local_review.php';
function t(bool $v,string $m):void{if(!$v){fwrite(STDERR,"FAIL: $m\n");exit(1);}}
$a=['names'=>hmadcr_expand_names(['APERION BEACH (EX. SEA PARADISE)']),'places'=>['Side'],'latitude'=>36.713018,'longitude'=>31.563078,'local_ids'=>[]];
$b=['names'=>hmadcr_expand_names(['APERION BEACH HOTEL']),'places'=>['Side'],'latitude'=>36.713018,'longitude'=>31.563078,'local_ids'=>[]];
$c=hmaclr_cluster($a,$b);t(in_array('SEA PARADISE',$c['names'],true),'former alias survives cluster');t(($c['supplier_distance_m']??1.0)<1.0,'close supplier coordinates');
$target=['id'=>6319,'country_id'=>4,'name'=>'APERION BEACH (EX. SEA PARADISE)','region_name'=>'Side','subregion_name'=>'','latitude'=>36.713018,'longitude'=>31.563078,'category'=>4];
$names=[6319=>hmadcr_expand_names([$target['name']])];$hotels=[6319=>$target];$set=[6319=>true];$idx=hmgcr_build_index($set,$hotels,$names);
$r=hmaclr_resolve($a,$b,4,$idx,$hotels,$names);t(($r['bucket']??'')==='prepared','cluster resolves one local');t((int)($r['target_local_hotel_id']??0)===6319,'correct local');t((int)($r['anchors']['count']??0)>=2,'cluster real anchors');
$badB=$b;$badB['names']=['APERION GARDEN'];$q=hmaclr_resolve($a,$badB,4,$idx,$hotels,$names);t(($q['bucket']??'')!=='prepared','critical qualifier disagreement cannot prepare');
$farB=$b;$farB['latitude']=37.5;$farB['longitude']=32.5;$f=hmaclr_resolve($a,$farB,4,$idx,$hotels,$names);t(($f['bucket']??'')==='hard_conflict','supplier coords >5km block');
$weakA=['names'=>['SAHL HASHEESH BEACH'],'places'=>['Sahl Hasheesh'],'latitude'=>null,'longitude'=>null,'local_ids'=>[]];$weakB=['names'=>['SAHL HASHEESH RESORT'],'places'=>['Sahl Hasheesh'],'latitude'=>null,'longitude'=>null,'local_ids'=>[]];$weakTarget=['id'=>9,'country_id'=>1,'name'=>'SAHL HASHEESH HOTEL','region_name'=>'Sahl Hasheesh','subregion_name'=>'','latitude'=>null,'longitude'=>null,'category'=>5];$wh=[9=>$weakTarget];$wn=[9=>[$weakTarget['name']]];$wi=hmgcr_build_index([9=>true],$wh,$wn);$w=hmaclr_resolve($weakA,$weakB,1,$wi,$wh,$wn);t(($w['bucket']??'')!=='prepared','geography-only cluster blocked');
t(hmaclr_other([1,2,3],2)===[1,3],'self occupancy removed');
echo "supplier-cluster local review tests passed\n";
