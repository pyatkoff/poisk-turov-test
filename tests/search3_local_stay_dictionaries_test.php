<?php
declare(strict_types=1);
require_once __DIR__.'/../v2/data/search3-local-results-read-v1.php';
require_once __DIR__.'/../v2/data/anytour-offer-store-v1.php';

function stay_results_need(bool $ok,string $label): void
{
    if(!$ok) throw new RuntimeException('CHECK_FAILED:'.$label);
}
function stay_results_sql(PDO $pdo,string $path): void
{
    $sql=preg_replace('/^\s*--.*$/m','',(string)file_get_contents($path));
    foreach(preg_split('/;\s*(?:\r?\n|$)/',$sql)?:[] as $statement){
        $statement=trim($statement);if($statement!=='')$pdo->exec($statement);
    }
}
function stay_results_params(): array
{
    return [
        'departureId'=>'1','countryId'=>'4','dateFrom'=>'2026-10-05','dateTo'=>'2026-10-05',
        'nightsFrom'=>'7','nightsTo'=>'7','adults'=>'2','childs'=>[],
        'meal'=>'','hotelCategory'=>'','hotelRating'=>'','hotelTypes'=>[],'hotelIds'=>[],
        'hotelServices'=>[],'arrivalId'=>'','regionIds'=>[],'subregionIds'=>[],'operatorIds'=>[],
        'priceFrom'=>'','priceTo'=>'','currency'=>'RUB','onlyCharter'=>'false','onlyDirect'=>'false',
    ];
}
function stay_results_offer(int $legacyId,int $issued): array
{
    return [
        'schema_version'=>1,'provider'=>'tourvisor',
        'operator'=>[
            'raw'=>'Pegas Touristik','canonical_name'=>null,'canonical_verified'=>false,
            'identity_source'=>'raw_label_only','filter_status'=>'verified',
            'cross_provider_equivalence_verified'=>false,'supplier_code_exposed'=>false,
        ],
        'local_hotel_id'=>$legacyId,
        'identity'=>[
            'search_ref_digest'=>hash('sha256','search:stay-dictionary'),
            'offer_ref_digest'=>hash('sha256','offer:stay-dictionary'),
            'provider_hotel_ref_digest'=>hash('sha256','hotel:stay-dictionary'),
        ],
        'tour'=>[
            'checkin'=>'2026-10-05','nights'=>7,
            'party'=>['adults'=>2,'children'=>0,'child_ages'=>[]],
            'meal'=>['raw'=>'AI'],'room'=>['raw'=>'STANDARD ROOM'],'placement'=>['raw'=>'2AD'],
            'availability'=>['hotel'=>['raw'=>'available']],
            'flight_details'=>['state'=>'search_summary_only'],
            'observed_at'=>gmdate('Y-m-d\\TH:i:s\\Z',$issued),
        ],
        'money'=>['search_price_with_surcharge'=>['amount'=>'199390','currency'=>'RUB']],
        'quote_state'=>'unknown','final_price_verified'=>false,'quote_evidence_digest'=>null,
        'context'=>['generation'=>1,'page'=>1,'issued_at'=>$issued,'expires_at'=>$issued+900,'current_context_verified'=>true],
        'selection_state'=>'disabled','booking_enabled'=>false,
        'finalPriceReady'=>true,'finalPrice'=>'199390','price'=>'199390','currency'=>'RUB',
    ];
}

