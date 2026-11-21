<?php
declare(strict_types=1);
require_once __DIR__.'/../scripts/catalog/anytour_hotel_stay_anchor_read_v1.php';
require_once __DIR__.'/../v2/data/anytour-offer-store-v1.php';

$checks=0;
function anchor_check(bool $ok,string $label): void {
    global $checks;++$checks;if (!$ok) throw new RuntimeException('ANCHOR_CHECK_FAILED:'.$label);
}
function anchor_sql(PDO $db,string $path): void {
    $sql=(string)file_get_contents($path);$sql=preg_replace('/^\s*--.*$/m','',$sql)??$sql;
    foreach (preg_split('/;\s*(?:\r?\n|$)/',$sql)?:[] as $statement) {
        if (trim($statement)!=='') $db->exec(trim($statement));
    }
}
function anchor_hotel(PDO $db,string $name,int $legacy): int {
    $json=json_encode(['name'=>$name],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    $db->prepare('INSERT INTO anytour_hotels(profile_json,profile_sha256,revision,is_active,created_at,updated_at)
        VALUES(?,?,1,1,UTC_TIMESTAMP(),UTC_TIMESTAMP())')->execute([$json,hash('sha256',$json)]);
    $id=(int)$db->lastInsertId();$seed=json_encode(['legacy'=>$legacy],JSON_THROW_ON_ERROR);
    $db->prepare("INSERT INTO anytour_hotel_sources(namespace,external_key,anytour_hotel_id,acquired_via,
        source_json,source_sha256,first_seen_at,last_seen_at) VALUES('legacy_catalog',?,?,'fixture',?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())")
        ->execute([(string)$legacy,$id,$seed,hash('sha256',$seed)]);
    $alias=['accepted_local_hotel_id'=>$legacy,'canonical_hotel_id'=>$id,'derived_from_namespace'=>'legacy_catalog',
        'derived_from_source_sha256'=>hash('sha256',$seed),'schema_version'=>1];ksort($alias,SORT_STRING);
    $bytes=json_encode($alias,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    $db->prepare("INSERT INTO anytour_hotel_sources(namespace,external_key,anytour_hotel_id,acquired_via,
        source_json,source_sha256,first_seen_at,last_seen_at) VALUES('anytour_local_id',?,?,'canonical_local_alias_v1',?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())")
        ->execute([(string)$legacy,$id,$bytes,hash('sha256',$bytes)]);
    return $id;
}
function anchor_dto(int $legacy,string $tour,int $issued,string $room='standard land view',string $meal='AI - Все Включено'): array {
    return ['schema_version'=>1,'provider'=>'tourvisor','operator'=>['raw'=>'FUN&SUN','canonical_name'=>null,
        'canonical_verified'=>false,'identity_source'=>'raw_label_only','filter_status'=>'verified',
        'cross_provider_equivalence_verified'=>false,'supplier_code_exposed'=>false],
        'local_hotel_id'=>$legacy,'identity'=>['search_ref_digest'=>hash('sha256','fixture-search:'.$tour),
            'offer_ref_digest'=>hash('sha256','tourvisor:tour:'.$tour),
            'provider_hotel_ref_digest'=>hash('sha256','tourvisor:hotel:'.$legacy)],
        'tour'=>['checkin'=>'2026-11-27','nights'=>7,'party'=>['adults'=>2,'children'=>0,'child_ages'=>[]],
            'meal'=>['raw'=>$meal],'room'=>['raw'=>$room],'placement'=>['raw'=>'2AD'],
            'availability'=>['hotel'=>['raw'=>'available']],'flight_details'=>['state'=>'search_summary_only'],
            'observed_at'=>gmdate('Y-m-d\TH:i:s\Z',$issued)],
        'money'=>['search_price'=>['amount'=>'129258','currency'=>'RUB']],
        'quote_state'=>'unknown','final_price_verified'=>false,'quote_evidence_digest'=>null,
        'context'=>['generation'=>1,'page'=>1,'issued_at'=>$issued,'expires_at'=>$issued+900,'current_context_verified'=>true],
        'selection_state'=>'disabled','booking_enabled'=>false,'finalPriceReady'=>false,'finalPrice'=>null,
        'price'=>'129258','currency'=>'RUB'];
}
function anchor_offer(PDO $db,int $hotel,int $legacy,string $tour,string $scope,DateTimeImmutable $seen,
    bool $complete=true,string $room='standard land view',string $meal='AI - Все Включено'): void {
    $token=AnyTourOfferStoreV1::beginRefresh($db,'tourvisor',hash('sha256',$scope),$seen);
    AnyTourOfferStoreV1::upsertReadyOffer($db,$token,$hotel,anchor_dto($legacy,$tour,$seen->getTimestamp(),$room,$meal),
        $seen->modify('+2 hours'),$seen);
    if ($complete) AnyTourOfferStoreV1::completeRefresh($db,$token,$seen);
}
function anchor_state(PDO $db,DateTimeImmutable $now,string $state): array {
    $result=AnyTourHotelStayAnchorReadV1::read($db,$now);
    anchor_check($result['receipt']['state']===$state,'state '.$state.' actual '.$result['receipt']['state']);
    anchor_check($result['receipt']['supplierCalls']===0&&$result['receipt']['databaseWrites']===0
        &&$result['receipt']['automaticAccepts']===0,'no side effects '.$state);
    anchor_check(!$db->inTransaction(),'dedicated transaction closed '.$state);
    return $result;
}
function anchor_fingerprint(PDO $db): string {
    $rows=[];
    foreach (['anytour_hotels','anytour_hotel_sources','anytour_offers','anytour_offer_scope_state',
        'anytour_offer_refreshes','anytour_hotel_room_concepts_v2','anytour_hotel_meal_concepts_v2','anytour_hotel_stay_mappings_v2'] as $table) {
        $all=$db->query('SELECT * FROM '.$table)->fetchAll(PDO::FETCH_ASSOC);
        $encoded=array_map(static fn($v)=>AnyTourHotelStayAnchorReadV1::json($v),$all);sort($encoded,SORT_STRING);$rows[$table]=$encoded;
    }
    return hash('sha256',AnyTourHotelStayAnchorReadV1::json($rows));
}
/** Deliberately malformed surplus fixture rows must be rejected by the cap before payload selection. */
function anchor_surplus(PDO $db,int $template,string $scope,int $count): void {
    if ($count<1||$count>15001) throw new RuntimeException('FIXTURE_SURPLUS_LIMIT');
    $columns=array_column($db->query('SHOW COLUMNS FROM anytour_offers')->fetchAll(PDO::FETCH_ASSOC),'Field');
    $columns=array_values(array_filter($columns,static fn($name)=>$name!=='id'));
    $sequence='(a.n+10*b.n+100*c.n+1000*d.n+10000*e.n)';
    $expressions=[];
    foreach ($columns as $column) {
        $expressions[]=$column==='scope_sha256'?':scope':($column==='identity_sha256'
            ?"SHA2(CONCAT('surplus-',o.id,'-',".$sequence.'),256)':'o.`'.$column.'`');
    }
    $digits='(SELECT 0 n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4
        UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9)';
    $query='INSERT INTO anytour_offers (`'.implode('`,`',$columns).'`) SELECT '.implode(',',$expressions)
        .' FROM anytour_offers o CROSS JOIN '.$digits.' a CROSS JOIN '.$digits.' b CROSS JOIN '.$digits.' c CROSS JOIN '
        .$digits.' d CROSS JOIN '.$digits.' e WHERE o.id=:id LIMIT '.$count;
    $db->prepare($query)->execute(['scope'=>$scope,'id'=>$template]);
}

$dsn=(string)getenv('ANYTOUR_STAY_ANCHOR_V1_TEST_DSN');
if (!preg_match('/^mysql:host=127\.0\.0\.1;port=[0-9]+;charset=utf8mb4$/D',$dsn)) {
    throw new RuntimeException('Dedicated loopback MySQL fixture DSN required');
}
$db=new PDO($dsn,'root',(string)getenv('ANYTOUR_STAY_OFFER_CANDIDATES_V2_TEST_PASSWORD'),[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_STRINGIFY_FETCHES=>false]);
$db->exec('CREATE DATABASE anytour_stay_anchor_v1_fixture CHARACTER SET utf8mb4');
$db->exec('USE anytour_stay_anchor_v1_fixture');
$now=new DateTimeImmutable('2026-10-25T17:00:00Z');
$missing=anchor_state($db,$now,'schema_missing');
anchor_check(count($missing['receipt']['missingTables'])===7,'all required missing tables actionable');
anchor_check($missing['inventory']===null&&$missing['reviewPacket']===null,'missing schema has no invented data');
foreach (['20260916-anytour-canonical-catalog.sql','20260916-anytour-offer-store.sql',
    '20260917-anytour-offer-store-v2.sql','20260918-anytour-hotel-stay-v2.sql'] as $migration) {
    anchor_sql($db,__DIR__.'/../v2/data/migrations/'.$migration);
}
anchor_state($db,$now,'anchor_missing');
$a=anchor_hotel($db,'FICTIONAL FIRST CLASS anchor fixture',101);$b=anchor_hotel($db,'Different fixture hotel',202);
$catalog=new AnyTourHotelStayCatalogV2($db);$db->beginTransaction();
$room=$catalog->createConcept('room',$a,'standard-land','Подтверждённый номер',['view'=>'land']);
$meal=$catalog->createConcept('meal',$a,'hotel-ai','Программа питания отеля',['hotelConcept'=>'fixture-ai']);
$scope=AnyTourHotelStayCatalogV2::offerScope('tourvisor',101,hash('sha256','tourvisor:hotel:101'),'FUN&SUN');
$evidence=['ref'=>'fixture:explicit-review','sha256'=>hash('sha256','fixture-review'),'reviewedBy'=>'fixture'];
$catalog->recordDecision($scope,['kind'=>'room','keyKind'=>'label','externalKey'=>'standard land view'],$a,'accepted',$room,$evidence);
$catalog->recordDecision($scope,['kind'=>'meal','keyKind'=>'label','externalKey'=>'AI - Все Включено'],$a,'accepted',$meal,$evidence);
$db->commit();
anchor_offer($db,$a,101,AnyTourHotelStayAnchorReadV1::TOUR_ID,'expired-anchor',$now->modify('-5 days'));
$empty=anchor_state($db,$now,'no_current_cohorts');
anchor_check($empty['receipt']['anchorExpiredRows']===1&&$empty['inventory']['anchorEvidence']['identityOnly']===true,'expired anchor is identity only');
anchor_check(count($empty['inventory']['hotelConcepts'])===1
    &&count($empty['inventory']['hotelConcepts'][0]['rooms'])===1,'no current offers still exposes only exact hotel concepts');
anchor_offer($db,$a,101,'new-current','current-a',$now);
anchor_offer($db,$a,101,'new-unmapped','current-unmapped',$now,true,'deluxe land view');
anchor_offer($db,$b,202,'other-hotel','current-b',$now);
anchor_offer($db,$a,101,'incomplete-current','incomplete-a',$now,false,'incomplete room');
anchor_offer($db,$a,101,'expired-current','expired-a',$now->modify('-1 day'),true,'expired room');
$before=anchor_fingerprint($db);$result=anchor_state($db,$now,'completed_read_only');
anchor_check(anchor_fingerprint($db)===$before,'all table bytes unchanged by complete read');
anchor_check($result['receipt']['currentValidatedOffers']===2&&$result['receipt']['returnedCohorts']===2,'only current complete exact hotel rows');
anchor_check(array_unique(array_column($result['inventory']['cohorts'],'hotelId'))===[$a],'separate hotel is never selected');
anchor_check($result['receipt']['reviewItems']===1,'only unmapped raw room needs review');
anchor_check($result['receipt']['inventorySha256']===hash('sha256',AnyTourHotelStayAnchorReadV1::json($result['inventory'])),'inventory receipt binds exact private bytes');
anchor_check($result['receipt']['reviewPacketSha256']===hash('sha256',AnyTourHotelStayAnchorReadV1::json($result['reviewPacket'])),'packet receipt binds exact private bytes');
$accepted=array_values(array_filter($result['inventory']['cohorts'],static fn($c)=>$c['roomRaw']==='standard land view'))[0];
anchor_check($accepted['match']['room']['canonical']['localKey']==='standard-land'
    &&$accepted['match']['meal']['canonical']['localKey']==='hotel-ai','accepted same-hotel concepts retained');

$anchorDigest=hash('sha256','tourvisor:tour:'.AnyTourHotelStayAnchorReadV1::TOUR_ID);
$q=$db->prepare('SELECT id,payload_json,payload_sha256 FROM anytour_offers WHERE offer_ref_digest=?');$q->execute([$anchorDigest]);$anchorRow=$q->fetch();
$db->prepare('UPDATE anytour_offers SET payload_sha256=? WHERE id=?')->execute([str_repeat('0',64),$anchorRow['id']]);
anchor_state($db,$now,'anchor_payload_invalid');
$db->prepare('UPDATE anytour_offers SET payload_sha256=? WHERE id=?')->execute([$anchorRow['payload_sha256'],$anchorRow['id']]);
$changed=json_decode($anchorRow['payload_json'],true,128,JSON_THROW_ON_ERROR);
$changed['identity']['provider_hotel_ref_digest']=hash('sha256','tourvisor:hotel:202');
$bytes=json_encode($changed,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
$db->prepare('UPDATE anytour_offers SET payload_json=?,payload_sha256=? WHERE id=?')->execute([$bytes,hash('sha256',$bytes),$anchorRow['id']]);
anchor_state($db,$now,'anchor_payload_invalid');
$db->prepare('UPDATE anytour_offers SET payload_json=?,payload_sha256=? WHERE id=?')->execute([$anchorRow['payload_json'],$anchorRow['payload_sha256'],$anchorRow['id']]);
anchor_offer($db,$b,202,AnyTourHotelStayAnchorReadV1::TOUR_ID,'conflicting-anchor',$now->modify('-4 days'));
anchor_state($db,$now,'anchor_identity_ambiguous');
$db->prepare('DELETE FROM anytour_offers WHERE offer_ref_digest=? AND anytour_hotel_id=?')->execute([$anchorDigest,$b]);
$db->prepare("UPDATE anytour_hotel_sources SET source_sha256=? WHERE namespace='anytour_local_id' AND external_key='101'")->execute([str_repeat('0',64)]);
anchor_state($db,$now,'anchor_identity_unavailable');
$db->exec("UPDATE anytour_hotel_sources SET source_sha256=SHA2(source_json,256) WHERE namespace='anytour_local_id' AND external_key='101'");
$db->prepare('UPDATE anytour_hotels SET is_active=0 WHERE id=?')->execute([$a]);
anchor_state($db,$now,'anchor_identity_unavailable');
$db->prepare('UPDATE anytour_hotels SET is_active=1 WHERE id=?')->execute([$a]);
$currentDigest=hash('sha256','tourvisor:tour:new-current');
$db->prepare('UPDATE anytour_offers SET payload_sha256=? WHERE offer_ref_digest=?')->execute([str_repeat('0',64),$currentDigest]);
anchor_state($db,$now,'current_payload_invalid');
$db->prepare('UPDATE anytour_offers SET payload_sha256=SHA2(payload_json,256) WHERE offer_ref_digest=?')->execute([$currentDigest]);
$db->exec('RENAME TABLE anytour_hotel_meal_concepts_v2 TO held_meal_concepts');
$missingOne=anchor_state($db,$now,'schema_missing');
anchor_check($missingOne['receipt']['missingTables']===['anytour_hotel_meal_concepts_v2'],'precise one-table missing state');
$db->exec('RENAME TABLE held_meal_concepts TO anytour_hotel_meal_concepts_v2');
anchor_state($db,$now,'completed_read_only');
$surplusScope=hash('sha256','surplus-historical-anchor');
anchor_surplus($db,(int)$anchorRow['id'],$surplusScope,65);
anchor_state($db,$now,'anchor_row_limit');
$db->prepare('DELETE FROM anytour_offers WHERE scope_sha256=?')->execute([$surplusScope]);
anchor_offer($db,$a,101,'cap-current','current-cap',$now);
$capScope=hash('sha256','current-cap');
$q=$db->prepare('SELECT id FROM anytour_offers WHERE scope_sha256=?');$q->execute([$capScope]);$capId=(int)$q->fetchColumn();
anchor_surplus($db,$capId,$capScope,15001);
anchor_state($db,$now,'current_row_limit');
$db->prepare('DELETE FROM anytour_offers WHERE scope_sha256=?')->execute([$capScope]);
anchor_state($db,$now,'completed_read_only');
echo 'HOTEL_STAY_ANCHOR_READ_OK checks='.$checks.' database=NATIVE_MYSQL supplier_calls=0 application_writes=0'.PHP_EOL;
