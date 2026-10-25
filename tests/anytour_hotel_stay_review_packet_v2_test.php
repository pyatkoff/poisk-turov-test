<?php
declare(strict_types=1);
require_once __DIR__.'/../scripts/catalog/anytour_hotel_stay_review_packet_v2.php';

$checks=0;
function packet_check(bool $ok,string $label): void
{
    global $checks;++$checks;
    if (!$ok) throw new RuntimeException('CHECK_FAILED:'.$label);
}
function packet_expect(callable $fn,string $needle,string $label): void
{
    try { $fn(); }
    catch (Throwable $error) {
        packet_check(str_contains($error->getMessage(),$needle),$label.':wrong:'.$error->getMessage());
        return;
    }
    throw new RuntimeException('CHECK_FAILED:'.$label.':no_error');
}
function packet_concept(string $kind,int $id,int $hotelId,string $key,string $name,array $facts): array
{
    return [
        'kind'=>$kind,'id'=>$id,'hotelId'=>$hotelId,'localKey'=>$key,
        'nameRu'=>$name,'facts'=>$facts,'revision'=>1,
    ];
}

$digestA=hash('sha256','hotel-A');
$digestB=hash('sha256','hotel-B');
$inventory=[
    'status'=>'read_only_current_offer_review_inventory',
    'source'=>'anytour-current-complete-offer-snapshots',
    'generatedAt'=>'2026-09-18T14:30:00Z',
    'writes'=>0,'supplierCalls'=>0,'automaticAccepts'=>0,
    'cohorts'=>[
        [
            'hotelId'=>11,'legacyHotelId'=>101,'provider'=>'tourvisor',
            'providerHotelRefDigest'=>$digestA,'operatorRaw'=>'Pegas Touristik',
            'roomRaw'=>'DELUXE ROOM','mealRaw'=>'AI','observedCount'=>9,'lastSeenAt'=>'2026-09-18 14:29:00',
            'reviewState'=>'needs-review',
            'match'=>[
                'room'=>['status'=>'unmapped','canonical'=>null],
                'meal'=>['status'=>'accepted','canonical'=>['localKey'=>'a-ai']],
            ],
        ],
        [
            'hotelId'=>11,'legacyHotelId'=>101,'provider'=>'tourvisor',
            'providerHotelRefDigest'=>$digestA,'operatorRaw'=>'Pegas Touristik',
            'roomRaw'=>'STANDARD ROOM','mealRaw'=>'UAI','observedCount'=>4,'lastSeenAt'=>'2026-09-18 14:28:00',
            'reviewState'=>'needs-review',
            'match'=>[
                'room'=>['status'=>'pending','canonical'=>null],
                'meal'=>['status'=>'unmapped','canonical'=>null],
            ],
        ],
        [
            'hotelId'=>22,'legacyHotelId'=>202,'provider'=>'anex',
            'providerHotelRefDigest'=>$digestB,'operatorRaw'=>'ANEX',
            'roomRaw'=>'STANDARD ROOM','mealRaw'=>'AI','observedCount'=>3,'lastSeenAt'=>'2026-09-18 14:27:00',
            'reviewState'=>'held',
            'match'=>[
                'room'=>['status'=>'conflict','canonical'=>null],
                'meal'=>['status'=>'unmapped','canonical'=>null],
            ],
        ],
        [
            'hotelId'=>22,'legacyHotelId'=>202,'provider'=>'anex',
            'providerHotelRefDigest'=>$digestB,'operatorRaw'=>'ANEX',
            'roomRaw'=>'FAMILY','mealRaw'=>'AI','observedCount'=>2,'lastSeenAt'=>'2026-09-18 14:26:00',
            'reviewState'=>'resolved-or-missing',
            'match'=>[
                'room'=>['status'=>'accepted','canonical'=>['localKey'=>'b-family']],
                'meal'=>['status'=>'accepted','canonical'=>['localKey'=>'b-ai']],
            ],
        ],
    ],
    'hotelConcepts'=>[
        [
            'hotelId'=>11,
            'rooms'=>[
                packet_concept('room',2,11,'a-standard','Стандарт',['view'=>'garden']),
                packet_concept('room',3,11,'a-deluxe','Делюкс',['view'=>'sea']),
            ],
            'meals'=>[
                packet_concept('meal',7,11,'a-ai','Всё включено',['concept'=>'A-AI']),
                packet_concept('meal',8,11,'a-uai','Ультра всё включено',['concept'=>'A-UAI']),
            ],
        ],
        [
            'hotelId'=>22,
            'rooms'=>[
                packet_concept('room',5,22,'b-family','Семейный',['bedrooms'=>2]),
            ],
            'meals'=>[
                packet_concept('meal',9,22,'b-ai','Всё включено B',['concept'=>'B-AI']),
            ],
        ],
    ],
];

