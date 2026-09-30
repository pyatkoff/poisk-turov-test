<?php
declare(strict_types=1);
/**
 * First bounded primary-SAMO batch. Uses the unchanged v76/v78 validators,
 * never their historical entrypoints. No supplier client or runtime install.
 */
require_once __DIR__.'/hotel_match_raw_native_pending11_v78.php';

const PM1_BATCH = 'samo3-20260929';
const PM1_NATIVE_OP = 'hotel-match-live30-retained-native-union-1971-20260927-v77';
const PM1_NATIVE_SHA = 'd40fbe2e0240a5df194ac838376a3425a8f4e80f2426a757560fe369011107f7';
const PM1_LEDGER_OP = 'hotel-match-request-ledger-1971-20260928-v93';
const PM1_LEDGER_SHA = '8da64bc3ac54d707d335b8808b171f46a02ba0464536681bc40b741136df935c';
const PM1_PAIRS = [
    420 => ['catalog'=>'9501','namespace'=>'operator_342','native'=>'24402','operator'=>43,
        'producer'=>'hotel-match-residual2041-common4-nonanex-detail-1971-20260921-v4',
        'producer_sha'=>'2534eebc2a8b0ba79dbf32dedda165c7e87da609284b25c5209c04747624e564'],
    16944 => ['catalog'=>'2000034238','namespace'=>'operator_315','native'=>'211585','operator'=>25,
        'producer'=>'hotel-match-residual2041-search30-common4-1971-20260921-v3',
        'producer_sha'=>'76c740c4efbb95a2c2c44fd7fe69ecea30b5091c0e17dfec2bb4cf30da75cea2'],
    42903 => ['catalog'=>'3126','namespace'=>'operator_315','native'=>'849821','operator'=>25,
        'producer'=>'hotel-match-residual2041-common4-nonanex-detail-1971-20260921-v4',
        'producer_sha'=>'2534eebc2a8b0ba79dbf32dedda165c7e87da609284b25c5209c04747624e564'],
];

