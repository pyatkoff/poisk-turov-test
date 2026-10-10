"""Retained-only first initialization of the new LOCAL tables in the stock executor."""
from __future__ import annotations

import ast
import hashlib
import io
import json
from pathlib import Path
import tarfile

MODE = 'local-tv-seed-v1'
OPERATIONS = {
    'int-andromeda-local-tv-seed-inventory-20261010-v1': 'inventory',
    'int-andromeda-local-tv-seed-apply-20261010-v1': 'apply',
    'int-andromeda-local-tv-seed-readback-20261010-v1': 'readback',
    'int-andromeda-local-tv-frontier-20261010-v1': 'frontier',
}
SOURCE_HASHES = {
    'v2/data/db-v1.php': 'ca7f11d6ec53e0cd3d4464e0f645f15b761bb734da1323689479838868d4abc4',
    'v2/data/hotel-details-v1.php': '137bbf53ef87fab50505ecd41a075cb5f571193480da7f0e3face51190c0f77f',
    'v2/data/local-tv-catalog-v1.php': 'b86614f62c410a19feb90b168298c51e0ef19ca27281cddc03da4fd477a205a6',
}
CONTROL_FILES = ('scripts/diagnostics/local_tv_schema_v1.php', 'scripts/diagnostics/local_tv_seed_v1.php')
BUNDLE_FILES = (*SOURCE_HASHES, *CONTROL_FILES)


def need(value: bool, reason: str) -> None:
    if not value:
        raise ValueError(reason)


def register_parser(core) -> None:
    original = core.parse_command

    def parse(body: str) -> dict:
        if not body.startswith(core.PREFIX):
            return original(body)
        parts = body[len(core.PREFIX):].split()
        if len(parts) < 2 or parts[1] != MODE:
            return original(body)
        need(len(parts) == 3 and parts[2] in OPERATIONS, 'local_tv_seed_exact_operation')
        need(core.SHA_RE.fullmatch(parts[0]) is not None, 'source_sha')
        return {'source_sha': parts[0], 'mode': MODE, 'operation_id': parts[2],
                'action': OPERATIONS[parts[2]], 'provider_http_calls': 0, 'old_profile_writes': 0,
                'mapping_writes': 0, 'schema_writes': 0}

    core.parse_command = parse


def bundle_source(source_root: Path) -> tuple[bytes, dict[str, str]]:
    root = source_root.resolve(); control = Path(__file__).resolve().parents[2]
    hashes = {}; output = io.BytesIO()
    with tarfile.open(fileobj=output, mode='w:gz', format=tarfile.PAX_FORMAT) as archive:
        for relative in BUNDLE_FILES:
            path = (control if relative in CONTROL_FILES else root) / relative
            need(path.is_file() and not path.is_symlink() and path.resolve() == path, 'local_tv_seed_source_path')
            data = path.read_bytes(); need(0 < len(data) <= 2*1024*1024, 'local_tv_seed_source_size')
            digest = hashlib.sha256(data).hexdigest()
            if relative in SOURCE_HASHES:
                need(digest == SOURCE_HASHES[relative], 'local_tv_seed_reviewed_source_changed')
            hashes[relative] = digest
            info = tarfile.TarInfo(relative); info.size = len(data); info.mode = 0o600
            info.mtime = info.uid = info.gid = 0; info.uname = info.gname = ''
            archive.addfile(info, io.BytesIO(data))
        data = json.dumps({'schema_version': 1, 'files': hashes}, sort_keys=True, separators=(',', ':')).encode()
        info = tarfile.TarInfo('manifest.json'); info.size = len(data); info.mode = 0o600; info.mtime = 0
        archive.addfile(info, io.BytesIO(data))
    return output.getvalue(), hashes


