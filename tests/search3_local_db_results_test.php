<?php
declare(strict_types=1);
require_once __DIR__.'/../v2/data/search3-local-results-read-v1.php';
require_once __DIR__.'/../v2/data/anytour-offer-store-v1.php';
function need(bool $ok,string $label):void{if(!$ok)throw new RuntimeException('CHECK_FAILED:'.$label);}
function exec_sql(PDO $pdo,string $path):void{$sql=preg_replace('/^\s*--.*$/m','',(string)file_get_contents($path));foreach(preg_split('/;\s*(?:\r?\n|$)/',$sql)?:[] as $s){$s=trim($s);if($s!=='')$pdo->exec($s);}}
function params_fixture():array{return[
 'departureId'=>'1','countryId'=>'4','dateFrom'=>'2026-10-05','dateTo'=>'2026-10-07','nightsFrom'=>'7','nightsTo'=>'9','adults'=>'2','childs'=>[7],
 'meal'=>'','hotelCategory'=>'5','hotelRating'=>'','hotelTypes'=>[],'hotelIds'=>[],'hotelServices'=>[],'arrivalId'=>'',
 'regionIds'=>[],'subregionIds'=>[],'operatorIds'=>[],'priceFrom'=>'','priceTo'=>'','currency'=>'RUB','onlyCharter'=>'false','onlyDirect'=>'false'];}
function dto_fixture(string $provider,int $legacy,string $salt,string $price,string $checkin='2026-10-05',int $nights=7):array{
 $now=1791309600;$operator=match($provider){'anex'=>'ANEX','andromeda'=>'FUN&SUN',default=>'Pegas Touristik'};$verified=$provider==='anex';
 return['schema_version'=>1,'provider'=>$provider,'operator'=>['raw'=>$operator,'canonical_name'=>$verified?'ANEX':null,'canonical_verified'=>$verified,'identity_source'=>$verified?'provider_fixed':'raw_label_only','filter_status'=>$provider==='tourvisor'?'verified':'unsupported','cross_provider_equivalence_verified'=>false,'supplier_code_exposed'=>false],
 'local_hotel_id'=>$legacy,'identity'=>['search_ref_digest'=>hash('sha256','search:'.$provider.':'.$salt),'offer_ref_digest'=>hash('sha256','offer:'.$provider.':'.$salt),'provider_hotel_ref_digest'=>$provider==='andromeda'?hash('sha256','andromeda_catalog:7001'):hash('sha256','hotel:'.$provider.':'.$salt)],
 'tour'=>['checkin'=>$checkin,'nights'=>$nights,'party'=>['adults'=>2,'children'=>1,'child_ages'=>[7]],'meal'=>['raw'=>'AI','family'=>'AI','qualifiers'=>['plus'=>false,'without_alcohol'=>false],'family_verified'=>true],'room'=>['raw'=>'STANDARD ROOM'],'placement'=>['raw'=>'2AD+1CHD'],'availability'=>['hotel'=>['raw'=>'available']],'flight_details'=>['state'=>'search_summary_only'],'observed_at'=>'2026-10-06T10:00:00Z'],
 'money'=>['search_price'=>['amount'=>$price,'currency'=>'RUB'],'search_price_with_surcharge'=>['amount'=>$price,'currency'=>'RUB']],
 'quote_state'=>'unknown','final_price_verified'=>false,'quote_evidence_digest'=>null,'context'=>['generation'=>1,'page'=>1,'issued_at'=>$now,'expires_at'=>$now+900,'current_context_verified'=>true],
 'selection_state'=>'disabled','booking_enabled'=>false,'finalPriceReady'=>true,'finalPrice'=>$price,'price'=>$price,'currency'=>'RUB'];}

