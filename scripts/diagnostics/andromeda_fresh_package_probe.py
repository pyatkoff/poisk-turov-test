#!/usr/bin/env python3
"""Owner-approved one-shot fresh Andromeda PRICE -> broninit diagnostic.

Runs only when explicitly invoked by a gated control workflow. No booking, calc,
get_flights, mapping write, lead, or automatic retry is implemented here.
"""
import json
import os
from pathlib import Path
import sys

SAFE_KEYS = {
    'status','phase','supplier_calls','database_writes','booking_calls','calc_calls','get_flights_calls',
    'price_rows','price_pages','price_id_sha256','catalog_key_sha256','id_equals_catalog_key',
    'package_sha256','claim_fields','condition','requires_external_flights','buyer_price',
    'hotels_count','transports_count','services_count','automatic_retry','transport_error','finished_at'
}

PHP = r'''
error_reporting(0); ini_set('display_errors','0'); ini_set('log_errors','0');
umask(0077); ob_start();
$supplierCalls=0; $phase='preflight'; $transportError=null;
$safe=['status'=>'unknown','phase'=>$phase,'supplier_calls'=>0,'database_writes'=>0,
    'booking_calls'=>0,'calc_calls'=>0,'get_flights_calls'=>0,'automatic_retry'=>false];
$private=null;
try {
    if(PHP_SAPI!=='cli')throw new RuntimeException('CLI_ONLY');
    $root=realpath(getcwd());
    if(!$root||basename($root)!=='anytoour.ru')throw new RuntimeException('WRONG_PROJECT');
    $target=$root.'/_preview/search3-anex-candidate';
    $clientPath=$target.'/app/integrations/andromeda-client.php';
    $configPath=$target.'/.andromeda-private.php';
    if(realpath($clientPath)!==$clientPath||!is_file($clientPath)||realpath($configPath)!==$configPath||!is_file($configPath))
        throw new RuntimeException('RUNTIME_MISSING');
    require_once $clientPath;
    $config=require $configPath;
    if(!is_array($config)||($config['enabled']??null)!==true
        ||!is_string($config['username']??null)||$config['username']===''
        ||!is_string($config['password']??null)||$config['password']==='')
        throw new RuntimeException('PRIVATE_CONFIG_MISSING');
    // Previously proven broad parity scenario: Moscow -> Turkey, 2026-10-05, 7n, 2 adults, AI, ANEX only.
    $params=['TOWNFROMINC'=>1,'STATEINC'=>3,'CHECKIN_BEG'=>'20261005','CHECKIN_END'=>'20261005',
        'NIGHTS_FROM'=>7,'NIGHTS_TILL'=>7,'ADULT'=>2,'CHILD'=>0,'CURRENCYINC'=>643,
        'MEAL'=>'5','OPERATORS'=>'5','PACKETTYPE'=>0,'PAGE'=>1];
    AnyTourAndromedaClient::validatePriceParams($params);

    // Dedicated bounded diagnostic transport so the legacy client cannot hide the
    // underlying transport class from the outer operation receipt.
    $attempts=0; $lastStarted=0.0;
    $transport=static function(string $url,array $ignored=[])use(&$attempts,&$lastStarted,&$transportError):array{
        if(strpos($url,'https://gateway.samo.ru/api/?')!==0||strlen($url)>16384||preg_match('/[\x00-\x20\x7f#]/',$url))
            throw new RuntimeException('PROBE_ENDPOINT_REJECTED');
        parse_str((string)parse_url($url,PHP_URL_QUERY),$query);
        if(($query['version']??null)!=='1.01'||!in_array($query['action']??null,['login','price','broninit'],true))
            throw new RuntimeException('PROBE_ACTION_REJECTED');
        if($attempts>=3)throw new RuntimeException('PROBE_REQUEST_BUDGET');
        if(!function_exists('curl_init')){$transportError='local_curl_missing';throw new RuntimeException('PROBE_CURL_REQUIRED');}
        $wait=1.05-(microtime(true)-$lastStarted);if($wait>0)usleep((int)ceil($wait*1000000));
        ++$attempts;$lastStarted=microtime(true);$handle=curl_init();
        if($handle===false){$transportError='local_curl_init';throw new RuntimeException('PROBE_CURL_INIT_FAILED');}
        $body='';$oversize=false;
        try{
            $ok=curl_setopt_array($handle,[CURLOPT_URL=>$url,CURLOPT_HTTPGET=>true,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,
                CURLOPT_FOLLOWLOCATION=>false,CURLOPT_MAXREDIRS=>0,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,
                CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>25,CURLOPT_VERBOSE=>false,CURLOPT_HEADER=>false,
                CURLOPT_HTTPHEADER=>['Accept: application/json'],
                CURLOPT_WRITEFUNCTION=>static function($curl,string $chunk)use(&$body,&$oversize):int{
                    if(strlen($body)+strlen($chunk)>2097152){$oversize=true;return 0;}$body.=$chunk;return strlen($chunk);
                }]);
            if(!$ok){$transportError='local_curl_setup';throw new RuntimeException('PROBE_CURL_SETUP_FAILED');}
            if(curl_exec($handle)===false){
                $transportError=$oversize?'response_too_large':'network_transport';
                throw new RuntimeException($oversize?'ANDROMEDA_RESPONSE_TOO_LARGE':'ANDROMEDA_NETWORK_TRANSPORT_FAILURE');
            }
            return ['status'=>(int)curl_getinfo($handle,CURLINFO_HTTP_CODE),'body'=>$body];
        }finally{curl_close($handle);}
    };
    $client=new AnyTourAndromedaClient($transport,true,true);
    $phase='login'; $supplierCalls=1; $client->login($config['username'],$config['password']);
    $phase='price'; $supplierCalls=2; $price=$client->price($params);
    if(!is_array($price['PRICES']??null)||!isset($price['PRICES'][0])||!is_array($price['PRICES'][0]))
        throw new RuntimeException('NO_PACKAGE_OFFER');
    $row=$price['PRICES'][0]; $offer=$row['id']??null;
    if(!(is_string($offer)||is_int($offer))||(string)$offer===''||strlen((string)$offer)>4096)
        throw new RuntimeException('INVALID_PACKAGE_OFFER');
    $offer=(string)$offer;
    $phase='broninit'; $supplierCalls=3; $package=$client->package($offer);
    $documents=$package['claimDocument']??null;
    if(!is_array($documents)||count($documents)!==1||!is_array($documents[0]))
        throw new RuntimeException('INVALID_PACKAGE_DOCUMENT');
    $doc=$documents[0];
    $packageJson=json_encode($package,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    $privateJson=json_encode(['price_row'=>$row,'package'=>$package],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    foreach([$config['username'],$config['password'],rawurlencode($config['username']),rawurlencode($config['password'])] as $secret)
        if($secret!==''&&strpos($privateJson,$secret)!==false)throw new RuntimeException('CREDENTIAL_ECHO');
    $groups=static function(array $parent,string $plural,string $singular):?array{
        if(!isset($parent[$plural]))return [];$out=[];
        if(!is_array($parent[$plural]))return null;
        foreach($parent[$plural] as $group){
            if(!is_array($group)||!is_array($group[$singular]??null))return null;
            foreach($group[$singular] as $item)if(is_array($item))$out[]=$item;else return null;
        }
        return $out;
    };
    $buyer=null;$buyerRows=$groups($doc,'buyerMoneys','buyerClaimMoney');
    if(is_array($buyerRows)&&count($buyerRows)===1){
        $money=$buyerRows[0];$net=(string)($money['net']??'');$currency=$money['currency']??null;
        if(preg_match('/^(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?$/D',$net)
            &&preg_match('/[1-9]/',$net)&&is_string($currency)&&preg_match('/^[A-Z0-9_]{2,8}$/D',$currency))
            $buyer=['amount'=>$net,'currency'=>$currency,'status'=>'package_unverified'];
    }
    $external=$doc['freightExternal']??null;$requires=null;
    if((is_int($external)||is_string($external))&&preg_match('/^(?:0|[1-9][0-9]{0,8})$/D',(string)$external))
        $requires=(int)$external>0;
    $hotels=$groups($doc,'hotels','hotel');$transports=$groups($doc,'transports','transport');$services=$groups($doc,'services','service');
    $catalog=(string)($doc['catalogKey']??'');
    $safe=['status'=>'captured','phase'=>'complete','supplier_calls'=>$supplierCalls,'database_writes'=>0,
        'booking_calls'=>0,'calc_calls'=>0,'get_flights_calls'=>0,'price_rows'=>count($price['PRICES']),
        'price_pages'=>(int)($price['PAGES_COUNT']??0),'price_id_sha256'=>hash('sha256',$offer),
        'catalog_key_sha256'=>hash('sha256',$catalog),'id_equals_catalog_key'=>hash_equals($offer,$catalog),
        'package_sha256'=>hash('sha256',$packageJson),'claim_fields'=>array_values(array_filter(array_keys($doc),'is_string')),
        'condition'=>is_string($doc['condition']??null)?substr($doc['condition'],0,80):null,
        'requires_external_flights'=>$requires,'buyer_price'=>$buyer,
        'hotels_count'=>is_array($hotels)?count($hotels):null,'transports_count'=>is_array($transports)?count($transports):null,
        'services_count'=>is_array($services)?count($services):null,'automatic_retry'=>false];
    $private=['price_row'=>$row,'package'=>$package];
} catch(Throwable $e) {
    $token=preg_match('/^[A-Z0-9_]{1,80}$/D',$e->getMessage())?$e->getMessage():'FRESH_PACKAGE_PROBE_FAILED';
    $safe=['status'=>'unknown','phase'=>$phase,'error'=>$token,'supplier_calls'=>$supplierCalls,'database_writes'=>0,
        'booking_calls'=>0,'calc_calls'=>0,'get_flights_calls'=>0,'automatic_retry'=>false];
    if(is_string($transportError)&&preg_match('/^[a-z0-9_]{1,80}$/D',$transportError))$safe['transport_error']=$transportError;
}
$safe['finished_at']=gmdate('c');
while(ob_get_level())ob_end_clean();
echo json_encode(['safe'=>$safe,'private'=>$private],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
'''


