<?php
declare(strict_types=1);

/**
 * Exact Biblio-Globus cross-source reconciliation for MATCH phase2 live234.
 *
 * Owner/current provider binding is Tourvisor operator 18 -> Andromeda operator 115.
 * This bridges only operator family. Hotel identity requires exact external native ID
 * equality between a Tourvisor detail-verified BG operatorLink and a fresh Andromeda
 * `operator_115` observation. No names, prices, fuzzy matching or cross-namespace
 * numeric coincidence are accepted.
 */
final class AnyTourMatchLive234BiblioCrossSourceV1
{
    private const TV_OPERATOR = 18;
    private const NAMESPACE = 'operator_115';

    public static function reconcile(
        array $tvEdges,
        array $andromedaObservations,
        array $acceptedRows,
        array $blockedPairs,
        string $freshAfterUtc
    ): array {
        $cutoff=self::time($freshAfterUtc,'fresh_cutoff_invalid');

        $observed=[];
        foreach($andromedaObservations as $row){
            if(!is_array($row))throw new InvalidArgumentException('observation_row_invalid');
            $namespace=self::identifier($row['supplier_namespace']??null,'observation_namespace_invalid');
            if($namespace!==self::NAMESPACE)continue;
            $external=self::identifier($row['external_hotel_id']??null,'observation_external_invalid');
            $seen=self::time($row['observed_at_utc']??null,'observation_time_invalid');
            if(!isset($observed[$external])||$seen>$observed[$external])$observed[$external]=$seen;
        }

        $acceptedSource=[];$acceptedTarget=[];
        foreach($acceptedRows as $row){
            if(!is_array($row))throw new InvalidArgumentException('accepted_row_invalid');
            if(($row['decision_status']??null)!=='accepted')continue;
            $namespace=self::identifier($row['supplier_namespace']??null,'accepted_namespace_invalid');
            if($namespace!==self::NAMESPACE)continue;
            $external=self::identifier($row['external_hotel_id']??null,'accepted_external_invalid');
            $local=self::positiveInt($row['local_hotel_id']??null,'accepted_local_invalid');
            if(isset($acceptedSource[$external])&&$acceptedSource[$external]!==$local)
                throw new RuntimeException('accepted_source_collision');
            $acceptedSource[$external]=$local;
            $acceptedTarget[$local][$external]=true;
        }

        $blocked=[];
        foreach($blockedPairs as $row){
            if(!is_array($row))throw new InvalidArgumentException('blocked_row_invalid');
            $namespace=self::identifier($row['supplier_namespace']??null,'blocked_namespace_invalid');
            if($namespace!==self::NAMESPACE)continue;
            $external=self::identifier($row['external_hotel_id']??null,'blocked_external_invalid');
            $local=self::positiveInt($row['local_hotel_id']??null,'blocked_local_invalid');
            $blocked[$external.'|'.$local]=true;
        }

        $prepared=[];$sourceTargets=[];
        foreach($tvEdges as $index=>$edge){
            if(!is_array($edge))throw new InvalidArgumentException('tv_edge_invalid');
            $local=self::positiveInt($edge['tv_hotel_id']??null,'tv_local_invalid');
            $operator=self::positiveInt($edge['operator_id']??null,'tv_operator_invalid');
            $row=['index'=>$index,'tv_hotel_id'=>$local,'operator_id'=>$operator,
                'namespace'=>self::NAMESPACE,'external_hotel_id'=>null,
                'state'=>null,'safe_to_write_now'=>false];
            if($operator!==self::TV_OPERATOR){$row['state']='not_biblio';$prepared[]=$row;continue;}
            if(($edge['state']??null)!=='detail_identity_verified'){$row['state']='tv_detail_not_verified';$prepared[]=$row;continue;}
            if(($edge['link_state']??null)!=='captured_single_native'){$row['state']='tv_not_single_native';$prepared[]=$row;continue;}
            $ids=$edge['positive_native_candidates']??null;
            if(!is_array($ids)||count($ids)!==1){$row['state']='tv_native_shape_invalid';$prepared[]=$row;continue;}
            $external=self::identifier($ids[0],'tv_native_invalid');$row['external_hotel_id']=$external;$row['state']='prepared';
            $sourceTargets[$external][$local]=true;$prepared[]=$row;
        }

        $rows=[];$counts=[];
        foreach($prepared as $row){
            if($row['state']!=='prepared'){$rows[]=self::counted($row,$counts);continue;}
            $local=$row['tv_hotel_id'];$external=$row['external_hotel_id'];
            if(count($sourceTargets[$external]??[])!==1){$row['state']='tv_source_collision';$rows[]=self::counted($row,$counts);continue;}
            $seen=$observed[$external]??null;
            if(!$seen instanceof DateTimeImmutable){$row['state']='andromeda_operator_115_exact_native_not_observed';$rows[]=self::counted($row,$counts);continue;}
            $row['andromeda_observed_at_utc']=$seen->format('Y-m-d H:i:s');
            if($seen<$cutoff){$row['state']='andromeda_operator_115_exact_native_stale';$rows[]=self::counted($row,$counts);continue;}
            if(isset($blocked[$external.'|'.$local])){$row['state']='manual_or_exclusion_block';$rows[]=self::counted($row,$counts);continue;}
            $acceptedLocal=$acceptedSource[$external]??null;
            if($acceptedLocal!==null&&$acceptedLocal!==$local){$row['state']='source_occupied_other';$row['accepted_local_hotel_id']=$acceptedLocal;$rows[]=self::counted($row,$counts);continue;}
            if($acceptedLocal===$local){$row['state']='resolved_same_secondary';$rows[]=self::counted($row,$counts);continue;}
            $existing=array_keys($acceptedTarget[$local]??[]);
            if($existing!==[]){sort($existing,SORT_NATURAL);$row['state']='target_namespace_occupied_other';$row['accepted_external_hotel_ids']=$existing;$rows[]=self::counted($row,$counts);continue;}
            $row['state']='writer_ready_exact_cross_source';$rows[]=self::counted($row,$counts);
        }

        ksort($counts);
        usort($rows,static fn(array $a,array $b):int=>
            [$a['tv_hotel_id'],(string)($a['external_hotel_id']??'')]
            <=>
            [$b['tv_hotel_id'],(string)($b['external_hotel_id']??'')]
        );
        $ready=array_values(array_filter($rows,static fn(array $r):bool=>$r['state']==='writer_ready_exact_cross_source'));
        return [
            'state'=>'completed_read_only_biblio_cross_source_reconcile',
            'operator_binding'=>['tourvisor'=>18,'andromeda'=>115,'namespace'=>self::NAMESPACE],
            'fresh_after_utc'=>$cutoff->format('Y-m-d H:i:s'),
            'tv_edges_checked'=>count($tvEdges),
            'andromeda_observation_keys'=>count($observed),
            'state_counts'=>$counts,
            'writer_ready_count'=>count($ready),
            'writer_ready'=>$ready,
            'rows'=>$rows,
            'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'safe_to_write_now'=>false,
        ];
    }