REMOTE_HANDLER = r'''
def run_local_tv_seed(stage):
    operations={
        'int-andromeda-local-tv-seed-inventory-20261010-v1':'inventory',
        'int-andromeda-local-tv-seed-apply-20261010-v1':'apply',
        'int-andromeda-local-tv-seed-readback-20261010-v1':'readback',
        'int-andromeda-local-tv-frontier-20261010-v1':'frontier',
    }
    action=operations.get(operation)
    if (action is None or payload.get('action')!=action
            or any(type(payload.get(k)) is not int or payload[k]!=0
                   for k in ('provider_http_calls','old_profile_writes','mapping_writes','schema_writes'))
            or not isinstance(payload.get('local_tv_seed_control_sha'),str)
            or not re.fullmatch(r'[a-f0-9]{40}',payload['local_tv_seed_control_sha'])):
        fail('local_tv_seed_scope')
    env={k:os.environ[k] for k in ('PATH','HOME','LANG','LC_ALL') if k in os.environ}
    env.update({'ANYTOUR_ROOT':str(project),'LOCAL_TV_SEED_DIR':str(op),'LOCAL_TV_SEED_SOURCE_ROOT':str(stage),
                'LOCAL_TV_SEED_SOURCE_SHA':source,'LOCAL_TV_SEED_CONTROL_SHA':payload['local_tv_seed_control_sha'],
                'LOCAL_TV_SEED_OPERATION':operation})
    if action=='apply':
        parent=private/'int-andromeda-local-tv-seed-inventory-20261010-v1'
        if not safe_file(parent/'result.json',65536) or not safe_file(parent/'seed-plan.json',16*1024*1024):
            fail('local_tv_seed_plan_missing')
        outer=safe_json(parent/'result.json',65536); previous=outer.get('local_tv_seed',{})
        stamp=previous.get('observed_at')
        if (outer.get('status')!='complete' or outer.get('mode')!='local-tv-seed-v1'
                or previous.get('state')!='inventoried_read_only' or previous.get('action')!='inventory'
                or previous.get('source_sha')!=source
                or previous.get('control_source_sha')!=payload['local_tv_seed_control_sha']
                or type(stamp) is not int or not 0<=int(time.time())-stamp<=1800
                or any(v!={'present':True,'valid':True,'rows':0,'issues':[]}
                       for v in previous.get('inventory',{}).get('schema',{}).values())
                or set(previous.get('inventory',{}).get('schema',{}))!={'local_tv_hotels','local_tv_legacy_links'}):
            fail('local_tv_seed_plan_contract')
        for key in ('config_sha256','plan_sha256'):
            if not isinstance(previous.get(key),str) or not re.fullmatch(r'[a-f0-9]{64}',previous[key]):
                fail('local_tv_seed_plan_hash')
        if hashlib.sha256((parent/'seed-plan.json').read_bytes()).hexdigest()!=previous['plan_sha256']: fail('local_tv_seed_plan_changed')
        env['LOCAL_TV_SEED_EXPECTED_CONFIG_SHA256']=previous['config_sha256']
        env['LOCAL_TV_SEED_EXPECTED_PLAN_SHA256']=previous['plan_sha256']
    disabled='curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,socket_connect,exec,shell_exec,system,passthru,popen,proc_open'
    run=subprocess.run(['php','-d','display_errors=0','-d','log_errors=0','-d','allow_url_fopen=0',
                        '-d','memory_limit=512M','-d','disable_functions='+disabled,
                        str(stage/'scripts/diagnostics/local_tv_seed_v1.php'),action],
                       cwd=project,env=env,capture_output=True,text=True,timeout=600)
    path=op/'local-tv-seed-receipt.json'
    if not safe_file(path,65536): fail('local_tv_seed_terminal_missing_no_replay')
    data=safe_json(path,65536)
    allowed={'schema_version','operation_id','source_sha','control_source_sha','action','supplier_calls',
             'old_profile_writes','mapping_writes','schema_writes','state','observed_at','config_sha256',
             'inventory','plan_sha256','registered','filled','links_transferred','link_issues',
             'protected_sources_unchanged','readback','frontier','capture_runtime','error_class','error_sha256'}
    if (set(data)-allowed or data.get('schema_version')!=1 or data.get('operation_id')!=operation
            or data.get('source_sha')!=source or data.get('control_source_sha')!=payload['local_tv_seed_control_sha']
            or data.get('action')!=action
            or any(type(data.get(k)) is not int or data[k]!=0
                   for k in ('supplier_calls','old_profile_writes','mapping_writes','schema_writes'))):
        fail('local_tv_seed_receipt_contract')
    result['local_tv_seed']=data
    state={'inventory':'inventoried_read_only','apply':'initialized_retained','readback':'verified_read_only','frontier':'frontier_read_only'}[action]
    if run.returncode!=0 or run.stderr.strip() or data.get('state')!=state:
        fail('local_tv_seed_nonzero_no_replay')
    try: emitted=json.loads(run.stdout.strip())
    except Exception: fail('local_tv_seed_stdout')
    if emitted!=data: fail('local_tv_seed_stdout')
    if action=='frontier':
        if (not safe_file(op/'frontier-scope.json',16*1024*1024)
                or hashlib.sha256((op/'frontier-scope.json').read_bytes()).hexdigest()!=data.get('frontier',{}).get('scope_sha256')):
            fail('local_tv_frontier_scope_readback')
    if action=='apply':
        if (data.get('protected_sources_unchanged') is not True
                or not safe_file(op/'seed-before.json',65536) or not safe_file(op/'seed-after.json',65536)
                or safe_json(op/'seed-after.json',65536)!=data.get('readback')
                or data.get('registered')!=previous['inventory']['observed']
                or data.get('filled')!=previous['inventory']['retained_valid']):
            fail('local_tv_seed_apply_readback')
    return data

'''

