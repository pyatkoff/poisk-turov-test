<?php
declare(strict_types=1);
$root=dirname(__DIR__);$script=$root.'/scripts/diagnostics/hotel_match_geo_current_preflight.php';$manifest=$root.'/reports/hotel-match-geo-current-preflight-manifest-1971.json';
if(!is_file($script)||!is_file($manifest))throw new RuntimeException('missing_source');
$raw=file_get_contents($script);$m=json_decode((string)file_get_contents($manifest),true,512,JSON_THROW_ON_ERROR);
$checks=[
    ($m['schema']??'')==='hotel-match-geo-current-preflight-manifest/1',
    ($m['not_write_authority']??false)===true,
    count($m['anex']??[])===209,
    count($m['andromeda']??[])===35,
    strpos($raw,"START TRANSACTION READ ONLY")!==false,
    strpos($raw,"AnyTourAnexSearchMappingRegistry::fromPdo")!==false,
    strpos($raw,"previewResolver")!==false,
    strpos($raw,"manual_protected")!==false,
    strpos($raw,"excluded")!==false,
    strpos($raw,"target_drift")!==false,
    strpos($raw,"namespace_ambiguous")!==false,
    strpos($raw,"supplier_calls'=>0")!==false,
    strpos($raw,"tourvisor_calls'=>0")!==false,
    stripos($raw,'INSERT INTO')===false,
    stripos($raw,'UPDATE ')===false,
    stripos($raw,'DELETE FROM')===false,
    stripos($raw,'COMMIT')===false,
];
foreach($checks as $i=>$ok)if(!$ok)throw new RuntimeException('guard_'.$i);
$seen=[];foreach(['anex','andromeda'] as $p)foreach($m[$p] as $pair){if(!is_array($pair)||count($pair)!==2||(int)$pair[0]<=0||(int)$pair[1]<=0)throw new RuntimeException('pair');$k=$p.':'.$pair[0];if(isset($seen[$k]))throw new RuntimeException('duplicate');$seen[$k]=1;}
exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' --self-test',$out,$rc);if($rc!==0||strpos(implode("\n",$out),'self-test PASS')===false)throw new RuntimeException('self_test');
echo 'MATCH geo CURRENT preflight guards PASS checks='.count($checks).' manifest='.count($seen)."\n";
