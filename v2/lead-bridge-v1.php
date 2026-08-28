<?php
/**
 * Standalone lead bridge used on anytoour.ru.
 * It authenticates the server-to-server request to the legacy AnyTour receiver;
 * the receiver then invokes the unchanged production Bitrix lead adapter.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

const V2_BRIDGE_MAX_BODY = 65536;
const V2_BRIDGE_RECEIVER = 'https://anytour.online/poisk-turov-test/v2/lead-receiver-v1.php';

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

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    bridge_out(['ok' => true, 'adapter' => 'v2-hmac-bridge-bitrix-lead', 'version' => 2, 'writes' => true]);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') bridge_out(['ok' => false, 'error' => 'Method not allowed'], 405);
if (!bridge_same_origin()) bridge_out(['ok' => false, 'error' => 'Origin not allowed'], 403);
if (stripos((string)($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json') !== 0) bridge_out(['ok' => false, 'error' => 'JSON request required'], 415);
if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > V2_BRIDGE_MAX_BODY) bridge_out(['ok' => false, 'error' => 'Request too large'], 413);

$raw = (string)file_get_contents('php://input');
if ($raw === '' || strlen($raw) > V2_BRIDGE_MAX_BODY) bridge_out(['ok' => false, 'error' => 'Invalid request body'], 400);
if (!is_array(json_decode($raw, true))) bridge_out(['ok' => false, 'error' => 'Invalid JSON'], 400);

$secretFile = __DIR__ . '/.anytoour-bridge-secret';
$secret = is_file($secretFile) ? trim((string)file_get_contents($secretFile)) : '';
if ($secret === '') bridge_out(['ok' => false, 'error' => 'Lead bridge is not configured'], 503);
$signature = hash_hmac('sha256', $raw, $secret);

$ch = curl_init(V2_BRIDGE_RECEIVER);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 40,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $raw,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Accept: application/json',
        'X-Anytoour-Signature: ' . $signature,
    ],
    CURLOPT_USERAGENT => 'AnytoourLeadBridge/2.0',
]);
$body = curl_exec($ch);
$errno = curl_errno($ch);
$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($errno) bridge_out(['ok' => false, 'error' => 'Lead service connection error', 'curlErrno' => $errno], 502);
if (!is_string($body) || json_decode($body, true) === null) bridge_out(['ok' => false, 'error' => 'Invalid lead service response', 'upstreamStatus' => $code], 502);

http_response_code($code > 0 ? $code : 502);
echo $body;
