#!/usr/bin/env python3
"""Select the only required outbound/return Andromeda flight variants via changeservice.

Diagnostic only. Uses the exact successful get_flights claim, performs login plus at
most two changeservice calls, and never calls calc/bron/booking.
"""
import json
import os
from pathlib import Path
import sys

EXPECTED_FLIGHTS_RUN = 34607247181
EXPECTED_FLIGHTS_RESPONSE_SHA256 = 'a30e6a6d5c6991b6fa56350f6bd766d8a57529b96257389d423fc534391c3a8a'

PHP = r'''
error_reporting(0); ini_set('display_errors','0'); ini_set('log_errors','0');
umask(0077); ob_start();
$safe=['status'=>'unknown','phase'=>'preflight','supplier_calls'=>0,'login_calls'=>0,'changeservice_calls'=>0,
  'calc_calls'=>0,'booking_calls'=>0,'database_writes'=>0,'automatic_retry'=>false];
$private=null;$transportError=null;$supplierError=null;
try {
  if(PHP_SAPI!=='cli')throw new RuntimeException('CLI_ONLY');
  $request=json_decode(stream_get_contents(STDIN),true,64,JSON_THROW_ON_ERROR);
  if(!is_array($request)||array_keys($request)!==['claim']||!is_array($request['claim']))throw new RuntimeException('INVALID_REQUEST');
  $claim=$request['claim'];
  $claimJson=json_encode($claim,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
  if(hash('sha256',$claimJson)!=='a30e6a6d5c6991b6fa56350f6bd766d8a57529b96257389d423fc534391c3a8a')
    throw new RuntimeException('CLAIM_DIGEST_MISMATCH');
  if(!is_array($claim['claimDocument']??null)||count($claim['claimDocument'])!==1||!is_array($claim['claimDocument'][0]))
    throw new RuntimeException('CLAIM_SHAPE_INVALID');
  $doc=$claim['claimDocument'][0];
  $existing=$doc['transports']??null;
  if(!($existing===null||$existing===[null]||$existing===[]))throw new RuntimeException('TRANSPORT_ALREADY_SELECTED');

  $required=[];
  foreach(($claim['groups']??[]) as $block){
    if(!is_array($block)||!is_array($block['group']??null))continue;
    foreach($block['group'] as $g){
      if(!is_array($g))continue;
      if(in_array((string)($g['id']??''),['20001','20002'],true)
        &&(string)($g['required']??'')==='true'&&(string)($g['oneItem']??'')==='true')$required[(string)$g['id']]=true;
    }
  }
  if(count($required)!==2)throw new RuntimeException('REQUIRED_FLIGHT_GROUPS_INVALID');
  $options=[];
  foreach(($claim['variants']??[]) as $variant){
    if(!is_array($variant))continue;
    foreach(($variant['transports']??[]) as $block){
      if(!is_array($block)||!is_array($block['transport']??null))continue;
      foreach($block['transport'] as $item){
        if(!is_array($item))continue;
        $gid=(string)($item['groupId']??'');$dir=(string)($item['direction']??'');
        if(!isset($required[$gid])||!in_array($dir,['0','1'],true))continue;
        if(!is_string($item['uid']??null)||!preg_match('/^[A-Za-z0-9_-]{1,128}$/D',$item['uid']))throw new RuntimeException('FLIGHT_UID_INVALID');
        $options[$dir][]=$item;
      }
    }
  }
  if(count($options['0']??[])!==1||count($options['1']??[])!==1)throw new RuntimeException('FLIGHT_SELECTION_AMBIGUOUS');

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

  $call=static function(array $current,array $item,string $sid)use(&$safe,&$supplierError,&$transportError):array{
    $doc=$current['claimDocument'][0];
    $selected=[];
    foreach(($doc['transports']??[]) as $b)if(is_array($b)&&is_array($b['transport']??null))foreach($b['transport'] as $t)if(is_array($t))$selected[]=$t;
    $selected[]=$item;
    $current['claimDocument'][0]['transports']=[['transport'=>$selected]];
    $json=json_encode($current,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    ++$safe['supplier_calls'];++$safe['changeservice_calls'];$safe['phase']='changeservice';
    // This is an ADD, not a replacement: there is no old transport in claimDocument,
    // so OLD_UID is deliberately omitted. No fallback value is guessed.
    $url='https://gateway.samo.ru/api/?'.http_build_query(['version'=>'1.01','action'=>'changeservice','sid'=>$sid,'NEW_UID'=>$item['uid']],'','&',PHP_QUERY_RFC3986);
    $post=http_build_query(['claim'=>$json],'','&',PHP_QUERY_RFC3986);
    $h=curl_init();if($h===false)throw new RuntimeException('PROBE_CURL_INIT_FAILED');$body='';
    try{$ok=curl_setopt_array($h,[CURLOPT_URL=>$url,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$post,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>30,CURLOPT_HEADER=>false,CURLOPT_HTTPHEADER=>['Accept: application/json','Content-Type: application/x-www-form-urlencoded'],CURLOPT_WRITEFUNCTION=>static function($c,string $chunk)use(&$body):int{if(strlen($body)+strlen($chunk)>2097152)return 0;$body.=$chunk;return strlen($chunk);}]);if(!$ok||curl_exec($h)===false){$transportError='network_transport';throw new RuntimeException('PROBE_NETWORK_FAILURE');}$http=(int)curl_getinfo($h,CURLINFO_HTTP_CODE);if($http!==200)throw new RuntimeException('ANDROMEDA_HTTP_ERROR');}finally{curl_close($h);}
    $reply=json_decode($body,true,64,JSON_THROW_ON_ERROR);if(!is_array($reply))throw new RuntimeException('ANDROMEDA_INVALID_RESPONSE');
    if(array_key_exists('error',$reply)){$e=$reply['error'];$enc=json_encode($e,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);$summary=['sha256'=>hash('sha256',$enc),'kind'=>gettype($e)];if(is_array($e))foreach(['code','id','message','text','description'] as $f){$v=$e[$f]??null;if(is_string($v)||is_int($v))$summary[$f]=substr(trim((string)$v),0,240);}$supplierError=$summary;throw new RuntimeException('ANDROMEDA_SUPPLIER_ERROR');}
    return $reply;
  };

  usleep(1050000);$claim=$call($claim,$options['0'][0],$sid);
  if(!is_array($claim['claimDocument']??null)||count($claim['claimDocument'])!==1)throw new RuntimeException('OUTBOUND_CHANGE_INVALID');
  usleep(1050000);$claim=$call($claim,$options['1'][0],$sid);
  $json=json_encode($claim,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
  $trans=[];foreach(($claim['claimDocument'][0]['transports']??[]) as $b)if(is_array($b)&&is_array($b['transport']??null))foreach($b['transport'] as $t)if(is_array($t))$trans[]=$t;
  $dirs=[];foreach($trans as $t)$dirs[]=(string)($t['direction']??'');sort($dirs);
  if($dirs!==['0','1'])throw new RuntimeException('SELECTED_FLIGHTS_INVALID');
  $safe=['status'=>'captured','phase'=>'complete','supplier_calls'=>3,'login_calls'=>1,'changeservice_calls'=>2,'calc_calls'=>0,'booking_calls'=>0,'database_writes'=>0,'automatic_retry'=>false,'input_claim_sha256'=>'a30e6a6d5c6991b6fa56350f6bd766d8a57529b96257389d423fc534391c3a8a','response_sha256'=>hash('sha256',$json),'selected_flights'=>2,'directions'=>$dirs,'old_uid_mode'=>'omitted_add'];
  $private=$claim;
}catch(Throwable $e){$token=preg_match('/^[A-Z0-9_]{1,80}$/D',$e->getMessage())?$e->getMessage():'FLIGHT_SELECTION_PROBE_FAILED';$safe['status']='unknown';$safe['error']=$token;if(is_string($transportError))$safe['transport_error']=$transportError;if(is_array($supplierError))$safe['supplier_error']=$supplierError;}
$safe['finished_at']=gmdate('c');while(ob_get_level())ob_end_clean();echo json_encode(['safe'=>$safe,'private'=>$private],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
'''


