<?php
declare(strict_types=1);

require_once __DIR__.'/seo-hotel-launch-pilot-v1.php';
require_once __DIR__.'/seo-opportunity-scoring-policy-v1.php';

function v2_seo_hotel_pilot_status_signal(array $signal, int $nowEpoch, int $maxAge): array
{
    $status=(string)($signal['status']??'unknown');
    $source=trim((string)($signal['source']??''));
    $observed=(int)($signal['observed_at_epoch']??0);
    $fresh=$observed>0&&$observed<=$nowEpoch&&($nowEpoch-$observed)<=$maxAge;
    if(!in_array($status,['confirmed','blocked','unknown'],true))$status='unknown';
    if(in_array($status,['confirmed','blocked'],true)&&($source===''||!$fresh))$status='unknown';
    return ['status'=>$status,'source'=>$source,'observed_at_epoch'=>$observed,'fresh'=>$fresh];
}

/**
 * Produce one fail-closed status view for the exact Turkey/Maldives/Egypt 3x3
 * hotel review pilot.
 *
 * This layer intentionally does not calculate an implicit opportunity score and
 * never promotes a hotel into publication. It separates verified technical/live
 * readiness from external demand, SERP intent, uniqueness, content and inventory
 * evidence, then reports the explicit scoring-policy state independently.
 */
