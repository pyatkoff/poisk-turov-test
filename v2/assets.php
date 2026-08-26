<?php
/** V2-only asset URL helper. Browser cache-busting follows file contents, not deploy mtime. */
require_once __DIR__ . '/asset-version-v1.php';

function v2_asset(string $file): string
{
    $name = basename($file);
    if ($name !== $file || !preg_match('/^[a-zA-Z0-9._-]+$/', $name)) {
        throw new InvalidArgumentException('Invalid V2 asset name');
    }
    $path = __DIR__ . '/' . $name;
    $version = v2_asset_content_version($path);
    return '/poisk-turov-test/v2/' . rawurlencode($name) . '?v=' . rawurlencode($version);
}
