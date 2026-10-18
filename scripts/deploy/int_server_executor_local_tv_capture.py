"""Exact permanent registration install and retained-only fill in the stock executor."""
from __future__ import annotations
import ast
import hashlib
import io
import json
import os
from pathlib import Path
import tarfile

MODE='local-tv-capture-v1'
OPERATIONS={
    'int-andromeda-local-tv-capture-inspect-20261010-v1':'inspect',
    'int-andromeda-local-tv-capture-install-20261010-v1':'install',
    'int-andromeda-local-tv-capture-retained-20261010-v1':'retained',
    'int-andromeda-local-tv-capture-readback-20261010-v1':'readback',
}
SOURCE_HASHES={
    'v2/api-v2.php':'445c421238c649b2dfb344f59b20c52f8be6418ded8de19b2d1c356bc710ccce',
    'v2/data/db-v1.php':'ca7f11d6ec53e0cd3d4464e0f645f15b761bb734da1323689479838868d4abc4',
    'v2/data/hotel-details-v1.php':'137bbf53ef87fab50505ecd41a075cb5f571193480da7f0e3face51190c0f77f',
    'v2/data/local-tv-catalog-v1.php':'b3f4f79d1bb54d723c5e9cbaf4b6e59375e66b9551d4e602a2f9b01c44886a46',
    'v2/data/price-observer-v1.php':'7e057905b1218815fc828c1c558f681eca67d079ad220ee3487b315661357843',
    'v2/data/collect-hotel-details-v1.php':'b653b3d174b5afbd22ba73398819fabd76d6a833783323b1845d64a2eb185113',
    'v2/data/tourvisor-client-v1.php':'f669434c2b0bf6af0a68684d02eea964173f2d3c0408e49b94db9606e8273a17',
}
REPAIR_OPERATION='int-andromeda-local-tv-repair-20261018-v1'
REPAIR_SOURCE='6bb834a586c231c3f06a6d6e918f8280d2b3f587'
REPAIR_TARGETS={
    'data/db-v1.php':'v2/data/db-v1.php',
    'data/price-observer-v1.php':'v2/data/price-observer-v1.php',
}
REPAIR_BAD={
    'data/db-v1.php':('c1c841d38c62845bc36e559183da17e0411c1c5bdd909517ffc02b5fbb1e2f13',3282),
    'data/price-observer-v1.php':('51a2f7e8cfbb4a9ceadf177bcc34f49a1fa731166e4526040398773bd49ebe66',8501),
}
REPAIR_GOOD_SIZES={'data/db-v1.php':3861,'data/price-observer-v1.php':9307}
REPAIR_GOOD_HASHES={p[3:]:sha for p,sha in SOURCE_HASHES.items()}
REPAIR_GOOD_HASHES['data/local-tv-catalog-enabled.json']='3c11725b4b9507427654398aac403177f338d7618b77c96d695582b4e49d3440'
CONTROL_FILES=tuple('scripts/diagnostics/'+p for p in ('local_tv_schema_v1.php','local_tv_seed_v1.php','local_tv_capture_v1.php'))
BUNDLE_FILES=(*SOURCE_HASHES,*CONTROL_FILES)

def need(value,reason):
    if not value:raise ValueError(reason)

def register_parser(core):
    original=core.parse_command
    def parse(body):
        if not body.startswith(core.PREFIX):return original(body)
        p=body[len(core.PREFIX):].split()
        if len(p)<2 or p[1]!=MODE:return original(body)
        need(len(p)==3 and (p[2] in OPERATIONS or p[2]==REPAIR_OPERATION),'capture_exact_operation')
        need(core.SHA_RE.fullmatch(p[0]) is not None,'source_sha')
        if p[2]==REPAIR_OPERATION:
            need(p[0]==REPAIR_SOURCE,'repair_exact_source')
            need(os.environ.get('GITHUB_RUN_ATTEMPT')=='1','repair_first_attempt_only')
        return {'source_sha':p[0],'mode':MODE,'operation_id':p[2],'action':OPERATIONS.get(p[2],'repair'),
                'provider_http_calls':0,'old_profile_writes':0,'mapping_writes':0,'schema_writes':0}
    core.parse_command=parse