def _write_private(path: Path, value):
    flags = os.O_WRONLY | os.O_CREAT | os.O_EXCL
    with os.fdopen(os.open(path, flags, 0o600), 'w') as handle:
        json.dump(value, handle, ensure_ascii=False, separators=(',', ':'))
        handle.flush(); os.fsync(handle.fileno())


def execute(output_directory: Path, runner):
    output_directory.mkdir(mode=0o700)
    result = runner(PHP, {}, maximum_bytes=4000000)
    if not isinstance(result, dict) or set(result) != {'safe','private'} or not isinstance(result['safe'], dict):
        raise ValueError('fresh_package_result_invalid')
    safe = result['safe']
    required = {'status','phase','supplier_calls','database_writes','booking_calls','calc_calls','get_flights_calls','automatic_retry','finished_at'}
    if not required.issubset(safe) or safe['database_writes'] != 0 or safe['booking_calls'] != 0 or safe['calc_calls'] != 0 or safe['get_flights_calls'] != 0 or safe['automatic_retry'] is not False:
        raise ValueError('fresh_package_result_invalid')
    if not isinstance(safe['supplier_calls'], int) or not 0 <= safe['supplier_calls'] <= 3:
        raise ValueError('fresh_package_result_invalid')
    if safe['status'] == 'captured':
        if safe['supplier_calls'] != 3 or result['private'] is None or not isinstance(result['private'], dict):
            raise ValueError('fresh_package_result_invalid')
        if any(k not in SAFE_KEYS for k in safe):
            raise ValueError('fresh_package_result_invalid')
        _write_private(output_directory/'private-package.json', result['private'])
    elif safe['status'] != 'unknown':
        raise ValueError('fresh_package_result_invalid')
    _write_private(output_directory/'result.json', safe)
    return safe


def main():
    if len(sys.argv) != 2 or sys.argv[1] != '--execute':
        raise SystemExit('usage: andromeda_fresh_package_probe.py --execute')
    from anex_search3_owner_decisions import ssh_php
    out = Path(os.environ['RUNNER_TEMP'])/'andromeda-fresh-package'
    safe = execute(out, ssh_php)
    print(json.dumps({k:safe.get(k) for k in ('status','phase','supplier_calls','transport_error','requires_external_flights','buyer_price')}, ensure_ascii=False, sort_keys=True))
    if safe['status'] != 'captured':
        raise SystemExit('fresh package outcome not captured; no automatic retry')


if __name__ == '__main__':
    main()
