#!/usr/bin/env python3
"""Publish one CI-checked Andromeda source handoff to the isolated integration preview.

Assembly and publication share one explicit source inventory. It never calls a supplier, database, quote or booking API.
The caller must pin and verify the source artifact/run before invoking it.
"""
from __future__ import annotations

import base64
import hashlib
import json
import os
from pathlib import Path
import sys

# One inventory for the candidate overlay, tested artifact and publisher allowlist.
# The two package-only files retain their reviewed historical source, not a fallback.
SOURCE_ORIGINS = {
    'app/integrations/andromeda-client.php': 'current',
    'app/integrations/andromeda-package-capture.php': 'package',
    'app/integrations/andromeda-selected-offer.php': 'package',
    'app/integrations/andromeda-transport.php': 'current',
    'app/integrations/andromeda-saved-package-runtime.php': 'current',
    'app/integrations/andromeda-claiminc-contract.php': 'current',
    'app/integrations/andromeda-network-transport-failure.php': 'current',
    'app/integrations/andromeda-package-retry-policy.php': 'current',
    'app/integrations/andromeda-package-attempt-state.php': 'current',
    'app/integrations/andromeda-claim-actions.php': 'current',
    'app/integrations/andromeda-search-surcharge.php': 'current',
    'app/integrations/andromeda-selected-quote.php': 'current',
    'app/integrations/andromeda-price-observation.php': 'current',
    'app/integrations/andromeda-quote-attempt-state.php': 'current',
    'v2/api-andromeda-quote-preview.php': 'current',
    'v2/api-andromeda-search3-preview.php': 'current',
}
SOURCE_PATHS = list(SOURCE_ORIGINS)
TARGET_PATHS = [p[3:] if p.startswith('v2/') else p for p in SOURCE_PATHS]
SUPPORT_PATHS = [
    'app/integrations/andromeda-offer-store.php',
    'app/integrations/andromeda-normalizer.php',
    'app/integrations/andromeda-hotel-resolver.php',
    'app/integrations/andromeda-search.php',
    'app/integrations/andromeda-hotel-observations.php',
    'app/integrations/anex-normalizer.php',
]
# The pinned SSH helper accepts exactly 64 KiB for ordinary diagnostic receipts
# (or 4 MiB for explicitly large diagnostics). This publisher's receipt is small.
SSH_RESPONSE_LIMIT = 65536


def _sha(data: bytes) -> str:
    return hashlib.sha256(data).hexdigest()


def handoff_metadata(source: str) -> dict:
    if not __import__('re').fullmatch(r'[0-9a-f]{40}', source):
        raise ValueError('source_sha')
    return {
        'source': source,
        'dependencies': {
            'runtime': '95fa11f5a1cb7e18c905c9760a1eae7f2c0ee7aa',
            'package': '7c3c5bb55c88e376d97c998d0ba77a46960584d6',
            'candidate_client': source, 'candidate_surcharge': source,
            'candidate_transport': source, 'candidate_quote': source,
            'retained_install_transport': '1f628e4b172ce9706eabe875784ce2b226175a0d',
            'retained_install_baseline': '163ef9eed9993c08558b84b7a0295e8b4295d2e8',
        },
        'scope': 'private-source-handoff-only', 'supplier_calls': 0,
        'published': False, 'package_captured_live': False, 'quote_verified': False,
        'typed_transport_in_candidate': True, 'live_retry_enabled': False,
        'saved_surcharge_available': True, 'live_surcharge_enabled': False,
    }


def candidate_files(current: Path, package: Path) -> dict[str, bytes]:
    roots = {'current': Path(current), 'package': Path(package)}
    files = {}
    for name, origin in SOURCE_ORIGINS.items():
        root = roots[origin]
        path = root / name
        if (root.is_symlink() or not root.is_dir() or not path.is_file()
                or path.resolve() != root.resolve() / name or path.stat().st_size > 524288):
            raise ValueError('candidate_source:' + name)
        data = path.read_bytes()
        if not data:
            raise ValueError('candidate_source:' + name)
        files[name] = data
    return files


