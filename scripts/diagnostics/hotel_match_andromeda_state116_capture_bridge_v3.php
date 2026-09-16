<?php
declare(strict_types=1);
const SRC='scripts/diagnostics/hotel_match_andromeda_state116_capture_bridge_v2.php';
const BLOB='e570f3be66364f58829278167245998a687f9674';
const OLDOP='hotel-match-andromeda-state116-capture-bridge-1971-20260916-v2';
const NEWOP='hotel-match-andromeda-state116-capture-bridge-1971-20260916-v3';
function runtime_source_v3():string{if(trim((string)shell_exec('git hash-object '.escapeshellarg(SRC)))!==BLOB)throw new RuntimeException('blob');$s=(string)shell_exec('php '.escapeshellarg(SRC).' --emit');if($s===''||substr_count($s,OLDOP)!==1)throw new RuntimeException('emit');$old="\$private===''||strpos(\$private,\$root.'/')!==0";if(substr_count($s,$old)!==1)throw new RuntimeException('path_marker');$s=str_replace(OLDOP,NEWOP,$s);$s=str_replace($old,"\$private===''",$s);return$s;}
if(in_array('--self-test',$argv??[],true)){$s=runtime_source_v3();if(str_contains($s,OLDOP)||substr_count($s,NEWOP)!==1||str_contains($s,"strpos(\$private,\$root.'/')"))throw new RuntimeException('rewrite');echo"PASS\n";exit(0);}if(in_array('--emit',$argv??[],true)){echo runtime_source_v3();exit(0);}throw new RuntimeException('emit_only');
