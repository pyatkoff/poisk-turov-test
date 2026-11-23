<?php
declare(strict_types=1);

/**
 * One finite LOCAL19 proposal; execution requires separate carrier admission. No discovery, provider client, migration, or retry.
 * The CLI authenticates the frozen proposal bytes; run() accepts that trusted manifest.
 */
final class AnyTourLocal19BridgeApplyV1
{
    public const OPERATION_ID = 'int-andromeda-local19-bridge-apply-20261123-v1';
    public const PROPOSAL_SHA256 = '02d3df3ecd94d775e58fd3e506cf81032b64179fa3b4db1ff43de9e515ec2192';
    public const PAIRS = [[4511,2490],[10334,22940],[8751,7831],[7599,2458],[14932,113544],[12248,52337],[4609,2948],[12520,57536],[12839,61241],[13686,73477],[13066,64648],[4533,2588],[7619,2611],[9843,17424],[9842,17423],[10377,23718],[4539,2612],[4538,2610],[4601,2931]];
    public const EXCLUDED_PAIRS = [[4679,4283],[14397,97001],[13766,74509],[14029,78669],[12816,60788],[14013,78456]];
    public const UNCHANGED_PAIRS = [[4172,80008]];
    public const FIELDS = ['description','primaryImage','images'];
    private const ACTIONS_SHA256 = 'd3e1c159a107638e069b5e6e003bf9ad085e2188be93ba1a2c78f75080db7c4e';
    private const MANUAL_SHA256 = [
        4511=>'d6f4adf9da5ecf5bb9f5fa9e667e1ea70d7e1088386dc30405149e68a1fb7a5a',
        10334=>'91caa4f92bc4b35bb71f64cdbbc28f8c8110a9ab29b7940efa31d3fcc464ad66',
        8751=>'a75799ae8d7a24775cf018162f6a32d5365ae544081ca5af3cd0a5109855d5d6',
        7599=>'796ebf5b920ebf2b5705968e9b69f908e677f69c770a40b5e121f08736ed8e0d',
        14932=>'d628c080b916517d9c4537e1301368e6bd8e9dff56affc4ca337c850655c2580',
        12248=>'7cdd7e9235712506832d5483b91470f3d096dfc08ed8d6e649be9b832cdc9586',
        4609=>'7c4c45d4c6acb4604181fcb4055ba627ad5a77d8e0d0070210d786059c846b3c',
        12520=>'0cd7378495da9ada8cd438b59bdf71e2f9df1f52254ac219dd509e09c20a6084',
        12839=>'1eb16a6e5fb5b9a490e778baf8f7a8ad0d69785867bcdbc06cf9d4c669b8fc5d',
        13686=>'d5f54e4f44513e560d15665631837a49c546d8f850758f57cd1355d4f2d55824',
        13066=>'ff67f53c68f7778b960c2c79c519a9df18f5ae1c643c5b5be66a6975b5bb9584',
        4533=>'989035326adf24ec682aba83480be4e25a13bb323f562905596d0a1d883f1a23',
        7619=>'973e560dbad185aa809ff041c0c559f9da221df65426959062ab3e5606f19352',
        9843=>'0a1cc925e268e33c54c6c0d83a5b7f52359192cbaaf79020b74b2831d861748b',
        9842=>'ee08b3827ba993cece3fcf06ed9050f26f6800f2d7e41f324f330ac791513331',
        10377=>'3261f6486430559d0d9ee19419b00af46f6b58ecb03b003d909bb1c15f125ad4',
        4539=>'f87104fa4c3b98b8d71fffccebc59d9b0c7f4df17c18d9dec3beba9bc3a71a62',
        4538=>'8bcabe6ef37c8ed51ed494fc30f829397926271637d43ca5ba790a31f84af144',
        4601=>'6673483498f16799e2117ec4afdf9531897b320a2097f80e67b2af81a4cf9abb',
    ];

    private static function requireThat(bool $ok, string $code): void
    {
        if (!$ok) throw new RuntimeException('local19_'.$code);
    }

    private static function number(mixed $value): int
    {
        self::requireThat((is_int($value) || is_string($value))
            && preg_match('/^[1-9][0-9]{0,14}$/D',(string)$value)===1, 'integer');
        return (int)$value;
    }

    private static function json(array $value): string
    {
        return json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    }

    private static function object(string $raw): array
    {
        self::requireThat(strlen($raw)<=1048576 && preg_match('/^\s*\{/D',$raw)===1,'json_bound');
        $value=json_decode($raw,true,64,JSON_THROW_ON_ERROR);
        self::requireThat(is_array($value),'json_object');
        return $value;
    }

    private static function hashMatches(mixed $pin, string $raw): bool
    {
        return is_string($pin) && preg_match('/^[a-f0-9]{64}$/D',$pin)===1
            && hash_equals($pin,hash('sha256',$raw));
    }

    private static function project(array $row, array $columns): array
    {
        $out=[];
        foreach ($columns as $column) {
            self::requireThat(array_key_exists($column,$row),'column_missing');
            $out[$column]=$row[$column];
        }
        return $out;
    }

