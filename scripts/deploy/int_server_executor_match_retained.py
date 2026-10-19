"""Reviewed retained-evidence intake in the stock carrier; no new SSH/auth route."""
from __future__ import annotations
import ast
import hashlib
import io
import json
import os
from pathlib import Path
import re
import tarfile

MODES = {'match-retained-intake': 'common4-retained',
         'match-tourvisor-quota-policy': 'tourvisor-anex-5000',
         'match-retained-readback': 'common4-retained'}
SOURCE_FILES = ('scripts/diagnostics/hotel_match_retained_intake_v1.php',
                'scripts/diagnostics/hotel_match_retained_projection_v1.py',
                'scripts/diagnostics/hotel_match_tourvisor_quota_v1.py',
                'v2/data/db-v1.php')
SOURCE_HASHES = {'scripts/diagnostics/hotel_match_retained_intake_v1.php': '8c504844d96632e7bcd13d3becf16a7b7e5b388a64c2ef11e8c24f23a25a52c1', 'scripts/diagnostics/hotel_match_retained_projection_v1.py': '7d76acee710e33cc5c649d909b59304f7937ca868519a1287c87b75d235df9c6', 'scripts/diagnostics/hotel_match_tourvisor_quota_v1.py': '74a9735375fd40bb776c2d8e6f4af15fc477dd88dc6b879e18436e90ad5b4df5', 'v2/data/db-v1.php': 'c1c841d38c62845bc36e559183da17e0411c1c5bdd909517ffc02b5fbb1e2f13'}


def need(value, reason):
    if not value:
        raise ValueError(reason)


def register_parser(core):
    original = core.parse_command
    def parse(body):
        if not body.startswith(core.PREFIX):
            return original(body)
        parts = body[len(core.PREFIX):].split()
        if len(parts) < 2 or parts[1] not in MODES:
            return original(body)
        readback = parts[1] == 'match-retained-readback'
        need(len(parts) == (5 if readback else 4), 'retained_command_arity')
        source, mode, operation, cohort = parts[:4]
        need(core.SHA_RE.fullmatch(source) is not None, 'retained_source_sha')
        prefix = 'retained-readback' if readback else 'retained' if mode == 'match-retained-intake' else 'quota5000'
        need(re.fullmatch(r'int-andromeda-match-' + prefix + r'-[0-9]{8}-v[1-9][0-9]*', operation) is not None,
             'retained_operation_scope')
        need(cohort == MODES[mode], 'retained_cohort')
        need(os.environ.get('GITHUB_RUN_ATTEMPT', '1') == '1', 'retained_first_attempt_only')
        command = {'source_sha': source, 'mode': mode, 'operation_id': operation, 'cohort': cohort}
        if readback:
            need(re.fullmatch(r'int-andromeda-match-(?:retained|quota5000)-[0-9]{8}-v[1-9][0-9]*', parts[4]) is not None,
                 'retained_readback_target')
            command['target_operation_id'] = parts[4]
        return command
    core.parse_command = parse


def bundle_source(source_root):
    root = Path(source_root).resolve()
    out = io.BytesIO()
    hashes = {}
    need(set(SOURCE_HASHES) == set(SOURCE_FILES), 'retained_source_policy')
    with tarfile.open(fileobj=out, mode='w:gz', format=tarfile.PAX_FORMAT) as archive:
        for relative in SOURCE_FILES:
            path = root / relative
            need(path.is_file() and not path.is_symlink() and path.resolve() == path
                 and path.stat().st_nlink == 1, 'retained_source_path')
            raw = path.read_bytes()
            need(0 < len(raw) <= 1024*1024, 'retained_source_size')
            digest = hashlib.sha256(raw).hexdigest()
            need(digest == SOURCE_HASHES[relative], 'retained_reviewed_source_changed')
            hashes[relative] = digest
            info = tarfile.TarInfo(relative)
            info.size = len(raw); info.mode = 0o600; info.mtime = 0
            archive.addfile(info, io.BytesIO(raw))
        raw = json.dumps({'schema_version': 1, 'files': hashes}, sort_keys=True, separators=(',', ':')).encode()
        info = tarfile.TarInfo('manifest.json')
        info.size = len(raw); info.mode = 0o600; info.mtime = 0
        archive.addfile(info, io.BytesIO(raw))
    return out.getvalue(), hashes


