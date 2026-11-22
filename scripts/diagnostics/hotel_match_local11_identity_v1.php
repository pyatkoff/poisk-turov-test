<?php
declare(strict_types=1);

/** One finite LOCAL identity snapshot. No migration, provider client, or write helper. */
final class MatchLocal11IdentityV1
{
    public const PAIRS = [[5371,12120],[6014,64957],[6202,76029],[5160,66198],[5794,49750],
        [4516,2526],[7218,123247],[12008,48184],[5598,32322],[10771,28272],[8689,7458]];

    private static function number(mixed $value): int
    {
        if ((!is_int($value) && !is_string($value)) || !preg_match('/^[1-9][0-9]{0,14}$/D',(string)$value))
            throw new RuntimeException('local11_integer');
        return (int)$value;
    }

    private static function object(string $raw): array
    {
        if (strlen($raw)>1048576) throw new RuntimeException('local11_json_bound');
        $value=json_decode($raw,true,64,JSON_THROW_ON_ERROR);
        if (!is_array($value)) throw new RuntimeException('local11_json_shape');
        return $value;
    }

    private static function text(mixed $value): ?string
    {
        if ($value===null) return null;
        if (!is_string($value) || strlen($value)>1024 || preg_match('/[\x00-\x1f]/',$value))
            throw new RuntimeException('local11_text_bound');
        return $value;
    }

    private static function optionalId(mixed $value): ?int
    {
        if ((!is_int($value) && !is_string($value)) || !preg_match('/^[1-9][0-9]{0,14}$/D',(string)$value)) return null;
        return (int)$value;
    }

    private static function identity(array $value): array
    {
        // setManualFields stores flat paths, including identity overrides.
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
        if (count($value)>128) throw new RuntimeException('local11_field_bound');
        $keys=array_keys($value);
        foreach ($keys as $key) if (!is_string($key) || !preg_match('/^[a-zA-Z][a-zA-Z0-9_.]{0,95}$/D',$key))
            throw new RuntimeException('local11_field_name');
        sort($keys,SORT_STRING); return $keys;
    }

