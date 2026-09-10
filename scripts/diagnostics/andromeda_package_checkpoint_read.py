#!/usr/bin/env python3
"""Read one existing Andromeda selected-package checkpoint without supplier access.

This diagnostic never includes the supplier client/transport, never authenticates, and
never creates/modifies a server checkpoint. It accepts only the browser-safe selected
identity envelope and returns fixed classifications/digests, not the private claim.
"""
import json
import os
from pathlib import Path
import re
import sys

RUNTIME_SOURCE = 'fdd099e36fdc1a20a561f285d22d0acb00f90e36'
MAX_INPUT = 16384
IDENTITY_KEYS = {'provider','search_ref','generation','page','offer_ref','hotel_scope','operator_ref','local_id'}


def validate_request(value):
    if not isinstance(value, dict) or set(value) != {'version','operation','runtime_source','local_country_id','selection'}:
        raise ValueError('checkpoint_request_invalid')
    if value['version'] != 1 or value['operation'] != 'capture-selected-retained-offer-1717' or value['runtime_source'] != RUNTIME_SOURCE:
        raise ValueError('checkpoint_request_invalid')
    if type(value['local_country_id']) is not int or value['local_country_id'] < 1:
        raise ValueError('checkpoint_request_invalid')
    s = value['selection']
    if not isinstance(s, dict) or set(s) != IDENTITY_KEYS or s.get('provider') != 'andromeda':
        raise ValueError('checkpoint_request_invalid')
    if not isinstance(s.get('search_ref'), str) or re.fullmatch(r'[a-f0-9]{64}', s['search_ref']) is None:
        raise ValueError('checkpoint_request_invalid')
    if not isinstance(s.get('offer_ref'), str) or re.fullmatch(r'offer_[a-f0-9]{64}', s['offer_ref']) is None:
        raise ValueError('checkpoint_request_invalid')
    for key in ('generation','page','local_id'):
        if type(s.get(key)) is not int or s[key] < 1:
            raise ValueError('checkpoint_request_invalid')
    for key in ('operator_ref','hotel_scope'):
        v = s.get(key)
        if key == 'hotel_scope' and v is None:
            continue
        if not isinstance(v, str) or not v or len(v) > 4096 or re.search(r'[\x00-\x20\x7f]', v):
            raise ValueError('checkpoint_request_invalid')
    return value


