"""One fixed LOCAL retained-anchor read inside the permanent executor; no writer."""
from __future__ import annotations

import ast
import hashlib
import io
import json
import os
from pathlib import Path
import re
import tarfile

MODE = 'local-stay-anchor-read-v1'
OPERATION = 'int-andromeda-local-stay-firstclass-read-20261025-v1'
TOUR = '25282424201500'
SOURCE_SHA = '516ab32e7ea3f23e0d071343143e207e3d9bbefe'
MAX_BYTES = 2 * 1024 * 1024
SOURCE_HASHES = {'scripts/catalog/anytour_hotel_stay_offer_candidates_v2.php': '7a555c9665d3917160eab1f01ac60a42530f854294fb36b2daf08f89fce7d40b', 'scripts/catalog/anytour_hotel_stay_review_packet_v2.php': 'a5606e262aa4395ee6a0723dcdbc8eea98dcfd92c3ba5aee4bfebc71ad4d5dfa', 'scripts/catalog/anytour_hotel_stay_anchor_read_v1.php': 'f2b5584a1ec87c75cc9265b51d0bfa091b20d61e27ea489579dc4838e3b130b4', 'v2/data/anytour-hotel-stay-catalog-v2.php': '7a0b9081bc1dd3033d1110441e0bc918e1c6cc6c5657cda0f3d3c50e39882a5a', 'v2/data/anytour-provider-identity-bridge-v1.php': '641e92a97c91a40166a4271662044ac54ba68aaa6ab74628315e20eca6f076ad', 'v2/data/db-v1.php': 'ca7f11d6ec53e0cd3d4464e0f645f15b761bb734da1323689479838868d4abc4'}
BUNDLE_FILES = (
    'scripts/catalog/anytour_hotel_stay_offer_candidates_v2.php',
    'scripts/catalog/anytour_hotel_stay_review_packet_v2.php',
    'scripts/catalog/anytour_hotel_stay_anchor_read_v1.php',
    'v2/data/anytour-hotel-stay-catalog-v2.php',
    'v2/data/anytour-provider-identity-bridge-v1.php',
    'v2/data/db-v1.php',
)


def need(value, reason):
    if not value:
        raise ValueError(reason)


def register_parser(core):
    original = core.parse_command

    def parse(body):
        schema = parse_schema_command(core, body)
        if schema is not None:
            return schema
        if not body.startswith(core.PREFIX):
            return original(body)
        parts = body[len(core.PREFIX):].split()
        if len(parts) < 2 or parts[1] != MODE:
            return original(body)
        need(len(parts) == 3 and parts[0] == SOURCE_SHA
             and core.SHA_RE.fullmatch(parts[0]) is not None
             and parts[2] == OPERATION, 'local_stay_fixed_command')
        need(os.environ.get('GITHUB_RUN_ATTEMPT', '1') == '1', 'local_stay_first_attempt_only')
        return {'source_sha': SOURCE_SHA, 'mode': MODE, 'operation_id': OPERATION,
                'maximum_cohorts': 50, 'provider_http_calls': 0, 'database_writes': 0}

    core.parse_command = parse


def bundle_source(source_root):
    root = Path(source_root).resolve()
    need(set(SOURCE_HASHES) == set(BUNDLE_FILES), 'local_stay_source_not_final')
    output = io.BytesIO()
    with tarfile.open(fileobj=output, mode='w:gz', format=tarfile.PAX_FORMAT) as archive:
        for relative in BUNDLE_FILES:
            path = root / relative
            need(path.is_file() and not path.is_symlink() and path.resolve() == path,
                 'local_stay_source_path')
            raw = path.read_bytes()
            need(0 < len(raw) <= MAX_BYTES
                 and hashlib.sha256(raw).hexdigest() == SOURCE_HASHES[relative],
                 'local_stay_reviewed_source_changed')
            info = tarfile.TarInfo(relative)
            info.size, info.mode, info.mtime = len(raw), 0o600, 0
            archive.addfile(info, io.BytesIO(raw))
        raw = json.dumps({'schema_version': 1, 'files': SOURCE_HASHES},
                         sort_keys=True, separators=(',', ':')).encode()
        info = tarfile.TarInfo('manifest.json')
        info.size, info.mode, info.mtime = len(raw), 0o600, 0
        archive.addfile(info, io.BytesIO(raw))
    return output.getvalue(), dict(SOURCE_HASHES)


