<?php
declare(strict_types=1);

/**
 * Dormant SEARCH meal-provider mapping activation contract.
 * No HTTP entrypoint, supplier calls or automatic execution. Mutating CLI
 * commands require --allow-write; live execution remains a separate HIGH op.
 */
final class Search3MealProviderMappingActivateV1
{
    public const TABLE = 'anytour_search_meal_provider_mappings_v1';
    public const PROVIDER = 'tourvisor';
    public const SCOPE = 'global';
    public const EVIDENCE_RESULT_SHA256 = 'a0755910d8e46c4f85021de5cae1ec88f29a2a811d4cc4f5c02d6c8ea0dfa4fa';
    public const EVIDENCE_REF = 'search3-meal-provider-mapping-plan-3353-20260923-v1;result-sha256:a0755910d8e46c4f85021de5cae1ec88f29a2a811d4cc4f5c02d6c8ea0dfa4fa';
    public const REVIEWED_BY = 'owner:pyatkoff;reviewed-evidence:2026-09-23';

    /** @var array<string,array{id:int,code:string,name:string}> */
    private const MAPPINGS = [
        '3' => ['id' => 2, 'code' => 'breakfast', 'name' => 'Завтраки'],
        '4' => ['id' => 3, 'code' => 'half-board', 'name' => 'Полупансион'],
        '7' => ['id' => 7, 'code' => 'all-inclusive', 'name' => 'Всё включено'],
        '9' => ['id' => 8, 'code' => 'ultra-all-inclusive', 'name' => 'Ультра всё включено'],
    ];

    public function __construct(private PDO $db)
    {
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    }

    /** @return array<string,mixed> */
    public function plan(): array
    {
        $this->assertCanonicalPlans();
        if (!$this->tableExists(self::TABLE)) {
            return ['state'=>'schema_absent','provider'=>self::PROVIDER,'scope'=>self::SCOPE,'expectedMappings'=>4,'writes'=>0];
        }
        $this->assertExactSchema();
        $rows = $this->scopeRows(false);
        return [
            'state'=>$this->classifyRows($rows),
            'provider'=>self::PROVIDER,
            'scope'=>self::SCOPE,
            'expectedMappings'=>4,
            'currentRows'=>count($rows),
            'externalIds'=>array_values(array_map(static fn(array $r): string => (string)$r['external_id'], $rows)),
            'writes'=>0,
        ];
    }

    /** @return array<string,mixed> */
    public function installSchema(bool $allowWrite): array
    {
        $this->assertCanonicalPlans();
        if ($this->tableExists(self::TABLE)) {
            $this->assertExactSchema();
            return ['state'=>'schema_already_exact','writes'=>0];
        }
        if (!$allowWrite) throw new RuntimeException('SEARCH3_MEAL_WRITE_NOT_AUTHORIZED');

        $path = dirname(__DIR__, 2).'/v2/data/migrations/20260923-anytour-search-meal-provider-mappings-v1.sql';
        $sql = @file_get_contents($path);
        if (!is_string($sql) || trim($sql)==='') throw new RuntimeException('SEARCH3_MEAL_MIGRATION_MISSING');
        if (str_contains($sql,'anytour_search_meal_memberships_v1') || str_contains($sql,'anytour_hotel_')) {
            throw new RuntimeException('SEARCH3_MEAL_MIGRATION_SCOPE_DRIFT');
        }
        if (substr_count($sql,'CREATE TABLE '.self::TABLE)!==1) throw new RuntimeException('SEARCH3_MEAL_MIGRATION_IDENTITY_DRIFT');
        $this->db->exec($sql);
        $this->assertExactSchema();
        return ['state'=>'schema_installed','writes'=>1];
    }

