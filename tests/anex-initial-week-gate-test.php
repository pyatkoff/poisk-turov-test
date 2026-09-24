<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/integrations/anex-initial-week-gate.php';

function expect_true($value, string $label): void {
    if ($value !== true) throw new RuntimeException($label);
}
function expect_false($value, string $label): void {
    if ($value !== false) throw new RuntimeException($label);
}
function expect_same($expected, $actual, string $label): void {
    if ($expected !== $actual) throw new RuntimeException($label . ': ' . var_export($actual, true));
}
function expect_throws(callable $fn, string $label): void {
    try { $fn(); } catch (InvalidArgumentException $e) { return; }
    throw new RuntimeException($label);
}

$state = [];
$first = anytour_anex_initial_week_gate($state, [
    'generation' => 101,
    'params' => ['dateFrom' => '2026-10-01', 'dateTo' => '2026-10-19'],
]);
expect_true($first['allowed'], 'first allowed');
expect_same('2026-10-07', $first['date_to'], 'first clamps to seven days');
expect_same(['generation' => 101, 'date_from' => '2026-10-01', 'date_to' => '2026-10-07'], $state, 'state');

$retry = anytour_anex_initial_week_gate($state, [
    'generation' => 101,
    'params' => ['dateFrom' => '2026-10-01', 'dateTo' => '2026-10-07'],
]);
expect_true($retry['allowed'], 'same initial retry allowed');
expect_same('initial_window_retry', $retry['reason'], 'retry reason');

$later = anytour_anex_initial_week_gate($state, [
    'generation' => 101,
    'params' => ['dateFrom' => '2026-10-08', 'dateTo' => '2026-10-14'],
]);
expect_false($later['allowed'], 'later window blocked');
$reply = anytour_anex_initial_week_skipped_response($later);
expect_same([], $reply['hotels'], 'skip hotels empty');
expect_same('initial_week_only', $reply['skip_reason'], 'skip reason');
expect_same(0, $reply['pages_read'], 'skip no supplier pages');

$new = anytour_anex_initial_week_gate($state, [
    'generation' => 102,
    'params' => ['dateFrom' => '2026-10-08', 'dateTo' => '2026-10-14'],
]);
expect_true($new['allowed'], 'new generation allowed');
expect_same('2026-10-08', $state['date_from'], 'new generation replaces gate');

expect_throws(static function (): void {
    $state = [];
    anytour_anex_initial_week_gate($state, [
        'generation' => 103,
        'params' => ['dateFrom' => '2026-02-30', 'dateTo' => '2026-03-02'],
    ]);
}, 'invalid calendar date fails');

expect_throws(static function (): void {
    $state = [];
    anytour_anex_initial_week_gate($state, [
        'generation' => 103,
        'params' => ['dateFrom' => '2026-10-08', 'dateTo' => '2026-10-07'],
    ]);
}, 'reverse range fails');

echo "OK\n";
