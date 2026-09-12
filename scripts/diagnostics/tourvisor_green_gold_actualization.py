#!/usr/bin/env python3
import json, os, shlex, subprocess, sys, tempfile
from pathlib import Path

EXPERIMENT='tourvisor_green_gold_actualization_20260913_v1'
DATE='2026-10-12'
HOTEL_ID=21753


def php_source():
    return r'''declare(strict_types=1);
$home=(string)getenv('HOME');
$root=realpath($home.'/www/anytoour.ru');
if(!$root) throw new RuntimeException('TV_ACT_RUNTIME');
$helper=is_file($root.'/data/tourvisor-client-v1.php')?$root.'/data/tourvisor-client-v1.php':$root.'/v2/data/tourvisor-client-v1.php';
require_once $helper;
$token=v2_data_tourvisor_token();
if(!is_string($token)||trim($token)==='') throw new RuntimeException('TV_ACT_TOKEN');
$log=[];
function act_text($v,$limit=120){if(!is_string($v)||strlen($v)>4096)return null;$v=trim(strip_tags($v));return mb_substr($v,0,$limit,'UTF-8');}
function act_id($v){return (is_int($v)||is_string($v))&&preg_match('/\\A[1-9][0-9]{0,17}\\z/D',(string)$v)?(string)$v:null;}
function act_money($v){if(is_int($v)||(is_float($v)&&is_finite($v)))$v=(string)$v;return is_string($v)&&preg_match('/\\A[0-9]{1,12}(?:\\.[0-9]{1,2})?\\z/D',$v)?$v:null;}
function act_get($path,$params,$token,&$log){
  if(!preg_match('~\\A/(?:departures|countries|operators|tours/search(?:/[1-9][0-9]{0,17}(?:/status)?)?|tours/[1-9][0-9]{0,17}(?:/flights)?)\\z~D',$path)) throw new RuntimeException('TV_ACT_PATH');
  $url='https://api.tourvisor.ru/search/api/v1'.$path; $q=v2_data_query_string($params); if($q!=='')$url.='?'.$q;
  $ch=curl_init($url); if($ch===false)throw new RuntimeException('TV_ACT_CURL'); $body=''; $too=false;
  $log[]=['path'=>preg_replace('~/[1-9][0-9]{5,}~','/{id}',$path)]; $idx=count($log)-1;
  try{curl_setopt_array($ch,[CURLOPT_HTTPGET=>true,CURLOPT_RETURNTRANSFER=>false,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>25,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROXY=>'',CURLOPT_NOPROXY=>'*',CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$token,'Accept: application/json'],CURLOPT_WRITEFUNCTION=>static function($u,$chunk)use(&$body,&$too){if(strlen($body)+strlen($chunk)>3000000){$too=true;return 0;}$body.=$chunk;return strlen($chunk);}]);$ok=curl_exec($ch);$http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$log[$idx]+=['http'=>$http,'bytes'=>strlen($body)];if($too||$ok===false||$http<200||$http>=300)throw new RuntimeException('TV_ACT_HTTP');$x=json_decode($body,true,48,JSON_THROW_ON_ERROR);if(!is_array($x))throw new RuntimeException('TV_ACT_JSON');return $x;}finally{curl_close($ch);} }
function norm($v){$s=act_text(is_array($v)?($v['name']??$v['russianName']??''):$v,100)??'';$s=mb_strtolower($s,'UTF-8');return trim(preg_replace('/[^\\p{L}\\p{N}]+/u',' ',$s));}
$deps=act_get('/departures',[],$token,$log);$dep=null;foreach($deps as $r){if(is_array($r)&&in_array(norm($r['name']??''),['москва','moscow'],true)){$dep=(int)$r['id'];break;}}if(!$dep)throw new RuntimeException('TV_ACT_DEP');
$countries=act_get('/countries',['departureId'=>$dep],$token,$log);$country=null;foreach($countries as $r){if(is_array($r)&&in_array(norm($r['name']??''),['турция','turkey'],true)){$country=(int)$r['id'];break;}}if(!$country)throw new RuntimeException('TV_ACT_COUNTRY');
$ops=act_get('/operators',['departureId'=>$dep,'countryId'=>$country],$token,$log);$op=null;foreach($ops as $r){if(!is_array($r))continue;$n=norm($r['name']??'');if(in_array($n,['anex','anex tour','anextour','анекс','анекс тур'],true)){$op=(int)$r['id'];break;}}if(!$op)throw new RuntimeException('TV_ACT_OPERATOR');
$criteria=['departureId'=>$dep,'countryId'=>$country,'dateFrom'=>'2026-10-12','dateTo'=>'2026-10-12','nightsFrom'=>7,'nightsTo'=>7,'adults'=>2,'childs'=>[],'currency'=>'RUB','onlyCharter'=>false,'onlyDirect'=>false,'hotelIds'=>[21753],'operatorIds'=>[$op]];
$start=act_get('/tours/search',$criteria,$token,$log);$sid=act_id($start['searchId']??null);if(!$sid)throw new RuntimeException('TV_ACT_SEARCH');
for($i=0;$i<8;$i++){sleep($i?4:1);$st=act_get('/tours/search/'.$sid.'/status',['operatorStatus'=>false],$token,$log);if((float)($st['progress']??0)>=100||strtolower((string)($st['status']??''))==='complete')break;}
$groups=act_get('/tours/search/'.$sid,['limit'=>25],$token,$log);$cand=[];
foreach($groups as $h){if(!is_array($h)||(int)($h['id']??0)!==21753)continue;foreach(($h['tours']??[]) as $t){if(!is_array($t))continue;if(($t['currency']??null)!=='RUB'||($t['date']??null)!=='2026-10-12'||(int)($t['nights']??0)!==7||(int)($t['adults']??0)!==2||(int)($t['childs']??0)!==0)continue;$oid=(int)(is_array($t['operator']??null)?($t['operator']['id']??0):0);if($oid!==$op)continue;$id=act_id($t['id']??null);$price=act_money($t['price']??null);$fuel=act_money($t['fuelCharge']??null);if(!$id||$price===null||$fuel===null)continue;$cand[]=['tour_id'=>$id,'price'=>$price,'fuel'=>$fuel,'currency'=>'RUB','base'=>(string)((float)$price-(float)$fuel),'room'=>act_text($t['roomType']??null),'placement'=>act_text($t['placement']??null),'meal'=>act_text(is_array($t['meal']??null)?($t['meal']['name']??$t['meal']['russianName']??null):($t['meal']??null))];}}
if(!$cand)throw new RuntimeException('TV_ACT_NO_TOUR');usort($cand,fn($a,$b)=>(float)$a['price']<=>(float)$b['price']);$search=$cand[0];$tid=$search['tour_id'];
$detail=act_get('/tours/'.$tid,['currency'=>'RUB'],$token,$log);if(act_id($detail['id']??null)!==$tid||($detail['currency']??null)!=='RUB')throw new RuntimeException('TV_ACT_DETAIL_CONTEXT');
$detailOut=['tour_id'=>$tid,'price'=>act_money($detail['price']??null),'fuel'=>act_money($detail['fuelCharge']??null),'currency'=>$detail['currency']??null,'room'=>act_text($detail['roomType']??null),'placement'=>act_text($detail['placement']??null)];
$fl=act_get('/tours/'.$tid.'/flights',['currency'=>'RUB'],$token,$log);$comb=[];foreach(($fl['flights']??[]) as $f){if(!is_array($f))continue;$p=$f['price']??[];$fc=$f['fuelCharge']??[];if(($p['currency']??null)!=='RUB'||($fc['currency']??null)!=='RUB')continue;$row=['is_default'=>(bool)($f['isDefault']??false),'price'=>act_money($p['value']??null),'fuel'=>act_money($fc['value']??null),'currency'=>'RUB','forward'=>[],'backward'=>[]];foreach(['forward','backward'] as $dir){foreach(($f[$dir]??[]) as $leg){if(!is_array($leg))continue;$row[$dir][]=['number'=>act_text($leg['number']??null,40),'company'=>act_text(is_array($leg['company']??null)?($leg['company']['name']??null):null,80),'dep_port'=>act_text($leg['departure']['port']['shortName']??null,20),'dep_date'=>act_text($leg['departure']['date']??null,20),'dep_time'=>act_text($leg['departure']['time']??null,20),'arr_port'=>act_text($leg['arrival']['port']['shortName']??null,20),'arr_date'=>act_text($leg['arrival']['date']??null,20),'arr_time'=>act_text($leg['arrival']['time']??null,20),'fuel_charges'=>array_map(static function($x){return is_array($x)?['amount'=>act_money($x['amount']??null),'currency'=>$x['currency']??null,'name'=>act_text($x['name']??null,80)]:null;},$leg['fuelCharges']??[])];}}$comb[]=$row;}
$surch=[];foreach(($fl['info']['surcharges']??[]) as $x){if(is_array($x))$surch[]=['amount'=>act_money($x['amount']??null),'currency'=>$x['currency']??null,'name'=>act_text($x['name']??null,100)];}
$out=['schema_version'=>1,'experiment_id'=>'tourvisor_green_gold_actualization_20260913_v1','status'=>'completed','scenario'=>['hotel_id'=>21753,'date'=>'2026-10-12','nights'=>7,'adults'=>2,'operator'=>'ANEX'],'search'=>$search,'detail'=>$detailOut,'actualized_flights'=>$comb,'surcharges'=>$surch,'request_log'=>$log,'booking_calls'=>0,'mapping_writes'=>0,'production_price_arithmetic_applied'=>false];echo json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
'''


