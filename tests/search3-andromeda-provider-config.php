<?php

declare(strict_types=1);

require_once __DIR__ . '/../v2/assets.php';

$fail = static function (string $message): never {
    fwrite(STDERR, $message . "\n");
    exit(1);
};

if (v2_optional_provider_path('V2_ANDROMEDA_MISSING_PATH') !== '') {
    $fail('missing provider configuration must stay disabled');
}

define(
    'V2_ANDROMEDA_TEST_PATH',
    '/_preview/search3-anex-candidate/api-andromeda-search3-preview.php'
);
if (v2_optional_provider_path('V2_ANDROMEDA_TEST_PATH') !== V2_ANDROMEDA_TEST_PATH) {
    $fail('valid same-origin provider path was rejected');
}

define('V2_ANDROMEDA_EXTERNAL_PATH', 'https://gateway.samo.ru/api/');
if (v2_optional_provider_path('V2_ANDROMEDA_EXTERNAL_PATH') !== '') {
    $fail('external provider URL escaped the server proxy boundary');
}

define('V2_ANDROMEDA_QUERY_PATH', '/api-andromeda-search3-preview.php?token=secret');
if (v2_optional_provider_path('V2_ANDROMEDA_QUERY_PATH') !== '') {
    $fail('provider path retained a query string');
}

echo "SEARCH3_ANDROMEDA_PROVIDER_CONFIG_OK production_default=disabled preview=isolated\n";
