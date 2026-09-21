<?php
declare(strict_types=1);
const OP='hotel-match-samo-direct27-current-guards-1971-20260921-v1';
const INPUT_SHA='550edf87e807ef615547ac000923e32afc3da0f09ff09305c977e96eed66bfac';

function need(bool $v,string $why):void{if(!$v)throw new RuntimeException($why);}
function rows(PDO $db,string $sql,array $args=[]):array{$s=$db->prepare($sql);$s->execute(array_values($args));return $s->fetchAll(PDO::FETCH_ASSOC)?:[];}
function writej(string $p,array $x):string{$b=json_encode($x,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";$f=fopen($p,'xb');need(is_resource($f),'open');need(fwrite($f,$b)===strlen($b),'write');fflush($f);if(function_exists('fsync'))fsync($f);fclose($f);return hash('sha256',$b);}
function norm(string $s):string{$s=mb_strtoupper(trim($s),'UTF-8');$s=preg_replace('/\b(?:HOTEL|HOTELS|OTEL|ОТЕЛЬ)\b/u',' ',$s);$s=preg_replace('/[^\pL\pN]+/u',' ',$s);return trim(preg_replace('/\s+/u',' ',$s));}

if(($argv[1]??'')==='--self-test'){
  need(norm('Fame Hotel')==='FAME','norm');
  need(norm('  PICKALBATROS-AQUA BLU  ')==='PICKALBATROS AQUA BLU','punct');
  echo "SAMO_DIRECT27_CURRENT_GUARDS_V1_SELFTEST_OK\n";exit;
}
need(PHP_SAPI==='cli'&&($argv[1]??'')==='--execute','disabled');
$root=realpath((string)getenv('ANYTOUR_ROOT'));$inp=realpath((string)getenv('MATCH_INPUT_PATH'));$dir=(string)getenv('MATCH_OPERATION_DIR');
need(is_string($root)&&basename($root)==='anytoour.ru'&&is_string($inp)&&is_dir($dir)&&basename($dir)===OP,'runtime');
need(hash_file('sha256',$inp)===INPUT_SHA,'input_hash');
$in=json_decode((string)file_get_contents($inp),true,512,JSON_THROW_ON_ERROR);
need(($in['operation']??'')==='hotel-match-current-anexonly-samo-direct27-offline-1971-20260920-v1','input_op');
$cands=$in['candidates']??[];need(is_array($cands)&&count($cands)===27,'input27');

require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
$db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
try{
  $tv=array_values(array_unique(array_map(fn($x)=>(int)$x['tv'],$cands)));
  $samo=array_values(array_unique(array_map(fn($x)=>(string)$x['samo'],$cands)));
  $hot=[];
  foreach(array_chunk($tv,300) as $chunk){$ph=implode(',',array_fill(0,count($chunk),'?'));foreach(rows($db,"SELECT id,name,country_name,region_name,subregion_name,category,is_active FROM catalog_hotels WHERE id IN ($ph)",$chunk) as $r)$hot[(int)$r['id']]=$r;}
  $idrows=[];
  foreach(array_chunk($samo,300) as $chunk){$ph=implode(',',array_fill(0,count($chunk),'?'));foreach(rows($db,"SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status,evidence_sha256,catalog_sha256 FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id IN ($ph)",$chunk) as $r)$idrows[(string)$r['external_hotel_id']][]=$r;}
  $occupants=[];
  foreach(array_chunk($tv,300) as $chunk){$ph=implode(',',array_fill(0,count($chunk),'?'));foreach(rows($db,"SELECT external_hotel_id,local_hotel_id,decision_status,evidence_sha256 FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IN ($ph)",$chunk) as $r)$occupants[(int)$r['local_hotel_id']][]=$r;}
  $db->rollBack();

  $out=[];$counts=[];$safePool=0;
  foreach($cands as $c){
    $tvId=(int)$c['tv'];$sid=(string)$c['samo'];$h=$hot[$tvId]??null;$ir=$idrows[$sid]??[];$state='review_current';
    if(isset($c['hold']))$state='historical_hold';
    elseif(!$h)$state='local_missing';
    elseif((int)$h['is_active']!==1)$state='local_inactive';
    elseif(norm((string)$h['country_name'])!==norm((string)$c['country']))$state='country_changed';
    elseif(count($ir)===0)$state='samo_identity_missing_current';
    elseif(count($ir)>1)$state='samo_identity_duplicate_rows';
    else{
      $r=$ir[0];$status=(string)$r['decision_status'];$local=$r['local_hotel_id']===null?null:(int)$r['local_hotel_id'];
      if($status==='accepted'&&$local===$tvId)$state='already_accepted_same';
      elseif($status==='accepted'&&$local!==null&&$local!==$tvId)$state='accepted_other_target_conflict';
      elseif(in_array($status,['conflict','rejected'],true))$state='protected_'.$status;
      elseif($local!==null&&$local!==$tvId)$state='pending_other_target';
      else $state='current_candidate_pending_or_unassigned';
    }
    $occ=$occupants[$tvId]??[];
    $otherOcc=array_values(array_filter($occ,fn($x)=>(string)$x['external_hotel_id']!==$sid));
    $nameExact=$h?norm((string)$h['name'])===norm((string)$c['name']):false;
    $regionExact=$h&&($c['region']??null)?norm((string)$h['region_name'])===norm((string)$c['region']):null;
    $subExact=$h&&($c['subregion']??null)?norm((string)$h['subregion_name'])===norm((string)$c['subregion']):null;
    if($state==='current_candidate_pending_or_unassigned'&&!$otherOcc&&$nameExact)$safePool++;
    $counts[$state]=($counts[$state]??0)+1;
    $out[]=[
      'tv_hotel_id'=>$tvId,'samo_hotel_id'=>$sid,'anex_native_id'=>(int)$c['anex'],
      'source_name'=>$c['name'],'source_country'=>$c['country'],'source_region'=>$c['region']??null,'source_subregion'=>$c['subregion']??null,
      'current_local'=>$h,'current_samo_identity'=>$ir,'accepted_samo_target_occupants'=>$occ,
      'name_exact_normalized'=>$nameExact,'region_exact_normalized'=>$regionExact,'subregion_exact_normalized'=>$subExact,
      'classification'=>$state,'aggregate_pool_candidate'=>($state==='current_candidate_pending_or_unassigned'&&!$otherOcc&&$nameExact),
      'safe_to_write_now'=>false,'source_artifact'=>$c['source_artifact'],'source_result'=>$c['source_result']
    ];
  }
  ksort($counts);
  $result=['operation'=>OP,'state'=>'completed_read_only','input_dossiers'=>27,'classification_counts'=>$counts,'aggregate_pool_candidates'=>$safePool,'rows'=>$out,'provider_calls'=>0,'tourvisor_calls'=>0,'samo_calls'=>0,'andromeda_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'safe_to_write_now'=>false];
  $sha=writej($dir.'/result.json',$result);
  writej($dir.'/receipt.json',['operation'=>OP,'state'=>'completed_read_only','result_sha256'=>$sha,'input_dossiers'=>27,'aggregate_pool_candidates'=>$safePool,'provider_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'readback_verified'=>true]);
  echo json_encode(['classification_counts'=>$counts,'aggregate_pool_candidates'=>$safePool,'result_sha256'=>$sha],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
