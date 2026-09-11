<?php
declare(strict_types=1);

require __DIR__ . '/../app/integrations/andromeda-package-public.php';

$checks = 0;
function guard_check(bool $ok): void {
    global $checks; ++$checks;
    if (!$ok) throw new RuntimeException('package_public_value_guard_' . $checks);
}

$date = new ReflectionMethod(AnyTourAndromedaPackagePublic::class, 'date');
$date->setAccessible(true);
foreach (['2026-01-01', '2026-12-31Z', '2028-02-29', '2026-03-05+14:00', '2026-03-05-13:59'] as $value) {
    guard_check($date->invoke(null, $value) === $value);
}
foreach (['2026-02-29', '2026-00-10', '2026-13-01', '2026-04-31', '2026-01-01+14:01',
    '2026-01-01+15:00', '2026-01-01+01:60', '2026-1-01', 'not-a-date', 20260922] as $value) {
    guard_check($date->invoke(null, $value) === null);
}

function projected_package(mixed $variants, bool $includeVariants = true): array {
    $raw = ['claimDocument' => [[
        'catalogKey' => 'selected-package-id',
        'condition' => 'ccOffer',
        'datebeg' => '2026-02-29',
        'dateend' => '2026-03-05',
        'hotels' => [['hotel' => [[
            'name' => 'Safe Hotel',
            'datebeg' => '2026-04-31',
            'dateend' => '2026-05-07',
        ]]]],
    ]]];
    if ($includeVariants) $raw['variants'] = $variants;
    return AnyTourAndromedaPackagePublic::record([
        'version' => 1,
        'status' => 'captured',
        // Full PRICES[].id/claiminc provenance is deliberately distinct from shortened catalogKey.
        'supplier_offer_sha256' => hash('sha256', 'operator5:form42:selected-package-id'),
        'package_sha256' => hash('sha256', json_encode($raw, JSON_THROW_ON_ERROR)),
        'private_package' => $raw,
        'identity_verified' => false,
        'quote_verified' => false,
        'selection_enabled' => false,
    ]);
}

$projection = projected_package([['hotels' => []]]);
guard_check($projection['status'] === 'package_captured_unquoted');
guard_check($projection['package_binding_verified'] === false);
guard_check($projection['alternatives_available'] === true);
guard_check(!isset($projection['trip']['date_from']) && $projection['trip']['date_to'] === '2026-03-05');
guard_check(!isset($projection['hotels'][0]['date_from']) && $projection['hotels'][0]['date_to'] === '2026-05-07');

guard_check(projected_package([], true)['alternatives_available'] === false);
guard_check(projected_package(null, false)['alternatives_available'] === false);
guard_check(projected_package(['hotels' => []], true)['alternatives_available'] === false);
guard_check(projected_package(array_fill(0, 101, ['hotels' => []]), true)['alternatives_available'] === false);

echo 'Andromeda package public value guards: ' . $checks . " checks passed; supplier/SSH/DB/filesystem writes=0.\n";
