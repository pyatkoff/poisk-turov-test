<?php
/** Optional read-only evidence for existing canonical LOCAL display fields. */
declare(strict_types=1);

/** The endpoint already checked the physical LOCAL directory and GET method. */
function anytour_canonical_provenance_meta(array $profile, array $row): ?array
{
    try {
        $id = $profile['id'] ?? null;
        $rev = $profile['revision'] ?? null;
        $tv = (int)($row['tv_id'] ?? 0);
        $localRev = (int)($row['local_revision'] ?? 0);
        $state = $row['content_state'] ?? null;
        if (($profile['catalog'] ?? null) !== 'anytour' || !is_int($id) || $id < 1 || $id > 9007199254740991
            || !is_int($rev) || $rev < 1 || $rev > 9007199254740991
            || (string)$id !== (string)($row['anytour_id'] ?? '')
            || (string)$rev !== (string)($row['canonical_revision'] ?? '')
            || $tv < 1 || $tv > 9007199254740991 || (string)$tv !== (string)($row['tv_id'] ?? '')
            || (string)$tv !== (string)($row['legacy_key'] ?? '')
            || $localRev < 1 || $localRev > 9007199254740991 || (string)$localRev !== (string)($row['local_revision'] ?? '')
            || !in_array($state, ['pending', 'ready', 'retry'], true)) return null;
        // Bind evidence to the same canonical revision and exact displayed values.
        $json = $row['profile_json'] ?? null;
        $hash = $row['profile_sha256'] ?? null;
        if (!is_string($json) || !is_string($hash) || !hash_equals($hash, hash('sha256', $json))) return null;
        $stored = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $manualJson = $row['manual_json'] ?? null;
        if (!is_array($stored) || !is_string($manualJson) || strlen($manualJson) > 2097152) return null;
        $manual = json_decode($manualJson, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($manual) || count($manual) > 256 || ($manual !== [] && array_is_list($manual))) return null;
        $fields = ['description', 'images', 'primaryImage'];
        $attested = [];
        foreach ($fields as $field) {
            if (!array_key_exists($field, $profile) || !array_key_exists($field, $stored) || !array_key_exists($field, $manual)
                || $profile[$field] !== $stored[$field] || $profile[$field] !== $manual[$field]) continue;
            // Nested overrides can change a top-level value. Do not guess precedence.
            foreach (array_keys($manual) as $path) {
                if (is_string($path) && str_starts_with($path, $field . '.')) continue 2;
            }
            $value = $profile[$field];
            if ($field === 'images') {
                if (!is_array($value) || !array_is_list($value) || !$value || count($value) > 256) continue;
                foreach ($value as $url) if (!is_string($url) || !preg_match('~^https://[^\s]+$~iD', $url)) continue 2;
            } elseif (!is_string($value) || trim($value) === '') continue;
            elseif ($field === 'primaryImage' && !preg_match('~^https://[^\s]+$~iD', $value)) continue;
            $attested[] = $field;
        }
        // Only field names leave this reader, never manual/source JSON or new content.
        if (!$attested) return null;
        $absent = json_decode((string)($row['source_absent_json'] ?? ''), true);
        if (!is_array($absent) || !array_is_list($absent) || count($absent) > 256) $absent = null;
        if ($absent !== null) foreach ($absent as $field) {
            if (!is_string($field) || strlen($field) > 192 || !preg_match('/^[A-Za-z][A-Za-z0-9_]*(?:\.[A-Za-z0-9_]+)*$/D', $field)
                || !in_array(explode('.', $field)[0], ['name','country','region','subRegion','category','rating','description','primaryImage','images','photoDetails','hotelInformation','descriptionSections','coordinates','address','place','build','repair','square'], true)) {
                $absent = null; break;
            }
        }
        $fetched = $row['source_fetched_at'] ?? null;
        return ['source' => 'anytour-local-tv-catalog-v1', 'anytourHotelId' => $id, 'tourvisorHotelId' => $tv,
            'canonicalRevision' => $rev, 'revision' => $localRev, 'manualFields' => $attested,
            'sourceAbsent' => $absent, 'contentState' => $state,
            'detailsAvailable' => $state === 'ready' && (string)($row['has_source'] ?? '') === '1',
            'detailsFetchedAt' => $state === 'ready' && is_string($fetched) && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $fetched) ? $fetched : null];
    } catch (Throwable $e) {
        return null;
    }
}

/** One bounded SELECT, no mutation, no provider calls. Missing/ambiguous evidence is unknown. */
function anytour_canonical_provenance_attach(PDO $pdo, array $items, ?array $expectedLinks = null): array
{
    foreach ($items as &$item) unset($item['localContentMeta']);
    unset($item);
    if (!$items || count($items) > 100) return $items;
    $ids = [];
    foreach ($items as $item) {
        $id = $item['id'] ?? null;
        if (!is_int($id) || $id < 1) return $items;
        $ids[$id] = $id;
    }
    $expected = [];
    if ($expectedLinks !== null) foreach ($expectedLinks as $link) {
        $expected[(int)$link['anytourHotelId']][] = (string)$link['legacyHotelId'];
    }
    $original = $items;
    try {
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $q = $pdo->prepare("SELECT a.id AS anytour_id, a.revision AS canonical_revision, a.profile_json, a.profile_sha256,
            b.tv_id, s.external_key AS legacy_key, l.revision AS local_revision, l.manual_json,
            l.state AS content_state, CASE WHEN l.source_json IS NULL THEN 0 ELSE 1 END AS has_source,
            l.source_fetched_at, l.source_absent_json
            FROM anytour_hotels a
            JOIN local_tv_legacy_links b ON b.old_local_id=a.id
            JOIN local_tv_hotels l ON l.id=b.tv_id
            JOIN anytour_hotel_sources s ON s.anytour_hotel_id=a.id AND s.namespace='legacy_catalog'
            WHERE a.is_active=1 AND a.id IN ($marks)");
        $q->execute(array_values($ids));
        $rows = [];
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $key = (int)($row['anytour_id'] ?? 0);
            if (array_key_exists($key, $rows)) $rows[$key] = null; // ambiguous: never choose a row
            else $rows[$key] = $row;
        }
        foreach ($items as &$item) {
            $row = $rows[$item['id']] ?? null;
            if (is_array($row) && ($expectedLinks === null
                || ($expected[$item['id']] ?? []) === [(string)$row['legacy_key']])) {
                $meta = anytour_canonical_provenance_meta($item, $row);
                if ($meta !== null) $item['localContentMeta'] = $meta;
            }
        }
        unset($item);
        return $items;
    } catch (Throwable $e) {
        // Optional metadata cannot break an otherwise valid catalogue response.
        return $original;
    }
}
