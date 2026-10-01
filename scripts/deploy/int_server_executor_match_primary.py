"""Bounded MATCH registration for the existing stock executor entrypoint.

No new transport or credential mechanism: checked_event and execute stay in core.
All old modes delegate unchanged. Native110 stages have fixed intake scopes.
"""
from __future__ import annotations

import ast
import re

MODE = 'match-primary-candidate'
READBACK_MODE = 'match-primary-proof-readback'
NATIVE_MODE = 'match-native110-current'
NATIVE_OPERATION = 'int-andromeda-match-native-current-20261001-v1'
NATIVE_BATCH = 'native110-20260928'
GUARDED_MODE = 'match-native110-write'
GUARDED_OPERATION = 'int-andromeda-match-native110-write-20261001-v1'
GUARDED_INPUT_SHA = '59cfe4bf4001636f77a0e8ad440475c4d6b514599c9147fc984daad5f4f7849e'
BG_MODE = 'match-native110-bg-evidence'
BG_OPERATION = 'int-andromeda-match-native110-bg-evidence-20261001-v1'
SHAMS_GEO_MODE = 'match-shams-geo-evidence'
SHAMS_GEO_OPERATION = 'int-andromeda-match-shams-geo-evidence-20261001-v1'
SHAMS_GEO_READBACK_MODE = 'match-shams-geo-readback'
SHAMS_GEO_READBACK_OPERATION = 'int-andromeda-match-shams-geo-readback-20261001-v1'
SHAMS_WRITE_MODE = 'match-shams-current-write'
SHAMS_WRITE_OPERATION = 'int-andromeda-match-shams-current-write-20261001-v1'
SHAMS_WRITE_BATCH = 'shams9501-geo-20261001'
TARGET_MODE = 'match-tv-live30-target-catalog'
TARGET_OPERATION = 'int-andromeda-match-live30-target-catalog-20261001-v1'
TARGET_BATCH = 'tv-live30-targets-20261001'
TARGET_READBACK_MODE = 'match-tv-live30-target-readback'
TARGET_READBACK_OPERATION = 'int-andromeda-match-live30-target-readback-20261001-v1'
TARGET_PREFLIGHT_MODE = 'match-tv-live30-target-preflight'
TARGET_PREFLIGHT_OPERATION = 'int-andromeda-match-live30-target-preflight-20261001-v1'
TARGET_PREFLIGHT_BATCH = 'tv-live30-target-preflight-20261001'
TARGET_SOURCE_FILES = (
    'scripts/diagnostics/hotel_match_pending8_transition_v76.php',
    'scripts/diagnostics/hotel_match_tv_live30_target_catalog_v1.php',
)
TARGET_PREFLIGHT_SOURCE_FILES = (
    'scripts/diagnostics/hotel_match_pending8_transition_v76.php',
    'scripts/diagnostics/hotel_match_tv_live30_target_preflight_v1.php',
)
BG_EXPECTED = {'13293': (367, '610184500', '102610184500'), '60328': (9242, '625162113', '102625162113'),
    '205729': (9283, '625414997', '102625414997'), '2000041008': (62868, '610121438', '102610121438'),
    '2000052316': (70457, '610144591', '102610144591'), '2000059209': (67000, '610155352', '102610155352'),
    '2000060910': (72889, '610175943', '102610175943'), '2000062548': (72865, '610149698', '102610149698'),
    '2000062557': (75791, '610179507', '102610179507'), '2000071830': (15902, '610210401', '102610210401'),
    '2000081107': (83106, '610227700', '102610227700'), '2000086021': (316, '668981793', '102668981793'),
    '2000086129': (75538, '610160139', '102610160139'), '2000087863': (99582, '610222069', '102610222069'),
    '2000041090': (1572, '625076488', '102625076488'), '2000072753': (1267, '610197738', '102610197738'),
    '2000072804': (65770, '610175325', '102610175325'), '2000079689': (1175, '610214047', '102610214047')}
BATCH = 'samo3-20260929'
OPERATION_RE = re.compile(r'\Aint-andromeda-match-primary-[a-z0-9-]{8,48}-v[1-9][0-9]*\Z')
SOURCE_FILES = (
    'scripts/diagnostics/hotel_match_pending8_transition_v76.php',
    'scripts/diagnostics/hotel_match_raw_native_pending11_v78.php',
    'scripts/diagnostics/hotel_match_primary_candidate_v1.php',
)
PROOF_SOURCE_FILES = SOURCE_FILES + ('scripts/diagnostics/hotel_match_primary_proof_audit_v1.php',)
NATIVE_SOURCE_FILES = PROOF_SOURCE_FILES + (
    'scripts/diagnostics/hotel_match_native110_current_v1.php',
    'scripts/diagnostics/fixtures/hotel_match_native110_current_v1.json',
)
GUARDED_SOURCE_FILES = NATIVE_SOURCE_FILES + ('scripts/diagnostics/hotel_match_native110_guarded_v1.php',)
BG_SOURCE_FILES = NATIVE_SOURCE_FILES + ('scripts/diagnostics/hotel_match_native110_bg_evidence_v1.php',)
SHAMS_GEO_SOURCE_FILES = NATIVE_SOURCE_FILES + ('scripts/diagnostics/hotel_match_shams_geography_saved_v1.php',)
SHAMS_WRITE_SOURCE_FILES = tuple(dict.fromkeys(GUARDED_SOURCE_FILES + SHAMS_GEO_SOURCE_FILES +
    ('scripts/diagnostics/hotel_match_shams_guarded_v1.php',)))


def register_parser(core) -> None:
    """Extend mode parsing; never replace owner/ref/comment authorization."""
    original = core.parse_command

    def parse(body: str) -> dict:
        if not body.startswith(core.PREFIX):
            return original(body)
        parts = body[len(core.PREFIX):].split()
        if len(parts) < 2 or parts[1] not in (MODE, READBACK_MODE, NATIVE_MODE, GUARDED_MODE, BG_MODE, SHAMS_GEO_MODE, SHAMS_GEO_READBACK_MODE, SHAMS_WRITE_MODE, TARGET_MODE, TARGET_READBACK_MODE, TARGET_PREFLIGHT_MODE):
            return original(body)
        core.need(len(parts) == 4, 'primary_command_shape')
        source, mode, operation, batch = parts
        core.need(core.SHA_RE.fullmatch(source) is not None, 'source_sha')
        if mode == TARGET_PREFLIGHT_MODE:
            core.need(operation == TARGET_PREFLIGHT_OPERATION and batch == TARGET_PREFLIGHT_BATCH, 'target_preflight_fixed_scope')
            return {'source_sha': source, 'mode': mode, 'operation_id': operation, 'batch': TARGET_PREFLIGHT_BATCH,
                    'maximum_writes': 0, 'provider_http_calls': 0}
        if mode == TARGET_READBACK_MODE:
            core.need(operation == TARGET_READBACK_OPERATION and batch == TARGET_BATCH, 'target_readback_fixed_scope')
            return {'source_sha': source, 'mode': mode, 'operation_id': operation, 'batch': TARGET_BATCH,
                    'maximum_writes': 0, 'provider_http_calls': 0}
        if mode == TARGET_MODE:
            core.need(operation == TARGET_OPERATION and batch == TARGET_BATCH, 'target_catalog_fixed_scope')
            return {'source_sha': source, 'mode': mode, 'operation_id': operation, 'batch': TARGET_BATCH,
                    'maximum_writes': 0, 'provider_http_calls': 0}
        if mode == SHAMS_WRITE_MODE:
            core.need(operation == SHAMS_WRITE_OPERATION and batch == SHAMS_WRITE_BATCH, 'shams_write_fixed_scope')
            return {'source_sha': source, 'mode': mode, 'operation_id': operation, 'batch': SHAMS_WRITE_BATCH,
                    'maximum_writes': 1, 'provider_http_calls': 0, 'input_sha256': GUARDED_INPUT_SHA,
                    'geography_operation': SHAMS_GEO_READBACK_OPERATION}
        if mode == SHAMS_GEO_READBACK_MODE:
            core.need(operation == SHAMS_GEO_READBACK_OPERATION and batch == NATIVE_BATCH, 'shams_geo_readback_fixed_scope')
            return {'source_sha': source, 'mode': mode, 'operation_id': operation, 'batch': NATIVE_BATCH,
                    'maximum_writes': 0, 'provider_http_calls': 0, 'input_sha256': GUARDED_INPUT_SHA}
        if mode == SHAMS_GEO_MODE:
            core.need(operation == SHAMS_GEO_OPERATION and batch == NATIVE_BATCH, 'shams_geo_fixed_scope')
            return {'source_sha': source, 'mode': mode, 'operation_id': operation, 'batch': NATIVE_BATCH,
                    'maximum_writes': 0, 'provider_http_calls': 0, 'input_sha256': GUARDED_INPUT_SHA}
        if mode == BG_MODE:
            core.need(operation == BG_OPERATION and batch == NATIVE_BATCH, 'bg_fixed_scope')
            return {'source_sha': source, 'mode': mode, 'operation_id': operation, 'batch': NATIVE_BATCH,
                    'maximum_writes': 0, 'provider_http_calls': 0, 'input_sha256': GUARDED_INPUT_SHA}
        if mode == GUARDED_MODE:
            core.need(operation == GUARDED_OPERATION and batch == NATIVE_BATCH, 'guarded_fixed_scope')
            return {'source_sha': source, 'mode': mode, 'operation_id': operation, 'batch': NATIVE_BATCH,
                    'maximum_writes': 4, 'provider_http_calls': 0, 'input_sha256': GUARDED_INPUT_SHA}
        if mode == NATIVE_MODE:
            core.need(operation == NATIVE_OPERATION and batch == NATIVE_BATCH, 'native110_fixed_scope')
            return {'source_sha': source, 'mode': mode, 'operation_id': operation,
                    'batch': NATIVE_BATCH, 'maximum_writes': 0, 'provider_http_calls': 0}
        core.need(core.OP_RE.fullmatch(operation) is not None
                  and OPERATION_RE.fullmatch(operation) is not None, 'primary_operation')
        core.need(batch == BATCH, 'primary_batch')
        return {'source_sha': source, 'mode': mode, 'operation_id': operation,
                'batch': BATCH, 'maximum_writes': 0 if mode == READBACK_MODE else 3, 'provider_http_calls': 0}

    core.parse_command = parse


