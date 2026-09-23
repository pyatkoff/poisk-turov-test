<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/integrations/operator-fuel-rule-evidence.php';

function ifx_fail(string $reason): never { throw new RuntimeException($reason); }
function ifx_json(string $path, int $max = 1048576): array {
    if (is_link($path) || !is_file($path)) ifx_fail('probe_receipt_missing');
    $size = filesize($path);
    if (!is_int($size) || $size < 2 || $size > $max) ifx_fail('probe_receipt_invalid');
    $v = json_decode((string)file_get_contents($path), true, 96, JSON_THROW_ON_ERROR);
    if (!is_array($v) || array_is_list($v)) ifx_fail('probe_receipt_invalid');
    return $v;
}
function ifx_digest(mixed $v): string {
    if (!is_string($v) || preg_match('/\A[a-f0-9]{64}\z/D', $v) !== 1) ifx_fail('probe_digest_invalid');
    return $v;
}
function ifx_operation(string $v): string {
    if (preg_match('/\Aint-andromeda-[a-z0-9][a-z0-9-]{8,160}\z/D', $v) !== 1) ifx_fail('operation_invalid');
    return $v;
}
function ifx_rate(array $rows): string {
    $eur = []; $rub = [];
    foreach ($rows as $row) {
        if (!is_array($row)
            || ($row['source'] ?? null) !== 'andromeda_claim_money'
            || !is_string($row['currency'] ?? null)
            || !is_string($row['rate'] ?? null)) continue;
        if ($row['currency'] === 'EUR') $eur[] = $row['rate'];
        if ($row['currency'] === 'RUB') $rub[] = $row['rate'];
    }
    $eur = array_values(array_unique($eur, SORT_STRING));
    $rub = array_values(array_unique($rub, SORT_STRING));
    if ($eur !== ['1'] || count($rub) !== 1) ifx_fail('probe_fx_invalid');
    $rate = $rub[0];
    if (!is_string($rate)
        || preg_match('/\A(?:0|[1-9][0-9]{0,5})(?:\.[0-9]{1,8})?\z/D', $rate) !== 1
        || (float)$rate <= 0) ifx_fail('probe_fx_invalid');
    return $rate;
}
function ifx_probe(string $opsRoot, string $operation): array {
    $operation = ifx_operation($operation);
    $path = rtrim($opsRoot, '/') . '/' . $operation . '/result.json';
    $v = ifx_json($path);
    if (($v['operation_id'] ?? null) !== $operation
        || ($v['mode'] ?? null) !== 'program-fuel-probe'
        || ($v['status'] ?? null) !== 'complete'
        || ($v['database_writes'] ?? null) !== 0
        || ($v['production_unchanged'] ?? null) !== true) ifx_fail('probe_terminal_invalid');
    $p = $v['program_fuel_probe'] ?? null;
    if (!is_array($p)
        || ($p['source'] ?? null) !== 'int-andromeda-program-getflights-probe-v1'
        || ($p['status'] ?? null) !== 'complete'
        || ($p['final_price_verified'] ?? null) !== false
        || ($p['database_writes'] ?? null) !== 0
        || ($p['mapping_writes'] ?? null) !== 0) ifx_fail('probe_payload_invalid');
    $calls = $p['supplier_calls'] ?? null;
    if (!is_array($calls)
        || ($calls['get_flights'] ?? null) !== 1
        || ($calls['package'] ?? null) !== 1
        || ($calls['changeservice'] ?? null) !== 0
        || ($calls['calc'] ?? null) !== 0
        || ($calls['booking'] ?? null) !== 0) ifx_fail('probe_supplier_contract');
    $target = $p['target'] ?? null;
    if (!is_array($target)
        || ($target['operator_family'] ?? null) !== 'funsun'
        || (string)($target['program_key'] ?? '') !== '114'
        || (string)($target['tour_key'] ?? '') !== '78'
        || !is_int($target['sample_distinct_spo_index'] ?? null)
        || $target['sample_distinct_spo_index'] < 0
        || !is_string($target['spo_key'] ?? null)
        || preg_match('/\A[1-9][0-9]{0,15}\z/D', $target['spo_key']) !== 1
        || ($target['retained_distinct_spo_count'] ?? 0) < 2
        || ($target['mapped_local_hotel'] ?? null) !== true) ifx_fail('probe_target_invalid');
    $estimate = $p['search_surcharge_estimate'] ?? null;
    if (!is_array($estimate)
        || ($estimate['provider'] ?? null) !== 'andromeda'
        || ($estimate['final_price_verified'] ?? null) !== false
        || !is_array($estimate['operator_currency_rates_reported'] ?? null)) ifx_fail('probe_fx_invalid');
    $mtime = filemtime($path);
    if (!is_int($mtime) || $mtime < 1) ifx_fail('probe_mtime_invalid');
    return [
        'operation'=>$operation,
        'sample_index'=>$target['sample_distinct_spo_index'],
        'spo_key'=>$target['spo_key'],
        'offer_sha256'=>ifx_digest($target['selected_offer_ref_sha256'] ?? null),
        'rate'=>ifx_rate($estimate['operator_currency_rates_reported']),
        'result_sha256'=>hash_file('sha256', $path),
        'observed_at'=>$mtime,
    ];
}
function ifx_write(string $path, array $payload): string {
    $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    if (strlen($encoded) > 65536) ifx_fail('fx_receipt_too_large');
    if (file_exists($path)) {
        $current = ifx_json($path, 65536);
        if ($current === $payload) return 'already_present';
        ifx_fail('fx_receipt_conflict');
    }
    $tmp = $path . '.tmp-' . bin2hex(random_bytes(8));
    $fh = fopen($tmp, 'x');
    if ($fh === false) ifx_fail('fx_write_open');
    try {
        if (fwrite($fh, $encoded) !== strlen($encoded) || !fflush($fh)) ifx_fail('fx_write_failed');
        if (function_exists('fsync') && !fsync($fh)) ifx_fail('fx_write_sync');
    } finally {
        fclose($fh);
    }
    chmod($tmp, 0600);
    if (!rename($tmp, $path)) { @unlink($tmp); ifx_fail('fx_write_rename'); }
    chmod($path, 0600);
    $read = ifx_json($path, 65536);
    if ($read !== $payload) ifx_fail('fx_write_readback');
    return 'created';
}
function ifx_seed(string $opsRoot, string $searches, string $one, string $two, int $now): array {
    if ($now < 1 || !is_dir($opsRoot) || is_link($opsRoot)
        || !is_dir($searches) || is_link($searches)
        || basename(rtrim($searches, '/')) !== 'searches') ifx_fail('seed_root_invalid');
    if ($one === $two) ifx_fail('probe_identity_same');
    $a = ifx_probe($opsRoot, $one);
    $b = ifx_probe($opsRoot, $two);
    if ($a['sample_index'] === $b['sample_index']
        || $a['spo_key'] === $b['spo_key']
        || $a['offer_sha256'] === $b['offer_sha256']) ifx_fail('probe_independence_invalid');
    if ($a['rate'] !== $b['rate']) ifx_fail('probe_fx_conflict');
    $observed = max($a['observed_at'], $b['observed_at']);
    $expires = $observed + 86400;
    if ($observed > $now + 5 || $expires <= $now) ifx_fail('probe_fx_stale');

    $direction = AnyTourOperatorFuelRuleEvidenceV1::canonicalDirection('FUN&SUN', [
        'market'=>'departure:1','destination'=>'country:4'
    ]);
    $directionSha = AnyTourOperatorFuelRuleEvidenceV1::directionDigest($direction);
    $resultShas = [$a['result_sha256'], $b['result_sha256']];
    sort($resultShas, SORT_STRING);
    $evidence = AnyTourOperatorFuelRuleEvidenceV1::hash([
        'direction'=>$direction,
        'rate'=>$a['rate'],
        'observed_at'=>$observed,
        'probe_result_sha256'=>$resultShas,
    ]);
    $payload = [
        'version'=>1,
        'direction_sha256'=>$directionSha,
        'direction'=>$direction,
        'source'=>'terminal_program_fuel_probes',
        'from'=>'EUR','to'=>'RUB','rate'=>$a['rate'],
        'observed_at'=>$observed,'expires_at'=>$expires,
        'independent_probe_count'=>2,
        'probe_result_sha256'=>$resultShas,
        'evidence_sha256'=>$evidence,
    ];
    $path = rtrim($searches, '/') . '/operator-direction-fx-v1-' . $directionSha . '.json';
    $write = ifx_write($path, $payload);
    return [
        'schema_version'=>1,
        'source'=>'int-funsun-direction-fx-seed-v1',
        'status'=>'seeded_verified',
        'write_state'=>$write,
        'direction'=>$direction,
        'rate'=>$a['rate'],
        'observed_at'=>$observed,
        'expires_at'=>$expires,
        'independent_probe_count'=>2,
        'evidence_sha256'=>$evidence,
        'supplier_calls'=>0,
        'database_reads'=>0,
        'database_writes'=>0,
        'fuel_rule_writes'=>0,
        'final_price_verified'=>false,
    ];
}

if (PHP_SAPI === 'cli' && getenv('INT_FUNSUN_FX_SEED_LIBRARY_ONLY') !== '1') {
    try {
        if ($argc !== 3) ifx_fail('command_shape');
        $home = rtrim((string)getenv('HOME'), '/');
        if ($home === '') ifx_fail('home_missing');
        $out = ifx_seed(
            $home . '/.anytoour-int-executor',
            $home . '/.anytoour-andromeda/searches',
            (string)$argv[1],
            (string)$argv[2],
            time()
        );
        echo json_encode($out, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR), "\n";
    } catch (Throwable $e) {
        fwrite(STDERR, 'INT_FUNSUN_FX_SEED_ERROR:' . preg_replace('/[^A-Za-z0-9_.:-]+/', '_', $e->getMessage()) . "\n");
        exit(1);
    }
}
