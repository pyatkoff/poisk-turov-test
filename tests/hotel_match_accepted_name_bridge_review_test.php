<?php
declare(strict_types=1);

putenv('MATCH_ACCEPTED_NAME_BRIDGE_TEST_LIBRARY=1');
require_once __DIR__ . '/../scripts/diagnostics/hotel_match_accepted_name_bridge_review.php';

function manb_t(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}
function manb_ar(string $id,int $local,int $cid,string $name):array{return['external_hotel_id'=>$id,'local_hotel_id'=>$local,'local_country_id'=>$cid,'evidence_json'=>json_encode(['source'=>['name'=>$name]],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)];}
$hotels=[
  10=>['id'=>10,'country_id'=>4,'country_name'=>'Турция','region_name'=>'Анталья','subregion_name'=>'Белек','name'=>'Local Canonical One'],
  11=>['id'=>11,'country_id'=>4,'country_name'=>'Турция','region_name'=>'Анталья','subregion_name'=>'Сиде','name'=>'Local Canonical Two'],
  12=>['id'=>12,'country_id'=>8,'country_name'=>'Мальдивы','region_name'=>'','subregion_name'=>'','name'=>'Island Local'],
];
$accepted=[
  manb_ar('100',10,4,'Blue Pearl Collection'),
  manb_ar('101',10,4,'Blue Pearl Collection Hotel'),
  manb_ar('102',11,4,'Other Unique Place'),
  manb_ar('103',10,4,'Twin Shared Name'),
  manb_ar('104',11,4,'Twin Shared Name'),
  manb_ar('105',10,4,'Antalya'),
  manb_ar('106',12,8,'Maldives'),
];
$a=manb_build_anchors($accepted,$hotels);
manb_t(isset($a['usable'][4]['blue pearl collection']),'unique multi-token supplier alias should be usable');
manb_t((int)$a['usable'][4]['blue pearl collection']['local_hotel_id']===10,'usable target mismatch');
manb_t(!isset($a['usable'][4]['twin shared name']),'ambiguous supplier name must not be usable');
manb_t(isset($a['ambiguous'][4]['twin shared name']),'ambiguous supplier name should be recorded');
manb_t(isset($a['weak'][4]['antalya']),'single-token geography must be weak');
manb_t(isset($a['weak'][8]['maldives']),'country-only key must be weak');

$r=manb_select(['Blue Pearl Collection'],4,$a);
manb_t(($r['route']??'')==='auto_accept_candidate','exact accepted supplier alias should bridge');
manb_t((int)($r['target']??0)===10,'bridge target mismatch');
manb_t((int)($r['anchor_count']??0)===2,'anchor count should preserve duplicate accepted ids');

$r=manb_select(['Twin Shared Name'],4,$a);
manb_t(($r['route']??'')==='needs_extra_evidence','ambiguous accepted key must not auto bridge');

$r=manb_select(['Antalya'],4,$a);
manb_t(($r['route']??'')==='needs_extra_evidence','single token must not auto bridge');

$r=manb_select(['Blue Pearl Collection','Other Unique Place'],4,$a);
manb_t(($r['route']??'')==='hard_conflict','different accepted anchors on one pending row must conflict');

$r=manb_select(['Blue Pearl Collection'],8,$a);
manb_t(($r['route']??'')==='needs_extra_evidence','accepted name bridge must be country-scoped');

echo "MATCH_ACCEPTED_NAME_BRIDGE_REVIEW_TEST_OK\n";
