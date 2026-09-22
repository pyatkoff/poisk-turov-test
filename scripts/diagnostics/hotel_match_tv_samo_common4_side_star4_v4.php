<?php
declare(strict_types=1);

require_once __DIR__.'/hotel_match_operator_fingerprint_room_evidence_v1.php';

const HMC_OP = 'hotel-match-tv-samo-common4-side-star4-1971-20260922-v4';
const HMC_PREVIOUS_OP = 'hotel-match-tv-samo-common4-weekly-1971-20260922-v2';
const HMC_DEPARTURE = 1;
const HMC_TV_COUNTRY = 4;
const HMC_SAMO_STATE = 5;
const HMC_NIGHTS = 7;
const HMC_ADULTS = 2;
const HMC_CHILDREN = 0;
const HMC_DATE_FROM = '2026-10-05';
const HMC_DATE_TO = '2026-10-11';
const HMC_MAX_TV_CALLS = 140;
const HMC_MAX_CONTINUE = 100;
const HMC_MAX_SAMO_PAGES = 120;
const HMC_MAX_SAMO_CALLS = 180;
const HMC_MAX_DETAIL_CALLS = 40;
const HMC_MAX_CANDIDATE_EVIDENCE_ROWS = 300;

function hmc_json(mixed $v): string {
    return json_encode($v, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
}
function hmc_write(string $path,array $v): string {
    $raw=hmc_json($v)."\n";
    $f=@fopen($path,'x+b');
    if(!$f)throw new RuntimeException('durable_create');
    try{
        if(fwrite($f,$raw)!==strlen($raw)||!fflush($f))throw new RuntimeException('durable_write');
        if(function_exists('fsync')&&!fsync($f))throw new RuntimeException('durable_sync');
        rewind($f);
        if(stream_get_contents($f)!==$raw)throw new RuntimeException('durable_readback');
    }finally{fclose($f);}
    return hash('sha256',$raw);
}
$GLOBALS['HMC_PRIVATE_EVIDENCE_DIR']=null;
$GLOBALS['HMC_PRIVATE_EVIDENCE_HASHES']=[];
$GLOBALS['HMC_TV_TARIFF_UNITS']=0;
$GLOBALS['HMC_SAMO_PHYSICAL_CALLS']=0;

function hmc_private_evidence_init(string $opDir): void {
    $dir=$opDir.'/evidence-private';
    if(!is_dir($dir)&&!mkdir($dir,0700,true)&&!is_dir($dir))throw new RuntimeException('evidence_dir');
    @chmod($dir,0700);
    $GLOBALS['HMC_PRIVATE_EVIDENCE_DIR']=$dir;
}
function hmc_private_evidence_record(string $source,array $meta,array $payload): string {
    $dir=$GLOBALS['HMC_PRIVATE_EVIDENCE_DIR']??null;
    if(!is_string($dir)||$dir==='')throw new RuntimeException('evidence_not_initialized');
    $seq=count($GLOBALS['HMC_PRIVATE_EVIDENCE_HASHES'])+1;
    $safe=preg_replace('/[^a-z0-9_-]+/i','-',strtolower($source));
    $raw=hmc_json(['schema'=>1,'source'=>$source,'sequence'=>$seq,'captured_at'=>gmdate('c'),'meta'=>$meta,'payload'=>$payload])."\n";
    $path=$dir.'/'.sprintf('%04d-%s.json',$seq,$safe);
    $f=@fopen($path,'x+b');if(!$f)throw new RuntimeException('evidence_create');
    try{
        if(fwrite($f,$raw)!==strlen($raw)||!fflush($f))throw new RuntimeException('evidence_write');
        if(function_exists('fsync')&&!fsync($f))throw new RuntimeException('evidence_sync');
        rewind($f);if(stream_get_contents($f)!==$raw)throw new RuntimeException('evidence_readback');
    }finally{fclose($f);}
    @chmod($path,0600);
    $sha=hash('sha256',$raw);
    $GLOBALS['HMC_PRIVATE_EVIDENCE_HASHES'][]=$sha;
    return $sha;
}
function hmc_private_evidence_record_raw(string $source,array $meta,int $status,string $body): string {
    $dir=$GLOBALS['HMC_PRIVATE_EVIDENCE_DIR']??null;
    if(!is_string($dir)||$dir==='')throw new RuntimeException('evidence_not_initialized');
    $seq=count($GLOBALS['HMC_PRIVATE_EVIDENCE_HASHES'])+1;
    $safe=preg_replace('/[^a-z0-9_-]+/i','-',strtolower($source));
    $sha=hash('sha256',$body);
    $base=$dir.'/'.sprintf('%04d-%s',$seq,$safe);
    $f=@fopen($base.'.bin','x+b');if(!$f)throw new RuntimeException('evidence_raw_create');
    try{
        if(fwrite($f,$body)!==strlen($body)||!fflush($f))throw new RuntimeException('evidence_raw_write');
        if(function_exists('fsync')&&!fsync($f))throw new RuntimeException('evidence_raw_sync');
    }finally{fclose($f);}
    @chmod($base.'.bin',0600);
    $metaRaw=hmc_json(['schema'=>1,'source'=>$source,'sequence'=>$seq,'captured_at'=>gmdate('c'),'http_status'=>$status,
        'raw_sha256'=>$sha,'bytes'=>strlen($body),'meta'=>$meta])."\n";
    $mf=@fopen($base.'.meta.json','x+b');if(!$mf)throw new RuntimeException('evidence_meta_create');
    try{
        if(fwrite($mf,$metaRaw)!==strlen($metaRaw)||!fflush($mf))throw new RuntimeException('evidence_meta_write');
        if(function_exists('fsync')&&!fsync($mf))throw new RuntimeException('evidence_meta_sync');
    }finally{fclose($mf);}
    @chmod($base.'.meta.json',0600);
    $GLOBALS['HMC_PRIVATE_EVIDENCE_HASHES'][]=$sha;
    return $sha;
}

function hmc_week_dates(): array {
    $tz=new DateTimeZone('UTC');
    $from=DateTimeImmutable::createFromFormat('!Y-m-d',HMC_DATE_FROM,$tz);
    $to=DateTimeImmutable::createFromFormat('!Y-m-d',HMC_DATE_TO,$tz);
    if(!$from||!$to)throw new RuntimeException('date_window_parse');
    $out=[];
    for($d=$from;$d<=$to;$d=$d->modify('+1 day'))$out[]=$d->format('Y-m-d');
    if(count($out)!==7)throw new RuntimeException('date_window_not_week');
    return $out;
}

function hmc_text(mixed $v,int $max=512): string {
    return is_scalar($v)?mb_substr(trim((string)$v),0,$max,'UTF-8'):'';
}
function hmc_id(mixed $v): ?int {
    if(is_array($v))$v=$v['id']??null;
    $n=filter_var($v,FILTER_VALIDATE_INT);
    return $n!==false&&(int)$n>0?(int)$n:null;
}
function hmc_label(mixed $v,int $max=300): string {
    if(is_scalar($v)){
        $s=hmc_text($v,$max);
        return preg_match('/^\d+$/D',$s)?'':$s;
    }
    if(!is_array($v))return '';
    foreach(['fullName','name','label','title','value'] as $k){
        if(isset($v[$k])&&is_scalar($v[$k])){
            $s=hmc_text($v[$k],$max);
            if($s!==''&&!preg_match('/^\d+$/D',$s))return $s;
        }
    }
    return '';
}
function hmc_norm(string $v): string {
    $v=mb_strtolower(trim($v),'UTF-8');
    $v=str_replace(['&','+'],' ',$v);
    $v=preg_replace('/[^\p{L}\p{N}]+/u',' ',$v)??$v;
    return trim(preg_replace('/\s+/u',' ',$v)??$v);
}
function hmc_generic(string $v): bool {
    return preg_match('/^(?:fortuna|roulette|фортуна|рулетка)(?:\s|$)/u',hmc_norm($v))===1;
}
function hmc_family(string $v): ?string {
    $f=hmf_operator_family($v);
    if($f!==null)return $f;
    $n=hmc_norm($v);
    $aliases=[
        'anex'=>['anex tour','anex','анекс тур','анекс'],
        'biblio'=>['biblio globus','biblioglobus','библио глобус'],
        'funsun'=>['fun sun','funsun','фан сан'],
        'intourist'=>['intourist','интурист'],
    ];
    foreach($aliases as $family=>$values)if(in_array($n,$values,true))return $family;
    return null;
}
function hmc_rows(array $p,array $keys=['items','results','hotels']): array {
    if(array_is_list($p))return $p;
    foreach($keys as $k)if(is_array($p[$k]??null))return $p[$k];
    return [];
}
function hmc_operator_map(array $rows): array {
    $tmp=[];
    foreach($rows as $r){
        if(!is_array($r))continue;
        $id=hmc_id($r['id']??null);$name=hmc_text($r['name']??$r['lName']??'',200);
        $family=hmc_family($name);
        if(!$id||$name===''||$family===null)continue;
        $tmp[$family][$id]=['id'=>$id,'name'=>$name,'family'=>$family];
    }
    $out=['unique'=>[],'ambiguous'=>[]];
    foreach(['anex','biblio','funsun','intourist'] as $family){
        $hits=array_values($tmp[$family]??[]);
        if(count($hits)===1)$out['unique'][$family]=$hits[0];
        elseif(count($hits)>1)$out['ambiguous'][$family]=$hits;
    }
    return $out;
}
function hmc_common_operators(array $tvRows,array $samoRows): array {
    $tv=hmc_operator_map($tvRows);$sa=hmc_operator_map($samoRows);
    $common=[];
    foreach(['anex','biblio','funsun','intourist'] as $family){
        if(!isset($tv['unique'][$family],$sa['unique'][$family]))continue;
        $common[$family]=['family'=>$family,'tv'=>$tv['unique'][$family],'samo'=>$sa['unique'][$family]];
    }
    return ['common'=>$common,'tv_ambiguous'=>$tv['ambiguous'],'samo_ambiguous'=>$sa['ambiguous'],
        'missing'=>array_values(array_diff(['anex','biblio','funsun','intourist'],array_keys($common)))];
}
function hmc_dates_walk(mixed $v,array &$out): void {
    if(is_array($v)){foreach($v as $x)hmc_dates_walk($x,$out);return;}
    if(!is_scalar($v))return;
    $s=trim((string)$v);
    foreach(['!Y-m-d','!Ymd','!d.m.Y'] as $fmt){
        $d=DateTimeImmutable::createFromFormat($fmt,$s,new DateTimeZone('UTC'));
        $e=DateTimeImmutable::getLastErrors();
        if($d&&($e===false||(($e['warning_count']??0)===0&&($e['error_count']??0)===0))){
            $out[$d->format('Y-m-d')]=true;return;
        }
    }
}
function hmc_dates(array $v): array {
    $out=[];hmc_dates_walk($v,$out);$dates=array_keys($out);sort($dates,SORT_STRING);return $dates;
}
function hmc_date(mixed $v,string $fallback): string {
    $out=[];hmc_dates_walk($v,$out);
    $dates=array_keys($out);sort($dates,SORT_STRING);
    return $dates[0]??$fallback;
}
function hmc_dict(array $rows,array $aliases): array {
    $want=[];foreach($aliases as $a)$want[hmc_norm($a)]=true;
    $found=[];
    foreach($rows as $r){
        if(!is_array($r))continue;$id=hmc_id($r['id']??null);$name=hmc_text($r['name']??$r['lName']??'');
        if($id&&$name!==''&&isset($want[hmc_norm($name)]))$found[$id]=['id'=>$id,'name'=>$name];
    }
    return array_values($found);
}
function hmc_star_id(array $rows,int $star): ?int {
    $hits=[];
    foreach($rows as $r){
        if(!is_array($r))continue;
        $id=hmc_id($r['id']??null);$name=hmc_norm(hmc_text($r['name']??$r['lName']??''));
        if(!$id||$name==='')continue;
        if(preg_match('/(?:^|\s)'.preg_quote((string)$star,'/').'\s*(?:\*|star|stars|звезд|звезды|звезда)?(?:\s|$)/u',$name))$hits[$id]=$id;
    }
    return count($hits)===1?array_values($hits)[0]:null;
}
function hmc_url(mixed $v): ?string {
    $s=hmc_text($v,2048);if($s==='')return null;
    if(str_starts_with($s,'//'))$s='https:'.$s;
    $p=parse_url($s);
    if(!is_array($p)||!in_array(strtolower((string)($p['scheme']??'')),['https','http'],true)
        ||empty($p['host'])||isset($p['user'])||isset($p['pass'])||strlen($s)>2048)return null;
    if(isset($p['query'])){
        parse_str((string)$p['query'],$q);
        foreach(array_keys($q) as $k)if(preg_match('/token|auth|pass|secret|session|sid|cookie|signature|api[_-]?key/i',(string)$k))return null;
    }
    return $s;
}
function hmc_urls_walk(mixed $v,array &$out,string $key='',int $depth=0): void {
    if($depth>5)return;
    if(is_array($v)){foreach($v as $k=>$x)hmc_urls_walk($x,$out,(string)$k,$depth+1);return;}
    if(!is_string($v)||!preg_match('/(?:url|link)/i',$key))return;
    $u=hmc_url($v);if($u!==null)$out[$u]=true;
}
function hmc_urls(array $v): array {
    $out=[];hmc_urls_walk($v,$out);$urls=array_keys($out);sort($urls,SORT_STRING);return array_slice($urls,0,20);
}
function hmc_numeric(array $r,array $keys): ?float {
    foreach($keys as $k)if(isset($r[$k])&&is_numeric($r[$k])&&(float)$r[$k]>0)return (float)$r[$k];
    return null;
}
function hmc_request_count(array $p): ?int {
    foreach(['requestCount','requestsCount','request_count'] as $k)if(array_key_exists($k,$p)&&is_numeric($p[$k]))return max(0,(int)$p[$k]);
    foreach($p as $v)if(is_array($v)){ $n=hmc_request_count($v);if($n!==null)return $n; }
    return null;
}
function hmc_search_id(array $p): ?int {
    foreach(['searchId','id'] as $k){$n=hmc_id($p[$k]??null);if($n)return $n;}
    foreach($p as $v)if(is_array($v)){ $n=hmc_search_id($v);if($n)return $n; }
    return null;
}
function hmc_complete(array $p): bool {
    if((int)($p['progress']??0)>=100)return true;
    if(in_array(mb_strtolower(trim((string)($p['status']??'')),'UTF-8'),['complete','completed','done','ready'],true))return true;
    foreach($p as $v)if(is_array($v)&&hmc_complete($v))return true;
    return false;
}
function hmc_tv_result_rows(array $p): array {
    if(array_is_list($p))return $p;
    foreach(['hotels','results','items'] as $k)if(is_array($p[$k]??null))return $p[$k];
    return [];
}
function hmc_tv_signatures(array $rows): array {
    $hotels=[];$tours=[];
    foreach($rows as $h){
        if(!is_array($h))continue;$hid=hmc_id($h['id']??null);if(!$hid)continue;$hotels[$hid]=true;
        foreach((array)($h['tours']??[]) as $t)if(is_array($t)){
            $tid=hmc_text($t['id']??$t['tourId']??'',220);
            $key=$tid!==''?$hid.'|'.$tid:$hid.'|sha:'.hash('sha256',hmc_json($t));
            $tours[$key]=true;
        }
    }
    return ['hotels'=>count($hotels),'tours'=>count($tours)];
}
function hmc_tv_merge_rows(array $acc,array $rows): array {
    $by=[];
    foreach($acc as $h)if(is_array($h)&&($id=hmc_id($h['id']??null)))$by[$id]=$h;
    foreach($rows as $h){
        if(!is_array($h)||!($id=hmc_id($h['id']??null)))continue;
        if(!isset($by[$id])){$by[$id]=$h;continue;}
        $base=$by[$id];$seen=[];$merged=[];
        foreach(array_merge((array)($base['tours']??[]),(array)($h['tours']??[])) as $t){
            if(!is_array($t))continue;$tid=hmc_text($t['id']??$t['tourId']??'',220);
            $key=$tid!==''?'id:'.$tid:'sha:'.hash('sha256',hmc_json($t));
            if(isset($seen[$key]))continue;$seen[$key]=true;$merged[]=$t;
        }
        foreach($h as $k=>$v)if($k!=='tours'&&(!array_key_exists($k,$base)||$base[$k]===null||$base[$k]===''))$base[$k]=$v;
        $base['tours']=$merged;$by[$id]=$base;
    }
    ksort($by,SORT_NUMERIC);return array_values($by);
}
function hmc_tv_call(string $path,array $params,array &$counter): array {
    if(++$counter['calls']>HMC_MAX_TV_CALLS)throw new RuntimeException('tv_call_budget');
    $tariff=$path==='/tours/search'||str_ends_with($path,'/continue');
    if($tariff)++$GLOBALS['HMC_TV_TARIFF_UNITS'];
    static $lastStarted=0.0;
    $wait=.25-(microtime(true)-$lastStarted);if($wait>0)usleep((int)ceil($wait*1000000));
    $token=v2_data_tourvisor_token();if($token==='')throw new RuntimeException('tv_token');
    $url='https://api.tourvisor.ru/search/api/v1'.$path;
    $query=v2_data_query_string($params);if($query!=='')$url.='?'.$query;
    $ch=curl_init($url);if($ch===false)throw new RuntimeException('tv_curl');
    $lastStarted=microtime(true);
    try{
        if(!curl_setopt_array($ch,[
            CURLOPT_RETURNTRANSFER=>true,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_MAXREDIRS=>0,
            CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>65,
            CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$token,'Accept: application/json'],
        ]))throw new RuntimeException('tv_curl_options');
        $body=curl_exec($ch);$errno=curl_errno($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);
        if($body===false)$body='';
        hmc_private_evidence_record_raw('tourvisor',['path'=>$path,'params'=>$params,'tariff_unit'=>$tariff],$status,(string)$body);
        if($errno!==0)throw new RuntimeException('tv_network');
        if($status<200||$status>=300)throw new RuntimeException('tv_http_'.$status);
        $reply=json_decode((string)$body,true,64,JSON_THROW_ON_ERROR);
        if(!is_array($reply))throw new RuntimeException('tv_json_shape');
        return $reply;
    }finally{curl_close($ch);}
}
function hmc_tv_wait(int $sid,array &$counter): void {
    for($i=0;$i<45;$i++){
        sleep(2);$s=hmc_tv_call('/tours/search/'.$sid.'/status',['operatorStatus'=>false],$counter);
        if(hmc_complete($s))return;
    }
    throw new RuntimeException('tv_search_timeout');
}
function hmc_tv_fetch(int $sid,array &$counter): array {
    $last=null;
    foreach([10000,5000,2000,1000,500,100] as $limit){
        try{return hmc_tv_result_rows(hmc_tv_call('/tours/search/'.$sid,['limit'=>$limit],$counter));}
        catch(Throwable $e){$last=$e;}
    }
    throw new RuntimeException('tv_results_failed',0,$last);
}
function hmc_tv_drain(array $params,array &$counter): array {
    $start=hmc_tv_call('/tours/search',$params,$counter);$sid=hmc_search_id($start);
    if(!$sid)throw new RuntimeException('tv_search_id');
    hmc_tv_wait($sid,$counter);
    $union=hmc_tv_merge_rows([],hmc_tv_fetch($sid,$counter));$sig=hmc_tv_signatures($union);
    $rounds=[['round'=>0,'request_count'=>null,'hotels'=>$sig['hotels'],'tours'=>$sig['tours'],
        'added_hotels'=>$sig['hotels'],'added_tours'=>$sig['tours']]];
    for($round=1;$round<=HMC_MAX_CONTINUE;$round++){
        $before=$sig;
        $cont=hmc_tv_call('/tours/search/'.$sid.'/continue',[],$counter);
        $requests=hmc_request_count($cont);if($requests===null)throw new RuntimeException('tv_continue_shape');
        if($requests>0)hmc_tv_wait($sid,$counter);
        $union=hmc_tv_merge_rows($union,hmc_tv_fetch($sid,$counter));$sig=hmc_tv_signatures($union);
        $addedHotels=max(0,$sig['hotels']-$before['hotels']);$addedTours=max(0,$sig['tours']-$before['tours']);
        $rounds[]=['round'=>$round,'request_count'=>$requests,'hotels'=>$sig['hotels'],'tours'=>$sig['tours'],
            'added_hotels'=>$addedHotels,'added_tours'=>$addedTours];
        if($requests===0)
            return ['search_id'=>$sid,'rows'=>$union,'rounds'=>$rounds,'fully_drained'=>true,'exhaustion'=>'request_count_zero'];
    }
    throw new RuntimeException('tv_continue_cap');
}
function hmc_tv_offer_rows(array $hotelRows,string $date,array $common): array {
    $idMap=[];
    foreach($common as $family=>$pair)$idMap[(int)$pair['tv']['id']]=['family'=>$family,'name'=>$pair['tv']['name']];
    $out=[];
    foreach($hotelRows as $hotel){
        if(!is_array($hotel))continue;$hid=hmc_id($hotel['id']??null);$hotelName=hmc_text($hotel['name']??'',300);
        if(!$hid||$hotelName===''||hmc_generic($hotelName))continue;
        $hotelUrls=hmc_urls($hotel);
        foreach((array)($hotel['tours']??[]) as $tour){
            if(!is_array($tour))continue;$op=hmc_id($tour['operator']??null);if(!$op||!isset($idMap[$op]))continue;
            $room=hmc_label($tour['roomType']??$tour['roomName']??'');
            $meal=hmc_label($tour['mealName']??$tour['meal']??'');
            $urls=array_values(array_unique(array_merge($hotelUrls,hmc_urls($tour))));
            $out[]=[
                'hotel_id'=>(string)$hid,'hotel_name'=>$hotelName,'operator_name'=>$idMap[$op]['name'],
                'operator_family'=>$idMap[$op]['family'],'operator_id'=>$op,
                'date'=>hmc_date($tour['date']??$tour['checkin']??$date,$date),
                'nights'=>hmc_id($tour['nights']??null)??HMC_NIGHTS,'adults'=>HMC_ADULTS,'children'=>HMC_CHILDREN,
                'room_raw'=>$room,'meal_raw'=>$meal,
                'price'=>hmc_numeric($tour,['price','priceRub','priceRUB','amount','cost']),
                'fuel_charge'=>hmc_numeric($tour,['fuelCharge','fuel','fuelSurcharge']),
                'currency'=>hmc_text($tour['currency']??'RUB',12)?:'RUB',
                'tour_id'=>hmc_text($tour['id']??$tour['tourId']??'',220)?:null,
                'hotel_url'=>$urls[0]??null,'operator_link'=>$urls[1]??null,
                'native_anex_hotel_id'=>null,
            ];
        }
    }
    return $out;
}
function hmc_samo_offer_row(array $r,string $date,array $commonById): ?array {
    $operator=hmc_id($r['operatorKey']??null);if(!$operator||!isset($commonById[$operator]))return null;
    $family=$commonById[$operator]['family'];$operatorName=hmc_text($r['operator']??$commonById[$operator]['name'],200);
    $hotelId=hmc_text($r['hotelKey']??'',80);$hotelName=hmc_text($r['hotel']??'',300);
    if($hotelId===''||$hotelName===''||hmc_generic($hotelName))return null;
    $native=null;
    if($family==='anex'&&is_array($r['original']??null)){
        $raw=hmc_text($r['original']['hotelKey']??'',80);
        if($raw!==''&&preg_match('/^[A-Za-z0-9_.-]{1,80}$/D',$raw))$native=$raw;
    }
    $urls=hmc_urls($r);
    return [
        'hotel_id'=>$hotelId,'hotel_name'=>$hotelName,'operator_name'=>$operatorName,
        'operator_family'=>$family,'operator_id'=>$operator,
        'date'=>hmc_date($r['checkIn']??$date,$date),
        'nights'=>hmc_id($r['nights']??null)??HMC_NIGHTS,
        'adults'=>hmc_id($r['adult']??null)??HMC_ADULTS,
        'children'=>isset($r['child'])&&is_numeric($r['child'])?max(0,(int)$r['child']):HMC_CHILDREN,
        'room_raw'=>hmc_text($r['room']??'',300),'meal_raw'=>hmc_text($r['meal']??'',200),
        'price'=>hmc_numeric($r,['price','priceRub','priceRUB','amount','cost']),
        'fuel_charge'=>hmc_numeric($r,['fuelCharge','fuel','fuelSurcharge','surcharge']),
        'currency'=>hmc_text($r['currency']??'RUB',12)?:'RUB',
        'offer_id'=>hmc_text($r['id']??'',220)?:null,
        'hotel_url'=>$urls[0]??null,'operator_link'=>$urls[1]??null,
        'native_anex_hotel_id'=>$native,'original_hotel_key'=>$native,
    ];
}
function hmc_detail_evidence(array $detail): array {
    $urls=hmc_urls($detail);$refs=[];
    foreach($urls as $url){
        $p=parse_url($url);if(!is_array($p))continue;
        parse_str((string)($p['query']??''),$q);
        foreach($q as $k=>$v){
            $key=strtolower((string)$k);
            if(!in_array($key,['hotel','hotels','hotellist','hotelid','hotel_id','hotelcode'],true))continue;
            foreach((array)$v as $part)foreach(preg_split('/[,;]+/',(string)$part)?:[] as $x){
                $x=trim($x);if(preg_match('/^[1-9][0-9]{0,15}$/D',$x))$refs['id:'.$x]=$x;
            }
        }
    }
    return ['urls'=>$urls,'native_hotel_refs'=>array_values($refs)];
}
function hmc_enrich_tv_anex(array $tvOffers,array $initial,array &$counter,int &$detailCalls): array {
    $candidateHotels=[];
    foreach($initial['hotel_candidates']??[] as $candidate){
        if(!in_array('anex',$candidate['operator_overlap']['operators']??[],true))continue;
        $candidateHotels[(string)$candidate['tv_hotel_id']]=true;
    }
    foreach(array_keys($candidateHotels) as $hotelId){
        if($detailCalls>=HMC_MAX_DETAIL_CALLS)break;
        $index=null;$tourId=null;
        foreach($tvOffers as $i=>$row){
            if((string)$row['hotel_id']!==$hotelId||($row['operator_family']??null)!=='anex'||empty($row['tour_id']))continue;
            $index=$i;$tourId=(string)$row['tour_id'];break;
        }
        if($index===null||$tourId===null)continue;
        $detail=hmc_tv_call('/tours/'.rawurlencode($tourId),['currency'=>'RUB'],$counter);$detailCalls++;
        $e=hmc_detail_evidence($detail);
        if(count($e['native_hotel_refs'])===1)$tvOffers[$index]['native_anex_hotel_id']=$e['native_hotel_refs'][0];
        if($e['urls']){
            $tvOffers[$index]['operator_link']=$e['urls'][0];
            if(isset($e['urls'][1]))$tvOffers[$index]['hotel_url']=$e['urls'][1];
        }
    }
    return $tvOffers;
}
function hmc_candidate_evidence(array $resolved,array $tvRows,array $samoRows): array {
    $out=[];
    foreach($resolved['hotel_candidates']??[] as $c){
        $tvId=(string)$c['tv_hotel_id'];$samoId=(string)$c['samo_hotel_id'];
        $tv=array_values(array_filter($tvRows,fn($r)=>(string)$r['hotel_id']===$tvId));
        $sa=array_values(array_filter($samoRows,fn($r)=>(string)$r['hotel_id']===$samoId));
        if(count($tv)>HMC_MAX_CANDIDATE_EVIDENCE_ROWS)$tv=array_slice($tv,0,HMC_MAX_CANDIDATE_EVIDENCE_ROWS);
        if(count($sa)>HMC_MAX_CANDIDATE_EVIDENCE_ROWS)$sa=array_slice($sa,0,HMC_MAX_CANDIDATE_EVIDENCE_ROWS);
        $out[]=['tv_hotel_id'=>$tvId,'samo_hotel_id'=>$samoId,'tv_offers'=>$tv,'samo_offers'=>$sa];
    }
    return $out;
}


