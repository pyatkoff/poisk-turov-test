"""Dedicated Search3 signing custody in the existing main-only INT executor.

No private bytes in SSH stdout, payloads or artifacts. No new transport, supplier,
database, bot call, arbitrary path, caller key or implicit rotation.
"""
from __future__ import annotations

import ast

TV_MODE = 'install-tv-session-gateway'
TV_SOURCE = 'f4d4dcae0c09b92a7e4024fc202ea3e23d7822db'
MODES = ('search3-signing-provision', 'search3-signing-readback', 'install-search3-producer', TV_MODE)
KEY_ID = 'search3-anytour-v1'
# Reviewed FEATURE b1a69c57 (INT4838 / producer4839 / expiry fix4841).
PRODUCER_PINS = {
    'app/integrations/anex-package-quote-runtime.php': 'bdc4b6737974935cf195f5db1477013ab95110108e2137aea684571bbd22af01',
    'app/integrations/anex-package-quote-handoff.php': 'd3d134a43b6f19676e7d046de525fc7ac206d7781aaaaf9eee375334e237bb7d',
    'app/integrations/search3-messenger-handoff.php': '48af409ca80523373e70dcb6b71089516b03b73961eb090e43d9e31115a87bb7',
    'app/integrations/anex-initial-week-gate.php': '145eff807355b50bcd381c6b3d732eb5c8ac4350f2945fb77304c9fd50673ce6',
    'v2/api-anex-search3-preview.php': '14cb8f11834ff576b941356d57a3d3d919ab8f6c4d57a1f1ad4317d1b457ec8e',
}
TV_PINS = {
    'app/integrations/search3-messenger-handoff.php': '5e0fe33e6adf3ce30451b9ffe1742cc77f791114e241fb41d92236c1d70ea85c',
    'app/integrations/tourvisor-selected-tour-handoff.php': '9dafb88cab92e19376e120c80dd5dfecc975730cca7e7039edc419bf1ffa57f3',
    'app/integrations/tourvisor-interactive-client.php': '01543d46b8cc68d4fd0ce030b1b35b93493b368a8bb1efe4235b545581dba954',
    'app/integrations/tourvisor-preview-gateway.php': 'eeddf0e80770f37c6c4bba49506b4624b8a1d380fa287f6f885be445c2c18e65',
    'v2/api-anex-search3-preview.php': '9eb518005ffae3245c8f1fb549737558e62990608a17c47979f51f51bb8e6769',
}
TV_DEPENDENCIES = {p: PRODUCER_PINS[p] for p in (
    'app/integrations/anex-package-quote-runtime.php',
    'app/integrations/anex-package-quote-handoff.php',
    'app/integrations/anex-initial-week-gate.php')}
TV_PREVIOUS = {
    'app/integrations/search3-messenger-handoff.php': PRODUCER_PINS['app/integrations/search3-messenger-handoff.php'],
    'api-anex-search3-preview.php': PRODUCER_PINS['v2/api-anex-search3-preview.php'],
    'app/integrations/tourvisor-selected-tour-handoff.php': None,
    'app/integrations/tourvisor-interactive-client.php': None,
    'app/integrations/tourvisor-preview-gateway.php': None,
}
TV_PUBLIC = {
    'index.php': {'state': 'regular', 'sha256': '80e993e80a4c3e11612187e90ffd8cfdadce08657981db19429f882ed2de4c6f'},
    'api-v2.php': {'state': 'regular', 'sha256': '90ddd43020aff509430b6ff85887a267ea4f0b30acc2213c82d7dcce0eb862f2'},
    'v2/index.php': {'state': 'absent'}, 'v2/api-v2.php': {'state': 'absent'},
    'v2/lead-adapter-v2.php': {'state': 'absent'},
}


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
        need(len(parts) == 3, 'search3_signing_command_shape')
        source, mode, operation = parts
        need(core.SHA_RE.fullmatch(source) is not None, 'search3_signing_source')
        prefix = ('int-anex-tv-session-gateway-install-' if mode == TV_MODE else
                  'int-anex-search3-producer-install-' if mode == 'install-search3-producer' else 'int-anex-search3-signing-')
        need(core.OP_RE.fullmatch(operation) is not None
             and operation.startswith(prefix), 'search3_signing_operation')
        return {'source_sha': source, 'mode': mode, 'operation_id': operation}

    core.parse_command = parse


