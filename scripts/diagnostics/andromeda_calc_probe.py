#!/usr/bin/env python3
"""Recalculate one exact Andromeda claim after required flights are selected.

Diagnostic only. Uses the exact successful v8 selected-claim artifact, performs one
login plus one POST calc, and never calls broninit/get_flights/changeservice/bron.
"""
import hashlib
import json
import os
from pathlib import Path
import sys

EXPECTED_SELECTED_RUN = 34608731086
EXPECTED_SELECTED_RESPONSE_SHA256 = '2a8b53c370cf0195c626f484bebe5aec6ddc571b392ecb3a35bfdd464ccc63c7'
EXPECTED_PRIVATE_FILE_SHA256 = '90d404a87cd2ceca20826fe3d1354c224faab2ba941744feed11bb2c273095e6'

PHP = r'''
error_reporting(0); ini_set('display_errors','0'); ini_set('log_errors','0');
umask(0077); ob_start();
$safe=['status'=>'unknown','phase'=>'preflight','supplier_calls'=>0,'login_calls'=>0,'calc_calls'=>0,
  'get_flights_calls'=>0,'changeservice_calls'=>0,'booking_calls'=>0,'database_writes'=>0,'automatic_retry'=>false];
$private=null;$transportError=null;$supplierError=null;
try {
  if(PHP_SAPI!=='cli')throw new RuntimeException('CLI_ONLY');
  $request=json_decode(stream_get_contents(STDIN),true,64,JSON_THROW_ON_ERROR);
  if(!is_array($request)||array_keys($request)!==['claim']||!is_array($request['claim']))throw new RuntimeException('INVALID_REQUEST');
  $claim=$request['claim'];
  $claimJson=json_encode($claim,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
  if(hash('sha256',$claimJson)!=='2a8b53c370cf0195c626f484bebe5aec6ddc571b392ecb3a35bfdd464ccc63c7')throw new RuntimeException('CLAIM_DIGEST_MISMATCH');
  if(!is_array($claim['claimDocument']??null)||count($claim['claimDocument'])!==1||!is_array($claim['claimDocument'][0]))throw new RuntimeException('CLAIM_SHAPE_INVALID');
  $doc=$claim['claimDocument'][0];
  if(($doc['condition']??null)!=='ccOffer')throw new RuntimeException('CLAIM_NOT_OFFER');
  $trans=[];foreach(($doc['transports']??[]) as $b)if(is_array($b)&&is_array($b['transport']??null))foreach($b['transport'] as $t)if(is_array($t))$trans[]=$t;
  $dirs=[];foreach($trans as $t)$dirs[]=(string)($t['direction']??'');sort($dirs);
  if($dirs!==['0','1'])throw new RuntimeException('SELECTED_FLIGHTS_INVALID');

  $touristPrice=static function(array $claim):?array{
    if(!is_array($claim['claimDocument']??null)||count($claim['claimDocument'])!==1||!is_array($claim['claimDocument'][0]))return null;
    $rows=[];
    foreach(($claim['claimDocument'][0]['buyerMoneys']??[]) as $b)if(is_array($b)&&is_array($b['buyerClaimMoney']??null))foreach($b['buyerClaimMoney'] as $m)if(is_array($m))$rows[]=$m;
    if(count($rows)!==1)return null;$m=$rows[0];$amount=(string)($m['net']??'');$currency=$m['currency']??null;
    if(!preg_match('/^(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?$/D',$amount)||!preg_match('/[1-9]/',$amount)||!is_string($currency)||!preg_match('/^[A-Z0-9_]{2,8}$/D',$currency))return null;
    return ['amount'=>$amount,'currency'=>$currency];
  };
  $before=$touristPrice($claim);

  $root=realpath(getcwd());if(!$root||basename($root)!=='anytoour.ru')throw new RuntimeException('WRONG_PROJECT');
  $target=$root.'/_preview/search3-anex-candidate';
  $clientPath=$target.'/app/integrations/andromeda-client.php';$configPath=$target.'/.andromeda-private.php';
  if(realpath($clientPath)!==$clientPath||!is_file($clientPath)||realpath($configPath)!==$configPath||!is_file($configPath))throw new RuntimeException('RUNTIME_MISSING');
  require_once $clientPath;$config=require $configPath;
  if(!is_array($config)||($config['enabled']??null)!==true||!is_string($config['username']??null)||$config['username']===''||!is_string($config['password']??null)||$config['password']==='')throw new RuntimeException('PRIVATE_CONFIG_MISSING');

  $loginAttempts=0;
  $loginTransport=static function(string $url,array $ignored=[])use(&$loginAttempts,&$transportError):array{
    if(strpos($url,'https://gateway.samo.ru/api/?')!==0||strlen($url)>16384||preg_match('/[\x00-\x20\x7f#]/',$url))throw new RuntimeException('PROBE_ENDPOINT_REJECTED');
    parse_str((string)parse_url($url,PHP_URL_QUERY),$q);
    if(($q['version']??null)!=='1.01'||($q['action']??null)!=='login'||$loginAttempts>=1)throw new RuntimeException('PROBE_ACTION_REJECTED');
    ++$loginAttempts;$h=curl_init();if($h===false)throw new RuntimeException('PROBE_CURL_INIT_FAILED');$body='';
    try{$ok=curl_setopt_array($h,[CURLOPT_URL=>$url,CURLOPT_HTTPGET=>true,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>25,CURLOPT_HEADER=>false,CURLOPT_HTTPHEADER=>['Accept: application/json'],CURLOPT_WRITEFUNCTION=>static function($c,string $chunk)use(&$body):int{if(strlen($body)+strlen($chunk)>2097152)return 0;$body.=$chunk;return strlen($chunk);}]);if(!$ok||curl_exec($h)===false){$transportError='network_transport';throw new RuntimeException('PROBE_NETWORK_FAILURE');}return ['status'=>(int)curl_getinfo($h,CURLINFO_HTTP_CODE),'body'=>$body];}finally{curl_close($h);}
  };
  $safe['phase']='login';$safe['supplier_calls']=1;$safe['login_calls']=1;
  $client=new AnyTourAndromedaClient($loginTransport,true,true);$client->login($config['username'],$config['password']);
  $sid=$client->privateSession()['sid']??null;if(!is_string($sid)||!preg_match('/^[A-Za-z0-9_-]{1,256}$/D',$sid))throw new RuntimeException('LOGIN_SESSION_INVALID');

  usleep(1050000);$safe['phase']='calc';$safe['supplier_calls']=2;$safe['calc_calls']=1;
  $url='https://gateway.samo.ru/api/?'.http_build_query(['version'=>'1.01','action'=>'calc','sid'=>$sid],'','&',PHP_QUERY_RFC3986);
  $post=http_build_query(['claim'=>$claimJson],'','&',PHP_QUERY_RFC3986);
  $h=curl_init();if($h===false)throw new RuntimeException('PROBE_CURL_INIT_FAILED');$body='';
  try{$ok=curl_setopt_array($h,[CURLOPT_URL=>$url,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$post,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>30,CURLOPT_HEADER=>false,CURLOPT_HTTPHEADER=>['Accept: application/json','Content-Type: application/x-www-form-urlencoded'],CURLOPT_WRITEFUNCTION=>static function($c,string $chunk)use(&$body):int{if(strlen($body)+strlen($chunk)>2097152)return 0;$body.=$chunk;return strlen($chunk);}]);if(!$ok||curl_exec($h)===false){$transportError='network_transport';throw new RuntimeException('PROBE_NETWORK_FAILURE');}$http=(int)curl_getinfo($h,CURLINFO_HTTP_CODE);if($http!==200)throw new RuntimeException('ANDROMEDA_HTTP_ERROR');}finally{curl_close($h);}
  $reply=json_decode($body,true,64,JSON_THROW_ON_ERROR);if(!is_array($reply))throw new RuntimeException('ANDROMEDA_INVALID_RESPONSE');
  if(array_key_exists('error',$reply)){$e=$reply['error'];$enc=json_encode($e,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);$summary=['sha256'=>hash('sha256',$enc),'kind'=>gettype($e)];if(is_array($e))foreach(['code','id','message','text','description'] as $f){$v=$e[$f]??null;if(is_string($v)||is_int($v))$summary[$f]=substr(trim((string)$v),0,240);}$supplierError=$summary;throw new RuntimeException('ANDROMEDA_SUPPLIER_ERROR');}
  $after=$touristPrice($reply);if($after===null)throw new RuntimeException('CALC_TOURIST_PRICE_MISSING');
  $outJson=json_encode($reply,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
  $safe=['status'=>'captured','phase'=>'complete','supplier_calls'=>2,'login_calls'=>1,'calc_calls'=>1,'get_flights_calls'=>0,'changeservice_calls'=>0,'booking_calls'=>0,'database_writes'=>0,'automatic_retry'=>false,'input_claim_sha256'=>'2a8b53c370cf0195c626f484bebe5aec6ddc571b392ecb3a35bfdd464ccc63c7','response_sha256'=>hash('sha256',$outJson),'selected_flights'=>2,'price_before_calc'=>$before,'price_after_calc'=>$after,'final_price_verified'=>true];
  $private=$reply;
}catch(Throwable $e){$token=preg_match('/^[A-Z0-9_]{1,80}$/D',$e->getMessage())?$e->getMessage():'CALC_PROBE_FAILED';$safe['status']='unknown';$safe['error']=$token;if(is_string($transportError))$safe['transport_error']=$transportError;if(is_array($supplierError))$safe['supplier_error']=$supplierError;}
$safe['finished_at']=gmdate('c');while(ob_get_level())ob_end_clean();echo json_encode(['safe'=>$safe,'private'=>$private],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
'''


