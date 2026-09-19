<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI only\n"); exit(2); }

$args=[];
foreach(array_slice($argv,1) as $arg){
    if(!str_starts_with($arg,'--')||!str_contains($arg,'=')) throw new InvalidArgumentException('ANDROMEDA_BRON_SHAPE_ARG');
    [$k,$v]=explode('=',substr($arg,2),2);$args[$k]=$v;
}
$get=static function(string $key)use($args):string{
    $v=$args[$key]??null;
    if(!is_string($v)||$v==='') throw new InvalidArgumentException('ANDROMEDA_BRON_SHAPE_ARG_'.$key);
    return $v;
};
$int=static function(string $value,int $min,int $max):int{
    if(!preg_match('/\A(?:0|[1-9][0-9]{0,9})\z/D',$value)) throw new InvalidArgumentException('ANDROMEDA_BRON_SHAPE_INT');
    $n=(int)$value;if($n<$min||$n>$max) throw new InvalidArgumentException('ANDROMEDA_BRON_SHAPE_INT');return $n;
};
$date=static function(string $value):string{
    if(!preg_match('/\A(\d{4})-(\d{2})-(\d{2})\z/D',$value,$m)||!checkdate((int)$m[2],(int)$m[3],(int)$m[1])) throw new InvalidArgumentException('ANDROMEDA_BRON_SHAPE_DATE');
    return $value;
};
$idText=static function(mixed $value):?string{
    if(is_int($value))$value=(string)$value;
    return is_string($value)&&preg_match('/\A[A-Za-z0-9_-]{1,128}\z/D',$value)?$value:null;
};
$safeScalar=static function(mixed $value):mixed{
    if(is_bool($value)||is_int($value)||is_float($value)||$value===null)return $value;
    if(!is_string($value))return null;
    $value=trim($value);if(strlen($value)>160)$value=substr($value,0,160).'…';return $value;
};

$site=realpath($get('site-root'));$privateConfig=realpath($get('private-config'));
$source=$get('source-sha');$operation=$get('operation-id');
if($site===false||basename($site)!=='anytoour.ru'||$privateConfig===false||!is_file($privateConfig)
    ||!preg_match('/\A[a-f0-9]{40}\z/D',$source)
    ||!preg_match('/\A[a-z0-9][a-z0-9._-]{20,160}\z/D',$operation)) throw new RuntimeException('ANDROMEDA_BRON_SHAPE_ROOT');
$targets=array_values(array_filter(array_map('trim',explode(',',$get('tour-keys'))),static fn($v)=>$v!==''));
if(count($targets)<1||count($targets)>10||count(array_unique($targets))!==count($targets)) throw new InvalidArgumentException('ANDROMEDA_BRON_SHAPE_TARGETS');
foreach($targets as $target)if(!preg_match('/\A[0-9]{1,18}\z/D',$target))throw new InvalidArgumentException('ANDROMEDA_BRON_SHAPE_TARGET');

$runtime=dirname(__DIR__,2);
require_once $runtime.'/v2/api-andromeda-search3-preview.php';
require_once $runtime.'/app/integrations/andromeda-operator-config.php';

$config=require $privateConfig;
if(!is_array($config)||($config['enabled']??null)!==true)throw new RuntimeException('ANDROMEDA_BRON_SHAPE_CONFIG');
require_once $site.'/config.php';
$dbFile=is_file($site.'/data/db-v1.php')?$site.'/data/db-v1.php':$site.'/v2/data/db-v1.php';require_once $dbFile;
$pdo=v2_data_db();$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);

$departure=$int($args['departure']??'1',1,999999999);$country=$int($args['country']??'4',1,999999999);
$dateFrom=$date($args['date-from']??'2026-09-19');$dateTo=$date($args['date-to']??$dateFrom);if($dateTo<$dateFrom)throw new InvalidArgumentException('ANDROMEDA_BRON_SHAPE_DATE_RANGE');
$nightsFrom=$int($args['nights-from']??'7',1,28);$nightsTo=$int($args['nights-to']??'10',$nightsFrom,28);$adults=$int($args['adults']??'2',1,6);
$fixedCheckIn=$date($args['fixed-checkin']??'2026-09-19');$fixedNights=$int($args['fixed-nights']??'7',$nightsFrom,$nightsTo);$generation=$int($args['generation']??'17171903',1,2147483647);

