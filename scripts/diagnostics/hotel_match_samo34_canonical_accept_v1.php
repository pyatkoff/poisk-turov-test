<?php
declare(strict_types=1);
const HMCA_OPERATION='hotel-match-samo34-canonical-accept-1971-20260919-v1';
const HMCA_CURRENT_SHA='ce3876480bd8dd1899192c0ff1c432f5925c802492aff61d5caada1483090d94';
const HMCA_INPUT_SHA='3c084e5e5c672dc7f6ffae5abe3eacccd6c0a57463ac79b0a6a2c1d6e2c39953';
function hmca_order(mixed $v):mixed {if(!is_array($v))return $v;if(!array_is_list($v))ksort($v,SORT_STRING);foreach($v as &$x)$x=hmca_order($x);unset($x);return $v;}
function hmca_hash(mixed $v):string {return hash('sha256',json_encode(hmca_order($v),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));}
function hmca_guard(array $plan,array $hs,array $aliases,array $catalog,array $identities,array $guards,?int $effective):void {
 hmcc_require($plan['tv_hotel_id']===93680&&$plan['external_hotel_id']==='2000084849'&&$plan['anex_id']===39882,'exact_target');
 hmcc_require(($plan['read_only_eligible']??false)===true&&($plan['reasons']??null)===[],'review_plan');
 $by=[];$index=[];$targetKeys=[];
 foreach($hs as $h){$id=(int)$h['id'];$by[$id]=$h;if((int)$h['country_id']!==4||(int)$h['is_active']!==1)continue;foreach(hmcc_names($h['name']) as $k){$index[$k][$id]=true;if($id===93680)$targetKeys[$k]=true;}}
 foreach($aliases as $a){$id=(int)$a['hotel_id'];if(!isset($by[$id])||(int)$by[$id]['is_active']!==1)continue;foreach(hmcc_names($a['alias']) as $k){$index[$k][$id]=true;if($id===93680)$targetKeys[$k]=true;}}
 $target=$by[93680]??null;hmcc_require(is_array($target)&&(int)$target['country_id']===4&&(int)$target['is_active']===1,'current_target');
 hmcc_require(hmca_hash($target)===hmca_hash($plan['target']),'target_drift');
 $source=null;$sourceRivals=[];$localRivals=[];$seen=[];
 foreach($catalog as $s){$id=(string)$s['id'];hmcc_require(!isset($seen[$id]),'duplicate_catalog');$seen[$id]=true;if((string)$s['stateKey']!=='5')continue;
  foreach([$s['name'],$s['lName']??''] as $n)foreach(hmcc_names($n) as $k)if(isset($targetKeys[$k]))$sourceRivals[$id]=true;
  if($id==='2000084849')$source=$s;
 }
 hmcc_require(is_array($source)&&hmca_hash($source)===hmca_hash($plan['source']),'source_drift_or_country');
 foreach([$source['name'],$source['lName']??''] as $n)foreach(hmcc_names($n) as $k)foreach(array_keys($index[$k]??[]) as $id)$localRivals[$id]=true;
 hmcc_require(count($localRivals)===1&&isset($localRivals[93680]),'local_name_competition');
 hmcc_require(count($sourceRivals)===1&&isset($sourceRivals['2000084849']),'source_name_competition');
 hmcc_require(hmcc_star($target['category'])===5&&hmcc_star($source['star'])===5,'category');
 hmcc_require(hmcc_norm($source['town'])==='бодрум'&&in_array('бодрум',[hmcc_norm((string)$target['region_name']),hmcc_norm((string)$target['subregion_name'])],true),'geography');
 foreach($identities as $r){hmcc_require(!($r['supplier_namespace']==='andromeda_catalog'&&(string)$r['external_hotel_id']==='2000084849'),'source_occupied');hmcc_require($r['local_hotel_id']===null||(int)$r['local_hotel_id']!==93680,'target_occupied_or_protected');}
 hmcc_require($effective===93680,'effective_anex_anchor');
 hmcc_require(($guards['anex_hotel_decisions']??null)===[]&&($guards['anex_review_pair_exclusions']??null)===[],'manual_or_exclusion');
 $ms=$guards['anex_hotel_search_mappings']??[];hmcc_require(count($ms)===1,'anex_mapping_count');$m=$ms[0];
 hmcc_require((int)$m['anex_hotel_id']===39882&&(int)$m['catalog_hotel_id']===93680&&(int)$m['enabled']===1&&$m['scope']==='preview'&&$m['match_class']==='exact'&&$m['approval_policy']==='owner_exact_and_strong_20260908'&&$m['mapping_digest']==='ff8a3412b84666b4373c636460d38fbe6ddfcf0cbd21d044095831134ea4185e','anex_mapping_drift');
}
$policy=(string)getenv('MATCH_POLICY_PATH');if($policy==='')$policy=__DIR__.'/policy.php';
if(!is_file($policy)||hash_file('sha256',$policy)!=='d76ca3ac77ca2b9524630b8082af856a17d6395c1434040e29e9196b9752358a')throw new RuntimeException('policy_digest');require_once $policy;
if(in_array('--self-test',$argv??[],true)) {
 $fixture=(string)getenv('MATCH_TEST_CURRENT');$current=json_decode((string)file_get_contents($fixture),true,64,JSON_THROW_ON_ERROR);hmcc_require(hash_file('sha256',$fixture)===HMCA_CURRENT_SHA,'test_capture');
 $input=json_decode((string)file_get_contents((string)getenv('MATCH_TEST_INPUT')),true,64,JSON_THROW_ON_ERROR);$plan=$current['new_candidate_plans'][0];$hs=[$plan['target']];$catalog=[$plan['source']];$guards=['anex_hotel_decisions'=>[],'anex_review_pair_exclusions'=>[],'anex_hotel_search_mappings'=>array_values(array_filter($current['anex_guards']['anex_hotel_search_mappings'],fn($r)=>(int)$r['anex_hotel_id']===39882))];
 hmca_guard($plan,$hs,[],$catalog,[],$guards,93680);$cases=[];
 $x=$plan;$x['tv_hotel_id']=1299;$cases[]=[$x,$hs,[],$catalog,[],$guards,93680];
 $x=$plan;$x['read_only_eligible']=false;$cases[]=[$x,$hs,[],$catalog,[],$guards,93680];
 $x=$hs;$x[0]['name']='OTHER';$cases[]=[$plan,$x,[],$catalog,[],$guards,93680];
 $x=$hs;$x[0]['is_active']=0;$cases[]=[$plan,$x,[],$catalog,[],$guards,93680];
 $x=$hs;$x[0]['country_id']=1;$cases[]=[$plan,$x,[],$catalog,[],$guards,93680];
 $x=$catalog;$x[0]['stateKey']=3;$cases[]=[$plan,$hs,[],$x,[],$guards,93680];
 $x=$catalog;$x[0]['star']='4';$cases[]=[$plan,$hs,[],$x,[],$guards,93680];
 $x=$catalog;$x[0]['town']='Кемер';$cases[]=[$plan,$hs,[],$x,[],$guards,93680];
 $x=$hs;$h=$hs[0];$h['id']=9999;$x[]=$h;$cases[]=[$plan,$x,[],$catalog,[],$guards,93680];
 $x=$catalog;$h=$catalog[0];$h['id']=9999;$x[]=$h;$cases[]=[$plan,$hs,[],$x,[],$guards,93680];
 $cases[]=[$plan,$hs,[],$catalog,[['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>'2000084849','local_hotel_id'=>null]],$guards,93680];
 $cases[]=[$plan,$hs,[],$catalog,[['supplier_namespace'=>'operator_315','external_hotel_id'=>'9999','local_hotel_id'=>93680]],$guards,93680];
 $x=$guards;$x['anex_hotel_decisions']=[['decision_status'=>'rejected']];$cases[]=[$plan,$hs,[],$catalog,[],$x,93680];
 $x=$guards;$x['anex_review_pair_exclusions']=[['catalog_hotel_id'=>93680]];$cases[]=[$plan,$hs,[],$catalog,[],$x,93680];
 $x=$guards;$x['anex_hotel_search_mappings'][0]['enabled']=0;$cases[]=[$plan,$hs,[],$catalog,[],$x,93680];
 $cases[]=[$plan,$hs,[],$catalog,[],$guards,null];
 foreach($cases as $n=>$args){$failed=false;try{hmca_guard(...$args);}catch(RuntimeException $e){$failed=true;}hmcc_require($failed,'adversarial_case_'.$n);}
 echo 'HMCA_SELFTEST_OK checks='.(count($cases)+1)."\n";exit(0);
}
hmcc_require(PHP_SAPI==='cli','cli');$dir=(string)getenv('MATCH_OPERATION_DIR');$source=(string)getenv('MATCH_SOURCE_SHA');hmcc_require(is_dir($dir)&&!is_link($dir)&&preg_match('/^[a-f0-9]{40}$/D',$source)===1,'operation');
$db=null;$committed=false;$commitAttempted=false;$inserted=0;$base=['operation'=>HMCA_OPERATION,'source_sha'=>$source,'provider_calls'=>0,'no_replay'=>true];
try {
 $res=json_decode((string)file_get_contents($dir.'/reservation.json'),true,32,JSON_THROW_ON_ERROR);hmcc_require(($res['operation']??'')===HMCA_OPERATION&&($res['source_sha']??'')===$source,'reservation');
 hmcc_require(hash_file('sha256',$dir.'/current.json')===HMCA_CURRENT_SHA&&hash_file('sha256',$dir.'/input.json')===HMCA_INPUT_SHA,'input_digest');
 $current=json_decode((string)file_get_contents($dir.'/current.json'),true,64,JSON_THROW_ON_ERROR);$input=json_decode((string)file_get_contents($dir.'/input.json'),true,64,JSON_THROW_ON_ERROR);$towns=array_values(array_filter($input['towns'],fn($t)=>(string)$t['id']==='17'));hmcc_require(count($towns)===1&&(string)$towns[0]['state']==='5'&&hmcc_norm($towns[0]['name'])==='бодрум','official_town');$plans=array_values(array_filter($current['new_candidate_plans'],fn($p)=>$p['read_only_eligible']));hmcc_require(count($plans)===1,'eligible_count');$plan=$plans[0];
 $rootValue=(string)getenv('ANYTOOUR_ROOT');$root=$rootValue!==''?realpath($rootValue):false;hmcc_require(is_string($root)&&basename($root)==='anytoour.ru','root');$f=$root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');hmcc_require(is_file($f),'db_bootstrap');require_once $f;
 require_once $dir.'/andromeda-hotel-resolver.php';require_once $dir.'/anex-search-mapping-registry.php';
 $db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);hmcc_require(strtoupper((string)$db->query("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='andromeda_hotel_identities'")->fetchColumn())==='INNODB','transaction_engine');
 $engines=hmcc_rows($db,"SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('andromeda_hotel_identities','catalog_hotels','hotel_aliases','anex_search_hotel_observations','anex_hotel_decisions','anex_hotel_search_mappings','anex_review_pair_exclusions')");hmcc_require(count($engines)===7,'guard_tables');foreach($engines as $engine)hmcc_require(strtoupper((string)$engine['ENGINE'])==='INNODB','guard_engine');
 hmcc_require(hmca_hash(hmcc_rows($db,'SHOW COLUMNS FROM andromeda_hotel_identities'))===hmca_hash($current['schemas']['andromeda_hotel_identities']),'schema_drift');
 $db->exec('SET SESSION innodb_lock_wait_timeout=10');$db->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');$db->beginTransaction();
 $all=hmcc_rows($db,'SELECT * FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id LIMIT 50001 FOR UPDATE');hmcc_require(count($all)<50001,'identity_cap');
 $guards=[];foreach(['anex_search_hotel_observations','anex_hotel_decisions','anex_hotel_search_mappings','anex_review_pair_exclusions'] as $t)$guards[$t]=hmcc_rows($db,'SELECT * FROM '.$t.' WHERE anex_hotel_id=39882 ORDER BY anex_hotel_id FOR UPDATE');
 $hs=hmcc_rows($db,'SELECT id,name,country_id,country_name,region_name,subregion_name,category,is_active FROM catalog_hotels WHERE country_id=4 ORDER BY id LIMIT 30001 FOR UPDATE');$aliases=hmcc_rows($db,'SELECT a.hotel_id,a.alias FROM hotel_aliases a JOIN catalog_hotels h ON h.id=a.hotel_id WHERE h.country_id=4 AND h.is_active=1 ORDER BY a.hotel_id,a.alias LIMIT 100001 FOR UPDATE');hmcc_require(count($hs)<30001&&count($aliases)<100001,'country_cap');
 $anex=AnyTourAnexSearchMappingRegistry::fromPdo($db);hmca_guard($plan,$hs,$aliases,$input['catalog'],$all,$guards,$anex->resolve('anex_online','39882','preview'));
 $before=[];foreach($all as $r)$before[$r['supplier_namespace'].':'.$r['external_hotel_id']]=hmca_hash($r);
 hmcc_save($dir.'/capture.json',$base+['current_sha256'=>HMCA_CURRENT_SHA,'input_sha256'=>HMCA_INPUT_SHA,'target'=>$plan['target'],'source'=>$plan['source'],'identity_hashes'=>$before,'anex_guards'=>$guards,'country_hotels'=>count($hs),'country_aliases'=>count($aliases)]);
 $evidence=['operation_id'=>HMCA_OPERATION,'source'=>$plan['source'],'target'=>$plan['target'],'reason'=>'unique_current_country_name_alias_and_official_geography','category'=>['source'=>5,'target'=>5],'geography'=>['status'=>'supported','country_id'=>4,'supplier_country_id'=>5,'town'=>'Бодрум','supplier_town_id'=>17],'anex_bridge'=>['id'=>39882,'local_hotel_id'=>93680,'effective'=>true],'current_report_sha256'=>HMCA_CURRENT_SHA,'source_result_sha256'=>$input['source_result_sha256'],'input_sha256'=>HMCA_INPUT_SHA];
 $ejson=json_encode(hmca_order($evidence),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);$ehash=hash('sha256',$ejson);$catalogHash=$input['raw_catalog_sha256'];
 hmcc_save($dir.'/plan.json',$base+['insert_only'=>true,'row'=>['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>'2000084849','local_hotel_id'=>93680,'decision_status'=>'accepted','catalog_sha256'=>$catalogHash,'evidence_sha256'=>$ehash,'evidence_json'=>$ejson]]);
 $s=$db->prepare("INSERT INTO andromeda_hotel_identities(supplier_namespace,external_hotel_id,local_hotel_id,decision_status,catalog_sha256,evidence_sha256,evidence_json) VALUES('andromeda_catalog','2000084849',93680,'accepted',?,?,?)");$s->execute([$catalogHash,$ehash,$ejson]);hmcc_require($s->rowCount()===1,'insert_count');$inserted=1;
 $after=hmcc_rows($db,'SELECT * FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id');$oldAfter=[];foreach($after as $r)if(!($r['supplier_namespace']==='andromeda_catalog'&&$r['external_hotel_id']==='2000084849'))$oldAfter[$r['supplier_namespace'].':'.$r['external_hotel_id']]=hmca_hash($r);
 hmcc_require(count($after)===count($all)+1&&$oldAfter===$before,'prior_rows_changed');
 hmcc_save($dir.'/pre-commit.json',$base+['inserted_uncommitted'=>1,'external_hotel_id'=>'2000084849','local_hotel_id'=>93680,'evidence_sha256'=>$ehash,'prior_identities_preserved'=>count($before)]);
 $commitAttempted=true;$db->commit();$committed=true;
 $db->exec('START TRANSACTION READ ONLY');$rows=hmcc_rows($db,"SELECT i.*,h.id AS existing_catalog_hotel_id FROM andromeda_hotel_identities i LEFT JOIN catalog_hotels h ON h.id=i.local_hotel_id WHERE i.decision_status IN ('accepted','rejected') ORDER BY i.supplier_namespace,i.external_hotel_id LIMIT 50001");hmcc_require(count($rows)<50001,'readback_cap');$projection=[];$written=null;
 foreach($rows as $r){$projection[]=['supplier_namespace'=>$r['supplier_namespace'],'external_hotel_id'=>$r['external_hotel_id'],'decision_status'=>$r['decision_status'],'catalog_hotel_id'=>$r['local_hotel_id'],'existing_catalog_hotel_id'=>$r['existing_catalog_hotel_id']];if($r['supplier_namespace']==='andromeda_catalog'&&$r['external_hotel_id']==='2000084849')$written=$r;}
 hmcc_require(is_array($written)&&$written['decision_status']==='accepted'&&(int)$written['local_hotel_id']===93680&&$written['catalog_sha256']===$catalogHash&&$written['evidence_sha256']===$ehash&&hash('sha256',$written['evidence_json'])===$ehash,'post_commit_row');
 $resolver=AnyTourAndromedaHotelResolver::fromRows($projection,hmca_hash($projection));$page=$resolver->apply(['provider'=>'andromeda','selection_enabled'=>false,'offers'=>[['provider'=>'andromeda','selection_enabled'=>false,'local_hotel_id'=>null,'supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>'2000084849']]]);hmcc_require($page['offers'][0]['local_hotel_id']===93680,'post_commit_resolver');
 $anex=AnyTourAnexSearchMappingRegistry::fromPdo($db);hmcc_require($anex->resolve('anex_online','39882','preview')===93680,'post_commit_anex');$db->rollBack();
 $out=$base+['state'=>'committed_verified','committed_at_utc'=>gmdate('c'),'database_writes'=>1,'mapping_writes'=>1,'accepted_delta'=>1,'unique_samo_local_delta'=>1,'triple_delta'=>1,'prior_identities_preserved'=>count($before),'prior_identity_hashes_sha256'=>hmca_hash($before),'row'=>$written,'effective_resolver_target'=>93680,'readback_verified'=>true];
}catch(Throwable $e){if($db instanceof PDO&&$db->inTransaction())$db->rollBack();$out=$base+['state'=>$committed?'committed_unverified':($commitAttempted?'commit_outcome_unknown':'rolled_back_or_prewrite_failed'),'reason'=>preg_replace('/[^a-z0-9_]/i','_',substr($e->getMessage(),0,140)),'database_writes'=>$committed?1:($commitAttempted?null:0),'mapping_writes'=>$committed?1:($commitAttempted?null:0),'insert_attempted'=>$inserted,'readback_verified'=>false];}
$hash=hmcc_save($dir.'/result.json',$out);hmcc_save($dir.'/receipt.json',$base+['state'=>$out['state'],'result_sha256'=>$hash,'mapping_writes'=>$out['mapping_writes'],'readback_verified'=>$out['readback_verified']]);echo hmcc_json(['state'=>$out['state'],'mapping_writes'=>$out['mapping_writes'],'result_sha256'=>$hash,'reason'=>$out['reason']??null]);exit($out['state']==='committed_verified'?0:2);