    /** @return array<string,mixed> */
    public function seed(bool $allowWrite): array
    {
        $this->assertCanonicalPlans();
        if (!$this->tableExists(self::TABLE)) throw new RuntimeException('SEARCH3_MEAL_SCHEMA_ABSENT');
        $this->assertExactSchema();
        $this->db->beginTransaction();
        try {
            $rows = $this->scopeRows(true);
            $state = $this->classifyRows($rows);
            if ($state==='exact') {
                $this->db->rollBack();
                return ['state'=>'seed_already_exact','writes'=>0,'rows'=>4];
            }
            if ($state!=='empty') throw new RuntimeException('SEARCH3_MEAL_MAPPING_STATE_NOT_EMPTY:'.$state);
            if (!$allowWrite) throw new RuntimeException('SEARCH3_MEAL_WRITE_NOT_AUTHORIZED');

            $q=$this->db->prepare(
                'INSERT INTO '.self::TABLE.' (provider,scope_key,external_id,meal_plan_id,state,evidence_ref,evidence_sha256,reviewed_by,created_at) '
                .'VALUES (:provider,:scope,:external,:plan,\'accepted\',:ref,:sha,:reviewer,UTC_TIMESTAMP())'
            );
            foreach(self::MAPPINGS as $external=>$mapping){
                $q->execute([
                    ':provider'=>self::PROVIDER, ':scope'=>self::SCOPE, ':external'=>$external, ':plan'=>$mapping['id'],
                    ':ref'=>self::EVIDENCE_REF, ':sha'=>self::EVIDENCE_RESULT_SHA256, ':reviewer'=>self::REVIEWED_BY,
                ]);
            }
            if ($this->classifyRows($this->scopeRows(false))!=='exact') throw new RuntimeException('SEARCH3_MEAL_POSTINSERT_MISMATCH');
            $this->db->commit();
        } catch(Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
        if ($this->classifyRows($this->scopeRows(false))!=='exact') throw new RuntimeException('SEARCH3_MEAL_POSTCOMMIT_MISMATCH');
        return ['state'=>'seeded','writes'=>4,'rows'=>4];
    }

    private function assertCanonicalPlans(): void
    {
        if (!$this->tableExists('anytour_meal_plans')) throw new RuntimeException('SEARCH3_MEAL_CANONICAL_PLANS_ABSENT');
        $ids=array_values(array_map(static fn(array $m):int=>$m['id'],self::MAPPINGS));
        $q=$this->db->prepare('SELECT id,code,name_ru,is_active FROM anytour_meal_plans WHERE id IN (?,?,?,?) ORDER BY id');
        $q->execute($ids); $by=[];
        foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r) $by[(int)$r['id']]=$r;
        foreach(self::MAPPINGS as $m){
            $r=$by[$m['id']]??null;
            if(!is_array($r) || (string)$r['code']!==$m['code'] || (string)$r['name_ru']!==$m['name'] || (int)$r['is_active']!==1){
                throw new RuntimeException('SEARCH3_MEAL_CANONICAL_PLAN_DRIFT:'.$m['id']);
            }
        }
    }

    private function tableExists(string $table): bool
    {
        $q=$this->db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
        $q->execute([$table]); return (int)$q->fetchColumn()===1;
    }

    private function assertExactSchema(): void
    {
        $q=$this->db->prepare('SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION');
        $q->execute([self::TABLE]); $rows=$q->fetchAll(PDO::FETCH_ASSOC);
        $expected=[
            ['provider','varchar(16)','NO','ascii_bin'], ['scope_key','varbinary(128)','NO',null], ['external_id','varbinary(128)','NO',null],
            ['meal_plan_id','bigint unsigned','YES',null], ['state',"enum('pending','accepted','rejected','conflict')",'NO','utf8mb4_bin'],
            ['evidence_ref','varchar(255)','NO','utf8mb4_bin'], ['evidence_sha256','char(64)','NO','ascii_bin'],
            ['reviewed_by','varchar(128)','NO','utf8mb4_bin'], ['created_at','datetime','NO',null],
        ];
        if(count($rows)!==count($expected)) throw new RuntimeException('SEARCH3_MEAL_SCHEMA_COLUMN_COUNT');
        foreach($expected as $i=>$want){$got=$rows[$i]; if((string)$got['COLUMN_NAME']!==$want[0] || strtolower((string)$got['COLUMN_TYPE'])!==$want[1] || (string)$got['IS_NULLABLE']!==$want[2] || ($got['COLLATION_NAME']??null)!==$want[3]) throw new RuntimeException('SEARCH3_MEAL_SCHEMA_COLUMN_DRIFT:'.$want[0]);}

        $q=$this->db->prepare('SELECT CONSTRAINT_NAME,CONSTRAINT_TYPE FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY CONSTRAINT_NAME');
        $q->execute([self::TABLE]); $constraints=[]; foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r)$constraints[(string)$r['CONSTRAINT_NAME']]=(string)$r['CONSTRAINT_TYPE'];
        $required=['PRIMARY'=>'PRIMARY KEY','fk_search_meal_plan'=>'FOREIGN KEY','ck_search_meal_provider'=>'CHECK','ck_search_meal_state'=>'CHECK','ck_search_meal_keys'=>'CHECK'];
        foreach($required as $name=>$type) if(($constraints[$name]??null)!==$type) throw new RuntimeException('SEARCH3_MEAL_SCHEMA_CONSTRAINT_DRIFT:'.$name);
        if(count($constraints)!==count($required)) throw new RuntimeException('SEARCH3_MEAL_SCHEMA_CONSTRAINT_COUNT');

