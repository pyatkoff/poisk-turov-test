<?php
declare(strict_types=1);

require_once __DIR__.'/../scripts/catalog/anytour_hotel_stay_reviewed_import_v2.php';
require_once __DIR__.'/../v2/data/anytour-offer-store-v1.php';

$checks=0;
function imp_check(bool $ok,string $label): void
{
    global $checks;++$checks;
    if (!$ok) throw new RuntimeException('CHECK_FAILED:'.$label);
}
function imp_expect(callable $fn,string $needle,string $label): void
{
    try { $fn(); }
    catch (Throwable $error) {
        imp_check(str_contains($error->getMessage(),$needle),$label.':wrong:'.$error->getMessage());
        return;
    }
    throw new RuntimeException('CHECK_FAILED:'.$label.':no_error');
}
function imp_sql(PDO $db,string $path): void
{
    $sql=file_get_contents($path);
    if (!is_string($sql)||$sql==='') throw new RuntimeException('EMPTY_SQL');
    $sql=preg_replace('/^\s*--.*$/m','',$sql)??$sql;
    foreach (preg_split('/;\s*(?:\r?\n|$)/',$sql)?:[] as $statement) {
        $statement=trim($statement);
        if ($statement!=='') $db->exec($statement);
    }
}
function imp_alias(PDO $db,int $hotelId,int $legacyId): void
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
function imp_regular_dto(int $legacy,int $issued): array
{
    return [
        'schema_version'=>1,'provider'=>'tourvisor',
        'operator'=>[
            'raw'=>'Pegas Touristik','canonical_name'=>null,'canonical_verified'=>false,
            'identity_source'=>'raw_label_only','filter_status'=>'verified',
            'cross_provider_equivalence_verified'=>false,'supplier_code_exposed'=>false,
        ],
        'local_hotel_id'=>$legacy,
        'identity'=>[
            'search_ref_digest'=>hash('sha256','reviewed-import-search'),
            'offer_ref_digest'=>hash('sha256','reviewed-import-offer'),
            'provider_hotel_ref_digest'=>hash('sha256','tourvisor-hotel-reviewed-A'),
        ],
        'tour'=>[
            'checkin'=>'2026-10-05','nights'=>7,
            'party'=>['adults'=>2,'children'=>0,'child_ages'=>[]],
            'meal'=>['raw'=>'UAI'],'room'=>['raw'=>'DELUXE ROOM'],'placement'=>['raw'=>'2AD'],
            'availability'=>['hotel'=>['raw'=>'available']],
            'flight_details'=>['state'=>'search_summary_only'],
            'observed_at'=>gmdate('Y-m-d\TH:i:s\Z',$issued),
        ],
        'money'=>[
            'search_price'=>['amount'=>'126000','currency'=>'RUB'],
            'search_price_with_surcharge'=>['amount'=>'126000','currency'=>'RUB'],
        ],
        'quote_state'=>'unknown','final_price_verified'=>false,'quote_evidence_digest'=>null,
        'context'=>[
            'generation'=>1,'page'=>1,'issued_at'=>$issued,'expires_at'=>$issued+900,
            'current_context_verified'=>true,
        ],
        'selection_state'=>'disabled','booking_enabled'=>false,
        'finalPriceReady'=>false,'finalPrice'=>null,'price'=>'126000','currency'=>'RUB',
    ];
}
function imp_manifest(int $hotelA,string $roomTarget='a-deluxe',string $operator='Pegas Touristik',?string $digest=null): array
{
    $digest??=hash('sha256','tourvisor-hotel-reviewed-A');
    $common=[
        'anytourHotelId'=>$hotelA,'legacyHotelId'=>101,'provider'=>'tourvisor',
        'providerHotelRefDigest'=>$digest,'operatorRaw'=>$operator,
        'evidenceRef'=>'review://batch/fixture','evidenceSha256'=>hash('sha256','review-evidence'),
        'reviewedBy'=>'fixture-reviewer',
    ];
    return [
        'schemaVersion'=>1,
        'reviewBatchId'=>hash('sha256','review-batch-fixture'),
        'decisions'=>[
            $common+[
                'kind'=>'room','raw'=>'DELUXE ROOM','state'=>'accepted',
                'targetLocalKey'=>$roomTarget,
            ],
            $common+[
                'kind'=>'meal','raw'=>'UAI','state'=>'rejected',
                'targetLocalKey'=>null,
            ],
        ],
    ];
}

$dsn=(string)getenv('ANYTOUR_STAY_REVIEWED_IMPORT_V2_TEST_DSN');
if ($dsn==='') throw new RuntimeException('Dedicated disposable MySQL fixture required');
$db=new PDO($dsn,'root',(string)getenv('ANYTOUR_STAY_REVIEWED_IMPORT_V2_TEST_PASSWORD'),[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES=>false,
    PDO::ATTR_STRINGIFY_FETCHES=>false,
]);

