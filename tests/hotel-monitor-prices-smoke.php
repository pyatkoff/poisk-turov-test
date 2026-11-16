<?php
declare(strict_types=1);
require_once __DIR__.'/../v2/data/hotel-monitor-prices-v1.php';
function hmp_check(bool $ok,string $label): void {if(!$ok)throw new RuntimeException($label);}
require_once __DIR__.'/../v2/data/price-observer-v1.php';
// SQLite supplies a deterministic NOW() stand-in to prove the observer uses
// the database session clock, not PHP's process timezone.
$clockDb=new PDO('sqlite::memory:');
$clockDb->sqliteCreateFunction('NOW',static fn():string=>'2026-11-15 04:56:40',0);
$originalTimezone=date_default_timezone_get();
date_default_timezone_set('Pacific/Honolulu');
try {
    hmp_check(v2_price_observer_db_now($clockDb)==='2026-11-15 04:56:40','observer uses DB clock despite PHP timezone');
} finally {
    date_default_timezone_set($originalTimezone);
}

$scope=v2_hmp_request(['tourvisorHotelIds'=>['325','212'],'departureId'=>'1','dateFrom'=>'2026-11-12','dateTo'=>'2027-10-31'],'2026-11-11');
hmp_check($scope['tourvisorHotelIds']===[212,325] && $scope['nightsFrom']===5,'explicit typed scope');
foreach([
 ['tourvisorHotelIds'=>['212','212'],'departureId'=>1,'dateFrom'=>'2026-11-12','dateTo'=>'2026-11-20'],
 ['hotelIds'=>[212],'departureId'=>1,'dateFrom'=>'2026-11-12','dateTo'=>'2026-11-20'],
 ['tourvisorHotelIds'=>[212],'departureId'=>1,'dateFrom'=>'2026-11-11','dateTo'=>'2026-11-20'],
 ['tourvisorHotelIds'=>[212],'departureId'=>1,'dateFrom'=>'2026-11-31','dateTo'=>'2026-12-01'],
 ['tourvisorHotelIds'=>[212],'departureId'=>1,'dateFrom'=>'2026-11-12','dateTo'=>'2028-05-01'],
 ['tourvisorHotelIds'=>range(1,101),'departureId'=>1,'dateFrom'=>'2026-11-12','dateTo'=>'2026-11-20'],
]as$bad){try{v2_hmp_request($bad,'2026-11-11');throw new LogicException('invalid request accepted');}catch(InvalidArgumentException){}}

// Exercise the actual window/ranking SQL using SQLite, replacing only MySQL time functions.
function hmp_sqlite_time(string $sql,string $now): string {
    $sql=preg_replace_callback('/DATE_SUB\(NOW\(\),INTERVAL ([0-9]+) HOUR\)/',
        static fn(array $m):string=>"datetime(NOW(), '-".$m[1]." hours')",$sql);
    $sql=str_replace('TIMESTAMPDIFF(SECOND,h.observed_at,NOW())',
        "CAST(strftime('%s',NOW()) AS INTEGER)-CAST(strftime('%s',h.observed_at) AS INTEGER)",$sql);
    return str_replace('NOW()',"'".$now."'",$sql);
}
$db=new PDO('sqlite::memory:');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE tour_price_observations(id INTEGER,hotel_id INTEGER,departure_id INTEGER,country_id INTEGER,
 departure_date TEXT,nights INTEGER,adults INTEGER,children_count INTEGER,child_ages_signature TEXT,
 meal_id INTEGER,room_id INTEGER,room_type TEXT,operator_id INTEGER,currency TEXT,tour_id TEXT,
 observed_at TEXT,price REAL,fuel_charge REAL,search_id INTEGER,source TEXT)');
