<?php
declare(strict_types=1);

const HMV3A_OPERATION='hotel-match-v3-current-candidates-accept-1971-20260918-v2';
const HMV3A_SOURCE_OPERATION='hotel-match-tv-samo-common4-full-drain-1971-20260918-v3';
const HMV3A_SOURCE_RESULT_SHA='79812e2b71e8d0c201a7e5ff3132d1b68f3d6d00961c1dc26d7bca438e8d3d10';
const HMV3A_SOURCE_RUN=35290070669;
const HMV3A_SOURCE_ARTIFACT=10525638655;

function hmv3a_manifest(): array {
    $json=<<<'JSON'
[
{"tv":1437,"samo":"110643","tv_name":"PRIMA HOTEL","samo_name":"Prima Hotel","star":3,"overlap":["funsun"],"tv_fp":{"anex":2,"funsun":1},"samo_fp":{"funsun":1},"rooms":[{"room_key":"standard","tv_rooms":["standard"],"samo_rooms":["Standard"],"operators":["funsun"],"contexts":["funsun|2026-09-25|7|2|0"],"meal_pairs":["bb только завтрак ↔ bed breakfast"],"evidence_count":1}]},
{"tv":43228,"samo":"2000073785","tv_name":"SHERWOOD PREMIO (EX. SHERWOOD PRIZE HOTEL)","samo_name":"Sherwood Premio Hotel (ex. Sherwood Prize Hotel)","star":3,"overlap":["funsun"],"tv_fp":{"funsun":1},"samo_fp":{"biblio":1,"funsun":1},"rooms":[{"room_key":"standard","tv_rooms":["standard"],"samo_rooms":["Standard"],"operators":["funsun"],"contexts":["funsun|2026-09-25|7|2|0"],"meal_pairs":["bb только завтрак ↔ bed breakfast"],"evidence_count":1}]},
{"tv":72931,"samo":"2000062466","tv_name":"CITRUS PARK HOTEL","samo_name":"Citrus Park Hotel","star":3,"overlap":[],"tv_fp":{"anex":1,"intourist":1},"samo_fp":{"biblio":1},"rooms":[]},
{"tv":112789,"samo":"2000093761","tv_name":"HAMPTON BY HILTON ANTALYA AIRPORT","samo_name":"Hampton by Hilton Antalya Airport","star":4,"overlap":["funsun"],"tv_fp":{"funsun":1},"samo_fp":{"funsun":1},"rooms":[{"room_key":"standard","tv_rooms":["standard"],"samo_rooms":["Standard"],"operators":["funsun"],"contexts":["funsun|2026-09-25|7|2|0"],"meal_pairs":["bb только завтрак ↔ bed breakfast"],"evidence_count":1}]},
{"tv":1100,"samo":"733","tv_name":"CLUB HOTEL SERA","samo_name":"Club Hotel Sera","star":5,"overlap":["funsun"],"tv_fp":{"funsun":1,"intourist":1},"samo_fp":{"funsun":1},"rooms":[{"room_key":"park","tv_rooms":["park"],"samo_rooms":["Park"],"operators":["funsun"],"contexts":["funsun|2026-09-25|7|2|0"],"meal_pairs":["uai ультра все включено ↔ ultra all inclusive"],"evidence_count":1}]},
{"tv":1146,"samo":"2000023030","tv_name":"AKRA ANTALYA","samo_name":"Akra Antalya","star":5,"overlap":["funsun"],"tv_fp":{"anex":8,"funsun":7,"intourist":4},"samo_fp":{"funsun":1},"rooms":[{"room_key":"deluxe city view","tv_rooms":["deluxe city view"],"samo_rooms":["Deluxe City View"],"operators":["funsun"],"contexts":["funsun|2026-09-25|7|2|0"],"meal_pairs":["bb только завтрак ↔ room only","fb полный пансион ↔ room only","hb завтрак ужин ↔ room only","room only ↔ room only"],"evidence_count":4}]},
{"tv":1258,"samo":"189892","tv_name":"IC HOTELS GREEN PALACE","samo_name":"IC Hotels Green Palace","star":5,"overlap":["funsun"],"tv_fp":{"funsun":3},"samo_fp":{"funsun":2},"rooms":[{"room_key":"standard","tv_rooms":["standard"],"samo_rooms":["Standard"],"operators":["funsun"],"contexts":["funsun|2026-09-25|7|2|0"],"meal_pairs":["ai все включено ↔ high end all inclusive"],"evidence_count":1}]},
{"tv":1407,"samo":"7490","tv_name":"OZKAYMAK FALEZ HOTEL","samo_name":"Ozkaymak Falez Hotel","star":5,"overlap":["funsun"],"tv_fp":{"anex":1,"funsun":1},"samo_fp":{"biblio":1,"funsun":1},"rooms":[{"room_key":"standard land view","tv_rooms":["standard land view"],"samo_rooms":["Standard Land View"],"operators":["funsun"],"contexts":["funsun|2026-09-25|7|2|0"],"meal_pairs":["uai ультра все включено ↔ ultra all inclusive"],"evidence_count":1}]},
{"tv":1436,"samo":"2000021297","tv_name":"PORTO BELLO","samo_name":"Porto Bello Hotel Resort & Spa","star":5,"overlap":["funsun"],"tv_fp":{"anex":1,"funsun":1},"samo_fp":{"funsun":1},"rooms":[{"room_key":"standard side sea view","tv_rooms":["standard side sea view"],"samo_rooms":["Standard Side Sea View"],"operators":["funsun"],"contexts":["funsun|2026-09-25|7|2|0"],"meal_pairs":["uai ультра все включено ↔ ultra all inclusive"],"evidence_count":1}]},
{"tv":15889,"samo":"2000034131","tv_name":"GRAND PARK LARA","samo_name":"Grand Park Lara","star":5,"overlap":["funsun"],"tv_fp":{"anex":1,"funsun":1,"intourist":1},"samo_fp":{"funsun":1},"rooms":[]},
{"tv":47025,"samo":"2000059197","tv_name":"ADALYA ELITE LARA","samo_name":"Adalya Elite Lara","star":5,"overlap":["funsun"],"tv_fp":{"anex":1,"funsun":1,"intourist":1},"samo_fp":{"funsun":1},"rooms":[{"room_key":"standard land view","tv_rooms":["standard land view"],"samo_rooms":["Standard Land View"],"operators":["funsun"],"contexts":["funsun|2026-09-25|7|2|0"],"meal_pairs":["uai ультра все включено ↔ ultra all inclusive"],"evidence_count":1}]},
{"tv":1464,"samo":"2000034210","tv_name":"ROYAL WINGS HOTEL","samo_name":"Royal Wings Hotel","star":5,"overlap":[],"tv_fp":{"anex":3},"samo_fp":{"funsun":1},"rooms":[]},
{"tv":3415,"samo":"2000034208","tv_name":"ROYAL HOLIDAY PALACE","samo_name":"Royal Holiday Palace","star":5,"overlap":[],"tv_fp":{"anex":1,"intourist":2},"samo_fp":{"funsun":1},"rooms":[]},
{"tv":53514,"samo":"2000034898","tv_name":"ROYAL SEGINUS","samo_name":"Royal Seginus Hotel","star":5,"overlap":[],"tv_fp":{"anex":2,"intourist":1},"samo_fp":{"funsun":1},"rooms":[]}
]
JSON;
    $rows=json_decode($json,true,64,JSON_THROW_ON_ERROR);
    if(!is_array($rows)||count($rows)!==14)throw new RuntimeException('manifest_count');
    $s=[];$t=[];$rooms=0;
    foreach($rows as $r){
        $eid=(string)($r['samo']??'');$local=(int)($r['tv']??0);
        if(!preg_match('/^[1-9][0-9]{0,18}$/D',$eid)||$local<1||isset($s[$eid])||isset($t[$local]))
            throw new RuntimeException('manifest_identity');
        if(hmv3a_hotel_key((string)$r['tv_name'])!==hmv3a_hotel_key((string)$r['samo_name']))
            throw new RuntimeException('manifest_name');
        $s[$eid]=true;$t[$local]=true;$rooms+=count($r['rooms']??[]);
    }
    if($rooms!==9)throw new RuntimeException('manifest_room_count');
    return $rows;
}
function hmv3a_hotel_key(string $v): string {
    $v=mb_strtolower(trim($v),'UTF-8');$v=str_replace(['&','+'],' ',$v);
    $v=preg_replace('/[^\\p{L}\\p{N}]+/u',' ',$v)??$v;
    $tokens=preg_split('/\\s+/u',trim($v),-1,PREG_SPLIT_NO_EMPTY)?:[];
    $drop=['hotel'=>1,'resort'=>1,'spa'=>1,'отель'=>1,'гостиница'=>1];$out=[];
    foreach($tokens as $x)if(!isset($drop[$x]))$out[]=$x;
    return implode(' ',$out);
}
function hmv3a_json(mixed $v): string {
    return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
}
function hmv3a_durable(string $path,array $v): string {
    $raw=hmv3a_json($v)."\n";$f=@fopen($path,'x+b');if(!$f)throw new RuntimeException('durable_exists');
    try{if(fwrite($f,$raw)!==strlen($raw)||!fflush($f))throw new RuntimeException('durable_write');
        if(function_exists('fsync')&&!fsync($f))throw new RuntimeException('durable_sync');
        rewind($f);if(stream_get_contents($f)!==$raw)throw new RuntimeException('durable_readback');
    }finally{fclose($f);}
    return hash('sha256',$raw);
}
function hmv3a_rows(PDO $pdo,string $sql,array $params=[]): array {
    $s=$pdo->prepare($sql);$s->execute(array_values($params));return $s->fetchAll(PDO::FETCH_ASSOC)?:[];
}
function hmv3a_table_innodb(PDO $pdo,string $table): void {
    $s=$pdo->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    $s->execute([$table]);$e=$s->fetchColumn();
    if(!is_string($e)||strcasecmp($e,'InnoDB')!==0)throw new RuntimeException('transactional_table_required');
}

