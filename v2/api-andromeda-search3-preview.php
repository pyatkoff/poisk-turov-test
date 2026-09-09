<?php
declare(strict_types=1);
require_once __DIR__.'/api-anex-search3-preview.php';
$andromedaApp=is_file(__DIR__.'/app/integrations/andromeda-client.php')?__DIR__.'/app/integrations':__DIR__.'/../app/integrations';
foreach(['andromeda-client','andromeda-transport','andromeda-normalizer','andromeda-hotel-resolver','anex-normalizer'] as $file) require_once $andromedaApp.'/'.$file.'.php';

function anytour_andromeda_search3_params(array $request, PDO $pdo, array $saved): array {
    if(!is_int($request['generation']??null) || $request['generation']<1 || $request['generation']>2147483647 || !is_array($request['params']??null)) throw new InvalidArgumentException();
    $p=$request['params'];
    if((string)($p['countryId']??'')!=='1') throw new DomainException('country_not_loaded');
    foreach(['arrivalId','operatorIds','hotelServices','hotelTypes'] as $key) if(!empty($p[$key]))throw new DomainException('filter_not_supported');
    foreach(['onlyDirect','onlyCharter'] as $key) if(!in_array($p[$key]??false,[false,'false',0,'0',''],true))throw new DomainException('filter_not_supported');
    if(!in_array($p['meal']??'',['','7',7],true) || ($p['currency']??'RUB')!=='RUB')throw new DomainException('filter_not_supported');
    foreach(['hotelIds','regionIds','subregionIds'] as $key){
        if(isset($p[$key]) && (!is_array($p[$key]) || count($p[$key])>30))throw new InvalidArgumentException();
        foreach($p[$key]??[] as $id)if(!is_scalar($id)||!ctype_digit((string)$id))throw new InvalidArgumentException();
    }
    $lookup=$pdo->prepare('SELECT name FROM catalog_departures WHERE id=? AND is_active=1');
    $lookup->execute([(int)($p['departureId']??0)]);$name=$lookup->fetchColumn();
    if(!$name)throw new DomainException('departure_not_loaded');
    $departure=anytour_anex_search3_dictionary_id($saved['townfrom']['payload']['TOWNFROM'],[$name]);
    $dates=[];
    foreach(['dateFrom','dateTo'] as $key){
        $value=$p[$key]??'';$date=is_string($value)?DateTimeImmutable::createFromFormat('!Y-m-d',$value):false;
        if(!$date || $date->format('Y-m-d')!==$value || $value<gmdate('Y-m-d'))throw new InvalidArgumentException();
        $dates[]=$date->format('Ymd');
    }
    $ages=$p['childs']??[];
    if(!is_array($ages)||count($ages)>3)throw new InvalidArgumentException();
    foreach($ages as $age)if(!is_scalar($age)||!ctype_digit((string)$age)||(int)$age>17)throw new InvalidArgumentException();
    $params=['TOWNFROMINC'=>$departure,'STATEINC'=>3,'CHECKIN_BEG'=>$dates[0],'CHECKIN_END'=>$dates[1],
        'NIGHTS_FROM'=>(int)($p['nightsFrom']??0),'NIGHTS_TILL'=>(int)($p['nightsTo']??0),
        'ADULT'=>(int)($p['adults']??0),'CHILD'=>count($ages),'CURRENCYINC'=>643,'PACKETTYPE'=>0,'PAGE'=>1];
    if($ages)$params['AGES']=implode(',',$ages);
    if(!empty($p['meal']))$params['MEAL']='5';
    // Empty exclusions keep every operator enabled in the owner's SAMO account.
    $excluded=$saved['excluded_operator_ids']??[];
    if($excluded){
        $operators=[];
        foreach($saved['all']['payload']['OPERATORS'] as $operator)
            if(!in_array((string)$operator['id'],array_map('strval',$excluded),true))$operators[]=(string)$operator['id'];
        if(!$operators)throw new DomainException('no_operators');
        $params['OPERATORS']=implode(',',$operators);
    }
    return $params;
}

