<?php
/** Exact phase2 retained130 apply; only existing AnyTourProfileEnrichmentV1 writes SQL. */
declare(strict_types=1);
require_once __DIR__.'/local_profile_mass_apply_4191.php';
require_once __DIR__.'/local_profile_mass_plan2_4191.php';

const LPMA2_OPERATION = 'int-andromeda-local-profile-mass-apply130-phase2-4191-20261002-v1';
const LPMA2_BATCH = 'local4191-mass-retained130-phase2-20261002';
const LPMA2_PLAN_OPERATION = 'int-andromeda-local-profile-mass-plan2-4191-20261002-v1';
const LPMA2_PLAN_BATCH = 'local4191-mass-retained2-20261002';
const LPMA2_PLAN_SHA = 'aafbc0aa015d485817ae9d851a6200f677488ea5538ab73447f2ee1dc67c84e1';
const LPMA2_PLAN_SOURCE = 'a54255507643501abdeca150aeae19b84cb586f6';
const LPMA2_PLAN_CONTROL = '00cc9b3ba28319c85282a993c6ca0d57558b604e';
const LPMA2_PROFILES = 130;
const LPMA2_FIELDS = 1312;
const LPMA2_BATCHES = 3;

function lpma2_identity(array $value): void {
    lpp_need(($value['schema_version'] ?? null) === 1
        && ($value['operation_id'] ?? null) === LPMA2_PLAN_OPERATION
        && ($value['batch'] ?? null) === LPMA2_PLAN_BATCH
        && ($value['source_sha'] ?? null) === LPMA2_PLAN_SOURCE
        && ($value['control_source_sha'] ?? null) === LPMA2_PLAN_CONTROL
        && ($value['demand_through'] ?? null) === '2026-10-01 23:19:31'
        && ($value['predecessor_private_plan_sha256'] ?? null) === LPM2_PARENT_SHA
        && ($value['safe_to_apply'] ?? null) === false, 'sealed_identity');
}

function lpma2_batches(array $index, array $protected, array $predecessor, callable $load): array {
    lpma2_identity($index);
    $counts = [
        'D1_OVERLAP_HELD'=>70,
        'HISTORICAL_366_HELD'=>366,
        'PLAN_BOUND_DEFERRED'=>9142,
        'PREDECESSOR_2000_HELD'=>2000,
        'PRIOR_OR_EDITORIAL_HELD'=>714,
        'RETAINED_DELTA_PREPARED'=>130,
        'SCREENED_FIELDS_PRESENT'=>1707,
        'SOURCE_MISSING'=>1868,
        'SOURCE_PROVENANCE_HELD'=>2,
    ];
    lpp_need(($index['active_profiles'] ?? null)===15999
        && ($index['source_plans_prepared'] ?? null)===2000
        && ($index['profiles_with_delta'] ?? null)===LPMA2_PROFILES
        && ($index['planned_fields'] ?? null)===LPMA2_FIELDS
        && ($index['classification_counts'] ?? null)===$counts
        && is_array($index['rows'] ?? null)&&array_is_list($index['rows'])
        && is_array($index['batches'] ?? null)&&array_is_list($index['batches'])
        && count($index['batches'])===LPMA2_BATCHES, 'sealed_counts');
    lpp_need(($predecessor['state']??null)==='verified_terminal_predecessor'
        && count($predecessor['ownIds']??[])===LPM2_PARENT_PROFILES
        && count($predecessor['localIds']??[])===LPM2_PARENT_PROFILES, 'predecessor_state');
    $predOwn=array_fill_keys($predecessor['ownIds'],true);$predLocal=array_fill_keys($predecessor['localIds'],true);
    $rows=[];$expected=[];$seenOwn=[];$seenLocal=[];$out=[];$fields=0;
    foreach($index['rows'] as $row){
        $own=$row['anytourHotelId']??null;
        lpp_need(is_int($own)&&$own>0&&!isset($rows[$own]),'census_identity');$rows[$own]=$row;
        if(($row['state']??null)==='RETAINED_DELTA_PREPARED')$expected[$own]=true;
    }
    lpp_need(count($expected)===LPMA2_PROFILES,'selected_count');
    foreach($index['batches'] as $i=>$meta){
        lpp_need(is_array($meta)&&($meta['file']??null)===sprintf('mass2-batch-%03d.json',$i+1)
            &&is_string($meta['sha256']??null)&&preg_match('/^[a-f0-9]{64}$/D',$meta['sha256'])===1,'batch_path');
        $bytes=$load($meta['file']);lpp_need(is_string($bytes)&&hash_equals($meta['sha256'],hash('sha256',$bytes)),'batch_digest');
        $batch=json_decode($bytes,true,512,JSON_THROW_ON_ERROR);lpma2_identity($batch);
        $plan=$batch['owner_plan']??null;
        lpp_need(is_array($plan)&&is_array($plan['contentScope']??null)&&array_is_list($plan['contentScope'])
            &&count($plan['contentScope'])>=1&&count($plan['contentScope'])<=250
            &&($plan['limit']??null)===($meta['scope_profiles']??null)
            &&($plan['planSha256']??null)===($meta['plan_sha256']??null)
            &&($plan['demandThrough']??null)===$index['demand_through'],'batch_scope');
        foreach($plan['contentScope'] as $scope){
            $own=$scope['anytourHotelId']??null;$local=$scope['localHotelId']??null;
            lpp_need(is_int($own)&&is_int($local)&&isset($rows[$own])
                &&!isset($protected['own'][$own])&&!isset($protected['local'][$local])
                &&!isset($predOwn[$own])&&!isset($predLocal[$local])
                &&($rows[$own]['missingFields']??null)===($scope['fields']??null),'excluded_scope');
        }
        $selected=lpm_validate_plan($plan,$plan['contentScope'],$rows);
        lpp_need(count($selected)===($meta['profiles']??null)&&$selected!==[],'batch_selected');
        $fieldCounts=[];
        foreach($selected as $own=>$item){
            $local=$item['localHotelId'];
            lpp_need(isset($expected[$own])&&!isset($seenOwn[$own])&&!isset($seenLocal[$local])
                &&$item['expectedRevision']===1,'selected_identity');
            $before=json_decode($item['beforeProfileJson'],true,512,JSON_THROW_ON_ERROR);
            foreach($item['patch'] as $field=>$value){
                lpp_need(in_array($field,LPP_FIELDS,true),'patch_field');
                $old=$before;foreach(explode('.',$field) as $part)$old=is_array($old)?($old[$part]??null):null;
                lpp_need(lpm_missing($old)&&!lpm_missing($value),'nonempty_preserved');
                $fieldCounts[$field]=($fieldCounts[$field]??0)+1;++$fields;
            }
            $seenOwn[$own]=$seenLocal[$local]=true;
        }
        ksort($fieldCounts);
        $out[]=['plan'=>$plan,'rows'=>$rows,'profiles'=>count($selected),'fieldCounts'=>$fieldCounts];
    }
    lpp_need(count($seenOwn)===LPMA2_PROFILES&&count($seenLocal)===LPMA2_PROFILES
        &&array_diff_key($expected,$seenOwn)===[]&&$fields===LPMA2_FIELDS,'cohort_count');
    return $out;
}

