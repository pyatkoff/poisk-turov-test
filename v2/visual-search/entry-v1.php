<?php
/** Internal renderer. Only the guarded preview/public wrappers may invoke it. */
if (!defined('V2_VISUAL_ENTRY_ALLOWED') || V2_VISUAL_ENTRY_ALLOWED !== true) {
    http_response_code(403);
    exit('Search3 renderer requires its guarded entry.');
}

function v2_visual_search_live_scripts(): array
{
    // The only authored LIVE list; compiler/inventory read this exact declaration.
    $scripts = [
        '../prototype-search/config.js', '../runtime-v3.js', '../lead-search-context.js',
        './lead-runtime-v1.js', '../search3-canonical-profiles-v1.js',
        '../search3-local-db-provider-v1.js', '../prototype-search/data.js',
        '../prototype-search/calendar-exact-hotel-v1.js',
        '../prototype-search/source-receipt-v1.js', '../prototype-search/search-lifecycle-v1.js',
        '../prototype-search/lead.js', '../prototype-search/hotel-popularity-v1.js',
        './live-bridge.js', './flight-picker-v18.js', '../prototype-search/messenger-context-v1.js',
        '../prototype-search/messenger-flow-v1.js', './filter-panel-v1.js', './app.js'
    ];
    return $scripts;
}

function v2_visual_search_graph(string $mode, string $html): array
{
    if (!in_array($mode, ['preview-live', 'preview-offline', 'public-live', 'public-review'], true)) {
        throw new InvalidArgumentException('Unknown Search3 entry profile.');
    }
    preg_match_all('/<script src="([^"?]+\.js)" defer><\/script>/', $html, $matches);
    $offline = $matches[1];
    if (!$offline || $offline[0] !== './fixture-data.js' || end($offline) !== './app.js') {
        throw new RuntimeException('Search3 offline graph source drift.');
    }
    $publicConfigScript = './public-config-v1.js';
    $publicAnalyticsScript = '../analytics-v4.js';
    $publicBeforeApp = ['./public-url-v1.js', './public-bootstrap-v1.js', './public-analytics-v1.js'];
    $graph = str_ends_with($mode, '-live') ? v2_visual_search_live_scripts() : $offline;
    if ($mode === 'public-live') {
        if ($graph[0] !== '../prototype-search/config.js' || $graph[1] !== '../runtime-v3.js' || end($graph) !== './app.js') {
            throw new RuntimeException('Search3 public graph source drift.');
        }
        $graph[0] = $publicConfigScript;
        array_splice($graph, 2, 0, [$publicAnalyticsScript]);
        array_splice($graph, count($graph) - 1, 0, $publicBeforeApp);
    } elseif ($mode === 'public-review') {
        array_unshift($graph, $publicConfigScript);
        array_splice($graph, count($graph) - 1, 0, array_slice($publicBeforeApp, 0, 2));
    }
    if (count($graph) !== count(array_unique($graph))) throw new RuntimeException('Duplicate Search3 script owner.');
    foreach ($graph as $src) {
        if (!preg_match('/^\.\.?\/[\w.\/-]+\.js$/D', $src) || str_contains($src, '/../')) {
            throw new RuntimeException('Unbounded Search3 script owner.');
        }
    }
    return $graph;
}

function v2_visual_search_public_build(string $path): array
{
    if (!is_file($path) || is_link($path) || filesize($path) > 2048) {
        throw new RuntimeException('Public Search3 build profile is unavailable.');
    }
    $build = json_decode((string)file_get_contents($path), true, 8, JSON_THROW_ON_ERROR);
    if (!is_array($build)) throw new RuntimeException('Invalid public Search3 build profile.');
    $keys = array_keys($build); sort($keys);
    if ($keys !== ['profile', 'scenario', 'schemaVersion'] || $build['schemaVersion'] !== 1
        || !in_array($build['profile'], ['review', 'live'], true)
        || !in_array($build['scenario'], ['family', 'snapshot'], true)) {
        throw new RuntimeException('Invalid public Search3 build profile.');
    }
    return $build;
}

