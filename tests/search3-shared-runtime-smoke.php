<?php
/** Exercise HTTP bundle bytes and fallback/cache behavior without executing browser requests. */
$root = dirname(__DIR__);
$temp = sys_get_temp_dir() . '/search3-shared-' . bin2hex(random_bytes(8));
mkdir($temp);
function verify_shared(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
}
function render_shared(string $temp, string $scope): string {
    $code = '$_GET=' . var_export(['type' => 'js', 'scope' => $scope], true)
        . '; require ' . var_export($temp . '/bundle-v1.php', true) . ';';
    $output = shell_exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($code));
    verify_shared(is_string($output), 'bundle subprocess failed');
    return $output;
}
try {
    foreach (['assets.php', 'asset-version-v1.php', 'bundle-manifest-v1.php', 'bundle-v1.php', 'search3-shared-runtime.php'] as $name) {
        copy($root . '/v2/' . $name, $temp . '/' . $name);
    }
    require $root . '/v2/assets.php';
    foreach (v2_bundle_files('js', 'full') as $name) copy($root . '/v2/' . $name, $temp . '/' . $name);
    $full = render_shared($temp, 'full');
    $plain = render_shared($temp, 'search3');
    $map = json_decode(file_get_contents($root . '/v2/search3-shared-runtime.json'), true, 512, JSON_THROW_ON_ERROR);
    $target = $temp . '/search3-shared-runtime.json';
    file_put_contents($target, json_encode($map));
    $compact = render_shared($temp, 'search3');
    verify_shared(strlen($compact) < strlen($plain), 'served Search3 response did not shrink');
    verify_shared(render_shared($temp, 'full') === $full, 'legacy response changed');
    foreach (v2_bundle_files('js', 'search3') as $name) {
        $expected = $map['entries'][$name]['code'] ?? file_get_contents($root . '/v2/' . $name);
        verify_shared(str_contains($compact, "\n;/* --- $name --- */\n" . $expected . "\n;\n"), 'missing/reordered script boundary: ' . $name);
    }
    $first = array_key_first($map['entries']);
    foreach (['sourceSha256', 'codeSha256'] as $hash) {
        $invalid = $map;
        $invalid['entries'][$first][$hash] = str_repeat('0', 64);
        file_put_contents($target, json_encode($invalid));
        verify_shared(str_contains(render_shared($temp, 'search3'), file_get_contents($root . '/v2/' . $first)), 'stale/corrupt entry was served');
    }
    file_put_contents($target, '{broken');
    verify_shared(render_shared($temp, 'search3') === $plain, 'corrupt manifest must fall back');
    $oldVersion = v2_bundle_content_version('js', 'full');
    $ctx = hash_init('sha256');
    foreach (v2_bundle_files('js', 'full') as $name) {
        hash_update($ctx, $name . ':' . v2_asset_content_version($root . '/v2/' . $name) . ';');
    }
    verify_shared($oldVersion === substr(hash_final($ctx), 0, 16), 'legacy cache version changed');
    echo 'SEARCH3_SHARED_HTTP_OK before=' . strlen($plain) . ' after=' . strlen($compact) . " legacy=identical stale=source-fallback\n";
} finally {
    foreach (glob($temp . '/*') as $path) unlink($path);
    rmdir($temp);
}
