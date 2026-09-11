<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/integrations/anex-client.php';
require_once __DIR__ . '/../scripts/diagnostics/anex_hotelcode_evidence.php';
require_once __DIR__ . '/../scripts/diagnostics/hotel_match_anex_photos_hotelcode_evidence.php';

function tassert($condition, string $message): void { if (!$condition) throw new RuntimeException($message); }

$payload = [[
    'hotelKey'=>8121,
    'photos'=>[
        ['url'=>'https://files.anextour.ru/hotel/turkey/hotel/example/o123?hotelCode=5844'],
        ['big'=>'https://files.anextour.ru/hotel/turkey/hotel/example/o124?foo=1&hotelCode=5844'],
    ],
]];
$p = hmaph_parse_photo_payload($payload, [8121], [8121,5844]);
tassert($p['counts']['confirmed'] === 1, 'confirmed_count');
tassert($p['counts']['confirmed_distinct_key'] === 1, 'distinct_count');
tassert($p['evidence'][0]['hotel_code'] === 5844, 'hotel_code');
tassert($p['evidence'][0]['hotel_code_known_current_anex_id'] === true, 'known_code');
tassert($p['evidence'][0]['hotel_code_equals_response_hotel_key'] === false, 'no_equal_assumption');

$conflict = [['hotelKey'=>10,'photos'=>[
    'https://files.anextour.ru/hotel/x/a?hotelCode=11',
    'https://files.anextour.ru/hotel/x/b?hotelCode=12',
]]];
$c = hmaph_parse_photo_payload($conflict, [10], [10,11,12]);
tassert($c['counts']['conflicting'] === 1, 'conflict_fail_closed');

$outside = [['hotelKey'=>99,'photos'=>['https://example.org/a?hotelCode=99']]];
$o = hmaph_parse_photo_payload($outside, [10], [10,99]);
tassert($o['counts']['unexpected_hotel_key'] === 1, 'unexpected_key');
tassert($o['missing_requested_ids'] === [10], 'missing_target');

$requests = [];
$transport = static function(string $url, array $options) use (&$requests): array {
    $requests[] = $url;
    parse_str((string)parse_url($url, PHP_URL_QUERY), $q);
    $ids = explode(',', (string)$q['HOTELS']);
    $rows = [];
    foreach ($ids as $id) {
        $n=(int)$id;
        $rows[]=['hotelKey'=>$n,'photos'=>['https://files.anextour.ru/hotel/test/o'.$n.'?hotelCode='.($n+1000)]];
    }
    return ['status'=>200,'body'=>json_encode(['Hotels_PHOTOS'=>$rows], JSON_THROW_ON_ERROR)];
};
$client = new AnyTourAnexClient('unit-test-token', $transport);
$ids = range(1, 31);
$plan = ['status'=>'ready','operation_id'=>HMAPH_OPERATION,'target_ids'=>$ids,'target_sha256'=>hash('sha256', hmaph_json($ids)),'known_anex_ids'=>array_merge($ids, range(1001,1031))];
$r = hmaph_collect($client, $plan);
tassert($r['supplier_calls'] === 2, 'batch_30');
tassert($r['target_count'] === 31, 'target_count');
tassert($r['counts']['confirmed'] === 31, 'all_confirmed');
tassert($r['database_writes'] === 0 && $r['mapping_writes'] === 0, 'no_writes');
tassert(count($requests) === 2, 'request_count');

echo "hotel-match-anex-photos-hotelcode-evidence-test: ok\n";