$base=[1,212,1,4,'2026-11-27',7,2,0,'',7,1,'Standard',1,'RUB','t1','2026-11-11 08:00:00',100000,3000,44,'scheduled_monitor'];
$insert=$db->prepare('INSERT INTO tour_price_observations VALUES('.implode(',',array_fill(0,count($base),'?')).')');
$insert->execute($base);
$add=static function(array $changes)use($base,$insert){$row=$base;foreach($changes as$i=>$v)$row[$i]=$v;$insert->execute($row);};
$add([0=>2,15=>'2026-11-11 09:00:00',16=>120000]); // superseded low price must disappear
$add([0=>3,11=>'Suite',10=>2,14=>'suite',15=>'2026-11-11 10:00:00',16=>200000]); // later suite cannot hide Standard
$add([0=>4,12=>2,14=>'other-operator',16=>110000]); // distinct operator survives
$add([0=>5,16=>1,19=>'user_search']);
$add([0=>6,16=>2,2=>3]);
$add([0=>7,16=>3,7=>1,8=>'7']);
$add([0=>8,16=>4,15=>'2026-11-09 21:59:59']); // more than 36 hours old
$add([0=>9,16=>5,15=>'2026-11-11 10:00:01']);
$add([0=>10,16=>6,1=>33536]); // same integer in another namespace is not inferred
$sql=hmp_sqlite_time(v2_hmp_offer_sql(1),'2026-11-11 10:00:00');
$q=$db->prepare($sql);$q->execute([212,1,'2026-11-12','2027-10-31']);$rows=$q->fetchAll(PDO::FETCH_ASSOC);
hmp_check(array_column($rows,'price')===[110000.0,120000.0,200000.0],'cheapest exact products; no stale/future/other-source/party/city leakage');
$offer=v2_hmp_offer($rows[0],'2026-11-11T10:00:00Z');
hmp_check($offer['price']===110000.0 && $offer['fuelCharge']===3000.0,'fuel not added twice');
hmp_check(!$offer['currentPriceVerified']&&!$offer['fullPackagePriceVerified']&&$offer['fuelIncluded']===null,'listing never promoted to final total');
$laterRow=$rows[0];$laterRow['observation_age_seconds']+=3600;
$again=v2_hmp_offer($laterRow,'2026-11-11T11:00:00Z');
hmp_check($offer['recordId']===$again['recordId'] && $again['ageSecondsAtExport']===$offer['ageSecondsAtExport']+3600,
    'observation identity is independent of export time and increasing source age');


// Diagnostic is a read-only count of existing rows, not an alternative price.
$diagSql=hmp_sqlite_time(v2_hmp_diagnostic_sql(1),'2026-11-11 10:00:00');
$diagQuery=$db->prepare($diagSql);
$diagQuery->execute([212,1,'2026-11-12','2027-10-31']);
$diagRow=$diagQuery->fetch(PDO::FETCH_ASSOC);
$diag=v2_hmp_diagnostic($diagRow);
hmp_check($diag['stage']==='eligible_rows_present' && $diag['counts']['eligibleRows']===4,
    'diagnostic reports existing eligible observations without altering ranking');
$missingDiag=v2_hmp_diagnostic([]);
hmp_check($missingDiag['stage']==='no_stored_rows_in_scope' && $missingDiag['counts']['storedRows']===0,
    'missing retained rows are not declared unavailable');
$diagPayload=v2_hmp_payload($scope,$rows,[],'2026-11-11 10:00:00','2026-11-11T10:00:00Z',false,[212=>$diagRow]);
hmp_check($diagPayload['items'][0]['diagnostic']['counts']['eligibleRows']===4 &&
    $diagPayload['items'][0]['offers'][0]['price']===110000.0,
    'diagnostic adds evidence without changing accepted prices');

