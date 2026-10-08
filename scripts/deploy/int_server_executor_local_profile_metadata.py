"""Fixed metadata-only LOCAL read in the existing permanent executor.

The retained input index is an exclusion source, not a replayable content plan.
No original phase3 files, supplier cards, collector, plan or apply are invoked.
"""
from __future__ import annotations

import ast
import hashlib
import io
import json
from pathlib import Path
import tarfile

MODE = 'local-profile-plan-4191'
OPERATION = 'int-andromeda-local-metadata-read-4191-20261008-v1'
BATCH = 'local4191-metadata-20261008'
LIMIT = 250
RUNNER = 'scripts/diagnostics/local_profile_metadata_4191.php'
HELPER = 'scripts/diagnostics/local_profile_plan_4191.php'
SOURCE_FILES = (
    'v2/data/anytour-profile-enrichment-v1.php',
    'v2/data/anytour-profile-content-sync-v1.php',
    'v2/data/anytour-canonical-catalog-v1.php',
    'v2/data/hotel-presentation-read-v1.php',
    'v2/data/hotel-details-v1.php',
)
BUNDLE_FILES = SOURCE_FILES + (HELPER, RUNNER)


def need(value: bool, reason: str) -> None:
    if not value:
        raise ValueError(reason)


def register_parser(core) -> None:
    original = core.parse_command

    def parse(body: str) -> dict:
        if not body.startswith(core.PREFIX):
            return original(body)
        parts = body[len(core.PREFIX):].split()
        if len(parts) < 3 or parts[1] != MODE or parts[2] != OPERATION:
            return original(body)
        need(len(parts) == 4 and parts[3] == BATCH, 'local_metadata_command_scope')
        need(core.SHA_RE.fullmatch(parts[0]) is not None, 'source_sha')
        return {'source_sha': parts[0], 'mode': MODE, 'operation_id': OPERATION,
                'batch': BATCH, 'maximum_writes': 0, 'provider_http_calls': 0,
                'maximum_metadata_profiles': LIMIT, 'metadata_only': True}

    core.parse_command = parse


