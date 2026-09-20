<?php
declare(strict_types=1);

const ILK_OP='hotel-match-ilkay-operator5-anex-current-review-1971-20260920-v1';
const ILK_LOCAL=17483;
const ILK_SAMO='4176';
const ILK_ANEX=32572;
const ILK_EVIDENCE='8b50ba11fd1907d0b43f2152e4fc10d8eb0e555d556e74a97f8065c1501f10dd';
const ILK_POLICY='owner_exact_and_strong_20260908';
function req(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
function q(PDO $db,string $sql,array $args=[]):array{$s=$db->prepare($sql);$s->execute(array_values($args));return $s->fetchAll(PDO::FETCH_ASSOC)?:[];}
function once(string $p,array $v):string{$raw=json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";$f=fopen($p,'x+b');req(is_resource($f),'open');req(fwrite($f,$raw)===strlen($raw),'write');fflush($f);if(function_exists('fsync'))fsync($f);rewind($f);req(stream_get_contents($f)===$raw,'readback');fclose($f);return hash('sha256',$raw);}
function accepted_anex(array $maps,array $decisions,array $exclusions):array{
  $excluded=[];foreach($exclusions as $x)$excluded[((int)$x['anex_hotel_id']).':'.((int)$x['catalog_hotel_id'])]=true;
  $decisionBy=[];foreach($decisions as $d)$decisionBy[(int)$d['anex_hotel_id']]=$d;
  $out=[];
  foreach($maps as $m){$a=(int)$m['anex_hotel_id'];$l=(int)$m['catalog_hotel_id'];$d=$decisionBy[$a]??null;if(isset($excluded[$a.':'.$l]))continue;if($d)continue;if((int)$m['enabled']===1&&(string)$m['scope']==='preview'&&(string)$m['approval_policy']===ILK_POLICY)$out[$a]=$l;}
  foreach($decisions as $d){$a=(int)$d['anex_hotel_id'];$l=$d['catalog_hotel_id']===null?0:(int)$d['catalog_hotel_id'];if((string)$d['decision_status']==='accepted'&&$l>0&&!isset($excluded[$a.':'.$l]))$out[$a]=$l;}
  return $out;
}
function main():void{
  req(PHP_SAPI==='cli'&&(string)getenv('MATCH_OPERATION_ID')===ILK_OP,'operation');$sha=(string)getenv('MATCH_SOURCE_SHA');req(preg_match('/^[0-9a-f]{40}$/D',$sha)===1,'sha');
  $dir=rtrim((string)getenv('HOME'),'/').'/.anytoour-match/operations/'.ILK_OP;$res=json_decode((string)file_get_contents($dir.'/reservation.json'),true,32,JSON_THROW_ON_ERROR);req(($res['operation_id']??'')===ILK_OP&&($res['source_sha']??'')===$sha,'reservation');
  $root=realpath(getcwd());req(is_string($root)&&basename($root)==='anytoour.ru','root');$dbp=$root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');require_once $dbp;$db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
  $out=['operation_id'=>ILK_OP,'source_sha'=>$sha,'state'=>'failed_no_replay','supplier_calls'=>0,'tourvisor_calls'=>0,'samo_calls'=>0,'andromeda_calls'=>0,'direct_anex_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'safe_to_write_now'=>false];
  try{
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
    $catalog=q($db,'SELECT id,name,country_id,country_name,region_id,region_name,subregion_id,subregion_name,category,is_active FROM catalog_hotels WHERE id=?',[ILK_LOCAL]);
    $andro=q($db,"SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status,evidence_sha256 FROM andromeda_hotel_identities WHERE (supplier_namespace='andromeda_catalog' AND (external_hotel_id=? OR local_hotel_id=?)) OR (supplier_namespace='operator_5' AND (external_hotel_id=? OR local_hotel_id=?))",[ILK_SAMO,ILK_LOCAL,(string)ILK_ANEX,ILK_LOCAL]);
    $maps=q($db,'SELECT anex_hotel_id,catalog_hotel_id,enabled,scope,approval_policy FROM anex_hotel_search_mappings WHERE anex_hotel_id=? OR catalog_hotel_id=?',[ILK_ANEX,ILK_LOCAL]);
    $decisions=q($db,'SELECT anex_hotel_id,catalog_hotel_id,decision_status FROM anex_hotel_decisions WHERE anex_hotel_id=? OR catalog_hotel_id=?',[ILK_ANEX,ILK_LOCAL]);
    $exclusions=q($db,'SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions WHERE anex_hotel_id=? OR catalog_hotel_id=?',[ILK_ANEX,ILK_LOCAL]);
    $accepted=accepted_anex($maps,$decisions,$exclusions);
    $canon=array_values(array_filter($andro,fn($r)=>(string)$r['supplier_namespace']==='andromeda_catalog'&&(string)$r['external_hotel_id']===ILK_SAMO&&(int)$r['local_hotel_id']===ILK_LOCAL&&(string)$r['decision_status']==='accepted'));
    $op5=array_values(array_filter($andro,fn($r)=>(string)$r['supplier_namespace']==='operator_5'&&(string)$r['external_hotel_id']===(string)ILK_ANEX&&(int)$r['local_hotel_id']===ILK_LOCAL&&(string)$r['decision_status']==='accepted'&&(string)$r['evidence_sha256']===ILK_EVIDENCE));
    $op5Comp=array_values(array_filter($andro,fn($r)=>(string)$r['supplier_namespace']==='operator_5'&&(string)$r['external_hotel_id']===(string)ILK_ANEX&&(int)$r['local_hotel_id']!==ILK_LOCAL&&in_array((string)$r['decision_status'],['accepted','pending','conflict'],true)));
    $pairExcluded=count(array_filter($exclusions,fn($r)=>(int)$r['anex_hotel_id']===ILK_ANEX&&(int)$r['catalog_hotel_id']===ILK_LOCAL))>0;
    $same=isset($accepted[ILK_ANEX])&&$accepted[ILK_ANEX]===ILK_LOCAL;
    $sourceConflict=isset($accepted[ILK_ANEX])&&$accepted[ILK_ANEX]!==ILK_LOCAL;
    $targetOccupants=[];foreach($accepted as $a=>$l)if($l===ILK_LOCAL&&$a!==ILK_ANEX)$targetOccupants[]=$a;
    $facts=count($catalog)===1&&((int)$catalog[0]['is_active']===1)&&in_array((string)$catalog[0]['country_name'],['Турция','Turkey','turkey'],true);
    $safe=$facts&&count($canon)===1&&count($op5)===1&&!$op5Comp&&!$pairExcluded&&!$same&&!$sourceConflict&&!$targetOccupants;
    $class=$same?'already_materialized':($sourceConflict?'anex_source_conflict':($targetOccupants?'anex_target_occupied':($pairExcluded?'pair_excluded':($op5Comp?'operator5_competition':($safe?'safe_materialization_candidate':'hold_incomplete_evidence')))));
    $db->commit();
    $out['state']='completed_read_only';$out['read_at_utc']=gmdate('c');$out['classification']=$class;$out['safe_to_write_now']=$safe;$out['candidate']=['local_hotel_id'=>ILK_LOCAL,'samo_hotel_id'=>ILK_SAMO,'native_anex_id'=>ILK_ANEX,'operator5_evidence_sha256'=>ILK_EVIDENCE];$out['catalog']=$catalog[0]??null;$out['canonical_samo_match_count']=count($canon);$out['operator5_exact_match_count']=count($op5);$out['operator5_competitor_count']=count($op5Comp);$out['pair_excluded']=$pairExcluded;$out['accepted_anex_same_pair']=$same;$out['accepted_anex_source_conflict']=$sourceConflict;$out['accepted_anex_target_occupants']=$targetOccupants;$out['current_maps']=$maps;$out['current_decisions']=$decisions;$out['current_exclusions']=$exclusions;
  }catch(Throwable $e){if($db->inTransaction())$db->rollBack();$out['reason']='read_failed';$out['error_class']=get_class($e);}
  $digest=once($dir.'/result.json',$out);once($dir.'/receipt.json',['operation_id'=>ILK_OP,'source_sha'=>$sha,'state'=>$out['state'],'result_sha256'=>$digest,'readback_verified'=>true,'supplier_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'no_replay'=>true]);if($out['state']!=='completed_read_only')exit(2);
}
function selftest():void{$m=[['anex_hotel_id'=>1,'catalog_hotel_id'=>2,'enabled'=>1,'scope'=>'preview','approval_policy'=>ILK_POLICY]];req(accepted_anex($m,[],[])[1]===2,'accept');req(accepted_anex($m,[],[['anex_hotel_id'=>1,'catalog_hotel_id'=>2]])===[],'exclude');echo "ILKAY_OPERATOR5_CURRENT_REVIEW_OK\n";}
if(($argv[1]??'')==='--self-test'){selftest();exit(0);}main();
