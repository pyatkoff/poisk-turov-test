"""Fixed, read-only passive-fuel inventory inside the permanent INT carrier.

No application/config execution, database connection, supplier request or install.
Only the reviewed five-file release bundle and exact installed dependencies apply.
"""
from __future__ import annotations

import ast
import hashlib
import io
import json
from pathlib import Path
import re
import tarfile

MODE = 'passive-fuel-readiness'
SOURCE = '670245c83b8fd47429a51a5d079e5106125476f9'
OPERATION = 'int-anex-passive-fuel-readiness-20261020-v1'
# A distinct, previously uninspected file closure. The ROOT operation above stays sealed.
PRIVATE_MODE = 'passive-fuel-private-readiness'
PRIVATE_SOURCE = '266848221cc78b1299c675171d7fe53fbb7e0cce'
PRIVATE_OPERATION = 'int-anex-passive-fuel-private-readiness-20261020-v1'
MODES = (MODE, PRIVATE_MODE)
SOURCE_CHECK_COMMIT = '990eb5859e1c8355337eb90351c655b604df8b39'
CHECK_IDS = {118651685241, 118651684803, 118651684153, 118651683778, 118651682993}
PRIVATE_SOURCE_CHECK_COMMIT = '65a802303afc3bedd6a8b674b9f5a9579ab4a38a'
PRIVATE_CHECK_IDS = {118696895774, 118696894874, 118696893621, 118696893334, 118696892718}
PROTECTED = ('api-v2.php','data/db-v1.php','data/collect-hotel-details-v1.php',
    'data/hotel-details-v1.php','data/local-tv-catalog-enabled.json','data/local-tv-catalog-v1.php',
    'data/price-observer-v1.php','data/tourvisor-client-v1.php','index.php','v2/index.php',
    'v2/api-v2.php','config.php','_preview/search3-next-candidate/visual-search/app.js',
    '_preview/search3-design-v2-candidate/visual-search/app.js')
SOURCE_HASHES = {
    'v2/api-v2.php': 'b87733d4f604f404c25f8d127c6b5949f6e0d42dcb3120c0e3c02df1793aa468',
    'app/integrations/tourvisor-operator-fuel-passive-intake.php': 'da89e7e5dc7816419140b716cb965462542ebc2ff3f42b4ec96dc623d823fa8d',
    'app/integrations/tourvisor-operator-fuel-retained-intake.php': 'a086f4d109b8d5e521311fd23a4047b5bf548cc41469e9b64370d740518adc0b',
    'app/integrations/operator-fuel-rule-evidence.php': 'cecd23217cc5e74b81f6c2ecd22db5813770edde3e861be888026393693ae1f2',
    'app/integrations/operator-fuel-rule-store.php': '774c5bc01e213c614644b269b111593967bd82dc2ea120a15175df846bcd386d',
}
PRIVATE_SOURCE_HASHES = {**SOURCE_HASHES,
    'v2/api-v2.php': '04cddd8c112bcdd70438a2e58e4935e1eae115057b218780f2f845bbfd83fd15'}


def need(value, reason):
    if not value:
        raise ValueError(reason)


def parse(parts):
    need(parts in ([SOURCE, MODE, OPERATION], [PRIVATE_SOURCE, PRIVATE_MODE, PRIVATE_OPERATION]),
         'passive_readiness_fixed_scope')
    return dict(source_sha=parts[0], mode=parts[1], operation_id=parts[2])


def policy(command=None):
    command = parse([SOURCE, MODE, OPERATION]) if command is None else command
    need(command == parse([command.get('source_sha'), command.get('mode'), command.get('operation_id')]),
         'passive_readiness_fixed_scope')
    return command