def bundle_source(source_root: Path) -> tuple[bytes, dict[str, str]]:
    root = source_root.resolve()
    control = Path(__file__).resolve().parents[2]
    hashes = {}
    output = io.BytesIO()
    with tarfile.open(fileobj=output, mode='w:gz', format=tarfile.PAX_FORMAT) as archive:
        for relative in BUNDLE_FILES:
            path = (control if relative in (HELPER, RUNNER) else root) / relative
            need(path.is_file() and not path.is_symlink() and path.resolve() == path,
                 'local_metadata_source_path')
            data = path.read_bytes()
            need(0 < len(data) <= 2 * 1024 * 1024, 'local_metadata_source_size')
            hashes[relative] = hashlib.sha256(data).hexdigest()
            info = tarfile.TarInfo(relative)
            info.size = len(data)
            info.mode = 0o600
            info.mtime = info.uid = info.gid = 0
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
def run_local_profile_metadata_4191(stage):
    if (operation!='int-andromeda-local-metadata-read-4191-20261008-v1'
            or payload.get('batch')!='local4191-metadata-20261008'
            or payload.get('metadata_only') is not True
            or type(payload.get('maximum_metadata_profiles')) is not int
            or payload['maximum_metadata_profiles']!=250
            or any(type(payload.get(k)) is not int or payload[k]!=0
                   for k in ('maximum_writes','provider_http_calls'))
            or not isinstance(payload.get('local_profile_control_sha'),str)
            or not re.fullmatch(r'[a-f0-9]{40}',payload['local_profile_control_sha'])):
        fail('local_metadata_scope')
    runner=stage/'scripts/diagnostics/local_profile_metadata_4191.php'
    if not safe_file(runner,2*1024*1024): fail('local_metadata_runner')
    env={k:os.environ[k] for k in ('PATH','HOME','LANG','LC_ALL') if k in os.environ}
    env.update({'ANYTOUR_ROOT':str(project),'LOCAL_METADATA_DIR':str(op),
                'LOCAL_PROFILE_SOURCE_SHA':source,'LOCAL_PROFILE_CONTROL_SHA':payload['local_profile_control_sha']})
    disabled='curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,socket_connect,exec,shell_exec,system,passthru,popen,proc_open'
    run=subprocess.run(['php','-d','display_errors=0','-d','log_errors=0','-d','allow_url_fopen=0',
                        '-d','disable_functions='+disabled,str(runner),'--metadata-only'],
                       cwd=project,env=env,capture_output=True,text=True,timeout=240)
    public_path=op/'local-metadata-receipt.json'
    private_path=op/'local-metadata.json';input_path=op/'local-metadata-input.json'
    if not safe_file(public_path,65536): fail('local_metadata_terminal_missing_no_replay')
    data=safe_json(public_path,65536)
    keys={'schema_version','operation_id','batch','source_sha','control_source_sha','parent_sha256',
          'requested_profiles','profiles_read','metadata_screened','aliases_validated',
          'classification_counts','missing_field_counts','state','input_sha256','metadata_sha256',
          'read_at','phase3_state','safe_to_plan','safe_to_apply','source_plans_prepared','source_cards_read',
          'provider_http_calls','database_writes','profile_writes','mapping_writes','schema_writes'}
    if (set(data)!=keys or type(data.get('schema_version')) is not int or data['schema_version']!=1 or data.get('state')!='completed_read_only'
            or data.get('operation_id')!=operation or data.get('batch')!='local4191-metadata-20261008'
            or data.get('source_sha')!=source or data.get('control_source_sha')!=payload['local_profile_control_sha']
            or data.get('parent_sha256')!='aafbc0aa015d485817ae9d851a6200f677488ea5538ab73447f2ee1dc67c84e1'
            or type(data.get('requested_profiles')) is not int or data['requested_profiles']!=250
            or data.get('phase3_state')!='UNKNOWN_NO_REPLAY'
            or data.get('safe_to_plan') is not False or data.get('safe_to_apply') is not False
            or any(type(data.get(k)) is not int or data[k]!=0 for k in
                ('source_plans_prepared','source_cards_read','provider_http_calls','database_writes',
                 'profile_writes','mapping_writes','schema_writes'))):
        fail('local_metadata_receipt_contract')
    for key in ('profiles_read','metadata_screened','aliases_validated'):
        if type(data.get(key)) is not int or not 0<=data[key]<=250: fail('local_metadata_count')
    if not data['aliases_validated']<=data['metadata_screened']<=data['profiles_read']:
        fail('local_metadata_count_order')
    for path,key in ((private_path,'metadata_sha256'),(input_path,'input_sha256')):
        if not safe_file(path,32*1024*1024) or hashlib.sha256(path.read_bytes()).hexdigest()!=data.get(key):
            fail('local_metadata_private_digest')
    private=safe_json(private_path,32*1024*1024);input_data=safe_json(input_path,32*1024*1024)
    identity=('schema_version','operation_id','batch','source_sha','control_source_sha','parent_sha256')
    if (any(private.get(k)!=data[k] or input_data.get(k)!=data[k] for k in identity)
            or private.get('input_sha256')!=data['input_sha256'] or private.get('read_at')!=data['read_at']
            or private.get('phase3_state')!='UNKNOWN_NO_REPLAY'
            or any(p.get(k) is not False for p in (private,input_data) for k in ('safe_to_plan','safe_to_apply'))
            or input_data.get('priority_basis')!='historical_mass2_demand'):
        fail('local_metadata_private_contract')
    rows=private.get('rows');scope=input_data.get('scope')
    if not isinstance(rows,list) or len(rows)!=250 or not isinstance(scope,list) or len(scope)!=250:
        fail('local_metadata_private_bound')
    wanted=[r.get('anytourHotelId') for r in scope if isinstance(r,dict)]
    actual=[r.get('anytourHotelId') for r in rows if isinstance(r,dict)]
    if (len(wanted)!=250 or wanted!=actual or any(type(v) is not int or not 0<v<=9007199254740991 for v in wanted)
            or len(set(wanted))!=250):
        fail('local_metadata_private_identity')
    allowed={'PROFILE_UNAVAILABLE','ALIAS_HELD','PROFILE_INTEGRITY_HELD','CURRENT_LINK_DRIFT_HELD',
             'D1_OVERLAP_HELD','PRIOR_OR_EDITORIAL_HELD','PHASE3_INDEPENDENCE_UNPROVEN','CURRENT_METADATA_DRIFT_HELD'}
    fields={'description','primaryImage','images','address','place','build','repair','square',
            'hotelInformation.infrastructure','hotelInformation.services','hotelInformation.meals','hotelInformation.roomTypes'}
    counts={};missing={};observed_read=observed_screened=observed_aliases=0
    for row,input_row in zip(rows,scope):
        if (set(input_row)!={'anytourHotelId','localHotelId','expectedRevision','expectedProfileSha256','expectedAliasSha256'}
                or type(input_row.get('localHotelId')) is not int or input_row['localHotelId']<=0
                or type(input_row.get('expectedRevision')) is not int or input_row['expectedRevision']!=1
                or any(not isinstance(input_row.get(k),str) or not re.fullmatch(r'[a-f0-9]{64}',input_row[k])
                       for k in ('expectedProfileSha256','expectedAliasSha256'))
                or row.get('requestedLocalHotelId')!=input_row['localHotelId']):
            fail('local_metadata_input_row')
        state=row.get('state');mask=row.get('missingFields')
        if (state not in allowed or not isinstance(mask,list) or any(not isinstance(v,str) for v in mask)
                or len(mask)!=len(set(mask))
                or any(f not in fields for f in mask)
                or row.get('safeToPlan') is not False or row.get('safeToApply') is not False
                or row.get('sourceMaterialEvaluated') is not False):
            fail('local_metadata_private_row')
        base={'anytourHotelId','requestedLocalHotelId','state','missingFields','safeToPlan','safeToApply','sourceMaterialEvaluated'}
        screened={'revision','profileSha256','priorContentOperations'};alias={'acceptedLocalHotelId','aliasSha256'}
        if not base<=set(row) or set(row)-(base|screened|alias): fail('local_metadata_private_keys')
        if state!='PROFILE_UNAVAILABLE': observed_read+=1
        if set(row)&screened:
            if (not screened<=set(row) or type(row.get('revision')) is not int or row['revision']<1
                    or type(row.get('priorContentOperations')) is not int or row['priorContentOperations']<0
                    or not isinstance(row.get('profileSha256'),str) or not re.fullmatch(r'[a-f0-9]{64}',row['profileSha256'])):
                fail('local_metadata_profile_keys')
            observed_screened+=1
        if set(row)&alias:
            if (not (screened|alias)<=set(row) or type(row.get('acceptedLocalHotelId')) is not int
                    or row['acceptedLocalHotelId']<=0 or not isinstance(row.get('aliasSha256'),str)
                    or not re.fullmatch(r'[a-f0-9]{64}',row['aliasSha256'])): fail('local_metadata_alias_keys')
            observed_aliases+=1
        counts[state]=counts.get(state,0)+1
        for field in mask: missing[field]=missing.get(field,0)+1
    if (observed_read!=data['profiles_read'] or observed_screened!=data['metadata_screened']
            or observed_aliases!=data['aliases_validated']): fail('local_metadata_private_counters')
    for key in ('classification_counts','missing_field_counts'):
        values=data.get(key)
        if not isinstance(values,dict) or any(type(v) is not int or not 0<v<=250 for v in values.values()):
            fail('local_metadata_counter_type')
    if data.get('classification_counts')!=counts or data.get('missing_field_counts')!=missing:
        fail('local_metadata_classification')
    if run.returncode!=0 or run.stderr.strip(): fail('local_metadata_terminal_nonzero_no_replay')
    try: emitted=json.loads(run.stdout.strip())
    except Exception: fail('local_metadata_stdout')
    if emitted!=data: fail('local_metadata_stdout')
    return data