REMOTE_HANDLER = r'''
def retained_readback():
    target_name=payload.get('target_operation_id','')
    if not re.fullmatch(r'int-andromeda-match-(?:retained|quota5000)-[0-9]{8}-v[1-9][0-9]*',target_name):fail('retained_readback_target')
    target=private/target_name
    if target.is_symlink() or target.resolve()!=target:fail('retained_target_path')
    reservation=safe_json(target/'reservation.json',65536);prior=safe_json(target/'result.json',32*1024*1024)
    if reservation.get('operation_id')!=target_name or prior.get('operation_id')!=target_name:fail('retained_target_identity')
    out={'target_operation_id':target_name,'target_source_sha':reservation.get('source_sha'),
        'prior_status':prior.get('status'),'prior_reason':prior.get('reason'),
        'prior_supplier_calls':prior.get('supplier_calls'),'prior_database_writes':prior.get('database_writes'),
        'prior_receipt_sha256':hashlib.sha256((target/'result.json').read_bytes()).hexdigest()}
    raw=target/'retained-raw.ndjson';out['raw_present']=raw.exists()
    if raw.exists():
        if not safe_file(raw,134217728) or raw.stat().st_nlink!=1:fail('retained_target_raw_path')
        digest=hashlib.sha256();kinds={};scope=None
        with raw.open('rb') as handle:
            for line in handle:
                if len(line)>4194304:fail('retained_target_raw_bound')
                digest.update(line)
                try:r=json.loads(line)
                except (ValueError,TypeError):kinds['invalid_json']=kinds.get('invalid_json',0)+1;continue
                kind=r.get('kind')
                if kind not in ('scope','observation','legacy_source','identity','target'):kind='unknown_kind'
                kinds[kind]=kinds.get(kind,0)+1
                if kind=='scope':scope={k:r.get(k) for k in ('cohort','captured_at_utc','database_snapshot','schema_columns')}
        out.update(raw_bytes=raw.stat().st_size,raw_sha256=digest.hexdigest(),raw_row_kinds=kinds,captured_scope=scope)
    helper=project/'data/db-v1.php';out['db_helper_sha256']=hashlib.sha256(helper.read_bytes()).hexdigest()
    if out['db_helper_sha256']=='ca7f11d6ec53e0cd3d4464e0f645f15b761bb734da1323689479838868d4abc4':
        php=r"""$_SERVER['DOCUMENT_ROOT']=$argv[1];require $argv[1].'/data/db-v1.php';try{$db=v2_data_db();$db->exec('SET TRANSACTION READ ONLY');$db->beginTransaction();$out=[];foreach(['tour_operator_identity_observations','local_tv_legacy_links','andromeda_hotel_identities','catalog_hotels']as $table){$q=$db->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$q->execute([$table]);$out[$table]=$q->fetchAll(PDO::FETCH_COLUMN);}$db->commit();echo json_encode(['columns'=>$out],JSON_THROW_ON_ERROR);}catch(Throwable $e){echo json_encode(['error_class'=>get_class($e),'error_sha256'=>hash('sha256',$e->getMessage())]);exit(1);}"""
        env={k:os.environ[k] for k in ('HOME','PATH','LANG','LC_ALL') if k in os.environ}
        run=subprocess.run(['php','-d','display_errors=0','-d','log_errors=0','-d','allow_url_fopen=0','-r',php,str(project)],env=env,cwd=project,capture_output=True,text=True,timeout=30)
        if run.stderr.strip() or len(run.stdout)>65536:fail('retained_schema_readback_failed')
        out['database_schema']=json.loads(run.stdout);out['schema_readback_exit']=run.returncode
    out['state']='reconciled_read_only'
    return out

def run_match_retained(stage):
    if (payload.get('cohort')!=RETAINED_MODES.get(mode)
            or not re.fullmatch(r'[a-f0-9]{40}',str(payload.get('match_retained_control_sha','')))):
        fail('retained_remote_scope')
    config=project/'config.php';dbhelper=project/'data/db-v1.php'
    if not safe_file(config,1048576) or not safe_file(dbhelper,65536):fail('retained_config_paths')
    config_hash=hashlib.sha256(config.read_bytes()).hexdigest()
    if mode=='match-retained-readback':
        data=retained_readback()
        data.update(operation_id=operation,source_sha=source,control_source_sha=payload['match_retained_control_sha'],
            cohort=payload['cohort'],supplier_calls=0,database_writes=0,mapping_writes=0,safe_to_write_now=False,no_replay=True)
        raw_result=json.dumps(data,sort_keys=True,separators=(',',':')).encode()
        return {'evidence_sha256':hashlib.sha256(raw_result).hexdigest(),'data':data}
    env={k:os.environ[k] for k in ('HOME','PATH','LANG','LC_ALL') if k in os.environ}
    env['MATCH_RETAINED_DIR']=str(op)
    env['MATCH_RETAINED_SOURCE_ROOT']=str(stage)
    if mode=='match-tourvisor-quota-policy':
        env['MATCH_QUOTA_ACTION']='apply-owner-5000'
        args=['python3',str(stage/'scripts/diagnostics/hotel_match_tourvisor_quota_v1.py')]
    else:
        disabled='curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,socket_create,socket_connect,exec,system,shell_exec,passthru,proc_open,popen'
        args=['php','-d','display_errors=0','-d','log_errors=0','-d','allow_url_fopen=0',
              '-d','memory_limit=512M','-d','disable_functions='+disabled,
              str(stage/'scripts/diagnostics/hotel_match_retained_intake_v1.php')]
    run=subprocess.run(args,cwd=project,env=env,capture_output=True,text=True,timeout=600)
    if run.returncode or run.stderr.strip() or len(run.stdout.encode())>65536:
        failure={'exit_code':run.returncode,'stdout_sha256':hashlib.sha256(run.stdout.encode()).hexdigest(),
            'stderr_sha256':hashlib.sha256(run.stderr.encode()).hexdigest()}
        try:
            child=json.loads(run.stdout)
            failure['child_error']={k:child.get(k) for k in ('state','error_code','error_class','error_sha256')}
        except Exception:pass
        result['match_retained_child_failure']=failure
        fail('retained_child_failed_no_replay')
    data=json.loads(run.stdout)
    if mode=='match-retained-intake':
        raw=op/'retained-raw.ndjson'
        if not safe_file(raw,134217728) or raw.stat().st_nlink!=1:fail('retained_raw_missing')
        run2=subprocess.run(['python3',str(stage/'scripts/diagnostics/hotel_match_retained_projection_v1.py'),str(raw)],
            cwd=project,env=env,capture_output=True,text=True,timeout=240)
        if run2.returncode or run2.stderr.strip() or len(run2.stdout.encode())>32*1024*1024:
            fail('retained_projection_failed_no_replay')
        projected=json.loads(run2.stdout)
        if (data.get('state')!='captured_read_only' or projected.get('state')!='projected_read_only'
                or data.get('raw_sha256')!=projected.get('raw_sha256')
                or data.get('raw_bytes')!=projected.get('raw_bytes')
                or any(data.get('rows',{}).get(k)!=projected.get('capture_rows',{}).get(k,0)
                       for k in ('observation','legacy_source','identity','target'))
                or projected.get('safe_to_write_now') is not False
                or any(type(projected.get(k)) is not int or projected[k]!=0
                       for k in ('supplier_calls','database_writes','mapping_writes','new_accepted_mappings'))):
            fail('retained_capture_projection_binding')
        data={'capture':data,'projection':projected,'state':'captured_and_projected_read_only'}
    else:
        if (data.get('account')!='TOURVISOR_ANEX_JWT' or data.get('policy')!='physical-http-v1'
                or data.get('daily_physical_http_limit')!=5000 or data.get('supplier_calls')!=0
                or data.get('operations_preserved') is not True or data.get('global_usage')!='not_measured'):
            fail('retained_quota_policy_binding')
        data={'quota_policy':data,'state':'quota_policy_applied_no_supplier_calls'}
    if hashlib.sha256(config.read_bytes()).hexdigest()!=config_hash:fail('retained_config_drift')
    data.update(operation_id=operation,source_sha=source,control_source_sha=payload['match_retained_control_sha'],
        cohort=payload['cohort'],supplier_calls=0,database_writes=0,mapping_writes=0,
        safe_to_write_now=False,no_replay=True)
    raw_result=json.dumps(data,sort_keys=True,separators=(',',':')).encode()
    fd=os.open(op/'retained-evidence.json',os.O_WRONLY|os.O_CREAT|os.O_EXCL|os.O_NOFOLLOW,0o600)
    with os.fdopen(fd,'wb') as handle:
        handle.write(raw_result);handle.flush();os.fsync(handle.fileno())
    return {'evidence_sha256':hashlib.sha256(raw_result).hexdigest(),'data':data}
'''

