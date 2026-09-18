<?php
declare(strict_types=1);

const HMTV_OP='hotel-match-user-seen-tv-detail-saturation-1971-20260918-v3';
const HMTV_MAX_ATTEMPTS=2705;
const HMTV_CUMULATIVE_MATCH_CAP=2975;
const HMTV_SKIP_PRIOR_SCHEDULE=270;
const HMTV_BODY_LIMIT=2097152;
const HMTV_ENDPOINT='https://api.tourvisor.ru/search/api/v1';

function hmtv_rows(PDO $db,string $sql,array $params=[]): array {
    $s=$db->prepare($sql);$s->execute(array_values($params));return $s->fetchAll(PDO::FETCH_ASSOC)?:[];
}
function hmtv_positive(mixed $v,int $max=32): ?string {
    if(is_int($v)&&$v>0)$v=(string)$v;
    return is_string($v)&&preg_match('/^[1-9][0-9]{0,'.($max-1).'}$/D',$v)?$v:null;
}
function hmtv_text(mixed $v,int $max=240): string {
    if(!is_scalar($v))return '';
    $s=trim((string)$v);if($s===''||strlen($s)>$max||preg_match('/[\x00-\x1f\x7f]/',$s))return '';
    return $s;
}
function hmtv_family(mixed $name,mixed $id=null): ?string {
    $s=mb_strtolower(trim((string)$name),'UTF-8');
    $s=str_replace(['&','+','-','_',' '],'',$s);
    if(in_array($s,['anex','anextour','анекс','анекстур'],true))return'anex';
    if(str_contains($s,'библиоглобус')||str_contains($s,'biblioglobus')||str_contains($s,'biblio'))return'biblio';
    if(str_contains($s,'funsun')||str_contains($s,'funandsun'))return'funsun';
    if(str_contains($s,'интурист')||str_contains($s,'intourist'))return'intourist';
    $n=is_numeric($id)?(int)$id:0;
    $known=[13=>'anex',18=>'biblio',25=>'funsun',43=>'intourist'];
    return $known[$n]??null;
}
function hmtv_generic_product(string $name): bool {
    return preg_match('/^(?:fortuna|roulette|фортуна|рулетка)\b/ui',trim($name))===1;
}
function hmtv_safe_url(string $url): ?array {
    if($url===''||strlen($url)>2048)return null;
    $u=parse_url($url);
    if(!is_array($u)||strtolower((string)($u['scheme']??''))!=='https'||empty($u['host'])||isset($u['user'])||isset($u['pass']))return null;
    $host=strtolower((string)$u['host']);$path=(string)($u['path']??'');
    $ids=[];
    if(isset($u['query'])){
        parse_str((string)$u['query'],$q);
        foreach($q as $k=>$v){
            $lk=strtolower((string)$k);
            if(!in_array($lk,['hotellist','hotelcode','hotel_code','hotelid','hotel_id','hotelinc'],true))continue;
            $vals=is_array($v)?$v:preg_split('/\s*,\s*/',(string)$v);
            foreach($vals?:[] as $x){
                $id=hmtv_positive(trim((string)$x),12);
                if($id!==null)$ids[$id][$lk]=true;
            }
        }
    }
    if(count($ids)!==1)return ['host'=>$host,'path'=>$path,'native_id'=>null,'params'=>[]];
    $id=(string)array_key_first($ids);$params=array_keys($ids[$id]);sort($params,SORT_STRING);
    return ['host'=>$host,'path'=>$path,'native_id'=>$id,'params'=>$params];
}
function hmtv_collect_links(mixed $v,array &$out,int $depth=0,string $key=''): void {
    if($depth>8)return;
    if(is_array($v)){foreach($v as $k=>$x)hmtv_collect_links($x,$out,$depth+1,(string)$k);return;}
    if(!is_string($v))return;
    $lk=strtolower($key);
    if(!str_contains($lk,'link')&&!str_contains($lk,'url'))return;
    $safe=hmtv_safe_url(trim($v));if($safe===null)return;
    $sig=$safe['host'].'|'.$safe['path'].'|'.($safe['native_id']??'').'|'.implode(',',$safe['params']);
    $out[$sig]=$safe+['field'=>$key];
}
function hmtv_collect_hotel_ids(mixed $v,array &$out,int $depth=0,string $parent=''): void {
    if($depth>8||!is_array($v))return;
    $lp=strtolower($parent);
    if(in_array($lp,['hotel','accommodation'],true)){
        foreach(['id','hotelId','hotel_id'] as $k)if(array_key_exists($k,$v)&&($id=hmtv_positive($v[$k],12))!==null)$out[$id]=true;
    }
    foreach(['hotelId','hotel_id'] as $k)if(array_key_exists($k,$v)&&($id=hmtv_positive($v[$k],12))!==null)$out[$id]=true;
    foreach($v as $k=>$x)if(is_array($x))hmtv_collect_hotel_ids($x,$out,$depth+1,(string)$k);
}
function hmtv_operator(mixed $v,array &$names,array &$ids,int $depth=0,string $parent=''): void {
    if($depth>8||!is_array($v))return;
    if(strtolower($parent)==='operator'){
        if(($id=hmtv_positive($v['id']??null,12))!==null)$ids[$id]=true;
        foreach(['name','fullName','russianName'] as $k){$n=hmtv_text($v[$k]??'',180);if($n!=='')$names[$n]=true;}
    }
    foreach($v as $k=>$x)if(is_array($x))hmtv_operator($x,$names,$ids,$depth+1,(string)$k);
}
function hmtv_http_once(string $token,string $tourId): array {
    static $last=0.0;
    $wait=1.1-(microtime(true)-$last);if($wait>0)usleep((int)ceil($wait*1_000_000));
    $url=HMTV_ENDPOINT.'/tours/'.rawurlencode($tourId).'?currency=RUB';
    $ch=curl_init();if($ch===false)throw new RuntimeException('curl_init');
    $body='';
    try{
        curl_setopt_array($ch,[
            CURLOPT_URL=>$url,CURLOPT_HTTPGET=>true,CURLOPT_RETURNTRANSFER=>false,
            CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>45,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_MAXREDIRS=>0,
            CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_REDIR_PROTOCOLS=>CURLPROTO_HTTPS,
            CURLOPT_PROXY=>'',CURLOPT_NOPROXY=>'*',CURLOPT_USERAGENT=>'AnyTour-MATCH-TV-detail-saturation/3.0',
            CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$token,'Accept: application/json'],
            CURLOPT_WRITEFUNCTION=>static function($unused,string $chunk)use(&$body):int{
                $remain=HMTV_BODY_LIMIT+1-strlen($body);$body.=substr($chunk,0,max(0,$remain));
                return strlen($body)>HMTV_BODY_LIMIT?0:strlen($chunk);
            },
        ]);
        $ok=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$errno=curl_errno($ch);
        $last=microtime(true);
        if($errno!==0||$ok===false)return ['status'=>$status?:0,'body'=>'','transport_error'=>true];
        if(strlen($body)>HMTV_BODY_LIMIT)return ['status'=>$status,'body'=>'','oversize'=>true];
        return ['status'=>$status,'body'=>$body];
    }finally{curl_close($ch);}
}
function hmtv_quota_attempt(string $home): int {
    $dir=rtrim($home,'/').'/.anytour-match/provider-quotas';
    if(!is_dir($dir)&&!mkdir($dir,0700,true)&&!is_dir($dir))throw new RuntimeException('quota_dir');
    $day=gmdate('Y-m-d');$p="$dir/tourvisor-test-$day.json";$f=fopen($p,'c+');if(!$f)throw new RuntimeException('quota_open');
    try{
        if(!flock($f,LOCK_EX))throw new RuntimeException('quota_lock');
        $raw=stream_get_contents($f);$j=$raw!==''?json_decode($raw,true):[];
        $new=(int)($j['match_new_attempts']??0);
        if($new>=HMTV_CUMULATIVE_MATCH_CAP)throw new RuntimeException('daily_match_attempt_cap');
        $new++;$j=['day'=>$day,'match_new_attempts'=>$new,'known_prior_attempt_floor'=>25,'owner_daily_limit'=>3000,'cumulative_match_cap'=>HMTV_CUMULATIVE_MATCH_CAP,'operation_cap'=>HMTV_MAX_ATTEMPTS,'updated_at'=>gmdate('c')];
        ftruncate($f,0);rewind($f);fwrite($f,json_encode($j,JSON_UNESCAPED_SLASHES));fflush($f);if(function_exists('fsync'))fsync($f);
        return$new;
    }finally{flock($f,LOCK_UN);fclose($f);}
}
if(in_array('--self-test',$argv??[],true)){
    $x=hmtv_safe_url('https://agent.anextour.ru/search/tour?HOTELLIST=5844&x=1');
    if(($x['native_id']??null)!=='5844')throw new RuntimeException('url');
    $links=[];hmtv_collect_links(['operatorLink'=>'https://online.anextour.ru/x?hotelCode=4158'],$links);
    if(count($links)!==1||array_values($links)[0]['native_id']!=='4158')throw new RuntimeException('walk');
    if(HMTV_MAX_ATTEMPTS!==2705||HMTV_CUMULATIVE_MATCH_CAP!==2975||HMTV_SKIP_PRIOR_SCHEDULE!==270)throw new RuntimeException('quota_contract');echo"TV_DETAIL_SATURATION_V3_SELFTEST_OK\n";exit;
}

