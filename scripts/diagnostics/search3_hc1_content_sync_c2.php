<?php
/** Exact audited HC-1 orchestration of the existing writer. No supplier or new SQL writer. */
declare(strict_types=1);
require_once dirname(__DIR__,2).'/v2/data/anytour-profile-enrichment-v1.php';
require_once dirname(__DIR__,2).'/v2/data/db-v1.php';
const OP='search3-hc1-content-sync-20260925-c2';
const SOURCE='00a553e5a4c2ef34d06bf5303e4ec8e4af49dbe8';
const A1='search3-hc1-full-content-parity-20260925-a1';
const A2='search3-hc1-content-diff-refinement-20260925-a2';
const A1_SHA='d9ecd0f2c56f43ca33191383b71d7e722a28b56077e766fc559c4cbe498f621d';
const A2_SHA='54b852cffb2e89c775a0dd6e1fc561f57b00046cc8d6af90dfc251070a1beef2';
const ROWS_SHA='fd29480824d8ab334939ded06292a0459fdbc1fbe81207b11ce9f362d04e9285';
const IDS_SHA='5da05f442024ced16c15bb698b9fcdb9dcb58de36cdfe3d58e7859be42889f14';
const THROUGH='2026-09-25T15:10:38Z';
function need(bool $ok,string $code): void { if(!$ok)throw new RuntimeException('HC1_C2_'.$code); }
function js(array $x): string { return AnyTourProfileEnrichmentV1::json($x); }
function dig(array $x): string { return hash('sha256',js($x)); }
function load(string $p): array { $x=json_decode((string)file_get_contents($p),true,512,JSON_THROW_ON_ERROR);need(is_array($x),'JSON');return $x; }
function seal(string $p,array $x): void {
 $s=js($x)."\n";$h=fopen($p,'xb');need(is_resource($h),'CREATE_ONLY');
 need(fwrite($h,$s)===strlen($s),'FILE_WRITE');need(fflush($h),'FLUSH');if(function_exists('fsync'))need(fsync($h),'FSYNC');fclose($h);
}
function at(array $p,string $f): mixed {foreach(explode('.',$f) as $k){if(!is_array($p)||!array_key_exists($k,$p))return null;$p=$p[$k];}return $p;}
function nextProfile(array $p,array $patch): array {foreach($patch as $f=>$v){if(str_starts_with($f,'hotelInformation.'))$p['hotelInformation'][substr($f,17)]=$v;else$p[$f]=$v;}return $p;}
function audit(string $dir,string $op,string $sha): array {
 need(is_file($dir.'/result.json')&&hash_file('sha256',$dir.'/result.json')===$sha,'AUDIT_HASH');
 $r=load($dir.'/result.json');$q=load($dir.'/receipt.json');
 need(($r['operation']??null)===$op&&($r['state']??null)==='completed_read_only','AUDIT_RESULT');
 need(($q['resultSha256']??null)===$sha&&($q['state']??null)==='completed_read_only','AUDIT_RECEIPT');return $r;
}
function inputs(string $d1,string $d2): array {
 $a=audit($d1,A1,A1_SHA);$b=audit($d2,A2,A2_SHA);
 need(($a['completeActiveProfileScan']??false)&&$a['scannedProfiles']===15999,'AUDIT_COMPLETE');
 need(($a['hotelRowsSha256']??null)===ROWS_SHA&&hash_file('sha256',$d1.'/hotels.jsonl')===ROWS_SHA,'ROWS_HASH');
 need($b['inputA1Sha256']===A1_SHA&&$b['driftedProfiles']===[],'REFINEMENT');$byId=[];
 foreach($b['rows'] as $r)$byId[(int)$r['id']]=$r;
 $targets=[];$unavailable=[];$h=fopen($d1.'/hotels.jsonl','rb');need(is_resource($h),'ROWS_OPEN');$n=0;
 while(($line=fgets($h))!==false){++$n;$r=json_decode($line,true,512,JSON_THROW_ON_ERROR);need(count($r['pairs']??[])===1,'ONE_PAIR');$pair=$r['pairs'][0];$id=(int)$r['anytourHotelId'];$ref=$byId[$id]??[];$fields=[];
  foreach(AnyTourProfileEnrichmentV1::SYNC_FIELDS as $f){
   if(!in_array($pair['fields'][$f]['state']??null,['local_missing','different','shape_diff'],true))continue;
   if($f==='images'&&($ref['gallery']??null)==='only_main400_preview_url_presence')continue;
   if($f==='primaryImage'&&($ref['primaryImage']??null)==='one_side_main400_preview')continue;
   $fields[$f]=true;
  }
  if(($ref['omittedBy100Cap']??0)>0||($ref['rawReferencesMissingInReader']??0)>0)$fields['images']=true;
  if(!$fields)continue;$names=array_keys($fields);sort($names,SORT_STRING);
  need(($pair['localAliasValid']??false)===true,'AUDIT_ALIAS');
  $target=['anytourHotelId'=>$id,'localHotelId'=>(int)$pair['legacyHotelId'],'fields'=>$names,
   'expectedProfileSha256'=>$r['profileSha256'],'expectedRevision'=>$r['revision'],
   'expectedLegacySourceSha256'=>$pair['legacySourceSha256'],'detailsFetchedAt'=>$pair['detailsFetchedAt'],
   'rawStatus'=>$pair['rawStatus']];
  if($pair['rawStatus']==='raw_valid')$targets[$id]=$target;else$unavailable[$id]=$target;
 }
 fclose($h);ksort($targets,SORT_NUMERIC);ksort($unavailable,SORT_NUMERIC);
 need($n===15999&&count($targets)===834&&count($unavailable)===54,'EXACT_COHORT');
 need(hash('sha256',implode(',',array_keys($targets)))===IDS_SHA,'EXACT_IDS');
 need(!isset($targets[1])&&!isset($targets[1379]),'CONTROLS_EXCLUDED');
 return ['targets'=>$targets,'sourceUnavailable'=>array_values($unavailable)];
}
function readRows(PDO $db,array $ids): array {
 $q=$db->prepare('SELECT id,profile_json,profile_sha256,revision,is_active FROM anytour_hotels WHERE id IN ('.implode(',',array_fill(0,count($ids),'?')).')');$q->execute($ids);$out=[];
 foreach($q->fetchAll() as $r){need(hash('sha256',$r['profile_json'])===$r['profile_sha256'],'PROFILE_INTEGRITY');$out[(int)$r['id']]=$r;}return $out;
}
function baseline(PDO $db,array $items): array {
 $ids=array_keys($items);$current=readRows($db,$ids);$marks=implode(',',array_fill(0,count($ids),'?'));
 $q=$db->prepare("SELECT s.anytour_hotel_id,CAST(s.external_key AS CHAR) AS local_id,s.source_sha256,d.fetched_at FROM anytour_hotel_sources s LEFT JOIN catalog_hotel_details d ON d.hotel_id=CAST(s.external_key AS UNSIGNED) WHERE s.namespace='legacy_catalog' AND s.anytour_hotel_id IN ($marks)");$q->execute($ids);$links=[];
 foreach($q->fetchAll() as $r)$links[(int)$r['anytour_hotel_id']][]=$r;$valid=[];$drift=[];
 foreach($items as $id=>$t){$c=$current[$id]??null;$ls=$links[$id]??[];$s=count($ls)===1?$ls[0]:null;
  if(!$c||(int)$c['is_active']!==1||$c['profile_sha256']!==$t['expectedProfileSha256']||(int)$c['revision']!==$t['expectedRevision']
   ||!$s||(int)$s['local_id']!==$t['localHotelId']||$s['source_sha256']!==$t['expectedLegacySourceSha256']||$s['fetched_at']!==$t['detailsFetchedAt'])$drift[$id]=['profile'=>'changed_since_audit'];
  else$valid[$id]=$t;
 }return [$valid,$drift];
}
function verifyPlan(array $p,array $items): void {
 need($p['status']==='prepared_read_only'&&$p['writes']===0&&$p['supplierCalls']===0,'PLAN_MODE');$seen=[];
 foreach($p['selected'] as $row){$id=$row['anytourHotelId'];need(isset($items[$id])&&!isset($seen[$id]),'PLAN_IDS');$seen[$id]=true;$t=$items[$id];
  need($row['localHotelId']===$t['localHotelId']&&$row['expectedProfileSha256']===$t['expectedProfileSha256']&&$row['expectedRevision']===$t['expectedRevision'],'PLAN_BASELINE');
  need(hash('sha256',$row['beforeProfileJson'])===$t['expectedProfileSha256'],'BEFORE_HASH');
  need($row['patch']!==[]&&array_diff(array_keys($row['patch']),$t['fields'])===[],'PLAN_FIELDS');
 }
}
function verifyRows(PDO $db,array $plan): array {
 if(!$plan['selected'])return[];$ids=array_column($plan['selected'],'anytourHotelId');$actual=readRows($db,$ids);$rows=[];
 foreach($plan['selected'] as $i){$id=$i['anytourHotelId'];$before=json_decode($i['beforeProfileJson'],true,512,JSON_THROW_ON_ERROR);$after=nextProfile($before,$i['patch']);$want=dig($after);$r=$actual[$id]??null;
  need($r&&$r['profile_sha256']===$want&&(int)$r['revision']===$i['expectedRevision']+1&&(int)$r['is_active']===1,'POSTCOMMIT_FULL_PROFILE');
  $field=[];foreach($i['patch'] as $f=>$v)$field[$f]=['beforeSha256'=>dig([at($before,$f)]),'afterSha256'=>dig([$v])];
  $rows[]=['anytourHotelId'=>$id,'legacyHotelId'=>$i['localHotelId'],'name'=>$after['name'],
   'beforeProfileSha256'=>$i['expectedProfileSha256'],'profileSha256'=>$want,'beforeRevision'=>$i['expectedRevision'],'revision'=>(int)$r['revision'],
   'fields'=>$field,'beforePhotoCount'=>count($before['images']??[]),'photoCount'=>count($after['images']??[]),
   'descriptionPresent'=>is_string($after['description']??null)&&trim(strip_tags($after['description']))!=='',
   'sourceFetchedAt'=>$i['detailsFetchedAt']];
 }return $rows;
}
if(in_array('--self-test',$argv,true)){
 need(nextProfile(['hotelInformation'=>[]],['hotelInformation.services'=>['child'=>'x']])===['hotelInformation'=>['services'=>['child'=>'x']]],'PATCH_PATH');
 need(dig(['b'=>2,'a'=>1])===dig(['a'=>1,'b'=>2]),'STABLE_JSON');
 if(isset($argv[2],$argv[3])){$in=inputs($argv[2],$argv[3]);echo js(['targets'=>count($in['targets']),'unavailable'=>count($in['sourceUnavailable']),'idSha256'=>IDS_SHA])."\n";}
 echo "HC1_C2_SELF_TEST_OK\n";exit(0);
}
if(in_array('--http-verify',$argv,true)){
 $expected=load($argv[2]);$dir=$argv[3];$rows=$expected['updatedProfiles'];foreach($expected['controls'] as $c)$rows[]=$c;
 $actual=[];$requests=load($dir.'/requests.json');need(count($requests)<=18,'HTTP_BUDGET');
 foreach($requests as $request){need($request['status']===200,'HTTP_STATUS');$batch=load($dir.'/'.$request['file']);need(($batch['ok']??false)===true&&($batch['source']??null)==='anytour-canonical-catalog','HTTP_CONTRACT');foreach($batch['items'] as $p){$id=$p['id'];need(!isset($actual[$id]),'HTTP_DUPLICATE');$actual[$id]=$p;}}
 need(count($actual)===count($rows),'HTTP_COUNT');$verified=[];
 foreach($rows as $r){$p=$actual[$r['anytourHotelId']]??null;need(is_array($p)&&$p['revision']===$r['revision']&&$p['catalog']==='anytour','HTTP_ID_REVISION');unset($p['id'],$p['revision'],$p['catalog']);need(dig($p)===$r['profileSha256'],'HTTP_FULL_CONTENT');$verified[]=$r['anytourHotelId'];}
 echo js(['state'=>'http_verified','profiles'=>count($verified),'ids'=>$verified,'httpRequests'=>count($requests),'supplierCalls'=>0,'imageRequests'=>0,'browser'=>'not_checked'])."\n";exit(0);
}
need(in_array('--execute',$argv,true),'EXECUTE');$dir=(string)getenv('HC1_OPERATION_DIR');$root=(string)getenv('ANYTOUR_ROOT');
need(is_dir($dir)&&basename($dir)===OP&&is_file($dir.'/reservation.json'),'OP_DIR');
need(is_file($root.'/config.php'),'ROOT');$ledger=dirname($dir);$in=inputs($ledger.'/'.A1,$ledger.'/'.A2);
seal($dir.'/scope.json',$in);seal($dir.'/execution-started.json',['operation'=>OP,'source'=>SOURCE,'at'=>gmdate('c'),'noReplay'=>true]);
$_SERVER['DOCUMENT_ROOT']=$root;$db=null;$commitAttempt=false;$completed=0;
$result=['operation'=>OP,'source'=>SOURCE,'basis'=>'retained_TV_not_fresh_live_TV','startedAt'=>gmdate('c'),
 'inputTargets'=>834,'inputSourceUnavailable'=>54,'idSha256'=>IDS_SHA,'state'=>'incomplete','batches'=>[],
 'updatedProfiles'=>[],'held'=>[],'controls'=>[],'profilesUpdated'=>0,'fieldsUpdated'=>0,'fieldCounts'=>[],
 'supplierCalls'=>0,'legacyWrites'=>0,'mappingWrites'=>0,'schemaWrites'=>0,'runtimePublication'=>false,'beforeImagesPrivate'=>true];
