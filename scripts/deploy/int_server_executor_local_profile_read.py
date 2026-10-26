"""One frozen catalog profile observation in the existing permanent carrier."""
from __future__ import annotations

import ast
import hashlib
import io
import json
import os
from pathlib import Path
import re
import tarfile

MODE = 'local-profile-completeness-read-v1'
OPERATION = 'int-andromeda-local-profile-completeness-read-20261026-v1'
SOURCE_SHA = '1d0e529e3fc0666783db1454c0765d3c16d63843'
RUNNER = 'scripts/catalog/anytour_hotel_profile_completeness_read_v1.php'
RUNNER_SHA = '11e54194b60ec03b2d6bebf8492953685d524a782ac9d80657006e2d54b9f8d3'
SOURCE_HASHES = {
    'v2/data/local-tv-catalog-v1.php': 'abbed95e0343ce402cdcfb11ef7819da0a12e5bafb7e5bf7f22cbb9260167b11',
    'v2/data/hotel-presentation-read-v1.php': '17c9c1ea19de0d86bf6d2d4f13426a8a0cbf232686abd63ec1db5414ccc3d9a3',
    'v2/data/hotel-details-v1.php': '137bbf53ef87fab50505ecd41a075cb5f571193480da7f0e3face51190c0f77f',
    'v2/data/db-v1.php': 'ca7f11d6ec53e0cd3d4464e0f645f15b761bb734da1323689479838868d4abc4',
}
PROTECTED = {'config.php': '121839ce1956ec7a48f0a94758e2c2359293ee01d601c69f720a294c4bda7def',
             'data/db-v1.php': SOURCE_HASHES['v2/data/db-v1.php']}
DATABASE = '4cd52b85a632855e79a5fafbe4e425d1c3c6a51dba2c995e0a43f976fbc25f1d'
MAX_BYTES = 262144


def need(value, reason):
    if not value:
        raise ValueError(reason)


def fixed_command():
    return {'source_sha': SOURCE_SHA, 'mode': MODE, 'operation_id': OPERATION,
            'catalog_hotel_id': 475, 'provider_http_calls': 0, 'database_writes': 0}


def register_parser(core):
    original = core.parse_command

    def parse(body):
        parts = body[len(core.PREFIX):].split() if body.startswith(core.PREFIX) else []
        if len(parts) >= 2 and parts[1] == BATCH_MODE:
            need(parts == [SOURCE_SHA, BATCH_MODE, BATCH_OPERATION], 'local_profile_batch_fixed_command')
            need(os.environ.get('GITHUB_RUN_ATTEMPT', '1') == '1', 'local_profile_batch_first_attempt')
            return batch_command()
        if len(parts) < 2 or parts[1] != MODE:
            return original(body)
        need(parts == [SOURCE_SHA, MODE, OPERATION], 'local_profile_read_fixed_command')
        need(os.environ.get('GITHUB_RUN_ATTEMPT', '1') == '1', 'local_profile_read_first_attempt')
        return fixed_command()
    core.parse_command = parse


def hashes():
    need(re.fullmatch(r'[a-f0-9]{64}', RUNNER_SHA), 'local_profile_read_unreviewed_runner')
    return {RUNNER: RUNNER_SHA, **SOURCE_HASHES}


def bundle_source(source_root):
    source = Path(source_root).resolve()
    control = Path(__file__).resolve().parents[2]
    output = io.BytesIO()
    pins = hashes()
    with tarfile.open(fileobj=output, mode='w:gz', format=tarfile.PAX_FORMAT) as archive:
        for relative, digest in pins.items():
            path = (control if relative == RUNNER else source) / relative
            need(path.is_file() and not path.is_symlink() and path.resolve() == path, 'local_profile_read_source_path')
            raw = path.read_bytes()
            need(0 < len(raw) <= 2097152 and hashlib.sha256(raw).hexdigest() == digest, 'local_profile_read_source_drift')
            info = tarfile.TarInfo(relative)
            info.size, info.mode, info.mtime = len(raw), 0o600, 0
            archive.addfile(info, io.BytesIO(raw))
        raw = json.dumps({'schema_version': 1, 'files': pins}, sort_keys=True, separators=(',', ':')).encode()
        info = tarfile.TarInfo('manifest.json')
        info.size, info.mode, info.mtime = len(raw), 0o600, 0
        archive.addfile(info, io.BytesIO(raw))
    return output.getvalue(), pins


