<?php
declare(strict_types=1);

require_once __DIR__.'/three-provider-meal-family.php';
require_once __DIR__.'/three-provider-room-placement.php';

/** Pure internal projection. No I/O, registry writes, booking or UI activation. */
final class AnyTourAndromedaNormalizer {
    private const OWNERSHIP_EXCLUDED = 'excluded_direct_or_tv';
    private const OWNERSHIP_ANDROMEDA = 'andromeda_owned';
    private const OWNERSHIP_UNKNOWN = 'unknown';

    private static function id($v): string {
        if ((!is_int($v) && !is_string($v)) || !preg_match('/^[A-Za-z0-9_-]{1,128}$/D',(string)$v)) throw new InvalidArgumentException('INVALID_ID');
        return (string)$v;
    }
    private static function text($v): string {
        if (!is_string($v) || strlen($v)>4096 || !preg_match('//u',$v) || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f]/',$v)) throw new InvalidArgumentException('INVALID_TEXT');
        return $v;
    }
    private static function optionalText(array $row,string $key): ?string {
        if (!array_key_exists($key,$row) || $row[$key]===null || $row[$key]==='') return null;
        return self::text($row[$key]);
    }
    private static function optionalId(array $row,string $key): ?string {
        if (!array_key_exists($key,$row) || $row[$key]===null || $row[$key]==='') return null;
        return self::id($row[$key]);
    }
    private static function operatorOwnership(mixed $row): string {
        if (!is_array($row)) return self::OWNERSHIP_UNKNOWN;

        $operatorKey=$row['operatorKey']??null;
        if ((is_int($operatorKey) || is_string($operatorKey)) && preg_match('/^[A-Za-z0-9_-]{1,128}$/D',(string)$operatorKey)) {
            if (in_array((string)$operatorKey,['9','12','14','15'],true)) return self::OWNERSHIP_EXCLUDED;
        }

        $operator=$row['operator']??null;
        if (is_string($operator) && $operator!=='' && strlen($operator)<=4096 && preg_match('//u',$operator)) {
            $value=str_replace(['Ё','ё'],'е',trim($operator));
            $value=function_exists('mb_strtolower')?mb_strtolower($value,'UTF-8'):strtolower($value);
            $compact=preg_replace('/[^\p{L}\p{N}]+/u','',$value)??'';
            foreach (['anex','анекс','pegas','пегас','coral','корал','sunmar','санмар'] as $excluded) {
                if ($compact!=='' && str_contains($compact,$excluded)) return self::OWNERSHIP_EXCLUDED;
            }
            if ($compact!=='') return self::OWNERSHIP_ANDROMEDA;
        }

        if ((is_int($operatorKey) || is_string($operatorKey)) && preg_match('/^[A-Za-z0-9_-]{1,128}$/D',(string)$operatorKey)) {
            return self::OWNERSHIP_ANDROMEDA;
        }
        return self::OWNERSHIP_UNKNOWN;
    }
    private static function rejection(int $index,mixed $row,InvalidArgumentException $error): array {
        $message=$error->getMessage();
        $reason=$message;
        $missingField=null;
        if (preg_match('/^MISSING_FIELD:([A-Za-z0-9_]{1,64})$/D',$message,$match)) {
            $reason='MISSING_FIELD';
            $missingField=$match[1];
        }
        $out=['index'=>$index,'reason'=>$reason,'ownership'=>self::operatorOwnership($row)];
        if ($missingField!==null) $out['missing_field']=$missingField;
        return $out;
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
        $offers=[]; $rejected=[]; $excludedRejected=[]; $seen=[];
        foreach ($payload['PRICES'] as $index=>$row) {
            try {
                if (!is_array($row)) throw new InvalidArgumentException('INVALID_ROW');
                $offer=self::offer($row,$criteria,$searchRef,$generation);
                $key=$offer['offer_ref'];
                if (isset($seen[$key])) throw new InvalidArgumentException('DUPLICATE_OFFER');
                $seen[$key]=true; $offers[]=$offer;
            } catch (InvalidArgumentException $e) {
                $diagnostic=self::rejection($index,$row,$e);
                if ($diagnostic['ownership']===self::OWNERSHIP_EXCLUDED) $excludedRejected[]=$diagnostic;
                else $rejected[]=$diagnostic;
            }
        }
        return ['provider'=>'andromeda','search_ref'=>$searchRef,'generation'=>$generation,
            'page'=>$payload['PAGE'],'pages_count'=>$payload['PAGES_COUNT'],
            // A standalone page does not prove earlier pages were received. Rejections
            // owned by Andromeda/unknown remain blocking; direct/TV exclusions are retained
            // separately as sanitized diagnostics and cannot make the Andromeda cohort partial.
            'status'=>($payload['PAGES_COUNT']<=1 && !$rejected)?'complete':'partial',
            'offers'=>$offers,'rejected'=>$rejected,'excluded_rejected'=>$excludedRejected,
            'selection_enabled'=>false];
    }
    private static function offer(array $r,array $c,string $search,int $generation): array {
        foreach (['id','hotelKey','operatorKey','isOperatorHotelKey','price','currency','currencyKey','checkIn','nights','hotel','operator','meal','mealKey','room','htplace','adult','child'] as $k) {
            if (!array_key_exists($k,$r)) throw new InvalidArgumentException('MISSING_FIELD:'.$k);
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

        $mealRaw=self::text($r['meal']);
        $meal=AnyTourThreeProviderMealFamily::normalize($mealRaw);
        $roomRaw=self::text($r['room']);
        $placementRaw=self::text($r['htplace']);
        $room=AnyTourThreeProviderRoomPlacement::normalize('andromeda',$roomRaw,$placementRaw);

        $freightExternal=null;
        if (array_key_exists('freightExternal',$r)) {
            if (!in_array($r['freightExternal'],['Y','N'],true)) throw new InvalidArgumentException('INVALID_TRANSPORT_CONTEXT');
            $freightExternal=$r['freightExternal']==='Y';
        }
        $transportContext=[
            'tour_ref'=>self::optionalId($r,'tourKey'),
            'tour_label'=>self::optionalText($r,'tour'),
            'program_ref'=>self::optionalId($r,'programKey'),
            'program_label'=>self::optionalText($r,'program'),
            'spo_ref'=>self::optionalId($r,'spoKey'),
            'spo_label'=>self::optionalText($r,'spo'),
            'freight_external'=>$freightExternal,
            'departure_times_reported'=>self::optionalText($r,'departureTimes'),
            'surcharge'=>null,
            'surcharge_status'=>'unknown',
            'arithmetic_applied'=>false,
        ];

        $ref='offer_'.hash('sha256',json_encode([$search,$generation,$operator,$raw],JSON_THROW_ON_ERROR));
        return ['provider'=>'andromeda','supplier_namespace'=>$namespace,'search_ref'=>$search,'generation'=>$generation,
            'offer_ref'=>$ref,'external_hotel_id'=>$hotel,'local_hotel_id'=>null,'operator_ref'=>$operator,
            'hotel'=>self::text($r['hotel']),'operator'=>self::text($r['operator']),
            'check_in'=>$date->format('Y-m-d'),'nights'=>(int)$nights,'adults'=>(int)$adult,'children'=>(int)$child,
            'room'=>$room['room']['display_label'],'room_raw'=>$roomRaw,'room_normalized'=>$room['room']['normalized'],
            'placement'=>$room['placement']['display_label'],'placement_raw'=>$placementRaw,'placement_normalized'=>$room['placement']['normalized'],
            'meal'=>['label'=>$meal['display_label']??$mealRaw,'raw_label'=>$mealRaw,'canonical_key'=>$meal['canonical_key'],
                'family'=>$meal['family'],'classification_status'=>$meal['classification_status'],
                'operator_id'=>self::id($r['mealKey']),'andromeda_id'=>isset($r['andrMealKey'])?self::id($r['andrMealKey']):null],
            'transport_context'=>$transportContext,
            'price'=>['amount'=>(string)$price,'currency'=>$currency,'currency_id'=>$currencyId,'kind'=>'offer','fees'=>'unknown','final'=>false],
            'selection_enabled'=>false];
    }
}
