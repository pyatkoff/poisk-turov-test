<?php
declare(strict_types=1);

/**
 * Provider-neutral gate between a CURRENT stored-offer handle and the existing
 * Andromeda package/quote owner. Pure: no supplier, DB or filesystem I/O.
 */
function anytour_stored_quote_gate(array $stored, ?array $quoteAttempt, int $now): array
{
    if (($stored['source'] ?? null) !== 'andromeda-stored-offer-v1'
        || ($stored['provider'] ?? null) !== 'andromeda'
        || !is_string($stored['handle'] ?? null)
        || preg_match('/\Astored_[a-f0-9]{64}\z/D', $stored['handle']) !== 1
        || !is_int($stored['anytourHotelId'] ?? null) || $stored['anytourHotelId'] < 1
        || !is_string($stored['scopeDigest'] ?? null)
        || preg_match('/\A[a-f0-9]{64}\z/D', $stored['scopeDigest']) !== 1
        || !is_string($stored['expiresAt'] ?? null)
        || $now < 1) {
        throw new InvalidArgumentException('STORED_QUOTE_CONTEXT_INVALID');
    }

    $nativeExpires = strtotime($stored['expiresAt']);
    if ($nativeExpires === false) throw new InvalidArgumentException('STORED_QUOTE_CONTEXT_INVALID');

    $quote = $stored['quote'] ?? null;
    if (is_array($quote)
        && ($quote['state'] ?? null) === 'verified'
        && is_array($quote['finalPrice'] ?? null)
        && ($quote['finalPrice']['currency'] ?? null) === 'RUB'
        && is_string($quote['finalPrice']['amount'] ?? null)
        && preg_match('/\A(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?\z/D', $quote['finalPrice']['amount']) === 1
        && preg_match('/[1-9]/', $quote['finalPrice']['amount']) === 1) {
        return [
            'state' => 'final_price_ready',
            'finalPriceReady' => true,
            'finalPrice' => $quote['finalPrice'],
            'supplierCallAllowed' => false,
            'sameCriteria' => true,
        ];
    }

    if ($nativeExpires <= $now) {
        return [
            'state' => 'refresh_required',
            'finalPriceReady' => false,
            'finalPrice' => null,
            'supplierCallAllowed' => false,
            'sameCriteria' => true,
        ];
    }

    if ($quoteAttempt !== null) {
        $status = $quoteAttempt['status'] ?? null;
        if ($status === 'completed' && is_array($quoteAttempt['result'] ?? null)) {
            $result = $quoteAttempt['result'];
            $ready = ($result['state'] ?? null) === 'quote_verified'
                && ($result['quote_state'] ?? null) === 'verified'
                && ($result['final_price_verified'] ?? null) === true
                && ($result['flight_selection_required'] ?? null) === false
                && is_array($result['final_price'] ?? null)
                && ($result['final_price']['currency'] ?? null) === 'RUB';
            return [
                'state' => $ready ? 'final_price_ready' : 'confirmation_required',
                'finalPriceReady' => $ready,
                'finalPrice' => $ready ? $result['final_price'] : null,
                'supplierCallAllowed' => false,
                'sameCriteria' => true,
            ];
        }
        if (in_array($status, ['reserved','unknown'], true)) {
            return [
                'state' => 'attempt_sealed',
                'finalPriceReady' => false,
                'finalPrice' => null,
                'supplierCallAllowed' => false,
                'sameCriteria' => true,
            ];
        }
        throw new RuntimeException('STORED_QUOTE_ATTEMPT_INVALID');
    }

    return [
        'state' => 'actualization_required',
        'finalPriceReady' => false,
        'finalPrice' => null,
        'supplierCallAllowed' => true,
        'sameCriteria' => true,
    ];
}
