<?php
declare(strict_types=1);
/** MATCH #1971: infer the unanchored Andromeda stateKey=116 from global core8 exact identity evidence. READ ONLY. */
const OP='hotel-match-andromeda-state116-crosswalk-current-1971-20260916-v1';
const STATE=116;
const CORE=[1,2,4,6,8,9,10,16];
const MIN_CROSSWALK_TARGETS=10;
function jj($v):string{return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function putx(string $p,$v):string{$raw=jj($v)."\n";$f=@fopen($p,'x');if(!$f)throw new RuntimeException('durable_create_failed');try{if(fwrite($f,$raw)!==strlen($raw))throw new RuntimeException('durable_write_failed');fflush($f);}finally{fclose($f);}if(file_get_contents($p)!==$raw)throw new RuntimeException('durable_readback_failed');return hash('sha256',$raw);}
function dbp(string $r):string{foreach([$r.'/data/db-v1.php',$r.'/v2/data/db-v1.php']as$p)if(is_file($p))return$p;throw new RuntimeException('db_bootstrap_missing');}
function qq(PDO$d,string$q,array$p=[]):array{$s=$d->prepare($q);$s->execute($p);return$s->fetchAll(PDO::FETCH_ASSOC)?:[];}
function ev($raw):array{if(!is_string($raw)||trim($raw)==='')return[];try{$x=json_decode($raw,true,512,JSON_THROW_ON_ERROR);return is_array($x)?$x:[];}catch(Throwable){return[];}}
function box(array$e):array{return is_array($e['source']??null)?$e['source']:$e;}
function sv($v):?string{if(!is_scalar($v))return null;$s=trim((string)$v);return$s===''?null:$s;}
function fi(array$a,array$ks):?string{foreach($ks as$k)if(array_key_exists($k,$a)&&($v=sv($a[$k]))!==null)return$v;return null;}
function iid($v):?int{$s=sv($v);return$s!==null&&preg_match('/^[1-9][0-9]{0,14}$/D',$s)?(int)$s:null;}
function num($v):?float{if(!is_scalar($v)||!is_numeric((string)$v))return null;$x=(float)$v;return is_finite($x)?$x:null;}
function nm(string$s):string{$s=mb_strtolower($s,'UTF-8');$s=preg_replace('/\b(?:hotel|resort|spa)\b/iu',' ',$s)??$s;$s=preg_replace('/[^\pL\pN]+/u',' ',$s)??$s;return trim(preg_replace('/\s+/u',' ',$s)??$s);}
function forms(?string$s):array{if($s===null||trim($s)==='')return[];$a=[];$former=[];if(preg_match_all('/\((?:\s*(?:ex\.?|former(?:ly)?)\s*[:.-]?\s*)([^)]{2,180})\)/iu',$s,$m))foreach($m[1]as$x)$former[]=(string)$x;$cur=preg_replace('/\((?:\s*(?:ex\.?|former(?:ly)?)\s*[:.-]?\s*)[^)]{2,180}\)/iu',' ',$s)??$s;foreach(array_merge([$cur],$former)as$x){$n=nm((string)$x);if($n!=='')$a[$n]=1;}return array_keys($a);}
function sigtokens(string$f):array{$out=[];foreach(preg_split('/\s+/u',$f,-1,PREG_SPLIT_NO_EMPTY)?:[]as$t){$t=(string)$t;if(preg_match('/^\d+$/D',$t)||mb_strlen($t,'UTF-8')>=2)$out[$t]=1;}return array_keys($out);}
function km($a,$b,$c,$d):?float{foreach([$a,$b,$c,$d]as$x)if($x===null)return null;$r=6371.0088;$p1=deg2rad((float)$a);$p2=deg2rad((float)$c);$dp=deg2rad((float)$c-(float)$a);$dl=deg2rad((float)$d-(float)$b);$h=sin($dp/2)**2+cos($p1)*cos($p2)*sin($dl/2)**2;return 2*$r*asin(min(1,sqrt($h)));}
function protected(array$x):bool{foreach($x as$k=>$v){$kl=mb_strtolower((string)$k,'UTF-8');if(preg_match('/manual|reject|conflict|exclusion/u',$kl)){if(is_array($v)){if($v!==[])return true;}elseif($v!==null&&$v!==false&&$v!==''&&$v!==0&&$v!=='0')return true;}if(is_array($v)&&protected($v))return true;}return false;}
if(in_array('--self-test',$argv??[],true)){
 if(forms('Swarn By Hawks Hotels (EX. Clarks Exotica Kamadhoo Maldives)')!==['swarn by hawks hotels','clarks exotica kamadhoo maldives'])throw new RuntimeException('forms');
 if(nm('Dickwella Resort & SPA')!=='dickwella')throw new RuntimeException('norm');
 if(count(sigtokens('riu sri lanka'))!==3)throw new RuntimeException('tokens');
 if(protected(['x'=>['manual_decision'=>false]])||!protected(['pair_conflict'=>'yes']))throw new RuntimeException('protected');
 echo "PASS\n";exit;
}
$op=(string)getenv('OPERATION_ID');$sha=(string)getenv('MATCH_SOURCE_SHA');if($op!==OP||!preg_match('/^[0-9a-f]{40}$/D',$sha))throw new RuntimeException('operation_guard');
$root=(string)realpath(getcwd());$base=rtrim((string)getenv('HOME'),'/').'/.anytoour-match/operations';if(!$root||basename($root)!=='anytoour.ru'||!is_dir($base))throw new RuntimeException('root_guard');$dir=$base.'/'.$op;if(!mkdir($dir,0700))throw new RuntimeException('operation_exists');
putx($dir.'/reservation.json',['operation_id'=>$op,'source_sha'=>$sha,'state'=>'reserved_before_db_access','read_only'=>true,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'no_replay'=>true]);
$db=null;try{
 require_once dbp($root);$db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
 $cat=qq($db,'SELECT id,country_id,name,latitude,longitude FROM catalog_hotels WHERE is_active=1 AND country_id IN (1,2,4,6,8,9,10,16) ORDER BY country_id,id');
 $aliases=qq($db,'SELECT a.hotel_id,a.alias FROM hotel_aliases a JOIN catalog_hotels h ON h.id=a.hotel_id WHERE h.is_active=1 AND h.country_id IN (1,2,4,6,8,9,10,16)');
 $acc=qq($db,"SELECT external_hotel_id,local_hotel_id FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IS NOT NULL");
 $pend=qq($db,"SELECT external_hotel_id,evidence_json,evidence_sha256,catalog_sha256 FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='pending' AND local_hotel_id IS NULL ORDER BY external_hotel_id");
 $db->exec('ROLLBACK');
 $byId=[];$idx=[];foreach($cat as$h){$id=(int)$h['id'];$byId[$id]=$h;foreach(forms((string)$h['name'])as$f)$idx[$f][$id]=1;}
 foreach($aliases as$a){$id=(int)$a['hotel_id'];if(!isset($byId[$id]))continue;foreach(forms((string)$a['alias'])as$f)$idx[$f][$id]=1;}
 $occupied=[];foreach($acc as$a)$occupied[(int)$a['local_hotel_id']][(string)$a['external_hotel_id']]=1;
 $scope=[];foreach($pend as$p){$e=ev($p['evidence_json']??null);$s=box($e);$state=iid(fi($s,['stateKey','state_id','countryKey']));if($state===STATE)$scope[]=$p;}
 $proof=[];$proofTargetCountries=[];$proofTargets=[];$raw=[];
 foreach($scope as$p){$e=ev($p['evidence_json']??null);$s=box($e);$name=fi($s,['name','hotelName','title']);$fs=forms($name);$cand=[];$matchForms=[];foreach($fs as$f){$ids=array_keys($idx[$f]??[]);if($ids){$matchForms[]=$f;foreach($ids as$id)$cand[(int)$id]=1;}}$ids=array_keys($cand);sort($ids,SORT_NUMERIC);$pr=null;if(count($ids)===1){$id=(int)$ids[0];$strong=false;foreach($matchForms as$f)if(count(sigtokens($f))>=2){$strong=true;break;}if($strong){$cid=(int)$byId[$id]['country_id'];$proofTargets[$id]=1;$proofTargetCountries[$cid][$id]=1;$pr=['external_hotel_id'=>(string)$p['external_hotel_id'],'source_name'=>$name,'target_local_id'=>$id,'target_country_id'=>$cid,'matched_forms'=>$matchForms];$proof[]=$pr;}}$raw[(string)$p['external_hotel_id']]=['row'=>$p,'evidence'=>$e,'source'=>$s,'source_name'=>$name,'source_forms'=>$fs,'global_exact_candidate_ids'=>$ids,'proof'=>$pr];}
 $countryTargetCounts=[];foreach($proofTargetCountries as$cid=>$ids)$countryTargetCounts[(string)$cid]=count($ids);arsort($countryTargetCounts,SORT_NUMERIC);$crossCountry=null;$crossUsable=false;if(count($countryTargetCounts)===1){$only=(int)array_key_first($countryTargetCounts);if(($countryTargetCounts[(string)$only]??0)>=MIN_CROSSWALK_TARGETS){$crossCountry=$only;$crossUsable=true;}}
 $preCandidates=[];if($crossUsable){foreach($raw as$id=>$x){$cand=[];$matched=[];foreach($x['source_forms'] as$f){foreach(array_keys($idx[$f]??[])as$lid){$lid=(int)$lid;if((int)$byId[$lid]['country_id']!==$crossCountry)continue;$cand[$lid]=1;$matched[$f]=1;}}$ids=array_keys($cand);sort($ids,SORT_NUMERIC);if(count($ids)===1)$preCandidates[$id]=['local_id'=>(int)$ids[0],'matched_forms'=>array_keys($matched)];}}
 $targetPending=[];foreach($preCandidates as$id=>$c)$targetPending[$c['local_id']][]=$id;
 $rows=[];$prepared=[];$counts=[];foreach($raw as$id=>$x){$p=$x['row'];$s=$x['source'];$row=['external_hotel_id'=>$id,'source_name'=>$x['source_name'],'source_forms'=>$x['source_forms'],'global_exact_candidate_ids'=>$x['global_exact_candidate_ids'],'crosswalk_country_id'=>$crossCountry,'candidate_local_id'=>null,'candidate_local_name'=>null,'route'=>null,'distance_km'=>null,'evidence_sha256'=>$p['evidence_sha256']??null,'catalog_sha256'=>$p['catalog_sha256']??null];
  if(!$crossUsable){$row['route']='hold_crosswalk_unproven';}
  elseif(!isset($preCandidates[$id])){$row['route']='hold_countrywide_exact_not_unique';}
  else{$local=$preCandidates[$id]['local_id'];$h=$byId[$local];$row['candidate_local_id']=$local;$row['candidate_local_name']=$h['name'];$row['matched_forms']=$preCandidates[$id]['matched_forms'];$row['pending_target_competitors']=$targetPending[$local]??[];
   if(count($targetPending[$local]??[])!==1)$row['route']='hold_pending_target_collision';
   elseif(!empty($occupied[$local])){$row['accepted_same_provider_occupancy']=array_keys($occupied[$local]);$row['route']='hold_same_provider_occupied';}
   elseif(protected($x['evidence']))$row['route']='hold_protected_evidence';
   else{$slat=num(fi($s,['latitude','lat','hotelLatitude']));$slon=num(fi($s,['longitude','lon','lng','hotelLongitude']));$d=km($slat,$slon,num($h['latitude']),num($h['longitude']));$row['source_coordinates']=['latitude'=>$slat,'longitude'=>$slon];$row['local_coordinates']=['latitude'=>num($h['latitude']),'longitude'=>num($h['longitude'])];$row['distance_km']=$d===null?null:round($d,3);$strong=false;foreach($preCandidates[$id]['matched_forms']as$f)if(count(sigtokens($f))>=2){$strong=true;break;}if($d!==null&&$d>5)$row['route']='hold_coordinate_gt5km';elseif(!$strong&&!($d!==null&&$d<=1))$row['route']='hold_low_information_exact';else{$row['route']='guard_passed_prepared';$prepared[]=$row;}}
  }
  $counts[$row['route']]=($counts[$row['route']]??0)+1;$rows[]=$row;
 }
 ksort($counts);$res=['schema'=>'hotel-match-andromeda-state116-crosswalk-current/1','operation_id'=>$op,'source_sha'=>$sha,'state'=>'completed_read_only','server_current'=>true,'state_key'=>STATE,'pending_state116_examined'=>count($scope),'global_unique_exact_proof_rows'=>count($proof),'global_unique_exact_proof_distinct_targets'=>count($proofTargets),'proof_target_country_counts'=>$countryTargetCounts,'crosswalk_min_distinct_targets'=>MIN_CROSSWALK_TARGETS,'crosswalk_country_id'=>$crossCountry,'crosswalk_usable'=>$crossUsable,'proof_rows'=>$proof,'route_counts'=>$counts,'guard_passed_prepared_count'=>count($prepared),'guard_passed_prepared'=>$prepared,'rows'=>$rows,'safe_to_write_now'=>false,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'booking_calls'=>0,'lead_calls'=>0,'no_replay'=>true];
 $dig=putx($dir.'/result.json',$res);putx($dir.'/receipt.json',['operation_id'=>$op,'source_sha'=>$sha,'state'=>'completed_read_only','result_sha256'=>$dig,'readback_verified'=>hash('sha256',(string)file_get_contents($dir.'/result.json'))===$dig,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'no_replay'=>true]);echo jj(['state'=>'completed_read_only','pending_state116_examined'=>count($scope),'global_unique_exact_proof_distinct_targets'=>count($proofTargets),'proof_target_country_counts'=>$countryTargetCounts,'crosswalk_country_id'=>$crossCountry,'crosswalk_usable'=>$crossUsable,'route_counts'=>$counts,'guard_passed_prepared_count'=>count($prepared)])."\n";
}catch(Throwable$e){try{if($db instanceof PDO&&$db->inTransaction())$db->rollBack();}catch(Throwable){}$f=['operation_id'=>$op,'source_sha'=>$sha,'state'=>'failed_read_only','error_class'=>get_class($e),'error_message'=>$e->getMessage(),'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'no_replay'=>true];$dig=putx($dir.'/failure.json',$f);putx($dir.'/receipt.json',['operation_id'=>$op,'source_sha'=>$sha,'state'=>'failed_read_only','failure_sha256'=>$dig,'readback_verified'=>true,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'no_replay'=>true]);fwrite(STDERR,"STATE116_FAILED ".$e->getMessage()."\n");exit(2);}
