<?php
declare(strict_types=1);
require_once __DIR__.'/../v2/seo-hotel-pilot-opportunity-evidence-intake-v1.php';
function intake_fail(string $m):void{fwrite(STDERR,"SEO_HOTEL_PILOT_EVIDENCE_INTAKE_FAIL:$m\n");exit(1);}
$now=1800000000;$rows=[];$n=0;
foreach(v2_seo_hotel_launch_pilot_spec()['countries'] as $bucket){
    $countryId=(int)$bucket['country_id'];
    foreach($bucket['paths'] as $path){
        $n++;$hotelId=(int)preg_replace('/^.*-(\d+)\/$/','$1',(string)$path);
        $pageKey="hotel:$countryId:$hotelId";$cluster="fixture hotel $hotelId tours";
        $rows[]=[
            'path'=>$path,'page_key'=>$pageKey,'query_cluster'=>$cluster,
            'demand'=>[
                'page_key'=>$pageKey,'query_cluster'=>$cluster,'source_class'=>'manual_serp_review','source_ref'=>'test-fixture-demand-'.$n,
                'observed_at_epoch'=>$now-60,'status'=>'confirmed','serp_intent'=>'commercial',
            ],
            'uniqueness'=>[
                'page_key'=>$pageKey,'query_cluster'=>$cluster,'page_path'=>$path,'source_class'=>'manual_serp_review','source_ref'=>'test-fixture-uniq-'.$n,
                'observed_at_epoch'=>$now-60,'status'=>'confirmed','decision'=>'distinct','competing_paths'=>[],
            ],
        ];
    }
}
$r=v2_seo_hotel_pilot_opportunity_evidence_intake($rows,$now);
if(($r['state']??'')!=='review_only_pilot_evidence_intake_ready'||($r['row_count']??0)!==9||($r['ready_count']??0)!==9||($r['blocked_count']??1)!==0)intake_fail('ready');
if(count($r['packets_by_path']??[])!==9||!preg_match('/^[a-f0-9]{64}$/',(string)($r['intake_sha256']??'')))intake_fail('packet_set');
foreach(['publication_allowed','indexation_allowed','sitemap_allowed','canonical_launch_allowed','route_launch_allowed'] as $flag)if(($r[$flag]??true)!==false)intake_fail('boundary_'.$flag);
if(($r['publication_candidates']??null)!==[]||($r['explicit_user_indexation_approval_required']??false)!==true)intake_fail('approval_boundary');

$missing=$rows;array_pop($missing);
$r=v2_seo_hotel_pilot_opportunity_evidence_intake($missing,$now);
if(($r['state']??'')!=='review_only_pilot_evidence_intake_blocked'||!in_array('row_count_must_be_9',$r['errors']??[],true))intake_fail('missing');

$extra=$rows;$extra[0]['path']='/country/turkey/hotel/not-in-pilot-999/';
$r=v2_seo_hotel_pilot_opportunity_evidence_intake($extra,$now);
if(($r['state']??'')!=='review_only_pilot_evidence_intake_blocked'||!str_starts_with((string)($r['errors'][0]??''),'unexpected_path:'))intake_fail('extra');

$mismatch=$rows;$mismatch[0]['demand']['query_cluster']='wrong cluster';
$r=v2_seo_hotel_pilot_opportunity_evidence_intake($mismatch,$now);
$joined=implode('|',$r['errors']??[]);
if(($r['state']??'')!=='review_only_pilot_evidence_intake_blocked'||!str_contains($joined,'demand_query_cluster_mismatch'))intake_fail('cluster_mismatch');

echo "SEO_HOTEL_PILOT_EVIDENCE_INTAKE_OK exact9=1 identityBound=1 queryClusterBound=1 publication=0 indexation=0\n";
