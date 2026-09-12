<?php
declare(strict_types=1);
if (!defined('FC_LIBRARY_ONLY')) define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/hotel_full_catalog_reconcile.php';

const HMADR_OPERATION = 'hotel-match-anex-sold-details-review-1971-20260911-v1';
const HMADR_SOURCE_OPERATION = 'hotel-match-anex-sellability-review-1971-20260911-v4';
const HMADR_SOURCE_RUN = 34582780463;
const HMADR_SOURCE_ARTIFACT = 10192364780;
const HMADR_SOURCE_RESULT_SHA256 = '47a55cf4ba8cab0fb2fa175f0856e2eca2f144e64b1d7b7a48ddfb0335d85c61';
const HMADR_CORE8 = [1=>true,2=>true,4=>true,8=>true,9=>true,10=>true,12=>true,16=>true];

function hmadr_manifest(array $m): array {
    if (($m['status'] ?? null) !== 'completed' || ($m['operation_id'] ?? null) !== HMADR_SOURCE_OPERATION
        || ($m['probe_semantics'] ?? null) !== 'bounded_supplier_dates_not_global_sellability'
        || !is_array($m['sold'] ?? null) || count($m['sold']) !== 254
        || (int)($m['stats']['sold_on_probe'] ?? -1) !== 254) throw new RuntimeException('HMADR_SOURCE_MANIFEST');
    $seen=[];$rows=[];
    foreach($m['sold'] as $row){
        $id=$row['anex_hotel_id']??null;$dates=$row['probe_dates']??null;
        if((!is_int($id)&&!is_string($id))||!preg_match('/\A[1-9][0-9]{0,7}\z/D',(string)$id)||isset($seen[(int)$id])||!is_array($dates)||count($dates)<1||count($dates)>3)throw new RuntimeException('HMADR_SOURCE_MANIFEST');
        $clean=[];foreach($dates as $date){if(!is_string($date)||!preg_match('/\A2026-(?:09|10)-[0-3][0-9]\z/D',$date))throw new RuntimeException('HMADR_SOURCE_MANIFEST');$clean[$date]=true;}
        $seen[(int)$id]=true;$rows[]=['anex_hotel_id'=>(int)$id,'probe_dates'=>array_keys($clean)];
    }
    usort($rows,static fn($a,$b)=>$a['anex_hotel_id']<=>$b['anex_hotel_id']);
    return ['source_operation_id'=>HMADR_SOURCE_OPERATION,'source_run_id'=>HMADR_SOURCE_RUN,'source_artifact_id'=>HMADR_SOURCE_ARTIFACT,'source_result_sha256'=>HMADR_SOURCE_RESULT_SHA256,'sold'=>$rows];
}
function hmadr_text($v,int $limit=500): ?string {
    if(!is_string($v)||strlen($v)>4096||!preg_match('//u',$v))return null;
    if(preg_match('~https?://|oauth_token|authorization|bearer\s|eyJ[A-Za-z0-9_-]{12,}~i',$v))return null;
    $v=trim((string)preg_replace('/[\p{Cc}\p{Cf}]/u',' ',strip_tags($v)));if($v==='')return null;
    return function_exists('mb_substr')?mb_substr($v,0,$limit,'UTF-8'):substr($v,0,$limit);
}
function hmadr_coord_pair(array $row): array {
    $lat=fc_num($row['latitude']??null);$lon=fc_num($row['longitude']??null);
    if($lat===null||$lon===null||abs($lat)>90||abs($lon)>180)return [null,null];return [$lat,$lon];
}
function hmadr_current_plan(PDO $db,array $manifest,string $operation=HMADR_OPERATION): array {
    if($operation!==HMADR_OPERATION)throw new RuntimeException('HMADR_OPERATION_SCOPE');$m=hmadr_manifest($manifest);
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
    try{
        $manual=array_fill_keys(array_map('intval',$db->query('SELECT anex_hotel_id FROM anex_hotel_decisions')->fetchAll(PDO::FETCH_COLUMN)),true);
        $mapped=array_fill_keys(array_map('intval',$db->query('SELECT anex_hotel_id FROM anex_hotel_search_mappings WHERE enabled=1')->fetchAll(PDO::FETCH_COLUMN)),true);
        $staging=[];foreach($db->query('SELECT * FROM anex_hotels')->fetchAll(PDO::FETCH_ASSOC) as $r)$staging[(int)$r['anex_hotel_id']]=$r;
        $observed=[];foreach($db->query('SELECT * FROM anex_search_hotel_observations ORDER BY search_count DESC,last_seen_utc DESC,anex_hotel_id')->fetchAll(PDO::FETCH_ASSOC) as $r){$id=(int)$r['anex_hotel_id'];if(!isset($observed[$id]))$observed[$id]=$r;}
        $targets=[];$rows=[];$stats=['source_sold'=>254,'already_mapped'=>0,'manual'=>0,'country_other'=>0,'current_unresolved_sold'=>0,'live_unresolved_sold'=>0,'staging_missing'=>0,'staging_name_missing'=>0,'staging_geo_missing'=>0,'staging_coordinates_missing'=>0,'details_targets'=>0];
        foreach($m['sold'] as $sold){$id=(int)$sold['anex_hotel_id'];if(isset($manual[$id])){$stats['manual']++;continue;}if(isset($mapped[$id])){$stats['already_mapped']++;continue;}$s=$staging[$id]??[];$o=$observed[$id]??null;$country=(int)($o['country_id']??0);if(!isset(HMADR_CORE8[$country]))$country=(int)(fc_country($s['api_country']??'')??0);if(!isset(HMADR_CORE8[$country])){$stats['country_other']++;continue;}$stats['current_unresolved_sold']++;$live=(int)($o['search_count']??0)>0;if($live)$stats['live_unresolved_sold']++;
            $names=array_values(array_filter([(string)($s['api_name']??''),(string)($s['xml_name']??''),(string)($s['xml_alternate_name']??''),(string)($o['hotel_name']??'')],static fn($v)=>trim($v)!==''));
            $places=array_values(array_filter([(string)($s['api_region']??''),(string)($s['api_town']??''),(string)($o['region_name']??'')],static fn($v)=>trim($v)!==''));
            [$lat,$lon]=hmadr_coord_pair($s);$missing=[];if(!$s){$stats['staging_missing']++;$missing[]='staging_missing';}if(!$names){$stats['staging_name_missing']++;$missing[]='name_missing';}if(!$places){$stats['staging_geo_missing']++;$missing[]='geo_missing';}if($lat===null||$lon===null){$stats['staging_coordinates_missing']++;$missing[]='coordinates_missing';}
            if(!$s||!$names||!$places||$lat===null||$lon===null){$targets[]=$id;$rows[]=['anex_hotel_id'=>$id,'country_id'=>$country,'live'=>$live,'search_count'=>(int)($o['search_count']??0),'last_seen_utc'=>$o['last_seen_utc']??null,'missing'=>$missing,'probe_dates'=>$sold['probe_dates']];}
        }
        sort($targets,SORT_NUMERIC);$stats['details_targets']=count($targets);$targetHash=hash('sha256',json_encode($targets,JSON_UNESCAPED_SLASHES));$db->commit();
        return ['status'=>'ready','operation_id'=>$operation,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'manifest'=>$m,'stats'=>$stats,'target_ids'=>$targets,'target_sha256'=>$targetHash,'target_context'=>$rows];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function hmadr_reason(Throwable $e): string {$m=$e->getMessage();return is_string($m)&&preg_match('/\A[A-Z0-9_]{1,80}\z/D',$m)?$m:'HMADR_OTHER_ERROR';}
function hmadr_collect_details(array $targetIds,string $token,string $expectedHash): array {
    $ids=array_values(array_unique(array_map('intval',$targetIds)));sort($ids,SORT_NUMERIC);if(count($ids)>254||hash('sha256',json_encode($ids,JSON_UNESCAPED_SLASHES))!==$expectedHash)throw new RuntimeException('HMADR_TARGET_CHANGED');
    $rows=[];$calls=0;$ok=0;$empty=0;$errors=0;
    foreach($ids as $id){$client=new AnyTourAnexClient($token);$status='error';$detail=[];$reason=null;$diag=[];try{$payload=$client->request('Hotels_DETAILS',['HOTELINC'=>$id]);$calls+=$client->requestsMade();if(!$payload){$status='empty';$empty++;}else{$returned=$payload['id']??null;if($returned!==null&&(int)$returned!==$id)throw new RuntimeException('HMADR_DETAIL_ID_MISMATCH');foreach(['id','name','state','region','town','townKey','address','latitude','longitude'] as $key){$v=$payload[$key]??null;if(is_int($v)||is_float($v))$detail[$key]=$v;elseif(is_string($v)&&($safe=hmadr_text($v,$key==='address'?1000:300))!==null)$detail[$key]=$safe;}$status='ok';$ok++;}}catch(Throwable $e){$calls+=$client->requestsMade();$errors++;$reason=hmadr_reason($e);$diag=$client->lastRequestDiagnostics();$diag=array_intersect_key($diag,array_flip(['action','http_status','response_bytes','supplier_code']));}
        $rows[]=['anex_hotel_id'=>$id,'status'=>$status,'reason'=>$reason,'detail'=>$detail,'request'=>$diag];
    }
    return ['status'=>'completed','supplier_calls'=>$calls,'requested'=>count($ids),'ok'=>$ok,'empty'=>$empty,'errors'=>$errors,'rows'=>$rows,'target_sha256'=>$expectedHash];
}
function hmadr_identity_tokens(string $v): array {$generic=['hotel'=>1,'hotels'=>1,'отель'=>1,'отели'=>1,'resort'=>1,'resorts'=>1,'spa'=>1,'резорт'=>1,'ресорт'=>1,'спа'=>1];return array_values(array_filter(explode(' ',fc_norm($v)),static fn($x)=>$x!==''&&!isset($generic[$x])));}
function hmadr_key(string $v): string {return implode(' ',hmadr_identity_tokens($v));}
function hmadr_critical(string $v): array {$set=['annex'=>1,'beach'=>1,'garden'=>1,'north'=>1,'south'=>1];$o=[];foreach(hmadr_identity_tokens($v) as $t)if(isset($set[$t]))$o[$t]=true;$k=array_keys($o);sort($k,SORT_STRING);return $k;}
function hmadr_candidate_review(PDO $db,array $plan,array $details,string $operation=HMADR_OPERATION): array {
    if($operation!==HMADR_OPERATION||($details['target_sha256']??'')!==($plan['target_sha256']??''))throw new RuntimeException('HMADR_EVIDENCE_SCOPE');
    $detailBy=[];foreach($details['rows']??[] as $r)if(($r['status']??'')==='ok')$detailBy[(int)$r['anex_hotel_id']]=$r['detail'];
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
    try{
        $manual=array_fill_keys(array_map('intval',$db->query('SELECT anex_hotel_id FROM anex_hotel_decisions')->fetchAll(PDO::FETCH_COLUMN)),true);$mapped=array_fill_keys(array_map('intval',$db->query('SELECT anex_hotel_id FROM anex_hotel_search_mappings WHERE enabled=1')->fetchAll(PDO::FETCH_COLUMN)),true);$excluded=[];foreach($db->query('SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions')->fetchAll(PDO::FETCH_ASSOC) as $r)$excluded[(int)$r['anex_hotel_id']][(int)$r['catalog_hotel_id']]=true;
        $hotels=[];$names=[];$index=[];foreach($db->query('SELECT h.id,h.country_id,h.name,h.region_name,h.subregion_name,h.category,h.latitude,h.longitude,d.latitude detail_latitude,d.longitude detail_longitude FROM catalog_hotels h LEFT JOIN catalog_hotel_details d ON d.hotel_id=h.id WHERE h.is_active=1 AND h.country_id IN (1,2,4,8,9,10,12,16)')->fetchAll(PDO::FETCH_ASSOC) as $h){$id=(int)$h['id'];$c=(int)$h['country_id'];$h['latitude']=$h['detail_latitude']??$h['latitude'];$h['longitude']=$h['detail_longitude']??$h['longitude'];$hotels[$id]=$h;$names[$id]=[(string)$h['name']];$k=hmadr_key((string)$h['name']);if($k!=='')$index[$c][$k][$id]=true;}
        foreach($db->query('SELECT a.hotel_id,a.alias,h.country_id FROM hotel_aliases a JOIN catalog_hotels h ON h.id=a.hotel_id WHERE h.is_active=1 AND h.country_id IN (1,2,4,8,9,10,12,16)')->fetchAll(PDO::FETCH_ASSOC) as $a){$id=(int)$a['hotel_id'];$c=(int)$a['country_id'];$names[$id][]=(string)$a['alias'];$k=hmadr_key((string)$a['alias']);if($k!=='')$index[$c][$k][$id]=true;}
        $context=[];foreach($plan['target_context']??[] as $r)$context[(int)$r['anex_hotel_id']]=$r;$prepared=[];$reasons=['no_detail'=>0,'became_protected'=>0,'country_conflict'=>0,'no_unique_name'=>0,'critical_qualifier'=>0,'pair_excluded'=>0,'coordinate_conflict'=>0,'low_information'=>0,'prepared'=>0];
        foreach($plan['target_ids']??[] as $id){$id=(int)$id;if(isset($manual[$id])||isset($mapped[$id])){$reasons['became_protected']++;continue;}$d=$detailBy[$id]??null;if(!$d){$reasons['no_detail']++;continue;}$ctx=$context[$id]??null;if(!$ctx){$reasons['country_conflict']++;continue;}$country=(int)$ctx['country_id'];$detailCountry=fc_country($d['state']??'');if($detailCountry!==null&&$detailCountry!==$country){$reasons['country_conflict']++;continue;}$name=trim((string)($d['name']??''));$key=hmadr_key($name);$targets=$key===''?[]:array_keys($index[$country][$key]??[]);if(count($targets)!==1){$reasons['no_unique_name']++;continue;}$target=(int)$targets[0];if(isset($excluded[$id][$target])){$reasons['pair_excluded']++;continue;}$bestCritical=false;foreach($names[$target]??[] as $tn)if(hmadr_critical($name)===hmadr_critical((string)$tn)){$bestCritical=true;break;}if(!$bestCritical){$reasons['critical_qualifier']++;continue;}$tokens=hmadr_identity_tokens($name);$h=$hotels[$target];$dist=fc_dist($d['latitude']??null,$d['longitude']??null,$h['latitude']??null,$h['longitude']??null);if($dist!==null&&$dist>5000){$reasons['coordinate_conflict']++;continue;}$place=fc_place([$d['region']??'',$d['town']??''],[$h['region_name']??'',$h['subregion_name']??'']);if(count(array_unique($tokens))<2 && !(($dist!==null&&$dist<=100)||$place)){$reasons['low_information']++;continue;}$prepared[]=['anex_hotel_id'=>$id,'country_id'=>$country,'source_name'=>$name,'source_region'=>$d['region']??null,'source_town'=>$d['town']??null,'target_local_hotel_id'=>$target,'target_name'=>$h['name'],'target_region'=>$h['region_name'],'target_subregion'=>$h['subregion_name'],'distance_m'=>$dist===null?null:round($dist,2),'place_match'=>$place,'live'=>(bool)$ctx['live'],'search_count'=>(int)$ctx['search_count'],'rule'=>'sold_direct_details_unique_identity_geo_guard'];$reasons['prepared']++;}
        usort($prepared,static fn($a,$b)=>(int)$b['live']<=>(int)$a['live'] ?: $b['search_count']<=>$a['search_count'] ?: $a['anex_hotel_id']<=>$b['anex_hotel_id']);$db->commit();return ['status'=>'completed','operation_id'=>$operation,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>(int)($details['supplier_calls']??0),'details_stats'=>array_intersect_key($details,array_flip(['requested','ok','empty','errors','supplier_calls','target_sha256'])),'reason_counts'=>$reasons,'prepared'=>$prepared];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
