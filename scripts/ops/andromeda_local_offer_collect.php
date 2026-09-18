<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI only\n"); exit(2); }

$args=[];
foreach(array_slice($argv,1) as $arg){
    if(!str_starts_with($arg,'--')||!str_contains($arg,'='))throw new InvalidArgumentException('ANDROMEDA_COLLECTOR_ARG');
    [$k,$v]=explode('=',substr($arg,2),2);$args[$k]=$v;
}
$get=static function(string $key)use($args):string{
    $v=$args[$key]??null;
    if(!is_string($v)||$v==='')throw new InvalidArgumentException('ANDROMEDA_COLLECTOR_ARG_'.$key);
    return $v;
};
$int=static function(string $value,int $min,int $max):int{
    if(!preg_match('/\A(?:0|[1-9][0-9]{0,9})\z/D',$value))throw new InvalidArgumentException('ANDROMEDA_COLLECTOR_INT');
    $n=(int)$value;if($n<$min||$n>$max)throw new InvalidArgumentException('ANDROMEDA_COLLECTOR_INT');return $n;
};
$date=static function(string $value):string{
    if(!preg_match('/\A(\d{4})-(\d{2})-(\d{2})\z/D',$value,$m)||!checkdate((int)$m[2],(int)$m[3],(int)$m[1]))throw new InvalidArgumentException('ANDROMEDA_COLLECTOR_DATE');
    return $value;
};

$site=realpath($get('site-root'));
$privateConfig=realpath($get('private-config'));
$source=$get('source-sha');
if($site===false||basename($site)!=='anytoour.ru'||$privateConfig===false||!is_file($privateConfig)
    ||!preg_match('/\A[a-f0-9]{40}\z/D',$source))throw new RuntimeException('ANDROMEDA_COLLECTOR_ROOT');

$runtime=dirname(__DIR__,2);
require_once $runtime.'/app/integrations/andromeda-local-offer-collector.php';
require_once $runtime.'/v2/api-andromeda-search3-preview.php';
require_once $runtime.'/app/integrations/andromeda-saved-package-runtime.php';

$config=require $privateConfig;
if(!is_array($config)||($config['enabled']??null)!==true)throw new RuntimeException('ANDROMEDA_COLLECTOR_CONFIG');

require_once $site.'/config.php';
$dbFile=is_file($site.'/data/db-v1.php')?$site.'/data/db-v1.php':$site.'/v2/data/db-v1.php';
require_once $dbFile;
$pdo=v2_data_db();
$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);

putenv('ANYTOUR_LOCAL_SNAPSHOT_INGEST_FILE='.$runtime.'/v2/data/anytour-offer-snapshot-ingest-v1.php');

$departure=$int($args['departure']??'1',1,999999999);
$country=$int($args['country']??'4',1,999999999);
$from=$date($args['date-from']??'2026-10-12');
$to=$date($args['date-to']??$from);
$nights=$int($args['nights']??'7',1,28);
$adults=$int($args['adults']??'2',1,6);
$meal=$args['meal']??'7';
$generation=$int($args['generation']??'17171801',1,2147483647);
$maxCaptures=$int($args['max-captures']??'2',1,6);

$params=[
    'departureId'=>(string)$departure,'countryId'=>(string)$country,
    'dateFrom'=>$from,'dateTo'=>$to,'nightsFrom'=>$nights,'nightsTo'=>$nights,
    'adults'=>$adults,'childs'=>[],'meal'=>$meal,'hotelCategory'=>'','hotelRating'=>'',
    'hotelTypes'=>[],'hotelIds'=>[],'hotelServices'=>[],'arrivalId'=>'','regionIds'=>[],
    'subregionIds'=>[],'operatorIds'=>[],'priceFrom'=>'','priceTo'=>'','currency'=>'RUB',
    'onlyCharter'=>false,'onlyDirect'=>false,
];
$request=['generation'=>$generation,'params'=>$params];

