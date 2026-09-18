<?php
declare(strict_types=1);

const HMAC_OP='hotel-match-user-seen-anex-live-context-discovery-1971-20260918-v1';
const HMAC_MAX_CONTEXTS=45;
const HMAC_MAX_DETAIL_CALLS=500;
const HMAC_ROW_LIMIT=250000;
const HMAC_EXCLUDED_COUNTRIES=[46=>true,47=>true];

function hmac_rows(PDO $db,string $sql,array $p=[]):array{$s=$db->prepare($sql);$s->execute(array_values($p));$r=$s->fetchAll(PDO::FETCH_ASSOC)?:[];if(count($r)>HMAC_ROW_LIMIT)throw new RuntimeException('row_budget');return$r;}
function hmac_norm(string $v):string{$v=mb_strtolower(trim($v),'UTF-8');$v=strtr($v,['ё'=>'е','é'=>'e','è'=>'e','ê'=>'e','á'=>'a','à'=>'a','ä'=>'a','â'=>'a','ö'=>'o','ô'=>'o','ü'=>'u','ú'=>'u','ç'=>'c','ş'=>'s','ğ'=>'g','ı'=>'i','&'=>' ','+'=>' ','_'=>' ','-'=>' ']);$v=preg_replace('/[^\p{L}\p{N}]+/u',' ',$v)??$v;return trim(preg_replace('/\s+/u',' ',$v)??$v);}
function hmac_tokens(mixed $v):array{$v=(string)$v;if($v===''||!preg_match('/\p{L}/u',$v))return[];$drop=['hotel'=>1,'hotels'=>1,'otel'=>1,'отель'=>1,'гостиница'=>1,'resort'=>1,'resorts'=>1,'spa'=>1,'the'=>1,'and'=>1,'ex'=>1];$o=[];foreach(preg_split('/\s+/u',hmac_norm($v),-1,PREG_SPLIT_NO_EMPTY)?:[]as$t)if(!isset($drop[$t]))$o[(string)$t]=true;return array_map('strval',array_keys($o));}
function hmac_key(string $v):string{$t=hmac_tokens($v);sort($t,SORT_STRING);return implode(' ',$t);}
function hmac_nums(string $v):array{$o=[];foreach(hmac_tokens($v)as$t)if(preg_match('/^[0-9]+$/D',$t))$o[$t]=true;$k=array_keys($o);sort($k,SORT_STRING);return$k;}
function hmac_quals(string $v):array{$q=['family'=>1,'beach'=>1,'garden'=>1,'gardens'=>1,'aqua'=>1,'aquamarine'=>1,'club'=>1,'grand'=>1,'select'=>1,'adult'=>1,'adults'=>1,'north'=>1,'south'=>1,'boutique'=>1,'palace'=>1,'royal'=>1,'premium'=>1,'deluxe'=>1,'villas'=>1,'villa'=>1,'annex'=>1,'pool'=>1];$o=[];foreach(hmac_tokens($v)as$t)if(isset($q[$t]))$o[$t]=true;$k=array_keys($o);sort($k,SORT_STRING);return$k;}
function hmac_score(string $a,string $b):int{$ak=hmac_key($a);$bk=hmac_key($b);if($ak===''||$bk==='')return 0;if($ak===$bk)return 100;$aa=array_fill_keys(hmac_tokens($a),true);$bb=array_fill_keys(hmac_tokens($b),true);$c=count(array_intersect_key($aa,$bb));if($c===0)return 0;$dice=(2*$c)/(count($aa)+count($bb));$lev=0;$an=hmac_norm($a);$bn=hmac_norm($b);if(strlen($an)<240&&strlen($bn)<240){$m=max(strlen($an),strlen($bn));if($m>0)$lev=1-(levenshtein($an,$bn)/$m);}return(int)round(100*max($dice,max(0,$lev)));}
function hmac_country_key(string $v):string{$n=hmac_norm($v);$map=['turkey'=>'turkey','turkiye'=>'turkey','türkiye'=>'turkey','турция'=>'turkey','egypt'=>'egypt','египет'=>'egypt','thailand'=>'thailand','таиланд'=>'thailand','тайланд'=>'thailand','maldives'=>'maldives','мальдивы'=>'maldives','uae'=>'uae','оаэ'=>'uae','united arab emirates'=>'uae','объединенные арабские эмираты'=>'uae','vietnam'=>'vietnam','вьетнам'=>'vietnam','china'=>'china','китай'=>'china','india'=>'india','индия'=>'india','cuba'=>'cuba','куба'=>'cuba','qatar'=>'qatar','катар'=>'qatar','mauritius'=>'mauritius','маврикий'=>'mauritius','sri lanka'=>'srilanka','шри ланка'=>'srilanka','uzbekistan'=>'uzbekistan','узбекистан'=>'uzbekistan','tanzania'=>'tanzania','танзания'=>'tanzania','indonesia'=>'indonesia','индонезия'=>'indonesia','tunisia'=>'tunisia','тунис'=>'tunisia','morocco'=>'morocco','марокко'=>'morocco','seychelles'=>'seychelles','сейшелы'=>'seychelles'];return$map[$n]??$n;}
function hmac_aliases(string $v,array $groups):array{$n=hmac_norm($v);$o=[$n=>true];foreach($groups as$g){$ng=array_map('hmac_norm',$g);if(in_array($n,$ng,true))foreach($ng as$x)$o[$x]=true;}return array_keys($o);}
function hmac_country_aliases(string $v):array{return hmac_aliases($v,[['Турция','Turkey','Turkiye','Türkiye'],['Египет','Egypt'],['ОАЭ','UAE','United Arab Emirates','Объединенные Арабские Эмираты'],['Мальдивы','Maldives'],['Вьетнам','Vietnam'],['Таиланд','Thailand'],['Куба','Cuba'],['Шри-Ланка','Sri Lanka'],['Катар','Qatar'],['Китай','China'],['Маврикий','Mauritius'],['Индонезия','Indonesia'],['Тунис','Tunisia'],['Индия','India'],['Танзания','Tanzania'],['Узбекистан','Uzbekistan'],['Марокко','Morocco'],['Сейшелы','Seychelles']]);}
function hmac_departure_aliases(string $v):array{return hmac_aliases($v,[['Москва','Moscow'],['Санкт-Петербург','Санкт Петербург','Saint Petersburg','St Petersburg'],['Екатеринбург','Yekaterinburg','Ekaterinburg'],['Казань','Kazan'],['Новосибирск','Novosibirsk'],['Самара','Samara'],['Уфа','Ufa'],['Челябинск','Chelyabinsk'],['Нижний Новгород','Nizhny Novgorod'],['Минеральные Воды','Mineralnye Vody'],['Пермь','Perm'],['Тюмень','Tyumen'],['Омск','Omsk'],['Красноярск','Krasnoyarsk'],['Иркутск','Irkutsk']]);}
function hmac_dict_id(array $rows,array $aliases):?int{$want=array_fill_keys($aliases,true);$hits=[];foreach(array_slice($rows,0,10000)as$r){if(!is_array($r))continue;$id=(int)($r['id']??0);if($id<1)continue;foreach(['name','nameAlt','alias','currencyISO']as$k){$x=$r[$k]??null;if(is_string($x)&&isset($want[hmac_norm($x)]))$hits[$id]=true;}}return count($hits)===1?(int)array_key_first($hits):null;}
function hmac_product(string $n):bool{return preg_match('/^(?:fortuna|roulette|фортуна|рулетка)\b/ui',trim($n))===1;}
function hmac_dist($a,$b,$c,$d):?float{foreach([$a,$b,$c,$d]as$v)if(!is_numeric($v))return null;$lat1=deg2rad((float)$a);$lon1=deg2rad((float)$b);$lat2=deg2rad((float)$c);$lon2=deg2rad((float)$d);$x=sin(($lat2-$lat1)/2)**2+cos($lat1)*cos($lat2)*sin(($lon2-$lon1)/2)**2;return 6371000*2*asin(min(1,sqrt($x)));}
function hmac_place(array $detail,array $local):bool{$src=[];foreach(['region','town','address']as$k){$n=hmac_norm((string)($detail[$k]??''));if($n!=='')$src[]=$n;}$dst=[];foreach(['region_name','subregion_name']as$k){$n=hmac_norm((string)($local[$k]??''));if($n!=='')$dst[]=$n;}foreach($src as$a)foreach($dst as$b)if($a===$b||str_contains($a,$b)||str_contains($b,$a))return true;return false;}
function hmac_detail(array $p):array{return['id'=>(string)($p['id']??$p['hotelKey']??''),'name'=>(string)($p['name']??$p['hotel']??''),'country'=>(string)($p['state']??$p['country']??''),'region'=>(string)($p['region']??''),'town'=>(string)($p['town']??''),'address'=>(string)($p['address']??''),'latitude'=>$p['latitude']??null,'longitude'=>$p['longitude']??null,'townKey'=>(string)($p['townKey']??'')];}
function hmac_complete_saved(array $r):bool{return trim((string)($r['api_name']??''))!==''&&trim((string)($r['api_country']??''))!==''&&(is_numeric($r['latitude']??null)&&is_numeric($r['longitude']??null)||trim((string)($r['api_town']??''))!==''||trim((string)($r['api_region']??''))!=='');}
function hmac_saved_detail(array $r):array{return['id'=>(string)($r['anex_hotel_id']??''),'name'=>(string)($r['api_name']??$r['xml_name']??''),'country'=>(string)($r['api_country']??''),'region'=>(string)($r['api_region']??''),'town'=>(string)($r['api_town']??''),'address'=>'','latitude'=>$r['latitude']??null,'longitude'=>$r['longitude']??null,'townKey'=>''];}
function hmac_best(array $sourceNames,array $localNames):array{$best=['score'=>0,'source'=>'','target'=>''];foreach($sourceNames as$s)foreach($localNames as$t){if(hmac_nums($s)!==hmac_nums($t))continue;$sq=hmac_quals($s);$tq=hmac_quals($t);if(($sq||$tq)&&$sq!==$tq)continue;$score=hmac_score($s,$t);if($score>$best['score'])$best=['score'=>$score,'source'=>$s,'target'=>$t];}return$best;}

