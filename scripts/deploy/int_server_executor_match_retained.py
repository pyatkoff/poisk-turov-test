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
         'match-retained-readback': 'common4-retained',
         'match-retained-project': 'common4-retained',
         'match-retained-current': 'common4-retained',
         'match-retained-acquire': 'anex105-current',
         'match-retained-anex-write': 'anex7-verified'}
SOURCE_FILES = ('scripts/diagnostics/hotel_match_retained_intake_v1.php',
                'scripts/diagnostics/hotel_match_retained_projection_v1.py',
                'scripts/diagnostics/hotel_match_tourvisor_quota_v1.py',
                'v2/data/db-v1.php',
                'scripts/diagnostics/hotel_match_retained_current_v1.php',
                'scripts/diagnostics/hotel_match_anex_effective_coverage.php',
                'scripts/diagnostics/hotel_match_live30_common4_anex_current_v4.php',
                'app/integrations/anex-search-mapping-registry.php',
                'scripts/diagnostics/hotel_match_retained_acquire_v1.py',
                'scripts/diagnostics/hotel_match_live30_common4_continuation_acquire_v10.py',
                'scripts/diagnostics/hotel_match_retained_anex_write_v1.php',
                'scripts/diagnostics/hotel_match_live30_common4_anex_writer_v5.php')
