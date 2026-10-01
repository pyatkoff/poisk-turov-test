<?php
declare(strict_types=1);
const LPA_OPERATION='int-andromeda-local-profile-apply-4191-retained36-20261001-v1';
const LPA_PLAN_OPERATION='int-andromeda-local-profile-plan-4191-20261001-v2';
const LPA_PLAN_SHA='a2306ab97e596b5df3d3eb54e85948c0e69a29237a9e96d697017db1cd902988';
const LPA_BATCH='local4191-retained36-20261001';
const LPA_FIELDS=['description','primaryImage','images','address','place','build','repair','square','hotelInformation.infrastructure','hotelInformation.services','hotelInformation.meals','hotelInformation.roomTypes'];
function lpa_need(bool $v,string $r):void{if(!$v)throw new RuntimeException($r);}
function lpa_file(string $p,int $max):string{$b=is_file($p)&&!is_link($p)&&realpath($p)===$p&&filesize($p)>0&&filesize($p)<=$max?file_get_contents($p):false;lpa_need(is_string($b),'private_file');return $b;}
function lpa_json(array $v):string{return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function lpa_save(string $p,array $v):void{$b=lpa_json($v)."\n";lpa_need(!file_exists($p)&&!is_link($p)&&strlen($b)<=65536,'private_output');$f=fopen($p,'x');lpa_need(is_resource($f),'private_output');try{lpa_need(chmod($p,0600)&&fwrite($f,$b)===strlen($b)&&fflush($f),'private_output');if(function_exists('fsync'))lpa_need(fsync($f),'private_flush');}finally{fclose($f);}}
function lpa_scope(array $p):array{
 lpa_need(($p['schema_version']??null)===1&&($p['operation_id']??null)===LPA_PLAN_OPERATION&&($p['safe_to_apply']??null)===false&&is_string($p['demand_through']??null)&&is_array($p['rows']??null)&&count($p['rows'])===366,'plan_contract');
 $counts=[];$scope=[];$seen=[];
 foreach($p['rows'] as $r){lpa_need(is_array($r)&&is_int($r['anytourHotelId']??null)&&$r['anytourHotelId']>0&&is_string($r['state']??null)&&!isset($seen[$r['anytourHotelId']]),'plan_row');$seen[$r['anytourHotelId']]=1;$s=$r['state'];$counts[$s]=($counts[$s]??0)+1;if($s!=='RETAINED_DELTA_PREPARED')continue;
  $own=$r['anytourHotelId'];$local=$r['localHotelId']??null;lpa_need(is_int($local)&&$local>0&&is_string($r['expectedProfileSha256']??null)&&preg_match('/^[a-f0-9]{64}$/D',$r['expectedProfileSha256'])===1&&is_int($r['expectedRevision']??null)&&$r['expectedRevision']>=1&&is_string($r['expectedAliasSha256']??null)&&preg_match('/^[a-f0-9]{64}$/D',$r['expectedAliasSha256'])===1,'retained_row');
  $scope[$own]=['anytourHotelId'=>$own,'localHotelId'=>$local,'fields'=>LPA_FIELDS,'expectedProfileSha256'=>$r['expectedProfileSha256'],'expectedRevision'=>$r['expectedRevision'],'expectedAliasSha256'=>$r['expectedAliasSha256']];
 }
 ksort($counts);lpa_need($counts===['D1_OVERLAP_HELD'=>10,'RETAINED_DELTA_PREPARED'=>36,'SOURCE_MISSING'=>320]&&count($scope)===36,'plan_counts');ksort($scope,SORT_NUMERIC);return ['through'=>$p['demand_through'],'scope'=>$scope];
}
function lpa_main(array $argv):int{
 lpa_need(PHP_SAPI==='cli'&&$argv===[(string)($argv[0]??''),'--apply-retained36'],'disabled');
 $home=(string)getenv('HOME');$root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('LOCAL_PROFILE_APPLY_DIR');$head=(string)getenv('LOCAL_PROFILE_SOURCE_SHA');$control=(string)getenv('LOCAL_PROFILE_CONTROL_SHA');
 lpa_need($root===$home.'/www/anytoour.ru'&&realpath($root)===$root&&realpath($dir)===$dir&&dirname($dir)===$home.'/.anytoour-int-executor'&&basename($dir)===LPA_OPERATION&&preg_match('/^[a-f0-9]{40}$/D',$head)===1&&preg_match('/^[a-f0-9]{40}$/D',$control)===1,'runtime_scope');
 $reservation=json_decode(lpa_file($dir.'/reservation.json',65536),true,32,JSON_THROW_ON_ERROR);lpa_need(($reservation['operation_id']??null)===LPA_OPERATION&&($reservation['source_sha']??null)===$head&&($reservation['mode']??null)==='local-profile-apply-4191','reservation');
 lpa_need(!file_exists($dir.'/local-apply-receipt.json'),'no_replay');
 $planPath=$home.'/.anytoour-int-executor/'.LPA_PLAN_OPERATION.'/local-plan.json';$bytes=lpa_file($planPath,32*1024*1024);lpa_need(hash_equals(LPA_PLAN_SHA,hash('sha256',$bytes)),'plan_digest');$private=json_decode($bytes,true,512,JSON_THROW_ON_ERROR);lpa_need(is_array($private),'plan_json');$x=lpa_scope($private);
 $bootstrap=$root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');lpa_need(is_file($bootstrap)&&!is_link($bootstrap)&&realpath($bootstrap)===$bootstrap,'bootstrap');$_SERVER['DOCUMENT_ROOT']=$root;require_once $bootstrap;require_once dirname(__DIR__,2).'/v2/data/anytour-profile-enrichment-v1.php';
 $db=v2_data_db();lpa_need($db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql','mysql_required');$owner=new AnyTourProfileEnrichmentV1($db);
 $scope=array_values(array_map(static fn(array $r):array=>array_intersect_key($r,array_flip(['anytourHotelId','localHotelId','fields'])),$x['scope']));
 $plan=$owner->plan(36,$x['through'],$scope,true);lpa_need(($plan['status']??null)==='prepared_read_only'&&($plan['writes']??null)===0&&($plan['supplierCalls']??null)===0&&count($plan['selected']??[])===36,'fresh_plan');
 $expected=$x['scope'];$seen=[];foreach($plan['selected'] as $item){$own=$item['anytourHotelId']??null;lpa_need(is_int($own)&&isset($expected[$own])&&!isset($seen[$own])&&($item['localHotelId']??null)===$expected[$own]['localHotelId']&&($item['expectedProfileSha256']??null)===$expected[$own]['expectedProfileSha256']&&($item['expectedRevision']??null)===$expected[$own]['expectedRevision']&&($item['expectedAliasSha256']??null)===$expected[$own]['expectedAliasSha256'],'fresh_plan_identity');$seen[$own]=1;}lpa_need(count($seen)===36,'fresh_plan_count');
 $applied=$owner->apply(LPA_OPERATION,36,$x['through'],(string)$plan['planSha256'],$scope,true);lpa_need(($applied['status']??null)==='committed_verified'&&($applied['profilesUpdated']??null)===36&&($applied['profileWrites']??null)===36&&($applied['provenanceWrites']??null)===36&&($applied['supplierCalls']??null)===0&&($applied['mappingWrites']??null)===0&&($applied['legacyWrites']??null)===0,'apply_contract');
 $receipt=['schema_version'=>1,'state'=>'committed_verified','operation_id'=>LPA_OPERATION,'source_sha'=>$head,'control_source_sha'=>$control,'batch'=>LPA_BATCH,'private_plan_sha256'=>LPA_PLAN_SHA,'requested_profiles'=>36,'profiles_updated'=>36,'fields_updated'=>(int)$applied['fieldsFilled'],'field_counts'=>$applied['fieldCounts'],'profile_writes'=>36,'provenance_writes'=>36,'supplier_calls'=>0,'provider_http_calls'=>0,'mapping_writes'=>0,'legacy_writes'=>0,'schema_writes'=>0,'readback_verified'=>true];lpa_save($dir.'/local-apply-receipt.json',$receipt);echo lpa_json($receipt)."\n";return 0;
}
if(PHP_SAPI==='cli'&&realpath((string)($argv[0]??''))===__FILE__){try{exit(lpa_main($argv));}catch(Throwable){fwrite(STDERR,"local_profile_apply_failed_no_replay\n");exit(2);}}
