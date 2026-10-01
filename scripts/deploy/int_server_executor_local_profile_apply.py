"""Guarded retainedTV apply through the existing permanent LOCAL registration."""
from __future__ import annotations
import ast,hashlib,io,json,tarfile
from pathlib import Path
MODE='local-profile-apply-4191';BATCH='local4191-retained36-20261001'
OPERATION='int-andromeda-local-profile-apply-4191-retained36-20261001-v1'
RUNNER='scripts/diagnostics/local_profile_apply_4191.php'
MASS_OPERATION='int-andromeda-local-profile-mass-apply71-4191-20261002-v1'
MASS_BATCH='local4191-mass-retained71-20261002'
MASS_RUNNER='scripts/diagnostics/local_profile_mass_apply_4191.php'
MASS_PLAN_SHA='0b0a8807bf4b7b07172561537eafe1bc865265ef926c91fa117eed1c9f618426'
MASS2_OPERATION='int-andromeda-local-profile-mass-apply130-phase2-4191-20261002-v1'
MASS2_BATCH='local4191-mass-retained130-phase2-20261002'
MASS2_RUNNER='scripts/diagnostics/local_profile_mass_apply2_4191.php'
MASS2_PLAN_SHA='aafbc0aa015d485817ae9d851a6200f677488ea5538ab73447f2ee1dc67c84e1'
SOURCE_FILES=('v2/data/anytour-profile-enrichment-v1.php','v2/data/anytour-profile-content-sync-v1.php','v2/data/anytour-canonical-catalog-v1.php','v2/data/hotel-presentation-read-v1.php','v2/data/hotel-details-v1.php')
CONTROL_FILES=(RUNNER,MASS_RUNNER,MASS2_RUNNER,'scripts/diagnostics/local_profile_mass_plan_4191.php','scripts/diagnostics/local_profile_mass_plan2_4191.php','scripts/diagnostics/local_profile_plan_4191.php')
BUNDLE_FILES=SOURCE_FILES+CONTROL_FILES

def need(v,r):
    if not v: raise ValueError(r)
def register_parser(core):
    original=core.parse_command
    def parse(body):
        if not body.startswith(core.PREFIX): return original(body)
        parts=body[len(core.PREFIX):].split()
        if len(parts)<2 or parts[1]!=MODE:return original(body)
        need(len(parts)==4,'local_profile_apply_command_shape');source,mode,operation,batch=parts
        need(core.SHA_RE.fullmatch(source) is not None,'source_sha')
        need((operation,batch) in ((OPERATION,BATCH),(MASS_OPERATION,MASS_BATCH),(MASS2_OPERATION,MASS2_BATCH)),'local_profile_apply_operation_batch')
        maximum=130 if operation==MASS2_OPERATION else (71 if operation==MASS_OPERATION else 36)
        return {'source_sha':source,'mode':mode,'operation_id':operation,'batch':batch,
                'maximum_profile_writes':maximum,'provider_http_calls':0}
    core.parse_command=parse

def bundle_source(source_root:Path):
    root=source_root.resolve();control=Path(__file__).resolve().parents[2];hashes={};out=io.BytesIO()
    with tarfile.open(fileobj=out,mode='w:gz',format=tarfile.PAX_FORMAT) as ar:
        for rel in BUNDLE_FILES:
            path=(control if rel in CONTROL_FILES else root)/rel;need(path.is_file() and not path.is_symlink() and path.resolve()==path,'local_profile_apply_source_path')
            data=path.read_bytes();need(0<len(data)<=2*1024*1024,'local_profile_apply_source_size');hashes[rel]=hashlib.sha256(data).hexdigest()
            info=tarfile.TarInfo(rel);info.size=len(data);info.mode=0o600;info.mtime=0;info.uid=info.gid=0;info.uname=info.gname='';ar.addfile(info,io.BytesIO(data))
        manifest=json.dumps({'schema_version':1,'files':hashes},sort_keys=True,separators=(',',':')).encode();info=tarfile.TarInfo('manifest.json');info.size=len(manifest);info.mode=0o600;ar.addfile(info,io.BytesIO(manifest))
    return out.getvalue(),hashes
