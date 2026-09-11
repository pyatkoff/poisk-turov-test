<?php
declare(strict_types=1);

require_once __DIR__.'/../app/integrations/andromeda-quote-attempt-state.php';

$checks = 0;
$context = hash('sha256', 'context-a');
$operation = hash('sha256', 'andromeda-selected-quote-v1');
$reserved = AnyTourAndromedaQuoteAttemptState::reserve($context, $operation);
if (($reserved['status'] ?? null) !== 'reserved' || !array_key_exists('result', $reserved) || $reserved['result'] !== null) throw new RuntimeException('reserve');
++$checks;

try {
    AnyTourAndromedaQuoteAttemptState::replay($reserved, $context, $operation);
    throw new RuntimeException('reserved replay accepted');
} catch (RuntimeException $e) {
    if ($e->getMessage() !== 'ANDROMEDA_QUOTE_REPLAY_REFUSED') throw $e;
}
++$checks;

$unknown = AnyTourAndromedaQuoteAttemptState::unknown($reserved);
try {
    AnyTourAndromedaQuoteAttemptState::replay($unknown, $context, $operation);
    throw new RuntimeException('unknown replay accepted');
} catch (RuntimeException $e) {
    if ($e->getMessage() !== 'ANDROMEDA_QUOTE_REPLAY_REFUSED') throw $e;
}
++$checks;

$result = [
    'schema_version' => 1,
    'provider' => 'andromeda',
    'selection_enabled' => true,
    'booking_enabled' => false,
    'local_id' => 6319,
    'state' => 'quote_verified',
    'quote_state' => 'verified',
    'search_price' => ['amount' => '119114', 'currency' => 'RUB'],
    'package_price' => ['amount' => '124864', 'currency' => 'RUB'],
    'final_price' => ['amount' => '135643', 'currency' => 'RUB'],
    'final_price_verified' => true,
    'flight_selection_required' => false,
    'flights' => [],
];
$completed = AnyTourAndromedaQuoteAttemptState::completed($reserved, $result);
$replayed = AnyTourAndromedaQuoteAttemptState::replay($completed, $context, $operation);
if ($replayed !== $result) throw new RuntimeException('completed replay');
++$checks;

foreach ([hash('sha256', 'context-b'), $context] as $i => $candidate) {
    $candidateOperation = $i === 0 ? $operation : hash('sha256', 'operation-b');
    try {
        AnyTourAndromedaQuoteAttemptState::replay($completed, $candidate, $candidateOperation);
        throw new RuntimeException('provenance mismatch accepted');
    } catch (RuntimeException $e) {
        if ($e->getMessage() !== 'ANDROMEDA_QUOTE_REPLAY_REFUSED') throw $e;
    }
    ++$checks;
}

$choice = $result;
$choice['state'] = 'flight_selection_required';
$choice['quote_state'] = 'unverified';
$choice['final_price'] = null;
$choice['final_price_verified'] = false;
$choice['flight_selection_required'] = true;
$choiceState = AnyTourAndromedaQuoteAttemptState::completed($reserved, $choice);
if (AnyTourAndromedaQuoteAttemptState::replay($choiceState, $context, $operation) !== $choice) throw new RuntimeException('choice replay');
++$checks;

$private = $result;
$private['supplier_offer_id'] = 'opaque';
try {
    AnyTourAndromedaQuoteAttemptState::completed($reserved, $private);
    throw new RuntimeException('private state accepted');
} catch (RuntimeException $e) {
    if ($e->getMessage() !== 'ANDROMEDA_QUOTE_PRIVATE_STATE') throw $e;
}
++$checks;

$booking = $result;
$booking['booking_enabled'] = true;
try {
    AnyTourAndromedaQuoteAttemptState::completed($reserved, $booking);
    throw new RuntimeException('booking accepted');
} catch (RuntimeException $e) {
    if ($e->getMessage() !== 'ANDROMEDA_QUOTE_RESULT_INVALID') throw $e;
}
++$checks;

$unverified = $result;
$unverified['final_price_verified'] = false;
try {
    AnyTourAndromedaQuoteAttemptState::completed($reserved, $unverified);
    throw new RuntimeException('unverified final accepted');
} catch (RuntimeException $e) {
    if ($e->getMessage() !== 'ANDROMEDA_QUOTE_RESULT_INVALID') throw $e;
}
++$checks;

print("Andromeda quote attempt state: {$checks} checks passed\n");