def bundle_source(source_root):
    root=Path(source_root).resolve();control=Path(__file__).resolve().parents[2];out=io.BytesIO();hashes={}
    with tarfile.open(fileobj=out,mode='w:gz',format=tarfile.PAX_FORMAT) as archive:
        for relative in BUNDLE_FILES:
            path=(control if relative in CONTROL_FILES else root)/relative
            need(path.is_file() and not path.is_symlink() and path.resolve()==path,'capture_source_path')
            data=path.read_bytes();need(0<len(data)<=2*1024*1024,'capture_source_size');sha=hashlib.sha256(data).hexdigest()
            if relative in SOURCE_HASHES:need(sha==SOURCE_HASHES[relative],'capture_reviewed_source_changed')
            hashes[relative]=sha;info=tarfile.TarInfo(relative);info.size=len(data);info.mode=0o600;info.mtime=0
            archive.addfile(info,io.BytesIO(data))
        data=json.dumps({'schema_version':1,'files':hashes},sort_keys=True,separators=(',',':')).encode()
        info=tarfile.TarInfo('manifest.json');info.size=len(data);info.mode=0o600;info.mtime=0;archive.addfile(info,io.BytesIO(data))
    return out.getvalue(),hashes

REMOTE_HANDLER=r'''
def capture_write(path,data,mode=0o600,before_replace=None):
    if path.is_symlink() or path.parent.is_symlink() or path.parent.resolve()!=path.parent:fail('capture_write_path')
    temporary=path.with_name(path.name+'.'+operation+'.tmp')
    fd=os.open(temporary,os.O_WRONLY|os.O_CREAT|os.O_EXCL,mode)
    try:
        with os.fdopen(fd,'wb') as handle:handle.write(data);handle.flush();os.fsync(handle.fileno())
        if before_replace is not None:before_replace()
        os.replace(temporary,path)
        fd=os.open(path.parent,os.O_RDONLY)
        try:os.fsync(fd)
        finally:os.close(fd)
    finally:
        if temporary.exists():temporary.unlink()

def capture_files(paths):
    out={}
    for relative in paths:
        path=project/relative
        if path.is_symlink() or path.parent.is_symlink() or path.parent.resolve()!=path.parent:fail('capture_target_path')
        if path.exists() and not safe_file(path,2*1024*1024):fail('capture_target_file')
        out[relative]=hashlib.sha256(path.read_bytes()).hexdigest() if path.exists() else None
    return out

def capture_install_files(stage,targets,expected,flag):
    paths=[*targets,'data/local-tv-catalog-enabled.json']
    if capture_files(paths)!=expected:fail('capture_before_files_drift')
    backup=op/'backup';backup.mkdir(mode=0o700);saved={}
    for relative in paths:
        path=project/relative;saved[relative]=path.read_bytes() if path.exists() else None
        if saved[relative] is not None:capture_write(backup/relative.replace('/','__'),saved[relative])
    capture_write(op/'capture-install-started.json',json.dumps({'before':expected,'targets':targets},sort_keys=True).encode())
    changed=[]
    try:
        # Capture is enabled last, after every dependency and the exact API hook is ready.
        for relative,source_path in targets.items():
            data=(stage/source_path).read_bytes()
            if saved[relative]!=data:capture_write(project/relative,data,0o644);changed.append(relative)
        capture_write(project/'data/local-tv-catalog-enabled.json',flag,0o644);changed.append('data/local-tv-catalog-enabled.json')
        return changed
    except Exception:
        # A failed known file copy is reversible. A failed restoration is explicitly unknown.
        try:
            for relative in paths:
                if saved[relative] is None:(project/relative).unlink(missing_ok=True)
                else:capture_write(project/relative,saved[relative],0o644)
            if capture_files(paths)!=expected:raise RuntimeError('rollback_hash')
        except Exception:fail('capture_install_unknown_no_replay')
        fail('capture_install_rolled_back_no_replay')

def run_local_tv_capture(stage):
    if operation=='int-andromeda-local-tv-repair-20261018-v1':
        return run_local_tv_repair(stage)
    actions={'int-andromeda-local-tv-capture-inspect-20261010-v1':'inspect','int-andromeda-local-tv-capture-install-20261010-v1':'install',
        'int-andromeda-local-tv-capture-retained-20261010-v1':'retained','int-andromeda-local-tv-capture-readback-20261010-v1':'readback'}
    action=actions.get(operation)
    if (action is None or payload.get('action')!=action or any(type(payload.get(k)) is not int or payload[k]!=0
            for k in ('provider_http_calls','old_profile_writes','mapping_writes','schema_writes'))
            or not re.fullmatch(r'[a-f0-9]{40}',str(payload.get('local_tv_capture_control_sha','')))):fail('capture_scope')
    order=('db-v1.php','hotel-details-v1.php','local-tv-catalog-v1.php','tourvisor-client-v1.php','price-observer-v1.php','collect-hotel-details-v1.php')
    targets={'data/'+name:'v2/data/'+name for name in order};targets['api-v2.php']='v2/api-v2.php'
    flag=b'{"registry":true,"dailyHttpBudget":0}\n';paths=[*targets,'data/local-tv-catalog-enabled.json']
    expected={relative:files[src] for relative,src in targets.items()};expected['data/local-tv-catalog-enabled.json']=hashlib.sha256(flag).hexdigest()
    env={k:os.environ[k] for k in ('PATH','HOME','LANG','LC_ALL') if k in os.environ}
    env.update({'ANYTOUR_ROOT':str(project),'LOCAL_TV_CAPTURE_SOURCE_ROOT':str(stage),'LOCAL_TV_CAPTURE_DIR':str(op)})
    disabled='curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,socket_connect,exec,shell_exec,system,passthru,popen,proc_open'
    options=['php','-d','display_errors=0','-d','log_errors=0','-d','allow_url_fopen=0','-d','memory_limit=512M','-d','disable_functions='+disabled]
    def snapshot(name):
        run=subprocess.run([*options,str(stage/'scripts/diagnostics/local_tv_capture_v1.php'),name],cwd=project,env=env,capture_output=True,text=True,timeout=600)
        if run.returncode or run.stderr.strip() or not safe_file(op/name,4*1024*1024):fail('capture_snapshot_failed')
        full=safe_json(op/name,4*1024*1024);summary={k:v for k,v in full.items() if k!='catalog_rows'}
        if json.loads(run.stdout.strip())!=summary:fail('capture_snapshot_stdout')
        return full,summary
    def enabled():
        code='require $argv[1]."/data/db-v1.php"; require $argv[1]."/data/local-tv-catalog-v1.php"; v2_data_db_config(); echo LocalTvCatalogV1::enabled()?"enabled":"disabled";'
        run=subprocess.run([*options,'-r',code,str(project)],cwd=project,env=env,capture_output=True,text=True,timeout=30)
        if run.returncode or run.stderr.strip() or run.stdout!='enabled':fail('capture_live_flag_probe')
    before_files=capture_files(paths);before_full,before_data=snapshot('capture-before.json')
    data={'schema_version':1,'action':action,'source_sha':source,'control_source_sha':payload['local_tv_capture_control_sha'],
        'operation_id':operation,'state':'blocked','supplier_calls':0,'old_profile_writes':0,'mapping_writes':0,'schema_writes':0,
        'database_writes':0,'file_writes':0,'observed_at':int(time.time()),'before_files':before_files,'before_data':before_data}
    result['local_tv_capture']=data
    if action=='inspect':
        if before_files['api-v2.php']!='5716bb07e0aced65a36b3ab155e7dd5088cced764b771bdbc5ad924651c5d529':fail('capture_api_base_not_reviewed')
        if before_files['data/local-tv-catalog-enabled.json'] is not None:fail('capture_flag_already_present')
        data['state']='inspected_read_only';return data
    parent=private/'int-andromeda-local-tv-capture-inspect-20261010-v1'
    if not safe_file(parent/'result.json',65536):fail('capture_inspection_missing')
    previous=safe_json(parent/'result.json',65536);prior=previous.get('local_tv_capture',{})
    if (previous.get('status')!='complete' or previous.get('mode')!='local-tv-capture-v1'
            or prior.get('state')!='inspected_read_only' or not re.fullmatch(r'[a-f0-9]{40}',str(prior.get('source_sha','')))):fail('capture_inspection_contract')
    if action=='install':
        if prior['source_sha']!=source:fail('capture_install_source_changed')
        if prior.get('control_source_sha')!=payload['local_tv_capture_control_sha'] or not 0<=int(time.time())-prior['observed_at']<=1800:fail('capture_inspection_expired')
        if before_files!=prior['before_files'] or before_data!=prior['before_data']:fail('capture_inspection_drift')
        # Block a concurrent installed data CLI before copying its dependencies.
        names={'collect-hotel-details-v1.php','top500-daily-collector-v1.php','scheduled-collector-v1.php','matrix-collector-v1.php','sync-priority-hotels-v1.php','build-seo-offer-snapshots-v1.php'}
        for process in pathlib.Path('/proc').glob('[0-9]*/cmdline'):
            try:args=process.read_bytes().split(b'\0')
            except (FileNotFoundError,PermissionError):continue
            if args and pathlib.Path(os.fsdecode(args[0])).name.startswith('php') and any(os.fsdecode(arg)==str(project/'data'/name) for arg in args[1:] for name in names):fail('capture_data_collector_active')
        data['file_writes']=None
        data['changed_files']=capture_install_files(stage,targets,prior['before_files'],flag)
        data['file_writes']=len(data['changed_files']);enabled();data['state']='installed_capture'
    else:
        if before_files!=expected:fail('capture_installed_files_drift')
        installed=private/'int-andromeda-local-tv-capture-install-20261010-v1'
        if not safe_file(installed/'result.json',65536):fail('capture_install_terminal_missing')
        terminal=safe_json(installed/'result.json',65536)
        installed_data=terminal.get('local_tv_capture',{})
        # A document-only ref advance must not require another installation.
        # Current bundle hashes and all installed files stay exact; the original
        # inspect/install receipt chain still binds its own source and control.
        if (terminal.get('status')!='complete' or terminal.get('mode')!='local-tv-capture-v1'
                or installed_data.get('state')!='installed_capture' or installed_data.get('source_sha')!=prior['source_sha']
                or installed_data.get('control_source_sha')!=prior.get('control_source_sha')
                or installed_data.get('before_files')!=prior.get('before_files')
                or installed_data.get('before_data')!=prior.get('before_data')
                or installed_data.get('after_files')!=expected):fail('capture_install_not_complete')
        installed_after=installed_data.get('after_data',{})
        if any(before_data.get(k)!=installed_after.get(k) for k in ('config_sha256','database_sha256','protected_snapshots')):fail('capture_original_protected_data_drift')
        data['installed_source_sha']=prior['source_sha']
        enabled()
        if action=='retained':
            data['database_writes']=None
            capture_write(op/'capture-retained-started.json',json.dumps({'http_budget':0,'limit':3000}).encode())
            run=subprocess.run([*options,str(project/'data/collect-hotel-details-v1.php'),'--candidate-scope=local','--http-budget=0','--limit=3000','--max-attempts=1'],cwd=project,env=env,capture_output=True,text=True,timeout=900)
            if run.returncode or run.stderr.strip() or not run.stdout.startswith('ANYTOUR_LOCAL_TV_DAILY '):fail('capture_retained_unknown_no_replay')
            report=json.loads(run.stdout[len('ANYTOUR_LOCAL_TV_DAILY '):].strip())
            if report.get('supplierHttpAttempts')!=0 or report.get('httpRequests')!=0 or report.get('legacyMigration')!={'transferred':0,'issues':[]}:fail('capture_retained_contract')
            data['retained_report']=report;data['state']='retained_complete'
        else:data['state']='verified_read_only'
    after_full,after_data=snapshot('capture-after.json');data['after_data']=after_data;data['after_files']=capture_files(paths)
    if data['after_files']!=expected:fail('capture_installed_readback')
    if after_data['config_sha256']!=before_data['config_sha256'] or after_data['database_sha256']!=before_data['database_sha256'] or after_data['protected_snapshots']!=before_data['protected_snapshots'] or after_data['readback']['links_sha256']!=before_data['readback']['links_sha256']:fail('capture_protected_data_drift')
    if action=='retained':
        rows_before=before_full['catalog_rows'];rows_after=after_full['catalog_rows']
        data['database_writes']=sum(rows_before.get(k)!=rows_after.get(k) for k in set(rows_before)|set(rows_after))
    if action=='readback':
        retained=private/'int-andromeda-local-tv-capture-retained-20261010-v1'
        if not safe_file(retained/'result.json',65536):fail('capture_retained_terminal_missing')
        retained_terminal=safe_json(retained/'result.json',65536);retained_data=retained_terminal.get('local_tv_capture',{})
        if (retained_terminal.get('status')!='complete' or retained_terminal.get('mode')!='local-tv-capture-v1'
                or retained_data.get('state')!='retained_complete' or retained_data.get('installed_source_sha')!=prior['source_sha']
                or retained_data.get('after_files')!=expected):fail('capture_retained_terminal_missing')
    data['registry_enabled']=True;return data

'''
REMOTE_DISPATCH=r'''    if mode=='local-tv-capture-v1':
        try:data=run_local_tv_capture(stage)
        except Exception as error:
            data=result.get('local_tv_capture',{})
            result['supplier_calls']=0;result['database_writes']=data.get('database_writes',0)
            if data.get('database_writes') is None or data.get('file_writes') is None:
                data['state']='unknown_no_replay'
            if str(error)=='capture_install_rolled_back_no_replay':data['file_writes']=0;data['state']='rolled_back_no_replay'
            raise
        result['supplier_calls']=0;result['database_writes']=data['database_writes']
        result['profile_writes']=result['mapping_writes']=result['schema_writes']=0
        result['production_after']=fingerprints()
        if result['production_after']!=before:fail('production_drift')
        result['production_unchanged']=True;result['status']='complete'
'''

