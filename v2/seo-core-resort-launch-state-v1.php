<?php
declare(strict_types=1);

/** Reserved first path segments under a country route that cannot be resort slugs. */
function v2_seo_core_resort_reserved_slugs(): array
{
    return [
        'january','february','march','april','may','june',
        'july','august','september','october','november','december',
        'hotel','poisk-turov',
    ];
}

function v2_seo_core_resort_launch_manifest(): array
{
    $file=__DIR__.'/data/generated/seo-core-resort-review-routes-v1.json';
    if(!is_file($file))return [];
    $manifest=json_decode((string)file_get_contents($file),true);
    if(!is_array($manifest)||($manifest['state']??'')!=='core_resort_review_routes_materialized')return [];
    foreach(['publication_allowed','indexation_allowed','sitemap_allowed','route_launch_allowed'] as $flag){
        if(($manifest[$flag]??false)!==true)return [];
    }
    $routes=is_array($manifest['generated_routes']??null)?array_values($manifest['generated_routes']):[];
    $resortCount=(int)($manifest['generated_resorts']??0);
    if($resortCount<=0||count($routes)!==$resortCount*13)return [];

    $months=array_fill_keys(array_slice(v2_seo_core_resort_reserved_slugs(),0,12),true);
    $reserved=array_fill_keys(v2_seo_core_resort_reserved_slugs(),true);
    $groups=[];$clean=[];
    foreach($routes as $path){
        if(!is_string($path))return [];
        if(preg_match('#^/country/(egypt|maldives)/([a-z0-9-]+)/$#',$path,$m)){
            if(isset($reserved[$m[2]]))return [];
            $groups[$m[1].'/'.$m[2]]['base']=true;
        } elseif(preg_match('#^/country/(egypt|maldives)/([a-z0-9-]+)/(january|february|march|april|may|june|july|august|september|october|november|december)/$#',$path,$m)){
            if(isset($reserved[$m[2]])||!isset($months[$m[3]]))return [];
            $groups[$m[1].'/'.$m[2]]['months'][$m[3]]=true;
        } else return [];
        $clean[$path]=true;
    }
    if(count($groups)!==$resortCount)return [];
    foreach($groups as $group){
        if(empty($group['base'])||count($group['months']??[])!==12)return [];
    }
    $manifest['generated_routes']=array_keys($clean);
    sort($manifest['generated_routes'],SORT_STRING);
    return $manifest;
}

function v2_seo_core_resort_launch_paths(): array
{
    return array_values(v2_seo_core_resort_launch_manifest()['generated_routes']??[]);
}

function v2_seo_core_resort_launch_registry(): array
{
    if(v2_seo_core_resort_launch_manifest()===[])return [];
    $file=__DIR__.'/data/generated/seo-core-resort-review-registry-v1.php';
    if(!is_file($file))return [];
    $rows=require $file;
    return is_array($rows)?$rows:[];
}

function v2_seo_core_resort_country_links(string $countryPath): array
{
    $countryPath=rtrim($countryPath,'/').'/';
    if(!in_array($countryPath,['/country/egypt/','/country/maldives/'],true))return [];
    $links=[];
    foreach(v2_seo_core_resort_launch_registry() as $path=>$record){
        if(!is_string($path)||!str_starts_with($path,$countryPath)||!is_array($record))continue;
        if(!preg_match('#^/country/(egypt|maldives)/[a-z0-9-]+/$#',$path))continue;
        $label=trim((string)($record['data']['name']??''));
        if($label!=='')$links[$path]=$label;
    }
    asort($links,SORT_NATURAL|SORT_FLAG_CASE);
    return $links;
}
