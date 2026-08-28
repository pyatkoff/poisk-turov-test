<?php
require_once __DIR__ . '/seo-page-primitives-v1.php';

/**
 * Normalize curated internal-link groups for future indexable destination pages.
 * Search/filter state is intentionally excluded: only stable first-party paths survive.
 */
function v2_seo_internal_link_groups(array $groups): array
{
    $normalized = [];
    $seenHrefs = [];

    foreach ($groups as $group) {
        if (!is_array($group)) continue;
        $title = trim((string)($group['title'] ?? ''));
        $links = is_array($group['links'] ?? null) ? $group['links'] : [];
        if ($title === '') continue;

        $cleanLinks = [];
        foreach ($links as $link) {
            if (!is_array($link)) continue;
            $label = trim((string)($link['label'] ?? ''));
            $href = v2_seo_internal_href($link['href'] ?? '');
            if ($label === '' || $href === null || isset($seenHrefs[$href])) continue;

            $seenHrefs[$href] = true;
            $cleanLinks[] = ['label' => $label, 'href' => $href];
        }

        if ($cleanLinks) $normalized[] = ['title' => $title, 'links' => $cleanLinks];
    }

    return $normalized;
}

function v2_seo_render_internal_link_groups(array $groups): string
{
    $groups = v2_seo_internal_link_groups($groups);
    if (!$groups) return '';

    $parts = [];
    foreach ($groups as $group) {
        $items = [];
        foreach ($group['links'] as $link) {
            $items[] = '<li><a href="'.v2_seo_escape($link['href']).'">'.v2_seo_escape($link['label']).'</a></li>';
        }
        $parts[] = '<section class="v2-seo-link-group"><h2>'.v2_seo_escape($group['title']).'</h2><ul>'.implode('', $items).'</ul></section>';
    }

    return '<nav class="v2-seo-internal-links" aria-label="Другие направления">'.implode('', $parts).'</nav>';
}
