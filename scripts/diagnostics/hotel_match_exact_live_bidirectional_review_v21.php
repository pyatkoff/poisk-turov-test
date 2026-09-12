<?php
declare(strict_types=1);
error_reporting(0);
const OPERATION_ID='hotel-match-exact-live-bidirectional-review-1971-20260912-v21';

function ntext($v): string {
    $v=mb_strtolower((string)$v,'UTF-8');
    $v=strtr($v,['ё'=>'е','é'=>'e','è'=>'e','ê'=>'e','á'=>'a','à'=>'a','ä'=>'a','â'=>'a','ö'=>'o','ô'=>'o','ü'=>'u','ú'=>'u','ç'=>'c','ş'=>'s','ğ'=>'g','ı'=>'i']);
    preg_match_all('/[\p{L}\p{N}]+/u',$v,$m); return implode(' ',$m[0]??[]);
}
function main_name($v): string {
    $v=preg_replace('/\(\s*(?:ex|ех)\s*\.?\s*[^)]{2,180}\)/iu',' ',(string)$v);
    return trim((string)$v);
}
function tokens($v): array {
    $drop=['hotel','hotels','resort','resorts','spa','otel','the','by'];
    return array_values(array_filter(explode(' ',ntext(main_name($v))),static fn($x)=>$x!==''&&!in_array($x,$drop,true)));
}
function canon_geo_token(string $x): string {
    $m=[
      'istanbul'=>'istanbul','стамбул'=>'istanbul',
      'sultanahmet'=>'sultanahmet','султанахмет'=>'sultanahmet',
      'taksim'=>'taksim','таксим'=>'taksim',
      'aksaray'=>'aksaray','аксарай'=>'aksaray',
      'laleli'=>'laleli','лалели'=>'laleli',
      'hurghada'=>'hurghada','хургада'=>'hurghada',
      'sharm'=>'sharm','шарм'=>'sharm','el'=>'el','эль'=>'el','sheikh'=>'sheikh','шейх'=>'sheikh',
      'naama'=>'naama','наама'=>'naama','bay'=>'bay','бей'=>'bay',
      'sharks'=>'sharks','шаркс'=>'sharks',
      'nabq'=>'nabq','набк'=>'nabq',
      'ras'=>'ras','рас'=>'ras','um'=>'um','ум'=>'um','sid'=>'sid','сид'=>'sid',
      'soma'=>'soma','сома'=>'soma',
      'safaga'=>'safaga','сафага'=>'safaga',
      'antalya'=>'antalya','анталия'=>'antalya',
      'kemer'=>'kemer','кемер'=>'kemer',
    ]; return $m[$x]??$x;
}
function geo_tokens($v): array {return array_values(array_map('canon_geo_token',tokens($v)));}
function multiset_subset(array $a,array $b): bool {$ca=array_count_values($a);$cb=array_count_values($b);foreach($ca as $k=>$n)if(($cb[$k]??0)<$n)return false;return true;}
function multiset_diff(array $a,array $b): array {$cb=array_count_values($b);$out=[];foreach($a as $x){if(($cb[$x]??0)>0)$cb[$x]--;else$out[]=$x;}return $out;}
function alias_names(string $name): array {
    $out=[$name];
    if(preg_match_all('/\(\s*(?:ex|ех)\s*\.?\s*([^)]{2,180})\)/iu',$name,$m))foreach($m[1] as $x)$out[]=trim((string)$x);
    $stripped=preg_replace('/\s*\(\s*(?:ex|ех)\s*\.?\s*[^)]{2,180}\)\s*/iu',' ', $name);
    if(is_string($stripped)&&trim($stripped)!=='')$out[]=trim($stripped);
    return array_values(array_unique($out));
}
function coord_from_array(array $row): ?array {$lat=$lon=null;foreach(['lat','latitude','geo_lat','hotel_latitude','coord_lat'] as $k)if(isset($row[$k])&&is_numeric($row[$k])){$lat=(float)$row[$k];break;}foreach(['lon','lng','longitude','geo_lon','hotel_longitude','coord_lon'] as $k)if(isset($row[$k])&&is_numeric($row[$k])){$lon=(float)$row[$k];break;}return $lat!==null&&$lon!==null?[$lat,$lon]:null;}
function coord_nested($v): ?array {if(!is_array($v))return null;$c=coord_from_array($v);if($c)return $c;foreach($v as $x){$c=coord_nested($x);if($c)return $c;}return null;}
function km(array $a,array $b): float {$r=6371.0;$p1=deg2rad($a[0]);$p2=deg2rad($b[0]);$dp=deg2rad($b[0]-$a[0]);$dl=deg2rad($b[1]-$a[1]);$q=sin($dp/2)**2+cos($p1)*cos($p2)*sin($dl/2)**2;return 2*$r*asin(min(1,sqrt($q)));}
function live_exact(array $r): bool {foreach(($r['observations']??[]) as $o){$ap=$o['anex_rub_price']??null;$dp=$o['andromeda_rub_price']??null;if($ap===null||$dp===null)continue;$mx=max((float)$ap,(float)$dp);$pd=$mx>0?abs((float)$ap-(float)$dp)/$mx:1;$pos=abs((int)($o['anex_position']??999)-(int)($o['andromeda_position']??-999));if($pd<=0.01&&$pos<=5)return true;}return false;}
function local_name_supported(string $source,array $h,array $aliases): array {
    $src=tokens($source); $geo=geo_tokens(((string)($h['region_name']??'')).' '.((string)($h['subregion_name']??'')));
    foreach(array_merge([(string)($h['name']??'')],$aliases) as $nm){foreach(alias_names((string)$nm) as $candidate){$t=tokens($candidate);if(!$t)continue;
        if($src===$t||multiset_subset($src,$t)&&!multiset_diff($t,$src))return [true,'token_exact',$candidate];
        if(multiset_subset($t,$src)){$extra=array_map('canon_geo_token',multiset_diff($src,$t));if($extra&&multiset_subset($extra,$geo))return [true,'source_geo_suffix',$candidate];}
        if(multiset_subset($src,$t)){$extra=array_map('canon_geo_token',multiset_diff($t,$src));if($extra&&multiset_subset($extra,$geo))return [true,'target_geo_suffix',$candidate];}
    }} return [false,null,null];
}
try {
    $req=json_decode(file_get_contents('php://stdin'),true,128,JSON_THROW_ON_ERROR);$rows=$req['rows']??[];if(count($rows)<20||count($rows)>40)throw new RuntimeException('row_count');
    $root=realpath(getcwd());if(!$root||basename($root)!=='anytoour.ru')throw new RuntimeException('root');
    $dbHelper=is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php';require_once $dbHelper;
    $preview=$root.'/_preview/search3-anex-candidate';$app=is_file($preview.'/app/integrations/anex-search-mapping-registry.php')?$preview.'/app/integrations':$root.'/app/integrations';require_once $app.'/anex-search-mapping-registry.php';
    $pdo=v2_data_db();$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$pdo->exec('START TRANSACTION READ ONLY');$registry=AnyTourAnexSearchMappingRegistry::fromPdo($pdo);
    $andr=$pdo->prepare("SELECT * FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=? LIMIT 2");$hotel=$pdo->prepare('SELECT * FROM catalog_hotels WHERE id=? LIMIT 1');$aliasQ=$pdo->prepare('SELECT alias FROM hotel_aliases WHERE hotel_id=? ORDER BY alias LIMIT 300');$dec=$pdo->prepare('SELECT anex_hotel_id FROM anex_hotel_decisions WHERE anex_hotel_id=? LIMIT 2');$map=$pdo->prepare('SELECT anex_hotel_id FROM anex_hotel_search_mappings WHERE anex_hotel_id=? LIMIT 2');$ex=$pdo->prepare('SELECT anex_hotel_id FROM anex_review_pair_exclusions WHERE anex_hotel_id=? LIMIT 2');
    $outRows=[];$counts=['reverse_safe'=>0,'forward_safe'=>0,'already_same'=>0,'needs_extra'=>0,'blocked'=>0];
    foreach($rows as $r){$aid=(string)$r['anex_id'];$did=(string)$r['andromeda_id'];$cur=$registry->resolve('anex_online',$aid,'preview');$andr->execute([$did]);$ars=$andr->fetchAll(PDO::FETCH_ASSOC);$a=count($ars)===1?$ars[0]:null;$bucket='needs_extra';$why=[];$target=null;$mode=null;$matched=null;$dist=null;
      if(!$a){$bucket='blocked';$why[]='andromeda_row_missing_or_ambiguous';}
      elseif(($a['decision_status']??'')==='conflict'){$bucket='blocked';$why[]='andromeda_conflict';}
      else {
        $aAccepted=(($a['decision_status']??'')==='accepted'&&$a['local_hotel_id']!==null);$aPending=(($a['decision_status']??'')==='pending'&&$a['local_hotel_id']===null);
        if($cur!==null&&$aAccepted){if((int)$cur===(int)$a['local_hotel_id']){$bucket='already_same';$why[]='same_current_local';}else{$bucket='blocked';$why[]='provider_local_conflict';}}
        elseif($cur===null&&$aAccepted){$target=(int)$a['local_hotel_id'];$dec->execute([(int)$aid]);$dn=count($dec->fetchAll());$map->execute([(int)$aid]);$mn=count($map->fetchAll());$ex->execute([(int)$aid]);$en=count($ex->fetchAll());if($dn||$mn||$en){$bucket='blocked';$why[]='manual_mapping_or_exclusion_present';}else{$hotel->execute([$target]);$h=$hotel->fetch(PDO::FETCH_ASSOC)?:null;if(!$h||(int)($h['is_active']??0)!==1){$bucket='blocked';$why[]='target_inactive';}else{$aliasQ->execute([$target]);$als=array_map('strval',$aliasQ->fetchAll(PDO::FETCH_COLUMN));[$ok,$mode,$matched]=local_name_supported((string)$r['anex_name'],$h,$als);$ev=[];try{$ev=json_decode((string)($a['evidence_json']??''),true,128,JSON_THROW_ON_ERROR);}catch(Throwable $e){}$sc=coord_nested($ev);$tc=coord_from_array($h);if($sc&&$tc){$dist=km($sc,$tc);if($dist>5){$bucket='blocked';$why[]='coordinate_conflict_gt_5km';}}if($bucket==='needs_extra'&&$ok&&live_exact($r)&&ntext($r['anex_name'])===ntext($r['andromeda_name'])&&($dist===null||$dist<=5)){$bucket='reverse_safe';$why[]='accepted_andromeda_plus_exact_live_pair_plus_local_name_geo';}elseif($bucket==='needs_extra')$why[]='reverse_evidence_incomplete';}}}
        elseif($cur!==null&&$aPending){$target=(int)$cur;$hotel->execute([$target]);$h=$hotel->fetch(PDO::FETCH_ASSOC)?:null;if(!$h||(int)($h['is_active']??0)!==1){$bucket='blocked';$why[]='target_inactive';}else{$aliasQ->execute([$target]);$als=array_map('strval',$aliasQ->fetchAll(PDO::FETCH_COLUMN));[$ok,$mode,$matched]=local_name_supported((string)$r['andromeda_name'],$h,$als);$ev=[];try{$ev=json_decode((string)($a['evidence_json']??''),true,128,JSON_THROW_ON_ERROR);}catch(Throwable $e){}$sc=coord_nested($ev);$tc=coord_from_array($h);if($sc&&$tc){$dist=km($sc,$tc);if($dist>5){$bucket='blocked';$why[]='coordinate_conflict_gt_5km';}}if($bucket==='needs_extra'&&live_exact($r)&&ntext($r['anex_name'])===ntext($r['andromeda_name'])&&($ok||$dist!==null&&$dist<=1)&&($dist===null||$dist<=5)){$bucket='forward_safe';$why[]='accepted_anex_plus_exact_live_pair_plus_local_name_or_direct_coord';}elseif($bucket==='needs_extra')$why[]='forward_evidence_incomplete';}}
        else {$bucket='needs_extra';$why[]='both_unresolved_or_noncanonical_state';}
      }
      $counts[$bucket]=($counts[$bucket]??0)+1;$outRows[]=['anex_id'=>$aid,'andromeda_id'=>$did,'anex_name'=>$r['anex_name'],'andromeda_name'=>$r['andromeda_name'],'current_anex_local_id'=>$cur,'andromeda_status'=>$a['decision_status']??null,'andromeda_local_id'=>$a['local_hotel_id']??null,'target_local_id'=>$target,'match_mode'=>$mode,'matched_name'=>$matched,'coordinate_distance_km'=>$dist===null?null:round($dist,3),'bucket'=>$bucket,'reasons'=>$why,'observations'=>$r['observations']??[]];
    }
    $pdo->exec('ROLLBACK');$out=['status'=>'completed','operation_id'=>OPERATION_ID,'input_count'=>count($rows),'counts'=>$counts,'rows'=>$outRows,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'no_replay'=>true];
} catch(Throwable $e){if(isset($pdo)&&$pdo instanceof PDO&&$pdo->inTransaction())$pdo->rollBack();$out=['status'=>'failed','operation_id'=>OPERATION_ID,'safe_message'=>substr($e->getMessage(),0,180),'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'no_replay'=>true];}
echo 'MATCH_BIDIR_JSON:'.json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;exit(($out['status']??'')==='completed'?0:2);