VALIDATOR = r'''
def profile_read_json(value):
    return json.dumps(value,ensure_ascii=False,sort_keys=True,separators=(',',':')).replace('\u2028','\\u2028').replace('\u2029','\\u2029').encode()
def profile_read_unique(pairs):
    value={}
    for k,v in pairs:
        if k in value: raise ValueError('duplicate_json_key')
        value[k]=v
    return value
def profile_read_valid(data,policy):
    digest=lambda x:isinstance(x,str) and re.fullmatch(r'[a-f0-9]{64}',x) is not None
    if not isinstance(data,dict) or set(data)!={'receipt','profile'} or len(profile_read_json(data))>262144: return False
    r=data['receipt'];p=data['profile']
    keys={'schemaVersion','source','state','observedAt','catalogHotelId','countryId','producerNamespace','producerRecordSha256','producerArtifactSha256',
          'databaseSha256Before','databaseSha256After','protectedBefore','protectedAfter','supplierCalls','databaseWrites','profileWrites','mappingWrites',
          'schemaWrites','automaticAccepts','crossNamespaceBridge','priceAuthority','importAllowed','profileSha256','operationId','sourceSha','controlSha'}
    if (not isinstance(r,dict) or set(r)!=keys or type(r['schemaVersion']) is not int or r['schemaVersion']!=1
        or r['source']!='anytour-hotel-profile-completeness-read-v1' or type(r['catalogHotelId']) is not int or r['catalogHotelId']!=475
        or type(r['countryId']) is not int or r['countryId']!=1 or r['producerNamespace']!='catalog_hotels'
        or r['producerRecordSha256']!='6e2dcaad78922dc71375504118ccfeb9be03a7e412604e2e52fd5e40e94ffe87'
        or r['producerArtifactSha256']!='c67a9aca2c2efd00e4a187cc43a3b6d258bd5629457778ee03d82e28f0d63b00'
        or r['operationId']!=policy['command']['operation_id'] or r['sourceSha']!=policy['command']['source_sha'] or r['controlSha']!=policy['control']
        or r['crossNamespaceBridge']!='not_read_not_required' or r['priceAuthority'] is not False or r['importAllowed'] is not False
        or type(r['observedAt']) is not int or not 0<r['observedAt']<=253402300799): return False
    if any(type(r[k]) is not int or r[k]!=0 for k in ('supplierCalls','databaseWrites','profileWrites','mappingWrites','schemaWrites','automaticAccepts')): return False
    for k in ('databaseSha256Before','databaseSha256After'):
        if r[k] is not None and not digest(r[k]): return False
    for k in ('protectedBefore','protectedAfter'):
        if r[k] is not None and (not isinstance(r[k],dict) or set(r[k])!=set(policy['protected']) or any(not digest(v) for v in r[k].values())): return False
    if r['state']!='completed_read_only':
        return (r['state'] in ('catalog_hotel_missing_or_inactive','producer_identity_drift','read_failed','caller_transaction','read_scope',
                               'protected_drift','database_identity','schema_unavailable','row_size_limit','retained_source_invalid','retained_source_unavailable') and p is None and r['profileSha256'] is None)
    if (r['databaseSha256Before']!=policy['database'] or r['databaseSha256After']!=policy['database']
        or r['protectedBefore']!=policy['protected'] or r['protectedAfter']!=policy['protected']
        or not isinstance(p,dict) or set(p)!={'name','detailsAvailable','description','primaryImage','galleryCount','gallerySha256','roomTypes','address','detailsFetchedAt','provenance','consumer','retainedSource'}
        or type(p['detailsAvailable']) is not bool or type(p['galleryCount']) is not int or not 0<=p['galleryCount']<=100
        or not digest(p['gallerySha256']) or r['profileSha256']!=hashlib.sha256(profile_read_json(p)).hexdigest()): return False
    def fact(f):
        if (not isinstance(f,dict) or set(f)!={'present','byteLength','sha256'} or type(f['present']) is not bool
            or type(f['byteLength']) is not int or not 0<=f['byteLength']<=1048576
            or (f['present'] and (f['byteLength']<1 or not digest(f['sha256'])))
            or (not f['present'] and (f['byteLength']!=0 or f['sha256'] is not None))): return False
        return True
    if any(not fact(p[k]) for k in ('name','description','primaryImage','roomTypes','address','detailsFetchedAt')): return False
    source=p['retainedSource'];c=p['consumer']
    if (not isinstance(source,dict) or set(source)!={'sha256','fetchedAt','identity'} or not digest(source['sha256'])
        or source['identity']!='verified_tourvisor_475' or not fact(source['fetchedAt']) or not source['fetchedAt']['present']
        or not isinstance(c,dict) or set(c)!={'state','profile','sourceSha256','sourceFetchedAt'}
        or c['state'] not in ('missing','excluded','observed') or (c['sourceSha256'] is not None and not digest(c['sourceSha256']))): return False
    if c['state']=='missing':
        if c!={'state':'missing','profile':None,'sourceSha256':None,'sourceFetchedAt':None}: return False
    else:
        if not fact(c['sourceFetchedAt']): return False
        if c['state']=='excluded':
            if c['profile'] is not None: return False
        else:
            cp=c['profile']
            if (not isinstance(cp,dict) or set(cp)!={'description','primaryImage','galleryCount','gallerySha256','detailsAvailable','revision','contentState','manualFieldCount','manualFieldsSha256','sourceAbsentCount','sourceAbsentSha256'}
                or any(not fact(cp[k]) for k in ('description','primaryImage')) or type(cp['detailsAvailable']) is not bool
                or cp['contentState'] not in ('pending','ready','retry','generic')
                or any(type(cp[k]) is not int or not 0<=cp[k]<=2097152 for k in ('galleryCount','manualFieldCount','sourceAbsentCount'))
                or type(cp['revision']) is not int or cp['revision']<1
                or any(not digest(cp[k]) for k in ('gallerySha256','manualFieldsSha256','sourceAbsentSha256'))): return False
    return p['provenance']=={'reader':'hotel-presentation-read-v1','detailsSource':'catalog_hotel_details_success_only','consumerReader':'local-tv-catalog-v1','manualOrOwnSource':'effective_manual_fields_hashed','generatedSeoAcceptedAsTruth':False}
'''