REMOTE_HANDLER = r'''
def run_match_primary_candidate(stage):
    if (payload.get('batch')!='samo3-20260929'
            or payload.get('maximum_writes')!=3 or payload.get('provider_http_calls')!=0
            or not re.fullmatch(r'int-andromeda-match-primary-[a-z0-9-]{8,48}-v[1-9][0-9]*',operation)):
        fail('primary_batch_scope')
    expected={('9501',420),('2000034238',16944),('3126',42903)}
    match_home=home/'.anytoour-match'
    match_root=match_home/'operations'
    for directory in (match_home,match_root):
        if directory.is_symlink() or directory.resolve()!=directory:
            fail('primary_private_root')
        directory.mkdir(mode=0o700,exist_ok=True)
    child=match_root/operation
    if child.exists() or child.is_symlink(): fail('primary_child_exists_no_replay')
    # A new operation name alone cannot rerun this approved first batch.
    batch_marker=match_home/'primary-batch-samo3-20260929.json'
    reservation={'operation':operation,'parent_operation':operation,'source_sha':source,
                 'batch':'samo3-20260929','maximum_writes':3,'provider_http_calls':0,
                 'state':'reserved_before_db','reserved_at':int(time.time())}
    def exclusive_json(path,value):
        data=json.dumps(value,sort_keys=True,separators=(',',':')).encode()+b'\n'
        fd=os.open(path,os.O_WRONLY|os.O_CREAT|os.O_EXCL,0o600)
        with os.fdopen(fd,'wb') as stream:
            stream.write(data);stream.flush();os.fsync(stream.fileno())
    exclusive_json(batch_marker,reservation)
    child.mkdir(mode=0o700)
    exclusive_json(child/'reservation.json',reservation)
    runner=stage/'scripts/diagnostics/hotel_match_primary_candidate_v1.php'
    if not safe_file(runner,2*1024*1024): fail('primary_runner_missing')
    # No supplier credentials or arbitrary environment is passed to this PHP mode.
    child_env={key:os.environ[key] for key in ('PATH','HOME','LANG','LC_ALL') if key in os.environ}
    child_env.update({'ANYTOUR_ROOT':str(project),'MATCH_OPERATION_DIR':str(child),
                      'MATCH_SOURCE_SHA':source})
    run=subprocess.run(['php',str(runner),'--execute'],cwd=project,env=child_env,
                       capture_output=True,text=True,timeout=240)
    result_path=child/'result.json';receipt_path=child/'receipt.json'
    if not safe_file(result_path,2*1024*1024) or not safe_file(receipt_path,1048576):
        fail('primary_terminal_missing_no_replay')
    data=safe_json(result_path,2*1024*1024);receipt=safe_json(receipt_path,1048576)
    digest=hashlib.sha256(result_path.read_bytes()).hexdigest()
    if (data.get('operation')!=operation or data.get('source_sha')!=source
            or data.get('batch')!='samo3-20260929' or data.get('requested_candidates')!=3
            or data.get('provider_http_calls')!=0 or data.get('no_replay') is not True
            or receipt.get('operation')!=operation or receipt.get('source_sha')!=source
            or receipt.get('batch')!='samo3-20260929' or receipt.get('result_sha256')!=digest
            or receipt.get('state')!=data.get('state') or receipt.get('provider_http_calls')!=0
            or receipt.get('no_replay') is not True):
        fail('primary_terminal_binding')
    count=data.get('mapping_writes')
    if count is not None and (type(count) is not int or not 0<=count<=3):
        fail('primary_write_count')
    if (data.get('database_writes')!=count or receipt.get('mapping_writes')!=count
            or receipt.get('database_writes')!=count
            or receipt.get('readback_verified')!=data.get('readback_verified')):
        fail('primary_terminal_count_binding')
    seen=set()
    for key in ('rows','held','already'):
        items=data.get(key,[])
        if not isinstance(items,list) or len(items)>3: fail('primary_terminal_row_shape')
        for row in items:
            if not isinstance(row,dict): fail('primary_terminal_row_shape')
            pair=(str(row.get('catalog_id')),row.get('local_hotel_id'))
            if pair not in expected or pair in seen: fail('primary_terminal_row_scope')
            seen.add(pair)
    successful=data.get('state') in ('committed_readback_verified','completed_no_new_writes')
    if successful:
        if (run.returncode!=0 or run.stderr.strip() or type(count) is not int
                or data.get('readback_verified') is not True
                or len(data.get('rows',[]))!=count
                or data.get('current_candidates_evaluated')!=3 or seen!=expected):
            fail('primary_success_contract')
        if data['state']=='committed_readback_verified' and (count<1
                or data.get('commit_completed') is not True
                or data.get('effective_resolver_verified') is not True):
            fail('primary_commit_contract')
        if data['state']=='completed_no_new_writes' and count!=0:
            fail('primary_zero_contract')
    elif data.get('readback_verified') is True:
        fail('primary_false_verification')
    return {'operation':operation,'result_sha256':digest,'successful':successful,
            'exit_code':run.returncode,'stderr_sha256':hashlib.sha256(run.stderr.encode()).hexdigest() if run.stderr else None,
            'summary':data}

'''

REMOTE_DISPATCH = r'''    if mode=='match-primary-candidate':
        primary=run_match_primary_candidate(stage)
        result['match_primary_candidate']=primary
        result['supplier_calls']=0
        result['database_writes']=primary['summary'].get('database_writes')
        result['mapping_writes']=primary['summary'].get('mapping_writes')
        result['production_after']=fingerprints()
        if result['production_after']!=before: fail('production_drift')
        result['production_unchanged']=True
        result['status']='complete' if primary['successful'] else 'terminal_nonzero_no_replay'
'''


REMOTE_PROOF_HANDLER = r'''
def run_match_primary_proof_readback(stage):
    if (payload.get('batch')!='samo3-20260929' or payload.get('maximum_writes')!=0
            or payload.get('provider_http_calls')!=0
            or not re.fullmatch(r'int-andromeda-match-primary-[a-z0-9-]{8,48}-v[1-9][0-9]*',operation)):
        fail('primary_proof_scope')
    root=home/'.anytoour-match/operations'
    if not root.is_dir() or root.is_symlink() or root.resolve()!=root:
        fail('primary_proof_private_root')
    runner=stage/'scripts/diagnostics/hotel_match_primary_proof_audit_v1.php'
    if not safe_file(runner,2*1024*1024): fail('primary_proof_runner_missing')
    env={key:os.environ[key] for key in ('PATH','HOME','LANG','LC_ALL') if key in os.environ}
    run=subprocess.run(['php','-d','display_errors=0','-d','log_errors=0',str(runner),'--read-saved',str(root)],
                       cwd=project,env=env,capture_output=True,text=True,timeout=90)
    if run.returncode!=0 or run.stderr.strip() or len(run.stdout.encode())>256*1024:
        fail('primary_proof_read_failed')
    data=json.loads(run.stdout)
    top_fields={'state','batch','rows','provider_http_calls','database_writes','mapping_writes','safe_to_write_now'}
    if (not isinstance(data,dict) or set(data) not in (top_fields,top_fields|{'origin_lookup'})
            or data.get('state')!='completed_saved_proof_audit' or data.get('batch')!='samo3-20260929'
            or any(type(data.get(k)) is not int or data[k]!=0 for k in ('provider_http_calls','database_writes','mapping_writes'))
            or data.get('safe_to_write_now') is not False): fail('primary_proof_authority')
    expected={
        420:('9501','operator_342','24402','hotel-match-residual2041-common4-nonanex-detail-1971-20260921-v4','2534eebc2a8b0ba79dbf32dedda165c7e87da609284b25c5209c04747624e564'),
        16944:('2000034238','operator_315','211585','hotel-match-residual2041-search30-common4-1971-20260921-v3','76c740c4efbb95a2c2c44fd7fe69ecea30b5091c0e17dfec2bb4cf30da75cea2'),
        42903:('3126','operator_315','849821','hotel-match-residual2041-common4-nonanex-detail-1971-20260921-v4','2534eebc2a8b0ba79dbf32dedda165c7e87da609284b25c5209c04747624e564'),
    }
    rows=data.get('rows')
    if not isinstance(rows,list) or len(rows)!=3: fail('primary_proof_rows')
    seen=set()
    fields={'tv_hotel_id','catalog_id','supplier_namespace','native_id','source_operation','source_result_sha256','safe_to_write_now','state','failures'}
    proof_fields={'proof_matches','source_targets','target_natives','edge_checks','invalid_edge_rows','edge_checks_omitted'}
    failure_names={'producer_edges_missing','verified_edge_missing','source_target_not_unique','target_native_not_unique',
        'retained_relative_path','retained_file','retained_read','retained_digest','retained_json','retained_terminal','salvaged_terminal_required','retained_read_failed'}
    edge_fields={'tv_hotel_id','operator_id','state','link_state','namespace','positive_native_candidates',
        'operator_link_sha256','tour_id_sha256','search_id_sha256','operator_link_host'}
    for row in rows:
        if not isinstance(row,dict): fail('primary_proof_rows')
        identity=row.get('tv_hotel_id')
        if type(identity) is not int or identity not in expected or identity in seen: fail('primary_proof_pair')
        seen.add(identity)
        if (tuple(row.get(k) for k in ('catalog_id','supplier_namespace','native_id','source_operation','source_result_sha256'))!=expected[identity]
                or row.get('safe_to_write_now') is not False): fail('primary_proof_pair')
        state=row.get('state'); failures=row.get('failures')
        if (state not in ('producer_unavailable','proof_hold','saved_tv_proof_verified')
                or not isinstance(failures,list) or any(f not in failure_names for f in failures)
                or (state=='saved_tv_proof_verified')!= (failures==[])): fail('primary_proof_state')
        if state=='producer_unavailable':
            if set(row)!=fields: fail('primary_proof_projection')
            continue
        # Missing edges has the smaller diagnostic schema; no raw field is permitted.
        if set(row) not in (fields|proof_fields,fields|{'proof_matches','source_targets','target_natives','edge_checks'}): fail('primary_proof_projection')
        for key in ('proof_matches','invalid_edge_rows','edge_checks_omitted'):
            if key in row and (type(row[key]) is not int or row[key]<0): fail('primary_proof_count')
        for key in ('source_targets','target_natives','edge_checks'):
            if not isinstance(row.get(key),list) or len(row[key])>1000: fail('primary_proof_projection')
        if any(type(i) is not int or i<=0 for i in row['source_targets']): fail('primary_proof_target')
        if any(not isinstance(n,str) or not re.fullmatch(r'[1-9][0-9]{0,31}',n) for n in row['target_natives']): fail('primary_proof_native')
        if len(row['edge_checks'])>100: fail('primary_proof_projection')
        for check in row['edge_checks']:
            if (not isinstance(check,dict) or set(check)!={'json_pointer','verified','failed_fields'}
                    or not isinstance(check['json_pointer'],str) or not re.fullmatch(r'/edges/[0-9]{1,8}',check['json_pointer'])
                    or type(check['verified']) is not bool or not isinstance(check['failed_fields'],list)
                    or any(f not in edge_fields for f in check['failed_fields'])): fail('primary_proof_projection')
    inventory=data.get('origin_lookup')
    if inventory is not None:
        if (not isinstance(inventory,dict) or set(inventory)!={'state','files_read','bytes_read','skipped_large_files','invalid_files','rows'}
                or inventory['state'] not in ('completed_bounded_inventory','partial_bounded_inventory')
                or any(type(inventory[k]) is not int or inventory[k]<0 for k in ('files_read','bytes_read','skipped_large_files','invalid_files'))
                or inventory['files_read']>5000 or inventory['bytes_read']>536870912
                or not isinstance(inventory['rows'],list) or len(inventory['rows'])!=3): fail('primary_proof_inventory')
        seen=set()
        for row in inventory['rows']:
            if (not isinstance(row,dict) or set(row)!={'tv_hotel_id','references'} or type(row['tv_hotel_id']) is not int
                    or row['tv_hotel_id'] not in expected or row['tv_hotel_id'] in seen
                    or not isinstance(row['references'],list) or len(row['references'])>100): fail('primary_proof_inventory')
            identity=row['tv_hotel_id'];seen.add(identity)
            operator=43 if identity==420 else 25
            for ref in row['references']:
                if (not isinstance(ref,dict) or set(ref)!={'source_operation','file','sha256','json_pointer','verified','failed_fields'}
                        or not isinstance(ref['source_operation'],str) or not re.fullmatch(r'hotel-match-[a-zA-Z0-9_-]{1,180}',ref['source_operation'])
                        or ref['file'] not in ('result.json','tv-edge-'+str(identity)+'-'+str(operator)+'.json')
                        or not isinstance(ref['sha256'],str) or not re.fullmatch(r'[a-f0-9]{64}',ref['sha256'])
                        or not isinstance(ref['json_pointer'],str)
                        or not (re.fullmatch(r'/edges/[0-9]{1,8}',ref['json_pointer']) if ref['file']=='result.json' else ref['json_pointer']=='')
                        or type(ref['verified']) is not bool or not isinstance(ref['failed_fields'],list)
                        or any(f not in edge_fields for f in ref['failed_fields'])): fail('primary_proof_inventory')
    return data

'''

