<?php
declare(strict_types=1);
require_once __DIR__ . '/seo-seasonal-review-page-v1.php';

/**
 * Structural/content readiness diagnostics for review-only seasonal previews.
 * A 100 score means the preview is internally coherent for review; it never
 * means publication/indexation is approved.
 */
function v2_seo_seasonal_preview_readiness(?int $nowEpoch = null): array
{
    $catalog=v2_seo_seasonal_preview_catalog();
    $records=v2_seo_seasonal_review_content_prototypes();
    $rendererSource=(string)@file_get_contents(__DIR__.'/seo-seasonal-review-page-v1.php');
    $pages=[]; $totalScore=0; $allReviewReady=true;

    foreach($catalog as $previewKey=>$preview){
        $errors=[]; $dimensions=[];
        $contentKey=(string)($preview['content_key']??'');
        $record=$records[$contentKey]??null;
        $path=(string)($preview['path']??'');

        $identityOk=is_array($record)
            && str_starts_with($path,'/_preview/seo2/seasonal/')
            && (int)($record['country_id']??0)===(int)(($preview['search_state']['country']??0));
        if($identityOk && ($record['region_id']??null)!==null){
            $identityOk=(int)$record['region_id']===(int)($preview['search_state']['region']??0);
        } elseif($identityOk && array_key_exists('region',(array)($preview['search_state']??[]))) {
            $identityOk=false;
        }
        $dimensions['identity_integrity']=$identityOk;
        if(!$identityOk) $errors[]='identity_integrity';

        $content=null;
        if(is_array($record)){
            try { $content=v2_seo_seasonal_render_review_content($record,$nowEpoch); }
            catch(Throwable $e){ $content=null; }
        }
        $claims=is_array($content['claims']??null)?$content['claims']:[];
        $sourcedOk=($content['state']??'')==='rendered_review_only_seasonal_content'&&$claims!==[];
        if($sourcedOk){
            foreach($claims as $claim){
                if(trim((string)($claim['source_id']??''))===''||trim((string)($claim['source_url']??''))===''){ $sourcedOk=false; break; }
            }
        }
        $dimensions['sourced_content']=$sourcedOk;
        if(!$sourcedOk) $errors[]='sourced_content';

        $handoffOk=$identityOk&&is_array($preview['search_state']??null)&&((int)($preview['search_state']['country']??0)>0);
        $dimensions['search_handoff_integrity']=$handoffOk;
        if(!$handoffOk) $errors[]='search_handoff_integrity';

        $boundaryOk=is_array($record);
        foreach(['publication_allowed','indexation_allowed','sitemap_allowed','route_creation_allowed'] as $flag){
            if(($record[$flag]??true)!==false||($content!==null&&($content[$flag]??true)!==false){$boundaryOk=false;}
        }
        if($content!==null&&($content['publication_candidates']??null)!==[]) $boundaryOk=false;
        $dimensions['publication_boundary']=$boundaryOk;
        if(!$boundaryOk) $errors[]='publication_boundary';

        $routeFile=__DIR__.'/'.trim($path,'/').'/index.php';
        $routeSource=is_file($routeFile)?(string)@file_get_contents($routeFile):'';
        $routeOk=$routeSource!==''
            && str_contains($routeSource,"v2_seo_render_seasonal_preview('".$previewKey."')")
            && $rendererSource!==''
            && !str_contains($rendererSource,'rel="canonical"')
            && !str_contains($rendererSource,'property="og:url"')
            && str_contains($rendererSource,'content="noindex,follow"');
        $dimensions['route_and_head_integrity']=$routeOk;
        if(!$routeOk) $errors[]='route_and_head_integrity';

        $passed=count(array_filter($dimensions,static fn($v)=>$v===true));
        $score=$passed*20;
        $reviewReady=$score===100;
        $totalScore+=$score;
        if(!$reviewReady) $allReviewReady=false;
        $pages[$previewKey]=[
            'path'=>$path,
            'page_key'=>(string)($record['page_key']??''),
            'score'=>$score,
            'review_ready'=>$reviewReady,
            'dimensions'=>$dimensions,
            'errors'=>$errors,
            'publication_allowed'=>false,
            'indexation_allowed'=>false,
            'sitemap_allowed'=>false,
        ];
    }

    $count=count($pages);
    return [
        'state'=>$count>0&&$allReviewReady?'review_ready':'blocked',
        'preview_count'=>$count,
        'average_score'=>$count>0?(int)round($totalScore/$count):0,
        'all_review_ready'=>$count>0&&$allReviewReady,
        'pages'=>$pages,
        'publication_candidates'=>[],
        'publication_allowed'=>false,
        'indexation_allowed'=>false,
        'sitemap_allowed'=>false,
        'explicit_launch_approval_required'=>true,
    ];
}
