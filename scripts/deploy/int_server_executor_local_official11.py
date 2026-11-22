"""Closed owner-approved official LOCAL profiles in the permanent server carrier.

The consumed LOCAL11 command, manifest and runner pins remain immutable. LOCAL26
is a second finite admission through the same transport and canonical DB owner.
"""
from __future__ import annotations

import ast
import hashlib
import io
import json
import os
from pathlib import Path
import re
import tarfile

MODE = 'local-official11-apply-v1'
OPERATION = 'int-andromeda-local-official11-apply-20261121-v1'
# Filled only after the reviewed SOURCE change is merged to the release branch.
SOURCE_SHA = '8b804957501d5b4715bd6f400fb794f02792d3de'
APPROVAL_REF = 'https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-7063885208'
ORIGINAL_PACKAGE_SHA = '01090549e278cbd739efa4936472c256724f6c555bd0d44864d6d21d2ce15123'
APPROVED_SHA = '93e0ea33e64ce2c41d68ffb54e9efaf6e383f3cdde68f01c6aa4ab51f4e5a242'
ACTIONS_SHA = '45e11557da29ecc652589e5cc89a6dcfa457b57e1a8936fe87d57d2ceb12edb4'
IDS = (5371, 6014, 6202, 5160, 5794, 4516, 7218, 12008, 5598, 10771, 8689)
FIELDS = ('description', 'primaryImage', 'images')
DB_HELPER_SHA = 'c1c841d38c62845bc36e559183da17e0411c1c5bdd909517ffc02b5fbb1e2f13'
RUNNER = 'scripts/catalog/anytour_local_official11_apply_v1.php'
APPROVED = 'scripts/catalog/anytour-local-official11-approved-20261121.json'
RUNNER_SHA = '94db2b7f7e6d38fce9550040a691e4c545928ae730b2387be166d3f91dc135cc'
SOURCE_HASHES = {
    'v2/data/anytour-profile-enrichment-v1.php': 'f670e20c219d6d056f8732b81d43648cd4368779b0abbde80e803bf229b5b730',
    'v2/data/anytour-profile-official-review-v1.php': '0aa1f0e8e1c8193f95f7d163bb927757eefdd4115ce558476c289533a732e6aa',
    'v2/data/anytour-profile-content-sync-v1.php': 'cfb3868bd4c732351637b31dd448612d0eb3970f550d5a14200a1b8d8c9e4487',
    'v2/data/anytour-canonical-catalog-v1.php': '06e737f7b00bfe17c1f9410c19f880d65b0bfad3345f0d79e62727282601041a',
    'v2/data/hotel-presentation-read-v1.php': '17c9c1ea19de0d86bf6d2d4f13426a8a0cbf232686abd63ec1db5414ccc3d9a3',
    'v2/data/hotel-details-v1.php': '137bbf53ef87fab50505ecd41a075cb5f571193480da7f0e3face51190c0f77f',
}

# The reviewed SOURCE candidate is pinned for preparation CI. Runtime still requires
# exact current release HEAD; final merge repinning needs the bounded HOME handoff.
# The original LOCAL11 constants above are immutable historical evidence.
MODE26 = 'local-official26-apply-v1'
OPERATION26 = 'int-andromeda-local-official26-apply-20261122-v1'
SOURCE_SHA26 = '6af9180edbfa3a2b15199cbd8ad04842ea42dfb3'
APPROVAL_REF26 = 'https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-7077944229'
ORIGINAL_PACKAGE_SHA26 = '9ceba785b8cbb25db3e4aaec0ec34d303e559dbe33577175f78a2889c631c79e'
APPROVED_SHA26 = '7843b25c32c7adb8fa88f262aa870d8a469727ac3681f038ae418b98c0375138'
ACTIONS_SHA26 = 'd3e1c159a107638e069b5e6e003bf9ad085e2188be93ba1a2c78f75080db7c4e'
IDS26 = (4511,10334,8751,4679,14397,13766,7599,14932,12248,4609,12520,12839,
         13686,13066,4533,7619,9843,9842,10377,4172,4539,4538,4601,14029,12816,14013)