# The same validator runs before the remote result is emitted and at local completion.
VALIDATOR = r'''
def local_stay_canonical(value):
    return json.dumps(value,ensure_ascii=False,sort_keys=True,separators=(',',':')).replace('\u2028','\\u2028').replace('\u2029','\\u2029').encode('utf-8')
def local_stay_valid(data,source_sha,control_sha):
    if not isinstance(data,dict) or set(data)!={'receipt','inventory','reviewPacket'}: return False
    if len(local_stay_canonical(data))>2097152: return False
    r=data['receipt'];inv=data['inventory'];packet=data['reviewPacket']
    keys={'schemaVersion','source','state','tourvisorTourId','supplierCalls','databaseWrites','automaticAccepts',
          'anchorRetainedRows','anchorExpiredRows','currentValidatedOffers','returnedCohorts','reviewItems',
          'missingTables','inventorySha256','reviewPacketSha256','operationId','sourceSha','controlSha'}
    if (not isinstance(r,dict) or set(r)!=keys or type(r['schemaVersion']) is not int or r['schemaVersion']!=1
        or r['source']!='anytour-hotel-stay-anchor-read-v1' or r['tourvisorTourId']!='25282424201500'
        or r['operationId']!='int-andromeda-local-stay-firstclass-read-20261025-v1'
        or r['sourceSha']!=source_sha or r['controlSha']!=control_sha
        or not isinstance(control_sha,str) or re.fullmatch(r'[a-f0-9]{40}',control_sha) is None): return False
    for key in ('supplierCalls','databaseWrites','automaticAccepts'):
        if type(r[key]) is not int or r[key]!=0: return False
    for key,maximum in (('anchorRetainedRows',64),('anchorExpiredRows',64),('currentValidatedOffers',15000),('returnedCohorts',50),('reviewItems',100)):
        if type(r[key]) is not int or not 0<=r[key]<=maximum: return False
    if r['anchorExpiredRows']>r['anchorRetainedRows']: return False
    tables={'anytour_hotels','anytour_hotel_sources','anytour_offers','anytour_offer_scope_state',
            'anytour_hotel_room_concepts_v2','anytour_hotel_meal_concepts_v2','anytour_hotel_stay_mappings_v2'}
    missing=r['missingTables']
    if not isinstance(missing,list) or any(not isinstance(x,str) or x not in tables for x in missing) or len(missing)!=len(set(missing)): return False
    if (r['state']=='schema_missing')!=bool(missing): return False
    if r['state'] in ('completed_read_only','no_current_cohorts'):
        if r['anchorRetainedRows']<1: return False
        if (not isinstance(inv,dict) or not isinstance(packet,dict)
            or hashlib.sha256(local_stay_canonical(inv)).hexdigest()!=r['inventorySha256']
            or hashlib.sha256(local_stay_canonical(packet)).hexdigest()!=r['reviewPacketSha256']): return False
        invkeys={'status','source','generatedAt','currentIdentityValidatedOffers','totalExactCohorts','returnedCohorts',
                 'omittedMissingOperatorOffers','omittedMissingStayFactOffers','invalidPayloads','reviewNeededCohorts',
                 'heldCohorts','existingNegativeCohorts','statusCounts','cohorts','hotelConcepts','writes','supplierCalls','automaticAccepts','anchorEvidence'}
        if (set(inv)!=invkeys or inv['status']!='read_only_current_offer_review_inventory'
            or inv['source']!='anytour-current-complete-offer-snapshots'
            or inv['currentIdentityValidatedOffers']!=r['currentValidatedOffers']
            or inv['returnedCohorts']!=r['returnedCohorts']): return False
        for key in ('writes','supplierCalls','automaticAccepts'):
            if type(inv[key]) is not int or inv[key]!=0: return False
        a=inv['anchorEvidence']
        akeys={'hotelId','legacyHotelId','provider','providerHotelRefDigest','offerRefDigest','retainedRows','expiredRows','identityOnly','profileSha256','retainedFacts'}
        if (not isinstance(a,dict) or set(a)!=akeys or a['provider']!='tourvisor' or a['identityOnly'] is not True
            or any(type(a[k]) is not int or a[k]<1 for k in ('hotelId','legacyHotelId'))
            or a['offerRefDigest']!=hashlib.sha256(b'tourvisor:tour:25282424201500').hexdigest()
            or a['providerHotelRefDigest']!=hashlib.sha256(('tourvisor:hotel:'+str(a['legacyHotelId'])).encode()).hexdigest()
            or a['retainedRows']!=r['anchorRetainedRows'] or a['expiredRows']!=r['anchorExpiredRows']
            or not isinstance(a['retainedFacts'],list) or len(a['retainedFacts'])!=a['retainedRows']): return False
        for f in a['retainedFacts']:
            if not isinstance(f,dict) or set(f)!={'payloadSha256','operatorRaw','roomRaw','mealRaw','lastSeenAt','expiresAt','expired','active'}: return False
            if any(not isinstance(f[k],str) or not 0<len(f[k].encode())<=512 for k in ('operatorRaw','roomRaw','mealRaw')): return False
            if type(f['expired']) is not bool or type(f['active']) is not bool: return False
        cohorts=inv['cohorts'];hotels=inv['hotelConcepts']
        if not isinstance(cohorts,list) or len(cohorts)!=r['returnedCohorts'] or not isinstance(hotels,list) or len(hotels)!=1: return False
        for cohort in cohorts:
            if not isinstance(cohort,dict) or any(cohort.get(k)!=a[k] for k in ('hotelId','legacyHotelId','provider','providerHotelRefDigest')): return False
        for hotel in hotels:
            if not isinstance(hotel,dict) or set(hotel)!={'hotelId','rooms','meals'} or hotel['hotelId']!=a['hotelId']: return False
            for kind,field in (('room','rooms'),('meal','meals')):
                if not isinstance(hotel[field],list): return False
                for concept in hotel[field]:
                    if not isinstance(concept,dict) or set(concept)!={'kind','id','hotelId','localKey','nameRu','facts','revision'} or concept['kind']!=kind or concept['hotelId']!=a['hotelId']: return False
        pkeys={'schemaVersion','source','inventorySource','inventoryGeneratedAt','batchSize','actionableItemCount','blockedItemCount','batches','blocked','rankedChoices','automaticDecisions','packetSha256'}
        if (set(packet)!=pkeys or packet['schemaVersion']!=1 or packet['source']!='anytour-hotel-stay-review-packet-v2'
            or packet['inventorySource']!=inv['source'] or packet['inventoryGeneratedAt']!=inv['generatedAt']
            or packet['batchSize']!=100 or packet['rankedChoices'] is not False
            or type(packet['automaticDecisions']) is not int or packet['automaticDecisions']!=0
            or packet['actionableItemCount']!=r['reviewItems']): return False
        body=dict(packet);digest=body.pop('packetSha256')
        if hashlib.sha256(local_stay_canonical(body)).hexdigest()!=digest: return False
        if (not isinstance(packet['batches'],list) or not isinstance(packet['blocked'],list)
            or sum(len(b.get('items',[])) for b in packet['batches'])!=r['reviewItems']
            or len(packet['blocked'])!=packet['blockedItemCount'] or packet['blockedItemCount']+r['reviewItems']>100): return False
        for item in [x for b in packet['batches'] for x in b['items']]+packet['blocked']:
            if (item.get('anytourHotelId')!=a['hotelId'] or item.get('legacyHotelId')!=a['legacyHotelId']
                or item.get('provider')!='tourvisor' or item.get('providerHotelRefDigest')!=a['providerHotelRefDigest']
                or item.get('recommendedTarget') is not None or item.get('automaticDecision') is not False): return False
        if (r['state']=='no_current_cohorts')!=(r['returnedCohorts']==0): return False
    else:
        allowed={'anchor_missing','anchor_payload_invalid','anchor_identity_ambiguous','anchor_identity_unavailable',
                 'schema_missing','anchor_row_limit','current_payload_invalid','current_row_limit','result_size_limit'}
        if r['state'] not in allowed or inv is not None or packet is not None or r['inventorySha256'] is not None or r['reviewPacketSha256'] is not None: return False
    return True
'''


