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
MASS_BATCH = 'local4191-mass-retained-20261002'
MASS_OPERATION = 'int-andromeda-local-profile-mass-plan-4191-20261002-v1'
MASS_RUNNER = 'scripts/diagnostics/local_profile_mass_plan_4191.php'
MASS2_BATCH = 'local4191-mass-retained2-20261002'
MASS2_OPERATION = 'int-andromeda-local-profile-mass-plan2-4191-20261002-v1'
MASS2_RUNNER = 'scripts/diagnostics/local_profile_mass_plan2_4191.php'
MASS_PARENT_SHA = '0b0a8807bf4b7b07172561537eafe1bc865265ef926c91fa117eed1c9f618426'
SOURCE_FILES = (
    'v2/data/anytour-profile-enrichment-v1.php',
    'v2/data/anytour-profile-content-sync-v1.php',
    'v2/data/anytour-canonical-catalog-v1.php',
    'v2/data/hotel-presentation-read-v1.php',
    'v2/data/hotel-details-v1.php',
)
BUNDLE_FILES = SOURCE_FILES + (RUNNER, MASS_RUNNER, MASS2_RUNNER)


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
        need((operation, batch) in ((OPERATION, BATCH), (MASS_OPERATION, MASS_BATCH),
                                   (MASS2_OPERATION, MASS2_BATCH)),
             'local_profile_operation_batch')
        return {'source_sha': source, 'mode': mode, 'operation_id': operation,
                'batch': batch, 'maximum_writes': 0, 'provider_http_calls': 0}

    core.parse_command = parse


def bundle_source(source_root: Path) -> tuple[bytes, dict[str, str]]:
    """Only saved-content owners from checked release plus trusted control runners."""
    root = source_root.resolve()
    control_root = Path(__file__).resolve().parents[2]
    hashes = {}
    output = io.BytesIO()
    with tarfile.open(fileobj=output, mode='w:gz', format=tarfile.PAX_FORMAT) as archive:
        for relative in BUNDLE_FILES:
            base = control_root if relative in (RUNNER, MASS_RUNNER, MASS2_RUNNER) else root
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


