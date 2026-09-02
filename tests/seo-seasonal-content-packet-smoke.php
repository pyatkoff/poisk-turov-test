<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/v2/seo-seasonal-content-packet-v1.php';

$key='month:1:8:2026-09';
$scope=['level'=>'country','country_id'=>8,'region_id'=>null];
$claim=['page_key'=>$key,'claim_key'=>'temperature_range','type'=>'climate_temperature','value'=>'25–32 °C','source_id'=>'mv-mms-climate','source_url'=>'https://www.meteorology.gov.mv/climate','geography_scope'=>$scope];
$content=['state'=>'rendered_review_only_seasonal_content','page_key'=>$key,'title'=>'T','h1'=>'H','intro'=>'I','sections'=>[['heading'=>'A','text'=>'B','claim_keys'=>['temperature_range']]],'claims'=>[$claim],'source_note'=>'S','publication_candidates'=>[],'publication_allowed'=>false,'indexation_allowed'=>false,'sitemap_allowed'=>false,'route_creation_allowed'=>false];
$item=['state'=>'review_only_seasonal_editorial_item','page_key'=>$key,'page_type'=>'month','country_id'=>8,'region_id'=>null,'parent_path'=>'/country/maldives/','claims'=>[$claim],'claim_count'=>1,'evidence_valid_until_epoch'=>2000,'publication_allowed'=>false,'indexation_allowed'=>false,'sitemap_allowed'=>false,'copy_generation_allowed'=>false];
$bundle=['state'=>'review_only_seasonal_editorial_bundle','item_count'=>1,'evidence_valid_until_epoch'=>2000,'publication_candidates'=>[],'publication_allowed'=>false,'indexation_allowed'=>false,'sitemap_allowed'=>false,'copy_generation_allowed'=>false,'items'=>[$item]];
$ok=v2_seo_seasonal_content_packet($content,$bundle,$key);
if(($ok['state']??'')!=='review_only_seasonal_content_packet'||($ok['page_key']??'')!==$key||($ok['evidence_valid_until_epoch']??0)!==2000) exit(1);
if(($ok['publication_candidates']??null)!==[]||($ok['publication_allowed']??true)!==false||($ok['indexation_allowed']??true)!==false||($ok['sitemap_allowed']??true)!==false||($ok['route_creation_allowed']??true)!==false) exit(2);

$bad=$bundle; $bad['items'][0]['claims'][0]['value']='wrong'; try{v2_seo_seasonal_content_packet($content,$bad,$key);exit(3);}catch(InvalidArgumentException $e){}
$bad=$bundle; $bad['items'][0]['claims'][0]['source_id']='other'; try{v2_seo_seasonal_content_packet($content,$bad,$key);exit(4);}catch(InvalidArgumentException $e){}
$bad=$bundle; $bad['items'][0]['claims'][0]['geography_scope']['country_id']=4; try{v2_seo_seasonal_content_packet($content,$bad,$key);exit(5);}catch(InvalidArgumentException $e){}
$bad=$bundle; $bad['items'][0]['indexation_allowed']=true; try{v2_seo_seasonal_content_packet($content,$bad,$key);exit(6);}catch(InvalidArgumentException $e){}
try{v2_seo_seasonal_content_packet($content,$bundle,'month:1:4:2026-09');exit(7);}catch(InvalidArgumentException $e){}
$bad=$bundle; $bad['items'][]=$item; try{v2_seo_seasonal_content_packet($content,$bad,$key);exit(8);}catch(InvalidArgumentException $e){}
$bad=$bundle; $bad['items'][0]['evidence_valid_until_epoch']=0; try{v2_seo_seasonal_content_packet($content,$bad,$key);exit(9);}catch(InvalidArgumentException $e){}
$bad=$content; $bad['publication_allowed']=true; try{v2_seo_seasonal_content_packet($bad,$bundle,$key);exit(10);}catch(InvalidArgumentException $e){}

echo "SEO_SEASONAL_CONTENT_PACKET_OK\n";
