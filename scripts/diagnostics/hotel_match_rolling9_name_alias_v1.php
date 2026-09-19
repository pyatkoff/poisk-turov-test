<?php
declare(strict_types=1);
// Exact pair-local spelling/location adjudication; never a global alias dictionary.
const R9_OP='hotel-match-rolling9-name-alias-1971-20260919-v1';
const R9_RELATED_SHA='022316636e8e981ea8496f793b5ece5e81e4d4caa831267e735a7ee1b0681b62';
const R9_FINAL_SHA='4b959f0796909cc3556d0ecb76d52aa5308d258586b96c7df2b669f564a414e3';
const R9_PAIRS=[1655=>367,9421=>1709,11241=>1255,37048=>97122,37884=>113292,45166=>148539,45259=>143442,45401=>81951,45402=>80872];
const R9_RULES=[
 1655=>['prefix'=>'SWISSOTEL','reason'=>'city_spelling_qusier_quseir','replace'=>['qusier'=>'quseir']],
 37048=>['prefix'=>'AMARINA JANNAH','reason'=>'aquapark_spacing','replace'=>['aqua park'=>'aquapark']],
 37884=>['prefix'=>'GRANADA','reason'=>'red_property_adult_wording','replace'=>[' 16 adult'=>' adults only']],
 45166=>['prefix'=>'AZUR WHITE','reason'=>'explicit_ras_el_hekma_location_suffix','suffix'=>' ras el hekma'],
 45259=>['prefix'=>'TIME MARINA','reason'=>'explicit_north_coast_location_suffix','suffix'=>' north coast'],
 45401=>['prefix'=>'AJIRA','reason'=>'boutique_property_hurghada_marina_suffix','suffix'=>' hurghada marina'],
 45402=>['prefix'=>'AJIRA','reason'=>'bay_property_hurghada_marina_suffix','suffix'=>' hurghada marina'],
];
$library=__DIR__.'/guard-library.php';
if(hash_file('sha256',$library)!=='990730d60f57aebf69f7f241bb08a09db7f3abb369d6b19880260ea729e517e5')throw new RuntimeException('guard_library_digest');
require_once $library;
function r9_load(string $path,string $sha):array{if(hash_file('sha256',$path)!==$sha)throw new RuntimeException('input_digest');return json_decode((string)file_get_contents($path),true,512,JSON_THROW_ON_ERROR);}
function r9_inputs(string $dir):array{
 $related=r9_load($dir.'/related.json',R9_RELATED_SHA);$final=r9_load($dir.'/plan.json',R9_FINAL_SHA);
 if($related['state']!=='completed_read_only'||$related['mapping_writes']!==0||count($related['dossiers'])!==22||$final['state']!=='planned_read_only'||$final['mapping_writes']!==0)throw new RuntimeException('input_state');
 $out=[];foreach(r54_input($dir.'/audit.json') as $p){$aid=$p['native_anex_id'];if(!isset(R9_PAIRS[$aid]))continue;
  $rr=array_values(array_filter($related['dossiers'],fn($x)=>$x['native_anex_id']===$aid));$ff=array_values(array_filter($final['dossiers'],fn($x)=>$x['native_anex_id']===$aid));
  if(count($rr)!==1||count($ff)!==1||R9_PAIRS[$aid]!==$p['hotel_id'])throw new RuntimeException('input_pair');$r=$rr[0];$f=$ff[0];
  if($r['hotel_id']!==$p['hotel_id']||$f['hotel_id']!==$p['hotel_id']||$r['prior_holds']!==['observed_name_needs_review']||$f['holds']!==['observed_name_needs_review']||$f['source_dossier_sha256']!==hash('sha256',r54_json($p)))throw new RuntimeException('dossier_binding');
  if($r['direct_operator_link']!==$p['operator_link']||$r['safe_to_write_now']!==false||$r['prefix_alternatives_truncated']!==false||count($r['native_profile']['observations'])!==1)throw new RuntimeException('saved_alias_evidence');
  $out[]=['anchor'=>$p,'held'=>$f,'related'=>$r];
 }
 if(count($out)!==9)throw new RuntimeException('exact_nine');return $out;
}
function r9_key(int $aid,string $raw):string{
 $name=r54_name($raw);$rule=R9_RULES[$aid]??null;if(!$rule)return $name;
 foreach($rule['replace']??[] as $old=>$new)$name=str_replace($old,$new,$name);
 if(isset($rule['suffix'])&&str_ends_with($name,$rule['suffix']))$name=substr($name,0,-strlen($rule['suffix']));return $name;
}
function r9_alternatives(PDO $db,array $items):array{
 $out=[];foreach($items as $item){$p=$item['anchor'];$aid=$p['native_anex_id'];if(!isset(R9_RULES[$aid]))continue;
  $out[$aid]=r54_rows($db,'SELECT id,name,country_id,region_name,subregion_name,latitude,longitude,is_active FROM catalog_hotels WHERE country_id=? AND name LIKE ? ORDER BY id LIMIT 101 FOR UPDATE',[$p['country_id'],'%'.R9_RULES[$aid]['prefix'].'%']);
 }return $out;
}
function r9_review(array $item,array $snapshot,array $alternatives):array{
 $p=$item['anchor'];$aid=$p['native_anex_id'];$lid=$p['hotel_id'];$d=r54_review($p,$snapshot);$original=$d['holds'];$reason=[];$proof=null;
 if(!isset(R9_RULES[$aid])){$reason[]='property_qualifier_corroboration_required';}
 else{
  $h=$d['current_local'];$obs=$d['saved_observation'];$expected=$item['related'];$oldObs=$expected['native_profile']['observations'][0];
  if(!$h||$h['name']!==$expected['target_local']['name']||!$obs||$obs['hotel_name']!==$oldObs['hotel_name'])$reason[]='raw_name_pair_changed';
  if(!$obs||(int)$obs['country_id']!==$p['country_id']||(int)$obs['anex_country_id']!==(int)$oldObs['anex_country_id'])$reason[]='alias_country_not_reconfirmed';
  if(!$h||!$obs||r9_key($aid,$h['name'])!==r9_key($aid,$obs['hotel_name']))$reason[]='pair_local_alias_not_equal';
  $alts=$alternatives[$aid]??[];$winners=[];
  if(!$alts||count($alts)>100)$reason[]='alternative_set_missing_or_truncated';
  foreach($alts as $a){if((int)$a['country_id']!==$p['country_id']){$reason[]='alternative_country_mismatch';continue;}if($h&&r9_key($aid,$a['name'])===r9_key($aid,$h['name']))$winners[]=(int)$a['id'];}
  if($winners!==[$lid])$reason[]='alias_not_unique_in_current_properties';
  if(!$reason){$proof=['pair'=>['native_anex_id'=>$aid,'hotel_id'=>$lid],'raw_local_name'=>$h['name'],'raw_native_name'=>$obs['hotel_name'],'rule'=>R9_RULES[$aid],'country_id'=>$p['country_id'],'anex_country_id'=>(int)$obs['anex_country_id'],'current_property_alternatives'=>$alts,'unique_alias_local_id'=>$lid,'related_evidence_sha256'=>R9_RELATED_SHA,'direct_link'=>$p['operator_link'],'scope'=>'this_exact_hotel_pair_only'];}
 }
 $remaining=$original;if($proof!==null)$remaining=array_values(array_filter($remaining,fn($r)=>$r!=='observed_name_needs_review'));
 $d['original_current_holds']=$original;$d['holds']=array_values(array_unique(array_merge($remaining,$reason)));$d['alias_adjudication']=$proof;$d['eligible_for_guarded_append']=$d['holds']===[];return $d;
}
function r9_fixture(array $item):array{
 $f=$item['held'];$s=array_fill_keys(['maps','decisions','exclusions','native','auto','observations','candidates','andromeda'],[]);$s['locals']=[$f['current_local']];
 foreach(['native'=>'native_catalog','auto'=>'auto_review','observations'=>'saved_observation'] as $k=>$v)if($f[$v]!==null)$s[$k]=[$f[$v]];
 foreach(['maps'=>'maps','decisions'=>'manual','exclusions'=>'exclusions','candidates'=>'saved_candidates'] as $k=>$v)$s[$k]=$f[$v];return $s;
}
function r9_selftest(string $dir):void{
 r54_selftest($dir.'/audit.json');$items=r9_inputs($dir);$n=0;$ok=function(bool $x)use(&$n){$n++;if(!$x)throw new RuntimeException('alias_test_'.$n);};$approved=0;
 foreach($items as $item){$p=$item['anchor'];$aid=$p['native_anex_id'];$lid=$p['hotel_id'];$s=r9_fixture($item);$alts=[$aid=>$item['related']['same_country_prefix_alternatives']];
  if(in_array($aid,[45401,45402],true)){$alts[$aid]=[];foreach($items as $other)if(in_array($other['anchor']['native_anex_id'],[45401,45402],true))$alts[$aid]=array_merge($alts[$aid],$other['related']['same_country_prefix_alternatives']);}
  $d=r9_review($item,$s,$alts);$ok($d['eligible_for_guarded_append']===isset(R9_RULES[$aid]));if(!isset(R9_RULES[$aid]))continue;$approved++;
  $ok($d['original_current_holds']===['observed_name_needs_review']);$ok($d['alias_adjudication']['raw_native_name']===$s['observations'][0]['hotel_name']);
  foreach(['maps','decisions','exclusions','candidates','auto'] as $t){$x=$s;$x[$t][]=$t==='auto'?['anex_hotel_id'=>$aid,'automated_reason'=>'protected_review']:['anex_hotel_id'=>$t==='maps'?999:$aid,'catalog_hotel_id'=>$t==='candidates'?999:$lid,'decision_status'=>'rejected'];$ok(!r9_review($item,$x,$alts)['eligible_for_guarded_append']);}
  $x=$s;$x['maps'][]=['anex_hotel_id'=>$aid,'catalog_hotel_id'=>999];$ok(!r9_review($item,$x,$alts)['eligible_for_guarded_append']);
  $x=$s;$x['observations'][0]['hotel_name'].=' ANNEX';$ok(!r9_review($item,$x,$alts)['eligible_for_guarded_append']);
  $x=$s;$x['observations'][0]['country_id']=999;$ok(!r9_review($item,$x,$alts)['eligible_for_guarded_append']);
  $x=$s;$x['observations'][0]['last_catalog_hotel_id']=999;$ok(!r9_review($item,$x,$alts)['eligible_for_guarded_append']);
  $x=$s;$x['locals'][0]['is_active']=0;$ok(!r9_review($item,$x,$alts)['eligible_for_guarded_append']);
  $x=$s;$x['locals'][0]['latitude']=0.1;$ok(!r9_review($item,$x,$alts)['eligible_for_guarded_append']);
  $y=$alts;$y[$aid][]=['id'=>999,'name'=>$s['locals'][0]['name'],'country_id'=>$p['country_id']];$ok(!r9_review($item,$s,$y)['eligible_for_guarded_append']);
  $ok(!r9_review($item,$s,[])['eligible_for_guarded_append']);
 }
 $ok($approved===7);$ok(r9_key(37884,'GRANADA LUXURY RED ADULTS ONLY')!==r9_key(37884,'GRANADA LUXURY BEACH HOTEL'));
 $ok(r9_key(45401,'AJIRA BOUTIQUE HOTEL')!==r9_key(45401,'AJIRA BAY HOTEL'));
 $ok(r9_key(11241,'TIMO DELUXE RESORT')!==r9_key(11241,'Timo Resort Hotel'));
 echo 'ROLLING9_NAME_ALIAS_SELFTEST_OK '.$n."\n";
}
if(($argv[1]??'')==='--self-test'){r9_selftest($argv[2]??__DIR__);exit;}
if(PHP_SAPI!=='cli'||($argv[1]??'')!=='--apply')throw new RuntimeException('disabled');
$dir=realpath((string)getenv('MATCH_OPERATION_DIR'));$root=realpath((string)getenv('ANYTOUR_ROOT'));if(!$dir||!$root||basename($root)!=='anytoour.ru')throw new RuntimeException('paths');
$res=json_decode((string)file_get_contents($dir.'/reservation.json'),true,512,JSON_THROW_ON_ERROR);
if(($res['operation']??'')!==R9_OP||($res['state']??'')!=='reserved_before_db_write'||($res['related_sha256']??'')!==R9_RELATED_SHA||($res['plan_sha256']??'')!==R9_FINAL_SHA||!preg_match('/^[0-9a-f]{40}$/D',$res['source_sha']??''))throw new RuntimeException('reservation');
$base=['operation'=>R9_OP,'source_sha'=>$res['source_sha'],'related_sha256'=>R9_RELATED_SHA,'plan_sha256'=>R9_FINAL_SHA,'audit_sha256'=>R54_AUDIT_SHA,'supplier_calls'=>0,'no_replay'=>true];
$db=null;$commitStarted=false;$committed=false;$written=[];$post=[];
try{
    r54_save($dir.'/execution-reservation.json',$res);$items=r9_inputs($dir.'/payload');
    $rf=$dir.'/payload/anex-search-mapping-registry.php';$raw=(string)file_get_contents($rf);if(sha1('blob '.strlen($raw)."\0".$raw)!==R54_REGISTRY_BLOB)throw new RuntimeException('registry_digest');require_once $rf;
    require_once (is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php');$db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');$db->beginTransaction();
    $snapshot=r54_current($db,array_column($items,'anchor'),true);$profiles=r9_alternatives($db,$items);$eligible=[];$held=[];
    foreach($items as $item){$d=r9_review($item,$snapshot,$profiles);if($d['eligible_for_guarded_append'])$eligible[]=$d;else $held[]=$d;}
    $clock=r54_rows($db,'SELECT UTC_TIMESTAMP AS db_utc_timestamp')[0]['db_utc_timestamp'];
    $mapHash=hash('sha256',r54_json($base+['eligible'=>$eligible,'match_class'=>R54_CLASS,'approval_policy'=>R54_POLICY,'scope'=>'preview']));
    r54_save($dir.'/precommit-plan.json',$base+['captured_at_utc'=>$clock,'eligible'=>$eligible,'held'=>$held,'mapping_digest'=>$mapHash]);
    $ins=$db->prepare('INSERT INTO anex_hotel_search_mappings (anex_hotel_id,catalog_hotel_id,match_class,scope,approval_policy,source_row_digest,mapping_digest,enabled) VALUES (?,?,?,?,?,?,?,1)');
    foreach($eligible as $d){$src=hash('sha256',r54_json($base+['adjudicated_current_evidence'=>$d]));$ins->execute([$d['native_anex_id'],$d['hotel_id'],R54_CLASS,'preview',R54_POLICY,$src,$mapHash]);if($ins->rowCount()!==1)throw new RuntimeException('insert_count');$written[]=['anex_hotel_id'=>$d['native_anex_id'],'catalog_hotel_id'=>$d['hotel_id'],'source_row_digest'=>$src,'mapping_digest'=>$mapHash,'has_accepted_andromeda'=>$d['has_accepted_andromeda']];}
    r54_save($dir.'/precommit-written.json',$base+['written_uncommitted'=>$written]);$commitStarted=true;$db->commit();$committed=true;r54_save($dir.'/commit-returned.json',$base+['committed_count'=>count($written)]);
    $reg=AnyTourAnexSearchMappingRegistry::fromPdo($db);
    foreach($written as $w){$rs=r54_rows($db,'SELECT anex_hotel_id,catalog_hotel_id,match_class,scope,approval_policy,source_row_digest,mapping_digest,enabled FROM anex_hotel_search_mappings WHERE anex_hotel_id=?',[$w['anex_hotel_id']]);if(count($rs)!==1)throw new RuntimeException('postcommit_row_count');$row=$rs[0];foreach(['catalog_hotel_id'=>$w['catalog_hotel_id'],'match_class'=>R54_CLASS,'scope'=>'preview','approval_policy'=>R54_POLICY,'source_row_digest'=>$w['source_row_digest'],'mapping_digest'=>$mapHash,'enabled'=>1] as $k=>$v)if((string)$row[$k]!== (string)$v)throw new RuntimeException('postcommit_contract');if($reg->resolve('anex_online',(string)$w['anex_hotel_id'],'preview')!==$w['catalog_hotel_id'])throw new RuntimeException('effective_resolver_readback');$post[]=$row;}
    $out=$base+['state'=>'committed_verified','current_checked_at_utc'=>$clock,'written_count'=>count($written),'triple_increment'=>count(array_filter($written,fn($x)=>$x['has_accepted_andromeda'])),'held_count'=>count($held),'held'=>$held,'written'=>$written,'post_commit_readback'=>$post,'mapping_writes'=>count($written),'database_writes'=>count($written)];
}catch(Throwable $e){$rollback=false;try{if($db&&$db->inTransaction()){$db->rollBack();$rollback=true;}}catch(Throwable $ignored){}$out=$base+['state'=>$committed?'committed_readback_failed':($commitStarted?'commit_outcome_unknown':'blocked_no_commit'),'reason'=>preg_match('/^[a-z0-9_]+$/D',$e->getMessage())?$e->getMessage():'database_or_runtime_error','sqlstate'=>$e instanceof PDOException?(string)$e->getCode():null,'driver_code'=>$e instanceof PDOException?($e->errorInfo[1]??null):null,'commit_started'=>$commitStarted,'commit_returned'=>$committed,'rollback_returned'=>$rollback,'attempted_rows'=>$written,'post_commit_readback'=>$post,'mapping_writes'=>$committed?count($written):($commitStarted?null:0),'database_writes'=>$committed?count($written):($commitStarted?null:0)];}
$sha=r54_save($dir.'/result.json',$out);r54_save($dir.'/receipt.json',['operation'=>R9_OP,'state'=>$out['state'],'result_sha256'=>$sha,'readback_verified'=>hash_file('sha256',$dir.'/result.json')===$sha,'mapping_writes'=>$out['mapping_writes'],'no_replay'=>true]);echo r54_json(['state'=>$out['state'],'written_count'=>$out['written_count']??null,'held_count'=>$out['held_count']??null,'result_sha256'=>$sha]);exit($out['state']==='committed_verified'?0:2);
