<?php
declare(strict_types=1);

/**
 * Pure phase-2 reconciler for exact secondary provider identities.
 *
 * No I/O, no supplier calls, no database writes. The caller supplies:
 * - retained Tourvisor edges;
 * - current Andromeda unresolved observations;
 * - current accepted secondary identity rows;
 * - explicit blocked/excluded pairs;
 * - a freshness cutoff.
 *
 * Only FUN&SUN and Intourist have a proven cross-provider namespace contract here:
 *   Tourvisor 25 -> Andromeda operator_315
 *   Tourvisor 43 -> Andromeda operator_342
 * Biblio-Globus (Tourvisor 18) is deliberately HOLD until a separate exact
 * namespace bridge to Andromeda operator_115 is proven. Numeric equality alone
 * must never bridge provider namespaces.
 */
final class AnyTourMatchLive234SecondaryCrossSourceV1
{
    private const TV_TO_ANDROMEDA_NAMESPACE = [
        25 => 'operator_315',
        43 => 'operator_342',
    ];

    public static function reconcile(
        array $tvEdges,
        array $andromedaObservations,
        array $acceptedRows,
        array $blockedPairs,
        string $freshAfterUtc
    ): array {
        $cutoff = self::time($freshAfterUtc, 'fresh_cutoff_invalid');

        $observed = [];
        foreach ($andromedaObservations as $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException('observation_row_invalid');
            }
            $namespace = self::identifier($row['supplier_namespace'] ?? null, 'observation_namespace_invalid');
            $external = self::identifier($row['external_hotel_id'] ?? null, 'observation_external_invalid');
            $seenAt = self::time($row['observed_at_utc'] ?? null, 'observation_time_invalid');
            $key = $namespace . '|' . $external;
            if (!isset($observed[$key]) || $seenAt > $observed[$key]) {
                $observed[$key] = $seenAt;
            }
        }

