<?php
declare(strict_types=1);
require_once __DIR__.'/../v2/data/search3-local-results-read-v1.php';
function need(bool $ok,string $label):void{if(!$ok)throw new RuntimeException('CHECK_FAILED:'.$label);}
function exec_sql(PDO $pdo,string $path):void{$sql=preg_replace('/^\s*--.*$/m','',(string)file_get_contents($path));foreach(preg_split('/;\s*(?:\r?\n|$)/',$sql)?:[] as $s){$s=trim($s);if($s!=='')$pdo->exec($s);}}
function params_fixture():array{return[
 'departureId'=>'1','countryId'=>'4','dateFrom'=>'2026-10-05','dateTo'=>'2026-10-07','nightsFrom'=>'7','nightsTo'=>'9','adults'=>'2','childs'=>[7],
 'meal'=>'','hotelCategory'=>'5','hotelRating'=>'','hotelTypes'=>[],'hotelIds'=>[],'hotelServices'=>[],'arrivalId'=>'',
 'regionIds'=>[],'subregionIds'=>[],'operatorIds'=>[],'priceFrom'=>'','priceTo'=>'','currency'=>'RUB','onlyCharter'=>'false','onlyDirect'=>'false'];}
function dto_fixture(string $provider,int $legacy,string $salt,string $price):array{
 $now=1791309600;$operator=match($provider){'anex'=>'ANEX','andromeda'=>'FUN&SUN',default=>'Pegas Touristik'};$verified=$provider==='anex';
 return['schema_version'=>1,'provider'=>$provider,'operator'=>['raw'=>$operator,'canonical_name'=>$verified?'ANEX':null,'canonical_verified'=>$verified,'identity_source'=>$verified?'provider_fixed':'raw_label_only','filter_status'=>$provider==='tourvisor'?'verified':'unsupported','cross_provider_equivalence_verified'=>false,'supplier_code_exposed'=>false],
 'local_hotel_id'=>$legacy,'identity'=>['search_ref_digest'=>hash('sha256','search:'.$provider.':'.$salt),'offer_ref_digest'=>hash('sha256','offer:'.$provider.':'.$salt),'provider_hotel_ref_digest'=>hash('sha256','hotel:'.$provider.':'.$salt)],
 'tour'=>['checkin'=>'2026-10-05','nights'=>7,'party'=>['adults'=>2,'children'=>1,'child_ages'=>[7]],'meal'=>['raw'=>'AI','family'=>'AI','qualifiers'=>['plus'=>false,'without_alcohol'=>false],'family_verified'=>true],'room'=>['raw'=>'STANDARD ROOM'],'placement'=>['raw'=>'2AD+1CHD'],'availability'=>['hotel'=>['raw'=>'available']],'flight_details'=>['state'=>'search_summary_only'],'observed_at'=>'2026-10-06T10:00:00Z'],
 'money'=>['search_price'=>['amount'=>$price,'currency'=>'RUB'],'search_price_with_surcharge'=>['amount'=>$price,'currency'=>'RUB']],
 'quote_state'=>'unknown','final_price_verified'=>false,'quote_evidence_digest'=>null,'context'=>['generation'=>1,'page'=>1,'issued_at'=>$now,'expires_at'=>$now+900,'current_context_verified'=>true],
 'selection_state'=>'disabled','booking_enabled'=>false,'finalPriceReady'=>true,'finalPrice'=>$price,'price'=>$price,'currency'=>'RUB'];}