FIELD_CAPS26 = {'description': 26, 'primaryImage': 25, 'images': 25}
RUNNER26 = 'scripts/catalog/anytour_local_official26_apply_v1.php'
APPROVED26 = 'scripts/catalog/anytour-local-official26-approved-20261122.json'
RUNNER_SHA26 = '4a18a8abc8420aeb32207eacb58dc6a42b0e41081d1c9656848194c3d782136b'
SOURCE_HASHES26 = {'v2/data/anytour-profile-enrichment-v1.php': '2e20fe89d6e663691ca5597841d219d520b1c5fdf9832e798b63272ec64e176b', 'v2/data/anytour-profile-official-review-v1.php': '3b74510fa0e488e42e04cb8e4e9e8e4dac7a3638e4cfb4b8127292f1a4187574', 'v2/data/anytour-profile-content-sync-v1.php': 'cfb3868bd4c732351637b31dd448612d0eb3970f550d5a14200a1b8d8c9e4487', 'v2/data/anytour-canonical-catalog-v1.php': '06e737f7b00bfe17c1f9410c19f880d65b0bfad3345f0d79e62727282601041a', 'v2/data/hotel-presentation-read-v1.php': '17c9c1ea19de0d86bf6d2d4f13426a8a0cbf232686abd63ec1db5414ccc3d9a3', 'v2/data/hotel-details-v1.php': '137bbf53ef87fab50505ecd41a075cb5f571193480da7f0e3face51190c0f77f'}


def need(value, reason):
    if not value:
        raise ValueError(reason)


def canonical(value):
    return json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(',', ':')).encode()


def unique(pairs):
    value = {}
    for key, item in pairs:
        need(key not in value, 'official11_duplicate_json_key')
        value[key] = item
    return value


def fixed_command():
    return {'source_sha': SOURCE_SHA, 'mode': MODE, 'operation_id': OPERATION,
            'manifest_sha': APPROVED_SHA, 'actions_sha': ACTIONS_SHA,
            'maximum_profile_writes': 11, 'maximum_field_writes': 33,
            'provider_http_calls': 0}


def fixed_command26():
    return {'source_sha': SOURCE_SHA26, 'mode': MODE26, 'operation_id': OPERATION26,
            'manifest_sha': APPROVED_SHA26, 'actions_sha': ACTIONS_SHA26,
            'maximum_profile_writes': 26, 'maximum_field_writes': 76,
            'provider_http_calls': 0}


def register_parser(core):
    original = core.parse_command

    def parse(body):
        parts = body[len(core.PREFIX):].split() if body.startswith(core.PREFIX) else []
        if len(parts) < 2 or parts[1] not in (MODE, MODE26):
            return original(body)
        if parts[1] == MODE26:
            need(SOURCE_SHA26 != '0' * 40 and core.SHA_RE.fullmatch(SOURCE_SHA26), 'official26_source_unreviewed')
            need(parts == [SOURCE_SHA26, MODE26, OPERATION26], 'official26_fixed_command')
            need(os.environ.get('GITHUB_RUN_ATTEMPT') == '1', 'official26_first_attempt')
            return fixed_command26()
        need(SOURCE_SHA != '0' * 40 and core.SHA_RE.fullmatch(SOURCE_SHA), 'official11_source_unreviewed')
        need(parts == [SOURCE_SHA, MODE, OPERATION], 'official11_fixed_command')
        need(os.environ.get('GITHUB_RUN_ATTEMPT') == '1', 'official11_first_attempt')
        return fixed_command()
    core.parse_command = parse


