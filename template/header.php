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
$APPLICATION->AddHeadString('<link rel="stylesheet" href="/poisk-turov-test/template/form-overrides.css?v=1">', true);
$APPLICATION->AddHeadString('<link rel="stylesheet" href="/poisk-turov-test/template/card-overrides.css?v=2">', true);

require __DIR__ . '/../site-template/header.php';
