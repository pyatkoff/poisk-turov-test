#!/usr/bin/env python3
import base64,hashlib,io,json,os,pathlib,re,subprocess,sys,urllib.request,zipfile
REPO='pyatkoff/poisk-turov-test'; ISSUE=3419
CMD=re.compile(r'^/(install|inspect)-search3-root-gateway(?: ([a-f0-9]{40}) ([a-f0-9]{40}) ([a-f0-9]{40}) ([a-f0-9]{64}))?
def need(v,m):
 if not v: raise RuntimeError(m)
def sha(b):return hashlib.sha256(b).hexdigest()
def blob(b):return hashlib.sha1(b'blob '+str(len(b)).encode()+b'\0'+b).hexdigest()
def ev():
 e=json.loads(pathlib.Path(os.environ['GITHUB_EVENT_PATH']).read_text());m=CMD.fullmatch(e['comment']['body'].strip());need(m,'command');need(e['issue']['number']==ISSUE and e['comment']['user']['id']==226193297 and e['comment']['author_association']=='OWNER','owner');return m.groups()
def api(path):
 q=urllib.request.Request('https://api.github.com/repos/'+REPO+path,headers={'Authorization':'Bearer '+os.environ['GH_TOKEN'],'Accept':'application/vnd.github+json','User-Agent':'search3-root-installer'})
 with urllib.request.urlopen(q,timeout=30) as r:return json.loads(r.read())
def source(source):
 item=api('/contents/v2/api-v2.php?ref='+source);b=base64.b64decode(item['content']);need(item['sha']==blob(b),'blob');return b,item['sha']
def prepare():
 mode,src,rel,bl,prev=ev();need(mode=='install','install_command');need(api('/git/ref/heads/release/search3-production-ready-v1')['object']['sha']==rel,'release_moved');b,got=source(src);need(got==bl,'source_blob');need(b'search_results_limit' in b and b'strict_int($value, 1, 5000' in b,'limit_contract')
 w=pathlib.Path(os.environ['RUNNER_TEMP'])/'search3-root-gateway-prepare'/'retained';w.mkdir(parents=True);(w/'api-v2.php').write_bytes(b);(w/'request.json').write_text(json.dumps({'source':src,'release':rel,'blob':bl,'sha256':sha(b),'previous':prev},sort_keys=True));print(json.dumps({'status':'prepared','sha256':sha(b)}))
def run(a,input=None,timeout=90):return subprocess.run(a,input=input,capture_output=True,check=True,timeout=timeout).stdout
def inspect():
 mode,src,rel,bl,prev=ev();need(mode=='inspect','inspect_command');work=pathlib.Path(os.environ['RUNNER_TEMP'])/'search3-root-gateway-inspect';work.mkdir();key=work/'key';known=work/'known';raw=os.environ['PREVIEW_KEY'].replace('\\r','')
 if 'PRIVATE KEY' not in raw:raw=base64.b64decode(raw,validate=True).decode()
 key.write_text(raw.rstrip()+'\\n');key.chmod(0o600);host=os.environ['PREVIEW_HOST'];user=os.environ['PREVIEW_USER'];known.write_bytes(run(['ssh-keyscan','-T','15','-t','ed25519',host]));known.chmod(0o600);opts=['-i',str(key),'-o','StrictHostKeyChecking=yes','-o','UserKnownHostsFile='+str(known),'-o','GlobalKnownHostsFile=/dev/null','-o','BatchMode=yes','-o','ConnectTimeout=15'];remote=user+'@'+host;out=run(['ssh',*opts,remote,'set -e; f="$HOME/www/anytoour.ru/api-v2.php"; test -f "$f" && ! test -L "$f"; php -l "$f" >/dev/null; sha256sum "$f" | cut -d" " -f1']).decode().strip();need(re.fullmatch(r'[a-f0-9]{64}',out),'live_hash');e={'status':'inspected_read_only','sha256':out,'supplier_calls':0,'server_writes':0};(work/'evidence.json').write_text(json.dumps(e,sort_keys=True));print(json.dumps(e))
def install():
 mode,src,rel,bl,prev=ev();need(mode=='install','install_command');need(api('/git/ref/heads/release/search3-production-ready-v1')['object']['sha']==rel,'release_moved');meta=api('/actions/artifacts/'+os.environ['READY_ARTIFACT_ID']);need(not meta['expired'] and meta['workflow_run']['id']==int(os.environ['GITHUB_RUN_ID']),'artifact_identity')
 q=urllib.request.Request(meta['archive_download_url'],headers={'Authorization':'Bearer '+os.environ['GH_TOKEN'],'Accept':'application/vnd.github+json','User-Agent':'search3-root-installer'});packed=urllib.request.urlopen(q,timeout=30).read()
 with zipfile.ZipFile(io.BytesIO(packed)) as z:b=z.read('api-v2.php');req=json.loads(z.read('request.json'))
 need(req=={'source':src,'release':rel,'blob':bl,'sha256':sha(b),'previous':prev},'artifact_request')
 work=pathlib.Path(os.environ['RUNNER_TEMP'])/'search3-root-gateway-install';work.mkdir();f=work/'api-v2.php';f.write_bytes(b);key=work/'key';known=work/'known';raw=os.environ['PREVIEW_KEY'].replace('\r','')
 if 'PRIVATE KEY' not in raw:raw=base64.b64decode(raw,validate=True).decode()
 key.write_text(raw.rstrip()+'\n');key.chmod(0o600);host=os.environ['PREVIEW_HOST'];user=os.environ['PREVIEW_USER'];need(re.fullmatch(r'[A-Za-z0-9][A-Za-z0-9.-]*',host) and re.fullmatch(r'[A-Za-z0-9_][A-Za-z0-9_.-]{0,63}',user),'ssh_identity');known.write_bytes(run(['ssh-keyscan','-T','15','-t','ed25519',host]));known.chmod(0o600)
 opts=['-i',str(key),'-o','StrictHostKeyChecking=yes','-o','UserKnownHostsFile='+str(known),'-o','GlobalKnownHostsFile=/dev/null','-o','BatchMode=yes','-o','ConnectTimeout=15'];remote=user+'@'+host;backup='$HOME/www/anytoour.ru/.api-v2.php.search3-'+os.environ['GITHUB_RUN_ID']+'.bak';e={'source':src,'release':rel,'status':'checked_not_installed','supplier_calls':0,'real_leads':0};attempt=False
 try:
  before=run(['ssh',*opts,remote,'sha256sum "$HOME/www/anytoour.ru/api-v2.php" | cut -d" " -f1']).decode().strip();need(before==prev,'predecessor_drift');run(['scp',*opts,str(f),remote+':/tmp/search3-api-v2-'+os.environ['GITHUB_RUN_ID']+'.php']);script='set -euo pipefail; t="$HOME/www/anytoour.ru/api-v2.php"; n="/tmp/search3-api-v2-'+os.environ['GITHUB_RUN_ID']+'.php"; b="'+backup+'"; php -l "$n" >/dev/null; cp "$t" "$b"; chmod 600 "$b"; mv "$n" "$t"; chmod 644 "$t"; php -l "$t" >/dev/null; sha256sum "$t" | cut -d" " -f1';attempt=True;after=run(['ssh',*opts,remote,script]).decode().strip();need(after==sha(b),'installed_hash')
  with urllib.request.urlopen('https://anytoour.ru/api-v2.php?action=health',timeout=30) as rr:health=rr.read();code=rr.status
  need(code==200 and b'tourvisor-direct' in health,'health');e.update(status='installed',previous_sha256=before,installed_sha256=after,health_HTTP=200,backup_retained=True)
 except Exception as exc:
  e.update(status='failed',reason=str(exc))
  if attempt:
   try:run(['ssh',*opts,remote,'set -e; t="$HOME/www/anytoour.ru/api-v2.php"; b="'+backup+'"; test -s "$b"; cp "$b" "$t"; php -l "$t" >/dev/null']);e['rollback']='restored'
   except Exception as rx:e['rollback']='unknown:'+str(rx)
  raise
 finally:
  (work/'evidence.json').write_text(json.dumps(e,sort_keys=True));key.unlink(missing_ok=True);known.unlink(missing_ok=True)
 print(json.dumps(e))
if __name__=='__main__':need(len(sys.argv)==2 and sys.argv[1] in ('prepare','install','inspect'),'usage');globals()[sys.argv[1]]()
)
def need(v,m):
 if not v: raise RuntimeError(m)
