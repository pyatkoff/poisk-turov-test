<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/app/integrations/anex-local-offer-demand.php';

final class AnyTourAnexDemandFillV1
{
    public static function collectorCommand(array $scope,string $collector,int $generation,int $maxExpands=600,int $maxApd=600): array
    {
        if($collector===''||$generation<1||$generation>2147483647||$maxExpands<0||$maxExpands>600||$maxApd<1||$maxApd>600){
            throw new InvalidArgumentException('ANEX_DEMAND_FILL_COMMAND');
        }
        $row=[
            'departure_id'=>$scope['departureId']??null,'country_id'=>$scope['countryId']??null,'region_id'=>$scope['regionId']??null,
            'departure_date'=>$scope['dateFrom']??null,'nights'=>$scope['nights']??null,'adults'=>$scope['adults']??null,
            'children_count'=>is_array($scope['childAges']??null)?count($scope['childAges']):null,
            'child_ages_signature'=>is_array($scope['childAges']??null)?implode(',',$scope['childAges']):null,
            'searches'=>$scope['searches']??1,'observations'=>$scope['observations']??1,'last_seen'=>$scope['lastSeen']??'2026-01-01 00:00:00',
        ];
        $normalized=AnyTourAnexLocalOfferDemandV1::normalizeRows([$row],1)[0];
        if(($scope['dateTo']??null)!==$normalized['dateTo'])throw new InvalidArgumentException('ANEX_DEMAND_FILL_DATE');
        $cmd=[PHP_BINARY,$collector,
            '--departure='.$normalized['departureId'],'--country='.$normalized['countryId'],
            '--date-from='.$normalized['dateFrom'],'--date-to='.$normalized['dateTo'],'--nights='.$normalized['nights'],
            '--adults='.$normalized['adults'],'--max-expands='.$maxExpands,'--max-apd='.$maxApd,'--generation='.$generation,
        ];
        if($normalized['regionId']!==null)$cmd[]='--region='.$normalized['regionId'];
        if($normalized['childAges']!==[])$cmd[]='--child-ages='.implode(',',$normalized['childAges']);
        return $cmd;
    }

    public static function summarize(array $scope,array $result): array
    {
        return [
            'scope'=>[
                'departureId'=>$scope['departureId'],'countryId'=>$scope['countryId'],'regionId'=>$scope['regionId'],
                'dateFrom'=>$scope['dateFrom'],'dateTo'=>$scope['dateTo'],'nights'=>$scope['nights'],
                'adults'=>$scope['adults'],'childAges'=>$scope['childAges'],
            ],
            'groupedCandidates'=>(int)($result['grouped_candidates']??0),
            'expandCalls'=>(int)($result['expand_calls']??0),
            'charterConcreteCandidates'=>(int)($result['charter_concrete_candidates']??0),
            'apdBatchItems'=>(int)($result['apd_batch_items']??0),
            'finalPriceReadyOffers'=>(int)($result['final_price_ready_offers']??0),
            'retryableOffers'=>(int)($result['retryable_offers']??0),
            'discoveredSetDrained'=>($result['discovered_set_drained']??false)===true,
            'searchClientInstances'=>(int)($result['search_client_instances']??0),
            'apdClientInstances'=>(int)($result['apd_client_instances']??0),
            'snapshotFinalize'=>$result['snapshot_finalize']??null,
        ];
    }
}

if(realpath($_SERVER['SCRIPT_FILENAME']??'')!==__FILE__)return;
if(PHP_SAPI!=='cli'){fwrite(STDERR,"CLI only\n");exit(2);}
$args=[];foreach(array_slice($argv,1) as $arg){
    if(!str_starts_with($arg,'--')||!str_contains($arg,'='))throw new InvalidArgumentException('ANEX_DEMAND_FILL_ARG');
    [$k,$v]=explode('=',substr($arg,2),2);$args[$k]=$v;
}
$int=static function(mixed $value,int $min,int $max):int{
    if(!is_string($value)||!preg_match('/\A(?:0|[1-9][0-9]*)\z/D',$value))throw new InvalidArgumentException('ANEX_DEMAND_FILL_ARG');
    $n=(int)$value;if($n<$min||$n>$max)throw new InvalidArgumentException('ANEX_DEMAND_FILL_ARG');return $n;
};
$limit=$int($args['limit']??'3',1,20);
$lookback=$int($args['lookback-hours']??'168',1,168);
$horizon=$int($args['horizon-days']??'21',1,21);
$maxExpands=$int($args['max-expands']??'600',0,600);
$maxApd=$int($args['max-apd']??'600',1,600);
$generationBase=$int($args['generation-base']??'261900000',1,2147483600);
$root=dirname(__DIR__,2);$queue=$root.'/scripts/ops/anex_local_offer_demand_queue.php';$collector=$root.'/scripts/ops/anex_local_offer_collect.php';
foreach([$queue,$collector] as $file)if(!is_file($file)||is_link($file))throw new RuntimeException('ANEX_DEMAND_FILL_SOURCE');