def activate(core,command):
    if command.get('mode')!=MODE:return
    expected=core.parse_command(core.PREFIX+' '.join([str(command.get('source_sha','')),MODE,str(command.get('operation_id',''))]))
    need(command==expected and all(type(command.get(k)) is int for k in ('provider_http_calls','old_profile_writes','mapping_writes','schema_writes')),'capture_authorized_shape')
    remote=core.REMOTE;definition='def run_match942(stage, mode, offset, limit):\n';dispatch="    if mode=='match-tv942-write':\n";collector="    if mode not in ('reconcile',";manifest="    if not isinstance(files,dict) or len(files)<20: fail('manifest')"
    need(remote.count(definition)==1 and remote.count(dispatch)==1 and remote.count(collector)==2 and remote.count(manifest)==1,'capture_registration_drift')
    repair=command['operation_id']==REPAIR_OPERATION
    handler=(repair_remote() if repair else '')+REMOTE_HANDLER
    remote=remote.replace(definition,handler+definition,1).replace(dispatch,REMOTE_DISPATCH+dispatch,1).replace(collector,"    if mode not in ('"+MODE+"','reconcile',")
    bundle_files=tuple(REPAIR_TARGETS.values()) if repair else BUNDLE_FILES
    literal='{'+', '.join(repr(v) for v in sorted(bundle_files))+'}'
    remote=remote.replace(manifest,"    if not isinstance(files,dict) or set(files)!="+literal+": fail('capture_manifest')",1)
    if repair:
        reservation='    op.mkdir(mode=0o700)\n'
        receipt="if 'op' in globals() and op.exists():"
        need(remote.count(reservation)==1 and remote.count(receipt)==1,'repair_ledger_registration_drift')
        # A rejected replay must preserve the original private receipt. This
        # guard exists only in the new repair's remote program.
        remote=remote.replace(reservation,reservation+'    repair_operation_reserved=True\n',1)
        remote=remote.replace(receipt,"if globals().get('repair_operation_reserved') is True and op.exists():",1)
    ast.parse(remote);core.REMOTE=remote;core.bundle_source=bundle_repair_source if repair else bundle_source
    if repair:
        original=core.completed_result
        def completed(cmd,result):
            if cmd.get('operation_id')==REPAIR_OPERATION:
                return repair_completed_result(cmd,result,os.environ.get('GITHUB_SHA',''))
            return original(cmd,result)
        core.completed_result=completed


