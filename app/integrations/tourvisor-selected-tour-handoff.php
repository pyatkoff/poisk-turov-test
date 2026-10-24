<?php
declare(strict_types=1);
require_once __DIR__ . '/anex-package-quote-handoff.php';

/**
 * Sole TV record owner in the existing locked PHP session.
 * capture() is INTERNAL: caller must supply current server-returned terms and
 * original timestamps under its viewer/search lock, never an HTTP/browser DTO.
 * No HTTP capture action is exposed by this source component.
 */
final class AnyTourTourvisorSelectedTourHandoff
{
    private const MAX_RECORDS = 8;

    private static function need(bool $ok, string $field): void
    { if (!$ok) throw new InvalidArgumentException($field); }

    private static function exact(array $value, array $keys, string $field): void
    {
        self::need(!array_is_list($value) && count($value) === count($keys)
            && !array_diff($keys, array_keys($value)) && !array_diff(array_keys($value), $keys), $field);
    }

    private static function pins(array $value): array
    {
        $keys = ['generation','search_ref','offer_ref','local_hotel_id','choice_ref','provider'];
        self::exact($value, $keys, 'selection.pins');
        self::need($value['provider'] === 'tourvisor' && is_int($value['generation'])
            && $value['generation'] > 0 && $value['generation'] <= 2147483647
            && is_string($value['search_ref']) && preg_match('/\A[a-f0-9]{32}\z/D', $value['search_ref']) === 1
            && is_string($value['offer_ref']) && preg_match('/\Atourvisor:[a-f0-9]{64}\z/D', $value['offer_ref']) === 1
            && is_int($value['local_hotel_id']) && $value['local_hotel_id'] > 0 && $value['local_hotel_id'] <= 2147483647
            && is_string($value['choice_ref']) && ($value['choice_ref'] === 'manager-deferred'
                || preg_match('/\Atourvisor_pair:[a-f0-9]{64}\z/D', $value['choice_ref']) === 1), 'selection.pins');
        $out = [];
        foreach ($keys as $key) $out[$key] = $value[$key];
        return $out;
    }

