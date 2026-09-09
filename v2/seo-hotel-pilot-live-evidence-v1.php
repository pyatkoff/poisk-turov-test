<?php
declare(strict_types=1);
require_once __DIR__.'/seo-hotel-review-pilot-package-v1.php';
require_once __DIR__.'/seo-hotel-pilot-review-dossier-v1.php';
require_once __DIR__.'/seo-content-pilot-turkey-hotel-review-catalog-v1.php';
require_once __DIR__.'/seo-content-pilot-maldives-catalog-v1.php';
require_once __DIR__.'/seo-content-pilot-egypt-hotel-review-catalog-v1.php';

function v2_seo_hotel_pilot_live_families(): array
{
    return [
        ['key'=>'turkey','catalog'=>v2_seo_content_pilot_turkey_hotel_review_catalog()],
        ['key'=>'maldives','catalog'=>v2_seo_content_pilot_maldives_catalog()],
        ['key'=>'egypt','catalog'=>v2_seo_content_pilot_egypt_hotel_review_catalog()],
    ];
}

function v2_seo_hotel_live_meta(string $body,string $name): string
{
    $q=preg_quote($name,'~');
    if(preg_match('~<meta[^>]+name=["\']'.$q.'["\'][^>]+content=["\']([^"\']+)~i',$body,$m)||preg_match('~<meta[^>]+content=["\']([^"\']+)["\'][^>]+name=["\']'.$q.'["\']~i',$body,$m))return trim($m[1]);
    return '';
}

function v2_seo_hotel_live_canonical(string $body): string
{
    if(preg_match('~<link[^>]+rel=["\']canonical["\'][^>]+href=["\']([^"\']+)~i',$body,$m)||preg_match('~<link[^>]+href=["\']([^"\']+)["\'][^>]+rel=["\']canonical["\']~i',$body,$m))return trim($m[1]);
    return '';
}

