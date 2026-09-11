<?php
declare(strict_types=1);

$sourcePath = dirname(__DIR__) . '/v2/anex-hotel-review.php';
$source = file_get_contents($sourcePath);
if (!is_string($source) || $source === '') throw new RuntimeException('panel_source_unavailable');

$checks = 0;
function check_gate(bool $ok, string $label): void
{
    global $checks;
    $checks++;
    if (!$ok) throw new RuntimeException($label);
}

$adapterStart = strpos($source, "if (is_string(\$path) && \$path !== '') {");
$standaloneStart = strpos($source, '} else {', $adapterStart === false ? 0 : $adapterStart);
$standaloneMarker = strpos($source, '$standaloneOwner = true;', $standaloneStart === false ? 0 : $standaloneStart);
$principalCall = strpos($source, 'AnexReviewAccess::principal($context)', $standaloneMarker === false ? 0 : $standaloneMarker);

check_gate($adapterStart !== false, 'trusted_adapter_branch_missing');
check_gate($standaloneStart !== false && $standaloneStart > $adapterStart, 'standalone_branch_missing');
check_gate($standaloneMarker !== false && $standaloneMarker > $standaloneStart, 'standalone_owner_marker_missing');
check_gate($principalCall !== false && $principalCall > $standaloneMarker, 'principal_call_order_invalid');

$adapterBlock = substr($source, $adapterStart, $standaloneStart - $adapterStart);
check_gate(strpos($adapterBlock, "'anex:decide'") === false, 'external_adapter_must_not_be_elevated');
check_gate(strpos($adapterBlock, "write_enabled'] = true") === false, 'external_adapter_write_must_be_explicit');
check_gate(strpos($adapterBlock, "pair_exclusions_enforced'] = true") === false, 'external_adapter_pair_guard_must_be_explicit');

$standaloneBlock = substr($source, $standaloneMarker, $principalCall - $standaloneMarker);
check_gate(strpos($standaloneBlock, "['anex:review', 'anex:decide']") !== false, 'standalone_decide_capability_missing');
check_gate(strpos($standaloneBlock, "\$context['write_enabled'] = true;") !== false, 'standalone_write_flag_missing');
check_gate(strpos($standaloneBlock, "\$context['pair_exclusions_enforced'] = true;") !== false, 'standalone_pair_guard_flag_missing');

$writeGate = "(\$context['write_enabled'] ?? false) === true && (\$context['pair_exclusions_enforced'] ?? false) === true";
check_gate(strpos($source, $writeGate) !== false, 'combined_write_gate_missing');
check_gate(strpos($source, "in_array('anex:decide', \$context['capabilities'] ?? [], true)") !== false, 'decision_capability_gate_missing');
check_gate(strpos($source, "AnexReviewAccess::post(\$_SERVER, \$_POST, \$csrf);") !== false, 'csrf_post_guard_missing');
check_gate(strpos($source, "review_confirmation_required") !== false, 'accept_confirmation_guard_missing');
check_gate(strpos($source, "\$service->decide(\$_POST, \$actor)") !== false, 'guarded_decide_call_missing');

fwrite(STDOUT, 'Standalone owner write gate: ' . $checks . " checks passed\n");
