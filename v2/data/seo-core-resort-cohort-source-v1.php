<?php
declare(strict_types=1);

/**
 * First-party resort identities with fresh future tour observations.
 *
 * Resort identity comes directly from the refreshed first-party hotel catalog.
 * Price observations only prove current/future live inventory coverage. We do
 * not require observation.region_id or a second catalog_regions join because
 * both can be incomplete for otherwise valid first-party hotel rows.
 *
 * Structural source only: no publication/indexation side effects.
 */
function v2_seo_core_resort_cohort_source_rows(PDO $pdo,int $limit=80): array
{
    $limit=max(1,min(200,$limit));
    $sql="SELECT
        h.region_id AS region_id,
        h.country_id,
        h.region_name AS region_name,
        c.slug AS country_slug,
        c.name AS country_name,
        COUNT(*) AS observation_count,
        COUNT(DISTINCT o.hotel_id) AS hotel_count,
        MAX(o.observed_at) AS last_observed_at
      FROM tour_price_observations o
      JOIN catalog_hotels h ON h.id=o.hotel_id
       AND h.country_id=o.country_id
       AND h.is_active=1
      JOIN catalog_countries c ON c.id=h.country_id AND c.is_active=1
     WHERE h.country_id IN (1,8)
       AND c.slug IN ('egypt','maldives')
       AND h.region_id IS NOT NULL AND h.region_id>0
       AND h.region_name IS NOT NULL AND h.region_name<>''
       AND c.name IS NOT NULL AND c.name<>''
       AND o.observed_at>=DATE_SUB(NOW(),INTERVAL 30 DAY)
       AND o.departure_date>=CURDATE()
       AND o.price>0 AND o.currency='RUB'
     GROUP BY h.region_id,h.country_id,h.region_name,c.slug,c.name
     HAVING COUNT(*)>0 AND COUNT(DISTINCT o.hotel_id)>0
     ORDER BY observation_count DESC,hotel_count DESC,last_observed_at DESC,h.region_id ASC
     LIMIT {$limit}";
    $rows=$pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC)?:[];
    $out=[];$seenSlugs=[];
    foreach($rows as $row){
        if(!is_array($row))continue;
        $regionId=(int)($row['region_id']??0);
        $countrySlug=trim((string)($row['country_slug']??''));
        $regionName=trim((string)($row['region_name']??''));
        if($regionId<=0||$countrySlug===''||$regionName==='')continue;
        $slug=v2_data_slug($regionName);
        if($slug==='')continue;
        $slugKey=$countrySlug.'/'.$slug;
        if(isset($seenSlugs[$slugKey])&&$seenSlugs[$slugKey]!==$regionId)$slug.='-'.$regionId;
        $seenSlugs[$countrySlug.'/'.$slug]=$regionId;
        $row['region_slug']=$slug;
        $out[]=$row;
    }
    return $out;
}
