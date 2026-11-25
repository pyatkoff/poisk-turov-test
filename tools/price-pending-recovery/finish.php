<?php
const REPAIR_HOTEL = 64957;
const REPAIR_RUN = 45720745186;
const REPAIR_RECORD = '2200c57affb7c4d9bb2585d2eeae65a6a208076210ba509565cb0fde77daff47';
const REPAIR_CHILD_SHA = 'de5089dd4cdb3563342c7eb3a349b77e7334442e6f66b3713520eb826f823dc5';
const REPAIR_MANIFEST_SHA = 'a86a1a1b094aaa2f3f138cf0b1b5b23228998d3a25f96a8705a173870c33e6bc';
const REPAIR_APP_HEAD = 'c1a445f48fd514b9a4f883faecd20e5ecf1bc4fb';
const REPAIR_OLD_TEXT = 'c53e2ae36216345d78c294574f341ac4de232f3c33c160279791cd2a93ec43c7';
const REPAIR_OLD_RECORD = 'a72f7642108c8211b7a35c5f744f2a81080ed1a93e973e014892e1917e13a48b';
const REPAIR_BUTTON = '✈️ Узнать стоимость тура';
const REPAIR_JSON = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;
const REPAIR_FEEDS = [
    'hotel-posts-max.xml' => '3d03cd6a8f732f064356c8ae8720a6c314d50e45fc5f7083abe217a181e701c2',
    'hotel-posts-telegram.xml' => 'a96fd7d8934fb3ce08f46a48467a0b7048dd8501e54e92521bd6bfef13b2b456',
    'hotel-posts-all.xml' => 'ad94cd96e1bf3345136bae1833d7cc69683501435c475542fa96430cff59ab32',
];

function recoveryLock(string $path) {
    need(realpath($path) === $path && !is_link($path), 'original_journal_path_changed');
    $lock = fopen($path, 'r+b');
    need(is_resource($lock) && flock($lock, LOCK_EX | LOCK_NB), 'original_journal_busy');
    $open = fstat($lock);
    $named = lstat($path);
    need($open['dev'] === $named['dev'] && $open['ino'] === $named['ino'], 'original_journal_replaced');
    return $lock;
}

function recoveryGlobalTail($lock): array {
    $size = fstat($lock)['size'];
    $take = min($size, 4194304);
    need(fseek($lock, $size - $take) === 0, 'global_journal_seek_failed');
    $lines = stream_get_contents($lock, $take);
    need(is_string($lines) && str_ends_with($lines, "\n"), 'global_journal_incomplete');
    $end = substr($lines, 0, -1);
    $split = strrpos($end, "\n");
    need($split !== false || $take === $size, 'global_journal_tail_too_large');
    return json_decode($split === false ? $end : substr($end, $split + 1), true, 64, JSON_THROW_ON_ERROR);
}

function recoveryPublicTelegram(string $caption, string $button): void {
    $url = 'https://t.me/any_toour/676?embed=1&_price_check=' . time();
    $handle = curl_init($url);
    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => ['Cache-Control: no-cache'],
    ]);
    $html = curl_exec($handle);
    $status = curl_getinfo($handle, CURLINFO_HTTP_CODE);
    curl_close($handle);
    need($status === 200 && is_string($html) && str_contains($html, 'data-post="any_toour/676"')
        && str_contains($html, 'tgme_widget_message_photo_wrap')
        && preg_match('/<div[^>]+tgme_widget_message_text[^>]*>(.*?)<\/div>/s', $html, $match) === 1,
        'telegram_public_post_unverified');
    $text = preg_replace('/<br\s*\/?>/i', "\n", $match[1]);
    $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    // Provider widgets can add variation selectors and nonbreaking spaces.
    $normalize = static fn(string $s): string => trim(str_replace(["\u{FE0F}", "\u{00A0}", "\r"], ['', ' ', ''], $s));
    need($normalize($text) === $normalize($caption), 'telegram_public_caption_changed');
    need(str_contains(html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8'), $button),
        'telegram_public_cta_unverified');
}

