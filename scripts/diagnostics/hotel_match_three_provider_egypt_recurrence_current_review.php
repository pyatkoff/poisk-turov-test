<?php
declare(strict_types=1);

/**
 * MATCH #1971 immutable manifest for Egypt recurrence CURRENT review.
 * The workflow derives the already-reviewed generic recurrence algorithm from
 * hotel_match_three_provider_recurrence_current_review.php by replacing only
 * these pinned scope/operation/evidence constants and evidence contract.
 */
const HMRE_OPERATION = 'hotel-match-three-provider-egypt-recurrence-current-review-1971-20260912-v2';
const HMRE_EVIDENCE_SHA256 = '4c5dd318322963e7488278ca62cd926c80b693730ecb48e028b2c10247c9b159';
const HMRE_COUNTRY_ID = 1;
const HMRE_EXPECTED_SUMMARY = [2,56,37,19,19];
const HMRE_EXPECTED_TUPLES = 37;

if (PHP_SAPI === 'cli' && ($argv[1] ?? '') === '--self-test') {
    if (HMRE_COUNTRY_ID !== 1 || HMRE_EXPECTED_TUPLES !== 37) throw new RuntimeException('scope_test');
    if (HMRE_EXPECTED_SUMMARY !== [2,56,37,19,19]) throw new RuntimeException('summary_test');
    if (!preg_match('/^[a-f0-9]{64}$/', HMRE_EVIDENCE_SHA256)) throw new RuntimeException('sha_test');
    echo "MATCH_EGYPT_RECURRENCE_CURRENT_REVIEW_MANIFEST_OK\n";
}