def policy(control):
    return {'command': fixed_command(), 'hashes': hashes(), 'control': control, 'protected': PROTECTED, 'database': DATABASE}


def valid_payload(data, control):
    namespace = {'hashlib': hashlib, 'json': json, 're': re}
    try:
        exec(VALIDATOR, namespace)
        return namespace['profile_read_valid'](data, policy(control))
    except (KeyError, TypeError, ValueError, AttributeError):
        return False


def completed(command, result, control):
    try:
        if (command != fixed_command() or any(type(command.get(k)) is not int for k in ('catalog_hotel_id','provider_http_calls','database_writes'))
            or result.get('status') != 'complete' or type(result.get('schema_version')) is not int or result['schema_version'] != 1
            or any(result.get(k) != command[k] for k in ('mode','operation_id','source_sha'))
            or len(json.dumps(result).encode()) > MAX_BYTES): return False
        if any(type(result.get(k)) is not int or result[k] != 0 for k in ('supplier_calls','database_writes','booking_calls','lead_calls','profile_writes','mapping_writes','schema_writes')): return False
        before=result.get('production_before')
        if (not isinstance(before,dict) or set(before)!={'index.php','v2/index.php','v2/api-v2.php','v2/lead-adapter-v2.php'}
            or not isinstance(before['index.php'],str) or before!=result.get('production_after') or result.get('production_unchanged') is not True
            or any(v is not None and (not isinstance(v,str) or re.fullmatch(r'[a-f0-9]{64}',v) is None) for v in before.values())): return False
        item=result['local_profile_completeness']
        if (set(item)!={'data','fileSha256','protectedBefore','protectedAfter'} or item['protectedBefore']!=PROTECTED
            or item['protectedAfter']!=PROTECTED or not valid_payload(item['data'],control)): return False
        ns={'json':json};exec(VALIDATOR,{'json':json,'re':re,'hashlib':hashlib},ns)
        raw=ns['profile_read_json'](item['data'])+b'\n'
        return item['fileSha256']==hashlib.sha256(raw).hexdigest()
    except (KeyError,TypeError,ValueError,AttributeError):
        return False


