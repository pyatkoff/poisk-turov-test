<?php
declare(strict_types=1);

/** MATCH #1971: read-only fuzzy bridge over CURRENT accepted ANEX->local anchors. */
function hmcf_tokens($value): array
{
    $generic = array_fill_keys(['hotel','hotels','отель','отели','resort','resorts','резорт','ресорт','spa','спа','the','and','ex','former','formerly'], true);
    $out = [];
    foreach (explode(' ', fc_norm((string)$value)) as $token) {
        if ($token === '' || isset($generic[$token])) continue;
        $out[$token] = true;
    }
    return array_keys($out);
}

function hmcf_qualifiers(array $tokens): array
{
    $q = ['annex','annexe','wing','beach','garden','gardens','north','south','east','west','adult','adults','family','only','suite','suites','villa','villas','apart','apartment','apartments','club','marina','city','island','bay','central','pool','sea','prestige','aquamarine'];
    $set = array_fill_keys($tokens, true); $out=[];
    foreach ($q as $token) if (isset($set[$token])) $out[$token]=true;
    return array_keys($out);
}

function hmcf_name_pair(string $a, string $b): array
{
    $left=hmcf_tokens($a); $right=hmcf_tokens($b);
    $l=array_fill_keys($left,true); $r=array_fill_keys($right,true);
    $shared=count(array_intersect_key($l,$r)); $union=count($l+$r);
    $score=$union ? $shared/$union : 0.0;
    $ql=hmcf_qualifiers($left); $qr=hmcf_qualifiers($right);
    sort($ql,SORT_STRING); sort($qr,SORT_STRING);
    return ['score'=>$score,'shared'=>$shared,'source_tokens'=>$left,'target_tokens'=>$right,'qualifier_ok'=>$ql===$qr,'source_qualifiers'=>$ql,'target_qualifiers'=>$qr];
}

function hmcf_best(array $sourceNames, array $candidateIds, array $names): array
{
    $rank=[];
    foreach ($candidateIds as $id) {
        $best=null;
        foreach ($sourceNames as $source) foreach ($names[$id] ?? [] as $target) {
            $pair=hmcf_name_pair((string)$source,(string)$target);
            $item=$pair+['id'=>(int)$id,'source_name'=>(string)$source,'target_name'=>(string)$target];
            if ($best===null || $item['score']>$best['score'] || ($item['score']===$best['score'] && $item['shared']>$best['shared']) || ($item['score']===$best['score'] && $item['shared']===$best['shared'] && $item['qualifier_ok']&&!$best['qualifier_ok'])) $best=$item;
        }
        if ($best!==null) $rank[]=$best;
    }
    usort($rank,static fn($a,$b)=>$b['score']<=>$a['score'] ?: $b['shared']<=>$a['shared'] ?: ($b['qualifier_ok']<=>$a['qualifier_ok']) ?: $a['id']<=>$b['id']);
    return $rank;
}

