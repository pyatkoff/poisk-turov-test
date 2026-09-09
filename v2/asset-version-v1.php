<?php
/** Stable browser cache version based on asset contents, not deploy mtime. */
function v2_asset_content_version(string $path): string
{
    if (!is_file($path) || !is_readable($path)) return '0';
    $hash = @hash_file('sha256', $path);
    return is_string($hash) && $hash !== '' ? substr($hash, 0, 16) : '0';
}
