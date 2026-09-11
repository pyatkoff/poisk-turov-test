<?php
declare(strict_types=1);

require_once __DIR__ . '/anex-search.php';

/**
 * Stateful, preview-only boundary for separate HTTP search/expand/flights calls.
 * Session state is server-owned and never contains the ANEX token or client.
 */
final class AnyTourAnexPreviewGateway
{
    private const SESSION_TTL = 900;
    private const BURST_WINDOW_SECONDS = 1;
    private const BURST_REQUEST_LIMIT = 10;
    private const WINDOW_SECONDS = 60;
    // Keep preview traffic below the supplier's 60 requests/minute token limit.
    private const WINDOW_REQUEST_LIMIT = 20;
    private $clientFactory;
    private $resolver;
    private $sensitive;
    private $clock;

    public function __construct(
        callable $clientFactory,
        ?callable $resolver = null,
        array $sensitive = [],
        ?callable $clock = null
    ) {
        $this->clientFactory = $clientFactory;
        $this->resolver = $resolver;
        $this->sensitive = $sensitive;
        $this->clock = $clock ?? static function (): int { return time(); };
    }

    public function handle(array $request, array &$session): array
    {
        $now = ($this->clock)();
        if (!is_int($now) || $now < 1) {
            throw new RuntimeException('ANEX_CLOCK_ERROR');
        }
        $this->prepareSession($session, $now);
        $this->consumeRequest($session);
        $action = $request['action'] ?? null;
        if (!is_string($action) || !in_array($action, ['search', 'expand', 'flights', 'offer'], true)) {
            throw new InvalidArgumentException('ANEX_INVALID_ACTION');
        }
        // Saved reads never instantiate the token-bearing client or fetch details.
        if ($action === 'offer') return $this->savedOffer($request, $session, $now);

        if ($action === 'search') {
            if (!self::exactKeys($request, ['action', 'criteria']) || !is_array($request['criteria'])) {
                throw new InvalidArgumentException('ANEX_INVALID_REQUEST');
            }
            // A failed replacement search invalidates earlier supplier references.
            unset($session['search'], $session['saved_offers']);
            $search = $this->newSearch();
            $result = $search->search($request['criteria']);
            $session['search'] = $search->snapshot();
            $session['saved_offers'] = [
                'search_ref' => bin2hex(random_bytes(16)),
                'created_at' => $now, 'expires_at' => $now + self::SESSION_TTL,
                'search' => $result['search'], 'offers' => [],
            ];
            $this->rememberOffers($result, $session, $now);
        } else {
            if (!self::exactKeys($request, ['action', 'offer_key'])
                || !is_string($request['offer_key'])
                || !preg_match('/\Aanex_online:[a-f0-9]{64}\z/D', $request['offer_key'])) {
                throw new InvalidArgumentException('ANEX_INVALID_REQUEST');
            }
            if (!isset($session['search']) || !is_array($session['search'])) {
                throw new InvalidArgumentException('ANEX_SESSION_REQUIRED');
            }
            $search = $this->newSearch();
            $search->restore($session['search']);
            $result = $action === 'expand'
                ? $search->expand($request['offer_key'])
                : $search->flights($request['offer_key']);
            if ($action === 'expand') {
                $session['search'] = $search->snapshot();
                $this->rememberOffers($result, $session, $now);
            }
        }
        $session['expires_at'] = $now + self::SESSION_TTL;
        $public = $this->publicResult($result);
        if (isset($public['offers'], $session['saved_offers']['search_ref'])) {
            $public['search_ref'] = $session['saved_offers']['search_ref'];
        }
        return $public;
    }

    /** Reuse the existing session; retain no more facts than its known offer set. */
    private function rememberOffers(array $result, array &$session, int $now): void
    {
        if (!isset($session['saved_offers']) || $now >= $session['saved_offers']['expires_at']) return;
        $known = array_column($session['search']['offers'], null, 'offer_key');
        foreach (array_slice($result['offers'], 0, 300) as $offer) {
            $key = $offer['offer_key'];
            if (!isset($known[$key])) continue;
            unset($offer['supplier_offer_id']);
            $session['saved_offers']['offers'][$key] = ['offer' => $offer, 'observed_at' => $now];
        }
        $session['saved_offers']['offers'] = array_intersect_key($session['saved_offers']['offers'], $known);
    }

