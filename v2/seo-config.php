<?php

declare(strict_types=1);

function v2_seo_is_anytoour_host(): bool
{
    $host = strtolower(trim((string)($_SERVER['HTTP_HOST'] ?? '')));
    $host = preg_replace('/:\d+$/', '', $host) ?: $host;
    return $host === 'anytoour.ru' || $host === 'www.anytoour.ru';
}

function v2_seo_normalize_path(string $path): string
{
    $path = parse_url($path, PHP_URL_PATH) ?: '/';
    $path = '/' . trim($path, '/');
    return $path === '/' ? '/' : $path . '/';
}

function v2_seo_request_path(): string
{
    if (defined('V2_CANONICAL_PATH')) {
        return v2_seo_normalize_path((string)V2_CANONICAL_PATH);
    }
    return v2_seo_normalize_path((string)($_SERVER['REQUEST_URI'] ?? '/'));
}

function v2_seo_indexable(array $siteParams = []): bool
{
    if (!v2_seo_is_anytoour_host() || empty($siteParams['SEO_INDEXABLE'])) {
        return false;
    }

    // A global launch flag alone must never open the whole site. Indexation is
    // opt-in per clean path so the first SEO release can be rolled out as a
    // narrow, reversible slice.
    $allowed = $siteParams['SEO_INDEXABLE_PATHS'] ?? [];
    if (!is_array($allowed) || $allowed === []) {
        return false;
    }

    $current = v2_seo_request_path();
    foreach ($allowed as $path) {
        if (!is_string($path) || trim($path) === '') continue;
        if (v2_seo_normalize_path($path) === $current) return true;
    }
    return false;
}

function v2_seo_canonical_url(): string
{
    if (v2_seo_is_anytoour_host()) {
        $path = defined('V2_CANONICAL_PATH') ? trim((string)V2_CANONICAL_PATH) : '/';
        $path = '/' . trim($path, '/');
        if ($path === '/') return 'https://anytoour.ru/';
        return 'https://anytoour.ru' . $path . '/';
    }

    // The anytour.online V2 route is compatibility-only. Keep it noindex,
    // but consolidate any accidental discovery toward the standalone search.
    return 'https://anytoour.ru/poisk-turov/';
}

function v2_seo_robots_content(bool $indexable): string
{
    return $indexable
        ? 'index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1'
        : 'noindex,follow,max-image-preview:large';
}

function v2_seo_schema(string $phone, string $description): array
{
    $siteUrl = v2_seo_is_anytoour_host() ? 'https://anytoour.ru/' : v2_seo_canonical_url();
    $graph = [
        [
            '@type' => 'WebSite',
            '@id' => $siteUrl . '#website',
            'url' => $siteUrl,
            'name' => 'AnyTour',
            'inLanguage' => 'ru-RU',
            'description' => $description,
        ],
        [
            '@type' => 'TravelAgency',
            '@id' => $siteUrl . '#travel-agency',
            'name' => 'AnyTour',
            'url' => $siteUrl,
            'logo' => $siteUrl . 'images/logo.svg',
            'telephone' => $phone,
            'description' => $description,
        ],
    ];

    return [
        '@context' => 'https://schema.org',
        '@graph' => $graph,
    ];
}
