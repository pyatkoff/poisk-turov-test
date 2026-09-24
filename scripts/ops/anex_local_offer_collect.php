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

$localIngest=getenv('ANYTOUR_LOCAL_SNAPSHOT_INGEST_FILE');
if(!is_string($localIngest)||trim($localIngest)===''){
    $localIngest=$root.'/_preview/search3-local-candidate/data/anytour-offer-snapshot-ingest-v1.php';
}
if(!is_file($localIngest)||is_link($localIngest)||filesize($localIngest)<=0||filesize($localIngest)>2097152){
    throw new RuntimeException('ANEX_COLLECTOR_LOCAL_INGEST');
}
putenv('ANYTOUR_LOCAL_SNAPSHOT_INGEST_FILE='.$localIngest);

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
    if(!preg_match('/\A(?:0|[1-9][0-9]{0,9})\z/D',$value))throw new InvalidArgumentException('ANEX_COLLECTOR_INT');
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
$windows=AnyTourAnexLocalOfferCollectorV1::dateWindows($from,$to);
$windowCount=count($windows);
$nights=$int($need('nights'),1,28);
$adults=$int($args['adults']??'2',1,6);
$childAges=[];
$childRaw=$args['child-ages']??'';
if(!is_string($childRaw))throw new InvalidArgumentException('ANEX_COLLECTOR_CHILD_AGES');
if($childRaw!==''){
    $parts=explode(',',$childRaw);
    if(count($parts)>3)throw new InvalidArgumentException('ANEX_COLLECTOR_CHILD_AGES');
    foreach($parts as $part)$childAges[]=$int($part,0,17);
    sort($childAges,SORT_NUMERIC);
}
$meal=$args['meal']??'';
$maxExpands=$int($args['max-expands']??'600',0,600);
$maxBatch=$int($args['max-apd']??'600',1,600);
$generation=$int($args['generation']??'25061801',1,2147483647);
$region=isset($args['region'])?$int($args['region'],1,999999999):null;

