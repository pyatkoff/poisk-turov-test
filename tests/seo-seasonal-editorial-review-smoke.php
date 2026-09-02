<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/v2/seo-seasonal-editorial-review-v1.php';

$now=1000;
$item=[
    'state'=>'review_only_seasonal_dataset_item','family'=>'turkey','page_key'=>'resort_month:1:4:20:2026-09','page_type'=>'resort_month',
    'country_id'=>4,'region_id'=>20,'departure_id'=>1,'year'=>2026,'month'=>9,'parent_path'=>'/country/turkey/antalya/',
    'evidence_checked_at_epoch'=>900,'expires_at_epoch'=>2000,'publication_allowed'=>false,'copy_allowed'=>false,
];
$dataset=[
    'state'=>'review_only_seasonal_dataset','family_count'=>1,'item_count'=>1,'evidence_valid_until_epoch'=>2000,
    'publication_candidates'=>[],'publication_allowed'=>false,'feed_publish_allowed'=>false,'copy_allowed'=>false,'items'=>[$item],
];
$claim=[
    'page_key'=>'resort_month:1:4:20:2026-09','claim_key'=>'temperature-reference','type'=>'climate_temperature','value'=>'fixture-value',
    'source_class'=>'official_meteorological','source_id'=>'tr-mgm-climate','source_url'=>'https://www.mgm.gov.tr/veridegerlendirme/il-ve-ilceler-istatistik.aspx?m=ANTALYA',
    'source_host'=>'www.mgm.gov.tr','observed_at'=>'2026-08-01T00:00:00+00:00','valid_until'=>null,
    'geography_scope'=>['level'=>'resort','country_id'=>4,'region_id'=>20],
];
$evidence=['state'=>'review_ready','review_ready'=>true,'claims'=>[$claim],'blocked'=>[],'publication_allowed'=>false,'copy_allowed_without_evidence'=>false,'hotel_tours_publication_allowed'=>false];

$ok=v2_seo_seasonal_editorial_review_bundle($dataset,$evidence,['resort_month:1:4:20:2026-09'],$now,3);
if (($ok['state']??'')!=='review_only_seasonal_editorial_bundle'||($ok['item_count']??0)!==1) exit(1);
if (($ok['publication_candidates']??null)!==[]||($ok['publication_allowed']??true)!==false||($ok['indexation_allowed']??true)!==false||($ok['sitemap_allowed']??true)!==false||($ok['copy_generation_allowed']??true)!==false) exit(2);
if (($ok['items'][0]['claim_count']??0)!==1||($ok['items'][0]['region_id']??0)!==20) exit(3);

$bad=$claim; $bad['geography_scope']['region_id']=21; $badEvidence=$evidence; $badEvidence['claims']=[$bad];
try { v2_seo_seasonal_editorial_review_bundle($dataset,$badEvidence,[$item['page_key']],$now); exit(4); } catch (InvalidArgumentException $e) {}
$bad=$claim; $bad['geography_scope']=['level'=>'country','country_id'=>4,'region_id'=>null]; $badEvidence=$evidence; $badEvidence['claims']=[$bad];
try { v2_seo_seasonal_editorial_review_bundle($dataset,$badEvidence,[$item['page_key']],$now); exit(5); } catch (InvalidArgumentException $e) {}
try { v2_seo_seasonal_editorial_review_bundle($dataset,$evidence,['month:1:4:2026-09'],$now); exit(6); } catch (InvalidArgumentException $e) {}
$stale=$dataset; $stale['evidence_valid_until_epoch']=999;
try { v2_seo_seasonal_editorial_review_bundle($stale,$evidence,[$item['page_key']],$now); exit(7); } catch (InvalidArgumentException $e) {}
$unsafe=$evidence; $unsafe['publication_allowed']=true;
try { v2_seo_seasonal_editorial_review_bundle($dataset,$unsafe,[$item['page_key']],$now); exit(8); } catch (InvalidArgumentException $e) {}
$empty=$evidence; $empty['claims']=[];
try { v2_seo_seasonal_editorial_review_bundle($dataset,$empty,[$item['page_key']],$now); exit(9); } catch (InvalidArgumentException $e) {}

echo "SEO_SEASONAL_EDITORIAL_REVIEW_OK\n";
