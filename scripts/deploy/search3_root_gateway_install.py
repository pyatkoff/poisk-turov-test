#!/usr/bin/env python3
import base64,hashlib,io,json,os,pathlib,re,subprocess,sys,urllib.request,zipfile
REPO='pyatkoff/poisk-turov-test'; ISSUE=3419
CMD=re.compile(r'^/install-search3-root-gateway ([a-f0-9]{40}) ([a-f0-9]{40}) ([a-f0-9]{40}) ([a-f0-9]{64})$')
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
 ready=pathlib.Path(os.environ['READY_DIR']); need(ready.is_dir() and not ready.is_symlink(),'ready_dir'); files={p.name:p for p in ready.rglob('*') if p.is_file()}; need(set(files)=={'api-v2.php','request.json'},'ready_inventory'); b=files['api-v2.php'].read_bytes(); req=json.loads(files['request.json'].read_text())
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
CHECKED_WORKFLOW='.github/workflows/search3-root-gateway-install.yml'
CHECKED_PREFIX='/install-search3-root-gateway'
CHECKED_OPERATION='install-20261018-fcfe503e-v1'
CHECKED_SOURCE='fcfe503e33f902ec19a9eff8cfc7985b1c242f04'
CHECKED_RELEASE='6bb834a586c231c3f06a6d6e918f8280d2b3f587'
CHECKED_BLOB='6fb963e68a99945118d04f8e46845ecd6bcb6bc9'
CHECKED_HASH='ef60da2d948c42b7741771ef1e1a091280c14cd79eafea10d737cb7067e55463'
CHECKED_BYTES=14557
CHECKED_READER_HASH='4ab35d1a96f825116b52f75b612597d9936e1f609d0120503742cc80b68777de'
CHECKED_CHECK_IDS={117537320957,117536871161,117536871120,117536871083,117536871012}
CHECKED_EXPECTED={
 'api-v2.php':'445c421238c649b2dfb344f59b20c52f8be6418ded8de19b2d1c356bc710ccce',
 'data/collect-hotel-details-v1.php':'b653b3d174b5afbd22ba73398819fabd76d6a833783323b1845d64a2eb185113',
 'data/db-v1.php':'ca7f11d6ec53e0cd3d4464e0f645f15b761bb734da1323689479838868d4abc4',
 'data/hotel-details-v1.php':'137bbf53ef87fab50505ecd41a075cb5f571193480da7f0e3face51190c0f77f',
 'data/local-tv-catalog-enabled.json':'3c11725b4b9507427654398aac403177f338d7618b77c96d695582b4e49d3440',
 'data/local-tv-catalog-v1.php':'b3f4f79d1bb54d723c5e9cbaf4b6e59375e66b9551d4e602a2f9b01c44886a46',
 'data/price-observer-v1.php':'7e057905b1218815fc828c1c558f681eca67d079ad220ee3487b315661357843',
 'data/tourvisor-client-v1.php':'f669434c2b0bf6af0a68684d02eea964173f2d3c0408e49b94db9606e8273a17',
}
CHECKED_PROTECTED=('index.php','v2/index.php','v2/api-v2.php')

def checked_event(event,env):
 need(env.get('GITHUB_REPOSITORY')==REPO and event.get('repository',{}).get('full_name')==REPO,'repository')
 need(env.get('GITHUB_REF')=='refs/heads/main','main_only')
 need(env.get('GITHUB_WORKFLOW_REF')==f'{REPO}/{CHECKED_WORKFLOW}@refs/heads/main','workflow')
 need(env.get('GITHUB_ACTOR')==env.get('GITHUB_TRIGGERING_ACTOR')=='pyatkoff','actor')
 need(env.get('GITHUB_EVENT_NAME')=='issue_comment' and event.get('action')=='created','event')
 need(env.get('GITHUB_RUN_ATTEMPT')=='1','run_attempt')
 issue,comment=event.get('issue',{}),event.get('comment',{})
 need(issue.get('number')==4217 and 'pull_request' not in issue,'issue')
 need(comment.get('user',{}).get('id')==226193297 and comment.get('author_association')=='OWNER','owner')
 control=env.get('GITHUB_SHA','');run_id=env.get('GITHUB_RUN_ID','')
 need(re.fullmatch(r'[a-f0-9]{40}',control) is not None and re.fullmatch(r'[1-9][0-9]{0,19}',run_id) is not None,'identity')
 need(type(comment.get('id')) is int and comment['id']>0,'comment_id')
 need(comment.get('body')==f'{CHECKED_PREFIX} {control} {CHECKED_OPERATION}','command')
 return {'control_source_sha':control,'run_id':run_id,'command_comment_id':comment['id']}

def checked_api(path):
 q=urllib.request.Request('https://api.github.com/repos/'+REPO+path,headers={'Authorization':'Bearer '+os.environ['GH_TOKEN'],'Accept':'application/vnd.github+json','User-Agent':'search3-checked-root-install'})
 with urllib.request.urlopen(q,timeout=30) as r:data=r.read(4*1024*1024+1)
 need(len(data)<=4*1024*1024,'github_response_limit')
 return json.loads(data)

def checked_admission(identity):
 need(checked_api('/git/ref/heads/main')['object']['sha']==identity['control_source_sha'],'main_moved')
 need(checked_api('/git/ref/heads/release/search3-production-ready-v1')['object']['sha']==CHECKED_RELEASE,'release_moved')
 source_item=checked_api('/contents/v2/api-v2.php?ref='+CHECKED_SOURCE)
 need(source_item['sha']==checked_api('/contents/v2/api-v2.php?ref='+CHECKED_RELEASE)['sha']==CHECKED_BLOB,'candidate_blob')
 data=base64.b64decode(source_item['content'],validate=False)
 need(blob(data)==CHECKED_BLOB and sha(data)==CHECKED_HASH and len(data)==CHECKED_BYTES,'candidate_bytes')
 checks=checked_api('/commits/'+CHECKED_SOURCE+'/check-runs?per_page=100')
 need(checks.get('total_count')==len(CHECKED_CHECK_IDS) and {c['id'] for c in checks['check_runs']}==CHECKED_CHECK_IDS,'source_checks')
 need(all(c.get('status')=='completed' and c.get('conclusion')=='success' and c.get('app',{}).get('slug')=='github-actions' for c in checks['check_runs']),'source_checks')
 matched=[]
 for page in range(1,31):
  comments=checked_api(f'/issues/4217/comments?per_page=100&page={page}');need(isinstance(comments,list),'comments_shape')
  matched.extend(c['id'] for c in comments if c.get('body','').strip().startswith(CHECKED_PREFIX+' '))
  if len(comments)<100:
   need(matched==[identity['command_comment_id']],'operation_already_reserved');return data
 raise RuntimeError('comment_history_incomplete')

def checked_remote():
 # Include only the existing reviewed race-safe reader, never its repair writer.
 import ast
 body=(pathlib.Path(__file__).with_name('int_server_executor_local_tv_capture.py')).read_bytes()
 need(sha(body)==CHECKED_READER_HASH,'reader_source_drift')
 assignments=ast.parse(body).body
 remote=ast.literal_eval(next(n.value for n in assignments if isinstance(n,ast.Assign) and any(isinstance(t,ast.Name) and t.id=='REMOTE_REPAIR' for t in n.targets)))
 reader=next(n for n in ast.parse(remote).body if isinstance(n,ast.FunctionDef) and n.name=='repair_read')
 code=ast.get_source_segment(remote,reader);need(code is not None,'reader_missing')
 policy={'operation':CHECKED_OPERATION,'source':CHECKED_SOURCE,'release':CHECKED_RELEASE,'blob':CHECKED_BLOB,
         'sha256':CHECKED_HASH,'bytes':CHECKED_BYTES,'expected':CHECKED_EXPECTED,'protected':CHECKED_PROTECTED}
 return 'POLICY='+repr(policy)+'\n'+code+'\n'+REMOTE_CHECKED_INSTALL

