<?php
declare(strict_types=1);
/** MATCH-only CURRENT receiver for sealed public operator evidence. READ ONLY. */
const HMPOR_OP='hotel-match-public-operator-current-receiver-1971-20260917-v1';
const HMPOR_EVIDENCE_JSON=<<<'JSON'
[
{"ns":"operator_342","external_id":"549","andromeda_id":"2000034121","source_name":"Delphin Palace","source_geo":"Лара"},
{"ns":"operator_342","external_id":"651","andromeda_id":"2000098416","source_name":"Fifty Five Suite","source_geo":"Мармарис"},
{"ns":"operator_342","external_id":"655","andromeda_id":"190031","source_name":"Ramada Plaza by Wyndham Antalya","source_geo":"Анталия"},
{"ns":"operator_342","external_id":"743","andromeda_id":"3060","source_name":"Labranda Mares Hotel","source_geo":"Ичмелер"},
{"ns":"operator_342","external_id":"12135","andromeda_id":"175606","source_name":"Rixos Sharm El Sheikh (Nabq Bay)","source_geo":"Набк Бэй"},
{"ns":"operator_342","external_id":"12281","andromeda_id":"2000039701","source_name":"Ivy Cyrene Sharm (ex. Cyrene Sharm)","source_geo":"Рас-Насрани"},
{"ns":"operator_342","external_id":"17163","andromeda_id":"2000023232","source_name":"The Meretto Hotel Istanbul Old City","source_geo":"Султанахмет"},
{"ns":"operator_342","external_id":"18273","andromeda_id":"2000062084","source_name":"Raimond Hotel","source_geo":"Кумкапы"},
{"ns":"operator_342","external_id":"19652","andromeda_id":"67775","source_name":"Grand Emin Hotel","source_geo":"Йеникапы"},
{"ns":"operator_342","external_id":"21978","andromeda_id":"650","source_name":"Conrad Istanbul Bosphorus","source_geo":"Бешикташ"},
{"ns":"operator_342","external_id":"23547","andromeda_id":"182522","source_name":"Best Nobel Hotel","source_geo":"Сиркеджи"},
{"ns":"operator_342","external_id":"24278","andromeda_id":"2000072249","source_name":"Badawia Sharm Resort","source_geo":"Шарм-эль-Шейх"},
{"ns":"operator_342","external_id":"24891","andromeda_id":"2000057636","source_name":"Bodrium Otel & You Spa","source_geo":"Бодрум"},
{"ns":"operator_342","external_id":"24954","andromeda_id":"2000061716","source_name":"Le Meridien Bodrum Beach Resort","source_geo":"Бодрум"},
{"ns":"operator_342","external_id":"25728","andromeda_id":"2000052591","source_name":"Arabia Azur Hurgada","source_geo":"Хургада"},
{"ns":"operator_342","external_id":"27835","andromeda_id":"2000073592","source_name":"Taxim Town Hotel","source_geo":"Таксим"},
{"ns":"operator_342","external_id":"28164","andromeda_id":"2000071512","source_name":"Golden Gate","source_geo":"Топкапы"},
{"ns":"operator_342","external_id":"32987","andromeda_id":"2000086129","source_name":"The Norm Collection Door","source_geo":"Торба"},
{"ns":"operator_342","external_id":"36672","andromeda_id":"2000034136","source_name":"Green Maxx Hotel","source_geo":"Кадрие"},
{"ns":"operator_315","external_id":"19155","andromeda_id":"2000043232","source_name":"Domina Coral Bay King's Lake","source_geo":"Шейх Коаст"},
{"ns":"operator_315","external_id":"69721","andromeda_id":"175606","source_name":"Rixos Sharm El Sheikh (Adult Only 18+)","source_geo":"Набк"},
{"ns":"operator_315","external_id":"780677","andromeda_id":"2000062084","source_name":"Raimond","source_geo":"Кумкапы"}
]
JSON;
function hmpor_req(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
function hmpor_json(array $x):string{return json_encode($x,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";}
function hmpor_write(string $path,array $x):string{$raw=hmpor_json($x);$f=@fopen($path,'x+b');hmpor_req(is_resource($f),'exclusive_output');try{hmpor_req(fwrite($f,$raw)===strlen($raw)&&fflush($f),'output_write');if(function_exists('fsync'))hmpor_req(fsync($f),'output_sync');rewind($f);hmpor_req(stream_get_contents($f)===$raw,'output_readback');}finally{fclose($f);}return hash('sha256',$raw);}
function hmpor_read(string $path,int $max=32768):array{hmpor_req(is_file($path)&&!is_link($path)&&realpath($path)===$path,'input_path');$raw=file_get_contents($path);hmpor_req(is_string($raw)&&strlen($raw)<=$max,'input_size');$x=json_decode($raw,true,64,JSON_THROW_ON_ERROR);hmpor_req(is_array($x),'input_shape');return$x;}
function hmpor_q(PDO $db,string $sql,array $args=[]):array{$q=$db->prepare($sql);$q->execute(array_values($args));$rows=$q->fetchAll(PDO::FETCH_ASSOC);hmpor_req(count($rows)<100000,'query_budget');return$rows;}
function hmpor_protected($v,string $key='',int $depth=0):bool{if($depth>20)return true;$s=preg_match('/manual|exclude|exclusion|conflict|reject|review|protect/i',$key)===1;if(is_array($v)){if($s&&$v!==[])return true;foreach($v as$k=>$x)if(hmpor_protected($x,(string)$k,$depth+1))return true;return false;}if(!$s||$v===null)return false;if(is_bool($v))return$v;if(is_numeric($v))return(float)$v!=0;if(is_string($v))return!in_array(strtolower(trim($v)),['','false','none','no','null'],true);return true;}
function hmpor_norm(string $s):string{$s=preg_replace('/\s*\((?:ex|ех)\.?[^)]*\)\s*$/iu','',$s)??$s;$s=strtolower(trim($s));$s=preg_replace('/[^\p{L}\p{N}]+/u','',$s)??'';return$s;}
function hmpor_bridge(array $e,string $andromedaId):?array{$b=$e['provider_bridges']??null;if(!is_array($b))return null;foreach($b as$x)if(is_array($x)&&(string)($x['andromeda_hotel_id']??'')===$andromedaId)return$x;return null;}
function hmpor_main():void{
 hmpor_req(PHP_SAPI==='cli'&&getenv('MATCH_OPERATION_ID')===HMPOR_OP,'operation_guard');$sha=(string)getenv('MATCH_SOURCE_SHA');hmpor_req(preg_match('/^[a-f0-9]{40}$/D',$sha)===1,'source_guard');
 $evidence=json_decode(HMPOR_EVIDENCE_JSON,true,32,JSON_THROW_ON_ERROR);hmpor_req(is_array($evidence)&&count($evidence)===22,'evidence_count');$uniq=[];$keys=[];foreach($evidence as$e){hmpor_req(is_array($e)&&in_array($e['ns']??'', ['operator_315','operator_342'],true),'evidence_ns');foreach(['external_id','andromeda_id','source_name','source_geo']as$k)hmpor_req(is_string($e[$k]??null)&&$e[$k]!=='','evidence_field');hmpor_req(ctype_digit($e['external_id'])&&ctype_digit($e['andromeda_id']),'evidence_id');$key=$e['ns'].'/'.$e['external_id'];hmpor_req(!isset($keys[$key]),'duplicate_operator_row');$keys[$key]=1;$uniq[$e['andromeda_id']]=1;}hmpor_req(count($uniq)===20,'unique_andromeda_count');
 $dir=(string)getenv('HOME').'/.anytoour-match/operations/'.HMPOR_OP;$reservation=hmpor_read($dir.'/reservation.json');hmpor_req(($reservation['operation_id']??'')===HMPOR_OP&&($reservation['source_sha']??'')===$sha&&($reservation['state']??'')==='reserved_before_db_access'&&($reservation['evidence_rows']??0)===22,'reservation_guard');
 $out=['operation_id'=>HMPOR_OP,'source_sha'=>$sha,'state'=>'failed_no_replay','no_replay'=>true,'sealed_public_evidence_rows'=>22,'unique_andromeda_ids'=>20,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'safe_to_write_now'=>false];$db=null;$phase='bootstrap';ob_start();try{
  $root=realpath(getcwd());hmpor_req(is_string($root)&&basename($root)==='anytoour.ru','root_guard');$bp=$root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');hmpor_req(realpath($bp)===$bp&&!is_link($bp),'bootstrap_path');require_once$bp;$db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
  $phase='current_read';$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
  $eng=hmpor_q($db,"SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('andromeda_hotel_identities','catalog_hotels') ORDER BY TABLE_NAME");hmpor_req(count($eng)===2,'table_contract');foreach($eng as$t)hmpor_req(strtoupper((string)$t['ENGINE'])==='INNODB','nontransactional_table');
  $counts=[];foreach(['andromeda_catalog','operator_315','operator_342','operator_5']as$ns){$r=hmpor_q($db,"SELECT COUNT(*) AS n FROM andromeda_hotel_identities WHERE supplier_namespace=? AND decision_status='pending' AND local_hotel_id IS NULL",[$ns]);$counts[$ns]=(int)$r[0]['n'];}
  $routes=[];$rows=[];$candidates=[];
  foreach($evidence as$e){$row=['supplier_namespace'=>$e['ns'],'external_hotel_id'=>$e['external_id'],'andromeda_hotel_id'=>$e['andromeda_id'],'public_source_name'=>$e['source_name'],'public_source_geo'=>$e['source_geo'],'route'=>null,'holds'=>[],'current_operator_status'=>null,'current_operator_local_id'=>null,'accepted_authority_local_id'=>null];
   $rr=hmpor_q($db,"SELECT supplier_namespace,external_hotel_id,decision_status,local_hotel_id,evidence_sha256,evidence_json FROM andromeda_hotel_identities WHERE supplier_namespace=? AND external_hotel_id=?",[$e['ns'],$e['external_id']]);
   if(count($rr)!==1){$row['route']='operator_row_missing_or_nonunique';$row['holds'][]='operator_row_count_'.count($rr);$rows[]=$row;$routes[$row['route']]=($routes[$row['route']]??0)+1;continue;}
   $r=$rr[0];$row['current_operator_status']=(string)$r['decision_status'];$row['current_operator_local_id']=$r['local_hotel_id']===null?null:(int)$r['local_hotel_id'];$oe=json_decode((string)$r['evidence_json'],true,64,JSON_THROW_ON_ERROR);hmpor_req(is_array($oe),'operator_evidence_shape');$bridge=hmpor_bridge($oe,$e['andromeda_id']);
   if($bridge===null){$row['route']='native_bridge_changed_or_missing';$row['holds'][]='expected_andromeda_bridge_absent';$rows[]=$row;$routes[$row['route']]=($routes[$row['route']]??0)+1;continue;}
   $bridgeName=(string)($bridge['hotel_name']??'');$bridgeCountry=(int)($bridge['country_id']??0);$row['bridge_country_id']=$bridgeCountry;$row['bridge_name']=$bridgeName;$row['operator_evidence_sha256']=(string)$r['evidence_sha256'];
   if(hmpor_norm($bridgeName)!==hmpor_norm($e['source_name']))$row['holds'][]='public_source_name_no_longer_matches_native_bridge';
   if(hmpor_protected($oe))$row['holds'][]='current_operator_protected';
   if((string)$r['decision_status']==='accepted'&&$r['local_hotel_id']!==null){$row['route']='already_resolved';$rows[]=$row;$routes[$row['route']]=($routes[$row['route']]??0)+1;continue;}
   if((string)$r['decision_status']!=='pending'||$r['local_hotel_id']!==null)$row['holds'][]='operator_not_pending_null';
   $aa=hmpor_q($db,"SELECT external_hotel_id,decision_status,local_hotel_id,evidence_sha256,evidence_json FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=?",[$e['andromeda_id']]);
   if(count($aa)!==1){$row['route']='public_name_geo_only';$row['holds'][]='accepted_authority_row_missing_or_nonunique';$rows[]=$row;$routes[$row['route']]=($routes[$row['route']]??0)+1;continue;}
   $a=$aa[0];$row['authority_status']=(string)$a['decision_status'];$row['authority_local_id']=$a['local_hotel_id']===null?null:(int)$a['local_hotel_id'];
   if((string)$a['decision_status']!=='accepted'||$a['local_hotel_id']===null){$row['route']='public_name_geo_only';$rows[]=$row;$routes[$row['route']]=($routes[$row['route']]??0)+1;continue;}
   $row['accepted_authority_local_id']=(int)$a['local_hotel_id'];$ae=json_decode((string)$a['evidence_json'],true,64,JSON_THROW_ON_ERROR);hmpor_req(is_array($ae),'authority_evidence_shape');if(hmpor_protected($ae))$row['holds'][]='accepted_authority_protected';
   $hh=hmpor_q($db,"SELECT id,country_id,name,is_active,latitude,longitude FROM catalog_hotels WHERE id=?",[(int)$a['local_hotel_id']]);if(count($hh)!==1|| (int)$hh[0]['is_active']!==1){$row['holds'][]='authority_target_inactive_or_missing';}else{$h=$hh[0];$row['authority_target']=['id'=>(int)$h['id'],'country_id'=>(int)$h['country_id'],'name'=>(string)$h['name']];if($bridgeCountry<=0||$bridgeCountry!==(int)$h['country_id'])$row['holds'][]='country_mismatch';}
   $busy=hmpor_q($db,"SELECT external_hotel_id FROM andromeda_hotel_identities WHERE supplier_namespace=? AND decision_status='accepted' AND local_hotel_id=? AND external_hotel_id<>? LIMIT 2",[$e['ns'],(int)$a['local_hotel_id'],$e['external_id']]);if($busy)$row['holds'][]='same_namespace_target_occupied';
   $row['holds']=array_values(array_unique($row['holds']));
   if($row['holds']){$row['route']='authority_present_but_held';}else{$row['route']='newly_authority_backed';$candidates[]=['supplier_namespace'=>$e['ns'],'external_hotel_id'=>$e['external_id'],'andromeda_hotel_id'=>$e['andromeda_id'],'local_hotel_id'=>(int)$a['local_hotel_id'],'operator_evidence_sha256'=>(string)$r['evidence_sha256'],'authority_evidence_sha256'=>(string)$a['evidence_sha256'],'public_source_name'=>$e['source_name'],'public_source_geo'=>$e['source_geo']];}
   $rows[]=$row;$routes[$row['route']]=($routes[$row['route']]??0)+1;
  }
  ksort($routes);$db->exec('ROLLBACK');$out['state']='completed_read_only';$out['checked_rows']=count($rows);$out['route_counts']=$routes;$out['rows']=$rows;$out['writer_candidates']=$candidates;$out['writer_candidate_count']=count($candidates);$out['current_pending_counts']=$counts;$out['current_pending_total']=array_sum($counts);$out['read_at_utc']=gmdate('c');$out['transaction']='REPEATABLE READ / READ ONLY';
 }catch(Throwable $x){if($db instanceof PDO&&$db->inTransaction())$db->rollBack();$out['error_phase']=$phase;$out['error_code']=preg_match('/^[a-z_0-9]{3,120}$/D',$x->getMessage())?$x->getMessage():'sanitized_failure';}while(ob_get_level())ob_end_clean();$digest=hmpor_write($dir.'/result.json',$out);hmpor_write($dir.'/receipt.json',['operation_id'=>HMPOR_OP,'source_sha'=>$sha,'state'=>$out['state'],'result_sha256'=>$digest,'readback_verified'=>true,'no_replay'=>true,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0]);echo hmpor_json(['state'=>$out['state'],'checked_rows'=>$out['checked_rows']??null,'route_counts'=>$out['route_counts']??[],'writer_candidate_count'=>$out['writer_candidate_count']??null,'current_pending_total'=>$out['current_pending_total']??null,'current_pending_counts'=>$out['current_pending_counts']??[],'result_sha256'=>$digest]);if($out['state']!=='completed_read_only')exit(2);
}
if(in_array('--self-test',$argv??[],true)){hmpor_req(hmpor_norm('Ivy Cyrene Sharm (ex. Cyrene Sharm)')==='ivycyrenesharm','norm_ex');hmpor_req(hmpor_norm('FUN&SUN FAMILY Sultan Gardens')==='funsunfamilysultangardens','norm_sanity');hmpor_req(hmpor_protected(['manual'=>true])&&!hmpor_protected(['conflict'=>false]),'protected');$e=json_decode(HMPOR_EVIDENCE_JSON,true,32,JSON_THROW_ON_ERROR);hmpor_req(count($e)===22,'count');echo "4 public-operator receiver self-tests PASS\n";exit;}
hmpor_main();
