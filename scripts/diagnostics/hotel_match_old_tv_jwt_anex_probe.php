<?php
declare(strict_types=1);
/**
 * MATCH #1971: bounded read-only ANEX evidence probe through the existing
 * Tourvisor client and its TOURVISOR_JWT credential. No API contract changes.
 */

const OT_MARKER = 'MATCH_OLD_TV_JWT_ANEX_PROBE_JSON:';
const OT_CORE8 = [1,2,4,8,9,10,12,16];
const OT_GENERIC = ['hotel'=>1,'hotels'=>1,'resort'=>1,'resorts'=>1,'spa'=>1,'the'=>1,'and'=>1,'otel'=>1,'отель'=>1,'отели'=>1,'спа'=>1];
const OT_QUALIFIERS = ['annex'=>1,'beach'=>1,'garden'=>1,'north'=>1,'south'=>1,'east'=>1,'west'=>1,'club'=>1,'palace'=>1,'royal'=>1,'grand'=>1,'premium'=>1,'select'=>1,'family'=>1,'adults'=>1];

function ot_json(array $v): string { return json_encode($v, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR); }
function ot_write_new(string $path, array $v): string {
    $raw = ot_json($v)."\n";
    $fh = @fopen($path, 'x');
    if (!$fh) throw new RuntimeException('durable_create_failed');
    try { if (fwrite($fh, $raw) !== strlen($raw)) throw new RuntimeException('durable_write_failed'); fflush($fh); } finally { fclose($fh); }
    if (file_get_contents($path) !== $raw) throw new RuntimeException('durable_readback_failed');
    return hash('sha256', $raw);
}
function ot_text($v, int $max=300): string {
    if (!is_scalar($v)) return '';
    $s = trim((string)(preg_replace('/\s+/u',' ',(string)$v) ?? ''));
    return function_exists('mb_substr') ? mb_substr($s,0,$max,'UTF-8') : substr($s,0,$max);
}
function ot_id($v): ?int {
    if (is_bool($v) || !is_scalar($v)) return null;
    $s=trim((string)$v); if(!preg_match('/^[1-9][0-9]{0,11}$/D',$s)) return null; return (int)$s;
}
function ot_num($v): ?float { if(!is_scalar($v)||trim((string)$v)===''||!is_numeric((string)$v))return null; $x=(float)$v; return is_finite($x)?$x:null; }
function ot_name($v): string {
    if (is_scalar($v)) return ot_text($v);
    if (!is_array($v)) return '';
    foreach(['name','fullName','russianName','title','label'] as $k) if(isset($v[$k]) && is_scalar($v[$k]) && ot_text($v[$k])!=='') return ot_text($v[$k]);
    return '';
}
function ot_norm($v): string {
    $s=str_replace(['Ё','ё','&'],['Е','е',' and '],(string)$v);
    $s=function_exists('mb_strtolower')?mb_strtolower($s,'UTF-8'):strtolower($s);
    $s=(string)(preg_replace('/\b(?:ex|ех)\.?\s*/iu',' ',$s)??$s);
    preg_match_all('/[\p{L}\p{N}]+/u',$s,$m); $out=[];
    foreach($m[0] as $t) if($t!=='' && !isset(OT_GENERIC[$t])) $out[$t]=1;
    $r=array_keys($out); sort($r,SORT_STRING); return implode(' ',$r);
}
function ot_semantic($a,$b): array {
    $x=array_values(array_filter(explode(' ',ot_norm($a)))); $y=array_values(array_filter(explode(' ',ot_norm($b))));
    if(!$x||!$y)return['state'=>'unknown','overlap'=>0,'ratio'=>null];
    $c=array_values(array_intersect($x,$y)); $ratio=count($c)/max(1,min(count($x),count($y)));
    $strong=($x===$y)||(count($c)>=2&&$ratio>=.80)||(count($x)===1&&count($c)===1&&count($y)===1);
    return['state'=>$strong?'corroborated':'non_corroborating','overlap'=>count($c),'ratio'=>round($ratio,4)];
}
function ot_quals($v): array { $o=[]; foreach(explode(' ',ot_norm($v)) as $t) if(isset(OT_QUALIFIERS[$t]))$o[$t]=1; ksort($o); return array_keys($o); }
function ot_nums($v): array { preg_match_all('/\b\d+\b/u',ot_norm($v),$m); $r=array_values(array_unique($m[0]??[])); sort($r,SORT_STRING); return $r; }
function ot_hav(?float $a,?float $b,?float $c,?float $d):?float { if($a===null||$b===null||$c===null||$d===null)return null; $r=6371.0088;$p1=deg2rad($a);$p2=deg2rad($c);$dp=deg2rad($c-$a);$dl=deg2rad($d-$b);$x=sin($dp/2)**2+cos($p1)*cos($p2)*sin($dl/2)**2;return round($r*2*atan2(sqrt($x),sqrt(max(0.0,1.0-$x))),3); }
function ot_coords(array $r): array {
    foreach([['latitude','longitude'],['lat','lng'],['lat','lon'],['api_latitude','api_longitude']] as [$a,$b]) { $x=ot_num($r[$a]??null);$y=ot_num($r[$b]??null); if($x!==null&&$y!==null&&abs($x)<=90&&abs($y)<=180)return[$x,$y]; }
    return[null,null];
}
function ot_entities($v, array &$out, int $depth=0): void {
    if($depth>8||!is_array($v))return;
    if(!array_is_list($v)) { $id=ot_id($v['id']??null);$name=ot_name($v); if($id!==null&&$name!=='')$out[$id]=['id'=>$id,'name'=>$name]; }
    foreach($v as $x) if(is_array($x))ot_entities($x,$out,$depth+1);
}
function ot_find_entity(array $payload, array $needles): ?array {
    $e=[];ot_entities($payload,$e);$best=null;
    foreach($e as $r){$n=function_exists('mb_strtolower')?mb_strtolower($r['name'],'UTF-8'):strtolower($r['name']);foreach($needles as$q){$q=function_exists('mb_strtolower')?mb_strtolower($q,'UTF-8'):strtolower($q);if(str_contains($n,$q))return$r;}if($best===null)$best=$r;}
    return null;
}
function ot_search_id($v, int $depth=0): ?int {
    if($depth>8)return null;
    if(is_array($v)){foreach($v as$k=>$x){if(is_string($k)&&preg_match('/^(?:search|request).*id$/i',$k)){if(($id=ot_id($x))!==null)return$id;}}foreach($v as$x){if(is_array($x)&&($id=ot_search_id($x,$depth+1))!==null)return$id;}}
    return null;
}
function ot_complete($v,int$depth=0):bool { if($depth>8||!is_array($v))return false; if((int)($v['progress']??0)>=100)return true; if(in_array(strtolower(trim((string)($v['status']??''))),['complete','completed','done','ready'],true))return true; foreach($v as$x)if(is_array($x)&&ot_complete($x,$depth+1))return true; return false; }
function ot_flatten($payload):array{
    $root=$payload;if(is_array($root)&&!array_is_list($root)){foreach(['hotels','results','tours','items','data']as$k)if(isset($root[$k])&&is_array($root[$k])){$root=$root[$k];break;}}
    if(!is_array($root))return[];$out=[];foreach($root as$row){if(!is_array($row))continue;if(isset($row['tours'])&&is_array($row['tours'])&&!isset($row['hotel'])){$hotel=$row;unset($hotel['tours']);foreach($row['tours']as$t)if(is_array($t)){if(!isset($t['hotel']))$t['hotel']=$hotel;$out[]=$t;}}elseif(is_array($row['hotel']??null))$out[]=$row;}return$out;
}
function ot_operator_anex($v):bool { $n=ot_norm(ot_name($v)); return in_array($n,['anex','anex tour','anextour','анекс','анекс тур'],true); }
function ot_operator_identity(string$url):?array{
    $url=trim($url);if($url===''||strlen($url)>4096)return null;$p=parse_url($url);if(!is_array($p)||strtolower((string)($p['scheme']??''))!=='https'||isset($p['user'])||isset($p['pass']))return null;$host=strtolower((string)($p['host']??''));$path=(string)($p['path']??'/');$want=null;$mode=null;
    if($host==='agent.anextour.ru'&&$path==='/search/tour'){$want='HOTELLIST';$mode='legacy_hotellist';}elseif($host==='online.anextour.ru'&&in_array($path,['/search','/search/'],true)){$want='hotelCode';$mode='online_hotelcode';}else return null;
    $ids=[];foreach(explode('&',(string)($p['query']??''))as$part){[$k,$val]=array_pad(explode('=',$part,2),2,'');if(strcasecmp(rawurldecode($k),$want)!==0)continue;if(($id=ot_id(rawurldecode($val)))===null)return null;$ids[]=$id;}if(count($ids)!==1)return null;return['anex_hotel_id'=>$ids[0],'mode'=>$mode,'host'=>$host];
}
function ot_select(PDO$db,string$sql,array$params=[]):array{$s=$db->prepare($sql);$s->execute($params);return$s->fetchAll(PDO::FETCH_ASSOC);}
function ot_resolve_db_path(string$root):string{foreach([$root.'/data/db-v1.php',$root.'/v2/data/db-v1.php']as$p)if(is_file($p))return$p;throw new RuntimeException('db_bootstrap_missing');}
function ot_resolve_tv_path(string$root):string{foreach([$root.'/data/tourvisor-client-v1.php',$root.'/v2/data/tourvisor-client-v1.php']as$p)if(is_file($p))return$p;throw new RuntimeException('tourvisor_client_missing');}

