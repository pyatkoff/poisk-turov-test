"""One read-only native producer, with private output and allowlisted diagnostics."""
import hashlib,json,os,re,shlex,subprocess
from pathlib import Path
ROOT=Path(__file__).resolve().parent
BRANCH='audit/price4294-producer-read-20261118'
BEFORE='4e7f4df3928d617d7cb652738da510f9be707d97'
ALLOWED_ERRORS={'isolated_sql_unbound','isolated_sql_not_proven_idle','isolated_sql_snapshot_too_late','isolated_sql_env_shape','isolated_sql_unavailable','producer_failed_closed','git_read_failed','producer_runtime_warning','producer_args','producer_identity','producer_config_hash','producer_closed','runtime_dirty','runtime_changed','path_changed','busy','file_replaced','incomplete','file_changed','preimage_changed','pending_changed','missing','native_evidence_changed','process_read_failed','process_read_incomplete','process_line_unknown','native_collector_busy','consumer_db_source_changed','consumer_db_function_missing','consumer_db_unavailable','consumer_config_missing','canonical_collector_sql_lock_busy','consumer_path_changed','consumer_changed','staged_bundle_changed','journal_identity_changed'}
EXPECTED={'producer.php':'67601c7d5d70eba51ac358bf19c3d7d0501662e36d0b966687b2dd2ca4679491','staged-bundle.php':'605f8e628f5ad8bb07ab7e12f0e8a33c51a773defad43711d46908db0cb1caa4'}
def need(v,m):
 if not v:raise RuntimeError(m)
def sha(b):return hashlib.sha256(b).hexdigest()
def checked(args,**kw):
 p=subprocess.run(args,capture_output=True,**kw);need(p.returncode==0,'read_command_failed');return p.stdout
def sanitized(raw,stderr,rc,run,head):
 out={'run_id':run,'head':head,'phase':'producer','read_only':True,'native_write_attempted':False,'native_writes':0,'returncode':rc,'raw_sha256':sha(raw),'stderr_sha256':sha(stderr),'ok':False}
 try:v=json.loads(raw)
 except (ValueError,UnicodeDecodeError):v={}
 if not isinstance(v,dict):v={}
 if rc!=0:
  out['error']=v.get('error') if v.get('error') in ALLOWED_ERRORS else 'producer_failed_closed'
  if out['error']=='producer_runtime_warning':
   if v.get('warning_kind') in {'constant_already_defined','undefined_array_key','deprecated','other'}:out['warning_kind']=v['warning_kind']
   if v.get('warning_phase') in {'bootstrap','runtime','app_config','global_read','child_read','registry','local_feeds','evidence','processes','consumer_db','consumer_config','consumer_sql','consumer_code','bundle','final_identity','output','unknown'}:out['warning_phase']=v['warning_phase']
   if v.get('warning_file') in {'producer.php','config.php','db-v1.php','ChannelPostRuntime.php','ChannelPostRegistryService.php','ConversationDb.php','other'}:out['warning_file']=v['warning_file']
   for k in ('warning_severity','warning_line'):
    if type(v.get(k)) is int and v[k]>=0:out[k]=v[k]
  enums={'ps_probe':{'failed','observed'},'owner_class':{'null','int_positive','numeric_string_positive','zero','false','other'},'owner_probe':{'not_positive','connection','processlist','observed','failed'},'owner_probe_error':{'pdo','error','exception'},'owner_command':{'Sleep','Query','Execute','Connect','Daemon','Binlog Dump','Killed','other'},'child_warning_kind':{'constant_already_defined','undefined_array_key','deprecated','other'},'child_warning_file':{'config.php','db-v1.php','other'},'pdo_state_class':{'08','28','42','HY','IM','other'},'identity_target':{'db','config'},'producer_phase':{'bootstrap','runtime','app_config','global_read','child_read','registry','local_feeds','evidence','processes','consumer_db','consumer_config','consumer_sql','consumer_code','bundle','final_identity','output','unknown'},'throwable_kind':{'pdo','error','exception','other'},'sql_transport':{'env','spawn','capture','timeout_or_bound','stderr','child_exit','parse'},'sql_phase':{'path','identity','db_pin','include_db','connect_config','query','owner','config_included','identity_after','output','worker_guard'},'sql_kind':{'guard','config_warning','pdo','error','loader','missing_driver','lock_busy','shape'}}
  for k,allowed in enums.items():
   if isinstance(v.get(k),str) and v[k] in allowed:out[k]=v[k]
  for k in ('owner_equals_connection_id','owner_visible','identity_exists','identity_is_link','identity_realpath_equal','parent_mysql_available','child_mysql_available','transport_timeout','transport_output_bounds','transport_stderr_nonempty'):
   if type(v.get(k)) is bool:out[k]=v[k]
  for k in ('child_warning_line','child_warning_severity','parent_php_version_id','child_php_version_id','transport_exitcode'):
   if type(v.get(k)) is int and -1<=v[k]<=1000000:out[k]=v[k]
  for k in ('timer_count','timer_elapsed_max','owner_seconds','collector_count','monitor_count','recover_count','collector_elapsed_max','monitor_elapsed_max','recover_elapsed_max'):
   if type(v.get(k)) is int and 0<=v[k]<=2147483647:out[k]=v[k]
  if type(v.get('pdo_driver_code')) is int and v['pdo_driver_code'] in {1045,1049,2002,2006,2013}:out['pdo_driver_code']=v['pdo_driver_code']
  if isinstance(v.get('transport_stderr_sha256'),str) and re.fullmatch('[a-f0-9]{64}',v['transport_stderr_sha256']):out['transport_stderr_sha256']=v['transport_stderr_sha256']
  if 'timer_receipts' in v:out['timer_receipts']=timer_projection(v['timer_receipts'])
  return out
 p=v.get('producer');need(isinstance(p,dict),'producer_shape_invalid')
 for key in ('runtime_sha','head_sha'):
  need(isinstance(p.get(key),str) and re.fullmatch('[a-f0-9]{40}',p[key]),'producer_identity_invalid');out[key]=p[key]
 for key in ('run_id','observed_at_epoch','native_active_collectors','sql_active_collectors'):
  need(type(p.get(key)) is int and p[key]>=0,'producer_counter_invalid');out[key]=p[key]
 need(p['run_id']==int(run) and p['head_sha']==head,'producer_original_changed')
 need(p['native_active_collectors']==p['sql_active_collectors']==0 and p.get('unknown_collectors') is False,'producer_not_idle')
 for key in ('consumer_db_sha256','staged_bundle_sha256','cohort_sha256','collector_sha256'):
  need(isinstance(p.get(key),str) and re.fullmatch('[a-f0-9]{64}',p[key]),'producer_hash_invalid');out[key]=p[key]
 for key in ('global_pin','child_pin'):
  pin=p.get(key);need(isinstance(pin,dict) and set(pin)=={'sha256','bytes','lines'} and isinstance(pin['sha256'],str) and re.fullmatch('[a-f0-9]{64}',pin['sha256']) and all(type(pin[k]) is int and pin[k]>=0 for k in ('bytes','lines')),'producer_pin_invalid');out[key]=pin
 feeds=p.get('feed_shas');need(isinstance(feeds,dict) and set(feeds)=={'telegram','max','all'} and all(isinstance(x,str) and re.fullmatch('[a-f0-9]{64}',x) for x in feeds.values()),'producer_feeds_invalid');out['feed_shas']=feeds
 for k in ('collector_count','monitor_count','recover_count','timer_count','collector_elapsed_max','monitor_elapsed_max','recover_elapsed_max','timer_elapsed_max'):
  if type(p.get(k)) is int and 0<=p[k]<=2147483647:out[k]=p[k]
 if p.get('ps_probe') in {'observed','failed'}:out['ps_probe']=p['ps_probe']
 if 'timer_receipts' in p:out['timer_receipts']=timer_projection(p['timer_receipts'])
 out['ok']=True;return out