REMOTE_HANDLER = r'''
def profile_read_protected():
    values={}
    for name in PROFILE_READ_POLICY['protected']:
        path=project/name
        if not safe_file(path,2097152) or path.resolve()!=path: fail('local_profile_read_private_dependency')
        values[name]=hashlib.sha256(path.read_bytes()).hexdigest()
    return values
def run_profile_read(stage):
    result.update(supplier_calls=0,database_writes=0,profile_writes=0,mapping_writes=0,schema_writes=0)
    if payload!={**PROFILE_READ_POLICY['command'],'archive':payload.get('archive'),'manifest_sha256':payload.get('manifest_sha256')}: fail('local_profile_read_payload')
    if any(type(payload.get(k)) is not int for k in ('catalog_hotel_id','provider_http_calls','database_writes')): fail('local_profile_read_payload_types')
    protected=profile_read_protected()
    item={'data':None,'fileSha256':None,'protectedBefore':protected,'protectedAfter':None}
    result['local_profile_completeness']=item
    if protected!=PROFILE_READ_POLICY['protected']: fail('local_profile_read_private_drift')
    env={k:os.environ[k] for k in ('PATH','HOME','LANG','LC_ALL') if k in os.environ}
    env.update(ANYTOUR_SITE_ROOT=str(project),ANYTOUR_OPERATION_OUTPUT_DIR=str(op),ANYTOUR_OPERATION_RELEASE_SHA=source,
               ANYTOUR_OPERATION_CONTROL_SHA=PROFILE_READ_POLICY['control'],ANYTOUR_OPERATION_ID=operation)
    disabled='curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,socket_connect,exec,shell_exec,system,passthru,popen,proc_open'
    run=subprocess.run(['php','-d','display_errors=0','-d','log_errors=0','-d','allow_url_fopen=0','-d','memory_limit=128M',
                        '-d','disable_functions='+disabled,str(stage/'scripts/catalog/anytour_hotel_profile_completeness_read_v1.php')],
                       cwd=project,env=env,capture_output=True,text=True,timeout=90)
    path=op/'local-profile-completeness-read.json'
    if not safe_file(path,262144) or path.resolve()!=path or path.stat().st_mode&0o777!=0o600: fail('local_profile_read_receipt_file')
    raw=path.read_bytes()
    try: data=json.loads(raw,object_pairs_hook=profile_read_unique)
    except Exception: fail('local_profile_read_receipt_json')
    item.update(data=data,fileSha256=hashlib.sha256(raw).hexdigest(),protectedAfter=profile_read_protected())
    if not profile_read_valid(data,PROFILE_READ_POLICY): fail('local_profile_read_receipt_contract')
    if run.returncode!=0 or run.stderr.strip() or len(run.stdout.encode())>262144: fail('local_profile_read_terminal_no_replay')
    try: emitted=json.loads(run.stdout,object_pairs_hook=profile_read_unique)
    except Exception: fail('local_profile_read_stdout')
    if emitted!=data or protected!=item['protectedAfter']: fail('local_profile_read_result_drift')
    return item
'''
REMOTE_DISPATCH = r'''    if mode=='local-profile-completeness-read-v1':
        result['local_profile_completeness']=run_profile_read(stage)
        result['production_after']=fingerprints()
        if result['production_after']!=before: fail('production_drift')
        result['production_unchanged']=True;result['status']='complete'
'''


