<?php
declare(strict_types=1);
require_once __DIR__.'/andromeda-surcharge-group-key.php';

final class AnyTourAndromedaSurchargeEvidenceV1
{
    private const SOURCES=['andromeda_get_flights_transport','andromeda_get_flights_transport_converted'];
    private const PROGRAM_FIXED_AGGREGATION='single_distinct_party_markup';

    /** Existing strict evidence path. Its shape/key remain backward-compatible. */
    public static function capture(array $offer,array $request,array $fact,int $observedAt,int $expiresAt):?array
    {
        $key=AndromedaSurchargeGroupKey::build($offer,$request);
        return self::captureScoped($key,$offer,$fact,$observedAt,$expiresAt,null);
    }

    /**
     * Capture the narrower owner-approved program-fixed reuse class.
     *
     * Only an already-derived single distinct party markup is eligible. Choice-
     * dependent minimums, unknown surcharge and verified quote totals must never
     * seed this cross-night scope. The evidence remains an estimate, not final price.
     */
    public static function captureProgramFixed(
        array $offer,array $request,array $fact,int $observedAt,int $expiresAt
    ):?array {
        if(!self::isProgramFixedFactForOffer($offer,$fact))return null;
        $key=AndromedaSurchargeGroupKey::buildProgramFixed($offer,$request);
        return self::captureScoped($key,$offer,$fact,$observedAt,$expiresAt,'program_fixed');
    }

    /**
     * True only when this exact normalized offer could seed program-fixed evidence
     * from the supplied estimate. Collector orchestration uses the same predicate as
     * the cache writer so an in-run skip cannot obtain broader authority than the
     * durable evidence path would accept.
     */
    public static function isProgramFixedFactForOffer(array $offer,array $fact):bool
    {
        $price=self::price($offer);
        return $price!==null&&self::factMatches($fact,$price)&&self::programFixedFact($fact);
    }

    private static function captureScoped(
        ?string $key,array $offer,array $fact,int $observedAt,int $expiresAt,?string $reuseScope
    ):?array {
        $price=self::price($offer);
        if($key===null||$price===null||$observedAt<1||$expiresAt<=$observedAt||$expiresAt>$observedAt+300
            ||!self::factMatches($fact,$price))return null;
        $party=$fact['party_surcharge'];
        $evidence=['schema_version'=>1,'provider'=>'andromeda','state'=>'estimated','group_key'=>$key,
            'party_surcharge'=>['amount'=>$party['amount'],'currency'=>$party['currency'],'source'=>$party['source']],
            'observed_at'=>$observedAt,'expires_at'=>$expiresAt];
        if($reuseScope!==null)$evidence['reuse_scope']=$reuseScope;
        return $evidence;
    }

    public static function apply(array $offer,array $request,array $evidence,int $now):?array
    {
        if(!self::valid($evidence))return null;
        $programFixed=($evidence['reuse_scope']??null)==='program_fixed';
        $key=$programFixed
            ? AndromedaSurchargeGroupKey::buildProgramFixed($offer,$request)
            : AndromedaSurchargeGroupKey::build($offer,$request);
        $price=self::price($offer);
        if($key===null||$price===null||$evidence['group_key']!==$key
            ||$now<$evidence['observed_at']||$now>=$evidence['expires_at'])return null;
        $party=$evidence['party_surcharge'];
        if($party['currency']!==$price['currency'])return null;
        $total=self::add($price['amount'],$party['amount']);if($total===null)return null;
        $result=['schema_version'=>1,'provider'=>'andromeda','state'=>'estimated','search_price'=>$price,
            'party_surcharge'=>$party,'search_price_with_surcharge'=>['amount'=>$total,'currency'=>$price['currency'],'source'=>'derived_search_estimate'],
            'surcharge_scope'=>'party','arithmetic_applied'=>true,'final_price_verified'=>false];
        if($programFixed)$result['reuse_scope']='program_fixed';
        return $result;
    }

