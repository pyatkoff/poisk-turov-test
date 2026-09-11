<?php
declare(strict_types=1);
define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/../scripts/diagnostics/hotel_match_global_approx_name_rescue_v3.php';

function t(bool $ok,string $m): void { if(!$ok) throw new RuntimeException($m); }
$base=[
  'identity_anchors'=>['identity_aligned'=>2], 'place_match'=>true,
  'source_places'=>['Паттайя'],'target_region'=>'Паттайя','target_subregion'=>null,
  'pair'=>['critical_ok'=>true,'source_tokens'=>['siam','platinum','pattaya'],'target_tokens'=>['siam','platinum','residence'],'pairs'=>[['source'=>'siam','target'=>'siam'],['source'=>'platinum','target'=>'platinum']]]
];
$r=hmgan3_semantic_guard($base);t($r['ok']===true,'generic/place extras should pass');
$x=$base;$x['pair']['source_tokens']=['club','robin','hood'];$x['pair']['target_tokens']=['robin','hood','adult','only'];$x['pair']['pairs']=[['source'=>'robin','target'=>'robin'],['source'=>'hood','target'=>'hood']];$r=hmgan3_semantic_guard($x);t($r['ok']===false&&in_array('club',$r['source_extra'],true),'CLUB must remain significant');
$x=$base;$x['pair']['source_tokens']=['cairo','world','trade','center'];$x['pair']['target_tokens']=['hilton','cairo','world','trade','center'];$x['pair']['pairs']=[['source'=>'world','target'=>'world'],['source'=>'trade','target'=>'trade'],['source'=>'center','target'=>'center']];$r=hmgan3_semantic_guard($x);t($r['ok']===false&&in_array('hilton',$r['target_extra'],true),'extra brand must block');
$x=$base;$x['identity_anchors']['identity_aligned']=1;$r=hmgan3_semantic_guard($x);t($r['ok']===false&&$r['reason']==='identity_anchors_lt_2','two anchors required');
echo "OK\n";
