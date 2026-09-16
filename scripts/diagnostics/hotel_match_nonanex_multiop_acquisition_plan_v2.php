<?php
declare(strict_types=1);
/** MATCH #1971 v2 wrapper: v1 planner with missing typed identity treated as acquisition-eligible evidence target. */
$base=__DIR__.'/hotel_match_nonanex_multiop_acquisition_plan_v1.php';
$src=file_get_contents($base);if($src===false)throw new RuntimeException('v1_source_missing');
$old1=<<<'SRC'
$current=$by[$ns.'|'.$native]??[];$pending=false;$protected=false;$accepted=false;$other=false;
SRC;
$new1=<<<'SRC'
$current=$by[$ns.'|'.$native]??[];$missing=count($current)===0;$pending=false;$protected=false;$accepted=false;$other=false;
SRC;
$old2=<<<'SRC'
elseif(!$pending)$reason='source_not_pending_null';
SRC;
$new2=<<<'SRC'
elseif(!$pending&&!$missing)$reason='source_not_pending_null';
SRC;
$old3=<<<'SRC'
'prior_holds'=>$holds,'previous_search_dates'
SRC;
$new3=<<<'SRC'
'prior_holds'=>$holds,'current_identity_missing'=>$missing,'current_pending_null'=>$pending,'previous_search_dates'
SRC;
$repl=[
 'hotel-match-nonanex-multiop-acquisition-plan-1971-20260916-v1'=>'hotel-match-nonanex-multiop-acquisition-plan-1971-20260916-v2',
 $old1=>$new1,
 $old2=>$new2,
 $old3=>$new3
];
foreach($repl as $a=>$b){if(substr_count($src,$a)!==1)throw new RuntimeException('v1_marker_changed:'.hash('sha256',$a));$src=str_replace($a,$b,$src);}
if(str_contains($src,'hotel-match-nonanex-multiop-acquisition-plan-1971-20260916-v1')||substr_count($src,'current_identity_missing')!==1||substr_count($src,'!$pending&&!$missing')!==1)throw new RuntimeException('v2_patch_incomplete');
if(in_array('--emit',$argv??[],true)){echo $src;exit(0);}
$tmp=sys_get_temp_dir().'/hotel-match-nonanex-multiop-acquisition-plan-v2-'.getmypid().'.php';
if(file_put_contents($tmp,$src,LOCK_EX)!==strlen($src))throw new RuntimeException('v2_temp_write_failed');
try{require $tmp;}finally{@unlink($tmp);}
