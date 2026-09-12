<?php
declare(strict_types=1);
if (!defined('FC_LIBRARY_ONLY')) define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/hotel_full_catalog_reconcile.php';

const HMASR_OPERATION = 'hotel-match-anex-sold-details-strong-review-1971-20260911-v1';
const HMASR_SOURCE_OPERATION = 'hotel-match-anex-sold-details-review-1971-20260911-v2';
const HMASR_SOURCE_RESULT_SHA256 = '86f3cbe232a262fa6c6bcd5d87a56af8167ce52f1dc7b589c7d4605fd57cc7fa';
const HMASR_CORE8 = [1=>true,2=>true,4=>true,8=>true,9=>true,10=>true,12=>true,16=>true];

function hmasr_variants(string $name): array {
    $out=[];$name=trim($name);if($name==='')return [];$out[$name]=true;
    $current=preg_replace('/\s*[\(\[]\s*(?:ex|former|formerly|old name)\s*\.?\s*[:\-]?\s*[^\)\]]+[\)\]]\s*$/ui','',$name);
    if(is_string($current)&&trim($current)!=='')$out[trim($current)]=true;
    if(preg_match_all('/[\(\[]\s*(?:ex|former|formerly|old name)\s*\.?\s*[:\-]?\s*([^\)\]]+)[\)\]]/ui',$name,$m))foreach($m[1] as $former)if(trim($former)!=='')$out[trim($former)]=true;
    return array_keys($out);
}
function hmasr_tokens(string $v): array {
    $generic=['hotel'=>1,'hotels'=>1,'отель'=>1,'отели'=>1,'resort'=>1,'resorts'=>1,'spa'=>1,'резорт'=>1,'ресорт'=>1,'спа'=>1,'the'=>1,'and'=>1];
    $tokens=[];foreach(explode(' ',fc_norm($v)) as $t)if($t!==''&&!isset($generic[$t]))$tokens[$t]=true;return array_keys($tokens);
}
function hmasr_critical(string $v): array {
    $crit=['annex'=>1,'beach'=>1,'garden'=>1,'north'=>1,'south'=>1];$out=[];foreach(hmasr_tokens($v) as $t)if(isset($crit[$t]))$out[$t]=true;$x=array_keys($out);sort($x,SORT_STRING);return $x;
}
function hmasr_ordered(array $small,array $large): bool {if(!$small||count($small)>count($large))return false;$i=0;foreach($large as $t)if(isset($small[$i])&&$small[$i]===$t)$i++;return $i===count($small);}
function hmasr_similarity(string $a,string $b): float {$a=fc_norm($a);$b=fc_norm($b);if($a===''||$b==='')return 0.0;similar_text($a,$b,$pct);return max(0.0,min(1.0,$pct/100.0));}
function hmasr_name_score(string $source,string $target): array {
    $s=hmasr_tokens($source);$t=hmasr_tokens($target);$shared=count(array_intersect($s,$t));$union=count(array_unique(array_merge($s,$t)));$j=$union?$shared/$union:0.0;$ordered=hmasr_ordered($s,$t)||hmasr_ordered($t,$s);$char=hmasr_similarity(implode(' ',$s),implode(' ',$t));
    $critical=hmasr_critical($source)===hmasr_critical($target);$score=0.55*$j+0.30*$char+0.15*($ordered?1.0:0.0);
    return ['shared'=>$shared,'jaccard'=>round($j,6),'character'=>round($char,6),'ordered'=>$ordered,'critical_ok'=>$critical,'score'=>round($score,6),'source_tokens'=>$s,'target_tokens'=>$t];
}
function hmasr_best_name(array $sourceVariants,array $targetNames): array {
    $best=['shared'=>0,'jaccard'=>0.0,'character'=>0.0,'ordered'=>false,'critical_ok'=>false,'score'=>0.0,'source'=>'','target'=>''];
    foreach($sourceVariants as $s)foreach($targetNames as $t){$r=hmasr_name_score((string)$s,(string)$t);$r['source']=$s;$r['target']=$t;if($r['score']>$best['score']||($r['score']===$best['score']&&$r['shared']>$best['shared']))$best=$r;}
    return $best;
}
function hmasr_coord($v): ?float {if($v===null||$v==='')return null;if(!is_numeric($v))return null;$x=(float)$v;return is_finite($x)?$x:null;}
function hmasr_source_payload(array $result,array $plan): array {
    if(($result['status']??null)!=='completed'||($result['operation_id']??null)!==HMASR_SOURCE_OPERATION||($result['database_writes']??null)!==0||($result['mapping_writes']??null)!==0)throw new RuntimeException('HMASR_SOURCE_RESULT');
    if(!is_array($result['details']['rows']??null)||!is_array($plan['target_context']??null)||($result['target_sha256']??'')!==($plan['target_sha256']??''))throw new RuntimeException('HMASR_SOURCE_RESULT');
    $ctx=[];foreach($plan['target_context'] as $r)$ctx[(int)$r['anex_hotel_id']]=$r;$rows=[];
    foreach($result['details']['rows'] as $r){if(($r['status']??'')!=='ok'||!is_array($r['detail']??null))continue;$id=(int)($r['anex_hotel_id']??0);if($id<1||!isset($ctx[$id]))continue;$d=$r['detail'];if(isset($d['id'])&&(int)$d['id']!==$id)throw new RuntimeException('HMASR_SOURCE_ID');$rows[$id]=['detail'=>$d,'context'=>$ctx[$id]];}
    return $rows;
}
function hmasr_review(PDO $db,array $sourceResult,array $sourcePlan,string $operation=HMASR_OPERATION): array {
    if($operation!==HMASR_OPERATION)throw new RuntimeException('HMASR_OPERATION_SCOPE');$sources=hmasr_source_payload($sourceResult,$sourcePlan);
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
    try{
        $manual=array_fill_keys(array_map('intval',$db->query('SELECT anex_hotel_id FROM anex_hotel_decisions')->fetchAll(PDO::FETCH_COLUMN)),true);$mapped=array_fill_keys(array_map('intval',$db->query('SELECT anex_hotel_id FROM anex_hotel_search_mappings WHERE enabled=1')->fetchAll(PDO::FETCH_COLUMN)),true);$excluded=[];foreach($db->query('SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions')->fetchAll(PDO::FETCH_ASSOC) as $r)$excluded[(int)$r['anex_hotel_id']][(int)$r['catalog_hotel_id']]=true;
        $andr=[];foreach($db->query("SELECT DISTINCT local_hotel_id FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN) as $id)$andr[(int)$id]=true;
        $hotels=[];$names=[];$tokenIndex=[];foreach($db->query('SELECT h.id,h.country_id,h.name,h.region_name,h.subregion_name,h.category,COALESCE(d.latitude,h.latitude) latitude,COALESCE(d.longitude,h.longitude) longitude FROM catalog_hotels h LEFT JOIN catalog_hotel_details d ON d.hotel_id=h.id WHERE h.is_active=1 AND h.country_id IN (1,2,4,8,9,10,12,16)')->fetchAll(PDO::FETCH_ASSOC) as $h){$id=(int)$h['id'];$c=(int)$h['country_id'];$hotels[$id]=$h;$names[$id]=[(string)$h['name']];foreach(hmasr_tokens((string)$h['name']) as $t)$tokenIndex[$c][$t][$id]=true;}
        foreach($db->query('SELECT a.hotel_id,a.alias,a.normalized_alias,h.country_id FROM hotel_aliases a JOIN catalog_hotels h ON h.id=a.hotel_id WHERE h.is_active=1 AND h.country_id IN (1,2,4,8,9,10,12,16)')->fetchAll(PDO::FETCH_ASSOC) as $a){$id=(int)$a['hotel_id'];$c=(int)$a['country_id'];foreach([(string)$a['alias'],(string)$a['normalized_alias']] as $alias){if(trim($alias)==='')continue;$names[$id][]=$alias;foreach(hmasr_tokens($alias) as $t)$tokenIndex[$c][$t][$id]=true;}}
        $stats=['source_details'=>count($sources),'became_protected'=>0,'country_other'=>0,'candidate_sets'=>0,'no_two_token_candidate'=>0,'coordinate_conflict'=>0,'qualifier_conflict'=>0,'weak_best'=>0,'small_margin'=>0,'prepared'=>0,'prepared_live'=>0,'prepared_andromeda_bridge'=>0];$prepared=[];$blocked=[];
        foreach($sources as $id=>$src){$id=(int)$id;if(isset($manual[$id])||isset($mapped[$id])){$stats['became_protected']++;continue;}$ctx=$src['context'];$d=$src['detail'];$country=(int)($ctx['country_id']??0);if(!isset(HMASR_CORE8[$country])){$stats['country_other']++;continue;}$sourceName=trim((string)($d['name']??''));$variants=hmasr_variants($sourceName);$tokens=[];foreach($variants as $v)foreach(hmasr_tokens($v) as $t)$tokens[$t]=true;$hits=[];foreach(array_keys($tokens) as $t)foreach(array_keys($tokenIndex[$country][$t]??[]) as $hid)$hits[(int)$hid]=($hits[(int)$hid]??0)+1;$candidateIds=[];foreach($hits as $hid=>$n)if($n>=2&&!isset($excluded[$id][$hid]))$candidateIds[]=(int)$hid;
            if(!$candidateIds){$stats['no_two_token_candidate']++;continue;}$stats['candidate_sets']++;$scored=[];$slat=hmasr_coord($d['latitude']??null);$slon=hmasr_coord($d['longitude']??null);
            foreach($candidateIds as $hid){$h=$hotels[$hid];$name=hmasr_best_name($variants,$names[$hid]??[(string)$h['name']]);if(!$name['critical_ok'])continue;$dist=fc_dist($slat,$slon,$h['latitude']??null,$h['longitude']??null);$place=fc_place([(string)($d['region']??''),(string)($d['town']??'')],[(string)($h['region_name']??''),(string)($h['subregion_name']??'')]);$geoStrong=($dist!==null&&$dist<=1000)||$place;$coordinateStrong=$dist!==null&&$dist<=120;$nameStrong=$name['shared']>=2&&($name['jaccard']>=0.58||($name['ordered']&&$name['character']>=0.72)||$name['character']>=0.86);$coordNameStrong=$coordinateStrong&&$name['shared']>=2&&($name['jaccard']>=0.42||$name['character']>=0.72);if(!$nameStrong&&!$coordNameStrong)continue;$score=$name['score']+($coordinateStrong?0.24:(($dist!==null&&$dist<=1000)?0.12:0.0))+($place?0.08:0.0)+(isset($andr[$hid])?0.04:0.0);$scored[]=['target_local_hotel_id'=>$hid,'target_name'=>$h['name'],'target_region'=>$h['region_name'],'target_subregion'=>$h['subregion_name'],'distance_m'=>$dist===null?null:round($dist,2),'place_match'=>$place,'andromeda_bridge'=>isset($andr[$hid]),'name'=>$name,'score'=>round($score,6),'geo_strong'=>$geoStrong];}
            if(!$scored){$stats['weak_best']++;continue;}usort($scored,static fn($a,$b)=>$b['score']<=>$a['score'] ?: (($a['distance_m']??PHP_FLOAT_MAX)<=>($b['distance_m']??PHP_FLOAT_MAX)) ?: $a['target_local_hotel_id']<=>$b['target_local_hotel_id']);$best=$scored[0];$second=$scored[1]??null;$margin=$second===null?1.0:$best['score']-$second['score'];
            if($best['distance_m']!==null&&$best['distance_m']>5000){$stats['coordinate_conflict']++;$blocked[]=['anex_hotel_id'=>$id,'reason'=>'coordinate_conflict','best'=>$best];continue;}if(!$best['name']['critical_ok']){$stats['qualifier_conflict']++;continue;}$accept=($best['geo_strong']&&$best['name']['shared']>=2&&$best['score']>=0.72&&$margin>=0.10)||($best['distance_m']!==null&&$best['distance_m']<=120&&$best['name']['shared']>=2&&$best['score']>=0.68&&$margin>=0.08);
            if(!$accept){if($margin<0.10)$stats['small_margin']++;else $stats['weak_best']++;continue;}$row=['anex_hotel_id'=>$id,'country_id'=>$country,'source_name'=>$sourceName,'source_region'=>$d['region']??null,'source_town'=>$d['town']??null,'source_latitude'=>$slat,'source_longitude'=>$slon,'live'=>(bool)($ctx['live']??false),'search_count'=>(int)($ctx['search_count']??0),'last_seen_utc'=>$ctx['last_seen_utc']??null,'target_local_hotel_id'=>$best['target_local_hotel_id'],'target_name'=>$best['target_name'],'target_region'=>$best['target_region'],'target_subregion'=>$best['target_subregion'],'distance_m'=>$best['distance_m'],'place_match'=>$best['place_match'],'andromeda_bridge'=>$best['andromeda_bridge'],'name'=>$best['name'],'score'=>$best['score'],'margin'=>round($margin,6),'second'=>$second===null?null:['target_local_hotel_id'=>$second['target_local_hotel_id'],'target_name'=>$second['target_name'],'score'=>$second['score'],'distance_m'=>$second['distance_m']],'rule'=>'direct_details_strong_name_margin_geo'];$prepared[]=$row;$stats['prepared']++;if($row['live'])$stats['prepared_live']++;if($row['andromeda_bridge'])$stats['prepared_andromeda_bridge']++;}
        usort($prepared,static fn($a,$b)=>(int)$b['live']<=>(int)$a['live'] ?: $b['search_count']<=>$a['search_count'] ?: $b['score']<=>$a['score'] ?: $b['margin']<=>$a['margin'] ?: $a['anex_hotel_id']<=>$b['anex_hotel_id']);$coverage=fc_coverage($db);$db->commit();return ['status'=>'completed','operation_id'=>$operation,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'historical_operations_replayed'=>false,'source_operation'=>HMASR_SOURCE_OPERATION,'source_result_sha256'=>HMASR_SOURCE_RESULT_SHA256,'stats'=>$stats,'coverage'=>$coverage,'prepared'=>$prepared,'blocked'=>array_slice($blocked,0,100)];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
