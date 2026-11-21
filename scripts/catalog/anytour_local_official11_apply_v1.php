<?php
/** CLI-only approved official LOCAL11 transaction in the existing private carrier. */
declare(strict_types=1);

const LO11_OPERATION = 'int-andromeda-local-official11-apply-20261121-v1';
const LO11_MODE = 'local-official11-apply-v1';
const LO11_APPROVAL_REF = 'https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-7063885208';
const LO11_MANIFEST_SHA = '93e0ea33e64ce2c41d68ffb54e9efaf6e383f3cdde68f01c6aa4ab51f4e5a242';
const LO11_ACTIONS_SHA = '45e11557da29ecc652589e5cc89a6dcfa457b57e1a8936fe87d57d2ceb12edb4';
const LO11_ORIGINAL_SHA = '01090549e278cbd739efa4936472c256724f6c555bd0d44864d6d21d2ce15123';
const LO11_DB_HELPER_SHA = 'c1c841d38c62845bc36e559183da17e0411c1c5bdd909517ffc02b5fbb1e2f13';
const LO11_IDS = [5371,6014,6202,5160,5794,4516,7218,12008,5598,10771,8689];
const LO11_FIELDS = ['description','primaryImage','images'];

function lo11_need(bool $value, string $reason): void {
    if (!$value) throw new RuntimeException('LOCAL_OFFICIAL11_'.$reason);
}
function lo11_stable(mixed $value): mixed {
    if (!is_array($value)) return $value;
    if (!array_is_list($value)) ksort($value, SORT_STRING);
    foreach ($value as $key=>$item) $value[$key]=lo11_stable($item);
    return $value;
}
function lo11_json(array $value): string {
    return json_encode(lo11_stable($value),JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
}
function lo11_digest(mixed $value): bool {
    return is_string($value) && preg_match('/\A[a-f0-9]{64}\z/D',$value)===1;
}
function lo11_exact(array $value, array $keys): bool {
    return count($value)===count($keys) && array_diff(array_keys($value),$keys)===[] && array_diff($keys,array_keys($value))===[];
}
function lo11_file(string $path, int $maximum): string {
    lo11_need(is_file($path)&&!is_link($path)&&realpath($path)===$path,'FILE_PATH');
    $size=filesize($path);lo11_need(is_int($size)&&$size>0&&$size<=$maximum,'FILE_SIZE');
    $raw=file_get_contents($path);lo11_need(is_string($raw)&&strlen($raw)===$size,'FILE_READ');
    return $raw;
}
function lo11_save(string $path, array $value): void {
    lo11_need(!file_exists($path)&&!is_link($path)&&realpath(dirname($path))===dirname($path),'PRIVATE_FILE_EXISTS');
    $stream=fopen($path,'xb');lo11_need(is_resource($stream),'PRIVATE_OPEN');
    try {
        lo11_need(chmod($path,0600),'PRIVATE_MODE');$raw=lo11_json($value)."\n";$offset=0;
        while($offset<strlen($raw)){$written=fwrite($stream,substr($raw,$offset));lo11_need(is_int($written)&&$written>0,'PRIVATE_WRITE');$offset+=$written;}
        lo11_need(fflush($stream)&&function_exists('fsync')&&fsync($stream),'PRIVATE_DURABILITY');
    } finally { fclose($stream); }
}
function lo11_protected(string $root): array {
    $helper=lo11_file($root.'/data/db-v1.php',2097152);
    lo11_need(hash_equals(LO11_DB_HELPER_SHA,hash('sha256',$helper)),'DB_HELPER_DRIFT');
    return ['config.php'=>hash('sha256',lo11_file($root.'/config.php',2097152)),
            'data/db-v1.php'=>LO11_DB_HELPER_SHA];
}
function lo11_envelope(string $manifest): array {
    $raw=lo11_file($manifest,262144);
    lo11_need(hash_equals(LO11_MANIFEST_SHA,hash('sha256',$raw)),'MANIFEST_DIGEST');
    $data=json_decode($raw,true,128,JSON_THROW_ON_ERROR);
    lo11_need(is_array($data)&&($data['original_package_sha256']??null)===LO11_ORIGINAL_SHA
        &&($data['approval_ref']??null)===LO11_APPROVAL_REF
        &&($data['proposed_actions_sha256']??null)===LO11_ACTIONS_SHA
        &&is_array($data['proposed_actions']??null)
        &&hash_equals(LO11_ACTIONS_SHA,hash('sha256',lo11_json($data['proposed_actions']))),'MANIFEST_SCOPE');
    return ['manifestJson'=>$raw,'manifestSha256'=>LO11_MANIFEST_SHA,'actionsSha256'=>LO11_ACTIONS_SHA,
            'anytourHotelIds'=>LO11_IDS,'approvalRef'=>LO11_APPROVAL_REF];
}
function lo11_empty_receipt(array $identity): array {
    return $identity+['state'=>'held_before_write','requested_profiles'=>11,'requested_fields'=>33,
        'plan_sha256'=>null,'profiles_updated'=>0,'fields_updated'=>0,
        'field_counts'=>['description'=>0,'primaryImage'=>0,'images'=>0],'preserved_fields'=>0,
        'profile_writes'=>0,'provenance_writes'=>0,'database_writes'=>0,
        'supplier_calls'=>0,'provider_http_calls'=>0,'mapping_writes'=>0,'legacy_writes'=>0,'schema_writes'=>0,
        'readback_verified'=>false,'consumer_readback_verified'=>false,'consumer_sha256'=>null,
        'owner_receipt_sha256'=>null,'protected_before'=>null,'protected_after'=>null,'error_code'=>null];
}
function lo11_owner_receipt(array $data, string $planSha): void {
    $keys=['status','operation','planSha256','manifestSha256','actionsSha256','originalPackageSha256',
        'profilesSelected','profilesUpdated','fieldsFilled','fieldCounts','profileWrites','provenanceWrites',
        'legacyWrites','mappingWrites','supplierCalls','readbackVerified','consumerReadbackVerified',
        'consumerReadbackSha256','readback'];
    lo11_need(lo11_exact($data,$keys)&&$data['status']==='committed_verified'&&$data['operation']===LO11_OPERATION
        &&$data['planSha256']===$planSha&&$data['manifestSha256']===LO11_MANIFEST_SHA
        &&$data['actionsSha256']===LO11_ACTIONS_SHA&&$data['originalPackageSha256']===LO11_ORIGINAL_SHA
        &&$data['profilesSelected']===11&&$data['readbackVerified']===true
        &&$data['consumerReadbackVerified']===true&&lo11_digest($data['consumerReadbackSha256']),'OWNER_RECEIPT');
    foreach(['profilesUpdated'=>11,'fieldsFilled'=>33,'profileWrites'=>11,'provenanceWrites'=>11] as $key=>$maximum)
        lo11_need(is_int($data[$key])&&$data[$key]>=0&&$data[$key]<=$maximum,'OWNER_COUNTS');
    lo11_need($data['profileWrites']===$data['profilesUpdated']&&$data['provenanceWrites']===$data['profilesUpdated']
        &&$data['legacyWrites']===0&&$data['mappingWrites']===0&&$data['supplierCalls']===0,'OWNER_WRITES');
    lo11_need(is_array($data['fieldCounts'])&&lo11_exact($data['fieldCounts'],LO11_FIELDS),'OWNER_FIELDS');
    foreach($data['fieldCounts'] as $count)lo11_need(is_int($count)&&$count>=0&&$count<=11,'OWNER_FIELD_COUNT');
    lo11_need(array_sum($data['fieldCounts'])===$data['fieldsFilled'],'OWNER_FIELD_TOTAL');
    lo11_need(is_array($data['readback'])&&array_is_list($data['readback'])&&count($data['readback'])===11,'OWNER_READBACK');
    $ids=[];$changedProfiles=0;$counts=array_fill_keys(LO11_FIELDS,0);
    foreach($data['readback'] as $row){
        lo11_need(is_array($row)&&lo11_exact($row,['anytourHotelId','revision','profileSha256','changedFields',
            'descriptionPresent','primaryImagePresent','imagesCount','detailsAvailable']),'OWNER_READBACK_ROW');
        lo11_need(is_int($row['anytourHotelId'])&&in_array($row['anytourHotelId'],LO11_IDS,true)
            &&lo11_digest($row['profileSha256'])&&is_array($row['changedFields'])&&array_is_list($row['changedFields'])
            &&count(array_unique($row['changedFields']))===count($row['changedFields'])
            &&array_diff($row['changedFields'],LO11_FIELDS)===[],'OWNER_READBACK_SCOPE');
        foreach($row['changedFields'] as $field)++$counts[$field];
        if($row['changedFields']!==[])++$changedProfiles;
        lo11_need($row['revision']===($row['changedFields']===[]?1:2)
            &&is_bool($row['descriptionPresent'])&&is_bool($row['primaryImagePresent'])
            &&is_bool($row['detailsAvailable'])&&is_int($row['imagesCount'])&&$row['imagesCount']>=0&&$row['imagesCount']<=100,
            'OWNER_READBACK_TYPES');
        $ids[]=$row['anytourHotelId'];
    }
    $expected=LO11_IDS;sort($expected,SORT_NUMERIC);
    lo11_need($ids===$expected&&$changedProfiles===$data['profilesUpdated']
        &&lo11_json($counts)===lo11_json($data['fieldCounts']),'OWNER_READBACK_TOTAL');
}

/** The runtime always supplies the existing canonical owner. Callbacks retain private evidence. */
function lo11_execute_owner(object $owner, array $approved, array $identity, array $protected,
    callable $savePlan, callable $markStarted, callable $readProtected): array {
    $receipt=lo11_empty_receipt($identity);$receipt['protected_before']=$protected;$started=false;
    try {
        $plan=$owner->planOfficial11($approved);
        lo11_need(is_array($plan)&&($plan['status']??null)==='prepared_read_only'&&($plan['writes']??null)===0
            &&($plan['supplierCalls']??null)===0&&lo11_digest($plan['planSha256']??null)
            &&is_array($plan['selected']??null)&&count($plan['selected'])===11,'PLAN');
        $ids=array_column($plan['selected'],'anytourHotelId');sort($ids,SORT_NUMERIC);
        $expected=LO11_IDS;sort($expected,SORT_NUMERIC);lo11_need($ids===$expected,'PLAN_ROSTER');
        $receipt['plan_sha256']=$plan['planSha256'];$savePlan($plan);
        lo11_need($readProtected()===$protected,'PROTECTED_DRIFT');
        $markStarted($plan['planSha256']);$started=true;
        $applied=$owner->applyOfficial11(LO11_OPERATION,$approved,$plan['planSha256']);
        lo11_need(is_array($applied),'OWNER_RESULT');lo11_owner_receipt($applied,$plan['planSha256']);
        $after=$readProtected();lo11_need($after===$protected,'PROTECTED_DRIFT');
        $receipt['profiles_updated']=$applied['profilesUpdated'];$receipt['fields_updated']=$applied['fieldsFilled'];
        $receipt['field_counts']=$applied['fieldCounts'];$receipt['preserved_fields']=33-$applied['fieldsFilled'];
        $receipt['profile_writes']=$applied['profileWrites'];$receipt['provenance_writes']=$applied['provenanceWrites'];
        $receipt['database_writes']=$applied['profileWrites']+$applied['provenanceWrites'];
        $receipt['readback_verified']=true;$receipt['consumer_readback_verified']=true;
        $receipt['consumer_sha256']=$applied['consumerReadbackSha256'];
        $receipt['owner_receipt_sha256']=hash('sha256',lo11_json($applied));
        $receipt['protected_after']=$after;$receipt['state']='committed_verified';
    } catch(Throwable $error) {
        $message=$error->getMessage();
        $receipt['error_code']=preg_match('/\A(?:ANYTOUR_PROFILE_OFFICIAL_|LOCAL_OFFICIAL11_)[A-Z0-9_]{1,70}\z/D',$message)===1
            ? $message : 'LOCAL_OFFICIAL11_FAILURE';
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

function lo11_main(array $arguments): int {
    lo11_need(PHP_SAPI==='cli'&&count($arguments)===2&&$arguments[1]==='--apply-approved11','CLI_SCOPE');
    $runtimeHome=(string)getenv('HOME');$root=(string)getenv('ANYTOUR_ROOT');
    $directory=(string)getenv('LOCAL_OFFICIAL11_DIR');$stage=(string)getenv('LOCAL_OFFICIAL11_SOURCE_ROOT');
    $source=(string)getenv('LOCAL_OFFICIAL11_SOURCE_SHA');$control=(string)getenv('LOCAL_OFFICIAL11_CONTROL_SHA');
    lo11_need($root===$runtimeHome.'/www/anytoour.ru'&&realpath($root)===$root
        &&$directory===$runtimeHome.'/.anytoour-int-executor/'.LO11_OPERATION&&realpath($directory)===$directory
        &&$stage===$directory.'/source'&&realpath($stage)===$stage
        &&preg_match('/\A[a-f0-9]{40}\z/D',$source)===1&&$source!==str_repeat('0',40)
        &&preg_match('/\A[a-f0-9]{40}\z/D',$control)===1,'RUNTIME_SCOPE');
    $reservation=json_decode(lo11_file($directory.'/reservation.json',65536),true,32,JSON_THROW_ON_ERROR);
    lo11_need(is_array($reservation)&&($reservation['operation_id']??null)===LO11_OPERATION
        &&($reservation['mode']??null)===LO11_MODE&&($reservation['source_sha']??null)===$source,'RESERVATION');
    $identity=['schema_version'=>1,'operation_id'=>LO11_OPERATION,'source_sha'=>$source,'control_sha'=>$control,
        'manifest_sha256'=>LO11_MANIFEST_SHA,'actions_sha256'=>LO11_ACTIONS_SHA,'original_package_sha256'=>LO11_ORIGINAL_SHA,
        'approval_ref'=>LO11_APPROVAL_REF];
    $receipt=lo11_empty_receipt($identity);
    try {
        $protected=lo11_protected($root);
        $approved=lo11_envelope($stage.'/scripts/catalog/anytour-local-official11-approved-20261121.json');
        $_SERVER['DOCUMENT_ROOT']=$root;require_once $root.'/data/db-v1.php';
        require_once $stage.'/v2/data/anytour-profile-enrichment-v1.php';
        $database=v2_data_db();lo11_need($database->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql','MYSQL_REQUIRED');
        $owner=new AnyTourProfileEnrichmentV1($database);
        $receipt=lo11_execute_owner($owner,$approved,$identity,$protected,
            static fn(array $plan)=>lo11_save($directory.'/official11-private-plan.json',$identity+['plan'=>$plan]),
            static fn(string $sha)=>lo11_save($directory.'/official11-apply-started.json',$identity+['plan_sha256'=>$sha]),
            static fn():array=>lo11_protected($root));
    } catch(Throwable $error) {
        $message=$error->getMessage();
        $receipt['error_code']=preg_match('/\A(?:ANYTOUR_PROFILE_OFFICIAL_|LOCAL_OFFICIAL11_)[A-Z0-9_]{1,70}\z/D',$message)===1
            ? $message : 'LOCAL_OFFICIAL11_PREFLIGHT_FAILURE';
    }
    lo11_save($directory.'/local-official11-receipt.json',$receipt);
    echo lo11_json($receipt)."\n";
    return $receipt['state']==='committed_verified'?0:2;
}
if(PHP_SAPI==='cli'&&realpath((string)($argv[0]??''))===__FILE__){
    try {exit(lo11_main($argv));}
    catch(Throwable){fwrite(STDERR,"local_official11_failed_no_replay\n");exit(2);}
}
