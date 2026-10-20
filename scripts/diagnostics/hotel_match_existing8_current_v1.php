<?php
declare(strict_types=1);
// Read-only continuation of the eight saved candidates in #1971 receipt5741064575.
const HM8_OPERATION = 'hotel-match-existing8-current-1971-20260919-v1';
const HM8_TARGETS = ['5464'=>1229,'150867'=>17507,'567'=>17490,'2000059721'=>35274,'2000040943'=>66152,'2000030138'=>81947,'2000071849'=>102661,'4176'=>127898];
const HM8_ANEX = [8638,8790,28846,32642,28837,28930,20824,32969,9226,40677];
function hm8_require(bool $ok, string $reason): void { if (!$ok) throw new RuntimeException($reason); }
function hm8_json(array $v): string { return json_encode($v, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n"; }
function hm8_save(string $path, array $v): string {
    $raw=hm8_json($v); $f=fopen($path,'xb'); hm8_require(is_resource($f),'exclusive_output');
    try { hm8_require(fwrite($f,$raw)===strlen($raw)&&fflush($f),'output_write'); if(function_exists('fsync')) hm8_require(fsync($f),'output_sync'); } finally { fclose($f); }
    hm8_require(hash_file('sha256',$path)===hash('sha256',$raw),'output_readback'); return hash('sha256',$raw);
}
function hm8_rows(PDO $db, string $sql, array $params=[], int $cap=50000): array {
    hm8_require(preg_match('/^SELECT\s/i',$sql)===1,'select_only');
    $s=$db->prepare($sql); $s->execute($params); $rows=$s->fetchAll(PDO::FETCH_ASSOC); hm8_require(count($rows)<=$cap,'read_cap'); return $rows;
}
function hm8_status(?array $row, string $source, int $target, ?int $effective): string {
    if($row===null) return 'source_absent';
    hm8_require(($row['supplier_namespace']??null)==='andromeda_catalog'&&(string)($row['external_hotel_id']??'')===$source,'exact_namespace');
    $status=$row['decision_status']??''; $local=$row['local_hotel_id']??null;
    if($status==='accepted') {
        if($local===null||$effective===null||(int)$local!==$effective) return 'accepted_resolver_mismatch';
        return $effective===$target?'already_effectively_mapped':'accepted_other_target_protected';
    }
    if($status==='pending'&&$local===null) return 'pending_unassigned_needs_evidence';
    return 'existing_state_protected';
}
// Evidence remains private on the server. Export hotel-level structural facts only.
function hm8_evidence(mixed $v, int $depth=0): mixed {
    if($depth>7) return '[depth-limited]';
    if(!is_array($v)) {
        if(is_string($v)&&(strlen($v)>600||preg_match('~https?://|@|Bearer\s~i',$v))) return '[redacted]';
        return $v;
    }
    $out=[]; $n=0;
    foreach($v as $k=>$value) {
        if(++$n>100) { $out['_truncated']=true; break; }
        if(is_string($k)&&preg_match('/auth|token|secret|password|passenger|tourist|email|phone|decided_by|cookie|session|credential|url|link/i',$k)) { $out[$k]='[redacted]'; continue; }
        $out[$k]=hm8_evidence($value,$depth+1);
    }
    return $out;
}
if(in_array('--self-test',$argv??[],true)) {
    $r=['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>'5464','decision_status'=>'accepted','local_hotel_id'=>1229]; $n=0;
    $check=function(bool $ok)use(&$n):void{hm8_require($ok,'selftest_'.(++$n));};
    $check(count(HM8_TARGETS)===8&&count(array_unique(HM8_TARGETS))===8);
    $check(hm8_status(null,'5464',1229,null)==='source_absent');
    $check(hm8_status($r,'5464',1229,1229)==='already_effectively_mapped');
    $x=$r;$x['local_hotel_id']=999;$check(hm8_status($x,'5464',1229,999)==='accepted_other_target_protected');
    $check(hm8_status($r,'5464',1229,null)==='accepted_resolver_mismatch');
    $x=$r;$x['decision_status']='pending';$x['local_hotel_id']=null;$check(hm8_status($x,'5464',1229,null)==='pending_unassigned_needs_evidence');
    foreach(['conflict','rejected','manual','unknown',''] as $status) {$x['decision_status']=$status;$check(hm8_status($x,'5464',1229,null)==='existing_state_protected');}
    $x=$r;$x['decision_status']='pending';$check(hm8_status($x,'5464',1229,null)==='existing_state_protected');
    foreach(['operator_315','operator_5',''] as $ns) {$x=$r;$x['supplier_namespace']=$ns;$failed=false;try{hm8_status($x,'5464',1229,null);}catch(RuntimeException $e){$failed=true;}$check($failed);}
    $failed=false;try{hm8_status($r,'567',1229,null);}catch(RuntimeException $e){$failed=true;}$check($failed);
    $e=hm8_evidence(['hotel'=>'GREEN PEACE','token'=>'secret','note'=>'https://example.test/?secret=x','nested'=>['email'=>'x@y.test','stars'=>3]]);
    $check($e['hotel']==='GREEN PEACE'&&$e['token']==='[redacted]'&&$e['note']==='[redacted]'&&$e['nested']['email']==='[redacted]'&&$e['nested']['stars']===3);
    echo 'HM8_SELFTEST_OK checks='.$n."\n";exit(0);
}
hm8_require(PHP_SAPI==='cli','cli_only');
$dir=(string)getenv('MATCH_OPERATION_DIR');$sha=(string)getenv('MATCH_SOURCE_SHA');$db=null;
hm8_require(is_dir($dir)&&!is_link($dir)&&basename($dir)===HM8_OPERATION&&preg_match('/^[a-f0-9]{40}$/D',$sha)===1,'operation_path');
$base=['operation'=>HM8_OPERATION,'source_sha'=>$sha,'provider_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'accepted_delta'=>0,'triple_delta'=>0,'no_replay'=>true];
try {
    $reservation=json_decode((string)file_get_contents($dir.'/reservation.json'),true,32,JSON_THROW_ON_ERROR);
    hm8_require(($reservation['operation']??null)===HM8_OPERATION&&($reservation['source_sha']??null)===$sha,'reservation');
    hm8_require(($reservation['reader_sha256']??'')===hash_file('sha256',__FILE__),'reader_hash');
    foreach(['andromeda-hotel-resolver.php','anex-search-mapping-registry.php'] as $name) {
        hm8_require(isset($reservation['dependencies'][$name])&&hash_file('sha256',$dir.'/'.$name)===$reservation['dependencies'][$name],'dependency_hash'); require_once $dir.'/'.$name;
    }
    $root=realpath((string)getenv('ANYTOOUR_ROOT'));hm8_require(is_string($root)&&basename($root)==='anytoour.ru','root');
    $bootstrap=$root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');hm8_require(is_file($bootstrap),'db_bootstrap');require_once $bootstrap;
    $db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
    $all=hm8_rows($db,'SELECT i.supplier_namespace,i.external_hotel_id,i.local_hotel_id,i.decision_status,h.id AS existing_catalog_hotel_id FROM andromeda_hotel_identities i LEFT JOIN catalog_hotels h ON h.id=i.local_hotel_id ORDER BY i.supplier_namespace,i.external_hotel_id LIMIT 50001');
    $sources=array_map('strval',array_keys(HM8_TARGETS));$targets=array_values(HM8_TARGETS);$placeholders=implode(',',array_fill(0,8,'?'));
    $raw=hm8_rows($db,"SELECT * FROM andromeda_hotel_identities WHERE (supplier_namespace='andromeda_catalog' AND external_hotel_id IN ($placeholders)) OR local_hotel_id IN ($placeholders) ORDER BY supplier_namespace,external_hotel_id LIMIT 1001",array_merge($sources,$targets),1000);
    $hotels=hm8_rows($db,'SELECT id,name,country_id,country_name,region_name,subregion_name,category,latitude,longitude,is_active FROM catalog_hotels WHERE country_id=4 ORDER BY id LIMIT 30001',[],30000);
    $aliases=hm8_rows($db,'SELECT a.hotel_id,a.alias,a.source FROM hotel_aliases a JOIN catalog_hotels h ON h.id=a.hotel_id WHERE h.country_id=4 ORDER BY a.hotel_id,a.alias,a.source LIMIT 100001',[],100000);
    $guards=[];$anexIds=HM8_ANEX;$ap=implode(',',array_fill(0,count($anexIds),'?'));
    foreach(['anex_hotel_search_mappings','anex_hotel_decisions','anex_review_pair_exclusions'] as $table) {
        $guards[$table]=hm8_rows($db,"SELECT * FROM $table WHERE anex_hotel_id IN ($ap) OR catalog_hotel_id IN ($placeholders) ORDER BY anex_hotel_id,catalog_hotel_id LIMIT 10001",array_merge($anexIds,$targets),10000);
    }
    $projection=[];$statusCounts=[];$index=[];$occupants=[];
    foreach($all as $r) {
        $key=$r['supplier_namespace'].':'.$r['external_hotel_id'];hm8_require(!isset($index[$key]),'duplicate_identity');$index[$key]=$r;
        $statusCounts[$r['decision_status']]=($statusCounts[$r['decision_status']]??0)+1;
        if($r['local_hotel_id']!==null)$occupants[(int)$r['local_hotel_id']][]=$r;
        if(in_array($r['decision_status'],['accepted','rejected'],true))$projection[]=['supplier_namespace'=>$r['supplier_namespace'],'external_hotel_id'=>$r['external_hotel_id'],'decision_status'=>$r['decision_status'],'catalog_hotel_id'=>$r['local_hotel_id'],'existing_catalog_hotel_id'=>$r['existing_catalog_hotel_id']];
    }
    $version=hash('sha256',hm8_json($projection));$resolver=AnyTourAndromedaHotelResolver::fromRows($projection,$version);
    $offers=[];foreach($sources as $s)$offers[]=['provider'=>'andromeda','selection_enabled'=>false,'local_hotel_id'=>null,'supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>$s];
    $page=$resolver->apply(['provider'=>'andromeda','selection_enabled'=>false,'offers'=>$offers]);$effective=[];
    foreach($page['offers'] as $o)$effective[$o['external_hotel_id']]=$o['local_hotel_id'];
    $anex=AnyTourAnexSearchMappingRegistry::fromPdo($db);$anexEffective=[];foreach($anexIds as $id)$anexEffective[(string)$id]=$anex->resolve('anex_online',(string)$id,'preview');
    $details=[];foreach($raw as $r){$key=$r['supplier_namespace'].':'.$r['external_hotel_id'];$r['row_sha256']=hash('sha256',hm8_json($r));$r['evidence_bytes_sha256']=hash('sha256',(string)$r['evidence_json']);$e=json_decode((string)$r['evidence_json'],true);$r['evidence']=is_array($e)?hm8_evidence($e):['unparseable'=>true];unset($r['evidence_json']);$details[$key]=$r;}
    $relevantIds=$targets;foreach($sources as $s){$row=$index['andromeda_catalog:'.$s]??null;if($row!==null&&$row['local_hotel_id']!==null)$relevantIds[]=(int)$row['local_hotel_id'];}
    $relevantIds=array_values(array_unique($relevantIds));$rp=implode(',',array_fill(0,count($relevantIds),'?'));
    $targetFacts=hm8_rows($db,"SELECT id,name,country_id,country_name,region_name,subregion_name,category,latitude,longitude,is_active FROM catalog_hotels WHERE id IN ($rp) ORDER BY id",$relevantIds,100);
    $db->rollBack();
    // Durable complete capture stays private; exported evidence is redacted above.
    $captureHash=hm8_save($dir.'/capture.json',$base+['rows'=>$raw,'guards'=>$guards,'identity_projection_sha256'=>hash('sha256',hm8_json($all))]);
    $rows=[];$counts=[];
    foreach(HM8_TARGETS as $s=>$target){$s=(string)$s;$key='andromeda_catalog:'.$s;$row=$index[$key]??null;$state=hm8_status($row,$s,$target,$effective[$s]);$counts[$state]=($counts[$state]??0)+1;$rows[]=['external_hotel_id'=>$s,'expected_tv_id'=>$target,'state'=>$state,'current_row'=>$details[$key]??null,'effective_local_id'=>$effective[$s],'target_occupants'=>$occupants[$target]??[],'safe_to_write_now'=>false];}
    $out=$base+['state'=>'completed_read_only','read_at_utc'=>gmdate('c'),'examined'=>8,'status_counts'=>$counts,'identity_count'=>count($all),'identity_status_counts'=>$statusCounts,'rows'=>$rows,'target_facts'=>$targetFacts,'country_hotels'=>$hotels,'country_aliases'=>$aliases,'anex_guards'=>hm8_evidence($guards),'anex_effective'=>$anexEffective,'capture_sha256'=>$captureHash,'resolver_version'=>$version,'dependencies'=>$reservation['dependencies']];
} catch(Throwable $e) {
    if($db instanceof PDO&&$db->inTransaction())$db->rollBack();
    $reason=$e instanceof RuntimeException&&preg_match('/^[a-z0-9_]{1,80}$/D',$e->getMessage())?$e->getMessage():'read_failed';
    $out=$base+['state'=>'failed_read_only','reason'=>$reason];
}
$resultHash=hm8_save($dir.'/result.json',$out);hm8_save($dir.'/receipt.json',$base+['state'=>$out['state'],'result_sha256'=>$resultHash,'readback_verified'=>true]);
echo hm8_json(['state'=>$out['state'],'result_sha256'=>$resultHash,'status_counts'=>$out['status_counts']??[],'reason'=>$out['reason']??null]);exit($out['state']==='completed_read_only'?0:2);
