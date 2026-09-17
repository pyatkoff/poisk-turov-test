<?php
declare(strict_types=1);

function access_check(bool $condition, string $label): void
{
    if (!$condition) {
        fwrite(STDERR, "SEARCH3_LOCAL_DATA_ACCESS_FAIL: {$label}\n");
        exit(1);
    }
}

$access = file_get_contents(__DIR__ . '/../v2/data/.htaccess');
$endpoint = file_get_contents(__DIR__ . '/../v2/data/search3-local-results-read-v1.php');
access_check(is_string($access) && is_string($endpoint), 'fixtures');

preg_match_all('/<Files\s+"([^"]+)"\s*>\s*Require\s+all\s+granted\s*<\/Files>/i', $access, $matches);
$granted = $matches[1] ?? [];
sort($granted);
$expected = ['hotel-details-read-v1.php', 'search3-local-results-read-v1.php'];
sort($expected);
access_check($granted === $expected, 'exact_read_only_allowlist');
access_check(substr_count(strtolower($access), 'require all granted') === 2, 'no_extra_grants');
access_check(!preg_match('/<FilesMatch\b/i', $access), 'no_broad_pattern_grant');
access_check(str_contains($endpoint, 'str_ends_with($normalized,\'/_preview/search3-local-candidate/data\')'), 'local_candidate_filesystem_guard');
access_check(str_contains($endpoint, 'HTTP_X_REQUESTED_WITH'), 'search3_header_guard');
access_check(str_contains($endpoint, 'REQUEST_METHOD'), 'post_method_guard');
access_check(!preg_match('/\b(?:INSERT|UPDATE|DELETE|REPLACE)\b/i', $endpoint), 'read_endpoint_has_no_write_sql');

echo "SEARCH3_LOCAL_DATA_ACCESS_OK grants=2 local_guard=1 readonly=1\n";
