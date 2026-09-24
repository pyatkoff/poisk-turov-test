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

// Preserve the exact family composition before loading runtime/configuration.
$childAges=[];
$childRaw=$args['child-ages']??'';
if($childRaw!==''){
    $parts=explode(',',$childRaw);
    if(count($parts)>3)throw new InvalidArgumentException('ANDROMEDA_COLLECTOR_CHILD_AGES');
    foreach($parts as $part)$childAges[]=$int($part,0,17);
    sort($childAges,SORT_NUMERIC);
}

// Keep local destination scope identical in search and persistence. The existing
// API resolves these local IDs against country-scoped provider dictionaries.
$destinationIds=[];
foreach(['region','subregion'] as $key){
    $raw=$args[$key]??'';
    $destinationIds[$key]=$raw===''?[]:[(string)$int($raw,1,999999999)];
}

$site=realpath($get('site-root'));
$privateConfig=realpath($get('private-config'));
$source=$get('source-sha');
if($site===false||basename($site)!=='anytoour.ru'||$privateConfig===false||!is_file($privateConfig)
    ||!preg_match('/\A[a-f0-9]{40}\z/D',$source))throw new RuntimeException('ANDROMEDA_COLLECTOR_ROOT');

$runtime=dirname(__DIR__,2);
require_once $runtime.'/app/integrations/andromeda-local-offer-collector.php';
$searchEnvelopeDiagnostic=$runtime.'/app/integrations/andromeda-search-envelope-diagnostic.php';
if(is_file($searchEnvelopeDiagnostic)&&!is_link($searchEnvelopeDiagnostic))require_once $searchEnvelopeDiagnostic;
require_once $runtime.'/v2/api-andromeda-search3-preview.php';
require_once $runtime.'/app/integrations/andromeda-saved-package-runtime.php';
require_once $runtime.'/app/integrations/andromeda-anytour-offer-autosave-cache-runtime.php';

$config=require $privateConfig;
if(!is_array($config)||($config['enabled']??null)!==true)throw new RuntimeException('ANDROMEDA_COLLECTOR_CONFIG');

require_once $site.'/config.php';
$dbFile=is_file($site.'/data/db-v1.php')?$site.'/data/db-v1.php':$site.'/v2/data/db-v1.php';
require_once $dbFile;
$pdo=v2_data_db();
$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);

$localIngest=$site.'/_preview/search3-local-candidate/data/anytour-offer-snapshot-ingest-v1.php';
if(!is_file($localIngest)||is_link($localIngest))throw new RuntimeException('ANDROMEDA_COLLECTOR_LOCAL_INGEST');
putenv('ANYTOUR_LOCAL_SNAPSHOT_INGEST_FILE='.$localIngest);

$departure=$int($args['departure']??'1',1,999999999);
$country=$int($args['country']??'4',1,999999999);
$from=$date($args['date-from']??'2026-10-12');
$to=$date($args['date-to']??$from);
$nights=$int($args['nights']??'7',1,28);
$adults=$int($args['adults']??'2',1,6);
$meal=$args['meal']??'7';
$operatorIds=[];
$operatorRaw=$args['operator-id']??'';
if($operatorRaw!=='')$operatorIds=[(string)$int($operatorRaw,1,999999999)];
$generation=$int($args['generation']??'17171801',1,2147483647);
// Background collection persists fresh PRICE results and reuses retained pricing;
// package/quote actualization is opt-in only and never enabled by the default CLI.
$maxCaptures=$int($args['max-captures']??'0',0,300);
$maxCaptureSeconds=$int($args['max-capture-seconds']??'0',0,240);
// Background collection must not discover flights merely to derive money evidence.
// External/unknown freight remains eligible for normal snapshot autosave, while an
// explicit caller can still opt into `all` for a separately owned quote-selection flow.
$captureMode=$args['capture-mode']??'non_external_only';
if(!in_array($captureMode,['all','non_external_only','external_group_only'],true))throw new InvalidArgumentException('ANDROMEDA_COLLECTOR_CAPTURE_MODE');

$params=[
    'departureId'=>(string)$departure,'countryId'=>(string)$country,
    'dateFrom'=>$from,'dateTo'=>$to,'nightsFrom'=>$nights,'nightsTo'=>$nights,
    'adults'=>$adults,'childs'=>$childAges,'meal'=>$meal,'hotelCategory'=>'','hotelRating'=>'',
    'hotelTypes'=>[],'hotelIds'=>[],'hotelServices'=>[],'arrivalId'=>'','regionIds'=>$destinationIds['region'],
    'subregionIds'=>$destinationIds['subregion'],'operatorIds'=>$operatorIds,'priceFrom'=>'','priceTo'=>'','currency'=>'RUB',
    'onlyCharter'=>false,'onlyDirect'=>false,
];
$request=['generation'=>$generation,'params'=>$params];

$saved=anytour_andromeda_search3_catalog($config,$request);
$saved['excluded_operator_ids']=$config['excluded_operator_ids']??[];
$session='intdb'.bin2hex(random_bytes(12));
$directory=dirname((string)$config['catalog_path']).'/searches';
if(!is_dir($directory)||is_link($directory))throw new RuntimeException('ANDROMEDA_COLLECTOR_SEARCH_DIR');