def sha(b):return hashlib.sha256(b).hexdigest()
def blob(b):return hashlib.sha1(b'blob '+str(len(b)).encode()+b'\0'+b).hexdigest()
def ev():
 e=json.loads(pathlib.Path(os.environ['GITHUB_EVENT_PATH']).read_text());m=CMD.fullmatch(e['comment']['body'].strip());need(m,'command');need(e['issue']['number']==ISSUE and e['comment']['user']['id']==226193297 and e['comment']['author_association']=='OWNER','owner');return m.groups()
def api(path):
 q=urllib.request.Request('https://api.github.com/repos/'+REPO+path,headers={'Authorization':'Bearer '+os.environ['GH_TOKEN'],'Accept':'application/vnd.github+json','User-Agent':'search3-root-installer'})
 with urllib.request.urlopen(q,timeout=30) as r:return json.loads(r.read())
def source(source):
 item=api('/contents/v2/api-v2.php?ref='+source);b=base64.b64decode(item['content']);need(item['sha']==blob(b),'blob');return b,item['sha']
def prepare():
 src,rel,bl,prev=ev();need(api('/git/ref/heads/release/search3-production-ready-v1')['object']['sha']==rel,'release_moved');b,got=source(src);need(got==bl,'source_blob');need(b'search_results_limit' in b and b'strict_int($value, 1, 5000' in b,'limit_contract')
 w=pathlib.Path(os.environ['RUNNER_TEMP'])/'search3-root-gateway-prepare'/'retained';w.mkdir(parents=True);(w/'api-v2.php').write_bytes(b);(w/'request.json').write_text(json.dumps({'source':src,'release':rel,'blob':bl,'sha256':sha(b),'previous':prev},sort_keys=True));print(json.dumps({'status':'prepared','sha256':sha(b)}))
