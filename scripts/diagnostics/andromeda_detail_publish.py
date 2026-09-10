"""Install the checked #1901 backend handoff using the existing preview SSH helper.

This replaces the completed #1873 operation, whose main control pins its old script.
No supplier action, package capture, DB access, public selection or new launcher.
"""
import base64
import hashlib
import json
import os
from pathlib import Path
import sys

SOURCE_SHA = 'fdd099e36fdc1a20a561f285d22d0acb00f90e36'
ARTIFACT_ID = 10159104848
ARTIFACT_ZIP_SHA256 = '269429a1e37c3e9dda8d3b706b28097a655b6914fe998c908e4b5af35491cc47'
DEPENDENCIES = {
    'runtime': '95fa11f5a1cb7e18c905c9760a1eae7f2c0ee7aa',
    'package': '7c3c5bb55c88e376d97c998d0ba77a46960584d6',
    'transport': '1f628e4b172ce9706eabe875784ce2b226175a0d',
}
# Dependency-first order. The existing selector is verified, not rewritten; API last.
FILES = {
    'app/integrations/andromeda-client.php': 'ef45ade3710b26488e13cca995ac730baf6f0a699f9075a71d09174fd345f135',
    'app/integrations/andromeda-transport.php': '457273021d5363a6b3e593ca5807110d0513b06ea2861a64a6719f86e837a470',
    'app/integrations/andromeda-package-capture.php': '1d64913636cfec93bcc38fd011c6a1960f370f35f7df503941efada771f5ab6f',
    'app/integrations/andromeda-saved-package-runtime.php': '52b2b7c394f99359997451ca162137527dc0189ec69fbd3671b670079cb5211e',
    'app/integrations/andromeda-selected-offer.php': '06f5f608cb310fe0e3eafc11987ae1f70fa829aae70866c6f87ea1eb603fbc01',
    'api-andromeda-search3-preview.php': '12cc0b0d4d9c0823eb78c6f78b3dfcbf6c692420bd9fa9209e620e860f437a28',
}
# Git blob IDs below are from the installed runtime's exact source, not guessed SHA256.
BEFORE = {
    'app/integrations/andromeda-client.php': ['git_blob', '182868b1ea66cf2c0af7c0afdea1a410cb1a663d'],
    'app/integrations/andromeda-transport.php': ['git_blob', '0b306268a55b0e573cf688ec1109de452885aceb'],
    'app/integrations/andromeda-package-capture.php': None,
    'app/integrations/andromeda-saved-package-runtime.php': None,
    'app/integrations/andromeda-selected-offer.php': ['sha256', FILES['app/integrations/andromeda-selected-offer.php']],
    'api-andromeda-search3-preview.php': ['sha256', 'b7d245af694c41c319c6bcf5b92448a1bc6e0c80e0e57664c4adb6a5388c4587'],
}
SUPPORT = {
    'app/integrations/andromeda-offer-store.php': ['git_blob', '513316e94d8b35965066102e6178694e71c00fad'],
    'app/integrations/andromeda-normalizer.php': ['git_blob', 'b4b4bcd7bbb18c98c918b617f3e0fa9713c4c462'],
    'app/integrations/andromeda-hotel-resolver.php': ['git_blob', 'd49d0eaf8d6abccefd84c0c3921edde94ef50a68'],
}


def load_handoff(directory):
    """Read only the previously checked seven-file source artifact, before SSH."""
    directory = Path(directory)
    expected = {p if p.startswith('app/') else 'v2/' + p: h for p, h in FILES.items()}
    names = set(expected) | {'receipt.json'}
    if directory.is_symlink() or not directory.is_dir():
        raise ValueError('invalid_handoff')
    actual = set()
    for path in directory.rglob('*'):
        if path.is_symlink() or (not path.is_file() and not path.is_dir()):
            raise ValueError('invalid_handoff_entry')
        if path.is_file():
            actual.add(path.relative_to(directory).as_posix())
    if actual != names or (directory / 'receipt.json').stat().st_size > 8192:
        raise ValueError('handoff_inventory')
    receipt = json.loads((directory / 'receipt.json').read_text())
    if (receipt.get('source') != SOURCE_SHA or receipt.get('dependencies') != DEPENDENCIES
            or receipt.get('files_sha256') != expected
            or receipt.get('scope') != 'private-source-handoff-only'
            or any(receipt.get(k) is not False for k in ('published', 'package_captured_live', 'quote_verified'))
            or receipt.get('supplier_calls') != 0):
        raise ValueError('handoff_receipt')
    files = {}
    for path, digest in FILES.items():
        source = directory / (path if path.startswith('app/') else 'v2/' + path)
        if source.stat().st_size > 250000:
            raise ValueError('candidate_size')
        content = source.read_bytes()
        if hashlib.sha256(content).hexdigest() != digest:
            raise ValueError('candidate_hash')
        files[path] = base64.b64encode(content).decode()
    return files


