<?php
require_once __DIR__ . '/seo-page-primitives-v1.php';

/**
 * Normalize and validate the data needed by future server-rendered SEO landing pages.
 * This file defines content architecture only; it does not publish or index a route.
 */
function v2_seo_page_contract(array $page): array
{
    $title = trim((string)($page['title'] ?? ''));
    $description = trim((string)($page['description'] ?? ''));
    $h1 = trim((string)($page['h1'] ?? ''));
    if ($title === '' || $h1 === '') throw new InvalidArgumentException('SEO page title and H1 are required');
    if (mb_strlen($description, 'UTF-8') < 20) throw new InvalidArgumentException('SEO page description is too short');

    $breadcrumbs = is_array($page['breadcrumbs'] ?? null) ? $page['breadcrumbs'] : [];
    $sections = [];
    foreach (($page['sections'] ?? []) as $section) {
        if (!is_array($section)) continue;
        $sectionTitle = trim((string)($section['title'] ?? ''));
        $paragraphs = is_array($section['paragraphs'] ?? null) ? $section['paragraphs'] : [];
        $cleanParagraphs = [];
        foreach ($paragraphs as $paragraph) {
            $paragraph = trim((string)$paragraph);
            if ($paragraph !== '') $cleanParagraphs[] = $paragraph;
        }
        if ($sectionTitle === '' || !$cleanParagraphs) continue;
        $sections[] = [
            'id' => trim((string)($section['id'] ?? '')),
            'title' => $sectionTitle,
            'paragraphs' => $cleanParagraphs,
        ];
    }

    $relatedTitle = trim((string)($page['related_title'] ?? 'Популярные направления'));
    $related = is_array($page['related'] ?? null) ? $page['related'] : [];
    $searchState = is_array($page['search_state'] ?? null) ? $page['search_state'] : [];

    return [
        'title' => $title,
        'description' => $description,
        'h1' => $h1,
        'eyebrow' => trim((string)($page['eyebrow'] ?? '')),
        'intro' => trim((string)($page['intro'] ?? '')),
        'breadcrumbs' => $breadcrumbs,
        'sections' => $sections,
        'related_title' => $relatedTitle,
        'related' => $related,
        'search_state' => $searchState,
    ];
}

function v2_seo_page_meta(array $page): array
{
    $page = v2_seo_page_contract($page);
    return [
        'title' => $page['title'],
        'description' => $page['description'],
        'h1' => $page['h1'],
    ];
}

function v2_seo_render_page_content(array $page, string $searchPath): string
{
    $page = v2_seo_page_contract($page);
    $parts = [];
    $breadcrumbs = v2_seo_render_breadcrumbs($page['breadcrumbs']);
    if ($breadcrumbs !== '') $parts[] = $breadcrumbs;

    $hero = '<header class="v2-seo-page__hero">';
    if ($page['eyebrow'] !== '') $hero .= '<p class="v2-seo-page__eyebrow">'.v2_seo_escape($page['eyebrow']).'</p>';
    $hero .= '<h1>'.v2_seo_escape($page['h1']).'</h1>';
    if ($page['intro'] !== '') $hero .= '<p class="v2-seo-page__intro">'.v2_seo_escape($page['intro']).'</p>';
    $hero .= '</header>';
    $parts[] = $hero;

    foreach ($page['sections'] as $section) {
        $html = v2_seo_render_editorial_section($section['title'], $section['paragraphs'], $section['id']);
        if ($html !== '') $parts[] = $html;
    }

    $related = v2_seo_render_related_links($page['related_title'], $page['related']);
    if ($related !== '') $parts[] = $related;

    $handoff = v2_seo_search_handoff_url($searchPath, $page['search_state']);
    $parts[] = '<aside class="v2-seo-page__search-handoff" aria-label="Поиск туров"><a href="'.v2_seo_escape($handoff).'">Подобрать тур по этим параметрам</a></aside>';

    return '<main class="v2-seo-page">'.implode('', $parts).'</main>';
}
