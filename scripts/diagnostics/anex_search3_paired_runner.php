<?php
/** Fixed, one-case CLI experiment. Only successful ANEX hotel sightings are written. */

function anex_paired_input($input): array
{
    $keys = ['experiment_id','case_id','date','nights','adults','currency'];
    if (!is_array($input) || count($input) !== count($keys) || array_diff($keys, array_keys($input))
        || ($input['experiment_id'] ?? null) !== 'one_day_anex_20260908'
        || !in_array($input['case_id'] ?? null, ['tv_day','tv_week','anex_day'], true)
        || $input['date'] !== '2026-09-16' || $input['nights'] !== 7
        || $input['adults'] !== 2 || $input['currency'] !== 'RUB') {
        throw new RuntimeException('PAIRED_INVALID_INPUT');
    }
    return $input;
}

function anex_paired_text($value, array $secrets = [], int $limit = 180): ?string
{
    if (is_array($value)) {
        foreach (['russianName','fullRussianName','name','title','value'] as $key) {
            if (isset($value[$key]) && is_scalar($value[$key])) return anex_paired_text($value[$key], $secrets, $limit);
        }
        return null;
    }
    if (!is_string($value) || strlen($value) > 4096 || !preg_match('//u', $value)) return null;
    for ($round = 0; $round < 8; ++$round) {
        foreach ($secrets as $secret) if ($secret !== '' && strpos($value, $secret) !== false) return null;
        if (preg_match('~https?://|www\.|oauth_token|authorization|bearer\s|eyJ[A-Za-z0-9_-]{12,}|0x[0-9a-f]{40,}~i', $value)) return null;
        $decoded = rawurldecode(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($decoded === $value) break;
        $value = $decoded;
        if ($round === 7) return null;
    }
    $value = trim((string) preg_replace('/[\p{Cc}\p{Cf}]/u', '', strip_tags($value)));
    preg_match('/\A.{1,' . $limit . '}/us', $value, $match);
    return $match[0] ?? null;
}

function anex_paired_id($value): ?int
{
    return (is_int($value) || is_string($value)) && preg_match('/\A[1-9][0-9]{0,9}\z/D', (string)$value)
        ? (int)$value : null;
}

function anex_paired_date($value): ?string
{
    if (!is_string($value)) return null;
    foreach (['Y-m-d','d.m.Y','Ymd'] as $format) {
        $date = DateTimeImmutable::createFromFormat('!' . $format, $value, new DateTimeZone('UTC'));
        if ($date && $date->format($format) === $value) return $date->format('Y-m-d');
    }
    return null;
}

function anex_paired_price($value): ?string
{
    if (is_int($value) || (is_float($value) && is_finite($value))) $value = (string)$value;
    return is_string($value) && preg_match('/\A(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?\z/D', $value)
        && preg_match('/[1-9]/', $value) ? $value : null;
}

function anex_paired_operator_name($value): string
{
    $name = anex_paired_text($value) ?? '';
    $name = function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
    $name = strtr($name, ['АНЕКС'=>'анекс','Анекс'=>'анекс','ТУР'=>'тур','Тур'=>'тур']);
    return trim((string)preg_replace('/[^\p{L}\p{N}]+/u', ' ', $name));
}

function anex_paired_operator(array $rows): array
{
    if (count($rows) > 1000) throw new RuntimeException('PAIRED_TV_OPERATORS_LIMIT');
    $found = [];
    foreach ($rows as $row) {
        if (!is_array($row)) continue;
        $id = anex_paired_id($row['id'] ?? null);
        if ($id === null) continue;
        $name = anex_paired_operator_name($row['name'] ?? null);
        if (in_array($name, ['anex','anex tour','anextour','анекс','анекс тур'], true)) {
            $found[$id] = ['id'=>$id,'name'=>anex_paired_text($row['name'])];
        }
    }
    if (count($found) !== 1) throw new RuntimeException('PAIRED_TV_OPERATOR_NOT_UNIQUE');
    return array_values($found)[0];
}

function anex_paired_metrics(array $data, array $secrets): array
{
    $out = [];
    foreach (array_slice($data, 0, 100, true) as $key => $value) {
        if (!is_string($key) || !preg_match('/\A[A-Za-z][A-Za-z0-9_]{0,63}\z/D', $key)
            || !preg_match('/(?:total|count|found|hotel|tour|price|progress|status)/i', $key)
            || preg_match('/(?:id|key|token|url)/i', $key)) continue;
        if (is_int($value) || is_bool($value) || (is_float($value) && is_finite($value))) $out[$key] = $value;
        elseif (is_string($value)) $out[$key] = anex_paired_text($value, $secrets, 80);
    }
    return $out;
}

/** Single attempt, including search creation. No continuation, redirects or retries. */
function anex_paired_tv_get(string $path, array $params, string $token, float $deadline, array &$requests): array
{
    $kind = $path === '/operators' ? 'operators' : ($path === '/tours/search' ? 'search_start'
        : (preg_match('~/status\z~', $path) ? 'search_status' : 'search_results'));
    if (!in_array($path, ['/operators','/tours/search'], true)
        && !preg_match('~\A/tours/search/[1-9][0-9]{0,17}(?:/status)?\z/D', $path)) throw new RuntimeException('PAIRED_TV_PATH');
    if (!function_exists('curl_init')) throw new RuntimeException('PAIRED_TV_TRANSPORT_UNAVAILABLE');
    $remaining = (int)floor($deadline - microtime(true));
    if ($remaining < 2) throw new RuntimeException('PAIRED_TV_DEADLINE');
    $query = v2_data_query_string($params);
    $url = 'https://api.tourvisor.ru/search/api/v1' . $path . ($query !== '' ? '?' . $query : '');
    $body = ''; $retryAfter = null; $tooLarge = false;
    $handle = curl_init($url);
    if ($handle === false) throw new RuntimeException('PAIRED_TV_TRANSPORT_UNAVAILABLE');
    $index = count($requests);
    $requests[] = ['action'=>$kind,'attempt'=>1];
    try {
        curl_setopt_array($handle, [CURLOPT_HTTPGET=>true,CURLOPT_RETURNTRANSFER=>false,
            CURLOPT_CONNECTTIMEOUT=>min(10,$remaining),CURLOPT_TIMEOUT=>min(20,$remaining),
            CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_FOLLOWLOCATION=>false,
            CURLOPT_MAXREDIRS=>0,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_PROXY=>'',CURLOPT_NOPROXY=>'*',
            CURLOPT_HTTPHEADER=>['Authorization: Bearer ' . $token,'Accept: application/json'],
            CURLOPT_HEADERFUNCTION=>static function ($unused, string $header) use (&$retryAfter): int {
                if (stripos($header, 'HTTP/') === 0) $retryAfter = null;
                if (stripos($header, 'Retry-After:') === 0 && strlen($header) <= 128) {
                    $raw = trim(substr($header, 12));
                    if (preg_match('/\A[0-9]{1,9}\z/D', $raw)) $retryAfter = (int)$raw;
                    else {
                        $date = DateTimeImmutable::createFromFormat('!D, d M Y H:i:s \G\M\T', $raw, new DateTimeZone('UTC'));
                        if ($date && $date->format('D, d M Y H:i:s \G\M\T') === $raw) $retryAfter = max(0,$date->getTimestamp()-time());
                    }
                }
                return strlen($header);
            },
            CURLOPT_WRITEFUNCTION=>static function ($unused, string $chunk) use (&$body,&$tooLarge): int {
                $body .= substr($chunk, 0, max(0, 3500001-strlen($body)));
                $tooLarge = strlen($body) > 3500000;
                return $tooLarge ? 0 : strlen($chunk);
            }]);
        $success = curl_exec($handle);
        $http = (int)curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $requests[$index] += ['http_status'=>$http,'response_bytes'=>strlen($body),
            'elapsed_ms'=>(int)round(curl_getinfo($handle,CURLINFO_TOTAL_TIME)*1000),
            'curl_errno'=>curl_errno($handle),'retry_after_seconds'=>$retryAfter];
        if ($http === 429) throw new RuntimeException('PAIRED_TV_RATE_LIMITED');
        if ($tooLarge) throw new RuntimeException('PAIRED_TV_RESPONSE_TOO_LARGE');
        if ($success === false) throw new RuntimeException('PAIRED_TV_TRANSPORT_ERROR');
        if ($http < 200 || $http >= 300) throw new RuntimeException('PAIRED_TV_HTTP_ERROR');
        $decoded = json_decode($body, true, 48);
        if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) throw new RuntimeException('PAIRED_TV_INVALID_RESPONSE');
        return $decoded;
    } finally { curl_close($handle); }
}

