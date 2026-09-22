#!/usr/bin/env python3
import hashlib,json,os,pathlib,re,subprocess,sys,tempfile,urllib.request,zipfile
REPO='pyatkoff/poisk-turov-test'; ISSUE=3419
CMD=re.compile(r'^/install-search3-root-gateway ([a-f0-9]{40}) ([a-f0-9]{40}) ([a-f0-9]{40}) ([a-f0-9]{64})$')
def need(v,m):
    if not v: raise RuntimeError(m)
def sha(b): return hashlib.sha256(b).hexdigest()
def event():
    e=json.loads(pathlib.Path(os.environ['GITHUB_EVENT_PATH']).read_text()); m=CMD.fullmatch(e['comment']['body'].strip());need(m,'command')
    need(e['issue']['number']==ISSUE and e['comment']['user']['id']==226193297 and e['comment']['author_association']=='OWNER','owner')
    return e,m.groups()
def api(path):
    q=urllib.request.Request('https://api.github.com/repos/'+REPO+path,headers={'Authorization':'Bearer '+os.environ['GH_TOKEN'],'Accept':'application/vnd.github+json','User-Agent':'search3-root-installer'})
    with urllib.request.urlopen(q,timeout=30) as r:return json.loads(r.read())
def source_bytes(source):
    item=api('/contents/v2/api-v2.php?ref='+source);need(item.get('sha'),'source_missing')
    import base64
    b=base64.b64decode(item['content']);need(item['sha']==blob_sha(b),'blob_mismatch');return b,item['sha']
def blob_sha(b):return hashlib.sha1(b'blob '+str(len(b)).encode()+b'\0'+b).hexdigest()
def prepare():
    e,(source,release,blob,previous)=event();need(api('/git/ref/heads/release/search3-production-ready-v1')['object']['sha']==release,'release_moved')
    b,actual=source_bytes(source);need(actual==blob,'source_blob');need(b'search_results_limit' in b and b'strict_int($value, 1, 5000' in b,'limit_contract')
    w=pathlib.Path(os.environ['RUNNER_TEMP'])/'search3-root-gateway-prepare'/'retained';w.mkdir(parents=True)
    (w/'api-v2.php').write_bytes(b);(w/'request.json').write_text(json.dumps({'source':source,'release':release,'blob':blob,'sha256':sha(b),'previous':previous},sort_keys=True))
    print(json.dumps({'status':'prepared','sha256':sha(b),'blob':blob}))
def cmd(a,input=None,timeout=60):return subprocess.run(a,input=input,capture_output=True,check=True,timeout=timeout).stdout
def install():
    e,(source,release,blob,previous)=event();need(api('/git/ref/heads/release/search3-production-ready-v1')['object']['sha']==release,'release_moved')
    meta=api('/actions/artifacts/'+os.environ['READY_ARTIFACT_ID']);need(not meta['expired'] and meta['workflow_run']['id']==int(os.environ['GITHUB_RUN_ID']),'artifact_identity')
    q=urllib.request.Request(meta['archive_download_url'],headers={'Authorization':'Bearer '+os.environ['GH_TOKEN'],'Accept':'application/vnd.github+json','User-Agent':'search3-root-installer'})
    packed=urllib.request.urlopen(q,timeout=30).read()
    with zipfile.ZipFile(__import__('io').BytesIO(packed)) as z:
        b=z.read('api-v2.php'); req=json.loads(z.read('request.json'))
    need(req=={'source':source,'release':release,'blob':blob,'sha256':sha(b),'previous':previous},'artifact_request')
    work=pathlib.Path(os.environ['RUNNER_TEMP'])/'search3-root-gateway-install';work.mkdir()
    f=work/'api-v2.php';f.write_bytes(b); key=work/'key';known=work/'known'
    raw=os.environ['PREVIEW_KEY'].replace('\r',''); 
    if 'PRIVATE KEY' not in raw:
        import base64;raw=base64.b64decode(raw,validate=True).decode()
    key.write_text(raw.rstrip()+'\n');key.chmod(0o600)
    host=os.environ['PREVIEW_HOST'];user=os.environ['PREVIEW_USER'];need(re.fullmatch(r'[A-Za-z0-9][A-Za-z0-9.-]*',host) and re.fullmatch(r'[A-Za-z0-9_][A-Za-z0-9_.-]{0,63}',user),'ssh_identity')
    known.write_bytes(cmd(['ssh-keyscan','-T','15','-t','ed25519',host]));known.chmod(0o600)
    opts=['-i',str(key),'-o','StrictHostKeyChecking=yes','-o','UserKnownHostsFile='+str(known),'-o','GlobalKnownHostsFile=/dev/null','-o','BatchMode=yes','-o','ConnectTimeout=15']
    remote=user+'@'+host; target='$HOME/www/anytoour.ru/api-v2.php'; backup='$HOME/www/anytoour.ru/.api-v2.php.search3-'+os.environ['GITHUB_RUN_ID']+'.bak'
    evidence={'source':source,'release':release,'status':'checked_not_installed','supplier_calls':0,'real_leads':0}
    attempted=False
    try:
        before=cmd(['ssh',*opts,remote,'sha256sum "$HOME/www/anytoour.ru/api-v2.php" | cut -d" " -f1']).decode().strip();need(before==previous,'predecessor_drift')
        cmd(['scp',*opts,str(f),remote+':/tmp/search3-api-v2-'+os.environ['GITHUB_RUN_ID']+'.php'])
        script='set -euo pipefail; t="$HOME/www/anytoour.ru/api-v2.php"; n="/tmp/search3-api-v2-'+os.environ['GITHUB_RUN_ID']+'.php"; b="'+backup.replace('$HOME','$HOME')+'"; php -l "$n" >/dev/null; cp "$t" "$b"; chmod 600 "$b"; mv "$n" "$t"; chmod 644 "$t"; php -l "$t" >/dev/null; sha256sum "$t" | cut -d" " -f1'
        attempted=True; after=cmd(['ssh',*opts,remote,script]).decode().strip();need(after==sha(b),'installed_hash')
        with urllib.request.urlopen('https://anytoour.ru/api-v2.php?action=health',timeout=30) as rr: health=rr.read()
        need(rr.status==200 and b'tourvisor-direct' in health,'health')
        evidence.update(status='installed',previous_sha256=before,installed_sha256=after,health_HTTP=200,backup_retained=True)
    except Exception as exc:
        evidence.update(status='failed',reason=str(exc))
        if attempted:
            try:cmd(['ssh',*opts,remote,'set -e; t="$HOME/www/anytoour.ru/api-v2.php"; b="'+backup.replace('$HOME','$HOME')+'"; test -s "$b"; cp "$b" "$t"; php -l "$t" >/dev/null']);evidence['rollback']='restored'
            except Exception as rexc:evidence['rollback']='unknown:'+str(rexc)
        raise
    finally:
        (work/'evidence.json').write_text(json.dumps(evidence,sort_keys=True));key.unlink(missing_ok=True);known.unlink(missing_ok=True)
    print(json.dumps(evidence))
if __name__=='__main__':
    need(len(sys.argv)==2 and sys.argv[1] in ('prepare','install'),'usage');globals()[sys.argv[1]]()
