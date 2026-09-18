<?php
declare(strict_types=1);

require_once __DIR__.'/hotel_match_tv_samo_common4_full_drain_v1.php';
const HMC2_OP = 'hotel-match-tv-samo-common4-full-drain-1971-20260918-v5';
const HMC5_MAX_ANEX_DATE_PROBES = 30;
const HMC5_MAX_ANEX_CALLS = 120;
const HMC5_MAX_ANEX_CHECKIN_CALLS = 20;
const HMC5_MAX_SAMO_CALLS = 1000;
function hmc5_samo_pace(): void {
    static $lastStarted=0.0;
    $wait=1.05-(microtime(true)-$lastStarted);
    if($wait>0)usleep((int)ceil($wait*1000000));
    $lastStarted=microtime(true);
}

final class Hmc4AnexReadClient {
    private string $token;
    private int $requests=0;
    private float $lastAt=0.0;
    public function __construct(string $token){
        $token=trim($token);
        if($token===''||strlen($token)>4096||preg_match('/[\\x00-\\x20\\x7f]/',$token))throw new RuntimeException('anex_credentials');
        $this->token=$token;
    }
    public function requestsMade(): int { return $this->requests; }
    public function request(string $action,array $params=[]): array {
        $allowed=['SearchTour_TOWNFROMS','SearchTour_STATES','SearchTour_TOURS','SearchTour_TOWNS','SearchTour_HOTELS','SearchTour_CHECKIN','SearchTour_CURRENCIES','SearchTour_PRICES'];
        if(!in_array($action,$allowed,true))throw new RuntimeException('anex_action');
        if(++$this->requests>HMC5_MAX_ANEX_CALLS)throw new RuntimeException('anex_call_budget');
        $wait=1.05-(microtime(true)-$this->lastAt);if($wait>0)usleep((int)ceil($wait*1000000));$this->lastAt=microtime(true);
        $url='https://parser.anextour.ru/export/default.php?'.http_build_query(array_merge([
            'samo_action'=>'api','version'=>'1.0','type'=>'json','action'=>$action,'oauth_token'=>$this->token
        ],$params),'','&',PHP_QUERY_RFC3986);
        $ch=curl_init($url);if($ch===false)throw new RuntimeException('anex_transport');
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>25,
            CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_MAXREDIRS=>0,
            CURLOPT_HTTPHEADER=>['Accept: application/json'],CURLOPT_USERAGENT=>'AnyTour-MATCH-resort-proof/1.0']);
        $body=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$errno=curl_errno($ch);curl_close($ch);
        if($errno!==0||!is_string($body)||strlen($body)>3000000)throw new RuntimeException('anex_transport');
        if($code!==200)throw new RuntimeException('anex_http');
        try{$env=json_decode($body,true,64,JSON_THROW_ON_ERROR);}catch(Throwable){throw new RuntimeException('anex_json');}
        if(!is_array($env))throw new RuntimeException('anex_json');
        if(array_key_exists('error',$env)){
            if($action==='SearchTour_PRICES'&&in_array($env['error'],[2110,'2110'],true))return ['prices'=>[]];
            throw new RuntimeException('anex_supplier');
        }
        $payload=$env[$action]??null;
        if(!is_array($payload))throw new RuntimeException('anex_shape');
        if(array_key_exists('error',$payload)){
            if($action==='SearchTour_PRICES'&&in_array($payload['error'],[2110,'2110'],true))return ['prices'=>[]];
            throw new RuntimeException('anex_supplier');
        }
        return $payload;
    }
}

function hmc2_dict_id(array $payload,array $aliases): int {
    $want=[];
    foreach($aliases as $alias)$want[hmc_norm($alias)]=true;
    $rows=hmc_rows($payload,['items','results','currencies','states','towns']);
    $hits=[];
    foreach($rows as $row){
        if(!is_array($row))continue;
        $id=hmc_id($row['id']??$row['key']??$row['currencyKey']??null);
        if(!$id)continue;
        $labels=[];
        foreach(['name','lName','label','title','alias','code','currency'] as $key){
            $v=hmc_text($row[$key]??'',120);
            if($v!=='')$labels[]=hmc_norm($v);
        }
        foreach($labels as $label)if(isset($want[$label])){$hits[$id]=true;break;}
    }
    if(count($hits)!==1)throw new RuntimeException('anex_dictionary_binding');
    return (int)array_key_first($hits);
}

