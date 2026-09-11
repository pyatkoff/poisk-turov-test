<?php
declare(strict_types=1);
require_once __DIR__ . '/../scripts/diagnostics/anex_hotelcode_evidence.php';

function same($expected, $actual, string $label): void {
    if ($expected !== $actual) {
        fwrite(STDERR, $label . ': expected ' . var_export($expected, true) . ' got ' . var_export($actual, true) . "\n");
        exit(1);
    }
}

$sample = 'https://files.anextour.ru/hotel/egypt/hotel/sharming-inn-hotel-sharm-el-sheikh/o417822?&hotelCode=5844';
$r = anytour_anex_hotelcode_url($sample);
same('confirmed', $r['status'], 'sample status');
same(5844, $r['hotel_code'], 'sample hotelCode');

$r = anytour_anex_hotelcode_evidence([
    $sample,
    'https://files.anextour.ru/hotel/egypt/hotel/another-slug/o999999?hotelCode=5844&size=large',
]);
same('confirmed', $r['status'], 'consistent evidence status');
same(5844, $r['hotel_code'], 'consistent evidence code');
same(2, $r['confirmed_urls'], 'consistent evidence count');

same('conflicting_codes', anytour_anex_hotelcode_evidence([
    $sample,
    'https://files.anextour.ru/hotel/egypt/hotel/x/o1?hotelCode=5845',
])['status'], 'conflicting evidence');

same('invalid_evidence', anytour_anex_hotelcode_evidence([
    $sample,
    'https://example.com/hotel/x?hotelCode=5844',
])['status'], 'mixed host');

same('invalid_url', anytour_anex_hotelcode_url(
    'http://files.anextour.ru/hotel/x?hotelCode=5844'
)['status'], 'https only');
same('invalid_url', anytour_anex_hotelcode_url(
    'https://agent.anextour.ru/search/tour?HOTELLIST=5844'
)['status'], 'operator link is not hotelCode evidence');
same('invalid_url', anytour_anex_hotelcode_url(
    'https://files.anextour.ru/hotel/x?hotelCode=5844&token=secret'
)['status'], 'credential query rejected');
same('ambiguous_code', anytour_anex_hotelcode_url(
    'https://files.anextour.ru/hotel/x?hotelCode=5844&hotelCode=5844'
)['status'], 'duplicate code rejected');
same('invalid_code', anytour_anex_hotelcode_url(
    'https://files.anextour.ru/hotel/x?hotelCode=0'
)['status'], 'zero code rejected');
same('no_code', anytour_anex_hotelcode_url(
    'https://files.anextour.ru/hotel/x/o5844?size=large'
)['status'], 'slug is not identity');
same('insufficient_evidence', anytour_anex_hotelcode_evidence([
    'https://files.anextour.ru/hotel/x/o5844?size=large'
])['status'], 'no query identity');

echo "anex-hotelcode-evidence-test: ok\n";
