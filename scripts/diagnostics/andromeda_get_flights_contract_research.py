#!/usr/bin/env python3
"""Temporary research helper: read public SAMO Andromeda contract pages via AnyTour SSH."""
import html
import re
from pathlib import Path
import sys

sys.path.insert(0, str(Path(__file__).resolve().parent))
from anex_search3_owner_decisions import ssh_php

PHP = r'''
error_reporting(0); ini_set('display_errors','0'); ini_set('log_errors','0');
if(!function_exists('curl_init')){echo json_encode(['status'=>'blocked','reason'=>'curl_missing']);return;}
$pages=['bron'=>'andromeda%3Abron','claim'=>'andromeda%3Aclaim_struct'];$out=[];
foreach($pages as $name=>$id){
  $url='https://dokuwiki.samo.ru/doku.php?id='.$id.'&do=export_xhtmlbody';
  $h=curl_init();$body='';$oversize=false;
  try{
    $ok=curl_setopt_array($h,[CURLOPT_URL=>$url,CURLOPT_HTTPGET=>true,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,
      CURLOPT_FOLLOWLOCATION=>true,CURLOPT_MAXREDIRS=>3,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,
      CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>25,CURLOPT_HEADER=>false,
      CURLOPT_WRITEFUNCTION=>static function($c,string $chunk)use(&$body,&$oversize):int{
        if(strlen($body)+strlen($chunk)>2000000){$oversize=true;return 0;}$body.=$chunk;return strlen($chunk);
      }]);
    if(!$ok||curl_exec($h)===false){echo json_encode(['status'=>'blocked','reason'=>$oversize?'too_large':'network','page'=>$name]);return;}
    $code=(int)curl_getinfo($h,CURLINFO_HTTP_CODE);
    if($code!==200){echo json_encode(['status'=>'blocked','reason'=>'http_'.$code,'page'=>$name]);return;}
    $out[$name]=$body;
  }finally{curl_close($h);}
}
echo json_encode(['status'=>'ok','pages'=>$out],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
'''

result=ssh_php(PHP, {}, maximum_bytes=4000000)
if result.get('status')!='ok' or not isinstance(result.get('pages'),dict):
    raise SystemExit('official docs unavailable via server: '+str(result))

def plain(raw):
    text=html.unescape(re.sub(r'<[^>]+>', '\n', raw))
    lines=[re.sub(r'\s+',' ',x).strip() for x in text.splitlines()]
    return [x for x in lines if x]

bron_raw=result['pages'].get('bron','')
bron=plain(bron_raw)
print('=== BRON CONTRACT ===')
hits=[i for i,x in enumerate(bron) if re.search(r'get_flights|freightExternal|\bcalc\b|POST: claim|Пример заявки',x,re.I)]
for i in hits:
    print(f'{i+1}: {bron[i]}')
print('=== BRON RELEVANT LINKS ===')
for href,label in re.findall(r'<a\b[^>]*href="([^"]+)"[^>]*>(.*?)</a>',bron_raw,re.I|re.S):
    label=html.unescape(re.sub(r'<[^>]+>',' ',label));label=re.sub(r'\s+',' ',label).strip()
    if re.search(r'структур|пример заявки|claim|flight',label+' '+href,re.I):
        print(label,'=>',html.unescape(href))

claim=plain(result['pages'].get('claim',''))
print('=== CLAIM STRUCTURE MARKERS ===')
hits=[i for i,x in enumerate(claim) if re.search(r'claimDocument|freightExternal|transport|freight|JSON|заявк|пример',x,re.I)]
shown=set()
for i in hits[:80]:
    for j in range(max(0,i-4),min(len(claim),i+9)):
        if j not in shown:
            print(f'{j+1}: {claim[j]}')
            shown.add(j)
    print('---')
