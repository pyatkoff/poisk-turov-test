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
        if (!is_string($action) || !in_array($action, ['search', 'expand', 'flights'], true)) {
            throw new InvalidArgumentException('ANEX_INVALID_ACTION');
        }

        if ($action === 'search') {
            if (!self::exactKeys($request, ['action', 'criteria']) || !is_array($request['criteria'])) {
                throw new InvalidArgumentException('ANEX_INVALID_REQUEST');
            }
            // A failed replacement search invalidates earlier supplier references.
            unset($session['search']);
            $search = $this->newSearch();
            $result = $search->search($request['criteria']);
            $session['search'] = $search->snapshot();
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
            }
        }
        $session['expires_at'] = $now + self::SESSION_TTL;
        return $this->publicResult($result);
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
