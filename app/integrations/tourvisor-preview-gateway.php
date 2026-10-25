<?php
declare(strict_types=1);
require_once __DIR__ . '/tourvisor-interactive-client.php';
require_once __DIR__ . '/tourvisor-selected-tour-handoff.php';

/** Private-session TV owner. No browser monetary projection and no new transport/store. */
final class AnyTourTourvisorPreviewGateway
{
    private static function need(bool $ok, string $code): void
    { if (!$ok) throw new InvalidArgumentException($code); }
    private static function exact(array $v, array $keys): void
    { self::need(count($v) === count($keys) && !array_diff(array_keys($v),$keys), 'tv_invalid_request'); }
    private static function id($v): string
    {
        self::need((is_string($v) || is_int($v)) && preg_match('/\A[A-Za-z0-9._:-]{1,220}\z/D',(string)$v) === 1,'tv_invalid_identity');
        return (string)$v;
    }
    private static function labels($v): array
    {
        if (is_string($v)) return trim($v) === '' ? [] : [trim($v)];
        if (!is_array($v)) return [];
        $out = [];
        foreach (['russianName','name','fullName'] as $k) if (is_string($v[$k] ?? null) && trim($v[$k]) !== '') $out[] = trim($v[$k]);
        return array_values(array_unique($out));
    }
    private static function operator(array $v): array
    { return array_values(array_unique(array_merge(self::labels($v['operator'] ?? null),self::labels($v['operatorName'] ?? null)))); }
    private static function operatorKey(string $v): string
    { return (string)preg_replace('/[^\pL\pN]+/u','',mb_strtolower($v,'UTF-8')); }
    private static function operatorAllowed(array $names): void
    {
        self::need($names !== [],'tv_operator_unverified');
        foreach ($names as $name) self::need(preg_match('/(?:^|[^\pL\pN])(?:coral[\s-]*travel|coral|корал[\s-]*тревел|корал|sunmar|санмар)(?:$|[^\pL\pN])/iu',$name) !== 1,
            'tv_operator_listing_only');
    }
    private static function amount($value): string
    {
        if (is_array($value)) {
            self::need(($value['currency'] ?? 'RUB') === 'RUB','tv_price_currency');
            $value = $value['value'] ?? null;
        }
        if (is_int($value) || is_float($value)) $value = json_encode($value,JSON_THROW_ON_ERROR);
        self::need(is_string($value) && preg_match('/\A(?:0|[1-9][0-9]{0,8})(?:\.[0-9]{1,2})?\z/D',$value) === 1
            && preg_match('/[1-9]/',$value) === 1,'tv_price_missing');
        return $value;
    }
    private static function text($v, int $max, bool $required = true): string
    {
        $names = self::labels($v); $s = $names[0] ?? '';
        self::need(anytour_anex_handoff_text($s,$max,$required),'tv_label_missing');
        return $s;
    }
    private static function context(array $r, array $s, int $now): array
    {
        $c = $s['tourvisor_context'] ?? null;
        self::need(is_array($c) && ($c['generation'] ?? null) === ($s['tourvisor_context_generation'] ?? null)
            && ($r['generation'] ?? null) === $c['generation'] && ($r['search_ref'] ?? null) === $c['search_ref']
            && $c['state'] === 'ready' && $now >= $c['created_at'] && $now < $c['expires_at'],'tv_stale_context');
        return $c;
    }
    private static function current(array $c, array $s): void
    {
        self::need(($s['tourvisor_context_generation'] ?? null) === $c['generation']
            && ($s['tourvisor_context']['search_ref'] ?? null) === $c['search_ref'],'tv_context_changed');
    }
    /** Supplier calendar syntax only; internal records keep their strict ISO day. */
    private static function supplierDay(mixed $value): ?string
    {
        if (!is_string($value)) return null;
        if (preg_match('/\A([0-9]{4})-([0-9]{2})-([0-9]{2})\z/D',$value,$m) === 1)
            [$year,$month,$day] = [(int)$m[1],(int)$m[2],(int)$m[3]];
        elseif (preg_match('/\A([0-9]{2})\.([0-9]{2})\.([0-9]{4})\z/D',$value,$m) === 1)
            [$year,$month,$day] = [(int)$m[3],(int)$m[2],(int)$m[1]];
        else return null;
        return checkdate($month,$day,$year) ? sprintf('%04d-%02d-%02d',$year,$month,$day) : null;
    }
    /** Strict identities/types; supplier dates must denote the same valid day. */
    private static function tourMatch(array $values, array $expected): void
    {
        $fields = []; $types = [];
        $kind = static fn($v): string => is_null($v) ? 'null' : (is_int($v) ? 'integer' : (is_string($v) ? 'string'
            : (is_float($v) ? 'number' : (is_bool($v) ? 'boolean' : (is_array($v) ? 'array' : 'other')))));
        foreach ($values as $field=>$value) if ($field === 'date'
            ? self::supplierDay($value) === null || self::supplierDay($value) !== self::supplierDay($expected[$field])
            : $value !== $expected[$field]) {
            $fields[] = $field;
            $types[$field] = ['supplier'=>$kind($value),'context'=>$kind($expected[$field])];
        }
        if ($fields === []) return;
        $diagnostics = ['provider'=>'tourvisor','stage'=>'tour','category'=>'context',
            'mismatchFields'=>$fields,'mismatchTypes'=>$types];
        if (in_array('date',$fields,true)) {
            $shape = static fn($v): string => !is_string($v) ? 'non-string'
                : (preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/D',$v) === 1 ? 'iso'
                : (preg_match('/\A[0-9]{2}\.[0-9]{2}\.[0-9]{4}\z/D',$v) === 1 ? 'dmy' : 'other'));
            $diagnostics['dateFormats'] = ['supplier'=>$shape($values['date']),'context'=>$shape($expected['date'])];
        }
        throw new \AnyTour\TourvisorInteractiveFailure(['ok'=>false,'error'=>'tv_tour_mismatch','diagnostics'=>$diagnostics],409);
    }
    private static function bounded(array $context, array $requests): void
    { self::need(strlen(json_encode([$context,$requests],JSON_THROW_ON_ERROR)) <= 4194304,'tv_context_limit'); }
    private static function deadline(array $offer, string $choice): int
    {
        $end = $offer['checked']['expires_at'];
        return $choice === 'manager-deferred' ? $end : min($end,$offer['pairs'][$choice]['expires_at']);
    }
    /** Check the complete reply cache candidate BEFORE capture can checkpoint raw context. */
    private static function captureBudget(array $c, string $ref, string $choice, mixed $data, array $s,
        string $rid, string $fingerprint): void
    {
        $extra = ['selector'=>self::pins($c,$ref,$c['offers'][$ref],$choice),'record_sha256'=>str_repeat('0',64)];
        $reply = ['provider'=>'tourvisor','generation'=>$c['generation'],'search_ref'=>$c['search_ref'],
            'data'=>$data]+$extra;
        self::need(strlen(json_encode($reply,JSON_THROW_ON_ERROR)) <= 1048576,'tv_response_limit');
        $requests = $s['tourvisor_requests'];
        $requests[$rid] = ['fingerprint'=>$fingerprint,'generation'=>$c['generation'],'state'=>'ready',
            'expires_at'=>self::deadline($c['offers'][$ref],$choice),'reply'=>$reply];
        self::bounded($c,$requests);
    }
    private static function invalidate(string $ref, array &$s): void
    {
        foreach ($s['tourvisor_handoff_records'] ?? [] as $key=>$slot) {
            if (($slot['selector']['offer_ref'] ?? null) === $ref) $s['tourvisor_handoff_records'][$key] = ['status'=>'expired'];
        }
        if (isset($s['tourvisor_context']['offers'][$ref])) $s['tourvisor_context']['offers'][$ref]['expired'] = true;
    }
    private static function pins(array $c, string $ref, array $offer, string $choice): array
    {
        return ['generation'=>$c['generation'],'search_ref'=>$c['search_ref'],'offer_ref'=>$ref,
            'local_hotel_id'=>$offer['hotel']['uiId'],'choice_ref'=>$choice,'provider'=>'tourvisor'];
    }
    private static function legs(array $pair, string $key): array
    {
        $legs = $pair[$key] ?? null;
        self::need(is_array($legs) && array_is_list($legs) && count($legs) >= 1 && count($legs) <= 4,'tv_pair_incomplete');
        $out = [];
        foreach ($legs as $leg) {
            self::need(is_array($leg),'tv_pair_incomplete');
            $number = self::text($leg['number'] ?? null,80);
            $from = $leg['departure']['port']['shortName'] ?? $leg['departure']['port']['id'] ?? null;
            $to = $leg['arrival']['port']['shortName'] ?? $leg['arrival']['port']['id'] ?? null;
            $date = self::text($leg['departure']['date'] ?? null,80);
            $out[] = ['label'=>$number . ' · ' . self::text($from,80) . ' → ' . self::text($to,80) . ' · ' . $date];
        }
        return $out;
    }
    private static function capture(array $c, string $ref, array &$s, string $choice, int $now, callable $checkpoint, callable $clock): array
    {
        $offer = $c['offers'][$ref]; $checked = $offer['checked'] ?? null;
        self::need(is_array($checked) && empty($offer['expired']) && $now >= $checked['observed_at']
            && $now < $checked['expires_at'],'tv_checked_tour_missing');
        $tour = $checked['tour']; $pair = null; $observedAt = $checked['observed_at'];
        if ($choice !== 'manager-deferred') {
            $fact = $offer['pairs'][$choice] ?? null;
            self::need(is_array($fact),'tv_pair_missing');
            self::need($now >= $fact['observed_at'] && $now < $fact['expires_at'],'tv_pair_expired');
            $pair = $fact['pair']; $observedAt = $fact['observed_at'];
        }
        $raw = $pair ?? $tour;
        self::need(($raw['currency'] ?? 'RUB') === 'RUB','tv_price_currency');
        $amount = self::amount($raw['price'] ?? null);
        $deadline = self::deadline($offer,$choice);
        $terms = ['source'=>'tourvisor','hotel'=>$offer['hotel'],
            'trip'=>['origin'=>self::text($tour['departure'] ?? null,180),
                'countryUiId'=>(string)$c['params']['countryId'],'departure'=>self::supplierDay($tour['date']),'nights'=>$tour['nights'],
                'stayEnd'=>null,'stayEndBasis'=>'unknown','adults'=>$c['params']['adults'],'childAges'=>$c['params']['childs']],
            'stay'=>['room'=>self::text($tour['roomType'] ?? null,500),'meal'=>self::text($tour['meal'] ?? null,300),
                'placement'=>self::text($tour['placement'] ?? '',500,false),'operator'=>self::operator($tour)[0]],
            'flights'=>['state'=>$pair === null ? 'manager-deferred' : 'selected',
                'outward'=>$pair === null ? [] : self::legs($pair,'forward'),'inward'=>$pair === null ? [] : self::legs($pair,'backward')],
            'price'=>['amount'=>$amount,'currency'=>'RUB','state'=>'checked',
                'description'=>'Цена Tourvisor за всю группу; окончательный расчёт не подтверждён.',
                'expiresAt'=>$deadline,'basis'=>$pair === null ? 'shown-offer' : 'selected-pair',
                'finalPriceVerified'=>false,'authority'=>'browser-observation-only']];
        $pins = self::pins($c,$ref,$offer,$choice);
        $result = AnyTourTourvisorSelectedTourHandoff::capture($pins,$terms,$s,$observedAt,$deadline,$now,$checkpoint,$clock);
        self::need(($result['status'] ?? null) === 'ready','tv_capture_held');
        return ['selector'=>$pins,'record_sha256'=>$result['record_sha256']];
    }