    private static function terms(array $value, array $pins, int $deadline): array
    {
        self::exact($value, ['source','hotel','trip','stay','flights','price'], 'record.server_terms');
        self::need($value['source'] === 'tourvisor', 'record.source');
        foreach (['hotel','trip','stay','flights','price'] as $key) self::need(is_array($value[$key]), 'record.server_terms');
        $hotel = $value['hotel']; $trip = $value['trip']; $stay = $value['stay']; $flights = $value['flights']; $price = $value['price'];
        self::exact($hotel, ['uiId','namespace','name','resort'], 'hotel.catalog_labels');
        self::need($hotel['uiId'] === $pins['local_hotel_id'] && $hotel['namespace'] === 'search3-ui'
            && anytour_anex_handoff_text($hotel['name'],191,true)
            && anytour_anex_handoff_text($hotel['resort'],300), 'hotel.catalog_labels');
        self::exact($trip, ['origin','countryUiId','departure','nights','stayEnd','stayEndBasis','adults','childAges'], 'trip.retained');
        $day = static fn($v): bool => is_string($v) && preg_match('/\A(\d{4})-(\d{2})-(\d{2})\z/D',$v,$m) === 1
            && checkdate((int)$m[2],(int)$m[3],(int)$m[1]);
        self::need(anytour_anex_handoff_text($trip['origin'],180,true)
            && is_string($trip['countryUiId']) && preg_match('/\A[1-9][0-9]{0,8}\z/D',$trip['countryUiId']) === 1
            && $day($trip['departure']) && is_int($trip['nights']) && $trip['nights'] >= 1 && $trip['nights'] <= 365
            && is_int($trip['adults']) && $trip['adults'] >= 1 && $trip['adults'] <= 6
            && is_array($trip['childAges']) && array_is_list($trip['childAges']) && count($trip['childAges']) <= 3
            && (($trip['stayEnd'] === null && $trip['stayEndBasis'] === 'unknown')
                || ($day($trip['stayEnd']) && $trip['stayEnd'] > $trip['departure'] && $trip['stayEndBasis'] === 'shown-stay-end')), 'trip.retained');
        foreach ($trip['childAges'] as $age) self::need(is_int($age) && $age >= 0 && $age <= 17, 'trip.childAges');
        self::exact($stay, ['room','meal','placement','operator'], 'stay.retained');
        foreach (['room'=>500,'meal'=>300,'placement'=>500,'operator'=>300] as $key=>$max)
            self::need(anytour_anex_handoff_text($stay[$key],$max,in_array($key,['room','meal','operator'],true)), 'stay.'.$key);
        self::exact($flights, ['state','outward','inward'], 'flights.retained_pair');
        $selected = $pins['choice_ref'] !== 'manager-deferred';
        self::need($flights['state'] === ($selected ? 'selected' : 'manager-deferred')
            && is_array($flights['outward']) && array_is_list($flights['outward'])
            && is_array($flights['inward']) && array_is_list($flights['inward']), 'flights.retained_pair');
        foreach (['outward','inward'] as $key) {
            self::need($selected ? count($flights[$key]) >= 1 && count($flights[$key]) <= 4 : $flights[$key] === [], 'flights.retained_pair');
            foreach ($flights[$key] as $leg) {
                self::need(is_array($leg), 'flights.retained_pair');
                self::exact($leg, ['label'], 'flights.retained_pair');
                self::need(anytour_anex_handoff_text($leg['label'],1000,true), 'flights.retained_pair');
            }
        }
        self::exact($price, ['amount','currency','state','description','expiresAt','basis','finalPriceVerified','authority'], 'price.retained');
        self::need(is_string($price['amount']) && preg_match('/\A(?:0|[1-9][0-9]{0,8})(?:\.[0-9]{1,2})?\z/D',$price['amount']) === 1
            && preg_match('/[1-9]/',$price['amount']) === 1 && $price['currency'] === 'RUB' && $price['state'] === 'checked'
            && anytour_anex_handoff_text($price['description'],1000)
            && $price['expiresAt'] === $deadline && $price['basis'] === ($selected ? 'selected-pair' : 'shown-offer')
            && $price['finalPriceVerified'] === false && $price['authority'] === 'browser-observation-only', 'price.retained');
        // Canonical key order, never change a value, amount, age order or qualification.
        $out = ['source'=>'tourvisor'];
        foreach (['hotel'=>['uiId','namespace','name','resort'],
                  'trip'=>['origin','countryUiId','departure','nights','stayEnd','stayEndBasis','adults','childAges'],
                  'stay'=>['room','meal','placement','operator'], 'flights'=>['state','outward','inward'],
                  'price'=>['amount','currency','state','description','expiresAt','basis','finalPriceVerified','authority']] as $key=>$fields) {
            foreach ($fields as $field) $out[$key][$field] = $value[$key][$field];
        }
        return $out;
    }

    private static function key(array $pins): string
    { return hash('sha256', json_encode($pins, JSON_THROW_ON_ERROR)); }

    public static function slot(array $pins, array $session): ?array
    {
        $pins = self::pins($pins);
        $slot = $session['tourvisor_handoff_records'][self::key($pins)] ?? null;
        return is_array($slot) ? $slot : null;
    }

    private static function current(array $pins, array $session, int $observedAt, int $deadline, int $now): void
    {
        self::need(($session['tourvisor_context_generation'] ?? null) === $pins['generation'], 'search.current_context');
        self::need($observedAt > 0 && $observedAt <= 8640000000000 && $now <= 8640000000000
            && $deadline <= 8640000000000 && $now >= $observedAt && $deadline > $now
            && $deadline > $observedAt && $deadline - $observedAt <= 900, 'record.observed_deadline');
    }

    private static function ready(array $pins, array $slot, array $session, int $now): array
    {
        self::exact($slot, ['selector','observed_at','expires_at','json','json_sha256'], 'record.integrity');
        self::need($slot['selector'] === $pins && is_int($slot['observed_at']) && is_int($slot['expires_at'])
            && is_string($slot['json']) && strlen($slot['json']) <= 24576
            && is_string($slot['json_sha256']) && hash('sha256',$slot['json']) === $slot['json_sha256'], 'record.integrity');
        self::current($pins,$session,$slot['observed_at'],$slot['expires_at'],$now);
        $record = json_decode($slot['json'],true,32,JSON_THROW_ON_ERROR);
        self::need(is_array($record), 'record.integrity');
        self::exact($record, ['binding','observation'], 'record.integrity');
        self::need(is_array($record['binding']) && is_array($record['observation']), 'record.integrity');
        self::exact($record['binding'], ['record_id','revision'], 'record.integrity');
        self::need(is_string($record['binding']['record_id'])
            && preg_match('/\Atourvisor:[a-f0-9]{32}\z/D',$record['binding']['record_id']) === 1
            && $record['binding']['revision'] === 1, 'record.integrity');
        $o = $record['observation'];
        self::exact($o,['schema','kind','capturedAt','source','hotel','trip','stay','flights','price'],'record.integrity');
        self::need($o['schema'] === 1 && $o['kind'] === 'search3_browser_observation'
            && $o['capturedAt'] === $slot['observed_at'] * 1000, 'record.integrity');
        $terms = self::terms(array_diff_key($o,array_flip(['schema','kind','capturedAt'])),$pins,$slot['expires_at']);
        self::need($record === ['binding'=>$record['binding'], 'observation'=>['schema'=>1,'kind'=>'search3_browser_observation',
            'capturedAt'=>$slot['observed_at']*1000]+$terms], 'record.integrity');
        self::need(strlen(json_encode($record,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)) <= 24576, 'record.receiver_size');
        return ['status'=>'ready','record'=>$record,'record_sha256'=>$slot['json_sha256']];
    }