function anex_paired_preservation(PDO $pdo): array
{
    if ($pdo->inTransaction()) throw new RuntimeException('PAIRED_TRANSACTION');
    $pdo->exec('SET TRANSACTION READ ONLY'); $pdo->beginTransaction();
    try {
        $out = [];
        foreach (['anex_hotel_search_mappings','anex_hotel_decisions'] as $table) {
            $hash = hash_init('sha256'); $count = 0;
            $read = $pdo->query('SELECT * FROM ' . $table . ' ORDER BY anex_hotel_id');
            while ($row = $read->fetch(PDO::FETCH_ASSOC)) {
                if (++$count > 50000) throw new RuntimeException('PAIRED_REGISTRY_LIMIT');
                hash_update($hash, json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
            }
            $out[$table] = ['count'=>$count,'sha256'=>hash_final($hash)];
        }
        foreach (['catalog_hotels','anex_hotels'] as $table) $out[$table] = ['count'=>(int)$pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn()];
        $out['effective_registry_count'] = AnyTourAnexSearchMappingRegistry::fromPdo($pdo)->count();
        return $out;
    } finally { $pdo->rollBack(); }
}

function anex_paired_observe(PDO $pdo, array $offers, array $context): array
{
    $rows = AnyTourAnexSearchObservations::rows($offers, $context);
    if (!$rows) return ['status'=>'empty','unique_hotels'=>0,'readback_verified'=>true];
    $ids = array_column($rows, 'anex_hotel_id');
    $query = $pdo->prepare('SELECT anex_hotel_id,search_count,first_seen_utc,last_seen_utc,last_catalog_hotel_id,country_id,anex_country_id,last_checkin_from,last_checkin_to,last_source_sha'
        . ' FROM anex_search_hotel_observations WHERE anex_hotel_id IN (' . implode(',',array_fill(0,count($ids),'?')) . ')');
    $query->execute($ids); $before = [];
    foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) $before[(int)$row['anex_hotel_id']] = $row;
    $stored = AnyTourAnexSearchObservations::record($pdo, $offers, $context);
    $query->execute($ids); $after = $query->fetchAll(PDO::FETCH_ASSOC);
    if (count($after) !== count($ids)) throw new RuntimeException('PAIRED_OBSERVATION_READBACK');
    $expected = array_column($rows, null, 'anex_hotel_id');
    foreach ($after as $row) {
        $id = (int)$row['anex_hotel_id']; $old = $before[$id] ?? null; $wanted = $expected[$id];
        if ((int)$row['search_count'] < ($old ? (int)$old['search_count'] : 0)+1
            || ($old && ($row['first_seen_utc'] !== $old['first_seen_utc'] || $row['last_seen_utc'] < $old['last_seen_utc']))
            || (int)$row['country_id'] !== $context['country_id'] || (int)$row['anex_country_id'] !== $context['anex_country_id']
            || $row['last_checkin_from'] !== $context['checkin_from'] || $row['last_checkin_to'] !== $context['checkin_to']
            || $row['last_source_sha'] !== ANEX_PREVIEW_SOURCE_SHA
            || ($row['last_catalog_hotel_id'] === null ? null : (int)$row['last_catalog_hotel_id']) !== $wanted['last_catalog_hotel_id']) {
            throw new RuntimeException('PAIRED_OBSERVATION_READBACK');
        }
    }
    return $stored + ['previously_observed'=>count($before),'persisted_hotels'=>count($after),'readback_verified'=>true];
}

