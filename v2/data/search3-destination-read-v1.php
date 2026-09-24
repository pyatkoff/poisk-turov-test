<?php
/** Search3 canonical destination read boundary. Local IDs out, accepted Tourvisor IDs as explicit bridge metadata. */
declare(strict_types=1);

require_once __DIR__.'/db-v1.php';
require_once __DIR__.'/anytour-destination-catalog-v1.php';

function search3_destination_positive_int(mixed $value,string $label): int
{
    if((!is_int($value)&&!is_string($value))
        || preg_match('/^[1-9][0-9]*$/D',(string)$value)!==1
        || filter_var($value,FILTER_VALIDATE_INT)===false
        || (int)$value>9007199254740991){
        throw new InvalidArgumentException($label);
    }
    return (int)$value;
}

function search3_destination_tourvisor_ids(AnyTourDestinationCatalogV1 $catalog,string $kind,int $localId): array
{
    $ids=$catalog->nativeIds('tourvisor',$kind,[$localId]);
    if(!$ids)throw new RuntimeException('DESTINATION_UNMAPPED');
    $out=[];
    foreach($ids as $id){
        if(!is_string($id)||preg_match('/^[1-9][0-9]*$/D',$id)!==1)throw new RuntimeException('DESTINATION_TOURVISOR_ID');
        $out[$id]=true;
    }
    $ids=array_keys($out);
    usort($ids,static fn(string $a,string $b):int=>(int)$a<=>(int)$b);
    return $ids;
}

function search3_destination_item(AnyTourDestinationCatalogV1 $catalog,array $dto): array
{
    $id=search3_destination_positive_int($dto['id']??null,'DESTINATION_ID');
    $kind=(string)($dto['kind']??'');
    if(!in_array($kind,['country','region','subregion'],true))throw new RuntimeException('DESTINATION_KIND');
    $name=trim((string)($dto['nameRu']??''));
    if($name===''||strlen($name)>255||preg_match('//u',$name)!==1)throw new RuntimeException('DESTINATION_NAME');
    $parent=$dto['parentId']===null?null:search3_destination_positive_int($dto['parentId'],'DESTINATION_PARENT_ID');
    return [
        'id'=>$id,
        'kind'=>$kind,
        'parentId'=>$parent,
        'name'=>$name,
        'russianName'=>$name,
        'slug'=>(string)($dto['slug']??''),
        'revision'=>search3_destination_positive_int($dto['revision']??null,'DESTINATION_REVISION'),
        'tourvisorIds'=>search3_destination_tourvisor_ids($catalog,$kind,$id),
    ];
}

function search3_destination_read(PDO $db,string $action,array $query): array
{
    if($db->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql')throw new RuntimeException('DESTINATION_MYSQL_REQUIRED');
    $catalog=new AnyTourDestinationCatalogV1($db);
    if(!$catalog->readable())throw new RuntimeException('DESTINATION_UNAVAILABLE');

    if($action==='countries'){
        $departureId=search3_destination_positive_int($query['departureId']??null,'DEPARTURE_ID');
        $stmt=$db->prepare("SELECT c.id AS external_id,c.name AS source_name
            FROM catalog_departure_countries dc
            JOIN catalog_countries c ON c.id=dc.country_id AND c.is_active=1
            WHERE dc.departure_id=? AND dc.is_active=1
            GROUP BY c.id,c.name
            ORDER BY source_name,c.id
            LIMIT 1001");
        $stmt->execute([$departureId]);
        $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
        if(count($rows)>1000)throw new RuntimeException('DESTINATION_LIMIT');
        $items=[];
        foreach($rows as $row){
            $external=search3_destination_positive_int($row['external_id']??null,'TOURVISOR_COUNTRY_ID');
            $localId=$catalog->localId('tourvisor','country',(string)$external);
            if($localId===null)throw new RuntimeException('DESTINATION_UNMAPPED');
            $dto=$catalog->get($localId);
            if(!$dto||$dto['kind']!=='country')throw new RuntimeException('DESTINATION_HIERARCHY');
            $item=search3_destination_item($catalog,$dto);
            $items[$localId]=$item;
        }
        $items=array_values($items);
        usort($items,static fn(array $a,array $b):int=>(($x=strnatcasecmp($a['name'],$b['name']))!==0?$x:$a['id']<=>$b['id']));
        return ['ok'=>true,'source'=>'anytour-destination-identities-v1','provider'=>'tourvisor','kind'=>'country','items'=>$items,'count'=>count($items)];
    }

    if($action==='regions'||$action==='subregions'){
        $parentKey=$action==='regions'?'countryId':'regionId';
        $parentKind=$action==='regions'?'country':'region';
        $kind=$action==='regions'?'region':'subregion';
        $parentId=search3_destination_positive_int($query[$parentKey]??null,strtoupper($parentKey));
        $parent=$catalog->get($parentId);
        if(!$parent||$parent['kind']!==$parentKind)throw new InvalidArgumentException('DESTINATION_PARENT');
        $rows=$catalog->children($parentId,$kind);
        $items=array_map(static fn(array $row):array=>search3_destination_item($catalog,$row),$rows);
        return ['ok'=>true,'source'=>'anytour-destination-identities-v1','provider'=>'tourvisor','kind'=>$kind,'parentId'=>$parentId,'items'=>$items,'count'=>count($items)];
    }

    throw new InvalidArgumentException('DESTINATION_ACTION');
}

function search3_destination_read_out(array $payload,int $status=200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: private, max-age=300, stale-while-revalidate=3600');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    exit;
}

function search3_destination_read_main(): never
{
    $action=(string)($_GET['action']??'');
    try{
        $payload=search3_destination_read(v2_data_db(),$action,$_GET);
        search3_destination_read_out($payload);
    }catch(InvalidArgumentException $error){
        search3_destination_read_out(['ok'=>false,'error'=>'Invalid destination request'],400);
    }catch(Throwable $error){
        error_log('search3-destination-read-v1: '.$error->getMessage());
        search3_destination_read_out(['ok'=>false,'error'=>'Destination catalogue is temporarily unavailable'],503);
    }
}

if(realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__)search3_destination_read_main();
