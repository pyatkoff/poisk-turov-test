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
    if (array_keys($registry) !== array_keys($reports) || array_keys($registry) !== array_keys($graph)) {
        $errors[] = 'registry_report_graph_parity';
    }

    $candidateSet = [];
    foreach ($candidates as $path) {
        $path = (string)$path;
        if ($path === '' || isset($candidateSet[$path]) || !isset($registry[$path])) $errors[] = 'invalid_publication_candidate';
        $candidateSet[$path] = true;
        if (($registry[$path]['type'] ?? '') === 'hotel_tours') $errors[] = 'hotel_tours_publication_candidate_leak';
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
        $identity = '';
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
    foreach ($readiness as $row) {
        if (!is_array($row)) { $errors[]='invalid_readiness_row'; continue; }
        $path=(string)($row['path']??''); $type=(string)($row['type']??'');
        if ($path==='' || isset($seenReadiness[$path]) || !isset($registry[$path]) || ($registry[$path]['type']??'')!==$type) $errors[]='readiness_identity_mismatch';
        $seenReadiness[$path]=true;
        if (isset($readyByType[$type])) {
            if (($row['ready_for_launch_review']??false)===true) $readyByType[$type]++; else $blockedByType[$type]++;
        }
        if ($type==='hotel_tours' && (int)($row['evidence_expires_epoch']??0)>0) $hotelEvidenceValidUntil[]=(int)$row['evidence_expires_epoch'];
    }

    usort($structuralRows, static fn(array $a,array $b):int => strcmp($a['path'],$b['path']));
    $fingerprintPayload = [
        'rows'=>$structuralRows,
        'publication_candidates'=>array_values(array_map('strval',$candidates)),
        'type_counts'=>$typeCounts,
    ];
    $fingerprint = hash('sha256', json_encode($fingerprintPayload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
    $errors = array_values(array_unique($errors));

    return [
        'state'=>$errors===[]?'review_manifest_valid':'review_manifest_blocked',
        'integrity_ok'=>$errors===[],
        'registry_count'=>count($registry),
        'type_counts'=>$typeCounts,
        'ready_by_type'=>$readyByType,
        'blocked_by_type'=>$blockedByType,
        'readiness_summary'=>$readinessSummary,
        'publication_candidate_count'=>count($candidates),
        'hotel_tours_publication_candidate_count'=>count(array_filter($candidates,static fn($p):bool=>isset($registry[$p])&&($registry[$p]['type']??'')==='hotel_tours')),
        'hotel_evidence_valid_until_epoch'=>$hotelEvidenceValidUntil===[]?0:min($hotelEvidenceValidUntil),
        'manifest_sha256'=>$fingerprint,
        'errors'=>$errors,
        'publication_allowed'=>false,
        'hotel_tours_publication_allowed'=>false,
        'hotel_tours_indexation_allowed'=>false,
    ];
}
