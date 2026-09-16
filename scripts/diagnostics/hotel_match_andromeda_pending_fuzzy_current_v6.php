<?php
declare(strict_types=1);
/** MATCH #1971 v6: deterministic repair of v5 numeric-token mb_strlen TypeError only. */
$base=__DIR__.'/hotel_match_andromeda_pending_fuzzy_current_v5.php';
$src=file_get_contents($base);if($src===false)throw new RuntimeException('v5_source_missing');
$old='hotel-match-andromeda-pending-fuzzy-current-1971-20260916-v5';
$new='hotel-match-andromeda-pending-fuzzy-current-1971-20260916-v6';
if(substr_count($src,$old)!==1)throw new RuntimeException('v5_operation_marker_changed');
$src=str_replace($old,$new,$src);
$needle="mb_strlen(\$t,'UTF-8')";
$replacement="mb_strlen((string)\$t,'UTF-8')";
if(substr_count($src,$needle)!==2)throw new RuntimeException('v5_token_length_marker_changed');
$src=str_replace($needle,$replacement,$src);
if(str_contains($src,$old)||substr_count($src,$replacement)!==2||str_contains($src,$needle))throw new RuntimeException('v6_patch_incomplete');
if(in_array('--emit',$argv??[],true)){echo $src;exit(0);}
$tmp=sys_get_temp_dir().'/hotel-match-andromeda-pending-fuzzy-v6-'.getmypid().'.php';
if(file_put_contents($tmp,$src,LOCK_EX)!==strlen($src))throw new RuntimeException('v6_temp_write_failed');
try{require $tmp;}finally{@unlink($tmp);}
