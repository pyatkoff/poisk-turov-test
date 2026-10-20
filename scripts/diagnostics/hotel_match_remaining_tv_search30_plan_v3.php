<?php
declare(strict_types=1);

/**
 * Plan CURRENT Tourvisor identity acquisition at hotel/provider edge granularity.
 *
 * Key invariants:
 * - only COMMON4 operators actually observed for this TV hotel in source=user_search
 *   become candidate edges;
 * - ANEX-only predecessor operations protect only operator 13, never the whole hotel;
 * - COMMON4 attempts protect all COMMON4 edges for the attempted hotel/context;
 * - already accepted, DB-saved, or immutable saved-detail evidence is not reacquired;
 * - output is read-only and batches at most 30 real TV hotel IDs by country.
 */
const OP='hotel-match-remaining-tv-search30-1971-20260921-v3';
const OPS=[13=>'anex',18=>'operator_115',25=>'operator_315',43=>'operator_342'];
const PROTECTED_IDS=[420,1244,81154,68705,72755];

function rr(PDO $db,string $sql,array $p=[]):array{
    $s=$db->prepare($sql);$s->execute(array_values($p));
    return $s->fetchAll(PDO::FETCH_ASSOC)?:[];
}
function loadp(string $env):array{
    $p=realpath((string)getenv($env));
    if(!$p)throw new RuntimeException('missing_'.$env);
    $x=json_decode((string)file_get_contents($p),true,512,JSON_THROW_ON_ERROR);
    if(!is_array($x))throw new RuntimeException('shape_'.$env);
    return $x;
}
function attempted(array $r):array{
    $o=[];
    foreach(($r['batches']??[])as$b)foreach(($b['hotel_ids']??[])as$id)$o[(int)$id]=true;
    foreach(($r['attempted_batches']??[])as$b)foreach(($b['hotel_ids']??[])as$id)$o[(int)$id]=true;
    return $o;
}
function edgeKey(int $hotelId,int $operatorId):string{return $hotelId.'|'.$operatorId;}
function protectEdges(array &$edges,array $hotelIds,array $operatorIds):void{
    foreach($hotelIds as$id)foreach($operatorIds as$op)$edges[edgeKey((int)$id,(int)$op)]=true;
}
function artifactEvidence(array $result):array{
    $out=[];
    foreach(($result['rows']??[])as$r){
        $hid=(int)($r['tv_hotel_id']??0);$op=(int)($r['operator_id']??0);
        $native=trim((string)($r['native_hotel_id']??''));
        if($hid>0&&isset(OPS[$op])&&$native!=='')$out[edgeKey($hid,$op)]=true;
    }
    return $out;
}

if(($argv[1]??'')==='--self-test'){
    if(count(array_chunk(range(1,61),30))!==3)throw new RuntimeException('chunk');
    $x=attempted(['attempted_batches'=>[['hotel_ids'=>[1,2]]]]);
    if(array_keys($x)!==[1,2])throw new RuntimeException('attempted');
    $p=[];protectEdges($p,[1],[13]);
    if(!isset($p['1|13'])||isset($p['1|25']))throw new RuntimeException('edge_scope');
    $p=[];protectEdges($p,[1],array_keys(OPS));
    if(count($p)!==4||!isset($p['1|43']))throw new RuntimeException('common4_scope');
    $e=artifactEvidence(['rows'=>[
        ['tv_hotel_id'=>10,'operator_id'=>25,'native_hotel_id'=>'123'],
        ['tv_hotel_id'=>11,'operator_id'=>25,'native_hotel_id'=>''],
        ['tv_hotel_id'=>12,'operator_id'=>99,'native_hotel_id'=>'777'],
    ]]);
    if(array_keys($e)!==['10|25'])throw new RuntimeException('artifact_evidence');
    echo "REMAINING_SEARCH30_PLAN_V3_SELFTEST_OK\n";exit;
}
if(PHP_SAPI!=='cli'||($argv[1]??'')!=='--execute')throw new RuntimeException('disabled');