REMOTE_DISPATCH = r'''    if mode in RETAINED_MODES:
        result['supplier_calls']=result['database_writes']=result['mapping_writes']=0
        result['match_retained']=run_match_retained(stage)
        result['production_after']=fingerprints()
        if result['production_after']!=before:fail('production_drift')
        result['production_unchanged']=True;result['status']='complete'
'''


def completed_result(command, result, control_sha):
    try:
        data = result['match_retained']['data']
        if (command['mode'] not in MODES or result.get('status') != 'complete'
                or result.get('mode') != command['mode']
                or result.get('source_sha') != command['source_sha']
                or result.get('operation_id') != command['operation_id']
                or data.get('source_sha') != command['source_sha']
                or data.get('operation_id') != command['operation_id']
                or data.get('control_source_sha') != control_sha
                or data.get('cohort') != command['cohort']
                or data.get('no_replay') is not True or data.get('safe_to_write_now') is not False
                or result.get('production_unchanged') is not True):
            return False
        for obj in (data, result):
            if any(type(obj.get(k)) is not int or obj[k] != 0 for k in ('supplier_calls','database_writes','mapping_writes')):
                return False
        if command['mode']=='match-retained-readback' and data.get('target_operation_id')!=command.get('target_operation_id'):
            return False
        expected = {'match-retained-intake':'captured_and_projected_read_only',
                    'match-tourvisor-quota-policy':'quota_policy_applied_no_supplier_calls',
                    'match-retained-readback':'reconciled_read_only'}[command['mode']]
        return (data.get('state')==expected and result['match_retained'].get('evidence_sha256')==
                hashlib.sha256(json.dumps(data,sort_keys=True,separators=(',',':')).encode()).hexdigest())
    except (KeyError, TypeError, AttributeError):
        return False


