<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/db-v1.php';
require_once dirname(__DIR__).'/seo-core-hotel-cohort-v1.php';

$limit=500;
foreach($argv as $arg){
    if(str_starts_with($arg,'--limit=')){
        $v=filter_var(substr($arg,8),FILTER_VALIDATE_INT);
        if($v!==false)$limit=max(1,min(500,(int)$v));
    }
}

$pdo=v2_data_db();
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
    LIMIT 500";
$rows=$pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC)?:[];
$result=v2_seo_core_hotel_cohort_records($rows,$limit);
$result['source']='first_party_catalog_plus_price_observations';
$result['selection_window_days']=30;
$result['country_scope']=[1,4,8];
$result['ranking_note']='Cohort order reflects observed inventory coverage and recency only; it is not a hotel quality, popularity or rating claim.';
echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),"\n";