$packet1=AnyTourHotelStayReviewPacketV2::build($inventory,1);
$packet2=AnyTourHotelStayReviewPacketV2::build($inventory,1);
packet_check($packet1===$packet2,'packet deterministic');
packet_check($packet1['source']==='anytour-hotel-stay-review-packet-v2','packet source');
packet_check($packet1['actionableItemCount']===2&&$packet1['blockedItemCount']===1,'two actionable one pending blocked');
packet_check(count($packet1['batches'])===2&&count($packet1['batches'][0]['items'])===1,'batch size respected');
packet_check($packet1['rankedChoices']===false&&$packet1['automaticDecisions']===0,'no ranking or auto decisions');
$allItems=[];
foreach ($packet1['batches'] as $batch) foreach ($batch['items'] as $item) $allItems[$item['kind'].':'.$item['raw']]=$item;
$room=$allItems['room:DELUXE ROOM']??null;
$meal=$allItems['meal:UAI']??null;
packet_check(is_array($room)&&$room['currentStatus']==='unmapped'&&$room['actionable']===true,'room actionable');
packet_check(is_array($meal)&&$meal['currentStatus']==='unmapped'&&$meal['actionable']===true,'meal actionable');
packet_check(array_column($room['choices'],'localKey')===['a-deluxe','a-standard'],'choices stable by local key, not score');
packet_check($room['recommendedTarget']===null&&$meal['recommendedTarget']===null,'no recommendation');
$blocked=$packet1['blocked'][0]??null;
packet_check(is_array($blocked)&&$blocked['kind']==='room'&&$blocked['raw']==='STANDARD ROOM','pending kept blocked');
packet_check(($blocked['blockedReason']??null)==='existing-pending-requires-reconcile','pending reconcile reason');

$review=[
    'schemaVersion'=>1,
    'packetSha256'=>$packet1['packetSha256'],
    'reviewedBy'=>'fixture-reviewer',
    'evidenceRef'=>'review://fixture/batch-1',
    'decisions'=>[
        ['itemId'=>$meal['itemId'],'state'=>'rejected','targetLocalKey'=>null],
        ['itemId'=>$room['itemId'],'state'=>'accepted','targetLocalKey'=>'a-deluxe'],
    ],
];
$manifest1=AnyTourHotelStayReviewPacketV2::compile($packet1,$review);
$reviewReversed=$review;
$reviewReversed['decisions']=array_reverse($reviewReversed['decisions']);
$manifest2=AnyTourHotelStayReviewPacketV2::compile($packet1,$reviewReversed);
packet_check($manifest1===$manifest2,'compile independent of response order');
packet_check($manifest1['schemaVersion']===1&&count($manifest1['decisions'])===2,'valid importer shape');
packet_check(preg_match('/^[0-9a-f]{64}$/D',$manifest1['reviewBatchId'])===1,'review batch digest');
$byKind=[];foreach($manifest1['decisions'] as $decision)$byKind[$decision['kind']]=$decision;
packet_check(($byKind['room']['state']??null)==='accepted'&&($byKind['room']['targetLocalKey']??null)==='a-deluxe','accepted target copied');
packet_check(($byKind['meal']['state']??null)==='rejected'&&array_key_exists('targetLocalKey',$byKind['meal'])&&$byKind['meal']['targetLocalKey']===null,'negative has no target');
packet_check(($byKind['room']['provider']??null)==='tourvisor'
    &&($byKind['room']['providerHotelRefDigest']??null)===$digestA
    &&($byKind['room']['operatorRaw']??null)==='Pegas Touristik'
    &&($byKind['room']['raw']??null)==='DELUXE ROOM','exact facts come from packet');
packet_check(($byKind['room']['evidenceSha256']??null)===($byKind['meal']['evidenceSha256']??null)
    &&preg_match('/^[0-9a-f]{64}$/D',$byKind['room']['evidenceSha256'])===1,'review evidence bound');
packet_check(($byKind['room']['reviewedBy']??null)==='fixture-reviewer'
    &&($byKind['room']['evidenceRef']??null)==='review://fixture/batch-1','review provenance copied');

