<?php
declare(strict_types=1);

require_once __DIR__.'/hotel_match_tv_samo_common4_full_drain_v1.php';
require_once __DIR__.'/anex-client.php';

const HMC2_OP = 'hotel-match-tv-samo-common4-full-drain-1971-20260918-v3';
const HMC2_MAX_ANEX_DATE_PROBES = 8;

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

function hmc2_samo_anex_probe(
    string $date,
    array $session,
    int $townId,
    int $samoAnexOperatorId,
    int &$samoCalls
): array {
    $ymd=str_replace('-','',$date);
    $tr=new AnyTourAndromedaTransport(true);
    $cl=new AnyTourAndromedaClient(function(string $url,array $options)use($tr,&$samoCalls){
        if(++$samoCalls>HMC_MAX_SAMO_CALLS)throw new RuntimeException('samo_call_budget');
        return $tr($url,$options);
    },true);
    $cl->restorePrivateSession($session);
    $reply=$cl->price([
        'TOWNFROMINC'=>HMC_DEPARTURE,
        'STATEINC'=>HMC_SAMO_STATE,
        'CHECKIN_BEG'=>$ymd,
        'CHECKIN_END'=>$ymd,
        'NIGHTS_FROM'=>HMC_NIGHTS,
        'NIGHTS_TILL'=>HMC_NIGHTS,
        'ADULT'=>HMC_ADULTS,
        'CHILD'=>HMC_CHILDREN,
        'CURRENCYINC'=>643,
        'OPERATORS'=>(string)$samoAnexOperatorId,
        'TOWNTOINC'=>(string)$townId,
        'PACKETTYPE'=>0,
        'PAGE'=>1,
        'GROUP_BY'=>32,
    ]);
    $ids=[];
    $samples=[];
    foreach($reply['PRICES'] as $row){
        if(!is_array($row)||(int)($row['operatorKey']??0)!==$samoAnexOperatorId)continue;
        $orig=is_array($row['original']??null)?hmc_text($row['original']['hotelKey']??'',40):'';
        if(!preg_match('/^[1-9][0-9]{0,15}$/D',$orig))continue;
        $ids[$orig]=true;
        if(count($samples)<5)$samples[]=[
            'andromeda_hotel_id'=>hmc_text($row['hotelKey']??'',40)?:null,
            'native_anex_hotel_id'=>$orig,
            'hotel_name'=>hmc_text($row['hotel']??'',180)?:null,
            'date'=>hmc_date($row['checkIn']??$date,$date),
        ];
    }
    return [
        'pages_count'=>$reply['PAGES_COUNT'],
        'page1_rows'=>count($reply['PRICES']),
        'native_anex_hotel_ids'=>array_slice(array_keys($ids),0,30),
        'sample'=>$samples,
    ];
}

function hmc2_anex_price_probe(
    AnyTourAnexClient $anex,
    int $departure,
    int $state,
    int $currency,
    string $date,
    array $nativeHotelIds
): array {
    $ids=[];
    foreach($nativeHotelIds as $id){
        $s=(string)$id;
        if(preg_match('/^[1-9][0-9]{0,7}$/D',$s))$ids[$s]=true;
        if(count($ids)>=30)break;
    }
    if(!$ids)return ['matched_offer_count'=>0,'matched_hotel_ids'=>[],'sample'=>[]];
    $ymd=str_replace('-','',$date);
    $raw=$anex->request('SearchTour_PRICES',[
        'TOWNFROMINC'=>$departure,
        'STATEINC'=>$state,
        'CURRENCY'=>$currency,
        'CHECKIN_BEG'=>$ymd,
        'CHECKIN_END'=>$ymd,
        'NIGHTS_FROM'=>HMC_NIGHTS,
        'NIGHTS_TILL'=>HMC_NIGHTS,
        'ADULT'=>HMC_ADULTS,
        'CHILD'=>HMC_CHILDREN,
        'HOTELS'=>implode(',',array_keys($ids)),
        'FREIGHT'=>1,
        'FILTER'=>1,
        'PRICEPAGE'=>1,
        'PARTITION_PRICE'=>32,
        'SORT'=>'ASC',
        'DYN_SEPARATE'=>1,
    ]);
    $matched=[];$sample=[];$count=0;
    foreach(($raw['prices']??[]) as $row){
        if(!is_array($row))continue;
        $hotel=hmc_text($row['hotelKey']??'',40);
        if(!isset($ids[$hotel]))continue;
        if(hmc_date($row['checkIn']??null,'')!==$date)continue;
        if((int)($row['nights']??0)!==HMC_NIGHTS||(int)($row['adult']??0)!==HMC_ADULTS||(int)($row['child']??0)!==HMC_CHILDREN)continue;
        ++$count;$matched[$hotel]=true;
        if(count($sample)<5)$sample[]=[
            'native_anex_hotel_id'=>$hotel,
            'hotel_name'=>hmc_text($row['hotel']??'',180)?:null,
            'town'=>hmc_text($row['town']??'',120)?:null,
            'date'=>$date,
            'room'=>hmc_text($row['room']??'',160)?:null,
            'meal'=>hmc_text($row['meal']??'',100)?:null,
        ];
    }
    return ['matched_offer_count'=>$count,'matched_hotel_ids'=>array_keys($matched),'sample'=>$sample];
}

