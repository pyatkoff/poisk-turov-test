<?php
declare(strict_types=1);
const OP='hotel-match-anex-three-source-sharm4-1971-20260918-v2';
const DATE='20261026';
function xnorm(mixed $v):string{$s=mb_strtolower(trim((string)$v),'UTF-8');$s=str_replace('ё','е',$s);$s=preg_replace('/[^\p{L}\p{N}]+/u',' ',$s)??$s;return trim(preg_replace('/\s+/u',' ',$s)??$s);}
function xid(mixed $v):?string{if(is_int($v)&&$v>0)$v=(string)$v;return is_string($v)&&preg_match('/^[1-9][0-9]{0,31}$/D',$v)?$v:null;}
function xdict(array $rows,array $names):int{$w=array_fill_keys(array_map('xnorm',$names),true);$f=[];foreach($rows as $r){if(!is_array($r))continue;$id=filter_var($r['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);if($id===false)continue;foreach(['name','lName','alias','title'] as $k){$n=xnorm($r[$k]??'');if($n!==''&&isset($w[$n])){$f[(int)$id]=true;break;}}}if(count($f)!==1)throw new RuntimeException('DICT_NOT_UNIQUE');return(int)array_key_first($f);}
function xmoney(mixed $v):?string{if(is_int($v)||(is_float($v)&&is_finite($v)))$v=(string)$v;return is_string($v)&&preg_match('/^(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?$/D',$v)&&preg_match('/[1-9]/',$v)?$v:null;}
function xwrite(string $p,array $v):void{$raw=json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";$f=@fopen($p,'x+b');if(!$f)throw new RuntimeException('OUTPUT_EXISTS');fwrite($f,$raw);fflush($f);if(function_exists('fsync'))@fsync($f);fclose($f);if(file_get_contents($p)!==$raw)throw new RuntimeException('OUTPUT_READBACK');}
if(PHP_SAPI!=='cli'||($argv[1]??'')!=='--execute'||!isset($argv[2]))exit(2);
$out=$argv[2];if(!is_dir($out)||is_link($out)||(getenv('OPERATION_ID')?:'')!==OP)throw new RuntimeException('GUARD');
$user=getenv('ANDROMEDA_USERNAME');$pass=getenv('ANDROMEDA_PASSWORD');if(!is_string($user)||trim($user)===''||!is_string($pass)||$pass==='')throw new RuntimeException('CREDENTIALS');
require_once __DIR__.'/../../app/integrations/andromeda-client.php';
require_once __DIR__.'/../../app/integrations/andromeda-transport.php';
$calls=0;$result=['operation'=>OP,'status'=>'failed','provider'=>'andromeda','calls_started'=>0,'criteria'=>['resort'=>'Sharm El Sheikh','star'=>4,'date'=>'2026-10-26','nights'=>7,'adults'=>2,'operator'=>'ANEX'],'rows'=>[],'writes'=>0];
try{
  $cat=new AnyTourAndromedaClient(new AnyTourAndromedaTransport(false),true);
  $calls++;$cat->login($user,$pass);
  $calls++;$tf=$cat->catalog('townfrom');$dep=xdict($tf['TOWNFROM'],['Москва','Moscow']);
  $calls++;$st=$cat->catalog('state',['TOWNFROMINC'=>$dep]);$state=xdict($st['STATE'],['Египет','Egypt']);
  $calls++;$all=$cat->catalog('all',['TOWNFROMINC'=>$dep,'STATEINC'=>$state]);
  $town=xdict($all['TOWNTO'],['Sharm El Sheikh','Sharm El-Sheikh','Шарм-эль-Шейх','Шарм эль Шейх']);
  $op=xdict($all['OPERATORS'],['ANEX','ANEX TOUR','Анекс Тур']);if($op!==5)throw new RuntimeException('ANEX_OPERATOR');
  $cur=xdict($all['CURRENCY'],['RUB','RUR','Рубль','Рубли','Руб']);
  $star=xdict($all['STARS'],['4','4*','4★']);
  $pc=new AnyTourAndromedaClient(new AnyTourAndromedaTransport(true),true);
  $calls++;$pc->login($user,$pass);
  $params=['TOWNFROMINC'=>$dep,'STATEINC'=>$state,'CHECKIN_BEG'=>DATE,'CHECKIN_END'=>DATE,'NIGHTS_FROM'=>7,'NIGHTS_TILL'=>7,'ADULT'=>2,'CHILD'=>0,'CURRENCYINC'=>$cur,'STARS'=>(string)$star,'OPERATORS'=>'5','TOWNTOINC'=>(string)$town,'PACKETTYPE'=>0,'PAGE'=>1,'GROUP_BY'=>32];
  $calls++;$reply=$pc->price($params);
  $seen=[];
  foreach($reply['PRICES'] as $r){
    if(!is_array($r)||(string)($r['operatorKey']??'')!=='5'||!in_array($r['isOperatorHotelKey']??null,[0,'0'],true))continue;
    $aid=xid($r['original']['hotelKey']??null);$did=xid($r['hotelKey']??null);$price=xmoney($r['price']??null);$name=trim((string)($r['hotel']??''));
    if(!$aid||!$did||$price===null||$name===''||preg_match('/fortuna|roulette|фортуна|рулетк/ui',$name))continue;
    $row=['hotel_name'=>$name,'resort'=>'Sharm El Sheikh','stars'=>4,'date'=>'2026-10-26','nights'=>7,'adults'=>2,'children'=>0,'meal'=>(string)($r['meal']??'unknown'),'room'=>(string)($r['room']??'unknown'),'price'=>$price,'currency'=>'RUB','andromeda_hotel_id'=>$did,'operator_key'=>'5','is_operator_hotel_key'=>0,'original_hotel_id'=>$aid];
    $key=hash('sha256',json_encode($row));if(isset($seen[$key]))continue;$seen[$key]=1;$result['rows'][]=$row;if(count($result['rows'])>=1000)break;
  }
  $result['status']='completed';$result['dictionary_ids']=['departure'=>$dep,'state'=>$state,'town_to'=>$town,'star'=>$star,'currency'=>$cur,'operator'=>$op];$result['pages_count']=$reply['PAGES_COUNT'];$result['native_anex_ids']=count(array_unique(array_column($result['rows'],'original_hotel_id')));
}catch(Throwable $e){$m=$e->getMessage();$result['error']=preg_match('/^[A-Z0-9_]{2,80}$/D',$m)?$m:'SANITIZED_FAILURE';}
$result['calls_started']=$calls;$result['no_replay']=$calls>0;
xwrite($out.'/andromeda.json',$result);
xwrite($out.'/andromeda-receipt.json',['operation'=>OP,'status'=>$result['status'],'calls_started'=>$calls,'rows'=>count($result['rows']),'writes'=>0,'no_replay'=>$result['no_replay'],'sha256'=>hash_file('sha256',$out.'/andromeda.json')]);
echo json_encode(['status'=>$result['status'],'calls'=>$calls,'rows'=>count($result['rows']),'native_anex_ids'=>$result['native_anex_ids']??0],JSON_UNESCAPED_SLASHES),"\n";
exit($result['status']==='completed'?0:2);
