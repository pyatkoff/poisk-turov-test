<?php
require_once __DIR__ . '/seo-page-contract-v1.php';

/**
 * Route-independent page-type adapters for future indexable AnyTour destination pages.
 * They shape editorial data only; no route, canonical or indexing policy is published here.
 */
function v2_seo_destination_page(string $type, array $data): array
{
    $type = strtolower(trim($type));
    if (!in_array($type, ['country', 'resort', 'seasonal'], true)) {
        throw new InvalidArgumentException('Unsupported SEO destination page type');
    }

    $name = trim((string)($data['name'] ?? ''));
    if ($name === '') throw new InvalidArgumentException('SEO destination name is required');

    $page = [
        'title' => trim((string)($data['title'] ?? '')),
        'description' => trim((string)($data['description'] ?? '')),
        'h1' => trim((string)($data['h1'] ?? '')),
        'eyebrow' => trim((string)($data['eyebrow'] ?? '')),
        'intro' => trim((string)($data['intro'] ?? '')),
        'breadcrumbs' => is_array($data['breadcrumbs'] ?? null) ? $data['breadcrumbs'] : [],
        'sections' => is_array($data['sections'] ?? null) ? $data['sections'] : [],
        'related_title' => trim((string)($data['related_title'] ?? 'Популярные направления')),
        'related' => is_array($data['related'] ?? null) ? $data['related'] : [],
        'internal_links' => is_array($data['internal_links'] ?? null) ? $data['internal_links'] : [],
        'search_state' => is_array($data['search_state'] ?? null) ? $data['search_state'] : [],
    ];

    if ($page['h1'] === '') {
        $page['h1'] = match ($type) {
            'country' => 'Туры в '.$name,
            'resort' => 'Туры в '.$name,
            'seasonal' => 'Туры: '.$name,
        };
    }
    if ($page['title'] === '') $page['title'] = $page['h1'].' — AnyTour';

    $contract = v2_seo_page_contract($page);
    $contract['page_type'] = $type;
    $contract['entity_name'] = $name;
    return $contract;
}

function v2_seo_country_page(array $data): array
{
    return v2_seo_destination_page('country', $data);
}

function v2_seo_resort_page(array $data): array
{
    return v2_seo_destination_page('resort', $data);
}

function v2_seo_seasonal_page(array $data): array
{
    return v2_seo_destination_page('seasonal', $data);
}
