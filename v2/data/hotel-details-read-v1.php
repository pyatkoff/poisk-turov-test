<?php
/** Read-only local hotel presentation DTO for Search3. */
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=300, stale-while-revalidate=1800');
header('X-Content-Type-Options: nosniff');
require_once __DIR__ . '/db-v1.php';

function hotel_details_read_out(array $payload, int $status=200): never {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}
function hotel_details_read_id(mixed $value): ?int {
    $id=filter_var($value,FILTER_VALIDATE_INT);
    return ($id===false||(int)$id<=0)?null:(int)$id;
}
function hotel_details_read_json(mixed $value): array {
    if(!is_string($value)||trim($value)==='') return [];
    try { $decoded=json_decode($value,true,512,JSON_THROW_ON_ERROR); return is_array($decoded)?$decoded:[]; }
    catch(Throwable) { return []; }
}
function hotel_details_read_text(mixed $value): ?string {
    if(!is_scalar($value)||$value===null) return null;
    $text=trim((string)$value); return $text===''?null:$text;
}

$hotelId=hotel_details_read_id($_GET['hotelId']??null);
if($hotelId===null) hotel_details_read_out(['ok'=>false,'error'=>'Invalid hotel id'],400);
try {
    $sql="SELECT h.id,h.name,h.country_id,h.country_name,h.region_id,h.region_name,h.subregion_id,h.subregion_name,
                 h.category,h.rating,h.hotel_type,h.latitude,h.longitude,h.primary_image_url,
                 d.status,d.description,d.address,d.place,d.build_info,d.repair_info,d.square_info,
                 d.images_json,d.infrastructure_json,d.meals_json,d.services_json,d.room_types,d.fetched_at
          FROM catalog_hotels h
          LEFT JOIN catalog_hotel_details d ON d.hotel_id=h.id AND d.status='success'
          WHERE h.id=:hotel_id AND h.is_active=1 LIMIT 1";
    $stmt=v2_data_db()->prepare($sql); $stmt->execute(['hotel_id'=>$hotelId]); $row=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$row) hotel_details_read_out(['ok'=>false,'error'=>'Hotel not found'],404);
    $images=hotel_details_read_json($row['images_json']??null);
    $images=array_values(array_filter($images,static fn($v)=>is_string($v)&&str_starts_with($v,'https://')));
    $primary=hotel_details_read_text($row['primary_image_url']??null);
    if($primary!==null&&!in_array($primary,$images,true)) array_unshift($images,$primary);
    $images=array_slice(array_values(array_unique($images)),0,100);
    $item=[
        'id'=>(int)$row['id'],'name'=>(string)$row['name'],
        'country'=>['id'=>(int)$row['country_id'],'name'=>(string)$row['country_name']],
        'region'=>$row['region_id']!==null?['id'=>(int)$row['region_id'],'name'=>(string)($row['region_name']??'')]:null,
        'subRegion'=>$row['subregion_id']!==null?['id'=>(int)$row['subregion_id'],'name'=>(string)($row['subregion_name']??'')]:null,
        'category'=>$row['category']!==null?(int)$row['category']:null,'rating'=>$row['rating']!==null?(float)$row['rating']:null,
        'type'=>$row['hotel_type']!==null?(int)$row['hotel_type']:null,
        'description'=>hotel_details_read_text($row['description']??null),'primaryImage'=>$primary,'images'=>$images,
        'infrastructure'=>hotel_details_read_json($row['infrastructure_json']??null),
        'meals'=>hotel_details_read_json($row['meals_json']??null),'services'=>hotel_details_read_json($row['services_json']??null),
        'roomTypes'=>hotel_details_read_text($row['room_types']??null),
        'address'=>hotel_details_read_text($row['address']??null),'place'=>hotel_details_read_text($row['place']??null),
        'build'=>hotel_details_read_text($row['build_info']??null),'repair'=>hotel_details_read_text($row['repair_info']??null),
        'square'=>hotel_details_read_text($row['square_info']??null),
        'coordinates'=>($row['latitude']!==null&&$row['longitude']!==null)?['latitude'=>(float)$row['latitude'],'longitude'=>(float)$row['longitude']]:null,
        'detailsAvailable'=>($row['status']??null)==='success','detailsFetchedAt'=>hotel_details_read_text($row['fetched_at']??null),
    ];
    hotel_details_read_out(['ok'=>true,'item'=>$item,'source'=>'anytour-local-hotel']);
} catch(Throwable $e) {
    error_log('hotel-details-read-v1: '.$e->getMessage());
    hotel_details_read_out(['ok'=>false,'error'=>'Hotel details temporarily unavailable'],503);
}
