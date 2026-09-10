"""Publish the checked #1863 API only, through the existing AnyTour SSH helper."""
import base64
import hashlib
import json
import os
from pathlib import Path
import sys

SOURCE_SHA = 'ac9f16063deadb3b7b2e4fb665c7b51668fe4c6a'
BEFORE = '07d8d9019833c36add09719014ebaa970e56cb85f292296d744f56ee2f36119d'
AFTER = '53ce2e6f14b0f4f88884f6b2a07d9a59bc5fa3d769e95a1fb42e8804b786ad29'
SOURCE = r'''
error_reporting(0);ob_start();$lock=null;$reserved=false;$manifest=null;
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
    $target=$root.'/_preview/search3-anex-candidate';
    $private=dirname($root,2).'/.anytoour-andromeda';
    foreach([$root.'/_preview',$target,$private] as $dir)if(!is_dir($dir)||is_link($dir))throw new RuntimeException('invalid_directory');
    $file=$target.'/api-andromeda-search3-preview.php';
    if(!is_file($file)||is_link($file))throw new RuntimeException('invalid_target');
    $input=json_decode(file_get_contents('php://stdin'),true,8,JSON_THROW_ON_ERROR);
    $bytes=base64_decode($input['content']??'',true);
    $before='07d8d9019833c36add09719014ebaa970e56cb85f292296d744f56ee2f36119d';
    $after='53ce2e6f14b0f4f88884f6b2a07d9a59bc5fa3d769e95a1fb42e8804b786ad29';
    $sha='ac9f16063deadb3b7b2e4fb665c7b51668fe4c6a';
    if($bytes===false||hash('sha256',$bytes)!==$after)throw new RuntimeException('candidate_hash');
    $lock=fopen($private.'/grouped-search-update.lock','c');
    if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('lock');
    $release=$private.'/projection-'.$sha;$manifest=$release.'/completed.json';
    if(file_exists($release)){
        $done=is_file($manifest)&&!is_link($manifest)?json_decode(file_get_contents($manifest),true):[];
        if(($done['status']??null)!=='published'||($done['sha256']??null)!==$after||hash_file('sha256',$file)!==$after)
            throw new RuntimeException('previous_outcome_unknown');
        $result=$done;$result['status']='already_published';
    }else{
        if(hash_file('sha256',$file)!==$before)throw new RuntimeException('predecessor_changed');
        if(!mkdir($release,0700))throw new RuntimeException('reservation');
        $reserved=true;
        durable($release.'/reservation.json',json_encode(['source'=>$sha,'before'=>$before,'after'=>$after]));
        durable($release.'/previous.php',file_get_contents($file));
        $stage=$release.'/next.php';durable($stage,$bytes);
        if(!chmod($stage,fileperms($file)&0777)||hash_file('sha256',$file)!==$before||!rename($stage,$file))
            throw new RuntimeException('publication_not_confirmed');
        if(hash_file('sha256',$file)!==$after)throw new RuntimeException('publication_readback');
        if(function_exists('opcache_invalidate'))opcache_invalidate($file,true);
        $result=['status'=>'published','source'=>$sha,'sha256'=>$after,'backup_sha256'=>hash_file('sha256',$release.'/previous.php'),
            'target'=>'/_preview/search3-anex-candidate/api-andromeda-search3-preview.php','supplier_calls'=>0,'database_writes'=>0];
        durable($manifest,json_encode($result));
    }
}catch(Throwable $e){$result=['status'=>'blocked','reason'=>$e->getMessage(),'reservation_created'=>$reserved];}
if($lock){flock($lock,LOCK_UN);fclose($lock);}while(ob_get_level())ob_end_clean();echo json_encode($result);
'''


def main():
    if len(sys.argv) != 3:
        raise SystemExit('usage: publisher CHECKED_API_FILE PINNED_HELPER_REPO')
    content = Path(sys.argv[1]).read_bytes()
    if hashlib.sha256(content).hexdigest() != AFTER:
        raise SystemExit('candidate hash mismatch; no SSH call')
    sys.path.insert(0, str(Path(sys.argv[2]) / 'scripts/diagnostics'))
    from anex_search3_owner_decisions import ssh_php
    directory = Path(os.environ['RUNNER_TEMP']) / 'andromeda-projection-publication'
    directory.mkdir(mode=0o700)
    reservation = {'status': 'reserved', 'source': SOURCE_SHA, 'before': BEFORE, 'after': AFTER}
    with (directory / 'reservation.json').open('x') as handle:
        json.dump(reservation, handle); handle.flush(); os.fsync(handle.fileno())
    if json.loads((directory / 'reservation.json').read_text()) != reservation:
        raise SystemExit('reservation readback failed')
    result = ssh_php(SOURCE, {'content': base64.b64encode(content).decode()})
    with (directory / 'result.json').open('x') as handle:
        json.dump(result, handle); handle.flush(); os.fsync(handle.fileno())
    if json.loads((directory / 'result.json').read_text()) != result:
        raise SystemExit('result readback failed; do not replay')
    print(json.dumps(result))
    if result.get('status') not in ('published', 'already_published') or result.get('sha256') != AFTER:
        raise SystemExit('publication unconfirmed; inspect checkpoint, do not replay')


if __name__ == '__main__':
    main()
