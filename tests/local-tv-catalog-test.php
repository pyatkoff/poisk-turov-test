<?php
/** Disposable SQL/HTTP fixtures only. No real hotel, supplier or deployed DB access. */
declare(strict_types=1);
require_once __DIR__.'/../v2/data/local-tv-catalog-v1.php';
require_once __DIR__.'/../v2/data/anytour-canonical-catalog-v1.php';

$checks=0;
function local_need(bool $ok,string $message): void { global $checks; $checks++; if(!$ok)throw new RuntimeException($message); }
function local_same(mixed $a,mixed $b,string $message): void { local_need($a===$b,$message.': '.LocalTvCatalogV1::json($a)); }
function local_bad(callable $f,string $message): void { try{$f();}catch(Throwable){local_need(true,$message);return;}throw new RuntimeException($message); }
function local_card(int $id,bool $content=true): array {
    return ['id'=>$id,'name'=>'Synthetic LOCAL fixture '.$id,'country'=>['id'=>4,'name'=>'Тестовая страна'],
        'common'=>$content?['description'=>'<p>Описание &#8203;отеля</p>','address'=>'Тестовый адрес','latitude'=>12.2,'longitude'=>34.4]:[],
        'images'=>$content?array_merge(array_map(static fn($i)=>'https://fixture.example.test/'.$id.'/'.$i.'.jpg',range(1,125)),
            [['url'=>'https://fixture.example.test/'.$id.'/caption.jpg','caption'=>'Подпись фото']]):[],
        'infrastructure'=>$content?['beach'=>'Описание пляжа','pools'=>'Два тестовых бассейна']:[],
        'services'=>$content?['child'=>'Детская игровая комната','internet'=>'Wi-Fi в общественных местах']:[],
        'roomTypes'=>$content?'Описание номеров':null,'extraSourceField'=>['sourceOnly'=>'Сохранить исходные дополнительные сведения']];
}

