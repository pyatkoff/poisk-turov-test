<?php
declare(strict_types=1);
require __DIR__ . '/../scripts/diagnostics/andromeda-package-probe.php';

// Offline boundary tests: checked real source bytes, synthetic selected DTO,
// injected bridge result. NO application config/DB, supplier or SSH is loaded.
$source = realpath($argv[1] ?? '');
if (!$source) throw new RuntimeException('Pass checked six-file runtime directory');
$n = 0; $calls = 0;
function rc_check(bool $ok): void { global $n; ++$n; if (!$ok) throw new RuntimeException('retained_cli_check_' . $n); }
function rc_remove(string $path): void {
    if (is_link($path) || is_file($path)) { unlink($path); return; }
    foreach (scandir($path) as $name) if ($name !== '.' && $name !== '..') rc_remove($path . '/' . $name);
    rmdir($path);
}
$temp = sys_get_temp_dir() . '/andromeda-retained-cli-' . bin2hex(random_bytes(8));
$root = $temp . '/account/www/anytoour.ru';
$target = $root . '/_preview/search3-anex-candidate';
$private = dirname($root, 2) . '/.anytoour-andromeda';
mkdir($target, 0700, true); mkdir($private, 0700, true);
$lockPath = $private . '/grouped-search-update.lock'; file_put_contents($lockPath, 'existing-lock');
$input = ['version' => 1, 'operation' => 'capture-selected-retained-offer-1717',
    'runtime_source' => ANDROMEDA_RETAINED_RUNTIME, 'local_country_id' => 4,
    'selection' => ['provider' => 'andromeda', 'search_ref' => str_repeat('a', 64),
        'generation' => 7, 'page' => 2, 'offer_ref' => 'offer_' . str_repeat('c', 64),
        'hotel_scope' => '3414', 'operator_ref' => 'operator_5', 'local_id' => 900,
        'tour' => ['hotel' => 'private-hotel', 'price' => '999123'],
        'quote_status' => 'unverified', 'package_status' => 'not_loaded']];
