"""Fixed new LOCAL schema operations in the existing owner-gated executor."""
from __future__ import annotations

import ast
import hashlib
import io
import json
from pathlib import Path
import tarfile

MODE = 'local-tv-schema-v1'
OPERATIONS = {
    'int-andromeda-local-tv-schema-inspect-20261010-v1': 'inspect',
    'int-andromeda-local-tv-schema-bootstrap-20261010-v1': 'bootstrap',
    'int-andromeda-local-tv-schema-readback-20261010-v1': 'readback',
}
INSPECT_OPERATION = next(iter(OPERATIONS))
SQL_SHA = '79e53072085eaac5eb37753a04ca7c68f3fcab2a4a4431d4e426e87e9f244bd2'
SOURCE_HASHES = {
    'v2/data/db-v1.php': 'ca7f11d6ec53e0cd3d4464e0f645f15b761bb734da1323689479838868d4abc4',
    'v2/data/migrations/20261010-local-tv-catalog.sql': SQL_SHA,
}
RUNNER = 'scripts/diagnostics/local_tv_schema_v1.php'
BUNDLE_FILES = (*SOURCE_HASHES, RUNNER)


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
        need(len(parts) == 3 and parts[2] in OPERATIONS, 'local_tv_schema_exact_operation')
        need(core.SHA_RE.fullmatch(parts[0]) is not None, 'source_sha')
        action = OPERATIONS[parts[2]]
        return {'source_sha': parts[0], 'mode': MODE, 'operation_id': parts[2], 'action': action,
                'schema_sha256': SQL_SHA, 'maximum_schema_tables': 2 if action == 'bootstrap' else 0,
                'maximum_content_writes': 0, 'provider_http_calls': 0}

    core.parse_command = parse


def bundle_source(source_root: Path) -> tuple[bytes, dict[str, str]]:
    root = source_root.resolve()
    control = Path(__file__).resolve().parents[2]
    hashes = {}
    output = io.BytesIO()
    with tarfile.open(fileobj=output, mode='w:gz', format=tarfile.PAX_FORMAT) as archive:
        for relative in BUNDLE_FILES:
            path = (control if relative == RUNNER else root) / relative
            need(path.is_file() and not path.is_symlink() and path.resolve() == path, 'local_tv_schema_source_path')
            data = path.read_bytes()
            need(0 < len(data) <= 2 * 1024 * 1024, 'local_tv_schema_source_size')
            digest = hashlib.sha256(data).hexdigest()
            if relative in SOURCE_HASHES:
                need(digest == SOURCE_HASHES[relative], 'local_tv_schema_reviewed_source_changed')
            hashes[relative] = digest
            info = tarfile.TarInfo(relative)
            info.size = len(data); info.mode = 0o600; info.mtime = info.uid = info.gid = 0
            info.uname = info.gname = ''
            archive.addfile(info, io.BytesIO(data))
        manifest = json.dumps({'schema_version': 1, 'files': hashes}, sort_keys=True, separators=(',', ':')).encode()
        info = tarfile.TarInfo('manifest.json'); info.size = len(manifest); info.mode = 0o600; info.mtime = 0
        archive.addfile(info, io.BytesIO(manifest))
    return output.getvalue(), hashes


