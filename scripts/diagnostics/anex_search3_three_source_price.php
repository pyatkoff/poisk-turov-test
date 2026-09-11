<?php
declare(strict_types=1);

if (!function_exists('anex_paired_text')) {
    if (!defined('ANYTOUR_ANEX_PAIRED_LIBRARY_ONLY')) define('ANYTOUR_ANEX_PAIRED_LIBRARY_ONLY', true);
    require __DIR__ . '/anex_search3_paired_runner.php';
}

const ANEX_THREE_PRICE_EXPERIMENT = 'anex_three_source_price_20260911_v1';
const ANEX_THREE_PRICE_CASES = ['anex','andromeda','tourvisor'];

function anex_three_price_input($value): array
{
    $keys = ['experiment_id','case_id','country','date','nights','adults','child_ages','meal_family','currency'];
    if (!is_array($value) || count($value) !== count($keys) || array_diff($keys, array_keys($value))
        || array_diff(array_keys($value), $keys)
        || ($value['experiment_id'] ?? null) !== ANEX_THREE_PRICE_EXPERIMENT
        || !in_array($value['case_id'] ?? null, ANEX_THREE_PRICE_CASES, true)
        || ($value['country'] ?? null) !== 'Turkey' || ($value['date'] ?? null) !== '2026-09-20'
        || ($value['nights'] ?? null) !== 7 || ($value['adults'] ?? null) !== 2
        || ($value['child_ages'] ?? null) !== [] || ($value['meal_family'] ?? null) !== 'ai'
        || ($value['currency'] ?? null) !== 'RUB') {
        throw new RuntimeException('THREE_PRICE_INVALID_INPUT');
    }
    return $value;
}

function anex_three_price_norm($value): string
{
    $text = anex_paired_text($value, [], 240) ?? '';
    $text = function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
    $text = str_replace(['ё','Ё'], 'е', $text);
    return trim((string)preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text));
}

function anex_three_price_meal($value): ?string
{
    $name = anex_three_price_norm($value);
    return in_array($name, ['ai','all','all inclusive','uai','ultra all inclusive','ai without alcohol',
        'все включено','ультра все включено','все включено без алкоголя'], true) ? 'ai' : null;
}

function anex_three_price_decimal($value): ?string
{
    if (is_int($value) || (is_float($value) && is_finite($value))) $value = (string)$value;
    return is_string($value) && preg_match('/\A(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?\z/D', $value)
        && preg_match('/[1-9]/', $value) ? $value : null;
}

