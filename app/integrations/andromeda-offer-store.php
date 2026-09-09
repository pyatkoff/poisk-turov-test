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
    private const TTL = 900; // Local retention policy, NOT supplier price validity.
    private const MAX_BYTES = 2097152;

    public function __construct(array &$privateState) { $this->state =& $privateState; }

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
        if (($payload['PAGE']??null)!==1) throw new InvalidArgumentException('FIRST_PAGE_REQUIRED');
        $projection=AnyTourAndromedaNormalizer::page($payload,$criteria,$searchRef,$generation);
        if ($resolver!==null) $projection=$resolver->apply($projection);
        $rejected=array_fill_keys(array_column($projection['rejected'],'index'),true);
        $raw=[]; $offerIndex=0;
        foreach ($payload['PRICES'] as $index=>$row) {
            if (isset($rejected[$index])) continue;
            $offer=$projection['offers'][$offerIndex++];
            $raw[$offer['offer_ref']]=$row['id'];
        }
        // Keep only normalized data + required private supplier IDs, not sid or raw response.
        $next=$this->state;
        $next['snapshot']=$projection;
        $allowed=['TOWNFROMINC','STATEINC','CHECKIN_BEG','CHECKIN_END','ADULT','CHILD',
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
