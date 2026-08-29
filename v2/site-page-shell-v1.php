<?php
require_once __DIR__ . '/assets.php';
require_once __DIR__ . '/seo-config.php';
require_once __DIR__ . '/site-footer-v1.php';
require_once __DIR__ . '/phone-value.php';
function sp_e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function sp_inline_css(string $file): string {
  $name=basename($file);
  if($name!==$file||!preg_match('/^[a-zA-Z0-9._-]+\.css$/',$name)) throw new InvalidArgumentException('Invalid standalone CSS name');
  $path=__DIR__.'/'.$name;
  if(!is_file($path)) throw new RuntimeException('Missing standalone CSS: '.$name);
  $css=file_get_contents($path);
  if($css===false) throw new RuntimeException('Unreadable standalone CSS: '.$name);
  return str_replace('</style','<\/style',$css);
}
function sp_context(string $path,string $title,string $description): array {
  $docRoot=rtrim((string)($_SERVER['DOCUMENT_ROOT']??''),'/');
  $siteConf=$docRoot.'/site_conf.php'; if($docRoot!==''&&is_file($siteConf)) require $siteConf;
  $siteParams=is_array($params??null)?$params:[];
  $phone=v2_site_phone($siteParams,'8 (800) 100 - 61 - 50');
  return ['path'=>$path,'title'=>$title,'description'=>$description,'phone'=>$phone,'phoneHref'=>v2_phone_href($phone),'robots'=>v2_seo_robots_content(v2_seo_indexable($siteParams))];
}
function sp_head(array $c): void { $canonical='https://anytoour.ru'.($c['path']==='/'?'/':rtrim($c['path'],'/').'/'); ?>
<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><title><?=sp_e($c['title'])?></title><meta name="description" content="<?=sp_e($c['description'])?>"><meta name="robots" content="<?=sp_e($c['robots'])?>"><link rel="canonical" href="<?=sp_e($canonical)?>"><link rel="icon" href="/favicon.svg" type="image/svg+xml"><meta name="theme-color" content="#ffffff"><meta property="og:type" content="website"><meta property="og:site_name" content="AnyTour"><meta property="og:title" content="<?=sp_e($c['title'])?>"><meta property="og:description" content="<?=sp_e($c['description'])?>"><meta property="og:url" content="<?=sp_e($canonical)?>"><style id="sp-page-css"><?=sp_inline_css('site-page-v1.css')?></style><style id="sp-footer-css"><?=sp_inline_css('site-footer-v1.css')?></style></head><body><?php }
function sp_header(array $c): void { ?><header class="sp-header"><div class="sp-header__in"><a class="sp-logo" href="/"><img src="/images/logo.svg" alt="AnyTour"></a><nav class="sp-nav" aria-label="Основное меню"><a href="/poisk-turov/">Поиск туров</a><a href="/country/">Страны</a><a href="/hot/">Горящие туры</a><a href="/rb/">Раннее бронирование</a><a href="/contacts/">Контакты</a></nav><a class="sp-phone" href="tel:<?=sp_e($c['phoneHref'])?>"><?=sp_e($c['phone'])?></a><a class="sp-cta" href="/poisk-turov/">Найти тур</a></div></header><?php }
function sp_hero(string $kicker,string $h1,string $copy): void { ?><section class="sp-hero"><div class="sp-wrap"><span class="sp-kicker"><?=sp_e($kicker)?></span><h1><?=sp_e($h1)?></h1><p><?=sp_e($copy)?></p></div></section><?php }
function sp_end(array $c): void { v2_render_site_footer($c['phone'],$c['phoneHref']); v2_render_standalone_canonical_footer(); echo '</body></html>'; }