'''


def activate(core, command: dict, plan_registration) -> None:
    if command.get('mode') != MODE or command.get('operation_id') != OPERATION:
        return
    expected = core.parse_command(core.PREFIX + ' '.join([
        str(command.get('source_sha', '')), MODE, OPERATION, str(command.get('batch', ''))]))
    need(command == expected and command.get('metadata_only') is True
         and all(type(command.get(k)) is int for k in
                 ('maximum_writes', 'provider_http_calls', 'maximum_metadata_profiles')),
         'local_metadata_authorized_shape')
    remote = plan_registration.remote_with_plan(core)
    definition = 'def run_local_profile_plan_4191(stage):\n'
    need(remote.count(definition) == 1, 'local_metadata_handler_anchor')
    remote = remote.replace(definition, REMOTE_HANDLER + definition
        + '    if operation==' + repr(OPERATION) + ':\n'
        + '        return run_local_profile_metadata_4191(stage)\n', 1)
    old = '{' + ', '.join(repr(p) for p in sorted(plan_registration.BUNDLE_FILES)) + '}'
    new = '{' + ', '.join(repr(p) for p in sorted(BUNDLE_FILES)) + '}'
    anchor = "if not isinstance(files,dict) or set(files)!=" + old + ": fail('local_profile_manifest')"
    need(remote.count(anchor) == 1, 'local_metadata_manifest_anchor')
    remote = remote.replace(anchor, "if not isinstance(files,dict) or set(files)!="
                            + new + ": fail('local_metadata_manifest')", 1)
    # Keep the stock reservation/SSH/production-fingerprint envelope and its existing result key.
    ast.parse(remote)
    core.REMOTE = remote
    core.bundle_source = bundle_source