$dsn=(string)getenv('ANYTOUR_LOCAL_STAY_RESULTS_TEST_DSN');
$password=(string)getenv('ANYTOUR_LOCAL_STAY_RESULTS_TEST_PASSWORD');
if($dsn!=='mysql:host=127.0.0.1;port=3306;dbname=anytour_local_results_fixture;charset=utf8mb4'){
    throw new RuntimeException('Dedicated disposable fixture DSN required');
}
$pdo=new PDO($dsn,'root',$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
foreach([
    'anytour_hotel_stay_mappings_v2','anytour_hotel_meal_concepts_v2','anytour_hotel_room_concepts_v2',
    'anytour_stay_mappings','anytour_hotel_rooms','anytour_room_categories','anytour_meal_plans',
    'anytour_offer_scopes','anytour_offers','anytour_offer_scope_state','anytour_offer_refreshes','anytour_offer_store_control',
    'anytour_hotel_sources','anytour_hotels','anytour_catalog_control'
] as $table)$pdo->exec("DROP TABLE IF EXISTS `$table`");
$pdo->exec('SET FOREIGN_KEY_CHECKS=1');
$root=dirname(__DIR__);
stay_results_sql($pdo,$root.'/v2/data/migrations/20260916-anytour-canonical-catalog.sql');
stay_results_sql($pdo,$root.'/v2/data/migrations/20260916-anytour-stay-catalog.sql');
stay_results_sql($pdo,$root.'/v2/data/migrations/20260916-anytour-offer-store.sql');
stay_results_sql($pdo,$root.'/v2/data/migrations/20260917-anytour-offer-store-v2.sql');
stay_results_sql($pdo,$root.'/v2/data/migrations/20260917-anytour-offer-scope-index.sql');

$profile=json_encode(['name'=>'Собственный AnyTour отель','description'=>'Описание AnyTour'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
$pdo->prepare('INSERT INTO anytour_hotels(profile_json,profile_sha256,revision,is_active,created_at,updated_at) VALUES(?,?,1,1,UTC_TIMESTAMP(),UTC_TIMESTAMP())')
    ->execute([$profile,hash('sha256',$profile)]);
$hotelId=(int)$pdo->lastInsertId();$legacyId=701;
$source='{}';
$pdo->prepare("INSERT INTO anytour_hotel_sources(namespace,external_key,anytour_hotel_id,acquired_via,source_json,source_sha256,first_seen_at,last_seen_at) VALUES('legacy_catalog',?,?, 'fixture',?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())")
    ->execute([(string)$legacyId,$hotelId,$source,hash('sha256',$source)]);
$aliasData=[
    'accepted_local_hotel_id'=>$legacyId,'canonical_hotel_id'=>$hotelId,
    'derived_from_namespace'=>'legacy_catalog','derived_from_source_sha256'=>hash('sha256',$source),
    'schema_version'=>1,
];
ksort($aliasData,SORT_STRING);
$aliasSource=json_encode($aliasData,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
$pdo->prepare("INSERT INTO anytour_hotel_sources(namespace,external_key,anytour_hotel_id,acquired_via,source_json,source_sha256,first_seen_at,last_seen_at) VALUES('anytour_local_id',?,?,'canonical_local_alias_v1',?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())")
    ->execute([(string)$legacyId,$hotelId,$aliasSource,hash('sha256',$aliasSource)]);
$pdo->prepare('INSERT INTO anytour_hotel_rooms(anytour_hotel_id,local_key,name_ru,category_code,facts_json,revision,is_active,created_at) VALUES(?,?,?,?,?,1,1,UTC_TIMESTAMP())')
    ->execute([$hotelId,'own:standard-sea','Стандарт · вид на море','standard',json_encode(['view'=>'Море'],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
$pdo->prepare('INSERT INTO anytour_hotel_rooms(anytour_hotel_id,local_key,name_ru,category_code,facts_json,revision,is_active,created_at) VALUES(?,?,?,?,?,1,0,UTC_TIMESTAMP())')
    ->execute([$hotelId,'own:retired','Старый номер','standard','{}']);

$params=stay_results_params();$scope=AnyTourSearchScopeV1::fromParams($params);
$now=new DateTimeImmutable('2026-09-17T19:20:00Z');$issued=$now->getTimestamp();
$token=AnyTourOfferStoreV1::beginRefresh($pdo,'tourvisor',$scope['digest'],$now);
AnyTourOfferStoreV1::upsertReadyOffer($pdo,$token,$hotelId,stay_results_offer($legacyId,$issued),$now->modify('+2 hours'),$now);
AnyTourOfferStoreV1::completeRefresh($pdo,$token,$now);
$before=[
    (int)$pdo->query('SELECT COUNT(*) FROM anytour_offers')->fetchColumn(),
    (int)$pdo->query('SELECT COUNT(*) FROM anytour_hotel_rooms')->fetchColumn(),
    (int)$pdo->query('SELECT COUNT(*) FROM anytour_stay_mappings')->fetchColumn(),
];
$fallback=search3_local_results_build($pdo,$params,$now);
stay_results_need(($fallback['stayCatalog']['source']??null)==='anytour-stay-catalog-v1-compat','v1 explicitly compatibility only');
stay_results_need(($fallback['stayCatalog']['compatibilityFallback']??null)===true,'v1 fallback marked');
stay_results_need(($fallback['stayCatalog']['hotelScoped']??null)===false,'v1 not claimed hotel scoped');
$legacyMeals=$fallback['stayCatalog']['mealPlans']??[];
stay_results_need(count($legacyMeals)===10,'legacy meals remain only until v2 install');

stay_results_sql($pdo,$root.'/v2/data/migrations/20260918-anytour-hotel-stay-v2.sql');
$stayV2=new AnyTourHotelStayCatalogV2($pdo);
$pdo->beginTransaction();
$roomV2=$stayV2->createConcept('room',$hotelId,'own-v2:standard-sea','Стандарт · вид на море',['view'=>'Море']);
$mealV2=$stayV2->createConcept('meal',$hotelId,'own-v2:ai','Всё включено · концепция отеля',[
    'concept'=>'hotel-specific-ai','alcohol'=>'local-only'
]);
$pdo->commit();

$offerScope=AnyTourHotelStayCatalogV2::offerScope(
    'tourvisor',$legacyId,hash('sha256','hotel:stay-dictionary'),'Pegas Touristik'
);
$evidence=['ref'=>'review://stay-result-fixture','sha256'=>hash('sha256','stay-result-fixture'),'reviewedBy'=>'fixture'];
$pdo->beginTransaction();
$stayV2->recordDecision(
    $offerScope,['kind'=>'room','keyKind'=>'label','externalKey'=>'STANDARD ROOM'],
    $hotelId,'accepted',$roomV2,$evidence
);
$stayV2->recordDecision(
    $offerScope,['kind'=>'meal','keyKind'=>'label','externalKey'=>'AI'],
    $hotelId,'accepted',$mealV2,$evidence
);
$pdo->commit();

$beforeV2=[
    (int)$pdo->query('SELECT COUNT(*) FROM anytour_offers')->fetchColumn(),
    (int)$pdo->query('SELECT COUNT(*) FROM anytour_hotel_room_concepts_v2')->fetchColumn(),
    (int)$pdo->query('SELECT COUNT(*) FROM anytour_hotel_meal_concepts_v2')->fetchColumn(),
    (int)$pdo->query('SELECT COUNT(*) FROM anytour_hotel_stay_mappings_v2')->fetchColumn(),
];
$result=search3_local_results_build($pdo,$params,$now);
$afterV2=[
    (int)$pdo->query('SELECT COUNT(*) FROM anytour_offers')->fetchColumn(),
    (int)$pdo->query('SELECT COUNT(*) FROM anytour_hotel_room_concepts_v2')->fetchColumn(),
    (int)$pdo->query('SELECT COUNT(*) FROM anytour_hotel_meal_concepts_v2')->fetchColumn(),
    (int)$pdo->query('SELECT COUNT(*) FROM anytour_hotel_stay_mappings_v2')->fetchColumn(),
];
stay_results_need($beforeV2===$afterV2,'v2 reader is read-only');
stay_results_need($result['hotelCount']===1&&$result['offerCount']===1,'one canonical hotel and offer');
stay_results_need(($result['stayCatalog']['source']??null)==='anytour-hotel-stay-v2','v2 preferred');
stay_results_need(($result['stayCatalog']['hotelScoped']??null)===true,'v2 declares hotel scope');
stay_results_need(($result['stayCatalog']['compatibilityFallback']??null)===false,'v2 no compatibility fallback');
stay_results_need(!array_key_exists('mealPlans',$result['stayCatalog']),'no global meal dictionary in v2');
$rooms=$result['hotels'][0]['stay']['rooms']??[];
$meals=$result['hotels'][0]['stay']['meals']??[];
stay_results_need(($result['hotels'][0]['stay']['source']??null)==='anytour-hotel-stay-v2','hotel stay v2 source');
stay_results_need(count($rooms)===1&&$rooms[0]['localKey']==='own-v2:standard-sea','hotel-local room exposed');
stay_results_need(count($meals)===1&&$meals[0]['localKey']==='own-v2:ai','hotel-local meal exposed');
stay_results_need($meals[0]['nameRu']==='Всё включено · концепция отеля','hotel-specific Russian meal label');
stay_results_need(($meals[0]['facts']['concept']??null)==='hotel-specific-ai','hotel-specific meal facts');
$offer=$result['hotels'][0]['offers'][0];
$listing=$offer['listing'];
stay_results_need(($listing['tour']['meal']['raw']??null)==='AI','supplier meal raw preserved');
stay_results_need(($listing['tour']['room']['raw']??null)==='STANDARD ROOM','supplier room raw preserved');
stay_results_need(!array_key_exists('canonical',$listing['tour']['meal'])&&!array_key_exists('canonical',$listing['tour']['room']),'no mutation of supplier stay facts');
$match=$offer['stayMatch']??null;
stay_results_need(is_array($match)&&($match['source']??null)==='anytour-hotel-stay-v2','offer stay match source');
stay_results_need(($match['exactScope']??null)===true&&array_key_exists('reason',$match)&&$match['reason']===null,'offer exact scope');
stay_results_need(($match['room']['status']??null)==='accepted','offer room mapping accepted');
stay_results_need(($match['room']['canonical']['localKey']??null)==='own-v2:standard-sea','offer room canonical local key');
stay_results_need(($match['meal']['status']??null)==='accepted','offer meal mapping accepted');
stay_results_need(($match['meal']['canonical']['localKey']??null)==='own-v2:ai','offer meal canonical local key');
stay_results_need(($match['meal']['canonical']['nameRu']??null)==='Всё включено · концепция отеля','offer meal hotel-specific label');
stay_results_need($beforeV2===$afterV2,'dictionary and mapping exposure creates no writes');
stay_results_need($afterV2[3]===2,'two reviewed mappings remain unchanged');

$caseMismatch=$stayV2->resolveOfferFactsBatch([[
    'anytourHotelId'=>$hotelId,'legacyHotelId'=>$legacyId,'provider'=>'tourvisor',
    'providerHotelRefDigest'=>hash('sha256','hotel:stay-dictionary'),
    'operatorRaw'=>'PEGAS TOURISTIK','roomRaw'=>'STANDARD ROOM','mealRaw'=>'AI',
]])[0];
stay_results_need($caseMismatch['room']['status']==='unmapped'&&$caseMismatch['meal']['status']==='unmapped','operator label case does not borrow mapping');

echo "SEARCH3_LOCAL_STAY_DICTIONARIES_V2_OK hotels=1 hotel_scoped_rooms=1 hotel_scoped_meals=1 exact_offer_mapping=1 raw_offer_preserved=1 mappings=2 writes=0\n";
