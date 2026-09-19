<?php
declare(strict_types=1);
const OP='int-local-db-autosave-failure-readback-20260919-v1';
function txt(array$r,array$keys):string{foreach($keys as$k){$v=$r[$k]??null;if(is_string($v)&&trim($v)!=='')return trim($v);if(is_array($v))foreach(['russianName','name','title','label','code']as$n)if(is_string($v[$n]??null)&&trim($v[$n])!=='')return trim($v[$n]);}return'';}
function validDate($v):bool{$s=trim((string)$v);foreach(['!Y-m-d','!d.m.Y']as$f){$d=DateTimeImmutable::createFromFormat($f,$s,new DateTimeZone('UTC'));$e=DateTimeImmutable::getLastErrors();if($d&&($e===false||(($e['warning_count']??0)===0&&($e['error_count']??0)===0)))return true;}return false;}
function validMoney($v):bool{if(is_array($v))$v=$v['value']??$v['amount']??null;if(!is_scalar($v))return false;$s=str_replace(',','.',trim((string)$v));return preg_match('/^(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?$/D',$s)&&preg_match('/[1-9]/',$s);}
function main(string$root):array{$path=rtrim((string)getenv('HOME'),'/').'/.anytour-ops/int-local-db-natural-search-20260919-v1/results.raw.json';if(!is_file($path)||is_link($path)||filesize($path)>8000000)throw new RuntimeException('RESULTS');$raw=file_get_contents($path);$list=json_decode($raw,true,64,JSON_THROW_ON_ERROR);if(!is_array($list)||!array_is_list($list))throw new RuntimeException('SHAPE');
 $missing=[];$tours=0;$first=[];$hotels=0;foreach($list as$hi=>$h){if(!is_array($h)){$missing['malformed_hotel']=($missing['malformed_hotel']??0)+1;continue;}++$hotels;$hid=filter_var($h['id']??null,FILTER_VALIDATE_INT);$ts=$h['tours']??null;if($hid===false||$hid<1)$missing['hotel_id']=($missing['hotel_id']??0)+1;if(!is_array($ts)||!array_is_list($ts)){$missing['hotel_tours']=($missing['hotel_tours']??0)+1;continue;}
  foreach($ts as$ti=>$t){++$tours;$bad=[];if(!is_array($t))$bad[]='malformed_tour';else{
   $id=$t['id']??$t['tourId']??null;if(!is_string($id)&&!is_int($id)||trim((string)$id)==='')$bad[]='tour_id';
   if(!validDate($t['date']??$t['checkin']??null))$bad[]='date';$n=filter_var($t['nights']??null,FILTER_VALIDATE_INT);if($n===false||$n<1||$n>30)$bad[]='nights';
   if(!validMoney($t['price']??null))$bad[]='price';if(strtoupper(trim((string)($t['currency']??'RUB')))!=='RUB')$bad[]='currency';
   if(txt($t,['mealName','mealType','meal','pansion'])==='')$bad[]='meal';if(txt($t,['roomType','roomName','room'])==='')$bad[]='room';
   if(txt($t,['operatorName','operator'])==='')$bad[]='operator';}
   foreach($bad as$b)$missing[$b]=($missing[$b]??0)+1;if($bad&&count($first)<12)$first[]=['hotel_id'=>(int)$hid,'hotel_name'=>is_string($h['name']??null)?mb_substr($h['name'],0,100,'UTF-8'):null,'tour_index'=>$ti,'missing'=>$bad];
  }}
 ksort($missing);$files=['api'=>$root.'/api-v2.php','helper'=>$root.'/app/integrations/tourvisor-anytour-offer-autosave.php'];$runtime=[];foreach($files as$k=>$f)$runtime[$k]=is_file($f)&&!is_link($f)?['sha256'=>hash_file('sha256',$f),'bytes'=>filesize($f),'has_autosave_hook'=>$k==='api'?str_contains((string)file_get_contents($f),'tourvisor_autosave_results'):null]:['missing'=>true];
 return['operation'=>OP,'results_sha256'=>hash('sha256',$raw),'hotels'=>$hotels,'tours'=>$tours,'missing_counts'=>$missing,'first_invalid'=>$first,'runtime'=>$runtime,'supplier_calls'=>0,'db_writes'=>0];}
if(PHP_SAPI==='cli'){try{if(count($argv)!==2)throw new RuntimeException('ARGS');$root=realpath($argv[1]);if(!$root||basename($root)!=='anytoour.ru')throw new RuntimeException('ROOT');echo json_encode(main($root),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";}catch(Throwable$e){fwrite(STDERR,"AUTOSAVE_READBACK_FAILED\n");exit(2);}}
