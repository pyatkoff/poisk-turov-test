<?php
declare(strict_types=1);
require_once __DIR__.'/andromeda-catalog-capture.php';
function group_stats(array $reply): array {
    $hotels=[]; $operators=[];
    foreach($reply['PRICES'] as $r) {
        $operatorId=(string)($r['operatorKey']??'');
        $namespace=(int)($r['isOperatorHotelKey']??0)===1 ? 'operator:'.$operatorId : 'andromeda';
        $key=$namespace.':'.(string)$r['hotelKey'];
        $hotels[$key]=($hotels[$key]??0)+1;
        $operators[$operatorId]=(string)($r['operator']??'');
    }
    return ['rows'=>count($reply['PRICES']),'unique_hotels'=>count($hotels),'page'=>$reply['PAGE'],
        'pages_count'=>$reply['PAGES_COUNT'],'max_offers_per_hotel'=>$hotels?max($hotels):0,'operators'=>$operators];
}
if(realpath($_SERVER['SCRIPT_FILENAME']??'')!==__FILE__) return;
if(PHP_SAPI!=='cli'||$argc!==4||$argv[1]!=='--execute') exit(2);
ini_set('display_errors','0');ini_set('zend.exception_ignore_args','1');umask(0077);
$report=['state'=>'reserved','supplier_calls'=>0,'mapping_writes'=>0,'db_writes'=>0,'published'=>false,'retry'=>false];
try {
    $raw=file_get_contents($argv[3]);
    if(hash('sha256',$raw)!=='c4e0722837b12a8c3979972a1145d6d0e43d50539efebbd7e68a416590dad6a9') throw new RuntimeException('BASELINE_DIGEST_MISMATCH');
    $saved=json_decode($raw,true,64,JSON_THROW_ON_ERROR);
    $params=AnyTourAndromedaClient::priceProbeParams();
    if($saved['params']!==$params || gmdate('Ymd')>'20260918') throw new RuntimeException('BASELINE_CRITERIA_MISMATCH');
    $report['baseline']=group_stats($saved['payload']);
    $report['baseline_sha256']=hash('sha256',$raw);
    $params['GROUP_BY']=32; $report['params']=$params;
    $username=getenv('ANDROMEDA_USERNAME');$password=getenv('ANDROMEDA_PASSWORD');
    if(!$username||!$password) throw new RuntimeException('CREDENTIALS_MISSING');
    andromeda_capture_save($argv[2],'checkpoint',$report);
    $client=new AnyTourAndromedaClient(new AnyTourAndromedaTransport(true),true);
    $report['supplier_calls']++;$client->login($username,$password);
    $report['supplier_calls']++;$start=microtime(true);$reply=$client->price($params);
    $report['elapsed_ms']=(int)round((microtime(true)-$start)*1000);
    $json=json_encode($reply,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    foreach([$username,$password,rawurlencode($username),rawurlencode($password)] as $secret)
        if(strpos($json,$secret)!==false) throw new RuntimeException('CREDENTIAL_ECHO');
    $report['response_sha256']=andromeda_capture_save($argv[2],'grouped-price',['params'=>$params,'payload'=>$reply]);
    $report['grouped']=group_stats($reply);$report['state']='completed';
    $report['comparison']='historical baseline; availability can change; no total-tour inference from grouped pages';
} catch(Throwable $e) {
    $report['state']='failed';$report['error']=preg_match('/^[A-Z_]{1,80}$/D',$e->getMessage())?$e->getMessage():'GROUP_PROBE_FAILED';
}
$report['finished_at']=gmdate('c');$report['source_sha']=getenv('GITHUB_SHA');
andromeda_capture_save($argv[2],'result',$report);
echo json_encode($report,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n";
exit($report['state']==='completed'?0:1);
