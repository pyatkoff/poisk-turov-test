"""Reviewed retained-evidence intake in the stock carrier; no new SSH/auth route."""
from __future__ import annotations
import ast
import hashlib
import io
import json
import os
from pathlib import Path
import re
import tarfile

MODES = {'match-retained-intake': 'common4-retained',
         'match-tourvisor-quota-policy': 'tourvisor-anex-5000'}
SOURCE_FILES = ('scripts/diagnostics/hotel_match_retained_intake_v1.php',
                'scripts/diagnostics/hotel_match_retained_projection_v1.py',
                'scripts/diagnostics/hotel_match_tourvisor_quota_v1.py')
SOURCE_HASHES = {'scripts/diagnostics/hotel_match_retained_intake_v1.php': 'baf51d8339fc96c856aa584c0c7693d655723ff00418f3594d672d5b5239f520', 'scripts/diagnostics/hotel_match_retained_projection_v1.py': '2d24fa4148f46f80ac2b275e1069fd50f476954dd20511b4a65f7c5b0d405b18', 'scripts/diagnostics/hotel_match_tourvisor_quota_v1.py': '74a9735375fd40bb776c2d8e6f4af15fc477dd88dc6b879e18436e90ad5b4df5'}


def need(value, reason):
    if not value:
        raise ValueError(reason)


def register_parser(core):
    original = core.parse_command
    def parse(body):
        if not body.startswith(core.PREFIX):
            return original(body)
        parts = body[len(core.PREFIX):].split()
        if len(parts) < 2 or parts[1] not in MODES:
            return original(body)
        need(len(parts) == 4, 'retained_command_arity')
        source, mode, operation, cohort = parts
        need(core.SHA_RE.fullmatch(source) is not None, 'retained_source_sha')
        prefix = 'retained' if mode == 'match-retained-intake' else 'quota5000'
        need(re.fullmatch(r'int-andromeda-match-' + prefix + r'-[0-9]{8}-v[1-9][0-9]*', operation) is not None,
             'retained_operation_scope')
        need(cohort == MODES[mode], 'retained_cohort')
        need(os.environ.get('GITHUB_RUN_ATTEMPT', '1') == '1', 'retained_first_attempt_only')
        return {'source_sha': source, 'mode': mode, 'operation_id': operation, 'cohort': cohort}
    core.parse_command = parse


def bundle_source(source_root):
    root = Path(source_root).resolve()
    out = io.BytesIO()
    hashes = {}
    need(set(SOURCE_HASHES) == set(SOURCE_FILES), 'retained_source_policy')
    with tarfile.open(fileobj=out, mode='w:gz', format=tarfile.PAX_FORMAT) as archive:
        for relative in SOURCE_FILES:
            path = root / relative
            need(path.is_file() and not path.is_symlink() and path.resolve() == path
                 and path.stat().st_nlink == 1, 'retained_source_path')
            raw = path.read_bytes()
            need(0 < len(raw) <= 1024*1024, 'retained_source_size')
            digest = hashlib.sha256(raw).hexdigest()
            need(digest == SOURCE_HASHES[relative], 'retained_reviewed_source_changed')
            hashes[relative] = digest
            info = tarfile.TarInfo(relative)
            info.size = len(raw); info.mode = 0o600; info.mtime = 0
            archive.addfile(info, io.BytesIO(raw))
        raw = json.dumps({'schema_version': 1, 'files': hashes}, sort_keys=True, separators=(',', ':')).encode()
        info = tarfile.TarInfo('manifest.json')
        info.size = len(raw); info.mode = 0o600; info.mtime = 0
        archive.addfile(info, io.BytesIO(raw))
    return out.getvalue(), hashes