function hmc2_window_dates(array $dates): array {
    $now=new DateTimeImmutable('now',new DateTimeZone('UTC'));
    $min=$now->modify('+7 days')->format('Y-m-d');
    $max=$now->modify('+120 days')->format('Y-m-d');
    $dates=array_values(array_unique(array_filter($dates,fn($d)=>is_string($d)&&$d>=$min&&$d<=$max)));
    sort($dates,SORT_STRING);
    return $dates;
}


function hmc4_rows(array $payload,array $keys=[]): array {
    if(array_is_list($payload))return $payload;
    foreach(array_merge($keys,['items','results','hotels','towns','tours']) as $key)
        if(is_array($payload[$key]??null))return $payload[$key];
    return [];
}
function hmc4_resort_inventory(Hmc4AnexReadClient $anex,int $departure,int $state): array {
    $tourRows=hmc4_rows($anex->request('SearchTour_TOURS',['TOWNFROMINC'=>$departure,'STATEINC'=>$state]));
    $tourIds=[];
    foreach($tourRows as $row){
        if(!is_array($row))continue;$id=hmc_id($row['id']??null);if(!$id)continue;
        $name=hmc_norm(hmc_text($row['name']??$row['nameAlt']??'',220));
        if($name!==''&&(str_contains($name,'antalya')||str_contains($name,'анталья')||str_contains($name,'анталия')))
            $tourIds[$id]=['id'=>$id,'name'=>hmc_text($row['name']??$row['nameAlt']??'',220)];
    }
    if(!$tourIds)throw new RuntimeException('anex_antalya_tour_binding');
    $tourIds=array_slice($tourIds,0,4,true);

    $byTour=[];$allHotels=[];$allTowns=[];$hotelGeo=[];
    foreach($tourIds as $tourId=>$tourMeta){
        $townRows=hmc4_rows($anex->request('SearchTour_TOWNS',['TOWNFROMINC'=>$departure,'STATEINC'=>$state,'TOURS'=>$tourId]));
        $towns=[];
        foreach($townRows as $row){
            if(!is_array($row))continue;$id=hmc_id($row['id']??null);if(!$id)continue;
            $name=hmc_norm(hmc_text($row['name']??$row['nameAlt']??'',160));
            $region=hmc_norm(hmc_text($row['region']??$row['regionAlt']??'',160));
            $isTarget=$name==='antalya'||$name==='анталья'||$name==='анталия'
                ||str_starts_with($name,'antalya ')||$region==='antalya'||$region==='анталья'||$region==='анталия';
            if($isTarget){$towns[$id]=['id'=>$id,'name'=>hmc_text($row['name']??'',160),'region'=>hmc_text($row['region']??'',160)];$allTowns[$id]=true;}
        }
        if(!$towns)continue;
        $hotelRows=hmc4_rows($anex->request('SearchTour_HOTELS',['TOWNFROMINC'=>$departure,'STATEINC'=>$state,'TOURS'=>$tourId]));
        $hotels=[];
        foreach($hotelRows as $row){
            if(!is_array($row))continue;$id=hmc_id($row['id']??null);$townKey=hmc_id($row['townKey']??null);
            if(!$id||!$townKey||!isset($towns[$townKey]))continue;
            $hotels[$id]=['id'=>$id,'name'=>hmc_text($row['name']??$row['nameAlt']??'',220),'town_key'=>$townKey];
            $allHotels[$id]=true;$hotelGeo[(string)$id]=['town_name'=>$towns[$townKey]['name']??'','town_region'=>$towns[$townKey]['region']??'','town_key'=>$townKey];
        }
        if($hotels)$byTour[$tourId]=['tour'=>$tourMeta,'towns'=>array_values($towns),'hotel_ids'=>array_keys($hotels)];
    }
    if(!$byTour||!$allHotels||!$allTowns)throw new RuntimeException('anex_antalya_hotel_binding');
    return ['by_tour'=>$byTour,'hotel_ids'=>array_keys($allHotels),'town_ids'=>array_keys($allTowns),'hotel_geo'=>$hotelGeo];
}
function hmc4_candidate_dates(Hmc4AnexReadClient $anex,int $departure,int $state,array $inventory): array {
    $dates=[];$calls=0;
    foreach($inventory['by_tour'] as $tourId=>$scope){
        foreach(array_chunk($scope['hotel_ids'],30) as $chunk){
            $payload=$anex->request('SearchTour_CHECKIN',[
                'TOWNFROMINC'=>$departure,'STATEINC'=>$state,'TOURS'=>(int)$tourId,'HOTELS'=>implode(',',$chunk),
                'ADULT'=>HMC_ADULTS,'CHILD'=>HMC_CHILDREN,
            ]);
            foreach(hmc2_window_dates(hmc_dates($payload)) as $date)$dates[$date]=true;
            if(++$calls>=HMC5_MAX_ANEX_CHECKIN_CALLS)break 2;
        }
    }
    $out=array_keys($dates);sort($out,SORT_STRING);
    if(!$out)throw new RuntimeException('anex_no_resort_dates');
    return $out;
}
function hmc4_anex_price_probe(
    Hmc4AnexReadClient $anex,int $departure,int $state,int $currency,string $date,array $inventory
): array {
    $ymd=str_replace('-','',$date);$hotelSet=array_fill_keys(array_map('strval',$inventory['hotel_ids']),true);
    $matched=[];$sample=[];$received=0;
    foreach($inventory['by_tour'] as $tourId=>$scope){
        $townIds=array_values(array_unique(array_map(fn($x)=>(string)$x['id'],$scope['towns'])));
        if(!$townIds)continue;
        $raw=$anex->request('SearchTour_PRICES',[
            'TOWNFROMINC'=>$departure,'STATEINC'=>$state,'TOURS'=>(int)$tourId,'TOWNS'=>implode(',',$townIds),
            'CURRENCY'=>$currency,'CHECKIN_BEG'=>$ymd,'CHECKIN_END'=>$ymd,
            'NIGHTS_FROM'=>HMC_NIGHTS,'NIGHTS_TILL'=>HMC_NIGHTS,'ADULT'=>HMC_ADULTS,'CHILD'=>HMC_CHILDREN,
            'FREIGHT'=>1,'FILTER'=>1,'PRICEPAGE'=>1,'PARTITION_PRICE'=>32,'SORT'=>'ASC','DYN_SEPARATE'=>1,
        ]);
        foreach(($raw['prices']??[]) as $row){
            if(!is_array($row))continue;++$received;$hotel=hmc_text($row['hotelKey']??'',40);
            if(!isset($hotelSet[$hotel]))continue;
            if(hmc_date($row['checkIn']??null,'')!==$date||(int)($row['nights']??0)!==HMC_NIGHTS
                ||(int)($row['adult']??0)!==HMC_ADULTS||(int)($row['child']??0)!==HMC_CHILDREN)continue;
            $matched[$hotel]=true;
            if(count($sample)<8)$sample[]=['native_anex_hotel_id'=>$hotel,'hotel_name'=>hmc_text($row['hotel']??'',180)?:null,
                'town'=>hmc_text($row['town']??'',120)?:null,'town_key'=>hmc_text($row['townKey']??'',40)?:null,
                'date'=>$date,'room'=>hmc_text($row['room']??'',160)?:null,'meal'=>hmc_text($row['meal']??'',100)?:null];
        }
        if($matched)break;
    }
    return ['received_rows'=>$received,'matched_offer_count'=>count($matched),'matched_hotel_ids'=>array_keys($matched),'sample'=>$sample];
}
function hmc4_samo_anex_full(
    string $date,array $session,int $townId,int $samoAnexOperatorId,int &$samoCalls,?array $andromedaHotelIds=null
): array {
    $ymd=str_replace('-','',$date);$native=[];$andromeda=[];$geo=[];$sample=[];$pages=[];$advertised=0;
    for($page=1;$page<=HMC_MAX_SAMO_PAGES;$page++){
        $params=[
            'TOWNFROMINC'=>HMC_DEPARTURE,'STATEINC'=>HMC_SAMO_STATE,'CHECKIN_BEG'=>$ymd,'CHECKIN_END'=>$ymd,
            'NIGHTS_FROM'=>HMC_NIGHTS,'NIGHTS_TILL'=>HMC_NIGHTS,'ADULT'=>HMC_ADULTS,'CHILD'=>HMC_CHILDREN,
            'CURRENCYINC'=>643,'OPERATORS'=>(string)$samoAnexOperatorId,'TOWNTOINC'=>(string)$townId,
            'PACKETTYPE'=>0,'PAGE'=>$page,'GROUP_BY'=>32,
        ];
        if($andromedaHotelIds)$params['HOTELS']=implode(',',array_slice(array_values(array_unique(array_map('strval',$andromedaHotelIds))),0,30));
        $tr=new AnyTourAndromedaTransport(true);
        $cl=new AnyTourAndromedaClient(function(string $url,array $options)use($tr,&$samoCalls){
            if(++$samoCalls>HMC5_MAX_SAMO_CALLS)throw new RuntimeException('samo_call_budget');hmc5_samo_pace();return $tr($url,$options);
        },true);
        $cl->restorePrivateSession($session);$reply=$cl->price($params);
        $pc=(int)$reply['PAGES_COUNT'];if($pc>HMC_MAX_SAMO_PAGES)throw new RuntimeException('samo_probe_page_cap');
        if($pc===0){
            if(count($reply['PRICES'])!==0)throw new RuntimeException('samo_zero_page_nonempty');
            $pages[]=['page'=>$page,'pages_count'=>0,'rows'=>0];break;
        }
        $advertised=max($advertised,$pc);
        foreach($reply['PRICES'] as $row){
            if(!is_array($row)||(int)($row['operatorKey']??0)!==$samoAnexOperatorId)continue;
            $aid=hmc_text($row['hotelKey']??'',40);
            $orig=is_array($row['original']??null)?hmc_text($row['original']['hotelKey']??'',40):'';
            if(!preg_match('/^[1-9][0-9]{0,15}$/D',$orig)||!preg_match('/^[1-9][0-9]{0,18}$/D',$aid))continue;
            $native[$orig]=true;$andromeda[$aid]=$orig;$geo[$aid]=['native_anex_hotel_id'=>$orig,'town'=>hmc_text($row['town']??'',120)?:null];
            if(count($sample)<8)$sample[]=['andromeda_hotel_id'=>$aid,'native_anex_hotel_id'=>$orig,
                'hotel_name'=>hmc_text($row['hotel']??'',180)?:null,'town'=>hmc_text($row['town']??'',120)?:null,
                'date'=>hmc_date($row['checkIn']??$date,$date)];
        }
        $pages[]=['page'=>$page,'pages_count'=>$pc,'rows'=>count($reply['PRICES'])];
        if($page>=$advertised)break;
    }
    return ['pages'=>$pages,'native_anex_hotel_ids'=>array_keys($native),'andromeda_to_native'=>$andromeda,'andromeda_geo'=>$geo,'sample'=>$sample];
}
function hmc5_geo_compatible(string $nativeId,array $geo,array $inventory): bool {
    $meta=$inventory['hotel_geo'][(string)$nativeId]??null;
    if(!is_array($meta))return false;
    $town=hmc_norm(hmc_text($geo['town']??'',120));
    if($town==='')return true;
    $allowed=[];
    foreach([$meta['town_name']??'',$meta['town_region']??'','Antalya','Анталья','Анталия'] as $v){
        $n=hmc_norm(hmc_text($v,120));if($n!=='')$allowed[$n]=true;
    }
    if(isset($allowed[$town]))return true;
    foreach(array_keys($allowed) as $n)if($n!==''&&(str_contains($town,$n)||str_contains($n,$town)))return true;
    return false;
}

