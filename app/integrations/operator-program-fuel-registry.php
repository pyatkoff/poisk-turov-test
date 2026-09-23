<?php
declare(strict_types=1);

require_once __DIR__ . '/operator-fuel-rule-evidence.php';

/**
 * Private exact program/tour fuel registry.
 *
 * This is narrower than the operator+direction fallback. A confirmed exact
 * programKey/tourKey rule wins; otherwise callers continue with the existing
 * direction fallback. No supplier I/O is performed here.
 */
final class AnyTourOperatorProgramFuelRegistryV1
{
    private const PREFIX = 'operator-program-fuel-v1-';
    private const MAX_BYTES = 131072;
    private const MAX_OBSERVATIONS = 32;

    public static function keyForOffer(array $offer): ?array
    {
        $family = AnyTourOperatorFuelRuleEvidenceV1::operatorFamily($offer['operator'] ?? null);
        $tc = is_array($offer['transport_context'] ?? null) ? $offer['transport_context'] : [];
        $program = self::ref($tc['program_ref'] ?? null);
        $tour = self::ref($tc['tour_ref'] ?? null);
        if ($family === null || $program === null || $tour === null) return null;
        return ['operator_family'=>$family,'program_key'=>$program,'tour_key'=>$tour];
    }

    public static function append(string $directory, array $raw, callable $write): array
    {
        self::assertDirectory($directory);
        $obs = self::observation($raw);
        $digest = self::hash($obs['key']);
        $path = self::path($directory, $digest);
        $current = self::readEnvelope($path, true);
        $rows = [];
        if ($current !== null) {
            self::assertEnvelope($current, $digest);
            if ($current['key'] !== $obs['key']) throw new DomainException('PROGRAM_FUEL_STORE_CONFLICT');
            $rows = $current['observations'];
        }
        $byEvidence = [];
        foreach ($rows as $row) $byEvidence[$row['evidence_sha256']] = $row;
        if (isset($byEvidence[$obs['evidence_sha256']])) {
            if ($byEvidence[$obs['evidence_sha256']] !== $obs) throw new DomainException('PROGRAM_FUEL_STORE_CONFLICT');
            return ['status'=>'unchanged','written'=>false,'path'=>$path,'observationCount'=>count($rows)];
        }
        $rows[] = $obs;
        usort($rows, static fn(array $a,array $b): int => [$a['observed_at'],$a['evidence_sha256']] <=> [$b['observed_at'],$b['evidence_sha256']]);
        if (count($rows) > self::MAX_OBSERVATIONS) $rows = array_slice($rows, -self::MAX_OBSERVATIONS);
        $next = ['version'=>1,'key_sha256'=>$digest,'key'=>$obs['key'],'observations'=>$rows];
        $encoded = json_encode($next, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        if (strlen($encoded) > self::MAX_BYTES) throw new DomainException('PROGRAM_FUEL_STORE_SIZE');
        if ($write($path, $next) !== true) throw new RuntimeException('PROGRAM_FUEL_STORE_WRITE');
        $read = self::readEnvelope($path, false);
        self::assertEnvelope($read, $digest);
        if ($read !== $next) throw new RuntimeException('PROGRAM_FUEL_STORE_READBACK');
        return ['status'=>$current===null?'created':'appended','written'=>true,'path'=>$path,'observationCount'=>count($rows)];
    }

    public static function priceForOffer(string $directory, array $offer, array $party, int $now): ?array
    {
        try {
            self::assertDirectory($directory);
            $key = self::keyForOffer($offer);
            if ($key === null || $now < 1) return null;
            $party = self::party($party);
            if (($offer['adults']??null)!==$party['adults'] || ($offer['children']??null)!==$party['children']) return null;
            if (self::hasInfant($party)) return null;

            $envelope = self::readEnvelope(self::path($directory, self::hash($key)), true);
            if ($envelope === null) return null;
            self::assertEnvelope($envelope, self::hash($key));

            $facts=[]; $offers=[]; $evidence=[]; $flightPairs=[]; $latestFx=null; $freshUntil=PHP_INT_MAX;
            foreach ($envelope['observations'] as $obs) {
                if ($obs['observed_at'] > $now || $obs['expires_at'] <= $now) continue;
                if ($obs['key'] !== $key || $obs['unit'] !== 'per_person_one_way' || $obs['direction_count'] !== 2) continue;
                $factKey = $obs['amount'].'|'.$obs['currency'].'|'.$obs['base_relation'].'|'.$obs['unit'];
                $facts[$factKey]=[$obs['amount'],$obs['currency'],$obs['base_relation']];
                $offers[$obs['offer_ref_digest']]=true; $evidence[$obs['evidence_sha256']]=true;
                if ($obs['flight_pair'] !== null) $flightPairs[self::hash($obs['flight_pair'])]=$obs['flight_pair'];
                $freshUntil=min($freshUntil,$obs['expires_at']);
                $fx=$obs['exchange'];
                if (is_array($fx) && $fx['observed_at'] <= $now && $fx['expires_at'] > $now
                    && ($latestFx===null || $fx['observed_at'] > $latestFx['observed_at'])) $latestFx=$fx;
            }
            if (count($offers)<2 || count($evidence)<2 || count($facts)!==1 || count($flightPairs)>1) return null;
            [$perPerson,$currency,$relation]=array_values($facts)[0];

            $base=$offer['price']??null;
            if (!is_array($base) || ($base['currency']??null)!=='RUB') return null;
            $baseUnits=self::units($base['amount']??null);
            $nativeUnits=self::units($perPerson);
            $passengers=$party['adults']+$party['children']; // infants already rejected; 2+ follow adult fare rule.
            if ($passengers<1 || $nativeUnits > intdiv(PHP_INT_MAX, $passengers*2)) return null;
            $nativeTotal=$nativeUnits*$passengers*2;

            $converted=$nativeTotal; $fxOut=null;
            if ($currency!=='RUB') {
                if (!is_array($latestFx) || $latestFx['from']!==$currency || $latestFx['to']!=='RUB') return null;
                $converted=self::convert($nativeTotal,$latestFx['rate']);
                $fxOut=$latestFx;
            }
            $increment=$relation==='excluded'?$converted:0;
            if ($baseUnits > 99999999999999-$increment) return null;
            $total=self::format($baseUnits+$increment);
            $digests=array_keys($evidence);sort($digests,SORT_STRING);
            $flightPair=count($flightPairs)===1?array_values($flightPairs)[0]:null;
            $rule=[
                'schema_version'=>1,'key'=>$key,'unit'=>'per_person_one_way',
                'amount'=>self::format($nativeUnits),'currency'=>$currency,
                'direction_count'=>2,'passenger_count'=>$passengers,
                'applied_native_total'=>self::format($nativeTotal),
                'base_relation'=>$relation,'flight_pair'=>$flightPair,
                'independent_offer_count'=>count($offers),'evidence_count'=>count($evidence),
                'evidence_sha256'=>self::hash($digests),'expires_at'=>$freshUntil,
                'exchange'=>$fxOut,
            ];
            $rule['rule_sha256']=self::hash($rule);
            $offerRef=$offer['offer_ref']??null;
            if(!is_string($offerRef)||preg_match('/\\Aoffer_[a-f0-9]{64}\\z/D',$offerRef)!==1)return null;
            return [
                'schema_version'=>1,
                'provider'=>'andromeda',
                'state'=>'estimated',
                'source'=>'operator_program_fuel_registry',
                'offer_ref_digest'=>hash('sha256',$offerRef),
                'party'=>$party,
                'final_price_verified'=>false,
                'arithmetic_applied'=>$relation==='excluded',
                'search_price'=>['amount'=>(string)$base['amount'],'currency'=>'RUB'],
                'party_surcharge'=>['amount'=>self::format($converted),'currency'=>'RUB',
                    'source'=>$currency==='RUB'?'andromeda_get_flights_transport':'andromeda_get_flights_transport_converted'],
                'search_price_with_surcharge'=>['amount'=>$total,'currency'=>'RUB','source'=>'derived_search_estimate'],
                'program_rule'=>$rule,
            ];
        } catch (Throwable $ignored) {
            return null;
        }
    }

    /**
     * Return the freshest unambiguous Andromeda claim EUR->RUB rate retained in
     * this existing registry. This is currency evidence only: no program fuel
     * amount, relation or flight fact is promoted by this reader.
     */
    public static function latestFreshExchange(string $directory, int $now): ?array
    {
        try {
            self::assertDirectory($directory);
            if ($now < 1) return null;
            $files = glob(rtrim($directory,'/') . '/' . self::PREFIX . '*.json', GLOB_NOSORT);
            if ($files === false || count($files) > 512) return null;
            $latestObserved = 0;
            $candidates = [];
            foreach ($files as $path) {
                if (!is_string($path) || is_link($path)) return null;
                $name = basename($path);
                if (preg_match('/\\A' . preg_quote(self::PREFIX,'/') . '([a-f0-9]{64})\\.json\\z/D', $name, $m) !== 1) {
                    return null;
                }
                $envelope = self::readEnvelope($path, false);
                self::assertEnvelope($envelope, $m[1]);
                foreach ($envelope['observations'] as $obs) {
                    $fx = $obs['exchange'] ?? null;
                    if (!is_array($fx) || ($fx['from'] ?? null) !== 'EUR' || ($fx['to'] ?? null) !== 'RUB'
                        || ($fx['observed_at'] ?? 0) > $now || ($fx['expires_at'] ?? 0) <= $now) continue;
                    $seen = (int)$fx['observed_at'];
                    if ($seen > $latestObserved) {
                        $latestObserved = $seen;
                        $candidates = [$fx];
                    } elseif ($seen === $latestObserved) {
                        $candidates[] = $fx;
                    }
                }
            }
            if ($candidates === []) return null;
            $rates = [];
            foreach ($candidates as $fx) $rates[$fx['rate']] = true;
            if (count($rates) !== 1) return null;
            usort($candidates, static function(array $a,array $b): int {
                $expiry = $b['expires_at'] <=> $a['expires_at'];
                return $expiry !== 0 ? $expiry : strcmp($a['evidence_sha256'],$b['evidence_sha256']);
            });
            $picked = $candidates[0];
            return [
                'from'=>'EUR','to'=>'RUB','rate'=>$picked['rate'],'source'=>'andromeda_claim_money',
                'observed_at'=>$picked['observed_at'],'expires_at'=>$picked['expires_at'],
                'evidence_sha256'=>$picked['evidence_sha256'],
            ];
        } catch (Throwable $ignored) {
            return null;
        }
    }

    public static function apply(array $dto,array $input,int $now):array
    {
        $hold=static fn(string $reason):array=>['dto'=>$dto,'applied'=>false,'reason'=>$reason];
        try{
            if($now<1||($dto['provider']??null)!=='andromeda'||($dto['quote_state']??null)!=='unknown'
                ||($dto['final_price_verified']??null)!==false||($dto['money']['arithmetic_applied']??null)!==false
                ||($dto['money']['additional_prices_reported']??null)!==[])return $hold('program_fuel_overlap_or_nonbase_state');
            if(($input['schema_version']??null)!==1||($input['provider']??null)!=='andromeda'
                ||($input['state']??null)!=='estimated'||($input['source']??null)!=='operator_program_fuel_registry'
                ||($input['final_price_verified']??null)!==false)return $hold('program_fuel_input');
            if(($input['offer_ref_digest']??null)!==($dto['identity']['offer_ref_digest']??null)
                ||!is_string($input['offer_ref_digest']??null)
                ||preg_match('/\\A[a-f0-9]{64}\\z/D',$input['offer_ref_digest'])!==1)return $hold('program_fuel_offer_binding');

            $party=self::party($input['party']??null);
            if($party!==self::party($dto['tour']['party']??null)||self::hasInfant($party))return $hold('program_fuel_party_binding');
            $base=$dto['money']['search_price']??null;
            if(!is_array($base)||($base['currency']??null)!=='RUB'
                ||($input['search_price']??null)!==['amount'=>$base['amount'],'currency'=>'RUB']
                ||($dto['price']??null)!==$base['amount'])return $hold('program_fuel_base_binding');

            $rule=$input['program_rule']??null;
            if(!is_array($rule)||($rule['schema_version']??null)!==1||($rule['unit']??null)!=='per_person_one_way'
                ||($rule['direction_count']??null)!==2||($rule['passenger_count']??null)!==($party['adults']+$party['children'])
                ||!in_array($rule['base_relation']??null,['included','excluded'],true)
                ||!is_int($rule['independent_offer_count']??null)||$rule['independent_offer_count']<2
                ||!is_int($rule['evidence_count']??null)||$rule['evidence_count']<2
                ||!is_int($rule['expires_at']??null)||$rule['expires_at']<=$now
                ||!is_string($rule['evidence_sha256']??null)||preg_match('/\\A[a-f0-9]{64}\\z/D',$rule['evidence_sha256'])!==1
                ||!is_string($rule['rule_sha256']??null)||preg_match('/\\A[a-f0-9]{64}\\z/D',$rule['rule_sha256'])!==1)return $hold('program_fuel_rule_invalid');
            $checkRule=$rule;unset($checkRule['rule_sha256']);
            if(self::hash($checkRule)!==$rule['rule_sha256'])return $hold('program_fuel_rule_hash');
            $family=AnyTourOperatorFuelRuleEvidenceV1::operatorFamily($dto['operator']['raw']??null);
            if($family===null||($rule['key']['operator_family']??null)!==$family)return $hold('program_fuel_operator_binding');

            $native=self::units($rule['amount']??null);
            $expectedNative=$native*$rule['passenger_count']*2;
            if(self::format($expectedNative)!==($rule['applied_native_total']??null))return $hold('program_fuel_native_total');
            $partySurcharge=$input['party_surcharge']??null;
            $total=$input['search_price_with_surcharge']??null;
            if(!is_array($partySurcharge)||($partySurcharge['currency']??null)!=='RUB'
                ||!in_array($partySurcharge['source']??null,['andromeda_get_flights_transport','andromeda_get_flights_transport_converted'],true)
                ||!is_array($total)||($total['currency']??null)!=='RUB'||($total['source']??null)!=='derived_search_estimate')return $hold('program_fuel_money');
            $charge=self::units($partySurcharge['amount']??null);$baseUnits=self::units($base['amount']??null);
            $relation=$rule['base_relation'];$expectedTotal=$baseUnits+($relation==='excluded'?$charge:0);
            if(self::format($expectedTotal)!==($total['amount']??null)
                ||($input['arithmetic_applied']??null)!==($relation==='excluded'))return $hold('program_fuel_total');

            $out=$dto;
            $out['money']['fuel_charge_reported']=['amount'=>self::format($charge),'currency'=>'RUB','source'=>'operator_program_fuel_rule'];
            $out['money']['operator_program_fuel_rule']=$rule;
            $out['money']['search_price_fuel_relation']=$relation;
            $out['money']['search_price_with_surcharge']=$total;
            $out['money']['arithmetic_applied']=$relation==='excluded';
            $out['finalPriceReady']=true;
            $out['finalPrice']=$out['price']=$total['amount'];
            $out['final_price_verified']=false;
            return ['dto'=>$out,'applied'=>true,'reason'=>null];
        }catch(Throwable $ignored){return $hold('program_fuel_invalid');}
    }

    public static function observation(array $row): array
    {
        $key=$row['key']??null;
        if (!is_array($key) || array_is_list($key)
            || !in_array($key['operator_family']??null,['fun_and_sun','intourist'],true)
            || ($program=self::ref($key['program_key']??null))===null
            || ($tour=self::ref($key['tour_key']??null))===null) throw new InvalidArgumentException('PROGRAM_FUEL_KEY');
        $key=['operator_family'=>$key['operator_family'],'program_key'=>$program,'tour_key'=>$tour];
        $amount=self::money($row['amount']??null);
        $currency=self::currency($row['currency']??null);
        if (($row['unit']??null)!=='per_person_one_way' || ($row['direction_count']??null)!==2
            || !in_array($row['base_relation']??null,['included','excluded'],true)) throw new InvalidArgumentException('PROGRAM_FUEL_SEMANTICS');
        $observed=self::positiveInt($row['observed_at']??null);
        $expires=self::positiveInt($row['expires_at']??null);
        if ($expires<=$observed) throw new InvalidArgumentException('PROGRAM_FUEL_TIME');
        $offer=self::digest($row['offer_ref_digest']??null);
        $evidence=self::digest($row['evidence_sha256']??null);
        $source=$row['source']??null;
        if (!in_array($source,['andromeda_get_flights','operator_official_reference'],true)) throw new InvalidArgumentException('PROGRAM_FUEL_SOURCE');
        $pair=null;
        if (array_key_exists('flight_pair',$row) && $row['flight_pair']!==null) $pair=self::flightPair($row['flight_pair']);
        $exchange=null;
        if (array_key_exists('exchange',$row) && $row['exchange']!==null) $exchange=self::exchange($row['exchange']);
        return [
            'schema_version'=>1,'key'=>$key,'unit'=>'per_person_one_way','amount'=>$amount,'currency'=>$currency,
            'direction_count'=>2,'base_relation'=>$row['base_relation'],'flight_pair'=>$pair,
            'offer_ref_digest'=>$offer,'evidence_sha256'=>$evidence,'source'=>$source,
            'observed_at'=>$observed,'expires_at'=>$expires,'exchange'=>$exchange,
        ];
    }

    private static function exchange(mixed $value): array
    {
        if (!is_array($value) || array_is_list($value)
            || ($value['from']??null)!=='EUR' || ($value['to']??null)!=='RUB'
            || !is_string($value['rate']??null) || preg_match('/\A(?:0|[1-9][0-9]{0,5})(?:\.[0-9]{1,8})?\z/D',$value['rate'])!==1
            || (float)$value['rate']<=0) throw new InvalidArgumentException('PROGRAM_FUEL_FX');
        $observed=self::positiveInt($value['observed_at']??null);
        $expires=self::positiveInt($value['expires_at']??null);
        if ($expires<=$observed) throw new InvalidArgumentException('PROGRAM_FUEL_FX');
        return ['from'=>'EUR','to'=>'RUB','rate'=>$value['rate'],'observed_at'=>$observed,'expires_at'=>$expires,
            'evidence_sha256'=>self::digest($value['evidence_sha256']??null)];
    }
    private static function flightPair(mixed $value): array
    {
        if (!is_array($value) || array_is_list($value) || array_keys($value)!==['outbound','return']) throw new InvalidArgumentException('PROGRAM_FUEL_FLIGHTS');
        $out=[];
        foreach (['outbound','return'] as $key) {
            $row=$value[$key]??null;
            if (!is_array($row) || array_is_list($row) || !is_string($row['flight']??null)
                || preg_match('/\A[A-Za-z0-9 .()\/-]{1,32}\z/D',$row['flight'])!==1) throw new InvalidArgumentException('PROGRAM_FUEL_FLIGHTS');
            $out[$key]=['flight'=>$row['flight']];
        }
        return $out;
    }
    private static function party(mixed $value): array
    {
        if (!is_array($value) || !is_int($value['adults']??null) || $value['adults']<1 || $value['adults']>9
            || !is_int($value['children']??null) || $value['children']<0 || $value['children']>9
            || !is_array($value['child_ages']??null) || !array_is_list($value['child_ages'])
            || count($value['child_ages'])!==$value['children']) throw new InvalidArgumentException('PROGRAM_FUEL_PARTY');
        $ages=$value['child_ages'];
        foreach($ages as $age) if(!is_int($age)||$age<0||$age>17) throw new InvalidArgumentException('PROGRAM_FUEL_PARTY');
        sort($ages,SORT_NUMERIC);
        return ['adults'=>$value['adults'],'children'=>$value['children'],'child_ages'=>$ages];
    }
    private static function hasInfant(array $party): bool { foreach($party['child_ages'] as $age) if($age<2)return true; return false; }
    private static function ref(mixed $v): ?string { if(is_int($v)&&$v>0)return(string)$v; return is_string($v)&&preg_match('/\A[1-9][0-9]{0,18}\z/D',$v)===1?$v:null; }
    private static function positiveInt(mixed $v): int { if(!is_int($v)||$v<1)throw new InvalidArgumentException('PROGRAM_FUEL_INT'); return $v; }
    private static function digest(mixed $v): string { if(!is_string($v)||preg_match('/\A[a-f0-9]{64}\z/D',$v)!==1)throw new InvalidArgumentException('PROGRAM_FUEL_DIGEST');return$v; }
    private static function currency(mixed $v): string { if(!is_string($v)||preg_match('/\A[A-Z]{3}\z/D',$v)!==1)throw new InvalidArgumentException('PROGRAM_FUEL_CURRENCY');return$v; }
    private static function money(mixed $v): string { if(!is_string($v)||preg_match('/\A(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?\z/D',$v)!==1)throw new InvalidArgumentException('PROGRAM_FUEL_MONEY');return$v; }
    private static function units(mixed $v): int { $v=self::money($v);$p=explode('.',$v,2);return(int)$p[0]*100+(int)str_pad($p[1]??'',2,'0'); }
    private static function format(int $u): string { return intdiv($u,100).'.'.str_pad((string)($u%100),2,'0',STR_PAD_LEFT); }
    private static function convert(int $u,string $rate): int {
        if(preg_match('/\A(?:0|[1-9][0-9]{0,5})(?:\.([0-9]{1,8}))?\z/D',$rate,$m)!==1)throw new InvalidArgumentException('PROGRAM_FUEL_RATE');
        $scale=strlen($m[1]??'');$factor=10**$scale;$num=(int)str_replace('.','',$rate);
        if($num<1||$u>intdiv(PHP_INT_MAX,$num))throw new InvalidArgumentException('PROGRAM_FUEL_RATE');
        $product=$u*$num;$out=intdiv($product,$factor);if($scale>0&&$product%$factor>=intdiv($factor,2))++$out;return$out;
    }
    private static function hash(mixed $value): string {
        $canonical=static function(mixed $v)use(&$canonical):mixed{if(!is_array($v))return$v;if(!array_is_list($v))ksort($v,SORT_STRING);foreach($v as $k=>$x)$v[$k]=$canonical($x);return$v;};
        return hash('sha256',json_encode($canonical($value),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
    }
    private static function path(string $directory,string $digest): string { return rtrim($directory,'/').'/'.self::PREFIX.$digest.'.json'; }
    private static function assertDirectory(string $directory): void {
        if(!is_dir($directory)||is_link($directory)||basename(rtrim($directory,'/'))!=='searches')throw new InvalidArgumentException('PROGRAM_FUEL_STORE_ROOT');
    }
    private static function readEnvelope(string $path,bool $optional): ?array {
        if(is_link($path))throw new DomainException('PROGRAM_FUEL_STORE_INVALID');
        if(!file_exists($path))return$optional?null:throw new DomainException('PROGRAM_FUEL_STORE_INVALID');
        if(!is_file($path)||filesize($path)<2||filesize($path)>self::MAX_BYTES)throw new DomainException('PROGRAM_FUEL_STORE_INVALID');
        $v=json_decode((string)file_get_contents($path),true,32,JSON_THROW_ON_ERROR);return is_array($v)?$v:throw new DomainException('PROGRAM_FUEL_STORE_INVALID');
    }
    private static function assertEnvelope(array $value,string $digest): void {
        $keys=array_keys($value);sort($keys);
        if($keys!==['key','key_sha256','observations','version']||($value['version']??null)!==1||($value['key_sha256']??null)!==$digest
            ||self::hash($value['key']??null)!==$digest||!is_array($value['observations']??null)||!array_is_list($value['observations'])
            ||count($value['observations'])>self::MAX_OBSERVATIONS)throw new DomainException('PROGRAM_FUEL_STORE_INVALID');
        foreach($value['observations'] as $row)if(!is_array($row)||self::observation($row)!==$row||$row['key']!==$value['key'])throw new DomainException('PROGRAM_FUEL_STORE_INVALID');
    }
}
