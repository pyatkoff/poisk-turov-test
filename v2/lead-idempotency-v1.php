<?php
/** Pure V2 lead idempotency fingerprint helper. */
function v2_lead_idempotency_key(array $lead): string
{
    $parts = [
        (string)($lead['phone'] ?? ''),
        (string)($lead['tourId'] ?? ''),
        (string)($lead['searchId'] ?? ''),
        (string)($lead['flight'] ?? ''),
        (string)($lead['flightPrice'] ?? ''),
        (string)($lead['flightFuel'] ?? ''),
        (string)($lead['comment'] ?? ''),
    ];
    return hash('sha256', implode('|', $parts));
}