    /** Current saved search/offer/local identity only; never a package or quote. */
    private function savedOffer(array $request, array $session, int $now): array
    {
        if (!self::exactKeys($request, ['action', 'search_ref', 'offer_key', 'local_hotel_id'])
            || !is_string($request['search_ref']) || !preg_match('/\A[a-f0-9]{32}\z/D', $request['search_ref'])
            || !is_string($request['offer_key']) || !preg_match('/\Aanex_online:[a-f0-9]{64}\z/D', $request['offer_key'])
            || !is_int($request['local_hotel_id']) || $request['local_hotel_id'] < 1 || $request['local_hotel_id'] > 999999999) {
            throw new InvalidArgumentException('ANEX_INVALID_REQUEST');
        }
        $result = ['provider' => 'anex', 'search_ref' => $request['search_ref'],
            'offer_key' => $request['offer_key'], 'status' => 'expired',
            'offer' => null, 'selection_state' => 'disabled'];
        $saved = $session['saved_offers'] ?? null;
        if (!is_array($saved) || !is_array($session['search'] ?? null)
            || !is_int($saved['created_at'] ?? null) || !is_int($saved['expires_at'] ?? null)
            || $saved['expires_at'] !== $saved['created_at'] + self::SESSION_TTL
            || $now < $saved['created_at'] || $now >= $saved['expires_at']) return $result;
        if (($saved['search_ref'] ?? null) !== $request['search_ref']) {
            return array_replace($result, ['status' => 'mismatch']);
        }
        $entry = $saved['offers'][$request['offer_key']] ?? null;
        if (!is_array($entry) || !is_array($entry['offer'] ?? null)) {
            return array_replace($result, ['status' => 'not_loaded']);
        }
        $offer = $entry['offer'];
        $known = array_column($session['search']['offers'], null, 'offer_key');
        $reference = $known[$request['offer_key']] ?? null;
        if (!is_array($reference) || ($offer['offer_key'] ?? null) !== $request['offer_key']
            || ($reference['kind'] ?? null) !== ($offer['kind'] ?? null)
            || ($reference['hotel_external_id'] ?? null) !== ($offer['hotel']['external_id'] ?? null)
            || !is_int($entry['observed_at'] ?? null) || $entry['observed_at'] < $saved['created_at']
            || $entry['observed_at'] > $now) {
            throw new InvalidArgumentException('ANEX_INVALID_SESSION');
        }
        if ($offer['kind'] === 'group_minimum') {
            return array_replace($result, ['status' => 'group_minimum']);
        }
        $external = $offer['hotel']['external_id'];
        // The HTTP entrypoint builds the current registry for every request.
        $local = $this->resolver === null ? null : ($this->resolver)('anex_online', $external);
        if ($local === null) return array_replace($result, ['status' => 'identity_unresolved']);
        if ($local !== $request['local_hotel_id'] || $local !== ($offer['hotel']['local_id'] ?? null)) {
            return array_replace($result, ['status' => 'identity_changed']);
        }
        require_once __DIR__ . '/three-provider-anex-offer.php';
        require_once __DIR__ . '/three-provider-offer-context.php';
        $dto = AnyTourThreeProviderAnexOffer::fromPage([
            'schema_version' => 1, 'provider' => 'anex', 'supplier_namespace' => 'anex_online',
            'search' => $saved['search'], 'offers' => [$offer],
        ], 0, ['supplier_namespace' => 'anex_online', 'external_id' => $external, 'local_id' => $local],
            $saved['search_ref'], gmdate('Y-m-d\TH:i:s\Z', $entry['observed_at']));
        $retained = AnyTourThreeProviderOfferContext::retain($dto, 1, 1, $saved['created_at'], self::SESSION_TTL);
        $current = array_intersect_key($retained, array_flip(['provider', 'operator', 'local_hotel_id', 'identity', 'generation', 'page']));
        return array_replace($result, ['status' => 'current', 'offer' => $dto,
            'context' => AnyTourThreeProviderOfferContext::validate($retained, $current, $now)]);
    }

    private function newSearch(): AnyTourAnexSearch
    {
        $client = ($this->clientFactory)();
        if (!$client instanceof AnyTourAnexClient) {
            throw new RuntimeException('ANEX_CLIENT_UNAVAILABLE');
        }
        return new AnyTourAnexSearch($client, $this->resolver, $this->sensitive);
    }

    private function prepareSession(array &$session, int $now): void
    {
        if (!isset($session['expires_at']) || !is_int($session['expires_at'])
            || $session['expires_at'] < $now) {
            $session = [];
        }
        if (!isset($session['window_started_at']) || !is_int($session['window_started_at'])
            || $session['window_started_at'] > $now
            || $session['window_started_at'] + self::WINDOW_SECONDS <= $now) {
            $session['window_started_at'] = $now;
            $session['request_count'] = 0;
        }
        if (!isset($session['request_count']) || !is_int($session['request_count'])
            || $session['request_count'] < 0) {
            $session['request_count'] = 0;
        }
        if (!isset($session['burst_started_at']) || !is_int($session['burst_started_at'])
            || $session['burst_started_at'] > $now
            || $session['burst_started_at'] + self::BURST_WINDOW_SECONDS <= $now) {
            $session['burst_started_at'] = $now;
            $session['burst_request_count'] = 0;
        }
        if (!isset($session['burst_request_count']) || !is_int($session['burst_request_count'])
            || $session['burst_request_count'] < 0) {
            $session['burst_request_count'] = 0;
        }
        $session['expires_at'] = $now + self::SESSION_TTL;
    }

    private function consumeRequest(array &$session): void
    {
        if ($session['request_count'] >= self::WINDOW_REQUEST_LIMIT
            || $session['burst_request_count'] >= self::BURST_REQUEST_LIMIT) {
            throw new RuntimeException('ANEX_RATE_LIMIT');
        }
        ++$session['request_count'];
        ++$session['burst_request_count'];
    }

    private static function exactKeys(array $value, array $expected): bool
    {
        return count($value) === count($expected)
            && array_diff($expected, array_keys($value)) === [];
    }

    private function publicResult(array $result): array
    {
        if (isset($result['offers']) && is_array($result['offers'])) {
            foreach ($result['offers'] as &$offer) {
                if (!is_array($offer)) {
                    throw new RuntimeException('ANEX_INVALID_PUBLIC_RESULT');
                }
                unset($offer['supplier_offer_id']);
            }
            unset($offer);
        }
        return $result;
    }
}
