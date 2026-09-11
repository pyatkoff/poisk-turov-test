<?php
declare(strict_types=1);
define('FC_LIBRARY_ONLY', true);
require_once __DIR__.'/../scripts/diagnostics/hotel_match_anex_andromeda_cluster_local_review.php';
function t(bool $v,string $m):void{if(!$v){fwrite(STDERR,"FAIL: $m\n");exit(1);}}
$alias=hmadcr_expand_names(['APERION BEACH (EX. SEA PARADISE)']);t(in_array('SEA PARADISE',$alias,true),'former alias survives cluster source');
$a=['names'=>hmadcr_expand_names(['FOUR SEASONS RESORT SHARM EL SHEIKH']),'places'=>['Sharm El Sheikh'],'latitude'=>27.956691,'longitude'=>34.392619,'local_ids'=>[]];
$b=['names'=>hmadcr_expand_names(['FOUR SEASONS SHARM EL SHEIKH']),'places'=>['Sharm El Sheikh'],'latitude'=>27.956700,'longitude'=>34.392620,'local_ids'=>[]];
$c=hmaclr_cluster($a,$b);t(($c['supplier_distance_m']??10.0)<5.0,'close supplier coordinates');
$target=['id'=>100,'country_id'=>1,'name'=>'FOUR SEASONS RESORT SSH','region_name'=>'Sharm El Sheikh','subregion_name'=>'','latitude'=>27.956695,'longitude'=>34.392620,'category'=>5];
$names=[100=>hmadcr_expand_names([$target['name']])];$hotels=[100=>$target];$idx=hmgcr_build_index([100=>true],$hotels,$names);
$r=hmaclr_resolve($a,$b,1,$idx,$hotels,$names);t(($r['bucket']??'')==='prepared','cluster resolves one local');t((int)($r['target_local_hotel_id']??0)===100,'correct local');t((int)($r['anchors']['count']??0)>=2,'cluster real anchors');
$badB=$b;$badB['names']=['FOUR GARDEN SHARM EL SHEIKH'];$q=hmaclr_resolve($a,$badB,1,$idx,$hotels,$names);t(($q['bucket']??'')!=='prepared','critical qualifier disagreement cannot prepare');
$farB=$b;$farB['latitude']=29.0;$farB['longitude']=35.0;$f=hmaclr_resolve($a,$farB,1,$idx,$hotels,$names);t(($f['bucket']??'')==='hard_conflict','supplier coords >5km block');
$weakA=['names'=>['SAHL HASHEESH BEACH'],'places'=>['Sahl Hasheesh'],'latitude'=>null,'longitude'=>null,'local_ids'=>[]];$weakB=['names'=>['SAHL HASHEESH RESORT'],'places'=>['Sahl Hasheesh'],'latitude'=>null,'longitude'=>null,'local_ids'=>[]];$weakTarget=['id'=>9,'country_id'=>1,'name'=>'SAHL HASHEESH HOTEL','region_name'=>'Sahl Hasheesh','subregion_name'=>'','latitude'=>null,'longitude'=>null,'category'=>5];$wh=[9=>$weakTarget];$wn=[9=>[$weakTarget['name']]];$wi=hmgcr_build_index([9=>true],$wh,$wn);$w=hmaclr_resolve($weakA,$weakB,1,$wi,$wh,$wn);t(($w['bucket']??'')!=='prepared','geography-only cluster blocked');
t(hmaclr_other([1,2,3],2)===[1,3],'self occupancy removed');
echo "supplier-cluster local review tests passed\n";