if(in_array('--self-test',$argv??[],true)){
    if(ot_norm('SUNRISE Arabian BEACH RESORT & SPA')!=='arabian beach sunrise')throw new RuntimeException('norm_test');
    $x=ot_operator_identity('https://agent.anextour.ru/search/tour?HOTELLIST=12345');if(($x['anex_hotel_id']??0)!==12345)throw new RuntimeException('link_test');
    echo "old-tv-jwt-anex-probe self-test: PASS\n";exit(0);
}

$op=(string)getenv('OPERATION_ID');$sourceSha=(string)getenv('MATCH_SOURCE_SHA');$countryNeed=(string)(getenv('MATCH_COUNTRY')?:'egypt');$date=(string)(getenv('MATCH_DATE')?:'2026-10-27');
if(!preg_match('/^hotel-match-old-tv-jwt-anex-probe-1971-20260914-v[0-9]+-[a-z]+-[0-9]{4}-[0-9]{2}-[0-9]{2}$/D',$op))throw new RuntimeException('operation_id_required');
if(!preg_match('/^[0-9a-f]{40}$/D',$sourceSha))throw new RuntimeException('source_sha_required');
$root=(string)realpath(getcwd());if($root===''||basename($root)!=='anytoour.ru')throw new RuntimeException('root_guard');
$base=rtrim((string)getenv('HOME'),'/').'/.anytoour-match/operations';if(!is_dir($base))throw new RuntimeException('operations_root_missing');$out=$base.'/'.$op;if(!mkdir($out,0700))throw new RuntimeException('operation_exists');
$reservation=['operation_id'=>$op,'source_sha'=>$sourceSha,'state'=>'reserved_before_db_and_supplier_access','read_only'=>true,'tourvisor_credential_identifier'=>'TOURVISOR_JWT','tourvisor_contract'=>'existing_v2_data_tv_get','country'=>$countryNeed,'date'=>$date,'database_writes'=>0,'mapping_writes'=>0,'booking_calls'=>0,'lead_calls'=>0,'no_replay'=>true];ot_write_new($out.'/reservation.json',$reservation);

