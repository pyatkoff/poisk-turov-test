<?php
declare(strict_types=1);
require_once __DIR__ . '/../scripts/diagnostics/hotel_match_andromeda_star_semantics.php';
$checks=0;$assert=static function(bool $ok,string $name)use(&$checks){$checks++;if(!$ok)throw new RuntimeException($name);};
$a=hmstar_field(['starKey'=>5,'starName'=>'4★']);
$assert(is_array($a)&&$a['field']==='starName'&&$a['category']===4,'starKey_excluded');
$assert(hmstar_field(['starKey'=>5])===null,'starKey_alone_not_parsed');
$b=hmstar_selected(['category'=>'3 stars'],['star'=>'5']);
$assert($b['origin']==='identity_evidence'&&$b['category']===3,'identity_precedence');
$c=hmstar_selected([],['star'=>'2*']);
$assert($c['origin']==='latest_search_observation'&&$c['category']===2,'observation_fallback');
$assert(hmstar_field(['starName'=>'5 Deluxe'])===null,'ambiguous_label_rejected');
$bucket=[];for($i=0;$i<50;$i++)hmstar_add($bucket,4,$i===0?5:4);$f=hmstar_finish($bucket);
$assert($f['samples']===50&&$f['agreements']===49&&$f['semantic_status']==='strong_consistent','strong_consistency');
$bucket=[];for($i=0;$i<20;$i++)hmstar_add($bucket,4,$i<16?4:5);$f=hmstar_finish($bucket);
$assert($f['semantic_status']==='inconsistent_with_local_category','inconsistent_threshold');
$assert(hmstar_semantic_status(19,19)==='insufficient_sample','sample_floor');
echo "hotel_match_andromeda_star_semantics_test: {$checks} checks PASS\n";