    private static function select(PDO $db,string $sql,array $args,int $maximum): array
    {
        $query=$db->prepare($sql.' LIMIT '.($maximum+1)); $query->execute($args);
        $rows=$query->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows)>$maximum) throw new RuntimeException('local11_row_bound');
        return $rows;
    }

    public static function run(PDO $db,array $scope): array
    {
        if (array_keys($scope)!==['pairs'] || $scope['pairs']!==self::PAIRS)
            throw new RuntimeException('local11_scope');
        if ($db->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql' || $db->inTransaction())
            throw new RuntimeException('local11_transaction');
        $ownIds=array_column(self::PAIRS,0); $tvIds=array_column(self::PAIRS,1);
        $marks=implode(',',array_fill(0,11,'?'));
        $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $db->exec('SET TRANSACTION READ ONLY'); $db->beginTransaction();
        try {
            $tables=self::select($db,"SELECT TABLE_NAME,ENGINE,TABLE_TYPE FROM information_schema.TABLES
                WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN (?,?,?,?) ORDER BY TABLE_NAME",
                ['anytour_hotels','anytour_hotel_sources','local_tv_legacy_links','local_tv_hotels'],4);
            if (count($tables)!==4) throw new RuntimeException('local11_schema');
            foreach ($tables as $table) if ($table['ENGINE']!=='InnoDB' || $table['TABLE_TYPE']!=='BASE TABLE')
                throw new RuntimeException('local11_snapshot_engine');
            $own=self::select($db,"SELECT id,profile_json,profile_sha256,revision,is_active
                FROM anytour_hotels WHERE id IN ($marks) ORDER BY id",$ownIds,11);
            // external_key is the legacy Tourvisor key; it is never an AnyTour own ID.
            $sources=self::select($db,"SELECT anytour_hotel_id,namespace,external_key,acquired_via,
                source_json,source_sha256,first_seen_at,last_seen_at FROM anytour_hotel_sources
                WHERE anytour_hotel_id IN ($marks) OR (namespace='legacy_catalog' AND external_key IN ($marks))
                ORDER BY namespace,external_key",[...$ownIds,...array_map('strval',$tvIds)],256);
            $links=self::select($db,"SELECT old_local_id,tv_id,snapshot_json,snapshot_sha256,migration_issues_json
                FROM local_tv_legacy_links WHERE old_local_id IN ($marks) OR tv_id IN ($marks)
                ORDER BY old_local_id",[...$ownIds,...$tvIds],128);
            $targetIds=$tvIds;
            foreach ($links as $link) if (in_array((int)$link['old_local_id'],$ownIds,true))
                $targetIds[]=self::number($link['tv_id']);
            $targetIds=array_values(array_unique($targetIds)); sort($targetIds,SORT_NUMERIC);
            if (count($targetIds)>22) throw new RuntimeException('local11_target_bound');
            $targetMarks=implode(',',array_fill(0,count($targetIds),'?'));
            // Deliberately no state filter: excluded and pending are different observations.
            $targets=self::select($db,"SELECT id,state,revision,discovery_json,content_json,manual_json,
                source_json,source_sha256,source_absent_json FROM local_tv_hotels
                WHERE id IN ($targetMarks) ORDER BY id",$targetIds,22);
            $ownOut=[]; $sourceOut=[]; $linkOut=[]; $targetOut=[];
            foreach ($own as $row) {
                $profile=self::object($row['profile_json']);
                if (!in_array((string)$row['is_active'],['0','1'],true)) throw new RuntimeException('local11_active');
                $ownOut[]=['own_id'=>self::number($row['id']),'revision'=>self::number($row['revision']),
                    'active'=>(string)$row['is_active']==='1','identity'=>self::identity($profile),
                    'profile_sha256'=>$row['profile_sha256'],
                    'profile_integrity'=>hash_equals($row['profile_sha256'],hash('sha256',$row['profile_json']))];
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
                $snapshot=self::object($row['snapshot_json']); $old=$snapshot['profile']??[];
                $issues=self::object($row['migration_issues_json']);
                $oldRaw=$old['profile_json']??null; $oldHash=$old['profile_sha256']??null;
                $linkOut[]=['own_id'=>self::number($row['old_local_id']),'tv_id'=>self::number($row['tv_id']),
                    'snapshot_sha256'=>$row['snapshot_sha256'],
                    'snapshot_integrity'=>hash_equals($row['snapshot_sha256'],hash('sha256',$row['snapshot_json'])),
                    'snapshot_own_id'=>self::optionalId($old['id']??null),
                    'snapshot_revision'=>self::optionalId($old['revision']??null),
                    'snapshot_profile_sha256'=>is_string($oldHash)?$oldHash:null,
                    'snapshot_profile_integrity'=>is_string($oldRaw) && is_string($oldHash)
                        && hash_equals($oldHash,hash('sha256',$oldRaw)),
                    'migration_issue_fields'=>self::keys($issues),
                    'migration_issues_sha256'=>hash('sha256',$row['migration_issues_json'])];
            }
            foreach ($targets as $row) {
                $discovery=self::object($row['discovery_json']); $content=self::object($row['content_json']);
                $manual=self::object($row['manual_json']); self::object($row['source_absent_json']);
                $source=$row['source_json']===null?null:self::object($row['source_json']);
                $targetOut[]=['tv_id'=>self::number($row['id']),'state'=>self::text($row['state']),
                    'revision'=>self::number($row['revision']),
                    // Separate identities make manual overrides observable, without exporting content.
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
            $db->rollBack();
            return ['schema_version'=>1,'state'=>'local11_identity_snapshot',
                'captured_at_utc'=>gmdate('c'),'database_snapshot'=>'repeatable-read-read-only',
                'own_rows'=>$ownOut,'source_rows'=>$sourceOut,'link_rows'=>$linkOut,'target_rows'=>$targetOut,
                'supplier_calls'=>0,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0];
        } finally { if ($db->inTransaction()) $db->rollBack(); }
    }
}

if (!defined('MATCH_LOCAL11_IDENTITY_LIBRARY_ONLY')) {
    try {
        $home=(string)getenv('HOME'); $root=$home.'/www/anytoour.ru';
        $dir=$home.'/.anytoour-int-executor/int-andromeda-match-retained-current-20261122-v1';
        $stage=(string)getenv('MATCH_RETAINED_SOURCE_ROOT');
        if (PHP_SAPI!=='cli' || count($argv)!==1 || getenv('MATCH_RETAINED_DIR')!==$dir
            || realpath($root)!==$root || realpath($dir)!==$dir || realpath($stage)!==$dir.'/source'
            || is_link($root.'/config.php') || is_link($dir.'/local11-scope.json'))
            throw new RuntimeException('local11_paths');
        $scope=json_decode((string)file_get_contents($dir.'/local11-scope.json'),true,8,JSON_THROW_ON_ERROR);
        $_SERVER['DOCUMENT_ROOT']=$root; require $root.'/config.php'; require $stage.'/v2/data/db-v1.php';
        echo json_encode(MatchLocal11IdentityV1::run(v2_data_db(),$scope),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    } catch (Throwable $error) {
        echo json_encode(['state'=>'failed_read_only','error_class'=>get_class($error),
            'error_sha256'=>hash('sha256',$error->getMessage())]); exit(1);
    }
}

