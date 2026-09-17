<?php
declare(strict_types=1);

const OP = 'hotel-match-tourvisor-passive-native-enrichment-current-1971-20260917-v1';
const CORE8 = [1,2,4,8,9,10,12,16];
const DIRECT = [25=>'operator_315',43=>'operator_342'];

function req(bool $ok,string $msg): void { if(!$ok) throw new RuntimeException($msg); }
function q(PDO $db,string $sql,array $args=[]): array {
    $s=$db->prepare($sql); $s->execute(array_values($args)); $r=$s->fetchAll(PDO::FETCH_ASSOC);
    req(count($r)<=50000,'row_budget'); return $r;
}
function write_json(string $path,array $value): string {
    $j=json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
    req(file_put_contents($path,$j,LOCK_EX)===strlen($j),'write_failed'); return hash('sha256',$j);
}
function protected_evidence(mixed $v,string $key='',int $depth=0): bool {
    if($depth>24) return true;
    if(is_array($v)){ foreach($v as $k=>$x) if(protected_evidence($x,(string)$k,$depth+1)) return true; return false; }
    if(!preg_match('/manual|exclude|exclusion|conflict|reject|review|protect/i',$key)) return false;
    if(is_bool($v)) return $v;
    if(is_numeric($v)) return (float)$v!==0.0;
    return is_string($v) && !in_array(strtolower(trim($v)),['','false','none','no','null','0','passed','clear','ok'],true);
}
function self_test(): void {
    req(DIRECT[25]==='operator_315' && DIRECT[43]==='operator_342','direct_contract');
    req(in_array(12,CORE8,true)&&!in_array(6,CORE8,true),'core8_contract');
    req(substr('102610157319',-9)==='610157319','biblio_suffix');
    req(protected_evidence(['manual'=>true]) && !protected_evidence(['guard'=>['conflict'=>'clear']]),'protection_contract');
    echo "hotel_match_tourvisor_passive_native_enrichment_current_v1 self-test: PASS\n";
}
if(in_array('--self-test',$argv??[],true)){self_test();exit(0);}

