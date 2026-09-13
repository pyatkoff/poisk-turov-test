<?php
declare(strict_types=1);
$root=$argv[1]??'';
if(!is_dir($root))throw new RuntimeException('runtime root required');
require $root.'/v2/api-andromeda-search3-preview.php';
$pdo=new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE catalog_departures(id INTEGER PRIMARY KEY,name TEXT,is_active INTEGER); CREATE TABLE catalog_hotels(id INTEGER PRIMARY KEY,name TEXT,country_id INTEGER,is_active INTEGER); CREATE TABLE andromeda_hotel_identities(local_hotel_id INTEGER,external_hotel_id TEXT,supplier_namespace TEXT,decision_status TEXT)');
$pdo->exec("INSERT INTO catalog_departures VALUES(1,'Moscow',1)");
$saved=[
 'local_country_id'=>1,
 'townfrom'=>['payload'=>['TOWNFROM'=>[['id'=>1,'name'=>'Moscow']]]],
 'all'=>['params'=>['STATEINC'=>3],'payload'=>[
   'HOTELS'=>[], 'OPERATORS'=>[],
   'MEAL'=>[
     ['id'=>8,'name'=>'OB','alias'=>'Без питания'],['id'=>1,'name'=>'BB','alias'=>'Завтрак'],
     ['id'=>3,'name'=>'HB','alias'=>'Завтрак и ужин'],['id'=>4,'name'=>'FB','alias'=>'Трех разовое'],
     ['id'=>5,'name'=>'AI','alias'=>'Все включено'],['id'=>7,'name'=>'UAI','alias'=>'Ультра все включено'],
     ['id'=>2000000011,'name'=>'FBT','alias'=>'Full Board Treatment']
   ],
   'STARS'=>[
     ['id'=>2,'name'=>'2*'],['id'=>3,'name'=>'3*'],['id'=>4,'name'=>'4*'],['id'=>5,'name'=>'5*'],
     ['id'=>16,'name'=>'Apts'],['id'=>2000000011,'name'=>'Villa']
   ]
 ]]
];
$base=['generation'=>1,'params'=>[
 'countryId'=>'1','departureId'=>'1','dateFrom'=>gmdate('Y-m-d',time()+86400),'dateTo'=>gmdate('Y-m-d',time()+86400),
 'nightsFrom'=>7,'nightsTo'=>7,'adults'=>2,'childs'=>[],'meal'=>'','hotelCategory'=>'','hotelIds'=>[],'regionIds'=>[],'subregionIds'=>[],'currency'=>'RUB'
]];
$cases=['2'=>'8','3'=>'1','4'=>'3','5'=>'4','7'=>'5,7','9'=>'7'];
foreach($cases as $meal=>$expected){
 $request=$base;$request['params']['meal']=$meal;
 $params=anytour_andromeda_search3_params($request,$pdo,$saved);
 if(($params['MEAL']??null)!==$expected)throw new RuntimeException("meal $meal not forwarded: ".json_encode($params));
 AnyTourAndromedaClient::validatePriceParams($params);
}
foreach(['2'=>'2,3,4,5','3'=>'3,4,5','4'=>'4,5','5'=>'5'] as $stars=>$expected){
 $request=$base;$request['params']['hotelCategory']=$stars;
 $params=anytour_andromeda_search3_params($request,$pdo,$saved);
 if(($params['STARS']??null)!==$expected)throw new RuntimeException("stars $stars not forwarded: ".json_encode($params));
 AnyTourAndromedaClient::validatePriceParams($params);
}
$request=$base;$request['params']['meal']='7';$request['params']['hotelCategory']='5';
$params=anytour_andromeda_search3_params($request,$pdo,$saved);
if(($params['MEAL']??null)!=='5,7'||($params['STARS']??null)!=='5'||($params['GROUP_BY']??null)!==32)
 throw new RuntimeException('5-star AI request does not reach supplier criteria');
if(isset(anytour_andromeda_search3_params($base,$pdo,$saved)['MEAL'])||isset(anytour_andromeda_search3_params($base,$pdo,$saved)['STARS']))
 throw new RuntimeException('empty filters unexpectedly narrowed supplier request');
$bad=$saved;$bad['all']['payload']['MEAL']=array_values(array_filter($bad['all']['payload']['MEAL'],fn($row)=>$row['name']!=='UAI'));
try{$request=$base;$request['params']['meal']='7';anytour_andromeda_search3_params($request,$pdo,$bad);throw new RuntimeException('incomplete AI family dictionary accepted');}catch(DomainException $expected){}
echo "Andromeda upstream filters: meals + minimum stars passed\n";