def valid_payload(data, control_sha):
    namespace = {'hashlib': hashlib, 'json': json, 're': re}
    try:
        exec(VALIDATOR, namespace)
        return namespace['local_stay_valid'](data, SOURCE_SHA, control_sha)
    except (KeyError, TypeError, ValueError, AttributeError):
        return False


REMOTE_HANDLER = r'''
def local_stay_protected():
    values={}
    for name in ('config.php','data/db-v1.php'):
        path=project/name
        if not safe_file(path,2097152) or path.resolve()!=path: fail('local_stay_private_dependency')
        values[name]=hashlib.sha256(path.read_bytes()).hexdigest()
    return values
def run_local_stay_read(stage):
    if payload!={**LOCAL_STAY_POLICY['command'],'archive':payload.get('archive'),'manifest_sha256':payload.get('manifest_sha256')}:
        fail('local_stay_payload_scope')
    if any(type(payload.get(k)) is not int for k in ('maximum_cohorts','provider_http_calls','database_writes')):
        fail('local_stay_payload_types')
    if files!=LOCAL_STAY_POLICY['hashes']: fail('local_stay_source_pins')
    protected_before=local_stay_protected()
    env={k:os.environ[k] for k in ('PATH','HOME','LANG','LC_ALL') if k in os.environ}
    env.update({'ANYTOUR_SITE_ROOT':str(project),'ANYTOUR_OPERATION_OUTPUT_DIR':str(op),
                'ANYTOUR_OPERATION_RELEASE_SHA':source,'ANYTOUR_OPERATION_CONTROL_SHA':LOCAL_STAY_POLICY['control'],
                'ANYTOUR_OPERATION_ID':operation})
    disabled='curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,socket_connect,exec,shell_exec,system,passthru,popen,proc_open'
    run=subprocess.run(['php','-d','display_errors=0','-d','log_errors=0','-d','allow_url_fopen=0',
                        '-d','memory_limit=256M','-d','disable_functions='+disabled,
                        str(stage/'scripts/catalog/anytour_hotel_stay_anchor_read_v1.php')],
                       cwd=project,env=env,capture_output=True,text=True,timeout=180)
    path=op/'local-stay-anchor-read.json'
    if not safe_file(path,2097152) or path.resolve()!=path or path.stat().st_mode&0o777!=0o600: fail('local_stay_receipt_file')
    raw=path.read_bytes()
    try: data=json.loads(raw)
    except Exception: fail('local_stay_receipt_json')
    try: valid=local_stay_valid(data,source,LOCAL_STAY_POLICY['control'])
    except Exception: valid=False
    if not valid: fail('local_stay_receipt_contract')
    if run.returncode!=0 or run.stderr.strip() or len(run.stdout.encode())>2097152: fail('local_stay_exit_no_replay')
    try: emitted=json.loads(run.stdout)
    except Exception: fail('local_stay_stdout')
    if emitted!=data: fail('local_stay_stdout')
    protected_after=local_stay_protected()
    if protected_before!=protected_after: fail('local_stay_dependency_drift')
    return {'data':data,'fileSha256':hashlib.sha256(raw).hexdigest(),
            'protectedBefore':protected_before,'protectedAfter':protected_after}
'''
REMOTE_DISPATCH = r'''    if mode=='local-stay-anchor-read-v1':
        result['local_stay_read']=run_local_stay_read(stage)
        result['production_after']=fingerprints()
        if result['production_after']!=before: fail('production_drift')
        result['supplier_calls']=result['database_writes']=result['profile_writes']=result['mapping_writes']=result['schema_writes']=0
        if len(json.dumps(result,ensure_ascii=False,separators=(',',':')).encode())>2097152: fail('local_stay_terminal_size')
        result['production_unchanged']=True;result['status']='complete'
'''


