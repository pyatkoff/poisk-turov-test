<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/v2/seo-seasonal-source-registry-v1.php';

$tr = v2_seo_seasonal_source_policy(4, 'tr-mgm-climate', 'climate_temperature');
if (($tr['state'] ?? '') !== 'review_ready' || ($tr['publication_allowed'] ?? true) !== false) exit(1);
if (!in_array('www.mgm.gov.tr', $tr['allowed_hosts'] ?? [], true)) exit(2);

$mv = v2_seo_seasonal_source_policy(8, 'mv-mms-climate', 'climate_precipitation');
if (($mv['state'] ?? '') !== 'review_ready' || !in_array('www.meteorology.gov.mv', $mv['allowed_hosts'] ?? [], true)) exit(3);

if ((v2_seo_seasonal_source_policy(1, 'eg-ema-climate', 'climate_temperature')['state'] ?? '') !== 'blocked') exit(4);
if ((v2_seo_seasonal_source_policy(4, 'mv-mms-climate', 'climate_temperature')['state'] ?? '') !== 'blocked') exit(5);
if ((v2_seo_seasonal_source_policy(8, 'mv-mms-climate', 'best_time_to_visit')['state'] ?? '') !== 'blocked') exit(6);
if ((v2_seo_seasonal_source_policy(999, 'x', 'climate_temperature')['state'] ?? '') !== 'blocked') exit(7);

echo "SEO_SEASONAL_SOURCE_REGISTRY_OK\n";
