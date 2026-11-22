<?php
declare(strict_types=1);

/** One finite LOCAL26 identity snapshot. No migration, provider client, or write helper. */
final class MatchLocal26IdentityV1
{
    public const PAIRS = [[4511,2490],[10334,22940],[8751,7831],[4679,4283],[14397,97001],
        [13766,74509],[7599,2458],[14932,113544],[12248,52337],[4609,2948],[12520,57536],
        [12839,61241],[13686,73477],[13066,64648],[4533,2588],[7619,2611],[9843,17424],
        [9842,17423],[10377,23718],[4172,80008],[4539,2612],[4538,2610],[4601,2931],
        [14029,78669],[12816,60788],[14013,78456]];
    public const MANIFEST_SHA256 = '7843b25c32c7adb8fa88f262aa870d8a469727ac3681f038ae418b98c0375138';
    public const ACTIONS_SHA256 = 'd3e1c159a107638e069b5e6e003bf9ad085e2188be93ba1a2c78f75080db7c4e';
    public const MAX_SOURCES = 256;
    public const MAX_LINKS = 128;
    public const MAX_TARGETS = 52;

    private static function number(mixed $value): int
    {
        if ((!is_int($value) && !is_string($value)) || !preg_match('/^[1-9][0-9]{0,14}$/D',(string)$value))
            throw new RuntimeException('local26_integer');
        return (int)$value;
    }

    private static function object(string $raw): array
    {
        if (strlen($raw)>1048576) throw new RuntimeException('local26_json_bound');
        $value=json_decode($raw,true,64,JSON_THROW_ON_ERROR);
        if (!is_array($value)) throw new RuntimeException('local26_json_shape');
        return $value;
    }

    private static function text(mixed $value): ?string
    {
        if ($value===null) return null;
        if (!is_string($value) || strlen($value)>1024 || preg_match('/[\x00-\x1f]/',$value))
            throw new RuntimeException('local26_text_bound');
        return $value;
    }

    private static function optionalId(mixed $value): ?int
    {
        if ((!is_int($value) && !is_string($value)) || !preg_match('/^[1-9][0-9]{0,14}$/D',(string)$value)) return null;
        return (int)$value;
    }

    private static function identity(array $value): array
    {
        // Manual fields can use flat country/region paths; keep that evidence separate.
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

    private static function keys(array $value): array
    {
        if (count($value)>128) throw new RuntimeException('local26_field_bound');
        $keys=array_keys($value);
        foreach ($keys as $key) if (!is_string($key) || !preg_match('/^[a-zA-Z][a-zA-Z0-9_.]{0,95}$/D',$key))
            throw new RuntimeException('local26_field_name');
        sort($keys,SORT_STRING); return $keys;
    }

    private static function valueHash(mixed $value): string
    {
        return hash('sha256',json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
    }

    private static function expectedFields(array $manifest): array
    {
        if (($manifest['schema_version']??null)!==2
            || ($manifest['source_policy']??null)!=='official_review_missing_only_v2'
            || ($manifest['proposed_actions_sha256']??null)!==self::ACTIONS_SHA256
            || !is_array($manifest['proposed_actions']??null) || count($manifest['proposed_actions'])!==26
            || !is_array($manifest['targets']??null) || count($manifest['targets'])!==26)
            throw new RuntimeException('local26_manifest_shape');
        $expected=[];
        foreach (self::PAIRS as $i=>[$ownId,$tvId]) {
            $action=$manifest['proposed_actions'][$i]??[]; $target=$manifest['targets'][$i]??[];
            $fields=$ownId===4172?['description']:['description','primaryImage','images'];
            if (($action['anytour_hotel_id']??null)!==$ownId || ($target['anytourHotelId']??null)!==$ownId
                || !is_array($action['field_changes']??null) || count($action['field_changes'])!==count($fields))
                throw new RuntimeException('local26_manifest_scope');
            foreach ($fields as $j=>$field) {
                $change=$action['field_changes'][$j]??[];
                if (($change['field']??null)!==$field || !array_key_exists('after',$change)
                    || ($field==='images'?!is_array($change['after']):!is_string($change['after'])))
                    throw new RuntimeException('local26_manifest_field');
                $expected[$ownId][$field]=self::valueHash($change['after']);
            }
        }
        // observed_revision/profile hashes in this manifest are historical before-pins, not current guards.
        return $expected;
    }

    private static function approvedFields(array $profile, array $expected): array
    {
        $out=[];
        foreach ($expected as $field=>$hash) {
            $present=array_key_exists($field,$profile); $actual=$present?self::valueHash($profile[$field]):null;
            $out[$field]=['present'=>$present,'sha256'=>$actual,'expected_sha256'=>$hash,
                'matches'=>$present && hash_equals($hash,$actual)];
        }
        return $out;
    }

    private static function snapshotSources(array $snapshot): array
    {
        $sources=$snapshot['sources']??null;
        if (!is_array($sources)) return [null,false];
        $count=count($sources);
        if ($count>64) throw new RuntimeException('local26_snapshot_source_bound');
        $intact=$count>0 && array_is_list($sources);
        $columns=['namespace','external_key','acquired_via','source_json','source_sha256','first_seen_at','last_seen_at'];
        foreach ($sources as $source) {
            if (!is_array($source) || count($source)!==7) { $intact=false; continue; }
            foreach ($columns as $column) if (!is_string($source[$column]??null)) $intact=false;
            if (!is_string($source['source_json']??null) || !is_string($source['source_sha256']??null)
                || !hash_equals($source['source_sha256'],hash('sha256',$source['source_json']))) $intact=false;
        }
        return [$count,$intact];
    }

    private static function select(PDO $db,string $sql,array $args,int $maximum): array
    {
        $query=$db->prepare($sql.' LIMIT '.($maximum+1));
        if ($query===false || !$query->execute($args)) throw new RuntimeException('local26_select');
        $rows=$query->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows)>$maximum) throw new RuntimeException('local26_row_bound');
        return $rows;
    }

