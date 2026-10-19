<?php
declare(strict_types=1);
require_once __DIR__ . '/../scripts/diagnostics/anex_hotelcode_current_queue.php';

function ahq_assert(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
}

$review = [
    'external_id'=>5844,
    'country_id'=>1,
    'observed'=>true,
    'search_count'=>9,
    'last_seen_utc'=>'2026-09-11 00:00:00',
    'reason'=>'tourvisor_anex_operator_link_hotelcode_needed',
    'source_names'=>['SHARMING INN HOTEL'],
    'source_places'=>['SHARM EL SHEIKH'],
    'candidate_ids'=>[123],
];
$seed = ahq_seed_from_review($review, [[
    'media'=>'https://files.anextour.ru/hotel/egypt/hotel/sharming-inn-hotel-sharm-el-sheikh/o417822?hotelCode=5844',
]]);
ahq_assert($seed['evidence_bucket']==='saved_hotelcode_evidence','confirmed bucket');
ahq_assert($seed['saved_hotelcode']===5844,'confirmed code');
ahq_assert($seed['saved_hotelcode_matches_current_anex_id']===true,'same id relation');
ahq_assert($seed['saved_url_count']===1,'saved url count');

$conflict = ahq_seed_from_review($review, [[
    'one'=>'https://files.anextour.ru/hotel/a?hotelCode=5844',
    'two'=>'https://files.anextour.ru/hotel/b?hotelCode=9999',
]]);
ahq_assert($conflict['evidence_bucket']==='saved_hotelcode_conflict','conflict fail closed');
ahq_assert($conflict['saved_hotelcode']===null,'conflict no code');

$invalid = ahq_seed_from_review($review, [[
    'bad'=>'https://files.anextour.ru/hotel/a?hotelCode=5844&token=secret',
]]);
ahq_assert($invalid['evidence_bucket']==='saved_hotelcode_invalid','credential evidence invalid');

$none = ahq_seed_from_review($review, [['name'=>'no persisted media url']]);
ahq_assert($none['evidence_bucket']==='operator_link_required','missing evidence queued');

$rows = [
    ['anex_hotel_id'=>5,'observed'=>false,'search_count'=>0,'evidence_bucket'=>'operator_link_required'],
    ['anex_hotel_id'=>8,'observed'=>true,'search_count'=>2,'evidence_bucket'=>'operator_link_required'],
    ['anex_hotel_id'=>4,'observed'=>true,'search_count'=>7,'evidence_bucket'=>'operator_link_required'],
    ['anex_hotel_id'=>3,'observed'=>true,'search_count'=>7,'evidence_bucket'=>'saved_hotelcode_conflict'],
];
ahq_sort_seeds($rows);
ahq_assert(array_column($rows,'anex_hotel_id')===[3,4,8,5],'priority order');

$source = file_get_contents(__DIR__ . '/../scripts/diagnostics/anex_hotelcode_current_queue.php');
ahq_assert(is_string($source) && str_contains($source,"START TRANSACTION READ ONLY"),'read only transaction');
ahq_assert(!preg_match('/\b(?:INSERT|UPDATE|DELETE|REPLACE|ALTER|DROP|TRUNCATE)\b\s+/i',$source),'no database write statements');
ahq_assert(str_contains($source,"historical_operations_replayed'=>false"),'no replay guard');
ahq_assert(str_contains($source,"AHQ_OPERATION = 'hotel-match-anex-hotelcode-current-queue-1971-20260911-v1'"),'immutable operation id');

echo "anex-hotelcode-current-queue-test: ok\n";
