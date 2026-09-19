<?php
declare(strict_types=1);

const HMBG_OPERATION = 'hotel-match-common4-biblio-turkey1-live-1971-20260919-v1';
const HMBG_SOURCE_OPERATION = 'hotel-match-common4-samo34-live-1971-20260919-v1';
const HMBG_SOURCE_SHA256 = '0dc3e14e22d797102aac54d82ee01c2ca75d7e9ba889d12e8b19e9244c89e7b0';
const HMBG_CONTEXT_SHA256 = '3bfc185a402c2dd2776991c373d31ba75428f49a94619f30f18bfeadbb2b60d1';
const HMBG_CONTEXT_OPERATION = 'hotel-match-common4-biblio-turkey1-context-1971-20260919-v1';
const HMBG_SOURCE_CONTEXT_SHA = '041d63ec0606411097b980268591e86400aad2d61ee3abf1786462c3c524855d';
const HMBG_SOURCE_CONTEXT_OPERATION = 'hotel-match-common4-context34-current-1971-20260919-v1';
const HMBG_MAX_SOURCE_BYTES = 1048576;

const HMBG_SOURCE_ROOT = '$root=realpath((string)getenv(\'ANYTOUR_ROOT\'));';
const HMBG_ROOT = '$rootValue=(string)getenv(\'ANYTOOUR_ROOT\');$root=$rootValue!==\'\'?realpath($rootValue):false;';
const HMBG_SOURCE_COUNT = "count(\$context['dossiers']??[])!==39";
const HMBG_COUNT = "count(\$context['dossiers']??[])!==1";
const HMBG_SOURCE_INPUT_GUARD = "if((\$d['status']??'')!=='exact_context_ready'||!in_array(\$d['operator']??'',['funsun','intourist'],true))throw new RuntimeException('dossier_status');";
const HMBG_INPUT_GUARD = "if((\$d['status']??'')!=='exact_context_ready'||(\$d['operator']??'')!=='biblio_globus'||(\$d['target_supplier_namespace']??'')!=='operator_115')throw new RuntimeException('dossier_status');";
const HMBG_SOURCE_ACCEPTED_SQL = "WHERE supplier_namespace IN ('operator_315','operator_342') AND local_hotel_id IN (\$ph)";
const HMBG_ACCEPTED_SQL = "WHERE supplier_namespace='operator_115' AND local_hotel_id IN (\$ph)";
const HMBG_SOURCE_BINDING = "\$bindings=hm_common4_resolve(\$all['OPERATORS'],'samo',['funsun','intourist']);if((\$bindings['funsun']['provider_operator_id']??'')!=='315'||(\$bindings['intourist']['provider_operator_id']??'')!=='342')throw new RuntimeException('operator_namespace_drift');";
const HMBG_BINDING = "\$bindings=hm_common4_resolve(\$all['OPERATORS'],'samo',['biblio_globus']);if((\$bindings['biblio_globus']['provider_operator_id']??'')!=='115')throw new RuntimeException('operator_namespace_drift');";
const HMBG_SOURCE_INPUT_EDGES = "'input_edges'=>39";
const HMBG_INPUT_EDGES = "'input_edges'=>1";

function hmbg_require(bool $ok, string $why): void
{
    if (!$ok) {
        throw new RuntimeException($why);
    }
}

function hmbg_replace_once(string $raw, string $old, string $new, string $label): string
{
    hmbg_require(substr_count($raw, $old) === 1, $label . '_source_occurrence');
    $next = str_replace($old, $new, $raw, $count);
    hmbg_require($count === 1, $label . '_replace_count');
    hmbg_require(strpos($next, $old) === false, $label . '_source_retained');
    hmbg_require(substr_count($next, $new) === 1, $label . '_target_occurrence');
    return $next;
}

function hmbg_transform_raw(string $raw): string
{
    $next = hmbg_replace_once($raw, HMBG_SOURCE_OPERATION, HMBG_OPERATION, 'operation');
    $next = hmbg_replace_once($next, HMBG_SOURCE_CONTEXT_SHA, HMBG_CONTEXT_SHA256, 'context_sha');
    $next = hmbg_replace_once($next, HMBG_SOURCE_CONTEXT_OPERATION, HMBG_CONTEXT_OPERATION, 'context_operation');
    $next = hmbg_replace_once($next, HMBG_SOURCE_ROOT, HMBG_ROOT, 'root');
    $next = hmbg_replace_once($next, HMBG_SOURCE_COUNT, HMBG_COUNT, 'context_count');
    $next = hmbg_replace_once($next, HMBG_SOURCE_INPUT_GUARD, HMBG_INPUT_GUARD, 'input_guard');
    $next = hmbg_replace_once($next, HMBG_SOURCE_ACCEPTED_SQL, HMBG_ACCEPTED_SQL, 'accepted_sql');
    $next = hmbg_replace_once($next, HMBG_SOURCE_BINDING, HMBG_BINDING, 'operator_binding');
    $next = hmbg_replace_once($next, HMBG_SOURCE_INPUT_EDGES, HMBG_INPUT_EDGES, 'input_edges');
    return $next;
}