def completed(command, result, control_sha):
    try:
        if len(json.dumps(result,ensure_ascii=False,separators=(',',':')).encode()) > MAX_BYTES:
            return False
        if (command != {'source_sha': SOURCE_SHA, 'mode': MODE, 'operation_id': OPERATION,
                        'maximum_cohorts': 50, 'provider_http_calls': 0, 'database_writes': 0}
                or result.get('status') != 'complete' or type(result.get('schema_version')) is not int
                or result['schema_version'] != 1
                or any(result.get(k) != command[k] for k in ('mode', 'operation_id', 'source_sha'))):
            return False
        if any(type(command.get(k)) is not int for k in ('maximum_cohorts','provider_http_calls','database_writes')):
            return False
        for key in ('supplier_calls','database_writes','booking_calls','lead_calls','profile_writes','mapping_writes','schema_writes'):
            if type(result.get(key)) is not int or result[key] != 0:
                return False
        before = result.get('production_before')
        if (not isinstance(before, dict) or set(before) != {'index.php','v2/index.php','v2/api-v2.php','v2/lead-adapter-v2.php'}
                or before != result.get('production_after') or result.get('production_unchanged') is not True):
            return False
        # Stock fingerprints represent an absent historical v2 path as null.
        # Preserve that distinction, while requiring real hashes for present files.
        if any(value is not None and (not isinstance(value,str) or re.fullmatch(r'[a-f0-9]{64}',value) is None)
               for value in before.values()):
            return False
        if not isinstance(before['index.php'],str):
            return False
        read = result.get('local_stay_read')
        return (isinstance(read, dict) and set(read) == {'data','fileSha256','protectedBefore','protectedAfter'}
                and isinstance(read['fileSha256'], str) and re.fullmatch(r'[a-f0-9]{64}', read['fileSha256']) is not None
                and read['fileSha256'] == hashlib.sha256((json.dumps(read['data'], ensure_ascii=False, sort_keys=True, separators=(',', ':')).replace('\u2028','\\u2028').replace('\u2029','\\u2029')+'\n').encode()).hexdigest()
                and isinstance(read['protectedBefore'], dict)
                and set(read['protectedBefore']) == {'config.php','data/db-v1.php'}
                and all(isinstance(v,str) and re.fullmatch(r'[a-f0-9]{64}',v) is not None for v in read['protectedBefore'].values())
                and read['protectedBefore'] == read['protectedAfter'] and valid_payload(read['data'], control_sha))
    except (KeyError, TypeError, ValueError):
        return False


def activate(core, command):
    if command.get('mode') == SCHEMA_MODE:
        return activate_schema(core, command)
    if command.get('mode') != MODE:
        return
    expected = core.parse_command(core.PREFIX + ' '.join([str(command.get('source_sha','')), MODE, str(command.get('operation_id',''))]))
    need(command == expected and all(type(command.get(k)) is int for k in ('maximum_cohorts','provider_http_calls','database_writes')),
         'local_stay_authorized_shape')
    control = os.environ.get('GITHUB_SHA', '')
    need(re.fullmatch(r'[a-f0-9]{40}', control) is not None, 'local_stay_control_sha')
    need(set(SOURCE_HASHES) == set(BUNDLE_FILES), 'local_stay_source_not_final')
    remote = core.REMOTE
    definition = 'def run_match942(stage, mode, offset, limit):\n'
    dispatch = "    if mode=='match-tv942-write':\n"
    collector = "    if mode not in ('reconcile',"
    manifest = "    if not isinstance(files,dict) or len(files)<20: fail('manifest')"
    need(remote.count(definition) == 1 and remote.count(dispatch) == 1 and remote.count(collector) == 2
         and remote.count(manifest) == 1, 'local_stay_registration_drift')
    policy = {'command': expected, 'hashes': SOURCE_HASHES, 'control': control}
    remote = remote.replace(definition, 'LOCAL_STAY_POLICY='+repr(policy)+'\n'+VALIDATOR+REMOTE_HANDLER+'\n'+definition, 1)
    remote = remote.replace(dispatch, REMOTE_DISPATCH+dispatch, 1)
    remote = remote.replace(collector, "    if mode not in ('"+MODE+"','reconcile',")
    remote = remote.replace(manifest, "    if not isinstance(files,dict) or files!=LOCAL_STAY_POLICY['hashes']: fail('local_stay_manifest')", 1)
    members = '        members=package.getmembers()\n'
    need(remote.count(members) == 1, 'local_stay_archive_anchor')
    remote = remote.replace(members, members + "        if len(members)!=7 or {m.name for m in members}!={*LOCAL_STAY_POLICY['hashes'],'manifest.json'}: fail('local_stay_archive_scope')\n", 1)
    reservation = '    op.mkdir(mode=0o700)\n'
    final = "if 'op' in globals() and op.exists():"
    need(remote.count(reservation) == 1 and remote.count(final) == 1, 'local_stay_reservation_anchor')
    remote = remote.replace(reservation, reservation+'    local_stay_reserved=True\n', 1)
    remote = remote.replace(final, "if globals().get('local_stay_reserved') is True and op.exists():", 1)
    ast.parse(remote)
    core.REMOTE, core.bundle_source = remote, bundle_source
    original_completed = core.completed_result
    core.completed_result = lambda cmd, result: completed(cmd, result, control) if cmd.get('mode') == MODE else original_completed(cmd, result)


