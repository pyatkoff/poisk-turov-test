<?php
declare(strict_types=1);
require_once __DIR__.'/hotel_match_live30_raw_operator_bridge_v79.php';
require_once __DIR__.'/hotel_match_raw_native_pending11_v78.php';
require_once __DIR__.'/hotel_match_current15_samo82_anex_v74.php';

const A83_OP='hotel-match-v82-exact-native-join-audit-1971-20260927-v83';
const A83_V82_OP='hotel-match-current100-dedicated-account-acquire-1971-20260927-v82';
const A83_V82_SHA='dfa4527126e25840b79aa56787503051041ae27a0444ec4e028781b14bf14898';
const A83_V77_SHA='d40fbe2e0240a5df194ac838376a3425a8f4e80f2426a757560fe369011107f7';
const A83_OP_TO_NS=[18=>'operator_115',25=>'operator_315',43=>'operator_342'];

function a83_need(bool $ok,string $reason):void{if(!$ok)throw new RuntimeException($reason);}
function a83_edge(array $e):bool{
    return ($e['state']??'')==='detail_identity_verified'
        &&($e['link_state']??'')==='captured_single_native'
        &&is_array($e['positive_native_candidates']??null)
        &&count($e['positive_native_candidates'])===1
        &&in_array((int)($e['operator_id']??0),[13,18,25,43],true)
        &&(int)($e['tv_hotel_id']??0)>0;
}
function a83_sha(mixed $v):bool{return is_string($v)&&preg_match('/^[0-9a-f]{64}$/D',$v)===1;}
function a83_v82(string $ops):array{
    $r=o79_read($ops.'/'.A83_V82_OP.'/result.json',A83_V82_SHA,16777216);
    a83_need(($r['operation']??'')===A83_V82_OP&&($r['state']??'')==='completed_read_only','v82_state');
    a83_need(($r['scope_count']??0)===100&&($r['searched_hotels']??0)===100,'v82_scope');
    a83_need(($r['tourvisor_account']??'')==='TOURVISOR_ANEX_JWT'&&($r['operator_filter_sent']??null)===false,'v82_account');
    a83_need(($r['continue_calls']??-1)===0&&($r['dates_calls']??-1)===0&&($r['database_writes']??-1)===0&&($r['mapping_writes']??-1)===0,'v82_readonly');
    $edges=[];$byKey=[];$byHotelOp=[];
    foreach($r['edges']??[] as $i=>$e){if(!is_array($e)||!a83_edge($e))continue;$op=(int)$e['operator_id'];$id=(int)$e['tv_hotel_id'];$native=(string)$e['positive_native_candidates'][0];
        a83_need(preg_match('/^[1-9][0-9]{0,19}$/D',$native)===1,'native_shape');
        foreach(['search_id_sha256','tour_id_sha256','operator_link_sha256']as$k)a83_need(a83_sha($e[$k]??null),'edge_provenance');
        $key=$op.'|'.$native;a83_need(!isset($byKey[$key]),'duplicate_operator_native_in_v82');a83_need(!isset($byHotelOp[$id.'|'.$op]),'duplicate_hotel_operator_in_v82');
        $e['_pointer']='/edges/'.$i;$e['_native']=$native;$byKey[$key]=$id;$byHotelOp[$id.'|'.$op]=$native;$edges[]=$e;
    }
    a83_need(count($edges)===43,'v82_single_native_count');
    return [$r,$edges];
}
function a83_fact_proof(string $ops,array $fact,string $cat,string $ns,string $native):array{
    $proof=null;
    foreach($fact['evidence']??[] as$p){
        if(!is_array($p)||!isset($p['source_file'],$p['sha256'],$p['json_pointer']))continue;
        if(!preg_match('~^operations/(hotel-match-[a-zA-Z0-9_-]+)/((?:evidence-private/[a-zA-Z0-9_.-]+|raw-[a-zA-Z0-9_.-]+)\.json)$~D',(string)$p['source_file'],$m))continue;
        $path=$ops.'/'.$m[1].'/'.$m[2];$root=realpath($ops);$real=realpath($path);a83_need($root!==false&&$real!==false&&str_starts_with($real,$root.'/'),'raw_path');
        $raw=o79_read($path,(string)$p['sha256'],16777216);$row=o79_pointer($raw,(string)$p['json_pointer']);a83_need(o79_fact($row,$cat,$ns,$native),'raw_identity');$proof=$p;break;
    }
    a83_need($proof!==null,'no_raw_proof');return $proof;
}
function a83_index_identities(array $rows):array{$out=[];foreach($rows as$r){$k=(string)$r['supplier_namespace'].'|'.(string)$r['external_hotel_id'];a83_need(!isset($out[$k]),'duplicate_identity');$out[$k]=$r;}return$out;}
function a83_target_reasons(int $id,array $hot,array $live,array $manualTarget,array $excludedTarget):array{
    $why=[];$h=$hot[$id]??null;if(!$h||(int)($h['is_active']??0)!==1)$why[]='target_inactive_or_missing';
    if($h&&preg_match('/^(?:Россия|Абхазия|Russia|Abkhazia|Russian Federation)$/iu',(string)$h['country_name']))$why[]='excluded_country';
    if(empty($live[$id]))$why[]='outside_tv_live30';if(!empty($manualTarget[$id]))$why[]='manual_target_protected';if(!empty($excludedTarget[$id]))$why[]='excluded_target_protected';return$why;
}
function a83_run(PDO $db,string $ops):array{
    [$v82,$edges]=a83_v82($ops);$v77=o79_read($ops.'/hotel-match-live30-retained-native-union-1971-20260927-v77/result.json',A83_V77_SHA,67108864);
    a83_need(($v77['state']??'')==='completed_retained_native_scan','v77_state');
    $facts=[];foreach($v77['native_facts']??[] as$f){$ns=(string)($f['supplier_namespace']??'');$n=(string)($f['native_id']??'');$cat=(string)($f['catalog_id']??'');if($ns!==''&&$n!==''&&$cat!=='')$facts[$ns][$n][$cat]=$f;}
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('START TRANSACTION READ ONLY');
    try{
        $all=a74_q($db,'SELECT * FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id LIMIT 50001');$idx=a83_index_identities($all);
        $targets=[];foreach($all as$r)if(($r['supplier_namespace']??'')==='andromeda_catalog'&&$r['local_hotel_id']!==null)$targets[(int)$r['local_hotel_id']][]=$r;
        $ids=array_values(array_unique(array_map(fn($e)=>(int)$e['tv_hotel_id'],$edges)));sort($ids,SORT_NUMERIC);$ph=implode(',',array_fill(0,count($ids),'?'));
        $hot=[];foreach(a74_q($db,"SELECT id,name,country_id,country_name,category,is_active FROM catalog_hotels WHERE id IN ($ph) ORDER BY id",$ids)as$r)$hot[(int)$r['id']]=$r;
        $live=[];foreach(a74_q($db,"SELECT DISTINCT hotel_id FROM tour_operator_identity_observations WHERE hotel_id IN ($ph) AND last_seen_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 30 DAY)",$ids)as$r)$live[(int)$r['hotel_id']]=true;
        $manualTarget=[];foreach(a74_q($db,"SELECT catalog_hotel_id FROM anex_hotel_decisions WHERE catalog_hotel_id IN ($ph)",$ids)as$r)if($r['catalog_hotel_id']!==null)$manualTarget[(int)$r['catalog_hotel_id']]=true;
        $excludedTarget=[];foreach(a74_q($db,"SELECT catalog_hotel_id FROM anex_review_pair_exclusions WHERE catalog_hotel_id IN ($ph)",$ids)as$r)if($r['catalog_hotel_id']!==null)$excludedTarget[(int)$r['catalog_hotel_id']]=true;

        $samo=[];$samoHolds=[];$samoAlready=[];
        foreach($edges as$e){$op=(int)$e['operator_id'];if($op===13)continue;$id=(int)$e['tv_hotel_id'];$native=$e['_native'];$ns=A83_OP_TO_NS[$op];$why=a83_target_reasons($id,$hot,$live,$manualTarget,$excludedTarget);$cats=$facts[$ns][$native]??[];
            if(count($cats)!==1)$why[]=count($cats)===0?'no_samo_native_fact':'samo_native_ambiguous';$cat=count($cats)===1?(string)array_key_first($cats):null;$fact=$cat!==null?$cats[$cat]:null;$proof=null;
            if($fact!==null){try{$proof=a83_fact_proof($ops,$fact,$cat,$ns,$native);}catch(Throwable$x){$why[]=preg_match('/^[a-z_]+$/D',$x->getMessage())?$x->getMessage():'raw_proof_invalid';}}
            $source=$cat!==null?($idx['andromeda_catalog|'.$cat]??null):null;$oprow=$idx[$ns.'|'.$native]??null;
            if($cat!==null){if(!$source)$why[]='canonical_source_missing';elseif(!o79_valid($source))$why[]='canonical_source_evidence_invalid';elseif(($source['decision_status']??'')==='accepted'&&$source['local_hotel_id']!==null){if((int)$source['local_hotel_id']===$id)$samoAlready[]=['local_hotel_id'=>$id,'operator_id'=>$op,'namespace'=>$ns,'native_id'=>$native,'catalog_id'=>$cat,'status'=>'already_primary_same'];else$why[]='canonical_source_other_target';}elseif(($source['decision_status']??'')!=='pending'||$source['local_hotel_id']!==null||!w78_auto($source))$why[]='canonical_source_protected';
                foreach($targets[$id]??[]as$t)if((string)$t['external_hotel_id']!==$cat&&($t['decision_status']??'')==='accepted'){$why[]='target_catalog_occupied';break;}
            }
            if($oprow){if(!o79_valid($oprow))$why[]='operator_identity_invalid';elseif(($oprow['decision_status']??'')==='accepted'&&$oprow['local_hotel_id']!==null){if((int)$oprow['local_hotel_id']!==$id)$why[]='operator_identity_other_target';}elseif(($oprow['decision_status']??'')==='pending'&&$oprow['local_hotel_id']===null){if(!o79_auto($oprow))$why[]='operator_identity_pending_protected';}else$why[]='operator_identity_protected';}
            $why=array_values(array_unique($why));if($why){$samoHolds[]=['local_hotel_id'=>$id,'operator_id'=>$op,'namespace'=>$ns,'native_id'=>$native,'catalog_id'=>$cat,'reasons'=>$why];continue;}
            if($source&&($source['decision_status']??'')==='accepted'&&(int)$source['local_hotel_id']===$id)continue;
            $samo[]=['local_hotel_id'=>$id,'operator_id'=>$op,'namespace'=>$ns,'native_id'=>$native,'catalog_id'=>$cat,'hotel'=>$hot[$id],'v82_edge'=>$e,'v77_fact'=>$fact,'raw_proof'=>$proof,'operator_identity_state'=>$oprow?['decision_status'=>$oprow['decision_status'],'local_hotel_id'=>$oprow['local_hotel_id'],'catalog_sha256'=>$oprow['catalog_sha256'],'evidence_sha256'=>$oprow['evidence_sha256']]:null];
        }

        require_once __DIR__.'/hotel_match_anex_effective_coverage.php';$maps=a74_q($db,'SELECT anex_hotel_id,catalog_hotel_id,match_class,scope,approval_policy,source_row_digest,mapping_digest,enabled FROM anex_hotel_search_mappings ORDER BY anex_hotel_id LIMIT 50001');
        $dec=a74_q($db,'SELECT anex_hotel_id,catalog_hotel_id,decision_status FROM anex_hotel_decisions ORDER BY anex_hotel_id LIMIT 50001');$exc=a74_q($db,'SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions ORDER BY anex_hotel_id,catalog_hotel_id LIMIT 50001');
        $c=['hotels'=>$hot,'live'=>$live,'mapping_source'=>[],'mapping_target'=>[],'manual_source'=>[],'manual_target'=>[],'excluded_source'=>[],'op5_source'=>[],'effective'=>AnyTourMatchAnexEffectiveCoverage::fromPdo($db)];
        foreach($maps as$r){$c['mapping_source'][(string)$r['anex_hotel_id']][]=$r;$c['mapping_target'][(int)$r['catalog_hotel_id']][]=$r;}foreach($dec as$r){$c['manual_source'][(string)$r['anex_hotel_id']][]=$r;if($r['catalog_hotel_id']!==null)$c['manual_target'][(int)$r['catalog_hotel_id']][]=$r;}foreach($exc as$r)$c['excluded_source'][(string)$r['anex_hotel_id']][]=$r;
        foreach($all as$r)if(($r['supplier_namespace']??'')==='operator_5')$c['op5_source'][(string)$r['external_hotel_id']][]=$r;
        $anex=[];$anexHolds=[];$anexAlready=[];
        foreach($edges as$e){if((int)$e['operator_id']!==13)continue;$id=(int)$e['tv_hotel_id'];$native=$e['_native'];$why=[];if(!in_array(strtolower((string)$e['operator_link_host']),['agent.anextour.ru','online.anextour.ru','anextour.ru'],true))$why[]='anex_host';if(!in_array('hotellist',array_map('strtolower',$e['query_keys']??[]),true))$why[]='anex_hotellist_missing';$d=a74_anex($id,$native,$c);$why=array_values(array_unique(array_merge($why,$d['reasons'])));
            if($d['status']==='already_effective_same'&&!$why){$anexAlready[]=['local_hotel_id'=>$id,'anex_hotel_id'=>$native,'status'=>'already_effective_same'];continue;}
            if($d['status']!=='source_missing_needs_identity_proof'||$why){$anexHolds[]=['local_hotel_id'=>$id,'anex_hotel_id'=>$native,'status'=>$d['status'],'reasons'=>$why];continue;}
            $anex[]=['local_hotel_id'=>$id,'anex_hotel_id'=>$native,'hotel'=>$hot[$id],'v82_edge'=>$e];
        }
        $coverage=w76_census($db);$db->rollBack();
        return ['state'=>'completed_read_only_v82_join_audit','operation'=>A83_OP,'v82_result_sha256'=>A83_V82_SHA,'v77_result_sha256'=>A83_V77_SHA,'captured_single_native'=>count($edges),'captured_by_operator'=>array_count_values(array_map(fn($e)=>(string)$e['operator_id'],$edges)),'safe_samo_primary_count'=>count($samo),'safe_direct_anex_count'=>count($anex),'safe_total_primary_count'=>count($samo)+count($anex),'samo_candidates'=>$samo,'direct_anex_candidates'=>$anex,'samo_already'=>$samoAlready,'direct_anex_already'=>$anexAlready,'samo_holds'=>$samoHolds,'direct_anex_holds'=>$anexHolds,'coverage_current'=>$coverage,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'safe_to_write_now'=>false,'generated_at_utc'=>gmdate('c')];
    }catch(Throwable$x){if($db->inTransaction())$db->rollBack();throw$x;}
}
if(($argv[1]??'')==='--self-test'){a83_need(count(A83_OP_TO_NS)===3&&A83_OP_TO_NS[18]==='operator_115'&&A83_OP_TO_NS[25]==='operator_315'&&A83_OP_TO_NS[43]==='operator_342','mapping');echo "A83_AUDIT_OK\n";exit;}
a83_need(($argv[1]??'')==='--execute','disabled');$root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');a83_need(is_dir($root)&&basename($root)==='anytoour.ru'&&is_dir($dir)&&basename($dir)===A83_OP,'scope');foreach(['result.json','receipt.json']as$f)a83_need(!file_exists($dir.'/'.$f),'no_replay');require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');$out=a83_run(v2_data_db(),dirname($dir));$sha=o79_save($dir.'/result.json',$out);o79_save($dir.'/receipt.json',['operation'=>A83_OP,'state'=>$out['state'],'result_sha256'=>$sha,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'no_replay'=>true]);echo o79_json(array_diff_key($out,['samo_candidates'=>true,'direct_anex_candidates'=>true,'samo_holds'=>true,'direct_anex_holds'=>true]))."\n";