function anytour_andromeda_search3_run(array $request, PDO $pdo, $client, array $saved): array {
    $params=anytour_andromeda_search3_params($request,$pdo,$saved);
    $payload=$client->price($params);
    $page=AnyTourAndromedaNormalizer::page($payload,$params,'search_'.bin2hex(random_bytes(12)),$request['generation']);
    $identities=$pdo->query("SELECT i.supplier_namespace,i.external_hotel_id,i.local_hotel_id AS catalog_hotel_id,h.id AS existing_catalog_hotel_id,i.decision_status FROM andromeda_hotel_identities i JOIN catalog_hotels h ON h.id=i.local_hotel_id WHERE i.decision_status='accepted' AND h.is_active=1 AND h.country_id=1 ORDER BY i.external_hotel_id")->fetchAll(PDO::FETCH_ASSOC);
    $page=AnyTourAndromedaHotelResolver::fromRows($identities,hash('sha256',json_encode($identities)))->apply($page);
    $converted=[];$ids=[];
    foreach($page['offers'] as $offer){
        $id=$offer['local_hotel_id'];if(!$id)continue;$ids[$id]=true;
        $converted[]=['hotel'=>['local_id'=>$id,'mapping_status'=>'resolved'],'price'=>$offer['price'],
            'checkin'=>$offer['check_in'],'nights'=>$offer['nights'],'adults'=>$offer['adults'],'children'=>$offer['children'],
            'meal'=>$offer['meal']['label'],'room'=>$offer['room'],'kind'=>'offer'];
    }
    $metadata=[];
    if($ids){
        $query=$pdo->prepare('SELECT id,name,country_id,country_name,region_id,region_name,subregion_id,subregion_name,category,rating FROM catalog_hotels WHERE id IN ('.implode(',',array_fill(0,count($ids),'?')).') AND is_active=1');
        $query->execute(array_keys($ids));foreach($query->fetchAll(PDO::FETCH_ASSOC) as $row)$metadata[(int)$row['id']]=$row;
        $metadata=anytour_anex_search3_catalog_hydrate($pdo,$metadata);
    }
    $hotels=anytour_anex_search3_project($converted,$metadata,$request['params']);
    // Projection sorts offers. Bind operator/source using full normalized display tuple, never price alone.
    foreach($hotels as &$hotel)foreach($hotel['tours'] as &$tour){
        $matches=array_values(array_filter($page['offers'],static function($o)use($hotel,$tour){
            return $o['local_hotel_id']===$hotel['local_id'] && $o['check_in']===$tour['checkin'] && $o['room']===$tour['room']
                && $o['nights']===$tour['nights'] && $o['meal']['label']===$tour['meal'] && $o['price']['amount']===$tour['price']['amount'];
        }));
        $tour['provider']='andromeda';$tour['operator']=count($matches)===1?$matches[0]['operator']:'Туроператор из ответа Андромеды';
        $tour['selection_enabled']=false;
    }
    unset($hotel,$tour);
    return ['provider'=>'andromeda','generation'=>$request['generation'],'hotels'=>$hotels,
        'date_range'=>['from'=>$request['params']['dateFrom'],'to'=>$request['params']['dateTo']],
        'first_page_only'=>true,'pages_count'=>$page['pages_count'],'external_search_pending'=>false,
        'received_offers'=>count($page['offers']),'mapped_offers'=>$page['mapped_offer_count'],'selection_enabled'=>false];
}

function anytour_andromeda_search3_http(): void {
    header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
    if(!is_file(__DIR__.'/.andromeda-private.php'))anytour_anex_search3_out(['ok'=>false,'error'=>'not_found'],404);
    $config=require __DIR__.'/.andromeda-private.php';
    if(($config['enabled']??false)!==true)anytour_anex_search3_out(['ok'=>false,'error'=>'not_found'],404);
    if(($_SERVER['REQUEST_METHOD']??'')!=='POST')anytour_anex_search3_out(['ok'=>false,'error'=>'method_not_allowed'],405);
    if(!in_array($_SERVER['HTTP_SEC_FETCH_SITE']??'same-origin',['same-origin','none'],true))anytour_anex_search3_out(['ok'=>false,'error'=>'forbidden'],403);
    if(strtolower(trim(explode(';',$_SERVER['CONTENT_TYPE']??'')[0]))!=='application/json')anytour_anex_search3_out(['ok'=>false,'error'=>'invalid_request'],400);
    $raw=file_get_contents('php://input',false,null,0,16385);
    if(strlen($raw)>16384)anytour_anex_search3_out(['ok'=>false,'error'=>'invalid_request'],400);
    session_name('ANYTOUR_ANDROMEDA_SEARCH3');ini_set('session.use_strict_mode','1');
    session_set_cookie_params(['secure'=>true,'httponly'=>true,'samesite'=>'Lax','path'=>'/_preview/search3-anex-candidate/']);
    if(!session_start())anytour_anex_search3_out(['ok'=>false,'error'=>'supplier_unavailable'],503);
    $recent=array_filter($_SESSION['requests']??[],static function($time){return $time>time()-60;});
    if(count($recent)>=6){session_write_close();anytour_anex_search3_out(['ok'=>false,'error'=>'rate_limited'],429);}
    $recent[]=time();$_SESSION['requests']=array_values($recent);session_write_close();
    try{
        $request=json_decode($raw,true,16,JSON_THROW_ON_ERROR);if(!is_array($request))throw new InvalidArgumentException();
        $root=realpath($_SERVER['DOCUMENT_ROOT']??'');if(!$root||basename($root)!=='anytoour.ru')throw new RuntimeException();
        require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
        $pdo=v2_data_db();$saved=json_decode(file_get_contents($config['catalog_path']),true,32,JSON_THROW_ON_ERROR);
        $saved['excluded_operator_ids']=$config['excluded_operator_ids']??[];
        // Reject unsupported form conditions before spending supplier requests.
        anytour_andromeda_search3_params($request,$pdo,$saved);
        $client=new AnyTourAndromedaClient(new AnyTourAndromedaTransport(true),true);
        $client->login($config['username'],$config['password']);
        $data=anytour_andromeda_search3_run($request,$pdo,$client,$saved);
        anytour_anex_search3_out(['ok'=>true,'data'=>$data],200);
    }catch(DomainException $e){anytour_anex_search3_out(['ok'=>false,'error'=>'search_not_supported'],422);
    }catch(InvalidArgumentException $e){anytour_anex_search3_out(['ok'=>false,'error'=>'invalid_request'],400);
    }catch(Throwable $e){anytour_anex_search3_out(['ok'=>false,'error'=>'supplier_unavailable'],502);}
}
if(realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__)anytour_andromeda_search3_http();
