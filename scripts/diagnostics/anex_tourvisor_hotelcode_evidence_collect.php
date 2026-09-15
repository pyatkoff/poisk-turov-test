<?php
declare(strict_types=1);
if (!defined('FC_LIBRARY_ONLY')) define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/hotel_match_current_bulk_review.php';
require_once __DIR__ . '/anex_hotelcode_evidence.php';

const ATE_OPERATION = 'hotel-match-anex-tourvisor-hotelcode-evidence-1971-20260911-v1';
const ATE_CORE8 = [1=>true,2=>true,4=>true,8=>true,9=>true,10=>true,12=>true,16=>true];

function ate_allowed_anex_host(string $host): bool {
    $host = strtolower(trim($host, '.'));
    return $host === 'anextour.ru' || str_ends_with($host, '.anextour.ru');
}
function ate_allowed_anex_page(string $url): bool {
    $p = parse_url($url);
    if (!is_array($p) || strtolower((string)($p['scheme'] ?? '')) !== 'https') return false;
    if (($p['user'] ?? '') !== '' || ($p['pass'] ?? '') !== '' || isset($p['port'])) return false;
    return ate_allowed_anex_host((string)($p['host'] ?? ''));
}
function ate_operator_hotellist(string $url): ?int {
    if (!ate_allowed_anex_page($url)) return null;
    $q = [];
    parse_str((string)(parse_url($url, PHP_URL_QUERY) ?? ''), $q);
    foreach (['HOTELLIST','hotellist','hotelList'] as $k) {
        if (!isset($q[$k]) || is_array($q[$k])) continue;
        $v = trim((string)$q[$k]);
        if (preg_match('/^\d{1,10}$/D', $v)) return (int)$v;
    }
    return null;
}
function ate_media_urls_from_html(string $html): array {
    $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $out = [];
    if (preg_match_all('~https://files\.anextour\.ru/[^\s"\'<>]+~iu', $html, $m)) {
        foreach ($m[0] as $url) {
            $url = rtrim($url, '.,);]');
            $out[$url] = true;
            if (count($out) >= 80) break;
        }
    }
    $urls = array_keys($out); sort($urls, SORT_STRING); return $urls;
}
function ate_page_hotelcode_evidence(string $html): array {
    return anytour_anex_hotelcode_evidence(ate_media_urls_from_html($html));
}
function ate_tv_rows(array $payload): array {
    if (array_is_list($payload)) return array_values(array_filter($payload, 'is_array'));
    foreach (['hotels','items','results'] as $k) if (is_array($payload[$k] ?? null)) return array_values(array_filter($payload[$k], 'is_array'));
    return [];
}
function ate_search_id(array $payload): ?int {
    foreach (['searchId','id'] as $k) {
        $v = filter_var($payload[$k] ?? null, FILTER_VALIDATE_INT);
        if ($v !== false && (int)$v > 0) return (int)$v;
    }
    return null;
}
function ate_search_complete(array $payload): bool {
    return (int)($payload['progress'] ?? 0) >= 100 || in_array(strtolower(trim((string)($payload['status'] ?? ''))), ['complete','completed','done'], true);
}
function ate_operator_id(array $rows): ?int {
    foreach ($rows as $row) {
        if (!is_array($row)) continue;
        $text = fc_norm(implode(' ', [(string)($row['name'] ?? ''),(string)($row['russianName'] ?? ''),(string)($row['fullName'] ?? '')]));
        if ($text !== '' && (str_contains($text, 'anex') || str_contains($text, 'анекс'))) {
            $id = filter_var($row['id'] ?? null, FILTER_VALIDATE_INT);
            if ($id !== false && (int)$id > 0) return (int)$id;
        }
    }
    return null;
}
function ate_departure_id(array $rows): ?int {
    foreach ($rows as $row) {
        if (!is_array($row)) continue;
        $name = fc_norm((string)($row['name'] ?? ''));
        if ($name === 'москва' || $name === 'moscow') {
            $id = filter_var($row['id'] ?? null, FILTER_VALIDATE_INT);
            if ($id !== false && (int)$id > 0) return (int)$id;
        }
    }
    return null;
}
function ate_pick_date(array $rows, ?DateTimeImmutable $today = null): ?string {
    $today ??= new DateTimeImmutable('today');
    $floor = $today->format('Y-m-d'); $dates = [];
    foreach ($rows as $v) {
        if (!is_string($v)) continue;
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', trim($v));
        if ($d && $d->format('Y-m-d') >= $floor) $dates[$d->format('Y-m-d')] = true;
    }
    if (!$dates) return null; ksort($dates, SORT_STRING); return (string)array_key_first($dates);
}
function ate_seed_keyset(array $seed, bool $broad = false): array {
    $out = [];
    foreach (($seed['source_names'] ?? []) as $name) {
        $k = fc_key((string)$name, $broad, $broad);
        if ($k !== '') $out[$k] = true;
    }
    return $out;
}
function ate_tv_places(array $row): array {
    $out = [];
    foreach (['region','subRegion'] as $k) {
        if (is_array($row[$k] ?? null)) $out[] = (string)($row[$k]['name'] ?? '');
    }
    return $out;
}
function ate_match_tv_hotel(array $row, array $seeds): ?array {
    $tvId = filter_var($row['id'] ?? null, FILTER_VALIDATE_INT);
    $country = is_array($row['country'] ?? null) ? (int)($row['country']['id'] ?? 0) : 0;
    $name = trim((string)($row['name'] ?? ''));
    if ($tvId === false || (int)$tvId <= 0 || !isset(ATE_CORE8[$country]) || $name === '') return null;
    $strictKey = fc_key($name, false, false); $broadKey = fc_key($name, true, true);
    $strict = []; $broad = [];
    foreach ($seeds as $seed) {
        if ((int)($seed['country_id'] ?? 0) !== $country) continue;
        if ($strictKey !== '' && isset(ate_seed_keyset($seed, false)[$strictKey])) $strict[] = $seed;
        if ($broadKey !== '' && isset(ate_seed_keyset($seed, true)[$broadKey])) $broad[] = $seed;
    }
    $pick = null; $rule = null;
    if (count($strict) === 1) { $pick = $strict[0]; $rule = 'exact_normalized_name'; }
    elseif (count($strict) > 1) {
        $near = [];
        foreach ($strict as $seed) {
            $d = fc_dist($seed['latitude'] ?? null, $seed['longitude'] ?? null, $row['latitude'] ?? null, $row['longitude'] ?? null);
            if ($d !== null && $d <= 1000) $near[] = [$seed,$d];
        }
        if (count($near) === 1) { $pick = $near[0][0]; $rule = 'exact_name_coordinate'; }
    }
    if ($pick === null && count($broad) === 1) {
        $seed = $broad[0];
        $d = fc_dist($seed['latitude'] ?? null, $seed['longitude'] ?? null, $row['latitude'] ?? null, $row['longitude'] ?? null);
        $place = fc_place($seed['source_places'] ?? [], ate_tv_places($row));
        if (($d !== null && $d <= 5000) || $place) { $pick = $seed; $rule = 'generic_name_geo'; }
    }
    if ($pick === null) return null;
    $distance = fc_dist($pick['latitude'] ?? null, $pick['longitude'] ?? null, $row['latitude'] ?? null, $row['longitude'] ?? null);
    if ($distance !== null && $distance > 5000) return null;
    return [
        'anex_hotel_id'=>(int)$pick['anex_hotel_id'],'country_id'=>$country,'search_count'=>(int)($pick['search_count'] ?? 0),'observed'=>(bool)($pick['observed'] ?? false),
        'tv_hotel_id'=>(int)$tvId,'tv_hotel_name'=>$name,'match_rule'=>$rule,'distance_m'=>$distance,
        'source_names'=>array_values($pick['source_names'] ?? []),'source_places'=>array_values($pick['source_places'] ?? []),
    ];
}
function ate_first_anex_tour_id(array $hotel, int $operatorId): ?string {
    foreach (($hotel['tours'] ?? []) as $tour) {
        if (!is_array($tour)) continue;
        $op = is_array($tour['operator'] ?? null) ? (int)($tour['operator']['id'] ?? 0) : 0;
        $id = trim((string)($tour['id'] ?? ''));
        if ($op === $operatorId && $id !== '' && strlen($id) <= 200) return $id;
    }
    return null;
}
function ate_fetch_anex_page(string $url, int $maxRedirects = 3): array {
    if (!ate_allowed_anex_page($url)) return ['status'=>'blocked_url','url'=>$url,'http_status'=>0,'body'=>''];
    $current = $url;
    for ($i=0; $i <= $maxRedirects; $i++) {
        $headers = [];
        $ch = curl_init($current);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>25,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_USERAGENT=>'AnyTour-MATCH-evidence/1.0',CURLOPT_HTTPHEADER=>['Accept: text/html,application/xhtml+xml'],CURLOPT_HEADERFUNCTION=>static function($c,string $h) use (&$headers): int {$headers[]=$h;return strlen($h);}]);
        $body = curl_exec($ch); $errno = curl_errno($ch); $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        if ($errno !== 0 || !is_string($body)) return ['status'=>'fetch_error','url'=>$current,'http_status'=>$status,'body'=>''];
        if ($status >= 300 && $status < 400) {
            $location = null;
            foreach ($headers as $h) if (stripos($h,'Location:')===0) $location=trim(substr($h,9));
            if ($location === null || $location === '') return ['status'=>'redirect_without_location','url'=>$current,'http_status'=>$status,'body'=>''];
            if (!str_starts_with($location,'http')) {
                $p=parse_url($current); $base=(string)($p['scheme']??'https').'://'.(string)($p['host']??'');
                $location=$base.'/'.ltrim($location,'/');
            }
            if (!ate_allowed_anex_page($location)) return ['status'=>'blocked_redirect','url'=>$current,'redirect'=>$location,'http_status'=>$status,'body'=>''];
            $current=$location; continue;
        }
        return ['status'=>($status>=200&&$status<300?'ok':'http_error'),'url'=>$current,'http_status'=>$status,'body'=>$body];
    }
    return ['status'=>'redirect_limit','url'=>$current,'http_status'=>0,'body'=>''];
}
function ate_current_seeds(PDO $db): array {
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $db->exec('START TRANSACTION READ ONLY');
    try {
        [$hotels,$names,$strict,$broad,$places] = mbr_catalog($db);
        $manual=array_fill_keys(array_map('intval',$db->query('SELECT anex_hotel_id FROM anex_hotel_decisions')->fetchAll(PDO::FETCH_COLUMN)),true);
        $existing=array_fill_keys(array_map('intval',$db->query('SELECT anex_hotel_id FROM anex_hotel_search_mappings')->fetchAll(PDO::FETCH_COLUMN)),true);
        $excluded=[]; foreach($db->query('SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions')->fetchAll(PDO::FETCH_ASSOC) as $x)$excluded[(int)$x['anex_hotel_id']][(int)$x['catalog_hotel_id']]=true;
        $staging=[]; foreach($db->query('SELECT * FROM anex_hotels ORDER BY anex_hotel_id')->fetchAll(PDO::FETCH_ASSOC) as $s)$staging[(int)$s['anex_hotel_id']]=$s;
        $obs=$db->query('SELECT * FROM anex_search_hotel_observations ORDER BY search_count DESC,last_seen_utc DESC,anex_hotel_id')->fetchAll(PDO::FETCH_ASSOC);
        $seeds=[];$seen=[];
        foreach($obs as $o){$id=(int)$o['anex_hotel_id'];$country=(int)$o['country_id'];if(!isset(ATE_CORE8[$country])||isset($seen[$id]))continue;$seen[$id]=true;if(isset($manual[$id])||isset($existing[$id]))continue;$s=$staging[$id]??[];$source=['observed'=>true,'search_count'=>(int)$o['search_count'],'last_seen_utc'=>$o['last_seen_utc'],'names'=>[$o['hotel_name'],$s['api_name']??'',$s['xml_name']??'',$s['xml_alternate_name']??''],'places'=>[$s['api_region']??'',$s['api_town']??''],'latitude'=>$s['latitude']??null,'longitude'=>$s['longitude']??null];$r=mbr_review_anex($source,$id,$country,$hotels,$names,$strict,$broad,$places);$target=(int)($r['target']['local_hotel_id']??0);if($target&&isset($excluded[$id][$target]))continue;if(($r['bucket']??'')==='auto_accept')continue;$seeds[]=['anex_hotel_id'=>$id,'country_id'=>$country,'observed'=>true,'search_count'=>(int)$o['search_count'],'last_seen_utc'=>$o['last_seen_utc'],'source_names'=>array_values(array_filter($source['names'],static fn($x)=>trim((string)$x)!=='')),'source_places'=>array_values(array_filter($source['places'],static fn($x)=>trim((string)$x)!=='')),'latitude'=>$s['latitude']??null,'longitude'=>$s['longitude']??null];}
        foreach($staging as $id=>$s){if(isset($seen[$id])||isset($manual[$id])||isset($existing[$id]))continue;$country=fc_country($s['api_country']??'');if(!$country||!isset(ATE_CORE8[$country]))continue;$source=['observed'=>false,'search_count'=>0,'names'=>[$s['api_name']??'',$s['xml_name']??'',$s['xml_alternate_name']??''],'places'=>[$s['api_region']??'',$s['api_town']??''],'latitude'=>$s['latitude']??null,'longitude'=>$s['longitude']??null];$r=mbr_review_anex($source,(int)$id,$country,$hotels,$names,$strict,$broad,$places);$target=(int)($r['target']['local_hotel_id']??0);if($target&&isset($excluded[(int)$id][$target]))continue;if(($r['bucket']??'')==='auto_accept')continue;$seeds[]=['anex_hotel_id'=>(int)$id,'country_id'=>$country,'observed'=>false,'search_count'=>0,'last_seen_utc'=>null,'source_names'=>array_values(array_filter($source['names'],static fn($x)=>trim((string)$x)!=='')),'source_places'=>array_values(array_filter($source['places'],static fn($x)=>trim((string)$x)!=='')),'latitude'=>$s['latitude']??null,'longitude'=>$s['longitude']??null];}
        usort($seeds,static fn($a,$b)=>(($a['observed']?0:1)<=>($b['observed']?0:1))?:(((int)$b['search_count'])<=>((int)$a['search_count']))?:(((int)$a['anex_hotel_id'])<=>((int)$b['anex_hotel_id'])));
        $coverage=fc_coverage($db);$db->commit();return ['seeds'=>$seeds,'coverage'=>$coverage];
    } catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    fwrite(STDERR, "library_only: use guarded workflow with server DB and canonical Tourvisor client\n"); exit(64);
}
