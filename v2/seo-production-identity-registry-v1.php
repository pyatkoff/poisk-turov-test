<?php
declare(strict_types=1);

/** Validate fresh live production identity across mixed SEO page families. */
function v2_seo_production_identity_registry_validate(array $evidence,array $expectedRows,?int $nowEpoch=null,int $maxAgeSeconds=86400): array
{
    $nowEpoch??=time();$errors=[];$domain=trim((string)($evidence['domain']??''));$observedRaw=trim((string)($evidence['observed_at_utc']??''));$observedAt=0;
    if($observedRaw!==''){try{$observedAt=(new DateTimeImmutable($observedRaw))->getTimestamp();}catch(Throwable){$observedAt=0;}}
    if($domain!=='anytoour.ru')$errors[]='identity_domain_mismatch';
    if($observedAt<=0)$errors[]='identity_observed_at_invalid';elseif($observedAt>$nowEpoch+300)$errors[]='identity_observed_in_future';elseif($nowEpoch-$observedAt>$maxAgeSeconds)$errors[]='identity_evidence_stale';

    $expected=[];
    foreach($expectedRows as $i=>$row){
        if(!is_array($row)){$errors[]='expected_row_invalid_'.$i;continue;}
        $path=(string)($row['path']??'');$type=(string)($row['type']??'');$robotsExact=(string)($row['robots']??'');$robotsPrefix=(string)($row['robots_prefix']??'');$sitemapMember=$row['sitemap_member']??null;
        if($path===''||isset($expected[$path])){$errors[]='expected_path_duplicate_or_missing_'.$i;continue;}
        if(!in_array($type,['country','resort','seasonal','hotel_tours'],true))$errors[]='expected_type_invalid:'.$path;
        if(($robotsExact===''&&$robotsPrefix==='')||($robotsExact!==''&&$robotsPrefix!=='')||!is_bool($sitemapMember))$errors[]='expected_identity_policy_invalid:'.$path;
        $robotsPolicy=$robotsExact!==''?$robotsExact:$robotsPrefix;
        if($type==='hotel_tours'){
            if(!str_starts_with($robotsPolicy,'noindex,follow'))$errors[]='hotel_tours_expected_noindex:'.$path;
            if($sitemapMember!==false)$errors[]='hotel_tours_expected_out_of_sitemap:'.$path;
        }
        $expected[$path]=['path'=>$path,'type'=>$type,'http_status'=>(int)($row['http_status']??200),'robots'=>$robotsExact,'robots_prefix'=>$robotsPrefix,'canonical'=>(string)($row['canonical']??('https://anytoour.ru'.$path)),'sitemap_member'=>$sitemapMember===true];
    }

    $seen=[];$pages=[];
    foreach((is_array($evidence['pages']??null)?$evidence['pages']:[]) as $i=>$page){
        if(!is_array($page)){$errors[]='identity_page_invalid_'.$i;continue;}
        $path=(string)($page['path']??'');if($path===''||isset($seen[$path])){$errors[]='identity_page_duplicate_or_missing_'.$i;continue;}$seen[$path]=true;
        if(!isset($expected[$path])){$errors[]='identity_path_outside_registry:'.$path;continue;}$want=$expected[$path];
        $status=(int)($page['http_status']??0);$robots=(string)($page['robots']??'');$canonical=(string)($page['canonical']??'');$sitemapMember=$page['sitemap_member']??null;
        if($status!==$want['http_status'])$errors[]='identity_http_status:'.$path;
        if($want['robots']!==''&&$robots!==$want['robots'])$errors[]='identity_robots_mismatch:'.$path;
        if($want['robots_prefix']!==''&&!str_starts_with($robots,$want['robots_prefix']))$errors[]='identity_robots_mismatch:'.$path;
        if($canonical!==$want['canonical'])$errors[]='identity_canonical_mismatch:'.$path;
        if(!is_bool($sitemapMember)||$sitemapMember!==$want['sitemap_member'])$errors[]='identity_sitemap_mismatch:'.$path;
        if($want['type']==='hotel_tours'){
            if(!str_starts_with($robots,'noindex,follow'))$errors[]='hotel_tours_live_noindex_boundary:'.$path;
            if($sitemapMember!==false)$errors[]='hotel_tours_live_sitemap_boundary:'.$path;
        }
        $pages[]=['path'=>$path,'type'=>$want['type'],'http_status'=>$status,'robots'=>$robots,'canonical'=>$canonical,'sitemap_member'=>$sitemapMember===true];
    }
    $expectedPaths=array_keys($expected);sort($expectedPaths,SORT_STRING);$actualPaths=array_keys($seen);sort($actualPaths,SORT_STRING);if($actualPaths!==$expectedPaths)$errors[]='identity_path_set_mismatch';
    usort($pages,static fn(array $a,array $b):int=>strcmp($a['path'],$b['path']));$errors=array_values(array_unique($errors));$remaining=$observedAt>0?max(0,$maxAgeSeconds-($nowEpoch-$observedAt)):0;
    $fingerprint=hash('sha256',json_encode(['domain'=>$domain,'observed_at_epoch'=>$observedAt,'max_age_seconds'=>$maxAgeSeconds,'pages'=>$pages],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
    $counts=['country'=>0,'resort'=>0,'seasonal'=>0,'hotel_tours'=>0];foreach($pages as $page)if(isset($counts[$page['type']]))$counts[$page['type']]++;
    return ['state'=>$errors===[]?'production_identity_registry_valid':'production_identity_registry_invalid','integrity_ok'=>$errors===[],'domain'=>$domain,'observed_at_epoch'=>$observedAt,'max_age_seconds'=>$maxAgeSeconds,'remaining_seconds'=>$remaining,'page_count'=>count($pages),'type_counts'=>$counts,'pages'=>$pages,'identity_registry_sha256'=>$fingerprint,'errors'=>$errors,'publication_allowed'=>false,'hotel_tours_publication_allowed'=>false,'hotel_tours_indexation_allowed'=>false];
}
