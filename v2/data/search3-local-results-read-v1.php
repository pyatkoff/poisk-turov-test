<?php
/** Isolated DB-first Search3 listing reader. Current offers + first-party AnyTour profiles only. */
declare(strict_types=1);
require_once __DIR__.'/db-v1.php';
require_once __DIR__.'/anytour-offer-store-v1.php';
require_once __DIR__.'/anytour-offer-store-read-v2.php';
require_once __DIR__.'/anytour-canonical-catalog-v1.php';
require_once __DIR__.'/anytour-search-scope-v1.php';

function search3_local_results_build(PDO $pdo,array $params,DateTimeImmutable $now,int $limit=5000): array
{
    if($limit<1||$limit>5000)throw new InvalidArgumentException('ANYTOUR_LOCAL_RESULTS_LIMIT');
    if($pdo->inTransaction()||$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql')throw new RuntimeException('Dedicated MySQL connection required');
    $scope=AnyTourSearchScopeV1::fromParams($params);
    $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $pdo->exec('SET TRANSACTION READ ONLY');
    $pdo->beginTransaction();
    try{
        $version=(int)$pdo->query('SELECT schema_version FROM anytour_offer_store_control WHERE singleton_id=1')->fetchColumn();
        if(!in_array($version,[1,2],true))throw new RuntimeException('Unsupported AnyTour offer-store schema');
        // Transitional compatibility: live v1 is empty before the checked v2 migration.
        // Once v2 is installed, only each provider's latest completed refresh is visible.
        $stored=$version===2?AnyTourOfferStoreReadV2::readScope($pdo,$scope['digest'],$now,$limit):AnyTourOfferStoreV1::readScope($pdo,$scope['digest'],$now,$limit);
        if(!hash_equals($scope['digest'],(string)$stored['scopeDigest']))throw new RuntimeException('Offer-store scope mismatch');
        $ids=[];foreach($stored['items'] as $item)$ids[(int)$item['anytourHotelId']]=(int)$item['anytourHotelId'];
        sort($ids,SORT_NUMERIC);
        $profiles=[];$catalog=new AnyTourCanonicalCatalog($pdo);
        foreach(array_chunk(array_values($ids),1000) as $chunk){
            $read=$catalog->read($chunk);
            foreach($read['items'] as $profile)$profiles[(int)$profile['id']]=$profile;
        }
        $groups=[];$providerCounts=[];$withheld=0;
        foreach($stored['items'] as $item){
            $own=(int)$item['anytourHotelId'];$profile=$profiles[$own]??null;
            if(!$profile){$withheld++;continue;}
            if(!isset($groups[$own]))$groups[$own]=['anytourHotelId'=>$own,'hotel'=>$profile,'offers'=>[],'providers'=>[],'minPrice'=>null];
            $offer=[
                'provider'=>$item['provider'],'legacyHotelId'=>$item['legacyHotelId'],'price'=>$item['price'],'currency'=>$item['currency'],
                'observedAt'=>$item['observedAt'],'lastSeenAt'=>$item['lastSeenAt'],'expiresAt'=>$item['expiresAt'],'listing'=>$item['offer'],
            ];
            if(($offer['listing']['selection_state']??null)!=='refresh_required'||($offer['listing']['booking_enabled']??null)!==false)throw new RuntimeException('Stored listing gained selection authority');
            $groups[$own]['offers'][]=$offer;$groups[$own]['providers'][$item['provider']]=true;
            $price=(float)$item['price'];if($groups[$own]['minPrice']===null||$price<(float)$groups[$own]['minPrice'])$groups[$own]['minPrice']=$item['price'];
            $providerCounts[$item['provider']]=($providerCounts[$item['provider']]??0)+1;
        }
        foreach($groups as &$group){$group['providers']=array_keys($group['providers']);sort($group['providers'],SORT_STRING);usort($group['offers'],static fn($a,$b)=>(float)$a['price']<=>(float)$b['price']?:strcmp($a['provider'],$b['provider']));}unset($group);
        $hotels=array_values($groups);usort($hotels,static fn($a,$b)=>(float)$a['minPrice']<=>(float)$b['minPrice']?:$a['anytourHotelId']<=>$b['anytourHotelId']);
        ksort($providerCounts);$pdo->commit();
        return[
            'source'=>'anytour-db-first-results-v1','offerStoreSchemaVersion'=>$version,'scopeVersion'=>$scope['version'],'scopeDigest'=>$scope['digest'],'scope'=>$scope['params'],
            'generatedAt'=>$now->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
            'hotelCount'=>count($hotels),'offerCount'=>array_sum($providerCounts),'storedOfferCount'=>count($stored['items']),
            'withheldOfferCount'=>$withheld,'providerOfferCounts'=>$providerCounts,'selectionAuthority'=>false,'hotels'=>$hotels,
        ];
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
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
        if(!is_array($input)||array_keys($input)!==['params']||!is_array($input['params']))throw new InvalidArgumentException('Invalid request envelope');
        $result=search3_local_results_build(v2_data_db(),$input['params'],new DateTimeImmutable('now',new DateTimeZone('UTC')));
        search3_local_results_out(['ok'=>true,'data'=>$result]);
    }catch(InvalidArgumentException $e){search3_local_results_out(['ok'=>false,'error'=>'Invalid Search3 scope'],400);}
    catch(Throwable $e){error_log('search3-local-results-read-v1: '.$e->getMessage());search3_local_results_out(['ok'=>false,'error'=>'Local results temporarily unavailable'],503);}
}
