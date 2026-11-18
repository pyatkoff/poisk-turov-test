"""CLOSED single invocation wiring. No retries or hidden resume after native calls."""
import base64
import gzip
import hashlib
import importlib.util
import json
import os
from pathlib import Path
import re
import shlex
import subprocess

ROOT=Path(__file__).resolve().parent
NATIVE_SHA='91dd4ec54aa101685efb8cee61eaf9b8d3c7cf5a07f225af52baeda523055666'

def need(v,m):
    if not v: raise RuntimeError(m)
def sha(b): return hashlib.sha256(b).hexdigest()
def canonical(v): return json.dumps(v,sort_keys=True,separators=(',',':'),ensure_ascii=False).encode()
def dump(path,v):
    raw=canonical(v);path.write_bytes(raw);path.chmod(0o600);return sha(raw)
def command(args,**kw):
    p=subprocess.run(args,capture_output=True,**kw)
    need(p.returncode==0,'command_failed_no_retry')
    return p.stdout

def decode_evidence(result):
    packed=base64.b64decode(result['native_gzip_base64'],validate=True)
    need(len(packed)<=2000000,'compressed_evidence_bound')
    # Bound decompression before allocation of an arbitrarily large gzip payload.
    import io
    with gzip.GzipFile(fileobj=io.BytesIO(packed)) as stream:
        raw=stream.read(2000001)
    need(len(raw)<=2000000 and sha(raw)==result['native_sha256']==NATIVE_SHA,'private_original_evidence_changed')
    return raw

def sanitized_result(raw, stderr, run_id, head, returncode, timed_out=False):
    result={'run_id':run_id,'head':head,'raw_sha256':sha(raw),'stderr_sha256':sha(stderr),
            'returncode':returncode,'timed_out':timed_out,'ok':False,'phase':'unknown','error':'unknown_requires_original_readback'}
    try: value=json.loads(raw)
    except (ValueError,UnicodeDecodeError): return result
    if not isinstance(value,dict): return result
    for key in ('ok','database_confirmation_attempted','child_append_attempted','feed_finish_attempted'):
        if type(value.get(key)) is bool: result[key]=value[key]
    for key in ('provider_calls','provider_writes','new_content_edits'):
        if type(value.get(key)) is int and value[key]>=0: result[key]=value[key]
    for key in ('phase','error','state'):
        if isinstance(value.get(key),str) and re.fullmatch('[a-z][a-z0-9_]{0,100}',value[key]):result[key]=value[key]
    for key in ('global_pin','child_pin'):
        pin=value.get(key)
        if isinstance(pin,dict) and re.fullmatch('[a-f0-9]{64}',str(pin.get('sha256',''))) and all(type(pin.get(k)) is int and pin[k]>=0 for k in ('bytes','lines')):
            result[key]={k:pin[k] for k in ('sha256','bytes','lines')}
    feeds=value.get('feed_shas')
    if isinstance(feeds,dict) and set(feeds)=={'telegram','max','all'} and all(isinstance(v,str) and re.fullmatch('[a-f0-9]{64}',v) for v in feeds.values()):result['feed_shas']=feeds
    if timed_out or returncode!=0:result['ok']=False
    if result['ok']:result.pop('error',None)
    return result

def retained_receipt_metadata(value, scratch):
    need(isinstance(scratch,str) and re.fullmatch(r'/tmp/price4294-recovery\.[A-Za-z0-9]+',scratch),
         'receipt_location_invalid')
    relative=scratch.removeprefix('/tmp/')+'/recovery-result.private.json'
    need(re.fullmatch(r'price4294-recovery\.[A-Za-z0-9]+/recovery-result\.private\.json',relative),
         'receipt_location_invalid')
    return {**value,'native_receipt_relative':relative}

def public_terminal(value):
    target=Path(os.environ['RUNNER_TEMP'])/'recovery-sanitized';target.mkdir(mode=0o700,exist_ok=True)
    dump(target/'terminal.json',value)

def bind_route(admission_cfg, route_raw, expected_sha):
    need(sha(route_raw)==expected_sha,'private_route_attestation_changed')
    route=json.loads(route_raw)
    need(isinstance(route,dict),'route_attestation_shape')
    admission_cfg['route_attestation']={'file':'route-attestation.json','sha256':expected_sha}
    admission_cfg['expected_route_attestation']=route
    return route

def retain_native_stdout(command_text, output_path):
    # Opening the noclobber redirection must succeed BEFORE the sole native call.
    path=shlex.quote(output_path)
    return 'set -C; if { '+command_text+'; native_rc=$?; } > '+path+'; then cat '+path+' || exit 96; exit "$native_rc"; else exit 97; fi'

