<?php
declare(strict_types=1);

define('HMSU_LIBRARY_ONLY',true);
$report=__DIR__.'/../reports/match-saved-tourvisor-anex-union-turkey-20260912.json';
$raw=(string)file_get_contents($report);
define('HMSU_EVIDENCE_B64',base64_encode($raw));
require_once __DIR__.'/../scripts/diagnostics/hotel_match_saved_tourvisor_union_current_review.php';

function hmsu_ok(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}
$ev=hmsu_evidence();
hmsu_ok(count($ev['hotels'])===108,'hotel_union_count');
hmsu_ok(($ev['summary']['recurrence_histogram']??null)===['1'=>51,'2'=>15,'3'=>11,'4'=>20,'5'=>5,'6'=>6],'recurrence_histogram');
hmsu_ok(hash('sha256',$raw)===HMSU_EVIDENCE_SHA256,'evidence_hash');

$keys=hmsu_keys(['FUAT BEY PALACE HOTEL (EX. KUPELI PALACE)']);
hmsu_ok(in_array('fuat bey palace',$keys,true),'current_name_key');
hmsu_ok(in_array('kupeli palace',$keys,true),'former_name_key');
hmsu_ok(hmsu_key('NORTH BEACH HOTEL RESORT SPA')==='north beach','generic_vs_qualifier');
hmsu_ok(hmsu_key('ANNEX GARDEN HOTEL')==='annex garden','meaningful_qualifiers');

$idx=['fuat bey palace'=>[78667=>true],'kupeli palace'=>[78667=>true]];
hmsu_ok(hmsu_candidate(['names'=>['Fuat Bey Palace Hotel']],$idx)===[78667],'unique_exact');
$amb=['grand palace'=>[11=>true,22=>true]];
hmsu_ok(hmsu_candidate(['names'=>['Grand Palace Hotel']],$amb)===[11,22],'ambiguous_exact');

$target=['latitude'=>36.5,'longitude'=>30.5,'region_name'=>'Kemer','subregion_name'=>''];
$d=hmsu_classify(['latitude'=>36.5001,'longitude'=>30.5001,'places'=>['Kemer']],$target,1);
hmsu_ok($d['bucket']==='prepared'&&$d['direct_geo']===true,'one_date_direct_geo');
$d=hmsu_classify(['places'=>[]],$target,1);
hmsu_ok($d['bucket']==='needs_extra_evidence','one_date_no_geo');
$d=hmsu_classify(['places'=>[]],$target,2);
hmsu_ok($d['bucket']==='prepared','recurrence_two');
$d=hmsu_classify(['latitude'=>35.0,'longitude'=>29.0,'places'=>[]],$target,6);
hmsu_ok($d['bucket']==='hard_conflict','coordinate_gt5km');

echo "HMSU_TEST_OK\n";
