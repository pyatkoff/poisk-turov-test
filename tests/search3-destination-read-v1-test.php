<?php
declare(strict_types=1);

require_once __DIR__.'/../v2/data/search3-destination-read-v1.php';

function s3d(bool $value,string $message):void{if(!$value)throw new RuntimeException($message);}
function s3d_error(callable $fn,string $message):void{
    try{$fn();throw new RuntimeException('expected '.$message);}
    catch(Throwable $e){if($e->getMessage()!==$message)throw $e;}
}

$dsn=(string)getenv('SEARCH3_DESTINATION_READ_TEST_DSN');
if($dsn===''){echo "SEARCH3_DESTINATION_READ_PURE_OK\n";exit(0);}
$db=new PDO($dsn,'root',(string)getenv('ANYTOUR_DESTINATION_TEST_PASSWORD'),[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES=>false,
    PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
]);

$db->exec("CREATE TABLE catalog_countries (id BIGINT UNSIGNED PRIMARY KEY,name VARCHAR(255) NOT NULL,is_active TINYINT UNSIGNED NOT NULL)");
$db->exec("CREATE TABLE catalog_departure_countries (departure_id BIGINT UNSIGNED NOT NULL,country_id BIGINT UNSIGNED NOT NULL,is_active TINYINT UNSIGNED NOT NULL,PRIMARY KEY(departure_id,country_id))");
$db->exec("INSERT INTO catalog_countries(id,name,is_active) VALUES(4,'Турция',1),(9,'Египет',1)");
$db->exec("INSERT INTO catalog_departure_countries(departure_id,country_id,is_active) VALUES(1,4,1),(1,9,1)");

$sql=preg_replace('/^\s*--.*$/m','',file_get_contents(__DIR__.'/../v2/data/migrations/20260921-anytour-destination-identities-v1.sql'));
foreach(preg_split('/;\s*(?:\r?\n|$)/',(string)$sql) as $statement)if(trim($statement)!=='')$db->exec($statement);

$insert=$db->prepare("INSERT INTO anytour_destinations_v1(kind,parent_id,name_ru,slug,revision,is_active,created_at,updated_at) VALUES(?,?,?,?,1,1,UTC_TIMESTAMP(),UTC_TIMESTAMP())");
$insert->execute(['country',null,'Турция','tourvisor-country-4']);$turkey=(int)$db->lastInsertId();
$insert->execute(['country',null,'Египет','tourvisor-country-9']);$egypt=(int)$db->lastInsertId();
$insert->execute(['region',$turkey,'Белек','tourvisor-region-21']);$belek=(int)$db->lastInsertId();
$insert->execute(['region',$turkey,'Кемер','tourvisor-region-22']);$kemer=(int)$db->lastInsertId();
$insert->execute(['subregion',$belek,'Кадрие','tourvisor-subregion-2101']);$kadriye=(int)$db->lastInsertId();

$bridge=$db->prepare("INSERT INTO anytour_destination_sources_v1(provider,kind,external_id,anytour_destination_id,state,evidence_ref,evidence_sha256,reviewed_by,created_at) VALUES('tourvisor',?,?,?,'accepted',?,?,?,UTC_TIMESTAMP())");
foreach([
    ['country','4',$turkey],['country','9',$egypt],['region','21',$belek],['region','22',$kemer],['subregion','2101',$kadriye]
] as $row){
    $bridge->execute([$row[0],$row[1],$row[2],'fixture://destination-read',hash('sha256',implode('|',$row)),'fixture']);
}

$countries=search3_destination_read($db,'countries',['departureId'=>'1']);
s3d($countries['ok']===true&&$countries['source']==='anytour-destination-identities-v1','country source');
s3d(array_column($countries['items'],'id')===[$egypt,$turkey]||array_column($countries['items'],'id')===[$turkey,$egypt],'local country ids');
$turkeyRow=array_values(array_filter($countries['items'],fn($row)=>$row['id']===$turkey))[0]??null;
s3d(is_array($turkeyRow)&&$turkeyRow['tourvisorIds']===['4'],'country native bridge');

$regions=search3_destination_read($db,'regions',['countryId'=>(string)$turkey]);
s3d(array_column($regions['items'],'id')===[$belek,$kemer],'local region ids');
s3d($regions['items'][0]['parentId']===$turkey,'region parent local');
s3d($regions['items'][0]['tourvisorIds']===['21'],'region native bridge');

$belekRegion=array_values(array_filter($regions['items'],fn($row)=>$row['id']===$belek))[0]??null;
s3d(is_array($belekRegion)&&count($belekRegion['subregions']??[])===1,'nested subregion count');
s3d($belekRegion['subregions'][0]['id']===$kadriye,'nested subregion local id');
s3d($belekRegion['subregions'][0]['parentId']===$belek,'nested subregion parent local');
s3d($belekRegion['subregions'][0]['tourvisorIds']===['2101'],'nested subregion native bridge');
s3d(($regions['subregionCount']??null)===1,'region response subregion count');

$subs=search3_destination_read($db,'subregions',['regionId'=>(string)$belek]);
s3d(count($subs['items'])===1&&$subs['items'][0]['id']===$kadriye,'subregion local id');
s3d($subs['items'][0]['tourvisorIds']===['2101'],'subregion native bridge');

$db->exec("DELETE FROM anytour_destination_sources_v1 WHERE provider='tourvisor' AND kind='country' AND external_id='9'");
s3d_error(fn()=>search3_destination_read($db,'countries',['departureId'=>'1']),'DESTINATION_UNMAPPED');
s3d_error(fn()=>search3_destination_read($db,'regions',['countryId'=>(string)$belek]),'DESTINATION_PARENT');

echo "SEARCH3_DESTINATION_READ_MYSQL_OK local_ids=1 accepted_bridge_only=1 subregions=1 source_id_as_local=0 name_identity=0\n";
