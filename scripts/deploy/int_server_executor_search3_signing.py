"""Dedicated Search3 signing custody in the existing main-only INT executor.

No private bytes in SSH stdout, payloads or artifacts. No new transport, supplier,
database, bot call, arbitrary path, caller key or implicit rotation.
"""
from __future__ import annotations

import ast

TV_CONTEXT_MODE = 'install-tv-context-revocation'
TV_CONTEXT_OPERATION = 'int-anex-tv-context-revocation-install-20261025-v1'
TV_CONTEXT_SOURCE = '95b70987966675e385809ee04cb2e2ea8ea4f4b6'
TV_CONTEXT_PATH = 'app/integrations/tourvisor-preview-gateway.php'
TV_CONTEXT_SHA = 'a68e7a30e2f5565647e690d915c4e0566c123719c830594782fac99e77991f96'

TV_CACHE_MODE = 'install-tv-cache-revocation'
TV_CACHE_OPERATION = 'int-anex-tv-cache-revocation-install-20261025-v1'
TV_CACHE_SOURCE = 'a4a12e6a84d39397b2457bc3acdde0a738b8b744'
TV_CACHE_PATH = 'app/integrations/tourvisor-preview-gateway.php'
TV_CACHE_SHA = 'c97fc0c2f86381a434ccfa61fa4f7a857abf704f52e58fe9b265dc04ccc945c6'

TV_DIAGNOSTIC_MODE = 'install-tv-client-diagnostics'
TV_DIAGNOSTIC_OPERATION = 'int-anex-tv-client-diagnostics-install-20261025-v1'
TV_DIAGNOSTIC_SOURCE = '771e3834ed8b232b21a33e68cc0dda97a82a32f0'
TV_DIAGNOSTIC_PATH = 'app/integrations/tourvisor-interactive-client.php'
TV_DIAGNOSTIC_SHA = '302fbebad64a304254bbc8944b96c06678eb57ee4fe716355469d53226721e44'
TV_ENABLED_CANONICAL = '16049bccb2a9908c54dd25c998ddc2c6c0b6f0edecb07e55bca88faeb1c62d03'
TV_ENABLED_CONFIG_SHA = 'faea72b846d201013a6426485996c0a2c022148ec897ae3092015c0946fb3d27'
TV_MODE = 'install-tv-session-gateway'
TV_ENABLE_MODE = 'enable-tv-session-gateway'
TV_RECOVERY_MODE = 'recover-tv-session-gateway'
TV_RECOVERY_OPERATION = 'int-anex-tv-session-gateway-recovery-20261025-v1'
TV_FAILURE_OPERATION = 'int-anex-tv-session-gateway-enable-20261024-v1'
TV_FAILURE_CANONICAL = '1e27a66c3b942e0ad7103673314fc2aa6e29d98f93ec8e3dc40d945bed18e8b4'
TV_FAILURE_CONTROL = '0456579020e8cbf9b888648b7a8e9921766bb463'
TV_FAILURE_RUN = 40136204936
TV_FAILURE_MANIFEST = '6f4c6e21b8a74d334d5085b289e4ef614dcf1d78f9ee04f782dd90d8abce5fbe'
TV_PARENT_OPERATION = 'int-anex-tv-session-gateway-install-20261024-v1'
TV_PARENT_CANONICAL = 'd5be638399918ca5cabf2ba2e22e768c9e1e5a99c416d032272d65664a5e42b9'
TV_SOURCE = 'f4d4dcae0c09b92a7e4024fc202ea3e23d7822db'
MODES = ('search3-signing-provision', 'search3-signing-readback', 'install-search3-producer', TV_MODE, TV_ENABLE_MODE, TV_RECOVERY_MODE, TV_DIAGNOSTIC_MODE, TV_CACHE_MODE, TV_CONTEXT_MODE)
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
    'app/integrations/tourvisor-selected-tour-handoff.php': '9dafb88cab92e19376e120c80dd5dfecc975730cca7e7039edc419bf1ffa57f3',
    'app/integrations/tourvisor-interactive-client.php': '01543d46b8cc68d4fd0ce030b1b35b93493b368a8bb1efe4235b545581dba954',
    'app/integrations/tourvisor-preview-gateway.php': 'eeddf0e80770f37c6c4bba49506b4624b8a1d380fa287f6f885be445c2c18e65',
    'app/integrations/search3-messenger-handoff.php': '5e0fe33e6adf3ce30451b9ffe1742cc77f791114e241fb41d92236c1d70ea85c',
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
        prefix = ('int-anex-tv-context-revocation-install-' if mode == TV_CONTEXT_MODE else
                  'int-anex-tv-cache-revocation-install-' if mode == TV_CACHE_MODE else
                  'int-anex-tv-client-diagnostics-install-' if mode == TV_DIAGNOSTIC_MODE else
                  'int-anex-tv-session-gateway-recovery-' if mode == TV_RECOVERY_MODE else
                  'int-anex-tv-session-gateway-enable-' if mode == TV_ENABLE_MODE else
                  'int-anex-tv-session-gateway-install-' if mode == TV_MODE else
                  'int-anex-search3-producer-install-' if mode == 'install-search3-producer' else 'int-anex-search3-signing-')
        need(core.OP_RE.fullmatch(operation) is not None
             and operation.startswith(prefix), 'search3_signing_operation')
        if mode == TV_CONTEXT_MODE:
            need(operation == TV_CONTEXT_OPERATION, 'tv_context_single_operation')
            need(source == TV_CONTEXT_SOURCE, 'tv_context_source')
        if mode == TV_CACHE_MODE:
            need(operation == TV_CACHE_OPERATION, 'tv_cache_single_operation')
            need(source == TV_CACHE_SOURCE, 'tv_cache_source')
        if mode == TV_DIAGNOSTIC_MODE:
            need(operation == TV_DIAGNOSTIC_OPERATION, 'tv_diagnostic_single_operation')
            need(source == TV_DIAGNOSTIC_SOURCE, 'tv_diagnostic_source')
        if mode == TV_RECOVERY_MODE:
            need(operation == TV_RECOVERY_OPERATION, 'tv_recovery_single_operation')
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
    if command.get('mode') == TV_CONTEXT_MODE:
        return activate_tv_context(core, command)
    if command.get('mode') == TV_CACHE_MODE:
        return activate_tv_cache(core, command)
    if command.get('mode') == TV_DIAGNOSTIC_MODE:
        return activate_tv_diagnostics(core, command)
    if command.get('mode') == TV_RECOVERY_MODE:
        return activate_tv_recovery(core, command)
    if command.get('mode') == TV_ENABLE_MODE:
        return activate_tv_enable(core, command)
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