$result=null;
try{
    require_once ot_resolve_db_path($root);
    $db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
    $mapped=array_fill_keys(array_map('intval',array_column(ot_select($db,'SELECT anex_hotel_id FROM anex_hotel_search_mappings'),'anex_hotel_id')),true);
    $manual=array_fill_keys(array_map('intval',array_column(ot_select($db,'SELECT anex_hotel_id FROM anex_hotel_decisions'),'anex_hotel_id')),true);
    $obs=[];foreach(ot_select($db,'SELECT * FROM anex_search_hotel_observations ORDER BY search_count DESC,last_seen_utc DESC,anex_hotel_id')as$r){$aid=(int)($r['anex_hotel_id']??0);if($aid>0)$obs[$aid]=$r;}
    $stage=[];foreach(ot_select($db,'SELECT * FROM anex_hotels ORDER BY anex_hotel_id')as$r){$aid=(int)($r['anex_hotel_id']??0);if($aid>0)$stage[$aid]=$r;}
    $excl=[];foreach(ot_select($db,'SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions')as$r){$excl[(int)$r['anex_hotel_id']][(int)$r['catalog_hotel_id']]=true;}
    $db->exec('ROLLBACK');

    require_once ot_resolve_tv_path($root);
    if(!function_exists('v2_data_tv_get'))throw new RuntimeException('tourvisor_client_contract_missing');
    if(trim((string)(getenv('TOURVISOR_JWT')?:''))==='' && !(defined('TOURVISOR_JWT')&&trim((string)TOURVISOR_JWT)!==''))throw new RuntimeException('old_tourvisor_jwt_missing');

    $calls=0;
    $departures=v2_data_tv_get('/departures');$calls++;$dep=ot_find_entity($departures,['моск','moscow']);if($dep===null)throw new RuntimeException('moscow_departure_not_found');
    $countries=v2_data_tv_get('/countries',['departureId'=>$dep['id'],'onlyCharter'=>false,'onlyDirect'=>false]);$calls++;
    $countryNeedles=$countryNeed==='egypt'?['егип','egypt']:($countryNeed==='turkey'?['турц','turkey','türkiye']:[$countryNeed]);$country=ot_find_entity($countries,$countryNeedles);if($country===null)throw new RuntimeException('country_not_found');
    $operators=v2_data_tv_get('/operators',['departureId'=>$dep['id'],'countryId'=>$country['id']]);$calls++;$operator=ot_find_entity($operators,['anex','анекс']);if($operator===null)throw new RuntimeException('anex_operator_not_found');

    $search=v2_data_tv_get('/tours/search',['departureId'=>$dep['id'],'countryId'=>$country['id'],'dateFrom'=>$date,'dateTo'=>$date,'nightsFrom'=>7,'nightsTo'=>7,'adults'=>2,'operatorIds'=>[$operator['id']],'currency'=>'RUB']);$calls++;$sid=ot_search_id($search);if($sid===null)throw new RuntimeException('search_id_not_found');
    try{v2_data_tv_get('/tours/search/'.$sid.'/continue');$calls++;}catch(Throwable$e){if(!str_contains($e->getMessage(),'429'))throw$e;throw new RuntimeException('old_tourvisor_http_429_continue');}
    sleep(8);$status=v2_data_tv_get('/tours/search/'.$sid.'/status',['operatorStatus'=>false]);$calls++;
    if(!ot_complete($status)){try{v2_data_tv_get('/tours/search/'.$sid.'/continue');$calls++;}catch(Throwable$e){if(str_contains($e->getMessage(),'429'))throw new RuntimeException('old_tourvisor_http_429_continue');throw$e;}sleep(12);$status=v2_data_tv_get('/tours/search/'.$sid.'/status',['operatorStatus'=>false]);$calls++;}
    if(!ot_complete($status))throw new RuntimeException('tourvisor_search_not_complete_bounded');
    $payload=v2_data_tv_get('/tours/search/'.$sid,['limit'=>500]);$calls++;
    $rows=ot_flatten($payload);$anexRows=0;$strict=[];$missingLink=0;$rejectedLink=0;
    foreach($rows as$row){if(!is_array($row))continue;$opv=is_array($row['operator']??null)?$row['operator']:[];if(!ot_operator_anex($opv))continue;$anexRows++;$h=is_array($row['hotel']??null)?$row['hotel']:[];$tv=ot_id($h['id']??null);$hname=ot_name($h);$link=ot_text($row['operatorLink']??($h['operatorLink']??''),4096);if($link===''){$missingLink++;continue;}$id=ot_operator_identity($link);if($id===null){$rejectedLink++;continue;}if($tv===null)continue;$aid=(int)$id['anex_hotel_id'];$src=$obs[$aid]??$stage[$aid]??[];$srcName=ot_text($src['hotel_name']??$src['api_name']??$src['xml_name']??'');$sem=$srcName!==''?ot_semantic($srcName,$hname):['state'=>'unknown','overlap'=>0,'ratio'=>null];$ql=($srcName!=='' && ot_quals($srcName)!==ot_quals($hname));$nl=($srcName!=='' && ot_nums($srcName)!==ot_nums($hname) && (ot_nums($srcName)||ot_nums($hname)));[$slat,$slon]=ot_coords($src);$clat=ot_num($h['latitude']??($h['common']['latitude']??null));$clon=ot_num($h['longitude']??($h['common']['longitude']??null));$dist=ot_hav($slat,$slon,$clat,$clon);$already=isset($mapped[$aid]);$protected=isset($manual[$aid]);$pairExcluded=isset($excl[$aid][$tv]);$coordConflict=$dist!==null&&$dist>5.0;$safe=!$already&&!$protected&&!$pairExcluded&&!$coordConflict&&!$ql&&!$nl&&$sem['state']==='corroborated';$strict[]=['anex_hotel_id'=>$aid,'local_hotel_id'=>$tv,'tourvisor_hotel_name'=>$hname,'source_name'=>$srcName?:null,'identity_mode'=>$id['mode'],'semantic'=>$sem,'qualifier_conflict'=>$ql,'numeric_conflict'=>$nl,'distance_km'=>$dist,'already_mapped'=>$already,'manual_protected'=>$protected,'pair_excluded'=>$pairExcluded,'coordinate_conflict_gt5km'=>$coordConflict,'live_search_count'=>(int)($obs[$aid]['search_count']??0),'tier'=>$safe?'SAFE':'HOLD'];}
    $byPair=[];foreach($strict as$r){$k=$r['anex_hotel_id'].'|'.$r['local_hotel_id'];if(!isset($byPair[$k])||($r['tier']==='SAFE'&&$byPair[$k]['tier']!=='SAFE'))$byPair[$k]=$r;}$strict=array_values($byPair);usort($strict,static fn($a,$b)=>[$a['tier']!=='SAFE',-$a['live_search_count'],$a['anex_hotel_id']]<=>[$b['tier']!=='SAFE',-$b['live_search_count'],$b['anex_hotel_id']]);
    $safe=count(array_filter($strict,static fn($r)=>$r['tier']==='SAFE'));
    $result=['operation_id'=>$op,'source_sha'=>$sourceSha,'status'=>'completed','read_only'=>true,'tourvisor_credential_identifier'=>'TOURVISOR_JWT','country'=>$countryNeed,'date'=>$date,'departure'=>['id'=>$dep['id'],'name'=>$dep['name']],'country_entity'=>['id'=>$country['id'],'name'=>$country['name']],'operator'=>['id'=>$operator['id'],'name'=>$operator['name']],'search_id_present'=>true,'tourvisor_calls'=>$calls,'flattened_rows'=>count($rows),'anex_operator_rows'=>$anexRows,'strict_operator_identities'=>count($strict),'safe_current_candidates'=>$safe,'missing_operator_link'=>$missingLink,'rejected_operator_link'=>$rejectedLink,'rows'=>$strict,'database_writes'=>0,'mapping_writes'=>0,'booking_calls'=>0,'lead_calls'=>0,'raw_provider_bodies_recorded'=>false,'token_values_recorded'=>false,'no_replay'=>true];
} catch(Throwable$e) {
    try{if(isset($db)&&$db instanceof PDO&&$db->inTransaction())$db->exec('ROLLBACK');}catch(Throwable$x){}
    $msg=$e->getMessage();$reason=str_contains($msg,'429')?'old_tourvisor_http_429':(str_contains(strtolower($msg),'401')||str_contains(strtolower($msg),'403')?'old_tourvisor_auth_rejected':preg_replace('/[^A-Za-z0-9_.-]+/','_',substr($msg,0,120)));
    $result=['operation_id'=>$op,'source_sha'=>$sourceSha,'status'=>'blocked','reason'=>$reason,'tourvisor_credential_identifier'=>'TOURVISOR_JWT','country'=>$countryNeed,'date'=>$date,'database_writes'=>0,'mapping_writes'=>0,'booking_calls'=>0,'lead_calls'=>0,'raw_provider_bodies_recorded'=>false,'token_values_recorded'=>false,'no_replay'=>true];
}
$rh=ot_write_new($out.'/result.json',$result);$receipt=['operation_id'=>$op,'source_sha'=>$sourceSha,'state'=>$result['status'],'result_sha256'=>$rh,'readback_verified'=>hash('sha256',(string)file_get_contents($out.'/result.json'))===$rh,'database_writes'=>0,'mapping_writes'=>0,'booking_calls'=>0,'lead_calls'=>0,'no_replay'=>true];ot_write_new($out.'/receipt.json',$receipt);echo OT_MARKER.ot_json($result)."\n";exit($result['status']==='completed'?0:2);