if(in_array('--self-test',$argv??[],true)){
  if(hmac_key('Porto Bello Hotel Resort & Spa')!=='bello porto')throw new RuntimeException('key');
  if(hmac_score('Ozkaymak Falez Hotel','OZKAYMAK FALEZ HOTEL')<95)throw new RuntimeException('score');
  if(hmac_dict_id([['id'=>1,'name'=>'Moscow'],['id'=>2,'name'=>'Other']],hmac_departure_aliases('Москва'))!==1)throw new RuntimeException('dict');
  if(!hmac_product('FORTUNA 5*')||hmac_product('Fortuna Hotel Phu Quoc'))throw new RuntimeException('product');
  $d=hmac_dist(0,0,0,0);if($d===null||$d>1)throw new RuntimeException('distance');
  echo"ANEX_LIVE_CONTEXT_DISCOVERY_SELFTEST_OK\n";exit;
}

$prep=getenv('HMAC_PREP_ONLY')==='1';
$root=realpath((string)getenv('ANYTOUR_ROOT'));$opdir=(string)getenv('MATCH_OPERATION_DIR');$token=$prep?'':trim((string)fgets(STDIN));
if(!$root||$opdir===''||(!$prep&&$token===''))throw new RuntimeException('runtime');
require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
$payload=is_file($opdir.'/payload/anex-client.php')?$opdir.'/payload':$opdir;
require_once $payload.'/anex-client.php';
$db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);