PHP = r'''
declare(strict_types=1);error_reporting(0);ini_set('display_errors','0');umask(0077);
function s3fail(string $code): never {throw new RuntimeException($code);}
function s3file(string $path,int $limit): string {
    clearstatcache(true,$path);
    if(!is_file($path)||is_link($path)||realpath($path)!==$path||filesize($path)>$limit) s3fail('custody_file');
    $raw=file_get_contents($path);if(!is_string($raw))s3fail('custody_read');return $raw;
}
function s3write(string $path,string $raw): void {
    $f=fopen($path,'x');if($f===false)s3fail('custody_create');
    try {if(fwrite($f,$raw)!==strlen($raw)||!fflush($f)||(function_exists('fsync')&&!fsync($f)))s3fail('custody_write');}
    finally {fclose($f);}
    if((fileperms($path)&0777)!==0600)s3fail('custody_mode');
}
function s3lint(string $path): void {
    $pipes=[];$proc=proc_open([PHP_BINARY,'-n','-l',$path],
        [0=>['file','/dev/null','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if(!is_resource($proc))s3fail('custody_config_lint');
    stream_get_contents($pipes[1]);stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
    if(proc_close($proc)!==0)s3fail('custody_config_lint');
}
try {
    if(count($argv)!==5||!in_array($argv[1],['search3-signing-provision','search3-signing-readback'],true))s3fail('custody_arguments');
    [$unused,$mode,$keyFile,$config,$operationDir]=$argv;
    $create=$mode==='search3-signing-provision';
    foreach([dirname($keyFile),$operationDir] as $dir) {
        if(!is_dir($dir)||is_link($dir)||realpath($dir)!==$dir||(fileperms($dir)&0077)!==0)s3fail('custody_directory');
    }
    $rawConfig=s3file($config,65536);$configMode=fileperms($config)&0777;
    if(($configMode&0022)!==0||stat(dirname($config))['dev']!==stat($operationDir)['dev'])s3fail('custody_config_mode');
    s3lint($config);
    $tokens=token_get_all($rawConfig);$clean=[];$inside=false;$opened=false;
    foreach($tokens as $t) {
        if(is_array($t)&&in_array($t[0],[T_RETURN,T_EXIT,T_HALT_COMPILER],true))s3fail('custody_config_shape');
        if(is_array($t)&&$t[0]===T_OPEN_TAG){$inside=true;$opened=true;}
        if(is_array($t)&&$t[0]===T_CLOSE_TAG)$inside=false;
        if(is_array($t)&&$t[0]===T_INLINE_HTML&&trim($t[1])!=='')s3fail('custody_config_shape');
        if(!is_array($t)||!in_array($t[0],[T_WHITESPACE,T_COMMENT,T_DOC_COMMENT],true))$clean[]=$t;
    }
    if(!$opened)s3fail('custody_config_shape');
    $expected=var_export($keyFile,true);$definitions=[];
    foreach($clean as $i=>$token) {
        if(!is_array($token))continue;
        if($token[0]===T_CONST&&($clean[$i+1][1]??null)==='ANYTOUR_SEARCH3_HANDOFF_KEY_FILE')s3fail('custody_pointer_conflict');
        if($token[0]!==T_STRING||strtolower($token[1])!=='define'||($clean[$i+1]??null)!=='(')continue;
        $name=$clean[$i+2][1]??null;
        if(!in_array($name,["'ANYTOUR_SEARCH3_HANDOFF_KEY_FILE'",'"ANYTOUR_SEARCH3_HANDOFF_KEY_FILE"'],true))continue;
        if(($clean[$i+3]??null)!==','||($clean[$i+4][0]??null)!==T_CONSTANT_ENCAPSED_STRING
            ||($clean[$i+5]??null)!==')'||($clean[$i+6]??null)!==';')s3fail('custody_pointer_conflict');
        $definitions[]=$clean[$i+4][1];
    }
    if(count($definitions)>1||($definitions!==[]&&$definitions!==[$expected]))s3fail('custody_pointer_conflict');
    $pointerReady=false;
    if($definitions!==[]) {
        // Textual presence is not execution: accept only our canonical top-level tail.
        $guard="if (!defined('ANYTOUR_SEARCH3_HANDOFF_KEY_FILE')) define('ANYTOUR_SEARCH3_HANDOFF_KEY_FILE', ".$expected.");";
        $canonical=[];
        foreach(token_get_all('<?php '.$guard) as $t) {
            if(is_array($t)&&in_array($t[0],[T_OPEN_TAG,T_WHITESPACE,T_COMMENT,T_DOC_COMMENT],true))continue;
            $canonical[]=is_array($t)?[$t[0],$t[1]]:$t;
        }
        $normal=array_map(fn($t)=>is_array($t)?[$t[0],$t[1]]:$t,$clean);
        $braces=0;$parens=0;
        foreach($normal as $i=>$t) {
            $previous=$normal[$i-1]??null;
            if($braces===0&&$parens===0&&is_array($t)&&$t[0]===T_IF
                &&($previous===';'||$previous==='}'||(is_array($previous)&&$previous[0]===T_OPEN_TAG))
                &&array_slice($normal,$i,count($canonical))===$canonical) {
                $tail=array_slice($normal,$i+count($canonical));
                if($tail===[]||(count($tail)===1&&is_array($tail[0])&&$tail[0][0]===T_CLOSE_TAG))$pointerReady=true;
            }
            if($t==='{')$braces++;if($t==='}')$braces--;
            if($t==='(')$parens++;if($t===')')$parens--;
        }
        if(!$pointerReady)s3fail('custody_pointer_conflict');
    }
    if(!$create&&!$pointerReady)s3fail('custody_pointer_missing');
    if(!function_exists('sodium_crypto_sign_keypair'))s3fail('custody_sodium');
    $created=false;
    if(!file_exists($keyFile)&&!is_link($keyFile)) {
        if(!$create)s3fail('custody_key_missing');
        $kp=sodium_crypto_sign_keypair();$now=time();
        $key=['key_id'=>'search3-anytour-v1','secret_key_base64'=>base64_encode(sodium_crypto_sign_secretkey($kp)),
            'not_before'=>$now-60,'not_after'=>$now+90*86400];
        s3write($keyFile,json_encode($key,JSON_THROW_ON_ERROR)."\n");$created=true;
        sodium_memzero($kp);
    }
    if((fileperms($keyFile)&0777)!==0600)s3fail('custody_key_mode');
    $key=json_decode(s3file($keyFile,2048),true,8,JSON_THROW_ON_ERROR);
    if(!is_array($key)||count($key)!==4||array_diff(array_keys($key),['key_id','secret_key_base64','not_before','not_after'])
        ||($key['key_id']??null)!=='search3-anytour-v1'||!is_string($key['secret_key_base64']??null)
        ||!is_int($key['not_before']??null)||!is_int($key['not_after']??null)
        ||time()<$key['not_before']||time()>=$key['not_after'])s3fail('custody_key_invalid');
    $sk=base64_decode($key['secret_key_base64'],true);
    if(!is_string($sk)||strlen($sk)!==64||base64_encode($sk)!==$key['secret_key_base64'])s3fail('custody_key_invalid');
    $derived=sodium_crypto_sign_seed_keypair(substr($sk,0,32));
    if(!hash_equals(sodium_crypto_sign_secretkey($derived),$sk))s3fail('custody_key_invalid');
    $public=base64_encode(sodium_crypto_sign_publickey($derived));sodium_memzero($derived);sodium_memzero($sk);
    $configured=false;
    if(!$pointerReady) {
        $addition="\n".($inside?'':"<?php\n")."if (!defined('ANYTOUR_SEARCH3_HANDOFF_KEY_FILE')) define('ANYTOUR_SEARCH3_HANDOFF_KEY_FILE', ".$expected.");\n";
        $next=$rawConfig.$addition;$temp=$operationDir.'/search3-config-staged.php';
        s3write($operationDir.'/search3-config-before.php',$rawConfig);
        s3write($temp,$next);
        s3lint($temp);
        if(s3file($config,65536)!==$rawConfig)s3fail('custody_config_changed');
        if(!chmod($temp,$configMode)||!rename($temp,$config)||s3file($config,65536)!==$next)s3fail('custody_config_write');
        $configured=true;
    }
    echo json_encode(['ok'=>true,'state'=>'ready','created'=>$created,'pointer_configured'=>$configured,
        'key_id'=>$key['key_id'],'producer'=>'search3','project'=>'anytour','public_key'=>$public,
        'not_before'=>$key['not_before'],'not_after'=>$key['not_after']],JSON_THROW_ON_ERROR);
}catch(Throwable $error){
    $code=$error->getMessage();
    echo json_encode(['ok'=>false,'error'=>preg_match('/\Acustody_[a-z_]{1,40}\z/D',$code)?$code:'custody_unavailable']);exit(1);
}
'''


