<?php
declare(strict_types=1);

$root=dirname(__DIR__);
$releaseRoot=rtrim((string)getenv('ANYTOUR_RELEASE_ROOT'),'/');
if($releaseRoot===''){
    echo "ANYTOUR_INT_SNAPSHOT_MYSQL_NOT_RUN reason=no_release_root\n";
    exit(0);
}
foreach([
    'app/integrations/three-provider-money-facts.php',
    'app/integrations/three-provider-availability.php',
    'app/integrations/three-provider-flight-details.php',
    'app/integrations/three-provider-operator.php',
    'app/integrations/three-provider-offer-contract.php',
    'app/integrations/three-provider-offer-context.php',
    'app/integrations/three-provider-quote-envelope.php',
    'app/integrations/three-provider-search-handoff.php',
    'app/integrations/anytour-offer-snapshot-producer.php',
] as $file) require_once $root.'/'.$file;
require_once $releaseRoot.'/v2/data/anytour-offer-snapshot-ingest-v1.php';
require_once $releaseRoot.'/v2/data/anytour-offer-store-read-v2.php';

function producer_sql_check(bool $ok,string $label):void{if(!$ok)throw new RuntimeException('PRODUCER_SQL_CHECK_FAILED:'.$label);}
function producer_sql_file(PDO $db,string $path):void{
    $sql=file_get_contents($path);if(!is_string($sql)||$sql==='')throw new RuntimeException('EMPTY_SQL:'.$path);
    $sql=preg_replace('/^\s*--.*$/m','',$sql)??$sql;
    foreach(preg_split('/;\s*(?:\r?\n|$)/',$sql)?:[] as $statement){$statement=trim($statement);if($statement!=='')$db->exec($statement);}
}
function producer_sql_params():array{
    return ['departureId'=>'1','countryId'=>'4','dateFrom'=>'2026-10-05','dateTo'=>'2026-10-05',
        'nightsFrom'=>7,'nightsTo'=>7,'adults'=>2,'childs'=>[],'meal'=>'','hotelCategory'=>'','hotelRating'=>'',
        'hotelTypes'=>[],'hotelIds'=>[],'hotelServices'=>[],'arrivalId'=>'','regionIds'=>[],'subregionIds'=>[],
        'operatorIds'=>[],'priceFrom'=>'','priceTo'=>'','currency'=>'RUB','onlyCharter'=>false,'onlyDirect'=>false];
}
function producer_sql_raw(string $provider,int $legacy,int $adults,array $additional,string $base,string $salt):array{
    return ['provider'=>$provider,'operator'=>$provider==='anex'?null:'ANEX','local_hotel_id'=>$legacy,
        'provider_hotel_ref'=>'private-'.$provider.'-hotel-'.$salt,'search_ref'=>'private-'.$provider.'-search-'.$salt,
        'offer_ref'=>'private-'.$provider.'-offer-'.$salt,'checkin'=>'2026-10-05','nights'=>7,'adults'=>$adults,
        'children'=>0,'child_ages'=>[],'meal'=>['raw'=>'AI','family'=>'ai','qualifiers'=>['plus'=>false,'without_alcohol'=>false]],
        'room'=>['raw'=>'Standard Room','normalized'=>'standard room'],
        'placement'=>$provider==='andromeda'?null:['raw'=>'DBL','normalized'=>'dbl'],
        'availability'=>['hotel'=>null,'flight_outbound_economy'=>null,'flight_return_economy'=>null],
        'search_price'=>['amount'=>$base,'currency'=>'RUB','source'=>$provider.'_search'],'fuel_charge_reported'=>null,
        'additional_prices_reported'=>$additional,'observed_at'=>'2026-09-17T06:00:00Z'];
}
function producer_sql_entry(string $provider,int $legacy,int $own,int $adults,string $base,string $fuel,string $salt,int $issued):array{
    $offer=AnyTourThreeProviderOfferContract::fromSearch(producer_sql_raw($provider,$legacy,$adults,[
        ['kind'=>'fuel_adult','amount'=>$fuel,'currency'=>'RUB','source'=>$provider.'_additional']],$base,$salt));
    $retained=AnyTourThreeProviderOfferContext::retain($offer,51,1,$issued,900);
    $current=['provider'=>$retained['provider'],'operator'=>$retained['operator'],'local_hotel_id'=>$retained['local_hotel_id'],
        'identity'=>$retained['identity'],'generation'=>51,'page'=>1];
    return ['anytour_hotel_id'=>$own,'offer'=>$offer,'retained'=>$retained,'current'=>$current,
        'priced_money'=>AnyTourThreeProviderMoneyFacts::withSearchSurchargeEstimate($offer['money'],$adults,0)];
}

