<?php
declare(strict_types=1);

putenv('MATCH_COORDINATE_REVIEW_TEST_LIBRARY=1');
require_once __DIR__ . '/../scripts/diagnostics/hotel_match_coordinate_current_review.php';

function mcc_t(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}
$hotels=[
  1=>['id'=>1,'country_id'=>4,'name'=>'Alpha Beach Hotel','latitude'=>36.0000,'longitude'=>30.0000],
  2=>['id'=>2,'country_id'=>4,'name'=>'Beta Hotel','latitude'=>36.0060,'longitude'=>30.0060],
  3=>['id'=>3,'country_id'=>4,'name'=>'Alpha Garden Hotel','latitude'=>36.0002,'longitude'=>30.0002],
  4=>['id'=>4,'country_id'=>4,'name'=>'Gamma Resort','latitude'=>36.0200,'longitude'=>30.0200],
];
$forms=[1=>['Alpha Beach Hotel'],2=>['Beta Hotel'],3=>['Alpha Garden Hotel'],4=>['Gamma Resort']];
$grid=mcc_grid($hotels);

$r=mcc_candidate(['Alpha Beach Hotel'],[['latitude'=>36.0000,'longitude'=>30.0000]],4,$hotels,$forms,$grid);
mcc_t(($r['route']??'')==='auto_accept_candidate','unique exact coordinate/name must accept');
mcc_t((int)($r['target']??0)===1,'exact target');
mcc_t(($r['reason']??'')==='coordinate_supported_unique_exact_name','exact reason');
mcc_t((int)($r['exact_candidate_count']??0)===1,'unique exact count');

$r=mcc_candidate(['Alpha Beach'],[['latitude'=>36.0000,'longitude'=>30.0000]],4,$hotels,$forms,$grid);
mcc_t(($r['route']??'')==='auto_accept_candidate','normalized exact coordinate/name must accept');
mcc_t((int)($r['target']??0)===1,'normalized exact target');

$r=mcc_candidate(['Alpha Garden'],[['latitude'=>36.0000,'longitude'=>30.0000]],4,$hotels,$forms,$grid);
mcc_t(($r['route']??'')==='needs_extra_evidence','nearby qualifier conflict/cluster must not blindly accept nearest');

$r=mcc_candidate(['Completely Renamed'],[['latitude'=>36.0000,'longitude'=>30.0000]],4,[1=>$hotels[1],2=>$hotels[2],4=>$hotels[4]],[1=>$forms[1],2=>$forms[2],4=>$forms[4]],mcc_grid([1=>$hotels[1],2=>$hotels[2],4=>$hotels[4]]));
mcc_t(($r['route']??'')==='needs_extra_evidence','coordinate-only rename must not auto accept');

$dupHotels=[
  5=>['id'=>5,'country_id'=>4,'name'=>'Twin Hotel','latitude'=>36.1000,'longitude'=>30.1000],
  6=>['id'=>6,'country_id'=>4,'name'=>'Twin Hotel','latitude'=>36.1002,'longitude'=>30.1002],
];
$dupForms=[5=>['Twin Hotel'],6=>['Twin Hotel']];
$r=mcc_candidate(['Twin Hotel'],[['latitude'=>36.1000,'longitude'=>30.1000]],4,$dupHotels,$dupForms,mcc_grid($dupHotels));
mcc_t(($r['route']??'')==='needs_extra_evidence','duplicate exact names nearby must not auto accept');
mcc_t((int)($r['exact_candidate_count']??0)===2,'duplicate exact count');

$r=mcc_candidate(['Alpha Beach'],[],4,$hotels,$forms,$grid);
mcc_t(($r['reason']??'')==='missing_coordinates','missing coordinate hold');

$r=mcc_candidate(['Alpha Beach'],[['latitude'=>10.0,'longitude'=>10.0]],4,$hotels,$forms,$grid);
mcc_t(($r['reason']??'')==='no_local_coordinate_neighbor','far source hold');

$r=mcc_candidate(['Alpha Garden Hotel'],[['latitude'=>36.0000,'longitude'=>30.0000]],4,[1=>$hotels[1]],[1=>$forms[1]],mcc_grid([1=>$hotels[1]]));
mcc_t(($r['route']??'')==='needs_extra_evidence','meaningful qualifier mismatch must hold');
mcc_t(($r['reason']??'')==='coordinate_nearest_qualifier_conflict','qualifier conflict reason');

echo "MATCH_COORDINATE_CURRENT_REVIEW_TEST_OK\n";
