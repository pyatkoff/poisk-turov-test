"""Fixed passive-fuel scopes inside the permanent INT carrier.

Sealed inventories remain read-only. The separate activation transaction replaces
exactly two reviewed files only after its complete dependency preflight is ready.
No application/config execution, database connection or supplier request applies.
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
PLAN_MODE = 'passive-fuel-activation-plan'
PLAN_SOURCE = '199a570adbaa8de4957bdd84b30648bfaaee5fb5'
PLAN_OPERATION = 'int-anex-passive-fuel-activation-plan-20261020-v1'
INSTALL_MODE = 'passive-fuel-activate'
INSTALL_SOURCE = '0415fe57046a8929d1b857e13cd0d0d8824e904d'
INSTALL_OPERATION = 'int-anex-passive-fuel-activate-20261020-v1'
STOCK_MODE = 'passive-fuel-stock-activate'
STOCK_SOURCE = '0415fe57046a8929d1b857e13cd0d0d8824e904d'
STOCK_OPERATION = 'int-anex-passive-fuel-stock-activate-20261020-v1'
MODES = (MODE, PRIVATE_MODE, PLAN_MODE, INSTALL_MODE, STOCK_MODE)
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
    need(parts in ([SOURCE, MODE, OPERATION], [PRIVATE_SOURCE, PRIVATE_MODE, PRIVATE_OPERATION],
                  [PLAN_SOURCE, PLAN_MODE, PLAN_OPERATION],
                  [INSTALL_SOURCE, INSTALL_MODE, INSTALL_OPERATION],
                  [STOCK_SOURCE, STOCK_MODE, STOCK_OPERATION]),
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
    private = command['mode'] in (PRIVATE_MODE, PLAN_MODE, INSTALL_MODE, STOCK_MODE)
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
    hashes = PRIVATE_SOURCE_HASHES if command['mode'] in (PRIVATE_MODE, PLAN_MODE, INSTALL_MODE, STOCK_MODE) else SOURCE_HASHES
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

# Installer planning only. Unlike the sealed metadata operations, this fixed
# closure validates the stock proxy target and private gateway caller before
# producing a two-file plan. It cannot execute an include or install any file.
PLAN_REMOTE_READER = (REMOTE_READER[:REMOTE_READER.index('def pf_snapshot():')]
    .replace("anchor=home/'www'", 'anchor=home', 1) + r'''
PP_PROXY='52e05b625b63a611f34cb87b58bd0e554c024df1e277bede469416330812d5aa'
PP_DB='c1c841d38c62845bc36e559183da17e0411c1c5bdd909517ffc02b5fbb1e2f13'
PP_CONFIG='121839ce1956ec7a48f0a94758e2c2359293ee01d601c69f720a294c4bda7def'
PP_OLD_PASSIVE='be4380b64caf4c98fc572c8db526f079a89bd04c301db5eccea3c2f2f232cc22'
PP_CALLER='2dedef982d7036c3f8520e8b8ca1175d83eb89fe14be865198441e33ff2c2ff4'
PP_LEGACY_CALLER='9935a1cd571ee58146e5fdec906833f3f678c38d3723c59c62d7796659c03dda'
PP_TARGETS={'api-v2.php':('01ac5ed43961f73879cd36c2c1d387ac79fadaf53ad66d3c4aaebcc617464c61',
    '04cddd8c112bcdd70438a2e58e4935e1eae115057b218780f2f845bbfd83fd15'),
    '_preview/search3-anex-candidate/app/integrations/tourvisor-operator-fuel-passive-intake.php':
    (PP_OLD_PASSIVE,'da89e7e5dc7816419140b716cb965462542ebc2ff3f42b4ec96dc623d823fa8d')}

def pf_identity(s):
    return (s.st_dev,s.st_ino,s.st_mode,s.st_nlink,s.st_size,s.st_mtime_ns,s.st_ctime_ns,s.st_uid,s.st_gid)

def pp_literal_config(body):
    # Parse bounded PHP literals, including var_export's array(...), never PHP.
    if body is None:return None
    try:text=body.decode('utf-8')
    except UnicodeDecodeError:return None
    literal=re.fullmatch(r'\s*<\?php\s*(?:declare\s*\(\s*strict_types\s*=\s*1\s*\)\s*;\s*)?return\s*(.*?)\s*;\s*',text,re.S)
    if literal is None:return None
    pattern=re.compile(r"""\s*(=>|array\b|\(|\)|\[|\]|,|'(?:\\.|[^'\\])*'|"(?:\\.|[^"\\])*"|-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?|true\b|false\b|null\b)""")
    tokens=[];position=0;raw=literal[1]
    while position<len(raw):
        match=pattern.match(raw,position)
        if match is None or len(tokens)>=4096:return None
        tokens.append(match[1]);position=match.end()
    cursor=0
    def value(depth=0):
        nonlocal cursor
        if depth>8 or cursor>=len(tokens):raise ValueError('shape')
        token=tokens[cursor];cursor+=1
        if token in ('[','array'):
            closing=']'
            if token=='array':
                if cursor>=len(tokens) or tokens[cursor]!='(':raise ValueError('array')
                cursor+=1;closing=')'
            entries=[]
            while cursor<len(tokens) and tokens[cursor]!=closing:
                first=value(depth+1);key=None
                if cursor<len(tokens) and tokens[cursor]=='=>':
                    cursor+=1;key=first;first=value(depth+1)
                    if not isinstance(key,(str,int)) or isinstance(key,bool):raise ValueError('key')
                entries.append((key,first))
                if cursor<len(tokens) and tokens[cursor]==',':cursor+=1
                elif cursor>=len(tokens) or tokens[cursor]!=closing:raise ValueError('separator')
            if cursor>=len(tokens):raise ValueError('array')
            cursor+=1;return entries
        if token[:1] in ("'",'"'):
            text=token[1:-1]
            if token[0]=='"' and '$' in text:raise ValueError('interpolation')
            # Only unambiguous escapes emitted by literal configuration writers.
            if re.search(r"\\(?![\\'\"])" ,text):raise ValueError('escape')
            return re.sub(r"\\([\\'\"])",r'\1',text)
        if token in ('true','false','null'):return {'true':True,'false':False,'null':None}[token]
        if re.fullmatch(r'-?(?:0|[1-9][0-9]*)',token):return int(token)
        if re.fullmatch(r'-?(?:0|[1-9][0-9]*)\.[0-9]+',token):return float(token)
        raise ValueError('value')
    try:entries=value()
    except (ValueError,RecursionError):return None
    if cursor!=len(tokens) or not isinstance(entries,list):return None
    enabled=[v for k,v in entries if k=='enabled']
    catalogs=[v for k,v in entries if k=='catalog_path']
    if len(enabled)!=1 or enabled[0] is not True or len(catalogs)!=1 or not isinstance(catalogs[0],str):return None
    return catalogs[0]

