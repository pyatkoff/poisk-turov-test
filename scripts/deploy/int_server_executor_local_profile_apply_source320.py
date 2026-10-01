"""Guarded canonical apply for #4191 exact320 after supplier-free salvage."""
from __future__ import annotations
import ast,hashlib,io,json,tarfile
from pathlib import Path
MODE='local-profile-apply-source320-4191';BATCH='local4191-source320-apply-20261001';OPERATION='int-andromeda-local-profile-apply-4191-source320-20261001-v1';RUNNER='scripts/diagnostics/local_profile_apply_source320_4191.php'
SOURCE_FILES=('v2/data/anytour-profile-enrichment-v1.php','v2/data/anytour-profile-content-sync-v1.php','v2/data/anytour-canonical-catalog-v1.php','v2/data/hotel-presentation-read-v1.php','v2/data/hotel-details-v1.php');BUNDLE_FILES=SOURCE_FILES+(RUNNER,)
def need(v,r):
 if not v:raise ValueError(r)
def register_parser(core):
 original=core.parse_command
 def parse(body):
  if not body.startswith(core.PREFIX):return original(body)
  p=body[len(core.PREFIX):].split()
  if len(p)<2 or p[1]!=MODE:return original(body)
  need(len(p)==4,'source320_apply_shape');source,mode,operation,batch=p;need(core.SHA_RE.fullmatch(source) is not None,'source_sha');need(operation==OPERATION,'source320_apply_operation');need(batch==BATCH,'source320_apply_batch');return {'source_sha':source,'mode':mode,'operation_id':operation,'batch':BATCH,'maximum_profile_writes':320,'provider_http_calls':0}
 core.parse_command=parse
def bundle_source(source_root:Path):
 root=source_root.resolve();control=Path(__file__).resolve().parents[2];out=io.BytesIO();hashes={}
 with tarfile.open(fileobj=out,mode='w:gz',format=tarfile.PAX_FORMAT) as ar:
  for rel in BUNDLE_FILES:
   path=(control if rel==RUNNER else root)/rel;need(path.is_file() and not path.is_symlink() and path.resolve()==path,'source320_apply_source');data=path.read_bytes();hashes[rel]=hashlib.sha256(data).hexdigest();info=tarfile.TarInfo(rel);info.size=len(data);info.mode=0o600;info.mtime=0;info.uid=info.gid=0;info.uname=info.gname='';ar.addfile(info,io.BytesIO(data))
  m=json.dumps({'schema_version':1,'files':hashes},sort_keys=True,separators=(',',':')).encode();info=tarfile.TarInfo('manifest.json');info.size=len(m);info.mode=0o600;ar.addfile(info,io.BytesIO(m))
 return out.getvalue(),hashes
HANDLER=r"""
def run_local_profile_apply_source320(stage):
    if (operation!='int-andromeda-local-profile-apply-4191-source320-20261001-v1' or payload.get('batch')!='local4191-source320-apply-20261001' or payload.get('maximum_profile_writes')!=320 or payload.get('provider_http_calls')!=0 or not isinstance(payload.get('local_profile_control_sha'),str)):fail('source320_apply_scope')
    runner=stage/'scripts/diagnostics/local_profile_apply_source320_4191.php'
    if not safe_file(runner,2*1024*1024):fail('source320_apply_runner')
    env={k:os.environ[k] for k in ('PATH','HOME','LANG','LC_ALL') if k in os.environ};env.update({'ANYTOUR_ROOT':str(project),'LOCAL_PROFILE_APPLY_DIR':str(op),'LOCAL_PROFILE_SOURCE_SHA':source,'LOCAL_PROFILE_CONTROL_SHA':payload['local_profile_control_sha']})
    disabled='curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,socket_connect,exec,shell_exec,system,passthru,popen,proc_open'
    run=subprocess.run(['php','-d','display_errors=0','-d','log_errors=0','-d','allow_url_fopen=0','-d','disable_functions='+disabled,str(runner),'--apply-source320'],cwd=project,env=env,capture_output=True,text=True,timeout=480)
    path=op/'local-source320-apply-receipt.json'
    if not safe_file(path,65536):fail('source320_apply_terminal_missing_no_replay')
    d=safe_json(path,65536);expected={'schema_version','state','operation_id','source_sha','control_source_sha','batch','private_plan_sha256','requested_profiles','selected_profiles','profiles_updated','fields_updated','field_counts','profile_writes','provenance_writes','supplier_calls','provider_http_calls','mapping_writes','legacy_writes','schema_writes','readback_verified'}
    if (set(d)!=expected or d.get('state')!='committed_verified' or d.get('operation_id')!=operation or d.get('source_sha')!=source or d.get('requested_profiles')!=320 or d.get('private_plan_sha256')!='a2306ab97e596b5df3d3eb54e85948c0e69a29237a9e96d697017db1cd902988' or any(d.get(k)!=0 for k in ('supplier_calls','provider_http_calls','mapping_writes','legacy_writes','schema_writes')) or d.get('readback_verified') is not True or not isinstance(d.get('profiles_updated'),int) or not 0<=d['profiles_updated']<=320 or d.get('profile_writes')!=d['profiles_updated'] or d.get('provenance_writes')!=d['profiles_updated'] or d.get('selected_profiles')!=d['profiles_updated']):fail('source320_apply_receipt_contract')
    if run.returncode!=0 or run.stderr.strip():fail('source320_apply_terminal_nonzero_no_replay')
    return d
"""
DISPATCH=r"""    if mode=='local-profile-apply-source320-4191':
        result['local_profile_apply_source320']=run_local_profile_apply_source320(stage);result['supplier_calls']=0;result['database_writes']=2*result['local_profile_apply_source320']['profiles_updated'];result['profile_writes']=result['local_profile_apply_source320']['profiles_updated'];result['mapping_writes']=0;result['schema_writes']=0
        result['production_after']=fingerprints()
        if result['production_after']!=before:fail('production_drift')
        result['production_unchanged']=True;result['status']='complete'
"""
def activate(core,command):
 if command.get('mode')!=MODE:return
 expected=core.parse_command(core.PREFIX+' '.join([str(command.get('source_sha','')),MODE,str(command.get('operation_id','')),str(command.get('batch',''))]));need(command==expected,'source320_apply_authorized')
 r=core.REMOTE;definition='def run_match942(stage, mode, offset, limit):\n';dispatch="    if mode=='match-tv942-write':\n";collector="    if mode not in ('reconcile',";manifest="    if not isinstance(files,dict) or len(files)<20: fail('manifest')";need(r.count(definition)==1 and r.count(dispatch)==1 and r.count(collector)==2 and r.count(manifest)==1,'source320_apply_source_drift')
 r=r.replace(definition,HANDLER+definition,1).replace(dispatch,DISPATCH+dispatch,1).replace(collector,"    if mode not in ('"+MODE+"','reconcile',");literal='{'+', '.join(repr(v) for v in sorted(BUNDLE_FILES))+'}';replacement="    if mode=='"+MODE+"':\n        if not isinstance(files,dict) or set(files)!="+literal+": fail('source320_apply_manifest')\n    elif not isinstance(files,dict) or len(files)<20: fail('manifest')";core.REMOTE=r.replace(manifest,replacement,1);ast.parse(core.REMOTE);core.bundle_source=bundle_source
