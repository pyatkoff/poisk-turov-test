<?php
declare(strict_types=1);
define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/../scripts/diagnostics/hotel_match_anex_andromeda_direct_missing_side_accept.php';
function oka(bool $v,string $m):void{if(!$v){fwrite(STDERR,"FAIL: $m\n");exit(1);}}
$base=['anex_hotel_id'=>1,'andromeda_external_id'=>'A','bucket'=>'high_confidence','validation'=>'anex_only','reason'=>'direct_exact_3plus'];
oka(hmadmsa_actionable($base),'anex_only exact high is actionable');
$x=$base;$x['validation']='andromeda_only';$x['bucket']='strong_candidate';$x['reason']='direct_exact_no_geo';oka(hmadmsa_actionable($x),'andromeda_only exact strong is actionable');
$x=$base;$x['validation']='neither_side';oka(!hmadmsa_actionable($x),'neither side cannot write');
$x=$base;$x['validation']='different_local';oka(!hmadmsa_actionable($x),'different local cannot write');
$x=$base;$x['reason']='direct_strong_fuzzy_geo';oka(!hmadmsa_actionable($x),'fuzzy-only cannot write');
$x=$base;$x['reason']='direct_single_token_ultratight';oka(!hmadmsa_actionable($x),'single-token cannot write');
$source=['status'=>'completed','operation_id'=>HMADMSA_SOURCE_OPERATION,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'pairs'=>[]];
for($i=1;$i<=HMADMSA_SOURCE_COUNT;$i++)$source['pairs'][]=['anex_hotel_id'=>$i,'andromeda_external_id'=>'A'.$i,'bucket'=>'high_confidence','validation'=>$i<=18?'anex_only':'andromeda_only','reason'=>'direct_exact_3plus'];
$allow=hmadmsa_allow($source);oka(count($allow)===HMADMSA_SOURCE_COUNT,'exact allow count');
oka(HMADMSA_MAX_WRITES>=HMADMSA_SOURCE_COUNT,'write cap covers reviewed package');
echo "direct missing-side writer guards passed\n";
