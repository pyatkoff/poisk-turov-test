<?php
/**
 * Private Anytoour -> AnyTour lead receiver.
 * Authenticates a server-to-server HMAC request, opens the local Bitrix session,
 * then forwards the payload to the unchanged V2 lead adapter using that session.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

const V2_RECEIVER_MAX_BODY = 65536;
const V2_RECEIVER_TARGET = 'https://anytour.online/poisk-turov-test/v2/lead-adapter-v2.php';
const V2_RECEIVER_REFERER = 'https://anytour.online/poisk-turov-test/v2/';

function receiver_out(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    receiver_out(['ok' => true, 'receiver' => 'anytoour-hmac-bitrix-session', 'version' => 1]);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') receiver_out(['ok' => false, 'error' => 'Method not allowed'], 405);
if (stripos((string)($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json') !== 0) receiver_out(['ok' => false, 'error' => 'JSON request required'], 415);
if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > V2_RECEIVER_MAX_BODY) receiver_out(['ok' => false, 'error' => 'Request too large'], 413);

$raw = (string)file_get_contents('php://input');
if ($raw === '' || strlen($raw) > V2_RECEIVER_MAX_BODY) receiver_out(['ok' => false, 'error' => 'Invalid request body'], 400);

$secretFile = __DIR__ . '/.anytoour-bridge-secret';
$secret = is_file($secretFile) ? trim((string)file_get_contents($secretFile)) : '';
if ($secret === '') receiver_out(['ok' => false, 'error' => 'Bridge is not configured'], 503);

$signature = trim((string)($_SERVER['HTTP_X_ANYTOOUR_SIGNATURE'] ?? ''));
$expected = hash_hmac('sha256', $raw, $secret);
if ($signature === '' || !hash_equals($expected, $signature)) receiver_out(['ok' => false, 'error' => 'Unauthorized'], 401);

$data = json_decode($raw, true);
if (!is_array($data)) receiver_out(['ok' => false, 'error' => 'Invalid JSON'], 400);

$docRoot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
$prolog = $docRoot . '/bitrix/modules/main/include/prolog_before.php';
if ($docRoot === '' || !is_file($prolog)) receiver_out(['ok' => false, 'error' => 'Bitrix bootstrap unavailable'], 500);
require_once $prolog;
if (!function_exists('bitrix_sessid')) receiver_out(['ok' => false, 'error' => 'Bitrix session unavailable'], 500);

$sessid = (string)bitrix_sessid();
$sessionName = session_name();
$sessionId = session_id();
if ($sessid === '' || $sessionName === '' || $sessionId === '') receiver_out(['ok' => false, 'error' => 'Bitrix session unavailable'], 500);

$data['sessid'] = $sessid;
$forwardBody = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($forwardBody === false) receiver_out(['ok' => false, 'error' => 'Lead payload encoding failed'], 500);

$ch = curl_init(V2_RECEIVER_TARGET);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 35,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $forwardBody,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Accept: application/json',
        'Origin: https://anytour.online',
        'Referer: ' . V2_RECEIVER_REFERER,
        'Cookie: ' . $sessionName . '=' . $sessionId,
    ],
    CURLOPT_USERAGENT => 'AnytoourLeadReceiver/1.0',
]);
$body = curl_exec($ch);
$errno = curl_errno($ch);
$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($errno) receiver_out(['ok' => false, 'error' => 'Lead adapter connection error', 'curlErrno' => $errno], 502);
if (!is_string($body) || json_decode($body, true) === null) receiver_out(['ok' => false, 'error' => 'Invalid lead adapter response', 'upstreamStatus' => $code], 502);

http_response_code($code > 0 ? $code : 502);
echo $body;
