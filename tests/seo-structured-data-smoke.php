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
$list=v2_seo_item_list_schema([
    ['name'=>'Турция','path'=>'/country/turkey/'],
    ['label'=>'Египет','href'=>'/country/egypt/'],
    ['name'=>'Дубликат','path'=>'/country/turkey/'],
    ['name'=>'Внешний URL','path'=>'https://example.com/'],
    ['name'=>'','path'=>'/country/maldives/'],
],'Направления AnyTour');
if(($list['@type']??'')!=='ItemList'||($list['numberOfItems']??0)!==2)exit(12);
$listItems=(array)($list['itemListElement']??[]);
if(count($listItems)!==2||($listItems[0]['position']??0)!==1||($listItems[1]['position']??0)!==2)exit(13);
if(($listItems[0]['item']??'')!=='https://anytoour.ru/country/turkey/'||($listItems[1]['name']??'')!=='Египет')exit(14);
if(v2_seo_item_list_schema([], 'Направления')!==[]||v2_seo_item_list_schema([['name'=>'Турция','path'=>'/country/turkey/']],'')!==[])exit(15);
$_SERVER['HTTP_HOST']='anytoour.ru';$_SERVER['REQUEST_URI']='/country/';
ob_start();require dirname(__DIR__).'/v2/country/index.php';$countryHtml=(string)ob_get_clean();
if(!str_contains($countryHtml,'"@type":"ItemList"')||!str_contains($countryHtml,'"numberOfItems":14'))exit(16);
if(substr_count($countryHtml,'type="application/ld+json"')!==3)exit(17);
echo "SEO_STRUCTURED_DATA_OK webpage=1 breadcrumbs=3 itemList=14 countryRender=1 safe_json=1\n";
