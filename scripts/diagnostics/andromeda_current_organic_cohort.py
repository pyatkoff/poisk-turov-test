#!/usr/bin/env python3
"""Aggregate current-producer organic Andromeda served-price observations, read only."""
import json, sys

PHP = r'''
error_reporting(0);ini_set('display_errors','0');ini_set('log_errors','0');ob_start();
$out=['status'=>'blocked','reason'=>'inspection_unconfirmed','supplier_calls'=>0,'database_access'=>false,'remote_writes'=>0];
try{
 if(PHP_SAPI!=='cli')throw new RuntimeException('cli_only');
 $root=realpath(getcwd());if(!$root||basename($root)!=='anytoour.ru')throw new RuntimeException('wrong_project');
 $target=$root.'/_preview/search3-anex-candidate';$configPath=$target.'/.andromeda-private.php';
 if(realpath($configPath)!==$configPath||!is_file($configPath))throw new RuntimeException('private_runtime_missing');
 $config=require $configPath;if(!is_array($config)||!is_string($config['catalog_path']??null))throw new RuntimeException('private_runtime_missing');
 $dir=dirname($config['catalog_path']).'/searches';$producer=$target.'/api-andromeda-quote-preview.php';
 if(realpath($dir)!==$dir||!is_dir($dir)||is_link($dir)||!is_file($producer)||is_link($producer))throw new RuntimeException('runtime_missing');
 $producerMtime=filemtime($producer);if(!is_int($producerMtime)||$producerMtime<1)throw new RuntimeException('runtime_missing');
 $scan=['matched'=>0,'completed'=>0,'current_completed'=>0,'current_final_verified'=>0,'current_observations'=>0,'current_missing_observation'=>0,'historical_completed'=>0,'invalid'=>0];
 $bps=[];$exact=0;$entries=0;
 foreach(new DirectoryIterator($dir) as $entry){
  if($entry->isDot())continue;if(++$entries>50000)throw new RuntimeException('inventory_too_large');
  if(!preg_match('/-(?:quote|quote-flight)-v1\.json$/D',$entry->getFilename()))continue;++$scan['matched'];
  try{
   if($entry->isLink()||!$entry->isFile()||$entry->getSize()<2||$entry->getSize()>131072)throw new RuntimeException();
   $path=$entry->getPathname();if(realpath($path)!==$path||(stat($path)['nlink']??0)!==1)throw new RuntimeException();
   $env=json_decode(file_get_contents($path),true,64,JSON_THROW_ON_ERROR);$state=$env['state']??null;
   if(!is_array($env)||array_keys($env)!==['state']||!is_array($state))throw new RuntimeException();
   if(($state['status']??null)!=='completed'||!is_array($state['result']??null))continue;++$scan['completed'];
   if($entry->getMTime()<$producerMtime){++$scan['historical_completed'];continue;}++$scan['current_completed'];
   $result=$state['result'];if(($result['final_price_verified']??null)!==true)continue;++$scan['current_final_verified'];
   $obs=$result['served_price_observation']??null;if($obs===null){++$scan['current_missing_observation'];continue;}
   if(!is_array($obs)||($obs['schema_version']??null)!==1||($obs['provider']??null)!=='andromeda'||($obs['basis']??null)!=='search_api_response'||($obs['final_price_verified']??null)!==true||($obs['state']??null)!=='comparable'||!is_int($obs['relative_delta_bps']??null)||$obs['relative_delta_bps']<0)throw new RuntimeException();
   ++$scan['current_observations'];$bps[]=$obs['relative_delta_bps'];if($obs['relative_delta_bps']===0)++$exact;
  }catch(Throwable $ignored){++$scan['invalid'];}
 }
 sort($bps,SORT_NUMERIC);$n=count($bps);$within=[];foreach([100,300,500,1000] as $limit){$c=0;foreach($bps as $v)if($v<=$limit)++$c;$within[(string)$limit]=$c;}
 $ratio=static fn(int $c,int $n):?float=>$n?round($c/$n,4):null;
 $out=['status'=>'ok','scan'=>$scan,'accuracy'=>['comparable'=>$n,'exact'=>$exact,'exact_accuracy'=>$ratio($exact,$n),'within_100_bps_accuracy'=>$ratio($within['100'],$n),'within_300_bps_accuracy'=>$ratio($within['300'],$n),'within_500_bps_accuracy'=>$ratio($within['500'],$n),'within_1000_bps_accuracy'=>$ratio($within['1000'],$n),'mean_relative_delta_bps'=>$n?(int)round(array_sum($bps)/$n):null,'max_relative_delta_bps'=>$n?max($bps):null],'supplier_calls'=>0,'database_access'=>false,'remote_writes'=>0];
}catch(Throwable $e){$allowed=['cli_only','wrong_project','private_runtime_missing','runtime_missing','inventory_too_large'];$out=['status'=>'blocked','reason'=>in_array($e->getMessage(),$allowed,true)?$e->getMessage():'inspection_unconfirmed','supplier_calls'=>0,'database_access'=>false,'remote_writes'=>0];}
while(ob_get_level())ob_end_clean();echo json_encode($out,JSON_THROW_ON_ERROR);
'''

def validate(v):
    if not isinstance(v,dict) or v.get('supplier_calls')!=0 or v.get('database_access') is not False or v.get('remote_writes')!=0: raise ValueError('invalid')
    if v.get('status')=='blocked': return v
    if v.get('status')!='ok' or set(v)!={'status','scan','accuracy','supplier_calls','database_access','remote_writes'}: raise ValueError('invalid')
    if any(k in json.dumps(v) for k in ('search_ref','offer_ref','supplier_offer_id','claiminc','catalog_path')): raise ValueError('private_leak')
    return v

def main():
    if sys.argv!=[sys.argv[0],'--read-only']: raise SystemExit('usage: --read-only')
    from anex_search3_owner_decisions import ssh_php
    try: result=validate(ssh_php(PHP,{}))
    except Exception: result={'status':'blocked','reason':'remote_outcome_unknown','supplier_calls':0,'database_access':False,'remote_writes':0}
    print(json.dumps(result,sort_keys=True,separators=(',',':')))
    if result['status']!='ok': raise SystemExit('inspection unconfirmed; no retry')
if __name__=='__main__': main()
