<?php
/** New LOCAL schema only. No hotel data import, supplier calls or old-table writes. */
declare(strict_types=1);

final class LocalTvSchemaV1
{
    public const SQL_SHA = '79e53072085eaac5eb37753a04ca7c68f3fcab2a4a4431d4e426e87e9f244bd2';
    public const TABLES = ['local_tv_hotels', 'local_tv_legacy_links'];
    private const TYPES = [
        'local_tv_hotels' => [
            'id'=>'int unsigned', 'first_seen_at'=>'datetime', 'last_seen_at'=>'datetime',
            'pending_since'=>'datetime', 'state'=>'varchar(24)', 'discovery_json'=>'longtext',
            'content_json'=>'longtext', 'manual_json'=>'longtext', 'source_json'=>'longtext',
            'source_sha256'=>'char(64)', 'source_fetched_at'=>'datetime',
            'source_absent_json'=>'longtext', 'revision'=>'bigint unsigned',
            'next_attempt_at'=>'datetime', 'last_error'=>'varchar(1000)',
        ],
        'local_tv_legacy_links'=>[
            'old_local_id'=>'bigint unsigned', 'tv_id'=>'int unsigned', 'snapshot_json'=>'longtext',
            'snapshot_sha256'=>'char(64)', 'migration_issues_json'=>'longtext', 'migrated_at'=>'datetime',
        ],
    ];

    public static function need(bool $condition, string $reason): void
    {
        if (!$condition) throw new RuntimeException($reason);
    }