$p=params_fixture();$scope=AnyTourSearchScopeV1::fromParams($p);
$variant=$p;$variant['hotelServices']=['9','2','9'];$variant['regionIds']=['7','3'];$variant2=$variant;$variant2['hotelServices']=['2','9'];$variant2['regionIds']=['3','7'];
need(AnyTourSearchScopeV1::fromParams($variant)['digest']===AnyTourSearchScopeV1::fromParams($variant2)['digest'],'list order/duplicates normalize');
$children=$p;$children['childs']=[7,5];$children2=$children;$children2['childs']=[5,7];need(AnyTourSearchScopeV1::fromParams($children)['digest']===AnyTourSearchScopeV1::fromParams($children2)['digest'],'child order normalize');
$changed=$p;$changed['dateTo']='2026-10-08';need(AnyTourSearchScopeV1::fromParams($changed)['digest']!==$scope['digest'],'date changes scope');
$bad=$p;$bad['providerId']='tourvisor';$failed=false;try{AnyTourSearchScopeV1::fromParams($bad);}catch(InvalidArgumentException){$failed=true;}need($failed,'extra provider field rejected');

$dsn=(string)getenv('ANYTOUR_LOCAL_RESULTS_TEST_DSN');$password=(string)getenv('ANYTOUR_LOCAL_RESULTS_TEST_PASSWORD');if(!str_starts_with($dsn,'mysql:'))throw new RuntimeException('fixture DSN required');
$pdo=new PDO($dsn,'root',$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$pdo->exec('SET FOREIGN_KEY_CHECKS=0');foreach(['anytour_offers','anytour_offer_scope_state','anytour_offer_refreshes','anytour_offer_store_control','anytour_hotel_sources','anytour_hotels','anytour_catalog_control'] as $t)$pdo->exec("DROP TABLE IF EXISTS `$t`");$pdo->exec('SET FOREIGN_KEY_CHECKS=1');
exec_sql($pdo,__DIR__.'/../v2/data/migrations/20260916-anytour-canonical-catalog.sql');exec_sql($pdo,__DIR__.'/../v2/data/migrations/20260916-anytour-offer-store.sql');exec_sql($pdo,__DIR__.'/../v2/data/migrations/20260917-anytour-offer-store-v2.sql');
$hotel=$pdo->prepare('INSERT INTO anytour_hotels(profile_json,profile_sha256,revision,is_active,created_at,updated_at) VALUES(?,?,1,1,UTC_TIMESTAMP(),UTC_TIMESTAMP())');
$bridge=$pdo->prepare("INSERT INTO anytour_hotel_sources(namespace,external_key,anytour_hotel_id,acquired_via,source_json,source_sha256,first_seen_at,last_seen_at) VALUES('legacy_catalog',?,?,'fixture',?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())");
$owns=[];foreach([[101,'Первый AnyTour отель'],[202,'Второй AnyTour отель']] as[$legacy,$name]){$profile=json_encode(['name'=>$name,'description'=>'Собственное описание','images'=>['https://images.example.test/'.$legacy.'.jpg']],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);$hotel->execute([$profile,hash('sha256',$profile)]);$own=(int)$pdo->lastInsertId();$owns[$legacy]=$own;$source=json_encode(['id'=>$legacy]);$bridge->execute([(string)$legacy,$own,$source,hash('sha256',$source)]);}
$at=new DateTimeImmutable('2026-10-06T10:00:00Z');$expires=$at->modify('+2 hours');
foreach([['tourvisor',101,'tv','120000'],['anex',101,'anex','125000'],['andromeda',202,'sam','119000']] as[$provider,$legacy,$salt,$price]){$token=AnyTourOfferStoreV1::beginRefresh($pdo,$provider,$scope['digest'],$at);AnyTourOfferStoreV1::upsertReadyOffer($pdo,$token,$owns[$legacy],dto_fixture($provider,$legacy,$salt,$price),$expires,$at);AnyTourOfferStoreV1::completeRefresh($pdo,$token,$at);}
$pdo->prepare('UPDATE anytour_hotels SET is_active=0 WHERE id=?')->execute([$owns[202]]);
$before=[(int)$pdo->query('SELECT COUNT(*) FROM anytour_offers')->fetchColumn(),(int)$pdo->query('SELECT COUNT(*) FROM anytour_hotels')->fetchColumn()];
$result=search3_local_results_build($pdo,$p,$at);$after=[(int)$pdo->query('SELECT COUNT(*) FROM anytour_offers')->fetchColumn(),(int)$pdo->query('SELECT COUNT(*) FROM anytour_hotels')->fetchColumn()];
need($before===$after,'reader writes nothing');need($result['offerStoreSchemaVersion']===2&&$result['scopeDigest']===$scope['digest']&&$result['scopeVersion']===1,'exact v2 scope echoed');need($result['storedOfferCount']===3&&$result['withheldOfferCount']===1,'inactive own profile withheld');
need($result['hotelCount']===1&&$result['offerCount']===2&&$result['selectionAuthority']===false,'one canonical card two offers');
need($result['providerOfferCounts']===['andromeda'=>0,'anex'=>1,'tourvisor'=>1],'provider count map includes zero-count known provider');
$group=$result['hotels'][0];need($group['anytourHotelId']===$owns[101]&&$group['hotel']['name']==='Первый AnyTour отель'&&$group['hotel']['catalog']==='anytour','first-party profile');
need($group['providers']===['anex','tourvisor'],'providers grouped under one hotel');need(count($group['offers'])===2&&$group['offers'][0]['price']==='120000','offers sorted by price');
foreach($group['offers'] as $offer){need(($offer['listing']['selection_state']??null)==='refresh_required'&&($offer['listing']['booking_enabled']??null)===false,'cached listing cannot select/book');need(!array_key_exists('context',$offer['listing']),'ephemeral provider context absent');}
$deleteBridge=$pdo->prepare("DELETE FROM anytour_hotel_sources WHERE namespace='legacy_catalog' AND external_key=? AND anytour_hotel_id=?");$deleteBridge->execute(['101',$owns[101]]);need($deleteBridge->rowCount()===1,'accepted bridge revoked');
$revoked=search3_local_results_build($pdo,$p,$at);need($revoked['storedOfferCount']===1&&$revoked['withheldOfferCount']===1,'revoked identity offers fail closed before canonical grouping');need($revoked['hotelCount']===0&&$revoked['offerCount']===0,'revoked identity cannot render cached canonical card');
need($revoked['providerOfferCounts']===['andromeda'=>0,'anex'=>0,'tourvisor'=>0],'revoked scope keeps stable zero provider map');
$source=json_encode(['id'=>101]);$bridge->execute(['101',$owns[101],$source,hash('sha256',$source)]);
$restored=search3_local_results_build($pdo,$p,$at);need($restored['storedOfferCount']===3&&$restored['hotelCount']===1&&$restored['offerCount']===2,'restored accepted bridge restores cached visibility');
need($restored['providerOfferCounts']===['andromeda'=>0,'anex'=>1,'tourvisor'=>1],'restored provider counts stable');
$empty=$p;$empty['dateFrom']='2026-11-01';$empty['dateTo']='2026-11-02';$none=search3_local_results_build($pdo,$empty,$at);need($none['hotelCount']===0&&$none['offerCount']===0,'unknown scope returns truthful empty set');
need($none['providerOfferCounts']===['andromeda'=>0,'anex'=>0,'tourvisor'=>0],'empty scope has explicit zero provider map');
need(json_encode($none['providerOfferCounts'],JSON_THROW_ON_ERROR)==='{"andromeda":0,"anex":0,"tourvisor":0}','empty provider counts encode as JSON object, never array');
$pdo->exec("UPDATE anytour_offers SET payload_json='{}' WHERE provider='tourvisor'");$integrityFailed=false;try{search3_local_results_build($pdo,$p,$at);}catch(RuntimeException $e){$integrityFailed=str_contains($e->getMessage(),'PAYLOAD_INTEGRITY');}need($integrityFailed,'corrupt stored payload fails closed');
echo "SEARCH3_LOCAL_DB_RESULTS_OK scope_v1=1 store_v2=1 stored=3 rendered=2 hotels=1 withheld=1 provider_map=stable revoked_identity_hidden=2 writes=0\n";