REMOTE = r'''
def search3_signing_custody():
    directory=private/'search3-signing'
    if (private.resolve()!=private or private.is_symlink()
            or private.stat().st_mode&0o077): fail('search3_signing_private_root')
    if mode=='search3-signing-provision': directory.mkdir(mode=0o700,exist_ok=True)
    if (not directory.is_dir() or directory.is_symlink() or directory.resolve()!=directory
            or directory.stat().st_mode&0o077): fail('search3_signing_directory')
    key=directory/'anytour-v1.json'
    config=runtime/'.anex-private.php'
    if (not safe_file(config,65536) or config.resolve()!=config): fail('search3_signing_config')
    run=subprocess.run(['php','-r',SEARCH3_SIGNING_PHP,mode,str(key),str(config),str(op)],
        capture_output=True,text=True,timeout=30)
    try: data=json.loads(run.stdout)
    except Exception: fail('search3_signing_output')
    if (run.returncode!=0 or not isinstance(data,dict) or data.get('ok') is not True):
        value=data.get('error') if isinstance(data,dict) else None
        fail(value if isinstance(value,str) and re.fullmatch(r'custody_[a-z_]{1,40}',value) else 'search3_signing_unavailable')
    expected={'ok','state','created','pointer_configured','key_id','producer','project','public_key','not_before','not_after'}
    if (set(data)!=expected or data['state']!='ready' or data['key_id']!='search3-anytour-v1'
            or data['producer']!='search3' or data['project']!='anytour'
            or type(data['created']) is not bool or type(data['pointer_configured']) is not bool
            or type(data['not_before']) is not int or type(data['not_after']) is not int): fail('search3_signing_public_shape')
    try: public=base64.b64decode(data['public_key'],validate=True)
    except Exception: fail('search3_signing_public_key')
    if len(public)!=32 or base64.b64encode(public).decode()!=data['public_key']: fail('search3_signing_public_key')
    if mode=='search3-signing-readback' and (data['created'] or data['pointer_configured']): fail('search3_signing_readback_write')
    return data
'''