def activate(core, command):
    if command.get('mode') == BATCH_MODE:
        return activate_batch(core, command)
    if command.get('mode') != MODE:
        return
    expected=core.parse_command(core.PREFIX+' '.join([SOURCE_SHA,MODE,OPERATION]))
    need(command==expected and all(type(command.get(k)) is int for k in ('catalog_hotel_id','provider_http_calls','database_writes')), 'local_profile_read_shape')
    control=os.environ.get('GITHUB_SHA','')
    need(re.fullmatch(r'[a-f0-9]{40}',control), 'local_profile_read_control')
    remote=core.REMOTE
    definition='def run_match942(stage, mode, offset, limit):\n';dispatch="    if mode=='match-tv942-write':\n"
    collector="    if mode not in ('reconcile',";manifest="    if not isinstance(files,dict) or len(files)<20: fail('manifest')"
    need(remote.count(definition)==1 and remote.count(dispatch)==1 and remote.count(collector)==2 and remote.count(manifest)==1,'local_profile_read_registration')
    remote=remote.replace(definition,'PROFILE_READ_POLICY='+repr(policy(control))+'\n'+VALIDATOR+REMOTE_HANDLER+'\n'+definition,1)
    remote=remote.replace(dispatch,REMOTE_DISPATCH+dispatch,1).replace(collector,"    if mode not in ('"+MODE+"','reconcile',")
    remote=remote.replace(manifest,"    if not isinstance(files,dict) or files!=PROFILE_READ_POLICY['hashes']: fail('local_profile_read_manifest')",1)
    members='        members=package.getmembers()\n'
    need(remote.count(members)==1,'local_profile_read_archive_anchor')
    remote=remote.replace(members,members+"        if len(members)!=len(PROFILE_READ_POLICY['hashes'])+1 or {m.name for m in members}!={*PROFILE_READ_POLICY['hashes'],'manifest.json'}: fail('local_profile_read_archive')\n",1)
    reservation='    op.mkdir(mode=0o700)\n';final="if 'op' in globals() and op.exists():"
    need(remote.count(reservation)==1 and remote.count(final)==1,'local_profile_read_reservation')
    remote=remote.replace(reservation,reservation+'    profile_read_reserved=True\n',1).replace(final,"if globals().get('profile_read_reserved') is True and op.exists():",1)
    ast.parse(remote)
    core.REMOTE,core.bundle_source=remote,bundle_source
    original=core.completed_result
    core.completed_result=lambda cmd,r:completed(cmd,r,control) if cmd.get('mode')==MODE else original(cmd,r)


# Additive finite batch after the consumed fixed475 proof. Its capsule/pins stay intact.
BATCH_MODE = 'local-profile-batch-read-v1'
BATCH_OPERATION = 'int-andromeda-local-profile-batch-read-20261026-v1'
BATCH_RUNNER = 'scripts/catalog/anytour_hotel_profile_batch_read_v1.php'
BATCH_MANIFEST = 'scripts/catalog/fixtures/local-profile-batch-20261026.json'
BATCH_RUNNER_SHA = '92d95e5920449375836de22ea2f312b9226480bc2f1fc2dd629031fafdf0e67e'
BATCH_MANIFEST_SHA = 'a591a3c09db7e77f251665ffe76782004a0645e7f2d0735b18e5845d75550d33'


def batch_command():
    return {'source_sha': SOURCE_SHA, 'mode': BATCH_MODE, 'operation_id': BATCH_OPERATION,
            'maximum_hotels': 20, 'provider_http_calls': 0, 'database_writes': 0}


def batch_hashes():
    need(re.fullmatch(r'[a-f0-9]{64}',BATCH_RUNNER_SHA) and re.fullmatch(r'[a-f0-9]{64}',BATCH_MANIFEST_SHA), 'local_profile_batch_unreviewed')
    return {**hashes(),BATCH_RUNNER:BATCH_RUNNER_SHA,BATCH_MANIFEST:BATCH_MANIFEST_SHA}


def batch_policy(control):
    path=Path(__file__).resolve().parents[2]/BATCH_MANIFEST
    need(path.is_file() and not path.is_symlink() and hashlib.sha256(path.read_bytes()).hexdigest()==BATCH_MANIFEST_SHA,'local_profile_batch_manifest_pin')
    manifest=json.loads(path.read_text())
    need(len(manifest['records'])==20 and len({x['hotelId'] for x in manifest['records']})==20
         and all(x['hotelId']!=475 for x in manifest['records']),'local_profile_batch_manifest_scope')
    return {'command':batch_command(),'hashes':batch_hashes(),'control':control,'protected':PROTECTED,'database':DATABASE,
            'manifestSha256':BATCH_MANIFEST_SHA,'targets':manifest['records']}


def bundle_batch_source(source_root):
    source=Path(source_root).resolve();control=Path(__file__).resolve().parents[2];output=io.BytesIO();pins=batch_hashes()
    with tarfile.open(fileobj=output,mode='w:gz',format=tarfile.PAX_FORMAT) as archive:
        for relative,digest in pins.items():
            path=(control if relative in (RUNNER,BATCH_RUNNER,BATCH_MANIFEST) else source)/relative
            need(path.is_file() and not path.is_symlink() and path.resolve()==path,'local_profile_batch_source_path')
            raw=path.read_bytes();need(0<len(raw)<=2097152 and hashlib.sha256(raw).hexdigest()==digest,'local_profile_batch_source_drift')
            info=tarfile.TarInfo(relative);info.size,info.mode,info.mtime=len(raw),0o600,0;archive.addfile(info,io.BytesIO(raw))
        raw=json.dumps({'schema_version':1,'files':pins},sort_keys=True,separators=(',',':')).encode()
        info=tarfile.TarInfo('manifest.json');info.size,info.mode,info.mtime=len(raw),0o600,0;archive.addfile(info,io.BytesIO(raw))
    return output.getvalue(),pins