REMOTE_HANDLER=r"""
def run_local_profile_apply_4191(stage):
    if (operation!='int-andromeda-local-profile-apply-4191-retained36-20261001-v1' or payload.get('batch')!='local4191-retained36-20261001'
        or payload.get('maximum_profile_writes')!=36 or payload.get('provider_http_calls')!=0
        or not isinstance(payload.get('local_profile_control_sha'),str) or not re.fullmatch(r'[a-f0-9]{40}',payload['local_profile_control_sha'])): fail('local_profile_apply_scope')
    runner=stage/'scripts/diagnostics/local_profile_apply_4191.php'
    if not safe_file(runner,2*1024*1024): fail('local_profile_apply_runner')
    env={k:os.environ[k] for k in ('PATH','HOME','LANG','LC_ALL') if k in os.environ};env.update({'ANYTOUR_ROOT':str(project),'LOCAL_PROFILE_APPLY_DIR':str(op),'LOCAL_PROFILE_SOURCE_SHA':source,'LOCAL_PROFILE_CONTROL_SHA':payload['local_profile_control_sha']})
    disabled='curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,socket_connect,exec,shell_exec,system,passthru,popen,proc_open'
    run=subprocess.run(['php','-d','display_errors=0','-d','log_errors=0','-d','allow_url_fopen=0','-d','disable_functions='+disabled,str(runner),'--apply-retained36'],cwd=project,env=env,capture_output=True,text=True,timeout=240)
    path=op/'local-apply-receipt.json'
    if not safe_file(path,65536): fail('local_profile_apply_terminal_missing_no_replay')
    data=safe_json(path,65536);expected={'schema_version','state','operation_id','source_sha','control_source_sha','batch','private_plan_sha256','requested_profiles','profiles_updated','fields_updated','field_counts','profile_writes','provenance_writes','supplier_calls','provider_http_calls','mapping_writes','legacy_writes','schema_writes','readback_verified'}
    if (set(data)!=expected or data.get('schema_version')!=1 or data.get('state')!='committed_verified' or data.get('operation_id')!=operation or data.get('source_sha')!=source
        or data.get('control_source_sha')!=payload['local_profile_control_sha'] or data.get('batch')!='local4191-retained36-20261001'
        or data.get('private_plan_sha256')!='a2306ab97e596b5df3d3eb54e85948c0e69a29237a9e96d697017db1cd902988'
        or data.get('requested_profiles')!=36 or data.get('profiles_updated')!=36 or data.get('profile_writes')!=36 or data.get('provenance_writes')!=36
        or any(data.get(k)!=0 for k in ('supplier_calls','provider_http_calls','mapping_writes','legacy_writes','schema_writes')) or data.get('readback_verified') is not True
        or not isinstance(data.get('fields_updated'),int) or data['fields_updated']<36 or not isinstance(data.get('field_counts'),dict)): fail('local_profile_apply_receipt_contract')
    if run.returncode!=0 or run.stderr.strip(): fail('local_profile_apply_terminal_nonzero_no_replay')
    return data
"""
REMOTE_DISPATCH=r"""    if mode=='local-profile-apply-4191':
        result['local_profile_apply']=run_local_profile_apply_4191(stage);result['supplier_calls']=0;result['database_writes']=72;result['profile_writes']=36;result['mapping_writes']=0;result['schema_writes']=0
        result['production_after']=fingerprints()
        if result['production_after']!=before: fail('production_drift')
        result['production_unchanged']=True;result['status']='complete'
"""

