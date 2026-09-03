<?php
declare(strict_types=1);

/**
 * First-party resort identities with fresh future tour observations.
 * This is structural source data only; no publication/indexation side effects.
 */
function v2_seo_core_resort_cohort_source_rows(PDO $pdo,int $limit=80): array
{
    $limit=max(1,min(200,$limit));
    $sql="SELECT
        cr.id AS region_id,
        cr.country_id,
        cr.name AS region_name,
        cr.slug AS region_slug,
        c.slug AS country_slug,
        c.name AS country_name,
        COUNT(*) AS observation_count,
        COUNT(DISTINCT o.hotel_id) AS hotel_count,
        MAX(o.observed_at) AS last_observed_at
      FROM catalog_regions cr
      JOIN catalog_countries c ON c.id=cr.country_id AND c.is_active=1
      JOIN tour_price_observations o ON o.region_id=cr.id AND o.country_id=cr.country_id
     WHERE cr.country_id IN (1,8)
       AND cr.name IS NOT NULL AND cr.name<>''
       AND cr.slug IS NOT NULL AND cr.slug<>''
       AND c.slug IS NOT NULL AND c.slug<>''
       AND o.observed_at>=DATE_SUB(NOW(),INTERVAL 30 DAY)
       AND o.departure_date>=CURDATE()
       AND o.price>0 AND o.currency='RUB'
     GROUP BY cr.id,cr.country_id,cr.name,cr.slug,c.slug,c.name
     HAVING COUNT(*)>0 AND COUNT(DISTINCT o.hotel_id)>0
     ORDER BY observation_count DESC,hotel_count DESC,last_observed_at DESC,cr.id ASC
     LIMIT {$limit}";
    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC)?:[];
}
