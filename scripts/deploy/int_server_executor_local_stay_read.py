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
        context_install = parse_context_install_command(core, body)
        if context_install is not None:
            return context_install
        context = parse_context_command(core, body)
        if context is not None:
            return context
        install = parse_install_command(core, body)
        if install is not None:
            return install
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
    if command.get('mode') == CONTEXT_INSTALL_MODE:
        return activate_context_install(core, command)
    if command.get('mode') == CONTEXT_MODE:
        return activate_context(core, command)
    if command.get('mode') == INSTALL_MODE:
        return activate_install(core, command)
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
SCHEMA_RUNNER_SHA = 'd0dc691633975ae7e0c48af13aa9ffaf5c5a5014c766c50826c4dff393808a83'
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


# Explicit owner-approved creation of exactly three empty tables. Historical read
# profiles above remain immutable and never become installation aliases.
INSTALL_MODE = 'local-stay-schema-install-v1'
INSTALL_OPERATION = 'int-andromeda-local-stay-schema-install-20261026-v1'
INSTALL_SOURCE_SHA = '30a7e7013edd7fe70a4700db0aafb2bf22ee05f8'
INSTALL_RUNNER = 'scripts/catalog/anytour_hotel_stay_schema_install_v1.php'
INSTALL_RUNNER_SHA = '73686ff430dc6dc6f95ab71b5739cb2056d189d1096dc0ea866132bdc081522c'
INSTALL_MAX_BYTES = 262144
INSTALL_TABLES = ('anytour_hotel_room_concepts_v2','anytour_hotel_meal_concepts_v2','anytour_hotel_stay_mappings_v2')
INSTALL_PARENT = {
    'operation': 'int-andromeda-local-stay-schema-read-20261025-v1',
    'source': '30a7e7013edd7fe70a4700db0aafb2bf22ee05f8',
    'control': 'a4faad3ba37937a44249df68e9a1530b3f5f258f',
    # Server private result serialization is compact with no newline. The
    # downloaded Actions artifact is a separate indent=2 representation.
    'private_sha256': '96e41780998f973f109674d40e6aec9f6d175a769d1a1ff21e8d6b536a706f86',
    'file_sha256': '47f6da4d660792e8a20c5882ed99b0ef34cbefd414331c9253fb84d57c66dcdb',
    'inventory_sha256': '8a37c3c6a3cd4894afff942268d6c669f1ae81f7403253bc58f0b8ae5f38c32e',
    'plan_sha256': '6f1c556fe1a6bf3182f3a49686850f6fcb479cf94e03210881a9faf216b603f6',
    'protected': {
        'config.php': '121839ce1956ec7a48f0a94758e2c2359293ee01d601c69f720a294c4bda7def',
        'data/db-v1.php': 'ca7f11d6ec53e0cd3d4464e0f645f15b761bb734da1323689479838868d4abc4',
    },
}


def parse_install_command(core, body):
    if not body.startswith(core.PREFIX):
        return None
    parts = body[len(core.PREFIX):].split()
    if len(parts) < 2 or parts[1] != INSTALL_MODE:
        return None
    need(len(parts) == 3 and parts[0] == INSTALL_SOURCE_SHA
         and core.SHA_RE.fullmatch(parts[0]) is not None
         and parts[2] == INSTALL_OPERATION, 'local_stay_install_fixed_command')
    need(os.environ.get('GITHUB_RUN_ATTEMPT','1') == '1', 'local_stay_install_first_attempt_only')
    return {'source_sha':INSTALL_SOURCE_SHA,'mode':INSTALL_MODE,'operation_id':INSTALL_OPERATION,
            'maximum_schema_tables':3,'provider_http_calls':0,'database_writes':0}


def install_hashes():
    need(re.fullmatch(r'[a-f0-9]{64}',INSTALL_RUNNER_SHA) is not None, 'local_stay_install_source_not_final')
    return {**SCHEMA_SOURCE_HASHES,SCHEMA_RUNNER:SCHEMA_RUNNER_SHA,INSTALL_RUNNER:INSTALL_RUNNER_SHA}


def bundle_install_source(source_root):
    source = Path(source_root).resolve()
    control = Path(__file__).resolve().parents[2]
    hashes = install_hashes()
    output = io.BytesIO()
    with tarfile.open(fileobj=output,mode='w:gz',format=tarfile.PAX_FORMAT) as archive:
        for relative,digest in hashes.items():
            path = (control if relative in (SCHEMA_RUNNER,INSTALL_RUNNER) else source) / relative
            need(path.is_file() and not path.is_symlink() and path.resolve() == path,'local_stay_install_source_path')
            raw = path.read_bytes()
            need(0 < len(raw) <= INSTALL_MAX_BYTES and hashlib.sha256(raw).hexdigest() == digest,
                 'local_stay_install_reviewed_source_changed')
            info = tarfile.TarInfo(relative)
            info.size,info.mode,info.mtime = len(raw),0o600,0
            archive.addfile(info,io.BytesIO(raw))
        raw = json.dumps({'schema_version':1,'files':hashes},sort_keys=True,separators=(',',':')).encode()
        info = tarfile.TarInfo('manifest.json')
        info.size,info.mode,info.mtime = len(raw),0o600,0
        archive.addfile(info,io.BytesIO(raw))
    return output.getvalue(),hashes


INSTALL_VALIDATOR = r'''
def local_stay_install_inventory_valid(inv,source_sha,control_sha):
    if not isinstance(inv,dict) or set(inv)!={'server','tables'} or not isinstance(inv['tables'],list): return False
    tables=inv['tables'];missing=[];existing=[];parents=[];blocked=[]
    for t in tables:
        if not isinstance(t,dict): return False
        if t.get('role')=='parent' and (t.get('exists') is not True or t.get('compatibility')!='compatible'): parents.append(t.get('name'))
        if t.get('role')=='target':
            if t.get('exists') is False: missing.append(t.get('name'))
            elif t.get('compatibility')=='compatible': existing.append(t.get('name'))
            else: blocked.append(t.get('name'))
    server=inv.get('server',{})
    state=('blocked_server_capability' if server.get('checkEnforcement')!='supported' or server.get('triggerVisibilityProven') is not True else
           ('blocked_parent_prerequisites' if parents else ('blocked_existing_drift' if blocked else ('create_only_after_review' if missing else 'ready_no_op'))))
    migration='1e80434e552fd0c91240db903245bf3f9976d4e937221b3270c5eda92f9ccc06'
    plan={'state':state,'createTables':missing,'existingCompatibleTables':existing,'blockedTables':parents+blocked,
          'requiresExplicitReview':True,'applyAuthorized':False,'migrationSha256':migration,'noDdlExecuted':True}
    read={'receipt':{'schemaVersion':1,'source':'anytour-hotel-stay-schema-read-v1','state':'completed_read_only',
          'supplierCalls':0,'databaseWrites':0,'schemaWrites':0,'automaticAccepts':0,'migrationSha256':migration,
          'inventorySha256':hashlib.sha256(local_stay_schema_canonical(inv)).hexdigest(),
          'planSha256':hashlib.sha256(local_stay_schema_canonical(plan)).hexdigest(),
          'operationId':'int-andromeda-local-stay-schema-read-20261025-v1','sourceSha':source_sha,'controlSha':control_sha},
          'inventory':inv,'plan':plan}
    return local_stay_schema_valid(read,source_sha,control_sha)
def local_stay_install_valid(data,source_sha,control_sha,parent):
    if not isinstance(data,dict) or set(data)!={'receipt','before','after'} or len(local_stay_schema_canonical(data))>262144: return False
    r=data['receipt'];before=data['before'];after=data['after']
    keys={'schemaVersion','source','state','reason','migrationSha256','parentReadOperation','parentReadInventorySha256',
          'databaseSha256','beforeSha256','afterSha256','attemptedTables','createdTables','unknownTables','tableRows',
          'supplierCalls','databaseWrites','automaticAccepts','schemaWrites','ddlOutcomeUnknown','parentMetadataUnchanged',
          'journalSha256','operationId','sourceSha','controlSha'}
    def digest(x): return isinstance(x,str) and re.fullmatch(r'[a-f0-9]{64}',x) is not None
    targets=['anytour_hotel_room_concepts_v2','anytour_hotel_meal_concepts_v2','anytour_hotel_stay_mappings_v2']
    if (not isinstance(r,dict) or set(r)!=keys or type(r['schemaVersion']) is not int or r['schemaVersion']!=1
        or r['source']!='anytour-hotel-stay-schema-install-v1' or r['operationId']!='int-andromeda-local-stay-schema-install-20261026-v1'
        or r['sourceSha']!=source_sha or r['controlSha']!=control_sha
        or not isinstance(control_sha,str) or re.fullmatch(r'[a-f0-9]{40}',control_sha) is None
        or r['migrationSha256']!='1e80434e552fd0c91240db903245bf3f9976d4e937221b3270c5eda92f9ccc06'
        or r['parentReadOperation']!=parent['operation'] or r['parentReadInventorySha256']!=parent['inventory_sha256']): return False
    if any(type(r[k]) is not int or r[k]!=0 for k in ('supplierCalls','databaseWrites','automaticAccepts')): return False
    if type(r['schemaWrites']) is not int or not 0<=r['schemaWrites']<=3 or type(r['ddlOutcomeUnknown']) is not bool: return False
    if r['parentMetadataUnchanged'] not in (True,False,None) or (r['parentMetadataUnchanged'] is not None and type(r['parentMetadataUnchanged']) is not bool): return False
    if r['state'] not in ('installed_empty','preflight_refused','ddl_unknown','verification_failed'): return False
    if r['reason'] is not None and r['reason'] not in ('parent_contract','caller_transaction','source_pin','database_identity','lock_unavailable','schema_preflight','privileges','constraint_collision','session_guards','journal','ddl_execution_failed','postflight','parent_metadata','rows_nonempty','read_failed','ddl_capability'): return False
    for field in ('databaseSha256','beforeSha256','afterSha256','journalSha256'):
        if r[field] is not None and not digest(r[field]): return False
    for field in ('attemptedTables','createdTables'):
        value=r[field]
        if not isinstance(value,list) or value!=targets[:len(value)] or len(value)>3: return False
    attempted=r['attemptedTables'];created=r['createdTables'];unknown=r['unknownTables']
    if (len(created)>len(attempted) or r['schemaWrites']!=len(created) or not isinstance(unknown,list)
        or unknown!=([attempted[-1]] if r['ddlOutcomeUnknown'] and attempted else [])): return False
    if r['ddlOutcomeUnknown'] and (not attempted or len(attempted)!=len(created)+1): return False
    for field,inv in (('before',before),('after',after)):
        if inv is None:
            if r[field+'Sha256'] is not None: return False
        elif (not local_stay_install_inventory_valid(inv,source_sha,control_sha)
              or r[field+'Sha256']!=hashlib.sha256(local_stay_schema_canonical(inv)).hexdigest()): return False
    if not isinstance(r['tableRows'],list) or len(r['tableRows'])>3: return False
    for pos,row in enumerate(r['tableRows']):
        if (not isinstance(row,dict) or set(row)!={'name','rows'} or row['name']!=targets[pos]
            or type(row['rows']) is not int or not 0<=row['rows']<=9223372036854775807): return False
    if r['state']=='preflight_refused':
        return attempted==created==unknown==[] and r['schemaWrites']==0 and r['ddlOutcomeUnknown'] is False and r['reason'] is not None
    if not digest(r['databaseSha256']) or before is None or not digest(r['journalSha256']): return False
    if r['beforeSha256']!=parent['inventory_sha256']: return False
    if r['state']=='ddl_unknown': return r['ddlOutcomeUnknown'] is True and r['reason'] is not None
    if r['ddlOutcomeUnknown'] or unknown: return False
    if r['state']=='verification_failed': return r['reason'] is not None and 0<len(created)<=3 and attempted==created
    if (r['reason'] is not None or attempted!=targets or created!=targets or r['schemaWrites']!=3
        or after is None or r['tableRows']!=[{'name':name,'rows':0} for name in targets]
        or r['parentMetadataUnchanged'] is not True): return False
    if any(t['compatibility']!='compatible' for t in after['tables']): return False
    return all(before['tables'][i]==after['tables'][i] for i in (0,1))
def local_stay_install_journal_valid(events,r):
    if r['journalSha256'] is None: return events==[]
    if not isinstance(events,list) or not 1<=len(events)<=8: return False
    targets=['anytour_hotel_room_concepts_v2','anytour_hotel_meal_concepts_v2','anytour_hotel_stay_mappings_v2']
    statements=['ae700b15382e9797ff20c351abeafd052783a549749431ad3f337c9881f098a8',
                '3dd522bc038f62e3161b15472d2081192bfae635ae9afcb154ece577f62e1762',
                '0789fec589f0ef74fa02a2502ab65d59bcd49576a1961371c09da6ee690ec5d5']
    if events[0]!={'stage':'start','operationId':r['operationId'],'migrationSha256':r['migrationSha256'],
                   'databaseSha256':r['databaseSha256'],'beforeSha256':r['beforeSha256'],
                   'parentReadInventorySha256':r['parentReadInventorySha256']}: return False
    index=0;pending=None;final=False
    for pos,event in enumerate(events[1:],1):
        if not isinstance(event,dict): return False
        if event.get('stage')=='before_create':
            if pending is not None or index>=3 or final or event!={'stage':'before_create','table':targets[index],'statementSha256':statements[index]}: return False
            pending=targets[index]
        elif event.get('stage')=='created':
            if pending is None or final or event!={'stage':'created','table':pending,'acknowledgedSchemaWrites':index+1}: return False
            pending=None;index+=1
        elif event.get('stage')=='final':
            if (final or pos!=len(events)-1 or set(event)!={'stage','state','reason','attemptedTables','createdTables','unknownTables'}
                or any(event[k]!=r[k] for k in ('attemptedTables','createdTables','unknownTables'))): return False
            if (event['state']!=r['state'] or event['reason']!=r['reason']) and r['reason']!='journal': return False
            final=True
        else: return False
    if index>len(r['createdTables']) or (index!=len(r['createdTables']) and r['reason']!='journal'): return False
    if r['ddlOutcomeUnknown'] and pending!=r['unknownTables'][0]: return False
    if pending is not None and not r['ddlOutcomeUnknown'] and r['reason']!='journal': return False
    if not final and r['reason']!='journal': return False
    return r['state']!='installed_empty' or (index==3 and pending is None and final and len(events)==8)
'''


