<?php
/** Deterministic provider-neutral Search3 intent key. No supplier translation or I/O. */
declare(strict_types=1);

final class AnyTourSearchScopeV1
{
    public const VERSION = 1;
    private const KEYS = [
        'departureId','countryId','dateFrom','dateTo','nightsFrom','nightsTo','adults','childs',
        'meal','hotelCategory','hotelRating','hotelTypes','hotelIds','hotelServices','arrivalId',
        'regionIds','subregionIds','operatorIds','priceFrom','priceTo','currency','onlyCharter','onlyDirect',
    ];

    private static function exactKeys(array $value): void
    {
        $got=array_keys($value);$want=self::KEYS;sort($got);sort($want);
        if($got!==$want)throw new InvalidArgumentException('ANYTOUR_SCOPE_FIELDS');
    }
    private static function integer(mixed $value,int $min,int $max,string $error): int
    {
        if((!is_int($value)&&!is_string($value))||!preg_match('/^(?:0|[1-9][0-9]*)$/D',(string)$value))throw new InvalidArgumentException($error);
        $number=(int)$value;if($number<$min||$number>$max)throw new InvalidArgumentException($error);return $number;
    }
    private static function positiveId(mixed $value,string $error): string
    {
        if((!is_int($value)&&!is_string($value))||!preg_match('/^[1-9][0-9]*$/D',(string)$value)||filter_var($value,FILTER_VALIDATE_INT)===false)throw new InvalidArgumentException($error);
        return (string)(int)$value;
    }
    private static function optionalToken(mixed $value,int $limit,string $error): string
    {
        if($value===null)return'';if(!is_string($value)&&!is_int($value))throw new InvalidArgumentException($error);
        $value=trim((string)$value);if($value==='' )return'';
        if(strlen($value)>$limit||!preg_match('//u',$value)||preg_match('/[\x00-\x1f\x7f]/',$value))throw new InvalidArgumentException($error);
        return $value;
    }
    private static function listTokens(mixed $value,string $error,int $max=100): array
    {
        if(!is_array($value)||!array_is_list($value)||count($value)>$max)throw new InvalidArgumentException($error);
        $out=[];foreach($value as $item){$token=self::optionalToken($item,128,$error);if($token!=='')$out[$token]=true;}
        $out=array_keys($out);sort($out,SORT_STRING);return $out;
    }
    private static function date(mixed $value,string $error): string
    {
        if(!is_string($value))throw new InvalidArgumentException($error);
        $d=DateTimeImmutable::createFromFormat('!Y-m-d',$value,new DateTimeZone('UTC'));
        if(!$d||$d->format('Y-m-d')!==$value)throw new InvalidArgumentException($error);return $value;
    }
    private static function money(mixed $value,string $error): string
    {
        if($value===null||$value==='')return'';
        if(!is_string($value)&&!is_int($value)&&!is_float($value))throw new InvalidArgumentException($error);
        $raw=trim((string)$value);if(!preg_match('/^(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?$/D',$raw))throw new InvalidArgumentException($error);
        $parts=explode('.',$raw,2);$whole=ltrim($parts[0],'0');if($whole==='')$whole='0';$fraction=rtrim($parts[1]??'','0');return $whole.($fraction===''?'':'.'.$fraction);
    }
    private static function flag(mixed $value,string $error): bool
    {
        if(is_bool($value))return$value;if($value==='true'||$value==='1'||$value===1)return true;if($value==='false'||$value==='0'||$value===0)return false;throw new InvalidArgumentException($error);
    }
    public static function normalize(array $params): array
    {
        self::exactKeys($params);
        $departure=self::positiveId($params['departureId'],'ANYTOUR_SCOPE_DEPARTURE');
        $country=self::positiveId($params['countryId'],'ANYTOUR_SCOPE_COUNTRY');
        $from=self::date($params['dateFrom'],'ANYTOUR_SCOPE_DATE');$to=self::date($params['dateTo'],'ANYTOUR_SCOPE_DATE');
        $fromDate=new DateTimeImmutable($from,new DateTimeZone('UTC'));$toDate=new DateTimeImmutable($to,new DateTimeZone('UTC'));
        $days=(int)$fromDate->diff($toDate)->format('%r%a');if($days<0||$days>21)throw new InvalidArgumentException('ANYTOUR_SCOPE_DATE_RANGE');
        $nightsFrom=self::integer($params['nightsFrom'],1,28,'ANYTOUR_SCOPE_NIGHTS');$nightsTo=self::integer($params['nightsTo'],1,28,'ANYTOUR_SCOPE_NIGHTS');
        if($nightsTo<$nightsFrom||$nightsTo-$nightsFrom>10)throw new InvalidArgumentException('ANYTOUR_SCOPE_NIGHTS_RANGE');
        $adults=self::integer($params['adults'],1,6,'ANYTOUR_SCOPE_ADULTS');
        if(!is_array($params['childs'])||!array_is_list($params['childs'])||count($params['childs'])>3)throw new InvalidArgumentException('ANYTOUR_SCOPE_CHILDREN');
        $children=[];foreach($params['childs'] as $age)$children[]=self::integer($age,0,17,'ANYTOUR_SCOPE_CHILD_AGE');sort($children,SORT_NUMERIC);
        $priceFrom=self::money($params['priceFrom'],'ANYTOUR_SCOPE_PRICE');$priceTo=self::money($params['priceTo'],'ANYTOUR_SCOPE_PRICE');
        if($priceFrom!==''&&$priceTo!==''&&(float)$priceTo<(float)$priceFrom)throw new InvalidArgumentException('ANYTOUR_SCOPE_PRICE_RANGE');
        if($params['currency']!=='RUB')throw new InvalidArgumentException('ANYTOUR_SCOPE_CURRENCY');
        return [
            'scopeVersion'=>self::VERSION,
            'departureId'=>$departure,'countryId'=>$country,'dateFrom'=>$from,'dateTo'=>$to,
            'nightsFrom'=>$nightsFrom,'nightsTo'=>$nightsTo,'adults'=>$adults,'childs'=>$children,
            'meal'=>self::optionalToken($params['meal'],128,'ANYTOUR_SCOPE_MEAL'),
            'hotelCategory'=>self::optionalToken($params['hotelCategory'],32,'ANYTOUR_SCOPE_CATEGORY'),
            'hotelRating'=>self::optionalToken($params['hotelRating'],32,'ANYTOUR_SCOPE_RATING'),
            'hotelTypes'=>self::listTokens($params['hotelTypes'],'ANYTOUR_SCOPE_HOTEL_TYPES',30),
            'hotelIds'=>self::listTokens($params['hotelIds'],'ANYTOUR_SCOPE_HOTELS',100),
            'hotelServices'=>self::listTokens($params['hotelServices'],'ANYTOUR_SCOPE_SERVICES',100),
            'arrivalId'=>self::optionalToken($params['arrivalId'],64,'ANYTOUR_SCOPE_ARRIVAL'),
            'regionIds'=>self::listTokens($params['regionIds'],'ANYTOUR_SCOPE_REGIONS',100),
            'subregionIds'=>self::listTokens($params['subregionIds'],'ANYTOUR_SCOPE_SUBREGIONS',100),
            'operatorIds'=>self::listTokens($params['operatorIds'],'ANYTOUR_SCOPE_OPERATORS',100),
            'priceFrom'=>$priceFrom,'priceTo'=>$priceTo,'currency'=>'RUB',
            'onlyCharter'=>self::flag($params['onlyCharter'],'ANYTOUR_SCOPE_CHARTER'),
            'onlyDirect'=>self::flag($params['onlyDirect'],'ANYTOUR_SCOPE_DIRECT'),
        ];
    }
    public static function json(array $normalized): string
    {
        return json_encode($normalized,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    }
    public static function fromParams(array $params): array
    {
        $normalized=self::normalize($params);return['version'=>self::VERSION,'params'=>$normalized,'digest'=>hash('sha256',self::json($normalized))];
    }
}