TV_FLAG_LINE = b"\nif (!defined('ANYTOUR_SEARCH3_TV_GATEWAY_ENABLED')) define('ANYTOUR_SEARCH3_TV_GATEWAY_ENABLED', true);\n"
TV_FLAG_PHP = r'''
declare(strict_types=1);error_reporting(0);ini_set('display_errors','0');
function tfail(string $code):never{throw new RuntimeException($code);}
function tneed(bool $ok,string $code='tv_flag_config_shape'):void{if(!$ok)tfail($code);}
function tname(array $tokens,int &$i):string{
    $t=$tokens[$i++]??null;tneed(is_array($t)&&$t['id']===T_CONSTANT_ENCAPSED_STRING);
    $raw=$t['text'];$q=$raw[0]??'';
    tneed(in_array($q,["'",'"'],true)&&substr($raw,-1)===$q);
    $name=substr($raw,1,-1);tneed(preg_match('/\A[A-Z][A-Z0-9_]{0,127}\z/D',$name)===1);
    return $name;
}
function tsymbol(array $tokens,int &$i,string $value):void{
    tneed(($tokens[$i++]['text']??null)===$value,'tv_flag_syntax_shape');
}
function tword(array $tokens,int &$i,string $word):void{
    $t=$tokens[$i++]??null;tneed(is_array($t)&&$t['id']===T_STRING&&strtolower($t['text'])===$word,'tv_flag_statement_shape');
}
function tdefine(array $tokens,int &$i):string{
    tword($tokens,$i,'define');tsymbol($tokens,$i,'(');$name=tname($tokens,$i);tsymbol($tokens,$i,',');
    $v=$tokens[$i++]??null;
    tneed(is_array($v)&&(in_array($v['id'],[T_CONSTANT_ENCAPSED_STRING,T_LNUMBER,T_DNUMBER],true)
        ||($v['id']===T_STRING&&in_array(strtolower($v['text']),['true','false','null'],true))),'tv_flag_value_shape');
    tsymbol($tokens,$i,')');tsymbol($tokens,$i,';');return $name;
}
try{
    tneed(count($argv)===2,'tv_flag_arguments');
    tneed(function_exists('token_get_all'),'tv_flag_tokenizer_unavailable');
    $raw=file_get_contents($argv[1]);tneed(is_string($raw)&&strlen($raw)>0&&strlen($raw)<=65536,'tv_flag_config_read');
    $tokens=[];$offset=0;$insert=null;
    foreach(token_get_all($raw,TOKEN_PARSE) as $v){
        $id=is_array($v)?$v[0]:0;$text=is_array($v)?$v[1]:$v;$end=$offset+strlen($text);
        if($id===T_OPEN_TAG){if($insert===null)$insert=$end;}
        elseif($id===T_INLINE_HTML){tneed(trim($text)==='');}
        elseif(!in_array($id,[T_WHITESPACE,T_COMMENT,T_DOC_COMMENT,T_CLOSE_TAG],true))
            $tokens[]=['id'=>$id,'text'=>$text,'end'=>$end];
        $offset=$end;
    }
    tneed(is_int($insert));$i=0;$statements=0;$pointer=false;$declared=false;$bootstrap=false;
    while($i<count($tokens)){
        $t=$tokens[$i];
        if($t['id']===T_DECLARE){
            tneed($statements===0);++$i;tsymbol($tokens,$i,'(');tword($tokens,$i,'strict_types');
            tsymbol($tokens,$i,'=');$v=$tokens[$i++]??null;
            tneed(is_array($v)&&$v['id']===T_LNUMBER&&in_array($v['text'],['0','1'],true));
            tsymbol($tokens,$i,')');tsymbol($tokens,$i,';');$insert=$tokens[$i-1]['end'];$declared=true;++$statements;continue;
        }
        if($t['id']===T_REQUIRE_ONCE){
            // Existing publisher's exact outer stub, recognized only; never follow or execute it.
            tneed(!$bootstrap&&($statements===0||($statements===1&&$declared)),'tv_flag_statement_shape');
            ++$i;tword($tokens,$i,'dirname');tsymbol($tokens,$i,'(');
            $dir=$tokens[$i++]??null;tneed(is_array($dir)&&$dir['id']===T_DIR&&$dir['text']==='__DIR__','tv_flag_value_shape');
            tsymbol($tokens,$i,',');$depth=$tokens[$i++]??null;
            tneed(is_array($depth)&&$depth['id']===T_LNUMBER&&$depth['text']==='4','tv_flag_value_shape');
            tsymbol($tokens,$i,')');tsymbol($tokens,$i,'.');$path=$tokens[$i++]??null;
            tneed(is_array($path)&&$path['id']===T_CONSTANT_ENCAPSED_STRING
                &&$path['text']==="'/.anytoour-anex/search3-preview.php'",'tv_flag_value_shape');
            tsymbol($tokens,$i,';');$bootstrap=true;++$statements;continue;
        }
        if($t['id']===T_IF){
            ++$i;tsymbol($tokens,$i,'(');tsymbol($tokens,$i,'!');tword($tokens,$i,'defined');
            tsymbol($tokens,$i,'(');$guard=tname($tokens,$i);tsymbol($tokens,$i,')');tsymbol($tokens,$i,')');
            $braced=($tokens[$i]['text']??null)==='{';if($braced)++$i;
            $name=tdefine($tokens,$i);tneed($guard===$name);if($braced)tsymbol($tokens,$i,'}');
        }else{$name=tdefine($tokens,$i);}
        if($name==='ANYTOUR_SEARCH3_TV_GATEWAY_ENABLED')tfail('tv_flag_already_defined');
        if($name==='ANYTOUR_SEARCH3_HANDOFF_KEY_FILE')$pointer=true;
        ++$statements;
    }
    tneed($pointer,'tv_flag_pointer_missing');
    echo json_encode(['ok'=>true,'offset'=>$insert,'sha256'=>hash('sha256',$raw)]);
}catch(Throwable $e){
    $allowed=['tv_flag_config_shape','tv_flag_syntax_shape','tv_flag_statement_shape','tv_flag_value_shape',
        'tv_flag_arguments','tv_flag_tokenizer_unavailable','tv_flag_config_read','tv_flag_pointer_missing','tv_flag_already_defined'];
    $code=$e instanceof ParseError?'tv_flag_php_syntax':
        (($e instanceof RuntimeException&&in_array($e->getMessage(),$allowed,true))?$e->getMessage():'tv_flag_config_shape');
    echo json_encode(['ok'=>false,'code'=>$code]);
    exit(1);
}
'''

