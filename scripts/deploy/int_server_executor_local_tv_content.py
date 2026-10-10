"""Fixed new-LOCAL content admission, fill and independent readback in the stock executor."""
from __future__ import annotations
import ast
import hashlib
import io
import json
from pathlib import Path
import tarfile

MODE='local-tv-content-v1'
OPERATIONS={
    'int-andromeda-local-tv-content-plan-20261010-v1':'plan',
    'int-andromeda-local-tv-content-fill-20261010-v1':'fill',
    'int-andromeda-local-tv-content-readback-20261010-v1':'readback',
}
SOURCE_HASHES={
    'v2/data/db-v1.php':'ca7f11d6ec53e0cd3d4464e0f645f15b761bb734da1323689479838868d4abc4',
    'v2/data/hotel-details-v1.php':'137bbf53ef87fab50505ecd41a075cb5f571193480da7f0e3face51190c0f77f',
    'v2/data/local-tv-catalog-v1.php':'b86614f62c410a19feb90b168298c51e0ef19ca27281cddc03da4fd477a205a6',
    'v2/data/tourvisor-client-v1.php':'f669434c2b0bf6af0a68684d02eea964173f2d3c0408e49b94db9606e8273a17',
}
CONTROL_FILES=tuple('scripts/diagnostics/'+p for p in ('local_tv_schema_v1.php','local_tv_seed_v1.php','local_tv_content_v1.php'))
BUNDLE_FILES=(*SOURCE_HASHES,*CONTROL_FILES)

def need(value,reason):
    if not value:raise ValueError(reason)

def register_parser(core):
    original=core.parse_command
    def parse(body):
        if not body.startswith(core.PREFIX):return original(body)
        p=body[len(core.PREFIX):].split()
        if len(p)<2 or p[1]!=MODE:return original(body)
        need(len(p)==3 and p[2] in OPERATIONS,'local_tv_content_exact_operation')
        need(core.SHA_RE.fullmatch(p[0]) is not None,'source_sha')
        action=OPERATIONS[p[2]]
        return {'source_sha':p[0],'mode':MODE,'operation_id':p[2],'action':action,
                'provider_http_calls':200 if action=='fill' else 0,'old_profile_writes':0,'mapping_writes':0,'schema_writes':0}
    core.parse_command=parse

def bundle_source(source_root):
    root=Path(source_root).resolve();control=Path(__file__).resolve().parents[2];out=io.BytesIO();hashes={}
    with tarfile.open(fileobj=out,mode='w:gz',format=tarfile.PAX_FORMAT) as archive:
        for relative in BUNDLE_FILES:
            path=(control if relative in CONTROL_FILES else root)/relative
            need(path.is_file() and not path.is_symlink() and path.resolve()==path,'content_source_path')
            data=path.read_bytes();need(0<len(data)<=2*1024*1024,'content_source_size');sha=hashlib.sha256(data).hexdigest()
            if relative in SOURCE_HASHES:need(sha==SOURCE_HASHES[relative],'content_reviewed_source_changed')
            hashes[relative]=sha;info=tarfile.TarInfo(relative);info.size=len(data);info.mode=0o600;info.mtime=0
            archive.addfile(info,io.BytesIO(data))
        data=json.dumps({'schema_version':1,'files':hashes},sort_keys=True,separators=(',',':')).encode()
        info=tarfile.TarInfo('manifest.json');info.size=len(data);info.mode=0o600;info.mtime=0;archive.addfile(info,io.BytesIO(data))
    return out.getvalue(),hashes

