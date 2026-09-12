<?php
declare(strict_types=1);

const ANEX_CONCRETE_FUEL_EXPERIMENT = 'anex_concrete_fuel_binding_20260912_v2';
const ANEX_CONCRETE_FUEL_DATE = '2026-10-12';
const ANEX_CONCRETE_FUEL_DATE_COMPACT = '20261012';
const ANEX_CONCRETE_FUEL_LOCAL_HOTEL = 21753;
const ANEX_CONCRETE_FUEL_EXTERNAL_HOTEL = '25084';
const ANEX_CONCRETE_FUEL_NIGHTS = 7;
const ANEX_CONCRETE_FUEL_ADULTS = 2;
const ANEX_CONCRETE_FUEL_RETAINED_SHA256 = '940a8677c0084a99e0c1f36baaa9e7301d1afef65d89c06a39c64f8799e11163';

function anex_concrete_fuel_decimal($value): ?string
{
    if (is_int($value) || (is_float($value) && is_finite($value))) $value = (string) $value;
    return is_string($value) && preg_match('/\A(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,6})?\z/D', $value)
        ? $value : null;
}

function anex_concrete_fuel_retained($value): array
{
    if (!is_array($value) || ($value['schema_version'] ?? null) !== 1
        || ($value['experiment_id'] ?? null) !== 'anex_additional_program2637_20260912_v1'
        || ($value['status'] ?? null) !== 'completed' || ($value['supplier_replay_allowed'] ?? null) !== false
        || ($value['result_sha256'] ?? null) !== ANEX_CONCRETE_FUEL_RETAINED_SHA256
        || ($value['artifact_id'] ?? null) !== 10304744621 || ($value['run_id'] ?? null) !== 34716809979) {
        throw new RuntimeException('CONCRETE_FUEL_RETAINED_INVALID');
    }
    $request = $value['request'] ?? null;
    $expected = ['currency'=>3,'dateBeg'=>'2026-10-12','nights'=>7,'page'=>1,'pageSize'=>10,'tour'=>2637];
    if ($request !== $expected) throw new RuntimeException('CONCRETE_FUEL_RETAINED_CONTEXT');
    $payload = $value['payload'] ?? null;
    if (!is_array($payload) || ($payload['totalCount'] ?? null) !== 1 || ($payload['totalPages'] ?? null) !== 1
        || !is_array($payload['data'] ?? null) || count($payload['data']) !== 1 || !is_array($payload['data'][0] ?? null)) {
        throw new RuntimeException('CONCRETE_FUEL_RETAINED_PAYLOAD');
    }
    $row = $payload['data'][0];
    if (($row['tour'] ?? null) !== 2637 || ($row['currency'] ?? null) !== 3 || ($row['nights'] ?? null) !== 7
        || ($row['dateBeg'] ?? null) !== '2026-10-12T00:00:00') {
        throw new RuntimeException('CONCRETE_FUEL_RETAINED_CONTEXT');
    }
    foreach (['price_adult','price_chd','cashrate','price_converted_adult','price_converted_chd'] as $field) {
        if (anex_concrete_fuel_decimal($row[$field] ?? null) === null) throw new RuntimeException('CONCRETE_FUEL_RETAINED_MONEY');
    }
    return $value;
}

function anex_concrete_fuel_input($value): array
{
    $keys = ['experiment_id','country','date','nights','adults','child_ages','meal_family','currency','retained_additional'];
    if (!is_array($value) || count($value) !== count($keys)
        || array_diff($keys, array_keys($value)) || array_diff(array_keys($value), $keys)
        || ($value['experiment_id'] ?? null) !== ANEX_CONCRETE_FUEL_EXPERIMENT
        || ($value['country'] ?? null) !== 'Turkey' || ($value['date'] ?? null) !== ANEX_CONCRETE_FUEL_DATE
        || ($value['nights'] ?? null) !== ANEX_CONCRETE_FUEL_NIGHTS
        || ($value['adults'] ?? null) !== ANEX_CONCRETE_FUEL_ADULTS
        || ($value['child_ages'] ?? null) !== [] || ($value['meal_family'] ?? null) !== 'ai'
        || ($value['currency'] ?? null) !== 'RUB') {
        throw new RuntimeException('CONCRETE_FUEL_INVALID_INPUT');
    }
    $value['retained_additional'] = anex_concrete_fuel_retained($value['retained_additional'] ?? null);
    return $value;
}

