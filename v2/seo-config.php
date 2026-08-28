<?php

declare(strict_types=1);

function v2_seo_is_anytoour_host(): bool
{
    $host = strtolower(trim((string)($_SERVER['HTTP_HOST'] ?? '')));
    $host = preg_replace('/:\d+$/', '', $host) ?: $host;
    return $host === 'anytoour.ru' || $host === 'www.anytoour.ru';
}

function v2_seo_indexable(array $siteParams = []): bool
{
    if (!v2_seo_is_anytoour_host()) {
        return false;
    }

    // Keep launch controlled. Indexing is enabled only through production
    // site configuration after the SEO pages and redirects are ready.
    return !empty($siteParams['SEO_INDEXABLE']);
}

function v2_seo_canonical_url(): string
{
    if (v2_seo_is_anytoour_host()) {
        $path = defined('V2_CANONICAL_PATH') ? trim((string)V2_CANONICAL_PATH) : '/';
        $path = '/' . trim($path, '/');
        if ($path === '/') return 'https://anytoour.ru/';
        return 'https://anytoour.ru' . $path . '/';
    }

    $host = trim((string)($_SERVER['HTTP_HOST'] ?? 'anytour.online'));
    $script = (string)($_SERVER['SCRIPT_NAME'] ?? '/');
    $path = rtrim(str_replace('\\', '/', dirname($script)), '/.');
    $path = $path === '' ? '/' : $path . '/';
    return 'https://' . $host . $path;
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