MASS2_HANDLER=r"""
def run_local_profile_mass_apply130_phase2(stage):
    if (operation!='int-andromeda-local-profile-mass-apply130-phase2-4191-20261002-v1'
            or payload.get('batch')!='local4191-mass-retained130-phase2-20261002'
            or type(payload.get('maximum_profile_writes')) is not int or payload['maximum_profile_writes']!=130
            or type(payload.get('provider_http_calls')) is not int or payload['provider_http_calls']!=0
            or not isinstance(payload.get('local_profile_control_sha'),str)
            or not re.fullmatch(r'[a-f0-9]{40}',payload['local_profile_control_sha'])):
        fail('mass2_apply_scope')
    runner=stage/'scripts/diagnostics/local_profile_mass_apply2_4191.php'
    if not safe_file(runner,2*1024*1024): fail('mass2_apply_runner')
    env={k:os.environ[k] for k in ('PATH','HOME','LANG','LC_ALL') if k in os.environ}
    env.update({'ANYTOUR_ROOT':str(project),'LOCAL_PROFILE_APPLY_DIR':str(op),
                'LOCAL_PROFILE_SOURCE_SHA':source,'LOCAL_PROFILE_CONTROL_SHA':payload['local_profile_control_sha']})
    disabled='curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,socket_connect,exec,shell_exec,system,passthru,popen,proc_open'
    run=subprocess.run(['php','-d','display_errors=0','-d','log_errors=0','-d','allow_url_fopen=0',
                        '-d','disable_functions='+disabled,str(runner),'--apply-retained130'],
                       cwd=project,env=env,capture_output=True,text=True,timeout=660)
    path=op/'local-mass2-apply-receipt.json'
    if not safe_file(path,65536): fail('mass2_apply_terminal_missing_no_replay')
    data=safe_json(path,65536)
    expected={'schema_version','operation_id','batch','source_sha','control_source_sha','plan_source_sha',
              'private_plan_sha256','requested_profiles','state','profiles_verified','fields_verified',
              'field_counts','batches_verified','profile_writes','provenance_writes','readback_verified',
              'unknown_batch','supplier_calls','provider_http_calls','mapping_writes','legacy_writes','schema_writes'}
    if (set(data)!=expected or data.get('schema_version')!=1 or data.get('operation_id')!=operation
            or data.get('batch')!='local4191-mass-retained130-phase2-20261002' or data.get('source_sha')!=source
            or data.get('control_source_sha')!=payload['local_profile_control_sha']
            or data.get('plan_source_sha')!='a54255507643501abdeca150aeae19b84cb586f6'
            or data.get('private_plan_sha256')!='aafbc0aa015d485817ae9d851a6200f677488ea5538ab73447f2ee1dc67c84e1'
            or type(data.get('requested_profiles')) is not int or data['requested_profiles']!=130
            or any(type(data.get(k)) is not int or data[k]!=0 for k in
                   ('supplier_calls','provider_http_calls','mapping_writes','legacy_writes','schema_writes'))):
        fail('mass2_apply_receipt_contract')
    for key,maximum in (('profiles_verified',130),('fields_verified',1312),('batches_verified',3)):
        if type(data.get(key)) is not int or not 0<=data[key]<=maximum: fail('mass2_apply_count')
    allowed={'description','primaryImage','images','address','place','build','repair','square',
             'hotelInformation.infrastructure','hotelInformation.services','hotelInformation.meals','hotelInformation.roomTypes'}
    counts=data['field_counts']
    if counts==[] and data['fields_verified']==0: counts={};data['field_counts']={}
    if (not isinstance(counts,dict) or set(counts)-allowed
            or any(type(v) is not int or not 1<=v<=130 for v in counts.values())
            or sum(counts.values())!=data['fields_verified']): fail('mass2_apply_fields')
    state=data['state']
    if state=='committed_verified':
        if (data['profiles_verified']!=130 or data['fields_verified']!=1312 or data['batches_verified']!=3
                or type(data['profile_writes']) is not int or data['profile_writes']!=130
                or type(data['provenance_writes']) is not int or data['provenance_writes']!=130
                or data['readback_verified'] is not True or data['unknown_batch'] is not None
                or run.returncode!=0 or run.stderr.strip()): fail('mass2_apply_false_complete')
    elif state=='held_before_write':
        if (any(type(data[k]) is not int or data[k]!=0 for k in
                ('profiles_verified','fields_verified','batches_verified','profile_writes','provenance_writes'))
                or data['readback_verified'] is not False or data['unknown_batch'] is not None
                or run.returncode!=2 or run.stderr.strip()): fail('mass2_apply_false_hold')
    elif state=='unknown_no_replay':
        if (data['profile_writes']!='unknown' or data['provenance_writes']!='unknown'
                or data['readback_verified'] is not False or type(data['unknown_batch']) is not int
                or not 1<=data['unknown_batch']<=3 or run.returncode!=2 or run.stderr.strip()):
            fail('mass2_apply_unknown_contract')
    else: fail('mass2_apply_state')
    return data
"""
MASS2_DISPATCH=r"""    if mode=='local-profile-apply-4191' and operation=='int-andromeda-local-profile-mass-apply130-phase2-4191-20261002-v1':
        data=run_local_profile_mass_apply130_phase2(stage)
        result['local_profile_mass2_apply']=data
        result['supplier_calls']=0;result['mapping_writes']=0;result['schema_writes']=0
        result['profile_writes']=data['profile_writes']
        result['database_writes']=data['profile_writes']+data['provenance_writes'] if type(data['profile_writes']) is int else 'unknown'
        result['production_after']=fingerprints()
        if result['production_after']!=before: fail('production_drift')
        result['production_unchanged']=True
        result['status']='complete' if data['state']=='committed_verified' else 'unknown_no_replay'
"""

