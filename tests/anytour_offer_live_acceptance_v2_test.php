<?php
declare(strict_types=1);
require_once __DIR__.'/../scripts/catalog/anytour_offer_live_acceptance_v2.php';

function accept_need(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);}
function accept_apply_sql(PDO $pdo,string $file):void{
    $sql=preg_replace('/^\s*--.*$/m','',(string)file_get_contents($file));
    foreach(preg_split('/;\s*(?:\r?\n|$)/',$sql) as $statement){$statement=trim($statement);if($statement!=='')$pdo->exec($statement);}
}
function accept_stamp(string $change):string{return(new DateTimeImmutable('now',new DateTimeZone('UTC')))->modify($change)->format('Y-m-d H:i:s');}

$dsn=(string)getenv('ANYTOUR_OFFER_ACCEPT_TEST_DSN');$password=(string)getenv('ANYTOUR_OFFER_ACCEPT_TEST_PASSWORD');
if(!str_starts_with($dsn,'mysql:'))throw new RuntimeException('fixture DSN required');
$pdo=new PDO($dsn,'root',$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$tables=array_merge(ANYTOUR_OFFER_ACCEPT_TABLES,['anytour_hotel_sources','anytour_hotels','anytour_catalog_control']);
$pdo->exec('SET FOREIGN_KEY_CHECKS=0');foreach($tables as $table)$pdo->exec("DROP TABLE IF EXISTS `$table`");$pdo->exec('SET FOREIGN_KEY_CHECKS=1');
accept_apply_sql($pdo,__DIR__.'/../v2/data/migrations/20260916-anytour-canonical-catalog.sql');
accept_apply_sql($pdo,__DIR__.'/../v2/data/migrations/20260916-anytour-offer-store.sql');
accept_apply_sql($pdo,__DIR__.'/../v2/data/migrations/20260917-anytour-offer-store-v2.sql');
accept_need((int)$pdo->query('SELECT schema_version FROM anytour_offer_store_control WHERE singleton_id=1')->fetchColumn()===2,'schema v2');

$hotel=$pdo->prepare('INSERT INTO anytour_hotels(profile_json,profile_sha256,revision,is_active,created_at,updated_at) VALUES(?,?,?,?,?,?)');
for($i=1;$i<=4;$i++){$json=json_encode(['name'=>'Fixture '.$i]);$at=accept_stamp('-1 day');$hotel->execute([$json,hash('sha256',$json),1,1,$at,$at]);}
$source=$pdo->prepare("INSERT INTO anytour_hotel_sources(namespace,external_key,anytour_hotel_id,acquired_via,source_json,source_sha256,first_seen_at,last_seen_at) VALUES('legacy_catalog',?,?,?,?,?,?,?)");
foreach([[102,1],[106,2],[108,3]] as[$legacy,$own]){$json=json_encode(['id'=>$legacy]);$at=accept_stamp('-1 day');$source->execute([(string)$legacy,$own,'fixture',$json,hash('sha256',$json),$at,$at]);}

$scopeTv=str_repeat('a',64);$scopeAnex=str_repeat('b',64);$scopeAnd=str_repeat('c',64);
$tvOld=str_repeat('1',64);$tvNew=str_repeat('2',64);$anexToken=str_repeat('3',64);$andToken=str_repeat('4',64);
$refresh=$pdo->prepare('INSERT INTO anytour_offer_refreshes(refresh_token,provider,scope_sha256,status,started_at,lease_expires_at,completed_at) VALUES(?,?,?,?,?,?,?)');
$refresh->execute([$tvOld,'tourvisor',$scopeTv,'completed',accept_stamp('-30 minutes'),accept_stamp('-20 minutes'),accept_stamp('-25 minutes')]);
$refresh->execute([$tvNew,'tourvisor',$scopeTv,'running',accept_stamp('-5 minutes'),accept_stamp('+10 minutes'),null]);
$refresh->execute([$anexToken,'anex',$scopeAnex,'completed',accept_stamp('-20 minutes'),accept_stamp('-10 minutes'),accept_stamp('-15 minutes')]);
$refresh->execute([$andToken,'andromeda',$scopeAnd,'completed',accept_stamp('-18 minutes'),accept_stamp('-8 minutes'),accept_stamp('-12 minutes')]);
$state=$pdo->prepare('INSERT INTO anytour_offer_scope_state(provider,scope_sha256,active_refresh_token,latest_complete_refresh_token,revision,updated_at) VALUES(?,?,?,?,?,?)');
$state->execute(['tourvisor',$scopeTv,$tvNew,$tvOld,3,accept_stamp('-5 minutes')]);
$state->execute(['anex',$scopeAnex,null,$anexToken,2,accept_stamp('-15 minutes')]);
$state->execute(['andromeda',$scopeAnd,null,$andToken,2,accept_stamp('-12 minutes')]);

$sql='INSERT INTO anytour_offers(anytour_hotel_id,legacy_hotel_id,provider,scope_sha256,search_ref_digest,offer_ref_digest,provider_hotel_ref_digest,identity_sha256,operator_json,operator_sha256,checkin,nights,adults,children,child_ages_json,party_sha256,meal_json,room_json,placement_json,display_price,currency,final_price_ready,final_price_verified,payload_json,payload_sha256,observed_at,source_context_expires_at,last_refresh_token,last_seen_at,expires_at,is_active) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
$offer=$pdo->prepare($sql);
function accept_add_offer(PDOStatement $s,int $own,int $legacy,string $provider,string $scope,string $token,int $price,int $seed,string $identity):void{
    $operator=json_encode(['raw'=>strtoupper($provider)],JSON_UNESCAPED_SLASHES);$payload=json_encode(['schema_version'=>1,'selection_state'=>'refresh_required','booking_enabled'=>false,'seed'=>$seed],JSON_UNESCAPED_SLASHES);$party=json_encode(['adults'=>2,'children'=>0,'child_ages'=>[]],JSON_UNESCAPED_SLASHES);
    $hex=static fn(int $n)=>str_repeat(dechex(($n%15)+1),64);
    $s->execute([$own,$legacy,$provider,$scope,$hex($seed),$hex($seed+1),$hex($seed+2),$identity,$operator,hash('sha256',$operator),accept_stamp('+14 days'),7,2,0,'[]',hash('sha256',$party),json_encode(['name'=>'AI']),json_encode(['name'=>'STD']),json_encode(['name'=>'DBL']),$price,'RUB',1,0,$payload,hash('sha256',$payload),accept_stamp('-10 minutes'),accept_stamp('+10 minutes'),$token,accept_stamp('-5 minutes'),accept_stamp('+2 hours'),1]);
}
$tvIdentity=str_repeat('d',64);
accept_add_offer($offer,1,102,'tourvisor',$scopeTv,$tvOld,120000,1,$tvIdentity);
accept_add_offer($offer,1,102,'tourvisor',$scopeTv,$tvNew,110000,2,$tvIdentity);
accept_add_offer($offer,2,106,'anex',$scopeAnex,$anexToken,130000,3,str_repeat('e',64));
accept_add_offer($offer,4,999,'andromeda',$scopeAnd,$andToken,140000,4,str_repeat('f',64));

$before=(int)$pdo->query('SELECT COUNT(*) FROM anytour_offers')->fetchColumn();
$r=anytour_offer_accept_collect($pdo);
$after=(int)$pdo->query('SELECT COUNT(*) FROM anytour_offers')->fetchColumn();
accept_need($before===4&&$after===4,'collector mutated offers');
accept_need($r['offerStoreSchemaVersion']===2&&$r['catalogSchemaVersion']===1,'schema versions');
accept_need($r['canonical']['activeHotels']===4&&$r['canonical']['legacyCatalogLinks']===3,'canonical census');
accept_need($r['overall']['storedRows']===4,'stored rows');
accept_need($r['overall']['visibleRows']===2&&$r['overall']['visibleHotels']===2&&$r['overall']['visibleScopes']===2,'visible completed rows');
accept_need($r['overall']['completedScopes']===3&&$r['overall']['activeRefreshes']===1,'scope state');
accept_need($r['storedByProvider']['tourvisor']['rows']===2&&$r['visibleByProvider']['tourvisor']['rows']===1,'running refresh isolation');
accept_need($r['visibleByProvider']['anex']['rows']===1&&$r['visibleByProvider']['andromeda']['rows']===0,'provider visibility');
accept_need($r['integrity']['withheldByCurrentBridgeRows']===1,'bridge withholding');
accept_need($r['integrity']['orphanLatestCompleteScopes']===0,'completed refresh provenance');
accept_need($r['integrity']['unknownProviderRows']===0&&$r['integrity']['invalidListingRows']===0&&$r['integrity']['payloadDigestMismatchRows']===0&&$r['integrity']['operatorDigestMismatchRows']===0,'integrity');
accept_need($r['databaseWrites']===0&&$r['supplierCalls']===0&&$r['selectionAuthority']===false,'boundary');
echo "ANYTOUR_OFFER_LIVE_ACCEPTANCE_V2_TEST_OK stored=4 visible=2 running_hidden=1 bridge_withheld=1 writes=0 supplier_calls=0\n";
