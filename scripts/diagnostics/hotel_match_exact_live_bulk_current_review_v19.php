<?php
declare(strict_types=1);
error_reporting(0);
const OPERATION_ID='hotel-match-exact-live-bulk-current-review-1971-20260912-v19b';
function norm_text($v): string {$v=mb_strtolower((string)$v,'UTF-8');$v=strtr($v,['ё'=>'е','é'=>'e','è'=>'e','ê'=>'e','á'=>'a','à'=>'a','ä'=>'a','â'=>'a','ö'=>'o','ô'=>'o','ü'=>'u','ú'=>'u','ç'=>'c','ş'=>'s','ğ'=>'g','ı'=>'i']);preg_match_all('/[\p{L}\p{N}]+/u',$v,$m);return implode(' ',$m[0]??[]);}
function name_key($v): string {$parts=array_values(array_filter(explode(' ',norm_text($v)),static fn($x)=>$x!==''&&!in_array($x,['hotel','resort','spa'],true)));sort($parts,SORT_STRING);return implode(' ',$parts);}
function aliases_for_name(string $v): array {$out=[$v];if(preg_match_all('/\(\s*(?:ex|ех)\s*\.?\s*([^)]{2,120})\)/iu',$v,$m))foreach($m[1] as $x)$out[]=trim((string)$x);$stripped=preg_replace('/\s*\(\s*(?:ex|ех)\s*\.?\s*[^)]{2,120}\)\s*/iu',' ',$v);if(is_string($stripped)&&trim($stripped)!=='')$out[]=trim($stripped);return array_values(array_unique($out));}
function coord_from_array(array $row): ?array {$lat=$lon=null;foreach(['lat','latitude','geo_lat','hotel_latitude'] as $k)if(isset($row[$k])&&is_numeric($row[$k])){$lat=(float)$row[$k];break;}foreach(['lon','lng','longitude','geo_lon','hotel_longitude'] as $k)if(isset($row[$k])&&is_numeric($row[$k])){$lon=(float)$row[$k];break;}return $lat!==null&&$lon!==null&&abs($lat)<=90&&abs($lon)<=180?[$lat,$lon]:null;}
function coord_from_nested($v): ?array {if(!is_array($v))return null;$c=coord_from_array($v);if($c)return $c;foreach($v as $x){$c=coord_from_nested($x);if($c)return $c;}return null;}
function km(array $a,array $b): float {$r=6371.0;$p1=deg2rad($a[0]);$p2=deg2rad($b[0]);$dp=deg2rad($b[0]-$a[0]);$dl=deg2rad($b[1]-$a[1]);$q=sin($dp/2)**2+cos($p1)*cos($p2)*sin($dl/2)**2;return 2*$r*asin(min(1,sqrt($q)));}
try {
 $raw=file_get_contents('php://stdin');$req=json_decode($raw,true,256,JSON_THROW_ON_ERROR);$pairs=$req['pairs']??[];if(count($pairs)<150||count($pairs)>400)throw new RuntimeException('pair_count');
 $root=realpath(getcwd());if(!$root||basename($root)!=='anytoour.ru')throw new RuntimeException('root');
 $dbHelper=is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php';require_once $dbHelper;
 $preview=$root.'/_preview/search3-anex-candidate';$app=is_file($preview.'/app/integrations/anex-search-mapping-registry.php')?$preview.'/app/integrations':$root.'/app/integrations';require_once $app.'/anex-search-mapping-registry.php';
 $pdo=v2_data_db();$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$pdo->exec('START TRANSACTION READ ONLY');$registry=AnyTourAnexSearchMappingRegistry::fromPdo($pdo);
 $andr=$pdo->prepare("SELECT * FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=? LIMIT 2");
 $hotel=$pdo->prepare('SELECT * FROM catalog_hotels WHERE id=? LIMIT 1');$aliases=$pdo->prepare('SELECT alias FROM hotel_aliases WHERE hotel_id=? ORDER BY alias LIMIT 200');
 $dec=$pdo->prepare('SELECT anex_hotel_id FROM anex_hotel_decisions WHERE anex_hotel_id=? LIMIT 2');$map=$pdo->prepare('SELECT anex_hotel_id FROM anex_hotel_search_mappings WHERE anex_hotel_id=? LIMIT 2');$ex=$pdo->prepare('SELECT anex_hotel_id FROM anex_review_pair_exclusions WHERE anex_hotel_id=? LIMIT 2');
 $rows=[];$counts=['already_same'=>0,'reverse_safe'=>0,'needs_extra'=>0,'blocked'=>0,'andromeda_unresolved'=>0,'anex_only'=>0];
 foreach($pairs as $p){
  $aid=(string)$p['anex_id'];$did=(string)$p['andromeda_id'];$cur=$registry->resolve('anex_online',$aid,'preview');$andr->execute([$did]);$ars=$andr->fetchAll(PDO::FETCH_ASSOC);$a=count($ars)===1?$ars[0]:null;$bucket='needs_extra';$reason=[];$target=null;$targetName=null;$matchedAlias=null;$dist=null;
  if(!$a){$bucket='blocked';$reason[]='andromeda_row_missing_or_ambiguous';}
  elseif(($a['decision_status']??'')==='conflict'){$bucket='blocked';$reason[]='andromeda_conflict';}
  elseif(($a['decision_status']??'')!=='accepted'||$a['local_hotel_id']===null){$bucket=$cur!==null?'anex_only':'andromeda_unresolved';$reason[]='andromeda_not_accepted';}
  else{$target=(int)$a['local_hotel_id'];if($cur!==null){if((int)$cur===$target){$bucket='already_same';$reason[]='same_current_local';}else{$bucket='blocked';$reason[]='provider_local_conflict';}}
   else{$dec->execute([(int)$aid]);$dN=count($dec->fetchAll());$map->execute([(int)$aid]);$mN=count($map->fetchAll());$ex->execute([(int)$aid]);$eN=count($ex->fetchAll());if($dN||$mN||$eN){$bucket='blocked';$reason[]='manual_mapping_or_exclusion_present';}
    else{$hotel->execute([$target]);$h=$hotel->fetch(PDO::FETCH_ASSOC)?:null;if(!$h||(int)($h['is_active']??0)!==1){$bucket='blocked';$reason[]='target_inactive';}
     else{$targetName=(string)$h['name'];$aliases->execute([$target]);$localAliases=array_map('strval',$aliases->fetchAll(PDO::FETCH_COLUMN));$sourceKey=name_key($p['anex_name']);foreach(array_merge(aliases_for_name($targetName),$localAliases) as $n)if($sourceKey!==''&&name_key($n)===$sourceKey){$matchedAlias=$n;break;}
      $sourceNamesMatch=(name_key($p['anex_name'])!==''&&name_key($p['anex_name'])===name_key($p['andromeda_name']));$live=false;foreach($p['observations'] as $o){$ap=$o['anex_rub_price']??null;$dp=$o['andromeda_rub_price']??null;$pd=1;if($ap!==null&&$dp!==null){$mx=max((float)$ap,(float)$dp);$pd=$mx>0?abs((float)$ap-(float)$dp)/$mx:1;}$pos=abs((int)$o['anex_position']-(int)$o['andromeda_position']);if($pd<=0.01&&$pos<=5){$live=true;break;}}
      $ev=[];try{$ev=json_decode((string)($a['evidence_json']??''),true,128,JSON_THROW_ON_ERROR);}catch(Throwable $e){}$sc=coord_from_nested($ev);$tc=coord_from_array($h);if($sc&&$tc){$dist=km($sc,$tc);if($dist>5){$bucket='blocked';$reason[]='coordinate_conflict_gt_5km';}}
      if($bucket==='needs_extra'&&$sourceNamesMatch&&$live&&$matchedAlias!==null&&($dist===null||$dist<=5)){$bucket='reverse_safe';$reason[]='accepted_andromeda_exact_live_pair_local_alias';}
      elseif($bucket==='needs_extra'){if(!$sourceNamesMatch)$reason[]='source_name_mismatch';if(!$live)$reason[]='live_price_position_not_confirmed';if($matchedAlias===null)$reason[]='local_alias_not_confirmed';}
 }}}}
 $counts[$bucket]=($counts[$bucket]??0)+1;$rows[]=['anex_id'=>$aid,'andromeda_id'=>$did,'anex_name'=>$p['anex_name'],'andromeda_name'=>$p['andromeda_name'],'current_anex_local_id'=>$cur,'andromeda_status'=>$a['decision_status']??null,'andromeda_local_id'=>$target,'target_name'=>$targetName,'matched_alias'=>$matchedAlias,'coordinate_distance_km'=>$dist===null?null:round($dist,3),'bucket'=>$bucket,'reasons'=>$reason,'observations'=>$p['observations']];
 }
 $pdo->exec('ROLLBACK');$out=['status'=>'completed','operation_id'=>OPERATION_ID,'input_pairs'=>count($pairs),'counts'=>$counts,'rows'=>$rows,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'no_replay'=>true];
}catch(Throwable $e){if(isset($pdo)&&$pdo instanceof PDO&&$pdo->inTransaction())$pdo->rollBack();$out=['status'=>'failed','operation_id'=>OPERATION_ID,'safe_message'=>substr($e->getMessage(),0,160),'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'no_replay'=>true];}
echo 'MATCH_EXACT_BULK_JSON:'.json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;exit(($out['status']??'')==='completed'?0:2);