REMOTE_PROOF_DISPATCH = r'''    if mode=='match-primary-proof-readback':
        result['match_primary_proof_readback']=run_match_primary_proof_readback(stage)
        result['supplier_calls']=0
        result['database_reads']=0
        result['database_writes']=0
        result['mapping_writes']=0
        result['production_after']=fingerprints()
        if result['production_after']!=before: fail('production_drift')
        result['production_unchanged']=True
        result['status']='complete'
'''


REMOTE_NATIVE_HANDLER = r'''
def run_match_native110_current(stage):
    if (operation!='int-andromeda-match-native-current-20261001-v1'
            or payload.get('batch')!='native110-20260928'
            or payload.get('maximum_writes')!=0 or payload.get('provider_http_calls')!=0):
        fail('native110_fixed_scope')
    root=home/'.anytoour-match/operations'
    if not root.is_dir() or root.is_symlink() or root.resolve()!=root:
        fail('native110_private_root')
    child=root/operation
    if child.exists() or child.is_symlink(): fail('native110_exists_no_replay')
    child.mkdir(mode=0o700)
    reservation={'operation':operation,'source_sha':source,'batch':'native110-20260928',
                 'maximum_writes':0,'provider_http_calls':0,'state':'reserved_before_db_read',
                 'reserved_at':int(time.time())}
    fd=os.open(child/'reservation.json',os.O_WRONLY|os.O_CREAT|os.O_EXCL,0o600)
    with os.fdopen(fd,'wb') as stream:
        stream.write(json.dumps(reservation,sort_keys=True,separators=(',',':')).encode()+b'\n')
        stream.flush();os.fsync(stream.fileno())
    runner=stage/'scripts/diagnostics/hotel_match_native110_current_v1.php'
    if not safe_file(runner,2*1024*1024): fail('native110_runner_missing_no_replay')
    env={key:os.environ[key] for key in ('PATH','HOME','LANG','LC_ALL') if key in os.environ}
    env.update({'ANYTOUR_ROOT':str(project),'MATCH_OPERATION_DIR':str(child),'MATCH_SOURCE_SHA':source})
    disabled='curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,socket_create,socket_connect,exec,system,shell_exec,passthru,proc_open,popen'
    run=subprocess.run(['php','-d','display_errors=0','-d','log_errors=0','-d','allow_url_fopen=0',
                        '-d','disable_functions='+disabled,str(runner),'--current'],cwd=project,env=env,
                       capture_output=True,text=True,timeout=240)
    if run.returncode!=0 or run.stderr.strip() or len(run.stdout.encode())>262144:
        fail('native110_read_failed_no_replay')
    data=json.loads(run.stdout)
    fields={'state','operation','source_sha','batch','manifest_sha256','sources_requested','sources_examined',
            'protected_skipped','current_rows_returned','raw_verified_facts','raw_files_read','raw_bytes_read',
            'native_facts_examined','provider_http_calls','database_writes','mapping_writes','safe_to_write_now',
            'no_replay','acceptance_policy_changed','review_rows'}
    if (not isinstance(data,dict) or set(data)!=fields
            or data['state']!='completed_native110_current_review' or data['operation']!=operation
            or data['source_sha']!=source or data['batch']!='native110-20260928'
            or data['safe_to_write_now'] is not False or data['acceptance_policy_changed'] is not False
            or data['no_replay'] is not True): fail('native110_summary_contract')
    fixed={'sources_requested':110,'sources_examined':109,'protected_skipped':1,
           'current_rows_returned':110,'native_facts_examined':3262,
           'provider_http_calls':0,'database_writes':0,'mapping_writes':0}
    if any(type(data[k]) is not int or data[k]!=v for k,v in fixed.items()): fail('native110_summary_counts')
    for k,cap in (('raw_verified_facts',3262),('raw_files_read',1000),('raw_bytes_read',536870912)):
        if type(data[k]) is not int or not 0<=data[k]<=cap: fail('native110_summary_bounds')
    def shape(value,keys):
        if not isinstance(value,dict) or set(value)!=set(keys.split()): fail('native110_review_projection')
    def identity(value):
        if not isinstance(value,str) or not re.fullmatch(r'[1-9][0-9]{0,31}',value): fail('native110_review_identity')
    def count(value,cap=50000):
        if type(value) is not int or not 0<=value<=cap: fail('native110_review_count')
    def codes(value):
        if not isinstance(value,list) or len(value)>100 or any(not isinstance(v,str) or not re.fullmatch(r'[a-z_]{1,100}',v) for v in value): fail('native110_review_codes')
    def items(value,cap=128):
        if not isinstance(value,list) or len(value)>cap: fail('native110_review_items')
        return value
    namespaces={'operator_5','operator_115','operator_315','operator_342'}
    def native(value):
        identity(value.get('native_id'))
        if value.get('namespace') not in namespaces: fail('native110_review_namespace')
    reviews=data['review_rows'];items(reviews,110)
    if len(reviews)!=110: fail('native110_review_membership')
    seen=set()
    for row in reviews:
        shape(row,'catalog_id state safe_to_write_now holds catalog_digest_matches_saved evidence_digest_matches_saved source_history_id_matches native_checks operator_checks targets tv_checks')
        identity(row['catalog_id'])
        if row['catalog_id'] in seen or row['safe_to_write_now'] is not False: fail('native110_review_authority')
        seen.add(row['catalog_id']);codes(row['holds'])
        for key in ('catalog_digest_matches_saved','evidence_digest_matches_saved','source_history_id_matches'):
            if type(row[key]) is not bool: fail('native110_review_bool')
        if row['catalog_id']=='2000086118':
            if (row['state']!='protected_not_examined' or row['holds']
                    or any(row[k] for k in ('native_checks','operator_checks','targets','tv_checks',
                                           'catalog_digest_matches_saved','evidence_digest_matches_saved','source_history_id_matches'))): fail('native110_protected_readback')
            continue
        if row['state']!='current_review_observed': fail('native110_review_state')
        for f in items(row['native_checks']):
            shape(f,'namespace native_id global_saved_unique raw_verified failures');native(f);codes(f['failures'])
            if type(f['global_saved_unique']) is not bool or type(f['raw_verified']) is not bool: fail('native110_review_bool')
        for o in items(row['operator_checks']):
            shape(o,'namespace native_id current_identity_count current_local_hotel_ids');native(o);count(o['current_identity_count'])
            for id in items(o['current_local_hotel_ids'],50000):
                count(id,9223372036854775807)
                if id==0: fail('native110_review_identity')
        for t in items(row['targets'],200):
            shape(t,'kind id tv_live30_observed holds');count(t['id'],9223372036854775807);codes(t['holds'])
            if t['id']==0 or t['kind'] not in ('tv_candidate','independent_local_anchor') or type(t['tv_live30_observed']) is not bool: fail('native110_review_target')
        for c in items(row['tv_checks'],100):
            shape(c,'tv_hotel_id operator native_id tv_native_id state producers');count(c['tv_hotel_id'],9223372036854775807)
            identity(c['native_id']);identity(c['tv_native_id'])
            if c['tv_hotel_id']==0 or c['operator'] not in ('anex','bg','funsun','intourist') or c['state'] not in ('namespace_bridge_review_required','saved_producers_reviewed'): fail('native110_review_tv')
            for p in items(c['producers'],2):
                shape(p,'source_operation source_result_sha256 state failures');codes(p['failures'])
                if (not isinstance(p['source_operation'],str) or not re.fullmatch(r'hotel-match-[a-zA-Z0-9_-]{1,180}',p['source_operation'])
                        or not isinstance(p['source_result_sha256'],str) or not re.fullmatch(r'[a-f0-9]{64}',p['source_result_sha256'])
                        or p['state'] not in ('producer_unavailable','proof_hold','saved_tv_proof_verified')): fail('native110_review_producer')
    if '2000086118' not in seen: fail('native110_review_membership')
    manifest_path=child/'native110-current-manifest.json'
    summary_path=child/'native110-current-summary.json'
    if (not isinstance(data['manifest_sha256'],str) or not re.fullmatch(r'[a-f0-9]{64}',data['manifest_sha256'])
            or not safe_file(manifest_path,64*1024*1024) or not safe_file(summary_path,262144)
            or hashlib.sha256(manifest_path.read_bytes()).hexdigest()!=data['manifest_sha256']
            or safe_json(summary_path,262144)!=data): fail('native110_private_result_binding')
    return data

'''

REMOTE_NATIVE_DISPATCH = r'''    if mode=='match-native110-current':
        result['match_native110_current']=run_match_native110_current(stage)
        result['supplier_calls']=0
        result['database_writes']=0
        result['mapping_writes']=0
        result['production_after']=fingerprints()
        if result['production_after']!=before: fail('production_drift')
        result['production_unchanged']=True
        result['status']='complete'
'''


