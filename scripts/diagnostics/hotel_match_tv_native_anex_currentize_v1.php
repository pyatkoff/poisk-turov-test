<?php
declare(strict_types=1);

const HMTN_OP='hotel-match-tv-native-anex-currentize-1971-20260918-v1';
const HMTN_SOURCE_SHA='06f1ad32e17e72bd5b2c1ad4fce69d3d765ea455926378710dfe8fc63954930b';
const HMTN_SOURCE_ARTIFACT=10559558054;
const HMTN_MAX_CALLS=40;

function hmtn_rows(PDO $db,string $sql,array $p=[]):array{$s=$db->prepare($sql);$s->execute(array_values($p));return$s->fetchAll(PDO::FETCH_ASSOC)?:[];}
function hmtn_norm(string $v):string{$v=mb_strtolower(trim($v),'UTF-8');$v=strtr($v,['ё'=>'е','Ё'=>'е','&'=>' ','+'=>' ','_'=>' ','-'=>' ','('=>' ',')'=>' ',';'=>' ',','=>' ','.'=>' ']);$v=preg_replace('/[^\p{L}\p{N}]+/u',' ',$v)??$v;return trim(preg_replace('/\s+/u',' ',$v)??$v);}
function hmtn_name_keys(string $v):array{
  $out=[];$n=hmtn_norm($v);if($n!=='')$out[$n]=true;
  $base=preg_replace('/\s*\((?:ex\.?|ex\s)[^)]*\)\s*$/ui','',$v)??$v;$b=hmtn_norm($base);if($b!=='')$out[$b]=true;
  $base2=preg_replace('/\s*\([^)]*\)\s*$/u','',$v)??$v;$b2=hmtn_norm($base2);if($b2!=='')$out[$b2]=true;
  return array_keys($out);
}
function hmtn_country_key(string $v):string{$n=hmtn_norm($v);$m=['турция'=>'turkey','turkey'=>'turkey','turkiye'=>'turkey','türkiye'=>'turkey','египет'=>'egypt','egypt'=>'egypt','танзания'=>'tanzania','tanzania'=>'tanzania','таиланд'=>'thailand','тайланд'=>'thailand','thailand'=>'thailand','вьетнам'=>'vietnam','vietnam'=>'vietnam','катар'=>'qatar','qatar'=>'qatar','индия'=>'india','india'=>'india'];return$m[$n]??$n;}
function hmtn_dist($a,$b,$c,$d):?float{foreach([$a,$b,$c,$d]as$v)if(!is_numeric($v))return null;$lat1=deg2rad((float)$a);$lon1=deg2rad((float)$b);$lat2=deg2rad((float)$c);$lon2=deg2rad((float)$d);$x=sin(($lat2-$lat1)/2)**2+cos($lat1)*cos($lat2)*sin(($lon2-$lon1)/2)**2;return 6371000*2*asin(min(1,sqrt($x)));}
function hmtn_place(array $detail,array $local):bool{$src=[];foreach(['state','region','town','address']as$k){$n=hmtn_norm((string)($detail[$k]??''));if($n!=='')$src[]=$n;}$dst=[];foreach(['region_name','subregion_name']as$k){$n=hmtn_norm((string)($local[$k]??''));if($n!=='')$dst[]=$n;}foreach($src as$a)foreach($dst as$b)if($a===$b||str_contains($a,$b)||str_contains($b,$a))return true;return false;}
function hmtn_detail(array $p):array{return[
  'name'=>(string)($p['name']??$p['hotel']??''),
  'country'=>(string)($p['country']??''),
  'state'=>(string)($p['state']??''),
  'region'=>(string)($p['region']??''),
  'town'=>(string)($p['town']??''),
  'address'=>(string)($p['address']??''),
  'latitude'=>$p['latitude']??null,'longitude'=>$p['longitude']??null,
];}
function hmtn_input():array{return json_decode(<<<'JSON'
[{"local_hotel_id":1244,"local_hotel_name":"HOTEL SU (EX. SU & AQUALAND; HILLSIDE SU)","country_id":4,"country_name":"Турция","region_name":"Анталья","subregion_name":"Анталия-центр","native_anex_hotel_id":"15072","current_user_search_rows":358,"tour_ids":["13275846385694","13278710940852"]},{"local_hotel_id":97122,"local_hotel_name":"AMARINA JANNAH RESORT & AQUAPARK","country_id":1,"country_name":"Египет","region_name":"Марса Алам","subregion_name":null,"native_anex_hotel_id":"37048","current_user_search_rows":212,"tour_ids":["13276337558972"]},{"local_hotel_id":56249,"local_hotel_name":"ROYAL MANDARIN HOTEL & RESORT (EX. CORAL REEF RESORT)","country_id":41,"country_name":"Танзания","region_name":"Занзибар","subregion_name":null,"native_anex_hotel_id":"41392","current_user_search_rows":210,"tour_ids":["13273598306321","13273619657800"]},{"local_hotel_id":74218,"local_hotel_name":"MARINA HOUSE MUAYTHAI TAEIAD","country_id":2,"country_name":"Таиланд","region_name":"Пхукет","subregion_name":null,"native_anex_hotel_id":"34652","current_user_search_rows":149,"tour_ids":["13273323653959"]},{"local_hotel_id":46770,"local_hotel_name":"ROSAKA","country_id":16,"country_name":"Вьетнам","region_name":"Нячанг","subregion_name":"Нячанг - центр","native_anex_hotel_id":"12007","current_user_search_rows":107,"tour_ids":["13280767144841"]},{"local_hotel_id":65896,"local_hotel_name":"SOCHI HOTEL NHA TRANG","country_id":16,"country_name":"Вьетнам","region_name":"Нячанг","subregion_name":"Нячанг - центр","native_anex_hotel_id":"20493","current_user_search_rows":80,"tour_ids":["13277783276587"]},{"local_hotel_id":367,"local_hotel_name":"SWISSOTEL RESORT EL QUSIER (EX. RADISSON BLU QUSIER)","country_id":1,"country_name":"Египет","region_name":"Марса Алам","subregion_name":null,"native_anex_hotel_id":"1655","current_user_search_rows":47,"tour_ids":["13272044214862"]},{"local_hotel_id":64672,"local_hotel_name":"MAPLE HOTEL & APARTMENT NHA TRANG","country_id":16,"country_name":"Вьетнам","region_name":"Нячанг","subregion_name":"Нячанг - центр","native_anex_hotel_id":"21679","current_user_search_rows":37,"tour_ids":["13277783276617","13277794775044"]},{"local_hotel_id":1709,"local_hotel_name":"SUN BAY (EX. SUN MARIS PARK)","country_id":4,"country_name":"Турция","region_name":"Мармарис","subregion_name":"Ситилер","native_anex_hotel_id":"9421","current_user_search_rows":25,"tour_ids":["13269904662986","13270482530038","13279011998258","13280271847295"]},{"local_hotel_id":27689,"local_hotel_name":"ROYAL ZANZIBAR BEACH RESORT","country_id":41,"country_name":"Танзания","region_name":"Занзибар","subregion_name":"Нунгви","native_anex_hotel_id":"28650","current_user_search_rows":21,"tour_ids":["13262676961522"]},{"local_hotel_id":22728,"local_hotel_name":"THE GRAND SOUTHSEA KHAO LAK (EX. KHAO LAK SOUTHSEA)","country_id":2,"country_name":"Таиланд","region_name":"Као Лак","subregion_name":null,"native_anex_hotel_id":"5177","current_user_search_rows":18,"tour_ids":["13276072754108","13278525928685"]},{"local_hotel_id":81951,"local_hotel_name":"AJIRA BOUTIQUE HURGHADA MARINA HOTEL","country_id":1,"country_name":"Египет","region_name":"Хургада","subregion_name":null,"native_anex_hotel_id":"45401","current_user_search_rows":18,"tour_ids":["13279738078848"]},{"local_hotel_id":80872,"local_hotel_name":"AJIRA BAY HOTEL HURGHADA MARINA","country_id":1,"country_name":"Египет","region_name":"Хургада","subregion_name":null,"native_anex_hotel_id":"45402","current_user_search_rows":17,"tour_ids":["13279738078870"]},{"local_hotel_id":57656,"local_hotel_name":"GOSIA HOTEL NHA TRANG","country_id":16,"country_name":"Вьетнам","region_name":"Нячанг","subregion_name":"Нячанг - центр","native_anex_hotel_id":"17572","current_user_search_rows":15,"tour_ids":["13277773149068","13277935326189","13278868856506","13279424008510"]},{"local_hotel_id":27673,"local_hotel_name":"BLUE BAY BEACH RESORT","country_id":41,"country_name":"Танзания","region_name":"Занзибар","subregion_name":"Кивенгва","native_anex_hotel_id":"28739","current_user_search_rows":11,"tour_ids":["13261865171798"]},{"local_hotel_id":157099,"local_hotel_name":"IBEROSTAR SELECTION ZANZIBAR (EX. MATEMWE MUYUNI RESORT & SPA)","country_id":41,"country_name":"Танзания","region_name":"Занзибар","subregion_name":null,"native_anex_hotel_id":"44470","current_user_search_rows":11,"tour_ids":["13274993294580","13276310085529"]},{"local_hotel_id":74258,"local_hotel_name":"CASA COOK EL GOUNA ADULTS ONLY","country_id":1,"country_name":"Египет","region_name":"Эль Гуна","subregion_name":null,"native_anex_hotel_id":"30599","current_user_search_rows":10,"tour_ids":["13275083695144"]},{"local_hotel_id":148539,"local_hotel_name":"AZUR WHITE RESORT","country_id":1,"country_name":"Египет","region_name":"Матрух","subregion_name":null,"native_anex_hotel_id":"45166","current_user_search_rows":10,"tour_ids":["13276571351025","13278010225154"]},{"local_hotel_id":1477,"local_hotel_name":"SEA GULL","country_id":4,"country_name":"Турция","region_name":"Кемер","subregion_name":"Бельдиби","native_anex_hotel_id":"8418","current_user_search_rows":9,"tour_ids":["13280646801158"]},{"local_hotel_id":37012,"local_hotel_name":"KONO KONO BEACH RESORT","country_id":41,"country_name":"Танзания","region_name":"Занзибар","subregion_name":null,"native_anex_hotel_id":"41090","current_user_search_rows":9,"tour_ids":["13267395646978"]},{"local_hotel_id":73871,"local_hotel_name":"MAX HOTEL NUNGWI","country_id":41,"country_name":"Танзания","region_name":"Занзибар","subregion_name":null,"native_anex_hotel_id":"31488","current_user_search_rows":9,"tour_ids":["13262656911868","13264677990005"]},{"local_hotel_id":127325,"local_hotel_name":"ANDAZ DOHA A CONCEPT BY HYATT","country_id":79,"country_name":"Катар","region_name":"Доха","subregion_name":null,"native_anex_hotel_id":"41621","current_user_search_rows":8,"tour_ids":["13280763119227","13280763119337","13280763119423"]},{"local_hotel_id":68625,"local_hotel_name":"CENTARA WEST BAY RESIDENCES & SUITES DOHA","country_id":79,"country_name":"Катар","region_name":"Доха","subregion_name":null,"native_anex_hotel_id":"41613","current_user_search_rows":7,"tour_ids":["13279976228083","13280246899321","13280763119196"]},{"local_hotel_id":77323,"local_hotel_name":"THE G SEA SHELL","country_id":1,"country_name":"Египет","region_name":"Матрух","subregion_name":null,"native_anex_hotel_id":"45234","current_user_search_rows":7,"tour_ids":["13268472277316","13278020603421"]},{"local_hotel_id":27676,"local_hotel_name":"TUI BLUE BAHARI ZANZIBAR (EX. DREAM OF ZANZIBAR)","country_id":41,"country_name":"Танзания","region_name":"Занзибар","subregion_name":null,"native_anex_hotel_id":"28624","current_user_search_rows":6,"tour_ids":["13261851548507","13261922211201","13262402074385"]},{"local_hotel_id":60004,"local_hotel_name":"MARSA MALAZ KEMPINSKI THE PEARL","country_id":79,"country_name":"Катар","region_name":"Доха","subregion_name":null,"native_anex_hotel_id":"25440","current_user_search_rows":6,"tour_ids":["13280320656879","13280320657049","13280763119817"]},{"local_hotel_id":74977,"local_hotel_name":"MANDARIN ORIENTAL DOHA","country_id":79,"country_name":"Катар","region_name":"Доха","subregion_name":null,"native_anex_hotel_id":"41650","current_user_search_rows":6,"tour_ids":["13280320658077","13280320658284","13280763119912"]},{"local_hotel_id":141976,"local_hotel_name":"DUY NGOC RESORT","country_id":16,"country_name":"Вьетнам","region_name":"Фукуок","subregion_name":null,"native_anex_hotel_id":"44231","current_user_search_rows":5,"tour_ids":["13271524178572"]},{"local_hotel_id":58479,"local_hotel_name":"SAILING HOTEL","country_id":16,"country_name":"Вьетнам","region_name":"Фукуок","subregion_name":null,"native_anex_hotel_id":"20718","current_user_search_rows":4,"tour_ids":["13273763840044"]},{"local_hotel_id":60388,"local_hotel_name":"CALIMERA SIDE RESORT HOTEL (EX. SIDE RESORT)","country_id":4,"country_name":"Турция","region_name":"Сиде","subregion_name":null,"native_anex_hotel_id":"29332","current_user_search_rows":4,"tour_ids":["13273771803432"]},{"local_hotel_id":69135,"local_hotel_name":"FAIRFIELD BY MARRIOTT GOA ANJUNA","country_id":3,"country_name":"Индия","region_name":"Север Гоа","subregion_name":null,"native_anex_hotel_id":"22546","current_user_search_rows":4,"tour_ids":["13281051649876","13281051649960","13281051650079","13281051650118"]},{"local_hotel_id":162479,"local_hotel_name":"SUN TERRA PHU QUOC HOTEL","country_id":16,"country_name":"Вьетнам","region_name":"Фукуок","subregion_name":null,"native_anex_hotel_id":"45123","current_user_search_rows":4,"tour_ids":["13271524178643","13274429954029"]},{"local_hotel_id":490,"local_hotel_name":"THE THREE CORNERS OCEAN VIEW ADULTS ONLY 16+","country_id":1,"country_name":"Египет","region_name":"Эль Гуна","subregion_name":null,"native_anex_hotel_id":"990","current_user_search_rows":3,"tour_ids":["13279738078900"]},{"local_hotel_id":148230,"local_hotel_name":"GRAND BAY HOTEL PHU QUOC","country_id":16,"country_name":"Вьетнам","region_name":"Фукуок","subregion_name":null,"native_anex_hotel_id":"44866","current_user_search_rows":3,"tour_ids":["13271524179055"]},{"local_hotel_id":159008,"local_hotel_name":"PALOMA PHU QUOC HOTEL","country_id":16,"country_name":"Вьетнам","region_name":"Фукуок","subregion_name":null,"native_anex_hotel_id":"44763","current_user_search_rows":3,"tour_ids":["13271524178891","13274429954184"]},{"local_hotel_id":161982,"local_hotel_name":"MAREA HOTEL","country_id":16,"country_name":"Вьетнам","region_name":"Фукуок","subregion_name":null,"native_anex_hotel_id":"45068","current_user_search_rows":3,"tour_ids":["13271524178878"]},{"local_hotel_id":127294,"local_hotel_name":"MED IL CAESAR ADULTS ONLY","country_id":1,"country_name":"Египет","region_name":"Матрух","subregion_name":null,"native_anex_hotel_id":"45249","current_user_search_rows":2,"tour_ids":["13270355585658"]},{"local_hotel_id":130621,"local_hotel_name":"MARMARICA CABANAS","country_id":1,"country_name":"Египет","region_name":"Матрух","subregion_name":null,"native_anex_hotel_id":"45330","current_user_search_rows":2,"tour_ids":["13278927189857"]},{"local_hotel_id":159711,"local_hotel_name":"MAREVA HOTEL KHEM BEACH","country_id":16,"country_name":"Вьетнам","region_name":"Фукуок","subregion_name":null,"native_anex_hotel_id":"45348","current_user_search_rows":2,"tour_ids":["13277286794903"]},{"local_hotel_id":163929,"local_hotel_name":"FORTUNE CENTER BAI TRUONG HOTEL","country_id":16,"country_name":"Вьетнам","region_name":"Фукуок","subregion_name":null,"native_anex_hotel_id":"45264","current_user_search_rows":1,"tour_ids":["13276512595443"]}]
JSON
,true,512,JSON_THROW_ON_ERROR);}
if(in_array('--self-test',$argv??[],true)){
  if(count(hmtn_input())!==40)throw new RuntimeException('input_count');
  if(hmtn_country_key('Турция')!=='turkey'||hmtn_country_key('Egypt')!=='egypt')throw new RuntimeException('country');
  if(!in_array('hotel su',hmtn_name_keys('HOTEL SU (EX. SU & AQUALAND; HILLSIDE SU)'),true))throw new RuntimeException('name_variant');
  $d=hmtn_dist(0,0,0,0);if($d===null||$d>1)throw new RuntimeException('distance');
  echo "TV_NATIVE_ANEX_CURRENTIZE_SELFTEST_OK\n";exit;
}