PHP_TEMPLATE = r'''
error_reporting(0);ob_start();$lock=null;$reserved=false;umask(0077);
function durable($path,$bytes){
    $f=fopen($path,'x');if(!$f)throw new RuntimeException('checkpoint_exists');
    try{if(fwrite($f,$bytes)!==strlen($bytes)||!fflush($f))throw new RuntimeException('checkpoint_write');
        if(function_exists('fsync')&&!fsync($f))throw new RuntimeException('checkpoint_sync');
    }finally{fclose($f);}
    if(hash_file('sha256',$path)!==hash('sha256',$bytes))throw new RuntimeException('checkpoint_readback');
}
function matches($path,$expected){
    clearstatcache(true,$path);
    if(is_link($path))return false;
    if($expected===null)return !file_exists($path);
    if(!is_file($path)||filesize($path)>1000000||stat($path)['nlink']!==1)return false;
    $bytes=file_get_contents($path);
    return ($expected[0]==='git_blob'?sha1('blob '.strlen($bytes)."\0".$bytes):hash('sha256',$bytes))===$expected[1];
}
try{
    if(PHP_SAPI!=='cli')throw new RuntimeException('cli_only');
    $root=realpath(getcwd());if(!$root||basename($root)!=='anytoour.ru')throw new RuntimeException('wrong_project');
    $target=$root.'/_preview/search3-anex-candidate';$private=dirname($root,2).'/.anytoour-andromeda';
    foreach([$root.'/_preview',$target,$target.'/app',$target.'/app/integrations',$private] as $dir)
        if(!is_dir($dir)||is_link($dir))throw new RuntimeException('invalid_directory');
    $spec=json_decode(base64_decode('__SPEC__'),true,16,JSON_THROW_ON_ERROR);
    $hashes=$spec['after'];$before=$spec['before'];$support=$spec['support'];$sha=$spec['source'];
    $wire=file_get_contents('php://stdin',false,null,0,1000001);
    if(strlen($wire)>1000000)throw new RuntimeException('candidate_size');
    $input=json_decode($wire,true,8,JSON_THROW_ON_ERROR);
    if(array_keys($input['files']??[])!==array_keys($hashes))throw new RuntimeException('candidate_paths');
    $files=[];foreach($hashes as $path=>$hash){
        if(!is_string($input['files'][$path]))throw new RuntimeException('candidate_hash');
        $bytes=base64_decode($input['files'][$path],true);
        if($bytes===false||hash('sha256',$bytes)!==$hash)throw new RuntimeException('candidate_hash');
        if(is_link($target.'/'.$path))throw new RuntimeException('invalid_target');$files[$path]=$bytes;
    }
    $lockPath=$private.'/grouped-search-update.lock';
    if(is_link($lockPath))throw new RuntimeException('invalid_lock');
    $lock=fopen($lockPath,'c');
    if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))throw new RuntimeException('lock_busy');
    foreach($support as $path=>$hash)if(!matches($target.'/'.$path,$hash))throw new RuntimeException('support_changed');
    $release=$private.'/package-install-1901-'.$sha;$manifest=$release.'/completed.json';
    if(is_link($release))throw new RuntimeException('previous_outcome_unknown');
    if(file_exists($release)){
        $done=is_file($manifest)&&!is_link($manifest)?json_decode(file_get_contents($manifest),true,16):[];
        if(($done['status']??null)!=='published'||($done['source']??null)!==$sha
            ||($done['sha256']??null)!==$hashes)throw new RuntimeException('previous_outcome_unknown');
        foreach($hashes as $path=>$hash)if(!matches($target.'/'.$path,['sha256',$hash]))throw new RuntimeException('previous_outcome_unknown');
        $result=['status'=>'already_published','source'=>$sha,'sha256'=>$hashes,
            'target'=>'/_preview/search3-anex-candidate/','supplier_calls'=>0,'database_writes'=>0,
            'package_captured'=>false,'selection_enabled'=>false,'quote_verified'=>false];
    }else{
        foreach($before as $path=>$hash)if(!matches($target.'/'.$path,$hash))throw new RuntimeException('predecessor_changed');
        if(!mkdir($release,0700))throw new RuntimeException('reservation');$reserved=true;
        durable($release.'/reservation.json',json_encode(['source'=>$sha,'before'=>$before,'after'=>$hashes,'support'=>$support]));
        $backups=[];$changed=[];
        foreach($files as $path=>$bytes){
            if($before[$path]===['sha256',$hashes[$path]])continue;
            if($before[$path]!==null){
                $old=file_get_contents($target.'/'.$path);
                $backup=$release.'/previous-'.basename($path);durable($backup,$old);
                if(!matches($backup,$before[$path]))throw new RuntimeException('backup_changed');
                $backups[$path]=hash('sha256',$old);
            }else{$backups[$path]=null;}
            $mode=$before[$path]===null?0644:fileperms($target.'/'.$path)&0777;
            if(($mode&0022)!==0)throw new RuntimeException('unsafe_permissions');
            $stage=$release.'/next-'.basename($path);durable($stage,$bytes);
            if(!chmod($stage,$mode))throw new RuntimeException('stage_permissions');$changed[]=$path;
        }
        // Default-off client/transport remain backwards compatible. The public API switches last.
        foreach($before as $path=>$hash)if(!matches($target.'/'.$path,$hash))throw new RuntimeException('predecessor_changed');
        foreach($changed as $path){
            if(!matches($target.'/'.$path,$before[$path]))throw new RuntimeException('predecessor_changed');
            if(!rename($release.'/next-'.basename($path),$target.'/'.$path))throw new RuntimeException('publication_not_confirmed');
            if(!matches($target.'/'.$path,['sha256',$hashes[$path]]))throw new RuntimeException('publication_readback');
            if(function_exists('opcache_invalidate'))opcache_invalidate($target.'/'.$path,true);
        }
        foreach($hashes as $path=>$hash)if(!matches($target.'/'.$path,['sha256',$hash]))throw new RuntimeException('publication_readback');
        foreach($support as $path=>$hash)if(!matches($target.'/'.$path,$hash))throw new RuntimeException('support_changed');
        $result=['status'=>'published','source'=>$sha,'sha256'=>$hashes,'backup_sha256'=>$backups,
            'changed_paths'=>$changed,'target'=>'/_preview/search3-anex-candidate/',
            'supplier_calls'=>0,'database_writes'=>0,'package_captured'=>false,'selection_enabled'=>false,'quote_verified'=>false];
        durable($manifest,json_encode($result));
    }
}catch(Throwable $e){$result=['status'=>'blocked','reason'=>$e->getMessage(),'reservation_created'=>$reserved];}
if($lock){flock($lock,LOCK_UN);fclose($lock);}while(ob_get_level())ob_end_clean();echo json_encode($result);
'''


