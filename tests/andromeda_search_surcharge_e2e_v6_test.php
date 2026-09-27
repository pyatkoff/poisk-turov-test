<?php
declare(strict_types=1);
define('ANYTOUR_ANDROMEDA_SURCHARGE_E2E_TEST_MODE', true);
require_once __DIR__ . '/../scripts/diagnostics/andromeda_search_surcharge_e2e.php';

final class AnyTourE2EFakeSupplierFailure extends RuntimeException
{
    public function __construct(private array $facts) { parent::__construct('ANDROMEDA_SUPPLIER_ERROR'); }
    public function diagnosticFacts(): array { return $this->facts; }
}

$checks = 0;
$got = anytour_andromeda_surcharge_e2e_failure_facts(new AnyTourE2EFakeSupplierFailure([
    'action' => 'get_flights', 'code' => 'FLIGHT_NOT_AVAILABLE', 'raw' => 'DO_NOT_RETAIN',
]));
if ($got !== ['failure_stage'=>'get_flights','supplier_code'=>'FLIGHT_NOT_AVAILABLE']) throw new RuntimeException('safe_facts'); ++$checks;

$got = anytour_andromeda_surcharge_e2e_failure_facts(new AnyTourE2EFakeSupplierFailure([
    'action' => 'private_stage', 'code' => 'bad code with spaces', 'raw' => 'DO_NOT_RETAIN',
]));
if ($got !== []) throw new RuntimeException('unsafe_facts'); ++$checks;

$got = anytour_andromeda_surcharge_e2e_failure_facts(new RuntimeException('plain'));
if ($got !== []) throw new RuntimeException('plain_exception'); ++$checks;

if (ANYTOUR_ANDROMEDA_SURCHARGE_E2E_OPERATION !== 'andromeda-search-surcharge-e2e-3419-v6-turkey-2026-12-13-2a-7n'
    || ANYTOUR_ANDROMEDA_SURCHARGE_E2E_RUNTIME_SOURCE !== '4f55bb90b68bf9429f8e7e7716d71429351a468b') {
    throw new RuntimeException('fresh_operation_pin');
}
++$checks;

echo "ANDROMEDA_SURCHARGE_E2E_V6_OK {$checks}\n";
