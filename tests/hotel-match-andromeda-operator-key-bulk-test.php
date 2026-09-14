<?php

declare(strict_types=1);

require_once __DIR__ . '/../scripts/diagnostics/hotel_match_andromeda_operator_key_bulk.php';

function hmb_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

hmb_assert(hmb_country_key('Египет') === 'egypt', 'egypt country');
hmb_assert(hmb_country_key('TÜRKİYE') === 'turkey', 'turkey country');
hmb_assert(hmb_country_key('Шри-Ланка') === 'srilanka', 'srilanka country');

$img = hmb_image_identity('https://cdn.example/hotel/5.36124.651573.jpg?x=1');
hmb_assert($img === ['operator' => 5, 'anex' => 36124, 'andromeda' => 651573], 'image identity');
hmb_assert(hmb_image_identity('https://cdn.example/hotel/plain.jpg') === null, 'plain image rejected');

$rows = [];
hmb_collect_offer_rows([
    'outer' => [
        ['operatorKey' => 5, 'hotelKey' => 651573, 'hotel' => 'A', 'original' => ['hotelKey' => 36124]],
        ['operatorKey' => 7, 'hotelKey' => 100, 'hotel' => 'B', 'original' => ['hotelKey' => 200]],
    ],
], $rows);
hmb_assert(count($rows) === 2, 'recursive offer rows');

hmb_assert(count(HM_BULK_DATES) === 8, 'date grid');
hmb_assert(count(HM_BULK_NIGHTS) === 4, 'night grid');
hmb_assert(count(array_unique(HM_BULK_DATES)) === count(HM_BULK_DATES), 'dates unique');
hmb_assert(count(array_unique(HM_BULK_NIGHTS)) === count(HM_BULK_NIGHTS), 'nights unique');

$source = file_get_contents(__DIR__ . '/../scripts/diagnostics/hotel_match_andromeda_operator_key_bulk.php');
hmb_assert(is_string($source), 'source read');
hmb_assert(strpos($source, 'api.tourvisor.ru') === false, 'new Tourvisor forbidden');
hmb_assert(strpos($source, 'tourvisor.ru/xml') === false, 'legacy Tourvisor forbidden');
hmb_assert(strpos($source, 'AnyTourAnexClient') === false, 'direct ANEX client forbidden in this family');
hmb_assert(strpos($source, 'START TRANSACTION READ ONLY') !== false, 'current DB read-only transaction required');
foreach (['INSERT ', 'UPDATE ', 'DELETE ', 'REPLACE ', 'ALTER ', 'DROP ', 'TRUNCATE '] as $needle) {
    hmb_assert(stripos($source, $needle) === false, 'mutation token forbidden: ' . trim($needle));
}

echo "HOTEL_MATCH_ANDROMEDA_OPERATOR_KEY_BULK_OK\n";
