<?php
declare(strict_types=1);
require_once __DIR__.'/../v2/data/anytour-stay-catalog-v1.php';

function room_key_need(bool $ok,string $label): void
{
    if (!$ok) throw new RuntimeException('CHECK_FAILED:'.$label);
}
function room_key_sql(PDO $pdo,string $path): void
{
    $sql=preg_replace('/^\s*--.*$/m','',(string)file_get_contents($path));
    foreach (preg_split('/;\s*(?:\r?\n|$)/',$sql) ?: [] as $statement) {
        $statement=trim($statement);
        if ($statement!=='') $pdo->exec($statement);
    }
}

$dsn=(string)getenv('ANYTOUR_STAY_ROOM_KEY_TEST_DSN');
$password=(string)getenv('ANYTOUR_STAY_ROOM_KEY_TEST_PASSWORD');
if ($dsn!=='mysql:host=127.0.0.1;port=3306;dbname=anytour_stay_room_key_fixture;charset=utf8mb4') {
    throw new RuntimeException('Dedicated disposable fixture DSN required');
}
$pdo=new PDO($dsn,'root',$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
room_key_need((int)$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()")->fetchColumn()===0,'fresh database');
$root=dirname(__DIR__);
room_key_sql($pdo,$root.'/v2/data/migrations/20260916-anytour-canonical-catalog.sql');
room_key_sql($pdo,$root.'/v2/data/migrations/20260916-anytour-stay-catalog.sql');

$profile=json_encode(['name'=>'Own room-key fixture'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
$insertHotel=$pdo->prepare('INSERT INTO anytour_hotels(profile_json,profile_sha256,created_at,updated_at) VALUES(?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())');
$insertHotel->execute([$profile,hash('sha256',$profile)]);
$hotelId=(int)$pdo->lastInsertId();
$source='{}';
$pdo->prepare("INSERT INTO anytour_hotel_sources(namespace,external_key,anytour_hotel_id,acquired_via,source_json,source_sha256,first_seen_at,last_seen_at) VALUES('tourvisor','77',?,'fixture',?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())")
    ->execute([$hotelId,$source,hash('sha256',$source)]);

$catalog=new AnyTourStayCatalog($pdo);
$pdo->beginTransaction();
$roomId=$catalog->createRoom($hotelId,'own:standard-sea','Стандарт · вид на море','standard',['view'=>'Море']);
$catalog->recordDecision(
    ['namespace'=>'tourvisor','hotelKey'=>'77','operatorKey'=>'5'],
    ['kind'=>'room','keyKind'=>'code','externalKey'=>'001'],
    $hotelId,'accepted',$roomId,
    ['ref'=>'fixture-reviewed','sha256'=>hash('sha256','room-key-evidence'),'reviewedBy'=>'fixture']
);
$pdo->commit();

$rooms=$catalog->rooms($hotelId);
room_key_need(count($rooms)===1,'one local room');
room_key_need($rooms[0]['localKey']==='own:standard-sea','hotel dictionary exposes own local key');
room_key_need($rooms[0]['localKey']!=='001','supplier key is not promoted to canonical key');
$resolved=$catalog->resolve(
    ['namespace'=>'tourvisor','hotelKey'=>'77','operatorKey'=>'5'],
    [['kind'=>'room','keyKind'=>'code','externalKey'=>'001']]
);
room_key_need($resolved['items'][0]['status']==='accepted','reviewed exact mapping resolves');
room_key_need($resolved['items'][0]['canonical']['localKey']==='own:standard-sea','accepted projection exposes same own local key');
room_key_need($resolved['items'][0]['reference']['externalKey']==='001','raw supplier reference stays exact and separate');

echo "ANYTOUR_STAY_ROOM_LOCAL_KEY_OK rooms=1 exact_mapping=1 supplier_key_preserved=1\n";