def bundle_repair_source(source_root):
    """Only the two reviewed bytes; historical capture bundle/pins stay unchanged."""
    root=Path(source_root).resolve();out=io.BytesIO();hashes={}
    with tarfile.open(fileobj=out,mode='w:gz',format=tarfile.PAX_FORMAT) as archive:
        for relative in REPAIR_TARGETS.values():
            path=root/relative
            need(path.is_file() and not path.is_symlink() and path.resolve()==path
                 and path.stat().st_nlink==1,'repair_source_path')
            data=path.read_bytes();sha=hashlib.sha256(data).hexdigest()
            need(sha==SOURCE_HASHES[relative],'repair_reviewed_source_changed')
            hashes[relative]=sha;info=tarfile.TarInfo(relative)
            info.size=len(data);info.mode=0o600;info.mtime=0
            archive.addfile(info,io.BytesIO(data))
        data=json.dumps({'schema_version':1,'files':hashes},sort_keys=True,separators=(',',':')).encode()
        info=tarfile.TarInfo('manifest.json');info.size=len(data);info.mode=0o600;info.mtime=0
        archive.addfile(info,io.BytesIO(data))
    return out.getvalue(),hashes


def repair_remote():
    policy={'operation':REPAIR_OPERATION,'source':REPAIR_SOURCE,'targets':REPAIR_TARGETS,
            'bad':REPAIR_BAD,'good_sizes':REPAIR_GOOD_SIZES,'good':REPAIR_GOOD_HASHES}
    return 'REPAIR_POLICY='+repr(policy)+'\n'+REMOTE_REPAIR


