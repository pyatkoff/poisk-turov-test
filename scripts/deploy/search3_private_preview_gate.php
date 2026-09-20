<?php
declare(strict_types=1);

/* Installed outside DOCUMENT_ROOT. Every private route, including assets, enters here. */
const SEARCH3_PRIVATE_ROUTE = '/_preview/search3-v17-candidate/';
const SEARCH3_OWNER_AUTH_SHA256 = '6f81a21490f641a9310dbf4594d1d95d43727d88989ee3f5dfb70d8db1c45cab';

function privatePreviewStop(int $status, string $text): never
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    echo $text;
    exit;
}

header_register_callback(static function (): void {
    header('Cache-Control: private, no-store, max-age=0');
    header('X-Robots-Tag: noindex, nofollow, noarchive');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
});

try {
    $private = dirname(__DIR__);
    $home = dirname($private);
    $documentRoot = $home . '/www/anytoour.ru';
    if (realpath($private) !== $private || realpath($documentRoot) !== $documentRoot
        || realpath((string)($_SERVER['DOCUMENT_ROOT'] ?? '')) !== $documentRoot
        || (($_SERVER['HTTPS'] ?? '') !== 'on' && (string)($_SERVER['SERVER_PORT'] ?? '') !== '443')
        || !in_array($_SERVER['HTTP_HOST'] ?? '', ['anytoour.ru', 'anytoour.ru:443'], true)) {
        privatePreviewStop(503, 'Private preview unavailable');
    }
    $authFile = $documentRoot . '/_preview/search3-anex-candidate/app/admin/anex-review/owner-auth.php';
    if (realpath($authFile) !== $authFile || !is_file($authFile)
        || !hash_equals(SEARCH3_OWNER_AUTH_SHA256, (string)hash_file('sha256', $authFile))) {
        privatePreviewStop(503, 'Private preview unavailable');
    }
    require_once $authFile;
    $auth = new AnexReviewOwnerAuth($home . '/.anytoour-anex/review-owner', $documentRoot);
    $sessions = $private . '/sessions';
    if (realpath($sessions) !== $sessions || (fileperms($sessions) & 0777) !== 0700) {
        privatePreviewStop(503, 'Private preview unavailable');
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('ANYTOUR_SEARCH3_PRIVATE');
    session_save_path($sessions);
    session_set_cookie_params(['lifetime' => 0, 'path' => SEARCH3_PRIVATE_ROUTE,
        'secure' => true, 'httponly' => true, 'samesite' => 'Strict']);
    if (!session_start()) privatePreviewStop(503, 'Private preview unavailable');
    $path = parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    if (!is_string($path) || !str_starts_with($path, SEARCH3_PRIVATE_ROUTE)) privatePreviewStop(404, 'Not found');
    $relative = substr($path, strlen(SEARCH3_PRIVATE_ROUTE));
    // Reject encoded and noncanonical paths before touching the private tree.
    if (preg_match('/[^a-zA-Z0-9_\/.\-]/D', $relative)
        || str_contains($relative, '//') || preg_match('#(^|/)\.#', $relative)) privatePreviewStop(404, 'Not found');
    $method = $_SERVER['REQUEST_METHOD'] ?? '';
    if (!in_array($method, ['GET', 'HEAD', 'POST'], true)) privatePreviewStop(405, 'Method not allowed');
    if (!is_string($_SESSION['csrf'] ?? null)) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    $error = '';
    if ($relative === '_login' && $method === 'POST') {
        if (($_SERVER['HTTP_ORIGIN'] ?? '') !== 'https://anytoour.ru'
            || (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 2048
            || !is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])
            || !is_string($_POST['password'] ?? null)) privatePreviewStop(403, 'Request rejected');
        try {
            $_SESSION['owner'] = $auth->login($_POST['password']);
            if (!session_regenerate_id(true)) privatePreviewStop(503, 'Private preview unavailable');
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
            session_write_close();
            header('Location: ' . SEARCH3_PRIVATE_ROUTE . 'poisk-turov/', true, 303);
            exit;
        } catch (Throwable $e) { $error = 'Не удалось войти. Проверьте пароль или попробуйте позже.'; }
    }
    $claim = is_array($_SESSION['owner'] ?? null) ? $_SESSION['owner'] : [];
    try { $principal = $auth->principal($claim); }
    catch (Throwable $e) { $principal = []; $claim = []; }
    $_SESSION['owner'] = $claim;
    if (($principal['authenticated'] ?? false) !== true) {
        http_response_code(401);
        header('Content-Type: text/html; charset=utf-8');
        header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
        $csrf = htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8');
        session_write_close();
        if ($method === 'HEAD') exit;
        echo '<!doctype html><html lang="ru"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>AnyTour · Закрытый просмотр</title><style>body{font:16px system-ui,sans-serif;color:#17233b;background:#f5f7fb;margin:0;min-height:100dvh;display:grid;place-items:center}main{box-sizing:border-box;width:min(100% - 32px,420px);background:white;padding:28px;border-radius:20px;border:1px solid #dbe2ed}img{width:180px;height:auto}h1{font-size:25px;margin:24px 0 8px}p{line-height:1.5;color:#526078}label{display:block;font-weight:600;margin-top:24px}input,button{box-sizing:border-box;width:100%;min-height:48px;font:inherit;border-radius:10px;padding:12px}input{border:1px solid #8793aa;margin:8px 0 16px}button{background:#2743cb;border:0;color:white;font-weight:700}a{color:#2743cb}:focus-visible{outline:3px solid #6f99ff;outline-offset:3px}</style><main><img src="/images/logo.svg" alt="AnyTour"><h1>Закрытый просмотр Search3</h1><p>Войдите с паролем владельца AnyTour.</p>';
        if ($error !== '') echo '<p role="alert">' . $error . '</p>';
        echo '<form method="post" action="' . SEARCH3_PRIVATE_ROUTE . '_login"><input type="hidden" name="csrf" value="' . $csrf . '"><label for="password">Пароль</label><input id="password" type="password" name="password" autocomplete="current-password" minlength="12" maxlength="128" required><button>Войти</button></form></main></html>';
        exit;
    }
    if ($relative === '_logout' && $method === 'POST') {
        if (($_SERVER['HTTP_ORIGIN'] ?? '') !== 'https://anytoour.ru'
            || !is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) privatePreviewStop(403, 'Request rejected');
        $_SESSION = [];
        session_destroy();
        header('Location: ' . SEARCH3_PRIVATE_ROUTE, true, 303);
        exit;
    }
    session_write_close();
    $file = $relative === '' || str_ends_with($relative, '/') ? $relative . 'index.php' : $relative;
    $payload = $private . '/payload';
    $absolute = $payload . '/' . $file;
    if (realpath($absolute) !== $absolute || !is_file($absolute) || is_link($absolute)) privatePreviewStop(404, 'Not found');
    $extension = pathinfo($file, PATHINFO_EXTENSION);
    if ($extension === 'php') {
        // Match the checked whole-site public entry allowlist. LOCAL-only DB routes remain denied.
        if (basename($file) !== 'index.php' && !in_array($file, ['bundle-v1.php', 'preview-lead-disabled.php'], true)) privatePreviewStop(403, 'Forbidden');
        if ($method === 'POST' && $file !== 'preview-lead-disabled.php') privatePreviewStop(405, 'Method not allowed');
        $_SERVER['SCRIPT_NAME'] = SEARCH3_PRIVATE_ROUTE . $file;
        $_SERVER['SCRIPT_FILENAME'] = $absolute;
        require $absolute;
        exit;
    }
    if ($method === 'POST') privatePreviewStop(405, 'Method not allowed');
    $types = ['css' => 'text/css', 'js' => 'application/javascript', 'svg' => 'image/svg+xml',
        'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp',
        'avif' => 'image/avif', 'gif' => 'image/gif', 'ico' => 'image/x-icon', 'woff' => 'font/woff', 'woff2' => 'font/woff2'];
    if (!isset($types[$extension])) privatePreviewStop(403, 'Forbidden');
    header('Content-Type: ' . $types[$extension]);
    if ($method !== 'HEAD') readfile($absolute);
} catch (Throwable $e) { privatePreviewStop(503, 'Private preview unavailable'); }
