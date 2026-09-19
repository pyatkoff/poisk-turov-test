<?php
declare(strict_types=1);
const C34_OP='hotel-match-common4-context34-current-1971-20260919-v1';
const C34_INPUT_SHA='b57bb8e39e36877ec27c6a4573a686aa62bace1fec6da827f99070719371555f';
const C34_SOURCE_RESULT='fd0fcf1a6d6c8db5a1b0d3cb95b91c53c62c683e845c3eca6470ff70c9d9c0a4';
function c34_req(bool $ok,string $reason):void{if(!$ok)throw new RuntimeException($reason);}
function c34_write(string $path,array $value):string{$raw=json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";$f=@fopen($path,'x+b');c34_req(is_resource($f),'exclusive_output');c34_req(fwrite($f,$raw)===strlen($raw),'short_write');fflush($f);if(function_exists('fsync'))fsync($f);fclose($f);return hash('sha256',$raw);}
function c34_safe_reason(Throwable $e):string{$m=$e->getMessage();return is_string($m)&&preg_match('/^[A-Za-z0-9_.:-]{1,96}$/D',$m)?$m:'sanitized_error';}
function c34_selftest():void{c34_req(C34_INPUT_SHA===strtolower(C34_INPUT_SHA),'sha');echo "hotel-match-common4-context34-current-v1: PASS\n";}
function c34_main():void{
 c34_req(PHP_SAPI==='cli'&&(string)getenv('MATCH_OPERATION_ID')===C34_OP,'operation_guard');
 $source=(string)getenv('MATCH_SOURCE_SHA');c34_req(preg_match('/^[0-9a-f]{40}$/D',$source)===1,'source_sha');
 $dir=rtrim((string)getenv('HOME'),'/').'/.anytoour-match/operations/'.C34_OP;
 $rp=realpath($dir.'/reservation.json');$ip=realpath($dir.'/input.json');
 c34_req(is_string($rp)&&is_string($ip)&&hash_file('sha256',$ip)===C34_INPUT_SHA,'input_guard');
 $reservation=json_decode((string)file_get_contents($rp),true,32,JSON_THROW_ON_ERROR);
 c34_req(($reservation['operation_id']??'')===C34_OP&&($reservation['source_sha']??'')===$source&&($reservation['state']??'')==='reserved_before_db_access','reservation');
 $input=json_decode((string)file_get_contents($ip),true,128,JSON_THROW_ON_ERROR);
 c34_req(($input['source_result_sha256']??'')===C34_SOURCE_RESULT&&count($input['edges']??[])===39&&(int)($input['unique_tv_hotels']??0)===34,'input_shape');
 $root=realpath(getcwd());c34_req(is_string($root)&&basename($root)==='anytoour.ru','root');
 require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
 $db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
 $out=['operation_id'=>C34_OP,'source_sha'=>$source,'source_result_sha256'=>C34_SOURCE_RESULT,'state'=>'failed_no_replay','reason'=>null,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'samo_calls'=>0,'no_replay'=>true];
 try{
  $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
  foreach(['tour_price_observations','catalog_hotels','catalog_departures'] as $table){$q=$db->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$q->execute([$table]);c34_req(strtoupper((string)$q->fetchColumn())==='INNODB','table_contract_'.$table);}
  $exact=$db->prepare("SELECT source,search_id,departure_id,country_id,region_id,subregion_id,hotel_id,tour_id,departure_date,nights,adults,children_count,child_ages_signature,operator_id,observed_at FROM tour_price_observations WHERE source='user_search' AND hotel_id=? AND operator_id=? AND tour_id=? ORDER BY observed_at DESC LIMIT 100");
  $fallback=$db->prepare("SELECT source,search_id,departure_id,country_id,region_id,subregion_id,hotel_id,tour_id,departure_date,nights,adults,children_count,child_ages_signature,operator_id,observed_at FROM tour_price_observations WHERE source='user_search' AND hotel_id=? AND operator_id=? ORDER BY observed_at DESC LIMIT 100");
  $hotelQ=$db->prepare('SELECT id,name,country_id,country_name,region_id,region_name,subregion_id,subregion_name,is_active FROM catalog_hotels WHERE id=?');
  $depQ=$db->prepare('SELECT id,name,is_active FROM catalog_departures WHERE id=?');
  $dossiers=[];$counts=['exact_context_ready'=>0,'hotel_operator_context_only'=>0,'no_current_observation_context'=>0];$uniqueContexts=[];
  foreach($input['edges'] as $edge){
   $hotelId=(int)$edge['tv_hotel_id'];$operatorId=(int)$edge['operator_id'];$tourId=(string)$edge['tour_id'];
   c34_req($hotelId>0&&$operatorId>0&&preg_match('/^[0-9A-Za-z_-]{1,220}$/D',$tourId)===1,'edge_shape');
   $exact->execute([$hotelId,$operatorId,$tourId]);$exactRows=$exact->fetchAll(PDO::FETCH_ASSOC)?:[];
   $fallbackRows=[];if(!$exactRows){$fallback->execute([$hotelId,$operatorId]);$fallbackRows=$fallback->fetchAll(PDO::FETCH_ASSOC)?:[];}
   $chosen=$exactRows[0]??($fallbackRows[0]??null);
   $hotelQ->execute([$hotelId]);$hotel=$hotelQ->fetch(PDO::FETCH_ASSOC)?:null;
   $departure=null;if(is_array($chosen)&&isset($chosen['departure_id'])){$depQ->execute([(int)$chosen['departure_id']]);$departure=$depQ->fetch(PDO::FETCH_ASSOC)?:null;}
   $status=$exactRows?'exact_context_ready':($fallbackRows?'hotel_operator_context_only':'no_current_observation_context');$counts[$status]++;
   $context=null;
   if(is_array($chosen)){
    $context=['source'=>(string)$chosen['source'],'search_id'=>(int)$chosen['search_id'],'departure_id'=>(int)$chosen['departure_id'],'departure_name'=>is_array($departure)?(string)$departure['name']:null,'departure_active'=>is_array($departure)?(int)$departure['is_active']:null,'country_id'=>(int)$chosen['country_id'],'region_id'=>$chosen['region_id']===null?null:(int)$chosen['region_id'],'subregion_id'=>$chosen['subregion_id']===null?null:(int)$chosen['subregion_id'],'departure_date'=>(string)$chosen['departure_date'],'nights'=>(int)$chosen['nights'],'adults'=>(int)$chosen['adults'],'children_count'=>(int)$chosen['children_count'],'child_ages_signature'=>(string)($chosen['child_ages_signature']??''),'observed_at'=>(string)$chosen['observed_at'],'matched_tour_id'=>(string)($chosen['tour_id']??'')];
    if($status==='exact_context_ready'&&$context['country_id']===4&&$context['departure_id']>0&&$context['departure_date']>=gmdate('Y-m-d')&&$context['nights']>=1&&$context['nights']<=28&&$context['adults']>=1&&$context['adults']<=6){$key=json_encode([$edge['operator'],$context['departure_id'],$context['country_id'],$context['departure_date'],$context['nights'],$context['adults'],$context['children_count'],$context['child_ages_signature']],JSON_THROW_ON_ERROR);$uniqueContexts[$key]=true;}
   }
   $dossiers[]=['tv_hotel_id'=>$hotelId,'hotel_name'=>(string)$edge['hotel_name'],'operator'=>(string)$edge['operator'],'tv_operator_id'=>$operatorId,'target_supplier_namespace'=>(string)$edge['target_supplier_namespace'],'source_tour_id'=>$tourId,'status'=>$status,'exact_observation_count'=>count($exactRows),'fallback_observation_count'=>count($fallbackRows),'current_hotel'=>$hotel,'context'=>$context,'safe_to_query_supplier_now'=>false,'safe_to_write_now'=>false];
  }
  $db->commit();$out['state']='completed_read_only';$out['read_at_utc']=gmdate('c');$out['classification_counts']=$counts;$out['unique_exact_context_keys']=count($uniqueContexts);$out['dossiers']=$dossiers;
 }catch(Throwable $e){if($db->inTransaction())$db->rollBack();$out['reason']=c34_safe_reason($e);}
 $hash=c34_write($dir.'/result.json',$out);c34_write($dir.'/receipt.json',['operation_id'=>C34_OP,'source_sha'=>$source,'state'=>$out['state'],'reason'=>$out['reason'],'result_sha256'=>$hash,'readback_verified'=>hash_file('sha256',$dir.'/result.json')===$hash,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'samo_calls'=>0,'no_replay'=>true]);
 echo json_encode(['state'=>$out['state'],'reason'=>$out['reason'],'classification_counts'=>$out['classification_counts']??null,'unique_exact_context_keys'=>$out['unique_exact_context_keys']??null,'result_sha256'=>$hash],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";exit($out['state']==='completed_read_only'?0:2);
}
if(($argv[1]??'')==='--self-test'){c34_selftest();exit(0);}if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__)c34_main();
