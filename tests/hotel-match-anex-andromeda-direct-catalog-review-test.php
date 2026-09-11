<?php
declare(strict_types=1);
define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/../scripts/diagnostics/hotel_match_anex_andromeda_direct_catalog_review.php';
function ok(bool $v,string $m):void{if(!$v){fwrite(STDERR,"FAIL: $m\n");exit(1);}}

$v=hmadcr_name_variants('APERION BEACH (EX. SEA PARADISE)');
ok(in_array('APERION BEACH',$v,true),'current name extracted from EX');
ok(in_array('SEA PARADISE',$v,true),'former name extracted from EX');

$p=hmadcr_best_pair(hmadcr_expand_names(['RIXOS PREMIUM BELEK']),hmadcr_expand_names(['Rixos Premium Belek Hotel']),['Belek'],['Belek']);
ok($p['critical_ok']===true,'same qualifiers');
ok($p['exact_bag']===true,'generic/place-stripped exact');
ok($p['shared']>=1,'brand identity remains');

$bad=hmadcr_best_pair(['ALPHA BEACH'],['ALPHA GARDEN'],['Side'],['Side']);
ok($bad['critical_ok']===false,'BEACH/GARDEN mismatch blocks');

$rows=[
 'A1'=>['country_id'=>4,'names'=>hmadcr_expand_names(['KACHA RESORT & SPA KOH CHANG']),'places'=>['Koh Chang'],'latitude'=>12.05,'longitude'=>102.30,'category'=>4,'live'=>true,'observation_count'=>3,'local_id'=>2993,'decision_status'=>'accepted'],
 'A2'=>['country_id'=>4,'names'=>hmadcr_expand_names(['KACHA BAY GARDEN']),'places'=>['Koh Chang'],'latitude'=>12.50,'longitude'=>102.80,'category'=>4,'live'=>false,'observation_count'=>0,'local_id'=>null,'decision_status'=>'pending'],
];
$idx=hmadcr_build_index($rows);
$source=['names'=>hmadcr_expand_names(['Kacha Resort & Spa, Koh Chang']),'places'=>['Koh Chang'],'latitude'=>12.0501,'longitude'=>102.3001,'local_ids'=>[]];
$d=hmadcr_decide($source,4,$rows,$idx);
ok(in_array($d['bucket'],['high_confidence','strong_candidate'],true),'direct exact supplier pair prepared');
ok(($d['andromeda_external_id']??'')==='A1','correct Andromeda target');
ok(($d['validation']??'')==='andromeda_only','local mapping used only as validation');

$confSource=$source;$confSource['local_ids']=[7777];
$conf=hmadcr_decide($confSource,4,$rows,$idx);
ok(($conf['bucket']??'')==='hard_conflict','different existing local blocks');
ok(($conf['reason']??'')==='direct_identity_existing_local_conflict','local conflict reason');

$farRows=$rows;$farRows['A1']['latitude']=20.0;$farRows['A1']['longitude']=110.0;$farIdx=hmadcr_build_index($farRows);
$far=hmadcr_decide($source,4,$farRows,$farIdx);
ok(($far['bucket']??'')==='hard_conflict','>5km direct identity blocks');
ok(($far['reason']??'')==='direct_identity_coordinate_conflict_gt_5km','coordinate conflict reason');

$typo=hmadcr_best_pair(hmadcr_expand_names(['MOVENPICK WATERPARK SOMA BAY']),hmadcr_expand_names(['MOVENPIK WATER PARK SOMA BAY']),['Soma Bay'],['Soma Bay']);
ok($typo['aligned']>=1,'typo brand evidence retained');
ok($typo['char_similarity']>=0.75,'typo full-name similarity retained');
ok(hmadcr_route($typo,null,true,1.0,'neither_side')===null,'one weak typo anchor is not auto-prepared');

ok(hmadcr_validation([2993],2993)==='same_local','same local validation');
ok(hmadcr_validation([2993],4000)==='different_local','different local validation');
ok(hmadcr_validation([],null)==='neither_side','unlinked validation');

echo "direct ANEX-Andromeda catalog review tests passed\n";
