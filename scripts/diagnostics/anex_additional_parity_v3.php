<?php
declare(strict_types=1);

if (!function_exists('anex_paired_text')) {
    if (!defined('ANYTOUR_ANEX_PAIRED_LIBRARY_ONLY')) define('ANYTOUR_ANEX_PAIRED_LIBRARY_ONLY', true);
    require __DIR__ . '/anex_search3_paired_runner.php';
}

const ANEX_ADDITIONAL_PARITY_EXPERIMENT = 'anex_additional_parity_20260912_v1';
const ANEX_ADDITIONAL_PROGRAM2637_EXPERIMENT = 'anex_additional_program2637_20260912_v1';
const ANEX_ADDITIONAL_PARITY_DATE = '2026-10-12';
const ANEX_ADDITIONAL_PARITY_DATE_COMPACT = '20261012';
const ANEX_ADDITIONAL_PARITY_NIGHTS = 7;
const ANEX_ADDITIONAL_PARITY_ADULTS = 2;
const ANEX_ADDITIONAL_PARITY_TARGETS = [
    ['local' => 1039, 'anex' => '33118'],
    ['local' => 1282, 'anex' => '8227'],
    ['local' => 1326, 'anex' => '16275'],
    ['local' => 21753, 'anex' => '25084'],
    ['local' => 6319, 'anex' => '8121'],
    ['local' => 953, 'anex' => '35376'],
];

function anex_additional_parity_input($value, bool $programFollowup = false): array
{
    $keys = ['experiment_id','country','date','nights','adults','child_ages','meal_family','currency'];
    $experiment = $programFollowup ? ANEX_ADDITIONAL_PROGRAM2637_EXPERIMENT : ANEX_ADDITIONAL_PARITY_EXPERIMENT;
    if (!is_array($value) || count($value) !== count($keys)
        || array_diff($keys, array_keys($value)) || array_diff(array_keys($value), $keys)
        || ($value['experiment_id'] ?? null) !== $experiment
        || ($value['country'] ?? null) !== 'Turkey'
        || ($value['date'] ?? null) !== ANEX_ADDITIONAL_PARITY_DATE
        || ($value['nights'] ?? null) !== ANEX_ADDITIONAL_PARITY_NIGHTS
        || ($value['adults'] ?? null) !== ANEX_ADDITIONAL_PARITY_ADULTS
        || ($value['child_ages'] ?? null) !== []
        || ($value['meal_family'] ?? null) !== 'ai'
        || ($value['currency'] ?? null) !== 'RUB') {
        throw new RuntimeException('ADDITIONAL_PARITY_INVALID_INPUT');
    }
    return $value;
}