    /**
     * transport(path,params,&state) performs exactly one existing interactive call,
     * releasing/reacquiring the PHP lock in the HTTP owner. checkpoint is durable.
     */
    public static function handle(array $r, array &$s, int $now, callable $transport,
        callable $catalog, callable $checkpoint, ?callable $clock = null): array
    {
        $clock ??= static fn(): int => time();
        $action = $r['action'] ?? ''; $rid = $r['request_id'] ?? null;
        self::need(($r['provider'] ?? null) === 'tourvisor' && is_string($rid)
            && preg_match('/\A[a-f0-9]{32}\z/D',$rid) === 1,'tv_invalid_request');
        $keys = ['action','provider','request_id','csrf'];
        $search = $action === 'tv_search';
        if ($search) $keys[] = 'params';
        else {
            $keys = array_merge($keys,['generation','search_ref']);
            if (in_array($action,['tv_tour','tv_flights','tv_select'],true)) $keys[] = 'offer_ref';
            if ($action === 'tv_select') $keys[] = 'choice_ref';
        }
        self::exact($r,$keys);
        self::need(in_array($action,['tv_search','tv_continue','tv_status','tv_results','tv_tour','tv_flights','tv_select'],true),'tv_invalid_request');
        $fingerprint = hash('sha256',json_encode(array_diff_key($r,['csrf'=>true]),JSON_THROW_ON_ERROR));
        $requests = $s['tourvisor_requests'] ?? [];
        self::need(is_array($requests),'tv_request_integrity');
        if (array_key_exists($rid,$requests)) {
            $old = $requests[$rid];
            self::need(is_array($old) && ($old['fingerprint'] ?? null) === $fingerprint,'tv_request_changed');
            self::need(($old['generation'] ?? null) === ($s['tourvisor_context_generation'] ?? null),'tv_stale_context');
            self::need(($old['state'] ?? null) === 'ready','tv_operation_unknown');
            self::need($now < ($old['expires_at'] ?? 0),'tv_stale_context');
            if (in_array($action,['tv_tour','tv_flights','tv_select'],true)) {
                $current = self::context($r,$s,$now); $ref = $r['offer_ref'];
                self::need(is_string($ref) && isset($current['offers'][$ref])
                    && empty($current['offers'][$ref]['expired']),'tv_offer_not_owned');
            }
            return $old['reply'];
        }
        self::need(count($requests) < 64 && strlen(json_encode([$s['tourvisor_context'] ?? [],$requests],JSON_THROW_ON_ERROR)) < 4194304,'tv_context_limit');
        if ($search) {
            self::need(is_array($r['params']),'tv_invalid_request');
            $allowed = ['departureId','countryId','dateFrom','dateTo','nightsFrom','nightsTo','adults','childs','meal',
                'hotelCategory','hotelRating','hotelTypes','hotelIds','hotelServices','arrivalId','regionIds','subregionIds',
                'operatorIds','priceFrom','priceTo','currency','onlyCharter','onlyDirect'];
            self::need(!array_diff(array_keys($r['params']),$allowed),'tv_invalid_request');
            $params = \AnyTour\TourvisorInteractiveClient::searchParams($r['params']);
            self::need($params['currency'] === 'RUB','tv_price_currency');
            $generation = ($s['tourvisor_context_generation'] ?? 0) + 1;
            self::need(is_int($generation) && $generation > 0 && $generation <= 2147483647,'tv_context_limit');
            $c = ['generation'=>$generation,'search_ref'=>bin2hex(random_bytes(16)),'state'=>'starting',
                'search_id'=>null,'created_at'=>$now,'expires_at'=>$now+1800,'params'=>$params,'offers'=>[]];
            $path = '/tours/search'; $query = $params;
        } else {
            $c = self::context($r,$s,$now); $generation = $c['generation']; $params = $c['params'];
            $path = '/tours/search/'.$c['search_id']; $query = [];
            if ($action === 'tv_continue') $path .= '/continue';
            if ($action === 'tv_status') { $path .= '/status'; $query = ['operatorStatus'=>false]; }
            if ($action === 'tv_results') $query = ['limit'=>1000];
            if (in_array($action,['tv_tour','tv_flights','tv_select'],true)) {
                $ref = $r['offer_ref'];
                self::need(is_string($ref) && isset($c['offers'][$ref]) && empty($c['offers'][$ref]['expired']),'tv_offer_not_owned');
                $offer = $c['offers'][$ref]; self::operatorAllowed($offer['operators']);
                $path = '/tours/'.rawurlencode($offer['id']); $query = ['currency'=>'RUB'];
                if ($action === 'tv_flights') {
                    self::need(isset($offer['checked']) && $now < $offer['checked']['expires_at'],'tv_checked_tour_missing');
                    $path .= '/flights';
                }
                if ($action === 'tv_select') self::need(is_string($r['choice_ref']),'tv_invalid_request');
                if ($action !== 'tv_select') {
                    self::need(!isset($offer['operations'][$action]),'tv_operation_already_attempted');
                    $c['offers'][$ref]['operations'][$action] = $rid;
                }
            }
        }
        $requests[$rid] = ['fingerprint'=>$fingerprint,'generation'=>$generation,'state'=>'unknown'];
        self::bounded($c,$requests);
        $s['tourvisor_context_generation'] = $generation;
        $s['tourvisor_context'] = $c; $s['tourvisor_requests'] = $requests;
        // This original marker remains UNKNOWN after every ambiguous attempt or checkpoint.
        $checkpoint($s); self::current($c,$s);
        try {
            $before = $clock();
            self::need(is_int($before) && $before >= $now && $before < $s['tourvisor_context']['expires_at'],'tv_stale_context');
            if (isset($ref) && isset($s['tourvisor_context']['offers'][$ref]['checked'])) {
                self::need($before >= $s['tourvisor_context']['offers'][$ref]['checked']['observed_at']
                    && $before < $s['tourvisor_context']['offers'][$ref]['checked']['expires_at'],'tv_checked_tour_missing');
            }
            if ($action === 'tv_select') $data = null;
            else $data = $transport($path,$query,$s);
            $at = $clock();
            self::need(is_int($at) && $at >= $before && $at < $c['expires_at'],'tv_stale_context');
            if ($action !== 'tv_select') self::need(is_array($data)
                && (!isset($data['error']) || $data['error'] === false || $data['error'] === ''),'tv_supplier_response_error');
            if ($action !== 'tv_select') self::need(strlen(json_encode($data,JSON_THROW_ON_ERROR)) <= 1048576,'tv_response_limit');
            self::current($c,$s);
            if (in_array($action,['tv_flights','tv_select'],true))
                self::need($at < $s['tourvisor_context']['offers'][$ref]['checked']['expires_at'],'tv_checked_tour_missing');
            self::need(($s['tourvisor_requests'][$rid] ?? null) === ['fingerprint'=>$fingerprint,'generation'=>$generation,'state'=>'unknown'],'tv_request_changed');
            // Re-read the live context: never overwrite a concurrent newer provider/session state.
            $c = $s['tourvisor_context']; $extra = [];
            if ($search) {
                self::need(is_array($data) && (is_int($data['searchId'] ?? null) || is_string($data['searchId'] ?? null))
                    && preg_match('/\A[1-9][0-9]{0,17}\z/D',(string)$data['searchId']) === 1,'tv_invalid_search_response');
                $c['search_id'] = (string)$data['searchId']; $c['state'] = 'ready';
            } elseif ($action === 'tv_results') {
                self::need(is_array($data) && array_is_list($data) && count($data) <= 1000,'tv_invalid_results');
                $selectors = [];
                foreach ($data as $hotel) {
                    self::need(is_array($hotel) && is_array($hotel['tours'] ?? null),'tv_invalid_results');
                    $hotelId = filter_var($hotel['id'] ?? null,FILTER_VALIDATE_INT);
                    self::need($hotelId !== false && $hotelId > 0,'tv_invalid_results');
                    $metadata = $catalog((int)$hotelId,$params['countryId']);
                    if (!is_array($metadata)) continue;
                    self::need(($metadata['uiId'] ?? null) === $hotelId && ($metadata['namespace'] ?? null) === 'search3-ui','tv_catalog_mismatch');
                    foreach ($hotel['tours'] as $tour) {
                        self::need(is_array($tour),'tv_invalid_results'); $id = self::id($tour['id'] ?? null);
                        $ref = 'tourvisor:'.hash('sha256','tourvisor:tour:'.$id);
                        $entry = ['id'=>$id,'hotel'=>$metadata,'operators'=>self::operator($tour),
                            'date'=>$tour['date'] ?? null,'nights'=>$tour['nights'] ?? null];
                        if (isset($c['offers'][$ref])) {
                            $old = $c['offers'][$ref];
                            self::need(array_intersect_key($old,$entry) === $entry,'tv_offer_identity_changed');
                        } else $c['offers'][$ref] = $entry;
                        self::need(count($c['offers']) <= 2000,'tv_context_limit');
                        $selectors[] = ['offer_ref'=>$ref,'local_hotel_id'=>$hotelId];
                    }
                }
                $extra['offers'] = $selectors;
            } elseif ($action === 'tv_tour') {
                self::tourMatch(['tour_id'=>self::id($data['id'] ?? null),'hotel_id'=>$data['hotel']['id'] ?? null,
                    'adults'=>$data['adults'] ?? null,'children'=>$data['childs'] ?? null,
                    'departure_id'=>$data['departure']['id'] ?? null,'date'=>$data['date'] ?? null,'nights'=>$data['nights'] ?? null],
                    ['tour_id'=>$offer['id'],'hotel_id'=>$offer['hotel']['uiId'],'adults'=>$params['adults'],
                    'children'=>count($params['childs']),'departure_id'=>$params['departureId'],
                    'date'=>$offer['date'],'nights'=>$offer['nights']]);
                $names = self::operator($data); self::operatorAllowed($names);
                self::need(array_intersect(array_map([self::class,'operatorKey'],$names),
                    array_map([self::class,'operatorKey'],$offer['operators'])) !== [],'tv_operator_changed');
                self::amount($data['price'] ?? null);
                self::need(!isset($offer['checked']),'tv_tour_already_checked');
                $deadline = min($c['expires_at'],$at+900);
                if (isset($data['expiresAt'])) {
                    self::need(is_int($data['expiresAt']) && $data['expiresAt'] > $at,'tv_tour_expired');
                    $deadline = min($deadline,$data['expiresAt']);
                }
                $c['offers'][$ref]['checked'] = ['tour'=>$data,'observed_at'=>$at,'expires_at'=>$deadline];
                self::captureBudget($c,$ref,'manager-deferred',$data,$s,$rid,$fingerprint);
                self::bounded($c,$s['tourvisor_requests']);
                $s['tourvisor_context'] = $c;
                $extra = self::capture($c,$ref,$s,'manager-deferred',$at,$checkpoint,$clock);
            } elseif ($action === 'tv_flights') {
                $pairs = is_array($data) && array_is_list($data) ? $data : ($data['flights'] ?? null);
                self::need(is_array($pairs) && array_is_list($pairs) && count($pairs) <= 100,'tv_invalid_flights');
                self::need(!isset($offer['pairs']),'tv_flights_already_loaded');
                $choices = []; $kept = [];
                foreach ($pairs as $index=>$pair) {
                    if (!is_array($pair)) continue;
                    try { self::amount($pair['price'] ?? null); self::legs($pair,'forward'); self::legs($pair,'backward'); }
                    catch (InvalidArgumentException $ignored) { continue; }
                    $forwardDay = self::supplierDay($pair['dateForward'] ?? null);
                    $backwardDay = self::supplierDay($pair['dateBackward'] ?? null);
                    $legDay = static fn($v): ?string => is_string($v) ? self::supplierDay(substr($v,0,10)) : null;
                    self::need($forwardDay !== null && $backwardDay !== null
                        && $forwardDay === self::supplierDay($offer['checked']['tour']['date']) && $backwardDay > $forwardDay
                        && $legDay($pair['forward'][0]['departure']['date']) === $forwardDay
                        && $legDay($pair['backward'][0]['departure']['date']) === $backwardDay,'tv_pair_mismatch');
                    $choice = 'tourvisor_pair:'.hash('sha256',json_encode($pair,JSON_THROW_ON_ERROR));
                    $deadline = min($offer['checked']['expires_at'],$at+900);
                    if (isset($pair['expiresAt'])) {
                        self::need(is_int($pair['expiresAt']) && $pair['expiresAt'] > $at,'tv_pair_expired');
                        $deadline = min($deadline,$pair['expiresAt']);
                    }
                    self::need(!isset($kept[$choice]),'tv_pair_duplicate');
                    $kept[$choice] = ['pair'=>$pair,'observed_at'=>$at,'expires_at'=>$deadline];
                    $choices[] = ['choice_ref'=>$choice,'index'=>$index];
                }
                $c['offers'][$ref]['pairs'] = $kept; $extra['choices'] = $choices;
            } elseif ($action === 'tv_select') {
                self::need($r['choice_ref'] === 'manager-deferred' || isset($c['offers'][$ref]['pairs'][$r['choice_ref']]),'tv_pair_missing');
                self::captureBudget($c,$ref,$r['choice_ref'],null,$s,$rid,$fingerprint);
                $extra = self::capture($c,$ref,$s,$r['choice_ref'],$at,$checkpoint,$clock);
            }
            $reply = ['provider'=>'tourvisor','generation'=>$generation,'search_ref'=>$c['search_ref'],'data'=>$data]+$extra;
            self::need(strlen(json_encode($reply,JSON_THROW_ON_ERROR)) <= 1048576,'tv_response_limit');
            $replyDeadline = in_array($action,['tv_tour','tv_flights','tv_select'],true)
                ? self::deadline($c['offers'][$ref],$action === 'tv_select' ? $r['choice_ref'] : 'manager-deferred')
                : min($c['expires_at'],$at+900);
            $readyRequests = $s['tourvisor_requests'];
            $readyRequests[$rid] = ['fingerprint'=>$fingerprint,'generation'=>$generation,
                'state'=>'ready','expires_at'=>$replyDeadline,'reply'=>$reply];
            self::bounded($c,$readyRequests);
            $s['tourvisor_context'] = $c; $s['tourvisor_requests'] = $readyRequests;
            $checkpoint($s); self::current($c,$s);
            return $reply;
        } catch (Throwable $error) {
            if (($s['tourvisor_requests'][$rid]['fingerprint'] ?? null) === $fingerprint)
                $s['tourvisor_requests'][$rid] = ['fingerprint'=>$fingerprint,'generation'=>$generation,'state'=>'unknown'];
            if ($error instanceof \AnyTour\TourvisorInteractiveFailure && $error->httpStatus === 410 && isset($ref)
                && ($s['tourvisor_context_generation'] ?? null) === $c['generation']
                && ($s['tourvisor_context']['search_ref'] ?? null) === $c['search_ref']) {
                self::invalidate($ref,$s);
                try { $checkpoint($s); } catch (Throwable $ignored) {}
            }
            throw $error;
        }
    }
}
