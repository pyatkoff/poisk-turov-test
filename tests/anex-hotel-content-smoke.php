<?php
declare(strict_types=1);
require_once __DIR__ . '/../scripts/diagnostics/anex_search3_hotel_content.php';

function content_check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException('ANEX content smoke: ' . $message);
}

// Documented Hotels_DETAILS fields; the original SAMO sample uses HTTP photos.
$sample = ['id' => '827411', 'name' => 'Arba', 'address' => '', 'description' => '',
    'latitude' => '', 'longitude' => '', 'url' => 'http://arba-hotel.uz/',
    'attributes' => [['groupKey' => '2', 'group' => 'Вид', 'id' => '59',
        'name' => 'Семейный', 'type' => '5', 'value' => '1']],
    'rooms' => [['id' => '4', 'name' => 'STANDARD ROOM', 'description' => '',
        'attributes' => [['id' => '2', 'name' => 'WiFi', 'type' => '5', 'value' => '0']]]],
    'photos' => [['url' => 'http://touroperator.tld/data/hotel/827411_1_photo_17267133.jpg', 'note' => '']]];
$result = anytour_anex_hotel_content($sample, 827411);
content_check($result['status'] === 'ok' && $result['content']['id'] === 827411, 'documented string ID accepted');
content_check($result['content']['rooms'][0]['attributes'][0]['value'] === '0', 'false supplier attribute retained');
content_check($result['content']['attributes'][0]['groupKey'] === 2, 'documented group retained');
content_check($result['content']['photos'] === [], 'HTTP is not silently upgraded to HTTPS');
content_check(!isset($result['content']['url']) && $result['availability']['description'] === 'empty'
    && $result['availability']['note'] === 'missing' && $result['availability']['photos'] === 'filtered', 'unknown/missing/empty/filtered distinct');
foreach ([null, 827412, '827411.0', 827411.0, '0827411', true] as $id) {
    $mismatch = anytour_anex_hotel_content(['id' => $id, 'description' => 'wrong hotel'], 827411);
    content_check($mismatch['status'] === 'id_mismatch' && $mismatch['content'] === []
        && $mismatch['source_sha256'] === null, 'identity fails closed');
}
content_check(anytour_anex_hotel_content([], 827411)['status'] === 'empty', 'empty supplier response explicit');

$clean = anytour_anex_hotel_content(['id' => 827411, 'description' =>
    '<p>У моря&nbsp;<b>уютно</b></p><script>hidden()</script>&lt;img src=x onerror=alert(1)&gt;<div>Пляж</div>',
    'latitude' => '0', 'longitude' => '-180',
    'photos' => [['url' => 'https://images.example.com/hotel/1.jpg?width=800', 'note' => '<b>Бассейн</b>']]], 827411);
content_check($clean['content']['description'] === 'У моря уютно Пляж', 'HTML/entities/scripts become plain text');
content_check($clean['content']['latitude'] === 0.0 && $clean['content']['longitude'] === -180.0, 'coordinate boundary and zero retained');
content_check(count($clean['content']['photos']) === 1 && $clean['content']['photos'][0]['note'] === 'Бассейн', 'safe HTTPS photo and caption');
foreach (['private-credential', rawurlencode('private-credential'),
    'private&#45;credential', 'private-<b>credential</b>', 'oauth_token=unknown', 'oauth_<b>token</b>=unknown'] as $echo) {
    $redacted = anytour_anex_hotel_content(['id' => 827411, 'description' => $echo,
        'photos' => [['url' => 'https://images.example.com/' . $echo, 'note' => $echo]]], 827411, 'private-credential');
    content_check($redacted['content']['description'] === '' && $redacted['content']['photos'] === [], 'credential echo is never retained');
}
foreach (['http://images.example.com/a.jpg', 'javascript:alert(1)', 'data:image/png;base64,AA',
    'https://user:pass@images.example.com/a.jpg', 'https://localhost/a', 'https://127.0.0.1/a',
    'https://10.0.0.1/a', 'https://[::1]/a', 'https://2130706433/a', 'https://127.1/a',
    'https://127.0.0.1.nip.io/a', 'https://host.internal/a', 'https://images.example.com:8443/a',
    'https://images.example.com/a?token=private', 'https://images.example.com/a?%74oken=private',
    'https://images.example.com/a?X-Amz-Credential=private', 'https://images.example.com/a?sig=private',
    'https://images.example.com/a?authorization=private', 'https://images.example.com/a?keyid=private',
    'https://images.example.com/a#secret', 'https://images.example.com/%0afoo',
    "https://images.example.com/\\@localhost/a"] as $url) {
    content_check(anytour_anex_content_photo_url($url, '') === '', 'unsafe photo URL rejected');
}
content_check(anytour_anex_content_text('See https://host.example.com/?token=abc and beach', 600, '')
    === 'See and beach', 'plain text does not carry URLs');
content_check(anytour_anex_content_text("bad\xFFutf8", 600, '') === '', 'invalid UTF-8 dropped');
$encoded = '%61';
for ($i = 0; $i < 10; ++$i) $encoded = rawurlencode($encoded);
content_check(anytour_anex_content_text($encoded, 600, '') === '', 'excessive nested encoding dropped');

$many = ['id' => 827411, 'description' => str_repeat('я', 16001), 'latitude' => 91,
    'longitude' => INF, 'photos' => [], 'rooms' => [], 'attributes' => []];
for ($i = 1; $i <= 250; ++$i) {
    $many['photos'][] = ['url' => 'https://images.example.com/' . $i . '.jpg', 'note' => str_repeat('я', 601)];
    $many['attributes'][] = ['name' => 'Pool', 'type' => 5, 'value' => '0', 'ignored' => 'raw'];
    $many['rooms'][] = ['id' => $i, 'name' => 'Room', 'description' => str_repeat('я', 2401),
        'attributes' => $many['attributes']];
}
$bounded = anytour_anex_hotel_content($many, 827411);
content_check(count($bounded['content']['photos']) === 40 && count($bounded['content']['rooms']) === 30
    && count($bounded['content']['attributes']) === 80, 'retained collection bounds');
content_check(strlen($bounded['content']['description']) === 16000
    && strlen($bounded['content']['photos'][0]['note']) === 600
    && strlen($bounded['content']['rooms'][0]['description']) === 2400, 'UTF-8 byte text bounds');
content_check($bounded['content']['latitude'] === null && $bounded['content']['longitude'] === null, 'invalid coordinates dropped');
content_check(!isset($bounded['content']['attributes'][0]['ignored']), 'nested unknown fields excluded');
$same = anytour_anex_hotel_content(array_reverse($many, true) + ['unknown' => 'discarded'], 827411);
content_check($same['source_sha256'] === $bounded['source_sha256'], 'normalized content hash ignores raw order and unknown fields');
$many['description'] = 'changed';
content_check(anytour_anex_hotel_content($many, 827411)['source_sha256'] !== $bounded['source_sha256'], 'normalized change changes hash');
echo "ANEX hotel content smoke passed\n";
