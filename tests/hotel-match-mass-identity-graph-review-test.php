<?php
declare(strict_types=1);
define('FC_LIBRARY_ONLY', true);
require_once __DIR__.'/../scripts/diagnostics/hotel_match_mass_identity_graph_review.php';
function t(bool $v,string $m):void{if(!$v){fwrite(STDERR,"FAIL: $m\n");exit(1);}}

t(hmigr_decide_votes([])['bucket']==='needs_extra_evidence','empty graph stays unresolved');
$single=[100=>['sources'=>['direct_local'=>true]]];
t(hmigr_decide_votes($single)['bucket']==='needs_extra_evidence','single evidence class cannot auto accept');
$consensus=[100=>['sources'=>['direct_local'=>true,'supplier_cluster_local'=>true]]];
$d=hmigr_decide_votes($consensus);t($d['bucket']==='auto_accept','two evidence classes auto accept');t((int)$d['target_local_hotel_id']===100,'consensus target preserved');
$split=[100=>['sources'=>['direct_local'=>true]],101=>['sources'=>['supplier_cluster_local'=>true]]];
t(hmigr_decide_votes($split)['bucket']==='hard_conflict','target disagreement hard blocks');

$rows=[
 'anex:1'=>['provider'=>'anex','bucket'=>'auto_accept','target_local_hotel_id'=>50],
 'anex:2'=>['provider'=>'anex','bucket'=>'auto_accept','target_local_hotel_id'=>50],
 'andromeda:x'=>['provider'=>'andromeda','bucket'=>'auto_accept','target_local_hotel_id'=>50],
];
[$u,$n]=hmigr_apply_uniqueness($rows);t($n===2,'same-provider collision demotes both');t($u['anex:1']['bucket']==='needs_extra_evidence'&&$u['anex:2']['bucket']==='needs_extra_evidence','anex collision demoted');t($u['andromeda:x']['bucket']==='auto_accept','opposite provider may share local target');

$source=['names'=>hmadcr_expand_names(['APERION BEACH HOTEL']),'places'=>['Manavgat'],'latitude'=>36.713018,'longitude'=>31.563078];
$target=['id'=>6319,'country_id'=>4,'name'=>'APERION BEACH (EX. SEA PARADISE)','region_name'=>'Manavgat','subregion_name'=>'','latitude'=>36.7130180,'longitude'=>31.5630780,'category'=>4];
$g=hmigr_source_target_guard($source,$target,hmadcr_expand_names([$target['name']]));t($g['ok']===true,'exact alias plus direct geo is strong');t(($g['distance_m']??100)>-1&&($g['distance_m']??100)<5,'coordinate formatting difference is harmless');
$bad=$target;$bad['name']='APERION GARDEN';$q=hmigr_source_target_guard($source,$bad,[$bad['name']]);t($q['ok']===false,'critical BEACH/GARDEN qualifier conflict blocks');
$far=$target;$far['latitude']=40.0;$f=hmigr_source_target_guard($source,$far,hmadcr_expand_names([$target['name']]));t($f['ok']===false&&$f['reason']==='coordinate_conflict_gt_5km','over 5km blocks');

t(hmigr_other([1,2,3],2)===[1,3],'self claim removed from occupancy');
echo "mass identity graph review tests passed\n";