def valid_install_payload(data, control_sha):
    namespace = {'hashlib':hashlib,'json':json,'re':re}
    try:
        exec(SCHEMA_VALIDATOR+INSTALL_VALIDATOR,namespace)
        return namespace['local_stay_install_valid'](data,INSTALL_SOURCE_SHA,control_sha,INSTALL_PARENT)
    except (KeyError,TypeError,ValueError,AttributeError,IndexError):
        return False


def completed_install(command,result,control_sha):
    try:
        expected={'source_sha':INSTALL_SOURCE_SHA,'mode':INSTALL_MODE,'operation_id':INSTALL_OPERATION,
                  'maximum_schema_tables':3,'provider_http_calls':0,'database_writes':0}
        if (command!=expected or any(type(command.get(k)) is not int for k in ('maximum_schema_tables','provider_http_calls','database_writes'))
            or result.get('status')!='complete' or type(result.get('schema_version')) is not int or result['schema_version']!=1
            or any(result.get(k)!=command[k] for k in ('mode','operation_id','source_sha'))
            or len(json.dumps(result,ensure_ascii=False,separators=(',',':')).encode())>INSTALL_MAX_BYTES): return False
        if any(type(result.get(k)) is not int or result[k]!=0 for k in ('supplier_calls','database_writes','booking_calls','lead_calls','profile_writes','mapping_writes')): return False
        if (type(result.get('schema_writes')) is not int or result['schema_writes']!=3
            or type(result.get('schema_writes_acknowledged')) is not int or result['schema_writes_acknowledged']!=3
            or result.get('schema_write_outcome_unknown') is not False): return False
        before=result.get('production_before')
        if (not isinstance(before,dict) or set(before)!={'index.php','v2/index.php','v2/api-v2.php','v2/lead-adapter-v2.php'}
            or before!=result.get('production_after') or result.get('production_unchanged') is not True
            or not isinstance(before['index.php'],str)
            or any(v is not None and (not isinstance(v,str) or re.fullmatch(r'[a-f0-9]{64}',v) is None) for v in before.values())): return False
        install=result.get('local_stay_schema_install')
        if (not isinstance(install,dict) or set(install)!={'data','fileSha256','protectedBefore','protectedAfter','parentResultSha256','journalSha256','journalEvents'}
            or install['parentResultSha256']!=INSTALL_PARENT['private_sha256']
            or install['protectedBefore']!=INSTALL_PARENT['protected'] or install['protectedAfter']!=INSTALL_PARENT['protected']
            or not valid_install_payload(install['data'],control_sha)): return False
        data=install['data']
        raw=(json.dumps(data,ensure_ascii=False,sort_keys=True,separators=(',',':')).replace('\u2028','\\u2028').replace('\u2029','\\u2029')+'\n').encode()
        namespace={'hashlib':hashlib,'json':json,'re':re}
        exec(SCHEMA_VALIDATOR+INSTALL_VALIDATOR,namespace)
        return (data['receipt']['state']=='installed_empty' and install['fileSha256']==hashlib.sha256(raw).hexdigest()
                and install['journalSha256']==data['receipt']['journalSha256']
                and namespace['local_stay_install_journal_valid'](install['journalEvents'],data['receipt']))
    except (KeyError,TypeError,ValueError,AttributeError):
        return False


