<?php
declare(strict_types=1);
/** MATCH #1971: derive a fresh immutable edit-distance=2 census from the proven edit1 implementation. */
$base=__DIR__.'/hotel_match_andromeda_state116_edit1_v1.php';
$src=file_get_contents($base); if($src===false) throw new RuntimeException('edit1_source_missing');
$repl=[
'hotel-match-andromeda-state116-edit1-1971-20260916-v1'=>'hotel-match-andromeda-state116-edit2-1971-20260916-v1',
'$w[\'d\']!==1||$minlen<8'=>'$w[\'d\']!==2||$minlen<12',
'hold_not_edit1'=>'hold_not_edit2',
'$d2<3'=>'$d2<5',
'hold_edit_margin'=>'hold_edit2_margin',
'hotel-match-andromeda-state116-edit1/1'=>'hotel-match-andromeda-state116-edit2/1',
'ascii_compact_edit1_len8_signature_equal_runner_edit_ge3'=>'ascii_compact_edit2_len12_signature_equal_runner_edit_ge5'
];
foreach($repl as $a=>$b){if(substr_count($src,$a)!==1)throw new RuntimeException('marker_changed:'.$a);$src=str_replace($a,$b,$src);}
$old="if(ed('shangrila hambantota','shangrilas hambantota')!==1||ed('villa 7','villa 8')!==null)";
$new="if(ed('passikudah','pasikuda')!==2||ed('shangrila hambantota','shangrilas hambantota')!==1||ed('villa 7','villa 8')!==null)";
if(substr_count($src,$old)!==1)throw new RuntimeException('selftest_marker_changed');$src=str_replace($old,$new,$src);
if(in_array('--emit',$argv??[],true)){echo $src;exit(0);} $tmp=sys_get_temp_dir().'/state116-edit2-'.getmypid().'.php';file_put_contents($tmp,$src,LOCK_EX);try{require $tmp;}finally{@unlink($tmp);}