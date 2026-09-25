"""Read only the exact selected quote checkpoint after the bounded UI failure."""
import hashlib
import json
import os
from pathlib import Path
import re
import subprocess
import sys

REMOTE = r'''
import hashlib,json,re
from datetime import datetime,timezone
from pathlib import Path
OFFER='offer_4903321565b16eb8e14d636b99e984472e7c804dcb428d209330b6c6f9b14fc0'
START=int(datetime(2026,9,25,19,27,tzinfo=timezone.utc).timestamp())
def read(path,limit=131072):
    assert path.resolve()==path and path.is_file() and not path.is_symlink()
    assert path.stat().st_nlink==1 and path.stat().st_size<=limit
    return path.read_bytes()
def inspect(directory):
    assert directory.resolve()==directory and directory.is_dir() and not directory.is_symlink()
    rows=[]
    for path in directory.glob('*-'+OFFER+'-quote-v1.json'):
        assert len(rows)<4,'ambiguous_checkpoint'
        assert re.fullmatch('[a-f0-9]{64}-g[1-9][0-9]*-p[1-9][0-9]*-'+OFFER+'-quote-v1.json',path.name)
        body=read(path)
        if path.stat().st_mtime<START:continue
        value=json.loads(body);assert set(value)=={'state'} and isinstance(value['state'],dict)
        state=value['state'];status=state.get('status');assert status in ('reserved','unknown','completed')
        result=state.get('result');assert result is None or isinstance(result,dict)
        quote_state=result.get('state') if result else None
        assert quote_state in (None,'quote_verified','flight_selection_required')
        rows.append({'sha256':hashlib.sha256(body).hexdigest(),'status':status,
          'result_state':quote_state,'result_present':result is not None,
          'final_price_verified':bool(result and result.get('final_price_verified') is True),
          'flight_count':len(result.get('flights',[])) if result else 0})
    return rows
if __name__=='__main__':
    root=Path.home()/'www/anytoour.ru';target=root/'_preview/search3-anex-candidate'
    pins={'.htaccess':'85d597ae4934bc3a2dc16c2aef6499b2dfce918c651c775bf182232aef958fe2','api-andromeda-quote-preview.php':'54bbab700a6507cd116744140b3bf8f4d1e05b9d549c4479ab98687d86eb6118'}
    for name,digest in pins.items():assert hashlib.sha256(read(target/name,524288)).hexdigest()==digest,'runtime_drift'
    rows=inspect(Path.home()/'.anytoour-andromeda/searches')
    print(json.dumps({'status':'inspected_read_only','matching_checkpoints':len(rows),'checkpoints':rows,'supplier_calls':0,'database_reads':0,'database_writes':0,'remote_writes':0,'lead_calls':0,'replay_attempts':0}))
'''


def run(args, data=None):
    result=subprocess.run(args,input=data,capture_output=True,timeout=60)
    if result.returncode:raise RuntimeError('checked command failed')
    return result.stdout


def main():
    compile(REMOTE,'read-only-remote','exec')
    if '--check' in sys.argv:return
    assert os.environ['GITHUB_REPOSITORY']=='pyatkoff/poisk-turov-test'
    assert os.environ['GITHUB_RUN_ATTEMPT']=='1'
    assert os.environ['GITHUB_ACTOR']==os.environ['GITHUB_TRIGGERING_ACTOR']=='pyatkoff'
    event=json.loads(Path(os.environ['GITHUB_EVENT_PATH']).read_text())
    pr=event['pull_request']
    assert event['sender']['id']==226193297 and pr['head']['repo']['full_name']=='pyatkoff/poisk-turov-test'
    assert pr['head']['ref']=='ops/search3-selected-quote-checkpoint-20260925'
    host=os.environ['PREVIEW_HOST'];user=os.environ['PREVIEW_USER']
    assert re.fullmatch(r'[A-Za-z0-9][A-Za-z0-9.-]*',host) and re.fullmatch(r'[A-Za-z0-9_][A-Za-z0-9_.-]{0,63}',user)
    out=Path(os.environ['RUNNER_TEMP'])/'search3-selected-quote-checkpoint';out.mkdir(mode=0o700)
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
        (out/'result.json').write_text(json.dumps(result,indent=2)+'\n')
        print(json.dumps(result))
    finally:
        key.unlink(missing_ok=True);known.unlink(missing_ok=True)


if __name__=='__main__':main()
