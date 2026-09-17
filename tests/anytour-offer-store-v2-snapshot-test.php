<?php
declare(strict_types=1);
require_once __DIR__.'/../v2/data/search3-local-results-read-v1.php';
require_once __DIR__.'/../v2/data/anytour-offer-store-v1.php';

function need_v2(bool $ok,string $label):void{if(!$ok)throw new RuntimeException('CHECK_FAILED:'.$label);}
function error_v2(callable $call,string $expected,string $label):void{
 try{$call();}catch(RuntimeException $e){need_v2($e->getMessage()===$expected,$label.'-error');return;}
 throw new RuntimeException('CHECK_FAILED:'.$label.'-did-not-fail');
}
function sql_file_v2(PDO $pdo,string $path):void{$sql=preg_replace('/^\s*--.*$/m','',(string)file_get_contents($path));foreach(preg_split('/;\s*(?:\r?\n|$)/',$sql)?:[] as $statement){$statement=trim($statement);if($statement!=='')$pdo->exec($statement);}}
function params_v2():array{return[
 'departureId'=>'1','countryId'=>'4','dateFrom'=>'2026-10-05','dateTo'=>'2026-10-05','nightsFrom'=>'7','nightsTo'=>'7','adults'=>'2','childs'=>[],
 'meal'=>'','hotelCategory'=>'','hotelRating'=>'','hotelTypes'=>[],'hotelIds'=>[],'hotelServices'=>[],'arrivalId'=>'','regionIds'=>[],'subregionIds'=>[],'operatorIds'=>[],
 'priceFrom'=>'','priceTo'=>'','currency'=>'RUB','onlyCharter'=>'false','onlyDirect'=>'false'];}
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
$profile=json_encode(['name'=>'Fixture AnyTour Hotel','description'=>'Own profile'],JSON_UNESCAPED_SLASHES);$pdo->prepare('INSERT INTO anytour_hotels(profile_json,profile_sha256,revision,is_active,created_at,updated_at) VALUES(?,?,1,1,UTC_TIMESTAMP(),UTC_TIMESTAMP())')->execute([$profile,hash('sha256',$profile)]);$own=(int)$pdo->lastInsertId();
$source=json_encode(['id'=>101]);$pdo->prepare("INSERT INTO anytour_hotel_sources(namespace,external_key,anytour_hotel_id,acquired_via,source_json,source_sha256,first_seen_at,last_seen_at) VALUES('legacy_catalog','101',?,'fixture',?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())")->execute([$own,$source,hash('sha256',$source)]);
$at=new DateTimeImmutable('2026-09-16T18:00:00Z');$expires=$at->modify('+2 hours');$params=params_v2();$scope=AnyTourSearchScopeV1::fromParams($params)['digest'];
$first=AnyTourOfferStoreV1::beginRefresh($pdo,'anex',$scope,$at);AnyTourOfferStoreV1::upsertReadyOffer($pdo,$first,$own,dto_v2('199390'),$expires,$at);AnyTourOfferStoreV1::completeRefresh($pdo,$first,$at);
$read=AnyTourOfferStoreReadV2::readScope($pdo,$scope,$at->modify('+1 minute'));need_v2(count($read['items'])===1&&$read['items'][0]['price']==='199390','first-complete-visible');
$page=search3_local_results_build($pdo,$params,$at->modify('+1 minute'));need_v2($page['offerStoreSchemaVersion']===2&&$page['hotelCount']===1&&$page['offerCount']===1&&$page['hotels'][0]['offers'][0]['price']==='199390','db-first-v2-first-complete');
$second=AnyTourOfferStoreV1::beginRefresh($pdo,'anex',$scope,$at->modify('+2 minutes'));AnyTourOfferStoreV1::upsertReadyOffer($pdo,$second,$own,dto_v2('190000'),$expires,$at->modify('+2 minutes'));
need_v2((int)$pdo->query("SELECT COUNT(*) FROM anytour_offers WHERE provider='anex'")->fetchColumn()===2,'running-refresh-keeps-old-row');
$read=AnyTourOfferStoreReadV2::readScope($pdo,$scope,$at->modify('+3 minutes'));need_v2(count($read['items'])===1&&$read['items'][0]['price']==='199390','running-refresh-hidden-old-complete-visible');
$page=search3_local_results_build($pdo,$params,$at->modify('+3 minutes'));need_v2($page['offerCount']===1&&$page['hotels'][0]['offers'][0]['price']==='199390','db-first-hides-running-refresh');
AnyTourOfferStoreV1::abortRefresh($pdo,$second,$at->modify('+3 minutes'));$read=AnyTourOfferStoreReadV2::readScope($pdo,$scope,$at->modify('+4 minutes'));need_v2(count($read['items'])===1&&$read['items'][0]['price']==='199390','aborted-refresh-hidden');
$page=search3_local_results_build($pdo,$params,$at->modify('+4 minutes'));need_v2($page['offerCount']===1&&$page['hotels'][0]['offers'][0]['price']==='199390','db-first-hides-aborted-refresh');
$third=AnyTourOfferStoreV1::beginRefresh($pdo,'anex',$scope,$at->modify('+5 minutes'));AnyTourOfferStoreV1::upsertReadyOffer($pdo,$third,$own,dto_v2('188000'),$expires,$at->modify('+5 minutes'));AnyTourOfferStoreV1::completeRefresh($pdo,$third,$at->modify('+5 minutes'));
$read=AnyTourOfferStoreReadV2::readScope($pdo,$scope,$at->modify('+6 minutes'));need_v2(count($read['items'])===1&&$read['items'][0]['price']==='188000','new-complete-atomically-visible');
$page=search3_local_results_build($pdo,$params,$at->modify('+6 minutes'));need_v2($page['offerCount']===1&&$page['hotels'][0]['offers'][0]['price']==='188000','db-first-switches-on-complete');
need_v2((int)$pdo->query("SELECT COUNT(*) FROM anytour_offers WHERE provider='anex'")->fetchColumn()===3,'refresh-history-retained');need_v2((int)$pdo->query("SELECT COUNT(*) FROM anytour_offers WHERE provider='anex' AND is_active=1")->fetchColumn()===1,'old-and-aborted-versions-deactivated-on-complete');
$state=$pdo->query("SELECT active_refresh_token,latest_complete_refresh_token FROM anytour_offer_scope_state WHERE provider='anex'")->fetch(PDO::FETCH_ASSOC);need_v2($state['active_refresh_token']===null&&$state['latest_complete_refresh_token']===$third,'latest-complete-switch');

