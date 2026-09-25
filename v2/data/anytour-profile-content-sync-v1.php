<?php
/** Opt-in content policy for the existing enrichment writer; no SQL writes or HTTP. */
declare(strict_types=1);
require_once __DIR__ . '/anytour-canonical-catalog-v1.php';

trait AnyTourProfileContentSyncV1
{
    public const SYNC_NAMESPACE = 'profile_sync:retained_tv_v1';
    public const SYNC_ACQUIRED_VIA = 'profile_sync_imported_v1';
    public const SYNC_FIELDS = [
        'name','category','rating','region','subRegion','coordinates',
        'description','primaryImage','images','address','place','build','repair','square',
        'hotelInformation.infrastructure','hotelInformation.services',
        'hotelInformation.meals','hotelInformation.roomTypes',
    ];

    private static function syncPresent(mixed $value): bool
    {
        if ($value === null) return false;
        if (is_string($value)) return preg_match('/[^\s\p{Z}]/u', html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8')) === 1;
        if (is_array($value)) {
            foreach ($value as $part) if (self::syncPresent($part)) return true;
            return false;
        }
        return true; // A real zero/false is not missing.
    }

    /** Empty/missing source leaves never delete retained facts. Lists are atomic. */
    private static function syncMerge(mixed $old, mixed $fresh): mixed
    {
        if (!self::syncPresent($fresh)) return $old;
        if (is_array($fresh) && !array_is_list($fresh)) {
            $out = is_array($old) && !array_is_list($old) ? $old : [];
            foreach ($fresh as $key => $value) {
                if (self::syncPresent($value)) $out[$key] = self::syncMerge($out[$key] ?? null, $value);
            }
            return $out;
        }
        return $fresh;
    }

    private static function syncEqual(mixed $a, mixed $b): bool
    {
        return self::json([$a]) === self::json([$b]);
    }

    private static function syncJson(array $row): array
    {
        $json = $row['source_json'] ?? null; $sha = $row['source_sha256'] ?? null;
        if (!is_string($json) || !is_string($sha) || !preg_match('/\A[a-f0-9]{64}\z/D', $sha)
            || !hash_equals($sha, hash('sha256', $json))) throw new DomainException('SYNC_SOURCE_INTEGRITY');
        $value = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($value)) throw new DomainException('SYNC_SOURCE_JSON');
        return $value;
    }

