<?php
declare(strict_types=1);
define('FC_LIBRARY_ONLY',true);
require_once __DIR__.'/../scripts/diagnostics/hotel_match_anex_andromeda_mutual_graph_review.php';
function ok(bool $v,string $m):void{if(!$v){fwrite(STDERR,"FAIL: $m\n");exit(1);}}

ok(hmamgr_validation([10],[10])==='same_local','same local');
ok(hmamgr_validation([10],[11])==='different_local','different local');
ok(hmamgr_validation([10],[])==='anex_only','anex only');
ok(hmamgr_validation([],[11])==='andromeda_only','andromeda only');
ok(hmamgr_validation([],[])==='neither_side','neither');

$targets=[
 'A1'=>['id'=>'A1','country_id'=>4,'names'=>['Kacha Emerald Resort Koh Chang'],'places'=>['Koh Chang'],'latitude'=>12.05,'longitude'=>102.30,'local_ids'=>[]],
 'A2'=>['id'=>'A2','country_id'=>4,'names'=>['Kacha Bay Garden'],'places'=>['Koh Chang'],'latitude'=>12.5,'longitude'=>102.8,'local_ids'=>[]],
];
$source=['id'=>'101','country_id'=>4,'names'=>['Kacha Emerald Resort & Spa, Koh Chang'],'places'=>['Koh Chang'],'latitude'=>12.0501,'longitude'=>102.3001,'local_ids'=>[]];
$idx=hmamgr_index($targets);$loc=hmadcrh_locality_common($targets);$d=hmamgr_choose($source,$targets,$idx,$loc);
ok(($d['bucket']??'')==='candidate','exact candidate');
ok(($d['target_id']??'')==='A1','correct target');
ok(in_array($d['reason']??'',['mutual_exact_3plus','mutual_exact_geo','mutual_exact_no_geo'],true),'exact route');
ok(($d['candidate']['identity_anchors']['identity_aligned']??0)>=2,'identity anchors');

$far=$targets;$far['A1']['latitude']=20.0;$far['A1']['longitude']=110.0;$fd=hmamgr_choose($source,$far,hmamgr_index($far),hmadcrh_locality_common($far));
ok(($fd['bucket']??'')!=='candidate','coordinate conflict cannot prepare');

$critical=['B1'=>['id'=>'B1','country_id'=>4,'names'=>['Alpha Garden'],'places'=>['Side'],'latitude'=>36.7,'longitude'=>31.5,'local_ids'=>[]]];
$cs=['id'=>'201','country_id'=>4,'names'=>['Alpha Beach'],'places'=>['Side'],'latitude'=>36.7,'longitude'=>31.5,'local_ids'=>[]];
$cd=hmamgr_choose($cs,$critical,hmamgr_index($critical),hmadcrh_locality_common($critical));
ok(($cd['bucket']??'')!=='candidate','critical qualifier mismatch blocks');

$conf=$targets;$conf['A1']['local_ids']=[55];$src2=$source;$src2['local_ids']=[77];$xd=hmamgr_choose($src2,$conf,hmamgr_index($conf),hmadcrh_locality_common($conf));
ok(($xd['bucket']??'')==='hard_conflict','different existing local hard blocks');

echo "mutual supplier graph review tests passed\n";