TV_ENABLE_REMOTE = r'''
def tv_enable_preflight():
    if source!=TV_SOURCE:fail('tv_enable_source')
    if any(files.get(p)!=sha for p,sha in {**TV_PINS,**TV_DEPENDENCIES}.items()):fail('tv_enable_source_pin')
    if tv_gateway_public()!=TV_PUBLIC:fail('tv_enable_public_drift')
    for relative,expected in {**TV_PINS,**TV_DEPENDENCIES}.items():
        target=runtime/(relative if relative.startswith('app/') else 'api-anex-search3-preview.php')
        tv_gateway_parents(runtime,target)
        if (not safe_file(target,2*1024*1024) or target.resolve()!=target or target.stat().st_nlink!=1
                or hashlib.sha256(target.read_bytes()).hexdigest()!=expected):fail('tv_enable_runtime_pin')
    parent=private/TV_PARENT_OPERATION/'result.json'
    if (not safe_file(parent,16384) or parent.resolve()!=parent or parent.stat().st_nlink!=1
            or parent.stat().st_uid!=os.getuid() or parent.stat().st_mode&0o777!=0o600):fail('tv_enable_parent_receipt')
    prior=json.loads(parent.read_bytes())
    digest=hashlib.sha256(json.dumps(prior,sort_keys=True,separators=(',',':')).encode()).hexdigest()
    if digest!=TV_PARENT_CANONICAL:fail('tv_enable_parent_receipt')

def tv_enable_config_identity(path):
    if (not safe_file(path,65536) or path.resolve()!=path or path.stat().st_nlink!=1
            or path.stat().st_uid!=os.getuid() or path.stat().st_mode&0o022):fail('tv_enable_config_type')
    s=path.stat()
    return (s.st_dev,s.st_ino,s.st_size,s.st_mtime_ns,s.st_ctime_ns,s.st_mode,s.st_uid,s.st_gid,s.st_nlink)

def tv_enable_http_ready():
    import urllib.request
    class NoRedirect(urllib.request.HTTPRedirectHandler):
        def redirect_request(self,*args,**kwargs):return None
    opener=urllib.request.build_opener(NoRedirect)
    request=urllib.request.Request('https://anytoour.ru/_preview/search3-anex-candidate/api-anex-search3-preview.php?action=tv_token',
        headers={'Origin':'https://anytoour.ru','Sec-Fetch-Site':'same-origin','Accept':'application/json'})
    try:
        with opener.open(request,timeout=15) as response:
            raw=response.read(4097)
            if response.status!=200 or len(raw)>4096:fail('tv_enable_http_shape')
            body=json.loads(raw)
            cookies=response.headers.get_all('Set-Cookie',[])
            cookie=next((c for c in cookies if c.startswith('ANYTOUR_ANEX_SEARCH3=')),'')
            lower=cookie.lower()
            if (set(body)!={'ok','csrf','provider'} or body['ok'] is not True or body['provider']!='tourvisor'
                    or not isinstance(body['csrf'],str) or re.fullmatch('[a-f0-9]{64}',body['csrf']) is None
                    or re.search(r'(?:^|;)\s*path=/_preview/search3-anex-candidate/(?:;|$)',lower) is None
                    or not all(x in lower for x in ('secure','httponly','samesite=lax'))
                    or 'no-store' not in response.headers.get('Cache-Control','')):fail('tv_enable_http_shape')
            # Discard the new anonymous session token/cookie; no customer or supplier request.
            return {'status':200,'provider':'tourvisor','csrf_exported':False,'private_cookie_scope':True,
                    'supplier_calls':0,'requests':1}
    except Exception:fail('tv_enable_http_unavailable')

def tv_enable_config(op):
    global install_started
    tv_enable_preflight()
    config=runtime/'.anex-private.php';identity=tv_enable_config_identity(config);old=config.read_bytes()
    if tv_enable_config_identity(config)!=identity:fail('tv_enable_config_drift')
    try:check=subprocess.run(['php','-r',TV_FLAG_PHP,str(config)],capture_output=True,timeout=20)
    except subprocess.TimeoutExpired:fail('tv_enable_parser_timeout')
    except OSError:fail('tv_enable_parser_unavailable')
    if len(check.stdout)>1024:fail('tv_enable_parser_output')
    try:parsed=json.loads(check.stdout)
    except Exception:fail('tv_enable_parser_output')
    if not isinstance(parsed,dict):fail('tv_enable_parser_output')
    if check.returncode!=0 or parsed.get('ok') is not True:
        codes={'tv_flag_config_shape':'tv_enable_config_shape','tv_flag_syntax_shape':'tv_enable_syntax_shape',
            'tv_flag_statement_shape':'tv_enable_statement_shape','tv_flag_value_shape':'tv_enable_value_shape',
            'tv_flag_arguments':'tv_enable_parser_arguments','tv_flag_tokenizer_unavailable':'tv_enable_tokenizer_unavailable',
            'tv_flag_config_read':'tv_enable_config_read','tv_flag_pointer_missing':'tv_enable_pointer_missing',
            'tv_flag_already_defined':'tv_enable_flag_already_defined','tv_flag_php_syntax':'tv_enable_php_syntax'}
        fail(codes.get(parsed.get('code'),'tv_enable_parser_output') if isinstance(parsed.get('code'),str) else 'tv_enable_parser_output')
    offset=parsed.get('offset')
    if (type(offset) is not int or not 5<=offset<=len(old)
            or parsed.get('sha256')!=hashlib.sha256(old).hexdigest()):fail('tv_enable_config_shape')
    candidate=old[:offset]+TV_FLAG_LINE+old[offset:]
    if len(candidate)>65536:fail('tv_enable_config_budget')
    mode=config.stat().st_mode&0o777
    backup=op/'backup';backup.mkdir(mode=0o700)
    saved=backup/'.anex-private.php';saved.write_bytes(old);os.chmod(saved,0o600)
    if saved.read_bytes()!=old:fail('tv_enable_backup')
    relative='.anex-private.php'
    install_previous[relative]={'exists':True,'sha256':hashlib.sha256(old).hexdigest(),'mode':mode}
    install_expected[relative]=hashlib.sha256(candidate).hexdigest()
    install_temps[relative]=stage_target_bytes(config,candidate,mode)
    lint=subprocess.run(['php','-l',str(install_temps[relative])],capture_output=True,timeout=20)
    if lint.returncode!=0:fail('tv_enable_config_lint')
    write_private_json(op/'tv-enable-plan.json',{'source_sha':source,'target':relative,
        'before_sha256':hashlib.sha256(old).hexdigest(),'after_sha256':hashlib.sha256(candidate).hexdigest(),
        'parent_operation':TV_PARENT_OPERATION,'status':'prepared'})
    tv_enable_preflight()
    if tv_enable_config_identity(config)!=identity or config.read_bytes()!=old:fail('tv_enable_config_drift')
    install_started=True
    os.replace(install_temps[relative],config);install_applied.append(relative);os.chmod(config,mode)
    if config.read_bytes()!=candidate or config.stat().st_mode&0o777!=mode:fail('tv_enable_readback')
    time.sleep(3)
    http=tv_enable_http_ready()
    tv_enable_preflight()
    if config.read_bytes()!=candidate:fail('tv_enable_postwrite_drift')
    return {'constant':'ANYTOUR_SEARCH3_TV_GATEWAY_ENABLED','enabled':True,'changed_files':1,
        'original_bytes_preserved':True,'signing_custody_changed':False,'parent_operation':TV_PARENT_OPERATION,
        'parent_canonical_sha256':TV_PARENT_CANONICAL,'before_sha256':hashlib.sha256(old).hexdigest(),
        'after_sha256':hashlib.sha256(candidate).hexdigest(),'before_bytes':len(old),'after_bytes':len(candidate),
        'before_mode':mode,'after_mode':mode,'http':http}
'''