def timer_projection(v):
 out={}
 if not isinstance(v,dict):return out
 for name in ('last-check','last-collection','last-error'):
  row=v.get(name)
  if not isinstance(row,dict):continue
  clean={}
  if row.get('read_status') in {'observed','missing','unavailable'}:clean['read_status']=row['read_status']
  for k in ('started_at','finished_at'):
   if isinstance(row.get(k),str) and re.fullmatch(r'[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}(?:Z|[+-][0-9]{2}:[0-9]{2})',row[k]):clean[k]=row[k]
  if row.get('state') in {'busy','recent','collected','partial','failed'}:clean['state']=row['state']
  if type(row.get('exit')) is int and -1<=row['exit']<=255:clean['exit']=row['exit']
  result=row.get('result')
  if isinstance(result,dict):clean['result']={k:result[k] for k in ('processed','logical_processed','observations_written','failed','partial','fresh_targets','pending_targets') if type(result.get(k)) is int and 0<=result[k]<=2147483647}
  out[name]=clean
 return out

def retained(command,path):return 'set -C; if { '+command+'; native_rc=$?; } > '+shlex.quote(path)+'; then cat '+shlex.quote(path)+' || exit 96; exit "$native_rc"; else exit 97; fi'
def main():
 os.umask(0o077);plan=json.loads((ROOT/'plan-CLOSED.json').read_bytes());need(plan.get('enabled') is True,'diagnostic_closed')
 e=os.environ;event=json.loads(Path(e['GITHUB_EVENT_PATH']).read_bytes())
 need(e['GITHUB_REPOSITORY']=='pyatkoff/poisk-turov-test' and e['GITHUB_ACTOR']=='pyatkoff' and e['GITHUB_EVENT_NAME']=='push' and e['GITHUB_RUN_ATTEMPT']=='1' and event['before']==BEFORE and event['ref']=='refs/heads/'+BRANCH,'original_successor_required')
 run=e['GITHUB_RUN_ID'];head=e['GITHUB_SHA'];need(re.fullmatch('[1-9][0-9]*',run) and re.fullmatch('[a-f0-9]{40}',head),'original_identity_invalid')
 current=json.loads(checked(['gh','api','--method','GET','repos/pyatkoff/poisk-turov-test/git/ref/heads/'+BRANCH],timeout=40));need(current['object']['sha']==head,'carrier_moved')
 base=Path(e['RUNNER_TEMP'])/'producer-read-private';base.mkdir(mode=0o700)
 need({x['name'] for x in plan['sources']}=={'producer.php','staged-bundle.php','producer-config.json','probe-core.php','probe-worker.php','subprocess.php'} and len(plan['sources'])==6,'exact_six_sources_required')
 for source in plan['sources']:
  need(source['repository']=='pyatkoff/poisk-turov-test' and re.fullmatch('[a-f0-9]{40}',source['ref']),'source_unbound')
  raw=checked(['gh','api','--method','GET','-H','Accept: application/vnd.github.raw+json','repos/'+source['repository']+'/contents/'+source['path']+'?ref='+source['ref']],timeout=40)
  need(hashlib.sha1(b'blob '+str(len(raw)).encode()+b'\0'+raw).hexdigest()==source['blob_sha'] and sha(raw)==source['sha256'] and (source['name'] not in EXPECTED or sha(raw)==EXPECTED[source['name']]),'source_changed')
  (base/source['name']).write_bytes(raw)
 config=json.loads((base/'producer-config.json').read_bytes());need(config.get('producer_enabled') is True,'producer_closed')
 need(config['consumer_db_sha256']=='ca7f11d6ec53e0cd3d4464e0f645f15b761bb734da1323689479838868d4abc4','db_binding_changed')
 host=e['DEPLOY_HOST'];user=e['DEPLOY_USER'];need(re.fullmatch('[A-Za-z0-9_.:-]+',host) and not host.startswith('-') and re.fullmatch('[A-Za-z0-9_-]+',user),'remote_invalid')
 opts=['-i',e['RUNNER_TEMP']+'/channel_key','-o','BatchMode=yes','-o','StrictHostKeyChecking=yes','-o','UserKnownHostsFile='+e['RUNNER_TEMP']+'/channel_known_hosts','-o','ConnectTimeout=15','-o','ServerAliveInterval=30'];remote=user+'@'+host
 prefix='unset GIT_DIR GIT_WORK_TREE GIT_COMMON_DIR GIT_INDEX_FILE GIT_CONFIG_PARAMETERS GIT_CONFIG_COUNT GIT_OBJECT_DIRECTORY GIT_ALTERNATE_OBJECT_DIRECTORIES; export GIT_OPTIONAL_LOCKS=0; '
 scratch=checked(['ssh',*opts,remote,'umask 077; mktemp -d /tmp/price4294-producer-read.XXXXXXXX'],timeout=30).decode().strip();need(re.fullmatch(r'/tmp/price4294-producer-read\.[A-Za-z0-9]+',scratch),'scratch_invalid')
 for source in plan['sources']:checked(['scp',*opts,str(base/source['name']),remote+':'+scratch+'/'+source['name']],timeout=60)
 remote_sums=checked(['ssh',*opts,remote,'sha256sum '+' '.join(shlex.quote(scratch+'/'+s['name']) for s in plan['sources'])],timeout=30).decode().splitlines()
 need(len(remote_sums)==6,'remote_sources_incomplete')
 for line,source in zip(remote_sums,plan['sources']):need(line.split()==[source['sha256'],scratch+'/'+source['name']],'remote_source_changed')
 cmd='php '+shlex.quote(scratch+'/producer.php')+' '+shlex.quote(scratch+'/producer-config.json')+' '+run+' '+head+' '+sha((base/'producer-config.json').read_bytes())
 try:
  proc=subprocess.run(['ssh',*opts,remote,prefix+retained(cmd,scratch+'/producer-result.private.json')],capture_output=True,timeout=240);raw,stderr,rc=proc.stdout,proc.stderr,proc.returncode
 except subprocess.TimeoutExpired as ex:raw,stderr,rc=ex.stdout or b'',ex.stderr or b'',None
 (base/'stdout.private').write_bytes(raw);(base/'stderr.private').write_bytes(stderr)
 try:out=sanitized(raw,stderr,rc,run,head)
 except (RuntimeError,KeyError,TypeError):out={'run_id':run,'head':head,'phase':'producer','read_only':True,'native_write_attempted':False,'native_writes':0,'returncode':rc,'raw_sha256':sha(raw),'stderr_sha256':sha(stderr),'ok':False,'error':'producer_result_invalid'}
 out['native_receipt_relative']=scratch.removeprefix('/tmp/')+'/producer-result.private.json'
 public=Path(e['RUNNER_TEMP'])/'producer-read-sanitized';public.mkdir(mode=0o700);(public/'terminal.json').write_text(json.dumps(out,sort_keys=True)+'\n')
 need(out['ok'] is True,'read_only_producer_failed')
if __name__=='__main__':main()
