<?php
declare(strict_types=1);

/**
 * Search-time flight-detail capability for the canonical offer boundary.
 *
 * A search result never proves that itinerary, baggage or price-impact details
 * have been loaded. Provider detail methods remain explicit and opt-in.
 */
final class AnyTourThreeProviderFlightDetails
{
    private const METHODS = [
        'tourvisor' => ['status' => 'verified', 'method' => 'tourvisor_tours_flights'],
        'anex' => ['status' => 'verified', 'method' => 'freight_monitor_freights_by_packet'],
        'andromeda' => ['status' => 'unknown', 'method' => 'andromeda_get_flights'],
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
            'segments' => [],
            'baggage_state' => 'unknown',
            'price_effect' => 'unknown',
            'automatic_fetch_allowed' => false,
            'final_price_verified' => false,
        ];
    }
}