REMOTE_CHECKED_INSTALL=r'''
import base64,hashlib,json,os,pathlib,stat,subprocess,sys
def fail(reason):raise RuntimeError(reason)
project=pathlib.Path.home()/'www/anytoour.ru'
REPAIR_POLICY={'good':dict.fromkeys((*POLICY['expected'],*POLICY['protected']))}
def identity(path):
 s=path.lstat();return (s.st_dev,s.st_ino,s.st_mode,s.st_nlink,s.st_size,s.st_mtime_ns,s.st_ctime_ns)
def checked_read(path):
 first=identity(project/path);data,record=repair_read(path)
 if identity(project/path)!=first:fail('file_changed')
 return data,record,first
def snapshot():
 values={};identities={};bodies={}
 for path in POLICY['expected']:
  bodies[path],values[path],identities[path]=checked_read(path)
 return values,identities,bodies
def public_fingerprints():
 values={}
 for path in POLICY['protected']:
  try:values[path]=checked_read(path)[1]['sha256']
  except FileNotFoundError:
   # Missing paths may be reported, but no existing symlink ancestor is accepted.
   for parent in (project/path).parents:
    if parent==project.parent:break
    if parent.is_symlink():fail('unsafe_parent')
   values[path]=None
 return values
def write_private(path,data,mode=0o600):
 fd=os.open(path,os.O_WRONLY|os.O_CREAT|os.O_EXCL|os.O_NOFOLLOW,mode)
 with os.fdopen(fd,'wb') as f:os.fchmod(f.fileno(),mode);f.write(data);f.flush();os.fsync(f.fileno())
def lint(path):
 result=subprocess.run(['php','-n','-l',str(path)],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL,
                       timeout=15,check=False,env={'PATH':'/usr/bin:/bin','HOME':str(pathlib.Path.home())})
 if result.returncode:fail('syntax_failed')
def sync_directory(path):
 fd=os.open(path,os.O_RDONLY|os.O_DIRECTORY|os.O_NOFOLLOW)
 try:os.fsync(fd)
 finally:os.close(fd)
data={'schema_version':1,'operation_id':POLICY['operation'],'source_sha':POLICY['source'],
      'release_sha':POLICY['release'],'source_blob':POLICY['blob'],'status':'blocked_no_replay',
      'supplier_calls':0,'database_writes':0,'profile_writes':0,'mapping_writes':0,'schema_writes':0,
      'lead_calls':0,'booking_calls':0,'root_write_attempts':0,'file_writes':0,'rollback_attempts':0,
      'written_files':[],'before_files':{},'after_files':{},'syntax_previous':'not_run',
      'syntax_candidate':'not_run','syntax_installed':'not_run','backup_retained':False}
reserved=False;op=None;installed_id=None;old=None
try:
 raw=sys.stdin.buffer.read(65537)
 if len(raw)>65536:fail('payload_limit')
 payload=json.loads(raw)
 if set(payload)!={'control_source_sha','run_id','command_comment_id','candidate_b64'}:fail('payload_fields')
 import re
 if (re.fullmatch(r'[a-f0-9]{40}',str(payload['control_source_sha'])) is None
     or re.fullmatch(r'[1-9][0-9]{0,19}',str(payload['run_id'])) is None
     or type(payload['command_comment_id']) is not int or payload['command_comment_id']<=0):fail('payload_identity')
 data.update({k:payload[k] for k in ('control_source_sha','run_id','command_comment_id')})
 candidate=base64.b64decode(payload['candidate_b64'],validate=True)
 if (len(candidate)!=POLICY['bytes'] or hashlib.sha256(candidate).hexdigest()!=POLICY['sha256']
     or hashlib.sha1(b'blob '+str(len(candidate)).encode()+b'\0'+candidate).hexdigest()!=POLICY['blob']):fail('candidate_bytes')
 home=pathlib.Path.home();private=home/'.anytoour-search3-root-gateway'
 if home.resolve()!=home or home.is_symlink() or not stat.S_ISDIR(home.lstat().st_mode):fail('unsafe_private_parent')
 try:private.mkdir(mode=0o700)
 except FileExistsError:pass
 p=private.lstat()
 if not stat.S_ISDIR(p.st_mode) or stat.S_IMODE(p.st_mode)!=0o700 or p.st_uid!=os.getuid():fail('unsafe_private_parent')
 op=private/POLICY['operation']
 try:op.mkdir(mode=0o700)
 except FileExistsError:fail('operation_exists_no_replay')
 reserved=True
 write_private(op/'reservation.json',json.dumps({k:data[k] for k in ('operation_id','source_sha','release_sha','source_blob','control_source_sha','run_id','command_comment_id')},sort_keys=True).encode())
 data['production_before']=public_fingerprints()
 before,identities,bodies=snapshot();data['before_files']=before;old=bodies['api-v2.php']
 if any(before[p]['sha256']!=h or before[p]['mode']!=0o644 for p,h in POLICY['expected'].items()):fail('predecessor_drift')
 if project.stat().st_dev!=op.stat().st_dev:fail('different_filesystem')
 stage=op/'candidate.php';backup=op/'previous.php'
 write_private(stage,candidate,0o644);write_private(backup,old);data['backup_retained']=True
 staged_id=identity(stage)
 lint(project/'api-v2.php');data['syntax_previous']='ok'
 lint(stage);data['syntax_candidate']='ok'
 current,current_ids,_=snapshot()
 if (current!=before or current_ids!=identities or public_fingerprints()!=data['production_before']
     or identity(stage)!=staged_id or identity(project/'api-v2.php')!=identities['api-v2.php']):fail('file_changed')
 data['root_write_attempts']=1
 os.replace(stage,project/'api-v2.php');data['file_writes']=1;data['written_files']=['api-v2.php']
 installed_id=identity(project/'api-v2.php');sync_directory(project)
 lint(project/'api-v2.php');data['syntax_installed']='ok'
 after,after_ids,_=snapshot();data['after_files']=after
 expected_after=dict(before);expected_after['api-v2.php']={'sha256':POLICY['sha256'],'bytes':POLICY['bytes'],'mode':0o644}
 data['production_after']=public_fingerprints()
 if (after!=expected_after or after_ids['api-v2.php']!=installed_id
     or any(after_ids[p]!=identities[p] for p in POLICY['expected'] if p!='api-v2.php')
     or data['production_before']!=data['production_after']):fail('installed_readback')
 data['status']='installed_verified'
except Exception as exc:
 allowed={'payload_limit','payload_fields','payload_identity','candidate_bytes','unsafe_private_parent',
          'operation_exists_no_replay','predecessor_drift','different_filesystem','syntax_failed',
          'file_changed','installed_readback','unsafe_parent'}
 reason=str(exc) if type(exc) is RuntimeError and str(exc) in allowed else 'operation_failed'
 data.update(reason=reason,error_type=type(exc).__name__)
 if data['root_write_attempts']:
  data['status']='unknown_no_replay'
  try:
   current,current_record,current_id=checked_read('api-v2.php')
   if (installed_id is not None and current_id==installed_id and current_record['sha256']==POLICY['sha256']):
    rollback=op/'rollback.php';write_private(rollback,old,0o644)
    if identity(project/'api-v2.php')!=installed_id:fail('file_changed')
    data['rollback_attempts']=1;os.replace(rollback,project/'api-v2.php');data['file_writes']+=1;sync_directory(project)
    data['after_files']=snapshot()[0];data['production_after']=public_fingerprints()
    if data['after_files']['api-v2.php']['sha256']==POLICY['expected']['api-v2.php']:
     data['status']='rolled_back_no_replay'
   elif installed_id is None and current_record['sha256']==POLICY['expected']['api-v2.php']:
    data['status']='blocked_no_replay'
  except Exception:pass
finally:
 if reserved:
  try:write_private(op/'result.json',json.dumps(data,sort_keys=True).encode())
  except Exception:data['status']='unknown_no_replay';data['reason']='private_receipt_failed'
 print(json.dumps(data,sort_keys=True))
'''

def checked_safe_receipt(identity,result):
 try:
  counters=('supplier_calls','database_writes','profile_writes','mapping_writes','schema_writes','lead_calls','booking_calls')
  required={'schema_version','operation_id','source_sha','release_sha','source_blob','status',*counters,
            'root_write_attempts','file_writes','rollback_attempts','written_files','before_files','after_files',
            'syntax_previous','syntax_candidate','syntax_installed','backup_retained',*identity}
  allowed=required|{'production_before','production_after','reason','error_type'}
  need(isinstance(result,dict) and required<=set(result)<=allowed,'receipt_fields')
  need(type(result['schema_version']) is int and result['schema_version']==1,'receipt_schema')
  need(result['status'] in ('blocked_no_replay','unknown_no_replay','rolled_back_no_replay','installed_verified'),'receipt_status')
  for k,value in {'operation_id':CHECKED_OPERATION,'source_sha':CHECKED_SOURCE,'release_sha':CHECKED_RELEASE,'source_blob':CHECKED_BLOB,**identity}.items():need(result[k]==value,'receipt_identity')
  for k in counters:need(type(result[k]) is int and result[k]==0,'receipt_counters')
  for k in ('root_write_attempts','rollback_attempts'):need(type(result[k]) is int and result[k] in (0,1),'receipt_attempts')
  need(type(result['file_writes']) is int and 0<=result['file_writes']<=2,'receipt_writes')
  need(result['written_files'] in ([],['api-v2.php']) and type(result['backup_retained']) is bool,'receipt_scope')
  need(all(result['syntax_'+p] in ('not_run','ok') for p in ('previous','candidate','installed')),'receipt_syntax')
  for phase in ('before_files','after_files'):
   need(isinstance(result[phase],dict) and set(result[phase])<=set(CHECKED_EXPECTED),'receipt_inventory')
   for record in result[phase].values():
    need(set(record)=={'sha256','bytes','mode'} and isinstance(record['sha256'],str) and re.fullmatch(r'[a-f0-9]{64}',record['sha256']) is not None,'receipt_record')
    need(type(record['bytes']) is int and 0<record['bytes']<=2*1024*1024 and type(record['mode']) is int and 0<=record['mode']<=0o7777,'receipt_metadata')
  for phase in ('production_before','production_after'):
   if phase in result:
    need(set(result[phase])==set(CHECKED_PROTECTED),'receipt_public')
    need(all(v is None or isinstance(v,str) and re.fullmatch(r'[a-f0-9]{64}',v) is not None for v in result[phase].values()),'receipt_public_hashes')
  if 'reason' in result:need(result['reason'] in ('payload_limit','payload_fields','payload_identity','candidate_bytes','unsafe_private_parent','operation_exists_no_replay','predecessor_drift','different_filesystem','syntax_failed','file_changed','installed_readback','unsafe_parent','operation_failed','private_receipt_failed'),'receipt_reason')
  if 'error_type' in result:need(result['error_type'] in ('RuntimeError','FileNotFoundError','PermissionError','OSError','TimeoutExpired','JSONDecodeError','Error','ValueError'),'receipt_error_type')
  return True
 except (RuntimeError,AttributeError,KeyError,TypeError,ValueError):return False

def checked_completed(identity,result):
 try:
  need(checked_safe_receipt(identity,result),'receipt_unsafe')
  need(result.get('schema_version')==1 and type(result['schema_version']) is int,'receipt_schema')
  need(result.get('status')=='installed_verified','receipt_status')
  for k,value in {'operation_id':CHECKED_OPERATION,'source_sha':CHECKED_SOURCE,'release_sha':CHECKED_RELEASE,'source_blob':CHECKED_BLOB,**identity}.items():need(result.get(k)==value,'receipt_identity')
  for k in ('supplier_calls','database_writes','profile_writes','mapping_writes','schema_writes','lead_calls','booking_calls','rollback_attempts'):need(type(result.get(k)) is int and result[k]==0,'receipt_counters')
  for k in ('root_write_attempts','file_writes'):need(type(result.get(k)) is int and result[k]==1,'receipt_writes')
  need(result.get('written_files')==['api-v2.php'] and result.get('backup_retained') is True,'receipt_scope')
  need(all(result.get('syntax_'+phase)=='ok' for phase in ('previous','candidate','installed')),'receipt_syntax')
  before,after=result['before_files'],result['after_files']
  for values in (before,after):
   need(set(values)==set(CHECKED_EXPECTED),'receipt_inventory')
   for record in values.values():
    need(set(record)=={'sha256','bytes','mode'} and re.fullmatch(r'[a-f0-9]{64}',record['sha256']) is not None,'receipt_record')
    need(type(record['bytes']) is int and 0<record['bytes']<=2*1024*1024 and type(record['mode']) is int and record['mode']==0o644,'receipt_metadata')
  need(all(before[p]['sha256']==h for p,h in CHECKED_EXPECTED.items()),'receipt_predecessor')
  expected=dict(before);expected['api-v2.php']={'sha256':CHECKED_HASH,'bytes':CHECKED_BYTES,'mode':0o644}
  need(after==expected,'receipt_after')
  need(set(result['production_before'])==set(CHECKED_PROTECTED) and result['production_before']==result.get('production_after'),'receipt_public')
  need(all(v is None or isinstance(v,str) and re.fullmatch(r'[a-f0-9]{64}',v) is not None for v in result['production_before'].values()),'receipt_public_hashes')
  need('error_type' not in result and 'reason' not in result,'receipt_error')
  return True
 except (RuntimeError,AttributeError,KeyError,TypeError,ValueError):return False

