<?php
declare(strict_types=1);
/** MATCH #1971 v2 wrapper: correct reserved PHP helper name, otherwise byte-derived v1 logic. */
$base=__DIR__.'/hotel_match_andromeda_state116_crosswalk_current_v1.php';
$src=file_get_contents($base);if($src===false)throw new RuntimeException('v1_source_missing');
$oldOp="const OP='hotel-match-andromeda-state116-crosswalk-current-1971-20260916-v1';";
$newOp="const OP='hotel-match-andromeda-state116-crosswalk-current-1971-20260916-v2';";
if(substr_count($src,$oldOp)!==1)throw new RuntimeException('v1_operation_marker_changed');$src=str_replace($oldOp,$newOp,$src);
if(substr_count($src,'protected(')<3)throw new RuntimeException('v1_protected_markers_changed');
$src=str_replace('protected(','has_protected_evidence(',$src);
if(str_contains($src,$oldOp)||str_contains($src,'function protected('))throw new RuntimeException('v2_patch_incomplete');
if(in_array('--emit',$argv??[],true)){echo $src;exit(0);}
$tmp=sys_get_temp_dir().'/hotel-match-andromeda-state116-crosswalk-v2-'.getmypid().'.php';
if(file_put_contents($tmp,$src,LOCK_EX)!==strlen($src))throw new RuntimeException('temp_write_failed');
try{require $tmp;}finally{@unlink($tmp);}
