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
    /**
     * Fields that must be identical before cached-scope reuse is considered.
     * Date and nights ranges are deliberately excluded: compatibility for them is
     * decided from each stored offer's concrete checkin/nights, not its source query.
     */
    private const HARD_KEYS = [
        'scopeVersion','departureId','countryId','adults','childs',
        'arrivalId','regionIds','subregionIds','currency','onlyCharter','onlyDirect',
    ];
    private const SCALAR_FILTER_KEYS = ['meal','hotelCategory','hotelRating'];
    private const LIST_FILTER_KEYS = ['hotelTypes','hotelIds','hotelServices','operatorIds'];

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
        $value=trim((string)$value);if($value==='')return'';
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
    private static function cents(string $value): int
    {
        if($value==='')throw new InvalidArgumentException('ANYTOUR_SCOPE_PRICE_EMPTY');
        $parts=explode('.',$value,2);return ((int)$parts[0])*100+(int)str_pad($parts[1]??'',2,'0');
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
        if($priceFrom!==''&&$priceTo!==''&&self::cents($priceTo)<self::cents($priceFrom))throw new InvalidArgumentException('ANYTOUR_SCOPE_PRICE_RANGE');
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
    public static function validateNormalized(array $normalized): array
    {
        if(($normalized['scopeVersion']??null)!==self::VERSION)throw new InvalidArgumentException('ANYTOUR_SCOPE_VERSION');
        $raw=$normalized;unset($raw['scopeVersion']);$checked=self::normalize($raw);
        if(self::json($checked)!==self::json($normalized))throw new InvalidArgumentException('ANYTOUR_SCOPE_NORMALIZED');
        return $checked;
    }
    public static function json(array $normalized): string
    {
        return json_encode($normalized,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    }
    public static function familyParams(array $normalized): array
    {
        $normalized=self::validateNormalized($normalized);$family=[];
        foreach(self::HARD_KEYS as $key)$family[$key]=$normalized[$key];
        return $family;
    }
    public static function familyDigest(array $normalized): string
    {
        return hash('sha256',self::json(self::familyParams($normalized)));
    }
    /** True only when every result allowed by the saved filtered scope is also allowed by current, apart from concrete trip dates/nights. */
    public static function savedSubsetOfCurrent(array $saved,array $current): bool
    {
        $saved=self::validateNormalized($saved);$current=self::validateNormalized($current);
        if(self::json(self::familyParams($saved))!==self::json(self::familyParams($current)))return false;
        foreach(self::SCALAR_FILTER_KEYS as $key){
            if($current[$key]!==''&&$saved[$key]!==$current[$key])return false;
        }
        foreach(self::LIST_FILTER_KEYS as $key){
            if($current[$key]===[])continue;
            if($saved[$key]===[]||array_diff($saved[$key],$current[$key])!==[])return false;
        }
        if($current['priceFrom']!==''&&($saved['priceFrom']===''||self::cents($saved['priceFrom'])<self::cents($current['priceFrom'])))return false;
        if($current['priceTo']!==''&&($saved['priceTo']===''||self::cents($saved['priceTo'])>self::cents($current['priceTo'])))return false;
        return true;
    }
    /** Stored supplier facts must satisfy the current concrete trip window before they can be reused. */
    public static function offerMatchesTrip(array $offer,array $current): bool
    {
        $current=self::validateNormalized($current);$tour=$offer['tour']??null;
        if(!is_array($tour))throw new RuntimeException('ANYTOUR_OFFER_TRIP_INTEGRITY');
        try{
            $checkin=self::date($tour['checkin']??null,'ANYTOUR_OFFER_TRIP_INTEGRITY');
            $nights=self::integer($tour['nights']??null,1,28,'ANYTOUR_OFFER_TRIP_INTEGRITY');
            $party=$tour['party']??null;
            if(!is_array($party))throw new InvalidArgumentException('ANYTOUR_OFFER_TRIP_INTEGRITY');
            $adults=self::integer($party['adults']??null,1,6,'ANYTOUR_OFFER_TRIP_INTEGRITY');
            $children=self::integer($party['children']??null,0,3,'ANYTOUR_OFFER_TRIP_INTEGRITY');
            $ages=$party['child_ages']??null;
            if(!is_array($ages)||!array_is_list($ages)||count($ages)!==$children)throw new InvalidArgumentException('ANYTOUR_OFFER_TRIP_INTEGRITY');
            $normalizedAges=[];foreach($ages as $age)$normalizedAges[]=self::integer($age,0,17,'ANYTOUR_OFFER_TRIP_INTEGRITY');sort($normalizedAges,SORT_NUMERIC);
        }catch(InvalidArgumentException $e){throw new RuntimeException('ANYTOUR_OFFER_TRIP_INTEGRITY',0,$e);}
        if($adults!==$current['adults']||$normalizedAges!==$current['childs'])return false;
        if($checkin<$current['dateFrom']||$checkin>$current['dateTo'])return false;
        return $nights>=$current['nightsFrom']&&$nights<=$current['nightsTo'];
    }
    public static function fromParams(array $params): array
    {
        $normalized=self::normalize($params);return['version'=>self::VERSION,'params'=>$normalized,'digest'=>hash('sha256',self::json($normalized))];
    }
}
