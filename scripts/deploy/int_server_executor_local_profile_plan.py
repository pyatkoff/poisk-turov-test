"""Read-only LOCAL #4191 registration in the existing permanent executor.

Uses the stock owner/ref checks, SSH connection, reservation and receipt.
No apply command, collector, new transport, credential or public endpoint.
"""
from __future__ import annotations

import ast
import hashlib
import io
import json
from pathlib import Path
import re
import tarfile

MODE = 'local-profile-plan-4191'
BATCH = 'local4191-20260930'
OPERATION = 'int-andromeda-local-profile-plan-4191-20261001-v2'
RUNNER = 'scripts/diagnostics/local_profile_plan_4191.php'
SOURCE_FILES = (
    'v2/data/anytour-profile-enrichment-v1.php',
    'v2/data/anytour-profile-content-sync-v1.php',
    'v2/data/anytour-canonical-catalog-v1.php',
    'v2/data/hotel-presentation-read-v1.php',
    'v2/data/hotel-details-v1.php',
)
BUNDLE_FILES = SOURCE_FILES + (RUNNER,)


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
        need(len(parts) == 4, 'local_profile_command_shape')
        source, mode, operation, batch = parts
        need(core.SHA_RE.fullmatch(source) is not None, 'source_sha')
        need(operation == OPERATION, 'local_profile_operation')
        need(batch == BATCH, 'local_profile_batch')
        return {'source_sha': source, 'mode': mode, 'operation_id': operation,
                'batch': BATCH, 'maximum_writes': 0, 'provider_http_calls': 0}

    core.parse_command = parse


def bundle_source(source_root: Path) -> tuple[bytes, dict[str, str]]:
    """Only saved-content owners from checked release plus the trusted control runner."""
    root = source_root.resolve()
    control_root = Path(__file__).resolve().parents[2]
    hashes = {}
    output = io.BytesIO()
    with tarfile.open(fileobj=output, mode='w:gz', format=tarfile.PAX_FORMAT) as archive:
        for relative in BUNDLE_FILES:
            base = control_root if relative == RUNNER else root
            path = base / relative
            need(path.is_file() and not path.is_symlink()
                 and path.resolve() == path, 'local_profile_source_path')
            data = path.read_bytes()
            need(0 < len(data) <= 2 * 1024 * 1024, 'local_profile_source_size')
            hashes[relative] = hashlib.sha256(data).hexdigest()
            info = tarfile.TarInfo(relative)
            info.size = len(data)
            info.mode = 0o600
            info.mtime = 0
            info.uid = info.gid = 0
            info.uname = info.gname = ''
            archive.addfile(info, io.BytesIO(data))
        manifest = json.dumps({'schema_version': 1, 'files': hashes},
                              sort_keys=True, separators=(',', ':')).encode()
        info = tarfile.TarInfo('manifest.json')
        info.size = len(manifest)
        info.mode = 0o600
        info.mtime = 0
        archive.addfile(info, io.BytesIO(manifest))
    return output.getvalue(), hashes


