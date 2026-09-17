<?php
declare(strict_types=1);

function fail(string $m): never { fwrite(STDERR,$m."\n"); exit(2); }
function original_sql(): string { return <<<'SQL'
INSERT INTO tour_operator_identity_observations (
 fingerprint,first_seen_at,last_seen_at,observation_count,source,search_id,country_id,region_id,subregion_id,
 hotel_id,hotel_name,region_name,subregion_name,latitude,longitude,operator_id,operator_name,tour_id,
 operator_link,operator_link_host,operator_link_path,operator_link_query,native_id_type,native_id_value,native_id_conflict
)
SELECT SHA2(CONCAT('tourvisor|',p.hotel_id,'|',p.operator_id),256),
 p.first_seen_at,p.last_seen_at,p.observation_count,'historical_backfill',NULL,
 c.country_id,c.region_id,c.subregion_id,c.id,c.name,c.region_name,c.subregion_name,c.latitude,c.longitude,
 p.operator_id,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0
FROM (
 SELECT hotel_id,operator_id,MIN(observed_at) first_seen_at,MAX(observed_at) last_seen_at,
        GREATEST(1,COUNT(DISTINCT search_id)) observation_count
 FROM tour_price_observations
 WHERE operator_id IS NOT NULL AND operator_id>0
 GROUP BY hotel_id,operator_id
) p
JOIN catalog_hotels c ON c.id=p.hotel_id
ON DUPLICATE KEY UPDATE
 first_seen_at=LEAST(first_seen_at,VALUES(first_seen_at)),
 last_seen_at=GREATEST(last_seen_at,VALUES(last_seen_at)),
 observation_count=GREATEST(observation_count,VALUES(observation_count)),
 country_id=VALUES(country_id),region_id=COALESCE(VALUES(region_id),region_id),subregion_id=COALESCE(VALUES(subregion_id),subregion_id),
 hotel_name=COALESCE(VALUES(hotel_name),hotel_name),region_name=COALESCE(VALUES(region_name),region_name),subregion_name=COALESCE(VALUES(subregion_name),subregion_name),
 latitude=COALESCE(VALUES(latitude),latitude),longitude=COALESCE(VALUES(longitude),longitude)
SQL; }
function qualified_sql(): string { return str_replace([
 'LEAST(first_seen_at,VALUES(first_seen_at))','GREATEST(last_seen_at,VALUES(last_seen_at))','GREATEST(observation_count,VALUES(observation_count))',
 'COALESCE(VALUES(region_id),region_id)','COALESCE(VALUES(subregion_id),subregion_id)','COALESCE(VALUES(hotel_name),hotel_name)',
 'COALESCE(VALUES(region_name),region_name)','COALESCE(VALUES(subregion_name),subregion_name)','COALESCE(VALUES(latitude),latitude)','COALESCE(VALUES(longitude),longitude)'
],[
 'LEAST(tour_operator_identity_observations.first_seen_at,VALUES(first_seen_at))','GREATEST(tour_operator_identity_observations.last_seen_at,VALUES(last_seen_at))','GREATEST(tour_operator_identity_observations.observation_count,VALUES(observation_count))',
 'COALESCE(VALUES(region_id),tour_operator_identity_observations.region_id)','COALESCE(VALUES(subregion_id),tour_operator_identity_observations.subregion_id)','COALESCE(VALUES(hotel_name),tour_operator_identity_observations.hotel_name)',
 'COALESCE(VALUES(region_name),tour_operator_identity_observations.region_name)','COALESCE(VALUES(subregion_name),tour_operator_identity_observations.subregion_name)','COALESCE(VALUES(latitude),tour_operator_identity_observations.latitude)','COALESCE(VALUES(longitude),tour_operator_identity_observations.longitude)'
 ], original_sql()); }

