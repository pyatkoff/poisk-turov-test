<?php
declare(strict_types=1);

/** MATCH #1971: read-only semantic calibration of explicit Andromeda star/category fields. */
function hmstar_field(array $source): ?array
{
    foreach (['category','star','stars','starName','star_name'] as $key) {
        if (!array_key_exists($key,$source)) continue;
        $value=trim((string)$source[$key]);
        if (preg_match('/^([1-5])(?:\s*(?:\*|★|stars?))?$/iu',$value,$m)) {
            return ['field'=>$key,'raw'=>$value,'category'=>(int)$m[1]];
        }
    }
    return null;
}

function hmstar_selected(array $identitySource, ?array $observation): ?array
{
    $parsed=hmstar_field($identitySource);
    if ($parsed!==null) return $parsed+['origin'=>'identity_evidence'];
    if ($observation!==null) {
        $parsed=hmstar_field($observation);
        if ($parsed!==null) return $parsed+['origin'=>'latest_search_observation'];
    }
    return null;
}

function hmstar_semantic_status(int $samples, int $agreements): string
{
    if ($samples<20) return 'insufficient_sample';
    $rate=$samples ? $agreements/$samples : 0.0;
    if ($samples>=50 && $rate>=0.95) return 'strong_consistent';
    if ($rate>=0.85) return 'moderate_consistent';
    return 'inconsistent_with_local_category';
}

function hmstar_add(array &$bucket, int $sourceCategory, int $targetCategory): void
{
    $bucket['samples']=($bucket['samples']??0)+1;
    if ($sourceCategory===$targetCategory) $bucket['agreements']=($bucket['agreements']??0)+1;
    else $bucket['mismatches']=($bucket['mismatches']??0)+1;
    $delta=(string)($sourceCategory-$targetCategory);
    $bucket['delta_counts'][$delta]=($bucket['delta_counts'][$delta]??0)+1;
}

function hmstar_finish(array $bucket): array
{
    $samples=(int)($bucket['samples']??0);$agreements=(int)($bucket['agreements']??0);
    $deltas=$bucket['delta_counts']??[];ksort($deltas,SORT_NUMERIC);
    return [
        'samples'=>$samples,
        'agreements'=>$agreements,
        'mismatches'=>(int)($bucket['mismatches']??0),
        'agreement_rate'=>$samples?round($agreements/$samples,6):null,
        'semantic_status'=>hmstar_semantic_status($samples,$agreements),
        'delta_counts'=>$deltas,
    ];
}

