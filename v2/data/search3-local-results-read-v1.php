<?php
/** Isolated DB-first Search3 listing reader. Current offers + first-party AnyTour profiles only. */
declare(strict_types=1);
require_once __DIR__.'/db-v1.php';
require_once __DIR__.'/anytour-offer-store-read-v2.php';
require_once __DIR__.'/anytour-offer-scope-index-v1.php';
require_once __DIR__.'/anytour-canonical-catalog-v1.php';
require_once __DIR__.'/anytour-stay-catalog-v1.php';
require_once __DIR__.'/anytour-hotel-stay-catalog-v2.php';
require_once __DIR__.'/anytour-search-scope-v1.php';
require_once __DIR__.'/anytour-search-meal-catalog-v1.php';
require_once __DIR__.'/price-calendar-core-v1.php';

const SEARCH3_LOCAL_RESULTS_MAX_OFFERS=15000;
const SEARCH3_LOCAL_RESULTS_MAX_HOTELS=5000;

/** Re-prove facts carried by each cached concrete offer before cross-scope reuse. */
function search3_local_cached_offer_matches_scope(array $item,array $scope): bool
{
    $listing=$item['offer']??null;$tour=is_array($listing)?($listing['tour']??null):null;
    if(!is_array($tour))return false;
    $checkin=$tour['checkin']??null;$nights=$tour['nights']??null;$party=$tour['party']??null;
    if(!is_string($checkin)||!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$checkin)
        ||$checkin<$scope['dateFrom']||$checkin>$scope['dateTo'])return false;
    if(!is_int($nights)||$nights<$scope['nightsFrom']||$nights>$scope['nightsTo'])return false;
    if(!is_array($party)||($party['adults']??null)!==$scope['adults'])return false;
    $ages=$party['child_ages']??null;$children=$party['children']??null;
    if(!is_array($ages)||!array_is_list($ages)||!is_int($children)||$children!==count($ages)||$children!==count($scope['childs']))return false;
    foreach($ages as $age)if(!is_int($age))return false;
    sort($ages,SORT_NUMERIC);
    return $ages===$scope['childs'];
}

/** Re-prove the minimum-stars request from the accepted canonical AnyTour profile. */
function search3_local_profile_matches_scope(array $profile,array $scope): bool
{
    $required=$scope['hotelCategory']??'';
    if($required==='')return true;
    if(!is_string($required)||preg_match('/^[1-5]$/D',$required)!==1)return false;
    $category=$profile['category']??null;
    return is_int($category)&&$category>=1&&$category<=5&&$category>=(int)$required;
}

/** Merge bounded stored cohorts; exact records win only for the same provider/offer. */
function search3_local_results_union(array $exact,array $compatible,array $params,int $limit): array
{
    if($limit<1||$limit>SEARCH3_LOCAL_RESULTS_MAX_OFFERS)throw new InvalidArgumentException('ANYTOUR_LOCAL_RESULTS_LIMIT');
    $items=[];$seen=[];$exactProviders=[];$legacyExactProviders=[];
    foreach([$exact,$compatible] as $tier=>$cohort){
        foreach($cohort as $item){
            if(!is_array($item))throw new RuntimeException('ANYTOUR_OFFER_IDENTITY_INTEGRITY');
            if($tier===1&&!search3_local_cached_offer_matches_scope($item,$params))continue;
            $provider=$item['provider']??null;$digest=$item['offer']['identity']['offer_ref_digest']??null;
            if(!in_array($provider,['tourvisor','anex','andromeda'],true)
                ||($digest!==null&&(!is_string($digest)||preg_match('/^[0-9a-f]{64}$/D',$digest)!==1)))throw new RuntimeException('ANYTOUR_OFFER_IDENTITY_INTEGRITY');
            // The store already deduplicates each cohort by its DB offer identity.
            // Legacy display payloads may omit that identity. Keep those exact rows;
            // never guess cross-cohort uniqueness within the same provider.
            if($tier===0){
                $exactProviders[$provider]=true;
                if($digest===null)$legacyExactProviders[$provider]=true;
            }elseif(isset($legacyExactProviders[$provider])||($digest===null&&isset($exactProviders[$provider]))){continue;}
            if($digest!==null){
                $key=$provider.':'.$digest;
                if(isset($seen[$key]))continue;
                $seen[$key]=true;
            }
            $items[]=$item;
            if(count($items)>=$limit)return $items;
        }
    }
    return $items;
}