    private static function syncTime(mixed $value): ?string
    {
        if ($value === null || $value === '') return null;
        if (!is_string($value) || !preg_match('/\A\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}(?:Z|[+]00:00)?\z/D', $value)) {
            throw new DomainException('SYNC_SOURCE_TIME');
        }
        $date = new DateTimeImmutable($value, new DateTimeZone('UTC'));
        $errors = DateTimeImmutable::getLastErrors();
        if ($errors !== false && ($errors['warning_count'] || $errors['error_count'])) throw new DomainException('SYNC_SOURCE_TIME');
        return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    /** Prove the complete current profile is an import or a chain of our known writes. */
    private static function syncOrigin(array $row, array $profile, array $records, int $local): array
    {
        $own = (int)$row['anytour_hotel_id']; $seedRows = [];
        foreach ($records as $r) if ($r['namespace'] === 'legacy_catalog') $seedRows[] = $r;
        if (count($seedRows) !== 1) throw new DomainException('SYNC_IMPORT_IDENTITY');
        $r = $seedRows[0]; $seed = self::syncJson($r);
        if ((int)$r['anytour_hotel_id'] !== $own || (string)$r['external_key'] !== (string)$local
            || $r['acquired_via'] !== 'saved_catalog' || ($seed['id'] ?? null) !== $local) {
            throw new DomainException('SYNC_IMPORT_IDENTITY');
        }
        $initial = AnyTourCanonicalCatalog::initialProfile($seed);
        $time = self::syncTime($seed['detailsFetchedAt'] ?? null);
        $proof = ['seedSha256'=>$r['source_sha256'], 'receipts'=>[], 'notBefore'=>$time];
        if ((int)$row['revision'] === 1 && self::syncEqual($initial, $profile)) return $proof;
        $baseHashes = [hash('sha256', AnyTourCanonicalCatalog::json($initial)), hash('sha256', self::json($initial))];
        $cursor = $row['profile_sha256']; $revision = (int)$row['revision'];
        $byResult = [];
        foreach ($records as $r) {
            if (!in_array($r['namespace'], [self::PROVENANCE_NAMESPACE, self::SYNC_NAMESPACE], true)) continue;
            $via = $r['namespace'] === self::SYNC_NAMESPACE ? self::SYNC_ACQUIRED_VIA : self::PROVENANCE_ACQUIRED_VIA;
            if ($r['acquired_via'] !== $via) throw new DomainException('SYNC_PROVENANCE_KIND');
            $p = self::syncJson($r);
            if (($p['canonical_hotel_id'] ?? null) !== $own || ($p['accepted_local_hotel_id'] ?? null) !== $local
                || !is_string($p['result_profile_sha256'] ?? null) || !is_string($p['previous_profile_sha256'] ?? null)) {
                throw new DomainException('SYNC_PROVENANCE_IDENTITY');
            }
            $byResult[$p['result_profile_sha256']][] = [$p, $r['source_sha256']];
        }
        for ($i = 0; !in_array($cursor, $baseHashes, true) && $i < 32; ++$i) {
            if (count($byResult[$cursor] ?? []) !== 1) throw new DomainException('SYNC_UNPROVEN_OR_MANUAL_PROFILE');
            [$p, $sha] = $byResult[$cursor][0];
            if (($p['result_revision'] ?? null) !== $revision || $revision <= 1) throw new DomainException('SYNC_PROVENANCE_REVISION');
            $stamp = self::syncTime($p['details_fetched_at'] ?? null);
            if ($stamp !== null && ($time === null || $stamp > $time)) $time = $stamp;
            $proof['receipts'][] = $sha; $cursor = $p['previous_profile_sha256']; --$revision;
        }
        if (!in_array($cursor, $baseHashes, true) || $revision !== 1) throw new DomainException('SYNC_UNPROVEN_OR_MANUAL_PROFILE');
        $proof['notBefore'] = $time;
        return $proof;
    }

    /** Retained full-card facts must agree with current saved fields; no stale raw fallback. */
    private static function syncFacts(array $saved, array $detail, int $local, ?string $notBefore, string $through): array
    {
        if (($detail['status'] ?? null) !== 'success') throw new DomainException('SYNC_FULL_CARD_UNAVAILABLE');
        $raw = self::syncJson(['source_json'=>$detail['raw_json'] ?? null, 'source_sha256'=>$detail['source_hash'] ?? null]);
        if (($raw['id'] ?? null) !== $local) throw new DomainException('SYNC_RAW_IDENTITY');
        $time = self::syncTime($detail['fetched_at'] ?? null);
        if ($time === null || $time > $through || ($notBefore !== null && $time < $notBefore)) throw new DomainException('SYNC_OLDER_OR_UNKNOWN_SOURCE');
        $norm = v2_hotel_detail_normalized($raw);
        if ($norm['generic_product']) throw new DomainException('SYNC_GENERIC_PRODUCT');
        $facts = []; $held = [];
        $textMap = ['description'=>'description','address'=>'address','place'=>'place','build'=>'build','repair'=>'repair','square'=>'square','roomTypes'=>'room_types'];
        foreach ($textMap as $source => $key) {
            $field = $source === 'roomTypes' ? 'hotelInformation.roomTypes' : $source;
            if (self::syncEqual($saved[$source] ?? null, $norm[$key] ?? null)) $facts[$field] = $norm[$key];
            else $held[$field] = 'raw_saved_disagreement';
        }
        foreach (['infrastructure','services','meals'] as $key) {
            $value = $raw[$key] ?? [];
            if (!is_array($value)) { $held['hotelInformation.'.$key] = 'invalid_structure'; continue; }
            if (self::syncEqual($saved[$key] ?? [], $value)) $facts['hotelInformation.'.$key] = $value;
            else $held['hotelInformation.'.$key] = 'raw_saved_disagreement';
        }
        $storedImages = json_decode($detail['images_json'] ?? '[]', true, 512, JSON_THROW_ON_ERROR);
        if (self::syncEqual($storedImages, $norm['images'])) {
            $facts['images'] = v2_hotel_detail_images($raw, null);
            $facts['primaryImage'] = $facts['images'][0] ?? null;
        } else $held['images'] = $held['primaryImage'] = 'raw_saved_disagreement';
        foreach (['name','category','rating'] as $key) {
            if (self::syncEqual($saved[$key] ?? null, $norm[$key] ?? null)) $facts[$key] = $saved[$key] ?? null;
            else $held[$key] = 'raw_catalog_disagreement';
        }
        foreach (['region','subRegion'] as $key) {
            $a = self::namedNode($saved[$key] ?? null); $b = self::namedNode($raw[$key] ?? null);
            if (self::syncEqual($a, $b)) $facts[$key] = $a;
            else $held[$key] = 'raw_catalog_disagreement';
        }
        $coord = self::coordinates($saved['coordinates'] ?? null);
        if ($coord !== null && $norm['latitude'] !== null && $norm['longitude'] !== null
            && abs($coord['latitude']-$norm['latitude']) <= 0.000000051
            && abs($coord['longitude']-$norm['longitude']) <= 0.000000051) $facts['coordinates'] = $coord;
        else $held['coordinates'] = 'raw_catalog_disagreement_or_missing';
        return ['facts'=>$facts, 'held'=>$held, 'detailsFetchedAt'=>$time,
            'rawSha256'=>$detail['source_hash'], 'savedSha256'=>hash('sha256', self::json($saved))];
    }

    /** Exact IDs only. The caller's existing plan/apply transaction supplies isolation. */
    private function syncSnapshot(int $limit, string $through, array $scope): array
    {
        $rows = $this->rankedRows($through, $scope); $selected = []; $held = [];
        $ids = array_keys($scope); $marks = implode(',', array_fill(0, count($ids), '?'));
        $query = $this->pdo->prepare("SELECT namespace,external_key,anytour_hotel_id,acquired_via,source_json,source_sha256
            FROM anytour_hotel_sources WHERE anytour_hotel_id IN ($marks)
            AND namespace IN ('legacy_catalog','profile_enrichment:legacy_saved_v1','profile_sync:retained_tv_v1') ORDER BY id");
        $query->execute($ids); $records = [];
        foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $r) $records[(int)$r['anytour_hotel_id']][] = $r;
        $localIds = array_column(array_values($scope), 'localHotelId'); $saved = []; $details = [];
        foreach (array_chunk($localIds, HOTEL_PRESENTATION_READ_LIMIT) as $chunk) {
            foreach (hotel_presentation_read_many($this->pdo, $chunk)['items'] as $s) $saved[(int)$s['id']] = $s;
            $marks = implode(',', array_fill(0, count($chunk), '?'));
            $q = $this->pdo->prepare("SELECT hotel_id,status,raw_json,source_hash,fetched_at,images_json FROM catalog_hotel_details WHERE hotel_id IN ($marks)");
            $q->execute($chunk);
            foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) $details[(int)$r['hotel_id']] = $r;
        }
        foreach ($rows as $row) {
            $alias = self::decodeAlias($row); $own = $alias['ownId']; $local = $alias['localId'];
            $profile = self::decodeProfile($row);
            try {
                if (!isset($saved[$local])) throw new DomainException('SYNC_SAVED_PROFILE_UNAVAILABLE');
                $proof = self::syncOrigin($row, $profile, $records[$own] ?? [], $local);
                $source = self::syncFacts($saved[$local], $details[$local] ?? [], $local, $proof['notBefore'], $through);
                $patch = []; $retained = [];
                foreach ($scope[$own]['fields'] as $field) {
                    if (isset($source['held'][$field])) { $retained[$field] = $source['held'][$field]; continue; }
                    $value = $source['facts'][$field] ?? null;
                    if (!self::syncPresent($value)) { $retained[$field] = 'source_missing_preserved'; continue; }
                    $old = self::ownValue($profile, $field); $next = self::syncMerge($old, $value);
                    if (!self::syncEqual($old, $next)) $patch[$field] = $next;
                    if (!self::syncEqual($next, $value)) $retained[$field] = 'empty_source_leaves_preserved';
                }
                if ($retained) $held[$own] = $retained;
                if (!$patch) continue;
                $selected[] = [
                    'anytourHotelId'=>$own,'localHotelId'=>$local,
                    'expectedProfileSha256'=>$row['profile_sha256'],'expectedRevision'=>(int)$row['revision'],
                    'expectedAliasSha256'=>$row['alias_source_sha256'],'beforeProfileJson'=>$row['profile_json'],
                    'sourceFingerprint'=>hash('sha256', self::json([$source,$proof])),
                    'detailsFetchedAt'=>$source['detailsFetchedAt'],'patch'=>$patch,'preservedConflicts'=>array_keys($retained),
                    'importProof'=>$proof,'sourceRawSha256'=>$source['rawSha256'],
                ];
            } catch (DomainException $e) {
                $held[$own] = ['profile'=>$e->getMessage()];
            }
        }
        ksort($held, SORT_NUMERIC);
        $core = ['schemaVersion'=>1,'contentPolicy'=>'sync_imported_retained_tv_v1','demandThrough'=>$through,
            'limit'=>$limit,'activeProfiles'=>count($rows),'scannedProfiles'=>count($rows),'selected'=>$selected,
            'contentScope'=>array_values($scope),'preservedConflictCounts'=>[], 'held'=>$held,
            'roomMealFieldsExcluded'=>false,'offerDictionariesUnchanged'=>true,'traitsExcluded'=>true];
        $core['planSha256'] = hash('sha256', self::json($core));
        return $core;
    }
}
