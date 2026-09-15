<?php
declare(strict_types=1);

putenv('MATCH_STATE_ACCEPTED_COUNTRY_TEST_LIBRARY=1');
require_once __DIR__ . '/hotel_match_state_accepted_country_consensus.php';

const MPG_OP = 'hotel-match-provider-geo-consensus-review-1971-20260915-v1';
const MPG_MIN_ANCHORS = 5;

function mpg_json(array $v): string { return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n"; }
function mpg_query(PDO $db,string $sql,array $args=[]): array { $q=$db->prepare($sql);$q->execute($args);return $q->fetchAll(PDO::FETCH_ASSOC); }
function mpg_write(string $file,array $v): string { $raw=mpg_json($v);$f=@fopen($file,'x+b');if(!$f)throw new RuntimeException('exclusive_file');try{if(fwrite($f,$raw)!==strlen($raw)||!fflush($f))throw new RuntimeException('write');if(function_exists('fsync')&&!fsync($f))throw new RuntimeException('sync');rewind($f);if(stream_get_contents($f)!==$raw)throw new RuntimeException('readback');}finally{fclose($f);}return hash('sha256',$raw); }
function mpg_geo_keys(array $e): array {
    $src=mcr_source($e);$out=[];
    foreach($src as $k=>$v){
        if(!is_string($k)||!preg_match('/(?:region|resort|city|town|district|area|locality|atoll).*key$/i',$k))continue;
        if(strcasecmp($k,'stateKey')===0)continue;
        if(!(is_int($v)||is_string($v)))continue;
        $s=trim((string)$v);if($s===''||strlen($s)>80||!preg_match('/^[\p{L}\p{N}._:-]+$/u',$s))continue;
        $out[$k]=$s;
    }
    return $out;
}
function mpg_build_consensus(array $accepted): array {
    $stats=[];
    foreach($accepted as $r){$e=mcr_evidence((string)($r['evidence_json']??''));foreach(mpg_geo_keys($e) as $field=>$value){$key=$field.'='.$value;$s=&$stats[$key];$s['field']=$field;$s['value']=$value;$s['anchors']=($s['anchors']??0)+1;$cid=(int)($r['local_country_id']??0);$rid=(int)($r['local_region_id']??0);$sid=(int)($r['local_subregion_id']??0);if($cid>0)$s['countries'][$cid]=($s['countries'][$cid]??0)+1;if($rid>0)$s['regions'][$rid]=($s['regions'][$rid]??0)+1;if($sid>0)$s['subregions'][$sid]=($s['subregions'][$sid]??0)+1;unset($s);}}
    $usable=[];$rejected=[];
    foreach($stats as $key=>$s){$n=(int)$s['anchors'];$countries=$s['countries']??[];$regions=$s['regions']??[];$subs=$s['subregions']??[];$base=['field'=>$s['field'],'value'=>$s['value'],'anchors'=>$n,'countries'=>$countries,'regions'=>$regions,'subregions'=>$subs];
        if($n<MPG_MIN_ANCHORS){$rejected[$key]=$base+['reason'=>'insufficient_anchors'];continue;}
        if(count($countries)!==1||array_sum($countries)!==$n){$rejected[$key]=$base+['reason'=>'country_not_unanimous'];continue;}
        $cid=(int)array_key_first($countries);
        if(count($subs)===1&&array_sum($subs)===$n){$usable[$key]=$base+['country_id'=>$cid,'scope'=>'subregion','scope_id'=>(int)array_key_first($subs)];continue;}
        if(count($regions)===1&&array_sum($regions)===$n){$usable[$key]=$base+['country_id'=>$cid,'scope'=>'region','scope_id'=>(int)array_key_first($regions)];continue;}
        $rejected[$key]=$base+['reason'=>'local_geography_not_unanimous'];
    }
    return ['usable'=>$usable,'rejected'=>$rejected];
}
function mpg_allowed_ids(array $e,int $countryId,array $consensus,array $scopeIndex): array {
    $sets=[];$used=[];
    foreach(mpg_geo_keys($e) as $field=>$value){$key=$field.'='.$value;$c=$consensus['usable'][$key]??null;if(!is_array($c)||(int)$c['country_id']!==$countryId)continue;$ids=$scopeIndex[$countryId][$c['scope']][(int)$c['scope_id']]??[];$sets[]=array_fill_keys(array_map('intval',array_keys($ids)),true);$used[]=$c;}
    if(!$sets)return ['ids'=>[],'anchors'=>[],'status'=>'no_geo_consensus'];
    $allowed=array_shift($sets);foreach($sets as $s)$allowed=array_intersect_key($allowed,$s);
    if(!$allowed)return ['ids'=>[],'anchors'=>$used,'status'=>'geo_consensus_conflict'];
    return ['ids'=>array_map('intval',array_keys($allowed)),'anchors'=>$used,'status'=>'ok'];
}
function mpg_select(array $names,array $points,array $allowed,array $hotels,array $forms): array {
    if(!$allowed)return ['route'=>'needs_extra_evidence','reason'=>'no_allowed_local_hotels'];
    $source=[];foreach($names as $n){$norm=mcr_norm((string)$n);if($norm!=='')$source[(string)$n]=$norm;}if(!$source)return['route'=>'needs_extra_evidence','reason'=>'missing_substantive_name'];
    $exact=[];
    foreach($allowed as $id){if(!isset($hotels[$id]))continue;foreach($source as $raw=>$norm){foreach($forms[$id]??[(string)$hotels[$id]['name']] as $lf){$ln=mcr_norm((string)$lf);$geo=mcr_geo_strip_form((string)$raw,$hotels[$id]);if(($norm===$ln||($geo!==null&&$geo===mcr_geo_latin($ln)))&&mcr_qualifier_ok((string)$raw,(string)$lf)){$exact[(int)$id]=true;break 2;}}}}
    if($exact){if(count($exact)!==1)return['route'=>'needs_extra_evidence','reason'=>'geo_restricted_exact_ambiguous','targets'=>array_map('intval',array_keys($exact))];$id=(int)array_key_first($exact);$guard=mcr_direct_target_guard(array_keys($source),$points,$hotels[$id]);if(!($guard['ok']??false))return['route'=>'hard_conflict','reason'=>(string)$guard['reason'],'target'=>$id]+$guard;return['route'=>'auto_accept_candidate','reason'=>'provider_geo_consensus_unique_exact','target'=>$id,'target_name'=>(string)$hotels[$id]['name']]+$guard;}
    $rank=[];
    foreach($allowed as $id){if(!isset($hotels[$id]))continue;$best=0.0;$qok=false;foreach(array_keys($source) as $raw)foreach($forms[$id]??[(string)$hotels[$id]['name']] as $lf){if(!mcr_qualifier_ok((string)$raw,(string)$lf))continue;$qok=true;$best=max($best,mcr_score((string)$raw,(string)$lf));}if(!$qok||$best<=0.0)continue;$guard=mcr_direct_target_guard(array_keys($source),$points,$hotels[$id]);if(!($guard['ok']??false))continue;$rank[$id]=['score'=>$best,'distance_km'=>$guard['distance_km']??null];}
    if(!$rank)return['route'=>'needs_extra_evidence','reason'=>'provider_geo_no_name_candidate'];
    uasort($rank,fn($a,$b)=>($b['score']<=>$a['score']));$ids=array_keys($rank);$bestId=(int)$ids[0];$best=$rank[$bestId];$second=isset($ids[1])?(float)$rank[$ids[1]]['score']:0.0;$margin=(float)$best['score']-$second;
    if((float)$best['score']>=0.60&&$margin>=0.25)return['route'=>'auto_accept_candidate','reason'=>'provider_geo_consensus_strong_fuzzy','target'=>$bestId,'target_name'=>(string)$hotels[$bestId]['name'],'score'=>(float)$best['score'],'runner_up_score'=>$second,'margin'=>$margin,'distance_km'=>$best['distance_km']];
    return['route'=>'needs_extra_evidence','reason'=>'provider_geo_fuzzy_below_margin','target'=>$bestId,'score'=>(float)$best['score'],'runner_up_score'=>$second,'margin'=>$margin];
}

if(getenv('MATCH_PROVIDER_GEO_TEST_LIBRARY')==='1')return;
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$op=(string)getenv('MATCH_OPERATION_ID');$sha=(string)getenv('MATCH_SOURCE_SHA');if($op!==MPG_OP||!preg_match('/^[0-9a-f]{40}$/D',$sha))throw new RuntimeException('operation_or_source_guard');$home=(string)getenv('HOME');if($home==='')throw new RuntimeException('home');$dir=$home.'/.anytoour-match/operations/'.MPG_OP;$res=mcr_evidence((string)@file_get_contents($dir.'/reservation.json'));if(($res['operation_id']??'')!==MPG_OP||($res['source_sha']??'')!==$sha||($res['state']??'')!=='reserved_before_db_access')throw new RuntimeException('reservation');$root=realpath(getcwd());if(!$root||basename($root)!=='anytoour.ru')throw new RuntimeException('root');require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');$db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
try{
 $core=[];foreach(mpg_query($db,'SELECT id,name FROM catalog_countries WHERE is_active=1 ORDER BY id') as $c)if(mcr_is_core8_name((string)$c['name']))$core[(int)$c['id']]=(string)$c['name'];if(count($core)<6)throw new RuntimeException('core8');$marks=implode(',',array_fill(0,count($core),'?'));
 $hotels=[];$forms=[];$scope=[];foreach(mpg_query($db,"SELECT id,country_id,country_name,region_id,region_name,subregion_id,subregion_name,name,category,latitude,longitude FROM catalog_hotels WHERE is_active=1 AND country_id IN ($marks) ORDER BY country_id,id",array_keys($core)) as $h){$id=(int)$h['id'];$hotels[$id]=$h;$forms[$id]=[(string)$h['name']];$cid=(int)$h['country_id'];$rid=(int)$h['region_id'];$sid=(int)$h['subregion_id'];if($rid>0)$scope[$cid]['region'][$rid][$id]=true;if($sid>0)$scope[$cid]['subregion'][$sid][$id]=true;}foreach(mpg_query($db,"SELECT a.hotel_id,a.alias FROM hotel_aliases a JOIN catalog_hotels h ON h.id=a.hotel_id WHERE h.is_active=1 AND h.country_id IN ($marks) ORDER BY a.hotel_id,a.id",array_keys($core)) as $a){$id=(int)$a['hotel_id'];if(isset($forms[$id]))$forms[$id][]=(string)$a['alias'];}
 $all=mpg_query($db,"SELECT external_hotel_id,evidence_json FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' ORDER BY external_hotel_id");$accepted=mpg_query($db,"SELECT i.external_hotel_id,i.local_hotel_id,i.evidence_json,h.country_id AS local_country_id,h.region_id AS local_region_id,h.subregion_id AS local_subregion_id FROM andromeda_hotel_identities i JOIN catalog_hotels h ON h.id=i.local_hotel_id AND h.is_active=1 WHERE i.supplier_namespace='andromeda_catalog' AND i.decision_status='accepted' AND i.local_hotel_id IS NOT NULL ORDER BY i.external_hotel_id");$country=msac_accepted_country_consensus($accepted,$all,$core);$geo=mpg_build_consensus($accepted);
 $pending=mpg_query($db,"SELECT supplier_namespace,external_hotel_id,evidence_sha256,evidence_json FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='pending' AND local_hotel_id IS NULL ORDER BY external_hotel_id");$routes=[];$candidates=[];$reasons=[];$withGeo=0;$freq=0;
 foreach($pending as $r){$e=mcr_evidence((string)$r['evidence_json']);$sk=mcr_state_key($e);if($sk===null||!isset($country['inferred'][$sk]))continue;$cid=(int)$country['inferred'][$sk]['country_id'];$allow=mpg_allowed_ids($e,$cid,$geo,$scope);if($allow['status']!=='no_geo_consensus')$withGeo++;if($allow['status']==='geo_consensus_conflict')$sel=['route'=>'hard_conflict','reason'=>'provider_geo_consensus_conflict'];elseif($allow['status']!=='ok')$sel=['route'=>'needs_extra_evidence','reason'=>'no_provider_geo_consensus'];else$sel=mpg_select(mcr_names($e),mcr_points($e),$allow['ids'],$hotels,$forms);$item=array_merge(['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>(string)$r['external_hotel_id'],'evidence_sha256'=>(string)$r['evidence_sha256'],'state_key'=>$sk,'country_id'=>$cid,'frequency'=>mcr_frequency($e),'geo_anchors'=>$allow['anchors']],$sel);$routes[$item['route']][]=$item;$reasons[$item['reason']]=($reasons[$item['reason']]??0)+1;if($item['route']==='auto_accept_candidate'){$candidates[]=$item;$freq+=(int)$item['frequency'];}}
 foreach($routes as &$ls)usort($ls,fn($a,$b)=>(($b['frequency']??0)<=>($a['frequency']??0))?:strcmp((string)$a['external_hotel_id'],(string)$b['external_hotel_id']));unset($ls);usort($candidates,fn($a,$b)=>(($b['frequency']??0)<=>($a['frequency']??0))?:strcmp((string)$a['external_hotel_id'],(string)$b['external_hotel_id']));ksort($routes);ksort($reasons);
 $result=['schema'=>'hotel-match-provider-geo-consensus-review/1','operation_id'=>MPG_OP,'source_sha'=>$sha,'state'=>'completed_read_only','server_current'=>true,'transaction'=>'REPEATABLE READ READ ONLY','accepted_andromeda_rows'=>count($accepted),'pending_andromeda_rows'=>count($pending),'usable_geo_key_values'=>count($geo['usable']),'rejected_geo_key_values'=>count($geo['rejected']),'pending_with_usable_geo'=>$withGeo,'candidate_count'=>count($candidates),'candidate_live_frequency_sum'=>$freq,'candidates'=>$candidates,'route_counts'=>array_map('count',$routes),'reason_counts'=>$reasons,'geo_consensus'=>$geo,'supplier_calls'=>0,'tourvisor_calls'=>0,'external_calls'=>0,'booking_calls'=>0,'db_writes'=>0,'mapping_writes'=>0,'operator_5_writes'=>0,'no_replay'=>true,'created_at'=>gmdate('c')];$hash=mpg_write($dir.'/result.json',$result);$raw=(string)file_get_contents($dir.'/result.json');$x=mcr_evidence($raw);if(hash('sha256',$raw)!==$hash||($x['state']??'')!=='completed_read_only')throw new RuntimeException('result_readback');mpg_write($dir.'/receipt.json',['operation_id'=>MPG_OP,'source_sha'=>$sha,'state'=>'completed_read_only','result_sha256'=>$hash,'readback_verified'=>true,'no_replay'=>true,'created_at'=>gmdate('c')]);$db->rollBack();echo 'MATCH_PROVIDER_GEO_CONSENSUS_OK '.count($candidates)."\n";
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw$e;}