function recoveryPlan(array $row, array $p, string $platform, array $price): array {
    $text = monitor_hook_draft($row['templates'][$platform], $price);
    return [
        'platform' => $platform, 'key' => $row['key'], 'logical_id' => $row['logical_id'],
        'message_id' => $p['external_message_id'], 'publication_id' => (int)$p['publication_id'],
        'old_version_id' => (int)$p['version_id'], 'token' => $p['start_payload'],
        'post_url' => $p['snapshot']['post_url'], 'photo_signature' => photoSignature($p['snapshot']),
        'text' => $text, 'update_text' => true, 'monitor_price' => $price,
        'price_link' => monitorEditorPriceLink($text, $platform, ChannelPostButtonTransport::buttonUrl($p)),
    ];
}

function recoveryRegistryReceipt($registry, array $previous, array $snap, array $plan, string $op, int $editStarted): array {
    $id = $plan['publication_id'];
    $platform = $plan['platform'];
    $current = $registry->publication($id);
    need($current === $previous, 'registry_changed_before_observation');
    $snap['content_hash'] = ChannelPostIncomingAdapter::contentFingerprint($snap, '', (string)$previous['channel']['bot_url']);
    $metadata = priceObservationMetadata(array_replace($previous['snapshot']['metadata'] ?? [], $snap['metadata'] ?? []), [], $plan['monitor_price']);
    $metadata['source_event_id'] = $op . ($platform === 'max' ? ':max:' . $plan['key'] : ':telegram:' . $plan['message_id']);
    $metadata['observation_kind'] = 'authorized_admin_content_edit';
    if ($platform === 'max') {
        $metadata['operation_observed_at_ms'] = (string)(int)floor(microtime(true) * 1000);
        $metadata['source_revision_ms'] = (string)$editStarted;
        $metadata['revision_clock_origin'] = 'authorized_edit_request_start';
    }
    $snap['metadata'] = $metadata;
    $bot = $platform === 'max' ? 'id9704048781_1_bot' : 'anyru_bot';
    $registered = $registry->registerPublication('anytour', $platform, $bot, $previous['channel_id'], $plan['message_id'], $snap);
    need(is_array($registered) && (int)$registered['publication_id'] === $id
        && (int)$registered['version_id'] > $plan['old_version_id'], 'provider_observation_not_registered');
    $current = $registry->publication($id);
    if ($current['state'] === 'pending') {
        need($registry->confirmObservedButton($id, $current['version_id'], [
            'event_id' => $op . ':' . $platform . '-confirmed', 'verified' => true,
            'reconciled' => true, 'content_preserved' => true, 'button_verified' => true,
            'request_count' => 0, 'write_attempts' => 0,
        ]), 'observed_button_not_reconciled');
    }
    $final = $registry->publication($id);
    $resolved = ChannelPostEntryService::resolve($platform, $plan['token'], $registry);
    need($final['state'] === 'sent' && $final['start_payload'] === $plan['token']
        && snapshotMatches($final, $final['snapshot'], $plan)
        && ($final['snapshot']['metadata']['monitor_price_record_id'] ?? null) === REPAIR_RECORD
        && is_array($resolved) && $resolved['version_id'] === $final['version_id']
        && $resolved['source_key'] === $previous['source_key'], 'confirmed_registry_readback_failed');
    return safeResult($final, $plan) + [
        'price_link_verified' => true, 'price_link_url' => $plan['price_link']['url'],
    ];
}

$out = ['ok' => false, 'original_run_id' => REPAIR_RUN, 'max_provider_writes' => 0,
    'telegram_caption_writes' => 0, 'button_only_writes' => 0, 'new_posts' => 0, 'stage' => 'admission'];
