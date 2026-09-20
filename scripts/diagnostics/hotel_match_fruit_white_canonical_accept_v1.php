<?php
declare(strict_types=1);
/** Max two canonical SAMO identities. Never writes an ANEX/room/offer registry. */
const C2_OP = 'hotel-match-fruit-white-canonical-accept-1971-20260920-v1';
const C2_INPUT = 'f47483a3859eebbd273ca826c8870a530d9745e03e274716a3f977673e2b387b';
function c2_need(bool $ok, string $reason): void { if (!$ok) throw new RuntimeException($reason); }
function c2_canonical($x) {
    if (!is_array($x)) return $x;
    if (!array_is_list($x)) ksort($x, SORT_STRING);
    foreach ($x as &$v) $v = c2_canonical($v);
    return $x;
}
function c2_json(array $x): string { return json_encode(c2_canonical($x), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n"; }
function c2_hash(array $x): string { return hash('sha256', c2_json($x)); }
function c2_put(string $path, array $x): string {
    $bytes=c2_json($x); $f=@fopen($path,'xb'); c2_need(is_resource($f),'immutable_output_exists');
    try { c2_need(fwrite($f,$bytes)===strlen($bytes)&&fflush($f),'output_write'); if(function_exists('fsync'))c2_need(fsync($f),'output_fsync'); }
    finally { fclose($f); }
    c2_need(file_get_contents($path)===$bytes,'output_readback'); return hash('sha256',$bytes);
}
function c2_rows(PDO $db,string $sql,array $params=[],int $cap=50000): array {
    $s=$db->prepare($sql); $s->execute(array_values($params)); $r=$s->fetchAll(PDO::FETCH_ASSOC);
    c2_need(count($r)<=$cap,'database_row_cap'); return $r;
}
function c2_pick(array $rows,string $key,$value): array { return array_values(array_filter($rows,fn($r)=>(string)($r[$key]??'')===(string)$value)); }
function c2_distance(array $a,array $b): float {
    foreach ([$a,$b] as $r) c2_need(is_numeric($r['latitude']??null)&&is_numeric($r['longitude']??null),'geo_missing');
    $x=deg2rad((float)$a['latitude']); $y=deg2rad((float)$b['latitude']);
    $q=sin(($y-$x)/2)**2+cos($x)*cos($y)*sin(deg2rad((float)$b['longitude']-(float)$a['longitude'])/2)**2;
    return 6371000*2*asin(sqrt(min(1,max(0,$q))));
}
function c2_validate(array $p): void {
    $specs=[49104=>['2000034436',28626,'41074','11077',['-1575330','41074'],5,'Кизимкази'],69340=>['2000063032',29125,'41080','46562',['-1574845','41080'],4,'Понгве']];
    $tv=$p['tv_hotel_id']??0; c2_need(isset($specs[$tv]),'pair_target');
    [$samo,$old,$anex,$it,$tokens,$stars,$town]=$specs[$tv];
    c2_need(($p['samo_hotel_id']??'')===$samo&&($p['old_accepted_anex_id']??0)===$old&&($p['new_anex_id_not_materialized']??'')===$anex,'pair_ids');
    c2_need(($p['safe_without_fresh_transaction']??null)===false&&($p['anex_signed_tokens_semantics']??'')==='unresolved_preserved_not_accepted_as_alias','pair_scope');
    $cat=$p['source_catalog']??[]; $target=$p['target_snapshot']??[];
    c2_need((string)($cat['id']??'')===$samo&&($cat['state']??'')==='Танзания'&&($cat['stateKey']??0)===179&&($cat['town']??'')===$town&&(int)($cat['star']??0)===$stars,'catalog_identity');
    c2_need(($target['id']??0)===$tv&&($target['country_id']??0)===41&&($target['country_name']??'')==='Танзания'&&($target['region_name']??'')==='Занзибар'&&($target['category']??0)===$stars&&($target['is_active']??0)===1,'target_identity');
    c2_need(count($p['full_operator_proofs']??[])===2,'operator_count');
    foreach ($p['full_operator_proofs'] as $i=>$proof) {
        $op=$i===0?13:43; $sop=$i===0?5:342; $native=$i===0?$anex:$it; $rawTokens=$i===0?$tokens:[$it];
        $edge=$proof['tv']['edge']??[]; $r=$proof['tv']['response']['data']??[]; $s=$proof['samo']['row']??[];
        c2_need(($proof['tv_operator_id']??0)===$op&&($proof['samo_operator_id']??0)===$sop&&($proof['native_operator_hotel_id']??'')===$native,'qualified_operator');
        c2_need(($r['hotel']['id']??0)===$tv&&($r['operator']['id']??0)===$op&&($r['id']??'')===($edge['tour_id']??null)&&($r['hotel']['name']??'')===$target['name'],'detail_binding');
        c2_need(($r['operatorLink']??'')===($edge['operator_link']??null)&&hash('sha256',$r['operatorLink'])===$edge['operator_link_sha256']&&($edge['raw_identity_tokens']??[])===$rawTokens,'full_raw_link');
        c2_need(($s['isOperatorHotelKey']??null)===0&&(string)($s['hotelKey']??'')===$samo&&(int)($s['operatorKey']??0)===$sop&&(string)($s['original']['hotelKey']??'')===$native,'explicit_canonical_namespace');
        c2_need(($s['town']??'')===$town&&(string)($s['samoTownKey']??'')===(string)$cat['townKey'],'concrete_town');
        c2_need(($s['checkIn']??'')==='01.10.2026'&&(string)($s['nights']??'')==='6'&&(string)($s['adult']??'')==='2'&&(string)($s['child']??'')==='0','saved_context');
    }
    $extra=$p['additional_saved_tv_edges']??null; c2_need(is_array($extra)&&count($extra)===($tv===69340?1:0),'full_saved_fingerprint');
    if($tv===69340)c2_need(($extra[0]['edge']['operator_id']??0)===25&&($extra[0]['edge']['raw_identity_tokens']??[])===['848600']&&$extra[0]['state']==='saved_additional_missing_operator_edge_not_accepted','funsun_not_autoaccepted');
}
function c2_competitors(array $peers,int $tv): array {
    $found=[];
    foreach($peers as $row) {
        $n=strtoupper($row['name']);
        $a=$tv===49104?'FRUIT':'WHITE'; $b=$tv===49104?'SPICE':'PARADISE';
        if(preg_match('/\b'.$a.'\b/u',$n)&&preg_match('/\b'.$b.'\b/u',$n))$found[]=(int)$row['id'];
    }
    sort($found,SORT_NUMERIC); return $found;
}
function c2_holds(array $p,array $current,array $expectedAuto): array {
    $tv=$p['tv_hotel_id'];$samo=$p['samo_hotel_id'];$old=$p['old_accepted_anex_id'];$new=$p['new_anex_id_not_materialized'];$holds=[];
    $targets=c2_pick($current['targets'],'id',$tv);
    if(count($targets)!==1||c2_hash($targets[0])!==c2_hash($p['target_snapshot']))$holds[]='target_current_drift';
    foreach($current['identities'] as $row) if((string)$row['local_hotel_id']===(string)$tv||($row['supplier_namespace']==='andromeda_catalog'&&(string)$row['external_hotel_id']===$samo)){$holds[]='source_or_target_occupied';break;}
    $qualified=[];
    foreach($p['full_operator_proofs'] as $proof)$qualified['operator_'.$proof['samo_operator_id'].':'.$proof['native_operator_hotel_id']]=true;
    foreach($p['additional_saved_tv_edges'] as $extra){$e=$extra['edge'];foreach($e['raw_identity_tokens'] as $token)$qualified[$e['target_supplier_namespace'].':'.$token]=true;}
    foreach($current['identities'] as $row)if(isset($qualified[$row['supplier_namespace'].':'.$row['external_hotel_id']])){$holds[]='qualified_native_identity_present';break;}
    foreach(['manual','exclusions'] as $type)foreach($current[$type] as $row)if(in_array((string)$row['anex_hotel_id'],[(string)$old,$new],true)||(int)($row['catalog_hotel_id']??0)===$tv){$holds[]=$type.'_present';break;}
    $oldNative=c2_pick($current['native'],'anex_hotel_id',$old);
    if(count($oldNative)!==1||c2_hash($oldNative[0])!==c2_hash($p['old_anex_catalog_snapshot']))$holds[]='old_native_current_drift';
    if(c2_pick($current['native'],'anex_hotel_id',$new)!==[])$holds[]='new_native_requires_separate_review';
    $maps=array_values(array_filter($current['maps'],fn($r)=>in_array((string)$r['anex_hotel_id'],[(string)$old,$new],true)||(int)$r['catalog_hotel_id']===$tv));
    if(count($maps)!==1||c2_hash($maps[0])!==c2_hash($p['old_anex_mapping_snapshot']))$holds[]='anex_mapping_current_drift';
    if(($current['resolved'][(string)$old]??null)!==$tv||($current['resolved'][$new]??null)!==null)$holds[]='anex_resolver_drift';
    if(c2_competitors($current['peers'],$tv)!==[$tv])$holds[]='pair_local_name_competition';
    if(c2_hash($current['auto'])!==c2_hash($expectedAuto))$holds[]='saved_auto_evidence_drift';
    if(count($oldNative)===1&&count($targets)===1) {
        if(($oldNative[0]['api_country']??'')!=='Танзания'||($oldNative[0]['api_region']??'')!=='Занзибар'||($oldNative[0]['api_town']??'')!==$p['source_catalog']['town']||c2_distance($oldNative[0],$targets[0])>500)$holds[]='concrete_geo_guard';
    }
    return array_values(array_unique($holds));
}
function c2_identity_hashes(array $rows): array {
    $hashes=[];foreach($rows as $r){$key=$r['supplier_namespace'].':'.$r['external_hotel_id'];c2_need(!isset($hashes[$key]),'duplicate_identity');$hashes[$key]=c2_hash($r);}ksort($hashes,SORT_STRING);return $hashes;
}
function c2_db_hashes(PDO $db): array {
    $s=$db->query('SELECT * FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id LIMIT 50001');$hashes=[];
    while($row=$s->fetch(PDO::FETCH_ASSOC)){$key=$row['supplier_namespace'].':'.$row['external_hotel_id'];c2_need(!isset($hashes[$key])&&count($hashes)<50000,'identity_hash_bound');$hashes[$key]=c2_hash($row);}
    $s->closeCursor();ksort($hashes,SORT_STRING);return $hashes;
}
function c2_current(PDO $db): array {
    $out=[];
    $out['identities']=c2_rows($db,'SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id LIMIT 50001 FOR UPDATE');
    $out['targets']=c2_rows($db,'SELECT id,name,country_id,country_name,region_name,subregion_name,category,latitude,longitude,is_active FROM catalog_hotels WHERE id IN (49104,69340) ORDER BY id FOR UPDATE',[],2);
    $out['peers']=c2_rows($db,"SELECT id,name,country_id,country_name,region_name,subregion_name,category,latitude,longitude,is_active FROM catalog_hotels WHERE country_name='Танзания' AND is_active=1 ORDER BY id LIMIT 2001 FOR UPDATE",[],2000);
    $out['native']=c2_rows($db,'SELECT * FROM anex_hotels WHERE anex_hotel_id IN (28626,29125,41074,41080) ORDER BY anex_hotel_id FOR UPDATE',[],4);
    $condition='anex_hotel_id IN (28626,29125,41074,41080) OR catalog_hotel_id IN (49104,69340)';
    $out['maps']=c2_rows($db,'SELECT * FROM anex_hotel_search_mappings WHERE '.$condition.' ORDER BY anex_hotel_id LIMIT 501 FOR UPDATE',[],500);
    $out['manual']=c2_rows($db,'SELECT * FROM anex_hotel_decisions WHERE '.$condition.' ORDER BY anex_hotel_id LIMIT 501 FOR UPDATE',[],500);
    $out['exclusions']=c2_rows($db,'SELECT * FROM anex_review_pair_exclusions WHERE '.$condition.' ORDER BY anex_hotel_id,catalog_hotel_id LIMIT 501 FOR UPDATE',[],500);
    $out['auto']=c2_rows($db,'SELECT * FROM anex_hotel_auto_matches WHERE anex_hotel_id IN (28626,29125,41074,41080) OR suggested_catalog_hotel_id IN (49104,69340) ORDER BY anex_hotel_id LIMIT 501 FOR UPDATE',[],500);
    $reg=AnyTourAnexSearchMappingRegistry::fromPdo($db);$out['resolved']=[];
    foreach([28626,29125,41074,41080] as $id)$out['resolved'][(string)$id]=$reg->resolve('anex_online',(string)$id,'preview');
    return $out;
}
function c2_resolve(PDO $db,string $external): ?int {
    $rows=c2_rows($db,"SELECT i.supplier_namespace,i.external_hotel_id,i.decision_status,i.local_hotel_id AS catalog_hotel_id,h.id AS existing_catalog_hotel_id FROM andromeda_hotel_identities i LEFT JOIN catalog_hotels h ON h.id=i.local_hotel_id WHERE i.decision_status IN ('accepted','rejected') ORDER BY i.supplier_namespace,i.external_hotel_id LIMIT 50001");
    $r=AnyTourAndromedaHotelResolver::fromRows($rows,c2_hash($rows));
    $p=$r->apply(['provider'=>'andromeda','selection_enabled'=>false,'offers'=>[['provider'=>'andromeda','supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>$external,'local_hotel_id'=>null,'selection_enabled'=>false]]]);
    return $p['offers'][0]['local_hotel_id'];
}
function c2_preserved(array $before,array $after): void { foreach($before as $k=>$v)c2_need(($after[$k]??null)===$v,'prior_identity_changed'); }

if(($argv[1]??'')==='--self-test') {
    $in=json_decode((string)file_get_contents($argv[2]),true,256,JSON_THROW_ON_ERROR);c2_need(hash_file('sha256',$argv[2])===C2_INPUT,'test_input_hash');
    $c=['identities'=>[],'targets'=>[],'peers'=>[],'native'=>[],'maps'=>[],'manual'=>[],'exclusions'=>[],'resolved'=>[],'auto'=>$in['auto_review_snapshot']];
    foreach($in['pairs'] as $p){c2_validate($p);$c['targets'][]=$p['target_snapshot'];$c['peers'][]=$p['target_snapshot'];$c['native'][]=$p['old_anex_catalog_snapshot'];$c['maps'][]=$p['old_anex_mapping_snapshot'];$c['resolved'][(string)$p['old_accepted_anex_id']]=$p['tv_hotel_id'];$c['resolved'][$p['new_anex_id_not_materialized']]=null;}
    foreach($in['pairs'] as $p)c2_need(c2_holds($p,$c,$in['auto_review_snapshot'])===[],'positive_guards');
    $p=$in['pairs'][0];$tests=0;
    foreach(['target','occupied','qualified','manual','exclusion','native','maps','resolver','peer','auto','geo'] as $mutation){$x=$c;
        if($mutation==='target')$x['targets'][0]['is_active']=0;
        if($mutation==='occupied')$x['identities'][]=['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>$p['samo_hotel_id'],'local_hotel_id'=>999,'decision_status'=>'rejected'];
        if($mutation==='qualified')$x['identities'][]=['supplier_namespace'=>'operator_342','external_hotel_id'=>'11077','local_hotel_id'=>999,'decision_status'=>'accepted'];
        if($mutation==='manual')$x['manual'][]=['anex_hotel_id'=>41074,'catalog_hotel_id'=>49104,'decision_status'=>'rejected'];
        if($mutation==='exclusion')$x['exclusions'][]=['anex_hotel_id'=>41074,'catalog_hotel_id'=>49104];
        if($mutation==='native')$x['native'][]=['anex_hotel_id'=>41074];
        if($mutation==='maps')$x['maps'][0]['enabled']=0;
        if($mutation==='resolver')$x['resolved']['28626']=999;
        if($mutation==='peer'){$r=$p['target_snapshot'];$r['id']=999;$x['peers'][]=$r;}
        if($mutation==='auto')$x['auto']=[];
        if($mutation==='geo')$x['native'][0]['latitude']='0';
        c2_need(c2_holds($p,$x,$in['auto_review_snapshot'])!==[],'negative_guard_admitted');$tests++;
    }
    foreach(['namespace','operator','tokens','target','samo','extra'] as $mutation){$x=$p;
        if($mutation==='namespace')$x['full_operator_proofs'][0]['samo']['row']['isOperatorHotelKey']=1;
        if($mutation==='operator')$x['full_operator_proofs'][0]['samo']['row']['operatorKey']=13;
        if($mutation==='tokens')$x['full_operator_proofs'][0]['tv']['edge']['raw_identity_tokens']=['41074'];
        if($mutation==='target')$x['target_snapshot']['name']='Wrong hotel';
        if($mutation==='samo')$x['full_operator_proofs'][1]['samo']['row']['hotelKey']=41074;
        if($mutation==='extra')$x['additional_saved_tv_edges']=[['ignored'=>true]];
        $failed=false;try{c2_validate($x);}catch(RuntimeException $e){$failed=true;}c2_need($failed,'negative_proof_admitted');$tests++;
    }
    $tmp=sys_get_temp_dir().'/canonical2-'.bin2hex(random_bytes(12)).'.json';try{c2_put($tmp,['one'=>1]);$failed=false;try{c2_put($tmp,['two'=>2]);}catch(RuntimeException $e){$failed=true;}c2_need($failed,'immutable_write_replayed');}finally{@unlink($tmp);}
    echo 'CANONICAL2_SELFTEST_OK '.($tests+3)."\n";exit;
}
c2_need(PHP_SAPI==='cli'&&($argv[1]??'')==='--execute','execute_disabled');
$dir=realpath((string)getenv('MATCH_OPERATION_DIR'));$root=realpath((string)getenv('ANYTOUR_ROOT'));
c2_need(is_string($dir)&&basename($dir)===C2_OP&&is_string($root)&&basename($root)==='anytoour.ru','runtime_paths');
$res=json_decode((string)file_get_contents($dir.'/reservation.json'),true,32,JSON_THROW_ON_ERROR);
c2_need(($res['operation']??'')===C2_OP&&($res['state']??'')==='reserved_before_db_write'&&($res['input_sha256']??'')===C2_INPUT&&($res['max_mapping_writes']??null)===2&&($res['provider_calls']??null)===0&&preg_match('/^[a-f0-9]{40}$/D',$res['source_sha']??'')===1,'reservation_binding');
$base=['operation'=>C2_OP,'source_sha'=>$res['source_sha'],'input_sha256'=>C2_INPUT,'provider_calls'=>0,'anex_mapping_writes'=>0,'no_replay'=>true];
$db=null;$attempted=false;$committed=false;$rolledBack=false;$writes=[];$skipped=[];
try {
    c2_put($dir.'/execution-reservation.json',$res);
    $ip=$dir.'/payload/input.json';c2_need(hash_file('sha256',$ip)===C2_INPUT,'input_hash');$input=json_decode((string)file_get_contents($ip),true,256,JSON_THROW_ON_ERROR);
    c2_need($input['operation']===C2_OP&&$input['max_writes']===2&&$input['mapping_namespace']==='andromeda_catalog'&&count($input['pairs'])===2,'input_scope');
    foreach($input['pairs'] as $p)c2_validate($p);
    foreach(['anex-search-mapping-registry.php'=>'cc135a95d2a6e0f73ce50be2141c9b8a26fddc58','andromeda-hotel-resolver.php'=>'d49d0eaf8d6abccefd84c0c3921edde94ef50a68'] as $file=>$blob){$path=$dir.'/payload/'.$file;$bytes=(string)file_get_contents($path);c2_need(sha1('blob '.strlen($bytes)."\0".$bytes)===$blob,'resolver_source');require_once $path;}
    require_once (is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php');$db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $db->exec('SET SESSION innodb_lock_wait_timeout=10');$db->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');$db->beginTransaction();
    $current=c2_current($db);$before=c2_db_hashes($db);$anexBefore=c2_hash($current['maps']);
    foreach($input['pairs'] as $p){$holds=c2_holds($p,$current,$input['auto_review_snapshot']);if($holds!==[]){$skipped[]=['samo_hotel_id'=>$p['samo_hotel_id'],'tv_hotel_id'=>$p['tv_hotel_id'],'holds'=>$holds];continue;}
        $evidence=$base+['method'=>'exact_intourist_native_explicit_canonical_namespace_with_anex_corroboration','review_result_sha256'=>$input['review_result_sha256'],'provenance'=>$input['provenance'],'pair'=>$p,'current_target'=>$p['target_snapshot'],'pair_local_competitors'=>c2_competitors($current['peers'],$p['tv_hotel_id']),'old_native_distance_m'=>c2_distance($p['old_anex_catalog_snapshot'],$p['target_snapshot']),'new_anex_mapping_remains_held'=>true,'current_anex_mapping_sha256'=>$anexBefore];
        $e=c2_json($evidence);$writes[]=['samo_hotel_id'=>$p['samo_hotel_id'],'tv_hotel_id'=>$p['tv_hotel_id'],'old_accepted_anex_id'=>$p['old_accepted_anex_id'],'new_anex_id_not_materialized'=>$p['new_anex_id_not_materialized'],'catalog_sha256'=>$input['catalog_sha256'],'evidence_sha256'=>hash('sha256',$e),'evidence_json'=>$e];
    }
    $captureCurrent=$current;unset($captureCurrent['identities']);$captureCurrent['identity_row_count']=count($current['identities']);c2_put($dir.'/capture.json',$base+['identity_hashes'=>$before,'current'=>$captureCurrent,'skipped'=>$skipped]);
    $planSha=c2_put($dir.'/plan.json',$base+['writes'=>$writes,'skipped'=>$skipped,'max_writes'=>2]);
    $expected=[];
    foreach($writes as $w){$s=$db->prepare("INSERT INTO andromeda_hotel_identities (supplier_namespace,external_hotel_id,local_hotel_id,decision_status,catalog_sha256,evidence_sha256,evidence_json) VALUES ('andromeda_catalog',?,?,'accepted',?,?,?)");$s->execute([$w['samo_hotel_id'],$w['tv_hotel_id'],$w['catalog_sha256'],$w['evidence_sha256'],$w['evidence_json']]);c2_need($s->rowCount()===1,'insert_count');$r=c2_rows($db,"SELECT * FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=?",[$w['samo_hotel_id']],1);c2_need(count($r)===1&&hash('sha256',$r[0]['evidence_json'])===$w['evidence_sha256'],'uncommitted_row');$expected[$w['samo_hotel_id']]=c2_hash($r[0]);}
    $afterInsert=c2_db_hashes($db);c2_preserved($before,$afterInsert);c2_need(count($afterInsert)===count($before)+count($writes),'append_delta');
    c2_put($dir.'/pre-commit.json',$base+['plan_sha256'=>$planSha,'uncommitted_writes'=>count($writes),'expected_row_digests'=>$expected,'prior_identities_preserved'=>count($before),'old_anex_mapping_sha256'=>$anexBefore]);
    if($writes!==[]){$attempted=true;c2_need($db->commit(),'commit_failed');$committed=true;}else{$db->rollBack();$rolledBack=true;}
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
    $readbackHashes=c2_db_hashes($db);c2_preserved($before,$readbackHashes);
    $reg=AnyTourAnexSearchMappingRegistry::fromPdo($db);$verified=[];
    foreach($writes as $w){$row=c2_rows($db,"SELECT * FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=?",[$w['samo_hotel_id']],1);c2_need(count($row)===1&&c2_hash($row[0])===$expected[$w['samo_hotel_id']]&&hash('sha256',$row[0]['evidence_json'])===$w['evidence_sha256'],'post_commit_row_digest');$resolved=c2_resolve($db,$w['samo_hotel_id']);c2_need($resolved===$w['tv_hotel_id'],'canonical_resolver_readback');c2_need($reg->resolve('anex_online',(string)$w['old_accepted_anex_id'],'preview')===$w['tv_hotel_id']&&$reg->resolve('anex_online',$w['new_anex_id_not_materialized'],'preview')===null,'anex_preservation');unset($w['evidence_json']);$verified[]=$w+['row_digest'=>$expected[$w['samo_hotel_id']],'canonical_resolver_target'=>$resolved,'new_anex_id_still_unresolved'=>true];}
    $mapsAfter=c2_rows($db,'SELECT * FROM anex_hotel_search_mappings WHERE anex_hotel_id IN (28626,29125,41074,41080) OR catalog_hotel_id IN (49104,69340) ORDER BY anex_hotel_id LIMIT 501',[],500);c2_need(c2_hash($mapsAfter)===$anexBefore,'anex_mapping_mutated');$clock=c2_rows($db,'SELECT UTC_TIMESTAMP AS utc',[],1)[0]['utc'];$db->exec('ROLLBACK');
    $count=count($verified);$out=$base+['state'=>$count?'committed_verified':'completed_no_write_current_drift','verified_at_utc'=>$clock,'mapping_writes'=>$count,'database_writes'=>$count,'accepted_delta'=>$count,'unique_samo_local_delta'=>$count,'triple_delta'=>$count,'written'=>$verified,'written_count'=>$count,'skipped'=>$skipped,'prior_identities_preserved'=>count($before),'old_anex_mapping_sha256'=>$anexBefore,'plan_sha256'=>$planSha];
} catch(Throwable $e) {
    try {if($db instanceof PDO&&$db->inTransaction()){$db->rollBack();$rolledBack=true;}}catch(Throwable $ignore){}
    $knownZero=!$attempted&&($writes===[]||$rolledBack);$out=$base+['state'=>$attempted?'commit_or_readback_unknown_no_replay':'blocked_before_commit_no_replay','reason'=>preg_match('/^[a-z0-9_]+$/D',$e->getMessage())?$e->getMessage():'database_or_runtime_error','commit_attempted'=>$attempted,'commit_returned_success'=>$committed,'rollback_returned_success'=>$rolledBack,'mapping_writes'=>$knownZero?0:null,'database_writes'=>$knownZero?0:null,'accepted_delta'=>null,'sqlstate'=>$e instanceof PDOException?(string)$e->getCode():null,'driver_code'=>$e instanceof PDOException?($e->errorInfo[1]??null):null];
}
$sha=c2_put($dir.'/result.json',$out);c2_put($dir.'/receipt.json',$base+['state'=>$out['state'],'result_sha256'=>$sha,'readback_verified'=>hash_file('sha256',$dir.'/result.json')===$sha]);
echo c2_json(['state'=>$out['state'],'written_count'=>$out['written_count']??null,'result_sha256'=>$sha,'provider_calls'=>0]);
exit(in_array($out['state'],['committed_verified','completed_no_write_current_drift'],true)?0:2);