PHP = r'''
error_reporting(0); ini_set('display_errors','0'); ini_set('log_errors','0'); umask(0077); ob_start();
$result=['status'=>'blocked','reason'=>'inspection_unconfirmed','supplier_calls'=>0,'database_writes'=>0,'capture_invoked'=>false,'automatic_retry'=>false];
try{
    if(PHP_SAPI!=='cli')throw new RuntimeException('cli_only');
    $root=realpath(getcwd()); if(!$root||basename($root)!=='anytoour.ru')throw new RuntimeException('wrong_project');
    $wire=file_get_contents('php://stdin',false,null,0,16385);
    if(!is_string($wire)||$wire===''||strlen($wire)>16384)throw new RuntimeException('request_invalid');
    $req=json_decode($wire,true,16,JSON_THROW_ON_ERROR);
    $top=['version','operation','runtime_source','local_country_id','selection'];
    if(!is_array($req)||count($req)!==count($top)||array_diff($top,array_keys($req))||array_diff(array_keys($req),$top)
        ||($req['version']??null)!==1||($req['operation']??null)!=='capture-selected-retained-offer-1717'
        ||($req['runtime_source']??null)!=='fdd099e36fdc1a20a561f285d22d0acb00f90e36'||!is_int($req['local_country_id']??null)||$req['local_country_id']<1)
        throw new RuntimeException('request_invalid');
    $s=$req['selection']??null; $keys=['provider','search_ref','generation','page','offer_ref','hotel_scope','operator_ref','local_id'];
    if(!is_array($s)||count($s)!==count($keys)||array_diff($keys,array_keys($s))||array_diff(array_keys($s),$keys)
        ||($s['provider']??null)!=='andromeda'||!is_string($s['search_ref']??null)||!preg_match('/^[a-f0-9]{64}$/D',$s['search_ref'])
        ||!is_string($s['offer_ref']??null)||!preg_match('/^offer_[a-f0-9]{64}$/D',$s['offer_ref']))throw new RuntimeException('request_invalid');
    foreach(['generation','page','local_id'] as $k)if(!is_int($s[$k]??null)||$s[$k]<1)throw new RuntimeException('request_invalid');
    foreach(['operator_ref','hotel_scope'] as $k){$v=$s[$k]??null;if($k==='hotel_scope'&&$v===null)continue;
        if(!is_string($v)||$v===''||strlen($v)>4096||preg_match('/[\x00-\x20\x7f]/',$v))throw new RuntimeException('request_invalid');}
    $target=$root.'/_preview/search3-anex-candidate'; $configPath=$target.'/.andromeda-private.php';
    if(realpath($configPath)!==$configPath||!is_file($configPath)||is_link($configPath))throw new RuntimeException('runtime_missing');
    $config=require $configPath;
    if(!is_array($config)||($config['enabled']??null)!==true||!is_string($config['catalog_path']??null)||$config['catalog_path']==='')
        throw new RuntimeException('private_runtime_missing');
    $directory=dirname($config['catalog_path']).'/searches';
    if(realpath($directory)!==$directory||!is_dir($directory)||is_link($directory))throw new RuntimeException('search_store_missing');
    $read=static function(string $path,bool $optional=false):array{
        if(is_link($path))throw new RuntimeException('checkpoint_invalid');
        if(!file_exists($path)&&$optional)return [];
        if(realpath($path)!==$path||!is_file($path)||filesize($path)>3000000||stat($path)['nlink']!==1)throw new RuntimeException('checkpoint_invalid');
        $v=json_decode(file_get_contents($path),true,32,JSON_THROW_ON_ERROR);if(!is_array($v))throw new RuntimeException('checkpoint_invalid');return $v;};
    $ref=$s['search_ref']; $first=$read($directory.'/'.$ref.'-1.json'); $store=$first['store']??null;
    if(!is_array($store)||!in_array($first['status']??null,['complete','partial'],true)||($store['version']??null)!==1
        ||($store['search_ref']??null)!==$ref||($store['generation']??null)!==$s['generation']||!is_int($store['created_at']??null)||$store['created_at']<1)
        throw new RuntimeException('search_context_mismatch');
    $created=$store['created_at'];
    if($s['page']!==1){$page=$read($directory.'/'.$ref.'-'.$created.'-'.$s['page'].'.json');$ps=$page['store']??null;
        if(!is_array($ps)||!in_array($page['status']??null,['complete','partial'],true)||($ps['search_ref']??null)!==$ref||($ps['generation']??null)!==$s['generation'])
            throw new RuntimeException('search_context_mismatch');}
    $path=$directory.'/'.$ref.'-'.$created.'-'.$s['page'].'-'.$s['offer_ref'].'-package.json';
    if(is_link($path))throw new RuntimeException('checkpoint_invalid');
    if(!file_exists($path)){
        $result=['status'=>'ok','checkpoint_status'=>'absent','context_match'=>null,'source_match'=>null,'package_present'=>false,
            'package_hash_valid'=>null,'verification_flags'=>'absent','supplier_effect'=>'not_determined','supplier_calls'=>0,'database_writes'=>0,
            'capture_invoked'=>false,'automatic_retry'=>false];
    }else{
        $envelope=$read($path);$record=$envelope['record']??null;
        if(($envelope['source']??null)!=='fdd099e36fdc1a20a561f285d22d0acb00f90e36'||!is_array($record)||$record===[])
            throw new RuntimeException('checkpoint_invalid');
        $ctx=$record['context']??null;$contextMatch=is_array($ctx)&&count($ctx)===count($keys)&&!array_diff($keys,array_keys($ctx))&&!array_diff(array_keys($ctx),$keys);
        if($contextMatch)foreach($keys as $k)if(($ctx[$k]??null)!==$s[$k]){$contextMatch=false;break;}
        if(!$contextMatch)throw new RuntimeException('checkpoint_context_mismatch');
        if(($record['version']??null)!==1||!in_array($record['status']??null,['reserved','unknown','captured','stale'],true))
            throw new RuntimeException('checkpoint_invalid');
        $state=$record['status'];$hasPackage=is_array($record['private_package']??null);
        $hashValid=null;
        if($hasPackage){$digest=$record['package_sha256']??null;$hashValid=is_string($digest)&&preg_match('/^[a-f0-9]{64}$/D',$digest)
            &&hash_equals($digest,hash('sha256',json_encode($record['private_package'],JSON_THROW_ON_ERROR)));}
        if(in_array($state,['captured','stale'],true)&&(!$hasPackage||$hashValid!==true))throw new RuntimeException('checkpoint_invalid');
        if(in_array($state,['reserved','unknown'],true)&&$hasPackage)throw new RuntimeException('checkpoint_invalid');
        $flags=array_key_exists('identity_verified',$record)||array_key_exists('quote_verified',$record)||array_key_exists('selection_enabled',$record)
            ?((($record['identity_verified']??null)===false&&($record['quote_verified']??null)===false&&($record['selection_enabled']??null)===false)?'all_false':'unexpected'):'absent';
        if($flags==='unexpected')throw new RuntimeException('checkpoint_invalid');
        $effect=$state==='unknown'?'unknown':(in_array($state,['captured','stale'],true)?'response_retained':'not_determined');
        $result=['status'=>'ok','checkpoint_status'=>$state,'context_match'=>true,'source_match'=>true,'package_present'=>$hasPackage,
            'package_hash_valid'=>$hashValid,'verification_flags'=>$flags,'supplier_effect'=>$effect,'supplier_calls'=>0,'database_writes'=>0,
            'capture_invoked'=>false,'automatic_retry'=>false];
    }
}catch(Throwable $e){$allowed=['cli_only','wrong_project','request_invalid','runtime_missing','private_runtime_missing','search_store_missing',
    'search_context_mismatch','checkpoint_invalid','checkpoint_context_mismatch'];$reason=in_array($e->getMessage(),$allowed,true)?$e->getMessage():'inspection_unconfirmed';
    $result=['status'=>'blocked','reason'=>$reason,'supplier_calls'=>0,'database_writes'=>0,'capture_invoked'=>false,'automatic_retry'=>false];}
while(ob_get_level())ob_end_clean();echo json_encode($result,JSON_THROW_ON_ERROR);
'''