DISPATCH = r'''
    if mode=='install-search3-producer':
        if any(files.get(p)!=sha for p,sha in SEARCH3_PRODUCER_PINS.items()): fail('search3_producer_source_pin')
        dependency=runtime/'app/integrations/anex-initial-week-gate.php'
        if (not safe_file(dependency,2*1024*1024)
                or hashlib.sha256(dependency.read_bytes()).hexdigest()!=SEARCH3_PRODUCER_PINS['app/integrations/anex-initial-week-gate.php']):
            fail('search3_producer_dependency_drift')
        result['install']=install_anex_preview(stage,files,op)
        result['production_after']=fingerprints()
        if result['production_after']!=before: fail('production_drift')
        result['status']='installed';result['supplier_calls']=0;result['database_writes']=0
        result['runtime_changed']=result['install']['changed_files']>0
        result['public_ui_entrypoints_unchanged']=True
    if mode in ('search3-signing-provision','search3-signing-readback'):
        required=('app/integrations/search3-messenger-handoff.php','app/integrations/anex-package-quote-handoff.php','v2/api-anex-search3-preview.php')
        if any(relative not in files for relative in required): fail('search3_signing_source_dependencies')
        result['signing']=search3_signing_custody()
        result['production_after']=fingerprints()
        if result['production_after']!=before: fail('production_drift')
        result['status']='complete'
        result['supplier_calls']=0;result['database_writes']=0
        result['public_ui_entrypoints_unchanged']=True
        result['private_key_exported']=False
'''


