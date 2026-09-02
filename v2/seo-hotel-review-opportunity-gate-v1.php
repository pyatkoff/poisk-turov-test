<?php
declare(strict_types=1);
require_once __DIR__.'/seo-opportunity-readiness-v2.php';

/**
 * Apply SEO opportunity readiness to an already selected review-only hotel slice.
 * Technical quality and fresh identity are necessary but never sufficient for
 * launch: every hotel remains blocked until caller-supplied demand, uniqueness,
 * content and commercial-inventory signals are confirmed and fresh.
 */
function v2_seo_hotel_review_opportunity_gate(array $reviewItems, array $signalsByPath, ?int $nowEpoch=null): array
{
    $nowEpoch??=time();
    $rows=[]; $ready=0; $blocked=0; $seen=[];
    foreach($reviewItems as $item){
        if(!is_array($item)) throw new InvalidArgumentException('Hotel review opportunity item must be an array');
        $path=(string)($item['path']??'');
        if($path===''||isset($seen[$path])) throw new InvalidArgumentException('Hotel review opportunity path missing or duplicated');
        $seen[$path]=true;
        if((int)($item['score']??0)!==100) throw new InvalidArgumentException('Hotel review opportunity gate requires technical score 100');
        $evidenceExpires=(int)($item['evidence_expires_epoch']??0);
        if($evidenceExpires<=$nowEpoch) throw new InvalidArgumentException('Hotel review opportunity identity evidence is stale');
        $signals=is_array($signalsByPath[$path]??null)?$signalsByPath[$path]:[];
        // Entity/technical identity comes from the validated review slice itself.
        foreach(['entity','technical'] as $key){
            if(!isset($signals[$key])){
                $signals[$key]=['status'=>'confirmed','score'=>100,'observed_at_epoch'=>$nowEpoch,'source'=>'review_slice:'.$key];
            }
        }
        $page=['path'=>$path,'page_role'=>'hotel_tours','intent'=>'commercial_transactional'];
        $opportunity=v2_seo_opportunity_readiness($page,$signals,$nowEpoch);
        if(($opportunity['review_candidate']??false)===true) $ready++; else $blocked++;
        $rows[]=[
            'path'=>$path,
            'country_id'=>(int)($item['country_id']??0),
            'hotel_id'=>(int)($item['hotel_id']??0),
            'technical_score'=>100,
            'identity_evidence_expires_epoch'=>$evidenceExpires,
            'opportunity'=>$opportunity,
        ];
    }
    usort($rows,static fn(array $a,array $b):int=>strcmp($a['path'],$b['path']));
    return [
        'state'=>$blocked===0&&$rows!==[]?'opportunity_review_ready':'opportunity_evidence_blocked',
        'total'=>count($rows),
        'opportunity_ready_count'=>$ready,
        'opportunity_blocked_count'=>$blocked,
        'rows'=>$rows,
        'publication_candidates'=>[],
        'publication_allowed'=>false,
        'indexation_allowed'=>false,
        'sitemap_allowed'=>false,
        'canonical_launch_allowed'=>false,
        'route_launch_allowed'=>false,
        'explicit_user_indexation_approval_required'=>true,
    ];
}
