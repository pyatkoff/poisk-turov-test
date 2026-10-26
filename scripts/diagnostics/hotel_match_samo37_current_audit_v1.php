<?php
declare(strict_types=1);
const OP="hotel-match-samo37-current-audit-1971-20260927-v1";
function need($x,$m){if(!$x)throw new RuntimeException($m);}
function main($argv){
 need(($argv[1]??"")==="--execute","disabled");
 $root=getenv("ANYTOUR_ROOT");$dir=getenv("MATCH_OPERATION_DIR");$manifest=getenv("MATCH_MANIFEST");
 need(is_dir($root)&&is_dir($dir)&&is_file($manifest),"scope");
 $m=json_decode(file_get_contents($manifest),true,128,JSON_THROW_ON_ERROR);need(($m["schema"]??"")==="match_samo37_current_audit_manifest_v1"&&count($m["rows"]??[])===37,"manifest");
 require_once $root.(is_file($root."/data/db-v1.php")?"/data/db-v1.php":"/v2/data/db-v1.php");$db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec("SET TRANSACTION ISOLATION LEVEL REPEATABLE READ");$db->exec("START TRANSACTION READ ONLY");
 $out=[];$counts=[];$cut=gmdate("Y-m-d H:i:s",time()-30*86400);
 try{foreach($m["rows"] as $r){$id=(int)$r["tv_hotel_id"];$cat=(string)$r["catalog_id"];$re=[];
  $q=$db->prepare("SELECT id,name,is_active,country_name FROM catalog_hotels WHERE id=?");$q->execute([$id]);$h=$q->fetch(PDO::FETCH_ASSOC);if(!$h||(int)$h["is_active"]!==1)$re[]="target_inactive";
  $q=$db->prepare("SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=?");$q->execute([$cat]);$src=$q->fetchAll(PDO::FETCH_ASSOC);
  $same=false;$pending=false;foreach($src as $x){if((int)($x["local_hotel_id"]??0)===$id&&$x["decision_status"]==="accepted")$same=true;elseif(($x["local_hotel_id"]===null||$x["local_hotel_id"]==='')&&$x["decision_status"]!=="accepted")$pending=true;else $re[]="source_occupied_or_protected";}
  $q=$db->prepare("SELECT external_hotel_id FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND local_hotel_id=? AND decision_status='accepted'");$q->execute([$id]);$targets=array_values(array_unique(array_map('strval',$q->fetchAll(PDO::FETCH_COLUMN))));if($targets&&!in_array($cat,$targets,true))$re[]="target_catalog_occupied";
  $q=$db->prepare("SELECT COUNT(*) FROM tour_operator_identity_observations WHERE hotel_id=? AND last_seen_at>=?");$q->execute([$id,$cut]);if((int)$q->fetchColumn()<1)$re[]="not_live30";
  $q=$db->prepare("SELECT COUNT(*) FROM anex_hotel_decisions WHERE catalog_hotel_id=? AND decision_status<>'accepted'");$q->execute([$id]);if((int)$q->fetchColumn()>0)$re[]="manual_anex_protection";
  $re=array_values(array_unique($re));$status=$same?"already_resolved_same":($re?"hold":($pending?"ready_pending_transition":"ready_source_missing"));$counts[$status]=($counts[$status]??0)+1;
  $out[]=["tv_hotel_id"=>$id,"catalog_id"=>$cat,"name"=>$r["name"],"candidate_mode"=>$r["candidate_mode"],"lane_count"=>$r["lane_count"],"direct_anex_match"=>$r["direct_anex_match"],"status"=>$status,"reasons"=>$re,"existing_target_catalog_ids"=>$targets];
 }$db->rollBack();}catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
 ksort($counts);$res=["operation"=>OP,"state"=>"completed_read_only_current_audit","generated_at_utc"=>gmdate('c'),"input_count"=>37,"status_counts"=>$counts,"rows"=>$out,"provider_http_calls"=>0,"database_writes"=>0,"mapping_writes"=>0,"safe_to_write_now"=>false];file_put_contents($dir."/result.json",json_encode($res,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT)."\n");echo json_encode(["state"=>$res["state"],"status_counts"=>$counts],JSON_UNESCAPED_UNICODE)."\n";return 0;}
if(PHP_SAPI==='cli')exit(main($argv));