def checked_install():
 import shlex
 work=pathlib.Path(os.environ['RUNNER_TEMP'])/'search3-root-gateway-checked';work.mkdir()
 evidence={'status':'blocked_no_replay','operation_id':CHECKED_OPERATION,'source_sha':CHECKED_SOURCE,'release_sha':CHECKED_RELEASE,'source_blob':CHECKED_BLOB}
 key,known=work/'key',work/'known';completed=False
 try:
  identity=checked_event(json.loads(pathlib.Path(os.environ['GITHUB_EVENT_PATH']).read_text()),os.environ)
  evidence.update(identity);candidate=checked_admission(identity)
  (work/'api-v2.php').write_bytes(candidate)
  (work/'request.json').write_text(json.dumps({**identity,'operation_id':CHECKED_OPERATION,'source_sha':CHECKED_SOURCE,'release_sha':CHECKED_RELEASE,'source_blob':CHECKED_BLOB,'candidate_sha256':CHECKED_HASH},sort_keys=True))
  payload={**identity,'candidate_b64':base64.b64encode(candidate).decode()};program=checked_remote()
  host,user=os.environ['PREVIEW_HOST'],os.environ['PREVIEW_USER']
  need(re.fullmatch(r'[A-Za-z0-9][A-Za-z0-9.-]*',host) and re.fullmatch(r'[A-Za-z0-9_][A-Za-z0-9_.-]{0,63}',user),'ssh_identity')
  raw=os.environ['PREVIEW_KEY'].replace('\r','')
  if 'PRIVATE KEY' not in raw:raw=base64.b64decode(raw,validate=True).decode()
  key.write_text(raw.rstrip()+'\n');key.chmod(0o600)
  known.write_bytes(run(['ssh-keyscan','-T','15','-t','ed25519',host]));known.chmod(0o600)
  opts=['-i',str(key),'-o','StrictHostKeyChecking=yes','-o','UserKnownHostsFile='+str(known),'-o','GlobalKnownHostsFile=/dev/null','-o','BatchMode=yes','-o','ConnectTimeout=15']
  evidence['status']='unknown_no_replay'
  response=run(['ssh',*opts,user+'@'+host,'python3 -c '+shlex.quote(program)],input=json.dumps(payload).encode(),timeout=90)
  need(len(response)<=16384,'receipt_limit');result=json.loads(response)
  need(checked_safe_receipt(identity,result),'receipt_unsafe');evidence['remote']=result
  completed=checked_completed(identity,result);need(completed,'install_not_verified')
  evidence['status']='installed_verified'
 except Exception as exc:evidence['error_type']=type(exc).__name__
 finally:
  try:(work/'evidence.json').write_text(json.dumps(evidence,sort_keys=True))
  finally:key.unlink(missing_ok=True);known.unlink(missing_ok=True)
 print(json.dumps(evidence,sort_keys=True));need(completed,'install_not_verified')

# Separately approved dependency-reuse installation. Frozen earlier operations above
# remain available only under their original exact commands and no-replay guards.
DEPENDENCY_PREFIX='/install-search3-root-dependencies'
DEPENDENCY_OPERATION='install-20261019-dependency-reuse-v1'
DEPENDENCY_SOURCE='d6780854c16f390ec9ad3117a484e72e0851c884'
DEPENDENCY_RELEASE='4ff952d8d5c8a943a7ba6b3af78e06f206e1e25e'
DEPENDENCY_APPROVAL=6263435322
DEPENDENCY_APPROVAL_HASH='68502f397f771f6d757c943372bba0671e6a0f83d402047c4429ddf465acd7c2'
DEPENDENCY_CHECK_IDS={117963783814,117963785119,117963784790,117963784645,117963784614,117963784447,117963784155}
DEPENDENCY_LOCAL_SOURCE='8bb7336cfa8e6e702ea656c762fe331a1c8283c6'
DEPENDENCY_LOCAL_TREE='f805a7fdadf2ff351aa4351ec489058d25cbdcaa'
DEPENDENCY_LOCAL_PARENT='8af3e190588bf61b067f81838aec3f0201205ec9'
DEPENDENCY_COLLECTORS={'.github/workflows/collect-hotel-details.yml':'f00d9986655f56ce1a5dcfc68dd681b199abe018','.github/workflows/collect-anytour-tours.yml':'9c8e7f8d3747c5f628353f1bb56e1b57d596551a'}
DEPENDENCY_CANDIDATES={
 'api-v2.php':{'source':DEPENDENCY_SOURCE,'path':'v2/api-v2.php','blob':'72d4845f7074584db34b2d67c481f81ea64f4a49','bytes':14995,'sha256':'2d92cba5cee08d4d112255ebcddf0cd0f4b023a5233761ace27453337385e3be'},
 'data/local-tv-catalog-v1.php':{'source':DEPENDENCY_LOCAL_SOURCE,'path':'v2/data/local-tv-catalog-v1.php','blob':'b4ac8e188122e6a3df78eae64f6a4cb41a182f32','bytes':36000,'sha256':'9dc60eb39100126d97307b83083d6a1b190cfdf8f684a18af47d8edd87b1c3b5'},
}
DEPENDENCY_EXPECTED={**CHECKED_EXPECTED,'api-v2.php':CHECKED_HASH}
DEPENDENCY_APP='_preview/search3-next-candidate/visual-search/app.js'
DEPENDENCY_APP_HASH='1772a1216ca111c1a1a9a2a5cbed0c14dc589674fe5d2bd0016171c2eb2a2f4b'
DEPENDENCY_PROTECTED=(*CHECKED_PROTECTED,'config.php',DEPENDENCY_APP)
DEPENDENCY_BLOCKED_CONTROL='9a3dd29670390aca00243b4b9e68098f90e005d5'
DEPENDENCY_BLOCKED_COMMAND=6263652341
DEPENDENCY_BLOCKED_RUN=39227575241
DEPENDENCY_BLOCKED_JOB=117977506793
DEPENDENCY_BLOCKED_ARTIFACT=12175215848
DEPENDENCY_BLOCKED_DIGEST='sha256:703f6d603c3b0afc82f47d8338a6415e349c881730ba3d0fd4cf4cba5b05e758'

def dependency_green_check(head,name,reason):
 # GitHub attaches every issue-comment job to main. Name filtering retrieves
 # the exact security guard even after unrelated jobs fill the first page.
 checks=checked_api('/commits/'+head+'/check-runs?check_name='+name+'&per_page=100')
 rows=checks.get('check_runs');total=checks.get('total_count')
 need(type(total) is int and 0<=total<=100 and isinstance(rows,list) and len(rows)==total,'checks_incomplete')
 need(all(isinstance(c,dict) and type(c.get('id')) is int and c['id']>0 for c in rows)
      and len({c['id'] for c in rows})==len(rows),'checks_shape')
 need(len(rows)==1 and rows[0].get('name')==name and rows[0].get('head_sha')==head
      and rows[0].get('status')=='completed' and rows[0].get('conclusion')=='success'
      and rows[0].get('app',{}).get('slug')=='github-actions',reason)

def dependency_pre_ssh_blocked_proof():
 # This one immutable retained ZIP was independently downloaded and reviewed:
 # evidence.json only, before request/candidates were written and before SSH.
 # Every reserved, unknown or runtime-started operation keeps its no-replay lock.
 old=checked_api('/issues/comments/'+str(DEPENDENCY_BLOCKED_COMMAND))
 need(old.get('id')==DEPENDENCY_BLOCKED_COMMAND and old.get('user',{}).get('id')==226193297 and old.get('author_association')=='OWNER'
      and old.get('body')==f'{DEPENDENCY_PREFIX} {DEPENDENCY_BLOCKED_CONTROL} {DEPENDENCY_OPERATION}','blocked_command_proof')
 run=checked_api('/actions/runs/'+str(DEPENDENCY_BLOCKED_RUN))
 need(run.get('id')==DEPENDENCY_BLOCKED_RUN and type(run.get('run_attempt')) is int and run['run_attempt']==1
      and run.get('event')=='issue_comment' and run.get('path')==CHECKED_WORKFLOW and run.get('head_sha')==DEPENDENCY_BLOCKED_CONTROL
      and run.get('status')=='completed' and run.get('conclusion')=='failure','blocked_run_proof')
 job=checked_api('/actions/jobs/'+str(DEPENDENCY_BLOCKED_JOB))
 need(job.get('id')==DEPENDENCY_BLOCKED_JOB and job.get('run_id')==DEPENDENCY_BLOCKED_RUN and type(job.get('run_attempt')) is int and job['run_attempt']==1
      and job.get('name')=='dependency-install' and job.get('head_sha')==DEPENDENCY_BLOCKED_CONTROL
      and job.get('status')=='completed' and job.get('conclusion')=='failure','blocked_job_proof')
 artifact=checked_api('/actions/artifacts/'+str(DEPENDENCY_BLOCKED_ARTIFACT))
 need(artifact.get('id')==DEPENDENCY_BLOCKED_ARTIFACT and artifact.get('name')=='search3-root-gateway-dependencies-39227575241-1'
      and type(artifact.get('size_in_bytes')) is int and artifact['size_in_bytes']==416 and artifact.get('digest')==DEPENDENCY_BLOCKED_DIGEST
      and artifact.get('expired') is False and artifact.get('workflow_run',{}).get('id')==DEPENDENCY_BLOCKED_RUN
      and artifact.get('workflow_run',{}).get('head_sha')==DEPENDENCY_BLOCKED_CONTROL,'blocked_artifact_proof')

def dependency_event(event,env):
 # Reuse all current-owner/main/stock-workflow validation without changing the
 # historical checked command or its fixed installation identity.
 import copy
 changed=copy.deepcopy(event);control=env.get('GITHUB_SHA','')
 need(changed.get('comment',{}).get('body')==f'{DEPENDENCY_PREFIX} {control} {DEPENDENCY_OPERATION}','command')
 changed['comment']['body']=f'{CHECKED_PREFIX} {control} {CHECKED_OPERATION}'
 return checked_event(changed,env)

