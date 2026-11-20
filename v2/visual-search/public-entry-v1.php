<?php
/** Source capability copied to poisk-turov/index.php only by a reviewed artifact. */
header('Cache-Control: no-store');
header('Content-Type: text/html; charset=utf-8');
$script = (string)($_SERVER['SCRIPT_NAME'] ?? '');
$rootRoutes = ['/poisk-turov/', '/poisk-turov/index.php'];
$reviewRoutes = ['/_preview/search3-site-candidate/poisk-turov/', '/_preview/search3-site-candidate/poisk-turov/index.php'];
if (!in_array($script, array_merge($rootRoutes, $reviewRoutes), true)) {
    http_response_code(403);
    exit('Public Search3 requires its exact search route.');
}
$visual = dirname(__DIR__) . '/visual-search';
define('V2_VISUAL_ENTRY_ALLOWED', true);
require $visual . '/entry-v1.php';
try {
    $build = v2_visual_search_public_build($visual . '/public-build.json');
    $prefix = in_array($script, $reviewRoutes, true) ? '/_preview/search3-site-candidate' : '';
    if ($prefix !== '' && $build['profile'] !== 'review') {
        http_response_code(403);
        exit('The whole-site preview only accepts the review profile.');
    }
    if (!defined('V2_PUBLIC_BASE_PATH')) define('V2_PUBLIC_BASE_PATH', $prefix);
    if (!defined('V2_CANONICAL_PATH')) define('V2_CANONICAL_PATH', '/poisk-turov/');
    $siteParams = [];
    if ($build['profile'] === 'live') {
        // Reuse the public site configuration without a second Bitrix bootstrap.
        $docRoot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
        $siteConf = $docRoot . '/site_conf.php';
        if ($docRoot !== '' && is_file($siteConf)) require_once $siteConf;
        $siteParams = is_array($params ?? null) ? $params : [];
    }
    $config = v2_visual_search_public_config($build, $prefix);
    if ($build['profile'] === 'review') header('X-Robots-Tag: noindex, nofollow, noarchive');
    echo v2_visual_search_render('public-' . $build['profile'], $config, $siteParams);
} catch (Throwable $error) {
    http_response_code(503);
    echo 'Public Search3 build is unavailable.';
}