# A separate, fixed metadata observation. The consumed anchor profile above is
# retained byte-for-byte; this operation never reads hotel or offer records.
SCHEMA_MODE = 'local-stay-schema-read-v1'
SCHEMA_OPERATION = 'int-andromeda-local-stay-schema-read-20261025-v1'
SCHEMA_SOURCE_SHA = '30a7e7013edd7fe70a4700db0aafb2bf22ee05f8'
SCHEMA_MAX_BYTES = 262144
SCHEMA_RUNNER = 'scripts/catalog/anytour_hotel_stay_schema_read_v1.php'
SCHEMA_SQL = 'v2/data/migrations/20260918-anytour-hotel-stay-v2.sql'
SCHEMA_MIGRATION_SHA = '1e80434e552fd0c91240db903245bf3f9976d4e937221b3270c5eda92f9ccc06'
SCHEMA_RUNNER_SHA = '32ba12ffdf6a29ec15f402872f402fc8bd599e9945c3ffc51065b0a470ae54b2'
SCHEMA_SOURCE_HASHES = {
    SCHEMA_SQL: SCHEMA_MIGRATION_SHA,
    'v2/data/db-v1.php': 'ca7f11d6ec53e0cd3d4464e0f645f15b761bb734da1323689479838868d4abc4',
}


def schema_hashes():
    need(re.fullmatch(r'[a-f0-9]{64}', SCHEMA_RUNNER_SHA) is not None, 'local_stay_schema_source_not_final')
    return {**SCHEMA_SOURCE_HASHES, SCHEMA_RUNNER: SCHEMA_RUNNER_SHA}


def parse_schema_command(core, body):
    if not body.startswith(core.PREFIX):
        return None
    parts = body[len(core.PREFIX):].split()
    if len(parts) < 2 or parts[1] != SCHEMA_MODE:
        return None
    need(len(parts) == 3 and parts[0] == SCHEMA_SOURCE_SHA
         and core.SHA_RE.fullmatch(parts[0]) is not None
         and parts[2] == SCHEMA_OPERATION, 'local_stay_schema_fixed_command')
    need(os.environ.get('GITHUB_RUN_ATTEMPT', '1') == '1', 'local_stay_schema_first_attempt_only')
    return {'source_sha': SCHEMA_SOURCE_SHA, 'mode': SCHEMA_MODE, 'operation_id': SCHEMA_OPERATION,
            'maximum_schema_tables': 5, 'provider_http_calls': 0, 'database_writes': 0}


def bundle_schema_source(source_root):
    source = Path(source_root).resolve()
    control = Path(__file__).resolve().parents[2]
    hashes = schema_hashes()
    output = io.BytesIO()
    with tarfile.open(fileobj=output, mode='w:gz', format=tarfile.PAX_FORMAT) as archive:
        for relative, digest in hashes.items():
            path = (control if relative == SCHEMA_RUNNER else source) / relative
            need(path.is_file() and not path.is_symlink() and path.resolve() == path,
                 'local_stay_schema_source_path')
            raw = path.read_bytes()
            need(0 < len(raw) <= SCHEMA_MAX_BYTES and hashlib.sha256(raw).hexdigest() == digest,
                 'local_stay_schema_reviewed_source_changed')
            info = tarfile.TarInfo(relative)
            info.size, info.mode, info.mtime = len(raw), 0o600, 0
            archive.addfile(info, io.BytesIO(raw))
        raw = json.dumps({'schema_version': 1, 'files': hashes}, sort_keys=True, separators=(',', ':')).encode()
        info = tarfile.TarInfo('manifest.json')
        info.size, info.mode, info.mtime = len(raw), 0o600, 0
        archive.addfile(info, io.BytesIO(raw))
    return output.getvalue(), hashes


