<?php
declare(strict_types=1);

// Actual CLI, collector, planner and batch executor; only supplier/cache/DB owners
// are isolated doubles. No real credentials, provider requests or database access.
function persistenceCheck(bool $ok, string $label): void
{
    if (!$ok) throw new RuntimeException('ANEX_CLI_PERSISTENCE_TEST:' . $label);
}
function persistenceCli(array $scenario): array
{
    $root=dirname(__DIR__);
    $dir=sys_get_temp_dir().'/anytour-anex-persistence-'.bin2hex(random_bytes(8));
    if(!mkdir($dir,0700))throw new RuntimeException('fixture directory');
    $write=static function(string $path,string $bytes)use($dir):void{
        $file=$dir.'/'.$path;
        if(!is_dir(dirname($file)))mkdir(dirname($file),0700,true);
        if(file_put_contents($file,$bytes)===false)throw new RuntimeException('fixture write');
    };
    $process=null;
    try{
        foreach(['scripts/ops/anex_local_offer_collect.php','app/integrations/anex-local-offer-collector.php',
            'app/integrations/anex-additional-prices-batch.php'] as $path){
            $bytes=file_get_contents($root.'/'.$path);
            if(!is_string($bytes))throw new RuntimeException('fixture source');
            $write('payload/'.$path,$bytes);
        }
        $write('scenario.json',json_encode($scenario,JSON_THROW_ON_ERROR));
        $write('payload/app/integrations/anex-anytour-offer-autosave.php', <<<'STUB'
<?php
function fixtureTrace(string $stage, mixed $value=null):void{
    file_put_contents(getenv('FIXTURE_DIR').'/trace.jsonl',json_encode([$stage,$value],JSON_THROW_ON_ERROR)."\n",FILE_APPEND);
}
function fixtureScenario():array{
    return json_decode(file_get_contents(getenv('FIXTURE_DIR').'/scenario.json'),true,64,JSON_THROW_ON_ERROR);
}
function anytour_anex_anytour_offer_autosave_runtime(array $plan,array &$state,array $results):array{
    $scenario=fixtureScenario();$n=($state['fixture_saves']??0)+1;$state['fixture_saves']=$n;
    $receipt=$scenario['receipts'][$n-1]??['published'=>true,'reason'=>null,'readyOfferCount'=>$n*6];
    fixtureTrace('save',$receipt);return $receipt;
}
function anytour_anex_anytour_offer_autosave_finalize_runtime(array &$state):array{
    $receipt=fixtureScenario()['final']??['published'=>false,'reason'=>'already_published','readyOfferCount'=>12];
    fixtureTrace('finalize',$receipt);return $receipt;
}
STUB);
        $write('payload/app/integrations/anex-apd-cache-runtime.php', <<<'STUB'
<?php
function anytour_anex_apd_cache_read_runtime(array $context):array{return ['hit'=>false];}
function anytour_anex_apd_cache_write_runtime(array $context,array $evidence,array &$state):void{}
STUB);
        $write('payload/v2/api-anex-search3-preview.php', <<<'STUB'
<?php
require_once __DIR__.'/../app/integrations/anex-additional-prices-batch.php';
final class AnyTourAnexClient{public function __construct(string $token){}}
final class AnyTourAnexAdditionalPricesClient{public function __construct(string $token){}}
final class AnyTourAnexSearchMappingRegistry{
    public static function fromPdo(PDO $db):self{return new self();}
    public function previewResolver():callable{return static fn():int=>501;}
}
final class AnyTourAnexProgramObservationRuntimeV1{
    public static function record(PDO $db,array &$state,DateTimeImmutable $now):array{return ['status'=>'complete'];}
}
function anytour_anex_search3_metadata(PDO $db,array $offers):array{return [];}
function anytour_anex_search3_run($request,$db,$client,&$cache,&$diagnostics,$observer,&$state,$scope,$background):array{
    fixtureTrace('search',$request);$state=['gateway'=>['saved_offers'=>['offers'=>[]],'search'=>['offers'=>[]]]];
    $tours=[];$scenario=fixtureScenario();
    for($i=0;$i<($scenario['groups']??12);++$i)$tours[]=['kind'=>'group_minimum','offer_ref'=>'anex_online:'.hash('sha256','g'.$i)];
    if(($scenario['regular']??false)===true)$tours[]=['kind'=>'concrete','offer_ref'=>'anex_online:'.hash('sha256','regular'),'flight_type'=>'regular'];
    return ['provider'=>'anex','search_ref'=>str_repeat('a',32),'hotels'=>[['local_id'=>501,'tours'=>$tours]]];
}
function anytour_anex_search3_followup($request,&$state,$resolver,$factory,$metadata,$clock,$checkpoint,$additional,$browser):array{
    $factory();$n=($state['fixture_expands']??0)+1;$state['fixture_expands']=$n;fixtureTrace('expand',$n);
    if((fixtureScenario()['expand_error']??0)===$n)throw new RuntimeException('FIXTURE_EXPAND_INVARIANT');
    $ref='anex_online:'.hash('sha256','c'.$n);
    $offer=['offer_key'=>$ref,'kind'=>'concrete','hotel'=>['local_id'=>501,'external_id'=>'77'],'checkin'=>'2026-10-30','nights'=>7];
    $state['gateway']['saved_offers']['offers'][$ref]=['offer'=>$offer,'supplier_tour_program_id'=>(string)$n,'supplier_currency_id'=>'1'];
    $state['gateway']['search']['offers'][]=['offer_key'=>$ref,'kind'=>'concrete','hotel_external_id'=>'77'];
    return ['status'=>'expanded','hotels'=>[['local_id'=>501,'tours'=>[['kind'=>'concrete','offer_ref'=>$ref,'flight_type'=>'charter']]]]];
}
function anytour_anex_search3_additional_batch($request,&$state,$resolver,$metadata,$clock,$checkpoint,$factory):array{
    fixtureTrace('batch',$request['items']);
    $plan=anytour_anex_additional_prices_batch_plan($request['items'],$state);
    $reply=anytour_anex_additional_prices_batch_execute($plan,$state,static function(array $context)use($factory):array{
        $factory();fixtureTrace('apd',$context['context_digest']);return ['fixture'=>'terminal'];
    },$checkpoint);
    // The private autosave result must not change or leak into the supplier reply.
    if(array_keys($reply)!==['requested_offers','unique_contexts','offers'])throw new RuntimeException('PUBLIC_BATCH_KEYS_CHANGED');
    foreach($reply['offers'] as $offer){
        if(array_keys($offer)!==['offer_ref','local_hotel_id','context_digest','status','additional_prices','retryable','retry_reason']
            ||$offer['status']!=='complete'||$offer['additional_prices']!==['fixture'=>'terminal']
            ||$offer['retryable']!==false||$offer['retry_reason']!==null)throw new RuntimeException('PUBLIC_BATCH_OFFER_CHANGED');
    }
    fixtureTrace('public_unchanged',true);
    if((fixtureScenario()['omit_receipt_at']??0)===($state['fixture_saves']??0))unset($state['anytour_offer_autosave_last_result']);
    return ['status'=>'additional_prices_batch','offers'=>array_map(static fn(array $o):array=>[
        'status'=>'additional_prices','finalPriceReady'=>true,'retryable'=>false,
    ],$reply['offers'])];
}
STUB);
        foreach(['anex-preview-gateway','anex-search-mapping-registry','anex-search-observations','anex-additional-prices-client'] as $name)
            $write('payload/app/integrations/'.$name.'.php','<?php // isolated dependency');
        $write('anytoour.ru/config.php','<?php // no real site configuration');
        $write('anytoour.ru/data/db-v1.php', <<<'STUB'
<?php
final class FixturePdo extends PDO{public function __construct(){}}
function v2_data_db():PDO{return new FixturePdo();}
STUB);
        $pipes=[];
        $process=proc_open([PHP_BINARY,$dir.'/payload/scripts/ops/anex_local_offer_collect.php',
            '--departure=1','--country=4','--date-from=2026-10-30','--nights=7','--child-ages=3,7'],
            [0=>['file','/dev/null','r'],1=>['file',$dir.'/stdout','w'],2=>['file',$dir.'/stderr','w']],$pipes,null,
            ['ANYTOUR_PROJECT_ROOT'=>$dir.'/anytoour.ru','ANEX_API_TOKEN'=>'fixture','ANEX_B2B_TOKEN'=>'fixture','FIXTURE_DIR'=>$dir]);
        if(!is_resource($process))throw new RuntimeException('fixture process');
        $deadline=microtime(true)+5;
        do{
            $status=proc_get_status($process);if(!$status['running'])break;
            if(microtime(true)>$deadline)throw new RuntimeException('fixture timeout');usleep(10000);
        }while(true);
        $code=proc_close($process);$process=null;if($code<0)$code=$status['exitcode'];
        $trace=[];
        foreach(is_file($dir.'/trace.jsonl')?file($dir.'/trace.jsonl',FILE_IGNORE_NEW_LINES):[] as $line)$trace[]=json_decode($line,true,64,JSON_THROW_ON_ERROR);
        $stdout=file_get_contents($dir.'/stdout');
        return ['code'=>$code,'result'=>$stdout===''?null:json_decode($stdout,true,64,JSON_THROW_ON_ERROR),
            'stderr'=>file_get_contents($dir.'/stderr'),'trace'=>$trace,'counts'=>array_count_values(array_column($trace,0))];
    }finally{
        if(is_resource($process)){proc_terminate($process,9);proc_close($process);}
        $files=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
        foreach($files as $file){if($file->isDir()&&!$file->isLink())rmdir($file->getPathname());else unlink($file->getPathname());}
        rmdir($dir);
    }
}

