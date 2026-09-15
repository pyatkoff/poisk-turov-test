<?php
declare(strict_types=1);

putenv('MATCH_TEST_LIBRARY=1');
require_once __DIR__ . '/hotel_match_live_residual_current_review.php';

const MCA_OP = 'hotel-match-live-residual-current-apply-1971-20260915-v1';
const MCA_REVIEW_OP = 'hotel-match-live-residual-current-review-1971-20260915-v1';
const MCA_REVIEW_SHA256 = '0cee0ae7984c1efef0466fc2465d50accb25718ff85e88b17e5c80d78312d9c9';

function mca_candidate_key(array $row): string {
    return (string)($row['kind'] ?? '') . '|' . (string)($row['supplier_namespace'] ?? '') . '|' . (string)($row['external_hotel_id'] ?? '');
}
function mca_eligible_plan(array $review): array {
    $out=[];
    foreach ($review['auto_accept_candidates'] ?? [] as $row) {
        if (!is_array($row)) continue;
        $kind=(string)($row['kind'] ?? ''); $reason=(string)($row['reason'] ?? '');
        if ($kind==='operator_native') {
            if ((string)($row['supplier_namespace'] ?? '')==='operator_5') continue;
            if ($reason!=='operator_native_accepted_andromeda_bridge') continue;
        } elseif ($kind==='andromeda_catalog') {
            if (!in_array($reason,['unique_exact_name_or_alias','strong_fuzzy_winner'],true)) continue;
        } else continue;
        $id=(string)($row['external_hotel_id'] ?? ''); $target=(int)($row['target'] ?? 0);
        if ($id==='' || $target<=0) continue;
        $key=mca_candidate_key($row); if(isset($out[$key]) && (int)$out[$key]['target']!==$target) throw new RuntimeException('plan_duplicate_target_conflict');
        $out[$key]=$row;
    }
    return $out;
}
function mca_promotion_json(array $prior,array $promotion): array {
    return ['prior_evidence'=>$prior,'promotion'=>$promotion];
}
function mca_read_review(string $path): array {
    $raw=@file_get_contents($path); if(!is_string($raw)||$raw==='')throw new RuntimeException('review_result_missing');
    if(hash('sha256',$raw)!==MCA_REVIEW_SHA256)throw new RuntimeException('review_result_hash');
    $x=json_decode($raw,true,64,JSON_THROW_ON_ERROR);
    if(!is_array($x)||($x['operation_id']??'')!==MCA_REVIEW_OP||($x['state']??'')!=='completed_read_only'||($x['no_replay']??false)!==true||($x['db_writes']??-1)!==0)throw new RuntimeException('review_result_contract');
    return $x;
}
function mca_core8(PDO $db): array {
    $ids=[];$names=[];foreach(mcr_query($db,'SELECT id,name FROM catalog_countries WHERE is_active=1 ORDER BY id') as $c){$id=(int)$c['id'];if(mcr_is_core8_name((string)$c['name'])){$ids[$id]=(string)$c['name'];$names[trim(mcr_country_key((string)$c['name']))]=$id;}}
    if(count($ids)<6)throw new RuntimeException('core8_country_dictionary_incomplete');return [$ids,$names];
}
function mca_catalog_indexes(PDO $db,array $coreIds): array {
    $ph=implode(',',array_fill(0,count($coreIds),'?'));
    $rows=mcr_query($db,"SELECT id,country_id,country_name,region_id,region_name,subregion_id,subregion_name,name,category,latitude,longitude,is_active FROM catalog_hotels WHERE is_active=1 AND country_id IN ($ph) ORDER BY country_id,id",array_keys($coreIds));
    $hotels=[];$forms=[];$exact=[];$token=[];foreach($rows as $h){$id=(int)$h['id'];$hotels[$id]=$h;$forms[$id]=[(string)$h['name']];}
    foreach(mcr_query($db,"SELECT a.hotel_id,a.alias FROM hotel_aliases a JOIN catalog_hotels h ON h.id=a.hotel_id WHERE h.is_active=1 AND h.country_id IN ($ph) ORDER BY a.hotel_id,a.id",array_keys($coreIds)) as $a){$id=(int)$a['hotel_id'];if(isset($forms[$id]))$forms[$id][]=(string)$a['alias'];}
    foreach($forms as $id=>$list){$cid=(int)$hotels[$id]['country_id'];foreach(array_values(array_unique($list)) as $raw){$n=mcr_norm($raw);if($n==='')continue;$exact[$cid][$n][]=$id;foreach(mcr_tokens($raw) as $t)$token[$cid][$t][$id]=true;}}
    return [$hotels,$forms,$exact,$token];
}
function mca_state_country(PDO $db,array $hotels,array $coreIds): array {
    $counts=[];
    foreach(mcr_query($db,"SELECT external_hotel_id,local_hotel_id,evidence_json FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IS NOT NULL") as $r){$local=(int)$r['local_hotel_id'];if(!isset($hotels[$local]))continue;$sk=mcr_state_key(mcr_evidence((string)$r['evidence_json']));if($sk!==null)$counts[$sk][(int)$hotels[$local]['country_id']]=($counts[$sk][(int)$hotels[$local]['country_id']]??0)+1;}
    $map=[];foreach($counts as $sk=>$c){arsort($c,SORT_NUMERIC);$cid=(int)array_key_first($c);$total=array_sum($c);$top=(int)$c[$cid];if(isset($coreIds[$cid])&&$top>=3&&$top/$total>=0.98)$map[$sk]=$cid;}return $map;
}
function mca_read_current_identity(PDO $db,string $ns,string $id): ?array {
    $r=mcr_query($db,'SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status,evidence_sha256,evidence_json FROM andromeda_hotel_identities WHERE supplier_namespace=? AND external_hotel_id=? FOR UPDATE',[$ns,$id]);return count($r)===1?$r[0]:null;
}
function mca_validate_catalog(array $plan,array $current,array $stateCountry,array $hotels,array $forms,array $exact,array $token): array {
    if($current['decision_status']!=='pending'||$current['local_hotel_id']!==null)return ['ok'=>false,'reason'=>'current_state_protected'];
    $e=mcr_evidence((string)$current['evidence_json']);$sk=mcr_state_key($e);$cid=$sk!==null?($stateCountry[$sk]??null):null;
    if($cid===null || !isset($hotels[(int)$plan['target']]) || (int)$hotels[(int)$plan['target']]['country_id']!==$cid)return ['ok'=>false,'reason'=>'country_or_target_current_conflict'];
    $sel=mcr_select_candidate(mcr_names($e),(int)$cid,mcr_points($e),$hotels,$forms,$exact,$token);
    if(($sel['route']??'')!=='auto_accept_candidate'||(int)($sel['target']??0)!==(int)$plan['target'])return ['ok'=>false,'reason'=>'candidate_changed','current_selection'=>$sel];
    if(($plan['reason']??'')==='unique_exact_name_or_alias'&&($sel['reason']??'')!=='unique_exact_name_or_alias')return ['ok'=>false,'reason'=>'exact_not_still_unique','current_selection'=>$sel];
    if(($plan['reason']??'')==='strong_fuzzy_winner'){
        if(($sel['reason']??'')!=='strong_fuzzy_winner'||(float)($sel['score']??0)<0.88||(float)($sel['margin']??0)<0.18)return ['ok'=>false,'reason'=>'fuzzy_threshold_changed','current_selection'=>$sel];
    }
    return ['ok'=>true,'evidence'=>$e,'selection'=>$sel];
}
function mca_validate_operator(PDO $db,array $plan,array $current,array $hotels): array {
    if($current['decision_status']!=='pending'||$current['local_hotel_id']!==null)return ['ok'=>false,'reason'=>'current_state_protected'];
    $ns=(string)$current['supplier_namespace'];if($ns==='operator_5')return ['ok'=>false,'reason'=>'operator5_excluded'];
    $e=mcr_evidence((string)$current['evidence_json']);$refs=mcr_provider_bridges($e);if(!$refs)return ['ok'=>false,'reason'=>'no_andromeda_refs'];
    $targets=[];foreach($refs as $aid){$r=mcr_query($db,"SELECT local_hotel_id,decision_status FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=? FOR UPDATE",[$aid]);if(count($r)!==1||$r[0]['decision_status']!=='accepted'||$r[0]['local_hotel_id']===null)return ['ok'=>false,'reason'=>'andromeda_ref_not_accepted','andromeda_id'=>$aid];$targets[(int)$r[0]['local_hotel_id']]=true;}
    if(count($targets)!==1)return ['ok'=>false,'reason'=>'andromeda_ref_target_conflict'];$target=(int)array_key_first($targets);if($target!==(int)$plan['target']||!isset($hotels[$target]))return ['ok'=>false,'reason'=>'operator_target_changed','target'=>$target];
    $guard=mcr_direct_target_guard(mcr_names($e),mcr_points($e),$hotels[$target]);if(!($guard['ok']??false))return ['ok'=>false,'reason'=>$guard['reason']??'direct_guard','guard'=>$guard];
    return ['ok'=>true,'evidence'=>$e,'guard'=>$guard];
}
function mca_result_readback_ok(array $row,string $op,int $target): bool {
    if(($row['decision_status']??'')!=='accepted'||(int)($row['local_hotel_id']??0)!==$target)return false;
    $e=mcr_evidence((string)($row['evidence_json']??''));return (($e['promotion']['operation_id']??'')===$op && (int)($e['promotion']['target_local_hotel_id']??0)===$target);
}

