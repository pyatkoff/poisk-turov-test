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

if __name__=='__main__':
 need(len(sys.argv)==2 and sys.argv[1] in ('prepare','install','checked_install'),'usage');globals()[sys.argv[1]]()
