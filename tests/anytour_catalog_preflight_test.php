<?php
/** Contract tests with a strict PDO double. These are NOT a real MySQL execution. */
declare(strict_types=1);
require_once __DIR__ . '/../v2/data/anytour-canonical-catalog-v1.php';

final class PreflightStatement extends PDOStatement
{
    private array $values = [];
    public function __construct(private array $rows, private ?Closure $reader = null) {}
    public function bindValue(string|int $param, mixed $value, int $type = PDO::PARAM_STR): bool
    {
        $this->values[$param] = $value;
        return true;
    }
    public function execute(?array $params = null): bool
    {
        if ($this->reader !== null) $this->rows = ($this->reader)($params ?? array_values($this->values));
        return true;
    }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        if ($mode === PDO::FETCH_KEY_PAIR) {
            $result = [];
            foreach ($this->rows as $row) { $v = array_values($row); $result[$v[0]] = $v[1]; }
            return $result;
        }
        return $this->rows;
    }
    public function fetchColumn(int $column = 0): mixed
    {
        return isset($this->rows[0]) ? array_values($this->rows[0])[$column] : false;
    }
}

final class PreflightPDO extends PDO
{
    public array $sql = [];
    public array $tables = [];
    public array $rows = [];
    public string $driver = 'mysql';
    public bool $transaction = false;
    public bool $failRead = false;
    public int $commits = 0;
    public int $rollbacks = 0;
    public function __construct()
    {
        foreach (['catalog_hotels','catalog_hotel_details'] as $name) {
            $this->tables[$name] = ['TABLE_NAME'=>$name,'ENGINE'=>'InnoDB','TABLE_TYPE'=>'BASE TABLE'];
        }
    }
    public function getAttribute(int $attribute): mixed { return $this->driver; }
    public function inTransaction(): bool { return $this->transaction; }
    public function beginTransaction(): bool
    {
        if ($this->transaction) throw new LogicException('Nested transaction');
        return $this->transaction = true;
    }
    public function commit(): bool { $this->commits++; $this->transaction=false; return true; }
    public function rollBack(): bool { $this->rollbacks++; $this->transaction=false; return true; }
    public function exec(string $statement): int|false
    {
        $this->sql[]=$statement;
        if (!in_array($statement, ['SET TRANSACTION ISOLATION LEVEL REPEATABLE READ', 'SET TRANSACTION READ ONLY'], true)) {
            throw new LogicException('A write or unexpected statement was attempted');
        }
        return 0;
    }
    public function query(string $query, ?int $fetchMode = null, mixed ...$args): PDOStatement|false
    {
        $this->sql[]=$query;
        if ($query==='SELECT VERSION()') return new PreflightStatement([['version'=>'8.0.46-private-host']]);
        if ($query==='SELECT DATABASE()') return new PreflightStatement([['db'=>'private_fixture_name']]);
        if (str_contains($query,'FROM information_schema.TABLES')) return new PreflightStatement(array_values($this->tables));
        if ($query==='SELECT schema_version FROM anytour_catalog_control WHERE singleton_id=1') return new PreflightStatement([['version'=>1]]);
        throw new LogicException('Unexpected query');
    }
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $this->sql[]=$query;
        if (str_contains($query,'FROM catalog_hotels h')) {
            if ($this->failRead) throw new RuntimeException('Fixture source read failure');
            return new PreflightStatement([], function (array $ids): array {
                return array_values(array_filter($this->rows, fn(array $r): bool => in_array($r['id'],$ids,true)));
            });
        }
        if (str_contains($query,'SELECT external_key,anytour_hotel_id FROM anytour_hotel_sources')) return new PreflightStatement([]);
        throw new LogicException('Write/unknown prepare attempted');
    }
    public function addTarget(string $name): void
    {
        $this->tables[$name]=['TABLE_NAME'=>$name,'ENGINE'=>'InnoDB','TABLE_TYPE'=>'BASE TABLE'];
    }
}