def remote_source():
    spec = {'source': SOURCE_SHA, 'after': FILES, 'before': BEFORE, 'support': SUPPORT}
    return PHP_TEMPLATE.replace('__SPEC__', base64.b64encode(json.dumps(spec).encode()).decode())


def main():
    if len(sys.argv) != 3:
        raise SystemExit('usage: publisher CHECKED_HANDOFF_DIRECTORY PINNED_HELPER_REPO')
    files = load_handoff(Path(sys.argv[1]))  # Includes full hashes/receipt before helper/SSH.
    sys.path.insert(0, str(Path(sys.argv[2]) / 'scripts/diagnostics'))
    from anex_search3_owner_decisions import ssh_php
    directory = Path(os.environ['RUNNER_TEMP']) / 'andromeda-detail-publication'
    directory.mkdir(mode=0o700)
    def save(name, data):
        with (directory / name).open('x') as handle:
            json.dump(data, handle); handle.flush(); os.fsync(handle.fileno())
        if json.loads((directory / name).read_text()) != data:
            raise SystemExit('checkpoint readback failed; do not replay')
    save('reservation.json', {'source': SOURCE_SHA, 'artifact': ARTIFACT_ID, 'before': BEFORE, 'after': FILES})
    try:
        result = ssh_php(remote_source(), {'files': files})
    except Exception:
        save('result.json', {'status': 'unknown', 'source': SOURCE_SHA, 'reason': 'ssh_outcome_unknown'})
        raise SystemExit('publication unconfirmed; inspect checkpoint, do not replay')
    save('result.json', result)
    print(json.dumps(result))
    if (not isinstance(result, dict) or result.get('status') not in ('published', 'already_published')
            or result.get('source') != SOURCE_SHA or result.get('sha256') != FILES):
        raise SystemExit('publication unconfirmed; inspect checkpoint, do not replay')


if __name__ == '__main__':
    main()