def pp_directory(path):
    # Metadata only. No directory listing, observations, or catalog contents.
    try:parts=path.relative_to(home).parts
    except ValueError:fail('passive_plan_directory_scope')
    if not parts or '..' in parts:fail('passive_plan_directory_scope')
    descriptor=os.open(home,os.O_PATH|os.O_DIRECTORY|os.O_NOFOLLOW)
    try:
        for part in parts:
            try:child=os.open(part,os.O_PATH|os.O_DIRECTORY|os.O_NOFOLLOW,dir_fd=descriptor)
            except FileNotFoundError:return None
            os.close(descriptor);descriptor=child
        first=os.fstat(descriptor);second=path.lstat()
        if path.resolve()!=path or not pf_stat.S_ISDIR(first.st_mode) or pf_identity(first)!=pf_identity(second):
            fail('passive_plan_directory_changed')
        mode=pf_stat.S_IMODE(first.st_mode)
        return (pf_identity(first),mode,first.st_uid==os.getuid(),
                first.st_uid==os.getuid() and mode&0o300==0o300 and not mode&0o002)
    finally:os.close(descriptor)

def pp_snapshot():
    runtime=project/'_preview/search3-anex-candidate'
    paths={p:project/p for p in PF_PROTECTED}
    for p in PF_EXPECTED:
        if p.startswith('app/integrations/'):paths['helper:'+p]=runtime/p
    paths.update(proxy=runtime/'.andromeda-private.php',private_gateway=runtime/'api-v2.php',
        private_db=runtime/'data/db-v1.php',
        backing_config=home/'.anytoour-andromeda/search3-preview.php')
    bodies={};records={};identities={}
    for key,path in paths.items():
        bodies[key],records[key]=pf_read(path,limit=2*1024*1024 if key in PF_PROTECTED else 65536)
        identities[key]=None if records[key] is None else pf_identity(path.lstat())
    directories=(pp_directory(home/'.anytoour-andromeda'),pp_directory(home/'.anytoour-andromeda/searches'))
    return bodies,records,identities,directories

