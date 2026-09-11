<?php
declare(strict_types=1);
define('FC_LIBRARY_ONLY',true);
require_once __DIR__.'/../scripts/diagnostics/hotel_match_anex_andromeda_local_consensus_review.php';
function ok(bool $v,string $m):void{if(!$v){fwrite(STDERR,"FAIL: $m\n");exit(1);}}

ok(hmalcr_other_claims([10,11],10)===[11],'self claim removed');
ok(hmalcr_other_claims(['A','B'],'A')===['B'],'string self claim removed');

$hotels=[501=>['id'=>501,'country_id'=>4,'name'=>'SUNRISE CRYSTAL BEACH RESORT','region_name'=>'Side','subregion_name'=>'Side','category'=>5,'latitude'=>36.70,'longitude'=>31.50]];
$names=[501=>['SUNRISE CRYSTAL BEACH RESORT']];$index=hmgcr_build_index([501=>true],$hotels,$names);
$a=['names'=>['Sunrise Crystal Beach Hotel'],'places'=>['Side'],'latitude'=>36.7001,'longitude'=>31.5001];
$b=['names'=>['Sunrise Crystal Beach Resort'],'places'=>['Side'],'latitude'=>36.7002,'longitude'=>31.5002];
$da=hmgtr_decide($a,4,$index,$hotels,$names,[],[]);$db=hmgtr_decide($b,4,$index,$hotels,$names,[],[]);
ok(($da['bucket']??'')==='prepared','ANEX-like source resolves local');
ok(($db['bucket']??'')==='prepared','Andromeda-like source resolves local');
ok((int)$da['target_local_hotel_id']===501&&(int)$db['target_local_hotel_id']===501,'both independently agree local');

$far=$a;$far['latitude']=20.0;$far['longitude']=110.0;$df=hmgtr_decide($far,4,$index,$hotels,$names,[],[]);
ok(($df['bucket']??'')==='hard_conflict','>5km exact identity blocks');

$bad=['names'=>['Sunrise Crystal Garden Hotel'],'places'=>['Side'],'latitude'=>36.7001,'longitude'=>31.5001];$dx=hmgtr_decide($bad,4,$index,$hotels,$names,[],[]);
ok(($dx['bucket']??'')!=='prepared','BEACH/GARDEN qualifier mismatch blocks');

echo "dual-supplier local consensus tests passed\n";
