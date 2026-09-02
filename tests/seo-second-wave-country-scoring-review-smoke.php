<?php
declare(strict_types=1);
require_once __DIR__.'/../v2/seo-second-wave-country-scoring-review-v1.php';
require_once __DIR__.'/../v2/seo-opportunity-evidence-packet-v1.php';

function scoring_review_fail(string $message): void
{
    fwrite(STDERR,"SEO_SECOND_WAVE_SCORING_REVIEW_FAIL:$message\n");
    exit(1);
}

$now=1788369300; // 2026-09-02 17:15 UTC
$paths=['/country/egypt/','/country/maldives/'];
$identity=[
    'state'=>'fresh_second_wave_production_identity_evidence',
    'domain'=>'anytoour.ru',
    'scope'=>'egypt_maldives_country_review_v1',
    'observed_at_utc'=>'2026-09-02T17:14:00+00:00',
    'production_identity_fresh'=>true,
    'publication_scope_expanded'=>false,
    'indexation_allowed'=>false,
    'sitemap_allowed'=>false,
    'hotel_tours_indexation_allowed'=>false,
    'pages'=>array_map(static fn(string $path):array=>[
        'path'=>$path,
        'http_status'=>200,
        'robots'=>'noindex,follow,max-image-preview:large',
        'canonical'=>'https://anytoour.ru'.$path,
        'sitemap_member'=>false,
    ],$paths),
];
$policy=[
    'policy_id'=>'fixture-country-ranking',
    'version'=>'test-v1',
    'source_ref'=>'fixture://explicit-reviewed-test-policy',
    'approved_at_epoch'=>$now-120,
    'dimensions'=>[
        'demand'=>[
            'weight'=>60,
            'rules'=>[
                ['field'=>'metrics.monthly_searches','operator'=>'gte','value'=>1,'score'=>80],
            ],
        ],
        'uniqueness'=>[
            'weight'=>40,
            'rules'=>[
                ['field'=>'decision','operator'=>'eq','value'=>'distinct','score'=>100],
            ],
        ],
    ],
];

$specs=v2_seo_second_wave_country_specs();
$packets=[];
foreach($specs as $i=>$spec){
    $page=[
        'page_key'=>(string)$spec['page_key'],
        'path'=>(string)$spec['path'],
        'query_cluster'=>(string)$spec['query_cluster'],
    ];
    $demand=[
        'page_key'=>$page['page_key'],
        'query_cluster'=>$page['query_cluster'],
        'source_class'=>'keyword_research_export',
        'source_ref'=>'fixture://keyword-export/'.($i+1),
        'observed_at_epoch'=>$now-60,
        'status'=>'confirmed',
        'metrics'=>['monthly_searches'=>100+$i],
        'serp_intent'=>'commercial',
    ];
    $uniqueness=[
        'page_key'=>$page['page_key'],
        'query_cluster'=>$page['query_cluster'],
        'page_path'=>$page['path'],
        'source_class'=>'manual_serp_review',
        'source_ref'=>'fixture://serp-review/'.($i+1),
        'observed_at_epoch'=>$now-60,
        'status'=>'confirmed',
        'decision'=>'distinct',
        'competing_paths'=>[],
    ];
    $packets[$page['path']]=v2_seo_opportunity_evidence_packet($page,$demand,$uniqueness,$now);
}

$review=v2_seo_second_wave_country_scoring_review($identity,$packets,$policy,$now);
if(($review['state']??'')!=='second_wave_country_scored_review_only') scoring_review_fail('scored_state');
if(($review['scored_count']??0)!==2||($review['score_summary']['count']??0)!==2) scoring_review_fail('scored_count');
if(($review['launch_decision_state']??'')!=='requires_separate_launch_dossier') scoring_review_fail('launch_dossier_boundary');
if(($review['identity_remaining_seconds']??0)<=0) scoring_review_fail('identity_ttl');
foreach($review['rows'] as $row){
    if(($row['scoring_state']??'')!=='SCORED_REVIEW_ONLY'||!is_int($row['opportunity_score']??null)) scoring_review_fail('row_score');
}
foreach(['publication_allowed','indexation_allowed','sitemap_allowed','canonical_launch_allowed','route_launch_allowed','hotel_tours_indexation_allowed','hotel_tours_sitemap_allowed'] as $flag){
    if(($review[$flag]??true)!==false) scoring_review_fail('boundary_'.$flag);
}
if(($review['publication_candidates']??null)!==[]||($review['publication_scope_expanded']??true)!==false) scoring_review_fail('publication_scope');

$missingPolicy=v2_seo_second_wave_country_scoring_review($identity,$packets,[],$now);
if(($missingPolicy['state']??'')!=='second_wave_country_scoring_blocked') scoring_review_fail('missing_policy_not_blocked');

$staleIdentity=$identity;
$staleIdentity['observed_at_utc']='2026-08-31T17:14:00+00:00';
$stale=v2_seo_second_wave_country_scoring_review($staleIdentity,$packets,$policy,$now);
if(($stale['state']??'')!=='second_wave_country_scoring_blocked') scoring_review_fail('stale_identity_not_blocked');

$badCanonical=$identity;
$badCanonical['pages'][0]['canonical']='https://wrong.example/country/egypt/';
$bad=v2_seo_second_wave_country_scoring_review($badCanonical,$packets,$policy,$now);
if(($bad['state']??'')!=='second_wave_country_scoring_blocked') scoring_review_fail('canonical_mismatch_not_blocked');

// The currently committed manual SERP packets deliberately carry no invented
// search-volume metrics, so a metric-based policy must remain HOLD until real
// demand data is supplied.
$base=v2_seo_second_wave_country_review($now);
$currentPackets=[];
foreach($base['rows'] as $row)$currentPackets[(string)$row['path']]=$row['opportunity_evidence'];
$current=v2_seo_second_wave_country_scoring_review($identity,$currentPackets,$policy,$now);
if(($current['state']??'')!=='second_wave_country_scoring_blocked'||($current['scored_count']??-1)!==0) scoring_review_fail('missing_real_metrics_not_blocked');

echo "SEO_SECOND_WAVE_SCORING_REVIEW_OK identityTTL=1 explicitPolicy=1 scoredFixture=2 currentRealMetrics=0 publication=0 hotelTours=0\n";