MASS_HANDLER=r"""
def run_local_profile_mass_apply71(stage):
    if (operation!='int-andromeda-local-profile-mass-apply71-4191-20261002-v1'
            or payload.get('batch')!='local4191-mass-retained71-20261002'
            or type(payload.get('maximum_profile_writes')) is not int or payload['maximum_profile_writes']!=71
            or type(payload.get('provider_http_calls')) is not int or payload['provider_http_calls']!=0
            or not isinstance(payload.get('local_profile_control_sha'),str)
            or not re.fullmatch(r'[a-f0-9]{40}',payload['local_profile_control_sha'])):
        fail('mass_apply_scope')
    runner=stage/'scripts/diagnostics/local_profile_mass_apply_4191.php'
    if not safe_file(runner,2*1024*1024): fail('mass_apply_runner')
    env={k:os.environ[k] for k in ('PATH','HOME','LANG','LC_ALL') if k in os.environ}
    env.update({'ANYTOUR_ROOT':str(project),'LOCAL_PROFILE_APPLY_DIR':str(op),
                'LOCAL_PROFILE_SOURCE_SHA':source,'LOCAL_PROFILE_CONTROL_SHA':payload['local_profile_control_sha']})
    disabled='curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,socket_connect,exec,shell_exec,system,passthru,popen,proc_open'
    run=subprocess.run(['php','-d','display_errors=0','-d','log_errors=0','-d','allow_url_fopen=0',
                        '-d','disable_functions='+disabled,str(runner),'--apply-retained71'],
                       cwd=project,env=env,capture_output=True,text=True,timeout=660)
    path=op/'local-mass-apply-receipt.json'
    if not safe_file(path,65536): fail('mass_apply_terminal_missing_no_replay')
    data=safe_json(path,65536)
    expected={'schema_version','operation_id','batch','source_sha','control_source_sha','plan_source_sha',
              'private_plan_sha256','requested_profiles','state','profiles_verified','fields_verified',
              'field_counts','batches_verified','profile_writes','provenance_writes','readback_verified',
              'unknown_batch','supplier_calls','provider_http_calls','mapping_writes','legacy_writes','schema_writes'}
    if (set(data)!=expected or data.get('schema_version')!=1 or data.get('operation_id')!=operation
            or data.get('batch')!='local4191-mass-retained71-20261002' or data.get('source_sha')!=source
            or data.get('control_source_sha')!=payload['local_profile_control_sha']
            or data.get('plan_source_sha')!='5e6797373c61f5b1ad4cb365a18cf15d66526b99'
            or data.get('private_plan_sha256')!='0b0a8807bf4b7b07172561537eafe1bc865265ef926c91fa117eed1c9f618426'
            or type(data.get('requested_profiles')) is not int or data['requested_profiles']!=71
            or any(type(data.get(k)) is not int or data[k]!=0 for k in
                   ('supplier_calls','provider_http_calls','mapping_writes','legacy_writes','schema_writes'))):
        fail('mass_apply_receipt_contract')
    for key,maximum in (('profiles_verified',71),('fields_verified',735),('batches_verified',4)):
        if type(data.get(key)) is not int or not 0<=data[key]<=maximum: fail('mass_apply_count')
    allowed={'description','primaryImage','images','address','place','build','repair','square',
             'hotelInformation.infrastructure','hotelInformation.services','hotelInformation.meals','hotelInformation.roomTypes'}
    counts=data['field_counts']
    # Empty PHP associative arrays encode as []; normalize only this exact zero-count case.
    if counts==[] and data['fields_verified']==0: counts={};data['field_counts']={}
    if (not isinstance(counts,dict) or set(counts)-allowed
            or any(type(v) is not int or not 1<=v<=71 for v in counts.values())
            or sum(counts.values())!=data['fields_verified']): fail('mass_apply_fields')
    state=data['state']
    if state=='committed_verified':
        if (data['profiles_verified']!=71 or data['fields_verified']!=735 or data['batches_verified']!=4
                or type(data['profile_writes']) is not int or data['profile_writes']!=71
                or type(data['provenance_writes']) is not int or data['provenance_writes']!=71
                or data['readback_verified'] is not True or data['unknown_batch'] is not None
                or run.returncode!=0 or run.stderr.strip()): fail('mass_apply_false_complete')
    elif state=='held_before_write':
        if (any(type(data[k]) is not int or data[k]!=0 for k in
                ('profiles_verified','fields_verified','batches_verified','profile_writes','provenance_writes'))
                or data['readback_verified'] is not False or data['unknown_batch'] is not None
                or run.returncode!=2 or run.stderr.strip()): fail('mass_apply_false_hold')
    elif state=='unknown_no_replay':
        if (data['profile_writes']!='unknown' or data['provenance_writes']!='unknown'
                or data['readback_verified'] is not False or type(data['unknown_batch']) is not int
                or not 1<=data['unknown_batch']<=4 or run.returncode!=2 or run.stderr.strip()):
            fail('mass_apply_unknown_contract')
    else: fail('mass_apply_state')
    return data
"""
MASS_DISPATCH=r"""    if mode=='local-profile-apply-4191' and operation=='int-andromeda-local-profile-mass-apply71-4191-20261002-v1':
        data=run_local_profile_mass_apply71(stage)
        result['local_profile_mass_apply']=data
        result['supplier_calls']=0;result['mapping_writes']=0;result['schema_writes']=0
        result['profile_writes']=data['profile_writes']
        result['database_writes']=data['profile_writes']+data['provenance_writes'] if type(data['profile_writes']) is int else 'unknown'
        result['production_after']=fingerprints()
        if result['production_after']!=before: fail('production_drift')
        result['production_unchanged']=True
        result['status']='complete' if data['state']=='committed_verified' else 'unknown_no_replay'
"""

