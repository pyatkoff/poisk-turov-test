<?php
function v2_privacy_url(): string
{
    $host = strtolower(trim((string)($_SERVER['HTTP_HOST'] ?? '')));
    $host = preg_replace('/:\d+$/', '', $host);
    if ($host === 'anytoour.ru') {
        return 'https://anytour.online/politika-konfidentsialnosti/';
    }
    return '/politika-konfidentsialnosti/';
}
