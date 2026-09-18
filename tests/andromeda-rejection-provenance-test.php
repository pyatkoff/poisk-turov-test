<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/integrations/andromeda-normalizer.php';

function arp_assert(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

function arp_criteria(): array {
    return [
        'TOWNFROMINC' => '1', 'STATEINC' => '2',
        'CHECKIN_BEG' => '20261010', 'CHECKIN_END' => '20261010',
        'ADULT' => '2', 'CHILD' => '0',
        'NIGHTS_FROM' => '7', 'NIGHTS_TILL' => '7', 'CURRENCYINC' => '1',
    ];
}

function arp_row(string $operator, string $operatorKey): array {
    return [
        'id' => 'tour-1', 'hotelKey' => '100', 'operatorKey' => $operatorKey,
        'isOperatorHotelKey' => '0', 'price' => '100000', 'currency' => 'RUB',
        'currencyKey' => '1', 'checkIn' => '10.10.2026', 'nights' => '7',
        'hotel' => 'Example Hotel', 'operator' => $operator, 'meal' => 'AI',
        'mealKey' => '1', 'room' => 'Standard Room', 'htplace' => '2AD',
        'adult' => '2', 'child' => '0',
    ];
}

function arp_page(array $rows): array {
    return AnyTourAndromedaNormalizer::page(
        ['PAGE' => 1, 'PAGES_COUNT' => 1, 'PRICES' => $rows],
        arp_criteria(), 'rejection-provenance', 1
    );
}

// A malformed row owned by direct ANEX is retained as a sanitized, non-blocking diagnostic.
$excluded = arp_row('ANEX', '9');
unset($excluded['room']);
$page = arp_page([$excluded]);
arp_assert($page['status'] === 'complete', 'excluded rejection blocked completeness');
arp_assert($page['rejected'] === [], 'excluded rejection leaked into blocking list');
arp_assert(count($page['excluded_rejected']) === 1, 'excluded rejection missing');
$diag = $page['excluded_rejected'][0];
arp_assert($diag === [
    'index' => 0,
    'reason' => 'MISSING_FIELD',
    'ownership' => 'excluded_direct_or_tv',
    'missing_field' => 'room',
], 'excluded diagnostic mismatch');
arp_assert(!array_key_exists('row', $diag) && !array_key_exists('payload', $diag), 'raw supplier row leaked');

// Missing data on an Andromeda-owned operator stays fail-closed.
$owned = arp_row('FUN&SUN', '20');
unset($owned['room']);
$page = arp_page([$owned]);
arp_assert($page['status'] === 'partial', 'owned rejection became authoritative');
arp_assert(count($page['rejected']) === 1 && $page['excluded_rejected'] === [], 'owned rejection classification mismatch');
arp_assert($page['rejected'][0]['reason'] === 'MISSING_FIELD', 'owned rejection reason mismatch');
arp_assert($page['rejected'][0]['missing_field'] === 'room', 'owned missing field lost');
arp_assert($page['rejected'][0]['ownership'] === 'andromeda_owned', 'owned operator provenance lost');

// If operator identity itself is absent, ownership cannot be guessed.
$unknown = arp_row('FUN&SUN', '20');
unset($unknown['operatorKey'], $unknown['operator']);
$page = arp_page([$unknown]);
arp_assert($page['status'] === 'partial', 'unknown rejection became authoritative');
arp_assert(count($page['rejected']) === 1, 'unknown rejection missing');
arp_assert($page['rejected'][0]['reason'] === 'MISSING_FIELD', 'unknown rejection reason mismatch');
arp_assert($page['rejected'][0]['missing_field'] === 'operatorKey', 'unknown missing field mismatch');
arp_assert($page['rejected'][0]['ownership'] === 'unknown', 'unknown ownership guessed');

// A direct/TV marker still proves exclusion when the numeric operator key is missing.
$byName = arp_row('Coral Travel', '14');
unset($byName['operatorKey']);
$page = arp_page([$byName]);
arp_assert($page['status'] === 'complete', 'direct name marker blocked completeness');
arp_assert($page['rejected'] === [] && count($page['excluded_rejected']) === 1, 'direct name marker not excluded');
arp_assert($page['excluded_rejected'][0]['missing_field'] === 'operatorKey', 'direct missing operatorKey not retained');
arp_assert($page['excluded_rejected'][0]['ownership'] === 'excluded_direct_or_tv', 'direct name ownership mismatch');

echo "andromeda-rejection-provenance-test: OK\n";