REMOTE_MASS2_HANDLER = r'''
def run_local_profile_mass2_plan_4191(stage):
    if (operation!='int-andromeda-local-profile-mass-plan2-4191-20261002-v1'
            or payload.get('batch')!='local4191-mass-retained2-20261002'
            or type(payload.get('maximum_writes')) is not int or payload['maximum_writes']!=0
            or type(payload.get('provider_http_calls')) is not int or payload['provider_http_calls']!=0
            or not isinstance(payload.get('local_profile_control_sha'),str)
            or not re.fullmatch(r'[a-f0-9]{40}',payload['local_profile_control_sha'])):
        fail('local_mass2_scope')
    runner=stage/'scripts/diagnostics/local_profile_mass_plan2_4191.php'
    if not safe_file(runner,2*1024*1024): fail('local_mass2_runner')
    env={k:os.environ[k] for k in ('PATH','HOME','LANG','LC_ALL') if k in os.environ}
    env.update({'ANYTOUR_ROOT':str(project),'LOCAL_PROFILE_PLAN_DIR':str(op),
                'LOCAL_PROFILE_SOURCE_SHA':source,
                'LOCAL_PROFILE_CONTROL_SHA':payload['local_profile_control_sha']})
    disabled='curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,socket_connect,exec,shell_exec,system,passthru,popen,proc_open'
    run=subprocess.run(['php','-d','display_errors=0','-d','log_errors=0',
                        '-d','allow_url_fopen=0','-d','disable_functions='+disabled,
                        str(runner),'--plan-only'],
                       cwd=project,env=env,capture_output=True,text=True,timeout=480)
    public_path=op/'local-mass2-receipt.json'; private_path=op/'local-mass2-plan.json'
    if not safe_file(public_path,65536): fail('local_mass2_terminal_missing_no_replay')
    data=safe_json(public_path,65536)
    expected={'schema_version','state','operation_id','source_sha','control_source_sha','batch','demand_through',
              'predecessor_private_plan_sha256','active_profiles','census_complete','core_fields_present',
              'missing_field_counts','eligible_profiles','source_plans_prepared','profiles_with_delta','planned_fields',
              'classification_counts','safe_to_apply','predecessor_exclusion_state','predecessor_excluded_profiles',
              'd1_exclusion_state','history_exclusion_state','ready_batches','private_plan_sha256',
              'provider_http_calls','database_writes','profile_writes','mapping_writes','schema_writes'}
    if (set(data)!=expected or data.get('schema_version')!=1 or data.get('state')!='completed_read_only'
            or data.get('operation_id')!=operation or data.get('source_sha')!=source
            or data.get('control_source_sha')!=payload['local_profile_control_sha']
            or data.get('batch')!='local4191-mass-retained2-20261002'
            or data.get('predecessor_private_plan_sha256')!='0b0a8807bf4b7b07172561537eafe1bc865265ef926c91fa117eed1c9f618426'
            or data.get('predecessor_exclusion_state')!='verified_terminal_predecessor'
            or data.get('predecessor_excluded_profiles')!=2000
            or data.get('census_complete') is not True or data.get('safe_to_apply') is not False
            or any(type(data.get(k)) is not int or data[k]!=0 for k in
                   ('provider_http_calls','database_writes','profile_writes','mapping_writes','schema_writes'))
            or not safe_file(private_path,32*1024*1024)
            or hashlib.sha256(private_path.read_bytes()).hexdigest()!=data.get('private_plan_sha256')):
        fail('local_mass2_receipt_contract')
    bounds={'active_profiles':30000,'core_fields_present':30000,'eligible_profiles':30000,
            'source_plans_prepared':2000,'profiles_with_delta':2000,'planned_fields':24000,'ready_batches':8}
    for key,maximum in bounds.items():
        if type(data.get(key)) is not int or not 0<=data[key]<=maximum: fail('local_mass2_count')
    allowed={'ALIAS_HELD','PROFILE_INTEGRITY_HELD','PRIOR_OR_EDITORIAL_HELD','SCREENED_FIELDS_PRESENT',
             'HISTORICAL_366_HELD','D1_MANIFEST_UNKNOWN_HELD','D1_OVERLAP_HELD','HISTORY_UNKNOWN_HELD',
             'PREDECESSOR_2000_HELD','PREDECESSOR_UNKNOWN_HELD','PLAN_BOUND_DEFERRED','OWNER_PLAN_HELD',
             'RETAINED_DELTA_PREPARED','NO_DELTA_NOT_PROVEN_COMPLETE','SOURCE_MISSING','SOURCE_PROVENANCE_HELD'}
    counts=data.get('classification_counts'); missing=data.get('missing_field_counts')
    fields={'description','primaryImage','images','address','place','build','repair','square',
            'hotelInformation.infrastructure','hotelInformation.services','hotelInformation.meals','hotelInformation.roomTypes'}
    if (not isinstance(counts,dict) or set(counts)-allowed
            or any(type(v) is not int or v<0 for v in counts.values())
            or sum(counts.values())!=data['active_profiles']
            or counts.get('RETAINED_DELTA_PREPARED',0)!=data['profiles_with_delta']
            or counts.get('PREDECESSOR_2000_HELD',0)!=2000
            or not isinstance(missing,dict) or set(missing)-fields
            or any(type(v) is not int or not 0<=v<=data['active_profiles'] for v in missing.values())
            or not 0<=data['profiles_with_delta']<=data['source_plans_prepared']<=data['eligible_profiles']<=data['active_profiles']
            or data.get('d1_exclusion_state')!='verified_terminal_manifest'
            or data.get('history_exclusion_state')!='verified'):
        fail('local_mass2_classification')
    private=safe_json(private_path,32*1024*1024)
    for key in ('operation_id','source_sha','control_source_sha','batch','demand_through','active_profiles',
                'classification_counts','profiles_with_delta','planned_fields','safe_to_apply',
                'predecessor_private_plan_sha256'):
        if private.get(key)!=data[key]: fail('local_mass2_private_contract')
    batches=private.get('batches')
    if not isinstance(batches,list) or len(batches)!=data['ready_batches']: fail('local_mass2_batch_count')
    profiles=0
    for index,batch in enumerate(batches,1):
        if (not isinstance(batch,dict) or set(batch)!={'file','sha256','profiles','scope_profiles','plan_sha256'}
                or batch.get('file')!='mass2-batch-%03d.json'%index
                or type(batch.get('profiles')) is not int or not 1<=batch['profiles']<=250
                or type(batch.get('scope_profiles')) is not int or not batch['profiles']<=batch['scope_profiles']<=250
                or not isinstance(batch.get('sha256'),str) or not re.fullmatch(r'[a-f0-9]{64}',batch['sha256'])
                or not isinstance(batch.get('plan_sha256'),str) or not re.fullmatch(r'[a-f0-9]{64}',batch['plan_sha256'])):
            fail('local_mass2_batch_contract')
        path=op/batch['file']
        if not safe_file(path,32*1024*1024) or hashlib.sha256(path.read_bytes()).hexdigest()!=batch['sha256']:
            fail('local_mass2_batch_digest')
        profiles+=batch['profiles']
    if profiles!=data['profiles_with_delta']: fail('local_mass2_delta_count')
    if run.returncode!=0 or run.stderr.strip(): fail('local_mass2_terminal_nonzero_no_replay')
    return data

'''