def activate(core, command):
    if command.get('mode') not in MODES:
        return
    fields=('source_sha','mode','operation_id','cohort')+ (('target_operation_id',) if command.get('mode')=='match-retained-readback' else ())
    expected=core.parse_command(core.PREFIX+' '.join(str(command.get(k,'')) for k in fields))
    need(command==expected,'retained_command_shape')
    control=os.environ.get('GITHUB_SHA','')
    need(re.fullmatch(r'[a-f0-9]{40}',control) is not None,'retained_control_sha')
    command['match_retained_control_sha']=control
    remote=core.REMOTE
    anchors={'definition':'def run_match942(stage, mode, offset, limit):\n',
             'dispatch':"    if mode=='match-tv942-write':\n",
             'collector':"    if mode not in ('reconcile',",
             'manifest':"    if not isinstance(files,dict) or len(files)<20: fail('manifest')"}
    for key, anchor in anchors.items():
        need(remote.count(anchor)==(2 if key=='collector' else 1),'retained_registration_drift_'+key)
    remote=remote.replace(anchors['definition'],'RETAINED_MODES='+repr(MODES)+'\n'+REMOTE_HANDLER+'\n'+anchors['definition'],1)
    remote=remote.replace(anchors['dispatch'],REMOTE_DISPATCH+anchors['dispatch'],1)
    remote=remote.replace(anchors['collector'],"    if mode not in ('match-retained-intake','match-tourvisor-quota-policy','match-retained-readback','reconcile',")
    remote=remote.replace(anchors['manifest'],"    if not isinstance(files,dict) or files!="+repr(SOURCE_HASHES)+": fail('retained_manifest')",1)
    reservation='    op.mkdir(mode=0o700)\n'
    receipt="if 'op' in globals() and op.exists():"
    need(remote.count(reservation)==1 and remote.count(receipt)==1,'retained_reservation_anchor')
    remote=remote.replace(reservation,reservation+'    retained_reserved=True\n',1)
    remote=remote.replace(receipt,"if globals().get('retained_reserved') is True and op.exists():",1)
    ast.parse(remote)
    core.REMOTE=remote;core.bundle_source=bundle_source
    original=core.completed_result
    core.completed_result=lambda cmd,res: completed_result(cmd,res,control) if cmd.get('mode') in MODES else original(cmd,res)
