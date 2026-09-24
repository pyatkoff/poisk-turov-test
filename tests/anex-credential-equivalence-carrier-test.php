<?php
declare(strict_types=1);

function ace(bool $ok,string $label):void{if(!$ok)throw new RuntimeException('ANEX_CREDENTIAL_CARRIER_TEST:'.$label);}

$collector=file_get_contents(__DIR__.'/../scripts/ops/anex_local_offer_collect.php');
ace(is_string($collector)&&$collector!=='','collector_read');
$trigger=strpos($collector,'if($region===999999999)');
$pdo=strpos($collector,'$pdo=v2_data_db();');
ace(is_int($trigger)&&is_int($pdo)&&$trigger<$pdo,'carrier_before_db');
ace(str_contains($collector,"'source'=>'anex-credential-equivalence-carrier-v1'"),'carrier_source');
ace(str_contains($collector,"'supplier_calls'=>0,'database_reads'=>0,'database_writes'=>0"),'zero_io_contract');
ace(str_contains($collector,'exit(23);'),'deliberate_nonzero');

$matched=preg_match("/\\$credentialProbeCode=<<<'ANEX_CREDENTIAL_PROBE'\\n(.*?)\\nANEX_CREDENTIAL_PROBE;/s",$collector,$m);
ace($matched===1&&isset($m[1])&&is_string($m[1]),'probe_extract');
$probe=$m[1];
ace(str_contains($probe,".anytoour-anex")===false,'child_has_no_fixed_snapshot_path');
ace(str_contains($probe,"hash_equals(\$snapshotApi,\$api)"),'api_compare');
ace(str_contains($probe,"hash_equals(\$snapshotB2b,\$b2b)"),'b2b_compare');
ace(!str_contains($probe,'sha256')&&!str_contains($probe,'strlen($api)')&&!str_contains($probe,'strlen($b2b)'),'no_hash_or_length_export');

$dir=sys_get_temp_dir().'/anex-credential-carrier-'.bin2hex(random_bytes(8));
ace(mkdir($dir,0700),'mkdir');
$snapshot=$dir.'/search3-preview.php';
file_put_contents($snapshot,"<?php\ndefine('ANEX_API_TOKEN','fixture-api');\ndefine('ANEX_B2B_TOKEN','fixture-b2b');\n");
chmod($snapshot,0600);

$run=static function(string $code,string $path,array $env):array{
    $pipes=[];
    $proc=proc_open(
        [PHP_BINARY,'-d','display_errors=0','-d','log_errors=0','-r',$code,$path],
        [0=>['file','/dev/null','r'],1=>['pipe','w'],2=>['pipe','w']],
        $pipes,null,$env
    );
    if(!is_resource($proc))throw new RuntimeException('probe_proc');
    $out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);
    fclose($pipes[1]);fclose($pipes[2]);
    return ['code'=>proc_close($proc),'out'=>$out,'err'=>$err];
};

try{
    $same=$run($probe,$snapshot,['ANEX_API_TOKEN'=>'fixture-api','ANEX_B2B_TOKEN'=>'fixture-b2b']);
    ace($same['code']===0&&$same['err']==='','same_exit');
    $sameJson=json_decode($same['out'],true,8,JSON_THROW_ON_ERROR);
    ace($sameJson===['snapshot_valid'=>true,'api_equal'=>true,'b2b_equal'=>true],'same_result');

    $different=$run($probe,$snapshot,['ANEX_API_TOKEN'=>'current-api-other','ANEX_B2B_TOKEN'=>'fixture-b2b']);
    ace($different['code']===0&&$different['err']==='','different_exit');
    $differentJson=json_decode($different['out'],true,8,JSON_THROW_ON_ERROR);
    ace($differentJson===['snapshot_valid'=>true,'api_equal'=>false,'b2b_equal'=>true],'different_result');

    $missing=$run($probe,$dir.'/missing.php',['ANEX_API_TOKEN'=>'fixture-api','ANEX_B2B_TOKEN'=>'fixture-b2b']);
    ace($missing['code']===3&&$missing['out']==='','missing_snapshot_fails_closed');
}finally{
    @unlink($snapshot);@rmdir($dir);
}

echo "ANEX_CREDENTIAL_EQUIVALENCE_CARRIER_OK same=1 different=1 missing=1 supplier=0 db=0\n";