$saved=anytour_andromeda_search3_catalog($config,$request);
$saved['excluded_operator_ids']=$config['excluded_operator_ids']??[];
$session='intdb'.bin2hex(random_bytes(12));
$directory=dirname((string)$config['catalog_path']).'/searches';
if(!is_dir($directory)||is_link($directory))throw new RuntimeException('ANDROMEDA_COLLECTOR_SEARCH_DIR');

$searchComplete=static function(array $req)use($pdo,$saved,$config,$session):array{
    return anytour_andromeda_search3_run_pages($req,
        static fn(array $pageRequest):array=>anytour_andromeda_search3_run($pageRequest,$pdo,$saved,$config,$session)
    );
};

$loadCohort=static function(string $ref,int $generation)use($directory):array{
    $firstPath=$directory.'/'.$ref.'-1.json';
    if(!is_file($firstPath)||is_link($firstPath))throw new RuntimeException('ANDROMEDA_COLLECTOR_COHORT');
    $first=json_decode((string)file_get_contents($firstPath),true,64,JSON_THROW_ON_ERROR);
    if(!is_array($first)||($first['generation']??null)!==$generation||!is_array($first['store']['snapshot']??null))
        throw new RuntimeException('ANDROMEDA_COLLECTOR_COHORT');
    $created=$first['store']['created_at']??null;
    $target=$first['store']['snapshot']['pages_count']??null;
    if(!is_int($created)||!is_int($target)||$target<0||$target>AnyTourAndromedaPaginationV1::MAX_PAGES)throw new RuntimeException('ANDROMEDA_COLLECTOR_COHORT');
    $rows=[];$page=1;$currentTarget=max(1,$target);
    while($page<=$currentTarget){
        $path=$page===1?$firstPath:$directory.'/'.$ref.'-'.$created.'-'.$page.'.json';
        if(!is_file($path)||is_link($path))throw new RuntimeException('ANDROMEDA_COLLECTOR_COHORT');
        $state=json_decode((string)file_get_contents($path),true,64,JSON_THROW_ON_ERROR);
        $snap=$state['store']['snapshot']??null;
        if(!is_array($snap)||($snap['provider']??null)!=='andromeda'||($snap['search_ref']??null)!==$ref
            ||($snap['generation']??null)!==$generation||($snap['page']??null)!==$page
            ||!is_array($snap['offers']??null)||!array_is_list($snap['offers'])
            ||!is_array($snap['rejected']??null)||!array_is_list($snap['rejected']))throw new RuntimeException('ANDROMEDA_COLLECTOR_COHORT');
        $decision=AnyTourAndromedaPaginationV1::nextTarget(
            $page,(int)$snap['pages_count'],count($snap['offers']),(string)($state['status']??''),
            count($snap['rejected']),$currentTarget
        );
        if(($decision['terminal']??false)===true)break;
        foreach($snap['offers'] as $offer)$rows[]=['page'=>$page,'offer'=>$offer];
        $currentTarget=$decision['target'];++$page;
    }
    return $rows;
};

$candidateAllowed=static function(array $selection,array $offer)use($pdo):bool{
    $local=$selection['local_id']??null;
    if(!is_int($local)||$local<1)return false;
    $q=$pdo->prepare("SELECT COUNT(*) FROM anytour_hotel_sources s JOIN anytour_hotels h ON h.id=s.anytour_hotel_id WHERE s.namespace='legacy_catalog' AND s.external_key=? AND h.is_active=1");
    $q->execute([(string)$local]);
    return (int)$q->fetchColumn()===1;
};

$capture=static function(array $selection)use($config,$saved,$pdo,$source):array{
    return anytour_andromeda_capture_selected_package(
        $config,$saved,$selection,$pdo,$source,true,null,null,true
    );
};

$autosave=static function(array $req,string $ref,int $generation)use($pdo,$saved,$directory):array{
    return anytour_andromeda_anytour_offer_autosave_runtime($req,$pdo,$saved,$directory,$ref,$generation);
};

$result=AnyTourAndromedaLocalOfferCollectorV1::collect(
    $request,$searchComplete,$loadCohort,$candidateAllowed,$capture,$autosave,$maxCaptures
);
echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
