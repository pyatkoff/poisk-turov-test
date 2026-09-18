<?php
declare(strict_types=1);
// MATCH-only: immutable direct links, final CURRENT evidence, then separately reserved append.
const R54_PLAN_OP = 'hotel-match-rolling54-final-plan-1971-20260919-v1';
const R54_APPLY_OP = 'hotel-match-rolling54-final-apply-1971-20260919-v1';
const R54_AUDIT_SHA = '8b19334f33fc1aa00c813138fbbdfa7650d008192e2c84dba419bd247701ecf2';
const R54_REGISTRY_BLOB = 'cc135a95d2a6e0f73ce50be2141c9b8a26fddc58';
const R54_POLICY = 'owner_exact_and_strong_20260908';
const R54_CLASS = 'strong_candidate';
function r54_json(array $x): string { return json_encode($x, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n"; }
function r54_save(string $path,array $x): string {
    $raw=r54_json($x);$f=@fopen($path,'xb');if(!$f)throw new RuntimeException('immutable_output_exists');
    try { if(fwrite($f,$raw)!==strlen($raw)||!fflush($f))throw new RuntimeException('durable_write');if(function_exists('fsync')&&!fsync($f))throw new RuntimeException('durable_sync'); } finally { fclose($f); }
    if(file_get_contents($path)!==$raw)throw new RuntimeException('durable_readback');return hash('sha256',$raw);
}
function r54_rows(PDO $db,string $sql,array $params=[]): array { $s=$db->prepare($sql);$s->execute(array_values($params));return $s->fetchAll(PDO::FETCH_ASSOC)?:[]; }
function r54_lower(string $s): string {
    if(function_exists('mb_strtolower'))return mb_strtolower($s,'UTF-8');
    return strtr(strtolower($s),array_combine(preg_split('//u','АБВГДЕЁЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЫЬЭЮЯ',-1,PREG_SPLIT_NO_EMPTY),preg_split('//u','абвгдеёжзийклмнопрстуфхцчшщъыьэюя',-1,PREG_SPLIT_NO_EMPTY)));
}
function r54_name(string $s): string {
    $s=r54_lower(trim($s));$s=preg_replace('/\s*\(?\s*ex\.\s+.*$/u','',$s)??$s;
    $s=preg_replace('/[^\p{L}\p{N}]+/u',' ',$s)??$s;
    $s=preg_replace('/(?<![\p{L}\p{N}])(?:hotel|hotels|отель)(?![\p{L}\p{N}])/u',' ',$s)??$s;
    return trim(preg_replace('/\s+/u',' ',$s)??$s);
}
function r54_country(string $s): string {
    $s=r54_lower(trim($s));foreach(['turkey'=>['турция','turkey','türkiye'],'egypt'=>['египет','egypt'],'vietnam'=>['вьетнам','vietnam','viet nam'],'uae'=>['оаэ','uae','united arab emirates','объединенные арабские эмираты'],'qatar'=>['катар','qatar']] as $key=>$names)if(in_array($s,$names,true))return $key;return $s;
}
function r54_dist(array $a,array $b): ?float {
    foreach(['latitude','longitude'] as $k)if(!isset($a[$k],$b[$k])||!is_numeric($a[$k])||!is_numeric($b[$k]))return null;
    $la=(float)$a['latitude'];$lb=(float)$b['latitude'];$oa=(float)$a['longitude'];$ob=(float)$b['longitude'];
    if(abs($la)>90||abs($lb)>90||abs($oa)>180||abs($ob)>180||($la==0&&$oa==0)||($lb==0&&$ob==0))return null;
    return 12742000*asin(min(1,sqrt(sin(deg2rad($lb-$la)/2)**2+cos(deg2rad($la))*cos(deg2rad($lb))*sin(deg2rad($ob-$oa)/2)**2)));
}
function r54_link(string $url): ?int {
    $p=parse_url($url);if(!is_array($p)||($p['scheme']??'')!=='https'||strtolower($p['host']??'')!=='agent.anextour.ru'||($p['path']??'')!=='/search/tour'||isset($p['user'])||isset($p['pass'])||isset($p['port'])||isset($p['fragment']))return null;
    $ids=[];foreach(explode('&',$p['query']??'') as $part){[$k,$v]=array_pad(explode('=',$part,2),2,'');$k=rawurldecode($k);$v=rawurldecode($v);if(preg_match('/token|password|auth|secret|session/i',$k))return null;if(strtoupper($k)==='HOTELLIST'){if(!preg_match('/^[1-9][0-9]{0,7}$/D',$v))return null;$ids[]=(int)$v;}}
    return count($ids)===1?$ids[0]:null;
}
function r54_input(string $path): array {
    if(hash_file('sha256',$path)!==R54_AUDIT_SHA)throw new RuntimeException('audit_digest');
    $a=json_decode((string)file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
    if(($a['state']??'')!=='completed_read_only'||($a['mapping_writes']??-1)!==0||count($a['dossiers']??[])!==62)throw new RuntimeException('audit_state');
    $pairs=array_values(array_filter($a['dossiers'],fn($p)=>$p['route']==='unoccupied_needs_identity_review'&&$p['holds']===[]));
    if(count($pairs)!==54||count(array_unique(array_column($pairs,'native_anex_id')))!==54||count(array_unique(array_column($pairs,'hotel_id')))!==54)throw new RuntimeException('audit_pair_set');
    foreach($pairs as $p){
        $id=(int)$p['native_anex_id'];if($p['operator_id']!==13||$p['raw_signed_tokens']!==[(string)$id]||$p['raw_hotellist_values']!==[(string)$id]||r54_link($p['operator_link'])!==$id||$p['safe_to_write_now']!==false)throw new RuntimeException('direct_anchor_invalid');
        if((int)$p['tv_hotel']['id']!==$p['hotel_id']||(int)$p['tv_hotel']['country']['id']!==$p['country_id']||$p['tv_hotel']['name']!==$p['hotel_name'])throw new RuntimeException('saved_tv_binding');
        foreach(['detail_response_sha256','results_response_sha256'] as $k)if(!preg_match('/^[0-9a-f]{64}$/D',$p[$k]??''))throw new RuntimeException('raw_provenance');
    }
    usort($pairs,fn($a,$b)=>$a['native_anex_id']<=>$b['native_anex_id']);return $pairs;
}
function r54_current(PDO $db,array $pairs,bool $lock): array {
    $a=array_column($pairs,'native_anex_id');$l=array_column($pairs,'hotel_id');$ph=implode(',',array_fill(0,count($a),'?'));$both=array_merge($a,$l);$tail=$lock?' FOR UPDATE':'';$s=[];
    $s['maps']=r54_rows($db,"SELECT anex_hotel_id,catalog_hotel_id,match_class,scope,approval_policy,source_row_digest,mapping_digest,enabled FROM anex_hotel_search_mappings WHERE anex_hotel_id IN ($ph) OR catalog_hotel_id IN ($ph) ORDER BY anex_hotel_id".$tail,$both);
    $s['decisions']=r54_rows($db,"SELECT anex_hotel_id,catalog_hotel_id,decision_status FROM anex_hotel_decisions WHERE anex_hotel_id IN ($ph) OR catalog_hotel_id IN ($ph) ORDER BY anex_hotel_id".$tail,$both);
    $s['exclusions']=r54_rows($db,"SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions WHERE anex_hotel_id IN ($ph) OR catalog_hotel_id IN ($ph) ORDER BY anex_hotel_id,catalog_hotel_id".$tail,$both);
    $s['locals']=r54_rows($db,"SELECT id,name,country_id,country_name,region_name,subregion_name,category,latitude,longitude,is_active FROM catalog_hotels WHERE id IN ($ph) ORDER BY id".$tail,$l);
    $s['native']=r54_rows($db,"SELECT anex_hotel_id,xml_name,xml_alternate_name,api_name,api_country,api_region,api_town,latitude,longitude,source_fingerprint FROM anex_hotels WHERE anex_hotel_id IN ($ph) ORDER BY anex_hotel_id".$tail,$a);
    $s['auto']=r54_rows($db,"SELECT anex_hotel_id,row_digest,source_fingerprint,original_status,automated_status,automated_reason,suggested_catalog_hotel_id,candidate_count FROM anex_hotel_auto_matches WHERE anex_hotel_id IN ($ph) ORDER BY anex_hotel_id".$tail,$a);
    $s['observations']=r54_rows($db,"SELECT anex_hotel_id,hotel_name,country_id,anex_country_id,last_catalog_hotel_id,first_seen_utc,last_seen_utc,search_count,last_source_sha FROM anex_search_hotel_observations WHERE anex_hotel_id IN ($ph) ORDER BY anex_hotel_id".$tail,$a);
    $s['candidates']=r54_rows($db,"SELECT anex_hotel_id,candidate_rank,catalog_hotel_id,score,name_similarity,distance_m,country_match,address_exact,SHA2(candidate_json,256) AS candidate_json_sha256 FROM anex_hotel_candidates WHERE anex_hotel_id IN ($ph) OR catalog_hotel_id IN ($ph) ORDER BY anex_hotel_id,candidate_rank".$tail,$both);
    // The count is descriptive only; it never supplies hotel identity authority.
    $s['andromeda']=r54_rows($db,"SELECT local_hotel_id FROM andromeda_hotel_identities WHERE decision_status='accepted' AND local_hotel_id IN ($ph) ORDER BY local_hotel_id".$tail,$l);
    return $s;
}
function r54_review(array $p,array $s): array {
    $aid=(int)$p['native_anex_id'];$lid=(int)$p['hotel_id'];$holds=[];$warnings=[];
    $one=function(string $table,string $key,int $id)use($s):?array{$r=array_values(array_filter($s[$table],fn($x)=>(int)$x[$key]===$id));if(count($r)>1)throw new RuntimeException('duplicate_identity_row');return $r[0]??null;};
    $h=$one('locals','id',$lid);$n=$one('native','anex_hotel_id',$aid);$au=$one('auto','anex_hotel_id',$aid);$obs=$one('observations','anex_hotel_id',$aid);
    $related=function(string $table)use($s,$aid,$lid):array{return array_values(array_filter($s[$table],fn($r)=>(int)$r['anex_hotel_id']===$aid||(int)($r['catalog_hotel_id']??0)===$lid));};
    $maps=$related('maps');$dec=$related('decisions');$ex=$related('exclusions');$cand=$related('candidates');
    if(!$h||(int)$h['is_active']!==1)$holds[]='local_missing_inactive';if(!$h||(int)$h['country_id']!==$p['country_id'])$holds[]='local_country_mismatch';
    if(!$h||r54_name($h['name'])!==r54_name($p['hotel_name']))$holds[]='local_name_drift';
    if($h&&in_array(r54_country($h['country_name']),['россия','абхазия','russia','abkhazia'],true))$holds[]='excluded_country';
    foreach($maps as $r){if((int)$r['anex_hotel_id']===$aid)$holds[]=(int)$r['catalog_hotel_id']===$lid?'existing_pair_preserved':'source_occupied_other';if((int)$r['catalog_hotel_id']===$lid&&(int)$r['anex_hotel_id']!==$aid)$holds[]='target_occupied_other';}
    if($dec)$holds[]='manual_decision_preserved';foreach($ex as $r)if((int)$r['anex_hotel_id']===$aid&&(int)$r['catalog_hotel_id']===$lid)$holds[]='pair_excluded';
    $tvDist=$h?r54_dist($p['tv_hotel'],$h):null;$nativeDist=$h&&$n?r54_dist($n,$h):null;
    if($tvDist!==null&&$tvDist>5000)$holds[]='tv_current_geo_over_5km';if($nativeDist!==null&&$nativeDist>5000)$holds[]='native_geo_over_5km';
    if($tvDist===null)$warnings[]='tv_current_coordinates_unavailable';if($nativeDist===null)$warnings[]='independent_native_coordinates_unavailable';
    if($n){
        if(trim((string)$n['api_country'])!==''&&$h&&r54_country($n['api_country'])!==r54_country($h['country_name']))$holds[]='native_country_mismatch';
        $names=array_values(array_filter(array_map('r54_name',[$n['api_name']??'',$n['xml_name']??'',$n['xml_alternate_name']??''])));
        if($names&&$h&&!in_array(r54_name($h['name']),$names,true))$holds[]='native_name_needs_review';
    }
    if($au){if(preg_match('/conflict|exclu|reject|manual|competing|ambig|mismatch|review/i',implode(' ',array_map('strval',$au))))$holds[]='protected_auto_review';if((int)($au['suggested_catalog_hotel_id']??0)>0&&(int)$au['suggested_catalog_hotel_id']!==$lid)$holds[]='auto_other_target';}
    if($obs){
        // country_id is the canonical TV country; anex_country_id is a separate namespace.
        if((int)$obs['country_id']!==$p['country_id'])$holds[]='observed_country_mismatch';
        if((int)($obs['last_catalog_hotel_id']??0)>0&&(int)$obs['last_catalog_hotel_id']!==$lid)$holds[]='observed_other_local';
        if(trim($obs['hotel_name'])!==''&&$h&&r54_name($obs['hotel_name'])!==r54_name($h['name']))$holds[]='observed_name_needs_review';
    } else {$warnings[]='saved_native_search_observation_absent';}
    foreach($cand as $r){
        if((int)$r['anex_hotel_id']!==$aid||(int)$r['catalog_hotel_id']!==$lid){$holds[]='competing_saved_candidate';continue;}
        if($r['country_match']!==null&&(int)$r['country_match']!==1)$holds[]='candidate_country_mismatch';
        if($r['distance_m']!==null&&is_numeric($r['distance_m'])&&(float)$r['distance_m']>5000)$holds[]='candidate_geo_over_5km';
    }
    $holds=array_values(array_unique($holds));$and=count(array_filter($s['andromeda'],fn($r)=>(int)$r['local_hotel_id']===$lid))>0;
    return ['native_anex_id'=>$aid,'hotel_id'=>$lid,'country_id'=>$p['country_id'],'hotel_name'=>$p['hotel_name'],'source_dossier_sha256'=>hash('sha256',r54_json($p)),'operator_link'=>$p['operator_link'],'raw_signed_tokens'=>$p['raw_signed_tokens'],'holds'=>$holds,'warnings'=>$warnings,'eligible_for_guarded_append'=>$holds===[],'current_local'=>$h,'native_catalog'=>$n,'auto_review'=>$au,'saved_observation'=>$obs,'saved_candidates'=>$cand,'maps'=>$maps,'manual'=>$dec,'exclusions'=>$ex,'tv_current_distance_m'=>$tvDist,'independent_native_distance_m'=>$nativeDist,'has_accepted_andromeda'=>$and];
}
function r54_selftest(?string $input=null): void {
    $count=0;$ok=function(bool $x)use(&$count){$count++;if(!$x)throw new RuntimeException('selftest_'.$count);};
    $ok(r54_name('HOTEL SU')===r54_name('SU HOTEL'));$ok(r54_name('FAMILY BEACH HOTEL')!==r54_name('FAMILY GARDEN HOTEL'));
    $ok(r54_name('ARMAS LABADA (EX. ASDEM LABADA BEACH)')==='armas labada');$ok(r54_country('ОАЭ')===r54_country('United Arab Emirates'));
    $ok(r54_link('https://agent.anextour.ru/search/tour?HOTELLIST=8231')===8231);
    foreach(['8231,-12','8231,22','8231&HOTELLIST=8231','8231&token=x'] as $v)$ok(r54_link('https://agent.anextour.ru/search/tour?HOTELLIST='.$v)===null);
    $ok(r54_link('https://evil.example/search/tour?HOTELLIST=8231')===null);
    $p=['native_anex_id'=>8231,'hotel_id'=>1001,'country_id'=>4,'hotel_name'=>'TEST BEACH HOTEL','operator_link'=>'https://agent.anextour.ru/search/tour?HOTELLIST=8231','raw_signed_tokens'=>['8231'],'tv_hotel'=>['latitude'=>36,'longitude'=>30]];
    $s=array_fill_keys(['maps','decisions','exclusions','native','auto','observations','candidates','andromeda'],[]);$s['locals']=[['id'=>1001,'name'=>'TEST BEACH HOTEL','country_id'=>4,'country_name'=>'Турция','is_active'=>1,'latitude'=>36,'longitude'=>30]];
    $ok(r54_review($p,$s)['eligible_for_guarded_append']);$ok(in_array('independent_native_coordinates_unavailable',r54_review($p,$s)['warnings'],true));
    $mutations=[['maps',['anex_hotel_id'=>8231,'catalog_hotel_id'=>999]],['maps',['anex_hotel_id'=>9000,'catalog_hotel_id'=>1001]],['maps',['anex_hotel_id'=>8231,'catalog_hotel_id'=>1001]],['decisions',['anex_hotel_id'=>8231,'catalog_hotel_id'=>1001,'decision_status'=>'rejected']],['exclusions',['anex_hotel_id'=>8231,'catalog_hotel_id'=>1001]],['auto',['anex_hotel_id'=>8231,'automated_reason'=>'competing_candidates']],['observations',['anex_hotel_id'=>8231,'hotel_name'=>'TEST BEACH HOTEL','country_id'=>9,'last_catalog_hotel_id'=>null]],['observations',['anex_hotel_id'=>8231,'hotel_name'=>'OTHER HOTEL','country_id'=>4,'last_catalog_hotel_id'=>null]],['candidates',['anex_hotel_id'=>8231,'catalog_hotel_id'=>999]],['candidates',['anex_hotel_id'=>8231,'catalog_hotel_id'=>1001,'country_match'=>1,'distance_m'=>6000]]];
    foreach($mutations as [$table,$row]){$z=$s;$z[$table]=[$row];$ok(!r54_review($p,$z)['eligible_for_guarded_append']);}
    $z=$s;$z['locals'][0]['latitude']=40;$ok(!r54_review($p,$z)['eligible_for_guarded_append']);$z=$s;$z['locals'][0]['is_active']=0;$ok(!r54_review($p,$z)['eligible_for_guarded_append']);
    $z=$s;$z['observations']=[['anex_hotel_id'=>8231,'hotel_name'=>'TEST BEACH HOTEL','country_id'=>4,'anex_country_id'=>3,'last_catalog_hotel_id'=>null]];$ok(r54_review($p,$z)['eligible_for_guarded_append']);
    if($input!==null){$ps=r54_input($input);$ok(count($ps)===54);foreach($ps as $row){$h=$row['current_local'];$z=$s;foreach(array_keys($z) as $t)$z[$t]=[];$z['locals']=[$h];$ok(r54_review($row,$z)['eligible_for_guarded_append']);}}
    echo 'ROLLING54_FINAL_SELFTEST_OK '.$count."\n";
}
if(($argv[1]??'')==='--self-test'){r54_selftest($argv[2]??null);exit;}
$mode=$argv[1]??'';if(PHP_SAPI!=='cli'||!in_array($mode,['--plan','--apply'],true))throw new RuntimeException('disabled');
$apply=$mode==='--apply';$op=$apply?R54_APPLY_OP:R54_PLAN_OP;$dir=realpath((string)getenv('MATCH_OPERATION_DIR'));$root=realpath((string)getenv('ANYTOUR_ROOT'));
if(!$dir||!$root||basename($root)!=='anytoour.ru')throw new RuntimeException('runtime_paths');
$res=json_decode((string)file_get_contents($dir.'/reservation.json'),true,512,JSON_THROW_ON_ERROR);
if(($res['operation']??'')!==$op||($res['state']??'')!==($apply?'reserved_before_db_write':'reserved_before_db')||!preg_match('/^[0-9a-f]{40}$/D',$res['source_sha']??''))throw new RuntimeException('reservation_invalid');
$base=['operation'=>$op,'source_sha'=>$res['source_sha'],'audit_sha256'=>R54_AUDIT_SHA,'supplier_calls'=>0,'no_replay'=>true];$db=null;$committed=false;$commitStarted=false;$written=[];$post=[];
try {
    r54_save($dir.'/execution-reservation.json',$res);$pairs=r54_input($dir.'/payload/audit.json');
    $rf=$dir.'/payload/anex-search-mapping-registry.php';$b=(string)file_get_contents($rf);if(sha1('blob '.strlen($b)."\0".$b)!==R54_REGISTRY_BLOB)throw new RuntimeException('registry_digest');require_once $rf;
    $approved=[];$planDigest=null;
    if($apply){
        $planPath=$dir.'/payload/plan.json';$planDigest=hash_file('sha256',$planPath);if(!preg_match('/^[0-9a-f]{64}$/D',$res['plan_sha256']??'')||$planDigest!==$res['plan_sha256'])throw new RuntimeException('plan_digest');
        $plan=json_decode((string)file_get_contents($planPath),true,512,JSON_THROW_ON_ERROR);
        if(($plan['operation']??'')!==R54_PLAN_OP||($plan['state']??'')!=='planned_read_only'||($plan['audit_sha256']??'')!==R54_AUDIT_SHA||($plan['mapping_writes']??-1)!==0||count($plan['dossiers']??[])!==54)throw new RuntimeException('plan_authority');
        foreach($plan['dossiers'] as $d)if($d['eligible_for_guarded_append']===true&&$d['holds']===[])$approved[(int)$d['native_anex_id']]=$d;
        if(!$approved||count($approved)!==$plan['eligible_count'])throw new RuntimeException('plan_empty_or_invalid');
        $pairs=array_values(array_filter($pairs,fn($p)=>isset($approved[$p['native_anex_id']])));
        foreach($pairs as $p){$d=$approved[$p['native_anex_id']];if($d['hotel_id']!==$p['hotel_id']||$d['source_dossier_sha256']!==hash('sha256',r54_json($p)))throw new RuntimeException('approved_pair_drift');}
        if(count($pairs)!==count($approved))throw new RuntimeException('approved_set');
    }
    require_once (is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php');$db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $db->exec('SET TRANSACTION ISOLATION LEVEL '.($apply?'SERIALIZABLE':'REPEATABLE READ'));
    if($apply)$db->beginTransaction();else $db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
    $snapshot=r54_current($db,$pairs,$apply);$dossiers=[];$holds=[];$eligible=[];
    foreach($pairs as $p){$d=r54_review($p,$snapshot);$dossiers[]=$d;if($d['eligible_for_guarded_append'])$eligible[]=$d;else $holds[]=$d;}
    $clock=r54_rows($db,'SELECT UTC_TIMESTAMP AS db_utc_timestamp')[0]['db_utc_timestamp'];
    if(!$apply){$db->exec('ROLLBACK');$result=$base+['state'=>'planned_read_only','captured_at_utc'=>$clock,'eligible_count'=>count($eligible),'held_count'=>count($holds),'potential_triples'=>count(array_filter($eligible,fn($d)=>$d['has_accepted_andromeda'])),'dossiers'=>$dossiers,'database_writes'=>0,'mapping_writes'=>0];}
    else {
        $mappingDigest=hash('sha256',r54_json(['operation'=>$op,'plan_sha256'=>$planDigest,'match_class'=>R54_CLASS,'approval_policy'=>R54_POLICY,'scope'=>'preview']));
        $insert=$db->prepare('INSERT INTO anex_hotel_search_mappings (anex_hotel_id,catalog_hotel_id,match_class,scope,approval_policy,source_row_digest,mapping_digest,enabled) VALUES (?,?,?,?,?,?,?,1)');
        r54_save($dir.'/precommit-plan.json',['operation'=>$op,'plan_sha256'=>$planDigest,'current_checked_at_utc'=>$clock,'eligible'=>$eligible,'held'=>$holds,'mapping_digest'=>$mappingDigest,'no_replay'=>true]);
        foreach($eligible as $d){$src=hash('sha256',r54_json(['audit_sha256'=>R54_AUDIT_SHA,'plan_sha256'=>$planDigest,'direct_dossier_sha256'=>$d['source_dossier_sha256'],'current_evidence'=>$d]));$insert->execute([$d['native_anex_id'],$d['hotel_id'],R54_CLASS,'preview',R54_POLICY,$src,$mappingDigest]);if($insert->rowCount()!==1)throw new RuntimeException('insert_count');$written[]=['anex_hotel_id'=>$d['native_anex_id'],'catalog_hotel_id'=>$d['hotel_id'],'source_row_digest'=>$src,'mapping_digest'=>$mappingDigest,'has_accepted_andromeda'=>$d['has_accepted_andromeda']];}
        r54_save($dir.'/precommit-written.json',['operation'=>$op,'written_uncommitted'=>$written,'plan_sha256'=>$planDigest,'no_replay'=>true]);
        $commitStarted=true;$db->commit();$committed=true;
        r54_save($dir.'/commit-returned.json',['operation'=>$op,'committed_count'=>count($written),'no_replay'=>true]);
        $reg=AnyTourAnexSearchMappingRegistry::fromPdo($db);
        foreach($written as $w){$rs=r54_rows($db,'SELECT anex_hotel_id,catalog_hotel_id,match_class,scope,approval_policy,source_row_digest,mapping_digest,enabled FROM anex_hotel_search_mappings WHERE anex_hotel_id=?',[$w['anex_hotel_id']]);if(count($rs)!==1)throw new RuntimeException('postcommit_row_count');$row=$rs[0];foreach(['catalog_hotel_id'=>$w['catalog_hotel_id'],'match_class'=>R54_CLASS,'scope'=>'preview','approval_policy'=>R54_POLICY,'source_row_digest'=>$w['source_row_digest'],'mapping_digest'=>$mappingDigest,'enabled'=>1] as $k=>$v)if((string)$row[$k]!== (string)$v)throw new RuntimeException('postcommit_contract');if($reg->resolve('anex_online',(string)$w['anex_hotel_id'],'preview')!==$w['catalog_hotel_id'])throw new RuntimeException('postcommit_effective_mapping');$post[]=$row;}
        $result=$base+['state'=>'committed_verified','plan_sha256'=>$planDigest,'written_count'=>count($written),'triple_increment'=>count(array_filter($written,fn($d)=>$d['has_accepted_andromeda'])),'written'=>$written,'post_commit_readback'=>$post,'held_count'=>count($holds),'held'=>$holds,'database_writes'=>count($written),'mapping_writes'=>count($written)];
    }
} catch(Throwable $e) {
    $rollback=false;try{if($db&&$db->inTransaction()){$db->rollBack();$rollback=true;}}catch(Throwable $ignored){}
    $state=$committed?'committed_readback_failed':($commitStarted?'commit_outcome_unknown':'blocked_no_commit');
    $result=$base+['state'=>$state,'reason_class'=>get_class($e),'reason'=>preg_match('/^[a-z0-9_]+$/D',$e->getMessage())?$e->getMessage():'database_or_runtime_error','sqlstate'=>$e instanceof PDOException?(string)$e->getCode():null,'driver_code'=>$e instanceof PDOException?($e->errorInfo[1]??null):null,'commit_started'=>$commitStarted,'commit_returned'=>$committed,'rollback_returned'=>$rollback,'database_writes'=>$committed?count($written):($commitStarted?null:0),'mapping_writes'=>$committed?count($written):($commitStarted?null:0),'attempted_rows'=>$written,'post_commit_readback'=>$post];
}
$sha=r54_save($dir.'/result.json',$result);r54_save($dir.'/receipt.json',['operation'=>$op,'state'=>$result['state'],'result_sha256'=>$sha,'readback_verified'=>hash_file('sha256',$dir.'/result.json')===$sha,'mapping_writes'=>$result['mapping_writes'],'no_replay'=>true]);
echo r54_json(['state'=>$result['state'],'eligible_count'=>$result['eligible_count']??null,'written_count'=>$result['written_count']??null,'held_count'=>$result['held_count']??null,'result_sha256'=>$sha]);exit(in_array($result['state'],['planned_read_only','committed_verified'],true)?0:2);
