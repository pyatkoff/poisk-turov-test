<?php
/** V2-only asset URL helper. Keeps browser cache-busting tied to the deployed file itself. */
function v2_asset(string $file): string
{
    $name = basename($file);
    if ($name !== $file || !preg_match('/^[a-zA-Z0-9._-]+$/', $name)) {
        throw new InvalidArgumentException('Invalid V2 asset name');
    }
    $path = __DIR__ . '/' . $name;
    $version = is_file($path) ? (string)filemtime($path) : '0';
    return '/poisk-turov-test/v2/' . rawurlencode($name) . '?v=' . rawurlencode($version);
}
