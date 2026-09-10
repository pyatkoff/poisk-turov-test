<?php
declare(strict_types=1);
require __DIR__ . '/../scripts/diagnostics/andromeda_package_evidence.php';

// SYNTHETIC contract fixtures, shaped from official claim_struct documentation.
// Not a supplier capture, a booking request, or evidence that a live offer exists.
$checks = 0;
function evidence_check(bool $ok): void {
    global $checks; ++$checks;
    if (!$ok) throw new RuntimeException('Package evidence fixture failed at assertion ' . $checks);
}
function evidence_record(array $raw): array {
    return ['version' => 1, 'status' => 'captured', 'private_package' => $raw,
        'package_sha256' => hash('sha256', json_encode($raw, JSON_THROW_ON_ERROR)),
        'supplier_offer_sha256' => hash('sha256', 'private-package-id'),
        'identity_verified' => false, 'quote_verified' => false, 'selection_enabled' => false];
}
$raw = ['version' => '3.0', 'checkFields' => [['people' => [[
    'name' => [['required' => 'true', 'readOnly' => 'false']],
    'pnumber' => [['required' => true]], 'sex' => [['required' => 'false']]]],
    'buyer' => [['email' => [['required' => false]]]]]],
    'claimDocument' => [[
        'catalogKey' => 'private-package-id', 'condition' => 'ccOffer', 'freightExternal' => '1',
        'hotels' => [['hotel' => [['key' => '123', 'name' => 'private-hotel-name']]]],
        'transports' => [['transport' => [['key' => '1'], ['key' => '2']]]],
        'services' => [['service' => [['name' => 'private-service']]]],
        'buyerMoneys' => [['buyerClaimMoney' => [['net' => '52902.00', 'currency' => 'RU_', 'currencyKey' => '643']]]],
        'moneys' => [['money' => [['net' => 'private-agency-cost', 'discoCost' => 'private-discount']]]],
        'buyer' => [['name' => 'private-person', 'email' => 'private-contact', 'pnumber' => 'private-passport']],
        'peoples' => [['people' => [['name' => 'private-traveller']]]],
        'provider' => [['guid' => 'private-provider-id', 'supportNote' => 'private-support']],
        'note' => [['_'=>'private-warning']],
    ]], 'variants' => [['hotels' => [['hotel' => array_fill(0, 12, ['name' => 'private-variant'])]]]],
    'private-arbitrary-key' => 'private-secret'];