$root=realpath((string)getenv('ANYTOUR_ROOT'));
if(!$root||basename($root)!=='anytoour.ru')throw new RuntimeException('root');
$r20=loadp('MATCH_REVERSE20_PATH');
$r21=loadp('MATCH_REVERSE21_PATH');
$prior=loadp('MATCH_SEARCH30_PATH');
$v6=loadp('MATCH_V6_PATH');
$detail=loadp('MATCH_SAVED_DETAIL_PATH');
if(($r20['operation']??'')!=='hotel-match-reverse686-live-anex-batches-1971-20260920-v1'
    ||($r21['operation']??'')!=='hotel-match-reverse110-live-anex-continuation-1971-20260921-v2'
    ||($prior['operation']??'')!=='hotel-match-residual2041-search30-common4-1971-20260921-v3'
    ||($v6['operation']??'')!=='hotel-match-remaining-tv-search30-1971-20260921-v6'
    ||($v6['state']??'')!=='terminal_failed_no_replay'
    ||($detail['operation']??'')!=='hotel-match-detail251-current-bridge-audit-1971-20260921-v1'
    ||($detail['state']??'')!=='completed_read_only')throw new RuntimeException('input_operation');

$noReplay=[];
// These predecessors were explicitly ANEX-only; they protect operator 13 only.
protectEdges($noReplay,array_keys(attempted($r20)),[13]);
protectEdges($noReplay,array_keys(attempted($r21)),[13]);
// These searches were COMMON4/no operator filter. Unknown/terminal memberships remain no-replay.
protectEdges($noReplay,array_keys(attempted($prior)),array_keys(OPS));
protectEdges($noReplay,array_keys(attempted($v6)),array_keys(OPS));
protectEdges($noReplay,PROTECTED_IDS,array_keys(OPS));
$artifactSaved=artifactEvidence($detail);

