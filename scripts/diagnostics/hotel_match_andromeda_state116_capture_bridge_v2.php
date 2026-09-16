<?php
declare(strict_types=1);
const SRC='scripts/diagnostics/hotel_match_andromeda_state116_capture_bridge_v1.php';
const BLOB='05b3ad2d3914710b416760cfc4abfdb77a09d5d5';
const OLDOP='hotel-match-andromeda-state116-capture-bridge-1971-20260916-v1';
const NEWOP='hotel-match-andromeda-state116-capture-bridge-1971-20260916-v2';
function runtime_source():string{$s=(string)file_get_contents(SRC);if(trim((string)shell_exec('git hash-object '.escapeshellarg(SRC)))!==BLOB)throw new RuntimeException('blob');if(substr_count($s,OLDOP)!==1)throw new RuntimeException('op_count');$old="function forms(\$v):array{\$s=(string)\$v;\$out=[norm(\$s)];if(preg_match('/\\(\\s*(?:ex|ех)\\.?\\s+([^()]+)\\)/ui',\$s,\$m))\$out[]=norm(\$m[1]);return array_values(array_unique(array_filter(\$out)));}";$new="function forms(\$v):array{\$s=(string)\$v;\$primary=preg_replace('/\\s*\\(\\s*(?:ex|ех)\\.?\\s+[^()]+\\)\\s*$/ui','',\$s);\$out=[norm(\$primary)];if(preg_match('/\\(\\s*(?:ex|ех)\\.?\\s+([^()]+)\\)/ui',\$s,\$m))\$out[]=norm(\$m[1]);return array_values(array_unique(array_filter(\$out)));}";if(substr_count($s,$old)!==1)throw new RuntimeException('forms_marker');$s=str_replace(OLDOP,NEWOP,$s);$s=str_replace($old,$new,$s);return$s;}
if(in_array('--self-test',$argv??[],true)){$s=runtime_source();if(str_contains($s,OLDOP)||substr_count($s,NEWOP)!==1)throw new RuntimeException('rewrite');echo"PASS\n";exit(0);}if(in_array('--emit',$argv??[],true)){echo runtime_source();exit(0);}throw new RuntimeException('emit_only');