$record = evidence_record($raw); $before = serialize($record);
$read = anytour_andromeda_package_evidence($record);
evidence_check($read['status'] === 'inspected_not_verified' && $read['document'] === 'single');
evidence_check($read['selected_id_relation'] === 'same_bytes' && !$read['identity_verified']);
evidence_check($read['external_flights'] === 'required');
evidence_check($read['selected_orders']['hotels']['count'] === 1);
evidence_check($read['selected_orders']['transports']['count'] === 2);
evidence_check($read['selected_orders']['services']['count'] === 1);
evidence_check($read['buyer_price']['single_amount_shape_valid']);
evidence_check($read['agency_money']['count'] === 1);
evidence_check($read['required_fields'] === ['shape' => 'list', 'traveller' => 2, 'buyer' => 0]);
evidence_check($read['message_fields_present']);
evidence_check(serialize($record) === $before && anytour_andromeda_package_evidence($record) === $read);
$encoded = json_encode($read, JSON_THROW_ON_ERROR);
evidence_check(!str_contains($encoded, 'private-') && !str_contains($encoded, '52902') && !str_contains($encoded, '643'));
foreach (['identity_verified', 'quote_verified', 'selection_enabled', 'current_context_verified'] as $flag) evidence_check($read[$flag] === false);
foreach (['reserved', 'unknown', 'stale', 'failed'] as $status) {
    evidence_check(anytour_andromeda_package_evidence(array_replace($record, ['status' => $status]))['status'] === 'not_captured');
}
foreach (['identity_verified', 'quote_verified', 'selection_enabled'] as $flag) {
    evidence_check(anytour_andromeda_package_evidence(array_replace($record, [$flag => true]))['status'] === 'invalid_checkpoint');
}
evidence_check(anytour_andromeda_package_evidence(array_replace($record, ['package_sha256' => str_repeat('0', 64)]))['status'] === 'invalid_checkpoint');
evidence_check(anytour_andromeda_package_evidence(array_replace($record, ['package_sha256' => 'private-digest']))['status'] === 'invalid_checkpoint');
$inspect = static fn(array $document): array => anytour_andromeda_package_evidence(evidence_record(array_replace($raw, ['claimDocument' => [$document]])));
$doc = $raw['claimDocument'][0];
foreach ([['catalogKey' => 'other-private-id', 'expected' => 'different_bytes'], ['catalogKey' => null, 'expected' => 'unknown']] as $case) {
    $r = $inspect(array_replace($doc, ['catalogKey' => $case['catalogKey']]));
    evidence_check($r['selected_id_relation'] === $case['expected'] && !$r['identity_verified']);
}
foreach ([['value' => '0', 'expected' => 'not_indicated'], ['value' => 0, 'expected' => 'not_indicated'],
    ['value' => 2, 'expected' => 'required'], ['value' => null, 'expected' => 'unknown'],
    ['value' => false, 'expected' => 'unknown'], ['value' => 'private-text', 'expected' => 'unknown']] as $case) {
    evidence_check($inspect(array_replace($doc, ['freightExternal' => $case['value']]))['external_flights'] === $case['expected']);
}
evidence_check($inspect(array_replace($doc, ['condition' => 'ccBooked']))['booking_state'] === 'not_temporary');
foreach ([null, 'bad', ['hotel'=>[]], [[[]]], [], [['hotel'=>[]]]] as $value) {
    $r = $inspect(array_replace($doc, ['hotels' => $value]));
    evidence_check($r['selected_orders']['hotels']['count'] === (in_array($value, [[], [['hotel'=>[]]]], true) ? 0 : null));
}
$r = $inspect(array_replace($doc, ['hotels' => [['hotel'=>array_fill(0,301,['key'=>'1'])]]]));
evidence_check($r['selected_orders']['hotels']['count'] === null);
foreach ([null, '', '0', '0.00', '-1', '1,20', '1e5', '1.001', 'private-price', 1.1, false, ['net'=>'9']] as $amount) {
    $changed = $doc; $changed['buyerMoneys'][0]['buyerClaimMoney'][0]['net'] = $amount;
    evidence_check(!$inspect($changed)['buyer_price']['single_amount_shape_valid']);
}
foreach (['0.01', '42', '42.00', 42] as $amount) {
    $changed = $doc; $changed['buyerMoneys'][0]['buyerClaimMoney'][0]['net'] = $amount;
    evidence_check($inspect($changed)['buyer_price']['single_amount_shape_valid']);
}
$changed = $doc; unset($changed['buyerMoneys']);
evidence_check(!$inspect($changed)['buyer_price']['single_amount_shape_valid']);
$changed = $doc; $changed['buyerMoneys'][0]['buyerClaimMoney'][] = $changed['buyerMoneys'][0]['buyerClaimMoney'][0];
evidence_check(!$inspect($changed)['buyer_price']['single_amount_shape_valid']);
$changed = $doc; $changed['buyerMoneys'][0]['buyerClaimMoney'][0]['currencyKey'] = null;
evidence_check(!$inspect($changed)['buyer_price']['single_amount_shape_valid']);
$changed = $doc; $changed['buyerMoneys'][0]['buyerClaimMoney'][0]['currency'] = 'private-currency';
evidence_check(!$inspect($changed)['buyer_price']['single_amount_shape_valid']);
foreach ([null, [], [$doc, $doc], ['catalogKey'=>'not-a-list'], [null], [[]]] as $value) {
    $r = anytour_andromeda_package_evidence(evidence_record(array_replace($raw, ['claimDocument'=>$value])));
    evidence_check($r['document'] !== 'single' && !isset($r['buyer_price']));
}
foreach ([null, ['people'=>[]], [[[]]], [['people'=>[['name'=>[['required'=>'TRUE']]]]]]] as $value) {
    $r = anytour_andromeda_package_evidence(evidence_record(array_replace($raw, ['checkFields'=>$value])));
    evidence_check($r['required_fields']['traveller'] === null);
}
$changed = $raw; unset($changed['claimDocument'][0]['note']); $changed['warnings'] = ['private-message'];
evidence_check(anytour_andromeda_package_evidence(evidence_record($changed))['message_fields_present']);
$changed = $raw; $changed['claimDocument'][0]['note'] = [];
evidence_check(!anytour_andromeda_package_evidence(evidence_record($changed))['message_fields_present']);
$changed = $record; $changed['private_package']['extra'] = new class implements JsonSerializable {
    public function jsonSerialize(): mixed { throw new LogicException('Serialization hook must not execute'); }
};
evidence_check(anytour_andromeda_package_evidence($changed)['status'] === 'invalid_checkpoint');
$changed = $raw; $changed['extra'] = str_repeat('x', 2097153);
evidence_check(anytour_andromeda_package_evidence(evidence_record($changed))['status'] === 'invalid_checkpoint');
$changed = $record; $changed['private_package']['extra'] = NAN;
evidence_check(anytour_andromeda_package_evidence($changed)['status'] === 'invalid_checkpoint');
$changed = $record; $deep = [];
for ($i=0; $i<34; ++$i) $deep = [$deep];
$changed['private_package']['extra'] = $deep;
evidence_check(anytour_andromeda_package_evidence($changed)['status'] === 'invalid_checkpoint');
// Every returned leaf is a fixed classification, boolean or count, never supplier text.
$walk = static function ($value) use (&$walk): bool {
    if (is_array($value)) { foreach ($value as $child) if (!$walk($child)) return false; return true; }
    return !is_string($value) || preg_match('/^[a-z_]+$/D', $value) === 1;
};
evidence_check($walk($read));
echo 'Package evidence: ' . $checks . " assertions passed; synthetic fixtures; supplier/DB/filesystem writes=0.\n";
