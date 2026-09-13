<?php
declare(strict_types=1);

/** MATCH #1971 read-only evidence library. Requires the current strict review library. */
function hmss_star(array $source): ?array
{
    foreach (['category','star','stars','starName','star_name'] as $key) {
        if (!array_key_exists($key, $source)) continue;
        $raw = trim((string)$source[$key]);
        if (preg_match('/^([1-5])(?:\s*(?:\*|★|stars?))?$/iu', $raw, $m)) {
            return ['field'=>$key, 'raw'=>$raw, 'numeric'=>(int)$m[1]];
        }
    }
    return null;
}

function hmss_evidence($raw): array
{
    if (!is_string($raw) || trim($raw) === '') return [];
    $v = json_decode($raw, true);
    return is_array($v) ? $v : [];
}

function hmss_audit(PDO $db, string $strictOperation, string $operation): array
{
    $strict = msr_review($db, $strictOperation);
    if (($strict['status'] ?? '') !== 'completed') throw new RuntimeException('strict_failed');
    $mismatch = array_values(array_filter(
        $strict['blocked_rows'] ?? [],
        static fn($r) => ($r['provider'] ?? '') === 'andromeda' && ($r['reason'] ?? '') === 'numeric_star_guard_mismatch'
    ));

    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $db->exec('START TRANSACTION READ ONLY');
    try {
        $latest = [];
        foreach ($db->query("SELECT * FROM andromeda_search_hotel_observations WHERE supplier_namespace='andromeda_catalog' ORDER BY observed_at_utc DESC,external_hotel_id")->fetchAll(PDO::FETCH_ASSOC) as $o) {
            $external = (string)$o['external_hotel_id'];
            if (!isset($latest[$external])) $latest[$external] = $o;
        }
        $rows = $db->query("SELECT i.external_hotel_id,i.local_hotel_id,i.decision_status,i.evidence_json,i.catalog_sha256,h.country_id,h.category FROM andromeda_hotel_identities i LEFT JOIN catalog_hotels h ON h.id=i.local_hotel_id WHERE i.supplier_namespace='andromeda_catalog' ORDER BY i.decision_status,i.external_hotel_id")->fetchAll(PDO::FETCH_ASSOC);

        $accepted = [
            'total'=>0,'with_numeric'=>0,'agree'=>0,'mismatch'=>0,'missing_local_category'=>0,
            'by_field'=>[],'difference_histogram'=>[],'starKey_values'=>[],'starKey_gt5'=>0,
        ];
        $identity = [];
        foreach ($rows as $r) {
            $external = (string)$r['external_hotel_id'];
            $identity[$external] = $r;
            if (($r['decision_status'] ?? '') !== 'accepted' || !$r['local_hotel_id']) continue;
            $country = (int)($r['country_id'] ?? 0);
            if (!isset(MBR_CORE8[$country])) continue;
            $accepted['total']++;
            $ev = hmss_evidence($r['evidence_json'] ?? '');
            $src = $ev['source'] ?? [];
            if (!is_array($src)) $src = [];
            $obs = $latest[$external] ?? [];
            $star = hmss_star($src) ?? hmss_star(is_array($obs) ? $obs : []);
            $starKey = $src['starKey'] ?? ($obs['starKey'] ?? null);
            if ($starKey !== null && $starKey !== '') {
                $key = (string)$starKey;
                $accepted['starKey_values'][$key] = ($accepted['starKey_values'][$key] ?? 0) + 1;
                if (is_numeric($starKey) && (int)$starKey > 5) $accepted['starKey_gt5']++;
            }
            if ($star === null) continue;
            $accepted['with_numeric']++;
            $field = $star['field'];
            $accepted['by_field'][$field]['total'] = ($accepted['by_field'][$field]['total'] ?? 0) + 1;
            $localCategory = $r['category'] === null ? null : (int)$r['category'];
            if ($localCategory === null) { $accepted['missing_local_category']++; continue; }
            $diff = $star['numeric'] - $localCategory;
            $diffKey = (string)$diff;
            $accepted['difference_histogram'][$diffKey] = ($accepted['difference_histogram'][$diffKey] ?? 0) + 1;
            if ($diff === 0) {
                $accepted['agree']++;
                $accepted['by_field'][$field]['agree'] = ($accepted['by_field'][$field]['agree'] ?? 0) + 1;
            } else {
                $accepted['mismatch']++;
                $accepted['by_field'][$field]['mismatch'] = ($accepted['by_field'][$field]['mismatch'] ?? 0) + 1;
            }
        }
        ksort($accepted['by_field']);
        ksort($accepted['difference_histogram'], SORT_NUMERIC);
        arsort($accepted['starKey_values']);
        $accepted['starKey_values'] = array_slice($accepted['starKey_values'], 0, 30, true);

        $cases = [];
        $kind = ['strict_unique'=>0, 'ranked'=>0];
        $fields = [];
        $diffs = [];
        foreach ($mismatch as $r) {
            $external = (string)$r['external_id'];
            $id = $identity[$external] ?? [];
            $ev = hmss_evidence($id['evidence_json'] ?? '');
            $src = $ev['source'] ?? [];
            if (!is_array($src)) $src = [];
            $obs = $latest[$external] ?? [];
            $star = hmss_star($src) ?? hmss_star(is_array($obs) ? $obs : []);
            $field = $star['field'] ?? 'unknown';
            $raw = $star['raw'] ?? null;
            $numeric = $star['numeric'] ?? ($r['source_category'] ?? null);
            $target = $r['target'] ?? [];
            $targetCategory = $target['category'] ?? null;
            $difference = ($numeric !== null && $targetCategory !== null) ? ((int)$numeric - (int)$targetCategory) : null;
            $diffKey = $difference === null ? 'null' : (string)$difference;
            $diffs[$diffKey] = ($diffs[$diffKey] ?? 0) + 1;
            $fields[$field] = ($fields[$field] ?? 0) + 1;
            $matchKind = isset($r['best']) ? 'ranked' : 'strict_unique';
            $kind[$matchKind]++;
            $cases[] = [
                'external_id'=>$external,'country_id'=>(int)($r['country_id'] ?? 0),
                'source_names'=>$r['source_names'] ?? [],'source_places'=>$r['source_places'] ?? [],
                'star_field'=>$field,'star_raw'=>$raw,'source_category'=>$numeric,
                'target_category'=>$targetCategory,'category_difference'=>$difference,
                'match_kind'=>$matchKind,'best'=>$r['best'] ?? null,'margin'=>$r['margin'] ?? null,
                'target'=>$target,'catalog_sha256'=>$r['catalog_sha256'] ?? null,
            ];
        }
        ksort($fields);
        ksort($diffs, SORT_NUMERIC);
        usort($cases, static fn($a,$b) => $a['country_id'] <=> $b['country_id'] ?: strcmp($a['match_kind'],$b['match_kind']) ?: strcmp($a['external_id'],$b['external_id']));
        $db->commit();

        return [
            'schema'=>'hotel-match-andromeda-star-semantics/1','status'=>'completed','operation_id'=>$operation,
            'strict_operation_id'=>$strictOperation,'mode'=>'current_db_read_only','database_writes'=>0,'mapping_writes'=>0,
            'supplier_calls'=>0,'tourvisor_calls'=>0,'accepted_semantics'=>$accepted,
            'numeric_star_guard_mismatch_count'=>count($mismatch),'mismatch_match_kind'=>$kind,
            'mismatch_field_counts'=>$fields,'mismatch_difference_histogram'=>$diffs,'mismatch_rows'=>$cases,
            'strict_blocked_reasons'=>$strict['blocked_reasons'] ?? [],'strict_candidates_count'=>count($strict['candidates'] ?? []),
            'guards'=>[
                'star_equality_is_not_identity_authority'=>true,'no_matching_semantics_changed'=>true,
                'accepted_manual_exclusions_mappings_unchanged'=>true,'separate_read_only_snapshots'=>true,
            ],
        ];
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
}
