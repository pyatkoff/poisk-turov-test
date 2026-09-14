<?php
declare(strict_types=1);
/**
 * MATCH #1971: bounded mass read-only Tourvisor(old JWT) -> ANEX HOTELLIST bridge.
 * One ANEX-only one-day search, up to 30 unique Tourvisor hotels, detail reads,
 * then CURRENT DB classification. No DB/mapping writes and no API contract changes.
 */

const HM_MARKER = 'MATCH_OLD_TV_HOTELLIST_MASS_JSON:';
const HM_GENERIC = ['hotel'=>1,'hotels'=>1,'resort'=>1,'resorts'=>1,'spa'=>1,'the'=>1,'and'=>1,'otel'=>1,'отель'=>1,'отели'=>1,'спа'=>1];
const HM_QUALIFIERS = ['annex'=>1,'beach'=>1,'garden'=>1,'north'=>1,'south'=>1,'east'=>1,'west'=>1,'club'=>1,'palace'=>1,'royal'=>1,'grand'=>1,'premium'=>1,'select'=>1,'family'=>1,'adults'=>1];

function hm_json(array $v): string { return json_encode($v, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR); }
function hm_write_new(string $path,array $v):string{
    $raw=hm_json($v)."\n";$fh=@fopen($path,'x');if(!$fh)throw new RuntimeException('durable_create_failed');
    try{if(fwrite($fh,$raw)!==strlen($raw))throw new RuntimeException('durable_write_failed');fflush($fh);}finally{fclose($fh);}if(file_get_contents($path)!==$raw)throw new RuntimeException('durable_readback_failed');return hash('sha256',$raw);
}
function hm_text($v,int $max=300):string{if(!is_scalar($v))return'';$s=trim((string)(preg_replace('/\s+/u',' ',(string)$v)??''));return function_exists('mb_substr')?mb_substr($s,0,$max,'UTF-8'):substr($s,0,$max);}
function hm_id($v):?int{if(is_bool($v)||!is_scalar($v))return null;$s=trim((string)$v);if(!preg_match('/^[1-9][0-9]{0,14}$/D',$s))return null;return(int)$s;}
function hm_num($v):?float{if(!is_scalar($v)||trim((string)$v)===''||!is_numeric((string)$v))return null;$x=(float)$v;return is_finite($x)?$x:null;}
function hm_name($v):string{if(is_scalar($v))return hm_text($v);if(!is_array($v))return'';foreach(['name','fullName','russianName','title','label']as$k)if(isset($v[$k])&&is_scalar($v[$k])&&hm_text($v[$k])!=='')return hm_text($v[$k]);return'';}
function hm_norm($v):string{
    $s=str_replace(['Ё','ё','&'],['Е','е',' and '],(string)$v);$s=function_exists('mb_strtolower')?mb_strtolower($s,'UTF-8'):strtolower($s);
    $s=(string)(preg_replace('/\b(?:ex|ех|former(?:ly)?)\.?\s*/iu',' ',$s)??$s);preg_match_all('/[\p{L}\p{N}]+/u',$s,$m);$out=[];
    foreach($m[0]as$t)if($t!==''&&!isset(HM_GENERIC[$t]))$out[$t]=1;$r=array_keys($out);sort($r,SORT_STRING);return implode(' ',$r);
}
function hm_semantic($a,$b):array{$x=array_values(array_filter(explode(' ',hm_norm($a))));$y=array_values(array_filter(explode(' ',hm_norm($b))));if(!$x||!$y)return['state'=>'unknown','overlap'=>0,'ratio'=>null];$c=array_values(array_intersect($x,$y));$ratio=count($c)/max(1,min(count($x),count($y)));$strong=($x===$y)||(count($c)>=2&&$ratio>=.80)||(count($x)===1&&count($c)===1&&count($y)===1);return['state'=>$strong?'corroborated':'non_corroborating','overlap'=>count($c),'ratio'=>round($ratio,4)];}
function hm_quals($v):array{$o=[];foreach(explode(' ',hm_norm($v))as$t)if(isset(HM_QUALIFIERS[$t]))$o[$t]=1;ksort($o);return array_keys($o);}
function hm_nums($v):array{preg_match_all('/\b\d+\b/u',hm_norm($v),$m);$r=array_values(array_unique($m[0]??[]));sort($r,SORT_STRING);return$r;}
function hm_coords(array$r):array{foreach([['latitude','longitude'],['lat','lng'],['lat','lon'],['api_latitude','api_longitude']]as[$a,$b]){$x=hm_num($r[$a]??null);$y=hm_num($r[$b]??null);if($x!==null&&$y!==null&&abs($x)<=90&&abs($y)<=180)return[$x,$y];}return[null,null];}
function hm_hav(?float$a,?float$b,?float$c,?float$d):?float{if($a===null||$b===null||$c===null||$d===null)return null;$r=6371.0088;$p1=deg2rad($a);$p2=deg2rad($c);$dp=deg2rad($c-$a);$dl=deg2rad($d-$b);$x=sin($dp/2)**2+cos($p1)*cos($p2)*sin($dl/2)**2;return round($r*2*atan2(sqrt($x),sqrt(max(0.0,1.0-$x))),3);}
function hm_entities($v,array&$out,int$depth=0):void{if($depth>8||!is_array($v))return;if(!array_is_list($v)){$id=hm_id($v['id']??null);$name=hm_name($v);if($id!==null&&$name!=='')$out[$id]=['id'=>$id,'name'=>$name];}foreach($v as$x)if(is_array($x))hm_entities($x,$out,$depth+1);}
function hm_find_entity(array$payload,array$needles):?array{$e=[];hm_entities($payload,$e);foreach($e as$r){$n=function_exists('mb_strtolower')?mb_strtolower($r['name'],'UTF-8'):strtolower($r['name']);foreach($needles as$q){$q=function_exists('mb_strtolower')?mb_strtolower($q,'UTF-8'):strtolower($q);if(str_contains($n,$q))return$r;}}return null;}
function hm_search_id($v,int$depth=0):?int{if($depth>8)return null;if(is_array($v)){foreach($v as$k=>$x)if(is_string($k)&&preg_match('/^(?:search|request).*id$/i',$k)&&($id=hm_id($x))!==null)return$id;foreach($v as$x)if(is_array($x)&&($id=hm_search_id($x,$depth+1))!==null)return$id;}return null;}
function hm_complete($v,int$depth=0):bool{if($depth>8||!is_array($v))return false;if((int)($v['progress']??0)>=100)return true;if(in_array(strtolower(trim((string)($v['status']??''))),['complete','completed','done','ready'],true))return true;foreach($v as$x)if(is_array($x)&&hm_complete($x,$depth+1))return true;return false;}
function hm_flatten($payload):array{$root=$payload;if(is_array($root)&&!array_is_list($root)){foreach(['hotels','results','tours','items','data']as$k)if(isset($root[$k])&&is_array($root[$k])){$root=$root[$k];break;}}if(!is_array($root))return[];$out=[];foreach($root as$row){if(!is_array($row))continue;if(isset($row['tours'])&&is_array($row['tours'])&&!isset($row['hotel'])){$hotel=$row;unset($hotel['tours']);foreach($row['tours']as$t)if(is_array($t)){if(!isset($t['hotel']))$t['hotel']=$hotel;$out[]=$t;}}elseif(is_array($row['hotel']??null))$out[]=$row;}return$out;}
function hm_operator_anex($v):bool{$n=hm_norm(hm_name($v));return in_array($n,['anex','anex tour','anextour','анекс','анекс тур'],true);}
function hm_operator_identity(string$url):?array{
    $url=trim($url);if($url===''||strlen($url)>4096)return null;$p=parse_url($url);if(!is_array($p)||strtolower((string)($p['scheme']??''))!=='https'||isset($p['user'])||isset($p['pass']))return null;
    $host=strtolower((string)($p['host']??''));$path=(string)($p['path']??'');$want=null;$mode=null;
    if($host==='agent.anextour.ru'&&$path==='/search/tour'){$want='HOTELLIST';$mode='legacy_hotellist';}
    elseif($host==='online.anextour.ru'&&in_array($path,['/search','/search/'],true)){$want='hotelCode';$mode='online_hotelcode';}else return null;
    $pairs=[];parse_str((string)($p['query']??''),$pairs);$ids=[];foreach($pairs as$k=>$v){if(strcasecmp((string)$k,$want)!==0||!is_scalar($v))continue;if(($id=hm_id($v))===null)return null;$ids[]=$id;}if(count($ids)!==1)return null;return['anex_hotel_id'=>$ids[0],'mode'=>$mode,'host'=>$host];
}
function hm_select(PDO$db,string$sql,array$params=[]):array{$s=$db->prepare($sql);$s->execute($params);return$s->fetchAll(PDO::FETCH_ASSOC);}
function hm_db_path(string$root):string{foreach([$root.'/data/db-v1.php',$root.'/v2/data/db-v1.php']as$p)if(is_file($p))return$p;throw new RuntimeException('db_bootstrap_missing');}
function hm_tv_path(string$root):string{foreach([$root.'/data/tourvisor-client-v1.php',$root.'/v2/data/tourvisor-client-v1.php']as$p)if(is_file($p))return$p;throw new RuntimeException('tourvisor_client_missing');}
function hm_detail_coords(array$h):array{$lat=hm_num($h['latitude']??($h['common']['latitude']??null));$lon=hm_num($h['longitude']??($h['common']['longitude']??null));return[$lat,$lon];}

