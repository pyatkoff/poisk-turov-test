<?php
declare(strict_types=1);
require __DIR__ . '/../app/integrations/three-provider-operator.php';
$checks=0;
function operator_check(bool $ok): void { global $checks; ++$checks; if(!$ok) throw new RuntimeException('operator_'.$checks); }
foreach (['tourvisor'=>'ANEX','andromeda'=>'FUN&SUN'] as $provider=>$raw) {
    $v=AnyTourThreeProviderOperator::fromSearch($provider,$raw);
    operator_check($v['raw']===$raw && $v['canonical_name']===null);
    operator_check($v['canonical_verified']===false && $v['identity_source']==='raw_label_only');
    operator_check($v['cross_provider_equivalence_verified']===false && $v['supplier_code_exposed']===false);
}
$anex=AnyTourThreeProviderOperator::fromSearch('anex','Anex Tour');
operator_check($anex['canonical_name']==='ANEX' && $anex['canonical_verified']===true);
operator_check($anex['identity_source']==='provider_fixed' && $anex['filter_status']==='unsupported');
foreach ([['other','ANEX'],['anex','Other'],['tourvisor',''],['tourvisor',"Bad\nName"],['tourvisor',str_repeat('A',121)]] as [$provider,$raw]) {
    try { AnyTourThreeProviderOperator::fromSearch($provider,$raw); operator_check(false); }
    catch (InvalidArgumentException $e) { operator_check(true); }
}

foreach (['tourvisor', 'anex', 'andromeda'] as $provider) {
    $missing = AnyTourThreeProviderOperator::fromSearch($provider, null);
    operator_check($missing === [
        'raw' => null,
        'canonical_name' => null,
        'canonical_verified' => false,
        'identity_source' => 'missing',
        // Provider filter capability is separate from the missing offer fact.
        'filter_status' => $provider === 'tourvisor' ? 'verified' : 'unsupported',
        'cross_provider_equivalence_verified' => false,
        'supplier_code_exposed' => false,
    ]);
    $known = AnyTourThreeProviderOperator::fromSearch($provider, 'ANEX');
    operator_check($missing !== $known);
    operator_check(array_keys($missing) === array_keys($known));
    // Only explicit null is absence; malformed supplied values are not repaired.
    foreach (['', '   ', 0, 7, false, [], "Bad\nName", str_repeat('A', 121)] as $bad) {
        try { AnyTourThreeProviderOperator::fromSearch($provider, $bad); operator_check(false); }
        catch (InvalidArgumentException $error) { operator_check($error->getMessage() === 'THREE_PROVIDER_OPERATOR'); }
    }
}
try { AnyTourThreeProviderOperator::fromSearch('other', null); operator_check(false); }
catch (InvalidArgumentException $error) { operator_check($error->getMessage() === 'THREE_PROVIDER_OPERATOR'); }
operator_check(AnyTourThreeProviderOperator::fromSearch('anex', ' Anex Tour ') === $anex);
echo 'Three-provider operator evidence: '.$checks." checks passed; mapping/supplier=0.\n";
