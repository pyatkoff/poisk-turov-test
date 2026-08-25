<?php
/**
 * Standalone test header.
 * Uses only the frozen copy stored inside /poisk-turov-test/site-template/.
 * Nothing in /local/templates or /bitrix/templates is modified.
 */
$APPLICATION->SetTitle('Поиск туров — тест');
$APPLICATION->AddHeadString('<link rel="stylesheet" href="/poisk-turov-test/template/search/style.css">', true);
$APPLICATION->AddHeadString('<link rel="stylesheet" href="/poisk-turov-test/template/search/slick/slick.css">', true);
$APPLICATION->AddHeadString('<link rel="stylesheet" href="/poisk-turov-test/template/style.css">', true);

require __DIR__ . '/../site-template/header.php';
?>
<link rel="stylesheet" href="/poisk-turov-test/template/form-overrides.css?v=2">
<link rel="stylesheet" href="/poisk-turov-test/template/form-layout-fixes.css?v=5">
<link rel="stylesheet" href="/poisk-turov-test/template/card-overrides.css?v=3">
<link rel="stylesheet" href="/poisk-turov-test/template/results-controls.css?v=1">
<link rel="stylesheet" href="/poisk-turov-test/template/hotel-details-overrides.css?v=1">
<link rel="stylesheet" href="/poisk-turov-test/template/tour-final-overrides.css?v=1">
<link rel="stylesheet" href="/poisk-turov-test/template/mobile-critical-fixes.css?v=2">
<script src="/poisk-turov-test/template/layout-fixes.js?v=6" defer></script>
<script src="/poisk-turov-test/template/results-controls.js?v=1" defer></script>