$sha=(string)getenv('MATCH_SOURCE_SHA');
req(PHP_SAPI==='cli','cli_only'); req((string)getenv('MATCH_OPERATION_ID')===OP,'operation_guard'); req((bool)preg_match('/^[a-f0-9]{40}$/D',$sha),'source_sha');
$dir=(string)getenv('HOME').'/.anytoour-match/operations/'.OP;
$res=json_decode((string)file_get_contents($dir.'/reservation.json'),true,32,JSON_THROW_ON_ERROR);
req(($res['operation_id']??'')===OP && ($res['source_sha']??'')===$sha && ($res['state']??'')==='reserved_before_db_access','reservation_guard');
$root=realpath(getcwd()); req(is_string($root)&&basename($root)==='anytoour.ru','root_guard');
require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
$db=v2_data_db(); $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$out=['operation_id'=>OP,'source_sha'=>$sha,'state'=>'failed_no_replay','no_replay'=>true,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'samo_andromeda_calls'=>0];
try {
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
    $cols=q($db,"SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tour_operator_identity_observations'");
    $colset=[]; foreach($cols as $c)$colset[(string)$c['COLUMN_NAME']]=1;
    foreach(['operator_id','hotel_id','operator_link','native_id_type','native_id_value','native_id_conflict','observation_count','last_seen_at'] as $c) req(isset($colset[$c]),'missing_column_'.$c);

    $total=(int)$db->query("SELECT COUNT(*) FROM tour_operator_identity_observations")->fetchColumn();
    $enriched=q($db,"SELECT i.operator_id,i.hotel_id,i.hotel_name,i.country_id,i.observation_count,i.last_seen_at,i.operator_link_host,i.operator_link_path,i.native_id_type,i.native_id_value,i.native_id_conflict,c.is_active,c.latitude,c.longitude FROM tour_operator_identity_observations i LEFT JOIN catalog_hotels c ON c.id=i.hotel_id WHERE i.operator_link IS NOT NULL OR i.native_id_value IS NOT NULL ORDER BY i.operator_id,i.hotel_id");
    $byOp=[]; foreach($enriched as $r){$op=(int)$r['operator_id']; if(!isset($byOp[$op]))$byOp[$op]=['enriched_rows'=>0,'with_link'=>0,'with_native'=>0,'native_conflict'=>0,'types'=>[]]; $byOp[$op]['enriched_rows']++; if($r['operator_link_host']!==null)$byOp[$op]['with_link']++; if($r['native_id_value']!==null){$byOp[$op]['with_native']++;$t=(string)($r['native_id_type']??'<none>');$byOp[$op]['types'][$t]=($byOp[$op]['types'][$t]??0)+1;} if((int)$r['native_id_conflict']!==0)$byOp[$op]['native_conflict']++;}

    $registry=[]; $acceptedOccupancy=[];
    foreach(q($db,"SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status,evidence_json FROM andromeda_hotel_identities WHERE supplier_namespace IN ('operator_115','operator_315','operator_342')") as $r){
        $ns=(string)$r['supplier_namespace'];$ext=(string)$r['external_hotel_id'];$registry[$ns][$ext]=$r;
        if(($r['decision_status']??'')==='accepted' && $r['local_hotel_id']!==null)$acceptedOccupancy[$ns][(int)$r['local_hotel_id']][]=$ext;
    }
    $pending=[];foreach(['andromeda_catalog','operator_115','operator_315','operator_342','operator_5'] as $ns){$s=$db->prepare("SELECT COUNT(*) FROM andromeda_hotel_identities WHERE supplier_namespace=? AND decision_status='pending' AND local_hotel_id IS NULL");$s->execute([$ns]);$pending[$ns]=(int)$s->fetchColumn();}
    $pending['total']=array_sum($pending);

    $direct=[];$directStats=['eligible_native_rows'=>0,'accepted_same'=>0,'accepted_different'=>0,'pending'=>0,'absent'=>0,'other_state'=>0,'held_conflict'=>0,'held_inactive_or_noncore'=>0,'held_target_occupied'=>0,'held_protected'=>0,'prepared'=>0];
    foreach($enriched as $r){
        $tv=(int)$r['operator_id']; if(!isset(DIRECT[$tv]))continue;
        if((string)$r['native_id_type']!=='hotels' || !preg_match('/^[1-9][0-9]*$/D',(string)$r['native_id_value']))continue;
        $directStats['eligible_native_rows']++;
        $ns=DIRECT[$tv];$ext=(string)$r['native_id_value'];$local=(int)$r['hotel_id'];$active=(int)($r['is_active']??0)===1;$core=in_array((int)$r['country_id'],CORE8,true);
        $entry=$registry[$ns][$ext]??null;$state='absent';$existingLocal=null;$protected=false;
        if(is_array($entry)){$state=(string)$entry['decision_status'];$existingLocal=$entry['local_hotel_id']===null?null:(int)$entry['local_hotel_id'];$ev=json_decode((string)$entry['evidence_json'],true);$protected=is_array($ev)&&protected_evidence($ev);}
        if($state==='accepted'&&$existingLocal===$local)$directStats['accepted_same']++;
        elseif($state==='accepted')$directStats['accepted_different']++;
        elseif($state==='pending')$directStats['pending']++;
        elseif($state==='absent')$directStats['absent']++;
        else $directStats['other_state']++;
        $occupants=array_values(array_filter($acceptedOccupancy[$ns][$local]??[],static fn(string $x):bool=>$x!==$ext));
        $reasons=[];
        if((int)$r['native_id_conflict']!==0){$reasons[]='native_observation_conflict';$directStats['held_conflict']++;}
        if(!$active||!$core){$reasons[]='inactive_or_noncore';$directStats['held_inactive_or_noncore']++;}
        if($occupants!==[]){$reasons[]='same_namespace_target_occupied';$directStats['held_target_occupied']++;}
        if($protected){$reasons[]='existing_identity_protected';$directStats['held_protected']++;}
        if($state==='accepted'&&$existingLocal!==$local)$reasons[]='accepted_different_target';
        if(!in_array($state,['absent','pending'],true))$reasons[]='not_new_or_pending';
        $prepared=$reasons===[];
        if($prepared)$directStats['prepared']++;
        $direct[]=['tourvisor_operator_id'=>$tv,'supplier_namespace'=>$ns,'native_hotel_id'=>$ext,'local_hotel_id'=>$local,'hotel_name'=>$r['hotel_name'],'country_id'=>(int)$r['country_id'],'observation_count'=>(int)$r['observation_count'],'last_seen_at'=>$r['last_seen_at'],'registry_state'=>$state,'registry_local_id'=>$existingLocal,'target_other_accepted_external_ids'=>$occupants,'prepared_direct_native'=>$prepared,'hold_reasons'=>$reasons];
    }
    usort($direct,static function(array $a,array $b):int{$x=((int)$b['prepared_direct_native']<=>(int)$a['prepared_direct_native']);if($x)return$x;$x=$b['observation_count']<=>$a['observation_count'];if($x)return$x;return $a['local_hotel_id']<=>$b['local_hotel_id'];});

    $biblio=[];$biblioStats=['f4_rows'=>0,'conflict_rows'=>0,'raw_registry_hits'=>0,'suffix_registry_hits'=>0,'suffix_accepted_same_local'=>0,'raw_accepted_same_local'=>0,'ambiguous_transform_rows'=>0];
    foreach($enriched as $r){
        if((int)$r['operator_id']!==18 || (string)$r['native_id_type']!=='f4' || !preg_match('/^[1-9][0-9]*$/D',(string)$r['native_id_value']))continue;
        $biblioStats['f4_rows']++; if((int)$r['native_id_conflict']!==0)$biblioStats['conflict_rows']++;
        $raw=(string)$r['native_id_value'];$suffix=strlen($raw)>9?substr($raw,-9):$raw;$local=(int)$r['hotel_id'];
        $rr=$registry['operator_115'][$raw]??null;$sr=$registry['operator_115'][$suffix]??null;
        if($rr!==null)$biblioStats['raw_registry_hits']++;if($sr!==null)$biblioStats['suffix_registry_hits']++;
        $rawSame=is_array($rr)&&($rr['decision_status']??'')==='accepted'&&(int)($rr['local_hotel_id']??0)===$local;
        $suffixSame=is_array($sr)&&($sr['decision_status']??'')==='accepted'&&(int)($sr['local_hotel_id']??0)===$local;
        if($rawSame)$biblioStats['raw_accepted_same_local']++;if($suffixSame)$biblioStats['suffix_accepted_same_local']++;if($rawSame&&$suffixSame&&$raw!==$suffix)$biblioStats['ambiguous_transform_rows']++;
        $biblio[]=['local_hotel_id'=>$local,'hotel_name'=>$r['hotel_name'],'country_id'=>(int)$r['country_id'],'observation_count'=>(int)$r['observation_count'],'last_seen_at'=>$r['last_seen_at'],'f4_digits'=>strlen($raw),'raw_registry_state'=>$rr['decision_status']??'absent','raw_registry_local'=>$rr['local_hotel_id']??null,'suffix_candidate'=>$suffix,'suffix_registry_state'=>$sr['decision_status']??'absent','suffix_registry_local'=>$sr['local_hotel_id']??null,'raw_accepted_same_local'=>$rawSame,'suffix_accepted_same_local'=>$suffixSame,'native_id_conflict'=>(int)$r['native_id_conflict']];
    }
    usort($biblio,static fn(array $a,array $b):int=>$b['observation_count']<=>$a['observation_count']);

    $db->exec('ROLLBACK');
    $out['state']='completed_read_only';$out['passive_rows_total']=$total;$out['enriched_rows']=count($enriched);$out['by_operator']=$byOp;$out['direct_stats']=$directStats;$out['direct_rows']=$direct;$out['biblio_stats']=$biblioStats;$out['biblio_rows']=$biblio;$out['current_pending']=$pending;$out['transaction']='REPEATABLE READ / READ ONLY';$out['read_at_utc']=gmdate('c');
} catch(Throwable $e){if($db->inTransaction())$db->rollBack();$out['error_code']=preg_match('/^[a-z0-9_]{2,120}$/i',$e->getMessage())?$e->getMessage():'sanitized_failure';}
$hash=write_json($dir.'/result.json',$out);write_json($dir.'/receipt.json',['operation_id'=>OP,'source_sha'=>$sha,'state'=>$out['state'],'result_sha256'=>$hash,'readback_verified'=>true,'no_replay'=>true,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'samo_andromeda_calls'=>0]);
echo json_encode($out+['result_sha256'=>$hash],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";if($out['state']!=='completed_read_only')exit(2);