$args = ['andromeda-package-probe.php', '--capture-retained-package'];
$bridge = static function ($givenRoot, $givenTarget, $given) use (&$calls, $root, $target): array {
    ++$calls; rc_check($givenRoot === $root && $givenTarget === $target);
    rc_check(!isset($given['selection']['tour']) && count($given['selection']) === 8);
    return ['status' => 'captured', 'source' => ANDROMEDA_RETAINED_RUNTIME,
        'context' => $given['selection'], 'reused' => false, 'package_sha256' => str_repeat('d', 64),
        'identity_verified' => false, 'quote_verified' => false, 'selection_enabled' => false,
        // Deliberate malicious surplus: none may be copied to CLI output.
        'private_package' => ['name' => 'private-person', 'cost' => 'private-cost', 'sid' => 'private-session']];
};
$run = static fn($value) => anytour_retained_package_run($args, json_encode($value, JSON_THROW_ON_ERROR), $root, $bridge);
try {
    foreach (ANDROMEDA_RETAINED_FILES as $path => $hash) {
        $from = $source . '/' . (str_starts_with($path, 'app/') ? $path : 'v2/' . $path);
        $bytes = file_get_contents($from); rc_check(hash('sha256', $bytes) === $hash);
        if (!is_dir(dirname($target . '/' . $path))) mkdir(dirname($target . '/' . $path), 0700, true);
        file_put_contents($target . '/' . $path, $bytes);
    }
    $before = $input; $out = $run($input);
    rc_check($calls === 1 && $out['status'] === 'captured' && $input === $before);
    rc_check(!str_contains(json_encode($out), 'private-') && !str_contains(json_encode($out), '999123'));
    rc_check(!isset($out['context'], $out['private_package']) && $out['automatic_retry'] === false);
    foreach (['identity_verified', 'quote_verified', 'selection_enabled'] as $key) rc_check($out[$key] === false);
    $called = $calls;
    foreach ([[], ['probe'], ['probe', '--execute', '/old-output'], ['probe', '--capture-retained-package', 'extra']] as $badArgs) {
        rc_check(anytour_retained_package_run($badArgs, json_encode($input), $root, $bridge)['status'] === 'blocked');
    }
    foreach (['', '{', 'null', '[]', str_repeat(' ', 16385)] as $raw) {
        rc_check(anytour_retained_package_run($args, $raw, $root, $bridge)['status'] === 'blocked');
    }
    foreach (array_keys($input) as $key) { $bad = $input; unset($bad[$key]); rc_check($run($bad)['status'] === 'blocked'); }
    foreach (['runtime_source' => str_repeat('e', 40), 'local_country_id' => '4', 'operation' => 'new-search'] as $key => $value) {
        $bad = $input; $bad[$key] = $value; rc_check($run($bad)['status'] === 'blocked');
    }
    foreach (['provider', 'search_ref', 'generation', 'page', 'offer_ref', 'hotel_scope', 'operator_ref', 'local_id'] as $key) {
        $bad = $input; unset($bad['selection'][$key]); rc_check($run($bad)['status'] === 'blocked');
    }
    foreach (['page' => '2', 'generation' => 0, 'local_id' => -1, 'operator_ref' => "bad\noperator", 'hotel_scope' => ['local_id' => 900]] as $key => $value) {
        $bad = $input; $bad['selection'][$key] = $value; rc_check($run($bad)['status'] === 'blocked');
    }
    $bad = $input; $bad['selection']['supplier_offer_id'] = 'private-id'; rc_check($run($bad)['status'] === 'blocked');
    $bad = $input; $bad['sid'] = 'private-sid'; rc_check($run($bad)['status'] === 'blocked');
    rc_check($calls === $called);
    // A valid broad context uses null hotel_scope; it must not be confused with absent.
    $broad = $input; $broad['selection']['hotel_scope'] = null;
    rc_check($run($broad)['status'] === 'captured');
    $called = $calls;
    $api = $target . '/api-andromeda-search3-preview.php'; $bytes = file_get_contents($api);
    unlink($api); rc_check($run($input)['reason'] === 'runtime_not_installed');
    file_put_contents($api, $bytes . "\n"); rc_check($run($input)['reason'] === 'runtime_not_installed');
    unlink($api); file_put_contents($temp . '/same-api.php', $bytes); symlink($temp . '/same-api.php', $api);
    rc_check($run($input)['reason'] === 'runtime_not_installed'); unlink($api); file_put_contents($api, $bytes);
    rename($target . '/app', $target . '/saved-app'); symlink($target . '/saved-app', $target . '/app');
    rc_check($run($input)['reason'] === 'runtime_not_installed'); unlink($target . '/app'); rename($target . '/saved-app', $target . '/app');
    rc_check(anytour_retained_package_run($args, json_encode($input), dirname($root), $bridge)['reason'] === 'project_invalid');
    unlink($lockPath); rc_check($run($input)['reason'] === 'publication_lock_missing' && !file_exists($lockPath));
    file_put_contents($lockPath, 'existing-lock'); $busy = fopen($lockPath, 'r+b'); flock($busy, LOCK_EX);
    rc_check($run($input)['reason'] === 'publication_busy'); flock($busy, LOCK_UN); fclose($busy);
    rc_check($calls === $called);
    foreach (['operator_ref' => 'operator_6', 'generation' => 8, 'offer_ref' => 'offer_' . str_repeat('e', 64)] as $key => $wrong) {
        $reply = static function ($r, $t, $i) use ($bridge, $key, $wrong) { $receipt = $bridge($r, $t, $i); $receipt['context'][$key] = $wrong; return $receipt; };
        $out = anytour_retained_package_run($args, json_encode($input), $root, $reply);
        rc_check($out['status'] === 'unconfirmed' && $out['reason'] === 'receipt_invalid');
    }
    foreach (['identity_verified', 'quote_verified', 'selection_enabled'] as $key) {
        $reply = static function ($r, $t, $i) use ($bridge, $key) { $receipt = $bridge($r, $t, $i); $receipt[$key] = true; return $receipt; };
        rc_check(anytour_retained_package_run($args, json_encode($input), $root, $reply)['reason'] === 'receipt_invalid');
    }
    $reuse = static function ($r, $t, $i) use ($bridge) { $receipt = $bridge($r, $t, $i); $receipt['reused'] = true; return $receipt; };
    rc_check(anytour_retained_package_run($args, json_encode($input), $root, $reuse)['reused'] === true);
    foreach (['ANDROMEDA_PACKAGE_OUTCOME_UNKNOWN', 'ANDROMEDA_PACKAGE_NOT_CAPTURED', 'private-password-in-exception'] as $reason) {
        $fail = static function () use ($reason) { throw new RuntimeException($reason); };
        $out = anytour_retained_package_run($args, json_encode($input), $root, $fail);
        rc_check($out['status'] === 'unconfirmed' && !$out['automatic_retry'] && !str_contains(json_encode($out), 'private-'));
    }
    $exclusive = fopen($lockPath, 'r+b'); rc_check(flock($exclusive, LOCK_EX | LOCK_NB)); flock($exclusive, LOCK_UN); fclose($exclusive);
    foreach (ANDROMEDA_RETAINED_FILES as $path => $hash) rc_check(hash_file('sha256', $target . '/' . $path) === $hash);
    rc_check(file_get_contents($lockPath) === 'existing-lock');
    // Real CLI: historical --execute cannot reach the config or bridge at all.
    $pipes = []; $process = proc_open([PHP_BINARY, __DIR__ . '/../scripts/diagnostics/andromeda-package-probe.php', '--execute', '/old-output'],
        [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, $root);
    fclose($pipes[0]); $stdout = stream_get_contents($pipes[1]); $stderr = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
    rc_check(proc_close($process) === 1 && $stderr === '');
    rc_check(json_decode($stdout, true, 16, JSON_THROW_ON_ERROR)['reason'] === 'operation_refused');
    if (($argv[2] ?? '') === '--installed-runtime') {
        // Hosted-only real CLI wiring: exact PHP modules, disposable SQLite and
        // a synthetic ALREADY-CAPTURED record. No auth file or network transport.
        // The ordinary bridge tests cover creation; this checks this caller's
        // config/catalog/DB includes and selected-only receipt in a new process.
        rc_check(in_array('sqlite', PDO::getAvailableDrivers(), true));
        $copyTree = static function (string $from, string $to) use (&$copyTree): void {
            if (is_link($from)) throw new RuntimeException('fixture_symlink');
            if (is_file($from)) { copy($from, $to); return; }
            if (!is_dir($to)) mkdir($to, 0700, true);
            foreach (scandir($from) as $name) if ($name !== '.' && $name !== '..') $copyTree($from . '/' . $name, $to . '/' . $name);
        };
        $copyTree($source . '/app/integrations', $target . '/app/integrations');
        $copyTree($source . '/v2/data', $target . '/data');
        copy($source . '/v2/api-anex-search3-preview.php', $target . '/api-anex-search3-preview.php');
        require $target . '/api-andromeda-search3-preview.php';
        require $target . '/app/integrations/andromeda-selected-offer.php';
        $created = time() - 3; $ref = str_repeat('a', 64);
        $criteria = ['TOWNFROMINC'=>1,'STATEINC'=>5,'CHECKIN_BEG'=>'20260922','CHECKIN_END'=>'20260922',
            'ADULT'=>2,'CHILD'=>0,'NIGHTS_FROM'=>7,'NIGHTS_TILL'=>7,'CURRENCYINC'=>643,'PAGE'=>1];
        $row = ['id'=>'private-selected-offer','hotelKey'=>3414,'operatorKey'=>5,'isOperatorHotelKey'=>0,
            'price'=>83080,'currency'=>'RUB','currencyKey'=>643,'checkIn'=>'22.09.2026','nights'=>'7',
            'hotel'=>'Fixture hotel','operator'=>'Fixture operator','meal'=>'RO','mealKey'=>1,
            'room'=>'Standard','htplace'=>'DBL','adult'=>'2','child'=>'0'];
        $resolver = AnyTourAndromedaHotelResolver::fromRows([['supplier_namespace'=>'andromeda_catalog',
            'external_hotel_id'=>'3414','decision_status'=>'accepted','catalog_hotel_id'=>'900',
            'existing_catalog_hotel_id'=>'900']], str_repeat('c',64));
        $state = []; $store = new AnyTourAndromedaOfferStore($state, true);
        $store->begin($ref, 7, $created);
        $page = $store->capture(['PAGE'=>1,'PAGES_COUNT'=>1,'PRICES'=>[$row]], $criteria, $ref, 7, $created+1, $resolver);
        $context = ['provider'=>'andromeda','search_ref'=>$ref,'generation'=>7,'page'=>1,'offer_ref'=>$page['offers'][0]['offer_ref']];
        $allows = static fn(array $offer): bool => $offer['local_hotel_id'] === 900;
        $resolved = AnyTourAndromedaSelectedOffer::resolve($store, $context, $allows, time());
        $rawPackage = ['version'=>'1.01','claimDocument'=>[['catalogKey'=>'private-synthetic-key']]];
        $record = ['version'=>1,'status'=>'captured','context'=>$resolved['context'],'created_at'=>$created+2,
            'criteria_sha256'=>$resolved['criteria_sha256'],'supplier_offer_sha256'=>$resolved['supplier_offer_sha256'],
            'private_package'=>$rawPackage,'package_sha256'=>hash('sha256',json_encode($rawPackage,JSON_THROW_ON_ERROR)),
            'identity_verified'=>false,'quote_verified'=>false,'selection_enabled'=>false];
        $directory = $private . '/searches'; mkdir($directory, 0700);
        mkdir($private . '/countries', 0700);
        file_put_contents($private . '/countries/4.json', '{"local_country_id":4}');
        file_put_contents($target . '/.andromeda-private.php', '<?php return ' . var_export([
            'enabled'=>true,'catalog_path'=>$private . '/catalog.json'], true) . ';');
        $packagePath = $directory . '/' . $ref . '-' . $created . '-1-' . $context['offer_ref'] . '-package.json';
        anytour_andromeda_search3_save($directory . '/' . $ref . '-1.json', ['status'=>'complete','store'=>$state]);
        anytour_andromeda_search3_save($packagePath, ['source'=>ANDROMEDA_RETAINED_RUNTIME,'record'=>$record]);
        $pdo = new PDO('sqlite:' . $private . '/mapping.sqlite');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE catalog_hotels (id INTEGER, country_id INTEGER, is_active INTEGER)');
        $pdo->exec('CREATE TABLE andromeda_hotel_identities (supplier_namespace TEXT, external_hotel_id TEXT, local_hotel_id INTEGER, decision_status TEXT)');
        $pdo->exec('INSERT INTO catalog_hotels VALUES (900,4,1),(901,4,1)');
        $pdo->exec("INSERT INTO andromeda_hotel_identities VALUES ('andromeda_catalog','3414',900,'accepted')");
        mkdir($root . '/data', 0700);
        file_put_contents($root . '/data/db-v1.php', '<?php function v2_data_db(): PDO { return new PDO(' . var_export('sqlite:' . $private . '/mapping.sqlite',true) . '); }');
        $selected = $input;
        $selected['selection'] = AnyTourAndromedaSelectedOffer::publicSelection($store, $context, $allows, time());
        $actualCli = static function (array $value) use ($root): array {
            $pipes=[];
            $p=proc_open([PHP_BINARY,'-d','allow_url_fopen=0','-d','disable_functions=curl_init,curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client',
                __DIR__ . '/../scripts/diagnostics/andromeda-package-probe.php','--capture-retained-package'],
                [['pipe','r'],['pipe','w'],['pipe','w']],$pipes,$root);
            fwrite($pipes[0], json_encode($value,JSON_THROW_ON_ERROR)); fclose($pipes[0]);
            $stdout=stream_get_contents($pipes[1]); $stderr=stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]); $code=proc_close($p);
            rc_check($stderr === '' && !str_contains($stdout,'private-'));
            return [$code,json_decode($stdout,true,16,JSON_THROW_ON_ERROR)];
        };
        $packageBytes = file_get_contents($packagePath);
        [$code,$reply] = $actualCli($selected);
        rc_check($code===0 && $reply['status']==='captured' && $reply['reused']===true);
        rc_check($reply['package_sha256']===$record['package_sha256'] && !$reply['quote_verified'] && !$reply['identity_verified'] && !$reply['selection_enabled']);
        foreach (['operator_ref'=>'wrong-operator','local_id'=>901] as $key=>$value) {
            $bad=$selected; $bad['selection'][$key]=$value; [$code,$reply]=$actualCli($bad);
            rc_check($code===1 && $reply['status']==='unconfirmed' && !$reply['automatic_retry']);
        }
        $pdo->exec("UPDATE andromeda_hotel_identities SET decision_status='pending'");
        [$code,$reply]=$actualCli($selected);
        rc_check($code===1 && $reply['reason']==='ANDROMEDA_PACKAGE_MAPPING_UNAVAILABLE');
        $pdo->exec("UPDATE andromeda_hotel_identities SET decision_status='accepted'");
        rc_check(file_get_contents($packagePath)===$packageBytes);
        $unknown=$record; $unknown['status']='unknown';
        anytour_andromeda_search3_save($packagePath,['source'=>ANDROMEDA_RETAINED_RUNTIME,'record'=>$unknown]);
        $unknownBytes=file_get_contents($packagePath);
        [$code,$reply]=$actualCli($selected);
        rc_check($code===1 && $reply['reason']==='ANDROMEDA_PACKAGE_NOT_CAPTURED' && !$reply['automatic_retry']);
        rc_check(file_get_contents($packagePath)===$unknownBytes && !file_exists($private.'/monthly-requests.json'));
        rc_check(!file_exists($directory.'/'.$ref.'-auth.json'));
        foreach (ANDROMEDA_RETAINED_FILES as $path=>$hash) rc_check(hash_file('sha256',$target.'/'.$path)===$hash);
        echo "Installed CLI: actual loader/catalog/SQLite/current mapping, saved readback and unknown refusal passed; network functions disabled.\n";
    }
    echo 'Retained CLI: ' . $n . " checks passed; supplier/SSH/production DB=0.\n";
} finally { rc_remove($temp); }
