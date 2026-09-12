<?php
declare(strict_types=1);
/** MATCH #1971: mass CURRENT unresolved ANEX ↔ Tourvisor live-offer evidence. READ ONLY. */
const HMOE_OP='hotel-match-live-offer-evidence-1971-20260912-v1';
const HMOE_DATES=['2026-10-05','2026-10-12'];
const HMOE_NIGHTS=7;
const HMOE_DEPARTURE=1;
const HMOE_HOTEL_CAP=10000;

function hmoe_norm(string $v,bool $generic=false): string {
    $v=str_replace(['Ё','ё'],['Е','е'],$v);
    $v=mb_strtoupper($v,'UTF-8');
    $v=preg_replace('/\bEX\.?\s*/u',' ',$v)??$v;
    $v=preg_replace('/[^\p{L}\p{N}]+/u',' ',$v)??$v;
    $parts=array_values(array_filter(preg_split('/\s+/u',trim($v))?:[]));
    if($generic)$parts=array_values(array_filter($parts,static fn($x)=>!in_array($x,['HOTEL','HOTELS','RESORT','SPA'],true)));
    return implode(' ',$parts);
}
function hmoe_aliases(string $v): array {
    $raw=[$v,preg_replace('/\s*\([^)]*\)\s*/u',' ',$v)??$v];
    if(preg_match_all('/\((?:EX\.?\s*)?([^)]{2,120})\)/iu',$v,$m))foreach($m[1] as $x)$raw[]=trim($x);
    $out=[];foreach($raw as $x)foreach([false,true] as $g){$k=hmoe_norm((string)$x,$g);if($k!=='')$out[$k]=true;}return array_keys($out);
}
function hmoe_rows(array $x): array {if(array_is_list($x))return array_values(array_filter($x,'is_array'));foreach(['hotels','items','results','data'] as $k)if(is_array($x[$k]??null)){ $r=hmoe_rows($x[$k]); if($r)return $r;}return [];}
function hmoe_complete(array $x): bool {if((int)($x['progress']??0)>=100)return true;if(in_array(strtolower((string)($x['status']??'')),['done','ready','complete','completed'],true))return true;foreach($x as $v)if(is_array($v)&&hmoe_complete($v))return true;return false;}
function hmoe_decimal($v): ?string {if(is_int($v)||is_float($v))$v=(string)$v;return is_string($v)&&preg_match('/\A(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?\z/D',$v)?$v:null;}
function hmoe_price_delta(?string $a,?string $b): ?float {if($a===null||$b===null||(float)$a<=0||(float)$b<=0)return null;return abs((float)$a-(float)$b)/max((float)$a,(float)$b);}
function hmoe_quarantine(string $name): bool {return (bool)preg_match('/\b(?:ROULETTE|FORTUNA|ТУР|ЭКСКУРС|EXCURSION)\b/ui',$name);}
function hmoe_tv_poll(int $sid): void {for($i=0;$i<40;$i++){if($i)usleep(1000000);if(hmoe_complete(v2_data_tv_get('/tours/search/'.$sid.'/status',['operatorStatus'=>false])))return;}throw new RuntimeException('tv_timeout');}
function hmoe_tv_fetch(int $sid): array {foreach([10000,5000,2000,1000,500,300,100] as $limit){try{return hmoe_rows(v2_data_tv_get('/tours/search/'.$sid,['limit'=>$limit]));}catch(Throwable $e){}}throw new RuntimeException('tv_results_unavailable');}
function hmoe_tv_offer(array $hotel): ?array {
    $tours=is_array($hotel['tours']??null)?$hotel['tours']:[];$best=null;
    foreach($tours as $t){if(!is_array($t))continue;$price=hmoe_decimal($t['price']??null);if($price===null||($best!==null&&(float)$price>=(float)$best['price']))continue;$best=['price'=>$price,'date'=>(string)($t['date']??''),'nights'=>(int)($t['nights']??0),'operator_id'=>(int)(is_array($t['operator']??null)?($t['operator']['id']??0):($t['operator']??0)),'meal'=>(string)(is_array($t['meal']??null)?($t['meal']['name']??''):($t['meal']??'')),'room'=>(string)($t['roomType']??$t['room']??''),'fuel_charge'=>isset($t['fuelCharge'])&&is_numeric($t['fuelCharge'])?(string)$t['fuelCharge']:null,'currency'=>(string)($t['currency']??'RUB')];}
    return $best;
}
function hmoe_tv_search(int $countryId,string $date): array {
    $start=v2_data_tv_get('/tours/search',['departureId'=>HMOE_DEPARTURE,'countryId'=>$countryId,'dateFrom'=>$date,'dateTo'=>$date,'nightsFrom'=>HMOE_NIGHTS,'nightsTo'=>HMOE_NIGHTS,'adults'=>2,'currency'=>'RUB','onlyCharter'=>false,'onlyDirect'=>false]);
    $sid=(int)($start['searchId']??$start['id']??0);if($sid<=0)throw new RuntimeException('tv_search_id');hmoe_tv_poll($sid);$rows=hmoe_tv_fetch($sid);$out=[];$rank=0;
    foreach($rows as $h){$id=(int)($h['id']??0);$name=trim((string)($h['name']??''));if($id<=0||$name==='')continue;$offer=hmoe_tv_offer($h);$out[]=['id'=>$id,'name'=>$name,'aliases'=>hmoe_aliases($name),'rank'=>++$rank,'offer'=>$offer];if(count($out)>HMOE_HOTEL_CAP)throw new RuntimeException('tv_cap');}
    return ['search_id'=>$sid,'hotels'=>$out];
}
function hmoe_indexes(array $hotels): array {$idx=[];foreach($hotels as $h)foreach($h['aliases'] as $a)$idx[$a][]=$h;return $idx;}