function hmbg_materialize(string $source, string $destination): string
{
    $real = realpath($source);
    hmbg_require(is_string($real) && $real === $source && is_file($real) && !is_link($real), 'source_path');
    $size = filesize($real);
    hmbg_require(is_int($size) && $size > 0 && $size <= HMBG_MAX_SOURCE_BYTES, 'source_size');
    hmbg_require(hash_file('sha256', $real) === HMBG_SOURCE_SHA256, 'source_sha256');
    $raw = file_get_contents($real);
    hmbg_require(is_string($raw), 'source_read');
    $next = hmbg_transform_raw($raw);

    hmbg_require(!file_exists($destination), 'destination_exists');
    $fh = @fopen($destination, 'x+b');
    hmbg_require(is_resource($fh), 'destination_open');
    try {
        hmbg_require(fwrite($fh, $next) === strlen($next), 'destination_write');
        hmbg_require(fflush($fh), 'destination_flush');
        if (function_exists('fsync')) hmbg_require(fsync($fh), 'destination_fsync');
        rewind($fh);
        hmbg_require(stream_get_contents($fh) === $next, 'destination_readback');
    } finally {
        fclose($fh);
    }
    return hash('sha256', $next);
}

$args = $argv ?? [];
if (in_array('--self-test', $args, true)) {
    $fixture = implode("\n", [
        "const X='" . HMBG_SOURCE_OPERATION . "';",
        "const C='" . HMBG_SOURCE_CONTEXT_SHA . "';",
        "if((\$context['operation_id']??'')!=='" . HMBG_SOURCE_CONTEXT_OPERATION . "'){}",
        HMBG_SOURCE_ROOT,
        HMBG_SOURCE_COUNT,
        HMBG_SOURCE_INPUT_GUARD,
        "SELECT x FROM y " . HMBG_SOURCE_ACCEPTED_SQL,
        HMBG_SOURCE_BINDING,
        HMBG_SOURCE_INPUT_EDGES,
    ]) . "\n";
    $out = hmbg_transform_raw($fixture);
    foreach ([HMBG_OPERATION,HMBG_CONTEXT_SHA256,HMBG_CONTEXT_OPERATION,HMBG_ROOT,HMBG_COUNT,HMBG_INPUT_GUARD,HMBG_ACCEPTED_SQL,HMBG_BINDING,HMBG_INPUT_EDGES] as $needle) {
        hmbg_require(str_contains($out, $needle), 'self_test_target_missing');
    }
    foreach ([HMBG_SOURCE_OPERATION,HMBG_SOURCE_CONTEXT_SHA,HMBG_SOURCE_CONTEXT_OPERATION,HMBG_SOURCE_ROOT,HMBG_SOURCE_COUNT,HMBG_SOURCE_INPUT_GUARD,HMBG_SOURCE_ACCEPTED_SQL,HMBG_SOURCE_BINDING,HMBG_SOURCE_INPUT_EDGES] as $needle) {
        hmbg_require(!str_contains($out, $needle), 'self_test_source_retained');
    }
    $bad = false;
    try { hmbg_transform_raw($fixture . HMBG_SOURCE_INPUT_EDGES); } catch (RuntimeException $e) { $bad = true; }
    hmbg_require($bad, 'self_test_duplicate_not_rejected');
    echo "MATCH_COMMON4_BIBLIO_TURKEY1_MATERIALIZER_OK\n";
    exit(0);
}

if (($args[1] ?? '') === '--materialize') {
    $source = (string)($args[2] ?? '');
    $destination = (string)($args[3] ?? '');
    hmbg_require($source !== '' && $destination !== '', 'materialize_args');
    $sha = hmbg_materialize($source, $destination);
    echo json_encode([
        'operation' => HMBG_OPERATION,
        'source_v1_sha256' => HMBG_SOURCE_SHA256,
        'context_sha256' => HMBG_CONTEXT_SHA256,
        'materialized_sha256' => $sha,
    ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    exit(0);
}

fwrite(STDERR, "usage: --self-test | --materialize <v1-source> <destination>\n");
exit(64);
