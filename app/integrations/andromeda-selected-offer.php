<?php
declare(strict_types=1);

require_once __DIR__ . '/andromeda-offer-store.php';

/**
 * Resolves one retained offer against the current private search snapshot.
 * supplier_offer_id is returned only from resolve() for server-side consumers;
 * publicSelection() deliberately exposes neither it nor the supplier hotel ID.
 */
final class AnyTourAndromedaSelectedOffer
{
    public static function resolve(AnyTourAndromedaOfferStore $store, array $context,
        callable $mappingAllows, int $now): array
    {
        $required = ['provider', 'search_ref', 'generation', 'page', 'offer_ref'];
        if (array_diff($required, array_keys($context))
            || array_diff(array_keys($context), array_merge($required, ['hotel_scope', 'operator_ref']))
            || $context['provider'] !== 'andromeda' || !is_string($context['search_ref'])
            || !is_string($context['offer_ref']) || !is_int($context['generation'])
            || $context['generation'] < 1 || !is_int($context['page']) || $context['page'] < 1
            || $now < 1) {
            throw new RuntimeException('ANDROMEDA_SELECTION_CONTEXT_MISMATCH');
        }
        $page = $store->projection($context['search_ref'], $context['generation'], $now);
        if (($page['page'] ?? null) !== $context['page']) {
            throw new RuntimeException('ANDROMEDA_SELECTION_CONTEXT_MISMATCH');
        }
        $lookup = $store->lookup($context['search_ref'], $context['generation'], $context['offer_ref'], $now);
        $offer = $lookup['offer'];
        $scope = $lookup['criteria']['HOTELS'] ?? null;
        if ((array_key_exists('hotel_scope', $context) && $context['hotel_scope'] !== $scope)
            || (array_key_exists('operator_ref', $context) && $context['operator_ref'] !== $offer['operator_ref'])) {
            throw new RuntimeException('ANDROMEDA_SELECTION_CONTEXT_MISMATCH');
        }
        try { $allowed = $mappingAllows($offer); }
        catch (Throwable $ignored) { $allowed = false; }
        if (!is_int($offer['local_hotel_id']) || $offer['local_hotel_id'] < 1 || $allowed !== true) {
            throw new RuntimeException('ANDROMEDA_SELECTION_MAPPING_UNAVAILABLE');
        }
        return [
            'context' => [
                'provider' => 'andromeda', 'search_ref' => $context['search_ref'],
                'generation' => $context['generation'], 'page' => $context['page'],
                'offer_ref' => $context['offer_ref'], 'hotel_scope' => $scope,
                'operator_ref' => $offer['operator_ref'], 'local_id' => $offer['local_hotel_id'],
            ],
            'offer' => $offer,
            'criteria_sha256' => hash('sha256', json_encode($lookup['criteria'], JSON_THROW_ON_ERROR)),
            'supplier_offer_sha256' => hash('sha256', $lookup['supplier_offer_id']),
            'supplier_offer_id' => $lookup['supplier_offer_id'],
        ];
    }

    /** Browser-safe state for the existing selected-tour owner; price is not a quote. */
    public static function publicSelection(AnyTourAndromedaOfferStore $store, array $context,
        callable $mappingAllows, int $now): array
    {
        $resolved = self::resolve($store, $context, $mappingAllows, $now);
        $offer = $resolved['offer'];
        return $resolved['context'] + [
            'tour' => [
                'hotel' => $offer['hotel'], 'operator' => $offer['operator'],
                'check_in' => $offer['check_in'], 'nights' => $offer['nights'],
                'adults' => $offer['adults'], 'children' => $offer['children'],
                'room' => $offer['room'], 'placement' => $offer['placement'],
                'meal' => $offer['meal'], 'price' => $offer['price'],
            ],
            'quote_status' => 'unverified',
            'package_status' => 'not_loaded',
        ];
    }
}
