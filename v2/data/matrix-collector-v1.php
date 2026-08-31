<?php
/**
 * Rotate Tourvisor collection through three dimensions:
 *  - hotel_batch: up to 30 hotelIds per search, owner top-500 first
 *  - resort: one region per search
 *  - month: broad departure/country month slices (<=21 days)
 *
 * Top-500 hotel collection is breadth-first: every priority batch gets an
 * initial nearby-window pass before already-covered batches gain more depth.
 * Results are persisted into tour_price_observations through price-observer-v1.php.
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/db-v1.php';
require_once __DIR__ . '/tourvisor-client-v1.php';
require_once __DIR__ . '/price-observer-v1.php';
require_once __DIR__ . '/top-hotels-v1.php';

function matrix_arg(array $argv,string $name,?string $fallback=null): ?string { foreach($argv as $arg) if(str_starts_with($arg,'--'.$name.'=')) return substr($arg,strlen($name)+3); return $fallback; }
function matrix_int(array $argv,string $name,int $default,int $min,int $max): int { $raw=matrix_arg($argv,$name,(string)$default); $n=filter_var($raw,FILTER_VALIDATE_INT); return $n===false?$default:max($min,min($max,(int)$n)); }
function matrix_search_id(array $x): ?int { foreach(['searchId','id'] as $k){$v=filter_var($x[$k]??null,FILTER_VALIDATE_INT);if($v!==false&&(int)$v>0)return(int)$v;} return null; }
function matrix_complete(array $x): bool { return (int)($x['progress']??0)>=100 || strtolower(trim((string)($x['status']??'')))==='complete'; }
function matrix_rows(array $payload): array { if(array_is_list($payload))return $payload; foreach(['hotels','items','results'] as $k)if(is_array($payload[$k]??null))return $payload[$k]; return []; }
function matrix_fetch_results(int $searchId): array { $last=null; foreach([10000,5000,2000,1000,500,100] as $limit){try{return matrix_rows(v2_data_tv_get('/tours/search/'.$searchId,['limit'=>$limit]));}catch(Throwable $e){$last=$e;}} throw new RuntimeException('matrix results unavailable',0,$last); }
function matrix_month_windows(int $months): array {
    $base=(new DateTimeImmutable('first day of next month'))->setTime(0,0); $out=[]; $order=0;
    for($i=0;$i<$months;$i++){
        $start=$base->modify('+'.$i.' months'); $end=$start->modify('last day of this month'); $cursor=$start; $part=1;
        while($cursor<=$end){$sliceEnd=min($cursor->modify('+20 days'),$end);$out[]=['month'=>$start->format('Y-m'),'part'=>$part,'from'=>$cursor->format('Y-m-d'),'to'=>$sliceEnd->format('Y-m-d'),'order'=>$order++];$cursor=$sliceEnd->modify('+1 day');$part++;}
    }
    return $out;
}
function matrix_pairs(PDO $pdo,int $limit=30): array {
    $sql="SELECT dc.departure_id,dc.country_id,d.name departure_name,c.name country_name FROM catalog_departure_countries dc JOIN catalog_departures d ON d.id=dc.departure_id AND d.is_active=1 JOIN catalog_countries c ON c.id=dc.country_id AND c.is_active=1 WHERE dc.is_active=1 ORDER BY dc.departure_id,dc.country_id LIMIT ".(int)$limit;
    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
function matrix_last_attempts(PDO $pdo): array { $rows=$pdo->query("SELECT criterion,target_key,MAX(started_at) last_at FROM tour_matrix_collection_attempts GROUP BY criterion,target_key")->fetchAll(PDO::FETCH_ASSOC)?:[]; $map=[];foreach($rows as $r)$map[$r['criterion'].'|'.$r['target_key']]=$r['last_at'];return $map; }
function matrix_batch_family(string $targetKey): ?string { if(preg_match('/^(\d+:\d+:b\d+):/',$targetKey,$m))return $m[1]; return null; }
function matrix_hotel_batch_coverage(PDO $pdo): array {
    $rows=$pdo->query("SELECT target_key,status FROM tour_matrix_collection_attempts WHERE criterion='hotel_batch'")->fetchAll(PDO::FETCH_ASSOC)?:[];
    $map=[]; foreach($rows as $r){ if(!in_array((string)$r['status'],['success','empty'],true))continue; $family=matrix_batch_family((string)$r['target_key']); if($family!==null)$map[$family]=($map[$family]??0)+1; }
    return $map;
}
function matrix_order_hotel_ids(array $ids): array { $rank=array_flip(v2_priority_hotel_ids()); usort($ids,static function(int $a,int $b)use($rank):int{$ra=$rank[$a]??PHP_INT_MAX;$rb=$rank[$b]??PHP_INT_MAX;return $ra===$rb?($a<=>$b):($ra<=>$rb);}); return $ids; }
function matrix_candidates(PDO $pdo,int $months): array {
    $pairs=matrix_pairs($pdo,40);$windows=matrix_month_windows($months);$last=matrix_last_attempts($pdo);$coverage=matrix_hotel_batch_coverage($pdo);$c=[];$prioritySet=array_fill_keys(v2_priority_hotel_ids(),true);
    foreach($pairs as $pair){
        $dep=(int)$pair['departure_id'];$country=(int)$pair['country_id'];
        $hs=$pdo->prepare("SELECT id FROM catalog_hotels WHERE country_id=:country AND is_active=1 ORDER BY id");$hs->execute(['country'=>$country]);$ids=matrix_order_hotel_ids(array_map('intval',$hs->fetchAll(PDO::FETCH_COLUMN)?:[]));$batches=array_chunk($ids,30);
        $rs=$pdo->prepare("SELECT id,name FROM catalog_regions WHERE country_id=:country AND is_active=1 ORDER BY id");$rs->execute(['country'=>$country]);$regions=$rs->fetchAll(PDO::FETCH_ASSOC)?:[];
        foreach($windows as $w){
            foreach($batches as $bi=>$batch){
                $family=$dep.':'.$country.':b'.($bi+1);$key=$family.':'.$w['month'].':p'.$w['part'];$priority=false;foreach($batch as $hotelId){if(isset($prioritySet[$hotelId])){$priority=true;break;}}
                $c[]=['criterion'=>'hotel_batch','target_key'=>$key,'batch_family'=>$family,'coverage'=>$coverage[$family]??0,'window_order'=>$w['order'],'departure_id'=>$dep,'country_id'=>$country,'region_id'=>null,'hotel_ids'=>$batch,'priority'=>$priority,'date_from'=>$w['from'],'date_to'=>$w['to'],'last'=>$last['hotel_batch|'.$key]??'0000-00-00 00:00:00'];
            }
            foreach($regions as $r){$rid=(int)$r['id'];$key=$dep.':'.$country.':r'.$rid.':'.$w['month'].':p'.$w['part'];$c[]=['criterion'=>'resort','target_key'=>$key,'coverage'=>0,'window_order'=>$w['order'],'departure_id'=>$dep,'country_id'=>$country,'region_id'=>$rid,'hotel_ids'=>[],'priority'=>false,'date_from'=>$w['from'],'date_to'=>$w['to'],'last'=>$last['resort|'.$key]??'0000-00-00 00:00:00'];}
            $key=$dep.':'.$country.':'.$w['month'].':p'.$w['part'];$c[]=['criterion'=>'month','target_key'=>$key,'coverage'=>0,'window_order'=>$w['order'],'departure_id'=>$dep,'country_id'=>$country,'region_id'=>null,'hotel_ids'=>[],'priority'=>false,'date_from'=>$w['from'],'date_to'=>$w['to'],'last'=>$last['month|'.$key]??'0000-00-00 00:00:00'];
        }
    }
    usort($c,static function(array $a,array $b):int{
        $pa=$a['criterion']==='hotel_batch'&&!empty($a['priority']);$pb=$b['criterion']==='hotel_batch'&&!empty($b['priority']); if($pa!==$pb)return $pa?-1:1;
        if($pa&&$pb){$cmp=((int)$a['coverage'])<=>((int)$b['coverage']);if($cmp!==0)return $cmp;$cmp=((int)$a['window_order'])<=>((int)$b['window_order']);if($cmp!==0)return $cmp;$cmp=strcmp((string)$a['batch_family'],(string)$b['batch_family']);if($cmp!==0)return $cmp;}
        $neverA=$a['last']==='0000-00-00 00:00:00'?0:1;$neverB=$b['last']==='0000-00-00 00:00:00'?0:1;if($neverA!==$neverB)return $neverA<=>$neverB;
        if($a['last']!==$b['last'])return strcmp($a['last'],$b['last']);$rank=['hotel_batch'=>0,'resort'=>1,'month'=>2];return $rank[$a['criterion']]<=>$rank[$b['criterion']];
    });
    return $c;
}
function matrix_pick_balanced(array $c,int $budget): array {
    $by=['hotel_batch'=>[],'resort'=>[],'month'=>[]];foreach($c as $x)$by[$x['criterion']][]=$x;$hotelSlots=min($budget,max(1,(int)ceil($budget*0.55)));$rest=$budget-$hotelSlots;$resortSlots=(int)ceil($rest/2);$monthSlots=$rest-$resortSlots;$out=[];
    foreach(array_slice($by['hotel_batch'],0,$hotelSlots) as $x)$out[]=$x;foreach(array_slice($by['resort'],0,$resortSlots) as $x)$out[]=$x;foreach(array_slice($by['month'],0,$monthSlots) as $x)$out[]=$x;
    if(count($out)<$budget){$used=[];foreach($out as $x)$used[$x['criterion'].'|'.$x['target_key']]=true;foreach($c as $x){$k=$x['criterion'].'|'.$x['target_key'];if(isset($used[$k]))continue;$out[]=$x;$used[$k]=true;if(count($out)>=$budget)break;}}
    return array_slice($out,0,$budget);
}
function matrix_attempt_start(PDO $pdo,array $t): int { $s=$pdo->prepare("INSERT INTO tour_matrix_collection_attempts (criterion,target_key,departure_id,country_id,region_id,hotel_ids_json,date_from,date_to,nights_from,nights_to,status,started_at) VALUES (:criterion,:target_key,:departure,:country,:region,:hotels,:df,:dt,5,14,'started',:started)");$s->execute(['criterion'=>$t['criterion'],'target_key'=>$t['target_key'],'departure'=>$t['departure_id'],'country'=>$t['country_id'],'region'=>$t['region_id'],'hotels'=>$t['hotel_ids']?json_encode($t['hotel_ids']):null,'df'=>$t['date_from'],'dt'=>$t['date_to'],'started'=>(new DateTimeImmutable())->format('Y-m-d H:i:s')]);return(int)$pdo->lastInsertId(); }
function matrix_attempt_finish(PDO $pdo,int $id,string $status,?int $searchId,int $rows,int $written,?string $error=null): void { $s=$pdo->prepare("UPDATE tour_matrix_collection_attempts SET status=:status,search_id=:sid,rows_received=:rows,observations_written=:written,error_text=:error,finished_at=:finished WHERE id=:id");$s->execute(['status'=>$status,'sid'=>$searchId,'rows'=>max(0,$rows),'written'=>max(0,$written),'error'=>$error?mb_substr($error,0,1000):null,'finished'=>(new DateTimeImmutable())->format('Y-m-d H:i:s'),'id'=>$id]); }

$budget=matrix_int($argv,'budget',9,1,30);$months=matrix_int($argv,'months',12,1,18);$pollAttempts=matrix_int($argv,'poll-attempts',20,1,30);$pollSeconds=matrix_int($argv,'poll-seconds',2,1,10);$pdo=v2_data_db();$targets=matrix_pick_balanced(matrix_candidates($pdo,$months),$budget);
if(!$targets){echo "MATRIX_COLLECTOR_NO_TARGETS\n";exit;}$total=0;$ok=0;$failed=0;
foreach($targets as $t){
    $search=['departureId'=>$t['departure_id'],'countryId'=>$t['country_id'],'dateFrom'=>$t['date_from'],'dateTo'=>$t['date_to'],'nightsFrom'=>5,'nightsTo'=>14,'adults'=>2,'currency'=>'RUB','onlyCharter'=>false,'onlyDirect'=>false];if($t['criterion']==='hotel_batch')$search['hotelIds']=$t['hotel_ids'];if($t['criterion']==='resort')$search['regionIds']=[$t['region_id']];
    $attempt=matrix_attempt_start($pdo,$t);$sid=null;echo "MATRIX_START criterion={$t['criterion']} priority=".(!empty($t['priority'])?'top500':'normal')." coverage=".(int)($t['coverage']??0)." key={$t['target_key']} hotels=".count($t['hotel_ids'])." from={$t['date_from']} to={$t['date_to']}\n";
    try{
        $sid=matrix_search_id(v2_data_tv_get('/tours/search',$search));if(!$sid)throw new RuntimeException('no searchId');$complete=false;for($p=0;$p<$pollAttempts;$p++){sleep($pollSeconds);if(matrix_complete(v2_data_tv_get('/tours/search/'.$sid.'/status',['operatorStatus'=>false]))){$complete=true;break;}}
        if(!$complete){matrix_attempt_finish($pdo,$attempt,'timeout',$sid,0,0,'bounded poll timeout');$failed++;continue;}$rows=matrix_fetch_results($sid);$trusted=[];foreach($rows as $h){if(!is_array($h))continue;$hc=is_array($h['country']??null)?(int)($h['country']['id']??0):0;if($hc>0&&$hc!==(int)$t['country_id'])continue;$trusted[]=$h;}
        if(!$trusted){matrix_attempt_finish($pdo,$attempt,'empty',$sid,0,0);$ok++;continue;}$r=v2_data_observe_search_results($trusted,['searchId'=>$sid,'departureId'=>$t['departure_id'],'countryId'=>$t['country_id'],'adults'=>2,'currency'=>'RUB','source'=>'scheduled_monitor','maxHotels'=>5000,'maxTours'=>50000]);$written=(int)($r['written']??0);$total+=$written;matrix_attempt_finish($pdo,$attempt,'success',$sid,count($trusted),$written);$ok++;echo "MATRIX_OK criterion={$t['criterion']} search={$sid} hotels=".count($trusted)." written={$written}\n";
    }catch(Throwable $e){matrix_attempt_finish($pdo,$attempt,'failure',$sid,0,0,$e->getMessage());$failed++;fwrite(STDERR,"MATRIX_FAIL {$t['criterion']} {$t['target_key']} ".mb_substr($e->getMessage(),0,400)."\n");}
}
echo "MATRIX_COLLECTOR_DONE targets=".count($targets)." ok={$ok} failed={$failed} written={$total}\n";if($failed===count($targets))exit(1);
