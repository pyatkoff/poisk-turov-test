#!/usr/bin/env python3
"""One-shot read-only Andromeda get_flights probe for the exact successful v5 claim."""
import json
import os
from pathlib import Path
import sys

EXPECTED_PACKAGE_SHA256 = '455525ffd6891945c6aceb36c0762c7c289373b5c30b2c15ef5a5b4d4a33d89a'
EXPECTED_ARTIFACT_RUN = 34605764015

PHP = r'''
error_reporting(0); ini_set('display_errors','0'); ini_set('log_errors','0');
umask(0077); ob_start();
$safe=['status'=>'unknown','phase'=>'preflight','supplier_calls'=>0,'login_calls'=>0,'get_flights_calls'=>0,
  'calc_calls'=>0,'booking_calls'=>0,'database_writes'=>0,'automatic_retry'=>false];
$private=null;$transportError=null;$supplierError=null;
try {
  if(PHP_SAPI!=='cli')throw new RuntimeException('CLI_ONLY');
  $request=json_decode(stream_get_contents(STDIN),true,64,JSON_THROW_ON_ERROR);
  if(!is_array($request)||array_keys($request)!==['claim']||!is_array($request['claim']))throw new RuntimeException('INVALID_REQUEST');
  $claim=$request['claim'];
  $claimJson=json_encode($claim,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
  if(hash('sha256',$claimJson)!=='455525ffd6891945c6aceb36c0762c7c289373b5c30b2c15ef5a5b4d4a33d89a')
    throw new RuntimeException('CLAIM_DIGEST_MISMATCH');
  if(!isset($claim['version'],$claim['claimDocument'])||!is_array($claim['claimDocument'])||count($claim['claimDocument'])!==1)
    throw new RuntimeException('CLAIM_SHAPE_INVALID');
  $doc=$claim['claimDocument'][0];
  if(!is_array($doc)||!isset($doc['freightExternal'])||(int)$doc['freightExternal']<=0)
    throw new RuntimeException('FLIGHTS_NOT_REQUIRED');

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

  $loginAttempts=0;
  $loginTransport=static function(string $url,array $ignored=[])use(&$loginAttempts,&$transportError):array{
    if(strpos($url,'https://gateway.samo.ru/api/?')!==0||strlen($url)>16384||preg_match('/[\x00-\x20\x7f#]/',$url))
      throw new RuntimeException('PROBE_ENDPOINT_REJECTED');
    parse_str((string)parse_url($url,PHP_URL_QUERY),$query);
    if(($query['version']??null)!=='1.01'||($query['action']??null)!=='login'||$loginAttempts>=1)
      throw new RuntimeException('PROBE_ACTION_REJECTED');
    ++$loginAttempts;
    if(!function_exists('curl_init')){$transportError='local_curl_missing';throw new RuntimeException('PROBE_CURL_REQUIRED');}
    $h=curl_init();if($h===false){$transportError='local_curl_init';throw new RuntimeException('PROBE_CURL_INIT_FAILED');}
    $body='';$oversize=false;
    try{
      $ok=curl_setopt_array($h,[CURLOPT_URL=>$url,CURLOPT_HTTPGET=>true,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,
        CURLOPT_FOLLOWLOCATION=>false,CURLOPT_MAXREDIRS=>0,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,
        CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>25,CURLOPT_HEADER=>false,CURLOPT_HTTPHEADER=>['Accept: application/json'],
        CURLOPT_WRITEFUNCTION=>static function($c,string $chunk)use(&$body,&$oversize):int{
          if(strlen($body)+strlen($chunk)>2097152){$oversize=true;return 0;}$body.=$chunk;return strlen($chunk);
        }]);
      if(!$ok){$transportError='local_curl_setup';throw new RuntimeException('PROBE_CURL_SETUP_FAILED');}
      if(curl_exec($h)===false){$transportError=$oversize?'response_too_large':'network_transport';throw new RuntimeException('PROBE_NETWORK_FAILURE');}
      return ['status'=>(int)curl_getinfo($h,CURLINFO_HTTP_CODE),'body'=>$body];
    }finally{curl_close($h);}
  };

  $safe['phase']='login';$safe['supplier_calls']=1;$safe['login_calls']=1;
  $client=new AnyTourAndromedaClient($loginTransport,true,true);
  $client->login($config['username'],$config['password']);
  $session=$client->privateSession();$sid=$session['sid']??null;
  if(!is_string($sid)||!preg_match('/^[A-Za-z0-9_-]{1,256}$/D',$sid))throw new RuntimeException('LOGIN_SESSION_INVALID');

  usleep(1050000);
  $safe['phase']='get_flights';$safe['supplier_calls']=2;$safe['get_flights_calls']=1;
  $url='https://gateway.samo.ru/api/?'.http_build_query(['version'=>'1.01','action'=>'get_flights','sid'=>$sid],'','&',PHP_QUERY_RFC3986);
  $post=http_build_query(['claim'=>$claimJson],'','&',PHP_QUERY_RFC3986);
  $h=curl_init();if($h===false)throw new RuntimeException('PROBE_CURL_INIT_FAILED');
  $body='';$oversize=false;
  try{
    $ok=curl_setopt_array($h,[CURLOPT_URL=>$url,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$post,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,
      CURLOPT_FOLLOWLOCATION=>false,CURLOPT_MAXREDIRS=>0,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,
      CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>30,CURLOPT_HEADER=>false,
      CURLOPT_HTTPHEADER=>['Accept: application/json','Content-Type: application/x-www-form-urlencoded'],
      CURLOPT_WRITEFUNCTION=>static function($c,string $chunk)use(&$body,&$oversize):int{
        if(strlen($body)+strlen($chunk)>2097152){$oversize=true;return 0;}$body.=$chunk;return strlen($chunk);
      }]);
    if(!$ok)throw new RuntimeException('PROBE_CURL_SETUP_FAILED');
    if(curl_exec($h)===false){$transportError=$oversize?'response_too_large':'network_transport';throw new RuntimeException('PROBE_NETWORK_FAILURE');}
    $http=(int)curl_getinfo($h,CURLINFO_HTTP_CODE);
    if($http!==200)throw new RuntimeException('ANDROMEDA_HTTP_ERROR');
  }finally{curl_close($h);}
  $reply=json_decode($body,true,64,JSON_THROW_ON_ERROR);
  if(!is_array($reply))throw new RuntimeException('ANDROMEDA_INVALID_RESPONSE');
  $replyJson=json_encode($reply,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
  foreach([$sid,rawurlencode($sid)] as $secret)if($secret!==''&&strpos($replyJson,$secret)!==false)throw new RuntimeException('SESSION_ECHO');
  if(array_key_exists('error',$reply)){
    $error=$reply['error'];$encoded=json_encode($error,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    $summary=['sha256'=>hash('sha256',$encoded),'kind'=>gettype($error)];
    if(is_string($error)){
      $text=trim($error);if($text!==''&&strlen($text)<=240&&preg_match('//u',$text)===1&&!preg_match('/[\x00-\x1f\x7f]/',$text))$summary['text']=$text;
    }elseif(is_array($error)){
      $summary['keys']=array_slice(array_values(array_filter(array_keys($error),static fn($k)=>is_string($k)&&preg_match('/^[A-Za-z0-9_.-]{1,80}$/D',$k))),0,20);
      foreach(['code','id','message','text','description'] as $field){$v=$error[$field]??null;if(is_string($v)||is_int($v)){$v=trim((string)$v);if($v!==''&&strlen($v)<=240&&preg_match('//u',$v)===1&&!preg_match('/[\x00-\x1f\x7f]/',$v))$summary[$field]=$v;}}
    }
    $supplierError=$summary;throw new RuntimeException('ANDROMEDA_SUPPLIER_ERROR');
  }
  $docOut=is_array($reply['claimDocument']??null)&&isset($reply['claimDocument'][0])&&is_array($reply['claimDocument'][0])?$reply['claimDocument'][0]:null;
  $external=$docOut['freightExternal']??null;
  $safe=['status'=>'captured','phase'=>'complete','supplier_calls'=>2,'login_calls'=>1,'get_flights_calls'=>1,
    'calc_calls'=>0,'booking_calls'=>0,'database_writes'=>0,'automatic_retry'=>false,
    'input_claim_sha256'=>'455525ffd6891945c6aceb36c0762c7c289373b5c30b2c15ef5a5b4d4a33d89a',
    'response_sha256'=>hash('sha256',$replyJson),'top_fields'=>array_values(array_filter(array_keys($reply),'is_string')),
    'claim_document_count'=>is_array($reply['claimDocument']??null)?count($reply['claimDocument']):null,
    'variants_count'=>is_array($reply['variants']??null)?count($reply['variants']):null,
    'groups_count'=>is_array($reply['groups']??null)?count($reply['groups']):null,
    'requires_external_flights'=>(is_int($external)||is_string($external))?(int)$external>0:null];
  $private=$reply;
}catch(Throwable $e){
  $token=preg_match('/^[A-Z0-9_]{1,80}$/D',$e->getMessage())?$e->getMessage():'GET_FLIGHTS_PROBE_FAILED';
  $safe['status']='unknown';$safe['error']=$token;
  if(is_string($transportError))$safe['transport_error']=$transportError;
  if(is_array($supplierError))$safe['supplier_error']=$supplierError;
}
$safe['finished_at']=gmdate('c');
while(ob_get_level())ob_end_clean();
echo json_encode(['safe'=>$safe,'private'=>$private],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
'''


