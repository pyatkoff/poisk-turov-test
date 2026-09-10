<?php
declare(strict_types=1);

/**
 * PRIVATE diagnostic of an existing PackageCapture record; no endpoint/CLI launcher.
 * Call after the existing PackageCapture::read(store, context), not instead of it.
 * This pure function cannot establish current context, identity, quote or permission.
 * It returns fixed classifications/counts, never claim values, money, IDs or PII.
 * Schema: https://dokuwiki.samo.ru/doku.php?id=andromeda:claim_struct
 * Method: https://dokuwiki.samo.ru/doku.php?id=andromeda:bron
 */
function anytour_andromeda_package_evidence(array $record): array
{
    $result = ['status' => 'not_captured', 'identity_verified' => false,
        'quote_verified' => false, 'selection_enabled' => false,
        'current_context_verified' => false];
    if (($record['version'] ?? null) !== 1 || ($record['status'] ?? null) !== 'captured') return $result;
    $result['status'] = 'invalid_checkpoint';
    foreach (['identity_verified', 'quote_verified', 'selection_enabled'] as $flag) {
        if (($record[$flag] ?? null) !== false) return $result;
    }
    $raw = $record['private_package'] ?? null;
    $digest = $record['package_sha256'] ?? null;
    if (!is_array($raw) || !is_string($digest) || !preg_match('/^[a-f0-9]{64}$/D', $digest)) return $result;
    // Reject non-JSON PHP values before serialization, including objects with hooks.
    $plain = static function ($value, int $depth = 0) use (&$plain): bool {
        if ($depth > 32) return false;
        if (is_array($value)) {
            foreach ($value as $child) if (!$plain($child, $depth + 1)) return false;
            return true;
        }
        return $value === null || is_scalar($value);
    };
    if (!$plain($raw)) return $result;
    try { $encoded = json_encode($raw, JSON_THROW_ON_ERROR); }
    catch (Throwable $ignored) { return $result; }
    if (strlen($encoded) > 2097152 || !hash_equals($digest, hash('sha256', $encoded))) return $result;
    $result['status'] = 'inspected_not_verified';
    $documents = $raw['claimDocument'] ?? null;
    $result['document'] = 'malformed';
    if (!is_array($documents) || !array_is_list($documents)) return $result;
    if (count($documents) !== 1) {
        $result['document'] = $documents === [] ? 'empty' : 'multiple';
        return $result;
    }
    $document = $documents[0];
    if (!is_array($document) || $document === [] || array_is_list($document)) return $result;
    $result['document'] = 'single';
    $key = $document['catalogKey'] ?? null;
    $selectedDigest = $record['supplier_offer_sha256'] ?? null;
    $result['selected_id_relation'] = 'unknown';
    if (is_string($key) && $key !== '' && is_string($selectedDigest)
        && preg_match('/^[a-f0-9]{64}$/D', $selectedDigest)) {
        // Same bytes is an observation, NOT proof that two API identifier domains agree.
        $result['selected_id_relation'] = hash_equals($selectedDigest, hash('sha256', $key)) ? 'same_bytes' : 'different_bytes';
    }
    $integer = static fn($value): bool => (is_int($value) || is_string($value))
        && preg_match('/^(?:0|[1-9][0-9]{0,8})$/D', (string) $value) === 1;
    $external = $document['freightExternal'] ?? null;
    $result['external_flights'] = !$integer($external) ? 'unknown' : ((int) $external > 0 ? 'required' : 'not_indicated');
    $condition = $document['condition'] ?? null;
    $result['booking_state'] = $condition === 'ccOffer' ? 'temporary' : (in_array($condition,
        ['ccPrebooking', 'ccAwaiting', 'ccBooked', 'ccRefusal'], true) ? 'not_temporary' : 'unknown');

    // Inspect only selected claimDocument orders. variants never become included orders.
    $rows = static function (array $parent, string $plural, string $singular): ?array {
        if (!array_key_exists($plural, $parent)) return null;
        $groups = $parent[$plural];
        if (!is_array($groups) || !array_is_list($groups) || count($groups) > 300) return null;
        $items = [];
        foreach ($groups as $group) {
            if (!is_array($group) || !isset($group[$singular]) || !is_array($group[$singular])
                || !array_is_list($group[$singular])) return null;
            foreach ($group[$singular] as $item) {
                if (!is_array($item) || $item === [] || array_is_list($item) || count($items) >= 300) return null;
                $items[] = $item;
            }
        }
        return $items;
    };
    $summary = static fn(?array $items): array => ['shape' => $items === null ? 'unknown_or_malformed' : 'list',
        'count' => $items === null ? null : count($items)];
    foreach (['hotels' => 'hotel', 'transports' => 'transport', 'services' => 'service'] as $plural => $singular) {
        $result['selected_orders'][$plural] = $summary($rows($document, $plural, $singular));
    }
    $buyer = $rows($document, 'buyerMoneys', 'buyerClaimMoney');
    $result['buyer_price'] = $summary($buyer);
    $result['buyer_price']['single_amount_shape_valid'] = false;
    if ($buyer !== null && count($buyer) === 1) {
        $amount = $buyer[0]['net'] ?? null;
        $currency = $buyer[0]['currency'] ?? null;
        $currencyKey = $buyer[0]['currencyKey'] ?? null;
        // Shape check only. No float math, totals, currency conversion or agency fallback.
        $result['buyer_price']['single_amount_shape_valid'] = (is_string($amount) || is_int($amount))
            && preg_match('/^(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?$/D', (string) $amount) === 1
            && preg_match('/[1-9]/', (string) $amount) === 1
            && is_string($currency) && preg_match('/^[A-Z_]{2,8}$/D', $currency) === 1
            && $integer($currencyKey) && (int) $currencyKey > 0;
    }
    $result['agency_money'] = $summary($rows($document, 'moneys', 'money'));
    $result['required_fields'] = ['shape' => 'unknown_or_malformed', 'traveller' => null, 'buyer' => null];
    $fields = $raw['checkFields'] ?? null;
    if (is_array($fields) && array_is_list($fields) && count($fields) <= 100) {
        $counts = ['traveller' => 0, 'buyer' => 0]; $valid = true;
        foreach ($fields as $group) {
            if (!is_array($group) || $group === [] || array_is_list($group)) { $valid = false; break; }
            foreach (['people' => 'traveller', 'buyer' => 'buyer'] as $name => $target) {
                if (!array_key_exists($name, $group)) continue;
                if (!is_array($group[$name]) || !array_is_list($group[$name]) || count($group[$name]) > 100) { $valid = false; break 2; }
                foreach ($group[$name] as $definitions) {
                    if (!is_array($definitions) || $definitions === [] || array_is_list($definitions) || count($definitions) > 100) { $valid = false; break 3; }
                    foreach ($definitions as $rules) {
                        if (!is_array($rules) || !array_is_list($rules) || count($rules) !== 1
                            || !is_array($rules[0]) || !in_array($rules[0]['required'] ?? null, [true, false, 'true', 'false'], true)) { $valid = false; break 4; }
                        if (in_array($rules[0]['required'], [true, 'true'], true)) ++$counts[$target];
                    }
                }
            }
        }
        if ($valid) $result['required_fields'] = ['shape' => 'list'] + $counts;
    }
    // Never copy supplier free text (it may contain IDs, credentials or personal data).
    $result['message_fields_present'] = false;
    foreach ([$raw, $document] as $part) foreach (['error', 'errors', 'warning', 'warnings', 'note'] as $name) {
        if (array_key_exists($name, $part) && !in_array($part[$name], [null, [], '', false], true)) $result['message_fields_present'] = true;
    }
    return $result;
}