REMOTE_HANDLER = r'''
def run_local_tv_schema(stage):
    expected_operations={
        'int-andromeda-local-tv-schema-inspect-20261010-v1':'inspect',
        'int-andromeda-local-tv-schema-bootstrap-20261010-v1':'bootstrap',
        'int-andromeda-local-tv-schema-readback-20261010-v1':'readback',
    }
    action=expected_operations.get(operation)
    schema_sha='79e53072085eaac5eb37753a04ca7c68f3fcab2a4a4431d4e426e87e9f244bd2'
    if (payload.get('action')!=action or action is None or payload.get('schema_sha256')!=schema_sha
            or type(payload.get('maximum_schema_tables')) is not int
            or payload['maximum_schema_tables']!=(2 if action=='bootstrap' else 0)
            or any(type(payload.get(k)) is not int or payload[k]!=0
                   for k in ('maximum_content_writes','provider_http_calls'))
            or not isinstance(payload.get('local_tv_schema_control_sha'),str)
            or not re.fullmatch(r'[a-f0-9]{40}',payload['local_tv_schema_control_sha'])):
        fail('local_tv_schema_scope')
    runner=stage/'scripts/diagnostics/local_tv_schema_v1.php'
    if not safe_file(runner,2*1024*1024): fail('local_tv_schema_runner')
    env={k:os.environ[k] for k in ('PATH','HOME','LANG','LC_ALL') if k in os.environ}
    env.update({'ANYTOUR_ROOT':str(project),'LOCAL_TV_SCHEMA_DIR':str(op),
                'LOCAL_TV_SCHEMA_SOURCE_ROOT':str(stage),'LOCAL_TV_SCHEMA_SOURCE_SHA':source,
                'LOCAL_TV_SCHEMA_CONTROL_SHA':payload['local_tv_schema_control_sha'],
                'LOCAL_TV_SCHEMA_OPERATION':operation})
    if action=='bootstrap':
        parent_path=private/'int-andromeda-local-tv-schema-inspect-20261010-v1'/'result.json'
        if not safe_file(parent_path,65536): fail('local_tv_schema_inspection_missing')
        parent=safe_json(parent_path,65536); inspection=parent.get('local_tv_schema',{})
        if (parent.get('status')!='complete' or parent.get('mode')!='local-tv-schema-v1'
                or inspection.get('state')!='inspected_read_only' or inspection.get('action')!='inspect'
                or inspection.get('schema_sha256')!=schema_sha or inspection.get('control_source_sha')!=payload['local_tv_schema_control_sha']):
            fail('local_tv_schema_inspection_contract')
        inventory=inspection.get('inventory',{}); tables=inventory.get('tables',{})
        if (set(tables)!={'local_tv_hotels','local_tv_legacy_links'}
                or any(v!={'present':False} for v in tables.values())):
            fail('local_tv_schema_preexisting_target_review')
        target=inventory.get('database_sha256')
        if not isinstance(target,str) or not re.fullmatch(r'[a-f0-9]{64}',target): fail('local_tv_schema_target')
        stamp=inspection.get('observed_at'); config_sha=inspection.get('config_sha256')
        if (type(stamp) is not int or not 0<=int(time.time())-stamp<=1800
                or not isinstance(config_sha,str) or not re.fullmatch(r'[a-f0-9]{64}',config_sha)):
            fail('local_tv_schema_inspection_stale')
        env['LOCAL_TV_SCHEMA_EXPECTED_DB_SHA256']=target
        env['LOCAL_TV_SCHEMA_EXPECTED_CONFIG_SHA256']=config_sha
    disabled='curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,socket_connect,exec,shell_exec,system,passthru,popen,proc_open'
    run=subprocess.run(['php','-d','display_errors=0','-d','log_errors=0','-d','allow_url_fopen=0',
                        '-d','disable_functions='+disabled,str(runner),action],
                       cwd=project,env=env,capture_output=True,text=True,timeout=180)
    receipt_path=op/'local-tv-schema-receipt.json'
    if not safe_file(receipt_path,65536): fail('local_tv_schema_terminal_missing_no_replay')
    data=safe_json(receipt_path,65536)
    allowed_keys={'schema_version','operation_id','source_sha','control_source_sha','schema_sha256',
                  'action','supplier_calls','content_writes','profile_writes','mapping_writes',
                  'schema_tables_created','state','observed_at','config_sha256','inventory',
                  'error_class','error_sha256'}
    if (data.get('schema_version')!=1 or data.get('operation_id')!=operation or data.get('source_sha')!=source
            or set(data)-allowed_keys
            or data.get('control_source_sha')!=payload['local_tv_schema_control_sha']
            or data.get('schema_sha256')!=schema_sha or data.get('action')!=action
            or any(type(data.get(k)) is not int or data[k]!=0
                   for k in ('supplier_calls','content_writes','profile_writes','mapping_writes'))):
        fail('local_tv_schema_receipt_contract')
    inventory=data.get('inventory')
    if inventory is not None:
        if (not isinstance(inventory,dict) or set(inventory)!={'database_sha256','tables'}
                or not isinstance(inventory['database_sha256'],str)
                or not re.fullmatch(r'[a-f0-9]{64}',inventory['database_sha256'])
                or not isinstance(inventory['tables'],dict)
                or set(inventory['tables'])!={'local_tv_hotels','local_tv_legacy_links'}):
            fail('local_tv_schema_inventory_contract')
        for table in inventory['tables'].values():
            if not isinstance(table,dict) or type(table.get('present')) is not bool:
                fail('local_tv_schema_table_contract')
            if table['present']:
                if (set(table)!={'present','valid','rows','issues'} or type(table['valid']) is not bool
                        or type(table['rows']) is not int or table['rows']<0
                        or not isinstance(table['issues'],list)
                        or any(v not in ('engine_or_collation','column_policy','hash_collation','state_default',
                                         'revision_default','columns','indexes','foreign_keys','unexpected_trigger') for v in table['issues'])):
                    fail('local_tv_schema_table_contract')
            elif set(table)!={'present'}:
                fail('local_tv_schema_absent_table_contract')
    result['local_tv_schema']=data
    expected_state='installed_empty' if action=='bootstrap' else 'inspected_read_only'
    if (run.returncode!=0 or run.stderr.strip() or data.get('state')!=expected_state
            or inventory is None
            or type(data.get('schema_tables_created')) is not int
            or data['schema_tables_created']!=(2 if action=='bootstrap' else 0)):
        fail('local_tv_schema_terminal_nonzero_no_replay')
    if action=='bootstrap':
        if not safe_file(op/'local-tv-schema-before.json',65536) or not safe_file(op/'local-tv-schema-after.json',65536):
            fail('local_tv_schema_images_missing_no_replay')
        after=safe_json(op/'local-tv-schema-after.json',65536)
        if (after!=inventory or inventory['database_sha256']!=env['LOCAL_TV_SCHEMA_EXPECTED_DB_SHA256']
                or any(t!={'present':True,'valid':True,'rows':0,'issues':[]} for t in inventory['tables'].values())):
            fail('local_tv_schema_readback_contract')
    try: emitted=json.loads(run.stdout.strip())
    except Exception: fail('local_tv_schema_stdout')
    if emitted!=data: fail('local_tv_schema_stdout')
    return data

'''