$locks = [];
try {
    $app = '/var/www/anytoour/data/www/app.anytoour.ru';
    $base = '/var/www/anytoour/data/channel-post-operations';
    $operation = 'commercial-monitor-price-h' . REPAIR_HOTEL . '-' . REPAIR_RECORD;
    need(getcwd() === $app && hook_runtime() === REPAIR_APP_HEAD, 'installed_release_changed');
    $locks[] = $globalLock = recoveryLock($base . '/monitor-price-after-collection-v1.jsonl');
    $locks[] = $childLock = recoveryLock($base . '/' . $operation . '/receipt.jsonl');
    $global = recoveryGlobalTail($globalLock);
    $originalGlobal = $global;
    $pending = $global['pending'];
    need(($pending['operation'] ?? null) === $operation
        && ($pending['tourvisor_hotel_id'] ?? null) === REPAIR_HOTEL
        && ($pending['phase'] ?? null) === 'post_started', 'original_pending_stage_changed');
    need(fstat($childLock)['size'] === 22304 && rewind($childLock), 'original_child_bytes_changed');
    $raw = stream_get_contents($childLock);
    need(hash('sha256', $raw) === REPAIR_CHILD_SHA, 'original_child_receipt_changed');
    $state = monitor_hook_parse_journal($raw, $operation);
    need(empty($state['complete']) && count($state['posts']) === 1 && !isset($state['recovery']) && !isset($state['terminal'])
        && $state['manifest_hash'] === REPAIR_MANIFEST_SHA, 'original_child_stage_changed');

    ob_start();
    try {
        require $app . '/config.php';
        require_once $app . '/services/ChannelPostRuntime.php';
        require_once $app . '/services/ChannelPostEntryService.php';
    } finally {
        ob_end_clean();
    }
    $registry = new ChannelPostRegistryService(ConversationDb::connection());
    $roster = json_decode(gzdecode(base64_decode(HOOK_ROSTER_GZIP_B64, true)), true, 64, JSON_THROW_ON_ERROR)['rows'];
    $rows = array_values(array_filter($roster, static fn(array $r): bool => $r['tourvisor_hotel_id'] === REPAIR_HOTEL));
    need(count($rows) === 1, 'original_roster_changed');
    $row = $rows[0];
    need($row['posts'] === ['max' => 'https://max.ru/channel_anytour/AaHo8tYWbs0', 'telegram' => 'https://t.me/any_toour/676']
        && $row['feed_binding'] === $state['feed_binding'], 'original_roster_binding_changed');
    $context = hook_context($row, $registry);
    foreach (['max' => [1508, 14155], 'telegram' => [1498, 14156]] as $platform => [$id, $version]) {
        $p = $context[$platform];
        need((int)$p['publication_id'] === $id && (int)$p['version_id'] === $version
            && empty($p['claim_token']) && (int)$p['channel']['config_version'] === 2
            && $p['channel']['button_text'] === REPAIR_BUTTON
            && hash('sha256', $p['snapshot']['text']) === REPAIR_OLD_TEXT
            && ($p['snapshot']['metadata']['monitor_price_record_id'] ?? null) === REPAIR_OLD_RECORD
            && ChannelPostIncomingAdapter::containsOnlyButton($p['snapshot'], ChannelPostButtonTransport::buttonUrl($p), REPAIR_BUTTON),
            'original_registry_preimage_changed');
    }
    $max = $context['max'];
    $telegram = $context['telegram'];
    $price = monitor_price_observation($state['monitor_snapshot'], REPAIR_HOTEL, time(), false);
    need($price['record_id'] === REPAIR_RECORD && (float)$price['price'] === 1563921.0
        && $price['offer']['departureDate'] === '2026-12-11', 'original_price_changed');
    $today = new DateTimeImmutable('today', new DateTimeZone('Europe/Moscow'));
    $fresh = hook_snapshots([$row], $today)[REPAIR_HOTEL];
    $freshPrice = monitor_price_observation($fresh, REPAIR_HOTEL, time());
    foreach (['record_id', 'tourvisor_hotel_id', 'tour_id', 'price', 'currency', 'observed_at',
        'price_basis', 'fuel_added_separately', 'full_package_price_verified', 'availability_verified'] as $field) {
        need(($price[$field] ?? null) === ($freshPrice[$field] ?? null), 'saved_source_record_changed');
    }
    $priorOffer = $price['offer'];
    $newOffer = $freshPrice['offer'];
    unset($priorOffer['ageSecondsAtExport'], $priorOffer['expiresAt'], $newOffer['ageSecondsAtExport'], $newOffer['expiresAt']);
    need($priorOffer === $newOffer, 'saved_source_terms_changed');

    $maxKey = 'max:' . $row['key'];
    $maxEntry = $state['posts'][$maxKey];
    $maxPlan = recoveryPlan($row, $max, 'max', $price);
    $tgPlan = recoveryPlan($row, $telegram, 'telegram', $price);
    need($maxEntry['state'] === 'text_edit_returned' && $maxEntry['provider_edit_returned'] === true
        && is_int($maxEntry['edit_request_start_ms']) && $maxEntry['plan'] === $maxPlan
        && $tgPlan['message_id'] === '676', 'original_post_intent_changed');

    $manifestRow = array_intersect_key($row, array_flip(['key','hotel_id','tourvisor_hotel_id','logical_id','original_publication_operation','cover_sha256']));
    $manifestRow['channel_texts'] = [];
    foreach (['legacy_proof', 'caption_proof'] as $proof) {
        if (array_key_exists($proof, $row)) {
            $manifestRow[$proof] = $row[$proof];
            $manifestRow['posts'] = $row['posts'];
        }
    }
    foreach ($context as $platform => $p) {
        $new = $platform === 'max' ? $maxPlan['text'] : $tgPlan['text'];
        $old = $p['snapshot']['text'];
        $manifestRow['channel_texts'][$platform] = [
            'old_text'=>$old, 'old_text_sha256'=>hash('sha256', $old),
            'new_text'=>$new, 'new_text_sha256'=>hash('sha256', $new),
        ];
        if (!isset($manifestRow['legacy_proof']) && !isset($manifestRow['caption_proof'])) {
            $manifestRow['channel_texts'][$platform]['original_text_sha256'] = $row['original_text_sha256'];
        }
        $manifestRow['channel_texts'][$platform]['expected_version_id'] = (int)$p['version_id'];
        $manifestRow['channel_texts'][$platform]['expected_post_url'] = $row['posts'][$platform];
    }
    $manifest = ['operation'=>$operation, 'price_basis'=>'scheduled_monitor_result',
        'monitor_snapshot'=>$state['monitor_snapshot'], 'button_text'=>REPAIR_BUTTON,
        'feed_binding'=>$row['feed_binding'], 'rows'=>[$manifestRow]];
    need(hash('sha256', json_encode($manifest, REPAIR_JSON)) === REPAIR_MANIFEST_SHA, 'original_manifest_reconstruction_changed');

    $out['stage'] = 'provider_and_xml_preimages';
    foreach (REPAIR_FEEDS as $name => $sha) {
        need(hash('sha256', hook_read($app . '/feed/' . $name, 4000000)) === $sha
            && hash('sha256', hook_get('https://app.anytoour.ru/feed/' . $name)) === $sha, 'xml_preimage_changed');
    }
    $nativeMax = readMax($maxPlan['message_id']);
    $maxSnapshot = ChannelPostIncomingAdapter::maxSnapshot($nativeMax);
    need(is_array($maxSnapshot) && snapshotMatches($max, $maxSnapshot, $maxPlan)
        && monitorEditorPriceLinkVerified('max', $nativeMax, $maxPlan['text'], $maxPlan['price_link']), 'original_max_effect_unverified');
    $capability = (new ChannelPostButtonTransport())->verifyChannel($telegram['channel']);
    need(($capability['state'] ?? null) === 'ready', 'telegram_existing_publisher_unavailable');
    recoveryPublicTelegram($telegram['snapshot']['text'], $tgPlan['price_link']['url']);
    need(hook_runtime() === REPAIR_APP_HEAD && recoveryGlobalTail($globalLock) === $originalGlobal, 'production_lease_changed');
    monitor_price_observation($fresh, REPAIR_HOTEL, time());
    $out['preimages_verified_at'] = gmdate('c');

    $out['stage'] = 'adopt_verified_max';
    $state['recovery'] = ['original_run_id' => REPAIR_RUN, 'stage' => 'max_registry_adoption_started',
        'prior_child_sha256' => REPAIR_CHILD_SHA, 'started_at' => gmdate('c')];
    durable($childLock, $state);
    $state['posts'][$maxKey]['receipt'] = recoveryRegistryReceipt($registry, $max, $maxSnapshot, $maxPlan, $operation, $maxEntry['edit_request_start_ms']);
    $state['posts'][$maxKey]['state'] = 'confirmed';
    $state['recovery']['stage'] = 'max_confirmed';
    durable($childLock, $state);
    $out['max_version_id'] = $state['posts'][$maxKey]['receipt']['version_id'];

    $out['stage'] = 'write_original_telegram_leg';
    need($registry->publication((int)$telegram['publication_id']) === $telegram
        && hook_runtime() === REPAIR_APP_HEAD, 'telegram_preimage_changed_before_edit');
    monitor_price_observation($fresh, REPAIR_HOTEL, time());
    $tgKey = 'telegram:' . $row['key'];
    $state['posts'][$tgKey] = ['state'=>'edit_started', 'plan'=>$tgPlan, 'edit_request_start_ms'=>(int)floor(microtime(true) * 1000)];
    $state['recovery']['stage'] = 'telegram_edit_started';
    durable($childLock, $state);
    $out['telegram_caption_writes'] = 1;
    $response = tg('editMessageCaption', [
        'chat_id'=>$telegram['channel_id'], 'message_id'=>$tgPlan['message_id'],
        'reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>REPAIR_BUTTON,'url'=>$tgPlan['price_link']['url']]]]], REPAIR_JSON),
        'caption'=>$tgPlan['text'], 'caption_entities'=>json_encode($tgPlan['price_link']['telegram_entities'], REPAIR_JSON),
    ]);
    $state['posts'][$tgKey]['provider_response'] = $response;
    $state['posts'][$tgKey]['state'] = 'provider_response_recorded';
    durable($childLock, $state);
    $message = $response['data']['result'] ?? null;
    need($response['http'] === 200 && ($response['data']['ok'] ?? false) === true && is_array($message)
        && (string)$message['chat']['id'] === $telegram['channel_id'] && (string)$message['message_id'] === '676'
        && ($message['chat']['username'] ?? null) === 'any_toour'
        && monitorEditorPriceLinkVerified('telegram', $message, $tgPlan['text'], $tgPlan['price_link']), 'telegram_provider_outcome_unverified');
    $snap = ChannelPostIncomingAdapter::telegramSnapshot($message);
    need(is_array($snap) && snapshotMatches($telegram, $snap, $tgPlan), 'telegram_photo_or_button_changed');
    $state['posts'][$tgKey]['receipt'] = recoveryRegistryReceipt($registry, $telegram, $snap, $tgPlan, $operation, $state['posts'][$tgKey]['edit_request_start_ms']);
    $state['posts'][$tgKey]['state'] = 'confirmed';
    $state['recovery']['stage'] = 'pair_confirmed';
    $state['complete'] = true;
    durable($childLock, $state);
    $out['telegram_version_id'] = $state['posts'][$tgKey]['receipt']['version_id'];
    $out['pair_written_at'] = gmdate('c');
    $out['stage'] = 'write_and_verify_original_xml';
    flock($childLock, LOCK_UN);
    fclose($childLock);
    array_pop($locks);
    $result = hook_finish_feed($global, $globalLock, REPAIR_APP_HEAD, $out);
    need($result === 'feed_completed' && $global['pending'] === null
        && $global['completed'][REPAIR_HOTEL]['operation'] === $operation, 'original_xml_transaction_unverified');
    $out['feed_operation'] = $global['completed'][REPAIR_HOTEL]['feed_operation'];
    $out['feeds_verified_at'] = gmdate('c');
    $out['stage'] = 'independent_provider_readback';
    $maxAgain = readMax($maxPlan['message_id']);
    $snapshotAgain = ChannelPostIncomingAdapter::maxSnapshot($maxAgain);
    need(is_array($snapshotAgain) && snapshotMatches($max, $snapshotAgain, $maxPlan)
        && monitorEditorPriceLinkVerified('max', $maxAgain, $maxPlan['text'], $maxPlan['price_link']), 'final_max_public_read_failed');
    recoveryPublicTelegram($tgPlan['text'], $tgPlan['price_link']['url']);
    $out['independent_verified_at'] = gmdate('c');
    $out['stage'] = 'complete';
    $out['ok'] = true;
} catch (Throwable $e) {
    $out['error'] = preg_match('/\A[a-z0-9_]+\z/D', $e->getMessage()) ? $e->getMessage() : 'original_recovery_failed';
    if (isset($state['recovery'])) {
        $out['child_stage'] = $state['recovery']['stage'];
    }
} finally {
    foreach (array_reverse($locks) as $lock) {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}
echo 'ORIGINAL_PRICE_FINISH=' . json_encode($out, REPAIR_JSON) . "\n";
exit($out['ok'] ? 0 : 2);
