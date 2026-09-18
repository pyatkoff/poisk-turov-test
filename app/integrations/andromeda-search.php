<?php
declare(strict_types=1);
require_once __DIR__.'/andromeda-client.php';
require_once __DIR__.'/andromeda-offer-store.php';

/**
 * Provider handler for the first bounded Search3 integration.
 * The caller supplies a private state under its existing lock and an atomic durable
 * save callback. The lock must span start/resume. No sessions or HTTP routes here.
 * Enable only behind the reviewed account budget and preview/session/CSRF gates.
 */
final class AnyTourAndromedaSearch {
    private $state;
    private $save;
    private $enabled;
    private $dynamic;
    private $checkpointFailed = false;

    public function __construct(array &$privateState, callable $save, bool $enabled = false, bool $dynamic = false) {
        $this->state =& $privateState;
        $this->save = $save;
        $this->enabled = $enabled;
        $this->dynamic = $dynamic;
    }

    public function __debugInfo(): array { return ['enabled'=>$this->enabled]; }
    public function __serialize(): array { throw new RuntimeException('ANDROMEDA_SERIALIZATION_DISABLED'); }

    /** Deliberately only the already captured pilot; never silently narrow a search. */
    public static function supports(array $criteria): bool {
        $expected=AnyTourAndromedaClient::priceProbeParams();
        ksort($expected); ksort($criteria);
        return $criteria===$expected;
    }

    private function accepts(array $criteria): bool {
        if (!$this->dynamic) return self::supports($criteria);
        try { AnyTourAndromedaClient::validatePriceParams($criteria); return true; }
        catch (InvalidArgumentException $ignored) { return false; }
    }

    private function commit(array $next): void {
        // Failure is ambiguous: retain local reservation too; never continue to API.
        $this->state=$next;
        try { $saved=($this->save)($next); }
        catch (Throwable $ignored) { $this->checkpointFailed=true; throw new RuntimeException('ANDROMEDA_CHECKPOINT_UNAVAILABLE'); }
        if ($saved!==true) { $this->checkpointFailed=true; throw new RuntimeException('ANDROMEDA_CHECKPOINT_UNAVAILABLE'); }
    }

    public function start(array $criteria, string $searchRef, int $generation, int $now,
        AnyTourAndromedaClient $client, string $username, string $password,
        ?AnyTourAndromedaHotelResolver $resolver=null): array {
        if (!$this->enabled) throw new RuntimeException('ANDROMEDA_DISABLED');
        if ($this->checkpointFailed) throw new RuntimeException('ANDROMEDA_CHECKPOINT_UNAVAILABLE');
        if (!$this->accepts($criteria)) throw new InvalidArgumentException('ANDROMEDA_SEARCH_UNSUPPORTED');
        if (!preg_match('/^[A-Za-z0-9_-]{1,128}$/D',$searchRef) || $generation<1 || $now<1)
            throw new InvalidArgumentException('INVALID_SEARCH_CONTEXT');
        if ($this->state) {
            if (($this->state['version']??null)!==1 || !is_int($this->state['generation']??null))
                throw new RuntimeException('ANDROMEDA_CHECKPOINT_INVALID');
            if ($generation<=$this->state['generation']) throw new RuntimeException('ANDROMEDA_SEARCH_REPLAY_REFUSED');
            if (in_array($this->state['status']??null,['pending','unavailable'],true))
                throw new RuntimeException('ANDROMEDA_PREVIOUS_RESULT_UNKNOWN');
        }
        $storeState=[];
        (new AnyTourAndromedaOfferStore($storeState,$this->dynamic))->begin($searchRef,$generation,$now);
        $next=['version'=>1,'search_ref'=>$searchRef,'generation'=>$generation,
            'status'=>'pending','criteria'=>$criteria,'store'=>$storeState,'error'=>null];
        $this->commit($next); // Durable reservation before login; no credentials/sid saved.
        try {
            $client->ensureLogin($username,$password);
            $payload=$this->dynamic ? $client->price($criteria) : $client->priceProbe();
            $store=new AnyTourAndromedaOfferStore($storeState,$this->dynamic);
            $projection=$store->capture($payload,$criteria,$searchRef,$generation,$now,$resolver);
            $next['store']=$storeState;
            $next['status']=$projection['status'];
        } catch (Throwable $ignored) {
            // Even a connection error may follow supplier execution. No retry/re-login.
            $next['status']='unavailable';
            $next['error']='supplier_result_unavailable';
        }
        $this->commit($next);
        return $this->resume($searchRef,$generation,$now);
    }

    /** Read-only return path: does not create a client or perform supplier calls. */
    public function resume(string $searchRef, int $generation, int $now): array {
        if (!$this->enabled) throw new RuntimeException('ANDROMEDA_DISABLED');
        if ($this->checkpointFailed) throw new RuntimeException('ANDROMEDA_CHECKPOINT_UNAVAILABLE');
        if (($this->state['version']??null)!==1 || ($this->state['search_ref']??null)!==$searchRef
            || ($this->state['generation']??null)!==$generation) throw new RuntimeException('STALE_SEARCH');
        $s=$this->state;
        if (!is_array($s['store']??null) || !$this->accepts($s['criteria']??[])
            || !in_array($s['status']??null,['pending','complete','partial','unavailable'],true))
            throw new RuntimeException('ANDROMEDA_CHECKPOINT_INVALID');
        if ($now<($s['store']['created_at']??PHP_INT_MAX) || $now>=($s['store']['expires_at']??0))
            throw new RuntimeException('EXPIRED_SEARCH');
        $out=['provider'=>'andromeda','search_ref'=>$searchRef,'generation'=>$generation,
            'status'=>$s['status'],'selection_enabled'=>false,
            'date_range'=>['from'=>DateTimeImmutable::createFromFormat('!Ymd',$s['criteria']['CHECKIN_BEG'])->format('Y-m-d'),'to'=>DateTimeImmutable::createFromFormat('!Ymd',$s['criteria']['CHECKIN_END'])->format('Y-m-d')],
            'page'=>null,'pages_count'=>null,'hotels'=>[],'offers'=>[],
            'error'=>$s['status']==='unavailable'?'supplier_result_unavailable':null];
        if (in_array($s['status'],['pending','unavailable'],true)) return $out;
        $storeState=$s['store'];
        $page=(new AnyTourAndromedaOfferStore($storeState,$this->dynamic))->projection($searchRef,$generation,$now);
        $out['page']=$page['page']; $out['pages_count']=$page['pages_count'];
        $out['offers']=$page['offers'];
        $groups=[];
        foreach ($page['offers'] as $offer) {
            $local=$offer['local_hotel_id'];
            $key=$local!==null ? 'catalog:'.$local
                : 'andromeda:'.$offer['supplier_namespace'].':'.$offer['external_hotel_id'];
            if (!isset($groups[$key])) $groups[$key]=['card_key'=>$key,'local_hotel_id'=>$local,
                'name'=>$offer['hotel'],'name_source'=>'andromeda','provider'=>'andromeda',
                'mapping_status'=>$local!==null?'resolved':'unresolved','offer_refs'=>[]];
            $groups[$key]['offer_refs'][]=$offer['offer_ref'];
        }
        $out['hotels']=array_values($groups);
        return $out; // No raw supplier IDs, sid, credentials or private criteria state.
    }
}
