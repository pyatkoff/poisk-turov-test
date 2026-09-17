<?php
/** Local-candidate-only bridge from already-normalized provider facts into AnyTour offer store. */
declare(strict_types=1);
require_once __DIR__.'/db-v1.php';
require_once __DIR__.'/anytour-offer-store-v1.php';
require_once __DIR__.'/anytour-search-scope-v1.php';

function search3_local_offer_text(mixed $value,int $limit,string $error): string
{
    if(!is_string($value))throw new InvalidArgumentException($error);
    $value=trim($value);
    if(strlen($value)>$limit||!preg_match('//u',$value)||preg_match('/[\x00-\x1f\x7f]/',$value))throw new InvalidArgumentException($error);
    return $value;
}

function search3_local_offer_price(mixed $value): string
{
    if(!is_string($value)||!preg_match('/\A(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?\z/D',$value)||!preg_match('/[1-9]/',$value))throw new InvalidArgumentException('ANYTOUR_LOCAL_OFFER_PRICE');
    $parts=explode('.',$value,2);$fraction=rtrim($parts[1]??'','0');return $parts[0].($fraction===''?'':'.'.$fraction);
}

function search3_local_anex_offer_dto(array $offer,array $scope,int $generation,DateTimeImmutable $now): array
{
    $keys=['legacyHotelId','searchRef','offerRef','checkin','nights','meal','room','placement','price','currency'];
    $got=array_keys($offer);sort($got);$want=$keys;sort($want);if($got!==$want)throw new InvalidArgumentException('ANYTOUR_LOCAL_OFFER_FIELDS');
    $legacy=$offer['legacyHotelId']??null;if(!is_int($legacy)||$legacy<1)throw new InvalidArgumentException('ANYTOUR_LOCAL_OFFER_HOTEL');
    $search=$offer['searchRef']??null;$ref=$offer['offerRef']??null;
    if(!is_string($search)||!preg_match('/\A[a-f0-9]{32}\z/D',$search))throw new InvalidArgumentException('ANYTOUR_LOCAL_OFFER_SEARCH_REF');
    if(!is_string($ref)||!preg_match('/\Aanex_online:[a-f0-9]{64}\z/D',$ref))throw new InvalidArgumentException('ANYTOUR_LOCAL_OFFER_REF');
    $checkin=$offer['checkin']??null;$date=is_string($checkin)?DateTimeImmutable::createFromFormat('!Y-m-d',$checkin,new DateTimeZone('UTC')):false;
    if(!$date||$date->format('Y-m-d')!==$checkin||$checkin<$scope['dateFrom']||$checkin>$scope['dateTo'])throw new InvalidArgumentException('ANYTOUR_LOCAL_OFFER_DATE');
    $nights=$offer['nights']??null;if(!is_int($nights)||$nights<$scope['nightsFrom']||$nights>$scope['nightsTo'])throw new InvalidArgumentException('ANYTOUR_LOCAL_OFFER_NIGHTS');
    if(($offer['currency']??null)!=='RUB')throw new InvalidArgumentException('ANYTOUR_LOCAL_OFFER_CURRENCY');
    $price=search3_local_offer_price($offer['price']??null);
    $meal=search3_local_offer_text($offer['meal']??null,160,'ANYTOUR_LOCAL_OFFER_MEAL');
    $room=search3_local_offer_text($offer['room']??null,300,'ANYTOUR_LOCAL_OFFER_ROOM');
    $placement=search3_local_offer_text($offer['placement']??null,160,'ANYTOUR_LOCAL_OFFER_PLACEMENT');
    $observed=$now->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');$issued=$now->getTimestamp();
    $children=$scope['childs'];
    return[
        'schema_version'=>1,'provider'=>'anex',
        'operator'=>['raw'=>'ANEX','canonical_name'=>'ANEX','canonical_verified'=>true,'identity_source'=>'provider_fixed','filter_status'=>'unsupported','cross_provider_equivalence_verified'=>false,'supplier_code_exposed'=>false],
        // Browser local_id is an accepted legacy_catalog target. The DB bridge below resolves
        // it to the independent AnyTour primary key; unresolved values are never stored.
        'local_hotel_id'=>$legacy,
        'identity'=>[
            'search_ref_digest'=>hash('sha256','anex-search-ref:'.$search),
            'offer_ref_digest'=>hash('sha256','anex-offer-ref:'.$ref),
            // ANEX browser normalization intentionally does not expose supplier hotel codes.
            // Bind persistence to the accepted local bridge rather than inventing one.
            'provider_hotel_ref_digest'=>hash('sha256','anex-accepted-local-bridge:'.$legacy),
        ],
        'tour'=>[
            'checkin'=>$checkin,'nights'=>$nights,
            'party'=>['adults'=>$scope['adults'],'children'=>count($children),'child_ages'=>$children],
            'meal'=>['raw'=>$meal],'room'=>['raw'=>$room],'placement'=>$placement===''?null:['raw'=>$placement],
            'availability'=>['hotel'=>['raw'=>'unknown']],'flight_details'=>['state'=>'search_summary_only'],'observed_at'=>$observed,
        ],
        // No arithmetic happens here: this is the already APD-applied final listing price.
        'money'=>['final_search_price'=>['amount'=>$price,'currency'=>'RUB'],'source'=>'anex_apd_applied'],
        'quote_state'=>'unknown','final_price_verified'=>false,'quote_evidence_digest'=>null,
        'context'=>['generation'=>$generation,'page'=>1,'issued_at'=>$issued,'expires_at'=>$issued+900,'current_context_verified'=>true],
        'selection_state'=>'disabled','booking_enabled'=>false,'finalPriceReady'=>true,'finalPrice'=>$price,'price'=>$price,'currency'=>'RUB',
    ];
}