REMOTE_DISPATCH = r'''    if mode=='local-tv-schema-v1':
        result['local_tv_schema']=run_local_tv_schema(stage)
        result['supplier_calls']=0
        result['database_writes']=0
        result['schema_tables_created']=result['local_tv_schema']['schema_tables_created']
        result['schema_writes']=result['schema_tables_created']
        result['profile_writes']=0
        result['mapping_writes']=0
        result['production_after']=fingerprints()
        if result['production_after']!=before: fail('production_drift')
        result['production_unchanged']=True
        result['status']='complete'
'''


def activate(core, command: dict) -> None:
    if command.get('mode') != MODE:
        return
    expected = core.parse_command(core.PREFIX + ' '.join([str(command.get('source_sha', '')), MODE, str(command.get('operation_id', ''))]))
    need(command == expected and all(type(command.get(k)) is int for k in
         ('maximum_schema_tables','maximum_content_writes','provider_http_calls')), 'local_tv_schema_authorized_shape')
    remote = core.REMOTE
    definition = 'def run_match942(stage, mode, offset, limit):\n'
    dispatch = "    if mode=='match-tv942-write':\n"
    collector = "    if mode not in ('reconcile',"
    manifest = "    if not isinstance(files,dict) or len(files)<20: fail('manifest')"
    need(remote.count(definition)==1 and remote.count(dispatch)==1 and remote.count(collector)==2
         and remote.count(manifest)==1, 'local_tv_schema_registration_drift')
    remote = remote.replace(definition, REMOTE_HANDLER + definition, 1)
    remote = remote.replace(dispatch, REMOTE_DISPATCH + dispatch, 1)
    remote = remote.replace(collector, "    if mode not in ('" + MODE + "','reconcile',")
    literal = '{' + ', '.join(repr(v) for v in sorted(BUNDLE_FILES)) + '}'
    remote = remote.replace(manifest, "    if not isinstance(files,dict) or set(files)!=" + literal + ": fail('local_tv_schema_manifest')", 1)
    ast.parse(remote)
    core.REMOTE = remote
    core.bundle_source = bundle_source