$failures=[
    ['published'=>false,'reason'=>'autosave_failed','writeOutcome'=>'unknown'],
    ['published'=>false,'reason'=>'local_ingest_unavailable'],
    ['published'=>false,'reason'=>'runtime_dependency_unavailable'],
    ['published'=>false,'reason'=>'protected_price_mismatch'],
    ['published'=>false,'reason'=>'supplier_identity_changed'],
    ['published'=>false,'reason'=>'canonical_bridge_changed'],
    ['published'=>false,'reason'=>'search_context_expired'],
    ['published'=>false], [], ['published'=>'true','reason'=>null],
    ['published'=>false,'reason'=>'unexpected_receipt'],
];
foreach($failures as $i=>$receipt){
    $r=persistenceCli(['receipts'=>[$receipt]]);
    if($i===0)echo 'ANEX_CLI_FIRST_FAILURE '.json_encode(['code'=>$r['code'],'status'=>$r['result']['status']??null,'counts'=>$r['counts']],JSON_THROW_ON_ERROR)."\n";
    persistenceCheck($r['code']===1&&$r['result']['status']==='incomplete','first failure stops CLI');
    persistenceCheck(($r['counts']['expand']??0)===6&&($r['counts']['apd']??0)===6
        &&($r['counts']['save']??0)===1&&!isset($r['counts']['finalize']),'no later queries/finalize/write attempt');
    persistenceCheck($r['result']['autosave_failure']===['phase'=>'batch','receipt'=>$receipt],'exact failed receipt retained');
    persistenceCheck(!isset($r['result']['snapshot_finalize'])&&!isset($r['result']['final_price_ready_offers']),'no false full receipt');
}
$success=['published'=>true,'reason'=>null,'readyOfferCount'=>6];
$late=persistenceCli(['groups'=>18,'receipts'=>[$success,$failures[0]]]);
persistenceCheck($late['code']===1&&$late['counts']['expand']===12&&$late['counts']['apd']===12
    &&$late['counts']['save']===2&&!isset($late['counts']['finalize']),'late refusal stops next batch without retry');
