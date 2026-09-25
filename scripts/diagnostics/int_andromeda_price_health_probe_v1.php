<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/app/integrations/andromeda-client.php';
require_once dirname(__DIR__,2).'/app/integrations/andromeda-transport.php';
const OP='int-andromeda-price-health-20260925-v1';
function savej($p,$v){$r=json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";if(file_put_contents($p,$r,LOCK_EX)!==strlen($r))throw new RuntimeException('write');}
$root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('INT_OPERATION_DIR');if(!is_dir($root)||!is_dir($dir)||basename($dir)!==OP)throw new RuntimeException('scope');
$cfg=null;foreach([$root.'/_preview/search3-anex-candidate/.andromeda-private.php',$root.'/v2/.andromeda-private.php'] as $p){if(is_file($p)&&!is_link($p)){ $x=require$p;if(is_array($x)&&($x['enabled']??false)===true){$cfg=$x;break;}}}if(!$cfg)throw new RuntimeException('config');
$calls=0;$transport=function($url,$opts)use(&$calls,$dir){$calls++;if($calls>2)throw new RuntimeException('cap');savej($dir.'/http-'.$calls.'-reserved.json',['operation'=>OP,'call'=>$calls,'state'=>'reserved_before_http']);$t=new AnyTourAndromedaTransport(true);return $t($url,$opts);};
$out=['operation'=>OP,'state'=>'blocked','provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0];
try{$c=new AnyTourAndromedaClient($transport,true);$c->login((string)$cfg['username'],(string)$cfg['password']);
$params=['TOWNFROMINC'=>1,'STATEINC'=>3,'CHECKIN_BEG'=>'20260927','CHECKIN_END'=>'20260927','NIGHTS_FROM'=>7,'NIGHTS_TILL'=>7,'ADULT'=>2,'CHILD'=>0,'CURRENCYINC'=>643,'PACKETTYPE'=>0,'PAGE'=>1,'GROUP_BY'=>32];
$r=$c->price($params);$out+=['state'=>'price_ok','page'=>$r['PAGE'],'pages_count'=>$r['PAGES_COUNT'],'price_rows'=>count($r['PRICES'])];
}catch(AnyTourAndromedaPriceSupplierException $e){$out+=['state'=>'supplier_rejected','reason'=>'ANDROMEDA_SUPPLIER_ERROR','supplier_error_facts'=>$e->diagnosticFacts()];}
catch(Throwable $e){$out+=['state'=>'failed','reason'=>preg_replace('/[^A-Za-z0-9_.:-]+/','_',substr($e->getMessage(),0,100))];}
$out['provider_http_calls']=$calls;savej($dir.'/result.json',$out);echo json_encode($out,JSON_UNESCAPED_SLASHES)."\n";exit(in_array($out['state'],['price_ok','supplier_rejected'],true)?0:2);