$p=params_fixture();$scope=AnyTourSearchScopeV1::fromParams($p);
$variant=$p;$variant['hotelServices']=['9','2','9'];$variant['regionIds']=['7','3'];$variant2=$variant;$variant2['hotelServices']=['2','9'];$variant2['regionIds']=['3','7'];
need(AnyTourSearchScopeV1::fromParams($variant)['digest']===AnyTourSearchScopeV1::fromParams($variant2)['digest'],'list order/duplicates normalize');
$children=$p;$children['childs']=[7,5];$children2=$children;$children2['childs']=[5,7];need(AnyTourSearchScopeV1::fromParams($children)['digest']===AnyTourSearchScopeV1::fromParams($children2)['digest'],'child order normalize');
$changed=$p;$changed['dateTo']='2026-10-08';need(AnyTourSearchScopeV1::fromParams($changed)['digest']!==$scope['digest'],'date changes exact scope');
$bad=$p;$bad['providerId']='tourvisor';$failed=false;try{AnyTourSearchScopeV1::fromParams($bad);}catch(InvalidArgumentException){$failed=true;}need($failed,'extra provider field rejected');
$broad=$p;$broad['hotelCategory']='';$broadScope=AnyTourSearchScopeV1::fromParams($broad);
need(AnyTourSearchScopeV1::savedCanContributeToCurrent($scope['params'],$broadScope['params']),'5-star saved scope may contribute to all-stars current');
need(!AnyTourSearchScopeV1::savedCanContributeToCurrent($broadScope['params'],$scope['params']),'all-stars saved scope cannot feed 5-star current without row-level category proof');
$narrow=$broad;$narrow['dateFrom']='2026-10-06';$narrow['dateTo']='2026-10-06';$narrow['nightsFrom']='8';$narrow['nightsTo']='8';$narrowScope=AnyTourSearchScopeV1::fromParams($narrow);
need(AnyTourSearchScopeV1::familyDigest($scope['params'])===AnyTourSearchScopeV1::familyDigest($narrowScope['params']),'date and nights are not hard family identity');
need(AnyTourSearchScopeV1::savedCanContributeToCurrent($scope['params'],$narrowScope['params']),'overlapping saved date/night window can nominate concrete offers');
$far=$narrow;$far['dateFrom']='2026-11-01';$far['dateTo']='2026-11-01';$farScope=AnyTourSearchScopeV1::fromParams($far);need(!AnyTourSearchScopeV1::savedCanContributeToCurrent($scope['params'],$farScope['params']),'non-overlapping dates cannot contribute');

// The union never uses display price, hotel name or cross-provider numeric IDs as identity.
$exactItem=['provider'=>'tourvisor','sourceScopeDigest'=>$scope['digest'],'price'=>'120000','offer'=>dto_fixture('tourvisor',101,'tv','120000')];
$duplicate=$exactItem;$duplicate['price']='140000';$duplicate['sourceScopeDigest']=$broadScope['digest'];
$otherProvider=$exactItem;$otherProvider['provider']='anex';$otherProvider['offer']['provider']='anex';$otherProvider['sourceScopeDigest']=$broadScope['digest'];
$invalid=[];
foreach(['date','nights','adults','ages'] as $fault){
    $item=$otherProvider;$item['offer']['identity']['offer_ref_digest']=hash('sha256',$fault);
    if($fault==='date')$item['offer']['tour']['checkin']='2026-10-08';
    if($fault==='nights')$item['offer']['tour']['nights']=10;
    if($fault==='adults')$item['offer']['tour']['party']['adults']=3;
    if($fault==='ages')$item['offer']['tour']['party']['child_ages']=[8];
    $invalid[]=$item;
}
$inputs=serialize([$exactItem,$duplicate,$otherProvider,$invalid]);
$merged=search3_local_results_union([$exactItem],array_merge([$duplicate],$invalid,[$otherProvider]),$scope['params'],10);
need($merged===[$exactItem,$otherProvider],'same provider/offer dedupes; same digest in another provider remains separate');
need($inputs===serialize([$exactItem,$duplicate,$otherProvider,$invalid]),'union keeps source records immutable');
need(search3_local_results_union([$exactItem],[$otherProvider],$scope['params'],1)===[$exactItem],'one overall bound retains exact records');
need(search3_local_results_union([],$invalid,$scope['params'],10)===[],'non-exact concrete date/nights/party proof remains mandatory');
need(search3_local_results_union([],[$otherProvider],$scope['params'],10)===[$otherProvider],'empty exact still accepts a compatible record');
$legacy=$exactItem;unset($legacy['offer']['identity']);
need(search3_local_results_union([$legacy],[],$scope['params'],10)===[$legacy],'already-deduplicated legacy exact records retain visibility');
need(search3_local_results_union([$legacy],[$duplicate,$otherProvider],$scope['params'],10)===[$legacy,$otherProvider],'legacy exact rows cannot hide a different provider or gain guessed same-provider duplicates');
$legacyCompatible=$duplicate;unset($legacyCompatible['offer']['identity']);
need(search3_local_results_union([$exactItem],[$legacyCompatible],$scope['params'],10)===[$exactItem],'unproved same-provider cross-cohort duplicate stays excluded');
need(search3_local_results_union([],[$legacyCompatible],$scope['params'],10)===[$legacyCompatible],'legacy compatible-only visibility remains store-deduplicated');
$broken=$otherProvider;$broken['offer']['identity']['offer_ref_digest']='not-a-digest';
$badIdentity=false;try{search3_local_results_union([],[$broken],$scope['params'],10);}catch(RuntimeException $e){$badIdentity=str_contains($e->getMessage(),'IDENTITY_INTEGRITY');}
need($badIdentity,'malformed immutable identity cannot be silently merged');
echo "SEARCH3_LOCAL_SCOPE_UNION_PURE_OK exact_copy=1 provider_identity=1 concrete_scope=1 bounded=1 immutable=1\n";

