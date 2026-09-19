<?php
declare(strict_types=1);

putenv('MATCH_PROVIDER_GEO_CATEGORY_TEST_LIBRARY=1');
require_once __DIR__ . '/../scripts/diagnostics/hotel_match_provider_geo_category_consensus_review.php';

function tc(bool $ok,string $msg):void{if(!$ok)throw new RuntimeException($msg);}
function ce(string $name,array $extra):string{return json_encode(['source'=>array_merge(['name'=>$name,'stateKey'=>'6'],$extra)],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}

tc(mpgc_local_category_class('5*')==='star:5','5 star');
tc(mpgc_local_category_class('4 stars')==='star:4','4 stars');
tc(mpgc_local_category_class('HV-1')==='text:hv 1','text category');

$tok=mpgc_provider_category_tokens(['source'=>['name'=>'X Hotel','starKey'=>7,'starName'=>'5*','categoryCode'=>'A','countryName'=>'Turkey']]);
tc(isset($tok['star=7'])&&isset($tok['star=5*'])&&isset($tok['category=a']),'provider category tokens');

$accepted=[];
for($i=1;$i<=20;$i++)$accepted[]=['external_hotel_id'=>(string)$i,'evidence_json'=>ce('Hotel '.$i,['starKey'=>7]),'local_country_id'=>4,'local_category'=>'5*'];
$c=mpgc_build_category_consensus($accepted);
tc(isset($c['usable']['4|star=7']),'20 anchors usable');
tc(($c['usable']['4|star=7']['winner_class']??'')==='star:5','learned star semantics');

$nineteen=array_slice($accepted,0,19);$c19=mpgc_build_category_consensus($nineteen);
tc(!isset($c19['usable']['4|star=7'])&&($c19['rejected']['4|star=7']['reason']??'')==='insufficient_anchors','min anchors');

$pending=mpgc_category_class_for_pending(['source'=>['name'=>'Target','starKey'=>7]],4,$c);
tc($pending['status']==='ok'&&$pending['class']==='star:5','pending category consensus');

$numeric=['usable'=>['regionKey=1'=>['country_id'=>4,'scope'=>'region','scope_id'=>10,'field'=>'regionKey','value'=>'1','anchors'=>30,'countries'=>[4=>30],'regions'=>[10=>30],'subregions'=>[]]],'rejected'=>[]];
$labels=['usable'=>['town=laleli'=>['country_id'=>4,'scope'=>'subregion','scope_id'=>20,'semantic'=>'town','label'=>'laleli','anchors'=>30,'countries'=>[4=>30],'regions'=>[10=>30],'subregions'=>[20=>30],'fields'=>['town'=>30]]],'rejected'=>[]];
$scope=[4=>['region'=>[10=>[100=>true,101=>true,102=>true]],'subregion'=>[20=>[100=>true,101=>true]]]];
$geo=mpgc_combined_geo_ids(['source'=>['name'=>'Target','regionKey'=>'1','town'=>'Laleli']],4,$numeric,$labels,$scope);
tc($geo['status']==='ok'&&$geo['ids']===[100,101],'geo intersection');

$hotels=[100=>['category'=>'5*'],101=>['category'=>'4*'],102=>['category'=>'5*']];
tc(mpgc_category_filter_ids([100,101], 'star:5',$hotels)===[100],'category narrows only inside geo');

$conf=$accepted;$conf[19]['local_category']='4*';$cc=mpgc_build_category_consensus($conf);
tc(!isset($cc['usable']['4|star=7']),'95 percent category mapping rejected');

echo "hotel_match_provider_geo_category_consensus_review_test OK\n";