$checks=0;
function checked(bool $pass,string $message): void
{
    global $checks; $checks++;
    if (!$pass) throw new RuntimeException($message);
}
function rejected(callable $fn,string $message): void
{
    try { $fn(); } catch (Throwable) { checked(true,$message); return; }
    checked(false,$message);
}
function source(int $id,string $name='Saved hotel'): array
{
    return ['id'=>$id,'name'=>$name,'country_id'=>4,'country_name'=>'Турция',
        'region_id'=>null,'subregion_id'=>null,'category'=>5,'rating'=>null,'hotel_type'=>null,
        'latitude'=>null,'longitude'=>null,'description'=>'Saved description','primary_image_url'=>null,
        'images_json'=>'["https://fixture.test/a.jpg"]','status'=>'success','fetched_at'=>'2026-09-16 00:00:00'];
}
$pdo=new PreflightPDO(); $pdo->rows=[source(9),source(2)];
$catalog=new AnyTourCanonicalCatalog($pdo);
$before=[$pdo->tables,$pdo->rows];
$result=$catalog->preflight([9,'2',9,77]);
checked($result['status']==='preflight_read_only','read-only status');
checked($result['requestedIds']===[2,9,77],'stable IDs');
checked($result['source_profiles']===2 && $result['missingIds']===[77],'real missing IDs from common reader');
checked($result['target_schema_state']==='absent' && $result['target_tables_present']===[],'schema absence succeeds');
checked($result['writes']===0 && $result['supplier_calls']===0,'no writes or suppliers');
checked(!$result['migration_authorized'] && !$result['seed_authorized'],'receipt never authorizes writes');
checked($result['profiles_with_description']===2 && $result['profiles_with_images']===2 && $result['profiles_with_saved_details']===2,'content coverage counts');
checked($result['server_version']==='8.0.46','version excludes private suffix');
checked(!str_contains(json_encode($result),'private_fixture_name'),'database name not disclosed');
checked($result['database_name_sha256']===hash('sha256','private_fixture_name'),'target fingerprint');
checked($before===[$pdo->tables,$pdo->rows],'source and schema unchanged');
checked($pdo->commits===1 && !$pdo->transaction,'transaction closed');
$repeat=$catalog->preflight([2,77,9]);
checked($repeat['source_sha256']===$result['source_sha256'],'deterministic digest');
$pdo->addTarget('anytour_hotels');
checked($catalog->preflight([2])['target_schema_state']==='partial_requires_review','partial schema never auto-adopted');
$pdo->addTarget('anytour_catalog_control'); $pdo->addTarget('anytour_hotel_sources');
checked($catalog->preflight([2])['target_schema_state']==='present_requires_review','existing target always needs review');
checked($catalog->plan([2,9,77])['source_sha256']===$result['source_sha256'],'same digest as existing seed plan');
$pdo->rows[0]['name']='Changed source';
checked($catalog->preflight([2,9,77])['source_sha256']!==$result['source_sha256'],'source drift changes digest');
$pdo->rows=[source(9,'  ')];
checked($catalog->preflight([9])['unnamedIds']===[9],'unnamed profiles exposed');
$pdo->rows=[];
$missing=$catalog->preflight([9]);
checked($missing['source_profiles']===0 && $missing['missingIds']===[9],'empty cohort not called migrated');
$pdo->failRead=true;
rejected(fn()=>$catalog->preflight([9]),'read failure propagates');
checked(!$pdo->transaction && $pdo->rollbacks===1,'failure rolls back read transaction');
$pdo->failRead=false;
$pdo->tables['catalog_hotels']['ENGINE']='MyISAM';
rejected(fn()=>$catalog->preflight([9]),'nontransactional source rejected');
$pdo->tables['catalog_hotels']['ENGINE']='InnoDB'; $pdo->tables['catalog_hotels']['TABLE_TYPE']='VIEW';
rejected(fn()=>$catalog->preflight([9]),'view source rejected');
unset($pdo->tables['catalog_hotels']);
rejected(fn()=>$catalog->preflight([9]),'missing source not fabricated');
$pdo=new PreflightPDO(); $catalog=new AnyTourCanonicalCatalog($pdo);
foreach ([[],[0],['01'],['1 OR 1=1'],range(1,1001)] as $bad) rejected(fn()=>$catalog->preflight($bad),'bad IDs rejected');
checked($pdo->sql===[],'invalid input executes no SQL');
$pdo->driver='sqlite'; rejected(fn()=>$catalog->preflight([9]),'wrong driver rejected');
checked($pdo->sql===[],'wrong driver does not transact');
$pdo->driver='mysql'; $pdo->transaction=true;
rejected(fn()=>$catalog->preflight([9]),'caller transaction rejected');
checked($pdo->transaction && $pdo->commits===0 && $pdo->rollbacks===0,'caller transaction preserved');
$pdo->transaction=false;
$pdo->rows=array_map(fn(int $id):array=>source($id),range(100001,101000));
$bulk=$catalog->preflight(range(100001,101000));
checked($bulk['source_profiles']===1000 && $bulk['missingIds']===[],'full thousand through actual DTO builder');
$selects=array_filter($pdo->sql,fn(string $q):bool=>str_contains($q,'FROM catalog_hotels h'));
checked(count($selects)===10,'bounded 100-row reads reused');
checked(count(array_filter($pdo->sql,fn(string $q):bool=>preg_match('/^\s*(INSERT|UPDATE|DELETE|CREATE|DROP|ALTER|TRUNCATE)\b/i',$q)===1))===0,'strict no-mutation trace');
$cli=file_get_contents(__DIR__.'/../scripts/catalog/anytour_catalog_preflight.php');
checked(!str_contains($cli,'v2_data_db()') && !str_contains($cli,"require_once __DIR__ . '/../../v2/data/db-v1.php'"),'CLI avoids configuration fallback');
checked(!str_contains($cli,'->seed(') && !str_contains($cli,'->exec('),'CLI no schema/install/apply path');
echo "ANYTOUR_PREFLIGHT_CONTRACTS_OK checks=$checks bulk=1000 real_mysql=0 SQL_EXECUTION_NOT_RUN=1\n";