TV_ENABLE_DISPATCH = r'''
    if mode==TV_ENABLE_MODE:
        result['tv_gateway_public_before']=tv_gateway_public()
        result['tv_enable']=tv_enable_config(op)
        result['tv_gateway_public_after']=tv_gateway_public()
        result['production_after']=fingerprints()
        if result['production_after']!=before:fail('production_drift')
        result['status']='enabled';result['runtime_changed']=True
        result['public_ui_entrypoints_unchanged']=True
        result['supplier_calls']=0;result['database_writes']=0
'''


def completed_enable(command,result):
    proof=result.get('tv_enable')
    fields={'constant','enabled','changed_files','original_bytes_preserved','signing_custody_changed','parent_operation',
            'parent_canonical_sha256','before_sha256','after_sha256','before_bytes','after_bytes','before_mode','after_mode','http'}
    public={p:v.get('sha256') for p,v in TV_PUBLIC.items() if p!='api-v2.php'}
    if not isinstance(proof,dict) or set(proof)!=fields:return False
    def sha(v):return isinstance(v,str) and len(v)==64 and all(c in '0123456789abcdef' for c in v)
    return (command.get('source_sha')==TV_SOURCE
        and all(result.get(k)==command.get(k) for k in ('source_sha','mode','operation_id'))
        and type(result.get('schema_version')) is int and result['schema_version']==1 and result.get('status')=='enabled'
        and proof['constant']=='ANYTOUR_SEARCH3_TV_GATEWAY_ENABLED' and proof['enabled'] is True
        and type(proof['changed_files']) is int and proof['changed_files']==1 and proof['original_bytes_preserved'] is True
        and proof['signing_custody_changed'] is False and proof['parent_operation']==TV_PARENT_OPERATION
        and proof['parent_canonical_sha256']==TV_PARENT_CANONICAL
        and all(sha(proof[k]) for k in ('before_sha256','after_sha256')) and proof['before_sha256']!=proof['after_sha256']
        and all(type(proof[k]) is int for k in ('before_bytes','after_bytes','before_mode','after_mode'))
        and proof['after_bytes']==proof['before_bytes']+len(TV_FLAG_LINE)
        and 0<proof['before_bytes']<proof['after_bytes']<=65536 and 0<=proof['after_mode']<=0o777
        and proof['before_mode']==proof['after_mode'] and proof['after_mode']&0o022==0
        and proof['http']=={'status':200,'provider':'tourvisor','csrf_exported':False,'private_cookie_scope':True,
                           'supplier_calls':0,'requests':1}
        and result.get('tv_gateway_public_before')==TV_PUBLIC==result.get('tv_gateway_public_after')
        and result.get('production_before')==public==result.get('production_after')
        and all(type(result.get(k)) is int and result[k]==0 for k in ('supplier_calls','database_writes','booking_calls','lead_calls'))
        and result.get('runtime_changed') is True and result.get('public_ui_entrypoints_unchanged') is True)