def run(a,input=None,timeout=90):return subprocess.run(a,input=input,capture_output=True,check=True,timeout=timeout).stdout
def install():
 src,rel,bl,prev=ev();need(api('/git/ref/heads/release/search3-production-ready-v1')['object']['sha']==rel,'release_moved');meta=api('/actions/artifacts/'+os.environ['READY_ARTIFACT_ID']);need(not meta['expired'] and meta['workflow_run']['id']==int(os.environ['GITHUB_RUN_ID']),'artifact_identity')
 q=urllib.request.Request(meta['archive_download_url'],headers={'Authorization':'Bearer '+os.environ['GH_TOKEN'],'Accept':'application/vnd.github+json','User-Agent':'search3-root-installer'});packed=urllib.request.urlopen(q,timeout=30).read()
 with zipfile.ZipFile(io.BytesIO(packed)) as z:b=z.read('api-v2.php');req=json.loads(z.read('request.json'))
 need(req=={'source':src,'release':rel,'blob':bl,'sha256':sha(b),'previous':prev},'artifact_request')
 work=pathlib.Path(os.environ['RUNNER_TEMP'])/'search3-root-gateway-install';work.mkdir();f=work/'api-v2.php';f.write_bytes(b);key=work/'key';known=work/'known';raw=os.environ['PREVIEW_KEY'].replace('\r','')
 if 'PRIVATE KEY' not in raw:raw=base64.b64decode(raw,validate=True).decode()
 key.write_text(raw.rstrip()+'\n');key.chmod(0o600);host=os.environ['PREVIEW_HOST'];user=os.environ['PREVIEW_USER'];need(re.fullmatch(r'[A-Za-z0-9][A-Za-z0-9.-]*',host) and re.fullmatch(r'[A-Za-z0-9_][A-Za-z0-9_.-]{0,63}',user),'ssh_identity');known.write_bytes(run(['ssh-keyscan','-T','15','-t','ed25519',host]));known.chmod(0o600)
 opts=['-i',str(key),'-o','StrictHostKeyChecking=yes','-o','UserKnownHostsFile='+str(known),'-o','GlobalKnownHostsFile=/dev/null','-o','BatchMode=yes','-o','ConnectTimeout=15'];remote=user+'@'+host;backup='$HOME/www/anytoour.ru/.api-v2.php.search3-'+os.environ['GITHUB_RUN_ID']+'.bak';e={'source':src,'release':rel,'status':'checked_not_installed','supplier_calls':0,'real_leads':0};attempt=False
 try:
  before=run(['ssh',*opts,remote,'sha256sum "$HOME/www/anytoour.ru/api-v2.php" | cut -d" " -f1']).decode().strip();need(before==prev,'predecessor_drift');run(['scp',*opts,str(f),remote+':/tmp/search3-api-v2-'+os.environ['GITHUB_RUN_ID']+'.php']);script='set -euo pipefail; t="$HOME/www/anytoour.ru/api-v2.php"; n="/tmp/search3-api-v2-'+os.environ['GITHUB_RUN_ID']+'.php"; b="'+backup+'"; php -l "$n" >/dev/null; cp "$t" "$b"; chmod 600 "$b"; mv "$n" "$t"; chmod 644 "$t"; php -l "$t" >/dev/null; sha256sum "$t" | cut -d" " -f1';attempt=True;after=run(['ssh',*opts,remote,script]).decode().strip();need(after==sha(b),'installed_hash')
  with urllib.request.urlopen('https://anytoour.ru/api-v2.php?action=health',timeout=30) as rr:health=rr.read();code=rr.status
  need(code==200 and b'tourvisor-direct' in health,'health');e.update(status='installed',previous_sha256=before,installed_sha256=after,health_HTTP=200,backup_retained=True)
 except Exception as exc:
  e.update(status='failed',reason=str(exc))
  if attempt:
   try:run(['ssh',*opts,remote,'set -e; t="$HOME/www/anytoour.ru/api-v2.php"; b="'+backup+'"; test -s "$b"; cp "$b" "$t"; php -l "$t" >/dev/null']);e['rollback']='restored'
   except Exception as rx:e['rollback']='unknown:'+str(rx)
  raise
 finally:
  (work/'evidence.json').write_text(json.dumps(e,sort_keys=True));key.unlink(missing_ok=True);known.unlink(missing_ok=True)
 print(json.dumps(e))
if __name__=='__main__':need(len(sys.argv)==2 and sys.argv[1] in ('prepare','install'),'usage');globals()[sys.argv[1]]()
