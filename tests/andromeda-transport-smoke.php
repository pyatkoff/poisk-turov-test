<?php
declare(strict_types=1);
require __DIR__ . '/../app/integrations/andromeda-transport.php';
$transport = new AnyTourAndromedaTransport();
$count = 0;
foreach ([
    'http://gateway.samo.ru/api/?version=1.01&action=login',
    'https://gateway.samo.ru.evil/api/?version=1.01&action=login',
    'https://gateway.samo.ru@evil/api/?version=1.01&action=login',
    'https://gateway.samo.ru/api/../?version=1.01&action=login',
    'https://gateway.samo.ru/api/?version=1.01&action=login#fragment',
    'https://gateway.samo.ru/api/?version=1.01&action=bron',
    'https://gateway.samo.ru/api/?version=1.01&action=price',
    'https://gateway.samo.ru/api/?version=1.0&action=townfrom',
] as $url) {
    try { $transport($url); throw new LogicException('UNEXPECTED_ALLOW'); }
    catch (RuntimeException $e) {
        if (!in_array($e->getMessage(), ['ANDROMEDA_ENDPOINT_REJECTED', 'ANDROMEDA_ACTION_NOT_ALLOWED'], true)) throw $e;
        ++$count;
    }
}
// CLI guard paths run in separate processes; neither can reach a transport call.
$probe = escapeshellarg(__DIR__ . '/../scripts/diagnostics/andromeda-access-probe.php');
exec(PHP_BINARY . ' ' . $probe, $lines, $status);
if ($status !== 2 || strpos(implode('', $lines), 'ANDROMEDA_PROBE_DISABLED') !== 0) throw new RuntimeException('DEFAULT_GATE_FAILED');
++ $count;
putenv('ANDROMEDA_USERNAME'); putenv('ANDROMEDA_PASSWORD');
$lines = [];
exec(PHP_BINARY . ' ' . $probe . ' --execute /unused-andromeda-reservation', $lines, $status);
if ($status !== 2 || implode('', $lines) !== 'ANDROMEDA_CREDENTIALS_REQUIRED') throw new RuntimeException('SECRET_GATE_FAILED');
++ $count;
echo "Andromeda transport/CLI guards: $count passed; supplier calls=0\n";
