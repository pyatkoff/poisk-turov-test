<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/v2/seo-structured-data-v1.php';

$page=v2_seo_webpage_schema('/country/egypt/aleksandriya/','Туры в Александрию','Описание страницы');
if(($page['@type']??'')!=='WebPage')exit(1);
if(($page['url']??'')!=='https://anytoour.ru/country/egypt/aleksandriya/')exit(2);
if(($page['isPartOf']['url']??'')!=='https://anytoour.ru/')exit(3);

$crumbs=v2_seo_breadcrumb_schema([
    ['label'=>'Главная','href'=>'/'],
    ['label'=>'Египет','href'=>'/country/egypt/'],
    ['label'=>'Александрия'],
],'/country/egypt/aleksandriya/');
if(($crumbs['@type']??'')!=='BreadcrumbList')exit(4);
$items=(array)($crumbs['itemListElement']??[]);
if(count($items)!==3)exit(5);
if(($items[0]['position']??0)!==1||($items[2]['position']??0)!==3)exit(6);
if(($items[2]['item']??'')!=='https://anytoour.ru/country/egypt/aleksandriya/')exit(7);
if(v2_seo_webpage_schema('/bad path/','Bad','')!==[])exit(8);
if(v2_seo_breadcrumb_schema([['label'=>'X','href'=>'https://evil.example/']],'/country/egypt/')!==[])exit(9);
$json=v2_seo_json_ld(['value'=>'</script><script>alert(1)</script>']);
if(stripos($json,'</script>')!==false)exit(10);
if(strpos($json,'\\u003C')===false)exit(11);
echo "SEO_STRUCTURED_DATA_OK webpage=1 breadcrumbs=3 safe_json=1\n";
