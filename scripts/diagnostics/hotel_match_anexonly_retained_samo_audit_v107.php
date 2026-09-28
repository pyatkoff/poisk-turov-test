<?php
declare(strict_types=1);
require_once __DIR__.'/hotel_match_retained93_primary_write_v84.php';
require_once __DIR__.'/hotel_match_live30_common4_gap_matrix_v1.php';
const M107_OP='hotel-match-anexonly-retained-samo-audit-1971-20260928-v107';
const M107_LEDGER_OP='hotel-match-request-ledger-1971-20260928-v93';
const M107_LEDGER_SHA='8da64bc3ac54d707d335b8808b171f46a02ba0464536681bc40b741136df935c';
const M107_NATIVE_OP='hotel-match-live30-retained-native-union-1971-20260927-v77';
const M107_NATIVE_SHA='d40fbe2e0240a5df194ac838376a3425a8f4e80f2426a757560fe369011107f7';
function m107_need(bool$b,string$m):void{if(!$b)throw new RuntimeException($m);}
function m107_main(array$a):int{
 m107_need(($a[1]??'')==='--execute','disabled');$root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$head=(string)getenv('MATCH_SOURCE_SHA');
 m107_need(is_dir($root)&&basename($root)==='anytoour.ru'&&is_dir($dir)&&basename($dir)===M107_OP&&preg_match('/^[a-f0-9]{40}$/D',$head),'scope');
 foreach(['result.json','receipt.json']as$f)m107_need(!file_exists($dir.'/'.$f),'no_replay');
 $ops=dirname($dir);$ledger=w84_read($ops.'/'.M107_LEDGER_OP.'/result.json',M107_LEDGER_SHA,33554432);$native=w84_read($ops.'/'.M107_NATIVE_OP.'/result.json',M107_NATIVE_SHA,67108864);
 m107_need(($ledger['state']??'')==='completed_read_only_request_ledger'&&($native['state']??'')==='completed_retained_native_scan','inputs');
 $nidx=[];foreach($native['native_facts']as$f){$ns=(string)$f['supplier_namespace'];if(!in_array($ns,['operator_315','operator_342'],true))continue;$n=(string)$f['native_id'];$cat=(string)$f['catalog_id'];$nidx[$ns][$n][$cat]=$f;}
 require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');$db=v2_data_db();$m=hmc4_execute($db);m107_need(($m['state']??'')==='completed_read_only_common4_gap_matrix','matrix');
 $cur=[];foreach($m['rows']as$r)$cur[(int)$r['tv_hotel_id']]=$r;
 $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
 try{
  $ident=w76_q($db,'SELECT * FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id LIMIT 50001');$idx=w76_index($ident);$manual=[];$excluded=[];
  foreach(w76_q($db,'SELECT catalog_hotel_id FROM anex_hotel_decisions WHERE catalog_hotel_id IS NOT NULL')as$r)$manual[(int)$r['catalog_hotel_id']]=true;
  foreach(w76_q($db,'SELECT catalog_hotel_id FROM anex_review_pair_exclusions')as$r)$excluded[(int)$r['catalog_hotel_id']]=true;
  $hot=[];foreach(w76_q($db,'SELECT id,name,country_name,region_name,subregion_name,category,is_active FROM catalog_hotels WHERE is_active=1 ORDER BY id LIMIT 50001')as$r)$hot[(int)$r['id']]=$r;
  $live=[];foreach(w76_q($db,'SELECT DISTINCT hotel_id FROM tour_operator_identity_observations WHERE last_seen_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 30 DAY)')as$r)$live[(int)$r['hotel_id']]=true;
  $candidates=[];$holds=[];$seen=[];
  foreach($ledger['rows']as$r){$id=(int)$r['tv_hotel_id'];if(($cur[$id]['gap_bucket']??'')!=='anex_only')continue;
   foreach([[25,'operator_315'],[43,'operator_342']]as[$op,$ns])foreach($r['lanes'][(string)$op]['retained_exact']??[]as$nv){$n=(string)$nv;$cats=array_keys($nidx[$ns][$n]??[]);if(count($cats)!==1)continue;$cat=(string)$cats[0];$key=$id.'|'.$cat;if(isset($seen[$key]))continue;$seen[$key]=true;$why=[];
    $src=$idx['andromeda_catalog|'.$cat]??null;$opr=$idx[$ns.'|'.$n]??null;
    if(!$src)$why[]='canonical_source_missing';elseif(($src['decision_status']??'')==='accepted'&&$src['local_hotel_id']!==null&&(int)$src['local_hotel_id']===$id){$holds[]=['local_hotel_id'=>$id,'catalog_id'=>$cat,'operator_namespace'=>$ns,'native_id'=>$n,'reasons'=>['already_same']];continue;}
    elseif(($src['decision_status']??'')!=='pending'||$src['local_hotel_id']!==null||!w78_auto($src))$why[]='canonical_source_not_automatic_pending';
    if($opr&&($opr['decision_status']??'')==='accepted'&&$opr['local_hotel_id']!==null&&(int)$opr['local_hotel_id']!==$id)$why[]='operator_other_target';
    elseif($opr&&($opr['decision_status']??'')!=='accepted'&&!w78_auto_operator($opr))$why[]='operator_protected';
    if(isset($manual[$id]))$why[]='manual_target_protected';if(isset($excluded[$id]))$why[]='excluded_target_protected';if(!isset($live[$id]))$why[]='outside_live30';if(!isset($hot[$id]))$why[]='target_inactive';
    foreach($ident as$x)if($x['supplier_namespace']==='andromeda_catalog'&&$x['local_hotel_id']!==null&&(int)$x['local_hotel_id']===$id&&(string)$x['external_hotel_id']!==$cat&&($x['decision_status']??'')==='accepted'){$why[]='target_other_canonical';break;}
    $proof=null;try{$proof=w84_raw($ops,$nidx[$ns][$n][$cat],$cat,$ns,$n);}catch(Throwable){$why[]='raw_samo_proof_missing';}
    $item=['local_hotel_id'=>$id,'hotel_name'=>$r['hotel_name'],'catalog_id'=>$cat,'operator_namespace'=>$ns,'native_id'=>$n,'source_evidence_sha256'=>$src['evidence_sha256']??null,'raw_proof'=>$proof,'reasons'=>array_values(array_unique($why))];
    if($item['reasons'])$holds[]=$item;else$candidates[]=$item;
   }
  }
  $coverage=w76_census($db);$cohort=w84_live_sources($root);$samo=w84_samo_census($ident,$cohort);$db->rollBack();
  $out=['operation'=>M107_OP,'state'=>'completed_read_only_anexonly_retained_samo_audit','source_sha'=>$head,'generated_at_utc'=>gmdate('c'),'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'new_operator_ids'=>0,'coverage_current'=>$coverage,'samo_live30_current'=>$samo,'candidate_count'=>count($candidates),'candidate_hotels'=>count(array_unique(array_column($candidates,'local_hotel_id'))),'hold_count'=>count($holds),'candidates'=>$candidates,'holds'=>$holds,'no_replay'=>true];
 }catch(Throwable$e){if($db->inTransaction())$db->rollBack();throw$e;}
 $sha=w76_save($dir.'/result.json',$out);w76_save($dir.'/receipt.json',['operation'=>M107_OP,'state'=>$out['state'],'source_sha'=>$head,'result_sha256'=>$sha,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'no_replay'=>true]);echo w76_json(array_diff_key($out,['candidates'=>true,'holds'=>true,'samo_live30_current'=>true]))."\n";return 0;
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__)exit(m107_main($argv));