def remote_with_apply(core):
    r=core.REMOTE;definition='def run_match942(stage, mode, offset, limit):\n';dispatch="    if mode=='match-tv942-write':\n";collector="    if mode not in ('reconcile',";manifest="    if not isinstance(files,dict) or len(files)<20: fail('manifest')"
    need(r.count(definition)==1 and r.count(dispatch)==1 and r.count(collector)==2 and r.count(manifest)==1,'local_profile_apply_registration_source_drift')
    old_dispatch=REMOTE_DISPATCH.replace("    if mode=='local-profile-apply-4191':", "    if mode=='local-profile-apply-4191' and operation!="+repr(MASS_OPERATION)+" and operation!="+repr(MASS2_OPERATION)+":",1)
    old_mass_dispatch=MASS_DISPATCH.replace("    if mode=='local-profile-apply-4191' and operation=='int-andromeda-local-profile-mass-apply71-4191-20261002-v1':", "    if mode=='local-profile-apply-4191' and operation=="+repr(MASS_OPERATION)+":",1)
    r=r.replace(definition,MASS2_HANDLER+MASS_HANDLER+REMOTE_HANDLER+definition,1).replace(dispatch,MASS2_DISPATCH+old_mass_dispatch+old_dispatch+dispatch,1).replace(collector,"    if mode not in ('"+MODE+"','reconcile',")
    literal='{'+', '.join(repr(v) for v in sorted(BUNDLE_FILES))+'}';replacement="    if mode=='"+MODE+"':\n        if not isinstance(files,dict) or set(files)!="+literal+": fail('local_profile_apply_manifest')\n    elif not isinstance(files,dict) or len(files)<20: fail('manifest')"
    r=r.replace(manifest,replacement,1);ast.parse(r);return r

def activate(core,command):
    if command.get('mode')!=MODE:return
    expected=core.parse_command(core.PREFIX+' '.join([str(command.get('source_sha','')),MODE,str(command.get('operation_id','')),str(command.get('batch',''))]));need(command==expected,'local_profile_apply_authorized_shape')
    core.REMOTE=remote_with_apply(core);core.bundle_source=bundle_source