def admission(api_get, event, token, env, repo, issue, prefix, command=None):
    command = policy(command)
    need(event.get('action') == 'created' and event.get('repository', {}).get('full_name') == repo
         and env.get('GITHUB_EVENT_NAME') == 'issue_comment'
         and env.get('GITHUB_REPOSITORY') == repo and env.get('GITHUB_REF') == 'refs/heads/main'
         and env.get('GITHUB_WORKFLOW_REF') == repo + '/.github/workflows/int-server-executor.yml@refs/heads/main'
         and env.get('GITHUB_ACTOR') == env.get('GITHUB_TRIGGERING_ACTOR') == 'pyatkoff'
         and env.get('GITHUB_RUN_ATTEMPT') == '1', 'passive_readiness_event')
    private = command['mode'] == PRIVATE_MODE
    check_commit = PRIVATE_SOURCE_CHECK_COMMIT if private else SOURCE_CHECK_COMMIT
    check_ids = PRIVATE_CHECK_IDS if private else CHECK_IDS
    checks = api_get('/commits/' + check_commit + '/check-runs?per_page=100', token)
    need(checks.get('total_count') == len(check_ids)
         and {c.get('id') for c in checks.get('check_runs', [])} == check_ids
         and all(c.get('status') == 'completed' and c.get('conclusion') == 'success'
                 and c.get('app', {}).get('slug') == 'github-actions' for c in checks['check_runs']),
         'passive_readiness_source_ci')
    matched = []
    for page in range(1, 31):
        comments = api_get(f'/issues/{issue}/comments?per_page=100&page={page}', token)
        need(isinstance(comments, list), 'passive_readiness_comments')
        for item in comments:
            body = item.get('body', '')
            if body.startswith(prefix) and command['mode'] in body[len(prefix):].split()[1:2]:
                matched.append(item.get('id'))
        if len(comments) < 100:
            need(matched == [event['comment']['id']], 'passive_readiness_no_replay')
            return
    raise ValueError('passive_readiness_history_incomplete')


def bundle_source(source_root, command=None):
    command = policy(command)
    hashes = PRIVATE_SOURCE_HASHES if command['mode'] == PRIVATE_MODE else SOURCE_HASHES
    root = Path(source_root).resolve()
    output = io.BytesIO()
    with tarfile.open(fileobj=output, mode='w:gz', format=tarfile.PAX_FORMAT) as archive:
        for relative, expected in hashes.items():
            path = root / relative
            need(path.resolve() == path and path.is_file() and not path.is_symlink(), 'passive_source_path')
            data = path.read_bytes()
            need(0 < len(data) <= 65536 and hashlib.sha256(data).hexdigest() == expected,
                 'passive_source_hash')
            info = tarfile.TarInfo(relative)
            info.size = len(data); info.mode = 0o600; info.mtime = 0
            archive.addfile(info, io.BytesIO(data))
        data = json.dumps({'schema_version': 1, 'files': hashes}, sort_keys=True, separators=(',', ':')).encode()
        info = tarfile.TarInfo('manifest.json')
        info.size = len(data); info.mode = 0o600; info.mtime = 0
        archive.addfile(info, io.BytesIO(data))
    return output.getvalue(), dict(hashes)