require_once __DIR__.'/hotel_match_anex_effective_coverage.php';
require_once (is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php');
$db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
$db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
try{
    $seen=rr($db,"SELECT hotel_id,MAX(observed_at) last_seen FROM tour_price_observations WHERE source='user_search' AND hotel_id>0 GROUP BY hotel_id ORDER BY hotel_id");
    $ids=array_values(array_unique(array_map(fn($r)=>(int)$r['hotel_id'],$seen)));
    $hot=[];
    foreach(array_chunk($ids,500)as$c){
        $ph=implode(',',array_fill(0,count($c),'?'));
        foreach(rr($db,"SELECT id,name,country_id,country_name,is_active FROM catalog_hotels WHERE id IN ($ph)",$c)as$h)$hot[(int)$h['id']]=$h;
    }

    // Relevant edges are the COMMON4 operators actually observed for each user-seen hotel.
    $observed=[];$observedEdgeCount=0;
    foreach(rr($db,"SELECT hotel_id,operator_id,MAX(observed_at) last_seen FROM tour_price_observations WHERE source='user_search' AND hotel_id>0 AND operator_id IN (13,18,25,43) GROUP BY hotel_id,operator_id ORDER BY hotel_id,operator_id")as$r){
        $hid=(int)$r['hotel_id'];$op=(int)$r['operator_id'];
        if($hid<=0||!isset(OPS[$op]))continue;
        $observed[$hid][$op]=(string)$r['last_seen'];$observedEdgeCount++;
    }

    $anex=AnyTourMatchAnexEffectiveCoverage::fromPdo($db);$accepted=[];
    foreach($anex['by_local']as$local=>$native)if($native!==[])$accepted[edgeKey((int)$local,13)]=true;
    foreach(rr($db,"SELECT supplier_namespace,local_hotel_id FROM andromeda_hotel_identities WHERE decision_status='accepted' AND local_hotel_id IS NOT NULL AND supplier_namespace IN ('operator_115','operator_315','operator_342')")as$r){
        $op=array_search((string)$r['supplier_namespace'],OPS,true);
        if($op!==false)$accepted[edgeKey((int)$r['local_hotel_id'],(int)$op)]=true;
    }

    $saved=[];
    if((bool)$db->query("SHOW TABLES LIKE 'tour_operator_identity_observations'")->fetchColumn()){
        foreach(rr($db,"SELECT hotel_id,operator_id FROM tour_operator_identity_observations WHERE source='user_search' AND operator_id IN (13,18,25,43) AND operator_link IS NOT NULL AND operator_link<>''")as$r){
            $saved[edgeKey((int)$r['hotel_id'],(int)$r['operator_id'])]=true;
        }
    }

    $stats=[
        'current_userseen'=>count($ids),
        'common4_observed_hotels'=>count($observed),
        'common4_observed_edges'=>$observedEdgeCount,
        'inactive_or_excluded'=>0,
        'no_common4_observation'=>0,
        'all_relevant_edges_evidenced_or_protected'=>0,
        'search_targets'=>0,
        'search_edges'=>0,
        'missing_edges'=>[],
        'suppressed'=>['accepted'=>0,'db_saved'=>0,'artifact_saved'=>0,'no_replay'=>0],
        'no_replay_edges'=>count($noReplay),
        'artifact_saved_edges'=>count($artifactSaved),
    ];
    $targets=[];
    foreach($ids as$id){
        $h=$hot[$id]??null;
        if(!$h||(int)$h['is_active']!==1||in_array(mb_strtolower((string)$h['country_name'],'UTF-8'),['россия','абхазия','russia','russian federation','abkhazia'],true)){
            $stats['inactive_or_excluded']++;continue;
        }
        $rel=$observed[$id]??[];
        if(!$rel){$stats['no_common4_observation']++;continue;}
        ksort($rel,SORT_NUMERIC);$missing=[];
        foreach($rel as$op=>$lastSeen){
            $k=edgeKey($id,(int)$op);
            if(isset($accepted[$k])){$stats['suppressed']['accepted']++;continue;}
            if(isset($saved[$k])){$stats['suppressed']['db_saved']++;continue;}
            if(isset($artifactSaved[$k])){$stats['suppressed']['artifact_saved']++;continue;}
            if(isset($noReplay[$k])){$stats['suppressed']['no_replay']++;continue;}
            $missing[]=(int)$op;$stats['search_edges']++;
            $stats['missing_edges'][(string)$op]=($stats['missing_edges'][(string)$op]??0)+1;
        }
        if(!$missing){$stats['all_relevant_edges_evidenced_or_protected']++;continue;}
        $targets[]=[
            'tv_hotel_id'=>$id,'name'=>(string)$h['name'],'country_id'=>(int)$h['country_id'],'country_name'=>(string)$h['country_name'],
            'observed_operator_ids'=>array_map('intval',array_keys($rel)),
            'last_seen_by_operator'=>array_combine(array_map('strval',array_keys($rel)),array_values($rel)),
            'missing_operator_ids'=>$missing,'safe_to_write_now'=>false,
        ];
        $stats['search_targets']++;
    }
    $db->rollBack();

    $by=[];foreach($targets as$t)$by[$t['country_id']][]=$t;
    $groups=[];$n=0;ksort($by,SORT_NUMERIC);
    foreach($by as$cid=>$rows){
        usort($rows,fn($a,$b)=>$a['tv_hotel_id']<=>$b['tv_hotel_id']);
        foreach(array_chunk($rows,30)as$c){
            $groups[]=['batch'=>++$n,'country_id'=>(int)$cid,'country_name'=>$c[0]['country_name'],'hotel_ids'=>array_column($c,'tv_hotel_id'),'targets'=>$c];
        }
    }
    echo json_encode([
        'operation'=>OP,'state'=>'current_plan_complete','stats'=>$stats,'group_count'=>count($groups),'groups'=>$groups,
        'provider_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'safe_to_write_now'=>false,
    ],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw$e;}
