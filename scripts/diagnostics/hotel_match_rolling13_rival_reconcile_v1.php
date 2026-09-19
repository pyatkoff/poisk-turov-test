<?php
declare(strict_types=1);
// Exact evidence-bound reconciliation, never a global relaxation of matching guards.
const R13_OP='hotel-match-rolling13-rival-reconcile-1971-20260919-v1';
const R13_RELATED_SHA='022316636e8e981ea8496f793b5ece5e81e4d4caa831267e735a7ee1b0681b62';
const R13_FINAL_SHA='4b959f0796909cc3556d0ecb76d52aa5308d258586b96c7df2b669f564a414e3';
const R13_PAIRS=[7619=>4492,8768=>4453,20718=>58479,21350=>58485,21963=>43060,22037=>106204,23821=>71258,28609=>73056,35510=>76295,44067=>70984,44168=>116228,44311=>43517,45177=>143790];
$library=__DIR__.'/guard-library.php';
if(hash_file('sha256',$library)!=='990730d60f57aebf69f7f241bb08a09db7f3abb369d6b19880260ea729e517e5')throw new RuntimeException('guard_library_digest');
require_once $library;
function r13_load(string $path,string $sha):array{if(hash_file('sha256',$path)!==$sha)throw new RuntimeException('input_digest');return json_decode((string)file_get_contents($path),true,512,JSON_THROW_ON_ERROR);}
function r13_inputs(string $dir):array{
    $related=r13_load($dir.'/related.json',R13_RELATED_SHA);$final=r13_load($dir.'/plan.json',R13_FINAL_SHA);
    if($related['state']!=='completed_read_only'||$related['mapping_writes']!==0||count($related['dossiers'])!==22||$final['state']!=='planned_read_only'||$final['mapping_writes']!==0)throw new RuntimeException('input_state');
    $ps=r54_input($dir.'/audit.json');$out=[];
    foreach($ps as $p){$aid=$p['native_anex_id'];if(!isset(R13_PAIRS[$aid]))continue;if(R13_PAIRS[$aid]!==$p['hotel_id'])throw new RuntimeException('pair_manifest');
        $rr=array_values(array_filter($related['dossiers'],fn($x)=>$x['native_anex_id']===$aid));$ff=array_values(array_filter($final['dossiers'],fn($x)=>$x['native_anex_id']===$aid));
        if(count($rr)!==1||count($ff)!==1)throw new RuntimeException('dossier_count');$r=$rr[0];$f=$ff[0];
        if($r['hotel_id']!==$p['hotel_id']||$f['hotel_id']!==$p['hotel_id']||$r['prior_holds']!==['competing_saved_candidate']||$f['holds']!==['competing_saved_candidate']||$f['source_dossier_sha256']!==hash('sha256',r54_json($p)))throw new RuntimeException('dossier_binding');
        if($r['direct_operator_link']!==$p['operator_link']||$r['safe_to_write_now']!==false)throw new RuntimeException('direct_link_binding');
        $rivals=[];foreach($r['rival_native_profiles'] as $x)$rivals[(int)$x['native_anex_id']]=$x;
        if(!$rivals)throw new RuntimeException('rival_absent');
        foreach($f['saved_candidates'] as $c){$other=(int)$c['anex_hotel_id'];if($other===$aid||(int)$c['catalog_hotel_id']!==$p['hotel_id']||!isset($rivals[$other]))throw new RuntimeException('not_reverse_candidate');}
        $out[]=['anchor'=>$p,'held'=>$f,'related'=>$r,'rivals'=>$rivals];
    }
    if(count($out)!==13)throw new RuntimeException('exact_thirteen');return $out;
}
function r13_rival_reason(array $target,array $current,array $expected):?string{
    if($current['native_anex_id']!==$expected['native_anex_id'])return null;
    $cn=$current['native_catalog'];$en=$expected['native_catalog'];if(count($cn)!==1||count($en)!==1)return null;
    foreach(['api_name','api_country','source_fingerprint'] as $key)if((string)$cn[0][$key]!== (string)$en[0][$key])return null;
    $known=$expected['effective_accepted_local_id'];$now=$current['effective_accepted_local_id'];
    if($known!==null){
        $c=$current['effective_local'];$e=$expected['effective_local'];
        if($now!==$known||$known===$target['hotel_id']||!$c||!$e||(int)$c['is_active']!==1||(int)$c['id']!==$known)return null;
        if($c['name']!==$e['name']||(int)$c['country_id']!==(int)$e['country_id']||r54_name($c['name'])===r54_name($target['hotel_name']))return null;
        return 'rival_resolved_to_different_named_hotel';
    }
    // Only these literal, independently retained wrong-country cases are adjudicated.
    $explicit=[24240=>['мальта','uae'],21863=>['нидерланды','uae'],4029=>['украина','vietnam']];
    $id=$current['native_anex_id'];if(!isset($explicit[$id])||$now!==null)return null;
    [$country,$targetCountry]=$explicit[$id];
    if(r54_country($cn[0]['api_country'])!==$country||r54_country($target['country_name'])!==$targetCountry)return null;
    return 'rival_explicit_other_country';
}
function r13_profiles(PDO $db,array $items):array{
    $rivals=[];$knownLocals=[];foreach($items as $item)foreach($item['rivals'] as $id=>$r){$rivals[$id]=true;if($r['effective_accepted_local_id']!==null)$knownLocals[$r['effective_accepted_local_id']]=true;}
    $ids=array_keys($rivals);sort($ids,SORT_NUMERIC);$ph=implode(',',array_fill(0,count($ids),'?'));
    $native=r54_rows($db,"SELECT anex_hotel_id,api_name,api_country,source_fingerprint FROM anex_hotels WHERE anex_hotel_id IN ($ph) ORDER BY anex_hotel_id FOR UPDATE",$ids);
    // Lock every fact used by the canonical resolver for the exact rival identities.
    $maps=r54_rows($db,"SELECT anex_hotel_id,catalog_hotel_id FROM anex_hotel_search_mappings WHERE anex_hotel_id IN ($ph) ORDER BY anex_hotel_id FOR UPDATE",$ids);
    r54_rows($db,"SELECT anex_hotel_id,catalog_hotel_id,decision_status FROM anex_hotel_decisions WHERE anex_hotel_id IN ($ph) ORDER BY anex_hotel_id FOR UPDATE",$ids);
    r54_rows($db,"SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions WHERE anex_hotel_id IN ($ph) ORDER BY anex_hotel_id,catalog_hotel_id FOR UPDATE",$ids);
    foreach($maps as $m)if((int)$m['catalog_hotel_id']>0)$knownLocals[(int)$m['catalog_hotel_id']]=true;
    $lids=array_keys($knownLocals);sort($lids,SORT_NUMERIC);$lp=implode(',',array_fill(0,count($lids),'?'));
    $local=r54_rows($db,"SELECT id,name,country_id,country_name,is_active FROM catalog_hotels WHERE id IN ($lp) ORDER BY id FOR UPDATE",$lids);$byLocal=array_column($local,null,'id');
    $reg=AnyTourAnexSearchMappingRegistry::fromPdo($db);$out=[];
    foreach($ids as $id){$to=$reg->resolve('anex_online',(string)$id,'preview');$out[$id]=['native_anex_id'=>$id,'effective_accepted_local_id'=>$to,'effective_local'=>$to?($byLocal[$to]??null):null,'native_catalog'=>array_values(array_filter($native,fn($r)=>(int)$r['anex_hotel_id']===$id))];}
    return $out;
}
function r13_review(array $item,array $snapshot,array $profiles):array{
    $p=$item['anchor'];$d=r54_review($p,$snapshot);$reason=[];$adjudications=[];
    // A new source-side competitor or changed proposal is never cleared by an old proof.
    if(r54_json($d['saved_candidates'])!==r54_json($item['held']['saved_candidates']))$reason[]='candidate_set_changed';
    foreach($d['saved_candidates'] as $c){$other=(int)$c['anex_hotel_id'];
        if($other===$p['native_anex_id']||(int)$c['catalog_hotel_id']!==$p['hotel_id']||!isset($item['rivals'][$other],$profiles[$other])){$reason[]='unreviewed_candidate';continue;}
        $target=['hotel_id'=>$p['hotel_id'],'hotel_name'=>$d['current_local']['name']??'','country_name'=>$d['current_local']['country_name']??''];
        $why=r13_rival_reason($target,$profiles[$other],$item['rivals'][$other]);
        if($why===null)$reason[]='rival_identity_not_reconfirmed';
        else $adjudications[]=['candidate'=>$c,'rival'=>$profiles[$other],'reason'=>$why];
    }
    $original=$d['holds'];$remaining=$original;
    if(!$reason&&count($adjudications)===count($d['saved_candidates'])&&$adjudications!==[])$remaining=array_values(array_filter($remaining,fn($r)=>$r!=='competing_saved_candidate'));
    $d['original_current_holds']=$original;$d['holds']=array_values(array_unique(array_merge($remaining,$reason)));$d['candidate_adjudications']=$adjudications;$d['eligible_for_guarded_append']=$d['holds']===[];
    return $d;
}
function r13_selftest(string $dir):void{
    r54_selftest($dir.'/audit.json');$items=r13_inputs($dir);$n=0;$ok=function(bool $x)use(&$n){$n++;if(!$x)throw new RuntimeException('reconcile_test_'.$n);};
    foreach($items as $item){
        $p=$item['anchor'];$s=array_fill_keys(['maps','decisions','exclusions','native','auto','observations','candidates','andromeda'],[]);$s['locals']=[$item['related']['target_local']];$s['candidates']=$item['held']['saved_candidates'];$profiles=$item['rivals'];
        $d=r13_review($item,$s,$profiles);$ok($d['eligible_for_guarded_append']);$ok(count($d['candidate_adjudications'])===count($s['candidates']));
        $x=$s;$x['maps']=[['anex_hotel_id'=>$p['native_anex_id'],'catalog_hotel_id'=>999]];$ok(!r13_review($item,$x,$profiles)['eligible_for_guarded_append']);
        $x=$s;$x['decisions']=[['anex_hotel_id'=>$p['native_anex_id'],'catalog_hotel_id'=>$p['hotel_id'],'decision_status'=>'rejected']];$ok(!r13_review($item,$x,$profiles)['eligible_for_guarded_append']);
        $x=$s;$x['exclusions']=[['anex_hotel_id'=>$p['native_anex_id'],'catalog_hotel_id'=>$p['hotel_id']]];$ok(!r13_review($item,$x,$profiles)['eligible_for_guarded_append']);
        $x=$s;$x['candidates'][0]['score']='0.000000';$ok(!r13_review($item,$x,$profiles)['eligible_for_guarded_append']);
        $y=$profiles;$k=array_key_first($y);$y[$k]['effective_accepted_local_id']=$p['hotel_id'];$ok(!r13_review($item,$s,$y)['eligible_for_guarded_append']);
        $y=$profiles;$y[$k]['native_catalog'][0]['source_fingerprint']='changed';$ok(!r13_review($item,$s,$y)['eligible_for_guarded_append']);
        $x=$s;$x['locals'][0]['country_id']=999;$ok(!r13_review($item,$x,$profiles)['eligible_for_guarded_append']);
        $x=$s;$x['candidates'][]=['anex_hotel_id'=>$p['native_anex_id'],'catalog_hotel_id'=>999];$ok(!r13_review($item,$x,$profiles)['eligible_for_guarded_append']);
    }
    echo 'ROLLING13_RECONCILE_SELFTEST_OK '.$n."\n";
}
if(($argv[1]??'')==='--self-test'){r13_selftest($argv[2]??__DIR__);exit;}
if(PHP_SAPI!=='cli'||($argv[1]??'')!=='--apply')throw new RuntimeException('disabled');
$dir=realpath((string)getenv('MATCH_OPERATION_DIR'));$root=realpath((string)getenv('ANYTOUR_ROOT'));if(!$dir||!$root||basename($root)!=='anytoour.ru')throw new RuntimeException('paths');
$res=json_decode((string)file_get_contents($dir.'/reservation.json'),true,512,JSON_THROW_ON_ERROR);
if(($res['operation']??'')!==R13_OP||($res['state']??'')!=='reserved_before_db_write'||($res['related_sha256']??'')!==R13_RELATED_SHA||($res['plan_sha256']??'')!==R13_FINAL_SHA||!preg_match('/^[0-9a-f]{40}$/D',$res['source_sha']??''))throw new RuntimeException('reservation');
$base=['operation'=>R13_OP,'source_sha'=>$res['source_sha'],'related_sha256'=>R13_RELATED_SHA,'plan_sha256'=>R13_FINAL_SHA,'audit_sha256'=>R54_AUDIT_SHA,'supplier_calls'=>0,'no_replay'=>true];
$db=null;$commitStarted=false;$committed=false;$written=[];$post=[];
try{
    r54_save($dir.'/execution-reservation.json',$res);$items=r13_inputs($dir.'/payload');
    $rf=$dir.'/payload/anex-search-mapping-registry.php';$raw=(string)file_get_contents($rf);if(sha1('blob '.strlen($raw)."\0".$raw)!==R54_REGISTRY_BLOB)throw new RuntimeException('registry_digest');require_once $rf;
    require_once (is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php');$db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');$db->beginTransaction();
    $snapshot=r54_current($db,array_column($items,'anchor'),true);$profiles=r13_profiles($db,$items);$eligible=[];$held=[];
    foreach($items as $item){$d=r13_review($item,$snapshot,$profiles);if($d['eligible_for_guarded_append'])$eligible[]=$d;else $held[]=$d;}
    $clock=r54_rows($db,'SELECT UTC_TIMESTAMP AS db_utc_timestamp')[0]['db_utc_timestamp'];
    $mapHash=hash('sha256',r54_json($base+['eligible'=>$eligible,'match_class'=>R54_CLASS,'approval_policy'=>R54_POLICY,'scope'=>'preview']));
    r54_save($dir.'/precommit-plan.json',$base+['captured_at_utc'=>$clock,'eligible'=>$eligible,'held'=>$held,'mapping_digest'=>$mapHash]);
    $ins=$db->prepare('INSERT INTO anex_hotel_search_mappings (anex_hotel_id,catalog_hotel_id,match_class,scope,approval_policy,source_row_digest,mapping_digest,enabled) VALUES (?,?,?,?,?,?,?,1)');
    foreach($eligible as $d){$src=hash('sha256',r54_json($base+['adjudicated_current_evidence'=>$d]));$ins->execute([$d['native_anex_id'],$d['hotel_id'],R54_CLASS,'preview',R54_POLICY,$src,$mapHash]);if($ins->rowCount()!==1)throw new RuntimeException('insert_count');$written[]=['anex_hotel_id'=>$d['native_anex_id'],'catalog_hotel_id'=>$d['hotel_id'],'source_row_digest'=>$src,'mapping_digest'=>$mapHash,'has_accepted_andromeda'=>$d['has_accepted_andromeda']];}
    r54_save($dir.'/precommit-written.json',$base+['written_uncommitted'=>$written]);$commitStarted=true;$db->commit();$committed=true;r54_save($dir.'/commit-returned.json',$base+['committed_count'=>count($written)]);
    $reg=AnyTourAnexSearchMappingRegistry::fromPdo($db);
    foreach($written as $w){$rs=r54_rows($db,'SELECT anex_hotel_id,catalog_hotel_id,match_class,scope,approval_policy,source_row_digest,mapping_digest,enabled FROM anex_hotel_search_mappings WHERE anex_hotel_id=?',[$w['anex_hotel_id']]);if(count($rs)!==1)throw new RuntimeException('postcommit_row_count');$row=$rs[0];foreach(['catalog_hotel_id'=>$w['catalog_hotel_id'],'match_class'=>R54_CLASS,'scope'=>'preview','approval_policy'=>R54_POLICY,'source_row_digest'=>$w['source_row_digest'],'mapping_digest'=>$mapHash,'enabled'=>1] as $k=>$v)if((string)$row[$k]!== (string)$v)throw new RuntimeException('postcommit_contract');if($reg->resolve('anex_online',(string)$w['anex_hotel_id'],'preview')!==$w['catalog_hotel_id'])throw new RuntimeException('effective_resolver_readback');$post[]=$row;}
    $out=$base+['state'=>'committed_verified','current_checked_at_utc'=>$clock,'written_count'=>count($written),'triple_increment'=>count(array_filter($written,fn($x)=>$x['has_accepted_andromeda'])),'held_count'=>count($held),'held'=>$held,'written'=>$written,'post_commit_readback'=>$post,'mapping_writes'=>count($written),'database_writes'=>count($written)];
}catch(Throwable $e){$rollback=false;try{if($db&&$db->inTransaction()){$db->rollBack();$rollback=true;}}catch(Throwable $ignored){}$out=$base+['state'=>$committed?'committed_readback_failed':($commitStarted?'commit_outcome_unknown':'blocked_no_commit'),'reason'=>preg_match('/^[a-z0-9_]+$/D',$e->getMessage())?$e->getMessage():'database_or_runtime_error','sqlstate'=>$e instanceof PDOException?(string)$e->getCode():null,'driver_code'=>$e instanceof PDOException?($e->errorInfo[1]??null):null,'commit_started'=>$commitStarted,'commit_returned'=>$committed,'rollback_returned'=>$rollback,'attempted_rows'=>$written,'post_commit_readback'=>$post,'mapping_writes'=>$committed?count($written):($commitStarted?null:0),'database_writes'=>$committed?count($written):($commitStarted?null:0)];}
$sha=r54_save($dir.'/result.json',$out);r54_save($dir.'/receipt.json',['operation'=>R13_OP,'state'=>$out['state'],'result_sha256'=>$sha,'readback_verified'=>hash_file('sha256',$dir.'/result.json')===$sha,'mapping_writes'=>$out['mapping_writes'],'no_replay'=>true]);echo r54_json(['state'=>$out['state'],'written_count'=>$out['written_count']??null,'held_count'=>$out['held_count']??null,'result_sha256'=>$sha]);exit($out['state']==='committed_verified'?0:2);