REMOTE_REPAIR=r'''
def repair_read(relative):
    import stat
    if relative not in REPAIR_POLICY['good']:fail('repair_read_scope')
    path=project/relative;parent=path.parent
    if parent.is_symlink() or parent.resolve()!=parent:fail('repair_parent_path')
    def identity(item):
        return (item.st_dev,item.st_ino,item.st_mode,item.st_nlink,item.st_size,item.st_mtime_ns,item.st_ctime_ns)
    before=path.lstat()
    if not stat.S_ISREG(before.st_mode) or before.st_nlink!=1 or not 0<before.st_size<=2*1024*1024:
        fail('repair_file_metadata')
    fd=os.open(path,os.O_RDONLY|os.O_NOFOLLOW|os.O_NONBLOCK)
    try:
        if identity(os.fstat(fd))!=identity(before):fail('repair_open_drift')
        chunks=[];size=0
        while True:
            block=os.read(fd,65536)
            if not block:break
            chunks.append(block);size+=len(block)
            if size>2*1024*1024:fail('repair_file_size')
        if identity(os.fstat(fd))!=identity(before):fail('repair_read_drift')
    finally:os.close(fd)
    if identity(path.lstat())!=identity(before):fail('repair_path_drift')
    data=b''.join(chunks)
    return data,{'sha256':hashlib.sha256(data).hexdigest(),'bytes':len(data),'mode':stat.S_IMODE(before.st_mode)}

def repair_inventory():
    return {relative:repair_read(relative)[1] for relative in REPAIR_POLICY['good']}

def repair_replace(path,data,expected):
    # This one-use Python process is single-threaded. Preserve the exact reviewed
    # 0644 mode even when the SSH account has a more restrictive default umask.
    relative=str(path.relative_to(project))
    def unchanged():
        if repair_read(relative)[1]!=expected:fail('repair_replace_target_drift')
    previous=os.umask(0o022)
    try:capture_write(path,data,0o644,before_replace=unchanged)
    finally:os.umask(previous)

def repair_before_valid(inventory):
    if set(inventory)!=set(REPAIR_POLICY['good']):fail('repair_inventory_scope')
    for relative,record in inventory.items():
        if record.get('mode')!=0o644:fail('repair_file_mode')
        good=REPAIR_POLICY['good'][relative]
        allowed={good}
        if relative in REPAIR_POLICY['targets']:
            bad,size=REPAIR_POLICY['bad'][relative];allowed.add(bad)
            expected_size=REPAIR_POLICY['good_sizes'][relative] if record['sha256']==good else size
            if record['bytes']!=expected_size:fail('repair_target_size')
        if record.get('sha256') not in allowed:fail('repair_unreviewed_bytes')

def repair_assert_idle():
    data_prefix=str(project/'data')+'/'
    for process in pathlib.Path('/proc').glob('[0-9]*/cmdline'):
        try:args=[os.fsdecode(p) for p in process.read_bytes().split(b'\0') if p]
        except FileNotFoundError:continue
        except PermissionError:
            # Unreadable foreign processes cannot be treated as an idle writer slot.
            fail('repair_process_visibility')
        if not args:continue
        executable=os.path.basename(args[0])
        if executable.startswith('php') and any(a.startswith(data_prefix) for a in args[1:]):
            fail('repair_data_writer_active')
        if executable in ('scp','sftp-server','rsync') and any(data_prefix.rstrip('/') in a for a in args[1:]):
            fail('repair_data_writer_active')

def run_local_tv_repair(stage):
    if (operation!=REPAIR_POLICY['operation'] or source!=REPAIR_POLICY['source']
            or payload.get('action')!='repair'
            or any(type(payload.get(k)) is not int or payload[k]!=0
                   for k in ('provider_http_calls','old_profile_writes','mapping_writes','schema_writes'))
            or not re.fullmatch(r'[a-f0-9]{40}',str(payload.get('local_tv_capture_control_sha','')))):
        fail('repair_scope')
    data={'schema_version':1,'action':'repair','operation_id':operation,'source_sha':source,
          'control_source_sha':payload['local_tv_capture_control_sha'],'observed_at':int(time.time()),
          'state':'blocked','supplier_calls':0,'database_writes':0,'old_profile_writes':0,
          'mapping_writes':0,'schema_writes':0,'file_writes':0,'root_write_attempts':0,
          'rollback_attempts':0,'changed_files':[],'before_files':{},'after_files':{}}
    result['local_tv_capture']=data
    before=repair_inventory();data['before_files']=before;repair_before_valid(before)
    repair_assert_idle()
    targets=REPAIR_POLICY['targets'];saved={};replacement={}
    for relative,src in targets.items():
        content,record=repair_read(relative)
        if record!=before[relative]:fail('repair_before_files_drift')
        source_path=stage/src
        if (not source_path.is_file() or source_path.is_symlink() or source_path.resolve()!=source_path
                or source_path.stat().st_nlink!=1):fail('repair_source_path')
        content_new=source_path.read_bytes()
        if (hashlib.sha256(content_new).hexdigest()!=REPAIR_POLICY['good'][relative]
                or len(content_new)!=REPAIR_POLICY['good_sizes'][relative]):fail('repair_source_hash')
        saved[relative]=content;replacement[relative]=content_new
    if repair_inventory()!=before:fail('repair_before_files_drift')
    changed=[p for p in targets if before[p]['sha256']!=REPAIR_POLICY['good'][p]]
    if not changed:
        data['after_files']=before;data['state']='already_correct';return data
    backup=op/'repair-backup';backup.mkdir(mode=0o700)
    for relative in changed:
        capture_write(backup/relative.replace('/','__'),saved[relative])
    capture_write(op/'repair-started.json',json.dumps({'before_files':before,'targets':changed,
        'source_sha':source,'control_source_sha':data['control_source_sha']},sort_keys=True).encode())
    expected=dict(before);attempted=[]
    try:
        for relative in changed:
            repair_assert_idle()
            if repair_inventory()!=expected:fail('repair_before_files_drift')
            # Mark an attempt before replace: fsync may fail after the inode was replaced.
            attempted.append(relative);data['root_write_attempts']+=1
            repair_replace(project/relative,replacement[relative],expected[relative])
            expected[relative]={'sha256':REPAIR_POLICY['good'][relative],
                'bytes':len(replacement[relative]),'mode':0o644}
            if repair_inventory()!=expected:fail('repair_after_file_drift')
        after=repair_inventory();data['after_files']=after
        if after!=expected or any(after[p]['sha256']!=sha for p,sha in REPAIR_POLICY['good'].items()):
            fail('repair_final_readback')
        data['changed_files']=changed;data['file_writes']=len(changed)
        data['state']='repaired_files_only';return data
    except Exception:
        data['file_writes']=None;data['state']='unknown_no_replay'
        try:
            for relative in reversed(attempted):
                _,current=repair_read(relative)
                allowed={before[relative]['sha256'],REPAIR_POLICY['good'][relative]}
                if current['sha256'] not in allowed:fail('repair_foreign_bytes_during_rollback')
                if current!=before[relative]:
                    data['rollback_attempts']+=1
                    repair_replace(project/relative,saved[relative],current)
            after=repair_inventory();data['after_files']=after
            if after!=before:fail('repair_rollback_readback')
        except Exception:fail('repair_unknown_no_replay')
        # file_writes counts final changed files; attempts remain explicit, including rollback.
        data['file_writes']=0;data['state']='rolled_back_no_replay'
        fail('repair_rolled_back_no_replay')
'''


