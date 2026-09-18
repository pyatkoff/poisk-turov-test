<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || $argc !== 2) { fwrite(STDERR, "CLI only\n"); exit(2); }
$site = realpath($argv[1]);
$runtime = realpath(dirname(__DIR__, 2));
if ($site === false || $runtime === false || basename($site) !== 'anytoour.ru') throw new RuntimeException('ANEX_OWNER_SCOPE_ROOT');
$private = dirname($site, 2) . '/.anytoour-anex/search3-preview.php';
if (!is_file($private) || is_link($private)) throw new RuntimeException('ANEX_OWNER_SCOPE_PRIVATE_CONFIG');
require_once $private;
putenv('ANYTOUR_PROJECT_ROOT=' . $site);
putenv('ANYTOUR_LOCAL_SNAPSHOT_INGEST_FILE=' . $runtime . '/v2/data/anytour-offer-snapshot-ingest-v1.php');
$collector = $runtime . '/scripts/ops/anex_local_offer_collect.php';
if (!is_file($collector) || is_link($collector)) throw new RuntimeException('ANEX_OWNER_SCOPE_COLLECTOR');
$oldArgv = $argv; $oldArgc = $argc;
$argv = [$collector, '--departure=1', '--country=4', '--date-from=2026-09-18', '--date-to=2026-09-24', '--nights=8', '--adults=2', '--max-expands=60', '--max-apd=300', '--generation=25061981'];
$argc = count($argv);
ob_start();
try { require $collector; $raw = trim((string)ob_get_clean()); }
catch (Throwable $e) { ob_end_clean(); $argv = $oldArgv; $argc = $oldArgc; throw $e; }
$argv = $oldArgv; $argc = $oldArgc;
$result = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
if (!is_array($result) || ($result['status'] ?? null) !== 'complete') throw new RuntimeException('ANEX_OWNER_SCOPE_RECEIPT');
echo json_encode(['status'=>'completed','provider'=>'anex','scope'=>['departure_id'=>1,'country_id'=>4,'date_from'=>'2026-09-18','date_to'=>'2026-09-24','nights'=>8,'adults'=>2,'children'=>0,'meal'=>''],'collector'=>$result,'booking_calls'=>0,'lead_calls'=>0,'mapping_writes'=>0,'search3_publication'=>0,'production_webroot_writes'=>0,'replay_allowed'=>false], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR), "\n";