$prep=getenv('HMTN_PREP_ONLY')==='1';
$root=realpath((string)getenv('ANYTOUR_ROOT'));$opdir=(string)getenv('MATCH_OPERATION_DIR');$token=$prep?'':trim((string)fgets(STDIN));
if(!$root||$opdir===''||(!$prep&&$token===''))throw new RuntimeException('runtime');
require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
$payload=is_file($opdir.'/payload/anex-client.php')?$opdir.'/payload':$opdir;
require_once $payload.'/anex-client.php';
$db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);

$inputs=hmtn_input();$eligible=[];$preBuckets=[];$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
try{
  foreach($inputs as$r){
    $lid=(int)$r['local_hotel_id'];$aid=(int)$r['native_anex_hotel_id'];
    $s=$db->prepare('SELECT id,country_id,country_name,name,region_name,subregion_name,latitude,longitude,is_active FROM catalog_hotels WHERE id=? LIMIT 1');$s->execute([$lid]);$local=$s->fetch(PDO::FETCH_ASSOC);
    if(!$local||(int)$local['is_active']!==1){$preBuckets[]=$r+['bucket'=>'invalid_current','reason'=>'local_inactive_or_missing'];continue;}
    $u=$db->prepare("SELECT COUNT(*) FROM tour_price_observations WHERE source='user_search' AND hotel_id=?");$u->execute([$lid]);$uc=(int)$u->fetchColumn();
    if($uc<1){$preBuckets[]=$r+['bucket'=>'invalid_current','reason'=>'no_current_user_search'];continue;}
    $same=false;$protected=null;
    foreach(hmtn_rows($db,"SELECT anex_hotel_id,catalog_hotel_id,enabled FROM anex_hotel_search_mappings WHERE enabled=1 AND (anex_hotel_id=? OR catalog_hotel_id=?)",[$aid,$lid])as$m){
      if((int)$m['anex_hotel_id']===$aid&&(int)$m['catalog_hotel_id']===$lid)$same=true;
      elseif((int)$m['anex_hotel_id']===$aid)$protected='external_occupied_other_local';
      elseif((int)$m['catalog_hotel_id']===$lid)$protected='local_occupied_other_external';
    }
    if($same){$preBuckets[]=$r+['bucket'=>'already_same','reason'=>'current_enabled_mapping'];continue;}
    if($protected!==null){$preBuckets[]=$r+['bucket'=>'protected_hold','reason'=>$protected];continue;}
    $dec=hmtn_rows($db,"SELECT anex_hotel_id,catalog_hotel_id,decision_status FROM anex_hotel_decisions WHERE anex_hotel_id=? OR catalog_hotel_id=?",[$aid,$lid]);
    if($dec){$preBuckets[]=$r+['bucket'=>'protected_hold','reason'=>'current_manual_or_decision_present'];continue;}
    $ex=$db->prepare('SELECT 1 FROM anex_review_pair_exclusions WHERE anex_hotel_id=? AND catalog_hotel_id=? LIMIT 1');$ex->execute([$aid,$lid]);if($ex->fetchColumn()){$preBuckets[]=$r+['bucket'=>'protected_hold','reason'=>'pair_excluded'];continue;}
    $aliases=[];foreach(hmtn_rows($db,'SELECT alias FROM hotel_aliases WHERE hotel_id=?',[$lid])as$a){$v=trim((string)$a['alias']);if($v!=='')$aliases[]=$v;}
    $eligible[]=$r+['current_user_search_rows'=>$uc,'local'=>$local,'aliases'=>$aliases];
  }
  $db->rollBack();
}catch(Throwable$e){if($db->inTransaction())$db->rollBack();throw$e;}

