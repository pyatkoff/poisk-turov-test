<?php
declare(strict_types=1);

/**
 * LOCAL-owned bridge from a provider-hotel identity already accepted by MATCH to an
 * independent AnyTour hotel profile. No supplier I/O and no mapping acceptance.
 *
 * Direct bindings are stored by the exact provider_hotel_ref_digest already present
 * in the provider-neutral offer contract. Andromeda's ref is
 * `supplier_namespace:external_hotel_id`. The stored source keeps the accepted MATCH
 * tuple so every read can revalidate current acceptance and fail closed after a
 * reassignment/rejection. `legacy_catalog` is used only to bootstrap today's already
 * existing AnyTour profile while MATCH still targets the historical local ID; it is
 * not required by offer admission/read once a direct binding exists.
 */
final class AnyTourProviderIdentityBridgeV1
{
    private const DIRECT_PROVIDER = 'andromeda';
    private const DIRECT_NAMESPACE = 'provider_ref_digest:andromeda';
    private const SOURCE_KEYS = [
        'schema_version', 'provider', 'supplier_namespace', 'external_hotel_id',
        'accepted_local_hotel_id',
    ];

    public static function providerRefDigest(string $providerHotelRef): string
    {
        $value = trim($providerHotelRef);
        if ($value === '' || strlen($value) > 240 || preg_match('/[\x00-\x1F\x7F]/', $value)) {
            throw new InvalidArgumentException('ANYTOUR_PROVIDER_BRIDGE_REF');
        }
        return hash('sha256', $value);
    }

    /**
     * Keep only rows whose AnyTour target is authorized now.
     *
     * A present direct Andromeda binding is authoritative for that digest: if its
     * stored MATCH tuple is stale, malformed or no longer accepted, the row is
     * rejected and MUST NOT fall back to legacy_catalog. Rows not migrated yet keep
     * the legacy compatibility path so the migration can be progressive.
     */
    public static function filterOfferRows(PDO $db, array $rows): array
    {
        if ($rows === []) return [];
        if (!array_is_list($rows) || count($rows) > 15000) {
            throw new InvalidArgumentException('ANYTOUR_PROVIDER_BRIDGE_ROWS');
        }

        $normalized = [];
        $andromedaDigests = [];
        $legacyIds = [];
        foreach ($rows as $index => $row) {
            if (!is_array($row)) throw new InvalidArgumentException('ANYTOUR_PROVIDER_BRIDGE_ROW');
            $provider = $row['provider'] ?? null;
            $digest = $row['provider_hotel_ref_digest'] ?? null;
            $legacy = self::positiveInt($row['legacy_hotel_id'] ?? null);
            $own = self::positiveInt($row['anytour_hotel_id'] ?? null);
            if (!is_string($provider) || !in_array($provider, ['tourvisor','anex','andromeda'], true)
                || !is_string($digest) || !preg_match('/\A[a-f0-9]{64}\z/D', $digest)) {
                throw new InvalidArgumentException('ANYTOUR_PROVIDER_BRIDGE_ROW');
            }
            $normalized[$index] = [
                'provider' => $provider, 'digest' => $digest, 'legacy' => $legacy, 'own' => $own,
            ];
            $legacyIds[$legacy] = true;
            if ($provider === self::DIRECT_PROVIDER) $andromedaDigests[$digest] = true;
        }

        $direct = self::directSources($db, array_keys($andromedaDigests));
        $currentAccepted = self::currentAndromedaAccepted($db, $direct);
        $legacyTargets = self::legacyTargets($db, array_keys($legacyIds));

        $out = [];
        foreach ($rows as $index => $row) {
            $meta = $normalized[$index];
            if ($meta['provider'] === self::DIRECT_PROVIDER && array_key_exists($meta['digest'], $direct)) {
                $source = $direct[$meta['digest']];
                if (!is_array($source) || ($source['valid'] ?? false) !== true
                    || ($source['anytour_hotel_id'] ?? null) !== $meta['own']
                    || ($source['accepted_local_hotel_id'] ?? null) !== $meta['legacy']) {
                    continue;
                }
                $key = self::tupleKey($source['supplier_namespace'], $source['external_hotel_id']);
                if (($currentAccepted[$key] ?? null) !== $meta['legacy']) continue;
                $out[] = $row;
                continue;
            }
            if (($legacyTargets[$meta['legacy']] ?? null) === $meta['own']) $out[] = $row;
        }
        return $out;
    }

