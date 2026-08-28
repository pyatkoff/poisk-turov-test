<?php
/**
 * Route-independent server-rendered primitives for future indexable AnyTour pages.
 * These helpers intentionally do not alter the current V2 search route or its indexing policy.
 */

function v2_seo_escape($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function v2_seo_internal_href($value): ?string
{
    $href = trim((string)$value);
    if ($href === '' || $href[0] !== '/' || str_starts_with($href, '//')) return null;
    if (preg_match('/[\x00-\x1F\x7F]/', $href)) return null;
    return $href;
}

function v2_seo_stable_internal_href($value): ?string
{
    $href = v2_seo_internal_href($value);
    if ($href === null || str_contains($href, '?') || str_contains($href, '#')) return null;
    return $href;
}

function v2_seo_render_breadcrumbs(array $items): string
{
    $normalized = [];
    foreach ($items as $item) {
        if (!is_array($item)) continue;
        $label = trim((string)($item['label'] ?? ''));
        if ($label === '') continue;
        $normalized[] = [
            'label' => $label,
            'href' => v2_seo_stable_internal_href($item['href'] ?? ''),
        ];
    }
    if (!$normalized) return '';

    $parts = [];
    $lastIndex = array_key_last($normalized);
    foreach ($normalized as $index => $item) {
        if ($index === $lastIndex) {
            $parts[] = '<li aria-current="page">'.v2_seo_escape($item['label']).'</li>';
            continue;
        }
        if ($item['href'] === null) continue;
        $parts[] = '<li><a href="'.v2_seo_escape($item['href']).'">'.v2_seo_escape($item['label']).'</a></li>';
    }
    if (!$parts) return '';
    return '<nav class="v2-seo-breadcrumbs" aria-label="Хлебные крошки"><ol>'.implode('', $parts).'</ol></nav>';
}

function v2_seo_render_editorial_section(string $title, array $paragraphs, string $id = ''): string
{
    $title = trim($title);
    if ($title === '') return '';
    $safeId = preg_replace('/[^a-zA-Z0-9_-]+/', '-', trim($id));
    $body = [];
    foreach ($paragraphs as $paragraph) {
        $paragraph = trim((string)$paragraph);
        if ($paragraph !== '') $body[] = '<p>'.v2_seo_escape($paragraph).'</p>';
    }
    if (!$body) return '';
    $idAttr = $safeId !== '' ? ' id="'.v2_seo_escape($safeId).'"' : '';
    return '<section class="v2-seo-editorial"'.$idAttr.'><h2>'.v2_seo_escape($title).'</h2>'.implode('', $body).'</section>';
}

function v2_seo_render_related_links(string $title, array $links): string
{
    $title = trim($title);
    if ($title === '') return '';
    $items = [];
    $seenHrefs = [];
    foreach ($links as $link) {
        if (!is_array($link)) continue;
        $label = trim((string)($link['label'] ?? ''));
        $href = v2_seo_stable_internal_href($link['href'] ?? '');
        if ($label === '' || $href === null || isset($seenHrefs[$href])) continue;
        $seenHrefs[$href] = true;
        $items[] = '<li><a href="'.v2_seo_escape($href).'">'.v2_seo_escape($label).'</a></li>';
    }
    if (!$items) return '';
    return '<nav class="v2-seo-related" aria-label="'.v2_seo_escape($title).'"><h2>'.v2_seo_escape($title).'</h2><ul>'.implode('', $items).'</ul></nav>';
}

function v2_seo_search_handoff_url(string $searchPath, array $state): string
{
    $path = v2_seo_stable_internal_href($searchPath);
    if ($path === null) throw new InvalidArgumentException('Search handoff path must be a stable first-party path');

    $allowed = [
        'from','country','dateFrom','dateTo','daysFrom','daysTill','count_people',
        'region','subregion','hotel','operator','stars','rating','food','onlyDirect','onlyCharter'
    ];
    $query = [];
    foreach ($allowed as $key) {
        if (!array_key_exists($key, $state) || is_array($state[$key]) || is_object($state[$key])) continue;
        $value = trim((string)$state[$key]);
        if ($value === '' || strlen($value) > 120) continue;
        $query[$key] = $value;
    }
    if (!$query) return $path;
    return $path.'?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
}