REMOTE_HANDLER = r'''
def run_match_retained(stage):
    if (payload.get('cohort')!=RETAINED_MODES.get(mode)
            or not re.fullmatch(r'[a-f0-9]{40}',str(payload.get('match_retained_control_sha','')))):
        fail('retained_remote_scope')
    config=project/'config.php';dbhelper=project/'data/db-v1.php'
    if not safe_file(config,1048576) or not safe_file(dbhelper,65536):fail('retained_config_paths')
    config_hash=hashlib.sha256(config.read_bytes()).hexdigest()
    env={k:os.environ[k] for k in ('HOME','PATH','LANG','LC_ALL') if k in os.environ}
    env['MATCH_RETAINED_DIR']=str(op)
    if mode=='match-tourvisor-quota-policy':
        env['MATCH_QUOTA_ACTION']='apply-owner-5000'
        args=['python3',str(stage/'scripts/diagnostics/hotel_match_tourvisor_quota_v1.py')]
    else:
        disabled='curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,socket_create,socket_connect,exec,system,shell_exec,passthru,proc_open,popen'
        args=['php','-d','display_errors=0','-d','log_errors=0','-d','allow_url_fopen=0',
              '-d','memory_limit=512M','-d','disable_functions='+disabled,
              str(stage/'scripts/diagnostics/hotel_match_retained_intake_v1.php')]
    run=subprocess.run(args,cwd=project,env=env,capture_output=True,text=True,timeout=600)
    if run.returncode or run.stderr.strip() or len(run.stdout.encode())>65536:
        fail('retained_child_failed_no_replay')
    data=json.loads(run.stdout)
    if mode=='match-retained-intake':
        raw=op/'retained-raw.ndjson'
        if not safe_file(raw,134217728) or raw.stat().st_nlink!=1:fail('retained_raw_missing')
        run2=subprocess.run(['python3',str(stage/'scripts/diagnostics/hotel_match_retained_projection_v1.py'),str(raw)],
            cwd=project,env=env,capture_output=True,text=True,timeout=240)
        if run2.returncode or run2.stderr.strip() or len(run2.stdout.encode())>32*1024*1024:
            fail('retained_projection_failed_no_replay')
        projected=json.loads(run2.stdout)
        if (data.get('state')!='captured_read_only' or projected.get('state')!='projected_read_only'
                or data.get('raw_sha256')!=projected.get('raw_sha256')
                or data.get('raw_bytes')!=projected.get('raw_bytes')
                or any(data.get('rows',{}).get(k)!=projected.get('capture_rows',{}).get(k,0)
                       for k in ('observation','legacy_source','identity','target'))
                or projected.get('safe_to_write_now') is not False
                or any(type(projected.get(k)) is not int or projected[k]!=0
                       for k in ('supplier_calls','database_writes','mapping_writes','new_accepted_mappings'))):
            fail('retained_capture_projection_binding')
        data={'capture':data,'projection':projected,'state':'captured_and_projected_read_only'}
    else:
        if (data.get('account')!='TOURVISOR_ANEX_JWT' or data.get('policy')!='physical-http-v1'
                or data.get('daily_physical_http_limit')!=5000 or data.get('supplier_calls')!=0
                or data.get('operations_preserved') is not True or data.get('global_usage')!='not_measured'):
            fail('retained_quota_policy_binding')
        data={'quota_policy':data,'state':'quota_policy_applied_no_supplier_calls'}
    if hashlib.sha256(config.read_bytes()).hexdigest()!=config_hash:fail('retained_config_drift')
    data.update(operation_id=operation,source_sha=source,control_source_sha=payload['match_retained_control_sha'],
        cohort=payload['cohort'],supplier_calls=0,database_writes=0,mapping_writes=0,
        safe_to_write_now=False,no_replay=True)
    raw_result=json.dumps(data,sort_keys=True,separators=(',',':')).encode()
    fd=os.open(op/'retained-evidence.json',os.O_WRONLY|os.O_CREAT|os.O_EXCL|os.O_NOFOLLOW,0o600)
    with os.fdopen(fd,'wb') as handle:
        handle.write(raw_result);handle.flush();os.fsync(handle.fileno())
    return {'evidence_sha256':hashlib.sha256(raw_result).hexdigest(),'data':data}
'''

