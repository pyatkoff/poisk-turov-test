<?php
declare(strict_types=1);

const HM_COMMON4_OBS_OP = 'hotel-match-common4-current-observation-plan-1971-20260919-v1';

function hm_c4obs_rows(PDO $db, string $sql, array $params=[]): array {
    $s=$db->prepare($sql); $s->execute(array_values($params)); return $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
function hm_c4obs_json(array $v): string { return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n"; }
function hm_c4obs_table(PDO $db,string $name): bool {
    $r=hm_c4obs_rows($db,"SELECT COUNT(*) AS c FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?",[$name]);
    return (int)($r[0]['c']??0)===1;
}
function hm_c4obs_host(string $url): ?array {
    $url=trim($url); if($url==='')return null; $p=parse_url($url); if(!is_array($p)||empty($p['host']))return null;
    return ['host'=>strtolower((string)$p['host']),'path'=>(string)($p['path']??'')];
}
if(($argv[1]??'')==='--self-test') {
    $x=hm_c4obs_host('https://Example.COM/a/b?x=1');
    if(($x['host']??'')!=='example.com'||($x['path']??'')!=='/a/b'||hm_c4obs_host('')!==null)throw new RuntimeException('self_test');
    echo "COMMON4_CURRENT_OBSERVATION_PLAN_SELFTEST_OK\n"; exit;
}
if(PHP_SAPI!=='cli'||($argv[1]??'')!=='--execute')throw new RuntimeException('disabled');
$dir=realpath((string)getenv('MATCH_OPERATION_DIR')); $root=realpath((string)getenv('ANYTOUR_ROOT'));
if(!$dir||!$root||basename($root)!=='anytoour.ru')throw new RuntimeException('paths');
$res=json_decode((string)file_get_contents($dir.'/reservation.json'),true,64,JSON_THROW_ON_ERROR);
if(($res['operation']??'')!==HM_COMMON4_OBS_OP||($res['state']??'')!=='reserved_before_db')throw new RuntimeException('reservation');
require_once $dir.'/payload/hotel_match_common4_operator_binding_v1.php';
require_once (is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php');
$db=v2_data_db(); $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ'); $db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
try {
    $haveObs=hm_c4obs_table($db,'tour_operator_identity_observations');
    $havePrice=hm_c4obs_table($db,'tour_price_observations');
    if(!$haveObs) throw new RuntimeException('operator_observation_table_missing');
    $stats=hm_c4obs_rows($db,"SELECT operator_id,COALESCE(NULLIF(TRIM(operator_name),''),'') AS operator_name,COUNT(*) AS observation_rows,COUNT(DISTINCT hotel_id) AS unique_hotels,SUM(operator_link IS NOT NULL AND operator_link<>'') AS link_rows,MAX(last_seen_at) AS last_seen_at FROM tour_operator_identity_observations WHERE source='user_search' GROUP BY operator_id,operator_name ORDER BY unique_hotels DESC,operator_id");
    $dict=['operators'=>array_map(fn($r)=>['id'=>(int)$r['operator_id'],'name'=>(string)$r['operator_name']],$stats)];
    $canonical=[];
    foreach(array_keys(hm_common4_aliases()) as $key){
        try {$canonical[$key]=['state'=>'resolved','binding'=>hm_common4_resolve($dict,'tourvisor',[$key])[$key]];}
        catch(Throwable $e){$canonical[$key]=['state'=>'unresolved','reason'=>$e->getMessage()];}
    }
    $resolvedIds=[]; foreach($canonical as $key=>$v) if(($v['state']??'')==='resolved') $resolvedIds[(int)$v['binding']['provider_operator_id']]=$key;
    $edge=[];$hosts=[];$samples=[];
    if($resolvedIds){
        $ids=array_keys($resolvedIds); $ph=implode(',',array_fill(0,count($ids),'?'));
        $rows=hm_c4obs_rows($db,"SELECT o.hotel_id,o.hotel_name,o.country_id,o.region_id,o.subregion_id,o.region_name,o.subregion_name,o.latitude,o.longitude,o.operator_id,o.operator_name,o.search_id,o.tour_id,o.operator_link,o.last_seen_at,o.observation_count FROM tour_operator_identity_observations o WHERE o.source='user_search' AND o.operator_id IN ($ph) ORDER BY o.last_seen_at DESC,o.id DESC",$ids);
        foreach($rows as $r){
            $op=(int)$r['operator_id'];$canon=$resolvedIds[$op];
            if(!isset($edge[$canon]))$edge[$canon]=['observation_rows'=>0,'unique_hotels'=>[],'link_rows'=>0,'tour_rows'=>0,'latest_seen'=>null];
            $edge[$canon]['observation_rows']++;$edge[$canon]['unique_hotels'][(int)$r['hotel_id']]=true;
            if(trim((string)$r['tour_id'])!=='')$edge[$canon]['tour_rows']++;
            if(trim((string)$r['operator_link'])!==''){
                $edge[$canon]['link_rows']++;$hp=hm_c4obs_host((string)$r['operator_link']);if($hp){$k=$hp['host'].'|'.$hp['path'];$hosts[$canon][$k]=($hosts[$canon][$k]??0)+1;}
            }
            $seen=(string)$r['last_seen_at']; if($edge[$canon]['latest_seen']===null||$seen>$edge[$canon]['latest_seen'])$edge[$canon]['latest_seen']=$seen;
            if(count($samples[$canon]??[])<25)$samples[$canon][]=array_intersect_key($r,array_flip(['hotel_id','hotel_name','country_id','region_name','subregion_name','operator_id','operator_name','search_id','tour_id','operator_link','last_seen_at','observation_count']));
        }
        foreach($edge as &$e){$e['unique_hotels']=count($e['unique_hotels']);}unset($e);
    }
    $priceContext=[];
    if($havePrice&&$resolvedIds){
        $ids=array_keys($resolvedIds);$ph=implode(',',array_fill(0,count($ids),'?'));
        $p=hm_c4obs_rows($db,"SELECT operator_id,COUNT(*) AS rows_count,COUNT(DISTINCT hotel_id) AS unique_hotels,MIN(departure_date) AS min_date,MAX(departure_date) AS max_date,MAX(observed_at) AS latest_observed FROM tour_price_observations WHERE source='user_search' AND operator_id IN ($ph) AND departure_date>=CURRENT_DATE() GROUP BY operator_id",$ids);
        foreach($p as $r){$id=(int)$r['operator_id'];if(isset($resolvedIds[$id]))$priceContext[$resolvedIds[$id]]=$r;}
    }
    foreach($hosts as &$h){arsort($h);$h=array_slice($h,0,12,true);}unset($h);
    $clock=hm_c4obs_rows($db,'SELECT UTC_TIMESTAMP AS db_utc_timestamp')[0]['db_utc_timestamp']??null;
    $db->exec('ROLLBACK');
    $out=['operation'=>HM_COMMON4_OBS_OP,'state'=>'completed_read_only','captured_at_utc'=>$clock,'tables'=>['operator_observations'=>$haveObs,'tour_price_observations'=>$havePrice],'operator_stats'=>$stats,'common4_bindings'=>$canonical,'common4_observation_coverage'=>$edge,'common4_future_price_context'=>$priceContext,'link_hosts_paths'=>$hosts,'latest_samples'=>$samples,'supplier_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'no_replay'=>true];
    $raw=hm_c4obs_json($out);$sha=hash('sha256',$raw);file_put_contents($dir.'/result.json',$raw,LOCK_EX);
    $receipt=['operation'=>HM_COMMON4_OBS_OP,'state'=>'completed_read_only','result_sha256'=>$sha,'readback_verified'=>hash('sha256',(string)file_get_contents($dir.'/result.json'))===$sha,'provider_accessed'=>false,'supplier_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'no_replay'=>true];
    file_put_contents($dir.'/receipt.json',hm_c4obs_json($receipt),LOCK_EX); echo $raw;
} catch(Throwable $e){ if($db->inTransaction())$db->rollBack(); throw $e; }