$root=sys_get_temp_dir().'/local-tv-fixture-'.bin2hex(random_bytes(6));mkdir($root,0700,true);
$dsn=getenv('LOCAL_TV_TEST_DSN')?:'sqlite:'.$root.'/local.sqlite';
$native=str_starts_with($dsn,'mysql:');
if($native&&!preg_match('#^mysql:(?:host=127\.0\.0\.1;port=3306|unix_socket=/[^;]*/local-tv-fixture/mysql\.sock);dbname=local_tv_fixture(?:;charset=utf8mb4)?$#D',$dsn)) {
    throw new RuntimeException('Only the explicit disposable LOCAL MySQL fixture is permitted');
}
$pdo=new PDO($dsn,$native?'root':null,$native?(getenv('LOCAL_TV_TEST_PASSWORD')?:''):null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
if($native)$pdo->exec('SET NAMES utf8mb4');
$schema=file_get_contents(__DIR__.'/../v2/data/migrations/20261010-local-tv-catalog.sql');
if(!$native){
    $schema=preg_replace('/ CHARACTER SET ascii COLLATE ascii_bin/','',$schema);
    $schema=preg_replace('/^    KEY .*\n/m','',$schema);
    $schema=preg_replace('/ ENGINE=InnoDB[^;]*;/',';',$schema);
    $schema=preg_replace('/,\s*\)/',"\n)",$schema);
}
$pdo->exec($schema);$pdo->exec($schema); // Same actual migration; repeat is harmless.
$pdo->exec('CREATE TABLE catalog_hotel_details(hotel_id INT PRIMARY KEY,raw_json TEXT,source_hash VARCHAR(64),fetched_at DATETIME)');
$pdo->exec('CREATE TABLE tour_price_observations(hotel_id INT,search_id INT,source VARCHAR(32),observed_at DATETIME)');
$pdo->exec('CREATE TABLE hot_tours_current(hotel_id INT,fetched_at DATETIME)');
$pdo->exec('CREATE TABLE anytour_hotels(id INT PRIMARY KEY,profile_json TEXT,profile_sha256 VARCHAR(64),revision INT,is_active INT)');
$pdo->exec('CREATE TABLE anytour_hotel_sources(anytour_hotel_id INT,namespace VARCHAR(64),external_key VARCHAR(128),acquired_via VARCHAR(64),source_json TEXT,source_sha256 VARCHAR(64),first_seen_at DATETIME,last_seen_at DATETIME)');
$c=new LocalTvCatalogV1($pdo);$now='2026-10-10 03:35:00';$oldTime='2026-10-08 01:00:00';
local_same($c->discover([['id'=>700001,'name'=>'Synthetic LOCAL fixture']], 'user_search',$oldTime),1,'One observation registers before any card opening');
local_same($c->discover(array_fill(0,50,['id'=>700001]),'scheduled_monitor',$now),0,'Repeat offers/monitor do not duplicate hotel or backlog');
local_same($pdo->query('SELECT pending_since FROM local_tv_hotels WHERE id=700001')->fetchColumn(),$oldTime,'Repeat search never rejuvenates backlog age');
local_bad(static fn()=>$c->discover([['id'=>1]],'demo',$now),'Demo origin cannot enter catalogue');
local_same($c->discover([['id'=>'anex:700001'],['id'=>0],['id'=>true]],'user_search',$now),0,'Foreign/invalid IDs rejected');
$pdo->prepare('INSERT INTO tour_price_observations VALUES(?,?,?,?)')->execute([700002,11,'user_search','2026-10-08 02:00:00']);
$pdo->prepare('INSERT INTO tour_price_observations VALUES(?,?,?,?)')->execute([700001,10,'user_search','2026-10-07 02:00:00']);
$pdo->prepare('INSERT INTO hot_tours_current VALUES(?,?)')->execute([700003,'2026-10-08 03:00:00']);
local_same($c->backfillObserved(),2,'Surviving user and monitor discoveries recovered');
local_same($c->backfillObserved(),0,'Historical backfill idempotent');
local_same($pdo->query('SELECT pending_since FROM local_tv_hotels WHERE id=700001')->fetchColumn(),'2026-10-07 02:00:00','Recovered earlier observation restores true FIFO age');

$seed=['id'=>700001,'name'=>'Synthetic LOCAL fixture 700001','country'=>['id'=>4,'name'=>'Тестовая страна'],
    'description'=>'Импортное описание','images'=>['https://fixture.example.test/seed.jpg'],
    'services'=>[],'infrastructure'=>[],'meals'=>[],'roomTypes'=>null];
$profile=AnyTourCanonicalCatalog::initialProfile($seed);$profile['description']='Ручное описание';
$profile['images']=['https://fixture.example.test/known-imported-gallery.jpg'];
$insertOld=$pdo->prepare('INSERT INTO anytour_hotels VALUES(?,?,?,?,?)');
$json=LocalTvCatalogV1::json($profile);$insertOld->execute([50,$json,hash('sha256',$json),2,1]);
$insertSource=$pdo->prepare('INSERT INTO anytour_hotel_sources VALUES(?,?,?,?,?,?,?,?)');
$json=LocalTvCatalogV1::json($seed);$insertSource->execute([50,'legacy_catalog','700001','saved_catalog',$json,hash('sha256',$json),$oldTime,$oldTime]);
$provider=['accepted'=>true,'nativeId'=>'anex-fixture-123','evidence'=>'fixture://reviewed','manualDecision'=>true];
$json=LocalTvCatalogV1::json($provider);$insertSource->execute([50,'anex:accepted','native-fixture-123','manual_review',$json,hash('sha256',$json),$oldTime,$oldTime]);
$receipt=['canonical_hotel_id'=>50,'accepted_local_hotel_id'=>700001,'result_profile_sha256'=>hash('sha256',LocalTvCatalogV1::json($profile)),
    'result_revision'=>2,'previous_profile_sha256'=>hash('sha256',LocalTvCatalogV1::json($seed)),'fields_updated'=>['images']];
$json=LocalTvCatalogV1::json($receipt);$insertSource->execute([50,'profile_sync:retained_tv_v1','50:2','profile_sync_imported_v1',$json,hash('sha256',$json),$oldTime,$oldTime]);
$insertOld->execute([51,'{}',hash('sha256','{}'),1,1]);
foreach([700002,700003] as $id){$json=LocalTvCatalogV1::json(['id'=>$id,'name'=>'Ambiguous fixture']);$insertSource->execute([51,'legacy_catalog',(string)$id,'saved_catalog',$json,hash('sha256',$json),$oldTime,$oldTime]);}
$legacyBefore=$pdo->query('SELECT * FROM anytour_hotels ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$sourcesBefore=$pdo->query('SELECT * FROM anytour_hotel_sources ORDER BY anytour_hotel_id,namespace,external_key')->fetchAll(PDO::FETCH_ASSOC);
$migration=$c->migrateLinks($now);
local_same($migration['transferred'],1,'Proven old LOCAL to TV link transferred');
local_same($migration['issues'][51],'ambiguous_old_local_to_tv','Ambiguity explicit and isolated');
local_same($pdo->query('SELECT * FROM anytour_hotels ORDER BY id')->fetchAll(PDO::FETCH_ASSOC),$legacyBefore,'Old profiles/IDs/revisions unchanged');
local_same($pdo->query('SELECT * FROM anytour_hotel_sources ORDER BY anytour_hotel_id,namespace,external_key')->fetchAll(PDO::FETCH_ASSOC),$sourcesBefore,'MATCH/provider evidence byte-for-byte retained');
$snapshot=json_decode($pdo->query('SELECT snapshot_json FROM local_tv_legacy_links WHERE old_local_id=50')->fetchColumn(),true);
local_same(json_decode($snapshot['sources'][0]['source_json'],true),$provider,'Accepted manual provider edge in retained snapshot');
local_same($c->migrateLinks($now)['transferred'],0,'Transfer repeat is idempotent');

$raw=LocalTvCatalogV1::json(local_card(700001));
$pdo->prepare('INSERT INTO catalog_hotel_details VALUES(?,?,?,?)')->execute([700001,$raw,hash('sha256',$raw),$now]);
$calls=[];$fetch=static function(int $id)use(&$calls){$calls[]=$id;return local_card($id);};
$r=$c->daily($fetch,1,0,$now);
local_same($calls,[],'Retained full card uses zero supplier calls');
local_same($r['retainedSource'],1,'Retained source counted after successful save');
$item=$c->read([700001])['items'][0];
local_same($item['id'],700001,'New LOCAL primary key equals TV ID');
local_same($item['description'],'Ручное описание','Manual description protected independently');
local_need(!in_array('Описание отеля',array_column($item['descriptionSections'],'text'),true),'Automatic description cannot leak back as a supplemental section');
local_same(count($item['images']),126,'Entire gallery survives beyond old100 limit');
local_same($item['photoDetails'][125]['caption'],'Подпись фото','Caption and source order preserved');
local_same($item['hotelInformation']['infrastructure']['pools'],'Два тестовых бассейна','Additional characteristics saved');
local_same(json_decode($pdo->query('SELECT source_json FROM local_tv_hotels WHERE id=700001')->fetchColumn(),true),local_card(700001),'Entire raw response preserved including unused fields');
local_same($c->read([50],true)['links'],[['oldLocalId'=>50,'tourvisorHotelId'=>700001]],'Old ID resolves via proven edge');
local_same($c->read([700001],true)['items'],[],'Numeric coincidence never substitutes for a bridge');
local_same($c->read([50],true)['items'][0],$item,'Batch TV and old-ID reader agree');
local_need(!isset($item['source_json'])&&!isset($item['extraSourceField'])&&!isset($item['price']),'Public DTO contains no raw/private/offer data');
$rev=$item['revision'];$c->saveSource(700001,local_card(700001),$now);
local_same($c->read([700001])['items'][0]['revision'],$rev,'Source repetition does not create a revision');
$c->setManualFields(700001,['hotelInformation.services.child'=>'Ручное поле для детей','hotelInformation.services.internet'=>'Ручной Интернет'],$rev);
local_bad(static fn()=>$c->setManualFields(700001,['description'=>'Bad overwrite'],$rev),'Stale manual revision rejected');
$c->saveSource(700001,local_card(700001),$now);
local_same($c->read([700001])['items'][0]['hotelInformation']['services']['child'],'Ручное поле для детей','Nested manual field protected while other fields fill');
local_need(in_array('Ручной Интернет',array_column($c->read([700001])['items'][0]['descriptionSections'],'text'),true),'Supplemental text follows protected effective field');

$r=$c->daily(static function(int $id)use(&$calls){$calls[]=$id;if($id===700002)throw new RuntimeException('fixture_network_error');return local_card($id,false);},2,2,$now);
local_same(array_slice($calls,-2),[700002,700003],'Older once-seen backlog served in stable FIFO order');
local_same($r['errors'][700002],'fixture_network_error','Source error has its own reason');
local_same($r['filled'],1,'Independent next hotel continues after an error');
$absent=$c->read([700003])['items'][0]['sourceAbsent'];
local_need(in_array('description',$absent,true)&&in_array('images',$absent,true),'Successful missing fields explicitly source-absent');
$c->daily($fetch,3,3,'2026-10-10 04:35:00');
local_same(array_slice($calls,-2),[700002,700003],'Absent fields and cooling failures cause no repeated request');
$c->daily($fetch,3,3,'2026-10-11 03:35:00');
local_same(end($calls),700002,'Failure retried on next daily run');
$pdo->exec('DELETE FROM tour_price_observations');$pdo->exec('DELETE FROM hot_tours_current');
local_same($c->counts()['discovered'],3,'History cleanup does not delete catalogue or backlog');
$c->discover([['id'=>700004]],'user_search',$now);
local_same($c->daily($fetch,3,0,'2026-10-11 04:35:00')['budgetDeferred'],1,'Budget exhaustion leaves concrete unfinished work');
local_same($pdo->query('SELECT pending_since FROM local_tv_hotels WHERE id=700004')->fetchColumn(),$now,'Unfinished age survives each run');
$c->daily($fetch,3,3,'2026-10-12 03:35:00');
$before=$c->read([700004])['items'][0];
local_bad(static fn()=>$c->saveSource(700004,[],'2026-10-12 04:00:00'),'Empty response rejected');
local_bad(static fn()=>$c->saveSource(700004,local_card(700003),'2026-10-12 04:00:00'),'Wrong TV identity rejected');
local_same($c->read([700004])['items'][0],$before,'Rejected source never erases good content');
$c->saveSource(700004,local_card(700004,false),'2026-10-12 05:00:00');
$partial=$c->read([700004])['items'][0];
local_same($partial['description'],$before['description'],'Sparse successful response preserves good description');
local_same($partial['images'],$before['images'],'Sparse response preserves saved gallery');
local_need(in_array('images',$partial['sourceAbsent'],true),'Source absence stays distinct from preserved presentation');

class LocalCommitUnknownPDO extends PDO {
    public bool $loseCommit=false;
    public function commit(): bool { $result=parent::commit();if($this->loseCommit){$this->loseCommit=false;throw new RuntimeException('fixture_lost_commit_ack');}return $result; }
}
$fault=new LocalCommitUnknownPDO($dsn,$native?'root':null,$native?(getenv('LOCAL_TV_TEST_PASSWORD')?:''):null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$faultCatalogue=new LocalTvCatalogV1($fault);$fault->loseCommit=true;
try{$faultCatalogue->saveSource(700004,local_card(700004),'2026-10-12 06:00:00');throw new RuntimeException('Unknown COMMIT not detected');}
catch(RuntimeException $e){local_same($e->getMessage(),'source_commit_unknown','Unknown COMMIT is surfaced, never claimed rollback');}
local_same($c->read([700004])['items'][0]['contentState'],'ready','Committed good data remain after lost ACK');
$count=count($calls);$c->daily($fetch,10,10,'2026-10-13 03:35:00');local_same(count($calls),$count,'Read state before rerun prevents another download');
foreach([[],[0],[true],['anex:7'],['07'],range(1,101),[1,'bad']]as $bad)local_bad(static fn()=>LocalTvCatalogV1::ids($bad),'Invalid batch rejected whole');

$refreshCalls=[];
$c->daily(static function(int $id)use(&$refreshCalls){$refreshCalls[]=$id;throw new RuntimeException('refresh_failed');},1,1,'2026-12-01 03:35:00');
local_same($refreshCalls,[700001],'Stale retained card cannot replace a needed refresh');
local_same($c->read([700001])['items'][0]['description'],'Ручное описание','Refresh failure preserves good manual data');
$c->daily(static function(int $id)use(&$refreshCalls){$refreshCalls[]=$id;return local_card($id);},1,1,'2026-12-02 03:35:00');
local_same($refreshCalls,[700001,700001],'Retry of a refresh cannot loop on an older retained source');
local_same($c->read([700001])['items'][0]['contentState'],'ready','Successful refresh removes retry state');

$pdo->exec('CREATE TABLE anytour_offers(legacy_hotel_id INT,provider VARCHAR(16),observed_at DATETIME,last_seen_at DATETIME)');
$pdo->exec("INSERT INTO anytour_offers VALUES(800001,'tourvisor','2026-10-01 00:00:00','2026-10-02 00:00:00'),(800099,'anex','2026-10-01 00:00:00','2026-10-02 00:00:00')");
$pdo->exec('CREATE TABLE anytour_offer_price_observations(provider_local_hotel_id INT,provider VARCHAR(16),observed_at DATETIME)');
$pdo->exec("INSERT INTO anytour_offer_price_observations VALUES(800002,'tourvisor','2026-10-01 00:00:00')");
$pdo->exec('CREATE TABLE tour_operator_identity_observations(hotel_id INT,search_id INT,source VARCHAR(32),first_seen_at DATETIME,last_seen_at DATETIME)');
$pdo->exec("INSERT INTO tour_operator_identity_observations VALUES(800003,100,'user_search','2026-10-01 00:00:00','2026-10-02 00:00:00')");
local_same($c->backfillObserved(),3,'Offer store, price history and passive identity evidence recovered');
local_same($c->read([800099])['items'],[],'Foreign provider local IDs cannot seed TV catalogue');

// Actual retained-only CLI: missing, generic, corrupt and wrong-ID cards never cause HTTP or retry writes.
$pdo->exec("ALTER TABLE catalog_hotel_details ADD COLUMN status VARCHAR(16) NOT NULL DEFAULT 'success'");
$c->discover(array_map(static fn($id)=>['id'=>$id],range(900001,900005)),'user_search',$now);
$cacheInsert=$pdo->prepare('INSERT INTO catalog_hotel_details(hotel_id,raw_json,source_hash,fetched_at) VALUES(?,?,?,?)');
foreach(range(900001,900005)as $id){
    $raw=local_card($id);if($id===900002)$raw['name']='Fortuna 5*';if($id===900003)$raw['id']=900005;
    $json=LocalTvCatalogV1::json($raw);$cacheInsert->execute([$id,$json,$id===900004?str_repeat('0',64):hash('sha256',$json),$now]);
}
$c->discover([['id'=>900006]],'user_search',$now);
$raw=local_card(900006);$raw['name']='Roulette 4*';$json=LocalTvCatalogV1::json($raw);
$cacheInsert->execute([900006,$json,hash('sha256',$json),$now]);
$c->setManualFields(900006,['name'=>'Проверенный ручной отель'],1);
$pdo->exec("UPDATE catalog_hotel_details SET status='not_found' WHERE hotel_id=900005");
$c->setManualFields(900001,['description'=>'Ручное описание сохраняется'],1);
$protectedBefore=LocalTvCatalogV1::json([$pdo->query('SELECT * FROM anytour_hotels ORDER BY id')->fetchAll(PDO::FETCH_ASSOC),
    $pdo->query('SELECT * FROM anytour_hotel_sources ORDER BY anytour_hotel_id,namespace,external_key')->fetchAll(PDO::FETCH_ASSOC),
    $pdo->query('SELECT * FROM local_tv_legacy_links ORDER BY old_local_id')->fetchAll(PDO::FETCH_ASSOC),
    $pdo->query('SELECT * FROM catalog_hotel_details ORDER BY hotel_id')->fetchAll(PDO::FETCH_ASSOC)]);
$cliEnv=array_merge(getenv(),['ANYTOUR_LOCAL_TV_CATALOG_ENABLED'=>'1','ANYTOUR_DATA_DSN'=>$dsn,
    'ANYTOUR_DATA_DB_USER'=>$native?'root':'fixture','ANYTOUR_DATA_DB_PASSWORD'=>$native?(getenv('LOCAL_TV_TEST_PASSWORD')?:''):'']);
$runCli=static function(int $budget)use($cliEnv):array{
    $p=proc_open([PHP_BINARY,__DIR__.'/../v2/data/collect-hotel-details-v1.php','--candidate-scope=local','--http-budget='.$budget,'--limit=3000'],
        [0=>['file','/dev/null','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,$cliEnv);
    $out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);return[proc_close($p),$out,$err];
};
if($native){
    [$status,$out,$err]=$runCli(0);local_same($status,0,'Actual LOCAL CLI succeeds with zero HTTP: '.$err.$out);
    $report=json_decode(substr(trim($out),strlen('ANYTOUR_LOCAL_TV_DAILY ')),true,512,JSON_THROW_ON_ERROR);
    local_same($report['supplierHttpAttempts'],0,'Actual CLI makes zero supplier attempts');
    local_same($report['legacyMigration'],['transferred'=>0,'issues'=>[]],'Daily CLI never migrates old bridges');
}else{$c->backfillObserved();local_same($c->dailyRetained(3000)['httpRequests'],0,'SQLite retained method makes zero HTTP attempts');}
$retained=$c->read([900001])['items'][0];local_same(count($retained['images']),126,'Retained daily keeps full gallery');
local_same($retained['description'],'Ручное описание сохраняется','Retained daily preserves manual content');
local_same($c->read([900002])['missingIds'],[900002],'Pristine generic product is excluded from the hotel reader');
local_same($pdo->query('SELECT state FROM local_tv_hotels WHERE id=900002')->fetchColumn(),'excluded','Pristine generic product is terminally classified');
local_same($c->counts()['excludedCount'],1,'Excluded non-hotel product is counted separately');
foreach([900003,900004,900005]as $id)local_same($c->read([$id])['items'][0]['contentState'],'pending','Invalid or absent retained row remains repairable');
local_same($c->read([900006])['items'][0]['contentState'],'pending','Manual hotel evidence blocks automatic generic exclusion');
local_same(LocalTvCatalogV1::json([$pdo->query('SELECT * FROM anytour_hotels ORDER BY id')->fetchAll(PDO::FETCH_ASSOC),
    $pdo->query('SELECT * FROM anytour_hotel_sources ORDER BY anytour_hotel_id,namespace,external_key')->fetchAll(PDO::FETCH_ASSOC),
    $pdo->query('SELECT * FROM local_tv_legacy_links ORDER BY old_local_id')->fetchAll(PDO::FETCH_ASSOC),
    $pdo->query('SELECT * FROM catalog_hotel_details ORDER BY hotel_id')->fetchAll(PDO::FETCH_ASSOC)]),$protectedBefore,'Retained CLI preserves old tables and links exactly');
$beforeDenied=LocalTvCatalogV1::json($pdo->query('SELECT * FROM local_tv_hotels ORDER BY id')->fetchAll(PDO::FETCH_ASSOC));
local_need($runCli(1)[0]!==0,'Unadmitted LOCAL supplier budget fails closed');
local_same(LocalTvCatalogV1::json($pdo->query('SELECT * FROM local_tv_hotels ORDER BY id')->fetchAll(PDO::FETCH_ASSOC)),$beforeDenied,'Refused LOCAL acquisition changes no data');
$retainedRepeat=$c->dailyRetained(3000);local_need(!isset($retainedRepeat['skipped'][900001]),'Successful retained source is not replayed');
local_need(!isset($retainedRepeat['skipped'][900002])&&!isset($retainedRepeat['excluded'][900002]),'Excluded generic product is not selected again');

// Additional actual DB/API snapshots match the fictional LIVE fixture's old501 -> TV101.
if(getenv('LOCAL_TV_TEST_EXPORT')){
    $c->discover([['id'=>101]],'user_search',$now);
    $browserSeed=['id'=>101,'name'=>'Synthetic LOCAL browser fixture','description'=>'Saved fixture'];
    $browserProfile=AnyTourCanonicalCatalog::initialProfile($browserSeed);
    $json=LocalTvCatalogV1::json($browserProfile);$insertOld->execute([501,$json,hash('sha256',$json),1,1]);
    $json=LocalTvCatalogV1::json($browserSeed);$insertSource->execute([501,'legacy_catalog','101','saved_catalog',$json,hash('sha256',$json),$now,$now]);
    $c->migrateLinks($now);$c->saveSource(101,local_card(101),$now);
    $c->setManualFields(101,['description'=>'LOCAL версия 1: ручное описание отеля'],$c->read([101])['items'][0]['revision']);
}

// Real local HTTP endpoint against the same saved fixture, including namespace/isolation guards.
$public=$root.'/public';$data=$public.'/_preview/search3-next-candidate/data';mkdir($data,0700,true);
foreach(['db-v1.php','hotel-details-v1.php','local-tv-catalog-v1.php','local-tv-catalog-read-v1.php']as $file)copy(__DIR__.'/../v2/data/'.$file,$data.'/'.$file);
mkdir($public.'/data');copy($data.'/local-tv-catalog-read-v1.php',$public.'/data/local-tv-catalog-read-v1.php');
foreach(['db-v1.php','hotel-details-v1.php','local-tv-catalog-v1.php']as $file)copy($data.'/'.$file,$public.'/data/'.$file);
$socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$error);$address=stream_socket_get_name($socket,false);fclose($socket);
$env=array_merge(getenv(),['ANYTOUR_DATA_DSN'=>$dsn,'ANYTOUR_DATA_DB_USER'=>$native?'root':'fixture',
    'ANYTOUR_DATA_DB_PASSWORD'=>$native?(getenv('LOCAL_TV_TEST_PASSWORD')?:''):'']);
$server=proc_open([PHP_BINARY,'-S',$address,'-t',$public],[0=>['file','/dev/null','r'],1=>['file',$root.'/http.log','a'],2=>['file',$root.'/http.log','a']],$pipes,$root,$env);
try {
    for($i=0;$i<50;$i++){if($connection=@stream_socket_client('tcp://'.$address,$errno,$error,.1)){fclose($connection);break;}usleep(20000);}
    $request=static function(string $query,string $method='GET',string $path='/_preview/search3-next-candidate/data/local-tv-catalog-read-v1.php')use($address):array{
        $body=file_get_contents('http://'.$address.$path.'?'.$query,false,stream_context_create(['http'=>['method'=>$method,'ignore_errors'=>true,'timeout'=>5]]));
        preg_match('/\s(\d{3})\s/',$http_response_header[0],$status);return[(int)$status[1],json_decode($body,true),implode("\n",$http_response_header)];
    };
    [$status,$body,$headers]=$request('tourvisorHotelIds[]=700001');local_same($status,200,'Real content HTTP read succeeds');
    local_same($body['items'][0],$c->read([700001])['items'][0],'SQL and API projection parity');
    local_need(str_contains($headers,'no-store'),'Reopening must reread current content without paid search');
    local_same($request('oldLocalHotelIds[]=50')[1]['items'][0]['id'],700001,'Actual API resolves old ID explicitly');
    local_same($request('tourvisorHotelIds[]=50')[1]['missingIds'],[50],'TV namespace never guesses old ID');
    foreach(['','tourvisorHotelIds[]=700001&oldLocalHotelIds[]=50','tourvisorHotelIds[]=bad','tourvisorHotelIds=700001']as $query)local_same($request($query)[0],400,'Ambiguous/malformed API namespace rejected');
    local_same($request('tourvisorHotelIds[]=700001','POST')[0],405,'API cannot be used as writer');
    local_same($request('tourvisorHotelIds[]=700001','GET','/data/local-tv-catalog-read-v1.php')[0],403,'Main public site reader is isolated');
    if(getenv('LOCAL_TV_TEST_EXPORT')){
        file_put_contents(getenv('LOCAL_TV_TEST_EXPORT'),LocalTvCatalogV1::json($request('oldLocalHotelIds[]=501')[1]));
        $c->setManualFields(101,['description'=>'LOCAL версия 2: новое ручное описание без повторного поиска'],$c->read([101])['items'][0]['revision']);
        file_put_contents(getenv('LOCAL_TV_TEST_EXPORT').'.updated',LocalTvCatalogV1::json($request('oldLocalHotelIds[]=501')[1]));
    }
}finally{if(is_resource($server)){proc_terminate($server);proc_close($server);}}
echo 'LOCAL_TV_CATALOG_OK checks='.$checks.' database='.($native?'NATIVE_MYSQL':'REAL_SQLITE').' http=REAL_LOCAL supplier_calls=0 production_writes=0' . "\n";