def _write(path: Path, value):
    with os.fdopen(os.open(path, os.O_WRONLY|os.O_CREAT|os.O_EXCL, 0o600),'w') as h:
        json.dump(value,h,ensure_ascii=False,separators=(',',':'));h.flush();os.fsync(h.fileno())

def load_claim(input_directory: Path):
    r=json.loads((input_directory/'result.json').read_text()); c=json.loads((input_directory/'private-flights.json').read_text())
    if r.get('status')!='captured' or r.get('response_sha256')!=EXPECTED_FLIGHTS_RESPONSE_SHA256 or r.get('get_flights_calls')!=1: raise ValueError('get_flights_receipt_mismatch')
    raw=json.dumps(c,ensure_ascii=False,separators=(',',':')).encode()
    import hashlib
    if hashlib.sha256(raw).hexdigest()!=EXPECTED_FLIGHTS_RESPONSE_SHA256: raise ValueError('get_flights_claim_digest_mismatch')
    return c

def execute(input_directory: Path, output_directory: Path, runner):
    claim=load_claim(input_directory);output_directory.mkdir(mode=0o700)
    reply=runner(PHP,{'claim':claim},maximum_bytes=4000000)
    if not isinstance(reply,dict) or set(reply)!={'safe','private'} or not isinstance(reply['safe'],dict): raise ValueError('flight_selection_result_invalid')
    safe=reply['safe'];required={'status','phase','supplier_calls','login_calls','changeservice_calls','calc_calls','booking_calls','database_writes','automatic_retry','finished_at'}
    if not required.issubset(safe) or safe['calc_calls']!=0 or safe['booking_calls']!=0 or safe['database_writes']!=0 or safe['automatic_retry'] is not False or safe['supplier_calls']>3 or safe['changeservice_calls']>2: raise ValueError('flight_selection_result_invalid')
    if safe['status']=='captured':
        if safe['supplier_calls']!=3 or safe['changeservice_calls']!=2 or safe.get('selected_flights')!=2 or not isinstance(reply['private'],dict): raise ValueError('flight_selection_result_invalid')
        _write(output_directory/'private-selected-claim.json',reply['private'])
    elif safe['status']!='unknown': raise ValueError('flight_selection_result_invalid')
    _write(output_directory/'result.json',safe);return safe

def main():
    if len(sys.argv)!=3 or sys.argv[1]!='--execute': raise SystemExit('usage: andromeda_select_required_flights_probe.py --execute INPUT_DIRECTORY')
    from anex_search3_owner_decisions import ssh_php
    safe=execute(Path(sys.argv[2]),Path(os.environ['RUNNER_TEMP'])/'andromeda-select-flights',ssh_php)
    print(json.dumps({k:safe.get(k) for k in ('status','phase','supplier_calls','changeservice_calls','supplier_error','selected_flights')},ensure_ascii=False,sort_keys=True))
    if safe['status']!='captured': raise SystemExit('flight selection outcome not captured; no retry')

if __name__=='__main__': main()