function hmcf_audit(PDO $db, string $strictOperation, string $operation): array
{
    $strict=msr_review($db,$strictOperation);
    if (($strict['status']??'')!=='completed') throw new RuntimeException('strict_failed');
    $tail=array_values(array_filter($strict['blocked_rows']??[],static fn($r)=>($r['provider']??'')==='andromeda'&&($r['reason']??'')==='cross_provider_or_supplier_evidence_needed'));

    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $db->exec('START TRANSACTION READ ONLY');
    try {
        [$hotels,$names,$strictNames,$broad,$places,$catalogScope]=mbr_catalog($db);
        [$anexLocal,$andromedaLocal]=mbr_local_sets($db);
        $latest=[];
        foreach($db->query("SELECT * FROM andromeda_search_hotel_observations WHERE supplier_namespace='andromeda_catalog' ORDER BY observed_at_utc DESC,external_hotel_id")->fetchAll(PDO::FETCH_ASSOC) as $o){$e=(string)$o['external_hotel_id'];if(!isset($latest[$e]))$latest[$e]=$o;}
        $identity=[];
        foreach($db->query("SELECT external_hotel_id,evidence_json,catalog_sha256 FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='pending' AND local_hotel_id IS NULL ORDER BY external_hotel_id")->fetchAll(PDO::FETCH_ASSOC) as $r)$identity[(string)$r['external_hotel_id']]=$r;

        $tokenIndex=[];
        foreach(array_keys($anexLocal) as $id){$id=(int)$id;if(!isset($hotels[$id]))continue;$country=(int)$hotels[$id]['country_id'];if(!isset(MBR_CORE8[$country]))continue;foreach($names[$id]??[] as $name)foreach(hmcf_tokens((string)$name) as $token)$tokenIndex[$country][$token][$id]=true;}

        $safe=[];$blocked=[];$reason=[];$countries=[];
        foreach($tail as $row){
            $external=(string)$row['external_id'];$country=(int)$row['country_id'];$sourceNames=array_values(array_filter(array_map('strval',$row['source_names']??[]),static fn($v)=>trim($v)!==''));$sourcePlaces=array_values(array_filter(array_map('strval',$row['source_places']??[]),static fn($v)=>trim($v)!==''));
            $candidate=[];$maxSourceTokens=0;
            foreach($sourceNames as $name){$tokens=hmcf_tokens($name);$maxSourceTokens=max($maxSourceTokens,count($tokens));foreach($tokens as $token)foreach(array_keys($tokenIndex[$country][$token]??[]) as $id)$candidate[(int)$id]=true;}
            if(!$candidate){$row['bridge_reason']='no_shared_significant_tokens';$blocked[]=$row;$reason['no_shared_significant_tokens']=($reason['no_shared_significant_tokens']??0)+1;continue;}
            $rank=hmcf_best($sourceNames,array_map('intval',array_keys($candidate)),$names);$best=$rank[0]??null;$second=$rank[1]['score']??0.0;
            if(!$best){$row['bridge_reason']='no_ranked_candidate';$blocked[]=$row;$reason['no_ranked_candidate']=($reason['no_ranked_candidate']??0)+1;continue;}
            $targetId=(int)$best['id'];$target=$hotels[$targetId];$margin=$best['score']-$second;
            $idrow=$identity[$external]??[];$ev=fc_evidence($idrow['evidence_json']??'');$src=$ev['source']??[];if(!is_array($src))$src=[];$obs=$latest[$external]??[];
            $coordSource=$src;if(is_array($obs))$coordSource+=$obs;$guard=mbr_target_guard($coordSource,$target);$place=fc_place($sourcePlaces,[(string)$target['region_name'],(string)$target['subregion_name']]);$directGeo=($guard['distance_m']!==null&&(int)$guard['distance_m']<=1000)||$place;
            $sourceCategory=mbr_numeric_category($src);if($sourceCategory===null&&is_array($obs))$sourceCategory=mbr_numeric_category($obs);$targetCategory=$target['category']===null?null:(int)$target['category'];$starSignal=($sourceCategory!==null&&$targetCategory!==null)?$sourceCategory-$targetCategory:null;
            $base=['provider'=>'andromeda','external_id'=>$external,'country_id'=>$country,'source_names'=>$sourceNames,'source_places'=>$sourcePlaces,'target'=>mbr_row_target($target),'best'=>$best,'second_score'=>round((float)$second,6),'margin'=>round((float)$margin,6),'guard'=>$guard,'direct_geo'=>$directGeo,'source_category'=>$sourceCategory,'star_difference_signal'=>$starSignal,'existing_anex_anchor'=>isset($anexLocal[$targetId]),'existing_andromeda_occupancy'=>isset($andromedaLocal[$targetId]),'not_write_authority'=>true];
            $why=null;
            if($guard['coordinate_conflict'])$why='coordinate_conflict_gt_5km';
            elseif(isset($andromedaLocal[$targetId]))$why='same_provider_target_occupied';
            elseif(!$best['qualifier_ok'])$why='meaningful_qualifier_mismatch';
            elseif($best['shared']<2)$why='insufficient_shared_tokens';
            elseif(!$directGeo)$why='direct_geo_required';
            elseif($best['score']<0.75)$why='score_below_075';
            elseif($margin<0.20)$why='winner_margin_below_020';
            if($why!==null){$base['bridge_reason']=$why;$blocked[]=$base;$reason[$why]=($reason[$why]??0)+1;continue;}
            $base['bridge_reason']=$best['score']>=0.999999?'cross_provider_reordered_or_generic_exact_geo':'cross_provider_strong_fuzzy_geo_margin';
            $safe[]=$base;$countries[$country]=($countries[$country]??0)+1;
        }
        ksort($reason);ksort($countries,SORT_NUMERIC);
        usort($safe,static fn($a,$b)=>$a['country_id']<=>$b['country_id'] ?: $b['best']['score']<=>$a['best']['score'] ?: strcmp($a['external_id'],$b['external_id']));
        $db->commit();
        return ['schema'=>'hotel-match-current-cross-provider-fuzzy/1','status'=>'completed','operation_id'=>$operation,'strict_operation_id'=>$strictOperation,'mode'=>'current_db_read_only','database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'tail_examined'=>count($tail),'safe_candidate_count'=>count($safe),'blocked_count'=>count($blocked),'safe_by_country'=>$countries,'blocked_reasons'=>$reason,'catalog_scope'=>$catalogScope,'anex_anchor_local_count'=>count($anexLocal),'andromeda_occupied_local_count'=>count($andromedaLocal),'safe_candidates'=>$safe,'guards'=>['generic_removed'=>['HOTEL','RESORT','SPA','THE','AND','EX/former'],'meaningful_qualifiers_preserved'=>true,'coordinate_conflict_gt_5km_blocks'=>true,'direct_geo_required'=>true,'star_difference_is_signal_only'=>true,'same_provider_occupancy_blocks'=>true,'no_existing_mapping_modified'=>true]];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
