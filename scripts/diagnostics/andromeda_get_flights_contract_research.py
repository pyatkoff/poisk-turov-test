#!/usr/bin/env python3
"""Temporary research helper: read the public SAMO Andromeda bron wiki via AnyTour SSH."""
import html
import re
from pathlib import Path
import sys

sys.path.insert(0, str(Path(__file__).resolve().parent))
from anex_search3_owner_decisions import ssh_php

PHP = r'''
error_reporting(0); ini_set('display_errors','0'); ini_set('log_errors','0');
$url='https://dokuwiki.samo.ru/doku.php?id=andromeda%3Abron&do=export_xhtmlbody';
if(!function_exists('curl_init')){echo json_encode(['status'=>'blocked','reason'=>'curl_missing']);return;}
$h=curl_init();$body='';$oversize=false;
try{
  $ok=curl_setopt_array($h,[CURLOPT_URL=>$url,CURLOPT_HTTPGET=>true,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,
    CURLOPT_FOLLOWLOCATION=>true,CURLOPT_MAXREDIRS=>3,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,
    CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>25,CURLOPT_HEADER=>false,
    CURLOPT_WRITEFUNCTION=>static function($c,string $chunk)use(&$body,&$oversize):int{
      if(strlen($body)+strlen($chunk)>2000000){$oversize=true;return 0;}$body.=$chunk;return strlen($chunk);
    }]);
  if(!$ok||curl_exec($h)===false){echo json_encode(['status'=>'blocked','reason'=>$oversize?'too_large':'network']);return;}
  $code=(int)curl_getinfo($h,CURLINFO_HTTP_CODE);
  if($code!==200){echo json_encode(['status'=>'blocked','reason'=>'http_'.$code]);return;}
  echo json_encode(['status'=>'ok','body'=>$body],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
}finally{curl_close($h);}
'''

result=ssh_php(PHP, {}, maximum_bytes=4000000)
if result.get('status')!='ok' or not isinstance(result.get('body'),str):
    raise SystemExit('official docs unavailable via server: '+str(result))
raw=result['body']
text=html.unescape(re.sub(r'<[^>]+>', '\n', raw))
lines=[re.sub(r'\s+',' ',x).strip() for x in text.splitlines()]
lines=[x for x in lines if x]
hits=[i for i,x in enumerate(lines) if re.search(r'get_flights|freightExternal|\bcalc\b|1110|Рейсы',x,re.I)]
if not hits:
    raise SystemExit('contract markers not found')
shown=set()
for i in hits:
    for j in range(max(0,i-15),min(len(lines),i+25)):
        if j not in shown:
            print(f'{j+1}: {lines[j]}')
            shown.add(j)
    print('---')