REMOTE_READER = r'''
import stat as pf_stat
PF_EXPECTED=__PF_EXPECTED__
PF_PREDECESSOR='01ac5ed43961f73879cd36c2c1d387ac79fadaf53ad66d3c4aaebcc617464c61'
PF_DB='ca7f11d6ec53e0cd3d4464e0f645f15b761bb734da1323689479838868d4abc4'
PF_PROTECTED=__PF_PROTECTED__

def pf_identity(s):
    return (s.st_dev,s.st_ino,s.st_mode,s.st_nlink,s.st_size,s.st_mtime_ns,s.st_ctime_ns)

def pf_read(path,optional=True,limit=2*1024*1024):
    # Walk fixed paths from the account's www directory using no-follow descriptors.
    anchor=home/'www'
    try: parts=path.relative_to(anchor).parts
    except ValueError: fail('passive_path_scope')
    if not parts or '..' in parts: fail('passive_path_scope')
    descriptor=os.open(anchor,os.O_RDONLY|os.O_DIRECTORY|os.O_NOFOLLOW)
    try:
        for part in parts[:-1]:
            try: child=os.open(part,os.O_RDONLY|os.O_DIRECTORY|os.O_NOFOLLOW,dir_fd=descriptor)
            except FileNotFoundError:
                if optional:return None,None
                raise
            os.close(descriptor);descriptor=child
        try: leaf=os.open(parts[-1],os.O_RDONLY|os.O_NOFOLLOW|os.O_NONBLOCK,dir_fd=descriptor)
        except FileNotFoundError:
            if optional:return None,None
            raise
        try:
            before=os.fstat(leaf)
            if not pf_stat.S_ISREG(before.st_mode) or before.st_nlink!=1 or not 0<before.st_size<=limit:
                fail('passive_file_shape')
            data=b''
            while len(data)<=limit:
                chunk=os.read(leaf,min(65536,limit+1-len(data)))
                if not chunk:break
                data+=chunk
            after=os.fstat(leaf)
            entry=os.stat(parts[-1],dir_fd=descriptor,follow_symlinks=False)
            if len(data)!=before.st_size or pf_identity(before)!=pf_identity(after) or pf_identity(after)!=pf_identity(entry):
                fail('passive_file_changed')
            if path.resolve()!=path or pf_identity(path.lstat())!=pf_identity(after):fail('passive_file_changed')
            return data,{'sha256':hashlib.sha256(data).hexdigest(),'bytes':len(data),'mode':pf_stat.S_IMODE(after.st_mode)}
        finally:os.close(leaf)
    finally:os.close(descriptor)

def pf_snapshot():
    records={};bodies={}
    for relative in PF_PROTECTED:
        bodies[relative],records[relative]=pf_read(project/relative)
    api=bodies['api-v2.php'] or b''
    parent_bound=b"require_once dirname(__DIR__) . '/app/integrations/tourvisor-anytour-offer-autosave.php';" in api
    for layout,base in (('root',project),('parent',project.parent)):
        for relative in PF_EXPECTED:
            if not relative.startswith('app/integrations/'):continue
            key=layout+':'+relative
            if layout=='parent' and not parent_bound:bodies[key],records[key]=None,None
            else:bodies[key],records[key]=pf_read(base/relative,limit=65536)
    bodies['private_config'],records['private_config']=pf_read(project/'.andromeda-private.php',limit=65536)
    return bodies,records

def pf_catalog_literal(text):
    literal=re.fullmatch(r'\s*<\?php\s*(?:declare\s*\(\s*strict_types\s*=\s*1\s*\)\s*;\s*)?return\s*(\[.*\])\s*;\s*',text,re.S)
    if literal is None:raise ValueError('literal')
    pattern=re.compile(r"""\s*(=>|\[|\]|,|'(?:\\.|[^'\\])*'|"(?:\\.|[^"\\])*"|-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?|true|false|null)""")
    tokens=[];position=0;raw=literal[1]
    while position<len(raw):
        match=pattern.match(raw,position)
        if match is None or len(tokens)>=4096:raise ValueError('tokens')
        tokens.append(match[1]);position=match.end()
    cursor=0
    def value(depth=0):
        nonlocal cursor
        if depth>8 or cursor>=len(tokens):raise ValueError('depth')
        token=tokens[cursor];cursor+=1
        if token=='[':
            entries=[]
            while cursor<len(tokens) and tokens[cursor]!=']':
                first=value(depth+1);key=None
                if cursor<len(tokens) and tokens[cursor]=='=>':
                    cursor+=1;key=first;first=value(depth+1)
                    if not isinstance(key,str):raise ValueError('key')
                entries.append((key,first))
                if cursor<len(tokens) and tokens[cursor]==',':cursor+=1
                elif cursor>=len(tokens) or tokens[cursor]!=']':raise ValueError('separator')
            if cursor>=len(tokens):raise ValueError('array')
            cursor+=1;return entries
        if token[:1] in ("'",'"'):
            result=token[1:-1]
            if '\\' in result or (token[0]=='"' and '$' in result):raise ValueError('string')
            return result
        if token in ('true','false','null'):return {'true':True,'false':False,'null':None}[token]
        if re.fullmatch(r'-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?',token):return float(token)
        raise ValueError('value')
    entries=value()
    if cursor!=len(tokens) or not isinstance(entries,list):raise ValueError('trailing')
    candidates=[v for k,v in entries if k=='catalog_path']
    if len(candidates)!=1 or not isinstance(candidates[0],str):raise ValueError('catalog')
    return candidates[0]

def pf_binding(body):
    if body is None:return False,'missing',None
    # Never execute a PHP config. Only an unambiguous literal binding is understood.
    try:text=body.decode('utf-8')
    except UnicodeDecodeError:return False,'unrecognized',None
    try:catalog=pathlib.Path(pf_catalog_literal(text))
    except ValueError:return False,'unrecognized',None
    if not catalog.is_absolute() or '..' in catalog.parts or project not in catalog.parents:
        return False,'outside_scope',None
    directory=catalog.parent/'searches'
    if directory.resolve()!=directory or not directory.is_dir() or directory.is_symlink():
        return False,'store_unavailable',None
    # Directory identity only: never list/read observations or return private paths.
    first=directory.stat()
    second=directory.stat()
    if pf_identity(first)!=pf_identity(second):fail('passive_store_changed')
    return True,'verified_literal',pf_identity(second)

def passive_fuel_readiness():
    bodies,before=pf_snapshot()
    if before['api-v2.php'] is None:fail('passive_gateway_missing')
    api=bodies['api-v2.php']
    parent_bound=b"require_once dirname(__DIR__) . '/app/integrations/tourvisor-anytour-offer-autosave.php';" in api
    # The parent app is an existing explicit gateway dependency, never a guessed fallback.
    if not parent_bound and any(before['parent:'+p] is not None for p in PF_EXPECTED if p.startswith('app/')):
        fail('passive_parent_binding_unknown')
    binding=pf_binding(bodies['private_config'])
    binding_ok,binding_state,_=binding
    helpers=[p for p in PF_EXPECTED if p.startswith('app/')]
    layouts={}
    for layout in ('root','parent'):
        layouts[layout]={p:before[layout+':'+p] for p in helpers}
    selected=next((layout for layout in ('root','parent') if all(layouts[layout][p] is not None for p in helpers)),None)
    exact=selected is not None and all(layouts[selected][p]['sha256']==PF_EXPECTED[p] for p in helpers)
    db=bodies['data/db-v1.php']
    db_ok=before['data/db-v1.php'] is not None and before['data/db-v1.php']['sha256']==PF_DB
    declarations=0 if db is None else sum(bool(re.search(rb'function\s+'+name+rb'\s*\(',db))
        for name in (b'v2_data_db_config',b'v2_data_db',b'v2_data_normalize_text',b'v2_data_slug'))
    _,after=pf_snapshot()
    if before!=after:fail('passive_inventory_changed')
    if binding!=pf_binding(bodies['private_config']):fail('passive_store_changed')
    predecessor=before['api-v2.php']['sha256']==PF_PREDECESSOR
    return {'status':'ready' if predecessor and binding_ok and exact and db_ok and declarations==4 else 'not_ready',
        'gateway_predecessor_matches':predecessor,'helper_layouts':layouts,'selected_layout':selected,
        'helpers_exact':exact,'config_binding':binding_state,'store_directory_verified':binding_ok,
        'db_helper_integrity':db_ok,'db_helper_declarations':declarations,'loaded_db_state':'not_executed',
        'protected_before':{p:before[p] for p in PF_PROTECTED},
        'protected_after':{p:after[p] for p in PF_PROTECTED},
        'private_config_sha256':None if before['private_config'] is None else before['private_config']['sha256'],
        'supplier_calls':0,'database_reads':0,'database_writes':0,'runtime_writes':0,
        'booking_calls':0,'lead_calls':0,'protected_unchanged':True}
'''.replace('__PF_EXPECTED__', repr(SOURCE_HASHES)).replace('__PF_PROTECTED__', repr(PROTECTED))