SCHEMA_VALIDATOR = r'''
def local_stay_schema_canonical(value):
    return json.dumps(value,ensure_ascii=False,sort_keys=True,separators=(',',':')).replace('\u2028','\\u2028').replace('\u2029','\\u2029').encode('utf-8')
def local_stay_schema_valid(data,source_sha,control_sha):
    tables=['anytour_hotels','anytour_hotel_sources','anytour_hotel_room_concepts_v2','anytour_hotel_meal_concepts_v2','anytour_hotel_stay_mappings_v2']
    migration='1e80434e552fd0c91240db903245bf3f9976d4e937221b3270c5eda92f9ccc06'
    def digest(x): return isinstance(x,str) and re.fullmatch(r'[a-f0-9]{64}',x) is not None
    def identifier(x): return isinstance(x,str) and re.fullmatch(r'[a-zA-Z0-9_]{1,64}',x) is not None
    def number(x,low,high): return type(x) is int and low<=x<=high
    def nullable(x,fn): return x is None or fn(x)
    def names(xs,limit=128): return isinstance(xs,list) and 0<len(xs)<=limit and all(identifier(x) for x in xs) and len(xs)==len(set(xs))
    if not isinstance(data,dict) or set(data)!={'receipt','inventory','plan'} or len(local_stay_schema_canonical(data))>262144: return False
    r=data['receipt'];inv=data['inventory'];plan=data['plan']
    keys={'schemaVersion','source','state','supplierCalls','databaseWrites','schemaWrites','automaticAccepts',
          'migrationSha256','inventorySha256','planSha256','operationId','sourceSha','controlSha'}
    if (not isinstance(r,dict) or set(r)!=keys or type(r['schemaVersion']) is not int or r['schemaVersion']!=1
        or r['source']!='anytour-hotel-stay-schema-read-v1' or r['operationId']!='int-andromeda-local-stay-schema-read-20261025-v1'
        or r['sourceSha']!=source_sha or r['controlSha']!=control_sha or r['migrationSha256']!=migration
        or not isinstance(control_sha,str) or re.fullmatch(r'[a-f0-9]{40}',control_sha) is None): return False
    if any(type(r[k]) is not int or r[k]!=0 for k in ('supplierCalls','databaseWrites','schemaWrites','automaticAccepts')): return False
    if r['state'] in ('metadata_limit','schema_changed'):
        return inv is None and plan is None and r['inventorySha256'] is None and r['planSha256'] is None
    if (r['state']!='completed_read_only' or not isinstance(inv,dict) or set(inv)!={'server','tables'}
        or not isinstance(plan,dict) or hashlib.sha256(local_stay_schema_canonical(inv)).hexdigest()!=r['inventorySha256']
        or hashlib.sha256(local_stay_schema_canonical(plan)).hexdigest()!=r['planSha256']): return False
    server=inv['server']
    if (not isinstance(server,dict) or set(server)!={'family','version','checkEnforcement','triggerVisibilityProven'}
        or type(server['triggerVisibilityProven']) is not bool or server['family'] not in ('mysql','mariadb','other') or server['checkEnforcement'] not in ('supported','not_reviewed')
        or not isinstance(server['version'],str) or (server['version']!='unknown' and re.fullmatch(r'[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}',server['version']) is None)): return False
    if server['checkEnforcement']=='supported':
        if server['family']!='mysql' or server['version']=='unknown': return False
        version=tuple(int(x) for x in server['version'].split('.'))
        if not (8,0,16)<=version<(9,0,0): return False
    ts=inv['tables']
    if not isinstance(ts,list) or len(ts)!=5: return False
    missing=[];existing=[];parents=[];blocked=[]
    issue_allow={'absent','wrong_table_type','wrong_engine','partitioned','parent_columns','parent_unique_key',
                 'table_collation','columns','indexes','foreign_keys','checks','check_capability_not_reviewed','unexpected_trigger','trigger_visibility_not_proven'}
    column_rows=index_rows=fk_rows=check_rows=trigger_rows=0
    for position,t in enumerate(ts):
        tkeys={'name','role','exists','tableType','engine','collation','createSha256','partitioned','triggerCount','columns','indexes','foreignKeys','checks','compatibility','issues'}
        if (not isinstance(t,dict) or set(t)!=tkeys or t['name']!=tables[position]
            or t['role']!=('parent' if position<2 else 'target') or type(t['exists']) is not bool
            or type(t['partitioned']) is not bool or not number(t['triggerCount'],0,32) or t['tableType'] not in (None,'base_table','other')
            or not nullable(t['engine'],identifier) or not nullable(t['collation'],identifier)
            or not nullable(t['createSha256'],digest)
            or any(not isinstance(t[k],list) for k in ('columns','indexes','foreignKeys','checks','issues'))
            or any(not isinstance(x,str) or x not in issue_allow for x in t['issues']) or len(t['issues'])!=len(set(t['issues']))): return False
        if not t['exists']:
            if (any(t[k] is not None for k in ('tableType','engine','collation','createSha256'))
                or t['partitioned'] or t['triggerCount']!=0 or any(t[k] for k in ('columns','indexes','foreignKeys','checks'))
                or t['compatibility']!='absent' or t['issues']!=['absent']): return False
            (parents if position<2 else missing).append(t['name']);continue
        if (t['tableType'] is None or (t['tableType']=='base_table' and not digest(t['createSha256']))
            or 'absent' in t['issues']): return False
        expected_compat=('wrong_table_type' if 'wrong_table_type' in t['issues'] else
                         ('wrong_engine' if 'wrong_engine' in t['issues'] else ('incompatible' if t['issues'] else 'compatible')))
        if t['compatibility']!=expected_compat: return False
        if t['issues']: (parents if position<2 else blocked).append(t['name'])
        elif position>=2: existing.append(t['name'])
        trigger_rows+=t['triggerCount']
        column_rows+=len(t['columns'])
        column_names=[]
        for col in t['columns']:
            ck={'name','ordinal','type','nullable','default','charset','collation','extra','generationSha256'}
            if (not isinstance(col,dict) or set(col)!=ck or not identifier(col['name'])
                or not number(col['ordinal'],1,128) or type(col['nullable']) is not bool
                or not isinstance(col['type'],str) or len(col['type'])>128
                or re.fullmatch(r"(?:[a-z0-9_(),' ]{1,128}|other:[a-f0-9]{64})",col['type']) is None
                or not nullable(col['charset'],identifier) or not nullable(col['collation'],identifier)
                or not nullable(col['generationSha256'],digest)
                or not (col['default'] is None or (isinstance(col['default'],str) and re.fullmatch(r'(?:-?[0-9]{1,20}|sha256:[a-f0-9]{64})',col['default']) is not None))
                or not (isinstance(col['extra'],str) and re.fullmatch(r'(?:|auto_increment|other:[a-f0-9]{64})',col['extra']) is not None)): return False
            column_names.append(col['name'])
        if len(column_names)!=len(set(column_names)): return False
        for idx in t['indexes']:
            if (not isinstance(idx,dict) or set(idx)!={'name','unique','type','columns'} or not identifier(idx['name'])
                or type(idx['unique']) is not bool or not identifier(idx['type']) or not isinstance(idx['columns'],list)
                or not 0<len(idx['columns'])<=128): return False
            index_rows+=len(idx['columns'])
            for col in idx['columns']:
                if (not isinstance(col,dict) or set(col)!={'name','prefix','order'} or not nullable(col['name'],identifier)
                    or not nullable(col['prefix'],lambda n:number(n,1,1048576)) or col['order'] not in ('A','D',None)): return False
        for fk in t['foreignKeys']:
            if (not isinstance(fk,dict) or set(fk)!={'name','columns','referencedTable','referencedColumns','sameSchema','updateRule','deleteRule'}
                or not identifier(fk['name']) or not identifier(fk['referencedTable']) or type(fk['sameSchema']) is not bool
                or not names(fk['columns'],64) or not names(fk['referencedColumns'],64)
                or len(fk['columns'])!=len(fk['referencedColumns'])
                or fk['updateRule'] not in ('RESTRICT','NO_ACTION','CASCADE','SET_NULL','SET_DEFAULT')
                or fk['deleteRule'] not in ('RESTRICT','NO_ACTION','CASCADE','SET_NULL','SET_DEFAULT')): return False
            fk_rows+=len(fk['columns'])
        check_rows+=len(t['checks'])
        for check in t['checks']:
            if (not isinstance(check,dict) or set(check)!={'name','enforced','expressionSha256','astSha256'}
                or not identifier(check['name']) or type(check['enforced']) is not bool
                or not digest(check['expressionSha256']) or not nullable(check['astSha256'],digest)): return False
        for field in ('indexes','foreignKeys','checks'):
            if len({x['name'] for x in t[field]})!=len(t[field]): return False
    if column_rows>128 or index_rows>128 or fk_rows>64 or check_rows>32 or trigger_rows>32: return False
    pkeys={'state','createTables','existingCompatibleTables','blockedTables','requiresExplicitReview','applyAuthorized','migrationSha256','noDdlExecuted'}
    if (set(plan)!=pkeys or plan['requiresExplicitReview'] is not True or plan['applyAuthorized'] is not False
        or plan['noDdlExecuted'] is not True or plan['migrationSha256']!=migration
        or plan['createTables']!=missing or plan['existingCompatibleTables']!=existing or plan['blockedTables']!=parents+blocked): return False
    state=('blocked_server_capability' if server['checkEnforcement']!='supported' or server['triggerVisibilityProven'] is not True else
           ('blocked_parent_prerequisites' if parents else ('blocked_existing_drift' if blocked else
            ('create_only_after_review' if missing else 'ready_no_op'))))
    return plan['state']==state
'''