function search3_local_results_build(PDO $pdo,array $params,DateTimeImmutable $now,int $limit=SEARCH3_LOCAL_RESULTS_MAX_OFFERS): array
{
    if($limit<1||$limit>SEARCH3_LOCAL_RESULTS_MAX_OFFERS)throw new InvalidArgumentException('ANYTOUR_LOCAL_RESULTS_LIMIT');
    if($pdo->inTransaction()||$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql')throw new RuntimeException('Dedicated MySQL connection required');
    $scope=AnyTourSearchScopeV1::fromParams($params);
    $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $pdo->exec('SET TRANSACTION READ ONLY');
    $pdo->beginTransaction();
    try{
        $version=(int)$pdo->query('SELECT schema_version FROM anytour_offer_store_control WHERE singleton_id=1')->fetchColumn();
        if($version!==2)throw new RuntimeException('Unsupported AnyTour offer-store schema');
        $exact=AnyTourOfferStoreReadV2::readScope($pdo,$scope['digest'],$now,$limit);
        if(!hash_equals($scope['digest'],(string)$exact['scopeDigest']))throw new RuntimeException('Offer-store scope mismatch');
        $compatible=[];
        // A nonempty exact provider must not hide other eligible saved offers.
        // Keep each existing store read bounded and cap the final union once.
        if(count($exact['items'])<$limit){
            $digests=AnyTourOfferScopeIndexV1::compatibleDigests($pdo,$scope,$now);
            if($digests!==[])$compatible=AnyTourOfferStoreReadV2::readScopes($pdo,$digests,$now,SEARCH3_LOCAL_RESULTS_MAX_OFFERS)['items'];
        }
        $stored=$exact;
        $stored['items']=search3_local_results_union($exact['items'],$compatible,$scope['params'],$limit);
        $mode=$stored['items']===[]?'none':'exact';$sourceScopes=[];
        foreach($stored['items'] as $item){
            $source=(string)$item['sourceScopeDigest'];$sourceScopes[$source]=true;
            if(!hash_equals($scope['digest'],$source))$mode='compatible';
        }
        $sourceScopes=array_keys($sourceScopes);
        $ids=[];foreach($stored['items'] as $item)$ids[(int)$item['anytourHotelId']]=(int)$item['anytourHotelId'];
        sort($ids,SORT_NUMERIC);
        $profiles=[];$catalog=new AnyTourCanonicalCatalog($pdo);
        foreach(array_chunk(array_values($ids),1000) as $chunk){
            $read=$catalog->read($chunk);
            foreach($read['items'] as $profile)$profiles[(int)$profile['id']]=$profile;
        }
        $groups=[];$withheld=0;$categoryFiltered=0;
        foreach($stored['items'] as $item){
            $own=(int)$item['anytourHotelId'];$profile=$profiles[$own]??null;
            if(!$profile){$withheld++;continue;}
            if(!hash_equals($scope['digest'],(string)$item['sourceScopeDigest'])&&!search3_local_profile_matches_scope($profile,$scope['params'])){$categoryFiltered++;continue;}
            if(!isset($groups[$own]))$groups[$own]=['anytourHotelId'=>$own,'hotel'=>$profile,'offers'=>[],'providers'=>[],'minPrice'=>null];
            $offer=[
                'provider'=>$item['provider'],'legacyHotelId'=>$item['legacyHotelId'],'price'=>$item['price'],'currency'=>$item['currency'],
                'observedAt'=>$item['observedAt'],'lastSeenAt'=>$item['lastSeenAt'],'expiresAt'=>$item['expiresAt'],'listing'=>$item['offer'],
            ];
            if(isset($item['sourceScopeDigest']))$offer['sourceScopeDigest']=$item['sourceScopeDigest'];
            if(($offer['listing']['selection_state']??null)!=='refresh_required'||($offer['listing']['booking_enabled']??null)!==false)throw new RuntimeException('Stored listing gained selection authority');
            $groups[$own]['offers'][]=$offer;$groups[$own]['providers'][$item['provider']]=true;
            $price=(float)$item['price'];if($groups[$own]['minPrice']===null||$price<(float)$groups[$own]['minPrice'])$groups[$own]['minPrice']=$item['price'];
        }
        foreach($groups as &$group){$group['providers']=array_keys($group['providers']);sort($group['providers'],SORT_STRING);usort($group['offers'],static fn($a,$b)=>(float)$a['price']<=>(float)$b['price']?:strcmp($a['provider'],$b['provider']));}unset($group);
        $hotels=array_values($groups);usort($hotels,static fn($a,$b)=>(float)$a['minPrice']<=>(float)$b['minPrice']?:$a['anytourHotelId']<=>$b['anytourHotelId']);
        $eligibleHotelCount=count($hotels);$omittedHotelCount=max(0,$eligibleHotelCount-SEARCH3_LOCAL_RESULTS_MAX_HOTELS);$omittedOfferCount=0;
        if($omittedHotelCount>0){
            foreach(array_slice($hotels,SEARCH3_LOCAL_RESULTS_MAX_HOTELS) as $hotel)$omittedOfferCount+=count($hotel['offers']);
            $hotels=array_slice($hotels,0,SEARCH3_LOCAL_RESULTS_MAX_HOTELS);
        }
        $displayHotelIds=array_map(static fn($hotel)=>(int)$hotel['anytourHotelId'],$hotels);
        $stayV2=new AnyTourHotelStayCatalogV2($pdo);$stayV2Available=$stayV2->readable();
        $roomsByHotel=[];$mealsByHotel=[];$mealPlans=[];$stayMeta=[];
        if($stayV2Available){
            foreach(array_chunk($displayHotelIds,AnyTourHotelStayCatalogV2::HOTEL_BATCH_LIMIT) as $chunk){
                foreach($stayV2->roomsForHotels($chunk) as $hotelId=>$rooms)$roomsByHotel[(int)$hotelId]=$rooms;
                foreach($stayV2->mealsForHotels($chunk) as $hotelId=>$meals)$mealsByHotel[(int)$hotelId]=$meals;
            }
            foreach($hotels as &$hotel){
                $hotelId=(int)$hotel['anytourHotelId'];
                $hotel['stay']=[
                    'source'=>'anytour-hotel-stay-v2',
                    'rooms'=>$roomsByHotel[$hotelId]??[],
                    'meals'=>$mealsByHotel[$hotelId]??[],
                ];
            }unset($hotel);

            $offerCoordinates=[];$offerRequests=[];
            foreach($hotels as $hotelIndex=>&$hotel){
                $hotelId=(int)$hotel['anytourHotelId'];
                foreach($hotel['offers'] as $offerIndex=>&$offer){
                    $listing=$offer['listing']??null;
                    $identity=is_array($listing)?($listing['identity']??null):null;
                    $operator=is_array($listing)?($listing['operator']??null):null;
                    $tour=is_array($listing)?($listing['tour']??null):null;
                    $digest=is_array($identity)?($identity['provider_hotel_ref_digest']??null):null;
                    $roomRaw=is_array($tour)&&is_array($tour['room']??null)?($tour['room']['raw']??null):null;
                    $mealRaw=is_array($tour)&&is_array($tour['meal']??null)?($tour['meal']['raw']??null):null;
                    if(!is_string($digest)||!preg_match('/^[0-9a-f]{64}$/D',$digest)){
                        $offer['stayMatch']=[
                            'source'=>'anytour-hotel-stay-v2','exactScope'=>false,'reason'=>'offer-identity-unresolved',
                            'room'=>['status'=>$roomRaw===null?'missing':'unmapped','canonical'=>null],
                            'meal'=>['status'=>$mealRaw===null?'missing':'unmapped','canonical'=>null],
                        ];
                        continue;
                    }
                    $offerCoordinates[]=[$hotelIndex,$offerIndex];
                    $offerRequests[]=[
                        'anytourHotelId'=>$hotelId,
                        'legacyHotelId'=>(int)$offer['legacyHotelId'],
                        'provider'=>(string)$offer['provider'],
                        'providerHotelRefDigest'=>$digest,
                        'operatorRaw'=>is_array($operator)?($operator['raw']??null):null,
                        'roomRaw'=>$roomRaw,
                        'mealRaw'=>$mealRaw,
                    ];
                }unset($offer);
            }unset($hotel);
            $resolvedOffset=0;
            foreach(array_chunk($offerRequests,AnyTourHotelStayCatalogV2::OFFER_BATCH_LIMIT) as $chunk){
                $resolved=$stayV2->resolveOfferFactsBatch($chunk);
                foreach($resolved as $index=>$match){
                    [$hotelIndex,$offerIndex]=$offerCoordinates[$resolvedOffset+$index];
                    $hotels[$hotelIndex]['offers'][$offerIndex]['stayMatch']=$match;
                }
                $resolvedOffset+=count($chunk);
            }
            if($resolvedOffset!==count($offerCoordinates))throw new RuntimeException('Stay mapping batch mismatch');
            $stayMeta=['source'=>'anytour-hotel-stay-v2','available'=>true,'hotelScoped'=>true,'compatibilityFallback'=>false];
        }else{
            $stayV1=new AnyTourStayCatalog($pdo);$stayV1Available=$stayV1->readable();
            if($stayV1Available){
                foreach(array_chunk($displayHotelIds,AnyTourStayCatalog::HOTEL_BATCH_LIMIT) as $chunk){
                    foreach($stayV1->roomsForHotels($chunk) as $hotelId=>$rooms)$roomsByHotel[(int)$hotelId]=$rooms;
                }
                $mealPlans=$stayV1->meals();
            }
            foreach($hotels as &$hotel)$hotel['stay']=[
                'source'=>'anytour-stay-catalog-v1-compat',
                'rooms'=>$roomsByHotel[(int)$hotel['anytourHotelId']]??[],
            ];unset($hotel);
            $stayMeta=[
                'source'=>'anytour-stay-catalog-v1-compat',
                'available'=>$stayV1Available,
                'hotelScoped'=>false,
                'compatibilityFallback'=>true,
                'mealPlans'=>$mealPlans,
            ];
        }
        $hotels=(new AnyTourSearchMealCatalogV1($pdo))->attachSearchPlans($hotels);
        $providerCounts=[];
        foreach($hotels as $hotel)foreach($hotel['offers'] as $offer)$providerCounts[$offer['provider']]=($providerCounts[$offer['provider']]??0)+1;
        ksort($providerCounts);$pdo->commit();
        return[
            'source'=>'anytour-db-first-results-v1','offerStoreSchemaVersion'=>$version,'scopeVersion'=>$scope['version'],'scopeDigest'=>$scope['digest'],'scope'=>$scope['params'],
            'matchMode'=>$mode,'partial'=>$mode==='compatible','sourceScopeDigests'=>$sourceScopes,
            'generatedAt'=>$now->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
            'hotelCount'=>count($hotels),'eligibleHotelCount'=>$eligibleHotelCount,'offerCount'=>array_sum($providerCounts),'storedOfferCount'=>count($stored['items']),
            'withheldOfferCount'=>$withheld,'categoryFilteredOfferCount'=>$categoryFiltered,
            'omittedHotelCount'=>$omittedHotelCount,'omittedOfferCount'=>$omittedOfferCount,
            'providerOfferCounts'=>(object)$providerCounts,'selectionAuthority'=>false,
            'stayCatalog'=>$stayMeta,'hotels'=>$hotels,
        ];
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
}

function search3_local_calendar_int(mixed $raw,int $min,int $max,string $name): int
{
    if(is_bool($raw)||is_array($raw)||is_object($raw)||$raw===null)throw new InvalidArgumentException('Invalid '.$name);
    $value=filter_var($raw,FILTER_VALIDATE_INT);
    if($value===false||(int)$value<$min||(int)$value>$max)throw new InvalidArgumentException('Invalid '.$name);
    return (int)$value;
}

/** Read-only exact-party calendar through the already-public LOCAL Search3 endpoint. */
function search3_local_price_calendar(PDO $pdo,array $input,?DateTimeImmutable $now=null): array
{
    $allowed=['action','departureId','countryId','regionId','regionIds','dateFrom','dateTo','nightsFrom','nightsTo','adults','childs'];
    foreach(array_keys($input) as $key)if(!in_array($key,$allowed,true))throw new InvalidArgumentException('Invalid price calendar envelope');
    foreach(['departureId','countryId','dateFrom','dateTo','nightsFrom','nightsTo','adults','childs'] as $key)if(!array_key_exists($key,$input))throw new InvalidArgumentException('Invalid price calendar envelope');
    if(($input['action']??null)!=='price_calendar'||!is_array($input['childs']))throw new InvalidArgumentException('Invalid price calendar envelope');

    $departureId=search3_local_calendar_int($input['departureId'],1,1000000,'departureId');
    $countryId=search3_local_calendar_int($input['countryId'],1,1000000,'countryId');
    $legacyRegionId=search3_local_calendar_int($input['regionId']??0,0,1000000,'regionId');
    $regionIds=[];
    if(array_key_exists('regionIds',$input)){
        if(!is_array($input['regionIds'])||count($input['regionIds'])>20)throw new InvalidArgumentException('Invalid regionIds');
        foreach($input['regionIds'] as $rawRegionId)$regionIds[search3_local_calendar_int($rawRegionId,1,1000000,'regionIds')]=true;
        $regionIds=array_keys($regionIds);sort($regionIds,SORT_NUMERIC);
    }
    if($legacyRegionId>0){
        if($regionIds!==[]&&$regionIds!==[$legacyRegionId])throw new InvalidArgumentException('Ambiguous region scope');
        $regionIds=[$legacyRegionId];
    }
    $nightsFrom=search3_local_calendar_int($input['nightsFrom'],1,30,'nightsFrom');
    $nightsTo=search3_local_calendar_int($input['nightsTo'],1,30,'nightsTo');
    if($nightsTo<$nightsFrom)throw new InvalidArgumentException('Invalid nights');
    $party=v2_price_calendar_party($input['adults'],$input['childs']);

    $dateFrom=is_string($input['dateFrom'])?trim($input['dateFrom']):'';
    $dateTo=is_string($input['dateTo'])?trim($input['dateTo']):'';
    $from=v2_price_calendar_date($dateFrom);$to=v2_price_calendar_date($dateTo);
    if($to<$from||((int)$from->diff($to)->format('%a')+1)>31)throw new InvalidArgumentException('Invalid calendar range');
    $clock=$now??new DateTimeImmutable('now',new DateTimeZone('UTC'));
    $clock=$clock->setTimezone(new DateTimeZone('UTC'));
    $today=$clock->setTime(0,0);
    if($from<$today)throw new InvalidArgumentException('dateFrom must not be in the past');

    $regionParams=[];$regionSql='';
    if($regionIds!==[]){
        $placeholders=[];
        foreach($regionIds as $index=>$regionId){$key='region_id_'.$index;$placeholders[]=':'.$key;$regionParams[$key]=$regionId;}
        $regionSql=' AND o.region_id IN ('.implode(',',$placeholders).')';
    }
    $sql="WITH ranked AS (
        SELECT o.*,
               ROW_NUMBER() OVER (
                 PARTITION BY o.departure_id,o.hotel_id,o.departure_date,o.nights,
                              o.adults,o.children_count,o.child_ages_signature,
                              COALESCE(o.meal_id,0),COALESCE(o.room_id,0),COALESCE(o.room_type,''),
                              COALESCE(o.operator_id,0),o.currency
                 ORDER BY o.observed_at DESC,o.id DESC
               ) AS rn
          FROM tour_price_observations o
         WHERE o.observed_at>=:observed_since
           AND o.departure_id=:departure_id
           AND o.country_id=:country_id
           {$regionSql}
           AND o.departure_date BETWEEN :date_from AND :date_to
           AND o.nights BETWEEN :nights_from AND :nights_to
           AND o.adults=:adults
           AND o.children_count=:children_count
           AND o.child_ages_signature=:child_ages_signature
           AND o.price>0 AND o.currency='RUB'
    )
    SELECT departure_date,
           MIN(price) AS min_price,
           COUNT(DISTINCT hotel_id) AS hotel_count,
           COUNT(DISTINCT search_id) AS independent_search_count,
           MAX(observed_at) AS latest_observed_at
      FROM ranked
     WHERE rn=1
     GROUP BY departure_date
     HAVING MIN(price)>0 AND COUNT(DISTINCT hotel_id)>0 AND COUNT(DISTINCT search_id)>0
     ORDER BY departure_date";
    $stmt=$pdo->prepare($sql);
    $params=[
        'observed_since'=>$clock->modify('-72 hours')->format('Y-m-d H:i:s'),
        'departure_id'=>$departureId,'country_id'=>$countryId,
        'date_from'=>$dateFrom,'date_to'=>$dateTo,
        'nights_from'=>$nightsFrom,'nights_to'=>$nightsTo,
        'adults'=>$party['adults'],'children_count'=>$party['childrenCount'],
        'child_ages_signature'=>$party['childAgesSignature'],
    ];
    $params+=$regionParams;
    $stmt->execute($params);
    $calendar=v2_price_calendar_build($stmt->fetchAll(PDO::FETCH_ASSOC)?:[],$dateFrom,$dateTo);
    return $calendar+[
        'ok'=>true,'departureId'=>$departureId,'countryId'=>$countryId,'regionId'=>count($regionIds)===1?$regionIds[0]:null,'regionIds'=>$regionIds,
        'nightsFrom'=>$nightsFrom,'nightsTo'=>$nightsTo,
        'adults'=>$party['adults'],'childrenCount'=>$party['childrenCount'],
        'childAges'=>$party['childAges'],'childAgesSignature'=>$party['childAgesSignature'],
        'currency'=>'RUB','observationWindowHours'=>72,
        'source'=>'latest-known-exact-segments-from-anytour-first-party-observations','cachedPriceIsFinal'=>false,
    ];
}

function search3_local_results_out(array $payload,int $status=200): never
{
    http_response_code($status);header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
    echo json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;
}

if(realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    $directory=realpath(__DIR__);$normalized=is_string($directory)?str_replace('\\','/',$directory):'';
    if($normalized===''||!str_ends_with($normalized,'/_preview/search3-local-candidate/data'))search3_local_results_out(['ok'=>false,'error'=>'Local DB results are isolated to local preview'],403);
    if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){header('Allow: POST');search3_local_results_out(['ok'=>false,'error'=>'Only POST is allowed'],405);}
    if(($_SERVER['HTTP_X_REQUESTED_WITH']??'')!=='AnyTourSearch3')search3_local_results_out(['ok'=>false,'error'=>'Search3 request header required'],403);
    $length=(int)($_SERVER['CONTENT_LENGTH']??0);if($length<2||$length>32768)search3_local_results_out(['ok'=>false,'error'=>'Invalid request size'],400);
    try{
        $raw=file_get_contents('php://input');$input=json_decode((string)$raw,true,64,JSON_THROW_ON_ERROR);
        if(is_array($input)&&($input['action']??null)==='meal_catalog'){
            if(count($input)!==3||!isset($input['provider'],$input['scopeKey'])||!is_string($input['provider'])||!is_string($input['scopeKey']))throw new InvalidArgumentException('Invalid meal catalogue envelope');
            AnyTourSearchMealCatalogV1::scope($input['provider'],$input['scopeKey']);
            $result=(new AnyTourSearchMealCatalogV1(v2_data_db()))->catalogue($input['provider'],$input['scopeKey']);
            search3_local_results_out(['ok'=>true,'data'=>$result]);
        }
        if(is_array($input)&&($input['action']??null)==='price_calendar'){
            $result=search3_local_price_calendar(v2_data_db(),$input);
            search3_local_results_out(['ok'=>true,'data'=>$result]);
        }
        if(!is_array($input)||array_keys($input)!==['params']||!is_array($input['params']))throw new InvalidArgumentException('Invalid request envelope');
        $result=search3_local_results_build(v2_data_db(),$input['params'],new DateTimeImmutable('now',new DateTimeZone('UTC')));
        search3_local_results_out(['ok'=>true,'data'=>$result]);
    }catch(InvalidArgumentException $e){search3_local_results_out(['ok'=>false,'error'=>'Invalid Search3 scope'],400);}
    catch(Throwable $e){error_log('search3-local-results-read-v1: '.$e->getMessage());search3_local_results_out(['ok'=>false,'error'=>'Local results temporarily unavailable'],503);}
}