imp_sql($db,__DIR__.'/../v2/data/migrations/20260916-anytour-canonical-catalog.sql');
imp_sql($db,__DIR__.'/../v2/data/migrations/20260916-anytour-offer-store.sql');
imp_sql($db,__DIR__.'/../v2/data/migrations/20260917-anytour-offer-store-v2.sql');
imp_sql($db,__DIR__.'/../v2/data/migrations/20260918-anytour-hotel-stay-v2.sql');

$insertHotel=$db->prepare(
    'INSERT INTO anytour_hotels(profile_json,profile_sha256,revision,is_active,created_at,updated_at)
     VALUES(?,?,1,1,UTC_TIMESTAMP(),UTC_TIMESTAMP())'
);
$pA=json_encode(['name'=>'Hotel A'],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
$insertHotel->execute([$pA,hash('sha256',$pA)]);$hotelA=(int)$db->lastInsertId();
$pB=json_encode(['name'=>'Hotel B'],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
$insertHotel->execute([$pB,hash('sha256',$pB)]);$hotelB=(int)$db->lastInsertId();
imp_alias($db,$hotelA,101);imp_alias($db,$hotelB,202);

$catalog=new AnyTourHotelStayCatalogV2($db);
$db->beginTransaction();
$catalog->createConcept('room',$hotelA,'a-standard','Стандарт',['view'=>'garden']);
$roomDeluxe=$catalog->createConcept('room',$hotelA,'a-deluxe','Делюкс',['view'=>'sea']);
$catalog->createConcept('meal',$hotelA,'a-uai','Ультра всё включено',['concept'=>'A-UAI']);
$catalog->createConcept('room',$hotelB,'b-deluxe','Делюкс B',['view'=>'pool']);
$db->commit();

$now=new DateTimeImmutable('2026-09-18T14:20:00Z');
$token=AnyTourOfferStoreV1::beginRefresh($db,'tourvisor',hash('sha256','reviewed-import-scope'),$now);
AnyTourOfferStoreV1::upsertReadyOffer(
    $db,$token,$hotelA,imp_regular_dto(101,$now->getTimestamp()),$now->modify('+2 hours'),$now
);
AnyTourOfferStoreV1::completeRefresh($db,$token,$now);

$manifest=imp_manifest($hotelA);
$importer=new AnyTourHotelStayReviewedImportV2($db);
$before=[
    'offers'=>(int)$db->query('SELECT COUNT(*) FROM anytour_offers')->fetchColumn(),
    'mappings'=>(int)$db->query('SELECT COUNT(*) FROM anytour_hotel_stay_mappings_v2')->fetchColumn(),
    'rooms'=>(int)$db->query('SELECT COUNT(*) FROM anytour_hotel_room_concepts_v2')->fetchColumn(),
    'meals'=>(int)$db->query('SELECT COUNT(*) FROM anytour_hotel_meal_concepts_v2')->fetchColumn(),
];

$plan1=$importer->plan($manifest,$now);
$plan2=$importer->plan($manifest,$now);
$afterPlan=[
    'offers'=>(int)$db->query('SELECT COUNT(*) FROM anytour_offers')->fetchColumn(),
    'mappings'=>(int)$db->query('SELECT COUNT(*) FROM anytour_hotel_stay_mappings_v2')->fetchColumn(),
    'rooms'=>(int)$db->query('SELECT COUNT(*) FROM anytour_hotel_room_concepts_v2')->fetchColumn(),
    'meals'=>(int)$db->query('SELECT COUNT(*) FROM anytour_hotel_meal_concepts_v2')->fetchColumn(),
];
imp_check($before===$afterPlan,'planning is read-only');
imp_check($plan1===$plan2,'two exact read-only plans byte-equivalent as arrays');
imp_check($plan1['planSha256']===$plan2['planSha256'],'two plan hashes identical');
imp_check($plan1['decisionCount']===2&&$plan1['insertCount']===2&&$plan1['existingIdenticalCount']===0,'two new reviewed decisions');
imp_check($plan1['supplierCalls']===0&&$plan1['automaticAccepts']===0&&$plan1['conceptCreates']===0,'plan side effects zero');
$roomPlan=array_values(array_filter($plan1['decisions'],static fn($d)=>$d['kind']==='room'))[0]??null;
imp_check(is_array($roomPlan)&&$roomPlan['targetId']===$roomDeluxe&&$roomPlan['targetLocalKey']==='a-deluxe','exact hotel-local target pinned');

imp_expect(
    fn()=> $importer->plan(imp_manifest($hotelA,'missing-room'),$now),
    'HOTEL_STAY_REVIEWED_IMPORT_TARGET_UNAVAILABLE','missing target blocked'
);
imp_expect(
    fn()=> $importer->plan(imp_manifest($hotelA,'a-deluxe','PEGAS TOURISTIK'),$now),
    'HOTEL_STAY_REVIEWED_IMPORT_CURRENT_OFFER_EVIDENCE','operator drift blocked'
);
imp_expect(
    fn()=> $importer->plan(imp_manifest($hotelA,'a-deluxe','Pegas Touristik',hash('sha256','wrong-hotel-ref')),$now),
    'HOTEL_STAY_REVIEWED_IMPORT_CURRENT_OFFER_EVIDENCE','provider-hotel drift blocked'
);

$alias=$db->query("SELECT source_json,source_sha256 FROM anytour_hotel_sources WHERE namespace='anytour_local_id' AND external_key='101'")->fetch(PDO::FETCH_ASSOC);
$db->prepare("UPDATE anytour_hotel_sources SET source_json='{}',source_sha256=? WHERE namespace='anytour_local_id' AND external_key='101'")
    ->execute([hash('sha256','{}')]);
imp_expect(
    fn()=> $importer->plan($manifest,$now),
    'HOTEL_STAY_REVIEWED_IMPORT_CURRENT_OFFER_EVIDENCE','revoked alias semantics blocked'
);
$db->prepare("UPDATE anytour_hotel_sources SET source_json=?,source_sha256=? WHERE namespace='anytour_local_id' AND external_key='101'")
    ->execute([$alias['source_json'],$alias['source_sha256']]);

$stored=$db->query("SELECT id,payload_json,payload_sha256 FROM anytour_offers WHERE provider='tourvisor' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$db->prepare("UPDATE anytour_offers SET payload_json='{}',payload_sha256=? WHERE id=?")
    ->execute([hash('sha256','{}'),$stored['id']]);
imp_expect(
    fn()=> $importer->plan($manifest,$now),
    'HOTEL_STAY_REVIEWED_IMPORT_PAYLOAD_INTEGRITY','payload tamper blocked'
);
$db->prepare('UPDATE anytour_offers SET payload_json=?,payload_sha256=? WHERE id=?')
    ->execute([$stored['payload_json'],$stored['payload_sha256'],$stored['id']]);

$result=$importer->apply($manifest,$plan1['planSha256'],$now);
if (($result['status']??null)!=='committed_verified') {
    fwrite(STDERR,"REVIEWED_IMPORT_RESULT ".json_encode($result,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n");
}
imp_check($result['status']==='committed_verified','commit verified');
imp_check($result['inserted']===2&&$result['verified']===2&&$result['noReplay']===true,'two exact writes read back');
imp_check($result['supplierCalls']===0&&$result['automaticAccepts']===0&&$result['conceptCreates']===0,'apply side effects bounded');

$afterApply=[
    'offers'=>(int)$db->query('SELECT COUNT(*) FROM anytour_offers')->fetchColumn(),
    'mappings'=>(int)$db->query('SELECT COUNT(*) FROM anytour_hotel_stay_mappings_v2')->fetchColumn(),
    'rooms'=>(int)$db->query('SELECT COUNT(*) FROM anytour_hotel_room_concepts_v2')->fetchColumn(),
    'meals'=>(int)$db->query('SELECT COUNT(*) FROM anytour_hotel_meal_concepts_v2')->fetchColumn(),
];
imp_check($afterApply['offers']===$before['offers'],'offers unchanged');
imp_check($afterApply['rooms']===$before['rooms']&&$afterApply['meals']===$before['meals'],'concepts unchanged');
imp_check($afterApply['mappings']===$before['mappings']+2,'only two reviewed mapping writes');

$post=$importer->plan($manifest,$now);
imp_check($post['insertCount']===0&&$post['existingIdenticalCount']===2,'same reviewed manifest becomes explicit no-op');
imp_expect(
    fn()=> $importer->apply($manifest,$plan1['planSha256'],$now),
    'HOTEL_STAY_REVIEWED_IMPORT_PLAN_DRIFT','old precommit plan cannot replay'
);

$conflict=imp_manifest($hotelA);
$conflict['decisions']=array_values(array_filter($conflict['decisions'],static fn($d)=>$d['kind']==='room'));
$conflict['decisions'][0]['state']='rejected';
$conflict['decisions'][0]['targetLocalKey']=null;
imp_expect(
    fn()=> $importer->plan($conflict,$now),
    'HOTEL_STAY_REVIEWED_IMPORT_DECISION_EXISTS','immutable accepted decision cannot be replaced by negative'
);

$cross=imp_manifest($hotelA,'b-deluxe');
imp_expect(
    fn()=> $importer->plan($cross,$now),
    'HOTEL_STAY_REVIEWED_IMPORT_TARGET_UNAVAILABLE','other hotel local key unavailable'
);

echo "ANYTOUR_HOTEL_STAY_REVIEWED_IMPORT_V2_OK checks=$checks plan_twice=1 confirmation_required_evidence=1 inserted=2 verified=2 replay_blocked=1 supplier_calls=0 auto_accepts=0\n";
