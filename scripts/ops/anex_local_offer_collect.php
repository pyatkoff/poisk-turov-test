<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){fwrite(STDERR,"CLI only\n");exit(2);}

$root=getenv('ANYTOUR_PROJECT_ROOT');
$root=is_string($root)&&$root!==''?$root:rtrim((string)getenv('HOME'),'/').'/www/anytoour.ru';
if(!is_dir($root)||is_link($root)||basename($root)!=='anytoour.ru')throw new RuntimeException('ANEX_COLLECTOR_PROJECT_ROOT');

$payloadRoot=dirname(__DIR__,2);
require_once $payloadRoot.'/app/integrations/anex-local-offer-collector.php';
require_once $payloadRoot.'/v2/api-anex-search3-preview.php';
foreach(['anex-preview-gateway','anex-search-mapping-registry','anex-search-observations','anex-additional-prices-client'] as $name){
    require_once $payloadRoot.'/app/integrations/'.$name.'.php';
}
$config=$root.'/config.php';
if(!is_file($config)||is_link($config))throw new RuntimeException('ANEX_COLLECTOR_CONFIG');
require_once $config;
$dbFile=is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php';
require_once $dbFile;

$apiToken=trim((string)getenv('ANEX_API_TOKEN'));
if($apiToken===''&&defined('ANEX_API_TOKEN'))$apiToken=trim((string)ANEX_API_TOKEN);
$b2bToken=trim((string)getenv('ANEX_B2B_TOKEN'));
if($b2bToken===''&&defined('ANEX_B2B_TOKEN'))$b2bToken=trim((string)ANEX_B2B_TOKEN);
if($apiToken===''||$b2bToken==='')throw new RuntimeException('ANEX_COLLECTOR_TOKEN');

$args=[];
foreach(array_slice($argv,1) as $arg){
    if(!str_starts_with($arg,'--')||!str_contains($arg,'='))throw new InvalidArgumentException('ANEX_COLLECTOR_ARG');
    [$k,$v]=explode('=',substr($arg,2),2);$args[$k]=$v;
}
$need=static function(string $key)use($args):string{
    $v=$args[$key]??null;
    if(!is_string($v)||$v==='')throw new InvalidArgumentException('ANEX_COLLECTOR_ARG_'.$key);
    return $v;
};
$int=static function(string $value,int $min,int $max):int{
    if(!preg_match('/\A(?:0|[1-9][0-9]{0,8})\z/D',$value))throw new InvalidArgumentException('ANEX_COLLECTOR_INT');
    $n=(int)$value;if($n<$min||$n>$max)throw new InvalidArgumentException('ANEX_COLLECTOR_INT');return $n;
};
$date=static function(string $value):string{
    if(!preg_match('/\A(\d{4})-(\d{2})-(\d{2})\z/D',$value,$m)||!checkdate((int)$m[2],(int)$m[3],(int)$m[1]))throw new InvalidArgumentException('ANEX_COLLECTOR_DATE');
    return $value;
};

$departure=$int($need('departure'),1,999999999);
$country=$int($need('country'),1,999999999);
$from=$date($need('date-from'));
$to=$date($args['date-to']??$from);
$nights=$int($need('nights'),1,28);
$adults=$int($args['adults']??'2',1,6);
$meal=$args['meal']??'';
$maxExpands=$int($args['max-expands']??'60',0,120);
$maxBatch=$int($args['max-apd']??'300',1,600);
$generation=$int($args['generation']??'25061801',1,2147483647);

$pdo=v2_data_db();
$cache=[];$state=[];$searchRequests=0;$apdRequests=0;
$searchBudget=min(130,max(8,$maxExpands+2));
$apdBudget=min(120,max(8,(int)ceil($maxBatch/6)*2));
$makeClient=static function()use($apiToken,&$searchRequests,$searchBudget):AnyTourAnexClient{
    ++$searchRequests;
    if($searchRequests>$searchBudget)throw new RuntimeException('ANEX_COLLECTOR_SEARCH_BUDGET');
    return new AnyTourAnexClient($apiToken);
};
$makeAdditional=static function()use($b2bToken,&$apdRequests,$apdBudget):AnyTourAnexAdditionalPricesClient{
    ++$apdRequests;
    if($apdRequests>$apdBudget)throw new RuntimeException('ANEX_COLLECTOR_APD_BUDGET');
    return new AnyTourAnexAdditionalPricesClient($b2bToken);
};
$resolver=AnyTourAnexSearchMappingRegistry::fromPdo($pdo)->previewResolver();
$metadata=static fn(array $offers):array=>anytour_anex_search3_metadata($pdo,$offers);
$checkpoint=static function(array &$unused):void{};

$params=[
    'departureId'=>(string)$departure,'countryId'=>(string)$country,
    'dateFrom'=>$from,'dateTo'=>$to,'nightsFrom'=>$nights,'nightsTo'=>$nights,
    'adults'=>$adults,'childs'=>[],'meal'=>$meal,'hotelCategory'=>'','hotelRating'=>'',
    'hotelTypes'=>[],'hotelIds'=>[],'hotelServices'=>[],'arrivalId'=>'','regionIds'=>[],
    'subregionIds'=>[],'operatorIds'=>[],'priceFrom'=>'','priceTo'=>'','currency'=>'RUB',
    'onlyCharter'=>false,'onlyDirect'=>false,
];
$request=['action'=>'search','generation'=>$generation,'params'=>$params];

$searchRunner=static function(array $req,array &$collectorState)use($pdo,&$cache,$makeClient):array{
    $diagnostics=null;
    $observer=static fn(array $offers,array $context):array=>AnyTourAnexSearchObservations::record($pdo,$offers,$context);
    return anytour_anex_search3_run($req,$pdo,$makeClient(),$cache,$diagnostics,$observer,$collectorState,'all');
};
$expandRunner=static function(array $req,array &$collectorState)use($resolver,$metadata,$makeClient,$checkpoint,$makeAdditional):array{
    // Background worker has its own explicit budget and uses AnyTourAnexClient's
    // shared supplier pacing. Do not inherit the browser preview session cap.
    return anytour_anex_search3_followup(
        $req,$collectorState,$resolver,$makeClient,$metadata,null,$checkpoint,$makeAdditional,false
    );
};
$programRecorder=static function(array &$collectorState)use($pdo):array{
    return AnyTourAnexProgramObservationRuntimeV1::record($pdo,$collectorState,new DateTimeImmutable('now',new DateTimeZone('UTC')));
};
$batchRunner=static function(array $req,array &$collectorState)use($resolver,$metadata,$checkpoint,$makeAdditional):array{
    return anytour_anex_search3_additional_batch($req,$collectorState,$resolver,$metadata,null,$checkpoint,$makeAdditional);
};

$result=AnyTourAnexLocalOfferCollectorV1::collect(
    $request,$state,$searchRunner,$expandRunner,$programRecorder,$batchRunner,$maxExpands,$maxBatch
);
$result['search_client_instances']=$searchRequests;
$result['apd_client_instances']=$apdRequests;
$result['search_budget']=$searchBudget;
$result['apd_budget']=$apdBudget;
$result['supplier_calls_bounded']=true;
$result['booking_calls']=0;
$result['lead_calls']=0;
echo json_encode($result,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n";
