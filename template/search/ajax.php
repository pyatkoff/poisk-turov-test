<?php
/**
 * Test-sandbox AJAX entry point.
 * Executes the frozen search handler from site-template, but redirects
 * Tourvisor PHP dependencies to /poisk-turov-test/tv_api/.
 * No files outside /poisk-turov-test are modified.
 */
$sourceFile = dirname(__DIR__, 2) . '/site-template/components/rhat.search/form/form/ajax.php';
$source = file_get_contents($sourceFile);
if ($source === false) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Test AJAX source not found'], JSON_UNESCAPED_UNICODE);
    exit;
}

$source = str_replace(
    [
        "'/bitrix/php_interface/tv_api/search.php'",
        "'/bitrix/php_interface/tv_api/tvapi.php'",
    ],
    [
        "'/poisk-turov-test/tv_api/search.php'",
        "'/poisk-turov-test/tv_api/tvapi.php'",
    ],
    $source
);

$source = preg_replace('/^\s*<\?(?:php)?/i', '', $source, 1);
eval($source);
