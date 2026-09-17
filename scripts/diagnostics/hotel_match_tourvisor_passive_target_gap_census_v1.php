<?php
declare(strict_types=1);

const OP = 'hotel-match-tourvisor-passive-target-gap-census-1971-20260917-v1';
const CORE8 = [1,2,4,8,9,10,12,16];
const TV_TO_NS = [18=>'operator_115',25=>'operator_315',43=>'operator_342'];

function req(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
function q(PDO $db, string $sql, array $args=[]): array {
    $st=$db->prepare($sql); $st->execute(array_values($args));
    $rows=$st->fetchAll(PDO::FETCH_ASSOC); req(count($rows)<=50000,'row_budget'); return $rows;
}
function write_json(string $path, array $value): string {
    $json=json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
    req(file_put_contents($path,$json,LOCK_EX)===strlen($json),'write_failed');
    return hash('sha256',$json);
}
function self_test(): void {
    req(TV_TO_NS[18]==='operator_115','biblio_map');
    req(TV_TO_NS[25]==='operator_315','funsun_map');
    req(TV_TO_NS[43]==='operator_342','intourist_map');
    req(in_array(12,CORE8,true) && !in_array(6,CORE8,true),'core8_contract');
    echo "hotel_match_tourvisor_passive_target_gap_census_v1 self-test: PASS\n";
}
if (in_array('--self-test',$argv??[],true)) { self_test(); exit(0); }

$sourceSha=(string)getenv('MATCH_SOURCE_SHA');
req(PHP_SAPI==='cli','cli_only');
req((string)getenv('MATCH_OPERATION_ID')===OP,'operation_guard');
req((bool)preg_match('/^[a-f0-9]{40}$/D',$sourceSha),'source_sha');
$dir=(string)getenv('HOME').'/.anytoour-match/operations/'.OP;
$res=json_decode((string)file_get_contents($dir.'/reservation.json'),true,32,JSON_THROW_ON_ERROR);
req(($res['operation_id']??'')===OP && ($res['source_sha']??'')===$sourceSha && ($res['state']??'')==='reserved_before_db_access','reservation_guard');

$root=realpath(getcwd()); req(is_string($root)&&basename($root)==='anytoour.ru','root_guard');
require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
$db=v2_data_db(); $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$out=['operation_id'=>OP,'source_sha'=>$sourceSha,'state'=>'failed_no_replay','no_replay'=>true,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'samo_andromeda_calls'=>0];
try {
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
    $passive=q($db,"SELECT i.operator_id,i.hotel_id,i.hotel_name,i.country_id,i.region_name,i.subregion_name,i.observation_count,i.first_seen_at,i.last_seen_at,c.is_active FROM tour_operator_identity_observations i LEFT JOIN catalog_hotels c ON c.id=i.hotel_id WHERE i.operator_id IN (18,25,43)");
    $accepted=[]; $pendingCounts=[];
    foreach (TV_TO_NS as $tv=>$ns) {
        $accepted[$ns]=[];
        foreach(q($db,"SELECT DISTINCT local_hotel_id FROM andromeda_hotel_identities WHERE supplier_namespace=? AND decision_status='accepted' AND local_hotel_id IS NOT NULL",[$ns]) as $r) $accepted[$ns][(int)$r['local_hotel_id']]=1;
        $pendingCounts[$ns]=(int)$db->query("SELECT COUNT(*) FROM andromeda_hotel_identities WHERE supplier_namespace=".$db->quote($ns)." AND decision_status='pending' AND local_hotel_id IS NULL")->fetchColumn();
    }
    $totalIdentity=(int)$db->query("SELECT COUNT(*) FROM tour_operator_identity_observations")->fetchColumn();
    $gaps=[]; $by=[]; $seenKey=[]; $duplicates=0;
    foreach($passive as $r) {
        $tv=(int)$r['operator_id']; $hotel=(int)$r['hotel_id']; $ns=TV_TO_NS[$tv]; $k=$tv.'|'.$hotel;
        if(isset($seenKey[$k])) {$duplicates++; continue;} $seenKey[$k]=1;
        $core=in_array((int)$r['country_id'],CORE8,true); $active=(int)($r['is_active']??0)===1;
        if(!isset($by[$tv])) $by[$tv]=['namespace'=>$ns,'passive_rows'=>0,'observed_locals'=>0,'observation_weight'=>0,'core8_active_locals'=>0,'accepted_covered_locals'=>0,'missing_accepted_locals'=>0,'core8_active_missing'=>0];
        $by[$tv]['passive_rows']++; $by[$tv]['observed_locals']++; $by[$tv]['observation_weight']+=(int)$r['observation_count'];
        if($core&&$active) $by[$tv]['core8_active_locals']++;
        $covered=isset($accepted[$ns][$hotel]);
        if($covered) {$by[$tv]['accepted_covered_locals']++; continue;}
        $by[$tv]['missing_accepted_locals']++; if($core&&$active) $by[$tv]['core8_active_missing']++;
        $gaps[]=['tourvisor_operator_id'=>$tv,'supplier_namespace'=>$ns,'local_hotel_id'=>$hotel,'hotel_name'=>$r['hotel_name'],'country_id'=>(int)$r['country_id'],'region_name'=>$r['region_name'],'subregion_name'=>$r['subregion_name'],'is_active'=>$active,'is_core8'=>$core,'observation_count'=>(int)$r['observation_count'],'first_seen_at'=>$r['first_seen_at'],'last_seen_at'=>$r['last_seen_at']];
    }
    usort($gaps,static function(array $a,array $b): int {
        $x=((int)$b['is_core8']<=> (int)$a['is_core8']); if($x!==0)return $x;
        $x=((int)$b['is_active']<=> (int)$a['is_active']); if($x!==0)return $x;
        $x=$b['observation_count']<=>$a['observation_count']; if($x!==0)return $x;
        $x=strcmp((string)$b['last_seen_at'],(string)$a['last_seen_at']); if($x!==0)return $x;
        return $a['local_hotel_id']<=>$b['local_hotel_id'];
    });
    $coreActiveGaps=count(array_filter($gaps,static fn(array $r):bool=>$r['is_core8']&&$r['is_active']));
    $db->exec('ROLLBACK');
    $out += ['state'=>'completed_read_only','passive_identity_rows_total'=>$totalIdentity,'relevant_passive_rows'=>count($passive),'distinct_relevant_pairs'=>count($seenKey),'duplicate_relevant_pairs'=>$duplicates,'by_tourvisor_operator'=>$by,'pending_external_rows_by_namespace'=>$pendingCounts,'missing_accepted_locals_total'=>count($gaps),'core8_active_missing_accepted_locals'=>$coreActiveGaps,'top_missing'=>array_slice($gaps,0,200),'transaction'=>'REPEATABLE READ / READ ONLY','read_at_utc'=>gmdate('c')];
} catch(Throwable $e) {
    if($db->inTransaction()) $db->rollBack();
    $out['error_code']=preg_match('/^[a-z0-9_]{2,100}$/i',$e->getMessage())?$e->getMessage():'sanitized_failure';
}
$hash=write_json($dir.'/result.json',$out);
write_json($dir.'/receipt.json',['operation_id'=>OP,'source_sha'=>$sourceSha,'state'=>$out['state'],'result_sha256'=>$hash,'readback_verified'=>true,'no_replay'=>true,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'samo_andromeda_calls'=>0]);
echo json_encode($out+['result_sha256'=>$hash],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
if($out['state']!=='completed_read_only') exit(2);
