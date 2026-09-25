"""Fixed-target read-only diagnosis of the existing Search3 provider boundary."""
import hashlib
import http.client
import json
import os
from pathlib import Path
import re
import subprocess
import sys

REMOTE = r'''
import hashlib,json
from pathlib import Path
root=Path.home()/'www/anytoour.ru'
target=root/'_preview/search3-anex-candidate'
assert root.resolve()==root and target.resolve()==target and target.is_dir()
def read(path,limit):
    assert path.resolve()==path and path.is_file() and not path.is_symlink()
    assert path.stat().st_nlink==1 and path.stat().st_size<=limit
    return path.read_bytes()
def sha(value):return hashlib.sha256(value).hexdigest()
names=['api-anex-search3-preview.php','api-andromeda-search3-preview.php','api-andromeda-quote-preview.php']
access=read(target/'.htaccess',65536)
result={'status':'inspected_read_only','target':'/_preview/search3-anex-candidate/',
 'target_writes':0,'supplier_calls':0,'database_reads':0,'database_writes':0,'lead_calls':0,
 'htaccess_sha256':sha(access),'htaccess_bytes':len(access),
 'quote_name_present':b'api-andromeda-quote-preview' in access,
 'endpoint_sha256':{name:sha(read(target/name,524288)) for name in names},
 'ancestor_policy_present':[(p.relative_to(root).as_posix()) for p in [root/'.htaccess',root/'_preview/.htaccess'] if p.exists()]}
manifest=read(target/'anex-preview-manifest.json',3000000)
value=json.loads(manifest)
result['manifest_sha256']=sha(manifest)
result['manifest_source']=value.get('source_sha')
print(json.dumps(result))
'''


def run(args, data=None):
    result=subprocess.run(args,input=data,capture_output=True,timeout=60)
    if result.returncode:raise RuntimeError('checked command failed')
    return result.stdout


def probe(name,method='GET'):
    connection=http.client.HTTPSConnection('anytoour.ru',timeout=20)
    try:
        headers={} if method=='GET' else {'X-Requested-With':'AnyTourSearch3','Content-Type':'text/plain','Origin':'https://anytoour.ru'}
        connection.request(method,'/_preview/search3-anex-candidate/'+name,body=None if method=='GET' else b'',headers=headers)
        response=connection.getresponse();body=response.read(4097)
        assert len(body)<=4096
        try:error=json.loads(body).get('error')
        except (ValueError,AttributeError):error=None
        return {'name':name,'method':method,'status':response.status,'json_error':error if error in ('method_not_allowed','invalid_request','forbidden','not_found') else None,'body_sha256':hashlib.sha256(body).hexdigest(),'content_type':response.getheader('Content-Type')}
    finally:connection.close()


def main():
    compile(REMOTE,'read-only-remote','exec')
    if '--check' in sys.argv:return
    assert os.environ['GITHUB_REPOSITORY']=='pyatkoff/poisk-turov-test'
    assert os.environ['GITHUB_RUN_ATTEMPT']=='1'
    assert os.environ['GITHUB_ACTOR']==os.environ['GITHUB_TRIGGERING_ACTOR']=='pyatkoff'
    event=json.loads(Path(os.environ['GITHUB_EVENT_PATH']).read_text())
    pr=event['pull_request']
    assert event['sender']['id']==226193297 and pr['head']['repo']['full_name']=='pyatkoff/poisk-turov-test'
    assert pr['head']['ref']=='ops/search3-provider-boundary-20260925'
    host=os.environ['PREVIEW_HOST'];user=os.environ['PREVIEW_USER']
    assert re.fullmatch(r'[A-Za-z0-9][A-Za-z0-9.-]*',host) and re.fullmatch(r'[A-Za-z0-9_][A-Za-z0-9_.-]{0,63}',user)
    out=Path(os.environ['RUNNER_TEMP'])/'search3-provider-boundary';out.mkdir(mode=0o700)
    key=out/'key';known=out/'known_hosts'
    raw=os.environ['PREVIEW_KEY'].replace('\r','')
    if 'PRIVATE KEY' not in raw:
        import base64
        raw=base64.b64decode(raw,validate=True).decode()
    key.write_text(raw.rstrip()+'\n');key.chmod(0o600)
    try:
        run(['ssh-keygen','-y','-f',str(key)])
        known.write_bytes(run(['ssh-keyscan','-T','15','-t','ed25519',host]));known.chmod(0o600)
        result=json.loads(run(['ssh','-i',str(key),'-o','StrictHostKeyChecking=yes','-o','UserKnownHostsFile='+str(known),'-o','GlobalKnownHostsFile=/dev/null','-o','BatchMode=yes','-o','ConnectTimeout=15',user+'@'+host,'python3 -'],REMOTE.encode()))
        result['source_sha']=pr['head']['sha'];result['run_id']=int(os.environ['GITHUB_RUN_ID'])
        result['http']=[probe(name,method) for name in result['endpoint_sha256'] for method in ('GET','POST')]
        connection=http.client.HTTPSConnection('anytoour.ru',timeout=20)
        try:
            connection.request('GET','/_preview/search3-anex-candidate/anex-preview-manifest.json')
            response=connection.getresponse();body=response.read(3000001)
            assert response.status==200 and len(body)<=3000000
            assert hashlib.sha256(body).hexdigest()==result['manifest_sha256'],'SSH/HTTPS target mismatch'
            result['https_manifest_matches']=True
        finally:connection.close()
        (out/'result.json').write_text(json.dumps(result,indent=2)+'\n')
        print(json.dumps(result))
    finally:
        key.unlink(missing_ok=True);known.unlink(missing_ok=True)


if __name__=='__main__':main()