# Reuse the already reviewed no-follow file reader and literal-only parser, not
# the consumed ROOT/parent inventory. Only the fixed private INT closure is new.
PRIVATE_REMOTE_READER = (REMOTE_READER[:REMOTE_READER.index('def pf_snapshot():')]
    + REMOTE_READER[REMOTE_READER.index('def pf_catalog_literal('):REMOTE_READER.index('def passive_fuel_readiness():')]
    + r'''
def pf_identity(s):
    return (s.st_dev,s.st_ino,s.st_mode,s.st_nlink,s.st_size,s.st_mtime_ns,s.st_ctime_ns,s.st_uid,s.st_gid)

def pf_private_binding(body):
    ok,state,identity=pf_binding(body)
    if not ok:return False,state,identity,None,False
    directory=pathlib.Path(pf_catalog_literal(body.decode('utf-8'))).parent/'searches'
    current=directory.stat(follow_symlinks=False)
    if pf_identity(current)!=identity:fail('passive_store_changed')
    mode=pf_stat.S_IMODE(current.st_mode)
    owner_matches=current.st_uid==os.getuid()
    if not owner_matches or mode&0o002 or mode&0o300!=0o300:
        return False,'store_unavailable',identity,mode,owner_matches
    return True,state,identity,mode,owner_matches

def pf_snapshot():
    records={};bodies={}
    for relative in PF_PROTECTED:
        bodies[relative],records[relative]=pf_read(project/relative)
    runtime=project/'_preview/search3-anex-candidate'
    for relative in PF_EXPECTED:
        if not relative.startswith('app/integrations/'):continue
        key='private_int:'+relative
        bodies[key],records[key]=pf_read(runtime/relative,limit=65536)
    bodies['private_config'],records['private_config']=pf_read(runtime/'.andromeda-private.php',limit=65536)
    return bodies,records

def passive_fuel_readiness():
    bodies,before=pf_snapshot()
    if before['api-v2.php'] is None:fail('passive_gateway_missing')
    binding=pf_private_binding(bodies['private_config'])
    binding_ok,binding_state,_,store_mode,owner_matches=binding
    helpers=[p for p in PF_EXPECTED if p.startswith('app/')]
    rows={p:before['private_int:'+p] for p in helpers}
    selected='private_int' if all(rows[p] is not None for p in helpers) else None
    exact=selected is not None and all(rows[p]['sha256']==PF_EXPECTED[p] for p in helpers)
    db=bodies['data/db-v1.php']
    db_ok=before['data/db-v1.php'] is not None and before['data/db-v1.php']['sha256']==PF_DB
    declarations=0 if db is None else sum(bool(re.search(rb'function\s+'+name+rb'\s*\(',db))
        for name in (b'v2_data_db_config',b'v2_data_db',b'v2_data_normalize_text',b'v2_data_slug'))
    _,after=pf_snapshot()
    if before!=after:fail('passive_inventory_changed')
    if binding!=pf_private_binding(bodies['private_config']):fail('passive_store_changed')
    predecessor=before['api-v2.php']['sha256']==PF_PREDECESSOR
    return {'status':'ready' if predecessor and binding_ok and exact and db_ok and declarations==4 else 'not_ready',
        'gateway_predecessor_matches':predecessor,'helper_layouts':{'private_int':rows},'selected_layout':selected,
        'helpers_exact':exact,'config_binding':binding_state,'store_directory_verified':binding_ok,
        'db_helper_integrity':db_ok,'db_helper_declarations':declarations,'loaded_db_state':'not_executed',
        'protected_before':{p:before[p] for p in PF_PROTECTED},
        'protected_after':{p:after[p] for p in PF_PROTECTED},
        'private_config_sha256':None if before['private_config'] is None else before['private_config']['sha256'],
        'private_config_record':before['private_config'],'store_directory_mode':store_mode,
        'store_directory_owner_matches':owner_matches,
        'supplier_calls':0,'database_reads':0,'database_writes':0,'runtime_writes':0,
        'booking_calls':0,'lead_calls':0,'protected_unchanged':True}
''').replace('PF_EXPECTED='+repr(SOURCE_HASHES), 'PF_EXPECTED='+repr(PRIVATE_SOURCE_HASHES), 1)