def approved_manifest():
    path = Path(__file__).resolve().parents[2] / APPROVED
    need(path.is_file() and not path.is_symlink() and path.resolve() == path, 'official11_approved_path')
    raw = path.read_bytes()
    need(0 < len(raw) <= 262144 and hashlib.sha256(raw).hexdigest() == APPROVED_SHA,
         'official11_approved_digest')
    data = json.loads(raw, object_pairs_hook=unique)
    need(set(data) == {'schema_version', 'source_policy', 'original_package_sha256',
                      'approval_ref', 'proposed_actions', 'proposed_actions_sha256', 'targets'},
         'official11_approved_shape')
    need(data['schema_version'] == 1 and type(data['schema_version']) is int
         and data['source_policy'] == 'official_review_missing_only_v1'
         and data['original_package_sha256'] == ORIGINAL_PACKAGE_SHA
         and data['approval_ref'] == APPROVAL_REF
         and data['proposed_actions_sha256'] == ACTIONS_SHA
         and hashlib.sha256(canonical(data['proposed_actions'])).hexdigest() == ACTIONS_SHA,
         'official11_approved_scope')
    need([a['anytourHotelId'] for a in data['proposed_actions']] == list(IDS)
         and [a['anytourHotelId'] for a in data['targets']] == list(IDS), 'official11_roster')
    for action in data['proposed_actions']:
        need([f['field'] for f in action['fields']] == list(FIELDS)
             and action['observedRevision'] == 1
             and action['mutation'] == 'fill_only_if_stored_field_still_missing', 'official11_fields')
    need(sum(len(t['photos']) for t in data['targets']) == 25, 'official11_photos')
    return data


def approved_manifest26():
    path = Path(__file__).resolve().parents[2] / APPROVED26
    need(path.is_file() and not path.is_symlink() and path.resolve() == path, 'official26_approved_path')
    raw = path.read_bytes()
    need(0 < len(raw) <= 131072 and hashlib.sha256(raw).hexdigest() == APPROVED_SHA26,
         'official26_approved_digest')
    data = json.loads(raw, object_pairs_hook=unique)
    need(set(data) == {'schema_version', 'source_policy', 'original_package_sha256',
                      'approval_ref', 'proposed_actions', 'proposed_actions_sha256', 'targets'},
         'official26_approved_shape')
    need(type(data['schema_version']) is int and data['schema_version'] == 2
         and data['source_policy'] == 'official_review_missing_only_v2'
         and data['original_package_sha256'] == ORIGINAL_PACKAGE_SHA26
         and data['approval_ref'] == APPROVAL_REF26
         and data['proposed_actions_sha256'] == ACTIONS_SHA26
         and hashlib.sha256(canonical(data['proposed_actions'])).hexdigest() == ACTIONS_SHA26,
         'official26_approved_scope')
    need([a['anytour_hotel_id'] for a in data['proposed_actions']] == list(IDS26)
         and [t['anytourHotelId'] for t in data['targets']] == list(IDS26), 'official26_roster')
    counts = dict.fromkeys(FIELDS, 0)
    for action, target in zip(data['proposed_actions'], data['targets']):
        expected = ['description'] if action['anytour_hotel_id'] == 4172 else list(FIELDS)
        need([f['field'] for f in action['field_changes']] == expected
             and action['observed_revision'] == 1, 'official26_fields')
        for field in action['field_changes']:
            need(field['before'] == ([] if field['field'] == 'images' else None), 'official26_empty_before')
            counts[field['field']] += 1
        need(len(target['photos']) == (0 if action['anytour_hotel_id'] == 4172 else 2), 'official26_photos')
        for source in target['sources']:
            need(set(source) == {'url', 'checked_at_utc', 'content_sha256', 'capture_format'}
                 and isinstance(source['content_sha256'], str)
                 and re.fullmatch('[a-f0-9]{64}', source['content_sha256']) is not None
                 and source['capture_format'] in ('raw_html', 'retrieved_web_text_not_origin_html'),
                 'official26_source_capture')
    need(counts == FIELD_CAPS26, 'official26_field_totals')
    return data


def pins():
    result = {RUNNER: RUNNER_SHA, APPROVED: APPROVED_SHA, **SOURCE_HASHES}
    need(all(isinstance(v, str) and re.fullmatch('[a-f0-9]{64}', v) and v != '0' * 64
             for v in result.values()), 'official11_unreviewed_source')
    return result


