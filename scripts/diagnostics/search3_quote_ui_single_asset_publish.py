#!/usr/bin/env python3
"""Publish only the checked Search3 verified-quote JS asset to the isolated site candidate."""
from __future__ import annotations
import base64, hashlib, json, os, re, subprocess, sys
from pathlib import Path

SOURCE_FILE = 'v2/search3-results-cards-v2.js'
TARGET_FILE = 'poisk-turov/v2/search3-results-cards-v2.js'
PHP = r'''
error_reporting(0);ini_set('display_errors','0');ini_set('log_errors','0');ob_start();
$lock=null;$result=['status'=>'blocked'];$installed=false;
function durable(string $path,string $bytes):void{$f=fopen($path,'x');if(!$f)throw new RuntimeException('write_exists');try{if(fwrite($f,$bytes)!==strlen($bytes)||!fflush($f))throw new RuntimeException('write_failed');if(function_exists('fsync')&&!fsync($f))throw new RuntimeException('sync_failed');}finally{fclose($f);}if(hash_file('sha256',$path)!==hash('sha256',$bytes))throw new RuntimeException('readback_failed');}
try{
 if(PHP_SAPI!=='cli')throw new RuntimeException('cli_only');
 $in=json_decode(stream_get_contents(STDIN),true,8,JSON_THROW_ON_ERROR);
 if(!is_array($in)||array_keys($in)!==['source','content','sha256']||!is_string($in['source'])||!preg_match('/^[0-9a-f]{40}$/D',$in['source'])||!is_string($in['content'])||!is_string($in['sha256'])||!preg_match('/^[0-9a-f]{64}$/D',$in['sha256']))throw new RuntimeException('input');
 $bytes=base64_decode($in['content'],true);if($bytes===false||strlen($bytes)<100||strlen($bytes)>524288||hash('sha256',$bytes)!==$in['sha256'])throw new RuntimeException('content');
 $root=realpath(getcwd());if(!$root||basename($root)!=='anytoour.ru')throw new RuntimeException('wrong_project');
 $preview=$root.'/_preview/search3-site-candidate';$target=$preview.'/poisk-turov/v2/search3-results-cards-v2.js';
 if(!is_dir($preview)||is_link($preview)||!is_file($target)||is_link($target))throw new RuntimeException('target');
 $private=dirname($root,2).'/.anytoour-andromeda';if(!is_dir($private)||is_link($private))throw new RuntimeException('private');
 $lock=fopen($private.'/search3-quote-ui-publication.lock','c');if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('lock');
 $release=$private.'/search3-quote-ui-'.$in['source'];$manifest=$release.'/completed.json';
 if(file_exists($release)){
   $done=is_file($manifest)&&!is_link($manifest)?json_decode(file_get_contents($manifest),true,8,JSON_THROW_ON_ERROR):[];
   if(($done['status']??null)!=='published'||($done['source']??null)!==$in['source']||($done['sha256']??null)!==$in['sha256'])throw new RuntimeException('previous_outcome_unknown');
   if(hash_file('sha256',$target)!==$in['sha256'])throw new RuntimeException('published_target_changed');$result=$done;$result['status']='already_published';
 }else{
   if(!mkdir($release,0700)||!mkdir($release.'/stage',0700))throw new RuntimeException('reservation');
   durable($release.'/reservation.json',json_encode(['source'=>$in['source'],'sha256'=>$in['sha256']],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
   $backup=file_get_contents($target);durable($release.'/backup.js',$backup);durable($release.'/stage/asset.js',$bytes);
   try{if(!rename($release.'/stage/asset.js',$target))throw new RuntimeException('rename_failed');$installed=true;if(hash_file('sha256',$target)!==$in['sha256'])throw new RuntimeException('target_readback');}
   catch(Throwable $e){if($installed){@unlink($target);@rename($release.'/backup.js',$target);}throw $e;}
   $result=['status'=>'published','source'=>$in['source'],'sha256'=>$in['sha256'],'target'=>'/_preview/search3-site-candidate/poisk-turov/v2/search3-results-cards-v2.js','files'=>1,'supplier_calls'=>0,'booking_calls'=>0,'database_writes'=>0];
   durable($manifest,json_encode($result,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
 }
}catch(Throwable $e){$result=['status'=>'blocked','reason'=>$e->getMessage(),'installed'=>$installed,'supplier_calls'=>0,'booking_calls'=>0,'database_writes'=>0];}
if($lock){flock($lock,LOCK_UN);fclose($lock);}while(ob_get_level())ob_end_clean();echo json_encode($result,JSON_UNESCAPED_SLASHES);
'''

def main() -> None:
    if len(sys.argv)!=2: raise SystemExit('usage: search3_quote_ui_single_asset_publish.py SOURCE_CHECKOUT')
    root=Path(sys.argv[1]).resolve(); source=os.environ.get('QUOTE_UI_SOURCE_SHA','')
    if not re.fullmatch(r'[0-9a-f]{40}',source): raise SystemExit('source sha missing')
    actual=subprocess.check_output(['git','-C',str(root),'rev-parse','HEAD'],text=True).strip()
    if actual!=source: raise SystemExit('source checkout mismatch')
    data=(root/SOURCE_FILE).read_bytes(); digest=hashlib.sha256(data).hexdigest()
    if b'Search3Andromeda' not in data and b'search3-selected-flow-v2' not in data: raise SystemExit('verified quote module missing')
    helper=os.environ.get('QUOTE_UI_HELPER_PATH','');
    if not helper: raise SystemExit('helper path missing')
    sys.path.insert(0,str(Path(helper).resolve()/'scripts'/'diagnostics'))
    from anex_search3_owner_decisions import ssh_php
    result=ssh_php(PHP,{'source':source,'content':base64.b64encode(data).decode(),'sha256':digest},maximum_bytes=65536)
    out=Path(os.environ['RUNNER_TEMP'])/'search3-quote-ui-publication';out.mkdir(mode=0o700,parents=True,exist_ok=True)
    (out/'result.json').write_text(json.dumps(result,ensure_ascii=False,separators=(',',':')))
    if result.get('status') not in ('published','already_published') or result.get('source')!=source or result.get('sha256')!=digest or result.get('files')!=1 or result.get('supplier_calls')!=0 or result.get('booking_calls')!=0 or result.get('database_writes')!=0: raise SystemExit('publication not confirmed; inspect retained receipt, do not replay')
    print(json.dumps({'status':result['status'],'source':source,'files':1,'supplier_calls':0,'booking_calls':0}))
if __name__=='__main__': main()
