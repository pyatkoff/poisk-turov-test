<?php
declare(strict_types=1);
const SRC='scripts/diagnostics/hotel_match_andromeda_operation_original_bridge_v1.php';
const BLOB='b026dbcc5974ea1bf181278400e4f2d9e7acbdae';
const OLD='hotel-match-andromeda-operation-original-bridge-1971-20260916-v1';
const NEWOP='hotel-match-andromeda-operation-original-bridge-1971-20260916-v3';
function runtime_source():string{
  $s=(string)file_get_contents(SRC);
  if(trim((string)shell_exec('git hash-object '.escapeshellarg(SRC)))!==BLOB)throw new RuntimeException('source_blob_mismatch');
  if(substr_count($s,OLD)!==1)throw new RuntimeException('operation_marker_count');
  $needle=";}}}}\n\$db=null;"; if(substr_count($s,$needle)!==1)throw new RuntimeException('brace_marker_count');
  if(substr_count($s,'pos(')!==4)throw new RuntimeException('pos_marker_count');
  $s=str_replace(OLD,NEWOP,$s);
  $s=str_replace($needle,";}}}\n\$db=null;",$s);
  $s=str_replace('pos(','native_posint(',$s);
  return $s;
}
if(in_array('--self-test',$argv??[],true)){$s=runtime_source();if(str_contains($s,OLD)||substr_count($s,NEWOP)!==1||substr_count($s,'native_posint(')!==4||str_contains($s,'pos('))throw new RuntimeException('rewrite_test');echo"PASS\n";exit(0);}
if(in_array('--emit',$argv??[],true)){echo runtime_source();exit(0);}throw new RuntimeException('wrapper_emit_only');
