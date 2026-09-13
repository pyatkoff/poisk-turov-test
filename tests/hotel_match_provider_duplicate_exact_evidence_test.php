<?php
declare(strict_types=1);
define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/../scripts/diagnostics/hotel_full_catalog_reconcile.php';
require_once __DIR__ . '/../scripts/diagnostics/hotel_match_current_cross_provider_fuzzy.php';
require_once __DIR__ . '/../scripts/diagnostics/hotel_match_provider_duplicate_evidence.php';
require_once __DIR__ . '/../scripts/diagnostics/hotel_match_provider_duplicate_exact_evidence.php';

$checks=0;
$assert=static function(bool $ok,string $name)use(&$checks):void{$checks++;if(!$ok)throw new RuntimeException($name);};

$fixture=static function(string $source,string $accepted,string $reason='target_direct_geo_required',array $places=['Dubai'],array $target=[]):array{
    $pair=hmcf_name_pair($source,$accepted)+['source_name'=>$source,'accepted_name'=>$accepted];
    return [
        'external_id'=>'pending-1','country_id'=>9,'source_names'=>[$source],'source_places'=>$places,
        'target'=>array_replace(['local_hotel_id'=>45028,'name'=>'TIME DUNES HOTEL APARTMENTS AL BARSHA','region'=>'Dubai','subregion'=>'Al Barsha'],$target),
        'target_name_match'=>['score'=>0.80,'shared'=>4,'qualifier_ok'=>true],
        'margin'=>0.35,'guard'=>['coordinate_conflict'=>false,'distance_m'=>null],'reason'=>$reason,
        'accepted_occupants'=>[['external_id'=>'accepted-1','catalog_sha256'=>str_repeat('a',64),'name_match'=>$pair]],
    ];
};

$a=$fixture('Dunes Hotel Apartments Al Barsha','Dunes Hotel Apartments Al Barsha');
$s=hmpdx_exact_support($a);
$assert(is_array($s)&&$s['exact_tokens']===['al','apartments','barsha','dunes'],'exact_same_provider_bridge');
$assert(count($s['non_geography_tokens'])>=2,'identity_tokens_outside_geo');

$b=$fixture('Sunrise North Hotel','Sunrise South Hotel');
$assert(hmpdx_exact_support($b)===null,'qualifier_difference_blocks');

$c=$fixture('Phu Quoc Hotel','Phu Quoc Resort','target_direct_geo_required',['Phu Quoc'],['region'=>'Phu Quoc','subregion'=>'Phu Quoc']);
$assert(hmpdx_exact_support($c)===null,'destination_only_blocks');

$d=$fixture('Grand Emin Hotel','Grand Emin Resort');
$d['guard']['coordinate_conflict']=true;
$assert(hmpdx_exact_support($d)===null,'coordinate_conflict_blocks');

$e=$fixture('Grand Emin Hotel','Grand Emin Resort','target_score_below_075');
$assert(hmpdx_exact_support($e)===null,'only_direct_geo_block_lane');

$f=$fixture('Grand Emin Hotel','Grand Emin Resort');
$f['target_name_match']['score']=0.74;
$assert(hmpdx_exact_support($f)===null,'target_score_guard_retained');

$g=$fixture('Grand Emin Hotel','Grand Emin Resort');
$g['margin']=0.19;
$assert(hmpdx_exact_support($g)===null,'target_margin_guard_retained');

$v1=['status'=>'completed','operation_id'=>'v1','tail_examined'=>1273,'prepared_candidate_count'=>4,'blocked_rows'=>[$a,$b,$c]];
$r=hmpdx_review($v1,'v2');
$assert($r['additional_exact_bridge_count']===1,'review_additional_count');
$assert($r['source_direct_geo_blocked']===3,'review_source_lane_count');
$assert($r['database_writes']===0&&$r['mapping_writes']===0&&$r['supplier_calls']===0&&$r['tourvisor_calls']===0,'read_only_contract');
$assert(($r['prepared_candidates'][0]['reason']??'')==='same_provider_exact_identity_bridge_without_direct_geo','reason_contract');
$assert(($r['guards']['accepted_identity_non_geography_tokens_min']??0)===2,'non_geo_guard_declared');

echo "hotel_match_provider_duplicate_exact_evidence_test: {$checks} checks PASS\n";
