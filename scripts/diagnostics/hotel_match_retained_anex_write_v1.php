<?php
declare(strict_types=1);
require_once __DIR__.'/hotel_match_live30_common4_anex_current_v4.php';
require_once __DIR__.'/hotel_match_live30_common4_anex_writer_v5.php';

/** New bounded receipt wrapper; historical fixed writers are never invoked. */
final class MatchRetainedAnexWriteV1
{
    public static function target(array $row): array
    {
        return hmc4aw_target($row)+['latitude'=>$row['latitude']??null,'longitude'=>$row['longitude']??null];
    }

    public static function run(PDO $db,array $input,string $sourceSha,?string $dir=null): array
    {
        $pairs=$input['pairs']??null;$operation=$input['operation_id']??'';
        if(!is_array($pairs)||!array_is_list($pairs)||!$pairs||count($pairs)>105
            ||preg_match('/^int-andromeda-match-retained-anex-write-[0-9]{8}-v[1-9][0-9]*$/D',$operation)!==1
            ||preg_match('/^[0-9a-f]{40}$/D',$sourceSha)!==1
            ||preg_match('/^[0-9a-f]{64}$/D',(string)($input['acquisition_evidence_sha256']??''))!==1)throw new RuntimeException('write_manifest_scope');
        $ids=[];$targetIds=[];$sourceTargets=[];
        foreach($pairs as $pair){
            $native=$pair['edge']['anex_hotel_id']??null;$tv=$pair['edge']['tv_hotel_id']??null;
            if(!is_string($native)||preg_match('/^[1-9][0-9]{0,7}$/D',$native)!==1||!is_int($tv)||$tv<1
                ||isset($ids[$native])||($pair['edge']['state']??'')!=='verified_native_pair'
                ||($pair['snapshot']['tv_hotel_id']??null)!==$tv)throw new RuntimeException('write_pair_scope');
            $ids[$native]=true;$targetIds[$tv]=true;$sourceTargets[$native][$tv]=true;
        }
        $ids=array_keys($ids);sort($ids,SORT_NUMERIC);$targetIds=array_keys($targetIds);sort($targetIds,SORT_NUMERIC);
        $ph=implode(',',array_fill(0,count($ids),'?'));$tph=implode(',',array_fill(0,count($targetIds),'?'));
        $attempted=false;$committed=false;$ready=[];$held=[];
        if($db->inTransaction())throw new RuntimeException('write_nested_transaction');
        try{
            $db->exec('SET SESSION innodb_lock_wait_timeout=20');$db->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');$db->beginTransaction();
            hmc4aw_query($db,"SELECT anex_hotel_id FROM anex_search_hotel_observations WHERE anex_hotel_id IN ($ph) ORDER BY anex_hotel_id FOR UPDATE",$ids);
            $catalog=[];foreach(hmc4aw_query($db,"SELECT id,name,country_id,country_name,region_id,region_name,subregion_id,subregion_name,category,latitude,longitude,is_active FROM catalog_hotels WHERE id IN ($tph) ORDER BY id FOR UPDATE",$targetIds)as$r)$catalog[(int)$r['id']]=$r;
            $maps=[];foreach(hmc4aw_query($db,"SELECT anex_hotel_id,catalog_hotel_id,match_class,scope,approval_policy,source_row_digest,mapping_digest,enabled FROM anex_hotel_search_mappings WHERE anex_hotel_id IN ($ph) ORDER BY anex_hotel_id FOR UPDATE",$ids)as$r)$maps[(string)$r['anex_hotel_id']]=$r;
            $decisions=[];foreach(hmc4aw_query($db,"SELECT anex_hotel_id,decision_status,catalog_hotel_id FROM anex_hotel_decisions WHERE anex_hotel_id IN ($ph) ORDER BY anex_hotel_id FOR UPDATE",$ids)as$r)$decisions[(string)$r['anex_hotel_id']]=$r;
            $exclusions=[];foreach(hmc4aw_query($db,"SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions WHERE anex_hotel_id IN ($ph) ORDER BY anex_hotel_id,catalog_hotel_id FOR UPDATE",$ids)as$r)$exclusions[(string)$r['anex_hotel_id']][(int)$r['catalog_hotel_id']]=true;
            $anchors=[];foreach(hmc4aw_query($db,"SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status,catalog_sha256,evidence_sha256,evidence_json FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IN ($tph) ORDER BY local_hotel_id,external_hotel_id FOR UPDATE",$targetIds)as$r)$anchors[(int)$r['local_hotel_id']][]=$r;
            $registry=AnyTourAnexSearchMappingRegistry::fromPdo($db);
            foreach($pairs as $pair){
                $edge=$pair['edge'];$snapshot=$pair['snapshot'];$native=$edge['anex_hotel_id'];$tv=$edge['tv_hotel_id'];
                $current=hmc4a_classify(['anex_hotel_id'=>$native,'tv_hotel_id'=>$tv],$catalog,$maps,$decisions,$exclusions,$anchors,$sourceTargets,$registry,[],[]);
                $reason=$current['writer_ready']?null:($current['status']==='current_missing_exact_key'?$current['anchor_state']:$current['status']);
                if($reason===null&&self::target($catalog[$tv])!==self::target($snapshot['target']))$reason='target_facts_drift';
                if($reason===null){
                    try{hmc4aw_validate_anchors(['catalog_hotel_id'=>$tv,'unanimous_catalog_sha256'=>$snapshot['canonical_catalog_sha256'],'anchors'=>$snapshot['anchors']],$anchors[$tv]??[]);}
                    catch(Throwable){$reason='canonical_anchor_drift';}
                }
                if($reason!==null){$held[]=['anex_hotel_id'=>$native,'tv_hotel_id'=>$tv,'reason'=>$reason];continue;}
                $evidence=['schema_version'=>1,'operation_id'=>$operation,'rule'=>'tourvisor_anex_exact_native_plus_current_canonical_anchor',
                    'source_sha'=>$sourceSha,'acquisition_evidence_sha256'=>$input['acquisition_evidence_sha256'],
                    'edge'=>$edge,'target_namespace'=>'tourvisor_hotel','target'=>self::target($catalog[$tv]),'canonical_anchors'=>$snapshot['anchors']];
                $ready[]=['anex_hotel_id'=>$native,'catalog_hotel_id'=>$tv,'source_row_digest'=>hash('sha256',hmc4aw_json($evidence))];
            }
            usort($ready,fn($a,$b)=>(int)$a['anex_hotel_id']<=>(int)$b['anex_hotel_id']);
            if(!$ready){$db->rollBack();return ['state'=>'completed_no_eligible','rows'=>[],'held'=>$held,'new_accepted_mappings'=>0,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'safe_to_write_now'=>false,'no_replay'=>true];}
            $digest=hash('sha256',hmc4aw_json($ready));
            $insert=$db->prepare("INSERT INTO anex_hotel_search_mappings (anex_hotel_id,catalog_hotel_id,match_class,scope,approval_policy,source_row_digest,mapping_digest,enabled) VALUES (?,?,'".HMC4AW_CLASS."','preview','".HMC4AW_POLICY."',?,?,1)");
            foreach($ready as $row){$insert->execute([$row['anex_hotel_id'],$row['catalog_hotel_id'],$row['source_row_digest'],$digest]);hmc4aw_need($insert->rowCount()===1,'insert_count');}
            $staged=AnyTourAnexSearchMappingRegistry::fromPdo($db);
            foreach($ready as$row)hmc4aw_need($staged->resolve('anex_online',$row['anex_hotel_id'],'preview')===$row['catalog_hotel_id'],'staged_resolver_mismatch');
            if($dir!==null)hmc4aw_save($dir.'/commit-attempt.json',['operation_id'=>$operation,'state'=>'commit_attempt_no_replay','planned'=>count($ready),'mapping_digest'=>$digest]);
            $attempted=true;hmc4aw_need($db->commit(),'commit_false');$committed=true;
            $readyIds=array_column($ready,'anex_hotel_id');$rph=implode(',',array_fill(0,count($readyIds),'?'));
            $post=[];foreach(hmc4aw_query($db,"SELECT anex_hotel_id,catalog_hotel_id,match_class,scope,approval_policy,source_row_digest,mapping_digest,enabled FROM anex_hotel_search_mappings WHERE anex_hotel_id IN ($rph) ORDER BY anex_hotel_id",$readyIds)as$r)$post[(string)$r['anex_hotel_id']]=$r;
            $resolver=AnyTourAnexSearchMappingRegistry::fromPdo($db);
            foreach($ready as$row){$r=$post[$row['anex_hotel_id']]??[];
                hmc4aw_need((int)($r['catalog_hotel_id']??0)===$row['catalog_hotel_id']&&($r['match_class']??'')===HMC4AW_CLASS
                    &&($r['approval_policy']??'')===HMC4AW_POLICY&&($r['scope']??'')==='preview'&&(int)($r['enabled']??0)===1
                    &&($r['source_row_digest']??'')===$row['source_row_digest']&&($r['mapping_digest']??'')===$digest,'post_commit_row_mismatch');
                hmc4aw_need($resolver->resolve('anex_online',$row['anex_hotel_id'],'preview')===$row['catalog_hotel_id'],'post_commit_resolver_mismatch');
            }
            return ['state'=>'committed_verified','rows'=>$ready,'held'=>$held,'new_accepted_mappings'=>count($ready),
                'database_writes'=>count($ready),'mapping_writes'=>count($ready),'supplier_calls'=>0,'mapping_digest'=>$digest,
                'readback_verified'=>true,'resolver_readback_verified'=>true,'safe_to_write_now'=>false,'no_replay'=>true];
        }catch(Throwable $error){
            try{if($db->inTransaction())$db->rollBack();}catch(Throwable){}
            $state=$committed?'post_commit_verification_failed_no_replay':($attempted?'commit_unknown_no_replay':'rolled_back_no_write');
            return ['state'=>$state,'database_writes'=>$attempted?null:0,'mapping_writes'=>$attempted?null:0,
                'supplier_calls'=>0,'planned_rows'=>$ready,'held'=>$held,'error_class'=>get_class($error),
                'error_sha256'=>hash('sha256',$error->getMessage()),'safe_to_write_now'=>false,'no_replay'=>true];
        }
    }
}

