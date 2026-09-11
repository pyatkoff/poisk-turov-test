<?php
declare(strict_types=1);

const HMAS_OPERATION = 'hotel-match-anex-sellability-review-1971-20260911-v2';
const HMAS_CORE8 = [1=>true,2=>true,4=>true,8=>true,9=>true,10=>true,12=>true,16=>true];

function hmas_norm($value): string {
    $value = trim((string)$value);
    if ($value === '') return '';
    $value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    $value = str_replace(['ё','Ё'], 'е', $value);
    return trim((string)preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value));
}

function hmas_country_id($value): ?int {
    $n = hmas_norm($value);
    $groups = [
        1=>['egypt','египет'], 2=>['thailand','таиланд','тайланд'],
        4=>['turkey','turkiye','türkiye','турция'], 8=>['maldives','мальдивы'],
        9=>['united arab emirates','uae','оаэ','эмираты'], 10=>['cuba','куба'],
        12=>['sri lanka','sri-lanka','шри ланка'], 16=>['vietnam','viet nam','вьетнам'],
    ];
    foreach ($groups as $id=>$names) foreach ($names as $name) if ($n === hmas_norm($name)) return $id;
    return null;
}

function hmas_dictionary_id(array $rows, array $names): int {
    $wanted = [];
    foreach ($names as $name) { $n=hmas_norm($name); if($n!=='') $wanted[$n]=true; }
    $matches = [];
    foreach (array_slice($rows,0,10000) as $row) {
        if (!is_array($row) || !preg_match('/\A[1-9][0-9]{0,7}\z/D',(string)($row['id']??''))) continue;
        foreach (['name','nameAlt','alias','currencyISO'] as $key) {
            if (is_string($row[$key]??null) && isset($wanted[hmas_norm($row[$key])])) $matches[(int)$row['id']]=true;
        }
    }
    if (count($matches)!==1) throw new RuntimeException('HMAS_DICTIONARY_NOT_UNIQUE');
    return (int)array_key_first($matches);
}

function hmas_date($value): ?string {
    if (!is_string($value)) return null;
    foreach (['Y-m-d','d.m.Y','Ymd'] as $format) {
        $date=DateTimeImmutable::createFromFormat('!'.$format,$value,new DateTimeZone('UTC'));
        if($date && $date->format($format)===$value) return $date->format('Y-m-d');
    }
    return null;
}

function hmas_calendar_dates(array $calendar, string $today): array {
    $start=hmas_date($calendar['start']??null); $valid=$calendar['valid']??null;
    if($start===null || !is_string($valid) || strlen($valid)>5000) throw new RuntimeException('HMAS_CALENDAR_INVALID');
    $startDate=new DateTimeImmutable($start,new DateTimeZone('UTC'));
    $todayDate=new DateTimeImmutable($today,new DateTimeZone('UTC'));
    $out=[];
    foreach([14,28,42] as $ahead){
        $target=$todayDate->modify('+'.$ahead.' days');
        for($extra=0;$extra<=6;$extra++){
            $candidate=$target->modify('+'.$extra.' days');
            $offset=(int)$startDate->diff($candidate)->format('%r%a');
            if($offset<0 || $offset>=strlen($valid)) continue;
            if(strpos('1235',$valid[$offset])===false) continue;
            $key=$candidate->format('Y-m-d'); $out[$key]=true; break;
        }
    }
    return array_keys($out);
}

function hmas_has_seven_nights(array $payload): bool {
    $rows=$payload['places']??($payload['nights']??$payload);
    if(!is_array($rows)) return false;
    foreach(array_slice($rows,0,1000) as $row){
        $v=is_array($row)?($row['id']??($row['nights']??null)):$row;
        if($v===7 || $v==='7') return true;
    }
    return false;
}

function hmas_error(Throwable $e): string {
    $m=$e->getMessage();
    return is_string($m) && preg_match('/\A[A-Z0-9_]{1,80}\z/D',$m) ? $m : 'HMAS_OTHER_ERROR';
}

function hmas_current_queue(PDO $db): array {
    $manual=array_fill_keys(array_map('intval',$db->query('SELECT anex_hotel_id FROM anex_hotel_decisions')->fetchAll(PDO::FETCH_COLUMN)),true);
    $existing=array_fill_keys(array_map('intval',$db->query('SELECT anex_hotel_id FROM anex_hotel_search_mappings')->fetchAll(PDO::FETCH_COLUMN)),true);
    $staging=[];
    foreach($db->query('SELECT anex_hotel_id,api_country FROM anex_hotels ORDER BY anex_hotel_id')->fetchAll(PDO::FETCH_ASSOC) as $r) $staging[(int)$r['anex_hotel_id']]=$r;
    $observed=[];
    foreach($db->query('SELECT anex_hotel_id,country_id,search_count,last_seen_utc FROM anex_search_hotel_observations ORDER BY anex_hotel_id,search_count DESC,last_seen_utc DESC')->fetchAll(PDO::FETCH_ASSOC) as $r){
        $id=(int)$r['anex_hotel_id']; if(!isset($observed[$id]))$observed[$id]=$r;
    }
    $ids=array_values(array_unique(array_merge(array_keys($staging),array_keys($observed)))); sort($ids,SORT_NUMERIC);
    $rows=[];
    foreach($ids as $id){
        $id=(int)$id; if(isset($manual[$id])||isset($existing[$id]))continue;
        $o=$observed[$id]??null; $s=$staging[$id]??null;
        $country=(int)($o['country_id']??0); if(!isset(HMAS_CORE8[$country]))$country=(int)(hmas_country_id($s['api_country']??'')??0);
        if(!isset(HMAS_CORE8[$country]))continue;
        $rows[]=['anex_hotel_id'=>$id,'country_id'=>$country,'live'=>(int)($o['search_count']??0)>0,'last_seen_utc'=>$o['last_seen_utc']??null];
    }
    if(count($rows)>1000)throw new RuntimeException('HMAS_QUEUE_LIMIT');
    return $rows;
}

