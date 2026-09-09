<?php
declare(strict_types=1);
require_once __DIR__.'/../v2/seo-seasonal-offer-snapshot-v1.php';
function seasonal_check(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);}
seasonal_check(in_array('sqlite',PDO::getAvailableDrivers(),true),'pdo_sqlite is required; never silently skip SQL regression');
$pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$pdo->sqliteCreateFunction('current_timestamp',static fn()=>'2026-09-09 14:00:00',0);
$pdo->sqliteCreateFunction('current_date',static fn()=>'2026-09-09',0);
$pdo->exec("CREATE TABLE tour_price_observations(id INTEGER PRIMARY KEY,source TEXT,search_id INTEGER,departure_id INTEGER,country_id INTEGER,region_id INTEGER,hotel_id INTEGER,tour_id TEXT,departure_date TEXT,departure_year INTEGER,departure_month INTEGER,nights INTEGER,adults INTEGER,children_count INTEGER,child_ages_signature TEXT,meal_id INTEGER,room_id INTEGER,room_type TEXT,operator_id INTEGER,price REAL,currency TEXT,observed_at TEXT);
CREATE TABLE catalog_hotels(id INTEGER PRIMARY KEY,country_id INTEGER,is_active INTEGER,name TEXT,category INTEGER,primary_image_url TEXT);
CREATE TABLE catalog_departures(id INTEGER PRIMARY KEY,name TEXT);
CREATE TABLE catalog_regions(id INTEGER PRIMARY KEY,name TEXT);
CREATE TABLE seo_offer_snapshots(page_key TEXT,page_type TEXT,departure_id INTEGER,offers_json TEXT,offer_count INTEGER,currency TEXT,observed_at TEXT,expires_at TEXT,min_price REAL);
INSERT INTO catalog_departures VALUES(1,'Москва'),(2,'Другой вылет');
INSERT INTO catalog_regions VALUES(22,'Кемер'),(23,'Другой курорт');");
for($id=101;$id<=125;$id++)$pdo->prepare('INSERT INTO catalog_hotels VALUES(?,4,1,?,5,?)')->execute([$id,'Hotel '.$id,'https://images.example.org/'.$id.'.jpg']);
$base=['source'=>'user_search','search_id'=>12345,'departure_id'=>1,'country_id'=>4,'region_id'=>22,'hotel_id'=>101,'tour_id'=>'t101','departure_date'=>'2026-11-10','departure_year'=>2026,'departure_month'=>11,'nights'=>7,'adults'=>2,'children_count'=>0,'child_ages_signature'=>'','meal_id'=>7,'room_id'=>1,'room_type'=>'STANDARD','operator_id'=>1,'price'=>110000,'currency'=>'RUB','observed_at'=>'2026-09-09 12:00:00'];
$insert=static function(array $changes=[])use($pdo,$base):void{
 $r=array_replace($base,$changes);$cols=implode(',',array_keys($r));$marks=implode(',',array_fill(0,count($r),'?'));
 $pdo->prepare("INSERT INTO tour_price_observations($cols) VALUES($marks)")->execute(array_values($r));
};
$insert();
// The old cheap price must never win against a newer exact segment.
$insert(['price'=>50000,'observed_at'=>'2026-09-08 12:00:00']);
$insert(['price'=>120000,'observed_at'=>'2026-09-09 13:00:00']);
$insert(['hotel_id'=>102,'tour_id'=>'t102','price'=>90000,'source'=>'scheduled_monitor']);
$insert(['hotel_id'=>103,'price'=>100000,'observed_at'=>'2026-09-06 14:00:00']);
$bad=[['hotel_id'=>104,'region_id'=>23],['hotel_id'=>105,'departure_id'=>2],['hotel_id'=>106,'country_id'=>1],['hotel_id'=>107,'departure_date'=>'2026-12-10','departure_month'=>12],['hotel_id'=>108,'departure_date'=>'2027-11-10','departure_year'=>2027],['hotel_id'=>109,'adults'=>1],['hotel_id'=>110,'children_count'=>1],['hotel_id'=>111,'currency'=>'USD'],['hotel_id'=>112,'observed_at'=>'2026-09-06 13:59:59'],['hotel_id'=>113,'observed_at'=>'2026-09-10 13:00:00'],['hotel_id'=>114,'departure_date'=>'2026-08-31'],['hotel_id'=>115,'nights'=>0],['hotel_id'=>116,'nights'=>29],['hotel_id'=>117,'price'=>0],['hotel_id'=>118],['hotel_id'=>999],['hotel_id'=>119]];
$pdo->exec('UPDATE catalog_hotels SET is_active=0 WHERE id=118; UPDATE catalog_hotels SET country_id=1 WHERE id=119');
foreach($bad as $r)$insert(array_replace(['price'=>1],$r));
$key='resort_month:1:4:22:2026-11';
$offers=v2_seo_seasonal_snapshot_candidates($pdo,$key);
seasonal_check(array_column($offers,'hotelId')===[102,103,101],'exact identity, party, date, catalog and 72h guards before limiting');
seasonal_check($offers[2]['price']===120000.0,'latest exact segment supersedes cheaper old price');
seasonal_check($offers[2]['observationSource']==='user_search','ordinary searches are included');
seasonal_check($offers[2]['departureName']==='Москва'&&$offers[2]['hotelImage']!==''&&strlen($offers[2]['segmentFingerprint'])===64,'complete card evidence');
seasonal_check(v2_seo_seasonal_observation_offers($pdo,$key,1)[0]['hotelId']===102,'bounded cheapest distinct hotels');
seasonal_check(in_array(104,array_column(v2_seo_seasonal_observation_offers($pdo,'month:1:4:2026-11'),'hotelId'),true),'country-month allows its other resort');
$offer=['hotelId'=>125,'hotelName'=>'Cached hotel','regionId'=>22,'departureDate'=>'2026-11-11','nights'=>7,'price'=>130000];
$stmt=$pdo->prepare("INSERT INTO seo_offer_snapshots VALUES(?,'resort_month',1,?,1,'RUB','2026-09-09 13:00:00','2026-09-12 13:00:00',130000)");
$stmt->execute([$key,json_encode([$offer],JSON_THROW_ON_ERROR)]);
seasonal_check(array_column(v2_seo_seasonal_snapshot_candidates($pdo,$key),'hotelId')===[125],'fresh valid snapshot remains first choice');
foreach([['regionId'=>23],['departureDate'=>'2026-12-01'],['departureDate'=>'2026-11-31']] as $invalid){
 $pdo->prepare('UPDATE seo_offer_snapshots SET offers_json=?')->execute([json_encode([array_replace($offer,$invalid)],JSON_THROW_ON_ERROR)]);
 seasonal_check(count(v2_seo_seasonal_snapshot_candidates($pdo,$key))===3,'bad snapshot cannot block correct observations');
}
$pdo->exec("UPDATE seo_offer_snapshots SET offers_json='broken'");
seasonal_check(count(v2_seo_seasonal_snapshot_candidates($pdo,$key))===3,'malformed cache fallback');
$pdo->prepare('UPDATE seo_offer_snapshots SET offers_json=?,expires_at=?')->execute([json_encode([$offer]),'2026-09-09 13:59:59']);
seasonal_check(count(v2_seo_seasonal_snapshot_candidates($pdo,$key))===3,'expired cache fallback without reviving it');
$pdo->exec('DROP TABLE seo_offer_snapshots');
seasonal_check(count(v2_seo_seasonal_snapshot_candidates($pdo,$key))===3,'snapshot SQL error does not conceal valid observations');
$pdo->exec('DELETE FROM tour_price_observations');
seasonal_check(v2_seo_seasonal_snapshot_candidates($pdo,$key)===[],'no made-up fallback inventory');
foreach(['resort_month:1:4:22:2026-13','month:0:4:2026-11','month:1:4:22:2026-11','resort_month:1:4:2026-11',"month:1:4:2026-11' OR 1=1"]as$invalid)seasonal_check(v2_seo_seasonal_page_identity($invalid)===null,'invalid identity rejected');
$source=file_get_contents(__DIR__.'/../v2/seo-seasonal-offer-snapshot-v1.php');
seasonal_check(!preg_match('/v2_data_tv_get\s*\(|curl_exec\s*\(|INSERT\s+INTO|UPDATE\s+catalog/i',$source),'reader is DB-only/read-only');
echo "SEO_SEASONAL_OBSERVATION_FALLBACK_OK exact_identity=1 snapshot_miss=1 user_search=1 latest_segment=1 freshness=1 no_supplier=1\n";