        $acceptedBySource = [];
        $acceptedByTargetNamespace = [];
        foreach ($acceptedRows as $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException('accepted_row_invalid');
            }
            if (($row['decision_status'] ?? null) !== 'accepted') {
                continue;
            }
            $namespace = self::identifier($row['supplier_namespace'] ?? null, 'accepted_namespace_invalid');
            $external = self::identifier($row['external_hotel_id'] ?? null, 'accepted_external_invalid');
            $local = self::positiveInt($row['local_hotel_id'] ?? null, 'accepted_local_invalid');
            $sourceKey = $namespace . '|' . $external;
            if (isset($acceptedBySource[$sourceKey]) && $acceptedBySource[$sourceKey] !== $local) {
                throw new RuntimeException('accepted_source_collision');
            }
            $acceptedBySource[$sourceKey] = $local;
            $acceptedByTargetNamespace[$local . '|' . $namespace][$external] = true;
        }

        $blocked = [];
        foreach ($blockedPairs as $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException('blocked_row_invalid');
            }
            $namespace = self::identifier($row['supplier_namespace'] ?? null, 'blocked_namespace_invalid');
            $external = self::identifier($row['external_hotel_id'] ?? null, 'blocked_external_invalid');
            $local = self::positiveInt($row['local_hotel_id'] ?? null, 'blocked_local_invalid');
            $blocked[$namespace . '|' . $external . '|' . $local] = true;
        }

        $prepared = [];
        $sourceTargets = [];
        foreach ($tvEdges as $index => $edge) {
            if (!is_array($edge)) {
                throw new InvalidArgumentException('tv_edge_invalid');
            }
            $local = self::positiveInt($edge['tv_hotel_id'] ?? null, 'tv_local_invalid');
            $operator = self::positiveInt($edge['operator_id'] ?? null, 'tv_operator_invalid');

            $base = [
                'index' => $index,
                'tv_hotel_id' => $local,
                'operator_id' => $operator,
                'namespace' => null,
                'external_hotel_id' => null,
                'state' => null,
                'safe_to_write_now' => false,
            ];

            if (($edge['state'] ?? null) !== 'detail_identity_verified') {
                $base['state'] = 'tv_detail_not_verified';
                $prepared[] = $base;
                continue;
            }
            if (($edge['link_state'] ?? null) !== 'captured_single_native') {
                $base['state'] = 'tv_not_single_native';
                $prepared[] = $base;
                continue;
            }
            $ids = $edge['positive_native_candidates'] ?? null;
            if (!is_array($ids) || count($ids) !== 1) {
                $base['state'] = 'tv_native_shape_invalid';
                $prepared[] = $base;
                continue;
            }
            $external = self::identifier($ids[0], 'tv_native_invalid');
            $base['external_hotel_id'] = $external;

            if ($operator === 18) {
                $base['state'] = 'bg_namespace_bridge_unproven';
                $prepared[] = $base;
                continue;
            }
            $namespace = self::TV_TO_ANDROMEDA_NAMESPACE[$operator] ?? null;
            if ($namespace === null) {
                $base['state'] = 'operator_not_supported';
                $prepared[] = $base;
                continue;
            }
            $base['namespace'] = $namespace;
            $base['state'] = 'prepared';
            $sourceKey = $namespace . '|' . $external;
            $sourceTargets[$sourceKey][$local] = true;
            $prepared[] = $base;
        }

        $rows = [];
        $counts = [];
        foreach ($prepared as $row) {
            if ($row['state'] !== 'prepared') {
                $rows[] = self::counted($row, $counts);
                continue;
            }

            $local = $row['tv_hotel_id'];
            $namespace = $row['namespace'];
            $external = $row['external_hotel_id'];
            $sourceKey = $namespace . '|' . $external;

            if (count($sourceTargets[$sourceKey] ?? []) !== 1) {
                $row['state'] = 'tv_source_collision';
                $rows[] = self::counted($row, $counts);
                continue;
            }

            $observedAt = $observed[$sourceKey] ?? null;
            if (!$observedAt instanceof DateTimeImmutable) {
                $row['state'] = 'andromeda_exact_native_not_observed';
                $rows[] = self::counted($row, $counts);
                continue;
            }
            if ($observedAt < $cutoff) {
                $row['state'] = 'andromeda_exact_native_stale';
                $row['andromeda_observed_at_utc'] = $observedAt->format('Y-m-d H:i:s');
                $rows[] = self::counted($row, $counts);
                continue;
            }
            $row['andromeda_observed_at_utc'] = $observedAt->format('Y-m-d H:i:s');

            $blockedKey = $namespace . '|' . $external . '|' . $local;
            if (isset($blocked[$blockedKey])) {
                $row['state'] = 'manual_or_exclusion_block';
                $rows[] = self::counted($row, $counts);
                continue;
            }

            $acceptedLocal = $acceptedBySource[$sourceKey] ?? null;
            if ($acceptedLocal !== null && $acceptedLocal !== $local) {
                $row['state'] = 'source_occupied_other';
                $row['accepted_local_hotel_id'] = $acceptedLocal;
                $rows[] = self::counted($row, $counts);
                continue;
            }
            if ($acceptedLocal === $local) {
                $row['state'] = 'resolved_same_secondary';
                $rows[] = self::counted($row, $counts);
                continue;
            }

            $targetExisting = array_keys($acceptedByTargetNamespace[$local . '|' . $namespace] ?? []);
            if ($targetExisting !== []) {
                sort($targetExisting, SORT_NATURAL);
                $row['state'] = 'target_namespace_occupied_other';
                $row['accepted_external_hotel_ids'] = $targetExisting;
                $rows[] = self::counted($row, $counts);
                continue;
            }

            $row['state'] = 'writer_ready_exact_cross_source';
            $row['safe_to_write_now'] = false;
            $rows[] = self::counted($row, $counts);
        }

        ksort($counts);
        usort($rows, static fn(array $a, array $b): int =>
            [$a['tv_hotel_id'], $a['operator_id'], (string)($a['external_hotel_id'] ?? '')]
            <=>
            [$b['tv_hotel_id'], $b['operator_id'], (string)($b['external_hotel_id'] ?? '')]
        );

        $writerReady = array_values(array_filter(
            $rows,
            static fn(array $row): bool => $row['state'] === 'writer_ready_exact_cross_source'
        ));

        return [
            'state' => 'completed_read_only_cross_source_reconcile',
            'fresh_after_utc' => $cutoff->format('Y-m-d H:i:s'),
            'tv_edges_checked' => count($tvEdges),
            'andromeda_observation_keys' => count($observed),
            'state_counts' => $counts,
            'writer_ready_count' => count($writerReady),
            'writer_ready' => $writerReady,
            'rows' => $rows,
            'provider_http_calls' => 0,
            'database_writes' => 0,
            'mapping_writes' => 0,
            'safe_to_write_now' => false,
        ];
    }

    private static function counted(array $row, array &$counts): array
    {
        $state = (string)$row['state'];
        $counts[$state] = ($counts[$state] ?? 0) + 1;
        return $row;
    }

    private static function identifier(mixed $value, string $reason): string
    {
        if (is_int($value)) {
            $value = (string)$value;
        }
        if (!is_string($value) || preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $value) !== 1) {
            throw new InvalidArgumentException($reason);
        }
        return $value;
    }

    private static function positiveInt(mixed $value, string $reason): int
    {
        if (is_int($value)) {
            $value = (string)$value;
        }
        if (!is_string($value) || preg_match('/^[1-9][0-9]{0,9}$/D', $value) !== 1 || (float)$value > 2147483647) {
            throw new InvalidArgumentException($reason);
        }
        return (int)$value;
    }

    private static function time(mixed $value, string $reason): DateTimeImmutable
    {
        if (!is_string($value) || preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}$/D', $value) !== 1) {
            throw new InvalidArgumentException($reason);
        }
        $time = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, new DateTimeZone('UTC'));
        if (!$time || $time->format('Y-m-d H:i:s') !== $value) {
            throw new InvalidArgumentException($reason);
        }
        return $time;
    }
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    if (($argv[1] ?? '') !== '--self-test') {
        fwrite(STDERR, "disabled\n");
        exit(2);
    }

    $edges = [
        ['tv_hotel_id'=>101,'operator_id'=>25,'state'=>'detail_identity_verified','link_state'=>'captured_single_native','positive_native_candidates'=>['5001']],
        ['tv_hotel_id'=>102,'operator_id'=>43,'state'=>'detail_identity_verified','link_state'=>'captured_single_native','positive_native_candidates'=>['6001']],
        ['tv_hotel_id'=>103,'operator_id'=>18,'state'=>'detail_identity_verified','link_state'=>'captured_single_native','positive_native_candidates'=>['7001']],
        ['tv_hotel_id'=>104,'operator_id'=>25,'state'=>'detail_identity_verified','link_state'=>'captured_single_native','positive_native_candidates'=>['5004']],
        ['tv_hotel_id'=>105,'operator_id'=>25,'state'=>'detail_identity_verified','link_state'=>'captured_single_native','positive_native_candidates'=>['5004']],
        ['tv_hotel_id'=>106,'operator_id'=>43,'state'=>'detail_identity_verified','link_state'=>'captured_single_native','positive_native_candidates'=>['6006']],
        ['tv_hotel_id'=>107,'operator_id'=>43,'state'=>'detail_identity_verified','link_state'=>'captured_single_native','positive_native_candidates'=>['6007']],
        ['tv_hotel_id'=>108,'operator_id'=>25,'state'=>'detail_identity_verified','link_state'=>'captured_single_native','positive_native_candidates'=>['5008']],
    ];
    $obs = [
        ['supplier_namespace'=>'operator_315','external_hotel_id'=>'5001','observed_at_utc'=>'2026-09-23 09:30:00'],
        ['supplier_namespace'=>'operator_342','external_hotel_id'=>'6001','observed_at_utc'=>'2026-09-23 09:31:00'],
        ['supplier_namespace'=>'operator_315','external_hotel_id'=>'5004','observed_at_utc'=>'2026-09-23 09:32:00'],
        ['supplier_namespace'=>'operator_342','external_hotel_id'=>'6006','observed_at_utc'=>'2026-09-23 08:00:00'],
        ['supplier_namespace'=>'operator_342','external_hotel_id'=>'6007','observed_at_utc'=>'2026-09-23 09:33:00'],
        ['supplier_namespace'=>'operator_315','external_hotel_id'=>'5008','observed_at_utc'=>'2026-09-23 09:34:00'],
    ];
    $accepted = [
        ['supplier_namespace'=>'operator_342','external_hotel_id'=>'6007','local_hotel_id'=>'999','decision_status'=>'accepted'],
        ['supplier_namespace'=>'operator_315','external_hotel_id'=>'5999','local_hotel_id'=>'108','decision_status'=>'accepted'],
    ];
    $blocked = [
        ['supplier_namespace'=>'operator_342','external_hotel_id'=>'6001','local_hotel_id'=>'102'],
    ];
    $out = AnyTourMatchLive234SecondaryCrossSourceV1::reconcile(
        $edges, $obs, $accepted, $blocked, '2026-09-23 09:00:00'
    );

    $expected = [
        'bg_namespace_bridge_unproven'=>1,
        'manual_or_exclusion_block'=>1,
        'tv_source_collision'=>2,
        'andromeda_exact_native_stale'=>1,
        'source_occupied_other'=>1,
        'target_namespace_occupied_other'=>1,
        'writer_ready_exact_cross_source'=>1,
    ];
    ksort($expected);
    if ($out['state_counts'] !== $expected || $out['writer_ready_count'] !== 1
        || ($out['writer_ready'][0]['tv_hotel_id'] ?? null) !== 101
        || ($out['provider_http_calls'] ?? -1) !== 0
        || ($out['database_writes'] ?? -1) !== 0
        || ($out['mapping_writes'] ?? -1) !== 0
        || ($out['safe_to_write_now'] ?? true) !== false) {
        fwrite(STDERR, json_encode($out, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) . "\n");
        exit(1);
    }
    echo "MATCH_LIVE234_SECONDARY_CROSS_SOURCE_V1_SELFTEST_OK\n";
}