def passive_fuel_activation_plan(snapshot=None):
    bodies,before,identities,directories=pp_snapshot() if snapshot is None else snapshot
    helpers=[p for p in PF_EXPECTED if p.startswith('app/')]
    passive='app/integrations/tourvisor-operator-fuel-passive-intake.php'
    digest=lambda name:None if before[name] is None else before[name]['sha256']
    proxy_ok=digest('proxy')==PP_PROXY and before['proxy']['mode']==0o600
    db_ok=digest('data/db-v1.php')==PP_DB
    private_db_ok=digest('private_db')==PP_DB
    config_ok=digest('config.php')==PP_CONFIG
    api_ok=digest('api-v2.php')==PP_TARGETS['api-v2.php'][0]
    passive_ok=digest('helper:'+passive)==PP_OLD_PASSIVE
    dependencies_ok=all(digest('helper:'+p)==PF_EXPECTED[p] for p in helpers if p!=passive)
    caller=digest('private_gateway')
    caller_state=('compatible_pr4732' if caller==PP_CALLER else 'legacy_eager_dependency'
        if caller==PP_LEGACY_CALLER else 'missing' if caller is None else 'unrecognized')
    catalog=pp_literal_config(bodies['backing_config'])
    expected=home/'.anytoour-andromeda/catalog.json'
    config_owned=(identities['backing_config'] is not None and identities['backing_config'][-2]==os.getuid()
        and before['backing_config']['mode']==0o600)
    binding=(proxy_ok and config_owned and catalog==str(expected))
    store_ok=binding and all(d is not None and d[3] for d in directories)
    store_state=('verified_stock_binding' if store_ok else 'config_unrecognized'
        if catalog is None else 'config_binding_mismatch' if not binding else 'store_unavailable')
    _,after,after_identities,after_directories=pp_snapshot()
    if before!=after or identities!=after_identities or directories!=after_directories:fail('passive_plan_drift')
    ready=all((api_ok,passive_ok,dependencies_ok,db_ok,private_db_ok,config_ok,proxy_ok,store_ok,caller_state=='compatible_pr4732'))
    return {'status':'ready' if ready else 'hold','gateway_predecessor_matches':api_ok,
        'passive_predecessor_matches':passive_ok,'dependencies_exact':dependencies_ok,
        'db_predecessor_matches':db_ok,'private_db_dependency_matches':private_db_ok,'root_config_predecessor_matches':config_ok,
        'proxy_predecessor_matches':proxy_ok,'private_caller_state':caller_state,
        'store_state':store_state,'store_directory_mode':None if directories[1] is None else directories[1][1],
        'store_directory_owner_matches':False if directories[1] is None else directories[1][2],
        'backing_config_record':before['backing_config'],'private_gateway_record':before['private_gateway'],
        'private_db_record':before['private_db'],
        'proxy_record':before['proxy'],'loaded_db_state':'not_executed','binding_identity_stable':True,
        'plan_files':{p:{'predecessor_sha256':v[0],'candidate_sha256':v[1]} for p,v in PP_TARGETS.items()},
        'protected_before':{p:before[p] for p in PF_PROTECTED},
        'protected_after':{p:after[p] for p in PF_PROTECTED},'protected_unchanged':True,
        'supplier_calls':0,'database_reads':0,'database_writes':0,'runtime_writes':0,'booking_calls':0,'lead_calls':0}
''').replace('PF_EXPECTED='+repr(SOURCE_HASHES), 'PF_EXPECTED='+repr(PRIVATE_SOURCE_HASHES), 1)


# This is a new installation transaction, not a replacement metadata operation.
# The old three operation/source tuples above remain immutable and no-replay.
INSTALL_REMOTE = PLAN_REMOTE_READER + r'''
import fcntl as pi_fcntl

def pi_parent(path):
    parts=path.relative_to(home).parts
    if not parts or '..' in parts:fail('activation_path')
    descriptor=os.open(home,os.O_RDONLY|os.O_DIRECTORY|os.O_NOFOLLOW)
    try:
        for part in parts[:-1]:
            child=os.open(part,os.O_RDONLY|os.O_DIRECTORY|os.O_NOFOLLOW,dir_fd=descriptor)
            os.close(descriptor);descriptor=child
        if path.parent.resolve()!=path.parent:fail('activation_parent')
        return descriptor
    except Exception:
        os.close(descriptor);raise

def pi_parent_stable(descriptor,path):
    a=os.fstat(descriptor);b=path.parent.lstat()
    fields=lambda s:(s.st_dev,s.st_ino,s.st_mode,s.st_uid,s.st_gid)
    if (fields(a)!=fields(b) or path.parent.resolve()!=path.parent or a.st_uid!=os.getuid()
            or pf_stat.S_IMODE(a.st_mode)&0o022):fail('activation_parent')

def pi_write(descriptor,name,data,mode):
    fd=os.open(name,os.O_WRONLY|os.O_CREAT|os.O_EXCL|os.O_NOFOLLOW,0o600,dir_fd=descriptor)
    with os.fdopen(fd,'wb') as stream:
        stream.write(data);stream.flush();os.fchmod(stream.fileno(),mode);os.fsync(stream.fileno())
        return pf_identity(os.fstat(stream.fileno()))

def pi_private(path,data):
    fd=pi_parent(path)
    try:
        pi_parent_stable(fd,path);pi_write(fd,path.name,data,0o600);os.fsync(fd)
    finally:os.close(fd)

def pi_lint(path):
    # Syntax only: -n disables PHP configuration; no include or application run.
    call=subprocess.run(['php','-n','-l',str(path)],stdout=subprocess.DEVNULL,
        stderr=subprocess.DEVNULL,timeout=15,check=False,env={'PATH':'/usr/bin:/bin','HOME':str(home)})
    if call.returncode:fail('activation_syntax')