def repair_completed_result(command,result,control_sha):
    """Do not let generic stock 'complete' accept an incomplete repair receipt."""
    try:
        if (command.get('mode')!=MODE or command.get('operation_id')!=REPAIR_OPERATION
                or command.get('source_sha')!=REPAIR_SOURCE or command.get('action')!='repair'
                or any(type(command.get(k)) is not int or command[k]!=0
                       for k in ('provider_http_calls','old_profile_writes','mapping_writes','schema_writes'))
                or result.get('status')!='complete' or type(result.get('schema_version')) is not int
                or result['schema_version']!=1
                or any(result.get(k)!=command.get(k) for k in ('mode','operation_id','source_sha'))):
            return False
        if any(type(result.get(k)) is not int or result[k]!=0
               for k in ('supplier_calls','database_writes','booking_calls','lead_calls',
                         'profile_writes','mapping_writes','schema_writes')):return False
        protected={'index.php','v2/index.php','v2/api-v2.php','v2/lead-adapter-v2.php'}
        if (not isinstance(result.get('production_before'),dict)
                or set(result['production_before'])!=protected
                or result['production_before']!=result.get('production_after')
                or result.get('production_unchanged') is not True):return False
        data=result.get('local_tv_capture',{})
        if (not isinstance(data,dict)
                or type(data.get('schema_version')) is not int or data['schema_version']!=1
                or data.get('action')!='repair' or data.get('operation_id')!=REPAIR_OPERATION
                or data.get('source_sha')!=REPAIR_SOURCE
                or data.get('control_source_sha')!=control_sha
                or not isinstance(control_sha,str) or len(control_sha)!=40
                or not set(control_sha)<=set('0123456789abcdef')):return False
        for key in ('supplier_calls','database_writes','old_profile_writes','mapping_writes','schema_writes'):
            if type(data.get(key)) is not int or data[key]!=0:return False
        before=data.get('before_files');after=data.get('after_files')
        if not isinstance(before,dict) or not isinstance(after,dict) or set(before)!=set(REPAIR_GOOD_HASHES) or set(after)!=set(before):
            return False
        for relative,good in REPAIR_GOOD_HASHES.items():
            a=before[relative];b=after[relative]
            if any(not isinstance(x,dict) or set(x)!={'sha256','bytes','mode'}
                   or type(x['bytes']) is not int or not 0<x['bytes']<=2*1024*1024
                   or type(x['mode']) is not int or x['mode']!=0o644 for x in (a,b)):
                return False
            if b['sha256']!=good:return False
            if relative in REPAIR_TARGETS:
                bad,size=REPAIR_BAD[relative]
                if (a['sha256'] not in (good,bad)
                        or a['bytes']!=(REPAIR_GOOD_SIZES[relative] if a['sha256']==good else size)
                        or b['bytes']!=REPAIR_GOOD_SIZES[relative]):return False
            elif a!=b:return False
        changed=[p for p in REPAIR_TARGETS if before[p]['sha256']!=REPAIR_GOOD_HASHES[p]]
        return (data.get('changed_files')==changed
                and type(data.get('file_writes')) is int and data['file_writes']==len(changed)
                and type(data.get('root_write_attempts')) is int and data['root_write_attempts']==len(changed)
                and type(data.get('rollback_attempts')) is int and data['rollback_attempts']==0
                and data.get('state')==('repaired_files_only' if changed else 'already_correct'))
    except (AttributeError,KeyError,TypeError,ValueError):
        return False