INSTALL_REMOTE_HANDLER = r'''
def local_stay_install_unique(pairs):
    result={}
    for key,value in pairs:
        if key in result: raise ValueError('duplicate_json_key')
        result[key]=value
    return result
def local_stay_install_protected():
    values={}
    for name in ('config.php','data/db-v1.php'):
        path=project/name
        if not safe_file(path,2097152) or path.resolve()!=path: fail('local_stay_install_private_dependency')
        values[name]=hashlib.sha256(path.read_bytes()).hexdigest()
    return values
def local_stay_install_parent():
    policy=LOCAL_STAY_INSTALL_POLICY['parent'];path=private/policy['operation']/'result.json'
    if not safe_file(path,262144) or path.resolve()!=path or path.stat().st_mode&0o777!=0o600: fail('local_stay_install_parent_file')
    raw=path.read_bytes()
    if hashlib.sha256(raw).hexdigest()!=policy['private_sha256']: fail('local_stay_install_parent_hash')
    try: parent=json.loads(raw,object_pairs_hook=local_stay_install_unique)
    except Exception: fail('local_stay_install_parent_json')
    command={'source_sha':policy['source'],'mode':'local-stay-schema-read-v1','operation_id':policy['operation'],
             'maximum_schema_tables':5,'provider_http_calls':0,'database_writes':0}
    try: valid=local_stay_parent_completed(command,parent,policy['control'])
    except Exception: valid=False
    if not valid: fail('local_stay_install_parent_contract')
    read=parent['local_stay_schema_read'];r=read['data']['receipt'];plan=read['data']['plan']
    if (read['fileSha256']!=policy['file_sha256'] or r['inventorySha256']!=policy['inventory_sha256']
        or r['planSha256']!=policy['plan_sha256'] or plan['state']!='create_only_after_review'
        or plan['createTables']!=LOCAL_STAY_INSTALL_POLICY['targets'] or plan['blockedTables']
        or read['protectedBefore']!=policy['protected']): fail('local_stay_install_parent_pins')
    return path,policy['private_sha256']
def run_local_stay_schema_install(stage):
    if payload!={**LOCAL_STAY_INSTALL_POLICY['command'],'archive':payload.get('archive'),'manifest_sha256':payload.get('manifest_sha256')}:
        fail('local_stay_install_payload_scope')
    if any(type(payload.get(k)) is not int for k in ('maximum_schema_tables','provider_http_calls','database_writes')):
        fail('local_stay_install_payload_types')
    if files!=LOCAL_STAY_INSTALL_POLICY['hashes']: fail('local_stay_install_source_pins')
    result['supplier_calls']=result['database_writes']=result['profile_writes']=result['mapping_writes']=0
    result['schema_writes']=result['schema_writes_acknowledged']=0;result['schema_write_outcome_unknown']=False
    protected_before=local_stay_install_protected()
    if protected_before!=LOCAL_STAY_INSTALL_POLICY['parent']['protected']: fail('local_stay_install_private_drift')
    parent_path,parent_sha=local_stay_install_parent()
    result['supplier_calls']=result['database_writes']=result['profile_writes']=result['mapping_writes']=0
    result['schema_writes']='unknown';result['schema_writes_acknowledged']=0;result['schema_write_outcome_unknown']=True
    env={k:os.environ[k] for k in ('PATH','HOME','LANG','LC_ALL') if k in os.environ}
    env.update({'ANYTOUR_SITE_ROOT':str(project),'ANYTOUR_OPERATION_OUTPUT_DIR':str(op),
                'ANYTOUR_OPERATION_RELEASE_SHA':source,'ANYTOUR_OPERATION_CONTROL_SHA':LOCAL_STAY_INSTALL_POLICY['control'],
                'ANYTOUR_OPERATION_ID':operation,'ANYTOUR_SCHEMA_PARENT_RECEIPT_PATH':str(parent_path)})
    disabled='curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,socket_connect,exec,shell_exec,system,passthru,popen,proc_open'
    run=subprocess.run(['php','-d','display_errors=0','-d','log_errors=0','-d','allow_url_fopen=0',
                        '-d','memory_limit=256M','-d','disable_functions='+disabled,
                        str(stage/'scripts/catalog/anytour_hotel_stay_schema_install_v1.php')],
                       cwd=project,env=env,capture_output=True,text=True,timeout=180)
    path=op/'local-stay-schema-install.json'
    if not safe_file(path,262144) or path.resolve()!=path or path.stat().st_mode&0o777!=0o600: fail('local_stay_install_receipt_file')
    raw=path.read_bytes()
    try: data=json.loads(raw,object_pairs_hook=local_stay_install_unique)
    except Exception: fail('local_stay_install_receipt_json')
    try: valid=local_stay_install_valid(data,source,LOCAL_STAY_INSTALL_POLICY['control'],LOCAL_STAY_INSTALL_POLICY['parent'])
    except Exception: valid=False
    if not valid: fail('local_stay_install_receipt_contract')
    receipt=data['receipt'];journal=op/'local-stay-schema-install-journal.jsonl';journal_sha=None;events=[]
    item={'data':data,'fileSha256':hashlib.sha256(raw).hexdigest(),
          'protectedBefore':protected_before,'protectedAfter':None,
          'parentResultSha256':parent_sha,'journalSha256':None,'journalEvents':events}
    result['local_stay_schema_install']=item
    result['schema_writes_acknowledged']=receipt['schemaWrites']
    result['schema_write_outcome_unknown']=receipt['ddlOutcomeUnknown']
    result['schema_writes']='unknown' if receipt['ddlOutcomeUnknown'] else receipt['schemaWrites']
    if receipt['journalSha256'] is not None:
        if not safe_file(journal,262144) or journal.resolve()!=journal or journal.stat().st_mode&0o777!=0o600:
            fail('local_stay_install_journal_file')
        journal_raw=journal.read_bytes();journal_sha=hashlib.sha256(journal_raw).hexdigest()
        if journal_sha!=receipt['journalSha256'] or not journal_raw.endswith(b'\n'): fail('local_stay_install_journal_hash')
        try: events=[json.loads(line,object_pairs_hook=local_stay_install_unique) for line in journal_raw.splitlines()]
        except Exception: fail('local_stay_install_journal_json')
        if len(events)>8 or any(not isinstance(e,dict) for e in events): fail('local_stay_install_journal_scope')
    elif journal.exists() or journal.is_symlink(): fail('local_stay_install_unbound_journal')
    item['journalSha256']=journal_sha;item['journalEvents']=events
    if not local_stay_install_journal_valid(events,receipt): fail('local_stay_install_journal_contract')
    protected_after=local_stay_install_protected();item['protectedAfter']=protected_after
    if protected_before!=protected_after: fail('local_stay_install_dependency_drift')
    if (run.returncode!=(0 if receipt['state']=='installed_empty' else 1)
        or run.stderr.strip() or len(run.stdout.encode())>262144): fail('local_stay_install_exit_no_replay')
    try: emitted=json.loads(run.stdout,object_pairs_hook=local_stay_install_unique)
    except Exception: fail('local_stay_install_stdout')
    if emitted!=data: fail('local_stay_install_stdout')
    return item
'''
INSTALL_REMOTE_DISPATCH = r'''    if mode=='local-stay-schema-install-v1':
        result['local_stay_schema_install']=run_local_stay_schema_install(stage)
        result['production_after']=fingerprints()
        if result['production_after']!=before: fail('production_drift')
        result['production_unchanged']=True
        result['status']='complete' if result['local_stay_schema_install']['data']['receipt']['state']=='installed_empty' else 'terminal_nonzero_no_replay'
        if len(json.dumps(result,ensure_ascii=False,separators=(',',':')).encode())>262144: fail('local_stay_install_terminal_size')
'''


def parent_completion_source():
    """Reuse the exact previously admitted completion predicate, not a new reader."""
    source = Path(__file__).read_text()
    node = next(n for n in ast.parse(source).body if isinstance(n,ast.FunctionDef) and n.name=='completed_schema')
    original = ast.get_source_segment(source,node)
    need(isinstance(original,str),'local_stay_install_parent_completion_source')
    return original.replace('def completed_schema(','def local_stay_parent_completed(',1).replace('valid_schema_payload(','local_stay_parent_valid_payload(')


def activate_install(core,command):
    expected=core.parse_command(core.PREFIX+' '.join([str(command.get('source_sha','')),INSTALL_MODE,str(command.get('operation_id',''))]))
    need(command==expected and all(type(command.get(k)) is int for k in ('maximum_schema_tables','provider_http_calls','database_writes')),
         'local_stay_install_authorized_shape')
    control=os.environ.get('GITHUB_SHA','')
    need(re.fullmatch(r'[a-f0-9]{40}',control) is not None,'local_stay_install_control_sha')
    hashes=install_hashes();remote=core.REMOTE
    definition='def run_match942(stage, mode, offset, limit):\n';dispatch="    if mode=='match-tv942-write':\n"
    collector="    if mode not in ('reconcile',";manifest="    if not isinstance(files,dict) or len(files)<20: fail('manifest')"
    need(remote.count(definition)==1 and remote.count(dispatch)==1 and remote.count(collector)==2
         and remote.count(manifest)==1,'local_stay_install_registration_drift')
    policy={'command':expected,'hashes':hashes,'control':control,'parent':INSTALL_PARENT,'targets':list(INSTALL_TABLES)}
    parent_constants=('SCHEMA_MAX_BYTES=262144\nSCHEMA_MODE='+repr(SCHEMA_MODE)+'\nSCHEMA_OPERATION='+repr(INSTALL_PARENT['operation'])
                      +'\nSCHEMA_SOURCE_SHA='+repr(INSTALL_PARENT['source'])+'\n')
    parent_validator=('def local_stay_parent_valid_payload(data,control_sha):\n'
                      '    return local_stay_schema_valid(data,SCHEMA_SOURCE_SHA,control_sha)\n')
    program=('LOCAL_STAY_INSTALL_POLICY='+repr(policy)+'\n'+parent_constants+SCHEMA_VALIDATOR+INSTALL_VALIDATOR
             +'\n'+parent_validator+'\n'+parent_completion_source()+'\n'+INSTALL_REMOTE_HANDLER+'\n')
    remote=remote.replace(definition,program+definition,1)
    remote=remote.replace(dispatch,INSTALL_REMOTE_DISPATCH+dispatch,1)
    remote=remote.replace(collector,"    if mode not in ('"+INSTALL_MODE+"','reconcile',")
    remote=remote.replace(manifest,"    if not isinstance(files,dict) or files!=LOCAL_STAY_INSTALL_POLICY['hashes']: fail('local_stay_install_manifest')",1)
    members='        members=package.getmembers()\n'
    need(remote.count(members)==1,'local_stay_install_archive_anchor')
    remote=remote.replace(members,members+"        if len(members)!=5 or {m.name for m in members}!={*LOCAL_STAY_INSTALL_POLICY['hashes'],'manifest.json'}: fail('local_stay_install_archive_scope')\n",1)
    reservation='    op.mkdir(mode=0o700)\n';final="if 'op' in globals() and op.exists():"
    need(remote.count(reservation)==1 and remote.count(final)==1,'local_stay_install_reservation_anchor')
    remote=remote.replace(reservation,reservation+'    local_stay_install_reserved=True\n',1)
    remote=remote.replace(final,"if globals().get('local_stay_install_reserved') is True and op.exists():",1)
    ast.parse(remote)
    core.REMOTE,core.bundle_source=remote,bundle_install_source
    original_completed=core.completed_result
    core.completed_result=lambda cmd,result: completed_install(cmd,result,control) if cmd.get('mode')==INSTALL_MODE else original_completed(cmd,result)


# A distinct read-only connection-context producer after the sealed install's
# before-PHP dependency guard. It does not repair, re-pin or retry that profile.
CONTEXT_MODE = 'local-stay-install-context-read-v1'
CONTEXT_OPERATION = 'int-andromeda-local-stay-install-context-read-20261026-v1'
CONTEXT_SOURCE_SHA = '30a7e7013edd7fe70a4700db0aafb2bf22ee05f8'
CONTEXT_RUNNER = 'scripts/catalog/anytour_hotel_stay_install_context_read_v1.php'
CONTEXT_RUNNER_SHA = 'b95a944072282fa0b0b0a7d7f4c3f8db7a2c4f5a75d16134605923c46b0ecbf5'
CONTEXT_MAX_BYTES = 262144
CONTEXT_FAILURE = {
    'operation': 'int-andromeda-local-stay-schema-install-20261026-v1',
    'source': '30a7e7013edd7fe70a4700db0aafb2bf22ee05f8',
    'control': 'd02f258b4cfe854f4c69715758d0966d38d46197',
    'private_sha256': 'fbf6bd8808816daa6b40c7ac1647c0ccf6ecb0f9157d8feaae36ce31647b8921',
}


def parse_context_command(core,body):
    if not body.startswith(core.PREFIX): return None
    parts=body[len(core.PREFIX):].split()
    if len(parts)<2 or parts[1]!=CONTEXT_MODE: return None
    need(len(parts)==3 and parts[0]==CONTEXT_SOURCE_SHA and core.SHA_RE.fullmatch(parts[0]) is not None
         and parts[2]==CONTEXT_OPERATION,'local_stay_context_fixed_command')
    need(os.environ.get('GITHUB_RUN_ATTEMPT','1')=='1','local_stay_context_first_attempt_only')
    return {'source_sha':CONTEXT_SOURCE_SHA,'mode':CONTEXT_MODE,'operation_id':CONTEXT_OPERATION,
            'maximum_schema_tables':5,'provider_http_calls':0,'database_writes':0}


def context_hashes():
    need(re.fullmatch(r'[a-f0-9]{64}',CONTEXT_RUNNER_SHA) is not None,'local_stay_context_source_not_final')
    return {**SCHEMA_SOURCE_HASHES,SCHEMA_RUNNER:SCHEMA_RUNNER_SHA,CONTEXT_RUNNER:CONTEXT_RUNNER_SHA}


