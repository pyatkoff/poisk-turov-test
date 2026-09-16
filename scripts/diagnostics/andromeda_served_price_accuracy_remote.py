#!/usr/bin/env python3
"""Read only completed Andromeda quote checkpoints and return aggregate served-price accuracy."""
import json
import os
import sys

PHP = r'''
error_reporting(0); ini_set('display_errors','0'); ini_set('log_errors','0'); ob_start();
$result=['status'=>'blocked','reason'=>'inspection_unconfirmed','supplier_calls'=>0,'database_access'=>false,'remote_writes'=>0];
try {
    if(PHP_SAPI!=='cli')throw new RuntimeException('cli_only');
    $root=realpath(getcwd());
    if(!$root||basename($root)!=='anytoour.ru')throw new RuntimeException('wrong_project');
    $target=$root.'/_preview/search3-anex-candidate';
    $configPath=$target.'/.andromeda-private.php';
    if(realpath($configPath)!==$configPath||!is_file($configPath))throw new RuntimeException('private_runtime_missing');
    $config=require $configPath;
    if(!is_array($config)||!is_string($config['catalog_path']??null))throw new RuntimeException('private_runtime_missing');
    $directory=dirname($config['catalog_path']).'/searches';
    if(realpath($directory)!==$directory||!is_dir($directory)||is_link($directory))throw new RuntimeException('search_store_missing');

    // Capability belongs to the deployed quote endpoint that writes the checkpoint field,
    // plus the helper that resolves the served receipt and compares it with the verified quote.
    // Do not infer support from a historical/nonexistent endpoint name or from the field name
    // appearing somewhere unrelated in the runtime tree.
    $quotePath=$target.'/api-andromeda-quote-preview.php';
    $observationPath=$target.'/app/integrations/andromeda-price-observation.php';
    $runtimeSupports=false;
    if(realpath($quotePath)===$quotePath && is_file($quotePath) && !is_link($quotePath)
        && realpath($observationPath)===$observationPath && is_file($observationPath) && !is_link($observationPath)){
        $quoteText=file_get_contents($quotePath);
        $observationText=file_get_contents($observationPath);
        $runtimeSupports=is_string($quoteText)&&is_string($observationText)
            && strpos($quoteText,'served_price_observation')!==false
            && strpos($quoteText,'AnyTourAndromedaPriceObservation::compareServed')!==false
            && strpos($observationText,'function compareServed')!==false
            && strpos($observationText,'function resolveServed')!==false;
    }

    $scan=['matched_files'=>0,'completed_checkpoints'=>0,'observations'=>0,'no_observation'=>0,'not_completed'=>0,'invalid_files'=>0];
    $bps=[];$exact=0;$comparable=0;$currencyMismatch=0;$entries=0;
    $iterator=new DirectoryIterator($directory);
    foreach($iterator as $entry){
        if($entry->isDot())continue;
        if(++$entries>50000)throw new RuntimeException('checkpoint_inventory_too_large');
        $name=$entry->getFilename();
        if(!preg_match('/-(?:quote|quote-flight)-v1\.json$/D',$name))continue;
        ++$scan['matched_files'];
        try {
            if($entry->isLink()||!$entry->isFile()||$entry->getSize()<2||$entry->getSize()>131072)throw new RuntimeException();
            $path=$entry->getPathname();
            if(realpath($path)!==$path||(stat($path)['nlink']??0)!==1)throw new RuntimeException();
            $envelope=json_decode(file_get_contents($path),true,64,JSON_THROW_ON_ERROR);
            if(!is_array($envelope)||array_keys($envelope)!==['state']||!is_array($envelope['state']))throw new RuntimeException();
            $state=$envelope['state'];
            if(($state['status']??null)!=='completed'||!is_array($state['result']??null)){++$scan['not_completed'];continue;}
            ++$scan['completed_checkpoints'];
            $observation=$state['result']['served_price_observation']??null;
            if($observation===null){++$scan['no_observation'];continue;}
            if(!is_array($observation)||($observation['schema_version']??null)!==1||($observation['provider']??null)!=='andromeda'
                ||($observation['basis']??null)!=='search_api_response'||($observation['final_price_verified']??null)!==true)throw new RuntimeException();
            $kind=$observation['state']??null;
            if($kind==='currency_mismatch'){$currencyMismatch++;++$scan['observations'];continue;}
            if($kind!=='comparable'||!is_int($observation['relative_delta_bps']??null)||$observation['relative_delta_bps']<0)throw new RuntimeException();
            $value=$observation['relative_delta_bps'];$bps[]=$value;++$comparable;++$scan['observations'];if($value===0)++$exact;
        } catch(Throwable $ignored){++$scan['invalid_files'];}
    }
    sort($bps,SORT_NUMERIC);
    $percentile=static function(array $values,float $fraction):?int{
        if(!$values)return null;$index=(int)ceil(count($values)*$fraction)-1;return $values[max(0,min(count($values)-1,$index))];
    };
    $within=[];foreach([100,300,500,1000] as $limit){$count=0;foreach($bps as $value)if($value<=$limit)++$count;$within[(string)$limit]=$count;}
    $accuracy=static fn(int $count,int $total):?float=>$total>0?round($count/$total,4):null;
    $result=[
        'status'=>'ok','observed_at'=>time(),'runtime_supports_served_price_observation'=>$runtimeSupports,
        'scan'=>$scan,
        'accuracy'=>[
            'comparable'=>$comparable,'currency_mismatch'=>$currencyMismatch,'exact'=>$exact,
            'exact_accuracy'=>$accuracy($exact,$comparable),
            'within_100_bps_accuracy'=>$accuracy($within['100'],$comparable),
            'within_300_bps_accuracy'=>$accuracy($within['300'],$comparable),
            'within_500_bps_accuracy'=>$accuracy($within['500'],$comparable),
            'within_1000_bps_accuracy'=>$accuracy($within['1000'],$comparable),
            'mean_relative_delta_bps'=>$bps?(int)round(array_sum($bps)/count($bps)):null,
            'p50_relative_delta_bps'=>$percentile($bps,.50),'p90_relative_delta_bps'=>$percentile($bps,.90),
            'max_relative_delta_bps'=>$bps?max($bps):null,
        ],
        'supplier_calls'=>0,'database_access'=>false,'remote_writes'=>0,
    ];
} catch(Throwable $e) {
    $allowed=['cli_only','wrong_project','private_runtime_missing','search_store_missing','checkpoint_inventory_too_large'];
    $reason=in_array($e->getMessage(),$allowed,true)?$e->getMessage():'inspection_unconfirmed';
    $result=['status'=>'blocked','reason'=>$reason,'supplier_calls'=>0,'database_access'=>false,'remote_writes'=>0];
}
while(ob_get_level())ob_end_clean();echo json_encode($result,JSON_THROW_ON_ERROR);
'''


