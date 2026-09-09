<?php
declare(strict_types=1);
// Disposable local fixture only. Never deploy or reuse its authority on a server.
if (getenv('ANEX_REVIEW_TEST_DSN') !== 'mysql:host=127.0.0.1;port=3306;dbname=anex_review_test;charset=utf8mb4') throw new RuntimeException('test_only');
$base = getenv('ANEX_REVIEW_OWNER_TEST_ROOT');
if (!$base || !is_dir($base)) throw new RuntimeException('test_root_required');
$home = $base . '/home';
$root = $home . '/www/anytoour.ru';
$preview = $root . '/_preview/search3-anex-candidate';
$private = $home . '/.anytoour-anex/review-owner';
if (PHP_SAPI === 'cli') {
    umask(0077);
    mkdir($preview . '/app/admin/anex-review', 0700, true);
    mkdir($preview . '/app/integrations', 0700, true);
    mkdir($private . '/sessions', 0700, true);
    foreach (glob(__DIR__.'/../app/admin/anex-review/*') as $file) if (is_file($file)) copy($file,$preview.'/app/admin/anex-review/'.basename($file));
    foreach (glob(__DIR__.'/../app/integrations/*.php') as $file) copy($file,$preview.'/app/integrations/'.basename($file));
    foreach (['anex-owner-login.php','anex-hotel-review.php'] as $name) copy(__DIR__.'/../v2/'.$name,$preview.'/'.$name);
    $config = '<?php return ["private_directory"=>__DIR__,"pdo_factory"=>static function(): PDO { return new PDO(getenv("ANEX_REVIEW_TEST_DSN"),"root",getenv("ANEX_REVIEW_TEST_PASSWORD")?:""); }];';
    file_put_contents($private.'/config.php',$config);
    require_once __DIR__.'/../app/admin/anex-review/owner-auth.php';
    $token = bin2hex(random_bytes(32));
    AnexReviewOwnerAuth::bootstrapHash($private,$root,hash('sha256',$token),time()+600);
    file_put_contents($base.'/fixture-private.json',json_encode(['token'=>$token,'password'=>bin2hex(random_bytes(20))]));
    echo "OWNER_HTTP_FIXTURE_READY synthetic=true live_identity=false\n";
    exit;
}
if (PHP_SAPI !== 'cli-server' || !in_array($_SERVER['REMOTE_ADDR']??'', ['127.0.0.1','::1'],true)) {http_response_code(403);exit;}
$route = parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if (!in_array($route,['/_preview/search3-anex-candidate/anex-owner-login.php','/_preview/search3-anex-candidate/anex-hotel-review.php'],true)) {http_response_code(404);exit;}
$_SERVER['SCRIPT_NAME']=$route;
$_SERVER['DOCUMENT_ROOT']=$root;
// Only the loopback fixture simulates HTTPS; production never trusts forwarded headers.
$_SERVER['HTTPS']='on';
putenv('ANYTOUR_ANEX_REVIEW_AUTH_FILE');
require $root.$route;
