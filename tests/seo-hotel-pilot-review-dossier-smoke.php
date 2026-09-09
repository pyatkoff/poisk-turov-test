<?php
declare(strict_types=1);
require_once __DIR__.'/../v2/seo-hotel-pilot-review-dossier-v1.php';
function fail_pilot_dossier(string $x): never { fwrite(STDERR,"SEO_HOTEL_PILOT_DOSSIER_FAIL:$x\n"); exit(1); }
$now=1788370800;$rows=[];
foreach(v2_seo_hotel_launch_pilot_spec()['countries'] as $bucket)foreach($bucket['paths'] as $path)$rows[]=['path'=>$path,'country_id'=>$bucket['country_id'],'captured_at_epoch'=>$now-60,'source_ref'=>'fixture://fresh'.$path,'quality_score'=>100,'identity_verified'=>true,'catalog_integrity_ok'=>true,'fresh_offer_evidence'=>true,'review_status_ok'=>true,'noindex_ok'=>true,'out_of_sitemap_ok'=>true,'publication_candidate_absent'=>true];
$d=v2_seo_hotel_pilot_review_dossier($rows,$now);
if(($d['state']??'')!=='review_only_hotel_pilot_evidence_ready'||($d['observed_hotel_count']??0)!==9)fail_pilot_dossier('ready');
if(($d['publication_candidates']??null)!==[])fail_pilot_dossier('candidates');
foreach(['publication_allowed','indexation_allowed','sitemap_allowed','canonical_launch_allowed','route_launch_allowed'] as $flag)if(($d[$flag]??true)!==false)fail_pilot_dossier($flag);
$bad=$rows;$bad[0]['quality_score']=99;if((v2_seo_hotel_pilot_review_dossier($bad,$now)['state']??'')!=='review_only_hotel_pilot_evidence_blocked')fail_pilot_dossier('quality');
$bad=$rows;$bad[0]['noindex_ok']=false;if((v2_seo_hotel_pilot_review_dossier($bad,$now)['state']??'')!=='review_only_hotel_pilot_evidence_blocked')fail_pilot_dossier('noindex');
echo "SEO_HOTEL_PILOT_DOSSIER_OK hotels=9 countries=3 publication=0 indexation=0\n";