def dependency_admission(identity):
 need(checked_api('/git/ref/heads/main')['object']['sha']==identity['control_source_sha'],'main_moved')
 need(checked_api('/git/ref/heads/release/search3-production-ready-v1')['object']['sha']==DEPENDENCY_RELEASE,'release_moved')
 approval=checked_api('/issues/comments/'+str(DEPENDENCY_APPROVAL))
 need(approval.get('id')==DEPENDENCY_APPROVAL and approval.get('user',{}).get('id')==226193297
      and approval.get('author_association')=='OWNER'
      and sha(approval.get('body','').encode())==DEPENDENCY_APPROVAL_HASH,'approval_changed')
 control=checked_api('/git/commits/'+identity['control_source_sha'])
 need(len(control['parents'])==2,'reviewed_merge_required')
 reviewed=checked_api('/git/commits/'+control['parents'][1]['sha'])
 need(reviewed['tree']['sha']==control['tree']['sha'],'reviewed_tree')
 for head,names,reason in ((reviewed['sha'],('guard','contracts','live-check-contracts'),'source_ci'),(identity['control_source_sha'],('guard',),'main_ci')):
  for name in names:dependency_green_check(head,name,reason)
 candidates={}
 for target,record in DEPENDENCY_CANDIDATES.items():
  item=checked_api('/contents/'+record['path']+'?ref='+record['source'])
  data=base64.b64decode(item['content'],validate=False)
  need(item['sha']==record['blob'] and blob(data)==record['blob'] and sha(data)==record['sha256'] and len(data)==record['bytes'],'candidate_bytes')
  if target=='api-v2.php':need(checked_api('/contents/'+record['path']+'?ref='+DEPENDENCY_RELEASE)['sha']==record['blob'],'release_candidate')
  candidates[target]=data
 checks=checked_api('/commits/'+DEPENDENCY_SOURCE+'/check-runs?per_page=100')
 selected=[c for c in checks['check_runs'] if c['id'] in DEPENDENCY_CHECK_IDS]
 need({c['id'] for c in selected}==DEPENDENCY_CHECK_IDS and len(selected)==len(DEPENDENCY_CHECK_IDS),'source_checks')
 need(all(c.get('status')=='completed' and c.get('conclusion')=='success' and c.get('app',{}).get('slug')=='github-actions' for c in selected),'source_checks')
 delivery=checked_api('/git/commits/'+DEPENDENCY_LOCAL_SOURCE)
 need(delivery['tree']['sha']==DEPENDENCY_LOCAL_TREE and [p['sha'] for p in delivery['parents']]==[DEPENDENCY_LOCAL_PARENT],'delivery_identity')
 difference=checked_api('/compare/'+DEPENDENCY_LOCAL_PARENT+'...'+DEPENDENCY_LOCAL_SOURCE)
 need(difference.get('status')=='ahead' and difference.get('ahead_by')==1 and difference.get('behind_by')==0
      and difference.get('total_commits')==1 and len(difference.get('files',[]))==1
      and difference['files'][0].get('filename')=='v2/data/local-tv-catalog-v1.php'
      and difference['files'][0].get('status')=='modified'
      and difference['files'][0].get('sha')==DEPENDENCY_CANDIDATES['data/local-tv-catalog-v1.php']['blob'],'delivery_scope')
 for path,expected_blob in DEPENDENCY_COLLECTORS.items():
  item=checked_api('/contents/'+path+'?ref='+identity['control_source_sha'])
  body=base64.b64decode(item['content'],validate=False)
  need(blob(body)==item['sha']==expected_blob and DEPENDENCY_LOCAL_SOURCE.encode() in body,'collector_source')
 matched=[]
 for page in range(1,31):
  comments=checked_api(f'/issues/4217/comments?per_page=100&page={page}');need(isinstance(comments,list),'comments_shape')
  matched.extend(c['id'] for c in comments if c.get('body','').strip().startswith(DEPENDENCY_PREFIX+' ') or DEPENDENCY_OPERATION in c.get('body','') and c.get('body','').strip().startswith('/'))
  if len(comments)<100:
   need(matched==[DEPENDENCY_BLOCKED_COMMAND,identity['command_comment_id']] and identity['command_comment_id']!=DEPENDENCY_BLOCKED_COMMAND,'operation_already_reserved')
   dependency_pre_ssh_blocked_proof()
   fresh=checked_api('/issues/comments/'+str(identity['command_comment_id']))
   need(fresh.get('id')==identity['command_comment_id'] and fresh.get('user',{}).get('id')==226193297 and fresh.get('author_association')=='OWNER'
        and fresh.get('body')==f'{DEPENDENCY_PREFIX} '+identity['control_source_sha']+' '+DEPENDENCY_OPERATION,'command_changed')
   return candidates
 raise RuntimeError('comment_history_incomplete')

def dependency_remote():
 import ast
 body=pathlib.Path(__file__).with_name('int_server_executor_local_tv_capture.py').read_bytes()
 need(sha(body)==CHECKED_READER_HASH,'reader_source_drift')
 module=ast.parse(body)
 remote=ast.literal_eval(next(n.value for n in module.body if isinstance(n,ast.Assign) and any(isinstance(t,ast.Name) and t.id=='REMOTE_REPAIR' for t in n.targets)))
 reader=next(n for n in ast.parse(remote).body if isinstance(n,ast.FunctionDef) and n.name=='repair_read')
 code=ast.get_source_segment(remote,reader);need(code is not None,'reader_missing')
 policy={'operation':DEPENDENCY_OPERATION,'source':DEPENDENCY_SOURCE,'release':DEPENDENCY_RELEASE,
         'local_source':DEPENDENCY_LOCAL_SOURCE,'candidates':DEPENDENCY_CANDIDATES,'expected':DEPENDENCY_EXPECTED,
         'protected':DEPENDENCY_PROTECTED,'app':DEPENDENCY_APP,'app_hash':DEPENDENCY_APP_HASH}
 return 'POLICY='+repr(policy)+'\n'+code+'\n'+REMOTE_DEPENDENCY_INSTALL

REMOTE_DEPENDENCY_INSTALL=r'''
import base64,hashlib,json,os,pathlib,re,stat,subprocess,sys
def fail(reason):raise RuntimeError(reason)
project=pathlib.Path.home()/'www/anytoour.ru'
REPAIR_POLICY={'good':dict.fromkeys((*POLICY['expected'],*POLICY['protected']))}
def identity(path):
 s=path.lstat();return (s.st_dev,s.st_ino,s.st_mode,s.st_nlink,s.st_size,s.st_mtime_ns,s.st_ctime_ns)
def checked_read(path):
 first=identity(project/path);body,record=repair_read(path)
 if identity(project/path)!=first:fail('file_changed')
 return body,record,first
def snapshot():
 records={};identities={};bodies={}
 for path in POLICY['expected']:bodies[path],records[path],identities[path]=checked_read(path)
 return records,identities,bodies
def protected():
 values={};identities={}
 for path in POLICY['protected']:
  try:_,record,identities[path]=checked_read(path);values[path]=record['sha256']
  except FileNotFoundError:
   if path in ('config.php',POLICY['app']):raise
   for parent in (project/path).parents:
    if parent==project.parent:break
    if parent.is_symlink():fail('unsafe_parent')
   values[path]=None;identities[path]=None
 return values,identities
def write_private(path,body,mode=0o600):
 fd=os.open(path,os.O_WRONLY|os.O_CREAT|os.O_EXCL|os.O_NOFOLLOW,mode)
 with os.fdopen(fd,'wb') as f:os.fchmod(f.fileno(),mode);f.write(body);f.flush();os.fsync(f.fileno())
def lint(path):
 result=subprocess.run(['php','-n','-l',str(path)],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL,timeout=15,check=False,env={'PATH':'/usr/bin:/bin','HOME':str(pathlib.Path.home())})
 if result.returncode:fail('syntax_failed')
def sync(path):
 fd=os.open(path,os.O_RDONLY|os.O_DIRECTORY|os.O_NOFOLLOW)
 try:os.fsync(fd)
 finally:os.close(fd)
def current_guard(records,identities,public,public_ids):
 fresh,fresh_ids,_=snapshot();p,pids=protected()
 if fresh!=records or fresh_ids!=identities or p!=public or pids!=public_ids:fail('file_changed')
data={'schema_version':1,'operation_id':POLICY['operation'],'source_sha':POLICY['source'],'release_sha':POLICY['release'],
      'local_source_sha':POLICY['local_source'],'status':'blocked_no_replay',
      'supplier_calls':0,'database_writes':0,'profile_writes':0,'mapping_writes':0,'schema_writes':0,'lead_calls':0,'booking_calls':0,
      'root_write_attempts':0,'file_writes':0,'rollback_attempts':0,'written_files':[],'rollback_files':[],
      'compatible_catalogue':False,'before_files':{},'after_files':{},'syntax_previous':'not_run','syntax_candidate':'not_run',
      'syntax_installed':'not_run','backup_retained':False}
reserved=False;op=None;owned={};old={};planned=[]
try:
 raw=sys.stdin.buffer.read(131073)
 if len(raw)>131072:fail('payload_limit')
 payload=json.loads(raw)
 if set(payload)!={'control_source_sha','run_id','command_comment_id','candidates_b64'}:fail('payload_fields')
 if (re.fullmatch(r'[a-f0-9]{40}',str(payload['control_source_sha'])) is None or re.fullmatch(r'[1-9][0-9]{0,19}',str(payload['run_id'])) is None or type(payload['command_comment_id']) is not int or payload['command_comment_id']<=0):fail('payload_identity')
 data.update({k:payload[k] for k in ('control_source_sha','run_id','command_comment_id')})
 if not isinstance(payload['candidates_b64'],dict) or set(payload['candidates_b64'])!=set(POLICY['candidates']):fail('candidate_fields')
 candidates={}
 for path,record in POLICY['candidates'].items():
  body=base64.b64decode(payload['candidates_b64'][path],validate=True)
  if len(body)!=record['bytes'] or hashlib.sha256(body).hexdigest()!=record['sha256'] or hashlib.sha1(b'blob '+str(len(body)).encode()+b'\0'+body).hexdigest()!=record['blob']:fail('candidate_bytes')
  candidates[path]=body
 home=pathlib.Path.home();private=home/'.anytoour-search3-root-gateway'
 if home.resolve()!=home or home.is_symlink() or not stat.S_ISDIR(home.lstat().st_mode):fail('unsafe_private_parent')
 try:private.mkdir(mode=0o700)
 except FileExistsError:pass
 p=private.lstat()
 if not stat.S_ISDIR(p.st_mode) or stat.S_IMODE(p.st_mode)!=0o700 or p.st_uid!=os.getuid():fail('unsafe_private_parent')
 op=private/POLICY['operation']
 try:op.mkdir(mode=0o700)
 except FileExistsError:fail('operation_exists_no_replay')
 reserved=True
 write_private(op/'reservation.json',json.dumps({k:data[k] for k in ('operation_id','source_sha','release_sha','local_source_sha','control_source_sha','run_id','command_comment_id')},sort_keys=True).encode())
 public,public_ids=protected();data['production_before']=public
 if public[POLICY['app']]!=POLICY['app_hash']:fail('frontend_drift')
 before,ids,old=snapshot();data['before_files']=before
 catalogue='data/local-tv-catalog-v1.php'
 data['compatible_catalogue']=before[catalogue]['sha256']==POLICY['candidates'][catalogue]['sha256']
 for path,digest in POLICY['expected'].items():
  if before[path]['mode']!=0o644 or before[path]['sha256']!=digest and not (path==catalogue and data['compatible_catalogue']):fail('predecessor_drift')
 if project.stat().st_dev!=op.stat().st_dev or (project/'data').stat().st_dev!=op.stat().st_dev:fail('different_filesystem')
 planned=([catalogue] if not data['compatible_catalogue'] else [])+['api-v2.php']
 stages={};stage_ids={}
 for n,path in enumerate(planned):
  stages[path]=op/('candidate-'+str(n)+'.php')
  write_private(stages[path],candidates[path],0o644);stage_ids[path]=identity(stages[path])
  write_private(op/('previous-'+str(n)+'.php'),old[path])
 data['backup_retained']=True
 for path in POLICY['candidates']:lint(project/path)
 data['syntax_previous']='ok'
 for path in planned:lint(stages[path])
 data['syntax_candidate']='ok'
 current=dict(before);current_ids=dict(ids)
 for path in planned:
  current_guard(current,current_ids,public,public_ids)
  if identity(stages[path])!=stage_ids[path]:fail('file_changed')
  data['root_write_attempts']+=1
  os.replace(stages[path],project/path);data['file_writes']+=1;data['written_files'].append(path)
  # Ownership follows the staged inode, never merely the body or a racing path.
  installed=identity(project/path)
  if installed[:2]!=stage_ids[path][:2]:fail('installed_identity')
  owned[path]=installed;sync((project/path).parent)
  current[path]={'sha256':POLICY['candidates'][path]['sha256'],'bytes':POLICY['candidates'][path]['bytes'],'mode':0o644};current_ids[path]=installed
  current_guard(current,current_ids,public,public_ids)
 for path in POLICY['candidates']:lint(project/path)
 data['syntax_installed']='ok'
 current_guard(current,current_ids,public,public_ids)
 data['after_files']=snapshot()[0];data['production_after']=protected()[0]
 data['status']='installed_verified'
except Exception as exc:
 allowed={'payload_limit','payload_fields','payload_identity','candidate_fields','candidate_bytes','unsafe_private_parent','operation_exists_no_replay','predecessor_drift','different_filesystem','syntax_failed','file_changed','installed_identity','unsafe_parent','frontend_drift'}
 data.update(reason=str(exc) if type(exc) is RuntimeError and str(exc) in allowed else 'operation_failed',error_type=type(exc).__name__)
 if data['root_write_attempts']:
  data['status']='unknown_no_replay'
  for n,path in enumerate(reversed(data['written_files'])):
   try:
    _,record,now_id=checked_read(path)
    if path not in owned or now_id!=owned[path] or record['sha256']!=POLICY['candidates'][path]['sha256']:continue
    rollback=op/('rollback-'+str(n)+'.php');write_private(rollback,old[path],0o644)
    if identity(project/path)!=owned[path]:continue
    data['rollback_attempts']+=1;os.replace(rollback,project/path);data['file_writes']+=1;sync((project/path).parent)
    if checked_read(path)[1]['sha256']==hashlib.sha256(old[path]).hexdigest():data['rollback_files'].append(path)
   except Exception:pass
  try:
   data['after_files']=snapshot()[0];data['production_after']=protected()[0]
   if set(data['rollback_files'])==set(data['written_files']) and data['after_files']==data['before_files'] and data['production_after']==data['production_before']:data['status']='rolled_back_no_replay'
  except Exception:pass
finally:
 if reserved:
  try:write_private(op/'result.json',json.dumps(data,sort_keys=True).encode())
  except Exception:data['status']='unknown_no_replay';data['reason']='private_receipt_failed'
 print(json.dumps(data,sort_keys=True))
'''

