<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'){
    http_response_code(404);
    exit;
}

require_once __DIR__.'/db-v1.php';
require_once __DIR__.'/seo-inventory-opportunity-engine-v1.php';
$launchSlice=dirname(__DIR__).'/seo-launch-slice-v1.php';
if(is_file($launchSlice)) require_once $launchSlice;

$limitPerType=50;
foreach(array_slice($argv,1) as $arg){
    if(str_starts_with($arg,'--limit-per-type=')){
        $value=(int)substr($arg,17);
        if($value<=0||$value>500){
            fwrite(STDERR,"SEO_INVENTORY_OPPORTUNITY_FAILED invalid_limit_per_type\n");
            exit(2);
        }
        $limitPerType=$value;
    }
}

$metricSql=<<<'SQL'
        COUNT(*) AS observations_total,
        SUM(o.observed_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)) AS observations_1d,
        SUM(o.observed_at >= DATE_SUB(NOW(), INTERVAL 3 DAY)) AS observations_3d,
        SUM(o.observed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) AS observations_7d,
        SUM(o.observed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) AS observations_30d,
        COUNT(DISTINCT o.hotel_id) AS hotels_total,
        COUNT(DISTINCT CASE WHEN o.observed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN o.hotel_id END) AS hotels_30d,
        COUNT(DISTINCT o.departure_id) AS departures_total,
        COUNT(DISTINCT CASE WHEN o.observed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN o.departure_id END) AS departures_30d,
        MIN(CASE WHEN o.observed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN o.price END) AS min_price_30d,
        MAX(CASE WHEN o.observed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN o.price END) AS max_price_30d,
        MIN(o.observed_at) AS oldest_observed_at,
        MAX(o.observed_at) AS newest_observed_at,
        DATEDIFF(MAX(o.observed_at),MIN(o.observed_at)) AS history_depth_days
SQL;

$baseWhere="o.departure_date >= CURDATE() AND o.price > 0 AND o.currency='RUB'";

$queries=[];
$queries['country']="SELECT
        'country' AS candidate_type,
        c.id AS country_id,c.name AS country_name,c.slug AS country_slug,
        NULL AS region_id,NULL AS region_name,NULL AS region_slug,
        NULL AS departure_year,NULL AS departure_month,
{$metricSql}
    FROM tour_price_observations o
    INNER JOIN catalog_countries c ON c.id=o.country_id
    WHERE {$baseWhere}
    GROUP BY c.id,c.name,c.slug
    HAVING observations_30d > 0
    ORDER BY observations_30d DESC,hotels_30d DESC,departures_30d DESC,c.id ASC";

$queries['resort']="SELECT
        'resort' AS candidate_type,
        c.id AS country_id,c.name AS country_name,c.slug AS country_slug,
        r.id AS region_id,r.name AS region_name,r.slug AS region_slug,
        NULL AS departure_year,NULL AS departure_month,
{$metricSql}
    FROM tour_price_observations o
    INNER JOIN catalog_countries c ON c.id=o.country_id
    INNER JOIN catalog_regions r ON r.id=o.region_id AND r.country_id=o.country_id
    WHERE {$baseWhere}
    GROUP BY c.id,c.name,c.slug,r.id,r.name,r.slug
    HAVING observations_30d > 0
    ORDER BY observations_30d DESC,hotels_30d DESC,departures_30d DESC,c.id ASC,r.id ASC";

$queries['country_month']="SELECT
        'country_month' AS candidate_type,
        c.id AS country_id,c.name AS country_name,c.slug AS country_slug,
        NULL AS region_id,NULL AS region_name,NULL AS region_slug,
        o.departure_year,o.departure_month,
{$metricSql}
    FROM tour_price_observations o
    INNER JOIN catalog_countries c ON c.id=o.country_id
    WHERE {$baseWhere}
    GROUP BY c.id,c.name,c.slug,o.departure_year,o.departure_month
    HAVING observations_30d > 0
    ORDER BY observations_30d DESC,hotels_30d DESC,departures_30d DESC,c.id ASC,o.departure_year ASC,o.departure_month ASC";

$queries['resort_month']="SELECT
        'resort_month' AS candidate_type,
        c.id AS country_id,c.name AS country_name,c.slug AS country_slug,
        r.id AS region_id,r.name AS region_name,r.slug AS region_slug,
        o.departure_year,o.departure_month,
{$metricSql}
    FROM tour_price_observations o
    INNER JOIN catalog_countries c ON c.id=o.country_id
    INNER JOIN catalog_regions r ON r.id=o.region_id AND r.country_id=o.country_id
    WHERE {$baseWhere}
    GROUP BY c.id,c.name,c.slug,r.id,r.name,r.slug,o.departure_year,o.departure_month
    HAVING observations_30d > 0
    ORDER BY observations_30d DESC,hotels_30d DESC,departures_30d DESC,c.id ASC,r.id ASC,o.departure_year ASC,o.departure_month ASC";

try{
    $pdo=v2_data_db();
    $rows=[];$queryCounts=[];
    foreach($queries as $type=>$sql){
        $part=$pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        $queryCounts[$type]=count($part);
        foreach($part as $row) $rows[]=$row;
    }
    $controlledPaths=function_exists('v2_seo_controlled_launch_paths')?v2_seo_controlled_launch_paths():[];
    $report=v2_seo_inventory_opportunity_report($rows,$limitPerType,$controlledPaths);
    $report['collector']=[
        'state'=>'read_only_first_party_db_aggregation',
        'currency'=>'RUB',
        'departure_scope'=>'future_or_today_departures_only',
        'recent_reporting_window_days'=>30,
        'query_counts'=>$queryCounts,
        'query_result_semantics'=>'all_observed_groups_before_output_limit',
        'source_table'=>'tour_price_observations',
        'catalog_tables'=>['catalog_countries','catalog_regions'],
        'writes_performed'=>false,
    ];
    $stable=$report;
    unset($stable['generated_at_utc'],$stable['evidence_sha256']);
    $report['evidence_sha256']=hash('sha256',json_encode($stable,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
    echo json_encode($report,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),"\n";
}catch(Throwable $e){
    fwrite(STDERR,'SEO_INVENTORY_OPPORTUNITY_FAILED '.mb_substr($e->getMessage(),0,1000)."\n");
    exit(1);
}