/** Read-only live evidence for the fixed Turkey/Maldives/Egypt 3x3 review slice. */
function v2_seo_collect_hotel_pilot_live_evidence(callable $fetch,?int $nowEpoch=null): array
{
    $nowEpoch??=time();
    $families=v2_seo_hotel_pilot_live_families();
    $union=v2_seo_review_catalog_union($families);
    $spec=v2_seo_hotel_launch_pilot_spec();
    $catalog=v2_seo_hotel_review_pilot_catalog($union,$spec);
    $snapshotSource=(string)@file_get_contents(__DIR__.'/seo-offer-snapshot-v1.php');
    $hotelRenderer=(string)@file_get_contents(__DIR__.'/seo-hotel-tour-page-v1.php');
    $sourceContract=substr_count($snapshotSource,'s.expires_at>=NOW()')>=2&&str_contains($hotelRenderer,'v2_seo_hotel_snapshot_offers(');
    $sitemap=(array)$fetch('https://anytoour.ru/sitemap.xml');
    $sitemapBody=(string)($sitemap['body']??'');
    $checks=[];$snapshotEvidence=[];
    foreach($spec['countries'] as $bucket){
        $expectedCountry=(int)$bucket['country_id'];
        foreach($bucket['paths'] as $path){
            $entry=$catalog['registry'][$path]??[];$report=$catalog['reports'][$path]??[];
            $state=is_array($entry['page']['search_state']??null)?$entry['page']['search_state']:[];
            $countryId=(int)($state['country']??0);$hotelId=(int)($state['hotel']??0);
            $url='https://anytoour.ru'.$path;$res=(array)$fetch($url);$body=(string)($res['body']??'');
            $robots=v2_seo_hotel_live_meta($body,'robots');$canonical=v2_seo_hotel_live_canonical($body);
            $identity=(int)($res['status']??0)===200&&$countryId===$expectedCountry&&$hotelId>0&&$canonical===$url;
            $freshOffers=$sourceContract&&str_contains($body,'sp-offer-snapshot')&&str_contains($body,'sp-offer-item--hotel');
            if($identity&&$freshOffers){
                $snapshotEvidence[]=['country_id'=>$countryId,'hotel_id'=>$hotelId,'hotel_slug'=>basename(rtrim($path,'/')),'evidence_epoch'=>$nowEpoch,'freshness_seconds'=>300];
            }
            $checks[$path]=[
                'path'=>$path,'country_id'=>$countryId,'hotel_id'=>$hotelId,'http_status'=>(int)($res['status']??0),'robots'=>$robots,'canonical'=>$canonical,
                'identity_verified'=>$identity,'fresh_offer_evidence'=>$freshOffers,'review_status_ok'=>(($report['status']??'')==='review'),
                'noindex_ok'=>str_starts_with($robots,'noindex,follow'),'out_of_sitemap_ok'=>!str_contains($sitemapBody,$url),
            ];
        }
    }
    $manifest=v2_seo_launch_manifest($catalog,$snapshotEvidence,$nowEpoch);
    $readiness=v2_seo_page_launch_readiness($catalog,$snapshotEvidence,$nowEpoch);$scoreByPath=[];
    foreach($readiness as $row)$scoreByPath[(string)($row['path']??'')]=(int)($row['score']??0);
    $catalogIntegrity=($manifest['integrity_ok']??false)===true&&($manifest['hotel_tours_publication_candidate_count']??1)===0;
    $rows=[];
    foreach($checks as $path=>$check){
        $rows[]=[
            'path'=>$path,'country_id'=>$check['country_id'],'captured_at_epoch'=>$nowEpoch,
            'source_ref'=>'https://anytoour.ru'.$path.';manifest='.(string)($manifest['manifest_sha256']??''),
            'quality_score'=>$scoreByPath[$path]??0,'identity_verified'=>$check['identity_verified'],'catalog_integrity_ok'=>$catalogIntegrity,
            'fresh_offer_evidence'=>$check['fresh_offer_evidence'],'review_status_ok'=>$check['review_status_ok'],'noindex_ok'=>$check['noindex_ok'],
            'out_of_sitemap_ok'=>$check['out_of_sitemap_ok'],'publication_candidate_absent'=>$catalogIntegrity&&$check['out_of_sitemap_ok'],
        ];
    }
    $dossier=v2_seo_hotel_pilot_review_dossier($rows,$nowEpoch);
    $packageState='review_only_package_not_ready';
    if(($dossier['state']??'')==='review_only_hotel_pilot_evidence_ready'){
        try{$package=v2_seo_hotel_review_pilot_package($families,$snapshotEvidence,$nowEpoch);$packageState=(string)($package['state']??'review_only_package_not_ready');}
        catch(Throwable $e){$packageState='review_only_package_blocked';}
    }
    return [
        'state'=>(($dossier['state']??'')==='review_only_hotel_pilot_evidence_ready'&&$packageState==='review_only_manifest_bound_pilot_package')?'review_only_hotel_pilot_live_evidence_ready':'review_only_hotel_pilot_live_evidence_blocked',
        'observed_at_epoch'=>$nowEpoch,'source'=>'live_http_plus_catalog_manifest','source_contract_ok'=>$sourceContract,'transport_sitemap_status'=>(int)($sitemap['status']??0),
        'checks'=>array_values($checks),'manifest'=>$manifest,'dossier'=>$dossier,'package_state'=>$packageState,
        'publication_candidates'=>[],'publication_allowed'=>false,'indexation_allowed'=>false,'sitemap_allowed'=>false,'canonical_launch_allowed'=>false,'route_launch_allowed'=>false,
        'explicit_user_indexation_approval_required'=>true,'search_contract_changes'=>false,'tourvisor_contract_changes'=>false,'pricing_contract_changes'=>false,'lead_contract_changes'=>false,'metrika_contract_changes'=>false,
    ];
}
