<?php
declare(strict_types=1);

/**
 * MATCH #1971: CURRENT-guarded evidence-only detail reads for previously saved
 * Tourvisor tour IDs. No search calls, DB writes, mapping writes, bookings or leads.
 */

const HM_MARK = 'MATCH_CURRENT_SAVED_TOUR_DETAIL_JSON:';
const HM_GENERIC = ['hotel'=>1,'hotels'=>1,'resort'=>1,'resorts'=>1,'spa'=>1,'the'=>1,'and'=>1,'otel'=>1,'отель'=>1,'отели'=>1,'спа'=>1];
const HM_QUALIFIERS = ['annex'=>1,'beach'=>1,'garden'=>1,'north'=>1,'south'=>1,'east'=>1,'west'=>1,'club'=>1,'palace'=>1,'royal'=>1,'grand'=>1,'premium'=>1,'select'=>1,'family'=>1,'adults'=>1,'adult'=>1,'only'=>1];

function hm_json(array $v): string {
    return json_encode($v, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
}
function hm_once(string $path, array $v): string {
    $raw = hm_json($v)."\n";
    $fh = @fopen($path, 'x');
    if (!$fh) throw new RuntimeException('durable_create_failed');
    try {
        if (fwrite($fh, $raw) !== strlen($raw)) throw new RuntimeException('durable_write_failed');
        fflush($fh);
    } finally {
        fclose($fh);
    }
    if (file_get_contents($path) !== $raw) throw new RuntimeException('durable_readback_failed');
    return hash('sha256', $raw);
}
function hm_id($v): ?int {
    if (is_bool($v) || !is_scalar($v)) return null;
    $s = trim((string)$v);
    return preg_match('/^[1-9][0-9]{0,20}$/D', $s) ? (int)$s : null;
}
function hm_text($v, int $max=300): string {
    if (!is_scalar($v)) return '';
    $s = trim((string)(preg_replace('/\s+/u', ' ', (string)$v) ?? ''));
    return function_exists('mb_substr') ? mb_substr($s, 0, $max, 'UTF-8') : substr($s, 0, $max);
}
function hm_name($v): string {
    if (is_scalar($v)) return hm_text($v);
    if (!is_array($v)) return '';
    foreach (['name','fullName','russianName','title','label'] as $k) {
        if (isset($v[$k]) && hm_text($v[$k]) !== '') return hm_text($v[$k]);
    }
    return '';
}
function hm_norm($v): string {
    $s = str_replace(['Ё','ё','&'], ['Е','е',' and '], (string)$v);
    $s = function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
    $s = (string)(preg_replace('/\b(?:ex|ех|former(?:ly)?)\.?\s*/iu', ' ', $s) ?? $s);
    preg_match_all('/[\p{L}\p{N}]+/u', $s, $m);
    $out = [];
    foreach ($m[0] as $t) if ($t !== '' && !isset(HM_GENERIC[$t])) $out[$t]=1;
    $r = array_keys($out);
    sort($r, SORT_STRING);
    return implode(' ', $r);
}
function hm_semantic($a, $b): array {
    $x = array_values(array_filter(explode(' ', hm_norm($a))));
    $y = array_values(array_filter(explode(' ', hm_norm($b))));
    if (!$x || !$y) return ['state'=>'unknown','overlap'=>0,'ratio'=>null];
    $c = array_values(array_intersect($x, $y));
    $ratio = count($c)/max(1, min(count($x), count($y)));
    $strong = ($x === $y) || (count($c)>=2 && $ratio>=.80) || (count($x)===1 && count($c)===1 && count($y)===1);
    return ['state'=>$strong?'corroborated':'non_corroborating','overlap'=>count($c),'ratio'=>round($ratio,4)];
}
function hm_quals($v): array {
    $out=[];
    foreach (explode(' ', hm_norm($v)) as $t) if (isset(HM_QUALIFIERS[$t])) $out[$t]=1;
    ksort($out);
    return array_keys($out);
}
function hm_nums($v): array {
    preg_match_all('/\b\d+\b/u', hm_norm($v), $m);
    $r = array_values(array_unique($m[0] ?? []));
    sort($r, SORT_STRING);
    return $r;
}
function hm_num($v): ?float {
    if (!is_scalar($v) || trim((string)$v)==='' || !is_numeric((string)$v)) return null;
    $x = (float)$v;
    return is_finite($x) ? $x : null;
}
function hm_coords(array $r): array {
    foreach ([['latitude','longitude'],['lat','lng'],['lat','lon'],['api_latitude','api_longitude']] as [$a,$b]) {
        $x=hm_num($r[$a]??null); $y=hm_num($r[$b]??null);
        if ($x!==null && $y!==null && abs($x)<=90 && abs($y)<=180) return [$x,$y];
    }
    return [null,null];
}
function hm_detail_coords(array $h): array {
    $lat=hm_num($h['latitude']??($h['common']['latitude']??null));
    $lon=hm_num($h['longitude']??($h['common']['longitude']??null));
    return [$lat,$lon];
}
function hm_hav(?float $a, ?float $b, ?float $c, ?float $d): ?float {
    if ($a===null||$b===null||$c===null||$d===null) return null;
    $r=6371.0088; $p1=deg2rad($a); $p2=deg2rad($c); $dp=deg2rad($c-$a); $dl=deg2rad($d-$b);
    $x=sin($dp/2)**2 + cos($p1)*cos($p2)*sin($dl/2)**2;
    return round($r*2*atan2(sqrt($x),sqrt(max(0.0,1.0-$x))),3);
}
function hm_operator_anex($v): bool {
    $n=hm_norm(hm_name($v));
    return in_array($n, ['anex','anex tour','anextour','анекс','анекс тур'], true);
}
function hm_link_identity(string $url): ?array {
    $url=trim($url);
    if ($url==='' || strlen($url)>4096) return null;
    $p=parse_url($url);
    if (!is_array($p) || strtolower((string)($p['scheme']??''))!=='https' || isset($p['user']) || isset($p['pass'])) return null;
    $host=strtolower((string)($p['host']??'')); $path=(string)($p['path']??'');
    $field=null; $mode=null;
    if ($host==='agent.anextour.ru' && $path==='/search/tour') { $field='HOTELLIST'; $mode='legacy_hotellist'; }
    elseif ($host==='online.anextour.ru' && in_array($path, ['/search','/search/'], true)) { $field='hotelCode'; $mode='online_hotelcode'; }
    else return null;
    $pairs=[]; parse_str((string)($p['query']??''), $pairs);
    $ids=[];
    foreach ($pairs as $k=>$v) {
        if (strcasecmp((string)$k, $field)!==0 || !is_scalar($v)) continue;
        $id=hm_id($v); if ($id===null) return null; $ids[]=$id;
    }
    if (count($ids)!==1) return null;
    return ['anex_hotel_id'=>$ids[0],'mode'=>$mode,'host'=>$host];
}
function hm_parse_expected(string $raw): array {
    if (trim($raw)==='') throw new RuntimeException('expected_required');
    $out=[]; $tours=[];
    foreach (explode(',', $raw) as $part) {
        if (!preg_match('/^([1-9][0-9]{0,11}):([1-9][0-9]{0,11}):([1-9][0-9]{0,20})$/D', trim($part), $m)) throw new RuntimeException('expected_format');
        $aid=(int)$m[1]; $tv=(int)$m[2]; $tour=(int)$m[3];
        if (isset($out[$aid]) || isset($tours[$tour])) throw new RuntimeException('expected_duplicate');
        $out[$aid]=['anex_hotel_id'=>$aid,'tourvisor_hotel_id'=>$tv,'tour_id'=>$tour];
        $tours[$tour]=true;
    }
    if (count($out)>12) throw new RuntimeException('expected_limit');
    return $out;
}
function hm_select(PDO $db, string $sql, array $params=[]): array {
    $s=$db->prepare($sql); $s->execute($params); return $s->fetchAll(PDO::FETCH_ASSOC);
}
function hm_db_path(string $root): string {
    foreach ([$root.'/data/db-v1.php',$root.'/v2/data/db-v1.php'] as $p) if (is_file($p)) return $p;
    throw new RuntimeException('db_bootstrap_missing');
}
function hm_client_path(string $root): string {
    foreach ([$root.'/data/tourvisor-client-v1.php',$root.'/v2/data/tourvisor-client-v1.php'] as $p) if (is_file($p)) return $p;
    throw new RuntimeException('tourvisor_client_missing');
}

if (in_array('--self-test', $argv??[], true)) {
    $e=hm_parse_expected('4158:37412:13278670760276,37719:132075:13279232318107');
    if (count($e)!==2 || $e[4158]['tourvisor_hotel_id']!==37412) throw new RuntimeException('expected_test');
    $x=hm_link_identity('https://agent.anextour.ru/search/tour?HOTELLIST=4158&ADULT=2');
    if (($x['anex_hotel_id']??0)!==4158 || ($x['mode']??'')!=='legacy_hotellist') throw new RuntimeException('link_test');
    if (hm_semantic('BARCELO TIRAN SHARM HOTEL','BARCELO TIRAN SHARM')['state']!=='corroborated') throw new RuntimeException('semantic_test');
    echo "current-saved-tour-detail self-test: PASS\n"; exit(0);
}

$op=(string)getenv('OPERATION_ID');
$sha=(string)getenv('MATCH_SOURCE_SHA');
$expected=hm_parse_expected((string)getenv('MATCH_EXPECTED_TRIPLES'));
if (!preg_match('/^hotel-match-current-saved-tour-detail-1971-20260914-v1-egypt$/D',$op) || !preg_match('/^[0-9a-f]{40}$/D',$sha)) throw new RuntimeException('identity_guard');

$root=(string)realpath(getcwd());
if ($root==='' || basename($root)!=='anytoour.ru') throw new RuntimeException('root_guard');
$base=rtrim((string)getenv('HOME'),'/').'/.anytoour-match/operations';
$out=$base.'/'.$op;
if (!is_dir($base) || !mkdir($out,0700)) throw new RuntimeException('operation_exists');
hm_once($out.'/reservation.json',[
    'operation_id'=>$op,'source_sha'=>$sha,'state'=>'reserved_before_db_and_supplier_access',
    'credential_identifier'=>'TOURVISOR_JWT','expected_count'=>count($expected),
    'expected_sha256'=>hash('sha256',hm_json($expected)),'read_only'=>true,
    'search_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'booking_calls'=>0,'lead_calls'=>0,'no_replay'=>true
]);

$calls=0; $rows=[]; $skipped=[]; $blocked=[];
try {
    require_once hm_db_path($root);
    $db=v2_data_db(); $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');

    $mapped=[]; foreach(hm_select($db,'SELECT anex_hotel_id,catalog_hotel_id FROM anex_hotel_search_mappings') as $r) $mapped[(int)$r['anex_hotel_id']]=(int)$r['catalog_hotel_id'];
    $manual=array_fill_keys(array_map('intval',array_column(hm_select($db,'SELECT anex_hotel_id FROM anex_hotel_decisions'),'anex_hotel_id')),true);
    $excl=[]; foreach(hm_select($db,'SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions') as $r) $excl[(int)$r['anex_hotel_id']][(int)$r['catalog_hotel_id']]=true;
    $obs=[]; foreach(hm_select($db,'SELECT * FROM anex_search_hotel_observations') as $r){$aid=(int)($r['anex_hotel_id']??0);if($aid>0)$obs[$aid]=$r;}
    $stage=[]; foreach(hm_select($db,'SELECT * FROM anex_hotels') as $r){$aid=(int)($r['anex_hotel_id']??0);if($aid>0)$stage[$aid]=$r;}
    $catalog=[]; foreach(hm_select($db,'SELECT id,country_id,name,latitude,longitude,is_active FROM catalog_hotels WHERE country_id=1') as $r)$catalog[(int)$r['id']]=$r;

    $current=[];
    foreach($expected as $aid=>$x){
        $tv=$x['tourvisor_hotel_id'];
        $reason=null;
        if(isset($mapped[$aid])) $reason='current_mapping_present';
        elseif(isset($manual[$aid])) $reason='manual_protected';
        elseif(isset($excl[$aid][$tv])) $reason='pair_excluded';
        elseif(!isset($catalog[$tv]) || (int)($catalog[$tv]['is_active']??0)!==1) $reason='target_missing_or_inactive';
        elseif(!isset($obs[$aid]) && !isset($stage[$aid])) $reason='source_row_missing';
        if($reason!==null){$skipped[]=$x+['reason'=>$reason];continue;}
        $current[$aid]=$x;
    }
    $db->exec('ROLLBACK');

    if(!$current) throw new RuntimeException('no_current_unresolved_expected');
    require_once hm_client_path($root);
    if (!function_exists('v2_data_tv_get')) throw new RuntimeException('tourvisor_client_contract_missing');

    foreach($current as $aid=>$x){
        if($calls>0) sleep(5);
        try{
            $calls++;
            $d=v2_data_tv_get('/tours/'.$x['tour_id'],['currency'=>'RUB']);
        }catch(Throwable $e){
            $m=$e->getMessage();
            $reason=str_contains($m,'429')?'http_429':(str_contains($m,'401')?'http_401':(str_contains($m,'403')?'http_403':(str_contains($m,'404')?'http_404':'detail_error')));
            $blocked[]=$x+['reason'=>$reason];
            if($reason==='http_429') break;
            continue;
        }
        $h=is_array($d['hotel']??null)?$d['hotel']:[];
        $tv=hm_id($h['id']??null); $hname=hm_name($h); $opname=hm_name($d['operator']??null);
        $link=hm_text($d['operatorLink']??'',4096); $identity=$link!==''?hm_link_identity($link):null;
        $src=$obs[$aid]??$stage[$aid]??[];
        $srcName=hm_text($src['hotel_name']??$src['api_name']??$src['xml_name']??'');
        $sem=hm_semantic($srcName,$hname);
        $qConflict=hm_quals($srcName)!==hm_quals($hname);
        $nConflict=hm_nums($srcName)!==hm_nums($hname) && (hm_nums($srcName)||hm_nums($hname));
        [$slat,$slon]=hm_coords($src); [$tlat,$tlon]=hm_detail_coords($h); $dist=hm_hav($slat,$slon,$tlat,$tlon);
        $reason='direct_identity_confirmed'; $tier='DIRECT';
        if($tv!==$x['tourvisor_hotel_id']){$tier='HOLD';$reason='tourvisor_hotel_id_mismatch';}
        elseif(!hm_operator_anex($d['operator']??null)){$tier='HOLD';$reason='detail_operator_not_anex';}
        elseif($identity===null){$tier='HOLD';$reason='operator_link_identity_missing';}
        elseif((int)$identity['anex_hotel_id']!==$aid){$tier='HOLD';$reason='operator_link_anex_id_mismatch';}
        elseif($srcName==='' || $sem['state']!=='corroborated'){$tier='HOLD';$reason='name_not_corroborated';}
        elseif($qConflict){$tier='HOLD';$reason='qualifier_conflict';}
        elseif($nConflict){$tier='HOLD';$reason='numeric_conflict';}
        elseif($dist!==null && $dist>5.0){$tier='HOLD';$reason='coordinate_conflict_gt5km';}
        $rows[]=[
            'expected_anex_hotel_id'=>$aid,'expected_tourvisor_hotel_id'=>$x['tourvisor_hotel_id'],'tour_id'=>(string)$x['tour_id'],
            'detail_tourvisor_hotel_id'=>$tv,'detail_hotel_name'=>$hname,'operator_name'=>$opname,
            'operator_link_present'=>$link!=='','operator_identity'=>$identity,'source_name'=>$srcName?:null,
            'semantic'=>$sem,'qualifier_conflict'=>$qConflict,'numeric_conflict'=>$nConflict,'distance_km'=>$dist,
            'live_search_count'=>(int)($obs[$aid]['search_count']??0),'tier'=>$tier,'reason'=>$reason
        ];
    }
    $direct=count(array_filter($rows,static fn($r)=>($r['tier']??'')==='DIRECT'));
    $holds=count(array_filter($rows,static fn($r)=>($r['tier']??'')==='HOLD'));
    $res=[
        'operation_id'=>$op,'source_sha'=>$sha,'status'=>'completed','credential_identifier'=>'TOURVISOR_JWT',
        'expected_count'=>count($expected),'current_unresolved_count'=>count($current),'skipped_current_count'=>count($skipped),
        'tourvisor_detail_calls'=>$calls,'search_calls'=>0,'detail_rows'=>count($rows),'direct_confirmed'=>$direct,'holds'=>$holds,
        'skipped_current'=>$skipped,'blocked_rows'=>$blocked,'rows'=>$rows,
        'database_writes'=>0,'mapping_writes'=>0,'booking_calls'=>0,'lead_calls'=>0,
        'raw_provider_bodies_recorded'=>false,'raw_operator_links_recorded'=>false,'token_values_recorded'=>false,'no_replay'=>true
    ];
}catch(Throwable $e){
    try{if(isset($db)&&$db instanceof PDO&&$db->inTransaction())$db->exec('ROLLBACK');}catch(Throwable $x){}
    $allowed=['no_current_unresolved_expected','db_bootstrap_missing','tourvisor_client_missing','tourvisor_client_contract_missing'];
    $reason=in_array($e->getMessage(),$allowed,true)?$e->getMessage():'diagnostic_error';
    $res=['operation_id'=>$op,'source_sha'=>$sha,'status'=>'blocked','reason'=>$reason,'tourvisor_detail_calls'=>$calls,'search_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'booking_calls'=>0,'lead_calls'=>0,'no_replay'=>true];
}
$rh=hm_once($out.'/result.json',$res);
hm_once($out.'/receipt.json',['operation_id'=>$op,'source_sha'=>$sha,'state'=>$res['status'],'result_sha256'=>$rh,'readback_verified'=>hash('sha256',(string)file_get_contents($out.'/result.json'))===$rh,'database_writes'=>0,'mapping_writes'=>0,'no_replay'=>true]);
echo HM_MARK.hm_json($res)."\n";
exit($res['status']==='completed'?0:2);