def activate_tv_enable(core,command):
    expected=core.parse_command(core.PREFIX+' '.join(str(command.get(k,'')) for k in ('source_sha','mode','operation_id')))
    need(command==expected,'tv_enable_shape')
    remote=core.REMOTE
    definition='def db_summary(provider):\n'
    dispatch="    if mode in ('install-runtime','install-anex-preview','install-andromeda-preview','install-andromeda-quote-preview'):\n"
    rollback="    if mode in ('install-runtime','install-anex-preview','install-andromeda-preview') and install_started:\n"
    need(remote.count(definition)==1 and remote.count(dispatch)==1 and remote.count(rollback)==1
         and remote.count("    if mode not in ('reconcile',")==2,'tv_enable_stock_drift')
    constants=('TV_ENABLE_MODE='+repr(TV_ENABLE_MODE)+'\nTV_SOURCE='+repr(TV_SOURCE)+'\nTV_PINS='+repr(TV_PINS)
        +'\nTV_DEPENDENCIES='+repr(TV_DEPENDENCIES)+'\nTV_PUBLIC='+repr(TV_PUBLIC)
        +'\nTV_PARENT_OPERATION='+repr(TV_PARENT_OPERATION)+'\nTV_PARENT_CANONICAL='+repr(TV_PARENT_CANONICAL)
        +'\nTV_FLAG_LINE='+repr(TV_FLAG_LINE)+'\nTV_FLAG_PHP='+repr(TV_FLAG_PHP)+'\n')
    common=TV_REMOTE[:TV_REMOTE.index('def tv_gateway_preflight():')]
    remote=remote.replace(definition,constants+common+TV_ENABLE_REMOTE+'\n'+definition,1)
    remote=remote.replace(dispatch,TV_ENABLE_DISPATCH+dispatch,1)
    remote=remote.replace(rollback,rollback.replace("('install-runtime',","('enable-tv-session-gateway','install-runtime',"),1)
    remote=remote.replace("    if mode not in ('reconcile',","    if mode not in ('enable-tv-session-gateway','reconcile',")
    ast.parse(remote)
    original_complete=core.completed_result
    core.completed_result=lambda cmd,result: completed_enable(cmd,result) if cmd.get('mode')==TV_ENABLE_MODE else original_complete(cmd,result)
    core.REMOTE=remote