function anex_three_price_save(string $path, array $value): void
{
    $bytes = json_encode($value, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    if (strlen($bytes) > 3500000) throw new RuntimeException('THREE_PRICE_CHECKPOINT_LIMIT');
    $tmp = $path . '.' . bin2hex(random_bytes(8));
    $fh = fopen($tmp, 'x'); if (!$fh) throw new RuntimeException('THREE_PRICE_CHECKPOINT_WRITE');
    chmod($tmp, 0600);
    try {
        if (fwrite($fh, $bytes) !== strlen($bytes) || !fflush($fh)) throw new RuntimeException('THREE_PRICE_CHECKPOINT_WRITE');
        if (function_exists('fsync') && !fsync($fh)) throw new RuntimeException('THREE_PRICE_CHECKPOINT_WRITE');
    } finally { fclose($fh); }
    if (!rename($tmp, $path)) { @unlink($tmp); throw new RuntimeException('THREE_PRICE_CHECKPOINT_WRITE'); }
    $back = json_decode((string)file_get_contents($path), true, 48, JSON_THROW_ON_ERROR);
    if ($back !== $value) throw new RuntimeException('THREE_PRICE_CHECKPOINT_READBACK');
}

function anex_three_price_subject(PDO $pdo, int $countryId, AnyTourAnexSearchMappingRegistry $registry, array $andromedaCatalog): array
{
    $catalog = [];
    foreach ($andromedaCatalog['all']['payload']['HOTELS'] ?? [] as $row) {
        if (is_array($row) && preg_match('/\A[1-9][0-9]{0,127}\z/D', (string)($row['id'] ?? ''))) $catalog[(string)$row['id']] = true;
    }
    if (!$catalog) throw new RuntimeException('THREE_PRICE_ANDROMEDA_CATALOG_EMPTY');
    $sql = "SELECT h.id AS local_id,h.name,m.anex_hotel_id,i.external_hotel_id AS andromeda_hotel_id,"
        . "COALESCE(o.search_count,0) AS search_count FROM catalog_hotels h "
        . "JOIN anex_hotel_search_mappings m ON m.catalog_hotel_id=h.id "
        . "JOIN andromeda_hotel_identities i ON i.local_hotel_id=h.id "
        . "LEFT JOIN anex_search_hotel_observations o ON o.anex_hotel_id=m.anex_hotel_id "
        . "WHERE h.is_active=1 AND h.country_id=? AND m.enabled=1 AND m.scope='preview' "
        . "AND i.decision_status='accepted' AND i.supplier_namespace='andromeda_catalog' "
        . "ORDER BY COALESCE(o.search_count,0) DESC,h.id ASC LIMIT 1000";
    $q = $pdo->prepare($sql); $q->execute([$countryId]);
    $seenLocal = [];
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $local = (int)$row['local_id']; $anex = (string)$row['anex_hotel_id']; $andr = (string)$row['andromeda_hotel_id'];
        if ($local < 1 || !preg_match('/\A[1-9][0-9]{0,8}\z/D',$anex)
            || !preg_match('/\A[1-9][0-9]{0,127}\z/D',$andr) || !isset($catalog[$andr])) continue;
        if ($registry->resolve('anex_online',$anex,'preview') !== $local) continue;
        if (isset($seenLocal[$local]) && $seenLocal[$local] !== $andr) continue;
        $seenLocal[$local] = $andr;
        $check = $pdo->prepare("SELECT COUNT(*) FROM andromeda_hotel_identities WHERE local_hotel_id=? AND decision_status='accepted' AND supplier_namespace='andromeda_catalog'");
        $check->execute([$local]);
        if ((int)$check->fetchColumn() !== 1) continue;
        return ['local_hotel_id'=>$local,'anex_hotel_id'=>(int)$anex,'andromeda_hotel_id'=>$andr,
            'hotel_name'=>anex_paired_text($row['name'] ?? null, [], 240),'selection_basis'=>'current_unique_triple_mapping',
            'anex_observation_count'=>(int)$row['search_count']];
    }
    throw new RuntimeException('THREE_PRICE_TRIPLE_SUBJECT_UNAVAILABLE');
}

function anex_three_price_current_subject(PDO $pdo, array $subject, int $countryId, AnyTourAnexSearchMappingRegistry $registry): void
{
    if ($registry->resolve('anex_online',(string)$subject['anex_hotel_id'],'preview') !== (int)$subject['local_hotel_id'])
        throw new RuntimeException('THREE_PRICE_SUBJECT_CHANGED');
    $q=$pdo->prepare("SELECT COUNT(*) FROM andromeda_hotel_identities i JOIN catalog_hotels h ON h.id=i.local_hotel_id WHERE i.local_hotel_id=? AND i.external_hotel_id=? AND i.decision_status='accepted' AND i.supplier_namespace='andromeda_catalog' AND h.is_active=1 AND h.country_id=?");
    $q->execute([(int)$subject['local_hotel_id'],(string)$subject['andromeda_hotel_id'],$countryId]);
    if ((int)$q->fetchColumn() !== 1) throw new RuntimeException('THREE_PRICE_SUBJECT_CHANGED');
}

function anex_three_price_offer(string $provider, int $local, $external, $date, $nights, $adults, $children,
    $meal, $room, $placement, $price, $currency, $fuel = null): ?array
{
    $date = anex_paired_date($date); $amount = anex_three_price_decimal($price); $mealFamily = anex_three_price_meal($meal);
    if ($date === null || $amount === null || $mealFamily !== 'ai' || (int)$nights !== 7 || (int)$adults !== 2 || (int)$children !== 0) return null;
    $currency = strtoupper((string)(anex_paired_text($currency, [], 8) ?? ''));
    if ($currency !== 'RUB') return null;
    $fuelValue = null;
    if ($fuel !== null) {
        if (is_array($fuel)) $fuel = $fuel['value'] ?? $fuel['amount'] ?? null;
        if (is_numeric($fuel) && (float)$fuel >= 0) $fuelValue = (string)$fuel;
    }
    return ['provider'=>$provider,'local_hotel_id'=>$local,'external_hotel_id'=>(string)$external,'date'=>$date,'nights'=>7,
        'adults'=>2,'children'=>0,'meal_family'=>'ai','meal_label'=>anex_paired_text($meal,[],80),
        'room'=>anex_paired_text($room,[],180),'room_norm'=>anex_three_price_norm($room),
        'placement'=>anex_paired_text($placement,[],120),'placement_norm'=>anex_three_price_norm($placement),
        'price'=>$amount,'currency'=>'RUB','fuel_charge'=>$fuelValue,
        'fuel_inclusion_verified'=>false,'final_price_verified'=>false];
}