def remote_source():
    return PHP


def validate_result(value):
    common = {'supplier_calls','database_writes','capture_invoked','automatic_retry'}
    if not isinstance(value, dict) or any(value.get(k) != 0 for k in ('supplier_calls','database_writes')) \
            or value.get('capture_invoked') is not False or value.get('automatic_retry') is not False:
        raise ValueError('checkpoint_result_invalid')
    if value.get('status') == 'blocked':
        if set(value) != {'status','reason'} | common or not isinstance(value.get('reason'), str):
            raise ValueError('checkpoint_result_invalid')
        return value
    keys = {'status','checkpoint_status','context_match','source_match','package_present','package_hash_valid',
            'verification_flags','supplier_effect'} | common
    if set(value) != keys or value.get('status') != 'ok' or value.get('checkpoint_status') not in {'absent','reserved','unknown','captured','stale'}:
        raise ValueError('checkpoint_result_invalid')
    if value['checkpoint_status'] == 'absent':
        if value['context_match'] is not None or value['source_match'] is not None or value['package_present'] is not False \
                or value['package_hash_valid'] is not None or value['verification_flags'] != 'absent' or value['supplier_effect'] != 'not_determined':
            raise ValueError('checkpoint_result_invalid')
    else:
        if value['context_match'] is not True or value['source_match'] is not True or value['verification_flags'] not in {'absent','all_false'}:
            raise ValueError('checkpoint_result_invalid')
        expected_effect = 'unknown' if value['checkpoint_status'] == 'unknown' else ('response_retained' if value['checkpoint_status'] in {'captured','stale'} else 'not_determined')
        if value['supplier_effect'] != expected_effect:
            raise ValueError('checkpoint_result_invalid')
        if value['checkpoint_status'] in {'captured','stale'} and (value['package_present'] is not True or value['package_hash_valid'] is not True):
            raise ValueError('checkpoint_result_invalid')
        if value['checkpoint_status'] in {'reserved','unknown'} and (value['package_present'] is not False or value['package_hash_valid'] is not None):
            raise ValueError('checkpoint_result_invalid')
    return value


def inspect(request, execute, output_directory):
    request = validate_request(request)
    directory = Path(output_directory)
    directory.mkdir(mode=0o700)
    if directory.stat().st_mode & 0o077:
        raise ValueError('checkpoint_output_permissions')
    try:
        result = validate_result(execute(remote_source(), request))
    except Exception:
        result = {'status':'blocked','reason':'remote_outcome_unknown','supplier_calls':0,'database_writes':0,'capture_invoked':False,'automatic_retry':False}
    path = directory / 'result.json'
    with os.fdopen(os.open(path, os.O_WRONLY | os.O_CREAT | os.O_EXCL, 0o600), 'w') as handle:
        json.dump(result, handle, sort_keys=True, separators=(',', ':')); handle.flush(); os.fsync(handle.fileno())
    if json.loads(path.read_text()) != result:
        raise ValueError('checkpoint_output_readback')
    return result


def main():
    if len(sys.argv) != 4 or sys.argv[1] != '--inspect-package-checkpoint':
        raise SystemExit('usage: andromeda_package_checkpoint_read.py --inspect-package-checkpoint PRIVATE_REQUEST PINNED_HELPER_REPO')
    request_path = Path(sys.argv[2])
    if request_path.is_symlink() or not request_path.is_file() or request_path.stat().st_size > MAX_INPUT or request_path.stat().st_mode & 0o077:
        raise SystemExit('private request invalid; no SSH call')
    try:
        request = validate_request(json.loads(request_path.read_text()))
    except Exception:
        raise SystemExit('private request invalid; no SSH call') from None
    sys.path.insert(0, str(Path(sys.argv[3]) / 'scripts/diagnostics'))
    from anex_search3_owner_decisions import ssh_php
    result = inspect(request, ssh_php, Path(os.environ['RUNNER_TEMP']) / 'andromeda-package-checkpoint-read')
    print(json.dumps(result, sort_keys=True))
    if result['status'] != 'ok':
        raise SystemExit('package checkpoint inspection unconfirmed; do not replay capture')


if __name__ == '__main__':
    main()
