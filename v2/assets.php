<?php
/** V2-only asset URL helper. Browser cache-busting follows file contents, not deploy mtime. */
require_once __DIR__ . '/asset-version-v1.php';
require_once __DIR__ . '/bundle-manifest-v1.php';

function v2_public_base_path(): string
{
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $dir = str_replace('\\', '/', dirname($script));
    if ($dir === '.' || $dir === '/' || $dir === '') return '';
    return '/' . trim($dir, '/');
}

function v2_public_path(string $file): string
{
    $name = ltrim($file, '/');
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

function v2_bundle_content_version(string $type): string
{
    $manifest = v2_bundle_manifest();
    if (!isset($manifest[$type])) return '0';
    $ctx = hash_init('sha256');
    foreach ($manifest[$type] as $file) {
        $path = __DIR__ . '/' . $file;
        hash_update($ctx, $file . ':' . v2_asset_content_version($path) . ';');
    }
    return substr(hash_final($ctx), 0, 16);
}

function v2_bundle_asset(string $type): string
{
    $manifest = v2_bundle_manifest();
    if (!isset($manifest[$type])) throw new InvalidArgumentException('Invalid V2 bundle type');
    $url = v2_public_path('bundle-v1.php') . '?type=' . rawurlencode($type) . '&v=' . rawurlencode(v2_bundle_content_version($type));
    // Keep source-closure names visible to legacy production verification without creating requests.
    if ($type === 'js') $url .= '#' . implode(',', array_map('rawurlencode', $manifest['js']));
    return $url;
}