    /** Internal capture only; no browser fields, HTTP, PDO, signer, new file or deadline renewal. */
    public static function capture(array $pins, array $serverTerms, array &$session, int $observedAt, int $deadline,
        int $now, callable $checkpoint, ?callable $clock = null): array
    {
        try {
            $pins = self::pins($pins);
            $clock ??= static fn(): int => time();
            $at = $clock();
            self::need(is_int($at) && $at >= $now, 'record.clock');
            $now = $at;
            self::current($pins,$session,$observedAt,$deadline,$now);
            $terms = self::terms($serverTerms,$pins,$deadline);
            $key = self::key($pins);
            $records = $session['tourvisor_handoff_records'] ?? [];
            self::need(is_array($records), 'record.integrity');
            $old = self::slot($pins,$session);
            self::need(!array_key_exists($key,$records) || $old !== null, 'record.integrity');
            if ($old !== null) {
                $ready = self::ready($pins,$old,$session,$now);
                self::need($old['observed_at'] === $observedAt && $old['expires_at'] === $deadline
                    && $ready['record']['observation'] === ['schema'=>1,'kind'=>'search3_browser_observation',
                        'capturedAt'=>$observedAt*1000]+$terms, 'record.immutable_terms');
                return $ready;
            }
            self::need(is_array($records) && count($records) < self::MAX_RECORDS, 'record.limit');
            $record = ['binding'=>['record_id'=>'tourvisor:'.bin2hex(random_bytes(16)),'revision'=>1],
                'observation'=>['schema'=>1,'kind'=>'search3_browser_observation','capturedAt'=>$observedAt*1000]+$terms];
            self::need(strlen(json_encode($record,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)) <= 24576, 'record.receiver_size');
            $json = json_encode($record,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            self::need(strlen($json) <= 24576, 'record.receiver_size');
            $session['tourvisor_handoff_records'][$key] = ['selector'=>$pins,'observed_at'=>$observedAt,'expires_at'=>$deadline,
                'json'=>$json,'json_sha256'=>hash('sha256',$json)];
            $expected = $session;
            try {
                $checkpoint($session);
                self::need($session === $expected, 'record.checkpoint_changed');
            } catch (Throwable $ignored) {
                if ($session === $expected) $session['tourvisor_handoff_records'][$key] = ['status'=>'unknown'];
                return anytour_anex_handoff_held('record.checkpoint_unknown');
            }
            $after = $clock();
            self::need(is_int($after) && $after >= $now, 'record.clock');
            return self::ready($pins,$session['tourvisor_handoff_records'][$key] ?? [],$session,$after);
        } catch (InvalidArgumentException $e) { return anytour_anex_handoff_held($e->getMessage()); }
        catch (Throwable $ignored) { return anytour_anex_handoff_held('record.invalid_retained'); }
    }

    /** Pure read of the existing canonical TV slot; freeze cannot capture/remint one. */
    public static function freeze(array $pins, array $session, int $now, callable $clock): array
    {
        try {
            $pins = self::pins($pins);
            $at = $clock();
            self::need(is_int($at) && $at >= $now, 'record.clock');
            $slot = self::slot($pins,$session);
            self::need($slot !== null, 'record.current_retained');
            return self::ready($pins,$slot,$session,$at);
        } catch (InvalidArgumentException $e) { return anytour_anex_handoff_held($e->getMessage()); }
        catch (Throwable $ignored) { return anytour_anex_handoff_held('record.invalid_retained'); }
    }
}