function v2_visual_search_public_config(array $build, string $prefix, string $entry = 'search'): array
{
    if (!in_array($prefix, ['', '/_preview/search3-site-candidate'], true)
        || ($prefix !== '' && $build['profile'] !== 'review')
        || !in_array($entry, ['search', 'home'], true)) {
        throw new RuntimeException('Public Search3 route/profile mismatch.');
    }
    $live = $build['profile'] === 'live';
    $counter = 0;
    if ($live) {
        $analytics = dirname(__DIR__) . '/analytics-config.php';
        if (!is_file($analytics)) throw new RuntimeException('Existing analytics configuration is unavailable.');
        require_once $analytics;
        $counter = v2_metrika_counter_id();
    }
    $config = [
        'schemaVersion' => 1, 'profile' => $build['profile'], 'scenario' => $build['scenario'],
        'publicBase' => $prefix . '/poisk-turov/', 'resourceBase' => $prefix . '/visual-search/',
        'api' => '/api-v2.php',
        'leadApi' => '/_preview/search3-next-candidate/preview-lead-disabled.php',
        'anexApi' => $live ? '/_preview/search3-anex-candidate/api-anex-search3-preview.php' : null,
        'metrikaCounter' => $counter,
    ];
    if ($entry === 'home') $config['entry'] = 'home';
    return $config;
}

/** Reuse the exact Search3 form, dialogs and script graph inside the home shell. */
function v2_visual_search_home_parts(array $config, array $siteParams = []): array
{
    $prefix = substr((string)($config['publicBase'] ?? ''), 0, -strlen('/poisk-turov/'));
    $script = (string)($_SERVER['SCRIPT_NAME'] ?? '');
    if (($config['entry'] ?? null) !== 'home'
        || !in_array($prefix, ['', '/_preview/search3-site-candidate'], true)
        || !in_array($script, [$prefix . '/', $prefix . '/index.php'], true)) {
        throw new RuntimeException('Search3 home form requires its exact home route.');
    }
    $html = v2_visual_search_render('public-' . $config['profile'], $config, $siteParams);
    if (!preg_match('/<head\b[^>]*>(.*?)<\/head>/s', $html, $head)
        || !preg_match('/<body\b[^>]*>(.*?)<main>(.*?)<\/main>(.*?)<\/body>/s', $html, $body)) {
        throw new RuntimeException('Search3 home composition source drift.');
    }
    $boundary = strpos($body[2], '<section class="results-section');
    if ($boundary === false) throw new RuntimeException('Search3 results boundary is unavailable.');
    $form = substr($body[2], 0, $boundary);
    $form = v2_visual_search_replace($form, '<h1 id="page-title">Подберём ваш отдых</h1>',
        '<h2 id="page-title">Найти тур</h2>');
    $form = preg_replace('/(<button\b[^>]*\bid="quick-budget"[^>]*>.*?<\/button>)/s',
        '<details class="home-search-more"><summary>Дополнительные параметры</summary>$1</details>', $form, 1, $count);
    if ($form === null || $count !== 1) throw new RuntimeException('Search3 budget control source drift.');
    // Price observations belong to the result page. Home supplies search conditions only.
    $form = preg_replace('/<details class="demo-note preview-disclosure">.*?<\/details>/s', '', $form);
    if ($form === null) throw new RuntimeException('Search3 home disclosure source drift.');
    preg_match_all('/<link\b[^>]*rel="stylesheet"[^>]*>|<script\b[^>]*src="[^"]+"[^>]*><\/script>/', $head[1], $assets);
    $before = preg_replace('/<a\b[^>]*class="skip"[^>]*>.*?<\/a>|<header\b.*?<\/header>/s', '', $body[1]);
    $after = preg_replace('/<footer\b.*?<\/footer>/s', '', $body[3]);
    if (!$assets[0] || $before === null || $after === null) throw new RuntimeException('Search3 home support source drift.');
    return [
        'head' => implode("\n", $assets[0]),
        'form' => $form,
        'support' => '<div hidden inert data-home-search-support>' . $before . substr($body[2], $boundary) . '</div>' . $after,
    ];
}

function v2_visual_search_replace(string $html, string $old, string $new): string
{
    if (substr_count($html, $old) !== 1) throw new RuntimeException('Search3 HTML source drift.');
    return str_replace($old, $new, $html);
}

