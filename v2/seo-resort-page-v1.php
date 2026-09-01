<?php
require_once __DIR__ . '/site-page-shell-v1.php';
require_once __DIR__ . '/seo-page-contract-v1.php';

/**
 * Render a curated resort page on its final clean path.
 *
 * review   => always noindex
 * approved => follows the existing global SEO_INDEXABLE site gate
 *
 * This keeps editorial approval separate from the site-wide indexing launch.
 */
function v2_seo_render_resort(array $record): void
{
    if (($record['type'] ?? '') !== 'resort') {
        throw new InvalidArgumentException('SEO resort runtime accepts resort records only');
    }

    $status = (string)($record['status'] ?? '');
    if (!in_array($status, ['review', 'approved'], true)) {
        throw new InvalidArgumentException('SEO resort runtime requires review or approved status');
    }

    $path = v2_seo_stable_internal_href($record['path'] ?? '');
    if ($path === null || !str_ends_with($path, '/')) {
        throw new InvalidArgumentException('SEO resort runtime requires a clean trailing-slash path');
    }

    $page = v2_seo_page_contract(is_array($record['data'] ?? null) ? $record['data'] : []);
    $context = sp_context($path, $page['title'], $page['description']);
    if ($status !== 'approved') {
        $context['robots'] = v2_seo_robots_content(false);
    }

    sp_head($context);
    sp_header($context);
    sp_breadcrumbs($page['breadcrumbs']);
    sp_hero($page['eyebrow'] ?: 'AnyTour · курорт', $page['h1'], $page['intro']);

    echo '<main class="sp-main">';
    foreach ($page['sections'] as $section) {
        $id = preg_replace('/[^a-zA-Z0-9_-]+/', '-', (string)($section['id'] ?? ''));
        echo '<section class="sp-card"'.($id !== '' ? ' id="'.sp_e($id).'"' : '').'><h2>'.sp_e($section['title']).'</h2>';
        foreach ($section['paragraphs'] as $paragraph) echo '<p>'.sp_e($paragraph).'</p>';
        echo '</section>';
    }

    $links = [];
    foreach ($page['related'] as $link) {
        if (!is_array($link)) continue;
        $href = v2_seo_stable_internal_href($link['href'] ?? '');
        $label = trim((string)($link['label'] ?? ''));
        if ($href !== null && $label !== '') $links[$href] = $label;
    }
    if ($links) {
        echo '<section class="sp-card"><h2>'.sp_e($page['related_title'] ?: 'Другие направления').'</h2><div class="sp-actions">';
        foreach ($links as $href => $label) echo '<a class="sp-secondary" href="'.sp_e($href).'">'.sp_e($label).'</a>';
        echo '</div></section>';
    }

    echo '<section class="sp-card sp-search-callout"><h2>Подобрать тур</h2><p>Проверьте актуальные даты, стоимость и доступность предложений в поиске AnyTour.</p><div class="sp-actions"><a class="sp-primary" href="'.sp_e(v2_seo_search_handoff_url('/poisk-turov/', $page['search_state'])).'">Перейти к поиску туров</a></div></section>';
    echo '</main>';
    sp_end($context);
}

/** Backward-compatible explicit review entrypoint. */
function v2_seo_render_resort_review(array $record): void
{
    if (($record['status'] ?? '') !== 'review') {
        throw new InvalidArgumentException('SEO resort review runtime accepts review records only');
    }
    v2_seo_render_resort($record);
}