function hmc3_previous_checkpoint(string $prevDir): array {
    $result=json_decode((string)file_get_contents($prevDir.'/result.json'),true,32,JSON_THROW_ON_ERROR);
    $receipt=json_decode((string)file_get_contents($prevDir.'/receipt.json'),true,32,JSON_THROW_ON_ERROR);
    $plan=json_decode((string)file_get_contents($prevDir.'/search-plan.json'),true,32,JSON_THROW_ON_ERROR);
    if(($result['operation']??null)!==HMC_PREVIOUS_OP||($result['state']??null)!=='terminal_failed_no_replay'||($result['reason']??null)!=='tv_continue_cap')throw new RuntimeException('previous_result');
    if(($receipt['operation']??null)!==HMC_PREVIOUS_OP||!($receipt['provider_accessed']??false)||!($receipt['no_replay']??false))throw new RuntimeException('previous_receipt');
    if(($plan['route']['resort']??null)!=='Side'||($plan['route']['tv_region']['id']??null)!==23||($plan['route']['samo_townto']['id']??null)!==20)throw new RuntimeException('previous_route');
    if(($plan['date_from']??null)!==HMC_DATE_FROM||($plan['date_to']??null)!==HMC_DATE_TO||($plan['nights']??null)!==HMC_NIGHTS||($plan['adults']??null)!==HMC_ADULTS)throw new RuntimeException('previous_scope');
    if(($plan['tv_operator_ids']??null)!==[13,18,25,43]||array_map('strval',$plan['samo_operator_ids']??[])!==['5','115','315','342'])throw new RuntimeException('previous_operators');
    $sid=null;$union=[];$continues=0;$lastRequests=null;
    $metas=glob($prevDir.'/evidence-private/*-tourvisor.meta.json')?:[];sort($metas,SORT_STRING);
    foreach($metas as $metaPath){
        $m=json_decode((string)file_get_contents($metaPath),true,32,JSON_THROW_ON_ERROR);
        $path=(string)($m['meta']['path']??'');$params=(array)($m['meta']['params']??[]);
        $bin=preg_replace('/\.meta\.json$/','.bin',$metaPath);$raw=(string)file_get_contents($bin);
        if(hash('sha256',$raw)!==($m['raw_sha256']??null))throw new RuntimeException('previous_raw_hash');
        $reply=json_decode($raw,true,64,JSON_THROW_ON_ERROR);if(!is_array($reply))throw new RuntimeException('previous_raw_shape');
        if($path==='/tours/search'&&(int)($params['hotelCategory']??0)===3){
            if($sid!==null)throw new RuntimeException('previous_multiple_star3_starts');
            $sid=hmc_search_id($reply);if(!$sid)throw new RuntimeException('previous_search_id');
            continue;
        }
        if(!$sid)continue;
        if($path==='/tours/search/'.$sid.'/continue'){
            ++$continues;$lastRequests=hmc_request_count($reply);if($lastRequests===null)throw new RuntimeException('previous_continue_shape');continue;
        }
        if($path==='/tours/search/'.$sid){
            $union=hmc_tv_merge_rows($union,hmc_tv_result_rows($reply));
        }
    }
    if(!$sid||$continues!==16||$lastRequests===null||$lastRequests<=0||!$union)throw new RuntimeException('previous_checkpoint_incomplete');
    return ['search_id'=>$sid,'rows'=>$union,'prior_continue_calls'=>$continues,'last_request_count'=>$lastRequests,'plan'=>$plan];
}
function hmc3_tv_resume(array $cp,array &$counter): array {
    $sid=(int)$cp['search_id'];$union=$cp['rows'];$sig=hmc_tv_signatures($union);$rounds=[];
    for($round=(int)$cp['prior_continue_calls']+1;$round<=HMC_MAX_CONTINUE;$round++){
        $before=$sig;$cont=hmc_tv_call('/tours/search/'.$sid.'/continue',[],$counter);
        $requests=hmc_request_count($cont);if($requests===null)throw new RuntimeException('tv_continue_shape');
        if($requests>0)hmc_tv_wait($sid,$counter);
        $union=hmc_tv_merge_rows($union,hmc_tv_fetch($sid,$counter));$sig=hmc_tv_signatures($union);
        $rounds[]=['round'=>$round,'request_count'=>$requests,'hotels'=>$sig['hotels'],'tours'=>$sig['tours'],
            'added_hotels'=>max(0,$sig['hotels']-$before['hotels']),'added_tours'=>max(0,$sig['tours']-$before['tours'])];
        if($requests===0)return ['search_id'=>$sid,'rows'=>$union,'rounds'=>$rounds,'fully_drained'=>true,'exhaustion'=>'request_count_zero',
            'resumed_from'=>HMC_PREVIOUS_OP,'prior_continue_calls'=>$cp['prior_continue_calls']];
    }
    throw new RuntimeException('tv_resume_continue_cap');
}

