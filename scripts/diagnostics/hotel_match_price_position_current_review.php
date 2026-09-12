<?php
declare(strict_types=1);
error_reporting(0);
const OPERATION_ID='hotel-match-price-position-current-review-1971-20260912-v16';
const SOURCE_RUN_ID=34682814570;
const SOURCE_SHA='769e5beb65df1fcb01741db4ab286178f52356a8';

function norm_text($v): string {
    $v=mb_strtolower((string)$v,'UTF-8');
    $v=strtr($v,['ё'=>'е','é'=>'e','è'=>'e','ê'=>'e','á'=>'a','à'=>'a','ä'=>'a','â'=>'a','ö'=>'o','ô'=>'o','ü'=>'u','ú'=>'u','ç'=>'c','ş'=>'s','ğ'=>'g','ı'=>'i']);
    $v=(string)(preg_replace('/\b(?:ex|ех)\.?\s*/ui',' ',$v)??$v);
    preg_match_all('/[\p{L}\p{N}]+/u',$v,$m);
    return implode(' ',$m[0]??[]);
}
function name_key($v): string {
    $parts=array_values(array_filter(explode(' ',norm_text($v)),static fn($x)=>$x!==''&&!in_array($x,['hotel','resort','spa'],true)));
    sort($parts,SORT_STRING); return implode(' ',$parts);
}
function coord_from_array(array $row): ?array {
    $latKeys=['lat','latitude','geo_lat','hotel_latitude'];$lonKeys=['lon','lng','longitude','geo_lon','hotel_longitude'];$lat=$lon=null;
    foreach($latKeys as $k)if(array_key_exists($k,$row)&&is_numeric($row[$k])){$lat=(float)$row[$k];break;}
    foreach($lonKeys as $k)if(array_key_exists($k,$row)&&is_numeric($row[$k])){$lon=(float)$row[$k];break;}
    return $lat!==null&&$lon!==null&&abs($lat)<=90&&abs($lon)<=180?[$lat,$lon]:null;
}
function coord_from_nested($v): ?array {if(!is_array($v))return null;$c=coord_from_array($v);if($c!==null)return $c;foreach($v as $x){$c=coord_from_nested($x);if($c!==null)return $c;}return null;}
function km(array $a,array $b): float {$r=6371.0;$p1=deg2rad($a[0]);$p2=deg2rad($b[0]);$dp=deg2rad($b[0]-$a[0]);$dl=deg2rad($b[1]-$a[1]);$q=sin($dp/2)**2+cos($p1)*cos($p2)*sin($dl/2)**2;return 2*$r*asin(min(1.0,sqrt($q)));}
try{
    $root=realpath(getcwd());if(!$root||basename($root)!=='anytoour.ru')throw new RuntimeException('server_root_invalid');
    $dbHelper=is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php';require_once $dbHelper;
    $preview=$root.'/_preview/search3-anex-candidate';$app=is_file($preview.'/app/integrations/anex-search-mapping-registry.php')?$preview.'/app/integrations':$root.'/app/integrations';require_once $app.'/anex-search-mapping-registry.php';
    $pdo=v2_data_db();$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$pdo->exec('START TRANSACTION READ ONLY');
    $report=json_decode(file_get_contents('https://raw.githubusercontent.com/pyatkoff/poisk-turov-test/becfd270fa4da54e0e385fb47bbd8e41e27f1295/reports/match-price-position-v15-strong-candidates.json'),true,128,JSON_THROW_ON_ERROR);
    if(($report['source_run_id']??null)!==SOURCE_RUN_ID||($report['source_sha']??'')!==SOURCE_SHA||count($report['pairs']??[])!==18)throw new RuntimeException('source_manifest_mismatch');
    $pairs=$report['pairs'];$registry=AnyTourAnexSearchMappingRegistry::fromPdo($pdo);
    $andrStmt=$pdo->prepare("SELECT * FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=? LIMIT 2");
    $hotelStmt=$pdo->prepare('SELECT * FROM catalog_hotels WHERE id=? LIMIT 1');$aliasStmt=$pdo->prepare('SELECT alias FROM hotel_aliases WHERE hotel_id=? ORDER BY alias LIMIT 200');$exStmt=$pdo->prepare('SELECT * FROM anex_review_pair_exclusions WHERE anex_hotel_id=? LIMIT 100');
    $rows=[];$counts=['safe_bridge'=>0,'already_same'=>0,'needs_extra_evidence'=>0,'hard_conflict'=>0,'excluded'=>0,'missing_current_row'=>0];
    foreach($pairs as $p){
        $aid=(string)$p['anex_id'];$did=(string)$p['andromeda_id'];$currentLocal=$registry->resolve('anex_online',$aid,'preview');$target=$currentLocal!==null?(int)$currentLocal:null;
        $andrStmt->execute([$did]);$andrRows=$andrStmt->fetchAll(PDO::FETCH_ASSOC);$andr=count($andrRows)===1?$andrRows[0]:null;$reason=[];$bucket='needs_extra_evidence';$hotel=null;$aliasMatch=false;$distanceKm=null;$excluded=false;
        if($andr===null){$bucket='missing_current_row';$reason[]='andromeda_current_row_missing_or_ambiguous';}
        elseif(($andr['decision_status']??'')==='conflict'){$bucket='hard_conflict';$reason[]='andromeda_current_conflict';}
        elseif(($andr['decision_status']??'')==='accepted'&&$andr['local_hotel_id']!==null){if($target!==null&&(int)$andr['local_hotel_id']===$target){$bucket='already_same';$reason[]='already_mapped_to_current_anex_target';}else{$bucket='hard_conflict';$reason[]='existing_andromeda_mapping_differs';}}
        elseif($target===null){$reason[]='current_anex_target_unresolved';}
        else{
            $hotelStmt->execute([$target]);$hotel=$hotelStmt->fetch(PDO::FETCH_ASSOC)?:null;
            if(!$hotel||(int)($hotel['is_active']??0)!==1||(int)($hotel['country_id']??0)!==4){$reason[]='current_target_inactive_or_wrong_country';}
            else{
                $aliasStmt->execute([$target]);$aliases=$aliasStmt->fetchAll(PDO::FETCH_COLUMN);$candidateKey=name_key($p['andromeda_name']);foreach(array_merge([(string)$hotel['name']],array_map('strval',$aliases)) as $n)if($candidateKey!==''&&name_key($n)===$candidateKey){$aliasMatch=true;break;}if(!$aliasMatch)$reason[]='current_target_name_alias_mismatch';
                try{$exStmt->execute([(int)$aid]);$excluded=count($exStmt->fetchAll(PDO::FETCH_ASSOC))>0;}catch(Throwable $e){$excluded=true;$reason[]='pair_exclusion_check_failed_closed';}
                if($excluded){$bucket='excluded';$reason[]='anex_pair_exclusion_present';}
                $ev=[];try{$ev=json_decode((string)($andr['evidence_json']??''),true,128,JSON_THROW_ON_ERROR);}catch(Throwable $e){}$sourceCoord=coord_from_nested($ev);$targetCoord=coord_from_array($hotel);
                if($sourceCoord!==null&&$targetCoord!==null){$distanceKm=km($sourceCoord,$targetCoord);if($distanceKm>5.0){$bucket='hard_conflict';$reason[]='coordinate_conflict_gt_5km';}}
                if($bucket==='needs_extra_evidence'&&$aliasMatch&&!$excluded&&($distanceKm===null||$distanceKm<=5.0)){$bucket='safe_bridge';$reason[]='current_anex_bridge_plus_exact_local_alias_plus_live_price_position';}
            }
        }
        $counts[$bucket]=($counts[$bucket]??0)+1;$rows[]=['anex_id'=>$aid,'andromeda_id'=>$did,'anex_name'=>$p['anex_name'],'andromeda_name'=>$p['andromeda_name'],'current_anex_local_id'=>$target,'observed_anex_local_id'=>$p['observed_anex_local_id']??null,'andromeda_status'=>$andr['decision_status']??null,'andromeda_current_local_id'=>isset($andr['local_hotel_id'])&&$andr['local_hotel_id']!==null?(int)$andr['local_hotel_id']:null,'target_name'=>$hotel['name']??null,'alias_match'=>$aliasMatch,'coordinate_distance_km'=>$distanceKm===null?null:round($distanceKm,3),'bucket'=>$bucket,'reasons'=>$reason,'observations'=>$p['observations']];
    }
    $pdo->exec('ROLLBACK');$out=['status'=>'completed','operation_id'=>OPERATION_ID,'source_run_id'=>SOURCE_RUN_ID,'source_sha'=>SOURCE_SHA,'input_unique_pairs'=>count($pairs),'counts'=>$counts,'rows'=>$rows,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'no_replay'=>true];
}catch(Throwable $e){if(isset($pdo)&&$pdo instanceof PDO&&$pdo->inTransaction())$pdo->rollBack();$out=['status'=>'failed','operation_id'=>OPERATION_ID,'reason'=>'review_exception','safe_message'=>substr($e->getMessage(),0,160),'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'no_replay'=>true];}
echo 'MATCH_PRICEPOS_CURRENT_JSON:'.json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;exit(($out['status']??'')==='completed'?0:2);
