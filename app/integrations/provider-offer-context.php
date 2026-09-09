<?php
declare(strict_types=1);

/**
 * Internal application identity, not a supplier wire format or booking authority.
 * No I/O, provider activation, mapping lookup or lifecycle owner lives here.
 * search_ref/offer_ref must be application-owned opaque references; raw supplier
 * tokens and credentials belong in the provider's private session only.
 */
final class AnyTourProviderOfferContext
{
    private $context;

    private function __construct(array $context)
    {
        $this->context = $context;
    }

    /** The caller supplies local_hotel_id only from an accepted mapping. */
    public static function fromArray(array $value): self
    {
        self::keys($value, [
            'provider', 'supplier_namespace', 'generation', 'search_ref',
            'offer_ref', 'external_hotel_id', 'local_hotel_id', 'operator_ref',
        ]);
        $search = self::searchContext([
            'provider' => $value['provider'],
            'supplier_namespace' => $value['supplier_namespace'],
            'generation' => $value['generation'],
            'search_ref' => $value['search_ref'],
        ]);
        if (!self::reference($value['offer_ref'])
            || !self::reference($value['external_hotel_id'])
            || ($value['operator_ref'] !== null && !self::reference($value['operator_ref']))
            || ($value['local_hotel_id'] !== null
                && (!is_int($value['local_hotel_id']) || $value['local_hotel_id'] < 1))) {
            throw new InvalidArgumentException('invalid_provider_offer_context');
        }
        return new self($search + [
            'offer_ref' => $value['offer_ref'],
            'external_hotel_id' => $value['external_hotel_id'],
            'local_hotel_id' => $value['local_hotel_id'],
            // An ANEX tour sold through Tourvisor is still provider=tourvisor.
            'operator_ref' => $value['operator_ref'],
        ]);
    }

    /** A copy: callers cannot mutate retained context through this projection. */
    public function toArray(): array
    {
        return $this->context;
    }

    /** Namespaced identity; never infer equality from a numeric hotel ID. */
    public function hotelKey(): string
    {
        return self::key('hotel', [
            $this->context['provider'], $this->context['supplier_namespace'],
            $this->context['external_hotel_id'],
        ]);
    }

    /** Search-scoped key; this is not a signed capability or public selection token. */
    public function offerKey(): string
    {
        return self::key('offer', [
            $this->context['provider'], $this->context['supplier_namespace'],
            $this->context['generation'], $this->context['search_ref'],
            $this->context['external_hotel_id'], $this->context['offer_ref'],
        ]);
    }

    /**
     * Compare against a fresh search context from the authoritative controller.
     * true alone does not authorize selection: expiry/cancellation, current
     * criteria, accepted mapping and offer availability remain caller checks.
     */
    public function matchesSearch(array $current): bool
    {
        try {
            $search = self::searchContext($current);
        } catch (InvalidArgumentException $ignored) {
            return false;
        }
        foreach ($search as $key => $value) {
            if ($this->context[$key] !== $value) {
                return false;
            }
        }
        return true;
    }

    private static function searchContext(array $value): array
    {
        self::keys($value, ['provider', 'supplier_namespace', 'generation', 'search_ref']);
        if (!self::namespaceValue($value['provider'])
            || !self::namespaceValue($value['supplier_namespace'])
            || !is_int($value['generation']) || $value['generation'] < 1
            || !self::reference($value['search_ref'])) {
            throw new InvalidArgumentException('invalid_provider_offer_context');
        }
        return [
            'provider' => $value['provider'],
            'supplier_namespace' => $value['supplier_namespace'],
            'generation' => $value['generation'],
            'search_ref' => $value['search_ref'],
        ];
    }

    private static function keys(array $value, array $expected): void
    {
        if (count($value) !== count($expected) || array_diff($expected, array_keys($value)) !== []) {
            throw new InvalidArgumentException('invalid_provider_offer_context');
        }
    }

    private static function namespaceValue($value): bool
    {
        return is_string($value) && preg_match('/\A[a-z][a-z0-9_]{1,47}\z/D', $value) === 1;
    }

    private static function reference($value): bool
    {
        // Application transport bound, NOT a supplier ID/limit assumption.
        // IDs are strings: no numeric coercion, leading-zero or case loss.
        return is_string($value) && strlen($value) >= 1 && strlen($value) <= 2048
            && trim($value) === $value && !preg_match('/[\x00-\x20\x7f]/', $value)
            && strpos($value, '://') === false && preg_match('//u', $value) === 1;
    }

    private static function key(string $kind, array $tuple): string
    {
        // Structured encoding prevents delimiter collisions in opaque references.
        return $kind . ':' . hash('sha256', json_encode($tuple, JSON_THROW_ON_ERROR));
    }
}