function anex_three_price_tv(PDO $pdo, array $local, array $subject, array &$requestLog, array &$secrets): array
{
    $root=realpath((string)getenv('HOME').'/www/anytoour.ru');
    $helper=is_file($root.'/data/tourvisor-client-v1.php')?$root.'/data/tourvisor-client-v1.php':$root.'/v2/data/tourvisor-client-v1.php';
    require_once $helper; $token=v2_data_tourvisor_token();
    if ($token==='') throw new RuntimeException('THREE_PRICE_TV_TOKEN_REQUIRED'); $secrets[]=$token;
    $deadline=microtime(true)+160;
    $criteria=['departureId'=>(int)$local['departure_id'],'countryId'=>(int)$local['country_id'],'dateFrom'=>'2026-09-20','dateTo'=>'2026-09-20',
        'nightsFrom'=>7,'nightsTo'=>7,'adults'=>2,'childs'=>[],'currency'=>'RUB','onlyCharter'=>false,'onlyDirect'=>false,
        'hotelIds'=>[(int)$subject['local_hotel_id']]];
    $operator=anex_paired_operator(anex_paired_tv_get('/operators',['departureId'=>$criteria['departureId'],'countryId'=>$criteria['countryId']],$token,$deadline,$requestLog));
    $criteria['operatorIds']=[$operator['id']]; usleep(1050000);
    $start=anex_paired_tv_get('/tours/search',$criteria,$token,$deadline,$requestLog); $searchId=$start['searchId']??null;
    if ((!is_int($searchId)&&!is_string($searchId))||!preg_match('/\A[1-9][0-9]{0,17}\z/D',(string)$searchId)) throw new RuntimeException('THREE_PRICE_TV_SEARCH_ID');
    $complete=false;
    for($poll=0;$poll<8 && microtime(true)<$deadline-30;++$poll){sleep($poll===0?1:10);$status=anex_paired_tv_get('/tours/search/'.$searchId.'/status',['operatorStatus'=>false],$token,$deadline,$requestLog);
        $complete=(is_numeric($status['progress']??null)&&(float)$status['progress']>=100)||(is_string($status['status']??null)&&strtolower($status['status'])==='complete');if($complete)break;}
    usleep(1050000);$groups=anex_paired_tv_get('/tours/search/'.$searchId,['limit'=>100],$token,$deadline,$requestLog);
    $offers=[];
    foreach($groups as $hotel){if(!is_array($hotel)||(int)($hotel['id']??0)!==(int)$subject['local_hotel_id'])continue;
        foreach(is_array($hotel['tours']??null)?$hotel['tours']:[] as $tour){if(!is_array($tour))continue;
            $op=$tour['operator']??null;$opId=anex_paired_id($tour['operatorId']??(is_array($op)?($op['id']??null):null));
            $opName=anex_paired_text($op,$secrets);$same=in_array(anex_paired_operator_name($opName),['anex','anex tour','anextour','анекс','анекс тур'],true);
            if(($opId!==null&&$opId!==$operator['id'])||($opId===null&&!$same))continue;
            $row=anex_three_price_offer('tourvisor',(int)$subject['local_hotel_id'],(int)$subject['local_hotel_id'],$tour['date']??null,$tour['nights']??null,
                $tour['adults']??2,$tour['children']??$tour['childs']??0,$tour['meal']??null,$tour['roomType']??null,$tour['placement']??null,
                $tour['price']??null,$tour['currency']??'RUB',$tour['fuelCharge']??null);if($row!==null)$offers[]=$row;
        }}
    return ['offers'=>$offers,'search_complete'=>$complete,'operator'=>'ANEX','source_price_semantics'=>'tourvisor_documented_final_display_price',
        'fuel_field_semantics'=>'api_exposes_fuelCharge_separately_do_not_add_automatically'];
}

