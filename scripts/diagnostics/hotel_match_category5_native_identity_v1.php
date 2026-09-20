<?php
declare(strict_types=1);
// The workflow stages a byte-pinned, functions-only prefix of the sealed #3215
// writer. Its historical entry point and operation are never executed here.
require_once __DIR__.'/category5-parent-guards.php';
const C5_OP='hotel-match-category5-native-identity-1971-20260920-v1';
const C5_PARENT_RESULT='918c08e2c5d95810c004e19c8beb22d7a022a6a38b33c59b586b1e8220be45e6';
const C5_PARENT_PROOFS='dc9896ae523fd5753ebc9aca399d9cbdb80bc0f0f5396446aa7ed8f4247cb752';
const C5_PAIRS=[4142=>['2000028171','38928'],23335=>['2000028543','25386'],23337=>['2000035153','22179'],51820=>['2000034945','22317'],126824=>['2000074682','39324']];

/** No blanket category relaxation: five immutable pairs need added exact-name proof. */
function c5_review(array $p,?array $h,array $all,array $dec,array $exc,array $resolved,array $localNames,array $sourceNames):array{
 $why=a28_guard($p,$h,$all,$dec,$exc,$resolved);$id=$p['local_hotel_id'];$sid=$p['samo_hotel_id'];$expected=C5_PAIRS[$id]??null;
 if($expected===null||$expected[0]!==$sid||$p['native_ids']!==[$expected[1]])$why[]='outside_reviewed_pairs';
 $name=$h!==null?a28_norm((string)$h['name']):'';
 if($name===''||count(explode(' ',$name))<2||a28_norm((string)$p['source_catalog']['name'])!==$name)$why[]='full_name_not_exact';
 foreach($p['rows'] as$r){
  if(a28_norm((string)($r['hotel']??''))!==$name||a28_norm((string)($r['original']['hotel']??''))!==$name)$why[]='raw_full_name_not_exact';
  if((string)($r['hotelKey']??'')!==$sid||($r['isOperatorHotelKey']??null)!==0||(string)($r['operatorKey']??'')!=='5'||(string)($r['original']['hotelKey']??'')!==($expected[1]??''))$why[]='raw_identity_not_exact';
  $u=parse_url((string)($r['hotelUrl']??''));if(!is_array($u)||($u['scheme']??'')!=='https'||($u['host']??'')!=='agent.anextour.ru'||isset($u['user'])||isset($u['pass']))$why[]='native_url_not_proven';
 }
 if($p['rows']===[])$why[]='missing_raw_rows';
 $localMatches=[];foreach($localNames as$x)if((int)$x['country_id']===(int)($h['country_id']??0)&&a28_norm((string)$x['name'])===$name)$localMatches[]=(int)$x['id'];
 if($localMatches!==[$id])$why[]='current_full_name_not_unique';
 $sourceMatches=[];foreach($sourceNames as$x)if((string)($x['stateKey']??'')===(string)$p['source_catalog']['stateKey']&&a28_norm((string)$x['name'])===$name)$sourceMatches[]=(string)$x['id'];
 if($sourceMatches!==[$sid])$why[]='source_full_name_not_unique';
 $why=array_values(array_unique($why));$ok=$why===['actual_star_label_conflict'];
 return ['eligible'=>$ok,'reasons'=>$ok?[]:$why,'parent_guard_reasons'=>a28_guard($p,$h,$all,$dec,$exc,$resolved),'category_disagreement'=>['local_raw'=>$h['category']??null,'local_known'=>a28_star($h['category']??null)!==null,'canonical_samo_raw'=>$p['source_catalog']['star']??null,'anex_price_raw'=>array_values(array_unique(array_column($p['rows'],'star'))),'category_selected_or_changed'=>false],'identity_basis'=>$ok?'exact_single_native_full_names_unique_country_concrete_place':null];
}
function c5_proofs(string $dir):array{
 $parent=a28_load($dir.'/parent/result.json',C5_PARENT_RESULT);$saved=a28_load($dir.'/parent/proofs.json',C5_PARENT_PROOFS);$receipt=a28_load($dir.'/parent/receipt.json');
 a28_need($parent['state']==='committed_verified'&&$parent['mapping_writes']===16&&$receipt['result_sha256']===C5_PARENT_RESULT,'parent_receipt');
 $old=[];foreach($saved['proofs']as$p)$old[$p['local_hotel_id']]=$p;$held=[];foreach($parent['skipped']as$p)$held[$p['local_hotel_id']]=$p;
 $all=a28_proofs($dir.'/input');$out=[];
 foreach($all as$p){$id=$p['local_hotel_id'];if(!isset(C5_PAIRS[$id]))continue;
  a28_need(($held[$id]['reasons']??null)===['actual_star_label_conflict']&&($held[$id]['samo_hotel_id']??null)===C5_PAIRS[$id][0]&&a28_hash($p)===a28_hash($old[$id]),'parent_category_only_binding');$out[]=$p;
 }
 a28_need(count($out)===5,'exact_five_proofs');return$out;
}
if(($argv[1]??'')==='--self-test'){
 $p=a28_load($argv[2])[0];$h=$p['target_snapshot'];$native=$p['native_ids'][0];$resolved=[$native=>$p['local_hotel_id']];$locals=[$h];$sources=[$p['source_catalog']];
 $go=static fn($p,$h,$all=[],$dec=[],$exc=[],$r=null,$l=null,$s=null)=>c5_review($p,$h,$all,$dec,$exc,$r??$resolved,$l??$locals,$s??$sources);
 a28_need($go($p,$h)['eligible'],'positive_exact');$count=1;
 $cases=[];$x=$p;$x['source_catalog']['name'].=' ANNEX';$cases[]=[$x,$h];$x=$p;$x['rows'][0]['hotel'].=' BEACH';$cases[]=[$x,$h];$x=$p;$x['rows'][0]['original']['hotel']='RIVAL';$cases[]=[$x,$h];$x=$p;$x['native_ids'][]='99999';$cases[]=[$x,$h];$x=$p;$x['source_catalog']['town']='Коломбо';$cases[]=[$x,$h];$x=$p;$x['source_catalog']['state']='Грузия';$cases[]=[$x,$h];$x=$p;$x['saved_proof_holds']=['canonical_namespace_unproven'];$cases[]=[$x,$h];$x=$h;$x['category']=5;$cases[]=[$p,$x];$x=$h;$x['is_active']=0;$cases[]=[$p,$x];$x=$p;$x['rows'][0]['isOperatorHotelKey']=1;$cases[]=[$x,$h];$x=$p;$x['rows'][0]['original']['hotelKey']=9999;$cases[]=[$x,$h];$x=$p;$x['rows'][0]['hotelUrl']='https://example.invalid/hotel';$cases[]=[$x,$h];
 foreach($cases as[$x,$y]){a28_need(!$go($x,$y)['eligible'],'negative_guard');$count++;}
 foreach([
  $go($p,$h,[['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>$p['samo_hotel_id'],'local_hotel_id'=>null]]),
  $go($p,$h,[['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>'9999','local_hotel_id'=>$p['local_hotel_id']]]),
  $go($p,$h,[['supplier_namespace'=>'operator_5','external_hotel_id'=>$native,'local_hotel_id'=>null]]),
  $go($p,$h,[],[['anex_hotel_id'=>$native,'catalog_hotel_id'=>9999]]),
  $go($p,$h,[],[],[['anex_hotel_id'=>$native,'catalog_hotel_id'=>$p['local_hotel_id']]]),
  $go($p,$h,[],[],[],[$native=>9999]),
  $go($p,$h,[],[],[],null,[$h,array_replace($h,['id'=>9999])]),
  $go($p,$h,[],[],[],null,null,[$p['source_catalog'],array_replace($p['source_catalog'],['id'=>9999])])
 ]as$x){a28_need(!$x['eligible'],'protected_or_ambiguous');$count++;}
 echo a28_json(['category5_checks'=>$count,'provider_calls'=>0,'database_writes'=>0]);exit(0);
}
if(($argv[1]??'')==='--inspect'){
 $out=[];foreach(c5_proofs($argv[2])as$p){$cat=a28_load($argv[2].'/input/'.$p['catalog_file'],$p['catalog_sha256']);$r=c5_review($p,$p['target_snapshot'],[],[],[],array_fill_keys($p['native_ids'],$p['local_hotel_id']),[$p['target_snapshot']],$cat['HOTELS']);$out[]=['local_hotel_id'=>$p['local_hotel_id'],'samo_hotel_id'=>$p['samo_hotel_id'],'review'=>$r,'current_db_checked'=>false];}
 echo a28_json(['prepared_reviews'=>$out,'provider_calls'=>0,'database_writes'=>0]);exit(0);
}
a28_need(PHP_SAPI==='cli'&&getenv('MATCH_OPERATION_ID')===C5_OP,'operation');$dir=rtrim((string)getenv('HOME'),'/').'/.anytoour-match/operations/'.C5_OP;a28_need(realpath((string)getenv('MATCH_OPERATION_DIR'))===$dir,'operation_dir');$sha=(string)getenv('MATCH_SOURCE_SHA');$res=a28_load($dir.'/reservation.json');a28_need(preg_match('/^[a-f0-9]{40}$/D',$sha)===1&&$res['source_sha']===$sha&&$res['operation_id']===C5_OP&&$res['mapping_write_cap']===5&&$res['state']==='reserved_before_db_access','reservation');
$base=['operation_id'=>C5_OP,'source_sha'=>$sha,'parent_result_sha256'=>C5_PARENT_RESULT,'provider_calls'=>0,'tourvisor_calls'=>0,'samo_calls'=>0,'direct_anex_calls'=>0,'anex_mapping_writes'=>0,'no_replay'=>true];$db=null;$attempted=false;$committed=false;$writes=0;$plan=[];$skipped=[];
try{
 a28_save($dir.'/execution-reservation.json',$base+['state'=>'started_before_db_access']);$proofs=c5_proofs($dir);$root=realpath(getcwd());a28_need(is_string($root)&&basename($root)==='anytoour.ru','root');require_once dirname(__DIR__,2).'/app/integrations/anex-search-mapping-registry.php';require_once dirname(__DIR__,2).'/app/integrations/andromeda-hotel-resolver.php';require_once(is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php');$db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
 foreach(['andromeda_hotel_identities','anex_hotel_search_mappings','anex_hotel_decisions','anex_review_pair_exclusions','catalog_hotels']as$t){$eng=a28_rows($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$t]);a28_need(count($eng)===1&&strtoupper((string)$eng[0]['ENGINE'])==='INNODB','engine');}
 $db->exec('SET SESSION innodb_lock_wait_timeout=10');$db->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');$db->beginTransaction();$all=a28_rows($db,'SELECT * FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id LIMIT 50001 FOR UPDATE');$before=[];foreach($all as$r)$before[$r['supplier_namespace'].':'.$r['external_hotel_id']]=a28_hash($r);
 $ids=array_keys(C5_PAIRS);$natives=array_column(C5_PAIRS,1);$tp=implode(',',array_fill(0,5,'?'));$hot=[];foreach(a28_rows($db,"SELECT * FROM catalog_hotels WHERE id IN ($tp) ORDER BY id FOR UPDATE",$ids)as$h)$hot[(int)$h['id']]=$h;
 $localNames=a28_rows($db,'SELECT id,name,country_id FROM catalog_hotels WHERE country_id IN (3,54) ORDER BY id LIMIT 50001 FOR UPDATE');
 $params=array_merge($natives,$ids);$maps=a28_rows($db,"SELECT * FROM anex_hotel_search_mappings WHERE anex_hotel_id IN ($tp) OR catalog_hotel_id IN ($tp) FOR UPDATE",$params);$dec=a28_rows($db,"SELECT * FROM anex_hotel_decisions WHERE anex_hotel_id IN ($tp) OR catalog_hotel_id IN ($tp) FOR UPDATE",$params);$exc=a28_rows($db,"SELECT * FROM anex_review_pair_exclusions WHERE anex_hotel_id IN ($tp) OR catalog_hotel_id IN ($tp) FOR UPDATE",$params);$anex=AnyTourAnexSearchMappingRegistry::fromPdo($db);$resolved=[];foreach($natives as$n)$resolved[$n]=$anex->resolve('anex_online',$n,'preview');$reviews=[];
 foreach($proofs as$p){$id=$p['local_hotel_id'];$cat=a28_load($dir.'/input/'.$p['catalog_file'],$p['catalog_sha256']);$review=c5_review($p,$hot[$id]??null,$all,$dec,$exc,$resolved,$localNames,$cat['HOTELS']);$reviews[$id]=$review;if(!$review['eligible']){$skipped[]=['local_hotel_id'=>$id,'samo_hotel_id'=>$p['samo_hotel_id'],'reasons'=>$review['reasons']];continue;}
  $e=$p;unset($e['rows']);$e['native_representative_rows']=[$p['native_ids'][0]=>$p['rows'][0]];$e['raw_row_count']=count($p['rows']);$e['operation_id']=C5_OP;$e['rule']='reviewed_category_disagreement_exact_full_names_unique_country_place_single_native';$e['category_review']=$review;$e['parent_result_sha256']=C5_PARENT_RESULT;$e['current_target']=$hot[$id];$e['current_anex_resolution']=[$p['native_ids'][0]=>$id];$ej=a28_json($e);a28_need(strlen($ej)<60000,'evidence_size');$plan[$p['samo_hotel_id']]=['local_hotel_id'=>$id,'native_ids'=>$p['native_ids'],'catalog_sha256'=>$p['catalog_sha256'],'evidence_sha256'=>hash('sha256',$ej),'evidence_json'=>$ej];
 }
 a28_need(count($plan)<=5,'write_cap');a28_save($dir.'/capture.json',$base+['identity_hashes_before'=>$before,'current_hotels'=>$hot,'current_anex_rows'=>$maps,'current_manual_rows'=>$dec,'current_exclusion_rows'=>$exc,'native_resolution'=>$resolved,'category_reviews'=>$reviews,'same_country_name_rows'=>$localNames]);$planHash=a28_save($dir.'/plan.json',$base+['mapping_write_cap'=>5,'safe_writes'=>$plan,'skipped'=>$skipped]);
 if($plan===[]){$db->rollBack();$out=$base+['state'=>'completed_no_safe_delta','database_writes'=>0,'mapping_writes'=>0,'readback_verified'=>true,'skipped'=>$skipped,'plan_sha256'=>$planHash];}
 else{
  foreach($plan as$sid=>$p){$q=$db->prepare("INSERT INTO andromeda_hotel_identities(supplier_namespace,external_hotel_id,local_hotel_id,decision_status,catalog_sha256,evidence_sha256,evidence_json) VALUES('andromeda_catalog',?,?,'accepted',?,?,?)");$q->execute([(string)$sid,$p['local_hotel_id'],$p['catalog_sha256'],$p['evidence_sha256'],$p['evidence_json']]);a28_need($q->rowCount()===1,'insert_count');$writes++;}
  $projection=a28_projection($db);foreach($plan as$sid=>$p)a28_need(a28_resolve_samo($projection,(string)$sid)===$p['local_hotel_id'],'precommit_resolver');$unchanged=[];foreach(a28_rows($db,'SELECT * FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id LIMIT 50001')as$r)if(!($r['supplier_namespace']==='andromeda_catalog'&&isset($plan[(string)$r['external_hotel_id']])))$unchanged[$r['supplier_namespace'].':'.$r['external_hotel_id']]=a28_hash($r);a28_need($unchanged===$before,'prior_identities_changed');$checkpoint=a28_save($dir.'/pre-commit.json',$base+['plan_sha256'=>$planHash,'writes_uncommitted'=>$writes,'prior_identity_count'=>count($before),'state'=>'ready_to_commit']);$attempted=true;$db->commit();$committed=true;
  $db->exec('START TRANSACTION READ ONLY');$projection=a28_projection($db);$anex=AnyTourAnexSearchMappingRegistry::fromPdo($db);$verified=[];$oldAfter=[];foreach(a28_rows($db,'SELECT * FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id LIMIT 50001')as$r){$sid=(string)$r['external_hotel_id'];if($r['supplier_namespace']==='andromeda_catalog'&&isset($plan[$sid])){$p=$plan[$sid];a28_need($r['decision_status']==='accepted'&&(int)$r['local_hotel_id']===$p['local_hotel_id']&&$r['catalog_sha256']===$p['catalog_sha256']&&$r['evidence_sha256']===$p['evidence_sha256']&&$r['evidence_json']===$p['evidence_json']&&hash('sha256',$r['evidence_json'])===$p['evidence_sha256'],'row_readback');a28_need(a28_resolve_samo($projection,$sid)===$p['local_hotel_id']&&$anex->resolve('anex_online',$p['native_ids'][0],'preview')===$p['local_hotel_id'],'resolver_readback');$verified[]=['samo_hotel_id'=>$sid,'local_hotel_id'=>$p['local_hotel_id'],'native_ids'=>$p['native_ids'],'evidence_sha256'=>$p['evidence_sha256'],'row_sha256'=>a28_hash($r)];}else$oldAfter[$r['supplier_namespace'].':'.$sid]=a28_hash($r);}
  a28_need($oldAfter===$before&&count($verified)===$writes,'postcommit_count_and_preservation');$afterHot=[];foreach(a28_rows($db,"SELECT * FROM catalog_hotels WHERE id IN ($tp) ORDER BY id",$ids)as$h)$afterHot[(int)$h['id']]=$h;a28_need($afterHot===$hot,'catalog_rows_changed');$db->rollBack();a28_save($dir.'/readback.json',$base+['verified_rows'=>$verified,'prior_identity_count'=>count($before),'catalog_rows_unchanged'=>true]);$out=$base+['state'=>'committed_verified','database_writes'=>$writes,'mapping_writes'=>$writes,'unique_samo_local_delta'=>$writes,'triple_delta'=>$writes,'written'=>$verified,'skipped'=>$skipped,'prior_identity_count'=>count($before),'catalog_rows_unchanged'=>true,'plan_sha256'=>$planHash,'precommit_sha256'=>$checkpoint,'readback_verified'=>true];
 }
}catch(Throwable $e){if($db&&$db->inTransaction())$db->rollBack();$out=$base+['state'=>$committed?'committed_unverified':($attempted?'commit_outcome_unknown':'rolled_back_or_prewrite_failed'),'reason'=>preg_match('/^[a-zA-Z0-9_]+$/D',$e->getMessage())?$e->getMessage():'database_or_runtime_error','error_class'=>get_class($e),'database_writes'=>$committed?$writes:($attempted?null:0),'mapping_writes'=>$committed?$writes:($attempted?null:0),'readback_verified'=>false];}
$hash=a28_save($dir.'/result.json',$out);a28_save($dir.'/receipt.json',$base+['state'=>$out['state'],'result_sha256'=>$hash,'database_writes'=>$out['database_writes'],'mapping_writes'=>$out['mapping_writes'],'readback_verified'=>$out['readback_verified']]);echo a28_json(['state'=>$out['state'],'mapping_writes'=>$out['mapping_writes'],'reason'=>$out['reason']??null,'result_sha256'=>$hash]);exit(in_array($out['state'],['committed_verified','completed_no_safe_delta'],true)?0:2);