function pm1_operation(string $operation): bool {
    return preg_match('/^int-andromeda-match-primary-[a-z0-9-]{8,48}-v[1-9][0-9]*$/D',$operation)===1;
}
function pm1_scope(array $entries): void {
    w76_need(array_keys($entries)===array_keys(PM1_PAIRS),'primary_exact_scope');
    foreach(PM1_PAIRS as $id=>$spec){
        $entry=$entries[$id];
        w76_need(($entry['id']??null)===$id && ($entry['catalog_id']??null)===$spec['catalog']
            && ($entry['namespace']??null)===$spec['namespace']
            && ($entry['native_id']??null)===$spec['native'],'primary_pair_scope');
    }
}
function pm1_read(string $root,string $relative,?string $sha=null,int $cap=67108864): array {
    w76_need(preg_match('~^[a-zA-Z0-9_.-]+/(?:evidence-private/)?[a-zA-Z0-9_.-]+\.json$~D',$relative)===1,
        'retained_relative_path');
    $base=realpath($root);$path=$root.'/'.$relative;$real=realpath($path);
    w76_need($base!==false && $real!==false && $real===$base.'/'.$relative
        && is_file($path) && !is_link($path) && filesize($path)>0 && filesize($path)<=$cap,'retained_file');
    $bytes=file_get_contents($path);w76_need(is_string($bytes),'retained_read');
    if($sha!==null)w76_need(w76_sha($sha) && hash_equals($sha,hash('sha256',$bytes)),'retained_digest');
    $value=json_decode($bytes,true,128,JSON_THROW_ON_ERROR);
    w76_need(is_array($value),'retained_json');return $value;
}
function pm1_terminal(string $root,string $op,string $sha,array $states): array {
    $r=pm1_read($root,$op.'/result.json',$sha);
    $receipt=pm1_read($root,$op.'/receipt.json',null,1048576);
    w76_need(($receipt['result_sha256']??null)===$sha
        && in_array($r['state']??null,$states,true)
        && ($receipt['state']??null)===($r['state']??null)
        && ($r['operation']??$op)===$op
        && ($r['database_writes']??null)===0 && ($r['mapping_writes']??null)===0,'retained_terminal');
    if(($r['state']??'')==='terminal_wrapper_timeout_salvaged_no_replay'){
        w76_need(($receipt['salvaged_after_wrapper_timeout']??false)===true
            && ($receipt['no_replay']??false)===true,'salvaged_terminal_required');
    }
    return $r;
}
function pm1_tv_edge(array $e,int $id,array $spec): bool {
    if((int)($e['tv_hotel_id']??0)!==$id || (int)($e['operator_id']??0)!==$spec['operator']
        || ($e['state']??'')!=='detail_identity_verified' || ($e['link_state']??'')!=='captured_single_native'
        || ($e['namespace']??'')!==$spec['namespace'])return false;
    $ids=$e['positive_native_candidates']??null;
    if(!is_array($ids)||count($ids)!==1||(string)reset($ids)!==$spec['native'])return false;
    foreach(['operator_link_sha256','tour_id_sha256','search_id_sha256'] as $key){
        if(!w76_sha($e[$key]??null))return false;
    }
    return ($e['operator_link_host']??'')===($spec['operator']===25?'b2b.fstravel.com':'searchtour.intourist.ru');
}
/** Project only the two hash-pinned legacy producers; do not weaken pm1_tv_edge. */
function pm1_tv_projection(array $edge,int $id,array $spec): ?array {
    if(pm1_tv_edge($edge,$id,$spec))return $edge;
    $legacy=$spec['producer']??'';
    $detail=$legacy==='hotel-match-residual2041-common4-nonanex-detail-1971-20260921-v4'
        &&($spec['producer_sha']??'')==='2534eebc2a8b0ba79dbf32dedda165c7e87da609284b25c5209c04747624e564';
    $search=$legacy==='hotel-match-residual2041-search30-common4-1971-20260921-v3'
        &&($spec['producer_sha']??'')==='76c740c4efbb95a2c2c44fd7fe69ecea30b5091c0e17dfec2bb4cf30da75cea2';
    if(!$detail&&!$search)return null;
    if((int)($edge['tv_hotel_id']??0)!==$id||(int)($edge['operator_id']??0)!==$spec['operator']
        ||($edge['state']??null)!=='detail_identity_verified'||($edge['link_state']??null)!=='captured_single_native')return null;
    $ids=$edge['positive_native_candidates']??null;
    if(!is_array($ids)||!array_is_list($ids)||count($ids)!==1||!is_scalar($ids[0])||(string)$ids[0]!==$spec['native'])return null;
    if($detail&&($edge['target_supplier_namespace']??null)!==$spec['namespace'])return null;
    if(isset($edge['namespace'])&&$edge['namespace']!==$spec['namespace'])return null;
    $tour=$edge['tour_id']??null;$searchId=$edge[$detail?'retained_search_id':'search_id']??null;
    foreach([$tour,$searchId] as $value)if((!is_int($value)&&!is_string($value))||!preg_match('/^[1-9][0-9]{0,31}$/D',(string)$value))return null;
    if($detail&&((int)($edge['returned_tv_hotel_id']??0)!==$id
        ||(int)($edge['returned_operator_id']??0)!==$spec['operator']
        ||(string)($edge['returned_tour_id']??'')!==(string)$tour))return null;
    $url=$edge['operator_link']??null;
    if(!is_string($url)||strlen($url)>8192||!w76_sha($edge['operator_link_sha256']??null)
        ||!hash_equals($edge['operator_link_sha256'],hash('sha256',$url)))return null;
    $parts=parse_url($url);$host=$spec['operator']===25?'b2b.fstravel.com':'searchtour.intourist.ru';
    if(!is_array($parts)||($parts['scheme']??null)!=='https'||($parts['host']??null)!==$host
        ||isset($parts['user'])||isset($parts['pass'])||isset($parts['fragment'])||isset($parts['port']))return null;
    $hotels=[];
    foreach(explode('&',$parts['query']??'') as $pair){
        [$key,$value]=array_pad(explode('=',$pair,2),2,'');$key=urldecode($key);$value=urldecode($value);
        if(preg_match('/token|session|password|passwd|secret|authorization|jwt|bearer|sid/i',$key))return null;
        if(in_array(strtolower($key),['hotels','hotel','hotelid','hotel_id','hotelcode','hotellist'],true))$hotels[]=$value;
    }
    if($hotels!==[$spec['native']])return null;
    $projection=['tv_hotel_id'=>$id,'operator_id'=>$spec['operator'],'namespace'=>$spec['namespace'],
        'state'=>'detail_identity_verified','link_state'=>'captured_single_native','positive_native_candidates'=>$ids,
        'operator_link_sha256'=>$edge['operator_link_sha256'],'operator_link_host'=>$host,
        'tour_id_sha256'=>hash('sha256',(string)$tour),'search_id_sha256'=>hash('sha256',(string)$searchId)];
    return pm1_tv_edge($projection,$id,$spec)?$projection:null;
}
function pm1_prepare(string $root): array {
    $native=pm1_terminal($root,PM1_NATIVE_OP,PM1_NATIVE_SHA,['completed_retained_native_scan']);
    $ledger=pm1_terminal($root,PM1_LEDGER_OP,PM1_LEDGER_SHA,['completed_read_only_request_ledger']);
    $facts=[];$prior=[];$front=[];$tvTargets=[];$tvRows=[];
    foreach($native['native_facts']??[] as $f){
        $facts[(string)$f['supplier_namespace']][(string)$f['native_id']][(string)$f['catalog_id']]=$f;
    }
    foreach($native['current_identities']??[] as $r){
        if(($r['supplier_namespace']??'')==='andromeda_catalog')$prior[(string)$r['external_hotel_id']]=$r;
    }
    foreach($native['source_frontier']??[] as $r)$front[(string)$r['catalog_id']]=$r;
    foreach($ledger['rows']??[] as $r){
        $tv=(int)$r['tv_hotel_id'];$tvRows[$tv]=$r;
        foreach($r['lanes']??[] as $op=>$lane){
            foreach($lane['retained_exact']??[] as $n)$tvTargets[(int)$op][(string)$n][$tv]=true;
        }
    }
    $out=[];
    foreach(PM1_PAIRS as $id=>$spec){
        $cat=$spec['catalog'];$ns=$spec['namespace'];$n=$spec['native'];
        $e=['id'=>$id,'catalog_id'=>$cat,'namespace'=>$ns,'native_id'=>$n,
            'prepare_holds'=>[],'proofs'=>[],'operator_facts'=>[],'observed_at'=>0];
        try{
            w76_need(isset($prior[$cat],$front[$cat],$tvRows[$id]),'retained_source_or_target_missing');
            w76_need(count($facts[$ns][$n]??[])===1 && isset($facts[$ns][$n][$cat]),'native_not_globally_unique');
            w76_need(count($tvTargets[$spec['operator']][$n]??[])===1
                && isset($tvTargets[$spec['operator']][$n][$id]),'tv_native_not_unique');
            $e['prior']=$prior[$cat];$e['expected_name']=$tvRows[$id]['hotel_name'];$e['expected_country']=$tvRows[$id]['country'];
            foreach($front[$cat]['contexts']??[] as $context)$e['observed_at']=max($e['observed_at'],(int)($context['created_at']??0));
            foreach($native['native_facts']??[] as $fact){
                if((string)$fact['catalog_id']===$cat)$e['operator_facts'][]=['ns'=>$fact['supplier_namespace'],'native'=>(string)$fact['native_id']];
            }
            $producer=pm1_terminal($root,$spec['producer'],$spec['producer_sha'],
                ['completed_read_only','terminal_wrapper_timeout_salvaged_no_replay']);
            $targets=[];$lanes=[];$proof=null;
            foreach($producer['edges']??[] as $index=>$edge){
                if(!is_array($edge)||(int)($edge['operator_id']??0)!==$spec['operator'])continue;
                if(($edge['link_state']??'')==='captured_single_native' && count($edge['positive_native_candidates']??[])===1){
                    $other=(int)($edge['tv_hotel_id']??0);$external=(string)$edge['positive_native_candidates'][0];
                    if($external===$n)$targets[$other]=true;
                    if($other===$id)$lanes[$external]=true;
                }
                $projected=pm1_tv_projection($edge,$id,$spec);
                if($projected!==null)$proof=['kind'=>'independent_tv_audit','row'=>$projected,
                    'source_operation'=>$spec['producer'],'source_result_sha256'=>$spec['producer_sha'],'json_pointer'=>'/edges/'.$index];
            }
            w76_need($proof!==null && array_keys($targets)===[$id] && array_map('strval',array_keys($lanes))===[$n],
                'verified_tv_proof_missing_or_conflicting');
            $rawProof=null;
            foreach($facts[$ns][$n][$cat]['evidence']??[] as $pointer){
                try{
                    $relative=(string)($pointer['source_file']??'');
                    w76_need(str_starts_with($relative,'operations/'),'raw_proof_prefix');
                    $raw=pm1_read($root,substr($relative,strlen('operations/')),(string)($pointer['sha256']??''),16777216);
                    w76_need(w78_fact(w78_ptr($raw,(string)$pointer['json_pointer']),$cat,$ns,$n),'raw_native_identity');
                    $rawProof=$pointer;break;
                }catch(Throwable $ignored){}
            }
            w76_need($rawProof!==null,'verified_raw_native_proof_missing');
            $e['proofs'][]=['namespace'=>$ns,'native_id'=>$n,'tv'=>$proof,'samo'=>$rawProof,'samo_catalog_id'=>$cat];
        }catch(Throwable $x){$e['prepare_holds'][]=preg_match('/^[a-z_]+$/D',$x->getMessage())?$x->getMessage():'retained_prepare_failed';}
        $out[$id]=$e;
    }
    pm1_scope($out);return $out;
}
/** Additional geography check; the inherited name/category/occupancy gates remain. */
function pm1_geography(array $source,array $target): array {
    $normal=static fn($v)=>mb_strtolower(w76_text(is_scalar($v)?$v:''));
    $town=$normal($source['town']??'');$regions=[$normal($target['region_name']??''),$normal($target['subregion_name']??'')];
    $place=$town!==''&&in_array($town,$regions,true);
    $coords=static function(array $x): ?array {
        if(!is_numeric($x['latitude']??null)||!is_numeric($x['longitude']??null))return null;
        $lat=(float)$x['latitude'];$lon=(float)$x['longitude'];
        return is_finite($lat)&&is_finite($lon)&&abs($lat)<=90&&abs($lon)<=180&&($lat!=0.0||$lon!=0.0)?[$lat,$lon]:null;
    };
    $a=$coords($source);$b=$coords($target);$km=null;
    if($a!==null&&$b!==null){
        $dl=deg2rad($a[0]-$b[0]);$dn=deg2rad($a[1]-$b[1]);
        $v=sin($dl/2)**2+cos(deg2rad($a[0]))*cos(deg2rad($b[0]))*sin($dn/2)**2;
        $km=6371*2*asin(sqrt(min(1.0,max(0.0,$v))));
        if($km>5)return ['coordinate_conflict_over_5km'];
    }
    return $place||($km!==null&&$km<=1)?[]:['geography_requires_review'];
}
function pm1_classify(array $e,array $c): array {
    $id=$e['id'];$cat=$e['catalog_id'];$src=$c['sources'][$cat]??[];$row=count($src)===1?$src[0]:null;
    if($e['prepare_holds'])return ['status'=>'hold','reasons'=>$e['prepare_holds']];
    if(!$row||!w76_evidence_valid($row))return ['status'=>'hold','reasons'=>['canonical_source_missing_or_invalid']];
    $h=$c['hotels'][$id]??null;
    if(!$h)return ['status'=>'hold','reasons'=>['target_missing']];
    $history=json_decode($row['evidence_json'],true);
    if(!is_array($history['source']??null))return ['status'=>'hold','reasons'=>['source_history_missing']];
    $e['history']=$history;
    $e['target']=['id'=>(string)$id,'name'=>$e['expected_name'],'country_name'=>$e['expected_country'],'category'=>(string)$h['category']];
    $e['live_samo']=$e['observed_at']>=time()-30*86400 && $e['observed_at']<=time()+60;
    if((string)($history['source']['id']??'')!==$cat)$e['prepare_holds'][]='canonical_source_id_conflict';
    $decision=w78_classify($e,$c);
    if($decision['status']==='already')return $decision;
    $why=array_merge($decision['reasons'],pm1_geography($history['source'],$h));
    // starKey is NOT asserted to mean stars. A legacy disagreement remains held;
    // interpreting or relaxing that historical category gate is out of this mode.
    $why=array_values(array_unique($why));return ['status'=>$why?'hold':'ready','reasons'=>$why];
}
function pm1_context(array $rows): array {
    $c=['sources'=>[],'targets'=>[],'operators'=>[],'hotels'=>[],'manual'=>[],'exclusions'=>[]];
    foreach($rows as $r){
        $ns=$r['supplier_namespace'];$id=(string)$r['external_hotel_id'];
        if($ns==='andromeda_catalog'){
            $c['sources'][$id][]=$r;if($r['local_hotel_id']!==null)$c['targets'][(int)$r['local_hotel_id']][]=$r;
        }else $c['operators'][$ns][$id][]=$r;
    }
    return $c;
}
function pm1_resolver_readback(PDO $db,array $planned): void {
    require_once dirname(__DIR__,2).'/app/integrations/andromeda-hotel-resolver.php';
    $projection=[];$offers=[];
    foreach($planned as $p){
        $rows=w76_q($db,"SELECT i.supplier_namespace,i.external_hotel_id,i.decision_status,i.local_hotel_id AS catalog_hotel_id,h.id AS existing_catalog_hotel_id FROM andromeda_hotel_identities i JOIN catalog_hotels h ON h.id=i.local_hotel_id AND h.is_active=1 WHERE i.supplier_namespace='andromeda_catalog' AND i.external_hotel_id=?",[$p['cat']]);
        w76_need(count($rows)===1,'resolver_row_missing');$projection[]=$rows[0];
        $offers[]=['provider'=>'andromeda','supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>$p['cat'],'local_hotel_id'=>null,'selection_enabled'=>false];
    }
    $resolver=AnyTourAndromedaHotelResolver::fromRows($projection,w76_hash($projection));
    $page=$resolver->apply(['provider'=>'andromeda','selection_enabled'=>false,'offers'=>$offers]);
    foreach(array_values($planned) as $i=>$p)w76_need($page['offers'][$i]['local_hotel_id']===$p['id'],'effective_resolver_mismatch');
}
function pm1_write(PDO $db,array $entries,string $operation,string $head,string $dir): array {
    pm1_scope($entries);w76_need(pm1_operation($operation)&&!$db->inTransaction(),'writer_scope');
    $attempt=false;$committed=false;$sql=false;$rollback=false;$planned=[];$held=[];$already=[];$beforeCoverage=null;$examined=0;$tripleTargets=[];
    try{
        foreach(['andromeda_hotel_identities','catalog_hotels','tour_operator_identity_observations','anex_hotel_search_mappings','anex_hotel_decisions','anex_review_pair_exclusions'] as $t){
            $r=w76_q($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$t]);
            w76_need(count($r)===1&&strtoupper($r[0]['ENGINE'])==='INNODB','nontransactional_table');
        }
        $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
        $db->exec('SET SESSION innodb_lock_wait_timeout=20');$db->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
        w76_need($db->beginTransaction(),'begin');
        $all=w76_q($db,'SELECT * FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id LIMIT 50001 FOR UPDATE');
        $before=w76_index($all);$c=pm1_context($all);$ids=array_keys(PM1_PAIRS);
        foreach(w76_q($db,'SELECT id,name,country_id,country_name,region_name,subregion_name,category,is_active,latitude,longitude FROM catalog_hotels WHERE id IN (?,?,?) ORDER BY id FOR UPDATE',$ids) as $r)$c['hotels'][(int)$r['id']]=$r;
        $manual=w76_q($db,'SELECT anex_hotel_id,catalog_hotel_id,decision_status FROM anex_hotel_decisions ORDER BY anex_hotel_id LIMIT 50001 FOR UPDATE');
        $excluded=w76_q($db,'SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions ORDER BY anex_hotel_id,catalog_hotel_id LIMIT 50001 FOR UPDATE');
        foreach($manual as $r)if($r['catalog_hotel_id']!==null)$c['manual'][(int)$r['catalog_hotel_id']]=true;
        foreach($excluded as $r)if($r['catalog_hotel_id']!==null)$c['exclusions'][(int)$r['catalog_hotel_id']]=true;
        $anexBefore=w76_q($db,'SELECT * FROM anex_hotel_search_mappings ORDER BY anex_hotel_id LIMIT 50001 FOR UPDATE');
        $beforeCoverage=w76_census($db);
        $effective=AnyTourMatchAnexEffectiveCoverage::fromPdo($db);
        $liveTargets=[];
        foreach(w76_q($db,'SELECT DISTINCT hotel_id FROM tour_operator_identity_observations WHERE hotel_id IN (?,?,?) AND last_seen_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 30 DAY)',$ids) as $r)$liveTargets[(int)$r['hotel_id']]=true;
        foreach($entries as $id=>$e){
            $examined++;
            $d=pm1_classify($e,$c);$item=['local_hotel_id'=>$id,'catalog_id'=>$e['catalog_id']]+$d;
            if($d['status']==='hold'){$held[]=$item;continue;}
            if($d['status']==='already'){$already[]=$item;continue;}
            if(isset($liveTargets[$id])&&!empty($effective['by_local'][$id]))$tripleTargets[]=$id;
            $old=$c['sources'][$e['catalog_id']][0];$history=json_decode($old['evidence_json'],true);
            $evidence=['operation_id'=>$operation,'batch'=>PM1_BATCH,'source_sha'=>$head,
                'rule'=>'bounded_exact_same_operator_pending_null_transition',
                'inputs'=>[PM1_NATIVE_OP=>PM1_NATIVE_SHA,PM1_LEDGER_OP=>PM1_LEDGER_SHA],
                'source'=>$history['source'],'target'=>$c['hotels'][$id],'proofs'=>$e['proofs'],
                'prior_evidence_json'=>$old['evidence_json'],'prior_evidence_sha256'=>$old['evidence_sha256'],
                'catalog_sha256_preserved'=>$old['catalog_sha256'],'provider_http_calls'=>0];
            $raw=w76_json($evidence);
            $planned['andromeda_catalog|'.$e['catalog_id']]=['id'=>$id,'cat'=>$e['catalog_id'],
                'name'=>$c['hotels'][$id]['name'],'old'=>$old,'new_json'=>$raw,'new_sha'=>hash('sha256',$raw),'proof_count'=>count($e['proofs'])];
        }
        w76_need(count($planned)<=3,'write_cap');
        w76_save($dir.'/write-plan.json',['operation'=>$operation,'batch'=>PM1_BATCH,'source_sha'=>$head,
            'planned'=>$planned,'held'=>$held,'already'=>$already,'before_sha256'=>w76_hash($before),'new_full_triple_targets'=>$tripleTargets]);
        if(!$planned){w76_need($db->rollBack(),'rollback_failed');return ['state'=>'completed_no_new_writes','rows'=>[],
            'current_candidates_evaluated'=>$examined,'held'=>$held,'already'=>$already,'database_writes'=>0,'mapping_writes'=>0,'new_full_triples'=>0,
            'readback_verified'=>true,'coverage_before'=>$beforeCoverage,'coverage_after'=>$beforeCoverage];}
        $st=$db->prepare("UPDATE andromeda_hotel_identities SET local_hotel_id=?,decision_status='accepted',evidence_sha256=?,evidence_json=? WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=? AND decision_status='pending' AND local_hotel_id IS NULL AND catalog_sha256=? AND evidence_sha256=?");
        foreach($planned as $p){$sql=true;w76_need($st->execute([$p['id'],$p['new_sha'],$p['new_json'],$p['cat'],$p['old']['catalog_sha256'],$p['old']['evidence_sha256']])&&$st->rowCount()===1,'conditional_update');}
        $verify=static function() use($db,$before,$planned,$manual,$excluded,$anexBefore): array {
            $read=w76_verify($before,$planned,w76_q($db,'SELECT * FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id LIMIT 50001'));
            w76_need($manual===w76_q($db,'SELECT anex_hotel_id,catalog_hotel_id,decision_status FROM anex_hotel_decisions ORDER BY anex_hotel_id LIMIT 50001'),'manual_changed');
            w76_need($excluded===w76_q($db,'SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions ORDER BY anex_hotel_id,catalog_hotel_id LIMIT 50001'),'exclusions_changed');
            w76_need($anexBefore===w76_q($db,'SELECT * FROM anex_hotel_search_mappings ORDER BY anex_hotel_id LIMIT 50001'),'anex_mappings_changed');
            pm1_resolver_readback($db,$planned);return $read;
        };
        $verify();w76_save($dir.'/commit-attempt.json',['operation'=>$operation,'batch'=>PM1_BATCH,'planned_count'=>count($planned),'plan_sha256'=>w76_hash($planned),'state'=>'commit_attempt_no_replay']);
        $attempt=true;w76_need($db->commit(),'commit');$committed=true;
        $db->exec('START TRANSACTION READ ONLY');$read=$verify();$afterCoverage=w76_census($db);w76_need($db->rollBack(),'readback_end');
        return ['state'=>'committed_readback_verified','commit_attempted'=>true,'commit_completed'=>true,
            'current_candidates_evaluated'=>$examined,'rows'=>$read,'held'=>$held,'already'=>$already,'database_writes'=>count($read),'mapping_writes'=>count($read),
            'readback_verified'=>true,'effective_resolver_verified'=>true,'prior_evidence_preserved'=>true,
            'unrelated_identities_unchanged'=>true,'coverage_before'=>$beforeCoverage,'coverage_after'=>$afterCoverage,
            'new_full_triples'=>count($tripleTargets),'new_full_triple_targets'=>$tripleTargets];
    }catch(Throwable $x){
        if($db->inTransaction())try{$rollback=$db->rollBack();}catch(Throwable $ignored){}
        $count=$committed?count($planned):(($attempt||($sql&&!$rollback))?null:0);
        return ['state'=>$committed?'committed_readback_unconfirmed':($attempt?'commit_outcome_unknown_no_replay':($count===0?'rolled_back_no_writes':'write_outcome_unknown_no_replay')),
            'reason'=>preg_match('/^[a-z_]+$/D',$x->getMessage())?$x->getMessage():'writer_failed',
            'commit_attempted'=>$attempt,'commit_completed'=>$committed,'database_writes'=>$count,'mapping_writes'=>$count,
            'current_candidates_evaluated'=>$examined,'readback_verified'=>false,'rows'=>[],'held'=>$held,'already'=>$already];
    }
}
function pm1_main(array $argv): int {
    w76_need(PHP_SAPI==='cli'&&($argv[1]??'')==='--execute','disabled');
    $root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$head=(string)getenv('MATCH_SOURCE_SHA');
    $operation=basename($dir);
    w76_need(pm1_operation($operation)&&is_dir($root)&&!is_link($root)&&basename($root)==='anytoour.ru'
        && realpath($dir)===$dir&&is_dir($dir)&&preg_match('/^[a-f0-9]{40}$/D',$head)===1,'runtime_scope');
    $reservation=pm1_read(dirname($dir),$operation.'/reservation.json',null,1048576);
    w76_need(($reservation['operation']??'')===$operation&&($reservation['source_sha']??'')===$head
        &&($reservation['batch']??'')===PM1_BATCH&&($reservation['maximum_writes']??null)===3
        &&($reservation['provider_http_calls']??null)===0,'reservation');
    foreach(['execution-started.json','write-plan.json','commit-attempt.json','result.json','receipt.json'] as $f)w76_need(!file_exists($dir.'/'.$f),'no_replay');
    w76_save($dir.'/execution-started.json',['operation'=>$operation,'source_sha'=>$head,'batch'=>PM1_BATCH]);
    try{
        $entries=pm1_prepare(dirname($dir));
        $bootstrap=$root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
        w76_need(is_file($bootstrap)&&!is_link($bootstrap),'bootstrap');require_once $bootstrap;
        $out=pm1_write(v2_data_db(),$entries,$operation,$head,$dir);
    }catch(Throwable $x){$out=['state'=>'failed_before_writer','reason'=>preg_match('/^[a-z_]+$/D',$x->getMessage())?$x->getMessage():'prepare_failed','database_writes'=>0,'mapping_writes'=>0,'readback_verified'=>false];}
    $out+=['operation'=>$operation,'batch'=>PM1_BATCH,'source_sha'=>$head,'requested_candidates'=>3,'prepared_candidates'=>isset($entries)?count($entries):0,'provider_http_calls'=>0,'no_replay'=>true,'generated_at_utc'=>gmdate('c')];
    $hash=w76_save($dir.'/result.json',$out);
    w76_save($dir.'/receipt.json',['operation'=>$operation,'batch'=>PM1_BATCH,'source_sha'=>$head,'state'=>$out['state'],
        'result_sha256'=>$hash,'database_writes'=>$out['database_writes'],'mapping_writes'=>$out['mapping_writes'],
        'readback_verified'=>$out['readback_verified'],'provider_http_calls'=>0,'no_replay'=>true]);
    echo w76_json($out)."\n";
    return in_array($out['state'],['committed_readback_verified','completed_no_new_writes'],true)?0:2;
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__)exit(pm1_main($argv));
