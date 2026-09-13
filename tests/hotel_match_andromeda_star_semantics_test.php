<?php
declare(strict_types=1);
require_once __DIR__ . '/../scripts/diagnostics/hotel_match_andromeda_star_semantics.php';

$checks = 0;
$assert = static function(bool $ok, string $name) use (&$checks): void {
    $checks++;
    if (!$ok) throw new RuntimeException($name);
};

foreach ([
    ['category'=>'5'], ['star'=>4], ['stars'=>'3*'], ['starName'=>'2 ★'], ['star_name'=>'1 star'],
] as $i=>$row) {
    $parsed = hmss_star($row);
    $assert(is_array($parsed) && $parsed['numeric'] === 5-$i, 'parse_'.$i);
}
$assert(hmss_star(['starKey'=>17]) === null, 'starKey_not_category');
$assert(hmss_star(['category'=>'6']) === null, 'out_of_range');
$assert(hmss_star(['category'=>'five']) === null, 'text_not_inferred');
$assert(hmss_star(['category'=>'', 'star'=>'4'])['field'] === 'star', 'skip_invalid_category');
$assert(hmss_evidence('') === [], 'empty_evidence');
$assert(hmss_evidence('{"source":{"star":"5"}}')['source']['star'] === '5', 'json_evidence');
$assert(hmss_evidence('{bad') === [], 'malformed_evidence_fail_closed');

echo "hotel_match_andromeda_star_semantics_test: {$checks} checks PASS\n";
