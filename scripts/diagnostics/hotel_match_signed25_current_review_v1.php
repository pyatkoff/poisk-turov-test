<?php
declare(strict_types=1);
const S25_OP='hotel-match-signed25-current-review-1971-20260920-v1';
const S25_INPUT='471de6a56d2b1056c35207c8c2e8434c30e80c6f396cae7119b2b3632a7495a9';
function s25_need(bool $ok,string $reason):void{if(!$ok)throw new RuntimeException($reason);}
function s25_json(array $x):string{return json_encode($x,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";}
function s25_save(string $path,array $x):string{
 $raw=s25_json($x);$f=fopen($path,'xb');s25_need(is_resource($f),'exclusive_output');
 try{s25_need(fwrite($f,$raw)===strlen($raw)&&fflush($f),'output_write');if(function_exists('fsync'))s25_need(fsync($f),'output_sync');}finally{fclose($f);}
 s25_need(file_get_contents($path)===$raw,'output_readback');return hash('sha256',$raw);
}
function s25_rows(PDO $db,string $sql,array $params=[]):array{$q=$db->prepare($sql);$q->execute(array_values($params));$r=$q->fetchAll(PDO::FETCH_ASSOC);s25_need(count($r)<=50000,'row_budget');return $r;}
function s25_tokens(string $url):array{
 $p=parse_url($url);s25_need(is_array($p)&&($p['scheme']??'')==='https'&&($p['host']??'')==='agent.anextour.ru'&&($p['path']??'')==='/search/tour'&&!isset($p['user'],$p['pass'])&&!isset($p['port'])&&!isset($p['fragment']),'link_origin');
 s25_need(!isset($p['user'])&&!isset($p['pass']),'link_userinfo');$values=[];
 foreach(explode('&',$p['query']??'')as $part){[$key,$value]=array_pad(explode('=',$part,2),2,'');$key=urldecode($key);$value=urldecode($value);s25_need(!preg_match('/token|password|auth|secret|session|cookie/i',$key),'secret_field');if(strtoupper($key)==='HOTELLIST')$values[]=$value;}
 s25_need(count($values)===1,'hotellist_count');$tokens=explode(',',$values[0]);s25_need(count($tokens)===2&&count(array_unique($tokens))===2,'signed_token_count');$positive=[];$negative=[];
 foreach($tokens as $t){s25_need(preg_match('/^-?[1-9][0-9]{0,7}$/D',$t)===1,'token_format');if($t[0]==='-')$negative[]=$t;else $positive[]=$t;}
 s25_need(count($positive)===1&&count($negative)===1,'signed_domains');return ['values'=>$values,'tokens'=>$tokens,'positive_candidate'=>$positive[0],'negative_tokens'=>$negative];
}
function s25_norm(string $s):string{$s=function_exists('mb_strtolower')?mb_strtolower(trim($s),'UTF-8'):strtolower(trim($s));$s=preg_replace('/[^\p{L}\p{N}]+/u',' ',$s)??$s;return trim(preg_replace('/\s+/u',' ',$s)??$s);}
function s25_distance(array $a,array $b):?float{
 foreach(['latitude','longitude']as $k)if(!isset($a[$k],$b[$k])||!is_numeric($a[$k])||!is_numeric($b[$k]))return null;
 $la=(float)$a['latitude'];$lb=(float)$b['latitude'];$oa=(float)$a['longitude'];$ob=(float)$b['longitude'];if(abs($la)>90||abs($lb)>90||abs($oa)>180||abs($ob)>180||($la==0&&$oa==0)||($lb==0&&$ob==0))return null;
 return 12742000*asin(min(1,sqrt(sin(deg2rad($lb-$la)/2)**2+cos(deg2rad($la))*cos(deg2rad($lb))*sin(deg2rad($ob-$oa)/2)**2)));
}
function s25_validate(array $r):array{
 $hid=$r['tv_hotel_id']??null;$d=$r['detail']??[];$c=$r['census_row']??[];s25_need(is_int($hid)&&$hid>0&&($c['tv_hotel_id']??null)===$hid,'hotel_id');
 s25_need(($d['hotel']['id']??null)===$hid&&($d['operator']['id']??null)===13&&($d['operator']['name']??null)==='Anex','detail_identity');
 s25_need(($d['hotel']['country']['id']??null)===($c['country_id']??null)&&in_array($c['country_name']??null,['Катар','Маврикий'],true),'country_identity');
 s25_need(($r['raw_operator_link']??null)===($d['operatorLink']??null),'exact_raw_link');$t=s25_tokens($r['raw_operator_link']);
 s25_need($t['tokens']===($r['all_signed_tokens']??null)&&$t['values']===($r['raw_hotellist_values']??null)&&(int)$t['positive_candidate']===($r['positive_native_candidate']??null),'lossless_tokens');
 s25_need(($r['safe_to_write_now']??null)===false&&($r['safe_to_query_now']??null)===false,'authority_guard');s25_need(preg_match('/^[0-9a-f]{64}$/D',$r['source']['response_sha256']??'')===1,'raw_digest');return $t;
}
if(($argv[1]??'')==='--self-test'){
 $n=0;$ok=function(bool $b)use(&$n){$n++;s25_need($b,'selftest_'.$n);};$u='https://agent.anextour.ru/search/tour?HOTELLIST=123,-456';$t=s25_tokens($u);$ok($t['tokens']===['123','-456']);$ok($t['negative_tokens']===['-456']);$ok(s25_tokens(str_replace('123,-456','-456,123',$u))['positive_candidate']==='123');
 foreach(['123,456','-123,-456','123','123,-456,789','123,-456&HOTELLIST=123','123,-456&token=x','0,-456','123,-0','01,-456']as $v){try{s25_tokens('https://agent.anextour.ru/search/tour?HOTELLIST='.$v);$ok(false);}catch(RuntimeException $e){$ok(!str_starts_with($e->getMessage(),'selftest_'));}}
 $ok(s25_distance(['latitude'=>1,'longitude'=>1],['latitude'=>1,'longitude'=>1])===0.0);$ok(s25_distance([],[])===null);$ok(s25_distance(['latitude'=>1,'longitude'=>1],['latitude'=>2,'longitude'=>2])>5000);
 if(isset($argv[2])){$b=file_get_contents($argv[2]);$ok(hash('sha256',$b)===S25_INPUT);$i=json_decode($b,true,128,JSON_THROW_ON_ERROR);foreach($i['rows']as $r){$x=s25_validate($r);$ok(count($x['tokens'])===2);}$ok(count($i['rows'])===25);}
 echo 'MATCH_SIGNED25_SELFTEST_OK '.$n."\n";exit(0);
}
s25_need(PHP_SAPI==='cli'&&getenv('MATCH_OPERATION_ID')===S25_OP,'operation');$dir=rtrim((string)getenv('HOME'),'/').'/.anytoour-match/operations/'.S25_OP;s25_need(realpath((string)getenv('MATCH_OPERATION_DIR'))===$dir,'directory');
$sha=(string)getenv('MATCH_SOURCE_SHA');s25_need(preg_match('/^[0-9a-f]{40}$/D',$sha)===1,'source_sha');$inputBytes=(string)file_get_contents($dir.'/input.json');s25_need(hash('sha256',$inputBytes)===S25_INPUT,'input_hash');$input=json_decode($inputBytes,true,128,JSON_THROW_ON_ERROR);
s25_need(($input['schema']??'')==='match-signed25-current-input-v1'&&count($input['rows']??[])===25,'input_schema');$ids=[];$tokens=[];foreach($input['rows']as $r){$t=s25_validate($r);$ids[]=$r['tv_hotel_id'];foreach($t['tokens']as $v)$tokens[$v]=true;}s25_need(count(array_unique($ids))===25,'unique_targets');
$res=json_decode((string)file_get_contents($dir.'/reservation.json'),true,64,JSON_THROW_ON_ERROR);s25_need(($res['operation_id']??'')===S25_OP&&($res['source_sha']??'')===$sha&&($res['state']??'')==='reserved_before_db_read'&&($res['input_sha256']??'')===S25_INPUT,'reservation');
$base=['operation_id'=>S25_OP,'source_sha'=>$sha,'input_sha256'=>S25_INPUT,'provider_calls'=>0,'tourvisor_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'quota_mutations'=>0,'safe_to_query_now'=>false,'safe_to_write_now'=>false,'no_replay'=>true];$db=null;
try{
 s25_save($dir.'/execution-reservation.json',$base+['state'=>'started_before_db_read']);$root=realpath(getcwd());s25_need(is_string($root)&&basename($root)==='anytoour.ru','root');require_once $dir.'/source/app/integrations/anex-search-mapping-registry.php';require_once(is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php');$db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
 $tables=['catalog_hotels','anex_hotels','anex_hotel_search_mappings','anex_hotel_decisions','anex_review_pair_exclusions','anex_hotel_auto_matches','anex_search_hotel_observations','anex_hotel_candidates','andromeda_hotel_identities'];foreach($tables as $table){$e=s25_rows($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table]);s25_need(count($e)===1&&strtoupper((string)$e[0]['ENGINE'])==='INNODB','table_engine');}
 $registry=AnyTourAnexSearchMappingRegistry::fromPdo($db);$tvph=implode(',',array_fill(0,count($ids),'?'));$tokenIds=array_map('strval',array_keys($tokens));$ph=implode(',',array_fill(0,count($tokenIds),'?'));$both=array_merge($tokenIds,$ids);$s=[];
 $s['locals']=s25_rows($db,"SELECT id,name,country_id,country_name,region_name,subregion_name,category,latitude,longitude,is_active FROM catalog_hotels WHERE id IN ($tvph) ORDER BY id",$ids);
 $s['native']=s25_rows($db,"SELECT anex_hotel_id,xml_name,xml_alternate_name,api_name,api_country,api_region,api_town,latitude,longitude,source_fingerprint FROM anex_hotels WHERE anex_hotel_id IN ($ph) ORDER BY anex_hotel_id",$tokenIds);
 $s['maps']=s25_rows($db,"SELECT anex_hotel_id,catalog_hotel_id,match_class,scope,approval_policy,source_row_digest,mapping_digest,enabled FROM anex_hotel_search_mappings WHERE anex_hotel_id IN ($ph) OR catalog_hotel_id IN ($tvph) ORDER BY anex_hotel_id",$both);
 $s['decisions']=s25_rows($db,"SELECT anex_hotel_id,catalog_hotel_id,decision_status FROM anex_hotel_decisions WHERE anex_hotel_id IN ($ph) OR catalog_hotel_id IN ($tvph) ORDER BY anex_hotel_id",$both);
 $s['exclusions']=s25_rows($db,"SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions WHERE anex_hotel_id IN ($ph) OR catalog_hotel_id IN ($tvph) ORDER BY anex_hotel_id,catalog_hotel_id",$both);
 $s['auto']=s25_rows($db,"SELECT anex_hotel_id,row_digest,source_fingerprint,original_status,automated_status,automated_reason,suggested_catalog_hotel_id,candidate_count FROM anex_hotel_auto_matches WHERE anex_hotel_id IN ($ph) ORDER BY anex_hotel_id",$tokenIds);
 $s['observations']=s25_rows($db,"SELECT anex_hotel_id,hotel_name,country_id,anex_country_id,last_catalog_hotel_id,first_seen_utc,last_seen_utc,search_count,last_source_sha FROM anex_search_hotel_observations WHERE anex_hotel_id IN ($ph) ORDER BY anex_hotel_id",$tokenIds);
 $s['candidates']=s25_rows($db,"SELECT anex_hotel_id,candidate_rank,catalog_hotel_id,score,name_similarity,distance_m,country_match,address_exact,SHA2(candidate_json,256) AS candidate_json_sha256 FROM anex_hotel_candidates WHERE anex_hotel_id IN ($ph) OR catalog_hotel_id IN ($tvph) ORDER BY anex_hotel_id,candidate_rank",$both);
 $s['samo_identities']=s25_rows($db,"SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status,catalog_sha256,evidence_sha256,evidence_json FROM andromeda_hotel_identities WHERE local_hotel_id IN ($tvph) OR (supplier_namespace='operator_5' AND external_hotel_id IN ($ph)) ORDER BY supplier_namespace,external_hotel_id",array_merge($ids,$tokenIds));
 $dossiers=[];$counts=[];
 foreach($input['rows']as $r){$hid=$r['tv_hotel_id'];$aid=(string)$r['positive_native_candidate'];$t=s25_validate($r);$facts=[];
  foreach($s as $table=>$rows){$facts[$table]=array_values(array_filter($rows,static function($x)use($table,$hid,$t){if($table==='locals')return (int)$x['id']===$hid;if($table==='samo_identities')return (int)($x['local_hotel_id']??0)===$hid||($x['supplier_namespace']==='operator_5'&&in_array((string)$x['external_hotel_id'],$t['tokens'],true));return in_array((string)($x['anex_hotel_id']??''),$t['tokens'],true)||(int)($x['catalog_hotel_id']??0)===$hid;}));}
  $h=$facts['locals'][0]??null;$ns=array_values(array_filter($facts['native'],fn($x)=>(string)$x['anex_hotel_id']===$aid));$n=count($ns)===1?$ns[0]:null;$holds=[];
  if(count($facts['locals'])!==1||!$h||(int)$h['is_active']!==1)$holds[]='current_local_missing_inactive';if($h&&((int)$h['country_id']!==$r['census_row']['country_id']||$h['country_name']!==$r['census_row']['country_name']))$holds[]='country_drift';
  if($h&&s25_norm($h['name'])!==s25_norm($r['census_row']['name']))$holds[]='name_drift';$effective=$registry->resolve('anex_online',$aid,'preview');
  if($effective!==null&&$effective!==$hid)$holds[]='canonical_other_target';if($facts['decisions'])$holds[]='manual_preserved';if($facts['exclusions'])$holds[]='exclusions_preserved';
  foreach($facts['maps']as $m)if((string)$m['anex_hotel_id']!==$aid||(int)$m['catalog_hotel_id']!==$hid)$holds[]='source_or_target_occupied';
  foreach($facts['auto']as $a)if(preg_match('/conflict|exclu|reject|manual|competing|ambig|mismatch|review/i',implode(' ',array_map('strval',$a)))||((int)($a['suggested_catalog_hotel_id']??0)>0&&(int)$a['suggested_catalog_hotel_id']!==$hid))$holds[]='protected_auto_review';
  foreach($facts['candidates']as $c){if((string)$c['anex_hotel_id']!==$aid||(int)$c['catalog_hotel_id']!==$hid)$holds[]='competing_saved_candidate';if($c['country_match']!==null&&(int)$c['country_match']!==1)$holds[]='candidate_country_conflict';if(is_numeric($c['distance_m'])&&(float)$c['distance_m']>5000)$holds[]='candidate_geo_over_5km';}
  foreach($facts['observations']as $o){if((int)$o['country_id']!==$r['census_row']['country_id'])$holds[]='observed_country_conflict';if((int)$o['last_catalog_hotel_id']>0&&(int)$o['last_catalog_hotel_id']!==$hid)$holds[]='observed_other_target';}
  $distance=$h&&$n?s25_distance($h,$n):null;if($distance!==null&&$distance>5000)$holds[]='independent_geo_over_5km';
  $holds=array_values(array_unique($holds));sort($holds);$state=$effective===$hid?'already_effective':($holds?'current_protected':'current_facts_ready_for_independent_identity_review');$counts[$state]=($counts[$state]??0)+1;
  $dossiers[]=['tv_hotel_id'=>$hid,'positive_native_candidate'=>$aid,'state'=>$state,'holds'=>$holds,'canonical_anex_target'=>$effective,'independent_distance_m'=>$distance,'raw_operator_link'=>$r['raw_operator_link'],'all_signed_tokens'=>$t['tokens'],'negative_tokens'=>$t['negative_tokens'],'source'=>$r['source'],'current'=>$facts,'input_record_sha256'=>hash('sha256',s25_json($r)),'signed_token_semantics'=>'preserved_not_inferred','safe_to_query_now'=>false,'safe_to_write_now'=>false];
 }
 $clock=s25_rows($db,'SELECT UTC_TIMESTAMP AS db_utc_timestamp,NOW() AS db_now')[0];$db->rollBack();$result=$base+['state'=>'completed_read_only','clock'=>$clock,'input_count'=>25,'signed_tokens_checked_without_conversion'=>$tokenIds,'counts'=>$counts,'dossiers'=>$dossiers];
}catch(Throwable $e){if($db&&$db->inTransaction())$db->rollBack();$reason=preg_match('/^[a-z0-9_]+$/D',$e->getMessage())?$e->getMessage():'database_or_runtime_error';$result=$base+['state'=>'failed_no_replay','reason'=>$reason,'error_class'=>get_class($e)];}
$hash=s25_save($dir.'/result.json',$result);s25_save($dir.'/receipt.json',$base+['state'=>$result['state'],'result_sha256'=>$hash,'readback_verified'=>hash_file('sha256',$dir.'/result.json')===$hash]);echo s25_json(['state'=>$result['state'],'counts'=>$result['counts']??null,'result_sha256'=>$hash,'reason'=>$result['reason']??null]);exit($result['state']==='completed_read_only'?0:2);