function v2_visual_search_render(string $mode, array $config = [], array $siteParams = []): string
{
    $html = file_get_contents(__DIR__ . '/index.html');
    if ($html === false) throw new RuntimeException('Search3 HTML is unavailable.');
    $graph = v2_visual_search_graph($mode, $html);
    $public = str_starts_with($mode, 'public-');
    $live = str_ends_with($mode, '-live');
    if ($public && ($config['profile'] ?? null) !== ($live ? 'live' : 'review')) {
        throw new RuntimeException('Search3 render/profile mismatch.');
    }
    require_once dirname(__DIR__) . '/site-header-v2.php';
    $phone = v2_site_phone($siteParams, '8 (800) 100 - 61 - 50');
    ob_start();
    v2_render_site_header($phone, v2_phone_href($phone), '/poisk-turov/', true);
    $header = trim((string)ob_get_clean());
    $html = v2_visual_search_replace($html, '<header class="header" data-site-header-slot></header>', $header);
    $html = v2_visual_search_replace($html, 'class="brand footer-brand" href="/"',
        'class="brand footer-brand" href="' . htmlspecialchars(v2_site_href('/'), ENT_QUOTES, 'UTF-8') . '"');
    if ($mode !== 'preview-offline') {
        $tags = [];
        foreach ($graph as $src) {
            $attributes = $src === '../prototype-search/config.js'
                ? ' data-local-tv-catalog="enabled" data-search3-messenger="enabled" data-search3-tv-session="enabled"' : '';
            if ($src === './public-config-v1.js') {
                $json = json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                $attributes = ' data-search3-public-config="' . htmlspecialchars($json, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
            }
            $tags[] = '<script src="' . $src . '"' . $attributes . ' defer></script>';
        }
        $html = preg_replace('/<script src="\.\/fixture-data\.js" defer><\/script>.*?<script src="\.\/app\.js" defer><\/script>/s', implode("\n  ", $tags), $html, 1, $count);
        if ($count !== 1 || $html === null) throw new RuntimeException('Search3 script span source drift.');
    }
    if ($live) {
        $html = str_replace('Здесь сохранённые предложения и учебные примеры. Цены и рейсы приведены для знакомства с поиском. Заявка в этом режиме учебная.',
            'Календарь показывает ранее найденные цены. При выборе тура проверяем стоимость и условия у поставщика. Чтобы передать тур менеджеру, откройте Telegram или MAX из заявки и запустите бота.', $html);
        $popularitySource = dirname(__DIR__) . '/data/hotel-popularity-v1.php';
        if (is_file($popularitySource)) {
            require_once $popularitySource;
            if (function_exists('v2_hotel_popularity_legacy_ids')) {
                $ids = array_filter(array_map('intval', v2_hotel_popularity_legacy_ids(v2_hotel_popularity_registry())), fn($id) => $id > 0);
                $html = str_replace('<body>', '<body data-popular-hotel-legacy-ids="' . implode(',', $ids) . '">', $html);
            }
        }
    }
    if ($public) {
        $html = v2_visual_search_replace($html,
            '<span class="private-badge"><span class="status-dot"></span> Версия для проверки</span>', '');
        $html = preg_replace('/<details class="prototype-tools wrap">.*?<\/details>/s', '', $html, 1, $count);
        if ($count !== 1 || $html === null) throw new RuntimeException('Search3 review-controls source drift.');
        if (!$live) {
            $html = preg_replace('/<details class="demo-note preview-disclosure">.*?<\/details>/s',
                '<p class="form-price-note" role="status">Проверочная версия: сохранённые/тестовые данные, заявки не отправляются.</p>', $html, 1, $count);
            if ($count !== 1 || $html === null) throw new RuntimeException('Search3 review-disclosure source drift.');
        }
        if ($live) {
            require_once dirname(__DIR__) . '/seo-config.php';
            $robots = v2_seo_robots_content(v2_seo_indexable($siteParams));
            $html = v2_visual_search_replace($html, '<meta name="robots" content="noindex, nofollow, noarchive">',
                '<meta name="robots" content="' . htmlspecialchars($robots, ENT_QUOTES, 'UTF-8') . '">');
        }
        $html = v2_visual_search_replace($html, '</head>', '<link rel="canonical" href="https://anytoour.ru/poisk-turov/">' . "\n</head>");
    }
    return preg_replace_callback('/\b(src|href|data-offer-list-src|data-hotel-details-src|data-flight-picker-src)="(\.\.?\/[^"?]+\.([a-zA-Z0-9]+))"/',
        function ($match) use ($public, $config) {
            $ref = $match[2]; $extension = strtolower($match[3]);
            $path = __DIR__ . '/' . $ref;
            if (!$public) {
                if (!in_array($extension, ['js', 'css'], true) || !is_file($path)) return $match[0];
                return $match[1] . '="' . $ref . '?v=' . substr(hash_file('sha256', $path), 0, 12) . '"';
            }
            if (!preg_match('/^\.\.?\/[\w.\/-]+$/D', $ref) || str_contains($ref, '/../') || !is_file($path)) {
                throw new RuntimeException('Missing or unbounded public Search3 asset.');
            }
            $resourceBase = $config['resourceBase'];
            $prefix = substr($resourceBase, 0, -strlen('/visual-search/'));
            $url = str_starts_with($ref, './') ? $resourceBase . substr($ref, 2) : $prefix . '/' . substr($ref, 3);
            if (in_array($extension, ['js', 'css'], true)) $url .= '?v=' . substr(hash_file('sha256', $path), 0, 12);
            return $match[1] . '="' . $url . '"';
        }, $html);
}