// The real offer and diagnostic SQL must agree at 35:59:59, 36:00:00,
// 36:00:01 and the database clock. Future rows never qualify.
$boundaryCases=[
    [600001,'2026-11-09 22:00:01',129599,true],
    [600002,'2026-11-09 22:00:00',129600,false],
    [600003,'2026-11-09 21:59:59',129601,false],
    [600004,'2026-11-11 10:00:00',0,true],
    [600005,'2026-11-11 10:00:01',-1,false],
];
$boundaryQuery=$db->prepare($sql);
$boundaryDiagnostics=[];$boundaryRows=[];
foreach($boundaryCases as[$hotel,$observedAt,$age,$fresh]){
    $add([0=>$hotel,1=>$hotel,14=>'boundary-'.$hotel,15=>$observedAt]);
    $boundaryQuery->execute([$hotel,1,'2026-11-12','2027-10-31']);
    $found=$boundaryQuery->fetchAll(PDO::FETCH_ASSOC);
    hmp_check(count($found)===($fresh?1:0),'strict 36h offer boundary: '.$age);
    if($fresh){
        hmp_check((int)$found[0]['observation_age_seconds']===$age,'source age comes from database clock: '.$age);
        $boundaryRows[]=$found[0];
    }
    $diagQuery->execute([$hotel,1,'2026-11-12','2027-10-31']);
    $diagnostic=$diagQuery->fetch(PDO::FETCH_ASSOC);$boundaryDiagnostics[$hotel]=$diagnostic;
    hmp_check((int)$diagnostic['storedRows']===1 && (int)$diagnostic['scheduledRows']===1,'boundary source rows are retained');
    foreach(['freshScheduledRows','matchingPartyRows','knownMealRows','eligibleRows'] as$key)
        hmp_check((int)$diagnostic[$key]===($fresh?1:0),'strict 36h diagnostic boundary '.$key.': '.$age);
}
$boundaryScope=$scope;$boundaryScope['tourvisorHotelIds']=array_column($boundaryCases,0);
$boundaryPayload=v2_hmp_payload($boundaryScope,$boundaryRows,[],'2026-11-11 10:00:00','2026-11-11T10:00:00Z',false,$boundaryDiagnostics);
hmp_check($boundaryPayload['observationWindowHours']===36,'payload declares the observation lifetime, not collection cadence');
foreach($boundaryCases as$i=>[$hotel,$observedAt,$age,$fresh]){
    $item=$boundaryPayload['items'][$i];
    hmp_check($item['state']===($fresh?'observed':'no_fresh_observation')
        && $item['diagnostic']['stage']===($fresh?'eligible_rows_present':'no_fresh_scheduled_rows'),
        'expired and future observations remain unknown: '.$age);
}
hmp_check($boundaryPayload['items'][0]['offers'][0]['expiresAt']==='2026-11-11T10:00:01Z',
    '35:59:59 observation leaves only one second, not another full snapshot TTL');
hmp_check($boundaryPayload['items'][3]['offers'][0]['expiresAt']==='2026-11-11T10:15:00Z',
    'fresh observation snapshot still lasts at most fifteen minutes');
$afterBoundary=$db->prepare(hmp_sqlite_time(v2_hmp_offer_sql(1),'2026-11-11 10:00:01'));
$afterBoundary->execute([600001,1,'2026-11-12','2027-10-31']);
hmp_check($afterBoundary->fetchAll(PDO::FETCH_ASSOC)===[],'re-export cannot extend the original 36h observation lifetime');
foreach([129600,129601] as$expiredAge){
    $expiredRow=$boundaryRows[0];$expiredRow['observation_age_seconds']=$expiredAge;
    hmp_check(v2_hmp_offer($expiredRow,'2026-11-11T10:00:00Z')['expiresAt']==='2026-11-11T10:00:00Z',
        'expired adapter input cannot receive a positive snapshot TTL');
}