function anex_three_price_anex(PDO $pdo, array $local, array $subject, AnyTourAnexSearchMappingRegistry $registry, array &$secrets): array
{
    $home=(string)getenv('HOME');$root=realpath($home.'/www/anytoour.ru');$preview=realpath($root.'/_preview/search3-anex-candidate');
    require_once $home.'/.anytoour-anex/search3-preview.php';
    if(!defined('ANEX_API_TOKEN')||!is_string(ANEX_API_TOKEN)||trim(ANEX_API_TOKEN)==='')throw new RuntimeException('THREE_PRICE_ANEX_TOKEN_REQUIRED');
    $secrets[]=ANEX_API_TOKEN;$client=new AnyTourAnexClient(ANEX_API_TOKEN);$cache=[];
    $departure=anytour_anex_search3_dictionary_id(anytour_anex_search3_dictionary($client,'SearchTour_TOWNFROMS',[],$cache),[$local['departure_name'],'Москва','Moscow']);
    $country=anytour_anex_search3_dictionary_id(anytour_anex_search3_dictionary($client,'SearchTour_STATES',['TOWNFROMINC'=>$departure],$cache),[$local['country_name'],'Турция','Turkey']);
    $dated=['TOWNFROMINC'=>$departure,'STATEINC'=>$country,'CHECKIN_BEG'=>'20260920','CHECKIN_END'=>'20260920','ADULT'=>2,'CHILD'=>0];
    $currency=anytour_anex_search3_dictionary_id(anytour_anex_search3_dictionary($client,'SearchTour_CURRENCIES',$dated,$cache),['RUB','RUR','Рубль','Рубли','Руб']);
    $criteria=['supplier_namespace'=>'anex_online','departure_id'=>$departure,'destination_id'=>$country,'currency_id'=>$currency,
        'checkin_begin'=>'2026-09-20','checkin_end'=>'2026-09-20','nights_from'=>7,'nights_till'=>7,'adults'=>2,'children'=>0,'child_ages'=>[],
        'hotel_ids'=>[(string)$subject['anex_hotel_id']]];
    $search=new AnyTourAnexSearch($client,$registry->previewResolver(),$secrets);$result=$search->search($criteria);$offers=[];
    foreach($result['offers'] as $offer){$price=($offer['price']['currency']??'')==='RUB'?$offer['price']:($offer['converted_price']??$offer['price']);
        if(($offer['hotel']['local_id']??null)!==(int)$subject['local_hotel_id'])continue;
        $row=anex_three_price_offer('anex',(int)$subject['local_hotel_id'],(string)$subject['anex_hotel_id'],$offer['checkin']??null,$offer['nights']??null,
            $offer['adults']??null,$offer['children']??null,$offer['meal']??null,$offer['room']??null,$offer['hotel_place']??null,$price['amount']??null,$price['currency']??null,null);
        if($row!==null)$offers[]=$row;}
    $obs=AnyTourAnexSearchObservations::record($pdo,$result['offers'],['country_id'=>(int)$local['country_id'],'anex_country_id'=>$country,'checkin_from'=>'2026-09-20','checkin_to'=>'2026-09-20']);
    return ['offers'=>$offers,'observation'=>$obs,'requests'=>$client->requestsMade(),'source_price_semantics'=>'search_price_unverified_until_additional_prices_or_quote',
        'fuel_field_semantics'=>'no_fuel_field_in_search_projection; AdditionalPricesDaily tracked separately'];
}

