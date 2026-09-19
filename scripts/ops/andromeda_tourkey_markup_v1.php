<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI only\n"); exit(2); }

$args=[];
foreach(array_slice($argv,1) as $arg){
    if(!str_starts_with($arg,'--')||!str_contains($arg,'=')) throw new InvalidArgumentException('ANDROMEDA_TOURKEY_MARKUP_ARG');
    [$k,$v]=explode('=',substr($arg,2),2);$args[$k]=$v;
}
$get=static function(string $key)use($args):string{
    $v=$args[$key]??null;
    if(!is_string($v)||$v==='') throw new InvalidArgumentException('ANDROMEDA_TOURKEY_MARKUP_ARG_'.$key);
    return $v;
};
$int=static function(string $value,int $min,int $max):int{
    if(!preg_match('/\A(?:0|[1-9][0-9]{0,9})\z/D',$value)) throw new InvalidArgumentException('ANDROMEDA_TOURKEY_MARKUP_INT');
    $n=(int)$value;if($n<$min||$n>$max) throw new InvalidArgumentException('ANDROMEDA_TOURKEY_MARKUP_INT');return $n;
};
$date=static function(string $value):string{
    if(!preg_match('/\A(\d{4})-(\d{2})-(\d{2})\z/D',$value,$m)||!checkdate((int)$m[2],(int)$m[3],(int)$m[1]))
        throw new InvalidArgumentException('ANDROMEDA_TOURKEY_MARKUP_DATE');
    return $value;
};
$idText=static function(mixed $value):?string{
    if(is_int($value))$value=(string)$value;
    return is_string($value)&&preg_match('/\A[A-Za-z0-9_-]{1,128}\z/D',$value)?$value:null;
};
$money=static function(mixed $value):?string{
    if(is_int($value))$value=(string)$value;
    if(is_float($value)&&is_finite($value))$value=number_format($value,2,'.','');
    if(!is_string($value)||!preg_match('/\A(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?\z/D',$value))return null;
    if(str_contains($value,'.')){$value=rtrim(rtrim($value,'0'),'.');if($value==='')$value='0';}
    return $value;
};

$site=realpath($get('site-root'));$privateConfig=realpath($get('private-config'));
$source=$get('source-sha');$operation=$get('operation-id');
if($site===false||basename($site)!=='anytoour.ru'||$privateConfig===false||!is_file($privateConfig)
    ||!preg_match('/\A[a-f0-9]{40}\z/D',$source)
    ||!preg_match('/\A[a-z0-9][a-z0-9._-]{20,160}\z/D',$operation)) throw new RuntimeException('ANDROMEDA_TOURKEY_MARKUP_ROOT');

$targets=array_values(array_filter(array_map('trim',explode(',',$get('tour-keys'))),static fn($v)=>$v!==''));
if(count($targets)<1||count($targets)>10)throw new InvalidArgumentException('ANDROMEDA_TOURKEY_MARKUP_TARGETS');
foreach($targets as $t)if(!preg_match('/\A[0-9]{1,18}\z/D',$t))throw new InvalidArgumentException('ANDROMEDA_TOURKEY_MARKUP_TARGET');
if(count(array_unique($targets))!==count($targets))throw new InvalidArgumentException('ANDROMEDA_TOURKEY_MARKUP_TARGET_DUP');

$runtime=dirname(__DIR__,2);
require_once $runtime.'/v2/api-andromeda-search3-preview.php';
require_once $runtime.'/app/integrations/andromeda-operator-config.php';
require_once $runtime.'/app/integrations/andromeda-claim-actions.php';

$config=require $privateConfig;
if(!is_array($config)||($config['enabled']??null)!==true)throw new RuntimeException('ANDROMEDA_TOURKEY_MARKUP_CONFIG');
require_once $site.'/config.php';
$dbFile=is_file($site.'/data/db-v1.php')?$site.'/data/db-v1.php':$site.'/v2/data/db-v1.php';
require_once $dbFile;
$pdo=v2_data_db();$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);

