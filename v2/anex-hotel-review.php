<?php
declare(strict_types=1);

// Deliberately not included in the existing public-preview deployment manifest.
ini_set('display_errors', '0');
header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');
$nonce = base64_encode(random_bytes(24));
header("Content-Security-Policy: default-src 'none'; style-src 'nonce-" . $nonce . "'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
header('Content-Type: text/html; charset=utf-8');

try {
    if (($_SERVER['SCRIPT_NAME'] ?? '') !== '/_preview/search3-anex-candidate/anex-hotel-review.php') {
        throw new RuntimeException('review_preview_only', 404);
    }
    if (!in_array($_SERVER['REQUEST_METHOD'] ?? '', ['GET', 'POST'], true)) throw new RuntimeException('method_not_allowed', 405);
    if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 8192) throw new RuntimeException('request_too_large', 413);
    // The absolute adapter path is a server setting, never a URL/query/header.
    // Missing adapter means NO session creation, NO DB connection, and NO writes.
    $path = getenv('ANYTOUR_ANEX_REVIEW_AUTH_FILE');
    $adapterPath = is_string($path) && substr($path, 0, 1) === '/' ? realpath($path) : false;
    $webRoot = realpath((string)($_SERVER['DOCUMENT_ROOT'] ?? ''));
    if (!$adapterPath || !$webRoot || strpos($adapterPath, rtrim($webRoot, '/') . '/') === 0 || !is_file($adapterPath)) {
        throw new RuntimeException('review_auth_not_connected', 503);
    }
    $adapter = require $adapterPath;
    if (!is_callable($adapter)) throw new RuntimeException('review_auth_not_connected', 503);
    $context = $adapter(); // Must validate a real owner session, not merely start one.
    if (!is_array($context) || session_status() !== PHP_SESSION_ACTIVE) throw new RuntimeException('review_forbidden', 403);
    $app = is_file(__DIR__ . '/app/admin/anex-review/service.php') ? __DIR__ . '/app/admin/anex-review' : dirname(__DIR__) . '/app/admin/anex-review';
    require_once $app . '/access.php';
    $actor = AnexReviewAccess::principal($context);
    if (!isset($_SESSION['anex_review_csrf']) || ($_SESSION['anex_review_actor'] ?? null) !== $actor) {
        $_SESSION['anex_review_csrf'] = bin2hex(random_bytes(32));
        $_SESSION['anex_review_actor'] = $actor;
    }
    $csrf = (string)$_SESSION['anex_review_csrf'];
    // A deployment gate: pair exclusions must also be honored by import paths
    // before write access is enabled. This packet does not claim that integration.
    $write = ($context['write_enabled'] ?? false) === true && ($context['pair_exclusions_enforced'] ?? false) === true
        && in_array('anex:decide', $context['capabilities'] ?? [], true);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!$write) throw new RuntimeException('review_write_not_connected', 403);
        AnexReviewAccess::post($_SERVER, $_POST, $csrf);
        if (($_POST['action'] ?? '') === 'accept' && ($_POST['confirm'] ?? '') !== 'yes') throw new RuntimeException('review_confirmation_required', 400);
    }
    // The trusted adapter reuses the existing AnyTour DB helper; no credentials here.
    if (!is_callable($context['pdo_factory'] ?? null)) throw new RuntimeException('review_db_not_connected', 503);
    $db = ($context['pdo_factory'])();
    if (!$db instanceof PDO) throw new RuntimeException('review_db_not_connected', 503);
    require_once $app . '/service.php';
    require_once $app . '/view.php';
    $service = new AnexReviewService($db);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $result = $service->decide($_POST, $actor);
        // PRG prevents browser refresh from creating a new action. Replay tokens remain in audit.
        header('Location: ?id=' . (int)$result['id'] . '&saved=1', true, 303);
        exit;
    }
    $filters = [];
    foreach (['q','country','status','page'] as $key) {
        if (isset($_GET[$key]) && !is_string($_GET[$key])) throw new RuntimeException('invalid_filter', 400);
        if (isset($_GET[$key])) $filters[$key] = $_GET[$key];
    }
    $detail = isset($_GET['id']) ? $service->detail($_GET['id']) : null;
    echo anex_review_render($service->queue($filters), $detail, $filters, $csrf, $write, $nonce,
        ($_GET['saved'] ?? '') === '1' ? 'Решение сохранено. Проверьте журнал ниже.' : '');
} catch (Throwable $e) {
    $code = in_array($e->getCode(), [400,403,404,405,409,413,503], true) ? $e->getCode() : 503;
    http_response_code($code);
    $message = $code === 409 ? 'Данные или решение изменились. Откройте карточку заново; запись не выполнена.'
        : ($code === 403 ? 'Нет доступа к этому действию. Нужна действующая сессия владельца.' : 'Панель пока недоступна. Запись решений не выполнялась.');
    // Never expose SQL, adapter paths, credentials or session data in the response.
    echo '<!doctype html><html lang="ru"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Проверка отелей</title><p>' . $message . '</p></html>';
}