TV_RECOVERY_REMOTE = r'''
_tv_failure_snapshot=None
def tv_recovery_failure_guard():
    global _tv_failure_snapshot
    old=private/TV_FAILURE_OPERATION
    if (old.is_symlink() or not old.is_dir() or old.resolve()!=old or old.stat().st_uid!=os.getuid()
            or old.stat().st_mode&0o777!=0o700):fail('tv_recovery_original_directory')
    # No original plan/backup means the pinned original parser refusal preceded its first config write.
    if {p.name for p in old.iterdir()}!={'reservation.json','result.json','source','installed-source.json'}:
        fail('tv_recovery_original_write_state')
    original_source=old/'source'
    if (original_source.is_symlink() or not original_source.is_dir() or original_source.resolve()!=original_source):
        fail('tv_recovery_original_source_type')
    temp=runtime/('..anex-private.php.'+TV_FAILURE_OPERATION+'.tmp')
    if temp.exists() or temp.is_symlink():fail('tv_recovery_original_write_state')
    snapshots=[];values={}
    for name in ('reservation.json','result.json','installed-source.json'):
        path=old/name
        limit=524288 if name=='installed-source.json' else 16384
        if (not safe_file(path,limit) or path.resolve()!=path or path.stat().st_nlink!=1
                or path.stat().st_uid!=os.getuid() or path.stat().st_mode&0o777!=0o600):fail('tv_recovery_original_file')
        def identity_of(p):
            s=p.stat();return (s.st_dev,s.st_ino,s.st_size,s.st_mtime_ns,s.st_ctime_ns,s.st_mode,s.st_uid,s.st_gid,s.st_nlink)
        identity=identity_of(path);raw=path.read_bytes()
        if identity_of(path)!=identity:fail('tv_recovery_original_drift')
        values[name]=json.loads(raw);snapshots.append((name,identity,hashlib.sha256(raw).hexdigest()))
    prior=values['result.json'];reservation=values['reservation.json']
    digest=hashlib.sha256(json.dumps(prior,sort_keys=True,separators=(',',':')).encode()).hexdigest()
    if digest!=TV_FAILURE_CANONICAL:fail('tv_recovery_original_receipt')
    installed=values['installed-source.json']
    if (not isinstance(installed,dict) or set(installed)!={'source_sha','files'}
            or installed['source_sha']!=TV_SOURCE or not isinstance(installed['files'],dict)
            or len(installed['files'])<20 or installed['files']!=files
            or hashlib.sha256(json.dumps(installed['files'],sort_keys=True,separators=(',',':')).encode()).hexdigest()!=TV_FAILURE_MANIFEST):
        fail('tv_recovery_original_source_manifest')
    if (prior.get('operation_id')!=TV_FAILURE_OPERATION or prior.get('source_sha')!=TV_SOURCE
            or prior.get('mode')!='enable-tv-session-gateway' or prior.get('status')!='unknown_no_replay'
            or prior.get('reason')!='tv_enable_config_shape'
            or prior.get('supplier_calls')!='unknown' or prior.get('database_writes')!='unknown'):
        fail('tv_recovery_original_boundary')
    if (set(reservation)!={'operation_id','source_sha','mode','reserved_at'}
            or reservation.get('operation_id')!=TV_FAILURE_OPERATION or reservation.get('source_sha')!=TV_SOURCE
            or reservation.get('mode')!='enable-tv-session-gateway'
            or type(reservation.get('reserved_at')) is not int or not 1792878816<=reservation['reserved_at']<=1792878878):
        fail('tv_recovery_original_reservation')
    snapshot=tuple(snapshots)
    if _tv_failure_snapshot is None:_tv_failure_snapshot=snapshot
    elif snapshot!=_tv_failure_snapshot:fail('tv_recovery_original_drift')
    return {'operation_id':TV_FAILURE_OPERATION,'canonical_sha256':TV_FAILURE_CANONICAL,
        'control_sha':TV_FAILURE_CONTROL,'run_id':TV_FAILURE_RUN,'source_sha':TV_SOURCE,
        'status':'unknown_no_replay','supplier_calls':'unknown','database_writes':'unknown',
        'original_prewrite_state_absent':True,'original_receipt_reservation_unchanged':True,
        'first_config_write_admitted':True,'reservation_sha256':snapshots[0][2],
        'source_manifest_sha256':TV_FAILURE_MANIFEST,'installed_source_sha256':snapshots[2][2]}

def tv_enable_preflight():
    tv_recovery_base_preflight()
    tv_recovery_failure_guard()
'''


def completed_recovery(command,result):
    proof=result.get('tv_recovery')
    if not isinstance(proof,dict):return False
    expected={'operation_id':TV_FAILURE_OPERATION,'canonical_sha256':TV_FAILURE_CANONICAL,
        'control_sha':TV_FAILURE_CONTROL,'run_id':TV_FAILURE_RUN,'source_sha':TV_SOURCE,
        'status':'unknown_no_replay','supplier_calls':'unknown','database_writes':'unknown',
        'original_prewrite_state_absent':True,'original_receipt_reservation_unchanged':True,
        'first_config_write_admitted':True,'source_manifest_sha256':TV_FAILURE_MANIFEST}
    digests=[proof.get(k) for k in ('reservation_sha256','installed_source_sha256')]
    return (command.get('mode')==TV_RECOVERY_MODE and command.get('operation_id')==TV_RECOVERY_OPERATION
        and completed_enable(command,result) and set(proof)==set(expected)|{'reservation_sha256','installed_source_sha256'}
        and all(proof.get(k)==v and type(proof.get(k)) is type(v) for k,v in expected.items())
        and all(isinstance(d,str) and len(d)==64 and all(c in '0123456789abcdef' for c in d) for d in digests))


def activate_tv_recovery(core,command):
    expected=core.parse_command(core.PREFIX+' '.join(str(command.get(k,'')) for k in ('source_sha','mode','operation_id')))
    need(command==expected,'tv_recovery_shape')
    # Reuse the existing single-flag writer; historical command programs remain byte-identical.
    internal={'source_sha':command['source_sha'],'mode':TV_ENABLE_MODE,
              'operation_id':'int-anex-tv-session-gateway-enable-render-only-v1'}
    activate_tv_enable(core,internal)
    remote=core.REMOTE
    need(remote.count(repr(TV_ENABLE_MODE))==4,'tv_recovery_mode_drift')
    remote=remote.replace(repr(TV_ENABLE_MODE),repr(TV_RECOVERY_MODE))
    native="['ok'=>true,'offset'=>$insert,'sha256'=>hash('sha256',$raw)]"
    need(TV_FLAG_PHP.count(native)==1,'tv_recovery_native_drift')
    php=TV_FLAG_PHP.replace(native,native[:-1]+",'outer_stub'=>$bootstrap]")
    anchor='TV_FLAG_PHP='+repr(TV_FLAG_PHP)
    need(remote.count(anchor)==1,'tv_recovery_php_drift')
    remote=remote.replace(anchor,'TV_FLAG_PHP='+repr(php),1)
    anchor='def tv_enable_preflight():\n'
    need(remote.count(anchor)==1,'tv_recovery_preflight_drift')
    remote=remote.replace(anchor,'def tv_recovery_base_preflight():\n',1)
    constants=('TV_FAILURE_OPERATION='+repr(TV_FAILURE_OPERATION)+'\nTV_FAILURE_CANONICAL='+repr(TV_FAILURE_CANONICAL)
        +'\nTV_FAILURE_CONTROL='+repr(TV_FAILURE_CONTROL)+'\nTV_FAILURE_RUN='+repr(TV_FAILURE_RUN)
        +'\nTV_FAILURE_MANIFEST='+repr(TV_FAILURE_MANIFEST)+'\n')
    remote=remote.replace('def db_summary(provider):\n',constants+TV_RECOVERY_REMOTE+'\ndef db_summary(provider):\n',1)
    anchor="    offset=parsed.get('offset')\n"
    need(remote.count(anchor)==1,'tv_recovery_parser_drift')
    remote=remote.replace(anchor,"    if parsed.get('outer_stub') is not True:fail('tv_recovery_current_outer_stub')\n"+anchor,1)
    anchor="        result['tv_enable']=tv_enable_config(op)\n"
    need(remote.count(anchor)==1,'tv_recovery_dispatch_drift')
    remote=remote.replace(anchor,anchor+"        result['tv_recovery']=tv_recovery_failure_guard()\n",1)
    ast.parse(remote)
    original=core.completed_result
    core.completed_result=lambda cmd,result: completed_recovery(cmd,result) if cmd.get('mode')==TV_RECOVERY_MODE else original(cmd,result)
    core.REMOTE=remote


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
    if any(files.get(p)!=sha for p,sha in {**TV_PINS,**TV_DEPENDENCIES}.items()):fail('tv_gateway_source_pin')
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


