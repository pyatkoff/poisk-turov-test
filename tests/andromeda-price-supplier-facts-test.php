<?php
declare(strict_types=1);
require_once __DIR__.'/../app/integrations/andromeda-client.php';

$transport = static function(string $url, array $opts): array {
    if (str_contains($url, 'action=login')) return ['status'=>200,'body'=>json_encode(['sid'=>'safe_session_1'], JSON_THROW_ON_ERROR)];
    if (str_contains($url, 'action=price')) return ['status'=>200,'body'=>json_encode(['error'=>['code'=>'BAD_PRICE_SCOPE','message'=>'hotel price unavailable secret-text']], JSON_THROW_ON_ERROR)];
    throw new RuntimeException('unexpected');
};
$c=new AnyTourAndromedaClient($transport,true);
$c->login('user','password');
try {
    $c->price(AnyTourAndromedaClient::priceProbeParams());
    throw new RuntimeException('supplier_error_not_thrown');
} catch (AnyTourAndromedaPriceSupplierException $e) {
    if($e->getMessage()!=='ANDROMEDA_SUPPLIER_ERROR')throw new RuntimeException('public_message');
    $f=$e->diagnosticFacts();
    if(($f['source']??null)!=='andromeda_supplier_error'||($f['action']??null)!=='price'||($f['code']??null)!=='BAD_PRICE_SCOPE'||($f['reason_category']??null)!=='price_or_fare')throw new RuntimeException('facts');
    $raw=json_encode($f,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    foreach(['secret-text','safe_session_1','password'] as $secret)if(str_contains($raw,$secret))throw new RuntimeException('secret_leak');
}
$pkg=new AnyTourAndromedaPackageSupplierException(['code'=>'X','message'=>'package rejected']);
$pf=$pkg->diagnosticFacts();
if(($pf['source']??null)!=='andromeda_package_error'||isset($pf['action'])||$pkg->getMessage()!=='ANDROMEDA_SUPPLIER_ERROR')throw new RuntimeException('package_compat');
echo "ANDROMEDA_PRICE_SUPPLIER_FACTS_OK\n";
