<?php
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/site_conf.php');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

function v2_phone_scalar($value){
    if (is_string($value) || is_numeric($value)) {
        $text = trim((string)$value);
        if ($text !== '' && preg_match('/\d/', $text)) return $text;
    }
    return '';
}

function v2_find_phone($value){
    $scalar = v2_phone_scalar($value);
    if ($scalar !== '') return $scalar;
    if (!is_array($value)) return '';

    foreach (array('PHONE','VALUE','TEXT','NUMBER','DISPLAY_VALUE') as $key) {
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

$siteParams = is_array($params ?? null) ? $params : array();
$phone = v2_find_phone($siteParams['PHONE'] ?? null);
if ($phone === '') $phone = '8 (800) 100-61-50';
$href = preg_replace('/[^0-9+]/', '', $phone);

echo json_encode(array('phone'=>$phone,'href'=>$href), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