REMOTE_DISPATCH = r'''    if mode in RETAINED_MODES:
        result['supplier_calls']=result['database_writes']=result['mapping_writes']=0
        result['match_retained']=run_match_retained(stage)
        result['production_after']=fingerprints()
        if result['production_after']!=before:fail('production_drift')
        result['production_unchanged']=True;result['status']='complete'
'''


def completed_result(command, result, control_sha):
    try:
        data = result['match_retained']['data']
        if (command['mode'] not in MODES or result.get('status') != 'complete'
                or result.get('mode') != command['mode']
                or result.get('source_sha') != command['source_sha']
                or result.get('operation_id') != command['operation_id']
                or data.get('source_sha') != command['source_sha']
                or data.get('operation_id') != command['operation_id']
                or data.get('control_source_sha') != control_sha
                or data.get('cohort') != command['cohort']
                or data.get('no_replay') is not True or data.get('safe_to_write_now') is not False
                or result.get('production_unchanged') is not True):
            return False
        for obj in (data, result):
            if any(type(obj.get(k)) is not int or obj[k] != 0 for k in ('supplier_calls','database_writes','mapping_writes')):
                return False
        expected = 'captured_and_projected_read_only' if command['mode']=='match-retained-intake' else 'quota_policy_applied_no_supplier_calls'
        return (data.get('state')==expected and result['match_retained'].get('evidence_sha256')==
                hashlib.sha256(json.dumps(data,sort_keys=True,separators=(',',':')).encode()).hexdigest())
    except (KeyError, TypeError, AttributeError):
        return False


def activate(core, command):
    if command.get('mode') not in MODES:
        return
    expected=core.parse_command(core.PREFIX+' '.join(str(command.get(k,'')) for k in ('source_sha','mode','operation_id','cohort')))
    need(command==expected,'retained_command_shape')
    control=os.environ.get('GITHUB_SHA','')
    need(re.fullmatch(r'[a-f0-9]{40}',control) is not None,'retained_control_sha')
    command['match_retained_control_sha']=control
    remote=core.REMOTE
    anchors={'definition':'def run_match942(stage, mode, offset, limit):\n',
             'dispatch':"    if mode=='match-tv942-write':\n",
             'collector':"    if mode not in ('reconcile',",
             'manifest':"    if not isinstance(files,dict) or len(files)<20: fail('manifest')"}
    for key, anchor in anchors.items():
        need(remote.count(anchor)==(2 if key=='collector' else 1),'retained_registration_drift_'+key)
    remote=remote.replace(anchors['definition'],'RETAINED_MODES='+repr(MODES)+'\n'+REMOTE_HANDLER+'\n'+anchors['definition'],1)
    remote=remote.replace(anchors['dispatch'],REMOTE_DISPATCH+anchors['dispatch'],1)
    remote=remote.replace(anchors['collector'],"    if mode not in ('match-retained-intake','match-tourvisor-quota-policy','reconcile',")
    remote=remote.replace(anchors['manifest'],"    if not isinstance(files,dict) or files!="+repr(SOURCE_HASHES)+": fail('retained_manifest')",1)
    reservation='    op.mkdir(mode=0o700)\n'
    receipt="if 'op' in globals() and op.exists():"
    need(remote.count(reservation)==1 and remote.count(receipt)==1,'retained_reservation_anchor')
    remote=remote.replace(reservation,reservation+'    retained_reserved=True\n',1)
    remote=remote.replace(receipt,"if globals().get('retained_reserved') is True and op.exists():",1)
    ast.parse(remote)
    core.REMOTE=remote;core.bundle_source=bundle_source
    original=core.completed_result
    core.completed_result=lambda cmd,res: completed_result(cmd,res,control) if cmd.get('mode') in MODES else original(cmd,res)