    public static function allowsOffer(
        PDO $db,
        string $provider,
        string $providerHotelRefDigest,
        int $legacyHotelId,
        int $anytourHotelId
    ): bool {
        $rows = self::filterOfferRows($db, [[
            'provider' => $provider,
            'provider_hotel_ref_digest' => $providerHotelRefDigest,
            'legacy_hotel_id' => $legacyHotelId,
            'anytour_hotel_id' => $anytourHotelId,
        ]]);
        return count($rows) === 1;
    }

    /**
     * Materialize only exact CURRENT accepted Andromeda mappings requested by caller.
     * No MATCH row is created/updated. The existing legacy->AnyTour link is read once
     * only to locate today's independent profile. Runtime direct reads do not need it.
     *
     * @param list<array{supplier_namespace:string,external_hotel_id:string|int}> $refs
     */
    public static function materializeAcceptedAndromeda(PDO $db, array $refs, DateTimeImmutable $now): array
    {
        if ($db->inTransaction()) throw new LogicException('ANYTOUR_PROVIDER_BRIDGE_CALLER_TRANSACTION');
        if (!array_is_list($refs) || count($refs) < 1 || count($refs) > 1000) {
            throw new InvalidArgumentException('ANYTOUR_PROVIDER_BRIDGE_REFS');
        }
        $wanted = [];
        foreach ($refs as $ref) {
            if (!is_array($ref) || array_keys($ref) !== ['supplier_namespace','external_hotel_id']) {
                throw new InvalidArgumentException('ANYTOUR_PROVIDER_BRIDGE_REF');
            }
            $namespace = self::supplierNamespace($ref['supplier_namespace']);
            $external = self::externalId($ref['external_hotel_id']);
            $wanted[self::tupleKey($namespace, $external)] = [$namespace, $external];
        }
        if (count($wanted) !== count($refs)) throw new InvalidArgumentException('ANYTOUR_PROVIDER_BRIDGE_DUPLICATE_REF');

        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->beginTransaction();
        try {
            $accepted = self::acceptedForRefs($db, array_values($wanted));
            $localIds = [];
            foreach ($accepted as $local) if (is_int($local) && $local > 0) $localIds[$local] = true;
            $targets = self::legacyTargets($db, array_keys($localIds));

            $insert = $db->prepare("INSERT INTO anytour_hotel_sources
                (namespace,external_key,anytour_hotel_id,acquired_via,source_json,source_sha256,first_seen_at,last_seen_at)
                VALUES (:namespace,:external_key,:own,'match_accepted_bridge',:source_json,:source_sha,:seen,:seen)");
            $lock = $db->prepare("SELECT anytour_hotel_id,acquired_via,source_json,source_sha256
                FROM anytour_hotel_sources WHERE namespace=:namespace AND external_key=:external_key FOR UPDATE");
            $touch = $db->prepare("UPDATE anytour_hotel_sources SET source_json=:source_json,source_sha256=:source_sha,last_seen_at=:seen
                WHERE namespace=:namespace AND external_key=:external_key AND anytour_hotel_id=:own");

            $created = 0; $refreshed = 0; $unchanged = 0; $unresolved = 0;
            $materialized = [];
            $seen = $now->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
            foreach ($wanted as $tuple => [$supplierNamespace, $external]) {
                $local = $accepted[$tuple] ?? null;
                if (!is_int($local) || $local < 1 || !isset($targets[$local])) { ++$unresolved; continue; }
                $own = $targets[$local];
                $providerRef = $supplierNamespace . ':' . $external;
                $digest = self::providerRefDigest($providerRef);
                $source = [
                    'schema_version' => 1,
                    'provider' => self::DIRECT_PROVIDER,
                    'supplier_namespace' => $supplierNamespace,
                    'external_hotel_id' => $external,
                    'accepted_local_hotel_id' => $local,
                ];
                $json = self::json($source); $sha = hash('sha256', $json);
                $lock->execute(['namespace'=>self::DIRECT_NAMESPACE,'external_key'=>$digest]);
                $existing = $lock->fetch(PDO::FETCH_ASSOC);
                if ($existing === false) {
                    $insert->execute([
                        'namespace'=>self::DIRECT_NAMESPACE,'external_key'=>$digest,'own'=>$own,
                        'source_json'=>$json,'source_sha'=>$sha,'seen'=>$seen,
                    ]);
                    ++$created;
                } else {
                    if ((int)$existing['anytour_hotel_id'] !== $own) {
                        throw new DomainException('ANYTOUR_PROVIDER_BRIDGE_TARGET_CONFLICT');
                    }
                    if (($existing['acquired_via'] ?? null) !== 'match_accepted_bridge') {
                        throw new DomainException('ANYTOUR_PROVIDER_BRIDGE_OWNER_CONFLICT');
                    }
                    if (($existing['source_sha256'] ?? null) === $sha
                        && hash('sha256', (string)$existing['source_json']) === $sha) {
                        ++$unchanged;
                    } else {
                        $touch->execute([
                            'source_json'=>$json,'source_sha'=>$sha,'seen'=>$seen,
                            'namespace'=>self::DIRECT_NAMESPACE,'external_key'=>$digest,'own'=>$own,
                        ]);
                        if ($touch->rowCount() !== 1) throw new RuntimeException('ANYTOUR_PROVIDER_BRIDGE_REFRESH');
                        ++$refreshed;
                    }
                }
                $materialized[] = [
                    'provider'=>self::DIRECT_PROVIDER,
                    'provider_hotel_ref_digest'=>$digest,
                    'legacy_hotel_id'=>$local,
                    'anytour_hotel_id'=>$own,
                ];
            }
            $db->commit();
        } catch (Throwable $error) {
            if ($db->inTransaction()) $db->rollBack();
            throw $error;
        }

        $verified = self::filterOfferRows($db, $materialized);
        return [
            'source'=>'anytour-provider-identity-bridge-v1',
            'provider'=>self::DIRECT_PROVIDER,
            'requested'=>count($refs),
            'materialized'=>count($materialized),
            'verified'=>count($verified),
            'created'=>$created,
            'refreshed'=>$refreshed,
            'unchanged'=>$unchanged,
            'unresolved'=>$unresolved,
            'mapping_writes'=>0,
            'supplier_calls'=>0,
        ];
    }

    private static function directSources(PDO $db, array $digests): array
    {
        if ($digests === []) return [];
        $result = [];
        foreach (array_chunk($digests, 500) as $chunk) {
            $sql = "SELECT CAST(s.external_key AS CHAR) AS external_key,s.anytour_hotel_id,s.source_json,s.source_sha256,h.is_active
                FROM anytour_hotel_sources s JOIN anytour_hotels h ON h.id=s.anytour_hotel_id
                WHERE s.namespace=? AND s.external_key IN (" . implode(',', array_fill(0, count($chunk), '?')) . ')';
            $query = $db->prepare($sql); $query->execute(array_merge([self::DIRECT_NAMESPACE], $chunk));
            foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $digest = (string)$row['external_key'];
                $decoded = self::decodeDirectSource($row, $digest);
                $result[$digest] = $decoded;
            }
        }
        return $result;
    }

    private static function decodeDirectSource(array $row, string $digest): array
    {
        $base = ['valid'=>false,'anytour_hotel_id'=>(int)($row['anytour_hotel_id'] ?? 0)];
        if (($row['is_active'] ?? null) != 1 || !is_string($row['source_json'] ?? null)
            || !is_string($row['source_sha256'] ?? null)
            || hash('sha256', $row['source_json']) !== $row['source_sha256']) return $base;
        try { $source = json_decode($row['source_json'], true, 16, JSON_THROW_ON_ERROR); }
        catch (Throwable) { return $base; }
        if (!is_array($source) || !self::exactKeys($source, self::SOURCE_KEYS)
            || ($source['schema_version'] ?? null) !== 1 || ($source['provider'] ?? null) !== self::DIRECT_PROVIDER
            || !is_int($source['accepted_local_hotel_id'] ?? null) || $source['accepted_local_hotel_id'] < 1) return $base;
        try {
            $namespace = self::supplierNamespace($source['supplier_namespace'] ?? null);
            $external = self::externalId($source['external_hotel_id'] ?? null);
            if (!hash_equals($digest, self::providerRefDigest($namespace . ':' . $external))) return $base;
        } catch (Throwable) { return $base; }
        return [
            'valid'=>true,
            'anytour_hotel_id'=>(int)$row['anytour_hotel_id'],
            'supplier_namespace'=>$namespace,
            'external_hotel_id'=>$external,
            'accepted_local_hotel_id'=>$source['accepted_local_hotel_id'],
        ];
    }

    private static function currentAndromedaAccepted(PDO $db, array $direct): array
    {
        $external = [];
        foreach ($direct as $source) {
            if (is_array($source) && ($source['valid'] ?? false) === true) $external[$source['external_hotel_id']] = true;
        }
        if ($external === []) return [];
        $result = [];
        foreach (array_chunk(array_keys($external), 500) as $chunk) {
            $sql = "SELECT supplier_namespace,CAST(external_hotel_id AS CHAR) AS external_hotel_id,local_hotel_id
                FROM andromeda_hotel_identities WHERE decision_status='accepted' AND external_hotel_id IN ("
                . implode(',', array_fill(0, count($chunk), '?')) . ')';
            try { $query = $db->prepare($sql); $query->execute($chunk); }
            catch (Throwable) { return []; }
            foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) {
                try {
                    $key = self::tupleKey(
                        self::supplierNamespace($row['supplier_namespace'] ?? null),
                        self::externalId($row['external_hotel_id'] ?? null)
                    );
                    $local = self::positiveInt($row['local_hotel_id'] ?? null);
                } catch (Throwable) { continue; }
                $result[$key] = array_key_exists($key, $result) ? null : $local;
            }
        }
        return $result;
    }

    private static function acceptedForRefs(PDO $db, array $refs): array
    {
        $external = [];
        foreach ($refs as [, $id]) $external[$id] = true;
        $rows = [];
        foreach (array_chunk(array_keys($external), 500) as $chunk) {
            $sql = "SELECT supplier_namespace,CAST(external_hotel_id AS CHAR) AS external_hotel_id,local_hotel_id
                FROM andromeda_hotel_identities WHERE decision_status='accepted' AND external_hotel_id IN ("
                . implode(',', array_fill(0, count($chunk), '?')) . ')';
            $query = $db->prepare($sql); $query->execute($chunk);
            foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $namespace = self::supplierNamespace($row['supplier_namespace'] ?? null);
                $id = self::externalId($row['external_hotel_id'] ?? null);
                $key = self::tupleKey($namespace, $id);
                $local = self::positiveInt($row['local_hotel_id'] ?? null);
                $rows[$key] = array_key_exists($key, $rows) ? null : $local;
            }
        }
        $out = [];
        foreach ($refs as [$namespace, $id]) {
            $key = self::tupleKey($namespace, $id);
            if (array_key_exists($key, $rows)) $out[$key] = $rows[$key];
        }
        return $out;
    }

    private static function legacyTargets(PDO $db, array $legacyIds): array
    {
        if ($legacyIds === []) return [];
        $result = [];
        foreach (array_chunk(array_map('strval', $legacyIds), 500) as $chunk) {
            $sql = "SELECT CAST(s.external_key AS CHAR) AS external_key,s.anytour_hotel_id
                FROM anytour_hotel_sources s JOIN anytour_hotels h ON h.id=s.anytour_hotel_id
                WHERE s.namespace='legacy_catalog' AND h.is_active=1 AND s.external_key IN ("
                . implode(',', array_fill(0, count($chunk), '?')) . ')';
            $query = $db->prepare($sql); $query->execute($chunk);
            foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $legacy = self::positiveInt($row['external_key'] ?? null);
                $own = self::positiveInt($row['anytour_hotel_id'] ?? null);
                $result[$legacy] = array_key_exists($legacy, $result) ? null : $own;
            }
        }
        return $result;
    }

    private static function supplierNamespace(mixed $value): string
    {
        if (!is_string($value) || !preg_match('/\A[a-z0-9_]{1,64}\z/D', $value)) {
            throw new InvalidArgumentException('ANYTOUR_PROVIDER_BRIDGE_SUPPLIER_NAMESPACE');
        }
        return $value;
    }

    private static function externalId(mixed $value): string
    {
        if ((!is_string($value) && !is_int($value)) || is_bool($value)) {
            throw new InvalidArgumentException('ANYTOUR_PROVIDER_BRIDGE_EXTERNAL_ID');
        }
        $value = trim((string)$value);
        if ($value === '' || strlen($value) > 120 || preg_match('/[\x00-\x1F\x7F:]/', $value)) {
            throw new InvalidArgumentException('ANYTOUR_PROVIDER_BRIDGE_EXTERNAL_ID');
        }
        return $value;
    }

    private static function positiveInt(mixed $value): int
    {
        if (is_int($value) && $value > 0) return $value;
        if (is_string($value) && preg_match('/\A[1-9][0-9]*\z/D', $value)
            && filter_var($value, FILTER_VALIDATE_INT) !== false) return (int)$value;
        throw new InvalidArgumentException('ANYTOUR_PROVIDER_BRIDGE_ID');
    }

    private static function tupleKey(string $namespace, string $external): string
    {
        return $namespace . "\0" . $external;
    }

    private static function exactKeys(array $value, array $expected): bool
    {
        return count($value) === count($expected)
            && array_diff($expected, array_keys($value)) === []
            && array_diff(array_keys($value), $expected) === [];
    }

    private static function json(array $value): string
    {
        ksort($value, SORT_STRING);
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
