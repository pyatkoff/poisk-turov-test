<?php
declare(strict_types=1);
function ck(bool $ok,string $m):void{if(!$ok)throw new RuntimeException($m);}
$root=dirname(__DIR__);$script=$root.'/scripts/diagnostics/hotel_match_retained_direct_native_apply_v1.php';
$cmd=escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' --self-test 2>&1';exec($cmd,$out,$rc);ck($rc===0,'self-test failed: '.implode("\n",$out));ck(str_contains(implode("\n",$out),'PASS'),'PASS marker');
$src=(string)file_get_contents($script);
foreach([
    "const PREFLIGHT_RESULT_SHA256 = '080f48c2cdc2516780dd3340a8cf75298730c958643310c20c63f893fac2d211'",
    "const SCHEMA_AUDIT_RESULT_SHA256 = '16d18250003402c6d854d78cd5e58bcc5b7518843fc1f3860043bdb7943ab8fd'",
    "const PLAN_SHA256 = '7e7b796b322efb2520c41c90a5b8b137bbed142fb2a6bbeb9326e7ee048c5dbe'",
    'SET TRANSACTION ISOLATION LEVEL SERIALIZABLE',
    'FOR UPDATE',
    'identity_no_longer_absent',
    'same_namespace_target_occupied',
    "INSERT INTO andromeda_hotel_identities",
    "completed_no_write",
    'post_commit_mismatch',
] as$n)ck(str_contains($src,$n),'missing contract '.$n);
ck(!str_contains($src,"'25192'"),'held 25192 must not be in writer plan');
ck(substr_count($src,"'operator_315'")>=1 && substr_count($src,"'operator_342'")>=1,'operator namespaces');
ck(!preg_match('/curl|wget|https?:\/\//i',$src),'writer must not call network');
echo "hotel_match_retained_direct_native_apply_v1_test: PASS\n";
