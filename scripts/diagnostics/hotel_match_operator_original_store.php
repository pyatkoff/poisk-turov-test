<?php
declare(strict_types=1);
/* MATCH-only append: supplier-native namespaces plus free ANEX preview mappings. */
const MOS_OP='hotel-match-operator-original-store-1971-20260915-v1';
const MOS_INPUT_OP='hotel-match-operator-original-batch-1971-20260915-v2';
const MOS_INPUT_SOURCE='0ba983e53ba3cef37f4400828d5efe9d2530381e';
const MOS_POLICY='owner_exact_and_strong_20260908';
function mos_json(array $x): string{return json_encode($x,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";}
function mos_write(string $file,array $x):string{$raw=mos_json($x);$f=fopen($file,'x+b');if(!$f)throw new RuntimeException('exclusive_file');try{if(fwrite($f,$raw)!==strlen($raw)||!fflush($f))throw new RuntimeException('file_write');if(function_exists('fsync')&&!fsync($f))throw new RuntimeException('file_sync');rewind($f);if(stream_get_contents($f)!==$raw)throw new RuntimeException('file_readback');}finally{fclose($f);}return hash('sha256',$raw);}
function mos_query(PDO $db,string $sql,array $args=[]):array{$q=$db->prepare($sql);$q->execute($args);return $q->fetchAll(PDO::FETCH_ASSOC);}
function mos_groups(array $input):array{
    if(($input['state']??'')!=='completed'||($input['operation_id']??'')!==MOS_INPUT_OP||($input['source_sha']??'')!==MOS_INPUT_SOURCE||count($input['facts']??[])>5000)throw new RuntimeException('source_contract');
    $proof=[];foreach($input['pages'] as $p)$proof[$p['request_sha256']]=$p;$p=$input['plan_summary']['previous_request'];$proof[$p['request_sha256']]=$p;
    $groups=[];$seen=[];
    foreach($input['facts'] as $f){$op=$f['operator_key'];$native=$f['native_hotel_id'];$id=$f['andromeda_hotel_id'];$country=$f['country_id'];
        if(!in_array($op,['5','315','342','115'],true)||!is_string($native)||!preg_match('/^[A-Za-z0-9_.-]{1,32}$/D',$native)||!is_string($id)||!preg_match('/^[1-9][0-9]{0,19}$/D',$id)||!in_array($country,[1,4],true)||($f['is_operator_hotel_key']??true)!==false||($f['action']??'')!=='price')throw new RuntimeException('fact_identity');
        $p=$proof[$f['request_sha256']]??null;if(!$p||$f['response_sha256']!==$p['response_sha256']||hash('sha256',mos_json($p['params']))!==$f['request_sha256']||(string)$p['params']['OPERATORS']!==$op||!in_array($id,explode(',',$p['params']['HOTELS']),true)||$p['params']['STATEINC']!==[1=>3,4=>5][$country])throw new RuntimeException('fact_request_binding');
        $key=$op.'|'.$native.'|'.$id;if(isset($seen[$key]))throw new RuntimeException('duplicate_fact');$seen[$key]=true;$g=$op.'|'.strtolower($native);
        if(!isset($groups[$g]))$groups[$g]=['operator_key'=>$op,'native_hotel_id'=>$native,'facts'=>[],'case_conflict'=>false];
        if($groups[$g]['native_hotel_id']!==$native)$groups[$g]['case_conflict']=true;$groups[$g]['facts'][]=$f;
    }ksort($groups,SORT_STRING);return $groups;
}
function mos_tokens(string $s):array{
    $s=mb_strtolower($s,'UTF-8');$s=strtr($s,['ё'=>'е','é'=>'e','è'=>'e','á'=>'a','ö'=>'o','ü'=>'u','ç'=>'c','ş'=>'s','ğ'=>'g','ı'=>'i']);
    $s=preg_replace('/\s*\(\s*(?:ex|ех)\.?\s+[^)]*\)\s*$/u','',$s);preg_match_all('/[\p{L}\p{N}]+/u',$s,$m);
    return array_values(array_unique(array_diff($m[0],['hotel','hotels','resort','resorts','spa','the','and','by','club','suite','suites','apart','apartment','apartments','отель','спа','sharm','el','sheikh','egypt','turkey','istanbul','antalya','alanya'])));
}
function mos_name_guard(array $names,string $local):bool{
    $lt=mos_tokens($local);$qual=['annex','beach','garden','gardens','north','south','pool','posh','aquamarine'];$lq=array_values(array_intersect($lt,$qual));sort($lq);
    foreach($names as $name){$t=mos_tokens($name);$q=array_values(array_intersect($t,$qual));sort($q);if(!array_intersect($t,$lt)||$q!==$lq)return false;}return true;
}
function mos_distance(array $s,array $h):?float{
    $a=$s['latitude']??$s['lat']??null;$b=$s['longitude']??$s['lng']??$s['lon']??null;$c=$h['latitude']??null;$d=$h['longitude']??null;
    foreach([$a,$b,$c,$d] as $v)if(!is_numeric($v))return null;$a=(float)$a;$b=(float)$b;$c=(float)$c;$d=(float)$d;
    if(abs($a)>90||abs($c)>90||abs($b)>180||abs($d)>180||($a==0&&$b==0)||($c==0&&$d==0))return null;
    [$a,$b,$c,$d]=array_map('deg2rad',[$a,$b,$c,$d]);return 6371*2*asin(min(1,sqrt(sin(($c-$a)/2)**2+cos($a)*cos($c)*sin(($d-$b)/2)**2)));
}
function mos_classify(array $group,array $anchors,array $hotels,bool $existing):array{
    if($existing)return ['state'=>'hold','reason'=>'existing_native_identity_protected'];
    if($group['case_conflict'])return ['state'=>'hold','reason'=>'native_key_case_conflict'];
    $countries=[];$locals=[];$evidence=[];$names=[];
    foreach($group['facts'] as $f){$countries[$f['country_id']]=true;$a=$anchors[$f['andromeda_hotel_id']]??null;
        if(!$a||!in_array($a['decision_status'],['accepted','pending'],true)||($a['decision_status']==='pending'&&$a['local_hotel_id']!==null))return ['state'=>'hold','reason'=>'catalog_identity_missing_or_protected'];
        $ev=json_decode($a['evidence_json']??'{}',true)?:[];$source=is_array($ev['source']??null)?$ev['source']:[];
        if(isset($source['stateKey'])&&(int)$source['stateKey']!==[1=>3,4=>5][$f['country_id']])return ['state'=>'hold','reason'=>'catalog_country_conflict'];
        if($a['decision_status']==='accepted'){
            $id=(int)$a['local_hotel_id'];$h=$hotels[$id]??null;if(!$h||(int)$h['is_active']!==1||(int)$h['country_id']!==$f['country_id'])return ['state'=>'hold','reason'=>'local_country_or_activity_conflict'];
            foreach([$source,is_array($ev['geography']??null)?$ev['geography']:[]] as $geo){$distance=mos_distance($geo,$h);if($distance!==null&&$distance>5)return ['state'=>'hold','reason'=>'coordinate_conflict_gt5km'];}
            if(!mos_name_guard([$f['hotel_name'],$f['original_name']],$h['name']))return ['state'=>'hold','reason'=>'name_or_qualifier_conflict'];
            $locals[$id]=true;$evidence[]=['andromeda_hotel_id'=>$f['andromeda_hotel_id'],'local_hotel_id'=>$id,'evidence_sha256'=>$a['evidence_sha256']];
        }
        $names[]=$f['original_name'];
    }
    if(count($countries)!==1||count($locals)>1)return ['state'=>'hold','reason'=>'cross_catalog_conflicting_targets'];
    $local=$locals?(int)array_key_first($locals):null;
    if($local!==null&&!mos_name_guard($names,$hotels[$local]['name']))return ['state'=>'hold','reason'=>'group_name_or_qualifier_conflict'];
    return ['state'=>$local===null?'pending':'accepted','local_hotel_id'=>$local,'country_id'=>(int)array_key_first($countries),'anchors'=>$evidence,'reason'=>$local===null?'native_bridge_saved_local_unresolved':'direct_original_to_current_accepted_catalog'];
}
function mos_coverage(PDO $db):array{
    $a=mos_query($db,"SELECT DISTINCT m.catalog_hotel_id id FROM anex_hotel_search_mappings m WHERE m.enabled=1 AND m.scope='preview' AND m.approval_policy='".MOS_POLICY."' AND NOT EXISTS(SELECT 1 FROM anex_hotel_decisions d WHERE d.anex_hotel_id=m.anex_hotel_id) AND NOT EXISTS(SELECT 1 FROM anex_review_pair_exclusions x WHERE x.anex_hotel_id=m.anex_hotel_id AND x.catalog_hotel_id=m.catalog_hotel_id) UNION SELECT d.catalog_hotel_id id FROM anex_hotel_decisions d WHERE d.decision_status='accepted' AND d.catalog_hotel_id IS NOT NULL AND NOT EXISTS(SELECT 1 FROM anex_review_pair_exclusions x WHERE x.anex_hotel_id=d.anex_hotel_id AND x.catalog_hotel_id=d.catalog_hotel_id)");
    $d=mos_query($db,"SELECT DISTINCT local_hotel_id id FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IS NOT NULL");$aa=array_column($a,'id');$dd=array_column($d,'id');return ['anex_unique_local'=>count($aa),'andromeda_unique_local'=>count($dd),'all_three'=>count(array_intersect($aa,$dd))];
}
function mos_apply(string $dir,string $sha,string $inputHash):void{
    if(!is_file($dir.'/reservation.json')||!hash_equals($inputHash,hash_file('sha256',$dir.'/input.json')))throw new RuntimeException('input_reservation');
    $reservation=json_decode(file_get_contents($dir.'/reservation.json'),true,32,JSON_THROW_ON_ERROR);
    if(($reservation['operation_id']??null)!==MOS_OP||($reservation['source_sha']??null)!==$sha||($reservation['input_result_sha256']??null)!==$inputHash||($reservation['state']??null)!=='reserved_before_db_access')throw new RuntimeException('reservation_contract');
    $input=json_decode(file_get_contents($dir.'/input.json'),true,64,JSON_THROW_ON_ERROR);$groups=mos_groups($input);$root=realpath(getcwd());if(!$root||basename($root)!=='anytoour.ru')throw new RuntimeException('root_guard');
    $db=null;$committed=false;$written=[];$anexWritten=[];$phase='bootstrap';
    try{
        require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');$db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->beginTransaction();$phase='current_guards';
        $need=['andromeda_hotel_identities','catalog_hotels','anex_hotel_search_mappings','anex_hotel_decisions','anex_review_pair_exclusions'];$eng=mos_query($db,'SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('.implode(',',array_fill(0,count($need),'?')).')',$need);$eng=array_column($eng,'ENGINE','TABLE_NAME');foreach($need as $t)if(strtoupper($eng[$t]??'')!=='INNODB')throw new RuntimeException('transactional_table_required');
        $andIds=[];foreach($groups as $g)foreach($g['facts'] as $f)$andIds[$f['andromeda_hotel_id']]=true;$andIds=array_map('strval',array_keys($andIds));sort($andIds,SORT_STRING);if(!$andIds)throw new RuntimeException('empty_source');
        $anchors=[];$locals=[];foreach(mos_query($db,"SELECT * FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id IN (".implode(',',array_fill(0,count($andIds),'?')).') ORDER BY external_hotel_id FOR UPDATE',$andIds) as $r){$anchors[(string)$r['external_hotel_id']]=$r;if($r['local_hotel_id']!==null)$locals[(int)$r['local_hotel_id']]=true;}
        $hotels=[];if($locals)foreach(mos_query($db,'SELECT id,country_id,name,latitude,longitude,is_active FROM catalog_hotels WHERE id IN ('.implode(',',array_fill(0,count($locals),'?')).') ORDER BY id FOR UPDATE',array_keys($locals)) as $h)$hotels[(int)$h['id']]=$h;
        $existing=[];foreach(mos_query($db,"SELECT supplier_namespace,external_hotel_id FROM andromeda_hotel_identities WHERE supplier_namespace IN ('operator_5','operator_315','operator_342','operator_115') ORDER BY supplier_namespace,external_hotel_id FOR UPDATE") as $r)$existing[$r['supplier_namespace'].'|'.strtolower($r['external_hotel_id'])]=true;
        $optional=mos_query($db,"SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='anex_review_state'");$hasReview=false;if($optional){if(strtoupper($optional[0]['ENGINE'])!=='INNODB')throw new RuntimeException('review_engine');$hasReview=(bool)mos_query($db,"SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='anex_review_state' AND COLUMN_NAME='anex_hotel_id'");}
        $before=mos_coverage($db);$plan=[];$holds=[];$plannedAnexTargets=[];
        foreach($groups as $g){$ns='operator_'.$g['operator_key'];$native=$g['native_hotel_id'];$decision=mos_classify($g,$anchors,$hotels,isset($existing[$ns.'|'.strtolower($native)]));$addAnex=false;$anexReason=null;
            if($decision['state']==='accepted'&&$g['operator_key']==='5'){
                if(!preg_match('/^[1-9][0-9]{0,9}$/D',$native)){$decision=['state'=>'hold','reason'=>'invalid_anex_key'];}
                else{$aid=(int)$native;$target=$decision['local_hotel_id'];$manual=mos_query($db,'SELECT * FROM anex_hotel_decisions WHERE anex_hotel_id=? FOR UPDATE',[$aid]);$maps=mos_query($db,'SELECT * FROM anex_hotel_search_mappings WHERE anex_hotel_id=? FOR UPDATE',[$aid]);$excluded=mos_query($db,'SELECT anex_hotel_id FROM anex_review_pair_exclusions WHERE anex_hotel_id=? AND catalog_hotel_id=? FOR UPDATE',[$aid,$target]);$review=$hasReview?mos_query($db,'SELECT anex_hotel_id FROM anex_review_state WHERE anex_hotel_id=? FOR UPDATE',[$aid]):[];
                    if($excluded||$review)$decision=['state'=>'hold','reason'=>'anex_exclusion_or_review_protected'];
                    elseif($manual){if(count($manual)!==1||$manual[0]['decision_status']!=='accepted'||(int)$manual[0]['catalog_hotel_id']!==$target)$decision=['state'=>'hold','reason'=>'anex_manual_protected'];else $anexReason='existing_accepted_manual';}
                    elseif($maps){foreach($maps as $m)if((int)$m['catalog_hotel_id']!==$target||(int)$m['enabled']!==1||$m['scope']!=='preview'||$m['approval_policy']!==MOS_POLICY){$decision=['state'=>'hold','reason'=>'existing_anex_mapping_protected'];break;}$anexReason='existing_mapping_preserved';}
                    else{$occupied=mos_query($db,'SELECT anex_hotel_id FROM anex_hotel_search_mappings WHERE catalog_hotel_id=? FOR UPDATE',[$target]);$manualTarget=mos_query($db,"SELECT anex_hotel_id FROM anex_hotel_decisions WHERE catalog_hotel_id=? AND decision_status='accepted' FOR UPDATE",[$target]);$addAnex=!$occupied&&!$manualTarget;$anexReason=$addAnex?'append_free_direct_anex_mapping':'existing_anex_target_occupancy_preserved';}
                }
            }
            if($decision['state']==='hold'){$holds[]=['supplier_namespace'=>$ns,'external_hotel_id'=>$native,'reason'=>$decision['reason'],'andromeda_ids'=>array_values(array_unique(array_column($g['facts'],'andromeda_hotel_id')))];continue;}
            if($addAnex){if(isset($plannedAnexTargets[$decision['local_hotel_id']])){$addAnex=false;$anexReason='duplicate_target_in_plan_preserved';}else{$plannedAnexTargets[$decision['local_hotel_id']]=true;}}
            $e=['schema'=>'operator-original-price-bridge/1','operation_id'=>MOS_OP,'source_operation_id'=>MOS_INPUT_OP,'source_result_sha256'=>$inputHash,'catalog_reference_kind'=>'price_capture','country_id'=>$decision['country_id'],'source'=>['id'=>$native,'name'=>$g['facts'][0]['original_name'],'operator_key'=>$g['operator_key']],'decision'=>$decision,'provider_bridges'=>$g['facts']];$json=mos_json($e);$plan[]=['supplier_namespace'=>$ns,'external_hotel_id'=>$native,'local_hotel_id'=>$decision['local_hotel_id'],'decision_status'=>$decision['state'],'catalog_sha256'=>$inputHash,'evidence_sha256'=>hash('sha256',$json),'evidence_json'=>$json,'append_anex_mapping'=>$addAnex,'anex_reason'=>$anexReason];
        }
        $planHash=mos_write($dir.'/prepared-current-plan.json',['operation_id'=>MOS_OP,'rows'=>$plan,'holds'=>$holds,'coverage_before'=>$before]);$phase='append';
        $insert=$db->prepare('INSERT INTO andromeda_hotel_identities(supplier_namespace,external_hotel_id,local_hotel_id,decision_status,catalog_sha256,evidence_sha256,evidence_json) VALUES(?,?,?,?,?,?,?)');
        foreach($plan as $r){$insert->execute([$r['supplier_namespace'],$r['external_hotel_id'],$r['local_hotel_id'],$r['decision_status'],$r['catalog_sha256'],$r['evidence_sha256'],$r['evidence_json']]);if($insert->rowCount()!==1)throw new RuntimeException('native_insert_count');$written[]=$r;
            if($r['append_anex_mapping']){$q=$db->prepare("INSERT INTO anex_hotel_search_mappings(anex_hotel_id,catalog_hotel_id,match_class,scope,approval_policy,source_row_digest,mapping_digest,enabled) VALUES(?,?,'strong_candidate','preview',?,?,?,1)");$q->execute([(int)$r['external_hotel_id'],$r['local_hotel_id'],MOS_POLICY,$r['evidence_sha256'],$planHash]);if($q->rowCount()!==1)throw new RuntimeException('anex_insert_count');$anexWritten[]=$r;}
        }
        $db->commit();$committed=true;$phase='post_commit_readback';mos_write($dir.'/committed.json',['operation_id'=>MOS_OP,'native_rows'=>count($written),'anex_rows'=>count($anexWritten),'plan_sha256'=>$planHash,'no_replay'=>true]);$readback=[];$byOperator=[];
        foreach($written as $r){$rows=mos_query($db,'SELECT * FROM andromeda_hotel_identities WHERE supplier_namespace=? AND external_hotel_id=?',[$r['supplier_namespace'],$r['external_hotel_id']]);if(count($rows)!==1)throw new RuntimeException('native_readback_count');$got=$rows[0];foreach(['supplier_namespace','external_hotel_id','decision_status','catalog_sha256','evidence_sha256','evidence_json'] as $k)if($got[$k]!==$r[$k])throw new RuntimeException('native_readback_value');if(($got['local_hotel_id']===null?null:(int)$got['local_hotel_id'])!==$r['local_hotel_id'])throw new RuntimeException('native_local_readback');
            $readback[]=array_intersect_key($r,array_flip(['supplier_namespace','external_hotel_id','local_hotel_id','decision_status','evidence_sha256']));$byOperator[$r['supplier_namespace']][$r['decision_status']]=($byOperator[$r['supplier_namespace']][$r['decision_status']]??0)+1;
        }
        $anexReadback=[];foreach($anexWritten as $r){$rows=mos_query($db,'SELECT * FROM anex_hotel_search_mappings WHERE anex_hotel_id=?',[(int)$r['external_hotel_id']]);if(count($rows)!==1||(int)$rows[0]['catalog_hotel_id']!==$r['local_hotel_id']||(int)$rows[0]['enabled']!==1||$rows[0]['scope']!=='preview'||$rows[0]['approval_policy']!==MOS_POLICY||$rows[0]['source_row_digest']!==$r['evidence_sha256']||$rows[0]['mapping_digest']!==$planHash)throw new RuntimeException('anex_readback');$anexReadback[]=['anex_hotel_id'=>(int)$r['external_hotel_id'],'catalog_hotel_id'=>$r['local_hotel_id'],'source_row_digest'=>$r['evidence_sha256']];}
        $result=['operation_id'=>MOS_OP,'source_sha'=>$sha,'state'=>'completed_committed','source_result_sha256'=>$inputHash,'source_pairs'=>count($input['facts']),'native_groups'=>count($groups),'native_rows_written'=>count($written),'anex_mappings_written'=>count($anexWritten),'by_operator'=>$byOperator,'holds'=>$holds,'post_commit_readback'=>$readback,'anex_post_commit_readback'=>$anexReadback,'coverage_before'=>$before,'coverage_after'=>mos_coverage($db),'supplier_calls'=>0,'no_replay'=>true];$hash=mos_write($dir.'/result.json',$result);mos_write($dir.'/receipt.json',['operation_id'=>MOS_OP,'source_sha'=>$sha,'state'=>'completed_committed','result_sha256'=>$hash,'readback_verified'=>true,'post_commit_native_rows'=>count($readback),'post_commit_anex_rows'=>count($anexReadback),'supplier_calls'=>0,'no_replay'=>true]);echo json_encode(['native_rows'=>count($written),'anex_mappings'=>count($anexWritten),'by_operator'=>$byOperator,'holds'=>count($holds)])."\n";
    }catch(Throwable $e){if(!$committed&&$db instanceof PDO&&$db->inTransaction())$db->rollBack();$state=$committed?'unknown_after_commit':'failed_before_commit';$failure=['operation_id'=>MOS_OP,'source_sha'=>$sha,'state'=>$state,'phase'=>$phase,'error_class'=>get_class($e),'reason'=>preg_match('/^[a-z_]+$/D',$e->getMessage())?$e->getMessage():'sanitized_database_error','no_replay'=>true];if($committed){mos_write($dir.'/unknown.json',$failure);}else{$hash=mos_write($dir.'/result.json',$failure);mos_write($dir.'/receipt.json',['operation_id'=>MOS_OP,'source_sha'=>$sha,'state'=>$state,'result_sha256'=>$hash,'readback_verified'=>true,'database_writes'=>0,'no_replay'=>true]);}fwrite(STDERR,$state.':'.$phase."\n");exit(2);}
}
if(!defined('MOS_LIBRARY_ONLY')){$sha=getenv('MATCH_SOURCE_SHA')?:'';$hash=getenv('MATCH_INPUT_SHA')?:'';if((getenv('MATCH_OPERATION_ID')?:'')!==MOS_OP||!preg_match('/^[0-9a-f]{40}$/D',$sha)||!preg_match('/^[0-9a-f]{64}$/D',$hash))throw new RuntimeException('operation_environment');mos_apply(getenv('HOME').'/.anytoour-match/operations/'.MOS_OP,$sha,$hash);}