REMOTE_GUARDED_HANDLER = r'''
def run_match_native110_write(stage):
    input_sha='59cfe4bf4001636f77a0e8ad440475c4d6b514599c9147fc984daad5f4f7849e'
    if (operation!='int-andromeda-match-native110-write-20261001-v1' or payload.get('batch')!='native110-20260928'
            or payload.get('maximum_writes')!=4 or payload.get('provider_http_calls')!=0
            or payload.get('input_sha256')!=input_sha): fail('guarded_fixed_scope')
    root=home/'.anytoour-match/operations'
    if not root.is_dir() or root.is_symlink() or root.resolve()!=root: fail('guarded_private_root')
    child=root/operation
    if child.exists() or child.is_symlink(): fail('guarded_exists_no_replay')
    reservation={'operation':operation,'source_sha':source,'batch':'native110-20260928','input_sha256':input_sha,
                 'maximum_writes':4,'provider_http_calls':0,'state':'reserved_before_db','reserved_at':int(time.time())}
    def exclusive(path,value):
        fd=os.open(path,os.O_WRONLY|os.O_CREAT|os.O_EXCL,0o600)
        with os.fdopen(fd,'wb') as stream:
            stream.write(json.dumps(value,sort_keys=True,separators=(',',':')).encode()+b'\n')
            stream.flush();os.fsync(stream.fileno())
    # A new name cannot consume the same checked raw/CURRENT intake twice.
    exclusive(root.parent/('native110-input-'+input_sha+'-consumed.json'),reservation)
    child.mkdir(mode=0o700);exclusive(child/'reservation.json',reservation)
    runner=stage/'scripts/diagnostics/hotel_match_native110_guarded_v1.php'
    if not safe_file(runner,2*1024*1024): fail('guarded_runner_missing_no_replay')
    env={key:os.environ[key] for key in ('PATH','HOME','LANG','LC_ALL') if key in os.environ}
    env.update({'ANYTOUR_ROOT':str(project),'MATCH_OPERATION_DIR':str(child),'MATCH_SOURCE_SHA':source})
    disabled='curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,socket_create,socket_connect,exec,system,shell_exec,passthru,proc_open,popen'
    run=subprocess.run(['php','-d','display_errors=0','-d','log_errors=0','-d','allow_url_fopen=0',
                        '-d','disable_functions='+disabled,str(runner),'--execute'],cwd=project,env=env,
                       capture_output=True,text=True,timeout=240)
    result_path=child/'result.json';receipt_path=child/'receipt.json'
    if (not safe_file(result_path,262144) or not safe_file(receipt_path,65536) or run.stderr.strip()
            or len(run.stdout.encode())>262144): fail('guarded_terminal_missing_no_replay')
    data=safe_json(result_path,262144);receipt=safe_json(receipt_path,65536)
    digest=hashlib.sha256(result_path.read_bytes()).hexdigest()
    if (json.loads(run.stdout)!=data or any(data.get(k)!=v or receipt.get(k)!=v for k,v in
            {'operation':operation,'source_sha':source,'batch':'native110-20260928','input_sha256':input_sha,
             'provider_http_calls':0,'no_replay':True}.items())
            or receipt.get('state')!=data.get('state') or receipt.get('result_sha256')!=digest): fail('guarded_terminal_binding')
    base={'state','current_candidates_evaluated','rows','held','database_writes','mapping_writes','readback_verified',
          'operation','source_sha','batch','input_sha256','provider_http_calls','no_replay'}
    optional={'reason','commit_attempted','commit_completed','effective_resolver_verified','prior_evidence_preserved',
              'unrelated_identities_unchanged','coverage_before','coverage_after','new_full_triples'}
    if not base.issubset(data) or set(data)-base-optional: fail('guarded_terminal_projection')
    if (any(type(v.get('provider_http_calls')) is not int or v['provider_http_calls']!=0 or v.get('no_replay') is not True for v in (data,receipt))
            or type(data['current_candidates_evaluated']) is not int or not 0<=data['current_candidates_evaluated']<=4): fail('guarded_terminal_counts')
    count=data['mapping_writes']
    if count is not None and (type(count) is not int or not 0<=count<=4): fail('guarded_write_count')
    if (data['database_writes']!=count or receipt.get('database_writes')!=count or receipt.get('mapping_writes')!=count
            or type(data['readback_verified']) is not bool or receipt.get('readback_verified')!=data['readback_verified']): fail('guarded_terminal_count_binding')
    expected={'3126':42903,'9501':420,'475947':28529,'2000034238':16944};seen=set()
    for key in ('rows','held'):
        rows=data[key]
        if not isinstance(rows,list) or len(rows)>4: fail('guarded_row_shape')
        for row in rows:
            fields={'catalog_id','local_hotel_id','status','reasons'} if key=='held' else {'catalog_id','local_hotel_id','name','catalog_sha256','evidence_sha256','prior_evidence_sha256','proof_operator_count'}
            if not isinstance(row,dict) or set(row)!=fields: fail('guarded_row_shape')
            cat=row['catalog_id']
            if cat not in expected or cat in seen or type(row['local_hotel_id']) is not int or row['local_hotel_id']!=expected[cat]: fail('guarded_row_scope')
            seen.add(cat)
            if key=='held':
                if row['status']!='hold' or not isinstance(row['reasons'],list) or len(row['reasons'])>100 or any(not isinstance(r,str) or not re.fullmatch(r'[a-z_][a-z0-9_]{0,99}',r) for r in row['reasons']): fail('guarded_hold_projection')
            else:
                if (not isinstance(row['name'],str) or len(row['name'])>1000 or type(row['proof_operator_count']) is not int
                        or not 1<=row['proof_operator_count']<=4 or any(not isinstance(row[k],str) or not re.fullmatch(r'[a-f0-9]{64}',row[k]) for k in ('catalog_sha256','evidence_sha256','prior_evidence_sha256'))): fail('guarded_written_projection')
    for key in ('commit_attempted','commit_completed','effective_resolver_verified','prior_evidence_preserved','unrelated_identities_unchanged'):
        if key in data and type(data[key]) is not bool: fail('guarded_terminal_bool')
    if 'reason' in data and (not isinstance(data['reason'],str) or not re.fullmatch(r'[a-z_]{1,100}',data['reason'])): fail('guarded_reason')
    for key in ('coverage_before','coverage_after'):
        if key in data:
            c=data[key]
            if not isinstance(c,dict) or set(c)!={'tv_total','full_triple','samo_only','anex_only','neither'} or any(type(v) is not int or not 0<=v<=50000 for v in c.values()): fail('guarded_coverage_projection')
    if 'new_full_triples' in data and (type(data['new_full_triples']) is not int or not -4<=data['new_full_triples']<=4): fail('guarded_coverage_projection')
    successful=data['state'] in ('committed_readback_verified','completed_no_new_writes')
    if successful:
        if (run.returncode!=0 or type(count) is not int or data['readback_verified'] is not True
                or type(data['current_candidates_evaluated']) is not int or data['current_candidates_evaluated']!=4
                or set(expected)!=seen or len(data['rows'])!=count): fail('guarded_success_contract')
        if data['state']=='committed_readback_verified' and (count<1 or any(data.get(k) is not True for k in
                ('commit_completed','commit_attempted','effective_resolver_verified','prior_evidence_preserved','unrelated_identities_unchanged'))): fail('guarded_commit_contract')
        if data['state']=='completed_no_new_writes' and count!=0: fail('guarded_zero_contract')
    elif data['state'] not in ('failed_before_writer','rolled_back_no_writes','commit_outcome_unknown_no_replay',
                             'committed_readback_unconfirmed','write_outcome_unknown_no_replay') or data['readback_verified']:
        fail('guarded_false_verification')
    return {'successful':successful,'exit_code':run.returncode,'result_sha256':digest,'summary':data}

'''

REMOTE_GUARDED_DISPATCH = r'''    if mode=='match-native110-write':
        guarded=run_match_native110_write(stage)
        result['match_native110_write']=guarded
        result['supplier_calls']=0
        result['database_writes']=guarded['summary']['database_writes']
        result['mapping_writes']=guarded['summary']['mapping_writes']
        result['production_after']=fingerprints()
        if result['production_after']!=before: fail('production_drift')
        result['production_unchanged']=True
        result['status']='complete' if guarded['successful'] else 'terminal_nonzero_no_replay'
'''


REMOTE_BG_HANDLER = r'''
def run_match_native110_bg_evidence(stage):
    input_sha='59cfe4bf4001636f77a0e8ad440475c4d6b514599c9147fc984daad5f4f7849e'
    if (operation!='int-andromeda-match-native110-bg-evidence-20261001-v1' or payload.get('batch')!='native110-20260928'
            or payload.get('maximum_writes')!=0 or payload.get('provider_http_calls')!=0
            or payload.get('input_sha256')!=input_sha): fail('bg_fixed_scope')
    root=home/'.anytoour-match/operations'
    if not root.is_dir() or root.is_symlink() or root.resolve()!=root: fail('bg_private_root')
    child=root/operation
    if child.exists() or child.is_symlink(): fail('bg_exists_no_replay')
    child.mkdir(mode=0o700)
    reservation={'operation':operation,'source_sha':source,'batch':'native110-20260928','input_sha256':input_sha,
                 'maximum_writes':0,'provider_http_calls':0,'state':'reserved_before_saved_read','reserved_at':int(time.time())}
    fd=os.open(child/'reservation.json',os.O_WRONLY|os.O_CREAT|os.O_EXCL,0o600)
    with os.fdopen(fd,'wb') as stream:
        stream.write(json.dumps(reservation,sort_keys=True,separators=(',',':')).encode()+b'\n');stream.flush();os.fsync(stream.fileno())
    runner=stage/'scripts/diagnostics/hotel_match_native110_bg_evidence_v1.php'
    if not safe_file(runner,2*1024*1024): fail('bg_runner_missing_no_replay')
    env={key:os.environ[key] for key in ('PATH','HOME','LANG','LC_ALL') if key in os.environ}
    env.update({'MATCH_OPERATION_DIR':str(child),'MATCH_SOURCE_SHA':source})
    disabled='curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,socket_create,socket_connect,exec,system,shell_exec,passthru,proc_open,popen'
    run=subprocess.run(['php','-d','display_errors=0','-d','log_errors=0','-d','allow_url_fopen=0','-d','disable_functions='+disabled,
                        str(runner),'--read-saved'],cwd=project,env=env,capture_output=True,text=True,timeout=240)
    path=child/'result.json'
    if run.returncode!=0 or run.stderr.strip() or len(run.stdout.encode())>262144 or not safe_file(path,262144): fail('bg_read_failed_no_replay')
    data=json.loads(run.stdout)
    fields={'state','operation','source_sha','batch','input_sha256','rows','raw_files_read','raw_bytes_read','provider_http_calls',
            'database_reads','database_writes','mapping_writes','safe_to_write_now','no_replay'}
    if (not isinstance(data,dict) or set(data)!=fields or safe_json(path,262144)!=data
            or data['state']!='completed_bg_original_fields_review' or data['operation']!=operation or data['source_sha']!=source
            or data['batch']!='native110-20260928' or data['input_sha256']!=input_sha
            or data['safe_to_write_now'] is not False or data['no_replay'] is not True): fail('bg_output_binding')
    for key,cap in [('provider_http_calls',0),('database_reads',0),('database_writes',0),('mapping_writes',0),('raw_files_read',1000),('raw_bytes_read',536870912)]:
        if type(data[key]) is not int or not 0<=data[key]<=cap: fail('bg_output_counts')
    expected=__BG_EXPECTED__
    rows=data['rows']
    if not isinstance(rows,list) or len(rows)!=18: fail('bg_output_rows')
    seen=set()
    def shape(value,fields):
        if not isinstance(value,dict) or set(value)!=set(fields.split()): fail('bg_projection')
    def items(value,cap):
        if not isinstance(value,list) or len(value)>cap: fail('bg_projection')
        return value
    def source_field(value):
        if not isinstance(value,str) or not re.fullmatch(r'(?:row|original)\.[a-zA-Z_][a-zA-Z0-9_.-]{0,79}',value): fail('bg_field')
    def sha(value):
        if not isinstance(value,str) or not re.fullmatch(r'[a-f0-9]{64}',value): fail('bg_digest')
    for row in rows:
        shape(row,'catalog_id tv_hotel_id samo_native_id tv_native_id raw_references_examined top_fields original_fields location_fields bg_links failures safe_to_write_now')
        cat=row['catalog_id']
        if (cat not in expected or cat in seen or type(row['tv_hotel_id']) is not int
                or tuple(row[k] for k in ('tv_hotel_id','samo_native_id','tv_native_id'))!=expected[cat]
                or row['safe_to_write_now'] is not False or type(row['raw_references_examined']) is not int
                or not 0<=row['raw_references_examined']<=1000): fail('bg_row_scope')
        seen.add(cat)
        for key in ('top_fields','original_fields'):
            for value in items(row[key],256):
                if not isinstance(value,str) or not re.fullmatch(r'[a-zA-Z_][a-zA-Z0-9_.-]{0,79}',value): fail('bg_field_inventory')
        for failure in items(row['failures'],10):
            if failure not in ('raw_file_unavailable','raw_reference_changed','raw_evidence_reference_missing'): fail('bg_failure')
        for geo in items(row['location_fields'],128):
            shape(geo,'source_field value');source_field(geo['source_field']);value=geo['value']
            if not re.fullmatch(r'(?:town|city|state|country|latitude|longitude|lat|lng|lon|townkey|townname|hotelLat|hotelLng|hotelLatitude|hotelLongitude|hotelTown|hotelCountry)',geo['source_field'].split('.',1)[1],re.I): fail('bg_location_field')
            if value is not None and (type(value) not in (str,int,float) or (type(value) is str and not re.fullmatch(r"[\w\s.,+'’()/_-]{1,180}",value)) or (type(value) in (int,float) and not (value==value and abs(value)<=10**15))): fail('bg_location_projection')
        for link in items(row['bg_links'],128):
            shape(link,'source_field host url_sha256 signed_parameters_present hotel_selectors');source_field(link['source_field']);sha(link['url_sha256'])
            if not re.fullmatch(r'(?:(?:hotel|object).*(?:url|link)|url|link)',link['source_field'].split('.',1)[1],re.I): fail('bg_link_field')
            if (not isinstance(link['host'],str) or not re.fullmatch(r'(?:[a-z0-9-]+\.)*bgoperator\.ru',link['host'])
                    or type(link['signed_parameters_present']) is not bool): fail('bg_link_projection')
            for selector in items(link['hotel_selectors'],32):
                shape(selector,'parameter positive_tokens opaque_tokens value_sha256');sha(selector['value_sha256'])
                if (not isinstance(selector['parameter'],str) or not re.fullmatch(r'(?:id|tid|hotel|hotelid|hotel_id|hotels|hotels\[\]|hotellist|i1hotelinc)',selector['parameter'],re.I)
                        or type(selector['opaque_tokens']) is not int or not 0<=selector['opaque_tokens']<=128): fail('bg_selector_projection')
                for token in items(selector['positive_tokens'],128):
                    if not isinstance(token,str) or not re.fullmatch(r'[1-9][0-9]{0,31}',token): fail('bg_selector_projection')
    return data

'''

