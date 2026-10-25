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
