<?php
declare(strict_types=1);
/** MATCH #1971 v3: deterministic repair of v1 PHP built-in helper collision only. */
$base=__DIR__.'/hotel_match_andromeda_pending_exact_current_v1.php';
$src=file_get_contents($base);if($src===false)throw new RuntimeException('v1_source_missing');
$old='hotel-match-andromeda-pending-exact-current-1971-20260916-v1';
$new='hotel-match-andromeda-pending-exact-current-1971-20260916-v3';
if(substr_count($src,$old)!==1)throw new RuntimeException('v1_operation_marker_changed');
$src=str_replace($old,$new,$src);
$n=substr_count($src,'extract(');if($n<4)throw new RuntimeException('v1_extract_marker_changed');
$src=str_replace('extract(','field_extract(',$src);
if(str_contains($src,'function extract(')||str_contains($src,$old)||substr_count($src,'field_extract(')!==$n)throw new RuntimeException('v3_patch_incomplete');
if(in_array('--emit',$argv??[],true)){echo $src;exit(0);}
$tmp=sys_get_temp_dir().'/hotel-match-andromeda-pending-exact-v3-'.getmypid().'.php';
if(file_put_contents($tmp,$src,LOCK_EX)!==strlen($src))throw new RuntimeException('v3_temp_write_failed');
try{require $tmp;}finally{@unlink($tmp);}
