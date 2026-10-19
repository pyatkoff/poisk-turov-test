<?php
declare(strict_types=1);
const HMSIH_OP='hotel-match-samo-identity-harvest-retained-1971-20260919-v1';
const HMSIH_MAX_DEPTH=18;
const HMSIH_MAX_NODES=2000000;
function need(bool $v,string $w):void{if(!$v)throw new RuntimeException($w);}
function scalar(mixed $v):?string{if(!is_scalar($v))return null;$s=trim((string)$v);return $s===''?null:$s;}
function positive(mixed $v):?string{$s=scalar($v);return $s!==null&&preg_match('/^[1-9][0-9]{0,31}$/D',$s)?$s:null;}
function sensitive(string $k):bool{return preg_match('/passenger|tourist|email|phone|auth|token|secret|password|cookie|session|credential|bearer/i',$k)===1;}
function identity_key(string $k):bool{return preg_match('/(?:hotel|operator|native|original|supplier|provider).*(?:id|key|code)|(?:id|key|code).*(?:hotel|operator|native|original|supplier|provider)/i',$k)===1;}
function link_key(string $k):bool{return preg_match('/url|uri|href|link/i',$k)===1;}
function url_shape(string $v):?array{
 if(!preg_match('~^https?://~i',$v))return null;$p=parse_url($v);if(!is_array($p)||empty($p['host']))return null;
 $keys=[];if(isset($p['query'])){parse_str($p['query'],$q);$keys=array_keys($q);sort($keys,SORT_STRING);}
 return ['scheme'=>strtolower((string)($p['scheme']??'')),'host'=>strtolower((string)$p['host']),'path'=>(string)($p['path']??''),'query_keys'=>$keys,'value_sha256'=>hash('sha256',$v)];
}
function walk(mixed $v,string $path,array $ctx,array &$facts,int &$nodes,int $depth=0):void{
 need(++$nodes<=HMSIH_MAX_NODES,'node_cap');need($depth<=HMSIH_MAX_DEPTH,'depth_cap');
 if(!is_array($v))return;
 $local=$ctx;
 foreach($v as $k=>$x){$ks=(string)$k;if(sensitive($ks))continue;
   if(in_array(strtolower($ks),['hotelkey','hotelid','hotelcode'],true)&&($id=positive($x))!==null)$local['hotel'][]=['path'=>$path.'/'.$ks,'value'=>$id];
   if(in_array(strtolower($ks),['operatorkey','operatorid','operatorcode'],true)&&($id=positive($x))!==null)$local['operator'][]=['path'=>$path.'/'.$ks,'value'=>$id];
 }
 foreach($v as $k=>$x){$ks=(string)$k;if(sensitive($ks))continue;$p=$path.'/'.$ks;
   if(is_array($x)){walk($x,$p,$local,$facts,$nodes,$depth+1);continue;}
   $s=scalar($x);if($s===null)continue;$shape=url_shape($s);
   if($shape!==null||link_key($ks)||identity_key($ks)){
     $facts[]=['path'=>$p,'kind'=>$shape!==null?'url':(identity_key($ks)?'identity_scalar':'link_scalar'),'value_sha256'=>hash('sha256',$s),'url_shape'=>$shape,'numeric_value'=>positive($s),'context'=>$local];
   }
 }
}
function save(string $p,array $v):string{$b=json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";$f=fopen($p,'xb');need(is_resource($f),'open');try{need(fwrite($f,$b)===strlen($b)&&fflush($f),'write');if(function_exists('fsync'))need(fsync($f),'sync');}finally{fclose($f);}need(hash_file('sha256',$p)===hash('sha256',$b),'readback');return hash('sha256',$b);}
if(in_array('--self-test',$argv??[],true)){
 $facts=[];$n=0;$x=['hotelKey'=>190031,'operatorKey'=>5,'original'=>['hotelKey'=>8365,'isOperatorHotelKey'=>0,'hotelUrl'=>'https://operator.test/hotel?id=8365&foo=x'],'token'=>'NO'];
 walk($x,'$',[],$facts,$n);need(count($facts)>=2,'facts');$urls=array_values(array_filter($facts,fn($f)=>$f['kind']==='url'));need(count($urls)===1,'url');need($urls[0]['url_shape']['query_keys']===['foo','id'],'keys');need(($urls[0]['context']['hotel'][0]['value']??null)==='190031','catalog_ctx');need(($urls[0]['context']['hotel'][1]['value']??null)==='8365','native_ctx');echo "SAMO_IDENTITY_HARVEST_SELFTEST_OK facts=".count($facts)."\n";exit(0);
}
$dir=(string)getenv('MATCH_OPERATION_DIR');need(PHP_SAPI==='cli'&&is_dir($dir)&&basename($dir)===HMSIH_OP,'dir');
$input=(string)getenv('MATCH_RAW_DIR');need(is_dir($input),'input');$files=glob($input.'/raw-*.json')?:[];sort($files,SORT_STRING);need(count($files)>0,'raw_absent');
$all=[];$summary=[];$nodes=0;
foreach($files as $file){$raw=file_get_contents($file);need(is_string($raw),'read');$data=json_decode($raw,true,128,JSON_THROW_ON_ERROR);$facts=[];walk($data,'$',[],$facts,$nodes);
 foreach($facts as &$f){$f['source_file']=basename($file);$f['source_sha256']=hash('sha256',$raw);}unset($f);$all=array_merge($all,$facts);
 $summary[]=['file'=>basename($file),'sha256'=>hash('sha256',$raw),'bytes'=>strlen($raw),'facts'=>count($facts)];
}
$dedup=[];foreach($all as $f){$ctx=$f['context'];$hotels=[];foreach($ctx['hotel']??[] as $h)$hotels[$h['value']]=true;$ops=[];foreach($ctx['operator']??[] as $o)$ops[$o['value']]=true;$key=hash('sha256',json_encode([array_keys($hotels),array_keys($ops),$f['kind'],$f['path'],$f['value_sha256']]));if(!isset($dedup[$key]))$dedup[$key]=$f;}
$private=['operation'=>HMSIH_OP,'state'=>'completed_read_only','provider_calls'=>0,'database_access'=>0,'database_writes'=>0,'raw_files'=>count($files),'nodes_scanned'=>$nodes,'facts_total'=>count($all),'facts_unique'=>count($dedup),'files'=>$summary,'facts'=>array_values($dedup)];
$privateHash=save($dir.'/private-index.json',$private);
$public=[];$urlCount=0;$numericCount=0;foreach($dedup as $f){if($f['kind']==='url')$urlCount++;if($f['numeric_value']!==null)$numericCount++;$public[]=['source_file'=>$f['source_file'],'source_sha256'=>$f['source_sha256'],'path'=>$f['path'],'kind'=>$f['kind'],'value_sha256'=>$f['value_sha256'],'url_shape'=>$f['url_shape'],'numeric_value'=>$f['numeric_value'],'hotel_ids'=>array_values(array_unique(array_map(fn($x)=>$x['value'],$f['context']['hotel']??[]))),'operator_ids'=>array_values(array_unique(array_map(fn($x)=>$x['value'],$f['context']['operator']??[])))];}
$out=['operation'=>HMSIH_OP,'state'=>'completed_read_only','provider_calls'=>0,'database_access'=>0,'database_writes'=>0,'raw_files'=>count($files),'nodes_scanned'=>$nodes,'facts_total'=>count($all),'facts_unique'=>count($dedup),'url_facts'=>$urlCount,'numeric_identity_facts'=>$numericCount,'private_index_sha256'=>$privateHash,'facts'=>$public];
$rh=save($dir.'/result.json',$out);save($dir.'/receipt.json',['operation'=>HMSIH_OP,'state'=>'completed_read_only','result_sha256'=>$rh,'private_index_sha256'=>$privateHash,'provider_calls'=>0,'database_writes'=>0,'readback_verified'=>true]);
echo json_encode(['state'=>'completed_read_only','raw_files'=>count($files),'facts_unique'=>count($dedup),'url_facts'=>$urlCount,'numeric_identity_facts'=>$numericCount,'result_sha256'=>$rh],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
