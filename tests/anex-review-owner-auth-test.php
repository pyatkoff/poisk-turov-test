<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/admin/anex-review/owner-auth.php';

$checks = 0;
function check(bool $ok, string $label): void { global $checks; $checks++; if (!$ok) throw new RuntimeException($label); }
function denied(callable $operation, string $reason): void {
    try { $operation(); } catch (RuntimeException $e) { check($e->getMessage() === $reason, $reason . ':' . $e->getMessage()); return; }
    throw new RuntimeException('Expected ' . $reason);
}
$base = sys_get_temp_dir() . '/anex-owner-test-' . bin2hex(random_bytes(8));
mkdir($base, 0700);
mkdir($base . '/public', 0700);
$now = 1900000000;
$clock = static function () use (&$now): int { return $now; };
$token = str_repeat('a', 64);
$password = 'Owner password chosen 2026!';
function account(string $name, int $expiry = 600): AnexReviewOwnerAuth {
    global $base, $now, $clock, $token;
    mkdir($base . '/' . $name, 0700);
    AnexReviewOwnerAuth::bootstrap($base . '/' . $name, $base . '/public', $token, $now + $expiry, $clock);
    return new AnexReviewOwnerAuth($base . '/' . $name, $base . '/public', $clock);
}
function removeTree(string $path): void {
    if (is_link($path) || is_file($path)) { unlink($path); return; }
    foreach (scandir($path) as $name) if ($name !== '.' && $name !== '..') removeTree($path . '/' . $name);
    rmdir($path);
}
try {
    denied(static function () use ($base, $clock): void { new AnexReviewOwnerAuth($base . '/public', $base . '/public', $clock); }, 'owner_private_directory_invalid');
    mkdir($base . '/public/inside', 0700);
    denied(static function () use ($base): void { new AnexReviewOwnerAuth($base . '/public/inside', $base . '/public'); }, 'owner_private_directory_invalid');
    $auth = account('owner');
    denied(static function () use ($base, $token, $now, $clock): void {
        AnexReviewOwnerAuth::bootstrap($base . '/owner', $base . '/public', $token, $now + 60, $clock);
    }, 'owner_bootstrap_exists');
    check((fileperms($base . '/owner/owner.json') & 0777) === 0600, 'private account permissions');
    check((fileperms($base . '/owner/owner.lock') & 0777) === 0600, 'private lock permissions');
    check(strpos(file_get_contents($base . '/owner/owner.json'), $token) === false, 'no raw bootstrap token');
    symlink($base . '/owner', $base . '/linked');
    denied(static function () use ($base): void { new AnexReviewOwnerAuth($base . '/linked', $base . '/public'); }, 'owner_private_directory_invalid');
    chmod($base . '/owner', 0755); clearstatcache();
    denied(static function () use ($base): void { new AnexReviewOwnerAuth($base . '/owner', $base . '/public'); }, 'owner_private_directory_invalid');
    chmod($base . '/owner', 0700); clearstatcache();
    denied(static function () use ($auth, $password): void { $auth->enroll(str_repeat('b', 64), $password); }, 'owner_credentials_invalid');
    denied(static function () use ($auth, $token): void { $auth->enroll($token, str_repeat('x', 11)); }, 'owner_credentials_invalid');
    denied(static function () use ($auth, $token): void { $auth->enroll($token, str_repeat('x', 129)); }, 'owner_credentials_invalid');
    $claim = $auth->enroll($token, $password);
    check($auth->principal($claim)['write_enabled'] === false, 'no write grant');
    check($auth->principal($claim)['capabilities'] === ['anex:review'], 'read capability only');
    $stored = json_decode(file_get_contents($base . '/owner/owner.json'), true);
    check($stored['setup_hash'] === null && $stored['setup_expires_at'] === 0, 'setup destroyed');
    check(strpos(file_get_contents($base . '/owner/owner.json'), $password) === false, 'no plaintext password');
    denied(static function () use ($auth, $token, $password): void { $auth->enroll($token, $password); }, 'owner_credentials_invalid');
    check($auth->login($password)['actor'] === $claim['actor'], 'stable owner identity');
    $another = new AnexReviewOwnerAuth($base . '/owner', $base . '/public', $clock);
    for ($i = 0; $i < 5; $i++) denied(static function () use ($another): void { $another->login('Wrong password long enough'); }, 'owner_credentials_invalid');
    denied(static function () use ($auth, $password): void { $auth->login($password); }, 'owner_rate_limited');
    $now += 900;
    $claim = $auth->login($password);
    $oldClaim = $claim;
    $now += 100;
    $auth->principal($claim);
    check($claim['last_seen'] === $now, 'idle touch');
    $claim['credential_version'] = str_repeat('0', 32);
    denied(static function () use ($auth, &$claim): void { $auth->principal($claim); }, 'owner_session_invalid');
    check($claim === [], 'invalid session revoked');
    $claim = $oldClaim;
    $now += 1800;
    denied(static function () use ($auth, &$claim): void { $auth->principal($claim); }, 'owner_session_invalid');
    $claim = $auth->login($password);
    $now += 28800;
    $claim['last_seen'] = $now;
    denied(static function () use ($auth, &$claim): void { $auth->principal($claim); }, 'owner_session_invalid');
    $claim = $auth->login($password);
    $auth->revoke($claim);
    denied(static function () use ($auth, &$claim): void { $auth->principal($claim); }, 'owner_session_invalid');
    $expired = account('expired', 60);
    $now += 60;
    denied(static function () use ($expired, $token, $password): void { $expired->enroll($token, $password); }, 'owner_credentials_invalid');
    mkdir($base . '/hash-only', 0700);
    AnexReviewOwnerAuth::bootstrapHash($base . '/hash-only', $base . '/public', hash('sha256', $token), $now + 60, $clock);
    $hashOnly = new AnexReviewOwnerAuth($base . '/hash-only', $base . '/public', $clock);
    check(isset($hashOnly->enroll($token, str_repeat('z', 12))['actor']), 'hash-only setup and 12-byte minimum');
    mkdir($base . '/too-late', 0700);
    denied(static function () use ($base, $now, $clock, $token): void {
        AnexReviewOwnerAuth::bootstrap($base . '/too-late', $base . '/public', $token, $now + 3601, $clock);
    }, 'owner_bootstrap_invalid');
    $long = account('long');
    $longPassword = str_repeat('x', 127) . 'A';
    $long->enroll($token, $longPassword);
    denied(static function () use ($long): void { $long->login(str_repeat('x', 127) . 'B'); }, 'owner_credentials_invalid');
    check(isset($long->login($longPassword)['actor']), 'full 128-byte password');
    chmod($base . '/long/owner.lock', 0644); clearstatcache();
    denied(static function () use ($long, $longPassword): void { $long->login($longPassword); }, 'owner_private_state_invalid');
    chmod($base . '/long/owner.lock', 0600); clearstatcache();
    rename($base . '/long/owner.json', $base . '/long/real.json');
    symlink($base . '/long/real.json', $base . '/long/owner.json');
    denied(static function () use ($long, $longPassword): void { $long->login($longPassword); }, 'owner_private_state_invalid');
    unlink($base . '/long/owner.json');
    rename($base . '/long/real.json', $base . '/long/owner.json');
    chmod($base . '/long/owner.json', 0644); clearstatcache();
    denied(static function () use ($long, $longPassword): void { $long->login($longPassword); }, 'owner_private_state_invalid');
    chmod($base . '/long/owner.json', 0600); clearstatcache();
    file_put_contents($base . '/long/owner.json', '{broken');
    denied(static function () use ($long, $longPassword): void { $long->login($longPassword); }, 'owner_private_state_invalid');
    unlink($base . '/long/owner.lock');
    denied(static function () use ($long, $longPassword): void { $long->login($longPassword); }, 'owner_private_state_invalid');
    echo 'Owner auth: ' . $checks . " checks passed\n";
} finally { removeTree($base); }
