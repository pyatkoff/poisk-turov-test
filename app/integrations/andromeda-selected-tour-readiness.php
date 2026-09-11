<?php
declare(strict_types=1);
require_once __DIR__ . '/three-provider-flight-details.php';

/**
 * Browser-safe launch readiness for one already selected/current Andromeda package.
 * Pure decision only: no supplier call, price arithmetic, booking or persistence.
 */
final class AnyTourAndromedaSelectedTourReadiness
{
    public static function fromPackage(array $package): array
    {
        $flight = AnyTourThreeProviderFlightDetails::forAndromedaPackage($package);
        $out = [
            'schema_version' => 1,
            'provider' => 'andromeda',
            'state' => 'package_not_ready',
            'next_supplier_step' => null,
            'package_price' => null,
            'package_price_status' => 'unknown',
            'flight_details' => $flight,
            'quote_state' => 'unverified',
            'final_price_verified' => false,
            'selection_enabled' => false,
            'booking_enabled' => false,
        ];

        $bound = ($package['status'] ?? null) === 'package_bound_unquoted'
            && ($package['identity_verified'] ?? null) === true
            && ($package['package_binding_verified'] ?? null) === true;
        if (!$bound) return $out;

        $price = $package['price'] ?? null;
        if (is_array($price)
            && array_keys($price) === ['amount', 'currency', 'status']
            && is_string($price['amount'])
            && preg_match('/^(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?$/D', $price['amount']) === 1
            && preg_match('/[1-9]/', $price['amount']) === 1
            && is_string($price['currency'])
            && preg_match('/^[A-Z0-9_]{2,8}$/D', $price['currency']) === 1
            && $price['status'] === 'package_unverified') {
            $out['package_price'] = $price;
            $out['package_price_status'] = 'package_unverified';
        }

        if ($flight['details_state'] === 'external_lookup_required') {
            $out['state'] = 'needs_flights';
            $out['next_supplier_step'] = 'get_flights';
            return $out;
        }
        if ($flight['details_state'] === 'external_lookup_not_required') {
            $out['state'] = 'needs_calc';
            $out['next_supplier_step'] = 'calc';
            return $out;
        }

        $out['state'] = 'transport_requirement_unknown';
        return $out;
    }
}