function lpma2_execute(array $batches, callable $plan, callable $apply, callable $consume, callable $checkpoint): array {
    $state=['state'=>'held_before_write','profiles_verified'=>0,'fields_verified'=>0,'field_counts'=>[],
        'batches_verified'=>0,'profile_writes'=>0,'provenance_writes'=>0,'readback_verified'=>false,'unknown_batch'=>null];
    $started=false;$number=null;
    try{
        foreach($batches as $batch){
            $old=$batch['plan'];$fresh=$plan($old);lpm_validate_plan($fresh,$old['contentScope'],$batch['rows']);
            lpp_need(hash_equals($old['planSha256'],$fresh['planSha256']),'current_plan_drift');
        }
        $consume();
        foreach($batches as $i=>$batch){
            $number=$i+1;$started=true;$old=$batch['plan'];$applied=$apply($old);$expectedFields=array_sum($batch['fieldCounts']);
            lpp_need(($applied['status']??null)==='committed_verified'
                &&($applied['operation']??null)===LPMA2_OPERATION
                &&($applied['planSha256']??null)===$old['planSha256']
                &&($applied['profilesUpdated']??null)===$batch['profiles']
                &&($applied['profileWrites']??null)===$batch['profiles']
                &&($applied['provenanceWrites']??null)===$batch['profiles']
                &&($applied['fieldsFilled']??null)===$expectedFields
                &&($applied['fieldCounts']??null)===$batch['fieldCounts']
                &&($applied['supplierCalls']??null)===0&&($applied['mappingWrites']??null)===0&&($applied['legacyWrites']??null)===0,
                'apply_readback_contract');
            $state['profiles_verified']+=$batch['profiles'];$state['fields_verified']+=$expectedFields;++$state['batches_verified'];
            foreach($batch['fieldCounts'] as $field=>$count)$state['field_counts'][$field]=($state['field_counts'][$field]??0)+$count;
            $checkpoint($number,$applied);
        }
        lpp_need($state['profiles_verified']===LPMA2_PROFILES&&$state['fields_verified']===LPMA2_FIELDS
            &&$state['batches_verified']===LPMA2_BATCHES,'final_count');
        $state['state']='committed_verified';$state['readback_verified']=true;
        $state['profile_writes']=$state['provenance_writes']=LPMA2_PROFILES;
    }catch(Throwable){
        if($started){$state['state']='unknown_no_replay';$state['unknown_batch']=$number;
            $state['profile_writes']=$state['provenance_writes']='unknown';}
    }
    ksort($state['field_counts']);return $state;
}