function hmc2_find_anex_samo_date(
    AnyTourAnexClient $anex,
    array $session,
    array $all,
    int $townId,
    int $samoAnexOperatorId,
    int &$samoCalls
): array {
    $departure=hmc2_dict_id($anex->request('SearchTour_TOWNFROMS'),['Москва','Moscow']);
    $state=hmc2_dict_id($anex->request('SearchTour_STATES',['TOWNFROMINC'=>$departure]),['Турция','Turkey']);
    $checkin=$anex->request('SearchTour_CHECKIN',[
        'TOWNFROMINC'=>$departure,'STATEINC'=>$state,'ADULT'=>HMC_ADULTS,'CHILD'=>HMC_CHILDREN,
    ]);
    $dates=hmc2_window_dates(hmc_dates($checkin));
    if(!$dates)throw new RuntimeException('anex_no_candidate_dates');

    $probeDates=array_slice($dates,0,HMC2_MAX_ANEX_DATE_PROBES);
    $firstYmd=str_replace('-','',$probeDates[0]);
    $currencies=$anex->request('SearchTour_CURRENCIES',[
        'TOWNFROMINC'=>$departure,'STATEINC'=>$state,'ADULT'=>HMC_ADULTS,'CHILD'=>HMC_CHILDREN,
        'CHECKIN_BEG'=>$firstYmd,'CHECKIN_END'=>$firstYmd,
    ]);
    $currency=hmc2_dict_id($currencies,['RUB','RUR','Рубль','Рубли','Руб']);

    $attempts=[];
    foreach($probeDates as $date){
        $samo=hmc2_samo_anex_probe($date,$session,$townId,$samoAnexOperatorId,$samoCalls);
        $entry=['date'=>$date,'samo_page1_rows'=>$samo['page1_rows'],
            'samo_native_anex_hotel_count'=>count($samo['native_anex_hotel_ids'])];
        if(!$samo['native_anex_hotel_ids']){
            $entry['direct_anex_offer_count']=0;$attempts[]=$entry;continue;
        }
        $direct=hmc2_anex_price_probe($anex,$departure,$state,$currency,$date,$samo['native_anex_hotel_ids']);
        $entry['direct_anex_offer_count']=$direct['matched_offer_count'];
        $entry['direct_anex_hotel_count']=count($direct['matched_hotel_ids']);
        $attempts[]=$entry;
        if($direct['matched_offer_count']>0){
            return [
                'authority'=>'direct_anex_plus_samo',
                'date'=>$date,
                'anex_departure_id'=>$departure,
                'anex_state_id'=>$state,
                'anex_currency_id'=>$currency,
                'anex_candidate_date_count'=>count($dates),
                'anex_candidate_dates_sample'=>array_slice($dates,0,15),
                'probe_attempts'=>$attempts,
                'samo_anex_operator_id'=>$samoAnexOperatorId,
                'samo_proof'=>$samo,
                'direct_anex_proof'=>$direct,
            ];
        }
    }
    throw new RuntimeException('no_common_anex_samo_antalia_date');
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
        if(++$samoCalls>HMC_MAX_SAMO_CALLS)throw new RuntimeException('samo_call_budget');
        return $transport($url,$options);
    },true);
    $catalog->login($user,$pass);
    $all=$catalog->catalog('all',['TOWNFROMINC'=>HMC_DEPARTURE,'STATEINC'=>HMC_SAMO_STATE]);
    $session=$catalog->privateSession();

    $town=hmc_dict($all['TOWNTO'],['Анталья','Анталия','Antalya']);
    if(count($town)!==1)throw new RuntimeException('samo_resort_binding');
    $samoMap=hmc_operator_map($all['OPERATORS']);
    if(!isset($samoMap['unique']['anex']))throw new RuntimeException('samo_anex_operator_binding');
    $samoAnexOperatorId=(int)$samoMap['unique']['anex']['id'];

    $anex=new AnyTourAnexClient($anexToken);
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
                'TOWNTOINC'=>(string)$town[0]['id'],'PACKETTYPE'=>0,'PAGE'=>$page,'GROUP_BY'=>32,
            ];
            $tr=new AnyTourAndromedaTransport(true);
            $cl=new AnyTourAndromedaClient(function(string $url,array $options)use($tr,&$samoCalls){
                if(++$samoCalls>HMC_MAX_SAMO_CALLS)throw new RuntimeException('samo_call_budget');
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
        'date_authority'=>'direct_anex_plus_samo','date_discovery'=>$dateProof,
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
    if(!is_array($dates))throw new RuntimeException('v3_window');
    $mock=['items'=>[['id'=>1,'name'=>'RUB']]];
    if(hmc2_dict_id($mock,['RUB'])!==1)throw new RuntimeException('v3_dict');
    echo "MATCH_TV_SAMO_COMMON4_FULL_DRAIN_V3_SELFTEST_OK\n";exit(0);
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
        $sha=hmc_write($opDir.'/result.json',$fail);
        hmc_write($opDir.'/receipt.json',['operation'=>HMC2_OP,'state'=>$fail['state'],'result_sha256'=>$sha,
            'readback_verified'=>true,'provider_accessed'=>$providerAccessed,'database_writes'=>0,'mapping_writes'=>0,
            'no_replay'=>$providerAccessed]);
        fwrite(STDERR,$reason."\n");exit(2);
    }
}