BATCH_VALIDATOR = r'''
def profile_read_json(value):
    return json.dumps(value,ensure_ascii=False,sort_keys=True,separators=(',',':')).replace('\u2028','\\u2028').replace('\u2029','\\u2029').encode()
def profile_read_unique(pairs):
    value={}
    for k,v in pairs:
        if k in value: raise ValueError('duplicate_json_key')
        value[k]=v
    return value
def profile_read_valid(data,policy):
    digest=lambda v:isinstance(v,str) and re.fullmatch(r'[a-f0-9]{64}',v) is not None
    integer=lambda v,lo,hi:type(v) is int and lo<=v<=hi
    reason=lambda v:isinstance(v,str) and re.fullmatch(r'[a-z_]{1,64}',v) is not None
    def fact(v):
        return (isinstance(v,dict) and set(v)=={'present','byteLength','sha256'} and type(v['present']) is bool
                and integer(v['byteLength'],0,2097152) and ((v['present'] and v['byteLength']>0 and digest(v['sha256']))
                or (not v['present'] and v['byteLength']==0 and v['sha256'] is None)))
    if not isinstance(data,dict) or set(data)!={'receipt','items'} or len(profile_read_json(data))>262144: return False
    r=data['receipt'];items=data['items']
    keys={'schemaVersion','source','state','reason','observedAt','manifestSha256','producerArtifactSha256','producerJsonSha256',
          'requestedCount','observedCount','heldCount','possibleGapCount','databaseSha256Before','databaseSha256After','protectedBefore','protectedAfter',
          'supplierCalls','databaseWrites','profileWrites','mappingWrites','schemaWrites','automaticAccepts','priceAuthority','importAllowed','itemsSha256',
          'operationId','sourceSha','controlSha'}
    if (not isinstance(r,dict) or set(r)!=keys or not integer(r['schemaVersion'],1,1) or r['source']!='anytour-hotel-profile-batch-read-v1'
        or r['state'] not in ('completed_read_only','held') or not integer(r['observedAt'],1,253402300799)
        or r['manifestSha256']!=policy['manifestSha256'] or r['producerArtifactSha256']!='c67a9aca2c2efd00e4a187cc43a3b6d258bd5629457778ee03d82e28f0d63b00'
        or r['producerJsonSha256']!='0b79183d8e4d8615cd810151431859ca1cd43c5e1c066fb9d0b2a9e0930fe251'
        or not integer(r['requestedCount'],20,20) or any(not integer(r[k],0,20) for k in ('observedCount','heldCount','possibleGapCount'))
        or r['observedCount']+r['heldCount']!=20 or r['priceAuthority'] is not False or r['importAllowed'] is not False
        or r['operationId']!=policy['command']['operation_id'] or r['sourceSha']!=policy['command']['source_sha'] or r['controlSha']!=policy['control']
        or any(not integer(r[k],0,0) for k in ('supplierCalls','databaseWrites','profileWrites','mappingWrites','schemaWrites','automaticAccepts'))
        or not isinstance(items,list) or len(items)!=20 or r['itemsSha256']!=hashlib.sha256(profile_read_json(items)).hexdigest()): return False
    for k in ('databaseSha256Before','databaseSha256After'):
        if r[k] is not None and not digest(r[k]): return False
    for k in ('protectedBefore','protectedAfter'):
        if r[k] is not None and (not isinstance(r[k],dict) or set(r[k])!=set(policy['protected']) or any(not digest(v) for v in r[k].values())): return False
    if r['state']=='completed_read_only':
        if (r['reason'] is not None or r['databaseSha256Before']!=policy['database'] or r['databaseSha256After']!=policy['database']
            or r['protectedBefore']!=policy['protected'] or r['protectedAfter']!=policy['protected']): return False
    elif not reason(r['reason']) or r['observedCount']!=0 or r['heldCount']!=20 or r['possibleGapCount']!=0: return False
    observed=possible=0;fields=('description','primaryImage','images')
    for item,target in zip(items,policy['targets']):
        if (not isinstance(item,dict) or set(item)!={'hotelId','countryId','recordSha256','state','reason','source','consumer','gaps'}
            or type(item['hotelId']) is not int or item['hotelId']!=target['hotelId'] or type(item['countryId']) is not int or item['countryId']!=target['countryId']
            or item['recordSha256']!=target['recordSha256']): return False
        if item['state']=='held':
            if not reason(item['reason']) or any(item[k] is not None for k in ('source','consumer','gaps')): return False
            continue
        if item['state']!='observed' or item['reason'] is not None or r['state']!='completed_read_only': return False
        observed+=1;s=item['source'];c=item['consumer'];g=item['gaps']
        if (not isinstance(s,dict) or set(s)!={'description','primaryImage','galleryCount','gallerySha256','detailsAvailable','sourceSha256','sourceFetchedAt'}
            or not fact(s['description']) or not fact(s['primaryImage']) or not integer(s['galleryCount'],0,2097152) or not digest(s['gallerySha256'])
            or type(s['detailsAvailable']) is not bool or not digest(s['sourceSha256']) or not fact(s['sourceFetchedAt']) or not s['sourceFetchedAt']['present']): return False
        if (not isinstance(c,dict) or set(c)!={'state','profile','sourceSha256','sourceFetchedAt'} or c['state'] not in ('observed','missing','excluded')
            or (c['sourceSha256'] is not None and not digest(c['sourceSha256'])) or (c['sourceFetchedAt'] is not None and not fact(c['sourceFetchedAt']))): return False
        if c['state']=='observed':
            cp=c['profile']
            if (not isinstance(cp,dict) or set(cp)!={'description','primaryImage','galleryCount','gallerySha256','detailsAvailable','revision','contentState','manualFieldCount','manualFieldsSha256','sourceAbsentCount','sourceAbsentSha256'}
                or not fact(cp['description']) or not fact(cp['primaryImage']) or not integer(cp['galleryCount'],0,2097152) or not digest(cp['gallerySha256'])
                or type(cp['detailsAvailable']) is not bool or not integer(cp['revision'],1,2**63-1) or cp['contentState'] not in ('pending','ready','retry','generic')
                or any(not integer(cp[k],0,2097152) for k in ('manualFieldCount','sourceAbsentCount'))
                or any(not digest(cp[k]) for k in ('manualFieldsSha256','sourceAbsentSha256'))): return False
        elif c['profile'] is not None: return False
        if not isinstance(g,dict) or set(g)!={'sourceMissing','consumerMissing','manualProtectedMissing','possibleFillFields'}: return False
        for value in g.values():
            if not isinstance(value,list) or any(v not in fields for v in value) or value!=[f for f in fields if f in value]: return False
        def missing(p):return [f for f in fields if not (p['galleryCount']>0 if f=='images' else p[f]['present'])]
        if g['sourceMissing']!=missing(s) or g['consumerMissing']!=(missing(c['profile']) if c['state']=='observed' else []): return False
        if any(f not in g['consumerMissing'] for f in g['manualProtectedMissing']): return False
        if g['possibleFillFields']!=[f for f in g['consumerMissing'] if f not in g['sourceMissing'] and f not in g['manualProtectedMissing']]: return False
        possible+=bool(g['possibleFillFields'])
    return r['observedCount']==observed and r['heldCount']==20-observed and r['possibleGapCount']==possible
'''


