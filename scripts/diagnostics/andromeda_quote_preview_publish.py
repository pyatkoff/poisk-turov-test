#!/usr/bin/env python3
"""Publish only the checked Andromeda quote runtime into the isolated Search3 preview."""
from __future__ import annotations
import base64
import hashlib
import json
import os
from pathlib import Path
import subprocess
import sys

FILES = {
    'app/integrations/andromeda-claim-actions.php': 'app/integrations/andromeda-claim-actions.php',
    'app/integrations/andromeda-selected-quote.php': 'app/integrations/andromeda-selected-quote.php',
    'api-andromeda-search3-preview.php': 'v2/api-andromeda-search3-preview.php',
    'api-andromeda-quote-preview.php': 'v2/api-andromeda-quote-preview.php',
}
ORDER = [
    'app/integrations/andromeda-claim-actions.php',
    'app/integrations/andromeda-selected-quote.php',
    'api-andromeda-search3-preview.php',
    'api-andromeda-quote-preview.php',
]

PHP = r'''
error_reporting(0);ini_set('display_errors','0');ini_set('log_errors','0');ob_start();
$lock=null;$release=null;$installed=[];$result=['status'=>'blocked'];
function qdurable(string $path,string $bytes):void{
  $f=fopen($path,'x');if(!$f)throw new RuntimeException('write_exists');
  try{if(fwrite($f,$bytes)!==strlen($bytes)||!fflush($f))throw new RuntimeException('write_failed');
    if(function_exists('fsync')&&!fsync($f))throw new RuntimeException('sync_failed');}finally{fclose($f);}
  if(hash_file('sha256',$path)!==hash('sha256',$bytes))throw new RuntimeException('readback_failed');
}
try{
  if(PHP_SAPI!=='cli')throw new RuntimeException('cli_only');
  $input=json_decode(stream_get_contents(STDIN),true,16,JSON_THROW_ON_ERROR);
  if(!is_array($input)||array_keys($input)!==['source','files']||!is_string($input['source'])||!preg_match('/^[0-9a-f]{40}$/D',$input['source'])||!is_array($input['files']))throw new RuntimeException('input');
  $allowed=['app/integrations/andromeda-claim-actions.php','app/integrations/andromeda-selected-quote.php','api-andromeda-search3-preview.php','api-andromeda-quote-preview.php'];
  if(array_keys($input['files'])!==$allowed)throw new RuntimeException('file_set');
  $root=realpath(getcwd());if(!$root||basename($root)!=='anytoour.ru')throw new RuntimeException('wrong_project');
  $target=$root.'/_preview/search3-anex-candidate';$integrations=$target.'/app/integrations';
  if(!is_dir($target)||is_link($target)||!is_dir($integrations)||is_link($integrations))throw new RuntimeException('target');
  $private=dirname($root,2).'/.anytoour-andromeda';if(!is_dir($private)||is_link($private))throw new RuntimeException('private');
  $lock=fopen($private.'/quote-preview-publication.lock','c');if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('lock');
  $release=$private.'/quote-preview-'.$input['source'];$manifest=$release.'/completed.json';
  $decoded=[];$hashes=[];
  foreach($allowed as $rel){
    $row=$input['files'][$rel]??null;if(!is_array($row)||array_keys($row)!==['content','sha256']||!is_string($row['content'])||!is_string($row['sha256'])||!preg_match('/^[0-9a-f]{64}$/D',$row['sha256']))throw new RuntimeException('file_input');
    $bytes=base64_decode($row['content'],true);if($bytes===false||hash('sha256',$bytes)!==$row['sha256']||strlen($bytes)>524288)throw new RuntimeException('file_hash');
    $decoded[$rel]=$bytes;$hashes[$rel]=$row['sha256'];
  }
  if(file_exists($release)){
    $done=is_file($manifest)&&!is_link($manifest)?json_decode(file_get_contents($manifest),true,16,JSON_THROW_ON_ERROR):[];
    if(($done['status']??null)!=='published'||($done['source']??null)!==$input['source']||($done['sha256']??null)!==$hashes)throw new RuntimeException('previous_outcome_unknown');
    foreach($allowed as $rel){$path=$target.'/'.$rel;if(!is_file($path)||is_link($path)||hash_file('sha256',$path)!==$hashes[$rel])throw new RuntimeException('published_target_changed');}
    $result=$done;$result['status']='already_published';
  }else{
    if(!mkdir($release,0700)||!mkdir($release.'/stage',0700)||!mkdir($release.'/backup',0700))throw new RuntimeException('reservation');
    qdurable($release.'/reservation.json',json_encode(['source'=>$input['source'],'sha256'=>$hashes],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
    foreach($allowed as $i=>$rel){
      $targetPath=$target.'/'.$rel;$stage=$release.'/stage/'.$i.'.php';
      if(is_link($targetPath))throw new RuntimeException('symlink_target');
      if(file_exists($targetPath)){
        if(!is_file($targetPath))throw new RuntimeException('invalid_existing_target');
        qdurable($release.'/backup/'.$i.'.php',file_get_contents($targetPath));
      }
      qdurable($stage,$decoded[$rel]);chmod($stage,file_exists($targetPath)?(fileperms($targetPath)&0777):0644);
    }
    try{
      foreach($allowed as $i=>$rel){
        $targetPath=$target.'/'.$rel;$stage=$release.'/stage/'.$i.'.php';
        if(!rename($stage,$targetPath))throw new RuntimeException('rename_failed');
        $installed[]=$i;
        if(hash_file('sha256',$targetPath)!==$hashes[$rel])throw new RuntimeException('target_readback');
        if(function_exists('opcache_invalidate'))opcache_invalidate($targetPath,true);
      }
    }catch(Throwable $publishError){
      foreach(array_reverse($installed) as $i){$rel=$allowed[$i];$targetPath=$target.'/'.$rel;$backup=$release.'/backup/'.$i.'.php';
        if(is_file($backup)){@unlink($targetPath);@rename($backup,$targetPath);}else{@unlink($targetPath);}if(function_exists('opcache_invalidate'))@opcache_invalidate($targetPath,true);}
      throw $publishError;
    }
    $result=['status'=>'published','source'=>$input['source'],'sha256'=>$hashes,
      'target'=>'/_preview/search3-anex-candidate/','files'=>count($allowed),'supplier_calls'=>0,'booking_calls'=>0,'database_writes'=>0];
    qdurable($manifest,json_encode($result,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
  }
}catch(Throwable $e){$result=['status'=>'blocked','reason'=>$e->getMessage(),'installed_count'=>count($installed),'supplier_calls'=>0,'booking_calls'=>0,'database_writes'=>0];}
if($lock){flock($lock,LOCK_UN);fclose($lock);}while(ob_get_level())ob_end_clean();echo json_encode($result,JSON_UNESCAPED_SLASHES);
'''


