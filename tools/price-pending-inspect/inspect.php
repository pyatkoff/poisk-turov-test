<?php
declare(strict_types=1);
// Read only. Never includes application configuration or invokes any publisher.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('display_errors', '0');
$handles = [];
function need(bool $ok, string $code): void { if (!$ok) throw new RuntimeException($code); }
function read_git(array $args): string {
    $lines=[];$status=0;
    exec('GIT_OPTIONAL_LOCKS=0 git -c core.fsmonitor=false -c core.untrackedCache=false '.implode(' ',array_map('escapeshellarg',$args)).' 2>/dev/null',$lines,$status);
    need($status===0,'runtime_git_failed');return implode("\n",$lines);
}
function snapshot(string $path, int $maximum, array &$handles): array {
    need(!is_link($path) && is_file($path) && realpath($path) === $path, 'read_path_invalid');
    $f = fopen($path, 'rb'); need(is_resource($f), 'read_open_failed');
    $handles[] = $f;
    need(flock($f, LOCK_SH | LOCK_NB), 'read_busy_no_retry');
    $before = fstat($f); $named = lstat($path);
    need(is_array($before) && is_array($named) && $before['ino'] === $named['ino'] && $before['dev'] === $named['dev'], 'read_identity_changed');
    need($before['size'] > 0 && $before['size'] <= $maximum, 'read_size_invalid');
    $raw = stream_get_contents($f, $maximum + 1);
    need(is_string($raw) && strlen($raw) === $before['size'], 'read_size_changed');
    clearstatcache(true, $path); $after = fstat($f); $named = lstat($path);
    foreach (['ino', 'dev', 'size', 'mtime', 'ctime'] as $k) {
        need(($after[$k] ?? null) === $before[$k] && ($named[$k] ?? null) === $before[$k], 'read_changed');
    }
    return ['raw' => $raw, 'bytes' => strlen($raw), 'sha256' => hash('sha256', $raw)];
}
/** Read only one complete final state, regardless of total historical journal size. */
function tail_snapshot(string $path, int $maximum, array &$handles, array &$evidence): string {
    need(!is_link($path) && is_file($path) && realpath($path) === $path, 'global_path_invalid');
    $f = fopen($path, 'rb'); need(is_resource($f), 'global_open_failed'); $handles[] = $f;
    need(flock($f, LOCK_SH | LOCK_NB), 'global_busy_no_retry');
    $before = fstat($f); $named = lstat($path);
    need(is_array($before) && is_array($named), 'global_stat_failed');
    $evidence = array_intersect_key($before, array_flip(['size','ino','dev','mtime','ctime']));
    $evidence['hash_scope'] = 'last_complete_line_only_not_entire_journal';
    need($before['ino'] === $named['ino'] && $before['dev'] === $named['dev'], 'global_identity_changed');
    need($before['size'] > 0, 'global_empty_stop');
    $offset = max(0, $before['size'] - $maximum);
    need(fseek($f, $offset, SEEK_SET) === 0, 'global_seek_failed');
    $raw = stream_get_contents($f, $maximum + 1);
    need(is_string($raw) && strlen($raw) === $before['size'] - $offset, 'global_tail_size_changed');
    need(str_ends_with($raw, "\n"), 'global_journal_incomplete');
    $body = substr($raw, 0, -1); $separator = strrpos($body, "\n");
    need($offset === 0 || $separator !== false, 'global_last_line_exceeds_bound');
    $last = $separator === false ? $body : substr($body, $separator + 1);
    need($last !== '', 'global_last_line_empty');
    clearstatcache(true, $path); $after = fstat($f); $named = lstat($path);
    foreach (['ino','dev','size','mtime','ctime'] as $key) {
        need(($after[$key] ?? null) === $before[$key] && ($named[$key] ?? null) === $before[$key], 'global_changed');
    }
    $evidence['tail_offset'] = $offset; $evidence['tail_bytes_read'] = strlen($raw);
    $evidence['last_line_offset'] = $before['size'] - strlen($last) - 1;
    $evidence['last_line_bytes'] = strlen($last) + 1;
    $evidence['last_line_sha256'] = hash('sha256', $last . "\n");
    return $last;
}
/** Return only recognized provider error descriptions; never arbitrary text/URLs/tokens. */
function safe_description(mixed $value): ?string {
    if (!is_string($value)) return null;
    $allowed = [
        'Bad Request: message is not modified', 'Bad Request: message to edit not found',
        "Bad Request: message can't be edited", 'Bad Request: message caption is too long',
        'Bad Request: caption is too long', 'Bad Request: chat not found',
        'Bad Request: MESSAGE_ID_INVALID', 'Unauthorized', 'Forbidden: bot was kicked from the channel chat',
        'Forbidden: bot is not a member of the channel chat', 'Bad Request: not enough rights to edit the message',
    ];
    if (in_array($value,$allowed,true)) return $value;
    if (preg_match('/\AToo Many Requests: retry after [0-9]{1,9}\z/D',$value)===1) return $value;
    if (str_starts_with($value,'Bad Request: message is not modified:')) return 'Bad Request: message is not modified [details omitted]';
    if (str_starts_with($value,"Bad Request: can't parse entities:")) return "Bad Request: can't parse entities [details omitted]";
    return '[unrecognized provider description omitted]';
}
function provider_projection(array $r): array {
    $o=[];
    if(is_int($r['http']??null))$o['http']=$r['http'];
    $d=$r['data']??null;
    if(is_array($d)){
        $o['data']=[];
        if(is_bool($d['ok']??null))$o['data']['ok']=$d['ok'];
        if(is_int($d['error_code']??null))$o['data']['error_code']=$d['error_code'];
        if(is_string($d['description']??null)){
            $o['data']['description']=safe_description($d['description']);
            $o['data']['description_bytes']=strlen($d['description']);
        }
        if(is_int($d['parameters']['retry_after']??null))$o['data']['parameters']=['retry_after'=>$d['parameters']['retry_after']];
        $o['data']['result_present']=is_array($d['result']??null);
    }
    foreach(['transport_error','curl_error','error'] as $k) if(array_key_exists($k,$r)) {
        $v=$r[$k];$o[$k.'_present']=!empty($v);
        if(is_int($v)||is_bool($v)||$v===null)$o[$k]=$v;
        elseif(is_string($v))$o[$k.'_bytes']=strlen($v);
    }
    foreach(['curl_errno','errno'] as $k)if(is_int($r[$k]??null))$o[$k]=$r[$k];
    return $o;
}
function projection(array $record): array {
    $out = [];
    foreach (['operation','feed_operation','event','type','state','phase','at','reason','error','complete','tourvisor_hotel_id','hotel_id','platform','publication_id','version_id','message_id','manifest_hash','manifest_sha256','text_sha256','old_text_sha256','new_text_sha256','photo_sha256','body_photo_button_verified','post_write_attempts','db_writes','message_sends','exit_code','child_exit_code'] as $key) {
        if (!array_key_exists($key, $record)) continue;
        $v = $record[$key];
        if (is_int($v) || is_bool($v) || $v === null) $out[$key] = $v;
        elseif (is_string($v) && strlen($v) <= 200 && preg_match('/\A[A-Za-z0-9_:.+\/-]*\z/D', $v) === 1) $out[$key] = $v;
    }
    if(is_array($record['provider_response']??null))$out['provider_response']=provider_projection($record['provider_response']);
    if(is_array($record['plan']??null)){
        $p=$record['plan'];$out['plan']=[];
        if(is_bool($p['update_text']??null))$out['plan']['update_text']=$p['update_text'];
        if(in_array($p['platform']??null,['max','telegram'],true))$out['plan']['platform']=$p['platform'];
        if(is_string($p['text']??null)){
            $out['plan']['text_bytes']=strlen($p['text']);
            $out['plan']['text_sha256']=hash('sha256',$p['text']);
            if(function_exists('mb_strlen'))$out['plan']['text_characters']=mb_strlen($p['text'],'UTF-8');
            if(function_exists('mb_convert_encoding'))$out['plan']['text_utf16_units']=strlen(mb_convert_encoding($p['text'],'UTF-16LE','UTF-8'))/2;
        }
    }
    foreach (['receipt','child_failure','terminal'] as $key) if (is_array($record[$key] ?? null)) $out[$key] = projection($record[$key]);
    foreach (['posts','configs'] as $key) if (is_array($record[$key] ?? null)) {
        $out[$key . '_count'] = count($record[$key]);
        $out[$key] = [];
        foreach (array_slice($record[$key],0,8) as $item) if (is_array($item)) $out[$key][] = projection($item);
    }
    return $out;
}
$out = ['ok'=>false,'mode'=>'read_only_pending_price_operation','checked_at'=>gmdate('c'),'server_writes'=>0,'post_writes'=>0,'feed_writes'=>0,'supplier_calls'=>0,'customer_messages'=>0];
try {
    $root = '/var/www/anytoour/data/channel-post-operations';
    need(!is_link($root) && realpath($root) === $root, 'private_root_changed');
    $out['global_journal'] = [];
    $last = tail_snapshot($root.'/monitor-price-after-collection-v1.jsonl', 4194304, $handles, $out['global_journal']);
    $state = json_decode($last, true, 64, JSON_THROW_ON_ERROR);
    need(is_array($state) && array_key_exists('pending', $state), 'global_state_invalid');
    $out['completed_hotels'] = is_array($state['completed'] ?? null) ? count($state['completed']) : null;
    $out['held_hotels'] = is_array($state['holds'] ?? null) ? count($state['holds']) : null;
    $cycle = $state['nightly_cycle'] ?? null;
    $out['nightly_cycle'] = is_array($cycle) ? array_intersect_key($cycle, array_flip(['collector_run_id','state','night_date','started_at','finished_at','updated_pairs','unchanged_pairs','held_pairs','scanned_pairs','remaining_unscanned_pairs'])) : null;
    $pending = $state['pending'];
    need($pending === null || is_array($pending), 'pending_invalid');
    $out['pending'] = $pending === null ? null : projection($pending);
    $out['pending_keys'] = $pending === null ? [] : array_keys($pending);
    $out['cycle_tuple_sha256'] = hash('sha256',json_encode($cycle,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
    $out['completed_tuple_sha256'] = hash('sha256',json_encode($state['completed'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
    if (is_array($pending)) {
        $op = $pending['operation'] ?? null;
        need(is_string($op) && preg_match('/\Acommercial-monitor-price-h[1-9][0-9]*-[a-f0-9]{64}\z/D', $op) === 1, 'pending_operation_invalid');
        $dir = $root.'/'.$op;
        need(!is_link($dir) && realpath($dir) === $dir, 'child_directory_invalid');
        $child = snapshot($dir.'/receipt.jsonl', 10000000, $handles);
        need(str_ends_with($child['raw'], "\n"), 'child_journal_incomplete');
        $rows = explode("\n", substr($child['raw'],0,-1)); need(count($rows) <= 1000, 'child_record_bound');
        $out['child_journal'] = ['sha256'=>$child['sha256'],'bytes'=>$child['bytes'],'records'=>count($rows),'entries'=>[]];
        foreach ($rows as $line) {
            $record = json_decode($line, true, 64, JSON_THROW_ON_ERROR); need(is_array($record), 'child_record_invalid');
            $entry = projection($record); $entry['keys'] = array_slice(array_keys($record),0,50);
            $out['child_journal']['entries'][] = $entry;
        }
        $out['pending_feed_files'] = [];
        $out['saved_monitor'] = ['generatedAt'=>$record['monitor_snapshot']['generatedAt'] ?? null, 'offers_count'=>count($record['monitor_snapshot']['item']['offers'] ?? [])];
        foreach (($pending['files'] ?? []) as $file) {
            need(is_array($file), 'pending_feed_file_invalid');
            $name = $file['name'] ?? null;
            need(in_array($name, ['hotel-posts-telegram.xml','hotel-posts-max.xml','hotel-posts-all.xml'],true), 'pending_feed_name_invalid');
            $actual = snapshot('/var/www/anytoour/data/www/app.anytoour.ru/feed/'.$name, 4000000, $handles);
            $out['pending_feed_files'][] = ['name'=>$name,'actual_sha256'=>$actual['sha256'],'expected_sha256'=>$file['sha256'] ?? null,'matches_expected'=>($file['sha256'] ?? null) === $actual['sha256'],'expected_previous_sha256'=>$file['expected_previous_sha256'] ?? null,'matches_previous'=>($file['expected_previous_sha256'] ?? null) === $actual['sha256']];
        }
    }
    $app='/var/www/anytoour/data/www/app.anytoour.ru';
    need(realpath($app)===$app && chdir($app),'app_root_invalid');
    $out['runtime']=['head'=>null,'tracked_clean'=>null,'git_checked'=>false];
    $head=read_git(['rev-parse','HEAD']);
    need(preg_match('/\A[a-f0-9]{40}\z/D',$head)===1,'runtime_head_invalid');
    $status=read_git(['status','--porcelain','--untracked-files=no']);
    need(read_git(['rev-parse','HEAD'])===$head,'runtime_head_changed');
    $out['runtime']=['head'=>$head,'tracked_clean'=>$status==='','git_checked'=>true];
    $out['ok'] = true;
} catch (Throwable $e) {
    $out['error'] = preg_match('/\A[a-z_]+\z/D',$e->getMessage()) === 1 ? $e->getMessage() : 'read_failed';
} finally {
    foreach (array_reverse($handles) as $f) { flock($f, LOCK_UN); fclose($f); }
}
echo json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),"\n";
exit($out['ok'] ? 0 : 2);
