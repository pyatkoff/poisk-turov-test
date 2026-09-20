<?php
declare(strict_types=1);

require_once dirname(__DIR__,2).'/app/integrations/anex-search-mapping-registry.php';

/** MATCH queue projection. Identity authority stays in the canonical resolver. */
final class AnyTourMatchAnexEffectiveCoverage
{
    private const MAX_NATIVE_IDS = 50000;

    /** Caller must provide its existing consistent READ ONLY or guarded snapshot. */
    public static function fromPdo(PDO $db): array
    {
        $statement=$db->query('SELECT anex_hotel_id FROM anex_hotel_search_mappings UNION SELECT anex_hotel_id FROM anex_hotel_decisions LIMIT 50001');
        if($statement===false)throw new RuntimeException('match_anex_coverage_enumeration_failed');
        $ids=$statement->fetchAll(PDO::FETCH_COLUMN);
        return self::fromRegistry(AnyTourAnexSearchMappingRegistry::fromPdo($db),$ids);
    }

    /** No policy SQL, class allowlist or manual/exclusion fallback belongs here. */
    public static function fromRegistry(AnyTourAnexSearchMappingRegistry $registry,array $nativeIds): array
    {
        if(count($nativeIds)>self::MAX_NATIVE_IDS)throw new UnexpectedValueException('match_anex_coverage_limit');
        $seen=[];$byNative=[];$byLocal=[];
        foreach($nativeIds as $native){
            if(is_int($native))$native=(string)$native;
            if(!is_string($native)||preg_match('/^[1-9][0-9]{0,7}$/D',$native)!==1)throw new UnexpectedValueException('match_anex_coverage_invalid_id');
            if(isset($seen[$native]))continue;
            $seen[$native]=true;
            $local=$registry->resolve('anex_online',$native,'preview');
            if($local===null)continue;
            $byNative[(int)$native]=$local;
            $byLocal[$local][(int)$native]=true;
        }
        // A partial enumeration must never turn omitted accepted IDs into missing work.
        if(count($byNative)!==$registry->count())throw new UnexpectedValueException('match_anex_coverage_incomplete_enumeration');
        ksort($byNative,SORT_NUMERIC);ksort($byLocal,SORT_NUMERIC);
        foreach($byLocal as &$ids)ksort($ids,SORT_NUMERIC);
        unset($ids);
        return ['native_count'=>count($byNative),'local_count'=>count($byLocal),'by_native'=>$byNative,'by_local'=>$byLocal];
    }
}
