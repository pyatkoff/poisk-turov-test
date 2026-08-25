<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require_once($_SERVER["DOCUMENT_ROOT"]."/site_conf.php");
require_once(__DIR__."/tv_api/tvapi.php");

$dataFilter = ["COUNTRY" => 4];
if (!empty($params["TV_CITY"])) {
    $dataFilter["FROM"] = $params["TV_CITY"];
}
if (isset($_GET["from"])) {
    $dataFilter["FROM"] = (int)$_GET["from"];
}
if (isset($_GET["country"])) {
    $dataFilter["COUNTRY"] = (int)$_GET["country"];
}

$arResult = [
    "AUTO_START" => "n",
    "HOTEL_MODE" => false,
    "HOTEL" => "",
    "COUNTRY" => $dataFilter["COUNTRY"],
    "FORM_PARAMS" => TvApi::prepareForm($dataFilter),
];

$templateFolder = "/poisk-turov-test/template/search";
require __DIR__."/template/header.php";
require __DIR__."/template/search/template.php";
require __DIR__."/template/footer.php";
