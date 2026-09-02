<?php
declare(strict_types=1);
require_once __DIR__ . '/seo-seasonal-content-evidence-v1.php';
require_once __DIR__ . '/seo-seasonal-source-registry-v1.php';

/** Bind every seasonal claim to its country-specific verified source policy. */
function v2_seo_seasonal_verified_content_evidence(array $claims, ?int $nowEpoch = null): array
{
    $blocked=[]; $hosts=[]; $scopeByIdentity=[];
    foreach ($claims as $index=>$claim) {
        if (!is_array($claim)) { $blocked[]=['index'=>$index,'code'=>'invalid_claim']; continue; }
        $countryId=(int)($claim['country_id']??0); $pageKey=trim((string)($claim['page_key']??''));
        $claimKey=trim((string)($claim['claim_key']??'');
        $sourceId=trim((string)($claim['source_id']??'')); $claimType=trim((string)($claim['type']??''));
        $sourceClass=trim((string)($claim['source_class']??'')); $sourceUrl=trim((string)($claim['source_url']??''));
        if ($countryId<=0) { $blocked[]=['index'=>$index,'code'=>'missing_country_id']; continue; }
        $parts=explode(':',$pageKey);
        if (count($parts)<3||(int)$parts[2]!==$countryId) { $blocked[]=['index'=>$index,'code'=>'page_country_mismatch']; continue; }

        $pageScope=null;
        if (($parts[0]??'')==='month' && count($parts)===4) {
            $pageScope=['level'=>'country','country_id'=>$countryId,'region_id'=>null];
        } elseif (($parts[0]??'')==='resort_month' && count($parts)===5 && (int)$parts[3]>0) {
            $pageScope=['level'=>'resort','country_id'=>$countryId,'region_id'=>(int)$parts[3]];
        } else {
            $blocked[]=['index'=>$index,'code'=>'invalid_page_geography_scope'];
            continue;
        }

        $policy=v2_seo_seasonal_source_policy($countryId,$sourceId,$claimType);
        if (($policy['state']??'')!=='review_ready') { $blocked[]=['index'=>$index,'code'=>(string)($policy['code']??'source_policy_blocked')]; continue; }
        if (($policy['source_class']??'')!==$sourceClass) { $blocked[]=['index'=>$index,'code'=>'source_class_policy_mismatch']; continue; }

        $parsed=parse_url($sourceUrl); $urlHost=strtolower((string)($parsed['host']??'')); $urlPath=(string)($parsed['path']??'/');
        $policyHosts=array_map('strtolower',$policy['allowed_hosts']??[]);
        if ($urlHost===''||!in_array($urlHost,$policyHosts,true)) { $blocked[]=['index'=>$index,'code'=>'source_host_policy_mismatch']; continue; }
        $pathAllowed=false;
        foreach (($policy['path_prefixes']??[]) as $prefix) { if (str_starts_with($urlPath,(string)$prefix)) { $pathAllowed=true; break; } }
        if (!$pathAllowed) { $blocked[]=['index'=>$index,'code'=>'source_path_policy_mismatch']; continue; }

        $matchedGeography=null;
        foreach (($policy['verified_geographies']??[]) as $geography) {
            if (!is_array($geography)) continue;
            if (($geography['level']??'')!==$pageScope['level']) continue;
            if ((int)($geography['country_id']??0)!==$countryId) continue;
            if ($pageScope['level']==='resort' && (int)($geography['region_id']??0)!==(int)$pageScope['region_id']) continue;
            $matchedGeography=$geography;
            break;
        }
        if ($matchedGeography===null) { $blocked[]=['index'=>$index,'code'=>'source_geography_scope_mismatch']; continue; }

        $requiredQuery=is_array($matchedGeography['required_query']??null)?$matchedGeography['required_query']:[];
        if ($requiredQuery!==[]) {
            $actualQuery=[];
            parse_str((string)($parsed['query']??''),$actualQuery);
            $queryOk=true;
            foreach ($requiredQuery as $key=>$expected) {
                if (!array_key_exists((string)$key,$actualQuery) || strcasecmp((string)$actualQuery[(string)$key],(string)$expected)!==0) { $queryOk=false; break; }
            }
            if (!$queryOk) { $blocked[]=['index'=>$index,'code'=>'source_geography_query_mismatch']; continue; }
        }

        $hosts=array_merge($hosts,$policyHosts);
        if ($claimKey!=='') $scopeByIdentity[$pageKey.'|'.$claimKey]=$pageScope;
    }
    if ($blocked!==[]||$claims===[]) return ['state'=>'blocked','review_ready'=>false,'claims'=>[],'blocked'=>$blocked,'publication_allowed'=>false,'copy_allowed_without_evidence'=>false,'hotel_tours_publication_allowed'=>false];

    $result=v2_seo_seasonal_content_evidence($claims,$nowEpoch,array_values(array_unique($hosts)));
    if (($result['state']??'')!=='review_ready') return $result;
    foreach (($result['claims']??[]) as $index=>$normalized) {
        $identity=(string)($normalized['page_key']??'').'|'.(string)($normalized['claim_key']??'');
        if (!isset($scopeByIdentity[$identity])) {
            return ['state'=>'blocked','review_ready'=>false,'claims'=>[],'blocked'=>[['index'=>$index,'code'=>'missing_normalized_geography_scope']],'publication_allowed'=>false,'copy_allowed_without_evidence'=>false,'hotel_tours_publication_allowed'=>false];
        }
        $result['claims'][$index]['geography_scope']=$scopeByIdentity[$identity];
    }
    return $result;
}
