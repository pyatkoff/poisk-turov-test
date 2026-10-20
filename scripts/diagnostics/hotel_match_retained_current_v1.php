<?php
declare(strict_types=1);
require_once __DIR__.'/hotel_match_anex_effective_coverage.php';
require_once __DIR__.'/hotel_match_live30_common4_anex_current_v4.php';

/** Current identities/context for a trusted saved cohort, never a supplier call. */
final class MatchRetainedCurrentV1
{
    public static function query(PDO $db,string $sql,array $args=[]): array
    {
        $q=$db->prepare($sql);$q->execute($args);return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function run(PDO $db,array $scope,string $today): array
    {
        $ids=$scope['tv_hotel_ids']??null;
        if(!is_array($ids)||!array_is_list($ids)||!$ids||count($ids)>10000
            ||count(array_unique($ids))!==count($ids))throw new RuntimeException('current_scope');
        foreach($ids as $id)if(!is_int($id)||$id<1)throw new RuntimeException('current_scope_id');
        $sorted=$ids;sort($sorted,SORT_NUMERIC);
        if($sorted!==$ids||preg_match('/^[0-9a-f]{64}$/D',(string)($scope['parent_receipt_sha256']??''))!==1
            ||preg_match('/^\d{4}-\d{2}-\d{2}$/D',$today)!==1)throw new RuntimeException('current_scope_binding');
        if($db->inTransaction())throw new RuntimeException('current_nested_transaction');
        $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $db->exec('SET TRANSACTION READ ONLY');$db->beginTransaction();
        try {
            $direct=AnyTourMatchAnexEffectiveCoverage::fromPdo($db);
            $targets=[];$anchors=[];$contexts=[];$providers=[];$contextRows=0;
            foreach(array_chunk($ids,250) as $chunk){
                $ph=implode(',',array_fill(0,count($chunk),'?'));
                foreach(self::query($db,"SELECT id,name,country_id,country_name,region_id,region_name,subregion_id,subregion_name,category,latitude,longitude,is_active FROM catalog_hotels WHERE id IN ($ph) ORDER BY id",$chunk) as $r)$targets[(int)$r['id']]=$r;
                $rows=self::query($db,"SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status,catalog_sha256,evidence_sha256,evidence_json FROM andromeda_hotel_identities WHERE local_hotel_id IN ($ph) ORDER BY supplier_namespace,external_hotel_id",$chunk);
                foreach($rows as $r){
                    $id=(int)$r['local_hotel_id'];$ns=(string)$r['supplier_namespace'];
                    if($ns==='andromeda_catalog'&&$r['decision_status']==='accepted')$anchors[$id][]=$r;
                    if($r['decision_status']==='accepted')$providers[$id][$ns][]=(string)$r['external_hotel_id'];
                }
                $since=(new DateTimeImmutable($today.' UTC'))->modify('-30 days')->format('Y-m-d');
                $rows=self::query($db,"SELECT hotel_id,departure_id,country_id,departure_date,nights,adults,children_count,child_ages_signature,observed_at,source FROM (SELECT hotel_id,departure_id,country_id,departure_date,nights,adults,children_count,child_ages_signature,observed_at,source,ROW_NUMBER() OVER (PARTITION BY hotel_id ORDER BY (source='user_search') DESC,observed_at DESC,departure_date ASC) AS rn FROM tour_price_observations WHERE hotel_id IN ($ph) AND departure_date>=? AND observed_at>=?) AS ranked WHERE rn=1 ORDER BY hotel_id",[...$chunk,$today,$since]);
                $contextRows+=count($rows);
                foreach($rows as $r)if(!isset($contexts[(int)$r['hotel_id']]))$contexts[(int)$r['hotel_id']]=$r;
            }
            $rows=[];$states=[];$ready=0;$covered=0;$eligibleCovered=0;$byNamespace=[];
            foreach($ids as $id){
                $h=$targets[$id]??null;$native=array_map('intval',array_keys($direct['by_local'][$id]??[]));
                if($native)$covered++;
                $anchor=hmc4a_anchor_state($anchors[$id]??[]);$ctx=$contexts[$id]??null;
                $state='missing_direct_anex';
                if($h===null)$state='target_missing';
                elseif((int)$h['is_active']!==1||hmc4a_excluded((string)$h['country_name']))$state='target_ineligible';
                elseif($native){$state='already_effectively_linked';$eligibleCovered++;}
                elseif($anchor['state']!=='canonical_anchor_ok')$state=$anchor['state'];
                elseif($ctx===null)$state='future_context_missing';
                elseif((int)$ctx['country_id']!==(int)$h['country_id']||(int)$ctx['departure_id']<1
                    ||(int)$ctx['nights']<1||(int)$ctx['nights']>28||(int)$ctx['adults']<1||(int)$ctx['adults']>6)$state='future_context_invalid';
                else {$state='missing_direct_anex_with_future_context_and_anchor';$ready++;}
                $states[$state]=($states[$state]??0)+1;
                foreach(($providers[$id]??[])as $ns=>$values)$byNamespace[$ns][$id]=true;
                $rows[]=['tv_hotel_id'=>$id,'state'=>$state,'target'=>$h,'direct_anex_native_ids'=>$native,
                    'accepted_provider_ids'=>$providers[$id]??[],'anchor_state'=>$anchor['state'],
                    'canonical_catalog_sha256'=>$anchor['catalog_sha256'],'anchors'=>$anchor['anchors'],
                    'future_context'=>$ctx,'acquisition_ready'=>$state==='missing_direct_anex_with_future_context_and_anchor'];
            }
            $db->commit();ksort($states);ksort($byNamespace);
            return ['state'=>'current_retained_read_only','captured_at_utc'=>gmdate('c'),
                'database_snapshot'=>'repeatable-read-read-only','parent_receipt_sha256'=>$scope['parent_receipt_sha256'],
                'cohort_hotels'=>count($ids),'current_target_rows'=>count($targets),
                'direct_anex_global_effective_native_ids'=>$direct['native_count'],
                'direct_anex_global_effective_local_hotels'=>$direct['local_count'],
                'direct_anex_effective_on_cohort'=>$covered,'direct_anex_effective_on_eligible_cohort'=>$eligibleCovered,'candidate_state_counts'=>$states,
                'accepted_provider_unique_hotels_on_cohort'=>array_map('count',$byNamespace),
                'future_context_rows'=>$contextRows,'acquisition_ready_count'=>$ready,'rows'=>$rows,
                'supplier_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'safe_to_write_now'=>false];
        }finally{if($db->inTransaction())$db->rollBack();}
    }
}

if(!defined('MATCH_RETAINED_CURRENT_LIBRARY_ONLY')){
    try{
        $home=(string)getenv('HOME');$root=$home.'/www/anytoour.ru';
        $dir=(string)getenv('MATCH_RETAINED_DIR');$stage=(string)getenv('MATCH_RETAINED_SOURCE_ROOT');
        if(PHP_SAPI!=='cli'||count($argv)!==1||realpath($root)!==$root||realpath($dir)!==$dir
            ||!str_starts_with($dir,$home.'/.anytoour-int-executor/int-andromeda-match-retained-current-')
            ||realpath($stage)!==$dir.'/source'||is_link($root.'/config.php'))throw new RuntimeException('current_paths');
        $scope=json_decode((string)file_get_contents($dir.'/current-scope.json'),true,32,JSON_THROW_ON_ERROR);
        $_SERVER['DOCUMENT_ROOT']=$root;require $root.'/config.php';require $stage.'/v2/data/db-v1.php';
        echo json_encode(MatchRetainedCurrentV1::run(v2_data_db(),$scope,gmdate('Y-m-d')),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    }catch(Throwable $e){echo json_encode(['state'=>'failed_read_only','error_class'=>get_class($e),'error_sha256'=>hash('sha256',$e->getMessage())]);exit(1);}
}