REMOTE_BG_DISPATCH = r'''    if mode=='match-native110-bg-evidence':
        result['match_native110_bg_evidence']=run_match_native110_bg_evidence(stage)
        result['supplier_calls']=0
        result['database_reads']=0
        result['database_writes']=0
        result['mapping_writes']=0
        result['production_after']=fingerprints()
        if result['production_after']!=before: fail('production_drift')
        result['production_unchanged']=True
        result['status']='complete'
'''
REMOTE_BG_HANDLER = REMOTE_BG_HANDLER.replace('__BG_EXPECTED__',repr(BG_EXPECTED))


REMOTE_SHAMS_GEO_HANDLER = r'''
def validate_match_shams_geo(data,path,expected_source):
    input_sha='59cfe4bf4001636f77a0e8ad440475c4d6b514599c9147fc984daad5f4f7849e'
    fields={'schema','state','operation','batch','source_sha','input_sha256','no_replay','catalog_id','tv_hotel_id',
            'snapshot_captured_at_utc','saved_target_geography','source_history_geography_exported','raw_files_read','raw_bytes_read',
            'references_examined','references','database_reads','provider_http_calls','database_writes','mapping_writes','safe_to_write_now'}
    if (not isinstance(data,dict) or set(data)!=fields or safe_json(path,524288)!=data
            or data['schema']!='match-shams-saved-geography/1' or data['state']!='completed_saved_geography_evidence'
            or data['operation']!='int-andromeda-match-shams-geo-evidence-20261001-v1' or data['source_sha']!=expected_source
            or data['batch']!='native110-20260928' or data['input_sha256']!=input_sha
            or data['catalog_id']!='9501' or data['tv_hotel_id']!=420
            or data['source_history_geography_exported'] is not False or data['safe_to_write_now'] is not False
            or data['no_replay'] is not True): fail('shams_geo_output_binding')
    for key,cap in [('provider_http_calls',0),('database_reads',0),('database_writes',0),('mapping_writes',0),
                    ('raw_files_read',8),('raw_bytes_read',67108864),('references_examined',128)]:
        if type(data[key]) is not int or not 0<=data[key]<=cap: fail('shams_geo_output_counts')
    if (not isinstance(data['snapshot_captured_at_utc'],str)
            or not re.fullmatch(r'20[0-9]{2}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}(?:\.[0-9]{1,6})?(?:Z|\+00:00)',data['snapshot_captured_at_utc'])): fail('shams_geo_snapshot')
    def shape(value,fields):
        if not isinstance(value,dict) or set(value)!=set(fields.split()): fail('shams_geo_projection')
    def items(value,cap):
        if not isinstance(value,list) or len(value)>cap: fail('shams_geo_projection')
        return value
    def field_name(value):
        if not isinstance(value,str) or not re.fullmatch(r'[a-zA-Z_][a-zA-Z0-9_.-]{0,79}',value): fail('shams_geo_field')
    def geo_value(value):
        if value is not None and (type(value) not in (str,int,float)
                or (type(value) is str and not re.fullmatch(r"[\w\s.,+'’()/_-]{1,180}",value))
                or (type(value) in (int,float) and not (value==value and abs(value)<=10**15))): fail('shams_geo_value')
    target_fields={'country_name','region_name','subregion_name','latitude','longitude'}
    for geo in items(data['saved_target_geography'],64):
        shape(geo,'source_field value')
        if not isinstance(geo['source_field'],str) or not geo['source_field'].startswith('saved_target.') or geo['source_field'].split('.',1)[1] not in target_fields: fail('shams_target_geo_field')
        geo_value(geo['value'])
    refs=items(data['references'],128)
    if data['references_examined']!=len(refs) or not refs: fail('shams_geo_reference_count')
    seen=set()
    allowed={('operator_5','835'),('operator_342','24402')}
    geo_fields={'town','city','state','country','country_name','region_name','subregion_name','latitude','longitude','lat','lng','lon',
                'townKey','townName','stateName','countryName','cityName','hotelLat','hotelLng','hotelLatitude','hotelLongitude','hotelTown','hotelCountry'}
    for ref in refs:
        shape(ref,'namespace native_id page_sha256 json_pointer field_names original_field_names location_fields raw_verified failures')
        pair=(ref['namespace'],ref['native_id'])
        if (pair not in allowed or not isinstance(ref['page_sha256'],str) or not re.fullmatch(r'[a-f0-9]{64}',ref['page_sha256'])
                or not isinstance(ref['json_pointer'],str) or not re.fullmatch(r'/(?:PRICES|prices)/[0-9]{1,8}',ref['json_pointer'])
                or type(ref['raw_verified']) is not bool): fail('shams_geo_reference_scope')
        seen.add(pair)
        for key in ('field_names','original_field_names'):
            for value in items(ref[key],256): field_name(value)
        for failure in items(ref['failures'],1):
            if failure!='saved_geo_reference_unverified': fail('shams_geo_failure')
        if ref['raw_verified']!=(not ref['failures']): fail('shams_geo_verification_state')
        for geo in items(ref['location_fields'],64):
            shape(geo,'source_field value')
            if (not isinstance(geo['source_field'],str) or not re.fullmatch(r'(?:row|original)\.[a-zA-Z_][a-zA-Z0-9_.-]{0,79}',geo['source_field'])
                    or geo['source_field'].split('.',1)[1] not in geo_fields): fail('shams_source_geo_field')
            geo_value(geo['value'])
    if seen!=allowed: fail('shams_geo_native_coverage')
    return data

def run_match_shams_geo_evidence(stage):
    input_sha='59cfe4bf4001636f77a0e8ad440475c4d6b514599c9147fc984daad5f4f7849e'
    if (operation!='int-andromeda-match-shams-geo-evidence-20261001-v1' or payload.get('batch')!='native110-20260928'
            or payload.get('maximum_writes')!=0 or payload.get('provider_http_calls')!=0
            or payload.get('input_sha256')!=input_sha): fail('shams_geo_fixed_scope')
    root=home/'.anytoour-match/operations'
    if not root.is_dir() or root.is_symlink() or root.resolve()!=root: fail('shams_geo_private_root')
    child=root/operation
    if child.exists() or child.is_symlink(): fail('shams_geo_exists_no_replay')
    child.mkdir(mode=0o700)
    reservation={'operation':operation,'source_sha':source,'batch':'native110-20260928','input_sha256':input_sha,
                 'maximum_writes':0,'provider_http_calls':0,'state':'reserved_before_saved_read','reserved_at':int(time.time())}
    fd=os.open(child/'reservation.json',os.O_WRONLY|os.O_CREAT|os.O_EXCL,0o600)
    with os.fdopen(fd,'wb') as stream:
        stream.write(json.dumps(reservation,sort_keys=True,separators=(',',':')).encode()+b'\n');stream.flush();os.fsync(stream.fileno())
    runner=stage/'scripts/diagnostics/hotel_match_shams_geography_saved_v1.php'
    if not safe_file(runner,2*1024*1024): fail('shams_geo_runner_missing_no_replay')
    env={key:os.environ[key] for key in ('PATH','HOME','LANG','LC_ALL') if key in os.environ}
    env.update({'MATCH_OPERATION_DIR':str(child),'MATCH_SOURCE_SHA':source})
    disabled='curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,socket_create,socket_connect,exec,system,shell_exec,passthru,proc_open,popen'
    run=subprocess.run(['php','-d','display_errors=0','-d','log_errors=0','-d','allow_url_fopen=0','-d','disable_functions='+disabled,
                        str(runner),'--read-saved'],cwd=project,env=env,capture_output=True,text=True,timeout=240)
    path=child/'result.json'
    if run.returncode!=0 or run.stderr.strip() or len(run.stdout.encode())>524288 or not safe_file(path,524288): fail('shams_geo_read_failed_no_replay')
    data=json.loads(run.stdout)
    return validate_match_shams_geo(data,path,source)

'''

REMOTE_SHAMS_GEO_DISPATCH = r'''    if mode=='match-shams-geo-evidence':
        result['match_shams_geo_evidence']=run_match_shams_geo_evidence(stage)
        result['supplier_calls']=0
        result['database_reads']=0
        result['database_writes']=0
        result['mapping_writes']=0
        result['production_after']=fingerprints()
        if result['production_after']!=before: fail('production_drift')
        result['production_unchanged']=True
        result['status']='complete'
'''