$run=static function(array $command):array{
    // A full stderr pipe can block the child before it closes stdout. Keep the
    // streams separate, but spool diagnostics to a private auto-removed file.
    $stderrStream=tmpfile();
    if(!is_resource($stderrStream))throw new RuntimeException('ANEX_DEMAND_FILL_STDERR');
    $pipes=[];$process=null;
    try{
        $process=proc_open($command,[0=>['file','/dev/null','r'],1=>['pipe','w'],2=>$stderrStream],$pipes,null,null,['bypass_shell'=>true]);
        if(!is_resource($process))throw new RuntimeException('ANEX_DEMAND_FILL_PROCESS');
        $stdout=stream_get_contents($pipes[1]);fclose($pipes[1]);unset($pipes[1]);
        $code=proc_close($process);$process=null;
        if($stdout===false||!rewind($stderrStream))throw new RuntimeException('ANEX_DEMAND_FILL_OUTPUT');
        $stderr=stream_get_contents($stderrStream);
        if($stderr===false)throw new RuntimeException('ANEX_DEMAND_FILL_OUTPUT');
        return ['code'=>$code,'stdout'=>$stdout,'stderr'=>$stderr];
    }finally{
        foreach($pipes as $pipe)if(is_resource($pipe))fclose($pipe);
        if(is_resource($process))proc_close($process);
        fclose($stderrStream);
    }
};
$q=$run([PHP_BINARY,$queue,'--limit='.$limit,'--lookback-hours='.$lookback,'--horizon-days='.$horizon]);
if($q['code']!==0)throw new RuntimeException('ANEX_DEMAND_FILL_QUEUE');
$queuePayload=json_decode(trim($q['stdout']),true,64,JSON_THROW_ON_ERROR);
$scopes=$queuePayload['scopes']??null;if(!is_array($scopes)||!array_is_list($scopes))throw new RuntimeException('ANEX_DEMAND_FILL_QUEUE');

$results=[];$ready=0;$status='complete';$error=null;
foreach(array_slice($scopes,0,$limit) as $index=>$scope){
    $command=AnyTourAnexDemandFillV1::collectorCommand($scope,$collector,$generationBase+$index,$maxExpands,$maxApd);
    $child=$run($command);
    if($child['code']!==0){
        $status='stopped_on_error';
        $error=['index'=>$index,'code'=>$child['code'],'stderr'=>mb_substr(trim($child['stderr']),0,500)];
        // The collector can fail closed with a structured persistence receipt. Retain only
        // that known source so an unknown write can be reconciled without replaying supplier
        // work. Arbitrary/malformed child stdout remains private and is never surfaced.
        try{
            $candidate=json_decode(trim($child['stdout']),true,64,JSON_THROW_ON_ERROR);
            if(is_array($candidate)&&($candidate['source']??null)==='anex-local-offer-collector-v1'){
                $error['collectorResult']=$candidate;
            }
        }catch(Throwable $ignored){}
        break;
    }
    $value=json_decode(trim($child['stdout']),true,64,JSON_THROW_ON_ERROR);
    if(($value['browser_supplier_calls']??null)!==0||($value['booking_calls']??null)!==0||($value['lead_calls']??null)!==0){
        throw new RuntimeException('ANEX_DEMAND_FILL_AUTHORITY');
    }
    // A successful process exit is not proof of a persisted snapshot. Preserve
    // the original receipt on failure: a commit may be unknown, so never retry it.
    $finalize=$value['snapshot_finalize']??null;
    $persisted=is_array($finalize)&&(($finalize['published']??null)===true
        ||(($finalize['published']??null)===false&&($finalize['reason']??null)==='already_published'));
    $empty=is_array($finalize)&&($finalize['published']??null)===false
        &&($finalize['reason']??null)==='no_final_price_ready'
        &&($finalize['readyOfferCount']??null)===0&&($value['final_price_ready_offers']??null)===0;
    if(!$persisted&&!$empty){
        $status='stopped_on_error';
        $error=['index'=>$index,'code'=>$child['code'],'reason'=>'snapshot_not_published','collectorResult'=>$value];
        break;
    }
    $summary=AnyTourAnexDemandFillV1::summarize($scope,$value);$results[]=$summary;$ready+=$summary['finalPriceReadyOffers'];
}
$receipt=[
    'source'=>'anex-local-offer-demand-fill-v1','status'=>$status,'queueSource'=>$queuePayload['source']??null,
    'requestedScopes'=>$limit,'completedScopes'=>count($results),'readyOffersAcrossScopes'=>$ready,'results'=>$results,'error'=>$error,
    'browserSupplierCalls'=>0,'bookingCalls'=>0,'leadCalls'=>0,
];
echo json_encode($receipt,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
exit($status==='complete'?0:1);
