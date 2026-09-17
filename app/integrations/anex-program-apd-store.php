<?php
declare(strict_types=1);

/**
 * INT-owned persistence for direct-ANEX program observations and sanitized
 * AdditionalPricesDaily rate evidence. No supplier transport and no listing
 * price arithmetic happen in this class.
 */
final class AnyTourAnexProgramApdStoreV1
{
    private const FLIGHT_CLASSES = ['charter', 'regular', 'unknown', 'mixed'];
    private const APD_STATES = ['rate', 'empty', 'ambiguous'];

    public static function recordProgram(PDO $db, array $fact, DateTimeImmutable $observedAt): array
    {
        $program = self::positiveInt($fact['supplier_program_id'] ?? null, 999999999, 'ANEX_PROGRAM_ID');
        $departure = self::positiveInt($fact['departure_id'] ?? null, 999999999, 'ANEX_PROGRAM_DEPARTURE');
        $country = self::positiveInt($fact['country_id'] ?? null, 999999999, 'ANEX_PROGRAM_COUNTRY');
        $currency = self::positiveInt($fact['supplier_currency_id'] ?? null, 999999999, 'ANEX_PROGRAM_CURRENCY');
        $flight = $fact['flight_class'] ?? null;
        if (!is_string($flight) || !in_array($flight, ['charter', 'regular', 'unknown'], true)) {
            throw new InvalidArgumentException('ANEX_PROGRAM_FLIGHT_CLASS');
        }
        $departureDate = self::date($fact['departure_date'] ?? null, 'ANEX_PROGRAM_DATE');
        $at = self::sqlTime($observedAt);
        $driver = (string)$db->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $sql = "INSERT INTO anytour_anex_programs
                (supplier_program_id,departure_id,country_id,supplier_currency_id,flight_class,first_seen_at,last_seen_at,first_departure_date,last_departure_date,observation_count)
                VALUES(:program,:departure,:country,:currency,:flight,:at,:at,:date,:date,1)
                ON DUPLICATE KEY UPDATE
                  flight_class=CASE
                    WHEN flight_class='mixed' THEN 'mixed'
                    WHEN VALUES(flight_class)='unknown' THEN flight_class
                    WHEN flight_class='unknown' THEN VALUES(flight_class)
                    WHEN flight_class=VALUES(flight_class) THEN flight_class
                    ELSE 'mixed' END,
                  first_seen_at=LEAST(first_seen_at,VALUES(first_seen_at)),
                  last_seen_at=GREATEST(last_seen_at,VALUES(last_seen_at)),
                  first_departure_date=LEAST(first_departure_date,VALUES(first_departure_date)),
                  last_departure_date=GREATEST(last_departure_date,VALUES(last_departure_date)),
                  observation_count=observation_count+1";
        } elseif ($driver === 'sqlite') {
            $sql = "INSERT INTO anytour_anex_programs
                (supplier_program_id,departure_id,country_id,supplier_currency_id,flight_class,first_seen_at,last_seen_at,first_departure_date,last_departure_date,observation_count)
                VALUES(:program,:departure,:country,:currency,:flight,:at,:at,:date,:date,1)
                ON CONFLICT(supplier_program_id,departure_id,country_id,supplier_currency_id) DO UPDATE SET
                  flight_class=CASE
                    WHEN flight_class='mixed' THEN 'mixed'
                    WHEN excluded.flight_class='unknown' THEN flight_class
                    WHEN flight_class='unknown' THEN excluded.flight_class
                    WHEN flight_class=excluded.flight_class THEN flight_class
                    ELSE 'mixed' END,
                  first_seen_at=MIN(first_seen_at,excluded.first_seen_at),
                  last_seen_at=MAX(last_seen_at,excluded.last_seen_at),
                  first_departure_date=MIN(first_departure_date,excluded.first_departure_date),
                  last_departure_date=MAX(last_departure_date,excluded.last_departure_date),
                  observation_count=observation_count+1";
        } else {
            throw new RuntimeException('ANEX_PROGRAM_STORE_DRIVER');
        }
        $q = $db->prepare($sql);
        $q->execute([
            'program' => $program, 'departure' => $departure, 'country' => $country, 'currency' => $currency,
            'flight' => $flight, 'at' => $at, 'date' => $departureDate,
        ]);
        return self::readProgram($db, $program, $departure, $country, $currency);
    }

    public static function recordApd(PDO $db, array $criteria, array $payload, DateTimeImmutable $observedAt, DateTimeImmutable $expiresAt): array
    {
        if ($expiresAt <= $observedAt) throw new InvalidArgumentException('ANEX_APD_EXPIRY');
        $ctx = self::criteria($criteria);
        $parsed = self::parsePayload($payload);
        $digest = hash('sha256', implode("\0", [
            (string)$ctx['supplier_program_id'], $ctx['date_beg'], (string)$ctx['nights'], (string)$ctx['supplier_currency_id'],
        ]));
        $at = self::sqlTime($observedAt);
        $expires = self::sqlTime($expiresAt);
        $params = [
            'program'=>$ctx['supplier_program_id'], 'date'=>$ctx['date_beg'], 'nights'=>$ctx['nights'],
            'currency'=>$ctx['supplier_currency_id'], 'digest'=>$digest, 'state'=>$parsed['state'],
            'total'=>$parsed['total_count'], 'rows'=>$parsed['row_count'],
            'adult'=>$parsed['price_adult'], 'child'=>$parsed['price_child'], 'cashrate'=>$parsed['cashrate'],
            'cadult'=>$parsed['price_converted_adult'], 'cchild'=>$parsed['price_converted_child'],
            'observed'=>$at, 'expires'=>$expires,
        ];
        $driver = (string)$db->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'mysql') {
            $sql = "INSERT INTO anytour_anex_apd_rates
              (supplier_program_id,date_beg,nights,supplier_currency_id,context_sha256,apd_state,total_count,row_count,price_adult,price_child,cashrate,price_converted_adult,price_converted_child,observed_at,expires_at,refresh_count)
              VALUES(:program,:date,:nights,:currency,:digest,:state,:total,:rows,:adult,:child,:cashrate,:cadult,:cchild,:observed,:expires,1)
              ON DUPLICATE KEY UPDATE
                context_sha256=VALUES(context_sha256),apd_state=VALUES(apd_state),total_count=VALUES(total_count),row_count=VALUES(row_count),
                price_adult=VALUES(price_adult),price_child=VALUES(price_child),cashrate=VALUES(cashrate),
                price_converted_adult=VALUES(price_converted_adult),price_converted_child=VALUES(price_converted_child),
                observed_at=VALUES(observed_at),expires_at=VALUES(expires_at),refresh_count=refresh_count+1";
        } elseif ($driver === 'sqlite') {
            $sql = "INSERT INTO anytour_anex_apd_rates
              (supplier_program_id,date_beg,nights,supplier_currency_id,context_sha256,apd_state,total_count,row_count,price_adult,price_child,cashrate,price_converted_adult,price_converted_child,observed_at,expires_at,refresh_count)
              VALUES(:program,:date,:nights,:currency,:digest,:state,:total,:rows,:adult,:child,:cashrate,:cadult,:cchild,:observed,:expires,1)
              ON CONFLICT(supplier_program_id,date_beg,nights,supplier_currency_id) DO UPDATE SET
                context_sha256=excluded.context_sha256,apd_state=excluded.apd_state,total_count=excluded.total_count,row_count=excluded.row_count,
                price_adult=excluded.price_adult,price_child=excluded.price_child,cashrate=excluded.cashrate,
                price_converted_adult=excluded.price_converted_adult,price_converted_child=excluded.price_converted_child,
                observed_at=excluded.observed_at,expires_at=excluded.expires_at,refresh_count=refresh_count+1";
        } else {
            throw new RuntimeException('ANEX_APD_STORE_DRIVER');
        }
        $q=$db->prepare($sql);$q->execute($params);
        return self::readApd($db, $criteria, $observedAt);
    }

    public static function readApd(PDO $db, array $criteria, DateTimeImmutable $now): array
    {
        $ctx=self::criteria($criteria);
        $q=$db->prepare("SELECT supplier_program_id,date_beg,nights,supplier_currency_id,context_sha256,apd_state,total_count,row_count,
            price_adult,price_child,cashrate,price_converted_adult,price_converted_child,observed_at,expires_at,refresh_count
            FROM anytour_anex_apd_rates
            WHERE supplier_program_id=:program AND date_beg=:date AND nights=:nights AND supplier_currency_id=:currency LIMIT 1");
        $q->execute(['program'=>$ctx['supplier_program_id'],'date'=>$ctx['date_beg'],'nights'=>$ctx['nights'],'currency'=>$ctx['supplier_currency_id']]);
        $row=$q->fetch(PDO::FETCH_ASSOC);
        if(!is_array($row)) return ['state'=>'missing','fresh'=>false,'criteria'=>$ctx];
        $state=(string)$row['apd_state'];
        if(!in_array($state,self::APD_STATES,true)) throw new RuntimeException('ANEX_APD_STORE_STATE');
        $fresh=(string)$row['expires_at']>self::sqlTime($now);
        return [
            'state'=>$state,'fresh'=>$fresh,'criteria'=>$ctx,'context_sha256'=>(string)$row['context_sha256'],
            'total_count'=>(int)$row['total_count'],'row_count'=>(int)$row['row_count'],
            'rates'=>[
                'adult'=>self::nullableDecimal($row['price_converted_adult'] ?? $row['price_adult'] ?? null),
                'child'=>self::nullableDecimal($row['price_converted_child'] ?? $row['price_child'] ?? null),
                'native_adult'=>self::nullableDecimal($row['price_adult'] ?? null),
                'native_child'=>self::nullableDecimal($row['price_child'] ?? null),
                'cashrate'=>self::nullableDecimal($row['cashrate'] ?? null),
            ],
            'observed_at'=>(string)$row['observed_at'],'expires_at'=>(string)$row['expires_at'],'refresh_count'=>(int)$row['refresh_count'],
        ];
    }

    public static function prewarmPrograms(PDO $db, int $departureId, int $countryId, DateTimeImmutable $seenSince, int $limit=1000): array
    {
        if($departureId<1||$countryId<1||$limit<1||$limit>5000) throw new InvalidArgumentException('ANEX_PROGRAM_PREWARM');
        $q=$db->prepare("SELECT supplier_program_id,departure_id,country_id,supplier_currency_id,flight_class,last_seen_at,last_departure_date,observation_count
            FROM anytour_anex_programs
            WHERE departure_id=:departure AND country_id=:country AND flight_class='charter' AND last_seen_at>=:since
            ORDER BY last_seen_at DESC,observation_count DESC,supplier_program_id ASC LIMIT ".$limit);
        $q->execute(['departure'=>$departureId,'country'=>$countryId,'since'=>self::sqlTime($seenSince)]);
        return $q->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private static function readProgram(PDO $db,int $program,int $departure,int $country,int $currency):array
    {
        $q=$db->prepare("SELECT supplier_program_id,departure_id,country_id,supplier_currency_id,flight_class,first_seen_at,last_seen_at,first_departure_date,last_departure_date,observation_count
            FROM anytour_anex_programs WHERE supplier_program_id=:program AND departure_id=:departure AND country_id=:country AND supplier_currency_id=:currency LIMIT 1");
        $q->execute(['program'=>$program,'departure'=>$departure,'country'=>$country,'currency'=>$currency]);
        $row=$q->fetch(PDO::FETCH_ASSOC);
        if(!is_array($row)) throw new RuntimeException('ANEX_PROGRAM_STORE_READBACK');
        if(!in_array((string)$row['flight_class'],self::FLIGHT_CLASSES,true)) throw new RuntimeException('ANEX_PROGRAM_STORE_STATE');
        return $row;
    }

    private static function criteria(array $criteria):array
    {
        $expected=['supplier_program_id','date_beg','nights','supplier_currency_id'];
        if(count($criteria)!==count($expected)||array_diff($expected,array_keys($criteria))||array_diff(array_keys($criteria),$expected)) {
            throw new InvalidArgumentException('ANEX_APD_CRITERIA');
        }
        return [
            'supplier_program_id'=>self::positiveInt($criteria['supplier_program_id'],999999999,'ANEX_APD_PROGRAM'),
            'date_beg'=>self::date($criteria['date_beg'],'ANEX_APD_DATE'),
            'nights'=>self::positiveInt($criteria['nights'],60,'ANEX_APD_NIGHTS'),
            'supplier_currency_id'=>self::positiveInt($criteria['supplier_currency_id'],999999999,'ANEX_APD_CURRENCY'),
        ];
    }

    private static function parsePayload(array $payload):array
    {
        $rows=$payload['data']??null;$total=$payload['totalCount']??null;
        if(is_string($total)&&preg_match('/\A[0-9]{1,9}\z/D',$total))$total=(int)$total;
        if(!is_int($total)||$total<0||!is_array($rows)||($rows!==[]&&array_keys($rows)!==range(0,count($rows)-1))||$total<count($rows)) {
            throw new InvalidArgumentException('ANEX_APD_PAYLOAD');
        }
        $base=['total_count'=>$total,'row_count'=>count($rows),'price_adult'=>null,'price_child'=>null,'cashrate'=>null,'price_converted_adult'=>null,'price_converted_child'=>null];
        if($total===0&&$rows===[]) return ['state'=>'empty']+$base;
        if($total!==1||count($rows)!==1||!is_array($rows[0])) return ['state'=>'ambiguous']+$base;
        $row=$rows[0];
        $adult=self::money($row['price_adult']??null,'ANEX_APD_ADULT');
        $child=self::money($row['price_chd']??null,'ANEX_APD_CHILD');
        $cash=self::nullableMoney($row['cashrate']??null,'ANEX_APD_CASHRATE',8);
        $cadult=self::nullableMoney($row['price_converted_adult']??null,'ANEX_APD_CONVERTED_ADULT');
        $cchild=self::nullableMoney($row['price_converted_chd']??null,'ANEX_APD_CONVERTED_CHILD');
        return array_replace(['state'=>'rate']+$base,[
            'price_adult'=>$adult,'price_child'=>$child,'cashrate'=>$cash,
            'price_converted_adult'=>$cadult,'price_converted_child'=>$cchild,
        ]);
    }

    private static function positiveInt($value,int $max,string $error):int
    {
        if((!is_int($value)&&!is_string($value))||!preg_match('/\A[1-9][0-9]{0,8}\z/D',(string)$value))throw new InvalidArgumentException($error);
        $n=(int)$value;if($n>$max)throw new InvalidArgumentException($error);return $n;
    }
    private static function date($value,string $error):string
    {
        if(!is_string($value)||!preg_match('/\A(\d{4})-(\d{2})-(\d{2})\z/D',$value,$m)||!checkdate((int)$m[2],(int)$m[3],(int)$m[1]))throw new InvalidArgumentException($error);
        return $value;
    }
    private static function money($value,string $error,int $scale=4):string
    {
        $v=self::nullableMoney($value,$error,$scale);if($v===null)throw new InvalidArgumentException($error);return $v;
    }
    private static function nullableMoney($value,string $error,int $scale=4):?string
    {
        if($value===null||$value==='')return null;
        if(is_int($value)||(is_float($value)&&is_finite($value)))$value=(string)$value;
        if(!is_string($value)||!preg_match('/\A(?:0|[1-9][0-9]{0,13})(?:\.[0-9]{1,'.(int)$scale.'})?\z/D',$value))throw new InvalidArgumentException($error);
        return $value;
    }
    private static function nullableDecimal($value):?string
    {
        if($value===null)return null;$v=(string)$value;$p=explode('.',$v,2);$fraction=rtrim($p[1]??'','0');return $p[0].($fraction===''?'':'.'.$fraction);
    }
    private static function sqlTime(DateTimeImmutable $at):string
    {
        return $at->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
