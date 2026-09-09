<?php
declare(strict_types=1);
require __DIR__ . '/../scripts/diagnostics/andromeda-catalog-capture.php';
$dir = sys_get_temp_dir() . '/andromeda-fixture-' . bin2hex(random_bytes(8));
mkdir($dir, 0700);
$calls = [];
$all = array_fill_keys(['CHECKIN_BEG','TOWNTO','STARS','HOTELS','MEAL','CURRENCY','OPERATORS'], []);
$all['HOTELS'] = [['id'=>123,'name'=>'Fixture hotel']];
$client = new AnyTourAndromedaClient(function ($url) use (&$calls, $all) {
    parse_str(parse_url($url, PHP_URL_QUERY), $p); $calls[] = $p['action'];
    $b = ['login'=>['sid'=>'fixture-sid'], 'townfrom'=>['TOWNFROM'=>[['id'=>42,'name'=>'Москва']]],
        'state'=>['STATE'=>[['id'=>73,'name'=>'Египет']]], 'all'=>$all][$p['action']];
    if ($p['action']==='state' && $p['TOWNFROMINC']!=='42') throw new RuntimeException('BAD_DEPARTURE');
    if ($p['action']==='all' && ($p['TOWNFROMINC']!=='42' || $p['STATEINC']!=='73')) throw new RuntimeException('BAD_COUNTRY');
    return ['status'=>200,'body'=>json_encode($b)];
}, true);
try {
    $r = andromeda_capture($client, $dir, 'fixture-account', 'fixture-password');
    if ($r['state']!=='completed' || $r['counts']['HOTELS']!==1 || count($calls)!==4) throw new RuntimeException('CAPTURE_FAILED');
    foreach ($r['digests'] as $action=>$hash) if (hash_file('sha256', "$dir/$action.json")!==$hash) throw new RuntimeException('DIGEST_FAILED');
    try { andromeda_capture($client, $dir, 'fixture-account', 'fixture-password'); throw new LogicException('REPLAY_ALLOWED'); }
    catch (RuntimeException $e) { if ($e->getMessage()!=='CAPTURE_EXISTS_OR_UNWRITABLE' || count($calls)!==4) throw $e; }
    try { andromeda_capture_id([['id'=>1,'name'=>'Москва'],['id'=>2,'name'=>'Москва']], 'Москва'); throw new LogicException('AMBIGUITY_ALLOWED'); }
    catch (RuntimeException $e) { if ($e->getMessage()!=='DIRECTION_MISSING_OR_AMBIGUOUS') throw $e; }
    echo "Catalog capture fixtures passed: direction IDs, digests, readback, replay and ambiguity; API0\n";
} finally { foreach (glob($dir.'/*.json') as $f) unlink($f); rmdir($dir); }
