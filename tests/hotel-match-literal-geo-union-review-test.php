<?php
declare(strict_types=1);
define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/../scripts/diagnostics/hotel_match_literal_geo_union_review.php';

function ok(bool $v,string $m):void{if(!$v){fwrite(STDERR,"FAIL: $m\n");exit(1);}}

$a=[126448=>['target_local_hotel_id'=>126448]];
$b=[126448=>['target_local_hotel_id'=>126448]];
$r=hmlgur_consensus($a,$b);ok($r['state']==='unique'&&$r['target_local_hotel_id']===126448,'same target across lanes must be unique');
$r=hmlgur_consensus([126448=>[]],[108356=>[]]);ok($r['state']==='conflict','different targets across lanes must block');ok($r['candidate_target_ids']===[108356,126448],'conflict ids must be deterministic');
$r=hmlgur_consensus([10=>[],11=>[]],[]);ok($r['state']==='conflict','ambiguous literal raw targets must block');
$r=hmlgur_consensus([],[]);ok($r['state']==='none','no raw targets stays unresolved');
$r=hmlgur_consensus([],[2993=>[]]);ok($r['state']==='unique'&&$r['target_local_hotel_id']===2993,'single lane unique target allowed for later geo guard');

echo "hotel-match-literal-geo-union-review tests passed\n";
