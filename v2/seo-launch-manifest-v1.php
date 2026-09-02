<?php
require_once __DIR__ . '/seo-page-launch-readiness-v1.php';

/**
 * Build one fail-closed review manifest across country, resort and hotel-tour pages.
 * This is diagnostics only. It never changes routes, editorial state, robots,
 * canonicals, sitemap output, launch flags or publication candidates.
 */
function v2_seo_launch_manifest(array $catalog, array $hotelSnapshotEvidence = [], ?int $nowEpoch = null): array
{
    $nowEpoch ??= time();
    $registry = is_array($catalog['registry'] ?? null) ? $catalog['registry'] : [];
    $reports = is_array($catalog['reports'] ?? null) ? $catalog['reports'] : [];
    $graph = is_array($catalog['graph'] ?? null) ? $catalog['graph'] : [];
    $candidates = is_array($catalog['publication_candidates'] ?? null) ? array_values($catalog['publication_candidates']) : [];
    $errors = [];

    if ($registry === []) $errors[] = 'empty_registry';
    $registryKeys=array_keys($registry); $reportKeys=array_keys($reports); $graphKeys=array_keys($graph);
    sort($registryKeys,SORT_STRING); sort($reportKeys,SORT_STRING); sort($graphKeys,SORT_STRING);
    if ($registryKeys !== $reportKeys || $registryKeys !== $graphKeys) $errors[] = 'registry_report_graph_parity';

    $candidateSet = [];
    foreach ($candidates as $path) {
        $path = (string)$path;
        if ($path === '' || isset($candidateSet[$path]) || !isset($registry[$path])) $errors[] = 'invalid_publication_candidate';
        $candidateSet[$path] = true;
        if (isset($registry[$path]) && ($registry[$path]['type'] ?? '') === 'hotel_tours') $errors[] = 'hotel_tours_publication_candidate_leak';
    }

    $identitySet = [];
    $typeCounts = ['country'=>0,'resort'=>0,'hotel_tours'=>0];
    $structuralRows = [];
    foreach ($registry as $path => $entry) {
        $type = (string)($entry['type'] ?? '');
        if (!isset($typeCounts[$type])) continue;
        $typeCounts[$type]++;
        $page = is_array($entry['page'] ?? null) ? $entry['page'] : [];
        $state = is_array($page['search_state'] ?? null) ? $page['search_state'] : [];
        $countryId = (int)($state['country'] ?? 0);
        if ($type === 'country') $identity = 'country:' . $countryId;
        elseif ($type === 'resort') $identity = 'resort:' . $countryId . ':' . (int)($state['region'] ?? 0);
        else $identity = 'hotel:' . $countryId . ':' . (int)($state['hotel'] ?? 0);
        if ($countryId <= 0 || str_ends_with($identity, ':0') || isset($identitySet[$identity])) $errors[] = 'duplicate_or_invalid_search_identity';
        $identitySet[$identity] = true;

        $report = is_array($reports[$path] ?? null) ? $reports[$path] : [];
        if ($type === 'hotel_tours' && (($report['status'] ?? '') !== 'review' || isset($candidateSet[(string)$path]))) {
            $errors[] = 'hotel_tours_review_boundary';
        }
        $structuralRows[] = [
            'path'=>(string)$path,
            'type'=>$type,
            'identity'=>$identity,
            'status'=>(string)($report['status'] ?? ''),
            'publishable'=>(bool)($report['publishable'] ?? false),
            'parent'=>(string)($graph[$path]['parent'] ?? ''),
        ];
    }

    $readiness = v2_seo_page_launch_readiness($catalog, $hotelSnapshotEvidence, $nowEpoch);
    $expectedReadinessCount = array_sum($typeCounts);
    if (count($readiness) !== $expectedReadinessCount) $errors[] = 'readiness_registry_parity';
    $readinessSummary = v2_seo_page_launch_readiness_summary($readiness);

    $seenReadiness = [];
    $readyByType = ['country'=>0,'resort'=>0,'hotel_tours'=>0];
    $blockedByType = ['country'=>0,'resort'=>0,'hotel_tours'=>0];
    $hotelEvidenceValidUntil = [];
    $scoreTotal=0; $scoreRows=0;
    foreach ($readiness as $row) {
        if (!is_array($row)) { $errors[]='invalid_readiness_row'; continue; }
        $path=(string)($row['path']??''); $type=(string)($row['type']??'');
        if ($path==='' || isset($seenReadiness[$path]) || !isset($registry[$path]) || ($registry[$path]['type']??'')!==$type) $errors[]='readiness_identity_mismatch';
        $seenReadiness[$path]=true;
        if (isset($readyByType[$type])) {
            if (($row['ready_for_launch_review']??false)===true) $readyByType[$type]++; else $blockedByType[$type]++;
            $scoreTotal+=max(0,min(100,(int)($row['score']??0))); $scoreRows++;
        }
        if ($type==='hotel_tours' && (int)($row['evidence_expires_epoch']??0)>0) $hotelEvidenceValidUntil[]=(int)$row['evidence_expires_epoch'];
    }

    usort($structuralRows, static fn(array $a,array $b):int => strcmp($a['path'],$b['path']));
    $normalizedCandidates=array_values(array_map('strval',$candidates)); sort($normalizedCandidates,SORT_STRING);
    $fingerprintPayload = ['rows'=>$structuralRows,'publication_candidates'=>$normalizedCandidates,'type_counts'=>$typeCounts];
    $fingerprint = hash('sha256', json_encode($fingerprintPayload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
    $errors = array_values(array_unique($errors));
    $readyCount=array_sum($readyByType); $blockedCount=array_sum($blockedByType);
    $qualityScore=$scoreRows>0?(int)round($scoreTotal/$scoreRows):0;
    $reviewReady=$errors===[] && $scoreRows>0 && $blockedCount===0;

    return [
        'state'=>$errors===[]?'review_manifest_valid':'review_manifest_blocked',
        'integrity_ok'=>$errors===[],
        'review_ready'=>$reviewReady,
        'quality_score'=>$qualityScore,
        'registry_count'=>count($registry),
        'readiness_row_count'=>$scoreRows,
        'ready_count'=>$readyCount,
        'blocked_count'=>$blockedCount,
        'type_counts'=>$typeCounts,
        'ready_by_type'=>$readyByType,
        'blocked_by_type'=>$blockedByType,
        'readiness_summary'=>$readinessSummary,
        'publication_candidate_count'=>count($candidates),
        'hotel_tours_publication_candidate_count'=>count(array_filter($candidates,static fn($p):bool=>isset($registry[$p])&&($registry[$p]['type']??'')==='hotel_tours')),
        'hotel_tours_review_ready_count'=>$readyByType['hotel_tours'],
        'hotel_evidence_valid_until_epoch'=>$hotelEvidenceValidUntil===[]?0:min($hotelEvidenceValidUntil),
        'manifest_sha256'=>$fingerprint,
        'errors'=>$errors,
        'publication_allowed'=>false,
        'hotel_tours_publication_allowed'=>false,
        'hotel_tours_indexation_allowed'=>false,
    ];
}