try{
 $db=v2_data_db();$db->exec('SET SESSION innodb_lock_wait_timeout=15');$engine=new AnyTourProfileEnrichmentV1($db);
 $controlBefore=readRows($db,[1,1379]);need(count($controlBefore)===2,'CONTROL_COUNT');
 foreach($controlBefore as $id=>$r){$p=json_decode($r['profile_json'],true,512,JSON_THROW_ON_ERROR);$result['controls'][]=['anytourHotelId'=>$id,'legacyHotelId'=>$id===1?102:6319,'profileSha256'=>dig($p),'revision'=>(int)$r['revision']];}
 seal($dir.'/controls-before.json',$controlBefore);
 $pilot=[562,688,535,3001];$pilot=array_merge($pilot,range(4349,4364));$first=[];$rest=$in['targets'];
 foreach($pilot as $id){need(isset($rest[$id]),'PILOT_ID');$first[$id]=$rest[$id];unset($rest[$id]);}
 $groups=array_merge([$first],array_chunk($rest,200,true));need(count($groups)===6,'BATCH_COUNT');
 foreach($groups as $i=>$group){
  $batch=$dir.'/batch-'.($i+1);need(mkdir($batch,0700),'BATCH_DIR');[$valid,$drift]=baseline($db,$group);
  foreach($drift as $id=>$hold)$result['held'][$id]=$hold;
  if($i===0)need(count($valid)>=5,'PILOT_CURRENT_MINIMUM');
  if(!$valid){seal($batch.'/result.json',['state'=>'held','drift'=>$drift]);continue;}
  $scope=array_map(static fn($t)=>['anytourHotelId'=>$t['anytourHotelId'],'localHotelId'=>$t['localHotelId'],'fields'=>$t['fields']],array_values($valid));
  $p=$engine->plan(count($scope),THROUGH,$scope,true);$again=$engine->plan(count($scope),THROUGH,$scope,true);
  verifyPlan($p,$valid);need($p['planSha256']===$again['planSha256'],'TWO_PLANS');
  seal($batch.'/plan.json',$p);seal($batch.'/rollback-constraints.json',['beforeImages'=>'plan.json: selected.beforeProfileJson','restoreOnlyIfCurrentEqualsCommittedResult'=>true,'noAutomaticRollback'=>true]);
  foreach($p['held'] as $id=>$hold)$result['held'][$id]=$hold;
  if($i===0)need(count($p['selected'])>=5,'PILOT_MINIMUM');
  if(!$p['selected']){$out=['state'=>'no_changes_or_held','planSha256'=>$p['planSha256']];seal($batch.'/result.json',$out);$result['batches'][]=$out;continue;}
  seal($batch.'/commit-attempt.json',['operation'=>OP.'-b'.($i+1),'planSha256'=>$p['planSha256'],'at'=>gmdate('c'),'noReplay'=>true]);$commitAttempt=true;
  $applied=$engine->apply(OP.'-b'.($i+1),count($scope),THROUGH,$p['planSha256'],$scope,true);
  need($applied['status']==='committed_verified'&&$applied['profilesUpdated']===count($p['selected']),'COMMIT_RESULT');
  $checked=verifyRows($db,$p);need(readRows($db,[1,1379])===$controlBefore,'CONTROL_UNCHANGED');
  $repeat=$engine->plan(count($scope),THROUGH,$scope,true);need($repeat['selected']===[],'REPEAT_PLAN_NOT_NOOP');
  $out=['state'=>'committed_verified','batch'=>$i+1,'profilesUpdated'=>count($checked),'fieldsUpdated'=>$applied['fieldsFilled'],
   'fieldCounts'=>$applied['fieldCounts'],'planSha256'=>$p['planSha256'],'updatedProfiles'=>$checked,'repeatPlanNoop'=>true,'controlsUnchanged'=>true];
  seal($batch.'/result.json',$out);seal($batch.'/receipt.json',['state'=>$out['state'],'resultSha256'=>hash_file('sha256',$batch.'/result.json')]);
  $commitAttempt=false;++$completed;$result['profilesUpdated']+=count($checked);$result['fieldsUpdated']+=$applied['fieldsFilled'];
  foreach($applied['fieldCounts'] as $field=>$n)$result['fieldCounts'][$field]=($result['fieldCounts'][$field]??0)+$n;
  array_push($result['updatedProfiles'],...$checked);unset($out['updatedProfiles']);$result['batches'][]=$out;
 }
 need($result['profilesUpdated']>0&&$result['profilesUpdated']<=834,'UPDATED_BOUND');
 need(readRows($db,[1,1379])===$controlBefore,'FINAL_CONTROLS');
 $result['controlsUnchanged']=true;$result['state']='completed_verified';$result['profileWrites']=$result['profilesUpdated'];$result['provenanceWrites']=$result['profilesUpdated'];
}catch(Throwable $e){
 $result['state']=$commitAttempt?'commit_outcome_requires_reconciliation':'stopped_before_next_commit';
 $result['errorClass']=get_class($e);$result['errorCode']=preg_match('/^(?:HC1_C2_|ANYTOUR_PROFILE_ENRICH_|SYNC_)[A-Z0-9_]+$/D',$e->getMessage())?$e->getMessage():'OPERATION_EXCEPTION';
 $result['completedBatches']=$completed;$result['replayForbidden']=true;
}
$result['finishedAt']=gmdate('c');ksort($result['fieldCounts'],SORT_STRING);ksort($result['held'],SORT_NUMERIC);
seal($dir.'/result.json',$result);seal($dir.'/receipt.json',['operation'=>OP,'state'=>$result['state'],'resultSha256'=>hash_file('sha256',$dir.'/result.json'),'noReplay'=>true]);
echo js(['state'=>$result['state'],'profilesUpdated'=>$result['profilesUpdated'],'fieldsUpdated'=>$result['fieldsUpdated'],'supplierCalls'=>0])."\n";
exit($result['state']==='completed_verified'?0:1);
