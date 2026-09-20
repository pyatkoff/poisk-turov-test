<?php
declare(strict_types=1);
const OP='hotel-match-userseen2041-tv-mass-1971-20260920-v1',DAY='2026-09-20',INPUT_SHA='2a375808d70ce4d1f73637888e8e80aafafd0fd5ecb8e0d66dca2cf76bfc8f34';
const LIMIT=3000,CAP=2600,POLLS=8,BODY_MAX=33554432,RISK_COMMENT=5751667327;
const API='https://api.tourvisor.ru/search/api/v1';
const PROTECTED_IDS=[420,1244,81154,68705,72755];
const ROUTES=[13=>['anex','agent.anextour.ru'],18=>['biblio','www.bgoperator.ru'],25=>['funsun','b2b.fstravel.com'],43=>['intourist','searchtour.intourist.ru']];
function need(bool $x,string $w):void{if(!$x)throw new RuntimeException($w);}
function enc(array $x):string{return json_encode($x,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT)."\n";}
function savej(string $p,array $x):string{$b=enc($x);$f=fopen($p,'xb');need(is_resource($f),'exclusive');try{need(fwrite($f,$b)===strlen($b),'write');need(fflush($f),'flush');if(function_exists('fsync'))need(fsync($f),'fsync');}finally{fclose($f);}need(file_get_contents($p)===$b,'readback');return hash('sha256',$b);}
function saveraw(string $p,string $b):string{$f=fopen($p,'xb');need(is_resource($f),'raw_exclusive');try{need(fwrite($f,$b)===strlen($b),'raw_write');need(fflush($f),'raw_flush');if(function_exists('fsync'))need(fsync($f),'raw_fsync');}finally{fclose($f);}return hash('sha256',$b);}
function readl($f):array{rewind($f);$x=json_decode((string)stream_get_contents($f),true,64,JSON_THROW_ON_ERROR);need(is_array($x),'ledger');return$x;}
function writel($f,array $x):void{$b=enc($x);rewind($f);ftruncate($f,0);need(fwrite($f,$b)===strlen($b),'ledger_write');fflush($f);if(function_exists('fsync'))fsync($f);}
function id(mixed $v):?int{if(is_array($v))$v=$v['id']??null;if(is_int($v)&&$v>0)return$v;if(is_string($v)&&preg_match('/^[1-9][0-9]{0,10}$/D',$v))return(int)$v;return null;}
function txt(mixed $v,int $n=300):string{return is_scalar($v)?substr(trim((string)$v),0,$n):'';}
function rows(array $p):array{if(array_is_list($p))return$p;foreach(['hotels','results','items']as$k)if(is_array($p[$k]??null))return$p[$k];return[];}
function sid(array $p):?string{foreach(['searchId','id']as$k){$v=$p[$k]??null;if((is_int($v)&&$v>0)||(is_string($v)&&preg_match('/^[1-9][0-9]{0,21}$/D',$v)))return(string)$v;}foreach($p as$v)if(is_array($v)&&($s=sid($v))!==null)return$s;return null;}
function complete(array $p):bool{if(isset($p['progress'])&&is_numeric($p['progress'])&&(int)$p['progress']>=100)return true;if(in_array(strtolower(txt($p['status']??'',30)),['complete','completed','done','ready'],true))return true;foreach($p as$v)if(is_array($v)&&complete($v))return true;return false;}
function qs(array $p):string{$o=[];foreach($p as$k=>$v){if($v===null||$v==='')continue;if(is_bool($v))$v=$v?'true':'false';if(is_array($v)){foreach($v as$i)$o[]=rawurlencode((string)$k).'='.rawurlencode((string)$i);}else$o[]=rawurlencode((string)$k).'='.rawurlencode((string)$v);}return implode('&',$o);}
function identity(array $x,string $k):?int{$v=$x[$k]??null;return is_array($v)?id($v['id']??null):id($x[$k.'Id']??null);}
function op_link(int $op,array $d):array{
 $u=$d['operatorLink']??null;if(!is_string($u)||$u===''||strlen($u)>8192)return['link_state'=>'missing'];
 $p=parse_url($u);if(!is_array($p)||($p['scheme']??'')!=='https'||strtolower((string)($p['host']??''))!==ROUTES[$op][1]||isset($p['user'])||isset($p['pass'])||isset($p['port'])||isset($p['fragment']))return['link_state'=>'invalid_origin','operator_link_sha256'=>hash('sha256',$u)];
 $pairs=[];foreach(explode('&',(string)($p['query']??''))as$z){if($z==='')continue;[$k,$v]=array_pad(explode('=',$z,2),2,'');$pairs[]=[rawurldecode($k),rawurldecode($v)];}
 foreach($pairs as[$k,$v])if(preg_match('/token|password|auth|secret|session/i',$k))return['link_state'=>'invalid_sensitive','operator_link_sha256'=>hash('sha256',$u)];
 $o=['operator_link'=>$u,'operator_link_sha256'=>hash('sha256',$u),'positive_native_candidates'=>[],'link_state'=>'captured'];
 if($op===13){$tok=[];foreach($pairs as[$k,$v])if(strtoupper($k)==='HOTELLIST')foreach(preg_split('/[,;]/',$v)?:[]as$t)if(trim($t)!=='')$tok[]=trim($t);$pos=array_values(array_map('intval',array_filter($tok,fn($x)=>preg_match('/^[1-9][0-9]{0,8}$/D',$x)===1)));$o+=['raw_identity_key'=>'HOTELLIST','raw_identity_tokens'=>$tok];$o['positive_native_candidates']=$pos;$o['link_state']=count($pos)===1&&count($tok)===1?'captured_single_native':'captured_ambiguous_native';}
 elseif(in_array($op,[25,43],true)){$tok=[];foreach($pairs as[$k,$v])if(strtoupper($k)==='HOTELS')foreach(preg_split('/[,;]/',$v)?:[]as$t)if(trim($t)!=='')$tok[]=trim($t);$pos=array_values(array_map('intval',array_filter($tok,fn($x)=>preg_match('/^[1-9][0-9]{0,12}$/D',$x)===1)));$o+=['raw_identity_key'=>'HOTELS','raw_identity_tokens'=>$tok];$o['positive_native_candidates']=$pos;$o['link_state']=count($pos)===1&&count($tok)===1?'captured_single_native':'captured_ambiguous_native';}
 else{$o['link_state']='captured_biblio_unproven';}
 return$o;
}
