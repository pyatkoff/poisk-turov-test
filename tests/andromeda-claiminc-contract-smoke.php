<?php
declare(strict_types=1);
require __DIR__ . '/../app/integrations/andromeda-claiminc-contract.php';

$checks = 0;
function claim_check(bool $ok, string $label): void {
    global $checks; ++$checks;
    if (!$ok) throw new RuntimeException($label . '_' . $checks);
}

$claiminc = '5:price-form:opaque/claim+bytes=ABC_123-xyz';
$row = ['id' => $claiminc, 'price' => 83080];
claim_check(AnyTourAndromedaClaimincContract::fromPriceRow($row) === $claiminc, 'price_id_not_preserved');
claim_check(AnyTourAndromedaClaimincContract::retained($claiminc) === $claiminc, 'retained_claim_changed');

foreach ([[], ['id'=>123], ['id'=>''], ['id'=>"bad\nclaim"], ['id'=>str_repeat('x',4097)]] as $bad) {
    try {
        AnyTourAndromedaClaimincContract::fromPriceRow($bad);
        throw new LogicException('bad_claiminc_accepted');
    } catch (RuntimeException $expected) {
        claim_check(str_starts_with($expected->getMessage(), 'ANDROMEDA_'), 'wrong_claiminc_error');
    }
}

$catalog = AnyTourAndromedaClaimincContract::catalogKeyEvidence('opaque/package/core');
claim_check($catalog === [
    'relation'=>'supplier_confirmed_reduced_claiminc',
    'full_claiminc_reconstructable'=>false,
    'usable_as_claiminc'=>false,
], 'catalog_relation_overclaimed');
claim_check(!hash_equals(hash('sha256',$claiminc), hash('sha256','opaque/package/core')), 'fixture_must_not_fake_equality');

foreach ([null, 123, '', "bad\tkey", str_repeat('x',4097)] as $bad) {
    try {
        AnyTourAndromedaClaimincContract::catalogKeyEvidence($bad);
        throw new LogicException('bad_catalog_key_accepted');
    } catch (RuntimeException $expected) {
        claim_check($expected->getMessage() === 'ANDROMEDA_CATALOG_KEY_INVALID', 'wrong_catalog_error');
    }
}

claim_check(AnyTourAndromedaClaimincContract::retryAllowed('transport_failure') === true, 'transport_retry_not_allowed');
foreach (['supplier_error','invalid_response','timeout_unclassified','reserved','unknown','captured','stale'] as $outcome) {
    claim_check(AnyTourAndromedaClaimincContract::retryAllowed($outcome) === false, 'retry_scope_too_wide');
}

$effect = AnyTourAndromedaClaimincContract::broninitEffect();
claim_check($effect['operation'] === 'refresh_selected_package', 'wrong_broninit_operation');
claim_check($effect['creates_booking'] === false && $effect['creates_application'] === false, 'broninit_booking_overclaim');
claim_check($effect['operator_internal_side_effects'] === 'unspecified', 'side_effects_overclaimed');

$before = serialize($row);
AnyTourAndromedaClaimincContract::fromPriceRow($row);
claim_check(serialize($row) === $before, 'price_row_mutated');

echo 'Andromeda claiminc contract: ' . $checks . " checks passed; supplier/network/DB/booking calls=0.\n";