REMOTE_DISPATCH = r'''
    if mode=='passive-fuel-readiness':
        try:result['passive_fuel_readiness']=passive_fuel_readiness()
        except OSError:fail('passive_file_unsafe')
        result['production_after']=fingerprints()
        if result['production_after']!=before:fail('production_drift')
        result.update(status='complete',supplier_calls=0,database_reads=0,database_writes=0,
                      runtime_changed=False,production_unchanged=True)
'''


def remote_program(remote, command=None):
    command = policy(command)
    mode = command['mode']
    marker = 'def local_read(scopes):\n'
    dispatch = "    if mode=='local-readback':\n"
    exclusion = "    if mode not in ('reconcile',"
    size = "if not isinstance(files,dict) or len(files)<20: fail('manifest')"
    need(remote.count(marker) == remote.count(dispatch) == remote.count(size) == 1
         and remote.count(exclusion) == 2, 'passive_registration_drift')
    reader = PRIVATE_REMOTE_READER if mode == PRIVATE_MODE else REMOTE_READER
    hashes = PRIVATE_SOURCE_HASHES if mode == PRIVATE_MODE else SOURCE_HASHES
    remote = remote.replace(marker, reader + '\n' + marker, 1)
    gate = "    if not re.fullmatch(r'int-(?:anex|andromeda)-[a-z0-9-]{8,80}-v[1-9][0-9]*',operation):"
    need(remote.count(gate) == 1, 'passive_remote_gate')
    fixed = "    if mode==" + repr(mode) + " and (source!=" + repr(command['source_sha']) + " or operation!=" + repr(command['operation_id']) + "):fail('passive_fixed_scope')\n"
    remote = remote.replace(gate, fixed + gate, 1)
    remote = remote.replace(dispatch, REMOTE_DISPATCH.replace(repr(MODE), repr(mode), 1) + dispatch, 1)
    remote = remote.replace(exclusion, "    if mode not in (" + repr(mode) + ",'reconcile',")
    remote = remote.replace(size, "if not isinstance(files,dict) or files!=" + repr(hashes) + ": fail('passive_manifest')", 1)
    ast.parse(remote)
    return remote