function v2_seo_hotel_pilot_readiness_status(
    array $liveEvidence,
    array $opportunityIntake=[],
    array $signalsByPath=[],
    array $policy=[],
    ?int $nowEpoch=null
): array {
    $nowEpoch??=time();
    $spec=v2_seo_hotel_launch_pilot_spec();
    $expected=[];
    foreach((array)($spec['countries']??[]) as $bucket){
        $countryId=(int)($bucket['country_id']??0);
        foreach((array)($bucket['paths']??[]) as $path)$expected[(string)$path]=$countryId;
    }
    if(count($expected)!==9)throw new RuntimeException('Hotel pilot status requires exact 3x3 pilot spec');

    $errors=[];
    $checks=[];
    foreach((array)($liveEvidence['checks']??[]) as $i=>$row){
        if(!is_array($row)){ $errors[]='invalid_live_check:'.$i; continue; }
        $path=(string)($row['path']??'');
        if($path===''||!isset($expected[$path])){ $errors[]='unexpected_live_check:'.$path; continue; }
        if(isset($checks[$path])){ $errors[]='duplicate_live_check:'.$path; continue; }
        if((int)($row['country_id']??0)!==$expected[$path])$errors[]='live_country_mismatch:'.$path;
        if((int)($row['hotel_id']??0)<=0)$errors[]='missing_live_hotel_id:'.$path;
        $checks[$path]=$row;
    }

    $dossierRows=[];
    foreach((array)($liveEvidence['dossier']['rows']??[]) as $i=>$row){
        if(!is_array($row)){ $errors[]='invalid_dossier_row:'.$i; continue; }
        $path=(string)($row['path']??'');
        if($path===''||!isset($expected[$path])){ $errors[]='unexpected_dossier_row:'.$path; continue; }
        if(isset($dossierRows[$path])){ $errors[]='duplicate_dossier_row:'.$path; continue; }
        $dossierRows[$path]=$row;
    }
    foreach(array_keys($expected) as $path){
        if(!isset($checks[$path]))$errors[]='missing_live_check:'.$path;
        if(!isset($dossierRows[$path]))$errors[]='missing_dossier_row:'.$path;
    }
    if(count($checks)!==9)$errors[]='live_check_count_must_be_9';
    if(count($dossierRows)!==9)$errors[]='dossier_row_count_must_be_9';

    $packets=is_array($opportunityIntake['packets_by_path']??null)?$opportunityIntake['packets_by_path']:[];
    foreach(array_keys($packets) as $path)if(!isset($expected[(string)$path]))$errors[]='unexpected_opportunity_packet:'.(string)$path;
    foreach(array_keys($signalsByPath) as $path)if(!isset($expected[(string)$path]))$errors[]='unexpected_supplemental_signal:'.(string)$path;

    $policyState='pending';$policySha='';$policyErrors=[];
    if($policy!==[]){
        $validatedPolicy=v2_seo_opportunity_scoring_policy($policy);
        $policyState=($validatedPolicy['state']??'')==='opportunity_scoring_policy_valid'?'valid':'invalid';
        $policySha=(string)($validatedPolicy['policy_sha256']??'');
        $policyErrors=(array)($validatedPolicy['errors']??[]);
    }

    $rows=[];$stateCounts=[];$evidenceComplete=0;$reviewReady=0;$policyPending=0;
    foreach($expected as $path=>$countryId){
        $check=is_array($checks[$path]??null)?$checks[$path]:[];
        $dossier=is_array($dossierRows[$path]??null)?$dossierRows[$path]:[];
        $packet=is_array($packets[$path]??null)?$packets[$path]:[];
        $supplemental=is_array($signalsByPath[$path]??null)?$signalsByPath[$path]:[];

        $technicalConfirmed=(int)($dossier['quality_score']??-1)===100&&($dossier['fresh']??false)===true;
        $identityConfirmed=($check['identity_verified']??false)===true;
        $boundaryConfirmed=($check['review_status_ok']??false)===true
            &&($check['noindex_ok']??false)===true
            &&($check['out_of_sitemap_ok']??false)===true
            &&(($dossier['fresh']??false)===true)
            &&(($dossier['quality_score']??-1)===100);

        $demand=is_array($packet['demand']??null)?$packet['demand']:[];
        $demandStatus='unknown';
        if(($demand['state']??'')==='demand_evidence_valid'&&($demand['fresh']??false)===true){
            $raw=(string)($demand['status']??'unknown');
            if(in_array($raw,['confirmed','blocked'],true))$demandStatus=$raw;
        }

        $uniqueness=is_array($packet['uniqueness']??null)?$packet['uniqueness']:[];
        $uniquenessStatus='unknown';$uniquenessDecision=(string)($uniqueness['decision']??'unknown');
        if(($uniqueness['state']??'')==='uniqueness_evidence_valid'&&($uniqueness['fresh']??false)===true&&($uniqueness['status']??'')==='confirmed'){
            $uniquenessStatus=$uniquenessDecision==='distinct'?'confirmed':(in_array($uniquenessDecision,['merge','noindex','skip'],true)?'blocked':'unknown');
        }

        $serpIntent=(string)($demand['serp_intent']??'');
        $intentStatus='unknown';
        if($demandStatus==='confirmed'&&in_array($serpIntent,['commercial','mixed'],true))$intentStatus='confirmed';
        elseif($demandStatus==='confirmed'&&$serpIntent==='informational')$intentStatus='blocked';

        $content=v2_seo_hotel_pilot_status_signal(is_array($supplemental['content']??null)?$supplemental['content']:[],$nowEpoch,86400*31);
        $inventoryLive=($check['fresh_offer_evidence']??false)===true;
        $inventory=$inventoryLive
            ? ['status'=>'confirmed','source'=>'live_hotel_pilot:fresh_offer_evidence','observed_at_epoch'=>(int)($liveEvidence['observed_at_epoch']??$nowEpoch),'fresh'=>true]
            : v2_seo_hotel_pilot_status_signal(is_array($supplemental['commercial_inventory']??null)?$supplemental['commercial_inventory']:[],$nowEpoch,86400*3);

        $dimensions=[
            'technical'=>['status'=>$technicalConfirmed?'confirmed':'blocked','score'=>$technicalConfirmed?100:null,'fresh'=>(bool)($dossier['fresh']??false)],
            'identity'=>['status'=>$identityConfirmed?'confirmed':'blocked','hotel_id'=>(int)($check['hotel_id']??0)],
            'review_boundary'=>['status'=>$boundaryConfirmed?'confirmed':'blocked','noindex'=>(bool)($check['noindex_ok']??false),'out_of_sitemap'=>(bool)($check['out_of_sitemap_ok']??false)],
            'intent'=>['status'=>$intentStatus,'serp_intent'=>$serpIntent],
            'demand'=>['status'=>$demandStatus,'source_class'=>(string)($demand['source_class']??''),'observed_at_epoch'=>(int)($demand['observed_at_epoch']??0),'fresh'=>(bool)($demand['fresh']??false)],
            'uniqueness'=>['status'=>$uniquenessStatus,'decision'=>$uniquenessDecision,'source_class'=>(string)($uniqueness['source_class']??''),'observed_at_epoch'=>(int)($uniqueness['observed_at_epoch']??0),'fresh'=>(bool)($uniqueness['fresh']??false)],
            'content'=>$content,
            'commercial_inventory'=>$inventory,
            'scoring_policy'=>['status'=>$policyState,'policy_sha256'=>$policySha],
        ];

        $hardTechnical=$technicalConfirmed&&$identityConfirmed&&$boundaryConfirmed;
        $evidenceStatuses=[$intentStatus,$demandStatus,$uniquenessStatus,$content['status'],$inventory['status']];
        $hasConflict=in_array('blocked',$evidenceStatuses,true);
        $allEvidence=$hardTechnical&&!$hasConflict&&count(array_filter($evidenceStatuses,static fn(string $s):bool=>$s==='confirmed'))===5;
        if(!$hardTechnical)$pageState='technical_identity_blocked';
        elseif($hasConflict)$pageState='evidence_conflict';
        elseif(!$allEvidence)$pageState='evidence_incomplete';
        elseif($policyState==='pending')$pageState='evidence_complete_policy_pending';
        elseif($policyState==='invalid')$pageState='evidence_complete_policy_invalid';
        else $pageState='review_ready_scoring_policy_valid';

        if($allEvidence)$evidenceComplete++;
        if($pageState==='review_ready_scoring_policy_valid')$reviewReady++;
        if($pageState==='evidence_complete_policy_pending')$policyPending++;
        $stateCounts[$pageState]=($stateCounts[$pageState]??0)+1;
        $rows[]=[
            'path'=>$path,
            'country_id'=>$countryId,
            'hotel_id'=>(int)($check['hotel_id']??0),
            'state'=>$pageState,
            'dimensions'=>$dimensions,
            'opportunity_packet_state'=>(string)($packet['state']??'missing'),
            'packet_sha256'=>(string)($packet['packet_sha256']??''),
        ];
    }
    usort($rows,static fn(array $a,array $b):int=>strcmp($a['path'],$b['path']));
    ksort($stateCounts,SORT_STRING);
    $fingerprint=hash('sha256',json_encode([
        'rows'=>$rows,
        'policy_state'=>$policyState,
        'policy_sha256'=>$policySha,
        'live_dossier_sha256'=>$liveEvidence['dossier']['dossier_sha256']??'',
        'opportunity_intake_sha256'=>$opportunityIntake['intake_sha256']??'',
    ],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));

    return [
        'state'=>$errors===[]?'review_only_hotel_pilot_status_complete':'review_only_hotel_pilot_status_blocked',
        'hotel_count'=>9,
        'country_counts'=>[1=>3,4=>3,8=>3],
        'evidence_complete_count'=>$evidenceComplete,
        'review_ready_count'=>$reviewReady,
        'policy_pending_count'=>$policyPending,
        'state_counts'=>$stateCounts,
        'scoring_policy_state'=>$policyState,
        'scoring_policy_errors'=>$policyErrors,
        'rows'=>$rows,
        'status_sha256'=>$fingerprint,
        'errors'=>array_values(array_unique($errors)),
        'publication_candidates'=>[],
        'publication_allowed'=>false,
        'indexation_allowed'=>false,
        'sitemap_allowed'=>false,
        'canonical_launch_allowed'=>false,
        'route_launch_allowed'=>false,
        'automatic_execution_allowed'=>false,
        'explicit_user_indexation_approval_required'=>true,
        'search_contract_changes'=>false,
        'tourvisor_contract_changes'=>false,
        'pricing_contract_changes'=>false,
        'lead_contract_changes'=>false,
        'metrika_contract_changes'=>false,
    ];
}
