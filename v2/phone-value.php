<?php
/** Shared scalar phone resolver for V2 server-rendered surfaces and phone-config endpoint. */
function v2_phone_scalar($value): string
{
    if (is_string($value) || is_numeric($value)) {
        $text = trim((string)$value);
        if ($text !== '' && preg_match('/\d/', $text)) return $text;
    }
    return '';
}

function v2_find_phone($value): string
{
    $scalar = v2_phone_scalar($value);
    if ($scalar !== '') return $scalar;
    if (!is_array($value)) return '';

    foreach (['PHONE','VALUE','TEXT','NUMBER','DISPLAY_VALUE'] as $key) {
        if (array_key_exists($key, $value)) {
            $found = v2_find_phone($value[$key]);
            if ($found !== '') return $found;
        }
    }
    foreach ($value as $item) {
        $found = v2_find_phone($item);
        if ($found !== '') return $found;
    }
    return '';
}

function v2_site_phone(array $siteParams, string $fallback = '8 (800) 100-61-50'): string
{
    $phone = v2_find_phone($siteParams['PHONE'] ?? null);
    return $phone !== '' ? $phone : $fallback;
}

function v2_phone_href(string $phone): string
{
    return (string)preg_replace('/[^0-9+]/', '', $phone);
}
