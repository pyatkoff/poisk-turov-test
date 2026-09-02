<?php
require_once __DIR__ . '/seo-hotel-launch-candidate-v1.php';

/**
 * Wrap an explicit country-balanced hotel proposal in a hard review-only boundary.
 *
 * The word "launch" here means human launch review only. This object cannot be
 * consumed as a publication/indexation/sitemap/canonical candidate and deliberately
 * requires a separate explicit user approval before any such policy may exist.
 */
function v2_seo_hotel_review_launch_slice(
    array $readinessRows,
    array $countryBuckets,
    array $requiredCountryIds = [4, 8, 1],
    int $maxPerCountry = 5,
    int $maxTotal = 15,
    ?int $nowEpoch = null
): array {
    $proposal = v2_seo_hotel_country_launch_slice_proposal(
        $readinessRows,
        $countryBuckets,
        $requiredCountryIds,
        $maxPerCountry,
        $maxTotal,
        $nowEpoch
    );

    $identityRows=[];
    foreach (($proposal['proposal'] ?? []) as $row) {
        if (!is_array($row)) throw new InvalidArgumentException('Hotel review launch slice proposal row must be an array');
        $path=trim((string)($row['path']??''));
        $countryId=(int)($row['country_id']??0);
        $hotelId=(int)($row['hotel_id']??0);
        $expires=(int)($row['evidence_expires_epoch']??0);
        if ($path==='' || $countryId<=0 || $hotelId<=0 || $expires<=0 || (int)($row['score']??0)!==100) {
            throw new InvalidArgumentException('Hotel review launch slice contains incomplete readiness identity');
        }
        $identityRows[]=['path'=>$path,'country_id'=>$countryId,'hotel_id'=>$hotelId,'score'=>100,'evidence_expires_epoch'=>$expires];
    }
    usort($identityRows,static fn(array $a,array $b):int=>strcmp($a['path'],$b['path']));
    $fingerprint=hash('sha256',json_encode($identityRows,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));

    return [
        'state'=>'review_only_requires_separate_indexation_approval',
        'validated_at_epoch'=>(int)($proposal['validated_at_epoch']??0),
        'required_country_ids'=>array_values($proposal['required_country_ids']??[]),
        'max_per_country'=>(int)($proposal['max_per_country']??0),
        'max_total'=>(int)($proposal['max_total']??0),
        'total'=>(int)($proposal['total']??0),
        'countries'=>$proposal['countries']??[],
        'review_items'=>$proposal['proposal']??[],
        'evidence_manifest_sha256'=>$fingerprint,
        'publication_candidates'=>[],
        'publication_allowed'=>false,
        'indexation_allowed'=>false,
        'sitemap_allowed'=>false,
        'canonical_launch_allowed'=>false,
        'route_launch_allowed'=>false,
        'explicit_user_indexation_approval_required'=>true,
    ];
}
