<?php
declare(strict_types=1);
if (!defined('FC_LIBRARY_ONLY')) define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/hotel_match_current_bulk_review.php';

const MSR_OPERATION = 'hotel-match-current-strict-review-1971-20260911-v3';

function msr_fuzzy_has_direct_geo(array $row): bool {
    $distance = $row['guard']['distance_m'] ?? null;
    if ($distance !== null && (int)$distance <= 1000) return true;
    $target = $row['target'] ?? [];
    return fc_place(
        array_map('strval', $row['source_places'] ?? []),
        [(string)($target['region'] ?? ''), (string)($target['subregion'] ?? '')]
    );
}

function msr_safe_auto(array $row): bool {
    if (($row['bucket'] ?? '') !== 'auto_accept') return false;
    if (($row['reason'] ?? '') === 'strong_fuzzy_geo_large_margin') {
        return msr_fuzzy_has_direct_geo($row);
    }
    return true;
}

function msr_bridge_index(array $localSet, array $hotels, array $names): array {
    $index = [];
    foreach (array_keys($localSet) as $id) {
        $id = (int)$id;
        if (!isset($hotels[$id])) continue;
        $country = (int)$hotels[$id]['country_id'];
        foreach ($names[$id] ?? [$hotels[$id]['name']] as $name) {
            $key = fc_key($name, true, false);
            if ($key !== '') $index[$country][$key][$id] = true;
        }
    }
    return $index;
}

function msr_cross_provider_bridge(array $review, array $coordSource, array $bridgeIndex, array $hotels): ?array {
    $country = (int)($review['country_id'] ?? 0);
    $candidateIds = [];
    $maxTokens = 0;
    foreach (array_map('strval', $review['source_names'] ?? []) as $name) {
        $tokens = fc_tokens($name, true);
        $maxTokens = max($maxTokens, count($tokens));
        $key = fc_key($name, true, false);
        if ($key === '') continue;
        foreach (array_keys($bridgeIndex[$country][$key] ?? []) as $id) $candidateIds[(int)$id] = true;
    }
    if (count($candidateIds) !== 1) return null;
    $targetId = (int)array_key_first($candidateIds);
    $target = $hotels[$targetId] ?? null;
    if (!is_array($target)) return null;
    $guard = mbr_target_guard($coordSource, $target);
    if ($guard['coordinate_conflict']) return null;
    $place = fc_place(
        array_map('strval', $review['source_places'] ?? []),
        [(string)($target['region_name'] ?? ''), (string)($target['subregion_name'] ?? '')]
    );
    $directGeo = ($guard['distance_m'] !== null && $guard['distance_m'] <= 1000) || $place;
    if ($maxTokens < 2 && !$directGeo) return null;
    $review['bucket'] = 'auto_accept';
    $review['reason'] = 'cross_provider_anex_tourvisor_strict_name';
    $review['guard'] = $guard;
    $review['target'] = mbr_row_target($target);
    $review['bridge'] = [
        'anex_tourvisor_existing_local' => true,
        'significant_tokens' => $maxTokens,
        'direct_geo' => $directGeo,
    ];
    return $review;
}

function msr_reverse_anex_bridge(array $review, array $coordSource, array $bridgeIndex, array $hotels): ?array {
    $country = (int)($review['country_id'] ?? 0);
    $candidateIds = [];
    $maxTokens = 0;
    foreach (array_map('strval', $review['source_names'] ?? []) as $name) {
        $tokens = fc_tokens($name, true);
        $maxTokens = max($maxTokens, count($tokens));
        $key = fc_key($name, true, false);
        if ($key === '') continue;
        foreach (array_keys($bridgeIndex[$country][$key] ?? []) as $id) $candidateIds[(int)$id] = true;
    }
    if (count($candidateIds) !== 1) return null;
    $targetId = (int)array_key_first($candidateIds);
    $target = $hotels[$targetId] ?? null;
    if (!is_array($target)) return null;
    $guard = mbr_target_guard($coordSource, $target);
    if ($guard['coordinate_conflict']) return null;
    $place = fc_place(
        array_map('strval', $review['source_places'] ?? []),
        [(string)($target['region_name'] ?? ''), (string)($target['subregion_name'] ?? '')]
    );
    $directGeo = ($guard['distance_m'] !== null && $guard['distance_m'] <= 1000) || $place;
    if ($maxTokens < 2 && !$directGeo) return null;
    $review['bucket'] = 'auto_accept';
    $review['reason'] = 'cross_provider_andromeda_tourvisor_strict_name';
    $review['guard'] = $guard;
    $review['target'] = mbr_row_target($target);
    $review['bridge'] = [
        'andromeda_tourvisor_existing_local' => true,
        'significant_tokens' => $maxTokens,
        'direct_geo' => $directGeo,
    ];
    return $review;
}

