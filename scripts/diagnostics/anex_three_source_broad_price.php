<?php
declare(strict_types=1);

if (!function_exists('anex_paired_text')) {
    if (!defined('ANYTOUR_ANEX_PAIRED_LIBRARY_ONLY')) define('ANYTOUR_ANEX_PAIRED_LIBRARY_ONLY', true);
    require __DIR__ . '/anex_search3_paired_runner.php';
}
if (!class_exists('AnyTourThreeProviderMealFamily')) {
    require __DIR__ . '/../../app/integrations/three-provider-meal-family.php';
}

const ANEX_BROAD_PRICE_EXPERIMENT = 'anex_three_source_green_gold_20260912_v8';
const ANEX_BROAD_PRICE_CASES = ['anex','andromeda','tourvisor'];
const ANEX_BROAD_PRICE_DATE = '2026-09-28';
const ANEX_BROAD_PRICE_NIGHTS = 7;
const ANEX_BROAD_PRICE_TARGET_LOCAL_HOTEL = 21753;
const ANEX_BROAD_PRICE_TARGET_ANEX_HOTEL = '25084';

function anex_broad_price_input($value): array
{
    $keys=['experiment_id','case_id','country','date','nights','adults','child_ages','meal_family','currency'];
    if(!is_array($value)||count($value)!==count($keys)||array_diff($keys,array_keys($value))||array_diff(array_keys($value),$keys)
        ||($value['experiment_id']??null)!==ANEX_BROAD_PRICE_EXPERIMENT
        ||!in_array($value['case_id']??null,ANEX_BROAD_PRICE_CASES,true)
        ||($value['country']??null)!=='Turkey'||($value['date']??null)!==ANEX_BROAD_PRICE_DATE
        ||($value['nights']??null)!==ANEX_BROAD_PRICE_NIGHTS||($value['adults']??null)!==2||($value['child_ages']??null)!==[]
        ||($value['meal_family']??null)!=='ai'||($value['currency']??null)!=='RUB') throw new RuntimeException('BROAD_PRICE_INVALID_INPUT');
    return $value;
}

function anex_broad_price_norm($value,int $limit=240): string
{
    $text=anex_paired_text($value,[],$limit)??'';
    $text=function_exists('mb_strtolower')?mb_strtolower($text,'UTF-8'):strtolower($text);
    return trim((string)preg_replace('/[^\p{L}\p{N}]+/u',' ',str_replace(['ё','Ё'],'е',$text)));
}

function anex_broad_price_meal($value): ?array
{
    try { return AnyTourThreeProviderMealFamily::normalize($value); }
    catch (InvalidArgumentException) { return null; }
}

function anex_broad_price_money($value,bool $positive=true): ?string
{
    if(is_int($value)||(is_float($value)&&is_finite($value)))$value=(string)$value;
    if(!is_string($value)||!preg_match('/\A(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?\z/D',$value))return null;
    if($positive&&!preg_match('/[1-9]/',$value))return null;
    return $value;
}

function anex_broad_price_provider_id($value): ?string
{
    if(is_int($value)&&$value>0)$value=(string)$value;
    return is_string($value)&&preg_match('/\A[1-9][0-9]{0,17}\z/D',$value)?$value:null;
}

/** Bind raw PRICES id to provider-scoped tour/currency without depending on the installed normalizer revision. */
function anex_broad_price_raw_programs(array $payload): array
{
    $rows=$payload['prices']??null;if(!is_array($rows))return [];$out=[];
    foreach(array_slice($rows,0,300) as $row){if(!is_array($row))continue;$claim=$row['id']??null;if(is_int($claim)&&$claim>0)$claim=(string)$claim;
        if(!is_string($claim)||!preg_match('~\A[A-Za-z0-9][A-Za-z0-9_.:,;\~@+/=|\-]{0,2047}\z~D',$claim)||strpos($claim,'://')!==false)continue;
        $out[$claim]=['tour'=>anex_broad_price_provider_id($row['tourKey']??null),'currency'=>anex_broad_price_provider_id($row['currencyKey']??null)];}
    return $out;
}

