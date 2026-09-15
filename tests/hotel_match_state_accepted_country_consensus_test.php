<?php
declare(strict_types=1);

putenv('MATCH_STATE_ACCEPTED_COUNTRY_TEST_LIBRARY=1');
require_once __DIR__ . '/../scripts/diagnostics/hotel_match_state_accepted_country_consensus.php';

function msac_t_assert(bool $cond, string $msg): void { if (!$cond) throw new RuntimeException($msg); }
function msac_t_row(string $id, string $state, int $country, int $local, ?string $name = null): array {
    $source = ['stateKey' => $state];
    if ($name !== null) $source['name'] = $name;
    return [
        'external_hotel_id' => $id,
        'local_hotel_id' => $local,
        'local_country_id' => $country,
        'evidence_json' => json_encode(['source' => $source], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
    ];
}
function msac_t_observation(string $id, string $state, string $name): array {
    return [
        'external_hotel_id' => $id,
        'evidence_json' => json_encode(['source' => ['stateKey' => $state, 'name' => $name]], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
    ];
}

$core = [1 => 'Египет', 4 => 'Турция', 8 => 'Мальдивы'];

$accepted = [];
for ($i = 0; $i < 8; $i++) $accepted[] = msac_t_row((string)(100 + $i), '73', 8, 1000 + $i, 'Anchor ' . $i);
$r = msac_accepted_country_consensus($accepted, [], $core);
msac_t_assert(isset($r['inferred']['73']), 'eight unanimous accepted anchors should infer');
msac_t_assert((int)$r['inferred']['73']['country_id'] === 8, 'winner country mismatch');
msac_t_assert((int)$r['inferred']['73']['accepted_other_country_count'] === 0, 'unexpected cross-country count');

$accepted7 = array_slice($accepted, 0, 7);
$r = msac_accepted_country_consensus($accepted7, [], $core);
msac_t_assert(!isset($r['inferred']['73']), 'seven anchors must not infer');
msac_t_assert(($r['rejected']['73']['reason'] ?? '') === 'insufficient_accepted_anchors', 'low anchor reason');

$mixed = $accepted;
$mixed[] = msac_t_row('999', '73', 4, 1999, 'Wrong Country');
$r = msac_accepted_country_consensus($mixed, [], $core);
msac_t_assert(!isset($r['inferred']['73']), 'accepted cross-country split must block');
msac_t_assert(($r['rejected']['73']['reason'] ?? '') === 'accepted_cross_country_split', 'split reason');

$contradict = [msac_t_observation('x1', '73', 'Example Turkey')];
$r = msac_accepted_country_consensus($accepted, $contradict, $core);
msac_t_assert(!isset($r['inferred']['73']), 'explicit country contradiction must block');
msac_t_assert(($r['rejected']['73']['reason'] ?? '') === 'explicit_country_contradiction', 'explicit contradiction reason');

$allSame = [msac_t_observation('x2', '73', 'Example Maldives')];
$r = msac_accepted_country_consensus($accepted, $allSame, $core);
msac_t_assert(isset($r['inferred']['73']), 'matching explicit marker should preserve inference');

$aliases = msc_country_aliases($core);
$hotels = [
    10 => ['id'=>10,'country_id'=>8,'name'=>'Sun Aqua Vilu Reef','latitude'=>3.0,'longitude'=>72.9,'region_name'=>'','subregion_name'=>''],
    11 => ['id'=>11,'country_id'=>8,'name'=>'Other Island Resort','latitude'=>4.0,'longitude'=>73.0,'region_name'=>'','subregion_name'=>''],
];
$forms = [10 => ['Sun Aqua Vilu Reef'], 11 => ['Other Island Resort']];
[$exact, $tokens] = msc_countryless_indexes($hotels, $forms, $aliases);
$sel = msc_countryless_select(['Sun Aqua Vilu Reef Maldives'], 8, [], $hotels, $forms, $aliases, $exact, $tokens);
msac_t_assert(($sel['route'] ?? '') === 'auto_accept_candidate', 'country suffix exact should accept');
msac_t_assert((int)($sel['target'] ?? 0) === 10, 'country suffix target');
msac_t_assert(($sel['reason'] ?? '') === 'unique_exact_after_country_geography_strip', 'country suffix reason');

$hotelsFar = $hotels;
$selFar = msc_countryless_select(['Sun Aqua Vilu Reef Maldives'], 8, [['latitude'=>20.0,'longitude'=>20.0]], $hotelsFar, $forms, $aliases, $exact, $tokens);
msac_t_assert(($selFar['route'] ?? '') === 'hard_conflict', 'far coordinate must block');
msac_t_assert(str_contains((string)($selFar['reason'] ?? ''), 'coordinate_conflict_gt5km'), 'far coordinate reason');

echo "MATCH_STATE_ACCEPTED_COUNTRY_TEST_OK\n";
