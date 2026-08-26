<?php
/** V2 catalog-only filesystem TTL cache. Not used by active gateway until promoted. */

function v2_catalog_cache_dir(): string
{
    return __DIR__ . '/.cache/catalogs';
}

function v2_catalog_cache_key(string $path, array $params): string
{
    return hash('sha256', $path . '?' . query_string($params));
}

function v2_catalog_cache_read(string $path, array $params, int $ttl)
{
    if ($ttl <= 0) return null;
    $file = v2_catalog_cache_dir() . '/' . v2_catalog_cache_key($path, $params) . '.json';
    if (!is_file($file)) return null;
    $mtime = @filemtime($file);
    if ($mtime === false || (time() - $mtime) > $ttl) return null;
    $raw = @file_get_contents($file);
    if ($raw === false || $raw === '') return null;
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : null;
}

function v2_catalog_cache_write(string $path, array $params, array $data): void
{
    $dir = v2_catalog_cache_dir();
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) return;
    $file = $dir . '/' . v2_catalog_cache_key($path, $params) . '.json';
    $tmp = $file . '.' . getmypid() . '.tmp';
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false || @file_put_contents($tmp, $json, LOCK_EX) === false) return;
    @rename($tmp, $file);
    if (is_file($tmp)) @unlink($tmp);
}

function v2_catalog_get(string $path, array $params = [], int $ttl = 900, ?callable $loader = null)
{
    $cached = v2_catalog_cache_read($path, $params, $ttl);
    if ($cached !== null) return $cached;
    $load = $loader ?: 'tv_get';
    $data = $load($path, $params);
    if (is_array($data)) v2_catalog_cache_write($path, $params, $data);
    return $data;
}
