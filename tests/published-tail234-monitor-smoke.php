<?php
declare(strict_types=1);
require_once __DIR__.'/../v2/data/published-hotels-monitor-v1.php';
require_once __DIR__.'/../v2/data/top500-monthly-plan-v1.php';

function tail234_check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

// Original W6–W10 receipts minus the installed plan at native job 134896254685.
$ids = v2_published_tail234_monitor_ids_20261118();
tail234_check(count(array_unique($ids)) === 234, 'wrong distinct publication count');
tail234_check(hash('sha256', implode(',', $ids)) === '69d1de534fc23cd44690ffc45dbb67e27e477be925d027f004e47679d3bf6396', 'receipt membership changed');
tail234_check(array_diff($ids, v2_published_expansion_monitor_ids()) === [], 'missing published queue priority');

$origin = [['id'=>200001, 'country_id'=>4, 'country_name'=>'A', 'is_active'=>1]];
$routes = [['departure_id'=>1, 'country_id'=>4, 'is_active'=>1]];
$plan = v2_top500_monthly_plan([200001], $origin, $routes, '2026-11-18', 12, 1);
$oldIds = array_diff(v2_all_hotel_monitor_input_ids(), $ids);
$oldRows = $origin;
foreach ($oldIds as $id) $oldRows[] = ['id'=>$id, 'country_id'=>4, 'country_name'=>'A', 'is_active'=>1];
$old = v2_append_published_hotel_monthly_plan($plan, $oldRows, $routes, '2026-11-18', 12, 1, true);
$rows = $oldRows;
foreach ($ids as $id) $rows[] = ['id'=>$id, 'country_id'=>4, 'country_name'=>'A', 'is_active'=>1];
$full = v2_append_published_hotel_monthly_plan($plan, $rows, $routes, '2026-11-18', 12, 1, true);
tail234_check(array_slice($full['targets'], 0, count($old['targets'])) === $old['targets'], 'existing search keys or windows changed');
$seen = [];
foreach (array_slice($full['targets'], count($old['targets'])) as $target) {
    tail234_check(count($target['hotel_ids']) <= 30, 'supplier batch limit');
    foreach ($target['hotel_ids'] as $id) $seen[$target['month']][$id] = true;
}
tail234_check(count($seen) === 12, 'incomplete calendar horizon');
foreach ($seen as $month => $covered) {
    tail234_check(count($covered) === 234 && array_diff($ids, array_keys($covered)) === [], 'wrong new IDs for '.$month);
}
$rows[count($rows)-1]['is_active'] = 0;
$rows[count($rows)-2]['country_id'] = 11; // No active departure route in this fixture.
$partial = v2_append_published_hotel_monthly_plan($plan, $rows, $routes, '2026-11-18', 12, 1, true);
tail234_check($partial['published_hotels']['cohorts']['published_tail234_20261118']['searchable'] === 232, 'inactive or unroutable hotel admitted');
echo "PUBLISHED_TAIL234_OK exact=234 months=12 previous_targets_preserved=1 supplier_calls=0 writes=0\n";