def activate(core, command):
    if command.get('mode') not in MODES:
        return
    if command.get('mode') == TV_MODE:
        return activate_tv(core, command)
    expected = core.parse_command(core.PREFIX + ' '.join(str(command.get(k, '')) for k in ('source_sha', 'mode', 'operation_id')))
    need(command == expected, 'search3_signing_shape')
    remote = core.REMOTE
    definition = 'def db_summary(provider):\n'
    dispatch = "    if mode in ('install-runtime','install-anex-preview','install-andromeda-preview','install-andromeda-quote-preview'):\n"
    collector = "    if mode not in ('reconcile',"
    selected = "    backup_root=op/'backup';backup_root.mkdir(mode=0o700)\n    changed=[]\n    endpoint_expected=None\n"
    rollback = "    if mode in ('install-runtime','install-anex-preview','install-andromeda-preview') and install_started:\n"
    need(remote.count(definition) == 1 and remote.count(dispatch) == 1
         and remote.count(collector) == 2 and remote.count(selected) == 1
         and remote.count(rollback) == 1, 'search3_signing_stock_drift')
    remote = remote.replace(definition, 'SEARCH3_SIGNING_PHP=' + repr(PHP) + '\nSEARCH3_PRODUCER_PINS=' + repr(PRODUCER_PINS) + '\n' + REMOTE + definition, 1)
    remote = remote.replace(selected, "    if mode=='install-search3-producer':\n        selected=[(p,p) for p in SEARCH3_PRODUCER_PINS if p.startswith('app/integrations/') and not p.endswith('anex-initial-week-gate.php')]+selected\n" + selected, 1)
    remote = remote.replace(rollback, rollback.replace("('install-runtime',", "('install-search3-producer','install-runtime',"), 1)
    remote = remote.replace(dispatch, DISPATCH + dispatch, 1)
    remote = remote.replace(collector, "    if mode not in ('install-search3-producer','search3-signing-provision','search3-signing-readback','reconcile',")
    ast.parse(remote)
    core.REMOTE = remote


TV_REMOTE = r'''
def tv_gateway_parents(base,p):
    parent=p.parent
    while parent!=base:
        if parent.is_symlink() or (parent.exists() and (not parent.is_dir() or parent.resolve()!=parent)):
            fail('tv_gateway_parent_type')
        parent=parent.parent
    if base.is_symlink() or not base.is_dir() or base.resolve()!=base:fail('tv_gateway_parent_type')

def tv_gateway_public():
    import stat
    out={}
    for relative in TV_PUBLIC:
        p=project/relative
        tv_gateway_parents(project,p)
        try: info=p.lstat()
        except FileNotFoundError:
            out[relative]={'state':'absent'}
            continue
        if (not stat.S_ISREG(info.st_mode) or p.is_symlink() or info.st_nlink!=1
                or p.resolve()!=p or not safe_file(p,2*1024*1024)):
            fail('tv_gateway_public_type')
        out[relative]={'state':'regular','sha256':hashlib.sha256(p.read_bytes()).hexdigest()}
    return out

def tv_gateway_preflight():
    if source!=TV_SOURCE:fail('tv_gateway_source')
    if any(files.get(p)!=sha for p,sha in TV_PINS.items()):fail('tv_gateway_source_pin')
    if tv_gateway_public()!=TV_PUBLIC:fail('tv_gateway_public_drift')
    for relative,expected in {**TV_PREVIOUS,**TV_DEPENDENCIES}.items():
        p=runtime/relative
        tv_gateway_parents(runtime,p)
        try: info=p.lstat()
        except FileNotFoundError:
            if expected is not None:fail('tv_gateway_predecessor')
            continue
        if expected is None:fail('tv_gateway_expected_absent')
        if (not safe_file(p,2*1024*1024) or p.resolve()!=p or info.st_nlink!=1
                or hashlib.sha256(p.read_bytes()).hexdigest()!=expected):
            fail('tv_gateway_predecessor')
'''

TV_DISPATCH = r'''
    if mode==TV_GATEWAY_MODE:
        tv_gateway_preflight()
        result['tv_gateway_public_before']=tv_gateway_public()
        result['install']=install_anex_preview(stage,files,op)
        result['tv_gateway_public_after']=tv_gateway_public()
        if result['tv_gateway_public_after']!=TV_PUBLIC:fail('tv_gateway_public_drift')
        result['production_after']=fingerprints()
        if result['production_after']!=before:fail('production_drift')
        result['status']='installed'
        result['supplier_calls']=0;result['database_writes']=0
        result['runtime_changed']=True
        result['tv_gateway']={'source_sha':TV_SOURCE,'files':TV_PINS,'activation_performed':False,
            'configuration_changed':False,'signing_custody_changed':False}
        result['public_ui_entrypoints_unchanged']=True
'''