$lastPageFinishedAt=0.0;
$searchComplete=static function(array $req)use($pdo,$saved,$config,$session,&$lastPageFinishedAt):array{
    $search=anytour_andromeda_search3_run_pages($req,
        static function(array $pageRequest)use($pdo,$saved,$config,$session,&$lastPageFinishedAt):array{
            if($lastPageFinishedAt>0.0){
                $wait=1.05-(microtime(true)-$lastPageFinishedAt);
                if($wait>0.0)usleep((int)ceil($wait*1000000));
            }
            try{
                return anytour_andromeda_search3_run($pageRequest,$pdo,$saved,$config,$session);
            }finally{
                $lastPageFinishedAt=microtime(true);
            }
        }
    );
    if(class_exists('AnyTourAndromedaSearchEnvelopeDiagnosticV1',false)){
        $diagnostic=AnyTourAndromedaSearchEnvelopeDiagnosticV1::exceptionCode($req,$search);
        if($diagnostic!==null)throw new RuntimeException($diagnostic);
    }
    return $search;
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

// Canonical eligibility is a hotel-level fact for this invocation. One supplier
// cohort may contain many offers for the same local hotel. Lazily prepare the query
// only when a candidate actually reaches this gate, then memoize both positive and
// negative answers. Never persist this cache: every new CLI run re-reads the bridge.
$canonicalEligibility=[];
$canonicalCheck=null;
$candidateAllowed=static function(array $selection,array $offer)use($pdo,&$canonicalCheck,&$canonicalEligibility):bool{
    $local=$selection['local_id']??null;
    if(!is_int($local)||$local<1)return false;
    if(array_key_exists($local,$canonicalEligibility))return $canonicalEligibility[$local];
    if(!$canonicalCheck instanceof PDOStatement){
        $canonicalCheck=$pdo->prepare("SELECT COUNT(*) FROM anytour_hotel_sources s JOIN anytour_hotels h ON h.id=s.anytour_hotel_id WHERE s.namespace='legacy_catalog' AND s.external_key=? AND h.is_active=1");
    }
    $canonicalCheck->execute([(string)$local]);
    $canonicalEligibility[$local]=(int)$canonicalCheck->fetchColumn()===1;
    return $canonicalEligibility[$local];
};

$capture=static function(array $selection)use($config,$saved,$pdo,$source):array{
    return anytour_andromeda_capture_selected_package(
        $config,$saved,$selection,$pdo,$source,true,null,null,true
    );
};

$autosave=static function(array $req,string $ref,int $generation)use($pdo,$saved,$directory):array{
    return anytour_andromeda_anytour_offer_autosave_cache_runtime(
        $req,$pdo,$saved,$directory,$ref,$generation
    );
};

// A current compatible group entry already contains the reusable transport fact.
// This is a read of the existing store, not another package/get_flights request.
// Autosave rereads it per target and never promotes an estimate to verified money.
$hasReusableSurcharge=static function(array $selection,array $offer,array $req)use($directory):bool{
    return AnyTourAndromedaSurchargeEvidenceStoreV1::readApplied(
        $directory,$offer,$req,time()
    )!==null;
};

$rangeStart=new DateTimeImmutable($from,new DateTimeZone('UTC'));
$rangeEnd=new DateTimeImmutable($to,new DateTimeZone('UTC'));
$inclusiveDays=(int)$rangeStart->diff($rangeEnd)->days+1;
if($rangeEnd<$rangeStart||$inclusiveDays<1||$inclusiveDays>21)throw new InvalidArgumentException('ANDROMEDA_COLLECTOR_DATE_RANGE');
if($inclusiveDays>7 && $maxCaptures>0)throw new InvalidArgumentException('ANDROMEDA_COLLECTOR_RANGE_CAPTURE_UNSUPPORTED');
$collectWindow=static function(array $windowRequest)use(
    $searchComplete,$loadCohort,$candidateAllowed,$capture,$autosave,
    $maxCaptures,$captureMode,$maxCaptureSeconds,$hasReusableSurcharge
):array{
    try{
        return AnyTourAndromedaLocalOfferCollectorV1::collect(
            $windowRequest,$searchComplete,$loadCohort,$candidateAllowed,$capture,$autosave,
            $maxCaptures,$captureMode,$maxCaptureSeconds,null,$hasReusableSurcharge
        );
    }catch(RuntimeException $error){
        if($error->getMessage()!=='supplier_unavailable')throw $error;
        return [
            'source'=>'andromeda-local-offer-collector-v1',
            'status'=>'incomplete',
            'incomplete_reason'=>'supplier_unavailable_before_first_page',
            'pages'=>0,
            'advertised_pages'=>null,
            'received_offers'=>0,
            'mapped_offers'=>0,
            'owned_operator_offers'=>null,
            'eligible_offers'=>null,
            'capture_mode'=>$captureMode,
            'capture_queue_offers'=>0,
            'reusable_surcharge_groups'=>0,
            'capture_time_budget_seconds'=>$maxCaptureSeconds,
            'capture_time_budget_exhausted'=>false,
            'surcharge_capture_attempts'=>0,
            'surcharge_ready'=>0,
            'autosave_published'=>false,
            'autosave_reason'=>'supplier_unavailable',
            'ready_offer_count'=>null,
            'confirmation_required_offer_count'=>null,
            'autosave'=>['published'=>false,'reason'=>'supplier_unavailable'],
            'selection_authority'=>false,
            'booking_calls'=>0,
        ];
    }
};
if($inclusiveDays<=7){
    $result=$collectWindow($request);
}else{
    $result=AnyTourAndromedaLocalOfferCollectorV1::collectRange(
        $request,$from,$to,
        static fn(array $windowRequest,int $index,array $window):array=>$collectWindow($windowRequest)
    );
}
echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
if (($result['status'] ?? null) !== 'complete') exit(1);