function anex_paired_tv_offers(array $groups, array $criteria, array $operator, array $secrets, array &$summary): array
{
    if ($groups !== [] && array_keys($groups) !== range(0,count($groups)-1)) throw new RuntimeException('PAIRED_TV_RESULTS_SHAPE');
    $offers = []; $seen = []; $summary = ['raw_hotels'=>count($groups),'raw_tours'=>0,'rejected_tours'=>0,'operator_unverified_tours'=>0,'output_limit_reached'=>false];
    foreach (array_slice($groups,0,100) as $hotel) {
        if (!is_array($hotel) || !is_array($hotel['tours'] ?? null)) throw new RuntimeException('PAIRED_TV_RESULTS_SHAPE');
        $hotelId = anex_paired_id($hotel['id'] ?? null);
        $summary['raw_tours'] += count($hotel['tours']);
        foreach ($hotel['tours'] as $tour) {
            if (!is_array($tour)) { $summary['rejected_tours']++; continue; }
            $operatorValue = $tour['operator'] ?? null;
            $operatorId = anex_paired_id($tour['operatorId'] ?? (is_array($operatorValue) ? ($operatorValue['id'] ?? null) : null));
            $operatorName = anex_paired_text($operatorValue, $secrets);
            $sameName = in_array(anex_paired_operator_name($operatorName), ['anex','anex tour','anextour','анекс','анекс тур'], true);
            if (($operatorId !== null && $operatorId !== $operator['id']) || ($operatorId === null && !$sameName)) {
                $summary['operator_unverified_tours']++; $summary['rejected_tours']++; continue;
            }
            $date = anex_paired_date($tour['date'] ?? null); $price = anex_paired_price($tour['price'] ?? null);
            if ((isset($hotel['country']['id']) && anex_paired_id($hotel['country']['id']) !== $criteria['countryId'])
                || $hotelId === null || $date === null || $date < $criteria['dateFrom'] || $date > $criteria['dateTo']
                || (string)($tour['nights'] ?? '') !== '7' || $price === null
                || (isset($tour['adults']) && (string)$tour['adults'] !== '2')
                || (isset($tour['children']) && (string)$tour['children'] !== '0')) {
                $summary['rejected_tours']++; continue;
            }
            if (count($offers) >= 1500) { $summary['output_limit_reached'] = true; continue; }
            $row = ['provider'=>'tourvisor','hotel_id'=>$hotelId,'local_hotel_id'=>null,
                'hotel_name'=>anex_paired_text($hotel['name'] ?? null,$secrets),
                'hotel_category'=>anex_paired_text((string)($hotel['category'] ?? ''),$secrets,32),
                'region'=>anex_paired_text($hotel['region'] ?? null,$secrets),'subregion'=>anex_paired_text($hotel['subRegion'] ?? null,$secrets),
                'date'=>$date,'nights'=>7,'adults'=>2,'children'=>0,'traveller_context_source'=>'request',
                'meal'=>anex_paired_text($tour['meal'] ?? null,$secrets),
                'room'=>anex_paired_text($tour['roomType'] ?? null,$secrets),
                'placement'=>anex_paired_text($tour['placement'] ?? null,$secrets),
                'price'=>$price,'currency'=>anex_paired_text($tour['currency'] ?? 'RUB',$secrets,8),
                'currency_source'=>isset($tour['currency']) ? 'response' : 'request',
                'operator_id'=>$operatorId ?? $operator['id'],'operator_name'=>$operatorName ?? $operator['name'],
                'operator_identity_evidence'=>$operatorId !== null ? 'response_id' : 'exact_response_name',
                'is_charter'=>is_bool($tour['isCharter'] ?? null) ? $tour['isCharter'] : null,
                'is_direct'=>is_bool($tour['isDirect'] ?? null) ? $tour['isDirect'] : null,
                'flight_itinerary_verified'=>false,'fuel_inclusion_verified'=>false,'final_price_verified'=>false];
            // Tour/search/CATCLAIM identifiers are intentionally absent from the artifact.
            $key = hash('sha256',json_encode($row));
            if (isset($seen[$key])) continue;
            $seen[$key] = true; $row['observation_key'] = $key; $offers[] = $row;
        }
    }
    return $offers;
}

