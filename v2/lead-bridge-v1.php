<?php
/**
 * Standalone lead bridge used on anytoour.ru.
 * It deliberately does not reimplement the production lead write. Instead it opens
 * the existing AnyTour V2 page to obtain a valid Bitrix session and forwards the
 * original JSON payload to the unchanged production lead-adapter-v2.php.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

const V2_BRIDGE_MAX_BODY = 65536;
const V2_BRIDGE_PAGE = 'https://anytour.online/poisk-turov-test/v2/';
const V2_BRIDGE_LEAD = 'https://anytour.online/poisk-turov-test/v2/lead-adapter-v2.php';

function bridge_out(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function bridge_same_origin(): bool
{
    $host = strtolower(preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? '')));
    if ($host === '') return false;
    $origin = trim((string)($_SERVER['HTTP_ORIGIN'] ?? ''));
    if ($origin !== '') {
        $originHost = strtolower((string)(parse_url($origin, PHP_URL_HOST) ?? ''));
        return $originHost === $host;
    }
    $referer = trim((string)($_SERVER['HTTP_REFERER'] ?? ''));
    if ($referer !== '') {
        $refererHost = strtolower((string)(parse_url($referer, PHP_URL_HOST) ?? ''));
        return $refererHost === $host;
    }
    return true;
}

function bridge_curl_error(int $errno): array
{
    return ['ok' => false, 'error' => 'Lead service connection error', 'curlErrno' => $errno];
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    bridge_out(['ok' => true, 'adapter' => 'v2-session-bridge-bitrix-lead', 'version' => 1, 'writes' => true]);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') bridge_out(['ok' => false, 'error' => 'Method not allowed'], 405);
if (!bridge_same_origin()) bridge_out(['ok' => false, 'error' => 'Origin not allowed'], 403);
if (stripos((string)($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json') !== 0) bridge_out(['ok' => false, 'error' => 'JSON request required'], 415);
if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > V2_BRIDGE_MAX_BODY) bridge_out(['ok' => false, 'error' => 'Request too large'], 413);

$raw = (string)file_get_contents('php://input');
if (strlen($raw) > V2_BRIDGE_MAX_BODY) bridge_out(['ok' => false, 'error' => 'Request too large'], 413);
$data = json_decode($raw, true);
if (!is_array($data)) bridge_out(['ok' => false, 'error' => 'Invalid JSON'], 400);

$cookieJar = tempnam(sys_get_temp_dir(), 'anytoour-lead-');
if ($cookieJar === false) bridge_out(['ok' => false, 'error' => 'Lead session unavailable'], 500);
@chmod($cookieJar, 0600);

try {
    $ch = curl_init(V2_BRIDGE_PAGE);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_COOKIEJAR => $cookieJar,
        CURLOPT_COOKIEFILE => $cookieJar,
        CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml'],
        CURLOPT_USERAGENT => 'AnytoourLeadBridge/1.0',
    ]);
    $page = curl_exec($ch);
    $errno = curl_errno($ch);
    $pageCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($errno) bridge_out(bridge_curl_error($errno), 502);
    if ($pageCode < 200 || $pageCode >= 400 || !is_string($page)) bridge_out(['ok' => false, 'error' => 'Lead session bootstrap failed', 'upstreamStatus' => $pageCode], 502);

    $sessid = '';
    if (preg_match('/name=["\']sessid["\'][^>]*value=["\']([^"\']+)["\']/i', $page, $m)) $sessid = trim($m[1]);
    if ($sessid === '' && preg_match('/value=["\']([^"\']+)["\'][^>]*name=["\']sessid["\']/i', $page, $m)) $sessid = trim($m[1]);
    if ($sessid === '') bridge_out(['ok' => false, 'error' => 'Lead session token missing'], 502);

    $data['sessid'] = $sessid;
    $forwardBody = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($forwardBody === false) bridge_out(['ok' => false, 'error' => 'Lead payload encoding failed'], 500);

    $ch = curl_init(V2_BRIDGE_LEAD);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 35,
        CURLOPT_COOKIEJAR => $cookieJar,
        CURLOPT_COOKIEFILE => $cookieJar,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $forwardBody,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
            'Origin: https://anytour.online',
            'Referer: ' . V2_BRIDGE_PAGE,
        ],
        CURLOPT_USERAGENT => 'AnytoourLeadBridge/1.0',
    ]);
    $body = curl_exec($ch);
    $errno = curl_errno($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($errno) bridge_out(bridge_curl_error($errno), 502);
    if (!is_string($body) || json_decode($body, true) === null) bridge_out(['ok' => false, 'error' => 'Invalid lead service response', 'upstreamStatus' => $code], 502);

    http_response_code($code > 0 ? $code : 502);
    echo $body;
} finally {
    @unlink($cookieJar);
}