REMOTE_DISPATCH = r'''    if mode=='local-tv-seed-v1':
        data=run_local_tv_seed(stage)
        result['supplier_calls']=result['profile_writes']=result['mapping_writes']=result['schema_writes']=0
        result['database_writes']=data.get('registered',0)+data.get('filled',0)+data.get('links_transferred',0)
        result['production_after']=fingerprints()
        if result['production_after']!=before: fail('production_drift')
        result['production_unchanged']=True
        result['status']='complete'
'''


def activate(core, command: dict) -> None:
    if command.get('mode') != MODE:
        return
    expected = core.parse_command(core.PREFIX + ' '.join([str(command.get('source_sha','')), MODE, str(command.get('operation_id',''))]))
    need(command == expected and all(type(command.get(k)) is int for k in
         ('provider_http_calls','old_profile_writes','mapping_writes','schema_writes')), 'local_tv_seed_authorized_shape')
    remote = core.REMOTE
    definition = 'def run_match942(stage, mode, offset, limit):\n'
    dispatch = "    if mode=='match-tv942-write':\n"
    collector = "    if mode not in ('reconcile',"
    manifest = "    if not isinstance(files,dict) or len(files)<20: fail('manifest')"
    need(remote.count(definition)==1 and remote.count(dispatch)==1 and remote.count(collector)==2
         and remote.count(manifest)==1, 'local_tv_seed_registration_drift')
    remote = remote.replace(definition, REMOTE_HANDLER + definition, 1)
    remote = remote.replace(dispatch, REMOTE_DISPATCH + dispatch, 1)
    remote = remote.replace(collector, "    if mode not in ('"+MODE+"','reconcile',")
    literal = '{' + ', '.join(repr(v) for v in sorted(BUNDLE_FILES)) + '}'
    remote = remote.replace(manifest,"    if not isinstance(files,dict) or set(files)!="+literal+": fail('local_tv_seed_manifest')",1)
    ast.parse(remote); core.REMOTE = remote; core.bundle_source = bundle_source
