<?php
declare(strict_types=1);

/**
 * First-party resort identities with fresh future tour observations.
 *
 * Resort identity is taken from the refreshed first-party hotel catalog. Price
 * observations are evidence that those catalog hotels have current/future live
 * inventory; their own region_id is intentionally not required because older
 * observation rows may legitimately have region_id=NULL.
 *
 * Structural source only: no publication/indexation side effects.
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
      FROM tour_price_observations o
      JOIN catalog_hotels h ON h.id=o.hotel_id
       AND h.country_id=o.country_id
       AND h.is_active=1
      JOIN catalog_regions cr ON cr.id=h.region_id
       AND cr.country_id=h.country_id
       AND cr.is_active=1
      JOIN catalog_countries c ON c.id=h.country_id AND c.is_active=1
     WHERE c.slug IN ('egypt','maldives')
       AND cr.name IS NOT NULL AND cr.name<>''
       AND cr.slug IS NOT NULL AND cr.slug<>''
       AND c.name IS NOT NULL AND c.name<>''
       AND o.observed_at>=DATE_SUB(NOW(),INTERVAL 30 DAY)
       AND o.departure_date>=CURDATE()
       AND o.price>0 AND o.currency='RUB'
     GROUP BY cr.id,cr.country_id,cr.name,cr.slug,c.slug,c.name
     HAVING COUNT(*)>0 AND COUNT(DISTINCT o.hotel_id)>0
     ORDER BY observation_count DESC,hotel_count DESC,last_observed_at DESC,cr.id ASC
     LIMIT {$limit}";
    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC)?:[];
}