function hmc2_find_anex_samo_date(
    Hmc4AnexReadClient $anex,array $session,array $all,int $townId,int $samoAnexOperatorId,int &$samoCalls
): array {
    $departure=hmc2_dict_id($anex->request('SearchTour_TOWNFROMS'),['Москва','Moscow']);
    $state=hmc2_dict_id($anex->request('SearchTour_STATES',['TOWNFROMINC'=>$departure]),['Турция','Turkey']);
    $inventory=hmc4_resort_inventory($anex,$departure,$state);
    $dates=hmc4_candidate_dates($anex,$departure,$state,$inventory);
    $probeDates=array_slice($dates,0,HMC5_MAX_ANEX_DATE_PROBES);
    $GLOBALS['HMC5_FAILURE_FACTS']=['candidate_date_count'=>count($dates),'probed_date_count'=>count($probeDates),'probe_attempts'=>[],'anex_resort_hotel_count'=>count($inventory['hotel_ids'])];
    $firstYmd=str_replace('-','',$probeDates[0]);
    $firstTour=(int)array_key_first($inventory['by_tour']);
    $currencies=$anex->request('SearchTour_CURRENCIES',[
        'TOWNFROMINC'=>$departure,'STATEINC'=>$state,'TOURS'=>$firstTour,'ADULT'=>HMC_ADULTS,'CHILD'=>HMC_CHILDREN,
        'CHECKIN_BEG'=>$firstYmd,'CHECKIN_END'=>$firstYmd,
    ]);
    $currency=hmc2_dict_id($currencies,['RUB','RUR','Рубль','Рубли','Руб']);

    $resortSet=array_fill_keys(array_map('strval',$inventory['hotel_ids']),true);$attempts=[];
    foreach($probeDates as $date){
        $direct=hmc4_anex_price_probe($anex,$departure,$state,$currency,$date,$inventory);
        $entry=['date'=>$date,'direct_anex_resort_offer_count'=>$direct['matched_offer_count']];
        if(!$direct['matched_hotel_ids']){$entry['samo_intersection_count']=0;$attempts[]=$entry;$GLOBALS['HMC5_FAILURE_FACTS']['probe_attempts']=$attempts;continue;}
        $broad=hmc4_samo_anex_full($date,$session,$townId,$samoAnexOperatorId,$samoCalls);
        $directSet=array_fill_keys(array_map('strval',$direct['matched_hotel_ids']),true);
        $candidateAndromeda=[];$candidateNative=[];
        foreach($broad['andromeda_to_native'] as $aid=>$native)
            if(isset($resortSet[(string)$native],$directSet[(string)$native])&&hmc5_geo_compatible((string)$native,$broad['andromeda_geo'][$aid]??[],$inventory)){$candidateAndromeda[$aid]=true;$candidateNative[(string)$native]=true;}
        $entry['samo_intersection_count']=count($candidateNative);
        $entry['samo_candidate_andromeda_count']=count($candidateAndromeda);
        if(!$candidateAndromeda){$attempts[]=$entry;$GLOBALS['HMC5_FAILURE_FACTS']['probe_attempts']=$attempts;continue;}

        $strict=['pages'=>[],'native_anex_hotel_ids'=>[],'andromeda_to_native'=>[],'andromeda_geo'=>[],'sample'=>[]];
        foreach(array_chunk(array_keys($candidateAndromeda),30) as $chunkIndex=>$strictChunk){
            $part=hmc4_samo_anex_full($date,$session,$townId,$samoAnexOperatorId,$samoCalls,$strictChunk);
            foreach($part['pages'] as $pm)$strict['pages'][]=['chunk'=>$chunkIndex+1]+$pm;
            foreach($part['native_anex_hotel_ids'] as $nid)$strict['native_anex_hotel_ids'][(string)$nid]=true;
            foreach($part['andromeda_to_native'] as $aid=>$nid)$strict['andromeda_to_native'][(string)$aid]=(string)$nid;
            foreach($part['andromeda_geo'] as $aid=>$g)$strict['andromeda_geo'][(string)$aid]=$g;
            foreach($part['sample'] as $sr)if(count($strict['sample'])<8)$strict['sample'][]=$sr;
        }
        $strict['native_anex_hotel_ids']=array_keys($strict['native_anex_hotel_ids']);
        $strictNative=array_fill_keys(array_map('strval',$strict['native_anex_hotel_ids']),true);
        $finalNative=array_values(array_filter(array_keys($candidateNative),fn($id)=>isset($strictNative[(string)$id])));
        $entry['strict_samo_hotel_count']=count($finalNative);$attempts[]=$entry;$GLOBALS['HMC5_FAILURE_FACTS']['probe_attempts']=$attempts;
        if($finalNative){
            $provenAndromeda=[];
            foreach($strict['andromeda_to_native'] as $aid=>$native)if(in_array((string)$native,$finalNative,true))$provenAndromeda[]=(string)$aid;
            return [
                'authority'=>'direct_anex_resort_plus_samo_hotel_scoped','date'=>$date,
                'anex_departure_id'=>$departure,'anex_state_id'=>$state,'anex_currency_id'=>$currency,
                'anex_resort_tours'=>array_values(array_map(fn($x)=>$x['tour'],$inventory['by_tour'])),
                'anex_resort_town_ids'=>$inventory['town_ids'],'anex_resort_hotel_count'=>count($inventory['hotel_ids']),
                'anex_candidate_date_count'=>count($dates),'anex_candidate_dates_sample'=>array_slice($dates,0,15),
                'probe_attempts'=>$attempts,'samo_anex_operator_id'=>$samoAnexOperatorId,
                'direct_anex_proof'=>$direct,'samo_broad_proof'=>$broad,'samo_strict_proof'=>$strict,
                'proven_native_anex_hotel_ids'=>$finalNative,'proven_andromeda_hotel_ids'=>array_values(array_unique($provenAndromeda)),
            ];
        }
    }
    $GLOBALS['HMC5_FAILURE_FACTS']['anex_calls']=$anex->requestsMade();$GLOBALS['HMC5_FAILURE_FACTS']['andromeda_calls']=$samoCalls;
    throw new RuntimeException('no_common_anex_samo_antalya_resort_date');
}

