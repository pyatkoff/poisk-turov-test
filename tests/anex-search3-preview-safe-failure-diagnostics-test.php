<?php
declare(strict_types=1);
require_once __DIR__ . '/../v2/api-anex-search3-preview.php';
function ok($v,$m){if(!$v)throw new RuntimeException($m);}
$f=anytour_anex_search3_failure_diagnostics(new RuntimeException('ANEX_HTTP_ERROR'),[
 'action'=>'SearchTour_PRICES','http_status'=>502,'curl_errno'=>0,'supplier_code'=>77,
 'secret'=>'DO_NOT_EXPORT','url'=>'https://example.invalid/?oauth_token=SECRET'
],true);
ok($f===['failure_class'=>'http_error','retry_attempted'=>true,'action'=>'SearchTour_PRICES','http_status'=>502,'curl_errno'=>0,'supplier_code'=>77],'bounded diagnostic mismatch');
$json=json_encode($f);
ok(strpos($json,'DO_NOT_EXPORT')===false && strpos($json,'SECRET')===false,'secret leaked');
$g=anytour_anex_search3_failure_diagnostics(new RuntimeException('ANEX_INVALID_RESPONSE'),[
 'action'=>'UnknownAction','http_status'=>999,'curl_errno'=>1000,'supplier_code'=>100000
],false);
ok($g===['failure_class'=>'invalid_response','retry_attempted'=>false],'invalid fields must be omitted');
echo "ANEX_PREVIEW_SAFE_FAILURE_DIAGNOSTICS_OK\n";