$request=['generation'=>$generation,'params'=>[
    'departureId'=>(string)$departure,'countryId'=>(string)$country,'dateFrom'=>$dateFrom,'dateTo'=>$dateTo,
    'nightsFrom'=>$nightsFrom,'nightsTo'=>$nightsTo,'adults'=>$adults,'childs'=>[],'meal'=>'','hotelCategory'=>'','hotelRating'=>'',
    'hotelTypes'=>[],'hotelIds'=>[],'hotelServices'=>[],'arrivalId'=>'','regionIds'=>[],'subregionIds'=>[],'operatorIds'=>[],
    'priceFrom'=>'','priceTo'=>'','currency'=>'RUB','onlyCharter'=>false,'onlyDirect'=>false,
]];
$saved=anytour_andromeda_search3_catalog($config,$request);$saved['excluded_operator_ids']=$config['excluded_operator_ids']??[];
$criteria=anytour_andromeda_search3_params($request,$pdo,$saved);$criteria['PAGE']=1;
$searchRef=hash('sha256','tourkey-bron-shape-v1|'.$operation.'|'.json_encode($criteria,JSON_THROW_ON_ERROR));

$lookup=$pdo->prepare("SELECT i.supplier_namespace,i.external_hotel_id,i.local_hotel_id AS catalog_hotel_id,h.id AS existing_catalog_hotel_id,i.decision_status FROM andromeda_hotel_identities i JOIN catalog_hotels h ON h.id=i.local_hotel_id WHERE i.decision_status='accepted' AND h.is_active=1 AND h.country_id=? ORDER BY i.external_hotel_id");
$lookup->execute([$country]);$identities=$lookup->fetchAll(PDO::FETCH_ASSOC);
$resolver=AnyTourAndromedaHotelResolver::fromRows($identities,hash('sha256',json_encode($identities,JSON_THROW_ON_ERROR)));unset($identities);

$monthlyDirectory=dirname((string)$config['catalog_path']);$lastSupplierStarted=0.0;
$reserveSupplier=static function()use(&$lastSupplierStarted,$monthlyDirectory):void{
    $wait=1.05-(microtime(true)-$lastSupplierStarted);if($wait>0)usleep((int)ceil($wait*1000000));
    anytour_andromeda_search3_budget($monthlyDirectory);$lastSupplierStarted=microtime(true);
};
$makeClient=static function(bool $allowPrice,bool $allowPackage)use($reserveSupplier):AnyTourAndromedaClient{
    $transport=new AnyTourAndromedaTransport($allowPrice,$allowPackage);
    $wrapped=static function(string $url,array $options)use($reserveSupplier,$transport):array{$reserveSupplier();return $transport($url,$options);};
    return new AnyTourAndromedaClient($wrapped,true,$allowPackage);
};

$client=$makeClient(true,false);$client->ensureLogin((string)$config['username'],(string)$config['password']);
$session=$client->privateSession();if(!is_string($session['sid']??null)||!is_int($session['expires']??null))throw new RuntimeException('ANDROMEDA_BRON_SHAPE_SESSION');

$ownsOperator=static function(string $raw):bool{
    $value=str_replace(['Ё','ё'],'е',trim($raw));$value=function_exists('mb_strtolower')?mb_strtolower($value,'UTF-8'):strtolower($value);
    $compact=preg_replace('/[^\p{L}\p{N}]+/u','',$value)??'';if($compact==='')return false;
    foreach(['anex','анекс','pegas','пегас','coral','корал','sunmar','санмар'] as $other)if(str_contains($compact,$other))return false;return true;
};

