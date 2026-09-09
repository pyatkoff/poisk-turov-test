<?php
/** Resumable TOP500 collection across every month; the historical CLI path is retained. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/db-v1.php';
require_once __DIR__.'/tourvisor-client-v1.php';
require_once __DIR__.'/price-observer-v1.php';
require_once __DIR__.'/top-hotels-v1.php';
require_once __DIR__.'/top500-monthly-plan-v1.php';

function top500_daily_arg(array $argv, string $name, ?string $fallback = null): ?string
{
    foreach ($argv as $arg) if (str_starts_with($arg, '--'.$name.'=')) return substr($arg, strlen($name)+3);
    return $fallback;
}
function top500_daily_int(array $argv, string $name, int $default, int $min, int $max): int
{
    $n=filter_var(top500_daily_arg($argv,$name,(string)$default),FILTER_VALIDATE_INT);
    return $n===false?$default:max($min,min($max,(int)$n));
}
function top500_daily_search_id(array $payload): ?int
{
    foreach (['searchId','id'] as $key) { $id=filter_var($payload[$key]??null,FILTER_VALIDATE_INT); if($id!==false&&(int)$id>0)return (int)$id; }
    return null;
}
function top500_daily_complete(array $payload): bool
{
    return (int)($payload['progress']??0)>=100 || strtolower(trim((string)($payload['status']??'')))==='complete';
}
function top500_daily_rows(array $payload): array
{
    if(array_is_list($payload))return $payload;
    foreach(['hotels','items','results'] as $key)if(is_array($payload[$key]??null))return $payload[$key];
    throw new RuntimeException('unrecognized Tourvisor result shape; not an empty search');
}
function top500_daily_get(string $path,array $params=[]): array
{
    static $last=0.0;
    $delay=1.05-(microtime(true)-$last);
    if($delay>0)usleep((int)ceil($delay*1000000));
    $last=microtime(true);
    return v2_data_tv_get($path,$params);
}
function top500_daily_fetch_results(int $searchId): array
{
    // A plan target explicitly contains <=30 hotels; 100 is not a tour-row cap.
    return top500_daily_rows(top500_daily_get('/tours/search/'.$searchId,['limit'=>100]));
}
function top500_daily_attempt_start(PDO $pdo,array $target): int
{
    $s=$pdo->prepare("INSERT INTO tour_matrix_collection_attempts (criterion,target_key,departure_id,country_id,region_id,hotel_ids_json,date_from,date_to,nights_from,nights_to,status,started_at) VALUES ('hotel_batch',:target_key,:departure,:country,NULL,:hotels,:date_from,:date_to,5,14,'started',NOW())");
    $s->execute(['target_key'=>$target['target_key'],'departure'=>$target['departure_id'],'country'=>$target['country_id'],'hotels'=>json_encode($target['hotel_ids'],JSON_THROW_ON_ERROR),'date_from'=>$target['date_from'],'date_to'=>$target['date_to']]);
    return (int)$pdo->lastInsertId();
}
function top500_daily_attempt_finish(PDO $pdo,int $id,string $status,?int $searchId,int $rows,int $written,?string $error=null): void
{
    $s=$pdo->prepare("UPDATE tour_matrix_collection_attempts SET status=:status,search_id=:search_id,rows_received=:rows,observations_written=observations_written+:written,error_text=:error,finished_at=NOW() WHERE id=:id");
    $s->execute(['status'=>$status,'search_id'=>$searchId,'rows'=>max(0,$rows),'written'=>max(0,$written),'error'=>$error!==null?mb_substr($error,0,1000):null,'id'=>$id]);
}
function top500_monthly_attempts(PDO $pdo): array
{
    return $pdo->query("SELECT a.*,UNIX_TIMESTAMP(a.started_at) started_epoch,UNIX_TIMESTAMP(a.finished_at) finished_epoch FROM tour_matrix_collection_attempts a JOIN (SELECT target_key,MAX(id) id FROM tour_matrix_collection_attempts WHERE criterion='hotel_batch' AND target_key LIKE 'monthly:%' AND started_at>=DATE_SUB(NOW(),INTERVAL 7 DAY) GROUP BY target_key) last_attempt ON last_attempt.id=a.id")->fetchAll(PDO::FETCH_ASSOC)?:[];
}

$months=top500_daily_int($argv,'months',12,1,18);
$budget=top500_daily_int($argv,'budget',64,1,100);
$maxSeconds=top500_daily_int($argv,'max-seconds',1500,60,1800);
$freshHours=top500_daily_int($argv,'refresh-hours',24,1,72);
$pollAttempts=top500_daily_int($argv,'poll-attempts',20,1,30);
$pollSeconds=top500_daily_int($argv,'poll-seconds',2,1,10);
$departureId=top500_daily_int($argv,'preferred-departure-id',1,1,1000000);
$onlyMonth=(string)top500_daily_arg($argv,'only-month','');
$onlyCountry=top500_daily_int($argv,'only-country',0,0,1000000);
if($onlyMonth!==''&&!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D',$onlyMonth))throw new InvalidArgumentException('invalid target month');

$pdo=v2_data_db();
// Connection-scoped advisory lock also protects against a manual CLI overlapping
// the scheduled workflow. It is released automatically on process termination.
if((int)$pdo->query("SELECT GET_LOCK('anytour_top500_monthly_v1',0)")->fetchColumn()!==1){echo "TOP500_MONTHLY_BUSY\n";exit;}
$deadline=microtime(true)+$maxSeconds;
try {
    $priorityIds=array_values(v2_priority_hotel_ids());
    if(!$priorityIds)throw new RuntimeException('top500 priority list empty');
    $csv=implode(',',array_map('intval',$priorityIds));
    $hotelRows=$pdo->query("SELECT id,country_id,country_name,is_active FROM catalog_hotels WHERE id IN ($csv) ORDER BY id")->fetchAll(PDO::FETCH_ASSOC)?:[];
    $departureRows=$pdo->query("SELECT dc.departure_id,dc.country_id,dc.is_active FROM catalog_departure_countries dc JOIN catalog_departures d ON d.id=dc.departure_id AND d.is_active=1 JOIN catalog_countries c ON c.id=dc.country_id AND c.is_active=1 WHERE dc.is_active=1 ORDER BY dc.country_id,dc.departure_id")->fetchAll(PDO::FETCH_ASSOC)?:[];
    $today=(string)$pdo->query('SELECT CURDATE()')->fetchColumn();
    $plan=v2_top500_monthly_plan($priorityIds,$hotelRows,$departureRows,$today,$months,$departureId);
    if($onlyMonth!==''&&!in_array($onlyMonth,$plan['months'],true))throw new InvalidArgumentException('target month outside horizon');
    $queue=v2_top500_monthly_queue($plan['targets'],top500_monthly_attempts($pdo),(int)$pdo->query('SELECT UNIX_TIMESTAMP()')->fetchColumn(),$freshHours);
    echo 'TOP500_MONTHLY_PLAN '.json_encode(['source_hotels'=>$plan['source_hotel_count'],'searchable_hotels'=>$plan['hotel_count'],'missing_catalog'=>$plan['missing_catalog_ids'],'no_departure'=>$plan['no_departure_hotels'],'months'=>$plan['months'],'window_days'=>7,'total_targets'=>$plan['batch_count'],'fresh'=>$queue['fresh'],'pending'=>$queue['pending'],'budget'=>$budget],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";
    $processed=0; $failed=0; $partial=0; $writtenTotal=0;
    foreach($queue['targets'] as $target){
        if(($onlyMonth!==''&&$target['month']!==$onlyMonth)||($onlyCountry>0&&(int)$target['country_id']!==$onlyCountry))continue;
        if($processed>=$budget||microtime(true)>=$deadline)break;
        $processed++;
        $searchId=$target['resume_search_id'];
        $attemptId=$target['resume_attempt_id']??top500_daily_attempt_start($pdo,$target);
        $trusted=[]; $written=0;
        echo 'TOP500_MONTHLY_START key='.$target['target_key'].' resume='.($searchId??0)."\n";
        try {
            $hotelIds=array_values(array_map('intval',$target['hotel_ids']));
            if($searchId===null){
                $searchId=top500_daily_search_id(top500_daily_get('/tours/search',['departureId'=>(int)$target['departure_id'],'countryId'=>(int)$target['country_id'],'dateFrom'=>$target['date_from'],'dateTo'=>$target['date_to'],'nightsFrom'=>5,'nightsTo'=>14,'adults'=>2,'currency'=>'RUB','onlyCharter'=>false,'onlyDirect'=>false,'hotelIds'=>$hotelIds]));
                if($searchId===null)throw new RuntimeException('no searchId');
                $pdo->prepare('UPDATE tour_matrix_collection_attempts SET search_id=:search_id WHERE id=:id')->execute(['search_id'=>$searchId,'id'=>$attemptId]);
            }
            $complete=false;
            for($poll=0;$poll<$pollAttempts&&microtime(true)<$deadline;$poll++){
                sleep($pollSeconds);
                if(top500_daily_complete(top500_daily_get('/tours/search/'.$searchId.'/status',['operatorStatus'=>false]))){$complete=true;break;}
            }
            // Incomplete searches can already contain useful trusted prices.
            // Persist them now, but do not label the target complete or empty.
            $rows=top500_daily_fetch_results($searchId); $requested=array_fill_keys($hotelIds,true);
            foreach($rows as $hotel){
                if(!is_array($hotel))continue;
                $id=v2_price_observer_id($hotel['id']??null); $country=v2_price_observer_id($hotel['country']??null);
                if($id!==null&&isset($requested[$id])&&($country===null||$country===(int)$target['country_id']))$trusted[]=$hotel;
            }
            $observed=v2_data_observe_search_results($trusted,['searchId'=>$searchId,'departureId'=>(int)$target['departure_id'],'countryId'=>(int)$target['country_id'],'adults'=>2,'childs'=>[],'currency'=>'RUB','source'=>'scheduled_monitor','maxHotels'=>5000,'maxTours'=>50000]);
            $written=(int)($observed['written']??0); $writtenTotal+=$written;
            $status=$complete?($trusted===[]?'empty':'success'):'timeout';
            if(!$complete)$partial++;
            top500_daily_attempt_finish($pdo,$attemptId,$status,$searchId,count($trusted),$written,$complete?null:'partial results saved; resume existing search');
            echo 'TOP500_MONTHLY_RESULT key='.$target['target_key'].' status='.$status.' returned_hotels='.count($trusted).' written='.$written."\n";
        }catch(Throwable $e){
            top500_daily_attempt_finish($pdo,$attemptId,'failure',$searchId,count($trusted),$written,$e->getMessage());
            $failed++;
            fwrite(STDERR,'TOP500_MONTHLY_ERROR key='.$target['target_key'].' '.str_replace(["\r","\n"],' ',mb_substr($e->getMessage(),0,800))."\n");
        }
    }
    $after=v2_top500_monthly_queue($plan['targets'],top500_monthly_attempts($pdo),(int)$pdo->query('SELECT UNIX_TIMESTAMP()')->fetchColumn(),$freshHours);
    echo 'TOP500_MONTHLY_DONE '.json_encode(['processed'=>$processed,'failed'=>$failed,'partial'=>$partial,'observations_written'=>$writtenTotal,'fresh_targets'=>$after['fresh'],'pending_targets'=>$after['pending'],'complete'=>$after['pending']===0,'months'=>$after['months']],JSON_UNESCAPED_SLASHES)."\n";
}finally{$pdo->query("SELECT RELEASE_LOCK('anytour_top500_monthly_v1')");}
if($failed>0)exit(2);
