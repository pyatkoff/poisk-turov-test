<?php
declare(strict_types=1);

/**
 * Flight-detail capability for the canonical three-provider offer boundary.
 * Search results alone never prove itinerary, baggage or final-price details.
 */
final class AnyTourThreeProviderFlightDetails
{
    private const METHODS = [
        'tourvisor' => ['status' => 'verified', 'method' => 'tourvisor_tours_flights'],
        'anex' => ['status' => 'verified', 'method' => 'freight_monitor_freights_by_packet'],
        // Andromeda flight lookup is package-conditional: broninit freightExternal
        // decides whether the documented get_flights step is required.
        'andromeda' => ['status' => 'package_conditional', 'method' => 'andromeda_get_flights'],
    ];

    public static function forSearch(string $provider): array
    {
        if (!isset(self::METHODS[$provider])) {
            throw new InvalidArgumentException('THREE_PROVIDER_FLIGHT_PROVIDER');
        }

        return [
            'schema_version' => 1,
            'provider' => $provider,
            'capability_status' => self::METHODS[$provider]['status'],
            'source_method' => self::METHODS[$provider]['method'],
            'details_state' => 'not_loaded',
            'external_lookup_required' => null,
            'segments' => [],
            'baggage_state' => 'unknown',
            'price_effect' => 'unknown',
            'automatic_fetch_allowed' => false,
            'final_price_verified' => false,
        ];
    }

    /**
     * Decide only whether a selected, current Andromeda package requires the
     * documented external flight lookup. No get_flights/calc/network action occurs.
     */
    public static function forAndromedaPackage(array $package): array
    {
        $out = self::forSearch('andromeda');
        $bound = ($package['status'] ?? null) === 'package_bound_unquoted'
            && ($package['identity_verified'] ?? null) === true
            && ($package['package_binding_verified'] ?? null) === true;
        if (!$bound) {
            $out['details_state'] = 'package_not_bound';
            return $out;
        }

        $required = $package['requires_external_flights'] ?? null;
        if (!is_bool($required)) {
            $out['details_state'] = 'requirement_unknown';
            return $out;
        }

        $out['external_lookup_required'] = $required;
        $out['details_state'] = $required
            ? 'external_lookup_required'
            : 'external_lookup_not_required';
        return $out;
    }
}
