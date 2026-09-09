<?php
declare(strict_types=1);
// Disposable loopback test environment only. Never deploy this file or its adapter.
if (getenv('ANEX_REVIEW_TEST_DSN') !== 'mysql:host=127.0.0.1;port=3306;dbname=anex_review_test;charset=utf8mb4') {
    throw new RuntimeException('test_database_required');
}
$root = getenv('ANEX_REVIEW_TEST_ROOT');
if (!$root || !is_dir($root) || !is_writable($root)) throw new RuntimeException('test_root_required');
if (PHP_SAPI === 'cli') {
    @mkdir($root . '/sessions', 0700);
    @mkdir($root . '/web', 0700);
    $session = bin2hex(random_bytes(24));
    session_save_path($root . '/sessions');
    session_name('ANEX_REVIEW_TEST');
    session_id($session);
    session_start();
    $_SESSION['principal'] = ['actor'=>'owner:http-test','authenticated'=>true,'expires_at'=>time()+600,
        'capabilities'=>['anex:review','anex:decide'],'write_enabled'=>true,'pair_exclusions_enforced'=>true];
    session_write_close();
    $adapter = <<<'PHP'
<?php
return static function (): array {
    if (getenv('ANEX_REVIEW_TEST_DSN') !== 'mysql:host=127.0.0.1;port=3306;dbname=anex_review_test;charset=utf8mb4') throw new RuntimeException('test_only');
    session_save_path(getenv('ANEX_REVIEW_TEST_ROOT') . '/sessions');
    session_name('ANEX_REVIEW_TEST');
    ini_set('session.use_strict_mode', '1');
    session_start();
    $principal = $_SESSION['principal'] ?? [];
    $principal['pdo_factory'] = static function (): PDO {
        return new PDO(getenv('ANEX_REVIEW_TEST_DSN'), 'root', getenv('ANEX_REVIEW_TEST_PASSWORD') ?: '');
    };
    return $principal;
};
PHP;
    file_put_contents($root . '/auth-adapter.php', $adapter);
    chmod($root . '/auth-adapter.php', 0600);
    file_put_contents($root . '/browser-session.json', json_encode(['session'=>$session]));
    chmod($root . '/browser-session.json', 0600);
    echo "ANEX_REVIEW_HTTP_FIXTURE_READY synthetic_session=true live_identity=false\n";
    exit;
}
if (PHP_SAPI !== 'cli-server' || !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1','::1'], true)) {
    http_response_code(403); exit;
}
// Route the real entrypoint on an isolated local document root.
$_SERVER['SCRIPT_NAME'] = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$_SERVER['DOCUMENT_ROOT'] = $root . '/web';
putenv('ANYTOUR_ANEX_REVIEW_AUTH_FILE=' . $root . '/auth-adapter.php');
require __DIR__ . '/../v2/anex-hotel-review.php';
