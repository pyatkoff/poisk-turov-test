<?php
declare(strict_types=1);
/** MATCH #1971 v2 wrapper: exact v1 reconciliation logic, new immutable operation id. */
$base=__DIR__.'/hotel_match_tv_multiop_native_reconcile_v1.php';
$src=file_get_contents($base);if($src===false)throw new RuntimeException('v1_source_missing');
$old='hotel-match-tv-multiop-native-reconcile-1971-20260916-v1-egypt';
$new='hotel-match-tv-multiop-native-reconcile-1971-20260916-v2-egypt';
if(substr_count($src,$old)!==1)throw new RuntimeException('v1_operation_marker_changed');
$src=str_replace($old,$new,$src);
if(str_contains($src,$old)||substr_count($src,$new)!==1)throw new RuntimeException('v2_patch_incomplete');
if(in_array('--emit',$argv??[],true)){echo $src;exit(0);}
$tmp=sys_get_temp_dir().'/hotel-match-tv-multiop-native-reconcile-v2-'.getmypid().'.php';
if(file_put_contents($tmp,$src,LOCK_EX)!==strlen($src))throw new RuntimeException('v2_temp_write_failed');
try{require $tmp;}finally{@unlink($tmp);}