function msr_review(PDO $db, string $operation): array {
    if ($operation !== MSR_OPERATION) throw new RuntimeException('operation_scope');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $db->exec('START TRANSACTION READ ONLY');
    try {
        $coverage = fc_coverage($db);
        [$hotels,$names,$strict,$broad,$places,$catalogScope] = mbr_catalog($db);
        $shaCountry = fc_sha_countries($db);
        [$anexLocal,$andromedaLocal] = mbr_local_sets($db);
        $anexBridgeIndex = msr_bridge_index($anexLocal, $hotels, $names);
        $andromedaBridgeIndex = msr_bridge_index($andromedaLocal, $hotels, $names);

        $manual = array_fill_keys(array_map('intval', $db->query('SELECT anex_hotel_id FROM anex_hotel_decisions')->fetchAll(PDO::FETCH_COLUMN)), true);
        $existing = array_fill_keys(array_map('intval', $db->query('SELECT anex_hotel_id FROM anex_hotel_search_mappings')->fetchAll(PDO::FETCH_COLUMN)), true);
        $excluded = [];
        foreach ($db->query('SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions')->fetchAll(PDO::FETCH_ASSOC) as $x) {
            $excluded[(int)$x['anex_hotel_id']][(int)$x['catalog_hotel_id']] = true;
        }
        $staging = [];
        foreach ($db->query('SELECT * FROM anex_hotels ORDER BY anex_hotel_id')->fetchAll(PDO::FETCH_ASSOC) as $s) {
            $staging[(int)$s['anex_hotel_id']] = $s;
        }

        $candidates = [];
        $blocked = [];
        $stats = [
            'anex_observed_examined'=>0,
            'anex_staging_examined'=>0,
            'anex_protected'=>0,
            'andromeda_pending_examined'=>0,
            'andromeda_country_unknown'=>0,
            'unsafe_fuzzy_demoted'=>0,
            'pair_exclusion_blocked'=>0,
            'reverse_bridge_pair_exclusion_blocked'=>0,
        ];
        $seenAnex = [];

        $consider = static function(array $row, string $class) use (&$candidates,&$blocked,&$stats): void {
            if (msr_safe_auto($row)) {
                $row['candidate_class'] = $class;
                $candidates[] = $row;
                return;
            }
            if (($row['bucket'] ?? '') === 'auto_accept') {
                $row['bucket'] = 'needs_extra_evidence';
                $row['reason_before_guard'] = $row['reason'] ?? null;
                $row['reason'] = 'fuzzy_without_direct_geo';
                $stats['unsafe_fuzzy_demoted']++;
            }
            $blocked[] = $row;
        };

        $considerAnex = static function(array $row, array $source, int $id) use (&$candidates,&$blocked,&$stats,$andromedaBridgeIndex,$hotels,$excluded,$consider): void {
            if (msr_safe_auto($row)) {
                $consider($row, 'strict_current_rule');
                return;
            }
            $bridge = msr_reverse_anex_bridge($row, $source, $andromedaBridgeIndex, $hotels);
            if ($bridge !== null) {
                $target = (int)($bridge['target']['local_hotel_id'] ?? 0);
                if ($target && isset($excluded[$id][$target])) {
                    $bridge['bucket'] = 'hard_conflict';
                    $bridge['reason'] = 'pair_exclusion_protected';
                    $stats['reverse_bridge_pair_exclusion_blocked']++;
                    $blocked[] = $bridge;
                    return;
                }
                $consider($bridge, 'cross_provider_andromeda_tourvisor');
                return;
            }
            $consider($row, 'strict_current_rule');
        };

        $observations = $db->query('SELECT * FROM anex_search_hotel_observations ORDER BY search_count DESC,last_seen_utc DESC,anex_hotel_id')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($observations as $o) {
            $id=(int)$o['anex_hotel_id']; $country=(int)$o['country_id'];
            if (!isset(MBR_CORE8[$country])) continue;
            $stats['anex_observed_examined']++; $seenAnex[$id]=true;
            if (isset($manual[$id]) || isset($existing[$id])) { $stats['anex_protected']++; continue; }
            $s=$staging[$id]??[];
            $source=[
                'observed'=>true,'search_count'=>(int)$o['search_count'],'last_seen_utc'=>$o['last_seen_utc'],
                'names'=>[$o['hotel_name'],$s['api_name']??'',$s['xml_name']??'',$s['xml_alternate_name']??''],
                'places'=>[$s['api_region']??'',$s['api_town']??''],
                'latitude'=>$s['latitude']??null,'longitude'=>$s['longitude']??null,
            ];
            $row=mbr_review_anex($source,$id,$country,$hotels,$names,$strict,$broad,$places);
            $target=(int)($row['target']['local_hotel_id']??0);
            if ($target && isset($excluded[$id][$target])) {
                $row['bucket']='hard_conflict'; $row['reason']='pair_exclusion_protected';
                $stats['pair_exclusion_blocked']++; $blocked[]=$row; continue;
            }
            $considerAnex($row,$source,$id);
        }

        foreach ($staging as $id=>$s) {
            if (isset($seenAnex[$id]) || isset($manual[$id]) || isset($existing[$id])) continue;
            $country=fc_country($s['api_country']??'');
            if (!$country || !isset(MBR_CORE8[$country])) continue;
            $stats['anex_staging_examined']++;
            $source=[
                'observed'=>false,'search_count'=>0,
                'names'=>[$s['api_name']??'',$s['xml_name']??'',$s['xml_alternate_name']??''],
                'places'=>[$s['api_region']??'',$s['api_town']??''],
                'latitude'=>$s['latitude']??null,'longitude'=>$s['longitude']??null,
            ];
            $row=mbr_review_anex($source,(int)$id,$country,$hotels,$names,$strict,$broad,$places);
            $target=(int)($row['target']['local_hotel_id']??0);
            if ($target && isset($excluded[(int)$id][$target])) {
                $row['bucket']='hard_conflict'; $row['reason']='pair_exclusion_protected';
                $stats['pair_exclusion_blocked']++; $blocked[]=$row; continue;
            }
            $considerAnex($row,$source,(int)$id);
        }

        $latest=[];
        foreach ($db->query("SELECT * FROM andromeda_search_hotel_observations WHERE supplier_namespace='andromeda_catalog' ORDER BY observed_at_utc DESC,external_hotel_id")->fetchAll(PDO::FETCH_ASSOC) as $o) {
            $external=(string)$o['external_hotel_id']; if (!isset($latest[$external])) $latest[$external]=$o;
        }
        $pending=$db->query("SELECT * FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='pending' AND local_hotel_id IS NULL ORDER BY external_hotel_id")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($pending as $r) {
            $external=(string)$r['external_hotel_id']; $obs=$latest[$external]??null;
            $country=(int)($obs['country_id']??0);
            if (!isset(MBR_CORE8[$country])) $country=(int)($shaCountry[$r['catalog_sha256']]??0);
            if (!isset(MBR_CORE8[$country])) { $stats['andromeda_country_unknown']++; continue; }
            $stats['andromeda_pending_examined']++;
            $row=mbr_review_andromeda($r,$obs,$country,$hotels,$names,$strict,$places);
            if (msr_safe_auto($row)) {
                $row['candidate_class']='strict_current_rule'; $candidates[]=$row; continue;
            }
            $prior=fc_evidence($r['evidence_json']??'');
            $coordSource=$prior['source']??[]; if (!is_array($coordSource)) $coordSource=[];
            if ($obs) $coordSource += $obs;
            $bridge=msr_cross_provider_bridge($row,$coordSource,$anexBridgeIndex,$hotels);
            if ($bridge!==null && msr_safe_auto($bridge)) {
                $bridge['candidate_class']='cross_provider_anex_tourvisor'; $candidates[]=$bridge; continue;
            }
            if (($row['bucket']??'')==='auto_accept') {
                $row['bucket']='needs_extra_evidence';
                $row['reason_before_guard']=$row['reason']??null;
                $row['reason']='fuzzy_without_direct_geo';
                $stats['unsafe_fuzzy_demoted']++;
            }
            $blocked[]=$row;
        }

        $strictCount=count(array_filter($candidates,static fn($r)=>($r['candidate_class']??'')==='strict_current_rule'));
        $bridgeCount=count(array_filter($candidates,static fn($r)=>($r['candidate_class']??'')==='cross_provider_anex_tourvisor'));
        $reverseBridgeCount=count(array_filter($candidates,static fn($r)=>($r['candidate_class']??'')==='cross_provider_andromeda_tourvisor'));
        $providerCounts=['anex'=>0,'andromeda'=>0];
        foreach($candidates as $row){$p=(string)($row['provider']??'');if(isset($providerCounts[$p]))$providerCounts[$p]++;}
        $blockedReasons=[];foreach($blocked as $row){$reason=(string)($row['reason']??'unknown');$blockedReasons[$reason]=($blockedReasons[$reason]??0)+1;}ksort($blockedReasons);

        $anexOnly=array_diff_key($anexLocal,$andromedaLocal);
        $andromedaOnly=array_diff_key($andromedaLocal,$anexLocal);
        $missingThird=['anex_tv_only_core8'=>0,'andromeda_tv_only_core8'=>0];
        foreach(array_keys($anexOnly) as $id) if(isset($hotels[(int)$id])) $missingThird['anex_tv_only_core8']++;
        foreach(array_keys($andromedaOnly) as $id) if(isset($hotels[(int)$id])) $missingThird['andromeda_tv_only_core8']++;

        $db->commit();
        return [
            'status'=>'completed','operation_id'=>$operation,'mode'=>'current_db_consistent_snapshot_strict_read_only',
            'database_writes'=>0,'supplier_calls'=>0,'catalog_scope'=>$catalogScope,'coverage'=>$coverage,
            'counts'=>[
                'safe_candidates'=>count($candidates),'strict_current_rule'=>$strictCount,
                'cross_provider_anex_tourvisor'=>$bridgeCount,
                'cross_provider_andromeda_tourvisor'=>$reverseBridgeCount,
                'candidate_anex'=>$providerCounts['anex'],
                'candidate_andromeda'=>$providerCounts['andromeda'],'blocked'=>count($blocked),
                'unsafe_fuzzy_demoted'=>$stats['unsafe_fuzzy_demoted'],
            ]+$missingThird,
            'examined'=>$stats,'blocked_reasons'=>$blockedReasons,'candidates'=>$candidates,
            'guards'=>[
                'core8_only'=>true,'database_write'=>false,'supplier_calls'=>0,
                'manual_decisions_overwritten'=>false,'pair_exclusions_overwritten'=>false,
                'existing_mappings_overwritten'=>false,'coordinate_conflict_auto_block_m'=>5000,
                'strong_fuzzy_requires_direct_geo'=>true,'numeric_star_is_guard_not_identity'=>true,
                'cross_provider_requires_existing_anex_tourvisor_local'=>true,
                'reverse_cross_provider_requires_existing_andromeda_tourvisor_local'=>true,
                'generic_hotel_resort_spa_removed_but_qualifiers_preserved'=>true,
                'single_token_bridge_requires_direct_geo'=>true,
            ],
        ];
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        return ['status'=>'failed','operation_id'=>$operation,'database_writes'=>0,'supplier_calls'=>0,'reason'=>in_array($e->getMessage(),['operation_scope','country_contract_changed','hotel_scope_limit','alias_scope_limit'],true)?$e->getMessage():'runtime_failure'];
    }
}