def bundle_context_source(source_root):
    source=Path(source_root).resolve();control=Path(__file__).resolve().parents[2];hashes=context_hashes();output=io.BytesIO()
    with tarfile.open(fileobj=output,mode='w:gz',format=tarfile.PAX_FORMAT) as archive:
        for relative,digest in hashes.items():
            path=(control if relative in (SCHEMA_RUNNER,CONTEXT_RUNNER) else source)/relative
            need(path.is_file() and not path.is_symlink() and path.resolve()==path,'local_stay_context_source_path')
            raw=path.read_bytes();need(0<len(raw)<=CONTEXT_MAX_BYTES and hashlib.sha256(raw).hexdigest()==digest,
                                     'local_stay_context_reviewed_source_changed')
            info=tarfile.TarInfo(relative);info.size,info.mode,info.mtime=len(raw),0o600,0;archive.addfile(info,io.BytesIO(raw))
        raw=json.dumps({'schema_version':1,'files':hashes},sort_keys=True,separators=(',',':')).encode()
        info=tarfile.TarInfo('manifest.json');info.size,info.mode,info.mtime=len(raw),0o600,0;archive.addfile(info,io.BytesIO(raw))
    return output.getvalue(),hashes


CONTEXT_VALIDATOR = r'''
def local_stay_context_lineage_valid(parent,failure,read_policy,failure_policy):
    command={'source_sha':read_policy['source'],'mode':'local-stay-schema-read-v1','operation_id':read_policy['operation'],
             'maximum_schema_tables':5,'provider_http_calls':0,'database_writes':0}
    if not local_stay_parent_completed(command,parent,read_policy['control']): return False
    read=parent['local_stay_schema_read'];r=read['data']['receipt'];plan=read['data']['plan']
    if (read['fileSha256']!=read_policy['file_sha256'] or r['inventorySha256']!=read_policy['inventory_sha256']
        or r['planSha256']!=read_policy['plan_sha256'] or read['protectedBefore']!=read_policy['protected']
        or plan['state']!='create_only_after_review' or plan['blockedTables']
        or plan['createTables']!=['anytour_hotel_room_concepts_v2','anytour_hotel_meal_concepts_v2','anytour_hotel_stay_mappings_v2']): return False
    keys={'schema_version','mode','operation_id','source_sha','status','reason','supplier_calls','database_writes',
          'booking_calls','lead_calls','profile_writes','mapping_writes','schema_writes','schema_writes_acknowledged',
          'schema_write_outcome_unknown','production_before'}
    if (not isinstance(failure,dict) or set(failure)!=keys or type(failure['schema_version']) is not int or failure['schema_version']!=1
        or failure['mode']!='local-stay-schema-install-v1' or failure['operation_id']!=failure_policy['operation']
        or failure['source_sha']!=failure_policy['source'] or failure['status']!='unknown_no_replay'
        or failure['reason']!='local_stay_install_private_drift' or failure['schema_write_outcome_unknown'] is not False): return False
    if any(type(failure[k]) is not int or failure[k]!=0 for k in ('supplier_calls','database_writes','booking_calls','lead_calls','profile_writes','mapping_writes','schema_writes','schema_writes_acknowledged')): return False
    p=failure['production_before']
    return (isinstance(p,dict) and set(p)=={'index.php','v2/index.php','v2/api-v2.php','v2/lead-adapter-v2.php'}
            and isinstance(p['index.php'],str) and all(v is None or (isinstance(v,str) and re.fullmatch(r'[a-f0-9]{64}',v) is not None) for v in p.values()))
def local_stay_context_valid(data,source_sha,control_sha,read_policy,failure_policy):
    if not isinstance(data,dict) or set(data)!={'receipt','inventory','plan'} or len(local_stay_schema_canonical(data))>262144: return False
    r=data['receipt'];inv=data['inventory'];plan=data['plan']
    keys={'schemaVersion','source','state','observedAt','parentReadOperation','failedInstallOperation','parentReadInventorySha256',
          'failedInstallReason','migrationSha256','supplierCalls','databaseWrites','schemaWrites','automaticAccepts',
          'previousProtected','currentProtectedBefore','currentProtectedAfter','changedFiles','helperAccepted','phpExecuted',
          'connectionAuthority','oldDatabaseIdentityKnown','databaseSha256','schemaState','inventorySha256','planSha256',
          'schemaMatchesParent','applyAuthorized','operationId','sourceSha','controlSha'}
    def digest(x): return isinstance(x,str) and re.fullmatch(r'[a-f0-9]{64}',x) is not None
    def protected(x): return isinstance(x,dict) and set(x)=={'config.php','data/db-v1.php'} and all(digest(v) for v in x.values())
    if (not isinstance(r,dict) or set(r)!=keys or type(r['schemaVersion']) is not int or r['schemaVersion']!=1
        or r['source']!='anytour-hotel-stay-install-context-read-v1' or r['operationId']!='int-andromeda-local-stay-install-context-read-20261026-v1'
        or r['sourceSha']!=source_sha or r['controlSha']!=control_sha or re.fullmatch(r'[a-f0-9]{40}',control_sha or '') is None
        or r['parentReadOperation']!=read_policy['operation'] or r['failedInstallOperation']!=failure_policy['operation']
        or r['parentReadInventorySha256']!=read_policy['inventory_sha256'] or r['failedInstallReason']!='local_stay_install_private_drift'
        or r['migrationSha256']!='1e80434e552fd0c91240db903245bf3f9976d4e937221b3270c5eda92f9ccc06'
        or r['previousProtected']!=read_policy['protected'] or not protected(r['currentProtectedBefore']) or not protected(r['currentProtectedAfter'])
        or r['applyAuthorized'] is not False or r['oldDatabaseIdentityKnown'] is not False): return False
    if any(type(r[k]) is not int or r[k]!=0 for k in ('supplierCalls','databaseWrites','schemaWrites','automaticAccepts')): return False
    if type(r['observedAt']) is not int or not 0<r['observedAt']<=253402300799: return False
    changed=[name for name in ('config.php','data/db-v1.php') if r['currentProtectedBefore'][name]!=r['previousProtected'][name]]
    accepted=r['currentProtectedBefore']['data/db-v1.php']=='ca7f11d6ec53e0cd3d4464e0f645f15b761bb734da1323689479838868d4abc4'
    if (r['changedFiles']!=changed or type(r['helperAccepted']) is not bool or r['helperAccepted']!=accepted
        or type(r['phpExecuted']) is not bool or r['phpExecuted']!=accepted
        or r['state'] not in ('completed_read_only','helper_unreviewed','protected_drift','connection_unavailable','schema_unavailable')): return False
    for field in ('databaseSha256','inventorySha256','planSha256'):
        if r[field] is not None and not digest(r[field]): return False
    if r['schemaMatchesParent'] is not None and type(r['schemaMatchesParent']) is not bool: return False
    drift=r['currentProtectedBefore']!=r['currentProtectedAfter']
    if (r['state']=='protected_drift')!=drift: return False
    if not accepted:
        return (r['state'] in ('helper_unreviewed','protected_drift') and r['connectionAuthority']=='not_examined'
                and r['databaseSha256'] is None and r['schemaState'] is None and inv is None and plan is None
                and r['inventorySha256'] is None and r['planSha256'] is None and r['schemaMatchesParent'] is None)
    if r['state']=='helper_unreviewed': return False
    if r['schemaState']=='completed_read_only':
        old_receipt={'schemaVersion':1,'source':'anytour-hotel-stay-schema-read-v1','state':'completed_read_only',
                     'supplierCalls':0,'databaseWrites':0,'schemaWrites':0,'automaticAccepts':0,'migrationSha256':r['migrationSha256'],
                     'inventorySha256':r['inventorySha256'],'planSha256':r['planSha256'],
                     'operationId':'int-andromeda-local-stay-schema-read-20261025-v1','sourceSha':source_sha,'controlSha':control_sha}
        if not local_stay_schema_valid({'receipt':old_receipt,'inventory':inv,'plan':plan},source_sha,control_sha): return False
        return (r['state'] in ('completed_read_only','protected_drift') and digest(r['databaseSha256'])
                and r['connectionAuthority']=='current_root_config_and_accepted_helper'
                and r['schemaMatchesParent']==(r['inventorySha256']==r['parentReadInventorySha256']))
    if inv is not None or plan is not None or r['inventorySha256'] is not None or r['planSha256'] is not None or r['schemaMatchesParent'] is not None: return False
    if r['state']=='connection_unavailable':
        return r['databaseSha256'] is None and r['schemaState'] is None and r['connectionAuthority']=='not_examined'
    if r['state'] in ('schema_unavailable','protected_drift'):
        return (r['schemaState'] in ('read_failed','metadata_limit','schema_changed',None)
                and r['connectionAuthority']==('current_root_config_and_accepted_helper' if r['databaseSha256'] is not None else 'not_examined'))
    return False
'''


def valid_context_payload(data,control_sha):
    namespace={'hashlib':hashlib,'json':json,'re':re}
    try:
        exec(SCHEMA_VALIDATOR+CONTEXT_VALIDATOR,namespace)
        return namespace['local_stay_context_valid'](data,CONTEXT_SOURCE_SHA,control_sha,INSTALL_PARENT,CONTEXT_FAILURE)
    except (KeyError,TypeError,ValueError,AttributeError,IndexError): return False


def completed_context(command,result,control_sha):
    try:
        expected={'source_sha':CONTEXT_SOURCE_SHA,'mode':CONTEXT_MODE,'operation_id':CONTEXT_OPERATION,
                  'maximum_schema_tables':5,'provider_http_calls':0,'database_writes':0}
        if (command!=expected or any(type(command.get(k)) is not int for k in ('maximum_schema_tables','provider_http_calls','database_writes'))
            or result.get('status')!='complete' or type(result.get('schema_version')) is not int or result['schema_version']!=1
            or any(result.get(k)!=command[k] for k in ('mode','operation_id','source_sha'))
            or len(json.dumps(result,ensure_ascii=False,separators=(',',':')).encode())>CONTEXT_MAX_BYTES): return False
        if any(type(result.get(k)) is not int or result[k]!=0 for k in ('supplier_calls','database_writes','booking_calls','lead_calls','profile_writes','mapping_writes','schema_writes')): return False
        before=result.get('production_before')
        if (not isinstance(before,dict) or set(before)!={'index.php','v2/index.php','v2/api-v2.php','v2/lead-adapter-v2.php'}
            or before!=result.get('production_after') or result.get('production_unchanged') is not True or not isinstance(before['index.php'],str)
            or any(v is not None and (not isinstance(v,str) or re.fullmatch(r'[a-f0-9]{64}',v) is None) for v in before.values())): return False
        context=result.get('local_stay_install_context')
        if (not isinstance(context,dict) or set(context)!={'data','fileSha256','protectedBefore','protectedAfter','parentReadResultSha256','failedInstallResultSha256'}
            or context['parentReadResultSha256']!=INSTALL_PARENT['private_sha256']
            or context['failedInstallResultSha256']!=CONTEXT_FAILURE['private_sha256'] or not valid_context_payload(context['data'],control_sha)): return False
        data=context['data'];r=data['receipt']
        if (r['state']=='protected_drift' or context['protectedBefore']!=r['currentProtectedBefore']
            or context['protectedAfter']!=r['currentProtectedAfter'] or context['protectedBefore']!=context['protectedAfter']): return False
        raw=(json.dumps(data,ensure_ascii=False,sort_keys=True,separators=(',',':')).replace('\u2028','\\u2028').replace('\u2029','\\u2029')+'\n').encode()
        return context['fileSha256']==hashlib.sha256(raw).hexdigest()
    except (KeyError,TypeError,ValueError,AttributeError): return False


