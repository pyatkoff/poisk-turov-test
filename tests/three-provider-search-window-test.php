<?php
declare(strict_types=1);
require __DIR__ . '/../app/integrations/three-provider-search-window.php';

$checks = 0;
function window_check(bool $ok): void
{
    global $checks;
    ++$checks;
    if (!$ok) {
        throw new RuntimeException('window_check_'.$checks);
    }
}
function window_fixture(): array
{
    return [
        'date_from' => '2026-10-19',
        'date_to' => '2026-10-25',
        'nights_from' => 10,
        'nights_to' => 12,
        'adults' => 2,
        'child_ages' => [4, 11],
    ];
}

$value = AnyTourThreeProviderSearchWindow::fromSearch(window_fixture());
window_check($value['schema_version'] === 1);
window_check($value['dates'] === ['from' => '2026-10-19', 'to' => '2026-10-25', 'inclusive_days' => 7]);
window_check($value['nights'] === ['from' => 10, 'to' => 12]);
window_check($value['party'] === ['adults' => 2, 'children' => 2, 'child_ages' => [4, 11]]);
window_check($value['statuses']['canonical_input_shape'] === 'verified');
window_check($value['statuses']['ordered_child_ages'] === 'verified');
window_check($value['statuses']['supplier_date_span_support'] === 'unknown');
window_check($value['statuses']['supplier_night_span_support'] === 'unknown');
window_check($value['statuses']['cross_provider_result_equivalence'] === 'unknown');
window_check(!isset($value['provider_id']) && !isset($value['supplier_id']));

$one = window_fixture();
$one['date_to'] = $one['date_from'];
$one['nights_to'] = $one['nights_from'];
$one['child_ages'] = [];
$same = AnyTourThreeProviderSearchWindow::fromSearch($one);
window_check($same['dates']['inclusive_days'] === 1);
window_check($same['party']['children'] === 0);

$leap = window_fixture();
$leap['date_from'] = '2028-02-28';
$leap['date_to'] = '2028-03-01';
window_check(AnyTourThreeProviderSearchWindow::fromSearch($leap)['dates']['inclusive_days'] === 3);

$bad = [
    function () { $x = window_fixture(); $x['date_from'] = '2026-02-30'; AnyTourThreeProviderSearchWindow::fromSearch($x); },
    function () { $x = window_fixture(); $x['date_from'] = '19.10.2026'; AnyTourThreeProviderSearchWindow::fromSearch($x); },
    function () { $x = window_fixture(); $x['date_to'] = '2026-10-18'; AnyTourThreeProviderSearchWindow::fromSearch($x); },
    function () { $x = window_fixture(); $x['nights_from'] = 0; AnyTourThreeProviderSearchWindow::fromSearch($x); },
    function () { $x = window_fixture(); $x['nights_to'] = 31; AnyTourThreeProviderSearchWindow::fromSearch($x); },
    function () { $x = window_fixture(); $x['nights_to'] = 9; AnyTourThreeProviderSearchWindow::fromSearch($x); },
    function () { $x = window_fixture(); $x['adults'] = 0; AnyTourThreeProviderSearchWindow::fromSearch($x); },
    function () { $x = window_fixture(); $x['child_ages'] = [18]; AnyTourThreeProviderSearchWindow::fromSearch($x); },
    function () { $x = window_fixture(); $x['child_ages'] = [1 => 7]; AnyTourThreeProviderSearchWindow::fromSearch($x); },
    function () { $x = window_fixture(); $x['child_ages'] = array_fill(0, 10, 7); AnyTourThreeProviderSearchWindow::fromSearch($x); },
    function () { $x = window_fixture(); $x['operator_id'] = 5; AnyTourThreeProviderSearchWindow::fromSearch($x); },
];
foreach ($bad as $case) {
    try {
        $case();
        window_check(false);
    } catch (InvalidArgumentException $e) {
        window_check(true);
    }
}

echo 'Three-provider search window: '.$checks." checks passed; supplier/DB/mapping=0.\n";
