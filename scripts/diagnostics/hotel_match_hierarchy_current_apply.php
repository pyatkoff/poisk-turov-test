<?php
declare(strict_types=1);
/** One MATCH identity delta. No supplier, booking, schema or runtime changes. */
const HMHA_OP='hotel-match-hierarchy-current-apply-1971-20260916-v1';
const HMHA_INPUT='__SEALED_CURRENT_PLAN_BASE64__';
function hmha_require(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
function hmha_json(array $x):string{return json_encode($x,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";}
function hmha_write(string $path,array $x):string{
 $raw=hmha_json($x);$f=@fopen($path,'x+b');hmha_require(is_resource($f),'exclusive_output');
 try{hmha_require(fwrite($f,$raw)===strlen($raw)&&fflush($f),'output_write');if(function_exists('fsync'))hmha_require(fsync($f),'output_sync');rewind($f);hmha_require(stream_get_contents($f)===$raw,'output_readback');}finally{fclose($f);}return hash('sha256',$raw);
}
function hmha_query(PDO $db,string $sql,array $args=[]):array{$q=$db->prepare($sql);$q->execute(array_values($args));$r=$q->fetchAll(PDO::FETCH_ASSOC);hmha_require(count($r)<=150000,'query_budget');return$r;}
function hmha_protected($v,string $key='',int $depth=0):bool{
 if($depth>20)return true;if(is_array($v)){foreach($v as $k=>$x)if(hmha_protected($x,(string)$k,$depth+1))return true;return false;}
 if(!preg_match('/manual|exclude|exclusion|conflict|reject|review/i',$key))return false;
 if(is_bool($v))return$v;if(is_numeric($v))return(float)$v!=0;
 return is_string($v)&&!in_array(strtolower(trim($v)),['','false','none','no','null'],true);
}
function hmha_source(array $e):array{return is_array($e['source']??null)?$e['source']:$e;}
function hmha_states(array $e):array{
 $s=hmha_source($e);$out=[];foreach([$s['stateKey']??null,$s['state_key']??null,$e['stateKey']??null,$e['state_key']??null]as$v)if((is_int($v)||is_string($v))&&preg_match('/^[1-9][0-9]{0,9}$/D',(string)$v))$out[(string)$v]=true;
 $ids=array_map('strval',array_keys($out));sort($ids,SORT_STRING);return$ids;
}
function hmha_points($x,array &$out,int $depth=0):void{
 hmha_require($depth<=32,'coordinate_depth');if(!is_array($x))return;
 $a=$x['latitude']??$x['lat']??null;$b=$x['longitude']??$x['lon']??$x['lng']??null;
 if(is_numeric($a)&&is_numeric($b)&&is_finite((float)$a)&&is_finite((float)$b)&&abs((float)$a)<=90&&abs((float)$b)<=180&&((float)$a!=0||(float)$b!=0))$out[]=[(float)$a,(float)$b];
 foreach($x as $v)if(is_array($v))hmha_points($v,$out,$depth+1);
}
function hmha_distance(array $a,array $b):float{
 [$x,$y,$u,$v]=array_map('deg2rad',[$a[0],$a[1],$b[0],$b[1]]);$h=sin(($u-$x)/2)**2+cos($x)*cos($u)*sin(($v-$y)/2)**2;return 6371.0088*2*asin(sqrt(min(1.0,max(0.0,$h))));
}
function hmha_source_holds(?array $r,array $p,array $occupancy):array{
 if($r===null)return['source_missing'];$holds=[];
 if($r['decision_status']!=='pending'||$r['local_hotel_id']!==null)$holds[]='not_pending_null';
 if(!is_string($r['evidence_sha256'])||!hash_equals($p['evidence_sha256'],$r['evidence_sha256'])||!hash_equals($p['evidence_bytes_sha256'],hash('sha256',(string)$r['evidence_json'])))$holds[]='source_bytes_changed';
 $e=json_decode((string)$r['evidence_json'],true,64,JSON_THROW_ON_ERROR);hmha_require(is_array($e),'evidence_shape');
 if(hmha_protected($e)||array_key_exists('hierarchy_compound_acceptance',$e))$holds[]='protected_source';
 if(hmha_states($e)!==[(string)$p['state_key']])$holds[]='state_changed';
 foreach($occupancy as $id)if((string)$id!==$p['external_hotel_id'])$holds[]='target_occupied';return array_values(array_unique($holds));
}
function hmha_plan(array $p):void{
 hmha_require(($p['preflight_result_sha256']??'')==='cbd3d25c9d1e75ae0871dbeb74f0b97f7b546021a1be2b5b8f4a8b80afe80183','preflight_pin');
 hmha_require(($p['review_sha256']??'')==='554d38008fd9f20009f7b82c4cd57219d793f9c79b3de3bafb7e9bbeddf9e30d','review_pin');
 hmha_require(count($p['rows']??[])===5&&count($p['countries']??[])===8,'plan_population');$seen=[];$targets=[];
 foreach($p['rows']as$r){$id=$r['external_hotel_id'];hmha_require(is_string($id)&&preg_match('/^[1-9][0-9]{0,19}$/D',$id)===1&&!isset($seen[$id]),'plan_id');$seen[$id]=true;
  hmha_require(is_int($r['local_hotel_id'])&&$r['local_hotel_id']>0&&!isset($targets[$r['local_hotel_id']]),'plan_target');$targets[$r['local_hotel_id']]=true;
  foreach(['evidence_sha256','evidence_bytes_sha256']as$k)hmha_require(preg_match('/^[a-f0-9]{64}$/D',$r[$k])===1,'plan_source_digest');
  hmha_require(isset($p['countries'][(string)$r['country_id']])&&is_int($r['state_key'])&&$r['state_key']>0&&count($r['geo_anchors'])>0&&$r['holds']===[],'plan_guards');
 }
 foreach(['local_hotels_sha256','aliases_sha256']as$k)hmha_require(preg_match('/^[a-f0-9]{64}$/D',$p[$k])===1,'plan_catalog_hash');
}
function hmha_main():void{
 hmha_require(PHP_SAPI==='cli'&&getenv('MATCH_OPERATION_ID')===HMHA_OP,'operation_guard');$sha=(string)getenv('MATCH_SOURCE_SHA');hmha_require(preg_match('/^[a-f0-9]{40}$/D',$sha)===1,'source_guard');
 $rawPlan=base64_decode(HMHA_INPUT,true);hmha_require(is_string($rawPlan),'plan_encoding');$plan=json_decode($rawPlan,true,64,JSON_THROW_ON_ERROR);hmha_plan($plan);$planSha=hash('sha256',$rawPlan);
 $dir=(string)getenv('HOME').'/.anytoour-match/operations/'.HMHA_OP;$rp=$dir.'/reservation.json';hmha_require(is_file($rp)&&!is_link($rp)&&realpath($rp)===$rp&&filesize($rp)<16384,'reservation_path');
 $res=json_decode((string)file_get_contents($rp),true,64,JSON_THROW_ON_ERROR);hmha_require(($res['operation_id']??'')===HMHA_OP&&($res['source_sha']??'')===$sha&&($res['plan_sha256']??'')===$planSha&&($res['state']??'')==='reserved_before_db_access','reservation_binding');
 $db=null;$phase='configuration';$commitAttempted=false;$committed=false;$valid=[];$holds=[];
 $out=['operation_id'=>HMHA_OP,'source_sha'=>$sha,'state'=>'failed_no_replay','plan_sha256'=>$planSha,'no_replay'=>true,'planned_count'=>5,'supplier_calls'=>0,'tourvisor_calls'=>0,'booking_calls'=>0,'lead_calls'=>0,'mapping_writes'=>0];
 ob_start();try{
  $root=realpath(getcwd());hmha_require(is_string($root)&&basename($root)==='anytoour.ru','project_guard');$bp=$root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');hmha_require(realpath($bp)===$bp&&!is_link($bp),'bootstrap_path');require_once$bp;
  $db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$phase='current_transaction';
  $db->exec('SET SESSION innodb_lock_wait_timeout=20');$db->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');$db->beginTransaction();
  $eng=hmha_query($db,"SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('andromeda_hotel_identities','catalog_hotels','hotel_aliases','catalog_countries')");hmha_require(count($eng)===4,'table_contract');foreach($eng as$t){$t=array_change_key_case($t,CASE_LOWER);hmha_require(strtoupper($t['engine'])==='INNODB','nontransactional_table');}
  $extra=hmha_query($db,"SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND (LOWER(TABLE_NAME) LIKE '%hotel%' OR LOWER(TABLE_NAME) LIKE '%identity%') AND LOWER(TABLE_NAME) REGEXP 'manual|exclu|decision|review' AND LOWER(TABLE_NAME) NOT LIKE 'anex_%'");hmha_require(!$extra,'separate_protection_contract_requires_review');
  $countries=[];foreach(hmha_query($db,'SELECT id,name FROM catalog_countries WHERE is_active=1 ORDER BY id')as$c)$countries[(string)$c['id']]=$c['name'];foreach($plan['countries']as$cid=>$name)hmha_require(($countries[(string)$cid]??null)===$name,'country_changed');
  $cids=array_map('intval',array_keys($plan['countries']));$marks=implode(',',array_fill(0,count($cids),'?'));$hotels=[];
  foreach(hmha_query($db,"SELECT id,country_id,country_name,region_id,region_name,subregion_id,subregion_name,name,category,latitude,longitude FROM catalog_hotels WHERE is_active=1 AND country_id IN ($marks) ORDER BY id",$cids)as$h){foreach(['id','country_id','region_id','subregion_id']as$k)$h[$k]=$h[$k]===null?null:(int)$h[$k];$hotels[(string)$h['id']]=$h;}
  $aliases=[];foreach(hmha_query($db,"SELECT a.hotel_id,a.alias FROM hotel_aliases a JOIN catalog_hotels h ON h.id=a.hotel_id WHERE h.is_active=1 AND h.country_id IN ($marks) ORDER BY a.hotel_id,a.id",$cids)as$r)$aliases[(string)$r['hotel_id']][]=$r['alias'];
  hmha_require(hash_equals($plan['local_hotels_sha256'],hash('sha256',hmha_json($hotels)))&&hash_equals($plan['aliases_sha256'],hash('sha256',hmha_json($aliases))),'current_catalog_or_alias_changed');
  $ids=array_column($plan['rows'],'external_hotel_id');$lids=array_column($plan['rows'],'local_hotel_id');$qmarks=implode(',',array_fill(0,count($ids),'?'));$rows=[];$occupancy=[];
  foreach(hmha_query($db,"SELECT external_hotel_id,local_hotel_id,decision_status,evidence_sha256,evidence_json FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND (external_hotel_id IN ($qmarks) OR local_hotel_id IN ($qmarks)) ORDER BY external_hotel_id FOR UPDATE",array_merge($ids,$lids))as$r){$rows[(string)$r['external_hotel_id']]=$r;if($r['local_hotel_id']!==null)$occupancy[(int)$r['local_hotel_id']][]=(string)$r['external_hotel_id'];}
  $accepted=hmha_query($db,"SELECT external_hotel_id,local_hotel_id,evidence_json FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IS NOT NULL ORDER BY external_hotel_id");
  foreach($plan['rows']as$p){$id=$p['external_hotel_id'];$lid=$p['local_hotel_id'];$r=$rows[$id]??null;$why=hmha_source_holds($r,$p,$occupancy[$lid]??[]);$h=$hotels[(string)$lid]??null;
   if(!$h||$h['country_id']!==$p['country_id'])$why[]='target_country_or_active';
   $e=$r?json_decode($r['evidence_json'],true,64,JSON_THROW_ON_ERROR):[];$s=hmha_source($e);$geoProof=[];
   foreach($p['geo_anchors']as$a){$scope=$a['scope']??'';if(!in_array($scope,['region','subregion'],true)||!isset($h[$scope.'_id'])||$h[$scope.'_id']!==$a['scope_id']||($a['country_id']??$p['country_id'])!==$p['country_id']){$why[]='target_geography';continue;}
    if(isset($a['field'])){$field=$a['field'];$value=(string)$a['value'];if(!in_array($field,['townKey','town_key','town','townId','town_id','townToKey','townToId','regionKey','regionId','parentKey','parentId'],true)||(string)($s[$field]??'')!==$value){$why[]='source_geography_key';continue;}
     $n=0;$conflict=false;foreach($accepted as$ar){$ae=json_decode($ar['evidence_json'],true,64,JSON_THROW_ON_ERROR);$as=hmha_source($ae);if(hmha_states($ae)!==[(string)$p['state_key']]||(string)($as[$field]??'')!==$value)continue;$ah=$hotels[(string)$ar['local_hotel_id']]??null;$n++;if(!$ah||$ah['country_id']!==$p['country_id']||$ah[$scope.'_id']!==$a['scope_id'])$conflict=true;}
     if($n<3||$conflict)$why[]='current_learned_geography_not_unanimous';$geoProof[]=['field'=>$field,'value'=>$value,'current_accepted_anchors'=>$n,'conflict'=>$conflict];
    }
   }
   $pts=[];hmha_points($e,$pts);foreach($p['retained_points']as$pt)$pts[]=$pt;$tp=[];if($h)hmha_points($h,$tp);$dist=[];
   if($pts&&!$tp)$why[]='target_coordinates_missing';elseif($tp)foreach($pts as$pt){$km=hmha_distance($pt,$tp[0]);$dist[]=$km;if($km>5)$why[]='coordinate_conflict_gt5km';}
   if($why){$holds[]=['external_hotel_id'=>$id,'local_hotel_id'=>$lid,'reasons'=>array_values(array_unique($why))];continue;}
   $e['hierarchy_compound_acceptance']=['operation_id'=>HMHA_OP,'source_sha'=>$sha,'plan_sha256'=>$planSha,'preflight_result_sha256'=>$plan['preflight_result_sha256'],'checked_matcher_source_sha'=>'361161ce8a25abb322ff2a777bcca4bbd10a188c','prior_evidence_sha256'=>$p['evidence_sha256'],'prior_evidence_bytes_sha256'=>$p['evidence_bytes_sha256'],'local_hotel_id'=>$lid,'name_proof'=>$p['name_proof'],'geo_anchors'=>$p['geo_anchors'],'current_geo_proof'=>$geoProof,'coordinate_distances_km'=>$dist,'catalog_and_alias_revalidated'=>true];
   $json=hmha_json($e);$valid[]=['external_hotel_id'=>$id,'local_hotel_id'=>$lid,'prior_evidence_sha256'=>$p['evidence_sha256'],'new_evidence_sha256'=>hash('sha256',$json),'evidence_json'=>$json];
  }
  $public=array_map(static function($r){unset($r['evidence_json']);return$r;},$valid);
  hmha_write($dir.'/precommit.json',['operation_id'=>HMHA_OP,'source_sha'=>$sha,'plan_sha256'=>$planSha,'state'=>'validated_before_commit','rows'=>$public,'holds'=>$holds,'no_replay'=>true]);$phase='conditional_write';
  $up=$db->prepare("UPDATE andromeda_hotel_identities SET local_hotel_id=?,decision_status='accepted',evidence_sha256=?,evidence_json=? WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=? AND decision_status='pending' AND local_hotel_id IS NULL AND evidence_sha256=?");
  foreach($valid as$w){$up->execute([$w['local_hotel_id'],$w['new_evidence_sha256'],$w['evidence_json'],$w['external_hotel_id'],$w['prior_evidence_sha256']]);hmha_require($up->rowCount()===1,'conditional_write_count');}
  $phase='commit';$commitAttempted=true;$db->commit();$committed=true;$phase='post_commit_readback';$readback=[];
  foreach($valid as$w){$rr=hmha_query($db,"SELECT external_hotel_id,local_hotel_id,decision_status,evidence_sha256,evidence_json FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=?",[$w['external_hotel_id']]);hmha_require(count($rr)===1,'readback_count');$r=$rr[0];$e=json_decode($r['evidence_json'],true,64,JSON_THROW_ON_ERROR);
   hmha_require((int)$r['local_hotel_id']===$w['local_hotel_id']&&$r['decision_status']==='accepted'&&$r['evidence_sha256']===$w['new_evidence_sha256']&&hash('sha256',$r['evidence_json'])===$w['new_evidence_sha256']&&($e['hierarchy_compound_acceptance']['operation_id']??'')===HMHA_OP,'post_commit_mismatch');
   $readback[]=['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>$w['external_hotel_id'],'local_hotel_id'=>$w['local_hotel_id'],'decision_status'=>'accepted','evidence_sha256'=>$r['evidence_sha256'],'operation_id'=>HMHA_OP];
  }
  $out['state']=$valid?'completed_committed':'completed_no_delta';$out['written']=count($readback);$out['mapping_writes']=count($readback);$out['post_commit_readback']=$readback;$out['holds']=$holds;$out['completed_at_utc']=gmdate('c');
 }catch(Throwable$e){if($db instanceof PDO&&$db->inTransaction())$db->rollBack();$out['state']=$commitAttempted?'unknown_after_commit':'rolled_back';$out['error_phase']=$phase;$out['error_code']=preg_match('/^[a-z_]{3,80}$/D',$e->getMessage())?$e->getMessage():'sanitized_failure';$out['mapping_writes']=$commitAttempted?null:0;$out['holds']=$holds;}
 while(ob_get_level())ob_end_clean();$digest=hmha_write($dir.'/result.json',$out);
 $ok=in_array($out['state'],['completed_committed','completed_no_delta'],true);
 hmha_write($dir.'/receipt.json',['operation_id'=>HMHA_OP,'source_sha'=>$sha,'state'=>$out['state'],'result_sha256'=>$digest,'readback_verified'=>$ok,'result_bytes_verified'=>true,'written'=>$out['written']??null,'no_replay'=>true]);
 echo hmha_json(['state'=>$out['state'],'written'=>$out['written']??null,'result_sha256'=>$digest]);if(!$ok)exit(2);
}
if(in_array('--self-test',$argv??[],true)){
 $raw=hmha_json(['source'=>['name'=>'Yaman Life','stateKey'=>5]]);$r=['decision_status'=>'pending','local_hotel_id'=>null,'evidence_json'=>$raw,'evidence_sha256'=>str_repeat('a',64)];$p=['external_hotel_id'=>'1','state_key'=>5,'evidence_sha256'=>str_repeat('a',64),'evidence_bytes_sha256'=>hash('sha256',$raw)];$n=0;
 hmha_require(hmha_source_holds($r,$p,[])===[],'source_ok');$n++;
 foreach(['missing','accepted','manual','changed','raw_changed','state','occupied']as$case){$x=$r;$q=$p;$occ=[];if($case==='missing')$x=null;if($case==='accepted')$x['decision_status']='accepted';if($case==='manual')$x['evidence_json']=hmha_json(['manual'=>true]);if($case==='changed')$x['evidence_sha256']=str_repeat('b',64);if($case==='raw_changed')$x['evidence_json'].=' ';if($case==='state')$q['state_key']=3;if($case==='occupied')$occ=['2'];hmha_require(count(hmha_source_holds($x,$q,$occ))>0,'source_negative');$n++;}
 hmha_require(hmha_distance([36,31],[36,31])<0.00001&&hmha_distance([36,31],[37,31])>100,'distance');$n++;
 $pts=[];hmha_points(['source'=>['lat'=>36,'lon'=>31],'nested'=>[['latitude'=>37,'longitude'=>31]]],$pts);hmha_require(count($pts)===2,'recursive_points');$n++;
 $tmp=tempnam(sys_get_temp_dir(),'hmha-');unlink($tmp);hmha_write($tmp,['ok'=>true]);$refused=false;try{hmha_write($tmp,['ok'=>false]);}catch(Throwable$e){$refused=true;}unlink($tmp);hmha_require($refused,'durable_no_replay');$n++;
 echo "$n guarded apply self-tests PASS\n";exit;
}
hmha_main();
