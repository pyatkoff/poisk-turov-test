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
echo 'Three-provider operator evidence: '.$checks." checks passed; mapping/supplier=0.\n";