// A newly admitted 35-hour product still uses its latest exact observation;
// expired/future cheap rows cannot occupy any of the three ranked positions.
$add([0=>600101,1=>600010,14=>'older-reprice',15=>'2026-11-09 23:00:00',16=>10000]);
$add([0=>600102,1=>600010,14=>'older-reprice',15=>'2026-11-09 23:00:01',16=>60000]);
$add([0=>600103,1=>600010,14=>'last-second',15=>'2026-11-09 22:00:01',16=>90000]);
$add([0=>600104,1=>600010,14=>'exact-expiry',15=>'2026-11-09 22:00:00',16=>1]);
$add([0=>600105,1=>600010,14=>'past-expiry',15=>'2026-11-09 21:59:59',16=>2]);
$add([0=>600106,1=>600010,14=>'future',15=>'2026-11-11 10:00:01',16=>3]);
$add([0=>600107,1=>600010,14=>'current',15=>'2026-11-11 10:00:00',16=>110000]);
$boundaryQuery->execute([600010,1,'2026-11-12','2027-10-31']);
hmp_check(array_column($boundaryQuery->fetchAll(PDO::FETCH_ASSOC),'id')===[600102,600103,600107],
    '36h filtering preserves latest-exact then cheapest-three ranking');

// TV43060 from the original 14 Nov saved response: two valid breakfasts followed
// by a higher price with meal=NULL. A bad third offer must not poison the minimum.
$incidentBase=[4113155,43060,1,9,'2026-12-14',5,2,0,'',3,692456,'standard room king bed',11,'RUB',
 '11307167966163','2026-11-14 03:20:31',77819,14011,14163361220,'scheduled_monitor'];
$addIncident=static function(array $changes)use($incidentBase,$insert):void{
    $row=$incidentBase;foreach($changes as$i=>$v)$row[$i]=$v;$insert->execute($row);
};
$addIncident([]);
$addIncident([0=>4113156,10=>692459,11=>'deluxe king or twin room',14=>'11307167966451',16=>79852]);
$addIncident([0=>4113157,4=>'2026-12-10',5=>6,9=>null,11=>'standard room',12=>25,14=>'25297630343951',16=>80670,17=>0]);
$incidentSql=hmp_sqlite_time(v2_hmp_offer_sql(1),'2026-11-14 13:10:29');
$readIncident=static function(int $hotelId)use($db,$incidentSql):array{
    $q=$db->prepare($incidentSql);$q->execute([$hotelId,1,'2026-11-15','2027-10-31']);return $q->fetchAll(PDO::FETCH_ASSOC);
};
$incidentRows=$readIncident(43060);
hmp_check(array_column($incidentRows,'price')===[77819.0,79852.0] && array_column($incidentRows,'meal_id')===[3,3],
 'valid minimum survives an incomplete higher-priced offer');
$incidentOffer=v2_hmp_offer($incidentRows[0],'2026-11-14T10:10:29Z');
hmp_check($incidentOffer['recordId']==='520d1bac32e1579b4be1c02541c0edb4e727c58b055f27338207183e6126bff5',
 'actual minimum source identity is unchanged');
hmp_check($incidentOffer['price']===77819.0 && $incidentOffer['fuelCharge']===14011.0
 && $incidentOffer['expiresAt']==='2026-11-14T10:25:29Z' && $incidentOffer['ageSecondsAtExport']===35398,
 'meal filtering preserves price, separate fuel and freshness');

// Never invent meal terms for an unknown cheapest row. Filter BEFORE the top-3
// limit so invalid low prices cannot crowd out three usable retained products.
$addIncident([0=>4113158,9=>null,14=>'missing-meal-lower',16=>70000]);
foreach([0,1,6,8,10,999] as$i=>$meal)$addIncident([0=>4200000+$i,9=>$meal,14=>'unsupported-meal-'.$meal,16=>71000+$i]);
$addIncident([0=>4113159,9=>7,14=>'valid-ai-higher',16=>82000]);
$addIncident([0=>4113160,9=>9,14=>'valid-uai-fourth',16=>83000]);
$incidentRows=$readIncident(43060);
hmp_check(array_column($incidentRows,'id')===[4113155,4113156,4113159],
 'unknown lower meal terms are excluded; exactly three known products remain');
hmp_check(v2_hmp_offer($incidentRows[0],'2026-11-14T10:11:29Z')['recordId']===$incidentOffer['recordId'],
 'minimum remains the same source record after filtering and re-export');