TV_DIAGNOSTIC_BOUNDARY = r'''
_tv_diagnostic_identity=None
def tv_diagnostic_boundary():
    global _tv_diagnostic_identity
    p=private/TV_RECOVERY_OPERATION/'result.json'
    tv_gateway_parents(private,p)
    if (not safe_file(p,16384) or p.resolve()!=p or p.stat().st_nlink!=1
            or p.stat().st_mode&0o777!=0o600 or p.stat().st_uid!=os.getuid()):fail('tv_diagnostic_parent_type')
    raw=p.read_bytes();r=json.loads(raw)
    canonical=hashlib.sha256(json.dumps(r,sort_keys=True,separators=(',',':')).encode()).hexdigest()
    if (canonical!=TV_ENABLED_CANONICAL or r.get('status')!='enabled'
            or r.get('operation_id')!=TV_RECOVERY_OPERATION or r.get('source_sha')!=TV_ORIGINAL_SOURCE
            or r.get('mode')!='recover-tv-session-gateway'):fail('tv_diagnostic_parent_receipt')
    config=runtime/'.anex-private.php';tv_gateway_parents(runtime,config)
    if (not safe_file(config,65536) or config.resolve()!=config or config.stat().st_nlink!=1
            or config.stat().st_mode&0o777!=0o644 or config.stat().st_uid!=os.getuid()
            or hashlib.sha256(config.read_bytes()).hexdigest()!=TV_ENABLED_CONFIG_SHA):fail('tv_diagnostic_config')
    def identity(x):
        v=x.stat();return (v.st_dev,v.st_ino,v.st_size,v.st_mtime_ns,v.st_ctime_ns,v.st_mode,v.st_uid,v.st_nlink)
    current=(identity(p),hashlib.sha256(raw).hexdigest(),identity(config))
    if _tv_diagnostic_identity is None:_tv_diagnostic_identity=current
    elif current!=_tv_diagnostic_identity:fail('tv_diagnostic_boundary_drift')
    return {'parent_operation':TV_RECOVERY_OPERATION,'parent_canonical_sha256':TV_ENABLED_CANONICAL,
            'configuration_sha256':TV_ENABLED_CONFIG_SHA,'configuration_mode':0o644,
            'receipt_and_configuration_unchanged':True}
'''


def _completed_tv_maintenance(command, result, mode, operation, source, pins):
    proof={'source_sha':source,'files':pins,'activation_performed':False,
           'configuration_changed':False,'signing_custody_changed':False}
    basis={'parent_operation':TV_RECOVERY_OPERATION,'parent_canonical_sha256':TV_ENABLED_CANONICAL,
           'configuration_sha256':TV_ENABLED_CONFIG_SHA,'configuration_mode':0o644,
           'receipt_and_configuration_unchanged':True}
    install=result.get('install');manifest=install.get('manifest_sha256') if isinstance(install,dict) else None
    public={p:v.get('sha256') for p,v in TV_PUBLIC.items() if p!='api-v2.php'}
    return (command=={'source_sha':source,'mode':mode,'operation_id':operation}
        and all(result.get(k)==v for k,v in command.items())
        and type(result.get('schema_version')) is int and result['schema_version']==1 and result.get('status')=='installed'
        and isinstance(install,dict) and install.get('status')=='installed' and install.get('source_sha')==source
        and isinstance(manifest,str) and len(manifest)==64 and all(c in '0123456789abcdef' for c in manifest)
        and all(type(install.get(k)) is int and install[k]==v for k,v in {'files':6,'changed_files':1,'created_files':0}.items())
        and install.get('endpoint')=={'source':'v2/api-anex-search3-preview.php','target':'api-anex-search3-preview.php',
            'sha256':TV_PINS['v2/api-anex-search3-preview.php'],'changed':False}
        and install.get('dependency')=={'source':'app/integrations/anex-initial-week-gate.php',
            'target':'app/integrations/anex-initial-week-gate.php','sha256':TV_DEPENDENCIES['app/integrations/anex-initial-week-gate.php'],'changed':False}
        and result.get('tv_gateway')==proof and result.get('diagnostic_basis_before')==basis==result.get('diagnostic_basis_after')
        and result.get('tv_gateway_public_before')==TV_PUBLIC==result.get('tv_gateway_public_after')
        and result.get('production_before')==public==result.get('production_after')
        and all(type(result.get(k)) is int and result[k]==0 for k in ('supplier_calls','database_writes','booking_calls','lead_calls'))
        and result.get('public_ui_entrypoints_unchanged') is True and result.get('runtime_changed') is True)


