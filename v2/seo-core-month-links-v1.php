<?php
declare(strict_types=1);

function v2_seo_core_month_links(string $parentPath,int $countryId,?int $regionId=null): array
{
    $parentPath=rtrim($parentPath,'/').'/';
    $allowedCountry=in_array($countryId,[1,4,8],true);
    $allowedResort=$countryId===4 && in_array((int)$regionId,[19,20,21,22,23],true);
    if($regionId===null && !$allowedCountry)return [];
    if($regionId!==null && !$allowedResort)return [];
    $months=[
        'january'=>'Январь','february'=>'Февраль','march'=>'Март','april'=>'Апрель','may'=>'Май','june'=>'Июнь',
        'july'=>'Июль','august'=>'Август','september'=>'Сентябрь','october'=>'Октябрь','november'=>'Ноябрь','december'=>'Декабрь',
    ];
    $links=[];
    foreach($months as $slug=>$label)$links[]=['label'=>$label,'href'=>$parentPath.$slug.'/'];
    return $links;
}

function v2_seo_render_core_month_links(array $links,string $title='Туры по месяцам'): string
{
    if($links===[])return '';
    $html='<section class="sp-card sp-related-card sp-month-links" data-core-month-links><h2>'.sp_e($title).'</h2>';
    $html.='<p>Выберите месяц поездки, чтобы перейти к подбору на нужный период и посмотреть актуальные предложения.</p><div class="sp-actions">';
    foreach($links as $link)$html.='<a class="sp-secondary" href="'.sp_e((string)$link['href']).'">'.sp_e((string)$link['label']).'</a>';
    return $html.'</div></section>';
}
