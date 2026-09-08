<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

function anytour_anex_preview_out(array $data, int $status): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function anytour_anex_preview_enabled(): bool
{
    if (getenv('ANYTOUR_ANEX_PREVIEW_ENABLED') === '1') return true;
    return defined('ANYTOUR_ANEX_PREVIEW_ENABLED')
        && (ANYTOUR_ANEX_PREVIEW_ENABLED === true || ANYTOUR_ANEX_PREVIEW_ENABLED === 1
            || ANYTOUR_ANEX_PREVIEW_ENABLED === '1');
}

function anytour_anex_preview_token(): string
{
    $token = trim((string) getenv('ANEX_API_TOKEN'));
    if ($token !== '') return $token;
    return defined('ANEX_API_TOKEN') ? trim((string) ANEX_API_TOKEN) : '';
}

function anytour_anex_preview_db(): PDO
{
    $root = realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
    if ($root === false || basename($root) !== 'anytoour.ru') {
        throw new RuntimeException('ANEX_DATABASE_UNAVAILABLE');
    }
    foreach (['data/db-v1.php', 'v2/data/db-v1.php'] as $relative) {
        $helper = realpath($root . DIRECTORY_SEPARATOR . $relative);
        if ($helper !== false && is_file($helper)
            && strpos($helper, $root . DIRECTORY_SEPARATOR) === 0) {
            require_once $helper;
            if (!function_exists('v2_data_db')) break;
            $pdo = v2_data_db();
            if ($pdo instanceof PDO) return $pdo;
            break;
        }
    }
    throw new RuntimeException('ANEX_DATABASE_UNAVAILABLE');
}

$privateConfig = __DIR__ . '/config.php';
if (is_file($privateConfig)) require_once $privateConfig;

if (!anytour_anex_preview_enabled()) {
    anytour_anex_preview_out(['ok' => false, 'error' => 'not_found'], 404);
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    anytour_anex_preview_out(['ok' => false, 'error' => 'method_not_allowed'], 405);
}
$fetchSite = strtolower((string) ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? ''));
if ($fetchSite !== '' && !in_array($fetchSite, ['same-origin', 'same-site', 'none'], true)) {
    anytour_anex_preview_out(['ok' => false, 'error' => 'forbidden'], 403);
}
$contentType = strtolower(trim(explode(';', (string) ($_SERVER['CONTENT_TYPE'] ?? ''))[0]));
$contentLength = (string) ($_SERVER['CONTENT_LENGTH'] ?? '0');
if ($contentType !== 'application/json' || !preg_match('/\A[0-9]{1,8}\z/D', $contentLength)
    || (int) $contentLength > 32768) {
    anytour_anex_preview_out(['ok' => false, 'error' => 'invalid_request'], 400);
}
$raw = file_get_contents('php://input', false, null, 0, 32769);
if (!is_string($raw) || $raw === '' || strlen($raw) > 32768) {
    anytour_anex_preview_out(['ok' => false, 'error' => 'invalid_request'], 400);
}
try {
    $request = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
} catch (Throwable $ignored) {
    anytour_anex_preview_out(['ok' => false, 'error' => 'invalid_request'], 400);
}
if (!is_array($request)) {
    anytour_anex_preview_out(['ok' => false, 'error' => 'invalid_request'], 400);
}

$token = anytour_anex_preview_token();
if ($token === '') {
    anytour_anex_preview_out(['ok' => false, 'error' => 'temporarily_unavailable'], 503);
}

require_once __DIR__ . '/../app/integrations/anex-preview-gateway.php';
require_once __DIR__ . '/../app/integrations/anex-search-mapping-registry.php';

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_name('ANYTOUR_ANEX_PREVIEW');
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => true,
    'httponly' => true, 'samesite' => 'Lax']);
if (!session_start()) {
    anytour_anex_preview_out(['ok' => false, 'error' => 'temporarily_unavailable'], 503);
}
if (!isset($_SESSION['anytour_anex_preview']) || !is_array($_SESSION['anytour_anex_preview'])) {
    $_SESSION['anytour_anex_preview'] = [];
}

try {
    $registry = AnyTourAnexSearchMappingRegistry::fromPdo(anytour_anex_preview_db());
    $gateway = new AnyTourAnexPreviewGateway(
        static function () use ($token): AnyTourAnexClient { return new AnyTourAnexClient($token); },
        $registry->previewResolver(),
        [$token]
    );
    $result = $gateway->handle($request, $_SESSION['anytour_anex_preview']);
    session_write_close();
    anytour_anex_preview_out(['ok' => true, 'data' => $result], 200);
} catch (InvalidArgumentException $error) {
    session_write_close();
    $status = $error->getMessage() === 'ANEX_SESSION_REQUIRED' ? 409 : 400;
    $message = $status === 409 ? 'search_expired' : 'invalid_request';
    anytour_anex_preview_out(['ok' => false, 'error' => $message], $status);
} catch (RuntimeException $error) {
    session_write_close();
    if ($error->getMessage() === 'ANEX_RATE_LIMIT') {
        anytour_anex_preview_out(['ok' => false, 'error' => 'rate_limited'], 429);
    }
    anytour_anex_preview_out(['ok' => false, 'error' => 'supplier_unavailable'], 502);
} catch (Throwable $ignored) {
    session_write_close();
    anytour_anex_preview_out(['ok' => false, 'error' => 'temporarily_unavailable'], 503);
}
