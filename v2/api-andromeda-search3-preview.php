<?php
declare(strict_types=1);
require_once __DIR__.'/api-anex-search3-preview.php';
$andromedaApp=is_file(__DIR__.'/app/integrations/andromeda-client.php')?__DIR__.'/app/integrations':__DIR__.'/../app/integrations';
foreach(['andromeda-client','andromeda-transport','andromeda-normalizer','andromeda-hotel-resolver','andromeda-search','anex-normalizer'] as $file) require_once $andromedaApp.'/'.$file.'.php';


/** Restrict upstream only with complete accepted catalog coverage; otherwise retain local filtering. */
function anytour_andromeda_search3_hotels(array $localIds, PDO $pdo, array $saved): ?string {
    if(!$localIds)return null;
    $country=(int)($saved['local_country_id']??1);
    $wanted=array_values(array_unique(array_map('strval',$localIds)));
    $query=$pdo->prepare("SELECT i.local_hotel_id,i.external_hotel_id FROM andromeda_hotel_identities i JOIN catalog_hotels h ON h.id=i.local_hotel_id WHERE i.supplier_namespace='andromeda_catalog' AND i.decision_status='accepted' AND h.is_active=1 AND h.country_id=? AND h.id IN (".implode(',',array_fill(0,count($wanted),'?')).")");
    $query->execute(array_merge([$country],$wanted));
    $catalog=[];foreach($saved['all']['payload']['HOTELS']??[] as $hotel)$catalog[(string)$hotel['id']]=true;
    $covered=[];$external=[];
    foreach($query->fetchAll(PDO::FETCH_ASSOC) as $row){
        $id=(string)$row['external_hotel_id'];
        if(!preg_match('/^[1-9][0-9]*$/D',$id)||!isset($catalog[$id]))return null;
        $covered[(string)$row['local_hotel_id']]=true;$external[$id]=true;
    }
    foreach($wanted as $id)if(!isset($covered[$id]))return null;
    $ids=array_map('strval',array_keys($external));sort($ids,SORT_STRING);
    $value=implode(',',$ids);
    return count($ids)<=30 && strlen($value)<=300 ? $value : null;
}

function anytour_andromeda_search3_params(array $request, PDO $pdo, array $saved): array {
    if(!is_int($request['generation']??null) || $request['generation']<1 || $request['generation']>2147483647 || !is_array($request['params']??null)) throw new InvalidArgumentException();
    $p=$request['params'];
    $country=(int)($saved['local_country_id']??1);
    if((string)($p['countryId']??'')!==(string)$country) throw new DomainException('country_not_loaded');
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
    $params=['TOWNFROMINC'=>$departure,'STATEINC'=>(int)($saved['all']['params']['STATEINC']??3),'CHECKIN_BEG'=>$dates[0],'CHECKIN_END'=>$dates[1],
        'NIGHTS_FROM'=>(int)($p['nightsFrom']??0),'NIGHTS_TILL'=>(int)($p['nightsTo']??0),
        'ADULT'=>(int)($p['adults']??0),'CHILD'=>count($ages),'CURRENCYINC'=>643,'PACKETTYPE'=>0,'PAGE'=>$request['page']??1];
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
    if (isset($request['andromeda_operator_ids'])) {
        if ($request['andromeda_operator_ids'] !== ['5'] || in_array('5',array_map('strval',$excluded),true)) throw new DomainException('operator_not_supported');
        $params['OPERATORS']='5';
    }
    $hotels=anytour_andromeda_search3_hotels($p['hotelIds']??[],$pdo,$saved);
    if($hotels!==null)$params['HOTELS']=$hotels;
    AnyTourAndromedaClient::validatePriceParams($params);
    return $params;
}