def pins26():
    # The unchanged LOCAL11 runner supplies pure file/JSON helpers. Its entry
    # guard prevents lo11_main from running when required by the LOCAL26 runner.
    result = {RUNNER: RUNNER_SHA, RUNNER26: RUNNER_SHA26, APPROVED26: APPROVED_SHA26, **SOURCE_HASHES26}
    need(all(isinstance(v, str) and re.fullmatch('[a-f0-9]{64}', v) and v != '0' * 64
             for v in result.values()), 'official26_unreviewed_source')
    return result


def bundle_source(source_root):
    approved_manifest()
    root = Path(source_root).resolve()
    control = Path(__file__).resolve().parents[2]
    hashes = pins()
    output = io.BytesIO()
    with tarfile.open(fileobj=output, mode='w:gz', format=tarfile.PAX_FORMAT) as archive:
        for relative, expected in hashes.items():
            path = (control if relative in (RUNNER, APPROVED) else root) / relative
            need(path.is_file() and not path.is_symlink() and path.resolve() == path,
                 'official11_source_path')
            raw = path.read_bytes()
            need(0 < len(raw) <= 2097152 and hashlib.sha256(raw).hexdigest() == expected,
                 'official11_source_drift')
            info = tarfile.TarInfo(relative)
            info.size, info.mode, info.mtime = len(raw), 0o600, 0
            archive.addfile(info, io.BytesIO(raw))
        raw = canonical({'schema_version': 1, 'files': hashes})
        info = tarfile.TarInfo('manifest.json')
        info.size, info.mode, info.mtime = len(raw), 0o600, 0
        archive.addfile(info, io.BytesIO(raw))
    return output.getvalue(), hashes


def bundle_source26(source_root):
    approved_manifest26()
    root = Path(source_root).resolve()
    control = Path(__file__).resolve().parents[2]
    hashes = pins26()
    output = io.BytesIO()
    with tarfile.open(fileobj=output, mode='w:gz', format=tarfile.PAX_FORMAT) as archive:
        for relative, expected in hashes.items():
            path = (control if relative in (RUNNER, RUNNER26, APPROVED26) else root) / relative
            need(path.is_file() and not path.is_symlink() and path.resolve() == path, 'official26_source_path')
            raw = path.read_bytes()
            need(0 < len(raw) <= 2097152 and hashlib.sha256(raw).hexdigest() == expected, 'official26_source_drift')
            info = tarfile.TarInfo(relative)
            info.size, info.mode, info.mtime = len(raw), 0o600, 0
            archive.addfile(info, io.BytesIO(raw))
        raw = canonical({'schema_version': 1, 'files': hashes})
        info = tarfile.TarInfo('manifest.json')
        info.size, info.mode, info.mtime = len(raw), 0o600, 0
        archive.addfile(info, io.BytesIO(raw))
    return output.getvalue(), hashes


def policy(control):
    return {'command': fixed_command(), 'control_sha': control, 'approval_ref': APPROVAL_REF,
            'original_package_sha': ORIGINAL_PACKAGE_SHA, 'ids': list(IDS),
            'fields': list(FIELDS), 'field_caps': dict.fromkeys(FIELDS, 11),
            'db_helper_sha': DB_HELPER_SHA, 'hashes': pins(), 'runner': RUNNER,
            'runner_argument': '--apply-approved11', 'environment_prefix': 'LOCAL_OFFICIAL11',
            'receipt_name': 'local-official11-receipt.json', 'result_key': 'local_official11',
            'payload_control_key': 'local_official11_control_sha'}


def policy26(control):
    return {'command': fixed_command26(), 'control_sha': control, 'approval_ref': APPROVAL_REF26,
            'original_package_sha': ORIGINAL_PACKAGE_SHA26, 'ids': list(IDS26),
            'fields': list(FIELDS), 'field_caps': dict(FIELD_CAPS26),
            'db_helper_sha': DB_HELPER_SHA, 'hashes': pins26(), 'runner': RUNNER26,
            'runner_argument': '--apply-approved26', 'environment_prefix': 'LOCAL_OFFICIAL26',
            'receipt_name': 'local-official26-receipt.json', 'result_key': 'local_official26',
            'payload_control_key': 'local_official26_control_sha'}


