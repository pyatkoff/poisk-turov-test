<?php
declare(strict_types=1);

/** CLI-only additive readiness/migration; no web writer or registry mutation. */
final class AnexReviewSchemaManager
{
    private PDO $db;
    private const TABLES = ['anex_review_state','anex_review_pair_exclusions','anex_review_audit','anex_review_dossier_batches','anex_review_dossiers'];
    private const PRESERVE = ['catalog_hotels'=>'id','anex_hotels'=>'anex_hotel_id','anex_hotel_auto_matches'=>'anex_hotel_id',
        'anex_hotel_candidates'=>'anex_hotel_id,candidate_rank','anex_hotel_search_mappings'=>'anex_hotel_id','anex_hotel_decisions'=>'anex_hotel_id'];

    public function __construct(PDO $db) {
        if ($db->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql') throw new RuntimeException('schema_requires_mysql');
        $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_EMULATE_PREPARES,false); $this->db=$db;
    }
    private function rows(string $sql,array $args=[]):array {
        $q=$this->db->prepare($sql);$q->execute($args);return $q->fetchAll(PDO::FETCH_ASSOC);
    }
    private static function type(string $value):string {
        return preg_replace('/\b(int|bigint|smallint)\([0-9]+\)/','$1',strtolower($value));
    }
    /** Parse only the checked five-table DDL grammar. No ALTER, DROP, DML or arbitrary names. */
    public static function definitions(array $files,string $expected):array {
        if (array_keys($files)!==['schema.sql','dossier-schema.sql']
            || !preg_match('/\A[0-9a-f]{64}\z/D',$expected)
            || !hash_equals($expected,hash('sha256',$files['schema.sql']."\n".$files['dossier-schema.sql']))) throw new RuntimeException('schema_source_mismatch');
        $definitions=[];
        foreach ($files as $raw) {
            if (!is_string($raw)||strlen($raw)>16000) throw new RuntimeException('schema_bound');
            $raw=preg_replace('/^--[^\r\n]*\R?/m','',$raw);
            foreach (explode(';',$raw) as $statement) {
                $statement=trim($statement);if($statement==='')continue;
                if(!preg_match('/\ACREATE TABLE IF NOT EXISTS ([a-z_]+) \((.*)\) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4\z/sD',$statement,$m))throw new RuntimeException('schema_grammar');
                $table=$m[1];if(!in_array($table,self::TABLES,true)||isset($definitions[$table]))throw new RuntimeException('schema_table_bound');
                $columns=[];$indexes=[];
                foreach(explode("\n",trim($m[2])) as $line){
                    $line=rtrim(trim($line),',');
                    if(preg_match('/\A(PRIMARY KEY|KEY [a-z_]+) \(([a-z_, ]+)\)\z/D',$line,$index)){
                        $name=$index[1]==='PRIMARY KEY'?'PRIMARY':substr($index[1],4);
                        $indexes[$name]=['unique'=>$name==='PRIMARY','columns'=>array_map('trim',explode(',',$index[2]))];continue;
                    }
                    if(!preg_match('/\A([a-z_]+) (INT UNSIGNED|BIGINT UNSIGNED|SMALLINT UNSIGNED|VARCHAR\([0-9]+\)|CHAR\([0-9]+\)|MEDIUMTEXT|DATETIME)(.*)\z/D',$line,$c))throw new RuntimeException('schema_column_grammar');
                    $tail=$c[3];$name=$c[1];
                    $stripped=preg_replace('/ (CHARACTER SET ascii|COLLATE ascii_bin|NOT NULL|NULL|DEFAULT 0|AUTO_INCREMENT|PRIMARY KEY|UNIQUE)/','',$tail);
                    if(trim($stripped)!=='')throw new RuntimeException('schema_column_options');
                    $columns[$name]=['type'=>strtolower($c[2]),'nullable'=>strpos($tail,'NOT NULL')===false?'YES':'NO',
                        'default'=>strpos($tail,'DEFAULT 0')!==false?'0':null,'extra'=>strpos($tail,'AUTO_INCREMENT')!==false?'auto_increment':'',
                        'charset'=>strpos($tail,'CHARACTER SET ascii')!==false?'ascii':(preg_match('/CHAR|TEXT/',$c[2])?'utf8mb4':null),
                        'binary'=>strpos($tail,'COLLATE ascii_bin')!==false];
                    if(strpos($tail,'PRIMARY KEY')!==false)$indexes['PRIMARY']=['unique'=>true,'columns'=>[$name]];
                    if(strpos($tail,'UNIQUE')!==false)$indexes[$name]=['unique'=>true,'columns'=>[$name]];
                }
                $definitions[$table]=['sql'=>$statement,'columns'=>$columns,'indexes'=>$indexes];
            }
        }
        if(array_keys($definitions)!==self::TABLES)throw new RuntimeException('schema_set_mismatch');
        return $definitions;
    }
    private function table(string $name):?array {
        return $this->rows('SELECT TABLE_TYPE,ENGINE FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[$name])[0]??null;
    }
    public function inspect(array $definitions):array {
        $missing=[];$installed=[];
        foreach($definitions as $table=>$definition){
            $meta=$this->table($table);if($meta===null){$missing[]=$table;continue;}
            if($meta['TABLE_TYPE']!=='BASE TABLE'||$meta['ENGINE']!=='InnoDB')throw new RuntimeException('schema_engine_mismatch');
            $columns=$this->rows('SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,EXTRA,CHARACTER_SET_NAME,COLLATION_NAME FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? ORDER BY ORDINAL_POSITION',[$table]);
            if(array_column($columns,'COLUMN_NAME')!==array_keys($definition['columns']))throw new RuntimeException('schema_columns_mismatch');
            foreach($columns as $column){
                $expected=$definition['columns'][$column['COLUMN_NAME']];
                if(self::type($column['COLUMN_TYPE'])!==$expected['type']||$column['IS_NULLABLE']!==$expected['nullable']
                    ||$column['COLUMN_DEFAULT']!==$expected['default']||strtolower($column['EXTRA'])!==$expected['extra']
                    ||$column['CHARACTER_SET_NAME']!==$expected['charset']||($expected['binary']&&$column['COLLATION_NAME']!=='ascii_bin'))throw new RuntimeException('schema_column_mismatch');
            }
            $indexes=[];
            foreach($this->rows('SELECT INDEX_NAME,NON_UNIQUE,COLUMN_NAME,SUB_PART,INDEX_TYPE FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=? ORDER BY INDEX_NAME,SEQ_IN_INDEX',[$table]) as $index){
                if($index['SUB_PART']!==null||$index['INDEX_TYPE']!=='BTREE')throw new RuntimeException('schema_index_mismatch');
                $indexes[$index['INDEX_NAME']]['unique']=(int)$index['NON_UNIQUE']===0;
                $indexes[$index['INDEX_NAME']]['columns'][]=$index['COLUMN_NAME'];
            }
            $expected=$definition['indexes'];ksort($expected);ksort($indexes);
            if($indexes!==$expected)throw new RuntimeException('schema_index_mismatch');
            if($this->rows('SELECT 1 FROM information_schema.triggers WHERE trigger_schema=DATABASE() AND event_object_table=?',[$table]))throw new RuntimeException('schema_unexpected_trigger');
            $installed[]=$table;
        }
        return ['missing'=>$missing,'installed'=>$installed,'ready'=>$missing===[]];
    }
    /** Bounded streaming fingerprints; raw rows and credentials never leave PHP. */
    public function preservation():array {
        $tables=self::PRESERVE;
        foreach(self::TABLES as $table)if($this->table($table)!==null)$tables[$table]=$table==='anex_review_audit'?'id':($table==='anex_review_dossier_batches'?'artifact_id':($table==='anex_review_dossiers'?'artifact_id,anex_hotel_id':($table==='anex_review_pair_exclusions'?'anex_hotel_id,catalog_hotel_id':'anex_hotel_id')));
        $result=[];
        foreach($tables as $table=>$order){
            $meta=$this->table($table);if($meta===null||$meta['TABLE_TYPE']!=='BASE TABLE'||$meta['ENGINE']!=='InnoDB')throw new RuntimeException('preservation_table_unavailable');
            $ctx=hash_init('sha256');$count=0;$bytes=0;$query=null;
            $this->db->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY,false);
            try{
                $query=$this->db->query('SELECT * FROM '.$table.' ORDER BY '.$order);
                while($row=$query->fetch(PDO::FETCH_ASSOC)){
                    $raw=json_encode($row,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
                    $bytes+=strlen($raw);++$count;
                    if($count>200000||$bytes>256000000)throw new RuntimeException('preservation_bound');
                    hash_update($ctx,strlen($raw).':'.$raw."\n");
                }
            }finally{if($query)$query->closeCursor();$this->db->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY,true);}
            $result[$table]=['count'=>$count,'sha256'=>hash_final($ctx)];
        }
        return $result;
    }
    public static function assertPreserved(array $before,array $after,array $exclude=[]):void {
        foreach($before as $table=>$value)if(!in_array($table,$exclude,true)&&($after[$table]??null)!==$value)throw new RuntimeException('preservation_changed');
    }
    public function run(array $files,string $expected,bool $apply=false):array {
        if($this->db->inTransaction())throw new RuntimeException('schema_transaction_owned');
        $definitions=self::definitions($files,$expected);
        if((int)$this->rows("SELECT GET_LOCK('anytour_anex_review_schema_v1',0) AS acquired")[0]['acquired']!==1)throw new RuntimeException('schema_operation_busy');
        try{
            $beforeSchema=$this->inspect($definitions);$before=$this->preservation();$created=[];
            if($apply)foreach($beforeSchema['missing'] as $table){
                // DDL is not transactional. Never drop/reset partial installations: a
                // subsequent run validates every installed table before continuing.
                $this->db->exec($definitions[$table]['sql']);$created[]=$table;
            }
            $afterSchema=$this->inspect($definitions);$after=$this->preservation();
            self::assertPreserved($before,$after);
            return ['status'=>$afterSchema['ready']?'ready':'migration_required','read_only'=>!$apply,'schema_sha256'=>$expected,
                'before_schema'=>$beforeSchema,'after_schema'=>$afterSchema,'created'=>$created,'before'=>$before,'after'=>$after,'preserved'=>true];
        }finally{$this->rows("SELECT RELEASE_LOCK('anytour_anex_review_schema_v1')");}
    }
}
