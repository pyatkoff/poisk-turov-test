<?php
/** Fixed LOCAL26 CLI adapter; the existing canonical owner performs every DB write. */
declare(strict_types=1);

// Its argv[0] guard is false during require: only pure helpers are loaded.
require_once __DIR__.'/anytour_local_official11_apply_v1.php';

const LO26_OPERATION = 'int-andromeda-local-official26-apply-20261122-v1';
const LO26_MODE = 'local-official26-apply-v1';
const LO26_APPROVAL_REF = 'https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-7077944229';
const LO26_MANIFEST_SHA = '7843b25c32c7adb8fa88f262aa870d8a469727ac3681f038ae418b98c0375138';
const LO26_ACTIONS_SHA = 'd3e1c159a107638e069b5e6e003bf9ad085e2188be93ba1a2c78f75080db7c4e';
const LO26_ORIGINAL_SHA = '9ceba785b8cbb25db3e4aaec0ec34d303e559dbe33577175f78a2889c631c79e';
const LO26_DB_HELPER_SHA = LO11_DB_HELPER_SHA;
const LO26_IDS = [4511,10334,8751,4679,14397,13766,7599,14932,12248,4609,12520,12839,13686,13066,4533,7619,9843,9842,10377,4172,4539,4538,4601,14029,12816,14013];
const LO26_FIELDS = ['description','primaryImage','images'];
const LO26_FIELD_CAPS = ['description'=>26,'primaryImage'=>25,'images'=>25];

function lo26_allowed_fields(int $id): array {
    return $id===4172 ? ['description'] : LO26_FIELDS;
}
function lo26_envelope(string $manifest): array {
    $raw=lo11_file($manifest,131072);
    lo11_need(hash_equals(LO26_MANIFEST_SHA,hash('sha256',$raw)),'MANIFEST_DIGEST');
    $data=json_decode($raw,true,128,JSON_THROW_ON_ERROR);
    lo11_need(is_array($data)&&($data['schema_version']??null)===2
        &&($data['source_policy']??null)==='official_review_missing_only_v2'
        &&($data['original_package_sha256']??null)===LO26_ORIGINAL_SHA
        &&($data['approval_ref']??null)===LO26_APPROVAL_REF
        &&($data['proposed_actions_sha256']??null)===LO26_ACTIONS_SHA
        &&is_array($data['proposed_actions']??null)
        &&hash_equals(LO26_ACTIONS_SHA,hash('sha256',lo11_json($data['proposed_actions']))),'MANIFEST_SCOPE');
    return ['manifestJson'=>$raw,'manifestSha256'=>LO26_MANIFEST_SHA,'actionsSha256'=>LO26_ACTIONS_SHA,
            'anytourHotelIds'=>LO26_IDS,'approvalRef'=>LO26_APPROVAL_REF];
}

