<?php
declare(strict_types=1);

/** Pure normalization for supplier-free recent user-search demand -> ANEX background scopes. */
final class AnyTourAnexLocalOfferDemandV1
{
    public static function normalizeRows(array $rows, int $limit = 50): array
    {
        if ($limit < 1 || $limit > 100 || !array_is_list($rows)) {
            throw new InvalidArgumentException('ANEX_DEMAND_LIMIT');
        }
        $out=[];$seen=[];
        foreach($rows as $row){
            if(!is_array($row))throw new InvalidArgumentException('ANEX_DEMAND_ROW');
            $departure=self::id($row['departure_id']??null);
            $country=self::id($row['country_id']??null);
            $region=($row['region_id']??null)===null?null:self::id($row['region_id']);
            $date=self::date($row['departure_date']??null);
            $nights=self::integer($row['nights']??null,1,28);
            $adults=self::integer($row['adults']??null,1,6);
            $children=self::integer($row['children_count']??null,0,3);
            $ages=self::ages($row['child_ages_signature']??'', $children);
            $searches=self::integer($row['searches']??null,1,100000000);
            $observations=self::integer($row['observations']??null,1,100000000);
            $lastSeen=self::timestamp($row['last_seen']??null);
            $key=implode('|',[$departure,$country,$region??0,$date,$nights,$adults,implode(',',$ages)]);
            if(isset($seen[$key]))continue;$seen[$key]=true;
            $out[]=[
                'departureId'=>$departure,'countryId'=>$country,'regionId'=>$region,
                'dateFrom'=>$date,'dateTo'=>$date,'nights'=>$nights,'adults'=>$adults,'childAges'=>$ages,
                'searches'=>$searches,'observations'=>$observations,'lastSeen'=>$lastSeen,
            ];
            if(count($out)>=$limit)break;
        }
        return $out;
    }

    private static function id(mixed $value): int
    {
        return self::integer($value,1,999999999);
    }
    private static function integer(mixed $value,int $min,int $max): int
    {
        if((!is_int($value)&&!is_string($value))||!preg_match('/\A(?:0|[1-9][0-9]*)\z/D',(string)$value)){
            throw new InvalidArgumentException('ANEX_DEMAND_INTEGER');
        }
        $n=(int)$value;if($n<$min||$n>$max)throw new InvalidArgumentException('ANEX_DEMAND_INTEGER');return $n;
    }
    private static function date(mixed $value): string
    {
        if(!is_string($value)||!preg_match('/\A(\d{4})-(\d{2})-(\d{2})\z/D',$value,$m)
            ||!checkdate((int)$m[2],(int)$m[3],(int)$m[1]))throw new InvalidArgumentException('ANEX_DEMAND_DATE');
        return $value;
    }
    private static function timestamp(mixed $value): string
    {
        if(!is_string($value)||!preg_match('/\A\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\z/D',$value))throw new InvalidArgumentException('ANEX_DEMAND_TIME');
        return $value;
    }
    private static function ages(mixed $value,int $children): array
    {
        if(!is_string($value))throw new InvalidArgumentException('ANEX_DEMAND_AGES');
        if($children===0){
            if($value!=='')throw new InvalidArgumentException('ANEX_DEMAND_AGES');
            return [];
        }
        $parts=explode(',',$value);
        if(count($parts)!==$children)throw new InvalidArgumentException('ANEX_DEMAND_AGES');
        $ages=[];foreach($parts as $part)$ages[]=self::integer($part,0,17);
        sort($ages,SORT_NUMERIC);return $ages;
    }
}
