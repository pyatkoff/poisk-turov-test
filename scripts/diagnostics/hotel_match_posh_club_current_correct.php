<?php
declare(strict_types=1);

const OP = 'hotel-match-posh-club-current-correct-1971-20260912-v1';
const POLICY = 'owner_authorized_posh_primary_name_correction_20260912_v1';
const PLAN = [
    ['external_id'=>'2000042757','old_local_id'=>9373,'new_local_id'=>132075,'expected_evidence_sha256'=>'afe13fa576700fce4bdcbc1b5054cc4acccccce0f13ec8c72287272d4aab417c','base_tokens'=>['diamond','beach']],
    ['external_id'=>'2000051422','old_local_id'=>9374,'new_local_id'=>131024,'expected_evidence_sha256'=>'43f7c9bd859d8bd385f01fcbb33af8f19c7b8ff4adf024ff8f88d809256de0a0','base_tokens'=>['arabian','beach']],
    ['external_id'=>'2000073047','old_local_id'=>81173,'new_local_id'=>111423,'expected_evidence_sha256'=>'58a229ff984ac978b426df2437ab42f8a40e42f8315a2f1048aae70afb628157','base_tokens'=>['white','hills']],
];

function j($v): string { return json_encode($v, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR); }
function save_once(string $path, array $v): void {
    $raw=j($v)."\n"; $f=fopen($path,'x'); if(!$f) throw new RuntimeException('receipt_exists');
    try { if(!chmod($path,0600)||fwrite($f,$raw)!==strlen($raw)||!fflush($f)) throw new RuntimeException('receipt_write'); if(function_exists('fsync')&&!fsync($f)) throw new RuntimeException('receipt_sync'); }
    finally { fclose($f); }
    if(file_get_contents($path)!==$raw) throw new RuntimeException('receipt_readback');
}
function tokens(string $s): array {
    $s=mb_strtolower($s,'UTF-8'); preg_match_all('/[\p{L}\p{N}]+/u',$s,$m); return array_values(array_unique($m[0]));
}
function has_tokens(string $name, array $required): bool { $t=array_flip(tokens($name)); foreach($required as $r) if(!isset($t[$r])) return false; return true; }
function source_rows($node, string $externalId, int $depth=0): array {
    if(!is_array($node)||$depth>16) return []; $out=[];
    if((string)($node['id']??$node['hotelKey']??'')===$externalId && isset($node['name'])) $out[]=$node;
    foreach($node as $k=>$v) if(is_array($v)&&!in_array((string)$k,['candidates','targets','local','hotels','offers'],true)) $out=array_merge($out,source_rows($v,$externalId,$depth+1));
    $uniq=[]; foreach($out as $r) $uniq[hash('sha256',j($r))]=$r; return array_values($uniq);
}
function validate_identity(array $spec, array $row, array $old, array $new): array {
    if($row['decision_status']!=='accepted'||(int)$row['local_hotel_id']!==$spec['old_local_id']) throw new RuntimeException('current_state_changed');
    if(!hash_equals($spec['expected_evidence_sha256'],(string)$row['evidence_sha256'])) throw new RuntimeException('evidence_digest_changed');
    $raw=(string)$row['evidence_json']; if(!hash_equals((string)$row['evidence_sha256'],hash('sha256',$raw))) throw new RuntimeException('evidence_digest_invalid');
    $e=json_decode($raw,true,512,JSON_THROW_ON_ERROR); $src=source_rows($e,$spec['external_id']); if(count($src)!==1) throw new RuntimeException('source_primary_ambiguous');
    $primary=(string)$src[0]['name'];
    if(!has_tokens($primary,array_merge(['posh','club'],$spec['base_tokens']))) throw new RuntimeException('source_primary_guard');
    if(has_tokens((string)$old['name'],['posh','club'])) throw new RuntimeException('old_target_unexpected_posh');
    if(!has_tokens((string)$new['name'],array_merge(['posh','club'],$spec['base_tokens']))) throw new RuntimeException('new_target_primary_guard');
    if((int)$old['is_active']!==1||(int)$new['is_active']!==1) throw new RuntimeException('target_inactive');
    if(mb_strtolower((string)$old['country_name'],'UTF-8')!==mb_strtolower((string)$new['country_name'],'UTF-8')) throw new RuntimeException('country_mismatch');
    return ['primary_name'=>$primary,'source_row'=>$src[0]];
}

if(in_array('--self-test',$_SERVER['argv']??[],true)) {
    if(count(PLAN)!==3) exit(2); $seen=[]; foreach(PLAN as $p){ if(isset($seen[$p['external_id']])||$p['old_local_id']===$p['new_local_id']) exit(3); $seen[$p['external_id']]=1; }
    if(!has_tokens('Posh Club by Sunrise Diamond Beach Resort',['posh','club','diamond','beach'])) exit(4);
    if(has_tokens('Sunrise Diamond Beach Resort',['posh','club'])) exit(5);
    if(!has_tokens('POSH CLUB SUNRISE WHITE HILLS RESORT',['posh','club','white','hills'])) exit(6);
    echo "MATCH Posh correction self-test PASS rows=3\n"; exit(0);
}

