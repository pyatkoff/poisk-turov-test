<?php
declare(strict_types=1);
const HSR_OP='hotel-match-hotelsu1244-manualgeo-reconcile-1971-20260920-v1';
function hsr_need(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
function hsr_rows(PDO $db,string $sql,array $p=[]):array{$s=$db->prepare($sql);$s->execute(array_values($p));return $s->fetchAll(PDO::FETCH_ASSOC)?:[];}
function hsr_json(array $v):string{return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";}
function hsr_write(string $p,array $v):string{$raw=hsr_json($v);$f=fopen($p,'xb');hsr_need(is_resource($f),'write_open');fwrite($f,$raw);fflush($f);if(function_exists('fsync'))fsync($f);fclose($f);return hash('sha256',$raw);}
function hsr_hash(mixed $v):string{return hash('sha256',json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));}
if(in_array('--self-test',$argv??[],true)){hsr_need(hsr_hash(['x'=>1])!=='','hash');echo "HOTELSU_RECONCILE_SELFTEST_OK\n";exit(0);}
$dir=(string)getenv('MATCH_OPERATION_DIR');$root=realpath((string)getenv('ANYTOUR_ROOT'));$sha=(string)getenv('MATCH_SOURCE_SHA');
hsr_need(PHP_SAPI==='cli'&&is_dir($dir)&&basename($dir)===HSR_OP&&is_string($root)&&preg_match('/^[0-9a-f]{40}$/D',$sha),'runtime');
$res=json_decode((string)file_get_contents($dir.'/reservation.json'),true,64,JSON_THROW_ON_ERROR);hsr_need(($res['operation_id']??'')===HSR_OP&&($res['source_sha']??'')===$sha,'reservation');
require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
require_once $dir.'/payload/andromeda-hotel-resolver.php';
require_once $dir.'/payload/anex-search-mapping-registry.php';
$db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$base=['operation_id'=>HSR_OP,'source_sha'=>$sha,'provider_calls'=>0,'tourvisor_calls'=>0,'samo_calls'=>0,'direct_anex_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'quota_mutations'=>0,'no_replay'=>true];
try{
 $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
 $source=hsr_rows($db,"SELECT * FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id='2000032277'");
 $targetOcc=hsr_rows($db,"SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status,evidence_sha256 FROM andromeda_hotel_identities WHERE local_hotel_id=1244 ORDER BY supplier_namespace,external_hotel_id");
 $target=hsr_rows($db,"SELECT id,name,country_name,region_name,subregion_name,category,is_active FROM catalog_hotels WHERE id=1244");
 $rr=hsr_rows($db,"SELECT i.supplier_namespace,i.external_hotel_id,i.decision_status,i.local_hotel_id AS catalog_hotel_id,h.id AS existing_catalog_hotel_id FROM andromeda_hotel_identities i LEFT JOIN catalog_hotels h ON h.id=i.local_hotel_id WHERE i.decision_status IN ('accepted','rejected') ORDER BY i.supplier_namespace,i.external_hotel_id LIMIT 50001");
 $resolver=AnyTourAndromedaHotelResolver::fromRows($rr,hsr_hash($rr));
 $page=$resolver->apply(['provider'=>'andromeda','selection_enabled'=>false,'offers'=>[['provider'=>'andromeda','selection_enabled'=>false,'local_hotel_id'=>null,'supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>'2000032277']]]);
 $anex=AnyTourAnexSearchMappingRegistry::fromPdo($db);$anexLocal=$anex->resolve('anex_online','15072','preview');
 $clock=hsr_rows($db,'SELECT UTC_TIMESTAMP AS db_utc_timestamp')[0]['db_utc_timestamp'];$db->rollBack();
 $state='unexpected_current_state';$resolution=$page['offers'][0]['local_hotel_id']??null;
 if(count($source)===1&&($source[0]['decision_status']??'')==='accepted'&&(int)($source[0]['local_hotel_id']??0)===1244&&$resolution===1244&&$anexLocal===1244)$state='reconciled_committed_verified';
 elseif(count($source)===1&&($source[0]['decision_status']??'')==='pending'&&$source[0]['local_hotel_id']===null&&$resolution===null&&$anexLocal===1244)$state='reconciled_not_committed_exact_pending';
 $out=$base+['state'=>$state,'read_at_utc'=>$clock,'source_rows'=>$source,'target_rows'=>$target,'target_occupancy'=>$targetOcc,'canonical_samo_resolution'=>$resolution,'canonical_anex15072_resolution'=>$anexLocal,'safe_to_reissue_write'=>$state==='reconciled_not_committed_exact_pending'];
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();$out=$base+['state'=>'failed_no_replay','reason'=>preg_replace('/[^A-Za-z0-9_.:-]/','_',substr($e->getMessage(),0,160)),'safe_to_reissue_write'=>false];}
$rh=hsr_write($dir.'/result.json',$out);hsr_write($dir.'/receipt.json',['operation_id'=>HSR_OP,'source_sha'=>$sha,'state'=>$out['state'],'result_sha256'=>$rh,'database_writes'=>0,'mapping_writes'=>0,'readback_verified'=>hash_file('sha256',$dir.'/result.json')===$rh,'no_replay'=>true]);
echo hsr_json(['state'=>$out['state'],'canonical_samo_resolution'=>$out['canonical_samo_resolution']??null,'canonical_anex15072_resolution'=>$out['canonical_anex15072_resolution']??null,'safe_to_reissue_write'=>$out['safe_to_reissue_write']??false,'result_sha256'=>$rh]);exit(str_starts_with((string)$out['state'],'reconciled_')?0:2);
