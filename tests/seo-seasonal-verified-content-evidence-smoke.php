<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/v2/seo-seasonal-verified-content-evidence-v1.php';
$now=strtotime('2026-09-02T12:00:00Z');
$claim=['country_id'=>4,'page_key'=>'month:1:4:2026-09','claim_key'=>'temperature-reference','type'=>'climate_temperature','value'=>'fixture-value','source_class'=>'official_meteorological','source_id'=>'tr-mgm-climate','source_url'=>'https://www.mgm.gov.tr/veridegerlendirme/il-ve-ilceler-istatistik.aspx?m=ANTALYA','observed_at'=>'2026-08-01T00:00:00Z'];
$ok=v2_seo_seasonal_verified_content_evidence([$claim],$now);
if (($ok['state']??'')!=='review_ready'||($ok['publication_allowed']??true)!==false) exit(1);
$bad=$claim; $bad['country_id']=8; if ((v2_seo_seasonal_verified_content_evidence([$bad],$now)['state']??'')!=='blocked') exit(2);
$bad=$claim; $bad['page_key']='month:1:8:2026-09'; if ((v2_seo_seasonal_verified_content_evidence([$bad],$now)['state']??'')!=='blocked') exit(3);
$bad=$claim; $bad['source_url']='https://meteorology.gov.mv/climate'; if ((v2_seo_seasonal_verified_content_evidence([$bad],$now)['state']??'')!=='blocked') exit(4);
$bad=$claim; $bad['source_class']='official_government'; if ((v2_seo_seasonal_verified_content_evidence([$bad],$now)['state']??'')!=='blocked') exit(5);
$bad=$claim; $bad['country_id']=1; $bad['page_key']='month:1:1:2026-09'; $bad['source_id']='eg-ema-climate'; if ((v2_seo_seasonal_verified_content_evidence([$bad],$now)['state']??'')!=='blocked') exit(6);
$bad=$claim; $bad['source_url']='https://www.mgm.gov.tr/eng/forecast-cities.aspx?m=ANTALYA'; if ((v2_seo_seasonal_verified_content_evidence([$bad],$now)['state']??'')!=='blocked') exit(7);
echo "SEO_SEASONAL_VERIFIED_CONTENT_EVIDENCE_OK\n";
