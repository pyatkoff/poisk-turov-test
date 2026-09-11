#!/usr/bin/env python3
"""Capture only a sanitized SAMO supplier-error summary for a NEW wide-window broninit attempt."""
import json
import os
from pathlib import Path
import sys

sys.path.insert(0, str(Path(__file__).resolve().parent))
import andromeda_fresh_package_probe as base
import andromeda_fresh_package_probe_wide as wide

SOURCE = wide.WIDE_PHP
PATCHES = [
    (
        "$supplierCalls=0; $phase='preflight'; $transportError=null;",
        "$supplierCalls=0; $phase='preflight'; $transportError=null; $supplierError=null;",
    ),
    (
        "$transport=static function(string $url,array $ignored=[])use(&$attempts,&$lastStarted,&$transportError):array{",
        "$transport=static function(string $url,array $ignored=[])use(&$attempts,&$lastStarted,&$transportError,&$supplierError):array{",
    ),
    (
        "            return ['status'=>(int)curl_getinfo($handle,CURLINFO_HTTP_CODE),'body'=>$body];",
        r'''            if(($query['action']??null)==='broninit'){
                try{
                    $decoded=json_decode($body,true,32,JSON_THROW_ON_ERROR);
                    if(is_array($decoded)&&array_key_exists('error',$decoded)){
                        $error=$decoded['error'];
                        $encoded=json_encode($error,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
                        $redacted=false;
                        foreach([(string)($query['sid']??''),(string)($query['claiminc']??'')] as $secret){
                            if($secret!==''&&(strpos($encoded,$secret)!==false||strpos($encoded,rawurlencode($secret))!==false)){$redacted=true;break;}
                        }
                        $summary=['sha256'=>hash('sha256',$encoded),'kind'=>gettype($error)];
                        if($redacted){$summary['redacted']=true;}
                        elseif(is_string($error)){
                            $text=trim($error);
                            if($text!==''&&strlen($text)<=240&&preg_match('//u',$text)===1&&!preg_match('/[\x00-\x1f\x7f]/',$text))$summary['text']=$text;
                        }elseif(is_array($error)){
                            $keys=[];
                            foreach(array_keys($error) as $key)if(is_string($key)&&preg_match('/^[A-Za-z0-9_.-]{1,80}$/D',$key))$keys[]=$key;
                            $summary['keys']=array_slice($keys,0,20);
                            foreach(['code','id','message','text','description'] as $field){
                                $value=$error[$field]??null;
                                if(!(is_string($value)||is_int($value)))continue;
                                $value=trim((string)$value);
                                if($value===''||strlen($value)>240||preg_match('//u',$value)!==1||preg_match('/[\x00-\x1f\x7f]/',$value))continue;
                                $blocked=false;
                                foreach([(string)($query['sid']??''),(string)($query['claiminc']??'')] as $secret)
                                    if($secret!==''&&(strpos($value,$secret)!==false||strpos($value,rawurlencode($secret))!==false)){$blocked=true;break;}
                                if(!$blocked)$summary[$field]=$value;
                            }
                        }
                        $supplierError=$summary;
                    }
                }catch(Throwable $ignored){$supplierError=['capture'=>'unavailable'];}
            }
            return ['status'=>(int)curl_getinfo($handle,CURLINFO_HTTP_CODE),'body'=>$body];''',
    ),
    (
        "    if(is_string($transportError)&&preg_match('/^[a-z0-9_]{1,80}$/D',$transportError))$safe['transport_error']=$transportError;",
        "    if(is_string($transportError)&&preg_match('/^[a-z0-9_]{1,80}$/D',$transportError))$safe['transport_error']=$transportError;\n    if(is_array($supplierError))$safe['supplier_error']=$supplierError;",
    ),
]

SUPPLIER_ERROR_PHP = SOURCE
for old, new in PATCHES:
    if SUPPLIER_ERROR_PHP.count(old) != 1:
        raise RuntimeError('supplier_error_probe_source_mismatch')
    SUPPLIER_ERROR_PHP = SUPPLIER_ERROR_PHP.replace(old, new)


def execute(output_directory: Path, runner):
    def patched_runner(_source, request, maximum_bytes=0):
        return runner(SUPPLIER_ERROR_PHP, request, maximum_bytes=maximum_bytes)
    return base.execute(output_directory, patched_runner)


def main():
    if len(sys.argv) != 2 or sys.argv[1] != '--execute':
        raise SystemExit('usage: andromeda_fresh_package_supplier_error_probe.py --execute')
    from anex_search3_owner_decisions import ssh_php
    out = Path(os.environ['RUNNER_TEMP'])/'andromeda-fresh-package'
    safe = execute(out, ssh_php)
    print(json.dumps({k:safe.get(k) for k in ('status','phase','supplier_calls','transport_error','supplier_error')}, ensure_ascii=False, sort_keys=True))
    if safe['status'] != 'captured':
        raise SystemExit('fresh package not captured; supplier error evidence saved; no automatic retry')


if __name__ == '__main__':
    main()
