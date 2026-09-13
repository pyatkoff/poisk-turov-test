<?php
declare(strict_types=1);
require_once __DIR__ . '/andromeda-client.php';
require_once __DIR__ . '/andromeda-offer-store.php';

/**
 * Private, default-off coordinator for one saved-offer package capture.
 * The caller owns the existing session/checkpoint lock for the entire operation.
 * persist(next, expected) must compare the durable record, atomically write, then
 * return its readback. mappingAllows(offer) must check the current accepted mapping.
 * No public route, booking, quote approval or default filesystem/network transport.
 */
final class AnyTourAndromedaPackageCapture
{
    private $record;
    private $persist;
    private $mappingAllows;
    private $clock;
    private $enabled;
    private const MAX_BYTES = 2097152;

    public function __construct(array &$privateRecord, callable $persist, callable $mappingAllows,
        bool $enabled = false, ?callable $clock = null)
    {
        $this->record =& $privateRecord;
        $this->persist = $persist;
        $this->mappingAllows = $mappingAllows;
        $this->enabled = $enabled;
        $this->clock = $clock ?? static fn() => time();
    }

    public function __debugInfo(): array { return ['enabled' => $this->enabled]; }
    public function __serialize(): array { throw new RuntimeException('ANDROMEDA_SERIALIZATION_DISABLED'); }

    private function resolve(AnyTourAndromedaOfferStore $store, array $context): array
    {
        $required = ['provider', 'search_ref', 'generation', 'page', 'offer_ref'];
        if (array_diff($required, array_keys($context))
            || array_diff(array_keys($context), array_merge($required, ['hotel_scope', 'operator_ref']))
            || $context['provider'] !== 'andromeda' || !is_string($context['search_ref'])
            || !is_string($context['offer_ref']) || !is_int($context['generation'])
            || $context['generation'] < 1 || !is_int($context['page']) || $context['page'] < 1) {
            throw new RuntimeException('ANDROMEDA_PACKAGE_CONTEXT_MISMATCH');
        }
        $now = ($this->clock)();
        if (!is_int($now) || $now < 1) throw new RuntimeException('ANDROMEDA_PACKAGE_CLOCK_INVALID');
        $page = $store->projection($context['search_ref'], $context['generation'], $now);
        if (($page['page'] ?? null) !== $context['page']) throw new RuntimeException('ANDROMEDA_PACKAGE_CONTEXT_MISMATCH');
        $lookup = $store->lookup($context['search_ref'], $context['generation'], $context['offer_ref'], $now);
        $offer = $lookup['offer'];
        $scope = $lookup['criteria']['HOTELS'] ?? null;
        if ((array_key_exists('hotel_scope', $context) && $context['hotel_scope'] !== $scope)
            || (array_key_exists('operator_ref', $context) && $context['operator_ref'] !== $offer['operator_ref'])) {
            throw new RuntimeException('ANDROMEDA_PACKAGE_CONTEXT_MISMATCH');
        }
        try { $mappingAllowed = ($this->mappingAllows)($offer); }
        catch (Throwable $ignored) { $mappingAllowed = false; }
        if (!is_int($offer['local_hotel_id']) || $offer['local_hotel_id'] < 1 || $mappingAllowed !== true) {
            throw new RuntimeException('ANDROMEDA_PACKAGE_MAPPING_UNAVAILABLE');
        }
        return ['context' => ['provider' => 'andromeda', 'search_ref' => $context['search_ref'],
            'generation' => $context['generation'], 'page' => $context['page'],
            'offer_ref' => $context['offer_ref'], 'hotel_scope' => $scope,
            'operator_ref' => $offer['operator_ref'], 'local_id' => $offer['local_hotel_id']],
            'criteria_sha256' => hash('sha256', json_encode($lookup['criteria'], JSON_THROW_ON_ERROR)),
            'supplier_offer_sha256' => hash('sha256', $lookup['supplier_offer_id']),
            'supplier_offer_id' => $lookup['supplier_offer_id'], 'now' => $now];
    }