function hmc_execute(string $root,string $opDir,string $user,string $pass): array {
    if($user===''||$pass==='')throw new RuntimeException('andromeda_credentials');
    require_once $root.'/config.php';
    require_once $opDir.'/payload/tourvisor-client-v1.php';
    require_once $opDir.'/payload/andromeda-client.php';
    require_once $opDir.'/payload/andromeda-network-transport-failure.php';
    require_once $opDir.'/payload/andromeda-transport.php';

    $reservation=json_decode((string)file_get_contents($opDir.'/reservation.json'),true,32,JSON_THROW_ON_ERROR);
    if(($reservation['operation']??null)!==HMC_OP||($reservation['state']??null)!=='reserved_before_provider_access')
        throw new RuntimeException('reservation');

    hmc_private_evidence_init($opDir);
    $tvCounter=['calls'=>0];$samoCalls=0;$detailCalls=0;

    $prevDir=dirname($opDir).'/'.HMC_PREVIOUS_OP;
    $checkpoint=hmc3_previous_checkpoint($prevDir);
    $previousPlan=$checkpoint['plan'];
    $region=[$previousPlan['route']['tv_region']];$town=[$previousPlan['route']['samo_townto']];
    $common=[
        'anex'=>['tv'=>['id'=>13,'name'=>'ANEX'],'samo'=>['id'=>5,'name'=>'ANEX']],
        'biblio'=>['tv'=>['id'=>18,'name'=>'Библио-Глобус'],'samo'=>['id'=>115,'name'=>'Biblio Globus']],
        'funsun'=>['tv'=>['id'=>25,'name'=>'FUN&SUN'],'samo'=>['id'=>315,'name'=>'FUN&SUN']],
        'intourist'=>['tv'=>['id'=>43,'name'=>'Интурист'],'samo'=>['id'=>342,'name'=>'Intourist']],
    ];
    $opInfo=['missing'=>[],'tv_ambiguous'=>[],'samo_ambiguous'=>[]];
    $tvOperatorIds=[13,18,25,43];$samoOperatorIds=['5','115','315','342'];$samoCsv=implode(',',$samoOperatorIds);
    $commonBySamoId=[5=>['family'=>'anex','name'=>'ANEX'],115=>['family'=>'biblio','name'=>'Biblio Globus'],
        315=>['family'=>'funsun','name'=>'FUN&SUN'],342=>['family'=>'intourist','name'=>'Intourist']];

    $transport=new AnyTourAndromedaTransport(false);
    $catalog=new AnyTourAndromedaClient(function(string $url,array $options)use($transport,&$samoCalls){
        if(++$samoCalls>HMC_MAX_SAMO_CALLS)throw new RuntimeException('samo_call_budget');
        ++$GLOBALS['HMC_SAMO_PHYSICAL_CALLS'];$raw=$transport($url,$options);
        hmc_private_evidence_record_raw('samo-http',['kind'=>'login_or_catalog','url_sha256'=>hash('sha256',$url)],
            (int)($raw['status']??0),(string)($raw['body']??''));return $raw;
    },true);
    $catalog->login($user,$pass);
    $all=$catalog->catalog('all',['TOWNFROMINC'=>HMC_DEPARTURE,'STATEINC'=>HMC_SAMO_STATE]);$session=$catalog->privateSession();
    $starIds=[];foreach([4] as $star){$id=hmc_star_id($all['STARS'],$star);if(!$id)throw new RuntimeException('samo_star_binding_'.$star);$starIds[$star]=$id;}
    $date=HMC_DATE_FROM;$dateTo=HMC_DATE_TO;$weekDates=hmc_week_dates();$ymd=str_replace('-','',$date);$ymdTo=str_replace('-','',$dateTo);
    $plan=[
        'operation'=>HMC_OP,'state'=>'planned_before_search','created_at'=>gmdate('c'),
        'route'=>['departure'=>'Moscow','country'=>'Turkey','resort'=>'Side','tv_region'=>$region[0],'samo_townto'=>$town[0]],
        'date_from'=>$date,'date_to'=>$dateTo,'dates'=>$weekDates,'nights'=>HMC_NIGHTS,'adults'=>HMC_ADULTS,'children'=>HMC_CHILDREN,
        'tv_operator_ids'=>$tvOperatorIds,'samo_operator_ids'=>$samoOperatorIds,
        'operator_families'=>array_keys($common),'tourvisor_dates_endpoint_used'=>false,
        'detail_calls_planned'=>0,'flight_calls_planned'=>0,'calc_calls_planned'=>0,
        'scope_provenance'=>HMC_PREVIOUS_OP,'acquisition_scope'=>'fresh_full_pair_star4_only',
    ];
    $planSha=hmc_write($opDir.'/search-plan.json',$plan);

    $stars=[];
    foreach([4] as $star){
        $tvParams=[
            'departureId'=>HMC_DEPARTURE,'countryId'=>HMC_TV_COUNTRY,'dateFrom'=>$date,'dateTo'=>$dateTo,
            'nightsFrom'=>HMC_NIGHTS,'nightsTo'=>HMC_NIGHTS,'adults'=>HMC_ADULTS,'currency'=>'RUB',
            'onlyCharter'=>false,'regionIds'=>[$region[0]['id']],'operatorIds'=>$tvOperatorIds,'hotelCategory'=>$star,
        ];
        $td=hmc_tv_drain($tvParams,$tvCounter);
        $tvRows=hmc_tv_offer_rows($td['rows'],$date,$common);

        $samoRows=[];$pageMeta=[];$pagesCount=null;
        for($page=1;$page<=HMC_MAX_SAMO_PAGES;$page++){
            $params=[
                'TOWNFROMINC'=>HMC_DEPARTURE,'STATEINC'=>HMC_SAMO_STATE,'CHECKIN_BEG'=>$ymd,'CHECKIN_END'=>$ymdTo,
                'NIGHTS_FROM'=>HMC_NIGHTS,'NIGHTS_TILL'=>HMC_NIGHTS,'ADULT'=>HMC_ADULTS,'CHILD'=>HMC_CHILDREN,
                'CURRENCYINC'=>643,'STARS'=>(string)$starIds[$star],'OPERATORS'=>$samoCsv,
                'TOWNTOINC'=>(string)$town[0]['id'],'PACKETTYPE'=>0,'PAGE'=>$page,'GROUP_BY'=>32,
            ];
            $tr=new AnyTourAndromedaTransport(true);
            $cl=new AnyTourAndromedaClient(function(string $url,array $options)use($tr,&$samoCalls,$star,$page,$params){
                if(++$samoCalls>HMC_MAX_SAMO_CALLS)throw new RuntimeException('samo_call_budget');
                ++$GLOBALS['HMC_SAMO_PHYSICAL_CALLS'];
                $raw=$tr($url,$options);
                hmc_private_evidence_record_raw('samo-price',['star'=>$star,'page'=>$page,'params'=>$params,'url_sha256'=>hash('sha256',$url)],
                    (int)($raw['status']??0),(string)($raw['body']??''));
                return $raw;
            },true);
            $cl->restorePrivateSession($session);$reply=$cl->price($params);
            $pagesCount=$reply['PAGES_COUNT'];
            if($pagesCount>HMC_MAX_SAMO_PAGES)throw new RuntimeException('samo_page_cap');
            $before=count($samoRows);
            foreach($reply['PRICES'] as $raw)if(is_array($raw)&&($row=hmc_samo_offer_row($raw,$date,$commonBySamoId))!==null)$samoRows[]=$row;
            $pageMeta[]=['page'=>$page,'pages_count'=>$pagesCount,'rows'=>count($reply['PRICES']),
                'accepted_evidence_rows'=>count($samoRows)-$before];
            if($page>=$pagesCount)break;
        }
        if($pagesCount===null)throw new RuntimeException('samo_not_fully_drained');
        if($pagesCount===0){
            if(count($pageMeta)!==1||($pageMeta[0]['rows']??-1)!==0)throw new RuntimeException('samo_zero_page_shape');
        }elseif(count($pageMeta)!==$pagesCount)throw new RuntimeException('samo_not_fully_drained');

        $resolved=hmf_resolve($tvRows,$samoRows,[]);
        $evidence=hmc_candidate_evidence($resolved,$tvRows,$samoRows);

        $tvFamilies=[];$samoFamilies=[];
        foreach($tvRows as $r)$tvFamilies[$r['operator_family']]=($tvFamilies[$r['operator_family']]??0)+1;
        foreach($samoRows as $r)$samoFamilies[$r['operator_family']]=($samoFamilies[$r['operator_family']]??0)+1;
        ksort($tvFamilies);ksort($samoFamilies);

        $stars[(string)$star]=[
            'tourvisor'=>['search_id'=>$td['search_id'],'fully_drained'=>$td['fully_drained'],
                'continue_rounds'=>$td['rounds'],'source_hotels'=>count($td['rows']),'evidence_rows'=>count($tvRows),
                'operator_rows'=>$tvFamilies],
            'andromeda'=>['pages_count'=>$pagesCount,'pages_drained'=>$pagesCount===0?0:count($pageMeta),
                'page_meta'=>$pageMeta,'evidence_rows'=>count($samoRows),'operator_rows'=>$samoFamilies],
            'resolver'=>$resolved,'candidate_evidence'=>$evidence,
        ];
    }

    return [
        'operation'=>HMC_OP,'state'=>'completed_read_only',
        'route'=>['departure'=>'Moscow','country'=>'Turkey','resort'=>'Side','date_from'=>$date,'date_to'=>$dateTo,
            'nights'=>HMC_NIGHTS,'adults'=>HMC_ADULTS,'children'=>HMC_CHILDREN],
        'common_operators'=>array_values($common),'missing_operator_families'=>$opInfo['missing'],
        'tv_ambiguous_operator_families'=>$opInfo['tv_ambiguous'],'samo_ambiguous_operator_families'=>$opInfo['samo_ambiguous'],
        'tv_operator_ids'=>$tvOperatorIds,'samo_operator_ids'=>$samoOperatorIds,
        'tv_multi_operator_mode'=>'repeated_operatorIds_query_param',
        'samo_multi_operator_mode'=>'csv_OPERATORS',
        'date_window'=>['from'=>$date,'to'=>$dateTo,'days'=>count($weekDates),'source'=>'retained-live-2026-10-05'],
        'search_plan_sha256'=>$planSha,
        'tv_region'=>$region[0],'samo_townto'=>$town[0],'samo_star_ids'=>$starIds,
        'tv_calls'=>$tvCounter['calls'],'tv_tariff_search_units'=>$GLOBALS['HMC_TV_TARIFF_UNITS'],
        'scope_provenance'=>HMC_PREVIOUS_OP,'acquisition_scope'=>'fresh_full_pair_star4_only',
        'andromeda_calls'=>$GLOBALS['HMC_SAMO_PHYSICAL_CALLS'],'andromeda_price_calls'=>$samoCalls,'tourvisor_detail_calls'=>0,
        'private_evidence'=>['response_hashes'=>$GLOBALS['HMC_PRIVATE_EVIDENCE_HASHES'],'raw_payload_exported'=>false,'server_directory'=>'evidence-private'],
        'detail_queue_state'=>'pending_current_and_fuel_reconcile','flight_calls'=>0,'calc_calls'=>0,
        'stars'=>$stars,
        'database_writes'=>0,'mapping_writes'=>0,'booking_calls'=>0,'lead_writes'=>0,'metrika_writes'=>0,
        'no_replay'=>true,
    ];
}

