<?php
declare(strict_types=1);

/**
 * Validate a live production identity artifact before it can participate in an
 * SEO launch review. The contract deliberately checks only identity/indexation
 * state; it does not infer demand, quality, prices or ranking potential.
 */
function v2_seo_production_identity_evidence_validate(
    array $evidence,
    array $expectedPaths,
    string $expectedScope,
    ?int $nowEpoch=null,
    int $maxAgeSeconds=86400
): array {
    $nowEpoch??=time();
    $errors=[];
    $domain=trim((string)($evidence['domain']??''));
    $scope=trim((string)($evidence['scope']??''));
    $state=trim((string)($evidence['state']??''));
    $observedRaw=trim((string)($evidence['observed_at_utc']??''));
    $observedAt=0;
    if($observedRaw!==''){
        try { $observedAt=(new DateTimeImmutable($observedRaw))->getTimestamp(); }
        catch(Throwable){ $observedAt=0; }
    }

    if($state!=='fresh_second_wave_production_identity_evidence')$errors[]='identity_state_invalid';
    if($domain!=='anytoour.ru')$errors[]='identity_domain_mismatch';
    if($scope!==$expectedScope)$errors[]='identity_scope_mismatch';
    if(($evidence['production_identity_fresh']??false)!==true)$errors[]='identity_fresh_flag_missing';
    if(($evidence['publication_scope_expanded']??true)!==false)$errors[]='identity_publication_scope_expanded';
    if(($evidence['indexation_allowed']??true)!==false)$errors[]='identity_indexation_boundary';
    if(($evidence['sitemap_allowed']??true)!==false)$errors[]='identity_sitemap_boundary';
    if(($evidence['hotel_tours_indexation_allowed']??true)!==false)$errors[]='identity_hotel_boundary';
    if($observedAt<=0)$errors[]='identity_observed_at_invalid';
    elseif($observedAt>$nowEpoch+300)$errors[]='identity_observed_in_future';
    elseif($nowEpoch-$observedAt>$maxAgeSeconds)$errors[]='identity_evidence_stale';

    $expected=array_values(array_unique(array_map('strval',$expectedPaths)));
    sort($expected,SORT_STRING);
    $seen=[]; $normalizedPages=[];
    $pages=is_array($evidence['pages']??null)?$evidence['pages']:[];
    foreach($pages as $i=>$page){
        if(!is_array($page)){ $errors[]='identity_page_invalid_'.$i; continue; }
        $path=(string)($page['path']??'');
        if($path===''||isset($seen[$path])){ $errors[]='identity_page_duplicate_or_missing_'.$i; continue; }
        $seen[$path]=true;
        if(!in_array($path,$expected,true))$errors[]='identity_path_outside_scope:'.$path;
        $status=(int)($page['http_status']??0);
        $robots=(string)($page['robots']??'');
        $canonical=(string)($page['canonical']??'');
        $sitemapMember=$page['sitemap_member']??null;
        if($status!==200)$errors[]='identity_http_status:'.$path;
        if(!str_starts_with($robots,'noindex,follow'))$errors[]='identity_expected_noindex:'.$path;
        if($canonical!=='https://anytoour.ru'.$path)$errors[]='identity_canonical_mismatch:'.$path;
        if($sitemapMember!==false)$errors[]='identity_premature_sitemap_member:'.$path;
        $normalizedPages[]=[
            'path'=>$path,
            'http_status'=>$status,
            'robots'=>$robots,
            'canonical'=>$canonical,
            'sitemap_member'=>$sitemapMember===true,
        ];
    }
    $actual=array_keys($seen); sort($actual,SORT_STRING);
    if($actual!==$expected)$errors[]='identity_path_set_mismatch';
    usort($normalizedPages,static fn(array $a,array $b):int=>strcmp($a['path'],$b['path']));

    $remaining=$observedAt>0?max(0,$maxAgeSeconds-($nowEpoch-$observedAt)):0;
    $fingerprint=hash('sha256',json_encode([
        'domain'=>$domain,
        'scope'=>$scope,
        'observed_at_epoch'=>$observedAt,
        'max_age_seconds'=>$maxAgeSeconds,
        'pages'=>$normalizedPages,
    ],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));

    return [
        'state'=>$errors===[]?'production_identity_evidence_valid':'production_identity_evidence_invalid',
        'domain'=>$domain,
        'scope'=>$scope,
        'observed_at_epoch'=>$observedAt,
        'max_age_seconds'=>$maxAgeSeconds,
        'remaining_seconds'=>$remaining,
        'pages'=>$normalizedPages,
        'identity_sha256'=>$fingerprint,
        'errors'=>array_values(array_unique($errors)),
        'publication_allowed'=>false,
        'indexation_allowed'=>false,
        'sitemap_allowed'=>false,
        'canonical_launch_allowed'=>false,
        'route_launch_allowed'=>false,
        'hotel_tours_indexation_allowed'=>false,
    ];
}