REMOTE_SHAMS_GEO_READBACK_HANDLER = REMOTE_SHAMS_GEO_HANDLER + r'''
def run_match_shams_geo_readback(stage):
    input_sha='59cfe4bf4001636f77a0e8ad440475c4d6b514599c9147fc984daad5f4f7849e'
    evidence_operation='int-andromeda-match-shams-geo-evidence-20261001-v1'
    evidence_source='12dc06dbdfd047c05caa346092cb9bd1c1dd0323'
    if (operation!='int-andromeda-match-shams-geo-readback-20261001-v1' or payload.get('batch')!='native110-20260928'
            or payload.get('maximum_writes')!=0 or payload.get('provider_http_calls')!=0
            or payload.get('input_sha256')!=input_sha): fail('shams_geo_readback_fixed_scope')
    root=home/'.anytoour-match/operations'
    if not root.is_dir() or root.is_symlink() or root.resolve()!=root: fail('shams_geo_readback_private_root')
    child=root/operation
    if child.exists() or child.is_symlink(): fail('shams_geo_readback_exists_no_replay')
    evidence=root/evidence_operation
    evidence_path=evidence/'result.json'
    if (not evidence.is_dir() or evidence.is_symlink() or evidence.resolve()!=evidence
            or not safe_file(evidence/'reservation.json',1048576) or not safe_file(evidence_path,524288)): fail('shams_geo_terminal_missing')
    evidence_reservation=safe_json(evidence/'reservation.json',1048576)
    if (evidence_reservation.get('operation')!=evidence_operation or evidence_reservation.get('source_sha')!=evidence_source
            or evidence_reservation.get('batch')!='native110-20260928' or evidence_reservation.get('input_sha256')!=input_sha
            or evidence_reservation.get('maximum_writes')!=0 or evidence_reservation.get('provider_http_calls')!=0): fail('shams_geo_terminal_binding')
    child.mkdir(mode=0o700)
    reservation={'operation':operation,'source_sha':source,'batch':'native110-20260928','input_sha256':input_sha,
                 'evidence_operation':evidence_operation,'evidence_source_sha':evidence_source,
                 'maximum_writes':0,'provider_http_calls':0,'state':'reserved_before_terminal_readback','reserved_at':int(time.time())}
    fd=os.open(child/'reservation.json',os.O_WRONLY|os.O_CREAT|os.O_EXCL,0o600)
    with os.fdopen(fd,'wb') as stream:
        stream.write(json.dumps(reservation,sort_keys=True,separators=(',',':')).encode()+b'\n');stream.flush();os.fsync(stream.fileno())
    data=validate_match_shams_geo(safe_json(evidence_path,524288),evidence_path,evidence_source)
    output={'state':'completed_saved_geography_readback','operation':operation,'source_sha':source,'batch':'native110-20260928',
            'input_sha256':input_sha,'evidence_operation':evidence_operation,'evidence_source_sha':evidence_source,
            'provider_http_calls':0,'database_reads':0,'database_writes':0,'mapping_writes':0,'safe_to_write_now':False,
            'no_replay':True,'evidence':data}
    fd=os.open(child/'result.json',os.O_WRONLY|os.O_CREAT|os.O_EXCL,0o600)
    with os.fdopen(fd,'wb') as stream:
        stream.write(json.dumps(output,sort_keys=True,separators=(',',':'),ensure_ascii=False).encode()+b'\n');stream.flush();os.fsync(stream.fileno())
    return output

'''

REMOTE_SHAMS_GEO_READBACK_DISPATCH = r'''    if mode=='match-shams-geo-readback':
        result['match_shams_geo_readback']=run_match_shams_geo_readback(stage)
        result['supplier_calls']=0
        result['database_reads']=0
        result['database_writes']=0
        result['mapping_writes']=0
        result['production_after']=fingerprints()
        if result['production_after']!=before: fail('production_drift')
        result['production_unchanged']=True
        result['status']='complete'
'''

REMOTE_SHAMS_WRITE_HANDLER = r'''
def run_match_shams_write(stage):
    input_sha='59cfe4bf4001636f77a0e8ad440475c4d6b514599c9147fc984daad5f4f7849e'
    geo_operation='int-andromeda-match-shams-geo-readback-20261001-v1'
    if (operation!='int-andromeda-match-shams-current-write-20261001-v1' or payload.get('batch')!='shams9501-geo-20261001'
            or payload.get('maximum_writes')!=1 or payload.get('provider_http_calls')!=0
            or payload.get('input_sha256')!=input_sha or payload.get('geography_operation')!=geo_operation): fail('shams_write_fixed_scope')
    root=home/'.anytoour-match/operations'
    if not root.is_dir() or root.is_symlink() or root.resolve()!=root: fail('shams_write_private_root')
    geo=root/geo_operation
    if (not geo.is_dir() or geo.is_symlink() or geo.resolve()!=geo or not safe_file(geo/'result.json',1048576)): fail('shams_write_geo_receipt_missing')
    geo_data=safe_json(geo/'result.json',1048576)
    if (geo_data.get('state')!='completed_saved_geography_readback' or geo_data.get('operation')!=geo_operation
            or geo_data.get('source_sha')!='12dc06dbdfd047c05caa346092cb9bd1c1dd0323' or geo_data.get('input_sha256')!=input_sha
            or geo_data.get('no_replay') is not True or geo_data.get('mapping_writes')!=0 or geo_data.get('provider_http_calls')!=0): fail('shams_write_geo_receipt_binding')
    child=root/operation
    if child.exists() or child.is_symlink(): fail('shams_write_exists_no_replay')
    reservation={'operation':operation,'source_sha':source,'batch':'shams9501-geo-20261001','input_sha256':input_sha,
                 'geography_operation':geo_operation,'maximum_writes':1,'provider_http_calls':0,'state':'reserved_before_db','reserved_at':int(time.time())}
    def exclusive(path,value):
        fd=os.open(path,os.O_WRONLY|os.O_CREAT|os.O_EXCL,0o600)
        with os.fdopen(fd,'wb') as stream:
            stream.write(json.dumps(value,sort_keys=True,separators=(',',':')).encode()+b'\n');stream.flush();os.fsync(stream.fileno())
    exclusive(root.parent/'shams9501-geo-20261001-consumed.json',reservation)
    child.mkdir(mode=0o700);exclusive(child/'reservation.json',reservation)
    runner=stage/'scripts/diagnostics/hotel_match_shams_guarded_v1.php'
    if not safe_file(runner,2*1024*1024): fail('shams_write_runner_missing_no_replay')
    env={key:os.environ[key] for key in ('PATH','HOME','LANG','LC_ALL') if key in os.environ}
    env.update({'ANYTOUR_ROOT':str(project),'MATCH_OPERATION_DIR':str(child),'MATCH_SOURCE_SHA':source})
    disabled='curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,socket_create,socket_connect,exec,system,shell_exec,passthru,proc_open,popen'
    run=subprocess.run(['php','-d','display_errors=0','-d','log_errors=0','-d','allow_url_fopen=0','-d','disable_functions='+disabled,
                        str(runner),'--execute'],cwd=project,env=env,capture_output=True,text=True,timeout=240)
    result_path=child/'result.json';receipt_path=child/'receipt.json'
    if not safe_file(result_path,262144) or not safe_file(receipt_path,65536) or run.stderr.strip() or len(run.stdout.encode())>262144: fail('shams_write_terminal_missing_no_replay')
    data=safe_json(result_path,262144);receipt=safe_json(receipt_path,65536);digest=hashlib.sha256(result_path.read_bytes()).hexdigest()
    fixed={'operation':operation,'source_sha':source,'batch':'shams9501-geo-20261001','input_sha256':input_sha,
           'geography_operation':geo_operation,'provider_http_calls':0,'no_replay':True}
    if (json.loads(run.stdout)!=data or any(data.get(k)!=v or receipt.get(k)!=v for k,v in fixed.items())
            or receipt.get('state')!=data.get('state') or receipt.get('result_sha256')!=digest): fail('shams_write_terminal_binding')
    if (data.get('no_replay') is not True or receipt.get('no_replay') is not True
            or type(data.get('provider_http_calls')) is not int or data['provider_http_calls']!=0
            or type(receipt.get('provider_http_calls')) is not int or receipt['provider_http_calls']!=0): fail('shams_write_terminal_binding')
    base={'state','current_candidates_evaluated','rows','held','database_writes','mapping_writes','readback_verified'}|set(fixed)
    optional={'reason','commit_attempted','commit_completed','effective_resolver_verified','prior_evidence_preserved','unrelated_identities_unchanged','coverage_before','coverage_after','new_full_triples'}
    if not base.issubset(data) or set(data)-base-optional: fail('shams_write_terminal_projection')
    if type(data['current_candidates_evaluated']) is not int or not 0<=data['current_candidates_evaluated']<=1: fail('shams_write_count')
    count=data['mapping_writes']
    if count is not None and (type(count) is not int or not 0<=count<=1): fail('shams_write_count')
    if data['database_writes']!=count or receipt.get('database_writes')!=count or receipt.get('mapping_writes')!=count or type(data['readback_verified']) is not bool or receipt.get('readback_verified')!=data['readback_verified']: fail('shams_write_count_binding')
    seen=set()
    for key in ('rows','held'):
        rows=data[key]
        if not isinstance(rows,list) or len(rows)>1: fail('shams_write_row_shape')
        for row in rows:
            fields={'catalog_id','local_hotel_id','status','reasons'} if key=='held' else {'catalog_id','local_hotel_id','name','catalog_sha256','evidence_sha256','prior_evidence_sha256','proof_operator_count'}
            if not isinstance(row,dict) or set(row)!=fields or row.get('catalog_id')!='9501' or row.get('local_hotel_id')!=420 or '9501' in seen: fail('shams_write_row_scope')
            seen.add('9501')
            if key=='held' and (row['status']!='hold' or not isinstance(row['reasons'],list) or not row['reasons'] or any(not isinstance(r,str) or not re.fullmatch(r'[a-z_][a-z0-9_]{0,99}',r) for r in row['reasons'])): fail('shams_write_hold_projection')
            if key=='rows' and (not isinstance(row['name'],str) or type(row['proof_operator_count']) is not int or not 1<=row['proof_operator_count']<=2 or any(not isinstance(row[k],str) or not re.fullmatch(r'[a-f0-9]{64}',row[k]) for k in ('catalog_sha256','evidence_sha256','prior_evidence_sha256'))): fail('shams_write_row_projection')
    successful=data['state'] in ('committed_readback_verified','completed_no_new_writes')
    if successful:
        if run.returncode!=0 or data['current_candidates_evaluated']!=1 or seen!={'9501'} or type(count) is not int or data['readback_verified'] is not True or len(data['rows'])!=count: fail('shams_write_success_contract')
        if data['state']=='committed_readback_verified' and (count!=1 or any(data.get(k) is not True for k in ('commit_attempted','commit_completed','effective_resolver_verified','prior_evidence_preserved','unrelated_identities_unchanged'))): fail('shams_write_commit_contract')
        if data['state']=='completed_no_new_writes' and count!=0: fail('shams_write_zero_contract')
    elif data['state'] not in ('failed_before_writer','rolled_back_no_writes','commit_outcome_unknown_no_replay','committed_readback_unconfirmed','write_outcome_unknown_no_replay') or data['readback_verified']:
        fail('shams_write_false_verification')
    return {'successful':successful,'exit_code':run.returncode,'result_sha256':digest,'summary':data}
'''

