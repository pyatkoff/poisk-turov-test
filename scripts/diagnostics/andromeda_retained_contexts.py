#!/usr/bin/env python3
"""Read-only discovery of CURRENT retained Andromeda publicSelection contexts.

No supplier/search/capture operation is implemented here. The remote PHP reads only
existing private search checkpoints and current accepted mappings, then returns the
same browser-safe identity/display fields already produced by publicSelection().
"""
import json
import os
from pathlib import Path
import sys

RUNTIME_SOURCE = 'fdd099e36fdc1a20a561f285d22d0acb00f90e36'
TTL_SECONDS = 900
MAX_ACTIVE_SEARCHES = 16
MAX_OFFERS = 2000
MAX_CANDIDATES = 20


PHP = r'''
error_reporting(0); ini_set('display_errors','0'); ini_set('log_errors','0');
umask(0077); ob_start();
$result=['status'=>'blocked','reason'=>'inspection_unconfirmed','supplier_calls'=>0,
    'database_writes'=>0,'capture_invoked'=>false];
try {
    if(PHP_SAPI!=='cli')throw new RuntimeException('cli_only');
    $root=realpath(getcwd());
    if(!$root||basename($root)!=='anytoour.ru')throw new RuntimeException('wrong_project');
    $target=$root.'/_preview/search3-anex-candidate';
    $api=$target.'/api-andromeda-search3-preview.php';
    $configPath=$target.'/.andromeda-private.php';
    if(realpath($api)!==$api||!is_file($api)||realpath($configPath)!==$configPath||!is_file($configPath))
        throw new RuntimeException('runtime_missing');
    require_once $api;
    require_once $target.'/app/integrations/andromeda-selected-offer.php';
    $dbPath=$root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
    if(realpath($dbPath)!==$dbPath||!is_file($dbPath))throw new RuntimeException('db_runtime_missing');
    require_once $dbPath;
    $config=require $configPath;
    if(!is_array($config)||($config['enabled']??null)!==true||!is_string($config['catalog_path']??null))
        throw new RuntimeException('private_runtime_missing');
    $directory=dirname($config['catalog_path']).'/searches';
    if(realpath($directory)!==$directory||!is_dir($directory)||is_link($directory))
        throw new RuntimeException('search_store_missing');
    $now=time(); $active=[]; $entries=0;
    $iterator=new DirectoryIterator($directory);
    foreach($iterator as $entry){
        if($entry->isDot())continue;
        if(++$entries>20000)throw new RuntimeException('search_inventory_too_large');
        $name=$entry->getFilename();
        if(!preg_match('/^([a-f0-9]{64})-1\.json$/D',$name,$match))continue;
        if($entry->isLink()||!$entry->isFile()||$entry->getSize()>3000000)continue;
        // TTL is 900s; a generous mtime window avoids parsing old retained history.
        if($entry->getMTime()<$now-1800)continue;
        $path=$entry->getPathname();
        if(realpath($path)!==$path||stat($path)['nlink']!==1)continue;
        try{$state=json_decode(file_get_contents($path),true,32,JSON_THROW_ON_ERROR);}catch(Throwable $ignored){continue;}
        $store=is_array($state)?($state['store']??null):null;
        if(!is_array($store)||!in_array($state['status']??null,['complete','partial'],true)
            ||($store['version']??null)!==1||($store['search_ref']??null)!==$match[1]
            ||!is_int($store['generation']??null)||$store['generation']<1
            ||!is_int($store['created_at']??null)||!is_int($store['expires_at']??null)
            ||$store['created_at']>$now||$store['expires_at']<=$now
            ||$store['expires_at']-$store['created_at']!==900
            ||!is_array($store['snapshot']??null)||($store['snapshot']['page']??null)!==1
            ||!is_array($store['snapshot']['offers']??null))continue;
        $active[]=['path'=>$path,'ref'=>$match[1],'state'=>$state];
        if(count($active)>16)throw new RuntimeException('too_many_active_searches');
    }
    usort($active,static fn($a,$b)=>($b['state']['store']['created_at']<=>$a['state']['store']['created_at'])
        ?:strcmp($a['ref'],$b['ref']));
    $offers=[];$external=[];
    foreach($active as $search){
        $store=$search['state']['store'];
        foreach($store['snapshot']['offers'] as $offer){
            if(!is_array($offer)||!is_string($offer['offer_ref']??null)
                ||!preg_match('/^offer_[a-f0-9]{64}$/D',$offer['offer_ref'])
                ||!is_string($offer['supplier_namespace']??null)||!is_scalar($offer['external_hotel_id']??null)
                ||!is_int($offer['local_hotel_id']??null)||$offer['local_hotel_id']<1)continue;
            if(count($offers)>=2000)throw new RuntimeException('too_many_current_offers');
            $key=json_encode([$offer['supplier_namespace'],(string)$offer['external_hotel_id']],JSON_THROW_ON_ERROR);
            $external[(string)$offer['external_hotel_id']]=true;
            $offers[]=['search'=>$search,'offer'=>$offer,'key'=>$key];
        }
    }
    $current=[];$countries=[];
    if($external){
        $ids=array_keys($external);
        $pdo=v2_data_db();
        $query=$pdo->prepare("SELECT i.supplier_namespace,i.external_hotel_id,i.local_hotel_id,h.country_id FROM andromeda_hotel_identities i JOIN catalog_hotels h ON h.id=i.local_hotel_id WHERE i.decision_status='accepted' AND h.is_active=1 AND i.external_hotel_id IN (".implode(',',array_fill(0,count($ids),'?')).')');
        $query->execute($ids);
        foreach($query->fetchAll(PDO::FETCH_ASSOC) as $row){
            $key=json_encode([$row['supplier_namespace'],(string)$row['external_hotel_id']],JSON_THROW_ON_ERROR);
            if(array_key_exists($key,$current)){$current[$key]=null;$countries[$key]=null;continue;}
            $current[$key]=(int)$row['local_hotel_id'];$countries[$key]=(int)$row['country_id'];
        }
    }
    $safeText=static function($value,int $max=180):string{
        if(!is_string($value)||preg_match('/[\x00-\x1f\x7f]/',$value))return '';
        return mb_substr($value,0,$max);
    };
    $candidates=[];
    foreach($offers as $item){
        $offer=$item['offer'];$key=$item['key'];
        if(($current[$key]??null)!==$offer['local_hotel_id']||!is_int($countries[$key]??null)||$countries[$key]<1)continue;
        $storeState=$item['search']['state']['store'];
        $store=new AnyTourAndromedaOfferStore($storeState,true);
        $allows=static fn(array $candidate):bool => ($candidate['supplier_namespace']??null)===$offer['supplier_namespace']
            && (string)($candidate['external_hotel_id']??'')===(string)$offer['external_hotel_id']
            && ($candidate['local_hotel_id']??null)===$offer['local_hotel_id'];
        $context=['provider'=>'andromeda','search_ref'=>$item['search']['ref'],
            'generation'=>$storeState['generation'],'page'=>1,'offer_ref'=>$offer['offer_ref']];
        try{$selection=AnyTourAndromedaSelectedOffer::publicSelection($store,$context,$allows,$now);}catch(Throwable $ignored){continue;}
        $identity=array_intersect_key($selection,array_flip(['provider','search_ref','generation','page','offer_ref','hotel_scope','operator_ref','local_id']));
        if(count($identity)!==8)continue;
        $tour=$selection['tour']??[];$price=$tour['price']??[];
        $display=['hotel'=>$safeText($tour['hotel']??''),'operator'=>$safeText($tour['operator']??''),
            'check_in'=>$safeText($tour['check_in']??'',40),'nights'=>is_int($tour['nights']??null)?$tour['nights']:null,
            'room'=>$safeText($tour['room']??''),'meal'=>$safeText($tour['meal']??'',80),
            'price_amount'=>(is_array($price)&&is_scalar($price['amount']??null))?(string)$price['amount']:null,
            'price_currency'=>(is_array($price)&&is_string($price['currency']??null))?$safeText($price['currency'],12):null];
        $request=['version'=>1,'operation'=>'capture-selected-retained-offer-1717',
            'runtime_source'=>'fdd099e36fdc1a20a561f285d22d0acb00f90e36',
            'local_country_id'=>$countries[$key],'selection'=>$identity];
        $candidates[]=['created_at'=>$storeState['created_at'],'expires_at'=>$storeState['expires_at'],
            'capture_request'=>$request,'display'=>$display];
        if(count($candidates)>=20)break;
    }
    $result=['status'=>'ok','observed_at'=>$now,'ttl_seconds'=>900,'active_searches'=>count($active),
        'candidates'=>$candidates,'supplier_calls'=>0,'database_writes'=>0,'capture_invoked'=>false];
}catch(Throwable $e){
    $allowed=['cli_only','wrong_project','runtime_missing','db_runtime_missing','private_runtime_missing',
        'search_store_missing','search_inventory_too_large','too_many_active_searches','too_many_current_offers'];
    $reason=in_array($e->getMessage(),$allowed,true)?$e->getMessage():'inspection_unconfirmed';
    $result=['status'=>'blocked','reason'=>$reason,'supplier_calls'=>0,'database_writes'=>0,'capture_invoked'=>false];
}
while(ob_get_level())ob_end_clean(); echo json_encode($result,JSON_THROW_ON_ERROR);
'''


