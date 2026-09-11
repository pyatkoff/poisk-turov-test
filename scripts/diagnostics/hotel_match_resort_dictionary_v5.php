<?php
declare(strict_types=1);
ini_set('display_errors','0');ini_set('log_errors','0');
const OP='hotel-match-resort-dictionary-1971-20260911-v5';
require_once getcwd().'/config.php';
$dbHelper=is_file(getcwd().'/data/db-v1.php')?getcwd().'/data/db-v1.php':getcwd().'/v2/data/db-v1.php';require_once $dbHelper;
$pdo=v2_data_db();
$regions=$pdo->query("SELECT r.id,r.name,COUNT(h.id) AS hotel_count FROM catalog_regions r LEFT JOIN catalog_hotels h ON h.region_id=r.id AND h.is_active=1 WHERE r.country_id=4 AND r.is_active=1 GROUP BY r.id,r.name ORDER BY hotel_count DESC,r.name")->fetchAll(PDO::FETCH_ASSOC);
$subs=$pdo->query("SELECT s.id,s.region_id,s.name,COUNT(h.id) AS hotel_count FROM catalog_subregions s JOIN catalog_regions r ON r.id=s.region_id LEFT JOIN catalog_hotels h ON h.subregion_id=s.id AND h.is_active=1 WHERE r.country_id=4 AND r.is_active=1 AND s.is_active=1 GROUP BY s.id,s.region_id,s.name ORDER BY hotel_count DESC,s.name")->fetchAll(PDO::FETCH_ASSOC);
echo 'MATCH_RD_JSON:'.json_encode(['status'=>'completed','operation_id'=>OP,'country_id'=>4,'regions'=>$regions,'subregions'=>$subs,'supplier_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'booking_calls'=>0,'lead_calls'=>0],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;
