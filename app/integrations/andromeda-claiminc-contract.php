<?php
declare(strict_types=1);

/**
 * Private Andromeda selected-package identity contract.
 *
 * Supplier clarification received 2026-09-11:
 * - PRICES[].id is the claiminc passed to action=broninit;
 * - broninit returns the current composition/parameters of that selected package;
 * - the same claiminc may be retried after a network failure;
 * - claimDocument[].catalogKey is the same package identity with operator/search-form
 *   parts removed, so it is related evidence but is NOT the full claiminc and MUST
 *   NOT be substituted for it.
 *
 * This helper performs no supplier call, booking, price arithmetic or ID reconstruction.
 */
final class AnyTourAndromedaClaimincContract
{
    private const MAX_ID_BYTES = 4096;

    /** Preserve PRICES[].id byte-for-byte. Never trim/cast/decode/rebuild it. */
    public static function fromPriceRow(array $row): string
    {
        if (!array_key_exists('id', $row) || !is_string($row['id'])) {
            throw new RuntimeException('ANDROMEDA_CLAIMINC_MISSING');
        }
        self::assertOpaqueId($row['id'], 'ANDROMEDA_CLAIMINC_INVALID');
        return $row['id'];
    }

    /** Validate a retained full claiminc without changing its bytes. */
    public static function retained(string $claiminc): string
    {
        self::assertOpaqueId($claiminc, 'ANDROMEDA_CLAIMINC_INVALID');
        return $claiminc;
    }

    /**
     * catalogKey is supplier-confirmed related evidence, but its reduction algorithm is
     * not specified sufficiently to reconstruct or verify the full claiminc locally.
     */
    public static function catalogKeyEvidence(mixed $catalogKey): array
    {
        if (!is_string($catalogKey)) {
            throw new RuntimeException('ANDROMEDA_CATALOG_KEY_INVALID');
        }
        self::assertOpaqueId($catalogKey, 'ANDROMEDA_CATALOG_KEY_INVALID');
        return [
            'relation' => 'supplier_confirmed_reduced_claiminc',
            'full_claiminc_reconstructable' => false,
            'usable_as_claiminc' => false,
        ];
    }

    /**
     * Supplier explicitly allows repeating broninit with the same claiminc after a
     * network failure. This permission is deliberately narrower than arbitrary retry:
     * application/HTTP/schema/unknown supplier outcomes are not classified here.
     */
    public static function retryAllowed(string $outcome): bool
    {
        return $outcome === 'transport_failure';
    }

    /** broninit is a package refresh/details operation, not the booking method. */
    public static function broninitEffect(): array
    {
        return [
            'operation' => 'refresh_selected_package',
            'creates_booking' => false,
            'creates_application' => false,
            'operator_internal_side_effects' => 'unspecified',
        ];
    }

    private static function assertOpaqueId(string $value, string $error): void
    {
        if ($value === '' || strlen($value) > self::MAX_ID_BYTES
            || preg_match('/^[\x21-\x7e]+$/D', $value) !== 1) {
            throw new RuntimeException($error);
        }
    }
}