$candidates=[];$pages=0;$priceRows=0;$target=1;
for($page=1;$page<=$target;++$page){
    if($page>AnyTourAndromedaPaginationV1::MAX_PAGES)throw new RuntimeException('ANDROMEDA_BRON_SHAPE_PAGE_BUDGET');
    $pageCriteria=$criteria;$pageCriteria['PAGE']=$page;
    if($page===1){$pageClient=$client;}else{$pageClient=$makeClient(true,false);$pageClient->restorePrivateSession($session);}
    $payload=$pageClient->price($pageCriteria);++$pages;$priceRows+=count($payload['PRICES']??[]);
    if(($payload['PAGES_COUNT']??null)===0&&($payload['PRICES']??[])===[])break;
    $normalized=AnyTourAndromedaNormalizer::page($payload,$pageCriteria,$searchRef,$generation);$normalized=$resolver->apply($normalized);
    $byRef=[];
    foreach(($payload['PRICES']??[]) as $raw){
        if(!is_array($raw))continue;$rawId=$raw['id']??null;$operatorKey=$idText($raw['operatorKey']??null);
        if((!is_string($rawId)&&!is_int($rawId))||$operatorKey===null)continue;$rawId=(string)$rawId;if($rawId===''||strlen($rawId)>2048)continue;
        $offerRef='offer_'.hash('sha256',json_encode([$searchRef,$generation,$operatorKey,$rawId],JSON_THROW_ON_ERROR));$byRef[$offerRef]=$rawId;
    }
    foreach(($normalized['offers']??[]) as $offer){
        if(!is_array($offer)||!is_int($offer['local_hotel_id']??null)||$offer['local_hotel_id']<1)continue;
        if(!$ownsOperator((string)($offer['operator']??'')))continue;
        if((string)($offer['check_in']??'')!==$fixedCheckIn||(int)($offer['nights']??0)!==$fixedNights)continue;
        $ctx=is_array($offer['transport_context']??null)?$offer['transport_context']:[];$tour=$idText($ctx['tour_ref']??null);
        if($tour===null||!in_array($tour,$targets,true))continue;
        $offerRef=$offer['offer_ref']??null;if(!is_string($offerRef)||!isset($byRef[$offerRef]))continue;
        $operator=$idText($offer['operator_ref']??null);if($operator===null)continue;
        $rawId=$byRef[$offerRef];$already=false;
        foreach(($candidates[$tour]??[]) as $row)if($row['supplier_offer_id']===$rawId){$already=true;break;}
        if($already)continue;
        $candidates[$tour][]=['supplier_offer_id'=>$rawId,'identity'=>[
            'operatorKey'=>$operator,'tourKey'=>$tour,'programKey'=>$idText($ctx['program_ref']??null),'spoKey'=>$idText($ctx['spo_ref']??null),
            'checkIn'=>$fixedCheckIn,'nights'=>$fixedNights,'adult'=>(int)$offer['adults'],'child'=>(int)$offer['children'],
        ]];
    }
    $decision=AnyTourAndromedaPaginationV1::nextTarget($page,(int)$normalized['pages_count'],count($normalized['offers']),(string)$normalized['status'],count($normalized['rejected']),$target);
    if(($decision['terminal']??false)===true)break;$target=(int)$decision['target'];
}
$selected=[];foreach($targets as $tour){if(count($candidates[$tour]??[])<2)throw new RuntimeException('ANDROMEDA_BRON_SHAPE_SECOND_NOT_FOUND');$selected[$tour]=$candidates[$tour][1];}

[$operatorLogin,$operatorPassword]=anytour_andromeda_operator_credentials_from_config($config);
$secretEcho=static function(mixed $value,string $sid,int $depth=0)use(&$secretEcho):bool{
    if($depth>8)return false;if(is_string($value))return str_contains($value,$sid);if(!is_array($value))return false;
    foreach($value as $k=>$v){if(is_string($k)&&str_contains($k,$sid))return true;if($secretEcho($v,$sid,$depth+1))return true;}return false;
};
$flattenServices=static function(array $doc):array{
    $out=[];foreach(($doc['services']??[]) as $block){if(!is_array($block))continue;$rows=$block['service']??null;if(!is_array($rows))continue;foreach($rows as $row)if(is_array($row))$out[]=$row;}return $out;
};
$flattenTransports=static function(array $doc):array{
    $out=[];foreach(($doc['transports']??[]) as $block){if(!is_array($block))continue;$rows=$block['transport']??null;if(!is_array($rows))continue;foreach($rows as $row)if(is_array($row))$out[]=$row;}return $out;
};
$serviceFact=static function(array $service)use($safeScalar):array{
    $out=[];foreach(['category','type','name','title','route','required','packet','common'] as $k)if(array_key_exists($k,$service)&&is_scalar($service[$k]))$out[$k]=$safeScalar($service[$k]);
    foreach(['price','amount','currency'] as $k){if(!array_key_exists($k,$service))continue;$v=$service[$k];if(is_scalar($v))$out[$k]=$safeScalar($v);elseif(is_array($v)){foreach(['amount','value','currency','currencyCode'] as $pk)if(array_key_exists($pk,$v)&&is_scalar($v[$pk]))$out['price_'.$pk]=$safeScalar($v[$pk]);}}
    $clients=$service['clients']??null;if(is_array($clients))$out['client_count']=count($clients);return $out;
};
$transportFact=static function(array $transport)use($safeScalar):array{
    $out=[];foreach(['key','name','type','datebeg','dateend','groupId'] as $k)if(array_key_exists($k,$transport)&&is_scalar($transport[$k]))$out[$k]=$safeScalar($transport[$k]);return $out;
};
$safeError=static function(mixed $error):array{
    $encoded=json_encode($error,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);$text=is_string($error)?$error:$encoded;
    $category='unclassified';foreach(['flight_or_freight'=>'/(?:flight|freight|avia|airline|airfare|рейс|перел[её]т|авиа)/iu','claim_or_package'=>'/(?:claim|package|booking|заявк|пакет|брони)/iu','service'=>'/(?:service|услуг)/iu','price_or_fare'=>'/(?:price|cost|fare|tariff|цен|тариф)/iu'] as $c=>$rx)if(preg_match($rx,$text)){ $category=$c;break; }
    return ['shape'=>gettype($error),'error_sha256'=>hash('sha256',$encoded),'reason_category'=>$category];
};