$badTarget=$review;
$badTarget['decisions'][1]['targetLocalKey']='b-family';
packet_expect(
    fn()=>AnyTourHotelStayReviewPacketV2::compile($packet1,$badTarget),
    'HOTEL_STAY_REVIEW_COMPILE_TARGET_NOT_IN_HOTEL','cross-hotel target blocked'
);
$negativeTarget=$review;
$negativeTarget['decisions'][0]['targetLocalKey']='a-uai';
packet_expect(
    fn()=>AnyTourHotelStayReviewPacketV2::compile($packet1,$negativeTarget),
    'HOTEL_STAY_REVIEW_COMPILE_TARGET','negative target blocked'
);
$incomplete=$review;
array_pop($incomplete['decisions']);
packet_expect(
    fn()=>AnyTourHotelStayReviewPacketV2::compile($packet1,$incomplete),
    'HOTEL_STAY_REVIEW_COMPILE_INCOMPLETE','incomplete review blocked'
);
$unknown=$review;
$unknown['decisions'][0]['itemId']=$blocked['itemId'];
packet_expect(
    fn()=>AnyTourHotelStayReviewPacketV2::compile($packet1,$unknown),
    'HOTEL_STAY_REVIEW_COMPILE_UNKNOWN_OR_DUPLICATE','blocked pending cannot be compiled'
);
$tampered=$packet1;
$tampered['batches'][0]['items'][0]['raw']='MUTATED';
packet_expect(
    fn()=>AnyTourHotelStayReviewPacketV2::compile($tampered,$review),
    'HOTEL_STAY_REVIEW_COMPILE_PACKET_INTEGRITY','packet tamper blocked'
);
$invalidInventory=$inventory;
$invalidInventory['automaticAccepts']=1;
packet_expect(
    fn()=>AnyTourHotelStayReviewPacketV2::build($invalidInventory,10),
    'HOTEL_STAY_REVIEW_PACKET_INVENTORY','automatic inventory rejected'
);
packet_expect(
    fn()=>AnyTourHotelStayReviewPacketV2::build($inventory,251),
    'HOTEL_STAY_REVIEW_PACKET_BATCH_SIZE','oversized batch blocked'
);