$dsn=trim((string)getenv('ANYTOUR_OFFER_TEST_DSN'));
if($dsn===''){echo "ANYTOUR_INT_SNAPSHOT_MYSQL_NOT_RUN reason=no_test_dsn\n";exit(0);}
producer_sql_check(in_array('mysql',PDO::getAvailableDrivers(),true),'pdo_mysql');
$db=new PDO($dsn,(string)getenv('ANYTOUR_OFFER_TEST_USER'),(string)getenv('ANYTOUR_OFFER_TEST_PASSWORD'),[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_STRINGIFY_FETCHES=>false]);
foreach(['anytour_offers','anytour_offer_scope_state','anytour_offer_refreshes','anytour_offer_store_control','anytour_hotel_sources','anytour_hotels','anytour_catalog_control'] as $table)$db->exec('DROP TABLE IF EXISTS '.$table);
producer_sql_file($db,$releaseRoot.'/v2/data/migrations/20260916-anytour-canonical-catalog.sql');
producer_sql_file($db,$releaseRoot.'/v2/data/migrations/20260916-anytour-offer-store.sql');
producer_sql_file($db,$releaseRoot.'/v2/data/migrations/20260917-anytour-offer-store-v2.sql');

$created='2026-09-17 06:00:00';
$hotel=$db->prepare('INSERT INTO anytour_hotels(profile_json,profile_sha256,revision,is_active,created_at,updated_at) VALUES(:json,:sha,1,1,:created,:updated)');
$bridge=$db->prepare("INSERT INTO anytour_hotel_sources(namespace,external_key,anytour_hotel_id,acquired_via,source_json,source_sha256,first_seen_at,last_seen_at) VALUES('legacy_catalog',:legacy,:own,'producer-test',:json,:sha,:first,:last)");
$owns=[];
foreach([3417=>'Alpha',4200=>'Beta'] as $legacy=>$name){
    $profile=json_encode(['name'=>$name],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    $hotel->execute(['json'=>$profile,'sha'=>hash('sha256',$profile),'created'=>$created,'updated'=>$created]);
    $own=(int)$db->lastInsertId();$owns[$legacy]=$own;
    $source=json_encode(['fixture'=>true],JSON_THROW_ON_ERROR);
    $bridge->execute(['legacy'=>(string)$legacy,'own'=>$own,'json'=>$source,'sha'=>hash('sha256',$source),'first'=>$created,'last'=>$created]);
}

$now=new DateTimeImmutable('2026-09-17T06:05:00Z');$issued=$now->getTimestamp()-60;$params=producer_sql_params();
$ingest=static fn(string $provider,array $search,array $rows,DateTimeImmutable $at):array
    => AnyTourOfferSnapshotIngestV1::replaceCompleteSnapshot($GLOBALS['db'],$provider,$search,$rows,$at);
$anex=producer_sql_entry('anex',3417,$owns[3417],2,'100000','5000','sql-anex',$issued);
$receipt=AnyTourIntOfferSnapshotProducerV1::produce('anex',$params,['complete'=>true,'authoritative_empty'=>false,'offers'=>[$anex]],$now,$ingest);
producer_sql_check($receipt['published']===true&&$receipt['readyOfferCount']===1,'anex-produced');
$scope=AnyTourSearchScopeV1::fromParams($params)['digest'];
$visible=AnyTourOfferStoreReadV2::readScope($db,$scope,$now);
producer_sql_check(count($visible['items'])===1&&$visible['items'][0]['provider']==='anex','anex-visible');
producer_sql_check($visible['items'][0]['price']==='110000','anex-final-price');

$badOffer=AnyTourThreeProviderOfferContract::fromSearch(producer_sql_raw('anex',3417,2,[],'100000','sql-not-ready'));
$badRetained=AnyTourThreeProviderOfferContext::retain($badOffer,51,1,$issued,900);
$bad=['anytour_hotel_id'=>$owns[3417],'offer'=>$badOffer,'retained'=>$badRetained,
    'current'=>['provider'=>$badRetained['provider'],'operator'=>$badRetained['operator'],'local_hotel_id'=>$badRetained['local_hotel_id'],
        'identity'=>$badRetained['identity'],'generation'=>51,'page'=>1],'priced_money'=>null];
$preserved=AnyTourIntOfferSnapshotProducerV1::produce('anex',$params,['complete'=>true,'authoritative_empty'=>false,'offers'=>[$bad]],$now->modify('+1 minute'),$ingest);
producer_sql_check($preserved['published']===false,'not-ready-no-publish');
$visible=AnyTourOfferStoreReadV2::readScope($db,$scope,$now->modify('+1 minute'));
producer_sql_check(count($visible['items'])===1&&$visible['items'][0]['price']==='110000','previous-complete-preserved');

$and=producer_sql_entry('andromeda',4200,$owns[4200],3,'144790','7185.60','sql-and',$issued+60);
AnyTourIntOfferSnapshotProducerV1::produce('andromeda',$params,['complete'=>true,'authoritative_empty'=>false,'offers'=>[$and]],$now->modify('+1 minute'),$ingest);
$visible=AnyTourOfferStoreReadV2::readScope($db,$scope,$now->modify('+1 minute'));
$providers=array_count_values(array_column($visible['items'],'provider'));
producer_sql_check(count($visible['items'])===2&&($providers['anex']??0)===1&&($providers['andromeda']??0)===1,'providers-isolated');
producer_sql_check(in_array('166346.8',array_column($visible['items'],'price'),true),'andromeda-final-price');
producer_sql_check((int)$db->query("SELECT COUNT(*) FROM anytour_offer_refreshes WHERE status='completed'")->fetchColumn()===2,'two-complete-refreshes');

echo "ANYTOUR_INT_SNAPSHOT_MYSQL_OK providers=2 offers=2 preserved_not_ready=1\n";
