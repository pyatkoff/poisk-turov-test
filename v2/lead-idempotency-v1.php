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
    if(in_array($lead['provider']??null,['andromeda','anex'],true)){
        // Include party and exact quote choice without changing the legacy TV key.
        $parts[]=$lead['provider'];$parts[]=$lead['providerOfferRef'];$parts[]=$lead['providerChoiceRef']??'';
        $parts[]=(string)($lead['adults']??'');$parts[]=json_encode($lead['childAges']??[]);
        return hash('sha256',json_encode($parts,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
    }
    return hash('sha256', implode('|', $parts));
}