def dependency_safe_receipt(identity,result):
 try:
  counters=('supplier_calls','database_writes','profile_writes','mapping_writes','schema_writes','lead_calls','booking_calls')
  required={'schema_version','operation_id','source_sha','release_sha','local_source_sha','status',*counters,'root_write_attempts','file_writes','rollback_attempts','written_files','rollback_files','compatible_catalogue','before_files','after_files','syntax_previous','syntax_candidate','syntax_installed','backup_retained'}
  allowed=required|set(identity)|{'production_before','production_after','reason','error_type'}
  need(isinstance(result,dict) and required<=set(result)<=allowed,'receipt_fields')
  need(type(result['schema_version']) is int and result['schema_version']==1,'receipt_schema')
  need(result['status'] in ('blocked_no_replay','unknown_no_replay','rolled_back_no_replay','installed_verified'),'receipt_status')
  for k,v in {'operation_id':DEPENDENCY_OPERATION,'source_sha':DEPENDENCY_SOURCE,'release_sha':DEPENDENCY_RELEASE,'local_source_sha':DEPENDENCY_LOCAL_SOURCE}.items():need(result[k]==v,'receipt_identity')
  for k,v in identity.items():need(k not in result or result[k]==v,'receipt_identity')
  for k in counters:need(type(result[k]) is int and result[k]==0,'receipt_counters')
  for k,maximum in (('root_write_attempts',2),('file_writes',4),('rollback_attempts',2)):need(type(result[k]) is int and 0<=result[k]<=maximum,'receipt_writes')
  need(type(result['compatible_catalogue']) is bool and type(result['backup_retained']) is bool,'receipt_flags')
  for key in ('written_files','rollback_files'):need(isinstance(result[key],list) and len(result[key])==len(set(result[key])) and set(result[key])<=set(DEPENDENCY_CANDIDATES),'receipt_scope')
  need(set(result['rollback_files'])<=set(result['written_files']),'receipt_scope')
  need(all(result['syntax_'+p] in ('not_run','ok') for p in ('previous','candidate','installed')),'receipt_syntax')
  for phase in ('before_files','after_files'):
   need(isinstance(result[phase],dict) and set(result[phase])<=set(DEPENDENCY_EXPECTED),'receipt_inventory')
   for record in result[phase].values():
    need(set(record)=={'sha256','bytes','mode'} and isinstance(record['sha256'],str) and re.fullmatch('[a-f0-9]{64}',record['sha256']) is not None,'receipt_record')
    need(type(record['bytes']) is int and 0<record['bytes']<=2*1024*1024 and type(record['mode']) is int and 0<=record['mode']<=0o7777,'receipt_metadata')
  for phase in ('production_before','production_after'):
   if phase in result:
    need(set(result[phase])==set(DEPENDENCY_PROTECTED),'receipt_public')
    need(all(v is None or isinstance(v,str) and re.fullmatch('[a-f0-9]{64}',v) is not None for v in result[phase].values()),'receipt_public_hashes')
  if 'reason' in result:need(result['reason'] in ('payload_limit','payload_fields','payload_identity','candidate_fields','candidate_bytes','unsafe_private_parent','operation_exists_no_replay','predecessor_drift','different_filesystem','syntax_failed','file_changed','installed_identity','unsafe_parent','frontend_drift','operation_failed','private_receipt_failed'),'receipt_reason')
  if 'error_type' in result:need(result['error_type'] in ('RuntimeError','FileNotFoundError','PermissionError','OSError','TimeoutExpired','JSONDecodeError','Error','ValueError'),'receipt_error_type')
  return True
 except (RuntimeError,AttributeError,KeyError,TypeError,ValueError):return False

def dependency_completed(identity,result):
 try:
  need(dependency_safe_receipt(identity,result) and result['status']=='installed_verified','receipt_status')
  for k,v in identity.items():need(result.get(k)==v,'receipt_identity')
  planned=([] if result['compatible_catalogue'] else ['data/local-tv-catalog-v1.php'])+['api-v2.php']
  need(result['written_files']==planned and result['rollback_files']==[] and result['root_write_attempts']==result['file_writes']==len(planned) and result['rollback_attempts']==0,'receipt_writes')
  need(result['backup_retained'] and all(result['syntax_'+p]=='ok' for p in ('previous','candidate','installed')),'receipt_syntax')
  before,after=result['before_files'],result['after_files'];need(set(before)==set(after)==set(DEPENDENCY_EXPECTED),'receipt_inventory')
  for path,h in DEPENDENCY_EXPECTED.items():
   need(before[path]['mode']==0o644 and before[path]['sha256']==(DEPENDENCY_CANDIDATES[path]['sha256'] if path=='data/local-tv-catalog-v1.php' and result['compatible_catalogue'] else h),'receipt_predecessor')
  expected=dict(before)
  for path,record in DEPENDENCY_CANDIDATES.items():expected[path]={'sha256':record['sha256'],'bytes':record['bytes'],'mode':0o644}
  need(after==expected,'receipt_after')
  need(result['production_before']==result['production_after'] and result['production_before'][DEPENDENCY_APP]==DEPENDENCY_APP_HASH and result['production_before']['config.php'] is not None,'receipt_public')
  need('reason' not in result and 'error_type' not in result,'receipt_error')
  return True
 except (RuntimeError,AttributeError,KeyError,TypeError,ValueError):return False

