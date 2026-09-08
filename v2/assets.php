<?php
/** V2-only asset URL helper. Browser cache-busting follows file contents, not deploy mtime. */
require_once __DIR__ . '/asset-version-v1.php';
require_once __DIR__ . '/bundle-manifest-v1.php';

function v2_public_base_path(): string
{
    if (defined('V2_PUBLIC_BASE_PATH')) {
        $override = trim((string)V2_PUBLIC_BASE_PATH);
        return $override === '' || $override === '/' ? '' : '/' . trim($override, '/');
    }

    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $dir = str_replace('\\', '/', dirname($script));
    if ($dir === '.' || $dir === '/' || $dir === '') return '';
    return '/' . trim($dir, '/');
}

function v2_public_path(string $file): string
{
    $name = ltrim($file, '/');
    if ($name === 'api-v2.php' && defined('V2_API_PUBLIC_PATH')) {
        return (string)V2_API_PUBLIC_PATH;
    }
    if ($name === 'lead-adapter-v2.php' && defined('V2_LEAD_PUBLIC_PATH')) {
        return (string)V2_LEAD_PUBLIC_PATH;
    }
    return v2_public_base_path() . '/' . $name;
}

function v2_asset(string $file): string
{
    $name = basename($file);
    if ($name !== $file || !preg_match('/^[a-zA-Z0-9._-]+$/', $name)) {
        throw new InvalidArgumentException('Invalid V2 asset name');
    }
    $path = __DIR__ . '/' . $name;
    $version = v2_asset_content_version($path);
    return v2_public_path(rawurlencode($name)) . '?v=' . rawurlencode($version);
}

function v2_bundle_scope(?string $scope = null): string
{
    if ($scope === null || $scope === '') {
        return defined('V2_SEARCH3_PRESENTATION') && V2_SEARCH3_PRESENTATION === true ? 'search3' : 'full';
    }
    if (!in_array($scope, ['full', 'search3'], true)) {
        throw new InvalidArgumentException('Invalid V2 bundle scope');
    }
    return $scope;
}

function v2_bundle_content_version(string $type, ?string $scope = null): string
{
    $scope = v2_bundle_scope($scope);
    try {
        $files = v2_bundle_files($type, $scope);
    } catch (InvalidArgumentException $error) {
        return '0';
    }
    $ctx = hash_init('sha256');
    foreach ($files as $file) {
        $path = __DIR__ . '/' . $file;
        hash_update($ctx, $file . ':' . v2_asset_content_version($path) . ';');
    }
    if ($scope === 'search3' && $type === 'js') {
        hash_update($ctx, 'compact:' . v2_asset_content_version(__DIR__ . '/search3-shared-runtime.json') . ';');
    }
    return substr(hash_final($ctx), 0, 16);
}

function v2_bundle_asset(string $type, ?string $scope = null): string
{
    $scope = v2_bundle_scope($scope);
    $files = v2_bundle_files($type, $scope);
    $url = v2_public_path('bundle-v1.php') . '?type=' . rawurlencode($type) . '&v=' . rawurlencode(v2_bundle_content_version($type, $scope));
    if ($scope !== 'full') $url .= '&scope=' . rawurlencode($scope);
    // Keep source-closure names visible to legacy production verification without creating requests.
    if ($type === 'js') $url .= '#' . implode(',', array_map('rawurlencode', $files));
    return $url;
}