def assemble_runtime(current: Path, package: Path, runtime: Path) -> None:
    """Overlay the exact candidate only after historical install regressions finish."""
    files = candidate_files(current, package)  # Validate every source before writing.
    runtime = Path(runtime)
    if runtime.is_symlink() or not runtime.is_dir():
        raise ValueError('runtime_directory')
    for name, data in files.items():
        target = runtime / name
        if target.resolve() != runtime.resolve() / name:
            raise ValueError('runtime_entry')
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_bytes(data)


def export_handoff(current: Path, package: Path, runtime: Path, output: Path, source: str) -> dict:
    """Export only bytes still identical to the sources after candidate runtime tests."""
    metadata = handoff_metadata(source)
    files = candidate_files(current, package)
    runtime = Path(runtime)
    if runtime.is_symlink() or not runtime.is_dir():
        raise ValueError('runtime_directory')
    for name, data in files.items():
        tested = runtime / name
        if (tested.resolve() != runtime.resolve() / name or not tested.is_file()
                or tested.read_bytes() != data):
            raise ValueError('tested_runtime_changed:' + name)
    output = Path(output)
    output.mkdir(parents=True, exist_ok=False)
    for name, data in files.items():
        target = output / name
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_bytes(data)
    receipt = metadata | {'files_sha256': {name: _sha(data) for name, data in files.items()}}
    (output / 'receipt.json').write_text(json.dumps(receipt, sort_keys=True, indent=2) + '\n')
    load_handoff(output, source)  # The publisher consumes the exact exported inventory.
    return receipt


def load_handoff(directory: Path, source: str) -> tuple[dict[str, str], dict[str, str]]:
    if not __import__('re').fullmatch(r'[0-9a-f]{40}', source):
        raise ValueError('source_sha')
    directory = Path(directory)
    if directory.is_symlink() or not directory.is_dir():
        raise ValueError('handoff_directory')
    expected = set(SOURCE_PATHS) | {'receipt.json'}
    actual: set[str] = set()
    for path in directory.rglob('*'):
        if path.is_symlink() or (not path.is_file() and not path.is_dir()):
            raise ValueError('handoff_entry')
        if path.is_file():
            actual.add(path.relative_to(directory).as_posix())
    if actual != expected:
        raise ValueError('handoff_inventory')
    receipt_path = directory / 'receipt.json'
    if receipt_path.stat().st_size > 16384:
        raise ValueError('handoff_receipt')
    receipt = json.loads(receipt_path.read_text())
    metadata = handoff_metadata(source)
    if not isinstance(receipt, dict) or any(
            type(receipt.get(key)) is not type(value) or receipt[key] != value
            for key, value in metadata.items()):
        raise ValueError('handoff_receipt')
    expected_hashes = receipt.get('files_sha256')
    if (not isinstance(expected_hashes, dict) or len(expected_hashes) != len(SOURCE_PATHS)
            or set(expected_hashes) != set(SOURCE_PATHS)):
        raise ValueError('handoff_receipt')
    files: dict[str, str] = {}
    hashes: dict[str, str] = {}
    for source_path, target_path in zip(SOURCE_PATHS, TARGET_PATHS):
        data = (directory / source_path).read_bytes()
        if not data or len(data) > 524288 or _sha(data) != expected_hashes.get(source_path):
            raise ValueError('candidate_hash')
        files[target_path] = base64.b64encode(data).decode()
        hashes[target_path] = _sha(data)
    return files, hashes