function anex_broad_price_offer(string $provider,int $local,$external,$hotelName,$date,$nights,$adults,$children,$meal,$room,$placement,$price,$currency,$fuel=null): ?array
{
    $date=anex_paired_date($date);$amount=anex_broad_price_money($price,true);$mealContract=anex_broad_price_meal($meal);
    if($local!==ANEX_BROAD_PRICE_TARGET_LOCAL_HOTEL||$date!==ANEX_BROAD_PRICE_DATE||$amount===null||($mealContract['canonical_key']??null)!=='ai'
        ||(int)$nights!==ANEX_BROAD_PRICE_NIGHTS||(int)$adults!==2||(int)$children!==0)return null;
    $currency=strtoupper((string)(anex_paired_text($currency,[],8)??''));if($currency!=='RUB')return null;
    $fuelValue=null;if($fuel!==null){if(is_array($fuel))$fuel=$fuel['value']??$fuel['amount']??null;$fuelValue=anex_broad_price_money($fuel,false);}
    return ['provider'=>$provider,'local_hotel_id'=>$local,'external_hotel_id'=>(string)$external,
        'hotel_name'=>anex_paired_text($hotelName,[],240),'date'=>$date,'nights'=>ANEX_BROAD_PRICE_NIGHTS,'adults'=>2,'children'=>0,
        'meal_family'=>'ai','meal_key'=>'ai','meal_qualifiers'=>[],'meal_equivalence_verified'=>false,
        'meal_label'=>anex_paired_text($meal,[],100),'room'=>anex_paired_text($room,[],180),
        'room_norm'=>anex_broad_price_norm($room,180),'placement'=>anex_paired_text($placement,[],120),
        'placement_norm'=>anex_broad_price_norm($placement,120),'price'=>$amount,'currency'=>'RUB','fuel_charge'=>$fuelValue,
        'fuel_inclusion_verified'=>false,'final_price_verified'=>false];
}

function anex_broad_price_save(string $path,array $value): void
{
    $bytes=json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    if(strlen($bytes)>5000000)throw new RuntimeException('BROAD_PRICE_CHECKPOINT_LIMIT');
    $tmp=$path.'.'.bin2hex(random_bytes(8));$fh=fopen($tmp,'x');if(!$fh)throw new RuntimeException('BROAD_PRICE_CHECKPOINT_WRITE');chmod($tmp,0600);
    try{if(fwrite($fh,$bytes)!==strlen($bytes)||!fflush($fh))throw new RuntimeException('BROAD_PRICE_CHECKPOINT_WRITE');if(function_exists('fsync')&&!fsync($fh))throw new RuntimeException('BROAD_PRICE_CHECKPOINT_WRITE');}
    finally{fclose($fh);}if(!rename($tmp,$path)){@unlink($tmp);throw new RuntimeException('BROAD_PRICE_CHECKPOINT_WRITE');}
    $back=json_decode((string)file_get_contents($path),true,48,JSON_THROW_ON_ERROR);if($back!==$value)throw new RuntimeException('BROAD_PRICE_CHECKPOINT_READBACK');
}

function anex_broad_price_local(PDO $pdo): array
{
    $q=$pdo->prepare("SELECT d.id departure_id,d.name departure_name,c.id country_id,c.name country_name FROM catalog_departures d CROSS JOIN catalog_countries c WHERE d.is_active=1 AND c.is_active=1 AND d.name IN ('Москва','Moscow') AND c.name IN ('Турция','Turkey') LIMIT 2");
    $q->execute();$rows=$q->fetchAll(PDO::FETCH_ASSOC);if(count($rows)!==1)throw new RuntimeException('BROAD_PRICE_LOCAL_IDENTITY');return $rows[0];
}

