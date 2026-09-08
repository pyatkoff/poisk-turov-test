<?php
declare(strict_types=1);

function v2_seo_schema_absolute_url(string $path): ?string
{
    $path=trim($path);
    if($path==='')return null;
    if($path==='/')return 'https://anytoour.ru/';
    if(!preg_match('#^/[a-z0-9/_-]+/$#',$path))return null;
    return 'https://anytoour.ru'.$path;
}

function v2_seo_webpage_schema(string $path,string $title,string $description): array
{
    $url=v2_seo_schema_absolute_url($path);
    $title=trim($title);$description=trim($description);
    if($url===null||$title==='')return [];
    return [
        '@context'=>'https://schema.org',
        '@type'=>'WebPage',
        'name'=>$title,
        'description'=>$description,
        'url'=>$url,
        'isPartOf'=>[
            '@type'=>'WebSite',
            'name'=>'AnyTour',
            'url'=>'https://anytoour.ru/',
        ],
    ];
}

function v2_seo_breadcrumb_schema(array $items,string $currentPath): array
{
    $currentUrl=v2_seo_schema_absolute_url($currentPath);
    if($currentUrl===null)return [];
    $elements=[];
    foreach(array_values($items) as $item){
        if(!is_array($item))continue;
        $label=trim((string)($item['label']??''));
        if($label==='')continue;
        $href=trim((string)($item['href']??''));
        $url=$href!==''?v2_seo_schema_absolute_url($href):$currentUrl;
        if($url===null)continue;
        $elements[]=[
            '@type'=>'ListItem',
            'position'=>count($elements)+1,
            'name'=>$label,
            'item'=>$url,
        ];
    }
    if(!$elements)return [];
    $last=count($elements)-1;
    $elements[$last]['item']=$currentUrl;
    return [
        '@context'=>'https://schema.org',
        '@type'=>'BreadcrumbList',
        'itemListElement'=>$elements,
    ];
}

/** Structured catalog for visible, canonical on-site destination links. */
function v2_seo_item_list_schema(array $items,string $name): array
{
    $name=trim($name);
    if($name==='')return [];
    $elements=[];$seen=[];
    foreach($items as $item){
        if(!is_array($item))continue;
        $label=trim((string)($item['name']??$item['label']??''));
        $path=trim((string)($item['path']??$item['href']??''));
        $url=v2_seo_schema_absolute_url($path);
        if($label===''||$url===null||isset($seen[$url]))continue;
        $seen[$url]=true;
        $elements[]=[
            '@type'=>'ListItem',
            'position'=>count($elements)+1,
            'name'=>$label,
            'item'=>$url,
        ];
    }
    if(!$elements)return [];
    return [
        '@context'=>'https://schema.org',
        '@type'=>'ItemList',
        'name'=>$name,
        'numberOfItems'=>count($elements),
        'itemListElement'=>$elements,
    ];
}

function v2_seo_json_ld(array $schema): string
{
    if(!$schema)return '';
    return json_encode(
        $schema,
        JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR
    );
}