$build=function()use($db):array{
  $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
  try{
    $anexLocal=[];$mappedExt=[];$localOccupancy=[];foreach(hmac_rows($db,"SELECT anex_hotel_id,catalog_hotel_id FROM anex_hotel_search_mappings WHERE enabled=1")as$r){$a=(int)$r['anex_hotel_id'];$l=(int)$r['catalog_hotel_id'];if($a>0&&$l>0){$anexLocal[$l]=true;$mappedExt[$a]=$l;$localOccupancy[$l][$a]=true;}}
    $manual=[];$decisions=[];foreach(hmac_rows($db,"SELECT anex_hotel_id,catalog_hotel_id,decision_status FROM anex_hotel_decisions")as$r){$a=(int)$r['anex_hotel_id'];$manual[$a]=true;$decisions[$a][]=$r;if(($r['decision_status']??'')==='accepted'&&$r['catalog_hotel_id']!==null){$l=(int)$r['catalog_hotel_id'];$anexLocal[$l]=true;$localOccupancy[$l][$a]=true;}}
    $excluded=[];foreach(hmac_rows($db,"SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions")as$r)$excluded[(int)$r['anex_hotel_id']][(int)$r['catalog_hotel_id']]=true;
    $andrLocal=[];foreach(hmac_rows($db,"SELECT DISTINCT local_hotel_id FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IS NOT NULL")as$r)$andrLocal[(int)$r['local_hotel_id']]=true;
    $seen=[];foreach(hmac_rows($db,"SELECT hotel_id,COUNT(*) obs,MAX(observed_at) last_seen FROM tour_price_observations WHERE source='user_search' GROUP BY hotel_id")as$r)$seen[(int)$r['hotel_id']]=['obs'=>(int)$r['obs'],'last_seen'=>(string)$r['last_seen']];
    $locals=[];$frontier=[];$names=[];$tokenIndex=[];$countryLocals=[];
    foreach(hmac_rows($db,"SELECT id,country_id,country_name,name,region_name,subregion_name,latitude,longitude,is_active FROM catalog_hotels WHERE is_active=1")as$r){$id=(int)$r['id'];$cid=(int)$r['country_id'];$locals[$id]=$r;$names[$id][(string)$r['name']]=true;$countryLocals[$cid][$id]=true;if(isset($seen[$id])&&!isset(HMAC_EXCLUDED_COUNTRIES[$cid])&&!isset($anexLocal[$id])&&!isset($andrLocal[$id])&&!hmac_product((string)$r['name']))$frontier[$id]=$r+$seen[$id];}
    foreach(hmac_rows($db,"SELECT a.hotel_id,a.alias FROM hotel_aliases a JOIN catalog_hotels h ON h.id=a.hotel_id WHERE h.is_active=1")as$r){$id=(int)$r['hotel_id'];$v=trim((string)$r['alias']);if($v!==''&&isset($locals[$id]))$names[$id][$v]=true;}
    foreach($names as$id=>$set){$cid=(int)$locals[$id]['country_id'];foreach(array_keys($set)as$n)foreach(hmac_tokens((string)$n)as$t)$tokenIndex[$cid][$t][$id]=true;}
    $contextRows=[];
    if($frontier){$ids=array_keys($frontier);foreach(array_chunk($ids,700)as$chunk){$ph=implode(',',array_fill(0,count($chunk),'?'));$contextRows=array_merge($contextRows,hmac_rows($db,"SELECT departure_id,country_id,departure_date,nights,COUNT(*) obs,COUNT(DISTINCT hotel_id) hotels FROM tour_price_observations WHERE source='user_search' AND departure_date>=CURRENT_DATE AND adults=2 AND children_count=0 AND hotel_id IN ($ph) GROUP BY departure_id,country_id,departure_date,nights",array_values($chunk)));}}
    $deps=[];foreach(hmac_rows($db,"SELECT id,name FROM catalog_departures WHERE is_active=1")as$r)$deps[(int)$r['id']]=(string)$r['name'];
    $countries=[];foreach(hmac_rows($db,"SELECT id,name FROM catalog_countries WHERE is_active=1")as$r)$countries[(int)$r['id']]=(string)$r['name'];
    usort($contextRows,fn($a,$b)=>(int)$b['hotels']<=>(int)$a['hotels']?: (int)$b['obs']<=>(int)$a['obs']?:strcmp((string)$a['departure_date'],(string)$b['departure_date']));
    $contexts=[];$pairCount=[];$countryCount=[];
    foreach($contextRows as$r){$dep=(int)$r['departure_id'];$cid=(int)$r['country_id'];if(!isset($deps[$dep],$countries[$cid])||isset(HMAC_EXCLUDED_COUNTRIES[$cid]))continue;$pair=$dep.'|'.$cid;if(($pairCount[$pair]??0)>=3||($countryCount[$cid]??0)>=6)continue;$contexts[]=['departure_id'=>$dep,'departure_name'=>$deps[$dep],'country_id'=>$cid,'country_name'=>$countries[$cid],'date'=>(string)$r['departure_date'],'nights'=>(int)$r['nights'],'hotel_weight'=>(int)$r['hotels'],'observation_weight'=>(int)$r['obs']];$pairCount[$pair]=($pairCount[$pair]??0)+1;$countryCount[$cid]=($countryCount[$cid]??0)+1;if(count($contexts)>=HMAC_MAX_CONTEXTS)break;}
    $staging=[];foreach(hmac_rows($db,"SELECT * FROM anex_hotels")as$r)$staging[(int)$r['anex_hotel_id']]=$r;
    $db->rollBack();
    return compact('frontier','locals','names','tokenIndex','contexts','staging','manual','mappedExt','decisions','excluded','localOccupancy');
  }catch(Throwable$e){if($db->inTransaction())$db->rollBack();throw$e;}
};

