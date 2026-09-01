<?php
require_once __DIR__ . '/site-page-shell-v1.php';
require_once __DIR__ . '/seo-page-contract-v1.php';

/**
 * Render an editorial SEO record as a production-safe review route.
 *
 * Review routes are always noindex regardless of global SEO_INDEXABLE. They may
 * have a clean self-canonical so routing/layout can be verified before promotion.
 */
function v2_seo_render_review_page(string $path, array $page): void
{
    $page = v2_seo_page_contract($page);
    $context = sp_context($path, $page['title'], $page['description']);
    $context['robots'] = v2_seo_robots_content(false);

    sp_head($context);
    sp_header($context);
    sp_breadcrumbs($page['breadcrumbs']);
    sp_hero($page['eyebrow'] !== '' ? $page['eyebrow'] : 'AnyTour', $page['h1'], $page['intro']);

    echo '<main class="sp-main">';
    foreach ($page['sections'] as $section) {
        echo '<section class="sp-card"';
        $id = preg_replace('/[^a-zA-Z0-9_-]+/', '-', (string)($section['id'] ?? ''));
        if ($id !== '') echo ' id="'.sp_e($id).'"';
        echo '><h2>'.sp_e($section['title']).'</h2>';
        foreach ($section['paragraphs'] as $paragraph) {
            echo '<p>'.sp_e($paragraph).'</p>';
        }
        echo '</section>';
    }

    $links = [];
    foreach ($page['related'] as $link) {
        if (!is_array($link)) continue;
        $label = trim((string)($link['label'] ?? ''));
        $href = v2_seo_stable_internal_href($link['href'] ?? '');
        if ($label !== '' && $href !== null) $links[$href] = $label;
    }
    foreach ($page['internal_links'] as $group) {
        foreach (($group['links'] ?? []) as $link) {
            if (!is_array($link)) continue;
            $label = trim((string)($link['label'] ?? ''));
            $href = v2_seo_stable_internal_href($link['href'] ?? '');
            if ($label !== '' && $href !== null) $links[$href] = $label;
        }
    }

    if ($links) {
        echo '<section class="sp-card"><h2>'.sp_e($page['related_title'] ?: 'Другие направления').'</h2><div class="sp-actions">';
        foreach ($links as $href => $label) {
            echo '<a class="sp-secondary" href="'.sp_e($href).'">'.sp_e($label).'</a>';
        }
        echo '</div></section>';
    }

    $searchUrl = v2_seo_search_handoff_url('/poisk-turov/', $page['search_state']);
    echo '<section class="sp-card sp-search-callout"><h2>Подобрать тур</h2><p>Проверьте актуальные даты, стоимость и доступность предложений в поиске AnyTour.</p><div class="sp-actions"><a class="sp-primary" href="'.sp_e($searchUrl).'">Перейти к поиску туров</a></div></section>';
    echo '</main>';

    sp_end($context);
}