PHP = r'''
error_reporting(0);ini_set('display_errors','0');ini_set('log_errors','0');ob_start();
$lock=null;$release=null;$installed=[];$result=['status'=>'blocked'];
function rdurable(string $path,string $bytes):void{
  $f=fopen($path,'x');if(!$f)throw new RuntimeException('checkpoint_exists');
  try{if(fwrite($f,$bytes)!==strlen($bytes)||!fflush($f))throw new RuntimeException('checkpoint_write');
    if(function_exists('fsync')&&!fsync($f))throw new RuntimeException('checkpoint_sync');}finally{fclose($f);}
  if(hash_file('sha256',$path)!==hash('sha256',$bytes))throw new RuntimeException('checkpoint_readback');
}
function rsafe_file(string $path):bool{
  clearstatcache(true,$path);return is_file($path)&&!is_link($path)&&filesize($path)<=1048576&&stat($path)['nlink']===1;
}
try{
  if(PHP_SAPI!=='cli')throw new RuntimeException('cli_only');
  $input=json_decode(stream_get_contents(STDIN),true,16,JSON_THROW_ON_ERROR);
  $allowed=__ALLOWED__;$support=__SUPPORT__;
  if(!is_array($input)||array_keys($input)!==['source','files','sha256']||!is_string($input['source'])
      ||!preg_match('/^[0-9a-f]{40}$/D',$input['source'])||!is_array($input['files'])||!is_array($input['sha256'])
      ||array_keys($input['files'])!==$allowed||array_keys($input['sha256'])!==$allowed)throw new RuntimeException('input');
  $root=realpath(getcwd());if(!$root||basename($root)!=='anytoour.ru')throw new RuntimeException('wrong_project');
  $target=$root.'/_preview/search3-anex-candidate';$private=dirname($root,2).'/.anytoour-andromeda';
  if(!is_dir($target)||is_link($target)||!is_dir($target.'/app/integrations')||is_link($target.'/app/integrations')
      ||!is_dir($private)||is_link($private))throw new RuntimeException('target');
  foreach($support as $path)if(!rsafe_file($target.'/'.$path))throw new RuntimeException('support_changed');
  $lockPath=$private.'/grouped-search-update.lock';if(is_link($lockPath))throw new RuntimeException('invalid_lock');
  $lock=fopen($lockPath,'c');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))throw new RuntimeException('lock_busy');
  $release=$private.'/runtime-surcharge-'.$input['source'];$manifest=$release.'/completed.json';
  $decoded=[];$hashes=[];
  foreach($allowed as $path){
    $encoded=$input['files'][$path]??null;$hash=$input['sha256'][$path]??null;
    if(!is_string($encoded)||!is_string($hash)||!preg_match('/^[0-9a-f]{64}$/D',$hash))throw new RuntimeException('candidate_hash');
    $bytes=base64_decode($encoded,true);if($bytes===false||strlen($bytes)>524288||hash('sha256',$bytes)!==$hash)throw new RuntimeException('candidate_hash');
    $decoded[$path]=$bytes;$hashes[$path]=$hash;
  }
  if(is_link($release))throw new RuntimeException('previous_outcome_unknown');
  if(file_exists($release)){
    $done=is_file($manifest)&&!is_link($manifest)?json_decode(file_get_contents($manifest),true,16,JSON_THROW_ON_ERROR):[];
    if(($done['status']??null)!=='published'||($done['source']??null)!==$input['source']||($done['sha256']??null)!==$hashes)
      throw new RuntimeException('previous_outcome_unknown');
    foreach($allowed as $path)if(!rsafe_file($target.'/'.$path)||hash_file('sha256',$target.'/'.$path)!==$hashes[$path])
      throw new RuntimeException('previous_outcome_unknown');
    $result=$done;$result['status']='already_published';
  }else{
    if(!mkdir($release,0700)||!mkdir($release.'/stage',0700)||!mkdir($release.'/backup',0700))throw new RuntimeException('reservation');
    rdurable($release.'/reservation.json',json_encode(['source'=>$input['source'],'sha256'=>$hashes,'support'=>$support],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
    $before=[];$modes=[];
    foreach($allowed as $i=>$path){
      $targetPath=$target.'/'.$path;if(is_link($targetPath))throw new RuntimeException('invalid_target');
      if(file_exists($targetPath)){
        if(!rsafe_file($targetPath))throw new RuntimeException('invalid_target');
        $old=file_get_contents($targetPath);$before[$path]=hash('sha256',$old);
        rdurable($release.'/backup/'.$i.'.php',$old);$mode=fileperms($targetPath)&0777;
        if(($mode&0022)!==0)throw new RuntimeException('unsafe_permissions');$modes[$path]=$mode;
      }else{$before[$path]=null;$modes[$path]=0644;}
      rdurable($release.'/stage/'.$i.'.php',$decoded[$path]);chmod($release.'/stage/'.$i.'.php',$modes[$path]);
    }
    try{
      foreach($allowed as $i=>$path){
        $targetPath=$target.'/'.$path;$current=file_exists($targetPath)&&rsafe_file($targetPath)?hash_file('sha256',$targetPath):null;
        if($current!==$before[$path])throw new RuntimeException('predecessor_changed');
        if(!rename($release.'/stage/'.$i.'.php',$targetPath))throw new RuntimeException('rename_failed');$installed[]=$i;
        if(!rsafe_file($targetPath)||hash_file('sha256',$targetPath)!==$hashes[$path])throw new RuntimeException('target_readback');
        if(function_exists('opcache_invalidate'))opcache_invalidate($targetPath,true);
      }
      foreach($support as $path)if(!rsafe_file($target.'/'.$path))throw new RuntimeException('support_changed');
    }catch(Throwable $publishError){
      foreach(array_reverse($installed) as $i){$path=$allowed[$i];$targetPath=$target.'/'.$path;$backup=$release.'/backup/'.$i.'.php';
        @unlink($targetPath);if(is_file($backup))@rename($backup,$targetPath);if(function_exists('opcache_invalidate'))@opcache_invalidate($targetPath,true);}
      throw $publishError;
    }
    $result=['status'=>'published','source'=>$input['source'],'sha256'=>$hashes,'before_sha256'=>$before,
      'changed_paths'=>$allowed,'target'=>'/_preview/search3-anex-candidate/','supplier_calls'=>0,'database_writes'=>0,'booking_calls'=>0,
      'search_surcharge_source_installed'=>true,'live_surcharge_capture_enabled'=>false];
    rdurable($manifest,json_encode($result,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
  }
}catch(Throwable $e){$result=['status'=>'blocked','reason'=>$e->getMessage(),'installed_count'=>count($installed),
  'supplier_calls'=>0,'database_writes'=>0,'booking_calls'=>0];}
if($lock){flock($lock,LOCK_UN);fclose($lock);}while(ob_get_level())ob_end_clean();echo json_encode($result,JSON_UNESCAPED_SLASHES);
'''