def _activate_tv_maintenance(core, command, mode, source, path, sha, previous_pins, completed):
    # Reuse the existing installer, backups, atomic replacement, lint, readback and rollback.
    # This adaptation is exclusive to the new fixed one-helper operation; historical programs stay byte-identical.
    activate_tv(core, command)
    remote=core.REMOTE
    pins=previous_pins|{path:sha}
    previous={p if p.startswith('app/') else 'api-anex-search3-preview.php':v for p,v in previous_pins.items()}
    for name,old,new in [('TV_GATEWAY_MODE',TV_MODE,mode),('TV_SOURCE',TV_SOURCE,source),
                         ('TV_PINS',TV_PINS,pins),('TV_PREVIOUS',TV_PREVIOUS,previous)]:
        anchor=name+'='+repr(old);need(remote.count(anchor)==1,'tv_diagnostic_stock_drift')
        remote=remote.replace(anchor,name+'='+repr(new),1)
    constants=('TV_RECOVERY_OPERATION='+repr(TV_RECOVERY_OPERATION)+'\nTV_ENABLED_CANONICAL='+repr(TV_ENABLED_CANONICAL)
        +'\nTV_ENABLED_CONFIG_SHA='+repr(TV_ENABLED_CONFIG_SHA)+'\nTV_ORIGINAL_SOURCE='+repr(TV_SOURCE)+'\n')
    remote=remote.replace('def db_summary(provider):\n',constants+TV_DIAGNOSTIC_BOUNDARY+'\ndef db_summary(provider):\n',1)
    anchor="    if source!=TV_SOURCE:fail('tv_gateway_source')\n"
    need(remote.count(anchor)==1,'tv_diagnostic_stock_drift')
    remote=remote.replace(anchor,"    tv_diagnostic_boundary()\n"+anchor,1)
    anchor="    if mode==TV_GATEWAY_MODE: tv_gateway_preflight()\n    install_started=True\n"
    need(remote.count(anchor)==1,'tv_diagnostic_stock_drift')
    remote=remote.replace(anchor,"    if changed!="+repr([path])+":fail('tv_diagnostic_exact_delta')\n"+anchor,1)
    before="        result['tv_gateway_public_before']=tv_gateway_public()\n"
    after="        result['tv_gateway_public_after']=tv_gateway_public()\n"
    need(remote.count(before)==1 and remote.count(after)==1,'tv_diagnostic_stock_drift')
    remote=remote.replace(before,"        result['diagnostic_basis_before']=tv_diagnostic_boundary()\n"+before,1)
    remote=remote.replace(after,"        result['diagnostic_basis_after']=tv_diagnostic_boundary()\n"+after,1)
    remote=remote.replace("'install-tv-session-gateway'",repr(mode))
    ast.parse(remote)
    original_complete=core.completed_result
    core.completed_result=lambda cmd,r:completed(cmd,r) if cmd.get('mode')==mode else original_complete(cmd,r)
    core.REMOTE=remote


def completed_tv_diagnostics(command, result):
    return _completed_tv_maintenance(command,result,TV_DIAGNOSTIC_MODE,TV_DIAGNOSTIC_OPERATION,
        TV_DIAGNOSTIC_SOURCE,TV_PINS|{TV_DIAGNOSTIC_PATH:TV_DIAGNOSTIC_SHA})


def activate_tv_diagnostics(core, command):
    return _activate_tv_maintenance(core,command,TV_DIAGNOSTIC_MODE,TV_DIAGNOSTIC_SOURCE,
        TV_DIAGNOSTIC_PATH,TV_DIAGNOSTIC_SHA,TV_PINS,completed_tv_diagnostics)


def completed_tv_cache(command, result):
    return _completed_tv_maintenance(command,result,TV_CACHE_MODE,TV_CACHE_OPERATION,TV_CACHE_SOURCE,
        TV_PINS|{TV_DIAGNOSTIC_PATH:TV_DIAGNOSTIC_SHA,TV_CACHE_PATH:TV_CACHE_SHA})


def activate_tv_cache(core, command):
    return _activate_tv_maintenance(core,command,TV_CACHE_MODE,TV_CACHE_SOURCE,TV_CACHE_PATH,TV_CACHE_SHA,
        TV_PINS|{TV_DIAGNOSTIC_PATH:TV_DIAGNOSTIC_SHA},completed_tv_cache)


def completed_tv_context(command, result):
    return _completed_tv_maintenance(command,result,TV_CONTEXT_MODE,TV_CONTEXT_OPERATION,TV_CONTEXT_SOURCE,
        TV_PINS|{TV_DIAGNOSTIC_PATH:TV_DIAGNOSTIC_SHA,TV_CONTEXT_PATH:TV_CONTEXT_SHA})


def activate_tv_context(core, command):
    return _activate_tv_maintenance(core,command,TV_CONTEXT_MODE,TV_CONTEXT_SOURCE,TV_CONTEXT_PATH,TV_CONTEXT_SHA,
        TV_PINS|{TV_DIAGNOSTIC_PATH:TV_DIAGNOSTIC_SHA,TV_CACHE_PATH:TV_CACHE_SHA},completed_tv_context)
