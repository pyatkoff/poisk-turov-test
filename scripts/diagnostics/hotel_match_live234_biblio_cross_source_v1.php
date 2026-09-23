<?php
declare(strict_types=1);

/**
 * Pure exact cross-source reconciler for Biblio-Globus secondary identities.
 *
 * Contract:
 *   Tourvisor operator 18 -> Andromeda operator_115
 *   exact provider-native external hotel ID equality only
 *
 * No I/O, no supplier calls and no database writes. Names, prices and numeric
 * coincidence across unrelated namespaces are intentionally not used.
 */
final class AnyTourMatchLive234BiblioCrossSourceV1
{
    private const TV_OPERATOR = 18;
    private const ANDROMEDA_NAMESPACE = 'operator_115';

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
            if ($namespace !== self::ANDROMEDA_NAMESPACE) {
                continue;
            }
            $external = self::identifier($row['external_hotel_id'] ?? null, 'observation_external_invalid');
            $seenAt = self::time($row['observed_at_utc'] ?? null, 'observation_time_invalid');
            if (!isset($observed[$external]) || $seenAt > $observed[$external]) {
                $observed[$external] = $seenAt;
            }
        }

        $acceptedBySource = [];
        $acceptedByTarget = [];
        foreach ($acceptedRows as $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException('accepted_row_invalid');
            }
            if (($row['decision_status'] ?? null) !== 'accepted') {
                continue;
            }
            $namespace = self::identifier($row['supplier_namespace'] ?? null, 'accepted_namespace_invalid');
            if ($namespace !== self::ANDROMEDA_NAMESPACE) {
                continue;
            }
            $external = self::identifier($row['external_hotel_id'] ?? null, 'accepted_external_invalid');
            $local = self::positiveInt($row['local_hotel_id'] ?? null, 'accepted_local_invalid');
            if (isset($acceptedBySource[$external]) && $acceptedBySource[$external] !== $local) {
                throw new RuntimeException('accepted_source_collision');
            }
            $acceptedBySource[$external] = $local;
            $acceptedByTarget[$local][$external] = true;
        }

        $blocked = [];
        foreach ($blockedPairs as $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException('blocked_row_invalid');
            }
            $namespace = self::identifier($row['supplier_namespace'] ?? null, 'blocked_namespace_invalid');
            if ($namespace !== self::ANDROMEDA_NAMESPACE) {
                continue;
            }
            $external = self::identifier($row['external_hotel_id'] ?? null, 'blocked_external_invalid');
            $local = self::positiveInt($row['local_hotel_id'] ?? null, 'blocked_local_invalid');
            $blocked[$external.'|'.$local] = true;
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
                'namespace' => self::ANDROMEDA_NAMESPACE,
                'external_hotel_id' => null,
                'state' => null,
                'safe_to_write_now' => false,
            ];

            if ($operator !== self::TV_OPERATOR) {
                $base['state'] = 'operator_not_biblio';
                $prepared[] = $base;
                continue;
            }
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
            $base['state'] = 'prepared';
            $sourceTargets[$external][$local] = true;
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
            $external = $row['external_hotel_id'];

            if (count($sourceTargets[$external] ?? []) !== 1) {
                $row['state'] = 'tv_source_collision';
                $rows[] = self::counted($row, $counts);
                continue;
            }

            $observedAt = $observed[$external] ?? null;
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

            if (isset($blocked[$external.'|'.$local])) {
                $row['state'] = 'manual_or_exclusion_block';
                $rows[] = self::counted($row, $counts);
                continue;
            }

            $acceptedLocal = $acceptedBySource[$external] ?? null;
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

            $targetExisting = array_keys($acceptedByTarget[$local] ?? []);
            if ($targetExisting !== []) {
                sort($targetExisting, SORT_NATURAL);
                $row['state'] = 'target_namespace_occupied_other';
                $row['accepted_external_hotel_ids'] = $targetExisting;
                $rows[] = self::counted($row, $counts);
                continue;
            }

            $row['state'] = 'writer_ready_exact_cross_source';
            $rows[] = self::counted($row, $counts);
        }

        ksort($counts);
        usort($rows, static fn(array $a, array $b): int =>
            [$a['tv_hotel_id'], (string)($a['external_hotel_id'] ?? '')]
            <=>
            [$b['tv_hotel_id'], (string)($b['external_hotel_id'] ?? '')]
        );
        $writerReady = array_values(array_filter(
            $rows,
            static fn(array $row): bool => $row['state'] === 'writer_ready_exact_cross_source'
        ));

        return [
            'state' => 'completed_read_only_biblio_cross_source_reconcile',
            'fresh_after_utc' => $cutoff->format('Y-m-d H:i:s'),
            'tv_edges_checked' => count($tvEdges),
            'andromeda_operator_115_observation_keys' => count($observed),
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
        ['tv_hotel_id'=>101,'operator_id'=>18,'state'=>'detail_identity_verified','link_state'=>'captured_single_native','positive_native_candidates'=>['7001']],
        ['tv_hotel_id'=>102,'operator_id'=>18,'state'=>'detail_identity_verified','link_state'=>'captured_single_native','positive_native_candidates'=>['7002']],
        ['tv_hotel_id'=>103,'operator_id'=>25,'state'=>'detail_identity_verified','link_state'=>'captured_single_native','positive_native_candidates'=>['7003']],
    ];
    $obs = [
        ['supplier_namespace'=>'operator_115','external_hotel_id'=>'7001','observed_at_utc'=>'2026-09-23 10:00:00'],
        ['supplier_namespace'=>'bgoperator','external_hotel_id'=>'7002','observed_at_utc'=>'2026-09-23 10:00:00'],
    ];
    $out = AnyTourMatchLive234BiblioCrossSourceV1::reconcile($edges,$obs,[],[],'2026-09-23 09:00:00');
    if (($out['state_counts']['writer_ready_exact_cross_source'] ?? 0) !== 1
        || ($out['state_counts']['andromeda_exact_native_not_observed'] ?? 0) !== 1
        || ($out['state_counts']['operator_not_biblio'] ?? 0) !== 1
        || ($out['writer_ready'][0]['tv_hotel_id'] ?? null) !== 101
        || $out['provider_http_calls'] !== 0
        || $out['database_writes'] !== 0
        || $out['mapping_writes'] !== 0
        || $out['safe_to_write_now'] !== false) {
        fwrite(STDERR, json_encode($out, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");
        exit(1);
    }
    echo "MATCH_LIVE234_BIBLIO_CROSS_SOURCE_V1_SELFTEST_OK\n";
}
