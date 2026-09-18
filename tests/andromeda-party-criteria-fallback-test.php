<?php
declare(strict_types=1);

require_once dirname(__DIR__).'/app/integrations/andromeda-normalizer.php';

function assertSameValue($expected, $actual, string $message): void {
    if ($expected !== $actual) {
        throw new RuntimeException($message.' expected='.var_export($expected, true).' actual='.var_export($actual, true));
    }
}

function criteria(): array {
    return [
        'TOWNFROMINC'=>'1',
        'STATEINC'=>'4',
        'CHECKIN_BEG'=>'20261010',
        'CHECKIN_END'=>'20261010',
        'ADULT'=>'2',
        'CHILD'=>'1',
        'NIGHTS_FROM'=>'7',
        'NIGHTS_TILL'=>'7',
        'CURRENCYINC'=>'1',
    ];
}

function validRow(string $id): array {
    return [
        'id'=>$id,
        'hotelKey'=>'100',
        'operatorKey'=>'20',
        'isOperatorHotelKey'=>'0',
        'price'=>'100000',
        'currency'=>'RUB',
        'currencyKey'=>'1',
        'checkIn'=>'10.10.2026',
        'nights'=>'7',
        'hotel'=>'Hotel',
        'operator'=>'Biblio Globus',
        'meal'=>'AI',
        'mealKey'=>'1',
        'room'=>'STD',
        'htplace'=>'2AD+1CH',
        'adult'=>'2',
        'child'=>'1',
    ];
}

function normalizeOne(array $row, string $searchRef): array {
    return AnyTourAndromedaNormalizer::page(
        ['PAGE'=>1, 'PAGES_COUNT'=>1, 'PRICES'=>[$row]],
        criteria(),
        $searchRef,
        1
    );
}

$missingAdult=validRow('missing-adult');
unset($missingAdult['adult']);
$result=normalizeOne($missingAdult, 'party_missing_adult');
assertSameValue(1, count($result['offers']), 'missing adult must normalize from exact criteria');
assertSameValue([], $result['rejected'], 'missing adult must not be rejected');
assertSameValue(2, $result['offers'][0]['adults'], 'normalized adult count');
assertSameValue(1, $result['offers'][0]['children'], 'child count stays supplier-confirmed');
assertSameValue('complete', $result['status'], 'single-page normalized result remains complete');

$missingChild=validRow('missing-child');
unset($missingChild['child']);
$result=normalizeOne($missingChild, 'party_missing_child');
assertSameValue(1, count($result['offers']), 'missing child must normalize from exact criteria');
assertSameValue([], $result['rejected'], 'missing child must not be rejected');
assertSameValue(2, $result['offers'][0]['adults'], 'adult count stays supplier-confirmed');
assertSameValue(1, $result['offers'][0]['children'], 'normalized child count');

$missingBoth=validRow('missing-both');
unset($missingBoth['adult'], $missingBoth['child']);
$result=normalizeOne($missingBoth, 'party_missing_both');
assertSameValue(1, count($result['offers']), 'both omitted party echoes must normalize from exact criteria');
assertSameValue(2, $result['offers'][0]['adults'], 'normalized adult count when both omitted');
assertSameValue(1, $result['offers'][0]['children'], 'normalized child count when both omitted');

$explicitAdultMismatch=validRow('adult-mismatch');
$explicitAdultMismatch['adult']='3';
$result=normalizeOne($explicitAdultMismatch, 'party_adult_mismatch');
assertSameValue(0, count($result['offers']), 'explicit adult mismatch must remain fail-closed');
assertSameValue('PARTY_OR_NIGHTS_MISMATCH', $result['rejected'][0]['reason'] ?? null, 'explicit adult mismatch reason');

$explicitChildMismatch=validRow('child-mismatch');
$explicitChildMismatch['child']='0';
$result=normalizeOne($explicitChildMismatch, 'party_child_mismatch');
assertSameValue(0, count($result['offers']), 'explicit child mismatch must remain fail-closed');
assertSameValue('PARTY_OR_NIGHTS_MISMATCH', $result['rejected'][0]['reason'] ?? null, 'explicit child mismatch reason');

$explicitNullAdult=validRow('adult-null');
$explicitNullAdult['adult']=null;
$result=normalizeOne($explicitNullAdult, 'party_adult_null');
assertSameValue(0, count($result['offers']), 'explicit null adult must not receive criteria fallback');
assertSameValue('INVALID_ID', $result['rejected'][0]['reason'] ?? null, 'explicit null adult remains invalid');

echo "OK\n";
