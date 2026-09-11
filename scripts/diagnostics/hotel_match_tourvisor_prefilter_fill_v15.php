<?php
declare(strict_types=1);

const V15_OPERATION = 'hotel-match-tourvisor-prefilter-fill-1971-20260911-v15';
const V15_COUNTRY = 1;
const V15_DATE = '2026-10-16';
const V15_NIGHTS = 7;
const V15_TV_OPERATOR = 13;

function v15_rows(array $data): array {
    if (array_is_list($data)) return array_values(array_filter($data, 'is_array'));
    foreach (['hotels','items','results','data'] as $k) {
        if (is_array($data[$k] ?? null)) {
            $rows = v15_rows($data[$k]);
            if ($rows !== []) return $rows;
        }
    }
    return [];
}

function v15_complete(array $data): bool {
    if ((int)($data['progress'] ?? 0) >= 100) return true;
    if (in_array(strtolower(trim((string)($data['status'] ?? ''))), ['complete','completed','done','ready'], true)) return true;
    foreach ($data as $v) if (is_array($v) && v15_complete($v)) return true;
    return false;
}

function v15_text($v, int $max=180): string {
    if (!is_string($v)) return '';
    $v = trim((string)(preg_replace('/\s+/u', ' ', $v) ?? ''));
    return mb_substr($v, 0, $max, 'UTF-8');
}

function v15_keys(string $name): array {
    $variants = [$name, (string)(preg_replace('/\s*\([^)]*\)\s*/u', ' ', $name) ?? $name)];
    if (preg_match_all('/\((?:EX\.?\s*)?([^)]{2,100})\)/iu', $name, $m)) {
        foreach ($m[1] as $x) $variants[] = trim((string)$x);
    }
    $out = [];
    foreach ($variants as $variant) {
        $tokens = array_values(array_filter(explode(' ', fc_norm($variant)), static fn($x) => $x !== '' && !in_array($x, ['hotel','hotels','отель','отели','resort','resorts','резорт','ресорт','spa','спа'], true)));
        if ($tokens === []) continue;
        $key = implode(' ', $tokens);
        $out[$key] = ['key'=>$key, 'tokens'=>count($tokens)];
    }
    return array_values($out);
}

function v15_tv_hotels(array $payload): array {
    $out = [];
    foreach (v15_rows($payload) as $row) {
        $id = filter_var($row['id'] ?? null, FILTER_VALIDATE_INT);
        $name = v15_text($row['name'] ?? '');
        if ($id === false || (int)$id <= 0 || $name === '') continue;
        $keys = v15_keys($name);
        if ($keys === []) continue;
        $out[(int)$id] = ['id'=>(int)$id, 'name'=>$name, 'keys'=>$keys];
        if (count($out) > 5000) throw new RuntimeException('tourvisor_hotel_cap');
    }
    return $out;
}

function v15_tv_fetch(): array {
    if (!defined('TOURVISOR_ANEX_JWT')) throw new RuntimeException('tourvisor_credential_missing');
    $token = trim((string)constant('TOURVISOR_ANEX_JWT'));
    if (stripos($token, 'Bearer ') === 0) $token = trim(substr($token, 7));
    if ($token === '') throw new RuntimeException('tourvisor_credential_empty');
    putenv('TOURVISOR_JWT='.$token);
    try {
        $start = v2_data_tv_get('/tours/search', [
            'departureId'=>1,'countryId'=>V15_COUNTRY,'dateFrom'=>V15_DATE,'dateTo'=>V15_DATE,
            'nightsFrom'=>V15_NIGHTS,'nightsTo'=>V15_NIGHTS,'adults'=>2,'currency'=>'RUB',
            'onlyCharter'=>false,'onlyDirect'=>false,'operatorIds'=>[V15_TV_OPERATOR],
        ]);
    } catch (Throwable $e) {
        $message = v15_text($e->getMessage(), 160);
        if (str_contains($message, '429')) throw new RuntimeException('tourvisor_rate_limited_429');
        throw $e;
    }
    $searchId = (int)($start['searchId'] ?? $start['id'] ?? 0);
    if ($searchId <= 0) throw new RuntimeException('tourvisor_search_id_missing');
    $polls = 0;
    for ($i=1; $i<=24; $i++) {
        $polls = $i;
        if ($i > 1) usleep(1200000);
        $status = v2_data_tv_get('/tours/search/'.$searchId.'/status', ['operatorStatus'=>false]);
        if (v15_complete($status)) break;
        if ($i === 24) throw new RuntimeException('tourvisor_search_timeout');
    }
    $payload = v2_data_tv_get('/tours/search/'.$searchId, ['limit'=>10000]);
    return ['search_id'=>$searchId,'status_polls'=>$polls,'hotels'=>v15_tv_hotels($payload)];
}