$dsn=(string)getenv('ANYTOUR_LOCAL_RESULTS_TEST_DSN');$password=(string)getenv('ANYTOUR_LOCAL_RESULTS_TEST_PASSWORD');if(!str_starts_with($dsn,'mysql:'))throw new RuntimeException('fixture DSN required');
$pdo=new PDO($dsn,'root',$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$pdo->exec('SET FOREIGN_KEY_CHECKS=0');foreach(['tour_price_observations','anytour_offer_scopes','anytour_offers','anytour_offer_scope_state','anytour_offer_refreshes','anytour_offer_store_control','andromeda_hotel_identities','anytour_hotel_sources','anytour_hotels','anytour_catalog_control'] as $t)$pdo->exec("DROP TABLE IF EXISTS `$t`");$pdo->exec('SET FOREIGN_KEY_CHECKS=1');
exec_sql($pdo,__DIR__.'/../v2/data/migrations/20260916-anytour-canonical-catalog.sql');exec_sql($pdo,__DIR__.'/../v2/data/migrations/20260916-anytour-offer-store.sql');
$schema1Rejected=false;try{search3_local_results_build($pdo,$p,new DateTimeImmutable('2026-10-06T10:00:00Z'));}catch(RuntimeException $e){$schema1Rejected=str_contains($e->getMessage(),'Unsupported AnyTour offer-store schema');}need($schema1Rejected,'schema v1 read path retired fail closed');
exec_sql($pdo,__DIR__.'/../v2/data/migrations/20260917-anytour-offer-store-v2.sql');exec_sql($pdo,__DIR__.'/../v2/data/migrations/20260917-anytour-offer-scope-index.sql');
$pdo->exec("CREATE TABLE andromeda_hotel_identities (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 supplier_namespace VARCHAR(64) NOT NULL,
 external_hotel_id VARCHAR(120) NOT NULL,
 local_hotel_id BIGINT UNSIGNED NULL,
 decision_status VARCHAR(32) NOT NULL,
 KEY ix_external (external_hotel_id),
 KEY ix_tuple (supplier_namespace,external_hotel_id,decision_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$hotel=$pdo->prepare('INSERT INTO anytour_hotels(profile_json,profile_sha256,revision,is_active,created_at,updated_at) VALUES(?,?,1,1,UTC_TIMESTAMP(),UTC_TIMESTAMP())');
$bridge=$pdo->prepare("INSERT INTO anytour_hotel_sources(namespace,external_key,anytour_hotel_id,acquired_via,source_json,source_sha256,first_seen_at,last_seen_at) VALUES('legacy_catalog',?,?,'fixture',?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())");
$aliasBridge=$pdo->prepare("INSERT INTO anytour_hotel_sources(namespace,external_key,anytour_hotel_id,acquired_via,source_json,source_sha256,first_seen_at,last_seen_at) VALUES('anytour_local_id',?,?,'canonical_local_alias_v1',?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())");
$owns=[];foreach([[101,'Первый AnyTour отель'],[202,'Второй AnyTour отель']] as[$legacy,$name]){$profile=json_encode(['name'=>$name,'description'=>'Собственное описание','images'=>['https://images.example.test/'.$legacy.'.jpg']],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);$hotel->execute([$profile,hash('sha256',$profile)]);$own=(int)$pdo->lastInsertId();$owns[$legacy]=$own;$source=json_encode(['id'=>$legacy]);$bridge->execute([(string)$legacy,$own,$source,hash('sha256',$source)]);
$aliasData=[
    'accepted_local_hotel_id'=>$legacy,'canonical_hotel_id'=>$own,
    'derived_from_namespace'=>'legacy_catalog','derived_from_source_sha256'=>hash('sha256',$source),
    'schema_version'=>1,
];
ksort($aliasData,SORT_STRING);
$aliasSource=json_encode($aliasData,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
$aliasBridge->execute([(string)$legacy,$own,$aliasSource,hash('sha256',$aliasSource)]);}
$pdo->prepare("INSERT INTO andromeda_hotel_identities
 (supplier_namespace,external_hotel_id,local_hotel_id,decision_status)
 VALUES('andromeda_catalog','7001',202,'accepted')")->execute();
need(AnyTourProviderIdentityBridgeV1::allowsOffer(
 $pdo,'andromeda',hash('sha256','andromeda_catalog:7001'),202,$owns[202]
),'current Andromeda identity accepted');
$at=new DateTimeImmutable('2026-10-06T10:00:00Z');$expires=$at->modify('+2 hours');

$pdo->exec("CREATE TABLE tour_price_observations (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 departure_id INT NOT NULL,country_id INT NOT NULL,region_id INT NULL,hotel_id BIGINT UNSIGNED NOT NULL,
 departure_date DATE NOT NULL,nights INT NOT NULL,adults INT NOT NULL,children_count INT NOT NULL,child_ages_signature VARCHAR(32) NOT NULL,
 meal_id INT NULL,room_id INT NULL,room_type VARCHAR(255) NULL,operator_id INT NULL,currency CHAR(3) NOT NULL,
 price DECIMAL(12,2) NOT NULL,search_id BIGINT UNSIGNED NOT NULL,observed_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$obs=$pdo->prepare("INSERT INTO tour_price_observations
 (departure_id,country_id,region_id,hotel_id,departure_date,nights,adults,children_count,child_ages_signature,meal_id,room_id,room_type,operator_id,currency,price,search_id,observed_at)
 VALUES(1,4,23,?,?,?,?,2,?,NULL,NULL,'STANDARD',NULL,'RUB',?,?,?)");
$obs->execute([501,'2026-10-06',7,1,'3,7','99000',11,'2026-10-06 09:00:00']);
$obs->execute([502,'2026-10-06',7,1,'4,7','1000',12,'2026-10-06 09:05:00']);
$obs->execute([503,'2026-10-07',7,1,'3,7','110000',13,'2026-10-06 09:10:00']);
$calendar=search3_local_price_calendar($pdo,[
 'action'=>'price_calendar','departureId'=>1,'countryId'=>4,'regionId'=>23,
 'dateFrom'=>'2026-10-06','dateTo'=>'2026-10-07','nightsFrom'=>7,'nightsTo'=>7,
 'adults'=>1,'childs'=>[7,3],
],$at);
need($calendar['ok']===true&&$calendar['adults']===1&&$calendar['childrenCount']===2
    &&$calendar['childAges']===[3,7]&&$calendar['childAgesSignature']==='3,7','price calendar exact party echoed');
need($calendar['observedDays']===2&&$calendar['bestDate']==='2026-10-06'&&(float)$calendar['bestPrice']===99000.0,'price calendar excludes wrong child ages');
need((float)$calendar['series'][0]['minPrice']===99000.0&&(float)$calendar['series'][1]['minPrice']===110000.0,'price calendar exact-party daily prices');
$badCalendar=false;try{search3_local_price_calendar($pdo,[
 'action'=>'price_calendar','departureId'=>1,'countryId'=>4,'dateFrom'=>'2026-10-06','dateTo'=>'2026-10-07',
 'nightsFrom'=>7,'nightsTo'=>7,'adults'=>1,'childs'=>[18],
],$at);}catch(InvalidArgumentException){$badCalendar=true;}
need($badCalendar,'price calendar invalid child fails closed');
echo "SEARCH3_LOCAL_PRICE_CALENDAR_OK exact_party=1 wrong_party_excluded=1 writes=0\n";

need(AnyTourOfferScopeIndexV1::recordIfInstalled($pdo,$scope,$at),'narrow scope indexed');
foreach([
 ['tourvisor',101,'tv','120000','2026-10-05',7],
 ['anex',101,'anex','125000','2026-10-06',8],
 ['andromeda',202,'sam','119000','2026-10-07',9],
] as[$provider,$legacy,$salt,$price,$checkin,$nights]){$token=AnyTourOfferStoreV1::beginRefresh($pdo,$provider,$scope['digest'],$at);AnyTourOfferStoreV1::upsertReadyOffer($pdo,$token,$owns[$legacy],dto_fixture($provider,$legacy,$salt,$price,$checkin,$nights),$expires,$at);AnyTourOfferStoreV1::completeRefresh($pdo,$token,$at);}
$pdo->prepare('UPDATE anytour_hotels SET is_active=0 WHERE id=?')->execute([$owns[202]]);
$before=[(int)$pdo->query('SELECT COUNT(*) FROM anytour_offers')->fetchColumn(),(int)$pdo->query('SELECT COUNT(*) FROM anytour_hotels')->fetchColumn()];
$result=search3_local_results_build($pdo,$p,$at);$after=[(int)$pdo->query('SELECT COUNT(*) FROM anytour_offers')->fetchColumn(),(int)$pdo->query('SELECT COUNT(*) FROM anytour_hotels')->fetchColumn()];
need($before===$after,'reader writes nothing');need($result['offerStoreSchemaVersion']===2&&$result['scopeDigest']===$scope['digest']&&$result['scopeVersion']===1,'exact v2 scope echoed');need($result['matchMode']==='exact'&&$result['partial']===false,'exact scope wins');need($result['storedOfferCount']===3&&$result['withheldOfferCount']===1,'inactive own profile withheld');
need($result['hotelCount']===1&&$result['offerCount']===2&&$result['selectionAuthority']===false,'one canonical card two offers');need(is_object($result['providerOfferCounts'])&&(array)$result['providerOfferCounts']===['anex'=>1,'tourvisor'=>1],'populated provider counts stay keyed map');$group=$result['hotels'][0];need($group['anytourHotelId']===$owns[101]&&$group['hotel']['name']==='Первый AnyTour отель'&&$group['hotel']['catalog']==='anytour','first-party profile');
need($group['providers']===['anex','tourvisor'],'providers grouped under one hotel');need(count($group['offers'])===2&&$group['offers'][0]['price']==='120000','offers sorted by price');
foreach($group['offers'] as $offer){need(($offer['listing']['selection_state']??null)==='refresh_required'&&($offer['listing']['booking_enabled']??null)===false,'cached listing cannot select/book');need(!array_key_exists('context',$offer['listing']),'ephemeral provider context absent');}

$compatible=search3_local_results_build($pdo,$broad,$at);
need($compatible['matchMode']==='compatible'&&$compatible['partial']===true,'all-stars falls back to narrower saved scope');
need($compatible['scopeDigest']===$broadScope['digest']&&$compatible['sourceScopeDigests']===[$scope['digest']],'request scope stays current and source scope is disclosed');
need($compatible['hotelCount']===1&&$compatible['offerCount']===2,'compatible 5-star offers render immediately in all-stars search');
$one=search3_local_results_build($pdo,$narrow,$at);
need($one['matchMode']==='compatible'&&$one['partial']===true,'one-day one-night-count query reuses overlapping saved scope');
need($one['storedOfferCount']===1&&$one['offerCount']===1&&$one['hotelCount']===1,'only concrete matching offer survives cross-scope reuse');
$oneOffer=$one['hotels'][0]['offers'][0];need($oneOffer['provider']==='anex'&&($oneOffer['listing']['tour']['checkin']??null)==='2026-10-06'&&($oneOffer['listing']['tour']['nights']??null)===8,'concrete checkin and nights decide cached eligibility');
$wrongNight=$narrow;$wrongNight['nightsFrom']='9';$wrongNight['nightsTo']='9';$wrongNightResult=search3_local_results_build($pdo,$wrongNight,$at);need($wrongNightResult['matchMode']==='none'&&$wrongNightResult['offerCount']===0,'overlapping scope cannot leak a concrete offer with wrong nights');
$otherDeparture=$narrow;$otherDeparture['departureId']='2';$otherDepartureResult=search3_local_results_build($pdo,$otherDeparture,$at);need($otherDepartureResult['matchMode']==='none'&&$otherDepartureResult['offerCount']===0,'different departure remains hard-isolated');
$incompatible=$broad;$incompatible['hotelCategory']='4';$four=search3_local_results_build($pdo,$incompatible,$at);need($four['matchMode']==='none'&&$four['offerCount']===0,'5-star saved scope never leaks into 4-star request');
$otherDate=$broad;$otherDate['dateFrom']='2026-11-01';$otherDate['dateTo']='2026-11-02';$none=search3_local_results_build($pdo,$otherDate,$at);need($none['matchMode']==='none'&&$none['hotelCount']===0&&$none['offerCount']===0,'non-overlapping date range stays isolated');

need(AnyTourOfferScopeIndexV1::recordIfInstalled($pdo,$broadScope,$at->modify('+1 minute')),'broad exact scope indexed');
$token=AnyTourOfferStoreV1::beginRefresh($pdo,'tourvisor',$broadScope['digest'],$at->modify('+1 minute'));AnyTourOfferStoreV1::upsertReadyOffer($pdo,$token,$owns[101],dto_fixture('tourvisor',101,'tv-broad','130000'),$expires,$at->modify('+1 minute'));AnyTourOfferStoreV1::completeRefresh($pdo,$token,$at->modify('+1 minute'));
$exactBroad=search3_local_results_build($pdo,$broad,$at->modify('+1 minute'));
need($exactBroad['matchMode']==='compatible'&&$exactBroad['partial']===true,'mixed exact and compatible results disclose partial saved coverage');
need($exactBroad['storedOfferCount']===4&&$exactBroad['withheldOfferCount']===1&&$exactBroad['offerCount']===3,'exact Tourvisor must not suppress compatible ANEX or another distinct Tourvisor offer');
need((array)$exactBroad['providerOfferCounts']===['anex'=>1,'tourvisor'=>2],'provider counts describe the deduplicated union');
need($exactBroad['scopeDigest']===$broadScope['digest']&&$exactBroad['sourceScopeDigests']===[$broadScope['digest'],$scope['digest']],'current query identity and both contributing scopes are retained');
need(array_column($exactBroad['hotels'][0]['offers'],'price')===['120000','125000','130000'],'whole concrete offers retain their original prices and final price sort');
$limited=search3_local_results_build($pdo,$broad,$at->modify('+1 minute'),1);
need($limited['storedOfferCount']===1&&$limited['offerCount']===1&&$limited['hotels'][0]['offers'][0]['price']==='130000','overall bound does not replace an exact record with a compatible one');

$deleteBridge=$pdo->prepare("DELETE FROM anytour_hotel_sources WHERE namespace='anytour_local_id' AND external_key=? AND anytour_hotel_id=?");$deleteBridge->execute(['101',$owns[101]]);need($deleteBridge->rowCount()===1,'accepted bridge revoked');
$revoked=search3_local_results_build($pdo,$p,$at);need($revoked['storedOfferCount']===1&&$revoked['withheldOfferCount']===1,'revoked identity offers fail closed before canonical grouping');need($revoked['hotelCount']===0&&$revoked['offerCount']===0,'revoked identity cannot render cached canonical card');
$source=json_encode(['id'=>101]);
$aliasData=[
 'accepted_local_hotel_id'=>101,'canonical_hotel_id'=>$owns[101],
 'derived_from_namespace'=>'legacy_catalog','derived_from_source_sha256'=>hash('sha256',$source),
 'schema_version'=>1,
];
ksort($aliasData,SORT_STRING);
$aliasSource=json_encode($aliasData,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
$aliasBridge->execute(['101',$owns[101],$aliasSource,hash('sha256',$aliasSource)]);
$restored=search3_local_results_build($pdo,$p,$at);need($restored['storedOfferCount']===4&&$restored['categoryFilteredOfferCount']===1&&$restored['hotelCount']===1&&$restored['offerCount']===2,'restored bridge restores exact visibility while unproved compatible category stays excluded');
need(is_object($none['providerOfferCounts'])&&count((array)$none['providerOfferCounts'])===0,'empty provider counts remain keyed map');need(str_contains((string)json_encode($none,JSON_UNESCAPED_SLASHES),'"providerOfferCounts":{}'),'empty provider counts serialize as JSON object');
// One exact provider coexists with both other providers; only test DB fixture data changes.
$pdo->prepare('UPDATE anytour_hotels SET is_active=1 WHERE id=?')->execute([$owns[202]]);
$all=search3_local_results_build($pdo,$broad,$at->modify('+1 minute'));
need($all['hotelCount']===2&&(array)$all['providerOfferCounts']===['andromeda'=>1,'anex'=>1,'tourvisor'=>2],'exact Tourvisor coexists with compatible ANEX and Andromeda');
$at2=$at->modify('+2 minutes');
$token=AnyTourOfferStoreV1::beginRefresh($pdo,'tourvisor',$broadScope['digest'],$at2);
foreach([['tv-broad','130000'],['tv','140000']] as [$salt,$price])AnyTourOfferStoreV1::upsertReadyOffer($pdo,$token,$owns[101],dto_fixture('tourvisor',101,$salt,$price),$expires,$at2);
AnyTourOfferStoreV1::completeRefresh($pdo,$token,$at2);
$again=search3_local_results_build($pdo,$broad,$at2);
need($again['offerCount']===4&&$again['storedOfferCount']===4,'same immutable offer across scopes is counted only once');
$prices=array_column($again['hotels'][1]['offers'],'price');
need(in_array('140000',$prices,true)&&!in_array('120000',$prices,true),'exact duplicate retains the exact record rather than the cheaper cached copy');
$reverse=search3_local_results_build($pdo,$p,$at2);
$tv=array_values(array_filter($reverse['hotels'],static fn($h)=>$h['anytourHotelId']===$owns[101]))[0];
need(array_column($tv['offers'],'price')===['120000','125000'],'exact narrower copies remain authoritative in a mixed result even without canonical category');

// Use another provider with the SAME digest; provider qualification must keep both.
$cross=dto_fixture('anex',101,'cross','127000','2026-10-06',8);
$cross['identity']['offer_ref_digest']=hash('sha256','offer:tourvisor:tv');
$token=AnyTourOfferStoreV1::beginRefresh($pdo,'anex',$broadScope['digest'],$at2);
AnyTourOfferStoreV1::upsertReadyOffer($pdo,$token,$owns[101],$cross,$expires,$at2);
AnyTourOfferStoreV1::completeRefresh($pdo,$token,$at2);
$crossResult=search3_local_results_build($pdo,$broad,$at2);
need((array)$crossResult['providerOfferCounts']===['andromeda'=>1,'anex'=>2,'tourvisor'=>2],'same offer digest in different providers never collides');
foreach($crossResult['hotels'] as $h)foreach($h['offers'] as $o){
    need($o['listing']['selection_state']==='refresh_required'&&$o['listing']['booking_enabled']===false,'union cannot reconstruct selection or booking authority');
    need(!isset($o['listing']['context']),'union never exposes provider context');
}
need($crossResult['selectionAuthority']===false,'response remains display-only');

$at3=$at->modify('+3 minutes');
$token=AnyTourOfferStoreV1::beginRefresh($pdo,'anex',$broadScope['digest'],$at3);
AnyTourOfferStoreV1::upsertReadyOffer($pdo,$token,$owns[101],$cross,$expires,$at3);
$categoryDto=dto_fixture('anex',202,'category','129000','2026-10-06',8);
AnyTourOfferStoreV1::upsertReadyOffer($pdo,$token,$owns[202],$categoryDto,$expires,$at3);
foreach(['date','nights','party'] as $fault){
    $badDto=dto_fixture('anex',202,'bad-'.$fault,'100000','2026-10-06',8);
    if($fault==='date')$badDto['tour']['checkin']='2026-10-08';
    if($fault==='nights')$badDto['tour']['nights']=10;
    if($fault==='party')$badDto['tour']['party']['child_ages']=[8];
    AnyTourOfferStoreV1::upsertReadyOffer($pdo,$token,$owns[202],$badDto,$expires,$at3);
}
AnyTourOfferStoreV1::completeRefresh($pdo,$token,$at3);
$profileQuery=$pdo->prepare('SELECT profile_json FROM anytour_hotels WHERE id=?');$profileQuery->execute([$owns[202]]);
$profile202=json_decode((string)$profileQuery->fetchColumn(),true,512,JSON_THROW_ON_ERROR);
$setProfile=$pdo->prepare('UPDATE anytour_hotels SET profile_json=?,profile_sha256=? WHERE id=?');
foreach([3,5] as $category){
    $profile202['category']=$category;$rawProfile=json_encode($profile202,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    $setProfile->execute([$rawProfile,hash('sha256',$rawProfile),$owns[202]]);
    $rowsBefore=$pdo->query('SELECT * FROM anytour_offers ORDER BY id')->fetchAll();
    $proved=search3_local_results_build($pdo,$p,$at3);
    need($rowsBefore===$pdo->query('SELECT * FROM anytour_offers ORDER BY id')->fetchAll(),'union does not mutate stored offer bytes');
    need($proved['storedOfferCount']===6,'wrong date/nights/child age rejected before canonical grouping despite an exact cohort');
    need($proved['categoryFilteredOfferCount']===($category===3?3:2),'canonical category proof applies to every compatible row but never drops exact records');
    need($proved['offerCount']===($category===3?3:4),'only a matching canonical minimum-star profile admits the extra compatible offer');
}
$expired=search3_local_results_build($pdo,$broad,$at->modify('+3 hours'));
need($expired['offerCount']===0&&$expired['matchMode']==='none','expired exact and compatible snapshots never regain visibility');


// A month differs from the saved search scope. Confirmation-only snapshots must
// contribute without borrowing a final-ready sibling or gaining quote authority.
$confirmationParams=$p;$confirmationParams['departureId']='3';$confirmationParams['hotelCategory']='';
$confirmationParams['nightsFrom']='7';$confirmationParams['nightsTo']='7';
$confirmationScope=AnyTourSearchScopeV1::fromParams($confirmationParams);
$calendarParams=$confirmationParams;$calendarParams['dateFrom']='2026-10-01';$calendarParams['dateTo']='2026-10-22';
$calendarScope=AnyTourSearchScopeV1::fromParams($calendarParams);
need(AnyTourOfferScopeIndexV1::recordIfInstalled($pdo,$confirmationScope,$at),'confirmation-only scope indexed');
$confirmation=dto_fixture('tourvisor',101,'confirmation-only','117777','2026-10-06',7);
$confirmation['finalPriceReady']=false;$confirmation['finalPrice']=null;
$confirmationToken=AnyTourOfferStoreV1::beginRefresh($pdo,'tourvisor',$confirmationScope['digest'],$at);
AnyTourOfferStoreV1::upsertReadyOffer($pdo,$confirmationToken,$owns[101],$confirmation,$expires,$at);
need(AnyTourOfferScopeIndexV1::compatibleDigests($pdo,$calendarScope,$at)===[],'incomplete confirmation snapshot is not nominated');
need(search3_local_results_build($pdo,$calendarParams,$at)['offerCount']===0,'incomplete confirmation snapshot stays invisible');
AnyTourOfferStoreV1::completeRefresh($pdo,$confirmationToken,$at);
$confirmationExact=search3_local_results_build($pdo,$confirmationParams,$at);
need($confirmationExact['matchMode']==='exact'&&$confirmationExact['offerCount']===1,'confirmation-only exact scope remains visible');
need(AnyTourOfferScopeIndexV1::compatibleDigests($pdo,$calendarScope,$at)===[$confirmationScope['digest']],'confirmation-only completed scope is nominated for a month');
$confirmationBefore=$pdo->query('SELECT * FROM anytour_offers ORDER BY id')->fetchAll();
$confirmationCalendar=search3_local_results_build($pdo,$calendarParams,$at);
need($confirmationCalendar['matchMode']==='compatible'&&$confirmationCalendar['offerCount']===1,'confirmation-only offer reaches the month reader');
need($confirmationCalendar['sourceScopeDigests']===[$confirmationScope['digest']]&&$confirmationCalendar['scopeDigest']===$calendarScope['digest'],'calendar keeps current scope and discloses saved scope');
$confirmationOffer=$confirmationCalendar['hotels'][0]['offers'][0];
need($confirmationOffer===$confirmationExact['hotels'][0]['offers'][0],'cross-scope confirmation price and complete listing are unchanged');
need($confirmationOffer['price']==='117777'
    &&$confirmationOffer['listing']['listingPriceState']==='search_price_confirmation_required'
    &&$confirmationOffer['listing']['listingPriceReady']===false
    &&$confirmationOffer['listing']['priceConfirmationRequired']===true
    &&$confirmationOffer['listing']['finalPriceVerified']===false
    &&$confirmationOffer['listing']['selection_state']==='refresh_required'
    &&$confirmationOffer['listing']['booking_enabled']===false
    &&!isset($confirmationOffer['listing']['context'])
    &&$confirmationCalendar['selectionAuthority']===false,'calendar cannot promote a confirmation price or restore booking authority');
need($confirmationBefore===$pdo->query('SELECT * FROM anytour_offers ORDER BY id')->fetchAll(),'confirmation-only reads do not mutate stored rows');
foreach(['date','nights','ages','departure'] as $fault){
    $unrelated=$calendarParams;
    if($fault==='date'){$unrelated['dateFrom']='2026-10-08';$unrelated['dateTo']='2026-10-09';}
    if($fault==='nights'){$unrelated['nightsFrom']='8';$unrelated['nightsTo']='8';}
    if($fault==='ages')$unrelated['childs']=[8];
    if($fault==='departure')$unrelated['departureId']='4';
    need(search3_local_results_build($pdo,$unrelated,$at)['offerCount']===0,'confirmation offer keeps exact '.$fault.' constraints');
}
$confirmationActive=$pdo->prepare('UPDATE anytour_offers SET is_active=? WHERE scope_sha256=?');
$confirmationActive->execute([0,$confirmationScope['digest']]);
need(AnyTourOfferScopeIndexV1::compatibleDigests($pdo,$calendarScope,$at)===[],'inactive confirmation scope is not nominated');
need(search3_local_results_build($pdo,$calendarParams,$at)['offerCount']===0,'inactive confirmation offer stays invisible');
$confirmationActive->execute([1,$confirmationScope['digest']]);
need(search3_local_results_build($pdo,$calendarParams,$expires)['offerCount']===0,'confirmation offer expires at the same exact boundary');

$nextConfirmation=dto_fixture('tourvisor',101,'confirmation-next','116666','2026-10-06',7);
$nextConfirmation['finalPriceReady']=false;$nextConfirmation['finalPrice']=null;
$pendingToken=AnyTourOfferStoreV1::beginRefresh($pdo,'tourvisor',$confirmationScope['digest'],$at2);
AnyTourOfferStoreV1::upsertReadyOffer($pdo,$pendingToken,$owns[101],$nextConfirmation,$expires,$at2);
$whilePending=search3_local_results_build($pdo,$calendarParams,$at2);
need($whilePending['offerCount']===1&&$whilePending['hotels'][0]['offers'][0]['price']==='117777','unfinished replacement cannot displace completed confirmation snapshot');
AnyTourOfferStoreV1::completeRefresh($pdo,$pendingToken,$at2);
$afterPending=search3_local_results_build($pdo,$calendarParams,$at2);
need($afterPending['offerCount']===1&&$afterPending['hotels'][0]['offers'][0]['price']==='116666','completed replacement is the only visible confirmation snapshot');

$readyToken=AnyTourOfferStoreV1::beginRefresh($pdo,'anex',$confirmationScope['digest'],$at2);
AnyTourOfferStoreV1::upsertReadyOffer($pdo,$readyToken,$owns[101],dto_fixture('anex',101,'confirmation-sibling','118888','2026-10-06',7),$expires,$at2);
AnyTourOfferStoreV1::completeRefresh($pdo,$readyToken,$at2);
$mixedConfirmation=search3_local_results_build($pdo,$calendarParams,$at2);
need((array)$mixedConfirmation['providerOfferCounts']===['anex'=>1,'tourvisor'=>1],'mixed ready and confirmation scope keeps both providers');
need(array_column($mixedConfirmation['hotels'][0]['offers'],'price')===['116666','118888'],'mixed scope preserves original amounts and sorting');
echo "SEARCH3_CONFIRMATION_SCOPE_OK exact=1 compatible=1 incomplete_hidden=1 active_expiry=1 no_price_promotion=1 same_trip=1 mixed=1 writes=0\n";

$pdo->exec("UPDATE anytour_offers SET payload_json='{}' WHERE provider='tourvisor'");$integrityFailed=false;try{search3_local_results_build($pdo,$p,$at);}catch(RuntimeException $e){$integrityFailed=str_contains($e->getMessage(),'PAYLOAD_INTEGRITY');}need($integrityFailed,'corrupt stored payload fails closed');
echo "SEARCH3_LOCAL_DB_RESULTS_OK scope_v1=1 compatible_filters=1 offer_date_nights=1 departure_hard=1 scope_union=1 exact_duplicate_wins=1 store_v2=1 rendered=2 revoked_identity_hidden=2 writes=0\n";