function anex_broad_price_anex(PDO $pdo,array $local,AnyTourAnexSearchMappingRegistry $registry,array &$secrets): array
{
    $home=(string)getenv('HOME');require_once $home.'/.anytoour-anex/search3-preview.php';
    if(!defined('ANEX_API_TOKEN')||!is_string(ANEX_API_TOKEN)||trim(ANEX_API_TOKEN)==='')throw new RuntimeException('BROAD_PRICE_ANEX_TOKEN_REQUIRED');
    $secrets[]=ANEX_API_TOKEN;$client=new AnyTourAnexClient(ANEX_API_TOKEN);$cache=[];
    $departure=anytour_anex_search3_dictionary_id(anytour_anex_search3_dictionary($client,'SearchTour_TOWNFROMS',[],$cache),[$local['departure_name'],'Москва','Moscow']);
    $country=anytour_anex_search3_dictionary_id(anytour_anex_search3_dictionary($client,'SearchTour_STATES',['TOWNFROMINC'=>$departure],$cache),[$local['country_name'],'Турция','Turkey']);
    $dated=['TOWNFROMINC'=>$departure,'STATEINC'=>$country,'CHECKIN_BEG'=>'20260928','CHECKIN_END'=>'20260928','ADULT'=>2,'CHILD'=>0];
    $currency=anytour_anex_search3_dictionary_id(anytour_anex_search3_dictionary($client,'SearchTour_CURRENCIES',$dated,$cache),['RUB','RUR','Рубль','Рубли','Руб']);
    $criteria=['supplier_namespace'=>'anex_online','departure_id'=>$departure,'destination_id'=>$country,'currency_id'=>$currency,
        'checkin_begin'=>ANEX_BROAD_PRICE_DATE,'checkin_end'=>ANEX_BROAD_PRICE_DATE,'nights_from'=>ANEX_BROAD_PRICE_NIGHTS,'nights_till'=>ANEX_BROAD_PRICE_NIGHTS,
        'adults'=>2,'children'=>0,'child_ages'=>[],'hotel_ids'=>[ANEX_BROAD_PRICE_TARGET_ANEX_HOTEL]];
    $params=['TOWNFROMINC'=>$departure,'STATEINC'=>$country,'CURRENCY'=>$currency,'CHECKIN_BEG'=>'20260928','CHECKIN_END'=>'20260928',
        'NIGHTS_FROM'=>ANEX_BROAD_PRICE_NIGHTS,'NIGHTS_TILL'=>ANEX_BROAD_PRICE_NIGHTS,'ADULT'=>2,'CHILD'=>0,'HOTELS'=>ANEX_BROAD_PRICE_TARGET_ANEX_HOTEL,
        'FREIGHT'=>1,'FILTER'=>1,'PRICEPAGE'=>1,'PARTITION_PRICE'=>32,'SORT'=>'ASC','DYN_SEPARATE'=>1];
    $raw=$client->request('SearchTour_PRICES',$params);$rawPrograms=anex_broad_price_raw_programs($raw);
    $result=anytour_anex_normalize_prices($raw,$criteria,$registry->previewResolver(),$secrets);$offers=[];$mapped=0;$unmapped=0;$programRows=0;
    foreach($result['offers'] as $offer){if(($offer['hotel']['external_id']??null)!==ANEX_BROAD_PRICE_TARGET_ANEX_HOTEL)continue;
        $localId=$offer['hotel']['local_id']??null;if(!is_int($localId)||$localId<1){++$unmapped;continue;}++$mapped;
        $price=($offer['price']['currency']??'')==='RUB'?$offer['price']:($offer['converted_price']??$offer['price']);
        $row=anex_broad_price_offer('anex',$localId,$offer['hotel']['external_id']??'',$offer['hotel']['name']??null,$offer['checkin']??null,$offer['nights']??null,
            $offer['adults']??null,$offer['children']??null,$offer['meal']??null,$offer['room']??null,$offer['hotel_place']??null,$price['amount']??null,$price['currency']??null,null);
        if($row!==null){$context=$rawPrograms[$offer['supplier_offer_id']??'']??[];$row['supplier_tour_program_id']=$context['tour']??null;$row['supplier_currency_id']=$context['currency']??null;
            if($row['supplier_tour_program_id']!==null)++$programRows;if(count($offers)<1500)$offers[]=$row;}}
    $obs=AnyTourAnexSearchObservations::record($pdo,$result['offers'],['country_id'=>(int)$local['country_id'],'anex_country_id'=>$country,'checkin_from'=>ANEX_BROAD_PRICE_DATE,'checkin_to'=>ANEX_BROAD_PRICE_DATE]);
    return ['offers'=>$offers,'observation'=>$obs,'requests'=>$client->requestsMade(),'received_offers'=>count($result['offers']),'mapped_received'=>$mapped,'unmapped_received'=>$unmapped,
        'target_local_hotel_id'=>ANEX_BROAD_PRICE_TARGET_LOCAL_HOTEL,'target_external_hotel_id'=>ANEX_BROAD_PRICE_TARGET_ANEX_HOTEL,
        'program_id_observed_offers'=>$programRows,'raw_program_bindings'=>count(array_filter($rawPrograms,static fn($x)=>($x['tour']??null)!==null)),'rejected_count'=>$result['rejected_count']??null,'truncated_count'=>$result['truncated_count']??null,
        'coverage'=>['state'=>'bounded','reason'=>'pricepage_1_target_hotel_only','page'=>1,'all_pages_retained'=>false],
        'source_price_semantics'=>'search_price_unverified_until_additional_prices_or_quote',
        'program_semantics'=>'raw SearchTour_PRICES id→tourKey/currencyKey binding; provider_scoped; AdditionalPricesDaily cohort evidence only',
        'fuel_field_semantics'=>'no_fuel_field_in_search_projection; AdditionalPricesDaily tracked separately'];
}

