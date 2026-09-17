<?php
declare(strict_types=1);
require_once __DIR__.'/../v2/data/anytour-offer-store-v1.php';
require_once __DIR__.'/../v2/data/anytour-offer-store-read-v2.php';

function need_v2(bool $ok,string $label):void{if(!$ok)throw new RuntimeException('CHECK_FAILED:'.$label);}
function sql_file_v2(PDO $pdo,string $path):void{$sql=preg_replace('/^\s*--.*$/m','',(string)file_get_contents($path));foreach(preg_split('/;\s*(?:\r?\n|$)/',$sql)?:[] as $statement){$statement=trim($statement);if($statement!=='')$pdo->exec($statement);}}
function dto_v2(string $price):array{
 $issued=1789581600;
 return['schema_version'=>1,'provider'=>'anex','operator'=>['raw'=>'ANEX','canonical_name'=>'ANEX','canonical_verified'=>true,'identity_source'=>'provider_fixed','filter_status'=>'unsupported','cross_provider_equivalence_verified'=>false,'supplier_code_exposed'=>false],
 'local_hotel_id'=>101,'identity'=>['search_ref_digest'=>hash('sha256','search:anex:stable'),'offer_ref_digest'=>hash('sha256','offer:anex:stable'),'provider_hotel_ref_digest'=>hash('sha256','hotel:anex:stable')],
 'tour'=>['checkin'=>'2026-10-05','nights'=>7,'party'=>['adults'=>2,'children'=>0,'child_ages'=>[]],'meal'=>['raw'=>'AI'],'room'=>['raw'=>'STANDARD ROOM'],'placement'=>['raw'=>'DBL'],'availability'=>['hotel'=>['raw'=>'unknown']],'flight_details'=>['state'=>'search_summary_only'],'observed_at'=>'2026-09-16T18:00:00Z'],
 'money'=>['search_price'=>['amount'=>'185125','currency'=>'RUB'],'search_price_with_surcharge'=>['amount'=>$price,'currency'=>'RUB']],
 'quote_state'=>'unknown','final_price_verified'=>false,'quote_evidence_digest'=>null,'context'=>['generation'=>1,'page'=>1,'issued_at'=>$issued,'expires_at'=>$issued+900,'current_context_verified'=>true],
 'selection_state'=>'disabled','booking_enabled'=>false,'finalPriceReady'=>true,'finalPrice'=>$price,'price'=>$price,'currency'=>'RUB'];
}
$dsn=(string)getenv('ANYTOUR_OFFER_V2_TEST_DSN');$password=(string)getenv('ANYTOUR_OFFER_V2_TEST_PASSWORD');if(!str_starts_with($dsn,'mysql:'))throw new RuntimeException('fixture DSN required');
$pdo=new PDO($dsn,'root',$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
$pdo->exec('SET FOREIGN_KEY_CHECKS=0');foreach(['anytour_offers','anytour_offer_scope_state','anytour_offer_refreshes','anytour_offer_store_control','anytour_hotel_sources','anytour_hotels','anytour_catalog_control'] as $table)$pdo->exec("DROP TABLE IF EXISTS `$table`");$pdo->exec('SET FOREIGN_KEY_CHECKS=1');
sql_file_v2($pdo,__DIR__.'/../v2/data/migrations/20260916-anytour-canonical-catalog.sql');sql_file_v2($pdo,__DIR__.'/../v2/data/migrations/20260916-anytour-offer-store.sql');sql_file_v2($pdo,__DIR__.'/../v2/data/migrations/20260917-anytour-offer-store-v2.sql');
need_v2((int)$pdo->query('SELECT schema_version FROM anytour_offer_store_control WHERE singleton_id=1')->fetchColumn()===2,'schema-v2');
$profile=json_encode(['name'=>'Fixture'],JSON_UNESCAPED_SLASHES);$pdo->prepare('INSERT INTO anytour_hotels(profile_json,profile_sha256,revision,is_active,created_at,updated_at) VALUES(?,?,1,1,UTC_TIMESTAMP(),UTC_TIMESTAMP())')->execute([$profile,hash('sha256',$profile)]);$own=(int)$pdo->lastInsertId();
$source=json_encode(['id'=>101]);$pdo->prepare("INSERT INTO anytour_hotel_sources(namespace,external_key,anytour_hotel_id,acquired_via,source_json,source_sha256,first_seen_at,last_seen_at) VALUES('legacy_catalog','101',?,'fixture',?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())")->execute([$own,$source,hash('sha256',$source)]);
$at=new DateTimeImmutable('2026-09-16T18:00:00Z');$expires=$at->modify('+2 hours');$scope=hash('sha256','scope-v2');
$first=AnyTourOfferStoreV1::beginRefresh($pdo,'anex',$scope,$at);AnyTourOfferStoreV1::upsertReadyOffer($pdo,$first,$own,dto_v2('199390'),$expires,$at);AnyTourOfferStoreV1::completeRefresh($pdo,$first,$at);
$read=AnyTourOfferStoreReadV2::readScope($pdo,$scope,$at->modify('+1 minute'));need_v2(count($read['items'])===1&&$read['items'][0]['price']==='199390','first-complete-visible');
$second=AnyTourOfferStoreV1::beginRefresh($pdo,'anex',$scope,$at->modify('+2 minutes'));AnyTourOfferStoreV1::upsertReadyOffer($pdo,$second,$own,dto_v2('190000'),$expires,$at->modify('+2 minutes'));
need_v2((int)$pdo->query("SELECT COUNT(*) FROM anytour_offers WHERE provider='anex'")->fetchColumn()===2,'running-refresh-keeps-old-row');
$read=AnyTourOfferStoreReadV2::readScope($pdo,$scope,$at->modify('+3 minutes'));need_v2(count($read['items'])===1&&$read['items'][0]['price']==='199390','running-refresh-hidden-old-complete-visible');
AnyTourOfferStoreV1::abortRefresh($pdo,$second,$at->modify('+3 minutes'));$read=AnyTourOfferStoreReadV2::readScope($pdo,$scope,$at->modify('+4 minutes'));need_v2(count($read['items'])===1&&$read['items'][0]['price']==='199390','aborted-refresh-hidden');
$third=AnyTourOfferStoreV1::beginRefresh($pdo,'anex',$scope,$at->modify('+5 minutes'));AnyTourOfferStoreV1::upsertReadyOffer($pdo,$third,$own,dto_v2('188000'),$expires,$at->modify('+5 minutes'));AnyTourOfferStoreV1::completeRefresh($pdo,$third,$at->modify('+5 minutes'));
$read=AnyTourOfferStoreReadV2::readScope($pdo,$scope,$at->modify('+6 minutes'));need_v2(count($read['items'])===1&&$read['items'][0]['price']==='188000','new-complete-atomically-visible');
need_v2((int)$pdo->query("SELECT COUNT(*) FROM anytour_offers WHERE provider='anex'")->fetchColumn()===3,'refresh-history-retained');need_v2((int)$pdo->query("SELECT COUNT(*) FROM anytour_offers WHERE provider='anex' AND is_active=1")->fetchColumn()===1,'old-and-aborted-versions-deactivated-on-complete');
$state=$pdo->query("SELECT active_refresh_token,latest_complete_refresh_token FROM anytour_offer_scope_state WHERE provider='anex'")->fetch(PDO::FETCH_ASSOC);need_v2($state['active_refresh_token']===null&&$state['latest_complete_refresh_token']===$third,'latest-complete-switch');
echo "ANYTOUR_OFFER_STORE_V2_SNAPSHOT_OK versions=3 visible=1 aborted_hidden=1\n";
