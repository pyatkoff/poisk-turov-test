#!/usr/bin/env python3
"""Read-only deployed Search3/Andromeda wiring inspection; no HTTP or supplier calls."""
import json, sys
PHP=r'''
error_reporting(0);ini_set('display_errors','0');ini_set('log_errors','0');ob_start();
$out=['status'=>'blocked','reason'=>'inspection_unconfirmed','supplier_calls'=>0,'http_requests'=>0,'database_access'=>false,'remote_writes'=>0];
try{
 if(PHP_SAPI!=='cli')throw new RuntimeException('cli_only');$root=realpath(getcwd());if(!$root||basename($root)!=='anytoour.ru')throw new RuntimeException('wrong_project');
 $site=$root.'/_preview/search3-site-candidate';$iso=$root.'/_preview/search3-anex-candidate';
 if(!is_dir($site)||is_link($site)||!is_dir($iso)||is_link($iso))throw new RuntimeException('candidate_missing');
 $files=['index.php','search-page-v2.php','poisk-turov/index.php','andromeda-provider-v1.js','assets.php'];$texts=[];
 foreach($files as $name){$p=$site.'/'.$name;if(is_file($p)&&!is_link($p)&&filesize($p)<=2097152){$texts[$name]=file_get_contents($p);}}
 $all=implode("\n",array_values($texts));
 $routeDefined=strpos($all,'V2_ANDROMEDA_API_PUBLIC_PATH')!==false;$routeNonEmpty=false;$routeSameOrigin=false;$routeTargetsIsolated=false;
 if(preg_match("/V2_ANDROMEDA_API_PUBLIC_PATH[^\n]{0,300}['\"](\/[^'\"]+\.php)['\"]/",$all,$m)){$routeNonEmpty=true;$routeSameOrigin=str_starts_with($m[1],'/');$routeTargetsIsolated=str_contains($m[1],'/_preview/search3-anex-candidate/');}
 $providerSource=isset($texts['andromeda-provider-v1.js']);$quoteClient=$providerSource&&strpos($texts['andromeda-provider-v1.js'],'quoteEndpoint')!==false&&strpos($texts['andromeda-provider-v1.js'],'listing_price_ref')!==false;
 $bundleReferences=strpos($all,'andromeda-provider-v1.js')!==false;$configPublishes=strpos($all,'andromedaApi')!==false;
 $isolatedSearch=is_file($iso.'/api-andromeda-search3-preview.php')&&!is_link($iso.'/api-andromeda-search3-preview.php');
 $isolatedQuote=is_file($iso.'/api-andromeda-quote-preview.php')&&!is_link($iso.'/api-andromeda-quote-preview.php');
 $out=['status'=>'ok','site'=>['route_symbol_present'=>$routeDefined,'route_nonempty_literal'=>$routeNonEmpty,'route_same_origin'=>$routeSameOrigin,'route_targets_isolated_candidate'=>$routeTargetsIsolated,'config_publishes_andromeda_api'=>$configPublishes,'provider_source_present'=>$providerSource,'bundle_references_provider'=>$bundleReferences,'quote_client_present'=>$quoteClient],'isolated'=>['search_endpoint_present'=>$isolatedSearch,'quote_endpoint_present'=>$isolatedQuote],'supplier_calls'=>0,'http_requests'=>0,'database_access'=>false,'remote_writes'=>0];
}catch(Throwable $e){$allowed=['cli_only','wrong_project','candidate_missing'];$out=['status'=>'blocked','reason'=>in_array($e->getMessage(),$allowed,true)?$e->getMessage():'inspection_unconfirmed','supplier_calls'=>0,'http_requests'=>0,'database_access'=>false,'remote_writes'=>0];}
while(ob_get_level())ob_end_clean();echo json_encode($out,JSON_THROW_ON_ERROR);
'''
def validate(v):
 if not isinstance(v,dict) or v.get('supplier_calls')!=0 or v.get('http_requests')!=0 or v.get('database_access') is not False or v.get('remote_writes')!=0: raise ValueError('invalid')
 if v.get('status')=='ok' and set(v)!={'status','site','isolated','supplier_calls','http_requests','database_access','remote_writes'}: raise ValueError('invalid')
 if any(k in json.dumps(v) for k in ('username','password','catalog_path','search_ref','offer_ref')): raise ValueError('private_leak')
 return v
def main():
 if sys.argv!=[sys.argv[0],'--read-only']: raise SystemExit('usage: --read-only')
 from anex_search3_owner_decisions import ssh_php
 try:r=validate(ssh_php(PHP,{}))
 except Exception:r={'status':'blocked','reason':'remote_outcome_unknown','supplier_calls':0,'http_requests':0,'database_access':False,'remote_writes':0}
 print(json.dumps(r,sort_keys=True,separators=(',',':')))
 if r['status']!='ok':raise SystemExit('inspection unconfirmed; no retry')
if __name__=='__main__':main()