function anex_three_price_andromeda(PDO $pdo, array $local, array $subject): array
{
    $root=realpath((string)getenv('HOME').'/www/anytoour.ru');$preview=realpath($root.'/_preview/search3-anex-candidate');
    $_SERVER['SCRIPT_FILENAME']='';require_once $preview.'/api-andromeda-search3-preview.php';
    $configPath=$preview.'/.andromeda-private.php';if(!is_file($configPath)||is_link($configPath))throw new RuntimeException('THREE_PRICE_ANDROMEDA_CONFIG');
    $config=require $configPath;if(!is_array($config)||($config['enabled']??null)!==true)throw new RuntimeException('THREE_PRICE_ANDROMEDA_CONFIG');
    $request=['generation'=>26091101,'page'=>1,'params'=>['departureId'=>(int)$local['departure_id'],'countryId'=>(int)$local['country_id'],
        'dateFrom'=>'2026-09-20','dateTo'=>'2026-09-20','nightsFrom'=>7,'nightsTo'=>7,'adults'=>2,'childs'=>[],
        'currency'=>'RUB','meal'=>7,'onlyCharter'=>false,'onlyDirect'=>false,'hotelIds'=>[(int)$subject['local_hotel_id']],
        'regionIds'=>[],'subregionIds'=>[],'arrivalId'=>null,'operatorIds'=>[],'hotelServices'=>[],'hotelTypes'=>[]],
        'andromeda_operator_ids'=>['5']];
    $saved=anytour_andromeda_search3_catalog($config,$request);$session='three-price-20260911-v1';
    $result=anytour_andromeda_search3_run($request,$pdo,$saved,$config,$session);$offers=[];
    foreach($result['hotels']??[] as $hotel){if((int)($hotel['local_id']??0)!==(int)$subject['local_hotel_id'])continue;
        foreach($hotel['tours']??[] as $tour){$price=$tour['price']??[];
            $row=anex_three_price_offer('andromeda',(int)$subject['local_hotel_id'],(string)$subject['andromeda_hotel_id'],$tour['checkin']??null,$tour['nights']??null,
                $tour['adults']??null,$tour['children']??null,$tour['meal']??null,$tour['room']??null,$tour['placement']??null,$price['amount']??null,$price['currency']??null,null);
            if($row!==null)$offers[]=$row;}}
    return ['offers'=>$offers,'received_offers'=>$result['received_offers']??null,'mapped_offers'=>$result['mapped_offers']??null,'pages_count'=>$result['pages_count']??null,
        'source_price_semantics'=>'andromeda_search_price_unverified_until_package_or_calc','fuel_field_semantics'=>'documented action=price has no separate fuel field'];
}

function anex_three_price_completed_result(array $out, array $subject, array $result, bool $reused = false): array
{
    return array_replace($out, [
        'status'=>'completed',
        'subject'=>$subject,
        'offers'=>$result['offers']??[],
        'details'=>array_diff_key($result,['offers'=>true]),
        'supplier_effect'=>'read_only_search_completed',
        'reused'=>$reused,
    ]);
}

function anex_three_price_reused_result(array $result): array
{
    return array_replace($result, ['reused'=>true]);
}

