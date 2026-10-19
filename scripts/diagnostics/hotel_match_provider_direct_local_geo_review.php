<?php
declare(strict_types=1);

putenv('MATCH_PROVIDER_GEO_LABEL_TEST_LIBRARY=1');
require_once __DIR__ . '/hotel_match_provider_geo_label_consensus_review.php';

const MPGD_OP = 'hotel-match-provider-direct-local-geo-review-1971-20260915-v1';

function mpgd_json(array $v): string { return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n"; }
function mpgd_query(PDO $db,string $sql,array $args=[]): array { $q=$db->prepare($sql);$q->execute($args);return $q->fetchAll(PDO::FETCH_ASSOC); }
function mpgd_write(string $file,array $v): string { $raw=mpgd_json($v);$f=@fopen($file,'x+b');if(!$f)throw new RuntimeException('exclusive_file');try{if(fwrite($f,$raw)!==strlen($raw)||!fflush($f))throw new RuntimeException('write');if(function_exists('fsync')&&!fsync($f))throw new RuntimeException('sync');rewind($f);if(stream_get_contents($f)!==$raw)throw new RuntimeException('readback');}finally{fclose($f);}return hash('sha256',$raw); }

function mpgd_local_geo_index(array $hotels): array {
    $raw=[];
    foreach($hotels as $id=>$h){
        $cid=(int)($h['country_id']??0);if($cid<=0)continue;
        $rid=(int)($h['region_id']??0);$sid=(int)($h['subregion_id']??0);
        $rn=mpgl_label_norm((string)($h['region_name']??''));$sn=mpgl_label_norm((string)($h['subregion_name']??''));
        if($rn!==''&&$rid>0){$raw[$cid][$rn]['regions'][$rid]=true;$raw[$cid][$rn]['hotel_ids'][(int)$id]=true;}
        if($sn!==''&&$sid>0){$raw[$cid][$sn]['subregions'][$sid]=true;if($rid>0)$raw[$cid][$sn]['regions'][$rid]=true;$raw[$cid][$sn]['hotel_ids'][(int)$id]=true;}
    }
    $usable=[];$rejected=[];
    foreach($raw as $cid=>$labels)foreach($labels as $label=>$s){
        $subs=array_keys($s['subregions']??[]);$regions=array_keys($s['regions']??[]);
        $base=['country_id'=>(int)$cid,'label'=>$label,'region_ids'=>array_map('intval',$regions),'subregion_ids'=>array_map('intval',$subs),'hotel_count'=>count($s['hotel_ids']??[])];
        if(count($subs)===1){$usable[(int)$cid][$label]=$base+['scope'=>'subregion','scope_id'=>(int)$subs[0]];continue;}
        if(count($regions)===1){$usable[(int)$cid][$label]=$base+['scope'=>'region','scope_id'=>(int)$regions[0]];continue;}
        $rejected[(int)$cid][$label]=$base+['reason'=>'local_geo_label_ambiguous'];
    }
    foreach($usable as &$v)ksort($v,SORT_NATURAL);unset($v);foreach($rejected as &$v)ksort($v,SORT_NATURAL);unset($v);
    return['usable'=>$usable,'rejected'=>$rejected];
}
function mpgd_direct_local_ids(array $e,int $cid,array $index,array $scope): array {
    $sets=[];$anchors=[];
    foreach(mpgl_geo_labels($e) as $label){
        $c=$index['usable'][$cid][$label['label']]??null;if(!is_array($c))continue;
        $ids=$scope[$cid][$c['scope']][(int)$c['scope_id']]??[];
        if(!$ids)continue;
        $sets[]=array_fill_keys(array_map('intval',array_keys($ids)),true);
        $anchors[]=$c+['provider_semantic'=>$label['semantic'],'source_field'=>$label['field'],'source_raw'=>$label['raw']];
    }
    if(!$sets)return['status'=>'no_direct_local_geo','ids'=>[],'anchors'=>[]];
    $allowed=array_shift($sets);foreach($sets as $s)$allowed=array_intersect_key($allowed,$s);
    if(!$allowed)return['status'=>'direct_local_geo_conflict','ids'=>[],'anchors'=>$anchors];
    return['status'=>'ok','ids'=>array_map('intval',array_keys($allowed)),'anchors'=>$anchors];
}
function mpgd_combined_geo_ids(array $e,int $cid,array $numeric,array $labels,array $direct,array $scope): array {
    $a=mpg_allowed_ids($e,$cid,$numeric,$scope);$b=mpgl_allowed_ids($e,$cid,$labels,$scope);$c=mpgd_direct_local_ids($e,$cid,$direct,$scope);
    if($a['status']==='geo_consensus_conflict'||$b['status']==='geo_label_consensus_conflict'||$c['status']==='direct_local_geo_conflict')return['status'=>'geo_consensus_conflict','ids'=>[],'anchors'=>array_merge($a['anchors']??[],$b['anchors']??[],$c['anchors']??[]),'sources'=>[]];
    $sets=[];$anchors=[];$sources=[];
    foreach([['provider_key',$a],['accepted_label',$b],['direct_local_label',$c]] as [$name,$x])if(($x['status']??'')==='ok'){$sets[]=array_fill_keys(array_map('intval',$x['ids']),true);$anchors=array_merge($anchors,$x['anchors']??[]);$sources[]=$name;}
    if(!$sets)return['status'=>'no_geo_consensus','ids'=>[],'anchors'=>[],'sources'=>[]];
    $allowed=array_shift($sets);foreach($sets as $s)$allowed=array_intersect_key($allowed,$s);
    if(!$allowed)return['status'=>'geo_consensus_intersection_empty','ids'=>[],'anchors'=>$anchors,'sources'=>$sources];
    return['status'=>'ok','ids'=>array_map('intval',array_keys($allowed)),'anchors'=>$anchors,'sources'=>$sources];
}

if(getenv('MATCH_PROVIDER_DIRECT_LOCAL_GEO_TEST_LIBRARY')==='1')return;
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$op=(string)getenv('MATCH_OPERATION_ID');$sha=(string)getenv('MATCH_SOURCE_SHA');if($op!==MPGD_OP||!preg_match('/^[0-9a-f]{40}$/D',$sha))throw new RuntimeException('operation_or_source_guard');$home=(string)getenv('HOME');if($home==='')throw new RuntimeException('home');$dir=$home.'/.anytoour-match/operations/'.MPGD_OP;$res=mcr_evidence((string)@file_get_contents($dir.'/reservation.json'));if(($res['operation_id']??'')!==MPGD_OP||($res['source_sha']??'')!==$sha||($res['state']??'')!=='reserved_before_db_access')throw new RuntimeException('reservation');$root=realpath(getcwd());if(!$root||basename($root)!=='anytoour.ru')throw new RuntimeException('root');require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');$db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
try{
 $core=[];foreach(mpgd_query($db,'SELECT id,name FROM catalog_countries WHERE is_active=1 ORDER BY id') as $c)if(mcr_is_core8_name((string)$c['name']))$core[(int)$c['id']]=(string)$c['name'];if(count($core)<6)throw new RuntimeException('core8');$marks=implode(',',array_fill(0,count($core),'?'));
 $hotels=[];$forms=[];$scope=[];foreach(mpgd_query($db,"SELECT id,country_id,country_name,region_id,region_name,subregion_id,subregion_name,name,category,latitude,longitude FROM catalog_hotels WHERE is_active=1 AND country_id IN ($marks) ORDER BY country_id,id",array_keys($core)) as $h){$id=(int)$h['id'];$hotels[$id]=$h;$forms[$id]=[(string)$h['name']];$cid=(int)$h['country_id'];$rid=(int)$h['region_id'];$sid=(int)$h['subregion_id'];if($rid>0)$scope[$cid]['region'][$rid][$id]=true;if($sid>0)$scope[$cid]['subregion'][$sid][$id]=true;}
 foreach(mpgd_query($db,"SELECT a.hotel_id,a.alias FROM hotel_aliases a JOIN catalog_hotels h ON h.id=a.hotel_id WHERE h.is_active=1 AND h.country_id IN ($marks) ORDER BY a.hotel_id,a.id",array_keys($core)) as $a){$id=(int)$a['hotel_id'];if(isset($forms[$id]))$forms[$id][]=(string)$a['alias'];}
 $direct=mpgd_local_geo_index($hotels);$all=mpgd_query($db,"SELECT external_hotel_id,evidence_json FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' ORDER BY external_hotel_id");$accepted=mpgd_query($db,"SELECT i.external_hotel_id,i.local_hotel_id,i.evidence_json,h.country_id AS local_country_id,h.region_id AS local_region_id,h.subregion_id AS local_subregion_id FROM andromeda_hotel_identities i JOIN catalog_hotels h ON h.id=i.local_hotel_id AND h.is_active=1 WHERE i.supplier_namespace='andromeda_catalog' AND i.decision_status='accepted' AND i.local_hotel_id IS NOT NULL ORDER BY i.external_hotel_id");$country=msac_accepted_country_consensus($accepted,$all,$core);$numeric=mpg_build_consensus($accepted);$labels=mpgl_build_consensus($accepted);
 $pending=mpgd_query($db,"SELECT supplier_namespace,external_hotel_id,evidence_sha256,evidence_json FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='pending' AND local_hotel_id IS NULL ORDER BY external_hotel_id");$routes=[];$candidates=[];$reasons=[];$withDirect=0;$withCombined=0;$freq=0;$directProviderFieldCounts=[];
 foreach($pending as $r){$e=mcr_evidence((string)$r['evidence_json']);foreach(mpgl_geo_labels($e) as $lab)$directProviderFieldCounts[$lab['field']]=($directProviderFieldCounts[$lab['field']]??0)+1;$sk=mcr_state_key($e);if($sk===null||!isset($country['inferred'][$sk]))continue;$cid=(int)$country['inferred'][$sk]['country_id'];$d=mpgd_direct_local_ids($e,$cid,$direct,$scope);if($d['status']!=='no_direct_local_geo')$withDirect++;$geo=mpgd_combined_geo_ids($e,$cid,$numeric,$labels,$direct,$scope);if($geo['status']==='ok')$withCombined++;
  if($geo['status']!=='ok')$sel=['route'=>$geo['status']==='geo_consensus_conflict'?'hard_conflict':'needs_extra_evidence','reason'=>$geo['status']];else$sel=mpg_select(mcr_names($e),mcr_points($e),$geo['ids'],$hotels,$forms);
  $item=array_merge(['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>(string)$r['external_hotel_id'],'evidence_sha256'=>(string)$r['evidence_sha256'],'state_key'=>$sk,'country_id'=>$cid,'frequency'=>mcr_frequency($e),'geo_sources'=>$geo['sources']??[],'geo_anchors'=>$geo['anchors']??[]],$sel);$routes[$item['route']][]=$item;$reasons[$item['reason']]=($reasons[$item['reason']]??0)+1;if($item['route']==='auto_accept_candidate'){$candidates[]=$item;$freq+=(int)$item['frequency'];}}
 foreach($routes as &$ls)usort($ls,fn($a,$b)=>(($b['frequency']??0)<=>($a['frequency']??0))?:strcmp((string)$a['external_hotel_id'],(string)$b['external_hotel_id']));unset($ls);usort($candidates,fn($a,$b)=>(($b['frequency']??0)<=>($a['frequency']??0))?:strcmp((string)$a['external_hotel_id'],(string)$b['external_hotel_id']));ksort($routes);ksort($reasons);ksort($directProviderFieldCounts,SORT_NATURAL);
 $usableDirect=0;$rejectedDirect=0;foreach($direct['usable'] as $v)$usableDirect+=count($v);foreach($direct['rejected'] as $v)$rejectedDirect+=count($v);
 $result=['schema'=>'hotel-match-provider-direct-local-geo-review/1','operation_id'=>MPGD_OP,'source_sha'=>$sha,'state'=>'completed_read_only','server_current'=>true,'transaction'=>'REPEATABLE READ READ ONLY','accepted_andromeda_rows'=>count($accepted),'pending_andromeda_rows'=>count($pending),'usable_direct_local_geo_labels'=>$usableDirect,'rejected_direct_local_geo_labels'=>$rejectedDirect,'pending_with_direct_local_geo'=>$withDirect,'pending_with_combined_geo'=>$withCombined,'pending_provider_geo_field_counts'=>$directProviderFieldCounts,'candidate_count'=>count($candidates),'candidate_live_frequency_sum'=>$freq,'candidates'=>$candidates,'route_counts'=>array_map('count',$routes),'reason_counts'=>$reasons,'supplier_calls'=>0,'tourvisor_calls'=>0,'external_calls'=>0,'booking_calls'=>0,'db_writes'=>0,'mapping_writes'=>0,'operator_5_writes'=>0,'no_replay'=>true,'created_at'=>gmdate('c')];$hash=mpgd_write($dir.'/result.json',$result);$raw=(string)file_get_contents($dir.'/result.json');$x=mcr_evidence($raw);if(hash('sha256',$raw)!==$hash||($x['state']??'')!=='completed_read_only'||(int)($x['db_writes']??-1)!==0)throw new RuntimeException('result_readback');mpgd_write($dir.'/receipt.json',['operation_id'=>MPGD_OP,'source_sha'=>$sha,'state'=>'completed_read_only','result_sha256'=>$hash,'readback_verified'=>true,'no_replay'=>true,'created_at'=>gmdate('c')]);$db->rollBack();echo 'MATCH_PROVIDER_DIRECT_LOCAL_GEO_OK '.count($candidates)."\n";
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw$e;}