function lo26_empty_receipt(array $identity): array {
    return $identity+['state'=>'held_before_write','requested_profiles'=>26,'requested_fields'=>76,
        'plan_sha256'=>null,'profiles_updated'=>0,'fields_updated'=>0,
        'field_counts'=>['description'=>0,'primaryImage'=>0,'images'=>0],'preserved_fields'=>0,
        'profile_writes'=>0,'provenance_writes'=>0,'database_writes'=>0,
        'supplier_calls'=>0,'provider_http_calls'=>0,'mapping_writes'=>0,'legacy_writes'=>0,'schema_writes'=>0,
        'readback_verified'=>false,'consumer_readback_verified'=>false,'consumer_sha256'=>null,
        'owner_receipt_sha256'=>null,'protected_before'=>null,'protected_after'=>null,'error_code'=>null];
}
function lo26_owner_receipt(array $data, string $planSha): void {
    $keys=['status','operation','planSha256','manifestSha256','actionsSha256','originalPackageSha256',
        'profilesSelected','profilesUpdated','fieldsFilled','fieldCounts','profileWrites','provenanceWrites',
        'legacyWrites','mappingWrites','supplierCalls','readbackVerified','consumerReadbackVerified',
        'consumerReadbackSha256','readback'];
    lo11_need(lo11_exact($data,$keys)&&$data['status']==='committed_verified'&&$data['operation']===LO26_OPERATION
        &&$data['planSha256']===$planSha&&$data['manifestSha256']===LO26_MANIFEST_SHA
        &&$data['actionsSha256']===LO26_ACTIONS_SHA&&$data['originalPackageSha256']===LO26_ORIGINAL_SHA
        &&$data['profilesSelected']===26&&$data['readbackVerified']===true
        &&$data['consumerReadbackVerified']===true&&lo11_digest($data['consumerReadbackSha256']),'OWNER_RECEIPT');
    foreach(['profilesUpdated'=>26,'fieldsFilled'=>76,'profileWrites'=>26,'provenanceWrites'=>26] as $key=>$maximum)
        lo11_need(is_int($data[$key])&&$data[$key]>=0&&$data[$key]<=$maximum,'OWNER_COUNTS');
    lo11_need($data['profileWrites']===$data['profilesUpdated']&&$data['provenanceWrites']===$data['profilesUpdated']
        &&$data['legacyWrites']===0&&$data['mappingWrites']===0&&$data['supplierCalls']===0,'OWNER_WRITES');
    lo11_need(is_array($data['fieldCounts'])&&lo11_exact($data['fieldCounts'],LO26_FIELDS),'OWNER_FIELDS');
    foreach($data['fieldCounts'] as $field=>$count)lo11_need(is_int($count)&&$count>=0&&$count<=LO26_FIELD_CAPS[$field],'OWNER_FIELD_COUNT');
    lo11_need(array_sum($data['fieldCounts'])===$data['fieldsFilled'],'OWNER_FIELD_TOTAL');
    lo11_need(is_array($data['readback'])&&array_is_list($data['readback'])&&count($data['readback'])===26,'OWNER_READBACK');
    $ids=[];$changedProfiles=0;$counts=array_fill_keys(LO26_FIELDS,0);
    foreach($data['readback'] as $row){
        lo11_need(is_array($row)&&lo11_exact($row,['anytourHotelId','revision','profileSha256','changedFields',
            'descriptionPresent','primaryImagePresent','imagesCount','detailsAvailable']),'OWNER_READBACK_ROW');
        lo11_need(is_int($row['anytourHotelId'])&&in_array($row['anytourHotelId'],LO26_IDS,true)
            &&lo11_digest($row['profileSha256'])&&is_array($row['changedFields'])&&array_is_list($row['changedFields'])
            &&count(array_unique($row['changedFields']))===count($row['changedFields'])
            &&array_diff($row['changedFields'],lo26_allowed_fields($row['anytourHotelId']))===[],'OWNER_READBACK_SCOPE');
        foreach($row['changedFields'] as $field)++$counts[$field];
        if($row['changedFields']!==[])++$changedProfiles;
        lo11_need($row['revision']===($row['changedFields']===[]?1:2)
            &&is_bool($row['descriptionPresent'])&&is_bool($row['primaryImagePresent'])
            &&is_bool($row['detailsAvailable'])&&is_int($row['imagesCount'])&&$row['imagesCount']>=0&&$row['imagesCount']<=100,
            'OWNER_READBACK_TYPES');
        if($row['anytourHotelId']===4172)lo11_need($row['primaryImagePresent']===true&&$row['imagesCount']===44,'HILTON_GALLERY_PRESERVED');
        $ids[]=$row['anytourHotelId'];
    }
    $expected=LO26_IDS;sort($expected,SORT_NUMERIC);
    lo11_need($ids===$expected&&$changedProfiles===$data['profilesUpdated']
        &&lo11_json($counts)===lo11_json($data['fieldCounts']),'OWNER_READBACK_TOTAL');
}

/** The runtime always supplies the existing canonical owner. Callbacks retain private evidence. */
function lo26_execute_owner(object $owner, array $approved, array $identity, array $protected,
    callable $savePlan, callable $markStarted, callable $readProtected): array {
    $receipt=lo26_empty_receipt($identity);$receipt['protected_before']=$protected;$started=false;
    try {
        $plan=$owner->planOfficial26($approved);
        lo11_need(is_array($plan)&&($plan['status']??null)==='prepared_read_only'&&($plan['writes']??null)===0
            &&($plan['supplierCalls']??null)===0&&lo11_digest($plan['planSha256']??null)
            &&is_array($plan['selected']??null)&&count($plan['selected'])===26,'PLAN');
        $ids=array_column($plan['selected'],'anytourHotelId');sort($ids,SORT_NUMERIC);
        $expected=LO26_IDS;sort($expected,SORT_NUMERIC);lo11_need($ids===$expected,'PLAN_ROSTER');
        $receipt['plan_sha256']=$plan['planSha256'];$savePlan($plan);
        lo11_need($readProtected()===$protected,'PROTECTED_DRIFT');
        $markStarted($plan['planSha256']);$started=true;
        $applied=$owner->applyOfficial26(LO26_OPERATION,$approved,$plan['planSha256']);
        lo11_need(is_array($applied),'OWNER_RESULT');lo26_owner_receipt($applied,$plan['planSha256']);
        $after=$readProtected();lo11_need($after===$protected,'PROTECTED_DRIFT');
        $receipt['profiles_updated']=$applied['profilesUpdated'];$receipt['fields_updated']=$applied['fieldsFilled'];
        $receipt['field_counts']=$applied['fieldCounts'];$receipt['preserved_fields']=76-$applied['fieldsFilled'];
        $receipt['profile_writes']=$applied['profileWrites'];$receipt['provenance_writes']=$applied['provenanceWrites'];
        $receipt['database_writes']=$applied['profileWrites']+$applied['provenanceWrites'];
        $receipt['readback_verified']=true;$receipt['consumer_readback_verified']=true;
        $receipt['consumer_sha256']=$applied['consumerReadbackSha256'];
        $receipt['owner_receipt_sha256']=hash('sha256',lo11_json($applied));
        $receipt['protected_after']=$after;$receipt['state']='committed_verified';
    } catch(Throwable $error) {
        $message=$error->getMessage();
        $receipt['error_code']=preg_match('/\A(?:ANYTOUR_PROFILE_OFFICIAL_|LOCAL_OFFICIAL26_|LOCAL_OFFICIAL11_)[A-Z0-9_]{1,70}\z/D',$message)===1
            ? $message : 'LOCAL_OFFICIAL26_FAILURE';
        if($started){
            $receipt['state']='unknown_no_replay';
            foreach(['profiles_updated','fields_updated','preserved_fields','profile_writes','provenance_writes','database_writes'] as $key)
                $receipt[$key]='unknown';
            $receipt['field_counts']=null;
            $receipt['readback_verified']=false;$receipt['consumer_readback_verified']=false;
            $receipt['consumer_sha256']=null;$receipt['owner_receipt_sha256']=null;
        }
    }
    return $receipt;
}