if(!defined('MATCH_RETAINED_ANEX_WRITE_LIBRARY_ONLY')){
    $home=(string)getenv('HOME');$root=$home.'/www/anytoour.ru';$dir=(string)getenv('MATCH_RETAINED_DIR');$stage=(string)getenv('MATCH_RETAINED_SOURCE_ROOT');
    if(PHP_SAPI!=='cli'||count($argv)!==1||realpath($dir)!==$dir||realpath($stage)!==$dir.'/source'
        ||!str_starts_with($dir,$home.'/.anytoour-int-executor/int-andromeda-match-retained-anex-write-')
        ||is_link($root.'/config.php'))exit(42);
    $input=json_decode((string)file_get_contents($dir.'/write-manifest.json'),true,64,JSON_THROW_ON_ERROR);
    $actual=[];foreach(($input['pairs']??[])as$p)$actual[]=[(string)($p['edge']['anex_hotel_id']??''),(int)($p['edge']['tv_hotel_id']??0)];sort($actual);
    $expected=[['9384',35297],['12901',1474],['8475',1014],['8476',1095],['14818',1015],['26591',3418],['19606',57027]];sort($expected);
    if($actual!==$expected||($input['operation_id']??'')!=='int-andromeda-match-retained-anex-write-20261020-v1'
        ||($input['acquisition_evidence_sha256']??'')!=='cb7f28c82d5df5b8f069f894ab978f198b2eebfc949034c8e371050c4f4e0aeb')exit(42);
    $_SERVER['DOCUMENT_ROOT']=$root;require $root.'/config.php';require $stage.'/v2/data/db-v1.php';
    $result=MatchRetainedAnexWriteV1::run(v2_data_db(),$input,(string)getenv('MATCH_RETAINED_SOURCE_SHA'),$dir);
    echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    exit(in_array($result['state'],['committed_verified','completed_no_eligible'],true)?0:1);
}