if(in_array('--self-test',$argv??[],true)){
    $m=hmv3a_manifest();
    if(hmv3a_hotel_key('Porto Bello Hotel Resort & Spa')!=='porto bello')throw new RuntimeException('key_porto');
    if(hmv3a_hotel_key('SHERWOOD PREMIO (EX. SHERWOOD PRIZE HOTEL)')!=='sherwood premio ex sherwood prize')throw new RuntimeException('key_sherwood');
    echo "MATCH_V3_CURRENT_ACCEPT_SELFTEST_OK rows=".count($m)." rooms=9\n";exit(0);
}
if(PHP_SAPI!=='cli')exit(2);

$op=(string)getenv('OPERATION_ID');$sourceSha=(string)getenv('MATCH_SOURCE_SHA');
if($op!==HMV3A_OPERATION||!preg_match('/^[a-f0-9]{40}$/D',$sourceSha))throw new RuntimeException('execution_guard');
$root=realpath((string)getenv('ANYTOUR_ROOT'));if(!$root)throw new RuntimeException('root');
$home=rtrim((string)getenv('HOME'),'/');$dir=$home.'/.anytour-match/operations/'.HMV3A_OPERATION;
if(!is_dir(dirname($dir))&&!mkdir(dirname($dir),0700,true))throw new RuntimeException('operation_parent');
if(!mkdir($dir,0700))throw new RuntimeException('operation_exists');
$manifest=hmv3a_manifest();
hmv3a_durable($dir.'/reservation.json',[
    'operation_id'=>HMV3A_OPERATION,'source_sha'=>$sourceSha,'state'=>'reserved_before_db_write',
    'source_operation'=>HMV3A_SOURCE_OPERATION,'source_result_sha256'=>HMV3A_SOURCE_RESULT_SHA,
    'planned_count'=>count($manifest),'room_evidence_count'=>9,'no_replay'=>true,
]);

