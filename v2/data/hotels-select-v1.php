<?php
/** Local AnyTour hotel catalog for advanced search select. */
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=120, stale-while-revalidate=600');
header('X-Content-Type-Options: nosniff');
require_once __DIR__ . '/db-v1.php';
function hotels_select_out(array $payload, int $status=200): never { http_response_code($status); echo json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }
function hotels_select_int(mixed $v): ?int { $x=filter_var($v,FILTER_VALIDATE_INT); return ($x===false||(int)$x<=0)?null:(int)$x; }
try {
    $country=hotels_select_int($_GET['countryId']??null); $region=hotels_select_int($_GET['regionId']??null); $subregion=hotels_select_int($_GET['subregionId']??null);
    if($country===null||$region===null) hotels_select_out(['ok'=>true,'items'=>[],'source'=>'anytour-catalog']);
    $limit=max(1,min(100,hotels_select_int($_GET['limit']??100)??100));
    $where=['is_active=1','country_id=:country','region_id=:region']; $params=['country'=>$country,'region'=>$region];
    if($subregion!==null){$where[]='subregion_id=:subregion';$params['subregion']=$subregion;}
    $category=hotels_select_int($_GET['category']??null); if($category!==null){$where[]='category=:category';$params['category']=$category;}
    $rating=(float)($_GET['rating']??0); if($rating>0){$where[]='rating>=:rating';$params['rating']=$rating;}
    $type=hotels_select_int($_GET['type']??null); if($type!==null){$where[]='hotel_type=:hotel_type';$params['hotel_type']=$type;}
    $sql='SELECT id,name,category,rating,country_id,country_name,region_id,region_name,subregion_id,subregion_name,hotel_type FROM catalog_hotels WHERE '.implode(' AND ',$where).' ORDER BY rating DESC,name ASC LIMIT '.$limit;
    $stmt=v2_data_db()->prepare($sql); $stmt->execute($params);
    $items=array_map(static fn(array $r):array=>[
        'id'=>(int)$r['id'],'name'=>(string)$r['name'],'russianName'=>(string)$r['name'],'category'=>$r['category']!==null?(int)$r['category']:null,'rating'=>$r['rating']!==null?(float)$r['rating']:null,
        'country'=>['id'=>(int)$r['country_id'],'name'=>(string)$r['country_name']],
        'region'=>['id'=>(int)$r['region_id'],'name'=>(string)($r['region_name']??'')],
        'subRegion'=>$r['subregion_id']!==null?['id'=>(int)$r['subregion_id'],'name'=>(string)($r['subregion_name']??'')]:null,
        'type'=>$r['hotel_type']!==null?(int)$r['hotel_type']:null,
    ],$stmt->fetchAll());
    hotels_select_out(['ok'=>true,'items'=>$items,'count'=>count($items),'source'=>'anytour-catalog']);
} catch(Throwable $e){ error_log('hotels-select-v1: '.$e->getMessage()); hotels_select_out(['ok'=>false,'items'=>[],'error'=>'Hotel catalog is temporarily unavailable'],503); }
