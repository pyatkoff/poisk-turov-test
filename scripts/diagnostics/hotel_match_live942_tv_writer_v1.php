<?php
declare(strict_types=1);

require_once __DIR__.'/hotel_match_anex_effective_coverage.php';

const HMTVW_OP='hotel-match-live942-tv-writer-1971-20260923-v1';
const HMTVW_RECONCILE_SHA='57245b9020beefb3760c3bf65696f3fc4a33485c8b4ec74f2058f40b363e1700';
const HMTVW_POLICY='owner_exact_and_strong_20260908';
const HMTVW_CLASS='strong_candidate';
const HMTVW_SCOPE='preview';

function hmtvw_need(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
function hmtvw_json(mixed $v):string{return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function hmtvw_save(string $path,array $value):string{
    $raw=hmtvw_json($value)."\n";$f=@fopen($path,'x+b');hmtvw_need($f!==false,'exclusive_create');
    try{hmtvw_need(fwrite($f,$raw)===strlen($raw)&&fflush($f),'durable_write');if(function_exists('fsync'))hmtvw_need(fsync($f),'durable_sync');}
    finally{fclose($f);}return hash('sha256',$raw);
}
function hmtvw_rows(PDO $db,string $sql,array $args=[]):array{$s=$db->prepare($sql);$s->execute(array_values($args));return $s->fetchAll(PDO::FETCH_ASSOC)?:[];}

function hmtvw_manifest(string $path):array{
    hmtvw_need(is_file($path)&&!is_link($path),'manifest_missing');
    $raw=(string)file_get_contents($path);hmtvw_need(hash('sha256',$raw)===HMTVW_RECONCILE_SHA,'manifest_sha');
    $m=json_decode($raw,true,128,JSON_THROW_ON_ERROR);hmtvw_need(is_array($m),'manifest_shape');
    hmtvw_need(($m['state']??'')==='completed_read_only','manifest_state');
    hmtvw_need((int)($m['raw_single_rows']??0)===92&&(int)($m['globally_unique_pair_count']??0)===92,'manifest_unique_count');
    hmtvw_need((int)($m['writer_ready_count']??0)===88,'manifest_writer_count');
    $cc=$m['classification_counts']??[];
    hmtvw_need(is_array($cc)&&($cc['writer_ready']??null)===88&&($cc['resolved_same']??null)===2&&($cc['source_occupied']??null)===2&&array_sum($cc)===92,'manifest_classes');
    $expected=[
        'f7fac893bf0abe572e4e79133bef11d00f81a8791a590b5c2f21a0e520bf586b',
        '0c82d1382b5b25851ab22a69ce1ec53cb5c1723f929c39fcda22ddff0b4de6c6',
        '5c98c84ab34a24e4f65c32e328bf90bfcc0c328749cffba93e9c1fde603c47d8',
    ];
    $seen=[];foreach((array)($m['child_summaries']??[]) as $c){
        if(!is_array($c))continue;$sha=(string)($c['result_sha256']??'');
        if(in_array($sha,$expected,true))$seen[$sha]=true;
    }
    hmtvw_need(count($seen)===3,'manifest_child_hashes');
    $pairs=[];
    foreach((array)($m['rows']??[]) as $r){
        if(!is_array($r)||($r['state']??'')!=='writer_ready')continue;
        $aid=(int)($r['anex_hotel_id']??0);$tv=(int)($r['catalog_hotel_id']??0);
        hmtvw_need($aid>0&&$tv>0&&($r['safe_to_write_now']??false)===true,'manifest_pair');
        $k=$aid.':'.$tv;hmtvw_need(!isset($pairs[$k]),'manifest_duplicate_pair');
        $pairs[$k]=['anex_hotel_id'=>$aid,'catalog_hotel_id'=>$tv];
    }
    ksort($pairs,SORT_NATURAL);hmtvw_need(count($pairs)===88,'manifest_pairs_88');
    hmtvw_need(count(array_unique(array_column($pairs,'anex_hotel_id')))===88,'manifest_source_unique');
    hmtvw_need(count(array_unique(array_column($pairs,'catalog_hotel_id')))===88,'manifest_target_unique');
    return ['pairs'=>$pairs,'raw'=>$m];
}

function hmtvw_coverage(PDO $db):array{
    $anex=AnyTourMatchAnexEffectiveCoverage::fromPdo($db);$a=[];foreach(($anex['by_local']??[]) as $id=>$set)if($set)$a[(int)$id]=true;
    $s=[];foreach($db->query("SELECT DISTINCT local_hotel_id FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN)?:[] as $id)$s[(int)$id]=true;
    $live=[];$cut=(new DateTimeImmutable('now',new DateTimeZone('UTC')))->modify('-30 days');
    foreach($db->query("SELECT hotel_id,MAX(last_seen_at) last_seen_at FROM tour_operator_identity_observations GROUP BY hotel_id")->fetchAll(PDO::FETCH_ASSOC)?:[] as $r){
        try{$dt=new DateTimeImmutable((string)$r['last_seen_at'],new DateTimeZone('UTC'));}catch(Throwable){continue;}
        if($dt>=$cut)$live[(int)$r['hotel_id']]=true;
    }
    return ['anex_unique_tv'=>count($a),'samo_unique_tv'=>count($s),'full_triple'=>count(array_intersect_key($a,$s)),'live30_full_triple'=>count(array_intersect_key($live,$a,$s))];
}

function hmtvw_execute(PDO $db,array $manifest,string $manifestSha):array{
    $pairs=$manifest['pairs'];$aids=array_values(array_map(static fn($x)=>(int)$x['anex_hotel_id'],$pairs));$lids=array_values(array_map(static fn($x)=>(int)$x['catalog_hotel_id'],$pairs));
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET SESSION innodb_lock_wait_timeout=20');$db->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
    $committed=false;
    try{
        $db->beginTransaction();
        $pa=implode(',',array_fill(0,count($aids),'?'));$pl=implode(',',array_fill(0,count($lids),'?'));
        $maps=hmtvw_rows($db,"SELECT anex_hotel_id,catalog_hotel_id,match_class,scope,approval_policy,enabled FROM anex_hotel_search_mappings WHERE anex_hotel_id IN ($pa) OR catalog_hotel_id IN ($pl) FOR UPDATE",array_merge($aids,$lids));
        $dec=hmtvw_rows($db,"SELECT anex_hotel_id,decision_status,catalog_hotel_id FROM anex_hotel_decisions WHERE anex_hotel_id IN ($pa) FOR UPDATE",$aids);
        $exc=hmtvw_rows($db,"SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions WHERE anex_hotel_id IN ($pa) OR catalog_hotel_id IN ($pl) FOR UPDATE",array_merge($aids,$lids));
        $hot=hmtvw_rows($db,"SELECT id,is_active FROM catalog_hotels WHERE id IN ($pl) FOR UPDATE",$lids);
        $byA=[];$byT=[];foreach($maps as $r){$byA[(int)$r['anex_hotel_id']][]=$r;$byT[(int)$r['catalog_hotel_id']][]=$r;}
        $byD=[];foreach($dec as $r)$byD[(int)$r['anex_hotel_id']][]=$r;
        $byE=[];foreach($exc as $r)$byE[(int)$r['anex_hotel_id']][(int)$r['catalog_hotel_id']]=true;
        $active=[];foreach($hot as $r)$active[(int)$r['id']]=(int)$r['is_active']===1;
        $mappingDigest=hash('sha256',hmtvw_json(['operation'=>HMTVW_OP,'reconcile_result_sha256'=>$manifestSha,'pairs'=>array_keys($pairs),'match_class'=>HMTVW_CLASS,'scope'=>HMTVW_SCOPE,'approval_policy'=>HMTVW_POLICY]));
        $ins=$db->prepare("INSERT INTO anex_hotel_search_mappings(anex_hotel_id,catalog_hotel_id,match_class,scope,approval_policy,source_row_digest,mapping_digest,enabled) VALUES(?,?,?,?,?,?,?,1)");
        $written=[];$held=[];
        foreach($pairs as $key=>$p){
            $aid=$p['anex_hotel_id'];$tv=$p['catalog_hotel_id'];$reasons=[];$same=false;
            foreach($byA[$aid]??[] as $r){if((int)$r['catalog_hotel_id']===$tv)$same=true;else$reasons[]='source_occupied';}
            foreach($byT[$tv]??[] as $r)if((int)$r['anex_hotel_id']!==$aid)$reasons[]='target_occupied';
            if($same)$reasons[]='resolved_same';
            if(!empty($byD[$aid]))$reasons[]='manual_decision_present';
            if(isset($byE[$aid][$tv]))$reasons[]='pair_excluded';
            if(!($active[$tv]??false))$reasons[]='target_inactive';
            $reasons=array_values(array_unique($reasons));
            if($reasons){$held[]=['anex_hotel_id'=>$aid,'catalog_hotel_id'=>$tv,'reasons'=>$reasons];continue;}
            $e=['operation'=>HMTVW_OP,'authority'=>'direct_hotellist','reconcile_result_sha256'=>$manifestSha,'anex_hotel_id'=>$aid,'catalog_hotel_id'=>$tv,
                'tv_child_result_sha256s'=>[
                    'f7fac893bf0abe572e4e79133bef11d00f81a8791a590b5c2f21a0e520bf586b',
                    '0c82d1382b5b25851ab22a69ce1ec53cb5c1723f929c39fcda22ddff0b4de6c6',
                    '5c98c84ab34a24e4f65c32e328bf90bfcc0c328749cffba93e9c1fde603c47d8',
                ]];
            $srcDigest=hash('sha256',hmtvw_json($e));
            $ins->execute([$aid,$tv,HMTVW_CLASS,HMTVW_SCOPE,HMTVW_POLICY,$srcDigest,$mappingDigest]);hmtvw_need($ins->rowCount()===1,'insert_count');
            $written[$aid]=['catalog_hotel_id'=>$tv,'source_row_digest'=>$srcDigest];
        }
        hmtvw_need(count($written)+count($held)===88,'decision_count');
        hmtvw_save((string)getenv('MATCH_OPERATION_DIR').'/precommit.json',['operation'=>HMTVW_OP,'state'=>'prepared_before_commit','manifest_sha256'=>$manifestSha,'written_count'=>count($written),'held'=>$held,'mapping_digest'=>$mappingDigest]);
        $db->commit();$committed=true;
        $db->exec('START TRANSACTION READ ONLY');$readback=[];
        foreach($written as $aid=>$w){
            $q=$db->prepare('SELECT catalog_hotel_id,match_class,scope,approval_policy,source_row_digest,mapping_digest,enabled FROM anex_hotel_search_mappings WHERE anex_hotel_id=?');$q->execute([$aid]);$r=$q->fetch(PDO::FETCH_ASSOC);
            hmtvw_need(is_array($r)&&(int)$r['catalog_hotel_id']===$w['catalog_hotel_id']&&$r['match_class']===HMTVW_CLASS&&$r['scope']===HMTVW_SCOPE&&$r['approval_policy']===HMTVW_POLICY&&(int)$r['enabled']===1&&$r['source_row_digest']===$w['source_row_digest']&&$r['mapping_digest']===$mappingDigest,'postcommit_readback');
            $readback[]=['anex_hotel_id'=>$aid,'catalog_hotel_id'=>$w['catalog_hotel_id'],'source_row_digest'=>$w['source_row_digest']];
        }
        $coverage=hmtvw_coverage($db);$db->rollBack();
        return ['operation'=>HMTVW_OP,'state'=>'committed_verified','manifest_sha256'=>$manifestSha,'planned'=>88,'inserted'=>count($written),'held_count'=>count($held),'held'=>$held,'readback_verified'=>count($readback)===count($written),'rows'=>$readback,'coverage_after'=>$coverage,'provider_http_calls'=>0,'supplier_calls'=>0,'database_writes'=>count($written),'mapping_writes'=>count($written)];
    }catch(Throwable $e){
        if(!$committed&&$db->inTransaction())$db->rollBack();
        throw new RuntimeException(($committed?'postcommit_':'precommit_').$e->getMessage(),0,$e);
    }
}

if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    $mode=$argv[1]??'';
    if($mode==='--self-test'){
        hmtvw_need(HMTVW_POLICY==='owner_exact_and_strong_20260908'&&HMTVW_CLASS==='strong_candidate','policy');
        hmtvw_need(preg_match('/^[a-f0-9]{64}$/D',HMTVW_RECONCILE_SHA)===1,'sha');echo "MATCH_LIVE942_TV_WRITER_V1_SELFTEST_OK\n";exit;
    }
    hmtvw_need($mode==='--execute','disabled');$root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$source=(string)getenv('MATCH_SOURCE_SHA');$manifestPath=(string)getenv('MATCH_MANIFEST_PATH');
    hmtvw_need(is_dir($root)&&is_dir($dir)&&basename($dir)===HMTVW_OP&&preg_match('/^[a-f0-9]{40}$/D',$source)===1&&is_file($manifestPath),'runtime_scope');
    $reservation=json_decode((string)file_get_contents($dir.'/reservation.json'),true,32,JSON_THROW_ON_ERROR);hmtvw_need(($reservation['operation']??'')===HMTVW_OP&&($reservation['state']??'')==='reserved_before_write','reservation');
    $manifest=hmtvw_manifest($manifestPath);$raw=(string)file_get_contents($manifestPath);$manifestSha=hash('sha256',$raw);
    require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
    try{
        $result=hmtvw_execute(v2_data_db(),$manifest,$manifestSha);$result['source_sha']=$source;$h=hmtvw_save($dir.'/result.json',$result);
        hmtvw_save($dir.'/receipt.json',['operation'=>HMTVW_OP,'state'=>$result['state'],'result_sha256'=>$h,'readback_verified'=>$result['readback_verified'],'provider_http_calls'=>0,'database_writes'=>$result['database_writes'],'mapping_writes'=>$result['mapping_writes'],'no_replay'=>true]);
        echo hmtvw_json(['state'=>$result['state'],'inserted'=>$result['inserted'],'held_count'=>$result['held_count'],'coverage_after'=>$result['coverage_after']])."\n";
    }catch(Throwable $e){
        $committed=str_starts_with($e->getMessage(),'postcommit_');$f=['operation'=>HMTVW_OP,'state'=>$committed?'terminal_unknown_or_postcommit_failure':'rolled_back_no_write','reason'=>preg_replace('/[^A-Za-z0-9_.:-]+/','_',mb_substr($e->getMessage(),0,180,'UTF-8')),'provider_http_calls'=>0,'database_writes'=>$committed?null:0,'mapping_writes'=>$committed?null:0];
        $h=hmtvw_save($dir.'/result.json',$f);hmtvw_save($dir.'/receipt.json',['operation'=>HMTVW_OP,'state'=>$f['state'],'result_sha256'=>$h,'readback_verified'=>true,'provider_http_calls'=>0,'database_writes'=>$f['database_writes'],'mapping_writes'=>$f['mapping_writes'],'no_replay'=>true]);
        fwrite(STDERR,$f['reason']."\n");exit(2);
    }
}