REMOTE_SHAMS_WRITE_DISPATCH = r'''    if mode=='match-shams-current-write':
        shams_write=run_match_shams_write(stage)
        result['match_shams_write']=shams_write
        result['supplier_calls']=0
        result['database_writes']=shams_write['summary']['database_writes']
        result['mapping_writes']=shams_write['summary']['mapping_writes']
        result['production_after']=fingerprints()
        if result['production_after']!=before: fail('production_drift')
        result['production_unchanged']=True
        result['status']='complete' if shams_write['successful'] else 'terminal_nonzero_no_replay'
'''


REMOTE_TARGET_HANDLER = r'''
def run_match_tv_live30_target_catalog(stage):
    if (operation!='int-andromeda-match-live30-target-catalog-20261001-v1'
            or payload.get('batch')!='tv-live30-targets-20261001'
            or payload.get('maximum_writes')!=0 or payload.get('provider_http_calls')!=0): fail('target_catalog_fixed_scope')
    root=home/'.anytoour-match/operations'
    if not root.is_dir() or root.is_symlink() or root.resolve()!=root: fail('target_catalog_private_root')
    child=root/operation
    if child.exists() or child.is_symlink(): fail('target_catalog_exists_no_replay')
    child.mkdir(mode=0o700)
    reservation={'operation':operation,'source_sha':source,'batch':'tv-live30-targets-20261001',
                 'maximum_writes':0,'provider_http_calls':0,'state':'reserved_before_db_read','reserved_at':int(time.time())}
    fd=os.open(child/'reservation.json',os.O_WRONLY|os.O_CREAT|os.O_EXCL,0o600)
    with os.fdopen(fd,'wb') as stream:
        stream.write(json.dumps(reservation,sort_keys=True,separators=(',',':')).encode()+b'\n');stream.flush();os.fsync(stream.fileno())
    runner=stage/'scripts/diagnostics/hotel_match_tv_live30_target_catalog_v1.php'
    if not safe_file(runner,2*1024*1024): fail('target_catalog_runner_missing_no_replay')
    env={key:os.environ[key] for key in ('PATH','HOME','LANG','LC_ALL') if key in os.environ}
    env.update({'ANYTOUR_ROOT':str(project),'MATCH_OPERATION_DIR':str(child),'MATCH_SOURCE_SHA':source})
    disabled='curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,socket_create,socket_connect,exec,system,shell_exec,passthru,proc_open,popen'
    run=subprocess.run(['php','-d','display_errors=0','-d','log_errors=0','-d','allow_url_fopen=0','-d','disable_functions='+disabled,
                        str(runner),'--current-targets'],cwd=project,env=env,capture_output=True,text=True,timeout=240)
    if run.returncode!=0 or run.stderr.strip() or len(run.stdout.encode())>8388608: fail('target_catalog_read_failed_no_replay')
    result_path=child/'result.json';receipt_path=child/'receipt.json'
    if not safe_file(result_path,8388608) or not safe_file(receipt_path,65536): fail('target_catalog_terminal_missing_no_replay')
    data=safe_json(result_path,8388608);receipt=safe_json(receipt_path,65536)
    digest=hashlib.sha256(result_path.read_bytes()).hexdigest()
    if json.loads(run.stdout)!=data: fail('target_catalog_stdout_binding')
    validate_match_tv_live30_target_catalog(data,receipt,digest,operation,source)
    return {'result_sha256':digest,'summary':data}

def validate_match_tv_live30_target_catalog(data,receipt,digest,expected_operation,expected_source):
    fixed={'state':'completed_tv_live30_target_catalog','operation':expected_operation,'source_sha':expected_source,'batch':'tv-live30-targets-20261001',
           'provider_http_calls':0,'database_writes':0,'mapping_writes':0,'safe_to_write_now':False,'no_replay':True}
    fields=set(fixed)|{'captured_at_utc','row_count','rows'}
    if (set(data)!=fields or set(receipt)!=(fields-{'rows'})|{'result_sha256'}
            or any(data.get(k)!=v or receipt.get(k)!=v for k,v in fixed.items())
            or receipt.get('result_sha256')!=digest): fail('target_catalog_terminal_binding')
    for value in (data,receipt):
        if (value.get('safe_to_write_now') is not False or value.get('no_replay') is not True
                or any(type(value[k]) is not int or value[k]!=0 for k in ('provider_http_calls','database_writes','mapping_writes'))): fail('target_catalog_zero_authority')
    if (not isinstance(data['captured_at_utc'],str) or not re.fullmatch(r'[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}Z',data['captured_at_utc'])
            or receipt['captured_at_utc']!=data['captured_at_utc'] or type(data['row_count']) is not int
            or not 0<=data['row_count']<=20000 or type(receipt['row_count']) is not int or receipt['row_count']!=data['row_count']
            or not isinstance(data['rows'],list) or len(data['rows'])!=data['row_count']): fail('target_catalog_count_binding')
    seen=set();row_fields={'id','name','country_id','country_name','region_name','subregion_name','category','is_active',
                          'latitude','longitude','accepted_samo_ids','manual_hold','exclusion_hold'}
    for row in data['rows']:
        if (not isinstance(row,dict) or set(row)!=row_fields or type(row['id']) is not int or row['id']<=0
                or row['id'] in seen or row['is_active'] is not True): fail('target_catalog_row_identity')
        seen.add(row['id'])
        for key in ('name','country_id','country_name','region_name','subregion_name','category'):
            v=row[key]
            if v is not None and (not isinstance(v,str) or len(v.encode())>512 or re.search(r'[\x00-\x1f\x7f]|https?://',v,re.I)): fail('target_catalog_row_text')
        if not row['name']: fail('target_catalog_row_name')
        for key,bound in (('latitude',90),('longitude',180)):
            v=row[key]
            if v is not None and (type(v) not in (int,float) or not -bound<=v<=bound): fail('target_catalog_row_coordinates')
        for key in ('manual_hold','exclusion_hold'):
            if type(row[key]) is not bool: fail('target_catalog_row_bool')
        occupants=row['accepted_samo_ids']
        if (not isinstance(occupants,list) or len(occupants)>32
                or any(not isinstance(v,str) or not re.fullmatch(r'[1-9][0-9]{0,31}',v) for v in occupants)
                or occupants!=sorted(set(occupants))): fail('target_catalog_row_occupants')

'''

REMOTE_TARGET_DISPATCH = r'''    if mode=='match-tv-live30-target-catalog':
        result['match_tv_live30_target_catalog']=run_match_tv_live30_target_catalog(stage)
        result['supplier_calls']=0
        result['database_writes']=0
        result['mapping_writes']=0
        result['production_after']=fingerprints()
        if result['production_after']!=before: fail('production_drift')
        result['production_unchanged']=True
        result['status']='complete'
'''


REMOTE_TARGET_PREFLIGHT_HANDLER = r'''
def run_match_tv_live30_target_preflight(stage):
    if (operation!='int-andromeda-match-live30-target-preflight-20261001-v1'
            or payload.get('batch')!='tv-live30-target-preflight-20261001'
            or payload.get('maximum_writes')!=0 or payload.get('provider_http_calls')!=0): fail('target_preflight_fixed_scope')
    root=home/'.anytoour-match/operations'
    if not root.is_dir() or root.is_symlink() or root.resolve()!=root: fail('target_preflight_private_root')
    child=root/operation
    if child.exists() or child.is_symlink(): fail('target_preflight_exists_no_replay')
    child.mkdir(mode=0o700)
    reservation={'operation':operation,'source_sha':source,'batch':'tv-live30-target-preflight-20261001',
                 'maximum_writes':0,'provider_http_calls':0,'state':'reserved_before_db_read','reserved_at':int(time.time())}
    fd=os.open(child/'reservation.json',os.O_WRONLY|os.O_CREAT|os.O_EXCL,0o600)
    with os.fdopen(fd,'wb') as stream:
        stream.write(json.dumps(reservation,sort_keys=True,separators=(',',':')).encode()+b'\n');stream.flush();os.fsync(stream.fileno())
    runner=stage/'scripts/diagnostics/hotel_match_tv_live30_target_preflight_v1.php'
    if not safe_file(runner,2*1024*1024): fail('target_preflight_runner_missing_no_replay')
    env={key:os.environ[key] for key in ('PATH','HOME','LANG','LC_ALL') if key in os.environ}
    env.update({'ANYTOUR_ROOT':str(project),'MATCH_OPERATION_DIR':str(child),'MATCH_SOURCE_SHA':source})
    disabled='curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,socket_create,socket_connect,exec,system,shell_exec,passthru,proc_open,popen'
    run=subprocess.run(['php','-d','display_errors=0','-d','log_errors=0','-d','allow_url_fopen=0','-d','disable_functions='+disabled,
                        str(runner),'--current-target-preflight'],cwd=project,env=env,capture_output=True,text=True,timeout=240)
    if run.returncode!=0 or run.stderr.strip() or len(run.stdout.encode())>262144: fail('target_preflight_read_failed_no_replay')
    result_path=child/'result.json';receipt_path=child/'receipt.json'
    if not safe_file(result_path,262144) or not safe_file(receipt_path,65536): fail('target_preflight_terminal_missing_no_replay')
    data=safe_json(result_path,262144);receipt=safe_json(receipt_path,65536)
    digest=hashlib.sha256(result_path.read_bytes()).hexdigest()
    if json.loads(run.stdout)!=data: fail('target_preflight_stdout_binding')
    validate_match_tv_live30_target_preflight(data,receipt,digest,operation,source)
    return {'result_sha256':digest,'summary':data}

def validate_match_tv_live30_target_preflight(data,receipt,digest,expected_operation,expected_source):
    fixed={'state':'completed_tv_live30_target_preflight','operation':expected_operation,'source_sha':expected_source,
           'batch':'tv-live30-target-preflight-20261001','provider_http_calls':0,'database_reads':1,
           'database_writes':0,'mapping_writes':0,'safe_to_write_now':False,'no_replay':True}
    details={'missing_columns','query_status','metrics'}
    fields=set(fixed)|{'captured_at_utc','schema_complete'}|details
    if (not isinstance(data,dict) or not isinstance(receipt,dict) or set(data)!=fields
            or set(receipt)!=(fields-details)|{'result_sha256'}
            or any(data.get(k)!=v or receipt.get(k)!=v for k,v in fixed.items())
            or receipt.get('result_sha256')!=digest): fail('target_preflight_terminal_binding')
    if (data.get('safe_to_write_now') is not False or data.get('no_replay') is not True
            or type(data.get('database_reads')) is not int or data['database_reads']!=1
            or any(type(data[k]) is not int or data[k]!=0 for k in ('provider_http_calls','database_writes','mapping_writes'))): fail('target_preflight_zero_authority')
    if (not isinstance(data['captured_at_utc'],str) or not re.fullmatch(r'[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}Z',data['captured_at_utc'])
            or receipt.get('captured_at_utc')!=data['captured_at_utc'] or type(data['schema_complete']) is not bool
            or receipt.get('schema_complete') is not data['schema_complete']): fail('target_preflight_status_binding')
    expected_columns={
        'catalog_hotels':{'id','name','country_id','country_name','region_name','subregion_name','category','is_active','latitude','longitude'},
        'tour_operator_identity_observations':{'hotel_id','last_seen_at'},
        'andromeda_hotel_identities':{'supplier_namespace','external_hotel_id','local_hotel_id','decision_status'},
        'anex_hotel_decisions':{'catalog_hotel_id'},
        'anex_review_pair_exclusions':{'catalog_hotel_id'},
    }
    missing=data['missing_columns']
    if not isinstance(missing,dict) or set(missing)!=set(expected_columns): fail('target_preflight_missing_shape')
    for table,columns in expected_columns.items():
        values=missing[table]
        if (not isinstance(values,list) or values!=list(dict.fromkeys(values))
                or any(not isinstance(value,str) or value not in columns for value in values)): fail('target_preflight_missing_shape')
    if data['schema_complete'] is (any(missing.values())): fail('target_preflight_schema_binding')
    metric_names={'cohort','invalid_coordinates','invalid_text','invalid_accepted_native','max_accepted_aliases','manual_targets','excluded_targets'}
    status=data['query_status'];metrics=data['metrics']
    if not isinstance(status,dict) or not isinstance(metrics,dict) or set(status)!=metric_names or set(metrics)!=metric_names: fail('target_preflight_metric_shape')
    for name in metric_names:
        if type(status[name]) is not bool: fail('target_preflight_metric_shape')
        value=metrics[name]
        if status[name]:
            if type(value) is not int or value<0: fail('target_preflight_metric_binding')
        elif value is not None: fail('target_preflight_metric_binding')
    if not data['schema_complete'] and any(status.values()): fail('target_preflight_schema_query_binding')

'''