function hmas_preflight(PDO $db,string $operation=HMAS_OPERATION): array {
    if($operation!==HMAS_OPERATION)throw new RuntimeException('HMAS_OPERATION_SCOPE');
    if(!class_exists('AnyTourAnexClient')||!class_exists('AnyTourAnexSearch'))throw new RuntimeException('HMAS_RUNTIME_MISSING');
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $db->exec('START TRANSACTION READ ONLY');
    try {
        $queue=hmas_current_queue($db);$live=0;$by=[];
        foreach($queue as $row){$by[(int)$row['country_id']]=true;if($row['live'])$live++;}
        $countries=(int)$db->query('SELECT COUNT(*) FROM catalog_countries WHERE id IN (1,2,4,8,9,10,12,16) AND is_active=1')->fetchColumn();
        $departure=(int)$db->query('SELECT COUNT(*) FROM catalog_departures WHERE id=1 AND is_active=1')->fetchColumn();
        if($countries!==8||$departure!==1)throw new RuntimeException('HMAS_LOCAL_SCOPE_CHANGED');
        $db->commit();
        return ['status'=>'ready','operation_id'=>$operation,'supplier_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'bookings'=>0,'queue'=>count($queue),'live_queue'=>$live,'countries'=>count($by)];
    } catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}

function hmas_review(PDO $db,string $token,string $operation=HMAS_OPERATION): array {
    if($operation!==HMAS_OPERATION)throw new RuntimeException('HMAS_OPERATION_SCOPE');
    if(!class_exists('AnyTourAnexClient')||!class_exists('AnyTourAnexSearch'))throw new RuntimeException('HMAS_RUNTIME_MISSING');
    $token=trim($token); if($token==='')throw new RuntimeException('HMAS_TOKEN_MISSING');
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $db->exec('START TRANSACTION READ ONLY');
    try {
        $queue=hmas_current_queue($db);
        $countryNames=[];
        $q=$db->query('SELECT id,name FROM catalog_countries WHERE id IN (1,2,4,8,9,10,12,16) AND is_active=1 ORDER BY id');
        foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r)$countryNames[(int)$r['id']]=(string)$r['name'];
        if(count($countryNames)!==8)throw new RuntimeException('HMAS_COUNTRY_SCOPE_CHANGED');
        $d=$db->query('SELECT id,name FROM catalog_departures WHERE id=1 AND is_active=1 LIMIT 1')->fetch(PDO::FETCH_ASSOC);
        if(!$d || trim((string)$d['name'])==='')throw new RuntimeException('HMAS_DEPARTURE_MISSING');
        $departureName=(string)$d['name'];
        $byCountry=[]; $live=0;
        foreach($queue as $row){$byCountry[$row['country_id']][]=$row['anex_hotel_id'];if($row['live'])$live++;}
        ksort($byCountry,SORT_NUMERIC);
        $today=(new DateTimeImmutable('today',new DateTimeZone('Europe/Moscow')))->format('Y-m-d');
        $sold=[]; $soldDates=[]; $countryReports=[]; $supplierCalls=0; $dateSlices=0; $priceBatches=0; $batchErrors=0;
        foreach($byCountry as $countryId=>$hotelIds){
            $report=['country_id'=>(int)$countryId,'queue'=>count($hotelIds),'dates'=>[],'status'=>'completed'];
            $metaClient=null;
            try {
                $metaClient=new AnyTourAnexClient($token);
                $towns=$metaClient->request('SearchTour_TOWNFROMS',[]);
                $departure=hmas_dictionary_id($towns,[$departureName,'Москва','Moscow']);
                $states=$metaClient->request('SearchTour_STATES',['TOWNFROMINC'=>$departure]);
                $state=hmas_dictionary_id($states,[$countryNames[$countryId]]);
                $party=['TOWNFROMINC'=>$departure,'STATEINC'=>$state,'ADULT'=>2,'CHILD'=>0];
                $calendar=$metaClient->request('SearchTour_CHECKIN',$party);
                $dates=hmas_calendar_dates($calendar,$today);
                if(!$dates){$report['status']='no_advertised_probe_dates';$countryReports[]=$report;continue;}
                foreach($dates as $date){
                    $ymd=str_replace('-','',$date);
                    $dated=$party+['CHECKIN_BEG'=>$ymd,'CHECKIN_END'=>$ymd];
                    try {
                        $currencies=$metaClient->request('SearchTour_CURRENCIES',$dated);
                        $currency=hmas_dictionary_id($currencies,['RUB','RUR','Рубль','Рубли','Руб']);
                        $nights=$metaClient->request('SearchTour_NIGHTS',$dated+['CURRENCY'=>$currency]);
                        if(!hmas_has_seven_nights($nights)){$report['dates'][]=['date'=>$date,'status'=>'seven_nights_unavailable','batches'=>0,'sold'=>0];continue;}
                    } catch(Throwable $e) {
                        $report['dates'][]=['date'=>$date,'status'=>'metadata_error','reason'=>hmas_error($e),'batches'=>0,'sold'=>0]; continue;
                    }
                    $dateSlices++; $dateSold=[]; $batches=0;
                    foreach(array_chunk($hotelIds,30) as $chunk){
                        $batches++;$priceBatches++;$priceClient=null;
                        try {
                            $priceClient=new AnyTourAnexClient($token);
                            $search=new AnyTourAnexSearch($priceClient,null,[$token]);
                            $result=$search->search(['supplier_namespace'=>'anex_online','departure_id'=>$departure,'destination_id'=>$state,'currency_id'=>$currency,
                                'checkin_begin'=>$date,'checkin_end'=>$date,'nights_from'=>7,'nights_till'=>7,'adults'=>2,'children'=>0,'hotel_ids'=>$chunk]);
                            foreach($result['offers']??[] as $offer){
                                $external=$offer['hotel']['external_id']??null;
                                if(!is_string($external)||!preg_match('/\A[1-9][0-9]{0,8}\z/D',$external))continue;
                                $id=(int)$external; if(!in_array($id,$chunk,true))continue;
                                $sold[$id]=true;$dateSold[$id]=true;$soldDates[$id][$date]=true;
                            }
                        } catch(Throwable $e) {
                            $batchErrors++; $report['dates'][]=['date'=>$date,'status'=>'price_batch_error','reason'=>hmas_error($e),'batch_index'=>$batches];
                        } finally { if($priceClient instanceof AnyTourAnexClient)$supplierCalls+=$priceClient->requestsMade(); }
                    }
                    $report['dates'][]=['date'=>$date,'status'=>'probed','batches'=>$batches,'sold'=>count($dateSold)];
                }
            } catch(Throwable $e) {
                $report['status']='country_metadata_error';$report['reason']=hmas_error($e);
            } finally { if($metaClient instanceof AnyTourAnexClient)$supplierCalls+=$metaClient->requestsMade(); }
            $countryReports[]=$report;
        }
        $soldIds=array_map('intval',array_keys($sold));sort($soldIds,SORT_NUMERIC);
        $queueIds=array_map(static fn($r)=>(int)$r['anex_hotel_id'],$queue);
        $notSeen=array_values(array_diff($queueIds,$soldIds));sort($notSeen,SORT_NUMERIC);
        $soldRows=[];
        foreach($soldIds as $id){$dates=array_keys($soldDates[$id]??[]);sort($dates,SORT_STRING);$soldRows[]=['anex_hotel_id'=>$id,'probe_dates'=>$dates,'probe_date_count'=>count($dates)];}
        $soldLive=0; $liveSet=[];foreach($queue as $r)if($r['live'])$liveSet[(int)$r['anex_hotel_id']]=true;foreach($soldIds as $id)if(isset($liveSet[$id]))$soldLive++;
        $coverage=['anex_links'=>(int)$db->query('SELECT COUNT(*) FROM anex_hotel_search_mappings')->fetchColumn(),
            'manual_decisions'=>(int)$db->query('SELECT COUNT(*) FROM anex_hotel_decisions')->fetchColumn()];
        $db->commit();
        return ['status'=>'completed','operation_id'=>$operation,'database_writes'=>0,'mapping_writes'=>0,'bookings'=>0,'search_continuations'=>0,
            'historical_operations_replayed'=>false,'probe_semantics'=>'bounded_supplier_dates_not_global_sellability','stats'=>[
                'queue'=>count($queue),'live_queue'=>$live,'countries'=>count($byCountry),'date_slices'=>$dateSlices,'price_batches'=>$priceBatches,
                'supplier_calls'=>$supplierCalls,'sold_on_probe'=>count($soldIds),'sold_live_on_probe'=>$soldLive,'not_seen_on_probed_dates'=>count($notSeen),'batch_errors'=>$batchErrors],
            'coverage'=>$coverage,'countries'=>$countryReports,'sold'=>$soldRows,'not_seen_on_probed_dates'=>$notSeen];
    } catch(Throwable $e) { if($db->inTransaction())$db->rollBack(); throw $e; }
}
