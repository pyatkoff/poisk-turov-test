<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/v2/seo-seasonal-verified-content-evidence-v1.php';
$now=strtotime('2026-09-02T12:00:00Z');

$tr=['country_id'=>4,'page_key'=>'resort_month:1:4:20:2026-09','claim_key'=>'temperature-reference','type'=>'climate_temperature','value'=>'fixture-value','source_class'=>'official_meteorological','source_id'=>'tr-mgm-climate','source_url'=>'https://www.mgm.gov.tr/veridegerlendirme/il-ve-ilceler-istatistik.aspx?m=ANTALYA','observed_at'=>'2026-08-01T00:00:00Z'];
$ok=v2_seo_seasonal_verified_content_evidence([$tr],$now);
if (($ok['state']??'')!=='review_ready'||($ok['publication_allowed']??true)!==false) exit(1);
if (($ok['claims'][0]['geography_scope']['level']??'')!=='resort'||($ok['claims'][0]['geography_scope']['region_id']??0)!==20) exit(2);

$bad=$tr; $bad['page_key']='month:1:4:2026-09'; if ((v2_seo_seasonal_verified_content_evidence([$bad],$now)['state']??'')!=='blocked') exit(3);
$bad=$tr; $bad['page_key']='resort_month:1:4:21:2026-09'; if ((v2_seo_seasonal_verified_content_evidence([$bad],$now)['state']??'')!=='blocked') exit(4);
$bad=$tr; $bad['source_url']='https://www.mgm.gov.tr/veridegerlendirme/il-ve-ilceler-istatistik.aspx?m=ISTANBUL'; if ((v2_seo_seasonal_verified_content_evidence([$bad],$now)['state']??'')!=='blocked') exit(5);
$bad=$tr; $bad['source_url']='https://www.mgm.gov.tr/eng/forecast-cities.aspx?m=ANTALYA'; if ((v2_seo_seasonal_verified_content_evidence([$bad],$now)['state']??'')!=='blocked') exit(6);

$mv=['country_id'=>8,'page_key'=>'month:1:8:2026-09','claim_key'=>'precipitation-reference','type'=>'climate_precipitation','value'=>'fixture-value','source_class'=>'official_meteorological','source_id'=>'mv-mms-climate','source_url'=>'https://meteorology.gov.mv/climate','observed_at'=>'2026-08-29T00:00:00Z'];
$ok=v2_seo_seasonal_verified_content_evidence([$mv],$now);
if (($ok['state']??'')!=='review_ready'||($ok['claims'][0]['geography_scope']['level']??'')!=='country') exit(7);
$bad=$mv; $bad['page_key']='resort_month:1:8:55:2026-09'; if ((v2_seo_seasonal_verified_content_evidence([$bad],$now)['state']??'')!=='blocked') exit(8);

$bad=$tr; $bad['country_id']=1; $bad['page_key']='month:1:1:2026-09'; $bad['source_id']='eg-ema-climate'; if ((v2_seo_seasonal_verified_content_evidence([$bad],$now)['state']??'')!=='blocked') exit(9);
$bad=$tr; $bad['country_id']=8; if ((v2_seo_seasonal_verified_content_evidence([$bad],$now)['state']??'')!=='blocked') exit(10);

echo "SEO_SEASONAL_VERIFIED_CONTENT_EVIDENCE_OK\n";
