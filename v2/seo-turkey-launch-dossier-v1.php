<?php
declare(strict_types=1);
require_once __DIR__.'/seo-content-pilot-turkey-catalog-v1.php';
require_once __DIR__.'/seo-publication-manifest-v1.php';
require_once __DIR__.'/seo-opportunity-evidence-packet-v1.php';
require_once __DIR__.'/seo-launch-slice-v1.php';

/**
 * Evidence snapshot for the first controlled production SEO launch.
 *
 * These records capture a manual SERP review performed on 2026-09-02. They
 * confirm commercial SERP intent and distinct country/resort landing-page
 * treatment only; no search-volume, pricing, availability or hotel facts are
 * inferred from them.
 */
function v2_seo_turkey_launch_evidence_rows(): array
{
    $observed=1788368400; // 2026-09-02 17:00:00 UTC
    return [
        [
            'page_key'=>'country:turkey',
            'path'=>'/country/turkey/',
            'query_cluster'=>'туры в Турцию',
            'source_ref'=>'https://www.onlinetours.ru/tury/turkey',
            'observed_at_epoch'=>$observed,
        ],
        [
            'page_key'=>'resort:turkey:alanya',
            'path'=>'/country/turkey/alanya/',
            'query_cluster'=>'туры в Аланью',
            'source_ref'=>'https://www.onlinetours.ru/tury/turkey/alanya',
            'observed_at_epoch'=>$observed,
        ],
        [
            'page_key'=>'resort:turkey:antalya',
            'path'=>'/country/turkey/antalya/',
            'query_cluster'=>'туры в Анталью',
            'source_ref'=>'https://www.onlinetours.ru/tury/turkey/antalya',
            'observed_at_epoch'=>$observed,
        ],
        [
            'page_key'=>'resort:turkey:belek',
            'path'=>'/country/turkey/belek/',
            'query_cluster'=>'туры в Белек',
            'source_ref'=>'https://www.onlinetours.ru/tury/turkey/belek',
            'observed_at_epoch'=>$observed,
        ],
        [
            'page_key'=>'resort:turkey:kemer',
            'path'=>'/country/turkey/kemer/',
            'query_cluster'=>'туры в Кемер',
            'source_ref'=>'https://www.onlinetours.ru/tury/turkey/kemer',
            'observed_at_epoch'=>$observed,
        ],
        [
            'page_key'=>'resort:turkey:side',
            'path'=>'/country/turkey/side/',
            'query_cluster'=>'туры в Сиде',
            'source_ref'=>'https://www.onlinetours.ru/tury/turkey/side',
            'observed_at_epoch'=>$observed,
        ],
    ];
}

function v2_seo_turkey_launch_dossier(?int $nowEpoch=null): array
{
    $nowEpoch??=time();
    $catalog=v2_seo_content_pilot_turkey_catalog();
    $manifest=v2_seo_publication_manifest($catalog);
    $manifestByPath=[];
    foreach($manifest as $entry) $manifestByPath[(string)$entry['path']]=$entry;

    $expected=v2_seo_turkey_launch_paths();
    $rows=[];
    $errors=[];
    foreach(v2_seo_turkey_launch_evidence_rows() as $source){
        $path=(string)$source['path'];
        if(!in_array($path,$expected,true)){$errors[]='evidence_path_outside_launch_slice:'.$path;continue;}
        $manifestEntry=$manifestByPath[$path]??null;
        if(!is_array($manifestEntry)){$errors[]='missing_publication_manifest_entry:'.$path;continue;}
        if(!in_array((string)($manifestEntry['type']??''),['country','resort'],true)){$errors[]='disallowed_launch_type:'.$path;continue;}

        $page=[
            'page_key'=>(string)$source['page_key'],
            'path'=>$path,
            'query_cluster'=>(string)$source['query_cluster'],
        ];
        $demand=[
            'page_key'=>$page['page_key'],
            'query_cluster'=>$page['query_cluster'],
            'source_class'=>'manual_serp_review',
            'source_ref'=>(string)$source['source_ref'],
            'observed_at_epoch'=>(int)$source['observed_at_epoch'],
            'status'=>'confirmed',
            'serp_intent'=>'commercial',
        ];
        $uniqueness=[
            'page_key'=>$page['page_key'],
            'query_cluster'=>$page['query_cluster'],
            'page_path'=>$path,
            'source_class'=>'manual_serp_review',
            'source_ref'=>(string)$source['source_ref'],
            'observed_at_epoch'=>(int)$source['observed_at_epoch'],
            'status'=>'confirmed',
            'decision'=>'distinct',
            'competing_paths'=>[],
        ];
        $packet=v2_seo_opportunity_evidence_packet($page,$demand,$uniqueness,$nowEpoch);
        if(($packet['state']??'')!=='opportunity_evidence_review_ready') $errors[]='evidence_not_ready:'.$path;
        $rows[]=[
            'path'=>$path,
            'type'=>(string)$manifestEntry['type'],
            'query_cluster'=>$page['query_cluster'],
            'packet'=>$packet,
        ];
    }

    $rowPaths=array_column($rows,'path');
    sort($rowPaths,SORT_STRING);
    $expectedSorted=$expected;
    sort($expectedSorted,SORT_STRING);
    if($rowPaths!==$expectedSorted)$errors[]='launch_slice_coverage_mismatch';

    $fingerprint=hash('sha256',json_encode([
        'domain'=>'anytoour.ru',
        'paths'=>$expectedSorted,
        'packets'=>array_map(static fn(array $r):string=>(string)($r['packet']['packet_sha256']??''),$rows),
        'owner_approval_scope'=>'country_resort_seo_launch',
        'owner_approval_date'=>'2026-09-02',
        'hotel_tours_approved'=>false,
    ],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));

    return [
        'state'=>$errors===[]?'controlled_country_resort_launch_authorized':'controlled_country_resort_launch_blocked',
        'domain'=>'anytoour.ru',
        'paths'=>$expected,
        'rows'=>$rows,
        'errors'=>array_values(array_unique($errors)),
        'dossier_sha256'=>$fingerprint,
        'owner_approval_scope'=>'country_resort_seo_launch',
        'owner_approval_date'=>'2026-09-02',
        'hotel_tours_approved'=>false,
        'hotel_tours_indexation_allowed'=>false,
        'hotel_tours_sitemap_allowed'=>false,
        'search_contract_changes'=>false,
        'tourvisor_contract_changes'=>false,
        'pricing_contract_changes'=>false,
        'lead_contract_changes'=>false,
        'metrika_contract_changes'=>false,
    ];
}
