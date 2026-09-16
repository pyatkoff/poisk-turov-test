<?php
declare(strict_types=1);
/** MATCH #1971 v7: deterministic representation repairs over v5, evidence rules unchanged. */
$base=__DIR__.'/hotel_match_andromeda_pending_fuzzy_current_v5.php';
$src=file_get_contents($base);if($src===false)throw new RuntimeException('v5_source_missing');
$old='hotel-match-andromeda-pending-fuzzy-current-1971-20260916-v5';
$new='hotel-match-andromeda-pending-fuzzy-current-1971-20260916-v7';
if(substr_count($src,$old)!==1)throw new RuntimeException('v5_operation_marker_changed');
$src=str_replace($old,$new,$src);
$lenNeedle="mb_strlen(\$t,'UTF-8')";
$lenReplacement="mb_strlen((string)\$t,'UTF-8')";
if(substr_count($src,$lenNeedle)!==2)throw new RuntimeException('v5_token_length_marker_changed');
$src=str_replace($lenNeedle,$lenReplacement,$src);
$formNeedle="\$o[\$n]=1;}return array_keys(\$o);}";
$formReplacement="\$o[\$n]=1;}return array_map('strval',array_keys(\$o));}";
if(substr_count($src,$formNeedle)!==1)throw new RuntimeException('v5_form_return_marker_changed');
$src=str_replace($formNeedle,$formReplacement,$src);
if(str_contains($src,$old)||substr_count($src,$lenReplacement)!==2||str_contains($src,$lenNeedle)||substr_count($src,$formReplacement)!==1)throw new RuntimeException('v7_patch_incomplete');
if(in_array('--emit',$argv??[],true)){echo $src;exit(0);}
$tmp=sys_get_temp_dir().'/hotel-match-andromeda-pending-fuzzy-v7-'.getmypid().'.php';
if(file_put_contents($tmp,$src,LOCK_EX)!==strlen($src))throw new RuntimeException('v7_temp_write_failed');
try{require $tmp;}finally{@unlink($tmp);}