    public static function json(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    public static function save(string $directory, string $name, array $value): void
    {
        $data = self::json($value);
        $file = $directory . '/' . $name;
        $stream = fopen($file, 'x+b');
        self::need($stream !== false, 'receipt_already_exists_no_replay');
        try {
            self::need(chmod($file, 0600), 'receipt_mode');
            self::need(fwrite($stream, $data) === strlen($data) && fflush($stream), 'receipt_write');
            if (function_exists('fsync')) self::need(fsync($stream), 'receipt_sync');
            rewind($stream);
            self::need(stream_get_contents($stream) === $data, 'receipt_readback');
        } finally { fclose($stream); }
    }

    public static function inspect(PDO $db): array
    {
        self::need($db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql', 'mysql_required');
        $target = (string)$db->query('SELECT DATABASE()')->fetchColumn();
        self::need($target !== '', 'database_not_selected');
        $out = ['database_sha256'=>hash('sha256', $target), 'tables'=>[]];
        foreach (self::TABLES as $table) {
            $q = $db->prepare('SELECT ENGINE,TABLE_COLLATION FROM information_schema.tables WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
            $q->execute([$table]); $meta = $q->fetch(PDO::FETCH_ASSOC);
            if (!$meta) { $out['tables'][$table] = ['present'=>false]; continue; }
            $issues = [];
            if ($meta['ENGINE'] !== 'InnoDB' || $meta['TABLE_COLLATION'] !== 'utf8mb4_bin') $issues[]='engine_or_collation';
            $q = $db->prepare('SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLLATION_NAME,COLUMN_DEFAULT,EXTRA FROM information_schema.columns WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION');
            $q->execute([$table]); $columns = $q->fetchAll(PDO::FETCH_ASSOC);
            $types = [];
            foreach ($columns as $column) {
                $name = $column['COLUMN_NAME']; $type = strtolower($column['COLUMN_TYPE']);
                $type = preg_replace('/^(int|bigint)\([0-9]+\)/', '$1', $type);
                $types[$name] = $type;
                $nullable = in_array($name, ['source_json','source_sha256','source_fetched_at','next_attempt_at','last_error'], true);
                if (($column['IS_NULLABLE'] === 'YES') !== $nullable || $column['EXTRA'] !== '') $issues[]='column_policy';
                if (str_ends_with($name, 'sha256') && $column['COLLATION_NAME'] !== 'ascii_bin') $issues[]='hash_collation';
                if ($name === 'state' && $column['COLUMN_DEFAULT'] !== 'pending') $issues[]='state_default';
                if ($name === 'revision' && (string)$column['COLUMN_DEFAULT'] !== '1') $issues[]='revision_default';
            }
            if ($types !== self::TYPES[$table]) $issues[]='columns';
            $q = $db->prepare('SELECT INDEX_NAME,NON_UNIQUE,COLUMN_NAME FROM information_schema.statistics WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY INDEX_NAME,SEQ_IN_INDEX');
            $q->execute([$table]); $indexes = [];
            foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $index) {
                $indexes[$index['INDEX_NAME']][] = [$index['COLUMN_NAME'], (int)$index['NON_UNIQUE']];
            }
            $expected = $table === 'local_tv_hotels'
                ? ['PRIMARY'=>[['id',0]], 'ix_local_tv_backlog'=>[['state',1],['next_attempt_at',1],['pending_since',1],['id',1]]]
                : ['PRIMARY'=>[['old_local_id',0]], 'ix_local_tv_old_reference'=>[['tv_id',1]]];
            ksort($indexes); ksort($expected);
            if ($indexes !== $expected) $issues[]='indexes';
            $q = $db->prepare('SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.UPDATE_RULE,r.DELETE_RULE FROM information_schema.key_column_usage k JOIN information_schema.referential_constraints r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL ORDER BY k.ORDINAL_POSITION');
            $q->execute([$table]); $foreign = $q->fetchAll(PDO::FETCH_ASSOC);
            $expectedForeign = $table === 'local_tv_hotels' ? [] : [[
                'COLUMN_NAME'=>'tv_id', 'REFERENCED_TABLE_NAME'=>'local_tv_hotels',
                'REFERENCED_COLUMN_NAME'=>'id', 'UPDATE_RULE'=>'RESTRICT', 'DELETE_RULE'=>'RESTRICT',
            ]];
            if ($foreign !== $expectedForeign) $issues[]='foreign_keys';
            $q = $db->prepare('SELECT COUNT(*) FROM information_schema.triggers WHERE TRIGGER_SCHEMA=DATABASE() AND EVENT_OBJECT_TABLE=?');
            $q->execute([$table]);
            if ((int)$q->fetchColumn() !== 0) $issues[]='unexpected_trigger';
            $rows = (int)$db->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
            $out['tables'][$table] = ['present'=>true,'valid'=>$issues === [],'rows'=>$rows,'issues'=>array_values(array_unique($issues))];
        }
        return $out;
    }

    /** Only two immutable CREATE statements; existing data is never changed. */
    public static function bootstrap(PDO $db, string $sql, string $directory, string $expectedDb): array
    {
        self::need(hash('sha256', $sql) === self::SQL_SHA, 'schema_source_hash');
        $lock = (int)$db->query("SELECT GET_LOCK('anytour-local-tv-daily',0)")->fetchColumn();
        self::need($lock === 1, 'local_collector_active');
        $started = false;
        try {
            $before = self::inspect($db);
            self::need(hash_equals($expectedDb, $before['database_sha256']), 'target_database_changed');
            self::save($directory, 'local-tv-schema-before.json', $before);
            $present = array_filter($before['tables'], static fn(array $t): bool=>$t['present']);
            if ($present !== []) {
                self::need(count($present) === 2 && !in_array(false,array_column($present,'valid'),true), 'existing_or_partial_schema_review_required');
                return ['state'=>'already_present_no_writes','schema_tables_created'=>0,'inventory'=>$before];
            }
            self::save($directory, 'local-tv-schema-started.json', ['schema_sha256'=>self::SQL_SHA,'inventory'=>$before,'planned_tables'=>self::TABLES]);
            $statements = array_values(array_filter(array_map('trim', explode(';', preg_replace('/^--.*$/m','',$sql)))));
            self::need(count($statements) === 2, 'schema_statement_count');
            foreach ($statements as $i=>$statement) {
                self::need(str_starts_with($statement, 'CREATE TABLE IF NOT EXISTS ' . self::TABLES[$i] . ' ('), 'schema_target');
                $started = true;
                $db->exec($statement);
                self::save($directory, 'local-tv-schema-ddl-' . ($i+1) . '.json', ['table'=>self::TABLES[$i],'returned'=>true]);
            }
            $after = self::inspect($db);
            foreach ($after['tables'] as $table) self::need($table['present'] && $table['valid'] && $table['rows'] === 0, 'new_schema_readback');
            self::need($after['database_sha256'] === $before['database_sha256'], 'schema_target_drift');
            self::save($directory, 'local-tv-schema-after.json', $after);
            return ['state'=>'installed_empty','schema_tables_created'=>2,'inventory'=>$after];
        } catch (Throwable $error) {
            if ($started) throw new RuntimeException('schema_unknown_no_replay',0,$error);
            throw $error;
        } finally { $db->query("SELECT RELEASE_LOCK('anytour-local-tv-daily')"); }
    }
}

if (!defined('LOCAL_TV_SCHEMA_LIBRARY_ONLY')) {
    $action = $argv[1] ?? '';
    $root = (string)getenv('ANYTOUR_ROOT');
    $directory = (string)getenv('LOCAL_TV_SCHEMA_DIR');
    $stage = (string)getenv('LOCAL_TV_SCHEMA_SOURCE_ROOT');
    $source = (string)getenv('LOCAL_TV_SCHEMA_SOURCE_SHA');
    $control = (string)getenv('LOCAL_TV_SCHEMA_CONTROL_SHA');
    $operation = (string)getenv('LOCAL_TV_SCHEMA_OPERATION');
    $receipt = ['schema_version'=>1,'operation_id'=>$operation,'source_sha'=>$source,'control_source_sha'=>$control,
        'schema_sha256'=>LocalTvSchemaV1::SQL_SHA,'action'=>$action,'supplier_calls'=>0,
        'content_writes'=>0,'profile_writes'=>0,'mapping_writes'=>0,'schema_tables_created'=>0,'state'=>'blocked','observed_at'=>time()];
    $exit = 1;
    try {
        $home = (string)getenv('HOME');
        LocalTvSchemaV1::need(in_array($action,['inspect','bootstrap','readback'],true) && count($argv)===2, 'schema_action');
        LocalTvSchemaV1::need($root === $home . '/www/anytoour.ru' && realpath($root)===$root && is_file($root.'/config.php') && !is_link($root.'/config.php'), 'schema_project_root');
        LocalTvSchemaV1::need(realpath($directory)===$directory && $directory===$home.'/.anytoour-int-executor/'.$operation && !is_link($directory), 'schema_receipt_root');
        LocalTvSchemaV1::need(preg_match('/^[a-f0-9]{40}$/D',$source)===1 && preg_match('/^[a-f0-9]{40}$/D',$control)===1, 'schema_source_identity');
        LocalTvSchemaV1::need(realpath($stage)===$stage && !is_link($stage), 'schema_source_root');
        $_SERVER['DOCUMENT_ROOT']=$root;
        require $stage . '/v2/data/db-v1.php';
        $config=v2_data_db_config();
        LocalTvSchemaV1::need(!(defined('ANYTOUR_LOCAL_TV_CATALOG_ENABLED') && constant('ANYTOUR_LOCAL_TV_CATALOG_ENABLED')===true), 'registry_flag_must_remain_disabled');
        $db=v2_data_db();
        $receipt['config_sha256']=hash_file('sha256',$root.'/config.php');
        if ($action==='bootstrap') {
            LocalTvSchemaV1::need(hash_equals((string)getenv('LOCAL_TV_SCHEMA_EXPECTED_CONFIG_SHA256'),$receipt['config_sha256']), 'private_config_changed_since_inspection');
            $sql=file_get_contents($stage.'/v2/data/migrations/20261010-local-tv-catalog.sql');
            LocalTvSchemaV1::need(is_string($sql),'schema_source_missing');
            $result=LocalTvSchemaV1::bootstrap($db,$sql,$directory,(string)getenv('LOCAL_TV_SCHEMA_EXPECTED_DB_SHA256'));
            $receipt=array_replace($receipt,$result);
        } else {
            $receipt['inventory']=LocalTvSchemaV1::inspect($db);
            $receipt['state']='inspected_read_only';
        }
        LocalTvSchemaV1::need(hash_file('sha256',$root.'/config.php')===$receipt['config_sha256'], 'private_config_drift');
        $exit=0;
    } catch (Throwable $error) {
        $receipt['state']=$error->getMessage()==='schema_unknown_no_replay' ? 'unknown_no_replay' : 'blocked';
        if ($receipt['state']==='unknown_no_replay') $receipt['schema_tables_created']=null;
        $receipt['error_class']=get_class($error);
        $receipt['error_sha256']=hash('sha256',$error->getMessage());
    }
    try { LocalTvSchemaV1::save($directory,'local-tv-schema-receipt.json',$receipt); }
    catch (Throwable) { $exit=1; }
    echo LocalTvSchemaV1::json($receipt) . "\n";
    exit($exit);
}