if(getenv('MATCH_APPLY_TEST_LIBRARY')==='1')return;
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$operation=(string)getenv('MATCH_OPERATION_ID');$sourceSha=(string)getenv('MATCH_SOURCE_SHA');if($operation!==MCA_OP||!preg_match('/^[0-9a-f]{40}$/D',$sourceSha))throw new RuntimeException('operation_or_source_guard');
$home=(string)getenv('HOME');if($home==='')throw new RuntimeException('home_missing');$dir=$home.'/.anytoour-match/operations/'.MCA_OP;$reviewPath=$home.'/.anytoour-match/operations/'.MCA_REVIEW_OP.'/result.json';
if(!is_file($dir.'/reservation.json'))throw new RuntimeException('reservation_missing');$reservation=mcr_evidence((string)file_get_contents($dir.'/reservation.json'));if(($reservation['operation_id']??'')!==MCA_OP||($reservation['source_sha']??'')!==$sourceSha||($reservation['state']??'')!=='reserved_before_db_access')throw new RuntimeException('reservation_contract');
$review=mca_read_review($reviewPath);$plans=mca_eligible_plan($review);if(count($plans)!==49)throw new RuntimeException('eligible_plan_count_'.count($plans));
$root=realpath(getcwd());if(!$root||basename($root)!=='anytoour.ru')throw new RuntimeException('root_guard');require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
$db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET SESSION innodb_lock_wait_timeout=30');$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->beginTransaction();$committed=false;
try{
    $required=['andromeda_hotel_identities','catalog_hotels','catalog_countries','hotel_aliases'];$p=mcr_query($db,'SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('.implode(',',array_fill(0,count($required),'?')).')',$required);$eng=array_column($p,'ENGINE','TABLE_NAME');foreach($required as $t)if(strtoupper((string)($eng[$t]??''))!=='INNODB')throw new RuntimeException('transactional_table_required_'.$t);
    [$coreIds,$countryNames]=mca_core8($db);[$hotels,$forms,$exact,$token]=mca_catalog_indexes($db,$coreIds);$stateCountry=mca_state_country($db,$hotels,$coreIds);
    $holds=[];$validated=[];
    foreach($plans as $key=>$plan){$kind=(string)$plan['kind'];$ns=$kind==='andromeda_catalog'?'andromeda_catalog':(string)$plan['supplier_namespace'];$id=(string)$plan['external_hotel_id'];$cur=mca_read_current_identity($db,$ns,$id);if($cur===null){$holds[]=['key'=>$key,'reason'=>'identity_missing'];continue;}
        $check=$kind==='andromeda_catalog'?mca_validate_catalog($plan,$cur,$stateCountry,$hotels,$forms,$exact,$token):mca_validate_operator($db,$plan,$cur,$hotels);
        if(!($check['ok']??false)){$holds[]=['key'=>$key,'reason'=>$check['reason']??'validation_failed','details'=>array_diff_key($check,['evidence'=>true])];continue;}
        $validated[$key]=['plan'=>$plan,'current'=>$cur,'evidence'=>$check['evidence']];
    }
    $update=$db->prepare("UPDATE andromeda_hotel_identities SET local_hotel_id=?, decision_status='accepted', evidence_sha256=?, evidence_json=? WHERE supplier_namespace=? AND external_hotel_id=? AND decision_status='pending' AND local_hotel_id IS NULL AND evidence_sha256 <=> ?");$written=[];
    foreach($validated as $key=>$x){$plan=$x['plan'];$cur=$x['current'];$target=(int)$plan['target'];$promotion=['operation_id'=>MCA_OP,'rule'=>(string)$plan['reason'],'target_local_hotel_id'=>$target,'review_operation_id'=>MCA_REVIEW_OP,'review_result_sha256'=>MCA_REVIEW_SHA256,'server_current_revalidated'=>true,'coordinate_conflict_gt5km_blocked'=>true,'manual_and_existing_state_preserved'=>true];$newEvidence=mca_promotion_json($x['evidence'],$promotion);$json=mcr_json($newEvidence);$sha=hash('sha256',$json);$update->execute([$target,$sha,$json,(string)$cur['supplier_namespace'],(string)$cur['external_hotel_id'],$cur['evidence_sha256']]);if($update->rowCount()!==1)throw new RuntimeException('concurrency_guard_'.$key);$written[]=['supplier_namespace'=>(string)$cur['supplier_namespace'],'external_hotel_id'=>(string)$cur['external_hotel_id'],'target'=>$target,'rule'=>(string)$plan['reason'],'new_sha'=>$sha];}
    $db->commit();$committed=true;
    $readback=[];foreach($written as $w){$r=mcr_query($db,'SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status,evidence_sha256,evidence_json FROM andromeda_hotel_identities WHERE supplier_namespace=? AND external_hotel_id=?',[$w['supplier_namespace'],$w['external_hotel_id']]);if(count($r)!==1||!mca_result_readback_ok($r[0],MCA_OP,(int)$w['target'])||(string)$r[0]['evidence_sha256']!==(string)$w['new_sha'])throw new RuntimeException('post_commit_readback_'.$w['supplier_namespace'].'_'.$w['external_hotel_id']);$readback[]=['supplier_namespace'=>$w['supplier_namespace'],'external_hotel_id'=>$w['external_hotel_id'],'local_hotel_id'=>(int)$r[0]['local_hotel_id'],'decision_status'=>$r[0]['decision_status'],'evidence_sha256'=>$r[0]['evidence_sha256'],'rule'=>$w['rule']];}
    $byNs=[];$byRule=[];foreach($readback as $r){$byNs[$r['supplier_namespace']]=($byNs[$r['supplier_namespace']]??0)+1;$byRule[$r['rule']]=($byRule[$r['rule']]??0)+1;}ksort($byNs);ksort($byRule);
    $result=['schema'=>'hotel-match-live-residual-current-apply/1','operation_id'=>MCA_OP,'source_sha'=>$sourceSha,'state'=>'completed_committed','server_current'=>true,'review_operation_id'=>MCA_REVIEW_OP,'review_result_sha256'=>MCA_REVIEW_SHA256,'planned'=>count($plans),'validated'=>count($validated),'written'=>count($written),'holds_count'=>count($holds),'holds'=>$holds,'written_by_namespace'=>$byNs,'written_by_rule'=>$byRule,'post_commit_readback'=>$readback,'supplier_calls'=>0,'external_calls'=>0,'booking_calls'=>0,'mapping_writes'=>count($written),'no_replay'=>true,'completed_at'=>gmdate('c')];
    $sha=mcr_write_exclusive($dir.'/result.json',$result);$rr=mcr_evidence((string)file_get_contents($dir.'/result.json'));$ok=hash('sha256',(string)file_get_contents($dir.'/result.json'))===$sha&&($rr['state']??'')==='completed_committed'&&count($rr['post_commit_readback']??[])===count($written);
    $receipt=['operation_id'=>MCA_OP,'source_sha'=>$sourceSha,'state'=>'completed_committed','result_sha256'=>$sha,'readback_verified'=>$ok,'planned'=>count($plans),'written'=>count($written),'holds_count'=>count($holds),'post_commit_rows'=>count($readback),'supplier_calls'=>0,'external_calls'=>0,'booking_calls'=>0,'no_replay'=>true,'completed_at'=>gmdate('c')];mcr_write_exclusive($dir.'/receipt.json',$receipt);if(!$ok)throw new RuntimeException('result_readback');echo mcr_json(['operation_id'=>MCA_OP,'state'=>'completed_committed','planned'=>count($plans),'written'=>count($written),'holds'=>count($holds),'written_by_namespace'=>$byNs,'written_by_rule'=>$byRule,'result_sha256'=>$sha]);
}catch(Throwable $e){if(!$committed&&$db->inTransaction())$db->rollBack();if(!$committed&&!is_file($dir.'/failure.json'))mcr_write_exclusive($dir.'/failure.json',['operation_id'=>MCA_OP,'source_sha'=>$sourceSha,'state'=>'rolled_back','error_class'=>get_class($e),'error'=>substr($e->getMessage(),0,300),'supplier_calls'=>0,'external_calls'=>0,'booking_calls'=>0,'no_replay'=>true,'failed_at'=>gmdate('c')]);throw $e;}
