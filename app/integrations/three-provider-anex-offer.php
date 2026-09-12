<?php
declare(strict_types=1);

require_once __DIR__ . '/anex-normalizer.php';
require_once __DIR__ . '/three-provider-money-facts.php';
require_once __DIR__ . '/three-provider-availability.php';
require_once __DIR__ . '/three-provider-flight-details.php';
require_once __DIR__ . '/three-provider-operator.php';
require_once __DIR__ . '/three-provider-offer-contract.php';

/**
 * Pure concrete-offer handoff from anytour_anex_normalize_prices().
 *
 * The caller supplies the retained page's original reference/time and a CURRENT
 * identity read for the exact namespace/external ID. This mapper does not resolve
 * identities or treat the page's historical local_id as current authority.
 * Native search money is preserved: converted_price is not silently substituted.
 * Already-normalized supplier-verified additional-price facts may be supplied by
 * an upstream evidence consumer; the existing money contract validates them and
 * keeps them separate from search price/fuel without applying arithmetic.
 * Group minima need a separate grouped contract, not a fabricated concrete offer.
 * No runtime endpoint is wired here; downstream selection still requires its own
 * current-context check, quote and final-price evidence.
 */
final class AnyTourThreeProviderAnexOffer
{
    public static function fromPage(
        array $page,
        int $offerIndex,
        array $currentIdentity,
        string $searchRef,
        string $observedAt,
        array $additionalPricesReported = []
    ): array {
        if (($page['schema_version'] ?? null) !== 1 || ($page['provider'] ?? null) !== 'anex'
            || ($page['supplier_namespace'] ?? null) !== 'anex_online'
            || !is_array($page['search'] ?? null) || !is_array($page['offers'] ?? null)
            || count($page['offers']) > 300
            || ($page['offers'] !== [] && array_keys($page['offers']) !== range(0, count($page['offers']) - 1))
            || $offerIndex < 0 || !is_array($page['offers'][$offerIndex] ?? null)) {
            throw new InvalidArgumentException('THREE_PROVIDER_ANEX_PAGE');
        }
        $search = anytour_anex_normalizer_context($page['search']);
        $offer = $page['offers'][$offerIndex];
        if (($offer['provider'] ?? null) !== 'anex' || ($offer['supplier_namespace'] ?? null) !== 'anex_online'
            || ($offer['final_price_verified'] ?? null) !== false
            || !in_array($offer['kind'] ?? null, ['concrete', 'group_minimum'], true)) {
            throw new InvalidArgumentException('THREE_PROVIDER_ANEX_OFFER');
        }
        if ($offer['kind'] === 'group_minimum') {
            throw new DomainException('THREE_PROVIDER_ANEX_CONCRETE_REQUIRED');
        }
        $hotel = $offer['hotel'] ?? null;
        $external = is_array($hotel) ? ($hotel['external_id'] ?? null) : null;
        if (!is_string($external) || anytour_anex_normalizer_id($external) !== $external
            || count($currentIdentity) !== 3
            || array_diff(['supplier_namespace', 'external_id', 'local_id'], array_keys($currentIdentity)) !== []
            || $currentIdentity['supplier_namespace'] !== 'anex_online'
            || $currentIdentity['external_id'] !== $external) {
            throw new InvalidArgumentException('THREE_PROVIDER_ANEX_IDENTITY');
        }
        if (!is_string($offer['offer_key'] ?? null)
            || !preg_match('/\Aanex_online:[a-f0-9]{64}\z/D', $offer['offer_key'])) {
            throw new InvalidArgumentException('THREE_PROVIDER_ANEX_REFERENCE');
        }
        if (($offer['adults'] ?? null) !== $search['adults'] || ($offer['children'] ?? null) !== $search['children']
            || !is_int($offer['nights'] ?? null) || !is_string($offer['checkin'] ?? null)
            || $offer['nights'] < $search['nights_from'] || $offer['nights'] > $search['nights_till']
            || $offer['checkin'] < $search['checkin_begin'] || $offer['checkin'] > $search['checkin_end']
            || !array_key_exists('infants', $offer) || !in_array($offer['infants'], [null, 0], true)) {
            throw new InvalidArgumentException('THREE_PROVIDER_ANEX_SEARCH_CONTEXT');
        }
        if (!is_array($offer['price'] ?? null) || !is_array($offer['availability'] ?? null)
            || !array_key_exists('hotel_place', $offer)) {
            throw new InvalidArgumentException('THREE_PROVIDER_ANEX_OFFER');
        }
        $meal = AnyTourThreeProviderMealFamily::normalize($offer['meal'] ?? null);
        $labels = AnyTourThreeProviderRoomPlacement::normalize('anex', $offer['room'] ?? null, $offer['hotel_place']);

        return AnyTourThreeProviderOfferContract::fromSearch([
            'provider' => 'anex',
            // The source normalizer has no operator label; provider is not evidence.
            'operator' => null,
            'local_hotel_id' => $currentIdentity['local_id'],
            'provider_hotel_ref' => 'anex_online:' . $external,
            'search_ref' => $searchRef,
            'offer_ref' => $offer['offer_key'],
            'checkin' => $offer['checkin'],
            'nights' => $offer['nights'],
            'adults' => $offer['adults'],
            'children' => $offer['children'],
            'child_ages' => $search['child_ages'],
            'meal' => ['raw' => $meal['raw'], 'family' => $meal['family'], 'qualifiers' => $meal['qualifiers']],
            'room' => ['raw' => $labels['room']['raw'], 'normalized' => $labels['room']['normalized']],
            'placement' => $labels['placement'] === null ? null
                : ['raw' => $labels['placement']['raw'], 'normalized' => $labels['placement']['normalized']],
            'availability' => $offer['availability'],
            'search_price' => ['amount' => $offer['price']['amount'] ?? null,
                'currency' => $offer['price']['currency'] ?? null, 'source' => 'anex_search'],
            'fuel_charge_reported' => null,
            'additional_prices_reported' => $additionalPricesReported,
            'observed_at' => $observedAt,
        ]);
    }
}
