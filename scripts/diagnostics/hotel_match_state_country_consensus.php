<?php
declare(strict_types=1);
putenv('MATCH_TEST_LIBRARY=1');
require_once __DIR__.'/hotel_match_live_residual_current_review.php';

const MSC_OP='hotel-match-state-country-consensus-1971-20260915-v2';

function msc_json(array $v):string{return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";}
function msc_query(PDO $db,string $sql,array $args=[]):array{$q=$db->prepare($sql);$q->execute($args);return $q->fetchAll(PDO::FETCH_ASSOC);}
function msc_write(string $file,array $v):string{$raw=msc_json($v);$f=@fopen($file,'x+b');if(!$f)throw new RuntimeException('exclusive_file');try{if(fwrite($f,$raw)!==strlen($raw)||!fflush($f))throw new RuntimeException('write');if(function_exists('fsync')&&!fsync($f))throw new RuntimeException('sync');rewind($f);if(stream_get_contents($f)!==$raw)throw new RuntimeException('readback');}finally{fclose($f);}return hash('sha256',$raw);}
function msc_country_aliases(array $coreIds):array{
    $vocab=[
        'египет'=>['egypt','египет'],
        'таиланд'=>['thailand','таиланд'],
        'турция'=>['turkey','turkiye','türkiye','турция'],
        'мальдивы'=>['maldives','мальдивы','мальдив'],
        'оаэ'=>['uae','united arab emirates','оаэ','объединенные арабские эмираты'],
        'куба'=>['cuba','куба'],
        'шри ланка'=>['sri lanka','шри ланка'],
        'вьетнам'=>['vietnam','вьетнам'],
    ];
    $out=[];foreach($coreIds as $cid=>$name){$k=trim(mcr_country_key((string)$name));foreach($vocab[$k]??[$k] as $a){$a=trim(mcr_country_key((string)$a));if($a!=='')$out[(int)$cid][$a]=true;}}
    return array_map(fn($x)=>array_keys($x),$out);
}
function msc_explicit_countries(array $names,array $aliases):array{
    $found=[];foreach($names as $name){$flat=' '.trim(mcr_country_key((string)$name)).' ';foreach($aliases as $cid=>$terms)foreach($terms as $term)if(str_contains($flat,' '.$term.' ')){$found[(int)$cid]=true;break;}}
    $ids=array_map('intval',array_keys($found));sort($ids,SORT_NUMERIC);return$ids;
}
function msc_marker_consensus(array $pending,array $coreIds):array{
    $aliases=msc_country_aliases($coreIds);$votes=[];$examples=[];$conflicting=[];
    foreach($pending as $r){$e=mcr_evidence((string)($r['evidence_json']??''));$sk=mcr_state_key($e);if($sk===null)continue;$ids=msc_explicit_countries(mcr_names($e),$aliases);if(count($ids)>1){$conflicting[$sk]=($conflicting[$sk]??0)+1;continue;}if(count($ids)!==1)continue;$cid=$ids[0];$votes[$sk][$cid]=($votes[$sk][$cid]??0)+1;if(count($examples[$sk][$cid]??[])<8)$examples[$sk][$cid][]=['external_hotel_id'=>(string)$r['external_hotel_id'],'names'=>mcr_names($e)];}
    $out=[];foreach($votes as $sk=>$counts){arsort($counts,SORT_NUMERIC);$cid=(int)array_key_first($counts);$winner=(int)$counts[$cid];$total=array_sum($counts);$share=$total?$winner/$total:0.0;$multi=(int)($conflicting[$sk]??0);if($winner<5||$share<0.98||$multi>0||!isset($coreIds[$cid]))continue;$out[(string)$sk]=['country_id'=>$cid,'country_name'=>$coreIds[$cid],'winner'=>$winner,'total_marked'=>$total,'share'=>$share,'multi_country_rows'=>$multi,'method'=>'current_provider_explicit_country_marker_consensus','examples'=>$examples[$sk][$cid]??[]];}
    return$out;
}

if(getenv('MATCH_STATE_CONSENSUS_TEST_LIBRARY')==='1')return;
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$op=(string)getenv('MATCH_OPERATION_ID');$sha=(string)getenv('MATCH_SOURCE_SHA');if($op!==MSC_OP||!preg_match('/^[0-9a-f]{40}$/D',$sha))throw new RuntimeException('operation_or_source_guard');$home=(string)getenv('HOME');if($home==='')throw new RuntimeException('home');$dir=$home.'/.anytoour-match/operations/'.MSC_OP;$res=mcr_evidence((string)@file_get_contents($dir.'/reservation.json'));if(($res['operation_id']??'')!==MSC_OP||($res['source_sha']??'')!==$sha||($res['state']??'')!=='reserved_before_db_access')throw new RuntimeException('reservation');$root=realpath(getcwd());if(!$root||basename($root)!=='anytoour.ru')throw new RuntimeException('root');require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');$db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
try{
    $coreIds=[];foreach(msc_query($db,'SELECT id,name FROM catalog_countries WHERE is_active=1 ORDER BY id') as $c)if(mcr_is_core8_name((string)$c['name']))$coreIds[(int)$c['id']]=(string)$c['name'];if(count($coreIds)<6)throw new RuntimeException('core8');$marks=implode(',',array_fill(0,count($coreIds),'?'));
    $hotels=[];$forms=[];foreach(msc_query($db,"SELECT id,country_id,country_name,region_id,region_name,subregion_id,subregion_name,name,category,latitude,longitude FROM catalog_hotels WHERE is_active=1 AND country_id IN ($marks) ORDER BY country_id,id",array_keys($coreIds)) as $h){$id=(int)$h['id'];$hotels[$id]=$h;$forms[$id]=[(string)$h['name']];}
    foreach(msc_query($db,"SELECT a.hotel_id,a.alias FROM hotel_aliases a JOIN catalog_hotels h ON h.id=a.hotel_id WHERE h.is_active=1 AND h.country_id IN ($marks) ORDER BY a.hotel_id,a.id",array_keys($coreIds)) as $a){$id=(int)$a['hotel_id'];if(isset($forms[$id]))$forms[$id][]=(string)$a['alias'];}
    $exact=[];$tokenIndex=[];foreach($forms as $id=>$list){$cid=(int)$hotels[$id]['country_id'];foreach(array_unique($list) as $raw){$n=mcr_norm((string)$raw);if($n==='')continue;$exact[$cid][$n][]=$id;foreach(mcr_tokens((string)$raw) as $t)$tokenIndex[$cid][$t][$id]=true;}}$geoExact=mcr_geo_exact_index($hotels,$forms);
    $pending=msc_query($db,"SELECT supplier_namespace,external_hotel_id,evidence_sha256,evidence_json FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='pending' AND local_hotel_id IS NULL ORDER BY external_hotel_id");$inferred=msc_marker_consensus($pending,$coreIds);$aliases=msc_country_aliases($coreIds);$routes=[];$candidates=[];$reason=[];
    foreach($pending as $r){$e=mcr_evidence((string)$r['evidence_json']);$sk=mcr_state_key($e);if($sk===null||!isset($inferred[$sk]))continue;$cid=(int)$inferred[$sk]['country_id'];$explicit=msc_explicit_countries(mcr_names($e),$aliases);if($explicit&&$explicit!==[$cid]){$sel=['route'=>'hard_conflict','reason'=>'row_explicit_country_conflict','explicit_country_ids'=>$explicit];}else{$sel=mcr_select_candidate(mcr_names($e),$cid,mcr_points($e),$hotels,$forms,$exact,$tokenIndex,$geoExact);}$item=array_merge(['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>(string)$r['external_hotel_id'],'evidence_sha256'=>(string)$r['evidence_sha256'],'state_key'=>$sk,'inferred_country_id'=>$cid,'inference'=>$inferred[$sk]],$sel);$routes[$item['route']][]=$item;$reason[$item['reason']]=($reason[$item['reason']]??0)+1;if($item['route']==='auto_accept_candidate')$candidates[]=$item;}
    foreach($routes as &$ls)usort($ls,fn($a,$b)=>strcmp((string)$a['external_hotel_id'],(string)$b['external_hotel_id']));unset($ls);ksort($routes);ksort($reason);$result=['schema'=>'hotel-match-state-country-consensus/2','operation_id'=>MSC_OP,'source_sha'=>$sha,'state'=>'completed_read_only','server_current'=>true,'transaction'=>'REPEATABLE READ READ ONLY','core8_countries'=>$coreIds,'pending_andromeda'=>count($pending),'inferred_states'=>$inferred,'candidate_count'=>count($candidates),'candidates'=>$candidates,'route_counts'=>array_map('count',$routes),'reason_counts'=>$reason,'routes'=>$routes,'supplier_calls'=>0,'tourvisor_calls'=>0,'external_calls'=>0,'booking_calls'=>0,'db_writes'=>0,'mapping_writes'=>0,'no_replay'=>true,'created_at'=>gmdate('c')];$hash=msc_write($dir.'/result.json',$result);$raw=(string)file_get_contents($dir.'/result.json');$ok=hash('sha256',$raw)===$hash&&($x=mcr_evidence($raw))&&($x['operation_id']??'')===MSC_OP&&($x['db_writes']??-1)===0;$receipt=['operation_id'=>MSC_OP,'source_sha'=>$sha,'state'=>'completed_read_only','result_sha256'=>$hash,'readback_verified'=>$ok,'pending_andromeda'=>count($pending),'inferred_state_count'=>count($inferred),'candidate_count'=>count($candidates),'route_counts'=>array_map('count',$routes),'db_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'external_calls'=>0,'no_replay'=>true,'completed_at'=>gmdate('c')];msc_write($dir.'/receipt.json',$receipt);if(!$ok)throw new RuntimeException('result_readback');echo msc_json(['operation_id'=>MSC_OP,'state'=>'completed_read_only','pending_andromeda'=>count($pending),'inferred_states'=>$inferred,'candidate_count'=>count($candidates),'route_counts'=>array_map('count',$routes),'reason_counts'=>$reason,'result_sha256'=>$hash]);$db->commit();
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();if(!is_file($dir.'/failure.json'))msc_write($dir.'/failure.json',['operation_id'=>MSC_OP,'source_sha'=>$sha,'state'=>'read_only_failed','error_class'=>get_class($e),'error'=>substr($e->getMessage(),0,300),'db_writes'=>0,'supplier_calls'=>0,'external_calls'=>0,'no_replay'=>true,'failed_at'=>gmdate('c')]);throw $e;}