$departure=$int($args['departure']??'1',1,999999999);
$country=$int($args['country']??'4',1,999999999);
$dateFrom=$date($args['date-from']??'2026-09-19');
$dateTo=$date($args['date-to']??$dateFrom);if($dateTo<$dateFrom)throw new InvalidArgumentException('ANDROMEDA_TOURKEY_MARKUP_DATE_RANGE');
$nightsFrom=$int($args['nights-from']??'7',1,28);
$nightsTo=$int($args['nights-to']??'10',$nightsFrom,28);
$adults=$int($args['adults']??'2',1,6);
$generation=$int($args['generation']??'17171902',1,2147483647);
$fixedCheckIn=$date($args['fixed-checkin']??'2026-09-19');
$fixedNights=$int($args['fixed-nights']??'7',$nightsFrom,$nightsTo);

$request=['generation'=>$generation,'params'=>[
    'departureId'=>(string)$departure,'countryId'=>(string)$country,
    'dateFrom'=>$dateFrom,'dateTo'=>$dateTo,'nightsFrom'=>$nightsFrom,'nightsTo'=>$nightsTo,
    'adults'=>$adults,'childs'=>[],'meal'=>'','hotelCategory'=>'','hotelRating'=>'','hotelTypes'=>[],
    'hotelIds'=>[],'hotelServices'=>[],'arrivalId'=>'','regionIds'=>[],'subregionIds'=>[],
    'operatorIds'=>[],'priceFrom'=>'','priceTo'=>'','currency'=>'RUB','onlyCharter'=>false,'onlyDirect'=>false,
]];
$saved=anytour_andromeda_search3_catalog($config,$request);
$saved['excluded_operator_ids']=$config['excluded_operator_ids']??[];
$criteria=anytour_andromeda_search3_params($request,$pdo,$saved);$criteria['PAGE']=1;
$searchRef=hash('sha256','tourkey-markup-v1|'.$operation.'|'.json_encode($criteria,JSON_THROW_ON_ERROR));

$lookup=$pdo->prepare("SELECT i.supplier_namespace,i.external_hotel_id,i.local_hotel_id AS catalog_hotel_id,h.id AS existing_catalog_hotel_id,i.decision_status FROM andromeda_hotel_identities i JOIN catalog_hotels h ON h.id=i.local_hotel_id WHERE i.decision_status='accepted' AND h.is_active=1 AND h.country_id=? ORDER BY i.external_hotel_id");
$lookup->execute([$country]);$identities=$lookup->fetchAll(PDO::FETCH_ASSOC);
$resolver=AnyTourAndromedaHotelResolver::fromRows($identities,hash('sha256',json_encode($identities,JSON_THROW_ON_ERROR)));unset($identities);

$monthlyDirectory=dirname((string)$config['catalog_path']);$lastSupplierStarted=0.0;
$reserveSupplier=static function()use(&$lastSupplierStarted,$monthlyDirectory):void{
    $wait=1.05-(microtime(true)-$lastSupplierStarted);if($wait>0)usleep((int)ceil($wait*1000000));
    anytour_andromeda_search3_budget($monthlyDirectory);$lastSupplierStarted=microtime(true);
};
$makeClient=static function(bool $allowPrice,bool $allowPackage,?string $operatorLogin=null,?string $operatorPassword=null)use($reserveSupplier):AnyTourAndromedaClient{
    $transport=new AnyTourAndromedaTransport($allowPrice,$allowPackage);
    $wrapped=static function(string $url,array $options)use($reserveSupplier,$transport):array{$reserveSupplier();return $transport($url,$options);};
    return new AnyTourAndromedaClient($wrapped,true,$allowPackage,$operatorLogin,$operatorPassword);
};

$client=$makeClient(true,false);$client->ensureLogin((string)$config['username'],(string)$config['password']);
$session=$client->privateSession();
if(!is_string($session['sid']??null)||!is_int($session['expires']??null))throw new RuntimeException('ANDROMEDA_TOURKEY_MARKUP_SESSION');