def valid_batch_payload(data,control):
    namespace={'hashlib':hashlib,'json':json,'re':re}
    try:
        exec(BATCH_VALIDATOR,namespace)
        return namespace['profile_read_valid'](data,batch_policy(control))
    except (KeyError,TypeError,ValueError,AttributeError):return False


def completed_batch(command,result,control):
    try:
        if (command!=batch_command() or any(type(command.get(k)) is not int for k in ('maximum_hotels','provider_http_calls','database_writes'))
            or result.get('status')!='complete' or type(result.get('schema_version')) is not int or result['schema_version']!=1
            or any(result.get(k)!=command[k] for k in ('mode','operation_id','source_sha')) or len(json.dumps(result).encode())>524288):return False
        if any(type(result.get(k)) is not int or result[k]!=0 for k in ('supplier_calls','database_writes','booking_calls','lead_calls','profile_writes','mapping_writes','schema_writes')):return False
        before=result.get('production_before')
        if (not isinstance(before,dict) or set(before)!={'index.php','v2/index.php','v2/api-v2.php','v2/lead-adapter-v2.php'} or not isinstance(before['index.php'],str)
            or before!=result.get('production_after') or result.get('production_unchanged') is not True
            or any(v is not None and (not isinstance(v,str) or re.fullmatch(r'[a-f0-9]{64}',v) is None) for v in before.values())):return False
        item=result['local_profile_batch']
        if (not isinstance(item,dict) or set(item)!={'data','fileSha256','protectedBefore','protectedAfter'} or item['protectedBefore']!=PROTECTED
            or item['protectedAfter']!=PROTECTED or not valid_batch_payload(item['data'],control) or item['data']['receipt']['state']!='completed_read_only'):return False
        raw=json.dumps(item['data'],ensure_ascii=False,sort_keys=True,separators=(',',':')).replace('\u2028','\\u2028').replace('\u2029','\\u2029').encode()+b'\n'
        return item['fileSha256']==hashlib.sha256(raw).hexdigest()
    except (KeyError,TypeError,ValueError,AttributeError):return False