def completed(command, result):
    command = policy(command)
    private = command['mode'] == PRIVATE_MODE
    value = result.get('passive_fuel_readiness')
    counts = ('supplier_calls','database_reads','database_writes','runtime_writes','booking_calls','lead_calls')
    fields = {'status','gateway_predecessor_matches','helper_layouts','selected_layout','helpers_exact',
              'config_binding','store_directory_verified','db_helper_integrity','db_helper_declarations',
              'loaded_db_state','protected_before','protected_after','private_config_sha256',
              'protected_unchanged',*counts}
    if private:fields.update(('private_config_record','store_directory_mode','store_directory_owner_matches'))
    def digest(value):
        return isinstance(value,str) and re.fullmatch('[a-f0-9]{64}',value) is not None
    def record(value):
        return value is None or (isinstance(value,dict) and set(value)=={'sha256','bytes','mode'}
            and digest(value['sha256']) and type(value['bytes']) is int and 0<value['bytes']<=2*1024*1024
            and type(value['mode']) is int and 0<=value['mode']<=0o7777)
    if not isinstance(value,dict) or set(value)!=fields:return False
    if private and (not record(value['private_config_record'])
            or value['store_directory_mode'] is not None and (type(value['store_directory_mode']) is not int
                or not 0<=value['store_directory_mode']<=0o7777)
            or type(value['store_directory_owner_matches']) is not bool):return False
    before=value['protected_before'];layouts=value['helper_layouts']
    if (not isinstance(before,dict) or set(before)!=set(PROTECTED) or not all(map(record,before.values()))
            or not isinstance(layouts,dict) or set(layouts)!=({'private_int'} if private else {'root','parent'})):return False
    helpers={p for p in SOURCE_HASHES if p.startswith('app/')}
    if any(not isinstance(rows,dict) or set(rows)!=helpers or not all(map(record,rows.values()))
           for rows in layouts.values()):return False
    if (value['config_binding'] not in ('missing','unrecognized','outside_scope','store_unavailable','verified_literal')
            or value['selected_layout'] not in ((None,'private_int') if private else (None,'root','parent'))
            or value['loaded_db_state']!='not_executed'
            or type(value['db_helper_declarations']) is not int or not 0<=value['db_helper_declarations']<=4
            or value['private_config_sha256'] is not None and not digest(value['private_config_sha256'])
            or any(type(value[key]) is not bool for key in ('gateway_predecessor_matches','helpers_exact',
                   'store_directory_verified','db_helper_integrity','protected_unchanged'))):return False
    return (all(result.get(k) == command[k] for k in command)
            and result.get('status') == 'complete' and result.get('production_unchanged') is True
            and result.get('runtime_changed') is False
            and isinstance(value, dict) and value.get('status') in ('ready', 'not_ready')
            and value.get('protected_unchanged') is True
            and isinstance(value.get('protected_before'), dict)
            and value.get('protected_before') == value.get('protected_after')
            and all(type(result.get(k)) is int and result[k] == 0
                    for k in ('supplier_calls', 'database_reads', 'database_writes', 'booking_calls', 'lead_calls'))
            and all(type(value.get(k)) is int and value[k] == 0 for k in counts))