REMOTE_MASS_HANDLER = r'''
def run_local_profile_mass_plan_4191(stage):
    if (operation!='int-andromeda-local-profile-mass-plan-4191-20261002-v1'
            or payload.get('batch')!='local4191-mass-retained-20261002'
            or type(payload.get('maximum_writes')) is not int or payload['maximum_writes']!=0
            or type(payload.get('provider_http_calls')) is not int or payload['provider_http_calls']!=0
            or not isinstance(payload.get('local_profile_control_sha'),str)
            or not re.fullmatch(r'[a-f0-9]{40}',payload['local_profile_control_sha'])):
        fail('local_mass_scope')
    runner=stage/'scripts/diagnostics/local_profile_mass_plan_4191.php'
    if not safe_file(runner,2*1024*1024): fail('local_mass_runner')
    env={k:os.environ[k] for k in ('PATH','HOME','LANG','LC_ALL') if k in os.environ}
    env.update({'ANYTOUR_ROOT':str(project),'LOCAL_PROFILE_PLAN_DIR':str(op),
                'LOCAL_PROFILE_SOURCE_SHA':source,
                'LOCAL_PROFILE_CONTROL_SHA':payload['local_profile_control_sha']})
    disabled='curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,socket_connect,exec,shell_exec,system,passthru,popen,proc_open'
    run=subprocess.run(['php','-d','display_errors=0','-d','log_errors=0',
                        '-d','allow_url_fopen=0','-d','disable_functions='+disabled,
                        str(runner),'--plan-only'],
                       cwd=project,env=env,capture_output=True,text=True,timeout=480)
    public_path=op/'local-mass-receipt.json'; private_path=op/'local-mass-plan.json'
    if not safe_file(public_path,65536): fail('local_mass_terminal_missing_no_replay')
    data=safe_json(public_path,65536)
    expected={'schema_version','state','operation_id','source_sha','control_source_sha','batch',
              'demand_through','active_profiles','census_complete','core_fields_present',
              'missing_field_counts','eligible_profiles','source_plans_prepared','profiles_with_delta',
              'planned_fields','classification_counts','safe_to_apply','d1_exclusion_state',
              'history_exclusion_state','ready_batches','private_plan_sha256','provider_http_calls',
              'database_writes','profile_writes','mapping_writes','schema_writes'}
    if (set(data)!=expected or data.get('schema_version')!=1
            or data.get('operation_id')!=operation or data.get('source_sha')!=source
            or data.get('control_source_sha')!=payload['local_profile_control_sha']
            or data.get('batch')!='local4191-mass-retained-20261002'
            or data.get('census_complete') is not True or data.get('safe_to_apply') is not False
            or any(type(data.get(k)) is not int or data[k]!=0 for k in
                   ('provider_http_calls','database_writes','profile_writes','mapping_writes','schema_writes'))
            or not safe_file(private_path,32*1024*1024)
            or hashlib.sha256(private_path.read_bytes()).hexdigest()!=data.get('private_plan_sha256')):
        fail('local_mass_receipt_contract')
    bounds={'active_profiles':30000,'core_fields_present':30000,'eligible_profiles':30000,
            'source_plans_prepared':2000,'profiles_with_delta':2000,'planned_fields':24000,'ready_batches':8}
    for key,maximum in bounds.items():
        if type(data.get(key)) is not int or not 0<=data[key]<=maximum: fail('local_mass_count')
    allowed={'ALIAS_HELD','PROFILE_INTEGRITY_HELD','PRIOR_OR_EDITORIAL_HELD','SCREENED_FIELDS_PRESENT',
             'HISTORICAL_366_HELD','D1_MANIFEST_UNKNOWN_HELD','D1_OVERLAP_HELD','HISTORY_UNKNOWN_HELD',
             'PLAN_BOUND_DEFERRED','OWNER_PLAN_HELD','RETAINED_DELTA_PREPARED',
             'NO_DELTA_NOT_PROVEN_COMPLETE','SOURCE_MISSING','SOURCE_PROVENANCE_HELD'}
    counts=data['classification_counts']
    fields={'description','primaryImage','images','address','place','build','repair','square',
            'hotelInformation.infrastructure','hotelInformation.services','hotelInformation.meals','hotelInformation.roomTypes'}
    missing=data['missing_field_counts']
    if (not isinstance(counts,dict) or set(counts)-allowed
            or any(type(v) is not int or v<0 for v in counts.values())
            or sum(counts.values())!=data['active_profiles']
            or counts.get('RETAINED_DELTA_PREPARED',0)!=data['profiles_with_delta']
            or not isinstance(missing,dict) or set(missing)-fields
            or any(type(v) is not int or not 0<=v<=data['active_profiles'] for v in missing.values())
            or data['core_fields_present']>data['active_profiles']
            or not 0<=data['profiles_with_delta']<=data['source_plans_prepared']<=data['eligible_profiles']<=data['active_profiles']
            or data['d1_exclusion_state'] not in ('verified_terminal_manifest','unknown_held')
            or data['history_exclusion_state'] not in ('verified','unknown_held')
            or ((data['d1_exclusion_state']=='unknown_held' or data['history_exclusion_state']=='unknown_held')
                and (data['source_plans_prepared'] or data['ready_batches']))):
        fail('local_mass_classification')
    private=safe_json(private_path,32*1024*1024)
    for key in ('operation_id','source_sha','control_source_sha','batch','demand_through','active_profiles',
                'classification_counts','profiles_with_delta','planned_fields','safe_to_apply'):
        if private.get(key)!=data[key]: fail('local_mass_private_contract')
    batches=private.get('batches')
    if not isinstance(batches,list) or len(batches)!=data['ready_batches']: fail('local_mass_batch_count')
    profiles=0
    for index,batch in enumerate(batches,1):
        if (not isinstance(batch,dict) or set(batch)!={'file','sha256','profiles','scope_profiles','plan_sha256'}
                or batch.get('file')!='mass-batch-%03d.json'%index
                or type(batch.get('profiles')) is not int or not 1<=batch['profiles']<=250
                or type(batch.get('scope_profiles')) is not int or not batch['profiles']<=batch['scope_profiles']<=250
                or not isinstance(batch.get('sha256'),str) or not re.fullmatch(r'[a-f0-9]{64}',batch['sha256'])
                or not isinstance(batch.get('plan_sha256'),str) or not re.fullmatch(r'[a-f0-9]{64}',batch['plan_sha256'])):
            fail('local_mass_batch_contract')
        path=op/batch['file']
        if not safe_file(path,32*1024*1024) or hashlib.sha256(path.read_bytes()).hexdigest()!=batch['sha256']:
            fail('local_mass_batch_digest')
        profiles+=batch['profiles']
    if profiles!=data['profiles_with_delta']: fail('local_mass_delta_count')
    if run.returncode!=0 or run.stderr.strip() or data.get('state')!='completed_read_only':
        fail('local_mass_terminal_nonzero_no_replay')
    return data

'''

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
    handler = REMOTE_HANDLER.replace('def run_local_profile_plan_4191(stage):\n',
        'def run_local_profile_plan_4191(stage):\n'
        '    if operation==' + repr(MASS2_OPERATION) + ':\n'
        '        return run_local_profile_mass2_plan_4191(stage)\n'
        '    if operation==' + repr(MASS_OPERATION) + ':\n'
        '        return run_local_profile_mass_plan_4191(stage)\n', 1)
    remote = remote.replace(definition, REMOTE_MASS2_HANDLER + REMOTE_MASS_HANDLER + handler + definition, 1)
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