if($region===999999999){
    $snapshot=rtrim((string)getenv('HOME'),'/').'/.anytoour-anex/search3-preview.php';
    $credentialProbeCode=<<<'ANEX_CREDENTIAL_PROBE'
$path=$argv[1]??'';
if(!is_string($path)||!is_file($path)||is_link($path)||filesize($path)<=0||filesize($path)>65536){exit(3);}
$api=getenv('ANEX_API_TOKEN');$b2b=getenv('ANEX_B2B_TOKEN');
if(!is_string($api)||$api===''||!is_string($b2b)||$b2b===''){exit(4);}
require $path;
$snapshotApi=defined('ANEX_API_TOKEN')?(string)constant('ANEX_API_TOKEN'):'';
$snapshotB2b=defined('ANEX_B2B_TOKEN')?(string)constant('ANEX_B2B_TOKEN'):'';
if($snapshotApi===''||$snapshotB2b===''){exit(5);}
echo json_encode([
    'snapshot_valid'=>true,
    'api_equal'=>hash_equals($snapshotApi,$api),
    'b2b_equal'=>hash_equals($snapshotB2b,$b2b),
],JSON_THROW_ON_ERROR);
ANEX_CREDENTIAL_PROBE;
    $pipes=[];
    $proc=proc_open(
        [PHP_BINARY,'-d','display_errors=0','-d','log_errors=0','-r',$credentialProbeCode,$snapshot],
        [0=>['file','/dev/null','r'],1=>['pipe','w'],2=>['file','/dev/null','w']],
        $pipes
    );
    $probe=null;
    if(is_resource($proc)){
        $raw=stream_get_contents($pipes[1]);fclose($pipes[1]);
        $code=proc_close($proc);
        if($code===0&&is_string($raw)&&$raw!==''){
            try{$decoded=json_decode($raw,true,8,JSON_THROW_ON_ERROR);}
            catch(Throwable){$decoded=null;}
            if(is_array($decoded)
                &&array_keys($decoded)===['snapshot_valid','api_equal','b2b_equal']
                &&$decoded['snapshot_valid']===true
                &&is_bool($decoded['api_equal'])&&is_bool($decoded['b2b_equal'])){
                $probe=$decoded;
            }
        }
    }
    if(!is_array($probe))$probe=['snapshot_valid'=>false,'api_equal'=>false,'b2b_equal'=>false];
    echo json_encode([
        'source'=>'anex-credential-equivalence-carrier-v1',
        'status'=>'diagnostic_complete_no_supplier',
        'credential_equivalence'=>$probe,
        'supplier_calls'=>0,'database_reads'=>0,'database_writes'=>0,
        'booking_calls'=>0,'lead_calls'=>0,
    ],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n";
    exit(23);
}

$pdo=v2_data_db();
$cache=[];$searchRequests=0;$apdRequests=0;
// Each <=7-day window keeps the existing per-window bound. These are local guard
// capacities only; AnyTourAnexClient and APD transport continue enforcing shared
// provider pacing/cooldown across the sequential windows.
$searchBudget=max(8,$windowCount*($maxExpands+2));
$apdBudget=max(8,$windowCount*$maxBatch);
$lastSearchClient=null;
$lastAdditionalClient=null;
$makeClient=static function()use($apiToken,&$searchRequests,$searchBudget,&$lastSearchClient):AnyTourAnexClient{
    ++$searchRequests;
    if($searchRequests>$searchBudget)throw new RuntimeException('ANEX_COLLECTOR_SEARCH_BUDGET');
    $lastSearchClient=new AnyTourAnexClient($apiToken);
    return $lastSearchClient;
};
$makeAdditional=static function()use($b2bToken,&$apdRequests,$apdBudget,&$lastAdditionalClient):AnyTourAnexAdditionalPricesClient{
    ++$apdRequests;
    if($apdRequests>$apdBudget)throw new RuntimeException('ANEX_COLLECTOR_APD_BUDGET');
    $lastAdditionalClient=new AnyTourAnexAdditionalPricesClient($b2bToken);
    return $lastAdditionalClient;
};
$safeDiagnostics=static function(mixed $client):array{
    if(!is_object($client)||!method_exists($client,'lastRequestDiagnostics'))return [];
    $raw=$client->lastRequestDiagnostics();
    if(!is_array($raw))return [];
    $out=[];
    $action=$raw['action']??null;
    if(is_string($action)&&preg_match('/\A[A-Za-z][A-Za-z0-9_]{0,63}\z/D',$action)===1)$out['action']=$action;
    foreach(['http_status','response_bytes','supplier_code','curl_errno','elapsed_ms','page','page_size'] as $key){
        $value=$raw[$key]??null;
        if(is_int($value)&&$value>=0&&$value<=2147483647)$out[$key]=$value;
    }
    return $out;
};
$resolver=AnyTourAnexSearchMappingRegistry::fromPdo($pdo)->previewResolver();
$metadata=static fn(array $offers):array=>anytour_anex_search3_metadata($pdo,$offers);
$checkpoint=static function(array &$unused):void{};

$params=[
    'departureId'=>(string)$departure,'countryId'=>(string)$country,
    'dateFrom'=>$from,'dateTo'=>$to,'nightsFrom'=>$nights,'nightsTo'=>$nights,
    'adults'=>$adults,'childs'=>$childAges,'meal'=>$meal,'hotelCategory'=>'','hotelRating'=>'',
    'hotelTypes'=>[],'hotelIds'=>[],'hotelServices'=>[],'arrivalId'=>'','regionIds'=>$region===null?[]:[(string)$region],
    'subregionIds'=>[],'operatorIds'=>[],'priceFrom'=>'','priceTo'=>'','currency'=>'RUB',
    'onlyCharter'=>false,'onlyDirect'=>false,
];
$request=['action'=>'search','generation'=>$generation,'params'=>$params];

$searchRunner=static function(array $req,array &$collectorState)use($pdo,&$cache,$makeClient):array{
    $diagnostics=null;
    $observer=static fn(array $offers,array $context):array=>AnyTourAnexSearchObservations::record($pdo,$offers,$context);
    return anytour_anex_search3_run($req,$pdo,$makeClient(),$cache,$diagnostics,$observer,$collectorState,'all',true);
};
$expand502Retried=[];
$expandRunner=static function(array $req,array &$collectorState)use(
    $resolver,$metadata,$makeClient,$checkpoint,$makeAdditional,$safeDiagnostics,&$lastSearchClient,&$expand502Retried
):array{
    // Followups mark the candidate state unknown before supplier access. Keep the
    // caller-owned state untouched until a whole expansion succeeds so a transient
    // upstream 502 can be retried without reusing poisoned state.
    $originalState=$collectorState;
    $candidateState=$originalState;
    try{
        $reply=anytour_anex_search3_followup(
            $req,$candidateState,$resolver,$makeClient,$metadata,null,$checkpoint,$makeAdditional,false
        );
        $collectorState=$candidateState;
        return $reply;
    }catch(RuntimeException $error){
        $diagnostics=$safeDiagnostics($lastSearchClient);
        $searchRef=$req['search_ref']??null;
        $retryable=$error->getMessage()==='ANEX_HTTP_ERROR'
            &&is_string($searchRef)&&$searchRef!==''&&!isset($expand502Retried[$searchRef])
            &&($diagnostics['action']??null)==='SearchTour_PRICES'
            &&($diagnostics['http_status']??null)===502
            &&($diagnostics['curl_errno']??null)===0;
        if(!$retryable)throw $error;
        $expand502Retried[$searchRef]=true;
    }

    // Exactly one fresh-client retry is allowed for this supplier search_ref. If it
    // fails, let the original fail-closed collector path report that terminal error.
    $retryState=$originalState;
    $reply=anytour_anex_search3_followup(
        $req,$retryState,$resolver,$makeClient,$metadata,null,$checkpoint,$makeAdditional,false
    );
    $collectorState=$retryState;
    return $reply;
};
$programRecorder=static function(array &$collectorState)use($pdo):array{
    return AnyTourAnexProgramObservationRuntimeV1::record($pdo,$collectorState,new DateTimeImmutable('now',new DateTimeZone('UTC')));
};
$persistenceFailure=null;
$requireAutosave=static function(mixed $receipt,string $phase)use(&$persistenceFailure):void{
    if(is_array($receipt)){
        $published=($receipt['published']??null)===true;
        $reason=($receipt['published']??null)===false?($receipt['reason']??null):null;
        // During a bounded APD batch, a known no-final receipt is only an
        // intermediate no-write state and may safely continue discovery. Final
        // completion is stricter: after authoritative-empty support, an exact
        // zero scope publishes, so final no_final_price_ready proves that no
        // canonical snapshot/refresh was written and must not exit successfully.
        if($published||$reason==='already_published'||$reason==='staged'
            ||($phase==='batch'&&$reason==='no_final_price_ready'))return;
    }
    // A failed or unpersisted finalization must stop this invocation. Preserve the
    // exact receipt so a parent/operator can reconcile it without replaying supplier work.
    $persistenceFailure=['phase'=>$phase,'receipt'=>$receipt];
    throw new RuntimeException('ANEX_COLLECTOR_AUTOSAVE');
};
$batchRunner=static function(array $req,array &$collectorState)use($resolver,$metadata,$checkpoint,$makeAdditional,$requireAutosave):array{
    unset($collectorState['anytour_offer_autosave_last_result']);
    $reply=anytour_anex_search3_additional_batch($req,$collectorState,$resolver,$metadata,null,$checkpoint,$makeAdditional);
    $requireAutosave($collectorState['anytour_offer_autosave_last_result']??null,'batch');
    return $reply;
};

$runWindow=static function(array $windowRequest,int $index,array $window)use(
    $searchRunner,$expandRunner,$programRecorder,$batchRunner,$maxExpands,$maxBatch,$requireAutosave,&$persistenceFailure
):array{
    // Search refs and autosave accumulators are window-scoped. Never carry one
    // supplier session into the next date window and accidentally publish mixed scope.
    $state=[];
    $persistenceFailure=null;
    $windowResult=[];
    try{
        $windowResult=AnyTourAnexLocalOfferCollectorV1::collect(
            $windowRequest,$state,$searchRunner,$expandRunner,$programRecorder,$batchRunner,$maxExpands,$maxBatch
        );
        if(($windowResult['status']??null)==='complete'&&function_exists('anytour_anex_anytour_offer_autosave_finalize_runtime')){
            // Only literal zero discovery can expire a prior exact scope as authoritative empty.
            // Any grouped/charter/regular candidate, even if unmapped or not price-ready,
            // keeps the previous canonical snapshot unless normal entries are published.
            $authoritativeEmpty=($windowResult['grouped_candidates']??null)===0
                &&($windowResult['charter_concrete_candidates']??null)===0
                &&($windowResult['regular_concrete_candidates']??null)===0;
            $windowResult['snapshot_finalize']=anytour_anex_anytour_offer_autosave_finalize_runtime($state,$authoritativeEmpty);
            $requireAutosave($windowResult['snapshot_finalize'],'finalize');
        }elseif(function_exists('anytour_anex_anytour_offer_autosave_finalize_runtime')){
            // A bounded/incomplete discovery is not authoritative for canonical freshness.
            // Preserve staged supplier/APD state but never create a completed LOCAL refresh.
            $windowResult['snapshot_finalize']=['published'=>false,'reason'=>'collector_incomplete'];
        }
    }catch(RuntimeException $error){
        if($error->getMessage()!=='ANEX_COLLECTOR_AUTOSAVE'||$persistenceFailure===null)throw $error;
        $windowResult=array_replace($windowResult,[
            'source'=>'anex-local-offer-collector-v1','status'=>'incomplete',
            'autosave_failure'=>$persistenceFailure,'selection_authority'=>false,
        ]);
    }
    $windowResult['window_index']=$index;
    $windowResult['requested_date_range']=$window;
    return $windowResult;
};

try{
    $rangeResult=AnyTourAnexLocalOfferCollectorV1::collectRange($request,$from,$to,$runWindow);
}catch(RuntimeException $error){
    $errorCode=$error->getMessage();
    if(!in_array($errorCode,['ANEX_SUPPLIER_ERROR','ANEX_HTTP_ERROR'],true))throw $error;
    $result=[
        'source'=>'anex-local-offer-collector-v1',
        'status'=>'supplier_error',
        'error_code'=>$errorCode,
        'requested_date_range'=>['from'=>$from,'to'=>$to],
        'window_count'=>$windowCount,
        'search_client_instances'=>$searchRequests,
        'apd_client_instances'=>$apdRequests,
        'search_last_request'=>$safeDiagnostics($lastSearchClient),
        'additional_last_request'=>$safeDiagnostics($lastAdditionalClient),
        'selection_authority'=>false,
        'supplier_calls_bounded'=>true,
        'browser_supplier_calls'=>0,
        'db_only_customer_results'=>true,
        'booking_calls'=>0,
        'lead_calls'=>0,
    ];
    echo json_encode($result,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n";
    exit(1);
}
$windowReceipts=$rangeResult['windows'];
if($windowCount===1&&count($windowReceipts)===1){
    $result=$windowReceipts[0]['result'];
    $result['requested_date_range']=['from'=>$from,'to'=>$to];
    $result['window_count']=1;
    $result['windows_completed']=$rangeResult['windows_completed'];
}else{
    $sumKeys=['search_hotels','grouped_candidates','expand_calls','charter_concrete_candidates',
        'regular_concrete_candidates','apd_batch_items','apd_batch_calls','apd_complete_offers',
        'final_price_ready_offers','retryable_offers'];
    $result=[
        'source'=>'anex-local-offer-collector-v1',
        'status'=>$rangeResult['status'],
        'requested_date_range'=>['from'=>$from,'to'=>$to],
        'window_count'=>$windowCount,
        'windows_completed'=>$rangeResult['windows_completed'],
        'windows'=>$windowReceipts,
        'selection_authority'=>false,
    ];
    foreach($sumKeys as $key){
        $result[$key]=0;
        foreach($windowReceipts as $receipt)$result[$key]+=(int)($receipt['result'][$key]??0);
    }
    $allTrue=static function(string $key)use($windowReceipts,$rangeResult):bool{
        if(($rangeResult['status']??null)!=='complete'||count($windowReceipts)===0)return false;
        foreach($windowReceipts as $receipt)if(($receipt['result'][$key]??null)!==true)return false;
        return true;
    };
    $result['grouped_drained']=$allTrue('grouped_drained');
    $result['concrete_drained']=$allTrue('concrete_drained');
    $result['discovered_set_drained']=$allTrue('discovered_set_drained');
    if($rangeResult['status']!=='complete'&&$windowReceipts!==[]){
        $last=$windowReceipts[count($windowReceipts)-1]['result'];
        if(array_key_exists('autosave_failure',$last))$result['autosave_failure']=$last['autosave_failure'];
    }
}
$result['search_client_instances']=$searchRequests;
$result['apd_client_instances']=$apdRequests;
$result['search_budget']=$searchBudget;
$result['apd_budget']=$apdBudget;
$result['supplier_calls_bounded']=true;
$result['browser_supplier_calls']=0;
$result['db_only_customer_results']=true;
$result['booking_calls']=0;
$result['lead_calls']=0;
echo json_encode($result,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n";
if (($result['status'] ?? null) !== 'complete') exit(1);