// These are the exact supported Tourvisor IDs in the existing publisher's
// monitor_price_meals(), not an inferred mapping or every positive integer.
foreach([2,3,4,5,7,9] as$meal){
    $hotel=43062+$meal;$addIncident([0=>4300000+$meal,1=>$hotel,9=>$meal,14=>'known-meal-'.$meal]);
    $known=$readIncident($hotel);
    hmp_check(count($known)===1 && $known[0]['meal_id']===$meal,'canonical meal retained: '.$meal);
}
foreach([null,0,1,6,8,10,999] as$i=>$meal)$addIncident([0=>4400000+$i,1=>43061,9=>$meal,14=>'unknown-only-'.$i]);
$unknownRows=$readIncident(43061);
$unknownScope=$scope;$unknownScope['tourvisorHotelIds']=[43061];
$unknown=v2_hmp_payload($unknownScope,$unknownRows,[],'2026-11-14 13:10:29','2026-11-14T10:10:29Z',false);
hmp_check($unknownRows===[] && $unknown['items'][0]['state']==='no_fresh_observation' && $unknown['items'][0]['offers']===[]
 && $unknown['missingSemantics']==='unknown_not_no_tours','all incomplete observations remain unknown, not no tours');
hmp_check($unknown['supplierRequests']===0 && $unknown['writes']===0 && $unknown['automaticDispatch']===false,
 'incomplete observations cannot trigger a supplier request or write');

$small=$scope;$small['dateFrom']='2026-11-12';$small['dateTo']='2026-11-14';
$attempt=['id'=>1,'target_key'=>'monthly:one','departure_id'=>1,'hotel_ids_json'=>'[212,325]',
 'date_from'=>'2026-11-12','date_to'=>'2026-11-14','nights_from'=>5,'nights_to'=>14,'status'=>'success','finished_at'=>'2026-11-11 09:00:00'];
$complete=v2_hmp_coverage([$attempt],$small,212,'2026-11-11 10:00:00',false);
hmp_check($complete['searchWindowsComplete']&&!$complete['marketCoverageComplete'],'window completion not whole-market proof');
$failed=$attempt;$failed['id']=2;$failed['status']='failure';
hmp_check(v2_hmp_coverage([$attempt,$failed],$small,212,'2026-11-11 10:00:00',false)['completedWindowDays']===0,'new failure supersedes prior completion');
hmp_check(!v2_hmp_coverage([$attempt],$small,33536,'2026-11-11 10:00:00',false)['searchWindowsComplete'],'no numeric/name mapping');
hmp_check(!v2_hmp_coverage([$attempt],$small,212,'2026-11-12 10:00:00',false)['searchWindowsComplete'],'24h coverage expires');
hmp_check(v2_hmp_coverage([$attempt],$small,212,'2026-11-12 09:00:00',false)['searchWindowsComplete']
    && !v2_hmp_coverage([$attempt],$small,212,'2026-11-12 09:00:01',false)['searchWindowsComplete'],
    'coverage retains its existing 24h boundary independently of the 36h observation window');
hmp_check(!v2_hmp_coverage([$attempt],$small,212,'2026-11-11 10:00:00',true)['searchWindowsComplete'],'truncated read cannot claim completion');
$attempt['status']='empty';hmp_check(v2_hmp_coverage([$attempt],$small,212,'2026-11-11 10:00:00',false)['searchWindowsComplete'],'empty completed search not missing data');
if(in_array('--contract-json',$argv,true)){
    echo json_encode(v2_hmp_payload($scope,$rows,[$attempt],'2026-11-11 10:00:00','2026-11-11T10:00:00Z',false),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
}else echo "HOTEL_MONITOR_PRICES_OK exact_products=1 retained_only=1 typed_ids=1 honest_coverage=1 fuel_not_added=1 canonical_meals=1 observation_36h=1 strict_boundary=1 snapshot_ttl_15m=1 coverage_24h=1\n";