function search3_local_offer_bridge(PDO $pdo,int $legacy): ?int
{
    $q=$pdo->prepare("SELECT s.anytour_hotel_id FROM anytour_hotel_sources s JOIN anytour_hotels h ON h.id=s.anytour_hotel_id WHERE s.namespace='legacy_catalog' AND s.external_key=:legacy AND h.is_active=1 ORDER BY s.anytour_hotel_id LIMIT 2");
    $q->execute(['legacy'=>(string)$legacy]);$rows=$q->fetchAll(PDO::FETCH_COLUMN);
    return count($rows)===1?(int)$rows[0]:null;
}

function search3_local_offers_write(PDO $pdo,array $input,DateTimeImmutable $now): array
{
    $keys=['provider','generation','params','offers'];$got=array_keys($input);sort($got);$want=$keys;sort($want);
    if($got!==$want||($input['provider']??null)!=='anex'||!is_int($input['generation']??null)||$input['generation']<1||!is_array($input['params']??null)||!is_array($input['offers']??null)||!array_is_list($input['offers'])||count($input['offers'])>5)throw new InvalidArgumentException('ANYTOUR_LOCAL_WRITE_ENVELOPE');
    if($pdo->inTransaction()||$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql')throw new RuntimeException('Dedicated MySQL connection required');
    $scope=AnyTourSearchScopeV1::fromParams($input['params']);$version=(int)$pdo->query('SELECT schema_version FROM anytour_offer_store_control WHERE singleton_id=1')->fetchColumn();
    if($version!==1)throw new RuntimeException('Unsupported AnyTour offer-store schema');
    $prepared=[];$withheld=0;
    foreach($input['offers'] as $offer){
        if(!is_array($offer))throw new InvalidArgumentException('ANYTOUR_LOCAL_OFFER');
        $dto=search3_local_anex_offer_dto($offer,$scope['params'],$input['generation'],$now);$own=search3_local_offer_bridge($pdo,$dto['local_hotel_id']);
        if($own===null){$withheld++;continue;}$prepared[]=['own'=>$own,'dto'=>$dto];
    }
    // A transient provider pass with no safe final-price-ready offer must not destroy the last
    // complete cache. Existing rows expire naturally; successful non-empty refreshes expire unseen rows.
    if($prepared===[])return['provider'=>'anex','scopeDigest'=>$scope['digest'],'stored'=>0,'withheld'=>$withheld,'noop'=>true];
    $token=AnyTourOfferStoreV1::beginRefresh($pdo,'anex',$scope['digest'],$now,300);$stored=0;
    try{
        foreach($prepared as $row){AnyTourOfferStoreV1::upsertReadyOffer($pdo,$token,$row['own'],$row['dto'],$now->modify('+30 minutes'),$now);$stored++;}
        $complete=AnyTourOfferStoreV1::completeRefresh($pdo,$token,$now);
        return['provider'=>'anex','scopeDigest'=>$scope['digest'],'stored'=>$stored,'withheld'=>$withheld,'expiredUnseen'=>$complete['expiredUnseen'],'noop'=>false];
    }catch(Throwable $e){
        try{AnyTourOfferStoreV1::abortRefresh($pdo,$token,$now);}catch(Throwable $ignored){}
        throw $e;
    }
}

function search3_local_offers_write_out(array $payload,int $status=200): never
{
    http_response_code($status);header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
    echo json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;
}

if(realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    $directory=realpath(__DIR__);$normalized=is_string($directory)?str_replace('\\','/',$directory):'';
    if($normalized===''||!str_ends_with($normalized,'/_preview/search3-local-candidate/data'))search3_local_offers_write_out(['ok'=>false,'error'=>'Local offer writes are isolated to local preview'],403);
    if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){header('Allow: POST');search3_local_offers_write_out(['ok'=>false,'error'=>'Only POST is allowed'],405);}
    if(($_SERVER['HTTP_X_REQUESTED_WITH']??'')!=='AnyTourSearch3')search3_local_offers_write_out(['ok'=>false,'error'=>'Search3 request header required'],403);
    $length=(int)($_SERVER['CONTENT_LENGTH']??0);if($length<2||$length>65536)search3_local_offers_write_out(['ok'=>false,'error'=>'Invalid request size'],400);
    try{
        $raw=file_get_contents('php://input');$input=json_decode((string)$raw,true,64,JSON_THROW_ON_ERROR);if(!is_array($input))throw new InvalidArgumentException('Invalid request envelope');
        $result=search3_local_offers_write(v2_data_db(),$input,new DateTimeImmutable('now',new DateTimeZone('UTC')));search3_local_offers_write_out(['ok'=>true,'data'=>$result]);
    }catch(InvalidArgumentException|DomainException $e){search3_local_offers_write_out(['ok'=>false,'error'=>'Invalid or unresolved LOCAL offer facts'],400);}
    catch(Throwable $e){error_log('search3-local-offers-write-v1: '.$e->getMessage());search3_local_offers_write_out(['ok'=>false,'error'=>'Local offer persistence temporarily unavailable'],503);}
}
