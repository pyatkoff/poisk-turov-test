<?php
/**
 * Targeted first-party inventory refresh for the exact two current seasonal SEO previews.
 *
 * This is a background data collector only. It performs at most two bounded Tourvisor
 * searches and persists returned rows through the existing price observer. It does not
 * render, publish, index, canonicalize or add any SEO route to a sitemap.
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__.'/db-v1.php';
require_once __DIR__.'/tourvisor-client-v1.php';
require_once __DIR__.'/price-observer-v1.php';

function seasonal_target_arg(array $argv,string $name,?string $fallback=null):?string
{
    foreach($argv as $arg) if(str_starts_with($arg,'--'.$name.'=')) return substr($arg,strlen($name)+3);
    return $fallback;
}
function seasonal_target_int(array $argv,string $name,int $default,int $min,int $max):int
{
    $raw=seasonal_target_arg($argv,$name,(string)$default);
    $n=filter_var($raw,FILTER_VALIDATE_INT);
    return $n===false?$default:max($min,min($max,(int)$n));
}
function seasonal_target_entity_id(mixed $value):?int
{
    if(is_array($value)) $value=$value['id']??null;
    $id=filter_var($value,FILTER_VALIDATE_INT);
    return $id!==false&&(int)$id>0?(int)$id:null;
}
function seasonal_target_search_id(array $payload):?int
{
    foreach(['searchId','id'] as $key){$id=seasonal_target_entity_id($payload[$key]??null);if($id!==null)return $id;}
    return null;
}
function seasonal_target_complete(array $payload):bool
{
    return (int)($payload['progress']??0)>=100||strtolower(trim((string)($payload['status']??'')))==='complete';
}
function seasonal_target_rows(array $payload):array
{
    if(array_is_list($payload))return $payload;
    foreach(['hotels','items','results'] as $key)if(is_array($payload[$key]??null))return $payload[$key];
    return [];
}
function seasonal_target_fetch_results(int $searchId):array
{
    $last=null;
    foreach([10000,5000,2000,1000,500,100] as $limit){
        try{return seasonal_target_rows(v2_data_tv_get('/tours/search/'.$searchId,['limit'=>$limit]));}
        catch(Throwable $e){$last=$e;}
    }
    throw new RuntimeException('seasonal target results unavailable',0,$last);
}
function seasonal_target_business_timezone():DateTimeZone
{
    static $timezone=null;
    return $timezone??=new DateTimeZone('Europe/Moscow');
}
function seasonal_target_business_today():DateTimeImmutable
{
    return new DateTimeImmutable('today',seasonal_target_business_timezone());
}

/** Exact pilot identities. Keeping this allowlist explicit prevents accidental broad collection. */
function v2_seo_seasonal_preview_collection_targets(?DateTimeImmutable $today=null):array
{
    $timezone=seasonal_target_business_timezone();
    $today=($today??seasonal_target_business_today())->setTimezone($timezone)->setTime(0,0);
    $monthStart=new DateTimeImmutable('2026-09-01',$timezone);
    $monthEnd=new DateTimeImmutable('2026-09-30',$timezone);
    $from=$today->modify('+1 day');
    if($from<$monthStart)$from=$monthStart;
    if($from>$monthEnd)return [];
    $to=$from->modify('+20 days');
    if($to>$monthEnd)$to=$monthEnd;
    $dateFrom=$from->format('Y-m-d');$dateTo=$to->format('Y-m-d');
    return [
        [
            'preview_key'=>'antalya-september',
            'page_key'=>'resort_month:1:4:20:2026-09',
            'departure_id'=>1,'country_id'=>4,'region_id'=>20,
            'date_from'=>$dateFrom,'date_to'=>$dateTo,
        ],
        [
            'preview_key'=>'maldives-september',
            'page_key'=>'month:1:8:2026-09',
            'departure_id'=>1,'country_id'=>8,'region_id'=>null,
            'date_from'=>$dateFrom,'date_to'=>$dateTo,
        ],
    ];
}

