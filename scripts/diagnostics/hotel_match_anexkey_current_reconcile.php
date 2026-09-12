<?php
declare(strict_types=1);
/** MATCH #1971. Read-only CURRENT reconciliation; plan is injected by workflow. */
const HM_OPERATION='hotel-match-anexkey-current-reconcile-1971-20260912-v2';
function hm_out(array $x,int $rc=0):void{echo 'MATCH_CURRENT_JSON:'.json_encode($x,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;exit($rc);}
function hm_select_in(PDO $db,string $sql,array $ids):array{
    if(!$ids)return[];$marks=implode(',',array_fill(0,count($ids),'?'));$q=$db->prepare(str_replace('__IN__',$marks,$sql));$q->execute(array_values($ids));return $q->fetchAll(PDO::FETCH_ASSOC);
}
if(in_array('--self-test',$_SERVER['argv']??[],true)){
    $fixture=['tourvisor_id'=>'10','anex_id'=>'20','andromeda_id'=>'30'];
    if((int)$fixture['tourvisor_id']!==10||(int)$fixture['anex_id']!==20||(int)$fixture['andromeda_id']!==30)exit(2);
    echo "MATCH current reconcile self-test PASS; DB0/network0\n";exit;
}
$db=null;
try{
    if(PHP_SAPI!=='cli'||!defined('HM_PLAN_B64'))throw new RuntimeException('plan_required');
    $raw=base64_decode(HM_PLAN_B64,true);if($raw===false)throw new RuntimeException('plan_decode');
    $plan=json_decode($raw,true,512,JSON_THROW_ON_ERROR);if(($plan['operation_id']??'')!==HM_OPERATION||!is_array($plan['triples']??null))throw new RuntimeException('plan_guard');
    if(count($plan['triples'])>500)throw new RuntimeException('plan_cap');
    $root=realpath(getcwd());if(!$root||basename($root)!=='anytoour.ru')throw new RuntimeException('root');
    require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');$db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
    $aids=array_values(array_unique(array_map(fn($r)=>(string)$r['anex_id'],$plan['triples'])));$dids=array_values(array_unique(array_map(fn($r)=>(string)$r['andromeda_id'],$plan['triples'])));
    $maps=hm_select_in($db,'SELECT anex_hotel_id,catalog_hotel_id,enabled,scope,approval_policy FROM anex_hotel_search_mappings WHERE anex_hotel_id IN (__IN__) ORDER BY anex_hotel_id,catalog_hotel_id',$aids);
    $dec=hm_select_in($db,'SELECT anex_hotel_id FROM anex_hotel_decisions WHERE anex_hotel_id IN (__IN__) ORDER BY anex_hotel_id',$aids);
    $exc=hm_select_in($db,'SELECT anex_hotel_id FROM anex_review_pair_exclusions WHERE anex_hotel_id IN (__IN__) ORDER BY anex_hotel_id',$aids);
    $and=hm_select_in($db,"SELECT external_hotel_id,local_hotel_id,decision_status,evidence_sha256,catalog_sha256 FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id IN (__IN__) ORDER BY external_hotel_id",$dids);
    $db->exec('ROLLBACK');
    $mi=[];foreach($maps as $r)$mi[(string)$r['anex_hotel_id']][]=$r;$di=[];foreach($dec as $r)$di[(string)$r['anex_hotel_id']]=true;$ei=[];foreach($exc as $r)$ei[(string)$r['anex_hotel_id']]=true;$ai=[];foreach($and as $r)$ai[(string)$r['external_hotel_id']]=$r;
    $confA=array_fill_keys(array_map('strval',$plan['provider_conflicts']['anex']??[]),true);$confD=array_fill_keys(array_map('strval',$plan['provider_conflicts']['andromeda']??[]),true);
    $rows=[];$counts=[];
    foreach($plan['triples'] as $t){
        $aid=(string)$t['anex_id'];$did=(string)$t['andromeda_id'];$tv=(int)$t['tourvisor_id'];$bucket='needs_more_evidence';$reason=[];$am=$mi[$aid]??[];$ar=$ai[$did]??null;
        if(isset($confA[$aid])||isset($confD[$did])){$bucket='provider_one_to_many_conflict';$reason[]='provider_conflict';}
        elseif(isset($di[$aid])||isset($ei[$aid])){$bucket='protected_manual_or_exclusion';$reason[]='anex_protected';}
        elseif(count($am)>1){$bucket='current_anex_multiple_rows';$reason[]='multiple_mapping_rows';}
        elseif($ar===null){$bucket='andromeda_identity_missing';$reason[]='registry_missing';}
        elseif((string)$ar['decision_status']==='conflict'){$bucket='current_andromeda_conflict';$reason[]='andromeda_conflict';}
        else{
            $anexLocal=count($am)===1?(int)$am[0]['catalog_hotel_id']:null;$anexEnabled=count($am)===1?(int)$am[0]['enabled']:null;$andLocal=$ar['local_hotel_id']===null?null:(int)$ar['local_hotel_id'];$andStatus=(string)$ar['decision_status'];
            if($anexLocal!==null&&$anexLocal!==$tv){$bucket='current_anex_target_mismatch';$reason[]='anex_local_differs';}
            elseif($andStatus==='accepted'&&$andLocal!==$tv){$bucket='current_andromeda_target_mismatch';$reason[]='andromeda_local_differs';}
            elseif($anexLocal===$tv&&$anexEnabled===1&&$andStatus==='accepted'&&$andLocal===$tv){$bucket='existing_exact_triple';}
            elseif($anexLocal===$tv&&$anexEnabled===1&&$andStatus==='pending'&&$andLocal===null){$bucket='safe_missing_andromeda_side';}
            elseif($anexLocal===null&&$andStatus==='accepted'&&$andLocal===$tv){$bucket='safe_missing_anex_side';}
            elseif($anexLocal===$tv&&$anexEnabled!==1){$bucket='disabled_anex_mapping_protected';$reason[]='disabled_mapping';}
            elseif($andStatus==='pending'&&$andLocal!==null){$bucket='pending_with_local_guard';$reason[]='pending_local_nonnull';}
            else{$reason[]='insufficient_current_anchor';}
        }
        $counts[$bucket]=($counts[$bucket]??0)+1;
        $rows[]=array_merge($t,['bucket'=>$bucket,'reason'=>$reason,'current_anex_rows'=>$am,'current_andromeda'=>$ar]);
    }
    ksort($counts);usort($rows,fn($a,$b)=>[$a['bucket'],-(int)($a['recurrence']??0),(int)$a['tourvisor_id']]<=>[$b['bucket'],-(int)($b['recurrence']??0),(int)$b['tourvisor_id']]);
    hm_out(['status'=>'completed','operation_id'=>HM_OPERATION,'evidence_family'=>$plan['evidence_family']??null,'evidence_sha256'=>$plan['evidence_sha256']??null,'examined'=>count($rows),'counts'=>$counts,'rows'=>$rows,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'no_replay'=>true]);
}catch(Throwable $e){try{if($db instanceof PDO&&$db->inTransaction())$db->rollBack();}catch(Throwable $ignored){}hm_out(['status'=>'failed','operation_id'=>HM_OPERATION,'safe_message'=>preg_match('/^[a-z0-9_]+$/i',$e->getMessage())?$e->getMessage():'guard_failure','database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'no_replay'=>true],2);}
