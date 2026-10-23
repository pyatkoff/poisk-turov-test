"""Dedicated Search3 signing custody in the existing main-only INT executor.

No private bytes in SSH stdout, payloads or artifacts. No new transport, supplier,
database, bot call, arbitrary path, caller key or implicit rotation.
"""
from __future__ import annotations

import ast

MODES = ('search3-signing-provision', 'search3-signing-readback')
KEY_ID = 'search3-anytour-v1'


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
        need(core.OP_RE.fullmatch(operation) is not None
             and operation.startswith('int-anex-search3-signing-'), 'search3_signing_operation')
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
    $pointerReady=$definitions===[$expected];
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
    expected = core.parse_command(core.PREFIX + ' '.join(str(command.get(k, '')) for k in ('source_sha', 'mode', 'operation_id')))
    need(command == expected, 'search3_signing_shape')
    remote = core.REMOTE
    definition = 'def db_summary(provider):\n'
    dispatch = "    if mode in ('install-runtime','install-anex-preview','install-andromeda-preview','install-andromeda-quote-preview'):\n"
    collector = "    if mode not in ('reconcile',"
    need(remote.count(definition) == 1 and remote.count(dispatch) == 1
         and remote.count(collector) == 2, 'search3_signing_stock_drift')
    remote = remote.replace(definition, 'SEARCH3_SIGNING_PHP=' + repr(PHP) + '\n' + REMOTE + definition, 1)
    remote = remote.replace(dispatch, DISPATCH + dispatch, 1)
    remote = remote.replace(collector, "    if mode not in ('search3-signing-provision','search3-signing-readback','reconcile',")
    ast.parse(remote)
    core.REMOTE = remote
