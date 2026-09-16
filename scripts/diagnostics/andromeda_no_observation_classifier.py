#!/usr/bin/env python3
"""Aggregate-only read of completed Andromeda checkpoints lacking served-price observations."""
import json, sys

PHP=r'''
error_reporting(0);ini_set('display_errors','0');ini_set('log_errors','0');ob_start();
$result=['status'=>'blocked','reason'=>'inspection_unconfirmed','supplier_calls'=>0,'database_access'=>false,'remote_writes'=>0];
try{
 if(PHP_SAPI!=='cli')throw new RuntimeException('cli_only');
 $root=realpath(getcwd());if(!$root||basename($root)!=='anytoour.ru')throw new RuntimeException('wrong_project');
 $target=$root.'/_preview/search3-anex-candidate';$configPath=$target.'/.andromeda-private.php';
 $producer=$target.'/api-andromeda-quote-preview.php';
 if(realpath($configPath)!==$configPath||!is_file($configPath)||realpath($producer)!==$producer||!is_file($producer)||is_link($producer))throw new RuntimeException('runtime_missing');
 $config=require $configPath;if(!is_array($config)||!is_string($config['catalog_path']??null))throw new RuntimeException('runtime_missing');
 $directory=dirname($config['catalog_path']).'/searches';if(realpath($directory)!==$directory||!is_dir($directory)||is_link($directory))throw new RuntimeException('search_store_missing');
 $producerMtime=filemtime($producer);if(!is_int($producerMtime)||$producerMtime<1)throw new RuntimeException('runtime_missing');
 $summary=['completed'=>0,'with_observation'=>0,'without_observation'=>0,'kind'=>['quote'=>0,'quote_flight'=>0],
  'observation_key'=>['absent'=>0,'null'=>0],'final_price_verified'=>['true'=>0,'false'=>0,'other'=>0],
  'price_observation'=>['present'=>0,'absent'=>0],'result_state'=>['quote_verified'=>0,'flight_selection_required'=>0,'other'=>0],
  'relative_to_current_producer'=>['before'=>0,'same_or_after'=>0]];
 $entries=0;
 foreach(new DirectoryIterator($directory) as $entry){
  if($entry->isDot())continue;if(++$entries>50000)throw new RuntimeException('checkpoint_inventory_too_large');
  $name=$entry->getFilename();$kind=null;
  if(preg_match('/-quote-flight-v1\.json$/D',$name))$kind='quote_flight';elseif(preg_match('/-quote-v1\.json$/D',$name))$kind='quote';else continue;
  if($entry->isLink()||!$entry->isFile()||$entry->getSize()<2||$entry->getSize()>131072)continue;
  $path=$entry->getPathname();if(realpath($path)!==$path||(stat($path)['nlink']??0)!==1)continue;
  try{$envelope=json_decode(file_get_contents($path),true,64,JSON_THROW_ON_ERROR);}catch(Throwable $ignored){continue;}
  $state=is_array($envelope)?($envelope['state']??null):null;$row=is_array($state)?($state['result']??null):null;
  if(($state['status']??null)!=='completed'||!is_array($row))continue;
  ++$summary['completed'];
  if(array_key_exists('served_price_observation',$row)&&$row['served_price_observation']!==null){++$summary['with_observation'];continue;}
  ++$summary['without_observation'];++$summary['kind'][$kind];
  ++$summary['observation_key'][array_key_exists('served_price_observation',$row)?'null':'absent'];
  $verified=$row['final_price_verified']??null;++$summary['final_price_verified'][$verified===true?'true':($verified===false?'false':'other')];
  ++$summary['price_observation'][isset($row['price_observation'])?'present':'absent'];
  $safe=in_array($row['state']??null,['quote_verified','flight_selection_required'],true)?$row['state']:'other';++$summary['result_state'][$safe];
  ++$summary['relative_to_current_producer'][$entry->getMTime()<$producerMtime?'before':'same_or_after'];
 }
 $result=['status'=>'ok','summary'=>$summary,'supplier_calls'=>0,'database_access'=>false,'remote_writes'=>0];
}catch(Throwable $e){$allowed=['cli_only','wrong_project','runtime_missing','search_store_missing','checkpoint_inventory_too_large'];$reason=in_array($e->getMessage(),$allowed,true)?$e->getMessage():'inspection_unconfirmed';$result=['status'=>'blocked','reason'=>$reason,'supplier_calls'=>0,'database_access'=>false,'remote_writes'=>0];}
while(ob_get_level())ob_end_clean();echo json_encode($result,JSON_THROW_ON_ERROR);
'''

def validate(v):
 if not isinstance(v,dict) or v.get('supplier_calls')!=0 or v.get('database_access') is not False or v.get('remote_writes')!=0:raise ValueError('classifier_invalid')
 if v.get('status')=='blocked':return v
 if set(v)!={'status','summary','supplier_calls','database_access','remote_writes'} or v['status']!='ok' or not isinstance(v['summary'],dict):raise ValueError('classifier_invalid')
 text=json.dumps(v,sort_keys=True)
 for forbidden in ('search_ref','offer_ref','supplier_offer_id','claiminc','final_price"','amount"','filename','catalog_path'):
  if forbidden in text:raise ValueError('classifier_private_leak')
 return v

def main():
 if sys.argv!=[sys.argv[0],'--read-only']:raise SystemExit('usage: andromeda_no_observation_classifier.py --read-only')
 from anex_search3_owner_decisions import ssh_php
 try:r=validate(ssh_php(PHP,{}))
 except Exception:r={'status':'blocked','reason':'remote_outcome_unknown','supplier_calls':0,'database_access':False,'remote_writes':0}
 print(json.dumps(r,sort_keys=True,separators=(',',':')))
 if r['status']!='ok':raise SystemExit('classifier unconfirmed; no retry')
if __name__=='__main__':main()
