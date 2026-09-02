<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/v2/seo-seasonal-content-evidence-v1.php';
$now = strtotime('2026-09-02T12:00:00Z');
$claim = [
 'page_key'=>'month:1:4:2026-09','claim_key'=>'temperature-reference','type'=>'climate_temperature',
 'value'=>'fixture-value','source_class'=>'official_meteorological','source_id'=>'fixture:official-record','observed_at'=>'2026-08-01T00:00:00Z'
];
$ok=v2_seo_seasonal_content_evidence([$claim],$now);
if(($ok['review_ready']??false)!==true||($ok['publication_allowed']??true)!==false||($ok['copy_allowed_without_evidence']??true)!==false) exit(1);
$bad=$claim; $bad['type']='best_time_to_visit'; if((v2_seo_seasonal_content_evidence([$bad],$now)['state']??'')!=='blocked') exit(2);
$bad=$claim; $bad['source_class']='blog'; if((v2_seo_seasonal_content_evidence([$bad],$now)['state']??'')!=='blocked') exit(3);
$bad=$claim; $bad['type']='entry_requirement'; if((v2_seo_seasonal_content_evidence([$bad],$now)['state']??'')!=='blocked') exit(4);
$bad=$claim; $bad['valid_until']='2026-09-01T00:00:00Z'; if((v2_seo_seasonal_content_evidence([$bad],$now)['state']??'')!=='blocked') exit(5);
if((v2_seo_seasonal_content_evidence([$claim,$claim],$now)['state']??'')!=='blocked') exit(6);
echo "SEO_SEASONAL_CONTENT_EVIDENCE_OK\n";