def passive_fuel_activate(stage,op,production_before):
    out={'status':'blocked_no_replay','reason':'preflight_failed','preflight':None,
        'publication_lock':'not_acquired','changed_files':[],'rollback_files':[],
        'before_files':{},'after_files':{},'protected_after':{},'protected_unchanged_except_target':False,
        'runtime_writes':0,'staging_file_writes':0,'backup_retained':False,
        'supplier_calls':0,'database_reads':0,'database_writes':0,'booking_calls':0,'lead_calls':0}
    lock=None;lock_parent=None;parents={};temps={};applied=[];initial=None;expected=None;expected_ids=None
    root='api-v2.php'
    passive='_preview/search3-anex-candidate/app/integrations/tourvisor-operator-fuel-passive-intake.php'
    order=(passive,root)  # Dependency first, ROOT gateway last; rollback reverses this order.
    keys={root:root,passive:'helper:app/integrations/tourvisor-operator-fuel-passive-intake.php'}
    source_paths={root:'v2/api-v2.php',passive:'app/integrations/tourvisor-operator-fuel-passive-intake.php'}
    old={};candidates={};installed={}
    def snapshot_matches():
        current=pp_snapshot()
        if (current[1]!=expected or current[2]!=expected_ids or current[3]!=initial[3]
                or fingerprints()!=production_before):fail('activation_drift')
        for rel,fd in parents.items():pi_parent_stable(fd,project/rel)
        if lock is not None and pf_identity(os.fstat(lock))!=lock_identity:fail('activation_lock_changed')
        if lock is not None and pf_identity(lock_path.lstat())!=lock_identity:fail('activation_lock_changed')
        return current
    def checkpoint(name):
        pi_private(op/name,json.dumps({k:out[k] for k in ('status','runtime_writes','changed_files','rollback_files')},
            sort_keys=True,separators=(',',':')).encode())
    try:
        out['preflight']=passive_fuel_activation_plan()
        out.update(protected_after=out['preflight']['protected_after'],protected_unchanged_except_target=True)
        if out['preflight']['status']!='ready':
            out.update(status='hold',reason='preflight_hold',protected_after=out['preflight']['protected_after'],
                protected_unchanged_except_target=True)
            return out
        lock_path=home/'.anytoour-andromeda/grouped-search-update.lock'
        lock_parent=pi_parent(lock_path)
        pi_parent_stable(lock_parent,lock_path)
        try:lock=os.open(lock_path.name,os.O_RDWR|os.O_NOFOLLOW|os.O_NONBLOCK,dir_fd=lock_parent)
        except FileNotFoundError:
            out.update(status='hold',reason='publication_lock_missing');return out
        info=os.fstat(lock);lock_identity=pf_identity(info)
        if (not pf_stat.S_ISREG(info.st_mode) or info.st_nlink!=1 or info.st_uid!=os.getuid()
                or pf_stat.S_IMODE(info.st_mode)&0o022 or info.st_size>65536
                or pf_identity(lock_path.lstat())!=lock_identity):fail('activation_lock_unsafe')
        try:pi_fcntl.flock(lock,pi_fcntl.LOCK_EX|pi_fcntl.LOCK_NB)
        except BlockingIOError:
            out.update(status='hold',reason='publication_busy');return out
        out['publication_lock']='exclusive_existing'
        # Revalidate everything under the publisher's existing lock.
        initial=pp_snapshot()
        out['preflight']=passive_fuel_activation_plan(initial)
        out.update(protected_after=out['preflight']['protected_after'],protected_unchanged_except_target=True)
        if out['preflight']['status']!='ready':
            out.update(status='hold',reason='preflight_hold');return out
        expected=dict(initial[1]);expected_ids=dict(initial[2])
        for rel in order:
            target=project/rel;key=keys[rel];record=initial[1][key];identity=initial[2][key]
            if (record is None or record['sha256']!=PP_TARGETS[rel][0] or record['mode']!=0o644
                    or identity[-2]!=os.getuid()):fail('activation_predecessor')
            parents[rel]=pi_parent(target);pi_parent_stable(parents[rel],target)
            if os.fstat(parents[rel]).st_dev!=op.stat().st_dev:fail('activation_filesystem')
            old[rel]=initial[0][key]
            data,record=pf_read(stage/source_paths[rel],optional=False,limit=65536)
            if record['sha256']!=PP_TARGETS[rel][1]:fail('activation_candidate')
            candidates[rel]=data;out['before_files'][rel]=initial[1][key]
        snapshot_matches()
        for index,rel in enumerate(order):
            pi_private(op/('activation-backup-'+str(index)+'.php'),old[rel])
        out['backup_retained']=True
        for index,rel in enumerate(order):
            name='.int-passive-fuel-'+operation+'-'+str(index)+'.tmp'
            identity=pi_write(parents[rel],name,candidates[rel],0o644)
            temps[rel]=(name,identity);out['staging_file_writes']+=1
            pi_lint(project/rel);pi_lint((project/rel).parent/name)
        snapshot_matches();out['status']='prepared';checkpoint('activation-prepared.json')
        for index,rel in enumerate(order):
            snapshot_matches();name,identity=temps[rel];fd=parents[rel]
            if pf_identity(os.stat(name,dir_fd=fd,follow_symlinks=False))!=identity:fail('activation_candidate')
            os.replace(name,(project/rel).name,src_dir_fd=fd,dst_dir_fd=fd)
            # Record ownership immediately after atomic replacement, before fsync/readback/lint.
            applied.append(rel);installed[rel]=identity
            out['runtime_writes']+=1;out['changed_files'].append(rel);temps.pop(rel)
            os.fsync(fd)
            data,record=pf_read(project/rel,optional=False,limit=65536)
            actual_id=pf_identity((project/rel).lstat())
            # rename changes ctime, so bind the new inode plus the exact installed bytes/mode.
            if (actual_id[:5]!=identity[:5] or record['sha256']!=PP_TARGETS[rel][1]
                    or record['mode']!=0o644):fail('activation_readback')
            installed[rel]=actual_id;expected[keys[rel]]=record;expected_ids[keys[rel]]=actual_id
            pi_lint(project/rel);snapshot_matches();checkpoint('activation-applied-'+str(index)+'.json')
        final=snapshot_matches()
        out.update(status='installed_verified',reason=None,after_files={p:final[1][keys[p]] for p in order},
            protected_after={p:final[1][p] for p in PF_PROTECTED},protected_unchanged_except_target=True)
        checkpoint('activation-verified.json')
    except Exception as exc:
        allowed={'activation_path','activation_parent','activation_lock_changed','activation_lock_unsafe',
            'activation_drift','activation_predecessor','activation_filesystem','activation_candidate',
            'activation_syntax','activation_readback'}
        out['reason']=str(exc) if isinstance(exc,RuntimeError) and str(exc) in allowed else 'activation_failed'
        out['status']='blocked_no_replay' if not applied else 'unknown_no_replay'
        if applied:
            try:
                for index,rel in enumerate(reversed(applied)):
                    fd=parents[rel];target=project/rel;pi_parent_stable(fd,target)
                    data,record=pf_read(target,optional=False,limit=65536)
                    current=pf_identity(target.lstat());ours=installed[rel]
                    # Never overwrite a subsequent writer's inode or changed bytes.
                    if (current[:5]!=ours[:5] or current[5]!=ours[5]
                            or current[7:]!=ours[7:] or record['sha256']!=PP_TARGETS[rel][1]):
                        fail('activation_rollback_ownership')
                    name='.int-passive-fuel-'+operation+'-rollback-'+str(index)+'.tmp'
                    identity=pi_write(fd,name,old[rel],0o644);temps[rel]=(name,identity)
                    if pf_identity(target.lstat())!=current:fail('activation_rollback_ownership')
                    os.replace(name,target.name,src_dir_fd=fd,dst_dir_fd=fd);temps.pop(rel)
                    out['runtime_writes']+=1;out['rollback_files'].append(rel);os.fsync(fd)
                    _,restored=pf_read(target,optional=False,limit=65536)
                    if restored!=out['before_files'][rel]:fail('activation_rollback_readback')
                final=pp_snapshot()
                if (final[1]!=initial[1] or final[3]!=initial[3] or fingerprints()!=production_before
                        or any(final[2][k]!=initial[2][k] for k in initial[2] if k not in keys.values())):
                    fail('activation_rollback_drift')
                out.update(status='rolled_back_no_replay',after_files={p:final[1][keys[p]] for p in order},
                    protected_after={p:final[1][p] for p in PF_PROTECTED},protected_unchanged_except_target=True)
            except Exception:out['status']='rollback_failed_no_replay'
    finally:
        for rel,(name,identity) in temps.items():
            try:
                fd=parents[rel]
                if pf_identity(os.stat(name,dir_fd=fd,follow_symlinks=False))==identity:os.unlink(name,dir_fd=fd);os.fsync(fd)
                else:out['status']='unknown_no_replay'
            except Exception:out['status']='unknown_no_replay'
        for fd in parents.values():os.close(fd)
        if lock is not None:os.close(lock)
        if lock_parent is not None:os.close(lock_parent)
    return out
