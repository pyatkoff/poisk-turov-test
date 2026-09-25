"""Restore the previously approved exact quote route, with pinned rollback."""
import http.client
import json
import os
from pathlib import Path
import re
import subprocess
import sys

REMOTE = r'''
import fcntl,hashlib,json,os,sys
from pathlib import Path
BEFORE='cd39e6031ceb82ed980abb0b34506604fe5b9611e3d37cadcff04ecb060c6a76'
PINS={'api-anex-search3-preview.php':'75a4168920ba4ea4ca043516d5979a05acf2be2a2757096121ccb6180c49e74e',
 'api-andromeda-search3-preview.php':'47d41972b275913a48b8ac95101f928deb9d504cf1354ea86019f3463f076c6b',
 'api-andromeda-quote-preview.php':'54bbab700a6507cd116744140b3bf8f4d1e05b9d549c4479ab98687d86eb6118',
 'anex-preview-manifest.json':'d2c8cb504b2d02f2dd9c0504ce5e1a05a9c4321d5438f7425990cd038577ec18'}
PRIOR_OP='search3-quote-http-restore-20260925-v1'
OP='search3-quote-http-restore-20260925-v2'
RULE=b'\n# Restore the existing selected-tour quote route (#1717).\n<FilesMatch "^api-andromeda-quote-preview\\.php$">\n  Require expr "%{REQUEST_URI} == \'/_preview/search3-anex-candidate/api-andromeda-quote-preview.php\'"\n</FilesMatch>\n'
def sha(b):return hashlib.sha256(b).hexdigest()
def read(path,limit=3000000):
    assert path.resolve()==path and path.is_file() and not path.is_symlink(),'path_invalid'
    s=path.stat();assert s.st_nlink==1 and s.st_size<=limit,'file_invalid'
    return path.read_bytes()
def write(path,body,mode=0o600):
    with path.open('xb') as f:
        os.fchmod(f.fileno(),mode);f.write(body);f.flush();os.fsync(f.fileno())
    assert read(path)==body,'write_readback'
def witnesses(root):
    names=['index.php','api-v2.php','v2/index.php','_preview/search3-local-candidate/prototype-search/data.js','_preview/search3-next-candidate/visual-search/app.js']
    return {name:sha(read(root/name)) for name in names if (root/name).is_file()}
def repair(root,private,action):
    assert action in ('apply','rollback'),'action_invalid'
    target=root/'_preview/search3-anex-candidate'
    assert root.name=='anytoour.ru' and root.resolve()==root and target.resolve()==target and private.resolve()==private,'project_invalid'
    assert private.is_dir() and target.is_dir(),'target_missing'
    assert not (root/'.htaccess').exists() and not (root/'_preview/.htaccess').exists(),'ancestor_policy_changed'
    lockpath=private/'grouped-search-update.lock';read(lockpath,65536)
    with lockpath.open('rb') as lock:
        fcntl.flock(lock,fcntl.LOCK_EX|fcntl.LOCK_NB)
        for name,digest in PINS.items():assert sha(read(target/name))==digest,'runtime_changed'
        access=target/'.htaccess';current=read(access,65536)
        assert access.stat().st_mode&0o777==0o644,'mode_changed'
        operation=private/OP
        if action=='apply':
            assert sha(current)==BEFORE and b'api-andromeda-quote-preview' not in current,'predecessor_changed'
            prior=private/PRIOR_OP
            rollback=json.loads(read(prior/'rollback-result.json',65536))
            assert rollback=={'status':'rolled_back','restored_sha256':BEFORE},'prior_not_rolled_back'
            assert read(prior/'before.htaccess',65536)==current,'prior_before_changed'
            applied=json.loads(read(prior/'applied.json',65536))
            assert applied['status']=='applied' and applied['before_sha256']==BEFORE and applied['after_sha256']==sha(current+RULE),'prior_result_changed'
            assert not operation.exists() and not operation.is_symlink(),'no_replay'
            before_witness=witnesses(root)
            candidate=current+RULE
            operation.mkdir(mode=0o700)
            write(operation/'reservation.json',json.dumps({'before':BEFORE,'after':sha(candidate),'witnesses':before_witness}).encode())
            write(operation/'before.htaccess',current)
            write(operation/'candidate.htaccess',candidate,0o644)
            assert read(access,65536)==current,'predecessor_changed'
            os.replace(operation/'candidate.htaccess',access)
            assert read(access,65536)==candidate,'target_readback'
            assert witnesses(root)==before_witness,'witness_changed'
            result={'status':'applied','before_sha256':BEFORE,'after_sha256':sha(candidate),'original_bytes_preserved':True,'witnesses_unchanged':True}
            write(operation/'applied.json',json.dumps(result).encode())
        else:
            assert operation.resolve()==operation and operation.is_dir(),'operation_missing'
            assert not (operation/'rollback.json').exists(),'rollback_no_replay'
            before=read(operation/'before.htaccess',65536)
            assert sha(before)==BEFORE and current==before+RULE,'rollback_drift'
            reservation=json.loads(read(operation/'reservation.json'))
            assert witnesses(root)==reservation['witnesses'],'witness_changed'
            write(operation/'rollback.json',b'{"status":"reserved"}')
            write(operation/'restore.htaccess',before,0o644)
            os.replace(operation/'restore.htaccess',access)
            assert read(access,65536)==before,'rollback_readback'
            result={'status':'rolled_back','restored_sha256':BEFORE}
            write(operation/'rollback-result.json',json.dumps(result).encode())
        return {**result,'operation':OP,'supplier_calls':0,'database_writes':0,'lead_calls':0,'php_files_changed':0}
if __name__=='__main__':
    assert len(sys.argv)==2
    print(json.dumps(repair(Path.home()/'www/anytoour.ru',Path.home()/'.anytoour-andromeda',sys.argv[1])))
'''


