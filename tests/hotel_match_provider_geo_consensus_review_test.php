<?php
declare(strict_types=1);
putenv('MATCH_PROVIDER_GEO_TEST_LIBRARY=1');
require_once __DIR__.'/../scripts/diagnostics/hotel_match_provider_geo_consensus_review.php';
function mpg_t(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}
function mpg_acc(string $id,int $cid,int $rid,int $sid,string $field,$value):array{return['external_hotel_id'=>$id,'local_country_id'=>$cid,'local_region_id'=>$rid,'local_subregion_id'=>$sid,'evidence_json'=>json_encode(['source'=>[$field=>$value]],JSON_THROW_ON_ERROR)];}
$accepted=[];for($i=0;$i<5;$i++)$accepted[]=mpg_acc((string)$i,4,100,200,'regionKey','55');
$c=mpg_build_consensus($accepted);mpg_t(isset($c['usable']['regionKey=55']),'five unanimous anchors should infer');mpg_t(($c['usable']['regionKey=55']['scope']??'')==='subregion','subregion should be strongest scope');mpg_t((int)$c['usable']['regionKey=55']['scope_id']===200,'subregion id mismatch');
$mixed=[];for($i=0;$i<3;$i++)$mixed[]=mpg_acc('a'.$i,4,100,200,'cityKey','77');for($i=0;$i<3;$i++)$mixed[]=mpg_acc('b'.$i,4,100,201,'cityKey','77');$cm=mpg_build_consensus($mixed);mpg_t(($cm['usable']['cityKey=77']['scope']??'')==='region','same region with split subregions should infer region');
$bad=$accepted;$bad[]=mpg_acc('x',1,300,400,'regionKey','55');$cb=mpg_build_consensus($bad);mpg_t(!isset($cb['usable']['regionKey=55']),'cross-country key must not infer');
$e=['source'=>['stateKey'=>'4','regionKey'=>'55','name'=>'Grand Emin Laleli']];$keys=mpg_geo_keys($e);mpg_t(isset($keys['regionKey'])&&!isset($keys['stateKey']),'stateKey must not be treated as local geo key');
$scope=[4=>['subregion'=>[200=>[10=>true,11=>true]]]];$allow=mpg_allowed_ids($e,4,$c,$scope);mpg_t(($allow['status']??'')==='ok'&&count($allow['ids'])===2,'geo allowed set mismatch');
$hotels=[10=>['id'=>10,'country_id'=>4,'name'=>'Grand Emin','country_name'=>'Турция','region_name'=>'Стамбул','subregion_name'=>'Laleli','latitude'=>null,'longitude'=>null],11=>['id'=>11,'country_id'=>4,'name'=>'Different Palace','country_name'=>'Турция','region_name'=>'Стамбул','subregion_name'=>'Laleli','latitude'=>null,'longitude'=>null]];$forms=[10=>['Grand Emin'],11=>['Different Palace']];$s=mpg_select(['Grand Emin Laleli'],[],$allow['ids'],$hotels,$forms);mpg_t(($s['route']??'')==='auto_accept_candidate','own geo suffix should resolve exact in provider geo scope');mpg_t((int)($s['target']??0)===10,'geo exact target mismatch');
$s=mpg_select(['Completely Unknown'],[],$allow['ids'],$hotels,$forms);mpg_t(($s['route']??'')==='needs_extra_evidence','geo-only must not blindly accept');
echo "MATCH_PROVIDER_GEO_CONSENSUS_TEST_OK\n";
