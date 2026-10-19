<?php
declare(strict_types=1);

putenv('MATCH_PROVIDER_GEO_LABEL_TEST_LIBRARY=1');
require_once __DIR__ . '/hotel_match_provider_geo_label_consensus_review.php';

const MPGC_OP = 'hotel-match-provider-geo-category-consensus-review-1971-20260915-v1';
const MPGC_MIN_CATEGORY_ANCHORS = 20;
const MPGC_MIN_CATEGORY_SHARE = 0.98;

function mpgc_json(array $v): string {
    return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
}
function mpgc_query(PDO $db, string $sql, array $args = []): array {
    $q=$db->prepare($sql); $q->execute($args); return $q->fetchAll(PDO::FETCH_ASSOC);
}
function mpgc_write(string $file, array $v): string {
    $raw=mpgc_json($v); $f=@fopen($file,'x+b'); if(!$f) throw new RuntimeException('exclusive_file');
    try {
        if(fwrite($f,$raw)!==strlen($raw)||!fflush($f)) throw new RuntimeException('write');
        if(function_exists('fsync')&&!fsync($f)) throw new RuntimeException('sync');
        rewind($f); if(stream_get_contents($f)!==$raw) throw new RuntimeException('readback');
    } finally { fclose($f); }
    return hash('sha256',$raw);
}
function mpgc_local_category_class(?string $raw): string {
    $s=trim((string)$raw); if($s==='') return '';
    $f=mcr_fold($s);
    if(preg_match('/^\s*([1-5])\s*(?:\*|stars?|star|зв(?:езда|езды|езд)?\.?|з?)?\s*$/u',$f,$m)) return 'star:'.$m[1];
    $n=trim(preg_replace('/[^\p{L}\p{N}]+/u',' ',$f)??$f);
    if($n===''||strlen($n)>80) return '';
    return 'text:'.$n;
}
function mpgc_provider_category_tokens(array $e): array {
    $src=mcr_source($e); $out=[];
    foreach($src as $field=>$value){
        if(!is_string($field)||!(is_string($value)||is_int($value))) continue;
        $f=strtolower(preg_replace('/[^a-z]+/i','',$field)??$field);
        $semantic=null;
        if(str_contains($f,'star')) $semantic='star';
        elseif(str_contains($f,'category')) $semantic='category';
        if($semantic===null) continue;
        if(!preg_match('/(?:star|category)(?:key|id|code|name|label|title|value)?$/',$f)) continue;
        $v=trim(mcr_fold((string)$value));
        $v=trim(preg_replace('/[^\p{L}\p{N}*+.-]+/u',' ',$v)??$v);
        if($v===''||strlen($v)>80) continue;
        $key=$semantic.'='.$v;
        $out[$key]=['semantic'=>$semantic,'field'=>$field,'value'=>$v,'raw'=>(string)$value];
    }
    ksort($out,SORT_NATURAL); return $out;
}
function mpgc_build_category_consensus(array $accepted): array {
    $stats=[];
    foreach($accepted as $r){
        $cid=(int)($r['local_country_id']??0); $local=mpgc_local_category_class((string)($r['local_category']??''));
        if($cid<=0||$local==='') continue;
        $e=mcr_evidence((string)($r['evidence_json']??''));
        foreach(mpgc_provider_category_tokens($e) as $token=>$meta){
            $key=$cid.'|'.$token; $s=&$stats[$key];
            $s['country_id']=$cid; $s['token']=$token; $s['fields'][$meta['field']]=($s['fields'][$meta['field']]??0)+1;
            $s['anchors']=($s['anchors']??0)+1; $s['local_classes'][$local]=($s['local_classes'][$local]??0)+1; unset($s);
        }
    }
    $usable=[];$rejected=[];
    foreach($stats as $key=>$s){
        $counts=$s['local_classes']??[]; arsort($counts,SORT_NUMERIC); $anchors=(int)($s['anchors']??0);
        $winner=(string)(array_key_first($counts)??''); $winnerN=$winner===''?0:(int)$counts[$winner]; $share=$anchors>0?$winnerN/$anchors:0.0;
        $base=['country_id'=>(int)$s['country_id'],'token'=>(string)$s['token'],'fields'=>$s['fields']??[],'anchors'=>$anchors,'local_classes'=>$counts,'winner_class'=>$winner,'winner_count'=>$winnerN,'winner_share'=>$share];
        if($anchors<MPGC_MIN_CATEGORY_ANCHORS){$rejected[$key]=$base+['reason'=>'insufficient_anchors'];continue;}
        if($winner===''||$share<MPGC_MIN_CATEGORY_SHARE){$rejected[$key]=$base+['reason'=>'category_semantics_not_stable'];continue;}
        $usable[$key]=$base+['method'=>'current_accepted_provider_category_to_local_category_consensus'];
    }
    ksort($usable,SORT_NATURAL);ksort($rejected,SORT_NATURAL);return['usable'=>$usable,'rejected'=>$rejected];
}
function mpgc_category_class_for_pending(array $e,int $cid,array $consensus): array {
    $classes=[];$used=[];
    foreach(mpgc_provider_category_tokens($e) as $token=>$meta){
        $c=$consensus['usable'][$cid.'|'.$token]??null;
        if(!is_array($c)) continue;
        $classes[(string)$c['winner_class']]=true; $used[]=$c+['source_field'=>$meta['field'],'source_raw'=>$meta['raw']];
    }
    if(!$used) return['status'=>'no_category_consensus','class'=>null,'anchors'=>[]];
    if(count($classes)!==1) return['status'=>'category_consensus_conflict','class'=>null,'anchors'=>$used,'classes'=>array_keys($classes)];
    return['status'=>'ok','class'=>(string)array_key_first($classes),'anchors'=>$used];
}
function mpgc_combined_geo_ids(array $e,int $cid,array $numeric,array $labels,array $scope): array {
    $a=mpg_allowed_ids($e,$cid,$numeric,$scope); $b=mpgl_allowed_ids($e,$cid,$labels,$scope);
    if($a['status']==='geo_consensus_conflict'||$b['status']==='geo_label_consensus_conflict') return['status'=>'geo_consensus_conflict','ids'=>[],'anchors'=>array_merge($a['anchors']??[],$b['anchors']??[])];
    $sets=[];$anchors=[];$sources=[];
    if($a['status']==='ok'){$sets[]=array_fill_keys(array_map('intval',$a['ids']),true);$anchors=array_merge($anchors,$a['anchors']);$sources[]='provider_key';}
    if($b['status']==='ok'){$sets[]=array_fill_keys(array_map('intval',$b['ids']),true);$anchors=array_merge($anchors,$b['anchors']);$sources[]='provider_label';}
    if(!$sets) return['status'=>'no_geo_consensus','ids'=>[],'anchors'=>[],'sources'=>[]];
    $allowed=array_shift($sets);foreach($sets as $s)$allowed=array_intersect_key($allowed,$s);
    if(!$allowed) return['status'=>'geo_consensus_intersection_empty','ids'=>[],'anchors'=>$anchors,'sources'=>$sources];
    return['status'=>'ok','ids'=>array_map('intval',array_keys($allowed)),'anchors'=>$anchors,'sources'=>$sources];
}
function mpgc_category_filter_ids(array $ids,string $class,array $hotels): array {
    $out=[];foreach($ids as $id){$id=(int)$id;if(!isset($hotels[$id]))continue;if(mpgc_local_category_class((string)($hotels[$id]['category']??''))===$class)$out[]=$id;}
    return$out;
}

