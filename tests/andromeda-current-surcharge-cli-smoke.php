<?php
declare(strict_types=1);
require __DIR__ . '/../scripts/diagnostics/andromeda-package-probe.php';

// Real published source bytes + synthetic identity + injected bridge. No supplier/SSH/DB access.
$source = realpath($argv[1] ?? '');
if (!$source) throw new RuntimeException('Pass published surcharge runtime directory');
$n = 0; $calls = 0;
function sc_check(bool $ok): void { global $n; ++$n; if (!$ok) throw new RuntimeException('surcharge_cli_check_' . $n); }
function sc_remove(string $path): void {
    if (is_link($path) || is_file($path)) { unlink($path); return; }
    foreach (scandir($path) as $name) if ($name !== '.' && $name !== '..') sc_remove($path . '/' . $name);
    rmdir($path);
}
$temp = sys_get_temp_dir() . '/andromeda-surcharge-cli-' . bin2hex(random_bytes(8));
$root = $temp . '/account/www/anytoour.ru';
$target = $root . '/_preview/search3-anex-candidate';
$private = dirname($root, 2) . '/.anytoour-andromeda';
mkdir($target, 0700, true); mkdir($private, 0700, true);
$lockPath = $private . '/grouped-search-update.lock'; file_put_contents($lockPath, 'existing');
$input = ['version'=>1, 'operation'=>ANDROMEDA_SURCHARGE_OPERATION,
    'runtime_source'=>ANDROMEDA_SURCHARGE_RUNTIME, 'local_country_id'=>4,
    'selection'=>['provider'=>'andromeda','search_ref'=>str_repeat('a',64),'generation'=>7,'page'=>2,
        'offer_ref'=>'offer_'.str_repeat('b',64),'hotel_scope'=>null,'operator_ref'=>'operator_5','local_id'=>900,
        'tour'=>['price'=>'private-display-value']]];
