<?php
declare(strict_types=1);
if(!defined('FC_LIBRARY_ONLY'))define('FC_LIBRARY_ONLY',true);
require_once __DIR__.'/hotel_match_anex_andromeda_mutual_graph_review.php';

const HMSU_OPERATION='hotel-match-saved-tourvisor-union-current-review-1971-20260912-v1';
const HMSU_EVIDENCE_SHA256='3abb228949ea1f60656c2ddfe68bcfc68897e05f01866a08c02faac35efed9bb';
const HMSU_COUNTRY_ID=4;
const HMSU_COORD_BLOCK_M=5000.0;
const HMSU_COORD_DIRECT_M=1000.0;

function hmsu_variants(string $name):array{
 $raw=[$name,(string)(preg_replace('/\s*\([^)]*\)\s*/u',' ',$name)??$name)];
 if(preg_match_all('/\((?:\s*(?:EX\.|EX|ЕХ\.|ЕХ)\s*)?([^)]{2,120})\)/iu',$name,$m))foreach($m[1]as$x)$raw[]=trim((string)$x);
 $out=[];foreach($raw as$x){$x=trim((string)$x);if($x!=='')$out[$x]=true;}return array_keys($out);
}
function hmsu_latin(string $v):string{
 $v=fc_norm($v);return strtr($v,['а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ж'=>'zh','з'=>'z','и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'h','ц'=>'c','ч'=>'ch','ш'=>'sh','щ'=>'sch','ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya']);
}
function hmsu_key(string $name):string{
 $drop=['hotel'=>1,'hotels'=>1,'resort'=>1,'resorts'=>1,'spa'=>1];$out=[];
 foreach(explode(' ',hmsu_latin($name))as$t)if($t!==''&&!isset($drop[$t])&&$t!=='ex')$out[]=$t;
 return implode(' ',$out);
}
function hmsu_keys(array $names):array{
 $out=[];foreach($names as$n)foreach(hmsu_variants((string)$n)as$v){$k=hmsu_key($v);if($k!==''&&count(explode(' ',$k))>=2)$out[$k]=true;}return array_keys($out);
}
function hmsu_evidence():array{
 if(!defined('HMSU_EVIDENCE_B64'))throw new RuntimeException('evidence_not_embedded');$raw=base64_decode((string)constant('HMSU_EVIDENCE_B64'),true);
 if(!is_string($raw)||hash('sha256',$raw)!==HMSU_EVIDENCE_SHA256)throw new RuntimeException('evidence_sha_mismatch');$d=json_decode($raw,true,512,JSON_THROW_ON_ERROR);$s=$d['summary']??[];
 if(($d['v']??null)!==1||(int)($d['country_id']??0)!==4||(int)($d['tourvisor_operator_id']??0)!==13||(int)($s['slice_count']??0)!==6||(int)($s['unique_tourvisor_hotels']??0)!==108||($s['recurrence_histogram']??null)!==['1'=>51,'2'=>15,'3'=>11,'4'=>20,'5'=>5,'6'=>6]||count($d['hotels']??[])!==108)throw new RuntimeException('evidence_contract_changed');return$d;
}
function hmsu_index(array $ev,array $hotels,array $names):array{
 $idx=[];$rec=[];$evName=[];foreach($ev['hotels']as$h){$id=(int)($h[0]??0);if(!isset($hotels[$id])||(int)$hotels[$id]['country_id']!==4)continue;$rec[$id]=(int)($h[2]??0);$all=array_merge([(string)($h[1]??'')],$names[$id]??[]);$evName[$id]=(string)($h[1]??'');foreach(hmsu_keys($all)as$k)$idx[$k][$id]=true;}return[$idx,$rec,$evName];
}
function hmsu_candidate(array $source,array $idx):array{
 $ids=[];foreach(hmsu_keys($source['names']??[])as$k)foreach(array_keys($idx[$k]??[])as$id)$ids[(int)$id]=true;$ids=array_map('intval',array_keys($ids));sort($ids,SORT_NUMERIC);return$ids;
}
function hmsu_classify(array $source,array $target,int $recurrence):array{
 $distance=fc_dist($source['latitude']??null,$source['longitude']??null,$target['latitude']??null,$target['longitude']??null);$place=fc_place($source['places']??[],[(string)($target['region_name']??''),(string)($target['subregion_name']??'')]);$direct=$place||($distance!==null&&$distance<=HMSU_COORD_DIRECT_M);
 if($distance!==null&&$distance>HMSU_COORD_BLOCK_M)return['bucket'=>'hard_conflict','reason'=>'saved_tv_exact_coordinate_conflict_gt_5km','distance_m'=>(int)round($distance),'direct_geo'=>$direct];
 if($recurrence>=2)return['bucket'=>'prepared','reason'=>'saved_tv_exact_recurrent_2plus_dates','distance_m'=>$distance===null?null:(int)round($distance),'direct_geo'=>$direct];
 if($recurrence===1&&$direct)return['bucket'=>'prepared','reason'=>'saved_tv_exact_one_date_plus_direct_geo','distance_m'=>$distance===null?null:(int)round($distance),'direct_geo'=>true];
 return['bucket'=>'needs_extra_evidence','reason'=>'saved_tv_exact_one_date_without_direct_geo','distance_m'=>$distance===null?null:(int)round($distance),'direct_geo'=>$direct];
}
function hmsu_review(PDO $db,array $ev,string $operation=HMSU_OPERATION):array{
 if($operation!==HMSU_OPERATION)throw new RuntimeException('operation_scope');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
 try{
  $coverage=fc_coverage($db);$an=hmamgr_anex_rows($db);$and=hmamgr_andromeda_rows($db);[$hotels,$names]=mbr_catalog($db);[$idx,$rec,$evName]=hmsu_index($ev,$hotels,$names);
  $manual=array_fill_keys(array_map('intval',$db->query('SELECT anex_hotel_id FROM anex_hotel_decisions')->fetchAll(PDO::FETCH_COLUMN)),true);$anyMap=array_fill_keys(array_map('intval',$db->query('SELECT anex_hotel_id FROM anex_hotel_search_mappings')->fetchAll(PDO::FETCH_COLUMN)),true);
  $excluded=[];foreach($db->query('SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions')->fetchAll(PDO::FETCH_ASSOC)as$r)$excluded[(int)$r['anex_hotel_id']][(int)$r['catalog_hotel_id']]=true;
  $andStatus=[];foreach($db->query("SELECT external_hotel_id,decision_status FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog'")->fetchAll(PDO::FETCH_ASSOC)as$r)$andStatus[(string)$r['external_hotel_id']]=(string)$r['decision_status'];
  $anClaims=[];foreach($db->query('SELECT anex_hotel_id,catalog_hotel_id FROM anex_hotel_search_mappings WHERE catalog_hotel_id IS NOT NULL')->fetchAll(PDO::FETCH_ASSOC)as$r)$anClaims[(int)$r['catalog_hotel_id']][]=(int)$r['anex_hotel_id'];foreach($db->query("SELECT anex_hotel_id,catalog_hotel_id FROM anex_hotel_decisions WHERE decision_status='accepted' AND catalog_hotel_id IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC)as$r)$anClaims[(int)$r['catalog_hotel_id']][]=(int)$r['anex_hotel_id'];
  $andClaims=[];foreach($db->query("SELECT external_hotel_id,local_hotel_id FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC)as$r)$andClaims[(int)$r['local_hotel_id']][]=(string)$r['external_hotel_id'];
  $stats=['target_universe'=>108,'current_targets'=>count($rec),'unresolved_anex_turkey'=>0,'unresolved_andromeda_turkey'=>0,'examined'=>0,'exact_unique'=>0,'exact_ambiguous'=>0,'no_exact'=>0,'prepared'=>0,'prepared_anex'=>0,'prepared_andromeda'=>0,'prepared_live'=>0,'needs_extra'=>0,'hard_conflict'=>0,'target_occupancy_block'=>0,'pair_exclusion_block'=>0,'one_to_one_demotions'=>0,'star_mismatch_annotated'=>0];$rows=[];
  foreach([['anex',$an],['andromeda',$and]]as[$provider,$set])foreach($set as$id=>$source){
   if((int)$source['country_id']!==4||$source['local_ids'])continue;if($provider==='anex'&&(isset($manual[(int)$id])||isset($anyMap[(int)$id])))continue;if($provider==='andromeda'&&($andStatus[(string)$id]??'')!=='pending')continue;
   $stats[$provider==='anex'?'unresolved_anex_turkey':'unresolved_andromeda_turkey']++;$stats['examined']++;$ids=hmsu_candidate($source,$idx);$base=['provider'=>$provider,'external_id'=>(string)$id,'live'=>(bool)$source['live'],'observation_count'=>(int)$source['observation_count'],'source_names'=>$source['names'],'source_places'=>$source['places'],'candidate_target_ids'=>$ids];
   if(!$ids){$rows[]=$base+['bucket'=>'needs_extra_evidence','reason'=>'no_exact_saved_tv_target'];$stats['no_exact']++;continue;}if(count($ids)!==1){$rows[]=$base+['bucket'=>'needs_extra_evidence','reason'=>'saved_tv_exact_ambiguous'];$stats['exact_ambiguous']++;continue;}$stats['exact_unique']++;$target=$ids[0];$d=hmsu_classify($source,$hotels[$target],$rec[$target]??0);
   if($provider==='anex'&&isset($excluded[(int)$id][$target])){$d=['bucket'=>'hard_conflict','reason'=>'pair_exclusion_protected','distance_m'=>$d['distance_m']??null,'direct_geo'=>$d['direct_geo']??false];$stats['pair_exclusion_block']++;}
   $other=$provider==='anex'?hmigr_other($anClaims[$target]??[],(int)$id):hmigr_other($andClaims[$target]??[],(string)$id);if($d['bucket']==='prepared'&&$other){$d=['bucket'=>'needs_extra_evidence','reason'=>'same_provider_target_occupied','distance_m'=>$d['distance_m']??null,'direct_geo'=>$d['direct_geo']??false];$stats['target_occupancy_block']++;}
   $sourceCat=$source['category']??null;$targetCat=$hotels[$target]['category']??null;$starMismatch=$sourceCat!==null&&$targetCat!==null&&(int)$sourceCat!==(int)$targetCat;if($starMismatch)$stats['star_mismatch_annotated']++;
   $rows[]=$base+$d+['target_local_hotel_id'=>$target,'target_name'=>$hotels[$target]['name'],'tourvisor_saved_name'=>$evName[$target]??null,'tv_date_recurrence'=>$rec[$target]??0,'star_mismatch_annotation'=>$starMismatch];
  }
  $groups=[];foreach($rows as$i=>$r)if(($r['bucket']??'')==='prepared')$groups[$r['provider'].':'.$r['target_local_hotel_id']][]=$i;foreach($groups as$ids)if(count($ids)>1)foreach($ids as$i){$rows[$i]['bucket']='needs_extra_evidence';$rows[$i]['reason']='same_provider_saved_tv_target_collision';$stats['one_to_one_demotions']++;}
  $prepared=[];$near=[];$hard=[];foreach($rows as$r){if($r['bucket']==='prepared'){$prepared[]=$r;$stats['prepared']++;$stats[$r['provider']==='anex'?'prepared_anex':'prepared_andromeda']++;if($r['live'])$stats['prepared_live']++;}elseif($r['bucket']==='hard_conflict'){$hard[]=$r;$stats['hard_conflict']++;}else{$near[]=$r;$stats['needs_extra']++;}}
  $sort=static fn($a,$b)=>(($b['live']??false)<=>($a['live']??false))?:((int)($b['observation_count']??0)<=>(int)($a['observation_count']??0))?:((int)($b['tv_date_recurrence']??0)<=>(int)($a['tv_date_recurrence']??0))?:strcmp((string)$a['external_id'],(string)$b['external_id']);usort($prepared,$sort);usort($near,$sort);usort($hard,$sort);$db->commit();
  return['status'=>'completed','operation_id'=>$operation,'mode'=>'current_turkey_saved_anex_only_tourvisor_union_read_only','database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'anex_calls'=>0,'andromeda_calls'=>0,'historical_operations_replayed'=>false,'evidence'=>['slice_count'=>6,'target_hotels'=>108,'report_sha256'=>HMSU_EVIDENCE_SHA256,'hotel_union_sha256'=>$ev['summary']['hotel_union_sha256'],'independent_claim_rebuild_sha256'=>$ev['summary']['independent_claim_rebuild_sha256']],'coverage'=>$coverage,'stats'=>$stats,'prepared'=>$prepared,'needs_extra_evidence'=>$near,'hard_conflicts'=>$hard,'guards'=>['target_universe'=>'saved ANEX-only Tourvisor Turkey union','min_significant_tokens'=>2,'generic_non_identity'=>['HOTEL','RESORT','SPA'],'former_names_as_alias'=>true,'meaningful_qualifiers_preserved'=>['ANNEX','BEACH','GARDEN','NORTH','SOUTH'],'prepared_recurrence_dates_min'=>2,'one_date_requires_direct_geo'=>true,'direct_geo_distance_m'=>HMSU_COORD_DIRECT_M,'coordinate_conflict_block_m'=>HMSU_COORD_BLOCK_M,'fuzzy_without_geo'=>false,'manual_decisions_protected'=>true,'pair_exclusions_protected'=>true,'existing_mappings_protected'=>true,'same_provider_occupancy_protected'=>true,'one_to_one_target_required'=>true,'stars_annotation_not_identity'=>true]];
 }catch(Throwable$e){if($db->inTransaction())$db->rollBack();throw$e;}
}
if(!defined('HMSU_LIBRARY_ONLY')&&PHP_SAPI==='cli'){
 $result=['status'=>'failed','operation_id'=>HMSU_OPERATION,'reason'=>'current_review_failed','database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'anex_calls'=>0,'andromeda_calls'=>0,'no_replay'=>true];
 try{$root=realpath(getcwd());if(!$root||basename($root)!=='anytoour.ru')throw new RuntimeException('root_guard');$dbHelper=is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php';require_once$dbHelper;$result=hmsu_review(v2_data_db(),hmsu_evidence(),HMSU_OPERATION);$result['no_replay']=true;}catch(Throwable){}echo 'HMSU_RESULT:',fc_json($result),"\n";exit(($result['status']??'')==='completed'?0:2);
}