    public static function valid(array $e):bool
    {
        $party=$e['party_surcharge']??null;
        $scope=$e['reuse_scope']??null;
        $key=$e['group_key']??null;
        $keyValid=is_string($key)&&(
            ($scope===null&&preg_match('/^andromeda-surcharge-v2:[a-f0-9]{64}$/D',$key)===1)
            ||($scope==='program_fixed'&&preg_match('/^andromeda-program-surcharge-v1:[a-f0-9]{64}$/D',$key)===1)
        );
        return ($e['schema_version']??null)===1&&($e['provider']??null)==='andromeda'&&($e['state']??null)==='estimated'
            &&$keyValid
            &&is_int($e['observed_at']??null)&&$e['observed_at']>0&&is_int($e['expires_at']??null)&&$e['expires_at']>$e['observed_at']
            &&$e['expires_at']<=$e['observed_at']+300&&is_array($party)&&is_string($party['amount']??null)&&self::money($party['amount'],true)
            &&is_string($party['currency']??null)&&preg_match('/^[A-Z]{3}$/D',$party['currency'])===1&&in_array($party['source']??null,self::SOURCES,true);
    }

    private static function programFixedFact(array $fact):bool
    {
        $reported=$fact['transport_markup_reported']??null;
        return is_array($reported)
            &&($reported['aggregation']??null)===self::PROGRAM_FIXED_AGGREGATION
            &&($reported['source']??null)==='andromeda_get_flights_transport';
    }

    private static function price(array $offer):?array
    {
        $p=$offer['price']??null;
        if(!is_array($p)||!is_string($p['amount']??null)||!self::money($p['amount'],false)
            ||!is_string($p['currency']??null)||preg_match('/^[A-Z]{3}$/D',$p['currency'])!==1)return null;
        return ['amount'=>$p['amount'],'currency'=>$p['currency']];
    }

    private static function factMatches(array $f,array $price):bool
    {
        $party=$f['party_surcharge']??null;$total=$f['search_price_with_surcharge']??null;
        if(($f['schema_version']??null)!==1||($f['provider']??null)!=='andromeda'||($f['state']??null)!=='estimated'
            ||($f['search_price']??null)!==$price||($f['surcharge_scope']??null)!=='party'||($f['arithmetic_applied']??null)!==true
            ||($f['final_price_verified']??null)!==false||!is_array($party)||!is_array($total)
            ||!is_string($party['amount']??null)||!self::money($party['amount'],true)||($party['currency']??null)!==$price['currency']
            ||!in_array($party['source']??null,self::SOURCES,true)||!is_string($total['amount']??null)||!self::money($total['amount'],true)
            ||($total['currency']??null)!==$price['currency']||($total['source']??null)!=='derived_search_estimate')return false;
        $expected=self::add($price['amount'],$party['amount']);
        return $expected!==null&&self::same($expected,$total['amount']);
    }

    private static function add(string $a,string $b):?string
    {
        $scale=max(self::scale($a),self::scale($b));$x=self::units($a,$scale);$y=self::units($b,$scale);
        if($x===null||$y===null||$x>PHP_INT_MAX-$y)return null;$sum=$x+$y;if($scale===0)return (string)$sum;
        $factor=10**$scale;return intdiv($sum,$factor).'.'.str_pad((string)($sum%$factor),$scale,'0',STR_PAD_LEFT);
    }
    private static function same(string $a,string $b):bool{$s=max(self::scale($a),self::scale($b));$x=self::units($a,$s);return $x!==null&&$x===self::units($b,$s);}
    private static function scale(string $v):int{$p=strpos($v,'.');return $p===false?0:strlen($v)-$p-1;}
    private static function units(string $v,int $scale):?int
    {
        if($scale<0||$scale>2||!self::money($v,true))return null;$p=explode('.',$v,2);$f=$p[1]??'';if(strlen($f)>$scale)return null;
        $factor=10**$scale;$whole=(int)$p[0];if($whole>intdiv(PHP_INT_MAX,$factor))return null;
        return $whole*$factor+(int)str_pad($f,$scale,'0');
    }
    private static function money(string $v,bool $zero):bool{return preg_match('/^(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?$/D',$v)===1&&($zero||preg_match('/[1-9]/',$v)===1);}
}
