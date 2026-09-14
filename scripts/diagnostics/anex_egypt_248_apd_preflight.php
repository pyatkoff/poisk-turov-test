<?php
declare(strict_types=1);

const ANEX_EGYPT_248_APD_PREFLIGHT_OPERATION = 'anex-egypt-248-apd-preflight-20260914-v2';
const ANEX_EGYPT_248_APD_SEMANTIC_OPERATION = 'anex-egypt-248-apd-retained-20260914-v1';
const ANEX_EGYPT_248_APD_PREFLIGHT_LOCAL_HOTEL = 248;
const ANEX_EGYPT_248_APD_PREFLIGHT_EXTERNAL_HOTEL = '10449';

function anex_egypt_248_apd_preflight_category(string $stage): string
{
    return [
        'input' => 'invalid_input',
        'binding_contract' => 'b2b_tour_binding_unverified',
    ][$stage] ?? 'preflight_unconfirmed';
}

function anex_egypt_248_apd_preflight_main(array $input): array
{
    $out = [
        'schema_version' => 1,
        'operation_id' => ANEX_EGYPT_248_APD_PREFLIGHT_OPERATION,
        'semantic_operation_id' => ANEX_EGYPT_248_APD_SEMANTIC_OPERATION,
        'status' => 'blocked',
        'blocker_stage' => 'input',
        'blocker_category' => 'invalid_input',
        'supplier_calls' => 0,
        'additional_prices_calls' => 0,
        'tourvisor_calls' => 0,
        'andromeda_calls' => 0,
        'booking_calls' => 0,
        'broninit_calls' => 0,
        'mapping_writes' => 0,
        'semantic_reservation_written' => false,
    ];
    if (array_keys($input) !== ['operation_id', 'source_sha']
        || ($input['operation_id'] ?? null) !== ANEX_EGYPT_248_APD_PREFLIGHT_OPERATION
        || !is_string($input['source_sha'] ?? null)
        || !preg_match('/\A[a-f0-9]{40}\z/D', $input['source_sha'])) {
        return $out;
    }
    $out['source_sha'] = $input['source_sha'];
    $out['blocker_stage'] = 'binding_contract';
    $out['blocker_category'] = anex_egypt_248_apd_preflight_category('binding_contract');
    $out['binding_contract'] = [
        'state' => 'unverified',
        'searchtour_program_is_b2b_tour' => false,
        'authority_required' => 'supplier_dictionary_or_supplier_issued_binding',
        'local_hotel_id' => ANEX_EGYPT_248_APD_PREFLIGHT_LOCAL_HOTEL,
        'external_hotel_id' => ANEX_EGYPT_248_APD_PREFLIGHT_EXTERNAL_HOTEL,
    ];
    return $out;
}

if (!defined('ANYTOUR_ANEX_EGYPT_248_APD_PREFLIGHT_LIBRARY_ONLY')) {
    $raw = file_get_contents('php://stdin', false, null, 0, 8192);
    try {
        $input = is_string($raw) ? json_decode($raw, true, 8, JSON_THROW_ON_ERROR) : null;
        if (!is_array($input)) throw new RuntimeException('PREFLIGHT_INPUT');
        $result = anex_egypt_248_apd_preflight_main($input);
    } catch (Throwable $ignored) {
        $result = anex_egypt_248_apd_preflight_main([]);
    }
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
}
