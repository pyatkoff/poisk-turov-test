<?php
/**
 * Offline review packet + reviewed-manifest compiler for AnyTour hotel-local stay V2.
 * No DB, supplier, mapping write, similarity rank, recommendation or automatic decision.
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

final class AnyTourHotelStayReviewPacketV2
{
    public const MAX_BATCH_SIZE = 250;
    private const PROVIDERS = ['tourvisor'=>true,'anex'=>true,'andromeda'=>true];
    private const REVIEW_STATES = ['accepted'=>true,'rejected'=>true,'conflict'=>true];

    private static function exactKeys(array $value,array $expected): bool
    {
        return count($value)===count($expected)
            && array_diff($expected,array_keys($value))===[]
            && array_diff(array_keys($value),$expected)===[];
    }

    private static function id(mixed $value,string $error): int
    {
        if ((!is_int($value)&&!is_string($value))
            || !preg_match('/^[1-9][0-9]*$/D',(string)$value)
            || filter_var($value,FILTER_VALIDATE_INT)===false) {
            throw new InvalidArgumentException($error);
        }
        return (int)$value;
    }

    private static function text(mixed $value,int $limit,string $error): string
    {
        if (!is_string($value)||trim($value)===''||strlen($value)>$limit
            || !preg_match('//u',$value)||preg_match('/[\x00-\x1f\x7f]/',$value)) {
            throw new InvalidArgumentException($error);
        }
        return $value;
    }

    private static function digest(mixed $value,string $error): string
    {
        if (!is_string($value)||!preg_match('/^[0-9a-f]{64}$/D',$value)) {
            throw new InvalidArgumentException($error);
        }
        return $value;
    }

    private static function canon(mixed $value): mixed
    {
        if (!is_array($value)) return $value;
        $isList=$value===[]||array_keys($value)===range(0,count($value)-1);
        if ($isList) return array_map([self::class,'canon'],$value);
        ksort($value,SORT_STRING);
        foreach ($value as $key=>$item) $value[$key]=self::canon($item);
        return $value;
    }

    private static function json(mixed $value): string
    {
        return json_encode(
            self::canon($value),
            JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR
        );
    }

    private static function itemId(
        int $hotelId,
        int $legacyId,
        string $provider,
        string $providerHotelRefDigest,
        string $operatorRaw,
        string $kind,
        string $raw
    ): string {
        return hash('sha256',self::json([
            $hotelId,$legacyId,$provider,$providerHotelRefDigest,$operatorRaw,$kind,$raw,
        ]));
    }

    private static function conceptChoices(array $inventory): array
    {
        $rows=$inventory['hotelConcepts']??null;
        if (!is_array($rows)||!array_is_list($rows)) {
            throw new InvalidArgumentException('HOTEL_STAY_REVIEW_PACKET_CONCEPTS');
        }
        $map=[];
        foreach ($rows as $hotel) {
            if (!is_array($hotel)) throw new InvalidArgumentException('HOTEL_STAY_REVIEW_PACKET_CONCEPTS');
            $hotelId=self::id($hotel['hotelId']??null,'HOTEL_STAY_REVIEW_PACKET_HOTEL');
            if (isset($map[$hotelId])) throw new InvalidArgumentException('HOTEL_STAY_REVIEW_PACKET_DUPLICATE_HOTEL');
            $map[$hotelId]=['room'=>[],'meal'=>[]];
            foreach (['room'=>'rooms','meal'=>'meals'] as $kind=>$key) {
                $concepts=$hotel[$key]??null;
                if (!is_array($concepts)||!array_is_list($concepts)) {
                    throw new InvalidArgumentException('HOTEL_STAY_REVIEW_PACKET_CONCEPTS');
                }
                foreach ($concepts as $concept) {
                    if (!is_array($concept)) throw new InvalidArgumentException('HOTEL_STAY_REVIEW_PACKET_CONCEPT');
                    $id=self::id($concept['id']??null,'HOTEL_STAY_REVIEW_PACKET_CONCEPT_ID');
                    $conceptHotel=self::id($concept['hotelId']??null,'HOTEL_STAY_REVIEW_PACKET_CONCEPT_HOTEL');
                    if ($conceptHotel!==$hotelId||($concept['kind']??null)!==$kind) {
                        throw new InvalidArgumentException('HOTEL_STAY_REVIEW_PACKET_CONCEPT_SCOPE');
                    }
                    $localKey=self::text($concept['localKey']??null,128,'HOTEL_STAY_REVIEW_PACKET_LOCAL_KEY');
                    $nameRu=self::text($concept['nameRu']??null,255,'HOTEL_STAY_REVIEW_PACKET_NAME');
                    $revision=self::id($concept['revision']??null,'HOTEL_STAY_REVIEW_PACKET_REVISION');
                    if (!is_array($concept['facts']??null)) {
                        throw new InvalidArgumentException('HOTEL_STAY_REVIEW_PACKET_FACTS');
                    }
                    $map[$hotelId][$kind][]=[
                        'id'=>$id,'localKey'=>$localKey,'nameRu'=>$nameRu,
                        'facts'=>$concept['facts'],'revision'=>$revision,
                    ];
                }
                usort($map[$hotelId][$kind],static fn(array $a,array $b)=>
                    strcmp($a['localKey'],$b['localKey'])?:$a['id']<=>$b['id']
                );
            }
        }
        return $map;
    }

    private static function cohortBase(array $cohort): array
    {
        $hotelId=self::id($cohort['hotelId']??null,'HOTEL_STAY_REVIEW_PACKET_HOTEL');
        $legacyId=self::id($cohort['legacyHotelId']??null,'HOTEL_STAY_REVIEW_PACKET_LEGACY');
        $provider=$cohort['provider']??null;
        if (!is_string($provider)||!isset(self::PROVIDERS[$provider])) {
            throw new InvalidArgumentException('HOTEL_STAY_REVIEW_PACKET_PROVIDER');
        }
        $digest=self::digest(
            $cohort['providerHotelRefDigest']??null,
            'HOTEL_STAY_REVIEW_PACKET_PROVIDER_HOTEL'
        );
        $operator=self::text($cohort['operatorRaw']??null,240,'HOTEL_STAY_REVIEW_PACKET_OPERATOR');
        $observed=$cohort['observedCount']??null;
        if (!is_int($observed)||$observed<1) {
            throw new InvalidArgumentException('HOTEL_STAY_REVIEW_PACKET_OBSERVED');
        }
        $lastSeen=self::text($cohort['lastSeenAt']??null,64,'HOTEL_STAY_REVIEW_PACKET_LAST_SEEN');
        return [
            'hotelId'=>$hotelId,'legacyHotelId'=>$legacyId,'provider'=>$provider,
            'providerHotelRefDigest'=>$digest,'operatorRaw'=>$operator,
            'observedCount'=>$observed,'lastSeenAt'=>$lastSeen,
        ];
    }

    private static function reviewItem(
        array $base,
        string $kind,
        string $raw,
        string $status,
        array $choices,
        bool $actionable
    ): array {
        $itemId=self::itemId(
            $base['hotelId'],$base['legacyHotelId'],$base['provider'],
            $base['providerHotelRefDigest'],$base['operatorRaw'],$kind,$raw
        );
        $item=[
            'itemId'=>$itemId,
            'anytourHotelId'=>$base['hotelId'],
            'legacyHotelId'=>$base['legacyHotelId'],
            'provider'=>$base['provider'],
            'providerHotelRefDigest'=>$base['providerHotelRefDigest'],
            'operatorRaw'=>$base['operatorRaw'],
            'kind'=>$kind,
            'raw'=>$raw,
            'currentStatus'=>$status,
            'observedCount'=>$base['observedCount'],
            'lastSeenAt'=>$base['lastSeenAt'],
            'actionable'=>$actionable,
            'choices'=>$choices,
            'recommendedTarget'=>null,
            'automaticDecision'=>false,
        ];
        if (!$actionable) $item['blockedReason']='existing-pending-requires-reconcile';
        return $item;
    }

    public static function build(array $inventory,int $batchSize=100): array
    {
        if ($batchSize<1||$batchSize>self::MAX_BATCH_SIZE) {
            throw new InvalidArgumentException('HOTEL_STAY_REVIEW_PACKET_BATCH_SIZE');
        }
        if (($inventory['status']??null)!=='read_only_current_offer_review_inventory'
            ||($inventory['source']??null)!=='anytour-current-complete-offer-snapshots'
            ||($inventory['writes']??null)!==0
            ||($inventory['supplierCalls']??null)!==0
            ||($inventory['automaticAccepts']??null)!==0) {
            throw new InvalidArgumentException('HOTEL_STAY_REVIEW_PACKET_INVENTORY');
        }
        $generatedAt=self::text(
            $inventory['generatedAt']??null,64,'HOTEL_STAY_REVIEW_PACKET_GENERATED_AT'
        );
        $concepts=self::conceptChoices($inventory);
        $cohorts=$inventory['cohorts']??null;
        if (!is_array($cohorts)||!array_is_list($cohorts)) {
            throw new InvalidArgumentException('HOTEL_STAY_REVIEW_PACKET_COHORTS');
        }

        $items=[];$blocked=[];$seen=[];
        foreach ($cohorts as $cohort) {
            if (!is_array($cohort)||($cohort['reviewState']??null)!=='needs-review') continue;
            $base=self::cohortBase($cohort);
            $hotelChoices=$concepts[$base['hotelId']]??['room'=>[],'meal'=>[]];
            $match=$cohort['match']??null;
            if (!is_array($match)) throw new InvalidArgumentException('HOTEL_STAY_REVIEW_PACKET_MATCH');
            foreach (['room'=>'roomRaw','meal'=>'mealRaw'] as $kind=>$rawKey) {
                $part=$match[$kind]??null;
                if (!is_array($part)) throw new InvalidArgumentException('HOTEL_STAY_REVIEW_PACKET_MATCH');
                $status=$part['status']??null;
                if (!in_array($status,['unmapped','pending'],true)) continue;
                $raw=self::text($cohort[$rawKey]??null,512,'HOTEL_STAY_REVIEW_PACKET_RAW');
                $actionable=$status==='unmapped';
                $item=self::reviewItem(
                    $base,$kind,$raw,$status,$hotelChoices[$kind]??[],$actionable
                );
                if (isset($seen[$item['itemId']])) {
                    throw new InvalidArgumentException('HOTEL_STAY_REVIEW_PACKET_DUPLICATE_ITEM');
                }
                $seen[$item['itemId']]=true;
                if ($actionable) $items[]=$item; else $blocked[]=$item;
            }
        }
        usort($items,static fn(array $a,array $b)=>strcmp($a['itemId'],$b['itemId']));
        usort($blocked,static fn(array $a,array $b)=>strcmp($a['itemId'],$b['itemId']));

        $batches=[];
        foreach (array_chunk($items,$batchSize) as $index=>$chunk) {
            $batches[]=['batchIndex'=>$index+1,'items'=>$chunk];
        }
        $body=[
            'schemaVersion'=>1,
            'source'=>'anytour-hotel-stay-review-packet-v2',
            'inventorySource'=>$inventory['source'],
            'inventoryGeneratedAt'=>$generatedAt,
            'batchSize'=>$batchSize,
            'actionableItemCount'=>count($items),
            'blockedItemCount'=>count($blocked),
            'batches'=>$batches,
            'blocked'=>$blocked,
            'rankedChoices'=>false,
            'automaticDecisions'=>0,
        ];
        return $body+['packetSha256'=>hash('sha256',self::json($body))];
    }

    private static function packetBody(array $packet): array
    {
        if (($packet['schemaVersion']??null)!==1
            ||($packet['source']??null)!=='anytour-hotel-stay-review-packet-v2') {
            throw new InvalidArgumentException('HOTEL_STAY_REVIEW_COMPILE_PACKET');
        }
        $sha=self::digest($packet['packetSha256']??null,'HOTEL_STAY_REVIEW_COMPILE_PACKET_SHA');
        $body=$packet;unset($body['packetSha256']);
        if (!hash_equals($sha,hash('sha256',self::json($body)))) {
            throw new InvalidArgumentException('HOTEL_STAY_REVIEW_COMPILE_PACKET_INTEGRITY');
        }
        if (($body['rankedChoices']??null)!==false||($body['automaticDecisions']??null)!==0) {
            throw new InvalidArgumentException('HOTEL_STAY_REVIEW_COMPILE_PACKET');
        }
        return $body;
    }

    private static function actionableItems(array $packetBody): array
    {
        $batches=$packetBody['batches']??null;
        if (!is_array($batches)||!array_is_list($batches)) {
            throw new InvalidArgumentException('HOTEL_STAY_REVIEW_COMPILE_BATCHES');
        }
        $items=[];
        foreach ($batches as $batch) {
            if (!is_array($batch)||!is_array($batch['items']??null)||!array_is_list($batch['items'])) {
                throw new InvalidArgumentException('HOTEL_STAY_REVIEW_COMPILE_BATCHES');
            }
            foreach ($batch['items'] as $item) {
                if (!is_array($item)||($item['actionable']??null)!==true) {
                    throw new InvalidArgumentException('HOTEL_STAY_REVIEW_COMPILE_ITEM');
                }
                $id=self::digest($item['itemId']??null,'HOTEL_STAY_REVIEW_COMPILE_ITEM_ID');
                if (isset($items[$id])) throw new InvalidArgumentException('HOTEL_STAY_REVIEW_COMPILE_DUPLICATE_ITEM');
                $items[$id]=$item;
            }
        }
        if (count($items)!==($packetBody['actionableItemCount']??null)) {
            throw new InvalidArgumentException('HOTEL_STAY_REVIEW_COMPILE_ITEM_COUNT');
        }
        return $items;
    }

    public static function compile(array $packet,array $review): array
    {
        $body=self::packetBody($packet);
        $packetSha=$packet['packetSha256'];
        $items=self::actionableItems($body);
        if ($items===[]) throw new InvalidArgumentException('HOTEL_STAY_REVIEW_COMPILE_EMPTY');

        $expected=['schemaVersion','packetSha256','reviewedBy','evidenceRef','decisions'];
        if (!self::exactKeys($review,$expected)||($review['schemaVersion']??null)!==1
            ||($review['packetSha256']??null)!==$packetSha) {
            throw new InvalidArgumentException('HOTEL_STAY_REVIEW_COMPILE_REVIEW');
        }
        $reviewedBy=self::text($review['reviewedBy']??null,128,'HOTEL_STAY_REVIEW_COMPILE_REVIEWER');
        $evidenceRef=self::text($review['evidenceRef']??null,255,'HOTEL_STAY_REVIEW_COMPILE_EVIDENCE');
        $decisions=$review['decisions']??null;
        if (!is_array($decisions)||!array_is_list($decisions)) {
            throw new InvalidArgumentException('HOTEL_STAY_REVIEW_COMPILE_DECISIONS');
        }

        $responses=[];
        foreach ($decisions as $decision) {
            if (!is_array($decision)
                ||!self::exactKeys($decision,['itemId','state','targetLocalKey'])) {
                throw new InvalidArgumentException('HOTEL_STAY_REVIEW_COMPILE_DECISION');
            }
            $itemId=self::digest($decision['itemId']??null,'HOTEL_STAY_REVIEW_COMPILE_ITEM_ID');
            if (!isset($items[$itemId])||isset($responses[$itemId])) {
                throw new InvalidArgumentException('HOTEL_STAY_REVIEW_COMPILE_UNKNOWN_OR_DUPLICATE');
            }
            $state=$decision['state']??null;
            if (!is_string($state)||!isset(self::REVIEW_STATES[$state])) {
                throw new InvalidArgumentException('HOTEL_STAY_REVIEW_COMPILE_STATE');
            }
            $target=$decision['targetLocalKey']??null;
            if ($state==='accepted') {
                $target=self::text($target,128,'HOTEL_STAY_REVIEW_COMPILE_TARGET');
                $allowed=[];
                foreach ($items[$itemId]['choices']??[] as $choice) {
                    if (is_array($choice)&&is_string($choice['localKey']??null)) {
                        $allowed[$choice['localKey']]=true;
                    }
                }
                if (!isset($allowed[$target])) {
                    throw new InvalidArgumentException('HOTEL_STAY_REVIEW_COMPILE_TARGET_NOT_IN_HOTEL');
                }
            } elseif ($target!==null) {
                throw new InvalidArgumentException('HOTEL_STAY_REVIEW_COMPILE_TARGET');
            }
            $responses[$itemId]=[
                'itemId'=>$itemId,'state'=>$state,'targetLocalKey'=>$target,
            ];
        }
        if (count($responses)!==count($items)) {
            throw new InvalidArgumentException('HOTEL_STAY_REVIEW_COMPILE_INCOMPLETE');
        }
        ksort($responses,SORT_STRING);
        $responseList=array_values($responses);
        $evidenceBody=[
            'schemaVersion'=>1,'packetSha256'=>$packetSha,'reviewedBy'=>$reviewedBy,
            'evidenceRef'=>$evidenceRef,'decisions'=>$responseList,
        ];
        $evidenceSha=hash('sha256',self::json($evidenceBody));
        $reviewBatchId=hash('sha256',self::json([
            'packetSha256'=>$packetSha,'evidenceSha256'=>$evidenceSha,
        ]));

        $manifest=[];
        foreach ($responseList as $response) {
            $item=$items[$response['itemId']];
            $manifest[]=[
                'anytourHotelId'=>$item['anytourHotelId'],
                'legacyHotelId'=>$item['legacyHotelId'],
                'provider'=>$item['provider'],
                'providerHotelRefDigest'=>$item['providerHotelRefDigest'],
                'operatorRaw'=>$item['operatorRaw'],
                'kind'=>$item['kind'],
                'raw'=>$item['raw'],
                'state'=>$response['state'],
                'targetLocalKey'=>$response['targetLocalKey'],
                'evidenceRef'=>$evidenceRef,
                'evidenceSha256'=>$evidenceSha,
                'reviewedBy'=>$reviewedBy,
            ];
        }
        return [
            'schemaVersion'=>1,
            'reviewBatchId'=>$reviewBatchId,
            'decisions'=>$manifest,
        ];
    }
}

function stay_review_packet_read_json(string $path): array
{
    if ($path===''||!is_file($path)) throw new InvalidArgumentException('HOTEL_STAY_REVIEW_FILE');
    $data=json_decode((string)file_get_contents($path),true,128,JSON_THROW_ON_ERROR);
    if (!is_array($data)) throw new InvalidArgumentException('HOTEL_STAY_REVIEW_JSON');
    return $data;
}

if (realpath((string)($_SERVER['SCRIPT_FILENAME']??''))===__FILE__) {
    $mode=null;$inventoryPath=null;$packetPath=null;$reviewPath=null;$batchSize=100;
    foreach (array_slice($argv,1) as $arg) {
        if ($arg==='--mode=packet') $mode='packet';
        elseif ($arg==='--mode=compile') $mode='compile';
        elseif (str_starts_with($arg,'--inventory=')) $inventoryPath=substr($arg,12);
        elseif (str_starts_with($arg,'--packet=')) $packetPath=substr($arg,9);
        elseif (str_starts_with($arg,'--review=')) $reviewPath=substr($arg,9);
        elseif (preg_match('/^--batch-size=([0-9]+)$/D',$arg,$m)) $batchSize=(int)$m[1];
        else throw new InvalidArgumentException(
            'Usage: --mode=packet --inventory=FILE [--batch-size=1..250] | --mode=compile --packet=FILE --review=FILE'
        );
    }
    $result=match($mode) {
        'packet'=>AnyTourHotelStayReviewPacketV2::build(
            stay_review_packet_read_json((string)$inventoryPath),$batchSize
        ),
        'compile'=>AnyTourHotelStayReviewPacketV2::compile(
            stay_review_packet_read_json((string)$packetPath),
            stay_review_packet_read_json((string)$reviewPath)
        ),
        default=>throw new InvalidArgumentException('HOTEL_STAY_REVIEW_MODE'),
    };
    echo json_encode(
        $result,
        JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR
    ).PHP_EOL;
}