function hmstar_audit(PDO $db, array $strict, string $operation): array
{
    if (($strict['status']??'')!=='completed' || !is_array($strict['blocked_rows']??null)) throw new RuntimeException('strict_blocked_rows_missing');
    $blocked=array_values(array_filter($strict['blocked_rows'],static fn($r)=>($r['provider']??'')==='andromeda'&&($r['reason']??'')==='numeric_star_guard_mismatch'));

    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $db->exec('START TRANSACTION READ ONLY');
    try {
        $latest=[];
        foreach($db->query("SELECT * FROM andromeda_search_hotel_observations WHERE supplier_namespace='andromeda_catalog' ORDER BY observed_at_utc DESC,external_hotel_id")->fetchAll(PDO::FETCH_ASSOC) as $row){$id=(string)$row['external_hotel_id'];if(!isset($latest[$id]))$latest[$id]=$row;}
        $pending=[];
        foreach($db->query("SELECT external_hotel_id,evidence_json,catalog_sha256 FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='pending' AND local_hotel_id IS NULL")->fetchAll(PDO::FETCH_ASSOC) as $row)$pending[(string)$row['external_hotel_id']]=$row;

        $calibration=[];$fieldTotals=[];$acceptedExamined=0;$acceptedParsed=0;$acceptedNoTargetCategory=0;
        $sql="SELECT i.external_hotel_id,i.evidence_json,h.country_id,h.category target_category FROM andromeda_hotel_identities i JOIN catalog_hotels h ON h.id=i.local_hotel_id WHERE i.supplier_namespace='andromeda_catalog' AND i.decision_status='accepted' AND i.local_hotel_id IS NOT NULL AND h.country_id IN (1,2,4,8,9,10,12,16) ORDER BY i.external_hotel_id";
        foreach($db->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $row){
            $acceptedExamined++;$target=$row['target_category'];if($target===null){$acceptedNoTargetCategory++;continue;}
            $ev=fc_evidence($row['evidence_json']??'');$src=$ev['source']??[];if(!is_array($src))$src=[];$id=(string)$row['external_hotel_id'];
            $parsed=hmstar_selected($src,$latest[$id]??null);if($parsed===null)continue;$acceptedParsed++;
            $country=(int)$row['country_id'];$key=$parsed['origin'].':'.$parsed['field'];
            hmstar_add($calibration[$key][$country],(int)$parsed['category'],(int)$target);
            hmstar_add($fieldTotals[$key],(int)$parsed['category'],(int)$target);
        }

        $calibrationOut=[];
        foreach($calibration as $key=>$byCountry){ksort($byCountry,SORT_NUMERIC);foreach($byCountry as $country=>$bucket)$calibrationOut[$key][(string)$country]=hmstar_finish($bucket);}
        ksort($calibrationOut,SORT_STRING);$fieldTotalsOut=[];ksort($fieldTotals,SORT_STRING);foreach($fieldTotals as $key=>$bucket)$fieldTotalsOut[$key]=hmstar_finish($bucket);

        $rows=[];$statusCounts=[];$fieldCounts=[];$countryCounts=[];$unparsed=0;
        foreach($blocked as $row){
            $external=(string)($row['external_id']??'');$country=(int)($row['country_id']??0);$idrow=$pending[$external]??[];$ev=fc_evidence($idrow['evidence_json']??'');$src=$ev['source']??[];if(!is_array($src))$src=[];
            $parsed=hmstar_selected($src,$latest[$external]??null);$targetCategory=$row['target']['category']??null;
            if($parsed===null){$unparsed++;$status='source_field_not_reproducible';$key=null;$cal=null;}
            else{
                $key=$parsed['origin'].':'.$parsed['field'];$countryCal=$calibrationOut[$key][(string)$country]??null;$globalCal=$fieldTotalsOut[$key]??null;
                $cal=is_array($countryCal)&&($countryCal['samples']??0)>=20?$countryCal:$globalCal;
                $status=is_array($cal)?(string)$cal['semantic_status']:'insufficient_sample';$fieldCounts[$key]=($fieldCounts[$key]??0)+1;
            }
            $statusCounts[$status]=($statusCounts[$status]??0)+1;$countryCounts[$country]=($countryCounts[$country]??0)+1;
            $rows[]=[
                'external_id'=>$external,'country_id'=>$country,'source_category'=>$row['source_category']??($parsed['category']??null),'target_category'=>$targetCategory,
                'field'=>$parsed['field']??null,'field_origin'=>$parsed['origin']??null,'raw_value'=>$parsed['raw']??null,
                'calibration'=>$cal,'semantic_status'=>$status,
                'target'=>$row['target']??null,'source_names'=>$row['source_names']??[],'source_places'=>$row['source_places']??[],
                'not_write_authority'=>true,
            ];
        }
        ksort($statusCounts);ksort($fieldCounts);ksort($countryCounts,SORT_NUMERIC);usort($rows,static fn($a,$b)=>$a['country_id']<=>$b['country_id'] ?: strcmp($a['external_id'],$b['external_id']));
        $db->commit();
        return [
            'schema'=>'hotel-match-andromeda-star-semantics/1','status'=>'completed','operation_id'=>$operation,'mode'=>'current_db_read_only',
            'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,
            'accepted_examined'=>$acceptedExamined,'accepted_parsed_star'=>$acceptedParsed,'accepted_without_target_category'=>$acceptedNoTargetCategory,
            'star_mismatch_blocked_rows'=>count($blocked),'blocked_source_field_unreproducible'=>$unparsed,
            'blocked_semantic_status_counts'=>$statusCounts,'blocked_field_counts'=>$fieldCounts,'blocked_country_counts'=>$countryCounts,
            'calibration_by_field_country'=>$calibrationOut,'calibration_by_field_global'=>$fieldTotalsOut,'blocked_rows'=>$rows,
            'guards'=>['starKey_excluded'=>true,'star_difference_remains_signal_not_identity'=>true,'no_mapping_authority'=>true,'current_accepted_registry_calibration'=>true],
        ];
    } catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