$dsn=getenv('MYSQL_DSN') ?: 'mysql:host=127.0.0.1;port=3306;dbname=matchtest;charset=utf8mb4';
$user=getenv('MYSQL_USER') ?: 'root'; $pass=getenv('MYSQL_PASSWORD') ?: 'root';
$db=new PDO($dsn,$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$db->exec("SET SESSION sql_mode=''");
foreach(['tour_operator_identity_observations','tour_price_observations','catalog_hotels'] as $t) $db->exec("DROP TABLE IF EXISTS `$t`");
$db->exec("CREATE TABLE catalog_hotels (id INT UNSIGNED PRIMARY KEY,country_id INT UNSIGNED NOT NULL,region_id INT UNSIGNED NULL,region_name VARCHAR(180) NULL,subregion_id INT UNSIGNED NULL,subregion_name VARCHAR(180) NULL,name VARCHAR(255) NOT NULL,latitude DECIMAL(10,7) NULL,longitude DECIMAL(10,7) NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$db->exec("CREATE TABLE tour_price_observations (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,observed_at DATETIME NOT NULL,search_id BIGINT UNSIGNED NULL,hotel_id INT UNSIGNED NOT NULL,operator_id INT UNSIGNED NULL) ENGINE=InnoDB");
$db->exec("CREATE TABLE tour_operator_identity_observations (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,fingerprint CHAR(64) NOT NULL,first_seen_at DATETIME NOT NULL,last_seen_at DATETIME NOT NULL,observation_count INT UNSIGNED NOT NULL DEFAULT 1,source VARCHAR(40) NOT NULL DEFAULT 'user_search',search_id BIGINT UNSIGNED NULL,country_id INT UNSIGNED NOT NULL,region_id INT UNSIGNED NULL,subregion_id INT UNSIGNED NULL,hotel_id INT UNSIGNED NOT NULL,hotel_name VARCHAR(255) NULL,region_name VARCHAR(180) NULL,subregion_name VARCHAR(180) NULL,latitude DECIMAL(10,7) NULL,longitude DECIMAL(10,7) NULL,operator_id INT UNSIGNED NOT NULL,operator_name VARCHAR(180) NULL,tour_id VARCHAR(220) NULL,operator_link VARCHAR(2048) NULL,operator_link_host VARCHAR(255) NULL,operator_link_path VARCHAR(1200) NULL,operator_link_query VARCHAR(1200) NULL,native_id_type VARCHAR(40) NULL,native_id_value VARCHAR(255) NULL,native_id_conflict TINYINT(1) NOT NULL DEFAULT 0,UNIQUE KEY uq_operator_identity_fingerprint(fingerprint),UNIQUE KEY uq_operator_identity_hotel_operator(hotel_id,operator_id),KEY idx_operator_identity_native(operator_id,native_id_type,native_id_value,last_seen_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$db->exec("INSERT INTO catalog_hotels VALUES (101,4,10,'Antalya',11,'Alanya','ALPHA HOTEL',36.5,31.9),(202,1,20,'Hurghada',21,'Soma Bay','BETA RESORT',26.8,33.9)");
$db->exec("INSERT INTO tour_price_observations(observed_at,search_id,hotel_id,operator_id) VALUES ('2026-09-17 10:00:00',1,101,25),('2026-09-17 11:00:00',2,101,25),('2026-09-17 12:00:00',3,202,43)");

function run_variant(PDO $db,string $sql): array {
 try { $db->beginTransaction(); $affected=$db->exec($sql); $count=(int)$db->query('SELECT COUNT(*) FROM tour_operator_identity_observations')->fetchColumn(); $db->rollBack(); return ['ok'=>true,'affected'=>$affected,'rows_in_tx'=>$count,'sqlstate'=>'00000','driver_code'=>0]; }
 catch(PDOException $e){ if($db->inTransaction())$db->rollBack(); $i=$e->errorInfo; return ['ok'=>false,'affected'=>null,'rows_in_tx'=>0,'sqlstate'=>(string)($i[0]??$e->getCode()),'driver_code'=>(int)($i[1]??0)]; }
}
$original=run_variant($db,original_sql());
$qualified=run_variant($db,qualified_sql());
$result=['mysql_version'=>(string)$db->query('SELECT VERSION()')->fetchColumn(),'original'=>$original,'qualified'=>$qualified];
echo json_encode($result,JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
if(!$qualified['ok'] || $qualified['rows_in_tx']!==2) fail('qualified variant did not produce exactly 2 rows');
if($original['ok'] && $original['rows_in_tx']!==2) fail('original succeeded with wrong row count');
echo "hotel_match_tourvisor_identity_backfill_mysql8: PASS\n";