REMOTE_HANDLER = r'''
def run_local_profile_plan_4191(stage):
    if (operation!='int-andromeda-local-profile-plan-4191-20261001-v2'
            or payload.get('batch')!='local4191-20260930'
            or type(payload.get('maximum_writes')) is not int or payload['maximum_writes']!=0
            or type(payload.get('provider_http_calls')) is not int or payload['provider_http_calls']!=0
            or not isinstance(payload.get('local_profile_control_sha'),str)
            or not re.fullmatch(r'[a-f0-9]{40}',payload['local_profile_control_sha'])):
        fail('local_profile_scope')
    runner=stage/'scripts/diagnostics/local_profile_plan_4191.php'
    if not safe_file(runner,2*1024*1024): fail('local_profile_runner')
    env={k:os.environ[k] for k in ('PATH','HOME','LANG','LC_ALL') if k in os.environ}
    env.update({'ANYTOUR_ROOT':str(project),'LOCAL_PROFILE_PLAN_DIR':str(op),
                'LOCAL_PROFILE_SOURCE_SHA':source,
                'LOCAL_PROFILE_CONTROL_SHA':payload['local_profile_control_sha']})
    disabled='curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,socket_connect,exec,shell_exec,system,passthru,popen,proc_open'
    run=subprocess.run(['php','-d','display_errors=0','-d','log_errors=0',
                        '-d','allow_url_fopen=0','-d','disable_functions='+disabled,
                        str(runner),'--plan-only'],
                       cwd=project,env=env,capture_output=True,text=True,timeout=480)
    public_path=op/'local-plan-receipt.json'
    private_path=op/'local-plan.json'
    if not safe_file(public_path,65536): fail('local_profile_terminal_missing_no_replay')
    data=safe_json(public_path,65536)
    expected={'schema_version','state','operation_id','source_sha','control_source_sha','batch','requested_profiles',
              'profiles_read','aliases_validated','source_plans_prepared','classification_counts',
              'd1_exclusion_state','private_plan_sha256','provider_http_calls',
              'database_writes','profile_writes','mapping_writes','schema_writes','safe_to_apply'}
    if (set(data)!=expected or data.get('schema_version')!=1
            or data.get('operation_id')!=operation or data.get('source_sha')!=source
            or data.get('control_source_sha')!=payload['local_profile_control_sha']
            or data.get('batch')!='local4191-20260930' or data.get('requested_profiles')!=366
            or data.get('safe_to_apply') is not False
            or any(type(data.get(k)) is not int or data[k]!=0 for k in
                   ('provider_http_calls','database_writes','profile_writes','mapping_writes','schema_writes'))
            or not safe_file(private_path,32*1024*1024)
            or hashlib.sha256(private_path.read_bytes()).hexdigest()!=data.get('private_plan_sha256')):
        fail('local_profile_receipt_contract')
    for key in ('profiles_read','aliases_validated','source_plans_prepared'):
        if type(data.get(key)) is not int or not 0<=data[key]<=366:
            fail('local_profile_receipt_count')
    allowed={'PROFILE_UNAVAILABLE','ALIAS_HELD','PROFILE_INTEGRITY_HELD','D1_OVERLAP_HELD',
             'D1_MANIFEST_UNKNOWN_HELD','RETAINED_DELTA_PREPARED','RETAINED_NO_DELTA',
             'SOURCE_MISSING','SOURCE_PROVENANCE_HELD','CURRENT_DRIFT_HELD'}
    counts=data.get('classification_counts')
    if (not isinstance(counts,dict) or set(counts)-allowed
            or any(type(n) is not int or not 0<=n<=366 for n in counts.values())
            or sum(counts.values())!=366
            or data.get('d1_exclusion_state') not in ('verified_terminal_manifest','unknown_held')):
        fail('local_profile_classification_count')
    if (data['d1_exclusion_state']=='unknown_held' and data['source_plans_prepared']!=0):
        fail('local_profile_unknown_d1_plan')
    if run.returncode!=0 or run.stderr.strip() or data.get('state')!='completed_read_only':
        fail('local_profile_terminal_nonzero_no_replay')
    return data

'''

REMOTE_DISPATCH = r'''    if mode=='local-profile-plan-4191':
        result['local_profile_plan']=run_local_profile_plan_4191(stage)
        result['supplier_calls']=0
        result['database_writes']=0
        result['profile_writes']=0
        result['mapping_writes']=0
        result['schema_writes']=0
        result['production_after']=fingerprints()
        if result['production_after']!=before: fail('production_drift')
        result['production_unchanged']=True
        result['status']='complete'
'''


def remote_with_plan(core) -> str:
    remote = core.REMOTE
    definition = 'def run_match942(stage, mode, offset, limit):\n'
    dispatch = "    if mode=='match-tv942-write':\n"
    collector = "    if mode not in ('reconcile',"
    manifest = "    if not isinstance(files,dict) or len(files)<20: fail('manifest')"
    need(remote.count(definition) == 1 and remote.count(dispatch) == 1
         and remote.count(collector) == 2 and remote.count(manifest) == 1,
         'local_profile_registration_source_drift')
    remote = remote.replace(definition, REMOTE_HANDLER + definition, 1)
    remote = remote.replace(dispatch, REMOTE_DISPATCH + dispatch, 1)
    remote = remote.replace(collector, "    if mode not in ('" + MODE + "','reconcile',")
    literal = '{' + ', '.join(repr(v) for v in sorted(BUNDLE_FILES)) + '}'
    replacement = (
        "    if mode=='" + MODE + "':\n"
        "        if not isinstance(files,dict) or set(files)!=" + literal +
        ": fail('local_profile_manifest')\n"
        "    elif not isinstance(files,dict) or len(files)<20: fail('manifest')"
    )
    remote = remote.replace(manifest, replacement, 1)
    ast.parse(remote)
    return remote


def activate(core, command: dict) -> None:
    if command.get('mode') != MODE:
        return
    expected = core.parse_command(core.PREFIX + ' '.join([
        str(command.get('source_sha', '')), MODE,
        str(command.get('operation_id', '')), str(command.get('batch', '')),
    ]))
    need(command == expected, 'local_profile_authorized_shape')
    core.REMOTE = remote_with_plan(core)
    core.bundle_source = bundle_source