$args = ['probe', '--capture-retained-surcharge'];
$bridge = static function ($r, $t, $i) use (&$calls, $root, $target): array {
    ++$calls; sc_check($r === $root && $t === $target && count($i['selection']) === 8);
    return ['status'=>'captured','source'=>ANDROMEDA_SURCHARGE_RUNTIME,'context'=>$i['selection'],
        'reused'=>false,'package_sha256'=>str_repeat('c',64),
        'identity_verified'=>false,'quote_verified'=>false,'selection_enabled'=>false,
        'private_package'=>['sid'=>'private-session'],
        'surcharge'=>['status'=>'complete','reused'=>false,'fact'=>[
            'provider'=>'andromeda','state'=>'estimated','arithmetic_applied'=>true,'final_price_verified'=>false,
            'private_surplus'=>'private-transport']]];
};
$run = static fn($i) => anytour_retained_package_run($args, json_encode($i, JSON_THROW_ON_ERROR), $root, $bridge);
try {
    sc_check(count(ANDROMEDA_SURCHARGE_FILES) === 12);
    foreach (ANDROMEDA_SURCHARGE_FILES as $path => $hash) {
        $bytes = file_get_contents($source . '/' . (str_starts_with($path,'app/') ? $path : 'v2/'.$path));
        sc_check(hash('sha256',$bytes) === $hash);
        if (!is_dir(dirname($target.'/'.$path))) mkdir(dirname($target.'/'.$path),0700,true);
        file_put_contents($target.'/'.$path,$bytes);
    }
    $out = $run($input);
    sc_check($out['status'] === 'captured' && $calls === 1 && $out['runtime_source'] === ANDROMEDA_SURCHARGE_RUNTIME);
    sc_check($out['surcharge_status'] === 'complete' && $out['surcharge_reused'] === false);
    sc_check(!str_contains(json_encode($out),'private-') && !isset($out['fact'], $out['context']));
    sc_check($out['automatic_retry'] === false && $out['quote_verified'] === false && $out['selection_enabled'] === false);
    $called = $calls;
    // Neither CLI flag can reinterpret the other operation/pin as permission.
    $old = $input; $old['operation'] = 'capture-selected-retained-offer-1717'; $old['runtime_source'] = ANDROMEDA_RETAINED_RUNTIME;
    sc_check($run($old)['reason'] === 'input_invalid');
    sc_check(anytour_retained_package_run(['probe','--capture-retained-package'],json_encode($input),$root,$bridge)['reason'] === 'input_invalid');
    foreach (['runtime_source'=>str_repeat('f',40),'operation'=>'bron','local_country_id'=>'4'] as $k=>$v) {
        $bad=$input; $bad[$k]=$v; sc_check($run($bad)['reason'] === 'input_invalid');
    }
    foreach (['generation'=>true,'page'=>0,'offer_ref'=>'bad','sid'=>'private-secret'] as $k=>$v) {
        $bad=$input; $bad['selection'][$k]=$v; sc_check($run($bad)['reason'] === 'input_invalid');
    }
    foreach ([[],['probe','--execute'],['probe','--capture-retained-surcharge','extra']] as $badArgs)
        sc_check(anytour_retained_package_run($badArgs,json_encode($input),$root,$bridge)['status'] === 'blocked');
    foreach (ANDROMEDA_SURCHARGE_FILES as $path => $hash) {
        $file=$target.'/'.$path; $bytes=file_get_contents($file); file_put_contents($file,$bytes."\n");
        sc_check($run($input)['reason'] === 'runtime_not_installed'); file_put_contents($file,$bytes);
    }
    $file=$target.'/app/integrations/andromeda-search-surcharge.php'; rename($file,$file.'.saved'); symlink($file.'.saved',$file);
    sc_check($run($input)['reason'] === 'runtime_not_installed'); unlink($file); rename($file.'.saved',$file);
    $busy=fopen($lockPath,'r+b'); flock($busy,LOCK_EX);
    sc_check($run($input)['reason'] === 'publication_busy'); flock($busy,LOCK_UN); fclose($busy);
    sc_check($calls === $called);
    $missing = static function($r,$t,$i) use($bridge) { $x=$bridge($r,$t,$i); unset($x['surcharge']); return $x; };
    sc_check(anytour_retained_package_run($args,json_encode($input),$root,$missing)['reason'] === 'receipt_invalid');
    foreach ([null, ['provider'=>'andromeda','state'=>'estimated','arithmetic_applied'=>true,'final_price_verified'=>true]] as $badFact) {
        $bad=static function($r,$t,$i) use($bridge,$badFact) { $x=$bridge($r,$t,$i); $x['surcharge']['fact']=$badFact; return $x; };
        sc_check(anytour_retained_package_run($args,json_encode($input),$root,$bad)['reason'] === 'receipt_invalid');
    }
    $unavailable=static function($r,$t,$i) use($bridge) { $x=$bridge($r,$t,$i); $x['surcharge']=['status'=>'unavailable','reused'=>true,'fact'=>null]; return $x; };
    $out=anytour_retained_package_run($args,json_encode($input),$root,$unavailable);
    sc_check($out['surcharge_status'] === 'unavailable' && $out['surcharge_reused'] === true && $out['automatic_retry'] === false);
    $failing=static function() { throw new RuntimeException('private-password'); };
    $out=anytour_retained_package_run($args,json_encode($input),$root,$failing);
    sc_check($out['status'] === 'unconfirmed' && !str_contains(json_encode($out),'private-') && !$out['automatic_retry']);

    // Exercise actual invoke argument wiring in a separate disposable stub environment.
    // The boundary above checked real hashes; these local stubs implement no transport.
    file_put_contents($target.'/api-andromeda-search3-preview.php', '<?php function anytour_andromeda_search3_catalog($c,$r){return $r["params"];}');
    file_put_contents($target.'/app/integrations/andromeda-saved-package-runtime.php', '<?php function anytour_andromeda_capture_selected_package(...$args){return $args;}');
    file_put_contents($target.'/.andromeda-private.php','<?php return ["enabled"=>true];');
    mkdir($root.'/data',0700); file_put_contents($root.'/data/db-v1.php','<?php function v2_data_db(){return null;}');
    $given=anytour_retained_package_invoke($root,$target,anytour_retained_package_input(json_encode($input),true));
    sc_check($given[4] === ANDROMEDA_SURCHARGE_RUNTIME && $given[5] === true && $given[6] === null && $given[7] === null && $given[8] === true);
    sc_check($given[1] === ['countryId'=>4] && count($given[2]) === 8);
    $given=anytour_retained_package_invoke($root,$target,anytour_retained_package_input(json_encode($old)));
    sc_check($given[4] === ANDROMEDA_RETAINED_RUNTIME && $given[8] === false);
    echo "Andromeda current surcharge CLI: {$n} checks; supplier_calls=0\n";
} finally { sc_remove($temp); }