def dependency_install():
 import shlex
 work=pathlib.Path(os.environ['RUNNER_TEMP'])/'search3-root-gateway-dependencies';work.mkdir()
 evidence={'status':'blocked_no_replay','operation_id':DEPENDENCY_OPERATION,'source_sha':DEPENDENCY_SOURCE,'release_sha':DEPENDENCY_RELEASE,'local_source_sha':DEPENDENCY_LOCAL_SOURCE}
 key,known=work/'key',work/'known';completed=False
 try:
  identity=dependency_event(json.loads(pathlib.Path(os.environ['GITHUB_EVENT_PATH']).read_text()),os.environ)
  evidence.update(identity);candidates=dependency_admission(identity)
  (work/'api-v2.php').write_bytes(candidates['api-v2.php']);(work/'local-tv-catalog-v1.php').write_bytes(candidates['data/local-tv-catalog-v1.php'])
  (work/'request.json').write_text(json.dumps({**identity,'operation_id':DEPENDENCY_OPERATION,'source_sha':DEPENDENCY_SOURCE,'release_sha':DEPENDENCY_RELEASE,'local_source_sha':DEPENDENCY_LOCAL_SOURCE,'candidates':DEPENDENCY_CANDIDATES},sort_keys=True))
  program=dependency_remote();payload={**identity,'candidates_b64':{p:base64.b64encode(b).decode() for p,b in candidates.items()}}
  host,user=os.environ['PREVIEW_HOST'],os.environ['PREVIEW_USER']
  need(re.fullmatch(r'[A-Za-z0-9][A-Za-z0-9.-]*',host) and re.fullmatch(r'[A-Za-z0-9_][A-Za-z0-9_.-]{0,63}',user),'ssh_identity')
  raw=os.environ['PREVIEW_KEY'].replace('\r','')
  if 'PRIVATE KEY' not in raw:raw=base64.b64decode(raw,validate=True).decode()
  key.write_text(raw.rstrip()+'\n');key.chmod(0o600)
  known.write_bytes(run(['ssh-keyscan','-T','15','-t','ed25519',host]));known.chmod(0o600)
  opts=['-i',str(key),'-o','StrictHostKeyChecking=yes','-o','UserKnownHostsFile='+str(known),'-o','GlobalKnownHostsFile=/dev/null','-o','BatchMode=yes','-o','ConnectTimeout=15']
  evidence['status']='unknown_no_replay'
  response=run(['ssh',*opts,user+'@'+host,'python3 -c '+shlex.quote(program)],input=json.dumps(payload).encode(),timeout=180)
  need(len(response)<=24576,'receipt_limit');result=json.loads(response)
  need(dependency_safe_receipt(identity,result),'receipt_unsafe');evidence['remote']=result
  completed=dependency_completed(identity,result);need(completed,'install_not_verified');evidence['status']='installed_verified'
 except Exception as exc:
  evidence['error_type']=type(exc).__name__
  reasons={'main_moved','release_moved','approval_changed','reviewed_merge_required','reviewed_tree','source_ci','main_ci','checks_incomplete','checks_shape','candidate_bytes','release_candidate','source_checks','delivery_identity','delivery_scope','collector_source','comments_shape','operation_already_reserved','blocked_command_proof','blocked_run_proof','blocked_job_proof','blocked_artifact_proof','command_changed','comment_history_incomplete','reader_source_drift','reader_missing','receipt_limit','receipt_unsafe','install_not_verified','ssh_identity'}
  if type(exc) is RuntimeError and str(exc) in reasons:evidence['reason']=str(exc)
 finally:
  try:(work/'evidence.json').write_text(json.dumps(evidence,sort_keys=True))
  finally:key.unlink(missing_ok=True);known.unlink(missing_ok=True)
 print(json.dumps(evidence,sort_keys=True));need(completed,'install_not_verified')

# New API-only diagnostic source. Historical operations and their policies above
# stay sealed; this policy reuses only the existing API transaction algorithm.
TV_DIAGNOSTIC_PREFIX='/install-search3-root-tv-diagnostics'
TV_DIAGNOSTIC_OPERATION='install-20261020-tv-diagnostics-v1'
TV_DIAGNOSTIC_SOURCE='19694ed619b57001f32b81c793d862a574a47e0e'
TV_DIAGNOSTIC_RELEASE='6351e328588317b0538ff0c668c972ae955c7d4f'
TV_DIAGNOSTIC_BLOB='8cce9fb633d0435fac1a22f6be24e38299c72ed4'
TV_DIAGNOSTIC_HASH='01ac5ed43961f73879cd36c2c1d387ac79fadaf53ad66d3c4aaebcc617464c61'
TV_DIAGNOSTIC_BYTES=16366
TV_DIAGNOSTIC_CHECK_IDS={118605478927,118605478621,118605478375,118605478212}
TV_DIAGNOSTIC_EXPECTED={**CHECKED_EXPECTED,
 'api-v2.php':'2d92cba5cee08d4d112255ebcddf0cd0f4b023a5233761ace27453337385e3be',
 'data/local-tv-catalog-v1.php':'9dc60eb39100126d97307b83083d6a1b190cfdf8f684a18af47d8edd87b1c3b5'}
TV_DIAGNOSTIC_PROTECTED=DEPENDENCY_PROTECTED
TV_DIAGNOSTIC_CONTROL_PATHS=(CHECKED_WORKFLOW,'scripts/deploy/search3_root_gateway_install.py','tests/search3_root_gateway_install_test.py')
TV_DIAGNOSTIC_CONTROL_DEPTH=16

def tv_diagnostic_event(event,env):
 need(env.get('GITHUB_REPOSITORY')==REPO and event.get('repository',{}).get('full_name')==REPO,'repository')
 need(env.get('GITHUB_REF')=='refs/heads/main' and env.get('GITHUB_WORKFLOW_REF')==f'{REPO}/{CHECKED_WORKFLOW}@refs/heads/main','workflow')
 need(env.get('GITHUB_ACTOR')==env.get('GITHUB_TRIGGERING_ACTOR')=='pyatkoff','actor')
 need(env.get('GITHUB_EVENT_NAME')=='issue_comment' and event.get('action')=='created' and env.get('GITHUB_RUN_ATTEMPT')=='1','event')
 issue,comment=event.get('issue',{}),event.get('comment',{})
 need(issue.get('number')==4709 and 'pull_request' not in issue,'issue')
 need(comment.get('user',{}).get('id')==226193297 and comment.get('author_association')=='OWNER','owner')
 control,run_id=env.get('GITHUB_SHA',''),env.get('GITHUB_RUN_ID','')
 need(re.fullmatch(r'[a-f0-9]{40}',control) is not None and re.fullmatch(r'[1-9][0-9]{0,19}',run_id) is not None,'identity')
 need(type(comment.get('id')) is int and comment['id']>0,'comment_id')
 need(comment.get('body')==f'{TV_DIAGNOSTIC_PREFIX} {control} {TV_DIAGNOSTIC_OPERATION}','command')
 return {'control_source_sha':control,'run_id':run_id,'command_comment_id':comment['id']}

def _single_file_reviewed_control(control_sha,required_checks):
 def owned_blobs(head):
  values={}
  for path in TV_DIAGNOSTIC_CONTROL_PATHS:
   item=checked_api('/contents/'+path+'?ref='+head);digest=item.get('sha')
   need(item.get('type')=='file' and isinstance(digest,str) and re.fullmatch(r'[a-f0-9]{40}',digest) is not None,'control_owned_blob')
   values[path]=digest
  return values
 current=owned_blobs(control_sha);head=control_sha;seen=set()
 for _ in range(TV_DIAGNOSTIC_CONTROL_DEPTH):
  need(isinstance(head,str) and re.fullmatch(r'[a-f0-9]{40}',head) is not None and head not in seen,'control_ancestry')
  seen.add(head);commit=checked_api('/git/commits/'+head);parents=commit.get('parents');tree=commit.get('tree',{}).get('sha')
  need(commit.get('sha')==head and isinstance(tree,str) and re.fullmatch(r'[a-f0-9]{40}',tree) is not None
       and isinstance(parents,list) and len(parents)<=2
       and all(isinstance(p,dict) and isinstance(p.get('sha'),str) and re.fullmatch(r'[a-f0-9]{40}',p['sha']) is not None for p in parents),'control_ancestry')
  if len(parents)==2:
   reviewed_sha=parents[1]['sha'];reviewed=checked_api('/git/commits/'+reviewed_sha)
   need(reviewed.get('sha')==reviewed_sha and isinstance(reviewed.get('tree',{}).get('sha'),str)
        and re.fullmatch(r'[a-f0-9]{40}',reviewed['tree']['sha']) is not None,'control_ancestry')
   if reviewed['tree']['sha']==tree and owned_blobs(reviewed_sha)==current:
    try:
     for name in required_checks:dependency_green_check(reviewed_sha,name,'source_ci')
    except RuntimeError as exc:
     if str(exc)!='source_ci':raise
    else:return {'merge_sha':head,'source_sha':reviewed_sha,'owned_blobs':current}
  if not parents:break
  head=parents[0]['sha']
 raise RuntimeError('reviewed_control_missing')

def tv_diagnostic_reviewed_control(control_sha):
 return _single_file_reviewed_control(control_sha,('guard','contracts','tv-diagnostic-contracts'))

def tv_diagnostic_admission(identity):
 need(re.fullmatch(r'[a-f0-9]{40}',TV_DIAGNOSTIC_RELEASE) is not None and bool(TV_DIAGNOSTIC_CHECK_IDS),'diagnostic_pins_incomplete')
 need(checked_api('/git/ref/heads/main')['object']['sha']==identity['control_source_sha'],'main_moved')
 need(checked_api('/git/ref/heads/release/search3-production-ready-v1')['object']['sha']==TV_DIAGNOSTIC_RELEASE,'release_moved')
 dependency_green_check(identity['control_source_sha'],'guard','main_ci')
 tv_diagnostic_reviewed_control(identity['control_source_sha'])
 item=checked_api('/contents/v2/api-v2.php?ref='+TV_DIAGNOSTIC_SOURCE)
 candidate=base64.b64decode(item['content'],validate=False)
 need(item['sha']==TV_DIAGNOSTIC_BLOB and blob(candidate)==TV_DIAGNOSTIC_BLOB and sha(candidate)==TV_DIAGNOSTIC_HASH and len(candidate)==TV_DIAGNOSTIC_BYTES,'candidate_bytes')
 need(checked_api('/contents/v2/api-v2.php?ref='+TV_DIAGNOSTIC_RELEASE)['sha']==TV_DIAGNOSTIC_BLOB,'release_candidate')
 checks=checked_api('/commits/'+TV_DIAGNOSTIC_SOURCE+'/check-runs?per_page=100');rows=checks.get('check_runs')
 need(type(checks.get('total_count')) is int and checks['total_count']==len(TV_DIAGNOSTIC_CHECK_IDS)
      and isinstance(rows,list) and len(rows)==checks['total_count']
      and {c.get('id') for c in rows}==TV_DIAGNOSTIC_CHECK_IDS,'source_checks')
 need(all(c.get('head_sha')==TV_DIAGNOSTIC_SOURCE and c.get('status')=='completed' and c.get('conclusion')=='success'
          and c.get('app',{}).get('slug')=='github-actions' for c in rows),'source_checks')
 command=f'{TV_DIAGNOSTIC_PREFIX} '+identity['control_source_sha']+' '+TV_DIAGNOSTIC_OPERATION
 fresh=checked_api('/issues/comments/'+str(identity['command_comment_id']))
 need(fresh.get('body')==command and fresh.get('user',{}).get('id')==226193297 and fresh.get('author_association')=='OWNER','command_changed')
 matched=[]
 for page in range(1,31):
  comments=checked_api(f'/issues/4709/comments?per_page=100&page={page}');need(isinstance(comments,list),'comments_shape')
  matched.extend(c['id'] for c in comments if c.get('body','').strip().startswith(TV_DIAGNOSTIC_PREFIX+' '))
  if len(comments)<100:
   need(matched==[identity['command_comment_id']],'operation_already_reserved');return candidate
 raise RuntimeError('comment_history_incomplete')

