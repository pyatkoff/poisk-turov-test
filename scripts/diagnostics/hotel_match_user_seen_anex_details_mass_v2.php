<?php
declare(strict_types=1);

const HMA_OP='hotel-match-user-seen-anex-details-mass-1971-20260918-v2';
const HMA_MAX_REQUESTS=2200;

function hma_norm(string $v): string {
    $v=mb_strtolower(trim($v),'UTF-8');
    $v=strtr($v,['ё'=>'е','é'=>'e','è'=>'e','ê'=>'e','á'=>'a','à'=>'a','ä'=>'a','â'=>'a','ö'=>'o','ô'=>'o','ü'=>'u','ú'=>'u','ç'=>'c','ş'=>'s','ğ'=>'g','ı'=>'i','&'=>' ','+'=>' ']);
    $v=preg_replace('/[^\p{L}\p{N}]+/u',' ',$v)??$v;
    return trim(preg_replace('/\s+/u',' ',$v)??$v);
}
function hma_tokens(string $v): array {
    $drop=['hotel'=>1,'hotels'=>1,'otel'=>1,'отель'=>1,'гостиница'=>1,'resort'=>1,'resorts'=>1,'spa'=>1,'the'=>1,'and'=>1,'ex'=>1];
    $out=[];foreach(preg_split('/\s+/u',hma_norm($v),-1,PREG_SPLIT_NO_EMPTY)?:[] as $t)if(!isset($drop[$t]))$out[$t]=true;
    return array_keys($out);
}
function hma_key(string $v): string {$t=hma_tokens($v);sort($t,SORT_STRING);return implode(' ',$t);}
function hma_nums(string $v): array {$o=[];foreach(hma_tokens($v) as $t)if(preg_match('/^[0-9]+$/D',$t))$o[$t]=true;$k=array_keys($o);sort($k);return$k;}
function hma_quals(string $v): array {
    $q=['family'=>1,'beach'=>1,'garden'=>1,'gardens'=>1,'aqua'=>1,'aquamarine'=>1,'club'=>1,'grand'=>1,'select'=>1,'adult'=>1,'adults'=>1,'north'=>1,'south'=>1,'boutique'=>1,'palace'=>1,'royal'=>1,'premium'=>1,'deluxe'=>1];
    $o=[];foreach(hma_tokens($v) as $t)if(isset($q[$t]))$o[$t]=true;$k=array_keys($o);sort($k);return$k;
}
function hma_score(string $a,string $b): int {
    $ak=hma_key($a);$bk=hma_key($b);if($ak===''||$bk==='')return 0;if($ak===$bk)return 100;
    $aa=array_fill_keys(hma_tokens($a),true);$bb=array_fill_keys(hma_tokens($b),true);$c=count(array_intersect_key($aa,$bb));
    if($c===0)return 0;$dice=(2*$c)/(count($aa)+count($bb));
    $lev=0;$an=hma_norm($a);$bn=hma_norm($b);
    if(strlen($an)<240&&strlen($bn)<240){$m=max(strlen($an),strlen($bn));if($m>0)$lev=1-(levenshtein($an,$bn)/$m);}
    return (int)round(100*max($dice,max(0,$lev)));
}
function hma_country_key(string $v): string {
    $n=hma_norm($v);
    $map=['turkey'=>'turkey','turkiye'=>'turkey','türkiye'=>'turkey','турция'=>'turkey',
      'egypt'=>'egypt','египет'=>'egypt','thailand'=>'thailand','таиланд'=>'thailand','тайланд'=>'thailand',
      'maldives'=>'maldives','мальдивы'=>'maldives','uae'=>'uae','оаэ'=>'uae','united arab emirates'=>'uae',
      'vietnam'=>'vietnam','вьетнам'=>'vietnam','china'=>'china','китай'=>'china','india'=>'india','индия'=>'india',
      'cuba'=>'cuba','куба'=>'cuba','qatar'=>'qatar','катар'=>'qatar','mauritius'=>'mauritius','маврикий'=>'mauritius',
      'sri lanka'=>'srilanka','шри ланка'=>'srilanka','uzbekistan'=>'uzbekistan','узбекистан'=>'uzbekistan',
      'tanzania'=>'tanzania','танзания'=>'tanzania','indonesia'=>'indonesia','индонезия'=>'indonesia'];
    return $map[$n]??$n;
}
function hma_rows(PDO $db,string $sql,array $p=[]): array{$s=$db->prepare($sql);$s->execute(array_values($p));return$s->fetchAll(PDO::FETCH_ASSOC)?:[];}
function hma_dist($a,$b,$c,$d): ?float{
    foreach([$a,$b,$c,$d] as $v)if(!is_numeric($v))return null;
    $lat1=deg2rad((float)$a);$lon1=deg2rad((float)$b);$lat2=deg2rad((float)$c);$lon2=deg2rad((float)$d);
    $x=sin(($lat2-$lat1)/2)**2+cos($lat1)*cos($lat2)*sin(($lon2-$lon1)/2)**2;
    return 6371000*2*asin(min(1,sqrt($x)));
}
function hma_place_match(array $detail,array $local): bool {
    $src=[];foreach(['region','town','address'] as $k){$n=hma_norm((string)($detail[$k]??''));if($n!=='')$src[]=$n;}
    $dst=[];foreach(['region_name','subregion_name'] as $k){$n=hma_norm((string)($local[$k]??''));if($n!=='')$dst[]=$n;}
    foreach($src as $a)foreach($dst as $b)if($a===$b||str_contains($a,$b)||str_contains($b,$a))return true;return false;
}
function hma_quota_reserve(string $home): int {
    $dir=rtrim($home,'/').'/.anytour-match/provider-quotas';if(!is_dir($dir)&&!mkdir($dir,0700,true)&&!is_dir($dir))throw new RuntimeException('quota_dir');
    $day=gmdate('Y-m-d');$p="$dir/anex-$day.json";$f=fopen($p,'c+');if(!$f)throw new RuntimeException('quota_open');
    try{if(!flock($f,LOCK_EX))throw new RuntimeException('quota_lock');$raw=stream_get_contents($f);$j=$raw!==''?json_decode($raw,true):[];
      $used=(int)($j['match_reserved']??0);if($used>=3000)throw new RuntimeException('anex_daily_match_quota_reached');
      $used++;$j=['day'=>$day,'match_reserved'=>$used,'owner_daily_limit'=>3000,'updated_at'=>gmdate('c')];
      ftruncate($f,0);rewind($f);fwrite($f,json_encode($j,JSON_UNESCAPED_SLASHES));fflush($f);if(function_exists('fsync'))fsync($f);return$used;
    }finally{flock($f,LOCK_UN);fclose($f);}
}
function hma_detail(array $p): array {
    $out=['id'=>(string)($p['id']??$p['hotelKey']??''),'name'=>(string)($p['name']??$p['hotel']??''),'country'=>(string)($p['state']??$p['country']??''),
      'region'=>(string)($p['region']??''),'town'=>(string)($p['town']??''),'address'=>(string)($p['address']??''),
      'latitude'=>$p['latitude']??null,'longitude'=>$p['longitude']??null,'townKey'=>(string)($p['townKey']??'')];
    return$out;
}
if(in_array('--self-test',$argv??[],true)){if(hma_key('Porto Bello Hotel Resort & Spa')!=='bello porto')throw new RuntimeException('key');if(hma_score('Ozkaymak Falez Hotel','OZKAYMAK FALEZ HOTEL')<95)throw new RuntimeException('score');echo"ANEX_MASS_DETAILS_SELFTEST_OK\n";exit;}

