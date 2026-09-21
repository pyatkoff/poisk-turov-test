<?php
declare(strict_types=1);
require_once __DIR__.'/andromeda-surcharge-evidence.php';

final class AnyTourAndromedaSurchargeEvidenceStoreV1
{
    private const PREFIX='andromeda-surcharge-group-v1-';
    private const MAX_BYTES=16384;

    public static function save(string $directory,array $evidence,array $provenance,callable $write):array
    {
        self::assertDirectory($directory);
        if(!AnyTourAndromedaSurchargeEvidenceV1::valid($evidence)||!self::validProvenance($provenance))
            throw new InvalidArgumentException('ANDROMEDA_SURCHARGE_EVIDENCE_STORE_INPUT');
        $path=self::path($directory,$evidence['group_key']);
        $next=['version'=>1,'provider'=>'andromeda','evidence'=>$evidence,'provenance'=>$provenance];
        $current=self::readEnvelope($path,true);
        if($current!==null){
            self::assertEnvelope($current,$evidence['group_key']);
            $currentObserved=$current['evidence']['observed_at'];$nextObserved=$evidence['observed_at'];
            if($currentObserved>$nextObserved)return ['status'=>'kept_newer','written'=>false,'path'=>$path];
            if($currentObserved===$nextObserved){
                if($current!==$next)throw new DomainException('ANDROMEDA_SURCHARGE_EVIDENCE_STORE_CONFLICT');
                return ['status'=>'unchanged','written'=>false,'path'=>$path];
            }
        }
        $encoded=json_encode($next,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        if(strlen($encoded)>self::MAX_BYTES)throw new DomainException('ANDROMEDA_SURCHARGE_EVIDENCE_STORE_INVALID');
        if($write($path,$next)!==true)throw new RuntimeException('ANDROMEDA_SURCHARGE_EVIDENCE_STORE_WRITE');
        $read=self::readEnvelope($path,false);self::assertEnvelope($read,$evidence['group_key']);
        if($read!==$next)throw new RuntimeException('ANDROMEDA_SURCHARGE_EVIDENCE_STORE_READBACK');
        return ['status'=>$current===null?'created':'replaced','written'=>true,'path'=>$path];
    }

    public static function readApplied(string $directory,array $offer,array $request,int $now):?array
    {
        try{
            self::assertDirectory($directory);
            $key=AndromedaSurchargeGroupKey::build($offer,$request);if($key===null)return null;
            $envelope=self::readEnvelope(self::path($directory,$key),true);if($envelope===null)return null;
            self::assertEnvelope($envelope,$key);
            return AnyTourAndromedaSurchargeEvidenceV1::apply($offer,$request,$envelope['evidence'],$now);
        }catch(Throwable $ignored){return null;}
    }

    private static function path(string $directory,string $groupKey):string
    {
        if(preg_match('/^andromeda-surcharge-v2:([a-f0-9]{64})$/D',$groupKey,$m)!==1)
            throw new InvalidArgumentException('ANDROMEDA_SURCHARGE_EVIDENCE_STORE_INPUT');
        return rtrim($directory,'/').'/'.self::PREFIX.$m[1].'.json';
    }

    private static function assertDirectory(string $directory):void
    {
        if(!is_dir($directory)||is_link($directory)||basename(rtrim($directory,'/'))!=='searches')
            throw new InvalidArgumentException('ANDROMEDA_SURCHARGE_EVIDENCE_STORE_ROOT');
    }

    private static function readEnvelope(string $path,bool $optional):?array
    {
        if(is_link($path))throw new DomainException('ANDROMEDA_SURCHARGE_EVIDENCE_STORE_INVALID');
        if(!file_exists($path))return $optional?null:throw new DomainException('ANDROMEDA_SURCHARGE_EVIDENCE_STORE_INVALID');
        if(!is_file($path)||filesize($path)<2||filesize($path)>self::MAX_BYTES)
            throw new DomainException('ANDROMEDA_SURCHARGE_EVIDENCE_STORE_INVALID');
        $value=json_decode((string)file_get_contents($path),true,20,JSON_THROW_ON_ERROR);
        return is_array($value)?$value:throw new DomainException('ANDROMEDA_SURCHARGE_EVIDENCE_STORE_INVALID');
    }

    private static function assertEnvelope(array $value,string $groupKey):void
    {
        $keys=array_keys($value);sort($keys);
        if($keys!==['evidence','provenance','provider','version']||($value['version']??null)!==1||($value['provider']??null)!=='andromeda'
            ||!is_array($value['evidence']??null)||!AnyTourAndromedaSurchargeEvidenceV1::valid($value['evidence'])
            ||($value['evidence']['group_key']??null)!==$groupKey||!is_array($value['provenance']??null)||!self::validProvenance($value['provenance']))
            throw new DomainException('ANDROMEDA_SURCHARGE_EVIDENCE_STORE_INVALID');
    }

    private static function validProvenance(array $value):bool
    {
        $keys=array_keys($value);sort($keys);
        return $keys===['source_offer_ref','source_search_ref','source_sha']
            &&is_string($value['source_sha'])&&preg_match('/^[a-f0-9]{40}$/D',$value['source_sha'])===1
            &&is_string($value['source_search_ref'])&&preg_match('/^[a-f0-9]{64}$/D',$value['source_search_ref'])===1
            &&is_string($value['source_offer_ref'])&&preg_match('/^offer_[a-f0-9]{64}$/D',$value['source_offer_ref'])===1;
    }
}
