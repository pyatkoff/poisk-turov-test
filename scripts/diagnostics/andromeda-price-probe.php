<?php
declare(strict_types=1);
require_once __DIR__ . '/andromeda-catalog-capture.php';
if (PHP_SAPI !== 'cli' || $argc !== 4 || $argv[1] !== '--execute') exit(2);
ini_set('display_errors','0'); ini_set('zend.exception_ignore_args','1'); umask(0077);
$phase='preflight'; $report=['state'=>'reserved','stage'=>'price_moscow_egypt_20260918_v1','actions'=>[]];
try {
    $raw=file_get_contents($argv[3]);
    if (hash('sha256',$raw) !== '01030bb9e23e0c87f8bed7c50628c8f56243b89f3e55a24151430766c1576641') throw new RuntimeException('CATALOG_DIGEST_MISMATCH');
    $saved=json_decode($raw,true,32,JSON_THROW_ON_ERROR);
    if ($saved['params'] !== ['TOWNFROMINC'=>1,'STATEINC'=>3]) throw new RuntimeException('DIRECTION_MISMATCH');
    foreach (['OPERATORS'=>['Anex Tour',5],'MEAL'=>['AI',5],'CURRENCY'=>['RUB',643]] as $key=>$pair) {
        if (andromeda_capture_id($saved['payload'][$key],$pair[0]) !== $pair[1]) throw new RuntimeException('DICTIONARY_ID_MISMATCH');
    }
    $dates=$saved['payload']['CHECKIN_BEG'];
    $start=DateTimeImmutable::createFromFormat('!d.m.Y',$dates['start'],new DateTimeZone('UTC'));
    $target=new DateTimeImmutable('2026-09-18',new DateTimeZone('UTC'));
    if (!$start || $start->format('d.m.Y') !== $dates['start']) throw new RuntimeException('DATE_INVALID');
    $delta=(int)$start->diff($target)->format('%r%a');
    if ($delta<0 || ($dates['available'][$delta] ?? '') !== '1' || gmdate('Ymd')>'20260918') throw new RuntimeException('DATE_UNAVAILABLE');
    $username=getenv('ANDROMEDA_USERNAME'); $password=getenv('ANDROMEDA_PASSWORD');
    if (!$username || !$password || !is_dir($argv[2]) || is_link($argv[2])) throw new RuntimeException('PREFLIGHT_FAILED');
    $report['params']=AnyTourAndromedaClient::priceProbeParams();
    andromeda_capture_save($argv[2],'checkpoint',$report);
    $client=new AnyTourAndromedaClient(new AnyTourAndromedaTransport(true),true);
    $phase='login'; $report['actions'][]='login'; $client->login($username,$password);
    $phase='price'; $report['actions'][]='price'; $reply=$client->priceProbe();
    $json=json_encode($reply,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    foreach ([$username,$password,rawurlencode($username),rawurlencode($password)] as $secret) {
        if (strpos($json,$secret)!==false) throw new RuntimeException('CREDENTIAL_ECHO');
    }
    $report['response_sha256']=andromeda_capture_save($argv[2],'price',['params'=>$report['params'],'payload'=>$reply]);
    $report['state']='completed'; $report['rows']=count($reply['PRICES']);
    $report['page']=$reply['PAGE']; $report['pages_count']=$reply['PAGES_COUNT'];
    $report['complete_catalog']=false; $report['fees']='unknown'; $report['ui_enabled']=false;
} catch (Throwable $e) {
    $report['state']='source_error'; $report['phase']=$phase;
    $report['error']=preg_match('/^[A-Z_]{1,80}$/D',$e->getMessage())?$e->getMessage():'PRICE_PROBE_FAILED';
    $report['retry']=false;
}
$report['finished_at']=gmdate('c');
try { andromeda_capture_save($argv[2],'result',$report); } catch(Throwable $e) { echo "RESULT_SAVE_FAILED_NO_REPLAY\n"; exit(1); }
echo json_encode($report,JSON_THROW_ON_ERROR)."\n";
exit($report['state']==='completed'?0:1);