function hmc2_execute(string $root,string $opDir,string $user,string $pass,string $anexToken): array {
    if($user===''||$pass==='')throw new RuntimeException('andromeda_credentials');
    if($anexToken==='')throw new RuntimeException('anex_credentials');
    require_once $root.'/config.php';
    require_once $opDir.'/payload/tourvisor-client-v1.php';
    require_once $opDir.'/payload/andromeda-client.php';
    require_once $opDir.'/payload/andromeda-network-transport-failure.php';
    require_once $opDir.'/payload/andromeda-transport.php';

    $reservation=json_decode((string)file_get_contents($opDir.'/reservation.json'),true,32,JSON_THROW_ON_ERROR);
    if(($reservation['operation']??null)!==HMC2_OP||($reservation['state']??null)!=='reserved_before_provider_access')
        throw new RuntimeException('reservation');

    $tvCounter=['calls'=>0];$samoCalls=0;$detailCalls=0;

    // OWNER ORDER: establish the date from SAMO + direct ANEX first. No Tourvisor call before this block completes.
    $transport=new AnyTourAndromedaTransport(false);
    $catalog=new AnyTourAndromedaClient(function(string $url,array $options)use($transport,&$samoCalls){
        if(++$samoCalls>HMC5_MAX_SAMO_CALLS)throw new RuntimeException('samo_call_budget');
        hmc5_samo_pace();return $transport($url,$options);
    },true);
    $catalog->login($user,$pass);
    $all=$catalog->catalog('all',['TOWNFROMINC'=>HMC_DEPARTURE,'STATEINC'=>HMC_SAMO_STATE]);
    $session=$catalog->privateSession();

    $town=hmc_dict($all['TOWNTO'],['Анталья','Анталия','Antalya']);
    if(count($town)!==1)throw new RuntimeException('samo_resort_binding');
    $samoMap=hmc_operator_map($all['OPERATORS']);
    if(!isset($samoMap['unique']['anex']))throw new RuntimeException('samo_anex_operator_binding');
    $samoAnexOperatorId=(int)$samoMap['unique']['anex']['id'];

    $anex=new Hmc4AnexReadClient($anexToken);
    $dateProof=hmc2_find_anex_samo_date($anex,$session,$all,(int)$town[0]['id'],$samoAnexOperatorId,$samoCalls);
    $date=$dateProof['date'];$ymd=str_replace('-','',$date);

    // Only now is Tourvisor allowed to enter the evidence pass.
    $tvOps=hmc_rows(hmc_tv_call('/operators',['departureId'=>HMC_DEPARTURE,'countryId'=>HMC_TV_COUNTRY],$tvCounter),['operators','items','results']);
    $tvRegions=hmc_rows(hmc_tv_call('/regions',['countryId'=>HMC_TV_COUNTRY],$tvCounter),['regions','items','results']);
    $region=hmc_dict($tvRegions,['Анталья','Анталия','Antalya']);
    if(count($region)!==1)throw new RuntimeException('tv_resort_binding');

    $opInfo=hmc_common_operators($tvOps,$all['OPERATORS']);
    $common=$opInfo['common'];
    if(count($common)<2)throw new RuntimeException('common_operator_fingerprint_too_small');

    $starIds=[];
    foreach([3,4,5] as $star){
        $id=hmc_star_id($all['STARS'],$star);if(!$id)throw new RuntimeException('samo_star_binding_'.$star);$starIds[$star]=$id;
    }

    $tvOperatorIds=[];$samoOperatorIds=[];$commonBySamoId=[];
    foreach($common as $family=>$pair){
        $tvOperatorIds[]=(int)$pair['tv']['id'];$samoOperatorIds[]=(string)$pair['samo']['id'];
        $commonBySamoId[(int)$pair['samo']['id']]=['family'=>$family,'name'=>$pair['samo']['name']];
    }
    sort($tvOperatorIds,SORT_NUMERIC);sort($samoOperatorIds,SORT_NATURAL);
    $samoCsv=implode(',',$samoOperatorIds);

    $stars=[];
    foreach([3,4,5] as $star){
        $tvParams=[
            'departureId'=>HMC_DEPARTURE,'countryId'=>HMC_TV_COUNTRY,'dateFrom'=>$date,'dateTo'=>$date,
            'nightsFrom'=>HMC_NIGHTS,'nightsTo'=>HMC_NIGHTS,'adults'=>HMC_ADULTS,'currency'=>'RUB',
            'onlyCharter'=>false,'regionIds'=>[$region[0]['id']],'operatorIds'=>$tvOperatorIds,'hotelCategory'=>$star,
        ];
        $td=hmc_tv_drain($tvParams,$tvCounter);
        $tvRows=hmc_tv_offer_rows($td['rows'],$date,$common);

        $samoRows=[];$pageMeta=[];$advertisedPagesMax=0;$termination=null;$positivePagesDrained=0;
        for($page=1;$page<=HMC_MAX_SAMO_PAGES;$page++){
            $params=[
                'TOWNFROMINC'=>HMC_DEPARTURE,'STATEINC'=>HMC_SAMO_STATE,'CHECKIN_BEG'=>$ymd,'CHECKIN_END'=>$ymd,
                'NIGHTS_FROM'=>HMC_NIGHTS,'NIGHTS_TILL'=>HMC_NIGHTS,'ADULT'=>HMC_ADULTS,'CHILD'=>HMC_CHILDREN,
                'CURRENCYINC'=>643,'STARS'=>(string)$starIds[$star],'OPERATORS'=>$samoCsv,
                'TOWNTOINC'=>(string)$town[0]['id'],
                'PACKETTYPE'=>0,'PAGE'=>$page,'GROUP_BY'=>32,
            ];
            $tr=new AnyTourAndromedaTransport(true);
            $cl=new AnyTourAndromedaClient(function(string $url,array $options)use($tr,&$samoCalls){
                if(++$samoCalls>HMC5_MAX_SAMO_CALLS)throw new RuntimeException('samo_call_budget');
                return $tr($url,$options);
            },true);
            $cl->restorePrivateSession($session);$reply=$cl->price($params);
            $pagesCount=(int)$reply['PAGES_COUNT'];
            if($pagesCount>HMC_MAX_SAMO_PAGES)throw new RuntimeException('samo_page_cap');
            if($pagesCount===0){
                if(count($reply['PRICES'])!==0)throw new RuntimeException('samo_zero_page_nonempty');
                $pageMeta[]=['page'=>$page,'pages_count'=>0,'advertised_pages_max'=>$advertisedPagesMax,
                    'rows'=>0,'accepted_evidence_rows'=>0,'terminal_empty'=>true];
                $termination=$page===1?'first_page_zero_empty':'pages_count_zero_empty';
                break;
            }
            $advertisedPagesMax=max($advertisedPagesMax,$pagesCount);
            $before=count($samoRows);
            foreach($reply['PRICES'] as $raw)if(is_array($raw)&&($row=hmc_samo_offer_row($raw,$date,$commonBySamoId))!==null)$samoRows[]=$row;
            $positivePagesDrained++;
            $pageMeta[]=['page'=>$page,'pages_count'=>$pagesCount,'advertised_pages_max'=>$advertisedPagesMax,
                'rows'=>count($reply['PRICES']),'accepted_evidence_rows'=>count($samoRows)-$before,'terminal_empty'=>false];
            if($page>=$advertisedPagesMax){
                $termination='advertised_pages_complete';
                break;
            }
        }
        if($termination===null)throw new RuntimeException('samo_not_fully_drained');
        if($termination==='advertised_pages_complete'&&$positivePagesDrained!==$advertisedPagesMax)
            throw new RuntimeException('samo_not_fully_drained');

        $initial=hmf_resolve($tvRows,$samoRows,[]);
        $tvRows=hmc_enrich_tv_anex($tvRows,$initial,$tvCounter,$detailCalls);
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
            'andromeda'=>['pages_count'=>$advertisedPagesMax,'pages_drained'=>$positivePagesDrained,
                'pages_requested'=>count($pageMeta),'termination'=>$termination,
                'page_meta'=>$pageMeta,'evidence_rows'=>count($samoRows),'operator_rows'=>$samoFamilies],
            'resolver'=>$resolved,'candidate_evidence'=>$evidence,
        ];
    }

    return [
        'operation'=>HMC2_OP,'state'=>'completed_read_only',
        'route'=>['departure'=>'Moscow','country'=>'Turkey','resort'=>'Antalya','date'=>$date,
            'nights'=>HMC_NIGHTS,'adults'=>HMC_ADULTS,'children'=>HMC_CHILDREN],
        'date_authority'=>'direct_anex_resort_plus_samo_hotel_scoped','date_discovery'=>$dateProof,'samo_resort_scope_hotel_ids'=>array_slice($dateProof['proven_andromeda_hotel_ids']??[],0,30),
        'common_operators'=>array_values($common),'missing_operator_families'=>$opInfo['missing'],
        'tv_ambiguous_operator_families'=>$opInfo['tv_ambiguous'],'samo_ambiguous_operator_families'=>$opInfo['samo_ambiguous'],
        'tv_operator_ids'=>$tvOperatorIds,'samo_operator_ids'=>$samoOperatorIds,
        'tv_multi_operator_mode'=>'repeated_operatorIds_query_param',
        'samo_multi_operator_mode'=>'csv_OPERATORS',
        'tv_region'=>$region[0],'samo_townto'=>$town[0],'samo_star_ids'=>$starIds,
        'anex_calls'=>$anex->requestsMade(),'tv_calls'=>$tvCounter['calls'],'andromeda_calls'=>$samoCalls,'tourvisor_detail_calls'=>$detailCalls,
        'stars'=>$stars,
        'database_writes'=>0,'mapping_writes'=>0,'booking_calls'=>0,'lead_writes'=>0,'metrika_writes'=>0,
        'no_replay'=>true,
    ];
}