VALIDATOR = r'''
def official11_json(value):
    return json.dumps(value,ensure_ascii=False,sort_keys=True,separators=(',',':')).encode()
def official11_unique(pairs):
    value={}
    for key,item in pairs:
        if key in value: raise ValueError('official11_duplicate_json_key')
        value[key]=item
    return value
def official11_terminal(data,p):
    digest=lambda v:isinstance(v,str) and re.fullmatch(r'[a-f0-9]{64}',v) is not None
    keys={'schema_version','operation_id','source_sha','control_sha','manifest_sha256','actions_sha256',
          'original_package_sha256','approval_ref','state','requested_profiles','requested_fields',
          'plan_sha256','profiles_updated','fields_updated','field_counts','preserved_fields',
          'profile_writes','provenance_writes','database_writes','supplier_calls','provider_http_calls',
          'mapping_writes','legacy_writes','schema_writes','readback_verified','consumer_readback_verified',
          'consumer_sha256','owner_receipt_sha256','protected_before','protected_after','error_code'}
    if not isinstance(data,dict) or set(data)!=keys: return False
    c=p['command']
    if (type(data['schema_version']) is not int or data['schema_version']!=1
        or data['operation_id']!=c['operation_id'] or data['source_sha']!=c['source_sha']
        or data['control_sha']!=p['control_sha'] or data['manifest_sha256']!=c['manifest_sha']
        or data['actions_sha256']!=c['actions_sha'] or data['approval_ref']!=p['approval_ref']
        or data['original_package_sha256']!=p['original_package_sha']
        or type(data['requested_profiles']) is not int or data['requested_profiles']!=c['maximum_profile_writes']
        or type(data['requested_fields']) is not int or data['requested_fields']!=c['maximum_field_writes']): return False
    if any(type(data[k]) is not int or data[k]!=0 for k in
           ('supplier_calls','provider_http_calls','mapping_writes','legacy_writes','schema_writes')): return False
    for key in ('protected_before','protected_after'):
        value=data[key]
        if value is not None and (not isinstance(value,dict) or set(value)!={'config.php','data/db-v1.php'}
            or any(not digest(v) for v in value.values()) or value['data/db-v1.php']!=p['db_helper_sha']): return False
    if data['plan_sha256'] is not None and not digest(data['plan_sha256']): return False
    if data['error_code'] is not None and (not isinstance(data['error_code'],str)
        or re.fullmatch(r'[A-Z0-9_]{1,96}',data['error_code']) is None): return False
    if data['state']=='unknown_no_replay':
        return (all(data[k]=='unknown' for k in ('profiles_updated','fields_updated','preserved_fields',
                'profile_writes','provenance_writes','database_writes'))
                and data['field_counts'] is None and data['readback_verified'] is False
                and data['consumer_readback_verified'] is False and data['consumer_sha256'] is None
                and data['owner_receipt_sha256'] is None and data['error_code'] is not None)
    counts=data['field_counts']
    if not isinstance(counts,dict) or set(counts)!=set(p['fields']) or any(
        type(v) is not int or not 0<=v<=p['field_caps'][k] for k,v in counts.items()): return False
    profiles=c['maximum_profile_writes']; fields=c['maximum_field_writes']
    for key,maximum in (('profiles_updated',profiles),('fields_updated',fields),('preserved_fields',fields),
                        ('profile_writes',profiles),('provenance_writes',profiles),('database_writes',2*profiles)):
        if type(data[key]) is not int or not 0<=data[key]<=maximum: return False
    if (sum(counts.values())!=data['fields_updated'] or data['profile_writes']!=data['profiles_updated']
        or data['provenance_writes']!=data['profiles_updated']
        or data['database_writes']!=2*data['profiles_updated']
        or not data['profiles_updated']<=data['fields_updated']<=3*data['profiles_updated']
        or any(v>data['profiles_updated'] for v in counts.values())): return False
    if data['state']=='held_before_write':
        return (all(data[k]==0 for k in ('profiles_updated','fields_updated','preserved_fields',
                'profile_writes','provenance_writes','database_writes'))
                and data['readback_verified'] is False and data['consumer_readback_verified'] is False
                and data['owner_receipt_sha256'] is None and data['consumer_sha256'] is None
                and data['error_code'] is not None)
    return (data['state']=='committed_verified' and digest(data['plan_sha256'])
            and data['preserved_fields']+data['fields_updated']==fields
            and (data['profiles_updated']==0)==(data['fields_updated']==0)
            and data['readback_verified'] is True and data['consumer_readback_verified'] is True
            and digest(data['owner_receipt_sha256']) and digest(data['consumer_sha256'])
            and data['protected_before'] is not None and data['protected_before']==data['protected_after']
            and data['error_code'] is None)
'''
exec(VALIDATOR)