    private static function indexed(array $rows, string $key): array
    {
        $out=[];
        foreach ($rows as $row) {
            $id=self::number($row[$key]??null);
            self::requireThat(!isset($out[$id]),'duplicate_row');
            $out[$id]=$row;
        }
        return $out;
    }

    private static function select(PDO $db, string $sql, array $args, int $maximum, bool $lock=false): array
    {
        $query=$db->prepare($sql.' LIMIT '.($maximum+1).($lock?' FOR UPDATE':''));
        self::requireThat($query!==false && $query->execute($args),'select_failed');
        $rows=$query->fetchAll(PDO::FETCH_ASSOC);
        self::requireThat(count($rows)<=$maximum,'row_bound');
        return $rows;
    }

    private static function stateRows(PDO $db, bool $lock): array
    {
        $pairs=[...self::PAIRS,...self::EXCLUDED_PAIRS,...self::UNCHANGED_PAIRS];
        $ownIds=array_column($pairs,0); $tvIds=array_column($pairs,1);
        $marks=implode(',',array_fill(0,26,'?'));
        // Indexed predicates also lock absent own/TV keys and source owner ranges.
        return [
            'own'=>self::select($db,"SELECT * FROM anytour_hotels WHERE id IN ($marks) ORDER BY id",$ownIds,26,$lock),
            'sources'=>self::select($db,"SELECT * FROM anytour_hotel_sources
                WHERE anytour_hotel_id IN ($marks) ORDER BY anytour_hotel_id,namespace,external_key",$ownIds,78,$lock),
            'legacy'=>self::select($db,"SELECT * FROM anytour_hotel_sources
                WHERE namespace='legacy_catalog' AND external_key IN ($marks) ORDER BY external_key",
                array_map('strval',$tvIds),26,$lock),
            'targets'=>self::select($db,"SELECT * FROM local_tv_hotels WHERE id IN ($marks) ORDER BY id",$tvIds,26,$lock),
            'links_by_own'=>self::select($db,"SELECT * FROM local_tv_legacy_links
                WHERE old_local_id IN ($marks) ORDER BY old_local_id",$ownIds,26,$lock),
            'links_by_tv'=>self::select($db,"SELECT * FROM local_tv_legacy_links
                WHERE tv_id IN ($marks) ORDER BY old_local_id",$tvIds,26,$lock),
        ];
    }

    private static function guards(array $rows): array
    {
        $activeTvIds=array_column(self::PAIRS,1);
        $preserved=array_values(array_filter($rows['targets'],
            static fn(array $row): bool => in_array((int)$row['id'],$activeTvIds,true)));
        foreach ($preserved as &$row) unset($row['manual_json'],$row['revision']);
        unset($row);
        $pairs=[...self::EXCLUDED_PAIRS,...self::UNCHANGED_PAIRS];
        $ownIds=array_column($pairs,0); $tvIds=array_column($pairs,1);
        $excluded=[];
        foreach (['own'=>'id','sources'=>'anytour_hotel_id','targets'=>'id',
            'links_by_own'=>'old_local_id','links_by_tv'=>'tv_id'] as $group=>$id) {
            $ids=in_array($group,['targets','links_by_tv'],true)?$tvIds:$ownIds;
            $excluded[$group]=array_values(array_filter($rows[$group],
                static fn(array $row): bool => in_array((int)$row[$id],$ids,true)));
        }
        return [
            'canonical_rows_sha256'=>hash('sha256',self::json(['own'=>$rows['own'],'sources'=>$rows['sources'],'legacy'=>$rows['legacy']])),
            'target_preserved_rows_sha256'=>hash('sha256',self::json($preserved)),
            'excluded_rows_sha256'=>hash('sha256',self::json($excluded)),
        ];
    }

    private static function sameObject(array $actual, array $expected): bool
    {
        ksort($actual,SORT_STRING); ksort($expected,SORT_STRING); return $actual===$expected;
    }

    private static function arrayJson(string $raw): array
    {
        self::requireThat(strlen($raw)<=1048576,'json_bound');
        $value=json_decode($raw,true,64,JSON_THROW_ON_ERROR);
        self::requireThat(is_array($value),'json_shape'); return $value;
    }

    private static function valueHash(mixed $value): string
    {
        return hash('sha256',json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
    }

    private static function optionalId(mixed $value): ?int
    {
        if ((!is_int($value) && !is_string($value)) || preg_match('/^[1-9][0-9]{0,14}$/D',(string)$value)!==1) return null;
        return (int)$value;
    }

    private static function text(mixed $value): ?string
    {
        if ($value===null) return null;
        self::requireThat(is_string($value) && strlen($value)<=1024 && !preg_match('/[\x00-\x1f]/',$value),'identity_text');
        return $value;
    }

    private static function identity(array $value): array
    {
        foreach (['country','region'] as $place) foreach (['name','id'] as $field) {
            $key=$place.'.'.$field;
            if (array_key_exists($key,$value)) {
                if (!is_array($value[$place]??null)) $value[$place]=[];
                $value[$place][$field]=$value[$key];
            }
        }
        $country=$value['country']??null; $region=$value['region']??null;
        return ['name'=>self::text($value['name']??null),
            'country'=>self::text(is_array($country)?($country['name']??null):$country),
            'region'=>self::text(is_array($region)?($region['name']??null):$region),
            'country_id'=>is_array($country)?self::optionalId($country['id']??null):null,
            'region_id'=>is_array($region)?self::optionalId($region['id']??null):null];
    }

    private static function payloadId(array $value): ?int
    {
        if (array_key_exists('id',$value)) return self::optionalId($value['id']);
        foreach ([$value['hotel']??null,$value['data']['hotel']??null,$value['data']??null] as $item)
            if (is_array($item) && array_key_exists('id',$item)) return self::optionalId($item['id']);
        return null;
    }

    private static function identityAgreement(array $left,array $right): void
    {
        foreach (['name','country','region'] as $field) self::requireThat(
            is_string($left[$field]??null) && $left[$field]!=='' && ($right[$field]??null)===$left[$field],'identity_agreement');
        foreach (['country_id','region_id'] as $field) if ($left[$field]!==null && $right[$field]!==null)
            self::requireThat($left[$field]===$right[$field],'geography_agreement');
    }

    private static function validateTargetHashes(array $target,array $pin): void
    {
        foreach (['discovery','content','manual','source_absent'] as $field)
            self::requireThat(self::hashMatches($pin[$field.'_sha256']??null,$target[$field.'_json']),'target_hash_drift');
        foreach (['discovery','content','manual'] as $field) self::requireThat(
            self::sameObject(self::identity(self::arrayJson($target[$field.'_json'])),$pin[$field.'_identity']??[]),'target_identity');
        $manual=self::arrayJson($target['manual_json']); $keys=array_keys($manual); sort($keys,SORT_STRING);
        self::requireThat($keys===($pin['manual_fields']??null),'target_manual_fields');
    }

    private static function validateHilton(array $target,array $link,array $before): void
    {
        $pin=$before['target']; $bridge=$before['bridge'];
        self::requireThat(self::number($target['id'])===80008 && $target['state']==='ready'
            && self::number($target['revision'])===3 && ($pin['source_present']??null)===true
            && ($pin['source_integrity']??null)===true && ($pin['source_identity_id']??null)===80008
            && is_string($target['source_json'])
            && self::hashMatches($target['source_sha256'],$target['source_json'])
            && self::hashMatches($pin['source_sha256']??null,$target['source_json'])
            && self::payloadId(self::object($target['source_json']))===80008,'unchanged_target_drift');
        self::validateTargetHashes($target,$pin);
        self::requireThat(count($link)===6 && self::number($link['old_local_id'])===4172 && self::number($link['tv_id'])===80008
            && self::hashMatches($link['snapshot_sha256'],$link['snapshot_json'])
            && self::hashMatches($bridge['snapshot_sha256']??null,$link['snapshot_json'])
            && self::hashMatches($bridge['migration_issues_sha256']??null,$link['migration_issues_json']),'unchanged_bridge_drift');
        $snapshot=self::object($link['snapshot_json']); $profile=$snapshot['profile']??[]; $sources=$snapshot['sources']??[];
        // The retained read measured these fields, not every historical object key.
        // The exact outer raw hash protects the complete preserved snapshot.
        self::requireThat(is_array($profile)
            && self::number($profile['id']??null)===4172 && self::number($profile['revision']??null)===1
            && is_string($profile['profile_json']??null)
            && self::hashMatches($profile['profile_sha256']??null,$profile['profile_json'])
            && self::hashMatches($bridge['snapshot_profile_sha256']??null,$profile['profile_json'])
            && array_is_list($sources) && count($sources)===2,'unchanged_snapshot_drift');
        foreach ($sources as $source) {
            self::requireThat(is_array($source) && count($source)===7,'unchanged_snapshot_source');
            foreach (['namespace','external_key','acquired_via','source_json','source_sha256','first_seen_at','last_seen_at'] as $field)
                self::requireThat(is_string($source[$field]??null),'unchanged_snapshot_source');
            self::requireThat(self::hashMatches($source['source_sha256'],$source['source_json']),'unchanged_snapshot_source');
        }
        $issues=self::object($link['migration_issues_json']); $keys=array_keys($issues); sort($keys,SORT_STRING);
        self::requireThat($keys===($bridge['migration_issue_fields']??null),'unchanged_migration_fields');
    }

    private static function activeRows(array $rows,string $key,int $pairColumn): array
    {
        $ids=array_column(self::PAIRS,$pairColumn);
        return array_values(array_filter($rows,static fn(array $row): bool => in_array((int)$row[$key],$ids,true)));
    }

    private static function proposal(array $proposal): array
    {
        self::requireThat(($proposal['schema_version']??null)===2
            && ($proposal['proposal_scope_frozen']??null)===true
            && is_array($proposal['pairs']??null) && count($proposal['pairs'])===19
            && is_array($proposal['excluded_pairs']??null) && count($proposal['excluded_pairs'])===6
            && is_array($proposal['unchanged_existing_pairs']??null) && count($proposal['unchanged_existing_pairs'])===1
            && ($proposal['conflicting_pairs']??null)===[],'proposal_shape');
        self::requireThat(($proposal['content_authority']['proposed_actions_sha256']??null)===self::ACTIONS_SHA256
            && ($proposal['content_authority']['manifest_sha256']??null)==='7843b25c32c7adb8fa88f262aa870d8a469727ac3681f038ae418b98c0375138',
            'content_authority');
        $counts=['bridge_inserts'=>19,'native_target_updates'=>19,'native_manual_field_values'=>57,
            'database_rows_changed'=>38,'target_revision_from'=>1,'target_revision_to'=>2,
            'own_profile_updates'=>0,'own_source_updates'=>0,'existing_bridge_updates'=>0,'new_native_target_inserts'=>0,
            'native_identity_acceptances_beyond_the_exact_proposed_legacy_bridges'=>0,
            'supplier_or_provider_http_calls'=>0,'publication_writes'=>0,'ai_bundle_writes'=>0,'ai_acceptances'=>0];
        self::requireThat(self::sameObject($proposal['requested_delta']??[],$counts),'proposal_delta');
        $expected=[...self::PAIRS,...self::EXCLUDED_PAIRS,...self::UNCHANGED_PAIRS];
        $entries=[...$proposal['pairs'],...$proposal['excluded_pairs'],...$proposal['unchanged_existing_pairs']];
        foreach ($entries as $i=>$entry) {
            [$ownId,$tvId]=$expected[$i];
            self::requireThat(($entry['own_id']??null)===$ownId && ($entry['tv_id']??null)===$tvId,'fixed_scope');
            $before=$entry['before']??[]; $isHilton=$ownId===4172;
            self::requireThat(($before['own']['own_id']??null)===$ownId
                && ($before['own']['revision']??null)===2 && ($before['own']['active']??null)===true
                && ($before['own']['profile_integrity']??null)===true
                && ($before['expected_legacy_owners']??null)===[$ownId]
                && ($before['target_link_owners']??null)===($isHilton?[$ownId]:[])
                && ($before['linked_target_link_owners']??null)===($isHilton?[$ownId]:[])
                && array_key_exists('linked_target',$before) && $before['linked_target']===null
                && is_array($before['own_sources']??null) && count($before['own_sources'])===3,'parent_shape');
            $fields=$isHilton?['description']:self::FIELDS;
            self::requireThat(($entry['approved_content_fields']??null)===$fields
                && is_array($before['own']['approved_fields']??null),'approved_field_scope');
            $pins=$before['own']['approved_fields'];
            $names=array_keys($pins); $wanted=$fields; sort($names,SORT_STRING); sort($wanted,SORT_STRING);
            self::requireThat($names===$wanted,'approved_field_scope');
            foreach ($fields as $field) {
                $pin=$pins[$field]??[];
                self::requireThat(($pin['present']??null)===true && ($pin['matches']??null)===true
                    && is_string($pin['sha256']??null) && preg_match('/^[a-f0-9]{64}$/D',$pin['sha256'])===1
                    && ($pin['expected_sha256']??null)===$pin['sha256'],'approved_field_pin');
            }
            if ($isHilton) {
                self::requireThat(($entry['proposed_changes']??null)===[] && is_array($before['bridge']??null)
                    && ($before['bridge']['own_id']??null)===$ownId && ($before['bridge']['tv_id']??null)===$tvId
                    && ($before['bridge']['snapshot_integrity']??null)===true
                    && ($before['bridge']['snapshot_own_id']??null)===$ownId
                    && ($before['bridge']['snapshot_revision']??null)===1
                    && ($before['bridge']['snapshot_profile_integrity']??null)===true
                    && ($before['bridge']['snapshot_source_count']??null)===2
                    && ($before['bridge']['snapshot_sources_integrity']??null)===true
                    && ($before['target']['tv_id']??null)===$tvId && ($before['target']['revision']??null)===3
                    && ($before['target']['state']??null)==='ready','unchanged_scope');
                continue;
            }
            self::requireThat(array_key_exists('bridge',$before) && $before['bridge']===null,'absent_bridge_pin');
            if ($i>=19) {
                self::requireThat(array_key_exists('target',$before) && $before['target']===null
                    && ($entry['proposed_changes']??null)===[],'excluded_scope');
                continue;
            }
            $target=$entry['proposed_after']['target']??[];
            $raw=$target['manual_json_exact_utf8']??null;
            self::requireThat(is_string($raw) && self::hashMatches(self::MANUAL_SHA256[$ownId],$raw)
                && ($target['manual_json_sha256']??null)===self::MANUAL_SHA256[$ownId],'approved_manual_hash');
            $manual=self::object($raw);
            self::requireThat(array_keys($manual)===self::FIELDS && $manual===($target['manual_json']??null)
                && ($target['changed_columns']??null)===['manual_json','revision']
                && ($target['manual_fields']??null)===self::FIELDS
                && ($target['revision']??null)===2 && ($target['preserved_state']??null)==='pending'
                && array_key_exists('preserved_source_json',$target) && $target['preserved_source_json']===null
                && array_key_exists('preserved_source_sha256',$target) && $target['preserved_source_sha256']===null
                && ($target['preserved_source_absent_json']??null)===[]
                && ($target['expected_details_available']??null)===false,'approved_manual_shape');
            foreach (self::FIELDS as $field) self::requireThat(
                self::valueHash($manual[$field])===$pins[$field]['sha256'],'approved_value_pin');
            $bridge=$entry['proposed_after']['bridge']??[];
            self::requireThat(($bridge['old_local_id']??null)===$ownId && ($bridge['tv_id']??null)===$tvId
                && ($bridge['profile_columns']??null)===['id','profile_json','profile_sha256','revision','is_active']
                && ($bridge['source_columns']??null)===['namespace','external_key','acquired_via','source_json','source_sha256','first_seen_at','last_seen_at']
                && ($bridge['source_order']??null)===['namespace','external_key']
                && ($bridge['migration_issues_json']??null)===['preservedFields'=>self::FIELDS,'originKnown'=>true], 'approved_bridge');
        }
        return $entries;
    }

    private static function validateBefore(array $rows, array $entries): void
    {
        self::requireThat(count($rows['own'])===26 && count($rows['sources'])===78
            && count($rows['legacy'])===26 && count($rows['targets'])===20
            && count($rows['links_by_own'])===1 && count($rows['links_by_tv'])===1
            && $rows['links_by_own']===$rows['links_by_tv'],'fresh_counts_or_links');
        $own=self::indexed($rows['own'],'id'); $targets=self::indexed($rows['targets'],'id');
        $links=self::indexed($rows['links_by_own'],'old_local_id');
        self::requireThat(array_keys($links)===[4172] && self::number($links[4172]['tv_id'])===80008,'existing_bridge_scope');
        $legacy=[];
        foreach ($rows['legacy'] as $row) {
            self::requireThat(!isset($legacy[$row['external_key']]),'legacy_ambiguous');
            $legacy[$row['external_key']]=$row;
        }
        $sources=[];
        foreach ($rows['sources'] as $row) $sources[self::number($row['anytour_hotel_id'])][]=$row;
        foreach ($entries as $i=>$entry) {
            $ownId=$entry['own_id']; $tvId=$entry['tv_id']; $before=$entry['before'];
            self::requireThat(isset($own[$ownId]) && isset($sources[$ownId]) && count($sources[$ownId])===3,'own_missing');
            $row=$own[$ownId]; $raw=$row['profile_json'];
            self::requireThat(self::number($row['revision'])===2 && (string)$row['is_active']==='1'
                && self::hashMatches($row['profile_sha256'],$raw)
                && self::hashMatches($before['own']['profile_sha256']??null,$raw),'own_drift');
            $profile=self::object($raw); $ownIdentity=self::identity($profile);
            self::requireThat(self::sameObject($ownIdentity,$before['own']['identity']??[]),'own_identity');
            foreach ($before['own']['approved_fields'] as $field=>$pin) self::requireThat(
                array_key_exists($field,$profile) && self::valueHash($profile[$field])===$pin['sha256'],'approved_value_drift');
            $sourceRules=[['anytour_local_id',(string)$tvId,'canonical_local_alias_v1'],
                ['legacy_catalog',(string)$tvId,'saved_catalog'],
                ['profile_enrichment:official_review_v1',self::ACTIONS_SHA256.':'.$ownId,'profile_fill_official_v1']];
            $legacyIdentity=null;
            foreach ($sources[$ownId] as $j=>$source) {
                [$namespace,$key,$via]=$sourceRules[$j]; $pin=$before['own_sources'][$j];
                self::requireThat($source['namespace']===$namespace && $source['external_key']===$key
                    && $source['acquired_via']===$via && ($pin['namespace']??null)===$namespace
                    && ($pin['external_key']??null)===$key && ($pin['acquired_via']??null)===$via
                    && ($pin['own_id']??null)===$ownId && ($pin['source_integrity']??null)===true
                    && self::hashMatches($source['source_sha256'],$source['source_json'])
                    && self::hashMatches($pin['source_sha256']??null,$source['source_json']),'source_drift');
                $payload=self::object($source['source_json']);
                self::requireThat(self::sameObject(self::identity($payload),$pin['identity']??[])
                    && self::payloadId($payload)===($pin['source_identity_id']??null),'source_identity');
                if ($namespace==='legacy_catalog') {
                    self::requireThat(self::payloadId($payload)===$tvId && ($pin['source_identity_id']??null)===$tvId
                        && isset($legacy[$key]) && $legacy[$key]===$source,'legacy_owner');
                    $legacyIdentity=self::identity($payload);
                    self::identityAgreement($ownIdentity,$legacyIdentity);
                }
            }
            if ($ownId===4172) {
                self::requireThat(isset($targets[$tvId]),'unchanged_target_missing');
                self::validateHilton($targets[$tvId],$links[$ownId],$before);
                self::identityAgreement($legacyIdentity,self::identity(self::arrayJson($targets[$tvId]['content_json'])));
                continue;
            }
            self::requireThat(!isset($links[$ownId]),'bridge_present');
            if ($i>=19) {
                self::requireThat(!isset($targets[$tvId]),'excluded_target_present');
                continue;
            }
            self::requireThat(isset($targets[$tvId]),'target_missing');
            $target=$targets[$tvId]; $pin=$before['target'];
            self::requireThat(($pin['tv_id']??null)===$tvId && ($pin['state']??null)==='pending'
                && ($pin['revision']??null)===1 && $target['state']==='pending'
                && self::number($target['revision'])===1 && $target['content_json']==='{}'
                && $target['manual_json']==='{}' && $target['source_json']===null && $target['source_sha256']===null
                && $target['source_absent_json']==='[]'
                && ($pin['source_present']??null)===false && ($pin['source_sha256']??null)===null
                && ($pin['source_integrity']??null)===null && ($pin['source_identity_id']??null)===null,'target_drift');
            self::validateTargetHashes($target,$pin);
            $discovery=self::identity(self::object($target['discovery_json']));
            self::identityAgreement($legacyIdentity,$discovery);
            foreach (self::FIELDS as $field) self::requireThat(array_key_exists($field,$profile)
                && $profile[$field]===$entry['proposed_after']['target']['manual_json'][$field],'approved_value_drift');
        }
    }

    private static function plans(array $rows, array $entries): array
    {
        $own=self::indexed($rows['own'],'id'); $plans=[]; $now=gmdate('Y-m-d H:i:s');
        foreach (array_slice($entries,0,19) as $entry) {
            $id=$entry['own_id']; $sources=[];
            foreach ($rows['sources'] as $source) if ((int)$source['anytour_hotel_id']===$id)
                $sources[]=self::project($source,['namespace','external_key','acquired_via','source_json','source_sha256','first_seen_at','last_seen_at']);
            // Existing bridge format, with raw stored JSON strings, not a sanitized projection.
            $snapshot=self::json(['profile'=>self::project($own[$id],['id','profile_json','profile_sha256','revision','is_active']),
                'sources'=>$sources]);
            $plans[]=['own_id'=>$id,'tv_id'=>$entry['tv_id'],'manual_json'=>$entry['proposed_after']['target']['manual_json_exact_utf8'],
                'manual_sha256'=>self::MANUAL_SHA256[$id],'snapshot_json'=>$snapshot,'snapshot_sha256'=>hash('sha256',$snapshot),
                'migration_issues_json'=>self::json(['preservedFields'=>self::FIELDS,'originKnown'=>true]),'migrated_at'=>$now];
        }
        return $plans;
    }

    private static function verifyTargets(array $rows, array $plans): void
    {
        self::requireThat(count($rows)===19,'readback_target_count');
        $targets=self::indexed($rows,'id');
        foreach ($plans as $plan) {
            $row=$targets[$plan['tv_id']]??[];
            self::requireThat(($row['manual_json']??null)===$plan['manual_json']
                && self::hashMatches($plan['manual_sha256'],$row['manual_json'])
                && self::number($row['revision']??null)===2 && ($row['state']??null)==='pending'
                && ($row['content_json']??null)==='{}' && array_key_exists('source_json',$row) && $row['source_json']===null
                && array_key_exists('source_sha256',$row) && $row['source_sha256']===null,'readback_target_drift');
        }
    }

    private static function verifyLinks(array $rows, array $plans): void
    {
        self::requireThat(count($rows)===19,'readback_link_count');
        $links=self::indexed($rows,'old_local_id');
        foreach ($plans as $plan) {
            $row=$links[$plan['own_id']]??[];
            self::requireThat(count($row)===6 && self::number($row['tv_id']??null)===$plan['tv_id'],'readback_link_identity');
            foreach (['snapshot_json','snapshot_sha256','migration_issues_json','migrated_at'] as $field)
                self::requireThat(($row[$field]??null)===$plan[$field],'readback_link_drift');
            self::requireThat(self::hashMatches($row['snapshot_sha256'],$row['snapshot_json']),'readback_snapshot_hash');
        }
    }

    private static function result(): array
    {
        return ['schema_version'=>'anytour_local19_bridge_apply_v1','operation_id'=>self::OPERATION_ID,
            'state'=>'rejected_before_write','completed_at_utc'=>gmdate('c'),'proposal_sha256'=>self::PROPOSAL_SHA256,
            'no_replay'=>true,'supplier_calls'=>0,'provider_http_calls'=>0,
            'database_writes'=>0,'mapping_writes'=>0,'native_updates'=>0,'manual_field_writes'=>0,
            'canonical_profile_writes'=>0,'source_writes'=>0,'new_target_inserts'=>0,'accepted_native_bindings'=>0,
            'commit_acknowledged'=>false,'readback_verified'=>false,'before'=>null,'after'=>null,
            'checks'=>['fresh_parent_match'=>false,'canonical_unchanged'=>null,'target_preserved'=>null,
                'excluded_unchanged'=>null,'direct_tv_sql'=>false,'own_to_tv_sql'=>false,'snapshots_verified'=>false],
            'pairs'=>[],'error'=>null];
    }

    private static function error(Throwable $error): array
    {
        $message=$error->getMessage();
        return ['code'=>preg_match('/^local19_[a-z0-9_]{1,88}$/D',$message)===1?$message:
            ($error instanceof PDOException?'database_error':'execution_error'),'message_sha256'=>hash('sha256',$message)];
    }

    public static function reject(Throwable $error): array
    {
        $result=self::result(); $result['error']=self::error($error); return $result;
    }

    private static function counts(array &$result, ?int $rows): void
    {
        $result['database_writes']=$rows;
        $result['mapping_writes']=$rows===null?null:($rows===38?19:0);
        $result['native_updates']=$result['mapping_writes'];
        $result['manual_field_writes']=$rows===null?null:($rows===38?57:0);
    }

    public static function run(PDO $db, array $proposal): array
    {
        $result=self::result(); $started=false; $writeAttempted=false; $committing=false; $readback=false;
        try {
            $entries=self::proposal($proposal);
            self::requireThat($db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql' && !$db->inTransaction(),'transaction');
            self::requireThat($db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION),'error_mode');
            self::requireThat($db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ')!==false,'isolation');
            self::requireThat($db->exec('SET TRANSACTION READ WRITE')!==false,'read_write');
            self::requireThat($db->beginTransaction(),'begin'); $started=true;
            $before=self::stateRows($db,true);
            // The table reads hold metadata locks before checking engines and write triggers.
            $tables=self::select($db,'SELECT TABLE_NAME,ENGINE,TABLE_TYPE FROM information_schema.TABLES
                WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN (?,?,?,?) ORDER BY TABLE_NAME',
                ['anytour_hotels','anytour_hotel_sources','local_tv_hotels','local_tv_legacy_links'],4);
            self::requireThat(count($tables)===4,'schema');
            foreach ($tables as $table) self::requireThat($table['ENGINE']==='InnoDB' && $table['TABLE_TYPE']==='BASE TABLE','transactional_tables');
            $triggers=self::select($db,'SELECT TRIGGER_NAME FROM information_schema.TRIGGERS
                WHERE EVENT_OBJECT_SCHEMA=DATABASE() AND EVENT_OBJECT_TABLE IN (?,?)',
                ['local_tv_hotels','local_tv_legacy_links'],0);
            self::requireThat($triggers===[],'write_triggers');
            self::validateBefore($before,$entries);
            $result['before']=self::guards($before); $result['checks']['fresh_parent_match']=true;
            $plans=self::plans($before,$entries);
            $result['pairs']=array_map(static fn(array $plan): array => ['own_id'=>$plan['own_id'],'tv_id'=>$plan['tv_id'],
                'revision_from'=>1,'revision_to'=>2,'manual_fields'=>self::FIELDS,'manual_sha256'=>$plan['manual_sha256'],
                'snapshot_sha256'=>$plan['snapshot_sha256'],'direct_tv_sql'=>false,'own_to_tv_sql'=>false],$plans);
            $insert=$db->prepare('INSERT INTO local_tv_legacy_links
                (old_local_id,tv_id,snapshot_json,snapshot_sha256,migration_issues_json,migrated_at) VALUES (?,?,?,?,?,?)');
            $update=$db->prepare("UPDATE local_tv_hotels SET manual_json=?,revision=revision+1
                WHERE id=? AND revision=1 AND state='pending' AND BINARY content_json='{}' AND BINARY manual_json='{}'
                AND source_json IS NULL AND source_sha256 IS NULL");
            self::requireThat($insert!==false && $update!==false,'prepare_writes');
            foreach ($plans as $plan) {
                $writeAttempted=true;
                self::requireThat($insert->execute([$plan['own_id'],$plan['tv_id'],$plan['snapshot_json'],$plan['snapshot_sha256'],
                    $plan['migration_issues_json'],$plan['migrated_at']]) && $insert->rowCount()===1,'insert_count');
                self::requireThat($update->execute([$plan['manual_json'],$plan['tv_id']]) && $update->rowCount()===1,'update_count');
            }
            // Detect all unintended column changes while rollback is still possible.
            $written=self::stateRows($db,true);
            self::requireThat(self::guards($written)===$result['before'],'precommit_preservation');
            self::verifyTargets(self::activeRows($written['targets'],'id',1),$plans);
            self::verifyLinks(self::activeRows($written['links_by_own'],'old_local_id',0),$plans); self::verifyLinks(self::activeRows($written['links_by_tv'],'tv_id',1),$plans);
            $committing=true;
            self::requireThat($db->commit(),'commit_not_acknowledged');
            $result['commit_acknowledged']=true; self::counts($result,38);
            self::requireThat(!$db->inTransaction(),'commit_still_in_transaction');

            // A new read-only snapshot, through both bounded SQL paths. This is not a public HTTP read.
            self::requireThat($db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ')!==false,'readback_isolation');
            self::requireThat($db->exec('SET TRANSACTION READ ONLY')!==false,'readback_read_only');
            self::requireThat($db->beginTransaction(),'readback_begin'); $readback=true;
            $after=self::stateRows($db,false); $result['after']=self::guards($after);
            foreach (['canonical_unchanged'=>'canonical_rows_sha256','target_preserved'=>'target_preserved_rows_sha256',
                'excluded_unchanged'=>'excluded_rows_sha256'] as $check=>$key)
                $result['checks'][$check]=hash_equals($result['before'][$key],$result['after'][$key]);
            self::requireThat($result['before']===$result['after'],'readback_preservation');
            self::verifyTargets(self::activeRows($after['targets'],'id',1),$plans); $result['checks']['direct_tv_sql']=true;
            self::verifyLinks(self::activeRows($after['links_by_own'],'old_local_id',0),$plans); self::verifyLinks(self::activeRows($after['links_by_tv'],'tv_id',1),$plans);
            $result['checks']['snapshots_verified']=true;
            $marks=implode(',',array_fill(0,19,'?'));
            $joined=self::select($db,"SELECT l.*,b.old_local_id FROM local_tv_legacy_links b
                JOIN local_tv_hotels l ON l.id=b.tv_id WHERE b.old_local_id IN ($marks)
                AND l.state<>'excluded' ORDER BY b.old_local_id",array_column(self::PAIRS,0),19);
            self::requireThat(count($joined)===19,'readback_join_count');
            $byOwn=self::indexed($joined,'old_local_id'); $direct=self::indexed($after['targets'],'id');
            foreach ($plans as $plan) {
                $row=$byOwn[$plan['own_id']]??[]; unset($row['old_local_id']);
                self::requireThat($row===$direct[$plan['tv_id']],'readback_join_drift');
            }
            $result['checks']['own_to_tv_sql']=true;
            self::requireThat($db->rollBack() && !$db->inTransaction(),'readback_close'); $readback=false;
            foreach ($result['pairs'] as &$pair) { $pair['direct_tv_sql']=true; $pair['own_to_tv_sql']=true; }
            unset($pair);
            $result['readback_verified']=true; $result['state']='committed_verified';
        } catch (Throwable $error) {
            $result['error']=self::error($error);
            if ($result['commit_acknowledged']) {
                $result['state']='post_commit_readback_unknown';
                if ($readback) {
                    try { if ($db->inTransaction()) $db->rollBack(); } catch (Throwable $ignored) {}
                }
            } elseif ($committing) {
                // A thrown COMMIT may have succeeded remotely. Never infer rollback or replay.
                $result['state']='commit_unknown'; self::counts($result,null);
            } elseif ($started) {
                try {
                    self::requireThat($db->inTransaction() && $db->rollBack() && !$db->inTransaction(),'rollback_not_acknowledged');
                    $result['state']=$writeAttempted?'rolled_back':'rejected_before_write';
                } catch (Throwable $rollbackError) {
                    $result['state']='rollback_unknown'; self::counts($result,null);
                }
            }
        }
        $result['completed_at_utc']=gmdate('c');
        return $result;
    }
}

if (!defined('ANYTOUR_LOCAL19_BRIDGE_APPLY_LIBRARY_ONLY')) {
    try {
        if (getenv('MATCH_LOCAL19_EXECUTION_ADMITTED')!=='1')
            throw new RuntimeException('local19_execution_not_admitted');
        $home=(string)getenv('HOME'); $root=$home.'/www/anytoour.ru';
        $dir=$home.'/.anytoour-int-executor/'.AnyTourLocal19BridgeApplyV1::OPERATION_ID;
        $stage=(string)getenv('MATCH_RETAINED_SOURCE_ROOT');
        $proposalFile=$stage.'/scripts/catalog/anytour-local19-bridge-proposal-20261123.json';
        $loader=$stage.'/v2/data/db-v1.php'; $config=$root.'/config.php';
        if (PHP_SAPI!=='cli' || count($argv)!==1 || getenv('MATCH_RETAINED_DIR')!==$dir
            || getcwd()!==$root || realpath($root)!==$root || realpath($dir)!==$dir
            || realpath($stage)!==$dir.'/source'
            || realpath(__FILE__)!==$stage.'/scripts/catalog/anytour_local19_bridge_apply_v1.php')
            throw new RuntimeException('local19_paths');
        foreach ([$proposalFile,$loader,$config] as $file)
            if (!is_file($file) || is_link($file) || realpath($file)!==$file) throw new RuntimeException('local19_file_path');
        $raw=file_get_contents($proposalFile);
        if (!is_string($raw) || strlen($raw)!==268469 || !hash_equals(AnyTourLocal19BridgeApplyV1::PROPOSAL_SHA256,hash('sha256',$raw)))
            throw new RuntimeException('local19_proposal_hash');
        $proposal=json_decode($raw,true,64,JSON_THROW_ON_ERROR);
        $_SERVER['DOCUMENT_ROOT']=$root; require $config; require $loader;
        $result=AnyTourLocal19BridgeApplyV1::run(v2_data_db(),$proposal);
    } catch (Throwable $error) {
        $result=AnyTourLocal19BridgeApplyV1::reject($error);
    }
    echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
    exit($result['state']==='committed_verified'?0:1);
}
