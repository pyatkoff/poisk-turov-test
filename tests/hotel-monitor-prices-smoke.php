<?php
declare(strict_types=1);
require_once __DIR__.'/../v2/data/hotel-monitor-prices-v1.php';
function hmp_check(bool $ok,string $label): void {if(!$ok)throw new RuntimeException($label);}
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
$add([0=>8,16=>4,15=>'2026-11-10 09:59:59']);
$add([0=>9,16=>5,15=>'2026-11-11 10:00:01']);
$add([0=>10,16=>6,1=>33536]); // same integer in another namespace is not inferred
$sql=str_replace(['DATE_SUB(NOW(),INTERVAL 24 HOUR)','TIMESTAMPDIFF(SECOND,h.observed_at,NOW())','NOW()'],
 ["'2026-11-10 10:00:00'",'1',"'2026-11-11 10:00:00'"],v2_hmp_offer_sql(1));
$q=$db->prepare($sql);$q->execute([212,1,'2026-11-12','2027-10-31']);$rows=$q->fetchAll(PDO::FETCH_ASSOC);
hmp_check(array_column($rows,'price')===[110000.0,120000.0,200000.0],'cheapest exact products; no stale/future/other-source/party/city leakage');
$offer=v2_hmp_offer($rows[0],'2026-11-11T10:00:00Z');
hmp_check($offer['price']===110000.0 && $offer['fuelCharge']===3000.0,'fuel not added twice');
hmp_check(!$offer['currentPriceVerified']&&!$offer['fullPackagePriceVerified']&&$offer['fuelIncluded']===null,'listing never promoted to final total');
$again=v2_hmp_offer($rows[0],'2026-11-11T11:00:00Z');
hmp_check($offer['recordId']===$again['recordId'],'stable observation identity');

$small=$scope;$small['dateFrom']='2026-11-12';$small['dateTo']='2026-11-14';
$attempt=['id'=>1,'target_key'=>'monthly:one','departure_id'=>1,'hotel_ids_json'=>'[212,325]',
 'date_from'=>'2026-11-12','date_to'=>'2026-11-14','nights_from'=>5,'nights_to'=>14,'status'=>'success','finished_at'=>'2026-11-11 09:00:00'];
$complete=v2_hmp_coverage([$attempt],$small,212,'2026-11-11 10:00:00',false);
hmp_check($complete['searchWindowsComplete']&&!$complete['marketCoverageComplete'],'window completion not whole-market proof');
$failed=$attempt;$failed['id']=2;$failed['status']='failure';
hmp_check(v2_hmp_coverage([$attempt,$failed],$small,212,'2026-11-11 10:00:00',false)['completedWindowDays']===0,'new failure supersedes prior completion');
hmp_check(!v2_hmp_coverage([$attempt],$small,33536,'2026-11-11 10:00:00',false)['searchWindowsComplete'],'no numeric/name mapping');
hmp_check(!v2_hmp_coverage([$attempt],$small,212,'2026-11-12 10:00:00',false)['searchWindowsComplete'],'24h coverage expires');
hmp_check(!v2_hmp_coverage([$attempt],$small,212,'2026-11-11 10:00:00',true)['searchWindowsComplete'],'truncated read cannot claim completion');
$attempt['status']='empty';hmp_check(v2_hmp_coverage([$attempt],$small,212,'2026-11-11 10:00:00',false)['searchWindowsComplete'],'empty completed search not missing data');
if(in_array('--contract-json',$argv,true)){
    echo json_encode(v2_hmp_payload($scope,$rows,[$attempt],'2026-11-11 10:00:00','2026-11-11T10:00:00Z',false),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
}else echo "HOTEL_MONITOR_PRICES_OK exact_products=1 retained_only=1 typed_ids=1 honest_coverage=1 fuel_not_added=1\n";
