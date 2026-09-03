<?php
declare(strict_types=1);
require_once __DIR__.'/seo-core-month-matrix-v1.php';

function v2_seo_core_month_links_for_parent(string $parentPath): array
{
    $parentPath=rtrim($parentPath,'/').'/';
    $labels=[1=>'Январь',2=>'Февраль',3=>'Март',4=>'Апрель',5=>'Май',6=>'Июнь',7=>'Июль',8=>'Август',9=>'Сентябрь',10=>'Октябрь',11=>'Ноябрь',12=>'Декабрь'];
    $links=[];
    foreach(v2_seo_core_month_matrix()['rows'] as $row){
        $path=(string)($row['path']??'');
        if(dirname(rtrim($path,'/')).'/'!==rtrim($parentPath,'/').'/')continue;
        $month=(int)($row['month']??0);
        if(!isset($labels[$month]))continue;
        $links[]=['label'=>$labels[$month],'href'=>$path];
    }
    usort($links,static function(array $a,array $b):int use($labels){
        $order=array_flip(array_values($labels));
        return ($order[$a['label']]??99)<=>($order[$b['label']]??99);
    });
    return $links;
}

function v2_seo_render_core_month_navigation(string $parentPath,string $title='Туры по месяцам'): string
{
    $links=v2_seo_core_month_links_for_parent($parentPath);
    if($links===[])return '';
    $html='<section class="sp-card sp-month-navigation"><h2>'.sp_e($title).'</h2><p>Выберите месяц, чтобы перейти к подбору туров на ближайший соответствующий период.</p><div class="sp-actions">';
    foreach($links as $link)$html.='<a class="sp-secondary" href="'.sp_e($link['href']).'">'.sp_e($link['label']).'</a>';
    return $html.'</div></section>';
}
