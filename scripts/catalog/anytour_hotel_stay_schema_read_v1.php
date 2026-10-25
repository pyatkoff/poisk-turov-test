<?php
/** Fixed metadata-only LOCAL V2 prerequisite diagnosis. Never applies DDL or reads hotel/offer rows. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

final class AnyTourHotelStaySchemaReadV1
{
    public const OPERATION='int-andromeda-local-stay-schema-read-20261025-v1';
    public const MAX_BYTES=262144;
    public const MIGRATION_SHA256='1e80434e552fd0c91240db903245bf3f9976d4e937221b3270c5eda92f9ccc06';
    public const TABLES=['anytour_hotels','anytour_hotel_sources','anytour_hotel_room_concepts_v2',
        'anytour_hotel_meal_concepts_v2','anytour_hotel_stay_mappings_v2'];
    private const MIGRATION='/../../v2/data/migrations/20260918-anytour-hotel-stay-v2.sql';

    public static function json(mixed $value): string
    {
        $sort=static function(mixed $v) use (&$sort): mixed {
            if (!is_array($v)) return $v;
            if (!array_is_list($v)) ksort($v,SORT_STRING);
            foreach ($v as $k=>$item) $v[$k]=$sort($item);
            return $v;
        };
        return json_encode($sort($value),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    }

    private static function rows(PDO $db,string $sql,array $args,int $cap): array
    {
        $q=$db->prepare($sql.' LIMIT '.($cap+1));$q->execute($args);$rows=$q->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows)>$cap) throw new RuntimeException('METADATA_LIMIT');
        return $rows;
    }

    private static function identifier(mixed $value): string
    {
        if (!is_string($value)||!preg_match('/^[a-zA-Z0-9_]{1,64}$/D',$value)) {
            throw new RuntimeException('METADATA_LIMIT');
        }
        return $value;
    }

    private static function safeType(string $type): string
    {
        $original=$type;$type=strtolower($type);
        if (str_contains($original,"'")&&$original!==$type) return 'other:'.hash('sha256',$original);
        $type=preg_replace('/\b(bigint|tinyint|int|smallint|mediumint)\([0-9]+\)/','$1',$type)??$type;
        if (str_contains($type,"'")&&!in_array($type,["enum('room','meal')","enum('code','label')","enum('pending','accepted','rejected','conflict')"],true)) return 'other:'.hash('sha256',$type);
        if (strlen($type)>128||!preg_match('/^[a-z0-9_(),\x27 ]+$/D',$type)) return 'other:'.hash('sha256',$type);
        return $type;
    }

    /** Restricted expression AST preserves AND/OR precedence and ignores only redundant grouping. */
    private static function checkAst(string $expression): ?array
    {
        if (strlen($expression)>8192) throw new RuntimeException('METADATA_LIMIT');
        $tokens=[];$offset=0;
        while ($offset<strlen($expression)) {
            if (!preg_match('/\G\s*(?:(`[a-zA-Z_][a-zA-Z0-9_]*`)|([a-zA-Z_][a-zA-Z0-9_]*)|([0-9]+)|(\x27(?:\x27\x27|[^\x27\\\\])*\x27)|(<>|!=|>=|<=|[=><(),]))/A',
                $expression,$m,0,$offset)) {
                if (trim(substr($expression,$offset))==='') break;
                return null;
            }
            $offset+=strlen($m[0]);$token='';
            foreach (array_slice($m,1) as $part) if ($part!=='') { $token=$part;break; }
            $tokens[]=str_starts_with($token,"'")?$token:strtolower(trim($token,'`'));
        }
        // Character-set introducers do not alter the ASCII literals used by this exact contract.
        $tokens=array_values(array_filter($tokens,static fn($t,$i)=>!in_array($t,['_utf8mb4','_utf8mb3','_ascii'],true)
            ||!isset($tokens[$i+1])||!str_starts_with($tokens[$i+1],"'"),ARRAY_FILTER_USE_BOTH));
        $at=0;$primary=null;$compare=null;$and=null;$or=null;
        $primary=static function() use (&$tokens,&$at,&$or,&$primary): array {
            $token=$tokens[$at++]??null;
            if ($token==='(') { $v=$or();if (($tokens[$at++]??null)!==')') throw new RuntimeException('CHECK_AST');return $v; }
            if (!is_string($token)) throw new RuntimeException('CHECK_AST');
            if (preg_match('/^[0-9]+$/D',$token)) return ['integer',$token];
            if (str_starts_with($token,"'")) return ['string',substr($token,1,-1)];
            if (!preg_match('/^[a-z_][a-z0-9_]*$/D',$token)) throw new RuntimeException('CHECK_AST');
            if (($tokens[$at]??null)==='(') {
                // MySQL serializes OCTET_LENGTH via Item_func_length::func_name() as length.
                if ($token==='length') $token='octet_length';
                if (!in_array($token,['json_valid','octet_length','char_length'],true)) throw new RuntimeException('CHECK_AST');
                ++$at;$arg=$primary();if (($tokens[$at++]??null)!==')') throw new RuntimeException('CHECK_AST');
                return ['function',$token,$arg];
            }
            return ['column',$token];
        };
        $compare=static function() use (&$tokens,&$at,&$primary): array {
            $left=$primary();$op=$tokens[$at]??null;
            if (in_array($op,['=','<>','!=','>','<','>=','<='],true)) { ++$at;return ['compare',$op==='!='?'<>':$op,$left,$primary()]; }
            if ($op==='is') {
                ++$at;$not=($tokens[$at]??null)==='not';if ($not) ++$at;
                if (($tokens[$at++]??null)!=='null') throw new RuntimeException('CHECK_AST');
                return ['is_null',$not,$left];
            }
            if ($op==='in') {
                ++$at;if (($tokens[$at++]??null)!=='(') throw new RuntimeException('CHECK_AST');$values=[];
                do { $values[]=$primary();$next=$tokens[$at++]??null; } while ($next===',');
                if ($next!==')') throw new RuntimeException('CHECK_AST');return ['in',$left,$values];
            }
            return $left;
        };
        $join=static function(string $op,array $parts): array {
            $out=[$op];foreach ($parts as $p) {
                if (($p[0]??null)===$op) array_push($out,...array_slice($p,1));else $out[]=$p;
            }
            return count($out)===2?$out[1]:$out;
        };
        $and=static function() use (&$tokens,&$at,&$compare,$join): array {
            $parts=[$compare()];while (($tokens[$at]??null)==='and') {++$at;$parts[]=$compare();}return $join('and',$parts);
        };
        $or=static function() use (&$tokens,&$at,&$and,$join): array {
            $parts=[$and()];while (($tokens[$at]??null)==='or') {++$at;$parts[]=$and();}return $join('or',$parts);
        };
        try { $ast=$or();return $at===count($tokens)?$ast:null; } catch (RuntimeException $e) { return null; }
    }

    private static function contract(): array
    {
        $result=[];
        foreach (['room','meal'] as $kind) {
            $table='anytour_hotel_'.$kind.'_concepts_v2';$stem='anytour_hotel_'.$kind.'_concept_v2';
            $result[$table]=[
                'columns'=>[
                    ['id','bigint unsigned',false,null,null,null,'auto_increment'],
                    ['anytour_hotel_id','bigint unsigned',false,null,null,null,''],
                    ['local_key','varbinary(128)',false,null,null,null,''],
                    ['name_ru','varchar(255)',false,null,'utf8mb4','utf8mb4_bin',''],
                    ['facts_json','longtext',false,null,'utf8mb4','utf8mb4_bin',''],
                    ['revision','bigint unsigned',false,'1',null,null,''],
                    ['is_active','tinyint unsigned',false,'1',null,null,''],
                    ['created_at','datetime',false,null,null,null,''],
                    ['updated_at','datetime',false,null,null,null,''],
                ],
                'unique'=>['PRIMARY'=>['id'],'uq_'.$stem.'_key'=>['anytour_hotel_id','local_key'],
                    'uq_'.$stem.'_pair'=>['anytour_hotel_id','id']],
                'foreign'=>['fk_'.$stem.'_hotel'=>[['anytour_hotel_id'],'anytour_hotels',['id']]],
                'checks'=>['ck_'.$stem.'_json'=>'JSON_VALID(facts_json)',
                    'ck_'.$stem.'_active'=>'is_active IN (0,1)','ck_'.$stem.'_revision'=>'revision > 0'],
                'secondary'=>[['anytour_hotel_id']],
            ];
        }
        $result['anytour_hotel_stay_mappings_v2']=[
            'columns'=>[
                ['id','bigint unsigned',false,null,null,null,'auto_increment'],
                ['namespace','varchar(64)',false,null,'ascii','ascii_bin',''],
                ['external_hotel_key','varbinary(128)',false,null,null,null,''],
                ['operator_key','varbinary(128)',false,null,null,null,''],
                ['kind',"enum('room','meal')",false,null,'utf8mb4','utf8mb4_bin',''],
                ['key_kind',"enum('code','label')",false,null,'utf8mb4','utf8mb4_bin',''],
                ['external_key','varbinary(512)',false,null,null,null,''],
                ['anytour_hotel_id','bigint unsigned',false,null,null,null,''],
                ['room_concept_id','bigint unsigned',true,null,null,null,''],
                ['meal_concept_id','bigint unsigned',true,null,null,null,''],
                ['state',"enum('pending','accepted','rejected','conflict')",false,null,'utf8mb4','utf8mb4_bin',''],
                ['evidence_ref','varchar(255)',false,null,'utf8mb4','utf8mb4_bin',''],
                ['evidence_sha256','char(64)',false,null,'ascii','ascii_bin',''],
                ['reviewed_by','varchar(128)',false,null,'utf8mb4','utf8mb4_bin',''],
                ['created_at','datetime',false,null,null,null,''],
            ],
            'unique'=>['PRIMARY'=>['id'],'uq_anytour_hotel_stay_mapping_v2_source'=>
                ['namespace','external_hotel_key','operator_key','kind','key_kind','external_key']],
            'foreign'=>[
                'fk_anytour_hotel_stay_mapping_v2_source'=>[['namespace','external_hotel_key'],'anytour_hotel_sources',['namespace','external_key']],
                'fk_anytour_hotel_stay_mapping_v2_hotel'=>[['anytour_hotel_id'],'anytour_hotels',['id']],
                'fk_anytour_hotel_stay_mapping_v2_room'=>[['anytour_hotel_id','room_concept_id'],'anytour_hotel_room_concepts_v2',['anytour_hotel_id','id']],
                'fk_anytour_hotel_stay_mapping_v2_meal'=>[['anytour_hotel_id','meal_concept_id'],'anytour_hotel_meal_concepts_v2',['anytour_hotel_id','id']],
            ],
            'checks'=>[
                'ck_anytour_hotel_stay_mapping_v2_target'=>"(state='accepted' AND ((kind='room' AND room_concept_id IS NOT NULL AND meal_concept_id IS NULL) OR (kind='meal' AND meal_concept_id IS NOT NULL AND room_concept_id IS NULL))) OR (state<>'accepted' AND room_concept_id IS NULL AND meal_concept_id IS NULL)",
                'ck_anytour_hotel_stay_mapping_v2_nonempty'=>'OCTET_LENGTH(operator_key)>0 AND OCTET_LENGTH(external_key)>0 AND CHAR_LENGTH(evidence_ref)>0 AND CHAR_LENGTH(reviewed_by)>0',
            ],
            'secondary'=>[['anytour_hotel_id'],['anytour_hotel_id','room_concept_id'],['anytour_hotel_id','meal_concept_id']],
        ];
        return $result;
    }

    private static function capture(PDO $db,array $server): array
    {
        $slots=implode(',',array_fill(0,count(self::TABLES),'?'));
        $tableRows=self::rows($db,'SELECT TABLE_NAME,TABLE_TYPE,ENGINE,TABLE_COLLATION FROM information_schema.TABLES
            WHERE TABLE_SCHEMA=DATABASE() AND BINARY TABLE_NAME IN ('.$slots.') ORDER BY TABLE_NAME',self::TABLES,5);
        $tables=[];
        foreach (self::TABLES as $name) $tables[$name]=['name'=>$name,'role'=>str_ends_with($name,'_v2')?'target':'parent',
            'exists'=>false,'tableType'=>null,'engine'=>null,'collation'=>null,'createSha256'=>null,'partitioned'=>false,
            'columns'=>[],'indexes'=>[],'foreignKeys'=>[],'checks'=>[],'triggerCount'=>0,'compatibility'=>'absent','issues'=>['absent']];
        foreach ($tableRows as $r) {
            $name=$r['TABLE_NAME'];$tables[$name]['exists']=true;
            $tables[$name]['tableType']=$r['TABLE_TYPE']==='BASE TABLE'?'base_table':'other';
            $tables[$name]['engine']=$r['ENGINE']===null?null:self::identifier($r['ENGINE']);
            $tables[$name]['collation']=$r['TABLE_COLLATION']===null?null:self::identifier($r['TABLE_COLLATION']);
            if ($r['TABLE_TYPE']==='BASE TABLE') {
                $q=$db->query('SHOW CREATE TABLE `'.$name.'`');$row=$q->fetch(PDO::FETCH_NUM);
                if (!is_array($row)||!is_string($row[1]??null)||strlen($row[1])>32768) throw new RuntimeException('METADATA_LIMIT');
                $create=preg_replace('/\s+AUTO_INCREMENT=[0-9]+\b/','',$row[1])??$row[1];
                $tables[$name]['createSha256']=hash('sha256',$create);
            }
        }
        // A missing metadata row alone may mean inadequate visibility. Error1146 proves absence;
        // any privilege or other server error stays an unclassified technical failure.
        foreach ($tables as $name=>$table) if (!$table['exists']) {
            try { $db->query('SHOW CREATE TABLE `'.$name.'`');throw new RuntimeException('METADATA_VISIBILITY'); }
            catch (PDOException $e) { if ((int)($e->errorInfo[1]??0)!==1146) throw new RuntimeException('METADATA_VISIBILITY'); }
        }
        $columns=self::rows($db,'SELECT TABLE_NAME,COLUMN_NAME,ORDINAL_POSITION,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,
            CHARACTER_SET_NAME,COLLATION_NAME,EXTRA,GENERATION_EXPRESSION FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA=DATABASE() AND BINARY TABLE_NAME IN ('.$slots.') ORDER BY TABLE_NAME,ORDINAL_POSITION',self::TABLES,128);
        foreach ($columns as $r) {
            $default=$r['COLUMN_DEFAULT'];
            if ($default!==null) { $default=(string)$default;if (!preg_match('/^-?[0-9]{1,20}$/D',$default)) $default='sha256:'.hash('sha256',$default); }
            $extra=strtolower((string)$r['EXTRA']);
            if (!in_array($extra,['','auto_increment'],true)) $extra='other:'.hash('sha256',$extra);
            $tables[$r['TABLE_NAME']]['columns'][]=['name'=>self::identifier($r['COLUMN_NAME']),
                'ordinal'=>(int)$r['ORDINAL_POSITION'],'type'=>self::safeType((string)$r['COLUMN_TYPE']),
                'nullable'=>$r['IS_NULLABLE']==='YES','default'=>$default,
                'charset'=>$r['CHARACTER_SET_NAME']===null?null:self::identifier($r['CHARACTER_SET_NAME']),
                'collation'=>$r['COLLATION_NAME']===null?null:self::identifier($r['COLLATION_NAME']),
                'extra'=>$extra,'generationSha256'=>in_array($r['GENERATION_EXPRESSION'],['',null],true)?null:hash('sha256',(string)$r['GENERATION_EXPRESSION'])];
        }
        $indexes=self::rows($db,'SELECT TABLE_NAME,INDEX_NAME,NON_UNIQUE,SEQ_IN_INDEX,COLUMN_NAME,SUB_PART,COLLATION,INDEX_TYPE
            FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND BINARY TABLE_NAME IN ('.$slots.')
            ORDER BY TABLE_NAME,INDEX_NAME,SEQ_IN_INDEX',self::TABLES,128);
        foreach ($indexes as $r) {
            $name=self::identifier($r['INDEX_NAME']);$table=$r['TABLE_NAME'];
            if (!isset($tables[$table]['indexes'][$name])) $tables[$table]['indexes'][$name]=[
                'name'=>$name,'unique'=>(int)$r['NON_UNIQUE']===0,'type'=>self::identifier($r['INDEX_TYPE']),'columns'=>[]];
            $tables[$table]['indexes'][$name]['columns'][]=['name'=>$r['COLUMN_NAME']===null?null:self::identifier($r['COLUMN_NAME']),
                'prefix'=>$r['SUB_PART']===null?null:(int)$r['SUB_PART'],'order'=>$r['COLLATION']===null?null:self::identifier($r['COLLATION'])];
        }
        $fks=self::rows($db,'SELECT k.TABLE_NAME,k.CONSTRAINT_NAME,k.COLUMN_NAME,k.ORDINAL_POSITION,k.REFERENCED_TABLE_NAME,
            k.REFERENCED_COLUMN_NAME,(k.REFERENCED_TABLE_SCHEMA=DATABASE()) AS SAME_SCHEMA,r.UPDATE_RULE,r.DELETE_RULE
            FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r
            ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.TABLE_NAME=k.TABLE_NAME AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME
            WHERE k.TABLE_SCHEMA=DATABASE() AND BINARY k.TABLE_NAME IN ('.$slots.') AND k.REFERENCED_TABLE_NAME IS NOT NULL
            ORDER BY k.TABLE_NAME,k.CONSTRAINT_NAME,k.ORDINAL_POSITION',self::TABLES,64);
        foreach ($fks as $r) {
            $name=self::identifier($r['CONSTRAINT_NAME']);$table=$r['TABLE_NAME'];
            if (!isset($tables[$table]['foreignKeys'][$name])) $tables[$table]['foreignKeys'][$name]=[
                'name'=>$name,'columns'=>[],'referencedTable'=>self::identifier($r['REFERENCED_TABLE_NAME']),
                'referencedColumns'=>[],'sameSchema'=>(int)$r['SAME_SCHEMA']===1,
                'updateRule'=>self::identifier(str_replace(' ','_',$r['UPDATE_RULE'])),
                'deleteRule'=>self::identifier(str_replace(' ','_',$r['DELETE_RULE']))];
            $tables[$table]['foreignKeys'][$name]['columns'][]=self::identifier($r['COLUMN_NAME']);
            $tables[$table]['foreignKeys'][$name]['referencedColumns'][]=self::identifier($r['REFERENCED_COLUMN_NAME']);
        }
        $partitions=self::rows($db,'SELECT DISTINCT TABLE_NAME FROM information_schema.PARTITIONS
            WHERE TABLE_SCHEMA=DATABASE() AND BINARY TABLE_NAME IN ('.$slots.') AND PARTITION_NAME IS NOT NULL
            ORDER BY TABLE_NAME',self::TABLES,5);
        foreach ($partitions as $r) $tables[$r['TABLE_NAME']]['partitioned']=true;
        // TRIGGERS filters rows by privilege. Prove direct current-account visibility;
        // role-only or otherwise unreviewed grant paths remain conservatively blocked.
        $grantee="CONCAT(QUOTE(LEFT(CURRENT_USER(),LENGTH(CURRENT_USER())-LENGTH(SUBSTRING_INDEX(CURRENT_USER(),'@',-1))-1)),'@',QUOTE(SUBSTRING_INDEX(CURRENT_USER(),'@',-1)))";
        $global=self::rows($db,'SELECT PRIVILEGE_TYPE FROM information_schema.USER_PRIVILEGES
            WHERE BINARY GRANTEE=BINARY '.$grantee.' AND PRIVILEGE_TYPE=\'TRIGGER\'',[],1);
        $schema=self::rows($db,'SELECT PRIVILEGE_TYPE FROM information_schema.SCHEMA_PRIVILEGES
            WHERE BINARY GRANTEE=BINARY '.$grantee.' AND BINARY TABLE_SCHEMA=BINARY DATABASE() AND PRIVILEGE_TYPE=\'TRIGGER\'',[],1);
        $tablePrivileges=self::rows($db,'SELECT TABLE_NAME FROM information_schema.TABLE_PRIVILEGES
            WHERE BINARY GRANTEE=BINARY '.$grantee.' AND BINARY TABLE_SCHEMA=BINARY DATABASE()
            AND BINARY TABLE_NAME IN ('.$slots.') AND PRIVILEGE_TYPE=\'TRIGGER\'
            ORDER BY TABLE_NAME',self::TABLES,5);
        $visible=array_column($tablePrivileges,'TABLE_NAME');$visibility=true;
        foreach ($tables as $name=>$table) if ($table['role']==='target'&&$table['exists']
            &&$global===[]&&$schema===[]&&!in_array($name,$visible,true)) $visibility=false;
        $server['triggerVisibilityProven']=$visibility;
        $triggers=self::rows($db,'SELECT EVENT_OBJECT_TABLE FROM information_schema.TRIGGERS
            WHERE TRIGGER_SCHEMA=DATABASE() AND BINARY EVENT_OBJECT_TABLE IN ('.$slots.')
            ORDER BY EVENT_OBJECT_TABLE,TRIGGER_NAME',self::TABLES,32);
        foreach ($triggers as $r) ++$tables[$r['EVENT_OBJECT_TABLE']]['triggerCount'];
        if ($server['checkEnforcement']==='supported') {
            $checks=self::rows($db,'SELECT t.TABLE_NAME,t.CONSTRAINT_NAME,t.ENFORCED,c.CHECK_CLAUSE
                FROM information_schema.TABLE_CONSTRAINTS t JOIN information_schema.CHECK_CONSTRAINTS c
                ON c.CONSTRAINT_SCHEMA=t.CONSTRAINT_SCHEMA AND c.CONSTRAINT_NAME=t.CONSTRAINT_NAME
                WHERE t.TABLE_SCHEMA=DATABASE() AND BINARY t.TABLE_NAME IN ('.$slots.') AND t.CONSTRAINT_TYPE=\'CHECK\'
                ORDER BY t.TABLE_NAME,t.CONSTRAINT_NAME',self::TABLES,32);
            foreach ($checks as $r) {
                $ast=self::checkAst((string)$r['CHECK_CLAUSE']);$name=self::identifier($r['CONSTRAINT_NAME']);
                $tables[$r['TABLE_NAME']]['checks'][$name]=['name'=>$name,'enforced'=>$r['ENFORCED']==='YES',
                    'expressionSha256'=>hash('sha256',(string)$r['CHECK_CLAUSE']),
                    'astSha256'=>$ast===null?null:hash('sha256',self::json($ast))];
            }
        }
        foreach ($tables as &$table) {
            foreach (['indexes','foreignKeys','checks'] as $key) $table[$key]=array_values($table[$key]);
        }unset($table);
        return ['server'=>$server,'tables'=>array_values($tables)];
    }

    private static function columnTuple(array $c): array
    {
        return [$c['name'],$c['type'],$c['nullable'],$c['default'],$c['charset'],$c['collation'],$c['extra']];
    }

    private static function evaluate(array $inventory): array
    {
        $contract=self::contract();$missing=[];$existing=[];$blocked=[];$parents=[];
        foreach ($inventory['tables'] as &$table) {
            if (!$table['exists']) {
                if ($table['role']==='target') $missing[]=$table['name'];else $parents[]=$table['name'];continue;
            }
            $issues=[];
            if ($table['tableType']!=='base_table') $issues[]='wrong_table_type';
            if (strtolower((string)$table['engine'])!=='innodb') $issues[]='wrong_engine';
            if ($table['partitioned']) $issues[]='partitioned';
            $cols=array_column($table['columns'],null,'name');
            $indices=array_column($table['indexes'],null,'name');
            $fullColumns=static fn($idx)=>array_map(static fn($c)=>$c['prefix']===null&&$c['order']==='A'?$c['name']:null,$idx['columns']);
            if ($table['role']==='parent') {
                $required=$table['name']==='anytour_hotels'?
                    ['id'=>['bigint unsigned',false,null,null]]:
                    ['namespace'=>['varchar(64)',false,'ascii','ascii_bin'],'external_key'=>['varbinary(128)',false,null,null]];
                foreach ($required as $name=>$want) {
                    $c=$cols[$name]??null;
                    if ($c===null||[$c['type'],$c['nullable'],$c['charset'],$c['collation']]!==$want||$c['generationSha256']!==null) {
                        $issues[]='parent_columns';break;
                    }
                }
                $key=$table['name']==='anytour_hotels'?['id']:['namespace','external_key'];$has=false;
                foreach ($indices as $idx) if ($idx['unique']&&$idx['type']==='BTREE'&&$fullColumns($idx)===$key
                    &&($table['name']!=='anytour_hotels'||$idx['name']==='PRIMARY')) $has=true;
                if (!$has) $issues[]='parent_unique_key';
            } else {
                $spec=$contract[$table['name']];
                if ($table['triggerCount']>0) $issues[]='unexpected_trigger';
                if (!$inventory['server']['triggerVisibilityProven']) $issues[]='trigger_visibility_not_proven';
                if ($table['collation']!=='utf8mb4_bin') $issues[]='table_collation';
                if (array_map([self::class,'columnTuple'],$table['columns'])!==$spec['columns']
                    ||count(array_filter($table['columns'],static fn($c)=>$c['generationSha256']!==null))>0) $issues[]='columns';
                $uniques=[];$secondaryOK=true;
                foreach ($indices as $name=>$idx) {
                    $key=$fullColumns($idx);
                    if ($idx['type']!=='BTREE'||in_array(null,$key,true)) $secondaryOK=false;
                    if ($idx['unique']) $uniques[$name]=$key;
                    elseif (!in_array($key,$spec['secondary'],true)) $secondaryOK=false;
                }
                ksort($uniques,SORT_STRING);$wantUnique=$spec['unique'];ksort($wantUnique,SORT_STRING);
                if ($uniques!==$wantUnique||!$secondaryOK) $issues[]='indexes';
                $foreign=[];$rulesOK=true;
                foreach ($table['foreignKeys'] as $fk) {
                    $foreign[$fk['name']]=[$fk['columns'],$fk['referencedTable'],$fk['referencedColumns']];
                    if (!$fk['sameSchema']||!in_array($fk['updateRule'],['RESTRICT','NO_ACTION'],true)
                        ||!in_array($fk['deleteRule'],['RESTRICT','NO_ACTION'],true)) $rulesOK=false;
                }
                ksort($foreign,SORT_STRING);$wantFk=$spec['foreign'];ksort($wantFk,SORT_STRING);
                if ($foreign!==$wantFk||!$rulesOK) $issues[]='foreign_keys';
                if ($inventory['server']['checkEnforcement']==='supported') {
                    $checks=array_column($table['checks'],null,'name');$expected=array_keys($spec['checks']);sort($expected,SORT_STRING);
                    $actual=array_keys($checks);sort($actual,SORT_STRING);$checkOK=$actual===$expected;
                    foreach ($spec['checks'] as $name=>$clause) {
                        $ast=self::checkAst($clause);
                        if ($ast===null||!isset($checks[$name])||!$checks[$name]['enforced']
                            ||$checks[$name]['astSha256']!==hash('sha256',self::json($ast))) $checkOK=false;
                    }
                    if (!$checkOK) $issues[]='checks';
                } else $issues[]='check_capability_not_reviewed';
            }
            $table['issues']=$issues;
            $table['compatibility']=in_array('wrong_table_type',$issues,true)?'wrong_table_type':
                (in_array('wrong_engine',$issues,true)?'wrong_engine':($issues===[]?'compatible':'incompatible'));
            if ($issues!==[]) { if ($table['role']==='parent') $parents[]=$table['name'];else $blocked[]=$table['name']; }
            elseif ($table['role']==='target') $existing[]=$table['name'];
        }unset($table);
        $state=($inventory['server']['checkEnforcement']!=='supported'||!$inventory['server']['triggerVisibilityProven'])?'blocked_server_capability':
            ($parents!==[]?'blocked_parent_prerequisites':($blocked!==[]?'blocked_existing_drift':
            ($missing!==[]?'create_only_after_review':'ready_no_op')));
        $plan=['state'=>$state,'createTables'=>$missing,'existingCompatibleTables'=>$existing,
            'blockedTables'=>array_merge($parents,$blocked),'requiresExplicitReview'=>true,'applyAuthorized'=>false,
            'migrationSha256'=>self::MIGRATION_SHA256,'noDdlExecuted'=>true];
        return [$inventory,$plan];
    }

    public static function read(PDO $db): array
    {
        $receipt=['schemaVersion'=>1,'source'=>'anytour-hotel-stay-schema-read-v1','state'=>'read_failed',
            'supplierCalls'=>0,'databaseWrites'=>0,'schemaWrites'=>0,'automaticAccepts'=>0,
            'migrationSha256'=>self::MIGRATION_SHA256,'inventorySha256'=>null,'planSha256'=>null];
        $inventory=null;$plan=null;$owned=false;
        try {
            if ($db->inTransaction()) throw new RuntimeException('TRANSACTION_SCOPE');
            $path=__DIR__.self::MIGRATION;
            if (!is_file($path)||is_link($path)||hash_file('sha256',$path)!==self::MIGRATION_SHA256) {
                throw new RuntimeException('MIGRATION_SOURCE_CHANGED');
            }
            $raw=(string)$db->query('SELECT VERSION()')->fetchColumn();
            $family=stripos($raw,'mariadb')!==false?'mariadb':'other';$version='unknown';
            if (preg_match('/^([0-9]+\.[0-9]+\.[0-9]+)/D',$raw,$m)) {
                $version=$m[1];if ($family==='other'&&version_compare($version,'8.0.16','>=')&&version_compare($version,'9.0.0','<')) $family='mysql';
            }
            $supported=$family==='mysql'&&version_compare($version,'8.0.16','>=');
            $server=['family'=>$family,'version'=>$version,'checkEnforcement'=>$supported?'supported':'not_reviewed'];
            $db->exec('SET TRANSACTION READ ONLY');$db->beginTransaction();$owned=true;
            $first=self::capture($db,$server);$second=self::capture($db,$server);
            if (self::json($first)!==self::json($second)) throw new RuntimeException('SCHEMA_CHANGED');
            [$inventory,$plan]=self::evaluate($first);
            $receipt['state']='completed_read_only';$receipt['inventorySha256']=hash('sha256',self::json($inventory));
            $receipt['planSha256']=hash('sha256',self::json($plan));
        } catch (Throwable $e) {
            $receipt['state']=match ($e->getMessage()) {'METADATA_LIMIT'=>'metadata_limit','SCHEMA_CHANGED'=>'schema_changed',default=>'read_failed'};
            $inventory=null;$plan=null;
        } finally { if ($owned&&$db->inTransaction()) $db->rollBack(); }
        $result=['receipt'=>$receipt,'inventory'=>$inventory,'plan'=>$plan];
        if (strlen(self::json($result))>self::MAX_BYTES-1024) {
            $receipt['state']='metadata_limit';$receipt['inventorySha256']=null;$receipt['planSha256']=null;
            return ['receipt'=>$receipt,'inventory'=>null,'plan'=>null];
        }
        return $result;
    }
}

if (realpath((string)($_SERVER['SCRIPT_FILENAME']??''))===__FILE__) {
    try {
        if ($argc!==1) throw new RuntimeException('CLI_SCOPE');
        $site=(string)getenv('ANYTOUR_SITE_ROOT');$dir=(string)getenv('ANYTOUR_OPERATION_OUTPUT_DIR');
        $source=(string)getenv('ANYTOUR_OPERATION_RELEASE_SHA');$control=(string)getenv('ANYTOUR_OPERATION_CONTROL_SHA');
        $operation=(string)getenv('ANYTOUR_OPERATION_ID');
        if (!preg_match('/^[a-f0-9]{40}$/D',$source)||!preg_match('/^[a-f0-9]{40}$/D',$control)
            ||$operation!==AnyTourHotelStaySchemaReadV1::OPERATION
            ||$site===''||realpath($site)!==$site||basename($site)!=='anytoour.ru'
            ||$dir===''||realpath($dir)!==$dir||is_link($dir)||!is_dir($dir)||str_starts_with($dir.'/',$site.'/')) {
            throw new RuntimeException('CLI_SCOPE');
        }
        $path=$dir.'/local-stay-schema-read.json';if (file_exists($path)||is_link($path)) throw new RuntimeException('CLI_RECEIPT_EXISTS');
        $_SERVER['DOCUMENT_ROOT']=$site;require_once __DIR__.'/../../v2/data/db-v1.php';
        $result=AnyTourHotelStaySchemaReadV1::read(v2_data_db());
        $result['receipt']+=['operationId'=>$operation,'sourceSha'=>$source,'controlSha'=>$control];
        $json=AnyTourHotelStaySchemaReadV1::json($result)."\n";
        if (strlen($json)>AnyTourHotelStaySchemaReadV1::MAX_BYTES) throw new RuntimeException('CLI_RESULT_SIZE');
        $old=umask(0077);try {$handle=fopen($path,'x+b');} finally {umask($old);}
        if ($handle===false) throw new RuntimeException('CLI_RECEIPT_CREATE');
        try {if (fwrite($handle,$json)!==strlen($json)||!fflush($handle)||!fsync($handle)) throw new RuntimeException('CLI_RECEIPT_WRITE');}
        finally {fclose($handle);}
        echo $json;exit($result['receipt']['state']==='read_failed'?1:0);
    } catch (Throwable $e) {fwrite(STDERR,"LOCAL_STAY_SCHEMA_READ_FAILED\n");exit(1);}
}