$results=[];
foreach($targets as $tour){
    $candidate=$selected[$tour];$entry=['price_identity'=>$candidate['identity'],'status'=>'reserved_terminal_no_replay'];
    try{
        $transport=new AnyTourAndromedaTransport(false,true);$wrapped=static function(string $url,array $options)use($reserveSupplier,$transport):array{$reserveSupplier();return $transport($url,$options);};
        $params=['version'=>'1.01','action'=>'broninit','sid'=>$session['sid'],'claiminc'=>$candidate['supplier_offer_id']];
        if($operatorLogin!==null&&$operatorPassword!==null){$params['OPERATOR_LOGIN']=$operatorLogin;$params['OPERATOR_PASSWORD']=$operatorPassword;}
        $response=$wrapped('https://gateway.samo.ru/api/?'.http_build_query($params,'','&',PHP_QUERY_RFC3986),[]);
        if(($response['status']??null)!==200||!is_string($response['body']??null))throw new RuntimeException('ANDROMEDA_BRON_SHAPE_HTTP');
        $reply=json_decode($response['body'],true,64,JSON_THROW_ON_ERROR);if(!is_array($reply))throw new RuntimeException('ANDROMEDA_BRON_SHAPE_JSON');
        if($secretEcho($reply,$session['sid']))throw new RuntimeException('ANDROMEDA_BRON_SHAPE_SECRET_ECHO');
        if(array_key_exists('error',$reply)){$entry['status']='supplier_error_terminal_no_replay';$entry['supplier_error']=$safeError($reply['error']);$results[]=$entry;continue;}
        $entry['top_level_keys']=array_values(array_filter(array_keys($reply),'is_string'));sort($entry['top_level_keys'],SORT_STRING);
        $docs=$reply['claimDocument']??null;$entry['claimDocument_present']=is_array($docs);$entry['claimDocument_count']=is_array($docs)?count($docs):0;
        $doc=is_array($docs)&&isset($docs[0])&&is_array($docs[0])?$docs[0]:null;
        if($doc===null){$entry['status']='raw_shape_without_claim_terminal_no_replay';$results[]=$entry;continue;}
        $entry['document_keys']=array_values(array_filter(array_keys($doc),'is_string'));sort($entry['document_keys'],SORT_STRING);
        $entry['catalogKey_present']=array_key_exists('catalogKey',$doc);$entry['catalogKey_type']=gettype($doc['catalogKey']??null);
        $entry['claim_tourKey']=$idText($doc['tourKey']??null);$entry['claim_spoKey']=$idText($doc['spoKey']??null);
        $entry['freightExternal']=$safeScalar($doc['freightExternal']??null);$entry['freightExternal_type']=gettype($doc['freightExternal']??null);
        $services=$flattenServices($doc);$transports=$flattenTransports($doc);$entry['current_service_count']=count($services);$entry['current_transport_count']=count($transports);
        $entry['transports']=array_map($transportFact,array_slice($transports,0,12));
        $fuel=[];foreach($services as $service){$text='';foreach(['category','type','name','title'] as $k)if(is_scalar($service[$k]??null))$text.=' '.(string)$service[$k];if(preg_match('/(?:\bfuel\b|fuel[_ -]?surcharge|топлив)/iu',$text))$fuel[]=$serviceFact($service);}
        $entry['fuel_like_service_count']=count($fuel);$entry['fuel_like_services']=array_slice($fuel,0,20);
        $entry['status']='completed_terminal_no_replay';
    }catch(Throwable $e){$entry['status']='failed_terminal_no_replay';$m=$e->getMessage();$entry['failure_class']=is_string($m)&&preg_match('/\A[A-Z0-9_:-]{1,96}\z/D',$m)?$m:'ANDROMEDA_BRON_SHAPE_FAILURE';}
    $results[]=$entry;
}

$out=['version'=>1,'operation'=>$operation,'source'=>$source,'status'=>'completed_terminal_no_replay',
    'scope'=>['departureId'=>$departure,'countryId'=>$country,'dateFrom'=>$dateFrom,'dateTo'=>$dateTo,'fixedCheckIn'=>$fixedCheckIn,'fixedNights'=>$fixedNights,'adult'=>$adults,'child'=>0,'currency'=>'RUB'],
    'price_pages'=>$pages,'price_rows'=>$priceRows,'tourKeys'=>$targets,'specimens'=>$results,
    'broninit_calls'=>count($targets),'get_flights_calls'=>0,'changeservice_calls'=>0,'calc_calls'=>0,'booking_calls'=>0,'db_writes'=>0,'autosave_calls'=>0,'search3_publication'=>0,'replay_allowed'=>false];
echo json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