def activate_batch(core,command):
    expected=core.parse_command(core.PREFIX+' '.join([SOURCE_SHA,BATCH_MODE,BATCH_OPERATION]))
    need(command==expected and all(type(command.get(k)) is int for k in ('maximum_hotels','provider_http_calls','database_writes')),'local_profile_batch_shape')
    control=os.environ.get('GITHUB_SHA','');need(re.fullmatch(r'[a-f0-9]{40}',control),'local_profile_batch_control')
    remote=core.REMOTE;definition='def run_match942(stage, mode, offset, limit):\n';dispatch="    if mode=='match-tv942-write':\n"
    collector="    if mode not in ('reconcile',";manifest="    if not isinstance(files,dict) or len(files)<20: fail('manifest')"
    need(remote.count(definition)==1 and remote.count(dispatch)==1 and remote.count(collector)==2 and remote.count(manifest)==1,'local_profile_batch_registration')
    handler=REMOTE_HANDLER.replace('run_profile_read','run_profile_batch').replace("('catalog_hotel_id','provider_http_calls','database_writes')","('maximum_hotels','provider_http_calls','database_writes')").replace('local_profile_completeness','local_profile_batch').replace('anytour_hotel_profile_completeness_read_v1.php','anytour_hotel_profile_batch_read_v1.php').replace('local-profile-completeness-read.json','local-profile-batch-read.json').replace('local_profile_read_','local_profile_batch_')
    code=REMOTE_DISPATCH.replace(MODE,BATCH_MODE).replace('local_profile_completeness','local_profile_batch').replace('run_profile_read','run_profile_batch')
    remote=remote.replace(definition,'PROFILE_READ_POLICY='+repr(batch_policy(control))+'\n'+BATCH_VALIDATOR+handler+'\n'+definition,1).replace(dispatch,code+dispatch,1)
    remote=remote.replace(collector,"    if mode not in ('"+BATCH_MODE+"','reconcile',").replace(manifest,"    if not isinstance(files,dict) or files!=PROFILE_READ_POLICY['hashes']: fail('local_profile_batch_manifest')",1)
    members='        members=package.getmembers()\n';need(remote.count(members)==1,'local_profile_batch_archive_anchor')
    remote=remote.replace(members,members+"        if len(members)!=len(PROFILE_READ_POLICY['hashes'])+1 or {m.name for m in members}!={*PROFILE_READ_POLICY['hashes'],'manifest.json'}: fail('local_profile_batch_archive')\n",1)
    reservation='    op.mkdir(mode=0o700)\n';final="if 'op' in globals() and op.exists():"
    need(remote.count(reservation)==1 and remote.count(final)==1,'local_profile_batch_reservation')
    remote=remote.replace(reservation,reservation+'    profile_read_reserved=True\n',1).replace(final,"if globals().get('profile_read_reserved') is True and op.exists():",1)
    ast.parse(remote);core.REMOTE,core.bundle_source=remote,bundle_batch_source
    original=core.completed_result
    core.completed_result=lambda cmd,r:completed_batch(cmd,r,control) if cmd.get('mode')==BATCH_MODE else original(cmd,r)
