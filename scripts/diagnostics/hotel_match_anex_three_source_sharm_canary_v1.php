<?php
declare(strict_types=1);

const OPERATION_ID = 'hotel-match-anex-three-source-sharm-1971-20260918-v1';
const COUNTRY_LOCAL_ID = 1;
const SEARCH_DATE = '2026-10-26';
const SEARCH_DATE_COMPACT = '20261026';
const NIGHTS = 7;
const ADULTS = 2;
const RESORT_LABEL = 'Sharm El Sheikh';
const STAR_BUCKETS = [3,4,5];
const TV_DETAIL_CAP = 12;

function m3_json(array $v): string {
    return json_encode($v, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
}
function m3_write(string $path, array $v): string {
    $raw=m3_json($v)."\n"; $f=@fopen($path,'x+b');
    if(!$f) throw new RuntimeException('OUTPUT_EXISTS');
    try { if(fwrite($f,$raw)!==strlen($raw)||!fflush($f)) throw new RuntimeException('OUTPUT_WRITE'); if(function_exists('fsync')) @fsync($f); }
    finally { fclose($f); }
    if(file_get_contents($path)!==$raw) throw new RuntimeException('OUTPUT_READBACK');
    return hash('sha256',$raw);
}
function m3_norm(mixed $v): string {
    if(!is_scalar($v)) return '';
    $s=mb_strtolower(trim((string)$v),'UTF-8'); $s=str_replace('ё','е',$s);
    $s=preg_replace('/[^\p{L}\p{N}]+/u',' ',$s)??$s;
    return trim(preg_replace('/\s+/u',' ',$s)??$s);
}
function m3_id(mixed $v): ?string {
    if(is_int($v)&&$v>0)$v=(string)$v;
    return is_string($v)&&preg_match('/^[1-9][0-9]{0,31}$/D',$v)?$v:null;
}
function m3_int_id(mixed $v): ?int {
    $x=filter_var($v,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    return $x===false?null:(int)$x;
}
function m3_rows(array $payload, array $keys=[]): array {
    if(array_is_list($payload)) return $payload;
    foreach($keys as $k) if(isset($payload[$k])&&is_array($payload[$k])) return $payload[$k];
    foreach($payload as $v) if(is_array($v)&&array_is_list($v)) return $v;
    return [];
}
function m3_dict_id(array $rows,array $aliases): int {
    $wanted=array_fill_keys(array_map('m3_norm',$aliases),true); $found=[];
    foreach($rows as $row){
        if(!is_array($row))continue; $id=m3_int_id($row['id']??$row['inc']??null); if(!$id)continue;
        foreach(['name','lName','alias','title'] as $k){$n=m3_norm($row[$k]??'');if($n!==''&&isset($wanted[$n])){$found[$id]=true;break;}}
    }
    if(count($found)!==1) throw new RuntimeException('DICTIONARY_NOT_UNIQUE');
    return (int)array_key_first($found);
}
function m3_star(mixed $v): ?int {
    if(is_int($v)&&in_array($v,[3,4,5],true))return $v;
    $s=m3_norm($v); if(preg_match('/(?:^| )(3|4|5)(?: |$)/',$s,$m))return (int)$m[1];
    return null;
}
function m3_money(mixed $v): ?string {
    if(is_int($v)||(is_float($v)&&is_finite($v)))$v=(string)$v;
    return is_string($v)&&preg_match('/^(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?$/D',$v)&&preg_match('/[1-9]/',$v)?$v:null;
}
function m3_sharm(mixed $v): bool {
    $n=m3_norm($v);
    return in_array($n,['sharm el sheikh','sharm el sheik','sharm el sheikh egypt','шарм эль шейх','шарм эль шейх египет'],true);
}
function m3_tv_anex_link(mixed $url): ?string {
    if(!is_string($url)||trim($url)==='')return null; $p=parse_url(trim($url)); if(!is_array($p))return null;
    $host=strtolower((string)($p['host']??'')); if(!in_array($host,['agent.anextour.ru','www.anextour.ru','anextour.ru'],true))return null;
    parse_str((string)($p['query']??''),$q); $vals=[];
    foreach($q as $k=>$v) if(strtoupper((string)$k)==='HOTELLIST'){
        foreach(is_array($v)?$v:explode(',',(string)$v) as $x){$id=m3_id(trim((string)$x)); if($id!==null)$vals[$id]=true;}
    }
    return count($vals)===1?(string)array_key_first($vals):null;
}
function m3_anex_calendar_has(array $calendar,string $date): bool {
    $start=$calendar['start']??null; $valid=$calendar['valid']??null;
    if(!is_string($start)||!is_string($valid))return false;
    $s=DateTimeImmutable::createFromFormat('!Y-m-d',$start)?:DateTimeImmutable::createFromFormat('!Ymd',$start);
    $d=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
    if(!$s||!$d)return false; $off=(int)$s->diff($d)->format('%r%a');
    return $off>=0&&isset($valid[$off])&&strpos('1235',$valid[$off])!==false;
}
function m3_safe_offer(array $base): ?array {
    foreach(['hotel_name','resort','stars','date','nights','adults','children','meal','room','price','currency'] as $k) if(!array_key_exists($k,$base))return null;
    if(($base['currency']??'')!=='RUB'||m3_money($base['price'])===null||m3_star($base['stars'])===null||!m3_sharm($base['resort']))return null;
    if((string)$base['date']!==SEARCH_DATE||(int)$base['nights']!==NIGHTS||(int)$base['adults']!==ADULTS||(int)$base['children']!==0)return null;
    if(trim((string)$base['hotel_name'])===''||trim((string)$base['meal'])===''||trim((string)$base['room'])==='')return null;
    return $base;
}
function m3_find_file(string $root,string $path): string {
    foreach([$root.$path,$root.'/_preview/search3-anex-candidate'.$path] as $p) if(is_file($p))return $p;
    throw new RuntimeException('RUNTIME_DEPENDENCY_MISSING');
}
function m3_tv_ready(array $s): bool {
    return (is_numeric($s['progress']??null)&&(float)$s['progress']>=100)
        || in_array(strtolower((string)($s['status']??'')),['complete','completed','done','ready'],true);
}

if(in_array('--self-test',$argv??[],true)){
    if(m3_tv_anex_link('https://agent.anextour.ru/search/tour?HOTELLIST=4158')!=='4158')throw new RuntimeException('SELF_LINK');
    if(m3_tv_anex_link('https://evil.test/?HOTELLIST=4158')!==null)throw new RuntimeException('SELF_HOST');
    if(!m3_sharm('Шарм-эль-Шейх')||m3_star('5*')!==5)throw new RuntimeException('SELF_NORM');
    echo "MATCH_THREE_SOURCE_SHARM_SELFTEST_OK\n"; exit(0);
}

if(PHP_SAPI!=='cli'||($argv[1]??'')!=='--execute'||!isset($argv[2])){fwrite(STDERR,"EXECUTE_DISABLED\n");exit(2);}
$outDir=$argv[2]; if(!is_dir($outDir)||is_link($outDir))throw new RuntimeException('OUTPUT_DIR');
if((getenv('OPERATION_ID')?:'')!==OPERATION_ID)throw new RuntimeException('OPERATION_GUARD');
umask(0077);

$home=(string)getenv('HOME'); $root=realpath($home.'/www/anytoour.ru'); if(!$root)throw new RuntimeException('ROOT');
$preview=realpath($root.'/_preview/search3-anex-candidate');
if(!$preview || $preview!==$root.'/_preview/search3-anex-candidate') throw new RuntimeException('ANEX_PREVIEW_RUNTIME');
$res=['schema_version'=>1,'operation'=>OPERATION_ID,'status'=>'blocked','phase'=>'runtime_init','provider_accessed'=>false,
    'criteria'=>['country'=>'Egypt','resort'=>RESORT_LABEL,'date'=>SEARCH_DATE,'nights'=>NIGHTS,'adults'=>ADULTS,'children'=>0,'stars'=>STAR_BUCKETS,'operator'=>'ANEX'],
    'provider_calls'=>['tourvisor'=>0,'anex'=>0,'andromeda'=>0],'mapping_writes'=>0,'database_writes'=>0,'booking_calls'=>0];
try{
    require_once m3_find_file($root,'/data/tourvisor-client-v1.php');
    putenv('TOURVISOR_HTTP_MAX_ATTEMPTS=1');

    require_once $home.'/.anytoour-anex/search3-preview.php';
    if(!defined('ANYTOUR_ANEX_PREVIEW_ENABLED') || ANYTOUR_ANEX_PREVIEW_ENABLED!==true
        || !defined('ANEX_API_TOKEN') || !is_string(ANEX_API_TOKEN) || trim(ANEX_API_TOKEN)==='') {
        throw new RuntimeException('ANEX_PREVIEW_CONFIGURATION');
    }
    require_once $preview.'/app/integrations/anex-search.php';
    require_once $preview.'/app/integrations/anex-search-mapping-registry.php';
    require_once $preview.'/app/integrations/anex-search-observations.php';
    $_SERVER['SCRIPT_FILENAME']='';
    require_once $preview.'/api-anex-search3-preview.php';

    require_once m3_find_file($root,'/app/integrations/andromeda-client.php');
    require_once m3_find_file($root,'/app/integrations/andromeda-transport.php');
    $andUser=getenv('ANDROMEDA_USERNAME'); $andPassword=getenv('ANDROMEDA_PASSWORD');
    if(!is_string($andUser)||trim($andUser)===''||!is_string($andPassword)||$andPassword==='')throw new RuntimeException('ANDROMEDA_CREDENTIALS');

    // Direct ANEX dictionary/date preflight.
    $res['phase']='anex_preflight';
    if(!defined('ANEX_API_TOKEN')||!is_string(ANEX_API_TOKEN)||trim(ANEX_API_TOKEN)==='')throw new RuntimeException('ANEX_TOKEN');
    $anexClient=new AnyTourAnexClient(ANEX_API_TOKEN); $cache=[];
    $aDeparture=anytour_anex_search3_dictionary_id(anytour_anex_search3_dictionary($anexClient,'SearchTour_TOWNFROMS',[],$cache),['Москва','Moscow']);
    $aCountry=anytour_anex_search3_dictionary_id(anytour_anex_search3_dictionary($anexClient,'SearchTour_STATES',['TOWNFROMINC'=>$aDeparture],$cache),['Египет','Egypt']);
    $party=['TOWNFROMINC'=>$aDeparture,'STATEINC'=>$aCountry,'ADULT'=>ADULTS,'CHILD'=>0];
    $calendar=anytour_anex_search3_dictionary($anexClient,'SearchTour_CHECKIN',$party,$cache);
    if(!m3_anex_calendar_has($calendar,SEARCH_DATE))throw new RuntimeException('ANEX_DATE_UNAVAILABLE');
    $dated=$party+['CHECKIN_BEG'=>SEARCH_DATE_COMPACT,'CHECKIN_END'=>SEARCH_DATE_COMPACT];
    $aCurrency=anytour_anex_search3_dictionary_id(anytour_anex_search3_dictionary($anexClient,'SearchTour_CURRENCIES',$dated,$cache),['RUB','RUR','Рубль','Рубли','Руб']);
    $res['provider_calls']['anex']=$anexClient->requestsMade();

    // Andromeda dictionaries: Moscow -> Egypt -> exact Sharm + exact 3/4/5 star IDs + ANEX operator.
    $res['phase']='andromeda_catalog';
    $andBase=new AnyTourAndromedaClient(new AnyTourAndromedaTransport(true),true);
    $andBase->login($andUser,$andPassword); $res['provider_calls']['andromeda']++;
    $townfrom=$andBase->catalog('townfrom'); $res['provider_calls']['andromeda']++;
    $d=m3_dict_id($townfrom['TOWNFROM'],['Москва','Moscow']);
    $states=$andBase->catalog('state',['TOWNFROMINC'=>$d]); $res['provider_calls']['andromeda']++;
    $state=m3_dict_id($states['STATE'],['Египет','Egypt']);
    $all=$andBase->catalog('all',['TOWNFROMINC'=>$d,'STATEINC'=>$state]); $res['provider_calls']['andromeda']++;
    $townTo=m3_dict_id($all['TOWNTO'],['Sharm El Sheikh','Sharm El-Sheikh','Шарм-эль-Шейх','Шарм эль Шейх']);
    $operator=m3_dict_id($all['OPERATORS'],['ANEX','ANEX TOUR','Анекс Тур']); if($operator!==5)throw new RuntimeException('ANDROMEDA_ANEX_OPERATOR');
    $currency=m3_dict_id($all['CURRENCY'],['RUB','RUR','Рубль','Рубли','Руб']);
    $starIds=[]; foreach(STAR_BUCKETS as $star)$starIds[$star]=m3_dict_id($all['STARS'],[(string)$star,$star.'*',$star.'★']);

    $andrRows=[]; $nativeByStar=[];
    foreach(STAR_BUCKETS as $star){
        $client=new AnyTourAndromedaClient(new AnyTourAndromedaTransport(true),true);
        $client->login($andUser,$andPassword); $res['provider_calls']['andromeda']++;
        $params=['TOWNFROMINC'=>$d,'STATEINC'=>$state,'CHECKIN_BEG'=>SEARCH_DATE_COMPACT,'CHECKIN_END'=>SEARCH_DATE_COMPACT,
            'NIGHTS_FROM'=>NIGHTS,'NIGHTS_TILL'=>NIGHTS,'ADULT'=>ADULTS,'CHILD'=>0,'CURRENCYINC'=>$currency,
            'STARS'=>(string)$starIds[$star],'OPERATORS'=>'5','TOWNTOINC'=>(string)$townTo,'PACKETTYPE'=>0,'PAGE'=>1,'GROUP_BY'=>32];
        $reply=$client->price($params); $res['provider_calls']['andromeda']++;
        foreach($reply['PRICES'] as $r){
            if(!is_array($r)||(string)($r['operatorKey']??'')!=='5'||!in_array($r['isOperatorHotelKey']??null,[0,'0'],true))continue;
            $andr=m3_id($r['hotelKey']??null); $native=m3_id($r['original']['hotelKey']??null); if(!$andr||!$native)continue;
            $name=(string)($r['hotel']??''); if($name===''||preg_match('/fortuna|roulette|фортуна|рулетк/ui',$name))continue;
            $price=m3_money($r['price']??null); if($price===null)continue;
            $meal=(string)($r['meal']??''); $room=(string)($r['room']??'');
            if($meal===''||$room===''){$meal='unknown';$room='unknown';}
            $row=m3_safe_offer(['hotel_name'=>$name,'resort'=>RESORT_LABEL,'stars'=>$star,'date'=>SEARCH_DATE,'nights'=>NIGHTS,'adults'=>ADULTS,'children'=>0,
                'meal'=>$meal,'room'=>$room,'price'=>$price,'currency'=>'RUB',
                'andromeda_hotel_id'=>$andr,'operator_key'=>'5','is_operator_hotel_key'=>0,'original_hotel_id'=>$native]);
            if($row){$andrRows[]=$row;$nativeByStar[$star][$native]=true;}
        }
    }
    $res['provider_accessed']=true;

    // Direct ANEX, targeted only to native IDs observed in Andromeda ANEX rows.
    $res['phase']='direct_anex_prices';
    $anexRows=[];
    foreach(STAR_BUCKETS as $star){
        $ids=array_map('strval',array_keys($nativeByStar[$star]??[])); sort($ids,SORT_STRING); $ids=array_slice($ids,0,30);
        if(!$ids)continue;
        $criteria=['supplier_namespace'=>'anex_online','departure_id'=>$aDeparture,'destination_id'=>$aCountry,'currency_id'=>$aCurrency,
            'checkin_begin'=>SEARCH_DATE,'checkin_end'=>SEARCH_DATE,'nights_from'=>NIGHTS,'nights_till'=>NIGHTS,
            'adults'=>ADULTS,'children'=>0,'child_ages'=>[],'hotel_ids'=>$ids];
        $search=new AnyTourAnexSearch($anexClient,null,[ANEX_API_TOKEN]);
        $reply=$search->search($criteria); $res['provider_calls']['anex']=$anexClient->requestsMade();
        foreach($reply['offers'] as $o){
            $hid=m3_id($o['hotel']['external_id']??null); if(!$hid||!isset(($nativeByStar[$star]??[])[$hid]))continue;
            $town=$o['hotel']['town']??null; if($town!==null&&!m3_sharm($town))continue;
            $p=(($o['price']['currency']??'')==='RUB')?$o['price']:($o['converted_price']??null);
            if(!is_array($p))continue;
            $row=m3_safe_offer(['hotel_name'=>$o['hotel']['name']??'','resort'=>RESORT_LABEL,'stars'=>$star,'date'=>$o['checkin']??'',
                'nights'=>$o['nights']??0,'adults'=>$o['adults']??0,'children'=>$o['children']??0,
                'meal'=>$o['meal']??'unknown','room'=>$o['room']??'unknown','price'=>$p['amount']??null,'currency'=>$p['currency']??'',
                'anex_hotel_id'=>$hid]);
            if($row)$anexRows[]=$row;
        }
    }

    // Tourvisor: same resort/date/party, operator ANEX only, exact requested category.
    $res['phase']='tourvisor_search';
    $ops=v2_data_tv_get('/operators',['departureId'=>1,'countryId'=>COUNTRY_LOCAL_ID]);
    $res['provider_calls']['tourvisor']=v2_data_tv_http_attempt_count();
    $tvOp=m3_dict_id(m3_rows($ops,['operators','items']),['ANEX','ANEX TOUR','ANEX Tour','Анекс Тур']);
    $regions=v2_data_tv_get('/regions',['countryId'=>COUNTRY_LOCAL_ID]);
    $res['provider_calls']['tourvisor']=v2_data_tv_http_attempt_count();
    $region=m3_dict_id(m3_rows($regions,['regions','items']),['Sharm El Sheikh','Sharm El-Sheikh','Шарм-эль-Шейх','Шарм эль Шейх']);
    $tvRows=[]; $tvTours=[];
    foreach(STAR_BUCKETS as $star){
        $criteria=['departureId'=>1,'countryId'=>COUNTRY_LOCAL_ID,'dateFrom'=>SEARCH_DATE,'dateTo'=>SEARCH_DATE,'nightsFrom'=>NIGHTS,'nightsTo'=>NIGHTS,
            'adults'=>ADULTS,'childs'=>[],'currency'=>'RUB','onlyCharter'=>false,'onlyDirect'=>false,'operatorIds'=>[$tvOp],
            'regionIds'=>[$region],'hotelCategory'=>(string)$star];
        $start=v2_data_tv_get('/tours/search',$criteria); $sid=m3_id($start['searchId']??null); if(!$sid)throw new RuntimeException('TV_SEARCH_ID');
        $ready=false; foreach([2,5,8,10,10] as $wait){sleep($wait);$st=v2_data_tv_get('/tours/search/'.$sid.'/status',['operatorStatus'=>false]);if(m3_tv_ready($st)){$ready=true;break;}}
        if(!$ready)throw new RuntimeException('TV_SEARCH_NOT_READY');
        $groups=v2_data_tv_get('/tours/search/'.$sid,['limit'=>100]);
        foreach(m3_rows($groups,['hotels','results','items']) as $h){
            if(!is_array($h))continue; $hid=m3_id($h['id']??null); if(!$hid)continue;
            $hs=m3_star($h['category']??null); if($hs!==null&&$hs!==$star)continue;
            $hname=(string)($h['name']??''); if($hname===''||preg_match('/fortuna|roulette|фортуна|рулетк/ui',$hname))continue;
            foreach(is_array($h['tours']??null)?$h['tours']:[] as $t){
                if(!is_array($t))continue; $tid=m3_id($t['id']??null); if(!$tid)continue;
                $opName=is_array($t['operator']??null)?($t['operator']['name']??''):($t['operator']??'ANEX');
                $opId=m3_id($t['operatorId']??(is_array($t['operator']??null)?($t['operator']['id']??null):null));
                if($opId!==null&&(int)$opId!==$tvOp)continue;
                $p=m3_money($t['price']??null); if($p===null)continue;
                $row=m3_safe_offer(['hotel_name'=>$hname,'resort'=>RESORT_LABEL,'stars'=>$star,'date'=>$t['date']??SEARCH_DATE,'nights'=>$t['nights']??NIGHTS,
                    'adults'=>ADULTS,'children'=>0,'meal'=>$t['meal']??'unknown','room'=>$t['roomType']??$t['room']??'unknown',
                    'price'=>$p,'currency'=>$t['currency']??'RUB','fuel_charge'=>m3_money($t['fuelCharge']??null),
                    'tv_hotel_id'=>$hid,'tour_id'=>$tid,'operator_name'=>$opName]);
                if($row){$tvRows[]=$row;if(!isset($tvTours[$hid]))$tvTours[$hid]=$tid;}
            }
        }
    }
    $res['provider_calls']['tourvisor']=v2_data_tv_http_attempt_count();
    if($res['provider_calls']['tourvisor']>32||$res['provider_calls']['anex']>10||$res['provider_calls']['andromeda']>12)throw new RuntimeException('CALL_BUDGET');

    require_once __DIR__.'/hotel_match_anex_three_source_resort_star_v1.php';
    $first=AnyTourAnexThreeSourceResortStarV1::resolve(['schema_version'=>1,'anex'=>$anexRows,'andromeda'=>$andrRows,'tourvisor'=>$tvRows]);

    // Direct confirmation pass: exact normalized name within the same star bucket only.
    $res['phase']='tourvisor_operator_link';
    $anexName=[];
    foreach($anexRows as $a){$k=AnyTourAnexThreeSourceResortStarV1::nameKey($a['hotel_name']);if($k!=='')$anexName[$a['stars']][$k][$a['anex_hotel_id']]=true;}
    $detailTargets=[];
    foreach($tvRows as $t){
        $k=AnyTourAnexThreeSourceResortStarV1::nameKey($t['hotel_name']); $ids=array_keys($anexName[$t['stars']][$k]??[]);
        if(count($ids)===1&&!isset($detailTargets[$t['tv_hotel_id']]))$detailTargets[$t['tv_hotel_id']]=['tour_id'=>$t['tour_id'],'expected_anex'=>(string)$ids[0]];
    }
    $detailTargets=array_slice($detailTargets,0,TV_DETAIL_CAP,true); $directDetails=[];
    foreach($detailTargets as $tvHotel=>$target){
        $detail=v2_data_tv_get('/tours/'.$target['tour_id'],['currency'=>'RUB']);
        $native=m3_tv_anex_link($detail['operatorLink']??null);
        $directDetails[]=['tv_hotel_id'=>(string)$tvHotel,'expected_anex_hotel_id'=>$target['expected_anex'],'operator_link_anex_id'=>$native,
            'matches_expected'=>$native!==null&&hash_equals($target['expected_anex'],$native)];
        if($native!==null)foreach($tvRows as &$row)if($row['tv_hotel_id']===(string)$tvHotel)$row['operator_link_anex_id']=$native;unset($row);
    }
    $res['provider_calls']['tourvisor']=v2_data_tv_http_attempt_count();
    if($res['provider_calls']['tourvisor']>32)throw new RuntimeException('TV_CALL_BUDGET');

    $final=AnyTourAnexThreeSourceResortStarV1::resolve(['schema_version'=>1,'anex'=>$anexRows,'andromeda'=>$andrRows,'tourvisor'=>$tvRows]);
    $res += [
        'status'=>'completed','phase'=>'completed','rows'=>['anex'=>count($anexRows),'andromeda'=>count($andrRows),'tourvisor'=>count($tvRows)],
        'native_anex_ids_from_andromeda'=>count(array_unique(array_map(fn($r)=>$r['original_hotel_id'],$andrRows))),
        'pre_detail_counts'=>$first['counts'],'post_detail_counts'=>$final['counts'],
        'direct_detail_checks'=>$directDetails,
        'direct_tourvisor'=>$final['direct_tourvisor'],
        'prepared_candidates'=>array_slice($final['prepared_candidates'],0,50),
        'ambiguous_count'=>$final['counts']['ambiguous_tv_hotels'],'conflict_count'=>$final['counts']['conflicts'],
        'mapping_writes'=>0,'database_writes'=>0,'booking_calls'=>0,'no_replay'=>true,
    ];
}catch(Throwable $e){
    $msg=$e->getMessage(); $res['status']='terminal_failed_no_retry';
    $res['error']=preg_match('/^[A-Z0-9_]{2,80}$/D',$msg)?$msg:'SANITIZED_FAILURE';
    $res['provider_calls']['tourvisor']=function_exists('v2_data_tv_http_attempt_count')?v2_data_tv_http_attempt_count():$res['provider_calls']['tourvisor'];
    if(isset($anexClient) && is_object($anexClient) && method_exists($anexClient,'requestsMade')) $res['provider_calls']['anex']=$anexClient->requestsMade();
    $res['provider_accessed']=$res['provider_accessed']||array_sum($res['provider_calls'])>0;
    $res['no_replay']=$res['provider_accessed'];
}
$digest=m3_write($outDir.'/result.json',$res);
m3_write($outDir.'/receipt.json',['operation'=>OPERATION_ID,'status'=>$res['status'],'result_sha256'=>$digest,'provider_accessed'=>$res['provider_accessed'],
    'provider_calls'=>$res['provider_calls'],'mapping_writes'=>0,'database_writes'=>0,'booking_calls'=>0,'no_replay'=>$res['no_replay']??false,'readback_verified'=>true]);
echo m3_json(['status'=>$res['status'],'provider_calls'=>$res['provider_calls'],'rows'=>$res['rows']??null,'post_detail_counts'=>$res['post_detail_counts']??null]),"\n";
exit($res['status']==='completed'?0:2);