def run(outdir: Path):
    names=('ANYTOOUR_DEPLOY_SSH_KEY','ANYTOOUR_DEPLOY_HOST','ANYTOOUR_DEPLOY_USER')
    if any(not os.environ.get(n,'').strip() for n in names): raise RuntimeError('missing_ssh')
    outdir.mkdir(parents=True,exist_ok=True)
    with tempfile.TemporaryDirectory(prefix='tv-act-',dir=os.environ.get('RUNNER_TEMP')) as td:
        key=Path(td)/'key'; key.write_text(os.environ[names[0]].rstrip()+'\n'); key.chmod(0o600)
        cmd=['ssh','-T','-i',str(key),'-o','IdentitiesOnly=yes','-o','BatchMode=yes','-o','StrictHostKeyChecking=accept-new','-o','UserKnownHostsFile='+str(Path(td)/'known_hosts'),'-o','ConnectTimeout=15','-l',os.environ[names[2]].strip(),os.environ[names[1]].strip(),'cd "$HOME/www/anytoour.ru" && php -r '+shlex.quote(php_source())]
        r=subprocess.run(cmd,text=True,capture_output=True,timeout=300)
    try: data=json.loads(r.stdout)
    except Exception: data={'schema_version':1,'experiment_id':EXPERIMENT,'status':'transport_unconfirmed','booking_calls':0,'mapping_writes':0,'production_price_arithmetic_applied':False}
    (outdir/'report.json').write_text(json.dumps(data,ensure_ascii=False,sort_keys=True,indent=2)+'\n')
    if r.returncode or data.get('status')!='completed': raise RuntimeError('actualization_failed')
    print(json.dumps(data,ensure_ascii=False,sort_keys=True))

if __name__=='__main__':
    if len(sys.argv)!=2: raise SystemExit('usage: tourvisor_green_gold_actualization.py OUTPUT_DIR')
    run(Path(sys.argv[1]))