function anex_additional_parity_norm($value, int $limit = 240): string
{
    $text = anex_paired_text($value, [], $limit) ?? '';
    $text = function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
    return trim((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', str_replace(['ё','Ё'], 'е', $text)));
}

function anex_additional_parity_ai($value): bool
{
    return in_array(anex_additional_parity_norm($value, 100), [
        'ai','all','all inclusive','uai','ultra all inclusive','ai without alcohol',
        'все включено','ультра все включено','все включено без алкоголя',
    ], true);
}

function anex_additional_parity_money($value, bool $allowZero = false): ?string
{
    if (is_int($value) || (is_float($value) && is_finite($value))) $value = (string) $value;
    if (!is_string($value) || !preg_match('/\A(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?\z/D', $value)) return null;
    if (!$allowZero && !preg_match('/[1-9]/', $value)) return null;
    return $value;
}

function anex_additional_parity_provider_id($value): ?string
{
    if (is_int($value) && $value > 0) $value = (string) $value;
    return is_string($value) && preg_match('/\A[1-9][0-9]{0,17}\z/D', $value) ? $value : null;
}

function anex_additional_parity_target_maps(): array
{
    $byLocal = [];
    $byAnex = [];
    foreach (ANEX_ADDITIONAL_PARITY_TARGETS as $row) {
        $byLocal[(int) $row['local']] = (string) $row['anex'];
        $byAnex[(string) $row['anex']] = (int) $row['local'];
    }
    return [$byLocal, $byAnex];
}

function anex_additional_parity_raw_programs(array $payload): array
{
    $rows = $payload['prices'] ?? null;
    if (!is_array($rows)) return [];
    $out = [];
    foreach (array_slice($rows, 0, 500) as $row) {
        if (!is_array($row)) continue;
        $claim = $row['id'] ?? null;
        if (is_int($claim) && $claim > 0) $claim = (string) $claim;
        if (!is_string($claim) || !preg_match('~\A[A-Za-z0-9][A-Za-z0-9_.:,;\~@+/=|\-]{0,2047}\z~D', $claim)
            || strpos($claim, '://') !== false) continue;
        $out[$claim] = [
            'tour' => anex_additional_parity_provider_id($row['tourKey'] ?? null),
            'currency' => anex_additional_parity_provider_id($row['currencyKey'] ?? null),
        ];
    }
    return $out;
}

function anex_additional_parity_offer(string $provider, int $localId, string $externalId, $date, $nights, $adults,
    $children, $meal, $room, $placement, $price, $currency, $fuel = null, ?string $tour = null,
    ?string $nativeCurrency = null): ?array
{
    [$byLocal, $byAnex] = anex_additional_parity_target_maps();
    if (!isset($byLocal[$localId])) return null;
    if ($provider === 'anex' && (($byAnex[$externalId] ?? null) !== $localId)) return null;
    if (anex_paired_date($date) !== ANEX_ADDITIONAL_PARITY_DATE
        || (int) $nights !== ANEX_ADDITIONAL_PARITY_NIGHTS
        || (int) $adults !== ANEX_ADDITIONAL_PARITY_ADULTS || (int) $children !== 0
        || !anex_additional_parity_ai($meal)) return null;
    $amount = anex_additional_parity_money($price);
    $currency = strtoupper((string) (anex_paired_text($currency, [], 8) ?? ''));
    if ($amount === null || $currency !== 'RUB') return null;
    $fuelValue = null;
    if ($fuel !== null) {
        if (is_array($fuel)) $fuel = $fuel['value'] ?? $fuel['amount'] ?? null;
        $fuelValue = anex_additional_parity_money($fuel, true);
    }
    return [
        'provider' => $provider,
        'local_hotel_id' => $localId,
        'external_hotel_id' => $externalId,
        'date' => ANEX_ADDITIONAL_PARITY_DATE,
        'nights' => ANEX_ADDITIONAL_PARITY_NIGHTS,
        'adults' => ANEX_ADDITIONAL_PARITY_ADULTS,
        'children' => 0,
        'meal_family' => 'ai',
        'meal_label' => anex_paired_text($meal, [], 100),
        'room' => anex_paired_text($room, [], 180),
        'room_norm' => anex_additional_parity_norm($room, 180),
        'placement' => anex_paired_text($placement, [], 120),
        'placement_norm' => anex_additional_parity_norm($placement, 120),
        'price' => $amount,
        'currency' => 'RUB',
        'fuel_charge' => $fuelValue,
        'supplier_tour_program_id' => $provider === 'anex' ? $tour : null,
        'supplier_currency_id' => $provider === 'anex' ? $nativeCurrency : null,
        'fuel_inclusion_verified' => false,
        'final_price_verified' => false,
    ];
}

function anex_additional_parity_align(array $anexOffers, array $tourvisorOffers): array
{
    $tv = [];
    foreach ($tourvisorOffers as $row) {
        if (!is_array($row) || ($row['provider'] ?? null) !== 'tourvisor' || ($row['fuel_charge'] ?? null) === null) continue;
        $key = implode('|', [(string) ($row['local_hotel_id'] ?? ''), (string) ($row['date'] ?? ''),
            (string) ($row['nights'] ?? ''), (string) ($row['adults'] ?? ''), (string) ($row['children'] ?? ''),
            (string) ($row['meal_family'] ?? ''), (string) ($row['room_norm'] ?? '')]);
        $tv[$key][] = $row;
    }
    $pairs = [];
    foreach ($anexOffers as $row) {
        if (!is_array($row) || ($row['provider'] ?? null) !== 'anex'
            || anex_additional_parity_provider_id($row['supplier_tour_program_id'] ?? null) === null
            || anex_additional_parity_provider_id($row['supplier_currency_id'] ?? null) === null) continue;
        $key = implode('|', [(string) ($row['local_hotel_id'] ?? ''), (string) ($row['date'] ?? ''),
            (string) ($row['nights'] ?? ''), (string) ($row['adults'] ?? ''), (string) ($row['children'] ?? ''),
            (string) ($row['meal_family'] ?? ''), (string) ($row['room_norm'] ?? '')]);
        foreach ($tv[$key] ?? [] as $other) {
            $pairs[] = [
                'basis' => 'same_current_local_hotel_date_party_ai_and_exact_room',
                'identical_supplier_package_verified' => false,
                'placement_compared_but_not_identity_key' => true,
                'anex' => $row,
                'tourvisor' => $other,
            ];
        }
    }
    usort($pairs, static function (array $a, array $b): int {
        foreach (['local_hotel_id','room_norm','price'] as $key) {
            $left = (string) ($a['anex'][$key] ?? ''); $right = (string) ($b['anex'][$key] ?? '');
            if ($left !== $right) return strnatcasecmp($left, $right);
        }
        return 0;
    });
    return $pairs;
}

function anex_additional_parity_save(string $path, array $value): void
{
    $bytes = json_encode($value, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    if (strlen($bytes) > 4000000) throw new RuntimeException('ADDITIONAL_PARITY_CHECKPOINT_LIMIT');
    $tmp = $path . '.' . bin2hex(random_bytes(8));
    $fh = fopen($tmp, 'x'); if (!$fh) throw new RuntimeException('ADDITIONAL_PARITY_CHECKPOINT_WRITE');
    chmod($tmp, 0600);
    try {
        if (fwrite($fh, $bytes) !== strlen($bytes) || !fflush($fh)) throw new RuntimeException('ADDITIONAL_PARITY_CHECKPOINT_WRITE');
        if (function_exists('fsync') && !fsync($fh)) throw new RuntimeException('ADDITIONAL_PARITY_CHECKPOINT_WRITE');
    } finally { fclose($fh); }
    if (!rename($tmp, $path)) { @unlink($tmp); throw new RuntimeException('ADDITIONAL_PARITY_CHECKPOINT_WRITE'); }
    // JSON legitimately decodes 130.0 as int 130; verify persisted bytes, not PHP scalar types.
    $readback = file_get_contents($path);
    if ($readback !== $bytes) throw new RuntimeException('ADDITIONAL_PARITY_CHECKPOINT_READBACK');
    json_decode($readback, true, 64, JSON_THROW_ON_ERROR);
}

function anex_additional_parity_local(PDO $pdo): array
{
    $q = $pdo->prepare("SELECT d.id departure_id,d.name departure_name,c.id country_id,c.name country_name FROM catalog_departures d CROSS JOIN catalog_countries c WHERE d.is_active=1 AND c.is_active=1 AND d.name IN ('Москва','Moscow') AND c.name IN ('Турция','Turkey') LIMIT 2");
    $q->execute(); $rows = $q->fetchAll(PDO::FETCH_ASSOC);
    if (count($rows) !== 1) throw new RuntimeException('ADDITIONAL_PARITY_LOCAL_IDENTITY');
    return $rows[0];
}

function anex_additional_parity_validate_targets(PDO $pdo, AnyTourAnexSearchMappingRegistry $registry): void
{
    foreach (ANEX_ADDITIONAL_PARITY_TARGETS as $row) {
        $local = (int) $row['local']; $anex = (string) $row['anex'];
        if ($registry->resolve('anex_online', $anex, 'preview') !== $local) throw new RuntimeException('ADDITIONAL_PARITY_TARGET_CHANGED');
        $q = $pdo->prepare('SELECT COUNT(*) FROM catalog_hotels WHERE id=? AND is_active=1');
        $q->execute([$local]); if ((int) $q->fetchColumn() !== 1) throw new RuntimeException('ADDITIONAL_PARITY_TARGET_CHANGED');
    }
}

function anex_additional_parity_anex(PDO $pdo, array $local, AnyTourAnexSearchMappingRegistry $registry, array &$secrets): array
{
    $home = (string) getenv('HOME'); require_once $home . '/.anytoour-anex/search3-preview.php';
    if (!defined('ANEX_API_TOKEN') || !is_string(ANEX_API_TOKEN) || trim(ANEX_API_TOKEN) === '') throw new RuntimeException('ADDITIONAL_PARITY_ANEX_TOKEN');
    $secrets[] = ANEX_API_TOKEN; $client = new AnyTourAnexClient(ANEX_API_TOKEN); $cache = [];
    $departure = anytour_anex_search3_dictionary_id(anytour_anex_search3_dictionary($client,'SearchTour_TOWNFROMS',[],$cache),[$local['departure_name'],'Москва','Moscow']);
    usleep(1050000);
    $country = anytour_anex_search3_dictionary_id(anytour_anex_search3_dictionary($client,'SearchTour_STATES',['TOWNFROMINC'=>$departure],$cache),[$local['country_name'],'Турция','Turkey']);
    usleep(1050000);
    $dated = ['TOWNFROMINC'=>$departure,'STATEINC'=>$country,'CHECKIN_BEG'=>ANEX_ADDITIONAL_PARITY_DATE_COMPACT,'CHECKIN_END'=>ANEX_ADDITIONAL_PARITY_DATE_COMPACT,'ADULT'=>2,'CHILD'=>0];
    $currency = anytour_anex_search3_dictionary_id(anytour_anex_search3_dictionary($client,'SearchTour_CURRENCIES',$dated,$cache),['RUB','RUR','Рубль','Рубли','Руб']);
    usleep(1050000);
    $hotelIds = array_map(static fn(array $row): string => (string) $row['anex'], ANEX_ADDITIONAL_PARITY_TARGETS);
    $params = ['TOWNFROMINC'=>$departure,'STATEINC'=>$country,'CURRENCY'=>$currency,'CHECKIN_BEG'=>ANEX_ADDITIONAL_PARITY_DATE_COMPACT,
        'CHECKIN_END'=>ANEX_ADDITIONAL_PARITY_DATE_COMPACT,'NIGHTS_FROM'=>7,'NIGHTS_TILL'=>7,'ADULT'=>2,'CHILD'=>0,
        'HOTELS'=>implode(',',$hotelIds),'FREIGHT'=>1,'FILTER'=>1,'PRICEPAGE'=>1,'PARTITION_PRICE'=>32,'SORT'=>'ASC','DYN_SEPARATE'=>1];
    $raw = $client->request('SearchTour_PRICES', $params); $programs = anex_additional_parity_raw_programs($raw);
    $criteria = ['supplier_namespace'=>'anex_online','departure_id'=>$departure,'destination_id'=>$country,'currency_id'=>$currency,
        'checkin_begin'=>ANEX_ADDITIONAL_PARITY_DATE,'checkin_end'=>ANEX_ADDITIONAL_PARITY_DATE,'nights_from'=>7,'nights_till'=>7,
        'adults'=>2,'children'=>0,'child_ages'=>[],'hotel_ids'=>$hotelIds];
    $result = anytour_anex_normalize_prices($raw, $criteria, $registry->previewResolver(), $secrets); $offers = [];
    foreach ($result['offers'] ?? [] as $offer) {
        $localId = $offer['hotel']['local_id'] ?? null; if (!is_int($localId)) continue;
        $price = ($offer['price']['currency'] ?? '') === 'RUB' ? $offer['price'] : ($offer['converted_price'] ?? $offer['price']);
        $ctx = $programs[$offer['supplier_offer_id'] ?? ''] ?? [];
        $row = anex_additional_parity_offer('anex',$localId,(string)($offer['hotel']['external_id']??''),$offer['checkin']??null,$offer['nights']??null,
            $offer['adults']??null,$offer['children']??null,$offer['meal']??null,$offer['room']??null,$offer['hotel_place']??null,
            $price['amount']??null,$price['currency']??null,null,$ctx['tour']??null,$ctx['currency']??null);
        if ($row !== null && count($offers) < 200) $offers[] = $row;
    }
    return ['offers'=>$offers,'requests'=>$client->requestsMade(),'received_offers'=>count($result['offers']??[]),
        'coverage'=>['state'=>'bounded','reason'=>'pricepage_1_six_proven_hotels','page'=>1,'all_pages_retained'=>false]];
}

function anex_additional_parity_tv(array $local, array &$requestLog, array &$secrets): array
{
    $root = realpath((string) getenv('HOME') . '/www/anytoour.ru');
    $helper = is_file($root.'/data/tourvisor-client-v1.php') ? $root.'/data/tourvisor-client-v1.php' : $root.'/v2/data/tourvisor-client-v1.php';
    require_once $helper; $token = v2_data_tourvisor_token(); if ($token === '') throw new RuntimeException('ADDITIONAL_PARITY_TV_TOKEN');
    $secrets[] = $token; $deadline = microtime(true) + 160;
    $operator = anex_paired_operator(anex_paired_tv_get('/operators',['departureId'=>(int)$local['departure_id'],'countryId'=>(int)$local['country_id']],$token,$deadline,$requestLog));
    $hotelIds = array_map(static fn(array $row): int => (int) $row['local'], ANEX_ADDITIONAL_PARITY_TARGETS);
    $criteria = ['departureId'=>(int)$local['departure_id'],'countryId'=>(int)$local['country_id'],'dateFrom'=>ANEX_ADDITIONAL_PARITY_DATE,
        'dateTo'=>ANEX_ADDITIONAL_PARITY_DATE,'nightsFrom'=>7,'nightsTo'=>7,'adults'=>2,'childs'=>[],'currency'=>'RUB',
        'onlyCharter'=>false,'onlyDirect'=>false,'hotelIds'=>$hotelIds,'operatorIds'=>[$operator['id']]];
    usleep(1050000); $start = anex_paired_tv_get('/tours/search',$criteria,$token,$deadline,$requestLog); $searchId = $start['searchId'] ?? null;
    if ((!is_int($searchId) && !is_string($searchId)) || !preg_match('/\A[1-9][0-9]{0,17}\z/D',(string)$searchId)) throw new RuntimeException('ADDITIONAL_PARITY_TV_SEARCH');
    $complete = false;
    for ($poll=0; $poll<8 && microtime(true)<$deadline-30; ++$poll) {
        sleep($poll===0?1:10); $status = anex_paired_tv_get('/tours/search/'.$searchId.'/status',['operatorStatus'=>false],$token,$deadline,$requestLog);
        $complete = (is_numeric($status['progress']??null) && (float)$status['progress']>=100)
            || (is_string($status['status']??null) && strtolower($status['status'])==='complete');
        if ($complete) break;
    }
    usleep(1050000); $groups = anex_paired_tv_get('/tours/search/'.$searchId,['limit'=>100],$token,$deadline,$requestLog); $offers = [];
    foreach ($groups as $hotel) {
        if (!is_array($hotel) || !in_array((int)($hotel['id']??0),$hotelIds,true)) continue;
        foreach (is_array($hotel['tours']??null) ? $hotel['tours'] : [] as $tour) {
            if (!is_array($tour)) continue; $op=$tour['operator']??null;
            $opId=anex_paired_id($tour['operatorId']??(is_array($op)?($op['id']??null):null));
            $opName=anex_paired_text($op,$secrets); $same=in_array(anex_paired_operator_name($opName),['anex','anex tour','anextour','анекс','анекс тур'],true);
            if (($opId!==null && $opId!==$operator['id']) || ($opId===null && !$same)) continue;
            $row = anex_additional_parity_offer('tourvisor',(int)$hotel['id'],(string)$hotel['id'],$tour['date']??null,$tour['nights']??null,
                $tour['adults']??2,$tour['children']??$tour['childs']??0,$tour['meal']??null,$tour['roomType']??null,$tour['placement']??null,
                $tour['price']??null,$tour['currency']??'RUB',$tour['fuelCharge']??null);
            if ($row !== null && count($offers) < 300) $offers[] = $row;
        }
    }
    return ['offers'=>$offers,'search_complete'=>$complete,'requests'=>count($requestLog),
        'coverage'=>['state'=>'bounded','reason'=>'single_result_page_for_six_proven_hotels','tourvisor_status_complete'=>$complete]];
}

function anex_additional_parity_main(bool $programFollowup = false): array
{
    $started = microtime(true); $pdo = null; $lock = null; $reserved = false; $path = null; $secrets = []; $tvLog = []; $client = null;
    $experiment = $programFollowup ? ANEX_ADDITIONAL_PROGRAM2637_EXPERIMENT : ANEX_ADDITIONAL_PARITY_EXPERIMENT;
    $out = ['schema_version'=>1,'experiment_id'=>$experiment,'status'=>'blocked','automatic_retry'=>false,
        'supplier_replay_allowed'=>false,'direct_anex_requests'=>0,'tourvisor_requests'=>0,'additional_prices_requests'=>0,
        'booking_calls'=>0,'broninit_calls'=>0,'mapping_writes'=>0,'selected_pair'=>null,'additional_prices'=>null];
    try {
        $raw = file_get_contents('php://stdin',false,null,0,4097); if (!is_string($raw)||$raw===''||strlen($raw)>4096) throw new RuntimeException('ADDITIONAL_PARITY_INVALID_INPUT');
        anex_additional_parity_input(json_decode($raw,true,8,JSON_THROW_ON_ERROR), $programFollowup);
        $home=(string)getenv('HOME'); $root=realpath($home.'/www/anytoour.ru'); $preview=realpath($root.'/_preview/search3-anex-candidate');
        if (!$root||!$preview||$preview!==$root.'/_preview/search3-anex-candidate'||!in_array(realpath((string)getcwd()),[$root,$preview],true)) throw new RuntimeException('ADDITIONAL_PARITY_RUNTIME');
        require_once $home.'/.anytoour-anex/search3-preview.php'; require_once $root.'/config.php';
        if (!defined('ANEX_B2B_TOKEN')||!is_string(ANEX_B2B_TOKEN)||trim(ANEX_B2B_TOKEN)==='') throw new RuntimeException('ADDITIONAL_PARITY_B2B_TOKEN');
        $secrets[] = ANEX_B2B_TOKEN;
        // The program follow-up consumes retained inventory in Python; it does not read mappings or search suppliers again.
        if (!$programFollowup) {
            require_once $preview.'/app/integrations/anex-search.php'; require_once $preview.'/app/integrations/anex-search-mapping-registry.php';
            $_SERVER['SCRIPT_FILENAME']=''; require_once $preview.'/api-anex-search3-preview.php';
            $db=is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php'; require_once $db; $pdo=v2_data_db();
            if (!$pdo instanceof PDO || $pdo->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql') throw new RuntimeException('ADDITIONAL_PARITY_DB');
            $local=anex_additional_parity_local($pdo); $registry=AnyTourAnexSearchMappingRegistry::fromPdo($pdo); anex_additional_parity_validate_targets($pdo,$registry);
        }
        $dir=$home.'/.anytoour-anex'; if(!is_dir($dir)||is_link($dir)) throw new RuntimeException('ADDITIONAL_PARITY_CHECKPOINT_DIR');
        $path=$dir.'/'.$experiment.'.json'; $lock=fopen($path.'.lock','c'); if(!$lock||!flock($lock,LOCK_EX)) throw new RuntimeException('ADDITIONAL_PARITY_LOCK');
        if (is_file($path)) {
            $prior=json_decode((string)file_get_contents($path),true,64,JSON_THROW_ON_ERROR);
            if (($prior['status']??null)==='completed' && is_array($prior['result']??null)) return array_replace($prior['result'],['reused'=>true]);
            throw new RuntimeException('ADDITIONAL_PARITY_NOT_REPLAYABLE');
        }
        anex_additional_parity_save($path,['schema_version'=>1,'experiment_id'=>$experiment,'status'=>'reserved','reserved_at'=>gmdate('c')]); $reserved=true;
        if ($programFollowup) {
            // A different program, not the consumed v3 program 778 operation under another name.
            $tour='2637'; $currency='3';
        } else {
            $anex=anex_additional_parity_anex($pdo,$local,$registry,$secrets); $out['direct_anex_requests']=$anex['requests'];
            $tv=anex_additional_parity_tv($local,$tvLog,$secrets); $out['tourvisor_requests']=count($tvLog);
            $pairs=anex_additional_parity_align($anex['offers'],$tv['offers']);
            $out['search_evidence']=['anex'=>$anex,'tourvisor'=>$tv,'aligned_pair_count'=>count($pairs)];
            if (!$pairs) throw new RuntimeException('ADDITIONAL_PARITY_NO_ALIGNED_PAIR');
            $selected=$pairs[0]; $out['selected_pair']=$selected;
            $tour=anex_additional_parity_provider_id($selected['anex']['supplier_tour_program_id']??null);
            $currency=anex_additional_parity_provider_id($selected['anex']['supplier_currency_id']??null);
        }
        if ($tour===null||$currency===null) throw new RuntimeException('ADDITIONAL_PARITY_PROGRAM_CONTEXT');
        $out['additional_request_context']=['page'=>1,'pageSize'=>10,'tour'=>(int)$tour,'dateBeg'=>ANEX_ADDITIONAL_PARITY_DATE,'nights'=>7,'currency'=>(int)$currency];
        $client=new AnyTourAnexAdditionalPricesClient(ANEX_B2B_TOKEN);
        $additional=$client->additionalPricesDaily($out['additional_request_context']);
        $out['additional_prices_requests']=$client->requestsMade(); $out['additional_prices']=$additional;
        $out['status']='completed'; $out['supplier_effect']=$programFollowup?'read_only_additional_from_retained_search_completed':'read_only_search_and_additional_completed'; $out['reused']=false;
        anex_additional_parity_save($path,['schema_version'=>1,'experiment_id'=>$experiment,'status'=>'completed','completed_at'=>gmdate('c'),'result'=>$out]); $reserved=false;
    } catch (Throwable $e) {
        $code=$e->getMessage(); $safe=preg_match('/\A(?:ADDITIONAL_PARITY|ANEX_B2B)_[A-Z0-9_]{1,90}\z/D',$code)?$code:'ADDITIONAL_PARITY_UNCONFIRMED';
        $out['status']=$reserved?'unknown':'blocked'; $out['reason']=$safe; $out['supplier_effect']=$reserved?'unknown':'none';
        if ($reserved && is_string($path)) {
            try { anex_additional_parity_save($path,['schema_version'=>1,'experiment_id'=>$experiment,'status'=>'unknown','recorded_at'=>gmdate('c'),'reason'=>$safe]); } catch (Throwable $ignored) {}
        }
    } finally {
        if (is_resource($lock)) { flock($lock,LOCK_UN); fclose($lock); }
        if ($pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
        if ($client instanceof AnyTourAnexAdditionalPricesClient) $out['additional_prices_requests']=$client->requestsMade();
        $out['elapsed_ms']=(int)round((microtime(true)-$started)*1000); $out['tourvisor_requests']=max($out['tourvisor_requests'],count($tvLog));
    }
    $json=json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    foreach($secrets as $secret) if($secret!==''&&is_string($json)&&strpos($json,$secret)!==false) return ['schema_version'=>1,'experiment_id'=>$experiment,'status'=>'unknown','reason'=>'ADDITIONAL_PARITY_OUTPUT_REDACTED','automatic_retry'=>false,'supplier_replay_allowed'=>false,'booking_calls'=>0,'broninit_calls'=>0,'mapping_writes'=>0];
    return $out;
}

if (!defined('ANYTOUR_ANEX_ADDITIONAL_PARITY_LIBRARY_ONLY')) {
    error_reporting(0); ob_start(); $report=anex_additional_parity_main(); ob_end_clean();
    echo json_encode($report,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),"\n";
    exit(($report['status']??null)==='completed'?0:1);
}