CONTEXT_REMOTE_HANDLER = r'''
def local_stay_context_unique(pairs):
    result={}
    for key,value in pairs:
        if key in result: raise ValueError('duplicate_json_key')
        result[key]=value
    return result
def local_stay_context_protected():
    values={}
    for name in ('config.php','data/db-v1.php'):
        path=project/name
        if not safe_file(path,2097152) or path.resolve()!=path: fail('local_stay_context_private_dependency')
        values[name]=hashlib.sha256(path.read_bytes()).hexdigest()
    return values
def local_stay_context_dependencies():
    policy=LOCAL_STAY_CONTEXT_POLICY;read_policy=policy['parent'];failure_policy=policy['failure'];outputs=[]
    for item in (read_policy,failure_policy):
        path=private/item['operation']/'result.json'
        if not safe_file(path,262144) or path.resolve()!=path or path.stat().st_mode&0o777!=0o600: fail('local_stay_context_lineage_file')
        raw=path.read_bytes()
        if hashlib.sha256(raw).hexdigest()!=item['private_sha256']: fail('local_stay_context_lineage_hash')
        try: value=json.loads(raw,object_pairs_hook=local_stay_context_unique)
        except Exception: fail('local_stay_context_lineage_json')
        outputs.append((path,value))
    try: valid=local_stay_context_lineage_valid(outputs[0][1],outputs[1][1],read_policy,failure_policy)
    except Exception: valid=False
    if not valid: fail('local_stay_context_lineage_contract')
    return outputs[0][0],outputs[1][0]
def local_stay_context_held(current,control):
    policy=LOCAL_STAY_CONTEXT_POLICY;previous=policy['parent']['protected']
    return {'receipt':{'schemaVersion':1,'source':'anytour-hotel-stay-install-context-read-v1','state':'helper_unreviewed','observedAt':int(time.time()),
            'parentReadOperation':policy['parent']['operation'],'failedInstallOperation':policy['failure']['operation'],
            'parentReadInventorySha256':policy['parent']['inventory_sha256'],'failedInstallReason':'local_stay_install_private_drift',
            'migrationSha256':'1e80434e552fd0c91240db903245bf3f9976d4e937221b3270c5eda92f9ccc06',
            'supplierCalls':0,'databaseWrites':0,'schemaWrites':0,'automaticAccepts':0,'previousProtected':previous,
            'currentProtectedBefore':current,'currentProtectedAfter':dict(current),
            'changedFiles':[name for name in ('config.php','data/db-v1.php') if current[name]!=previous[name]],
            'helperAccepted':False,'phpExecuted':False,'connectionAuthority':'not_examined','oldDatabaseIdentityKnown':False,
            'databaseSha256':None,'schemaState':None,'inventorySha256':None,'planSha256':None,'schemaMatchesParent':None,
            'applyAuthorized':False,'operationId':operation,'sourceSha':source,'controlSha':control},'inventory':None,'plan':None}
def run_local_stay_install_context(stage):
    policy=LOCAL_STAY_CONTEXT_POLICY
    if payload!={**policy['command'],'archive':payload.get('archive'),'manifest_sha256':payload.get('manifest_sha256')}:
        fail('local_stay_context_payload_scope')
    if any(type(payload.get(k)) is not int for k in ('maximum_schema_tables','provider_http_calls','database_writes')): fail('local_stay_context_payload_types')
    if files!=policy['hashes']: fail('local_stay_context_source_pins')
    result['supplier_calls']=result['database_writes']=result['profile_writes']=result['mapping_writes']=result['schema_writes']=0
    read_path,failure_path=local_stay_context_dependencies();protected_before=local_stay_context_protected()
    path=op/'local-stay-install-context-read.json';run=None
    if protected_before['data/db-v1.php']!='ca7f11d6ec53e0cd3d4464e0f645f15b761bb734da1323689479838868d4abc4':
        data=local_stay_context_held(protected_before,policy['control']);protected_after=local_stay_context_protected()
        data['receipt']['currentProtectedAfter']=protected_after
        if protected_before!=protected_after: data['receipt']['state']='protected_drift'
        raw=local_stay_schema_canonical(data)+b'\n'
        fd=os.open(path,os.O_WRONLY|os.O_CREAT|os.O_EXCL,0o600)
        with os.fdopen(fd,'wb') as handle:
            if handle.write(raw)!=len(raw): fail('local_stay_context_receipt_write')
            handle.flush();os.fsync(handle.fileno())
    else:
        env={k:os.environ[k] for k in ('PATH','HOME','LANG','LC_ALL') if k in os.environ}
        env.update({'ANYTOUR_SITE_ROOT':str(project),'ANYTOUR_OPERATION_OUTPUT_DIR':str(op),
                    'ANYTOUR_OPERATION_RELEASE_SHA':source,'ANYTOUR_OPERATION_CONTROL_SHA':policy['control'],'ANYTOUR_OPERATION_ID':operation,
                    'ANYTOUR_CONTEXT_PARENT_READ_PATH':str(read_path),'ANYTOUR_CONTEXT_FAILED_INSTALL_PATH':str(failure_path)})
        disabled='curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,socket_connect,exec,shell_exec,system,passthru,popen,proc_open'
        run=subprocess.run(['php','-d','display_errors=0','-d','log_errors=0','-d','allow_url_fopen=0',
                            '-d','memory_limit=256M','-d','disable_functions='+disabled,
                            str(stage/'scripts/catalog/anytour_hotel_stay_install_context_read_v1.php')],
                           cwd=project,env=env,capture_output=True,text=True,timeout=180)
    if not safe_file(path,262144) or path.resolve()!=path or path.stat().st_mode&0o777!=0o600: fail('local_stay_context_receipt_file')
    raw=path.read_bytes()
    try: data=json.loads(raw,object_pairs_hook=local_stay_context_unique)
    except Exception: fail('local_stay_context_receipt_json')
    try: valid=local_stay_context_valid(data,source,policy['control'],policy['parent'],policy['failure'])
    except Exception: valid=False
    if not valid: fail('local_stay_context_receipt_contract')
    protected_after=local_stay_context_protected()
    item={'data':data,'fileSha256':hashlib.sha256(raw).hexdigest(),'protectedBefore':protected_before,'protectedAfter':protected_after,
          'parentReadResultSha256':policy['parent']['private_sha256'],'failedInstallResultSha256':policy['failure']['private_sha256']}
    result['local_stay_install_context']=item
    if data['receipt']['currentProtectedBefore']!=protected_before or data['receipt']['currentProtectedAfter']!=protected_after:
        fail('local_stay_context_dependency_drift')
    if run is not None:
        if (run.returncode!=(1 if data['receipt']['state']=='protected_drift' else 0) or run.stderr.strip()
            or len(run.stdout.encode())>262144): fail('local_stay_context_exit_no_replay')
        try: emitted=json.loads(run.stdout,object_pairs_hook=local_stay_context_unique)
        except Exception: fail('local_stay_context_stdout')
        if emitted!=data: fail('local_stay_context_stdout')
    return item
'''
CONTEXT_REMOTE_DISPATCH = r'''    if mode=='local-stay-install-context-read-v1':
        result['local_stay_install_context']=run_local_stay_install_context(stage)
        result['production_after']=fingerprints()
        if result['production_after']!=before: fail('production_drift')
        result['production_unchanged']=True
        result['status']='terminal_nonzero_no_replay' if result['local_stay_install_context']['data']['receipt']['state']=='protected_drift' else 'complete'
        if len(json.dumps(result,ensure_ascii=False,separators=(',',':')).encode())>262144: fail('local_stay_context_terminal_size')
'''


def activate_context(core,command):
    expected=core.parse_command(core.PREFIX+' '.join([str(command.get('source_sha','')),CONTEXT_MODE,str(command.get('operation_id',''))]))
    need(command==expected and all(type(command.get(k)) is int for k in ('maximum_schema_tables','provider_http_calls','database_writes')),
         'local_stay_context_authorized_shape')
    control=os.environ.get('GITHUB_SHA','');need(re.fullmatch(r'[a-f0-9]{40}',control) is not None,'local_stay_context_control_sha')
    hashes=context_hashes();remote=core.REMOTE
    definition='def run_match942(stage, mode, offset, limit):\n';dispatch="    if mode=='match-tv942-write':\n"
    collector="    if mode not in ('reconcile',";manifest="    if not isinstance(files,dict) or len(files)<20: fail('manifest')"
    need(remote.count(definition)==1 and remote.count(dispatch)==1 and remote.count(collector)==2 and remote.count(manifest)==1,
         'local_stay_context_registration_drift')
    policy={'command':expected,'hashes':hashes,'control':control,'parent':INSTALL_PARENT,'failure':CONTEXT_FAILURE}
    parent_constants=('SCHEMA_MAX_BYTES=262144\nSCHEMA_MODE='+repr(SCHEMA_MODE)+'\nSCHEMA_OPERATION='+repr(INSTALL_PARENT['operation'])
                      +'\nSCHEMA_SOURCE_SHA='+repr(INSTALL_PARENT['source'])+'\n')
    parent_validator=('def local_stay_parent_valid_payload(data,control_sha):\n'
                      '    return local_stay_schema_valid(data,SCHEMA_SOURCE_SHA,control_sha)\n')
    program=('LOCAL_STAY_CONTEXT_POLICY='+repr(policy)+'\n'+parent_constants+SCHEMA_VALIDATOR+CONTEXT_VALIDATOR
             +'\n'+parent_validator+'\n'+parent_completion_source()+'\n'+CONTEXT_REMOTE_HANDLER+'\n')
    remote=remote.replace(definition,program+definition,1);remote=remote.replace(dispatch,CONTEXT_REMOTE_DISPATCH+dispatch,1)
    remote=remote.replace(collector,"    if mode not in ('"+CONTEXT_MODE+"','reconcile',")
    remote=remote.replace(manifest,"    if not isinstance(files,dict) or files!=LOCAL_STAY_CONTEXT_POLICY['hashes']: fail('local_stay_context_manifest')",1)
    members='        members=package.getmembers()\n';need(remote.count(members)==1,'local_stay_context_archive_anchor')
    remote=remote.replace(members,members+"        if len(members)!=5 or {m.name for m in members}!={*LOCAL_STAY_CONTEXT_POLICY['hashes'],'manifest.json'}: fail('local_stay_context_archive_scope')\n",1)
    reservation='    op.mkdir(mode=0o700)\n';final="if 'op' in globals() and op.exists():"
    need(remote.count(reservation)==1 and remote.count(final)==1,'local_stay_context_reservation_anchor')
    remote=remote.replace(reservation,reservation+'    local_stay_context_reserved=True\n',1)
    remote=remote.replace(final,"if globals().get('local_stay_context_reserved') is True and op.exists():",1)
    ast.parse(remote);core.REMOTE,core.bundle_source=remote,bundle_context_source
    original_completed=core.completed_result
    core.completed_result=lambda cmd,result: completed_context(cmd,result,control) if cmd.get('mode')==CONTEXT_MODE else original_completed(cmd,result)


