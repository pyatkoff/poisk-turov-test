<?php
declare(strict_types=1);
require_once __DIR__.'/../scripts/catalog/anytour_offer_store_current.php';

function need(bool $ok, string $label): void { if (!$ok) throw new RuntimeException($label); }
function apply_sql(PDO $pdo, string $file): void {
    $sql = file_get_contents($file);
    foreach (preg_split('/;\s*(?:\r?\n|$)/', $sql) as $statement) {
        $statement = trim($statement);
        if ($statement === '' || preg_match('/^--(?:.|\R)*$/D', $statement)) continue;
        $pdo->exec($statement);
    }
}
function stamp(string $change): string {
    return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->modify($change)->format('Y-m-d H:i:s');
}

$dsn = (string)getenv('ANYTOUR_OFFER_CURRENT_TEST_DSN');
$password = (string)getenv('ANYTOUR_OFFER_CURRENT_TEST_PASSWORD');
if (!str_starts_with($dsn, 'mysql:')) throw new RuntimeException('fixture DSN required');
$pdo = new PDO($dsn, 'root', $password, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES=>false, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$tables = array_merge(ANYTOUR_OFFER_CURRENT_TABLES, ['anytour_hotel_sources','anytour_hotels','anytour_catalog_control']);
$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
foreach ($tables as $table) $pdo->exec("DROP TABLE IF EXISTS `$table`");
$pdo->exec('SET FOREIGN_KEY_CHECKS=1');
apply_sql($pdo, __DIR__.'/../v2/data/migrations/20260916-anytour-canonical-catalog.sql');

$absent = anytour_offer_current_collect($pdo);
need($absent['schemaState'] === 'absent', 'absent schema must be explicit');
need($absent['canonical']['activeHotels'] === 0 && $absent['databaseWrites'] === 0, 'absent audit remains read only');

$pdo->exec('CREATE TABLE anytour_offer_store_control (singleton_id TINYINT UNSIGNED NOT NULL PRIMARY KEY,schema_version INT UNSIGNED NOT NULL) ENGINE=InnoDB');
$pdo->exec('INSERT INTO anytour_offer_store_control VALUES (1,1)');
$partial = anytour_offer_current_collect($pdo);
need($partial['schemaState'] === 'partial', 'partial schema must be explicit');
need($partial['overall'] === null && $partial['providers'] === [], 'partial schema must not pretend to have offers');
$pdo->exec('DROP TABLE anytour_offer_store_control');
apply_sql($pdo, __DIR__.'/../v2/data/migrations/20260916-anytour-offer-store.sql');

$insertHotel = $pdo->prepare('INSERT INTO anytour_hotels(profile_json,profile_sha256,revision,is_active,created_at,updated_at) VALUES(?,?,?,?,?,?)');
for ($i=1;$i<=3;$i++) {
    $json=json_encode(['name'=>'Fixture '.$i],JSON_UNESCAPED_SLASHES); $at=stamp('-1 day');
    $insertHotel->execute([$json,hash('sha256',$json),1,1,$at,$at]);
}
$source = $pdo->prepare("INSERT INTO anytour_hotel_sources(namespace,external_key,anytour_hotel_id,acquired_via,source_json,source_sha256,first_seen_at,last_seen_at) VALUES('legacy_catalog',?,?,?,?,?,?,?)");
foreach ([[102,1],[106,2],[108,3]] as [$legacy,$hotel]) {
    $json=json_encode(['id'=>$legacy],JSON_UNESCAPED_SLASHES); $at=stamp('-1 day');
    $source->execute([(string)$legacy,$hotel,'fixture',$json,hash('sha256',$json),$at,$at]);
}

$refresh = $pdo->prepare('INSERT INTO anytour_offer_refreshes(refresh_token,provider,scope_sha256,status,started_at,lease_expires_at,completed_at) VALUES(?,?,?,?,?,?,?)');
$scope = str_repeat('a',64); $scope2=str_repeat('b',64); $scope3=str_repeat('c',64);
$tokens=['tourvisor'=>str_repeat('1',64),'anex'=>str_repeat('2',64),'andromeda'=>str_repeat('3',64)];
$refresh->execute([$tokens['tourvisor'],'tourvisor',$scope,'completed',stamp('-30 minutes'),stamp('-20 minutes'),stamp('-25 minutes')]);
$refresh->execute([$tokens['anex'],'anex',$scope2,'completed',stamp('-20 minutes'),stamp('-10 minutes'),stamp('-15 minutes')]);
$refresh->execute([$tokens['andromeda'],'andromeda',$scope3,'running',stamp('-5 minutes'),stamp('+10 minutes'),null]);
$state = $pdo->prepare('INSERT INTO anytour_offer_scope_state(provider,scope_sha256,active_refresh_token,latest_complete_refresh_token,revision,updated_at) VALUES(?,?,?,?,?,?)');
$state->execute(['tourvisor',$scope,null,$tokens['tourvisor'],2,stamp('-25 minutes')]);
$state->execute(['anex',$scope2,null,$tokens['anex'],2,stamp('-15 minutes')]);
$state->execute(['andromeda',$scope3,$tokens['andromeda'],null,2,stamp('-5 minutes')]);

$offerSql='INSERT INTO anytour_offers(anytour_hotel_id,legacy_hotel_id,provider,scope_sha256,search_ref_digest,offer_ref_digest,provider_hotel_ref_digest,identity_sha256,operator_json,operator_sha256,checkin,nights,adults,children,child_ages_json,party_sha256,meal_json,room_json,placement_json,display_price,currency,final_price_ready,final_price_verified,payload_json,payload_sha256,observed_at,source_context_expires_at,last_refresh_token,last_seen_at,expires_at,is_active) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
$offer=$pdo->prepare($offerSql);
function add_offer(PDOStatement $offer,int $own,int $legacy,string $provider,string $scope,string $token,bool $active,bool $expired,int $price,int $seed): void {
    $operator=json_encode(['raw'=>strtoupper($provider)],JSON_UNESCAPED_SLASHES); $payload=json_encode(['schema_version'=>1,'seed'=>$seed],JSON_UNESCAPED_SLASHES);
    $meal=json_encode(['name'=>'AI']); $room=json_encode(['name'=>'STD']); $placement=json_encode(['name'=>'DBL']); $ages='[]'; $party=json_encode(['adults'=>2,'children'=>0,'child_ages'=>[]]);
    $offer->execute([$own,$legacy,$provider,$scope,str_repeat(dechex(($seed%15)+1),64),str_repeat(dechex((($seed+1)%15)+1),64),str_repeat(dechex((($seed+2)%15)+1),64),str_repeat(dechex((($seed+3)%15)+1),64),
        $operator,hash('sha256',$operator),date('Y-m-d',strtotime('+14 days')),7,2,0,$ages,hash('sha256',$party),$meal,$room,$placement,$price,'RUB',1,0,$payload,hash('sha256',$payload),stamp('-10 minutes'),stamp('+10 minutes'),$token,stamp('-5 minutes'),stamp($expired?'-1 minute':'+2 hours'),$active?1:0]);
}
add_offer($offer,1,102,'tourvisor',$scope,$tokens['tourvisor'],true,false,120000,1);
add_offer($offer,1,102,'tourvisor',$scope,$tokens['tourvisor'],true,true,110000,2);
add_offer($offer,2,106,'anex',$scope2,$tokens['anex'],true,false,130000,3);
add_offer($offer,3,108,'andromeda',$scope3,$tokens['andromeda'],false,false,140000,4);

$before=(int)$pdo->query('SELECT COUNT(*) FROM anytour_offers')->fetchColumn();
$result=anytour_offer_current_collect($pdo);
$after=(int)$pdo->query('SELECT COUNT(*) FROM anytour_offers')->fetchColumn();
need($before===4 && $after===4, 'collector must not mutate offers');
need($result['schemaState']==='complete' && $result['offerStoreVersion']===1, 'complete schema/version');
need($result['canonical']['activeHotels']===3 && $result['canonical']['legacyCatalogLinks']===3, 'canonical coverage baseline');
need($result['overall']['totalRows']===4 && $result['overall']['currentRows']===2 && $result['overall']['currentHotels']===2, 'overall current rows/hotels');
need($result['providers']['tourvisor']['totalRows']===2 && $result['providers']['tourvisor']['currentRows']===1, 'Tourvisor current count');
need($result['providers']['anex']['currentRows']===1 && $result['providers']['andromeda']['currentRows']===0, 'provider current counts');
need($result['scopeState']['andromeda']['activeRefreshes']===1, 'active refresh state visible');
need(count($result['refreshes'])===3, 'refresh status summary');
need($result['integrity']===['unknownProviderRows'=>0,'invalidListingReadinessRows'=>0,'payloadDigestMismatchRows'=>0,'operatorDigestMismatchRows'=>0], 'integrity summary');
need(abs($result['overall']['canonicalHotelCoveragePct']-66.67)<0.01, 'coverage percentage');
need($result['databaseWrites']===0 && $result['supplierCalls']===0 && $result['migrationAuthorized']===false, 'audit authorization boundary');

echo "ANYTOUR_OFFER_CURRENT_TEST_OK states=3 offers=4 current=2 writes=0\n";