def command(args,data=None):
    p=subprocess.run(args,input=data,capture_output=True,timeout=60)
    if p.returncode:raise RuntimeError('checked command failed')
    return p.stdout


def http_probe(path,method='GET',headers=None):
    c=http.client.HTTPSConnection('anytoour.ru',timeout=20)
    try:
        c.request(method,'/_preview/search3-anex-candidate/'+path,body=None if method=='GET' else b'',headers=headers or {})
        r=c.getresponse();b=r.read(4097);assert len(b)<=4096
        try:error=json.loads(b).get('error')
        except (ValueError,AttributeError):error=None
        return {'path':path,'method':method,'status':r.status,'error':error}
    finally:c.close()


def main():
    env=os.environ;assert env['GITHUB_RUN_ATTEMPT']=='1'
    assert env['GITHUB_REPOSITORY']=='pyatkoff/poisk-turov-test'
    assert env['GITHUB_ACTOR']==env['GITHUB_TRIGGERING_ACTOR']=='pyatkoff'
    event=json.loads(Path(env['GITHUB_EVENT_PATH']).read_text());pr=event['pull_request']
    assert event['sender']['id']==226193297 and pr['head']['repo']['full_name']==env['GITHUB_REPOSITORY']
    assert pr['head']['ref']=='fix/search3-quote-http-restore-20260925'
    host=env['PREVIEW_HOST'];user=env['PREVIEW_USER']
    assert re.fullmatch(r'[A-Za-z0-9][A-Za-z0-9.-]*',host) and re.fullmatch(r'[A-Za-z0-9_][A-Za-z0-9_.-]{0,63}',user)
    out=Path(env['RUNNER_TEMP'])/'search3-quote-http-restore';out.mkdir(mode=0o700)
    key=out/'key';known=out/'known_hosts';raw=env['PREVIEW_KEY'].replace('\r','')
    if 'PRIVATE KEY' not in raw:
        import base64
        raw=base64.b64decode(raw,validate=True).decode()
    key.write_text(raw.rstrip()+'\n');key.chmod(0o600)
    result={'status':'unconfirmed','source_sha':pr['head']['sha'],'supplier_calls':0,'lead_calls':0}
    def save():(out/'result.json').write_text(json.dumps(result,indent=2)+'\n')
    save()
    try:
        command(['ssh-keygen','-y','-f',str(key)])
        known.write_bytes(command(['ssh-keyscan','-T','15','-t','ed25519',host]));known.chmod(0o600)
        args=['ssh','-i',str(key),'-o','StrictHostKeyChecking=yes','-o','UserKnownHostsFile='+str(known),'-o','GlobalKnownHostsFile=/dev/null','-o','BatchMode=yes','-o','ConnectTimeout=15',user+'@'+host]
        def remote(action):return json.loads(command(args+['python3 - '+action],REMOTE.encode()))
        result['activation']=remote('apply');save()
        assert result['activation']['status']=='applied'
        try:
            result['checks']=[]
            checks=[('api-andromeda-quote-preview.php','GET',{},405,'method_not_allowed'),
                ('api-andromeda-quote-preview.php','POST',{},403,'forbidden'),
                ('api-andromeda-quote-preview.php','POST',{'X-Requested-With':'AnyTourSearch3','Content-Type':'text/plain','Origin':'https://anytoour.ru'},400,'invalid_request'),
                ('api-andromeda-search3-preview.php','GET',{},405,'method_not_allowed'),
                ('api-anex-search3-preview.php','GET',{},405,'method_not_allowed'),
                ('app/integrations/andromeda-selected-quote.php','GET',{},403,None),
                ('.andromeda-private.php','GET',{},403,None)]
            for path,method,headers,status,error in checks:
                row=http_probe(path,method,headers);result['checks'].append(row);save()
                assert row['status']==status and row['error']==error,'HTTP boundary failed'
            result['status']='verified';save()
        except Exception:
            result['rollback']=remote('rollback');result['status']='rolled_back';save();raise
    finally:
        key.unlink(missing_ok=True);known.unlink(missing_ok=True);save()
    print(json.dumps(result))


if __name__=='__main__':main()
