<?php
declare(strict_types=1);

/**
 * LOCAL-owned provider -> AnyTour identity bridge.
 *
 * v2 introduces the first-party `local_hotel_id` alias in anytour_hotel_sources.
 * The alias is authoritative when present. `legacy_catalog` is consulted only for
 * migration of profiles that have not received the alias yet; an invalid/conflicting
 * local alias never falls back to legacy. Direct Andromeda bindings still revalidate
 * the exact CURRENT accepted MATCH tuple on every read.
 *
 * No supplier I/O and no MATCH decision writes.
 */
final class AnyTourProviderIdentityBridgeV2
{
    private const DIRECT_PROVIDER = 'andromeda';
    private const DIRECT_NAMESPACE = 'provider_ref_digest:andromeda';
    private const LOCAL_NAMESPACE = 'local_hotel_id';
    private const LEGACY_NAMESPACE = 'legacy_catalog';
    private const LOCAL_ACQUIRED_VIA = 'canonical_local_alias';
    private const SOURCE_KEYS = [
        'schema_version', 'provider', 'supplier_namespace', 'external_hotel_id',
        'accepted_local_hotel_id',
    ];
    private const LOCAL_SOURCE_KEYS = ['schema_version', 'local_hotel_id'];

    public static function providerRefDigest(string $providerHotelRef): string
    {
        $value = trim($providerHotelRef);
        if ($value === '' || strlen($value) > 240 || preg_match('/[\x00-\x1F\x7F]/', $value)) {
            throw new InvalidArgumentException('ANYTOUR_PROVIDER_BRIDGE_REF');
        }
        return hash('sha256', $value);
    }

    public static function filterOfferRows(PDO $db, array $rows): array
    {
        if ($rows === []) return [];
        if (!array_is_list($rows) || count($rows) > 15000) {
            throw new InvalidArgumentException('ANYTOUR_PROVIDER_BRIDGE_ROWS');
        }

        $normalized = [];
        $andromedaDigests = [];
        $andromedaLocalIds = [];
        $localIds = [];
        foreach ($rows as $index => $row) {
            if (!is_array($row)) throw new InvalidArgumentException('ANYTOUR_PROVIDER_BRIDGE_ROW');
            $provider = $row['provider'] ?? null;
            $digest = $row['provider_hotel_ref_digest'] ?? null;
            $local = self::positiveInt($row['legacy_hotel_id'] ?? null);
            $own = self::positiveInt($row['anytour_hotel_id'] ?? null);
            if (!is_string($provider) || !in_array($provider, ['tourvisor','anex','andromeda'], true)
                || !is_string($digest) || !preg_match('/\A[a-f0-9]{64}\z/D', $digest)) {
                throw new InvalidArgumentException('ANYTOUR_PROVIDER_BRIDGE_ROW');
            }
            $normalized[$index] = ['provider'=>$provider,'digest'=>$digest,'local'=>$local,'own'=>$own];
            $localIds[$local] = true;
            if ($provider === self::DIRECT_PROVIDER) {
                $andromedaDigests[$digest] = true;
                $andromedaLocalIds[$local] = true;
            }
        }

        $direct = self::directSources($db, array_keys($andromedaDigests));
        $currentAccepted = self::currentAndromedaAccepted($db, $direct);
        $fallbackAccepted = self::currentAndromedaAcceptedByDigest($db, array_keys($andromedaLocalIds));
        $targets = self::identityTargets($db, array_keys($localIds), false);

        $out = [];
        foreach ($rows as $index => $row) {
            $meta = $normalized[$index];
            if ($meta['provider'] === self::DIRECT_PROVIDER && array_key_exists($meta['digest'], $direct)) {
                $source = $direct[$meta['digest']];
                if (!is_array($source) || ($source['valid'] ?? false) !== true
                    || ($source['anytour_hotel_id'] ?? null) !== $meta['own']
                    || ($source['accepted_local_hotel_id'] ?? null) !== $meta['local']) continue;
                $key = self::tupleKey($source['supplier_namespace'], $source['external_hotel_id']);
                if (($currentAccepted[$key] ?? null) !== $meta['local']) continue;
                $out[] = $row;
                continue;
            }
            if ($meta['provider'] === self::DIRECT_PROVIDER
                && ($fallbackAccepted[$meta['digest']] ?? null) !== $meta['local']) continue;
            if (($targets[$meta['local']] ?? null) === $meta['own']) $out[] = $row;
        }
        return $out;
    }