if(in_array('--self-test',$_SERVER['argv']??[],true)){
    $ok=[hmoe_norm('APERION BEACH HOTEL (EX. SEA PARADISE)',true)==='APERION BEACH SEA PARADISE',hmoe_norm('NORTH GARDEN RESORT SPA',true)==='NORTH GARDEN',hmoe_quarantine('Roulette 5* Sharm El Sheikh'),hmoe_quarantine('Тур Золотое Кольцо Турции'),!hmoe_quarantine('Grand Emin Hotel'),abs((hmoe_price_delta('100','105')??1)-5/105)<0.00001];
    foreach($ok as $v)if(!$v)throw new RuntimeException('self_test');echo "MATCH live offer evidence self-test PASS cases=".count($ok)." network=0 database=0\n";exit(0);
}

error_reporting(0);ob_start();
try{
    if(PHP_SAPI!=='cli')throw new RuntimeException('cli_only');$root=realpath(getcwd());if(!$root||basename($root)!=='anytoour.ru')throw new RuntimeException('root');
    $preview=$root.'/_preview/search3-anex-candidate';require_once $root.'/config.php';require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
    $app=is_file($preview.'/app/integrations/anex-search.php')?$preview.'/app/integrations':$root.'/app/integrations';require_once $app.'/anex-search.php';require_once $app.'/anex-search-mapping-registry.php';
    $tv=is_file($root.'/data/tourvisor-client-v1.php')?$root.'/data/tourvisor-client-v1.php':$root.'/v2/data/tourvisor-client-v1.php';require_once $tv;
    $private=$preview.'/.anex-private.php';if(is_file($private))require_once $private;
    if(!defined('TOURVISOR_ANEX_JWT'))throw new RuntimeException('tv_credential_missing');$tok=trim((string)TOURVISOR_ANEX_JWT);if(stripos($tok,'Bearer ')===0)$tok=trim(substr($tok,7));putenv('TOURVISOR_JWT='.$tok);
    $anexToken=trim((string)getenv('ANEX_API_TOKEN'));if($anexToken===''&&defined('ANEX_API_TOKEN'))$anexToken=trim((string)ANEX_API_TOKEN);if($anexToken==='')throw new RuntimeException('anex_credential_missing');
    $pdo=v2_data_db();$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $registry=AnyTourAnexSearchMappingRegistry::fromPdo($pdo);$resolve=$registry->previewResolver();
    $countries=$pdo->query("SELECT id,name FROM catalog_countries WHERE is_active=1")->fetchAll(PDO::FETCH_ASSOC);$countryNames=[];foreach($countries as $c)$countryNames[(int)$c['id']]=(string)$c['name'];
    $dec=[];foreach($pdo->query('SELECT anex_hotel_id FROM anex_hotel_decisions')->fetchAll(PDO::FETCH_COLUMN) as $id)$dec[(int)$id]=true;
    $obs=$pdo->query('SELECT anex_hotel_id,hotel_name,country_id,SUM(GREATEST(search_count,1)) occurrences,MAX(last_seen_utc) last_seen FROM anex_search_hotel_observations GROUP BY anex_hotel_id,hotel_name,country_id')->fetchAll(PDO::FETCH_ASSOC);
    $byCountry=[];$freq=[];$names=[];
    foreach($obs as $r){$aid=(int)$r['anex_hotel_id'];$cid=(int)$r['country_id'];if($aid<=0||$cid<=0||isset($dec[$aid])||$resolve('anex_online',(string)$aid)!==null)continue;$cn=mb_strtolower($countryNames[$cid]??'','UTF-8');if(!preg_match('/егип|egypt|турц|turkey|türkiye/u',$cn))continue;$byCountry[$cid][$aid]=true;$freq[$aid]=($freq[$aid]??0)+(int)$r['occurrences'];$n=trim((string)$r['hotel_name']);if($n!=='')$names[$aid][$n]=true;}
    $client=new AnyTourAnexClient($anexToken);$cache=[];$result=['status'=>'completed','operation_id'=>HMOE_OP,'database_writes'=>0,'mapping_writes'=>0,'booking_calls'=>0,'lead_calls'=>0,'price_arithmetic_modified'=>false,'dates'=>HMOE_DATES,'countries'=>[],'candidates'=>[],'unresolved_count'=>count($freq),'unresolved_occurrences'=>array_sum($freq)];
    foreach($byCountry as $cid=>$idsSet){$ids=array_keys($idsSet);$cname=$countryNames[$cid]??'';$country=['country_id'=>$cid,'country_name'=>$cname,'unresolved_ids'=>count($ids),'days'=>[]];
        foreach(HMOE_DATES as $date){$tvRun=hmoe_tv_search((int)$cid,$date);$tvIdx=hmoe_indexes($tvRun['hotels']);
            $core=['supplier_namespace'=>'anex_online','checkin_begin'=>$date,'checkin_end'=>$date,'nights_from'=>HMOE_NIGHTS,'nights_till'=>HMOE_NIGHTS,'adults'=>2,'children'=>0,'child_ages'=>[]];
            $depRows=$client->request('SearchTour_TOWNFROMS',[]);$depId=anytour_anex_search3_dictionary_id($depRows,['Москва','Moscow']);if($depId===null)throw new RuntimeException('anex_departure');$core['departure_id']=$depId;
            $stateRows=$client->request('SearchTour_STATES',['TOWNFROMINC'=>$depId]);$dstId=anytour_anex_search3_dictionary_id($stateRows,[$cname]);if($dstId===null)throw new RuntimeException('anex_destination');$core['destination_id']=$dstId;
            $dated=['TOWNFROMINC'=>$depId,'STATEINC'=>$dstId,'CHECKIN_BEG'=>str_replace('-','',$date),'CHECKIN_END'=>str_replace('-','',$date),'ADULT'=>2,'CHILD'=>0];$curRows=$client->request('SearchTour_CURRENCIES',$dated);$curId=anytour_anex_search3_dictionary_id($curRows,['RUB','RUR','Рубль','Рубли','Руб']);if($curId===null)throw new RuntimeException('anex_currency');$core['currency_id']=$curId;
            $an=[];$supplierCalls=3;foreach(array_chunk($ids,30) as $chunk){$c=$core;$c['hotel_ids']=array_map('strval',$chunk);$search=new AnyTourAnexSearch($client,$resolve);$rr=$search->search($c);$supplierCalls++;foreach($rr['offers'] as $o){$aid=(int)$o['hotel']['external_id'];if(!isset($idsSet[$aid]))continue;$row=['id'=>$aid,'name'=>$o['hotel']['name'],'aliases'=>hmoe_aliases($o['hotel']['name']),'offer'=>['price'=>$o['converted_price']['currency']==='RUB'?($o['converted_price']['amount']??null):($o['price']['currency']==='RUB'?($o['price']['amount']??null):null),'currency'=>'RUB','date'=>$o['checkin'],'nights'=>$o['nights'],'meal'=>$o['meal'],'room'=>$o['room'],'town'=>$o['hotel']['town']]];if(!isset($an[$aid])||((float)($row['offer']['price']??PHP_FLOAT_MAX)<(float)($an[$aid]['offer']['price']??PHP_FLOAT_MAX)))$an[$aid]=$row;}}
            $pairs=[];foreach($an as $aid=>$a){$hits=[];foreach($a['aliases'] as $alias)foreach($tvIdx[$alias]??[] as $h)$hits[$h['id']]=$h;if(count($hits)!==1)continue;$h=array_values($hits)[0];$delta=hmoe_price_delta($a['offer']['price']??null,$h['offer']['price']??null);$q=false;foreach($names[$aid]??[] as $n)if(hmoe_quarantine($n))$q=true;$pairs[]=['anex_hotel_id'=>$aid,'anex_name'=>$a['name'],'tourvisor_hotel_id'=>$h['id'],'tourvisor_name'=>$h['name'],'occurrences'=>$freq[$aid]??0,'quarantine'=>$q,'name_evidence'=>'unique_alias','anex_offer'=>$a['offer'],'tourvisor_offer'=>$h['offer'],'tourvisor_rank'=>$h['rank'],'price_relative_delta'=>$delta,'price_signal'=>$delta!==null&&$delta<=0.12?'close':'not_close_or_unknown'];}
            usort($pairs,static fn($a,$b)=>($b['occurrences']<=>$a['occurrences'])?:($a['anex_hotel_id']<=>$b['anex_hotel_id']));foreach($pairs as $p)$result['candidates'][$p['anex_hotel_id'].'|'.$p['tourvisor_hotel_id']]=$p;
            $country['days'][]=['date'=>$date,'tourvisor_search_id'=>$tvRun['search_id'],'tourvisor_hotels'=>count($tvRun['hotels']),'anex_hotels'=>count($an),'supplier_calls'=>$supplierCalls,'unique_alias_pairs'=>count($pairs)];
        }$result['countries'][]=$country;
    }
    $result['candidates']=array_values($result['candidates']);usort($result['candidates'],static fn($a,$b)=>($b['occurrences']<=>$a['occurrences'])?:($a['anex_hotel_id']<=>$b['anex_hotel_id']));
    $result['candidate_count']=count($result['candidates']);$result['safe_review_count']=count(array_filter($result['candidates'],static fn($r)=>!$r['quarantine']));$result['supplier_calls']=array_sum(array_map(static fn($c)=>array_sum(array_column($c['days'],'supplier_calls')),$result['countries']));$result['tourvisor_calls']=count($result['countries'])*count(HMOE_DATES);
    echo 'HMOE_JSON:'.json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
}catch(Throwable $e){echo 'HMOE_JSON:'.json_encode(['status'=>'failed','operation_id'=>HMOE_OP,'reason'=>preg_replace('/[^A-Za-z0-9_:. -]/','?',substr($e->getMessage(),0,180)),'database_writes'=>0,'mapping_writes'=>0,'booking_calls'=>0,'lead_calls'=>0],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;exit(2);}