// The saved-offer collector emits distinct room+meal pairs. Their individual
// room/meal references must remain reviewable when that cross-product repeats.
$pairs=$inventory;$pairs['cohorts']=[];
foreach ([
    ['STANDARD ROOM','AI',2,'2026-09-18 14:21:00'],
    ['STANDARD ROOM','UAI',3,'2026-09-18 14:24:00'],
    ['DELUXE ROOM','AI',5,'2026-09-18 14:22:00'],
    ['DELUXE ROOM','UAI',7,'2026-09-18 14:23:00'],
] as [$roomRaw,$mealRaw,$observed,$lastSeen]) {
    $cohort=$inventory['cohorts'][0];
    $cohort['roomRaw']=$roomRaw;$cohort['mealRaw']=$mealRaw;
    $cohort['observedCount']=$observed;$cohort['lastSeenAt']=$lastSeen;
    $cohort['match']=[
        'room'=>['status'=>'unmapped','canonical'=>null],
        'meal'=>['status'=>'unmapped','canonical'=>null],
    ];
    $pairs['cohorts'][]=$cohort;
}
$originalPairs=$pairs;
$pairPacket=AnyTourHotelStayReviewPacketV2::build($pairs,2);
packet_check($pairs===$originalPairs,'pair inventory remains unchanged');
packet_check($pairPacket['actionableItemCount']===4&&$pairPacket['blockedItemCount']===0,'four pairs produce four unique review references');
packet_check(count($pairPacket['batches'])===2,'unique references respect batch size');
$pairItems=[];
foreach ($pairPacket['batches'] as $batch) foreach ($batch['items'] as $item) $pairItems[$item['kind'].':'.$item['raw']]=$item;
foreach ([
    'room:STANDARD ROOM'=>[5,'2026-09-18 14:24:00'],
    'room:DELUXE ROOM'=>[12,'2026-09-18 14:23:00'],
    'meal:AI'=>[7,'2026-09-18 14:22:00'],
    'meal:UAI'=>[10,'2026-09-18 14:24:00'],
] as $key=>[$observed,$lastSeen]) {
    packet_check($pairItems[$key]['observedCount']===$observed&&$pairItems[$key]['lastSeenAt']===$lastSeen,'exact observation aggregate '.$key);
    packet_check($pairItems[$key]['recommendedTarget']===null&&$pairItems[$key]['automaticDecision']===false,'no inferred target '.$key);
}
packet_check($pairItems['room:DELUXE ROOM']['itemId']===$room['itemId'],'existing scoped reference identity remains stable');
$reversedPairs=$pairs;$reversedPairs['cohorts']=array_reverse($pairs['cohorts']);
packet_check(AnyTourHotelStayReviewPacketV2::build($reversedPairs,2)===$pairPacket,'pair aggregation independent of source order');
$pairReview=$review;$pairReview['packetSha256']=$pairPacket['packetSha256'];$pairReview['decisions']=[];
foreach ($pairItems as $item) $pairReview['decisions'][]=['itemId'=>$item['itemId'],'state'=>'rejected','targetLocalKey'=>null];
$pairManifest=AnyTourHotelStayReviewPacketV2::compile($pairPacket,$pairReview);
packet_check(count($pairManifest['decisions'])===4,'one explicit reviewed decision per unique reference compiles');
foreach ($pairManifest['decisions'] as $decision) {
    packet_check($decision['state']==='rejected'&&$decision['targetLocalKey']===null
        &&$decision['anytourHotelId']===11&&$decision['providerHotelRefDigest']===$digestA
        &&$decision['evidenceRef']===$review['evidenceRef'],'compiled decision retains exact scope and explicit negative');
}
$pendingPairs=$pairs;
foreach ($pendingPairs['cohorts'] as &$cohort) {
    $cohort['match']['room']['status']='pending';
    $cohort['match']['meal']['status']='accepted';
}
unset($cohort);
$pendingPacket=AnyTourHotelStayReviewPacketV2::build($pendingPairs);
packet_check($pendingPacket['actionableItemCount']===0&&$pendingPacket['blockedItemCount']===2,'repeated pending rooms stay blocked');
foreach ($pendingPacket['blocked'] as $item) {
    packet_check($item['currentStatus']==='pending'&&$item['actionable']===false
        &&$item['blockedReason']==='existing-pending-requires-reconcile'
        &&$item['observedCount']===$pairItems['room:'.$item['raw']]['observedCount'],'pending aggregates without promotion');
}
$inconsistentPairs=$pairs;$inconsistentPairs['cohorts'][0]['match']['room']['status']='pending';
packet_expect(fn()=>AnyTourHotelStayReviewPacketV2::build($inconsistentPairs),'HOTEL_STAY_REVIEW_PACKET_DUPLICATE_ITEM','inconsistent repeated reference remains blocked');
$duplicatePair=$pairs;$duplicatePair['cohorts'][]=$pairs['cohorts'][0];
packet_expect(fn()=>AnyTourHotelStayReviewPacketV2::build($duplicatePair),'HOTEL_STAY_REVIEW_PACKET_DUPLICATE_ITEM','exact repeated pair cannot inflate counts');
$overflowPairs=$pairs;$overflowPairs['cohorts'][0]['observedCount']=PHP_INT_MAX;
packet_expect(fn()=>AnyTourHotelStayReviewPacketV2::build($overflowPairs),'HOTEL_STAY_REVIEW_PACKET_OBSERVED','observation total cannot overflow to float');
$separateScopes=$pairs;
foreach (['hotel','legacy','provider','digest','operator'] as $scopePart) {
    $cohort=$pairs['cohorts'][0];
    if ($scopePart==='hotel') $cohort['hotelId']=22;
    elseif ($scopePart==='legacy') $cohort['legacyHotelId']=202;
    elseif ($scopePart==='provider') $cohort['provider']='anex';
    elseif ($scopePart==='digest') $cohort['providerHotelRefDigest']=$digestB;
    else $cohort['operatorRaw']='Another operator';
    $separateScopes['cohorts'][]=$cohort;
}
packet_check(AnyTourHotelStayReviewPacketV2::build($separateScopes)['actionableItemCount']===14,'equal raw labels in different exact scopes remain separate');
$qualifiedPairs=$pairs;$cohort=$pairs['cohorts'][0];$cohort['roomRaw']='STANDARD SEA VIEW';$qualifiedPairs['cohorts'][]=$cohort;
$qualifiedPacket=AnyTourHotelStayReviewPacketV2::build($qualifiedPairs);
packet_check($qualifiedPacket['actionableItemCount']===5,'room qualifier keeps a separate review reference');

echo "ANYTOUR_HOTEL_STAY_REVIEW_PACKET_V2_OK checks=$checks repeated_pair_references=4 recommendations=0 auto=0 db=0 supplier=0\n";
