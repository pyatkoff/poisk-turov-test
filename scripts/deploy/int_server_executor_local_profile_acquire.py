"""Exact #4191 SOURCE_MISSING hotel-card acquisition via existing permanent executor."""
from __future__ import annotations
import ast,hashlib,io,json,tarfile
from pathlib import Path
MODE='local-profile-acquire-4191';BATCH='local4191-source320-20261001'
READBACK_MODE='local-profile-acquire-readback-4191';READBACK_BATCH='local4191-source320-readback-20261001'
OPERATION='int-andromeda-local-profile-acquire-4191-source320-20261001-v1'
READBACK_OPERATION='int-andromeda-local-profile-acquire-readback-4191-20261001-v1'
RUNNER='scripts/diagnostics/local_profile_acquire_4191.php'
SOURCE_FILES=('v2/data/db-v1.php','v2/data/tourvisor-client-v1.php','v2/data/hotel-details-v1.php')
BUNDLE_FILES=SOURCE_FILES+(RUNNER,)
def need(v,r):
    if not v:raise ValueError(r)
def register_parser(core):
    original=core.parse_command
    def parse(body):
        if not body.startswith(core.PREFIX):return original(body)
        parts=body[len(core.PREFIX):].split()
        if len(parts)<2 or parts[1] not in (MODE,READBACK_MODE):return original(body)
        need(len(parts)==4,'local_profile_acquire_command_shape');source,mode,operation,batch=parts
        need(core.SHA_RE.fullmatch(source) is not None,'source_sha')
        if mode==READBACK_MODE:
            need(operation==READBACK_OPERATION,'local_profile_acquire_readback_operation');need(batch==READBACK_BATCH,'local_profile_acquire_readback_batch')
            return {'source_sha':source,'mode':mode,'operation_id':operation,'batch':READBACK_BATCH,'maximum_hotel_http_calls':0,'maximum_profile_writes':0}
        need(operation==OPERATION,'local_profile_acquire_operation');need(batch==BATCH,'local_profile_acquire_batch')
        return {'source_sha':source,'mode':mode,'operation_id':operation,'batch':BATCH,'maximum_hotel_http_calls':320,'maximum_profile_writes':0}
    core.parse_command=parse
def bundle_source(source_root:Path):
    root=source_root.resolve();control=Path(__file__).resolve().parents[2];hashes={};out=io.BytesIO()
    with tarfile.open(fileobj=out,mode='w:gz',format=tarfile.PAX_FORMAT) as ar:
        for rel in BUNDLE_FILES:
            path=(control if rel==RUNNER else root)/rel;need(path.is_file() and not path.is_symlink() and path.resolve()==path,'local_profile_acquire_source_path')
            data=path.read_bytes();need(0<len(data)<=2*1024*1024,'local_profile_acquire_source_size');hashes[rel]=hashlib.sha256(data).hexdigest();info=tarfile.TarInfo(rel);info.size=len(data);info.mode=0o600;info.mtime=0;info.uid=info.gid=0;info.uname=info.gname='';ar.addfile(info,io.BytesIO(data))
        manifest=json.dumps({'schema_version':1,'files':hashes},sort_keys=True,separators=(',',':')).encode();info=tarfile.TarInfo('manifest.json');info.size=len(manifest);info.mode=0o600;ar.addfile(info,io.BytesIO(manifest))
    return out.getvalue(),hashes
