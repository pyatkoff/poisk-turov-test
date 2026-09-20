<?php
declare(strict_types=1);

const HSU_OP='hotel-match-hotelsu1244-manualgeo-accept-1971-20260920-v1';
const HSU_AUDIT_SHA='0a2767e4e6dde89505e8397d46c46b1b03a542e7bddeeda25e4d5b7325a06895';
const HSU_OWNER_CLAIM='5752445552';

function hsu_need(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
function hsu_rows(PDO $db,string $sql,array $p=[]):array{$s=$db->prepare($sql);$s->execute(array_values($p));return $s->fetchAll(PDO::FETCH_ASSOC)?:[];}
function hsu_json(array $v):string{return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";}
function hsu_write(string $path,array $v):string{$raw=hsu_json($v);$f=fopen($path,'xb');hsu_need(is_resource($f),'write_open');fwrite($f,$raw);fflush($f);if(function_exists('fsync'))fsync($f);fclose($f);return hash('sha256',$raw);}
function hsu_hash(mixed $v):string{return hash('sha256',json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));}

if(in_array('--self-test',$argv??[],true)){
    hsu_need(hsu_hash(['x'=>1])!=='','hash');
    echo "HOTELSU_MANUALGEO_ACCEPT_SELFTEST_OK\n";exit(0);
}

$dir=(string)getenv('MATCH_OPERATION_DIR');
$root=realpath((string)getenv('ANYTOUR_ROOT'));
$source=(string)getenv('MATCH_SOURCE_SHA');
$auditPath=realpath((string)getenv('MATCH_AUDIT_PATH'));
hsu_need(PHP_SAPI==='cli'&&is_dir($dir)&&basename($dir)===HSU_OP&&is_string($root)&&is_string($auditPath),'runtime');
hsu_need(preg_match('/^[0-9a-f]{40}$/D',$source)===1&&hash_file('sha256',$auditPath)===HSU_AUDIT_SHA,'audit_pin');
$audit=json_decode((string)file_get_contents($auditPath),true,128,JSON_THROW_ON_ERROR);
hsu_need(($audit['state']??'')==='completed_read_only'&&($audit['local_hotel_id']??0)===1244&&(string)($audit['samo_hotel_id']??'')==='2000032277'&&($audit['current_anex_resolution']??null)===1244,'audit_contract');
hsu_need(($audit['acceptance_reasons']??[])===['current_direct_place_corroboration_missing'],'owner_only_blocker');

$res=json_decode((string)file_get_contents($dir.'/reservation.json'),true,64,JSON_THROW_ON_ERROR);
hsu_need(($res['operation_id']??'')===HSU_OP&&($res['source_sha']??'')===$source,'reservation');

require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
require_once $dir.'/payload/andromeda-hotel-resolver.php';
require_once $dir.'/payload/anex-search-mapping-registry.php';

$db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$committed=false;$attempted=false;$writes=0;
$base=['operation_id'=>HSU_OP,'source_sha'=>$source,'owner_claim'=>HSU_OWNER_CLAIM,'audit_sha256'=>HSU_AUDIT_SHA,'provider_calls'=>0,'tourvisor_calls'=>0,'samo_calls'=>0,'direct_anex_calls'=>0,'quota_mutations'=>0,'no_replay'=>true];

try{
  $db->exec('SET SESSION innodb_lock_wait_timeout=10');
  $db->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
  $db->beginTransaction();

  $target=hsu_rows($db,'SELECT id,name,country_name,region_name,subregion_name,category,is_active,latitude,longitude FROM catalog_hotels WHERE id=1244 FOR UPDATE');
  hsu_need(count($target)===1&&(int)$target[0]['is_active']===1&&(string)$target[0]['country_name']==='Турция'&&(int)$target[0]['category']===5,'target_guard');

  $sourceRows=hsu_rows($db,"SELECT * FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id='2000032277' FOR UPDATE");
  hsu_need(count($sourceRows)===1,'source_count');
  $src=$sourceRows[0];
  hsu_need(($src['decision_status']??'')==='pending'&&$src['local_hotel_id']===null,'source_pending');
  $auditSrc=$audit['source_identity_rows'][0]??null;
  hsu_need(is_array($auditSrc)&&($auditSrc['catalog_sha256']??'')===($src['catalog_sha256']??'')&&($auditSrc['evidence_sha256']??'')===($src['evidence_sha256']??'')&&($auditSrc['evidence_json']??'')===($src['evidence_json']??''),'source_drift');

  $targetOcc=hsu_rows($db,"SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status FROM andromeda_hotel_identities WHERE local_hotel_id=1244 AND decision_status='accepted' FOR UPDATE");
  $competing=array_values(array_filter($targetOcc,fn($r)=>!($r['supplier_namespace']==='andromeda_catalog'&&(string)$r['external_hotel_id']==='2000032277')));
  hsu_need($competing===[],'target_occupied');

  $manual=hsu_rows($db,'SELECT anex_hotel_id,decision_status,catalog_hotel_id,decided_by,decision_note FROM anex_hotel_decisions WHERE anex_hotel_id=15072 OR catalog_hotel_id=1244 ORDER BY anex_hotel_id,catalog_hotel_id,decision_status FOR UPDATE');
  $excl=hsu_rows($db,'SELECT anex_hotel_id,catalog_hotel_id,decided_by,evidence_digest FROM anex_review_pair_exclusions WHERE anex_hotel_id=15072 OR catalog_hotel_id=1244 ORDER BY anex_hotel_id,catalog_hotel_id FOR UPDATE');
  hsu_need($manual===[]&&$excl===[],'manual_or_exclusion');

  $anex=AnyTourAnexSearchMappingRegistry::fromPdo($db);
  hsu_need($anex->resolve('anex_online','15072','preview')===1244,'anex_resolution');

  $saved=$audit['saved_candidate']??[];
  hsu_need(($saved['id']??'')==='2000032277'&&trim((string)($saved['name']??''))==='Sunis Hotel Su'&&(string)($saved['state']??'')==='Турция'&&(string)($saved['star']??'')==='5'&&(string)($saved['town']??'')==='Коньяалты','saved_supplier_guard');

  $evidence=[
    'operation_id'=>HSU_OP,
    'decision'=>'owner_manual_geo_confirmation',
    'owner_claim_comment_id'=>(int)HSU_OWNER_CLAIM,
    'owner_decision'=>'Konyaalti is accepted as central Antalya geography for this exact HOTEL SU pair',
    'tv_local'=>['id'=>1244,'name'=>$target[0]['name'],'country'=>$target[0]['country_name'],'region'=>$target[0]['region_name'],'subregion'=>$target[0]['subregion_name'],'category'=>(int)$target[0]['category'],'latitude'=>$target[0]['latitude'],'longitude'=>$target[0]['longitude']],
    'samo'=>['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>'2000032277','name'=>trim((string)$saved['name']),'country'=>$saved['state'],'town'=>$saved['town'],'town_key'=>$saved['townKey']??null,'star'=>$saved['star']],
    'independent_anex'=>['native_id'=>'15072','canonical_local_id'=>1244],
    'prior_current_audit_sha256'=>HSU_AUDIT_SHA,
    'prior_source_evidence_sha256'=>$src['evidence_sha256'],
    'prior_source_catalog_sha256'=>$src['catalog_sha256'],
    'guard_summary'=>['source_pending_exact'=>true,'target_active'=>true,'country_agrees'=>true,'category_agrees'=>true,'manual_or_exclusion'=>false,'target_competing_occupancy'=>false,'anex15072_resolves_1244'=>true,'owner_geo_confirmation'=>true]
  ];
  $ejson=hsu_json($evidence);$eh=hash('sha256',$ejson);

  hsu_write($dir.'/capture.json',$base+['source_before'=>$src,'target'=>$target[0],'manual_rows'=>$manual,'exclusion_rows'=>$excl,'target_occupancy_before'=>$targetOcc,'evidence_sha256'=>$eh]);
  hsu_write($dir.'/plan.json',$base+['write'=>['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>'2000032277','from_status'=>'pending','to_status'=>'accepted','local_hotel_id'=>1244,'catalog_sha256'=>$src['catalog_sha256'],'evidence_sha256'=>$eh]]);

  $st=$db->prepare("UPDATE andromeda_hotel_identities SET local_hotel_id=1244,decision_status='accepted',evidence_sha256=?,evidence_json=? WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id='2000032277' AND decision_status='pending' AND local_hotel_id IS NULL AND catalog_sha256=? AND evidence_sha256=? AND evidence_json=?");
  $st->execute([$eh,$ejson,$src['catalog_sha256'],$src['evidence_sha256'],$src['evidence_json']]);
  hsu_need($st->rowCount()===1,'write_count');$writes=1;
  hsu_write($dir.'/pre-commit.json',$base+['writes_uncommitted'=>1,'external_hotel_id'=>'2000032277','local_hotel_id'=>1244,'evidence_sha256'=>$eh]);
  $attempted=true;$db->commit();$committed=true;

  $db->exec('START TRANSACTION READ ONLY');
  $row=hsu_rows($db,"SELECT * FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id='2000032277'");
  hsu_need(count($row)===1&&($row[0]['decision_status']??'')==='accepted'&&(int)$row[0]['local_hotel_id']===1244&&($row[0]['catalog_sha256']??'')===($src['catalog_sha256']??'')&&($row[0]['evidence_sha256']??'')===$eh&&hash('sha256',(string)$row[0]['evidence_json'])===$eh,'row_readback');

  $rr=hsu_rows($db,"SELECT i.supplier_namespace,i.external_hotel_id,i.decision_status,i.local_hotel_id AS catalog_hotel_id,h.id AS existing_catalog_hotel_id FROM andromeda_hotel_identities i LEFT JOIN catalog_hotels h ON h.id=i.local_hotel_id WHERE i.decision_status IN ('accepted','rejected') ORDER BY i.supplier_namespace,i.external_hotel_id LIMIT 50001");
  $resolver=AnyTourAndromedaHotelResolver::fromRows($rr,hsu_hash($rr));
  $page=$resolver->apply(['provider'=>'andromeda','selection_enabled'=>false,'offers'=>[['provider'=>'andromeda','selection_enabled'=>false,'local_hotel_id'=>null,'supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>'2000032277']]]);
  hsu_need(($page['offers'][0]['local_hotel_id']??null)===1244,'samo_resolver_readback');
  $anex2=AnyTourAnexSearchMappingRegistry::fromPdo($db);
  hsu_need($anex2->resolve('anex_online','15072','preview')===1244,'anex_resolver_readback');
  $db->rollBack();

  $out=$base+['state'=>'committed_verified','database_writes'=>1,'mapping_writes'=>1,'accepted_delta'=>1,'triple_delta'=>1,'external_hotel_id'=>'2000032277','local_hotel_id'=>1244,'anex_native_id'=>'15072','evidence_sha256'=>$eh,'owner_geo_decision_applied'=>true,'readback_verified'=>true];
}catch(Throwable $e){
  if($db->inTransaction())$db->rollBack();
  $out=$base+['state'=>$committed?'committed_unverified':($attempted?'commit_outcome_unknown':'rolled_back_or_prewrite_failed'),'database_writes'=>$committed?1:($attempted?null:0),'mapping_writes'=>$committed?1:($attempted?null:0),'reason'=>preg_replace('/[^A-Za-z0-9_.:-]/','_',substr($e->getMessage(),0,160)),'readback_verified'=>false];
}
$rh=hsu_write($dir.'/result.json',$out);
hsu_write($dir.'/receipt.json',['operation_id'=>HSU_OP,'source_sha'=>$source,'state'=>$out['state'],'result_sha256'=>$rh,'database_writes'=>$out['database_writes'],'mapping_writes'=>$out['mapping_writes'],'readback_verified'=>$out['readback_verified'],'no_replay'=>true]);
echo hsu_json(['state'=>$out['state'],'database_writes'=>$out['database_writes'],'mapping_writes'=>$out['mapping_writes'],'result_sha256'=>$rh,'reason'=>$out['reason']??null]);
exit(($out['state']??'')==='committed_verified'?0:2);
