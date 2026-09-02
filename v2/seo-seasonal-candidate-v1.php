<?php
require_once __DIR__ . '/seo-seasonal-evidence-v1.php';

/**
 * Build a deterministic review-only inventory of monthly data candidates.
 *
 * This is not a page/publication generator and intentionally carries no prices,
 * rankings, discounts, climate claims or copy. Only fresh evidence identities
 * may enter the inventory.
 */
function v2_seo_seasonal_candidate_inventory(array $rows, ?int $nowEpoch = null): array
{
    $nowEpoch ??= time();
    $candidates = [];
    $blocked = [];

    foreach ($rows as $row) {
        if (!is_array($row)) {
            $blocked[] = ['errors'=>['invalid_item']];
            continue;
        }
        if (isset($row['page_type'])) $row = v2_seo_seasonal_evidence_from_snapshot($row);
        $assessment = v2_seo_seasonal_evidence_assess($row, $nowEpoch);
        if (($assessment['usable'] ?? false) !== true) {
            $blocked[] = [
                'page_key'=>(string)($assessment['page_key'] ?? ''),
                'errors'=>$assessment['errors'] ?? ['blocked'],
            ];
            continue;
        }

        $key = (string)$assessment['page_key'];
        $candidate = [
            'state'=>'review_only_data_candidate',
            'scope'=>(string)$assessment['scope'],
            'page_key'=>$key,
            'country_id'=>(int)$assessment['country_id'],
            'region_id'=>$assessment['region_id'],
            'departure_id'=>(int)$assessment['departure_id'],
            'year'=>(int)$assessment['year'],
            'month'=>(int)$assessment['month'],
            'offer_count'=>(int)$assessment['offer_count'],
            'freshness_seconds'=>(int)$assessment['freshness_seconds'],
            'publication_allowed'=>false,
            'copy_allowed'=>false,
        ];

        // Multiple observations for one identity collapse deterministically to
        // the evidence with the longest remaining freshness window.
        if (!isset($candidates[$key]) || $candidate['freshness_seconds'] > $candidates[$key]['freshness_seconds']) {
            $candidates[$key] = $candidate;
        }
    }

    ksort($candidates, SORT_STRING);
    return [
        'state'=>'review_only_seasonal_candidate_inventory',
        'candidate_count'=>count($candidates),
        'blocked_count'=>count($blocked),
        'publication_candidates'=>[],
        'candidates'=>array_values($candidates),
        'blocked'=>$blocked,
    ];
}