    public static function allowsOffer(
        PDO $db,
        string $provider,
        string $providerHotelRefDigest,
        int $localHotelId,
        int $anytourHotelId
    ): bool {
        $active = $db->prepare('SELECT 1 FROM anytour_hotels WHERE id=:id AND is_active=1 LIMIT 1');
        $active->execute(['id'=>$anytourHotelId]);
        if ($active->fetchColumn() === false) return false;
        return count(self::filterOfferRows($db, [[
            'provider'=>$provider,
            'provider_hotel_ref_digest'=>$providerHotelRefDigest,
            'legacy_hotel_id'=>$localHotelId,
            'anytour_hotel_id'=>$anytourHotelId,
        ]])) === 1;
    }

    /**
     * Materialize exact CURRENT accepted Andromeda identities. A legacy source may
     * locate a not-yet-migrated profile once; the same transaction establishes the
     * authoritative first-party local alias before writing the direct provider link.
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
            if (!is_array($ref) || !self::exactKeys($ref, ['supplier_namespace','external_hotel_id'])) {
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
            $targets = self::identityTargets($db, array_keys($localIds), true);

            $insert = $db->prepare("INSERT INTO anytour_hotel_sources
                (namespace,external_key,anytour_hotel_id,acquired_via,source_json,source_sha256,first_seen_at,last_seen_at)
                VALUES (:namespace,:external_key,:own,'match_accepted_bridge',:source_json,:source_sha,:first_seen,:last_seen)");
            $lock = $db->prepare("SELECT anytour_hotel_id,acquired_via,source_json,source_sha256
                FROM anytour_hotel_sources WHERE namespace=:namespace AND external_key=:external_key FOR UPDATE");
            $touch = $db->prepare("UPDATE anytour_hotel_sources SET source_json=:source_json,source_sha256=:source_sha,last_seen_at=:seen
                WHERE namespace=:namespace AND external_key=:external_key AND anytour_hotel_id=:own");

            $created = $refreshed = $unchanged = $unresolved = 0;
            $aliasCreated = $aliasUnchanged = 0;
            $materialized = [];
            $seen = $now->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
            foreach ($wanted as $tuple => [$supplierNamespace, $external]) {
                $local = $accepted[$tuple] ?? null;
                if (!is_int($local) || $local < 1 || !isset($targets[$local]) || !is_int($targets[$local])) {
                    ++$unresolved; continue;
                }
                $own = $targets[$local];
                $alias = self::ensureLocalAlias($db, $local, $own, $seen);
                $aliasCreated += $alias === 'created' ? 1 : 0;
                $aliasUnchanged += $alias === 'unchanged' ? 1 : 0;

                $digest = self::providerRefDigest($supplierNamespace . ':' . $external);
                $source = [
                    'schema_version'=>1,
                    'provider'=>self::DIRECT_PROVIDER,
                    'supplier_namespace'=>$supplierNamespace,
                    'external_hotel_id'=>$external,
                    'accepted_local_hotel_id'=>$local,
                ];
                $json = self::json($source); $sha = hash('sha256', $json);
                $lock->execute(['namespace'=>self::DIRECT_NAMESPACE,'external_key'=>$digest]);
                $existing = $lock->fetch(PDO::FETCH_ASSOC);
                if ($existing === false) {
                    $insert->execute([
                        'namespace'=>self::DIRECT_NAMESPACE,'external_key'=>$digest,'own'=>$own,
                        'source_json'=>$json,'source_sha'=>$sha,'first_seen'=>$seen,'last_seen'=>$seen,
                    ]);
                    ++$created;
                } else {
                    if ((int)$existing['anytour_hotel_id'] !== $own) throw new DomainException('ANYTOUR_PROVIDER_BRIDGE_TARGET_CONFLICT');
                    if (($existing['acquired_via'] ?? null) !== 'match_accepted_bridge') throw new DomainException('ANYTOUR_PROVIDER_BRIDGE_OWNER_CONFLICT');
                    if (($existing['source_sha256'] ?? null) === $sha
                        && hash('sha256', (string)$existing['source_json']) === $sha) ++$unchanged;
                    else {
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
            'source'=>'anytour-provider-identity-bridge-v2',
            'provider'=>self::DIRECT_PROVIDER,
            'requested'=>count($refs),'materialized'=>count($materialized),'verified'=>count($verified),
            'created'=>$created,'refreshed'=>$refreshed,'unchanged'=>$unchanged,'unresolved'=>$unresolved,
            'local_alias_created'=>$aliasCreated,'local_alias_unchanged'=>$aliasUnchanged,
            'mapping_writes'=>0,'supplier_calls'=>0,
        ];
    }

    private static function ensureLocalAlias(PDO $db, int $local, int $own, string $seen): string
    {
        $key = (string)$local;
        $source = ['schema_version'=>1,'local_hotel_id'=>$local];
        $json = self::json($source); $sha = hash('sha256', $json);
        $lock = $db->prepare('SELECT anytour_hotel_id,acquired_via,source_json,source_sha256 FROM anytour_hotel_sources WHERE namespace=? AND external_key=? FOR UPDATE');
        $lock->execute([self::LOCAL_NAMESPACE, $key]);
        $row = $lock->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            $insert = $db->prepare("INSERT INTO anytour_hotel_sources
                (namespace,external_key,anytour_hotel_id,acquired_via,source_json,source_sha256,first_seen_at,last_seen_at)
                VALUES (?,?,?,? ,?,?,?,?)");
            $insert->execute([self::LOCAL_NAMESPACE,$key,$own,self::LOCAL_ACQUIRED_VIA,$json,$sha,$seen,$seen]);
            return 'created';
        }
        if ((int)$row['anytour_hotel_id'] !== $own) throw new DomainException('ANYTOUR_LOCAL_ALIAS_TARGET_CONFLICT');
        if (!self::validLocalAliasRow($row, $local, $own)) throw new DomainException('ANYTOUR_LOCAL_ALIAS_INTEGRITY');
        $touch = $db->prepare('UPDATE anytour_hotel_sources SET last_seen_at=? WHERE namespace=? AND external_key=? AND anytour_hotel_id=?');
        $touch->execute([$seen,self::LOCAL_NAMESPACE,$key,$own]);
        return 'unchanged';
    }

    /** local_hotel_id is authoritative when present; legacy is migration-only fallback. */
    private static function identityTargets(PDO $db, array $localIds, bool $requireActive): array
    {
        if ($localIds === []) return [];
        $ids = array_values(array_unique(array_map(static fn($v)=>(string)self::positiveInt($v), $localIds)));
        $result = []; $present = [];
        foreach (array_chunk($ids, 500) as $chunk) {
            $join = $requireActive ? ' JOIN anytour_hotels h ON h.id=s.anytour_hotel_id' : '';
            $active = $requireActive ? ' AND h.is_active=1' : '';
            $sql = "SELECT CAST(s.external_key AS CHAR) AS external_key,s.anytour_hotel_id,s.acquired_via,s.source_json,s.source_sha256
                FROM anytour_hotel_sources s".$join." WHERE s.namespace=?".$active." AND s.external_key IN ("
                .implode(',',array_fill(0,count($chunk),'?')).')';
            $query = $db->prepare($sql); $query->execute(array_merge([self::LOCAL_NAMESPACE],$chunk));
            foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $local = self::positiveInt($row['external_key'] ?? null);
                $own = self::positiveInt($row['anytour_hotel_id'] ?? null);
                $present[$local] = true;
                $result[$local] = self::validLocalAliasRow($row,$local,$own) ? $own : null;
            }
        }
        $missing = array_values(array_filter($ids, static fn($id)=>!isset($present[(int)$id])));
        foreach (array_chunk($missing, 500) as $chunk) {
            if ($chunk === []) continue;
            $join = $requireActive ? ' JOIN anytour_hotels h ON h.id=s.anytour_hotel_id' : '';
            $active = $requireActive ? ' AND h.is_active=1' : '';
            $sql = "SELECT CAST(s.external_key AS CHAR) AS external_key,s.anytour_hotel_id FROM anytour_hotel_sources s"
                .$join." WHERE s.namespace=?".$active." AND s.external_key IN (".implode(',',array_fill(0,count($chunk),'?')).')';
            $query = $db->prepare($sql); $query->execute(array_merge([self::LEGACY_NAMESPACE],$chunk));
            foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $local = self::positiveInt($row['external_key'] ?? null);
                $own = self::positiveInt($row['anytour_hotel_id'] ?? null);
                $result[$local] = array_key_exists($local,$result) ? null : $own;
            }
        }
        return $result;
    }

    private static function validLocalAliasRow(array $row, int $local, int $own): bool
    {
        if (($row['acquired_via'] ?? null) !== self::LOCAL_ACQUIRED_VIA
            || !is_string($row['source_json'] ?? null) || !is_string($row['source_sha256'] ?? null)
            || !hash_equals($row['source_sha256'], hash('sha256',$row['source_json']))) return false;
        try { $source=json_decode($row['source_json'],true,8,JSON_THROW_ON_ERROR); }
        catch (Throwable) { return false; }
        return is_array($source) && self::exactKeys($source,self::LOCAL_SOURCE_KEYS)
            && ($source['schema_version']??null)===1 && ($source['local_hotel_id']??null)===$local && $own>0;
    }

    private static function directSources(PDO $db, array $digests): array
    {
        if ($digests === []) return [];
        $result=[];
        foreach (array_chunk($digests,500) as $chunk) {
            $sql="SELECT CAST(external_key AS CHAR) AS external_key,anytour_hotel_id,acquired_via,source_json,source_sha256
                FROM anytour_hotel_sources WHERE namespace=? AND external_key IN (".implode(',',array_fill(0,count($chunk),'?')).')';
            $query=$db->prepare($sql); $query->execute(array_merge([self::DIRECT_NAMESPACE],$chunk));
            foreach($query->fetchAll(PDO::FETCH_ASSOC) as $row){$digest=(string)$row['external_key'];$result[$digest]=self::decodeDirectSource($row,$digest);}
        }
        return $result;
    }

    private static function decodeDirectSource(array $row,string $digest): array
    {
        $base=['valid'=>false,'anytour_hotel_id'=>(int)($row['anytour_hotel_id']??0)];
        if(($row['acquired_via']??null)!=='match_accepted_bridge'||!is_string($row['source_json']??null)||!is_string($row['source_sha256']??null)
            ||!hash_equals($row['source_sha256'],hash('sha256',$row['source_json']))) return $base;
        try{$source=json_decode($row['source_json'],true,16,JSON_THROW_ON_ERROR);}catch(Throwable){return $base;}
        if(!is_array($source)||!self::exactKeys($source,self::SOURCE_KEYS)||($source['schema_version']??null)!==1
            ||($source['provider']??null)!==self::DIRECT_PROVIDER||!is_int($source['accepted_local_hotel_id']??null)||$source['accepted_local_hotel_id']<1) return $base;
        try{$namespace=self::supplierNamespace($source['supplier_namespace']??null);$external=self::externalId($source['external_hotel_id']??null);
            if(!hash_equals($digest,self::providerRefDigest($namespace.':'.$external)))return $base;}catch(Throwable){return $base;}
        return ['valid'=>true,'anytour_hotel_id'=>(int)$row['anytour_hotel_id'],'supplier_namespace'=>$namespace,
            'external_hotel_id'=>$external,'accepted_local_hotel_id'=>$source['accepted_local_hotel_id']];
    }

    private static function currentAndromedaAccepted(PDO $db,array $direct): array
    {
        $external=[];foreach($direct as $source)if(is_array($source)&&($source['valid']??false)===true)$external[$source['external_hotel_id']]=true;
        if($external===[])return[];$result=[];
        foreach(array_chunk(array_keys($external),500) as $chunk){
            $sql="SELECT supplier_namespace,CAST(external_hotel_id AS CHAR) AS external_hotel_id,local_hotel_id FROM andromeda_hotel_identities
                WHERE decision_status='accepted' AND external_hotel_id IN (".implode(',',array_fill(0,count($chunk),'?')).')';
            try{$query=$db->prepare($sql);$query->execute($chunk);}catch(Throwable){return[];}
            foreach($query->fetchAll(PDO::FETCH_ASSOC) as $row){try{$key=self::tupleKey(self::supplierNamespace($row['supplier_namespace']??null),self::externalId($row['external_hotel_id']??null));$local=self::positiveInt($row['local_hotel_id']??null);}catch(Throwable){continue;}
                $result[$key]=array_key_exists($key,$result)?null:$local;}
        }return$result;
    }

    private static function currentAndromedaAcceptedByDigest(PDO $db,array $localIds): array
    {
        if($localIds===[])return[];$result=[];
        foreach(array_chunk(array_map('strval',$localIds),500) as $chunk){
            $sql="SELECT supplier_namespace,CAST(external_hotel_id AS CHAR) AS external_hotel_id,local_hotel_id FROM andromeda_hotel_identities
                WHERE decision_status='accepted' AND local_hotel_id IN (".implode(',',array_fill(0,count($chunk),'?')).')';
            try{$query=$db->prepare($sql);$query->execute($chunk);}catch(Throwable){return[];}
            foreach($query->fetchAll(PDO::FETCH_ASSOC) as $row){try{$namespace=self::supplierNamespace($row['supplier_namespace']??null);$external=self::externalId($row['external_hotel_id']??null);$local=self::positiveInt($row['local_hotel_id']??null);$digest=self::providerRefDigest($namespace.':'.$external);}catch(Throwable){continue;}
                $result[$digest]=array_key_exists($digest,$result)?null:$local;}
        }return$result;
    }

    private static function acceptedForRefs(PDO $db,array $refs): array
    {
        $external=[];foreach($refs as[,$id])$external[$id]=true;$rows=[];
        foreach(array_chunk(array_keys($external),500) as $chunk){
            $sql="SELECT supplier_namespace,CAST(external_hotel_id AS CHAR) AS external_hotel_id,local_hotel_id FROM andromeda_hotel_identities
                WHERE decision_status='accepted' AND external_hotel_id IN (".implode(',',array_fill(0,count($chunk),'?')).')';
            $query=$db->prepare($sql);$query->execute($chunk);
            foreach($query->fetchAll(PDO::FETCH_ASSOC) as $row){$namespace=self::supplierNamespace($row['supplier_namespace']??null);$id=self::externalId($row['external_hotel_id']??null);$key=self::tupleKey($namespace,$id);$local=self::positiveInt($row['local_hotel_id']??null);$rows[$key]=array_key_exists($key,$rows)?null:$local;}
        }
        $out=[];foreach($refs as[$namespace,$id]){$key=self::tupleKey($namespace,$id);if(array_key_exists($key,$rows))$out[$key]=$rows[$key];}return$out;
    }

    private static function supplierNamespace(mixed $value): string
    {
        if(!is_string($value)||!preg_match('/\A[a-z0-9_]{1,64}\z/D',$value))throw new InvalidArgumentException('ANYTOUR_PROVIDER_BRIDGE_SUPPLIER_NAMESPACE');
        return$value;
    }
    private static function externalId(mixed $value): string
    {
        if((!is_string($value)&&!is_int($value))||is_bool($value))throw new InvalidArgumentException('ANYTOUR_PROVIDER_BRIDGE_EXTERNAL_ID');
        $value=trim((string)$value);if($value===''||strlen($value)>120||preg_match('/[\x00-\x1F\x7F:]/',$value))throw new InvalidArgumentException('ANYTOUR_PROVIDER_BRIDGE_EXTERNAL_ID');return$value;
    }
    private static function positiveInt(mixed $value): int
    {
        if(is_int($value)&&$value>0)return$value;
        if(is_string($value)&&preg_match('/\A[1-9][0-9]*\z/D',$value)&&filter_var($value,FILTER_VALIDATE_INT)!==false)return(int)$value;
        throw new InvalidArgumentException('ANYTOUR_PROVIDER_BRIDGE_ID');
    }
    private static function tupleKey(string $namespace,string $external): string{return$namespace."\0".$external;}
    private static function exactKeys(array $value,array $expected): bool{return count($value)===count($expected)&&array_diff($expected,array_keys($value))===[]&&array_diff(array_keys($value),$expected)===[];}
    private static function json(array $value): string{ksort($value,SORT_STRING);return json_encode($value,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);}
}