function anex_paired_main(): array
{
    $started = microtime(true); $pdo = null; $anexClient = null; $tvRequests = []; $secrets = [];
    $report = ['schema_version'=>1,'experiment_id'=>'one_day_anex_20260908','case_id'=>null,
        'status'=>'probe_unavailable','ok'=>false,'scope'=>'preview','offers'=>[],
        'coverage_limits'=>['full_supplier_inventory'=>false,'final_price_verified'=>false],
        'requests'=>['tourvisor'=>0,'anex'=>0,'total'=>0],'no_request'=>true];
    try {
        if (PHP_SAPI !== 'cli') throw new RuntimeException('PAIRED_CLI_ONLY');
        $raw = file_get_contents('php://stdin',false,null,0,4097);
        if (!is_string($raw) || strlen($raw)>4096) throw new RuntimeException('PAIRED_INVALID_INPUT');
        $input = anex_paired_input(json_decode($raw,true,8));
        $report['case_id'] = $input['case_id']; $report['input'] = $input;
        $accountHome = (string)getenv('HOME');
        $root = realpath($accountHome . '/www/anytoour.ru');
        $preview = realpath($accountHome . '/www/anytoour.ru/_preview/search3-anex-candidate');
        if (!$root || !$preview || $preview !== $root . '/_preview/search3-anex-candidate'
            || !in_array(realpath((string)getcwd()),[$root,$preview],true)) throw new RuntimeException('PAIRED_INVALID_RUNTIME');
        require_once $accountHome . '/.anytoour-anex/search3-preview.php';
        $manifest = json_decode((string)file_get_contents($preview . '/anex-preview-manifest.json'),true);
        if (!defined('ANYTOUR_ANEX_PREVIEW_ENABLED') || ANYTOUR_ANEX_PREVIEW_ENABLED !== true
            || !defined('ANEX_PREVIEW_SOURCE_SHA') || !preg_match('/\A[a-f0-9]{40}\z/D',(string)ANEX_PREVIEW_SOURCE_SHA)
            || ANEX_PREVIEW_SOURCE_SHA !== 'd3bf074933f372e4e06eec3781c51b96542aa57a'
            || ($manifest['source_sha'] ?? null) !== ANEX_PREVIEW_SOURCE_SHA
            || !defined('ANEX_API_TOKEN') || !is_string(ANEX_API_TOKEN) || trim(ANEX_API_TOKEN)==='') throw new RuntimeException('PAIRED_PREVIEW_CONFIGURATION');
        $report['preview_source_sha'] = ANEX_PREVIEW_SOURCE_SHA;
        $secrets[] = ANEX_API_TOKEN;
        require_once $preview . '/app/integrations/anex-search.php';
        require_once $preview . '/app/integrations/anex-search-mapping-registry.php';
        require_once $preview . '/app/integrations/anex-search-observations.php';
        $_SERVER['SCRIPT_FILENAME'] = '';
        require_once $preview . '/api-anex-search3-preview.php';
        $helper = is_file($root.'/data/db-v1.php') ? $root.'/data/db-v1.php' : $root.'/v2/data/db-v1.php';
        require_once $helper;
        $pdo = v2_data_db();
        if (!$pdo instanceof PDO || $pdo->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql') throw new RuntimeException('PAIRED_DATABASE_UNAVAILABLE');
        $report['preservation_before'] = anex_paired_preservation($pdo);
        $lookup = $pdo->prepare('SELECT d.id AS departure_id,d.name AS departure_name,c.id AS country_id,c.name AS country_name'
            . ' FROM catalog_departures d CROSS JOIN catalog_countries c WHERE d.is_active=1 AND c.is_active=1'
            . ' AND d.name IN (?,?) AND c.name IN (?,?) LIMIT 2');
        $lookup->execute(['Москва','Moscow','Турция','Turkey']); $local = $lookup->fetchAll(PDO::FETCH_ASSOC);
        if (count($local)!==1) throw new RuntimeException('PAIRED_LOCAL_IDENTITY_NOT_UNIQUE');
        $local = $local[0];
        $dateTo = $input['case_id']==='tv_week' ? '2026-09-22' : '2026-09-16';
        $criteria = ['departureId'=>(int)$local['departure_id'],'countryId'=>(int)$local['country_id'],
            'dateFrom'=>$input['date'],'dateTo'=>$dateTo,'nightsFrom'=>7,'nightsTo'=>7,
            'adults'=>2,'childs'=>[],'currency'=>'RUB','onlyCharter'=>false,'onlyDirect'=>false];
        $report['criteria'] = $criteria;
        if ($input['case_id'] !== 'anex_day') {
            $tvHelper = is_file($root.'/data/tourvisor-client-v1.php') ? $root.'/data/tourvisor-client-v1.php' : $root.'/v2/data/tourvisor-client-v1.php';
            require_once $tvHelper;
            $token = v2_data_tourvisor_token();
            if ($token === '') throw new RuntimeException('PAIRED_TV_TOKEN_REQUIRED');
            $secrets[] = $token; $deadline = microtime(true)+160;
            $operator = anex_paired_operator(anex_paired_tv_get('/operators',[
                'departureId'=>$criteria['departureId'],'countryId'=>$criteria['countryId']],$token,$deadline,$tvRequests));
            $criteria['operatorIds'] = [$operator['id']]; $report['criteria'] = $criteria; $report['operator'] = $operator;
            usleep(1050000);
            $search = anex_paired_tv_get('/tours/search',$criteria,$token,$deadline,$tvRequests);
            $searchId = $search['searchId'] ?? null;
            if ((!is_int($searchId) && !is_string($searchId)) || !preg_match('/\A[1-9][0-9]{0,17}\z/D',(string)$searchId)) throw new RuntimeException('PAIRED_TV_SEARCH_ID_REQUIRED');
            $report['search_started'] = true; $report['search_status_samples'] = []; $complete = false;
            for ($poll=0; $poll<8 && microtime(true)<$deadline-30; ++$poll) {
                sleep($poll===0 ? 1 : 10);
                $status = anex_paired_tv_get('/tours/search/'.$searchId.'/status',['operatorStatus'=>false],$token,$deadline,$tvRequests);
                $report['search_status_samples'][] = anex_paired_metrics($status,$secrets);
                $complete = (is_numeric($status['progress'] ?? null) && (float)$status['progress']>=100)
                    || (is_string($status['status'] ?? null) && strtolower($status['status'])==='complete');
                if ($complete) break;
            }
            usleep(1050000);
            $groups = anex_paired_tv_get('/tours/search/'.$searchId,['limit'=>100],$token,$deadline,$tvRequests);
            $summary = []; $offers = anex_paired_tv_offers($groups,$criteria,$operator,$secrets,$summary);
            $ids = array_values(array_unique(array_column($offers,'hotel_id'))); $known = [];
            if ($ids) {
                $read = $pdo->prepare('SELECT id FROM catalog_hotels WHERE country_id=? AND is_active=1 AND id IN (' . implode(',',array_fill(0,count($ids),'?')) . ')');
                $read->execute(array_merge([$criteria['countryId']],$ids));
                foreach ($read->fetchAll(PDO::FETCH_COLUMN) as $id) $known[(int)$id] = true;
            }
            foreach ($offers as &$offer) if (isset($known[$offer['hotel_id']])) $offer['local_hotel_id'] = $offer['hotel_id'];
            unset($offer);
            $report['offers'] = $offers; $report['summary'] = $summary;
            $report['coverage_limits'] += ['hotel_limit'=>100,'offer_limit'=>1500,'search_complete'=>$complete,
                'hotel_limit_reached'=>count($groups)>=100,'output_limit_reached'=>$summary['output_limit_reached'],
                'status_poll_limit'=>8,'continuation_requested'=>false,'supplier_total_known'=>false,
                'raw_status_projection'=>'Only bounded status/count/progress/price scalars; no search or tour identifiers'];
        } else {
            $anexClient = new AnyTourAnexClient(ANEX_API_TOKEN); $cache = [];
            $departure = anytour_anex_search3_dictionary_id(anytour_anex_search3_dictionary($anexClient,'SearchTour_TOWNFROMS',[],$cache),[$local['departure_name'],'Москва','Moscow']);
            $country = anytour_anex_search3_dictionary_id(anytour_anex_search3_dictionary($anexClient,'SearchTour_STATES',['TOWNFROMINC'=>$departure],$cache),[$local['country_name'],'Турция','Turkey']);
            $party = ['TOWNFROMINC'=>$departure,'STATEINC'=>$country,'ADULT'=>2,'CHILD'=>0];
            $calendar = anytour_anex_search3_dictionary($anexClient,'SearchTour_CHECKIN',$party,$cache);
            $start = anex_paired_date($calendar['start'] ?? null);
            if ($start===null || !is_string($calendar['valid'] ?? null)) throw new RuntimeException('PAIRED_ANEX_CALENDAR_INVALID');
            $offset = (int)(new DateTimeImmutable($start))->diff(new DateTimeImmutable($input['date']))->format('%r%a');
            if ($offset<0 || !isset($calendar['valid'][$offset]) || strpos('1235',$calendar['valid'][$offset])===false) throw new RuntimeException('PAIRED_ANEX_DATE_UNAVAILABLE');
            $dated = ['TOWNFROMINC'=>$departure,'STATEINC'=>$country,'CHECKIN_BEG'=>'20260916','CHECKIN_END'=>'20260916','ADULT'=>2,'CHILD'=>0];
            $currency = anytour_anex_search3_dictionary_id(anytour_anex_search3_dictionary($anexClient,'SearchTour_CURRENCIES',$dated,$cache),['RUB','RUR','Рубль','Рубли','Руб']);
            $nightData = anytour_anex_search3_dictionary($anexClient,'SearchTour_NIGHTS',$dated+['CURRENCY'=>$currency],$cache);
            $nightRows = $nightData['places'] ?? $nightData['nights'] ?? $nightData; $hasSeven = false;
            foreach (is_array($nightRows) ? $nightRows : [] as $row) {
                $value = is_array($row) ? ($row['id'] ?? $row['nights'] ?? null) : $row;
                if ($value===7 || $value==='7') $hasSeven = true;
            }
            if (!$hasSeven) throw new RuntimeException('PAIRED_ANEX_NIGHTS_UNAVAILABLE');
            $anexCriteria = ['supplier_namespace'=>'anex_online','departure_id'=>$departure,'destination_id'=>$country,
                'currency_id'=>$currency,'checkin_begin'=>$input['date'],'checkin_end'=>$input['date'],
                'nights_from'=>7,'nights_till'=>7,'adults'=>2,'children'=>0,'child_ages'=>[]];
            $report['anex_criteria'] = $anexCriteria;
            $registry = AnyTourAnexSearchMappingRegistry::fromPdo($pdo);
            $search = new AnyTourAnexSearch($anexClient,$registry->previewResolver(),$secrets);
            $result = $search->search($anexCriteria);
            foreach ($result['offers'] as $offer) {
                $price = ($offer['price']['currency'] ?? '')==='RUB' ? $offer['price'] : ($offer['converted_price'] ?? $offer['price']);
                $report['offers'][] = ['provider'=>'anex','hotel_id'=>(int)$offer['hotel']['external_id'],
                    'local_hotel_id'=>$offer['hotel']['local_id'],'mapping_status'=>$offer['hotel']['mapping_status'],
                    'hotel_name'=>$offer['hotel']['name'],'hotel_category'=>$offer['hotel']['star'],'region'=>$offer['hotel']['town'],
                    'date'=>$offer['checkin'],'checkout'=>$offer['checkout'],'nights'=>$offer['nights'],'adults'=>$offer['adults'],
                    'children'=>$offer['children'],'meal'=>$offer['meal'],'room'=>$offer['room'],'placement'=>$offer['hotel_place'],
                    'price'=>$price['amount'],'currency'=>$price['currency'],'native_price'=>$offer['price'],
                    'operator_id'=>null,'operator_name'=>'ANEX','operator_identity_evidence'=>'direct_anex_gateway',
                    'kind'=>$offer['kind'],'availability'=>$offer['availability'],
                    'flight_itinerary_verified'=>false,'fuel_inclusion_verified'=>false,'final_price_verified'=>false];
            }
            $report['observations'] = anex_paired_observe($pdo,$result['offers'],['country_id'=>$criteria['countryId'],
                'anex_country_id'=>$country,'checkin_from'=>$input['date'],'checkin_to'=>$input['date']]);
            $report['summary'] = ['rejected_tours'=>$result['rejected_count'],'truncated_count'=>$result['truncated_count']];
            $report['coverage_limits'] += ['first_page_only'=>true,'price_page'=>1,'partition_price'=>32,'offer_limit'=>300,
                'external_search_pending'=>$result['external_search_pending'],'external_results_loaded'=>false,
                'groups_expanded'=>false,'local_inventory_only'=>true,'supplier_total_known'=>false];
        }
        $report['summary']['offers'] = count($report['offers']);
        $report['summary']['unique_hotels'] = count(array_unique(array_column($report['offers'],'hotel_id')));
        $report['summary']['mapped_hotels'] = count(array_unique(array_filter(array_column($report['offers'],'local_hotel_id'),static function ($id) { return $id!==null; })));
        $report['status'] = 'ok'; $report['ok'] = true;
    } catch (Throwable $error) {
        $code = $error->getMessage();
        $report['error_code'] = preg_match('/\A(?:PAIRED|ANEX)_[A-Z_]{1,70}\z/D',$code) ? $code : 'PAIRED_PROBE_ERROR';
    } finally {
        if ($pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
        if ($pdo instanceof PDO && isset($report['preservation_before'])) {
            try {
                $report['preservation_after'] = anex_paired_preservation($pdo);
                $report['preservation_verified'] = $report['preservation_before']===$report['preservation_after'];
                if (!$report['preservation_verified']) throw new RuntimeException('PAIRED_PRESERVATION_CHANGED');
            } catch (Throwable $ignored) {
                $report['ok'] = false; $report['status'] = 'probe_unavailable'; $report['error_code'] = 'PAIRED_PRESERVATION_UNVERIFIED';
            }
        }
        $anexCount = $anexClient instanceof AnyTourAnexClient ? $anexClient->requestsMade() : 0;
        $report['requests'] = ['tourvisor'=>count($tvRequests),'anex'=>$anexCount,'total'=>count($tvRequests)+$anexCount,
            'tourvisor_log'=>$tvRequests,'anex_last_request'=>$anexClient instanceof AnyTourAnexClient ? $anexClient->lastRequestDiagnostics() : null];
        $report['no_request'] = $report['requests']['total']===0;
        $report['elapsed_ms'] = (int)round((microtime(true)-$started)*1000);
        $report['generated_at_utc'] = gmdate('c');
    }
    // Last boundary checks the complete encoded report, including supplier labels.
    $json = json_encode($report,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    if ($json===false || strlen($json)>3900000) return ['schema_version'=>1,'experiment_id'=>'one_day_anex_20260908',
        'case_id'=>$report['case_id'],'status'=>'probe_unavailable','ok'=>false,'error_code'=>'PAIRED_OUTPUT_LIMIT',
        'requests'=>$report['requests'],'no_request'=>$report['no_request'],'offers'=>[]];
    foreach ($secrets as $secret) if ($secret!=='' && strpos($json,$secret)!==false) return ['schema_version'=>1,
        'experiment_id'=>'one_day_anex_20260908','case_id'=>$report['case_id'],'status'=>'probe_unavailable','ok'=>false,
        'error_code'=>'PAIRED_OUTPUT_REDACTED','requests'=>$report['requests'],'no_request'=>$report['no_request'],'offers'=>[]];
    return $report;
}

if (!defined('ANYTOUR_ANEX_PAIRED_LIBRARY_ONLY')) {
    error_reporting(0); ob_start();
    $anexPairedReport = anex_paired_main();
    ob_end_clean();
    echo json_encode($anexPairedReport,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),"\n";
    // A verified controlled failure must reach the caller's checkpoint validator.
    exit(($anexPairedReport['ok'] || ($anexPairedReport['preservation_verified'] ?? false)) ? 0 : 1);
}