def tv_diagnostic_remote():
 import ast
 body=pathlib.Path(__file__).with_name('int_server_executor_local_tv_capture.py').read_bytes()
 need(sha(body)==CHECKED_READER_HASH,'reader_source_drift')
 module=ast.parse(body)
 remote=ast.literal_eval(next(n.value for n in module.body if isinstance(n,ast.Assign) and any(isinstance(t,ast.Name) and t.id=='REMOTE_REPAIR' for t in n.targets)))
 reader=next(n for n in ast.parse(remote).body if isinstance(n,ast.FunctionDef) and n.name=='repair_read')
 code=ast.get_source_segment(remote,reader);need(code is not None,'reader_missing')
 policy={'operation':TV_DIAGNOSTIC_OPERATION,'source':TV_DIAGNOSTIC_SOURCE,'release':TV_DIAGNOSTIC_RELEASE,
         'blob':TV_DIAGNOSTIC_BLOB,'sha256':TV_DIAGNOSTIC_HASH,'bytes':TV_DIAGNOSTIC_BYTES,
         'expected':TV_DIAGNOSTIC_EXPECTED,'protected':TV_DIAGNOSTIC_PROTECTED}
 return 'POLICY='+repr(policy)+'\n'+code+'\n'+REMOTE_CHECKED_INSTALL

def _single_file_safe_receipt(policy,identity,result,complete=False):
 try:
  counters=('supplier_calls','database_writes','profile_writes','mapping_writes','schema_writes','lead_calls','booking_calls')
  required={'schema_version','operation_id','source_sha','release_sha','source_blob','status',*counters,
            'root_write_attempts','file_writes','rollback_attempts','written_files','before_files','after_files',
            'syntax_previous','syntax_candidate','syntax_installed','backup_retained',*identity}
  need(isinstance(result,dict) and required<=set(result)<=required|{'production_before','production_after','reason','error_type'},'receipt_fields')
  need(type(result['schema_version']) is int and result['schema_version']==1,'receipt_schema')
  for key,value in {'operation_id':policy['operation'],'source_sha':policy['source'],'release_sha':policy['release'],'source_blob':policy['blob'],**identity}.items():need(result[key]==value,'receipt_identity')
  for key in counters:need(type(result[key]) is int and result[key]==0,'receipt_counters')
  need(result['status'] in ('blocked_no_replay','unknown_no_replay','rolled_back_no_replay','installed_verified'),'receipt_status')
  for key in ('root_write_attempts','rollback_attempts'):need(type(result[key]) is int and result[key] in (0,1),'receipt_attempts')
  need(type(result['file_writes']) is int and 0<=result['file_writes']<=2,'receipt_writes')
  need(result['written_files'] in ([],[policy.get('target','api-v2.php')]) and type(result['backup_retained']) is bool,'receipt_scope')
  need(all(result['syntax_'+p] in ('not_run','ok') for p in ('previous','candidate','installed')),'receipt_syntax')
  for phase in ('before_files','after_files'):
   values=result[phase];need(isinstance(values,dict) and set(values)<=set(policy['expected']),'receipt_inventory')
   for record in values.values():
    need(isinstance(record,dict) and set(record)=={'sha256','bytes','mode'} and isinstance(record['sha256'],str)
         and re.fullmatch(r'[a-f0-9]{64}',record['sha256']) is not None,'receipt_record')
    need(type(record['bytes']) is int and 0<record['bytes']<=2*1024*1024 and type(record['mode']) is int and 0<=record['mode']<=0o7777,'receipt_metadata')
  for phase in ('production_before','production_after'):
   if phase in result:
    need(isinstance(result[phase],dict) and set(result[phase])==set(policy['protected']),'receipt_public')
    need(all(v is None or isinstance(v,str) and re.fullmatch(r'[a-f0-9]{64}',v) is not None for v in result[phase].values()),'receipt_public_hashes')
  if 'reason' in result:need(result['reason'] in ('payload_limit','payload_fields','payload_identity','candidate_bytes','unsafe_private_parent','operation_exists_no_replay','predecessor_drift','different_filesystem','syntax_failed','file_changed','installed_readback','unsafe_parent','operation_failed','private_receipt_failed'),'receipt_reason')
  if 'error_type' in result:need(result['error_type'] in ('RuntimeError','FileNotFoundError','PermissionError','OSError','TimeoutExpired','JSONDecodeError','Error','ValueError'),'receipt_error_type')
  if complete:
   need(result['status']=='installed_verified' and result['root_write_attempts']==result['file_writes']==1 and result['rollback_attempts']==0,'receipt_status')
   need(result['written_files']==[policy.get('target','api-v2.php')] and result['backup_retained'] and all(result['syntax_'+p]=='ok' for p in ('previous','candidate','installed')),'receipt_scope')
   before,after=result['before_files'],result['after_files'];need(set(before)==set(after)==set(policy['expected']),'receipt_inventory')
   need(all(before[p]['sha256']==h and before[p]['mode']==0o644 and after[p]['mode']==0o644 for p,h in policy['expected'].items()),'receipt_predecessor')
   expected=dict(before);expected[policy.get('target','api-v2.php')]={'sha256':policy['sha256'],'bytes':policy['bytes'],'mode':0o644};need(after==expected,'receipt_after')
   need(result['production_before']==result['production_after'] and 'reason' not in result and 'error_type' not in result,'receipt_public')
  return True
 except (RuntimeError,AttributeError,KeyError,TypeError,ValueError):return False

def tv_diagnostic_safe_receipt(identity,result,complete=False):
 return _single_file_safe_receipt(_tv_diagnostic_policy(),identity,result,complete)

def _single_file_install(policy,work_name,event_check,admission_check,remote_program,receipt_check):
 import shlex
 work=pathlib.Path(os.environ['RUNNER_TEMP'])/work_name;work.mkdir()
 evidence={'status':'blocked_no_replay','operation_id':policy['operation'],'source_sha':policy['source'],'release_sha':policy['release'],'source_blob':policy['blob'],'failure_phase':'identity','ssh_started':False}
 key,known=work/'key',work/'known';completed=False
 try:
  identity=event_check(json.loads(pathlib.Path(os.environ['GITHUB_EVENT_PATH']).read_text()),os.environ)
  evidence.update(identity);evidence['failure_phase']='admission';candidate=admission_check(identity)
  evidence['failure_phase']='prepare'
  (work/policy.get('candidate_name','api-v2.php')).write_bytes(candidate)
  (work/'request.json').write_text(json.dumps({**identity,'operation_id':policy['operation'],'source_sha':policy['source'],'release_sha':policy['release'],'source_blob':policy['blob'],'candidate_sha256':policy['sha256']},sort_keys=True))
  payload={**identity,'candidate_b64':base64.b64encode(candidate).decode()};program=remote_program()
  host,user=os.environ['PREVIEW_HOST'],os.environ['PREVIEW_USER']
  need(re.fullmatch(r'[A-Za-z0-9][A-Za-z0-9.-]*',host) and re.fullmatch(r'[A-Za-z0-9_][A-Za-z0-9_.-]{0,63}',user),'ssh_identity')
  raw=os.environ['PREVIEW_KEY'].replace('\r','')
  if 'PRIVATE KEY' not in raw:raw=base64.b64decode(raw,validate=True).decode()
  key.write_text(raw.rstrip()+'\n');key.chmod(0o600)
  known.write_bytes(run(['ssh-keyscan','-T','15','-t','ed25519',host]));known.chmod(0o600)
  opts=['-i',str(key),'-o','StrictHostKeyChecking=yes','-o','UserKnownHostsFile='+str(known),'-o','GlobalKnownHostsFile=/dev/null','-o','BatchMode=yes','-o','ConnectTimeout=15']
  evidence.update(status='unknown_no_replay',failure_phase='ssh',ssh_started=True)
  response=run(['ssh',*opts,user+'@'+host,'python3 -c '+shlex.quote(program)],input=json.dumps(payload).encode(),timeout=90)
  evidence['failure_phase']='readback'
  need(len(response)<=16384,'receipt_limit');result=json.loads(response)
  need(receipt_check(identity,result),'receipt_unsafe');evidence['remote']=result
  completed=receipt_check(identity,result,complete=True);need(completed,'install_not_verified');evidence.update(status='installed_verified',failure_phase=None)
 except Exception as exc:evidence['error_type']=type(exc).__name__
 finally:
  try:(work/'evidence.json').write_text(json.dumps(evidence,sort_keys=True))
  finally:key.unlink(missing_ok=True);known.unlink(missing_ok=True)
 print(json.dumps(evidence,sort_keys=True));need(completed,'install_not_verified')

def _tv_diagnostic_policy():
 return {'operation':TV_DIAGNOSTIC_OPERATION,'source':TV_DIAGNOSTIC_SOURCE,'release':TV_DIAGNOSTIC_RELEASE,
         'blob':TV_DIAGNOSTIC_BLOB,'sha256':TV_DIAGNOSTIC_HASH,'bytes':TV_DIAGNOSTIC_BYTES,
         'expected':TV_DIAGNOSTIC_EXPECTED,'protected':TV_DIAGNOSTIC_PROTECTED}

def tv_diagnostic_install():
 return _single_file_install(_tv_diagnostic_policy(),'search3-root-tv-diagnostics',
     tv_diagnostic_event,tv_diagnostic_admission,tv_diagnostic_remote,tv_diagnostic_safe_receipt)

