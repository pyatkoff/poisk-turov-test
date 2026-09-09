<?php
declare(strict_types=1);
// Explicit CLI only. Not wired into observed/content/preview modes in this packet.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('display_errors','0');
try {
    if ($argc !== 1) throw new RuntimeException('arguments_not_supported');
    $root = realpath(getcwd());
    if (!$root || basename($root) !== 'anytoour.ru') throw new RuntimeException('anytour_root_required');
    $helper = false;
    foreach (['data/db-v1.php','v2/data/db-v1.php'] as $path) {
        $file = realpath($root . '/' . $path);
        if ($file && strpos($file,$root.'/') === 0 && is_file($file)) { $helper=$file; break; }
    }
    if (!$helper) throw new RuntimeException('db_helper_required');
    $raw = stream_get_contents(STDIN,20000001);
    if ($raw === false || strlen($raw)>20000000) throw new RuntimeException('envelope_bound');
    $input = json_decode($raw,true,64,JSON_THROW_ON_ERROR);
    if (!is_array($input)) throw new RuntimeException('envelope_invalid');
    require_once $helper;
    if (!function_exists('v2_data_db')) throw new RuntimeException('db_helper_required');
    $pdo = v2_data_db();
    if (!$pdo instanceof PDO) throw new RuntimeException('db_unavailable');
    $pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES,false);
    require_once __DIR__ . '/../../app/admin/anex-review/dossier-store.php';
    $result = (new AnexReviewDossierStore($pdo))->import($input);
    echo json_encode($result,JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (Throwable $e) {
    // No SQL, credentials, supplier payload or infrastructure paths in diagnostics.
    fwrite(STDERR,"ANEX_DOSSIER_IMPORT_FAILED\n");
    exit(1);
}