function anex_broad_price_andromeda(PDO $pdo,array $local): array
{
    $root=realpath((string)getenv('HOME').'/www/anytoour.ru');$preview=realpath($root.'/_preview/search3-anex-candidate');
    $_SERVER['SCRIPT_FILENAME']='';require_once $preview.'/api-andromeda-search3-preview.php';$configPath=$preview.'/.andromeda-private.php';
    if(!is_file($configPath)||is_link($configPath))throw new RuntimeException('BROAD_PRICE_ANDROMEDA_CONFIG');$config=require $configPath;
    if(!is_array($config)||($config['enabled']??null)!==true)throw new RuntimeException('BROAD_PRICE_ANDROMEDA_CONFIG');
    $request=['generation'=>26091208,'page'=>1,'params'=>['departureId'=>(int)$local['departure_id'],'countryId'=>(int)$local['country_id'],
        'dateFrom'=>ANEX_BROAD_PRICE_DATE,'dateTo'=>ANEX_BROAD_PRICE_DATE,'nightsFrom'=>ANEX_BROAD_PRICE_NIGHTS,'nightsTo'=>ANEX_BROAD_PRICE_NIGHTS,'adults'=>2,'childs'=>[],
        'currency'=>'RUB','meal'=>7,'onlyCharter'=>false,'onlyDirect'=>false,'hotelIds'=>[ANEX_BROAD_PRICE_TARGET_LOCAL_HOTEL],'regionIds'=>[],'subregionIds'=>[],
        'arrivalId'=>null,'operatorIds'=>[],'hotelServices'=>[],'hotelTypes'=>[]],'andromeda_operator_ids'=>['5']];
    $saved=anytour_andromeda_search3_catalog($config,$request);$result=anytour_andromeda_search3_run($request,$pdo,$saved,$config,'three-green-gold-20260912-v8');$offers=[];
    foreach($result['hotels']??[] as $hotel){$localId=(int)($hotel['local_id']??0);if($localId!==ANEX_BROAD_PRICE_TARGET_LOCAL_HOTEL)continue;
        foreach($hotel['tours']??[] as $tour){$price=$tour['price']??[];$row=anex_broad_price_offer('andromeda',$localId,$localId,$hotel['name']??null,$tour['checkin']??null,$tour['nights']??null,
            $tour['adults']??null,$tour['children']??null,$tour['meal']??null,$tour['room']??null,$tour['placement']??null,$price['amount']??null,$price['currency']??null,null);
            if($row!==null&&count($offers)<1500)$offers[]=$row;}}
    $pages=(int)($result['pages_count']??0);$page=(int)($result['page']??1);
    $coverage=($pages===1&&$page===1)?['state'=>'complete','reason'=>'all_advertised_target_hotel_pages_retained','page'=>1,'pages_count'=>1,'all_pages_retained'=>true]
        :['state'=>'partial','reason'=>'target_hotel_page_1_only','page'=>$page,'pages_count'=>$pages>0?$pages:null,'all_pages_retained'=>false];
    return ['offers'=>$offers,'received_offers'=>$result['received_offers']??null,'mapped_offers'=>$result['mapped_offers']??null,'pages_count'=>$result['pages_count']??null,
        'page'=>$result['page']??1,'first_page_only'=>true,'target_local_hotel_id'=>ANEX_BROAD_PRICE_TARGET_LOCAL_HOTEL,'coverage'=>$coverage,
        'source_price_semantics'=>'andromeda_search_price_unverified_until_package_or_calc',
        'fuel_field_semantics'=>'documented action=price has no separate fuel field','observation_semantics'=>'runtime records raw supplier page before current-mapping projection'];
}

