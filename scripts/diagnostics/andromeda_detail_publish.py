"""Install only the checked #1873 module and API through the existing SSH helper."""
import base64
import hashlib
import json
import os
from pathlib import Path
import sys

SOURCE_SHA = 'eaf16ed769185cc1cb3a4a198ae6c87ae56b2bf7'
BEFORE = '53ce2e6f14b0f4f88884f6b2a07d9a59bc5fa3d769e95a1fb42e8804b786ad29'
FILES = {
    'app/integrations/andromeda-selected-offer.php': '06f5f608cb310fe0e3eafc11987ae1f70fa829aae70866c6f87ea1eb603fbc01',
    'api-andromeda-search3-preview.php': 'b7d245af694c41c319c6bcf5b92448a1bc6e0c80e0e57664c4adb6a5388c4587',
}
SOURCE = r'''
error_reporting(0);ob_start();$lock=null;$reserved=false;
function durable($path,$bytes){
    $f=fopen($path,'x');if(!$f)throw new RuntimeException('checkpoint_exists');
    try{if(fwrite($f,$bytes)!==strlen($bytes)||!fflush($f))throw new RuntimeException('checkpoint_write');
        if(function_exists('fsync')&&!fsync($f))throw new RuntimeException('checkpoint_sync');
    }finally{fclose($f);}
    if(hash_file('sha256',$path)!==hash('sha256',$bytes))throw new RuntimeException('checkpoint_readback');
}
try{
    if(PHP_SAPI!=='cli')throw new RuntimeException('cli_only');
    $root=realpath(getcwd());if(!$root||basename($root)!=='anytoour.ru')throw new RuntimeException('wrong_project');
    $target=$root.'/_preview/search3-anex-candidate';$private=dirname($root,2).'/.anytoour-andromeda';
    foreach([$root.'/_preview',$target,$target.'/app',$target.'/app/integrations',$private] as $dir)
        if(!is_dir($dir)||is_link($dir))throw new RuntimeException('invalid_directory');
    $api='api-andromeda-search3-preview.php';$module='app/integrations/andromeda-selected-offer.php';
    $before='53ce2e6f14b0f4f88884f6b2a07d9a59bc5fa3d769e95a1fb42e8804b786ad29';
    $hashes=[$module=>'06f5f608cb310fe0e3eafc11987ae1f70fa829aae70866c6f87ea1eb603fbc01',
        $api=>'b7d245af694c41c319c6bcf5b92448a1bc6e0c80e0e57664c4adb6a5388c4587'];
    $sha='eaf16ed769185cc1cb3a4a198ae6c87ae56b2bf7';
    $input=json_decode(file_get_contents('php://stdin'),true,8,JSON_THROW_ON_ERROR);
    if(array_keys($input['files']??[])!==array_keys($hashes))throw new RuntimeException('candidate_paths');
    $files=[];foreach($hashes as $path=>$hash){
        $bytes=base64_decode($input['files'][$path],true);
        if($bytes===false||hash('sha256',$bytes)!==$hash)throw new RuntimeException('candidate_hash');
        if(is_link($target.'/'.$path))throw new RuntimeException('invalid_target');$files[$path]=$bytes;
    }
    $lock=fopen($private.'/grouped-search-update.lock','c');
    if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('lock');
    $release=$private.'/detail-selection-'.$sha;$manifest=$release.'/completed.json';
    if(file_exists($release)){
        $done=is_file($manifest)&&!is_link($manifest)?json_decode(file_get_contents($manifest),true):[];
        if(($done['status']??null)!=='published'||($done['sha256']??null)!==$hashes)throw new RuntimeException('previous_outcome_unknown');
        foreach($hashes as $path=>$hash)if(!is_file($target.'/'.$path)||hash_file('sha256',$target.'/'.$path)!==$hash)throw new RuntimeException('previous_outcome_unknown');
        $result=$done;$result['status']='already_published';
    }else{
        if(!is_file($target.'/'.$api)||hash_file('sha256',$target.'/'.$api)!==$before)throw new RuntimeException('predecessor_changed');
        if(file_exists($target.'/'.$module))throw new RuntimeException('module_already_exists');
        if(!mkdir($release,0700))throw new RuntimeException('reservation');$reserved=true;
        durable($release.'/reservation.json',json_encode(['source'=>$sha,'before'=>[$api=>$before,$module=>null],'after'=>$hashes]));
        durable($release.'/previous-api.php',file_get_contents($target.'/'.$api));
        $modes=[$module=>0644,$api=>fileperms($target.'/'.$api)&0777];
        foreach($files as $path=>$bytes){$stage=$release.'/next-'.basename($path);durable($stage,$bytes);
            if(!chmod($stage,$modes[$path]))throw new RuntimeException('stage_permissions');}
        // The old API never loads the new module. Install the dependency first; switch the entry point last.
        if(file_exists($target.'/'.$module)||is_link($target.'/'.$module)||hash_file('sha256',$target.'/'.$api)!==$before)throw new RuntimeException('predecessor_changed');
        foreach($files as $path=>$bytes){
            if(!rename($release.'/next-'.basename($path),$target.'/'.$path))throw new RuntimeException('publication_not_confirmed');
            if(hash_file('sha256',$target.'/'.$path)!==$hashes[$path])throw new RuntimeException('publication_readback');
            if(function_exists('opcache_invalidate'))opcache_invalidate($target.'/'.$path,true);
        }
        $result=['status'=>'published','source'=>$sha,'sha256'=>$hashes,'backup_sha256'=>hash_file('sha256',$release.'/previous-api.php'),
            'target'=>'/_preview/search3-anex-candidate/','supplier_calls'=>0,'database_writes'=>0];
        durable($manifest,json_encode($result));
    }
}catch(Throwable $e){$result=['status'=>'blocked','reason'=>$e->getMessage(),'reservation_created'=>$reserved];}
if($lock){flock($lock,LOCK_UN);fclose($lock);}while(ob_get_level())ob_end_clean();echo json_encode($result);
'''


def main():
    if len(sys.argv) != 3:
        raise SystemExit('usage: publisher PINNED_CANDIDATE_REPO PINNED_HELPER_REPO')
    candidate = Path(sys.argv[1])
    files = {}
    for path, expected in FILES.items():
        content = (candidate / (path if path.startswith('app/') else 'v2/' + path)).read_bytes()
        if hashlib.sha256(content).hexdigest() != expected:
            raise SystemExit('candidate hash mismatch; no SSH call')
        files[path] = base64.b64encode(content).decode()
    sys.path.insert(0, str(Path(sys.argv[2]) / 'scripts/diagnostics'))
    from anex_search3_owner_decisions import ssh_php
    directory = Path(os.environ['RUNNER_TEMP']) / 'andromeda-detail-publication'
    directory.mkdir(mode=0o700)
    def save(name, data):
        with (directory / name).open('x') as handle:
            json.dump(data, handle); handle.flush(); os.fsync(handle.fileno())
        if json.loads((directory / name).read_text()) != data:
            raise SystemExit('checkpoint readback failed; do not replay')
    save('reservation.json', {'source': SOURCE_SHA, 'before': BEFORE, 'after': FILES})
    result = ssh_php(SOURCE, {'files': files})
    save('result.json', result)
    print(json.dumps(result))
    if result.get('status') not in ('published', 'already_published') or result.get('sha256') != FILES:
        raise SystemExit('publication unconfirmed; inspect checkpoint, do not replay')


if __name__ == '__main__':
    main()