    public static function run(PDO $db,array $scope,array $manifest): array
    {
        if (array_keys($scope)!==['pairs'] || $scope['pairs']!==self::PAIRS)
            throw new RuntimeException('local26_scope');
        $expected=self::expectedFields($manifest);
        if ($db->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql' || $db->inTransaction())
            throw new RuntimeException('local26_transaction');
        if (!$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION)
            || $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ')===false
            || $db->exec('SET TRANSACTION READ ONLY')===false || !$db->beginTransaction())
            throw new RuntimeException('local26_begin');
        $ownIds=array_column(self::PAIRS,0); $tvIds=array_column(self::PAIRS,1);
        $marks=implode(',',array_fill(0,26,'?'));
        try {
            $own=self::select($db,"SELECT id,profile_json,profile_sha256,revision,is_active
                FROM anytour_hotels WHERE id IN ($marks) ORDER BY id",$ownIds,26);
            // Legacy keys and candidate TV IDs are separate namespaces even when their numbers agree.
            $sources=self::select($db,"SELECT anytour_hotel_id,namespace,external_key,acquired_via,
                source_json,source_sha256,first_seen_at,last_seen_at FROM anytour_hotel_sources
                WHERE anytour_hotel_id IN ($marks) OR (namespace='legacy_catalog' AND external_key IN ($marks))
                ORDER BY namespace,external_key",[...$ownIds,...array_map('strval',$tvIds)],self::MAX_SOURCES);
            $ownLinks=self::select($db,"SELECT old_local_id,tv_id FROM local_tv_legacy_links
                WHERE old_local_id IN ($marks) ORDER BY old_local_id",$ownIds,26);
            $targetIds=$tvIds;
            foreach ($ownLinks as $link) $targetIds[]=self::number($link['tv_id']);
            $targetIds=array_values(array_unique($targetIds)); sort($targetIds,SORT_NUMERIC);
            if (count($targetIds)>self::MAX_TARGETS) throw new RuntimeException('local26_target_bound');
            $targetMarks=implode(',',array_fill(0,count($targetIds),'?'));
            // Include foreign owners of actual alternate targets, without recursively expanding scope.
            $links=self::select($db,"SELECT old_local_id,tv_id,snapshot_json,snapshot_sha256,migration_issues_json
                FROM local_tv_legacy_links WHERE old_local_id IN ($marks) OR tv_id IN ($targetMarks)
                ORDER BY old_local_id",[...$ownIds,...$targetIds],self::MAX_LINKS);
            // Deliberately no state filter: excluded, pending and ready remain distinct observations.
            $targets=self::select($db,"SELECT id,state,revision,discovery_json,content_json,manual_json,
                source_json,source_sha256,source_absent_json FROM local_tv_hotels
                WHERE id IN ($targetMarks) ORDER BY id",$targetIds,self::MAX_TARGETS);
            // These reads hold metadata locks before the engine check; no mixed-engine snapshot is published.
            $tables=self::select($db,"SELECT TABLE_NAME,ENGINE,TABLE_TYPE FROM information_schema.TABLES
                WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN (?,?,?,?) ORDER BY TABLE_NAME",
                ['anytour_hotels','anytour_hotel_sources','local_tv_legacy_links','local_tv_hotels'],4);
            if (count($tables)!==4) throw new RuntimeException('local26_schema');
            foreach ($tables as $table) if ($table['ENGINE']!=='InnoDB' || $table['TABLE_TYPE']!=='BASE TABLE')
                throw new RuntimeException('local26_snapshot_engine');
            $ownOut=[]; $sourceOut=[]; $linkOut=[]; $targetOut=[];
            foreach ($own as $row) {
                $id=self::number($row['id']); $profile=self::object($row['profile_json']);
                if (!in_array((string)$row['is_active'],['0','1'],true)) throw new RuntimeException('local26_active');
                $ownOut[]=['own_id'=>$id,'revision'=>self::number($row['revision']),
                    'active'=>(string)$row['is_active']==='1','identity'=>self::identity($profile),
                    'profile_sha256'=>$row['profile_sha256'],
                    'profile_integrity'=>hash_equals($row['profile_sha256'],hash('sha256',$row['profile_json'])),
                    'approved_fields'=>self::approvedFields($profile,$expected[$id])];
            }
            foreach ($sources as $row) {
                $source=self::object($row['source_json']);
                $sourceOut[]=['own_id'=>self::number($row['anytour_hotel_id']),
                    'namespace'=>self::text($row['namespace']),'external_key'=>self::text($row['external_key']),
                    'acquired_via'=>self::text($row['acquired_via']),'source_sha256'=>$row['source_sha256'],
                    'source_integrity'=>hash_equals($row['source_sha256'],hash('sha256',$row['source_json'])),
                    'source_identity_id'=>self::payloadId($source),'identity'=>self::identity($source)];
            }
            foreach ($links as $row) {
                $snapshot=self::object($row['snapshot_json']);
                $old=is_array($snapshot['profile']??null)?$snapshot['profile']:[];
                $issues=self::object($row['migration_issues_json']);
                $oldRaw=$old['profile_json']??null; $oldHash=$old['profile_sha256']??null;
                [$sourceCount,$sourcesIntact]=self::snapshotSources($snapshot);
                $linkOut[]=['own_id'=>self::number($row['old_local_id']),'tv_id'=>self::number($row['tv_id']),
                    'snapshot_sha256'=>$row['snapshot_sha256'],
                    'snapshot_integrity'=>hash_equals($row['snapshot_sha256'],hash('sha256',$row['snapshot_json'])),
                    'snapshot_own_id'=>self::optionalId($old['id']??null),
                    'snapshot_revision'=>self::optionalId($old['revision']??null),
                    'snapshot_profile_sha256'=>is_string($oldHash)?$oldHash:null,
                    'snapshot_profile_integrity'=>is_string($oldRaw) && is_string($oldHash)
                        && hash_equals($oldHash,hash('sha256',$oldRaw)),
                    'snapshot_source_count'=>$sourceCount,'snapshot_sources_integrity'=>$sourcesIntact,
                    'migration_issue_fields'=>self::keys($issues),
                    'migration_issues_sha256'=>hash('sha256',$row['migration_issues_json'])];
            }
            foreach ($targets as $row) {
                $discovery=self::object($row['discovery_json']); $content=self::object($row['content_json']);
                $manual=self::object($row['manual_json']); self::object($row['source_absent_json']);
                $source=$row['source_json']===null?null:self::object($row['source_json']);
                $targetOut[]=['tv_id'=>self::number($row['id']),'state'=>self::text($row['state']),
                    'revision'=>self::number($row['revision']),
                    'discovery_identity'=>self::identity($discovery),'content_identity'=>self::identity($content),
                    'manual_identity'=>self::identity($manual),'manual_fields'=>self::keys($manual),
                    'discovery_sha256'=>hash('sha256',$row['discovery_json']),
                    'content_sha256'=>hash('sha256',$row['content_json']),'manual_sha256'=>hash('sha256',$row['manual_json']),
                    'source_absent_sha256'=>hash('sha256',$row['source_absent_json']),
                    'source_present'=>$source!==null,'source_sha256'=>$row['source_sha256'],
                    'source_integrity'=>$source===null?null:(is_string($row['source_sha256'])
                        && hash_equals($row['source_sha256'],hash('sha256',$row['source_json']))),
                    'source_identity_id'=>$source===null?null:self::payloadId($source)];
            }
            if (!$db->rollBack() || $db->inTransaction()) throw new RuntimeException('local26_read_close');
            return ['schema_version'=>1,'state'=>'local26_identity_snapshot',
                'captured_at_utc'=>gmdate('c'),'database_snapshot'=>'repeatable-read-read-only',
                'approved_manifest_sha256'=>self::MANIFEST_SHA256,'actions_sha256'=>self::ACTIONS_SHA256,
                'own_rows'=>$ownOut,'source_rows'=>$sourceOut,'link_rows'=>$linkOut,'target_rows'=>$targetOut,
                'supplier_calls'=>0,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0];
        } finally { if ($db->inTransaction()) $db->rollBack(); }
    }
}

if (!defined('MATCH_LOCAL26_IDENTITY_LIBRARY_ONLY')) {
    try {
        $home=(string)getenv('HOME'); $root=$home.'/www/anytoour.ru';
        $dir=$home.'/.anytoour-int-executor/int-andromeda-match-retained-current-20261123-v1';
        $stage=(string)getenv('MATCH_RETAINED_SOURCE_ROOT'); $scopeFile=$dir.'/local26-scope.json';
        $manifestFile=$stage.'/scripts/catalog/anytour-local-official26-approved-20261122.json';
        $config=$root.'/config.php'; $loader=$stage.'/v2/data/db-v1.php';
        if (PHP_SAPI!=='cli' || count($argv)!==1 || getenv('MATCH_RETAINED_DIR')!==$dir
            || getcwd()!==$root || realpath($root)!==$root || realpath($dir)!==$dir
            || realpath($stage)!==$dir.'/source'
            || realpath(__FILE__)!==$stage.'/scripts/diagnostics/hotel_match_local26_identity_v1.php')
            throw new RuntimeException('local26_paths');
        foreach ([$scopeFile,$manifestFile,$config,$loader] as $file)
            if (!is_file($file) || is_link($file) || realpath($file)!==$file) throw new RuntimeException('local26_file_path');
        $raw=file_get_contents($manifestFile);
        if (!is_string($raw) || strlen($raw)!==101081 || !hash_equals(MatchLocal26IdentityV1::MANIFEST_SHA256,hash('sha256',$raw)))
            throw new RuntimeException('local26_manifest_hash');
        $manifest=json_decode($raw,true,64,JSON_THROW_ON_ERROR);
        $scopeRaw=file_get_contents($scopeFile);
        if (!is_string($scopeRaw) || strlen($scopeRaw)>4096) throw new RuntimeException('local26_scope_bound');
        $scope=json_decode($scopeRaw,true,8,JSON_THROW_ON_ERROR);
        if (!is_array($scope) || array_keys($scope)!==['pairs'] || $scope['pairs']!==MatchLocal26IdentityV1::PAIRS)
            throw new RuntimeException('local26_scope');
        $_SERVER['DOCUMENT_ROOT']=$root; require $config; require $loader;
        echo json_encode(MatchLocal26IdentityV1::run(v2_data_db(),$scope,$manifest),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n";
    } catch (Throwable $error) {
        echo json_encode(['state'=>'failed_read_only','error_class'=>get_class($error),
            'error_sha256'=>hash('sha256',$error->getMessage())])."\n"; exit(1);
    }
}
