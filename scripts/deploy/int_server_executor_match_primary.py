"""Bounded MATCH registration for the existing stock executor entrypoint.

No new transport or credential mechanism: checked_event and execute stay in core.
All old modes delegate unchanged. Only the explicitly approved first batch exists.
"""
from __future__ import annotations

import ast
import re

MODE = 'match-primary-candidate'
BATCH = 'samo3-20260929'
OPERATION_RE = re.compile(r'\Aint-andromeda-match-primary-[a-z0-9-]{8,48}-v[1-9][0-9]*\Z')
SOURCE_FILES = (
    'scripts/diagnostics/hotel_match_pending8_transition_v76.php',
    'scripts/diagnostics/hotel_match_raw_native_pending11_v78.php',
    'scripts/diagnostics/hotel_match_primary_candidate_v1.php',
)


def register_parser(core) -> None:
    """Extend mode parsing; never replace owner/ref/comment authorization."""
    original = core.parse_command

    def parse(body: str) -> dict:
        if not body.startswith(core.PREFIX):
            return original(body)
        parts = body[len(core.PREFIX):].split()
        if len(parts) < 2 or parts[1] != MODE:
            return original(body)
        core.need(len(parts) == 4, 'primary_command_shape')
        source, mode, operation, batch = parts
        core.need(core.SHA_RE.fullmatch(source) is not None, 'source_sha')
        core.need(core.OP_RE.fullmatch(operation) is not None
                  and OPERATION_RE.fullmatch(operation) is not None, 'primary_operation')
        core.need(batch == BATCH, 'primary_batch')
        return {'source_sha': source, 'mode': mode, 'operation_id': operation,
                'batch': BATCH, 'maximum_writes': 3, 'provider_http_calls': 0}

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


def remote_with_primary(core) -> str:
    remote = core.REMOTE
    definition = 'def run_match942(stage, mode, offset, limit):\n'
    dispatch = "    if mode=='match-tv942-write':\n"
    collector = "    if mode not in ('reconcile',"
    core.need(remote.count(definition) == 1 and remote.count(dispatch) == 1
              and remote.count(collector) == 2, 'primary_registration_source_drift')
    remote = remote.replace(definition, REMOTE_HANDLER + definition, 1)
    remote = remote.replace(dispatch, REMOTE_DISPATCH + dispatch, 1)
    remote = remote.replace(collector, "    if mode not in ('match-primary-candidate','reconcile',")
    ast.parse(remote)
    return remote


def activate(core, command: dict) -> None:
    if command.get('mode') != MODE:
        return
    expected = core.parse_command(core.PREFIX + ' '.join([
        str(command.get('source_sha','')), MODE,
        str(command.get('operation_id','')), str(command.get('batch','')),
    ]))
    core.need(command == expected, 'primary_authorized_command_shape')
    remote = remote_with_primary(core)
    files = list(core.FIXED)
    for path in SOURCE_FILES:
        if path not in files:
            files.append(path)
    core.FIXED = files
    core.REMOTE = remote