# A separate CREATE admission bound to the completed CURRENT connection context.
# The earlier failed install and all four historical profiles remain sealed.
CONTEXT_INSTALL_MODE = 'local-stay-context-install-v1'
CONTEXT_INSTALL_OPERATION = 'int-andromeda-local-stay-context-install-20261026-v1'
CONTEXT_INSTALL_SOURCE_SHA = '30a7e7013edd7fe70a4700db0aafb2bf22ee05f8'
CONTEXT_INSTALL_RUNNER = 'scripts/catalog/anytour_hotel_stay_context_install_v1.php'
CONTEXT_INSTALL_RUNNER_SHA = 'd1d53b15dd25e0fcfe6939a54f2be7b90f057ec9beebf0a89d2b8d133b483847'
CONTEXT_INSTALL_PARENT = {
    'operation': 'int-andromeda-local-stay-install-context-read-20261026-v1',
    'source': '30a7e7013edd7fe70a4700db0aafb2bf22ee05f8',
    'control': 'af256ad98636480ee1e62c59808bad2a612d6420',
    'private_sha256': '21ce19017ad4211a67d8f9e053d2ed033cfa3f5350493d0797084eb31867f1b0',
    'file_sha256': '4f60fd91f95774b08e5b44611a20c6202d989eb96fda9a573a011101bb0ef043',
    'database_sha256': '4cd52b85a632855e79a5fafbe4e425d1c3c6a51dba2c995e0a43f976fbc25f1d',
    'inventory_sha256': '8a37c3c6a3cd4894afff942268d6c669f1ae81f7403253bc58f0b8ae5f38c32e',
    'plan_sha256': '6f1c556fe1a6bf3182f3a49686850f6fcb479cf94e03210881a9faf216b603f6',
    'protected': dict(INSTALL_PARENT['protected']),
}


def parse_context_install_command(core,body):
    if not body.startswith(core.PREFIX): return None
    parts=body[len(core.PREFIX):].split()
    if len(parts)<2 or parts[1]!=CONTEXT_INSTALL_MODE: return None
    need(len(parts)==3 and parts[0]==CONTEXT_INSTALL_SOURCE_SHA and core.SHA_RE.fullmatch(parts[0]) is not None
         and parts[2]==CONTEXT_INSTALL_OPERATION,'local_stay_context_install_fixed_command')
    need(os.environ.get('GITHUB_RUN_ATTEMPT','1')=='1','local_stay_context_install_first_attempt_only')
    return {'source_sha':CONTEXT_INSTALL_SOURCE_SHA,'mode':CONTEXT_INSTALL_MODE,'operation_id':CONTEXT_INSTALL_OPERATION,
            'maximum_schema_tables':3,'provider_http_calls':0,'database_writes':0}


def context_install_hashes():
    need(re.fullmatch(r'[a-f0-9]{64}',CONTEXT_INSTALL_RUNNER_SHA) is not None,'local_stay_context_install_source_not_final')
    return {**SCHEMA_SOURCE_HASHES,SCHEMA_RUNNER:SCHEMA_RUNNER_SHA,CONTEXT_INSTALL_RUNNER:CONTEXT_INSTALL_RUNNER_SHA}


def bundle_context_install_source(source_root):
    source=Path(source_root).resolve();control=Path(__file__).resolve().parents[2];hashes=context_install_hashes();output=io.BytesIO()
    with tarfile.open(fileobj=output,mode='w:gz',format=tarfile.PAX_FORMAT) as archive:
        for relative,digest in hashes.items():
            path=(control if relative in (SCHEMA_RUNNER,CONTEXT_INSTALL_RUNNER) else source)/relative
            need(path.is_file() and not path.is_symlink() and path.resolve()==path,'local_stay_context_install_source_path')
            raw=path.read_bytes();need(0<len(raw)<=262144 and hashlib.sha256(raw).hexdigest()==digest,
                                     'local_stay_context_install_reviewed_source_changed')
            info=tarfile.TarInfo(relative);info.size,info.mode,info.mtime=len(raw),0o600,0;archive.addfile(info,io.BytesIO(raw))
        raw=json.dumps({'schema_version':1,'files':hashes},sort_keys=True,separators=(',',':')).encode()
        info=tarfile.TarInfo('manifest.json');info.size,info.mode,info.mtime=len(raw),0o600,0;archive.addfile(info,io.BytesIO(raw))
    return output.getvalue(),hashes