def _write(path: Path, value):
    with os.fdopen(os.open(path, os.O_WRONLY|os.O_CREAT|os.O_EXCL, 0o600),'w') as h:
        json.dump(value,h,ensure_ascii=False,separators=(',',':'));h.flush();os.fsync(h.fileno())


def load_claim(input_directory: Path):
    result=json.loads((input_directory/'result.json').read_text())
    private=json.loads((input_directory/'private-package.json').read_text())
    if result.get('status')!='captured' or result.get('package_sha256')!=EXPECTED_PACKAGE_SHA256 \
       or result.get('requires_external_flights') is not True or result.get('supplier_calls')!=3:
        raise ValueError('v5_package_receipt_mismatch')
    if set(private)!= {'price_row','package'} or not isinstance(private['package'],dict):
        raise ValueError('v5_private_package_invalid')
    claim=private['package']
    if not isinstance(claim.get('claimDocument'),list) or len(claim['claimDocument'])!=1 \
       or not isinstance(claim.get('version'),str):
        raise ValueError('v5_claim_shape_invalid')
    return claim


def execute(input_directory: Path, output_directory: Path, runner):
    claim=load_claim(input_directory)
    output_directory.mkdir(mode=0o700)
    reply=runner(PHP, {'claim':claim}, maximum_bytes=4000000)
    if not isinstance(reply,dict) or set(reply)!= {'safe','private'} or not isinstance(reply['safe'],dict):
        raise ValueError('get_flights_result_invalid')
    safe=reply['safe']
    required={'status','phase','supplier_calls','login_calls','get_flights_calls','calc_calls','booking_calls','database_writes','automatic_retry','finished_at'}
    if not required.issubset(safe) or safe['calc_calls']!=0 or safe['booking_calls']!=0 or safe['database_writes']!=0 \
       or safe['automatic_retry'] is not False or safe['supplier_calls']>2 or safe['get_flights_calls']>1:
        raise ValueError('get_flights_result_invalid')
    if safe['status']=='captured':
        if safe['supplier_calls']!=2 or safe['login_calls']!=1 or safe['get_flights_calls']!=1 or not isinstance(reply['private'],dict):
            raise ValueError('get_flights_result_invalid')
        _write(output_directory/'private-flights.json',reply['private'])
    elif safe['status']!='unknown':
        raise ValueError('get_flights_result_invalid')
    _write(output_directory/'result.json',safe)
    return safe


def main():
    if len(sys.argv)!=3 or sys.argv[1]!='--execute':
        raise SystemExit('usage: andromeda_get_flights_probe.py --execute INPUT_DIRECTORY')
    from anex_search3_owner_decisions import ssh_php
    safe=execute(Path(sys.argv[2]),Path(os.environ['RUNNER_TEMP'])/'andromeda-get-flights',ssh_php)
    print(json.dumps({k:safe.get(k) for k in ('status','phase','supplier_calls','get_flights_calls','supplier_error','top_fields','requires_external_flights')},ensure_ascii=False,sort_keys=True))
    if safe['status']!='captured':raise SystemExit('get_flights outcome not captured; no retry')

if __name__=='__main__':main()