'''


# Stock publisher c03dbecd at this deployed source excludes api-v2.php at
# every path component. Its safe wrapper does not change build_payload; the
# isolated UI keeps ROOT /api-v2.php. These seven rows were independently
# reproduced by that exact publisher, without PHP/config/provider execution.
STOCK_PUBLISHER_SOURCE = '7d21538b56a463ced185dd893361469e92911c77'
STOCK_REQUIRED = {
    'poisk-turov/index.php': (1272, 'a6d151903685c4aaf68163c339a57a52f2979e7587d6635b2a64e0d6e6548c67'),
    'api-anex-search3-preview.php': (52903, 'd69aaedf759d2d7cdfd3d8a0cbedf400bb1cc92a285b79ad9746fa825338f8db'),
    'api-andromeda-search3-preview.php': (43929, 'fc338f37496290871793c6b1fe0f5614b4aea4db2a2f0503277117a676a912e4'),
    'anex-search3-preview-v1.js': (47839, 'a5343ea7cc716e1319ba0bb93548a5f08162672e406c4718f909252537a6aa7e'),
    'preview-lead-disabled.php': (378, '15d1c61b6fde806caa52820748050b8bd55c82345499390d5f2a8e04577c2f5d'),
    'app/integrations/anex-search-mapping-registry.php': (8865, 'ba06d082ac66d671efc01569a7d11e9d8b5abd73cabde8ea72ecc154b3e67814'),
    'app/integrations/anex-additional-prices-client.php': (21737, '82811acb7908d95315287d5e552b4d8ccf1f30103f6714ee5424aec8788fd34a'),
}

STOCK_REMOTE = INSTALL_REMOTE + '\nPS_SOURCE=' + repr(STOCK_PUBLISHER_SOURCE) + '\nPS_REQUIRED=' + repr(STOCK_REQUIRED) + r'''
ps_base_snapshot=pp_snapshot
ps_base_plan=passive_fuel_activation_plan

