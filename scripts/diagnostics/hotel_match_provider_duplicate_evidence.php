<?php
declare(strict_types=1);

/** MATCH #1971 read-only classification of pending Andromeda IDs whose best local target already has accepted Andromeda identity. */
function hmpd_names_from_identity(array $row, ?array $obs): array
{
    $ev=fc_evidence($row['evidence_json']??''); $src=$ev['source']??[]; if(!is_array($src))$src=[];
    return array_values(array_unique(array_filter([
        (string)($src['name']??''),(string)($src['lName']??''),(string)($obs['hotel_name']??'')
    ],static fn($v)=>trim($v)!=='')));
}

function hmpd_pair_best(array $leftNames, array $rightNames): ?array
{
    $best=null;
    foreach($leftNames as $a)foreach($rightNames as $b){
        $p=hmcf_name_pair((string)$a,(string)$b)+['source_name'=>(string)$a,'accepted_name'=>(string)$b];
        if($best===null||$p['score']>$best['score']||($p['score']===$best['score']&&$p['shared']>$best['shared'])||($p['score']===$best['score']&&$p['shared']===$best['shared']&&$p['qualifier_ok']&&!$best['qualifier_ok']))$best=$p;
    }
    return $best;
}

function hmpd_audit(PDO $db,string $strictOperation,string $operation): array
{
    $strict=msr_review($db,$strictOperation); if(($strict['status']??'')!=='completed')throw new RuntimeException('strict_failed');
    $tail=array_values(array_filter($strict['blocked_rows']??[],static fn($r)=>($r['provider']??'')==='andromeda'&&($r['reason']??'')==='cross_provider_or_supplier_evidence_needed'));
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
    try{
        [$hotels,$names,$strictNames,$broad,$places,$catalogScope]=mbr_catalog($db);[$anexLocal,$andromedaLocal]=mbr_local_sets($db);
        $latest=[];foreach($db->query("SELECT * FROM andromeda_search_hotel_observations WHERE supplier_namespace='andromeda_catalog' ORDER BY observed_at_utc DESC,external_hotel_id")->fetchAll(PDO::FETCH_ASSOC) as $o){$e=(string)$o['external_hotel_id'];if(!isset($latest[$e]))$latest[$e]=$o;}
        $pending=[];foreach($db->query("SELECT external_hotel_id,evidence_json,catalog_sha256 FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='pending' AND local_hotel_id IS NULL ORDER BY external_hotel_id")->fetchAll(PDO::FETCH_ASSOC) as $r)$pending[(string)$r['external_hotel_id']]=$r;
        $acceptedByLocal=[];$acceptedRows=[];foreach($db->query("SELECT external_hotel_id,local_hotel_id,evidence_json,catalog_sha256 FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IS NOT NULL ORDER BY local_hotel_id,external_hotel_id")->fetchAll(PDO::FETCH_ASSOC) as $r){$lid=(int)$r['local_hotel_id'];$acceptedByLocal[$lid][]=$r;$acceptedRows[]=$r;}
        $multiTargets=0;$multiIdentities=0;$maxMultiplicity=0;foreach($acceptedByLocal as $rows){$n=count($rows);$maxMultiplicity=max($maxMultiplicity,$n);if($n>1){$multiTargets++;$multiIdentities+=$n;}}
        $tokenIndex=[];foreach(array_keys($anexLocal) as $id){$id=(int)$id;if(!isset($hotels[$id]))continue;$country=(int)$hotels[$id]['country_id'];if(!isset(MBR_CORE8[$country]))continue;foreach($names[$id]??[] as $name)foreach(hmcf_tokens((string)$name) as $token)$tokenIndex[$country][$token][$id]=true;}

        $prepared=[];$blocked=[];$reasons=[];$occupiedEligible=0;$byCountry=[];
        foreach($tail as $row){
            $external=(string)$row['external_id'];$country=(int)$row['country_id'];$sourceNames=array_values(array_filter(array_map('strval',$row['source_names']??[]),static fn($v)=>trim($v)!==''));$sourcePlaces=array_values(array_filter(array_map('strval',$row['source_places']??[]),static fn($v)=>trim($v)!==''));
            $candidate=[];foreach($sourceNames as $name)foreach(hmcf_tokens($name) as $token)foreach(array_keys($tokenIndex[$country][$token]??[]) as $id)$candidate[(int)$id]=true;
            if(!$candidate)continue;$rank=hmcf_best($sourceNames,array_map('intval',array_keys($candidate)),$names);$best=$rank[0]??null;$second=$rank[1]['score']??0.0;if(!$best)continue;
            $targetId=(int)$best['id'];if(!isset($andromedaLocal[$targetId]))continue;$occupiedEligible++;
            $target=$hotels[$targetId];$margin=$best['score']-$second;$idrow=$pending[$external]??[];$ev=fc_evidence($idrow['evidence_json']??'');$src=$ev['source']??[];if(!is_array($src))$src=[];$obs=$latest[$external]??[];$coordSource=$src;if(is_array($obs))$coordSource+=$obs;$guard=mbr_target_guard($coordSource,$target);$place=fc_place($sourcePlaces,[(string)$target['region_name'],(string)$target['subregion_name']]);$directGeo=($guard['distance_m']!==null&&(int)$guard['distance_m']<=1000)||$place;
            $base=['external_id'=>$external,'country_id'=>$country,'source_names'=>$sourceNames,'source_places'=>$sourcePlaces,'target'=>mbr_row_target($target),'target_name_match'=>$best,'margin'=>round((float)$margin,6),'guard'=>$guard,'direct_geo'=>$directGeo,'accepted_occupants'=>[],'not_write_authority'=>true];
            $why=null;if($guard['coordinate_conflict'])$why='coordinate_conflict_gt_5km';elseif(!$best['qualifier_ok'])$why='target_qualifier_mismatch';elseif($best['shared']<2)$why='target_insufficient_shared_tokens';elseif(!$directGeo)$why='target_direct_geo_required';elseif($best['score']<0.75)$why='target_score_below_075';elseif($margin<0.20)$why='target_margin_below_020';
            $occupantSupport=[];foreach($acceptedByLocal[$targetId]??[] as $acc){$aid=(string)$acc['external_hotel_id'];$accNames=hmpd_names_from_identity($acc,$latest[$aid]??null);$pair=hmpd_pair_best($sourceNames,$accNames);if($pair!==null)$occupantSupport[]=['external_id'=>$aid,'names'=>$accNames,'name_match'=>$pair,'catalog_sha256'=>$acc['catalog_sha256']??null];}
            usort($occupantSupport,static fn($a,$b)=>$b['name_match']['score']<=>$a['name_match']['score'] ?: $b['name_match']['shared']<=>$a['name_match']['shared'] ?: strcmp($a['external_id'],$b['external_id']));$base['accepted_occupants']=$occupantSupport;$support=$occupantSupport[0]['name_match']??null;
            if($why===null){if(!$support)$why='accepted_occupant_name_missing';elseif(!$support['qualifier_ok'])$why='accepted_occupant_qualifier_mismatch';elseif($support['shared']<2)$why='accepted_occupant_insufficient_shared_tokens';elseif($support['score']<0.75)$why='accepted_occupant_score_below_075';}
            if($why!==null){$base['reason']=$why;$blocked[]=$base;$reasons[$why]=($reasons[$why]??0)+1;continue;}
            $base['reason']='same_provider_duplicate_identity_strong_local_and_name_geo';$base['duplicate_support']=$support;$prepared[]=$base;$byCountry[$country]=($byCountry[$country]??0)+1;
        }
        ksort($reasons);ksort($byCountry,SORT_NUMERIC);usort($prepared,static fn($a,$b)=>$a['country_id']<=>$b['country_id'] ?: $b['target_name_match']['score']<=>$a['target_name_match']['score'] ?: strcmp($a['external_id'],$b['external_id']));$db->commit();
        return ['schema'=>'hotel-match-provider-duplicate-evidence/1','status'=>'completed','operation_id'=>$operation,'mode'=>'current_db_read_only','database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'tail_examined'=>count($tail),'occupied_target_rows_examined'=>$occupiedEligible,'prepared_candidate_count'=>count($prepared),'blocked_count'=>count($blocked),'prepared_by_country'=>$byCountry,'blocked_reasons'=>$reasons,'accepted_registry_precedent'=>['accepted_identity_rows'=>count($acceptedRows),'unique_local_targets'=>count($acceptedByLocal),'multi_identity_local_targets'=>$multiTargets,'identities_on_multi_targets'=>$multiIdentities,'max_multiplicity'=>$maxMultiplicity],'catalog_scope'=>$catalogScope,'prepared_candidates'=>$prepared,'guards'=>['same_provider_occupancy_not_assumed_conflict'=>true,'pending_target_must_match_current_anex_anchor'=>true,'pending_to_local_direct_geo_required'=>true,'pending_to_local_large_margin_required'=>true,'pending_to_accepted_identity_name_support_required'=>true,'meaningful_qualifiers_preserved'=>true,'coordinate_conflict_gt_5km_blocks'=>true,'no_write_authority'=>true]];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
