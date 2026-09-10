<?php
declare(strict_types=1);
// Execute the real server projector with an ephemeral local catalog, never a supplier.
require $argv[1].'/v2/api-andromeda-search3-preview.php';
$pdo=new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE catalog_hotels(id INTEGER,name TEXT,country_id INTEGER,country_name TEXT,region_id INTEGER,region_name TEXT,subregion_id INTEGER,subregion_name TEXT,category INTEGER,rating REAL,is_active INTEGER,primary_image_url TEXT)');
$pdo->exec("INSERT INTO catalog_hotels VALUES
 (447,'Accepted hotel',1,'Egypt',1,'Sharm',1,'Bay',5,4.5,1,'https://example.com/catalog.jpg'),
 (999,'Inactive hotel',1,'Egypt',1,'Sharm',1,'Bay',5,4.5,0,NULL),
 (888,'Other country',4,'Turkey',1,'Kemer',1,'Bay',5,4.5,1,NULL),
 (111,'Below category filter',1,'Egypt',1,'Sharm',1,'Bay',3,4.5,1,NULL)");
$offer=static function(?int $local,string $key): array {
    return ['local_hotel_id'=>$local,'price'=>['currency'=>'RUB','amount'=>'100000'],
        'hotel_content'=>['image_url'=>'https://example.com/provider.jpg'],
        'meal'=>['label'=>'AI'],'supplier_namespace'=>'andromeda_catalog',
        'external_hotel_id'=>'synthetic_'.$key,'hotel'=>'Supplier fixture',
        'offer_ref'=>'offer_'.hash('sha256',$key),'operator'=>'ANEX',
        'check_in'=>'2027-01-02','nights'=>8,'adults'=>2,'children'=>0,'room'=>'Standard'];
};
$page=['offers'=>[$offer(447,'accepted'),$offer(null,'unresolved'),$offer(999,'inactive'),
    $offer(888,'country'),$offer(111,'category'),$offer(777,'missing')],
    'generation'=>7,'page'=>1,'pages_count'=>1,'search_ref'=>hash('sha256','fixture'),
    'status'=>'complete'];
$request=['generation'=>7,'params'=>['countryId'=>'1','dateFrom'=>'2027-01-02',
    'dateTo'=>'2027-01-02','hotelCategory'=>'4','meal'=>'7']];
$data=anytour_andromeda_search3_project($request,$pdo,$page);
if(count($data['hotels'])!==1 || $data['hotels'][0]['local_id']!==447)
    throw new RuntimeException('Existing identity, activity, country or filter guard changed');
echo json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
