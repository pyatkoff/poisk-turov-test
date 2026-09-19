<?php
declare(strict_types=1);

const HMS34V2_OPERATION = 'hotel-match-common4-samo34-live-1971-20260919-v2';
const HMS34V2_V1_OPERATION = 'hotel-match-common4-samo34-live-1971-20260919-v1';
const HMS34V2_V1_SHA256 = '0dc3e14e22d797102aac54d82ee01c2ca75d7e9ba889d12e8b19e9244c89e7b0';
const HMS34V2_MAX_SOURCE_BYTES = 1048576;

function hms34v2_require(bool $ok, string $why): void
{
    if (!$ok) {
        throw new RuntimeException($why);
    }
}

function hms34v2_transform_raw(string $raw): string
{
    hms34v2_require(substr_count($raw, HMS34V2_V1_OPERATION) === 1, 'v1_operation_occurrence');
    $next = str_replace(HMS34V2_V1_OPERATION, HMS34V2_OPERATION, $raw, $count);
    hms34v2_require($count === 1, 'operation_replace_count');
    hms34v2_require(strpos($next, HMS34V2_V1_OPERATION) === false, 'old_operation_retained');
    hms34v2_require(substr_count($next, HMS34V2_OPERATION) === 1, 'new_operation_occurrence');
    return $next;
}

function hms34v2_materialize(string $source, string $destination): string
{
    $real = realpath($source);
    hms34v2_require(is_string($real) && $real === $source && is_file($real) && !is_link($real), 'source_path');
    $size = filesize($real);
    hms34v2_require(is_int($size) && $size > 0 && $size <= HMS34V2_MAX_SOURCE_BYTES, 'source_size');
    hms34v2_require(hash_file('sha256', $real) === HMS34V2_V1_SHA256, 'source_sha256');
    $raw = file_get_contents($real);
    hms34v2_require(is_string($raw), 'source_read');
    $next = hms34v2_transform_raw($raw);

    hms34v2_require(!file_exists($destination), 'destination_exists');
    $fh = @fopen($destination, 'x+b');
    hms34v2_require(is_resource($fh), 'destination_open');
    try {
        hms34v2_require(fwrite($fh, $next) === strlen($next), 'destination_write');
        hms34v2_require(fflush($fh), 'destination_flush');
        if (function_exists('fsync')) {
            hms34v2_require(fsync($fh), 'destination_fsync');
        }
        rewind($fh);
        hms34v2_require(stream_get_contents($fh) === $next, 'destination_readback');
    } finally {
        fclose($fh);
    }
    return hash('sha256', $next);
}

$args = $argv ?? [];
if (in_array('--self-test', $args, true)) {
    $fixture = "const X='" . HMS34V2_V1_OPERATION . "';\n";
    $out = hms34v2_transform_raw($fixture);
    hms34v2_require(str_contains($out, HMS34V2_OPERATION), 'self_test_new');
    hms34v2_require(!str_contains($out, HMS34V2_V1_OPERATION), 'self_test_old');
    echo "MATCH_COMMON4_SAMO34_LIVE_V2_SELFTEST_OK\n";
    exit(0);
}

if (($args[1] ?? '') === '--materialize') {
    $source = (string)($args[2] ?? '');
    $destination = (string)($args[3] ?? '');
    hms34v2_require($source !== '' && $destination !== '', 'materialize_args');
    $sha = hms34v2_materialize($source, $destination);
    echo json_encode([
        'operation' => HMS34V2_OPERATION,
        'source_v1_sha256' => HMS34V2_V1_SHA256,
        'materialized_sha256' => $sha,
    ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    exit(0);
}

fwrite(STDERR, "usage: --self-test | --materialize <v1-source> <destination>\n");
exit(64);