def pp_snapshot():
    bodies,records,identities,directories=ps_base_snapshot()
    for key,path in (('stock_manifest',runtime/'anex-preview-manifest.json'),
                     ('nested_gateway',runtime/'v2/api-v2.php')):
        bodies[key],records[key]=pf_read(path,limit=1024*1024)
        identities[key]=None if records[key] is None else pf_identity(path.lstat())
    return bodies,records,identities,directories

def ps_manifest(body):
    if body is None:return False
    def unique(pairs):
        result={}
        for key,value in pairs:
            if key in result:raise ValueError('duplicate')
            result[key]=value
        return result
    try:
        value=json.loads(body,object_pairs_hook=unique,parse_constant=lambda _:None)
        if (not isinstance(value,dict) or type(value.get('schema_version')) is not int or value['schema_version']!=1
                or value.get('source_sha')!=PS_SOURCE or value.get('route')!='/_preview/search3-anex-candidate/'
                or value.get('production_entry_changes') is not False or value.get('production_lead_delivery') is not False
                or type(value.get('preview_metrika_counter')) is not int or value['preview_metrika_counter']!=0
                or value.get('mapping_scope')!='preview' or not isinstance(value.get('files'),list)
                or type(value.get('file_count')) is not int or not len(PS_REQUIRED)<=value['file_count']<=3000
                or value['file_count']!=len(value['files'])):return False
        rows={}
        for row in value['files']:
            if not isinstance(row,dict) or set(row)!={'path','sha256','bytes'}:return False
            path=row['path']
            if (not isinstance(path,str) or not 1<=len(path)<=240 or '\\' in path
                    or pathlib.PurePosixPath(path).is_absolute() or str(pathlib.PurePosixPath(path))!=path
                    or any(part in ('.','..','api-v2.php') for part in path.split('/')) or path in rows
                    or not isinstance(row['sha256'],str) or re.fullmatch('[a-f0-9]{64}',row['sha256']) is None
                    or type(row['bytes']) is not int or not 0<row['bytes']<=8*1024*1024):return False
            rows[path]=(row['bytes'],row['sha256'])
        return all(rows.get(path)==record for path,record in PS_REQUIRED.items())
    except (ValueError,UnicodeError,RecursionError):return False

def passive_fuel_activation_plan(snapshot=None):
    initial=pp_snapshot() if snapshot is None else snapshot
    value=ps_base_plan(initial)  # Full extended snapshot also checked after validation.
    records=initial[1]
    absent=records['private_gateway'] is None and records['nested_gateway'] is None
    verified=(ps_manifest(initial[0]['stock_manifest']) and records['stock_manifest']['mode']==0o644
        and initial[2]['stock_manifest'][-2]==os.getuid())
    topology=verified and absent
    value['stock_topology']={'status':'verified_stock_exclusion' if topology else 'gateway_present'
        if not absent else 'unrecognized_manifest','publisher_source_sha':PS_SOURCE,
        'required_rows_verified':verified,'manifest_record':records['stock_manifest'],
        'nested_gateway_record':records['nested_gateway']}
    if topology:value['private_caller_state']='excluded_by_stock_publisher'
    flags=('gateway_predecessor_matches','passive_predecessor_matches','dependencies_exact',
        'db_predecessor_matches','private_db_dependency_matches','root_config_predecessor_matches',
        'proxy_predecessor_matches','store_directory_owner_matches','binding_identity_stable','protected_unchanged')
    value['status']='ready' if topology and all(value[k] for k in flags) and value['store_state']=='verified_stock_binding' else 'hold'
    return value
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
    reader = STOCK_REMOTE if mode == STOCK_MODE else INSTALL_REMOTE if mode == INSTALL_MODE else PLAN_REMOTE_READER if mode == PLAN_MODE else PRIVATE_REMOTE_READER if mode == PRIVATE_MODE else REMOTE_READER
    hashes = PRIVATE_SOURCE_HASHES if mode in (PRIVATE_MODE, PLAN_MODE, INSTALL_MODE, STOCK_MODE) else SOURCE_HASHES
    remote = remote.replace(marker, reader + '\n' + marker, 1)
    gate = "    if not re.fullmatch(r'int-(?:anex|andromeda)-[a-z0-9-]{8,80}-v[1-9][0-9]*',operation):"
    need(remote.count(gate) == 1, 'passive_remote_gate')
    fixed = "    if mode==" + repr(mode) + " and (source!=" + repr(command['source_sha']) + " or operation!=" + repr(command['operation_id']) + "):fail('passive_fixed_scope')\n"
    remote = remote.replace(gate, fixed + gate, 1)
    registration = REMOTE_DISPATCH.replace(repr(MODE), repr(mode), 1)
    if mode == PLAN_MODE:
        registration = registration.replace("result['passive_fuel_readiness']=passive_fuel_readiness()",
            "result['passive_fuel_activation_plan']=passive_fuel_activation_plan()", 1)
    if mode in (INSTALL_MODE, STOCK_MODE):
        registration = "    if mode==" + repr(mode) + ":\n" + """        result['passive_fuel_activation']=passive_fuel_activate(stage,op,before)
        activation=result['passive_fuel_activation']
        result['production_after']=fingerprints()
        result.update(status='complete' if activation['status'] in ('hold','installed_verified') else activation['status'],
            supplier_calls=0,database_reads=0,database_writes=0,
            runtime_changed=activation['status']=='installed_verified' or activation['status'] in ('unknown_no_replay','rollback_failed_no_replay'),
            production_unchanged=result['production_after']==before)
"""
    remote = remote.replace(dispatch, registration + dispatch, 1)
    remote = remote.replace(exclusion, "    if mode not in (" + repr(mode) + ",'reconcile',")
    remote = remote.replace(size, "if not isinstance(files,dict) or files!=" + repr(hashes) + ": fail('passive_manifest')", 1)
    ast.parse(remote)
    return remote


