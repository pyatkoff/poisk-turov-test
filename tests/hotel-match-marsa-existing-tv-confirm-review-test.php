<?php
declare(strict_types=1);
define('FC_LIBRARY_ONLY', true);
require_once __DIR__.'/../scripts/diagnostics/hotel_match_marsa_existing_tv_confirm_review.php';
function t2(bool $v,string $m):void{if(!$v){fwrite(STDERR,"FAIL: $m\n");exit(1);}}
t2(hmetv_marsa(['Марса-эль-Алам']),'russian hyphenated Marsa');
t2(hmetv_marsa(['Марса Алам']),'russian short Marsa');
t2(hmetv_marsa(['Marsa El Alam']),'english Marsa');
t2(!hmetv_marsa(['Хургада']),'other resort rejected');
$r=hmetv_tv_hotels(['data'=>['hotels'=>[['id'=>123,'name'=>'Alpha Resort'],['id'=>124,'name'=>'Beta Hotel']]]]);
t2(count($r)===2&&$r[123]['name']==='Alpha Resort','cached TV rows parsed');
$p=['anex_names'=>hmadcr_expand_names(['SUNRISE GARDEN BEACH']),'andromeda_names'=>hmadcr_expand_names(['SUNRISE GARDEN BEACH']),'anex_places'=>['Марса-эль-Алам'],'andromeda_places'=>['Марса Алам'],'anex_latitude'=>25.1,'anex_longitude'=>34.1,'andromeda_latitude'=>25.1,'andromeda_longitude'=>34.1];
$l=['names'=>hmadcr_expand_names(['SUNRISE GARDEN BEACH HOTEL']),'places'=>['Марса-эль-Алам'],'latitude'=>25.1,'longitude'=>34.1];
t2(hmetv_pair_local_ok($p,$l)['ok']===true,'generic HOTEL does not break exact proof');
$l2=$l;$l2['names']=hmadcr_expand_names(['SUNRISE BEACH']);t2(hmetv_pair_local_ok($p,$l2)['ok']===false,'meaningful GARDEN qualifier preserved');
$l3=$l;$l3['latitude']=26.0;$l3['longitude']=35.0;t2(hmetv_pair_local_ok($p,$l3)['ok']===false,'far coordinate blocks');
echo "Marsa cached Tourvisor confirmation tests passed\n";
