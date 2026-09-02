<?php
declare(strict_types=1);
require_once __DIR__ . '/seo-seasonal-content-evidence-v1.php';
require_once __DIR__ . '/seo-seasonal-source-registry-v1.php';

/** Bind every seasonal claim to its country-specific verified source policy. */
function v2_seo_seasonal_verified_content_evidence(array $claims, ?int $nowEpoch = null): array
{
    $blocked=[]; $hosts=[];
    foreach ($claims as $index=>$claim) {
        if (!is_array($claim)) { $blocked[]=['index'=>$index,'code'=>'invalid_claim']; continue; }
        $countryId=(int)($claim['country_id']??0); $pageKey=trim((string)($claim['page_key']??''));
        $sourceId=trim((string)($claim['source_id']??'')); $claimType=trim((string)($claim['type']??''));
        $sourceClass=trim((string)($claim['source_class']??'')); $sourceUrl=trim((string)($claim['source_url']??''));
        if ($countryId<=0) { $blocked[]=['index'=>$index,'code'=>'missing_country_id']; continue; }
        $parts=explode(':',$pageKey);
        if (count($parts)<3||(int)$parts[2]!==$countryId) { $blocked[]=['index'=>$index,'code'=>'page_country_mismatch']; continue; }
        $policy=v2_seo_seasonal_source_policy($countryId,$sourceId,$claimType);
        if (($policy['state']??'')!=='review_ready') { $blocked[]=['index'=>$index,'code'=>(string)($policy['code']??'source_policy_blocked')]; continue; }
        if (($policy['source_class']??'')!==$sourceClass) { $blocked[]=['index'=>$index,'code'=>'source_class_policy_mismatch']; continue; }

        $parsed=parse_url($sourceUrl); $urlHost=strtolower((string)($parsed['host']??'')); $urlPath=(string)($parsed['path']??'/');
        $policyHosts=array_map('strtolower',$policy['allowed_hosts']??[]);
        if ($urlHost===''||!in_array($urlHost,$policyHosts,true)) { $blocked[]=['index'=>$index,'code'=>'source_host_policy_mismatch']; continue; }
        $pathAllowed=false;
        foreach (($policy['path_prefixes']??[]) as $prefix) { if (str_starts_with($urlPath,(string)$prefix)) { $pathAllowed=true; break; } }
        if (!$pathAllowed) { $blocked[]=['index'=>$index,'code'=>'source_path_policy_mismatch']; continue; }
        $hosts=array_merge($hosts,$policyHosts);
    }
    if ($blocked!==[]||$claims===[]) return ['state'=>'blocked','review_ready'=>false,'claims'=>[],'blocked'=>$blocked,'publication_allowed'=>false,'copy_allowed_without_evidence'=>false,'hotel_tours_publication_allowed'=>false];
    return v2_seo_seasonal_content_evidence($claims,$nowEpoch,array_values(array_unique($hosts)));
}