SOURCE_HASHES = {'scripts/diagnostics/hotel_match_retained_intake_v1.php': '8c504844d96632e7bcd13d3becf16a7b7e5b388a64c2ef11e8c24f23a25a52c1', 'scripts/diagnostics/hotel_match_retained_projection_v1.py': '6ad86cdcd33edce8d20d238fdd4ba46dc61b76b16e6b32a85028fb2853a000d4', 'scripts/diagnostics/hotel_match_tourvisor_quota_v1.py': '74a9735375fd40bb776c2d8e6f4af15fc477dd88dc6b879e18436e90ad5b4df5', 'v2/data/db-v1.php': 'c1c841d38c62845bc36e559183da17e0411c1c5bdd909517ffc02b5fbb1e2f13', 'scripts/diagnostics/hotel_match_retained_current_v1.php': 'fc0c7f1dc6d913dd83ac7713ba1572ca3f17921da16d11b57e246c289e3615e2', 'scripts/diagnostics/hotel_match_anex_effective_coverage.php': '638694ec2d7254d4436f0cdac3f7ce780ef3c987d12ecf0e648c9ad64e2f4067', 'scripts/diagnostics/hotel_match_live30_common4_anex_current_v4.php': '98243ba62910a2becf3c15a6a5c73d9635d07bca84374110d377577680a906b3', 'app/integrations/anex-search-mapping-registry.php': 'ba06d082ac66d671efc01569a7d11e9d8b5abd73cabde8ea72ecc154b3e67814', 'scripts/diagnostics/hotel_match_retained_acquire_v1.py': '24e6efc5e9d8ff849a866ad579833ebfc616d756eb400c7375efefd665137995', 'scripts/diagnostics/hotel_match_live30_common4_continuation_acquire_v10.py': '9690f85d2d7e5c5e66f946e83024c1df98ab7f4e35285e9b0cbd8acde72f385f', 'scripts/diagnostics/hotel_match_retained_anex_write_v1.php': '20fadcfc0064ba4a3bd0f807cc91194cccda41e98ed25a8706897456a3adfb64', 'scripts/diagnostics/hotel_match_live30_common4_anex_writer_v5.php': '4c6f232d139b2d88727be17727a072acefcb59b9a1aec5086b95c085318a04d4'}


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
        target_mode = parts[1] in ('match-retained-readback','match-retained-project','match-retained-current','match-retained-acquire','match-retained-anex-write')
        need(len(parts) == (5 if target_mode else 4), 'retained_command_arity')
        source, mode, operation, cohort = parts[:4]
        need(core.SHA_RE.fullmatch(source) is not None, 'retained_source_sha')
        prefix = 'retained-readback' if mode=='match-retained-readback' else 'retained-project' if mode=='match-retained-project' else 'retained-current' if mode=='match-retained-current' else 'retained-acquire' if mode=='match-retained-acquire' else 'retained-anex-write' if mode=='match-retained-anex-write' else 'retained' if mode == 'match-retained-intake' else 'quota5000'
        need(re.fullmatch(r'int-andromeda-match-' + prefix + r'-[0-9]{8}-v[1-9][0-9]*', operation) is not None,
             'retained_operation_scope')
        need(cohort == MODES[mode], 'retained_cohort')
        if mode=='match-retained-acquire':need(operation=='int-andromeda-match-retained-acquire-20261020-v1','acquire_consumed_scope')
        if mode=='match-retained-anex-write':need(operation=='int-andromeda-match-retained-anex-write-20261020-v1','write_fixed_scope')
        need(os.environ.get('GITHUB_RUN_ATTEMPT', '1') == '1', 'retained_first_attempt_only')
        command = {'source_sha': source, 'mode': mode, 'operation_id': operation, 'cohort': cohort}
        if target_mode:
            family='retained-acquire' if mode=='match-retained-anex-write' else 'retained-current' if mode=='match-retained-acquire' else 'retained-project' if mode=='match-retained-current' else 'retained' if mode=='match-retained-project' else '(?:retained|quota5000)'
            need(re.fullmatch(r'int-andromeda-match-'+family+r'-[0-9]{8}-v[1-9][0-9]*', parts[4]) is not None,
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
def write_retained_anex_target(stage):
    name=payload.get('target_operation_id','')
    if name!='int-andromeda-match-retained-acquire-20261020-v1':fail('write_acquisition_scope')
    target=private/name
    if target.is_symlink() or target.resolve()!=target:fail('write_acquisition_path')
    prior=safe_json(target/'result.json',4194304);reservation=safe_json(target/'reservation.json',65536)
    acquired=prior.get('match_retained',{}).get('data',{})
    expected='cb7f28c82d5df5b8f069f894ab978f198b2eebfc949034c8e371050c4f4e0aeb'
    if (prior.get('status')!='complete' or prior.get('mode')!='match-retained-acquire'
            or acquired.get('acquisition_state')!='completed' or acquired.get('unknown_http_calls')!=0
            or any(x.get('operation_id')!=name for x in (prior,reservation,acquired))
            or acquired.get('source_sha')!=reservation.get('source_sha') or prior.get('source_sha')!=reservation.get('source_sha')
            or hashlib.sha256(json.dumps(acquired,sort_keys=True,separators=(',',':')).encode()).hexdigest()!=expected
            or prior.get('match_retained',{}).get('evidence_sha256')!=expected
            or prior.get('production_unchanged') is not True
            or any(x.get(k)!=0 for x in (prior,acquired) for k in ('database_writes','mapping_writes'))):fail('write_acquisition_binding')
    parent_hash=hashlib.sha256((target/'result.json').read_bytes()).hexdigest()
    current_dir=private/'int-andromeda-match-retained-current-20261020-v1'
    current=safe_json(current_dir/'result.json',32*1024*1024)
    if hashlib.sha256((current_dir/'result.json').read_bytes()).hexdigest()!=acquired.get('parent_receipt_sha256'):fail('write_current_parent_drift')
    context=current.get('match_retained',{}).get('data',{})
    if hashlib.sha256(json.dumps(context,sort_keys=True,separators=(',',':')).encode()).hexdigest()!=acquired.get('parent_evidence_sha256'):fail('write_current_evidence')
    snapshots={r['tv_hotel_id']:r for r in context.get('rows',[])}
    import sys
    sys.path.insert(0,str(stage/'scripts/diagnostics'))
    import hotel_match_retained_acquire_v1 as proof
    pairs=[]
    for edge in acquired.get('edges',[]):
        if edge.get('state')!='verified_native_pair':continue
        raw_info=edge.get('detail_raw',{});number=raw_info.get('response_number')
        if type(number) is not int or not 1<=number<=400:fail('write_raw_number')
        raw_path=target/('response-%04d.bin'%number)
        if not safe_file(raw_path,16*1024*1024):fail('write_raw_path')
        raw=raw_path.read_bytes()
        if hashlib.sha256(raw).hexdigest()!=raw_info.get('raw_sha256'):fail('write_raw_hash')
        meta=safe_json(target/('response-%04d.json'%number),65536)
        request=safe_json(target/('request-%04d.json'%number),65536)
        if (meta.get('http_status')!=200 or meta.get('complete_body') is not True or meta.get('raw_sha256')!=raw_info['raw_sha256']
                or request.get('action')!='tour_detail' or not re.fullmatch(r'/tours/[1-9][0-9]{0,21}',str(request.get('path','')))
                or hashlib.sha256(request['path'].split('/')[-1].encode()).hexdigest()!=edge.get('tour_id_sha256')):fail('write_response_binding')
        detail=proof.shape.unwrap(json.loads(raw));verified=proof.native_pair(detail,edge['tv_hotel_id'])
        for key in ('state','anex_hotel_id','tv_hotel_id','namespace','operator_id','operator_link_host','operator_link_sha256'):
            if verified.get(key)!=edge.get(key):fail('write_native_proof_mismatch')
        snap=snapshots.get(edge['tv_hotel_id'])
        if not snap or snap.get('acquisition_ready') is not True:fail('write_target_snapshot')
        pairs.append({'edge':edge,'snapshot':snap})
    actual=sorted((p['edge']['anex_hotel_id'],p['edge']['tv_hotel_id']) for p in pairs)
    if actual!=sorted([('9384',35297),('12901',1474),('8475',1014),('8476',1095),('14818',1015),('26591',3418),('19606',57027)]):fail('write_exact_seven_pairs')
    manifest={'operation_id':operation,'acquisition_evidence_sha256':expected,'pairs':pairs}
    fd=os.open(op/'write-manifest.json',os.O_WRONLY|os.O_CREAT|os.O_EXCL|os.O_NOFOLLOW,0o600)
    with os.fdopen(fd,'wb') as handle:handle.write(json.dumps(manifest,sort_keys=True).encode());handle.flush();os.fsync(handle.fileno())
    config=project/'config.php'
    if not safe_file(config,1048576):fail('write_config_path')
    config_hash=hashlib.sha256(config.read_bytes()).hexdigest()
    env={k:os.environ[k] for k in ('HOME','PATH','LANG','LC_ALL') if k in os.environ}
    env.update(MATCH_RETAINED_DIR=str(op),MATCH_RETAINED_SOURCE_ROOT=str(stage),MATCH_RETAINED_SOURCE_SHA=source)
    disabled='curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,socket_create,socket_connect,exec,system,shell_exec,passthru,proc_open,popen'
    run=subprocess.run(['php','-d','display_errors=0','-d','log_errors=0','-d','allow_url_fopen=0','-d','disable_functions='+disabled,
        str(stage/'scripts/diagnostics/hotel_match_retained_anex_write_v1.php')],cwd=project,env=env,capture_output=True,text=True,timeout=240)
    if len(run.stdout.encode())>1048576:fail('write_output_bound')
    fd=os.open(op/'write-raw.json',os.O_WRONLY|os.O_CREAT|os.O_EXCL|os.O_NOFOLLOW,0o600)
    with os.fdopen(fd,'wb') as handle:handle.write(run.stdout.encode());handle.flush();os.fsync(handle.fileno())
    try:data=json.loads(run.stdout)
    except Exception:fail('write_output_unknown_no_replay')
    result['database_writes']=data.get('database_writes');result['mapping_writes']=data.get('mapping_writes')
    if run.returncode or run.stderr.strip() or data.get('state') not in ('committed_verified','completed_no_eligible'):
        result['match_retained_write_failure']=data;fail('write_failed_no_replay')
    if (data.get('supplier_calls')!=0 or data.get('safe_to_write_now') is not False
            or hashlib.sha256((target/'result.json').read_bytes()).hexdigest()!=parent_hash
            or hashlib.sha256(config.read_bytes()).hexdigest()!=config_hash):fail('write_post_binding_unknown_no_replay')
    data.update(operation_id=operation,source_sha=source,control_source_sha=payload['match_retained_control_sha'],
        cohort=payload['cohort'],target_operation_id=name,parent_receipt_sha256=parent_hash,
        acquisition_evidence_sha256=expected,raw_evidence_reverified_pairs=7,no_replay=True)
    evidence=json.dumps(data,sort_keys=True,separators=(',',':')).encode()
    fd=os.open(op/'retained-evidence.json',os.O_WRONLY|os.O_CREAT|os.O_EXCL|os.O_NOFOLLOW,0o600)
    with os.fdopen(fd,'wb') as handle:handle.write(evidence);handle.flush();os.fsync(handle.fileno())
    return {'evidence_sha256':hashlib.sha256(evidence).hexdigest(),'data':data}

def acquire_retained_target(stage):
    name=payload.get('target_operation_id','')
    if name!='int-andromeda-match-retained-current-20261020-v1':fail('acquire_parent_scope')
    target=private/name
    if target.is_symlink() or target.resolve()!=target:fail('acquire_parent_path')
    prior=safe_json(target/'result.json',32*1024*1024);reservation=safe_json(target/'reservation.json',65536)
    parent=prior.get('match_retained',{}).get('data',{})
    digest=hashlib.sha256(json.dumps(parent,sort_keys=True,separators=(',',':')).encode()).hexdigest()
    if (prior.get('status')!='complete' or prior.get('mode')!='match-retained-current'
            or parent.get('state')!='current_retained_read_only' or prior.get('production_unchanged') is not True
            or any(x.get('operation_id')!=name for x in (prior,reservation,parent))
            or parent.get('source_sha')!=prior.get('source_sha') or prior.get('source_sha')!=reservation.get('source_sha')
            or digest!='c0d7c1f53cceea267f08a5b15d29d9f2bb314401b7de380e0fe40b59ad513046'
            or digest!=prior.get('match_retained',{}).get('evidence_sha256')
            or any(type(x.get(k)) is not int or x[k]!=0 for x in (prior,parent) for k in ('supplier_calls','database_writes','mapping_writes'))):fail('acquire_parent_not_complete')
    raw=target/'current-raw.json'
    if not safe_file(raw,32*1024*1024) or hashlib.sha256(raw.read_bytes()).hexdigest()!=parent.get('current_raw_sha256'):fail('acquire_parent_raw')
    before=hashlib.sha256((target/'result.json').read_bytes()).hexdigest()
    config=project/'config.php'
    if not safe_file(config,1048576):fail('acquire_config_path')
    config_hash=hashlib.sha256(config.read_bytes()).hexdigest()
    fd=os.open(op/'acquisition-input.json',os.O_WRONLY|os.O_CREAT|os.O_EXCL|os.O_NOFOLLOW,0o600)
    with os.fdopen(fd,'wb') as handle:handle.write(json.dumps({'current':parent,'parent_evidence_sha256':digest},sort_keys=True).encode());handle.flush();os.fsync(handle.fileno())
    env={k:os.environ[k] for k in ('HOME','PATH','LANG','LC_ALL') if k in os.environ}
    env.update(MATCH_RETAINED_DIR=str(op),MATCH_RETAINED_OPERATION=operation)
    run=subprocess.run(['python3',str(stage/'scripts/diagnostics/hotel_match_retained_acquire_v1.py')],cwd=project,env=env,capture_output=True,text=True,timeout=780)
    if run.returncode or run.stderr.strip() or len(run.stdout.encode())>4*1024*1024:
        result['match_retained_child_failure']={'exit_code':run.returncode,'stdout_sha256':hashlib.sha256(run.stdout.encode()).hexdigest(),'stderr_sha256':hashlib.sha256(run.stderr.encode()).hexdigest()}
        fail('acquire_child_failed_no_replay')
    data=json.loads(run.stdout)
    if (data.get('state')!='acquired_retained_native_read_only' or data.get('selected_hotels')!=105
            or data.get('parent_evidence_sha256')!=digest
            or data.get('selected_id_sha256')!='4f8fa702fad1445d2a6bb5978bbdc1e60f30abd75b9328251f381a86f2b351d3'
            or data.get('safe_to_write_now') is not False
            or any(type(data.get(k)) is not int or data[k]!=0 for k in ('database_writes','mapping_writes','new_accepted_mappings'))
            or type(data.get('supplier_calls')) is not int or not 0<=data['supplier_calls']<=400
            or hashlib.sha256((target/'result.json').read_bytes()).hexdigest()!=before
            or hashlib.sha256(config.read_bytes()).hexdigest()!=config_hash):fail('acquire_result_binding')
    data.update(operation_id=operation,source_sha=source,control_source_sha=payload['match_retained_control_sha'],
        cohort=payload['cohort'],target_operation_id=name,parent_receipt_sha256=before,no_replay=True)
    evidence=json.dumps(data,sort_keys=True,separators=(',',':')).encode()
    fd=os.open(op/'retained-evidence.json',os.O_WRONLY|os.O_CREAT|os.O_EXCL|os.O_NOFOLLOW,0o600)
    with os.fdopen(fd,'wb') as handle:handle.write(evidence);handle.flush();os.fsync(handle.fileno())
    return {'evidence_sha256':hashlib.sha256(evidence).hexdigest(),'data':data}

def current_retained_target(stage):
    name=payload.get('target_operation_id','')
    if not re.fullmatch(r'int-andromeda-match-retained-project-[0-9]{8}-v[1-9][0-9]*',name):fail('current_parent_name')
    target=private/name
    if target.is_symlink() or target.resolve()!=target:fail('current_parent_path')
    prior=safe_json(target/'result.json',32*1024*1024);reservation=safe_json(target/'reservation.json',65536)
    parent=prior.get('match_retained',{}).get('data',{});projection=parent.get('projection',{})
    if (prior.get('status')!='complete' or prior.get('mode')!='match-retained-project'
            or parent.get('state')!='reprojected_retained_read_only'
            or projection.get('state')!='projected_read_only' or projection.get('cohort')!='common4-retained'
            or prior.get('production_unchanged') is not True
            or any(x.get('operation_id')!=name for x in (prior,reservation,parent))
            or prior.get('source_sha')!=reservation.get('source_sha') or parent.get('source_sha')!=prior.get('source_sha')
            or hashlib.sha256(json.dumps(parent,sort_keys=True,separators=(',',':')).encode()).hexdigest()!=prior.get('match_retained',{}).get('evidence_sha256')
            or any(type(x.get(k)) is not int or x[k]!=0 for x in (prior,parent,projection) for k in ('supplier_calls','database_writes','mapping_writes'))):
        fail('current_parent_not_complete')
    ids=projection.get('cohort_tv_hotel_ids')
    if (not isinstance(ids,list) or not 0<len(ids)<=10000 or any(type(v) is not int or v<1 for v in ids)
            or ids!=sorted(set(ids)) or len(ids)!=projection.get('cohort_hotels')):fail('current_parent_cohort')
    parent_hash=hashlib.sha256((target/'result.json').read_bytes()).hexdigest()
    scope={'tv_hotel_ids':ids,'parent_receipt_sha256':parent_hash}
    fd=os.open(op/'current-scope.json',os.O_WRONLY|os.O_CREAT|os.O_EXCL|os.O_NOFOLLOW,0o600)
    with os.fdopen(fd,'wb') as handle:handle.write(json.dumps(scope,sort_keys=True).encode());handle.flush();os.fsync(handle.fileno())
    config=project/'config.php'
    if not safe_file(config,1048576):fail('current_config_path')
    before_hash=hashlib.sha256(config.read_bytes()).hexdigest()
    env={k:os.environ[k] for k in ('HOME','PATH','LANG','LC_ALL') if k in os.environ}
    env.update(MATCH_RETAINED_DIR=str(op),MATCH_RETAINED_SOURCE_ROOT=str(stage))
    disabled='curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,socket_create,socket_connect,exec,system,shell_exec,passthru,proc_open,popen'
    run=subprocess.run(['php','-d','display_errors=0','-d','log_errors=0','-d','allow_url_fopen=0',
        '-d','memory_limit=512M','-d','disable_functions='+disabled,
        str(stage/'scripts/diagnostics/hotel_match_retained_current_v1.php')],cwd=project,env=env,capture_output=True,text=True,timeout=300)
    if len(run.stdout.encode())>32*1024*1024:fail('current_output_bound')
    fd=os.open(op/'current-raw.json',os.O_WRONLY|os.O_CREAT|os.O_EXCL|os.O_NOFOLLOW,0o600)
    with os.fdopen(fd,'wb') as handle:handle.write(run.stdout.encode());handle.flush();os.fsync(handle.fileno())
    if run.returncode or run.stderr.strip():
        result['match_retained_child_failure']={'exit_code':run.returncode,'stdout_sha256':hashlib.sha256(run.stdout.encode()).hexdigest(),'stderr_sha256':hashlib.sha256(run.stderr.encode()).hexdigest()}
        fail('current_child_failed_no_replay')
    data=json.loads(run.stdout)
    if (data.get('state')!='current_retained_read_only' or data.get('parent_receipt_sha256')!=parent_hash
            or data.get('cohort_hotels')!=len(ids) or [r.get('tv_hotel_id') for r in data.get('rows',[])]!=ids
            or data.get('safe_to_write_now') is not False
            or any(type(data.get(k)) is not int or data[k]!=0 for k in ('supplier_calls','database_writes','mapping_writes'))
            or hashlib.sha256((target/'result.json').read_bytes()).hexdigest()!=parent_hash
            or hashlib.sha256(config.read_bytes()).hexdigest()!=before_hash):fail('current_result_binding')
    data.update(operation_id=operation,source_sha=source,control_source_sha=payload['match_retained_control_sha'],
        cohort=payload['cohort'],target_operation_id=name,current_raw_sha256=hashlib.sha256(run.stdout.encode()).hexdigest(),
        no_replay=True,new_database_reads=1)
    raw_result=json.dumps(data,sort_keys=True,separators=(',',':')).encode()
    fd=os.open(op/'retained-evidence.json',os.O_WRONLY|os.O_CREAT|os.O_EXCL|os.O_NOFOLLOW,0o600)
    with os.fdopen(fd,'wb') as handle:handle.write(raw_result);handle.flush();os.fsync(handle.fileno())
    return {'evidence_sha256':hashlib.sha256(raw_result).hexdigest(),'data':data}

def project_retained_target(stage):
    target_name=payload.get('target_operation_id','')
    if not re.fullmatch(r'int-andromeda-match-retained-[0-9]{8}-v[1-9][0-9]*',target_name):fail('retained_projection_target')
    target=private/target_name
    if target.is_symlink() or target.resolve()!=target:fail('retained_projection_target_path')
    prior=safe_json(target/'result.json',32*1024*1024);reservation=safe_json(target/'reservation.json',65536)
    parent=prior.get('match_retained',{}).get('data',{});capture=parent.get('capture',{})
    if (prior.get('status')!='complete' or prior.get('mode')!='match-retained-intake'
            or prior.get('production_unchanged') is not True
            or prior.get('operation_id')!=target_name or reservation.get('operation_id')!=target_name
            or parent.get('operation_id')!=target_name or parent.get('source_sha')!=reservation.get('source_sha')
            or capture.get('state')!='captured_read_only'
            or hashlib.sha256(json.dumps(parent,sort_keys=True,separators=(',',':')).encode()).hexdigest()!=prior.get('match_retained',{}).get('evidence_sha256')
            or any(type(prior.get(k)) is not int or prior[k]!=0 for k in ('supplier_calls','database_writes','mapping_writes'))):
        fail('retained_projection_parent_not_complete')
    raw=target/'retained-raw.ndjson'
    if not safe_file(raw,134217728) or raw.stat().st_nlink!=1:fail('retained_projection_raw')
    receipt_before=hashlib.sha256((target/'result.json').read_bytes()).hexdigest()
    env={k:os.environ[k] for k in ('HOME','PATH','LANG','LC_ALL') if k in os.environ}
    run=subprocess.run(['python3',str(stage/'scripts/diagnostics/hotel_match_retained_projection_v1.py'),str(raw)],cwd=project,env=env,capture_output=True,text=True,timeout=240)
    if run.returncode or run.stderr.strip() or len(run.stdout.encode())>32*1024*1024:fail('retained_saved_projection_failed')
    projected=json.loads(run.stdout)
    if (projected.get('state')!='projected_read_only' or projected.get('raw_sha256')!=capture.get('raw_sha256')
            or projected.get('raw_bytes')!=capture.get('raw_bytes')
            or projected.get('safe_to_write_now') is not False
            or any(projected.get('capture_rows',{}).get(k,0)!=capture.get('rows',{}).get(k) for k in ('observation','legacy_source','identity','target'))
            or hashlib.sha256((target/'result.json').read_bytes()).hexdigest()!=receipt_before):
        fail('retained_saved_projection_binding')
    data={'state':'reprojected_retained_read_only','target_operation_id':target_name,
        'parent_source_sha':reservation['source_sha'],'parent_receipt_sha256':receipt_before,
        'projection':projected,'operation_id':operation,'source_sha':source,
        'control_source_sha':payload['match_retained_control_sha'],'cohort':payload['cohort'],
        'supplier_calls':0,'database_writes':0,'mapping_writes':0,'safe_to_write_now':False,'no_replay':True}
    raw_result=json.dumps(data,sort_keys=True,separators=(',',':')).encode()
    fd=os.open(op/'retained-evidence.json',os.O_WRONLY|os.O_CREAT|os.O_EXCL|os.O_NOFOLLOW,0o600)
    with os.fdopen(fd,'wb') as handle:handle.write(raw_result);handle.flush();os.fsync(handle.fileno())
    return {'evidence_sha256':hashlib.sha256(raw_result).hexdigest(),'data':data}

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
    if mode=='match-retained-project':return project_retained_target(stage)
    if mode=='match-retained-current':return current_retained_target(stage)
    if mode=='match-retained-acquire':return acquire_retained_target(stage)
    if mode=='match-retained-anex-write':return write_retained_anex_target(stage)
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
        if mode=='match-retained-acquire':result['supplier_calls']=None
        if mode=='match-retained-anex-write':result['database_writes']=result['mapping_writes']=None
        result['match_retained']=run_match_retained(stage)
        if mode=='match-retained-anex-write':
            result['database_writes']=result['match_retained']['data']['database_writes']
            result['mapping_writes']=result['match_retained']['data']['mapping_writes']
        if mode=='match-retained-acquire':result['supplier_calls']=result['match_retained']['data']['supplier_calls']
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
            keys=('supplier_calls',) if command['mode']=='match-retained-anex-write' else ('database_writes','mapping_writes') if command['mode']=='match-retained-acquire' else ('supplier_calls','database_writes','mapping_writes')
            if any(type(obj.get(k)) is not int or obj[k] != 0 for k in keys):
                return False
        if command['mode'] in ('match-retained-readback','match-retained-project','match-retained-current','match-retained-acquire','match-retained-anex-write') and data.get('target_operation_id')!=command.get('target_operation_id'):
            return False
        if command['mode']=='match-retained-acquire' and (type(data.get('supplier_calls')) is not int or not 0<=data['supplier_calls']<=400 or result.get('supplier_calls')!=data['supplier_calls']):return False
        if command['mode']=='match-retained-anex-write':
            n=data.get('new_accepted_mappings')
            if type(n) is not int or not 0<=n<=7 or any(type(obj.get(k)) is not int or obj[k]!=n for obj in (data,result) for k in ('database_writes','mapping_writes')):return False
            if data.get('state')=='committed_verified' and (n<1 or len(data.get('rows',[]))!=n or data.get('readback_verified') is not True or data.get('resolver_readback_verified') is not True):return False
            if data.get('state') not in ('committed_verified','completed_no_eligible'):return False
            if data.get('state')=='completed_no_eligible' and n!=0:return False
        expected = {'match-retained-intake':'captured_and_projected_read_only',
                    'match-tourvisor-quota-policy':'quota_policy_applied_no_supplier_calls',
                    'match-retained-readback':'reconciled_read_only','match-retained-project':'reprojected_retained_read_only',
                    'match-retained-current':'current_retained_read_only',
                    'match-retained-acquire':'acquired_retained_native_read_only',
                    'match-retained-anex-write':data.get('state')}[command['mode']]
        return (data.get('state')==expected and result['match_retained'].get('evidence_sha256')==
                hashlib.sha256(json.dumps(data,sort_keys=True,separators=(',',':')).encode()).hexdigest())
    except (KeyError, TypeError, AttributeError):
        return False


def activate(core, command):
    if command.get('mode') not in MODES:
        return
    fields=('source_sha','mode','operation_id','cohort')+ (('target_operation_id',) if command.get('mode') in ('match-retained-readback','match-retained-project','match-retained-current','match-retained-acquire','match-retained-anex-write') else ())
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
    remote=remote.replace(anchors['collector'],"    if mode not in ('match-retained-intake','match-tourvisor-quota-policy','match-retained-readback','match-retained-project','match-retained-current','match-retained-acquire','match-retained-anex-write','reconcile',")
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