def validate(value):
    if not isinstance(value, dict) or value.get('supplier_calls') != 0 or value.get('database_access') is not False or value.get('remote_writes') != 0:
        raise ValueError('accuracy_remote_result_invalid')
    if value.get('status') == 'blocked':
        if set(value) != {'status','reason','supplier_calls','database_access','remote_writes'}:
            raise ValueError('accuracy_remote_result_invalid')
        return value
    expected = {'status','observed_at','runtime_supports_served_price_observation','scan','accuracy','supplier_calls','database_access','remote_writes'}
    if set(value) != expected or value.get('status') != 'ok' or type(value.get('observed_at')) is not int or type(value.get('runtime_supports_served_price_observation')) is not bool:
        raise ValueError('accuracy_remote_result_invalid')
    scan=value.get('scan'); accuracy=value.get('accuracy')
    scan_keys={'matched_files','completed_checkpoints','observations','no_observation','not_completed','invalid_files'}
    accuracy_keys={'comparable','currency_mismatch','exact','exact_accuracy','within_100_bps_accuracy','within_300_bps_accuracy','within_500_bps_accuracy','within_1000_bps_accuracy','mean_relative_delta_bps','p50_relative_delta_bps','p90_relative_delta_bps','max_relative_delta_bps'}
    if not isinstance(scan,dict) or set(scan)!=scan_keys or any(type(v) is not int or v<0 for v in scan.values()):
        raise ValueError('accuracy_remote_result_invalid')
    if not isinstance(accuracy,dict) or set(accuracy)!=accuracy_keys:
        raise ValueError('accuracy_remote_result_invalid')
    if any(key in json.dumps(value) for key in ('search_ref','offer_ref','supplier_offer_id','claiminc','catalog_path')):
        raise ValueError('accuracy_remote_private_leak')
    return value


def main():
    if sys.argv != [sys.argv[0], '--read-only']:
        raise SystemExit('usage: andromeda_served_price_accuracy_remote.py --read-only')
    from anex_search3_owner_decisions import ssh_php
    try:
        result=validate(ssh_php(PHP, {}))
    except Exception:
        result={'status':'blocked','reason':'remote_outcome_unknown','supplier_calls':0,'database_access':False,'remote_writes':0}
    print(json.dumps(result,sort_keys=True,separators=(',',':')))
    if result['status']!='ok':raise SystemExit('read-only accuracy inspection unconfirmed; no retry')

if __name__=='__main__':main()
