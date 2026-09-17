<?php
declare(strict_types=1);

const HM_OP = 'hotel-match-tv-samo-biblio-resort-star-1971-20260918-v1';
const HM_TV_OPERATOR = 18;
const HM_SAMO_OPERATOR = '115';
const HM_DEPARTURE = 1;
const HM_TV_COUNTRY = 4;
const HM_SAMO_STATE = 5;
const HM_NIGHTS = 7;
const HM_ADULTS = 2;
const HM_MAX_TV_CALLS = 80;
const HM_MAX_CONTINUE = 12;
const HM_MAX_SAMO_PAGES = 100;
const HM_MAX_DETAIL_CALLS = 24;

function hm_json(mixed $v): string {
    return json_encode($v, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
}
function hm_write(string $path, array $v): string {
    $raw = hm_json($v)."\n";
    $f = @fopen($path, 'x+b');
    if (!$f) throw new RuntimeException('durable_create');
    try {
        if (fwrite($f,$raw)!==strlen($raw) || !fflush($f)) throw new RuntimeException('durable_write');
        if (function_exists('fsync') && !fsync($f)) throw new RuntimeException('durable_sync');
        rewind($f);
        if (stream_get_contents($f)!==$raw) throw new RuntimeException('durable_readback');
    } finally { fclose($f); }
    return hash('sha256',$raw);
}
function hm_text(mixed $v, int $max=512): string {
    return is_scalar($v) ? mb_substr(trim((string)$v),0,$max,'UTF-8') : '';
}
function hm_id(mixed $v): ?int {
    if (is_array($v)) $v=$v['id']??null;
    $n=filter_var($v,FILTER_VALIDATE_INT);
    return $n!==false && (int)$n>0 ? (int)$n : null;
}
function hm_norm(string $v): string {
    $v=mb_strtolower(trim($v),'UTF-8');
    $v=preg_replace('/[^\p{L}\p{N}]+/u',' ',$v)??$v;
    $v=trim(preg_replace('/\s+/u',' ',$v)??$v);
    return $v;
}
function hm_identity_key(string $v): string {
    $tokens=preg_split('/\s+/u',hm_norm($v),-1,PREG_SPLIT_NO_EMPTY)?:[];
    $drop=['hotel'=>1,'resort'=>1,'spa'=>1,'отель'=>1,'гостиница'=>1];
    $keep=[]; foreach($tokens as $t) if(!isset($drop[$t])) $keep[]=$t;
    return implode(' ',$keep);
}
function hm_generic(string $v): bool {
    return preg_match('/^(?:fortuna|roulette|фортуна|рулетка)(?:\s|$)/u',hm_norm($v))===1;
}
function hm_rows(array $p, array $keys=['items','results','hotels']): array {
    if (array_is_list($p)) return $p;
    foreach($keys as $k) if(is_array($p[$k]??null)) return $p[$k];
    return [];
}
function hm_dates_walk(mixed $v, array &$out): void {
    if (is_array($v)) { foreach($v as $x) hm_dates_walk($x,$out); return; }
    if (!is_scalar($v)) return;
    $s=trim((string)$v);
    foreach(['!Y-m-d','!Ymd','!d.m.Y'] as $fmt) {
        $d=DateTimeImmutable::createFromFormat($fmt,$s);
        $e=DateTimeImmutable::getLastErrors();
        if($d && ($e===false || (($e['warning_count']??0)===0 && ($e['error_count']??0)===0))) {
            $out[$d->format('Y-m-d')]=true; return;
        }
    }
}
function hm_dates(array $p): array {
    $out=[]; hm_dates_walk($p,$out); $r=array_keys($out); sort($r,SORT_STRING); return $r;
}
function hm_dict(array $rows, array $aliases): array {
    $wanted=[]; foreach($aliases as $a)$wanted[hm_norm($a)]=true;
    $found=[];
    foreach($rows as $r) {
        if(!is_array($r))continue;
        $name=hm_text($r['name']??$r['lName']??'');
        $id=hm_id($r['id']??null);
        if($id && $name!=='' && isset($wanted[hm_norm($name)])) $found[$id]=['id'=>$id,'name'=>$name];
    }
    return array_values($found);
}
function hm_star_id(array $rows,int $star): ?int {
    $hits=[];
    foreach($rows as $r) {
        if(!is_array($r))continue;
        $id=hm_id($r['id']??null); $name=hm_norm(hm_text($r['name']??$r['lName']??''));
        if(!$id||$name==='')continue;
        if(preg_match('/(?:^|\s)'.preg_quote((string)$star,'/').'\s*(?:\*|star|stars|звезд|звезды|звезда)?(?:\s|$)/u',$name))$hits[$id]=$id;
    }
    return count($hits)===1 ? array_values($hits)[0] : null;
}
function hm_url(string $v): ?string {
    $v=trim($v); if($v==='')return null;
    if(str_starts_with($v,'//'))$v='https:'.$v;
    $p=parse_url($v);
    if(!is_array($p)||!in_array(strtolower((string)($p['scheme']??'')),['https','http'],true)||empty($p['host'])||isset($p['user'])||isset($p['pass'])||strlen($v)>2048)return null;
    if (isset($p['query'])) {
        parse_str((string)$p['query'],$q);
        foreach(array_keys($q) as $k) {
            if(preg_match('/token|auth|pass|secret|session|sid|cookie|signature|api[_-]?key/i',(string)$k))return null;
        }
    }
    return $v;
}
function hm_urls_walk(mixed $v, array &$out, string $key='', int $depth=0): void {
    if($depth>5)return;
    if(is_array($v)){foreach($v as $k=>$x)hm_urls_walk($x,$out,(string)$k,$depth+1);return;}
    if(!is_string($v) || !preg_match('/(?:url|link)/i',$key))return;
    $u=hm_url($v); if($u!==null)$out[$u]=true;
}
function hm_urls(array $v): array { $o=[]; hm_urls_walk($v,$o); return array_slice(array_keys($o),0,12); }
function hm_numeric(array $r, array $keys): ?float {
    foreach($keys as $k) {
        $v=$r[$k]??null;
        if(is_numeric($v) && (float)$v>0)return (float)$v;
    }
    return null;
}
function hm_request_count(array $p): int {
    foreach(['requestCount','requestsCount','request_count'] as $k)if(isset($p[$k])&&is_numeric($p[$k]))return max(0,(int)$p[$k]);
    foreach($p as $v)if(is_array($v)){ $n=hm_request_count($v); if($n>0)return$n; }
    return 0;
}
function hm_search_id(array $p): ?int {
    foreach(['searchId','id'] as $k){$n=hm_id($p[$k]??null);if($n)return$n;}
    foreach($p as $v)if(is_array($v)){ $n=hm_search_id($v); if($n)return$n; }
    return null;
}
function hm_complete(array $p): bool {
    if((int)($p['progress']??0)>=100)return true;
    if(in_array(mb_strtolower(trim((string)($p['status']??'')),'UTF-8'),['complete','completed','done','ready'],true))return true;
    foreach($p as $v)if(is_array($v)&&hm_complete($v))return true;
    return false;
}
function hm_tv_result_rows(array $p): array {
    if(array_is_list($p))return$p;
    foreach(['hotels','results','items'] as $k)if(is_array($p[$k]??null))return$p[$k];
    return [];
}
function hm_tv_signatures(array $rows): array {
    $hotels=[];$tours=[];
    foreach($rows as $h){
        if(!is_array($h))continue;$hid=hm_id($h['id']??null);if(!$hid)continue;$hotels[$hid]=true;
        foreach((array)($h['tours']??[]) as $t)if(is_array($t)){ $tid=hm_text($t['id']??$t['tourId']??'',220); if($tid!=='')$tours[$hid.'|'.$tid]=true; }
    }
    return ['hotels'=>count($hotels),'tours'=>count($tours),'hotel_ids'=>array_keys($hotels),'tour_keys'=>array_keys($tours)];
}
function hm_continue_stop(int $requestCount,int $added): bool { return $requestCount===0 || $added===0; }
function hm_tv_merge_rows(array $acc,array $rows): array {
    $by=[];
    foreach($acc as $h) if(is_array($h) && ($id=hm_id($h['id']??null))) $by[$id]=$h;
    foreach($rows as $h){
        if(!is_array($h) || !($id=hm_id($h['id']??null))) continue;
        if(!isset($by[$id])){$by[$id]=$h;continue;}
        $base=$by[$id];
        foreach($h as $k=>$v) if($k!=='tours' && (!array_key_exists($k,$base) || $base[$k]===null || $base[$k]==='')) $base[$k]=$v;
        $seen=[];$merged=[];
        foreach(array_merge((array)($base['tours']??[]),(array)($h['tours']??[])) as $t){
            if(!is_array($t))continue;
            $tid=hm_text($t['id']??$t['tourId']??'',220);
            $key=$tid!=='' ? 'id:'.$tid : 'sha:'.hash('sha256',hm_json($t));
            if(isset($seen[$key]))continue;$seen[$key]=true;$merged[]=$t;
        }
        $base['tours']=$merged;$by[$id]=$base;
    }
    ksort($by,SORT_NUMERIC);return array_values($by);
}

function hm_tv_call(string $path,array $params,array &$counter): array {
    if(++$counter['calls']>HM_MAX_TV_CALLS)throw new RuntimeException('tv_call_budget');
    return v2_data_tv_get($path,$params);
}
function hm_tv_wait(int $sid,array &$counter): array {
    $last=[];
    for($i=0;$i<40;$i++){sleep(2);$last=hm_tv_call('/tours/search/'.$sid.'/status',['operatorStatus'=>false],$counter);if(hm_complete($last))return$last;}
    throw new RuntimeException('tv_search_timeout');
}
function hm_tv_fetch(int $sid,array &$counter): array {
    $last=null;
    foreach([10000,5000,2000,1000,500,100] as $limit){
        try{return hm_tv_result_rows(hm_tv_call('/tours/search/'.$sid,['limit'=>$limit],$counter));}
        catch(Throwable $e){$last=$e;}
    }
    throw new RuntimeException('tv_results_failed',0,$last);
}
function hm_tv_drain(array $params,array &$counter): array {
    $start=hm_tv_call('/tours/search',$params,$counter);$sid=hm_search_id($start);if(!$sid)throw new RuntimeException('tv_search_id');
    hm_tv_wait($sid,$counter);$union=hm_tv_merge_rows([],hm_tv_fetch($sid,$counter));$sig=hm_tv_signatures($union);
    $rounds=[['round'=>0,'request_count'=>null,'hotels'=>$sig['hotels'],'tours'=>$sig['tours'],'added_hotels'=>$sig['hotels'],'added_tours'=>$sig['tours']]];
    for($r=1;$r<=HM_MAX_CONTINUE;$r++){
        $before=$sig;
        $cont=hm_tv_call('/tours/search/'.$sid.'/continue',[],$counter);$requests=hm_request_count($cont);
        if($requests>0)hm_tv_wait($sid,$counter);
        $union=hm_tv_merge_rows($union,hm_tv_fetch($sid,$counter));$sig=hm_tv_signatures($union);
        $addedHotels=max(0,$sig['hotels']-$before['hotels']);$addedTours=max(0,$sig['tours']-$before['tours']);$added=$addedHotels+$addedTours;
        $rounds[]=['round'=>$r,'request_count'=>$requests,'hotels'=>$sig['hotels'],'tours'=>$sig['tours'],'added_hotels'=>$addedHotels,'added_tours'=>$addedTours];
        if(hm_continue_stop($requests,$added))return['search_id'=>$sid,'rows'=>$union,'rounds'=>$rounds,'fully_drained'=>true];
    }
    throw new RuntimeException('tv_continue_cap');
}
function hm_tv_hotels(array $rows,int $star,string $date): array {
    $out=[];
    foreach($rows as $h){
        if(!is_array($h))continue;$id=hm_id($h['id']??null);$name=hm_text($h['name']??'');
        if(!$id||$name===''||hm_generic($name))continue;
        $offers=[];
        foreach((array)($h['tours']??[]) as $t){
            if(!is_array($t))continue;$op=hm_id($t['operator']??null);if($op!==null&&$op!==HM_TV_OPERATOR)continue;
            $tid=hm_text($t['id']??$t['tourId']??'',220);$price=hm_numeric($t,['price','priceRub','priceRUB','amount']);
            $offers[]=['tour_id'=>$tid?:null,'date'=>hm_text($t['date']??$date,20),'nights'=>(int)($t['nights']??0),'price'=>$price,'currency'=>hm_text($t['currency']??'RUB',12),'fuel_charge'=>is_numeric($t['fuelCharge']??null)?(float)$t['fuelCharge']:null,'meal_id'=>hm_id($t['meal']??null),'room_id'=>hm_id($t['room']??$t['roomId']??null),'room_type'=>hm_text($t['roomType']??'',200)];
        }
        $prices=array_values(array_filter(array_map(fn($x)=>$x['price'],$offers),fn($x)=>$x!==null));sort($prices,SORT_NUMERIC);
        $out[$id]=['provider'=>'tourvisor','hotel_id'=>$id,'name'=>$name,'identity_key'=>hm_identity_key($name),'star'=>$star,'region'=>hm_text($h['region']??''),'subregion'=>hm_text($h['subRegion']??$h['subregion']??''),'urls'=>hm_urls($h),'offers'=>$offers,'min_price'=>$prices[0]??null,'first_tour_id'=>$offers[0]['tour_id']??null];
    }
    return $out;
}
function hm_samo_row(array $r,int $star,string $date): ?array {
    if((string)($r['operatorKey']??'')!==HM_SAMO_OPERATOR)return null;
    $name=hm_text($r['hotel']??'');if($name===''||hm_generic($name))return null;
    $hid=hm_text($r['hotelKey']??'',64);if(!preg_match('/^[1-9][0-9]*$/D',$hid))return null;
    $native=$r['original']['hotelKey']??null;$native=is_scalar($native)?trim((string)$native):null;
    if($native!==null&&!preg_match('/^[A-Za-z0-9_.-]{1,64}$/D',$native))$native=null;
    $price=hm_numeric($r,['price','priceRub','priceRUB','amount','cost']);
    return ['provider'=>'andromeda','hotel_id'=>$hid,'native_operator_hotel_id'=>$native,'name'=>$name,'original_name'=>hm_text($r['original']['hotel']??'',300),'identity_key'=>hm_identity_key($name),'star'=>$star,'town'=>hm_text($r['town']??'',200),'date'=>$date,'price'=>$price,'requested_currency'=>'RUB','urls'=>hm_urls($r)];
}
function hm_price_gap(?float $a,?float $b): ?array {
    if($a===null||$b===null||$a<=0||$b<=0)return null;$abs=abs($a-$b);return['absolute'=>$abs,'relative'=>$abs/max($a,$b)];
}
function hm_match(array $tv,array $samo): array {
    $ti=[];$si=[];foreach($tv as$id=>$r)$ti[$r['identity_key']][]=$id;foreach($samo as$id=>$r)$si[$r['identity_key']][]=$id;
    $out=[];
    foreach($ti as$key=>$tids){
        if($key===''||count($tids)!==1||count($si[$key]??[])!==1)continue;$tid=$tids[0];$sid=$si[$key][0];
        $out[]=['identity_key'=>$key,'tv_hotel_id'=>$tid,'samo_hotel_id'=>$sid,'samo_native_operator_hotel_id'=>$samo[$sid]['native_operator_hotel_id'],'tv_name'=>$tv[$tid]['name'],'samo_name'=>$samo[$sid]['name'],'tv_min_price'=>$tv[$tid]['min_price'],'samo_price'=>$samo[$sid]['price'],'price_gap'=>hm_price_gap($tv[$tid]['min_price'],$samo[$sid]['price']),'tv_first_tour_id'=>$tv[$tid]['first_tour_id'],'samo_urls'=>$samo[$sid]['urls']];
    }
    return $out;
}
function hm_detail_link(array $d): array {
    $link=hm_text($d['operatorLink']??'',2048);$urls=hm_urls($d);if($link!==''&&($u=hm_url($link))!==null)$urls=array_values(array_unique(array_merge([$u],$urls)));
    $native=[];
    foreach($urls as$u){$p=parse_url($u);parse_str((string)($p['query']??''),$q);foreach(['hotel','hotels','hotellist','hotelid','hotel_id']as$k)if(isset($q[$k]))foreach((array)$q[$k]as$v)foreach(preg_split('/[,;]+/',(string)$v)?:[]as$x)if(preg_match('/^[1-9][0-9]*$/D',trim($x)))$native[(int)trim($x)]=true;}
    return ['urls'=>$urls,'numeric_hotel_refs'=>array_keys($native)];
}

function hm_execute(string $root,string $opDir,string $user,string $pass): array {
    if($user===''||$pass==='')throw new RuntimeException('andromeda_credentials');
    require_once $root.'/config.php';
    require_once $opDir.'/payload/tourvisor-client-v1.php';
    require_once $opDir.'/payload/andromeda-client.php';
    require_once $opDir.'/payload/andromeda-network-transport-failure.php';
    require_once $opDir.'/payload/andromeda-transport.php';
    $reservation=json_decode(file_get_contents($opDir.'/reservation.json'),true,32,JSON_THROW_ON_ERROR);
    if(($reservation['operation']??null)!==HM_OP||($reservation['state']??null)!=='reserved_before_provider_access')throw new RuntimeException('reservation');

    $tvCounter=['calls'=>0];$andromedaCalls=0;
    $tvOps=hm_rows(hm_tv_call('/operators',['departureId'=>HM_DEPARTURE,'countryId'=>HM_TV_COUNTRY],$tvCounter),['operators','items','results']);
    $op=hm_dict($tvOps,['Библио-Глобус','Biblio Globus','Biblio-Globus']);if(count($op)!==1||$op[0]['id']!==HM_TV_OPERATOR)throw new RuntimeException('tv_operator_binding');
    $tvRegions=hm_rows(hm_tv_call('/regions',['countryId'=>HM_TV_COUNTRY],$tvCounter),['regions','items','results']);
    $region=hm_dict($tvRegions,['Анталья','Анталия','Antalya']);if(count($region)!==1)throw new RuntimeException('tv_resort_binding');
    $tvDates=hm_dates(hm_tv_call('/tours/dates',['departureId'=>HM_DEPARTURE,'countryId'=>HM_TV_COUNTRY,'onlyCharter'=>false],$tvCounter));

    $transport=new AnyTourAndromedaTransport(false);
    $catalog=new AnyTourAndromedaClient(function(string$url,array$options)use($transport,&$andromedaCalls){$andromedaCalls++;return$transport($url,$options);},true);
    $catalog->login($user,$pass);$all=$catalog->catalog('all',['TOWNFROMINC'=>HM_DEPARTURE,'STATEINC'=>HM_SAMO_STATE]);$session=$catalog->privateSession();
    $sop=hm_dict($all['OPERATORS'],['Библио-Глобус','Biblio Globus','Biblio-Globus']);if(count($sop)!==1||(string)$sop[0]['id']!==HM_SAMO_OPERATOR)throw new RuntimeException('samo_operator_binding');
    $town=hm_dict($all['TOWNTO'],['Анталья','Анталия','Antalya']);if(count($town)!==1)throw new RuntimeException('samo_resort_binding');
    $starIds=[];foreach([3,4,5]as$s){$id=hm_star_id($all['STARS'],$s);if(!$id)throw new RuntimeException('samo_star_binding_'.$s);$starIds[$s]=$id;}
    $samoDates=hm_dates($all['CHECKIN_BEG']);$common=array_values(array_intersect($tvDates,$samoDates));sort($common,SORT_STRING);
    $min=(new DateTimeImmutable('now'))->modify('+7 day')->format('Y-m-d');$max=(new DateTimeImmutable('now'))->modify('+120 day')->format('Y-m-d');
    $common=array_values(array_filter($common,fn($d)=>$d>=$min&&$d<=$max));if(!$common)throw new RuntimeException('no_common_date');$date=$common[0];$ymd=str_replace('-','',$date);

    $stars=[];$detailBudget=0;
    foreach([3,4,5]as$star){
        $tvParams=['departureId'=>HM_DEPARTURE,'countryId'=>HM_TV_COUNTRY,'dateFrom'=>$date,'dateTo'=>$date,'nightsFrom'=>HM_NIGHTS,'nightsTo'=>HM_NIGHTS,'adults'=>HM_ADULTS,'currency'=>'RUB','onlyCharter'=>false,'regionIds'=>[$region[0]['id']],'operatorIds'=>[HM_TV_OPERATOR],'hotelCategory'=>$star];
        $td=hm_tv_drain($tvParams,$tvCounter);$tv=hm_tv_hotels($td['rows'],$star,$date);

        $allSamo=[];$pagesMeta=[];$pagesCount=null;$last=0.0;
        for($page=1;$page<=HM_MAX_SAMO_PAGES;$page++){
            $wait=1.1-(microtime(true)-$last);if($wait>0)usleep((int)ceil($wait*1000000));$last=microtime(true);
            $params=['TOWNFROMINC'=>HM_DEPARTURE,'STATEINC'=>HM_SAMO_STATE,'CHECKIN_BEG'=>$ymd,'CHECKIN_END'=>$ymd,'NIGHTS_FROM'=>HM_NIGHTS,'NIGHTS_TILL'=>HM_NIGHTS,'ADULT'=>HM_ADULTS,'CHILD'=>0,'CURRENCYINC'=>643,'STARS'=>(string)$starIds[$star],'OPERATORS'=>HM_SAMO_OPERATOR,'TOWNTOINC'=>(string)$town[0]['id'],'PACKETTYPE'=>0,'PAGE'=>$page,'GROUP_BY'=>32];
            $tr=new AnyTourAndromedaTransport(true);$cl=new AnyTourAndromedaClient(function(string$url,array$options)use($tr,&$andromedaCalls){$andromedaCalls++;return$tr($url,$options);},true);$cl->restorePrivateSession($session);$reply=$cl->price($params);
            $pagesCount=$reply['PAGES_COUNT'];if($pagesCount>HM_MAX_SAMO_PAGES)throw new RuntimeException('samo_page_cap');
            foreach($reply['PRICES']as$r)if(is_array($r)&&($x=hm_samo_row($r,$star,$date))!==null)$allSamo[$x['hotel_id']]=$x;
            $pagesMeta[]=['page'=>$page,'pages_count'=>$pagesCount,'rows'=>count($reply['PRICES']),'unique_hotels'=>count($allSamo)];
            if($page>=$pagesCount)break;
        }
        if($pagesCount===null)throw new RuntimeException('samo_not_fully_drained');
        if($pagesCount===0){
            if(count($pagesMeta)!==1 || ($pagesMeta[0]['rows']??-1)!==0)throw new RuntimeException('samo_zero_page_shape');
        } elseif(count($pagesMeta)!==$pagesCount)throw new RuntimeException('samo_not_fully_drained');
        $matches=hm_match($tv,$allSamo);
        foreach($matches as&$m){
            if($detailBudget>=HM_MAX_DETAIL_CALLS||empty($m['tv_first_tour_id']))continue;
            $detail=hm_tv_call('/tours/'.rawurlencode((string)$m['tv_first_tour_id']),['currency'=>'RUB'],$tvCounter);$detailBudget++;$m['tv_detail']=hm_detail_link($detail);
        }unset($m);
        $stars[(string)$star]=['tourvisor'=>['search_id'=>$td['search_id'],'fully_drained'=>$td['fully_drained'],'continue_rounds'=>$td['rounds'],'unique_hotels'=>count($tv),'hotels'=>array_values($tv)],'andromeda'=>['pages_count'=>$pagesCount,'pages_drained'=>count($pagesMeta),'page_meta'=>$pagesMeta,'unique_hotels'=>count($allSamo),'hotels'=>array_values($allSamo)],'unique_exact_name_matches'=>count($matches),'matches'=>$matches];
    }
    return ['operation'=>HM_OP,'state'=>'completed_read_only','route'=>['departure'=>'Moscow','country'=>'Turkey','resort'=>'Antalya','nights'=>HM_NIGHTS,'adults'=>HM_ADULTS,'tv_operator'=>HM_TV_OPERATOR,'samo_operator'=>(int)HM_SAMO_OPERATOR],'selected_date'=>$date,'common_date_count'=>count($common),'common_dates_sample'=>array_slice($common,0,15),'tv_region'=>$region[0],'samo_townto'=>$town[0],'samo_star_ids'=>$starIds,'tv_calls'=>$tvCounter['calls'],'andromeda_calls'=>$andromedaCalls,'detail_calls'=>$detailBudget,'stars'=>$stars,'database_writes'=>0,'mapping_writes'=>0,'booking_calls'=>0,'lead_writes'=>0,'no_replay'=>true];
}

if(in_array('--self-test',$argv??[],true)){
    if(hm_identity_key('THE Test Hotel & SPA')!=='the test')throw new RuntimeException('norm');
    $d=[];hm_dates_walk(['2026-10-05',['date'=>'05.10.2026']],$d);if(count($d)!==1||!isset($d['2026-10-05']))throw new RuntimeException('dates');
    if(!hm_generic('Fortuna Antalya 5*')||hm_generic('Hotel Fortuna Beach'))throw new RuntimeException('generic');
    if(!hm_continue_stop(0,2)||!hm_continue_stop(5,0)||hm_continue_stop(2,3))throw new RuntimeException('continue');
    $a=[1=>['identity_key'=>'alpha beach','name'=>'Alpha Beach','min_price'=>100000,'first_tour_id'=>'t1']];
    $b=['9'=>['identity_key'=>'alpha beach','name'=>'Alpha Beach','price'=>101000,'native_operator_hotel_id'=>'55','urls'=>[]]];
    $m=hm_match($a,$b);if(count($m)!==1||$m[0]['samo_native_operator_hotel_id']!=='55')throw new RuntimeException('match');
    echo "MATCH_TV_SAMO_BIBLIO_SELFTEST_OK\n";exit(0);
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    if(($argv[1]??'')!=='--execute')throw new RuntimeException('disabled');
    $root=(string)getenv('ANYTOUR_ROOT');$opDir=(string)getenv('MATCH_OPERATION_DIR');if($root===''||$opDir===''||!is_dir($opDir))throw new RuntimeException('runtime_paths');
    $user=rtrim((string)fgets(STDIN),"\r\n");$pass=rtrim((string)fgets(STDIN),"\r\n");
    try{$result=hm_execute($root,$opDir,$user,$pass);$sha=hm_write($opDir.'/result.json',$result);hm_write($opDir.'/receipt.json',['operation'=>HM_OP,'state'=>'completed_read_only','result_sha256'=>$sha,'readback_verified'=>hash_file('sha256',$opDir.'/result.json')===$sha,'provider_accessed'=>true,'database_writes'=>0,'mapping_writes'=>0,'no_replay'=>true]);echo hm_json(['state'=>'completed_read_only','tv_calls'=>$result['tv_calls'],'andromeda_calls'=>$result['andromeda_calls']])."\n";exit(0);}
    catch(Throwable$e){$reason=preg_match('/^[A-Za-z0-9_.:-]{1,120}$/D',$e->getMessage())?$e->getMessage():'sanitized_failure';$fail=['operation'=>HM_OP,'state'=>'terminal_failed_no_replay','reason'=>$reason,'database_writes'=>0,'mapping_writes'=>0,'no_replay'=>true];$sha=hm_write($opDir.'/result.json',$fail);hm_write($opDir.'/receipt.json',['operation'=>HM_OP,'state'=>$fail['state'],'result_sha256'=>$sha,'readback_verified'=>true,'provider_accessed'=>true,'database_writes'=>0,'mapping_writes'=>0,'no_replay'=>true]);fwrite(STDERR,$reason."\n");exit(2);}
}
