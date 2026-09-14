<?php
declare(strict_types=1);
if (!defined('FC_LIBRARY_ONLY')) define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/hotel_match_current_bulk_review.php';
const APH_OPERATION='hotel-match-anex-photo-current-review-1971-20260914-v2';
function aph_review(PDO $db,array $evidence,string $op):array{
    if($op!==APH_OPERATION)throw new RuntimeException('operation_scope');
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
    try{
        $coverage=fc_coverage($db);[$hotels,$names,$strict,$broad,$places,$scope]=mbr_catalog($db);
        $manual=array_fill_keys(array_map('intval',$db->query('SELECT anex_hotel_id FROM anex_hotel_decisions')->fetchAll(PDO::FETCH_COLUMN)),true);
        $existing=[];foreach($db->query('SELECT anex_hotel_id,catalog_hotel_id,enabled,scope,approval_policy FROM anex_hotel_search_mappings ORDER BY anex_hotel_id,catalog_hotel_id')->fetchAll(PDO::FETCH_ASSOC)as$r)$existing[(int)$r['anex_hotel_id']][]=$r;
        $excluded=[];foreach($db->query('SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions')->fetchAll(PDO::FETCH_ASSOC)as$r)$excluded[(int)$r['anex_hotel_id']][(int)$r['catalog_hotel_id']]=true;
        $st=[];foreach($db->query('SELECT * FROM anex_hotels ORDER BY anex_hotel_id')->fetchAll(PDO::FETCH_ASSOC)as$r)$st[(int)$r['anex_hotel_id']]=$r;
        $obs=[];foreach($db->query('SELECT * FROM anex_search_hotel_observations ORDER BY search_count DESC,last_seen_utc DESC,anex_hotel_id')->fetchAll(PDO::FETCH_ASSOC)as$r)$obs[(int)$r['anex_hotel_id']]=$r;
        $and=[];$sql="SELECT i.local_hotel_id,i.evidence_json,h.country_id FROM andromeda_hotel_identities i JOIN catalog_hotels h ON h.id=i.local_hotel_id WHERE i.supplier_namespace='andromeda_catalog' AND i.decision_status='accepted' AND i.local_hotel_id IS NOT NULL AND h.country_id IN (1,2,4,8,9,10,12,16)";
        foreach($db->query($sql)->fetchAll(PDO::FETCH_ASSOC)as$r){$src=fc_source($r);foreach([$src['name']??'',$src['lName']??'']as$n){$k=fc_key($n,true,true);if($k!=='')$and[(int)$r['country_id']][$k][(int)$r['local_hotel_id']]=true;}}
        $rows=[];$counts=['evidence'=>0,'ready'=>0,'protected_existing'=>0,'protected_manual'=>0,'country_mismatch'=>0,'pair_exclusion'=>0,'current_auto'=>0,'andromeda_exact_bridge'=>0,'needs_extra'=>0,'hard_conflict'=>0];
        foreach($evidence as$e){$counts['evidence']++;$id=(int)($e['anex_hotel_id']??0);$hc=(int)($e['hotelcode']??0);$country=(int)($e['country_id']??0);$url=(string)($e['page_url']??'');$u=parse_url($url);$photoValid=$id>0&&$id===$hc&&($u['scheme']??'')==='https'&&in_array(strtolower((string)($u['host']??'')),['anextour.ru','www.anextour.ru'],true);
            if(!$photoValid){$rows[]=['anex_hotel_id'=>$id,'status'=>'invalid_evidence'];continue;}
            if(isset($manual[$id])){$counts['protected_manual']++;$rows[]=['anex_hotel_id'=>$id,'status'=>'protected_manual'];continue;}
            if(isset($existing[$id])){$counts['protected_existing']++;$rows[]=['anex_hotel_id'=>$id,'status'=>'protected_existing','current_mappings'=>$existing[$id]];continue;}
            $o=$obs[$id]??[];$s=$st[$id]??[];$currentCountry=(int)($o['country_id']??0);if(!$currentCountry)$currentCountry=(int)(fc_country($s['api_country']??'')??0);if($currentCountry!==$country){$counts['country_mismatch']++;$rows[]=['anex_hotel_id'=>$id,'status'=>'country_mismatch','evidence_country'=>$country,'current_country'=>$currentCountry];continue;}
            $source=['observed'=>isset($obs[$id]),'search_count'=>(int)($o['search_count']??0),'last_seen_utc'=>$o['last_seen_utc']??null,'names'=>[$o['hotel_name']??'',$s['api_name']??'',$s['xml_name']??'',$s['xml_alternate_name']??''],'places'=>[$s['api_region']??'',$s['api_town']??''],'latitude'=>$s['latitude']??null,'longitude'=>$s['longitude']??null];
            $review=mbr_review_anex($source,$id,$country,$hotels,$names,$strict,$broad,$places);$status='needs_extra';$target=$review['target']['local_hotel_id']??null;
            if(($review['bucket']??'')==='hard_conflict'){$status='hard_conflict';$counts['hard_conflict']++;}
            elseif(($review['bucket']??'')==='auto_accept'&&$target!==null){if(isset($excluded[$id][(int)$target])){$status='pair_exclusion';$counts['pair_exclusion']++;}else{$status='ready_current_auto';$counts['ready']++;$counts['current_auto']++;}}
            else{
                $bridge=[];$bridgeKey=null;foreach(array_values(array_unique(array_filter(array_map('strval',$source['names']),fn($v)=>trim($v)!=='')))as$n){$k=fc_key($n,true,true);if($k==='')continue;foreach(array_keys($and[$country][$k]??[])as$t)$bridge[(int)$t]=true;if(count(fc_tokens($n,true))>=2)$bridgeKey=$k;}
                $ids=array_map('intval',array_keys($bridge));sort($ids,SORT_NUMERIC);
                if(count($ids)===1&&$bridgeKey!==null){$t=$ids[0];$guard=mbr_target_guard($source,$hotels[$t]);if($guard['coordinate_conflict']){$status='hard_conflict';$counts['hard_conflict']++;$review['bridge_guard']=$guard;$review['bridge_target']=mbr_row_target($hotels[$t]);}elseif(isset($excluded[$id][$t])){$status='pair_exclusion';$counts['pair_exclusion']++;}else{$status='ready_andromeda_exact_bridge';$counts['ready']++;$counts['andromeda_exact_bridge']++;$review['bridge_guard']=$guard;$review['bridge_target']=mbr_row_target($hotels[$t]);}}
                else{$counts['needs_extra']++;$review['andromeda_bridge_ids']=$ids;}
            }
            $rows[]=['anex_hotel_id'=>$id,'country_id'=>$country,'status'=>$status,'photo_evidence'=>['page_url'=>$url,'hotelcode'=>$hc,'source_run'=>$e['source_run']??null],'source_names'=>array_values(array_unique(array_filter(array_map('strval',$source['names']),fn($v)=>trim($v)!==''))),'source_places'=>array_values(array_unique(array_filter(array_map('strval',$source['places']),fn($v)=>trim($v)!==''))),'review'=>$review];
        }
        usort($rows,fn($a,$b)=>($a['anex_hotel_id']??0)<=>($b['anex_hotel_id']??0));$db->commit();return['status'=>'completed','operation_id'=>$op,'mode'=>'current_db_photo_hotelcode_review_read_only','counts'=>$counts,'coverage'=>$coverage,'catalog_scope'=>$scope,'rows'=>$rows,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'guards'=>['native_hotelcode_equals_source_id'=>true,'country_current_equal_evidence'=>true,'manual_protected'=>true,'existing_mapping_protected'=>true,'pair_exclusion_protected'=>true,'coordinate_gt_5km_blocked'=>true,'andromeda_bridge_requires_unique_broad_key_and_2_tokens'=>true,'no_replay'=>true]];
    }catch(Throwable$e){if($db->inTransaction())$db->rollBack();return['status'=>'failed','operation_id'=>$op,'reason'=>in_array($e->getMessage(),['operation_scope','country_contract_changed','hotel_scope_limit','alias_scope_limit'],true)?$e->getMessage():'runtime_failure','database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0];}
}
