<?php
declare(strict_types=1);
// One reviewed pending/local-NULL transition only; never repairs accepted/conflict rows.
const HMK_OP='hotel-match-kalyon-pending-accept-1971-20260919-v1';
const HMK_CURRENT='780d4d81c277cc303b9482a611a525eeabe8a0af16f45566d0c1e9f802d2b98e';
const HMK_CATALOG='45fe75aca798b5e66fc2422206fa9620cc09c0a4711c014598e696e9a4d60606';
const HMK_ROW='1aea2cba2f45efda4cedb971be01deb77b26282d12cc9629c331184ba880ebf5';
const HMK_EVIDENCE='39a123b2ca1212e1ec48e6cb0f56d1b5918c3c374eef18ee3574a44b2e508172';
function hmk_need(bool $ok,string $why):void {if(!$ok)throw new RuntimeException($why);}
function hmk_json(mixed $v):string {return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function hmk_order(mixed $v):mixed {if(is_array($v)){if(!array_is_list($v))ksort($v,SORT_STRING);foreach($v as &$x)$x=hmk_order($x);unset($x);}return $v;}
function hmk_hash(mixed $v):string {return hash('sha256',hmk_json($v)."\n");}
function hmk_save(string $p,array $v):string {$b=hmk_json($v)."\n";$f=fopen($p,'xb');hmk_need(is_resource($f),'exclusive_record');try{hmk_need(fwrite($f,$b)===strlen($b)&&fflush($f),'record_write');if(function_exists('fsync'))hmk_need(fsync($f),'record_sync');}finally{fclose($f);}hmk_need(hash_file('sha256',$p)===hash('sha256',$b),'record_readback');return hash('sha256',$b);}
function hmk_norm(string $v):string {
 $v=function_exists('mb_strtolower')?mb_strtolower($v,'UTF-8'):strtolower(strtr($v,array_combine(preg_split('//u','АБВГДЕЁЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЫЬЭЮЯ',-1,PREG_SPLIT_NO_EMPTY),preg_split('//u','абвгдеёжзийклмнопрстуфхцчшщъыьэюя',-1,PREG_SPLIT_NO_EMPTY))));
 $v=strtr($v,['ё'=>'е','é'=>'e','è'=>'e','ê'=>'e','á'=>'a','à'=>'a','ä'=>'a','ö'=>'o','ü'=>'u','ç'=>'c','ş'=>'s','ğ'=>'g','ı'=>'i']);return trim(preg_replace('/[^\p{L}\p{N}]+/u',' ',$v)??'');
}
function hmk_names(string $v):array {
 $parts=[$v,preg_split('/\s*\(\s*(?:ex\.?|formerly|бывш\.?)\s*/iu',$v)[0]];preg_match_all('/\(\s*(?:ex\.?|formerly|бывш\.?)\s*([^)]*)/iu',$v,$m);foreach($m[1] as $x)foreach(preg_split('/[;|\/]/u',$x) as $s)$parts[]=$s;
 $out=[];foreach($parts as $s){$t=array_values(array_filter(explode(' ',hmk_norm($s)),fn($w)=>$w!==''&&!in_array($w,['hotel','отель'],true)));sort($t,SORT_STRING);if($t)$out[implode(' ',$t)]=true;}return array_keys($out);
}
function hmk_rows(PDO $db,string $sql,array $params=[],int $cap=50000):array {$s=$db->prepare($sql);$s->execute($params);$r=$s->fetchAll(PDO::FETCH_ASSOC);hmk_need(count($r)<=$cap,'read_cap');return $r;}
function hmk_guard(array $old,array $hotels,array $aliases,array $identities,array $guards,?int $anex,array $catalog,array $expected):array {
 hmk_need(($old['supplier_namespace']??null)==='andromeda_catalog'&&(string)($old['external_hotel_id']??'')==='567'&&($old['decision_status']??'')==='pending'&&array_key_exists('local_hotel_id',$old)&&$old['local_hotel_id']===null,'pending_only');
 hmk_need(hmk_hash($old)===HMK_ROW&&($old['evidence_sha256']??'')===HMK_EVIDENCE&&hash('sha256',(string)$old['evidence_json'])===HMK_EVIDENCE,'pending_row_drift');
 $prior=json_decode($old['evidence_json'],true,64,JSON_THROW_ON_ERROR);$keys=array_keys($prior);sort($keys);hmk_need($keys===['candidate_ids','reason','source','target_name']&&$prior['reason']==='no_unique_country_name'&&$prior['target_name']===null&&$prior['candidate_ids']===[17490,130534],'prior_protection');
 $sources=[];foreach($catalog['HOTELS'] as $h){$key=(string)$h['id'];hmk_need(!isset($sources[$key]),'duplicate_source');$sources[$key]=$h;}hmk_need(count($sources)===3260,'catalog_complete');$source=$sources['567']??null;
 hmk_need(is_array($source)&&(string)$source['stateKey']==='5'&&$source['state']==='Турция'&&$source['star']==='4'&&$source['starLName']==='4*'&&(string)$source['townKey']==='2000007752'&&hmk_norm($source['town'])==='фатих','source_facts');
 hmk_need(hmk_hash(hmk_order($source))===hmk_hash(hmk_order($prior['source'])),'source_drift');
 $towns=[];$townNames=[];foreach($catalog['TOWNTO'] as $t){$id=(string)$t['id'];hmk_need(!isset($towns[$id]),'duplicate_town');$towns[$id]=$t;if((string)$t['state']==='5')foreach([$t['name'],$t['lName']??''] as $n)if($n!=='')$townNames[hmk_norm($n)][$id]=true;}
 $fatih=$towns['2000007752']??[];$sultan=$towns['2000003642']??[];$cesme=$towns['647']??[];
 foreach([$fatih,$sultan,$cesme] as $t)hmk_need((string)($t['state']??'')==='5'&&($t['stateName']??'')==='Турция','town_country');
 hmk_need(hmk_norm($fatih['name'])==='фатих'&&(string)($fatih['Region']??'')==='32'&&hmk_norm($fatih['RegionName'])==='стамбул','source_parent');
 hmk_need(hmk_norm($sultan['name'])==='султанахмет'&&(string)($sultan['Region']??'')==='32'&&hmk_norm($sultan['RegionName'])==='стамбул','target_parent');
 hmk_need(hmk_norm($cesme['name'])==='чешме'&&(string)($cesme['Region']??'')==='12'&&hmk_norm($cesme['RegionName'])==='измир','competitor_parent');
 hmk_need(array_keys($townNames['чешме']??[])===[647],'competitor_town_unique');
 $by=[];$index=[];foreach($hotels as $h){$id=(int)$h['id'];hmk_need(!isset($by[$id]),'duplicate_local');$by[$id]=$h;if((int)$h['country_id']===4&&(int)$h['is_active']===1)foreach(hmk_names($h['name']) as $k)$index[$k][$id]=true;}
 foreach($aliases as $a){$id=(int)$a['hotel_id'];if(isset($by[$id])&&(int)$by[$id]['country_id']===4&&(int)$by[$id]['is_active']===1)foreach(hmk_names($a['alias']) as $k)$index[$k][$id]=true;}
 $sourceKeys=array_unique(array_merge(hmk_names($source['name']),hmk_names($source['lName'])));$local=[];foreach($sourceKeys as $k)foreach(array_keys($index[$k]??[]) as $id)$local[$id]=true;$localIds=array_keys($local);sort($localIds,SORT_NUMERIC);
 hmk_need($localIds===[17490,130534],'full_country_competition');
 foreach($localIds as $id)hmk_need(isset($expected[$id])&&hmk_hash($by[$id])===hmk_hash($expected[$id]),'local_facts_drift');
 $target=$by[17490];$other=$by[130534];hmk_need((int)$target['category']===4&&(int)$target['is_active']===1&&hmk_norm($target['region_name'])===hmk_norm($fatih['RegionName'])&&hmk_norm($target['subregion_name'])===hmk_norm($sultan['name']),'target_geography_category');
 hmk_need(hmk_norm($other['region_name'])===hmk_norm($cesme['name'])&&(string)($cesme['Region']??'')!==(string)($fatih['Region']??''),'competitor_not_resolved');
 $targetKeys=hmk_names($target['name']);foreach($aliases as $a)if((int)$a['hotel_id']===17490)$targetKeys=array_merge($targetKeys,hmk_names($a['alias']));
 $reverse=[];foreach($sources as $id=>$h)if((string)$h['stateKey']==='5'&&array_intersect($targetKeys,array_merge(hmk_names($h['name']),hmk_names($h['lName']??''))))$reverse[]=(string)$id;
 hmk_need($reverse===['567'],'reverse_source_competition');$sourceCount=0;
 foreach($identities as $r){hmk_need($r['local_hotel_id']===null||(int)$r['local_hotel_id']!==17490,'target_occupied');if($r['supplier_namespace']==='andromeda_catalog'&&(string)$r['external_hotel_id']==='567'){++$sourceCount;hmk_need(hmk_hash($r)===HMK_ROW,'current_row_drift');}}
 hmk_need($sourceCount===1,'source_count');hmk_need($anex===17490,'effective_anex');
 hmk_need(($guards['anex_hotel_decisions']??null)===[]&&($guards['anex_review_pair_exclusions']??null)===[],'manual_or_exclusion');
 $ms=$guards['anex_hotel_search_mappings']??[];hmk_need(count($ms)===1,'anex_count');$m=$ms[0];
 hmk_need((int)$m['anex_hotel_id']===28837&&(int)$m['catalog_hotel_id']===17490&&(int)$m['enabled']===1&&$m['scope']==='preview'&&$m['match_class']==='exact'&&$m['approval_policy']==='owner_exact_and_strong_20260908'&&$m['mapping_digest']==='fa81ffe74639012e4af6d678e62e01106a04737a52dcc8979f6ffb2fea074a96','anex_drift');
 return ['source'=>$source,'target'=>$target,'all_name_competitors'=>[$target,$other],'reverse_source_ids'=>$reverse,'official_towns'=>[$fatih,$sultan,$cesme],'geography'=>'unique_same_country_parent_with_explicit_foreign_parent_competitor','source_coordinates'=>null,'independent_anex'=>['anex_id'=>28837,'local_id'=>17490,'effective'=>true],'prior_evidence'=>$prior];
}
$dir=(string)getenv('MATCH_OPERATION_DIR');
if(in_array('--self-test',$argv??[],true)) {
 $c=json_decode((string)file_get_contents($dir.'/current.json'),true,64,JSON_THROW_ON_ERROR);$cat=json_decode((string)file_get_contents($dir.'/catalog.json'),true,64,JSON_THROW_ON_ERROR);
 hmk_need(hash_file('sha256',$dir.'/current.json')===HMK_CURRENT&&hash_file('sha256',$dir.'/catalog.json')===HMK_CATALOG,'fixture_digest');
 $row=array_values(array_filter($c['rows'],fn($r)=>$r['external_hotel_id']==='567'))[0]['current_row'];$old=[];foreach(['supplier_namespace','external_hotel_id','local_hotel_id','decision_status','catalog_sha256','evidence_sha256'] as $k)$old[$k]=$row[$k];$old['evidence_json']=hmk_json($row['evidence']);$old['created_at']=$row['created_at'];hmk_need(hmk_hash($old)===HMK_ROW,'reconstructed_row');
 $expected=[];foreach($c['country_hotels'] as $h)if(in_array((int)$h['id'],[17490,130534],true))$expected[(int)$h['id']]=$h;
 $guards=[];foreach($c['anex_guards'] as $k=>$rows)$guards[$k]=array_values(array_filter($rows,fn($r)=>(int)$r['anex_hotel_id']===28837||(int)($r['catalog_hotel_id']??0)===17490));
 $args=[$old,$c['country_hotels'],$c['country_aliases'],[$old],$guards,17490,$cat,$expected];$proof=hmk_guard(...$args);$tests=1;
 $cases=[];
 foreach(['accepted','conflict','rejected','manual',''] as $v){$x=$args;$x[0]['decision_status']=$v;$cases[]=$x;}
 foreach([0,17490,130534] as $v){$x=$args;$x[0]['local_hotel_id']=$v;$cases[]=$x;}
 foreach(['supplier_namespace'=>'operator_315','external_hotel_id'=>'5464','evidence_sha256'=>str_repeat('0',64),'catalog_sha256'=>str_repeat('0',64)] as $k=>$v){$x=$args;$x[0][$k]=$v;$cases[]=$x;}
 $x=$args;$x[3][]=['supplier_namespace'=>'operator_5','external_hotel_id'=>'99','local_hotel_id'=>17490];$cases[]=$x;
 $x=$args;$x[3]=[];$cases[]=$x;$x=$args;$x[5]=null;$cases[]=$x;
 foreach(['anex_hotel_decisions','anex_review_pair_exclusions'] as $k){$x=$args;$x[4][$k]=[['catalog_hotel_id'=>17490]];$cases[]=$x;}
 $x=$args;$x[4]['anex_hotel_search_mappings'][0]['enabled']=0;$cases[]=$x;
 foreach(['is_active'=>0,'country_id'=>1,'category'=>3,'region_name'=>'Чешме','name'=>'KALYON SUITE'] as $k=>$v){$x=$args;foreach($x[1] as &$h)if((int)$h['id']===17490)$h[$k]=$v;unset($h);$cases[]=$x;}
 $x=$args;$h=$expected[17490];$h['id']=999999;$x[1][]=$h;$cases[]=$x;
 $x=$args;$x[2][]=['hotel_id'=>1229,'alias'=>'KALYON','source'=>'test'];$cases[]=$x;
 $x=$args;foreach($x[6]['TOWNTO'] as &$t)if((string)$t['id']==='647')$t['Region']=32;unset($t);$cases[]=$x;
 $x=$args;foreach($x[6]['TOWNTO'] as &$t)if((string)$t['id']==='2000007752')unset($t['Region']);unset($t);$cases[]=$x;
 $x=$args;foreach($x[6]['HOTELS'] as &$h)if((string)$h['id']==='5464'){$h['name']='Kalyon';$h['lName']='Kalyon';}unset($h);$cases[]=$x;
 foreach($cases as $i=>$x){$fail=false;try{hmk_guard(...$x);}catch(Throwable $e){$fail=true;}hmk_need($fail,'negative_'.(string)$i);++$tests;}
 hmk_need(!array_intersect(hmk_names('HAN HOTEL'),hmk_names('HAN SUITE HOTEL')),'suite_qualifier');++$tests;
 echo 'HMK_SELFTEST_OK checks='.$tests."\n";exit(0);
}
hmk_need(PHP_SAPI==='cli'&&is_dir($dir)&&!is_link($dir)&&basename($dir)===HMK_OP,'operation_dir');$sha=(string)getenv('MATCH_SOURCE_SHA');hmk_need(preg_match('/^[a-f0-9]{40}$/D',$sha)===1,'source_sha');
$db=null;$committed=false;$attempted=false;$updated=false;$base=['operation'=>HMK_OP,'source_sha'=>$sha,'provider_calls'=>0,'no_replay'=>true];
try {
 $res=json_decode((string)file_get_contents($dir.'/reservation.json'),true,32,JSON_THROW_ON_ERROR);hmk_need($res['operation']===HMK_OP&&$res['source_sha']===$sha&&$res['reader_sha256']===hash_file('sha256',__FILE__),'reservation');
 hmk_need(hash_file('sha256',$dir.'/current.json')===HMK_CURRENT&&hash_file('sha256',$dir.'/catalog.json')===HMK_CATALOG,'input_digest');
 $c=json_decode((string)file_get_contents($dir.'/current.json'),true,64,JSON_THROW_ON_ERROR);$cat=json_decode((string)file_get_contents($dir.'/catalog.json'),true,64,JSON_THROW_ON_ERROR);$expected=[];foreach($c['country_hotels'] as $h)if(in_array((int)$h['id'],[17490,130534],true))$expected[(int)$h['id']]=$h;
 foreach(['andromeda-hotel-resolver.php','anex-search-mapping-registry.php'] as $n){hmk_need(isset($res['dependencies'][$n])&&hash_file('sha256',$dir.'/'.$n)===$res['dependencies'][$n],'dependency_hash');require_once $dir.'/'.$n;}
 $root=realpath((string)getenv('ANYTOOUR_ROOT'));hmk_need(is_string($root)&&basename($root)==='anytoour.ru','root');$bootstrap=$root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');hmk_need(is_file($bootstrap),'bootstrap');require_once $bootstrap;
 $db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
 $eng=hmk_rows($db,"SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('andromeda_hotel_identities','catalog_hotels','hotel_aliases','anex_hotel_decisions','anex_hotel_search_mappings','anex_review_pair_exclusions')");hmk_need(count($eng)===6,'guard_tables');foreach($eng as $e)hmk_need(strtoupper((string)$e['ENGINE'])==='INNODB','guard_engine');
 $db->exec('SET SESSION innodb_lock_wait_timeout=10');$db->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');$db->beginTransaction();
 $all=hmk_rows($db,'SELECT * FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id LIMIT 50001 FOR UPDATE');$oldRows=array_values(array_filter($all,fn($r)=>$r['supplier_namespace']==='andromeda_catalog'&&(string)$r['external_hotel_id']==='567'));hmk_need(count($oldRows)===1,'old_count');$old=$oldRows[0];
 $hs=hmk_rows($db,'SELECT id,name,country_id,country_name,region_name,subregion_name,category,latitude,longitude,is_active FROM catalog_hotels WHERE country_id=4 ORDER BY id LIMIT 30001 FOR UPDATE',[],30000);
 $aliases=hmk_rows($db,'SELECT a.hotel_id,a.alias,a.source FROM hotel_aliases a JOIN catalog_hotels h ON h.id=a.hotel_id WHERE h.country_id=4 ORDER BY a.hotel_id,a.alias,a.source LIMIT 100001 FOR UPDATE',[],100000);
 $guards=[];foreach(['anex_hotel_search_mappings','anex_hotel_decisions','anex_review_pair_exclusions'] as $t)$guards[$t]=hmk_rows($db,'SELECT * FROM '.$t.' WHERE anex_hotel_id=28837 OR catalog_hotel_id=17490 ORDER BY anex_hotel_id,catalog_hotel_id LIMIT 10001 FOR UPDATE',[],10000);
 $anex=AnyTourAnexSearchMappingRegistry::fromPdo($db);$proof=hmk_guard($old,$hs,$aliases,$all,$guards,$anex->resolve('anex_online','28837','preview'),$cat,$expected);
 $before=[];foreach($all as $r){$k=$r['supplier_namespace'].':'.$r['external_hotel_id'];hmk_need(!isset($before[$k]),'duplicate_identity');$before[$k]=hmk_hash($r);}hmk_save($dir.'/capture.json',$base+['prior_row'=>$old,'identity_hashes'=>$before,'anex_guards'=>$guards,'proof'=>$proof,'current_sha256'=>HMK_CURRENT,'catalog_sha256'=>HMK_CATALOG]);
 $evidence=['operation_id'=>HMK_OP,'rule'=>'unique_current_exact_name_with_official_parent_geography','prior_row_sha256'=>HMK_ROW,'prior_catalog_sha256'=>$old['catalog_sha256'],'prior_evidence_sha256'=>HMK_EVIDENCE,'current_read_sha256'=>HMK_CURRENT,'catalog_sha256'=>HMK_CATALOG,'proof'=>$proof];$ej=hmk_json($evidence);$eh=hash('sha256',$ej);
 hmk_save($dir.'/plan.json',$base+['pending_only'=>true,'expected_previous_row_sha256'=>HMK_ROW,'supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>'567','local_hotel_id'=>17490,'decision_status'=>'accepted','catalog_sha256'=>HMK_CATALOG,'evidence_sha256'=>$eh,'evidence_json'=>$ej]);
 $s=$db->prepare("UPDATE andromeda_hotel_identities SET local_hotel_id=17490,decision_status='accepted',catalog_sha256=?,evidence_sha256=?,evidence_json=? WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id='567' AND decision_status='pending' AND local_hotel_id IS NULL AND catalog_sha256=? AND evidence_sha256=? AND evidence_json=?");
 $s->execute([HMK_CATALOG,$eh,$ej,$old['catalog_sha256'],HMK_EVIDENCE,$old['evidence_json']]);hmk_need($s->rowCount()===1,'conditional_update_count');$updated=true;
 $after=hmk_rows($db,'SELECT * FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id');$preserved=[];foreach($after as $r){$k=$r['supplier_namespace'].':'.$r['external_hotel_id'];if($k!=='andromeda_catalog:567')$preserved[$k]=hmk_hash($r);}
 $others=$before;unset($others['andromeda_catalog:567']);hmk_need(count($after)===count($all)&&$others===$preserved,'other_identities_changed');
 hmk_save($dir.'/pre-commit.json',$base+['updated_uncommitted'=>1,'evidence_sha256'=>$eh,'prior_identities_preserved'=>count($others),'prior_identity_hashes_sha256'=>hmk_hash($others)]);
 $attempted=true;$db->commit();$committed=true;$db->exec('START TRANSACTION READ ONLY');
 $rows=hmk_rows($db,"SELECT i.*,h.id AS existing_catalog_hotel_id FROM andromeda_hotel_identities i LEFT JOIN catalog_hotels h ON h.id=i.local_hotel_id WHERE i.decision_status IN ('accepted','rejected') ORDER BY i.supplier_namespace,i.external_hotel_id LIMIT 50001");$projection=[];$written=null;
 foreach($rows as $r){$projection[]=['supplier_namespace'=>$r['supplier_namespace'],'external_hotel_id'=>$r['external_hotel_id'],'decision_status'=>$r['decision_status'],'catalog_hotel_id'=>$r['local_hotel_id'],'existing_catalog_hotel_id'=>$r['existing_catalog_hotel_id']];if($r['supplier_namespace']==='andromeda_catalog'&&(string)$r['external_hotel_id']==='567')$written=$r;}
 hmk_need(is_array($written)&&(int)$written['local_hotel_id']===17490&&$written['decision_status']==='accepted'&&$written['catalog_sha256']===HMK_CATALOG&&$written['evidence_sha256']===$eh&&hash('sha256',$written['evidence_json'])===$eh,'post_commit_row');
 $resolver=AnyTourAndromedaHotelResolver::fromRows($projection,hmk_hash($projection));$page=$resolver->apply(['provider'=>'andromeda','selection_enabled'=>false,'offers'=>[['provider'=>'andromeda','selection_enabled'=>false,'local_hotel_id'=>null,'supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>'567']]]);hmk_need($page['offers'][0]['local_hotel_id']===17490,'post_commit_resolver');
 $anex=AnyTourAnexSearchMappingRegistry::fromPdo($db);hmk_need($anex->resolve('anex_online','28837','preview')===17490,'post_commit_anex');$counts=hmk_rows($db,'SELECT decision_status,COUNT(*) AS n FROM andromeda_hotel_identities GROUP BY decision_status ORDER BY decision_status');$db->rollBack();
 $out=$base+['state'=>'committed_verified','committed_at_utc'=>gmdate('c'),'database_writes'=>1,'mapping_writes'=>1,'accepted_delta'=>1,'pending_delta'=>-1,'unique_samo_local_delta'=>1,'triple_delta'=>1,'prior_identities_preserved'=>count($others),'prior_identity_hashes_sha256'=>hmk_hash($others),'identity_status_counts'=>$counts,'row'=>$written,'effective_resolver_target'=>17490,'anex_effective_target'=>17490,'readback_verified'=>true];
}catch(Throwable $e){if($db instanceof PDO&&$db->inTransaction())$db->rollBack();$reason=$e instanceof RuntimeException&&preg_match('/^[a-z0-9_]{1,80}$/D',$e->getMessage())?$e->getMessage():'operation_failed';$out=$base+['state'=>$committed?'committed_unverified':($attempted?'commit_outcome_unknown':'rolled_back_or_prewrite_failed'),'reason'=>$reason,'database_writes'=>$committed?1:($attempted?null:0),'mapping_writes'=>$committed?1:($attempted?null:0),'update_attempted'=>$updated,'readback_verified'=>false];}
$h=hmk_save($dir.'/result.json',$out);hmk_save($dir.'/receipt.json',$base+['state'=>$out['state'],'result_sha256'=>$h,'mapping_writes'=>$out['mapping_writes'],'readback_verified'=>$out['readback_verified']]);echo hmk_json(['state'=>$out['state'],'mapping_writes'=>$out['mapping_writes'],'result_sha256'=>$h,'reason'=>$out['reason']??null])."\n";exit($out['state']==='committed_verified'?0:2);