def completed(command, result, control, selected_policy=None):
    p = policy(control) if selected_policy is None else selected_policy
    result_key = p['result_key']
    keys = {'schema_version', 'operation_id', 'source_sha', 'mode', 'status', 'supplier_calls',
            'database_writes', 'booking_calls', 'lead_calls', 'production_before', 'production_after',
            'production_unchanged', 'profile_writes', 'provenance_writes', 'mapping_writes',
            'legacy_writes', 'schema_writes', result_key}
    if not isinstance(result, dict) or set(result) != keys or command != p['command']:
        return False
    if (type(result['schema_version']) is not int or result['schema_version'] != 1
            or any(result[k] != command[k] for k in ('operation_id', 'source_sha', 'mode'))
            or result['status'] != 'complete' or result['production_unchanged'] is not True):
        return False
    before, after = result['production_before'], result['production_after']
    if (not isinstance(before, dict) or set(before) != {'index.php', 'v2/index.php', 'v2/api-v2.php', 'v2/lead-adapter-v2.php'}
            or before != after or any(v is not None and re.fullmatch('[a-f0-9]{64}', str(v)) is None for v in before.values())):
        return False
    for key in ('supplier_calls', 'booking_calls', 'lead_calls', 'mapping_writes', 'legacy_writes', 'schema_writes'):
        if type(result[key]) is not int or result[key] != 0:
            return False
    data = result[result_key]
    if not official11_terminal(data, p) or data['state'] != 'committed_verified':
        return False
    return all(type(result[k]) is int and result[k] == data[k]
               for k in ('profile_writes', 'provenance_writes', 'database_writes'))


REMOTE_HANDLER = r'''
def run_official11(stage):
    p=OFFICIAL11_POLICY
    if set(payload)!={*p['command'],'archive','manifest_sha256',p['payload_control_key']}:
        fail('official11_payload_shape')
    if any(payload.get(k)!=v or type(payload.get(k)) is not type(v) for k,v in p['command'].items()):
        fail('official11_scope')
    if payload.get(p['payload_control_key'])!=p['control_sha']: fail('official11_control')
    helper=project/'data/db-v1.php'; config=project/'config.php'
    for path in (helper,config):
        if not safe_file(path,2*1024*1024) or path.resolve()!=path: fail('official11_private_path')
    if hashlib.sha256(helper.read_bytes()).hexdigest()!=p['db_helper_sha']: fail('official11_db_helper_drift')
    protected={'config.php':hashlib.sha256(config.read_bytes()).hexdigest(),'data/db-v1.php':p['db_helper_sha']}
    runner=stage/p['runner']
    env={k:os.environ[k] for k in ('PATH','HOME','LANG','LC_ALL') if k in os.environ}
    prefix=p['environment_prefix']
    env.update({'ANYTOUR_ROOT':str(project),prefix+'_DIR':str(op),
        prefix+'_SOURCE_ROOT':str(stage),prefix+'_SOURCE_SHA':source,
        prefix+'_CONTROL_SHA':p['control_sha']})
    disabled='curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,socket_connect,exec,shell_exec,system,passthru,popen,proc_open'
    run=subprocess.run(['php','-d','display_errors=0','-d','log_errors=0','-d','allow_url_fopen=0',
        '-d','memory_limit=256M','-d','disable_functions='+disabled,str(runner),p['runner_argument']],
        cwd=project,env=env,capture_output=True,text=True,timeout=180)
    terminal=op/p['receipt_name']
    if not safe_file(terminal,65536) or terminal.resolve()!=terminal: fail('official11_terminal_missing_no_replay')
    data=json.loads(terminal.read_text(),object_pairs_hook=official11_unique)
    result[p['result_key']]=data
    if not official11_terminal(data,p): fail('official11_terminal_contract')
    try: emitted=json.loads(run.stdout.strip(),object_pairs_hook=official11_unique)
    except Exception: fail('official11_stdout_invalid_no_replay')
    if emitted!=data or run.stderr.strip(): fail('official11_stdout_drift_no_replay')
    if data['state']=='committed_verified':
        if run.returncode!=0 or data['protected_before']!=protected: fail('official11_false_complete')
    elif run.returncode!=2: fail('official11_false_failure')
    return data
'''