if(getenv('MATCH_PROVIDER_GEO_CATEGORY_TEST_LIBRARY')==='1') return;
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$op=(string)getenv('MATCH_OPERATION_ID');$sha=(string)getenv('MATCH_SOURCE_SHA');
if($op!==MPGC_OP||!preg_match('/^[0-9a-f]{40}$/D',$sha)) throw new RuntimeException('operation_or_source_guard');
$home=(string)getenv('HOME');if($home==='')throw new RuntimeException('home');$dir=$home.'/.anytoour-match/operations/'.MPGC_OP;
$res=mcr_evidence((string)@file_get_contents($dir.'/reservation.json'));if(($res['operation_id']??'')!==MPGC_OP||($res['source_sha']??'')!==$sha||($res['state']??'')!=='reserved_before_db_access')throw new RuntimeException('reservation');
$root=realpath(getcwd());if(!$root||basename($root)!=='anytoour.ru')throw new RuntimeException('root');require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
$db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
try{
 $core=[];foreach(mpgc_query($db,'SELECT id,name FROM catalog_countries WHERE is_active=1 ORDER BY id') as $c)if(mcr_is_core8_name((string)$c['name']))$core[(int)$c['id']]=(string)$c['name'];if(count($core)<6)throw new RuntimeException('core8');$marks=implode(',',array_fill(0,count($core),'?'));
 $hotels=[];$forms=[];$scope=[];foreach(mpgc_query($db,"SELECT id,country_id,country_name,region_id,region_name,subregion_id,subregion_name,name,category,latitude,longitude FROM catalog_hotels WHERE is_active=1 AND country_id IN ($marks) ORDER BY country_id,id",array_keys($core)) as $h){$id=(int)$h['id'];$hotels[$id]=$h;$forms[$id]=[(string)$h['name']];$cid=(int)$h['country_id'];$rid=(int)$h['region_id'];$sid=(int)$h['subregion_id'];if($rid>0)$scope[$cid]['region'][$rid][$id]=true;if($sid>0)$scope[$cid]['subregion'][$sid][$id]=true;}
 foreach(mpgc_query($db,"SELECT a.hotel_id,a.alias FROM hotel_aliases a JOIN catalog_hotels h ON h.id=a.hotel_id WHERE h.is_active=1 AND h.country_id IN ($marks) ORDER BY a.hotel_id,a.id",array_keys($core)) as $a){$id=(int)$a['hotel_id'];if(isset($forms[$id]))$forms[$id][]=(string)$a['alias'];}
 $all=mpgc_query($db,"SELECT external_hotel_id,evidence_json FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' ORDER BY external_hotel_id");
 $accepted=mpgc_query($db,"SELECT i.external_hotel_id,i.local_hotel_id,i.evidence_json,h.country_id AS local_country_id,h.region_id AS local_region_id,h.subregion_id AS local_subregion_id,h.category AS local_category FROM andromeda_hotel_identities i JOIN catalog_hotels h ON h.id=i.local_hotel_id AND h.is_active=1 WHERE i.supplier_namespace='andromeda_catalog' AND i.decision_status='accepted' AND i.local_hotel_id IS NOT NULL ORDER BY i.external_hotel_id");
 $country=msac_accepted_country_consensus($accepted,$all,$core);$numeric=mpg_build_consensus($accepted);$labels=mpgl_build_consensus($accepted);$category=mpgc_build_category_consensus($accepted);
 $pending=mpgc_query($db,"SELECT supplier_namespace,external_hotel_id,evidence_sha256,evidence_json FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='pending' AND local_hotel_id IS NULL ORDER BY external_hotel_id");
 $routes=[];$candidates=[];$reasons=[];$withGeo=0;$withCategory=0;$withBoth=0;$freq=0;$categoryFieldCounts=[];
 foreach($pending as $r){
  $e=mcr_evidence((string)$r['evidence_json']);foreach(mpgc_provider_category_tokens($e) as $meta)$categoryFieldCounts[$meta['field']]=($categoryFieldCounts[$meta['field']]??0)+1;
  $sk=mcr_state_key($e);if($sk===null||!isset($country['inferred'][$sk]))continue;$cid=(int)$country['inferred'][$sk]['country_id'];
  $geo=mpgc_combined_geo_ids($e,$cid,$numeric,$labels,$scope);$cat=mpgc_category_class_for_pending($e,$cid,$category);if($geo['status']!=='no_geo_consensus')$withGeo++;if($cat['status']!=='no_category_consensus')$withCategory++;if($geo['status']==='ok'&&$cat['status']==='ok')$withBoth++;
  if($geo['status']!=='ok'){$sel=['route'=>$geo['status']==='geo_consensus_conflict'?'hard_conflict':'needs_extra_evidence','reason'=>$geo['status']];}
  elseif($cat['status']==='category_consensus_conflict'){$sel=['route'=>'hard_conflict','reason'=>'provider_category_consensus_conflict'];}
  elseif($cat['status']!=='ok'){$sel=['route'=>'needs_extra_evidence','reason'=>'no_provider_category_consensus'];}
  else{$ids=mpgc_category_filter_ids($geo['ids'],(string)$cat['class'],$hotels);if(!$ids)$sel=['route'=>'needs_extra_evidence','reason'=>'geo_category_intersection_empty'];else$sel=mpg_select(mcr_names($e),mcr_points($e),$ids,$hotels,$forms);}
  $item=array_merge(['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>(string)$r['external_hotel_id'],'evidence_sha256'=>(string)$r['evidence_sha256'],'state_key'=>$sk,'country_id'=>$cid,'frequency'=>mcr_frequency($e),'geo_sources'=>$geo['sources']??[],'geo_anchors'=>$geo['anchors']??[],'category_class'=>$cat['class']??null,'category_anchors'=>$cat['anchors']??[]],$sel);
  $routes[$item['route']][]=$item;$reasons[$item['reason']]=($reasons[$item['reason']]??0)+1;if($item['route']==='auto_accept_candidate'){$candidates[]=$item;$freq+=(int)$item['frequency'];}
 }
 foreach($routes as &$ls)usort($ls,fn($a,$b)=>(($b['frequency']??0)<=>($a['frequency']??0))?:strcmp((string)$a['external_hotel_id'],(string)$b['external_hotel_id']));unset($ls);usort($candidates,fn($a,$b)=>(($b['frequency']??0)<=>($a['frequency']??0))?:strcmp((string)$a['external_hotel_id'],(string)$b['external_hotel_id']));ksort($routes);ksort($reasons);ksort($categoryFieldCounts,SORT_NATURAL);
 $result=['schema'=>'hotel-match-provider-geo-category-consensus-review/1','operation_id'=>MPGC_OP,'source_sha'=>$sha,'state'=>'completed_read_only','server_current'=>true,'transaction'=>'REPEATABLE READ READ ONLY','accepted_andromeda_rows'=>count($accepted),'pending_andromeda_rows'=>count($pending),'usable_numeric_geo'=>count($numeric['usable']),'usable_geo_labels'=>count($labels['usable']),'usable_category_tokens'=>count($category['usable']),'rejected_category_tokens'=>count($category['rejected']),'pending_with_geo'=>$withGeo,'pending_with_category_consensus'=>$withCategory,'pending_with_geo_and_category'=>$withBoth,'pending_category_field_counts'=>$categoryFieldCounts,'candidate_count'=>count($candidates),'candidate_live_frequency_sum'=>$freq,'candidates'=>$candidates,'route_counts'=>array_map('count',$routes),'reason_counts'=>$reasons,'category_consensus'=>$category,'supplier_calls'=>0,'tourvisor_calls'=>0,'external_calls'=>0,'booking_calls'=>0,'db_writes'=>0,'mapping_writes'=>0,'operator_5_writes'=>0,'no_replay'=>true,'created_at'=>gmdate('c')];
 $hash=mpgc_write($dir.'/result.json',$result);$raw=(string)file_get_contents($dir.'/result.json');$x=mcr_evidence($raw);if(hash('sha256',$raw)!==$hash||($x['state']??'')!=='completed_read_only'||(int)($x['db_writes']??-1)!==0)throw new RuntimeException('result_readback');mpgc_write($dir.'/receipt.json',['operation_id'=>MPGC_OP,'source_sha'=>$sha,'state'=>'completed_read_only','result_sha256'=>$hash,'readback_verified'=>true,'no_replay'=>true,'created_at'=>gmdate('c')]);$db->rollBack();echo 'MATCH_PROVIDER_GEO_CATEGORY_CONSENSUS_OK '.count($candidates)."\n";
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw$e;}