CONTEXT_INSTALL_VALIDATOR = r'''
def local_stay_context_install_parent_valid(context,read,failure,policy,read_policy,failure_policy):
    if not local_stay_context_lineage_valid(read,failure,read_policy,failure_policy): return False
    command={'source_sha':policy['source'],'mode':'local-stay-install-context-read-v1','operation_id':policy['operation'],
             'maximum_schema_tables':5,'provider_http_calls':0,'database_writes':0}
    if not local_stay_context_parent_completed(command,context,policy['control']): return False
    item=context['local_stay_install_context'];data=item['data'];r=data['receipt'];plan=data['plan']
    return (item['fileSha256']==policy['file_sha256'] and r['state']=='completed_read_only'
            and r['databaseSha256']==policy['database_sha256'] and r['inventorySha256']==policy['inventory_sha256']
            and r['planSha256']==policy['plan_sha256'] and r['schemaState']=='completed_read_only'
            and r['helperAccepted'] is True and r['phpExecuted'] is True and r['changedFiles']==[]
            and r['connectionAuthority']=='current_root_config_and_accepted_helper'
            and r['oldDatabaseIdentityKnown'] is False and r['applyAuthorized'] is False and r['schemaMatchesParent'] is True
            and r['currentProtectedBefore']==policy['protected'] and r['currentProtectedAfter']==policy['protected']
            and r['previousProtected']==read_policy['protected'] and r['parentReadInventorySha256']==read_policy['inventory_sha256']
            and plan['state']=='create_only_after_review' and plan['createTables']==['anytour_hotel_room_concepts_v2','anytour_hotel_meal_concepts_v2','anytour_hotel_stay_mappings_v2']
            and plan['blockedTables']==[] and plan['existingCompatibleTables']==[])
def local_stay_context_install_valid(data,source_sha,control_sha,context,read_policy,failure_policy):
    if not isinstance(data,dict) or set(data)!={'receipt','before','after'} or len(local_stay_schema_canonical(data))>262144: return False
    r=data['receipt'];before=data['before'];after=data['after']
    keys={'schemaVersion','source','state','reason','migrationSha256','parentReadOperation','parentReadInventorySha256',
          'databaseSha256','beforeSha256','afterSha256','attemptedTables','createdTables','unknownTables','tableRows',
          'supplierCalls','databaseWrites','automaticAccepts','schemaWrites','ddlOutcomeUnknown','parentMetadataUnchanged',
          'journalSha256','operationId','sourceSha','controlSha','contextOperation','contextControlSha','contextInventorySha256',
          'contextPlanSha256','contextDatabaseSha256','contextFileSha256','contextResultSha256','parentReadResultSha256',
          'failedInstallResultSha256','protectedBefore','protectedAfter'}
    def digest(x): return isinstance(x,str) and re.fullmatch(r'[a-f0-9]{64}',x) is not None
    def protected(x): return isinstance(x,dict) and set(x)=={'config.php','data/db-v1.php'} and all(digest(v) for v in x.values())
    targets=['anytour_hotel_room_concepts_v2','anytour_hotel_meal_concepts_v2','anytour_hotel_stay_mappings_v2']
    if (not isinstance(r,dict) or set(r)!=keys or type(r['schemaVersion']) is not int or r['schemaVersion']!=1
        or r['source']!='anytour-hotel-stay-context-install-v1' or r['operationId']!='int-andromeda-local-stay-context-install-20261026-v1'
        or r['sourceSha']!=source_sha or r['controlSha']!=control_sha or re.fullmatch(r'[a-f0-9]{40}',control_sha or '') is None
        or r['migrationSha256']!='1e80434e552fd0c91240db903245bf3f9976d4e937221b3270c5eda92f9ccc06'
        or r['parentReadOperation']!=read_policy['operation'] or r['parentReadInventorySha256']!=read_policy['inventory_sha256']
        or r['contextOperation']!=context['operation'] or r['contextControlSha']!=context['control']
        or r['contextInventorySha256']!=context['inventory_sha256'] or r['contextPlanSha256']!=context['plan_sha256']
        or r['contextDatabaseSha256']!=context['database_sha256'] or r['contextFileSha256']!=context['file_sha256']
        or r['contextResultSha256']!=context['private_sha256'] or r['parentReadResultSha256']!=read_policy['private_sha256']
        or r['failedInstallResultSha256']!=failure_policy['private_sha256']): return False
    if any(type(r[k]) is not int or r[k]!=0 for k in ('supplierCalls','databaseWrites','automaticAccepts')): return False
    if type(r['schemaWrites']) is not int or not 0<=r['schemaWrites']<=3 or type(r['ddlOutcomeUnknown']) is not bool: return False
    if r['parentMetadataUnchanged'] is not None and type(r['parentMetadataUnchanged']) is not bool: return False
    if r['state'] not in ('installed_empty','preflight_refused','ddl_unknown','verification_failed'): return False
    if r['reason'] is not None and r['reason'] not in ('context_contract','caller_transaction','source_pin','database_identity','lock_unavailable','schema_preflight','privileges','constraint_collision','session_guards','journal','ddl_execution_failed','postflight','parent_metadata','rows_nonempty','read_failed','ddl_capability','protected_drift','context_identity'): return False
    for field in ('databaseSha256','beforeSha256','afterSha256','journalSha256'):
        if r[field] is not None and not digest(r[field]): return False
    for field in ('protectedBefore','protectedAfter'):
        if r[field] is not None and not protected(r[field]): return False
    for field in ('attemptedTables','createdTables'):
        value=r[field]
        if not isinstance(value,list) or value!=targets[:len(value)] or len(value)>3: return False
    attempted=r['attemptedTables'];created=r['createdTables'];unknown=r['unknownTables']
    if (len(created)>len(attempted) or r['schemaWrites']!=len(created) or not isinstance(unknown,list)
        or unknown!=([attempted[-1]] if r['ddlOutcomeUnknown'] and attempted else [])): return False
    if r['ddlOutcomeUnknown'] and (not attempted or len(attempted)!=len(created)+1): return False
    for field,inv in (('before',before),('after',after)):
        if inv is None:
            if r[field+'Sha256'] is not None: return False
        elif (not local_stay_install_inventory_valid(inv,source_sha,control_sha)
              or r[field+'Sha256']!=hashlib.sha256(local_stay_schema_canonical(inv)).hexdigest()): return False
    if not isinstance(r['tableRows'],list) or len(r['tableRows'])>3: return False
    for pos,row in enumerate(r['tableRows']):
        if (not isinstance(row,dict) or set(row)!={'name','rows'} or row['name']!=targets[pos]
            or type(row['rows']) is not int or not 0<=row['rows']<=9223372036854775807): return False
    if r['state']=='preflight_refused':
        return attempted==created==unknown==[] and r['schemaWrites']==0 and r['ddlOutcomeUnknown'] is False and r['reason'] is not None
    if (r['databaseSha256']!=context['database_sha256'] or before is None or not digest(r['journalSha256'])
        or r['beforeSha256']!=context['inventory_sha256']): return False
    if r['state']=='ddl_unknown': return r['ddlOutcomeUnknown'] is True and r['reason'] is not None
    if r['ddlOutcomeUnknown'] or unknown: return False
    if r['state']=='verification_failed': return r['reason'] is not None and 0<len(created)<=3 and attempted==created
    if (r['reason'] is not None or attempted!=targets or created!=targets or r['schemaWrites']!=3
        or after is None or r['tableRows']!=[{'name':name,'rows':0} for name in targets]
        or r['parentMetadataUnchanged'] is not True or r['protectedBefore']!=context['protected'] or r['protectedAfter']!=context['protected']): return False
    if any(t['compatibility']!='compatible' for t in after['tables']): return False
    return all(before['tables'][i]==after['tables'][i] for i in (0,1))
def local_stay_context_install_journal_valid(events,r):
    if r['journalSha256'] is None: return events==[]
    if not isinstance(events,list) or not 1<=len(events)<=8: return False
    targets=['anytour_hotel_room_concepts_v2','anytour_hotel_meal_concepts_v2','anytour_hotel_stay_mappings_v2']
    statements=['ae700b15382e9797ff20c351abeafd052783a549749431ad3f337c9881f098a8',
                '3dd522bc038f62e3161b15472d2081192bfae635ae9afcb154ece577f62e1762',
                '0789fec589f0ef74fa02a2502ab65d59bcd49576a1961371c09da6ee690ec5d5']
    if events[0]!={'stage':'start','operationId':r['operationId'],'migrationSha256':r['migrationSha256'],
                   'databaseSha256':r['databaseSha256'],'beforeSha256':r['beforeSha256'],
                   'contextResultSha256':r['contextResultSha256'],'contextInventorySha256':r['contextInventorySha256']}: return False
    index=0;pending=None;final=False
    for pos,event in enumerate(events[1:],1):
        if not isinstance(event,dict): return False
        if event.get('stage')=='before_create':
            if pending is not None or index>=3 or final or event!={'stage':'before_create','table':targets[index],'statementSha256':statements[index]}: return False
            pending=targets[index]
        elif event.get('stage')=='created':
            if pending is None or final or event!={'stage':'created','table':pending,'acknowledgedSchemaWrites':index+1}: return False
            pending=None;index+=1
        elif event.get('stage')=='final':
            if (final or pos!=len(events)-1 or set(event)!={'stage','state','reason','attemptedTables','createdTables','unknownTables'}
                or any(event[k]!=r[k] for k in ('attemptedTables','createdTables','unknownTables'))): return False
            if (event['state']!=r['state'] or event['reason']!=r['reason']) and r['reason']!='journal': return False
            final=True
        else: return False
    if index>len(r['createdTables']) or (index!=len(r['createdTables']) and r['reason']!='journal'): return False
    if r['ddlOutcomeUnknown'] and pending!=r['unknownTables'][0]: return False
    if pending is not None and not r['ddlOutcomeUnknown'] and r['reason']!='journal': return False
    if not final and r['reason']!='journal': return False
    return r['state']!='installed_empty' or (index==3 and pending is None and final and len(events)==8)
'''


def context_install_inventory_validator():
    """Reuse only the existing pure metadata shape checker, with no old writer."""
    node=next(n for n in ast.parse(INSTALL_VALIDATOR).body if isinstance(n,ast.FunctionDef) and n.name=='local_stay_install_inventory_valid')
    return ast.get_source_segment(INSTALL_VALIDATOR,node)+'\n'


def valid_context_install_payload(data,control_sha):
    namespace={'hashlib':hashlib,'json':json,'re':re}
    try:
        exec(SCHEMA_VALIDATOR+context_install_inventory_validator()+CONTEXT_INSTALL_VALIDATOR,namespace)
        return namespace['local_stay_context_install_valid'](data,CONTEXT_INSTALL_SOURCE_SHA,control_sha,CONTEXT_INSTALL_PARENT,INSTALL_PARENT,CONTEXT_FAILURE)
    except (KeyError,TypeError,ValueError,AttributeError,IndexError): return False


def completed_context_install(command,result,control_sha):
    try:
        expected={'source_sha':CONTEXT_INSTALL_SOURCE_SHA,'mode':CONTEXT_INSTALL_MODE,'operation_id':CONTEXT_INSTALL_OPERATION,
                  'maximum_schema_tables':3,'provider_http_calls':0,'database_writes':0}
        if (command!=expected or any(type(command.get(k)) is not int for k in ('maximum_schema_tables','provider_http_calls','database_writes'))
            or result.get('status')!='complete' or type(result.get('schema_version')) is not int or result['schema_version']!=1
            or any(result.get(k)!=command[k] for k in ('mode','operation_id','source_sha'))
            or len(json.dumps(result,ensure_ascii=False,separators=(',',':')).encode())>262144): return False
        if any(type(result.get(k)) is not int or result[k]!=0 for k in ('supplier_calls','database_writes','booking_calls','lead_calls','profile_writes','mapping_writes')): return False
        if (type(result.get('schema_writes')) is not int or result['schema_writes']!=3
            or type(result.get('schema_writes_acknowledged')) is not int or result['schema_writes_acknowledged']!=3
            or result.get('schema_write_outcome_unknown') is not False): return False
        before=result.get('production_before')
        if (not isinstance(before,dict) or set(before)!={'index.php','v2/index.php','v2/api-v2.php','v2/lead-adapter-v2.php'}
            or before!=result.get('production_after') or result.get('production_unchanged') is not True or not isinstance(before['index.php'],str)
            or any(v is not None and (not isinstance(v,str) or re.fullmatch(r'[a-f0-9]{64}',v) is None) for v in before.values())): return False
        item=result.get('local_stay_context_install');policy=CONTEXT_INSTALL_PARENT
        if (not isinstance(item,dict) or set(item)!={'data','fileSha256','protectedBefore','protectedAfter','contextResultSha256','parentReadResultSha256','failedInstallResultSha256','journalSha256','journalEvents'}
            or item['contextResultSha256']!=policy['private_sha256'] or item['parentReadResultSha256']!=INSTALL_PARENT['private_sha256']
            or item['failedInstallResultSha256']!=CONTEXT_FAILURE['private_sha256']
            or item['protectedBefore']!=policy['protected'] or item['protectedAfter']!=policy['protected']
            or not valid_context_install_payload(item['data'],control_sha)): return False
        data=item['data'];r=data['receipt'];raw=(json.dumps(data,ensure_ascii=False,sort_keys=True,separators=(',',':')).replace('\u2028','\\u2028').replace('\u2029','\\u2029')+'\n').encode()
        namespace={'hashlib':hashlib,'json':json,'re':re}
        exec(SCHEMA_VALIDATOR+context_install_inventory_validator()+CONTEXT_INSTALL_VALIDATOR,namespace)
        return (r['state']=='installed_empty' and item['fileSha256']==hashlib.sha256(raw).hexdigest()
                and item['journalSha256']==r['journalSha256'] and namespace['local_stay_context_install_journal_valid'](item['journalEvents'],r))
    except (KeyError,TypeError,ValueError,AttributeError,IndexError): return False


