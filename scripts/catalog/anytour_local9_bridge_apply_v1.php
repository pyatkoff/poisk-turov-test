<?php
declare(strict_types=1);

/**
 * One approved LOCAL9 packet. No discovery, provider client, migration, or retry.
 * The CLI authenticates the frozen proposal bytes; run() accepts that trusted manifest.
 */
final class AnyTourLocal9BridgeApplyV1
{
    public const OPERATION_ID = 'int-andromeda-local9-bridge-apply-20261123-v1';
    public const PROPOSAL_SHA256 = '4ffb86ff073be9b9b0d2f89bf29d508099a22f94e2e5bbdf03e3c3a218fedc55';
    public const APPROVAL_REF = 'https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-7086996252';
    public const PAIRS = [[5371,12120],[6014,64957],[6202,76029],[5160,66198],[5794,49750],
        [7218,123247],[12008,48184],[5598,32322],[10771,28272]];
    public const EXCLUDED_PAIRS = [[4516,2526],[8689,7458]];
    public const FIELDS = ['description','primaryImage','images'];
    private const ACTIONS_SHA256 = '45e11557da29ecc652589e5cc89a6dcfa457b57e1a8936fe87d57d2ceb12edb4';
    private const MANUAL_SHA256 = [
        5371=>'f6356e45bac53f8c2c7a88c4470efec7d09cda6d4a5cba69914383b03e03d651',
        6014=>'5fd49755df7d4abe7b9b2b45aefd7a3f532618005d4f93670421480048e52cc4',
        6202=>'574c75fc5369dd898416e52728371f2d3b5f8e98acd24c7fc20aec7e6fd58e33',
        5160=>'84e6876908884f8f173e1978b32de19c8d6049f66964d985a0a1dd84cd67cfa2',
        5794=>'41cb4867d5704205b687981fc91db531c903c6302351825f2547155b5eed8a67',
        7218=>'1abe89f5849383275c910bb9daaf74abb02d2af168415e22c6809d4e6861ed36',
        12008=>'47d2dd2b58cd23d619a361d8a9caa08cde76cb9fa1860b6e0c93f314331babb5',
        5598=>'8b4b6a722032d4af2a74e434ccd3f398c1c336eaac9962b28f3b1efecdc50ef5',
        10771=>'1b29726139bfb05effe46309f697b9d9d3f85dbd06344472c6060ec18beabd09',
    ];