error_reporting(0); ob_start(); $db=null; $dir=null; $commitAttempted=false; $committed=false; $written=[];
try {
    if(PHP_SAPI!=='cli') throw new RuntimeException('cli_only');
    $root=realpath(getcwd()); $home=realpath((string)getenv('HOME')); if(!$root||basename($root)!=='anytoour.ru'||!$home) throw new RuntimeException('root_guard');
    $base=$home.'/.anytoour-match/operations'; if(!is_dir($base)&&!mkdir($base,0700,true)) throw new RuntimeException('receipt_root');
    $dir=$base.'/'.OP; if(file_exists($dir)||!mkdir($dir,0700)){$dir=null;throw new RuntimeException('prior_operation_no_replay');}
    save_once($dir.'/reservation.json',['operation_id'=>OP,'status'=>'reserved_before_db_access','owner_authorized'=>true,'planned'=>count(PLAN),'no_replay'=>true]);
    require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
    $db=v2_data_db(); $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION); $db->exec('SET SESSION innodb_lock_wait_timeout=10'); $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ'); $db->beginTransaction();
    $upd=$db->prepare("UPDATE andromeda_hotel_identities SET local_hotel_id=?,decision_status='accepted',evidence_json=?,evidence_sha256=? WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=? AND decision_status='accepted' AND local_hotel_id=? AND evidence_sha256=?");
    foreach(PLAN as $spec){
        $q=$db->prepare("SELECT external_hotel_id,local_hotel_id,decision_status,evidence_json,evidence_sha256 FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=? FOR UPDATE"); $q->execute([$spec['external_id']]); $row=$q->fetch(PDO::FETCH_ASSOC); if(!$row) throw new RuntimeException('identity_missing');
        $q=$db->prepare('SELECT id,name,country_name,region_name,subregion_name,is_active FROM catalog_hotels WHERE id IN (?,?) ORDER BY id FOR UPDATE'); $q->execute([$spec['old_local_id'],$spec['new_local_id']]); $hs=$q->fetchAll(PDO::FETCH_ASSOC); $by=[]; foreach($hs as $h)$by[(int)$h['id']]=$h; if(!isset($by[$spec['old_local_id']],$by[$spec['new_local_id']])) throw new RuntimeException('target_missing');
        $verified=validate_identity($spec,$row,$by[$spec['old_local_id']],$by[$spec['new_local_id']]);
        $proof=['operation_id'=>OP,'policy'=>POLICY,'owner_authorized'=>true,'external_hotel_id'=>$spec['external_id'],'old_local_id'=>$spec['old_local_id'],'new_local_id'=>$spec['new_local_id'],'expected_old_evidence_sha256'=>$spec['expected_evidence_sha256'],'primary_name'=>$verified['primary_name'],'base_tokens'=>$spec['base_tokens'],'reason'=>'primary_name_contains_posh_club_but_old_target_does_not'];
        $prior=json_decode((string)$row['evidence_json'],true,512,JSON_THROW_ON_ERROR); $newEvidence=j(['prior_evidence'=>$prior,'correction_proof'=>$proof]); $newSha=hash('sha256',$newEvidence);
        $upd->execute([$spec['new_local_id'],$newEvidence,$newSha,$spec['external_id'],$spec['old_local_id'],$spec['expected_evidence_sha256']]); if($upd->rowCount()!==1) throw new RuntimeException('cas_update_failed');
        $written[]=$spec+['old_name'=>$by[$spec['old_local_id']]['name'],'new_name'=>$by[$spec['new_local_id']]['name'],'primary_name'=>$verified['primary_name'],'new_evidence_sha256'=>$newSha];
    }
    if(count($written)!==3) throw new RuntimeException('write_count_guard');
    save_once($dir.'/precommit-intent.json',['operation_id'=>OP,'owner_authorized'=>true,'written'=>$written,'no_replay'=>true]);
    $commitAttempted=true; $db->commit(); $committed=true;
    $db->exec('START TRANSACTION READ ONLY');
    foreach($written as $w){ $q=$db->prepare("SELECT local_hotel_id,decision_status,evidence_sha256 FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=?"); $q->execute([$w['external_id']]); $r=$q->fetch(PDO::FETCH_ASSOC); if(!$r||(int)$r['local_hotel_id']!==$w['new_local_id']||$r['decision_status']!=='accepted'||!hash_equals($w['new_evidence_sha256'],(string)$r['evidence_sha256'])) throw new RuntimeException('postcommit_readback_failed'); }
    $db->exec('ROLLBACK');
    $out=['status'=>'accepted','operation_id'=>OP,'owner_authorized'=>true,'planned'=>3,'written_count'=>3,'written'=>$written,'readback_verified'=>true,'database_writes'=>3,'mapping_writes'=>3,'supplier_calls'=>0,'tourvisor_calls'=>0,'crystal_bay_changed'=>false,'ordinary_sunrise_rows_changed'=>false,'no_replay'=>true]; save_once($dir.'/result.json',$out);
} catch(Throwable $e){
    try{if($db instanceof PDO&&$db->inTransaction())$db->rollBack();}catch(Throwable $ignored){}
    $out=['status'=>$commitAttempted?'commit_outcome_requires_readback':'rolled_back_or_not_started','operation_id'=>OP,'safe_message'=>preg_match('/^[a-z0-9_]+$/D',$e->getMessage())?$e->getMessage():'guard_failure','commit_attempted'=>$commitAttempted,'commit_confirmed'=>$committed,'database_writes'=>$commitAttempted?null:0,'mapping_writes'=>$commitAttempted?null:0,'supplier_calls'=>0,'tourvisor_calls'=>0,'no_replay'=>true]; if($dir&&is_dir($dir)&&!file_exists($dir.'/failure.json')){try{save_once($dir.'/failure.json',$out);}catch(Throwable $ignored){}}
}
while(ob_get_level())ob_end_clean(); echo 'MATCH_POSH_CURRENT_CORRECT:'.j($out).PHP_EOL; exit(($out['status']??'')==='accepted'?0:2);