    private static function counted(array $row,array &$counts):array{
        $state=(string)$row['state'];$counts[$state]=($counts[$state]??0)+1;return $row;
    }
    private static function identifier(mixed $value,string $reason):string{
        if(is_int($value))$value=(string)$value;
        if(!is_string($value)||preg_match('/^[A-Za-z0-9_-]{1,128}$/D',$value)!==1)throw new InvalidArgumentException($reason);
        return $value;
    }
    private static function positiveInt(mixed $value,string $reason):int{
        if(is_int($value))$value=(string)$value;
        if(!is_string($value)||preg_match('/^[1-9][0-9]{0,9}$/D',$value)!==1||(float)$value>2147483647)throw new InvalidArgumentException($reason);
        return (int)$value;
    }
    private static function time(mixed $value,string $reason):DateTimeImmutable{
        if(!is_string($value)||preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}$/D',$value)!==1)throw new InvalidArgumentException($reason);
        $dt=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$value,new DateTimeZone('UTC'));
        if(!$dt||$dt->format('Y-m-d H:i:s')!==$value)throw new InvalidArgumentException($reason);
        return $dt;
    }
}

if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    if(($argv[1]??'')!=='--self-test'){fwrite(STDERR,"disabled\n");exit(2);}
    $edges=[
        ['tv_hotel_id'=>10,'operator_id'=>18,'state'=>'detail_identity_verified','link_state'=>'captured_single_native','positive_native_candidates'=>['701']],
        ['tv_hotel_id'=>11,'operator_id'=>18,'state'=>'detail_identity_verified','link_state'=>'captured_single_native','positive_native_candidates'=>['702']],
        ['tv_hotel_id'=>12,'operator_id'=>25,'state'=>'detail_identity_verified','link_state'=>'captured_single_native','positive_native_candidates'=>['703']],
    ];
    $obs=[
        ['supplier_namespace'=>'operator_115','external_hotel_id'=>'701','observed_at_utc'=>'2026-09-23 10:00:00'],
        ['supplier_namespace'=>'operator_115','external_hotel_id'=>'702','observed_at_utc'=>'2026-09-23 08:00:00'],
        ['supplier_namespace'=>'bgoperator','external_hotel_id'=>'701','observed_at_utc'=>'2026-09-23 10:05:00'],
    ];
    $out=AnyTourMatchLive234BiblioCrossSourceV1::reconcile($edges,$obs,[],[],'2026-09-23 09:00:00');
    $expected=['andromeda_operator_115_exact_native_stale'=>1,'not_biblio'=>1,'writer_ready_exact_cross_source'=>1];ksort($expected);
    if($out['state_counts']!==$expected||$out['writer_ready_count']!==1
        ||($out['writer_ready'][0]['tv_hotel_id']??null)!==10
        ||($out['operator_binding']['andromeda']??null)!==115
        ||($out['provider_http_calls']??-1)!==0||($out['database_writes']??-1)!==0
        ||($out['mapping_writes']??-1)!==0||($out['safe_to_write_now']??true)!==false){
        fwrite(STDERR,json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");exit(1);
    }
    echo "MATCH_LIVE234_BIBLIO_CROSS_SOURCE_V1_SELFTEST_OK\n";
}
