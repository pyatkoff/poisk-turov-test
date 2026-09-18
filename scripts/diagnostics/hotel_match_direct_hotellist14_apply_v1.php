<?php
declare(strict_types=1);

const HD14_OP = 'hotel-match-direct-hotellist14-apply-1971-20260919-v1';
const HD14_MATCH_CLASS = 'strong_candidate';
const HD14_SCOPE = 'preview';
const HD14_POLICY = 'owner_exact_and_strong_20260908';
const HD14_MAX = 14;

function hd14_json(mixed $v): string {
    return json_encode($v, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
}
function hd14_put_new(string $path, array $value): string {
    $raw = hd14_json($value)."\n";
    $f = @fopen($path, 'x+b');
    if (!$f) throw new RuntimeException('durable_exists');
    try {
        if (fwrite($f, $raw) !== strlen($raw) || !fflush($f)) throw new RuntimeException('durable_write');
        if (function_exists('fsync') && !fsync($f)) throw new RuntimeException('durable_sync');
        rewind($f);
        if (stream_get_contents($f) !== $raw) throw new RuntimeException('durable_readback');
    } finally { fclose($f); }
    return hash('sha256', $raw);
}
function hd14_rows(PDO $db, string $sql, array $params=[]): array {
    $s = $db->prepare($sql); $s->execute(array_values($params));
    return $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
function hd14_id(mixed $v): ?int {
    $x = filter_var($v, FILTER_VALIDATE_INT);
    return $x !== false && (int)$x > 0 ? (int)$x : null;
}
function hd14_plan(string $path, string $expectedSha): array {
    if (!is_file($path) || !preg_match('/^[0-9a-f]{64}$/D', $expectedSha)) throw new RuntimeException('plan_missing');
    if (hash_file('sha256', $path) !== $expectedSha) throw new RuntimeException('plan_digest');
    $p = json_decode((string)file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($p) || ($p['operation_id']??'') !== HD14_OP || ($p['version']??null) !== 1 || !is_array($p['pairs']??null)) throw new RuntimeException('plan_shape');
    if (count($p['pairs']) < 1 || count($p['pairs']) > HD14_MAX) throw new RuntimeException('plan_count');
    $a=[]; $l=[];
    foreach ($p['pairs'] as $r) {
        if (!is_array($r)) throw new RuntimeException('plan_row');
        $aid=hd14_id($r['anex_hotel_id']??null); $lid=hd14_id($r['catalog_hotel_id']??null); $country=hd14_id($r['expected_country_id']??null);
        if (!$aid || !$lid || !$country || !preg_match('/^[0-9a-f]{64}$/D',(string)($r['evidence_sha256']??''))) throw new RuntimeException('plan_row_value');
        if (isset($a[$aid]) || isset($l[$lid])) throw new RuntimeException('plan_duplicate');
        $a[$aid]=true; $l[$lid]=true;
    }
    return $p;
}
function hd14_table_exists(PDO $db, string $name): bool {
    $s=$db->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    $s->execute([$name]); return $s->fetchColumn() !== false;
}

if (in_array('--self-test', $argv??[], true)) {
    $tmp=tempnam(sys_get_temp_dir(),'hd14');
    $v=['operation_id'=>HD14_OP,'version'=>1,'pairs'=>[['anex_hotel_id'=>1,'catalog_hotel_id'=>2,'expected_country_id'=>9,'evidence_sha256'=>str_repeat('a',64)]]];
    file_put_contents($tmp, hd14_json($v)."\n"); $h=hash_file('sha256',$tmp);
    if (count(hd14_plan($tmp,$h)['pairs'])!==1) throw new RuntimeException('self_plan');
    @unlink($tmp);
    echo "HD14_SELFTEST_OK\n"; exit(0);
}

if (PHP_SAPI!=='cli' || ($argv[1]??'')!=='--execute') throw new RuntimeException('disabled');
$op=(string)getenv('OPERATION_ID'); $sourceSha=(string)getenv('MATCH_SOURCE_SHA'); $planPath=(string)getenv('MATCH_PLAN_PATH'); $planSha=(string)getenv('MATCH_PLAN_SHA256');
if ($op!==HD14_OP || !preg_match('/^[0-9a-f]{40}$/D',$sourceSha)) throw new RuntimeException('operation_guard');
$root=(string)realpath((string)getenv('ANYTOUR_ROOT')); if ($root==='' || basename($root)!=='anytoour.ru') throw new RuntimeException('root_guard');
$plan=hd14_plan($planPath,$planSha);
$base=rtrim((string)getenv('HOME'),'/').'/.anytoour-match/operations'; if(!is_dir($base)) throw new RuntimeException('operations_root');
$dir=$base.'/'.$op; if(!@mkdir($dir,0700)) throw new RuntimeException('operation_exists');
hd14_put_new($dir.'/reservation.json',['operation_id'=>$op,'source_sha'=>$sourceSha,'state'=>'reserved_before_db_write','plan_sha256'=>$planSha,'pair_count'=>count($plan['pairs']),'no_replay'=>true]);

$db=null; $committed=false; $written=[]; $held=[]; $post=[]; $newTriple=0; $newNonTriple=0;
try {
    $dbf=is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php'; require_once $dbf;
    $db=v2_data_db(); $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $db->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE'); $db->beginTransaction();
    $pairs=$plan['pairs']; $aids=array_map(fn($r)=>(int)$r['anex_hotel_id'],$pairs); $lids=array_map(fn($r)=>(int)$r['catalog_hotel_id'],$pairs);
    $aph=implode(',',array_fill(0,count($aids),'?')); $lph=implode(',',array_fill(0,count($lids),'?'));
    $maps=hd14_rows($db,"SELECT * FROM anex_hotel_search_mappings WHERE anex_hotel_id IN ($aph) OR catalog_hotel_id IN ($lph) FOR UPDATE",array_merge($aids,$lids));
    $dec=hd14_table_exists($db,'anex_hotel_decisions')?hd14_rows($db,"SELECT * FROM anex_hotel_decisions WHERE anex_hotel_id IN ($aph) FOR UPDATE",$aids):[];
    $exc=hd14_table_exists($db,'anex_review_pair_exclusions')?hd14_rows($db,"SELECT * FROM anex_review_pair_exclusions WHERE anex_hotel_id IN ($aph) OR catalog_hotel_id IN ($lph) FOR UPDATE",array_merge($aids,$lids)):[];
    $hot=hd14_rows($db,"SELECT id,country_id,name,is_active,latitude,longitude FROM catalog_hotels WHERE id IN ($lph) FOR UPDATE",$lids);
    $and=[];
    if (hd14_table_exists($db,'andromeda_hotel_identities')) {
        foreach(hd14_rows($db,"SELECT local_hotel_id FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IN ($lph)",$lids) as $r) $and[(int)$r['local_hotel_id']]=true;
    }
    $byA=[];$byL=[];$byH=[];$byD=[];$byE=[];
    foreach($maps as $r){$byA[(int)$r['anex_hotel_id']][]=$r;$byL[(int)$r['catalog_hotel_id']][]=$r;}
    foreach($hot as $r)$byH[(int)$r['id']]=$r;
    foreach($dec as $r)$byD[(int)$r['anex_hotel_id']][]=$r;
    foreach($exc as $r)$byE[(int)$r['anex_hotel_id']][]=$r;
    $mappingDigest=hash('sha256',hd14_json(['operation_id'=>$op,'plan_sha256'=>$planSha,'match_class'=>HD14_MATCH_CLASS,'scope'=>HD14_SCOPE,'approval_policy'=>HD14_POLICY]));
    $insert=$db->prepare('INSERT INTO anex_hotel_search_mappings (anex_hotel_id,catalog_hotel_id,match_class,scope,approval_policy,source_row_digest,mapping_digest,enabled) VALUES (?,?,?,?,?,?,?,1)');
    foreach($pairs as $r){
        $aid=(int)$r['anex_hotel_id'];$lid=(int)$r['catalog_hotel_id'];$country=(int)$r['expected_country_id'];$reason=[];$same=false;
        foreach($byA[$aid]??[] as $m){if((int)$m['catalog_hotel_id']===$lid)$same=true;else$reason[]='source_mapped_other';}
        foreach($byL[$lid]??[] as $m)if((int)$m['anex_hotel_id']!==$aid)$reason[]='target_occupied_other';
        if($same)$reason[]='already_same';
        if(!empty($byD[$aid]))$reason[]='manual_decision_present';
        foreach($byE[$aid]??[] as $e)if((int)($e['catalog_hotel_id']??0)===$lid)$reason[]='pair_excluded';
        $h=$byH[$lid]??null; if(!$h||(int)$h['is_active']!==1||(int)$h['country_id']!==$country)$reason[]='target_not_active_expected_country';
        $reason=array_values(array_unique($reason));
        if($reason){$held[]=['anex_hotel_id'=>$aid,'catalog_hotel_id'=>$lid,'reasons'=>$reason];continue;}
        $src=(string)$r['evidence_sha256']; $insert->execute([$aid,$lid,HD14_MATCH_CLASS,HD14_SCOPE,HD14_POLICY,$src,$mappingDigest]);
        $written[]=['anex_hotel_id'=>$aid,'catalog_hotel_id'=>$lid,'source_row_digest'=>$src,'mapping_digest'=>$mappingDigest,'creates_triple'=>isset($and[$lid])];
        if(isset($and[$lid]))$newTriple++;else$newNonTriple++;
    }
    hd14_put_new($dir.'/precommit.json',['operation_id'=>$op,'source_sha'=>$sourceSha,'plan_sha256'=>$planSha,'written'=>$written,'held'=>$held,'mapping_digest'=>$mappingDigest,'state'=>'sealed_before_commit','no_replay'=>true]);
    $db->commit(); $committed=true;
    foreach($written as $w){
        $rows=hd14_rows($db,'SELECT anex_hotel_id,catalog_hotel_id,match_class,scope,approval_policy,source_row_digest,mapping_digest,enabled FROM anex_hotel_search_mappings WHERE anex_hotel_id=? AND catalog_hotel_id=?',[$w['anex_hotel_id'],$w['catalog_hotel_id']]);
        if(count($rows)!==1)throw new RuntimeException('post_commit_row_count');$z=$rows[0];
        foreach(['match_class'=>HD14_MATCH_CLASS,'scope'=>HD14_SCOPE,'approval_policy'=>HD14_POLICY,'enabled'=>'1','source_row_digest'=>$w['source_row_digest'],'mapping_digest'=>$w['mapping_digest']] as $k=>$v)if((string)$z[$k]!==$v)throw new RuntimeException('post_commit_contract_'.$k);
        $post[]=$z;
    }
    $result=['operation_id'=>$op,'source_sha'=>$sourceSha,'status'=>'completed','plan_sha256'=>$planSha,'examined_count'=>count($pairs),'written_count'=>count($written),'held_count'=>count($held),'new_triple_count'=>$newTriple,'new_nontriple_count'=>$newNonTriple,'written'=>$written,'held'=>$held,'post_commit_readback'=>$post,'database_writes'=>count($written),'mapping_writes'=>count($written),'supplier_calls'=>0,'tourvisor_calls'=>0,'booking_calls'=>0,'lead_calls'=>0,'no_replay'=>true];
} catch(Throwable $e) {
    try{if($db&&$db->inTransaction())$db->rollBack();}catch(Throwable $ignored){}
    $result=['operation_id'=>$op,'source_sha'=>$sourceSha,'status'=>$committed?'committed_unverified':'blocked_rolled_back','reason_class'=>get_class($e),'reason_code'=>preg_match('/^[A-Za-z0-9_]+$/D',$e->getMessage())?$e->getMessage():'runtime_error','plan_sha256'=>$planSha,'written_count'=>$committed?count($written):0,'held_count'=>count($held),'new_triple_count'=>$committed?$newTriple:0,'new_nontriple_count'=>$committed?$newNonTriple:0,'written'=>$written,'held'=>$held,'post_commit_readback'=>$post,'database_writes'=>$committed?count($written):0,'mapping_writes'=>$committed?count($written):0,'supplier_calls'=>0,'tourvisor_calls'=>0,'no_replay'=>true];
}
$rh=hd14_put_new($dir.'/result.json',$result);
hd14_put_new($dir.'/receipt.json',['operation_id'=>$op,'source_sha'=>$sourceSha,'state'=>$result['status'],'plan_sha256'=>$planSha,'result_sha256'=>$rh,'readback_verified'=>hash_file('sha256',$dir.'/result.json')===$rh,'written_count'=>$result['written_count'],'no_replay'=>true]);
echo hd14_json(['operation_id'=>$op,'status'=>$result['status'],'written_count'=>$result['written_count'],'held_count'=>$result['held_count'],'new_triple_count'=>$result['new_triple_count'],'result_sha256'=>$rh])."\n";
exit($result['status']==='completed'?0:2);