def completed(command, result):
    command = policy(command)
    if command['mode'] == STOCK_MODE:return stock_completed(command,result)
    if command['mode'] == INSTALL_MODE:return install_completed(command,result)
    if command['mode'] == PLAN_MODE:return plan_completed(command, result)
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


def plan_completed(command, result):
    value=result.get('passive_fuel_activation_plan')
    counts={'supplier_calls','database_reads','database_writes','runtime_writes','booking_calls','lead_calls'}
    flags={'gateway_predecessor_matches','passive_predecessor_matches','dependencies_exact',
           'db_predecessor_matches','private_db_dependency_matches','root_config_predecessor_matches','proxy_predecessor_matches',
           'store_directory_owner_matches','binding_identity_stable','protected_unchanged'}
    fields={'status','private_caller_state','store_state','store_directory_mode','backing_config_record',
            'private_gateway_record','private_db_record','proxy_record','loaded_db_state','plan_files','protected_before','protected_after',
            *flags,*counts}
    def record(row):
        return row is None or (isinstance(row,dict) and set(row)=={'sha256','bytes','mode'}
            and isinstance(row['sha256'],str) and re.fullmatch('[a-f0-9]{64}',row['sha256']) is not None
            and type(row['bytes']) is int and 0<row['bytes']<=2*1024*1024
            and type(row['mode']) is int and 0<=row['mode']<=0o7777)
    if not isinstance(value,dict) or set(value)!=fields:return False
    if (value['status'] not in ('ready','hold') or any(type(value[k]) is not bool for k in flags)
            or value['private_caller_state'] not in ('compatible_pr4732','legacy_eager_dependency','missing','unrecognized')
            or value['store_state'] not in ('verified_stock_binding','config_unrecognized','config_binding_mismatch','store_unavailable')
            or value['store_directory_mode'] is not None and (type(value['store_directory_mode']) is not int
                or not 0<=value['store_directory_mode']<=0o7777)
            or value['loaded_db_state']!='not_executed'
            or any(not record(value[k]) for k in ('backing_config_record','private_gateway_record','private_db_record','proxy_record'))):return False
    before=value['protected_before']
    if (not isinstance(before,dict) or set(before)!=set(PROTECTED) or not all(map(record,before.values()))
            or before!=value['protected_after'] or value['protected_unchanged'] is not True
            or value['binding_identity_stable'] is not True):return False
    expected={'api-v2.php':{'predecessor_sha256':'01ac5ed43961f73879cd36c2c1d387ac79fadaf53ad66d3c4aaebcc617464c61',
                          'candidate_sha256':PRIVATE_SOURCE_HASHES['v2/api-v2.php']},
        '_preview/search3-anex-candidate/app/integrations/tourvisor-operator-fuel-passive-intake.php':{
            'predecessor_sha256':'be4380b64caf4c98fc572c8db526f079a89bd04c301db5eccea3c2f2f232cc22',
            'candidate_sha256':PRIVATE_SOURCE_HASHES['app/integrations/tourvisor-operator-fuel-passive-intake.php']}}
    if value['plan_files']!=expected:return False
    if value['status']=='ready' and (not all(value[k] for k in flags)
            or value['private_caller_state']!='compatible_pr4732' or value['store_state']!='verified_stock_binding'):return False
    return (command==parse([PLAN_SOURCE,PLAN_MODE,PLAN_OPERATION])
        and all(result.get(k)==command[k] for k in command) and result.get('status')=='complete'
        and result.get('production_unchanged') is True and result.get('runtime_changed') is False
        and all(type(value[k]) is int and value[k]==0 for k in counts)
        and all(type(result.get(k)) is int and result[k]==0 for k in counts-{'runtime_writes'}))