function anytour_andromeda_search3_project(array $request, PDO $pdo, array $page, array $saved=[]): array {
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
    $used=[];
    foreach($hotels as &$hotel)foreach($hotel['tours'] as &$tour){
        $matches=array_values(array_filter($page['offers'],static function($o)use($hotel,$tour){
            return $o['local_hotel_id']===$hotel['local_id'] && $o['check_in']===$tour['checkin'] && $o['room']===$tour['room']
                && $o['nights']===$tour['nights'] && $o['meal']['label']===$tour['meal'] && $o['price']['amount']===$tour['price']['amount'];
        }));
        $tour['provider']='andromeda';
        foreach($matches as $match)if(!isset($used[$match['offer_ref']])){
            $tour['operator']=$match['operator'];$tour['offer_ref']=$match['offer_ref'];
            if(!isset($hotel['andromeda_content'])||empty($hotel['andromeda_content']['image_url']))$hotel['andromeda_content']=$match['hotel_content']??null;$used[$match['offer_ref']]=true;break;
        }
        $tour['selection_enabled']=false;
    }
    unset($hotel,$tour);
    // Unresolved identities stay separate and never borrow an unaccepted catalog ID.
    $unresolved=[];$p=$request['params'];
    $needsCatalog=false;
    foreach(['hotelIds','regionIds','subregionIds','hotelRating'] as $filter)if(!empty($p[$filter]))$needsCatalog=true;
    if(!$needsCatalog)foreach($page['offers'] as $offer){
        if($offer['local_hotel_id']!==null || $offer['price']['currency']!=='RUB')continue;
        $category=$offer['hotel_content']['category']??null;
        if(!empty($p['hotelCategory']) && (!is_int($category)||$category<1||$category>5||$category<(float)$p['hotelCategory']))continue;
        $amount=(float)$offer['price']['amount'];
        if((!empty($p['priceFrom'])&&$amount<(float)$p['priceFrom'])||(!empty($p['priceTo'])&&$amount>(float)$p['priceTo']))continue;
        if(!empty($p['meal'])&&!in_array(anytour_anex_search3_name($offer['meal']['label']),['ai','all','all inclusive','uai','ultra all inclusive','ai without alcohol','все включено','ультра все включено','все включено без алкоголя'],true))continue;
        $key='andromeda:'.$offer['supplier_namespace'].':'.$offer['external_hotel_id'];
        if(!isset($unresolved[$key]))$unresolved[$key]=['local_id'=>null,'card_key'=>$key,'provider'=>'andromeda','mapping_status'=>'unresolved',
            'name'=>$offer['hotel'],'category'=>$offer['hotel_content']['category']??null,'rating'=>null,'country'=>(string)($saved['local_country_name']??'Египет'),
            'region'=>$offer['hotel_content']['region']??'','catalog'=>null,'andromeda_content'=>$offer['hotel_content']??null,'tours'=>[]];
        $unresolved[$key]['tours'][]=['provider'=>'andromeda','operator'=>$offer['operator'],'offer_ref'=>$offer['offer_ref'],
            'price'=>$offer['price'],'checkin'=>$offer['check_in'],'nights'=>$offer['nights'],'adults'=>$offer['adults'],'children'=>$offer['children'],
            'meal'=>$offer['meal']['label'],'room'=>$offer['room'],'kind'=>'offer','selection_enabled'=>false,'final_price_verified'=>false];
    }
    foreach($unresolved as $hotel){usort($hotel['tours'],static function($a,$b){return (float)$a['price']['amount']<=>(float)$b['price']['amount'];});$hotels[]=$hotel;}
    return ['provider'=>'andromeda','generation'=>$request['generation'],'hotels'=>$hotels,
        'date_range'=>['from'=>$request['params']['dateFrom'],'to'=>$request['params']['dateTo']],
        'first_page_only'=>false,'page'=>$page['page'],'pages_count'=>$page['pages_count'],'external_search_pending'=>false,
        'search_ref'=>$page['search_ref'],'status'=>$page['status'],
        'received_offers'=>count($page['offers']),'mapped_offers'=>count(array_filter($page['offers'],static function($o){return $o['local_hotel_id']!==null;})),'selection_enabled'=>false];
}

