<?php
declare(strict_types=1);

/** Fail-closed editorial evidence contract for review-only seasonal SEO copy. */
function v2_seo_seasonal_content_evidence(array $claims, ?int $nowEpoch = null, array $allowedSourceHosts = []): array
{
    $nowEpoch ??= time();
    $allowedTypes = ['climate_temperature','climate_precipitation','sea_temperature','daylight','entry_requirement','calendar_event'];
    $allowedSources = ['official_government','official_meteorological','first_party_verified'];
    $volatileTypes = ['entry_requirement','calendar_event'];
    $allowedSourceHosts = array_values(array_unique(array_filter(array_map(static fn($v) => strtolower(trim((string)$v)), $allowedSourceHosts))));
    $blocked=[]; $normalized=[]; $seen=[];

    foreach ($claims as $index=>$claim) {
        if (!is_array($claim)) { $blocked[]=['index'=>$index,'code'=>'invalid_claim']; continue; }
        $pageKey=trim((string)($claim['page_key']??''));
        $claimKey=trim((string)($claim['claim_key']??''));
        $type=trim((string)($claim['type']??''));
        $value=trim((string)($claim['value']??''));
        $sourceClass=trim((string)($claim['source_class']??''));
        $sourceId=trim((string)($claim['source_id']??''));
        $sourceUrl=trim((string)($claim['source_url']??''));
        $observedAt=trim((string)($claim['observed_at']??''));
        $validUntil=trim((string)($claim['valid_until']??''));

        if ($pageKey===''||$claimKey===''||$value===''||$sourceId===''||$sourceUrl==='') { $blocked[]=['index'=>$index,'code'=>'missing_required_field']; continue; }
        if (!in_array($type,$allowedTypes,true)) { $blocked[]=['index'=>$index,'code'=>'unsupported_claim_type']; continue; }
        if (!in_array($sourceClass,$allowedSources,true)) { $blocked[]=['index'=>$index,'code'=>'untrusted_source_class']; continue; }

        $parts=parse_url($sourceUrl);
        $scheme=strtolower((string)($parts['scheme']??''));
        $host=strtolower((string)($parts['host']??''));
        if ($scheme!=='https'||$host===''||isset($parts['user'])||isset($parts['pass'])) { $blocked[]=['index'=>$index,'code'=>'invalid_source_url']; continue; }
        if ($allowedSourceHosts===[]||!in_array($host,$allowedSourceHosts,true)) { $blocked[]=['index'=>$index,'code'=>'unverified_source_host']; continue; }

        $identity=$pageKey.'|'.$claimKey;
        if (isset($seen[$identity])) { $blocked[]=['index'=>$index,'code'=>'duplicate_claim_identity']; continue; }
        $seen[$identity]=true;

        $observedEpoch=$observedAt!==''?strtotime($observedAt):false;
        if ($observedEpoch===false||$observedEpoch>$nowEpoch) { $blocked[]=['index'=>$index,'code'=>'invalid_observed_at']; continue; }
        $validUntilEpoch=$validUntil!==''?strtotime($validUntil):false;
        if (in_array($type,$volatileTypes,true)) {
            if ($validUntilEpoch===false) { $blocked[]=['index'=>$index,'code'=>'missing_volatile_expiry']; continue; }
            if ($validUntilEpoch<=$nowEpoch) { $blocked[]=['index'=>$index,'code'=>'expired_claim_evidence']; continue; }
        } elseif ($validUntil!==''&&($validUntilEpoch===false||$validUntilEpoch<=$nowEpoch)) { $blocked[]=['index'=>$index,'code'=>'expired_claim_evidence']; continue; }

        $normalized[]=[
            'page_key'=>$pageKey,'claim_key'=>$claimKey,'type'=>$type,'value'=>$value,
            'source_class'=>$sourceClass,'source_id'=>$sourceId,'source_url'=>$sourceUrl,'source_host'=>$host,
            'observed_at'=>gmdate('c',$observedEpoch),'valid_until'=>$validUntilEpoch===false?null:gmdate('c',$validUntilEpoch),
        ];
    }

    return [
        'state'=>$blocked===[]&&$normalized!==[]?'review_ready':'blocked',
        'review_ready'=>$blocked===[]&&$normalized!==[],
        'claims'=>$normalized,'blocked'=>$blocked,
        'publication_allowed'=>false,'copy_allowed_without_evidence'=>false,'hotel_tours_publication_allowed'=>false,
    ];
}