if(in_array('--self-test',$argv??[],true)){
    if(hm_norm('BARCELO TIRAN SHARM HOTEL')!=='barcelo sharm tiran')throw new RuntimeException('norm_test');
    $x=hm_operator_identity('https://agent.anextour.ru/search/tour?HOTELLIST=4158&ADULT=2');if(($x['anex_hotel_id']??0)!==4158||($x['mode']??'')!=='legacy_hotellist')throw new RuntimeException('identity_test');
    echo "old-tv-hotellist-mass self-test: PASS\n";exit(0);
}

$op=(string)getenv('OPERATION_ID');$sha=(string)getenv('MATCH_SOURCE_SHA');$date=(string)(getenv('MATCH_DATE')?:'2026-10-31');$limit=(int)(getenv('MATCH_LIMIT')?:30);
if(!preg_match('/^hotel-match-old-tv-hotellist-mass-1971-20260914-v[0-9]+-egypt$/D',$op))throw new RuntimeException('operation_id_required');
if(!preg_match('/^[0-9a-f]{40}$/D',$sha))throw new RuntimeException('source_sha_required');if($limit<1||$limit>30)throw new RuntimeException('limit_guard');
$root=(string)realpath(getcwd());if($root===''||basename($root)!=='anytoour.ru')throw new RuntimeException('root_guard');$base=rtrim((string)getenv('HOME'),'/').'/.anytoour-match/operations';if(!is_dir($base))throw new RuntimeException('operations_root_missing');$out=$base.'/'.$op;if(!mkdir($out,0700))throw new RuntimeException('operation_exists');
hm_write_new($out.'/reservation.json',['operation_id'=>$op,'source_sha'=>$sha,'state'=>'reserved_before_db_and_supplier_access','read_only'=>true,'credential_identifier'=>'TOURVISOR_JWT','date'=>$date,'limit'=>$limit,'database_writes'=>0,'mapping_writes'=>0,'booking_calls'=>0,'lead_calls'=>0,'no_replay'=>true]);

