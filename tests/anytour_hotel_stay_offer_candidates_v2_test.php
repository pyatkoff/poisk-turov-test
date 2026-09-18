<?php
declare(strict_types=1);

require_once __DIR__.'/../scripts/catalog/anytour_hotel_stay_offer_candidates_v2.php';
require_once __DIR__.'/../v2/data/anytour-offer-store-v1.php';

$checks=0;
function cand_check(bool $ok,string $label): void
{
    global $checks; ++$checks;
    if (!$ok) throw new RuntimeException('CHECK_FAILED:'.$label);
}
function cand_expect(callable $fn,string $needle,string $label): void
{
    try { $fn(); }
    catch (Throwable $e) {
        cand_check(str_contains($e->getMessage(),$needle),$label.':wrong:'.$e->getMessage());
        return;
    }
    throw new RuntimeException('CHECK_FAILED:'.$label.':no_error');
}
function cand_sql(PDO $db,string $path): void
{
    $sql=file_get_contents($path);
    if (!is_string($sql)||$sql==='') throw new RuntimeException('EMPTY_SQL');
    $sql=preg_replace('/^\s*--.*$/m','',$sql) ?? $sql;
    foreach (preg_split('/;\s*(?:\r?\n|$)/',$sql) ?: [] as $statement) {
        $statement=trim($statement);
        if ($statement!=='') $db->exec($statement);
    }
}
function cand_alias(PDO $db,int $hotelId,int $legacyId): void
{
    $legacy=json_encode(['legacy'=>$legacyId],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    $legacySha=hash('sha256',$legacy);
    $db->prepare("INSERT INTO anytour_hotel_sources
        (namespace,external_key,anytour_hotel_id,acquired_via,source_json,source_sha256,first_seen_at,last_seen_at)
        VALUES('legacy_catalog',?,?,'fixture',?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())")
        ->execute([(string)$legacyId,$hotelId,$legacy,$legacySha]);
    $alias=[
        'accepted_local_hotel_id'=>$legacyId,
        'canonical_hotel_id'=>$hotelId,
        'derived_from_namespace'=>'legacy_catalog',
        'derived_from_source_sha256'=>$legacySha,
        'schema_version'=>1,
    ];
    ksort($alias,SORT_STRING);
    $json=json_encode($alias,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    $db->prepare("INSERT INTO anytour_hotel_sources
        (namespace,external_key,anytour_hotel_id,acquired_via,source_json,source_sha256,first_seen_at,last_seen_at)
        VALUES('anytour_local_id',?,?,'canonical_local_alias_v1',?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())")
        ->execute([(string)$legacyId,$hotelId,$json,hash('sha256',$json)]);
}
function cand_dto(
    string $provider,int $legacy,string $offerSalt,string $hotelRef,?string $operatorRaw,
    string $roomRaw,string $mealRaw,int $issued,string $price
): array {
    $verified=$provider==='anex' && $operatorRaw!==null;
    return [
        'schema_version'=>1,'provider'=>$provider,
        'operator'=>[
            'raw'=>$operatorRaw,
            'canonical_name'=>$verified?'ANEX':null,
            'canonical_verified'=>$verified,
            'identity_source'=>$operatorRaw===null?'missing':($verified?'provider_fixed':'raw_label_only'),
            'filter_status'=>$provider==='tourvisor'?'verified':'unsupported',
            'cross_provider_equivalence_verified'=>false,
            'supplier_code_exposed'=>false,
        ],
        'local_hotel_id'=>$legacy,
        'identity'=>[
            'search_ref_digest'=>hash('sha256','search:'.$provider),
            'offer_ref_digest'=>hash('sha256','offer:'.$offerSalt),
            'provider_hotel_ref_digest'=>hash('sha256',$hotelRef),
        ],
        'tour'=>[
            'checkin'=>'2026-10-05','nights'=>7,
            'party'=>['adults'=>2,'children'=>0,'child_ages'=>[]],
            'meal'=>['raw'=>$mealRaw],
            'room'=>['raw'=>$roomRaw],
            'placement'=>['raw'=>'2AD'],
            'availability'=>['hotel'=>['raw'=>'available']],
            'flight_details'=>['state'=>'search_summary_only'],
            'observed_at'=>gmdate('Y-m-d\TH:i:s\Z',$issued),
        ],
        'money'=>['search_price_with_surcharge'=>['amount'=>$price,'currency'=>'RUB']],
        'quote_state'=>'unknown','final_price_verified'=>false,'quote_evidence_digest'=>null,
        'context'=>[
            'generation'=>1,'page'=>1,'issued_at'=>$issued,'expires_at'=>$issued+900,
            'current_context_verified'=>true,
        ],
        'selection_state'=>'disabled','booking_enabled'=>false,
        'finalPriceReady'=>true,'finalPrice'=>$price,'price'=>$price,'currency'=>'RUB',
    ];
}

$dsn=(string)getenv('ANYTOUR_STAY_OFFER_CANDIDATES_V2_TEST_DSN');
if ($dsn==='') throw new RuntimeException('Dedicated disposable MySQL fixture required');
$db=new PDO($dsn,'root',(string)getenv('ANYTOUR_STAY_OFFER_CANDIDATES_V2_TEST_PASSWORD'),[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES=>false,
    PDO::ATTR_STRINGIFY_FETCHES=>false,
]);

cand_sql($db,__DIR__.'/../v2/data/migrations/20260916-anytour-canonical-catalog.sql');
cand_sql($db,__DIR__.'/../v2/data/migrations/20260916-anytour-offer-store.sql');
cand_sql($db,__DIR__.'/../v2/data/migrations/20260917-anytour-offer-store-v2.sql');
cand_sql($db,__DIR__.'/../v2/data/migrations/20260918-anytour-hotel-stay-v2.sql');

$insertHotel=$db->prepare(
    'INSERT INTO anytour_hotels(profile_json,profile_sha256,revision,is_active,created_at,updated_at)
     VALUES(?,?,1,1,UTC_TIMESTAMP(),UTC_TIMESTAMP())'
);
$pA=json_encode(['name'=>'Hotel A'],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
$insertHotel->execute([$pA,hash('sha256',$pA)]);$hotelA=(int)$db->lastInsertId();
$pB=json_encode(['name'=>'Hotel B'],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
$insertHotel->execute([$pB,hash('sha256',$pB)]);$hotelB=(int)$db->lastInsertId();
cand_alias($db,$hotelA,101);
cand_alias($db,$hotelB,202);

$catalog=new AnyTourHotelStayCatalogV2($db);
$db->beginTransaction();
$roomStandard=$catalog->createConcept('room',$hotelA,'a-standard','Стандарт',['view'=>'garden']);
$roomDeluxe=$catalog->createConcept('room',$hotelA,'a-deluxe','Делюкс',['view'=>'sea']);
$mealAi=$catalog->createConcept('meal',$hotelA,'a-ai','Всё включено',['hotelConcept'=>'A-AI']);
$catalog->createConcept('room',$hotelB,'b-standard','Стандарт B',['view'=>'pool']);
$catalog->createConcept('meal',$hotelB,'b-uai','Ультра всё включено B',['hotelConcept'=>'B-UAI']);
$db->commit();

$tvDigest=hash('sha256','tourvisor-hotel-A');
$tvScope=AnyTourHotelStayCatalogV2::offerScope('tourvisor',101,$tvDigest,'Pegas Touristik');
$evidence=['ref'=>'review://candidate-fixture','sha256'=>hash('sha256','candidate-fixture'),'reviewedBy'=>'fixture'];
$db->beginTransaction();
$catalog->recordDecision($tvScope,['kind'=>'room','keyKind'=>'label','externalKey'=>'STANDARD ROOM'],$hotelA,'accepted',$roomStandard,$evidence);
$catalog->recordDecision($tvScope,['kind'=>'meal','keyKind'=>'label','externalKey'=>'AI'],$hotelA,'accepted',$mealAi,$evidence);
$db->commit();

$now=new DateTimeImmutable('2026-09-18T14:00:00Z');
$issued=$now->getTimestamp();
$scope=hash('sha256','candidate-fixture-scope');
$tvToken=AnyTourOfferStoreV1::beginRefresh($db,'tourvisor',$scope,$now);
AnyTourOfferStoreV1::upsertReadyOffer($db,$tvToken,$hotelA,cand_dto(
    'tourvisor',101,'tv-std','tourvisor-hotel-A','Pegas Touristik','STANDARD ROOM','AI',$issued,'120000'
),$now->modify('+2 hours'),$now);
AnyTourOfferStoreV1::upsertReadyOffer($db,$tvToken,$hotelA,cand_dto(
    'tourvisor',101,'tv-deluxe','tourvisor-hotel-A','Pegas Touristik','DELUXE ROOM','AI',$issued,'125000'
),$now->modify('+2 hours'),$now);
AnyTourOfferStoreV1::upsertReadyOffer($db,$tvToken,$hotelA,cand_dto(
    'tourvisor',101,'tv-no-operator','tourvisor-hotel-A-2',null,'STANDARD ROOM','AI',$issued,'121000'
),$now->modify('+2 hours'),$now);
AnyTourOfferStoreV1::completeRefresh($db,$tvToken,$now);

$anexToken=AnyTourOfferStoreV1::beginRefresh($db,'anex',$scope,$now);
AnyTourOfferStoreV1::upsertReadyOffer($db,$anexToken,$hotelB,cand_dto(
    'anex',202,'anex-b','anex-hotel-B','ANEX','STANDARD ROOM','UAI',$issued,'119000'
),$now->modify('+2 hours'),$now);
AnyTourOfferStoreV1::completeRefresh($db,$anexToken,$now);

$before=[
    'offers'=>(int)$db->query('SELECT COUNT(*) FROM anytour_offers')->fetchColumn(),
    'sources'=>(int)$db->query('SELECT COUNT(*) FROM anytour_hotel_sources')->fetchColumn(),
    'mappings'=>(int)$db->query('SELECT COUNT(*) FROM anytour_hotel_stay_mappings_v2')->fetchColumn(),
    'rooms'=>(int)$db->query('SELECT COUNT(*) FROM anytour_hotel_room_concepts_v2')->fetchColumn(),
    'meals'=>(int)$db->query('SELECT COUNT(*) FROM anytour_hotel_meal_concepts_v2')->fetchColumn(),
];

$inventory=(new AnyTourHotelStayOfferCandidatesV2($db))->collect(50,$now);
$after=[
    'offers'=>(int)$db->query('SELECT COUNT(*) FROM anytour_offers')->fetchColumn(),
    'sources'=>(int)$db->query('SELECT COUNT(*) FROM anytour_hotel_sources')->fetchColumn(),
    'mappings'=>(int)$db->query('SELECT COUNT(*) FROM anytour_hotel_stay_mappings_v2')->fetchColumn(),
    'rooms'=>(int)$db->query('SELECT COUNT(*) FROM anytour_hotel_room_concepts_v2')->fetchColumn(),
    'meals'=>(int)$db->query('SELECT COUNT(*) FROM anytour_hotel_meal_concepts_v2')->fetchColumn(),
];

cand_check($before===$after,'inventory is read-only');
cand_check($inventory['status']==='read_only_current_offer_review_inventory','status');
cand_check($inventory['source']==='anytour-current-complete-offer-snapshots','current snapshots only');
cand_check($inventory['writes']===0&&$inventory['supplierCalls']===0&&$inventory['automaticAccepts']===0,'zero side effects');
cand_check($inventory['currentIdentityValidatedOffers']===4,'four current identity-valid saved offers');
cand_check($inventory['omittedMissingOperatorOffers']===1,'missing operator held outside exact review');
cand_check($inventory['totalExactCohorts']===3&&$inventory['returnedCohorts']===3,'three exact cohorts');
cand_check($inventory['reviewNeededCohorts']===2,'two cohorts need mapping review');
cand_check(count($inventory['hotelConcepts'])===2,'both hotel-local concept dictionaries exposed separately');

$byRoom=[];
foreach ($inventory['cohorts'] as $cohort) $byRoom[$cohort['provider'].':'.($cohort['roomRaw']??'NULL')]=$cohort;
$std=$byRoom['tourvisor:STANDARD ROOM']??null;
$deluxe=$byRoom['tourvisor:DELUXE ROOM']??null;
$anex=$byRoom['anex:STANDARD ROOM']??null;
cand_check(is_array($std)&&$std['reviewState']==='resolved-or-missing','accepted exact cohort not requeued');
cand_check(($std['match']['room']['status']??null)==='accepted'
    &&($std['match']['room']['canonical']['localKey']??null)==='a-standard','accepted room retained');
cand_check(($std['match']['meal']['status']??null)==='accepted'
    &&($std['match']['meal']['canonical']['localKey']??null)==='a-ai','accepted meal retained');
cand_check(is_array($deluxe)&&$deluxe['reviewState']==='needs-review','unmapped deluxe queued');
cand_check(($deluxe['match']['room']['status']??null)==='unmapped'
    &&($deluxe['match']['meal']['status']??null)==='accepted','room-only gap explicit');
cand_check(is_array($anex)&&$anex['reviewState']==='needs-review','unmapped anex queued');
cand_check(($anex['match']['room']['status']??null)==='unmapped'
    &&($anex['match']['meal']['status']??null)==='unmapped','both anex stay facts unmapped');
cand_check($inventory['statusCounts']['room']['accepted']===1
    &&$inventory['statusCounts']['room']['unmapped']===2,'room status census');
cand_check($inventory['statusCounts']['meal']['accepted']===2
    &&$inventory['statusCounts']['meal']['unmapped']===1,'meal status census');

$conceptA=array_values(array_filter($inventory['hotelConcepts'],static fn($v)=>$v['hotelId']===$hotelA))[0]??null;
cand_check(is_array($conceptA)&&count($conceptA['rooms'])===2&&count($conceptA['meals'])===1,'hotel A review choices complete');
cand_check(array_column($conceptA['rooms'],'localKey')===['a-standard','a-deluxe'],'review choices are own concepts, not supplier guesses');

cand_expect(fn()=>(new AnyTourHotelStayOfferCandidatesV2($db))->collect(0,$now),'HOTEL_STAY_OFFER_CANDIDATES_LIMIT','zero limit');
cand_expect(fn()=>(new AnyTourHotelStayOfferCandidatesV2($db))->collect(5001,$now),'HOTEL_STAY_OFFER_CANDIDATES_LIMIT','oversized limit');

$db->prepare("DELETE FROM anytour_hotel_sources WHERE namespace='anytour_local_id' AND external_key='202'")->execute();
$revoked=(new AnyTourHotelStayOfferCandidatesV2($db))->collect(50,$now);
cand_check($revoked['currentIdentityValidatedOffers']===3,'revoked hotel identity removes saved offer before review');
cand_check(count(array_filter($revoked['cohorts'],static fn($v)=>$v['provider']==='anex'))===0,'revoked anex cohort hidden');

$db->prepare("UPDATE anytour_offers SET payload_json='{}' WHERE provider='tourvisor' LIMIT 1")->execute();
cand_expect(
    fn()=>(new AnyTourHotelStayOfferCandidatesV2($db))->collect(50,$now),
    'HOTEL_STAY_OFFER_CANDIDATE_PAYLOAD_INTEGRITY',
    'payload tamper fails closed'
);

echo "ANYTOUR_HOTEL_STAY_OFFER_CANDIDATES_V2_OK checks=$checks current_offers=4 exact_cohorts=3 review_needed=2 supplier_calls=0 writes=0\n";
