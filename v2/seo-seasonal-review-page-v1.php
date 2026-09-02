<?php
declare(strict_types=1);
require_once __DIR__ . '/site-page-shell-v1.php';
require_once __DIR__ . '/seo-page-primitives-v1.php';
require_once __DIR__ . '/seo-seasonal-review-content-v1.php';

function v2_seo_seasonal_preview_catalog(): array
{
    return [
        'antalya-september' => [
            'path'=>'/_preview/seo2/seasonal/antalya-september/',
            'content_key'=>'antalya-september',
            'parent_path'=>'/country/turkey/antalya/',
            'parent_label'=>'Анталья',
            'search_state'=>['country'=>4,'region'=>20],
        ],
        'maldives-september' => [
            'path'=>'/_preview/seo2/seasonal/maldives-september/',
            'content_key'=>'maldives-september',
            'parent_path'=>'/country/maldives/',
            'parent_label'=>'Мальдивы',
            'search_state'=>['country'=>8],
        ],
    ];
}

function v2_seo_seasonal_preview_head(array $context): void
{
    ?>
<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><title><?=sp_e($context['title'])?></title><meta name="description" content="<?=sp_e($context['description'])?>"><meta name="robots" content="noindex,follow"><link rel="icon" href="/favicon.svg" type="image/svg+xml"><meta property="og:type" content="website"><meta property="og:site_name" content="AnyTour"><meta property="og:title" content="<?=sp_e($context['title'])?>"><meta property="og:description" content="<?=sp_e($context['description'])?>"><style id="sp-design-system-css"><?=sp_inline_css('design-system-v2.css')?></style><style id="sp-site-header-css"><?=sp_inline_css('site-header-v2.css')?></style><style id="sp-page-css"><?=sp_inline_css('site-page-v1.css')?></style><style id="sp-seo-editorial-reference-css"><?=sp_inline_css('seo-editorial-reference-v1.css')?></style><style id="sp-shell-alignment-css"><?=sp_inline_css('site-shell-alignment-v1.css')?></style><style id="sp-editorial-rhythm-css"><?=sp_inline_css('editorial-rhythm-v1.css')?></style><style id="sp-shared-content-primitives-css"><?=sp_inline_css('shared-content-primitives-v1.css')?></style><style id="sp-editorial-ds2-convergence-css"><?=sp_inline_css('editorial-ds2-convergence-v1.css')?></style><style id="sp-site-coherence-css"><?=sp_inline_css('site-coherence-v1.css')?></style><style id="sp-footer-css"><?=sp_inline_css('site-footer-v1.css')?></style></head><body><?php
}

function v2_seo_render_seasonal_preview(string $previewKey, ?int $nowEpoch = null): void
{
    $catalog=v2_seo_seasonal_preview_catalog();
    if(!isset($catalog[$previewKey])) throw new InvalidArgumentException('Unknown seasonal preview key');
    $preview=$catalog[$previewKey];
    $records=v2_seo_seasonal_review_content_prototypes();
    $contentKey=(string)$preview['content_key'];
    if(!isset($records[$contentKey])) throw new RuntimeException('Missing seasonal review content prototype');
    $content=v2_seo_seasonal_render_review_content($records[$contentKey],$nowEpoch);
    if(($content['state']??'')!=='rendered_review_only_seasonal_content') throw new RuntimeException('Seasonal preview content evidence is blocked');
    foreach(['publication_allowed','indexation_allowed','sitemap_allowed','route_creation_allowed'] as $flag) {
        if(($content[$flag]??true)!==false) throw new RuntimeException('Seasonal preview crossed review-only boundary');
    }
    if(($content['publication_candidates']??null)!==[]) throw new RuntimeException('Seasonal preview cannot expose publication candidates');

    $path=(string)$preview['path'];
    $description='Review-only предпросмотр AnyTour: '.trim((string)$content['intro']);
    $context=sp_context($path,(string)$content['title'].' — preview | AnyTour',$description);
    $context['robots']='noindex,follow';
    v2_seo_seasonal_preview_head($context);
    sp_header($context);
    sp_breadcrumbs([
        ['label'=>'Главная','href'=>'/'],
        ['label'=>(string)$preview['parent_label'],'href'=>(string)$preview['parent_path']],
        ['label'=>(string)$content['h1'].' · preview','href'=>''],
    ]);

    $handoff=v2_seo_search_handoff_url('/poisk-turov/',(array)$preview['search_state']);
    sp_hero('AnyTour · SEO review preview',(string)$content['h1'],(string)$content['intro'],$handoff,'Проверить актуальные туры');

    echo '<main class="sp-main sp-seo-editorial-page sp-seasonal-review-page">';
    echo '<section class="sp-card sp-search-callout"><h2>Статус страницы</h2><p>Это технический review-предпросмотр. Страница закрыта от индексации, не включена в sitemap и не является кандидатом на публикацию. Перед любым запуском production identity и фактические источники должны быть проверены повторно.</p></section>';
    echo '<div class="sp-editorial-grid">';
    foreach(($content['sections']??[]) as $section){
        echo '<section class="sp-card sp-editorial-section"><h2>'.sp_e((string)($section['heading']??'')).'</h2><p>'.sp_e((string)($section['text']??'')).'</p></section>';
    }
    echo '</div>';

    echo '<section class="sp-card sp-related-card"><h2>Источники и границы фактов</h2><p>'.sp_e((string)($content['source_note']??'')).'</p><p>Климатические ориентиры не заменяют краткосрочный прогноз, а наличие и стоимость тура всегда проверяются в поиске AnyTour.</p></section>';
    echo '<section class="sp-card sp-search-callout"><h2>Найти тур</h2><p>Даты, продолжительность, состав пакета, стоимость и доступность не зафиксированы в этой SEO-странице и берутся только из актуального поиска.</p><div class="sp-actions"><a class="sp-primary" href="'.sp_e($handoff).'">Перейти к поиску туров</a><a class="sp-secondary" href="'.sp_e((string)$preview['parent_path']).'">Вернуться: '.sp_e((string)$preview['parent_label']).'</a></div></section>';
    echo '</main>';
    sp_end($context);
}
