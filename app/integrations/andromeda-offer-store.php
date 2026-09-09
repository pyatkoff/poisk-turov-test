<?php
declare(strict_types=1);
require_once __DIR__.'/andromeda-normalizer.php';
require_once __DIR__.'/andromeda-hotel-resolver.php';

/**
 * Disabled integration building block. Pass ONLY a private server-session subarray,
 * under its existing session lock. Never serialize this state into browser output.
 * Does not start sessions, configure cookies, authorize booking or access the network.
 */
final class AnyTourAndromedaOfferStore {
    private $state;
    private $allowPages;
    private const TTL = 900; // Local retention policy, NOT supplier price validity.
    private const MAX_BYTES = 2097152;

    public function __construct(array &$privateState, bool $allowPages=false) { $this->state =& $privateState; $this->allowPages=$allowPages; }

    private static function publicUrl($value): ?string {
        if(!is_string($value)||strlen($value)>2048||preg_match('/[\x00-\x20\x7f]/',$value))return null;
        $u=parse_url($value);$host=strtolower($u['host']??'');
        if(!$u||($u['scheme']??'')!=='https'||isset($u['user'],$u['pass'])||isset($u['user'])||isset($u['port'])
            || !preg_match('/^[a-z0-9-]+(?:\.[a-z0-9-]+)*\.[a-z]{2,}$/D',$host)
            || preg_match('/\.(?:local|localhost|internal|lan)$/D',$host)
            || preg_match('/(?:^|&)(?:sid|token|password|auth|apikey|secret|session)=/i',$u['query']??''))return null;
        return $value;
    }

    public function begin(string $searchRef, int $generation, int $now): void {
        if (!preg_match('/^[A-Za-z0-9_-]{1,128}$/D',$searchRef) || $generation < 1 || $now < 1)
            throw new InvalidArgumentException('INVALID_SEARCH_CONTEXT');
        if ($this->state && (!isset($this->state['generation']) || !is_int($this->state['generation'])
            || $generation <= $this->state['generation'])) throw new RuntimeException('STALE_GENERATION');
        $this->state = ['version'=>1,'search_ref'=>$searchRef,'generation'=>$generation,
            'created_at'=>$now,'expires_at'=>$now+self::TTL,'snapshot'=>null,'criteria'=>[],'raw_ids'=>[]];
    }

    private function guard(string $searchRef, int $generation, int $now): void {
        $s=$this->state;
        if (($s['version']??null)!==1 || ($s['search_ref']??null)!==$searchRef
            || ($s['generation']??null)!==$generation) throw new RuntimeException('STALE_SEARCH');
        if (!isset($s['created_at'],$s['expires_at']) || $now<$s['created_at'] || $now>=$s['expires_at'])
            throw new RuntimeException('EXPIRED_SEARCH');
    }

    /** Capture one bounded page once. Later pages require a separately reviewed collector. */
    public function capture(array $payload, array $criteria, string $searchRef, int $generation, int $now,
        ?AnyTourAndromedaHotelResolver $resolver=null): array {
        $this->guard($searchRef,$generation,$now);
        if ($this->state['snapshot']!==null) throw new RuntimeException('SNAPSHOT_ALREADY_CAPTURED');
        if (!$this->allowPages && ($payload['PAGE']??null)!==1) throw new InvalidArgumentException('FIRST_PAGE_REQUIRED');
        if (($payload['PAGE']??null)!==($criteria['PAGE']??1)) throw new InvalidArgumentException('PAGE_MISMATCH');
        $projection=AnyTourAndromedaNormalizer::page($payload,$criteria,$searchRef,$generation);
        if ($resolver!==null) $projection=$resolver->apply($projection);
        $rejected=array_fill_keys(array_column($projection['rejected'],'index'),true);
        $raw=[]; $offerIndex=0;
        foreach ($payload['PRICES'] as $index=>$row) {
            if (isset($rejected[$index])) continue;
            $offer=$projection['offers'][$offerIndex];
            if($this->allowPages){
                $star=$row['star']??'';$category=null;
                if(is_scalar($star)&&preg_match('/^([1-5])(?:\*|\s|$)/',(string)$star,$match))$category=(int)$match[1];
                elseif(is_string($star)&&preg_match('/^\*{1,5}$/D',$star))$category=strlen($star);
                $projection['offers'][$offerIndex]['hotel_content']=['source'=>'andromeda',
                    'image_url'=>self::publicUrl($row['hotelImage']??null),'hotel_url'=>self::publicUrl($row['hotelUrl']??null),
                    'region'=>is_string($row['town']??null)?mb_substr($row['town'],0,180):'','category'=>$category];
            }
            ++$offerIndex;
            $raw[$offer['offer_ref']]=$row['id'];
        }
        // Keep only normalized data + required private supplier IDs, not sid or raw response.
        $next=$this->state;
        $next['snapshot']=$projection;
        $allowed=['AGES','TOWNFROMINC','STATEINC','CHECKIN_BEG','CHECKIN_END','ADULT','CHILD',
            'NIGHTS_FROM','NIGHTS_TILL','CURRENCYINC','MEAL','OPERATORS','PACKETTYPE','PAGE'];
        if (array_diff(array_keys($criteria),$allowed)) throw new InvalidArgumentException('UNSUPPORTED_CRITERIA');
        foreach ($criteria as $value) if (!is_int($value) && !is_string($value))
            throw new InvalidArgumentException('UNSUPPORTED_CRITERIA');
        $next['criteria']=$criteria;
        $next['raw_ids']=$raw;
        if (strlen(json_encode($next,JSON_THROW_ON_ERROR))>self::MAX_BYTES)
            throw new RuntimeException('SNAPSHOT_TOO_LARGE');
        $this->state=$next; // Atomic at the caller's existing session-lock boundary.
        return $projection;
    }

    public function projection(string $searchRef, int $generation, int $now): array {
        $this->guard($searchRef,$generation,$now);
        if (!is_array($this->state['snapshot'])) throw new RuntimeException('SNAPSHOT_MISSING');
        return $this->state['snapshot'];
    }

    /**
     * Server-only lookup; never return this structure to a browser.
     * Callers must still enforce session/CSRF, current mapping and quote/selection gates.
     */
    public function lookup(string $searchRef, int $generation, string $offerRef, int $now): array {
        $projection=$this->projection($searchRef,$generation,$now);
        if (!preg_match('/^offer_[a-f0-9]{64}$/D',$offerRef)
            || !isset($this->state['raw_ids'][$offerRef])) throw new RuntimeException('OFFER_NOT_FOUND');
        foreach ($projection['offers'] as $offer) {
            if ($offer['offer_ref']===$offerRef) return ['offer'=>$offer,
                'supplier_offer_id'=>$this->state['raw_ids'][$offerRef],
                'criteria'=>$this->state['criteria'],
                'selection_enabled'=>false,'quote_required'=>true];
        }
        throw new RuntimeException('OFFER_NOT_FOUND');
    }
}
