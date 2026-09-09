<?php
declare(strict_types=1);

/**
 * Final review-only join between authored seasonal content and a fresh editorial
 * evidence bundle. The packet is deliberately non-routable/non-publishable.
 */
function v2_seo_seasonal_content_packet(array $content, array $editorialBundle, string $pageKey): array
{
    $pageKey=trim($pageKey);
    if($pageKey==='') throw new InvalidArgumentException('Seasonal content packet requires explicit page key');
    if(($content['state']??'')!=='rendered_review_only_seasonal_content') throw new InvalidArgumentException('Seasonal content packet requires rendered review-only content');
    if(($editorialBundle['state']??'')!=='review_only_seasonal_editorial_bundle') throw new InvalidArgumentException('Seasonal content packet requires fresh editorial review bundle');

    foreach([
        [$content,'publication_allowed'],[$content,'indexation_allowed'],[$content,'sitemap_allowed'],[$content,'route_creation_allowed'],
        [$editorialBundle,'publication_allowed'],[$editorialBundle,'indexation_allowed'],[$editorialBundle,'sitemap_allowed'],[$editorialBundle,'copy_generation_allowed'],
    ] as [$source,$flag]) {
        if(($source[$flag]??true)!==false) throw new InvalidArgumentException('Seasonal content packet crossed launch boundary');
    }
    if(($content['publication_candidates']??null)!==[]||($editorialBundle['publication_candidates']??null)!==[]) throw new InvalidArgumentException('Seasonal content packet contains publication candidates');
    if(($content['page_key']??'')!==$pageKey) throw new InvalidArgumentException('Seasonal content page key mismatch');

    $editorialItem=null;
    foreach(($editorialBundle['items']??[]) as $item){
        if(is_array($item)&&($item['page_key']??'')===$pageKey){
            if($editorialItem!==null) throw new InvalidArgumentException('Seasonal editorial bundle has duplicate page key');
            $editorialItem=$item;
        }
    }
    if($editorialItem===null) throw new InvalidArgumentException('Seasonal content page absent from editorial bundle');
    foreach(['publication_allowed','indexation_allowed','sitemap_allowed','copy_generation_allowed'] as $flag) {
        if(($editorialItem[$flag]??true)!==false) throw new InvalidArgumentException('Seasonal editorial item crossed launch boundary');
    }

    $normalizeClaims=static function(array $claims): array {
        $rows=[];
        foreach($claims as $claim){
            if(!is_array($claim)) throw new InvalidArgumentException('Seasonal packet claim is invalid');
            $key=trim((string)($claim['claim_key']??''));
            if($key===''||isset($rows[$key])) throw new InvalidArgumentException('Seasonal packet claim key is empty or duplicate');
            $rows[$key]=[
                'type'=>(string)($claim['type']??''),
                'value'=>(string)($claim['value']??''),
                'source_id'=>(string)($claim['source_id']??''),
                'source_url'=>(string)($claim['source_url']??''),
                'geography_scope'=>$claim['geography_scope']??null,
            ];
        }
        ksort($rows);
        return $rows;
    };
    $contentClaims=$normalizeClaims(is_array($content['claims']??null)?$content['claims']:[]);
    $editorialClaims=$normalizeClaims(is_array($editorialItem['claims']??null)?$editorialItem['claims']:[]);
    if($contentClaims===[]||$contentClaims!==$editorialClaims) throw new InvalidArgumentException('Seasonal authored claims do not match fresh editorial evidence');

    $expires=(int)($editorialItem['evidence_valid_until_epoch']??0);
    if($expires<=0) throw new InvalidArgumentException('Seasonal content packet lacks evidence expiry');

    return [
        'state'=>'review_only_seasonal_content_packet',
        'page_key'=>$pageKey,
        'title'=>(string)($content['title']??''),
        'h1'=>(string)($content['h1']??''),
        'intro'=>(string)($content['intro']??''),
        'sections'=>is_array($content['sections']??null)?$content['sections']:[],
        'claims'=>is_array($content['claims']??null)?$content['claims']:[],
        'source_note'=>(string)($content['source_note']??''),
        'evidence_valid_until_epoch'=>$expires,
        'publication_candidates'=>[],
        'publication_allowed'=>false,
        'indexation_allowed'=>false,
        'sitemap_allowed'=>false,
        'route_creation_allowed'=>false,
    ];
}
