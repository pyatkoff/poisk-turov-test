<?php
declare(strict_types=1);

if (!function_exists('anex_paired_text')) {
    if (!defined('ANYTOUR_ANEX_PAIRED_LIBRARY_ONLY')) define('ANYTOUR_ANEX_PAIRED_LIBRARY_ONLY', true);
    require __DIR__ . '/anex_search3_paired_runner.php';
}
if (!class_exists('AnyTourThreeProviderMealFamily')) {
    require __DIR__ . '/../../app/integrations/three-provider-meal-family.php';
}

const ANEX_BROAD_PRICE_EXPERIMENT = 'anex_green_gold_program_20260912_v9';
const ANEX_BROAD_PRICE_CASES = ['anex'];
const ANEX_BROAD_PRICE_DATE = '2026-10-05';
const ANEX_BROAD_PRICE_NIGHTS = 7;
const ANEX_BROAD_PRICE_TARGET_LOCAL_HOTEL = 21753;
const ANEX_BROAD_PRICE_TARGET_ANEX_HOTEL = '25084';

function anex_broad_price_input($value): array
{
    $keys=['experiment_id','case_id','country','date','nights','adults','child_ages','meal_family','currency'];
    if(!is_array($value)||count($value)!==count($keys)||array_diff($keys,array_keys($value))||array_diff(array_keys($value),$keys)
        ||($value['experiment_id']??null)!==ANEX_BROAD_PRICE_EXPERIMENT||($value['case_id']??null)!=='anex'
        ||($value['country']??null)!=='Turkey'||($value['date']??null)!==ANEX_BROAD_PRICE_DATE
        ||($value['nights']??null)!==ANEX_BROAD_PRICE_NIGHTS||($value['adults']??null)!==2||($value['child_ages']??null)!==[]
        ||($value['meal_family']??null)!=='ai'||($value['currency']??null)!=='RUB') throw new RuntimeException('BROAD_PRICE_INVALID_INPUT');
    return $value;
}
function anex_broad_price_norm($value,int $limit=240): string
{
    $text=anex_paired_text($value,[],$limit)??'';$text=function_exists('mb_strtolower')?mb_strtolower($text,'UTF-8'):strtolower($text);
    return trim((string)preg_replace('/[^\p{L}\p{N}]+/u',' ',str_replace(['ё','Ё'],'е',$text)));
}
function anex_broad_price_meal($value): ?array
{
    try{return AnyTourThreeProviderMealFamily::normalize($value);}catch(InvalidArgumentException){return null;}
}
function anex_broad_price_money($value,bool $positive=true): ?string
{
    if(is_int($value)||(is_float($value)&&is_finite($value)))$value=(string)$value;
    if(!is_string($value)||!preg_match('/\A(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?\z/D',$value))return null;
    if($positive&&!preg_match('/[1-9]/',$value))return null;return $value;
}
function anex_broad_price_provider_id($value): ?string
{
    if(is_int($value)&&$value>0)$value=(string)$value;return is_string($value)&&preg_match('/\A[1-9][0-9]{0,17}\z/D',$value)?$value:null;
}
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
    if($provider!=='anex'||$local!==ANEX_BROAD_PRICE_TARGET_LOCAL_HOTEL||(string)$external!==ANEX_BROAD_PRICE_TARGET_ANEX_HOTEL
        ||$date!==ANEX_BROAD_PRICE_DATE||$amount===null||($mealContract['canonical_key']??null)!=='ai'
        ||(int)$nights!==ANEX_BROAD_PRICE_NIGHTS||(int)$adults!==2||(int)$children!==0)return null;
    $currency=strtoupper((string)(anex_paired_text($currency,[],8)??''));if($currency!=='RUB')return null;
    return ['provider'=>'anex','local_hotel_id'=>$local,'external_hotel_id'=>(string)$external,'hotel_name'=>anex_paired_text($hotelName,[],240),
        'date'=>$date,'nights'=>ANEX_BROAD_PRICE_NIGHTS,'adults'=>2,'children'=>0,'meal_family'=>'ai','meal_key'=>'ai','meal_qualifiers'=>[],
        'meal_equivalence_verified'=>false,'meal_label'=>anex_paired_text($meal,[],100),'room'=>anex_paired_text($room,[],180),'room_norm'=>anex_broad_price_norm($room,180),
        'placement'=>anex_paired_text($placement,[],120),'placement_norm'=>anex_broad_price_norm($placement,120),'price'=>$amount,'currency'=>'RUB','fuel_charge'=>null,
        'fuel_inclusion_verified'=>false,'final_price_verified'=>false];
}
function anex_broad_price_save(string $path,array $value): void
{
    $bytes=json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);if(strlen($bytes)>2000000)throw new RuntimeException('BROAD_PRICE_CHECKPOINT_LIMIT');
    $tmp=$path.'.'.bin2hex(random_bytes(8));$fh=fopen($tmp,'x');if(!$fh)throw new RuntimeException('BROAD_PRICE_CHECKPOINT_WRITE');chmod($tmp,0600);
    try{if(fwrite($fh,$bytes)!==strlen($bytes)||!fflush($fh))throw new RuntimeException('BROAD_PRICE_CHECKPOINT_WRITE');if(function_exists('fsync')&&!fsync($fh))throw new RuntimeException('BROAD_PRICE_CHECKPOINT_WRITE');}finally{fclose($fh);}
    if(!rename($tmp,$path)){@unlink($tmp);throw new RuntimeException('BROAD_PRICE_CHECKPOINT_WRITE');}
    if(json_decode((string)file_get_contents($path),true,32,JSON_THROW_ON_ERROR)!==$value)throw new RuntimeException('BROAD_PRICE_CHECKPOINT_READBACK');
}
function anex_broad_price_local(PDO $pdo): array
{
    $q=$pdo->prepare("SELECT d.id departure_id,d.name departure_name,c.id country_id,c.name country_name FROM catalog_departures d CROSS JOIN catalog_countries c WHERE d.is_active=1 AND c.is_active=1 AND d.name IN ('Москва','Moscow') AND c.name IN ('Турция','Turkey') LIMIT 2");
    $q->execute();$rows=$q->fetchAll(PDO::FETCH_ASSOC);if(count($rows)!==1)throw new RuntimeException('BROAD_PRICE_LOCAL_IDENTITY');return $rows[0];
}
function anex_broad_price_pause(): void{usleep(3200000);}
function anex_broad_price_anex(PDO $pdo,array $local,AnyTourAnexSearchMappingRegistry $registry,array &$secrets): array
{
    $home=(string)getenv('HOME');require_once $home.'/.anytoour-anex/search3-preview.php';
    if(!defined('ANEX_API_TOKEN')||!is_string(ANEX_API_TOKEN)||trim(ANEX_API_TOKEN)==='')throw new RuntimeException('BROAD_PRICE_ANEX_TOKEN_REQUIRED');
    $secrets[]=ANEX_API_TOKEN;$client=new AnyTourAnexClient(ANEX_API_TOKEN);$cache=[];
    $departure=anytour_anex_search3_dictionary_id(anytour_anex_search3_dictionary($client,'SearchTour_TOWNFROMS',[],$cache),[$local['departure_name'],'Москва','Moscow']);anex_broad_price_pause();
    $country=anytour_anex_search3_dictionary_id(anytour_anex_search3_dictionary($client,'SearchTour_STATES',['TOWNFROMINC'=>$departure],$cache),[$local['country_name'],'Турция','Turkey']);anex_broad_price_pause();
    $dated=['TOWNFROMINC'=>$departure,'STATEINC'=>$country,'CHECKIN_BEG'=>'20261005','CHECKIN_END'=>'20261005','ADULT'=>2,'CHILD'=>0];
    $currency=anytour_anex_search3_dictionary_id(anytour_anex_search3_dictionary($client,'SearchTour_CURRENCIES',$dated,$cache),['RUB','RUR','Рубль','Рубли','Руб']);anex_broad_price_pause();
    $criteria=['supplier_namespace'=>'anex_online','departure_id'=>$departure,'destination_id'=>$country,'currency_id'=>$currency,'checkin_begin'=>ANEX_BROAD_PRICE_DATE,'checkin_end'=>ANEX_BROAD_PRICE_DATE,
        'nights_from'=>ANEX_BROAD_PRICE_NIGHTS,'nights_till'=>ANEX_BROAD_PRICE_NIGHTS,'adults'=>2,'children'=>0,'child_ages'=>[],'hotel_ids'=>[ANEX_BROAD_PRICE_TARGET_ANEX_HOTEL]];
    $params=['TOWNFROMINC'=>$departure,'STATEINC'=>$country,'CURRENCY'=>$currency,'CHECKIN_BEG'=>'20261005','CHECKIN_END'=>'20261005','NIGHTS_FROM'=>7,'NIGHTS_TILL'=>7,
        'ADULT'=>2,'CHILD'=>0,'HOTELS'=>ANEX_BROAD_PRICE_TARGET_ANEX_HOTEL,'FREIGHT'=>1,'FILTER'=>1,'PRICEPAGE'=>1,'PARTITION_PRICE'=>32,'SORT'=>'ASC','DYN_SEPARATE'=>1];
    $raw=$client->request('SearchTour_PRICES',$params);$programs=anex_broad_price_raw_programs($raw);$result=anytour_anex_normalize_prices($raw,$criteria,$registry->previewResolver(),$secrets);
    $offers=[];$pairs=[];
    foreach($result['offers']??[] as $offer){if(($offer['hotel']['external_id']??null)!==ANEX_BROAD_PRICE_TARGET_ANEX_HOTEL)continue;$localId=$offer['hotel']['local_id']??null;if(!is_int($localId))continue;
        $price=($offer['price']['currency']??'')==='RUB'?$offer['price']:($offer['converted_price']??$offer['price']);
        $row=anex_broad_price_offer('anex',$localId,$offer['hotel']['external_id']??'',$offer['hotel']['name']??null,$offer['checkin']??null,$offer['nights']??null,$offer['adults']??null,$offer['children']??null,
            $offer['meal']??null,$offer['room']??null,$offer['hotel_place']??null,$price['amount']??null,$price['currency']??null,null);
        if($row===null)continue;$ctx=$programs[$offer['supplier_offer_id']??'']??[];$row['supplier_tour_program_id']=$ctx['tour']??null;$row['supplier_currency_id']=$ctx['currency']??null;
        if($row['supplier_tour_program_id']!==null)$pairs[$row['supplier_tour_program_id'].'|'.($row['supplier_currency_id']??'')]=['tour'=>$row['supplier_tour_program_id'],'currency'=>$row['supplier_currency_id']];
        if(count($offers)<100)$offers[]=$row;}
    return ['offers'=>$offers,'requests'=>$client->requestsMade(),'received_offers'=>count($result['offers']??[]),'program_pairs'=>array_values($pairs),
        'coverage'=>['state'=>'bounded','reason'=>'pricepage_1_target_hotel_only','page'=>1,'all_pages_retained'=>false],
        'target_local_hotel_id'=>ANEX_BROAD_PRICE_TARGET_LOCAL_HOTEL,'target_external_hotel_id'=>ANEX_BROAD_PRICE_TARGET_ANEX_HOTEL,
        'program_semantics'=>'raw SearchTour_PRICES id→tourKey/currencyKey on target HOTELS=25084; provider_scoped only',
        'saved_cross_source_context'=>['date'=>'2026-10-05','andromeda_search_price'=>'119952','tourvisor_display_price'=>'149548','tourvisor_fuel_charge'=>'29596','requeried'=>false]];
}
function anex_broad_price_completed(array $out,array $result): array{return array_replace($out,['status'=>'completed','offers'=>$result['offers']??[],'details'=>array_diff_key($result,['offers'=>true]),'supplier_effect'=>'read_only_search_completed','reused'=>false]);}
function anex_broad_price_reused(array $result): array{return array_replace($result,['reused'=>true]);}
function anex_broad_price_main(): array
{
    $started=microtime(true);$pdo=null;$reserved=false;$lock=null;$secrets=[];$state=null;$path=null;$stage='PRECHECK';
    $out=['schema_version'=>1,'experiment_id'=>ANEX_BROAD_PRICE_EXPERIMENT,'status'=>'blocked','case_id'=>'anex','offers'=>[],'supplier_effect'=>'none','automatic_retry'=>false,
        'booking_calls'=>0,'broninit_calls'=>0,'mapping_writes'=>0,'observation_writes_allowed'=>false];
    try{$raw=file_get_contents('php://stdin',false,null,0,4097);if(!is_string($raw)||$raw===''||strlen($raw)>4096)throw new RuntimeException('BROAD_PRICE_INVALID_INPUT');$input=anex_broad_price_input(json_decode($raw,true,8,JSON_THROW_ON_ERROR));
        $home=(string)getenv('HOME');$root=realpath($home.'/www/anytoour.ru');$preview=realpath($root.'/_preview/search3-anex-candidate');if(!$root||!$preview||$preview!==$root.'/_preview/search3-anex-candidate'||!in_array(realpath((string)getcwd()),[$root,$preview],true))throw new RuntimeException('BROAD_PRICE_RUNTIME');
        $stage='DEPENDENCY';require_once $home.'/.anytoour-anex/search3-preview.php';require_once $preview.'/app/integrations/anex-search.php';require_once $preview.'/app/integrations/anex-search-mapping-registry.php';$_SERVER['SCRIPT_FILENAME']='';require_once $preview.'/api-anex-search3-preview.php';
        $db=is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php';require_once $db;$pdo=v2_data_db();if(!$pdo instanceof PDO||$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql')throw new RuntimeException('BROAD_PRICE_DB');$local=anex_broad_price_local($pdo);$registry=AnyTourAnexSearchMappingRegistry::fromPdo($pdo);
        $stage='CHECKPOINT';$dir=$home.'/.anytoour-anex';if(!is_dir($dir)||is_link($dir))throw new RuntimeException('BROAD_PRICE_CHECKPOINT_DIR');$path=$dir.'/'.ANEX_BROAD_PRICE_EXPERIMENT.'.json';$lock=fopen($path.'.lock','c');if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('BROAD_PRICE_LOCK');
        $state=is_file($path)?json_decode((string)file_get_contents($path),true,32,JSON_THROW_ON_ERROR):[];if($state){$prior=$state['result']??null;if(is_array($prior)&&($prior['status']??null)==='completed')return anex_broad_price_reused($prior);throw new RuntimeException('BROAD_PRICE_CASE_NOT_REPLAYABLE');}
        $state=['schema_version'=>1,'experiment_id'=>ANEX_BROAD_PRICE_EXPERIMENT,'status'=>'reserved','reserved_at'=>gmdate('c')];anex_broad_price_save($path,$state);$reserved=true;$out['supplier_effect']='unknown_after_reservation';
        $stage='ANEX_SEARCH';$result=anex_broad_price_anex($pdo,$local,$registry,$secrets);$out=anex_broad_price_completed($out,$result);$state=['schema_version'=>1,'experiment_id'=>ANEX_BROAD_PRICE_EXPERIMENT,'status'=>'completed','completed_at'=>gmdate('c'),'result'=>$out];anex_broad_price_save($path,$state);$reserved=false;
    }catch(Throwable $e){$code=$e->getMessage();$safe=preg_match('/\A(?:BROAD_PRICE|ANEX)_[A-Z0-9_]{1,80}\z/D',$code)?$code:'BROAD_PRICE_'.$stage.'_UNCONFIRMED';$out['reason']=$safe;if($reserved&&is_string($path)){$state=['schema_version'=>1,'experiment_id'=>ANEX_BROAD_PRICE_EXPERIMENT,'status'=>'unknown','recorded_at'=>gmdate('c'),'reason'=>$safe];try{anex_broad_price_save($path,$state);}catch(Throwable $ignored){}$out['status']='unknown';$out['supplier_effect']='unknown';}}
    finally{if(is_resource($lock)){flock($lock,LOCK_UN);fclose($lock);}if($pdo instanceof PDO&&$pdo->inTransaction())$pdo->rollBack();$out['elapsed_ms']=(int)round((microtime(true)-$started)*1000);}
    $json=json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);foreach($secrets as $secret)if($secret!==''&&is_string($json)&&strpos($json,$secret)!==false)return ['schema_version'=>1,'experiment_id'=>ANEX_BROAD_PRICE_EXPERIMENT,'case_id'=>'anex','status'=>'unknown','reason'=>'BROAD_PRICE_OUTPUT_REDACTED','supplier_effect'=>'unknown','automatic_retry'=>false,'booking_calls'=>0,'broninit_calls'=>0,'mapping_writes'=>0,'observation_writes_allowed'=>false];
    return $out;
}
if(!defined('ANYTOUR_ANEX_BROAD_PRICE_LIBRARY_ONLY')){error_reporting(0);ob_start();$report=anex_broad_price_main();ob_end_clean();echo json_encode($report,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),"\n";exit(($report['status']??null)==='completed'?0:1);}