REMOTE_HANDLER=r"""
def run_local_profile_acquire_4191(stage):
    if (operation!='int-andromeda-local-profile-acquire-4191-source320-20261001-v1' or payload.get('batch')!='local4191-source320-20261001'
        or payload.get('maximum_hotel_http_calls')!=320 or payload.get('maximum_profile_writes')!=0
        or not isinstance(payload.get('local_profile_control_sha'),str) or not re.fullmatch(r'[a-f0-9]{40}',payload['local_profile_control_sha'])):fail('local_profile_acquire_scope')
    runner=stage/'scripts/diagnostics/local_profile_acquire_4191.php'
    if not safe_file(runner,2*1024*1024):fail('local_profile_acquire_runner')
    env={k:os.environ[k] for k in ('PATH','HOME','LANG','LC_ALL') if k in os.environ};env.update({'ANYTOUR_ROOT':str(project),'LOCAL_PROFILE_ACQUIRE_DIR':str(op),'LOCAL_PROFILE_SOURCE_SHA':source,'LOCAL_PROFILE_CONTROL_SHA':payload['local_profile_control_sha']})
    run=subprocess.run(['php','-d','display_errors=0','-d','log_errors=0',str(runner),'--acquire-source320'],cwd=project,env=env,capture_output=True,text=True,timeout=720)
    path=op/'local-acquire-receipt.json'
    if not safe_file(path,65536):fail('local_profile_acquire_terminal_missing_no_replay')
    data=safe_json(path,65536);expected={'schema_version','state','operation_id','source_sha','control_source_sha','batch','private_plan_sha256','requested_profiles','d1_excluded','http_attempts','success','not_found','failed','catalog_detail_writes','catalog_hotel_inserts','profile_writes','mapping_writes','schema_writes','rate_interval_ms'}
    if (set(data)!=expected or data.get('schema_version')!=1 or data.get('state')!='completed_acquisition' or data.get('operation_id')!=operation or data.get('source_sha')!=source
        or data.get('control_source_sha')!=payload['local_profile_control_sha'] or data.get('batch')!='local4191-source320-20261001'
        or data.get('private_plan_sha256')!='a2306ab97e596b5df3d3eb54e85948c0e69a29237a9e96d697017db1cd902988'
        or data.get('requested_profiles')!=320 or data.get('d1_excluded')!=10 or data.get('profile_writes')!=0 or data.get('mapping_writes')!=0 or data.get('schema_writes')!=0
        or data.get('rate_interval_ms')!=550 or any(type(data.get(k)) is not int or data[k]<0 for k in ('http_attempts','success','not_found','failed','catalog_detail_writes','catalog_hotel_inserts'))
        or data['http_attempts']>320 or data['success']+data['not_found']+data['failed']!=data['http_attempts'] or data['catalog_detail_writes']!=data['http_attempts']):fail('local_profile_acquire_receipt_contract')
    if run.returncode!=0 or run.stderr.strip():fail('local_profile_acquire_terminal_nonzero_no_replay')
    return data
"""
REMOTE_READBACK=r"""
def run_local_profile_acquire_readback_4191():
    if (operation!='int-andromeda-local-profile-acquire-readback-4191-20261001-v1' or payload.get('batch')!='local4191-source320-readback-20261001'
        or payload.get('maximum_hotel_http_calls')!=0 or payload.get('maximum_profile_writes')!=0):fail('local_profile_acquire_readback_scope')
    path=home/'.anytoour-int-executor'/'int-andromeda-local-profile-acquire-4191-source320-20261001-v1'/'local-acquire-receipt.json'
    if not safe_file(path,65536):fail('local_profile_acquire_readback_missing')
    data=safe_json(path,65536)
    if not isinstance(data,dict):fail('local_profile_acquire_readback_shape')
    return data
"""
REMOTE_READBACK_DISPATCH=r"""    if mode=='local-profile-acquire-readback-4191':
        result['local_profile_acquire_readback']=run_local_profile_acquire_readback_4191();result['supplier_calls']=0;result['database_writes']=0;result['profile_writes']=0;result['mapping_writes']=0;result['schema_writes']=0
        result['production_after']=fingerprints()
        if result['production_after']!=before:fail('production_drift')
        result['production_unchanged']=True;result['status']='complete'
"""
REMOTE_DISPATCH=r"""    if mode=='local-profile-acquire-4191':
        result['local_profile_acquire']=run_local_profile_acquire_4191(stage);result['supplier_calls']=result['local_profile_acquire']['http_attempts'];result['database_writes']=result['local_profile_acquire']['catalog_detail_writes']+result['local_profile_acquire']['catalog_hotel_inserts'];result['profile_writes']=0;result['mapping_writes']=0;result['schema_writes']=0
        result['production_after']=fingerprints()
        if result['production_after']!=before:fail('production_drift')
        result['production_unchanged']=True;result['status']='complete'
"""
def remote_with_acquire(core):
    r=core.REMOTE;definition='def run_match942(stage, mode, offset, limit):\n';dispatch="    if mode=='match-tv942-write':\n";collector="    if mode not in ('reconcile',";manifest="    if not isinstance(files,dict) or len(files)<20: fail('manifest')"
    need(r.count(definition)==1 and r.count(dispatch)==1 and r.count(collector)==2 and r.count(manifest)==1,'local_profile_acquire_registration_source_drift')
    r=r.replace(definition,REMOTE_HANDLER+definition,1).replace(dispatch,REMOTE_DISPATCH+dispatch,1).replace(collector,"    if mode not in ('"+MODE+"','reconcile',")
    literal='{'+', '.join(repr(v) for v in sorted(BUNDLE_FILES))+'}';replacement="    if mode=='"+MODE+"':\n        if not isinstance(files,dict) or set(files)!="+literal+": fail('local_profile_acquire_manifest')\n    elif not isinstance(files,dict) or len(files)<20: fail('manifest')";r=r.replace(manifest,replacement,1);ast.parse(r);return r
def activate(core,command):
    mode=command.get('mode')
    if mode not in (MODE,READBACK_MODE):return
    expected=core.parse_command(core.PREFIX+' '.join([str(command.get('source_sha','')),mode,str(command.get('operation_id','')),str(command.get('batch',''))]));need(command==expected,'local_profile_acquire_authorized_shape')
    if mode==MODE:
        core.REMOTE=remote_with_acquire(core);core.bundle_source=bundle_source;return
    r=core.REMOTE;definition='def run_match942(stage, mode, offset, limit):\n';dispatch="    if mode=='match-tv942-write':\n";need(r.count(definition)==1 and r.count(dispatch)==1,'local_profile_acquire_readback_source_drift')
    core.REMOTE=r.replace(definition,REMOTE_READBACK+definition,1).replace(dispatch,REMOTE_READBACK_DISPATCH+dispatch,1);ast.parse(core.REMOTE)