function lo26_main(array $arguments): int {
    lo11_need(PHP_SAPI==='cli'&&count($arguments)===2&&$arguments[1]==='--apply-approved26','CLI_SCOPE');
    $runtimeHome=(string)getenv('HOME');$root=(string)getenv('ANYTOUR_ROOT');
    $directory=(string)getenv('LOCAL_OFFICIAL26_DIR');$stage=(string)getenv('LOCAL_OFFICIAL26_SOURCE_ROOT');
    $source=(string)getenv('LOCAL_OFFICIAL26_SOURCE_SHA');$control=(string)getenv('LOCAL_OFFICIAL26_CONTROL_SHA');
    lo11_need($root===$runtimeHome.'/www/anytoour.ru'&&realpath($root)===$root
        &&$directory===$runtimeHome.'/.anytoour-int-executor/'.LO26_OPERATION&&realpath($directory)===$directory
        &&$stage===$directory.'/source'&&realpath($stage)===$stage
        &&preg_match('/\A[a-f0-9]{40}\z/D',$source)===1&&$source!==str_repeat('0',40)
        &&preg_match('/\A[a-f0-9]{40}\z/D',$control)===1,'RUNTIME_SCOPE');
    $reservation=json_decode(lo11_file($directory.'/reservation.json',65536),true,32,JSON_THROW_ON_ERROR);
    lo11_need(is_array($reservation)&&($reservation['operation_id']??null)===LO26_OPERATION
        &&($reservation['mode']??null)===LO26_MODE&&($reservation['source_sha']??null)===$source,'RESERVATION');
    $identity=['schema_version'=>1,'operation_id'=>LO26_OPERATION,'source_sha'=>$source,'control_sha'=>$control,
        'manifest_sha256'=>LO26_MANIFEST_SHA,'actions_sha256'=>LO26_ACTIONS_SHA,'original_package_sha256'=>LO26_ORIGINAL_SHA,
        'approval_ref'=>LO26_APPROVAL_REF];
    $receipt=lo26_empty_receipt($identity);
    try {
        $protected=lo11_protected($root);
        $approved=lo26_envelope($stage.'/scripts/catalog/anytour-local-official26-approved-20261122.json');
        $_SERVER['DOCUMENT_ROOT']=$root;require_once $root.'/data/db-v1.php';
        require_once $stage.'/v2/data/anytour-profile-enrichment-v1.php';
        $database=v2_data_db();lo11_need($database->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql','MYSQL_REQUIRED');
        $owner=new AnyTourProfileEnrichmentV1($database);
        $receipt=lo26_execute_owner($owner,$approved,$identity,$protected,
            static fn(array $plan)=>lo11_save($directory.'/official26-private-plan.json',$identity+['plan'=>$plan]),
            static fn(string $sha)=>lo11_save($directory.'/official26-apply-started.json',$identity+['plan_sha256'=>$sha]),
            static fn():array=>lo11_protected($root));
    } catch(Throwable $error) {
        $message=$error->getMessage();
        $receipt['error_code']=preg_match('/\A(?:ANYTOUR_PROFILE_OFFICIAL_|LOCAL_OFFICIAL26_|LOCAL_OFFICIAL11_)[A-Z0-9_]{1,70}\z/D',$message)===1
            ? $message : 'LOCAL_OFFICIAL26_PREFLIGHT_FAILURE';
    }
    lo11_save($directory.'/local-official26-receipt.json',$receipt);
    echo lo11_json($receipt)."\n";
    return $receipt['state']==='committed_verified'?0:2;
}
if(PHP_SAPI==='cli'&&realpath((string)($argv[0]??''))===__FILE__){
    try {exit(lo26_main($argv));}
    catch(Throwable){fwrite(STDERR,"local_official26_failed_no_replay\n");exit(2);}
}