        $q=$this->db->prepare("SELECT INDEX_NAME,NON_UNIQUE,GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',') AS cols FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? GROUP BY INDEX_NAME,NON_UNIQUE ORDER BY INDEX_NAME");
        $q->execute([self::TABLE]); $indexes=[]; foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r)$indexes[(string)$r['INDEX_NAME']]=[(int)$r['NON_UNIQUE'],(string)$r['cols']];
        $expectedIndexes=[
            'PRIMARY'=>[0,'provider,scope_key,external_id'],
            'ix_search_meal_plan'=>[1,'provider,scope_key,meal_plan_id'],
            // MySQL/InnoDB creates this supporting index because the named composite index does not start with meal_plan_id.
            'fk_search_meal_plan'=>[1,'meal_plan_id'],
        ];
        foreach($expectedIndexes as $name=>$value) if(($indexes[$name]??null)!==$value) throw new RuntimeException('SEARCH3_MEAL_SCHEMA_INDEX_DRIFT:'.$name);
        if(count($indexes)!==count($expectedIndexes)) throw new RuntimeException('SEARCH3_MEAL_SCHEMA_INDEX_COUNT');
    }

    /** @return list<array<string,mixed>> */
    private function scopeRows(bool $forUpdate): array
    {
        $sql='SELECT provider,scope_key,external_id,meal_plan_id,state,evidence_ref,evidence_sha256,reviewed_by FROM '.self::TABLE.' WHERE provider=? AND scope_key=? ORDER BY external_id'.($forUpdate?' FOR UPDATE':'');
        $q=$this->db->prepare($sql); $q->execute([self::PROVIDER,self::SCOPE]); return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @param list<array<string,mixed>> $rows */
    private function classifyRows(array $rows): string
    {
        if($rows===[]) return 'empty';
        if(count($rows)!==4) return 'partial_or_foreign';
        foreach($rows as $r){
            $external=(string)$r['external_id']; $m=self::MAPPINGS[$external]??null;
            if($m===null) return 'foreign';
            if((string)$r['provider']!==self::PROVIDER || (string)$r['scope_key']!==self::SCOPE || (int)$r['meal_plan_id']!==$m['id'] || (string)$r['state']!=='accepted' || (string)$r['evidence_ref']!==self::EVIDENCE_REF || (string)$r['evidence_sha256']!==self::EVIDENCE_RESULT_SHA256 || (string)$r['reviewed_by']!==self::REVIEWED_BY) return 'conflict';
        }
        return 'exact';
    }
}

function search3MealActivationCli(array $argv): int
{
    $command=$argv[1]??'';
    if(!in_array($command,['plan','install-schema','seed'],true)){fwrite(STDERR,"usage: php search3_meal_provider_mapping_activate_v1.php plan|install-schema|seed [--allow-write]\n");return 64;}
    $dsn=(string)getenv('SEARCH3_MEAL_ACTIVATION_DSN');
    if($dsn===''){fwrite(STDERR,"SEARCH3_MEAL_ACTIVATION_DSN is required\n");return 64;}
    try{
        $pdo=new PDO($dsn,(string)getenv('SEARCH3_MEAL_ACTIVATION_USER'),(string)getenv('SEARCH3_MEAL_ACTIVATION_PASSWORD'),[PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
        $a=new Search3MealProviderMappingActivateV1($pdo); $allow=in_array('--allow-write',$argv,true);
        $result=match($command){'plan'=>$a->plan(),'install-schema'=>$a->installSchema($allow),'seed'=>$a->seed($allow)};
        echo json_encode($result,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n"; return 0;
    }catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");return 1;}
}

if(realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__) exit(search3MealActivationCli($argv));
