<?php
/** One HC-1 invocation of the existing scoped engine; no supplier or new SQL writer. */
declare(strict_types=1);
require_once dirname(__DIR__,2) . '/v2/data/anytour-profile-enrichment-v1.php';
const HC1_OP = 'search3-hc1-content-apply-20260925-c1';
const HC1_SOURCE = '772cef853390afde25041a7ca65d30f86f97b549';
const HC1_THROUGH = '2026-09-25T11:15:51Z';
function hc1_assert(bool $ok, string $code): void { if (!$ok) throw new RuntimeException('HC1_' . $code); }
function hc1_scope(): array {
    $pairs=[559=>4349,562=>4350,563=>4351,570=>4352,571=>4353,572=>4354,573=>4355,583=>4356,584=>4357,586=>4358,587=>4359,606=>4360,608=>4361,609=>4362,615=>4363,621=>4364,628=>4365,632=>4366];
    $noDescription=[563,573,583,584,608,615]; $out=[];
    foreach ($pairs as $id=>$own) {
        $fields=['images','primaryImage'];
        if (!in_array($id,$noDescription,true)) array_unshift($fields,'description');
        $out[]=['anytourHotelId'=>$own,'localHotelId'=>$id,'fields'=>$fields];
    }
    return $out;
}
function hc1_validate(array $plan): array {
    hc1_assert(($plan['status']??null)==='prepared_read_only' && ($plan['writes']??null)===0,'PLAN_STATUS');
    hc1_assert(($plan['contentScope']??null)===hc1_scope(),'PLAN_SCOPE');
    hc1_assert(count($plan['selected']??[])===18,'PLAN_COUNT');
    $wanted=[]; foreach (hc1_scope() as $t) $wanted[$t['anytourHotelId']]=$t;
    $counts=[]; $seen=[];
    foreach ($plan['selected'] as $item) {
        $id=$item['anytourHotelId']; hc1_assert(isset($wanted[$id])&&!isset($seen[$id]),'PLAN_ID'); $seen[$id]=true;
        hc1_assert($item['localHotelId']===$wanted[$id]['localHotelId'],'PLAN_PAIR');
        hc1_assert($item['expectedRevision']===1,'BASE_REVISION');
        $keys=array_keys($item['patch']); sort($keys,SORT_STRING);
        hc1_assert($keys===$wanted[$id]['fields'],'PATCH_MASK');
        hc1_assert(hash('sha256',$item['beforeProfileJson'])===$item['expectedProfileSha256'],'BEFORE_HASH');
        $before=json_decode($item['beforeProfileJson'],true,512,JSON_THROW_ON_ERROR);
        hc1_assert(!v2_hotel_detail_is_generic_product_name($before['name']??null),'GENERIC_PRODUCT');
        foreach ($item['patch'] as $field=>$value) {
            $prev=$before[$field]??null;
            hc1_assert($prev===null || $prev===[] || is_string($prev)&&trim($prev)==='','NOT_EMPTY');
            if ($field==='description') {
                hc1_assert(is_string($value)&&preg_match('/[^\s\p{Z}\x{200B}\x{FEFF}]/u',html_entity_decode(strip_tags($value),ENT_QUOTES|ENT_HTML5,'UTF-8'))===1,'EMPTY_DESCRIPTION');
            } elseif ($field==='primaryImage') {
                hc1_assert(is_string($value)&&v2_hotel_detail_https_url($value)===$value,'PRIMARY_URL');
            } else {
                hc1_assert(is_array($value)&&$value!==[]&&v2_hotel_detail_images(['images'=>$value])===$value,'GALLERY_URLS');
            }
            $counts[$field]=($counts[$field]??0)+1;
        }
    }
    ksort($counts); hc1_assert($counts===['description'=>12,'images'=>18,'primaryImage'=>18],'FIELD_COUNTS');
    return $counts;
}
function hc1_save(string $path,array $value): void {
    $fh=fopen($path,'x'); hc1_assert($fh!==false,'EXCLUSIVE_FILE');
    try {
        $json=AnyTourProfileEnrichmentV1::json($value)."\n";
        hc1_assert(fwrite($fh,$json)===strlen($json)&&fflush($fh),'DURABLE_FILE');
        if (function_exists('fsync')) hc1_assert(fsync($fh),'FSYNC');
    } finally { fclose($fh); }
}
function hc1_rows(PDO $db,array $ids): array {
    $s=$db->prepare('SELECT id,profile_json,profile_sha256,revision,is_active FROM anytour_hotels WHERE id IN ('.implode(',',array_fill(0,count($ids),'?')).') ORDER BY id');
    $s->execute($ids); $rows=$s->fetchAll(PDO::FETCH_ASSOC); hc1_assert(count($rows)===count($ids),'READ_COUNT');
    $out=[]; foreach ($rows as $r) {
        hc1_assert((int)$r['is_active']===1&&hash('sha256',$r['profile_json'])===$r['profile_sha256'],'READ_HASH');
        $out[(int)$r['id']]=$r;
    }
    return $out;
}
if (($argv[1]??'')==='--self-test') {
    $fixture=['status'=>'prepared_read_only','writes'=>0,'contentScope'=>hc1_scope(),'selected'=>[]];
    foreach (hc1_scope() as $t) {
        $before=AnyTourProfileEnrichmentV1::json(['name'=>'Fictional test hotel','description'=>null,'primaryImage'=>null,'images'=>[]]);
        $patch=[];foreach ($t['fields'] as $f) $patch[$f]=$f==='description'?'Fictional test description':($f==='images'?['https://example.invalid/a.jpg']:'https://example.invalid/a.jpg');
        $fixture['selected'][]=['anytourHotelId'=>$t['anytourHotelId'],'localHotelId'=>$t['localHotelId'],'expectedRevision'=>1,'beforeProfileJson'=>$before,'expectedProfileSha256'=>hash('sha256',$before),'patch'=>$patch];
    }
    hc1_validate($fixture); $checks=1;
    foreach (['count','scope','pair','revision','mask','before','blank','generic'] as $case) {
        $bad=$fixture;
        switch($case) {
            case 'count': array_pop($bad['selected']);break;
            case 'scope': $bad['contentScope'][0]['fields'][]='rating';break;
            case 'pair': $bad['selected'][0]['localHotelId']=999;break;
            case 'revision': $bad['selected'][0]['expectedRevision']=2;break;
            case 'mask': $bad['selected'][0]['patch']['rating']=5;break;
            case 'before': $bad['selected'][0]['expectedProfileSha256']=str_repeat('0',64);break;
            case 'blank': $bad['selected'][0]['patch']['description']='<p> &nbsp; </p>';break;
            case 'generic': $j=AnyTourProfileEnrichmentV1::json(['name'=>'Fortuna 5','description'=>null,'primaryImage'=>null,'images'=>[]]);$bad['selected'][0]['beforeProfileJson']=$j;$bad['selected'][0]['expectedProfileSha256']=hash('sha256',$j);break;
        }
        try { hc1_validate($bad); throw new LogicException('fixture_should_reject_'.$case); }
        catch (RuntimeException $e) { hc1_assert(str_starts_with($e->getMessage(),'HC1_'),'SELF_TEST'); ++$checks; }
    }
    echo 'HC1_OPERATION_PURE_OK checks='.$checks."\n";exit(0);
}
hc1_assert(PHP_SAPI==='cli'&&count($argv)===2&&$argv[1]==='--execute','MODE');
umask(0077); $dir=realpath((string)getenv('HC1_OPERATION_DIR')); $root=realpath((string)getenv('ANYTOUR_ROOT'));
hc1_assert(is_string($dir)&&basename($dir)===HC1_OP&&realpath(dirname(__DIR__,3))===$dir,'OP_DIR');
hc1_assert(is_string($root)&&is_file($root.'/config.php')&&is_dir($root.'/_preview/search3-local-candidate/data'),'ROOT');
hc1_assert(!str_starts_with($dir.'/', $root.'/'),'PRIVATE_DIR');
$attempted=false; $result=['operation'=>HC1_OP,'sourceSha'=>HC1_SOURCE,'supplierCalls'=>0,'mappingWrites'=>0,'legacyWrites'=>0,'runtimePublished'=>false,'noReplay'=>true,'state'=>'preparing'];
try {
    hc1_save($dir.'/execution-started.json',['operation'=>HC1_OP,'startedAt'=>gmdate('c')]);
    $lock=fopen(dirname($dir).'/canonical-hc1.lock','c'); hc1_assert($lock!==false&&flock($lock,LOCK_EX|LOCK_NB),'LOCK');
    $_SERVER['DOCUMENT_ROOT']=$root;
    require_once dirname(__DIR__,2) . '/v2/data/db-v1.php';
    $db=v2_data_db(); $engine=new AnyTourProfileEnrichmentV1($db); $scope=hc1_scope();
    $controls=hc1_rows($db,[1,1379]);
    $p1=$engine->plan(18,HC1_THROUGH,$scope);hc1_validate($p1);
    $p2=$engine->plan(18,HC1_THROUGH,$scope);hc1_validate($p2);
    hc1_assert(AnyTourProfileEnrichmentV1::json($p1)===AnyTourProfileEnrichmentV1::json($p2),'PLAN_DRIFT');
    hc1_save($dir.'/private-plan.json',$p1);
    hc1_save($dir.'/private-controls-before.json',$controls);
    hc1_save($dir.'/rollback-contract.json',['operation'=>HC1_OP,'planSha256'=>$p1['planSha256'],'policy'=>'Restore only changed fields from private-plan beforeProfileJson after matching applied profile hash/revision; preserve later edits; never restore the whole database. Rollback requires separate explicit execution.']);
    hc1_save($dir.'/commit-attempt.json',['operation'=>HC1_OP,'planSha256'=>$p1['planSha256'],'noReplay'=>true]);
    $attempted=true;
    $applied=$engine->apply(HC1_OP,18,HC1_THROUGH,$p1['planSha256'],$scope);
    hc1_assert($applied['status']==='committed_verified'&&$applied['profilesUpdated']===18&&$applied['fieldsFilled']===48,'APPLY_RESULT');
    hc1_assert($applied['fieldCounts']===['description'=>12,'images'=>18,'primaryImage'=>18],'APPLY_MASK');
    $rows=hc1_rows($db,array_column($scope,'anytourHotelId'));$safe=[];
    foreach ($p1['selected'] as $item) {
        $id=$item['anytourHotelId'];$expected=json_decode($item['beforeProfileJson'],true,512,JSON_THROW_ON_ERROR);
        foreach ($item['patch'] as $f=>$v) $expected[$f]=$v;
        hc1_assert($rows[$id]['profile_json']===AnyTourProfileEnrichmentV1::json($expected)&&(int)$rows[$id]['revision']===2,'POSTCOMMIT_DIFF');
        $safe[]=['anytourHotelId'=>$id,'localHotelId'=>$item['localHotelId'],'revision'=>2,'fields'=>array_keys($item['patch']),'profileSha256'=>$rows[$id]['profile_sha256']];
    }
    hc1_assert(hc1_rows($db,[1,1379])===$controls,'CONTROLS_CHANGED');
    $result['state']='committed_verified';$result['apply']=$applied;$result['rows']=$safe;
    $result['controlsUnchanged']=true;$result['beforeImagesPrivate']=true;$result['observedAt']=gmdate('c');
    hc1_save($dir.'/result.json',$result);
    hc1_save($dir.'/receipt.json',['operation'=>HC1_OP,'state'=>'committed_verified','resultSha256'=>hash_file('sha256',$dir.'/result.json'),'noReplay'=>true]);
    echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";
} catch (Throwable $e) {
    $result['state']=$attempted?'commit_attempted_inspect_no_replay':'failed_before_apply';
    $result['commitAttempted']=$attempted;
    $message=$e->getMessage();$result['error']=preg_match('/\A(?:HC1_|ANYTOUR_PROFILE_ENRICH_)[A-Z_]+\z/D',$message)?$message:'operation_failed';
    if (!is_file($dir.'/result.json')) hc1_save($dir.'/result.json',$result);
    if (!is_file($dir.'/receipt.json')) hc1_save($dir.'/receipt.json',['operation'=>HC1_OP,'state'=>$result['state'],'resultSha256'=>hash_file('sha256',$dir.'/result.json'),'noReplay'=>true]);
    echo json_encode($result)."\n";exit(2);
}
