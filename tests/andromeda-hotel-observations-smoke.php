<?php
declare(strict_types=1);
require_once __DIR__.'/../app/integrations/andromeda-hotel-observations.php';

function observation_ok(bool $condition, string $label): void {
    if (!$condition) throw new RuntimeException($label);
}

$offer = static function (?int $local, string $id, string $operatorRef, string $operator, ?string $image): array {
    return ['provider'=>'andromeda','supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>$id,
        'local_hotel_id'=>$local,'operator_ref'=>$operatorRef,'operator'=>$operator,'hotel'=>'Hotel '.$id,
        'hotel_content'=>['region'=>'Sharm el Sheikh','category'=>4,'image_url'=>$image,
            'hotel_url'=>'https://example.org/hotels/'.$id]];
};
$page=['provider'=>'andromeda','search_ref'=>'search_fixture','generation'=>3,'page'=>2,'offers'=>[
    $offer(null,'3414','5','ANEX','https://files.example.org/5.5844.3414.jpg'),
    $offer(null,'3414','7','FUN&SUN','https://files.example.org/5.5844.3414.jpg'),
    $offer(453,'5844','5','ANEX','https://files.example.org/mapped.jpg'),
]];
$country=['local_country_id'=>1,'local_country_name'=>'Египет'];
$rows=AnyTourAndromedaHotelObservations::rows($page,$country);
observation_ok(count($rows)===1,'only unresolved unique hotel is retained');
observation_ok($rows[0]['external_hotel_id']==='3414','external identity retained');
observation_ok($rows[0]['operator_refs']===['5','7'] && $rows[0]['operator_names']===['ANEX','FUN&SUN'],'operators aggregated');
observation_ok($rows[0]['description_text']===null,'missing description remains unknown');
observation_ok($rows[0]['image_url']==='https://files.example.org/5.5844.3414.jpg','image retained');

$pdo=new PDO('sqlite::memory:');$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE andromeda_search_hotel_observations ('
    .'observation_sha256 TEXT PRIMARY KEY,search_evidence_sha256 TEXT,supplier_namespace TEXT,external_hotel_id TEXT,'
    .'hotel_name TEXT,operator_refs_json TEXT,operator_names_json TEXT,country_id INTEGER,country_name TEXT,'
    .'region_name TEXT,category INTEGER,description_text TEXT,image_url TEXT,hotel_url TEXT,content_sha256 TEXT,observed_at_utc TEXT)');
$first=AnyTourAndromedaHotelObservations::record($pdo,$page,$country);
$second=AnyTourAndromedaHotelObservations::record($pdo,$page,$country);
observation_ok($first['inserted']===1 && $second['inserted']===0,'same search/page observation is idempotent');
observation_ok((int)$pdo->query('SELECT COUNT(*) FROM andromeda_search_hotel_observations')->fetchColumn()===1,'one immutable evidence row');
$saved=$pdo->query('SELECT * FROM andromeda_search_hotel_observations')->fetch(PDO::FETCH_ASSOC);
observation_ok(json_decode($saved['operator_refs_json'],true)===['5','7'],'operator evidence readback');
observation_ok($saved['country_name']==='Египет' && $saved['region_name']==='Sharm el Sheikh','location evidence readback');

$next=$page;$next['page']=3;
$third=AnyTourAndromedaHotelObservations::record($pdo,$next,$country);
observation_ok($third['inserted']===1 && (int)$pdo->query('SELECT COUNT(*) FROM andromeda_search_hotel_observations')->fetchColumn()===2,'new page appends evidence');

$bad=$page;$bad['offers'][0]['hotel_content']['image_url']='https://example.org/photo.jpg?token=secret';
observation_ok(AnyTourAndromedaHotelObservations::rows($bad,$country)[0]['image_url']===null,'secret-like URL rejected');

echo "Andromeda unresolved observations: unresolved-only, content, operators, idempotency and readback passed\n";
