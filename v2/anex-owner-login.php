<?php
declare(strict_types=1);
ini_set('display_errors', '0');
header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');
header('Content-Type: text/html; charset=utf-8');
$nonce = base64_encode(random_bytes(24));
header("Content-Security-Policy: default-src 'none'; style-src 'nonce-$nonce'; script-src 'nonce-$nonce'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
try {
    if (($_SERVER['SCRIPT_NAME'] ?? '') !== '/_preview/search3-anex-candidate/anex-owner-login.php') throw new RuntimeException('owner_preview_only', 404);
    if (!in_array($_SERVER['REQUEST_METHOD'] ?? '', ['GET','POST'], true)) throw new RuntimeException('method_not_allowed', 405);
    if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 8192 || $_GET !== []) throw new RuntimeException('request_invalid', 400);
    $app = is_file(__DIR__.'/app/admin/anex-review/owner-login.php') ? __DIR__.'/app/admin/anex-review' : dirname(__DIR__).'/app/admin/anex-review';
    require_once $app.'/owner-login.php';
    $login = new AnexReviewOwnerLogin(anex_owner_private_config(__DIR__), (string)($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $destination = $login->post($_SERVER, $_POST);
            header('Location: '.$destination, true, 303);
            exit;
        } catch (Throwable $e) {
            $limited = $e->getMessage() === 'owner_rate_limited';
            http_response_code($limited ? 429 : 403);
            if ($limited) header('Retry-After: 900');
            $error = $limited ? 'Слишком много попыток. Повторите вход через 15 минут.' : 'Вход не выполнен. Проверьте пароль или срок действия ссылки активации.';
        }
    }
    try { $login->context(); $authenticated = true; } catch (Throwable $e) { $authenticated = false; }
    $enrollmentToken = ($_POST['action'] ?? '') === 'enroll' && is_string($_POST['token'] ?? null) ? $_POST['token'] : '';
    echo anex_owner_login_html($login->csrf(), $nonce, $error, $authenticated, $enrollmentToken);
} catch (Throwable $e) {
    http_response_code(in_array($e->getCode(), [400,403,404,405], true) ? $e->getCode() : 503);
    echo '<!doctype html><html lang="ru"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Вход владельца</title><p>Защищённый вход пока недоступен.</p></html>';
}