CONTEXT_INSTALL_REMOTE_HANDLER = r'''
def local_stay_context_install_unique(pairs):
    value={}
    for key,item in pairs:
        if key in value: raise ValueError('duplicate_json_key')
        value[key]=item
    return value
def local_stay_context_install_protected():
    values={}
    for name in ('config.php','data/db-v1.php'):
        path=project/name
        if not safe_file(path,2097152) or path.resolve()!=path: fail('local_stay_context_install_private_dependency')
        values[name]=hashlib.sha256(path.read_bytes()).hexdigest()
    return values
def local_stay_context_install_dependencies():
    policy=LOCAL_STAY_CONTEXT_INSTALL_POLICY;outputs=[]
    for item in (policy['parent'],policy['failure'],policy['context']):
        path=private/item['operation']/'result.json'
        if not safe_file(path,262144) or path.resolve()!=path or path.stat().st_mode&0o777!=0o600: fail('local_stay_context_install_lineage_file')
        raw=path.read_bytes()
        if hashlib.sha256(raw).hexdigest()!=item['private_sha256']: fail('local_stay_context_install_lineage_hash')
        try: value=json.loads(raw,object_pairs_hook=local_stay_context_install_unique)
        except Exception: fail('local_stay_context_install_lineage_json')
        outputs.append((path,value))
    try: valid=local_stay_context_install_parent_valid(outputs[2][1],outputs[0][1],outputs[1][1],policy['context'],policy['parent'],policy['failure'])
    except Exception: valid=False
    if not valid: fail('local_stay_context_install_lineage_contract')
    return outputs[2][0]
def run_local_stay_context_install(stage):
    policy=LOCAL_STAY_CONTEXT_INSTALL_POLICY
    if payload!={**policy['command'],'archive':payload.get('archive'),'manifest_sha256':payload.get('manifest_sha256')}:
        fail('local_stay_context_install_payload_scope')
    if any(type(payload.get(k)) is not int for k in ('maximum_schema_tables','provider_http_calls','database_writes')): fail('local_stay_context_install_payload_types')
    if files!=policy['hashes']: fail('local_stay_context_install_source_pins')
    result['supplier_calls']=result['database_writes']=result['profile_writes']=result['mapping_writes']=0
    result['schema_writes']=result['schema_writes_acknowledged']=0;result['schema_write_outcome_unknown']=False
    context_path=local_stay_context_install_dependencies();protected_before=local_stay_context_install_protected()
    if protected_before!=policy['context']['protected']: fail('local_stay_context_install_private_drift')
    result['schema_writes']='unknown';result['schema_writes_acknowledged']=0;result['schema_write_outcome_unknown']=True
    env={k:os.environ[k] for k in ('PATH','HOME','LANG','LC_ALL') if k in os.environ}
    env.update({'ANYTOUR_SITE_ROOT':str(project),'ANYTOUR_OPERATION_OUTPUT_DIR':str(op),
                'ANYTOUR_OPERATION_RELEASE_SHA':source,'ANYTOUR_OPERATION_CONTROL_SHA':policy['control'],
                'ANYTOUR_OPERATION_ID':operation,'ANYTOUR_CONTEXT_INSTALL_PARENT_PATH':str(context_path)})
    disabled='curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,socket_connect,exec,shell_exec,system,passthru,popen,proc_open'
    run=subprocess.run(['php','-d','display_errors=0','-d','log_errors=0','-d','allow_url_fopen=0',
                        '-d','memory_limit=256M','-d','disable_functions='+disabled,
                        str(stage/'scripts/catalog/anytour_hotel_stay_context_install_v1.php')],
                       cwd=project,env=env,capture_output=True,text=True,timeout=180)
    path=op/'local-stay-context-install.json'
    if not safe_file(path,262144) or path.resolve()!=path or path.stat().st_mode&0o777!=0o600: fail('local_stay_context_install_receipt_file')
    raw=path.read_bytes()
    try: data=json.loads(raw,object_pairs_hook=local_stay_context_install_unique)
    except Exception: fail('local_stay_context_install_receipt_json')
    try: valid=local_stay_context_install_valid(data,source,policy['control'],policy['context'],policy['parent'],policy['failure'])
    except Exception: valid=False
    if not valid: fail('local_stay_context_install_receipt_contract')
    r=data['receipt'];journal=op/'local-stay-context-install-journal.jsonl';journal_sha=None;events=[]
    item={'data':data,'fileSha256':hashlib.sha256(raw).hexdigest(),'protectedBefore':protected_before,'protectedAfter':None,
          'contextResultSha256':policy['context']['private_sha256'],'parentReadResultSha256':policy['parent']['private_sha256'],
          'failedInstallResultSha256':policy['failure']['private_sha256'],'journalSha256':None,'journalEvents':events}
    result['local_stay_context_install']=item
    result['schema_writes_acknowledged']=r['schemaWrites'];result['schema_write_outcome_unknown']=r['ddlOutcomeUnknown']
    result['schema_writes']='unknown' if r['ddlOutcomeUnknown'] else r['schemaWrites']
    if r['journalSha256'] is not None:
        if not safe_file(journal,262144) or journal.resolve()!=journal or journal.stat().st_mode&0o777!=0o600: fail('local_stay_context_install_journal_file')
        journal_raw=journal.read_bytes();journal_sha=hashlib.sha256(journal_raw).hexdigest()
        if journal_sha!=r['journalSha256'] or not journal_raw.endswith(b'\n'): fail('local_stay_context_install_journal_hash')
        try: events=[json.loads(line,object_pairs_hook=local_stay_context_install_unique) for line in journal_raw.splitlines()]
        except Exception: fail('local_stay_context_install_journal_json')
        if len(events)>8 or any(not isinstance(e,dict) for e in events): fail('local_stay_context_install_journal_scope')
    elif journal.exists() or journal.is_symlink(): fail('local_stay_context_install_unbound_journal')
    item['journalSha256']=journal_sha;item['journalEvents']=events
    if not local_stay_context_install_journal_valid(events,r): fail('local_stay_context_install_journal_contract')
    protected_after=local_stay_context_install_protected();item['protectedAfter']=protected_after
    if (r['protectedBefore']!=protected_before or r['protectedAfter']!=protected_after
        or protected_before!=protected_after): fail('local_stay_context_install_dependency_drift')
    if (run.returncode!=(0 if r['state']=='installed_empty' else 1) or run.stderr.strip()
        or len(run.stdout.encode())>262144): fail('local_stay_context_install_exit_no_replay')
    try: emitted=json.loads(run.stdout,object_pairs_hook=local_stay_context_install_unique)
    except Exception: fail('local_stay_context_install_stdout')
    if emitted!=data: fail('local_stay_context_install_stdout')
    return item
'''
CONTEXT_INSTALL_REMOTE_DISPATCH = r'''    if mode=='local-stay-context-install-v1':
        result['local_stay_context_install']=run_local_stay_context_install(stage)
        result['production_after']=fingerprints()
        if result['production_after']!=before: fail('production_drift')
        result['production_unchanged']=True
        result['status']='complete' if result['local_stay_context_install']['data']['receipt']['state']=='installed_empty' else 'terminal_nonzero_no_replay'
        if len(json.dumps(result,ensure_ascii=False,separators=(',',':')).encode())>262144: fail('local_stay_context_install_terminal_size')
'''


def context_dependency_completion_source():
    source=Path(__file__).read_text();node=next(n for n in ast.parse(source).body if isinstance(n,ast.FunctionDef) and n.name=='completed_context')
    return ast.get_source_segment(source,node).replace('def completed_context(','def local_stay_context_parent_completed(',1).replace('valid_context_payload(','local_stay_context_parent_payload(')


def activate_context_install(core,command):
    expected=core.parse_command(core.PREFIX+' '.join([str(command.get('source_sha','')),CONTEXT_INSTALL_MODE,str(command.get('operation_id',''))]))
    need(command==expected and all(type(command.get(k)) is int for k in ('maximum_schema_tables','provider_http_calls','database_writes')),
         'local_stay_context_install_authorized_shape')
    control=os.environ.get('GITHUB_SHA','');need(re.fullmatch(r'[a-f0-9]{40}',control) is not None,'local_stay_context_install_control_sha')
    hashes=context_install_hashes();remote=core.REMOTE
    definition='def run_match942(stage, mode, offset, limit):\n';dispatch="    if mode=='match-tv942-write':\n"
    collector="    if mode not in ('reconcile',";manifest="    if not isinstance(files,dict) or len(files)<20: fail('manifest')"
    need(remote.count(definition)==1 and remote.count(dispatch)==1 and remote.count(collector)==2 and remote.count(manifest)==1,
         'local_stay_context_install_registration_drift')
    policy={'command':expected,'hashes':hashes,'control':control,'context':CONTEXT_INSTALL_PARENT,'parent':INSTALL_PARENT,'failure':CONTEXT_FAILURE}
    constants=('SCHEMA_MAX_BYTES=262144\nSCHEMA_MODE='+repr(SCHEMA_MODE)+'\nSCHEMA_OPERATION='+repr(INSTALL_PARENT['operation'])
               +'\nSCHEMA_SOURCE_SHA='+repr(INSTALL_PARENT['source'])+'\nCONTEXT_MAX_BYTES=262144\nCONTEXT_MODE='+repr(CONTEXT_MODE)
               +'\nCONTEXT_OPERATION='+repr(CONTEXT_OPERATION)+'\nCONTEXT_SOURCE_SHA='+repr(CONTEXT_SOURCE_SHA)
               +'\nINSTALL_PARENT='+repr(INSTALL_PARENT)+'\nCONTEXT_FAILURE='+repr(CONTEXT_FAILURE)+'\n')
    validators=('def local_stay_parent_valid_payload(data,control_sha):\n'
                '    return local_stay_schema_valid(data,SCHEMA_SOURCE_SHA,control_sha)\n'
                'def local_stay_context_parent_payload(data,control_sha):\n'
                '    return local_stay_context_valid(data,CONTEXT_SOURCE_SHA,control_sha,INSTALL_PARENT,CONTEXT_FAILURE)\n')
    program=('LOCAL_STAY_CONTEXT_INSTALL_POLICY='+repr(policy)+'\n'+constants+SCHEMA_VALIDATOR+CONTEXT_VALIDATOR
             +context_install_inventory_validator()+CONTEXT_INSTALL_VALIDATOR+'\n'+validators+'\n'+parent_completion_source()
             +'\n'+context_dependency_completion_source()+'\n'+CONTEXT_INSTALL_REMOTE_HANDLER+'\n')
    remote=remote.replace(definition,program+definition,1);remote=remote.replace(dispatch,CONTEXT_INSTALL_REMOTE_DISPATCH+dispatch,1)
    remote=remote.replace(collector,"    if mode not in ('"+CONTEXT_INSTALL_MODE+"','reconcile',")
    remote=remote.replace(manifest,"    if not isinstance(files,dict) or files!=LOCAL_STAY_CONTEXT_INSTALL_POLICY['hashes']: fail('local_stay_context_install_manifest')",1)
    members='        members=package.getmembers()\n';need(remote.count(members)==1,'local_stay_context_install_archive_anchor')
    remote=remote.replace(members,members+"        if len(members)!=5 or {m.name for m in members}!={*LOCAL_STAY_CONTEXT_INSTALL_POLICY['hashes'],'manifest.json'}: fail('local_stay_context_install_archive_scope')\n",1)
    reservation='    op.mkdir(mode=0o700)\n';final="if 'op' in globals() and op.exists():"
    need(remote.count(reservation)==1 and remote.count(final)==1,'local_stay_context_install_reservation_anchor')
    remote=remote.replace(reservation,reservation+'    local_stay_context_install_reserved=True\n',1)
    remote=remote.replace(final,"if globals().get('local_stay_context_install_reserved') is True and op.exists():",1)
    ast.parse(remote);core.REMOTE,core.bundle_source=remote,bundle_context_install_source
    original_completed=core.completed_result
    core.completed_result=lambda cmd,result: completed_context_install(cmd,result,control) if cmd.get('mode')==CONTEXT_INSTALL_MODE else original_completed(cmd,result)
