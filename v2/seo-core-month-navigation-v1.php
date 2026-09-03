<?php
declare(strict_types=1);
require_once __DIR__.'/seo-launch-slice-v1.php';

function v2_seo_core_month_navigation_labels(): array
{
    return [
        'january'=>'Январь','february'=>'Февраль','march'=>'Март','april'=>'Апрель',
        'may'=>'Май','june'=>'Июнь','july'=>'Июль','august'=>'Август',
        'september'=>'Сентябрь','october'=>'Октябрь','november'=>'Ноябрь','december'=>'Декабрь',
    ];
}

/** Month links are emitted only for the exact currently launched parent family. */
function v2_seo_core_month_links_for_parent(string $parentPath): array
{
    $parentPath=rtrim(trim($parentPath),'/').'/';
    if(!preg_match('#^/country/[a-z0-9-]+(?:/[a-z0-9-]+)?/$#',$parentPath))return [];

    $launched=array_fill_keys(v2_seo_controlled_launch_paths(),true);
    if(!isset($launched[$parentPath]))return [];

    $links=[];
    foreach(v2_seo_core_month_navigation_labels() as $slug=>$label){
        $path=$parentPath.$slug.'/';
        if(isset($launched[$path]))$links[]=['label'=>$label,'href'=>$path];
    }
    return count($links)===12?$links:[];
}

function v2_seo_render_core_month_navigation(string $parentPath,string $title='Туры по месяцам'): string
{
    $links=v2_seo_core_month_links_for_parent($parentPath);
    if(!$links)return '';
    $title=trim($title)!==''?trim($title):'Туры по месяцам';
    $e=static fn(string $value):string=>htmlspecialchars($value,ENT_QUOTES,'UTF-8');
    $html='<section class="sp-card sp-related-card" data-core-month-navigation><h2>'.$e($title).'</h2><div class="sp-actions">';
    foreach($links as $link)$html.='<a class="sp-secondary" href="'.$e((string)$link['href']).'">'.$e((string)$link['label']).'</a>';
    return $html.'</div></section>';
}