$prepOnly=getenv('HMA_PREP_ONLY')==='1';
$root=realpath((string)getenv('ANYTOUR_ROOT'));$opdir=(string)getenv('MATCH_OPERATION_DIR');$token=$prepOnly?'':trim((string)fgets(STDIN));
if(!$root||$opdir===''||(!$prepOnly&&$token===''))throw new RuntimeException('runtime');
require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
require_once $opdir.'/payload/anex-client.php';
require_once $opdir.'/payload/anex-search-mapping-registry.php';
$db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
try{
  $registry=AnyTourAnexSearchMappingRegistry::fromPdo($db);$anexTargets=[];$ids=hma_rows($db,'SELECT anex_hotel_id FROM anex_hotel_search_mappings UNION SELECT anex_hotel_id FROM anex_hotel_decisions');
  foreach($ids as $r){$t=$registry->resolve('anex_online',(string)$r['anex_hotel_id'],'preview');if(is_int($t)&&$t>0)$anexTargets[$t]=true;}
  $andrTargets=[];foreach(hma_rows($db,"SELECT DISTINCT local_hotel_id FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IS NOT NULL")as$r)$andrTargets[(int)$r['local_hotel_id']]=true;
  $obs=hma_rows($db,"SELECT o.hotel_id,COUNT(*) obs,MAX(o.observed_at) last_seen FROM tour_price_observations o WHERE o.source='user_search' GROUP BY o.hotel_id");
  $seen=[];foreach($obs as$r)$seen[(int)$r['hotel_id']]=['obs'=>(int)$r['obs'],'last_seen'=>(string)$r['last_seen']];
  $locals=[];$frontier=[];foreach(hma_rows($db,'SELECT id,country_id,country_name,name,region_name,subregion_name,latitude,longitude,is_active FROM catalog_hotels WHERE is_active=1')as$r){
    $id=(int)$r['id'];$locals[$id]=$r;if(isset($seen[$id])&&!isset($anexTargets[$id])&&!isset($andrTargets[$id])&&!preg_match('/^(?:fortuna|roulette|фортуна|рулетка)\b/ui',trim((string)$r['name'])))$frontier[$id]=$r+$seen[$id];
  }
  $aliases=[];foreach(hma_rows($db,'SELECT hotel_id,alias FROM hotel_aliases')as$r)$aliases[(int)$r['hotel_id']][]=(string)$r['alias'];
  $manual=[];foreach(hma_rows($db,'SELECT anex_hotel_id FROM anex_hotel_decisions')as$r)$manual[(int)$r['anex_hotel_id']]=true;
  $mapped=[];$occupied=[];foreach(hma_rows($db,"SELECT anex_hotel_id,catalog_hotel_id FROM anex_hotel_search_mappings WHERE enabled=1 AND scope='preview'")as$r){$mapped[(int)$r['anex_hotel_id']]=true;$occupied[(int)$r['catalog_hotel_id']][(int)$r['anex_hotel_id']]=true;}
  $excluded=[];foreach(hma_rows($db,'SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions')as$r)$excluded[(int)$r['anex_hotel_id']][(int)$r['catalog_hotel_id']]=true;
  $sources=[];
  foreach(hma_rows($db,'SELECT * FROM anex_hotels')as$r){
    $aid=(int)($r['anex_hotel_id']??0);if($aid<1||isset($manual[$aid])||isset($mapped[$aid]))continue;
    $names=[];foreach(['api_name','xml_name','xml_alternate_name']as$k){$v=trim((string)($r[$k]??''));if($v!=='')$names[$v]=true;}
    if(!$names)continue;$ck=hma_country_key((string)($r['api_country']??''));if($ck==='')continue;
    $sources[$aid]=['id'=>$aid,'names'=>array_keys($names),'country_key'=>$ck,'region'=>(string)($r['api_region']??''),'town'=>(string)($r['api_town']??''),'latitude'=>$r['latitude']??$r['api_latitude']??null,'longitude'=>$r['longitude']??$r['api_longitude']??null];
  }
  foreach(hma_rows($db,'SELECT * FROM anex_search_hotel_observations')as$r){$aid=(int)($r['anex_hotel_id']??0);if(!$aid||!isset($sources[$aid]))continue;$v=trim((string)($r['hotel_name']??''));if($v!==''&&!in_array($v,$sources[$aid]['names'],true))$sources[$aid]['names'][]=$v;}
  $db->rollBack();

  $byCountry=[];$tokenIndex=[];foreach($sources as$aid=>$s){$byCountry[$s['country_key']][$aid]=true;foreach($s['names']as$n)foreach(hma_tokens($n)as$t)$tokenIndex[$s['country_key']][$t][$aid]=true;}
  $plans=[];$candidateToLocals=[];$frontierRows=array_values($frontier);usort($frontierRows,fn($a,$b)=>$b['obs']<=>$a['obs']?:strcmp($b['last_seen'],$a['last_seen']));
  foreach($frontierRows as$l){$lid=(int)$l['id'];$ck=hma_country_key((string)$l['country_name']);$names=array_merge([(string)$l['name']],$aliases[$lid]??[]);
    $pool=[];foreach($names as$n)foreach(hma_tokens($n)as$t)foreach(array_keys($tokenIndex[$ck][$t]??[])as$aid)$pool[$aid]=true;
    $rank=[];foreach(array_keys($pool)as$aid){$s=$sources[$aid];$best=0;$bestNames=['',''];foreach($names as$ln)foreach($s['names']as$sn){if(hma_nums($ln)!==hma_nums($sn))continue;$lq=hma_quals($ln);$sq=hma_quals($sn);if($lq&&$sq&&$lq!==$sq)continue;$sc=hma_score($ln,$sn);if($sc>$best){$best=$sc;$bestNames=[$ln,$sn];}}
      if($best>=65)$rank[]=['aid'=>$aid,'score'=>$best,'local_name'=>$bestNames[0],'source_name'=>$bestNames[1]];}
    usort($rank,fn($a,$b)=>$b['score']<=>$a['score']?:$a['aid']<=>$b['aid']);$rank=array_slice($rank,0,3);
    if($rank){$plans[$lid]=['local'=>$l,'rank'=>$rank];foreach($rank as$r)$candidateToLocals[$r['aid']][$lid]=true;}
  }
  $candidateIds=array_keys($candidateToLocals);usort($candidateIds,function($a,$b)use($candidateToLocals,$seen){$ma=max(array_map(fn($lid)=>$seen[$lid]['obs']??0,array_keys($candidateToLocals[$a])));$mb=max(array_map(fn($lid)=>$seen[$lid]['obs']??0,array_keys($candidateToLocals[$b])));return$mb<=>$ma?:$a<=>$b;});
  $candidateIds=array_slice($candidateIds,0,HMA_MAX_REQUESTS);
  if($prepOnly){
    echo json_encode(['operation'=>HMA_OP,'state'=>'prepared_before_provider_access','frontier_count'=>count($frontier),'planned_local_count'=>count($plans),'unique_anex_candidates'=>count($candidateIds),'database_writes'=>0,'mapping_writes'=>0,'provider_calls'=>0],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
    exit(0);
  }
  $details=[];$calls=0;$stop=null;$home=(string)getenv('HOME');$rateDir=rtrim($home,'/').'/.anytour-anex-rate';
  foreach($candidateIds as$i=>$aid){try{hma_quota_reserve($home);if($i%12===0)$client=new AnyTourAnexClient($token,null,$rateDir);$p=$client->request('Hotels_DETAILS',['HOTELINC'=>$aid]);$calls++;if($p)$details[$aid]=hma_detail($p);}catch(Throwable$e){$m=$e->getMessage();if($m==='ANEX_HTTP_ERROR'||$m==='ANEX_TRANSPORT_ERROR'||$m==='anex_daily_match_quota_reached'){$stop=$m;break;}$details[$aid]=['error'=>$m];}}

  $safe=[];$ambiguous=[];$holds=[];foreach($plans as$lid=>$plan){$local=$plan['local'];$oks=[];foreach($plan['rank']as$rr){$aid=$rr['aid'];if(!isset($details[$aid])||isset($details[$aid]['error']))continue;$d=$details[$aid];$countryOk=hma_country_key((string)($d['country']??''))===hma_country_key((string)$local['country_name']);if(!$countryOk)continue;
      $nameScore=max($rr['score'],hma_score((string)$local['name'],(string)$d['name']));$dist=hma_dist($d['latitude']??null,$d['longitude']??null,$local['latitude']??null,$local['longitude']??null);$place=hma_place_match($d,$local);
      $geo=($dist!==null&&$dist<=1500)||$place;$exact=hma_key((string)$local['name'])===hma_key((string)$d['name']);
      if(($exact||$nameScore>=85)&&$geo&&!isset($excluded[$aid][$lid]))$oks[]=['anex_hotel_id'=>$aid,'local_hotel_id'=>$lid,'name_score'=>$nameScore,'exact_name'=>$exact,'distance_m'=>$dist===null?null:(int)round($dist),'place_match'=>$place,'supplier_name'=>$d['name'],'supplier_town'=>$d['town'],'supplier_region'=>$d['region'],'user_observations'=>$plan['local']['obs']];
    }
    if(count($oks)===1)$safe[]=$oks[0];elseif(count($oks)>1)$ambiguous[]=['local_hotel_id'=>$lid,'candidates'=>$oks];else$holds[]=['local_hotel_id'=>$lid,'reason'=>'no_unique_direct_detail_candidate'];
  }
  echo json_encode(['operation'=>HMA_OP,'state'=>'completed_read_only','frontier_count'=>count($frontier),'planned_local_count'=>count($plans),'unique_anex_candidates'=>count($candidateIds),'anex_detail_calls'=>$calls,'stop_reason'=>$stop,'details_returned'=>count(array_filter($details,fn($x)=>!isset($x['error']))),'safe_candidate_count'=>count($safe),'ambiguous_local_count'=>count($ambiguous),'safe_candidates'=>$safe,'ambiguous'=>$ambiguous,'hold_count'=>count($holds),'database_writes'=>0,'mapping_writes'=>0,'tourvisor_calls'=>0,'andromeda_calls'=>0],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
}catch(Throwable$e){if($db->inTransaction())$db->rollBack();throw$e;}
