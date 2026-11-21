<?php
/** V2-only asset URL helper. Browser cache-busting follows file contents, not deploy mtime. */
require_once __DIR__ . '/asset-version-v1.php';
require_once __DIR__ . '/bundle-manifest-v1.php';

function v2_public_base_path(bool $siteRoot = false): string
{
    if (defined('V2_PUBLIC_BASE_PATH')) {
        $override = trim((string)V2_PUBLIC_BASE_PATH);
        return $override === '' || $override === '/' ? '' : '/' . trim($override, '/');
    }

    // Content routes share assets at the site root. An explicit preview base
    // still wins; legacy search entries keep their existing directory default.
    if ($siteRoot) return '';

    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $dir = str_replace('\\', '/', dirname($script));
    if ($dir === '.' || $dir === '/' || $dir === '') return '';
    return '/' . trim($dir, '/');
}

function v2_public_path(string $file, bool $siteRoot = false): string
{
    $name = ltrim($file, '/');
    return v2_public_base_path($siteRoot) . '/' . $name;
}

function v2_asset(string $file, bool $siteRoot = false): string
{
    $name = basename($file);
    if ($name !== $file || !preg_match('/^[a-zA-Z0-9._-]+$/', $name)) {
        throw new InvalidArgumentException('Invalid V2 asset name');
    }
    $path = __DIR__ . '/' . $name;
    $version = v2_asset_content_version($path);
    return v2_public_path(rawurlencode($name), $siteRoot) . '?v=' . rawurlencode($version);
}

function v2_bundle_content_version(string $type, string $scope = 'full'): string
{
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
    return substr(hash_final($ctx), 0, 16);
}

function v2_bundle_asset(string $type, string $scope = 'full'): string
{
    $files = v2_bundle_files($type, $scope);
    $url = v2_public_path('bundle-v1.php', $scope === 'site') . '?type=' . rawurlencode($type) . '&v=' . rawurlencode(v2_bundle_content_version($type, $scope));
    if ($scope !== 'full') $url .= '&scope=' . rawurlencode($scope);
    // Keep source-closure names visible to legacy production verification without creating requests.
    if ($type === 'js') $url .= '#' . implode(',', array_map('rawurlencode', $files));
    return $url;
}