/** Atomic private checkpoint: never save supplier credentials or sid. */
function anytour_andromeda_search3_save(string $path, array $value): bool {
    $bytes=json_encode($value,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    if(strlen($bytes)>3000000)throw new RuntimeException();
    $temp=$path.'.'.bin2hex(random_bytes(8));
    $file=fopen($temp,'x');if(!$file)throw new RuntimeException();
    chmod($temp,0600);
    try {
        if(fwrite($file,$bytes)!==strlen($bytes)||!fflush($file))throw new RuntimeException();
        if(function_exists('fsync')&&!fsync($file))throw new RuntimeException();
    }finally{fclose($file);}
    if(!rename($temp,$path))throw new RuntimeException();
    return true;
}

/** Owner-confirmed allowance: 5,000,000 supplier requests per calendar month.
 * This counter covers this integration from deployment, not unrelated account consumers.
 */
function anytour_andromeda_search3_budget(string $directory): void {
    $path=$directory.'/monthly-requests.json';$lock=fopen($path.'.lock','c');
    if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException();
    try {
        $month=gmdate('Y-m');$state=is_file($path)?json_decode(file_get_contents($path),true,8,JSON_THROW_ON_ERROR):[];
        if(($state['month']??null)!==$month)$state=['month'=>$month,'reserved_requests'=>0,'monthly_limit'=>5000000,'scope'=>'this_integration'];
        if(!is_int($state['reserved_requests'])||$state['reserved_requests']<0)throw new RuntimeException();
        if($state['reserved_requests']>=5000000)throw new OverflowException('monthly_quota_exhausted');
        ++$state['reserved_requests'];anytour_andromeda_search3_save($path,$state);
    }finally{flock($lock,LOCK_UN);fclose($lock);}
}

function anytour_andromeda_search3_run(array $request, PDO $pdo, array $saved, array $config, string $session): array {
    $criteria=anytour_andromeda_search3_params($request,$pdo,$saved);$number=$criteria['PAGE'];
    $directory=dirname($config['catalog_path']).'/searches';
    if(!is_dir($directory)&&!mkdir($directory,0700)&&!is_dir($directory))throw new RuntimeException();
    $base=$criteria;unset($base['PAGE']);$ref=hash('sha256','paged-v1'.$session.json_encode($base));
    $lock=fopen($directory.'/'.$ref.'.lock','c');if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException();
    try {
        $firstPath=$directory.'/'.$ref.'-1.json';
        $first=is_file($firstPath)?json_decode(file_get_contents($firstPath),true,32,JSON_THROW_ON_ERROR):[];
        if($number>1){
            if(!$first || !in_array($first['status']??null,['complete','partial'],true) || time()>=($first['store']['expires_at']??0))throw new RuntimeException('page_context_missing');
            $prefix=$directory.'/'.$ref.'-'.$first['store']['created_at'].'-';
            $previousPath=$number===2?$firstPath:$prefix.($number-1).'.json';
            $previous=is_file($previousPath)?json_decode(file_get_contents($previousPath),true,32,JSON_THROW_ON_ERROR):[];
            if(!in_array($previous['status']??null,['complete','partial'],true))throw new RuntimeException('previous_page_missing');
            // SAMO may change PAGES_COUNT while collecting operator responses.
            // Authorize the next page from the latest completed response.
            if($number>($previous['store']['snapshot']['pages_count']??0))throw new RuntimeException('page_outside_latest_response');
            $path=$prefix.$number.'.json';$generation=$first['generation'];
        }else{$path=$firstPath;$generation=$request['generation'];}
        $state=is_file($path)?json_decode(file_get_contents($path),true,32,JSON_THROW_ON_ERROR):[];
        if($number===1 && $state && in_array($state['status']??null,['complete','partial'],true) && time()>=($state['store']['expires_at']??0))$state=[];
        $handler=new AnyTourAndromedaSearch($state,static function($next)use($path){return anytour_andromeda_search3_save($path,$next);},true,true);
        if($state){$page=$handler->resume($ref,$state['generation'],time());}
        else{
            $lookup=$pdo->prepare("SELECT i.supplier_namespace,i.external_hotel_id,i.local_hotel_id AS catalog_hotel_id,h.id AS existing_catalog_hotel_id,i.decision_status FROM andromeda_hotel_identities i JOIN catalog_hotels h ON h.id=i.local_hotel_id WHERE i.decision_status='accepted' AND h.is_active=1 AND h.country_id=? ORDER BY i.external_hotel_id");
            $lookup->execute([(int)($saved['local_country_id']??1)]);$identities=$lookup->fetchAll(PDO::FETCH_ASSOC);
            $resolver=AnyTourAndromedaHotelResolver::fromRows($identities,hash('sha256',json_encode($identities)));
            $transport=new AnyTourAndromedaTransport(true);
            $client=new AnyTourAndromedaClient(static function($url,$options)use($transport,$directory){
                anytour_andromeda_search3_budget(dirname($directory));return $transport($url,$options);
            },true);
            $authPath=$directory.'/'.$ref.'-auth.json';
            if($number>1){
                $auth=is_file($authPath)?json_decode(file_get_contents($authPath),true,8,JSON_THROW_ON_ERROR):[];
                if(($auth['created_at']??null)!==$first['store']['created_at'])throw new RuntimeException('page_session_expired');
                $client->restorePrivateSession($auth['session']??[]);
            }
            $page=$handler->start($criteria,$ref,$generation,time(),$client,$config['username'],$config['password'],$resolver);
            if($number===1 && $client->privateSession())anytour_andromeda_search3_save($authPath,['created_at'=>$state['store']['created_at'],'session'=>$client->privateSession()]);
        }
        if(in_array($page['status'],['pending','unavailable'],true))throw new RuntimeException('supplier_unavailable');
        return anytour_andromeda_search3_project($request,$pdo,$page,$saved);
    }finally{flock($lock,LOCK_UN);fclose($lock);}
}


/** Only explicitly installed country catalogs are eligible for live search. */
function anytour_andromeda_search3_catalog(array $config, array $request): array {
    $id=$request['params']['countryId']??null;
    if(!is_scalar($id)||!preg_match('/^[1-9][0-9]{0,8}$/D',(string)$id))throw new InvalidArgumentException();
    $path=(string)$id==='1'?$config['catalog_path']:dirname($config['catalog_path']).'/countries/'.(string)$id.'.json';
    if(!is_file($path)||is_link($path))throw new DomainException('country_not_loaded');
    $saved=json_decode(file_get_contents($path),true,32,JSON_THROW_ON_ERROR);
    if((string)($saved['local_country_id']??1)!==(string)$id)throw new DomainException('country_not_loaded');
    return $saved;
}

function anytour_andromeda_search3_http(): void {
    header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
    if(!is_file(__DIR__.'/.andromeda-private.php'))anytour_anex_search3_out(['ok'=>false,'error'=>'not_found'],404);
    $config=require __DIR__.'/.andromeda-private.php';
    if(($config['enabled']??false)!==true)anytour_anex_search3_out(['ok'=>false,'error'=>'not_found'],404);
    if(($_SERVER['REQUEST_METHOD']??'')!=='POST')anytour_anex_search3_out(['ok'=>false,'error'=>'method_not_allowed'],405);
    if(!in_array($_SERVER['HTTP_SEC_FETCH_SITE']??'same-origin',['same-origin','none'],true))anytour_anex_search3_out(['ok'=>false,'error'=>'forbidden'],403);
    if(($_SERVER['HTTP_X_REQUESTED_WITH']??'')!=='AnyTourSearch3' || (isset($_SERVER['HTTP_ORIGIN']) && $_SERVER['HTTP_ORIGIN']!=='https://anytoour.ru'))anytour_anex_search3_out(['ok'=>false,'error'=>'forbidden'],403);
    if(strtolower(trim(explode(';',$_SERVER['CONTENT_TYPE']??'')[0]))!=='application/json')anytour_anex_search3_out(['ok'=>false,'error'=>'invalid_request'],400);
    $raw=file_get_contents('php://input',false,null,0,16385);
    if(strlen($raw)>16384)anytour_anex_search3_out(['ok'=>false,'error'=>'invalid_request'],400);
    // Read-only search is public; supplier credentials stay in private server config.
    session_name('ANYTOUR_ANDROMEDA_SEARCH3');
    ini_set('session.use_strict_mode','1');ini_set('session.use_only_cookies','1');
    session_set_cookie_params(['secure'=>true,'httponly'=>true,'samesite'=>'Lax','path'=>'/_preview/search3-anex-candidate/']);
    if(!session_start())anytour_anex_search3_out(['ok'=>false,'error'=>'supplier_unavailable'],503);
    $session=session_id();session_write_close();
    try{
        $request=json_decode($raw,true,16,JSON_THROW_ON_ERROR);if(!is_array($request))throw new InvalidArgumentException();
        $root=realpath($_SERVER['DOCUMENT_ROOT']??'');if(!$root||basename($root)!=='anytoour.ru')throw new RuntimeException();
        require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
        $pdo=v2_data_db();$saved=anytour_andromeda_search3_catalog($config,$request);
        $saved['excluded_operator_ids']=$config['excluded_operator_ids']??[];
        // Reject unsupported form conditions before spending supplier requests.
        anytour_andromeda_search3_params($request,$pdo,$saved);
        $data=anytour_andromeda_search3_run($request,$pdo,$saved,$config,$session);
        anytour_anex_search3_out(['ok'=>true,'data'=>$data],200);
    }catch(OverflowException $e){anytour_anex_search3_out(['ok'=>false,'error'=>'monthly_quota_exhausted'],429);
    }catch(DomainException $e){anytour_anex_search3_out(['ok'=>false,'error'=>'search_not_supported'],422);
    }catch(InvalidArgumentException $e){anytour_anex_search3_out(['ok'=>false,'error'=>'invalid_request'],400);
    }catch(Throwable $e){anytour_anex_search3_out(['ok'=>false,'error'=>'supplier_unavailable'],502);}
}
if(realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__)anytour_andromeda_search3_http();