$dbf=is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php';
require_once $dbf;$pdo=v2_data_db();$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
hmv3a_table_innodb($pdo,'andromeda_hotel_identities');hmv3a_table_innodb($pdo,'catalog_hotels');

$written=[];$held=[];$committed=false;
try{
    $pdo->exec('SET SESSION innodb_lock_wait_timeout=20');
    $pdo->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');$pdo->beginTransaction();

    $external=array_column($manifest,'samo');$locals=array_map('intval',array_column($manifest,'tv'));
    $ep=implode(',',array_fill(0,count($external),'?'));$lp=implode(',',array_fill(0,count($locals),'?'));
    $ids=hmv3a_rows($pdo,"SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status,catalog_sha256,evidence_sha256,evidence_json
        FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id IN ($ep) FOR UPDATE",$external);
    $hotels=hmv3a_rows($pdo,"SELECT id,country_id,name,is_active FROM catalog_hotels WHERE id IN ($lp) FOR UPDATE",$locals);
    $byExt=[];foreach($ids as $r)$byExt[(string)$r['external_hotel_id']]=$r;
    $byLocal=[];foreach($hotels as $r)$byLocal[(int)$r['id']]=$r;

    $update=$pdo->prepare("UPDATE andromeda_hotel_identities
        SET local_hotel_id=?,decision_status='accepted',evidence_sha256=?,evidence_json=?
        WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=?
          AND decision_status='pending' AND local_hotel_id IS NULL
          AND evidence_sha256=? AND catalog_sha256=?");

    foreach($manifest as $candidate){
        $eid=(string)$candidate['samo'];$target=(int)$candidate['tv'];$reasons=[];
        $src=$byExt[$eid]??null;$hotel=$byLocal[$target]??null;
        if(!$src)$reasons[]='source_identity_missing';
        if(!$hotel||(int)$hotel['is_active']!==1||(int)$hotel['country_id']!==4)$reasons[]='target_not_active_turkey';
        if($hotel){
            $current=hmv3a_hotel_key((string)$hotel['name']);
            if($current!==hmv3a_hotel_key((string)$candidate['tv_name'])
                ||$current!==hmv3a_hotel_key((string)$candidate['samo_name']))$reasons[]='current_name_key_changed';
        }
        if($src){
            $status=(string)$src['decision_status'];$existing=$src['local_hotel_id']===null?null:(int)$src['local_hotel_id'];
            if($status==='accepted'){
                $reasons[]=$existing===$target?'already_same':'accepted_other_target';
            }elseif($status==='conflict')$reasons[]='current_conflict';
            elseif($status!=='pending'||$existing!==null)$reasons[]='source_state_not_pending_null';
            if(!is_string($src['evidence_json'])||!is_string($src['evidence_sha256'])
                ||!hash_equals((string)$src['evidence_sha256'],hash('sha256',(string)$src['evidence_json'])))
                $reasons[]='source_evidence_digest_invalid';
        }
        $reasons=array_values(array_unique($reasons));
        if($reasons){
            $held[]=['external_hotel_id'=>$eid,'local_hotel_id'=>$target,'reasons'=>$reasons];continue;
        }
        $prior=json_decode((string)$src['evidence_json'],true,64,JSON_THROW_ON_ERROR);
        if(!is_array($prior)){
            $held[]=['external_hotel_id'=>$eid,'local_hotel_id'=>$target,'reasons'=>['source_evidence_not_object']];continue;
        }
        $evidence=[
            'prior_evidence'=>$prior,
            'source'=>'match_v3_exact_name_operator_fingerprint_owner_authorized',
            'operation_id'=>HMV3A_OPERATION,
            'source_operation'=>HMV3A_SOURCE_OPERATION,
            'source_result_sha256'=>HMV3A_SOURCE_RESULT_SHA,
            'source_run'=>HMV3A_SOURCE_RUN,
            'source_artifact'=>HMV3A_SOURCE_ARTIFACT,
            'target'=>['local_hotel_id'=>$target,'current_name'=>(string)$hotel['name'],'country_id'=>(int)$hotel['country_id']],
            'candidate'=>$candidate,
            'room_correspondences_are_evidence_only'=>true,
            'global_room_mapping_written'=>false,
        ];
        $ejson=hmv3a_json($evidence);$ehash=hash('sha256',$ejson);
        $update->execute([$target,$ehash,$ejson,$eid,(string)$src['evidence_sha256'],(string)$src['catalog_sha256']]);
        if($update->rowCount()!==1)throw new RuntimeException('concurrent_state_changed');
        $written[]=['external_hotel_id'=>$eid,'local_hotel_id'=>$target,'evidence_sha256'=>$ehash,'rooms_attached'=>count($candidate['rooms'])];
    }

    hmv3a_durable($dir.'/precommit.json',[
        'operation_id'=>HMV3A_OPERATION,'state'=>'prepared_before_commit','written'=>$written,'held'=>$held,
        'database_writes'=>count($written),'no_replay'=>true,
    ]);
    $pdo->commit();$committed=true;

    $readback=[];
    $read=$pdo->prepare("SELECT local_hotel_id,decision_status,evidence_sha256,evidence_json FROM andromeda_hotel_identities
        WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=?");
    foreach($written as $w){
        $read->execute([$w['external_hotel_id']]);$r=$read->fetch(PDO::FETCH_ASSOC);
        if(!$r||(int)$r['local_hotel_id']!==$w['local_hotel_id']||(string)$r['decision_status']!=='accepted'
            ||!hash_equals($w['evidence_sha256'],(string)$r['evidence_sha256'])
            ||!hash_equals($w['evidence_sha256'],hash('sha256',(string)$r['evidence_json'])))
            throw new RuntimeException('post_commit_readback_failed');
        $payload=json_decode((string)$r['evidence_json'],true,64,JSON_THROW_ON_ERROR);
        if(($payload['operation_id']??null)!==HMV3A_OPERATION)throw new RuntimeException('post_commit_evidence_operation');
        $readback[]=['external_hotel_id'=>$w['external_hotel_id'],'local_hotel_id'=>$w['local_hotel_id'],
            'decision_status'=>'accepted','evidence_sha256'=>$w['evidence_sha256'],'rooms_attached'=>$w['rooms_attached']];
    }
    $result=['status'=>'completed','operation_id'=>HMV3A_OPERATION,'source_sha'=>$sourceSha,
        'planned_count'=>14,'written_count'=>count($written),'held_count'=>count($held),
        'rooms_attached'=>array_sum(array_column($written,'rooms_attached')),
        'written'=>$written,'held'=>$held,'post_commit_readback'=>$readback,
        'database_writes'=>count($written),'mapping_writes'=>count($written),
        'supplier_calls'=>0,'tourvisor_calls'=>0,'booking_calls'=>0,'lead_writes'=>0,'metrika_writes'=>0,
        'committed'=>true,'readback_verified'=>true,'no_replay'=>true];
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    $safe=in_array($e->getMessage(),['concurrent_state_changed','post_commit_readback_failed','post_commit_evidence_operation'],true)
        ?$e->getMessage():'runtime_failure';
    $result=['status'=>'failed','operation_id'=>HMV3A_OPERATION,'source_sha'=>$sourceSha,'reason'=>$safe,
        'database_writes'=>$committed?count($written):0,'mapping_writes'=>$committed?count($written):0,
        'supplier_calls'=>0,'tourvisor_calls'=>0,'committed'=>$committed,'readback_verified'=>false,'no_replay'=>true];
}
$sha=hmv3a_durable($dir.'/result.json',$result);
hmv3a_durable($dir.'/receipt.json',['operation_id'=>HMV3A_OPERATION,'state'=>$result['status'],
    'result_sha256'=>$sha,'readback_verified'=>hash_file('sha256',$dir.'/result.json')===$sha,
    'written_count'=>$result['written_count']??0,'held_count'=>$result['held_count']??0,
    'database_writes'=>$result['database_writes']??0,'supplier_calls'=>0,'tourvisor_calls'=>0,'no_replay'=>true]);
echo hmv3a_json($result)."\n";
exit(($result['status']??'')==='completed'?0:2);