$ownsOperator=static function(string $raw):bool{
    $value=str_replace(['Ё','ё'],'е',trim($raw));
    $value=function_exists('mb_strtolower')?mb_strtolower($value,'UTF-8'):strtolower($value);
    $compact=preg_replace('/[^\p{L}\p{N}]+/u','',$value)??'';
    if($compact==='')return false;
    foreach(['anex','анекс','pegas','пегас','coral','корал','sunmar','санмар'] as $other)if(str_contains($compact,$other))return false;
    return true;
};

$selected=[];$pages=0;$priceRows=0;$target=1;
for($page=1;$page<=$target;++$page){
    if($page>AnyTourAndromedaPaginationV1::MAX_PAGES)throw new RuntimeException('ANDROMEDA_TOURKEY_MARKUP_PAGE_BUDGET');
    $pageCriteria=$criteria;$pageCriteria['PAGE']=$page;
    if($page===1){$pageClient=$client;}else{$pageClient=$makeClient(true,false);$pageClient->restorePrivateSession($session);}
    $payload=$pageClient->price($pageCriteria);++$pages;$priceRows+=count($payload['PRICES']??[]);
    if(($payload['PAGES_COUNT']??null)===0&&($payload['PRICES']??[])===[])break;
    $normalized=AnyTourAndromedaNormalizer::page($payload,$pageCriteria,$searchRef,$generation);$normalized=$resolver->apply($normalized);
    $byRef=[];
    foreach(($payload['PRICES']??[]) as $raw){
        if(!is_array($raw))continue;$rawId=$raw['id']??null;$operatorKey=$idText($raw['operatorKey']??null);
        if((!is_string($rawId)&&!is_int($rawId))||$operatorKey===null)continue;$rawId=(string)$rawId;
        if($rawId===''||strlen($rawId)>2048)continue;
        $offerRef='offer_'.hash('sha256',json_encode([$searchRef,$generation,$operatorKey,$rawId],JSON_THROW_ON_ERROR));
        $byRef[$offerRef]=$rawId;
    }
    foreach(($normalized['offers']??[]) as $offer){
        if(!is_array($offer)||!is_int($offer['local_hotel_id']??null)||$offer['local_hotel_id']<1)continue;
        if(!$ownsOperator((string)($offer['operator']??'')))continue;
        if((string)($offer['check_in']??'')!==$fixedCheckIn||(int)($offer['nights']??0)!==$fixedNights)continue;
        $ctx=is_array($offer['transport_context']??null)?$offer['transport_context']:[];
        $tour=$idText($ctx['tour_ref']??null);if($tour===null||!in_array($tour,$targets,true)||isset($selected[$tour]))continue;
        $offerRef=$offer['offer_ref']??null;if(!is_string($offerRef)||!isset($byRef[$offerRef]))continue;
        $operator=$idText($offer['operator_ref']??null);if($operator===null)continue;
        $selected[$tour]=[
            'supplier_offer_id'=>$byRef[$offerRef],
            'identity'=>[
                'operatorKey'=>$operator,'programKey'=>$idText($ctx['program_ref']??null),
                'tourKey'=>$tour,'spoKey'=>$idText($ctx['spo_ref']??null),
                'checkIn'=>$fixedCheckIn,'nights'=>$fixedNights,'adult'=>(int)$offer['adults'],'child'=>(int)$offer['children'],
            ],
        ];
    }
    $decision=AnyTourAndromedaPaginationV1::nextTarget($page,(int)$normalized['pages_count'],count($normalized['offers']),(string)$normalized['status'],count($normalized['rejected']),$target);
    if(($decision['terminal']??false)===true)break;$target=(int)$decision['target'];
}
foreach($targets as $tour)if(!isset($selected[$tour]))throw new RuntimeException('ANDROMEDA_TOURKEY_MARKUP_TARGET_NOT_FOUND');

