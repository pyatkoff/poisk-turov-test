"""Bounded MATCH registration for the existing stock executor entrypoint.

No new transport or credential mechanism: checked_event and execute stay in core.
All old modes delegate unchanged. Only the explicitly approved first batch exists.
"""
from __future__ import annotations

import ast
import re

MODE = 'match-primary-candidate'
READBACK_MODE = 'match-primary-proof-readback'
BATCH = 'samo3-20260929'
OPERATION_RE = re.compile(r'\Aint-andromeda-match-primary-[a-z0-9-]{8,48}-v[1-9][0-9]*\Z')
SOURCE_FILES = (
    'scripts/diagnostics/hotel_match_pending8_transition_v76.php',
    'scripts/diagnostics/hotel_match_raw_native_pending11_v78.php',
    'scripts/diagnostics/hotel_match_primary_candidate_v1.php',
)
PROOF_SOURCE_FILES = SOURCE_FILES + ('scripts/diagnostics/hotel_match_primary_proof_audit_v1.php',)


def register_parser(core) -> None:
    """Extend mode parsing; never replace owner/ref/comment authorization."""
    original = core.parse_command

    def parse(body: str) -> dict:
        if not body.startswith(core.PREFIX):
            return original(body)
        parts = body[len(core.PREFIX):].split()
        if len(parts) < 2 or parts[1] not in (MODE, READBACK_MODE):
            return original(body)
        core.need(len(parts) == 4, 'primary_command_shape')
        source, mode, operation, batch = parts
        core.need(core.SHA_RE.fullmatch(source) is not None, 'source_sha')
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
        420:('9501','operator_342','24402','hotel-match-live30-common4-acquire-1971-20260923-o0-n100-v1','1d10e02a1a541a242b7466b3eab99887203c005ee270469f2c351179a3387faa'),
        16944:('2000034238','operator_315','211585','hotel-match-live30-common4-continuation-resume-1971-20260924-r2-n138-v1','8e42b3e76cdef4075f09c9f8da68a8dd3881b93a263b094c88b74cc69b25ce3d'),
        42903:('3126','operator_315','849821','hotel-match-live30-common4-continuation-resume-1971-20260924-r1-n899-v1','11408e926160b87a10ffdf04ebb56106f17fb7efc30f95611033a5cb427dc794'),
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


def remote_with_primary(core, proof: bool = False) -> str:
    remote = core.REMOTE
    definition = 'def run_match942(stage, mode, offset, limit):\n'
    dispatch = "    if mode=='match-tv942-write':\n"
    collector = "    if mode not in ('reconcile',"
    core.need(remote.count(definition) == 1 and remote.count(dispatch) == 1
              and remote.count(collector) == 2, 'primary_registration_source_drift')
    handler = REMOTE_PROOF_HANDLER if proof else REMOTE_HANDLER
    mode_dispatch = REMOTE_PROOF_DISPATCH if proof else REMOTE_DISPATCH
    selected_mode = READBACK_MODE if proof else MODE
    remote = remote.replace(definition, handler + definition, 1)
    remote = remote.replace(dispatch, mode_dispatch + dispatch, 1)
    remote = remote.replace(collector, "    if mode not in ('" + selected_mode + "','reconcile',")
    ast.parse(remote)
    return remote


def activate(core, command: dict) -> None:
    if command.get('mode') not in (MODE, READBACK_MODE):
        return
    expected = core.parse_command(core.PREFIX + ' '.join([
        str(command.get('source_sha','')), command['mode'],
        str(command.get('operation_id','')), str(command.get('batch','')),
    ]))
    core.need(command == expected, 'primary_authorized_command_shape')
    proof = command['mode'] == READBACK_MODE
    remote = remote_with_primary(core, proof)
    files = list(core.FIXED)
    for path in PROOF_SOURCE_FILES if proof else SOURCE_FILES:
        if path not in files:
            files.append(path)
    core.FIXED = files
    core.REMOTE = remote
