<?php
declare(strict_types=1);
require_once __DIR__.'/../v2/seo-hotel-pilot-opportunity-packets-v1.php';
function pilot_packet_fail(string $m):void{fwrite(STDERR,"SEO_HOTEL_PILOT_OPPORTUNITY_PACKETS_FAIL:$m\n");exit(1);}
$items=[];$packets=[];
foreach([1,4,8] as $countryId){
    for($i=1;$i<=3;$i++){
        $hotelId=$countryId*1000+$i;
        $path="/country/c{$countryId}/hotel/h{$hotelId}/";
        $items[]=['path'=>$path,'country_id'=>$countryId,'hotel_id'=>$hotelId,'score'=>100];
        $packets[$path]=[
            'state'=>'opportunity_evidence_review_ready','path'=>$path,
            'evidence_fresh'=>true,'evidence_confirmed'=>true,'uniqueness_distinct'=>true,
            'scoring_policy_pending'=>true,'packet_sha256'=>hash('sha256',$path),
        ];
    }
}
$r=v2_seo_hotel_pilot_opportunity_packets($items,$packets);
if(($r['state']??'')!=='review_only_opportunity_evidence_complete'||($r['hotel_count']??0)!==9||($r['country_counts']??[])!==[1=>3,4=>3,8=>3])pilot_packet_fail('complete_counts');
if(($r['opportunity_evidence_ready_count']??0)!==9||($r['opportunity_evidence_blocked_count']??0)!==0||($r['scoring_policy_pending_count']??0)!==9)pilot_packet_fail('complete_summary');
if(!preg_match('/^[a-f0-9]{64}$/',(string)($r['packet_set_sha256']??'')))pilot_packet_fail('fingerprint');
foreach(['publication_allowed','indexation_allowed','sitemap_allowed','canonical_launch_allowed','route_launch_allowed'] as $flag)if(($r[$flag]??true)!==false)pilot_packet_fail('boundary_'.$flag);
if(($r['publication_candidates']??null)!==[]||($r['explicit_user_indexation_approval_required']??false)!==true)pilot_packet_fail('approval_boundary');

$one=array_key_first($packets);$packets[$one]['state']='opportunity_evidence_incomplete';$packets[$one]['evidence_fresh']=false;
$r=v2_seo_hotel_pilot_opportunity_packets($items,$packets);
if(($r['state']??'')!=='review_only_opportunity_evidence_incomplete'||($r['opportunity_evidence_ready_count']??0)!==8||($r['opportunity_evidence_blocked_count']??0)!==1)pilot_packet_fail('partial');

$bad=$items;$bad[0]['score']=99;$failed=false;
try{v2_seo_hotel_pilot_opportunity_packets($bad,$packets);}catch(InvalidArgumentException $e){$failed=true;}
if(!$failed)pilot_packet_fail('quality_gate');

$bad=$items;$bad[0]['country_id']=4;$failed=false;
try{v2_seo_hotel_pilot_opportunity_packets($bad,$packets);}catch(InvalidArgumentException $e){$failed=true;}
if(!$failed)pilot_packet_fail('balance_gate');

echo "SEO_HOTEL_PILOT_OPPORTUNITY_PACKETS_OK hotels=9 countries=3 technical100=1 evidencePacketBound=1 publication=0 indexation=0\n";