REMOTE_DISPATCH = r'''    if mode==OFFICIAL11_POLICY['command']['mode']:
        data=run_official11(stage)
        result['supplier_calls']=result['mapping_writes']=result['legacy_writes']=result['schema_writes']=0
        result['profile_writes']=data['profile_writes'];result['provenance_writes']=data['provenance_writes']
        result['database_writes']=data['database_writes']
        result['production_after']=fingerprints()
        if result['production_after']!=before: fail('production_drift')
        result['production_unchanged']=True
        result['status']={'committed_verified':'complete','held_before_write':'blocked_no_replay','unknown_no_replay':'unknown_no_replay'}[data['state']]
'''


def activate(core, command):
    selected_mode = command.get('mode')
    if selected_mode not in (MODE, MODE26):
        return
    profile26 = selected_mode == MODE26
    source_sha, operation = (SOURCE_SHA26, OPERATION26) if profile26 else (SOURCE_SHA, OPERATION)
    expected = core.parse_command(core.PREFIX + ' '.join([source_sha, selected_mode, operation]))
    need(command == expected, 'official11_authorized_shape')
    control = os.environ.get('GITHUB_SHA', '')
    need(re.fullmatch('[a-f0-9]{40}', control), 'official11_control')
    (approved_manifest26 if profile26 else approved_manifest)()
    selected_policy = (policy26 if profile26 else policy)(control)
    remote = core.REMOTE
    definition = 'def run_match942(stage, mode, offset, limit):\n'
    dispatch = "    if mode=='match-tv942-write':\n"
    collector = "    if mode not in ('reconcile',"
    manifest = "    if not isinstance(files,dict) or len(files)<20: fail('manifest')"
    reservation = '    op.mkdir(mode=0o700)\n'
    final = "if 'op' in globals() and op.exists():"
    members = '        members=package.getmembers()\n'
    need(all(remote.count(v) == n for v, n in ((definition, 1), (dispatch, 1), (collector, 2),
         (manifest, 1), (reservation, 1), (final, 1), (members, 1))), 'official11_registration_drift')
    remote = remote.replace(definition, 'OFFICIAL11_POLICY=' + repr(selected_policy) + '\n' + VALIDATOR + REMOTE_HANDLER + '\n' + definition, 1)
    remote = remote.replace(dispatch, REMOTE_DISPATCH + dispatch, 1)
    remote = remote.replace(collector, "    if mode not in ('" + selected_mode + "','reconcile',")
    remote = remote.replace(manifest, "    if set(manifest)!={'schema_version','files'} or type(manifest.get('schema_version')) is not int or files!=OFFICIAL11_POLICY['hashes']: fail('official11_manifest')", 1)
    remote = remote.replace(members, members + "        if len(members)!=len(OFFICIAL11_POLICY['hashes'])+1 or {m.name for m in members}!={*OFFICIAL11_POLICY['hashes'],'manifest.json'}: fail('official11_archive_members')\n", 1)
    remote = remote.replace(reservation, reservation + '    official11_reserved=True\n', 1)
    remote = remote.replace(final, "if globals().get('official11_reserved') is True and op.exists():", 1)
    ast.parse(remote)
    core.REMOTE, core.bundle_source = remote, bundle_source26 if profile26 else bundle_source
    original = core.completed_result
    core.completed_result = lambda cmd, result: completed(cmd, result, control, selected_policy) if cmd.get('mode') == selected_mode else original(cmd, result)
