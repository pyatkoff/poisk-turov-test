<?php
/** Fill missing owner-priority hotels directly from Tourvisor hotel detail endpoint. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/db-v1.php';
require_once __DIR__ . '/tourvisor-client-v1.php';
require_once __DIR__ . '/top-hotels-v1.php';

function ph_arg(array $argv,string $name,string $default): string { foreach($argv as $arg){ if(str_starts_with($arg,'--'.$name.'=')) return substr($arg,strlen($name)+3); } return $default; }
function ph_pick_hotel(array $row,int $id): array {
    if (isset($row['hotel']) && is_array($row['hotel'])) $row=$row['hotel'];
    if (isset($row['data']) && is_array($row['data'])) $row=$row['data'];
    if (isset($row[0]) && is_array($row[0])) $row=$row[0];
    if (!isset($row['id'])) $row['id']=$id;
    return $row;
}
function ph_upsert(PDO $pdo,array $row,string $now): bool {
    $id=(int)($row['id']??0); $name=trim((string)($row['name']??'')); if($id<=0||$name==='') return false;
    $country=is_array($row['country']??null)?$row['country']:[];
    $region=is_array($row['region']??null)?$row['region']:[];
    $sub=is_array($row['subRegion']??null)?$row['subRegion']:(is_array($row['subregion']??null)?$row['subregion']:[]);
    $common=is_array($row['common']??null)?$row['common']:[];
    $countryId=(int)($country['id']??($row['countryId']??0));
    $countryName=(string)($country['name']??($row['countryName']??''));
    $regionId=isset($region['id'])?(int)$region['id']:(isset($row['regionId'])?(int)$row['regionId']:null);
    $regionName=$region['name']??($row['regionName']??null);
    $subId=isset($sub['id'])?(int)$sub['id']:(isset($row['subregionId'])?(int)$row['subregionId']:null);
    $subName=$sub['name']??($row['subregionName']??null);
    $normalized=v2_data_normalize_text($name);
    $parts=array_filter([$normalized,v2_data_normalize_text($countryName),v2_data_normalize_text((string)$regionName),v2_data_normalize_text((string)$subName)]);
    $searchKey=implode(' ',array_values(array_unique($parts)));
    $sql="INSERT INTO catalog_hotels (id,country_id,country_name,region_id,region_name,subregion_id,subregion_name,name,normalized_name,search_key,slug,category,rating,hotel_type,latitude,longitude,is_active,first_seen_at,last_seen_at,synced_at) VALUES (:id,:country_id,:country_name,:region_id,:region_name,:subregion_id,:subregion_name,:name,:normalized_name,:search_key,:slug,:category,:rating,:hotel_type,:latitude,:longitude,1,:first_seen,:last_seen,:synced) ON DUPLICATE KEY UPDATE country_id=VALUES(country_id),country_name=VALUES(country_name),region_id=VALUES(region_id),region_name=VALUES(region_name),subregion_id=VALUES(subregion_id),subregion_name=VALUES(subregion_name),name=VALUES(name),normalized_name=VALUES(normalized_name),search_key=VALUES(search_key),slug=VALUES(slug),category=VALUES(category),rating=VALUES(rating),hotel_type=VALUES(hotel_type),latitude=VALUES(latitude),longitude=VALUES(longitude),is_active=1,last_seen_at=VALUES(last_seen_at),synced_at=VALUES(synced_at)";
    $stmt=$pdo->prepare($sql);
    $stmt->execute(['id'=>$id,'country_id'=>$countryId?:null,'country_name'=>$countryName,'region_id'=>$regionId,'region_name'=>$regionName,'subregion_id'=>$subId,'subregion_name'=>$subName,'name'=>$name,'normalized_name'=>$normalized,'search_key'=>$searchKey,'slug'=>(v2_data_slug($name)?:'hotel').'-'.$id,'category'=>isset($row['category'])?(int)$row['category']:null,'rating'=>isset($row['rating'])?(float)$row['rating']:null,'hotel_type'=>isset($row['type'])?(int)$row['type']:null,'latitude'=>isset($common['latitude'])?(float)$common['latitude']:(isset($row['latitude'])?(float)$row['latitude']:null),'longitude'=>isset($common['longitude'])?(float)$common['longitude']:(isset($row['longitude'])?(float)$row['longitude']:null),'first_seen'=>$now,'last_seen'=>$now,'synced'=>$now]);
    $alias=$pdo->prepare("INSERT IGNORE INTO hotel_aliases (hotel_id,alias,normalized_alias,source) VALUES (:hotel,:alias,:normalized,'priority_detail')");
    $alias->execute(['hotel'=>$id,'alias'=>$name,'normalized'=>$normalized]);
    return true;
}

$limitRaw=filter_var(ph_arg($argv,'limit','20'),FILTER_VALIDATE_INT); $limit=$limitRaw===false?20:max(1,min(50,(int)$limitRaw));
$pdo=v2_data_db(); $ids=array_values(array_unique(array_map('intval',v2_priority_hotel_ids()))); $csv=implode(',',$ids);
$present=$pdo->query("SELECT id FROM catalog_hotels WHERE id IN ($csv)")->fetchAll(PDO::FETCH_COLUMN)?:[];
$missing=array_values(array_diff($ids,array_map('intval',$present))); $batch=array_slice($missing,0,$limit); $now=(new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
if($batch===[]){echo "PRIORITY_HOTEL_SYNC_UP_TO_DATE total=".count($ids)."\n";exit(0);} 
$ok=0;$failed=0;
foreach($batch as $id){
    try { $row=ph_pick_hotel(v2_data_tv_get('/hotels/'.$id),$id); if(ph_upsert($pdo,$row,$now)){ $ok++; echo "PRIORITY_HOTEL_SYNC_OK hotel=$id name=".str_replace(' ','_',trim((string)($row['name']??'')))."\n"; } else { $failed++; echo "PRIORITY_HOTEL_SYNC_EMPTY hotel=$id\n"; } }
    catch(Throwable $e){ $failed++; fwrite(STDERR,"PRIORITY_HOTEL_SYNC_FAILED hotel=$id ".mb_substr($e->getMessage(),0,300)."\n"); }
    usleep(250000);
}
$remaining=max(0,count($missing)-$ok);
echo "PRIORITY_HOTEL_SYNC_SUMMARY requested=".count($batch)." synced=$ok failed=$failed remaining_estimate=$remaining\n";
if($ok===0 && $failed>0) exit(1);