def remote_source() -> str:
    return (PHP.replace('__ALLOWED__', json.dumps(TARGET_PATHS, separators=(',', ':')))
               .replace('__SUPPORT__', json.dumps(SUPPORT_PATHS, separators=(',', ':'))))


def main() -> None:
    if len(sys.argv) != 3:
        raise SystemExit('usage: andromeda_runtime_source_publish.py CHECKED_HANDOFF_DIRECTORY PINNED_HELPER_REPO')
    source = os.environ.get('RUNTIME_SOURCE_SHA', '')
    files, hashes = load_handoff(Path(sys.argv[1]), source)
    sys.path.insert(0, str(Path(sys.argv[2]).resolve() / 'scripts' / 'diagnostics'))
    from anex_search3_owner_decisions import ssh_php
    out = Path(os.environ['RUNNER_TEMP']) / 'andromeda-runtime-source-publication'
    out.mkdir(mode=0o700)
    reservation = out / 'reservation.json'
    reservation.write_text(json.dumps({'source': source, 'sha256': hashes}, sort_keys=True))
    try:
        result = ssh_php(remote_source(), {'source': source, 'files': files, 'sha256': hashes}, maximum_bytes=SSH_RESPONSE_LIMIT)
    except Exception:
        (out / 'result.json').write_text(json.dumps({'status':'unknown','source':source,'reason':'ssh_outcome_unknown'}))
        raise SystemExit('publication unconfirmed; inspect checkpoint, do not replay')
    (out / 'result.json').write_text(json.dumps(result, sort_keys=True))
    if (not isinstance(result, dict) or result.get('status') not in ('published','already_published')
            or result.get('source') != source or result.get('sha256') != hashes
            or result.get('supplier_calls') != 0 or result.get('database_writes') != 0
            or result.get('booking_calls') != 0 or result.get('search_surcharge_source_installed') is not True
            or result.get('live_surcharge_capture_enabled') is not False):
        raise SystemExit('publication unconfirmed; inspect checkpoint, do not replay')
    print(json.dumps({'status':result['status'],'source':source,'files':len(files),
        'supplier_calls':0,'database_writes':0,'booking_calls':0}, sort_keys=True))


if __name__ == '__main__':
    main()