    private static function requireThat(bool $ok, string $code): void
    {
        if (!$ok) throw new RuntimeException('local9_'.$code);
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
        $pairs=[...self::PAIRS,...self::EXCLUDED_PAIRS];
        $ownIds=array_column($pairs,0); $tvIds=array_column($pairs,1);
        $marks=implode(',',array_fill(0,11,'?'));
        // Separate indexed predicates also lock missing own/TV keys and source ranges.
        return [
            'own'=>self::select($db,"SELECT * FROM anytour_hotels WHERE id IN ($marks) ORDER BY id",$ownIds,11,$lock),
            'sources'=>self::select($db,"SELECT * FROM anytour_hotel_sources
                WHERE anytour_hotel_id IN ($marks) ORDER BY anytour_hotel_id,namespace,external_key",$ownIds,33,$lock),
            'legacy'=>self::select($db,"SELECT * FROM anytour_hotel_sources
                WHERE namespace='legacy_catalog' AND external_key IN ($marks) ORDER BY external_key",
                array_map('strval',$tvIds),11,$lock),
            'targets'=>self::select($db,"SELECT * FROM local_tv_hotels WHERE id IN ($marks) ORDER BY id",$tvIds,11,$lock),
            'links_by_own'=>self::select($db,"SELECT * FROM local_tv_legacy_links
                WHERE old_local_id IN ($marks) ORDER BY old_local_id",$ownIds,11,$lock),
            'links_by_tv'=>self::select($db,"SELECT * FROM local_tv_legacy_links
                WHERE tv_id IN ($marks) ORDER BY old_local_id",$tvIds,11,$lock),
        ];
    }

    private static function guards(array $rows): array
    {
        $preserved=$rows['targets'];
        foreach ($preserved as &$row) unset($row['manual_json'],$row['revision']);
        unset($row);
        $ownIds=array_column(self::EXCLUDED_PAIRS,0); $tvIds=array_column(self::EXCLUDED_PAIRS,1);
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

    private static function proposal(array $proposal): array
    {
        self::requireThat(($proposal['schema_version']??null)===1
            && ($proposal['proposal_scope_frozen']??null)===true
            && is_array($proposal['pairs']??null) && count($proposal['pairs'])===9
            && is_array($proposal['excluded_pairs']??null) && count($proposal['excluded_pairs'])===2,'proposal_shape');
        self::requireThat(($proposal['content_authority']['proposed_actions_sha256']??null)===self::ACTIONS_SHA256,'content_authority');
        $expected=[...self::PAIRS,...self::EXCLUDED_PAIRS];
        $entries=[...$proposal['pairs'],...$proposal['excluded_pairs']];
        foreach ($entries as $i=>$entry) {
            [$ownId,$tvId]=$expected[$i];
            self::requireThat(($entry['own_id']??null)===$ownId && ($entry['tv_id']??null)===$tvId,'fixed_scope');
            $before=$entry['before']??[];
            self::requireThat(($before['own']['own_id']??null)===$ownId
                && ($before['own']['revision']??null)===2 && ($before['own']['active']??null)===true
                && ($before['own']['profile_integrity']??null)===true
                && array_key_exists('bridge',$before) && $before['bridge']===null
                && ($before['expected_legacy_owners']??null)===[$ownId]
                && ($before['target_link_owners']??null)===[]
                && is_array($before['own_sources']??null) && count($before['own_sources'])===3,'parent_shape');
            if ($i>=9) {
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
                && ($target['revision']??null)===2,'approved_manual_shape');
            $bridge=$entry['proposed_after']['bridge']??[];
            self::requireThat(($bridge['old_local_id']??null)===$ownId && ($bridge['tv_id']??null)===$tvId
                && ($bridge['migration_issues_json']??null)===['preservedFields'=>self::FIELDS,'originKnown'=>true], 'approved_bridge');
        }
        return $entries;
    }

    private static function validateBefore(array $rows, array $entries): void
    {
        self::requireThat(count($rows['own'])===11 && count($rows['sources'])===33
            && count($rows['legacy'])===11 && count($rows['targets'])===9
            && $rows['links_by_own']===[] && $rows['links_by_tv']===[],'fresh_counts_or_links');
        $own=self::indexed($rows['own'],'id'); $targets=self::indexed($rows['targets'],'id');
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
            $profile=self::object($raw);
            $sourceRules=[['anytour_local_id',(string)$tvId,'canonical_local_alias_v1'],
                ['legacy_catalog',(string)$tvId,'saved_catalog'],
                ['profile_enrichment:official_review_v1',self::ACTIONS_SHA256.':'.$ownId,'profile_fill_official_v1']];
            foreach ($sources[$ownId] as $j=>$source) {
                [$namespace,$key,$via]=$sourceRules[$j]; $pin=$before['own_sources'][$j];
                self::requireThat($source['namespace']===$namespace && $source['external_key']===$key
                    && $source['acquired_via']===$via && ($pin['namespace']??null)===$namespace
                    && ($pin['external_key']??null)===$key && ($pin['acquired_via']??null)===$via
                    && ($pin['own_id']??null)===$ownId && ($pin['source_integrity']??null)===true
                    && self::hashMatches($source['source_sha256'],$source['source_json'])
                    && self::hashMatches($pin['source_sha256']??null,$source['source_json']),'source_drift');
                $payload=self::object($source['source_json']);
                if ($namespace==='legacy_catalog') {
                    $payloadId=$payload['id']??$payload['hotel']['id']??$payload['data']['hotel']['id']??$payload['data']['id']??null;
                    self::requireThat(self::number($payloadId)===$tvId && ($pin['source_identity_id']??null)===$tvId
                        && isset($legacy[$key]) && $legacy[$key]===$source,'legacy_owner');
                }
            }
            if ($i>=9) {
                self::requireThat(!isset($targets[$tvId]),'excluded_target_present');
                continue;
            }
            self::requireThat(isset($targets[$tvId]),'target_missing');
            $target=$targets[$tvId]; $pin=$before['target'];
            self::requireThat(($pin['tv_id']??null)===$tvId && ($pin['state']??null)==='pending'
                && ($pin['revision']??null)===1 && $target['state']==='pending'
                && self::number($target['revision'])===1 && $target['content_json']==='{}'
                && $target['manual_json']==='{}' && $target['source_json']===null && $target['source_sha256']===null
                && $target['source_absent_json']==='[]','target_drift');
            foreach (['discovery','content','manual','source_absent'] as $field)
                self::requireThat(self::hashMatches($pin[$field.'_sha256']??null,$target[$field.'_json']),'target_hash_drift');
            self::object($target['discovery_json']);
            foreach (self::FIELDS as $field) self::requireThat(array_key_exists($field,$profile)
                && $profile[$field]===$entry['proposed_after']['target']['manual_json'][$field],'approved_value_drift');
        }
    }

    private static function plans(array $rows, array $entries): array
    {
        $own=self::indexed($rows['own'],'id'); $plans=[]; $now=gmdate('Y-m-d H:i:s');
        foreach (array_slice($entries,0,9) as $entry) {
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
        self::requireThat(count($rows)===9,'readback_target_count');
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
        self::requireThat(count($rows)===9,'readback_link_count');
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
        return ['schema_version'=>'anytour_local9_bridge_apply_v1','operation_id'=>self::OPERATION_ID,
            'state'=>'rejected_before_write','completed_at_utc'=>gmdate('c'),'proposal_sha256'=>self::PROPOSAL_SHA256,
            'approval_ref'=>self::APPROVAL_REF,'no_replay'=>true,'supplier_calls'=>0,'provider_http_calls'=>0,
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
        return ['code'=>preg_match('/^local9_[a-z0-9_]{1,88}$/D',$message)===1?$message:
            ($error instanceof PDOException?'database_error':'execution_error'),'message_sha256'=>hash('sha256',$message)];
    }

    public static function reject(Throwable $error): array
    {
        $result=self::result(); $result['error']=self::error($error); return $result;
    }

    private static function counts(array &$result, ?int $rows): void
    {
        $result['database_writes']=$rows;
        $result['mapping_writes']=$rows===null?null:($rows===18?9:0);
        $result['native_updates']=$result['mapping_writes'];
        $result['manual_field_writes']=$rows===null?null:($rows===18?27:0);
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
            self::verifyTargets($written['targets'],$plans);
            self::verifyLinks($written['links_by_own'],$plans); self::verifyLinks($written['links_by_tv'],$plans);
            $committing=true;
            self::requireThat($db->commit(),'commit_not_acknowledged');
            $result['commit_acknowledged']=true; self::counts($result,18);
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
            self::verifyTargets($after['targets'],$plans); $result['checks']['direct_tv_sql']=true;
            self::verifyLinks($after['links_by_own'],$plans); self::verifyLinks($after['links_by_tv'],$plans);
            $result['checks']['snapshots_verified']=true;
            $marks=implode(',',array_fill(0,9,'?'));
            $joined=self::select($db,"SELECT l.*,b.old_local_id FROM local_tv_legacy_links b
                JOIN local_tv_hotels l ON l.id=b.tv_id WHERE b.old_local_id IN ($marks)
                AND l.state<>'excluded' ORDER BY b.old_local_id",array_column(self::PAIRS,0),9);
            self::requireThat(count($joined)===9,'readback_join_count');
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

if (!defined('ANYTOUR_LOCAL9_BRIDGE_APPLY_LIBRARY_ONLY')) {
    try {
        $home=(string)getenv('HOME'); $root=$home.'/www/anytoour.ru';
        $dir=$home.'/.anytoour-int-executor/'.AnyTourLocal9BridgeApplyV1::OPERATION_ID;
        $stage=(string)getenv('MATCH_RETAINED_SOURCE_ROOT');
        $proposalFile=$stage.'/scripts/catalog/anytour-local9-bridge-proposal-20261122.json';
        $loader=$stage.'/v2/data/db-v1.php'; $config=$root.'/config.php';
        if (PHP_SAPI!=='cli' || count($argv)!==1 || getenv('MATCH_RETAINED_DIR')!==$dir
            || getcwd()!==$root || realpath($root)!==$root || realpath($dir)!==$dir
            || realpath($stage)!==$dir.'/source'
            || realpath(__FILE__)!==$stage.'/scripts/catalog/anytour_local9_bridge_apply_v1.php')
            throw new RuntimeException('local9_paths');
        foreach ([$proposalFile,$loader,$config] as $file)
            if (!is_file($file) || is_link($file) || realpath($file)!==$file) throw new RuntimeException('local9_file_path');
        $raw=file_get_contents($proposalFile);
        if (!is_string($raw) || strlen($raw)!==90820 || !hash_equals(AnyTourLocal9BridgeApplyV1::PROPOSAL_SHA256,hash('sha256',$raw)))
            throw new RuntimeException('local9_proposal_hash');
        $proposal=json_decode($raw,true,64,JSON_THROW_ON_ERROR);
        $_SERVER['DOCUMENT_ROOT']=$root; require $config; require $loader;
        $result=AnyTourLocal9BridgeApplyV1::run(v2_data_db(),$proposal);
    } catch (Throwable $error) {
        $result=AnyTourLocal9BridgeApplyV1::reject($error);
    }
    echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
    exit($result['state']==='committed_verified'?0:1);
}
