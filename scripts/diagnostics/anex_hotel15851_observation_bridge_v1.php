<?php
declare(strict_types=1);
const OWN=15851,OP='int-anex-hotel15851-observation-bridge-20260919-v1';
function rows(PDO $db,string $sql,array $p=[]):array{$q=$db->prepare($sql);$q->execute($p);return$q->fetchAll(PDO::FETCH_ASSOC);}
function main(string $root):array{
 $_SERVER['DOCUMENT_ROOT']=$root;$f=is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php';require_once$f;$db=v2_data_db();
 $src=rows($db,"SELECT namespace,external_key,acquired_via,last_seen_at FROM anytour_hotel_sources WHERE anytour_hotel_id=? ORDER BY namespace,external_key",[OWN]);
 $legacy=[];foreach($src as$r)if($r['namespace']==='legacy_catalog'&&preg_match('/^[1-9][0-9]{0,18}$/D',(string)$r['external_key']))$legacy[]=(int)$r['external_key'];
 $legacy=array_values(array_unique($legacy));$catalog=[];
 foreach($legacy as$id){$r=rows($db,"SELECT id,name,country_id,country_name,region_id,region_name,category,rating,is_active FROM catalog_hotels WHERE id=? LIMIT 1",[$id]);if($r)$catalog[]=$r[0];}
 $regfile=$root.'/_preview/search3-anex-candidate/app/integrations/anex-search-mapping-registry.php';
 if(!is_file($regfile)||is_link($regfile))throw new RuntimeException('REGISTRY_RUNTIME');require_once$regfile;$registry=AnyTourAnexSearchMappingRegistry::fromPdo($db);
 $ownFilter=$registry->previewHotelIds([OWN]);$legacyFilters=[];foreach($legacy as$id)$legacyFilters[(string)$id]=$registry->previewHotelIds([$id]);
 $observed=rows($db,"SELECT anex_hotel_id,hotel_name,last_catalog_hotel_id,last_seen_utc,search_count,last_checkin_from,last_checkin_to
 FROM anex_search_hotel_observations WHERE country_id=4 AND last_checkin_from='2026-09-19' AND last_checkin_to='2026-09-22'
 AND last_seen_utc>='2026-09-19 15:10:18' ORDER BY anex_hotel_id LIMIT 500");
 $targets=[];foreach($observed as$r)if(in_array((int)($r['last_catalog_hotel_id']??0),$legacy,true))$targets[]=$r;
 $maps=[];if($legacy){$ph=implode(',',array_fill(0,count($legacy),'?'));$maps=rows($db,"SELECT anex_hotel_id,catalog_hotel_id,match_class,approval_policy,enabled,scope FROM anex_hotel_search_mappings WHERE catalog_hotel_id IN ($ph) ORDER BY anex_hotel_id",$legacy);}
 $ids=array_values(array_unique(array_map(fn($r)=>(int)$r['anex_hotel_id'],$maps)));$dec=[];$exc=[];
 if($ids){$ph=implode(',',array_fill(0,count($ids),'?'));$dec=rows($db,"SELECT anex_hotel_id,decision_status,catalog_hotel_id FROM anex_hotel_decisions WHERE anex_hotel_id IN ($ph) ORDER BY anex_hotel_id",$ids);
  $exc=rows($db,"SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions WHERE anex_hotel_id IN ($ph) ORDER BY anex_hotel_id,catalog_hotel_id",$ids);}
 return['operation'=>OP,'own_hotel_id'=>OWN,'sources'=>array_map(fn($r)=>['namespace'=>$r['namespace'],'external_key'=>preg_match('/^[1-9][0-9]{0,18}$/D',(string)$r['external_key'])?(string)$r['external_key']:null,'acquired_via'=>$r['acquired_via'],'last_seen_at'=>$r['last_seen_at']],$src),
  'legacy_catalog_ids'=>$legacy,'catalog'=>$catalog,'registry'=>['previewHotelIds_own'=>$ownFilter,'previewHotelIds_legacy'=>$legacyFilters,'count'=>$registry->count()],
  'completed_search_observed_hotels'=>count($observed),'observed_target_rows'=>$targets,'mapping_rows'=>$maps,'decision_rows'=>$dec,'pair_exclusions'=>$exc,
  'supplier_calls'=>0,'db_writes'=>0,'runtime_writes'=>0,'lead_calls'=>0,'booking_calls'=>0];
}
if(PHP_SAPI==='cli'){try{if(count($argv)!==2)throw new RuntimeException('ARGS');$root=realpath($argv[1]);if(!$root||basename($root)!=='anytoour.ru')throw new RuntimeException('ROOT');echo json_encode(main($root),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";}catch(Throwable$e){fwrite(STDERR,"ANEX_BRIDGE_READ_FAILED\n");exit(2);}}