function anex_three_price_main(): array
{
    $started=microtime(true);$pdo=null;$reserved=false;$lock=null;$secrets=[];$tvLog=[];
    $out=['schema_version'=>1,'experiment_id'=>ANEX_THREE_PRICE_EXPERIMENT,'status'=>'blocked','case_id'=>null,'offers'=>[],
        'supplier_effect'=>'none','automatic_retry'=>false,'booking_calls'=>0,'broninit_calls'=>0,'mapping_writes'=>0];
    try{
        $raw=file_get_contents('php://stdin',false,null,0,4097);if(!is_string($raw)||$raw===''||strlen($raw)>4096)throw new RuntimeException('THREE_PRICE_INVALID_INPUT');
        $input=anex_three_price_input(json_decode($raw,true,8,JSON_THROW_ON_ERROR));$case=$input['case_id'];$out['case_id']=$case;
        $home=(string)getenv('HOME');$root=realpath($home.'/www/anytoour.ru');$preview=realpath($root.'/_preview/search3-anex-candidate');
        if(!$root||!$preview||$preview!==$root.'/_preview/search3-anex-candidate'||!in_array(realpath((string)getcwd()),[$root,$preview],true))throw new RuntimeException('THREE_PRICE_RUNTIME');
        require_once $home.'/.anytoour-anex/search3-preview.php';require_once $preview.'/app/integrations/anex-search.php';
        require_once $preview.'/app/integrations/anex-search-mapping-registry.php';require_once $preview.'/app/integrations/anex-search-observations.php';
        $db=is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php';require_once $db;$pdo=v2_data_db();
        if(!$pdo instanceof PDO||$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql')throw new RuntimeException('THREE_PRICE_DB');
        $q=$pdo->prepare("SELECT d.id departure_id,d.name departure_name,c.id country_id,c.name country_name FROM catalog_departures d CROSS JOIN catalog_countries c WHERE d.is_active=1 AND c.is_active=1 AND d.name IN ('Москва','Moscow') AND c.name IN ('Турция','Turkey') LIMIT 2");$q->execute();$rows=$q->fetchAll(PDO::FETCH_ASSOC);
        if(count($rows)!==1)throw new RuntimeException('THREE_PRICE_LOCAL_IDENTITY');$local=$rows[0];
        $registry=AnyTourAnexSearchMappingRegistry::fromPdo($pdo);
        $andConfig=$preview.'/.andromeda-private.php';if(!is_file($andConfig)||is_link($andConfig))throw new RuntimeException('THREE_PRICE_ANDROMEDA_CONFIG');$cfg=require $andConfig;
        $_SERVER['SCRIPT_FILENAME']='';require_once $preview.'/api-andromeda-search3-preview.php';$catalog=anytour_andromeda_search3_catalog($cfg,['params'=>['countryId'=>(int)$local['country_id']]]);
        $dir=$home.'/.anytoour-anex';if(!is_dir($dir)||is_link($dir))throw new RuntimeException('THREE_PRICE_CHECKPOINT_DIR');
        $path=$dir.'/'.ANEX_THREE_PRICE_EXPERIMENT.'.json';$lock=fopen($path.'.lock','c');if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('THREE_PRICE_LOCK');
        $state=is_file($path)?json_decode((string)file_get_contents($path),true,48,JSON_THROW_ON_ERROR):[];
        if($state && (($state['experiment_id']??null)!==ANEX_THREE_PRICE_EXPERIMENT||!is_array($state['subject']??null)||!is_array($state['cases']??null)))throw new RuntimeException('THREE_PRICE_CHECKPOINT_INVALID');
        if(!$state){$subject=anex_three_price_subject($pdo,(int)$local['country_id'],$registry,$catalog);$state=['schema_version'=>1,'experiment_id'=>ANEX_THREE_PRICE_EXPERIMENT,'spec'=>array_diff_key($input,['case_id'=>true]),'subject'=>$subject,'cases'=>[]];anex_three_price_save($path,$state);}else{$subject=$state['subject'];}
        anex_three_price_current_subject($pdo,$subject,(int)$local['country_id'],$registry);
        $prior=$state['cases'][$case]??null;
        if(is_array($prior)){
            if(($prior['status']??null)==='completed'&&is_array($prior['result']??null)){return anex_three_price_reused_result($prior['result']);}
            throw new RuntimeException('THREE_PRICE_CASE_NOT_REPLAYABLE');
        }
        $state['cases'][$case]=['status'=>'reserved','reserved_at'=>gmdate('c')];anex_three_price_save($path,$state);$reserved=true;$out['supplier_effect']='unknown_after_reservation';
        if($case==='anex')$result=anex_three_price_anex($pdo,$local,$subject,$registry,$secrets);
        elseif($case==='andromeda')$result=anex_three_price_andromeda($pdo,$local,$subject);
        else $result=anex_three_price_tv($pdo,$local,$subject,$tvLog,$secrets);
        $out=anex_three_price_completed_result($out,$subject,$result,false);
        $state['cases'][$case]=['status'=>'completed','completed_at'=>gmdate('c'),'result'=>$out];anex_three_price_save($path,$state);$reserved=false;
    }catch(Throwable $e){$code=$e->getMessage();$safe=preg_match('/\A(?:THREE_PRICE|ANEX|ANDROMEDA)_[A-Z0-9_]{1,80}\z/D',$code)?$code:'THREE_PRICE_UNCONFIRMED';$out['reason']=$safe;
        if($reserved && isset($state,$case,$path)){$state['cases'][$case]=['status'=>'unknown','recorded_at'=>gmdate('c'),'reason'=>$safe];try{anex_three_price_save($path,$state);}catch(Throwable $ignored){}$out['status']='unknown';$out['supplier_effect']='unknown';}}
    finally{if(is_resource($lock)){flock($lock,LOCK_UN);fclose($lock);}if($pdo instanceof PDO&&$pdo->inTransaction())$pdo->rollBack();$out['elapsed_ms']=(int)round((microtime(true)-$started)*1000);$out['tv_request_count']=count($tvLog);}
    $json=json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);foreach($secrets as $secret)if($secret!==''&&is_string($json)&&strpos($json,$secret)!==false)return ['schema_version'=>1,'experiment_id'=>ANEX_THREE_PRICE_EXPERIMENT,'case_id'=>$out['case_id'],'status'=>'unknown','reason'=>'THREE_PRICE_OUTPUT_REDACTED','supplier_effect'=>'unknown','automatic_retry'=>false,'booking_calls'=>0,'broninit_calls'=>0,'mapping_writes'=>0];
    return $out;
}

if (!defined('ANYTOUR_ANEX_THREE_PRICE_LIBRARY_ONLY')) {
    error_reporting(0);ob_start();$report=anex_three_price_main();ob_end_clean();echo json_encode($report,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),"\n";
    exit(($report['status']??null)==='completed'?0:1);
}