if($prep){
  $c=[];foreach($preBuckets as$r)$c[$r['bucket']]=($c[$r['bucket']]??0)+1;ksort($c);
  echo json_encode(['operation'=>HMTN_OP,'state'=>'prepared_before_provider_access','source_reconcile_sha256'=>HMTN_SOURCE_SHA,'input_count'=>count($inputs),'eligible_count'=>count($eligible),'pre_bucket_counts'=>$c,'provider_attempts'=>0,'database_writes'=>0,'mapping_writes'=>0],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";exit;
}

$rows=$preBuckets;$attempts=0;$calls=0;$stop=null;$client=null;
foreach($eligible as$i=>$r){
  $aid=(int)$r['native_anex_hotel_id'];
  try{
    if(!$client||$client->requestsMade()>=11)$client=new AnyTourAnexClient($token);
    $attempts++;$raw=$client->request('Hotels_DETAILS',['HOTELINC'=>$aid]);$calls++;
    if(!$raw){$rows[]=$r+['bucket'=>'needs_second_confirmation','reason'=>'details_empty'];continue;}
    $d=hmtn_detail($raw);$local=$r['local'];
    $country=trim($d['country']);if($country!==''&&hmtn_country_key($country)!==hmtn_country_key((string)$local['country_name'])){$rows[]=$r+['bucket'=>'protected_hold','reason'=>'explicit_country_conflict','detail'=>$d];continue;}
    $detailKey=hmtn_norm($d['name']);$nameKeys=[];foreach(array_merge([(string)$local['name']],$r['aliases'])as$n)foreach(hmtn_name_keys((string)$n)as$k)$nameKeys[$k]=true;
    $nameExact=$detailKey!==''&&isset($nameKeys[$detailKey]);
    $dist=hmtn_dist($d['latitude'],$d['longitude'],$local['latitude'],$local['longitude']);$place=hmtn_place($d,$local);
    if($dist!==null&&$dist>5000){$rows[]=$r+['bucket'=>'protected_hold','reason'=>'coordinate_conflict_gt_5km','distance_m'=>(int)round($dist),'name_exact_or_alias'=>$nameExact,'place_match'=>$place,'detail'=>$d];continue;}
    $strongGeo=($dist!==null&&$dist<=1500)||($dist===null&&$place);
    $midGeo=$dist!==null&&$dist>1500&&$dist<=5000;
    if($nameExact&&$strongGeo)$bucket='current_safe_native';
    elseif($nameExact&&$midGeo&&$place)$bucket='needs_second_confirmation';
    elseif($nameExact&&!$strongGeo)$bucket='needs_second_confirmation';
    elseif($strongGeo)$bucket='needs_second_confirmation';
    else $bucket='needs_second_confirmation';
    $reason=$bucket==='current_safe_native'?'native_anchor_plus_exact_name_plus_geo':(!$nameExact?'name_not_exact_or_alias':($midGeo?'coordinate_1_5_to_5km':'geography_unproven'));
    $rows[]=$r+['bucket'=>$bucket,'reason'=>$reason,'distance_m'=>$dist===null?null:(int)round($dist),'name_exact_or_alias'=>$nameExact,'place_match'=>$place,'detail'=>$d];
  }catch(Throwable$e){
    $m=$e->getMessage();$diag=$client instanceof AnyTourAnexClient?$client->lastRequestDiagnostics():[];
    if(in_array($m,['ANEX_HTTP_ERROR','ANEX_TRANSPORT_ERROR','ANEX_TRANSPORT_UNAVAILABLE'],true)||($diag['http_status']??null)===429){$stop=$m.(($diag['http_status']??null)===429?'_429':'');break;}
    $rows[]=$r+['bucket'=>'needs_second_confirmation','reason'=>'supplier_detail_error','error'=>preg_replace('/[^A-Za-z0-9_\-]/','_',substr($m,0,100))];
  }
}
$counts=[];foreach($rows as$r)$counts[$r['bucket']]=($counts[$r['bucket']]??0)+1;ksort($counts);
$safe=array_values(array_filter($rows,fn($r)=>$r['bucket']==='current_safe_native'));
usort($safe,fn($a,$b)=>(int)$b['current_user_search_rows']<=>(int)$a['current_user_search_rows']?:$a['local_hotel_id']<=>$b['local_hotel_id']);
echo json_encode([
  'operation'=>HMTN_OP,'state'=>$stop===null?'completed_read_only':'completed_partial_provider_stop',
  'source_reconcile_sha256'=>HMTN_SOURCE_SHA,'source_artifact_id'=>HMTN_SOURCE_ARTIFACT,
  'input_count'=>count($inputs),'eligible_count'=>count($eligible),'provider_attempts'=>$attempts,'detail_calls'=>$calls,'stop_reason'=>$stop,
  'bucket_counts'=>$counts,'current_safe_native_count'=>count($safe),'current_safe_native'=>$safe,'rows'=>$rows,
  'tourvisor_calls'=>0,'andromeda_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'safe_to_write_now'=>false,
  'guards'=>['tourvisor_native_anchor'=>true,'details_state_not_country'=>true,'explicit_country_conflict_blocks'=>true,'known_coordinate_gt_5km_blocks'=>true,'exact_alias_name_for_safe'=>true,'shared_default_token_rate_lock'=>true,'supplier_limit_per_minute'=>60]
],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
