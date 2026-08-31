<?php
/**
 * Read-only coverage/confidence planner for AnyTour accumulated tour observations.
 *
 * Reports coverage by persisted flight-availability tier and ranks active
 * departure→country pairs that need either first coverage or deeper independent
 * price history. Never starts Tourvisor searches and never mutates the database.
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/db-v1.php';

function collection_coverage_arg(array $argv, string $name, ?string $default = null): ?string {
    foreach ($argv as $arg) if (str_starts_with($arg, '--'.$name.'=')) return substr($arg, strlen($name)+3);
    return $default;
}
function collection_coverage_limit(array $argv): int {
    $v=filter_var(collection_coverage_arg($argv,'limit','25'),FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>500]]);
    return $v===false?25:(int)$v;
}
function collection_confidence(array $r): string {
    $s=(int)$r['distinct_search_count']; $d=(int)$r['distinct_observation_days'];
    if ($s>=30 && $d>=7) return 'history_ready';
    if ($s>=15 && $d>=3) return 'guarded_delta_ready';
    if ($s>=5 && $d>=2) return 'good_price_only';
    return 'collect_more';
}
function collection_flight_tier(array $r): string {
    if ((int)$r['is_direct_charter']===1) return 'direct_charter';
    if ((int)$r['is_charter']===1) return 'charter';
    if ((int)$r['is_direct']===1) return 'direct';
    return 'general';
}
function collection_flight_rank(string $tier): int { return ['direct_charter'=>4,'charter'=>3,'direct'=>2,'general'=>1][$tier]??0; }
function collection_coverage_score(array $r, DateTimeImmutable $now): int {
    $obs=(int)$r['observation_count']; $s=(int)$r['distinct_search_count']; $d=(int)$r['distinct_observation_days'];
    $last=trim((string)($r['last_observed_at']??'')); $age=9999;
    if ($last!=='') try { $age=(int)floor(max(0,$now->getTimestamp()-(new DateTimeImmutable($last))->getTimestamp())/3600); } catch(Throwable) {}
    $base=$obs===0?1000000:(max(0,30-$s)*1000)+(max(0,7-$d)*5000)+min(720,$age);
    return $base + collection_flight_rank((string)$r['flight_tier'])*10000000;
}
function collection_coverage_rows(PDO $pdo): array {
    foreach (['catalog_departure_countries','catalog_departure_countries_direct','catalog_departure_countries_charter','catalog_departure_countries_direct_charter'] as $table) {
        if ($pdo->query("SHOW TABLES LIKE ".$pdo->quote($table))->fetchColumn()===false) throw new RuntimeException($table.' is missing');
    }
    $sql="SELECT dc.departure_id,d.name departure_name,dc.country_id,c.name country_name,
      IF(ddc.departure_id IS NULL,0,1) is_direct,IF(ch.departure_id IS NULL,0,1) is_charter,IF(dch.departure_id IS NULL,0,1) is_direct_charter,
      COUNT(o.id) observation_count,COUNT(DISTINCT NULLIF(o.search_id,'')) distinct_search_count,
      COUNT(DISTINCT DATE(o.observed_at)) distinct_observation_days,COUNT(DISTINCT o.hotel_id) observed_hotel_count,
      COUNT(DISTINCT o.departure_date) observed_departure_date_count,MIN(o.observed_at) first_observed_at,MAX(o.observed_at) last_observed_at
      FROM catalog_departure_countries dc
      JOIN catalog_departures d ON d.id=dc.departure_id AND d.is_active=1
      JOIN catalog_countries c ON c.id=dc.country_id AND c.is_active=1
      LEFT JOIN catalog_departure_countries_direct ddc ON ddc.departure_id=dc.departure_id AND ddc.country_id=dc.country_id AND ddc.is_active=1
      LEFT JOIN catalog_departure_countries_charter ch ON ch.departure_id=dc.departure_id AND ch.country_id=dc.country_id AND ch.is_active=1
      LEFT JOIN catalog_departure_countries_direct_charter dch ON dch.departure_id=dc.departure_id AND dch.country_id=dc.country_id AND dch.is_active=1
      LEFT JOIN tour_price_observations o ON o.departure_id=dc.departure_id AND o.country_id=dc.country_id
      WHERE dc.is_active=1 GROUP BY dc.departure_id,d.name,dc.country_id,c.name,ddc.departure_id,ch.departure_id,dch.departure_id";
    $r=$pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC); return is_array($r)?$r:[];
}
function blank_stats(): array { return ['pairs'=>0,'unobserved'=>0,'collect_more'=>0,'good_price_only'=>0,'guarded_delta_ready'=>0,'history_ready'=>0,'observations'=>0]; }
try {
    $limit=collection_coverage_limit($argv); $format=strtolower((string)collection_coverage_arg($argv,'format','text')); if(!in_array($format,['text','json'],true))$format='text';
    $rows=collection_coverage_rows(v2_data_db()); $now=new DateTimeImmutable('now');
    $tiers=['all'=>blank_stats(),'direct_charter'=>blank_stats(),'charter'=>blank_stats(),'direct'=>blank_stats(),'general'=>blank_stats()];
    foreach($rows as &$r){ $r['flight_tier']=collection_flight_tier($r); $r['confidence']=collection_confidence($r); $r['priority_score']=collection_coverage_score($r,$now);
        foreach(['all',(string)$r['flight_tier']] as $t){$tiers[$t]['pairs']++;$tiers[$t]['observations']+=(int)$r['observation_count'];if((int)$r['observation_count']===0)$tiers[$t]['unobserved']++;$tiers[$t][(string)$r['confidence']]++;}
    } unset($r);
    usort($rows,static function($a,$b){$x=((int)$b['priority_score'])<=>((int)$a['priority_score']);if($x!==0)return $x;$x=((int)$a['observation_count'])<=>((int)$b['observation_count']);return $x!==0?$x:strcmp((string)$a['departure_name'].(string)$a['country_name'],(string)$b['departure_name'].(string)$b['country_name']);});
    $targets=array_slice(array_values(array_filter($rows,static fn($r)=>(string)$r['confidence']!=='history_ready')),0,$limit);
    $summary=['active_pairs'=>count($rows),'target_count'=>count($targets),'tiers'=>$tiers];
    if($format==='json'){echo json_encode(['ok'=>true,'summary'=>$summary,'targets'=>$targets],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."\n";exit;}
    echo "ANYTOUR_COLLECTION_COVERAGE_V2_OK\nactive_pairs=".count($rows)."\ntarget_count=".count($targets)."\n";
    foreach($tiers as $name=>$s) printf("TIER %s pairs=%d unobserved=%d collect_more=%d good_price_only=%d guarded_delta_ready=%d history_ready=%d observations=%d\n",$name,$s['pairs'],$s['unobserved'],$s['collect_more'],$s['good_price_only'],$s['guarded_delta_ready'],$s['history_ready'],$s['observations']);
    echo "TARGETS\n";
    foreach($targets as $i=>$r) printf("%d\tflight=%s\tconfidence=%s\tdeparture=%d:%s\tcountry=%d:%s\tobs=%d\tsearches=%d\tdays=%d\thotels=%d\tdates=%d\tlast=%s\tscore=%d\n",$i+1,$r['flight_tier'],$r['confidence'],(int)$r['departure_id'],$r['departure_name'],(int)$r['country_id'],$r['country_name'],(int)$r['observation_count'],(int)$r['distinct_search_count'],(int)$r['distinct_observation_days'],(int)$r['observed_hotel_count'],(int)$r['observed_departure_date_count'],(string)($r['last_observed_at']??'-'),(int)$r['priority_score']);
} catch(Throwable $e){fwrite(STDERR,'ANYTOUR_COLLECTION_COVERAGE_FAILED: '.$e->getMessage()."\n");exit(1);}
