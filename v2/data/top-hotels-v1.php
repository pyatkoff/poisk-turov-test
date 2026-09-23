<?php
/**
 * Backward-compatible TOP500 collector input.
 *
 * Canonical ordered cohort now lives in hotel-popularity-v1.php so search,
 * collectors and legacy sales-leader enrichment read one source of truth.
 */
declare(strict_types=1);
require_once __DIR__ . '/hotel-popularity-v1.php';

function v2_priority_hotel_ids(): array
{
    return v2_hotel_popularity_legacy_ids();
}