$root=realpath((string)getenv('ANYTOUR_ROOT'));$opdir=(string)getenv('MATCH_OPERATION_DIR');$token=rtrim((string)fgets(STDIN),"\r\n");
if(!$root||$opdir===''||$token===''||strlen($token)>8192)throw new RuntimeException('runtime');
require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
require_once $opdir.'/payload/anex-search-mapping-registry.php';
$db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
try{
    $registry=AnyTourAnexSearchMappingRegistry::fromPdo($db);
    $anexTargets=[];foreach(hmtv_rows($db,'SELECT anex_hotel_id FROM anex_hotel_search_mappings UNION SELECT anex_hotel_id FROM anex_hotel_decisions')as$r){$t=$registry->resolve('anex_online',(string)$r['anex_hotel_id'],'preview');if(is_int($t)&&$t>0)$anexTargets[$t]=true;}
    $andrTargets=[];foreach(hmtv_rows($db,"SELECT DISTINCT local_hotel_id FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IS NOT NULL")as$r)$andrTargets[(int)$r['local_hotel_id']]=true;
    $hotels=[];foreach(hmtv_rows($db,'SELECT id,name,country_id,country_name,region_name,subregion_name,is_active FROM catalog_hotels WHERE is_active=1')as$r)$hotels[(int)$r['id']]=$r;
    $obs=hmtv_rows($db,"SELECT hotel_id,tour_id,operator_id,COUNT(*) obs,MAX(observed_at) last_seen FROM tour_price_observations WHERE source='user_search' AND tour_id IS NOT NULL AND tour_id<>'' GROUP BY hotel_id,tour_id,operator_id");
    $byHotel=[];$totals=[];
    foreach($obs as$r){
        $lid=(int)$r['hotel_id'];if(!isset($hotels[$lid])||isset($anexTargets[$lid])||isset($andrTargets[$lid])||hmtv_generic_product((string)$hotels[$lid]['name']))continue;
        $tid=hmtv_text($r['tour_id']??'',220);if($tid==='')continue;$oid=is_numeric($r['operator_id']??null)?(int)$r['operator_id']:null;
        $row=['local_hotel_id'=>$lid,'tour_id'=>$tid,'operator_id'=>$oid,'obs'=>(int)$r['obs'],'last_seen'=>(string)$r['last_seen'],'hotel_name'=>(string)$hotels[$lid]['name'],'country_id'=>(int)$hotels[$lid]['country_id']];
        $row['common4_priority']=hmtv_family('', $oid)!==null?1:0;$byHotel[$lid][]=$row;$totals[$lid]=($totals[$lid]??0)+(int)$r['obs'];
    }
    $db->rollBack();

    foreach($byHotel as&$rows)usort($rows,fn($a,$b)=>$b['common4_priority']<=>$a['common4_priority']?:$b['obs']<=>$a['obs']?:strcmp($b['last_seen'],$a['last_seen']));unset($rows);
    $hotelIds=array_keys($byHotel);usort($hotelIds,fn($a,$b)=>($totals[$b]??0)<=>($totals[$a]??0)?:$a<=>$b);
    // Reconstruct the deterministic v2 prefix, then continue beyond its 270 attempted seeds.
    // CURRENT DB may have grown since v2, so this is a conservative no-replay approximation;
    // selected v3 tour IDs are also globally deduplicated.
    $rawSchedule=[];$round=0;$rawLimit=HMTV_SKIP_PRIOR_SCHEDULE+HMTV_MAX_ATTEMPTS+1000;
    while(count($rawSchedule)<$rawLimit){
        $added=0;
        foreach($hotelIds as$lid){
            if(isset($byHotel[$lid][$round])){$rawSchedule[]=$byHotel[$lid][$round];$added++;if(count($rawSchedule)>=$rawLimit)break;}
        }
        if($added===0)break;$round++;
    }
    $priorApprox=array_slice($rawSchedule,0,HMTV_SKIP_PRIOR_SCHEDULE);$priorTourIds=[];
    foreach($priorApprox as$seed)$priorTourIds[(string)$seed['tour_id']]=true;
    $schedule=[];$selectedTourIds=[];$skippedPrior=0;$skippedDuplicate=0;
    foreach(array_slice($rawSchedule,HMTV_SKIP_PRIOR_SCHEDULE) as$seed){
        $tid=(string)$seed['tour_id'];
        if(isset($priorTourIds[$tid])){$skippedPrior++;continue;}
        if(isset($selectedTourIds[$tid])){$skippedDuplicate++;continue;}
        $selectedTourIds[$tid]=true;$schedule[]=$seed;
        if(count($schedule)>=HMTV_MAX_ATTEMPTS)break;
    }

    $attempted=0;$http200=0;$notFound=0;$rateLimited=false;$transport=0;$bindingConflict=0;$bindingMissing=0;$direct=[];$linksFound=0;$familyCounts=[];$statusCounts=[];
    foreach($schedule as$seed){
        hmtv_quota_attempt((string)getenv('HOME'));
        $reply=hmtv_http_once($token,$seed['tour_id']);$attempted++;$status=(int)($reply['status']??0);$statusCounts[(string)$status]=($statusCounts[(string)$status]??0)+1;
        if($status===429){$rateLimited=true;break;}
        if($status===404){$notFound++;continue;}
        if($status<200||$status>=300||!empty($reply['transport_error'])){$transport++;continue;}
        try{$payload=json_decode((string)$reply['body'],true,64,JSON_THROW_ON_ERROR);}catch(Throwable){$transport++;continue;}
        if(!is_array($payload)){$transport++;continue;}$http200++;
        $hotelIdsDetail=[];hmtv_collect_hotel_ids($payload,$hotelIdsDetail);
        if($hotelIdsDetail&&!isset($hotelIdsDetail[(string)$seed['local_hotel_id']])){$bindingConflict++;continue;}
        if(!$hotelIdsDetail){$bindingMissing++;continue;}
        $opNames=[];$opIds=[];hmtv_operator($payload,$opNames,$opIds);
        $family=null;foreach(array_keys($opNames)as$n){$family=hmtv_family($n,array_key_first($opIds));if($family)break;}
        if(!$family)$family=hmtv_family('',array_key_first($opIds)?:$seed['operator_id']);
        $family=$family??'other';$familyCounts[$family]=($familyCounts[$family]??0)+1;
        $links=[];hmtv_collect_links($payload,$links);
        foreach($links as$link){
            if(($link['native_id']??null)===null)continue;$linksFound++;
            $direct[]=[
                'local_hotel_id'=>$seed['local_hotel_id'],'local_hotel_name'=>$seed['hotel_name'],'tour_id'=>$seed['tour_id'],
                'saved_operator_id'=>$seed['operator_id'],'detail_operator_ids'=>array_values(array_keys($opIds)),
                'detail_operator_names'=>array_values(array_keys($opNames)),'operator_family'=>$family,
                'native_hotel_id'=>$link['native_id'],'native_host'=>$link['host'],'native_path'=>$link['path'],
                'native_params'=>$link['params'],'source_field'=>$link['field'],'user_observation_weight'=>$totals[$seed['local_hotel_id']]??0,
            ];
        }
    }
    $pairIndex=[];$conflicts=[];
    foreach($direct as$r){$k=$r['operator_family'].'|'.$r['local_hotel_id'];$pairIndex[$k][$r['native_hotel_id']]=true;}
    $unique=[];foreach($pairIndex as$k=>$ids){if(count($ids)===1){[$fam,$local]=explode('|',$k,2);$id=(string)array_key_first($ids);$unique[]=['operator_family'=>$fam,'local_hotel_id'=>(int)$local,'native_hotel_id'=>$id];}else$conflicts[]=['pair_key'=>$k,'native_ids'=>array_keys($ids)];}
    ksort($statusCounts);ksort($familyCounts);
    echo json_encode([
        'operation'=>HMTV_OP,'state'=>$rateLimited?'completed_partial_rate_limited':'completed_read_only',
        'frontier_hotels_with_saved_tours'=>count($byHotel),'saved_seed_rows'=>array_sum(array_map('count',$byHotel)),
        'reconstructed_prior_seed_count'=>count($priorApprox),'reconstructed_prior_unique_tour_ids'=>count($priorTourIds),'skipped_prior_tour_ids'=>$skippedPrior,'skipped_duplicate_tour_ids'=>$skippedDuplicate,'scheduled_attempts'=>count($schedule),'http_attempts'=>$attempted,'http_200'=>$http200,'http_404'=>$notFound,'transport_or_shape_failures'=>$transport,
        'rate_limited'=>$rateLimited,'binding_conflict'=>$bindingConflict,'binding_missing'=>$bindingMissing,
        'status_counts'=>$statusCounts,'detail_family_counts'=>$familyCounts,'direct_link_rows'=>count($direct),'direct_native_links'=>$linksFound,
        'unique_local_operator_native_pairs'=>count($unique),'native_conflict_groups'=>count($conflicts),
        'unique_pairs'=>$unique,'direct_evidence'=>$direct,'conflicts'=>$conflicts,
        'database_writes'=>0,'mapping_writes'=>0,'andromeda_calls'=>0,'anex_calls'=>0,'no_replay'=>true
    ],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
}catch(Throwable$e){if($db->inTransaction())$db->rollBack();throw$e;}
