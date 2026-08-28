<?php
require_once __DIR__ . '/seo-page-registry-v1.php';

/**
 * Structural quality gate for future public SEO pages.
 * Passing this gate means "editorially complete enough to review for publication";
 * it does not publish a route, add canonical, generate sitemap entries or enable indexing.
 */
function v2_seo_publishability_report(array $record): array
{
    $errors = [];
    $page = is_array($record['page'] ?? null) ? $record['page'] : [];

    try {
        v2_seo_registry_path($record['path'] ?? '');
    } catch (InvalidArgumentException $e) {
        $errors[] = 'invalid_path';
    }

    $type = strtolower(trim((string)($record['type'] ?? '')));
    if (!in_array($type, ['country', 'resort', 'seasonal'], true)) $errors[] = 'invalid_type';

    try {
        $page = v2_seo_page_contract($page);
    } catch (InvalidArgumentException $e) {
        $errors[] = 'invalid_page_contract';
        return ['publishable' => false, 'errors' => array_values(array_unique($errors))];
    }

    if (trim((string)$page['intro']) === '') $errors[] = 'missing_intro';

    $sections = is_array($page['sections'] ?? null) ? $page['sections'] : [];
    if (count($sections) < 2) $errors[] = 'insufficient_editorial_sections';
    $seenSectionTitles = [];
    foreach ($sections as $section) {
        $title = mb_strtolower(trim((string)($section['title'] ?? '')), 'UTF-8');
        if ($title === '' || isset($seenSectionTitles[$title])) {
            $errors[] = 'duplicate_or_empty_section_title';
            continue;
        }
        $seenSectionTitles[$title] = true;
    }

    $breadcrumbs = is_array($page['breadcrumbs'] ?? null) ? $page['breadcrumbs'] : [];
    if (count($breadcrumbs) < 2) {
        $errors[] = 'insufficient_breadcrumbs';
    } else {
        $lastIndex = array_key_last($breadcrumbs);
        $seenBreadcrumbHrefs = [];
        foreach ($breadcrumbs as $index => $crumb) {
            if (!is_array($crumb)) {
                $errors[] = 'breadcrumb_chain_invalid';
                continue;
            }
            $label = trim((string)($crumb['label'] ?? ''));
            if ($index === $lastIndex) {
                $href = trim((string)($crumb['href'] ?? ''));
                if ($label === '' || $href !== '') $errors[] = 'breadcrumb_current_page_invalid';
                continue;
            }

            $href = v2_seo_stable_internal_href($crumb['href'] ?? '');
            if ($label === '' || $href === null || isset($seenBreadcrumbHrefs[$href])) {
                $errors[] = 'breadcrumb_chain_invalid';
                continue;
            }
            $seenBreadcrumbHrefs[$href] = true;
        }
    }

    $related = is_array($page['related'] ?? null) ? $page['related'] : [];
    $internalGroups = is_array($page['internal_links'] ?? null) ? $page['internal_links'] : [];
    $hasRelated = v2_seo_render_related_links((string)($page['related_title'] ?? ''), $related) !== '';
    $hasInternal = v2_seo_render_internal_link_groups($internalGroups) !== '';
    if (!$hasRelated && !$hasInternal) $errors[] = 'missing_curated_internal_links';

    $searchState = is_array($page['search_state'] ?? null) ? $page['search_state'] : [];
    $transientKeys = ['dateFrom','dateTo','daysFrom','daysTill','price_from','price_till','hotel','operator'];
    foreach ($transientKeys as $key) {
        if (array_key_exists($key, $searchState) && trim((string)$searchState[$key]) !== '') {
            $errors[] = 'transient_search_state';
            break;
        }
    }

    return [
        'publishable' => !$errors,
        'errors' => array_values(array_unique($errors)),
    ];
}

function v2_seo_is_publishable_candidate(array $record): bool
{
    return v2_seo_publishability_report($record)['publishable'] === true;
}