function anex_concrete_fuel_norm($value, int $limit = 180): string
{
    $text = anex_paired_text($value, [], $limit) ?? '';
    $text = function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
    return trim((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', str_replace(['ё','Ё'], 'е', $text)));
}

function anex_concrete_fuel_ai($value): bool
{
    return in_array(anex_concrete_fuel_norm($value, 100), [
        'ai','all','all inclusive','uai','ultra all inclusive','ai without alcohol',
        'все включено','ультра все включено','все включено без алкоголя',
    ], true);
}

function anex_concrete_fuel_dictionary_id(array $rows, array $names): int
{
    $wanted = array_map('anex_concrete_fuel_norm', $names); $found = [];
    foreach (array_slice($rows, 0, 10000) as $row) {
        if (!is_array($row) || !preg_match('/\A[1-9][0-9]{0,7}\z/D', (string) ($row['id'] ?? ''))) continue;
        foreach (['name','nameAlt','alias','currencyISO'] as $field) {
            if (is_string($row[$field] ?? null) && in_array(anex_concrete_fuel_norm($row[$field]), $wanted, true)) {
                $found[(int) $row['id']] = true;
            }
        }
    }
    if (count($found) !== 1) throw new RuntimeException('CONCRETE_FUEL_DICTIONARY');
    return (int) array_key_first($found);
}

function anex_concrete_fuel_dictionary(AnyTourAnexClient $client, string $action, array $params): array
{
    $rows = $client->request($action, $params);
    if (!is_array($rows) || count($rows) > 10000) throw new RuntimeException('CONCRETE_FUEL_DICTIONARY');
    return $rows;
}

function anex_concrete_fuel_offer_price(array $offer): ?array
{
    $price = ($offer['price']['currency'] ?? '') === 'RUB' ? ($offer['price'] ?? null) : ($offer['converted_price'] ?? null);
    if (!is_array($price) || ($price['currency'] ?? null) !== 'RUB') return null;
    $amount = anex_concrete_fuel_decimal($price['amount'] ?? null);
    return $amount === null ? null : ['amount'=>$amount,'currency'=>'RUB'];
}

function anex_concrete_fuel_offer_summary(array $offer): ?array
{
    $price = anex_concrete_fuel_offer_price($offer);
    if ($price === null || !anex_concrete_fuel_ai($offer['meal'] ?? null)
        || ($offer['hotel']['external_id'] ?? null) !== ANEX_CONCRETE_FUEL_EXTERNAL_HOTEL
        || ($offer['checkin'] ?? null) !== ANEX_CONCRETE_FUEL_DATE
        || ($offer['nights'] ?? null) !== ANEX_CONCRETE_FUEL_NIGHTS
        || ($offer['adults'] ?? null) !== ANEX_CONCRETE_FUEL_ADULTS || ($offer['children'] ?? null) !== 0) return null;
    return [
        'kind'=>$offer['kind'] ?? null,'price'=>$price['amount'],'currency'=>'RUB',
        'room'=>anex_paired_text($offer['room'] ?? null, [], 180),'room_norm'=>anex_concrete_fuel_norm($offer['room'] ?? null),
        'placement'=>anex_paired_text($offer['hotel_place'] ?? null, [], 120),
        'placement_norm'=>anex_concrete_fuel_norm($offer['hotel_place'] ?? null, 120),
        'supplier_tour_program_id'=>$offer['supplier_tour_program_id'] ?? null,
        'supplier_currency_id'=>$offer['supplier_currency_id'] ?? null,
        'availability'=>$offer['availability'] ?? null,
        'final_price_verified'=>false,
    ];
}

function anex_concrete_fuel_routes(array $flights): array
{
    $out = [];
    foreach (array_slice($flights['routes'] ?? [], 0, 6) as $route) {
        if (!is_array($route)) continue;
        $entry = ['date'=>anex_paired_text($route['date'] ?? null, [], 40),'from'=>anex_paired_text($route['from'] ?? null),
            'to'=>anex_paired_text($route['to'] ?? null),'options'=>[]];
        foreach (array_slice($route['options'] ?? [], 0, 20) as $option) {
            if (!is_array($option)) continue;
            $entry['options'][] = [
                'name'=>anex_paired_text($option['name'] ?? null, [], 80),
                'carrier'=>anex_paired_text($option['carrier'] ?? null, [], 120),
                'departure_airport'=>anex_paired_text($option['departure']['airport_code'] ?? null, [], 12),
                'departure_time'=>anex_paired_text($option['departure']['time'] ?? null, [], 40),
                'arrival_airport'=>anex_paired_text($option['arrival']['airport_code'] ?? null, [], 12),
                'arrival_time'=>anex_paired_text($option['arrival']['time'] ?? null, [], 40),
                'itinerary_details_available'=>($option['itinerary_details_available'] ?? false) === true,
            ];
        }
        $out[] = $entry;
    }
    return $out;
}

function anex_concrete_fuel_save(string $path, array $value): void
{
    $bytes = json_encode($value, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    if (strlen($bytes) > 4000000) throw new RuntimeException('CONCRETE_FUEL_CHECKPOINT_LIMIT');
    $tmp = $path . '.' . bin2hex(random_bytes(8)); $fh = fopen($tmp, 'x');
    if (!$fh) throw new RuntimeException('CONCRETE_FUEL_CHECKPOINT_WRITE'); chmod($tmp, 0600);
    try {
        if (fwrite($fh, $bytes) !== strlen($bytes) || !fflush($fh)) throw new RuntimeException('CONCRETE_FUEL_CHECKPOINT_WRITE');
        if (function_exists('fsync') && !fsync($fh)) throw new RuntimeException('CONCRETE_FUEL_CHECKPOINT_WRITE');
    } finally { fclose($fh); }
    if (!rename($tmp, $path)) { @unlink($tmp); throw new RuntimeException('CONCRETE_FUEL_CHECKPOINT_WRITE'); }
    $readback = file_get_contents($path);
    if ($readback !== $bytes) throw new RuntimeException('CONCRETE_FUEL_CHECKPOINT_READBACK');
    json_decode($readback, true, 64, JSON_THROW_ON_ERROR);
}

function anex_concrete_fuel_tv(array $local, array &$requestLog, array &$secrets): array
{
    $root = realpath((string) getenv('HOME') . '/www/anytoour.ru');
    $helper = is_file($root.'/data/tourvisor-client-v1.php') ? $root.'/data/tourvisor-client-v1.php' : $root.'/v2/data/tourvisor-client-v1.php';
    require_once $helper; $token = v2_data_tourvisor_token();
    if ($token === '') throw new RuntimeException('CONCRETE_FUEL_TV_TOKEN');
    $secrets[] = $token; $deadline = microtime(true) + 150;
    $operator = anex_paired_operator(anex_paired_tv_get('/operators',[
        'departureId'=>(int)$local['departure_id'],'countryId'=>(int)$local['country_id']],$token,$deadline,$requestLog));
    $criteria = ['departureId'=>(int)$local['departure_id'],'countryId'=>(int)$local['country_id'],
        'dateFrom'=>ANEX_CONCRETE_FUEL_DATE,'dateTo'=>ANEX_CONCRETE_FUEL_DATE,'nightsFrom'=>7,'nightsTo'=>7,
        'adults'=>2,'childs'=>[],'currency'=>'RUB','onlyCharter'=>false,'onlyDirect'=>false,
        'hotelIds'=>[ANEX_CONCRETE_FUEL_LOCAL_HOTEL],'operatorIds'=>[$operator['id']]];
    usleep(1050000); $start = anex_paired_tv_get('/tours/search',$criteria,$token,$deadline,$requestLog);
    $searchId = $start['searchId'] ?? null;
    if ((!is_int($searchId) && !is_string($searchId)) || !preg_match('/\A[1-9][0-9]{0,17}\z/D',(string)$searchId)) {
        throw new RuntimeException('CONCRETE_FUEL_TV_SEARCH');
    }
    $complete = false;
    for ($poll=0; $poll<8 && microtime(true)<$deadline-25; ++$poll) {
        sleep($poll===0 ? 1 : 8);
        $status = anex_paired_tv_get('/tours/search/'.$searchId.'/status',['operatorStatus'=>false],$token,$deadline,$requestLog);
        $complete = (is_numeric($status['progress'] ?? null) && (float)$status['progress'] >= 100)
            || (is_string($status['status'] ?? null) && strtolower($status['status']) === 'complete');
        if ($complete) break;
    }
    usleep(1050000); $groups = anex_paired_tv_get('/tours/search/'.$searchId,['limit'=>25],$token,$deadline,$requestLog);
    $rows = [];
    foreach ($groups as $hotel) {
        if (!is_array($hotel) || (int)($hotel['id'] ?? 0) !== ANEX_CONCRETE_FUEL_LOCAL_HOTEL) continue;
        foreach (is_array($hotel['tours'] ?? null) ? $hotel['tours'] : [] as $tour) {
            if (!is_array($tour) || !anex_concrete_fuel_ai($tour['meal'] ?? null)
                || anex_paired_date($tour['date'] ?? null) !== ANEX_CONCRETE_FUEL_DATE
                || (int)($tour['nights'] ?? 0) !== 7 || (int)($tour['adults'] ?? 2) !== 2
                || (int)($tour['childs'] ?? $tour['children'] ?? 0) !== 0) continue;
            $op = $tour['operator'] ?? null; $opId = anex_paired_id(is_array($op) ? ($op['id'] ?? null) : null);
            if ($opId !== null && $opId !== $operator['id']) continue;
            $price = anex_concrete_fuel_decimal($tour['price'] ?? null); $fuel = anex_concrete_fuel_decimal($tour['fuelCharge'] ?? null);
            if ($price === null || $fuel === null) continue;
            $rows[] = ['price'=>$price,'fuel_charge'=>$fuel,'currency'=>anex_paired_text($tour['currency'] ?? 'RUB', [], 8),
                'room'=>anex_paired_text($tour['roomType'] ?? null, [], 180),'room_norm'=>anex_concrete_fuel_norm($tour['roomType'] ?? null),
                'placement'=>anex_paired_text($tour['placement'] ?? null, [], 120),
                'placement_norm'=>anex_concrete_fuel_norm($tour['placement'] ?? null, 120),'final_price_verified'=>false];
        }
    }
    usort($rows, static fn(array $a,array $b): int => (float)$a['price'] <=> (float)$b['price']);
    return ['offers'=>array_slice($rows,0,40),'search_complete'=>$complete,'requests'=>count($requestLog),
        'coverage'=>['hotel_limit'=>25,'continuation_requested'=>false,'tour_flights_requested'=>false]];
}

function anex_concrete_fuel_main(): array
{
    $started=microtime(true); $pdo=null; $lock=null; $reserved=false; $path=null; $secrets=[]; $tvLog=[]; $anexClient=null;
    $out=['schema_version'=>1,'experiment_id'=>ANEX_CONCRETE_FUEL_EXPERIMENT,'status'=>'blocked','automatic_retry'=>false,
        'supplier_replay_allowed'=>false,'anex_requests'=>0,'tourvisor_requests'=>0,'additional_prices_requests'=>0,
        'andromeda_requests'=>0,'booking_calls'=>0,'broninit_calls'=>0,'mapping_writes'=>0,'group_minimum'=>null,
        'concrete_offers'=>[],'selected_concrete'=>null,'anex_flights'=>null,'additional_prices'=>null,
        'additional_source'=>null,'additional_result_sha256'=>null,'tourvisor'=>null];
    try {
        $raw=file_get_contents('php://stdin',false,null,0,8193); if(!is_string($raw)||$raw===''||strlen($raw)>8192)throw new RuntimeException('CONCRETE_FUEL_INVALID_INPUT');
        $input=anex_concrete_fuel_input(json_decode($raw,true,16,JSON_THROW_ON_ERROR));
        $retained=$input['retained_additional'];
        $home=(string)getenv('HOME'); $root=realpath($home.'/www/anytoour.ru'); $preview=realpath($root.'/_preview/search3-anex-candidate');
        if(!$root||!$preview||$preview!==$root.'/_preview/search3-anex-candidate'||!in_array(realpath((string)getcwd()),[$root,$preview],true))throw new RuntimeException('CONCRETE_FUEL_RUNTIME');
        require_once $home.'/.anytoour-anex/search3-preview.php'; require_once $root.'/config.php';
        if(!defined('ANEX_API_TOKEN')||!is_string(ANEX_API_TOKEN)||trim(ANEX_API_TOKEN)==='')throw new RuntimeException('CONCRETE_FUEL_ANEX_TOKEN');
        $secrets=[ANEX_API_TOKEN];
        $db=is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php'; require_once $db; $pdo=v2_data_db();
        if(!$pdo instanceof PDO||$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql')throw new RuntimeException('CONCRETE_FUEL_DB');
        $lookup=$pdo->prepare("SELECT d.id departure_id,d.name departure_name,c.id country_id,c.name country_name FROM catalog_departures d CROSS JOIN catalog_countries c WHERE d.is_active=1 AND c.is_active=1 AND d.name IN ('Москва','Moscow') AND c.name IN ('Турция','Turkey') LIMIT 2");
        $lookup->execute(); $locals=$lookup->fetchAll(PDO::FETCH_ASSOC); if(count($locals)!==1)throw new RuntimeException('CONCRETE_FUEL_LOCAL'); $local=$locals[0];
        $registry=AnyTourAnexSearchMappingRegistry::fromPdo($pdo);
        if($registry->resolve('anex_online',ANEX_CONCRETE_FUEL_EXTERNAL_HOTEL,'preview')!==ANEX_CONCRETE_FUEL_LOCAL_HOTEL)throw new RuntimeException('CONCRETE_FUEL_IDENTITY_CHANGED');
        $active=$pdo->prepare('SELECT COUNT(*) FROM catalog_hotels WHERE id=? AND country_id=? AND is_active=1');
        $active->execute([ANEX_CONCRETE_FUEL_LOCAL_HOTEL,(int)$local['country_id']]); if((int)$active->fetchColumn()!==1)throw new RuntimeException('CONCRETE_FUEL_IDENTITY_CHANGED');
        $dir=$home.'/.anytoour-anex'; if(!is_dir($dir)||is_link($dir))throw new RuntimeException('CONCRETE_FUEL_CHECKPOINT_DIR');
        $path=$dir.'/'.ANEX_CONCRETE_FUEL_EXPERIMENT.'.json'; $lock=fopen($path.'.lock','c'); if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('CONCRETE_FUEL_LOCK');
        if(is_file($path)){
            $prior=json_decode((string)file_get_contents($path),true,64,JSON_THROW_ON_ERROR);
            if(($prior['status']??null)==='completed'&&is_array($prior['result']??null))return array_replace($prior['result'],['reused'=>true]);
            throw new RuntimeException('CONCRETE_FUEL_NOT_REPLAYABLE');
        }
        anex_concrete_fuel_save($path,['schema_version'=>1,'experiment_id'=>ANEX_CONCRETE_FUEL_EXPERIMENT,'status'=>'reserved','reserved_at'=>gmdate('c')]); $reserved=true;
        $anexClient=new AnyTourAnexClient(ANEX_API_TOKEN);
        $departure=anex_concrete_fuel_dictionary_id(anex_concrete_fuel_dictionary($anexClient,'SearchTour_TOWNFROMS',[]),[$local['departure_name'],'Москва','Moscow']);
        usleep(1050000); $country=anex_concrete_fuel_dictionary_id(anex_concrete_fuel_dictionary($anexClient,'SearchTour_STATES',['TOWNFROMINC'=>$departure]),[$local['country_name'],'Турция','Turkey']);
        usleep(1050000); $dated=['TOWNFROMINC'=>$departure,'STATEINC'=>$country,'CHECKIN_BEG'=>ANEX_CONCRETE_FUEL_DATE_COMPACT,'CHECKIN_END'=>ANEX_CONCRETE_FUEL_DATE_COMPACT,'ADULT'=>2,'CHILD'=>0];
        $currency=anex_concrete_fuel_dictionary_id(anex_concrete_fuel_dictionary($anexClient,'SearchTour_CURRENCIES',$dated),['RUB','RUR','Рубль','Рубли','Руб']);
        $criteria=['supplier_namespace'=>'anex_online','departure_id'=>$departure,'destination_id'=>$country,'currency_id'=>$currency,
            'checkin_begin'=>ANEX_CONCRETE_FUEL_DATE,'checkin_end'=>ANEX_CONCRETE_FUEL_DATE,'nights_from'=>7,'nights_till'=>7,
            'adults'=>2,'children'=>0,'child_ages'=>[],'hotel_ids'=>[ANEX_CONCRETE_FUEL_EXTERNAL_HOTEL]];
        $search=new AnyTourAnexSearch($anexClient,$registry->previewResolver(),$secrets); usleep(1050000); $page=$search->search($criteria);
        $eligible=[]; foreach($page['offers']??[] as $offer){$summary=anex_concrete_fuel_offer_summary($offer);if($summary!==null)$eligible[]=['raw'=>$offer,'summary'=>$summary];}
        if(!$eligible)throw new RuntimeException('CONCRETE_FUEL_ANEX_EMPTY');
        usort($eligible,static fn(array $a,array $b):int=>(float)$a['summary']['price']<=>(float)$b['summary']['price']);
        $first=$eligible[0]; $out['group_minimum']=$first['summary']; $concrete=[];
        if(($first['raw']['kind']??null)==='group_minimum'){
            usleep(1050000); $expanded=$search->expand($first['raw']['offer_key']);
            foreach($expanded['offers']??[] as $offer){$summary=anex_concrete_fuel_offer_summary($offer);if($summary!==null&&($summary['kind']??null)==='concrete')$concrete[]=['raw'=>$offer,'summary'=>$summary];}
        } else {
            foreach($eligible as $row)if(($row['summary']['kind']??null)==='concrete')$concrete[]=$row;
        }
        if(!$concrete)throw new RuntimeException('CONCRETE_FUEL_NO_CONCRETE');
        usort($concrete,static fn(array $a,array $b):int=>(float)$a['summary']['price']<=>(float)$b['summary']['price']);
        foreach(array_slice($concrete,0,30) as $row)$out['concrete_offers'][]=$row['summary'];
        $selected=$concrete[0]; $tour=$selected['summary']['supplier_tour_program_id']??null; $nativeCurrency=$selected['summary']['supplier_currency_id']??null;
        if(!is_string($tour)||!preg_match('/\A[1-9][0-9]{0,17}\z/D',$tour)||!is_string($nativeCurrency)||!preg_match('/\A[1-9][0-9]{0,17}\z/D',$nativeCurrency))throw new RuntimeException('CONCRETE_FUEL_PROGRAM');
        if($tour!==(string)$retained['request']['tour']||$nativeCurrency!==(string)$retained['request']['currency'])throw new RuntimeException('CONCRETE_FUEL_PROGRAM_MISMATCH');
        $out['selected_concrete']=$selected['summary']; usleep(1050000); $flightResult=$search->flights($selected['raw']['offer_key']);
        $out['anex_flights']=['routes'=>anex_concrete_fuel_routes($flightResult),'selected'=>false,'final_price_verified'=>false];
        $out['anex_requests']=$anexClient->requestsMade();
        $out['additional_request_context']=$retained['request'];$out['additional_prices']=$retained['payload'];
        $out['additional_source']='retained_completed_operation';$out['additional_result_sha256']=$retained['result_sha256'];
        $out['tourvisor']=anex_concrete_fuel_tv($local,$tvLog,$secrets); $out['tourvisor_requests']=count($tvLog);
        $out['status']='completed'; $out['supplier_effect']='read_only_search_expand_flights_tv_with_retained_additional_completed'; $out['reused']=false;
        anex_concrete_fuel_save($path,['schema_version'=>1,'experiment_id'=>ANEX_CONCRETE_FUEL_EXPERIMENT,'status'=>'completed','completed_at'=>gmdate('c'),'result'=>$out]); $reserved=false;
    } catch(Throwable $e) {
        $code=$e->getMessage(); $safe=preg_match('/\A(?:CONCRETE_FUEL|PAIRED_TV)_[A-Z0-9_]{1,90}\z/D',$code)?$code:'CONCRETE_FUEL_UNCONFIRMED';
        $out['status']=$reserved?'unknown':'blocked';$out['reason']=$safe;$out['supplier_effect']=$reserved?'unknown':'none';
        if($reserved&&is_string($path)){try{anex_concrete_fuel_save($path,['schema_version'=>1,'experiment_id'=>ANEX_CONCRETE_FUEL_EXPERIMENT,'status'=>'unknown','recorded_at'=>gmdate('c'),'reason'=>$safe]);}catch(Throwable $ignored){}}
    } finally {
        if(is_resource($lock)){flock($lock,LOCK_UN);fclose($lock);} if($pdo instanceof PDO&&$pdo->inTransaction())$pdo->rollBack();
        if($anexClient instanceof AnyTourAnexClient)$out['anex_requests']=$anexClient->requestsMade();
        $out['tourvisor_requests']=max($out['tourvisor_requests'],count($tvLog));$out['elapsed_ms']=(int)round((microtime(true)-$started)*1000);
    }
    $json=json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    foreach($secrets as $secret)if($secret!==''&&is_string($json)&&strpos($json,$secret)!==false)return['schema_version'=>1,'experiment_id'=>ANEX_CONCRETE_FUEL_EXPERIMENT,'status'=>'unknown','reason'=>'CONCRETE_FUEL_OUTPUT_REDACTED','automatic_retry'=>false,'supplier_replay_allowed'=>false,'booking_calls'=>0,'broninit_calls'=>0,'mapping_writes'=>0];
    return $out;
}

if(!defined('ANYTOUR_ANEX_CONCRETE_FUEL_LIBRARY_ONLY')){
    error_reporting(0);ob_start();$report=anex_concrete_fuel_main();ob_end_clean();
    echo json_encode($report,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),"\n";exit(($report['status']??null)==='completed'?0:1);
}