# A separate fixed contract for the existing shared search helper. It never
# reopens an older gateway operation or accepts a destination from the command.
CONTEXT_SENDER_PREFIX='/install-search3-tv-search-context'
CONTEXT_SENDER_OPERATION='install-20261022-tv-search-context-v1'
CONTEXT_SENDER_SOURCE='20e669cfbc7d99d9463a87fd9aee257f188df7ab'
CONTEXT_SENDER_RELEASE='764c1096d641e0387df5d472c494158526977a00'
CONTEXT_SENDER_PARENT='4d1486eb9a8949a84adf11c0cfdf6d680897dacb'
CONTEXT_SENDER_TREE='a202a08569decd3f73138c124c4aa6a035286121'
CONTEXT_SENDER_PATH='app/integrations/tourvisor-anytour-offer-autosave.php'
CONTEXT_SENDER_BLOB='54898eb773c12fd449a9b3bd23625fc3d73dc043'
CONTEXT_SENDER_HASH='0c0e623f8e923e61f4ac8436d7eac4e8671dcb7114195054456537f900f83271'
CONTEXT_SENDER_BYTES=21672
CONTEXT_SENDER_CHECK_IDS={119740007205,119740006238,119740005358}
CONTEXT_RECEIVER_SOURCE='5d95ba40c32b7f6322a979cc8c9c74e2f53fb59e'
CONTEXT_RECEIVER_FEATURE='d6599dc44e7fb1066272b6eab09a528498c3c55a'
CONTEXT_RECEIVER_PATH='app/integrations/tourvisor-operator-fuel-passive-intake.php'
CONTEXT_RECEIVER_BLOB='2e529cd25ddbe5c2eac8f20ada61cfc20213b9d6'
CONTEXT_RECEIVER_CHECK_IDS={119739791997,119739790960}
CONTEXT_SENDER_EXPECTED={
 CONTEXT_SENDER_PATH:'0291c16056be5b1a490e687f33d871e03e9f77a6b19d07f0cfcb0da9ad63893c',
 'anytoour.ru/api-v2.php':'04cddd8c112bcdd70438a2e58e4935e1eae115057b218780f2f845bbfd83fd15',
 'anytoour.ru/_preview/search3-anex-candidate/'+CONTEXT_RECEIVER_PATH:
   '7ae4c385f49bf9e994a073d3405564054e9e503da1d1e5f8c1ad4ee7cd9219ef',
}
# All previous gateway protected paths remain unchanged; no old UI byte pin is
# reused as a current predecessor. Only the shared helper can be replaced.
CONTEXT_SENDER_PROTECTED=tuple(sorted({'anytoour.ru/'+p for p in
 (*TV_DIAGNOSTIC_EXPECTED,*TV_DIAGNOSTIC_PROTECTED)}))

def context_sender_policy():
 return {'operation':CONTEXT_SENDER_OPERATION,'source':CONTEXT_SENDER_SOURCE,
         'release':CONTEXT_SENDER_RELEASE,'blob':CONTEXT_SENDER_BLOB,
         'sha256':CONTEXT_SENDER_HASH,'bytes':CONTEXT_SENDER_BYTES,
         'expected':CONTEXT_SENDER_EXPECTED,'protected':CONTEXT_SENDER_PROTECTED,
         'target':CONTEXT_SENDER_PATH,'candidate_name':'tourvisor-anytour-offer-autosave.php'}

def context_sender_event(event,env):
 # Same actor/workflow/created-only/first-attempt gate as the existing carrier.
 need(env.get('GITHUB_REPOSITORY')==REPO and event.get('repository',{}).get('full_name')==REPO,'repository')
 need(env.get('GITHUB_REF')=='refs/heads/main' and env.get('GITHUB_WORKFLOW_REF')==f'{REPO}/{CHECKED_WORKFLOW}@refs/heads/main','workflow')
 need(env.get('GITHUB_ACTOR')==env.get('GITHUB_TRIGGERING_ACTOR')=='pyatkoff','actor')
 need(env.get('GITHUB_EVENT_NAME')=='issue_comment' and event.get('action')=='created' and env.get('GITHUB_RUN_ATTEMPT')=='1','event')
 issue,comment=event.get('issue',{}),event.get('comment',{})
 need(issue.get('number')==4709 and 'pull_request' not in issue,'issue')
 need(comment.get('user',{}).get('id')==226193297 and comment.get('author_association')=='OWNER','owner')
 control,run_id=env.get('GITHUB_SHA',''),env.get('GITHUB_RUN_ID','')
 need(re.fullmatch(r'[a-f0-9]{40}',control) is not None and re.fullmatch(r'[1-9][0-9]{0,19}',run_id) is not None,'identity')
 need(type(comment.get('id')) is int and comment['id']>0,'comment_id')
 need(comment.get('body')==f'{CONTEXT_SENDER_PREFIX} {control} {CONTEXT_SENDER_OPERATION}','command')
 return {'control_source_sha':control,'run_id':run_id,'command_comment_id':comment['id']}

def context_sender_admission(identity):
 need(checked_api('/git/ref/heads/main')['object']['sha']==identity['control_source_sha'],'main_moved')
 need(checked_api('/git/ref/heads/release/search3-production-ready-v1')['object']['sha']==CONTEXT_SENDER_RELEASE,'release_moved')
 need(checked_api('/git/ref/heads/feature/anex-search-adapter-20260907')['object']['sha']==CONTEXT_RECEIVER_FEATURE,'receiver_moved')
 dependency_green_check(identity['control_source_sha'],'guard','main_ci')
 _single_file_reviewed_control(identity['control_source_sha'],
     ('guard','contracts','live-check-contracts','tv-diagnostic-contracts','context-sender-contracts'))
 release=checked_api('/git/commits/'+CONTEXT_SENDER_RELEASE)
 need(release.get('sha')==CONTEXT_SENDER_RELEASE
      and [p.get('sha') for p in release.get('parents',[])]==[CONTEXT_SENDER_PARENT,CONTEXT_SENDER_SOURCE]
      and release.get('tree',{}).get('sha')==CONTEXT_SENDER_TREE,'release_merge')
 item=checked_api('/contents/'+CONTEXT_SENDER_PATH+'?ref='+CONTEXT_SENDER_SOURCE)
 candidate=base64.b64decode(item['content'],validate=False)
 need(item['sha']==CONTEXT_SENDER_BLOB and blob(candidate)==CONTEXT_SENDER_BLOB
      and sha(candidate)==CONTEXT_SENDER_HASH and len(candidate)==CONTEXT_SENDER_BYTES,'candidate_bytes')
 need(checked_api('/contents/'+CONTEXT_SENDER_PATH+'?ref='+CONTEXT_SENDER_RELEASE)['sha']==CONTEXT_SENDER_BLOB,'release_candidate')
 need(checked_api('/contents/'+CONTEXT_RECEIVER_PATH+'?ref='+CONTEXT_RECEIVER_FEATURE)['sha']==CONTEXT_RECEIVER_BLOB,'receiver_candidate')
 for head,ids in ((CONTEXT_SENDER_SOURCE,CONTEXT_SENDER_CHECK_IDS),(CONTEXT_RECEIVER_SOURCE,CONTEXT_RECEIVER_CHECK_IDS)):
  checks=checked_api('/commits/'+head+'/check-runs?per_page=100');rows=checks.get('check_runs')
  need(type(checks.get('total_count')) is int and checks['total_count']==len(ids)
       and isinstance(rows,list) and len(rows)==checks['total_count']
       and {c.get('id') for c in rows}==ids,'source_checks')
  need(all(c.get('head_sha')==head and c.get('status')=='completed' and c.get('conclusion')=='success'
           and c.get('app',{}).get('slug')=='github-actions' for c in rows),'source_checks')
 command=f'{CONTEXT_SENDER_PREFIX} '+identity['control_source_sha']+' '+CONTEXT_SENDER_OPERATION
 fresh=checked_api('/issues/comments/'+str(identity['command_comment_id']))
 need(fresh.get('body')==command and fresh.get('user',{}).get('id')==226193297
      and fresh.get('author_association')=='OWNER','command_changed')
 matched=[]
 for page in range(1,31):
  comments=checked_api(f'/issues/4709/comments?per_page=100&page={page}');need(isinstance(comments,list),'comments_shape')
  matched.extend(c['id'] for c in comments if c.get('body','').strip().startswith(CONTEXT_SENDER_PREFIX+' '))
  if len(comments)<100:
   need(matched==[identity['command_comment_id']],'operation_already_reserved');return candidate
 raise RuntimeError('comment_history_incomplete')

def context_sender_remote():
 # Reuse the existing reviewed single-file transaction/reader, with a fixed
 # target under www/app. Neither the command nor payload can choose a path.
 import ast
 template=tv_diagnostic_remote();first,program=template.split('\n',1)
 need(first.startswith('POLICY=') and ast.literal_eval(first[7:])==_tv_diagnostic_policy(),'sender_template')
 need(program.count("project=pathlib.Path.home()/'www/anytoour.ru'")==1
      and program.count('sync_directory(project)')==2
      and program.count('if project.stat().st_dev!=op.stat().st_dev:')==1,'sender_template')
 program=program.replace("'api-v2.php'",repr(CONTEXT_SENDER_PATH))
 guard="""project=pathlib.Path.home()/'www'
def sender_target_guard():
 target=project/POLICY['target']
 for parent in (pathlib.Path.home(),project,target.parent.parent,target.parent):
  s=parent.lstat()
  if (parent.resolve()!=parent or not stat.S_ISDIR(s.st_mode)
      or s.st_uid!=os.getuid() or stat.S_IMODE(s.st_mode)&0o022):fail('unsafe_parent')
 if target.lstat().st_uid!=os.getuid():fail('unsafe_parent')
"""
 program=program.replace("project=pathlib.Path.home()/'www/anytoour.ru'",guard)
 program=program.replace(" data['production_before']=public_fingerprints()"," sender_target_guard()\n data['production_before']=public_fingerprints()")
 program=program.replace(' current,current_ids,_=snapshot()',' sender_target_guard()\n current,current_ids,_=snapshot()')
 program=program.replace('sync_directory(project)',"sync_directory((project/POLICY['target']).parent)")
 program=program.replace('if project.stat().st_dev!=op.stat().st_dev:',"if (project/POLICY['target']).parent.stat().st_dev!=op.stat().st_dev:")
 return 'POLICY='+repr(context_sender_policy())+'\n'+program

def context_sender_safe_receipt(identity,result,complete=False):
 return _single_file_safe_receipt(context_sender_policy(),identity,result,complete)

def context_sender_install():
 return _single_file_install(context_sender_policy(),'search3-tv-context-sender',
     context_sender_event,context_sender_admission,context_sender_remote,context_sender_safe_receipt)

if __name__=='__main__':
 need(len(sys.argv)==2 and sys.argv[1] in ('prepare','install','checked_install','dependency_install','tv_diagnostic_install','context_sender_install'),'usage');globals()[sys.argv[1]]()