def sha(data: bytes) -> str:
    return hashlib.sha256(data).hexdigest()


def payload(root: Path, source: str) -> dict:
    if not __import__('re').fullmatch(r'[0-9a-f]{40}', source):
        raise ValueError('source_sha')
    files = {}
    for target, source_path in FILES.items():
        data = (root / source_path).read_bytes()
        if not data or len(data) > 524288:
            raise ValueError('source_file_size')
        files[target] = {'content': base64.b64encode(data).decode(), 'sha256': sha(data)}
    if list(files) != ORDER:
        raise ValueError('file_order')
    return {'source': source, 'files': files}


def main() -> None:
    if len(sys.argv) != 2:
        raise SystemExit('usage: andromeda_quote_preview_publish.py SOURCE_CHECKOUT')
    root = Path(sys.argv[1]).resolve()
    source = os.environ.get('QUOTE_SOURCE_SHA', '')
    actual = subprocess.check_output(['git','-C',str(root),'rev-parse','HEAD'], text=True).strip()
    if actual != source:
        raise SystemExit('source checkout mismatch')
    data = payload(root, source)
    helper = os.environ.get('QUOTE_HELPER_PATH', '')
    if not helper:
        raise SystemExit('helper path missing')
    sys.path.insert(0, str(Path(helper).resolve() / 'scripts' / 'diagnostics'))
    from anex_search3_owner_decisions import ssh_php
    result = ssh_php(PHP, data, maximum_bytes=65536)
    out = Path(os.environ['RUNNER_TEMP']) / 'andromeda-quote-publication'
    out.mkdir(mode=0o700)
    receipt = out / 'result.json'
    receipt.write_text(json.dumps(result, ensure_ascii=False, separators=(',',':')))
    if result.get('status') not in ('published','already_published') or result.get('source') != source \
       or result.get('supplier_calls') != 0 or result.get('booking_calls') != 0 or result.get('database_writes') != 0:
        raise SystemExit('publication not confirmed; inspect retained receipt, do not replay')
    expected = {name: row['sha256'] for name, row in data['files'].items()}
    if result.get('sha256') != expected or result.get('files') != 4:
        raise SystemExit('publication readback mismatch')
    print(json.dumps({'status':result['status'],'source':source,'files':4,'supplier_calls':0,'booking_calls':0}))

if __name__ == '__main__':
    main()