$businessTimezone=seasonal_target_business_timezone();
$nowDateRaw=trim((string)seasonal_target_arg($argv,'now-date',''));
$today=$nowDateRaw===''?seasonal_target_business_today():DateTimeImmutable::createFromFormat('!Y-m-d',$nowDateRaw,$businessTimezone);
if(!$today)throw new InvalidArgumentException('invalid --now-date');
$today=$today->setTimezone($businessTimezone)->setTime(0,0);
$targets=v2_seo_seasonal_preview_collection_targets($today);
$dryRun=in_array(strtolower((string)seasonal_target_arg($argv,'dry-run','0')),['1','true','yes'],true);
if($dryRun){
    echo json_encode([
        'state'=>'review_only_seasonal_target_plan',
        'business_timezone'=>$businessTimezone->getName(),
        'business_date'=>$today->format('Y-m-d'),
        'target_count'=>count($targets),
        'targets'=>$targets,
        'tourvisor_calls_allowed'=>false,
        'publication_allowed'=>false,
        'indexation_allowed'=>false,
        'sitemap_allowed'=>false,
        'route_launch_allowed'=>false,
    ],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),"\n";
    exit(0);
}
if($targets===[]){echo "SEO_SEASONAL_TARGET_COLLECTION_NO_ACTIVE_TARGETS\n";exit(0);}

$pollAttempts=seasonal_target_int($argv,'poll-attempts',20,1,30);
$pollSeconds=seasonal_target_int($argv,'poll-seconds',2,1,10);
$pdo=v2_data_db();
$ok=0;$empty=0;$failed=0;$writtenTotal=0;
foreach($targets as $target){
    $search=[
        'departureId'=>(int)$target['departure_id'],
        'countryId'=>(int)$target['country_id'],
        'dateFrom'=>(string)$target['date_from'],
        'dateTo'=>(string)$target['date_to'],
        'nightsFrom'=>5,'nightsTo'=>14,'adults'=>2,'currency'=>'RUB',
        'onlyCharter'=>false,'onlyDirect'=>false,
    ];
    if($target['region_id']!==null)$search['regionIds']=[(int)$target['region_id']];
    $searchId=null;
    echo 'SEO_SEASONAL_TARGET_START preview='.$target['preview_key'].' country='.$target['country_id'].' region='.($target['region_id']??0).' from='.$target['date_from'].' to='.$target['date_to']."\n";
    try{
        $searchId=seasonal_target_search_id(v2_data_tv_get('/tours/search',$search));
        if($searchId===null)throw new RuntimeException('no searchId');
        $complete=false;
        for($poll=0;$poll<$pollAttempts;$poll++){
            sleep($pollSeconds);
            if(seasonal_target_complete(v2_data_tv_get('/tours/search/'.$searchId.'/status',['operatorStatus'=>false]))){$complete=true;break;}
        }
        if(!$complete)throw new RuntimeException('bounded poll timeout');
        $rows=seasonal_target_fetch_results($searchId);
        $trusted=[];
        foreach($rows as $hotel){
            if(!is_array($hotel))continue;
            $countryId=seasonal_target_entity_id($hotel['country']??$hotel['countryId']??null);
            if($countryId!==null&&$countryId!==(int)$target['country_id'])continue;
            if($target['region_id']!==null){
                $regionId=seasonal_target_entity_id($hotel['region']??$hotel['regionId']??null);
                if($regionId!==null&&$regionId!==(int)$target['region_id'])continue;
            }
            $trusted[]=$hotel;
        }
        $observed=v2_data_observe_search_results($trusted,[
            'searchId'=>$searchId,
            'departureId'=>(int)$target['departure_id'],
            'countryId'=>(int)$target['country_id'],
            'adults'=>2,'childs'=>[],'currency'=>'RUB',
            'source'=>'seo_seasonal_pilot','maxHotels'=>5000,'maxTours'=>50000,
        ]);
        $written=(int)($observed['written']??0);$writtenTotal+=$written;
        if($trusted===[]){$empty++;echo 'SEO_SEASONAL_TARGET_EMPTY preview='.$target['preview_key']."\n";}
        else{$ok++;echo 'SEO_SEASONAL_TARGET_OK preview='.$target['preview_key'].' returned_hotels='.count($trusted).' observations_written='.$written."\n";}
    }catch(Throwable $e){
        $failed++;
        fwrite(STDERR,'SEO_SEASONAL_TARGET_ERROR preview='.$target['preview_key'].' message='.str_replace(["\r","\n"],' ',mb_substr($e->getMessage(),0,500))."\n");
    }
}
echo 'SEO_SEASONAL_TARGET_DONE targets='.count($targets).' success='.$ok.' empty='.$empty.' failed='.$failed.' observations_written='.$writtenTotal."\n";
if($failed>0)exit(2);
