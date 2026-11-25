<?php
declare(strict_types=1);
ini_set('display_errors', '0');
ini_set('log_errors', '0');

function requireProof(bool $value, string $failure): void {
    if (!$value) {
        throw new RuntimeException($failure);
    }
}

const TARGET = 'commercial-monitor-price-h64957-2200c57affb7c4d9bb2585d2eeae65a6a208076210ba509565cb0fde77daff47';
const PRICE = 1563921;
const ORIGINAL = 45720745186;
const PRICE_STAMP = '25.11.2026, 03:17 МСК';
const FEED_ROOT = 'https://app.anytoour.ru/feed/hotel-posts-';
const MAX_POST = 'https://max.ru/channel_anytour/AaHo8tYWbs0';
const TG_POST = 'https://t.me/any_toour/676';

function publicRead(string $url): array {
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 6, CURLOPT_TIMEOUT => 18,
        CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => ['Cache-Control: no-cache', 'Pragma: no-cache'],
        CURLOPT_USERAGENT => 'Mozilla/5.0',
    ]);
    $content = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    return [$status, is_string($content) ? $content : ''];
}

function cleanDisplay(string $x): string {
    $x = preg_replace('/<br\s*\/?>/i', "\n", $x);
    $x = html_entity_decode(strip_tags($x), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $x = str_replace(["\u{FE0F}", "\u{00A0}", "\u{202F}", "\r"], ['', ' ', ' ', ''], $x);
    return trim($x);
}

function tailRead(string $path): array {
    requireProof(realpath($path) === $path, 'journal_path_changed');
    $lock = fopen($path, 'rb');
    requireProof(is_resource($lock) && flock($lock, LOCK_SH | LOCK_NB), 'journal_write_active');
    try {
        $size = fstat($lock)['size'];
        $take = min($size, 4194304);
        requireProof(fseek($lock, $size - $take) === 0, 'journal_seek_failed');
        $raw = stream_get_contents($lock);
        requireProof(is_string($raw) && str_ends_with($raw, "\n"), 'journal_incomplete');
        $raw = substr($raw, 0, -1);
        $split = strrpos($raw, "\n");
        requireProof($split !== false || $take === $size, 'journal_tail_too_large');
        return json_decode($split === false ? $raw : substr($raw, $split + 1), true, 64, JSON_THROW_ON_ERROR);
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

$out = ['ok'=>false, 'source_run'=>ORIGINAL, 'mutations'=>0, 'at'=>gmdate('c')];
try {
    $base = '/var/www/anytoour/data/channel-post-operations';
    $global = tailRead($base . '/monitor-price-after-collection-v1.jsonl');
    $state = tailRead($base . '/' . TARGET . '/receipt.jsonl');
    requireProof(($global['completed'][64957]['operation'] ?? null) === TARGET && array_key_exists('pending', $global) && $global['pending'] === null
        && ($state['complete'] ?? null) === true && ($state['operation'] ?? null) === TARGET
        && count($state['posts']) === 2, 'original_completion_missing');
    $posts = [];
    foreach ($state['posts'] as $item) {
        $receipt = $item['receipt'];
        requireProof($item['state'] === 'confirmed' && $receipt['body_photo_button_verified'] && $receipt['price_link_verified']
            && $receipt['monitor_price']['observed_at'] === '2026-11-25T00:17:19Z'
            && (int)$receipt['monitor_price']['price'] === PRICE
            && hash('sha256', $receipt['text']) === $receipt['text_sha256'], 'original_post_receipt_changed');
        $posts[$receipt['platform']] = $receipt;
    }
    requireProof($posts['max']['post_url'] === MAX_POST && $posts['telegram']['post_url'] === TG_POST
        && $posts['max']['version_id'] === 16015 && $posts['telegram']['version_id'] === 16016
        && $posts['telegram']['photo_signature'] === stableTelegramPhoto($state), 'original_provider_identity_changed');

    $out['feeds'] = [];
    foreach (['max'=>['max_64957'], 'telegram'=>['tg_64957'], 'all'=>['tg_64957','max_64957']] as $feed => $ids) {
        [$http, $xml] = publicRead(FEED_ROOT . $feed . '.xml');
        requireProof($http === 200 && stripos($xml, '<!DOCTYPE') === false, 'public_feed_http_failed');
        $root = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET);
        $rows = $root->shop->offers->offer;
        requireProof(count($rows) === ($feed === 'all' ? 2110 : 1055), 'public_feed_count_changed');
        foreach ($ids as $id) {
            $found = $root->xpath('//offer[@id="' . $id . '"]');
            requireProof(count($found) === 1 && (string)$found[0]->price === (string)PRICE
                && (string)$found[0]->description === $posts[str_starts_with($id, 'tg_') ? 'telegram' : 'max']['text'],
                'public_feed_row_changed');
        }
        $out['feeds'][$feed] = ['count'=>count($rows), 'sha256'=>hash('sha256', $xml)];
    }

    [$status, $html] = publicRead(MAX_POST);
    $out['max'] = ['http'=>$status, 'new_price'=>$status === 200 && str_contains(cleanDisplay($html), '1 563 921')];
    requireProof($out['max']['new_price'], 'public_max_price_missing');

    $out['telegram_public'] = [];
    foreach (['?embed=1', '?embed=1&mode=tme', '?embed=1&_price_verify=' . time(), ''] as $suffix) {
        [$status, $html] = publicRead(TG_POST . $suffix);
        $caption = '';
        if (preg_match('/<div[^>]+tgme_widget_message_text[^>]*>(.*?)<\/div>/s', $html, $match) === 1) {
            $caption = cleanDisplay($match[1]);
        }
        $amount = null;
        if (preg_match('/От\s*([\d ]+)\s*₽/u', $caption, $match) === 1) {
            $amount = (int)str_replace(' ', '', $match[1]);
        }
        $proof = [
            'http'=>$status, 'exact_post'=>str_contains($html, 'data-post="any_toour/676"'), 'amount'=>$amount,
            'observed_marker'=>str_contains($caption, PRICE_STAMP),
            'departure'=>str_contains($caption, '11.12.2026'),
            'same_caption'=>$caption === cleanDisplay($posts['telegram']['text']),
            'photo'=>str_contains($html, 'tgme_widget_message_photo_wrap'),
            'same_button'=>str_contains(html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8'), $posts['telegram']['button_url']),
        ];
        $out['telegram_public'][] = $proof;
        if ($status === 200 && $proof['exact_post'] && $amount === PRICE && $proof['observed_marker']
            && $proof['departure'] && $proof['photo'] && $proof['same_button']) {
            $out['ok'] = true;
            break;
        }
    }
    requireProof($out['ok'], 'public_telegram_observation_still_unconfirmed');
} catch (Throwable $e) {
    $out['error'] = preg_match('/\A[a-z_]+\z/D', $e->getMessage()) ? $e->getMessage() : 'public_read_failed';
}
$out['finished_at'] = gmdate('c');
echo 'PRICE_TARGET_PUBLIC=' . json_encode($out, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
exit($out['ok'] ? 0 : 2);

function stableTelegramPhoto(array $state): string {
    $app = '/var/www/anytoour/data/www/app.anytoour.ru';
    requireProof(chdir($app), 'readback_root_changed');
    ob_start();
    try {
        require $app . '/config.php';
        require_once $app . '/services/ChannelPostRuntime.php';
    } finally {
        ob_end_clean();
    }
    foreach ($state['posts'] as $item) {
        if ($item['plan']['platform'] !== 'telegram') {
            continue;
        }
        $data = $item['provider_response'];
        requireProof(($data['http'] ?? null) === 200 && ($data['data']['ok'] ?? null) === true, 'telegram_edit_receipt_invalid');
        $provider = ChannelPostIncomingAdapter::telegramSnapshot($data['data']['result']);
        requireProof(is_array($provider) && count($provider['attachments']) === 1
            && ($provider['post_url'] ?? null) === TG_POST, 'telegram_provider_photo_missing');
        $photo = $provider['attachments'][0];
        $signature = $photo['provider'] . ':' . ($photo['photo_id'] ?? $photo['file_unique_id']);
        requireProof($signature === $item['plan']['photo_signature']
            && ChannelPostIncomingAdapter::containsOnlyButton($provider, $item['receipt']['button_url'], '✈️ Узнать стоимость тура'),
            'telegram_provider_photo_cta_changed');
        return $signature;
    }
    throw new RuntimeException('telegram_provider_snapshot_missing');
}
