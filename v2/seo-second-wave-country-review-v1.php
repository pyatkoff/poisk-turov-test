<?php
declare(strict_types=1);

require_once __DIR__.'/seo-content-catalog-v1.php';
require_once __DIR__.'/seo-page-launch-readiness-v1.php';
require_once __DIR__.'/seo-opportunity-evidence-packet-v1.php';
require_once __DIR__.'/seo-content-pilot-egypt-v1.php';
require_once __DIR__.'/seo-content-pilot-maldives-v1.php';

/**
 * Review-only second-wave shortlist for existing country pages.
 *
 * Manual SERP evidence below records page-shape/intent evidence only. It does
 * not claim search volume, ranking potential, prices, availability or offer
 * facts. Production identity is verified separately by the live workflow.
 */
function v2_seo_second_wave_country_specs(): array
{
    $observedAt=1788369000; // 2026-09-02 17:10:00 UTC
    return [
        [
            'key'=>'egypt',
            'record'=>v2_seo_content_pilot_egypt(),
            'page_key'=>'country:egypt',
            'path'=>'/country/egypt/',
            'query_cluster'=>'туры в Египет',
            'serp_source_ref'=>'https://www.sunmar.ru/egypt/',
            'observed_at_epoch'=>$observedAt,
            'resort_layer_reason'=>'authoritative_production_region_ids_not_verified',
        ],
        [
            'key'=>'maldives',
            'record'=>v2_seo_content_pilot_maldives(),
            'page_key'=>'country:maldives',
            'path'=>'/country/maldives/',
            'query_cluster'=>'туры на Мальдивы',
            'serp_source_ref'=>'https://maldives.ru/',
            'observed_at_epoch'=>$observedAt,
            'resort_layer_reason'=>'resort_region_ids_unbound',
        ],
    ];
}

function v2_seo_second_wave_country_review(?int $nowEpoch=null): array
{
    $nowEpoch??=time();
    $rows=[];
    $errors=[];

    foreach(v2_seo_second_wave_country_specs() as $spec){
        $record=$spec['record'];
        $catalog=v2_seo_content_catalog([$record],[]);
        $readinessRows=v2_seo_page_launch_readiness($catalog,[],$nowEpoch);
        $readiness=$readinessRows[0]??[];
        if((string)($readiness['path']??'')!==(string)$spec['path']){
            $errors[]='readiness_path_mismatch:'.$spec['key'];
        }
        if((int)($readiness['score']??0)!==100||($readiness['ready_for_launch_review']??false)!==true){
            $errors[]='quality_not_100:'.$spec['key'];
        }

        $page=[
            'page_key'=>(string)$spec['page_key'],
            'path'=>(string)$spec['path'],
            'query_cluster'=>(string)$spec['query_cluster'],
        ];
        $demand=[
            'page_key'=>$page['page_key'],
            'query_cluster'=>$page['query_cluster'],
            'source_class'=>'manual_serp_review',
            'source_ref'=>(string)$spec['serp_source_ref'],
            'observed_at_epoch'=>(int)$spec['observed_at_epoch'],
            'status'=>'confirmed',
            'serp_intent'=>'commercial',
        ];
        $uniqueness=[
            'page_key'=>$page['page_key'],
            'query_cluster'=>$page['query_cluster'],
            'page_path'=>$page['path'],
            'source_class'=>'manual_serp_review',
            'source_ref'=>(string)$spec['serp_source_ref'],
            'observed_at_epoch'=>(int)$spec['observed_at_epoch'],
            'status'=>'confirmed',
            'decision'=>'distinct',
            'competing_paths'=>[],
        ];
        $packet=v2_seo_opportunity_evidence_packet($page,$demand,$uniqueness,$nowEpoch);
        if(($packet['state']??'')!=='opportunity_evidence_review_ready'){
            $errors[]='opportunity_evidence_not_ready:'.$spec['key'];
        }

        $rows[]=[
            'key'=>(string)$spec['key'],
            'path'=>$page['path'],
            'page_type'=>'country',
            'query_cluster'=>$page['query_cluster'],
            'technical_quality_score'=>(int)($readiness['score']??0),
            'technical_review_ready'=>($readiness['ready_for_launch_review']??false)===true,
            'opportunity_evidence'=>$packet,
            'production_identity_state'=>'requires_live_verifier',
            'review_decision'=>'HOLD',
            'hold_reasons'=>['explicit_scoring_policy_pending','fresh_live_production_identity_required'],
            'next_action'=>'bind_fresh_production_identity_then_score',
            'resort_layer'=>[
                'state'=>'HOLD',
                'reason'=>(string)$spec['resort_layer_reason'],
                'route_creation_allowed'=>false,
            ],
        ];
    }

    $fingerprintRows=array_map(static function(array $row):array{
        return [
            'path'=>$row['path'],
            'query_cluster'=>$row['query_cluster'],
            'quality'=>$row['technical_quality_score'],
            'packet_sha256'=>$row['opportunity_evidence']['packet_sha256']??'',
            'decision'=>$row['review_decision'],
            'resort_layer_state'=>$row['resort_layer']['state']??'',
        ];
    },$rows);
    $fingerprint=hash('sha256',json_encode([
        'domain'=>'anytoour.ru',
        'scope'=>'second_wave_existing_country_review_v1',
        'rows'=>$fingerprintRows,
        'publication_scope_expanded'=>false,
        'hotel_tours_indexation_allowed'=>false,
    ],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));

    return [
        'state'=>$errors===[]?'second_wave_country_review_ready':'second_wave_country_review_blocked',
        'domain'=>'anytoour.ru',
        'scope'=>'second_wave_existing_country_review_v1',
        'rows'=>$rows,
        'errors'=>array_values(array_unique($errors)),
        'review_sha256'=>$fingerprint,
        'live_production_verifier'=>'.github/workflows/verify-anytoour-second-wave-review.yml',
        'publication_candidates'=>[],
        'publication_scope_expanded'=>false,
        'publication_allowed'=>false,
        'indexation_allowed'=>false,
        'sitemap_allowed'=>false,
        'canonical_launch_allowed'=>false,
        'route_launch_allowed'=>false,
        'hotel_tours_indexation_allowed'=>false,
        'hotel_tours_sitemap_allowed'=>false,
        'search_contract_changes'=>false,
        'tourvisor_contract_changes'=>false,
        'pricing_contract_changes'=>false,
        'lead_contract_changes'=>false,
        'metrika_contract_changes'=>false,
    ];
}
