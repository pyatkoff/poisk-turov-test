<?php
declare(strict_types=1);
namespace AnyTour;

final class TourvisorInteractiveFailure extends \RuntimeException
{
    public function __construct(public readonly array $response, public readonly int $httpStatus)
    { parent::__construct('tourvisor_interactive_failure'); }
}

/** The existing interactive TV transport, shared without dispatch or CLI retries. */
final class TourvisorInteractiveClient
{
    public static function query(array $params): string
    {
        $parts = [];
        foreach ($params as $key => $value) {
            if ($value === null || $value === '') continue;
            if (is_bool($value)) $value = $value ? 'true' : 'false';
            if (is_array($value)) {
                foreach ($value as $item) {
                    if ($item === '' || $item === null) continue;
                    $parts[] = rawurlencode($key) . '=' . rawurlencode((string)$item);
                }
            } else $parts[] = rawurlencode($key) . '=' . rawurlencode((string)$value);
        }
        return implode('&', $parts);
    }

    private static function fail(string $message, int $status = 400, array $extra = []): never
    { throw new TourvisorInteractiveFailure(['ok'=>false,'error'=>$message]+$extra, $status); }

    public static function text($value, int $max): string
    { return mb_substr(trim((string)$value), 0, $max, 'UTF-8'); }

    private static function optionalInt($value): ?int
    {
        if ($value === null || $value === '') return null;
        $v = filter_var($value, FILTER_VALIDATE_INT);
        return $v === false ? null : (int)$v;
    }

    private static function integer($value, int $min, int $max, string $name): int
    {
        $v = filter_var($value, FILTER_VALIDATE_INT);
        if ($v === false || (int)$v < $min || (int)$v > $max) self::fail($name . ' must be between ' . $min . ' and ' . $max);
        return (int)$v;
    }

    private static function values(array $input, string $key, int $max, bool $unique = true): array
    {
        $value = $input[$key] ?? [];
        if ($value === '' || $value === null) return [];
        $out = [];
        foreach (array_slice(is_array($value) ? $value : [$value], 0, $max) as $v) {
            $v = self::text($v, 80);
            if ($v !== '') $out[] = $v;
        }
        return $unique ? array_values(array_unique($out)) : $out;
    }

    private static function day($value): ?string
    {
        $raw = self::text($value ?? '', 20);
        if ($raw === '') return null;
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $raw);
        $errors = \DateTimeImmutable::getLastErrors();
        return !$d || ($errors !== false && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0))
            ? null : $d->format('Y-m-d');
    }

    /** Same root search parsing; repeated ages describe separate people. */
    public static function searchParams(array $input): array
    {
        $departureId = self::optionalInt($input['departureId'] ?? null);
        $countryId = self::optionalInt($input['countryId'] ?? null);
        $dateFrom = self::day($input['dateFrom'] ?? null); $dateTo = self::day($input['dateTo'] ?? null);
        $nightsFrom = self::integer($input['nightsFrom'] ?? 5,1,28,'nightsFrom');
        $nightsTo = self::integer($input['nightsTo'] ?? 14,1,28,'nightsTo');
        $adults = self::integer($input['adults'] ?? 2,1,6,'adults'); $childs = [];
        foreach (self::values($input,'childs',3,false) as $age) $childs[] = self::integer($age,0,17,'child age');
        $priceFrom = self::optionalInt($input['priceFrom'] ?? null); $priceTo = self::optionalInt($input['priceTo'] ?? null);
        if (!$departureId || !$countryId || !$dateFrom || !$dateTo) self::fail('Invalid required search parameters');
        if ($dateFrom < (new \DateTimeImmutable('today'))->format('Y-m-d')) self::fail('dateFrom must not be in the past');
        if ($dateTo < $dateFrom) self::fail('dateTo must not be before dateFrom');
        $span = (new \DateTimeImmutable($dateFrom))->diff(new \DateTimeImmutable($dateTo))->days;
        if ($span === false || $span > 21) self::fail('date range must not exceed 21 days');
        if ($nightsTo < $nightsFrom) self::fail('nightsTo must not be less than nightsFrom');
        if ($nightsTo - $nightsFrom > 10) self::fail('nights range must not exceed 10');
        if ($priceFrom !== null && $priceFrom < 0) self::fail('priceFrom must be positive');
        if ($priceTo !== null && $priceTo < 0) self::fail('priceTo must be positive');
        if ($priceFrom !== null && $priceTo !== null && $priceTo < $priceFrom) self::fail('priceTo must not be less than priceFrom');
        return [
            'departureId'=>$departureId,'countryId'=>$countryId,'dateFrom'=>$dateFrom,'dateTo'=>$dateTo,
            'nightsFrom'=>$nightsFrom,'nightsTo'=>$nightsTo,'adults'=>$adults,'childs'=>$childs,
            'meal'=>self::text($input['meal'] ?? '',40),'hotelCategory'=>self::text($input['hotelCategory'] ?? '',20),
            'hotelRating'=>self::text($input['hotelRating'] ?? '',20),
            'hotelTypes'=>self::values($input,'hotelTypes',20),'hotelIds'=>self::values($input,'hotelIds',50),
            'hotelServices'=>self::values($input,'hotelServices',100),'arrivalId'=>self::optionalInt($input['arrivalId'] ?? null),
            'regionIds'=>self::values($input,'regionIds',50),'subregionIds'=>self::values($input,'subregionIds',50),
            'operatorIds'=>self::values($input,'operatorIds',50),'priceFrom'=>$priceFrom,'priceTo'=>$priceTo,
            'currency'=>self::text($input['currency'] ?? 'RUB',8) ?: 'RUB',
            'onlyCharter'=>filter_var($input['onlyCharter'] ?? false,FILTER_VALIDATE_BOOLEAN),
            'onlyDirect'=>filter_var($input['onlyDirect'] ?? false,FILTER_VALIDATE_BOOLEAN),
        ];
    }

    public static function get(string $path, array $params, string $token): array
    {
        if ($token === '') self::fail('Tourvisor is not configured',500);
        $url = 'https://api.tourvisor.ru/search/api/v1' . $path;
        $qs = self::query($params); if ($qs !== '') $url .= '?' . $qs;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER=>true, CURLOPT_CONNECTTIMEOUT=>10, CURLOPT_TIMEOUT=>45,
            CURLOPT_HTTPHEADER=>['Authorization: Bearer ' . $token,'Accept: application/json'],
        ]);
        $body = curl_exec($ch); $errno = curl_errno($ch);
        $code = (int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
        if ($errno) self::fail('Tourvisor connection error',502);
        $decoded = json_decode((string)$body,true);
        if ($code < 200 || $code >= 300) {
            self::fail('Tourvisor request failed',($code >= 400 && $code < 600) ? $code : 502,['upstreamStatus'=>$code]);
        }
        return is_array($decoded) ? $decoded : ['raw'=>self::text((string)$body,1000)];
    }
}
