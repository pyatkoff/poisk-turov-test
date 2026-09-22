#!/usr/bin/env python3
import base64,json,os,pathlib,re,subprocess
def need(v,m):
 if not v: raise RuntimeError(m)
e=json.loads(pathlib.Path(os.environ['GITHUB_EVENT_PATH']).read_text())
need(e['issue']['number']==3419 and e['comment']['body'].strip()=='/inspect-search3-root-gateway','command')
need(e['comment']['user']['id']==226193297 and e['comment']['author_association']=='OWNER','owner')
work=pathlib.Path(os.environ['RUNNER_TEMP'])/'search3-root-gateway-inspect';work.mkdir()
key=work/'key';known=work/'known';raw=os.environ['PREVIEW_KEY'].replace('\r','')
if 'PRIVATE KEY' not in raw:raw=base64.b64decode(raw,validate=True).decode()
key.write_text(raw.rstrip()+'\n');key.chmod(0o600)
host=os.environ['PREVIEW_HOST'];user=os.environ['PREVIEW_USER']
need(re.fullmatch(r'[A-Za-z0-9][A-Za-z0-9.-]*',host) and re.fullmatch(r'[A-Za-z0-9_][A-Za-z0-9_.-]{0,63}',user),'ssh_identity')
known.write_bytes(subprocess.run(['ssh-keyscan','-T','15','-t','ed25519',host],capture_output=True,check=True,timeout=30).stdout);known.chmod(0o600)
opts=['-i',str(key),'-o','StrictHostKeyChecking=yes','-o','UserKnownHostsFile='+str(known),'-o','GlobalKnownHostsFile=/dev/null','-o','BatchMode=yes','-o','ConnectTimeout=15']
q=subprocess.run(['ssh',*opts,user+'@'+host,'set -e; f="$HOME/www/anytoour.ru/api-v2.php"; test -f "$f" && ! test -L "$f"; php -l "$f" >/dev/null; sha256sum "$f" | cut -d" " -f1'],capture_output=True,text=True,check=True,timeout=45)
value=q.stdout.strip();need(re.fullmatch(r'[a-f0-9]{64}',value),'live_hash')
out={'status':'inspected_read_only','sha256':value,'supplier_calls':0,'server_writes':0}
(work/'evidence.json').write_text(json.dumps(out,sort_keys=True));print(json.dumps(out))