if(in_array('--self-test',$argv??[],true)){
    $ops=hmc_common_operators(
        [['id'=>11,'name'=>'ANEX TOUR'],['id'=>12,'name'=>'Библио-Глобус'],['id'=>13,'name'=>'FUN&SUN'],['id'=>14,'name'=>'Интурист']],
        [['id'=>51,'name'=>'ANEX'],['id'=>52,'name'=>'Biblio Globus'],['id'=>53,'name'=>'FUN SUN'],['id'=>54,'name'=>'Intourist']]
    );
    if(array_keys($ops['common'])!==['anex','biblio','funsun','intourist'])throw new RuntimeException('operators');
    $merged=hmc_tv_merge_rows(
        [['id'=>1,'name'=>'A','tours'=>[['id'=>'t1']]]],
        [['id'=>1,'name'=>'A','tours'=>[['id'=>'t2']]],['id'=>2,'name'=>'B','tours'=>[['id'=>'t3']]]]
    );
    $sig=hmc_tv_signatures($merged);if($sig!==['hotels'=>2,'tours'=>3])throw new RuntimeException('tv_union');
    if(hmc_url('https://example.test/h?session=secret')!==null)throw new RuntimeException('url_secret');
    if(hmf_room_key('DELUXE SEA VIEW ROOM')!=='deluxe sea view')throw new RuntimeException('room_qualifier');
    echo "MATCH_TV_SAMO_COMMON4_SIDE_STAR4_V4_SELFTEST_OK\n";exit(0);
}

