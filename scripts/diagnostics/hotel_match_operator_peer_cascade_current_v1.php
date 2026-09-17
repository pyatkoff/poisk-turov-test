<?php
declare(strict_types=1);

const MATCH_PEER_OP = 'hotel-match-operator-peer-cascade-current-1971-20260917-v1';

function mp_req(bool $ok, string $why): void { if (!$ok) throw new RuntimeException($why); }
function mp_q(PDO $db, string $sql, array $args = []): array {
    $q = $db->prepare($sql); $q->execute(array_values($args));
    $rows = $q->fetchAll(PDO::FETCH_ASSOC); mp_req(count($rows) <= 100000, 'row_budget'); return $rows;
}
function mp_json(array $x): string { return json_encode($x, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR) . "\n"; }
function mp_write(string $path, array $x): string {
    $raw = mp_json($x); $f = @fopen($path, 'x+b'); mp_req(is_resource($f), 'exclusive_output');
    try { mp_req(fwrite($f, $raw) === strlen($raw) && fflush($f), 'output_write'); if (function_exists('fsync')) mp_req(fsync($f), 'output_sync'); rewind($f); mp_req(stream_get_contents($f) === $raw, 'output_readback'); }
    finally { fclose($f); }
    return hash('sha256', $raw);
}
function mp_norm(string $s): string {
    $s = mb_strtolower(trim($s), 'UTF-8'); $s = str_replace('ё', 'е', $s);
    $s = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $s) ?? $s;
    return trim(preg_replace('/\s+/u', ' ', $s) ?? $s);
}
function mp_forms(string $s): array {
    $n = mp_norm($s); if ($n === '') return [];
    $out = [$n => true];
    $tokens = preg_split('/\s+/u', $n, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $generic = ['hotel'=>true,'отель'=>true,'resort'=>true,'spa'=>true];
    $reduced = array_values(array_filter($tokens, static fn(string $t): bool => !isset($generic[$t])));
    if ($reduced && count($reduced) !== count($tokens)) $out[implode(' ', $reduced)] = true;
    return array_keys($out);
}
function mp_intersects(array $a, array $b): bool { $m = array_fill_keys($a, true); foreach ($b as $v) if (isset($m[$v])) return true; return false; }
function mp_protected($v, string $key = '', int $depth = 0): bool {
    if ($depth > 24) return true;
    if (is_array($v)) { foreach ($v as $k => $x) if (mp_protected($x, (string)$k, $depth + 1)) return true; return false; }
    if (!preg_match('/manual|exclude|exclusion|conflict|reject|review|protect/i', $key)) return false;
    if (is_bool($v)) return $v; if (is_numeric($v)) return (float)$v != 0.0;
    return is_string($v) && !in_array(strtolower(trim($v)), ['', 'false', 'none', 'no', 'null', '0'], true);
}
function mp_bridge(array $e): ?array {
    $b = $e['provider_bridges'][0] ?? null; if (!is_array($b)) return null;
    $aid = trim((string)($b['andromeda_hotel_id'] ?? ''));
    $country = filter_var($b['country_id'] ?? null, FILTER_VALIDATE_INT);
    $name = trim((string)($b['hotel_name'] ?? ''));
    if ($aid === '' || !ctype_digit($aid) || $country === false || (int)$country <= 0 || $name === '') return null;
    $lat = null; $lon = null;
    foreach (['latitude','lat'] as $k) if (isset($b[$k]) && is_numeric($b[$k])) { $lat = (float)$b[$k]; break; }
    foreach (['longitude','lon','lng'] as $k) if (isset($b[$k]) && is_numeric($b[$k])) { $lon = (float)$b[$k]; break; }
    return ['andromeda_id'=>$aid,'country_id'=>(int)$country,'hotel_name'=>$name,'latitude'=>$lat,'longitude'=>$lon];
}
function mp_km(float $lat1, float $lon1, float $lat2, float $lon2): float {
    $r=6371.0; $p1=deg2rad($lat1); $p2=deg2rad($lat2); $dp=deg2rad($lat2-$lat1); $dl=deg2rad($lon2-$lon1);
    $a=sin($dp/2)**2 + cos($p1)*cos($p2)*sin($dl/2)**2; return 2*$r*asin(min(1.0, sqrt($a)));
}
function mp_target_forms(array $hotel, array $aliases): array {
    $out=[]; foreach ([(string)$hotel['name'], (string)($hotel['normalized_name'] ?? '')] as $s) foreach (mp_forms($s) as $f) $out[$f]=true;
    foreach ($aliases as $a) foreach ([(string)($a['alias'] ?? ''),(string)($a['normalized_alias'] ?? '')] as $s) foreach (mp_forms($s) as $f) $out[$f]=true;
    return array_keys($out);
}
function mp_main(): void {
    mp_req(PHP_SAPI === 'cli' && getenv('MATCH_OPERATION_ID') === MATCH_PEER_OP, 'operation_guard');
    $sha=(string)getenv('MATCH_SOURCE_SHA'); mp_req((bool)preg_match('/^[a-f0-9]{40}$/D',$sha),'source_sha');
    $dir=(string)getenv('HOME').'/.anytoour-match/operations/'.MATCH_PEER_OP;
    $reservation=json_decode((string)file_get_contents($dir.'/reservation.json'),true,32,JSON_THROW_ON_ERROR);
    mp_req(($reservation['operation_id']??'')===MATCH_PEER_OP && ($reservation['source_sha']??'')===$sha && ($reservation['state']??'')==='reserved_before_db_access','reservation_guard');
    $out=['operation_id'=>MATCH_PEER_OP,'source_sha'=>$sha,'state'=>'failed_no_replay','no_replay'=>true,'safe_to_write_now'=>false,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'samo_andromeda_calls'=>0];
    $db=null; $phase='bootstrap'; ob_start();
    try {
        $root=realpath(getcwd()); mp_req(is_string($root)&&basename($root)==='anytoour.ru','root_guard');
        require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
        $db=v2_data_db(); $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION); $phase='current';
        $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ'); $db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
        $engine=mp_q($db,"SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='andromeda_hotel_identities'");
        mp_req(count($engine)===1 && strtoupper((string)$engine[0]['ENGINE'])==='INNODB','identity_table_contract');
        $pendingRaw=mp_q($db,"SELECT supplier_namespace,external_hotel_id,evidence_sha256,evidence_json FROM andromeda_hotel_identities WHERE supplier_namespace IN ('operator_315','operator_342') AND decision_status='pending' AND local_hotel_id IS NULL ORDER BY supplier_namespace,CAST(external_hotel_id AS UNSIGNED),external_hotel_id");
        $acceptedRaw=mp_q($db,"SELECT supplier_namespace,external_hotel_id,local_hotel_id,evidence_sha256,evidence_json FROM andromeda_hotel_identities WHERE supplier_namespace IN ('operator_315','operator_342') AND decision_status='accepted' AND local_hotel_id IS NOT NULL ORDER BY supplier_namespace,external_hotel_id");
        $pending=[]; $pendingMalformed=0;
        foreach($pendingRaw as $r){ $e=json_decode((string)$r['evidence_json'],true,64,JSON_THROW_ON_ERROR); mp_req(is_array($e),'pending_evidence_shape'); $b=mp_bridge($e); if($b===null){$pendingMalformed++;continue;} $pending[]=['namespace'=>(string)$r['supplier_namespace'],'external_id'=>(string)$r['external_hotel_id'],'evidence_sha256'=>(string)$r['evidence_sha256'],'evidence'=>$e,'bridge'=>$b,'protected'=>mp_protected($e)]; }
        $peersByAid=[]; $acceptedWithBridge=0;
        foreach($acceptedRaw as $r){ $e=json_decode((string)$r['evidence_json'],true,64,JSON_THROW_ON_ERROR); if(!is_array($e))continue; $b=mp_bridge($e); if($b===null)continue; $acceptedWithBridge++; $peersByAid[$b['andromeda_id']][]=['namespace'=>(string)$r['supplier_namespace'],'external_id'=>(string)$r['external_hotel_id'],'local_id'=>(int)$r['local_hotel_id'],'evidence_sha256'=>(string)$r['evidence_sha256'],'bridge'=>$b,'protected'=>mp_protected($e)]; }
        $localIds=[]; foreach($peersByAid as $list) foreach($list as $p) $localIds[$p['local_id']]=true;
        $hotels=[]; $aliases=[];
        if($localIds){ foreach(array_chunk(array_keys($localIds),500) as $chunk){$ph=implode(',',array_fill(0,count($chunk),'?')); foreach(mp_q($db,"SELECT id,country_id,name,normalized_name,latitude,longitude,is_active FROM catalog_hotels WHERE id IN ($ph)",$chunk) as $h)$hotels[(int)$h['id']]=$h; foreach(mp_q($db,"SELECT hotel_id,alias,normalized_alias,source FROM hotel_aliases WHERE hotel_id IN ($ph)",$chunk) as $a)$aliases[(int)$a['hotel_id']][]=$a; } }
        $occupied=[]; foreach($acceptedRaw as $r) $occupied[(string)$r['supplier_namespace']][(int)$r['local_hotel_id']][]=(string)$r['external_hotel_id'];
        $reasons=[]; $prepared=[]; $rawPrepared=[]; $peerAidHits=0;
        $hold=function(string $why) use (&$reasons):void{$reasons[$why]=($reasons[$why]??0)+1;};
        foreach($pending as $p){
            if($p['protected']){$hold('pending_protected');continue;}
            $other=$p['namespace']==='operator_315'?'operator_342':'operator_315';
            $allPeers=array_values(array_filter($peersByAid[$p['bridge']['andromeda_id']]??[],static fn(array $x):bool=>$x['namespace']===$other));
            if(!$allPeers){$hold('no_accepted_other_namespace_peer');continue;} $peerAidHits++;
            $distinct=[]; foreach($allPeers as $x)$distinct[$x['local_id']]=true; if(count($distinct)!==1){$hold('peer_target_ambiguous');continue;}
            $lid=(int)array_key_first($distinct); $hotel=$hotels[$lid]??null; if(!$hotel || (int)$hotel['is_active']!==1){$hold('target_missing_or_inactive');continue;}
            if((int)$hotel['country_id']!==$p['bridge']['country_id']){$hold('pending_country_mismatch');continue;}
            $tf=mp_target_forms($hotel,$aliases[$lid]??[]); if(!mp_intersects(mp_forms($p['bridge']['hotel_name']),$tf)){$hold('pending_name_not_exact_target');continue;}
            $cleanPeers=[]; foreach($allPeers as $peer){ if($peer['local_id']!==$lid||$peer['protected'])continue; if($peer['bridge']['country_id']!==(int)$hotel['country_id'])continue; if(!mp_intersects(mp_forms($peer['bridge']['hotel_name']),$tf))continue; $cleanPeers[]=$peer; }
            if(!$cleanPeers){$hold('no_clean_exact_peer');continue;}
            $busy=array_values(array_filter($occupied[$p['namespace']][$lid]??[],static fn(string $id):bool=>$id!==$p['external_id'])); if($busy){$hold('same_namespace_target_occupied');continue;}
            $distance=null; if($p['bridge']['latitude']!==null&&$p['bridge']['longitude']!==null&&$hotel['latitude']!==null&&$hotel['longitude']!==null){$distance=mp_km((float)$p['bridge']['latitude'],(float)$p['bridge']['longitude'],(float)$hotel['latitude'],(float)$hotel['longitude']); if($distance>5.0){$hold('coordinate_over_5km');continue;}}
            usort($cleanPeers,static fn(array $a,array $b):int=>strcmp($a['external_id'],$b['external_id'])); $peer=$cleanPeers[0];
            $rawPrepared[]=['supplier_namespace'=>$p['namespace'],'external_hotel_id'=>$p['external_id'],'source_evidence_sha256'=>$p['evidence_sha256'],'andromeda_hotel_id'=>$p['bridge']['andromeda_id'],'source_name'=>$p['bridge']['hotel_name'],'country_id'=>$p['bridge']['country_id'],'local_id'=>$lid,'local_name'=>(string)$hotel['name'],'distance_km'=>$distance===null?null:round($distance,3),'peer_namespace'=>$peer['namespace'],'peer_external_hotel_id'=>$peer['external_id'],'peer_evidence_sha256'=>$peer['evidence_sha256'],'peer_name'=>$peer['bridge']['hotel_name']];
        }
        $targetUse=[]; foreach($rawPrepared as $x)$targetUse[$x['supplier_namespace'].':'.$x['local_id']][]=$x['external_hotel_id'];
        foreach($rawPrepared as $x){$key=$x['supplier_namespace'].':'.$x['local_id']; if(count(array_unique($targetUse[$key]))!==1){$hold('prepared_duplicate_target');continue;} $prepared[]=$x;}
        ksort($reasons); $db->exec('ROLLBACK');
        $out['state']='completed_read_only'; $out['pending_total']=count($pendingRaw); $out['pending_parseable']=count($pending); $out['pending_malformed']=$pendingMalformed; $out['accepted_operator_rows']=count($acceptedRaw); $out['accepted_with_exact_bridge']=$acceptedWithBridge; $out['pending_with_accepted_other_namespace_bridge']=$peerAidHits; $out['strict_prepared_count']=count($prepared); $out['reason_counts']=$reasons; $out['prepared']=$prepared; $out['read_at_utc']=gmdate('c'); $out['transaction']='REPEATABLE READ / READ ONLY';
    } catch(Throwable $e){ if($db instanceof PDO && $db->inTransaction())$db->rollBack(); $out['error_phase']=$phase; $out['error_code']=preg_match('/^[a-z_]{2,120}$/D',$e->getMessage())?$e->getMessage():'sanitized_failure'; }
    while(ob_get_level())ob_end_clean(); $dig=mp_write($dir.'/result.json',$out); mp_write($dir.'/receipt.json',['operation_id'=>MATCH_PEER_OP,'source_sha'=>$sha,'state'=>$out['state'],'result_sha256'=>$dig,'readback_verified'=>true,'no_replay'=>true,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'samo_andromeda_calls'=>0]);
    echo mp_json(['state'=>$out['state'],'pending_total'=>$out['pending_total']??null,'accepted_with_exact_bridge'=>$out['accepted_with_exact_bridge']??null,'peer_bridge_hits'=>$out['pending_with_accepted_other_namespace_bridge']??null,'strict_prepared_count'=>$out['strict_prepared_count']??null,'reason_counts'=>$out['reason_counts']??[],'prepared'=>$out['prepared']??[],'result_sha256'=>$dig]); if($out['state']!=='completed_read_only')exit(2);
}

if(in_array('--self-test',$argv??[],true)){
    mp_req(mp_intersects(mp_forms('Romance Hotel'),mp_forms('ROMANCE')),'generic_exact');
    mp_req(mp_protected(['manual'=>true]) && !mp_protected(['conflict'=>false]),'protection');
    mp_req(mp_bridge(['provider_bridges'=>[['andromeda_hotel_id'=>'123','country_id'=>4,'hotel_name'=>'X']]])['andromeda_id']==='123','bridge');
    echo "3 peer-cascade self-tests PASS\n"; exit(0);
}
mp_main();