$current=$pdo->prepare("SELECT id,payload_json,payload_sha256,display_price,currency,last_seen_at,expires_at FROM anytour_offers WHERE provider='anex' AND scope_sha256=? AND last_refresh_token=? AND is_active=1");$current->execute([$scope,$third]);$stored=$current->fetch(PDO::FETCH_ASSOC);need_v2(is_array($stored),'current-row');$id=(int)$stored['id'];
$pdo->prepare('UPDATE anytour_offers SET currency=? WHERE id=?')->execute(['USD',$id]);
error_v2(fn()=>AnyTourOfferStoreReadV2::readScope($pdo,$scope,$at->modify('+6 minutes')),'ANYTOUR_OFFER_LISTING_INTEGRITY','db-currency-fail-closed');
$pdo->prepare('UPDATE anytour_offers SET currency=? WHERE id=?')->execute([$stored['currency'],$id]);
$pdo->prepare('UPDATE anytour_offers SET display_price=? WHERE id=?')->execute(['0.00',$id]);
error_v2(fn()=>AnyTourOfferStoreReadV2::readScope($pdo,$scope,$at->modify('+6 minutes')),'ANYTOUR_OFFER_PRICE_INTEGRITY','non-positive-price-fail-closed');
$pdo->prepare('UPDATE anytour_offers SET display_price=? WHERE id=?')->execute([$stored['display_price'],$id]);

$pdo->prepare('UPDATE anytour_offers SET expires_at=last_seen_at WHERE id=?')->execute([$id]);
error_v2(fn()=>AnyTourOfferStoreReadV2::readScope($pdo,$scope,$at->modify('+4 minutes')),'ANYTOUR_OFFER_TIME_INTEGRITY','non-positive-visibility-window-fail-closed');
$pdo->prepare('UPDATE anytour_offers SET expires_at=? WHERE id=?')->execute([$stored['expires_at'],$id]);
$lastSeen=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',(string)$stored['last_seen_at'],new DateTimeZone('UTC'));need_v2($lastSeen!==false,'stored-last-seen-parse');
$tooLong=$lastSeen->modify('+21601 seconds')->format('Y-m-d H:i:s');$pdo->prepare('UPDATE anytour_offers SET expires_at=? WHERE id=?')->execute([$tooLong,$id]);
error_v2(fn()=>AnyTourOfferStoreReadV2::readScope($pdo,$scope,$at->modify('+6 minutes')),'ANYTOUR_OFFER_TIME_INTEGRITY','overlong-visibility-window-fail-closed');
$pdo->prepare('UPDATE anytour_offers SET expires_at=? WHERE id=?')->execute([$stored['expires_at'],$id]);

$payload=json_decode((string)$stored['payload_json'],true,512,JSON_THROW_ON_ERROR);$payload['booking_enabled']=true;$mutated=json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
$pdo->prepare('UPDATE anytour_offers SET payload_json=?,payload_sha256=? WHERE id=?')->execute([$mutated,hash('sha256',$mutated),$id]);
error_v2(fn()=>AnyTourOfferStoreReadV2::readScope($pdo,$scope,$at->modify('+6 minutes')),'ANYTOUR_OFFER_LISTING_INTEGRITY','selection-authority-fail-closed');
$payload['booking_enabled']=false;$payload['listingPrice']='999';$mutated=json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
$pdo->prepare('UPDATE anytour_offers SET payload_json=?,payload_sha256=? WHERE id=?')->execute([$mutated,hash('sha256',$mutated),$id]);
error_v2(fn()=>AnyTourOfferStoreReadV2::readScope($pdo,$scope,$at->modify('+6 minutes')),'ANYTOUR_OFFER_LISTING_INTEGRITY','price-mismatch-fail-closed');
$pdo->prepare('UPDATE anytour_offers SET payload_json=?,payload_sha256=? WHERE id=?')->execute([$stored['payload_json'],$stored['payload_sha256'],$id]);
$read=AnyTourOfferStoreReadV2::readScope($pdo,$scope,$at->modify('+6 minutes'));need_v2(count($read['items'])===1&&$read['items'][0]['price']==='188000','integrity-restored');

echo "ANYTOUR_OFFER_STORE_V2_SNAPSHOT_OK versions=3 visible=1 aborted_hidden=1 db_first=1 listing_integrity=1 positive_price=1 time_integrity=1\n";