function lpma2_main(array $argv): int {
    lpp_need(PHP_SAPI==='cli'&&count($argv)===2&&$argv[1]==='--apply-retained130','disabled');
    $home=(string)getenv('HOME');$root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('LOCAL_PROFILE_APPLY_DIR');
    $head=(string)getenv('LOCAL_PROFILE_SOURCE_SHA');$control=(string)getenv('LOCAL_PROFILE_CONTROL_SHA');
    lpp_need($root===$home.'/www/anytoour.ru'&&realpath($root)===$root&&realpath($dir)===$dir
        &&dirname($dir)===$home.'/.anytoour-int-executor'&&basename($dir)===LPMA2_OPERATION
        &&preg_match('/^[a-f0-9]{40}$/D',$head)===1&&preg_match('/^[a-f0-9]{40}$/D',$control)===1,'runtime_scope');
    $reservation=json_decode(lpp_file($dir.'/reservation.json',65536),true,32,JSON_THROW_ON_ERROR);
    lpp_need(($reservation['operation_id']??null)===LPMA2_OPERATION&&($reservation['source_sha']??null)===$head
        &&($reservation['mode']??null)==='local-profile-apply-4191','reservation');
    $identity=['schema_version'=>1,'operation_id'=>LPMA2_OPERATION,'batch'=>LPMA2_BATCH,'source_sha'=>$head,
        'control_source_sha'=>$control,'plan_source_sha'=>LPMA2_PLAN_SOURCE,'private_plan_sha256'=>LPMA2_PLAN_SHA,
        'requested_profiles'=>LPMA2_PROFILES];
    lpp_save($dir.'/mass2-apply-started.json',$identity);
    $result=['state'=>'held_before_write','profiles_verified'=>0,'fields_verified'=>0,'field_counts'=>[],
        'batches_verified'=>0,'profile_writes'=>0,'provenance_writes'=>0,'readback_verified'=>false,'unknown_batch'=>null];
    try{
        $parent=$home.'/.anytoour-int-executor/'.LPMA2_PLAN_OPERATION;
        $bytes=lpp_file($parent.'/local-mass2-plan.json',32*1024*1024);
        lpp_need(hash_equals(LPMA2_PLAN_SHA,hash('sha256',$bytes)),'private_plan_digest');
        $index=json_decode($bytes,true,512,JSON_THROW_ON_ERROR);lpma2_identity($index);
        $outer=json_decode(lpp_file($parent.'/result.json',65536),true,512,JSON_THROW_ON_ERROR);
        $public=json_decode(lpp_file($parent.'/local-mass2-receipt.json',65536),true,512,JSON_THROW_ON_ERROR);lpma2_identity($public);
        lpp_need(($outer['status']??null)==='complete'&&($outer['mode']??null)==='local-profile-plan-4191'
            &&($outer['operation_id']??null)===LPMA2_PLAN_OPERATION&&($outer['source_sha']??null)===LPMA2_PLAN_SOURCE
            &&($outer['database_writes']??null)===0&&($outer['supplier_calls']??null)===0
            &&($public['state']??null)==='completed_read_only'&&($public['private_plan_sha256']??null)===LPMA2_PLAN_SHA
            &&is_array($outer['local_profile_plan']??null)&&lpm_digest($public)===lpm_digest($outer['local_profile_plan']),'producer_receipt');
        $bootstrap=$root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
        lpp_need(is_file($bootstrap)&&!is_link($bootstrap)&&realpath($bootstrap)===$bootstrap,'bootstrap');
        $_SERVER['DOCUMENT_ROOT']=$root;require_once $bootstrap;require_once dirname(__DIR__,2).'/v2/data/anytour-profile-enrichment-v1.php';
        $db=v2_data_db();lpp_need($db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql','mysql_required');
        $protected=lpma_protected($db,lpp_d1($home));$predecessor=lpm2_parent($home);
        $batches=lpma2_batches($index,$protected,$predecessor,static fn(string $file):string=>lpp_file($parent.'/'.$file,32*1024*1024));
        $owner=new AnyTourProfileEnrichmentV1($db);
        $result=lpma2_execute($batches,
            static fn(array $p):array=>$owner->plan($p['limit'],$p['demandThrough'],$p['contentScope'],true),
            static fn(array $p):array=>$owner->apply(LPMA2_OPERATION,$p['limit'],$p['demandThrough'],$p['planSha256'],$p['contentScope'],true),
            static fn()=>lpp_save($parent.'/mass2-apply130-consumed.json',$identity),
            static fn(int $i,array $applied)=>lpp_save($dir.'/'.sprintf('mass2-apply-batch-%03d.json',$i),$identity+['owner_receipt'=>$applied]));
    }catch(Throwable){}
    $receipt=$identity+$result+['supplier_calls'=>0,'provider_http_calls'=>0,'mapping_writes'=>0,'legacy_writes'=>0,'schema_writes'=>0];
    lpp_save($dir.'/local-mass2-apply-receipt.json',$receipt);echo lpp_json($receipt)."\n";
    return $result['state']==='committed_verified'?0:2;
}
if(PHP_SAPI==='cli'&&realpath((string)($argv[0]??''))===__FILE__){
    try{exit(lpma2_main($argv));}catch(Throwable){fwrite(STDERR,"local_mass2_apply_failed_no_replay\n");exit(2);}
}
