<?php
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/site_conf.php');
require_once(__DIR__.'/phone-value.php');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

$siteParams = is_array($params ?? null) ? $params : array();
$phone = v2_site_phone($siteParams);
$href = v2_phone_href($phone);

echo json_encode(array('phone'=>$phone,'href'=>$href), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