ALLOWED_ERRORS={'isolated_sql_unbound','isolated_sql_not_proven_idle','isolated_sql_snapshot_too_late','isolated_sql_env_shape','isolated_sql_unavailable','producer_failed_closed','git_read_failed','producer_runtime_warning','producer_args','producer_identity','producer_config_hash','producer_closed','runtime_dirty','runtime_changed','path_changed','busy','file_replaced','incomplete','file_changed','preimage_changed','pending_changed','missing','native_evidence_changed','process_read_failed','process_read_incomplete','process_line_unknown','native_collector_busy','consumer_db_source_changed','consumer_db_function_missing','consumer_db_unavailable','consumer_config_missing','canonical_collector_sql_lock_busy','consumer_path_changed','consumer_changed','staged_bundle_changed','journal_identity_changed'}

def producer_failure_metadata(raw):
 out={}
 try:v=json.loads(raw)
 except (ValueError,UnicodeDecodeError):v={}
 if not isinstance(v,dict):v={}
 error=v.get('error')
 out['error']=error if isinstance(error,str) and error in ALLOWED_ERRORS else 'producer_failed_closed'
 if out['error']=='producer_runtime_warning':
  if isinstance(v.get('warning_kind'),str) and v.get('warning_kind') in {'constant_already_defined','undefined_array_key','deprecated','other'}:out['warning_kind']=v['warning_kind']
  if isinstance(v.get('warning_phase'),str) and v.get('warning_phase') in {'bootstrap','runtime','app_config','global_read','child_read','registry','local_feeds','evidence','processes','consumer_db','consumer_config','consumer_sql','consumer_code','bundle','final_identity','output','unknown'}:out['warning_phase']=v['warning_phase']
  if isinstance(v.get('warning_file'),str) and v.get('warning_file') in {'producer.php','config.php','db-v1.php','ChannelPostRuntime.php','ChannelPostRegistryService.php','ConversationDb.php','other'}:out['warning_file']=v['warning_file']
  for k in ('warning_severity','warning_line'):
   if type(v.get(k)) is int and v[k]>=0:out[k]=v[k]
 enums={'child_warning_kind':{'constant_already_defined','undefined_array_key','deprecated','other'},'child_warning_file':{'config.php','db-v1.php','other'},'pdo_state_class':{'08','28','42','HY','IM','other'},'identity_target':{'db','config'},'producer_phase':{'bootstrap','runtime','app_config','global_read','child_read','registry','local_feeds','evidence','processes','consumer_db','consumer_config','consumer_sql','consumer_code','bundle','final_identity','output','unknown'},'throwable_kind':{'pdo','error','exception','other'},'sql_transport':{'env','spawn','capture','timeout_or_bound','stderr','child_exit','parse'},'sql_phase':{'path','identity','db_pin','include_db','connect_config','query','owner','config_included','identity_after','output','worker_guard'},'sql_kind':{'guard','config_warning','pdo','error','loader','missing_driver','lock_busy','shape'}}
 for k,allowed in enums.items():
  if isinstance(v.get(k),str) and v[k] in allowed:out[k]=v[k]
 for k in ('identity_exists','identity_is_link','identity_realpath_equal','parent_mysql_available','child_mysql_available','transport_timeout','transport_output_bounds','transport_stderr_nonempty'):
  if type(v.get(k)) is bool:out[k]=v[k]
 for k in ('child_warning_line','child_warning_severity','parent_php_version_id','child_php_version_id','transport_exitcode'):
  if type(v.get(k)) is int and -1<=v[k]<=1000000:out[k]=v[k]
 if type(v.get('pdo_driver_code')) is int and v['pdo_driver_code'] in {1045,1049,2002,2006,2013}:out['pdo_driver_code']=v['pdo_driver_code']
 if isinstance(v.get('transport_stderr_sha256'),str) and re.fullmatch('[a-f0-9]{64}',v['transport_stderr_sha256']):out['transport_stderr_sha256']=v['transport_stderr_sha256']
 return out

def capture_readonly_producer(args, base, run_id, head):
    # Read-only diagnostics are retained before interpreting exit status. No retry.
    try:
        proc=subprocess.run(args,capture_output=True,timeout=240)
        raw,stderr,rc=proc.stdout,proc.stderr,proc.returncode
    except subprocess.TimeoutExpired as exc:
        raw,stderr,rc=exc.stdout or b'',exc.stderr or b'',None
    (base/'producer-stdout.private.json').write_bytes(raw)
    (base/'producer-stderr.private.log').write_bytes(stderr)
    if rc != 0:
        metadata=producer_failure_metadata(raw)
        if rc is None: metadata['error']='producer_timeout'
        public_terminal({'run_id':run_id,'head':head,'phase':'producer','returncode':rc,
                         **metadata,'native_write_attempted':False,'ok':False,
                         'raw_sha256':sha(raw),'stderr_sha256':sha(stderr)})
        raise RuntimeError('producer_preflight_failed_no_writer')
    return raw

