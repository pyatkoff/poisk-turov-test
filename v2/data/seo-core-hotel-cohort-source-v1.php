<?php
declare(strict_types=1);

function v2_seo_core_hotel_cohort_source_rows(PDO $pdo,int $limit=500): array
{
    $limit=max(1,min(500,$limit));
    $sql="SELECT
        h.id AS hotel_id,
        h.country_id,
        h.name AS hotel_name,
        h.slug AS hotel_slug,
        c.slug AS country_slug,
        COALESCE(c.name,h.country_name) AS country_name,
        COUNT(*) AS observation_count,
        MAX(o.observed_at) AS last_observed_at
      FROM catalog_hotels h
      JOIN catalog_countries c ON c.id=h.country_id AND c.is_active=1
      JOIN tour_price_observations o ON o.hotel_id=h.id AND o.country_id=h.country_id
     WHERE h.is_active=1
       AND h.country_id IN (1,4,8)
       AND h.slug IS NOT NULL AND h.slug<>''
       AND c.slug IS NOT NULL AND c.slug<>''
       AND o.observed_at>=DATE_SUB(NOW(),INTERVAL 30 DAY)
       AND o.departure_date>=CURDATE()
       AND o.price>0 AND o.currency='RUB'
     GROUP BY h.id,h.country_id,h.name,h.slug,c.slug,c.name,h.country_name
     ORDER BY observation_count DESC,last_observed_at DESC,h.id ASC
     LIMIT {$limit}";
    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC)?:[];
}
