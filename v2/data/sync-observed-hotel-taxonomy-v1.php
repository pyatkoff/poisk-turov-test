<?php
/** Enrich region/subregion only for fresh observed hotels that need SEO taxonomy. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/db-v1.php';
require_once __DIR__.'/tourvisor-client-v1.php';

function oht_arg(array $argv,string $name,?string $default=null): ?string
{
    foreach($argv as $arg)if(str_starts_with($arg,'--'.$name.'='))return substr($arg,strlen($name)+3);
    return $default;
}

function oht_detail_row(array $row,int $id): array
{
    if(isset($row['hotel'])&&is_array($row['hotel']))$row=$row['hotel'];
    if(isset($row['data'])&&is_array($row['data']))$row=$row['data'];
    if(isset($row[0])&&is_array($row[0]))$row=$row[0];
    if(!isset($row['id']))$row['id']=$id;
    return $row;
}

function oht_taxonomy_from_detail(PDO $pdo,array $row,int $countryId): array
{
    $region=is_array($row['region']??null)?$row['region']:[];
    $sub=is_array($row['subRegion']??null)?$row['subRegion']:(is_array($row['subregion']??null)?$row['subregion']:[]);
    $regionId=isset($region['id'])?(int)$region['id']:(isset($row['regionId'])?(int)$row['regionId']:0);
    $regionName=trim((string)($region['name']??($row['regionName']??'')));
    $subId=isset($sub['id'])?(int)$sub['id']:(isset($row['subregionId'])?(int)$row['subregionId']:(isset($row['subRegionId'])?(int)$row['subRegionId']:0));
    $subName=trim((string)($sub['name']??($row['subregionName']??($row['subRegionName']??''))));

    if($regionId>0&&$regionName===''){
        $q=$pdo->prepare('SELECT name FROM catalog_regions WHERE id=:id AND country_id=:country AND is_active=1 LIMIT 1');
        $q->execute(['id'=>$regionId,'country'=>$countryId]);
        $regionName=trim((string)($q->fetchColumn()?:''));
    }
    if($regionId<=0&&$subId>0){
        $q=$pdo->prepare('SELECT r.id,r.name FROM catalog_subregions s JOIN catalog_regions r ON r.id=s.region_id AND r.is_active=1 WHERE s.id=:sub AND s.is_active=1 AND r.country_id=:country LIMIT 1');
        $q->execute(['sub'=>$subId,'country'=>$countryId]);
        $mapped=$q->fetch(PDO::FETCH_ASSOC)?:[];
        $regionId=(int)($mapped['id']??0);
        if($regionName==='')$regionName=trim((string)($mapped['name']??''));
    }
    return [
        'region_id'=>$regionId>0?$regionId:null,
        'region_name'=>$regionName!==''?$regionName:null,
        'subregion_id'=>$subId>0?$subId:null,
        'subregion_name'=>$subName!==''?$subName:null,
    ];
}

$countryRaw=(string)oht_arg($argv,'country','1,8');
$countryIds=[];
foreach(explode(',',$countryRaw) as $raw){$id=filter_var(trim($raw),FILTER_VALIDATE_INT);if($id!==false&&(int)$id>0)$countryIds[]=(int)$id;}
$countryIds=array_values(array_unique($countryIds));
if($countryIds===[]){fwrite(STDERR,"OBSERVED_HOTEL_TAXONOMY_FAIL countries\n");exit(2);}
$days=max(1,min(90,(int)oht_arg($argv,'days','30')));
$limit=max(1,min(500,(int)oht_arg($argv,'limit','500')));
$pdo=v2_data_db();
$countrySql=implode(',',array_map('intval',$countryIds));
$sql="SELECT h.id,h.country_id,h.name,MAX(o.observed_at) AS last_observed_at
      FROM catalog_hotels h
      JOIN tour_price_observations o ON o.hotel_id=h.id AND o.country_id=h.country_id
     WHERE h.is_active=1
       AND h.country_id IN ({$countrySql})
       AND (h.region_id IS NULL OR h.region_id=0 OR h.region_name IS NULL OR h.region_name='')
       AND o.observed_at>=DATE_SUB(NOW(),INTERVAL {$days} DAY)
       AND o.departure_date>=CURDATE()
       AND o.price>0 AND o.currency='RUB'
     GROUP BY h.id,h.country_id,h.name
     ORDER BY last_observed_at DESC,h.id ASC
     LIMIT {$limit}";
$candidates=$pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC)?:[];
if($candidates===[]){echo "OBSERVED_HOTEL_TAXONOMY_OK candidates=0 enriched=0 unresolved=0 not_found=0 errors=0\n";exit(0);}
$update=$pdo->prepare("UPDATE catalog_hotels SET region_id=:region_id,region_name=:region_name,subregion_id=:subregion_id,subregion_name=:subregion_name,synced_at=:synced WHERE id=:id AND country_id=:country");
$enriched=0;$unresolved=0;$notFound=0;$errors=0;
$now=(new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
foreach($candidates as $candidate){
    $id=(int)($candidate['id']??0);$countryId=(int)($candidate['country_id']??0);
    if($id<=0||$countryId<=0)continue;
    try{
        $row=oht_detail_row(v2_data_tv_get('/hotels/'.$id),$id);
        $taxonomy=oht_taxonomy_from_detail($pdo,$row,$countryId);
        if(($taxonomy['region_id']??null)===null||trim((string)($taxonomy['region_name']??''))===''){$unresolved++;continue;}
        $update->execute([
            'region_id'=>$taxonomy['region_id'],'region_name'=>$taxonomy['region_name'],
            'subregion_id'=>$taxonomy['subregion_id'],'subregion_name'=>$taxonomy['subregion_name'],
            'synced'=>$now,'id'=>$id,'country'=>$countryId,
        ]);
        $enriched++;
    }catch(Throwable $e){
        if(str_contains($e->getMessage(),'HTTP 404')){$notFound++;}
        else{$errors++;fwrite(STDERR,'OBSERVED_HOTEL_TAXONOMY_ERROR hotel='.$id.' '.mb_substr($e->getMessage(),0,240)."\n");}
    }
    usleep(150000);
}
echo 'OBSERVED_HOTEL_TAXONOMY_OK candidates='.count($candidates).' enriched='.$enriched.' unresolved='.$unresolved.' not_found='.$notFound.' errors='.$errors."\n";