def main():
    os.umask(0o077)
    plan=json.loads((ROOT/'launch-CLOSED.json').read_bytes())
    need(plan.get('enabled') is True and plan.get('binding_reviewed') is True,'launch_closed')
    env=os.environ
    need(env['GITHUB_REPOSITORY']=='pyatkoff/poisk-turov-test' and env['GITHUB_RUN_ATTEMPT']=='1'
         and env['GITHUB_EVENT_NAME']=='push','original_run_required')
    run=env['GITHUB_RUN_ID'];head=env['GITHUB_SHA']
    need(re.fullmatch('[1-9][0-9]*',run) and re.fullmatch('[a-f0-9]{40}',head),'identity_invalid')
    base=Path(env['RUNNER_TEMP'])/('price4294-'+run)
    base.mkdir(mode=0o700) # exclusive: never reuse a previous attempt
    sources=plan['sources']; need(sources and len({s['name'] for s in sources})==len(sources),'manifest_invalid')
    for s in sources:
        need(Path(s['name']).name==s['name'] and re.fullmatch('[a-f0-9]{40}',s['ref']),'source_invalid')
        need(s['repository'] in ('pyatkoff/max-search-bot','pyatkoff/poisk-turov-test'),'source_repository_invalid')
        raw=command(['gh','api','--method','GET','-H','Accept: application/vnd.github.raw+json',
                     'repos/'+s['repository']+'/contents/'+s['path']+'?ref='+s['ref']],timeout=40)
        need(sha(raw)==s['sha256'] and hashlib.sha1(b'blob '+str(len(raw)).encode()+b'\0'+raw).hexdigest()==s['blob_sha'],'immutable_source_changed')
        (base/s['name']).write_bytes(raw)
    required={'admit.py','recover.php','pure.php','safe-result.php','editor-pure-proof.php','verify-release.js','release_priority.js','deployment_release.js','release_slot_close.js','producer.php','read.php','staged-bundle.php','config-CLOSED.json','admission-config-CLOSED.json'}
    need(required<={s['name'] for s in sources},'missing_source')
    helper=json.loads((base/'config-CLOSED.json').read_bytes()); admission_cfg=json.loads((base/'admission-config-CLOSED.json').read_bytes())
    need(helper.get('enabled') is True and admission_cfg.get('enabled') is True and admission_cfg.get('binding_reviewed') is True,'helper_or_admission_closed')
    # The runner never edits source guards, enables templates, or invents accepted release.
    route_raw=(ROOT/'route-attestation.json').read_bytes()
    bind_route(admission_cfg,route_raw,plan['route_attestation_sha256'])
    (base/'route-attestation.json').write_bytes(route_raw)
    host=env['DEPLOY_HOST'];user=env['DEPLOY_USER']
    need(re.fullmatch('[A-Za-z0-9_.:-]+',host) and not host.startswith('-') and re.fullmatch('[A-Za-z0-9_-]+',user),'remote_invalid')
    opts=['-i',env['RUNNER_TEMP']+'/channel_key','-o','BatchMode=yes','-o','StrictHostKeyChecking=yes','-o','UserKnownHostsFile='+env['RUNNER_TEMP']+'/channel_known_hosts','-o','ConnectTimeout=15','-o','ServerAliveInterval=30']
    remote=user+'@'+host
    def ssh(cmd,timeout=240):
        return command(['ssh',*opts,remote,'unset GIT_DIR GIT_WORK_TREE GIT_COMMON_DIR GIT_INDEX_FILE GIT_CONFIG_PARAMETERS GIT_CONFIG_COUNT GIT_OBJECT_DIRECTORY GIT_ALTERNATE_OBJECT_DIRECTORIES ANYTOUR_MONITOR_HOOK_INSPECT; export GIT_OPTIONAL_LOCKS=0; '+cmd],timeout=timeout)
    scratch=ssh('umask 077; mktemp -d /tmp/price4294-recovery.XXXXXXXX',30).decode().strip()
    need(re.fullmatch(r'/tmp/price4294-recovery\.[A-Za-z0-9]+',scratch),'scratch_invalid')
    # Only private scratch staging; no canonical code/journal/publication install here.
    def upload(name): command(['scp',*opts,str(base/name),remote+':'+scratch+'/'+name],timeout=60)
    for s in sources: upload(s['name'])
    producer_cfg=dict(plan['producer_config'])
    need(producer_cfg.get('producer_enabled') is True,'producer_closed')
    for k in ('canonical_hook_sha256','staged_bundle_sha256','cohort_sha256','collector_sha256'): need(producer_cfg[k]==helper[k],'producer_template_pin_changed')
    producer_sha=dump(base/'producer-config.json',producer_cfg);upload('producer-config.json')
    staged_lines=ssh('sha256sum '+' '.join(shlex.quote(scratch+'/'+s['name']) for s in sources)).decode().splitlines()
    need(len(staged_lines)==len(sources),'remote_manifest_incomplete')
    for line,s in zip(staged_lines,sources): need(line.split()[0]==s['sha256'] and line.split()[1]==scratch+'/'+s['name'],'remote_stage_changed')
    producer_cmd='php '+shlex.quote(scratch+'/producer.php')+' '+shlex.quote(scratch+'/producer-config.json')+' '+run+' '+head+' '+producer_sha
    producer_retained=retain_native_stdout(producer_cmd,scratch+'/producer-result.private.json')
    observed=capture_readonly_producer(['ssh',*opts,remote,'unset GIT_DIR GIT_WORK_TREE GIT_COMMON_DIR GIT_INDEX_FILE GIT_CONFIG_PARAMETERS GIT_CONFIG_COUNT GIT_OBJECT_DIRECTORY GIT_ALTERNATE_OBJECT_DIRECTORIES ANYTOUR_MONITOR_HOOK_INSPECT; export GIT_OPTIONAL_LOCKS=0; '+producer_retained],base,run,head)
    need(len(observed)<=2000000,'producer_output_bound')
    result=json.loads(observed)
    raw=decode_evidence(result)
    (base/'native.json').write_bytes(raw)
    producer=result['producer']; producer_digest=dump(base/'producer.json',producer)
    helper['producer_proof']={'run_id':producer['run_id'],'observed_at_epoch':producer['observed_at_epoch'],'receipt_sha256':producer_digest}
    admission_cfg['producer_receipt']={'file':'producer.json','sha256':producer_digest}
    payload=dict(helper);payload.pop('admission_sha256',None)
    admission_cfg['helper_config_payload_sha256']=sha(canonical(payload))
    spec=importlib.util.spec_from_file_location('recovery_admission',base/'admit.py');module=importlib.util.module_from_spec(spec);spec.loader.exec_module(module)
    event=json.loads(Path(env['GITHUB_EVENT_PATH']).read_bytes())
    # Fresh canonical release acceptance + GHA/claims + native/route TTL checks.
    admitted=module.evaluate(admission_cfg,helper,env,event,base)
    helper['admission_sha256']=dump(base/'admission.json',admitted)
    config_sha=dump(base/'config-CLOSED.json',helper)
    for name in ('native.json','admission.json','config-CLOSED.json'):upload(name)
    # Persist intent locally before the ONLY mutating command. Any timeout/failure is UNKNOWN.
    dump(base/'invocation-intent.json',{'run_id':run,'head':head,'config_sha256':config_sha,'remote_scratch':scratch,'attempts':1})
    try:
        recover_cmd='php '+shlex.quote(scratch+'/recover.php')+' '+shlex.quote(scratch+'/config-CLOSED.json')+' '+shlex.quote(scratch+'/native.json')+' '+shlex.quote(scratch+'/staged-bundle.php')+' '+shlex.quote(scratch+'/admission.json')+' '+config_sha
        # Do not lose the original terminal receipt when PHP exits nonzero.
        retained_cmd=retain_native_stdout(recover_cmd,scratch+'/recovery-result.private.json')
        proc=subprocess.run(['ssh',*opts,remote,'unset GIT_DIR GIT_WORK_TREE GIT_COMMON_DIR GIT_INDEX_FILE GIT_CONFIG_PARAMETERS GIT_CONFIG_COUNT GIT_OBJECT_DIRECTORY GIT_ALTERNATE_OBJECT_DIRECTORIES ANYTOUR_MONITOR_HOOK_INSPECT; export GIT_OPTIONAL_LOCKS=0; '+retained_cmd],capture_output=True)
        out=proc.stdout
        (base/'recovery-result.private.json').write_bytes(out)
        (base/'recovery-stderr.private.log').write_bytes(proc.stderr)
        public_terminal(retained_receipt_metadata(sanitized_result(out,proc.stderr,run,head,proc.returncode),scratch))
        need(proc.returncode==0,'native_nonzero_UNKNOWN_no_retry')
        receipt=json.loads(out)
        need(receipt.get('ok') is True,'recovery_not_confirmed_do_not_retry')
    except BaseException as exc:
        if isinstance(exc,subprocess.TimeoutExpired):
            (base/'recovery-timeout-stdout.private').write_bytes(exc.stdout or b'')
            (base/'recovery-timeout-stderr.private').write_bytes(exc.stderr or b'')
            public_terminal(retained_receipt_metadata(sanitized_result(exc.stdout or b'',exc.stderr or b'',run,head,None,True),scratch))
        dump(base/'UNKNOWN.json',{'outcome':'UNKNOWN','retry_authorized':False,'original_run_id':run})
        raise
    print('Original recovery returned verified ok; private receipt retained for separate readback.')

if __name__=='__main__': main()