function v15_match_source(array $sourceNames, array $tvIndex): array {
    $targets = [];
    $maxTokens = 0;
    foreach ($sourceNames as $name) {
        foreach (v15_keys((string)$name) as $k) {
            $maxTokens = max($maxTokens, (int)$k['tokens']);
            foreach (array_keys($tvIndex[$k['key']] ?? []) as $id) $targets[(int)$id] = true;
        }
    }
    return ['target_ids'=>array_keys($targets), 'max_tokens'=>$maxTokens];
}

function v15_review(PDO $db): array {
    if (V15_OPERATION !== 'hotel-match-tourvisor-prefilter-fill-1971-20260911-v15') throw new RuntimeException('operation_scope');
    $tvResult = v15_tv_fetch();
    $tv = $tvResult['hotels'];
    $tvIndex = [];
    foreach ($tv as $id=>$hotel) foreach ($hotel['keys'] as $k) $tvIndex[$k['key']][$id] = true;

    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $db->exec('START TRANSACTION READ ONLY');
    try {
        [$hotels,$names,$strict,$broad,$places,$catalogScope] = mbr_catalog($db);
        $coverage = fc_coverage($db);
        $manual = array_fill_keys(array_map('intval', $db->query('SELECT anex_hotel_id FROM anex_hotel_decisions')->fetchAll(PDO::FETCH_COLUMN)), true);
        $existing = array_fill_keys(array_map('intval', $db->query('SELECT anex_hotel_id FROM anex_hotel_search_mappings')->fetchAll(PDO::FETCH_COLUMN)), true);
        $excluded = [];
        foreach ($db->query('SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions')->fetchAll(PDO::FETCH_ASSOC) as $x) $excluded[(int)$x['anex_hotel_id']][(int)$x['catalog_hotel_id']] = true;
        $staging = [];
        foreach ($db->query('SELECT * FROM anex_hotels ORDER BY anex_hotel_id')->fetchAll(PDO::FETCH_ASSOC) as $s) $staging[(int)$s['anex_hotel_id']] = $s;

        $anex = [];
        $seen = [];
        $obs = $db->query('SELECT * FROM anex_search_hotel_observations WHERE country_id=1 ORDER BY search_count DESC,last_seen_utc DESC,anex_hotel_id')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($obs as $o) {
            $id=(int)$o['anex_hotel_id']; $seen[$id]=true;
            if (isset($manual[$id]) || isset($existing[$id])) continue;
            $s=$staging[$id]??[];
            $names0=[$o['hotel_name']??'',$s['api_name']??'',$s['xml_name']??'',$s['xml_alternate_name']??''];
            $m=v15_match_source($names0,$tvIndex); if (count($m['target_ids'])!==1) continue;
            $target=(int)$m['target_ids'][0]; if (!isset($hotels[$target]) || (int)$hotels[$target]['country_id']!==V15_COUNTRY) continue;
            if (isset($excluded[$id][$target])) continue;
            $source=['latitude'=>$s['latitude']??null,'longitude'=>$s['longitude']??null];
            $guard=mbr_target_guard($source,$hotels[$target]);
            $place=fc_place([$s['api_region']??'',$s['api_town']??''],[(string)$hotels[$target]['region_name'],(string)$hotels[$target]['subregion_name']]);
            $directGeo=($guard['distance_m']!==null && (int)$guard['distance_m']<=1000)||$place;
            $safe=!$guard['coordinate_conflict'] && ($m['max_tokens']>=2 || $directGeo);
            $anex[]=['provider'=>'anex','external_id'=>$id,'source_name'=>(string)($o['hotel_name']??$s['api_name']??''),'search_count'=>(int)($o['search_count']??0),'target_local_id'=>$target,'target_name'=>$tv[$target]['name'],'max_tokens'=>$m['max_tokens'],'direct_geo'=>$directGeo,'distance_m'=>$guard['distance_m'],'safe'=>$safe,'reason'=>$safe?'tourvisor_anex_only_exact_alias':'needs_geo_or_conflict'];
        }
        foreach ($staging as $id=>$s) {
            if (isset($seen[$id]) || isset($manual[$id]) || isset($existing[$id])) continue;
            if (fc_country($s['api_country']??'')!==V15_COUNTRY) continue;
            $m=v15_match_source([$s['api_name']??'',$s['xml_name']??'',$s['xml_alternate_name']??''],$tvIndex); if (count($m['target_ids'])!==1) continue;
            $target=(int)$m['target_ids'][0]; if (!isset($hotels[$target]) || isset($excluded[$id][$target])) continue;
            $guard=mbr_target_guard(['latitude'=>$s['latitude']??null,'longitude'=>$s['longitude']??null],$hotels[$target]);
            $place=fc_place([$s['api_region']??'',$s['api_town']??''],[(string)$hotels[$target]['region_name'],(string)$hotels[$target]['subregion_name']]);
            $directGeo=($guard['distance_m']!==null && (int)$guard['distance_m']<=1000)||$place;
            $safe=!$guard['coordinate_conflict'] && ($m['max_tokens']>=2 || $directGeo);
            $anex[]=['provider'=>'anex','external_id'=>(int)$id,'source_name'=>(string)($s['api_name']??$s['xml_name']??''),'search_count'=>0,'target_local_id'=>$target,'target_name'=>$tv[$target]['name'],'max_tokens'=>$m['max_tokens'],'direct_geo'=>$directGeo,'distance_m'=>$guard['distance_m'],'safe'=>$safe,'reason'=>$safe?'tourvisor_anex_only_exact_alias':'needs_geo_or_conflict'];
        }

        $latest=[];
        foreach ($db->query("SELECT * FROM andromeda_search_hotel_observations WHERE supplier_namespace='andromeda_catalog' AND country_id=1 ORDER BY observed_at_utc DESC,external_hotel_id")->fetchAll(PDO::FETCH_ASSOC) as $o) {
            $external=(string)$o['external_hotel_id']; if (!isset($latest[$external])) $latest[$external]=$o;
        }
        $andromeda=[];
        $pending=$db->query("SELECT * FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='pending' AND local_hotel_id IS NULL ORDER BY external_hotel_id")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($pending as $r) {
            $external=(string)$r['external_hotel_id']; $o=$latest[$external]??null; if (!$o) continue;
            $prior=fc_evidence($r['evidence_json']??''); $src=$prior['source']??[]; if (!is_array($src)) $src=[];
            $sourceNames=[$o['hotel_name']??'',$src['name']??'',$src['lName']??''];
            $m=v15_match_source($sourceNames,$tvIndex); if (count($m['target_ids'])!==1) continue;
            $target=(int)$m['target_ids'][0]; if (!isset($hotels[$target]) || (int)$hotels[$target]['country_id']!==V15_COUNTRY) continue;
            $coord=$src; $coord += $o;
            $guard=mbr_target_guard($coord,$hotels[$target]);
            $place=fc_place([$o['region_name']??'',$o['subregion_name']??'',$src['region']??'',$src['subregion']??''],[(string)$hotels[$target]['region_name'],(string)$hotels[$target]['subregion_name']]);
            $directGeo=($guard['distance_m']!==null && (int)$guard['distance_m']<=1000)||$place;
            $safe=!$guard['coordinate_conflict'] && ($m['max_tokens']>=2 || $directGeo);
            $andromeda[]=['provider'=>'andromeda','external_id'=>$external,'source_name'=>(string)($o['hotel_name']??$src['name']??''),'target_local_id'=>$target,'target_name'=>$tv[$target]['name'],'max_tokens'=>$m['max_tokens'],'direct_geo'=>$directGeo,'distance_m'=>$guard['distance_m'],'safe'=>$safe,'reason'=>$safe?'tourvisor_anex_only_exact_alias':'needs_geo_or_conflict'];
        }

        usort($anex, static fn($a,$b)=>($b['safe']<=>$a['safe']) ?: ($b['search_count']<=>$a['search_count']) ?: ($a['external_id']<=>$b['external_id']));
        usort($andromeda, static fn($a,$b)=>($b['safe']<=>$a['safe']) ?: strcmp((string)$a['external_id'],(string)$b['external_id']));
        $safeAnex=array_values(array_filter($anex,static fn($x)=>$x['safe']));
        $safeAndr=array_values(array_filter($andromeda,static fn($x)=>$x['safe']));
        $db->commit();
        return [
            'status'=>'completed','operation_id'=>V15_OPERATION,
            'criteria'=>['departure'=>'Moscow','country_id'=>V15_COUNTRY,'country'=>'Egypt','date'=>V15_DATE,'nights'=>V15_NIGHTS,'operator_id'=>V15_TV_OPERATOR],
            'tourvisor'=>['hotel_count'=>count($tv),'status_polls'=>$tvResult['status_polls']],
            'counts'=>['anex_candidates'=>count($anex),'andromeda_candidates'=>count($andromeda),'safe_anex'=>count($safeAnex),'safe_andromeda'=>count($safeAndr),'safe_total'=>count($safeAnex)+count($safeAndr)],
            'safe_candidates'=>array_slice(array_merge($safeAnex,$safeAndr),0,300),
            'all_candidates_sample'=>array_slice(array_merge($anex,$andromeda),0,80),
            'coverage'=>$coverage,
            'database_writes'=>0,'mapping_writes'=>0,'booking_calls'=>0,'lead_calls'=>0,'tourvisor_search_starts'=>1,'tourvisor_continue_calls'=>0,
        ];
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
}
