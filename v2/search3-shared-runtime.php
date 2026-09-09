<?php
/** Build-only derived JS. Missing/stale/corrupt entries fall back to canonical source. */
function v2_search3_compact_script(string $file): ?string
{
    static $entries = null;
    if ($entries === null) {
        $path = __DIR__ . '/search3-shared-runtime.json';
        $data = is_readable($path) ? json_decode((string)file_get_contents($path), true) : null;
        $entries = is_array($data) && ($data['schema_version'] ?? null) === 1
            && ($data['method'] ?? '') === 'local-bindings-only' && is_array($data['entries'] ?? null)
            ? $data['entries'] : [];
    }
    $entry = $entries[$file] ?? null;
    if (basename($file) !== $file || !is_array($entry) || !is_string($entry['code'] ?? null)
        || !is_string($entry['sourceSha256'] ?? null) || !is_string($entry['codeSha256'] ?? null)) return null;
    $sourceHash = is_readable(__DIR__ . '/' . $file) ? hash_file('sha256', __DIR__ . '/' . $file) : false;
    if (!is_string($sourceHash) || !hash_equals($entry['sourceSha256'], $sourceHash)
        || !hash_equals($entry['codeSha256'], hash('sha256', $entry['code']))) return null;
    return $entry['code'];
}