REMOTE_HANDLER=r'''
def run_local_tv_content(stage):
    operations={'int-andromeda-local-tv-content-plan-20261010-v1':'plan',
        'int-andromeda-local-tv-content-fill-20261010-v1':'fill','int-andromeda-local-tv-content-readback-20261010-v1':'readback'}
    action=operations.get(operation)
    if (action is None or payload.get('action')!=action
            or type(payload.get('provider_http_calls')) is not int or payload['provider_http_calls']!=(200 if action=='fill' else 0)
            or any(type(payload.get(k)) is not int or payload[k]!=0 for k in ('old_profile_writes','mapping_writes','schema_writes'))
            or not isinstance(payload.get('local_tv_content_control_sha'),str)
            or not re.fullmatch(r'[a-f0-9]{40}',payload['local_tv_content_control_sha'])):fail('local_tv_content_scope')
    env={k:os.environ[k] for k in ('PATH','HOME','LANG','LC_ALL') if k in os.environ}
    env.update({'ANYTOUR_ROOT':str(project),'LOCAL_TV_CONTENT_DIR':str(op),'LOCAL_TV_CONTENT_SOURCE_ROOT':str(stage),
        'LOCAL_TV_CONTENT_SOURCE_SHA':source,'LOCAL_TV_CONTENT_CONTROL_SHA':payload['local_tv_content_control_sha'],'LOCAL_TV_CONTENT_OPERATION':operation})
    if action=='plan':
        parent=private/'int-andromeda-local-tv-frontier-20261010-v1';key='local_tv_seed';expected_state='frontier_read_only';parent_action='frontier';scope_name='frontier-scope.json';hash_key='scope_sha256'
    else:
        parent=private/'int-andromeda-local-tv-content-plan-20261010-v1';key='local_tv_content';expected_state='planned_independent';parent_action='plan';scope_name='content-plan.json';hash_key='plan_sha256'
    if not safe_file(parent/'result.json',65536) or not safe_file(parent/scope_name,16*1024*1024):fail('content_parent_missing')
    outer=safe_json(parent/'result.json',65536);previous=outer.get(key,{});stamp=previous.get('observed_at')
    if (outer.get('status')!='complete' or previous.get('state')!=expected_state or previous.get('action')!=parent_action
            or previous.get('source_sha')!=('4a5d84348f6fcce0a39c288ebe7f3f72c4e790cd' if action=='plan' else source) or type(stamp) is not int
            or (action!='readback' and not 0<=int(time.time())-stamp<=(7200 if action=='plan' else 1800))):fail('content_parent_contract')
    expected=previous.get('frontier',{}).get(hash_key) if action=='plan' else previous.get(hash_key)
    config=previous.get('config_sha256')
    if any(not isinstance(v,str) or not re.fullmatch(r'[a-f0-9]{64}',v) for v in (expected,config)):fail('content_parent_hash')
    if hashlib.sha256((parent/scope_name).read_bytes()).hexdigest()!=expected:fail('content_parent_changed')
    env.update({'LOCAL_TV_CONTENT_PARENT_FILE':str(parent/scope_name),'LOCAL_TV_CONTENT_PARENT_SHA256':expected,'LOCAL_TV_CONTENT_EXPECTED_CONFIG':config})
    disabled='curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,socket_connect,exec,shell_exec,system,passthru,popen,proc_open'
    if action!='fill':disabled='curl_exec,'+disabled
    run=subprocess.run(['php','-d','display_errors=0','-d','log_errors=0','-d','allow_url_fopen=0','-d','memory_limit=512M','-d','disable_functions='+disabled,
        str(stage/'scripts/diagnostics/local_tv_content_v1.php'),action],cwd=project,env=env,capture_output=True,text=True,timeout=900)
    if not safe_file(op/'local-tv-content-receipt.json',65536):fail('content_terminal_missing_no_replay')
    data=safe_json(op/'local-tv-content-receipt.json',65536);result['local_tv_content']=data
    if data.get('state')=='unknown_no_replay':
        result['database_writes']=None;result['supplier_calls']=data.get('supplier_calls');fail('content_unknown_no_replay')
    allowed={'schema_version','operation_id','source_sha','control_source_sha','action','state','supplier_calls','old_profile_writes','mapping_writes','schema_writes',
        'database_writes','observed_at','config_sha256','plan','plan_sha256','result','readback','error_class','error_sha256'}
    if (set(data)-allowed or data.get('operation_id')!=operation or data.get('source_sha')!=source
            or data.get('control_source_sha')!=payload['local_tv_content_control_sha'] or data.get('action')!=action or data.get('schema_version')!=1
            or data.get('config_sha256')!=config
            or any(type(data.get(k)) is not int or data[k]!=0 for k in ('old_profile_writes','mapping_writes','schema_writes'))
            or type(data.get('supplier_calls')) is not int or not 0<=data['supplier_calls']<=payload['provider_http_calls']
            or type(data.get('database_writes')) is not int or not 0<=data['database_writes']<=(200 if action=='fill' else 0)):fail('content_receipt_contract')
    states={'plan':'planned_independent','fill':'filled_independent','readback':'verified_read_only'}
    if run.returncode!=0 or run.stderr.strip() or data.get('state')!=states[action]:fail('content_nonzero_no_replay')
    try:emitted=json.loads(run.stdout.strip())
    except Exception:fail('content_stdout')
    if emitted!=data:fail('content_stdout')
    if action=='plan':
        if not safe_file(op/'content-plan.json',16*1024*1024) or hashlib.sha256((op/'content-plan.json').read_bytes()).hexdigest()!=data.get('plan_sha256'):fail('content_plan_readback')
    elif action=='fill':
        if not safe_file(op/'content-after.json',65536) or safe_json(op/'content-after.json',65536)!=data.get('result'):fail('content_fill_readback')
    else:
        filled=private/'int-andromeda-local-tv-content-fill-20261010-v1'
        if not safe_file(filled/'result.json',65536):fail('content_fill_terminal_missing')
        terminal=safe_json(filled/'result.json',65536)
        if terminal.get('status')!='complete' or terminal.get('local_tv_content',{}).get('result',{}).get('readback')!=data.get('readback'):fail('content_independent_readback')
    return data

'''
REMOTE_DISPATCH=r'''    if mode=='local-tv-content-v1':
        data=run_local_tv_content(stage)
        result['supplier_calls']=data['supplier_calls'];result['database_writes']=data['database_writes']
        result['profile_writes']=result['mapping_writes']=result['schema_writes']=0
        result['production_after']=fingerprints()
        if result['production_after']!=before:fail('production_drift')
        result['production_unchanged']=True;result['status']='complete'
'''

def activate(core,command):
    if command.get('mode')!=MODE:return
    expected=core.parse_command(core.PREFIX+' '.join([str(command.get('source_sha','')),MODE,str(command.get('operation_id',''))]))
    need(command==expected and all(type(command.get(k)) is int for k in ('provider_http_calls','old_profile_writes','mapping_writes','schema_writes')),'content_authorized_shape')
    remote=core.REMOTE;definition='def run_match942(stage, mode, offset, limit):\n';dispatch="    if mode=='match-tv942-write':\n";collector="    if mode not in ('reconcile',";manifest="    if not isinstance(files,dict) or len(files)<20: fail('manifest')"
    need(remote.count(definition)==1 and remote.count(dispatch)==1 and remote.count(collector)==2 and remote.count(manifest)==1,'content_registration_drift')
    remote=remote.replace(definition,REMOTE_HANDLER+definition,1).replace(dispatch,REMOTE_DISPATCH+dispatch,1).replace(collector,"    if mode not in ('"+MODE+"','reconcile',")
    literal='{'+', '.join(repr(v) for v in sorted(BUNDLE_FILES))+'}'
    remote=remote.replace(manifest,"    if not isinstance(files,dict) or set(files)!="+literal+": fail('content_manifest')",1)
    ast.parse(remote);core.REMOTE=remote;core.bundle_source=bundle_source