$result=null;
try{
    require_once hm_db_path($root);$db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
    $mapped=[];foreach(hm_select($db,'SELECT anex_hotel_id,catalog_hotel_id FROM anex_hotel_search_mappings')as$r)$mapped[(int)$r['anex_hotel_id']]=(int)$r['catalog_hotel_id'];
    $manual=array_fill_keys(array_map('intval',array_column(hm_select($db,'SELECT anex_hotel_id FROM anex_hotel_decisions'),'anex_hotel_id')),true);
    $obs=[];foreach(hm_select($db,'SELECT * FROM anex_search_hotel_observations ORDER BY search_count DESC,last_seen_utc DESC,anex_hotel_id')as$r){$aid=(int)($r['anex_hotel_id']??0);if($aid>0)$obs[$aid]=$r;}
    $stage=[];foreach(hm_select($db,'SELECT * FROM anex_hotels ORDER BY anex_hotel_id')as$r){$aid=(int)($r['anex_hotel_id']??0);if($aid>0)$stage[$aid]=$r;}
    $excl=[];foreach(hm_select($db,'SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions')as$r)$excl[(int)$r['anex_hotel_id']][(int)$r['catalog_hotel_id']]=true;$db->exec('ROLLBACK');

    require_once hm_tv_path($root);if(!function_exists('v2_data_tv_get'))throw new RuntimeException('tourvisor_client_contract_missing');
    if(trim((string)(getenv('TOURVISOR_JWT')?:''))===''&&!(defined('TOURVISOR_JWT')&&trim((string)TOURVISOR_JWT)!==''))throw new RuntimeException('old_tourvisor_jwt_missing');
    $calls=0;$departures=v2_data_tv_get('/departures');$calls++;$dep=hm_find_entity($departures,['моск','moscow']);if($dep===null)throw new RuntimeException('moscow_departure_not_found');
    $countries=v2_data_tv_get('/countries',['departureId'=>$dep['id'],'onlyCharter'=>false,'onlyDirect'=>false]);$calls++;$country=hm_find_entity($countries,['егип','egypt']);if($country===null)throw new RuntimeException('egypt_not_found');
    $operators=v2_data_tv_get('/operators',['departureId'=>$dep['id'],'countryId'=>$country['id']]);$calls++;$operator=hm_find_entity($operators,['anex','анекс']);if($operator===null)throw new RuntimeException('anex_operator_not_found');
    $search=v2_data_tv_get('/tours/search',['departureId'=>$dep['id'],'countryId'=>$country['id'],'dateFrom'=>$date,'dateTo'=>$date,'nightsFrom'=>7,'nightsTo'=>7,'adults'=>2,'operatorIds'=>[$operator['id']],'currency'=>'RUB']);$calls++;$sid=hm_search_id($search);if($sid===null)throw new RuntimeException('search_id_not_found');
    try{v2_data_tv_get('/tours/search/'.$sid.'/continue');$calls++;}catch(Throwable$e){if(str_contains($e->getMessage(),'429'))throw new RuntimeException('old_tourvisor_http_429_continue');throw$e;}
    sleep(8);$status=v2_data_tv_get('/tours/search/'.$sid.'/status',['operatorStatus'=>false]);$calls++;
    if(!hm_complete($status)){try{v2_data_tv_get('/tours/search/'.$sid.'/continue');$calls++;}catch(Throwable$e){if(str_contains($e->getMessage(),'429'))throw new RuntimeException('old_tourvisor_http_429_continue');throw$e;}sleep(12);$status=v2_data_tv_get('/tours/search/'.$sid.'/status',['operatorStatus'=>false]);$calls++;}
    if(!hm_complete($status))throw new RuntimeException('tourvisor_search_not_complete_bounded');$payload=v2_data_tv_get('/tours/search/'.$sid,['limit'=>500]);$calls++;$flat=hm_flatten($payload);

    $selected=[];$seenHotels=[];foreach($flat as$r){if(!is_array($r))continue;$tid=hm_id($r['id']??null);$h=is_array($r['hotel']??null)?$r['hotel']:[];$hid=hm_id($h['id']??null);if($tid===null||$hid===null||isset($seenHotels[$hid]))continue;$seenHotels[$hid]=true;$selected[]=['tour_id'=>$tid,'search_hotel_id'=>$hid,'search_hotel_name'=>hm_name($h)];if(count($selected)>=$limit)break;}
    if(!$selected)throw new RuntimeException('no_unique_tours_selected');

    $rows=[];$rateLimited=false;$detailErrors=0;foreach($selected as$s){
        try{$d=v2_data_tv_get('/tours/'.$s['tour_id'],['currency'=>'RUB']);$calls++;}catch(Throwable$e){$calls++;if(str_contains($e->getMessage(),'429')){$rateLimited=true;break;}$detailErrors++;$rows[]=$s+['tier'=>'HOLD','reason'=>'detail_error'];continue;}
        $h=is_array($d['hotel']??null)?$d['hotel']:[];$tv=hm_id($h['id']??null);$hname=hm_name($h);$opName=hm_name($d['operator']??null);$link=hm_text($d['operatorLink']??'',4096);$id=hm_operator_identity($link);
        if($tv===null||$tv!==$s['search_hotel_id']){$rows[]=$s+['tier'=>'HOLD','reason'=>'tourvisor_hotel_id_mismatch','detail_hotel_id'=>$tv,'detail_hotel_name'=>$hname];continue;}
        if(!hm_operator_anex($d['operator']??null)){$rows[]=$s+['tier'=>'HOLD','reason'=>'detail_operator_not_anex','detail_hotel_id'=>$tv,'detail_hotel_name'=>$hname,'operator_name'=>$opName];continue;}
        if($id===null){$rows[]=$s+['tier'=>'HOLD','reason'=>'operator_link_identity_missing','detail_hotel_id'=>$tv,'detail_hotel_name'=>$hname,'operator_name'=>$opName,'operator_link_present'=>$link!==''];continue;}
        $aid=(int)$id['anex_hotel_id'];$src=$obs[$aid]??$stage[$aid]??[];$srcName=hm_text($src['hotel_name']??$src['api_name']??$src['xml_name']??'');$sem=$srcName!==''?hm_semantic($srcName,$hname):['state'=>'unknown','overlap'=>0,'ratio'=>null];$qConflict=$srcName!==''&&hm_quals($srcName)!==hm_quals($hname);$nConflict=$srcName!==''&&hm_nums($srcName)!==hm_nums($hname)&&(hm_nums($srcName)||hm_nums($hname));[$slat,$slon]=hm_coords($src);[$tlat,$tlon]=hm_detail_coords($h);$dist=hm_hav($slat,$slon,$tlat,$tlon);$coordConflict=$dist!==null&&$dist>5.0;$pairExcluded=isset($excl[$aid][$tv]);$manualProtected=isset($manual[$aid]);$mappedLocal=$mapped[$aid]??null;$alreadySame=$mappedLocal===$tv;$mappingConflict=$mappedLocal!==null&&$mappedLocal!==$tv;
        $reason='safe_direct_hotellist';$tier='SAFE';if($alreadySame){$tier='EXISTING';$reason='already_mapped_same_pair';}elseif($mappingConflict){$tier='HOLD';$reason='existing_mapping_conflict';}elseif($manualProtected){$tier='HOLD';$reason='manual_protected';}elseif($pairExcluded){$tier='HOLD';$reason='pair_excluded';}elseif($srcName===''){$tier='HOLD';$reason='anex_source_row_missing';}elseif($coordConflict){$tier='HOLD';$reason='coordinate_conflict_gt5km';}elseif($qConflict){$tier='HOLD';$reason='qualifier_conflict';}elseif($nConflict){$tier='HOLD';$reason='numeric_conflict';}elseif($sem['state']!=='corroborated'){$tier='HOLD';$reason='name_not_corroborated';}
        $rows[]=['tour_id'=>$s['tour_id'],'tourvisor_hotel_id'=>$tv,'tourvisor_hotel_name'=>$hname,'anex_hotel_id'=>$aid,'identity_mode'=>$id['mode'],'source_name'=>$srcName?:null,'semantic'=>$sem,'distance_km'=>$dist,'qualifier_conflict'=>$qConflict,'numeric_conflict'=>$nConflict,'manual_protected'=>$manualProtected,'pair_excluded'=>$pairExcluded,'existing_mapping_local_id'=>$mappedLocal,'live_search_count'=>(int)($obs[$aid]['search_count']??0),'tier'=>$tier,'reason'=>$reason];usleep(200000);
    }
    $safe=count(array_filter($rows,fn($r)=>($r['tier']??'')==='SAFE'));$existing=count(array_filter($rows,fn($r)=>($r['tier']??'')==='EXISTING'));$holds=count(array_filter($rows,fn($r)=>($r['tier']??'')==='HOLD'));$identities=count(array_filter($rows,fn($r)=>isset($r['anex_hotel_id'])));
    $result=['operation_id'=>$op,'source_sha'=>$sha,'status'=>$rateLimited?'partial_rate_limited':'completed','credential_identifier'=>'TOURVISOR_JWT','date'=>$date,'limit'=>$limit,'search_id_present'=>true,'flattened_rows'=>count($flat),'unique_tours_selected'=>count($selected),'detail_rows'=>count($rows),'direct_hotellist_identities'=>$identities,'safe_current_candidates'=>$safe,'existing_same_pair'=>$existing,'holds'=>$holds,'detail_errors'=>$detailErrors,'rate_limited'=>$rateLimited,'tourvisor_calls'=>$calls,'rows'=>$rows,'database_writes'=>0,'mapping_writes'=>0,'booking_calls'=>0,'lead_calls'=>0,'raw_provider_bodies_recorded'=>false,'raw_operator_links_recorded'=>false,'token_values_recorded'=>false,'no_replay'=>true];
}catch(Throwable$e){try{if(isset($db)&&$db instanceof PDO&&$db->inTransaction())$db->exec('ROLLBACK');}catch(Throwable$x){}$msg=$e->getMessage();$reason=str_contains($msg,'429')?'old_tourvisor_http_429':(str_contains(strtolower($msg),'401')||str_contains(strtolower($msg),'403')?'old_tourvisor_auth_rejected':preg_replace('/[^A-Za-z0-9_.-]+/','_',substr($msg,0,120)));$result=['operation_id'=>$op,'source_sha'=>$sha,'status'=>'blocked','reason'=>$reason,'credential_identifier'=>'TOURVISOR_JWT','database_writes'=>0,'mapping_writes'=>0,'booking_calls'=>0,'lead_calls'=>0,'no_replay'=>true];}
$rh=hm_write_new($out.'/result.json',$result);hm_write_new($out.'/receipt.json',['operation_id'=>$op,'source_sha'=>$sha,'state'=>$result['status'],'result_sha256'=>$rh,'readback_verified'=>hash('sha256',(string)file_get_contents($out.'/result.json'))===$rh,'database_writes'=>0,'mapping_writes'=>0,'no_replay'=>true]);echo HM_MARKER.hm_json($result)."\n";exit(in_array($result['status'],['completed','partial_rate_limited'],true)?0:2);
