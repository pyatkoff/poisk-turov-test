<?php
declare(strict_types=1);

require_once __DIR__ . '/../v2/data/anytour-hotel-stay-catalog-v2.php';

function hs_check(bool $value,string $label): void
{
    if (!$value) throw new RuntimeException('CHECK_FAILED:'.$label);
}
function hs_expect(callable $fn,string $needle,string $label): void
{
    try { $fn(); }
    catch (Throwable $e) {
        hs_check(str_contains($e->getMessage(),$needle),$label.':wrong:'.$e->getMessage());
        return;
    }
    throw new RuntimeException('CHECK_FAILED:'.$label.':no_error');
}
function hs_sql(PDO $db,string $path): void
{
    $sql=file_get_contents($path);
    if (!is_string($sql) || $sql==='') throw new RuntimeException('EMPTY_SQL');
    $sql=preg_replace('/^\s*--.*$/m','',$sql) ?? $sql;
    foreach (preg_split('/;\s*(?:\r?\n|$)/',$sql) ?: [] as $statement) {
        $statement=trim($statement);
        if ($statement!=='') $db->exec($statement);
    }
}
function hs_source(PDO $db,int $hotelId,string $external): void
{
    $json=json_encode(['fixture'=>true],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    $stmt=$db->prepare(
        "INSERT INTO anytour_hotel_sources
        (namespace,external_key,anytour_hotel_id,acquired_via,source_json,source_sha256,first_seen_at,last_seen_at)
        VALUES ('provider_ref_digest:test',?,?,'fixture',?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())"
    );
    $stmt->execute([$external,$hotelId,$json,hash('sha256',$json)]);
}

function hs_alias(PDO $db,int $hotelId,int $legacyId): void
{
    $legacy=json_encode(['legacy'=>$legacyId],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    $legacySha=hash('sha256',$legacy);
    $db->prepare(
        "INSERT INTO anytour_hotel_sources
        (namespace,external_key,anytour_hotel_id,acquired_via,source_json,source_sha256,first_seen_at,last_seen_at)
        VALUES ('legacy_catalog',?,?,'fixture',?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())"
    )->execute([(string)$legacyId,$hotelId,$legacy,$legacySha]);
    $alias=[
        'accepted_local_hotel_id'=>$legacyId,
        'canonical_hotel_id'=>$hotelId,
        'derived_from_namespace'=>'legacy_catalog',
        'derived_from_source_sha256'=>$legacySha,
        'schema_version'=>1,
    ];
    ksort($alias,SORT_STRING);
    $json=json_encode($alias,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    $db->prepare(
        "INSERT INTO anytour_hotel_sources
        (namespace,external_key,anytour_hotel_id,acquired_via,source_json,source_sha256,first_seen_at,last_seen_at)
        VALUES ('anytour_local_id',?,?,'canonical_local_alias_v1',?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())"
    )->execute([(string)$legacyId,$hotelId,$json,hash('sha256',$json)]);
}

$dsn=(string)getenv('ANYTOUR_HOTEL_STAY_V2_TEST_DSN');
if ($dsn==='') {
    hs_check(AnyTourHotelStayCatalogV2::scope([
        'namespace'=>'provider_ref_digest:test','hotelKey'=>'hotel-A','operatorKey'=>'5'
    ])['hotelKey']==='hotel-A','pure-scope');
    hs_check(AnyTourHotelStayCatalogV2::reference([
        'kind'=>'meal','keyKind'=>'label','externalKey'=>'AI'
    ])['externalKey']==='AI','pure-raw-key');
    $digest=hash('sha256','hotel:fixture');
    $exact=AnyTourHotelStayCatalogV2::offerScope('tourvisor',101,$digest,'Pegas Touristik');
    $same=AnyTourHotelStayCatalogV2::offerScope('tourvisor',101,$digest,'Pegas Touristik');
    $case=AnyTourHotelStayCatalogV2::offerScope('tourvisor',101,$digest,'PEGAS TOURISTIK');
    $otherHotel=AnyTourHotelStayCatalogV2::offerScope('tourvisor',101,hash('sha256','hotel:other'),'Pegas Touristik');
    hs_check($exact===$same,'pure-offer-scope-stable');
    hs_check($exact['namespace']==='anytour_local_id' && $exact['hotelKey']==='101','pure-offer-scope-alias');
    hs_check($exact['operatorKey']!==$case['operatorKey'],'pure-operator-case-exact');
    hs_check($exact['operatorKey']!==$otherHotel['operatorKey'],'pure-provider-hotel-exact');
    hs_check(AnyTourHotelStayCatalogV2::offerScope('tourvisor',101,$digest,null)===null,'pure-missing-operator');
    echo "ANYTOUR_HOTEL_STAY_V2_PURE_OK\n";
    exit(0);
}

$db=new PDO($dsn,(string)getenv('ANYTOUR_HOTEL_STAY_V2_TEST_USER'),(string)getenv('ANYTOUR_HOTEL_STAY_V2_TEST_PASSWORD'),[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES=>false,
    PDO::ATTR_STRINGIFY_FETCHES=>false,
]);

hs_sql($db,__DIR__.'/../v2/data/migrations/20260916-anytour-canonical-catalog.sql');
hs_sql($db,__DIR__.'/../v2/data/migrations/20260918-anytour-hotel-stay-v2.sql');

$profile=json_encode(['name'=>'Hotel A','country'=>'Test'],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
$insert=$db->prepare(
    'INSERT INTO anytour_hotels(profile_json,profile_sha256,revision,is_active,created_at,updated_at)
     VALUES(?,?,1,1,UTC_TIMESTAMP(),UTC_TIMESTAMP())'
);
$insert->execute([$profile,hash('sha256',$profile)]);
$hotelA=(int)$db->lastInsertId();
$profileB=json_encode(['name'=>'Hotel B','country'=>'Test'],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
$insert->execute([$profileB,hash('sha256',$profileB)]);
$hotelB=(int)$db->lastInsertId();
hs_source($db,$hotelA,'hotel-A');
hs_source($db,$hotelB,'hotel-B');
hs_alias($db,$hotelA,101);
hs_alias($db,$hotelB,202);

$catalog=new AnyTourHotelStayCatalogV2($db);
hs_check($catalog->readable(),'readable');

$db->beginTransaction();
$roomA=$catalog->createConcept('room',$hotelA,'standard','Стандарт у бассейна',['view'=>'pool']);
$roomB=$catalog->createConcept('room',$hotelB,'standard','Стандарт с террасой',['view'=>'garden']);
$mealA=$catalog->createConcept('meal',$hotelA,'ai','Всё включено',[
    'concept'=>'hotel-a-ai','included'=>['breakfast','lunch','dinner'],'alcohol'=>'local'
]);
$mealB=$catalog->createConcept('meal',$hotelB,'ai','Всё включено',[
    'concept'=>'hotel-b-ai','included'=>['breakfast','lunch','dinner','snacks'],'alcohol'=>'none'
]);
$db->commit();

hs_check($roomA!==$roomB && $mealA!==$mealB,'ids-independent');
$aMeals=$catalog->meals($hotelA);
$bMeals=$catalog->meals($hotelB);
hs_check(count($aMeals)===1 && count($bMeals)===1,'meal-counts');
hs_check($aMeals[0]['hotelId']===$hotelA && $bMeals[0]['hotelId']===$hotelB,'meal-hotel-scope');
hs_check($aMeals[0]['nameRu']==='Всё включено' && $bMeals[0]['nameRu']==='Всё включено','same-label-allowed');
hs_check($aMeals[0]['facts']['concept']==='hotel-a-ai' && $bMeals[0]['facts']['concept']==='hotel-b-ai','same-label-different-concepts');
hs_check($catalog->rooms($hotelA)[0]['facts']['view']==='pool','room-a-facts');
hs_check($catalog->rooms($hotelB)[0]['facts']['view']==='garden','room-b-facts');
$batchRooms=$catalog->roomsForHotels([$hotelB,$hotelA,$hotelA]);
$batchMeals=$catalog->mealsForHotels([$hotelA,$hotelB]);
hs_check(count($batchRooms)==2 && count($batchRooms[$hotelA])===1 && count($batchRooms[$hotelB])===1,'batch-rooms');
hs_check(count($batchMeals)==2 && $batchMeals[$hotelA][0]['facts']['concept']==='hotel-a-ai'
    && $batchMeals[$hotelB][0]['facts']['concept']==='hotel-b-ai','batch-meals');
hs_expect(fn()=> $catalog->mealsForHotels(array_fill(0,1001,$hotelA)),'HOTEL_STAY_V2_HOTEL_BATCH','batch-limit');

$scopeA=['namespace'=>'provider_ref_digest:test','hotelKey'=>'hotel-A','operatorKey'=>'op-5'];
$mealRef=['kind'=>'meal','keyKind'=>'label','externalKey'=>'AI'];
$roomRef=['kind'=>'room','keyKind'=>'label','externalKey'=>'STANDARD ROOM'];
$ev=['ref'=>'review://fixture/A','sha256'=>hash('sha256','fixture-A'),'reviewedBy'=>'fixture'];

$db->beginTransaction();
$catalog->recordDecision($scopeA,$mealRef,$hotelA,'accepted',$mealA,$ev);
$catalog->recordDecision($scopeA,$roomRef,$hotelA,'accepted',$roomA,$ev);
$db->commit();

$resolved=$catalog->resolve($scopeA,[$mealRef,$roomRef]);
hs_check($resolved['hotelId']===$hotelA,'resolved-hotel');
hs_check($resolved['items'][0]['status']==='accepted','meal-accepted');
hs_check($resolved['items'][0]['canonical']['hotelId']===$hotelA,'meal-target-same-hotel');
hs_check($resolved['items'][0]['canonical']['facts']['concept']==='hotel-a-ai','meal-concept-exact');
hs_check($resolved['items'][1]['canonical']['localKey']==='standard','room-exact');

$providerDigest=hash('sha256','tourvisor-hotel-A');
$offerScope=AnyTourHotelStayCatalogV2::offerScope('tourvisor',101,$providerDigest,'Pegas Touristik');
$db->beginTransaction();
$catalog->recordDecision($offerScope,$mealRef,$hotelA,'accepted',$mealA,[
    'ref'=>'review://offer/A/meal','sha256'=>hash('sha256','offer-A-meal'),'reviewedBy'=>'fixture'
]);
$catalog->recordDecision($offerScope,$roomRef,$hotelA,'accepted',$roomA,[
    'ref'=>'review://offer/A/room','sha256'=>hash('sha256','offer-A-room'),'reviewedBy'=>'fixture'
]);
$db->commit();

$exactBatch=$catalog->resolveOfferFactsBatch([[
    'anytourHotelId'=>$hotelA,'legacyHotelId'=>101,'provider'=>'tourvisor',
    'providerHotelRefDigest'=>$providerDigest,'operatorRaw'=>'Pegas Touristik',
    'roomRaw'=>'STANDARD ROOM','mealRaw'=>'AI',
]]);
hs_check(count($exactBatch)===1 && $exactBatch[0]['exactScope']===true,'offer-batch-exact-scope');
hs_check($exactBatch[0]['room']['status']==='accepted'
    && $exactBatch[0]['room']['canonical']['localKey']==='standard','offer-room-exact');
hs_check($exactBatch[0]['meal']['status']==='accepted'
    && $exactBatch[0]['meal']['canonical']['facts']['concept']==='hotel-a-ai','offer-meal-exact');

$mismatches=$catalog->resolveOfferFactsBatch([
    [
        'anytourHotelId'=>$hotelA,'legacyHotelId'=>101,'provider'=>'tourvisor',
        'providerHotelRefDigest'=>$providerDigest,'operatorRaw'=>'PEGAS TOURISTIK',
        'roomRaw'=>'STANDARD ROOM','mealRaw'=>'AI',
    ],
    [
        'anytourHotelId'=>$hotelA,'legacyHotelId'=>101,'provider'=>'tourvisor',
        'providerHotelRefDigest'=>hash('sha256','tourvisor-hotel-A-other'),'operatorRaw'=>'Pegas Touristik',
        'roomRaw'=>'STANDARD ROOM','mealRaw'=>'AI',
    ],
    [
        'anytourHotelId'=>$hotelA,'legacyHotelId'=>101,'provider'=>'tourvisor',
        'providerHotelRefDigest'=>$providerDigest,'operatorRaw'=>null,
        'roomRaw'=>'STANDARD ROOM','mealRaw'=>'AI',
    ],
]);
hs_check($mismatches[0]['room']['status']==='unmapped' && $mismatches[0]['meal']['status']==='unmapped','operator-case-no-borrow');
hs_check($mismatches[1]['room']['status']==='unmapped' && $mismatches[1]['meal']['status']==='unmapped','provider-hotel-no-borrow');
hs_check($mismatches[2]['exactScope']===false && $mismatches[2]['reason']==='operator-unresolved','missing-operator-fail-closed');
hs_expect(
    fn()=> $catalog->resolveOfferFactsBatch(array_fill(0,1001,[
        'anytourHotelId'=>$hotelA,'legacyHotelId'=>101,'provider'=>'tourvisor',
        'providerHotelRefDigest'=>$providerDigest,'operatorRaw'=>'Pegas Touristik',
        'roomRaw'=>'STANDARD ROOM','mealRaw'=>'AI',
    ])),
    'HOTEL_STAY_V2_OFFER_BATCH','offer-batch-limit'
);

$db->beginTransaction();
hs_expect(
    fn()=> $catalog->recordDecision(
        ['namespace'=>'provider_ref_digest:test','hotelKey'=>'hotel-A','operatorKey'=>'op-other'],
        ['kind'=>'meal','keyKind'=>'code','externalKey'=>'AI'],
        $hotelA,'accepted',$mealB,$ev
    ),
    'HOTEL_STAY_V2_CROSS_HOTEL_TARGET','class-cross-hotel-block'
);
$db->rollBack();

$direct=$db->prepare(
    "INSERT INTO anytour_hotel_stay_mappings_v2
    (namespace,external_hotel_key,operator_key,kind,key_kind,external_key,anytour_hotel_id,room_concept_id,meal_concept_id,state,evidence_ref,evidence_sha256,reviewed_by,created_at)
    VALUES ('provider_ref_digest:test','hotel-A','op-direct','meal','label','AI-DIRECT',?,NULL,?,'accepted','review://direct',?,'fixture',UTC_TIMESTAMP())"
);
hs_expect(
    fn()=> $direct->execute([$hotelA,$mealB,hash('sha256','direct')]),
    'foreign key','db-cross-hotel-fk'
);

$unresolved=$catalog->resolve(
    ['namespace'=>'provider_ref_digest:test','hotelKey'=>'missing-hotel','operatorKey'=>'op-5'],
    [$mealRef]
);
hs_check($unresolved['hotelId']===null && $unresolved['items'][0]['status']==='hotel-unresolved','unresolved-fail-closed');

$rawMeal=['kind'=>'meal','keyKind'=>'label','externalKey'=>'AI PREMIUM / 24H'];
$unmapped=$catalog->resolve($scopeA,[$rawMeal]);
hs_check($unmapped['items'][0]['reference']['externalKey']==='AI PREMIUM / 24H','raw-preserved');
hs_check($unmapped['items'][0]['status']==='unmapped' && $unmapped['items'][0]['canonical']===null,'no-label-guess');

$source=file_get_contents(__DIR__.'/../v2/data/anytour-hotel-stay-catalog-v2.php');
hs_check(is_string($source),'source-readable');
foreach (['anytour_meal_plans','anytour_room_categories','anytour_stay_mappings'] as $legacy) {
    hs_check(!str_contains($source,$legacy),'no-global-authority-'.$legacy);
}

echo "ANYTOUR_HOTEL_STAY_V2_OK hotel_scoped_rooms=1 hotel_scoped_meals=1 exact_offer_mapping=1 cross_hotel_blocked=1 raw_preserved=1\n";