def remote_source():
    return PHP


def validate_result(value):
    if not isinstance(value, dict) or value.get('supplier_calls') != 0 or value.get('database_writes') != 0 or value.get('capture_invoked') is not False:
        raise ValueError('retained_context_result_invalid')
    if value.get('status') == 'blocked':
        if set(value) != {'status','reason','supplier_calls','database_writes','capture_invoked'} or not isinstance(value.get('reason'), str):
            raise ValueError('retained_context_result_invalid')
        return value
    if set(value) != {'status','observed_at','ttl_seconds','active_searches','candidates','supplier_calls','database_writes','capture_invoked'}:
        raise ValueError('retained_context_result_invalid')
    if value.get('status') != 'ok' or type(value.get('observed_at')) is not int or value.get('ttl_seconds') != TTL_SECONDS \
            or type(value.get('active_searches')) is not int or not 0 <= value['active_searches'] <= MAX_ACTIVE_SEARCHES \
            or not isinstance(value.get('candidates'), list) or len(value['candidates']) > MAX_CANDIDATES:
        raise ValueError('retained_context_result_invalid')
    identity_keys = {'provider','search_ref','generation','page','offer_ref','hotel_scope','operator_ref','local_id'}
    for row in value['candidates']:
        if not isinstance(row, dict) or set(row) != {'created_at','expires_at','capture_request','display'}:
            raise ValueError('retained_context_result_invalid')
        if type(row['created_at']) is not int or type(row['expires_at']) is not int or row['expires_at'] <= value['observed_at']:
            raise ValueError('retained_context_result_invalid')
        req = row['capture_request']; sel = req.get('selection') if isinstance(req, dict) else None
        if set(req or {}) != {'version','operation','runtime_source','local_country_id','selection'} \
                or req['version'] != 1 or req['operation'] != 'capture-selected-retained-offer-1717' \
                or req['runtime_source'] != RUNTIME_SOURCE or type(req['local_country_id']) is not int or req['local_country_id'] < 1 \
                or not isinstance(sel, dict) or set(sel) != identity_keys:
            raise ValueError('retained_context_result_invalid')
        if sel['provider'] != 'andromeda' or not isinstance(sel['search_ref'], str) or len(sel['search_ref']) != 64 \
                or not isinstance(sel['offer_ref'], str) or not sel['offer_ref'].startswith('offer_') \
                or type(sel['generation']) is not int or sel['generation'] < 1 or sel['page'] != 1 \
                or type(sel['local_id']) is not int or sel['local_id'] < 1 or not isinstance(sel['operator_ref'], str):
            raise ValueError('retained_context_result_invalid')
        display = row['display']
        allowed_display = {'hotel','operator','check_in','nights','room','meal','price_amount','price_currency'}
        if not isinstance(display, dict) or set(display) != allowed_display:
            raise ValueError('retained_context_result_invalid')
        if any(isinstance(v, str) and ('PRIVATE' in v or '\x00' in v) for v in display.values()):
            raise ValueError('retained_context_result_invalid')
    return value