    private function save(array $next, array $expected): void
    {
        $encoded = json_encode($next, JSON_THROW_ON_ERROR);
        if (strlen($encoded) > self::MAX_BYTES) throw new RuntimeException('ANDROMEDA_PACKAGE_CHECKPOINT_TOO_LARGE');
        // A failed result write must not turn the in-memory reservation into a usable capture.
        $this->record = $expected === [] ? $next : $expected;
        try {
            $readback = ($this->persist)($next, $expected);
            if (!is_array($readback) || json_encode($readback, JSON_THROW_ON_ERROR) !== $encoded) {
                throw new RuntimeException();
            }
        } catch (Throwable $ignored) { throw new RuntimeException('ANDROMEDA_PACKAGE_CHECKPOINT_FAILED'); }
        $this->record = $next;
    }

    /** Server-only result. A full claim must never be returned by a browser endpoint. */
    public function capture(AnyTourAndromedaOfferStore $store, AnyTourAndromedaClient $client, array $context): array
    {
        if (!$this->enabled) throw new RuntimeException('ANDROMEDA_PACKAGE_DISABLED');
        if ($this->record !== []) throw new RuntimeException('ANDROMEDA_PACKAGE_REPLAY_REFUSED');
        $resolved = $this->resolve($store, $context);
        $reserved = ['version' => 1, 'status' => 'reserved', 'context' => $resolved['context'],
            'created_at' => $resolved['now'], 'criteria_sha256' => $resolved['criteria_sha256'],
            'supplier_offer_sha256' => $resolved['supplier_offer_sha256']];
        $this->save($reserved, []); // Durable compare/write/readback MUST precede the request.
        $this->assertCurrent($store, $context, $reserved);
        try { $raw = $client->package($resolved['supplier_offer_id']); }
        catch (Throwable $ignored) {
            $unknown = $reserved;
            $unknown['status'] = 'unknown';
            $this->save($unknown, $reserved);
            throw new RuntimeException('ANDROMEDA_PACKAGE_OUTCOME_UNKNOWN');
        }
        $result = $reserved;
        $result['status'] = 'captured';
        $result['package_sha256'] = hash('sha256', json_encode($raw, JSON_THROW_ON_ERROR));
        $result['private_package'] = $raw;
        $result['identity_verified'] = false;
        $result['quote_verified'] = false;
        $result['selection_enabled'] = false;
        try { $this->assertCurrent($store, $context, $reserved); }
        catch (Throwable $ignored) {
            $result['status'] = 'stale';
            $this->save($result, $reserved); // Preserve the late response as private evidence.
            throw new RuntimeException('ANDROMEDA_PACKAGE_CONTEXT_STALE');
        }
        $this->save($result, $reserved);
        return $result;
    }

    private function assertCurrent(AnyTourAndromedaOfferStore $store, array $context, array $record): void
    {
        $resolved = $this->resolve($store, $context);
        foreach (['context', 'criteria_sha256', 'supplier_offer_sha256'] as $field) {
            if (($record[$field] ?? null) !== $resolved[$field]) throw new RuntimeException('ANDROMEDA_PACKAGE_CONTEXT_STALE');
        }
    }

    /** Read an already captured private package without any supplier operation. */
    public function read(AnyTourAndromedaOfferStore $store, array $context): array
    {
        if (!$this->enabled) throw new RuntimeException('ANDROMEDA_PACKAGE_DISABLED');
        if (($this->record['version'] ?? null) !== 1 || ($this->record['status'] ?? null) !== 'captured'
            || !is_array($this->record['private_package'] ?? null)
            || ($this->record['identity_verified'] ?? null) !== false
            || ($this->record['quote_verified'] ?? null) !== false
            || ($this->record['selection_enabled'] ?? null) !== false
            || ($this->record['package_sha256'] ?? null) !== hash('sha256', json_encode($this->record['private_package'], JSON_THROW_ON_ERROR))) {
            throw new RuntimeException('ANDROMEDA_PACKAGE_NOT_CAPTURED');
        }
        $this->assertCurrent($store, $context, $this->record);
        return $this->record;
    }
}