def _write(path: Path, value):
    with os.fdopen(os.open(path, os.O_WRONLY|os.O_CREAT|os.O_EXCL, 0o600),'w') as h:
        json.dump(value,h,ensure_ascii=False,separators=(',',':'));h.flush();os.fsync(h.fileno())

def load_claim(input_directory: Path):
    receipt=json.loads((input_directory/'result.json').read_text()); raw=(input_directory/'private-selected-claim.json').read_bytes()
    if receipt.get('status')!='captured' or receipt.get('response_sha256')!=EXPECTED_SELECTED_RESPONSE_SHA256 or receipt.get('changeservice_calls')!=2 or receipt.get('selected_flights')!=2: raise ValueError('selected_claim_receipt_mismatch')
    if hashlib.sha256(raw).hexdigest()!=EXPECTED_PRIVATE_FILE_SHA256: raise ValueError('selected_claim_artifact_mismatch')
    claim=json.loads(raw.decode('utf-8'))
    if not isinstance(claim,dict): raise ValueError('selected_claim_invalid')
    return claim

def execute(input_directory: Path, output_directory: Path, runner):
    claim=load_claim(input_directory);output_directory.mkdir(mode=0o700)
    reply=runner(PHP,{'claim':claim},maximum_bytes=4000000)
    if not isinstance(reply,dict) or set(reply)!={'safe','private'} or not isinstance(reply['safe'],dict): raise ValueError('calc_result_invalid')
    safe=reply['safe'];required={'status','phase','supplier_calls','login_calls','calc_calls','get_flights_calls','changeservice_calls','booking_calls','database_writes','automatic_retry','finished_at'}
    if not required.issubset(safe) or safe['get_flights_calls']!=0 or safe['changeservice_calls']!=0 or safe['booking_calls']!=0 or safe['database_writes']!=0 or safe['automatic_retry'] is not False or safe['supplier_calls']>2 or safe['calc_calls']>1: raise ValueError('calc_result_invalid')
    if safe['status']=='captured':
        if safe['supplier_calls']!=2 or safe['calc_calls']!=1 or safe.get('final_price_verified') is not True or not isinstance(reply['private'],dict): raise ValueError('calc_result_invalid')
        _write(output_directory/'private-calculated-claim.json',reply['private'])
    elif safe['status']!='unknown': raise ValueError('calc_result_invalid')
    _write(output_directory/'result.json',safe);return safe

def main():
    if len(sys.argv)!=3 or sys.argv[1]!='--execute': raise SystemExit('usage: andromeda_calc_probe.py --execute INPUT_DIRECTORY')
    from anex_search3_owner_decisions import ssh_php
    safe=execute(Path(sys.argv[2]),Path(os.environ['RUNNER_TEMP'])/'andromeda-calc',ssh_php)
    print(json.dumps({k:safe.get(k) for k in ('status','phase','supplier_calls','calc_calls','price_before_calc','price_after_calc','supplier_error')},ensure_ascii=False,sort_keys=True))
    if safe['status']!='captured': raise SystemExit('calc outcome not captured; no retry')

if __name__=='__main__': main()