def completed_tv(command, result):
    proof = {'source_sha': TV_SOURCE, 'files': TV_PINS, 'activation_performed': False,
             'configuration_changed': False, 'signing_custody_changed': False}
    install = result.get('install')
    endpoint = {'source':'v2/api-anex-search3-preview.php','target':'api-anex-search3-preview.php',
                'sha256':TV_PINS['v2/api-anex-search3-preview.php'],'changed':True}
    dep = 'app/integrations/anex-initial-week-gate.php'
    dependency = {'source':dep,'target':dep,'sha256':TV_DEPENDENCIES[dep],'changed':False}
    public = {p:v.get('sha256') for p,v in TV_PUBLIC.items() if p != 'api-v2.php'}
    manifest = install.get('manifest_sha256') if isinstance(install,dict) else None
    return (command.get('source_sha') == TV_SOURCE
            and all(result.get(k) == command.get(k) for k in ('source_sha', 'mode', 'operation_id'))
            and type(result.get('schema_version')) is int and result['schema_version'] == 1
            and result.get('status') == 'installed'
            and isinstance(install, dict) and install.get('source_sha') == TV_SOURCE
            and install.get('status') == 'installed' and install.get('endpoint') == endpoint
            and install.get('dependency') == dependency
            and isinstance(manifest,str) and len(manifest) == 64
            and all(c in '0123456789abcdef' for c in manifest)
            and install.get('files') == 6 and install.get('changed_files') == 5 and install.get('created_files') == 3
            and result.get('tv_gateway') == proof
            and result.get('tv_gateway_public_before') == TV_PUBLIC == result.get('tv_gateway_public_after')
            and result.get('production_before') == public == result.get('production_after')
            and all(type(result.get(k)) is int and result[k] == 0
                    for k in ('supplier_calls', 'database_writes', 'booking_calls', 'lead_calls'))
            and result.get('public_ui_entrypoints_unchanged') is True and result.get('runtime_changed') is True)


def activate_tv(core, command):
    expected = core.parse_command(core.PREFIX + ' '.join(str(command.get(k, '')) for k in ('source_sha','mode','operation_id')))
    need(command == expected, 'tv_gateway_shape')
    remote = core.REMOTE
    definition = 'def db_summary(provider):\n'
    dispatch = "    if mode in ('install-runtime','install-anex-preview','install-andromeda-preview','install-andromeda-quote-preview'):\n"
    rollback = "    if mode in ('install-runtime','install-anex-preview','install-andromeda-preview') and install_started:\n"
    begin = remote.index('def install_anex_preview(stage,files,op):\n')
    end = remote.index('def install_andromeda_preview(stage,files,op):\n', begin)
    install = remote[begin:end]
    selected = "    backup_root=op/'backup';backup_root.mkdir(mode=0o700)\n"
    prewrite = "    install_started=True\n    for target_relative in changed:\n"
    need(install.count(selected) == 1 and install.count(prewrite) == 1
         and remote.count(definition) == 1 and remote.count(dispatch) == 1
         and remote.count(rollback) == 1 and remote.count("    if mode not in ('reconcile',") == 2,
         'tv_gateway_stock_drift')
    install = install.replace(selected,
        "    if mode==TV_GATEWAY_MODE:\n"
        "        selected=[(p,p) for p in TV_PINS if p.startswith('app/integrations/')]+selected\n"
        "        tv_gateway_preflight()\n" + selected, 1)
    install = install.replace(prewrite, "    if mode==TV_GATEWAY_MODE: tv_gateway_preflight()\n" + prewrite, 1)
    remote = remote[:begin] + install + remote[end:]
    constants = ('TV_GATEWAY_MODE=' + repr(TV_MODE) + '\nTV_SOURCE=' + repr(TV_SOURCE)
                 + '\nTV_PINS=' + repr(TV_PINS) + '\nTV_PREVIOUS=' + repr(TV_PREVIOUS)
                 + '\nTV_DEPENDENCIES=' + repr(TV_DEPENDENCIES) + '\nTV_PUBLIC=' + repr(TV_PUBLIC) + '\n')
    remote = remote.replace(definition, constants + TV_REMOTE + '\n' + definition, 1)
    remote = remote.replace(dispatch, TV_DISPATCH + dispatch, 1)
    remote = remote.replace(rollback, rollback.replace("('install-runtime',", "('install-tv-session-gateway','install-runtime',"), 1)
    remote = remote.replace("    if mode not in ('reconcile',", "    if mode not in ('install-tv-session-gateway','reconcile',")
    ast.parse(remote)
    original_complete = core.completed_result
    def complete(cmd, result):
        return completed_tv(cmd, result) if cmd.get('mode') == TV_MODE else original_complete(cmd, result)
    core.completed_result = complete
    core.REMOTE = remote