persistenceCheck(array_values(array_filter($late['trace'],static fn(array $e):bool=>$e[0]==='save'))[0][1]===$success,'earlier successful receipt untouched');
foreach([$success,['published'=>false,'reason'=>'already_published'],['published'=>false,'reason'=>'no_final_price_ready']] as $receipt){
    $r=persistenceCli(['receipts'=>[$receipt,$receipt],'final'=>$receipt]);
    persistenceCheck($r['code']===0&&$r['result']['status']==='complete'&&$r['counts']['expand']===12
        &&$r['counts']['batch']===2&&$r['counts']['finalize']===1,'nonfailure paths still drain and finalize');
    persistenceCheck($r['result']['snapshot_finalize']===$receipt,'exact final receipt unchanged');
}
$final=persistenceCli(['final'=>$failures[0]]);
persistenceCheck($final['code']===1&&$final['result']['status']==='incomplete'&&$final['counts']['finalize']===1
    &&$final['result']['autosave_failure']===['phase'=>'finalize','receipt'=>$failures[0]],'failed finalization not success or replayed');
$missing=persistenceCli(['omit_receipt_at'=>2]);
persistenceCheck($missing['code']===1&&!isset($missing['counts']['finalize'])
    &&$missing['result']['autosave_failure']===['phase'=>'batch','receipt'=>null],'absent receipt cannot reuse previous success');
foreach([['groups'=>0,'final'=>['published'=>false,'reason'=>'no_final_price_ready']],
    ['groups'=>0,'regular'=>true,'final'=>['published'=>true,'reason'=>null,'readyOfferCount'=>0,'confirmationRequiredOfferCount'=>1]]] as $scenario){
    $r=persistenceCli($scenario);
    persistenceCheck($r['code']===0&&$r['result']['status']==='complete'&&!isset($r['counts']['apd'])
        &&$r['counts']['finalize']===1,'zero/regular-only finalize supported without APD');
}
$other=persistenceCli(['expand_error'=>7]);
persistenceCheck($other['code']!==0&&$other['result']===null&&str_contains($other['stderr'],'FIXTURE_EXPAND_INVARIANT')
    &&!isset($other['counts']['finalize']),'unrelated invariant not swallowed');
echo 'ANEX_CLI_PERSISTENCE_OK failures='.count($failures).' success=3 late=1 final=1 missing=1 empty_regular=2 invariant=1 public_unchanged=1 supplier=0 db=0'."\n";