REMOTE_TARGET_PREFLIGHT_DISPATCH = r'''    if mode=='match-tv-live30-target-preflight':
        result['match_tv_live30_target_preflight']=run_match_tv_live30_target_preflight(stage)
        result['supplier_calls']=0
        result['database_reads']=1
        result['database_writes']=0
        result['mapping_writes']=0
        result['production_after']=fingerprints()
        if result['production_after']!=before: fail('production_drift')
        result['production_unchanged']=True
        result['status']='complete'
'''


REMOTE_TARGET_READBACK_HANDLER = REMOTE_TARGET_HANDLER + r'''
def run_match_tv_live30_target_readback(stage):
    original='int-andromeda-match-live30-target-catalog-20261001-v1'
    original_source='6b49c5ac61ca21e7bb413d30d6badd9f29518cb4'
    if (operation!='int-andromeda-match-live30-target-readback-20261001-v1'
            or payload.get('batch')!='tv-live30-targets-20261001'
            or payload.get('maximum_writes')!=0 or payload.get('provider_http_calls')!=0): fail('target_readback_fixed_scope')
    root=home/'.anytoour-match/operations';evidence=root/original
    if (not root.is_dir() or root.is_symlink() or root.resolve()!=root or not evidence.is_dir()
            or evidence.is_symlink() or evidence.resolve()!=evidence): fail('target_readback_private_root')
    reservation=safe_json(evidence/'reservation.json',65536)
    fixed={'operation':original,'source_sha':original_source,'batch':'tv-live30-targets-20261001',
           'maximum_writes':0,'provider_http_calls':0,'state':'reserved_before_db_read'}
    if any(reservation.get(k)!=v for k,v in fixed.items()): fail('target_readback_reservation_binding')
    child=root/operation
    if child.exists() or child.is_symlink(): fail('target_readback_exists_no_replay')
    child.mkdir(mode=0o700)
    def exclusive(path,data):
        fd=os.open(path,os.O_WRONLY|os.O_CREAT|os.O_EXCL,0o600)
        with os.fdopen(fd,'wb') as stream:
            stream.write(json.dumps(data,sort_keys=True,separators=(',',':'),ensure_ascii=False).encode()+b'\n');stream.flush();os.fsync(stream.fileno())
    exclusive(child/'reservation.json',{'operation':operation,'source_sha':source,'evidence_operation':original,'no_replay':True})
    observed={}
    for name,cap in (('execution-started.json',65536),('result.json',8388608),('receipt.json',65536)):
        path=evidence/name;exists=path.exists() or path.is_symlink()
        if exists and not safe_file(path,cap): fail('target_readback_file_shape')
        observed[name]={'present':exists,'bytes':path.stat().st_size if exists else 0,
                        'sha256':hashlib.sha256(path.read_bytes()).hexdigest() if exists else None}
    terminal=observed['result.json']['present'] and observed['receipt.json']['present']
    output={'state':'completed_saved_target_catalog_readback','operation':operation,'source_sha':source,'batch':'tv-live30-targets-20261001',
            'evidence_operation':original,'evidence_source_sha':original_source,'original_read_reexecuted':False,
            'provider_http_calls':0,'database_reads':0,'database_writes':0,'mapping_writes':0,'safe_to_write_now':False,
            'no_replay':True,'terminal_verified':False,'files':observed,'catalog':None}
    if terminal:
        data=safe_json(evidence/'result.json',8388608);receipt=safe_json(evidence/'receipt.json',65536)
        validate_match_tv_live30_target_catalog(data,receipt,observed['result.json']['sha256'],original,original_source)
        output['terminal_verified']=True;output['catalog']=data
    exclusive(child/'result.json',output);return output
'''

REMOTE_TARGET_READBACK_DISPATCH = r'''    if mode=='match-tv-live30-target-readback':
        result['match_tv_live30_target_readback']=run_match_tv_live30_target_readback(stage)
        result['supplier_calls']=0
        result['database_reads']=0
        result['database_writes']=0
        result['mapping_writes']=0
        result['production_after']=fingerprints()
        if result['production_after']!=before: fail('production_drift')
        result['production_unchanged']=True
        result['status']='complete'
'''


def remote_with_primary(core, proof: bool = False, native: bool = False, guarded: bool = False, bg: bool = False,
                        shams_geo: bool = False, shams_geo_readback: bool = False, shams_write: bool = False,
                        target_catalog: bool = False, target_readback: bool = False,
                        target_preflight: bool = False) -> str:
    remote = core.REMOTE
    definition = 'def run_match942(stage, mode, offset, limit):\n'
    dispatch = "    if mode=='match-tv942-write':\n"
    collector = "    if mode not in ('reconcile',"
    core.need(remote.count(definition) == 1 and remote.count(dispatch) == 1
              and remote.count(collector) == 2, 'primary_registration_source_drift')
    handler = REMOTE_PROOF_HANDLER if proof else REMOTE_HANDLER
    mode_dispatch = REMOTE_PROOF_DISPATCH if proof else REMOTE_DISPATCH
    selected_mode = READBACK_MODE if proof else MODE
    if native:
        handler, mode_dispatch, selected_mode = REMOTE_NATIVE_HANDLER, REMOTE_NATIVE_DISPATCH, NATIVE_MODE
    if guarded:
        handler, mode_dispatch, selected_mode = REMOTE_GUARDED_HANDLER, REMOTE_GUARDED_DISPATCH, GUARDED_MODE
    if bg:
        handler, mode_dispatch, selected_mode = REMOTE_BG_HANDLER, REMOTE_BG_DISPATCH, BG_MODE
    if shams_geo:
        handler, mode_dispatch, selected_mode = REMOTE_SHAMS_GEO_HANDLER, REMOTE_SHAMS_GEO_DISPATCH, SHAMS_GEO_MODE
    if shams_geo_readback:
        handler, mode_dispatch, selected_mode = REMOTE_SHAMS_GEO_READBACK_HANDLER, REMOTE_SHAMS_GEO_READBACK_DISPATCH, SHAMS_GEO_READBACK_MODE
    if shams_write:
        handler, mode_dispatch, selected_mode = REMOTE_SHAMS_WRITE_HANDLER, REMOTE_SHAMS_WRITE_DISPATCH, SHAMS_WRITE_MODE
    if target_catalog:
        handler, mode_dispatch, selected_mode = REMOTE_TARGET_HANDLER, REMOTE_TARGET_DISPATCH, TARGET_MODE
    if target_readback:
        handler, mode_dispatch, selected_mode = REMOTE_TARGET_READBACK_HANDLER, REMOTE_TARGET_READBACK_DISPATCH, TARGET_READBACK_MODE
    if target_preflight:
        handler, mode_dispatch, selected_mode = REMOTE_TARGET_PREFLIGHT_HANDLER, REMOTE_TARGET_PREFLIGHT_DISPATCH, TARGET_PREFLIGHT_MODE
    remote = remote.replace(definition, handler + definition, 1)
    remote = remote.replace(dispatch, mode_dispatch + dispatch, 1)
    remote = remote.replace(collector, "    if mode not in ('" + selected_mode + "','reconcile',")
    ast.parse(remote)
    return remote


def activate(core, command: dict) -> None:
    if command.get('mode') not in (MODE, READBACK_MODE, NATIVE_MODE, GUARDED_MODE, BG_MODE, SHAMS_GEO_MODE, SHAMS_GEO_READBACK_MODE, SHAMS_WRITE_MODE, TARGET_MODE, TARGET_READBACK_MODE, TARGET_PREFLIGHT_MODE):
        return
    expected = core.parse_command(core.PREFIX + ' '.join([
        str(command.get('source_sha','')), command['mode'],
        str(command.get('operation_id','')), str(command.get('batch','')),
    ]))
    core.need(command == expected, 'primary_authorized_command_shape')
    proof = command['mode'] == READBACK_MODE
    native = command['mode'] == NATIVE_MODE
    guarded = command['mode'] == GUARDED_MODE
    bg = command['mode'] == BG_MODE
    shams_geo = command['mode'] == SHAMS_GEO_MODE
    shams_geo_readback = command['mode'] == SHAMS_GEO_READBACK_MODE
    shams_write = command['mode'] == SHAMS_WRITE_MODE
    target_catalog = command['mode'] == TARGET_MODE
    target_readback = command['mode'] == TARGET_READBACK_MODE
    target_preflight = command['mode'] == TARGET_PREFLIGHT_MODE
    remote = remote_with_primary(core, proof, native, guarded, bg, shams_geo, shams_geo_readback, shams_write, target_catalog, target_readback, target_preflight)
    files = list(core.FIXED)
    selected_files = SHAMS_WRITE_SOURCE_FILES if shams_write else (SHAMS_GEO_SOURCE_FILES if (shams_geo or shams_geo_readback) else (BG_SOURCE_FILES if bg else (GUARDED_SOURCE_FILES if guarded else (NATIVE_SOURCE_FILES if native else (PROOF_SOURCE_FILES if proof else SOURCE_FILES)))))
    if target_catalog or target_readback:
        selected_files = TARGET_SOURCE_FILES
    if target_preflight:
        selected_files = TARGET_PREFLIGHT_SOURCE_FILES
    for path in selected_files:
        if path not in files:
            files.append(path)
    core.FIXED = files
    core.REMOTE = remote
