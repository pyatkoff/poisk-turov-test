<?php
declare(strict_types=1);

const ANEX_ADDITIONAL_GREEN_GOLD_OPERATION = 'anex-additional-green-gold-20260912-v7';
const ANEX_ADDITIONAL_GREEN_GOLD_TOUR = 2637;
const ANEX_ADDITIONAL_GREEN_GOLD_DATE = '2026-10-05';
const ANEX_ADDITIONAL_GREEN_GOLD_NIGHTS = 7;
const ANEX_ADDITIONAL_GREEN_GOLD_CURRENCY = 3;
const ANEX_ADDITIONAL_GREEN_GOLD_MAX_BYTES = 262144;
const ANEX_ADDITIONAL_GREEN_GOLD_ENDPOINT = 'https://api.anextour.ru/b2b/AdditionalPricesDaily';

function anytour_anex_additional_decimal($value): ?string
{
    if (is_int($value) || (is_float($value) && is_finite($value))) $value = (string) $value;
    if (!is_string($value) || !preg_match('/\A(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,4})?\z/D', $value)) return null;
    return $value;
}
function anytour_anex_additional_label($value, int $limit = 160): ?string
{
    if (!is_string($value) || $value === '' || strlen($value) > $limit || preg_match('/[\x00-\x1f\x7f<>]/', $value)) return null;
    return $value;
}
function anytour_anex_additional_query(array $criteria): string
{
    $expected = ['tour'=>2637,'dateBeg'=>'2026-10-05','nights'=>7,'currency'=>3,'page'=>1,'pageSize'=>10];
    if ($criteria !== $expected) throw new RuntimeException('ANEX_ADDITIONAL_CRITERIA');
    return http_build_query(['page'=>1,'pageSize'=>10,'tour'=>2637,'dateBeg'=>'2026-10-05','nights'=>7,'currency'=>3], '', '&', PHP_QUERY_RFC3986);
}
/** Exactly one bounded HTTPS request. No redirects, retries or hidden arithmetic. */
function anytour_anex_additional_http_get(string $token, array $criteria): array
{
    if (!function_exists('curl_init')) throw new RuntimeException('ANEX_ADDITIONAL_CURL');
    $query = anytour_anex_additional_query($criteria);
    $url = ANEX_ADDITIONAL_GREEN_GOLD_ENDPOINT . '?' . $query;
    $ch = curl_init($url);
    if ($ch === false) throw new RuntimeException('ANEX_ADDITIONAL_CURL');
    $body = '';
    $overflow = false;
    curl_setopt_array($ch, [
        CURLOPT_HTTPGET => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_MAXREDIRS => 0,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERAGENT => 'TourismPlus',
        CURLOPT_HTTPHEADER => ['Accept: application/json', 'Authorization: Bearer ' . $token],
        CURLOPT_HEADER => false,
        CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body, &$overflow): int {
            if (strlen($body) + strlen($chunk) > ANEX_ADDITIONAL_GREEN_GOLD_MAX_BYTES) { $overflow = true; return 0; }
            $body .= $chunk; return strlen($chunk);
        },
    ]);
    if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTPS')) curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
    $ok = curl_exec($ch);
    $errno = curl_errno($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    if ($overflow) throw new RuntimeException('ANEX_ADDITIONAL_RESPONSE_LIMIT');
    if ($ok !== true || $errno !== 0) throw new RuntimeException('ANEX_ADDITIONAL_TRANSPORT');
    if ($status !== 200) throw new RuntimeException('ANEX_ADDITIONAL_HTTP');
    if ($body === '' || strlen($body) > ANEX_ADDITIONAL_GREEN_GOLD_MAX_BYTES) throw new RuntimeException('ANEX_ADDITIONAL_RESPONSE');
    return ['body'=>$body,'diagnostics'=>['http_status'=>$status,'response_bytes'=>strlen($body),'content_type'=>anytour_anex_additional_label($contentType,120)]];
}
/** Keep only bounded, non-secret supplier facts. No currency or fuel equivalence is inferred. */
function anytour_anex_additional_sanitize_payload($payload): array
{
    if (is_string($payload)) {
        try { $payload = json_decode($payload, true, 64, JSON_THROW_ON_ERROR); }
        catch (Throwable $ignored) { throw new RuntimeException('ANEX_ADDITIONAL_RESPONSE'); }
    }
    if (!is_array($payload) || ($payload !== [] && array_keys($payload) !== range(0, count($payload) - 1)) || count($payload) > 1000) throw new RuntimeException('ANEX_ADDITIONAL_RESPONSE');
    $rows=[];$currencies=[];$pages=[];
    foreach (array_slice($payload,0,100) as $row) {
        if (!is_array($row)) throw new RuntimeException('ANEX_ADDITIONAL_RESPONSE');
        $amount=anytour_anex_additional_decimal($row['additionalPriceAmount']??null);
        $currencyAlias=anytour_anex_additional_label($row['keyAlias']??null,24);
        $currencyName=anytour_anex_additional_label($row['keyName']??null,80);
        $currency=$currencyAlias??$currencyName;if($currency!==null)$currencies[$currency]=true;
        $pageCount=$row['pagesCount']??null;if(is_string($pageCount)&&ctype_digit($pageCount))$pageCount=(int)$pageCount;
        if(is_int($pageCount)&&$pageCount>=1&&$pageCount<=100000)$pages[$pageCount]=true;
        $safe=['additional_price_amount'=>$amount,'additional_price_currency_alias'=>$currencyAlias,'additional_price_currency_name'=>$currencyName,'pages_count'=>is_int($pageCount)?$pageCount:null];
        foreach(['programId'=>'program_id','programName'=>'program_name','packetDateBeg'=>'packet_date_beg','packetDateEnd'=>'packet_date_end','tourNights'=>'tour_nights','hotelId'=>'hotel_id','hotelName'=>'hotel_name'] as $source=>$target){
            $value=$row[$source]??null;if(is_int($value)&&$value>=0)$safe[$target]=(string)$value;
            elseif(is_string($value)&&strlen($value)<=160&&!preg_match('/[\x00-\x1f\x7f<>]/',$value))$safe[$target]=$value;else $safe[$target]=null;
        }
        $safe['partner_price_amount']=anytour_anex_additional_decimal($row['partnerPriceAmount']??null);
        $safe['partner_price_currency']=anytour_anex_additional_label($row['partnerPriceCurrency']??null,24);
        $rows[]=$safe;
    }
    return ['entry_count'=>count($payload),'retained_entry_count'=>count($rows),'rows'=>$rows,'reported_additional_price_currencies'=>array_values(array_keys($currencies)),'reported_pages_counts'=>array_map('intval',array_values(array_keys($pages))),'truncated'=>count($payload)>100];
}
function anytour_anex_additional_specimen_run(array $input): array
{
    if(PHP_SAPI!=='cli'||array_keys($input)!==['operation_id','source_sha']||$input['operation_id']!==ANEX_ADDITIONAL_GREEN_GOLD_OPERATION||!is_string($input['source_sha'])||!preg_match('/\A[a-f0-9]{40}\z/D',$input['source_sha']))throw new RuntimeException('ANEX_ADDITIONAL_INPUT');
    $home=(string)getenv('HOME');$root=realpath($home.'/www/anytoour.ru');$private=realpath($home.'/.anytoour-anex');
    if(!$root||!$private||$private!==$home.'/.anytoour-anex')throw new RuntimeException('ANEX_ADDITIONAL_RUNTIME');
    foreach([$root.'/config.php',$private.'/search3-preview.php'] as $config){if(!is_file($config)||is_link($config))throw new RuntimeException('ANEX_ADDITIONAL_CONFIG');require_once $config;}
    if(!defined('ANEX_B2B_TOKEN')||!is_string(ANEX_B2B_TOKEN)||trim(ANEX_B2B_TOKEN)==='')throw new RuntimeException('ANEX_ADDITIONAL_B2B_TOKEN');
    if(stripos(ANEX_B2B_TOKEN,'Bearer ')===0||preg_match('/[\x00-\x20\x7f]/',ANEX_B2B_TOKEN))throw new RuntimeException('ANEX_ADDITIONAL_B2B_TOKEN_FORMAT');
    if(defined('ANEX_B2B_USER_AGENT')&&ANEX_B2B_USER_AGENT!=='TourismPlus')throw new RuntimeException('ANEX_ADDITIONAL_USER_AGENT');
    umask(0077);$directory=$private.'/'.$input['operation_id'];if(!@mkdir($directory,0700))throw new RuntimeException('ANEX_ADDITIONAL_NO_REPLAY');
    $reservation=json_encode($input+['state'=>'unknown_reserved','replay_allowed'=>false,'tour'=>2637,'dateBeg'=>'2026-10-05','nights'=>7,'currency'=>3],JSON_THROW_ON_ERROR)."\n";
    if(file_put_contents($directory.'/reservation.json',$reservation,LOCK_EX)!==strlen($reservation))throw new RuntimeException('ANEX_ADDITIONAL_RESERVATION');
    $criteria=['tour'=>2637,'dateBeg'=>'2026-10-05','nights'=>7,'currency'=>3,'page'=>1,'pageSize'=>10];
    $result=['schema_version'=>1]+$input+['observed_at'=>gmdate('c'),'supplier_replay_allowed'=>false,'criteria'=>$criteria,'additional_prices_requests'=>0,'booking_calls'=>0,'mapping_writes'=>0,
        'search_price_current'=>['amount'=>'119448','currency'=>'RUB','source'=>'direct_anex_v10'],'historical_tourvisor_context'=>['display_price'=>'149548','fuel_charge'=>'29596','currency'=>'RUB','stale_for_arithmetic'=>true]];
    try{
        $http=anytour_anex_additional_http_get(ANEX_B2B_TOKEN,$criteria);$result['additional_prices_requests']=1;
        $safe=anytour_anex_additional_sanitize_payload($http['body']);
        $result += ['status'=>'completed','additional_prices'=>$safe,'request_diagnostics'=>$http['diagnostics'],'money_semantics'=>[
            'request_currency_key'=>3,'request_currency_label'=>null,'additional_currency_namespace_verified'=>false,'per_person_or_package'=>'unknown','direction_specific'=>false,
            'selected_flight_specific'=>false,'fuel_only'=>false,'included_in_search_price'=>'unknown','package_identity_verified'=>false,'fuel_equivalence_verified'=>false,'final_price_verified'=>false,'arithmetic_applied'=>false]];
    }catch(Throwable $e){$result += ['status'=>'unknown','error'=>preg_match('/\AANEX_[A-Z0-9_]+\z/D',$e->getMessage())?$e->getMessage():'ANEX_ADDITIONAL_FAILED','automatic_retry'=>false];}
    $encoded=json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
    if(strpos($encoded,ANEX_B2B_TOKEN)!==false||stripos($encoded,'Bearer ')!==false)throw new RuntimeException('ANEX_ADDITIONAL_SECRET_OUTPUT');
    if(file_put_contents($directory.'/result.json',$encoded,LOCK_EX)!==strlen($encoded))throw new RuntimeException('ANEX_ADDITIONAL_RECEIPT');return $result;
}
if(!defined('ANYTOUR_ANEX_ADDITIONAL_SPECIMEN_LIBRARY_ONLY')){
    try{$raw=file_get_contents('php://stdin',false,null,0,4097);if(!is_string($raw)||strlen($raw)>4096)throw new RuntimeException('ANEX_ADDITIONAL_INPUT');$input=json_decode($raw,true,8,JSON_THROW_ON_ERROR);if(!is_array($input))throw new RuntimeException('ANEX_ADDITIONAL_INPUT');$result=anytour_anex_additional_specimen_run($input);fwrite(STDOUT,json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n");exit(($result['status']??null)==='completed'?0:2);}
    catch(Throwable $e){fwrite(STDOUT,json_encode(['schema_version'=>1,'operation_id'=>ANEX_ADDITIONAL_GREEN_GOLD_OPERATION,'status'=>'unknown','error'=>preg_match('/\AANEX_[A-Z0-9_]+\z/D',$e->getMessage())?$e->getMessage():'ANEX_ADDITIONAL_FAILED','automatic_retry'=>false,'supplier_replay_allowed'=>false],JSON_THROW_ON_ERROR)."\n");exit(1);}
}
