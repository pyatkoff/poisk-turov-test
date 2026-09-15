<?php
declare(strict_types=1);
$source=file_get_contents(__DIR__.'/../v2/data/hotel-details-read-v1.php');
if($source===false) throw new RuntimeException('reader missing');
foreach(['catalog_hotel_details','description','images_json','infrastructure_json','services_json','room_types','detailsAvailable','anytour-local-hotel'] as $needle){
    if(!str_contains($source,$needle)) throw new RuntimeException('reader contract missing '.$needle);
}
if(!str_contains($source,"WHERE h.id=:hotel_id AND h.is_active=1 LIMIT 1")) throw new RuntimeException('reader must be keyed by one active local hotel id');
if(preg_match('/\b(?:INSERT|UPDATE|DELETE|REPLACE)\b/i',$source)) throw new RuntimeException('reader must remain read-only');
if(str_contains($source,'tourvisor-client')||str_contains($source,'V2Runtime.api')) throw new RuntimeException('reader must not call supplier/runtime APIs');
echo "SEARCH3_LOCAL_HOTEL_DETAILS_READ_SMOKE_OK\n";
