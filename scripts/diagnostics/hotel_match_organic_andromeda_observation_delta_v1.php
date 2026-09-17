<?php
declare(strict_types=1);

const HMOA_OP = 'hotel-match-organic-andromeda-observation-delta-1971-20260917-v1';
const HMOA_CUTOFF = '2026-09-17 00:00:00';
const HMOA_CORE8 = [1=>true,2=>true,4=>true,8=>true,9=>true,10=>true,12=>true,16=>true];

function hmoa_require(bool $ok, string $why): void { if (!$ok) throw new RuntimeException($why); }
function hmoa_json(array $v): string { return json_encode($v, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n"; }
function hmoa_write(string $path, array $v): string {
    $raw=hmoa_json($v); $f=@fopen($path,'x+b'); hmoa_require(is_resource($f),'exclusive_output');
    try { hmoa_require(fwrite($f,$raw)===strlen($raw) && fflush($f),'durable_write'); if(function_exists('fsync')) hmoa_require(fsync($f),'durable_sync'); rewind($f); hmoa_require(stream_get_contents($f)===$raw,'durable_readback'); }
    finally { fclose($f); }
    return hash('sha256',$raw);
}
function hmoa_q(PDO $db,string $sql,array $args=[]): array { hmoa_require(preg_match('/^SELECT\b/i',trim($sql))===1,'select_only'); $q=$db->prepare($sql); $q->execute(array_values($args)); $r=$q->fetchAll(PDO::FETCH_ASSOC); hmoa_require(count($r)<=200000,'read_limit'); return $r; }
function hmoa_protected($v,string $key='',int $depth=0): bool {
    if($depth>24) return true;
    if(is_array($v)){ foreach($v as $k=>$x) if(hmoa_protected($x,(string)$k,$depth+1)) return true; return false; }
    if(!preg_match('/manual|exclude|exclusion|conflict|reject|review/i',$key)) return false;
    if(is_bool($v)) return $v; if(is_numeric($v)) return (float)$v!==0.0;
    return is_string($v) && !in_array(strtolower(trim($v)),['','false','none','no','null','0'],true);
}
function hmoa_point(array $r): ?array {
    foreach([['latitude','longitude'],['lat','lng'],['lat','lon'],['hotelLatitude','hotelLongitude'],['api_latitude','api_longitude']] as [$a,$b]){
        if(!is_numeric($r[$a]??null)||!is_numeric($r[$b]??null)) continue;
        $lat=(float)$r[$a]; $lon=(float)$r[$b];
        if(is_finite($lat)&&is_finite($lon)&&abs($lat)<=90&&abs($lon)<=180&&($lat!=0.0||$lon!=0.0)) return [$lat,$lon];
    }
    return null;
}
function hmoa_km(array $a,array $b): float {
    [$x,$y,$u,$v]=array_map('deg2rad',[$a[0],$a[1],$b[0],$b[1]]);
    $h=sin(($u-$x)/2)**2+cos($x)*cos($u)*sin(($v-$y)/2)**2;
    return 12742.0176*asin(sqrt(min(1.0,max(0.0,$h))));
}
function hmoa_tokens(string $norm): array {
    $generic=['hotel'=>true,'hotels'=>true,'resort'=>true,'resorts'=>true,'spa'=>true,'otel'=>true,'отель'=>true,'отели'=>true,'резорт'=>true,'ресорт'=>true,'спа'=>true];
    return array_values(array_filter(preg_split('/\s+/u',trim($norm))?:[], static fn($x)=>$x!==''&&!isset($generic[$x])));
}
function hmoa_self_test(): void {
    hmoa_require(hmoa_protected(['manual'=>true]),'protected_true');
    hmoa_require(!hmoa_protected(['conflict'=>false]),'protected_false');
    hmoa_require(abs(hmoa_km([0,0],[0,0]))<0.001,'distance');
    hmoa_require(count(hmoa_tokens('alpha resort beta'))===2,'tokens');
}
function hmoa_main(): void {
    hmoa_require(PHP_SAPI==='cli' && getenv('MATCH_OPERATION_ID')===HMOA_OP,'operation_guard');
    $sha=(string)getenv('MATCH_SOURCE_SHA'); hmoa_require((bool)preg_match('/^[a-f0-9]{40}$/D',$sha),'source_sha');
    $dir=(string)getenv('HOME').'/.anytoour-match/operations/'.HMOA_OP;
    $reservation=json_decode((string)file_get_contents($dir.'/reservation.json'),true,32,JSON_THROW_ON_ERROR);
    hmoa_require(($reservation['operation_id']??'')===HMOA_OP && ($reservation['source_sha']??'')===$sha && ($reservation['state']??'')==='reserved_before_db_access','reservation_binding');
    $out=['schema'=>'hotel-match-organic-andromeda-observation-delta/1','operation_id'=>HMOA_OP,'source_sha'=>$sha,'state'=>'failed_no_replay','cutoff_utc'=>HMOA_CUTOFF,'no_replay'=>true,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'booking_calls'=>0,'lead_calls'=>0,'safe_to_write_now'=>false];
    $db=null; ob_start();
    try {
        $root=realpath(getcwd()); hmoa_require(is_string($root)&&basename($root)==='anytoour.ru','root_guard');
        $dbPath=is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php'; hmoa_require(is_file($dbPath)&&!is_link($dbPath),'db_path'); require_once $dbPath;
        hmoa_require(function_exists('v2_data_db')&&function_exists('v2_data_normalize_text'),'db_contract');
        $db=v2_data_db(); $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
        $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ'); $db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');

        $pending=hmoa_q($db,"SELECT supplier_namespace,external_hotel_id,evidence_json,catalog_sha256 FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='pending' AND local_hotel_id IS NULL ORDER BY external_hotel_id");
        $pendingBy=[]; foreach($pending as $r) $pendingBy[(string)$r['external_hotel_id']]=$r;
        $obs=hmoa_q($db,"SELECT * FROM andromeda_search_hotel_observations WHERE supplier_namespace='andromeda_catalog' AND observed_at_utc>=? ORDER BY observed_at_utc DESC,external_hotel_id",[HMOA_CUTOFF]);
        $latest=[]; foreach($obs as $r){$id=(string)($r['external_hotel_id']??''); if($id!==''&&isset($pendingBy[$id])&&!isset($latest[$id]))$latest[$id]=$r;}

        $hotels=[]; $nameIndex=[]; $localPoint=[];
        foreach(hmoa_q($db,"SELECT h.id,h.country_id,h.name,h.normalized_name,h.region_name,h.subregion_name,COALESCE(d.latitude,h.latitude) latitude,COALESCE(d.longitude,h.longitude) longitude FROM catalog_hotels h LEFT JOIN catalog_hotel_details d ON d.hotel_id=h.id WHERE h.is_active=1 AND h.country_id IN (1,2,4,8,9,10,12,16) ORDER BY h.id") as $r){
            $id=(int)$r['id']; $country=(int)$r['country_id']; $hotels[$id]=$r; $norm=trim((string)$r['normalized_name']); if($norm!=='')$nameIndex[$country][$norm][$id]=true; if(($p=hmoa_point($r))!==null)$localPoint[$id]=$p;
        }
        foreach(hmoa_q($db,"SELECT a.hotel_id,a.normalized_alias,h.country_id FROM hotel_aliases a JOIN catalog_hotels h ON h.id=a.hotel_id WHERE h.is_active=1 AND h.country_id IN (1,2,4,8,9,10,12,16) AND a.normalized_alias<>''") as $r){$id=(int)$r['hotel_id'];$country=(int)$r['country_id'];$norm=trim((string)$r['normalized_alias']);if($norm!=='')$nameIndex[$country][$norm][$id]=true;}
        $occupied=[]; foreach(hmoa_q($db,"SELECT external_hotel_id,local_hotel_id FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IS NOT NULL") as $r)$occupied[(int)$r['local_hotel_id']][]=(string)$r['external_hotel_id'];

        $rows=[]; $holds=['country_unknown'=>0,'name_missing'=>0,'name_too_generic'=>0,'no_unique_exact'=>0,'protected'=>0,'target_occupied'=>0,'coordinate_conflict'=>0,'duplicate_pending_target'=>0]; $exactHits=0;
        foreach($latest as $id=>$o){
            $country=(int)($o['country_id']??0); if(!isset(HMOA_CORE8[$country])){$holds['country_unknown']++;continue;}
            $name=trim((string)($o['hotel_name']??'')); if($name===''){$holds['name_missing']++;continue;}
            $norm=v2_data_normalize_text($name); $tokens=hmoa_tokens($norm); if(count($tokens)<2 && !(count($tokens)===1&&mb_strlen($tokens[0],'UTF-8')>=8)){$holds['name_too_generic']++;continue;}
            $targets=array_map('intval',array_keys($nameIndex[$country][$norm]??[])); sort($targets,SORT_NUMERIC); if(count($targets)!==1){$holds['no_unique_exact']++;continue;}
            $exactHits++; $local=$targets[0]; $ev=json_decode((string)($pendingBy[$id]['evidence_json']??'{}'),true,64,JSON_THROW_ON_ERROR); if(!is_array($ev))$ev=[];
            if(hmoa_protected($ev)){$holds['protected']++;continue;}
            if(!empty($occupied[$local]) && !in_array($id,$occupied[$local],true)){$holds['target_occupied']++;continue;}
            $sp=hmoa_point($o); $lp=$localPoint[$local]??null; $distance=null; if($sp&&$lp){$distance=hmoa_km($sp,$lp); if($distance>5.0){$holds['coordinate_conflict']++;continue;}}
            $rows[]=['external_hotel_id'=>$id,'country_id'=>$country,'observed_name'=>$name,'normalized_name'=>$norm,'observed_at_utc'=>(string)($o['observed_at_utc']??''),'target_local_id'=>$local,'target_name'=>(string)$hotels[$local]['name'],'target_region'=>(string)($hotels[$local]['region_name']??''),'target_subregion'=>(string)($hotels[$local]['subregion_name']??''),'distance_km'=>$distance===null?null:round($distance,3),'evidence_mode'=>'same_day_organic_observation_unique_exact_name_or_alias','safe_to_write_now'=>false];
        }
        $byTarget=[]; foreach($rows as $i=>$r)$byTarget[(int)$r['target_local_id']][]=$i; $drop=[]; foreach($byTarget as $idxs)if(count($idxs)>1){$holds['duplicate_pending_target']+=count($idxs);foreach($idxs as $i)$drop[$i]=true;} if($drop)$rows=array_values(array_filter($rows,static fn($r,$i)=>!isset($drop[$i]),ARRAY_FILTER_USE_BOTH));
        usort($rows,static fn($a,$b)=>strcmp($b['observed_at_utc'],$a['observed_at_utc'])?:strcmp($a['external_hotel_id'],$b['external_hotel_id']));
        $db->exec('ROLLBACK');
        $out += ['state'=>'completed_read_only','pending_total'=>count($pending),'same_day_observation_rows'=>count($obs),'same_day_observed_pending'=>count($latest),'unique_exact_hits_before_guards'=>$exactHits,'strict_clean_candidates'=>count($rows),'hold_counts'=>$holds,'candidates'=>$rows,'newest_observed_at_utc'=>$obs?(string)($obs[0]['observed_at_utc']??null):null,'read_at_utc'=>gmdate('c'),'transaction'=>'REPEATABLE READ / READ ONLY','evidence_is_mapping_authority'=>false];
    } catch(Throwable $e){ if($db instanceof PDO && $db->inTransaction())$db->rollBack(); $code=$e->getMessage(); $out['error_code']=preg_match('/^[a-zA-Z0-9_.:-]{2,120}$/D',$code)?$code:'sanitized_failure'; }
    while(ob_get_level())ob_end_clean();
    $hash=hmoa_write($dir.'/result.json',$out); hmoa_write($dir.'/receipt.json',['operation_id'=>HMOA_OP,'source_sha'=>$sha,'state'=>$out['state'],'result_sha256'=>$hash,'readback_verified'=>true,'no_replay'=>true,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0]);
    echo hmoa_json(['state'=>$out['state'],'pending_total'=>$out['pending_total']??null,'same_day_observed_pending'=>$out['same_day_observed_pending']??null,'strict_clean_candidates'=>$out['strict_clean_candidates']??null,'hold_counts'=>$out['hold_counts']??null,'result_sha256'=>$hash]); if($out['state']!=='completed_read_only')exit(2);
}

if(in_array('--self-test',$argv??[],true)){hmoa_self_test();echo "organic-andromeda-observation-delta-v1 self-test PASS\n";exit;}
hmoa_main();