def install_completed(command,result):
    value=result.get('passive_fuel_activation')
    fields={'status','reason','preflight','publication_lock','changed_files','rollback_files',
        'before_files','after_files','protected_after','protected_unchanged_except_target',
        'runtime_writes','staging_file_writes','backup_retained',
        'supplier_calls','database_reads','database_writes','booking_calls','lead_calls'}
    if not isinstance(value,dict) or set(value)!=fields:return False
    counts=('supplier_calls','database_reads','database_writes','booking_calls','lead_calls')
    if (any(type(value[k]) is not int or value[k]!=0 or type(result.get(k)) is not int or result[k]!=0 for k in counts)
            or result.get('status')!='complete' or result.get('production_unchanged') is not True
            or result.get('production_before')!=result.get('production_after')
            or not isinstance(result.get('production_before'),dict)
            or set(result['production_before'])!={'index.php','v2/index.php','v2/api-v2.php','v2/lead-adapter-v2.php'}
            or command!=parse([INSTALL_SOURCE,INSTALL_MODE,INSTALL_OPERATION])
            or any(result.get(k)!=command[k] for k in command)
            or value['protected_unchanged_except_target'] is not True):return False
    plan_command=parse([PLAN_SOURCE,PLAN_MODE,PLAN_OPERATION])
    if not plan_completed(plan_command,{**plan_command,'status':'complete','runtime_changed':False,
            'production_unchanged':True,**dict.fromkeys(counts,0),'passive_fuel_activation_plan':value['preflight']}):return False
    preflight=value['preflight']
    if value['status']=='hold':
        return (value['reason'] in ('preflight_hold','publication_lock_missing','publication_busy')
            and (value['reason']!='preflight_hold' or preflight['status']=='hold')
            and value['publication_lock'] in ('not_acquired','exclusive_existing')
            and value['changed_files']==value['rollback_files']==[]
            and value['before_files']==value['after_files']=={}
            and type(value['runtime_writes']) is int and value['runtime_writes']==0
            and type(value['staging_file_writes']) is int and value['staging_file_writes']==0
            and value['backup_retained'] is False and result.get('runtime_changed') is False
            and value['protected_after']==preflight['protected_before'])
    root='api-v2.php'
    passive='_preview/search3-anex-candidate/app/integrations/tourvisor-operator-fuel-passive-intake.php'
    before={root:{'sha256':'01ac5ed43961f73879cd36c2c1d387ac79fadaf53ad66d3c4aaebcc617464c61','bytes':16366,'mode':0o644},
        passive:{'sha256':'be4380b64caf4c98fc572c8db526f079a89bd04c301db5eccea3c2f2f232cc22','bytes':13823,'mode':0o644}}
    after={root:{'sha256':PRIVATE_SOURCE_HASHES['v2/api-v2.php'],'bytes':20233,'mode':0o644},
        passive:{'sha256':PRIVATE_SOURCE_HASHES['app/integrations/tourvisor-operator-fuel-passive-intake.php'],'bytes':13766,'mode':0o644}}
    expected_protected={**preflight['protected_before'],root:after[root]}
    return (value['status']=='installed_verified' and value['reason'] is None and preflight['status']=='ready'
        and value['publication_lock']=='exclusive_existing' and value['backup_retained'] is True
        and value['changed_files']==[passive,root] and value['rollback_files']==[]
        and value['before_files']==before and value['after_files']==after
        and preflight['protected_before'][root]==before[root] and value['protected_after']==expected_protected
        and type(value['runtime_writes']) is int and value['runtime_writes']==2
        and type(value['staging_file_writes']) is int and value['staging_file_writes']==2
        and result.get('runtime_changed') is True)


def stock_completed(command,result):
    if (command!=parse([STOCK_SOURCE,STOCK_MODE,STOCK_OPERATION])
            or any(result.get(k)!=command[k] for k in command)):return False
    value=result.get('passive_fuel_activation')
    if not isinstance(value,dict) or not isinstance(value.get('preflight'),dict):return False
    preflight=dict(value['preflight']);topology=preflight.pop('stock_topology',None)
    if (not isinstance(topology,dict) or set(topology)!={'status','publisher_source_sha','required_rows_verified',
            'manifest_record','nested_gateway_record'} or topology['publisher_source_sha']!=STOCK_PUBLISHER_SOURCE
            or type(topology['required_rows_verified']) is not bool
            or topology['status'] not in ('verified_stock_exclusion','gateway_present','unrecognized_manifest')):return False
    def record(row):
        return row is None or (isinstance(row,dict) and set(row)=={'sha256','bytes','mode'}
            and isinstance(row['sha256'],str) and re.fullmatch('[a-f0-9]{64}',row['sha256']) is not None
            and type(row['bytes']) is int and 0<row['bytes']<=1024*1024
            and type(row['mode']) is int and 0<=row['mode']<=0o7777)
    if not all(record(topology[k]) for k in ('manifest_record','nested_gateway_record')):return False
    if topology['status']=='verified_stock_exclusion':
        if (topology['required_rows_verified'] is not True or topology['manifest_record'] is None
                or topology['manifest_record']['mode']!=0o644
                or topology['nested_gateway_record'] is not None or preflight.get('private_gateway_record') is not None
                or preflight.get('private_caller_state')!='excluded_by_stock_publisher'):return False
        # Reuse the complete original two-file receipt validator only after this
        # separate positive publisher-topology proof, never for the sealed mode.
        preflight['private_caller_state']='compatible_pr4732'
    elif preflight.get('status')!='hold' or preflight.get('private_caller_state')=='excluded_by_stock_publisher':return False
    original=parse([INSTALL_SOURCE,INSTALL_MODE,INSTALL_OPERATION])
    normalized={**result,**original,'passive_fuel_activation':{**value,'preflight':preflight}}
    return install_completed(original,normalized)
