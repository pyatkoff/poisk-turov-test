<?php
declare(strict_types=1);
if (!defined('FC_LIBRARY_ONLY')) define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/hotel_match_anex_sold_details_strong_review.php';

const HMASDA_OPERATION = 'hotel-match-anex-sold-details-accept-1971-20260911-v1';
const HMASDA_SOURCE_OPERATION = 'hotel-match-anex-sold-details-strong-review-1971-20260911-v1';
const HMASDA_SOURCE_RESULT_SHA256 = '6ca31304fbb40fad165add17f50175555a3eb50837c8a3a3340e2265fc9b1cb9';
const HMASDA_DIRECT_RESULT_SHA256 = '86f3cbe232a262fa6c6bcd5d87a56af8167ce52f1dc7b589c7d4605fd57cc7fa';

function hmasda_require_transactional(PDO $db): void {
    $tables=['catalog_hotels','catalog_hotel_details','hotel_aliases','anex_hotel_search_mappings','anex_hotel_decisions','anex_review_pair_exclusions','andromeda_hotel_identities'];
    $q=$db->prepare("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?");
    foreach($tables as $table){$q->execute([$table]);$engine=$q->fetchColumn();if($engine===false)throw new RuntimeException('required_table_missing:'.$table);if(strcasecmp((string)$engine,'InnoDB')!==0)throw new RuntimeException('required_table_not_innodb:'.$table);}
}
function hmasda_source_ids(array $strong): array {
    if(($strong['status']??null)!=='completed'||($strong['operation_id']??null)!==HMASDA_SOURCE_OPERATION||($strong['database_writes']??null)!==0||($strong['mapping_writes']??null)!==0||($strong['supplier_calls']??null)!==0)throw new RuntimeException('strong_source_invalid');
    if(($strong['source_result_sha256']??null)!==HMASDA_DIRECT_RESULT_SHA256||!is_array($strong['prepared']??null)||count($strong['prepared'])!==106)throw new RuntimeException('strong_source_invalid');
    $ids=[];foreach($strong['prepared'] as $row){$id=(int)($row['anex_hotel_id']??0);if($id<1||isset($ids[$id]))throw new RuntimeException('strong_source_ids_invalid');$ids[$id]=true;}
    return $ids;
}
function hmasda_original_strong(array $best,float $margin): bool {
    $dist=$best['distance_m']??null;$shared=(int)($best['name']['shared']??0);$score=(float)($best['score']??0);$geo=(bool)($best['geo_strong']??false);
    if(!($best['name']['critical_ok']??false)||$shared<2)return false;
    if($dist!==null&&(float)$dist>5000.0)return false;
    return ($geo&&$score>=0.72&&$margin>=0.10)||($dist!==null&&(float)$dist<=120.0&&$score>=0.68&&$margin>=0.08);
}
function hmasda_candidate_safe(array $best,float $margin): bool {
    if(!hmasda_original_strong($best,$margin))return false;
    $dist=$best['distance_m']??null;
    if($dist!==null&&(float)$dist<=250.0)return true;
    return (bool)($best['andromeda_bridge']??false)
        &&(bool)($best['place_match']??false)
        &&(float)($best['name']['score']??0)>=0.75
        &&($dist===null||(float)$dist<=1000.0);
}
function hmasda_target_names(string $name,array $aliases): array {
    $out=[];foreach(array_merge([$name],$aliases) as $value){foreach(hmasr_variants((string)$value) as $variant){$variant=trim($variant);if($variant!=='')$out[$variant]=true;}}
    return array_keys($out);
}
function hmasda_accept(PDO $db,array $directResult,array $directPlan,array $strongResult,string $operation=HMASDA_OPERATION,int $maxWrites=100): array {
    if($operation!==HMASDA_OPERATION)throw new RuntimeException('operation_scope');if($maxWrites<1||$maxWrites>100)throw new RuntimeException('write_scope_limit_config');
    if(($directPlan['operation_id']??null)!==HMASR_SOURCE_OPERATION||!is_array($directPlan['target_context']??null))throw new RuntimeException('direct_plan_invalid');
    $allowed=hmasda_source_ids($strongResult);$sources=hmasr_source_payload($directResult,$directPlan);$sources=array_intersect_key($sources,$allowed);if(count($sources)>106)throw new RuntimeException('source_scope_limit');
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);hmasda_require_transactional($db);$db->exec('SET SESSION innodb_lock_wait_timeout=20');$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $before=fc_coverage($db);$rows=[];$stats=['source_allowed'=>count($allowed),'source_current'=>count($sources),'protected'=>0,'country_other'=>0,'no_two_token_candidate'=>0,'weak_best'=>0,'coordinate_conflict'=>0,'small_margin'=>0,'conservative_gate'=>0,'pair_excluded'=>0,'planned'=>0,'written'=>0,'live_written'=>0,'bridge_written'=>0,'direct_250_written'=>0,'bridge_geo_written'=>0];
    try{
        $db->beginTransaction();
        $manual=array_fill_keys(array_map('intval',$db->query('SELECT anex_hotel_id FROM anex_hotel_decisions ORDER BY anex_hotel_id FOR UPDATE')->fetchAll(PDO::FETCH_COLUMN)),true);
        $existing=array_fill_keys(array_map('intval',$db->query('SELECT anex_hotel_id FROM anex_hotel_search_mappings ORDER BY anex_hotel_id FOR UPDATE')->fetchAll(PDO::FETCH_COLUMN)),true);
        $excluded=[];foreach($db->query('SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions ORDER BY anex_hotel_id,catalog_hotel_id FOR UPDATE')->fetchAll(PDO::FETCH_ASSOC) as $r)$excluded[(int)$r['anex_hotel_id']][(int)$r['catalog_hotel_id']]=true;
        $andr=[];foreach($db->query("SELECT DISTINCT local_hotel_id FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IS NOT NULL ORDER BY local_hotel_id FOR UPDATE")->fetchAll(PDO::FETCH_COLUMN) as $id)$andr[(int)$id]=true;
        $hotels=[];$rawNames=[];$tokenIndex=[];
        $hq=$db->query('SELECT h.id,h.country_id,h.name,h.region_name,h.subregion_name,h.category,COALESCE(d.latitude,h.latitude) latitude,COALESCE(d.longitude,h.longitude) longitude FROM catalog_hotels h LEFT JOIN catalog_hotel_details d ON d.hotel_id=h.id WHERE h.is_active=1 AND h.country_id IN (1,2,4,8,9,10,12,16) ORDER BY h.country_id,h.id FOR UPDATE');
        foreach($hq->fetchAll(PDO::FETCH_ASSOC) as $h){$hid=(int)$h['id'];$country=(int)$h['country_id'];$hotels[$hid]=$h;$rawNames[$hid]=[(string)$h['name']];}
        $aq=$db->query('SELECT a.hotel_id,a.alias,a.normalized_alias,h.country_id FROM hotel_aliases a JOIN catalog_hotels h ON h.id=a.hotel_id WHERE h.is_active=1 AND h.country_id IN (1,2,4,8,9,10,12,16) ORDER BY h.country_id,a.hotel_id,a.id FOR UPDATE');
        foreach($aq->fetchAll(PDO::FETCH_ASSOC) as $a){$hid=(int)$a['hotel_id'];foreach([(string)$a['alias'],(string)$a['normalized_alias']] as $alias)if(trim($alias)!=='')$rawNames[$hid][]=$alias;}
        $names=[];foreach($hotels as $hid=>$h){$country=(int)$h['country_id'];$names[$hid]=hmasda_target_names((string)$h['name'],$rawNames[$hid]??[]);foreach($names[$hid] as $name)foreach(hmasr_tokens($name) as $token)$tokenIndex[$country][$token][$hid]=true;}
        $planned=[];
        foreach($sources as $id=>$src){$id=(int)$id;if(isset($manual[$id])||isset($existing[$id])){$stats['protected']++;continue;}$ctx=$src['context'];$d=$src['detail'];$country=(int)($ctx['country_id']??0);if(!isset(HMASR_CORE8[$country])){$stats['country_other']++;continue;}
            $sourceName=trim((string)($d['name']??''));$variants=hmasr_variants($sourceName);$tokens=[];foreach($variants as $variant)foreach(hmasr_tokens($variant) as $token)$tokens[$token]=true;$hits=[];foreach(array_keys($tokens) as $token)foreach(array_keys($tokenIndex[$country][$token]??[]) as $hid)$hits[(int)$hid]=($hits[(int)$hid]??0)+1;$candidateIds=[];foreach($hits as $hid=>$n)if($n>=2&&!isset($excluded[$id][$hid]))$candidateIds[]=(int)$hid;
            if(!$candidateIds){$stats['no_two_token_candidate']++;continue;}$scored=[];$slat=hmasr_coord($d['latitude']??null);$slon=hmasr_coord($d['longitude']??null);
            foreach($candidateIds as $hid){$h=$hotels[$hid];$name=hmasr_best_name($variants,$names[$hid]??[(string)$h['name']]);if(!$name['critical_ok'])continue;$dist=fc_dist($slat,$slon,$h['latitude']??null,$h['longitude']??null);$place=fc_place([(string)($d['region']??''),(string)($d['town']??'')],[(string)($h['region_name']??''),(string)($h['subregion_name']??'')]);$geoStrong=($dist!==null&&$dist<=1000)||$place;$coordinateStrong=$dist!==null&&$dist<=120;$nameStrong=$name['shared']>=2&&($name['jaccard']>=0.58||($name['ordered']&&$name['character']>=0.72)||$name['character']>=0.86);$coordNameStrong=$coordinateStrong&&$name['shared']>=2&&($name['jaccard']>=0.42||$name['character']>=0.72);if(!$nameStrong&&!$coordNameStrong)continue;$score=$name['score']+($coordinateStrong?0.24:(($dist!==null&&$dist<=1000)?0.12:0.0))+($place?0.08:0.0)+(isset($andr[$hid])?0.04:0.0);$scored[]=['target_local_hotel_id'=>$hid,'target_name'=>$h['name'],'target_region'=>$h['region_name'],'target_subregion'=>$h['subregion_name'],'distance_m'=>$dist===null?null:round($dist,2),'place_match'=>$place,'andromeda_bridge'=>isset($andr[$hid]),'name'=>$name,'score'=>round($score,6),'geo_strong'=>$geoStrong];}
            if(!$scored){$stats['weak_best']++;continue;}usort($scored,static fn($a,$b)=>$b['score']<=>$a['score'] ?: (($a['distance_m']??PHP_FLOAT_MAX)<=>($b['distance_m']??PHP_FLOAT_MAX)) ?: $a['target_local_hotel_id']<=>$b['target_local_hotel_id']);$best=$scored[0];$second=$scored[1]??null;$margin=$second===null?1.0:(float)$best['score']-(float)$second['score'];
            if($best['distance_m']!==null&&(float)$best['distance_m']>5000.0){$stats['coordinate_conflict']++;continue;}if(!hmasda_original_strong($best,$margin)){if($margin<0.08)$stats['small_margin']++;else $stats['weak_best']++;continue;}if(!hmasda_candidate_safe($best,$margin)){$stats['conservative_gate']++;continue;}$target=(int)$best['target_local_hotel_id'];if(isset($excluded[$id][$target])){$stats['pair_excluded']++;continue;}
            $route=$best['distance_m']!==null&&(float)$best['distance_m']<=250.0?'direct_250':'bridge_geo';$planned[$id]=['anex_hotel_id'=>$id,'country_id'=>$country,'source_name'=>$sourceName,'source_region'=>$d['region']??null,'source_town'=>$d['town']??null,'source_latitude'=>$slat,'source_longitude'=>$slon,'live'=>(bool)($ctx['live']??false),'search_count'=>(int)($ctx['search_count']??0),'last_seen_utc'=>$ctx['last_seen_utc']??null,'target_local_hotel_id'=>$target,'target_name'=>$best['target_name'],'target_region'=>$best['target_region'],'target_subregion'=>$best['target_subregion'],'distance_m'=>$best['distance_m'],'place_match'=>$best['place_match'],'andromeda_bridge'=>$best['andromeda_bridge'],'name'=>$best['name'],'score'=>$best['score'],'margin'=>round($margin,6),'second'=>$second===null?null:['target_local_hotel_id'=>$second['target_local_hotel_id'],'target_name'=>$second['target_name'],'score'=>$second['score'],'distance_m'=>$second['distance_m']],'rule'=>'direct_details_strong_current_conservative','route'=>$route];}
        $stats['planned']=count($planned);if(count($planned)>$maxWrites)throw new RuntimeException('write_scope_limit');
        $insert=$db->prepare("INSERT INTO anex_hotel_search_mappings (anex_hotel_id,catalog_hotel_id,match_class,scope,approval_policy,source_row_digest,mapping_digest,enabled) VALUES(?,?,'strong_candidate','preview',?,?,?,1)");$mappingDigest=fc_hash([$operation,'direct_details_strong_current_conservative_v1']);
        foreach($planned as $id=>$row){$id=(int)$id;$target=(int)$row['target_local_hotel_id'];if(isset($manual[$id])||isset($existing[$id])){$stats['protected']++;continue;}if(isset($excluded[$id][$target])){$stats['pair_excluded']++;continue;}$evidence=['operation_id'=>$operation,'lane'=>'MATCH','provider'=>'anex','rule'=>$row['rule'],'route'=>$row['route'],'anex_hotel_id'=>$id,'country_id'=>$row['country_id'],'target'=>$target,'source_name'=>$row['source_name'],'target_name'=>$row['target_name'],'source_region'=>$row['source_region'],'source_town'=>$row['source_town'],'target_region'=>$row['target_region'],'target_subregion'=>$row['target_subregion'],'identity_tokens'=>$row['name']['source_tokens'],'target_tokens'=>$row['name']['target_tokens'],'name_score'=>$row['name']['score'],'total_score'=>$row['score'],'margin'=>$row['margin'],'distance_m'=>$row['distance_m'],'place_match'=>$row['place_match'],'existing_andromeda_tourvisor_bridge'=>$row['andromeda_bridge'],'search_count'=>$row['search_count'],'last_seen_utc'=>$row['last_seen_utc'],'source_operation'=>HMASDA_SOURCE_OPERATION,'server_current'=>true];$sourceDigest=fc_hash($evidence);$insert->execute([$id,$target,FC_POLICY,$sourceDigest,$mappingDigest]);if($insert->rowCount()!==1)throw new RuntimeException('anex_insert_not_one:'.$id);$rows[$id]=['target'=>$target,'source_row_digest'=>$sourceDigest,'mapping_digest'=>$mappingDigest,'rule'=>$row['rule'],'route'=>$row['route'],'live'=>$row['live'],'bridge'=>$row['andromeda_bridge'],'distance_m'=>$row['distance_m'],'name_score'=>$row['name']['score'],'margin'=>$row['margin']];$existing[$id]=true;$stats['written']++;if($row['live'])$stats['live_written']++;if($row['andromeda_bridge'])$stats['bridge_written']++;if($row['route']==='direct_250')$stats['direct_250_written']++;else $stats['bridge_geo_written']++;}
        if($stats['written']>$maxWrites)throw new RuntimeException('write_scope_limit');$db->commit();
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    $read=$db->prepare('SELECT catalog_hotel_id,source_row_digest,mapping_digest,enabled,scope,approval_policy FROM anex_hotel_search_mappings WHERE anex_hotel_id=?');foreach($rows as $id=>$expected){$read->execute([(int)$id]);$actual=$read->fetch(PDO::FETCH_ASSOC);if(!$actual||(int)$actual['catalog_hotel_id']!==$expected['target']||(int)$actual['enabled']!==1||$actual['scope']!=='preview'||$actual['approval_policy']!==FC_POLICY||!hash_equals($expected['source_row_digest'],(string)$actual['source_row_digest'])||!hash_equals($expected['mapping_digest'],(string)$actual['mapping_digest']))throw new RuntimeException('post_commit_readback_failed:'.$id);$rows[$id]['readback']='verified';}
    $after=fc_coverage($db);return ['status'=>'completed','operation_id'=>$operation,'database_writes'=>$stats['written'],'mapping_writes'=>$stats['written'],'supplier_calls'=>0,'historical_operations_replayed'=>false,'source_operation'=>HMASDA_SOURCE_OPERATION,'source_result_sha256'=>HMASDA_SOURCE_RESULT_SHA256,'direct_result_sha256'=>HMASDA_DIRECT_RESULT_SHA256,'stats'=>$stats,'coverage_before'=>$before,'coverage_after'=>$after,'rows'=>array_values($rows),'post_commit_readback_verified'=>count($rows)===$stats['written']];
}