def valid_schema_payload(data, control_sha):
    namespace = {'hashlib': hashlib, 'json': json, 're': re}
    try:
        exec(SCHEMA_VALIDATOR, namespace)
        return namespace['local_stay_schema_valid'](data, SCHEMA_SOURCE_SHA, control_sha)
    except (KeyError, TypeError, ValueError, AttributeError):
        return False


def completed_schema(command, result, control_sha):
    try:
        if len(json.dumps(result,ensure_ascii=False,separators=(',',':')).encode()) > SCHEMA_MAX_BYTES:
            return False
        expected = {'source_sha': SCHEMA_SOURCE_SHA, 'mode': SCHEMA_MODE, 'operation_id': SCHEMA_OPERATION,
                    'maximum_schema_tables': 5, 'provider_http_calls': 0, 'database_writes': 0}
        if (command != expected or result.get('status') != 'complete'
                or type(result.get('schema_version')) is not int or result['schema_version'] != 1
                or any(result.get(k) != command[k] for k in ('mode','operation_id','source_sha'))
                or any(type(command.get(k)) is not int for k in ('maximum_schema_tables','provider_http_calls','database_writes'))):
            return False
        if any(type(result.get(k)) is not int or result[k] != 0 for k in
               ('supplier_calls','database_writes','booking_calls','lead_calls','profile_writes','mapping_writes','schema_writes')):
            return False
        before = result.get('production_before')
        if (not isinstance(before,dict) or set(before) != {'index.php','v2/index.php','v2/api-v2.php','v2/lead-adapter-v2.php'}
                or before != result.get('production_after') or result.get('production_unchanged') is not True
                or not isinstance(before['index.php'],str)
                or any(v is not None and (not isinstance(v,str) or re.fullmatch(r'[a-f0-9]{64}',v) is None) for v in before.values())):
            return False
        read = result.get('local_stay_schema_read')
        if not isinstance(read,dict) or set(read) != {'data','fileSha256','protectedBefore','protectedAfter'}:
            return False
        protected = read['protectedBefore']
        raw = (json.dumps(read['data'],ensure_ascii=False,sort_keys=True,separators=(',',':')).replace('\u2028','\\u2028').replace('\u2029','\\u2029')+'\n').encode()
        return (isinstance(read['fileSha256'],str) and read['fileSha256'] == hashlib.sha256(raw).hexdigest()
                and isinstance(protected,dict) and set(protected) == {'config.php','data/db-v1.php'}
                and all(isinstance(v,str) and re.fullmatch(r'[a-f0-9]{64}',v) is not None for v in protected.values())
                and protected == read['protectedAfter'] and valid_schema_payload(read['data'],control_sha))
    except (KeyError,TypeError,ValueError):
        return False