[$operatorLogin,$operatorPassword]=anytour_andromeda_operator_credentials_from_config($config);
$extractMarkup=static function(array $claim)use($money):array{
    $facts=[];
    foreach(($claim['variants']??[]) as $variant){
        if(!is_array($variant))continue;
        foreach(($variant['transports']??[]) as $block){
            if(!is_array($block)||!is_array($block['transport']??null))continue;
            foreach($block['transport'] as $transport){
                if(!is_array($transport)||($transport['type']??null)!=='ttAvia')continue;
                foreach(($transport['details']??[]) as $detailBlock){
                    if(!is_array($detailBlock)||!is_array($detailBlock['detail']??null))continue;
                    foreach($detailBlock['detail'] as $detail){
                        if(!is_array($detail))continue;$amount=$money($detail['markup']??null);$currency=$detail['currency']??null;
                        if($amount===null||!is_string($currency)||!preg_match('/\A[A-Z0-9_]{2,8}\z/D',$currency))continue;
                        $facts[$currency."\0".$amount]=['amount'=>$amount,'currency'=>$currency];
                    }
                }
            }
        }
    }
    ksort($facts,SORT_STRING);if(count($facts)>128)throw new RuntimeException('ANDROMEDA_TOURKEY_MARKUP_FACT_BUDGET');
    return array_values($facts);
};
$safeCode=static function(Throwable $e):string{
    $m=$e->getMessage();return is_string($m)&&preg_match('/\A[A-Z0-9_:-]{1,96}\z/D',$m)?$m:'ANDROMEDA_TOURKEY_MARKUP_FAILURE';
};

$results=[];
foreach($targets as $tour){
    $candidate=$selected[$tour];$entry=['price_identity'=>$candidate['identity'],'status'=>'reserved_terminal_no_replay','markup'=>[]];
    try{
        $packageClient=$makeClient(false,true,$operatorLogin,$operatorPassword);$packageClient->restorePrivateSession($session);
        $package=$packageClient->package($candidate['supplier_offer_id']);
        $doc=is_array($package['claimDocument']??null)&&array_keys($package['claimDocument'])===[0]&&is_array($package['claimDocument'][0])?$package['claimDocument'][0]:null;
        if(!is_array($doc))throw new RuntimeException('ANDROMEDA_CLAIM_SHAPE_INVALID');
        $entry['claim_tourKey']=$idText($doc['tourKey']??null);
        $entry['claim_spoKey']=$idText($doc['spoKey']??null);
        $entry['freightExternal']=is_scalar($doc['freightExternal']??null)?(string)$doc['freightExternal']:null;
        if($entry['claim_tourKey']!==$tour)throw new RuntimeException('ANDROMEDA_TOURKEY_MARKUP_CLAIM_MISMATCH');
        if((string)($doc['freightExternal']??'0')==='0'){
            $entry['status']='package_non_external_terminal_no_replay';$results[]=$entry;continue;
        }
        $actions=new AnyTourAndromedaClaimActions($session['sid'],$reserveSupplier);
        $flights=$actions->getFlights($package);$entry['markup']=$extractMarkup($flights);
        $entry['status']=$entry['markup']===[]?'completed_no_markup':'completed';
    }catch(Throwable $e){
        $entry['status']='failed_terminal_no_replay';$entry['failure_class']=$safeCode($e);
    }
    $results[]=$entry;
}

$out=[
    'version'=>1,'operation'=>$operation,'source'=>$source,'status'=>'completed_terminal_no_replay',
    'scope'=>['departureId'=>$departure,'countryId'=>$country,'dateFrom'=>$dateFrom,'dateTo'=>$dateTo,'fixedCheckIn'=>$fixedCheckIn,'fixedNights'=>$fixedNights,'adult'=>$adults,'child'=>0,'currency'=>'RUB'],
    'price_pages'=>$pages,'price_rows'=>$priceRows,'tourKeys'=>$targets,'specimens'=>$results,
    'package_calls'=>count($targets),'get_flights_attempts'=>count(array_filter($results,static fn($r)=>($r['status']??'')!=='package_non_external_terminal_no_replay')),
    'changeservice_calls'=>0,'calc_calls'=>0,'booking_calls'=>0,'db_writes'=>0,'autosave_calls'=>0,'search3_publication'=>0,
    'fuel_interpretation'=>'not_evaluated','replay_allowed'=>false,
];
echo json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
