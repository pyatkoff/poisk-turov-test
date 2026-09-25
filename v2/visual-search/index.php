<?php
// Isolated migration entry. Never expose this candidate as a production search.
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('Cache-Control: no-store');
header('Content-Type: text/html; charset=utf-8');
if (!str_starts_with((string)($_SERVER['SCRIPT_NAME'] ?? ''), '/_preview/search3-next-candidate/visual-search/')) {
    http_response_code(403);
    exit('Visual migration is only available in its separate preview.');
}
$html = file_get_contents(__DIR__ . '/index.html');
$scenario = is_string($_GET['scenario'] ?? null) ? $_GET['scenario'] : '';
$offline = in_array($scenario, ['snapshot', 'flights', 'mixed', 'family', 'incomplete', 'price-change', 'unavailable', 'expired', 'flight-error', 'empty', 'recorded'], true);
if (!$offline) {
    // Reuse the functional owners. This route has no second search/price/lead transport.
    $scripts = [
        '../prototype-search/config.js', '../runtime-v3.js', '../lead-search-context.js',
        '../tour-controller-v4.js', '../lead-form-guard-v1.js', '../search3-canonical-profiles-v1.js',
        '../search3-local-db-provider-v1.js', '../prototype-search/data.js',
        '../prototype-search/calendar-exact-hotel-v1.js',
        '../prototype-search/source-receipt-v1.js', '../prototype-search/search-lifecycle-v1.js',
        '../prototype-search/lead.js', '../prototype-search/hotel-popularity-v1.js',
        './live-bridge.js', './flight-picker-v18.js', './app.js'
    ];
    $graph = implode("\n  ", array_map(fn($src) => '<script src="' . $src . '" defer></script>', $scripts));
    $html = preg_replace('/<script src="\.\/fixture-data\.js" defer><\/script>.*?<script src="\.\/app\.js" defer><\/script>/s', $graph, $html);
    $html = str_replace('Прототип · цены требуют проверки', 'Версия для проверки · живой поиск', $html);
    $html = str_replace('Сохранённая выдача и демонстрационные сценарии. Живой поиск ещё не подключён. Заявки и оплата отключены.',
        'Актуальные предложения — ТВ, САМО и ANEX. Календарь показывает ранее найденные цены из базы. Поиск начинается только по нажатию «Найти туры». Заявки не отправляются.', $html);
    $popularitySource = dirname(__DIR__) . '/data/hotel-popularity-v1.php';
    if (is_file($popularitySource)) {
        require_once $popularitySource;
        if (function_exists('v2_hotel_popularity_legacy_ids')) {
            $ids = array_filter(array_map('intval', v2_hotel_popularity_legacy_ids()), fn($id) => $id > 0);
            $html = str_replace('<body>', '<body data-popular-hotel-legacy-ids="' . implode(',', $ids) . '">', $html);
        }
    }
}
echo preg_replace_callback('/\b(src|href)="(\.\.?\/[^"?]+\.(?:js|css))"/', function ($match) {
    $path = __DIR__ . '/' . $match[2];
    if (!is_file($path)) return $match[0];
    return $match[1] . '="' . $match[2] . '?v=' . substr(hash_file('sha256', $path), 0, 12) . '"';
}, $html);