def inspect(execute, output_directory):
    directory = Path(output_directory)
    directory.mkdir(mode=0o700)
    if directory.stat().st_mode & 0o077:
        raise ValueError('retained_context_output_permissions')
    try:
        result = validate_result(execute(remote_source(), {}))
    except Exception:
        result = {'status':'blocked','reason':'remote_outcome_unknown','supplier_calls':0,'database_writes':0,'capture_invoked':False}
    path = directory / 'result.json'
    with os.fdopen(os.open(path, os.O_WRONLY | os.O_CREAT | os.O_EXCL, 0o600), 'w') as handle:
        json.dump(result, handle, sort_keys=True, separators=(',', ':')); handle.flush(); os.fsync(handle.fileno())
    if json.loads(path.read_text()) != result:
        raise ValueError('retained_context_output_readback')
    return result


def main():
    if len(sys.argv) != 2 or sys.argv[1] != '--inspect-retained':
        raise SystemExit('usage: andromeda_retained_contexts.py --inspect-retained')
    from anex_search3_owner_decisions import ssh_php
    result = inspect(ssh_php, Path(os.environ['RUNNER_TEMP']) / 'andromeda-retained-contexts')
    print(json.dumps({'status':result['status'],'active_searches':result.get('active_searches',0),
        'candidates':len(result.get('candidates',[])),'supplier_calls':0,'database_writes':0}))
    if result['status'] != 'ok':
        raise SystemExit('retained context inspection unconfirmed; no retry')


if __name__ == '__main__':
    main()