if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    if(($argv[1]??'')!=='--execute')throw new RuntimeException('disabled');
    $root=(string)getenv('ANYTOUR_ROOT');$opDir=(string)getenv('MATCH_OPERATION_DIR');
    if($root===''||$opDir===''||!is_dir($opDir))throw new RuntimeException('runtime_paths');
    $user=rtrim((string)fgets(STDIN),"\r\n");$pass=rtrim((string)fgets(STDIN),"\r\n");
    $providerAccessed=false;
    try{
        $providerAccessed=true;
        $result=hmc_execute($root,$opDir,$user,$pass);
        $sha=hmc_write($opDir.'/result.json',$result);
        hmc_write($opDir.'/receipt.json',['operation'=>HMC_OP,'state'=>'completed_read_only','result_sha256'=>$sha,
            'readback_verified'=>hash_file('sha256',$opDir.'/result.json')===$sha,'provider_accessed'=>true,
            'database_writes'=>0,'mapping_writes'=>0,'no_replay'=>true]);
        echo hmc_json(['state'=>'completed_read_only','tv_calls'=>$result['tv_calls'],'andromeda_calls'=>$result['andromeda_calls'],
            'candidate_counts'=>array_map(fn($x)=>$x['resolver']['hotel_candidate_count']??0,$result['stars'])])."\n";
        exit(0);
    }catch(Throwable $e){
        $reason=preg_match('/^[A-Za-z0-9_.:-]{1,120}$/D',$e->getMessage())?$e->getMessage():'sanitized_failure';
        $fail=['operation'=>HMC_OP,'state'=>$providerAccessed?'terminal_failed_no_replay':'pre_provider_failed',
            'reason'=>$reason,'database_writes'=>0,'mapping_writes'=>0,'booking_calls'=>0,'lead_writes'=>0,
            'no_replay'=>$providerAccessed];
        $sha=hmc_write($opDir.'/result.json',$fail);
        hmc_write($opDir.'/receipt.json',['operation'=>HMC_OP,'state'=>$fail['state'],'result_sha256'=>$sha,
            'readback_verified'=>true,'provider_accessed'=>$providerAccessed,'database_writes'=>0,'mapping_writes'=>0,
            'no_replay'=>$providerAccessed]);
        fwrite(STDERR,$reason."\n");exit(2);
    }
}