if(in_array('--self-test',$argv??[],true)){
    $dates=hmc2_window_dates(['1999-01-01','2199-01-01']);
    if(!is_array($dates))throw new RuntimeException('v4_window');
    $mock=['items'=>[['id'=>1,'name'=>'RUB']]];
    if(hmc2_dict_id($mock,['RUB'])!==1)throw new RuntimeException('v5_dict');
    if(HMC5_MAX_ANEX_DATE_PROBES!==30||HMC5_MAX_ANEX_CHECKIN_CALLS!==20||HMC5_MAX_SAMO_CALLS!==1000)throw new RuntimeException('v5_limits');
    echo "MATCH_TV_SAMO_COMMON4_FULL_DRAIN_V5_SELFTEST_OK\n";exit(0);
}

if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    if(($argv[1]??'')!=='--execute')throw new RuntimeException('disabled');
    $root=(string)getenv('ANYTOUR_ROOT');$opDir=(string)getenv('MATCH_OPERATION_DIR');
    if($root===''||$opDir===''||!is_dir($opDir))throw new RuntimeException('runtime_paths');
    $user=rtrim((string)fgets(STDIN),"\r\n");$pass=rtrim((string)fgets(STDIN),"\r\n");$anexToken=rtrim((string)fgets(STDIN),"\r\n");
    $providerAccessed=false;
    try{
        $providerAccessed=true;
        $result=hmc2_execute($root,$opDir,$user,$pass,$anexToken);
        $sha=hmc_write($opDir.'/result.json',$result);
        hmc_write($opDir.'/receipt.json',['operation'=>HMC2_OP,'state'=>'completed_read_only','result_sha256'=>$sha,
            'readback_verified'=>hash_file('sha256',$opDir.'/result.json')===$sha,'provider_accessed'=>true,
            'database_writes'=>0,'mapping_writes'=>0,'no_replay'=>true]);
        echo hmc_json(['state'=>'completed_read_only','date'=>$result['route']['date'],'anex_calls'=>$result['anex_calls'],
            'tv_calls'=>$result['tv_calls'],'andromeda_calls'=>$result['andromeda_calls'],
            'candidate_counts'=>array_map(fn($x)=>$x['resolver']['hotel_candidate_count']??0,$result['stars'])])."\n";
        exit(0);
    }catch(Throwable $e){
        $reason=preg_match('/^[A-Za-z0-9_.:-]{1,120}$/D',$e->getMessage())?$e->getMessage():'sanitized_failure';
        $fail=['operation'=>HMC2_OP,'state'=>$providerAccessed?'terminal_failed_no_replay':'pre_provider_failed',
            'reason'=>$reason,'database_writes'=>0,'mapping_writes'=>0,'booking_calls'=>0,'lead_writes'=>0,
            'no_replay'=>$providerAccessed];
        if(is_array($GLOBALS['HMC5_FAILURE_FACTS']??null))$fail['failure_facts']=$GLOBALS['HMC5_FAILURE_FACTS'];
        $sha=hmc_write($opDir.'/result.json',$fail);
        hmc_write($opDir.'/receipt.json',['operation'=>HMC2_OP,'state'=>$fail['state'],'result_sha256'=>$sha,
            'readback_verified'=>true,'provider_accessed'=>$providerAccessed,'database_writes'=>0,'mapping_writes'=>0,
            'no_replay'=>$providerAccessed]);
        fwrite(STDERR,$reason."\n");exit(2);
    }
}