$state=$build();
if($prep){echo json_encode(['operation'=>HMAC_OP,'state'=>'prepared_before_provider_access','frontier_count'=>count($state['frontier']),'context_count'=>count($state['contexts']),'staging_anex_count'=>count($state['staging']),'provider_attempts'=>0,'database_writes'=>0,'mapping_writes'=>0],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";exit(0);}

$attempts=0;$successCalls=0;$dictionaryCalls=0;$searchCalls=0;$detailAttempts=0;$detailCalls=0;$stop=null;$client=null;
$call=function(string $action,array $params)use(&$client,$token,&$attempts,&$successCalls,&$dictionaryCalls,&$searchCalls,&$detailAttempts,&$detailCalls,&$stop){
  if(!$client||$client->requestsMade()>=11)$client=new AnyTourAnexClient($token);
  $attempts++;if(str_starts_with($action,'SearchTour_')&&$action!=='SearchTour_PRICES')$dictionaryCalls++;elseif($action==='SearchTour_PRICES')$searchCalls++;elseif($action==='Hotels_DETAILS')$detailAttempts++;
  try{$v=$client->request($action,$params);$successCalls++;if($action==='Hotels_DETAILS')$detailCalls++;return$v;}
  catch(Throwable$e){$m=$e->getMessage();$diag=$client->lastRequestDiagnostics();if(in_array($m,['ANEX_HTTP_ERROR','ANEX_TRANSPORT_ERROR','ANEX_TRANSPORT_UNAVAILABLE'],true)||($diag['http_status']??null)===429){$stop=$m.(($diag['http_status']??null)===429?'_429':'');throw new RuntimeException('provider_stop');}throw$e;}
};
$dictCache=[];$dict=function(string $action,array $params)use(&$dictCache,$call):array{$k=$action.'|'.json_encode($params);if(isset($dictCache[$k]))return$dictCache[$k];$r=$call($action,$params);if(count($r)>10000)throw new RuntimeException('dict_size');return$dictCache[$k]=$r;};

$supplier=[];$contextResults=[];$townfrom=[];
try{
  $townfrom=$dict('SearchTour_TOWNFROMS',[]);
  foreach($state['contexts']as$ctx){
    if($stop!==null)break;
    $rec=$ctx+['status'=>'pending','provider_hotel_rows'=>0,'pages'=>0];
    try{
      $dep=hmac_dict_id($townfrom,hmac_departure_aliases($ctx['departure_name']));if(!$dep){$rec['status']='departure_unbound';$contextResults[]=$rec;continue;}
      $states=$dict('SearchTour_STATES',['TOWNFROMINC'=>$dep]);$dest=hmac_dict_id($states,hmac_country_aliases($ctx['country_name']));if(!$dest){$rec['status']='country_unbound';$contextResults[]=$rec;continue;}
      $date=str_replace('-','',$ctx['date']);$dated=['TOWNFROMINC'=>$dep,'STATEINC'=>$dest,'CHECKIN_BEG'=>$date,'CHECKIN_END'=>$date,'ADULT'=>2,'CHILD'=>0];
      $curr=$dict('SearchTour_CURRENCIES',$dated);$currency=hmac_dict_id($curr,array_map('hmac_norm',['RUB','RUR','Рубль','Рубли','Руб']));if(!$currency){$rec['status']='rub_unbound';$contextResults[]=$rec;continue;}
      $base=$dated+['CURRENCY'=>$currency,'NIGHTS_FROM'=>$ctx['nights'],'NIGHTS_TILL'=>$ctx['nights'],'FREIGHT'=>1,'FILTER'=>1,'PARTITION_PRICE'=>32,'SORT'=>'ASC','DYN_SEPARATE'=>1];
      for($page=1;$page<=2;$page++){
        $raw=$call('SearchTour_PRICES',$base+['PRICEPAGE'=>$page]);$prices=is_array($raw['prices']??null)?$raw['prices']:[];
        $rec['pages']++;$rec['provider_hotel_rows']+=count($prices);
        foreach(array_slice($prices,0,1000)as$row){if(!is_array($row))continue;$id=(int)($row['hotelKey']??0);$name=trim((string)($row['hotel']??''));if($id<1||$name===''||hmac_product($name))continue;$s=&$supplier[$ctx['country_id']][$id];if(!$s)$s=['external_id'=>$id,'country_id'=>$ctx['country_id'],'country_name'=>$ctx['country_name'],'names'=>[],'towns'=>[],'town_keys'=>[],'stars'=>[],'context_count'=>0,'offer_rows'=>0,'context_weight'=>0];$s['names'][$name]=true;$town=trim((string)($row['town']??''));if($town!=='')$s['towns'][$town]=true;$tk=(string)($row['townKey']??'');if(preg_match('/^[1-9][0-9]{0,12}$/D',$tk))$s['town_keys'][$tk]=true;$star=trim((string)($row['star']??''));if($star!=='')$s['stars'][$star]=true;$s['offer_rows']++;$s['context_weight']+=$ctx['observation_weight'];unset($s);}
        if(count($prices)<250)break;
      }
      foreach($supplier[$ctx['country_id']]??[]as&$s){/* context_count incremented below only for seen ids is expensive to track per page */}unset($s);
      $rec['status']='searched';
    }catch(Throwable$e){if($e->getMessage()==='provider_stop'){$rec['status']='provider_stop';$contextResults[]=$rec;break;}$rec['status']='context_error';$rec['reason']=preg_replace('/[^A-Z0-9_\-]/i','_',mb_substr($e->getMessage(),0,80));}
    $contextResults[]=$rec;
  }
}catch(Throwable$e){if($e->getMessage()!=='provider_stop')throw$e;}

foreach($supplier as$cid=>&$rows)foreach($rows as&$s){$s['names']=array_keys($s['names']);$s['towns']=array_keys($s['towns']);$s['town_keys']=array_keys($s['town_keys']);$s['stars']=array_keys($s['stars']);}unset($s,$rows);

$preCandidates=[];$reasonCounts=[];
foreach($supplier as$cid=>$rows){
  foreach($rows as$ext=>$s){
    if(isset($state['manual'][$ext])||isset($state['mappedExt'][$ext])){$reasonCounts['external_protected']=($reasonCounts['external_protected']??0)+1;continue;}
    $cand=[];$pool=[];
    foreach($s['names']as$sn)foreach(hmac_tokens($sn)as$t)foreach(array_keys($state['tokenIndex'][$cid][$t]??[])as$lid)$pool[$lid]=true;
    foreach(array_keys($pool)as$lid){$best=hmac_best($s['names'],array_keys($state['names'][$lid]??[]));if($best['score']<60)continue;$cand[]=['local_id'=>(int)$lid]+$best;}
    usort($cand,fn($a,$b)=>$b['score']<=>$a['score']?:$a['local_id']<=>$b['local_id']);
    if(!$cand){$reasonCounts['no_local_name_candidate']=($reasonCounts['no_local_name_candidate']??0)+1;continue;}
    $best=$cand[0];$second=$cand[1]??null;$margin=$best['score']-(int)($second['score']??0);$lid=(int)$best['local_id'];
    if(!isset($state['frontier'][$lid])){$reasonCounts['best_target_not_frontier']=($reasonCounts['best_target_not_frontier']??0)+1;continue;}
    if($best['score']<72||$margin<8){$reasonCounts['weak_or_ambiguous_name_rank']=($reasonCounts['weak_or_ambiguous_name_rank']??0)+1;continue;}
    if(isset($state['excluded'][$ext][$lid])){$reasonCounts['pair_excluded']=($reasonCounts['pair_excluded']??0)+1;continue;}
    $others=array_filter(array_keys($state['localOccupancy'][$lid]??[]),fn($x)=>(int)$x!==$ext);if($others){$reasonCounts['local_occupied_by_other_anex']=($reasonCounts['local_occupied_by_other_anex']??0)+1;continue;}
    $preCandidates[]=['external_id'=>(int)$ext,'local_id'=>$lid,'rank_score'=>$best['score'],'rank_margin'=>$margin,'supplier_names'=>$s['names'],'supplier_towns'=>$s['towns'],'offer_rows'=>$s['offer_rows'],'context_weight'=>$s['context_weight']];
  }
}
usort($preCandidates,fn($a,$b)=>$b['context_weight']<=>$a['context_weight']?:$b['rank_score']<=>$a['rank_score']?:$a['local_id']<=>$b['local_id']);
$details=[];$detailSource=[];$detailQueue=[];
foreach($preCandidates as$c){$ext=$c['external_id'];$saved=$state['staging'][$ext]??null;if(is_array($saved)&&hmac_complete_saved($saved)){$details[$ext]=hmac_saved_detail($saved);$detailSource[$ext]='saved_anex_hotels';}else$detailQueue[$ext]=true;}
$detailIds=array_slice(array_keys($detailQueue),0,HMAC_MAX_DETAIL_CALLS);
foreach($detailIds as$ext){if($stop!==null)break;try{$raw=$call('Hotels_DETAILS',['HOTELINC'=>(int)$ext]);$details[$ext]=hmac_detail($raw);$detailSource[$ext]='live_hotels_details';}catch(Throwable$e){if($e->getMessage()==='provider_stop')break;$details[$ext]=['error'=>preg_replace('/[^A-Z0-9_\-]/i','_',mb_substr($e->getMessage(),0,80))];$detailSource[$ext]='error';}}

$provisional=[];$holds=[];$extToLocals=[];$localToExt=[];
foreach($preCandidates as$c){$ext=$c['external_id'];$lid=$c['local_id'];$local=$state['frontier'][$lid];$d=$details[$ext]??null;if(!is_array($d)||isset($d['error'])){$holds[]=$c+['reason'=>'detail_unavailable'];continue;}
  if(hmac_country_key((string)($d['country']??''))!==hmac_country_key((string)$local['country_name'])){$holds[]=$c+['reason'=>'detail_country_conflict','detail_source'=>$detailSource[$ext]??null];continue;}
  $localNames=array_keys($state['names'][$lid]??[(string)$local['name']=>true]);$nb=hmac_best([(string)($d['name']??'')],$localNames);if($nb['score']<88){$holds[]=$c+['reason'=>'detail_name_insufficient','detail_score'=>$nb['score'],'detail_source'=>$detailSource[$ext]??null];continue;}
  $dist=hmac_dist($d['latitude']??null,$d['longitude']??null,$local['latitude']??null,$local['longitude']??null);if($dist!==null&&$dist>5000){$holds[]=$c+['reason'=>'coordinate_conflict_gt_5km','distance_m'=>(int)round($dist),'detail_source'=>$detailSource[$ext]??null];continue;}
  $place=hmac_place($d,$local);$exact=hmac_key((string)$d['name'])===hmac_key((string)$local['name']);$geo=($dist!==null&&$dist<=1500)||($dist===null&&$place)||($dist!==null&&$place);
  if(!$geo){$holds[]=$c+['reason'=>'geography_unproven','distance_m'=>$dist===null?null:(int)round($dist),'place_match'=>$place,'detail_source'=>$detailSource[$ext]??null];continue;}
  $row=$c+['detail_name'=>(string)$d['name'],'detail_region'=>(string)$d['region'],'detail_town'=>(string)$d['town'],'detail_source'=>$detailSource[$ext]??null,'detail_score'=>$nb['score'],'exact_name'=>$exact,'distance_m'=>$dist===null?null:(int)round($dist),'place_match'=>$place,'user_observations'=>(int)$local['obs']];
  $provisional[]=$row;$extToLocals[$ext][$lid]=true;$localToExt[$lid][$ext]=true;
}
$prepared=[];foreach($provisional as$r){if(count($extToLocals[$r['external_id']]??[])!==1){$holds[]=$r+['reason'=>'external_multiple_locals'];continue;}if(count($localToExt[$r['local_id']]??[])!==1){$holds[]=$r+['reason'=>'local_multiple_externals'];continue;}$prepared[]=$r;}
usort($prepared,fn($a,$b)=>$b['user_observations']<=>$a['user_observations']?:$b['detail_score']<=>$a['detail_score']?:$a['local_id']<=>$b['local_id']);
$holdCounts=$reasonCounts;foreach($holds as$h)$holdCounts[$h['reason']]=($holdCounts[$h['reason']]??0)+1;ksort($holdCounts);
$ctxCounts=[];foreach($contextResults as$r)$ctxCounts[$r['status']]=($ctxCounts[$r['status']]??0)+1;ksort($ctxCounts);
$supplierUnique=0;foreach($supplier as$rows)$supplierUnique+=count($rows);
$result=['operation'=>HMAC_OP,'state'=>$stop===null?'completed_read_only':'completed_partial_provider_stop','stop_reason'=>$stop,'frontier_count'=>count($state['frontier']),'planned_context_count'=>count($state['contexts']),'context_status_counts'=>$ctxCounts,'supplier_unique_hotels'=>$supplierUnique,'pre_candidate_count'=>count($preCandidates),'saved_detail_reused'=>count(array_filter($detailSource,fn($x)=>$x==='saved_anex_hotels')),'live_detail_queue'=>count($detailIds),'provider_attempts'=>$attempts,'provider_success_calls'=>$successCalls,'dictionary_calls'=>$dictionaryCalls,'search_calls'=>$searchCalls,'detail_attempts'=>$detailAttempts,'detail_calls'=>$detailCalls,'prepared_count'=>count($prepared),'prepared'=>$prepared,'hold_count'=>count($holds),'hold_reason_counts'=>$holdCounts,'hold_sample'=>array_slice($holds,0,150),'context_results'=>$contextResults,'database_writes'=>0,'mapping_writes'=>0,'tourvisor_calls'=>0,'andromeda_calls'=>0,'guards'=>['shared_default_token_rate_lock'=>true,'supplier_limit_per_minute'=>60,'hard_coordinate_block_m'=>5000,'detail_required_for_prepared'=>true,'full_country_competition'=>true,'one_to_one'=>true,'no_mapping_write'=>true]];
echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