function anex_broad_price_tv(PDO $pdo,array $local,array &$requests,array &$secrets): array
{
    $root=realpath((string)getenv('HOME').'/www/anytoour.ru');$helper=is_file($root.'/data/tourvisor-client-v1.php')?$root.'/data/tourvisor-client-v1.php':$root.'/v2/data/tourvisor-client-v1.php';
    require_once $helper;$token=v2_data_tourvisor_token();if($token==='')throw new RuntimeException('BROAD_PRICE_TV_TOKEN_REQUIRED');$secrets[]=$token;$deadline=microtime(true)+160;
    $criteria=['departureId'=>(int)$local['departure_id'],'countryId'=>(int)$local['country_id'],'dateFrom'=>ANEX_BROAD_PRICE_DATE,'dateTo'=>ANEX_BROAD_PRICE_DATE,
        'nightsFrom'=>ANEX_BROAD_PRICE_NIGHTS,'nightsTo'=>ANEX_BROAD_PRICE_NIGHTS,'adults'=>2,'childs'=>[],'currency'=>'RUB','onlyCharter'=>false,'onlyDirect'=>false];
    $operator=anex_paired_operator(anex_paired_tv_get('/operators',['departureId'=>$criteria['departureId'],'countryId'=>$criteria['countryId']],$token,$deadline,$requests));
    $criteria['operatorIds']=[$operator['id']];usleep(1050000);$start=anex_paired_tv_get('/tours/search',$criteria,$token,$deadline,$requests);$searchId=$start['searchId']??null;
    if(!(is_int($searchId)||is_string($searchId))||!preg_match('/\A[1-9][0-9]{0,17}\z/D',(string)$searchId))throw new RuntimeException('BROAD_PRICE_TV_SEARCH_ID');
    $complete=false;for($poll=0;$poll<8&&microtime(true)<$deadline-30;++$poll){sleep($poll===0?1:10);$status=anex_paired_tv_get('/tours/search/'.$searchId.'/status',['operatorStatus'=>false],$token,$deadline,$requests);
        $complete=(is_numeric($status['progress']??null)&&(float)$status['progress']>=100)||(is_string($status['status']??null)&&strtolower($status['status'])==='complete');if($complete)break;}
    usleep(1050000);$groups=anex_paired_tv_get('/tours/search/'.$searchId,['limit'=>100],$token,$deadline,$requests);$offers=[];$hotelCount=0;
    foreach($groups as $hotel){if(!is_array($hotel))continue;$localId=(int)($hotel['id']??0);if($localId<1)continue;++$hotelCount;if($localId!==ANEX_BROAD_PRICE_TARGET_LOCAL_HOTEL)continue;
        foreach(is_array($hotel['tours']??null)?$hotel['tours']:[] as $tour){if(!is_array($tour))continue;$op=$tour['operator']??null;
            $opId=anex_paired_id($tour['operatorId']??(is_array($op)?($op['id']??null):null));$opName=anex_paired_text($op,$secrets);
            $same=in_array(anex_paired_operator_name($opName),['anex','anex tour','anextour','анекс','анекс тур'],true);if(($opId!==null&&$opId!==$operator['id'])||($opId===null&&!$same))continue;
            $row=anex_broad_price_offer('tourvisor',$localId,$localId,$hotel['name']??null,$tour['date']??null,$tour['nights']??null,$tour['adults']??2,$tour['children']??$tour['childs']??0,
                $tour['meal']??null,$tour['roomType']??null,$tour['placement']??null,$tour['price']??null,$tour['currency']??'RUB',$tour['fuelCharge']??null);
            if($row!==null&&count($offers)<1500)$offers[]=$row;}}
    return ['offers'=>$offers,'search_complete'=>$complete,'groups_received'=>count($groups),'mapped_hotel_groups'=>$hotelCount,'operator'=>'ANEX',
        'target_local_hotel_id'=>ANEX_BROAD_PRICE_TARGET_LOCAL_HOTEL,'target_filter_stage'=>'local_response_only_protected_request_unchanged',
        'coverage'=>['state'=>'bounded','reason'=>'status_without_continue_no_growth','status_complete'=>$complete,'all_pages_retained'=>false],
        'source_price_semantics'=>'tourvisor_documented_final_display_price','fuel_field_semantics'=>'api_exposes_fuelCharge_separately_do_not_add_automatically'];
}