def activate_schema(core, command):
    expected = core.parse_command(core.PREFIX + ' '.join([str(command.get('source_sha','')), SCHEMA_MODE, str(command.get('operation_id',''))]))
    need(command == expected and all(type(command.get(k)) is int for k in
         ('maximum_schema_tables','provider_http_calls','database_writes')), 'local_stay_schema_authorized_shape')
    control = os.environ.get('GITHUB_SHA','')
    need(re.fullmatch(r'[a-f0-9]{40}',control) is not None, 'local_stay_schema_control_sha')
    hashes = schema_hashes()
    remote = core.REMOTE
    definition = 'def run_match942(stage, mode, offset, limit):\n'
    dispatch = "    if mode=='match-tv942-write':\n"
    collector = "    if mode not in ('reconcile',"
    manifest = "    if not isinstance(files,dict) or len(files)<20: fail('manifest')"
    need(remote.count(definition) == 1 and remote.count(dispatch) == 1 and remote.count(collector) == 2
         and remote.count(manifest) == 1, 'local_stay_schema_registration_drift')
    policy = {'command':expected,'hashes':hashes,'control':control}
    # The same hardened read-only process boundary, configured only for this
    # metadata runner. It does not alter the historical anchor program.
    handler = (REMOTE_HANDLER.replace('local_stay_', 'local_stay_schema_')
               .replace('LOCAL_STAY_POLICY','LOCAL_STAY_SCHEMA_POLICY')
               .replace('maximum_cohorts','maximum_schema_tables')
               .replace('scripts/catalog/anytour_hotel_stay_anchor_read_v1.php',SCHEMA_RUNNER)
               .replace('local-stay-anchor-read.json','local-stay-schema-read.json')
               .replace('2097152','262144'))
    dispatch_code = (REMOTE_DISPATCH.replace(MODE,SCHEMA_MODE)
                     .replace('local_stay_read','local_stay_schema_read').replace('2097152','262144'))
    remote = remote.replace(definition,'LOCAL_STAY_SCHEMA_POLICY='+repr(policy)+'\n'+SCHEMA_VALIDATOR+handler+'\n'+definition,1)
    remote = remote.replace(dispatch,dispatch_code+dispatch,1)
    remote = remote.replace(collector,"    if mode not in ('"+SCHEMA_MODE+"','reconcile',")
    remote = remote.replace(manifest,"    if not isinstance(files,dict) or files!=LOCAL_STAY_SCHEMA_POLICY['hashes']: fail('local_stay_schema_manifest')",1)
    members = '        members=package.getmembers()\n'
    need(remote.count(members) == 1, 'local_stay_schema_archive_anchor')
    remote = remote.replace(members,members+"        if len(members)!=4 or {m.name for m in members}!={*LOCAL_STAY_SCHEMA_POLICY['hashes'],'manifest.json'}: fail('local_stay_schema_archive_scope')\n",1)
    reservation = '    op.mkdir(mode=0o700)\n'
    final = "if 'op' in globals() and op.exists():"
    need(remote.count(reservation) == 1 and remote.count(final) == 1, 'local_stay_schema_reservation_anchor')
    remote = remote.replace(reservation,reservation+'    local_stay_schema_reserved=True\n',1)
    remote = remote.replace(final,"if globals().get('local_stay_schema_reserved') is True and op.exists():",1)
    ast.parse(remote)
    core.REMOTE,core.bundle_source = remote,bundle_schema_source
    original_completed = core.completed_result
    core.completed_result = lambda cmd,result: completed_schema(cmd,result,control) if cmd.get('mode') == SCHEMA_MODE else original_completed(cmd,result)
