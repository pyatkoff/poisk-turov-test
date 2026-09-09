<?php
declare(strict_types=1);
/** Pure internal projection. No I/O, registry writes, booking or UI activation. */
final class AnyTourAndromedaNormalizer {
    private static function id($v): string {
        if ((!is_int($v) && !is_string($v)) || !preg_match('/^[A-Za-z0-9_-]{1,128}$/D',(string)$v)) throw new InvalidArgumentException('INVALID_ID');
        return (string)$v;
    }
    private static function text($v): string {
        if (!is_string($v) || strlen($v)>4096 || !preg_match('//u',$v) || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f]/',$v)) throw new InvalidArgumentException('INVALID_TEXT');
        return $v;
    }
    public static function page(array $payload, array $criteria, string $searchRef, int $generation): array {
        if (!preg_match('/^[A-Za-z0-9_-]{1,128}$/D',$searchRef) || $generation<1) throw new InvalidArgumentException('INVALID_SEARCH_CONTEXT');
        if (!isset($payload['PAGE'],$payload['PAGES_COUNT'],$payload['PRICES']) || !is_int($payload['PAGE']) || $payload['PAGE']<1
            || !is_int($payload['PAGES_COUNT']) || $payload['PAGES_COUNT']<0 || !is_array($payload['PRICES'])
            || array_values($payload['PRICES'])!==$payload['PRICES'] || count($payload['PRICES'])>2000
            || ($payload['PAGES_COUNT']===0 && count($payload['PRICES'])>0)
            || ($payload['PAGES_COUNT']>0 && $payload['PAGE']>$payload['PAGES_COUNT'])) throw new InvalidArgumentException('INVALID_PAGE');
        foreach (['TOWNFROMINC','STATEINC','CHECKIN_BEG','CHECKIN_END','ADULT','CHILD','NIGHTS_FROM','NIGHTS_TILL','CURRENCYINC'] as $k) {
            if (!array_key_exists($k,$criteria) || !is_scalar($criteria[$k])) throw new InvalidArgumentException('INVALID_CRITERIA');
        }
        $offers=[]; $rejected=[]; $seen=[];
        foreach ($payload['PRICES'] as $index=>$row) {
            try {
                if (!is_array($row)) throw new InvalidArgumentException('INVALID_ROW');
                $offer=self::offer($row,$criteria,$searchRef,$generation);
                $key=$offer['offer_ref'];
                if (isset($seen[$key])) throw new InvalidArgumentException('DUPLICATE_OFFER');
                $seen[$key]=true; $offers[]=$offer;
            } catch (InvalidArgumentException $e) { $rejected[]=['index'=>$index,'reason'=>$e->getMessage()]; }
        }
        return ['provider'=>'andromeda','search_ref'=>$searchRef,'generation'=>$generation,
            'page'=>$payload['PAGE'],'pages_count'=>$payload['PAGES_COUNT'],
            // A standalone page does not prove earlier pages were received.
            'status'=>($payload['PAGES_COUNT']<=1 && !$rejected)?'complete':'partial',
            'offers'=>$offers,'rejected'=>$rejected,'selection_enabled'=>false];
    }
    private static function offer(array $r,array $c,string $search,int $generation): array {
        foreach (['id','hotelKey','operatorKey','isOperatorHotelKey','price','currency','currencyKey','checkIn','nights','hotel','operator','meal','mealKey','room','htplace','adult','child'] as $k) {
            if (!array_key_exists($k,$r)) throw new InvalidArgumentException('MISSING_FIELD');
        }
        $raw=self::text($r['id']);
        if ($raw==='' || strlen($raw)>2048) throw new InvalidArgumentException('INVALID_OFFER_ID');
        $operator=self::id($r['operatorKey']); $hotel=self::id($r['hotelKey']);
        if (!in_array($r['isOperatorHotelKey'],[0,1,'0','1'],true)) throw new InvalidArgumentException('UNKNOWN_HOTEL_NAMESPACE');
        $namespace=(string)$r['isOperatorHotelKey']==='1'?'operator_'.$operator:'andromeda_catalog';
        $price=$r['price'];
        if ((!is_string($price) && !is_int($price)) || !preg_match('/^(?:0|[1-9][0-9]{0,14})(?:\.[0-9]{1,4})?$/D',(string)$price)
            || !preg_match('/[1-9]/',(string)$price)) throw new InvalidArgumentException('INVALID_PRICE');
        $currency=self::text($r['currency']);
        if (!preg_match('/^[A-Z]{3}$/D',$currency)) throw new InvalidArgumentException('INVALID_CURRENCY');
        $currencyId=self::id($r['currencyKey']);
        if ($currencyId!==(string)$c['CURRENCYINC']) throw new InvalidArgumentException('CURRENCY_MISMATCH');
        $date=DateTimeImmutable::createFromFormat('!d.m.Y',self::text($r['checkIn']),new DateTimeZone('UTC'));
        if (!$date || $date->format('d.m.Y')!==$r['checkIn'] || $date->format('Ymd')<(string)$c['CHECKIN_BEG'] || $date->format('Ymd')>(string)$c['CHECKIN_END']) throw new InvalidArgumentException('DATE_MISMATCH');
        $nights=self::id($r['nights']); $adult=self::id($r['adult']); $child=self::id($r['child']);
        if (!ctype_digit($nights) || (int)$nights<(int)$c['NIGHTS_FROM'] || (int)$nights>(int)$c['NIGHTS_TILL']
            || $adult!==(string)$c['ADULT'] || $child!==(string)$c['CHILD']) throw new InvalidArgumentException('PARTY_OR_NIGHTS_MISMATCH');
        $ref='offer_'.hash('sha256',json_encode([$search,$generation,$operator,$raw],JSON_THROW_ON_ERROR));
        return ['provider'=>'andromeda','supplier_namespace'=>$namespace,'search_ref'=>$search,'generation'=>$generation,
            'offer_ref'=>$ref,'external_hotel_id'=>$hotel,'local_hotel_id'=>null,'operator_ref'=>$operator,
            'hotel'=>self::text($r['hotel']),'operator'=>self::text($r['operator']),
            'check_in'=>$date->format('Y-m-d'),'nights'=>(int)$nights,'adults'=>(int)$adult,'children'=>(int)$child,
            'room'=>self::text($r['room']),'placement'=>self::text($r['htplace']),
            'meal'=>['label'=>self::text($r['meal']),'operator_id'=>self::id($r['mealKey']),
                'andromeda_id'=>isset($r['andrMealKey'])?self::id($r['andrMealKey']):null],
            'price'=>['amount'=>(string)$price,'currency'=>$currency,'currency_id'=>$currencyId,'kind'=>'offer','fees'=>'unknown','final'=>false],
            'selection_enabled'=>false];
    }
}
