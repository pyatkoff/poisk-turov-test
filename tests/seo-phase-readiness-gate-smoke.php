<?php
declare(strict_types=1);
require_once __DIR__.'/../v2/seo-phase-readiness-gate-v1.php';
function phase_fail(string $x): never { fwrite(STDERR,"SEO_PHASE_READINESS_GATE_FAIL:$x\n"); exit(1); }

$manifest=[
    'integrity_ok'=>true,
    'family_quality_floor'=>100,
    'quality_by_type'=>[
        'country'=>['quality_score'=>100],
        'resort'=>['quality_score'=>100],
        'hotel_tours'=>['quality_score'=>100],
    ],
];
$identity=['integrity_ok'=>true,'source'=>'live_http_collector','hotel_tours_indexation_allowed'=>false];
$render=['state'=>'review_only_ds2_render_evidence_ready','expected_capture_count'=>10,'observed_capture_count'=>10,'hotel_tours_indexation_allowed'=>false];
$pilot=['state'=>'review_only_hotel_pilot_evidence_ready','expected_hotel_count'=>9,'observed_hotel_count'=>9,'publication_candidates'=>[],'indexation_allowed'=>false];

$r=v2_seo_phase_readiness_gate($manifest,$identity,$render,$pilot);
if(($r['state']??'')!=='phase_4_review_ready_expansion_review_may_begin') phase_fail('ready_state');
if(($r['expansion_review_allowed']??false)!==true) phase_fail('expansion_review');
if(($r['blocked_stage']??'x')!==null) phase_fail('blocked_stage');
foreach(['publication_allowed','hotel_tours_publication_allowed','hotel_tours_indexation_allowed','hotel_tours_sitemap_allowed','hotel_tours_canonical_launch_allowed','hotel_tours_route_launch_allowed'] as $flag){
    if(($r[$flag]??true)!==false) phase_fail('boundary_'.$flag);
}
if(($r['hotel_tours_publication_candidates']??null)!==[]) phase_fail('hotel_candidates');

$bad=$identity;$bad['integrity_ok']=false;
$b=v2_seo_phase_readiness_gate($manifest,$bad,$render,$pilot);
if(($b['blocked_stage']??'')!=='production_identity'||($b['expansion_review_allowed']??true)!==false) phase_fail('identity_block');

$bad=$render;$bad['observed_capture_count']=9;
$b=v2_seo_phase_readiness_gate($manifest,$identity,$bad,$pilot);
if(($b['blocked_stage']??'')!=='ds2_reference_render') phase_fail('render_block');

$bad=$pilot;$bad['publication_candidates']=['/forbidden/'];
$b=v2_seo_phase_readiness_gate($manifest,$identity,$render,$bad);
if(($b['blocked_stage']??'')!=='hotel_pilot_review_evidence') phase_fail('pilot_block');

echo "SEO_PHASE_READINESS_GATE_OK stages=4 expansion_review=1 hotel_tours_indexation=0\n";
