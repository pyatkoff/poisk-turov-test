<?php
declare(strict_types=1);

/** Strict local doubles. Only the existing two canonical SELECTs and legacy SELECT are allowed. */
final class CanonicalReadFakePDO extends PDO
{
    public array $events = [], $bindings = [];
    public bool $active = false, $fail = false;
    public array $targets = ['102'=>1, '106'=>2, '108'=>3, '110'=>1, '1'=>102];
    public array $profiles = [];
    public function __construct() {
        foreach ([1,2,3,102] as $id) {
            $json = json_encode(['name'=>'Own '.$id, 'description'=>'Our text',
                'images'=>['https://fixture.test/'.$id.'.jpg']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $this->profiles[$id] = ['id'=>$id, 'profile_json'=>$json,
                'profile_sha256'=>hash('sha256',$json), 'revision'=>7, 'is_active'=>$id===3?0:1];
        }
    }
    public function getAttribute(int $attribute): mixed { return 'mysql'; }
    public function inTransaction(): bool { return $this->active; }
    public function exec(string $statement): int|false {
        if (!in_array($statement,['SET TRANSACTION ISOLATION LEVEL REPEATABLE READ','SET TRANSACTION READ ONLY'],true)) {
            throw new RuntimeException('Unexpected non-read SQL');
        }
        $this->events[]=$statement; return 0;
    }
    public function beginTransaction(): bool { $this->events[]='BEGIN'; $this->active=true; return true; }
    public function commit(): bool { $this->events[]='COMMIT'; $this->active=false; return true; }
    public function rollBack(): bool { $this->events[]='ROLLBACK'; $this->active=false; return true; }
    public function prepare(string $query, array $options=[]): PDOStatement|false {
        if (!str_starts_with($query,'SELECT ')) throw new RuntimeException('Only SELECT');
        $this->events[]=$query;
        if ($this->fail) throw new RuntimeException('Fixture connection unavailable');
        return new CanonicalReadFakeStatement($this,$query);
    }
}
final class CanonicalReadFakeStatement extends PDOStatement
{
    private array $values=[];
    public function __construct(private CanonicalReadFakePDO $db, private string $sql) {}
    public function bindValue(string|int $param, mixed $value, int $type=PDO::PARAM_STR): bool {
        $this->values[(int)$param-1]=$value; return true;
    }
    public function execute(?array $params=null): bool {
        if ($params!==null) $this->values=$params;
        $this->db->bindings[]=$this->values; return true;
    }
    public function fetchAll(int $mode=PDO::FETCH_DEFAULT, mixed ...$args): array {
        $result=[];
        if (str_contains($this->sql,'FROM anytour_hotel_sources')) {
            if (!str_contains($this->sql,"namespace='legacy_catalog'")) throw new RuntimeException('Wrong namespace');
            foreach ($this->values as $id) if (isset($this->db->targets[$id])) $result[$id]=$this->db->targets[$id];
        } elseif (str_contains($this->sql,'FROM anytour_hotels')) {
            if (!str_contains($this->sql,'is_active=1')) throw new RuntimeException('Inactive guard missing');
            foreach ($this->values as $id) if (($this->db->profiles[$id]['is_active']??0)===1) $result[]=$this->db->profiles[$id];
        } elseif (str_contains($this->sql,'FROM catalog_hotels h')) {
            foreach ($this->values as $id) if (in_array((int)$id,[1,102],true)) $result[]=[
                'id'=>$id,'name'=>'Legacy '.$id,'country_id'=>4,'country_name'=>'Country',
                'region_id'=>null,'region_name'=>null,'subregion_id'=>null,'subregion_name'=>null,
                'category'=>5,'rating'=>null,'hotel_type'=>null,'latitude'=>null,'longitude'=>null,
                'primary_image_url'=>null,'description'=>'Legacy text', 'status'=>'success'];
        } else throw new RuntimeException('Unexpected SELECT');
        return $result;
    }
}
if (defined('ANYTOUR_CANONICAL_TEST_LIBRARY')) return;
require_once __DIR__ . '/../v2/data/anytour-canonical-catalog-v1.php';

$checks=0;
function cr_check(bool $ok,string $message): void {
    global $checks; $checks++; if (!$ok) throw new RuntimeException($message);
}
function cr_reject(callable $fn,string $message): void {
    try {$fn();} catch (Throwable) {cr_check(true,$message);return;}
    cr_check(false,$message);
}
$db=new CanonicalReadFakePDO(); $catalogue=new AnyTourCanonicalCatalog($db);
$result=$catalogue->readLegacyProfiles([106,102,110,106,108,999,1]);
cr_check($result['requestedLegacyIds']===[106,102,110,108,999,1],'caller order/dedup');
cr_check(array_column($result['items'],'id')===[2,1,102],'own IDs ordered, deduped, no numeric identity guess');
cr_check($result['links']===[['legacyHotelId'=>106,'anytourHotelId'=>2],['legacyHotelId'=>102,'anytourHotelId'=>1],
    ['legacyHotelId'=>110,'anytourHotelId'=>1],['legacyHotelId'=>1,'anytourHotelId'=>102]],'explicit separate identities');
cr_check($result['missingLegacyIds']===[108,999],'inactive and missing have no fallback');
cr_check($result['items'][0]['description']==='Our text' && $result['items'][0]['revision']===7,'own editorial content');
cr_check(array_slice($db->events,0,3)===['SET TRANSACTION ISOLATION LEVEL REPEATABLE READ','SET TRANSACTION READ ONLY','BEGIN'], 'snapshot starts before SELECT');
cr_check(count($db->bindings)===2 && end($db->events)==='COMMIT' && !$db->active,'two SELECTs and committed read only');
cr_check(array_reduce($db->bindings[0],fn($v,$id)=>$v&&is_string($id),true),'exact namespace keys bound as strings');
foreach ([[],[0],[-1],[true],[1.0],['01'],[' 1'],['+1'],['1e3'],['1 OR 1=1'],[[1]],[null],
    ['a'=>1],[str_repeat('9',40)],range(1,101)] as $invalid) {
    $before=$db->events;
    cr_reject(fn()=>$catalogue->readLegacyProfiles($invalid),'invalid input refused');
    cr_check($db->events===$before,'validation before DB');
}
$db=new CanonicalReadFakePDO();$db->active=true;
cr_reject(fn()=>(new AnyTourCanonicalCatalog($db))->readLegacyProfiles([102]),'caller transaction rejected');
cr_check($db->events===[] && $db->active,'caller transaction not rolled back');
$db=new CanonicalReadFakePDO();$db->fail=true;
cr_reject(fn()=>(new AnyTourCanonicalCatalog($db))->readLegacyProfiles([102]),'DB failure throws');
cr_check(end($db->events)==='ROLLBACK' && !$db->active,'own transaction rolled back');
$db=new CanonicalReadFakePDO();$db->profiles[1]['profile_sha256']=str_repeat('0',64);
cr_reject(fn()=>(new AnyTourCanonicalCatalog($db))->readLegacyProfiles([106,102]),'corruption fails whole batch');
cr_check(end($db->events)==='ROLLBACK','corruption not returned as empty success');
$db=new CanonicalReadFakePDO();
$r=(new AnyTourCanonicalCatalog($db))->readLegacyProfiles([999]);
cr_check($r['items']===[] && $r['links']===[] && $r['missingLegacyIds']===[999] && count($db->bindings)===1,'unmapped avoids empty IN query');

function cr_remove(string $path): void {
    if (is_link($path) || is_file($path)) {unlink($path);return;}
    foreach (scandir($path) as $name) if ($name!=='.' && $name!=='..') cr_remove($path.'/'.$name);
    rmdir($path);
}
/** Real HTTP process, with either strict PDO doubles or disposable real MySQL. */
function cr_http(string $dsn=''): void {
    $root=sys_get_temp_dir().'/anytour-read-'.bin2hex(random_bytes(8));mkdir($root,0700);
    $files=['hotel-details-read-v1.php','hotel-presentation-read-v1.php','hotel-details-v1.php','anytour-canonical-catalog-v1.php'];
    $routes=['/data','/_preview/search3-site-candidate/data','/_preview/search3-local-candidate/data'];
    foreach ($routes as $route) {
        mkdir($root.$route,0700,true);
        foreach ($files as $file) copy(__DIR__.'/../v2/data/'.$file,$root.$route.'/'.$file);
        $stub='<?php declare(strict_types=1); define("ANYTOUR_CANONICAL_TEST_LIBRARY", true); require_once '.var_export(__FILE__,true).';'.
            'function v2_data_db(): PDO {file_put_contents('.var_export($root.'/db-access',true).',"DB\\n",FILE_APPEND);'.
            'if(is_file('.var_export($root.'/fail',true).')) throw new RuntimeException("private fixture failure");'.
            ($dsn===''?'return new CanonicalReadFakePDO();':
            'return new PDO('.var_export($dsn,true).',"root",getenv("ANYTOUR_TEST_PASSWORD"),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);').'}';
        file_put_contents($root.$route.'/db-v1.php',$stub);
    }
    mkdir($root.'/alias/_preview/search3-local-candidate',0700,true);
    symlink($root.'/data',$root.'/alias/_preview/search3-local-candidate/data');
    $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$error);
    if (!$socket) throw new RuntimeException('No loopback port');
    $address=stream_socket_get_name($socket,false);fclose($socket);
    $process=proc_open([PHP_BINARY,'-S',$address,'-t',$root],[0=>['pipe','r'],1=>['file',$root.'/http.log','a'],2=>['file',$root.'/http.log','a']],$pipes);
    if (!is_resource($process)) throw new RuntimeException('HTTP process failed');
    fclose($pipes[0]);
    $request=static function(string $route,array $query,string $method='GET') use ($address): array {
        $ctx=stream_context_create(['http'=>['ignore_errors'=>true,'timeout'=>5,'method'=>$method,
            'header'=>"X-Forwarded-Host: anytoour.ru\r\nX-Original-URL: /_preview/search3-local-candidate/data/hotel-details-read-v1.php\r\n"]]);
        $raw=file_get_contents('http://'.$address.$route.'/hotel-details-read-v1.php?'.http_build_query($query),false,$ctx);
        $headers=$http_response_header??[];
        preg_match('/ ([0-9]{3}) /',$headers[0]??'',$m);
        return [(int)($m[1]??0),json_decode((string)$raw,true,512,JSON_THROW_ON_ERROR),strtolower(implode("\n",$headers))];
    };
    try {
        $ready=false;
        for ($i=0;$i<100;$i++) {$probe=@stream_socket_client('tcp://'.$address,$e,$s,0.05);if($probe){fclose($probe);$ready=true;break;}usleep(20000);}
        cr_check($ready,'local HTTP started');
        foreach (['/data','/_preview/search3-site-candidate/data','/alias/_preview/search3-local-candidate/data'] as $route) {
            [$status,$body,$headers]=$request($route,['catalog'=>'anytour','legacyHotelIds'=>[102]]);
            cr_check($status===403 && !$body['ok'] && str_contains($headers,'cache-control: no-store'),'route and symlink isolation');
        }
        cr_check(!file_exists($root.'/db-access'),'isolation denied before any DB access');
        $route='/_preview/search3-local-candidate/data';
        foreach ([['legacyHotelIds'=>[102]],['catalog'=>'unknown','legacyHotelIds'=>[102]],['catalog'=>['anytour'],'legacyHotelIds'=>[102]],
            ['catalog'=>'anytour','hotelId'=>102],['catalog'=>'anytour','legacyHotelIds'=>[102],'hotelId'=>102],
            ['catalog'=>'anytour','legacyHotelIds'=>'102'],['catalog'=>'anytour','legacyHotelIds'=>['01']],
            ['catalog'=>'anytour','anytourHotelId'=>[1]],['catalog'=>'anytour','anytourHotelIds'=>range(1,101)],
            ['catalog'=>'anytour','legacyHotelIds'=>[102],'anytourHotelId'=>1]] as $query) {
            [$status,$body]=$request($route,$query);cr_check($status===400 && !$body['ok'],'malformed explicit mode rejected');
        }
        [$status,$body,$headers]=$request($route,['catalog'=>'anytour','anytourHotelId'=>1],'POST');
        cr_check($status===405 && str_contains($headers,'allow: get'),'GET only');
        cr_check(!file_exists($root.'/db-access'),'invalid inputs rejected before DB access');
        [$status,$body,$headers]=$request($route,['catalog'=>'anytour','legacyHotelIds'=>[106,102,110,108,999,1]]);
        cr_check($status===200 && $body['ok'] && array_column($body['items'],'id')===[2,1,102],'HTTP current legacy to own bridge');
        cr_check($body['missingLegacyIds']===[108,999] && count($body['links'])===4,'HTTP missing/active/convergence');
        cr_check(str_contains($headers,'cache-control: no-store') && str_contains($headers,'x-content-type-options: nosniff'),'canonical no stale shared cache');
        [$status,$body]=$request($route,['catalog'=>'anytour','anytourHotelId'=>1]);
        cr_check($status===200 && $body['item']['id']===1 && $body['item']['name']==='Own 1','direct own ID read');
        [$status,$body]=$request($route,['catalog'=>'anytour','anytourHotelId'=>3]);
        cr_check($status===404 && !$body['ok'],'inactive scalar 404');
        [$status,$body]=$request($route,['catalog'=>'anytour','anytourHotelIds'=>[2,1,3,999,2]]);
        cr_check($status===200 && array_column($body['items'],'id')===[2,1] && $body['requestedAnyTourIds']===[2,1,3,999]
            && $body['missingAnyTourIds']===[3,999],'own batch order and typed missing IDs');
        foreach (['/data',$route] as $legacyRoute) {
            [$status,$body,$headers]=$request($legacyRoute,['hotelId'=>102]);
            cr_check($status===200 && $body['item']['id']===102 && $body['item']['name']==='Legacy 102'
                && $body['source']==='anytour-local-hotel' && str_contains($headers,'public, max-age=300'),'unchanged legacy scalar/cache');
            [$status,$body]=$request($legacyRoute,['hotelIds'=>[102,1,999]]);
            cr_check($status===200 && array_column($body['items'],'id')===[102,1] && $body['missingIds']===[999],'unchanged legacy batch');
        }
        touch($root.'/fail');
        [$status,$body,$headers]=$request($route,['catalog'=>'anytour','legacyHotelIds'=>[102]]);
        cr_check($status===503 && !$body['ok'] && str_contains($headers,'cache-control: no-store')
            && !str_contains(json_encode($body),'private'),'database failure is generic no-store error, not empty success');
    } finally {proc_terminate($process);proc_close($process);cr_remove($root);}
}
cr_http();
$localChecks=$checks;
if (in_array('--unit-only',$argv,true)) {
    echo "ANYTOUR_CANONICAL_READ_LOCAL_OK checks=$checks SQL_NOT_RUN=1 HTTP_PDO_DOUBLE=1\n";exit;
}

// Full mode never silently skips SQL. Strict disposable loopback guard before access.
$dsn=(string)getenv('ANYTOUR_CANONICAL_READ_TEST_DSN');
if (!preg_match('/^mysql:host=127\.0\.0\.1;port=[0-9]+;dbname=anytour_canonical_read_fixture;charset=utf8mb4$/D',$dsn)) {
    throw new RuntimeException('Explicit disposable MySQL canonical-read fixture required');
}
function cr_connection(): PDO {
    global $dsn;
    return new PDO($dsn,'root',(string)getenv('ANYTOUR_TEST_PASSWORD'),[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
}
$pdo=cr_connection();cr_check($pdo->query('SHOW TABLES')->fetchAll()===[],'empty dedicated fixture');
$schema=file_get_contents(__DIR__.'/../v2/data/migrations/20260916-anytour-canonical-catalog.sql');
foreach (explode(';',preg_replace('/^--.*$/m','',$schema)) as $sql) if(trim($sql)!=='')$pdo->exec($sql);
$fixture=new CanonicalReadFakePDO();
$insert=$pdo->prepare('INSERT INTO anytour_hotels (id,profile_json,profile_sha256,revision,is_active,created_at,updated_at) VALUES (?,?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())');
foreach ($fixture->profiles as $row) $insert->execute(array_values($row));
$bridge=$pdo->prepare('INSERT INTO anytour_hotel_sources (namespace,external_key,anytour_hotel_id,acquired_via,source_json,source_sha256,first_seen_at,last_seen_at) VALUES (?,?,?,\'saved_catalog\',\'{}\',?,UTC_TIMESTAMP(),UTC_TIMESTAMP())');
foreach ($fixture->targets as $legacy=>$own) $bridge->execute(['legacy_catalog',(string)$legacy,$own,hash('sha256','{}')]);
$bridge->execute(['operator_5','999',1,hash('sha256','{}')]);
$bridge->execute(['legacy_catalog','0102',2,hash('sha256','{}')]);
$pdo->exec("CREATE TABLE catalog_hotels (id BIGINT PRIMARY KEY,name VARCHAR(255),is_active TINYINT DEFAULT 1,
 country_id INT DEFAULT 4,country_name VARCHAR(255) DEFAULT 'Country',region_id INT NULL,region_name VARCHAR(255),
 subregion_id INT NULL,subregion_name VARCHAR(255),category INT DEFAULT 5,rating FLOAT,hotel_type INT,
 latitude FLOAT,longitude FLOAT,primary_image_url TEXT) ENGINE=InnoDB");
$pdo->exec('CREATE TABLE catalog_hotel_details (hotel_id BIGINT PRIMARY KEY,status VARCHAR(32),description TEXT,address TEXT,place TEXT,
 build_info TEXT,repair_info TEXT,square_info TEXT,images_json TEXT,infrastructure_json TEXT,meals_json TEXT,services_json TEXT,room_types TEXT,fetched_at DATETIME) ENGINE=InnoDB');
$pdo->exec("INSERT INTO catalog_hotels (id,name) VALUES (1,'Legacy 1'),(102,'Legacy 102')");
function cr_digest(PDO $pdo): string {
    $all=[];
    foreach (['anytour_catalog_control','anytour_hotels','anytour_hotel_sources','catalog_hotels','catalog_hotel_details'] as $table) {
        $all[$table]=$pdo->query('SELECT * FROM '.$table.' ORDER BY 1')->fetchAll(PDO::FETCH_ASSOC);
    }
    return hash('sha256',AnyTourCanonicalCatalog::json($all));
}
$before=cr_digest($pdo);$catalogue=new AnyTourCanonicalCatalog($pdo);
$r=$catalogue->readLegacyProfiles([106,102,110,108,999,1]);
cr_check(array_column($r['items'],'id')===[2,1,102] && $r['missingLegacyIds']===[108,999],'real namespace/exact-byte/active collision guards');
cr_check(count($r['links'])===4 && count($r['items'])===3,'real many sources to one own profile');
cr_http($dsn);
cr_check(cr_digest($pdo)===$before,'all actual HTTP/catalog reads leave every table unchanged');
$mass=[];
for($i=0;$i<100;$i++) {
    $own=1000+$i;$legacy=10000+$i;$mass[]=$legacy;
    $json=AnyTourCanonicalCatalog::json(['name'=>'Mass '.$own,'description'=>'Canonical','images'=>['https://fixture.test/'.$own.'.jpg']]);
    $insert->execute([$own,$json,hash('sha256',$json),1,1]);
    $bridge->execute(['legacy_catalog',(string)$legacy,$own,hash('sha256','{}')]);
}
$mass=array_reverse($mass);$before=cr_digest($pdo);$r=$catalogue->readLegacyProfiles($mass);
cr_check(count($r['items'])===100 && count($r['links'])===100 && $r['items'][0]['id']===1099,'100 actual profiles preserve caller order');
cr_check(cr_digest($pdo)===$before,'mass read has no writes');
$pdo->exec("UPDATE anytour_hotels SET profile_sha256=REPEAT('0',64) WHERE id=1");
cr_reject(fn()=>$catalogue->readLegacyProfiles([106,102]),'real corrupted profile aborts batch');
cr_check(!$pdo->inTransaction(),'real failed read rolled back');
$pdo->prepare('UPDATE anytour_hotels SET profile_sha256=? WHERE id=1')->execute([$fixture->profiles[1]['profile_sha256']]);
$pdo->exec('SET TRANSACTION READ ONLY');$pdo->beginTransaction();
cr_reject(fn()=>$pdo->exec("UPDATE anytour_hotels SET revision=revision+1 WHERE id=1"),'MySQL enforces READ ONLY');
cr_reject(fn()=>$catalogue->readLegacyProfiles([102]),'real caller transaction refused');
cr_check($pdo->inTransaction(),'caller transaction preserved');$pdo->rollBack();

// Mutate on a separate connection BETWEEN the two SELECTs: first read retains its snapshot.
final class CanonicalReadConcurrentPDO extends PDO {
    public bool $mutate=true;
    public function prepare(string $query,array $options=[]): PDOStatement|false {
        if($this->mutate && str_contains($query,'FROM anytour_hotels')) {
            $this->mutate=false;$other=cr_connection();
            $other->exec("UPDATE anytour_hotel_sources SET anytour_hotel_id=2 WHERE namespace='legacy_catalog' AND external_key='102'");
            $json=AnyTourCanonicalCatalog::json(['name'=>'New editorial content']);
            $other->prepare('UPDATE anytour_hotels SET profile_json=?,profile_sha256=? WHERE id=1')->execute([$json,hash('sha256',$json)]);
        }
        return parent::prepare($query,$options);
    }
}
$probe=new CanonicalReadConcurrentPDO($dsn,'root',(string)getenv('ANYTOUR_TEST_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$r=(new AnyTourCanonicalCatalog($probe))->readLegacyProfiles([102]);
cr_check($r['links'][0]['anytourHotelId']===1 && $r['items'][0]['name']==='Own 1','real coherent two-query snapshot during concurrent change');
$r=$catalogue->readLegacyProfiles([102]);
cr_check($r['links'][0]['anytourHotelId']===2 && $r['items'][0]['name']==='Own 2','next read sees current link, not retained old cache');
echo "ANYTOUR_CANONICAL_READ_OK checks=$checks local=$localChecks sql=REAL_MYSQL http=REAL_HTTP\n";