function anex_broad_price_completed(array $out,array $result): array
{
    return array_replace($out,['status'=>'completed','offers'=>$result['offers']??[],'details'=>array_diff_key($result,['offers'=>true]),'supplier_effect'=>'read_only_search_completed','reused'=>false]);
}
function anex_broad_price_reused(array $result): array{return array_replace($result,['reused'=>true]);}

function anex_broad_price_main(): array
{
    $started=microtime(true);$pdo=null;$reserved=false;$lock=null;$secrets=[];$tv=[];$state=null;$path=null;$case=null;$stage='PRECHECK';
    $out=['schema_version'=>1,'experiment_id'=>ANEX_BROAD_PRICE_EXPERIMENT,'status'=>'blocked','case_id'=>null,'offers'=>[],
        'supplier_effect'=>'none','automatic_retry'=>false,'booking_calls'=>0,'broninit_calls'=>0,'mapping_writes'=>0,'observation_writes_allowed'=>true];
    try{$raw=file_get_contents('php://stdin',false,null,0,4097);if(!is_string($raw)||$raw===''||strlen($raw)>4096)throw new RuntimeException('BROAD_PRICE_INVALID_INPUT');
        $input=anex_broad_price_input(json_decode($raw,true,8,JSON_THROW_ON_ERROR));$case=$input['case_id'];$out['case_id']=$case;
        $home=(string)getenv('HOME');$root=realpath($home.'/www/anytoour.ru');$preview=realpath($root.'/_preview/search3-anex-candidate');
        if(!$root||!$preview||$preview!==$root.'/_preview/search3-anex-candidate'||!in_array(realpath((string)getcwd()),[$root,$preview],true))throw new RuntimeException('BROAD_PRICE_RUNTIME');
        $stage='DEPENDENCY';require_once $home.'/.anytoour-anex/search3-preview.php';require_once $preview.'/app/integrations/anex-search.php';require_once $preview.'/app/integrations/anex-search-mapping-registry.php';require_once $preview.'/app/integrations/anex-search-observations.php';
        $_SERVER['SCRIPT_FILENAME']='';require_once $preview.'/api-anex-search3-preview.php';if(!function_exists('anytour_anex_search3_dictionary')||!function_exists('anytour_anex_search3_dictionary_id'))throw new RuntimeException('BROAD_PRICE_HELPER_MISSING');
        $db=is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php';require_once $db;$pdo=v2_data_db();if(!$pdo instanceof PDO||$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql')throw new RuntimeException('BROAD_PRICE_DB');
        $local=anex_broad_price_local($pdo);$registry=AnyTourAnexSearchMappingRegistry::fromPdo($pdo);
        $stage='CHECKPOINT';$dir=$home.'/.anytoour-anex';if(!is_dir($dir)||is_link($dir))throw new RuntimeException('BROAD_PRICE_CHECKPOINT_DIR');$path=$dir.'/'.ANEX_BROAD_PRICE_EXPERIMENT.'.json';
        $lock=fopen($path.'.lock','c');if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('BROAD_PRICE_LOCK');$state=is_file($path)?json_decode((string)file_get_contents($path),true,48,JSON_THROW_ON_ERROR):[];
        if($state&&(($state['experiment_id']??null)!==ANEX_BROAD_PRICE_EXPERIMENT||!is_array($state['cases']??null)))throw new RuntimeException('BROAD_PRICE_CHECKPOINT_INVALID');
        if(!$state){$state=['schema_version'=>1,'experiment_id'=>ANEX_BROAD_PRICE_EXPERIMENT,'spec'=>array_diff_key($input,['case_id'=>true]),'cases'=>[]];anex_broad_price_save($path,$state);}
        $prior=$state['cases'][$case]??null;if(is_array($prior)){if(($prior['status']??null)==='completed'&&is_array($prior['result']??null))return anex_broad_price_reused($prior['result']);throw new RuntimeException('BROAD_PRICE_CASE_NOT_REPLAYABLE');}
        $state['cases'][$case]=['status'=>'reserved','reserved_at'=>gmdate('c')];anex_broad_price_save($path,$state);$reserved=true;$out['supplier_effect']='unknown_after_reservation';
        if($case==='anex'){$stage='ANEX_SEARCH';$result=anex_broad_price_anex($pdo,$local,$registry,$secrets);}elseif($case==='andromeda'){$stage='ANDROMEDA_SEARCH';$result=anex_broad_price_andromeda($pdo,$local);}else{$stage='TOURVISOR_SEARCH';$result=anex_broad_price_tv($pdo,$local,$tv,$secrets);}
        $out=anex_broad_price_completed($out,$result);$state['cases'][$case]=['status'=>'completed','completed_at'=>gmdate('c'),'result'=>$out];anex_broad_price_save($path,$state);$reserved=false;
    }catch(Throwable $e){$code=$e->getMessage();$safe=preg_match('/\A(?:BROAD_PRICE|ANEX|ANDROMEDA)_[A-Z0-9_]{1,80}\z/D',$code)?$code:'BROAD_PRICE_'.$stage.'_UNCONFIRMED';$out['reason']=$safe;
        if($reserved&&is_array($state)&&is_string($case)&&is_string($path)){$state['cases'][$case]=['status'=>'unknown','recorded_at'=>gmdate('c'),'reason'=>$safe];try{anex_broad_price_save($path,$state);}catch(Throwable $ignored){}$out['status']='unknown';$out['supplier_effect']='unknown';}}
    finally{if(is_resource($lock)){flock($lock,LOCK_UN);fclose($lock);}if($pdo instanceof PDO&&$pdo->inTransaction())$pdo->rollBack();$out['elapsed_ms']=(int)round((microtime(true)-$started)*1000);$out['tv_request_count']=count($tv);}
    $json=json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);foreach($secrets as $secret)if($secret!==''&&is_string($json)&&strpos($json,$secret)!==false)return ['schema_version'=>1,'experiment_id'=>ANEX_BROAD_PRICE_EXPERIMENT,'case_id'=>$out['case_id'],'status'=>'unknown','reason'=>'BROAD_PRICE_OUTPUT_REDACTED','supplier_effect'=>'unknown','automatic_retry'=>false,'booking_calls'=>0,'broninit_calls'=>0,'mapping_writes'=>0,'observation_writes_allowed'=>true];
    return $out;
}

if(!defined('ANYTOUR_ANEX_BROAD_PRICE_LIBRARY_ONLY')){error_reporting(0);ob_start();$report=anex_broad_price_main();ob_end_clean();echo json_encode($report,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),"\n";exit(($report['status']??null)==='completed'?0:1);}
